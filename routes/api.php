<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\EmailCheckController;
use App\Http\Controllers\Auth\ValidationController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AnnonceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SiteStatsController;
use App\Http\Controllers\MedecinController;
use App\Http\Controllers\OrganisationController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\StatisticsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Public routes
Route::post('/login', [LoginController::class, 'login']);
Route::post('/register', [RegisterController::class, 'register']);
Route::post('/check-email', [EmailCheckController::class, 'check']);
Route::post('/check-availability', [ValidationController::class, 'checkAvailability']);
Route::get('/annonces', [AnnonceController::class, 'publicIndex']);
Route::get('/annonces/{id}', [AnnonceController::class, 'show']);
// NEW: make site stats public
Route::get('/site-stats', [SiteStatsController::class, 'getStats']);
Route::post('/site-stats/bump', [SiteStatsController::class, 'bump']);

// Statistics routes for auto-incrementing counters
Route::get('/statistics', [StatisticsController::class, 'getStatistics']);
Route::post('/statistics/reset', [StatisticsController::class, 'resetStatistics']);

// Public search routes (no authentication required)
Route::get('/users', [UserController::class, 'publicSearch']);
Route::get('/medecins', [MedecinController::class, 'publicIndex']);
Route::get('/medecins/{id}', [MedecinController::class, 'publicShow']);
Route::get('/organisations', [OrganisationController::class, 'index']);
Route::get('/organisations/{id}', [OrganisationController::class, 'publicShow']);
Route::get('/users/{id}', [UserController::class, 'publicShow']);

// Universal profile endpoints - auto-detect profile type
Route::get('/profiles/{id}', [App\Http\Controllers\ProfileController::class, 'show']);
Route::get('/professionals/{id}', [App\Http\Controllers\ProfessionalController::class, 'show']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    // User info
    Route::get('/user', [UserController::class, 'user']);
    
    // User profile
    Route::get('/user/profile', [UserController::class, 'profile']);
    Route::put('/user/profile', [UserController::class, 'updateProfile']);
    Route::post('/user/profile/update', [UserController::class, 'updateProfile']); // <-- ADD THIS LINE
    Route::post('/user/profile/update-avatar', [UserController::class, 'updateProfileAvatar']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    // Doctor routes
    Route::prefix('doctor')->group(function () {
        Route::get('/stats', [DashboardController::class, 'doctorStats']);
        Route::get('/appointments', [DashboardController::class, 'doctorAppointments']);
        Route::get('/appointments/{id}', [DashboardController::class, 'getAppointmentDetails']);
        Route::put('/appointments/{id}/status', [DashboardController::class, 'updateAppointmentStatus']);

        Route::get('/annonces', [AnnonceController::class, 'index']);
        Route::post('/annonces', [AnnonceController::class, 'store']);
        Route::post('/annonces/activate-all', [AnnonceController::class, 'activateAll']);
        Route::post('/annonces/deactivate-all', [AnnonceController::class, 'deactivateAll']);
        Route::get('/annonces/{id}', [AnnonceController::class, 'show']);
        Route::post('/annonces/{id}', [AnnonceController::class, 'update']);
        Route::put('/annonces/{id}/toggle-status', [AnnonceController::class, 'toggleStatus']);
        Route::delete('/annonces/{id}', [AnnonceController::class, 'destroy']);
    });

    // Patient routes
    Route::prefix('patient')->group(function () {
        Route::post('/appointments', [AppointmentController::class, 'store']);
        Route::get('/appointments', [AppointmentController::class, 'index']);
    });

    // Medecins
    Route::get('/medecins', [MedecinController::class, 'index']);

    // Organisations (authenticated)
    Route::get('/organisations/debug', [OrganisationController::class, 'debug']);

    // Appointment booking routes (aliases for rendezvous)
    Route::post('/rendezvous', [AppointmentController::class, 'store']);
    Route::get('/rendezvous/{id}', [AppointmentController::class, 'show']);
    Route::get('/appointments/booked-slots/{doctorId}', [AppointmentController::class, 'getBookedSlots']); // Add alias for frontend compatibility
    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::get('/appointments/{id}', [AppointmentController::class, 'show']);
    Route::get('/rendezvous/{id}', [AppointmentController::class, 'show']); // Add alias for frontend compatibility
    Route::put('/appointments/{id}', [AppointmentController::class, 'update']);
    Route::post('/appointments/{id}/cancel', [AppointmentController::class, 'cancel']);
    Route::delete('/appointments/{id}', [AppointmentController::class, 'destroy']);
    Route::get('/doctors/{id}/available-hours', [MedecinController::class, 'availableHours']);
    
    // Stripe subscription routes
    Route::prefix('stripe')->group(function () {
        Route::post('/create-checkout-session', [StripeController::class, 'createCheckoutSession']);
        Route::post('/verify-session', [StripeController::class, 'verifySession']);
        Route::post('/cancel-subscription', [StripeController::class, 'cancelSubscription']);
        Route::post('/customer-portal', [StripeController::class, 'customerPortal']);
        Route::get('/subscription-details/{userId}', [StripeController::class, 'getSubscriptionDetails']);
    });

    // Admin routes
    Route::get('/admin/users', [UserController::class, 'index']);
    Route::post('/admin/users', [UserController::class, 'store']);
    Route::put('/admin/users/{id}', [UserController::class, 'update']);
    Route::delete('/admin/users/{id}', [UserController::class, 'destroy']);
    Route::get('/roles', [UserController::class, 'roles']);
});

// Stripe webhook (public route for Stripe to call)
Route::post('/stripe/webhook', [StripeController::class, 'handleWebhook']);

// Public doctor routes
Route::get('/medecins/{id}', [MedecinController::class, 'show']);
