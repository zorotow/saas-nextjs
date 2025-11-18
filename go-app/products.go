package main

// PricingPlan represents a pricing plan
type PricingPlan struct {
	Key            string
	Name           string
	Description    string
	Price          int
	Interval       string
	StripePriceID  string
	StripeProductID string
	Featured       bool
	Features       []string
	Limits         map[string]int
}

// PRICING_PLANS defines all available plans
var PRICING_PLANS = map[string]PricingPlan{
	"free": {
		Key:           "free",
		Name:          "Free",
		Description:   "Perfect for getting started",
		Price:         0,
		Interval:      "month",
		StripePriceID: "",
		Features: []string{
			"1 team member",
			"Basic features",
			"Community support",
		},
		Limits: map[string]int{
			"team_members": 1,
			"projects":     3,
		},
	},
	"base": {
		Key:             "base",
		Name:            "Base",
		Description:     "For small teams",
		Price:           8,
		Interval:        "month",
		StripePriceID:   "price_XXXXXXXXXXXXX", // Replace with your Stripe price ID
		StripeProductID: "prod_XXXXXXXXXXXXX",  // Replace with your Stripe product ID
		Featured:        true,
		Features: []string{
			"Up to 5 team members",
			"All basic features",
			"Priority support",
			"API access",
		},
		Limits: map[string]int{
			"team_members": 5,
			"projects":     10,
		},
	},
	"plus": {
		Key:             "plus",
		Name:            "Plus",
		Description:     "For growing teams",
		Price:           12,
		Interval:        "month",
		StripePriceID:   "price_YYYYYYYYYYYYY", // Replace with your Stripe price ID
		StripeProductID: "prod_YYYYYYYYYYYYY",  // Replace with your Stripe product ID
		Features: []string{
			"Unlimited team members",
			"All base features",
			"Advanced analytics",
			"24/7 premium support",
			"Custom integrations",
		},
		Limits: map[string]int{
			"team_members": -1,
			"projects":     -1,
		},
	},
}

const (
	CURRENCY        = "usd"
	CURRENCY_SYMBOL = "$"
	TRIAL_PERIOD_DAYS = 14
)

// GetPlanByPriceID returns a plan by its Stripe price ID
func GetPlanByPriceID(priceID string) *PricingPlan {
	for _, plan := range PRICING_PLANS {
		if plan.StripePriceID == priceID {
			return &plan
		}
	}
	return nil
}

// GetPlan returns a plan by key
func GetPlan(key string) *PricingPlan {
	if plan, ok := PRICING_PLANS[key]; ok {
		plan.Key = key
		return &plan
	}
	return nil
}

// GetAllPlans returns all plans
func GetAllPlans() []PricingPlan {
	plans := []PricingPlan{}
	for key, plan := range PRICING_PLANS {
		plan.Key = key
		plans = append(plans, plan)
	}
	return plans
}

// CanAddTeamMember checks if team can add more members based on plan
func CanAddTeamMember(planKey string, currentCount int) bool {
	plan := GetPlan(planKey)
	if plan == nil {
		return false
	}
	limit := plan.Limits["team_members"]
	return limit == -1 || currentCount < limit
}
