<?php
/**
 * Transport Notification System
 * Handles notifications for transport requests without interfering with existing functionality
 */

class TransportNotifications {
    private $db;
    private static $instance = null;

    public function __construct($database) {
        $this->db = $database;
        $this->initializeNotifications();
    }

    public static function getInstance($database) {
        if (self::$instance === null) {
            self::$instance = new self($database);
        }
        return self::$instance;
    }

    /**
     * Initialize notification tables (safe - won't affect existing data)
     */
    private function initializeNotifications() {
        try {
            // Create notifications table only if it doesn't exist
            $this->db->execute("CREATE TABLE IF NOT EXISTS transport_notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                request_id INT NOT NULL,
                notification_type ENUM('new_request', 'request_approved', 'request_allocated', 'request_cancelled') NOT NULL,
                target_role ENUM('admin', 'superadmin', 'manager', 'supervisor', 'user') NOT NULL,
                target_location VARCHAR(50) DEFAULT NULL,
                target_user_epf VARCHAR(20) DEFAULT NULL,
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                is_read BOOLEAN DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP DEFAULT NULL,
                INDEX idx_target_role (target_role),
                INDEX idx_target_location (target_location),
                INDEX idx_target_user (target_user_epf),
                INDEX idx_is_read (is_read),
                INDEX idx_created_at (created_at)
            )");
        } catch (Exception $e) {
            // Silent fail - don't break existing functionality
            error_log("Transport notifications initialization error: " . $e->getMessage());
        }
    }

    /**
     * Get pending allocation count for admin/superadmin roles
     */
    public function getPendingAllocationCount($user) {
        // EMERGENCY DEBUG - log everything
        error_log("=== TRANSPORT NOTIFICATION DEBUG START ===");
        error_log("User array: " . json_encode($user));

        try {
            $account_type = $user['account_type'] ?? 'user';
            $user_location = $user['location'] ?? $user['location_name'] ?? null;

            error_log("Account type: $account_type");
            error_log("User location from session: '$user_location'");

            // Only count for admin and superadmin roles
            if (!in_array($account_type, ['admin', 'superadmin'])) {
                return 0;
            }

            // Get user location from database if not in session
            if (!$user_location && isset($user['epf_number'])) {
                $location_data = $this->db->fetch("
                    SELECT l.location_name
                    FROM employees e
                    LEFT JOIN locations l ON e.location_id = l.id
                    WHERE e.epf_number = ?
                ", [$user['epf_number']]);

                if ($location_data) {
                    $user_location = $location_data['location_name'];
                }
            }

            // Debug logging
            error_log("TransportNotifications Debug - EPF: " . ($user['epf_number'] ?? 'N/A') . ", Account: $account_type, Location: '$user_location'");

            // Count pending requests (status = 'pending' means waiting for allocation)
            if ($account_type === 'superadmin') {
                // SuperAdmin sees all pending requests
                $result = $this->db->fetch("
                    SELECT COUNT(*) as count
                    FROM transport_requests tr
                    WHERE tr.status = 'pending'
                ");
            } else {
                // Admin sees only requests from their location
                $sql = "
                    SELECT COUNT(*) as count
                    FROM transport_requests tr
                    LEFT JOIN employees e ON tr.emp_number = e.epf_number
                    LEFT JOIN locations l ON e.location_id = l.id
                    WHERE tr.status = 'pending'
                    AND l.location_name = ?
                ";
                $result = $this->db->fetch($sql, [$user_location]);

                // Debug: Log the query and result
                error_log("TransportNotifications SQL: $sql with location='$user_location'");
                error_log("TransportNotifications Result: " . json_encode($result));
            }

            $final_count = $result ? (int)$result['count'] : 0;
            error_log("Final count: $final_count");
            error_log("=== TRANSPORT NOTIFICATION DEBUG END ===");
            return $final_count;

        } catch (Exception $e) {
            // Silent fail - don't break existing functionality
            error_log("Error getting pending allocation count: " . $e->getMessage());
            error_log("=== TRANSPORT NOTIFICATION DEBUG END (ERROR) ===");
            return 0;
        }
    }

    /**
     * Get total transport notification count for sidebar
     */
    public function getTransportNotificationCount($user) {
        try {
            $account_type = $user['account_type'] ?? 'user';

            // Only admin and superadmin get notifications for now
            if (!in_array($account_type, ['admin', 'superadmin'])) {
                return 0;
            }

            return $this->getPendingAllocationCount($user);

        } catch (Exception $e) {
            error_log("Error getting transport notification count: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Create notification when new transport request is submitted
     * This would be called from transport request submission (optional integration)
     */
    public function notifyNewRequest($request_id) {
        try {
            // Get request details
            $request = $this->db->fetch("
                SELECT tr.*, e.name as requester_name, l.location_name as requester_location
                FROM transport_requests tr
                LEFT JOIN employees e ON tr.emp_number = e.epf_number
                LEFT JOIN employees emp ON tr.emp_number = emp.epf_number
                LEFT JOIN locations l ON emp.location_id = l.id
                WHERE tr.id = ?
            ", [$request_id]);

            if (!$request) {
                return false;
            }

            $title = "New Transport Request";
            $message = "{$request['emp_name']} has submitted a transport request for {$request['departure_date']}";

            // Notify SuperAdmins
            $this->db->execute("
                INSERT INTO transport_notifications
                (request_id, notification_type, target_role, title, message, expires_at)
                VALUES (?, 'new_request', 'superadmin', ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))
            ", [$request_id, $title, $message]);

            // Notify Admins in the same location
            if ($request['requester_location']) {
                $this->db->execute("
                    INSERT INTO transport_notifications
                    (request_id, notification_type, target_role, target_location, title, message, expires_at)
                    VALUES (?, 'new_request', 'admin', ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))
                ", [$request_id, $request['requester_location'], $title, $message]);
            }

            return true;

        } catch (Exception $e) {
            error_log("Error creating notification for request $request_id: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark notifications as read
     */
    public function markAsRead($notification_ids) {
        try {
            if (empty($notification_ids)) {
                return false;
            }

            $placeholders = str_repeat('?,', count($notification_ids) - 1) . '?';
            $this->db->execute("
                UPDATE transport_notifications
                SET is_read = 1
                WHERE id IN ($placeholders)
            ", $notification_ids);

            return true;

        } catch (Exception $e) {
            error_log("Error marking notifications as read: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get notifications for a user
     */
    public function getNotificationsForUser($user, $limit = 10) {
        try {
            $account_type = $user['account_type'] ?? 'user';
            $user_location = $user['location'] ?? $user['location_name'] ?? null;
            $user_epf = $user['epf_number'] ?? '';

            // Build query based on user role
            $conditions = ["(expires_at IS NULL OR expires_at > NOW())"];
            $params = [];

            if ($account_type === 'superadmin') {
                $conditions[] = "target_role = 'superadmin'";
            } elseif ($account_type === 'admin') {
                $conditions[] = "(target_role = 'admin' AND (target_location = ? OR target_location IS NULL))";
                $params[] = $user_location;
            } else {
                $conditions[] = "target_user_epf = ?";
                $params[] = $user_epf;
            }

            $where_clause = implode(' AND ', $conditions);
            $params[] = $limit;

            $notifications = $this->db->fetchAll("
                SELECT *
                FROM transport_notifications
                WHERE $where_clause
                ORDER BY created_at DESC, is_read ASC
                LIMIT ?
            ", $params);

            return $notifications ?: [];

        } catch (Exception $e) {
            error_log("Error getting notifications for user: " . $e->getMessage());
            return [];
        }
    }
}
?>