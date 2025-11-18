<?php
/**
 * Products and Pricing Plans Configuration
 *
 * Define your Stripe products and prices here.
 * Update the price IDs with your actual Stripe price IDs.
 */

// Pricing Plans
define('PRICING_PLANS', [
    'free' => [
        'name' => 'Free',
        'description' => 'Perfect for getting started',
        'price' => 0,
        'interval' => 'month',
        'stripe_price_id' => null, // No Stripe for free plan
        'features' => [
            '1 team member',
            'Basic features',
            'Community support'
        ],
        'limits' => [
            'team_members' => 1,
            'projects' => 3
        ]
    ],
    'base' => [
        'name' => 'Base',
        'description' => 'For small teams',
        'price' => 8,
        'interval' => 'month',
        'stripe_price_id' => 'price_XXXXXXXXXXXXX', // Replace with your Stripe price ID
        'stripe_product_id' => 'prod_XXXXXXXXXXXXX', // Replace with your Stripe product ID
        'featured' => true, // Highlight this plan
        'features' => [
            'Up to 5 team members',
            'All basic features',
            'Priority support',
            'API access'
        ],
        'limits' => [
            'team_members' => 5,
            'projects' => 10
        ]
    ],
    'plus' => [
        'name' => 'Plus',
        'description' => 'For growing teams',
        'price' => 12,
        'interval' => 'month',
        'stripe_price_id' => 'price_YYYYYYYYYYYYY', // Replace with your Stripe price ID
        'stripe_product_id' => 'prod_YYYYYYYYYYYYY', // Replace with your Stripe product ID
        'features' => [
            'Unlimited team members',
            'All base features',
            'Advanced analytics',
            '24/7 premium support',
            'Custom integrations'
        ],
        'limits' => [
            'team_members' => -1, // -1 = unlimited
            'projects' => -1
        ]
    ]
]);

// Annual pricing (optional - multiply by 10 for 2 months free)
define('ANNUAL_DISCOUNT', 2); // 2 months free

// Currency
define('CURRENCY', 'usd');
define('CURRENCY_SYMBOL', '$');

// Trial period (days)
define('TRIAL_PERIOD_DAYS', 14);

/**
 * Get plan by Stripe price ID
 */
function getPlanByPriceId($priceId) {
    foreach (PRICING_PLANS as $key => $plan) {
        if (isset($plan['stripe_price_id']) && $plan['stripe_price_id'] === $priceId) {
            return array_merge(['key' => $key], $plan);
        }
    }
    return null;
}

/**
 * Get plan by key
 */
function getPlan($key) {
    if (isset(PRICING_PLANS[$key])) {
        return array_merge(['key' => $key], PRICING_PLANS[$key]);
    }
    return null;
}

/**
 * Check if team can add more members based on plan
 */
function canAddTeamMember($planKey, $currentCount) {
    $plan = getPlan($planKey);
    if (!$plan) return false;

    $limit = $plan['limits']['team_members'] ?? 1;
    return $limit === -1 || $currentCount < $limit;
}

/**
 * Get all plans for pricing page
 */
function getAllPlans() {
    $plans = [];
    foreach (PRICING_PLANS as $key => $plan) {
        $plans[] = array_merge(['key' => $key], $plan);
    }
    return $plans;
}
