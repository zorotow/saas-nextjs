<?php
/**
 * API Controller
 * Handles API endpoints for AJAX requests
 */

class ApiController {
    private $userModel;
    private $teamModel;

    public function __construct() {
        $this->userModel = new User();
        $this->teamModel = new Team();
    }

    /**
     * Get current user data
     */
    public function getUser() {
        header('Content-Type: application/json');

        $user = Auth::getUser();

        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        // Remove sensitive data
        unset($user['password_hash']);

        echo json_encode(['user' => $user]);
        exit;
    }

    /**
     * Get team data
     */
    public function getTeam() {
        header('Content-Type: application/json');

        $user = Auth::getUser();

        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $team = $this->teamModel->getForUser($user['id']);

        echo json_encode(['team' => $team]);
        exit;
    }

    /**
     * Stripe checkout session
     */
    public function stripeCheckout() {
        header('Content-Type: application/json');

        $user = Auth::getUser();

        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $priceId = $_POST['priceId'] ?? $_GET['priceId'] ?? '';

        if (!$priceId) {
            http_response_code(400);
            echo json_encode(['error' => 'Price ID is required']);
            exit;
        }

        $team = $this->teamModel->getForUser($user['id']);

        if (!$team) {
            http_response_code(400);
            echo json_encode(['error' => 'User must be part of a team']);
            exit;
        }

        // Include Stripe helper
        require_once APP_ROOT . '/lib/Stripe.php';

        try {
            $stripe = new StripeHelper();
            $session = $stripe->createCheckoutSession($team, $priceId);

            echo json_encode(['url' => $session['url']]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Stripe webhook handler
     */
    public function stripeWebhook() {
        require_once APP_ROOT . '/lib/Stripe.php';

        $payload = file_get_contents('php://input');
        $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

        try {
            $stripe = new StripeHelper();
            $stripe->handleWebhook($payload, $sigHeader);

            http_response_code(200);
            echo json_encode(['received' => true]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }
}
