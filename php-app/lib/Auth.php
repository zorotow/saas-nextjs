<?php
/**
 * Authentication Helper Class
 */

class Auth {

    /**
     * Hash a password
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => SALT_ROUNDS]);
    }

    /**
     * Verify password
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * Create session token
     */
    public static function createToken($userId) {
        $payload = [
            'user' => ['id' => $userId],
            'exp' => time() + JWT_EXPIRY
        ];

        return JWT::encode($payload, AUTH_SECRET);
    }

    /**
     * Verify and decode session token
     */
    public static function verifyToken($token) {
        try {
            return JWT::decode($token, AUTH_SECRET);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Set session cookie
     */
    public static function setSession($userId) {
        $token = self::createToken($userId);
        $expires = time() + JWT_EXPIRY;

        setcookie('session', $token, [
            'expires' => $expires,
            'path' => '/',
            'secure' => APP_ENV === 'production',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        return $token;
    }

    /**
     * Get current session
     */
    public static function getSession() {
        if (!isset($_COOKIE['session'])) {
            return null;
        }

        $payload = self::verifyToken($_COOKIE['session']);

        if (!$payload || !isset($payload['user']['id'])) {
            return null;
        }

        return $payload;
    }

    /**
     * Get current user from session
     */
    public static function getUser() {
        $session = self::getSession();

        if (!$session) {
            return null;
        }

        $db = Database::getInstance();
        $user = $db->fetch(
            "SELECT * FROM users WHERE id = ? AND deleted_at IS NULL",
            [$session['user']['id']]
        );

        return $user ?: null;
    }

    /**
     * Get user with team info
     */
    public static function getUserWithTeam($userId) {
        $db = Database::getInstance();

        return $db->fetch(
            "SELECT u.*, tm.team_id
             FROM users u
             LEFT JOIN team_members tm ON u.id = tm.user_id
             WHERE u.id = ?
             LIMIT 1",
            [$userId]
        );
    }

    /**
     * Clear session
     */
    public static function clearSession() {
        setcookie('session', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => APP_ENV === 'production',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }

    /**
     * Check if user is authenticated
     */
    public static function check() {
        return self::getUser() !== null;
    }

    /**
     * Require authentication (redirect if not logged in)
     */
    public static function require() {
        if (!self::check()) {
            header('Location: /sign-in');
            exit;
        }
    }

    /**
     * Get client IP address
     */
    public static function getIpAddress() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? '';
        }
    }
}
