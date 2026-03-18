<?php
session_start();
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

echo "<h2>Debug User Transport Permissions</h2>";

try {
    $db = new Database();
    $user = getCurrentUser();

    echo "<h3>Current User Info</h3>";
    echo "User ID: " . ($user['id'] ?? 'Not set') . "<br>";
    echo "Name: " . ($user['name'] ?? 'Not set') . "<br>";
    echo "Role: " . ($user['role'] ?? 'Not set') . "<br>";
    echo "Account Type: " . ($user['account_type'] ?? 'Not set') . "<br>";
    echo "Account Type ID: " . ($_SESSION['account_type_id'] ?? 'Not set') . "<br>";
    echo "Account Level: " . ($_SESSION['account_level'] ?? 'Not set') . "<br>";
    echo "EPF Number: " . ($user['epf_number'] ?? 'Not set') . "<br>";

    $account_type_id = $_SESSION['account_type_id'] ?? null;

    if ($account_type_id) {
        // Check transport permissions
        echo "<h3>Transport Module Permissions</h3>";
        $transport_permissions = $db->fetchAll("
            SELECT permission_key, is_granted
            FROM permission_assignments
            WHERE account_type_id = ? AND permission_key LIKE '%transport%'
            ORDER BY permission_key
        ", [$account_type_id]);

        if ($transport_permissions) {
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>Permission Key</th><th>Granted</th><th>Test Result</th></tr>";
            foreach ($transport_permissions as $perm) {
                $test_result = hasModulePermission($db, $perm['permission_key']) ? 'PASS' : 'FAIL';
                $row_color = $test_result === 'PASS' ? 'background: #e8f5e8;' : 'background: #ffe8e8;';

                echo "<tr style='$row_color'>";
                echo "<td>{$perm['permission_key']}</td>";
                echo "<td>" . ($perm['is_granted'] ? 'Yes' : 'No') . "</td>";
                echo "<td><strong>$test_result</strong></td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "❌ <strong>NO TRANSPORT PERMISSIONS FOUND FOR ACCOUNT TYPE {$account_type_id}</strong><br>";
        }

        // Test key transport permissions
        echo "<h3>Key Transport Permission Tests</h3>";
        $key_permissions = [
            'transport.transport_allocation',
            'transport.vehicle_register',
            'transport.vehicle_pool',
            'transport.transport_request'
        ];

        $has_any_transport = false;
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Permission</th><th>Result</th></tr>";
        foreach ($key_permissions as $permission) {
            $has_permission = hasModulePermission($db, $permission);
            $result = $has_permission ? 'GRANTED ✅' : 'DENIED ❌';
            $row_color = $has_permission ? 'background: #e8f5e8;' : 'background: #ffe8e8;';

            if ($has_permission) $has_any_transport = true;

            echo "<tr style='$row_color'>";
            echo "<td>$permission</td>";
            echo "<td><strong>$result</strong></td>";
            echo "</tr>";
        }
        echo "</table>";

        echo "<h3>Transport Access Diagnosis</h3>";
        if ($has_any_transport) {
            echo "✅ <strong>User HAS transport permissions - data should be visible</strong><br>";
            echo "<p>If you're still seeing zeros, the issue might be with the auth_check.php file blocking access.</p>";
        } else {
            echo "❌ <strong>User has NO transport permissions - this is why you see all zeros!</strong><br>";
            echo "<p><strong>SOLUTION:</strong> Grant transport permissions to account type {$account_type_id}.</p>";
        }

        // Check account type details
        echo "<h3>Account Type Details</h3>";
        $account_type_info = $db->fetch("SELECT * FROM account_types WHERE id = ?", [$account_type_id]);
        if ($account_type_info) {
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>Field</th><th>Value</th></tr>";
            foreach ($account_type_info as $key => $value) {
                echo "<tr><td>{$key}</td><td>{$value}</td></tr>";
            }
            echo "</table>";
        }

        // Show SQL to fix permissions
        echo "<h3>SQL Fix (if needed)</h3>";
        echo "<pre style='background: #f0f0f0; padding: 10px;'>";
        echo "-- Grant basic transport permissions to account type {$account_type_id}\n";
        echo "INSERT IGNORE INTO permission_assignments (account_type_id, permission_key, is_granted) VALUES\n";
        echo "({$account_type_id}, 'transport.transport_allocation', 1),\n";
        echo "({$account_type_id}, 'transport.vehicle_register', 1),\n";
        echo "({$account_type_id}, 'transport.vehicle_pool', 1),\n";
        echo "({$account_type_id}, 'transport.transport_request', 1);";
        echo "</pre>";

    } else {
        echo "❌ No account_type_id found in session<br>";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "Stack trace:<br><pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<p><a href='transport_allocation.php'>← Back to Transport Allocation</a></p>";
?>