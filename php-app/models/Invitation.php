<?php
/**
 * Invitation Model
 */

class Invitation {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Find invitation by ID
     */
    public function find($id) {
        return $this->db->fetch(
            "SELECT * FROM invitations WHERE id = ?",
            [$id]
        );
    }

    /**
     * Create invitation
     */
    public function create($teamId, $email, $role, $invitedBy) {
        $id = $this->db->insert('invitations', [
            'team_id' => $teamId,
            'email' => $email,
            'role' => $role,
            'invited_by' => $invitedBy,
            'status' => 'pending'
        ]);

        return $this->find($id);
    }

    /**
     * Get pending invitation by email and team
     */
    public function getPending($email, $teamId) {
        return $this->db->fetch(
            "SELECT * FROM invitations
             WHERE email = ? AND team_id = ? AND status = 'pending'",
            [$email, $teamId]
        );
    }

    /**
     * Get pending invitation by ID and email
     */
    public function getPendingById($id, $email) {
        return $this->db->fetch(
            "SELECT * FROM invitations
             WHERE id = ? AND email = ? AND status = 'pending'",
            [$id, $email]
        );
    }

    /**
     * Accept invitation
     */
    public function accept($id) {
        return $this->db->update(
            'invitations',
            ['status' => 'accepted'],
            'id = ?',
            [$id]
        );
    }

    /**
     * Decline invitation
     */
    public function decline($id) {
        return $this->db->update(
            'invitations',
            ['status' => 'declined'],
            'id = ?',
            [$id]
        );
    }

    /**
     * Get invitations for team
     */
    public function getForTeam($teamId) {
        return $this->db->fetchAll(
            "SELECT i.*, u.name as invited_by_name, u.email as invited_by_email
             FROM invitations i
             LEFT JOIN users u ON i.invited_by = u.id
             WHERE i.team_id = ?
             ORDER BY i.invited_at DESC",
            [$teamId]
        );
    }

    /**
     * Check if pending invitation exists
     */
    public function pendingExists($email, $teamId) {
        return $this->getPending($email, $teamId) !== false;
    }

    /**
     * Delete invitation
     */
    public function delete($id) {
        return $this->db->delete('invitations', 'id = ?', [$id]);
    }
}
