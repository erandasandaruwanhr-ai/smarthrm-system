<?php
require_once '../../config/config.php';

try {
    $db = new Database();

    echo "<h3>Current Skill Matrix / Jobs Permissions</h3>";

    // Check existing permissions
    echo "<h4>Existing Permissions:</h4>";
    $permissions = $db->fetchAll("SELECT * FROM role_permissions WHERE permission_name LIKE '%job%' OR permission_name LIKE '%skill%' ORDER BY permission_name");

    if (!empty($permissions)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th style='padding: 8px;'>ID</th>";
        echo "<th style='padding: 8px;'>Permission Name</th>";
        echo "<th style='padding: 8px;'>Description</th>";
        echo "<th style='padding: 8px;'>Module</th>";
        echo "</tr>";

        foreach ($permissions as $perm) {
            echo "<tr>";
            echo "<td style='padding: 5px;'>" . $perm['id'] . "</td>";
            echo "<td style='padding: 5px;'>" . htmlspecialchars($perm['permission_name']) . "</td>";
            echo "<td style='padding: 5px;'>" . htmlspecialchars($perm['description']) . "</td>";
            echo "<td style='padding: 5px;'>" . htmlspecialchars($perm['module_name'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No job/skill permissions found.<br>";
    }

    // Check all permissions to see pattern
    echo "<br><h4>All Permissions (first 20):</h4>";
    $allPermissions = $db->fetchAll("SELECT * FROM role_permissions ORDER BY permission_name LIMIT 20");

    if (!empty($allPermissions)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th style='padding: 8px;'>Permission Name</th>";
        echo "<th style='padding: 8px;'>Description</th>";
        echo "</tr>";

        foreach ($allPermissions as $perm) {
            echo "<tr>";
            echo "<td style='padding: 5px;'>" . htmlspecialchars($perm['permission_name']) . "</td>";
            echo "<td style='padding: 5px;'>" . htmlspecialchars($perm['description']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // Check permission assignments
    echo "<br><h4>Permission Assignments for Managers:</h4>";
    $assignments = $db->fetchAll("SELECT rp.permission_name, rp.description, pa.is_granted
                                 FROM permission_assignments pa
                                 JOIN role_permissions rp ON pa.permission_id = rp.id
                                 WHERE pa.account_type = 'manager'
                                 AND (rp.permission_name LIKE '%job%' OR rp.permission_name LIKE '%skill%')
                                 ORDER BY rp.permission_name");

    if (!empty($assignments)) {
        foreach ($assignments as $assign) {
            $status = $assign['is_granted'] ? '✓ Granted' : '✗ Denied';
            echo "- " . htmlspecialchars($assign['permission_name']) . ": $status<br>";
        }
    } else {
        echo "No job/skill permission assignments found for managers.<br>";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

<style>
body { font-family: Arial, sans-serif; padding: 20px; }
table { margin: 10px 0; }
th { background-color: #f0f0f0; }
</style>