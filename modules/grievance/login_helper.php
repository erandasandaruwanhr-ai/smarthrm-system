<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

// SECURITY: Only allow superadmin to use this testing tool
if (!isSuperAdmin()) {
    echo "<!DOCTYPE html><html><head><title>Access Denied</title></head><body>";
    echo "<h1>🔒 Access Denied</h1>";
    echo "<p>This testing tool is only available to superadmin users.</p>";
    echo "<p><a href='../../dashboard.php'>← Back to Dashboard</a></p>";
    echo "</body></html>";
    exit();
}

echo "<style>body{font-family: Arial; padding: 20px;} .user-card{background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px; border: 1px solid #ddd;} .btn{padding: 8px 16px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 5px;}</style>";
echo "<h1>🔑 Quick Login Helper for Testing</h1>\n";

try {
    $db = new Database();

    // Get test users for our scenario
    $test_users = [
        '170' => 'Employee 170',
        '494' => 'Supervisor 494',
        '91' => 'Manager 91'
    ];

    echo "<p>Click a user below to simulate login (for testing purposes):</p>\n";

    foreach ($test_users as $epf => $description) {
        $employee = $db->fetch("SELECT * FROM employees WHERE epf_number = ?", [$epf]);

        if ($employee) {
            echo "<div class='user-card'>\n";
            echo "<h3>$description</h3>\n";
            echo "<p><strong>EPF:</strong> {$employee['epf_number']}<br>\n";
            echo "<strong>Name:</strong> {$employee['name']}<br>\n";
            echo "<strong>Department:</strong> {$employee['department']}<br>\n";
            echo "<strong>Account Type:</strong> {$employee['account_type']}<br>\n";
            echo "<strong>Reports To:</strong> {$employee['reports_to']}</p>\n";

            echo "<form method='POST' style='display: inline;'>\n";
            echo "<input type='hidden' name='test_login' value='{$employee['epf_number']}'>\n";
            echo "<button type='submit' class='btn'>Test Login as {$employee['name']}</button>\n";
            echo "</form>\n";
            echo "</div>\n";
        } else {
            echo "<div class='user-card' style='background: #f8d7da;'>\n";
            echo "<h3>$description - NOT FOUND</h3>\n";
            echo "<p>Employee with EPF $epf not found in database</p>\n";
            echo "</div>\n";
        }
    }

    // Handle test login
    if (isset($_POST['test_login'])) {
        $test_epf = $_POST['test_login'];
        $employee = $db->fetch("SELECT * FROM employees WHERE epf_number = ?", [$test_epf]);

        if ($employee) {
            // Start session and set session variables
            session_start();

            $_SESSION['user_id'] = $employee['id'];
            $_SESSION['epf_number'] = $employee['epf_number'];
            $_SESSION['name'] = $employee['name'];
            $_SESSION['account_type'] = $employee['account_type'];
            $_SESSION['account_type_id'] = $employee['account_type_id'];
            $_SESSION['account_level'] = [
                'user' => 1,
                'supervisor' => 2,
                'manager' => 3,
                'admin' => 4,
                'superadmin' => 5
            ][$employee['account_type']] ?? 1;
            $_SESSION['location_id'] = $employee['location_id'] ?? null;
            $_SESSION['location_name'] = $employee['location'] ?? $employee['location_name'] ?? null;
            $_SESSION['employment_level'] = $employee['employment_level'] ?? null;
            $_SESSION['department'] = $employee['department'];
            $_SESSION['reports_to'] = $employee['reports_to'];
            $_SESSION['last_activity'] = time();

            echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
            echo "<h3>✅ Test Login Successful!</h3>\n";
            echo "<p>You are now logged in as <strong>{$employee['name']}</strong> (EPF: {$employee['epf_number']})</p>\n";
            echo "<p><a href='grievance_list.php' class='btn'>Go to Grievance List</a> ";
            echo "<a href='session_debug.php' class='btn'>Check Session</a></p>\n";
            echo "</div>\n";
        }
    }

} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>\n";
    echo "<h3>❌ Database Error</h3>\n";
    echo "<p>" . $e->getMessage() . "</p>\n";
    echo "</div>\n";
}

echo "<div style='margin-top: 30px; padding: 15px; background: #fff3cd; border-radius: 5px;'>\n";
echo "<h3>⚠️ Important Notes:</h3>\n";
echo "<ul>\n";
echo "<li>This is for testing purposes only</li>\n";
echo "<li>After testing, use the proper login system</li>\n";
echo "<li>Make sure you have test cases for Employee 170</li>\n";
echo "</ul>\n";
echo "</div>\n";
?>

<div style="margin-top: 30px;">
    <h3>🔗 Quick Navigation</h3>
    <a href="grievance_list.php" class="btn">Grievance List</a>
    <a href="complete_debug.php" class="btn">Complete Debug</a>
    <a href="session_debug.php" class="btn">Session Debug</a>
    <a href="../../auth/logout.php" class="btn">Proper Logout</a>
</div>