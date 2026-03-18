<?php
/**
 * Request Notification System
 * Handles notifications for employee requests submissions and updates
 */

class RequestNotifications {
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
            $this->db->execute("CREATE TABLE IF NOT EXISTS request_notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                request_id INT DEFAULT NULL,
                notification_type ENUM('new_request', 'status_update', 'approval_needed') NOT NULL,
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
            error_log("Request notifications initialization error: " . $e->getMessage());
        }
    }

    /**
     * Get pending request count for SuperAdmin only
     */
    public function getPendingRequestCount($user) {
        try {
            $account_type = $user['account_type'] ?? 'user';

            // Only SuperAdmin gets request notifications
            if ($account_type !== 'superadmin') {
                return 0;
            }

            // Count all pending employee requests
            $result = $this->db->fetch("
                SELECT COUNT(*) as count
                FROM employee_requests er
                WHERE er.status IN ('Pending Supervisor', 'Processing', 'Documents Pending', 'Pending Manager')
            ");

            return $result ? (int)$result['count'] : 0;

        } catch (Exception $e) {
            // Silent fail - don't break existing functionality
            error_log("Error getting pending request count: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get total request notification count for sidebar
     */
    public function getRequestNotificationCount($user) {
        try {
            $account_type = $user['account_type'] ?? 'user';

            // Only SuperAdmin gets notifications
            if ($account_type !== 'superadmin') {
                return 0;
            }

            return $this->getPendingRequestCount($user);

        } catch (Exception $e) {
            error_log("Error getting request notification count: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Create notification when new request is submitted
     */
    public function notifyNewRequest($request_id) {
        try {
            // Get request details
            $request = $this->db->fetch("
                SELECT er.*
                FROM employee_requests er
                WHERE er.id = ?
            ", [$request_id]);

            if (!$request) {
                return false;
            }

            $title = "New Employee Request";
            $message = "{$request['name']} has submitted a request: {$request['request_type']} - Status: {$request['status']}";

            // Notify only SuperAdmins for all requests
            $this->db->execute("
                INSERT INTO request_notifications
                (request_id, notification_type, target_role, title, message, expires_at)
                VALUES (?, 'new_request', 'superadmin', ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))
            ", [$request_id, $title, $message]);

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
                UPDATE request_notifications
                SET is_read = 1
                WHERE id IN ($placeholders)
            ", $notification_ids);

            return true;

        } catch (Exception $e) {
            error_log("Error marking request notifications as read: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get notifications for a user
     */
    public function getNotificationsForUser($user, $limit = 10) {
        try {
            $account_type = $user['account_type'] ?? 'user';

            // Only SuperAdmin gets notifications
            if ($account_type !== 'superadmin') {
                return [];
            }

            $notifications = $this->db->fetchAll("
                SELECT *
                FROM request_notifications
                WHERE target_role = 'superadmin'
                AND (expires_at IS NULL OR expires_at > NOW())
                ORDER BY created_at DESC, is_read ASC
                LIMIT ?
            ", [$limit]);

            return $notifications ?: [];

        } catch (Exception $e) {
            error_log("Error getting request notifications for user: " . $e->getMessage());
            return [];
        }
    }
}
?>