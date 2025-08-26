<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Subscription;

// Include our custom Stripe library
require_once base_path('lib/stripe/init.php');

class StripeController extends Controller
{
    public function __construct()
    {
        // Set Stripe secret key
        \Stripe\Stripe::setApiKey(env('STRIPE_SECRET_KEY'));
    }

    /**
     * Create Stripe checkout session
     */
    public function createCheckoutSession(Request $request)
    {
        try {
            $request->validate([
                'plan_id' => 'required|string',
                'user_id' => 'required|integer',
                'user_email' => 'required|email',
                'success_url' => 'required|url',
                'cancel_url' => 'required|url'
            ]);

            $user = User::findOrFail($request->user_id);

            // Plan configuration
            $plans = [
                'basic_monthly' => [
                    'price' => 900, // 9 DH in cents
                    'currency' => 'mad',
                    'interval' => 'month',
                    'name' => 'Basic Mensuel'
                ],
                'family_monthly' => [
                    'price' => 1900, // 19 DH in cents
                    'currency' => 'mad',
                    'interval' => 'month',
                    'name' => 'Famille Mensuel'
                ],
                'basic_yearly' => [
                    'price' => 10800, // 108 DH in cents
                    'currency' => 'mad',
                    'interval' => 'year',
                    'name' => 'Basic Annuel'
                ],
                'family_yearly' => [
                    'price' => 21500, // 215 DH in cents
                    'currency' => 'mad',
                    'interval' => 'year',
                    'name' => 'Famille Annuel'
                ]
            ];

            if (!isset($plans[$request->plan_id])) {
                return response()->json(['error' => 'Plan invalide'], 400);
            }

            $plan = $plans[$request->plan_id];

            // Create or get Stripe customer
            $stripeCustomer = $this->getOrCreateStripeCustomer($user);

            // Create checkout session
            $session = \Stripe\Checkout\Session::create([
                'customer' => $stripeCustomer->id,
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => $plan['currency'],
                        'product_data' => [
                            'name' => $plan['name'],
                            'description' => 'Abonnement ViSanté - ' . $plan['name']
                        ],
                        'unit_amount' => $plan['price'],
                        'recurring' => [
                            'interval' => $plan['interval']
                        ]
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'subscription',
                'success_url' => $request->success_url . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $request->cancel_url,
                'metadata' => [
                    'user_id' => $user->id,
                    'plan_id' => $request->plan_id
                ]
            ]);

            return response()->json([
                'id' => $session->id,
                'url' => $session->url
            ]);

        } catch (\Exception $e) {
            Log::error('Stripe checkout session creation failed: ' . $e->getMessage());
            return response()->json(['error' => 'Erreur lors de la création de la session de paiement: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Verify checkout session and update user subscription
     */
    public function verifySession(Request $request)
    {
        try {
            $request->validate([
                'session_id' => 'required|string'
            ]);

            $session = \Stripe\Checkout\Session::retrieve($request->session_id);

            if (!$session || $session->payment_status !== 'paid') {
                return response()->json(['error' => 'Session invalide ou paiement non confirmé'], 400);
            }

            $userId = $session->metadata->user_id;
            $planId = $session->metadata->plan_id;

            $user = User::findOrFail($userId);
            $subscription = \Stripe\Subscription::retrieve($session->subscription);

            // Update user subscription
            $this->updateUserSubscription($user, $subscription, $planId);

            return response()->json([
                'success' => true,
                'plan_name' => $this->getPlanName($planId),
                'plan_type' => explode('_', $planId)[0],
                'amount' => $subscription->items->data[0]->price->unit_amount / 100,
                'interval' => $subscription->items->data[0]->price->recurring->interval
            ]);

        } catch (\Exception $e) {
            Log::error('Session verification failed: ' . $e->getMessage());
            return response()->json(['error' => 'Erreur lors de la vérification'], 500);
        }
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(Request $request)
    {
        try {
            $request->validate([
                'subscription_id' => 'required|string'
            ]);

            $subscription = \Stripe\Subscription::cancel($request->subscription_id);

            // Update local subscription record
            $localSubscription = Subscription::where('stripe_subscription_id', $request->subscription_id)->first();
            if ($localSubscription) {
                $localSubscription->update([
                    'cancel_at_period_end' => true,
                    'canceled_at' => now()
                ]);
            }

            return response()->json(['success' => true, 'message' => 'Abonnement annulé avec succès']);

        } catch (\Exception $e) {
            Log::error('Subscription cancellation failed: ' . $e->getMessage());
            return response()->json(['error' => 'Erreur lors de l\'annulation'], 500);
        }
    }

    /**
     * Get customer portal URL
     */
    public function customerPortal(Request $request)
    {
        try {
            $request->validate([
                'customer_id' => 'required|string',
                'return_url' => 'required|url'
            ]);

            $session = \Stripe\BillingPortal::create([
                'customer' => $request->customer_id,
                'return_url' => $request->return_url
            ]);

            return response()->json(['url' => $session->url]);

        } catch (\Exception $e) {
            Log::error('Customer portal creation failed: ' . $e->getMessage());
            return response()->json(['error' => 'Erreur lors de la création du portail client'], 500);
        }
    }

    /**
     * Get subscription details
     */
    public function getSubscriptionDetails($userId)
    {
        try {
            $user = User::findOrFail($userId);
            $subscription = $user->subscription;

            if (!$subscription) {
                return response()->json([
                    'is_subscribed' => false,
                    'subscription_type' => null
                ]);
            }

            return response()->json([
                'is_subscribed' => true,
                'subscription_type' => $subscription->plan_type,
                'status' => $subscription->status,
                'amount' => $subscription->amount,
                'billing_interval' => $subscription->billing_interval,
                'current_period_end' => $subscription->current_period_end,
                'cancel_at_period_end' => $subscription->cancel_at_period_end,
                'stripe_customer_id' => $subscription->stripe_customer_id,
                'stripe_subscription_id' => $subscription->stripe_subscription_id,
                'created_at' => $subscription->created_at,
                'family_members_count' => $user->familyMembers()->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Get subscription details failed: ' . $e->getMessage());
            return response()->json(['error' => 'Erreur lors de la récupération des détails'], 500);
        }
    }

    /**
     * Handle Stripe webhooks
     */
    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = env('STRIPE_WEBHOOK_SECRET');

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, env('STRIPE_WEBHOOK_SECRET'));
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::error('Webhook signature verification failed: ' . $e->getMessage());
            return response('', 400);
        }

        // Handle the event
        switch ($event['type']) {
            case 'customer.subscription.created':
                $this->handleSubscriptionCreated($event['data']['object']);
                break;
            case 'customer.subscription.updated':
                $this->handleSubscriptionUpdated($event['data']['object']);
                break;
            case 'customer.subscription.deleted':
                $this->handleSubscriptionDeleted($event['data']['object']);
                break;
            case 'invoice.payment_succeeded':
                $this->handlePaymentSucceeded($event['data']['object']);
                break;
            case 'invoice.payment_failed':
                $this->handlePaymentFailed($event['data']['object']);
                break;
            default:
                Log::info('Unhandled webhook event type: ' . $event['type']);
        }

        return response('', 200);
    }

    /**
     * Get or create Stripe customer
     */
    private function getOrCreateStripeCustomer(User $user)
    {
        if ($user->stripe_customer_id) {
            try {
                return \Stripe\Customer::retrieve($user->stripe_customer_id);
            } catch (\Exception $e) {
                // Customer doesn't exist, create new one
            }
        }

        $customer = \Stripe\Customer::create([
            'email' => $user->email,
            'name' => $user->name,
            'metadata' => [
                'user_id' => $user->id
            ]
        ]);

        $user->update(['stripe_customer_id' => $customer->id]);

        return $customer;
    }

    /**
     * Update user subscription
     */
    private function updateUserSubscription(User $user, $stripeSubscription, $planId)
    {
        $planType = explode('_', $planId)[0]; // 'basic' or 'family'
        $interval = explode('_', $planId)[1]; // 'monthly' or 'yearly'

        $subscriptionData = [
            'user_id' => $user->id,
            'stripe_subscription_id' => $stripeSubscription->id,
            'stripe_customer_id' => $stripeSubscription->customer,
            'plan_id' => $planId,
            'plan_type' => $planType,
            'status' => $stripeSubscription->status,
            'amount' => $stripeSubscription->items->data[0]->price->unit_amount / 100,
            'billing_interval' => $interval === 'yearly' ? 'year' : 'month',
            'current_period_start' => date('Y-m-d H:i:s', $stripeSubscription->current_period_start),
            'current_period_end' => date('Y-m-d H:i:s', $stripeSubscription->current_period_end),
            'cancel_at_period_end' => $stripeSubscription->cancel_at_period_end ?? false
        ];

        Subscription::updateOrCreate(
            ['user_id' => $user->id],
            $subscriptionData
        );

        // Update user subscription status
        $user->update([
            'is_subscribed' => true,
            'subscription_type' => $planType
        ]);
    }

    /**
     * Handle subscription created webhook
     */
    private function handleSubscriptionCreated($subscription)
    {
        Log::info('Subscription created: ' . $subscription['id']);
        // Additional logic if needed
    }

    /**
     * Handle subscription updated webhook
     */
    private function handleSubscriptionUpdated($subscription)
    {
        $localSubscription = Subscription::where('stripe_subscription_id', $subscription['id'])->first();
        
        if ($localSubscription) {
            $localSubscription->update([
                'status' => $subscription['status'],
                'current_period_start' => date('Y-m-d H:i:s', $subscription['current_period_start']),
                'current_period_end' => date('Y-m-d H:i:s', $subscription['current_period_end']),
                'cancel_at_period_end' => $subscription['cancel_at_period_end'] ?? false
            ]);

            // Update user status
            $user = $localSubscription->user;
            $user->update([
                'is_subscribed' => $subscription['status'] === 'active'
            ]);
        }
    }

    /**
     * Handle subscription deleted webhook
     */
    private function handleSubscriptionDeleted($subscription)
    {
        $localSubscription = Subscription::where('stripe_subscription_id', $subscription['id'])->first();
        
        if ($localSubscription) {
            $localSubscription->update(['status' => 'canceled']);
            
            // Update user status
            $user = $localSubscription->user;
            $user->update([
                'is_subscribed' => false,
                'subscription_type' => null
            ]);
        }
    }

    /**
     * Handle payment succeeded webhook
     */
    private function handlePaymentSucceeded($invoice)
    {
        if ($invoice['subscription']) {
            $subscription = Subscription::where('stripe_subscription_id', $invoice['subscription'])->first();
            if ($subscription) {
                $subscription->update(['status' => 'active']);
                $subscription->user->update(['is_subscribed' => true]);
            }
        }
    }

    /**
     * Handle payment failed webhook
     */
    private function handlePaymentFailed($invoice)
    {
        if ($invoice['subscription']) {
            $subscription = Subscription::where('stripe_subscription_id', $invoice['subscription'])->first();
            if ($subscription) {
                $subscription->update(['status' => 'past_due']);
            }
        }
    }

    /**
     * Get plan name by ID
     */
    private function getPlanName($planId)
    {
        $names = [
            'basic_monthly' => 'Basic Mensuel',
            'family_monthly' => 'Famille Mensuel',
            'basic_yearly' => 'Basic Annuel',
            'family_yearly' => 'Famille Annuel'
        ];

        return $names[$planId] ?? 'Plan inconnu';
    }
}
