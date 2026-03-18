<?php
require_once '../../config/config.php';

try {
    $db = new Database();

    echo "<h3>Finding Job/Skill Permissions</h3>";

    // Look for job-related permissions in role_permissions
    echo "<h4>Job-related permissions in role_permissions:</h4>";
    $jobPermissions = $db->fetchAll("SELECT * FROM role_permissions WHERE module = 'jobs' OR permission LIKE '%job%' OR permission LIKE '%skill%'");

    if (!empty($jobPermissions)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th style='padding: 8px;'>ID</th>";
        echo "<th style='padding: 8px;'>Role</th>";
        echo "<th style='padding: 8px;'>Module</th>";
        echo "<th style='padding: 8px;'>Permission</th>";
        echo "<th style='padding: 8px;'>Granted</th>";
        echo "</tr>";

        foreach ($jobPermissions as $perm) {
            echo "<tr>";
            echo "<td style='padding: 5px;'>" . $perm['id'] . "</td>";
            echo "<td style='padding: 5px;'>" . htmlspecialchars($perm['role']) . "</td>";
            echo "<td style='padding: 5px;'>" . htmlspecialchars($perm['module']) . "</td>";
            echo "<td style='padding: 5px;'>" . htmlspecialchars($perm['permission']) . "</td>";
            echo "<td style='padding: 5px;'>" . ($perm['granted'] ? 'Yes' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        // Generate SQL to update these to skill matrix
        echo "<br><h4>SQL to update to Skill Matrix:</h4>";
        echo "<textarea style='width: 100%; height: 200px;'>";

        // Update module name from 'jobs' to 'skills'
        echo "-- Update module name\n";
        echo "UPDATE role_permissions SET module = 'skills' WHERE module = 'jobs';\n\n";

        // Update permission names
        echo "-- Update permission names\n";
        foreach ($jobPermissions as $perm) {
            $newPermission = str_replace(['jobs.', 'job_'], ['skills.', 'skill_'], $perm['permission']);
            if ($newPermission != $perm['permission']) {
                echo "UPDATE role_permissions SET permission = '" . $newPermission . "' WHERE id = " . $perm['id'] . ";\n";
            }
        }

        echo "</textarea>";

    } else {
        echo "No job-related permissions found in role_permissions table.<br>";
    }

    // Look in permission_assignments
    echo "<br><h4>Job-related permission assignments:</h4>";
    $assignments = $db->fetchAll("SELECT * FROM permission_assignments WHERE permission_key LIKE '%job%' OR permission_key LIKE '%skill%'");

    if (!empty($assignments)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th style='padding: 8px;'>ID</th>";
        echo "<th style='padding: 8px;'>Account Type ID</th>";
        echo "<th style='padding: 8px;'>Permission Key</th>";
        echo "<th style='padding: 8px;'>Granted</th>";
        echo "</tr>";

        foreach ($assignments as $assign) {
            echo "<tr>";
            echo "<td style='padding: 5px;'>" . $assign['id'] . "</td>";
            echo "<td style='padding: 5px;'>" . $assign['account_type_id'] . "</td>";
            echo "<td style='padding: 5px;'>" . htmlspecialchars($assign['permission_key']) . "</td>";
            echo "<td style='padding: 5px;'>" . ($assign['is_granted'] ? 'Yes' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No job-related permission assignments found.<br>";
    }

    // Show what modules exist
    echo "<br><h4>All modules in role_permissions:</h4>";
    $modules = $db->fetchAll("SELECT DISTINCT module FROM role_permissions ORDER BY module");
    foreach ($modules as $module) {
        echo "- " . htmlspecialchars($module['module']) . "<br>";
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