<?php
/**
 * Stripe Helper Class
 * Simple Stripe integration without Composer
 * Uses Stripe API directly via cURL
 */

class StripeHelper {
    private $secretKey;
    private $webhookSecret;
    private $apiBase = 'https://api.stripe.com/v1';

    public function __construct() {
        $this->secretKey = STRIPE_SECRET_KEY;
        $this->webhookSecret = STRIPE_WEBHOOK_SECRET;
    }

    /**
     * Make API request to Stripe
     */
    private function request($method, $endpoint, $data = []) {
        $url = $this->apiBase . $endpoint;

        $ch = curl_init();

        $headers = [
            'Authorization: Bearer ' . $this->secretKey,
            'Content-Type: application/x-www-form-urlencoded'
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode >= 400) {
            throw new Exception($result['error']['message'] ?? 'Stripe API error');
        }

        return $result;
    }

    /**
     * Create or get Stripe customer
     */
    public function getOrCreateCustomer($team) {
        if (!empty($team['stripe_customer_id'])) {
            return $team['stripe_customer_id'];
        }

        // Create new customer
        $customer = $this->request('POST', '/customers', [
            'metadata[team_id]' => $team['id']
        ]);

        // Update team with customer ID
        $db = Database::getInstance();
        $db->update('teams', [
            'stripe_customer_id' => $customer['id']
        ], 'id = ?', [$team['id']]);

        return $customer['id'];
    }

    /**
     * Create checkout session
     */
    public function createCheckoutSession($team, $priceId) {
        $customerId = $this->getOrCreateCustomer($team);

        $session = $this->request('POST', '/checkout/sessions', [
            'customer' => $customerId,
            'payment_method_types[]' => 'card',
            'line_items[0][price]' => $priceId,
            'line_items[0][quantity]' => 1,
            'mode' => 'subscription',
            'success_url' => APP_URL . '/dashboard?success=true',
            'cancel_url' => APP_URL . '/pricing?canceled=true'
        ]);

        return $session;
    }

    /**
     * Create billing portal session
     */
    public function createPortalSession($customerId) {
        $session = $this->request('POST', '/billing_portal/sessions', [
            'customer' => $customerId,
            'return_url' => APP_URL . '/dashboard'
        ]);

        return $session;
    }

    /**
     * Handle webhook
     */
    public function handleWebhook($payload, $sigHeader) {
        // Verify webhook signature
        if ($this->webhookSecret) {
            $this->verifyWebhookSignature($payload, $sigHeader);
        }

        $event = json_decode($payload, true);

        switch ($event['type']) {
            case 'checkout.session.completed':
                $this->handleCheckoutCompleted($event['data']['object']);
                break;

            case 'customer.subscription.updated':
            case 'customer.subscription.deleted':
                $this->handleSubscriptionChange($event['data']['object']);
                break;

            case 'invoice.payment_succeeded':
                $this->handleInvoicePaid($event['data']['object']);
                break;

            case 'invoice.payment_failed':
                $this->handleInvoiceFailed($event['data']['object']);
                break;
        }

        return true;
    }

    /**
     * Verify webhook signature
     */
    private function verifyWebhookSignature($payload, $sigHeader) {
        $parts = explode(',', $sigHeader);
        $timestamp = null;
        $signatures = [];

        foreach ($parts as $part) {
            list($key, $value) = explode('=', trim($part), 2);
            if ($key === 't') {
                $timestamp = $value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        if (!$timestamp || empty($signatures)) {
            throw new Exception('Invalid signature header');
        }

        // Check timestamp (allow 5 minute tolerance)
        if (abs(time() - $timestamp) > 300) {
            throw new Exception('Timestamp outside tolerance');
        }

        $signedPayload = $timestamp . '.' . $payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $this->webhookSecret);

        $valid = false;
        foreach ($signatures as $sig) {
            if (hash_equals($expectedSignature, $sig)) {
                $valid = true;
                break;
            }
        }

        if (!$valid) {
            throw new Exception('Invalid signature');
        }

        return true;
    }

    /**
     * Handle checkout completed
     */
    private function handleCheckoutCompleted($session) {
        $customerId = $session['customer'];
        $subscriptionId = $session['subscription'];

        $teamModel = new Team();
        $team = $teamModel->findByStripeCustomerId($customerId);

        if ($team && $subscriptionId) {
            // Get subscription details
            $subscription = $this->request('GET', '/subscriptions/' . $subscriptionId);

            $teamModel->updateSubscription($team['id'], [
                'stripe_subscription_id' => $subscriptionId,
                'stripe_product_id' => $subscription['items']['data'][0]['price']['product'] ?? null,
                'plan_name' => $this->getPlanName($subscription),
                'subscription_status' => $subscription['status']
            ]);
        }
    }

    /**
     * Handle subscription change
     */
    private function handleSubscriptionChange($subscription) {
        $customerId = $subscription['customer'];

        $teamModel = new Team();
        $team = $teamModel->findByStripeCustomerId($customerId);

        if ($team) {
            $teamModel->updateSubscription($team['id'], [
                'stripe_subscription_id' => $subscription['id'],
                'stripe_product_id' => $subscription['items']['data'][0]['price']['product'] ?? null,
                'plan_name' => $this->getPlanName($subscription),
                'subscription_status' => $subscription['status']
            ]);
        }
    }

    /**
     * Handle invoice paid
     */
    private function handleInvoicePaid($invoice) {
        // Can add additional logic here like sending receipt emails
    }

    /**
     * Handle invoice failed
     */
    private function handleInvoiceFailed($invoice) {
        // Can add logic to notify user of payment failure
    }

    /**
     * Get plan name from subscription
     */
    private function getPlanName($subscription) {
        $priceId = $subscription['items']['data'][0]['price']['id'] ?? '';

        // Map price IDs to plan names
        // You should update this with your actual Stripe price IDs
        $plans = [
            'price_base' => 'Base',
            'price_plus' => 'Plus'
        ];

        return $plans[$priceId] ?? 'Unknown';
    }
}
