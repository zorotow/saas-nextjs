<?php
/**
 * Dashboard Controller
 */

class DashboardController {
    private $userModel;
    private $teamModel;
    private $activityLog;

    public function __construct() {
        $this->userModel = new User();
        $this->teamModel = new Team();
        $this->activityLog = new ActivityLog();
    }

    /**
     * Dashboard home
     */
    public function index() {
        $user = Auth::getUser();
        $team = $this->teamModel->getForUser($user['id']);

        echo View::render('dashboard.index', [
            'title' => 'Dashboard',
            'user' => $user,
            'team' => $team
        ]);
    }

    /**
     * General settings page
     */
    public function general() {
        $user = Auth::getUser();

        echo View::render('dashboard.general', [
            'title' => 'General Settings',
            'user' => $user
        ]);
    }

    /**
     * Update account
     */
    public function updateAccount() {
        if (!View::verifyCsrf()) {
            View::flash('error', 'Invalid request. Please try again.');
            header('Location: /dashboard/general');
            exit;
        }

        $user = Auth::getUser();

        $validator = Validator::make($_POST)
            ->rule('name', ['required', 'min' => 1, 'max' => 100])
            ->rule('email', ['required', 'email', 'max' => 255]);

        if (!$validator->validate()) {
            View::flash('error', $validator->firstError());
            header('Location: /dashboard/general');
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        // Check if email is taken by another user
        if ($email !== $user['email'] && $this->userModel->emailExists($email, $user['id'])) {
            View::flash('error', 'Email is already in use.');
            header('Location: /dashboard/general');
            exit;
        }

        $this->userModel->update($user['id'], [
            'name' => $name,
            'email' => $email
        ]);

        // Log activity
        $userWithTeam = $this->userModel->getWithTeam($user['id']);
        $this->activityLog->log(
            $userWithTeam['team_id'] ?? null,
            $user['id'],
            ActivityLog::UPDATE_ACCOUNT
        );

        View::flash('success', 'Account updated successfully.');
        header('Location: /dashboard/general');
        exit;
    }

    /**
     * Security settings page
     */
    public function security() {
        $user = Auth::getUser();

        echo View::render('dashboard.security', [
            'title' => 'Security Settings',
            'user' => $user
        ]);
    }

    /**
     * Update password
     */
    public function updatePassword() {
        if (!View::verifyCsrf()) {
            View::flash('error', 'Invalid request. Please try again.');
            header('Location: /dashboard/security');
            exit;
        }

        $user = Auth::getUser();

        $validator = Validator::make($_POST)
            ->rule('currentPassword', ['required', 'min' => 8, 'max' => 100])
            ->rule('newPassword', ['required', 'min' => 8, 'max' => 100])
            ->rule('confirmPassword', ['required', 'match' => 'newPassword']);

        if (!$validator->validate()) {
            View::flash('error', $validator->firstError());
            header('Location: /dashboard/security');
            exit;
        }

        $currentPassword = $_POST['currentPassword'] ?? '';
        $newPassword = $_POST['newPassword'] ?? '';

        // Verify current password
        if (!Auth::verifyPassword($currentPassword, $user['password_hash'])) {
            View::flash('error', 'Current password is incorrect.');
            header('Location: /dashboard/security');
            exit;
        }

        // Check if new password is different
        if ($currentPassword === $newPassword) {
            View::flash('error', 'New password must be different from the current password.');
            header('Location: /dashboard/security');
            exit;
        }

        // Update password
        $this->userModel->update($user['id'], [
            'password' => $newPassword
        ]);

        // Log activity
        $userWithTeam = $this->userModel->getWithTeam($user['id']);
        $this->activityLog->log(
            $userWithTeam['team_id'] ?? null,
            $user['id'],
            ActivityLog::UPDATE_PASSWORD
        );

        View::flash('success', 'Password updated successfully.');
        header('Location: /dashboard/security');
        exit;
    }

    /**
     * Delete account
     */
    public function deleteAccount() {
        if (!View::verifyCsrf()) {
            View::flash('error', 'Invalid request. Please try again.');
            header('Location: /dashboard/security');
            exit;
        }

        $user = Auth::getUser();
        $password = $_POST['password'] ?? '';

        // Verify password
        if (!Auth::verifyPassword($password, $user['password_hash'])) {
            View::flash('error', 'Incorrect password. Account deletion failed.');
            header('Location: /dashboard/security');
            exit;
        }

        // Log activity before deletion
        $userWithTeam = $this->userModel->getWithTeam($user['id']);
        $this->activityLog->log(
            $userWithTeam['team_id'] ?? null,
            $user['id'],
            ActivityLog::DELETE_ACCOUNT
        );

        // Delete user
        $this->userModel->delete($user['id']);

        // Remove from team
        if ($userWithTeam['team_id']) {
            $teamMember = new TeamMember();
            $db = Database::getInstance();
            $db->delete('team_members', 'user_id = ? AND team_id = ?', [
                $user['id'],
                $userWithTeam['team_id']
            ]);
        }

        // Clear session
        Auth::clearSession();

        header('Location: /sign-in');
        exit;
    }

    /**
     * Activity log page
     */
    public function activity() {
        $user = Auth::getUser();
        $logs = $this->activityLog->getForUser($user['id']);

        echo View::render('dashboard.activity', [
            'title' => 'Activity Log',
            'user' => $user,
            'logs' => $logs
        ]);
    }
}
