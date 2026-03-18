<?php
// Compare sidebar vs dashboard notification logic
chdir('/mnt/c/laragon/www/pbpictures/smarthrmjiffy/modules/transport');
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$db = new Database();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Sidebar vs Dashboard Notification Comparison</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        .comparison { display: flex; gap: 20px; }
        .test-box { flex: 1; padding: 20px; margin: 10px 0; border-radius: 8px; }
        .sidebar-test { background: #e3f2fd; border-left: 4px solid #2196f3; }
        .dashboard-test { background: #f3e5f5; border-left: 4px solid #9c27b0; }
        .result { font-size: 1.2em; font-weight: bold; padding: 10px; margin: 10px 0; border-radius: 5px; }
        .success { background: #e8f5e8; color: #2e7d32; }
        .error { background: #ffebee; color: #d32f2f; }
    </style>
</head>
<body class='p-4'>";

echo "<h1>🔍 Sidebar vs Dashboard Notification Debug</h1>";

echo "<div class='comparison'>";

// Test 1: Sidebar Logic
echo "<div class='test-box sidebar-test'>";
echo "<h2>📄 Sidebar Logic Test</h2>";

try {
    // Exactly replicate sidebar logic
    $canAccess = hasModulePermission($db, 'transport.transport_request') ||
                hasModulePermission($db, 'transport.transport_allocation') ||
                hasModulePermission($db, 'transport.vehicle_register') ||
                hasModulePermission($db, 'transport.vehicle_pool') ||
                hasModulePermission($db, 'transport.driver_pool') ||
                hasModulePermission($db, 'transport.view') ||
                isSuperAdmin();

    echo "<strong>Can Access:</strong> " . ($canAccess ? 'YES' : 'NO') . "<br>";

    if ($canAccess) {
        require_once 'TransportNotifications.php';
        $transport_notifications = TransportNotifications::getInstance($db);
        $user_data = getCurrentUser();
        $notification_count = $transport_notifications->getTransportNotificationCount($user_data);

        echo "<strong>User EPF:</strong> " . ($user_data['epf_number'] ?? 'N/A') . "<br>";
        echo "<strong>Account Type:</strong> " . ($user_data['account_type'] ?? 'N/A') . "<br>";
        echo "<strong>Location:</strong> " . ($user_data['location'] ?? $user_data['location_name'] ?? 'N/A') . "<br>";
        echo "<div class='result " . ($notification_count > 0 ? 'success' : 'error') . "'>Notification Count: $notification_count</div>";
    }
} catch (Exception $e) {
    echo "<div class='result error'>Error: " . $e->getMessage() . "</div>";
}

echo "</div>";

// Test 2: Dashboard Logic
echo "<div class='test-box dashboard-test'>";
echo "<h2>🚗 Dashboard Logic Test</h2>";

try {
    // Exactly replicate dashboard logic
    $user = getCurrentUser();

    echo "<strong>User EPF:</strong> " . ($user['epf_number'] ?? 'N/A') . "<br>";
    echo "<strong>Account Type:</strong> " . ($user['account_type'] ?? 'N/A') . "<br>";
    echo "<strong>Location:</strong> " . ($user['location'] ?? $user['location_name'] ?? 'N/A') . "<br>";

    // Check permission
    $hasPermission = hasModulePermission($db, 'transport.transport_allocation');
    echo "<strong>Has transport_allocation Permission:</strong> " . ($hasPermission ? 'YES' : 'NO') . "<br>";

    if ($hasPermission) {
        require_once 'TransportNotifications.php';
        $notifications = TransportNotifications::getInstance($db);
        $user_data = getCurrentUser();
        $notification_count = $notifications->getTransportNotificationCount($user_data);

        echo "<div class='result " . ($notification_count > 0 ? 'success' : 'error') . "'>Notification Count: $notification_count</div>";
    }
} catch (Exception $e) {
    echo "<div class='result error'>Error: " . $e->getMessage() . "</div>";
}

echo "</div>";
echo "</div>";

// Test 3: Direct Database Query
echo "<div class='test-box' style='background: #fff3e0; border-left: 4px solid #ff9800;'>";
echo "<h2>🗄️ Direct Database Test</h2>";

try {
    $user = getCurrentUser();
    $account_type = $user['account_type'] ?? 'user';
    $user_location = $user['location'] ?? $user['location_name'] ?? null;

    // Get user location from database if not in session
    if (!$user_location && isset($user['epf_number'])) {
        $location_data = $db->fetch("
            SELECT l.location_name
            FROM employees e
            LEFT JOIN locations l ON e.location_id = l.id
            WHERE e.epf_number = ?
        ", [$user['epf_number']]);

        if ($location_data) {
            $user_location = $location_data['location_name'];
        }
    }

    echo "<strong>Account Type:</strong> $account_type<br>";
    echo "<strong>User Location:</strong> '$user_location'<br>";

    if ($account_type === 'superadmin') {
        echo "<strong>Query Type:</strong> SuperAdmin (all pending)<br>";
        $result = $db->fetch("SELECT COUNT(*) as count FROM transport_requests WHERE status = 'pending'");
    } else {
        echo "<strong>Query Type:</strong> Admin (location-specific)<br>";
        $sql = "
            SELECT COUNT(*) as count
            FROM transport_requests tr
            LEFT JOIN employees e ON tr.emp_number = e.epf_number
            LEFT JOIN locations l ON e.location_id = l.id
            WHERE tr.status = 'pending'
            AND l.location_name = ?
        ";
        echo "<strong>SQL:</strong> <pre style='background: #f5f5f5; padding: 10px; font-size: 0.9em;'>$sql</pre>";
        echo "<strong>Parameter:</strong> '$user_location'<br>";
        $result = $db->fetch($sql, [$user_location]);
    }

    $count = $result ? (int)$result['count'] : 0;
    echo "<div class='result " . ($count > 0 ? 'success' : 'error') . "'>Direct DB Count: $count</div>";

    // Show all pending requests for reference
    echo "<strong>All Pending Requests:</strong><br>";
    $all_pending = $db->fetchAll("
        SELECT tr.id, tr.emp_number, tr.emp_name, tr.emp_location, tr.status,
               e.location_id, l.location_name
        FROM transport_requests tr
        LEFT JOIN employees e ON tr.emp_number = e.epf_number
        LEFT JOIN locations l ON e.location_id = l.id
        WHERE tr.status = 'pending'
    ");

    if (empty($all_pending)) {
        echo "No pending requests found.<br>";
    } else {
        echo "<table class='table table-sm table-bordered mt-2'>";
        echo "<thead><tr><th>ID</th><th>EPF</th><th>Name</th><th>Request Location</th><th>Employee Location</th></tr></thead>";
        foreach ($all_pending as $req) {
            echo "<tr>";
            echo "<td>{$req['id']}</td>";
            echo "<td>{$req['emp_number']}</td>";
            echo "<td>{$req['emp_name']}</td>";
            echo "<td>{$req['emp_location']}</td>";
            echo "<td>" . ($req['location_name'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

} catch (Exception $e) {
    echo "<div class='result error'>Error: " . $e->getMessage() . "</div>";
}

echo "</div>";

echo "<div class='mt-4'>";
echo "<a href='index.php' class='btn btn-primary'>← Back to Transport Dashboard</a>";
echo "<a href='debug_sql.php' class='btn btn-secondary ms-2'>SQL Debug</a>";
echo "</div>";

echo "</body></html>";
?>