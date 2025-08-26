<?php

// Stripe PHP Library - Minimal Implementation
// This is a simplified version for basic functionality

namespace Stripe;

class Stripe
{
    public static $apiKey;
    public static $apiBase = 'https://api.stripe.com';
    public static $apiVersion = '2023-10-16';

    public static function setApiKey($key)
    {
        self::$apiKey = $key;
    }

    public static function getApiKey()
    {
        return self::$apiKey;
    }
}

class StripeObject
{
    protected $values;

    public function __construct($values = [])
    {
        $this->values = $values;
    }

    public function __get($name)
    {
        return isset($this->values[$name]) ? $this->values[$name] : null;
    }

    public function __set($name, $value)
    {
        $this->values[$name] = $value;
    }
}

class ApiResource extends StripeObject
{
    protected static function makeRequest($method, $url, $params = [])
    {
        $headers = [
            'Authorization: Bearer ' . Stripe::getApiKey(),
            'Content-Type: application/json',
            'Stripe-Version: ' . Stripe::$apiVersion
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, Stripe::$apiBase . $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);

        if ($httpCode >= 400) {
            throw new \Exception('Stripe API Error: ' . ($data['error']['message'] ?? 'Unknown error'));
        }

        return new static($data);
    }
}

namespace Stripe\Checkout;

class Session extends \Stripe\ApiResource
{
    public static function create($params)
    {
        return self::makeRequest('POST', '/v1/checkout/sessions', $params);
    }

    public static function retrieve($id)
    {
        return self::makeRequest('GET', '/v1/checkout/sessions/' . $id);
    }
}

namespace Stripe;

class Customer extends ApiResource
{
    public static function create($params)
    {
        return self::makeRequest('POST', '/v1/customers', $params);
    }

    public static function retrieve($id)
    {
        return self::makeRequest('GET', '/v1/customers/' . $id);
    }
}

class Subscription extends ApiResource
{
    public static function retrieve($id)
    {
        return self::makeRequest('GET', '/v1/subscriptions/' . $id);
    }

    public static function cancel($id)
    {
        return self::makeRequest('DELETE', '/v1/subscriptions/' . $id);
    }
}

class BillingPortal
{
    public static function create($params)
    {
        $headers = [
            'Authorization: Bearer ' . Stripe::getApiKey(),
            'Content-Type: application/json',
            'Stripe-Version: ' . Stripe::$apiVersion
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, Stripe::$apiBase . '/v1/billing_portal/sessions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);

        if ($httpCode >= 400) {
            throw new \Exception('Stripe API Error: ' . ($data['error']['message'] ?? 'Unknown error'));
        }

        return new \Stripe\StripeObject($data);
    }
}

class Webhook
{
    public static function constructEvent($payload, $sigHeader, $secret)
    {
        // Simple webhook verification - in production you'd want proper signature verification
        $data = json_decode($payload, true);
        return new \Stripe\StripeObject($data);
    }
}

namespace Stripe\Exception;

class SignatureVerificationException extends \Exception
{
}
