<?php
/**
 * User Model
 */

class User {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Find user by ID
     */
    public function find($id) {
        return $this->db->fetch(
            "SELECT * FROM users WHERE id = ? AND deleted_at IS NULL",
            [$id]
        );
    }

    /**
     * Find user by email
     */
    public function findByEmail($email) {
        return $this->db->fetch(
            "SELECT * FROM users WHERE email = ? AND deleted_at IS NULL",
            [$email]
        );
    }

    /**
     * Create new user
     */
    public function create($data) {
        $id = $this->db->insert('users', [
            'name' => $data['name'] ?? null,
            'email' => $data['email'],
            'password_hash' => Auth::hashPassword($data['password']),
            'role' => $data['role'] ?? 'member'
        ]);

        return $this->find($id);
    }

    /**
     * Update user
     */
    public function update($id, $data) {
        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['email'])) {
            $updateData['email'] = $data['email'];
        }
        if (isset($data['password'])) {
            $updateData['password_hash'] = Auth::hashPassword($data['password']);
        }

        if (!empty($updateData)) {
            $this->db->update('users', $updateData, 'id = ?', [$id]);
        }

        return $this->find($id);
    }

    /**
     * Soft delete user
     */
    public function delete($id) {
        $user = $this->find($id);
        if (!$user) return false;

        $this->db->update('users', [
            'deleted_at' => date('Y-m-d H:i:s'),
            'email' => $user['email'] . '-' . $id . '-deleted'
        ], 'id = ?', [$id]);

        return true;
    }

    /**
     * Get user with team
     */
    public function getWithTeam($userId) {
        return $this->db->fetch(
            "SELECT u.*, tm.team_id, tm.role as team_role, t.name as team_name
             FROM users u
             LEFT JOIN team_members tm ON u.id = tm.user_id
             LEFT JOIN teams t ON tm.team_id = t.id
             WHERE u.id = ? AND u.deleted_at IS NULL
             LIMIT 1",
            [$userId]
        );
    }

    /**
     * Verify user credentials
     */
    public function verifyCredentials($email, $password) {
        $user = $this->findByEmail($email);

        if (!$user) {
            return false;
        }

        if (!Auth::verifyPassword($password, $user['password_hash'])) {
            return false;
        }

        return $user;
    }

    /**
     * Check if email exists
     */
    public function emailExists($email, $excludeId = null) {
        $sql = "SELECT id FROM users WHERE email = ? AND deleted_at IS NULL";
        $params = [$email];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        return $this->db->fetch($sql, $params) !== false;
    }
}
