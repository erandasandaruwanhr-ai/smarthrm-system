<?php
// Initialize transport notification system
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();

// Only admin and superadmin can initialize
if (!in_array($user['account_type'], ['admin', 'superadmin'])) {
    echo "Access denied. Only admin and superadmin can initialize the notification system.";
    exit;
}

$db = new Database();

try {
    require_once 'TransportNotifications.php';
    $notifications = TransportNotifications::getInstance($db);

    echo "✅ Transport notification system initialized successfully!<br>";
    echo "📊 Testing notification count for current user...<br>";

    $count = $notifications->getTransportNotificationCount($user);
    echo "🔔 Current notification count: $count<br>";

    $pending_count = $notifications->getPendingAllocationCount($user);
    echo "⏳ Pending allocation count: $pending_count<br>";

    echo "<br><a href='index.php'>← Back to Transport Dashboard</a>";

} catch (Exception $e) {
    echo "❌ Error initializing notification system: " . $e->getMessage();
}
?>