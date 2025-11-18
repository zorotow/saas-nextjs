package main

import (
	"bytes"
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"math"
	"net/http"
	"net/url"
	"os"
	"strconv"
	"strings"
	"time"
)

// StripeHelper handles Stripe API operations
type StripeHelper struct {
	SecretKey     string
	WebhookSecret string
}

// NewStripeHelper creates a new Stripe helper instance
func NewStripeHelper() *StripeHelper {
	return &StripeHelper{
		SecretKey:     os.Getenv("STRIPE_SECRET_KEY"),
		WebhookSecret: os.Getenv("STRIPE_WEBHOOK_SECRET"),
	}
}

// CreateCheckoutSession creates a Stripe checkout session
func (s *StripeHelper) CreateCheckoutSession(teamID int, priceID, successURL, cancelURL string) map[string]interface{} {
	if s.SecretKey == "" {
		return map[string]interface{}{"error": "Stripe is not configured"}
	}

	data := url.Values{}
	data.Set("payment_method_types[]", "card")
	data.Set("line_items[0][price]", priceID)
	data.Set("line_items[0][quantity]", "1")
	data.Set("mode", "subscription")
	data.Set("success_url", successURL)
	data.Set("cancel_url", cancelURL)
	data.Set("client_reference_id", strconv.Itoa(teamID))

	resp, err := s.apiRequest("POST", "/checkout/sessions", data)
	if err != nil {
		return map[string]interface{}{"error": err.Error()}
	}

	if errMsg, ok := resp["error"].(map[string]interface{}); ok {
		if msg, ok := errMsg["message"].(string); ok {
			return map[string]interface{}{"error": msg}
		}
		return map[string]interface{}{"error": "Failed to create checkout session"}
	}

	return map[string]interface{}{
		"sessionId": resp["id"],
		"url":       resp["url"],
	}
}

// CreatePortalSession creates a Stripe billing portal session
func (s *StripeHelper) CreatePortalSession(customerID, returnURL string) map[string]interface{} {
	if s.SecretKey == "" {
		return map[string]interface{}{"error": "Stripe is not configured"}
	}

	data := url.Values{}
	data.Set("customer", customerID)
	data.Set("return_url", returnURL)

	resp, err := s.apiRequest("POST", "/billing_portal/sessions", data)
	if err != nil {
		return map[string]interface{}{"error": err.Error()}
	}

	if errMsg, ok := resp["error"].(map[string]interface{}); ok {
		if msg, ok := errMsg["message"].(string); ok {
			return map[string]interface{}{"error": msg}
		}
		return map[string]interface{}{"error": "Failed to create portal session"}
	}

	return map[string]interface{}{
		"url": resp["url"],
	}
}

// VerifyWebhook verifies and parses a Stripe webhook event
func (s *StripeHelper) VerifyWebhook(payload []byte, signature string) map[string]interface{} {
	if s.WebhookSecret == "" || signature == "" {
		return nil
	}

	// Parse signature header
	elements := make(map[string]string)
	for _, part := range strings.Split(signature, ",") {
		kv := strings.SplitN(part, "=", 2)
		if len(kv) == 2 {
			elements[kv[0]] = kv[1]
		}
	}

	timestamp := elements["t"]
	expectedSig := elements["v1"]

	if timestamp == "" || expectedSig == "" {
		return nil
	}

	// Check timestamp tolerance (5 minutes)
	ts, err := strconv.ParseInt(timestamp, 10, 64)
	if err != nil {
		return nil
	}

	if math.Abs(float64(time.Now().Unix()-ts)) > 300 {
		return nil
	}

	// Compute expected signature
	signedPayload := timestamp + "." + string(payload)
	mac := hmac.New(sha256.New, []byte(s.WebhookSecret))
	mac.Write([]byte(signedPayload))
	computedSig := hex.EncodeToString(mac.Sum(nil))

	// Secure compare
	if !hmac.Equal([]byte(computedSig), []byte(expectedSig)) {
		return nil
	}

	var event map[string]interface{}
	if err := json.Unmarshal(payload, &event); err != nil {
		return nil
	}

	return event
}

func (s *StripeHelper) apiRequest(method, endpoint string, data url.Values) (map[string]interface{}, error) {
	client := &http.Client{Timeout: 30 * time.Second}

	var req *http.Request
	var err error

	url := "https://api.stripe.com/v1" + endpoint

	if method == "POST" {
		req, err = http.NewRequest(method, url, strings.NewReader(data.Encode()))
		if err != nil {
			return nil, err
		}
		req.Header.Set("Content-Type", "application/x-www-form-urlencoded")
	} else {
		req, err = http.NewRequest(method, url, nil)
		if err != nil {
			return nil, err
		}
	}

	req.Header.Set("Authorization", "Bearer "+s.SecretKey)

	resp, err := client.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	body, err := io.ReadAll(resp.Body)
	if err != nil {
		return nil, err
	}

	var result map[string]interface{}
	if err := json.Unmarshal(body, &result); err != nil {
		return nil, err
	}

	return result, nil
}

// Stripe HTTP handlers

