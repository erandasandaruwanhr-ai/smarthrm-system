<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

if (!isSuperAdmin()) {
    die("Access denied.");
}

$db = new Database();

echo "<h2>Database Column Check</h2>";

try {
    // Check employees table columns
    echo "<h3>Employees Table Columns:</h3>";
    $employee_columns = $db->fetchAll("DESCRIBE employees");
    foreach ($employee_columns as $col) {
        echo "<p>✓ " . $col['Field'] . " (" . $col['Type'] . ")</p>";
    }

    echo "<h3>Goal Setting Periods Table Columns:</h3>";
    $period_columns = $db->fetchAll("DESCRIBE goal_setting_periods");
    foreach ($period_columns as $col) {
        echo "<p>✓ " . $col['Field'] . " (" . $col['Type'] . ")</p>";
    }

    // Test the specific query that's failing
    echo "<h3>Testing Queries:</h3>";

    echo "<p><strong>Testing employees query...</strong></p>";
    try {
        $test1 = $db->fetch("SELECT COUNT(*) as count FROM employees WHERE status = 'Active'");
        echo "<p style='color:green;'>✓ Employees status query works: " . $test1['count'] . " active employees</p>";
    } catch (Exception $e) {
        echo "<p style='color:red;'>✗ Employees status query failed: " . $e->getMessage() . "</p>";
    }

    echo "<p><strong>Testing goal_setting_periods query...</strong></p>";
    try {
        $test2 = $db->fetch("SELECT COUNT(*) as count FROM goal_setting_periods WHERE status = 'active'");
        echo "<p style='color:green;'>✓ Goal periods status query works: " . $test2['count'] . " active periods</p>";
    } catch (Exception $e) {
        echo "<p style='color:red;'>✗ Goal periods status query failed: " . $e->getMessage() . "</p>";
    }

} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}
?>