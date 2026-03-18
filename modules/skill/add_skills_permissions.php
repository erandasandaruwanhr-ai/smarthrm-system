<?php
require_once '../../config/config.php';

$db = new Database();

echo "<h2>Adding Skills Permissions to Database</h2>";

// Define the skills permissions that should exist
$skills_permissions = [
    'view_skill_matrix' => 'View Skill Matrix Dashboard',
    'complete_skill_assessment' => 'Complete Skill Assessment Forms',
    'manage_location_skills' => 'Manage Location Skills',
    'setup_skills' => 'Setup Skills & Categories',
    'assessment_form' => 'Access Assessment Forms',
    'skill_reports' => 'View Skill Reports'
];

// Define which roles should have which permissions by default
$role_permissions = [
    'user' => ['view_skill_matrix', 'complete_skill_assessment'],
    'supervisor' => ['view_skill_matrix', 'complete_skill_assessment', 'skill_reports'],
    'manager' => ['view_skill_matrix', 'complete_skill_assessment', 'manage_location_skills', 'skill_reports'],
    'admin' => ['view_skill_matrix', 'complete_skill_assessment', 'manage_location_skills', 'setup_skills', 'assessment_form', 'skill_reports'],
    'superadmin' => [] // SuperAdmin gets all permissions automatically
];

try {
    // First, remove any existing skills permissions
    echo "<p>Removing existing skills permissions...</p>";
    $db->query("DELETE FROM role_permissions WHERE module = 'skills'");

    $total_added = 0;

    // Add permissions for each role
    foreach ($role_permissions as $role => $permissions) {
        echo "<h3>Adding permissions for role: $role</h3>";

        foreach ($permissions as $permission) {
            if (isset($skills_permissions[$permission])) {
                try {
                    $db->query(
                        "INSERT INTO role_permissions (role, module, permission, granted) VALUES (?, ?, ?, 1)",
                        [$role, 'skills', $permission]
                    );
                    echo "✅ Added: skills.$permission for $role<br>";
                    $total_added++;
                } catch (Exception $e) {
                    echo "❌ Error adding skills.$permission for $role: " . $e->getMessage() . "<br>";
                }
            }
        }
        echo "<br>";
    }

    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<strong>✅ Success!</strong> Added $total_added skills permissions to the database.";
    echo "</div>";

    // Show what was added
    echo "<h3>Current Skills Permissions in Database:</h3>";
    $current_permissions = $db->fetchAll("SELECT role, permission FROM role_permissions WHERE module = 'skills' ORDER BY role, permission");

    if (!empty($current_permissions)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Role</th><th>Permission</th><th>Description</th></tr>";
        foreach ($current_permissions as $perm) {
            $description = $skills_permissions[$perm['permission']] ?? 'Unknown permission';
            echo "<tr>";
            echo "<td>" . htmlspecialchars($perm['role']) . "</td>";
            echo "<td>skills." . htmlspecialchars($perm['permission']) . "</td>";
            echo "<td>" . htmlspecialchars($description) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

} catch (Exception $e) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<strong>❌ Error:</strong> " . $e->getMessage();
    echo "</div>";
}

echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>✅ Skills permissions added to database</li>";
echo "<li><a href='../admin/permissions_management.php?role=manager'>Check Permissions Management</a> - Verify skills module appears</li>";
echo "<li>Update skill module URLs to match permission names</li>";
echo "</ol>";

echo "<p><a href='../admin/permissions_management.php?role=manager'>Go to Permissions Management</a></p>";
?>