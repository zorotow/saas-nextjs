"""
Products and Pricing Plans Configuration

Define your Stripe products and prices here.
Update the price IDs with your actual Stripe price IDs.
"""

PRICING_PLANS = {
    'free': {
        'name': 'Free',
        'description': 'Perfect for getting started',
        'price': 0,
        'interval': 'month',
        'stripe_price_id': None,
        'features': [
            '1 team member',
            'Basic features',
            'Community support'
        ],
        'limits': {
            'team_members': 1,
            'projects': 3
        }
    },
    'base': {
        'name': 'Base',
        'description': 'For small teams',
        'price': 8,
        'interval': 'month',
        'stripe_price_id': 'price_XXXXXXXXXXXXX',  # Replace with your Stripe price ID
        'stripe_product_id': 'prod_XXXXXXXXXXXXX',  # Replace with your Stripe product ID
        'featured': True,
        'features': [
            'Up to 5 team members',
            'All basic features',
            'Priority support',
            'API access'
        ],
        'limits': {
            'team_members': 5,
            'projects': 10
        }
    },
    'plus': {
        'name': 'Plus',
        'description': 'For growing teams',
        'price': 12,
        'interval': 'month',
        'stripe_price_id': 'price_YYYYYYYYYYYYY',  # Replace with your Stripe price ID
        'stripe_product_id': 'prod_YYYYYYYYYYYYY',  # Replace with your Stripe product ID
        'features': [
            'Unlimited team members',
            'All base features',
            'Advanced analytics',
            '24/7 premium support',
            'Custom integrations'
        ],
        'limits': {
            'team_members': -1,
            'projects': -1
        }
    }
}

CURRENCY = 'usd'
CURRENCY_SYMBOL = '$'
TRIAL_PERIOD_DAYS = 14


def get_plan_by_price_id(price_id):
    """Get plan by Stripe price ID"""
    for key, plan in PRICING_PLANS.items():
        if plan.get('stripe_price_id') == price_id:
            return {**plan, 'key': key}
    return None


def get_plan(key):
    """Get plan by key"""
    if key in PRICING_PLANS:
        return {**PRICING_PLANS[key], 'key': key}
    return None


def get_all_plans():
    """Get all plans for pricing page"""
    return [{**plan, 'key': key} for key, plan in PRICING_PLANS.items()]


def can_add_team_member(plan_key, current_count):
    """Check if team can add more members based on plan"""
    plan = get_plan(plan_key)
    if not plan:
        return False
    limit = plan.get('limits', {}).get('team_members', 1)
    return limit == -1 or current_count < limit
