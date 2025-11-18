# Products and Pricing Plans Configuration
# Define your Stripe products and prices here.
# Update the price IDs with your actual Stripe price IDs.

PRICING_PLANS = {
  'free' => {
    'name' => 'Free',
    'description' => 'Perfect for getting started',
    'price' => 0,
    'interval' => 'month',
    'stripe_price_id' => nil,
    'features' => [
      '1 team member',
      'Basic features',
      'Community support'
    ],
    'limits' => {
      'team_members' => 1,
      'projects' => 3
    }
  },
  'base' => {
    'name' => 'Base',
    'description' => 'For small teams',
    'price' => 8,
    'interval' => 'month',
    'stripe_price_id' => 'price_XXXXXXXXXXXXX',  # Replace with your Stripe price ID
    'stripe_product_id' => 'prod_XXXXXXXXXXXXX',  # Replace with your Stripe product ID
    'featured' => true,
    'features' => [
      'Up to 5 team members',
      'All basic features',
      'Priority support',
      'API access'
    ],
    'limits' => {
      'team_members' => 5,
      'projects' => 10
    }
  },
  'plus' => {
    'name' => 'Plus',
    'description' => 'For growing teams',
    'price' => 12,
    'interval' => 'month',
    'stripe_price_id' => 'price_YYYYYYYYYYYYY',  # Replace with your Stripe price ID
    'stripe_product_id' => 'prod_YYYYYYYYYYYYY',  # Replace with your Stripe product ID
    'features' => [
      'Unlimited team members',
      'All base features',
      'Advanced analytics',
      '24/7 premium support',
      'Custom integrations'
    ],
    'limits' => {
      'team_members' => -1,
      'projects' => -1
    }
  }
}.freeze

CURRENCY = 'usd'.freeze
CURRENCY_SYMBOL = '$'.freeze
TRIAL_PERIOD_DAYS = 14

def get_plan_by_price_id(price_id)
  PRICING_PLANS.each do |key, plan|
    return plan.merge('key' => key) if plan['stripe_price_id'] == price_id
  end
  nil
end

def get_plan(key)
  return nil unless PRICING_PLANS.key?(key)
  PRICING_PLANS[key].merge('key' => key)
end

def get_all_plans
  PRICING_PLANS.map { |key, plan| plan.merge('key' => key) }
end

def can_add_team_member?(plan_key, current_count)
  plan = get_plan(plan_key)
  return false unless plan
  limit = plan.dig('limits', 'team_members') || 1
  limit == -1 || current_count < limit
end
