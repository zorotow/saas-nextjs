<?php
/**
 * Activity Log Model
 */

class ActivityLog {
    private $db;

    // Activity Types
    const SIGN_UP = 'SIGN_UP';
    const SIGN_IN = 'SIGN_IN';
    const SIGN_OUT = 'SIGN_OUT';
    const UPDATE_PASSWORD = 'UPDATE_PASSWORD';
    const DELETE_ACCOUNT = 'DELETE_ACCOUNT';
    const UPDATE_ACCOUNT = 'UPDATE_ACCOUNT';
    const CREATE_TEAM = 'CREATE_TEAM';
    const REMOVE_TEAM_MEMBER = 'REMOVE_TEAM_MEMBER';
    const INVITE_TEAM_MEMBER = 'INVITE_TEAM_MEMBER';
    const ACCEPT_INVITATION = 'ACCEPT_INVITATION';

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Log an activity
     */
    public function log($teamId, $userId, $action, $ipAddress = null) {
        if (!$teamId) return null;

        return $this->db->insert('activity_logs', [
            'team_id' => $teamId,
            'user_id' => $userId,
            'action' => $action,
            'ip_address' => $ipAddress ?: Auth::getIpAddress()
        ]);
    }

    /**
     * Get activity logs for user
     */
    public function getForUser($userId, $limit = 10) {
        return $this->db->fetchAll(
            "SELECT al.*, u.name as user_name
             FROM activity_logs al
             LEFT JOIN users u ON al.user_id = u.id
             WHERE al.user_id = ?
             ORDER BY al.timestamp DESC
             LIMIT ?",
            [$userId, $limit]
        );
    }

    /**
     * Get activity logs for team
     */
    public function getForTeam($teamId, $limit = 50) {
        return $this->db->fetchAll(
            "SELECT al.*, u.name as user_name, u.email as user_email
             FROM activity_logs al
             LEFT JOIN users u ON al.user_id = u.id
             WHERE al.team_id = ?
             ORDER BY al.timestamp DESC
             LIMIT ?",
            [$teamId, $limit]
        );
    }

    /**
     * Get readable action name
     */
    public static function getActionLabel($action) {
        $labels = [
            self::SIGN_UP => 'Signed up',
            self::SIGN_IN => 'Signed in',
            self::SIGN_OUT => 'Signed out',
            self::UPDATE_PASSWORD => 'Updated password',
            self::DELETE_ACCOUNT => 'Deleted account',
            self::UPDATE_ACCOUNT => 'Updated account',
            self::CREATE_TEAM => 'Created team',
            self::REMOVE_TEAM_MEMBER => 'Removed team member',
            self::INVITE_TEAM_MEMBER => 'Invited team member',
            self::ACCEPT_INVITATION => 'Accepted invitation',
        ];

        return $labels[$action] ?? $action;
    }
}
