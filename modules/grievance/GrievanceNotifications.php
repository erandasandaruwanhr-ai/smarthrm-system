<?php
/**
 * Grievance Notification System
 * Handles notifications for grievance submissions and updates
 */

class GrievanceNotifications {
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
            $this->db->execute("CREATE TABLE IF NOT EXISTS grievance_notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                grievance_id INT DEFAULT NULL,
                anonymous_grievance_id INT DEFAULT NULL,
                notification_type ENUM('new_grievance', 'new_anonymous_grievance', 'status_update', 'assignment_update') NOT NULL,
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
            error_log("Grievance notifications initialization error: " . $e->getMessage());
        }
    }

    /**
     * Get pending grievance count for admin/supervisory roles
     */
    public function getPendingGrievanceCount($user) {
        try {
            $account_type = $user['account_type'] ?? 'user';
            $user_location = $user['location'] ?? $user['location_name'] ?? null;
            $user_epf = $user['epf_number'] ?? '';

            // Only count for supervisor, manager, admin and superadmin roles
            if (!in_array($account_type, ['supervisor', 'manager', 'admin', 'superadmin'])) {
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

            $total_count = 0;

            // Count normal grievances based on role
            if ($account_type === 'superadmin') {
                // SuperAdmin sees all normal grievances + all anonymous grievances
                $normal_result = $this->db->fetch("
                    SELECT COUNT(*) as count
                    FROM grievances g
                    WHERE g.status IN ('Open', 'Under Supervisory Review', 'Under Managerial Review', 'In Progress', 'Reopened')
                ");
                $normal_count = $normal_result ? (int)$normal_result['count'] : 0;

                $anonymous_result = $this->db->fetch("
                    SELECT COUNT(*) as count
                    FROM anonymous_grievances ag
                    WHERE ag.status IN ('Opened', 'Reviewing', 'Action', 'Under Investigation', 'Open')
                ");
                $anonymous_count = $anonymous_result ? (int)$anonymous_result['count'] : 0;

                $total_count = $normal_count + $anonymous_count;

            } elseif ($account_type === 'admin') {
                // Admin sees normal grievances from their location only
                if ($user_location) {
                    $result = $this->db->fetch("
                        SELECT COUNT(*) as count
                        FROM grievances g
                        WHERE g.status IN ('Open', 'Under Supervisory Review', 'Under Managerial Review', 'In Progress', 'Reopened')
                        AND g.employee_location = ?
                    ", [$user_location]);
                    $total_count = $result ? (int)$result['count'] : 0;
                }

            } elseif (in_array($account_type, ['manager', 'supervisor'])) {
                // Manager/Supervisor sees grievances from their hierarchy
                if ($user_location) {
                    $result = $this->db->fetch("
                        SELECT COUNT(*) as count
                        FROM grievances g
                        WHERE g.status IN ('Open', 'Under Supervisory Review', 'Under Managerial Review', 'In Progress', 'Reopened')
                        AND g.employee_location = ?
                    ", [$user_location]);
                    $total_count = $result ? (int)$result['count'] : 0;
                }
            }

            return $total_count;

        } catch (Exception $e) {
            // Silent fail - don't break existing functionality
            error_log("Error getting pending grievance count: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get total grievance notification count for sidebar
     */
    public function getGrievanceNotificationCount($user) {
        try {
            $account_type = $user['account_type'] ?? 'user';

            // Only supervisor, manager, admin and superadmin get notifications
            if (!in_array($account_type, ['supervisor', 'manager', 'admin', 'superadmin'])) {
                return 0;
            }

            return $this->getPendingGrievanceCount($user);

        } catch (Exception $e) {
            error_log("Error getting grievance notification count: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Create notification when new grievance is submitted
     */
    public function notifyNewGrievance($grievance_id, $is_anonymous = false) {
        try {
            if ($is_anonymous) {
                // Get anonymous grievance details
                $grievance = $this->db->fetch("
                    SELECT ag.*, ag.submitter_location as employee_location
                    FROM anonymous_grievances ag
                    WHERE ag.id = ?
                ", [$grievance_id]);

                if (!$grievance) {
                    return false;
                }

                $title = "New Anonymous Grievance";
                $message = "Anonymous grievance submitted: {$grievance['subject']} - {$grievance['category']}";

                // Notify only SuperAdmins for anonymous grievances
                $this->db->execute("
                    INSERT INTO grievance_notifications
                    (anonymous_grievance_id, notification_type, target_role, title, message, expires_at)
                    VALUES (?, 'new_anonymous_grievance', 'superadmin', ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))
                ", [$grievance_id, $title, $message]);

            } else {
                // Get normal grievance details
                $grievance = $this->db->fetch("
                    SELECT g.*
                    FROM grievances g
                    WHERE g.id = ?
                ", [$grievance_id]);

                if (!$grievance) {
                    return false;
                }

                $title = "New Grievance Submitted";
                $message = "{$grievance['employee_name']} has submitted a grievance: {$grievance['subject']} - {$grievance['category']}";

                // Notify SuperAdmins
                $this->db->execute("
                    INSERT INTO grievance_notifications
                    (grievance_id, notification_type, target_role, title, message, expires_at)
                    VALUES (?, 'new_grievance', 'superadmin', ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))
                ", [$grievance_id, $title, $message]);

                // Notify Admins in the same location
                if ($grievance['employee_location']) {
                    $this->db->execute("
                        INSERT INTO grievance_notifications
                        (grievance_id, notification_type, target_role, target_location, title, message, expires_at)
                        VALUES (?, 'new_grievance', 'admin', ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))
                    ", [$grievance_id, $grievance['employee_location'], $title, $message]);

                    // Notify Managers in the same location
                    $this->db->execute("
                        INSERT INTO grievance_notifications
                        (grievance_id, notification_type, target_role, target_location, title, message, expires_at)
                        VALUES (?, 'new_grievance', 'manager', ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))
                    ", [$grievance_id, $grievance['employee_location'], $title, $message]);

                    // Notify Supervisors in the same location
                    $this->db->execute("
                        INSERT INTO grievance_notifications
                        (grievance_id, notification_type, target_role, target_location, title, message, expires_at)
                        VALUES (?, 'new_grievance', 'supervisor', ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))
                    ", [$grievance_id, $grievance['employee_location'], $title, $message]);
                }
            }

            return true;

        } catch (Exception $e) {
            error_log("Error creating notification for grievance $grievance_id: " . $e->getMessage());
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
                UPDATE grievance_notifications
                SET is_read = 1
                WHERE id IN ($placeholders)
            ", $notification_ids);

            return true;

        } catch (Exception $e) {
            error_log("Error marking grievance notifications as read: " . $e->getMessage());
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
            } elseif ($account_type === 'manager') {
                $conditions[] = "(target_role = 'manager' AND (target_location = ? OR target_location IS NULL))";
                $params[] = $user_location;
            } elseif ($account_type === 'supervisor') {
                $conditions[] = "(target_role = 'supervisor' AND (target_location = ? OR target_location IS NULL))";
                $params[] = $user_location;
            } else {
                $conditions[] = "target_user_epf = ?";
                $params[] = $user_epf;
            }

            $where_clause = implode(' AND ', $conditions);
            $params[] = $limit;

            $notifications = $this->db->fetchAll("
                SELECT *
                FROM grievance_notifications
                WHERE $where_clause
                ORDER BY created_at DESC, is_read ASC
                LIMIT ?
            ", $params);

            return $notifications ?: [];

        } catch (Exception $e) {
            error_log("Error getting grievance notifications for user: " . $e->getMessage());
            return [];
        }
    }
}
?>