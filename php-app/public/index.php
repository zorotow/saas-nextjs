<?php
/**
 * SaaS PHP Application - Main Entry Point
 *
 * This is the single entry point for the entire application.
 * All requests are routed through this file.
 */

// Define application root
define('APP_ROOT', dirname(__DIR__));

// Start session
session_name('saas_session');
session_start();

// Load configuration
require_once APP_ROOT . '/config/config.php';

// Load libraries
require_once APP_ROOT . '/lib/Database.php';
require_once APP_ROOT . '/lib/JWT.php';
require_once APP_ROOT . '/lib/Auth.php';
require_once APP_ROOT . '/lib/Router.php';
require_once APP_ROOT . '/lib/Validator.php';
require_once APP_ROOT . '/lib/View.php';

// Load models
require_once APP_ROOT . '/models/User.php';
require_once APP_ROOT . '/models/Team.php';
require_once APP_ROOT . '/models/ActivityLog.php';
require_once APP_ROOT . '/models/Invitation.php';

// Load controllers
require_once APP_ROOT . '/controllers/AuthController.php';
require_once APP_ROOT . '/controllers/DashboardController.php';
require_once APP_ROOT . '/controllers/TeamController.php';
require_once APP_ROOT . '/controllers/ApiController.php';

// Share common data with views
View::share('appName', APP_NAME);
View::share('currentUser', Auth::getUser());

// Authentication middleware
$authMiddleware = function() {
    if (!Auth::check()) {
        header('Location: /sign-in');
        exit;
    }
    return true;
};

// Guest middleware (redirect if logged in)
$guestMiddleware = function() {
    if (Auth::check()) {
        header('Location: /dashboard');
        exit;
    }
    return true;
};

// Create router
$router = new Router();

// Public routes
$router->get('/', function() {
    if (Auth::check()) {
        header('Location: /dashboard');
    } else {
        header('Location: /sign-in');
    }
    exit;
});

// Auth routes
$router->get('/sign-in', function() {
    (new AuthController())->showSignIn();
}, [$guestMiddleware]);

$router->post('/sign-in', function() {
    (new AuthController())->signIn();
});

$router->get('/sign-up', function() {
    (new AuthController())->showSignUp();
}, [$guestMiddleware]);

$router->post('/sign-up', function() {
    (new AuthController())->signUp();
});

$router->get('/sign-out', function() {
    (new AuthController())->signOut();
});

$router->post('/sign-out', function() {
    (new AuthController())->signOut();
});

// Dashboard routes
$router->get('/dashboard', function() {
    (new DashboardController())->index();
}, [$authMiddleware]);

$router->get('/dashboard/general', function() {
    (new DashboardController())->general();
}, [$authMiddleware]);

$router->post('/dashboard/general', function() {
    (new DashboardController())->updateAccount();
}, [$authMiddleware]);

$router->get('/dashboard/security', function() {
    (new DashboardController())->security();
}, [$authMiddleware]);

$router->post('/dashboard/security', function() {
    (new DashboardController())->updatePassword();
}, [$authMiddleware]);

$router->post('/dashboard/delete-account', function() {
    (new DashboardController())->deleteAccount();
}, [$authMiddleware]);

$router->get('/dashboard/activity', function() {
    (new DashboardController())->activity();
}, [$authMiddleware]);

// Team routes
$router->get('/dashboard/team', function() {
    (new TeamController())->index();
}, [$authMiddleware]);

$router->post('/dashboard/team/invite', function() {
    (new TeamController())->invite();
}, [$authMiddleware]);

$router->post('/dashboard/team/remove-member', function() {
    (new TeamController())->removeMember();
}, [$authMiddleware]);

// API routes
$router->get('/api/user', function() {
    (new ApiController())->getUser();
});

$router->get('/api/team', function() {
    (new ApiController())->getTeam();
});

$router->post('/api/stripe/checkout', function() {
    (new ApiController())->stripeCheckout();
});

$router->post('/api/stripe/webhook', function() {
    (new ApiController())->stripeWebhook();
});

// Pricing page
$router->get('/pricing', function() {
    echo View::render('dashboard.pricing', [
        'title' => 'Pricing'
    ]);
});

// 404 handler
$router->notFound(function() {
    http_response_code(404);
    echo View::render('errors.404', [
        'title' => 'Page Not Found'
    ]);
});

// Run the router
$router->run();