func stripeCheckoutHandler(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")

	if r.Method != "POST" {
		w.WriteHeader(http.StatusMethodNotAllowed)
		json.NewEncoder(w).Encode(map[string]string{"error": "Method not allowed"})
		return
	}

	user := getCurrentUser(r)
	if user == nil {
		w.WriteHeader(http.StatusUnauthorized)
		json.NewEncoder(w).Encode(map[string]string{"error": "Unauthorized"})
		return
	}

	userWithTeam := getUserWithTeam(user.ID)
	if userWithTeam == nil || !userWithTeam.TeamID.Valid {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]string{"error": "User is not part of a team"})
		return
	}

	var reqBody struct {
		PriceID string `json:"priceId"`
	}

	body, _ := io.ReadAll(r.Body)
	json.Unmarshal(body, &reqBody)

	if reqBody.PriceID == "" {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]string{"error": "Price ID is required"})
		return
	}

	stripe := NewStripeHelper()
	result := stripe.CreateCheckoutSession(
		int(userWithTeam.TeamID.Int64),
		reqBody.PriceID,
		config.AppURL+"/dashboard?success=subscribed",
		config.AppURL+"/pricing",
	)

	if _, ok := result["error"]; ok {
		w.WriteHeader(http.StatusBadRequest)
	}

	json.NewEncoder(w).Encode(result)
}

func stripePortalHandler(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")

	if r.Method != "POST" {
		w.WriteHeader(http.StatusMethodNotAllowed)
		json.NewEncoder(w).Encode(map[string]string{"error": "Method not allowed"})
		return
	}

	user := getCurrentUser(r)
	if user == nil {
		w.WriteHeader(http.StatusUnauthorized)
		json.NewEncoder(w).Encode(map[string]string{"error": "Unauthorized"})
		return
	}

	userWithTeam := getUserWithTeam(user.ID)
	if userWithTeam == nil || !userWithTeam.TeamID.Valid {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]string{"error": "User is not part of a team"})
		return
	}

	var customerID string
	err := db.QueryRow("SELECT stripe_customer_id FROM teams WHERE id = ?", userWithTeam.TeamID.Int64).Scan(&customerID)
	if err != nil || customerID == "" {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]string{"error": "No billing account found"})
		return
	}

	stripe := NewStripeHelper()
	result := stripe.CreatePortalSession(customerID, config.AppURL+"/dashboard")

	if _, ok := result["error"]; ok {
		w.WriteHeader(http.StatusBadRequest)
	}

	json.NewEncoder(w).Encode(result)
}

func stripeWebhookHandler(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")

	if r.Method != "POST" {
		w.WriteHeader(http.StatusMethodNotAllowed)
		json.NewEncoder(w).Encode(map[string]string{"error": "Method not allowed"})
		return
	}

	var buf bytes.Buffer
	io.Copy(&buf, r.Body)
	payload := buf.Bytes()

	sigHeader := r.Header.Get("Stripe-Signature")

	stripe := NewStripeHelper()
	event := stripe.VerifyWebhook(payload, sigHeader)

	if event == nil {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]string{"error": "Invalid signature"})
		return
	}

	eventType, _ := event["type"].(string)
	data, _ := event["data"].(map[string]interface{})
	object, _ := data["object"].(map[string]interface{})

	switch eventType {
	case "checkout.session.completed":
		teamID, _ := object["client_reference_id"].(string)
		customerID, _ := object["customer"].(string)
		subscriptionID, _ := object["subscription"].(string)

		if teamID != "" && customerID != "" {
			db.Exec(`
				UPDATE teams SET
					stripe_customer_id = ?,
					stripe_subscription_id = ?,
					subscription_status = 'active'
				WHERE id = ?
			`, customerID, subscriptionID, teamID)
		}

	case "customer.subscription.updated":
		subscriptionID, _ := object["id"].(string)
		status, _ := object["status"].(string)

		var planName string
		if items, ok := object["items"].(map[string]interface{}); ok {
			if dataArr, ok := items["data"].([]interface{}); ok && len(dataArr) > 0 {
				if item, ok := dataArr[0].(map[string]interface{}); ok {
					if price, ok := item["price"].(map[string]interface{}); ok {
						if priceID, ok := price["id"].(string); ok {
							if plan := GetPlanByPriceID(priceID); plan != nil {
								planName = plan.Name
							}
						}
					}
				}
			}
		}

		if planName != "" {
			db.Exec(`
				UPDATE teams SET
					subscription_status = ?,
					plan_name = ?
				WHERE stripe_subscription_id = ?
			`, status, planName, subscriptionID)
		} else {
			db.Exec(`
				UPDATE teams SET subscription_status = ?
				WHERE stripe_subscription_id = ?
			`, status, subscriptionID)
		}

	case "customer.subscription.deleted":
		subscriptionID, _ := object["id"].(string)

		db.Exec(`
			UPDATE teams SET
				subscription_status = 'canceled',
				plan_name = 'free'
			WHERE stripe_subscription_id = ?
		`, subscriptionID)

	case "invoice.payment_failed":
		subscriptionID, _ := object["subscription"].(string)

		db.Exec(`
			UPDATE teams SET subscription_status = 'past_due'
			WHERE stripe_subscription_id = ?
		`, subscriptionID)
	}

	json.NewEncoder(w).Encode(map[string]bool{"received": true})
}
