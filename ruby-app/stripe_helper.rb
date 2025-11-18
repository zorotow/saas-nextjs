# Stripe Helper for Ruby/Sinatra
# Handles Stripe API calls using net/http (no external gem required)

require 'net/http'
require 'uri'
require 'json'
require 'openssl'

class StripeHelper
  STRIPE_API_BASE = 'https://api.stripe.com/v1'.freeze

  def initialize
    @secret_key = ENV['STRIPE_SECRET_KEY'] || ''
    @webhook_secret = ENV['STRIPE_WEBHOOK_SECRET'] || ''
  end

  def create_checkout_session(team_id:, price_id:, success_url:, cancel_url:)
    return { 'error' => 'Stripe is not configured' } if @secret_key.empty?

    params = {
      'payment_method_types[]' => 'card',
      'line_items[0][price]' => price_id,
      'line_items[0][quantity]' => '1',
      'mode' => 'subscription',
      'success_url' => success_url,
      'cancel_url' => cancel_url,
      'client_reference_id' => team_id.to_s
    }

    response = api_request('POST', '/checkout/sessions', params)

    if response['error']
      { 'error' => response['error']['message'] || 'Failed to create checkout session' }
    else
      { 'sessionId' => response['id'], 'url' => response['url'] }
    end
  end

  def create_portal_session(customer_id:, return_url:)
    return { 'error' => 'Stripe is not configured' } if @secret_key.empty?

    params = {
      'customer' => customer_id,
      'return_url' => return_url
    }

    response = api_request('POST', '/billing_portal/sessions', params)

    if response['error']
      { 'error' => response['error']['message'] || 'Failed to create portal session' }
    else
      { 'url' => response['url'] }
    end
  end

  def retrieve_subscription(subscription_id)
    return nil if @secret_key.empty?
    api_request('GET', "/subscriptions/#{subscription_id}")
  end

  def cancel_subscription(subscription_id)
    return { 'error' => 'Stripe is not configured' } if @secret_key.empty?
    api_request('DELETE', "/subscriptions/#{subscription_id}")
  end

  def verify_webhook(payload, signature)
    return nil if @webhook_secret.empty? || signature.nil?

    elements = {}
    signature.split(',').each do |part|
      key, value = part.split('=', 2)
      elements[key] = value
    end

    timestamp = elements['t']
    expected_sig = elements['v1']

    return nil unless timestamp && expected_sig

    # Check timestamp is within tolerance (5 minutes)
    if (Time.now.to_i - timestamp.to_i).abs > 300
      return nil
    end

    # Compute expected signature
    signed_payload = "#{timestamp}.#{payload}"
    computed_sig = OpenSSL::HMAC.hexdigest('SHA256', @webhook_secret, signed_payload)

    # Secure compare
    return nil unless secure_compare(computed_sig, expected_sig)

    JSON.parse(payload)
  rescue JSON::ParserError
    nil
  end

  private

  def api_request(method, endpoint, params = nil)
    uri = URI.parse("#{STRIPE_API_BASE}#{endpoint}")

    http = Net::HTTP.new(uri.host, uri.port)
    http.use_ssl = true
    http.verify_mode = OpenSSL::SSL::VERIFY_PEER

    case method
    when 'GET'
      request = Net::HTTP::Get.new(uri.request_uri)
    when 'POST'
      request = Net::HTTP::Post.new(uri.request_uri)
      request.set_form_data(params) if params
    when 'DELETE'
      request = Net::HTTP::Delete.new(uri.request_uri)
    end

    request['Authorization'] = "Bearer #{@secret_key}"
    request['Content-Type'] = 'application/x-www-form-urlencoded'

    response = http.request(request)
    JSON.parse(response.body)
  rescue StandardError => e
    { 'error' => { 'message' => e.message } }
  end

  def secure_compare(a, b)
    return false if a.bytesize != b.bytesize
    l = a.unpack("C*")
    r = b.unpack("C*")
    result = 0
    l.zip(r) { |x, y| result |= x ^ y }
    result == 0
  end
end
