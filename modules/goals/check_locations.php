<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

if (!isSuperAdmin()) {
    die("Access denied.");
}

$db = new Database();

echo "<h2>Locations Table Check</h2>";

try {
    // Check if locations table exists
    $tables = $db->fetchAll("SHOW TABLES LIKE 'locations'");
    if (empty($tables)) {
        echo "<p style='color:red;'>❌ Locations table does not exist</p>";
    } else {
        echo "<p style='color:green;'>✅ Locations table exists</p>";

        // Check locations table structure
        echo "<h3>Locations Table Columns:</h3>";
        $columns = $db->fetchAll("DESCRIBE locations");
        foreach ($columns as $col) {
            echo "<p>✓ " . $col['Field'] . " (" . $col['Type'] . ")</p>";
        }

        // Show sample data
        echo "<h3>Sample Location Data:</h3>";
        $locations = $db->fetchAll("SELECT * FROM locations LIMIT 5");
        foreach ($locations as $location) {
            echo "<p>";
            foreach ($location as $key => $value) {
                echo "$key: $value | ";
            }
            echo "</p>";
        }
    }

    // Test query without location join
    echo "<h3>Test Query Without Location:</h3>";
    $test = $db->fetchAll("
        SELECT
            e.epf_number,
            e.name,
            e.designation,
            e.department,
            e.location_id,
            e.joined_date,
            e.employment_level_id,
            el.level_name as employment_level
        FROM employees e
        LEFT JOIN employment_levels el ON e.employment_level_id = el.id
        WHERE e.is_active = 1
        AND e.employment_level_id IN (3, 4, 5, 6, 7)
        LIMIT 3
    ");

    foreach ($test as $employee) {
        echo "<p>EPF: " . $employee['epf_number'] . " | Name: " . $employee['name'] . " | Location ID: " . $employee['location_id'] . " | Level: " . $employee['employment_level'] . "</p>";
    }

} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}
?>