<?php
session_start();
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

try {
    $db = new Database();
    $user = getCurrentUser();

    echo "<h3>Sidebar Debug for User: " . htmlspecialchars($user['name']) . "</h3>";
    echo "EPF: " . htmlspecialchars($user['epf_number']) . "<br>";
    echo "Account Type: " . htmlspecialchars($user['account_type']) . "<br><br>";

    echo "<h4>Module 15 Permission Check:</h4>";

    // Test each permission individually
    $permissions = [
        'skills.view_skill_matrix',
        'skills.complete_skill_assessment',
        'skills.manage_location_skills'
    ];

    foreach ($permissions as $perm) {
        $hasPermission = hasModulePermission($db, $perm);
        echo "- $perm: " . ($hasPermission ? '<span style="color: green;">✓ GRANTED</span>' : '<span style="color: red;">✗ DENIED</span>') . "<br>";
    }

    // Check admin functions
    echo "<br><h4>Admin Check:</h4>";
    echo "- isAdmin(): " . (isAdmin() ? '<span style="color: green;">✓ TRUE</span>' : '<span style="color: red;">✗ FALSE</span>') . "<br>";
    echo "- isSuperAdmin(): " . (isSuperAdmin() ? '<span style="color: green;">✓ TRUE</span>' : '<span style="color: red;">✗ FALSE</span>') . "<br>";

    // Final access calculation
    $canAccess = hasModulePermission($db, 'skills.view_skill_matrix') ||
                hasModulePermission($db, 'skills.complete_skill_assessment') ||
                hasModulePermission($db, 'skills.manage_location_skills') ||
                isAdmin() || isSuperAdmin();

    echo "<br><h4>Final Module Access:</h4>";
    echo "Module 15 (Skill Matrix) canAccess: " . ($canAccess ? '<span style="color: green;">✓ TRUE</span>' : '<span style="color: red;">✗ FALSE</span>') . "<br>";

    // Show raw permissions from database
    echo "<br><h4>Raw Permissions from Database:</h4>";
    $rawPermissions = $db->fetchAll("SELECT * FROM role_permissions WHERE role = ? AND module = 'skills'", [$user['account_type']]);

    if (!empty($rawPermissions)) {
        foreach ($rawPermissions as $perm) {
            echo "- " . htmlspecialchars($perm['permission']) . ": " . ($perm['granted'] ? '<span style="color: green;">✓ GRANTED</span>' : '<span style="color: red;">✗ DENIED</span>') . "<br>";
        }
    } else {
        echo "No permissions found for role: " . htmlspecialchars($user['account_type']) . "<br>";
    }

    // Test the hasModulePermission function directly
    echo "<br><h4>Function Debug:</h4>";
    echo "Testing hasModulePermission function...<br>";

    if (function_exists('hasModulePermission')) {
        echo "✓ hasModulePermission function exists<br>";

        // Test with a simple permission first
        $testResult = hasModulePermission($db, 'skills.view_skill_matrix');
        echo "Test result for 'skills.view_skill_matrix': " . ($testResult ? 'TRUE' : 'FALSE') . "<br>";
    } else {
        echo "✗ hasModulePermission function not found<br>";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

<style>body { font-family: Arial, sans-serif; padding: 20px; }</style>