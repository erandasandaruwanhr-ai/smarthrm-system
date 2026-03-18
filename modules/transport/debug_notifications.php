<?php
// Debug notification system for transport module
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Change to the correct working directory
chdir('/mnt/c/laragon/www/pbpictures/smarthrmjiffy/modules/transport');
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$db = new Database();
$user = getCurrentUser();

echo "<h2>🔍 Transport Notification Debug</h2>";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h3>Current User Info:</h3>";
echo "EPF: " . ($user['epf_number'] ?? 'N/A') . "<br>";
echo "Name: " . ($user['name'] ?? 'N/A') . "<br>";
echo "Account Type: " . ($user['account_type'] ?? 'N/A') . "<br>";
echo "Location: " . ($user['location'] ?? $user['location_name'] ?? 'N/A') . "<br>";
echo "</div>";

// Test hasModulePermission function
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h3>Permission Check:</h3>";
$has_allocation_permission = hasModulePermission($db, 'transport.transport_allocation');
echo "transport.transport_allocation: " . ($has_allocation_permission ? 'YES' : 'NO') . "<br>";
echo "</div>";

// Test notification count calculation
echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h3>Notification Count Test:</h3>";
try {
    require_once 'TransportNotifications.php';
    $notifications = TransportNotifications::getInstance($db);
    $notification_count = $notifications->getPendingAllocationCount($user);

    echo "Notification Count: <strong>$notification_count</strong><br>";

    // Show the calculation logic being used
    $account_type = $user['account_type'] ?? 'user';
    $user_location = $user['location'] ?? $user['location_name'] ?? null;

    echo "Account Type: $account_type<br>";
    echo "User Location: $user_location<br>";

    if (!in_array($account_type, ['admin', 'superadmin'])) {
        echo "❌ User type '$account_type' does not get notifications (only admin/superadmin)<br>";
    } else {
        echo "✅ User type '$account_type' should get notifications<br>";

        if ($account_type === 'superadmin') {
            echo "🌍 SuperAdmin sees all pending requests<br>";
        } else {
            echo "🏢 Admin sees requests from location: $user_location<br>";
        }
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
echo "</div>";

// Show pending requests in database
echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h3>Database State:</h3>";
try {
    $all_pending = $db->fetchAll("
        SELECT tr.id, tr.emp_number, tr.emp_name, tr.emp_location, tr.status,
               e.location_id, l.location_name
        FROM transport_requests tr
        LEFT JOIN employees e ON tr.emp_number = e.epf_number
        LEFT JOIN locations l ON e.location_id = l.id
        WHERE tr.status = 'pending'
        ORDER BY tr.id
    ");

    echo "Total pending requests: " . count($all_pending) . "<br>";
    if (!empty($all_pending)) {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse; margin-top: 10px;'>";
        echo "<tr><th>ID</th><th>EPF</th><th>Name</th><th>Location</th><th>Status</th></tr>";
        foreach ($all_pending as $req) {
            echo "<tr>";
            echo "<td>" . $req['id'] . "</td>";
            echo "<td>" . $req['emp_number'] . "</td>";
            echo "<td>" . $req['emp_name'] . "</td>";
            echo "<td>" . ($req['location_name'] ?? $req['emp_location']) . "</td>";
            echo "<td>" . $req['status'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}
echo "</div>";

echo "<div style='margin: 20px 0;'>";
echo "<a href='index.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>← Back to Transport Dashboard</a>";
echo "</div>";
?>