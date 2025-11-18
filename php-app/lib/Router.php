<?php
/**
 * Simple Router for PHP SaaS
 * Works without mod_rewrite using query parameters
 */

class Router {
    private $routes = [];
    private $notFoundCallback;

    /**
     * Add GET route
     */
    public function get($path, $callback, $middleware = []) {
        $this->addRoute('GET', $path, $callback, $middleware);
        return $this;
    }

    /**
     * Add POST route
     */
    public function post($path, $callback, $middleware = []) {
        $this->addRoute('POST', $path, $callback, $middleware);
        return $this;
    }

    /**
     * Add route
     */
    private function addRoute($method, $path, $callback, $middleware) {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'callback' => $callback,
            'middleware' => $middleware
        ];
    }

    /**
     * Set 404 handler
     */
    public function notFound($callback) {
        $this->notFoundCallback = $callback;
        return $this;
    }

    /**
     * Run the router
     */
    public function run() {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = $this->getUri();

        foreach ($this->routes as $route) {
            $params = $this->matchRoute($route['path'], $uri);

            if ($route['method'] === $method && $params !== false) {
                // Run middleware
                foreach ($route['middleware'] as $middleware) {
                    $result = $middleware();
                    if ($result === false) {
                        return;
                    }
                }

                // Execute callback
                return call_user_func_array($route['callback'], $params);
            }
        }

        // 404 Not Found
        if ($this->notFoundCallback) {
            return call_user_func($this->notFoundCallback);
        }

        http_response_code(404);
        echo '404 Not Found';
    }

    /**
     * Get current URI
     */
    private function getUri() {
        // Support both PATH_INFO and query string routing
        if (isset($_SERVER['PATH_INFO'])) {
            $uri = $_SERVER['PATH_INFO'];
        } elseif (isset($_GET['route'])) {
            $uri = '/' . trim($_GET['route'], '/');
        } else {
            $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            // Remove base path if app is in subdirectory
            $basePath = dirname($_SERVER['SCRIPT_NAME']);
            if ($basePath !== '/' && strpos($uri, $basePath) === 0) {
                $uri = substr($uri, strlen($basePath));
            }
        }

        return '/' . trim($uri, '/');
    }

    /**
     * Match route with parameters
     */
    private function matchRoute($routePath, $uri) {
        // Convert route parameters to regex
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#';

        if (preg_match($pattern, $uri, $matches)) {
            // Extract only named parameters
            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[] = $value;
                }
            }
            return $params;
        }

        return false;
    }
}
