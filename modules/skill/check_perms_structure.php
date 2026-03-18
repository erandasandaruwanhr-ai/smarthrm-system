<?php
require_once '../../config/config.php';

try {
    $db = new Database();

    echo "<h3>Permission System Structure</h3>";

    // Check role_permissions table structure
    echo "<h4>role_permissions table structure:</h4>";
    try {
        $result = $db->query("DESCRIBE role_permissions");
        $columns = $result->fetchAll();
        foreach ($columns as $column) {
            echo "- " . $column['Field'] . " (" . $column['Type'] . ")<br>";
        }
    } catch (Exception $e) {
        echo "role_permissions table error: " . $e->getMessage() . "<br>";
    }

    // Check permission_assignments table structure
    echo "<br><h4>permission_assignments table structure:</h4>";
    try {
        $result = $db->query("DESCRIBE permission_assignments");
        $columns = $result->fetchAll();
        foreach ($columns as $column) {
            echo "- " . $column['Field'] . " (" . $column['Type'] . ")<br>";
        }
    } catch (Exception $e) {
        echo "permission_assignments table error: " . $e->getMessage() . "<br>";
    }

    // Sample data from role_permissions
    echo "<br><h4>Sample role_permissions data:</h4>";
    try {
        $permissions = $db->fetchAll("SELECT * FROM role_permissions WHERE description LIKE '%job%' OR description LIKE '%skill%' OR description LIKE '%Job%' LIMIT 10");
        if (!empty($permissions)) {
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr>";
            foreach (array_keys($permissions[0]) as $header) {
                echo "<th style='padding: 5px;'>" . htmlspecialchars($header) . "</th>";
            }
            echo "</tr>";
            foreach ($permissions as $perm) {
                echo "<tr>";
                foreach ($perm as $value) {
                    echo "<td style='padding: 5px;'>" . htmlspecialchars($value) . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        } else {
            // If no job/skill permissions, show some general permissions
            $permissions = $db->fetchAll("SELECT * FROM role_permissions LIMIT 5");
            echo "No job/skill permissions found. Sample permissions:<br>";
            foreach ($permissions as $perm) {
                echo "- ID: " . $perm['id'] . ", Permission: " . htmlspecialchars($perm['permission'] ?? $perm['name'] ?? 'Unknown') . "<br>";
            }
        }
    } catch (Exception $e) {
        echo "Error loading permissions: " . $e->getMessage() . "<br>";
    }

    // Check what permissions exist
    echo "<br><h4>All permissions (first 10):</h4>";
    try {
        $allPermissions = $db->fetchAll("SELECT * FROM role_permissions LIMIT 10");
        foreach ($allPermissions as $perm) {
            echo "<pre>" . print_r($perm, true) . "</pre>";
            break; // Just show structure of first one
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }

} catch (Exception $e) {
    echo "Database error: " . $e->getMessage();
}
?>

<style>
body { font-family: Arial, sans-serif; padding: 20px; }
table { margin: 10px 0; }
th { background-color: #f0f0f0; }
</style>