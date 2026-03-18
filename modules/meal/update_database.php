<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

echo "<h2>Updating Meal Module Database Schema</h2>";

$db = new Database();

try {
    echo "<h3>Checking and updating meal_requests_visitor table...</h3>";

    // Check current table structure
    $columns = $db->fetchAll("SHOW COLUMNS FROM meal_requests_visitor");
    $existing_columns = array_column($columns, 'Field');

    echo "<p><strong>Existing columns:</strong> " . implode(', ', $existing_columns) . "</p>";

    $columns_to_add = [
        'visitor_names' => "ALTER TABLE meal_requests_visitor ADD COLUMN visitor_names TEXT AFTER requesting_emp_location",
        'visit_purpose' => "ALTER TABLE meal_requests_visitor ADD COLUMN visit_purpose VARCHAR(100) AFTER visitor_names",
        'visitor_remarks' => "ALTER TABLE meal_requests_visitor ADD COLUMN visitor_remarks TEXT AFTER visit_purpose"
    ];

    foreach ($columns_to_add as $column_name => $sql) {
        if (!in_array($column_name, $existing_columns)) {
            try {
                $db->query($sql);
                echo "<p style='color: green;'>✓ Added column: $column_name</p>";
            } catch (Exception $e) {
                echo "<p style='color: red;'>✗ Error adding $column_name: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p style='color: blue;'>- Column already exists: $column_name</p>";
        }
    }

    // Show updated table structure
    echo "<h3>Updated table structure:</h3>";
    $updated_columns = $db->fetchAll("SHOW COLUMNS FROM meal_requests_visitor");
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($updated_columns as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($col['Type'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($col['Null'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($col['Key'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($col['Extra'] ?? '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<h2 style='color: green;'>Database update completed successfully!</h2>";
    echo "<p><a href='visitor_meal_request.php'>Test Visitor Meal Request Form</a></p>";
    echo "<p><a href='index.php'>Back to Meal Management</a></p>";

} catch (Exception $e) {
    echo "<h2 style='color: red;'>Database update failed!</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>