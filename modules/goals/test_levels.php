<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

if (!isSuperAdmin()) {
    die("Access denied.");
}

$db = new Database();

echo "<h2>Employment Levels Check</h2>";

try {
    // Check employment levels table
    $levels = $db->fetchAll("SELECT * FROM employment_levels");
    echo "<h3>Employment Levels Table:</h3>";
    foreach ($levels as $level) {
        echo "<p>ID: " . $level['id'] . " | Name: " . $level['level_name'] . "</p>";
    }

    // Test query with join
    echo "<h3>Test Executive Query:</h3>";
    $executives = $db->fetchAll("
        SELECT
            e.epf_number,
            e.name,
            e.employment_level_id,
            el.level_name
        FROM employees e
        LEFT JOIN employment_levels el ON e.employment_level_id = el.id
        WHERE e.is_active = 1
        AND e.employment_level_id IN (3, 4, 5, 6, 7)
        LIMIT 5
    ");

    foreach ($executives as $exec) {
        echo "<p>EPF: " . $exec['epf_number'] . " | Name: " . $exec['name'] . " | Level ID: " . $exec['employment_level_id'] . " | Level: " . ($exec['level_name'] ?? 'NULL') . "</p>";
    }

} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}
?>