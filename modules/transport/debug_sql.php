<?php
// Debug the exact SQL query issue
chdir('/mnt/c/laragon/www/pbpictures/smarthrmjiffy/modules/transport');
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$db = new Database();
$user = getCurrentUser();

echo "<h2>🔍 SQL Query Debug for EPF 475</h2>";

echo "<div style='background: #e3f2fd; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
echo "<h3>User Info:</h3>";
echo "EPF: " . ($user['epf_number'] ?? 'N/A') . "<br>";
echo "Account Type: " . ($user['account_type'] ?? 'N/A') . "<br>";
echo "Location from session: '" . ($user['location'] ?? 'N/A') . "'<br>";
echo "Location_name from session: '" . ($user['location_name'] ?? 'N/A') . "'<br>";
echo "</div>";

// Test 1: Direct query matching the TransportNotifications logic
echo "<div style='background: #fff3e0; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
echo "<h3>Test 1: Direct SQL Query</h3>";

$user_location = $user['location'] ?? $user['location_name'] ?? null;
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

echo "Final user_location: '$user_location'<br>";

$sql = "
    SELECT COUNT(*) as count
    FROM transport_requests tr
    LEFT JOIN employees e ON tr.emp_number = e.epf_number
    LEFT JOIN locations l ON e.location_id = l.id
    WHERE tr.status = 'pending'
    AND l.location_name = ?
";

echo "<pre>SQL Query:\n$sql</pre>";
echo "Parameters: ['" . $user_location . "']<br>";

try {
    $result = $db->fetch($sql, [$user_location]);
    echo "Result: " . json_encode($result) . "<br>";
    echo "Count: " . ($result ? $result['count'] : 'NULL') . "<br>";
} catch (Exception $e) {
    echo "<span style='color: red;'>Error: " . $e->getMessage() . "</span><br>";
}
echo "</div>";

// Test 2: Step by step debug
echo "<div style='background: #f3e5f5; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
echo "<h3>Test 2: Step by Step Debug</h3>";

// Check pending requests
echo "<strong>All pending requests:</strong><br>";
try {
    $all_pending = $db->fetchAll("SELECT * FROM transport_requests WHERE status = 'pending'");
    foreach ($all_pending as $req) {
        echo "ID: {$req['id']}, EPF: {$req['emp_number']}, Location: {$req['emp_location']}<br>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

echo "<br><strong>Join query debug:</strong><br>";
try {
    $join_debug = $db->fetchAll("
        SELECT tr.id, tr.emp_number, tr.emp_name, tr.emp_location, tr.status,
               e.epf_number as e_epf, e.location_id,
               l.id as l_id, l.location_name
        FROM transport_requests tr
        LEFT JOIN employees e ON tr.emp_number = e.epf_number
        LEFT JOIN locations l ON e.location_id = l.id
        WHERE tr.status = 'pending'
    ");

    foreach ($join_debug as $row) {
        echo "TR_ID: {$row['id']}, EPF: {$row['emp_number']}, ";
        echo "E_EPF: " . ($row['e_epf'] ?? 'NULL') . ", ";
        echo "LOC_ID: " . ($row['location_id'] ?? 'NULL') . ", ";
        echo "LOC_NAME: '" . ($row['location_name'] ?? 'NULL') . "'<br>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}
echo "</div>";

// Test 3: Test TransportNotifications class directly
echo "<div style='background: #e8f5e8; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
echo "<h3>Test 3: TransportNotifications Class</h3>";
try {
    require_once 'TransportNotifications.php';
    $notifications = TransportNotifications::getInstance($db);
    $count = $notifications->getPendingAllocationCount($user);
    echo "TransportNotifications result: $count<br>";
} catch (Exception $e) {
    echo "<span style='color: red;'>Error: " . $e->getMessage() . "</span><br>";
}
echo "</div>";

echo "<a href='index.php' style='background: #2196F3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>← Back</a>";
?>