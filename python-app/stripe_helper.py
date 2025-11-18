"""
Stripe Helper Module
Handles all Stripe payment operations
"""

import json
import time
import hmac
import hashlib
import urllib.request
import urllib.parse
import urllib.error
from products import get_plan_by_price_id


class StripeHelper:
    def __init__(self, secret_key, webhook_secret=None):
        self.secret_key = secret_key
        self.webhook_secret = webhook_secret
        self.api_base = 'https://api.stripe.com/v1'

    def _request(self, method, endpoint, data=None):
        """Make API request to Stripe"""
        url = self.api_base + endpoint

        headers = {
            'Authorization': f'Bearer {self.secret_key}',
            'Content-Type': 'application/x-www-form-urlencoded'
        }

        if data:
            data = urllib.parse.urlencode(data).encode('utf-8')

        req = urllib.request.Request(url, data=data, headers=headers, method=method)

        try:
            with urllib.request.urlopen(req) as response:
                return json.loads(response.read().decode('utf-8'))
        except urllib.error.HTTPError as e:
            error_body = json.loads(e.read().decode('utf-8'))
            raise Exception(error_body.get('error', {}).get('message', 'Stripe API error'))

    def get_or_create_customer(self, team, db):
        """Create or get Stripe customer"""
        if team.get('stripe_customer_id'):
            return team['stripe_customer_id']

        # Create new customer
        customer = self._request('POST', '/customers', {
            'metadata[team_id]': team['id']
        })

        # Update team with customer ID
        cursor = db.cursor()
        cursor.execute(
            "UPDATE teams SET stripe_customer_id = %s WHERE id = %s",
            (customer['id'], team['id'])
        )
        db.commit()
        cursor.close()

        return customer['id']

    def create_checkout_session(self, team, price_id, app_url, db):
        """Create Stripe checkout session"""
        customer_id = self.get_or_create_customer(team, db)

        session = self._request('POST', '/checkout/sessions', {
            'customer': customer_id,
            'payment_method_types[]': 'card',
            'line_items[0][price]': price_id,
            'line_items[0][quantity]': '1',
            'mode': 'subscription',
            'success_url': f'{app_url}/dashboard?success=true',
            'cancel_url': f'{app_url}/pricing?canceled=true'
        })

        return session

    def create_portal_session(self, customer_id, app_url):
        """Create billing portal session"""
        session = self._request('POST', '/billing_portal/sessions', {
            'customer': customer_id,
            'return_url': f'{app_url}/dashboard'
        })
        return session

    def handle_webhook(self, payload, sig_header, db):
        """Handle Stripe webhook"""
        if self.webhook_secret:
            self._verify_webhook_signature(payload, sig_header)

        event = json.loads(payload)
        event_type = event.get('type')
        data_object = event.get('data', {}).get('object', {})

        if event_type == 'checkout.session.completed':
            self._handle_checkout_completed(data_object, db)
        elif event_type in ['customer.subscription.updated', 'customer.subscription.deleted']:
            self._handle_subscription_change(data_object, db)

        return True

    def _verify_webhook_signature(self, payload, sig_header):
        """Verify webhook signature"""
        parts = sig_header.split(',')
        timestamp = None
        signatures = []

        for part in parts:
            key, value = part.strip().split('=', 1)
            if key == 't':
                timestamp = value
            elif key == 'v1':
                signatures.append(value)

        if not timestamp or not signatures:
            raise Exception('Invalid signature header')

        if abs(time.time() - int(timestamp)) > 300:
            raise Exception('Timestamp outside tolerance')

        signed_payload = f'{timestamp}.{payload}'
        expected = hmac.new(
            self.webhook_secret.encode(),
            signed_payload.encode(),
            hashlib.sha256
        ).hexdigest()

        if not any(hmac.compare_digest(expected, sig) for sig in signatures):
            raise Exception('Invalid signature')

    def _handle_checkout_completed(self, session, db):
        """Handle checkout completed"""
        customer_id = session.get('customer')
        subscription_id = session.get('subscription')

        if not subscription_id:
            return

        cursor = db.cursor(dictionary=True)
        cursor.execute("SELECT id FROM teams WHERE stripe_customer_id = %s", (customer_id,))
        team = cursor.fetchone()

        if team:
            subscription = self._request('GET', f'/subscriptions/{subscription_id}')
            price_id = subscription['items']['data'][0]['price']['id']
            plan = get_plan_by_price_id(price_id)

            cursor.execute("""
                UPDATE teams SET
                    stripe_subscription_id = %s,
                    stripe_product_id = %s,
                    plan_name = %s,
                    subscription_status = %s
                WHERE id = %s
            """, (
                subscription_id,
                subscription['items']['data'][0]['price'].get('product'),
                plan['name'] if plan else 'Unknown',
                subscription['status'],
                team['id']
            ))
            db.commit()

        cursor.close()

    def _handle_subscription_change(self, subscription, db):
        """Handle subscription change"""
        customer_id = subscription.get('customer')

        cursor = db.cursor(dictionary=True)
        cursor.execute("SELECT id FROM teams WHERE stripe_customer_id = %s", (customer_id,))
        team = cursor.fetchone()

        if team:
            price_id = subscription['items']['data'][0]['price']['id']
            plan = get_plan_by_price_id(price_id)

            cursor.execute("""
                UPDATE teams SET
                    stripe_subscription_id = %s,
                    stripe_product_id = %s,
                    plan_name = %s,
                    subscription_status = %s
                WHERE id = %s
            """, (
                subscription['id'],
                subscription['items']['data'][0]['price'].get('product'),
                plan['name'] if plan else 'Unknown',
                subscription['status'],
                team['id']
            ))
            db.commit()

        cursor.close()
