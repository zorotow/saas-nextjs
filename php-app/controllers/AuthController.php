<?php
/**
 * Authentication Controller
 */

class AuthController {
    private $userModel;
    private $teamModel;
    private $activityLog;
    private $invitation;

    public function __construct() {
        $this->userModel = new User();
        $this->teamModel = new Team();
        $this->activityLog = new ActivityLog();
        $this->invitation = new Invitation();
    }

    /**
     * Show sign in page
     */
    public function showSignIn() {
        if (Auth::check()) {
            header('Location: /dashboard');
            exit;
        }

        echo View::render('auth.sign-in', [
            'title' => 'Sign In'
        ]);
    }

    /**
     * Handle sign in
     */
    public function signIn() {
        if (!View::verifyCsrf()) {
            View::flash('error', 'Invalid request. Please try again.');
            header('Location: /sign-in');
            exit;
        }

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validate
        $validator = Validator::make($_POST)
            ->rule('email', ['required', 'email', 'max' => 255])
            ->rule('password', ['required', 'min' => 8, 'max' => 100]);

        if (!$validator->validate()) {
            View::setOld($_POST);
            View::flash('error', $validator->firstError());
            header('Location: /sign-in');
            exit;
        }

        // Verify credentials
        $user = $this->userModel->verifyCredentials($email, $password);

        if (!$user) {
            View::setOld(['email' => $email]);
            View::flash('error', 'Invalid email or password. Please try again.');
            header('Location: /sign-in');
            exit;
        }

        // Set session
        Auth::setSession($user['id']);

        // Log activity
        $userWithTeam = $this->userModel->getWithTeam($user['id']);
        $this->activityLog->log(
            $userWithTeam['team_id'] ?? null,
            $user['id'],
            ActivityLog::SIGN_IN
        );

        // Handle redirect for checkout
        $redirect = $_POST['redirect'] ?? '';
        if ($redirect === 'checkout') {
            $priceId = $_POST['priceId'] ?? '';
            header('Location: /checkout?priceId=' . urlencode($priceId));
            exit;
        }

        header('Location: /dashboard');
        exit;
    }

    /**
     * Show sign up page
     */
    public function showSignUp() {
        if (Auth::check()) {
            header('Location: /dashboard');
            exit;
        }

        echo View::render('auth.sign-up', [
            'title' => 'Sign Up',
            'inviteId' => $_GET['inviteId'] ?? null
        ]);
    }

    /**
     * Handle sign up
     */
    public function signUp() {
        if (!View::verifyCsrf()) {
            View::flash('error', 'Invalid request. Please try again.');
            header('Location: /sign-up');
            exit;
        }

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $inviteId = $_POST['inviteId'] ?? null;

        // Validate
        $validator = Validator::make($_POST)
            ->rule('email', ['required', 'email', 'max' => 255])
            ->rule('password', ['required', 'min' => 8, 'max' => 100]);

        if (!$validator->validate()) {
            View::setOld($_POST);
            View::flash('error', $validator->firstError());
            header('Location: /sign-up');
            exit;
        }

        // Check if email exists
        if ($this->userModel->emailExists($email)) {
            View::setOld(['email' => $email]);
            View::flash('error', 'Failed to create user. Please try again.');
            header('Location: /sign-up');
            exit;
        }

        $db = Database::getInstance();

        try {
            $db->beginTransaction();

            // Create user
            $user = $this->userModel->create([
                'email' => $email,
                'password' => $password,
                'role' => 'owner'
            ]);

            $teamId = null;
            $userRole = 'owner';

            // Handle invitation
            if ($inviteId) {
                $invitation = $this->invitation->getPendingById($inviteId, $email);

                if ($invitation) {
                    $teamId = $invitation['team_id'];
                    $userRole = $invitation['role'];

                    $this->invitation->accept($invitation['id']);
                    $this->activityLog->log($teamId, $user['id'], ActivityLog::ACCEPT_INVITATION);
                } else {
                    $db->rollback();
                    View::setOld(['email' => $email]);
                    View::flash('error', 'Invalid or expired invitation.');
                    header('Location: /sign-up');
                    exit;
                }
            } else {
                // Create new team
                $team = $this->teamModel->create([
                    'name' => $email . "'s Team"
                ]);
                $teamId = $team['id'];

                $this->activityLog->log($teamId, $user['id'], ActivityLog::CREATE_TEAM);
            }

            // Add user to team
            $teamMember = new TeamMember();
            $teamMember->add($teamId, $user['id'], $userRole);

            // Log sign up
            $this->activityLog->log($teamId, $user['id'], ActivityLog::SIGN_UP);

            // Set session
            Auth::setSession($user['id']);

            $db->commit();

            // Handle redirect for checkout
            $redirect = $_POST['redirect'] ?? '';
            if ($redirect === 'checkout') {
                $priceId = $_POST['priceId'] ?? '';
                header('Location: /checkout?priceId=' . urlencode($priceId));
                exit;
            }

            header('Location: /dashboard');
            exit;

        } catch (Exception $e) {
            $db->rollback();
            View::setOld(['email' => $email]);
            View::flash('error', 'Failed to create account. Please try again.');
            header('Location: /sign-up');
            exit;
        }
    }

    /**
     * Handle sign out
     */
    public function signOut() {
        $user = Auth::getUser();

        if ($user) {
            $userWithTeam = $this->userModel->getWithTeam($user['id']);
            $this->activityLog->log(
                $userWithTeam['team_id'] ?? null,
                $user['id'],
                ActivityLog::SIGN_OUT
            );
        }

        Auth::clearSession();
        header('Location: /sign-in');
        exit;
    }
}
