<?php
/**
 * Centralized Notification System for SmartHRM
 * Handles notifications across all modules with role-based targeting
 */

class NotificationSystem {
    private $db;

    public function __construct($database = null) {
        $this->db = $database ?: new Database();
        $this->initializeDatabase();
    }

    /**
     * Initialize notifications table if it doesn't exist
     */
    private function initializeDatabase() {
        try {
            // Create notifications table
            $this->db->query("CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL DEFAULT '',
                description TEXT NOT NULL,
                type ENUM('employee', 'security', 'meal', 'transport', 'grievance', 'admin', 'system') NOT NULL,
                action_needed TINYINT(1) NOT NULL DEFAULT 0,
                module_link VARCHAR(255) DEFAULT '#',
                target_users TEXT,
                target_roles TEXT,
                created_by INT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
                expires_at TIMESTAMP NULL DEFAULT NULL,
                INDEX idx_type (type),
                INDEX idx_created_at (created_at),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Create notification_reads table
            $this->db->query("CREATE TABLE IF NOT EXISTS notification_reads (
                id INT AUTO_INCREMENT PRIMARY KEY,
                notification_id INT NOT NULL,
                user_id INT NOT NULL,
                read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_read (notification_id, user_id),
                INDEX idx_notification (notification_id),
                INDEX idx_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        } catch (Exception $e) {
            error_log("Notification system initialization error: " . $e->getMessage());
        }
    }

    /**
     * Send notification to specific users or roles
     */
    public function sendNotification($title, $description, $type, $options = []) {
        $defaults = [
            'action_needed' => false,
            'module_link' => '#',
            'target_users' => [],
            'target_roles' => [],
            'created_by' => $_SESSION['user_id'] ?? null,
            'priority' => 'medium',
            'expires_at' => null
        ];

        $options = array_merge($defaults, $options);

        // Ensure boolean values are properly converted
        $action_needed = $options['action_needed'] ? 1 : 0;

        // Ensure arrays are properly handled
        $target_users = is_array($options['target_users']) ? $options['target_users'] : [];
        $target_roles = is_array($options['target_roles']) ? $options['target_roles'] : [];

        // Validate required fields
        if (empty($title) || empty($description) || empty($type)) {
            error_log("Notification failed: Missing required fields");
            return false;
        }

        try {
            $this->db->query("
                INSERT INTO notifications (title, description, type, action_needed, module_link,
                                         target_users, target_roles, created_by, priority, expires_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                trim($title),
                trim($description),
                $type,
                $action_needed,
                $options['module_link'] ?: '#',
                json_encode($target_users),
                json_encode($target_roles),
                $options['created_by'],
                $options['priority'],
                $options['expires_at']
            ]);

            return true;
        } catch (Exception $e) {
            error_log("Failed to send notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get recent notifications for a specific user (within specified hours)
     */
    public function getRecentUserNotifications($user_id, $limit = 10, $hours = 24) {
        try {
            // Ensure tables exist first
            $this->initializeDatabase();

            // Try to get user info from employees table first, then from users table
            $user = $this->db->fetch("
                SELECT account_type, account_type_id
                FROM employees
                WHERE id = ?
            ", [$user_id]);

            // If not found in employees, try users table
            if (!$user) {
                $user = $this->db->fetch("
                    SELECT account_type, account_type_id
                    FROM users
                    WHERE id = ?
                ", [$user_id]);
            }

            // If still not found, assume superadmin for fallback
            if (!$user) {
                $user = ['account_type' => 'superadmin', 'account_type_id' => 1];
            }

            // Check if notifications table exists
            $tables = $this->db->fetchAll("SHOW TABLES LIKE 'notifications'");
            if (empty($tables)) return [];

            // Get user's location and department for filtering
            $user_details = $this->db->fetch("
                SELECT l.location_name, e.department
                FROM employees e
                LEFT JOIN locations l ON e.location_id = l.id
                WHERE e.id = ?
            ", [$user_id]);

            if (!$user_details) {
                $user_details = $this->db->fetch("
                    SELECT l.location_name, e.department
                    FROM users u
                    LEFT JOIN employees e ON u.employee_id = e.id
                    LEFT JOIN locations l ON e.location_id = l.id
                    WHERE u.id = ?
                ", [$user_id]);
            }

            // Build hierarchy filter based on user type
            $location_filter = "";
            $params = [$user_id, $user['account_type'], $hours, $limit];

            if ($user_details && $user['account_type'] !== 'superadmin') {
                $user_location = $user_details['location_name'];
                $user_department = $user_details['department'];

                if ($user['account_type'] === 'manager') {
                    // Managers see notifications from their department across ALL locations
                    if ($user_department) {
                        $location_filter = "AND (
                            JSON_CONTAINS(target_users, JSON_QUOTE(?))
                            OR target_users = '\"all\"'
                            OR (JSON_CONTAINS(target_roles, JSON_QUOTE(?)) AND (
                                description LIKE CONCAT('%', ?, '%')
                                OR title LIKE CONCAT('%', ?, '%')
                            ))
                        )";
                        array_splice($params, 2, 0, [$user_id, $user['account_type'], $user_department, $user_department]);
                    }
                } elseif (($user['account_type'] === 'admin' || $user['account_type'] === 'supervisor') && $user_location) {
                    // Admins/Supervisors see notifications from their location only
                    // BUT also see role-based notifications that don't have location info (legacy notifications)
                    $location_filter = "AND (
                        JSON_CONTAINS(target_users, JSON_QUOTE(?))
                        OR target_users = '\"all\"'
                        OR (JSON_CONTAINS(target_roles, JSON_QUOTE(?)) AND (
                            description LIKE CONCAT('%', ?, '%')
                            OR title LIKE CONCAT('% ', ?, '%')
                            OR title LIKE CONCAT('%- ', ?, '%')
                            OR (title NOT LIKE '% - %' AND title NOT LIKE '%from %' AND title NOT LIKE '%at %')
                        ))
                    )";
                    array_splice($params, 2, 0, [$user_id, $user['account_type'], $user_location, $user_location, $user_location]);
                }
            }

            $notifications = $this->db->fetchAll("
                SELECT n.* FROM notifications n
                LEFT JOIN notification_reads nr ON n.id = nr.notification_id AND nr.user_id = ?
                WHERE n.is_active = 1
                AND (
                    JSON_CONTAINS(n.target_users, JSON_QUOTE(?))
                    OR JSON_CONTAINS(n.target_roles, JSON_QUOTE(?))
                    OR n.target_users = '\"all\"'
                )
                $location_filter
                AND (n.expires_at IS NULL OR n.expires_at > NOW())
                AND n.created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                AND nr.id IS NULL
                ORDER BY n.priority = 'urgent' DESC,
                         n.priority = 'high' DESC,
                         n.created_at DESC
                LIMIT ?
            ", array_merge([$user_id], $params));

            // Add module-specific styling
            foreach ($notifications as &$notification) {
                $notification['color'] = $this->getNotificationColor($notification['type']);
                $notification['icon'] = $this->getNotificationIcon($notification['type']);
            }

            return $notifications;
        } catch (Exception $e) {
            error_log("Failed to get recent user notifications: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get notifications for a specific user
     */
    public function getUserNotifications($user_id, $limit = 10) {
        try {
            // Ensure tables exist first
            $this->initializeDatabase();

            // Try to get user info from employees table first, then from users table
            $user = $this->db->fetch("
                SELECT account_type, account_type_id
                FROM employees
                WHERE id = ?
            ", [$user_id]);

            // If not found in employees, try users table
            if (!$user) {
                $user = $this->db->fetch("
                    SELECT account_type, account_type_id
                    FROM users
                    WHERE id = ?
                ", [$user_id]);
            }

            // If still not found, assume superadmin for fallback
            if (!$user) {
                $user = ['account_type' => 'superadmin', 'account_type_id' => 1];
            }

            // Check if notifications table exists
            $tables = $this->db->fetchAll("SHOW TABLES LIKE 'notifications'");
            if (empty($tables)) return [];

            // Get user's location and department for filtering
            $user_details = $this->db->fetch("
                SELECT l.location_name, e.department
                FROM employees e
                LEFT JOIN locations l ON e.location_id = l.id
                WHERE e.id = ?
            ", [$user_id]);

            if (!$user_details) {
                $user_details = $this->db->fetch("
                    SELECT l.location_name, e.department
                    FROM users u
                    LEFT JOIN employees e ON u.employee_id = e.id
                    LEFT JOIN locations l ON e.location_id = l.id
                    WHERE u.id = ?
                ", [$user_id]);
            }

            // Build hierarchy filter based on user type
            $location_filter = "";
            $params = [$user_id, $user['account_type'], $limit];

            if ($user_details && $user['account_type'] !== 'superadmin') {
                $user_location = $user_details['location_name'];
                $user_department = $user_details['department'];

                if ($user['account_type'] === 'manager') {
                    // Managers see notifications from their department across ALL locations
                    if ($user_department) {
                        $location_filter = "AND (
                            JSON_CONTAINS(target_users, JSON_QUOTE(?))
                            OR target_users = '\"all\"'
                            OR (JSON_CONTAINS(target_roles, JSON_QUOTE(?)) AND (
                                description LIKE CONCAT('%', ?, '%')
                                OR title LIKE CONCAT('%', ?, '%')
                            ))
                        )";
                        array_splice($params, 2, 0, [$user_id, $user['account_type'], $user_department, $user_department]);
                    }
                } elseif (($user['account_type'] === 'admin' || $user['account_type'] === 'supervisor') && $user_location) {
                    // Admins/Supervisors see notifications from their location only
                    // BUT also see role-based notifications that don't have location info (legacy notifications)
                    $location_filter = "AND (
                        JSON_CONTAINS(target_users, JSON_QUOTE(?))
                        OR target_users = '\"all\"'
                        OR (JSON_CONTAINS(target_roles, JSON_QUOTE(?)) AND (
                            description LIKE CONCAT('%', ?, '%')
                            OR title LIKE CONCAT('% ', ?, '%')
                            OR title LIKE CONCAT('%- ', ?, '%')
                            OR (title NOT LIKE '% - %' AND title NOT LIKE '%from %' AND title NOT LIKE '%at %')
                        ))
                    )";
                    array_splice($params, 2, 0, [$user_id, $user['account_type'], $user_location, $user_location, $user_location]);
                }
            }

            $notifications = $this->db->fetchAll("
                SELECT n.*,
                       CASE WHEN nr.id IS NOT NULL THEN 1 ELSE 0 END as is_read
                FROM notifications n
                LEFT JOIN notification_reads nr ON n.id = nr.notification_id AND nr.user_id = ?
                WHERE n.is_active = 1
                AND (
                    JSON_CONTAINS(n.target_users, JSON_QUOTE(?))
                    OR JSON_CONTAINS(n.target_roles, JSON_QUOTE(?))
                    OR n.target_users = '\"all\"'
                )
                $location_filter
                AND (n.expires_at IS NULL OR n.expires_at > NOW())
                ORDER BY n.priority = 'urgent' DESC,
                         n.priority = 'high' DESC,
                         n.created_at DESC
                LIMIT ?
            ", array_merge([$user_id], $params));

            // Add module-specific styling
            foreach ($notifications as &$notification) {
                $notification['color'] = $this->getNotificationColor($notification['type']);
                $notification['icon'] = $this->getNotificationIcon($notification['type']);
            }

            return $notifications;
        } catch (Exception $e) {
            error_log("Failed to get user notifications: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Module-specific notification senders
     */

    // Employee Module Notifications
    public function notifyEmployeeAdded($employee_name, $employee_epf) {
        return $this->sendNotification(
            "New Employee Added",
            "$employee_name (EPF: $employee_epf) has been added to the system",
            "employee",
            [
                'module_link' => 'modules/employee/',
                'target_roles' => ['admin', 'superadmin', 'manager'],
                'action_needed' => false,
                'priority' => 'low'
            ]
        );
    }

    public function notifyEmployeeUpdated($employee_name, $updated_by_name) {
        return $this->sendNotification(
            "Employee Information Updated",
            "$employee_name's information was updated by $updated_by_name",
            "employee",
            [
                'module_link' => 'modules/employee/',
                'target_roles' => ['admin', 'superadmin', 'manager'],
                'action_needed' => false,
                'priority' => 'low'
            ]
        );
    }

    // Meal Module Notifications
    public function notifyMealRequest($requestor_name, $requestor_location, $meal_type = 'employee') {
        // Get location-specific admins and managers
        try {
            $location_users = $this->db->fetchAll("
                SELECT DISTINCT e.id
                FROM employees e
                WHERE e.is_active = 1
                AND (e.location_id = (SELECT id FROM locations WHERE location_name = ? LIMIT 1)
                     OR e.department = ?)
                AND e.account_type IN ('admin', 'superadmin', 'manager')
            ", [$requestor_location, $requestor_location]);

            $target_user_ids = array_column($location_users, 'id');

            return $this->sendNotification(
                "New Meal Request - $requestor_location",
                "$requestor_name from $requestor_location submitted a $meal_type meal request",
                "meal",
                [
                    'module_link' => 'modules/meal/',
                    'target_users' => $target_user_ids,
                    'target_roles' => ['admin', 'superadmin', 'manager'], // Fallback
                    'action_needed' => true,
                    'priority' => 'medium'
                ]
            );
        } catch (Exception $e) {
            error_log("Error getting location-specific users for meal notification: " . $e->getMessage());
            // Fallback to role-based notification
            return $this->sendNotification(
                "New Meal Request",
                "$requestor_name submitted a meal request",
                "meal",
                [
                    'module_link' => 'modules/meal/',
                    'target_roles' => ['admin', 'superadmin', 'manager'],
                    'action_needed' => true,
                    'priority' => 'medium'
                ]
            );
        }
    }

    public function notifyMealRequestApproved($requestor_name, $approved_by) {
        return $this->sendNotification(
            "Meal Request Approved",
            "Your meal request has been approved by $approved_by",
            "meal",
            [
                'module_link' => 'modules/meal/',
                'target_users' => [$requestor_name], // Should be user ID
                'action_needed' => false,
                'priority' => 'low'
            ]
        );
    }

    // Transport Module Notifications
    public function notifyTransportRequest($requestor_name, $destination) {
        return $this->sendNotification(
            "Transport Request Submitted",
            "$requestor_name requested transport to $destination",
            "transport",
            [
                'module_link' => 'modules/transport/',
                'target_roles' => ['admin', 'superadmin', 'manager'],
                'action_needed' => true,
                'priority' => 'high'
            ]
        );
    }

    public function notifyVehicleMaintenance($vehicle_number, $issue) {
        return $this->sendNotification(
            "Vehicle Maintenance Required",
            "Vehicle $vehicle_number requires maintenance: $issue",
            "transport",
            [
                'module_link' => 'modules/transport/',
                'target_roles' => ['admin', 'superadmin'],
                'action_needed' => true,
                'priority' => 'high'
            ]
        );
    }

    // Grievance Module Notifications
    public function notifyNewGrievance($grievance_type, $anonymous = false) {
        $title = $anonymous ? "New Anonymous Grievance" : "New Grievance Submitted";
        $description = $anonymous ?
            "A new anonymous grievance has been submitted regarding $grievance_type" :
            "A new grievance has been submitted regarding $grievance_type";

        return $this->sendNotification(
            $title,
            $description,
            "grievance",
            [
                'module_link' => 'modules/grievance/',
                'target_roles' => ['admin', 'superadmin', 'manager'],
                'action_needed' => true,
                'priority' => 'urgent'
            ]
        );
    }

    public function notifyGrievanceStatusUpdate($grievance_id, $status, $assigned_to = null) {
        $description = $assigned_to ?
            "Grievance #$grievance_id status updated to '$status' and assigned to $assigned_to" :
            "Grievance #$grievance_id status updated to '$status'";

        return $this->sendNotification(
            "Grievance Status Updated",
            $description,
            "grievance",
            [
                'module_link' => 'modules/grievance/',
                'target_roles' => ['admin', 'superadmin', 'manager'],
                'action_needed' => $status === 'Under Investigation',
                'priority' => 'high'
            ]
        );
    }

    // Admin Module Notifications
    public function notifyPasswordReset($employee_name, $reset_by) {
        return $this->sendNotification(
            "Password Reset",
            "$employee_name's password was reset by $reset_by",
            "security",
            [
                'module_link' => 'modules/admin/password_management_enhanced.php',
                'target_roles' => ['admin', 'superadmin'],
                'action_needed' => true,
                'priority' => 'medium'
            ]
        );
    }

    public function notifyPermissionChange($employee_name, $new_role, $changed_by) {
        return $this->sendNotification(
            "User Permissions Updated",
            "$employee_name's role was changed to '$new_role' by $changed_by",
            "admin",
            [
                'module_link' => 'modules/admin/',
                'target_roles' => ['superadmin'],
                'action_needed' => false,
                'priority' => 'medium'
            ]
        );
    }

    public function notifySystemMaintenance($maintenance_type, $scheduled_time) {
        return $this->sendNotification(
            "System Maintenance Scheduled",
            "$maintenance_type maintenance scheduled for $scheduled_time",
            "system",
            [
                'module_link' => 'modules/admin/',
                'target_users' => ['all'],
                'action_needed' => false,
                'priority' => 'medium'
            ]
        );
    }

    /**
     * Utility functions
     */
    private function getNotificationColor($type) {
        $colors = [
            'employee' => 'success',
            'security' => 'warning',
            'meal' => 'info',
            'transport' => 'primary',
            'grievance' => 'danger',
            'admin' => 'secondary',
            'system' => 'dark'
        ];
        return $colors[$type] ?? 'primary';
    }

    private function getNotificationIcon($type) {
        $icons = [
            'employee' => 'fas fa-user-plus',
            'security' => 'fas fa-shield-alt',
            'meal' => 'fas fa-utensils',
            'transport' => 'fas fa-car',
            'grievance' => 'fas fa-exclamation-triangle',
            'admin' => 'fas fa-cogs',
            'system' => 'fas fa-server'
        ];
        return $icons[$type] ?? 'fas fa-bell';
    }

    /**
     * Mark notifications as read/viewed
     */
    public function markAsRead($notification_ids, $user_id) {
        try {
            // Ensure notification_ids is an array
            if (!is_array($notification_ids)) {
                $notification_ids = [$notification_ids];
            }

            // Mark as read
            foreach ($notification_ids as $notification_id) {
                // Extract numeric ID if it has 'notification_' prefix
                if (is_string($notification_id) && strpos($notification_id, 'notification_') === 0) {
                    $notification_id = str_replace('notification_', '', $notification_id);
                }

                if (is_numeric($notification_id) && is_numeric($user_id)) {
                    $this->db->query("
                        INSERT IGNORE INTO notification_reads (notification_id, user_id)
                        VALUES (?, ?)
                    ", [(int)$notification_id, (int)$user_id]);
                }
            }

            return true;
        } catch (Exception $e) {
            error_log("Failed to mark notifications as read: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get recent unread notification count for user (within specified hours)
     */
    public function getRecentUnreadCount($user_id, $hours = 24) {
        try {
            // Ensure tables exist first
            $this->initializeDatabase();

            // Try to get user info from employees table first, then from users table
            $user = $this->db->fetch("
                SELECT account_type FROM employees WHERE id = ?
            ", [$user_id]);

            // If not found in employees, try users table
            if (!$user) {
                $user = $this->db->fetch("
                    SELECT account_type FROM users WHERE id = ?
                ", [$user_id]);
            }

            // If still not found, assume superadmin for fallback
            if (!$user) {
                $user = ['account_type' => 'superadmin'];
            }

            // Check if tables exist before complex query
            $tables = $this->db->fetchAll("SHOW TABLES LIKE 'notifications'");
            if (empty($tables)) return 0;

            // Get user's location and department for filtering
            $user_details = $this->db->fetch("
                SELECT l.location_name, e.department
                FROM employees e
                LEFT JOIN locations l ON e.location_id = l.id
                WHERE e.id = ?
            ", [$user_id]);

            if (!$user_details) {
                $user_details = $this->db->fetch("
                    SELECT l.location_name, e.department
                    FROM users u
                    LEFT JOIN employees e ON u.employee_id = e.id
                    LEFT JOIN locations l ON e.location_id = l.id
                    WHERE u.id = ?
                ", [$user_id]);
            }

            // Build hierarchy filter based on user type
            $location_filter = "";
            $params = [$user_id, $user_id, $user['account_type'], $hours];

            if ($user_details && $user['account_type'] !== 'superadmin') {
                $user_location = $user_details['location_name'];
                $user_department = $user_details['department'];

                if ($user['account_type'] === 'manager') {
                    // Managers see notifications from their department across ALL locations
                    if ($user_department) {
                        $location_filter = "AND (
                            JSON_CONTAINS(n.target_users, JSON_QUOTE(?))
                            OR n.target_users = '\"all\"'
                            OR (JSON_CONTAINS(n.target_roles, JSON_QUOTE(?)) AND (
                                n.description LIKE CONCAT('%', ?, '%')
                                OR n.title LIKE CONCAT('%', ?, '%')
                            ))
                        )";
                        array_push($params, $user_id, $user['account_type'], $user_department, $user_department);
                    }
                } elseif (($user['account_type'] === 'admin' || $user['account_type'] === 'supervisor') && $user_location) {
                    // Admins/Supervisors see notifications from their location only
                    // BUT also see role-based notifications that don't have location info (legacy notifications)
                    $location_filter = "AND (
                        JSON_CONTAINS(n.target_users, JSON_QUOTE(?))
                        OR n.target_users = '\"all\"'
                        OR (JSON_CONTAINS(n.target_roles, JSON_QUOTE(?)) AND (
                            n.description LIKE CONCAT('%', ?, '%')
                            OR n.title LIKE CONCAT('% ', ?, '%')
                            OR n.title LIKE CONCAT('%- ', ?, '%')
                            OR (n.title NOT LIKE '% - %' AND n.title NOT LIKE '%from %' AND n.title NOT LIKE '%at %')
                        ))
                    )";
                    array_push($params, $user_id, $user['account_type'], $user_location, $user_location, $user_location);
                }
            }

            $count = $this->db->fetch("
                SELECT COUNT(*) as count FROM notifications n
                LEFT JOIN notification_reads nr ON n.id = nr.notification_id AND nr.user_id = ?
                WHERE n.is_active = 1
                AND (
                    JSON_CONTAINS(n.target_users, JSON_QUOTE(?))
                    OR JSON_CONTAINS(n.target_roles, JSON_QUOTE(?))
                    OR n.target_users = '\"all\"'
                )
                $location_filter
                AND (n.expires_at IS NULL OR n.expires_at > NOW())
                AND n.created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                AND nr.id IS NULL
            ", $params);

            return $count['count'] ?? 0;
        } catch (Exception $e) {
            error_log("Failed to get recent unread count: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get unread notification count for user
     */
    public function getUnreadCount($user_id) {
        try {
            // Ensure tables exist first
            $this->initializeDatabase();

            // Try to get user info from employees table first, then from users table
            $user = $this->db->fetch("
                SELECT account_type FROM employees WHERE id = ?
            ", [$user_id]);

            // If not found in employees, try users table
            if (!$user) {
                $user = $this->db->fetch("
                    SELECT account_type FROM users WHERE id = ?
                ", [$user_id]);
            }

            // If still not found, assume superadmin for fallback
            if (!$user) {
                $user = ['account_type' => 'superadmin'];
            }

            // Check if tables exist before complex query
            $tables = $this->db->fetchAll("SHOW TABLES LIKE 'notifications'");
            if (empty($tables)) return 0;

            // Get user's location and department for filtering
            $user_details = $this->db->fetch("
                SELECT l.location_name, e.department
                FROM employees e
                LEFT JOIN locations l ON e.location_id = l.id
                WHERE e.id = ?
            ", [$user_id]);

            if (!$user_details) {
                $user_details = $this->db->fetch("
                    SELECT l.location_name, e.department
                    FROM users u
                    LEFT JOIN employees e ON u.employee_id = e.id
                    LEFT JOIN locations l ON e.location_id = l.id
                    WHERE u.id = ?
                ", [$user_id]);
            }

            // Build hierarchy filter based on user type
            $location_filter = "";
            $params = [$user_id, $user_id, $user['account_type']];

            if ($user_details && $user['account_type'] !== 'superadmin') {
                $user_location = $user_details['location_name'];
                $user_department = $user_details['department'];

                if ($user['account_type'] === 'manager') {
                    // Managers see notifications from their department across ALL locations
                    if ($user_department) {
                        $location_filter = "AND (
                            JSON_CONTAINS(n.target_users, JSON_QUOTE(?))
                            OR n.target_users = '\"all\"'
                            OR (JSON_CONTAINS(n.target_roles, JSON_QUOTE(?)) AND (
                                n.description LIKE CONCAT('%', ?, '%')
                                OR n.title LIKE CONCAT('%', ?, '%')
                            ))
                        )";
                        array_push($params, $user_id, $user['account_type'], $user_department, $user_department);
                    }
                } elseif (($user['account_type'] === 'admin' || $user['account_type'] === 'supervisor') && $user_location) {
                    // Admins/Supervisors see notifications from their location only
                    // BUT also see role-based notifications that don't have location info (legacy notifications)
                    $location_filter = "AND (
                        JSON_CONTAINS(n.target_users, JSON_QUOTE(?))
                        OR n.target_users = '\"all\"'
                        OR (JSON_CONTAINS(n.target_roles, JSON_QUOTE(?)) AND (
                            n.description LIKE CONCAT('%', ?, '%')
                            OR n.title LIKE CONCAT('% ', ?, '%')
                            OR n.title LIKE CONCAT('%- ', ?, '%')
                            OR (n.title NOT LIKE '% - %' AND n.title NOT LIKE '%from %' AND n.title NOT LIKE '%at %')
                        ))
                    )";
                    array_push($params, $user_id, $user['account_type'], $user_location, $user_location, $user_location);
                }
            }

            $count = $this->db->fetch("
                SELECT COUNT(*) as count FROM notifications n
                LEFT JOIN notification_reads nr ON n.id = nr.notification_id AND nr.user_id = ?
                WHERE n.is_active = 1
                AND (
                    JSON_CONTAINS(n.target_users, JSON_QUOTE(?))
                    OR JSON_CONTAINS(n.target_roles, JSON_QUOTE(?))
                    OR n.target_users = '\"all\"'
                )
                $location_filter
                AND (n.expires_at IS NULL OR n.expires_at > NOW())
                AND nr.id IS NULL
            ", $params);

            return $count['count'] ?? 0;
        } catch (Exception $e) {
            error_log("Failed to get unread count: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Clean expired notifications
     */
    public function cleanExpiredNotifications() {
        try {
            $this->db->query("
                UPDATE notifications
                SET is_active = 0
                WHERE expires_at IS NOT NULL AND expires_at < NOW()
            ");
            return true;
        } catch (Exception $e) {
            error_log("Failed to clean expired notifications: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clean old notifications (older than specified hours)
     */
    public function cleanOldNotifications($hours = 48) {
        try {
            $this->db->query("
                UPDATE notifications
                SET is_active = 0
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? HOUR)
            ", [$hours]);
            return true;
        } catch (Exception $e) {
            error_log("Failed to clean old notifications: " . $e->getMessage());
            return false;
        }
    }
}

// Global notification instance
function getNotificationSystem() {
    static $instance = null;
    if ($instance === null) {
        $instance = new NotificationSystem();
    }
    return $instance;
}
?>