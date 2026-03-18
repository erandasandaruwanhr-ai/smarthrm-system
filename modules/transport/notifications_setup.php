<?php
// Transport Notification System Setup
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$db = new Database();

try {
    // Create notifications table
    $create_notifications_table = "CREATE TABLE IF NOT EXISTS transport_notifications (
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
        FOREIGN KEY (request_id) REFERENCES transport_requests(id) ON DELETE CASCADE,
        INDEX idx_target_role (target_role),
        INDEX idx_target_location (target_location),
        INDEX idx_target_user (target_user_epf),
        INDEX idx_is_read (is_read),
        INDEX idx_created_at (created_at)
    )";

    $db->execute($create_notifications_table);
    echo "✅ Transport notifications table created successfully!<br>";

    // Create notification functions table for tracking notification counts
    $create_notification_counts = "CREATE TABLE IF NOT EXISTS transport_notification_counts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_epf VARCHAR(20) NOT NULL,
        unread_count INT DEFAULT 0,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user (user_epf)
    )";

    $db->execute($create_notification_counts);
    echo "✅ Transport notification counts table created successfully!<br>";

} catch (Exception $e) {
    echo "❌ Error creating tables: " . $e->getMessage() . "<br>";
}

// Function to create notification for new transport requests
function createTransportNotification($db, $request_id, $notification_type, $request_data = null) {
    try {
        // Get request data if not provided
        if (!$request_data) {
            $request_data = $db->fetch("
                SELECT tr.*, e.name as requester_name, l.location_name as requester_location
                FROM transport_requests tr
                LEFT JOIN employees e ON tr.emp_number = e.epf_number
                LEFT JOIN employees emp ON tr.emp_number = emp.epf_number
                LEFT JOIN locations l ON emp.location_id = l.id
                WHERE tr.id = ?
            ", [$request_id]);

            if (!$request_data) {
                error_log("Transport notification: Request not found for ID $request_id");
                return false;
            }
        }

        $notifications_created = 0;

        switch ($notification_type) {
            case 'new_request':
                // Notify SuperAdmins (all locations)
                $title = "New Transport Request";
                $message = "{$request_data['emp_name']} has submitted a transport request for {$request_data['departure_date']}";

                $db->execute("
                    INSERT INTO transport_notifications
                    (request_id, notification_type, target_role, title, message, expires_at)
                    VALUES (?, ?, 'superadmin', ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))
                ", [$request_id, $notification_type, $title, $message]);
                $notifications_created++;

                // Notify Admins (same location only)
                if ($request_data['requester_location']) {
                    $db->execute("
                        INSERT INTO transport_notifications
                        (request_id, notification_type, target_role, target_location, title, message, expires_at)
                        VALUES (?, ?, 'admin', ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))
                    ", [$request_id, $notification_type, $request_data['requester_location'], $title, $message]);
                    $notifications_created++;
                }
                break;

            case 'request_allocated':
                // Notify the requester
                $title = "Transport Request Allocated";
                $message = "Your transport request for {$request_data['departure_date']} has been allocated";

                $db->execute("
                    INSERT INTO transport_notifications
                    (request_id, notification_type, target_role, target_user_epf, title, message, expires_at)
                    VALUES (?, ?, 'user', ?, ?, ?, DATE_ADD(NOW(), INTERVAL 3 DAY))
                ", [$request_id, $notification_type, $request_data['emp_number'], $title, $message]);
                $notifications_created++;
                break;
        }

        // Update notification counts for affected users
        updateNotificationCounts($db);

        error_log("Transport notification: Created $notifications_created notifications for request $request_id");
        return $notifications_created > 0;

    } catch (Exception $e) {
        error_log("Transport notification error: " . $e->getMessage());
        return false;
    }
}

// Function to update notification counts for users
function updateNotificationCounts($db) {
    try {
        // Update counts for all users who have notifications
        $db->execute("
            INSERT INTO transport_notification_counts (user_epf, unread_count)
            SELECT
                CASE
                    WHEN target_user_epf IS NOT NULL THEN target_user_epf
                    ELSE (
                        SELECT e.epf_number
                        FROM employees e
                        WHERE e.account_type = tn.target_role
                        AND (tn.target_location IS NULL OR e.location_id = (
                            SELECT id FROM locations WHERE location_name = tn.target_location
                        ))
                        LIMIT 1
                    )
                END as user_epf,
                COUNT(*) as unread_count
            FROM transport_notifications tn
            WHERE tn.is_read = 0
            AND (tn.expires_at IS NULL OR tn.expires_at > NOW())
            GROUP BY user_epf
            ON DUPLICATE KEY UPDATE
            unread_count = VALUES(unread_count),
            last_updated = NOW()
        ");
    } catch (Exception $e) {
        error_log("Error updating notification counts: " . $e->getMessage());
    }
}

echo "<br>✅ Notification system setup complete!<br>";
echo "<a href='index.php'>← Back to Transport Dashboard</a>";
?>