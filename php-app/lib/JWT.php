<?php
/**
 * Simple JWT Implementation
 * No external dependencies - works on shared hosting without Composer
 */

class JWT {

    /**
     * Generate a JWT token
     */
    public static function encode($payload, $secret, $algorithm = 'HS256') {
        $header = [
            'typ' => 'JWT',
            'alg' => $algorithm
        ];

        // Add issued at and expiration
        $payload['iat'] = time();
        if (!isset($payload['exp'])) {
            $payload['exp'] = time() + JWT_EXPIRY;
        }

        $headerEncoded = self::base64UrlEncode(json_encode($header));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));

        $signature = self::sign("$headerEncoded.$payloadEncoded", $secret, $algorithm);
        $signatureEncoded = self::base64UrlEncode($signature);

        return "$headerEncoded.$payloadEncoded.$signatureEncoded";
    }

    /**
     * Decode and verify a JWT token
     */
    public static function decode($token, $secret, $algorithms = ['HS256']) {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new Exception('Invalid token format');
        }

        list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;

        $header = json_decode(self::base64UrlDecode($headerEncoded), true);
        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);
        $signature = self::base64UrlDecode($signatureEncoded);

        if (!$header || !$payload) {
            throw new Exception('Invalid token encoding');
        }

        // Verify algorithm
        if (!isset($header['alg']) || !in_array($header['alg'], $algorithms)) {
            throw new Exception('Invalid algorithm');
        }

        // Verify signature
        $expectedSignature = self::sign("$headerEncoded.$payloadEncoded", $secret, $header['alg']);

        if (!hash_equals($signature, $expectedSignature)) {
            throw new Exception('Invalid signature');
        }

        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new Exception('Token has expired');
        }

        // Check not before
        if (isset($payload['nbf']) && $payload['nbf'] > time()) {
            throw new Exception('Token not yet valid');
        }

        return $payload;
    }

    /**
     * Create signature
     */
    private static function sign($data, $secret, $algorithm) {
        switch ($algorithm) {
            case 'HS256':
                return hash_hmac('sha256', $data, $secret, true);
            case 'HS384':
                return hash_hmac('sha384', $data, $secret, true);
            case 'HS512':
                return hash_hmac('sha512', $data, $secret, true);
            default:
                throw new Exception('Unsupported algorithm');
        }
    }

    /**
     * Base64 URL encode
     */
    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL decode
     */
    private static function base64UrlDecode($data) {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
