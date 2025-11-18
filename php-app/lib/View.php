<?php
/**
 * Simple View/Template Engine
 */

class View {
    private static $data = [];
    private static $layout = 'main';

    /**
     * Share data with all views
     */
    public static function share($key, $value) {
        self::$data[$key] = $value;
    }

    /**
     * Set layout
     */
    public static function layout($layout) {
        self::$layout = $layout;
    }

    /**
     * Render a view
     */
    public static function render($view, $data = [], $layout = null) {
        $data = array_merge(self::$data, $data);

        // Extract data to variables
        extract($data);

        // Capture view content
        ob_start();
        $viewPath = APP_ROOT . '/views/' . str_replace('.', '/', $view) . '.php';
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            throw new Exception("View not found: {$view}");
        }
        $content = ob_get_clean();

        // Render with layout
        $layoutName = $layout ?? self::$layout;
        if ($layoutName) {
            $layoutPath = APP_ROOT . '/views/layouts/' . $layoutName . '.php';
            if (file_exists($layoutPath)) {
                ob_start();
                include $layoutPath;
                return ob_get_clean();
            }
        }

        return $content;
    }

    /**
     * Escape HTML
     */
    public static function e($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }

    /**
     * Render partial
     */
    public static function partial($view, $data = []) {
        $data = array_merge(self::$data, $data);
        extract($data);

        $viewPath = APP_ROOT . '/views/' . str_replace('.', '/', $view) . '.php';
        if (file_exists($viewPath)) {
            include $viewPath;
        }
    }

    /**
     * Generate CSRF token
     */
    public static function csrf() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
    }

    /**
     * Verify CSRF token
     */
    public static function verifyCsrf() {
        $token = $_POST['csrf_token'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        if (!$token || !$sessionToken || !hash_equals($sessionToken, $token)) {
            return false;
        }
        return true;
    }

    /**
     * Get old input value (for form repopulation)
     */
    public static function old($key, $default = '') {
        return $_SESSION['old_input'][$key] ?? $default;
    }

    /**
     * Set old input
     */
    public static function setOld($data) {
        $_SESSION['old_input'] = $data;
    }

    /**
     * Clear old input
     */
    public static function clearOld() {
        unset($_SESSION['old_input']);
    }

    /**
     * Flash message
     */
    public static function flash($key, $message = null) {
        if ($message === null) {
            $value = $_SESSION['flash'][$key] ?? null;
            unset($_SESSION['flash'][$key]);
            return $value;
        }
        $_SESSION['flash'][$key] = $message;
    }
}
