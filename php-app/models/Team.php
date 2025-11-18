<?php
/**
 * Team Model
 */

class Team {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Find team by ID
     */
    public function find($id) {
        return $this->db->fetch(
            "SELECT * FROM teams WHERE id = ?",
            [$id]
        );
    }

    /**
     * Create new team
     */
    public function create($data) {
        $id = $this->db->insert('teams', [
            'name' => $data['name']
        ]);

        return $this->find($id);
    }

    /**
     * Update team
     */
    public function update($id, $data) {
        $this->db->update('teams', $data, 'id = ?', [$id]);
        return $this->find($id);
    }

    /**
     * Get team with members
     */
    public function getWithMembers($teamId) {
        $team = $this->find($teamId);
        if (!$team) return null;

        $members = $this->db->fetchAll(
            "SELECT tm.*, u.id as user_id, u.name, u.email
             FROM team_members tm
             JOIN users u ON tm.user_id = u.id
             WHERE tm.team_id = ? AND u.deleted_at IS NULL
             ORDER BY tm.joined_at ASC",
            [$teamId]
        );

        $team['members'] = $members;
        return $team;
    }

    /**
     * Get team for user
     */
    public function getForUser($userId) {
        $membership = $this->db->fetch(
            "SELECT team_id FROM team_members WHERE user_id = ? LIMIT 1",
            [$userId]
        );

        if (!$membership) return null;

        return $this->getWithMembers($membership['team_id']);
    }

    /**
     * Find by Stripe customer ID
     */
    public function findByStripeCustomerId($customerId) {
        return $this->db->fetch(
            "SELECT * FROM teams WHERE stripe_customer_id = ?",
            [$customerId]
        );
    }

    /**
     * Update subscription
     */
    public function updateSubscription($teamId, $data) {
        return $this->update($teamId, [
            'stripe_subscription_id' => $data['stripe_subscription_id'] ?? null,
            'stripe_product_id' => $data['stripe_product_id'] ?? null,
            'plan_name' => $data['plan_name'] ?? null,
            'subscription_status' => $data['subscription_status']
        ]);
    }
}

/**
 * Team Member Model
 */
class TeamMember {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Add member to team
     */
    public function add($teamId, $userId, $role = 'member') {
        return $this->db->insert('team_members', [
            'team_id' => $teamId,
            'user_id' => $userId,
            'role' => $role
        ]);
    }

    /**
     * Remove member from team
     */
    public function remove($memberId, $teamId) {
        return $this->db->delete(
            'team_members',
            'id = ? AND team_id = ?',
            [$memberId, $teamId]
        );
    }

    /**
     * Find membership
     */
    public function find($id) {
        return $this->db->fetch(
            "SELECT * FROM team_members WHERE id = ?",
            [$id]
        );
    }

    /**
     * Get user's membership in team
     */
    public function getMembership($userId, $teamId) {
        return $this->db->fetch(
            "SELECT * FROM team_members WHERE user_id = ? AND team_id = ?",
            [$userId, $teamId]
        );
    }

    /**
     * Update member role
     */
    public function updateRole($memberId, $role) {
        return $this->db->update(
            'team_members',
            ['role' => $role],
            'id = ?',
            [$memberId]
        );
    }

    /**
     * Check if user is member of team
     */
    public function isMember($userId, $teamId) {
        return $this->getMembership($userId, $teamId) !== false;
    }
}
