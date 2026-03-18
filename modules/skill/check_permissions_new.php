<?php
require_once '../../config/config.php';

$db = new Database();

echo "<h2>Skills Permissions Check</h2>";

try {
    // Check what skills permissions exist
    echo "<h3>Current Skills Permissions in Database:</h3>";
    $skills_permissions = $db->fetchAll("SELECT DISTINCT module, permission, role FROM role_permissions WHERE module = 'skills' ORDER BY role, permission");

    if (!empty($skills_permissions)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Role</th><th>Module</th><th>Permission</th></tr>";
        foreach ($skills_permissions as $perm) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($perm['role']) . "</td>";
            echo "<td>" . htmlspecialchars($perm['module']) . "</td>";
            echo "<td>" . htmlspecialchars($perm['permission']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>No skills permissions found in database. Need to add them!</p>";
    }

    echo "<h3>All Modules with Permissions:</h3>";
    $all_permissions = $db->fetchAll("SELECT module, COUNT(*) as count FROM role_permissions GROUP BY module ORDER BY module");

    if (!empty($all_permissions)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Module</th><th>Permission Count</th></tr>";
        foreach ($all_permissions as $perm) {
            $highlight = ($perm['module'] == 'skills') ? 'background: yellow;' : '';
            echo "<tr style='$highlight'>";
            echo "<td>" . htmlspecialchars($perm['module']) . "</td>";
            echo "<td>" . $perm['count'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<h3>Action Needed:</h3>";
echo "<p>The permissions system needs to be updated to include the skills module. Here's what needs to be fixed:</p>";
echo "<ol>";
echo "<li>Add skills module to the permissions management system</li>";
echo "<li>Create proper skills permissions in the database</li>";
echo "<li>Update the permissions URLs to match actual skill module paths</li>";
echo "</ol>";

echo "<p><a href='../admin/permissions_management.php?role=manager'>Back to Permissions Management</a></p>";
?>