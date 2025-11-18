<?php
/**
 * Team Controller
 */

class TeamController {
    private $userModel;
    private $teamModel;
    private $teamMember;
    private $activityLog;
    private $invitation;

    public function __construct() {
        $this->userModel = new User();
        $this->teamModel = new Team();
        $this->teamMember = new TeamMember();
        $this->activityLog = new ActivityLog();
        $this->invitation = new Invitation();
    }

    /**
     * Team settings page
     */
    public function index() {
        $user = Auth::getUser();
        $team = $this->teamModel->getForUser($user['id']);

        if (!$team) {
            View::flash('error', 'Team not found.');
            header('Location: /dashboard');
            exit;
        }

        $invitations = $this->invitation->getForTeam($team['id']);

        echo View::render('team.index', [
            'title' => 'Team Settings',
            'user' => $user,
            'team' => $team,
            'invitations' => $invitations
        ]);
    }

    /**
     * Invite team member
     */
    public function invite() {
        if (!View::verifyCsrf()) {
            View::flash('error', 'Invalid request. Please try again.');
            header('Location: /dashboard/team');
            exit;
        }

        $user = Auth::getUser();
        $userWithTeam = $this->userModel->getWithTeam($user['id']);

        if (!$userWithTeam['team_id']) {
            View::flash('error', 'User is not part of a team.');
            header('Location: /dashboard');
            exit;
        }

        $validator = Validator::make($_POST)
            ->rule('email', ['required', 'email', 'max' => 255])
            ->rule('role', ['required', 'in' => ['member', 'owner']]);

        if (!$validator->validate()) {
            View::flash('error', $validator->firstError());
            header('Location: /dashboard/team');
            exit;
        }

        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'member';

        // Check if user is already a member
        $db = Database::getInstance();
        $existingMember = $db->fetch(
            "SELECT u.id FROM users u
             JOIN team_members tm ON u.id = tm.user_id
             WHERE u.email = ? AND tm.team_id = ?",
            [$email, $userWithTeam['team_id']]
        );

        if ($existingMember) {
            View::flash('error', 'User is already a member of this team.');
            header('Location: /dashboard/team');
            exit;
        }

        // Check if invitation already exists
        if ($this->invitation->pendingExists($email, $userWithTeam['team_id'])) {
            View::flash('error', 'An invitation has already been sent to this email.');
            header('Location: /dashboard/team');
            exit;
        }

        // Create invitation
        $this->invitation->create(
            $userWithTeam['team_id'],
            $email,
            $role,
            $user['id']
        );

        // Log activity
        $this->activityLog->log(
            $userWithTeam['team_id'],
            $user['id'],
            ActivityLog::INVITE_TEAM_MEMBER
        );

        // TODO: Send invitation email
        // $inviteUrl = APP_URL . '/sign-up?inviteId=' . $invitation['id'];

        View::flash('success', 'Invitation sent successfully.');
        header('Location: /dashboard/team');
        exit;
    }

    /**
     * Remove team member
     */
    public function removeMember() {
        if (!View::verifyCsrf()) {
            View::flash('error', 'Invalid request. Please try again.');
            header('Location: /dashboard/team');
            exit;
        }

        $user = Auth::getUser();
        $userWithTeam = $this->userModel->getWithTeam($user['id']);

        if (!$userWithTeam['team_id']) {
            View::flash('error', 'User is not part of a team.');
            header('Location: /dashboard');
            exit;
        }

        $memberId = intval($_POST['memberId'] ?? 0);

        if (!$memberId) {
            View::flash('error', 'Invalid member ID.');
            header('Location: /dashboard/team');
            exit;
        }

        // Remove member
        $this->teamMember->remove($memberId, $userWithTeam['team_id']);

        // Log activity
        $this->activityLog->log(
            $userWithTeam['team_id'],
            $user['id'],
            ActivityLog::REMOVE_TEAM_MEMBER
        );

        View::flash('success', 'Team member removed successfully.');
        header('Location: /dashboard/team');
        exit;
    }

    /**
     * Get team data (API endpoint)
     */
    public function getData() {
        header('Content-Type: application/json');

        $user = Auth::getUser();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $team = $this->teamModel->getForUser($user['id']);

        echo json_encode([
            'team' => $team
        ]);
        exit;
    }
}
