<?php
// Test transport notification system
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$db = new Database();
$user = getCurrentUser();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Transport Notification System Test</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body class='p-4'>
<div class='container'>
    <h2>🚀 Transport Notification System Test</h2>";

echo "<div class='card mb-3'>
        <div class='card-body'>
            <h5>User Information</h5>
            <p>EPF: " . ($user['epf_number'] ?? 'N/A') . "</p>
            <p>Name: " . ($user['name'] ?? 'N/A') . "</p>
            <p>Account Type: " . ($user['account_type'] ?? 'N/A') . "</p>
            <p>Location: " . ($user['location'] ?? $user['location_name'] ?? 'N/A') . "</p>
        </div>
      </div>";

// Test 1: Initialize notification system
echo "<div class='card mb-3'>
        <div class='card-body'>
            <h5>Test 1: Initialize Notification System</h5>";

try {
    require_once 'TransportNotifications.php';
    $notifications = TransportNotifications::getInstance($db);
    echo "<span class='badge bg-success'>✅ Notification system loaded successfully</span><br>";

    // Test 2: Get notification count
    echo "<h6 class='mt-3'>Test 2: Get Notification Counts</h6>";
    $pending_count = $notifications->getPendingAllocationCount($user);
    echo "Pending allocation count: <span class='badge bg-primary'>$pending_count</span><br>";

    $transport_count = $notifications->getTransportNotificationCount($user);
    echo "Transport notification count: <span class='badge bg-info'>$transport_count</span><br>";

    // Test 3: Check pending requests in database
    echo "<h6 class='mt-3'>Test 3: Database Check</h6>";

    $total_pending = $db->fetch("SELECT COUNT(*) as count FROM transport_requests WHERE status = 'pending'");
    echo "Total pending requests: <span class='badge bg-warning'>" . ($total_pending['count'] ?? 0) . "</span><br>";

    if (in_array($user['account_type'], ['admin', 'superadmin'])) {
        echo "<span class='badge bg-info'>You have permission to see allocation notifications</span><br>";
    } else {
        echo "<span class='badge bg-secondary'>You don't have permission to see allocation notifications</span><br>";
    }

} catch (Exception $e) {
    echo "<span class='badge bg-danger'>❌ Error: " . $e->getMessage() . "</span>";
}

echo "        </div>
      </div>";

// Test 4: Check existing transport functionality
echo "<div class='card mb-3'>
        <div class='card-body'>
            <h5>Test 4: Existing Functionality Check</h5>";

try {
    // Test basic database access
    $vehicle_count = $db->fetch("SELECT COUNT(*) as count FROM vehicles");
    echo "Vehicles in database: <span class='badge bg-success'>" . ($vehicle_count['count'] ?? 0) . "</span><br>";

    $driver_count = $db->fetch("SELECT COUNT(*) as count FROM drivers");
    echo "Drivers in database: <span class='badge bg-success'>" . ($driver_count['count'] ?? 0) . "</span><br>";

    echo "<span class='badge bg-success'>✅ All existing functionality working</span>";

} catch (Exception $e) {
    echo "<span class='badge bg-danger'>❌ Database error: " . $e->getMessage() . "</span>";
}

echo "        </div>
      </div>";

echo "<div class='mt-3'>
        <a href='index.php' class='btn btn-primary'>← Back to Transport Dashboard</a>
        <a href='init_notifications.php' class='btn btn-secondary'>Initialize Notifications</a>
      </div>";

echo "</div></body></html>";
?>