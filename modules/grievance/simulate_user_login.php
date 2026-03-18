<?php
// SECURITY: This file is disabled for security reasons
// Simulate user login for testing
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

if (!isset($_GET['epf'])) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>🔄 Simulate User Login</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .form { padding: 20px; border: 1px solid #ccc; background: #f9f9f9; max-width: 400px; }
            input, select, button { padding: 8px; margin: 5px 0; width: 100%; }
        </style>
    </head>
    <body>
        <h1>🔄 Simulate User Login for Testing</h1>

        <div class="form">
            <h3>Select a team member to simulate login:</h3>
            <form method="GET">
                <label>Investigation Team Members:</label>
                <select name="epf" required>
                    <option value="">-- Select EPF --</option>
                    <option value="40">40 - D. Chanuka Krishantha (Team Leader)</option>
                    <option value="162">162 - A. Nawzi Fareed (Member 1)</option>
                    <option value="476">476 - H. M. L. Sajith Gunawardana (Member 2)</option>
                </select>
                <button type="submit">Simulate Login</button>
            </form>

            <hr>

            <h3>Or enter any EPF number:</h3>
            <form method="GET">
                <input type="text" name="epf" placeholder="Enter EPF number" required>
                <button type="submit">Simulate Login</button>
            </form>
        </div>

        <p><strong>Note:</strong> This will temporarily set the session to simulate being logged in as that user for testing purposes.</p>

    </body>
    </html>
    <?php
    exit;
}

$epf = $_GET['epf'];
$db = new Database();

try {
    // Get user details
    $user = $db->fetch("SELECT * FROM employees WHERE epf_number = ?", [$epf]);

    if (!$user) {
        echo "<p>❌ User not found with EPF: $epf</p>";
        echo "<a href='?'>← Back</a>";
        exit;
    }

    // Simulate login by setting session variables
    session_start();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['epf_number'] = $user['epf_number'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['account_type'] = $user['account_type'];
    $_SESSION['department'] = $user['department'];
    $_SESSION['location'] = $user['location'];
    $_SESSION['location_name'] = $user['location']; // Backward compatibility
    $_SESSION['is_logged_in'] = true;

    echo "<!DOCTYPE html>";
    echo "<html><head><title>Login Simulated</title><style>body{font-family:Arial,sans-serif;margin:20px;}</style></head><body>";
    echo "<h1>✅ Login Simulated Successfully</h1>";
    echo "<p><strong>Now logged in as:</strong></p>";
    echo "<ul>";
    echo "<li><strong>Name:</strong> " . htmlspecialchars($user['name']) . "</li>";
    echo "<li><strong>EPF:</strong> " . htmlspecialchars($user['epf_number']) . "</li>";
    echo "<li><strong>Account Type:</strong> " . htmlspecialchars($user['account_type']) . "</li>";
    echo "<li><strong>Department:</strong> " . htmlspecialchars($user['department']) . "</li>";
    echo "<li><strong>Location:</strong> " . htmlspecialchars($user['location']) . "</li>";
    echo "</ul>";

    echo "<h2>🚀 Test Links</h2>";
    echo "<ul>";
    echo "<li><a href='index.php' target='_blank'>→ Grievance Module (should show investigation cases)</a></li>";
    echo "<li><a href='grievance_list.php' target='_blank'>→ Grievance List (should show assigned cases)</a></li>";
    echo "<li><a href='case_management.php?id=1&type=anonymous' target='_blank'>→ Direct Case Access</a></li>";
    echo "<li><a href='db_debug.php' target='_blank'>→ Debug Page</a></li>";
    echo "</ul>";

    echo "<hr>";
    echo "<p><a href='?'>← Simulate Different User</a></p>";
    echo "<p><a href='../../auth/logout.php'>← Logout</a></p>";
    echo "</body></html>";

} catch (Exception $e) {
    echo "❌ Error: " . htmlspecialchars($e->getMessage());
}
?>