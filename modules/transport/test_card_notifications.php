<?php
// Simple test to verify notification count on Transport Allocation card
chdir('/mnt/c/laragon/www/pbpictures/smarthrmjiffy/modules/transport');
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$db = new Database();
$user = getCurrentUser();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Transport Card Notification Test</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css' rel='stylesheet'>
</head>
<body class='p-4'>";

echo "<h2>🔍 Transport Card Notification Test</h2>";

// Show user info
echo "<div class='alert alert-info'>";
echo "<strong>Current User:</strong><br>";
echo "EPF: " . ($user['epf_number'] ?? 'N/A') . "<br>";
echo "Name: " . ($user['name'] ?? 'N/A') . "<br>";
echo "Account Type: " . ($user['account_type'] ?? 'N/A') . "<br>";
echo "Location: " . ($user['location'] ?? $user['location_name'] ?? 'N/A') . "<br>";
echo "</div>";

// Test notification count calculation
$notification_count = 0;
try {
    require_once 'TransportNotifications.php';
    $notifications = TransportNotifications::getInstance($db);
    $notification_count = $notifications->getPendingAllocationCount($user);
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
}

echo "<div class='alert alert-success'>";
echo "<strong>Notification Count: $notification_count</strong><br>";
echo "</div>";

// Show what the card should look like
echo "<h3>Test Transport Allocation Card:</h3>";
echo "<div class='col-lg-4 col-md-6 mb-4' style='max-width: 300px;'>";
echo "<div class='card position-relative border-danger' style='min-height: 200px; cursor: pointer;'>";

// Always show notification badge for testing
echo "<span class='position-absolute top-0 end-0 badge bg-danger rounded-circle d-flex align-items-center justify-content-center'
          style='width: 30px; height: 30px; font-size: 0.75rem; margin-top: 10px; margin-right: 10px; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.2);'>";
echo "<i class='fas fa-bell' style='margin-right: 2px;'></i>" . max(1, $notification_count); // Show at least 1 for testing
echo "</span>";

echo "<div class='card-body text-center'>";
echo "<div class='mb-3'>";
echo "<i class='fas fa-tasks' style='font-size: 2rem; color: #dc3545;'></i>";
echo "</div>";
echo "<h5 class='card-title'>Transport Allocation</h5>";
echo "<p class='card-text'>Assign vehicles and drivers to requests</p>";
echo "</div>";
echo "</div>";
echo "</div>";

// Show current pending requests
echo "<h3>Current Pending Requests:</h3>";
try {
    $pending_requests = $db->fetchAll("
        SELECT tr.id, tr.emp_number, tr.emp_name, tr.emp_location, tr.status
        FROM transport_requests tr
        WHERE tr.status = 'pending'
        ORDER BY tr.id
    ");

    if (empty($pending_requests)) {
        echo "<div class='alert alert-warning'>No pending requests found.</div>";
    } else {
        echo "<div class='table-responsive'>";
        echo "<table class='table table-bordered'>";
        echo "<thead class='table-dark'>";
        echo "<tr><th>ID</th><th>EPF</th><th>Name</th><th>Location</th><th>Status</th></tr>";
        echo "</thead><tbody>";
        foreach ($pending_requests as $req) {
            echo "<tr>";
            echo "<td>{$req['id']}</td>";
            echo "<td>{$req['emp_number']}</td>";
            echo "<td>{$req['emp_name']}</td>";
            echo "<td>{$req['emp_location']}</td>";
            echo "<td><span class='badge bg-warning'>{$req['status']}</span></td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Database error: " . $e->getMessage() . "</div>";
}

echo "<div class='mt-3'>";
echo "<a href='index.php' class='btn btn-primary'>← Back to Transport Dashboard</a>";
echo "</div>";

echo "</body></html>";
?>