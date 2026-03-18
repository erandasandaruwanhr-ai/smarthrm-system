<?php
session_start();
require_once '../../config/config.php';

echo "<h3>Simple Access Test</h3>";

echo "Session user_epf: " . ($_SESSION['user_epf'] ?? 'Not set') . "<br>";
echo "Session account_type: " . ($_SESSION['account_type'] ?? 'Not set') . "<br>";

try {
    $db = new Database();

    // Get user's account type from session
    $accountType = $_SESSION['account_type'] ?? null;

    if ($accountType) {
        echo "<br><h4>Permissions for: $accountType</h4>";

        $permissions = $db->fetchAll("SELECT permission, granted FROM role_permissions WHERE role = ? AND module = 'skills'", [$accountType]);

        foreach ($permissions as $perm) {
            echo "- " . htmlspecialchars($perm['permission']) . ": " . ($perm['granted'] ? 'GRANTED' : 'DENIED') . "<br>";
        }

        // Check if user should have access
        $hasViewPermission = false;
        foreach ($permissions as $perm) {
            if ($perm['permission'] == 'skills.view_skill_matrix' && $perm['granted']) {
                $hasViewPermission = true;
                break;
            }
        }

        echo "<br>Should have access to Skills module: " . ($hasViewPermission ? 'YES' : 'NO') . "<br>";

    } else {
        echo "No account type in session<br>";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

echo "<br><br><a href='../../dashboard.php'>Back to Dashboard</a>";
?>

<style>body { font-family: Arial, sans-serif; padding: 20px; }</style>