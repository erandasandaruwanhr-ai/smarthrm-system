<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

echo "<h2>Cleaning Up Zero-Meal Records</h2>";

$db = new Database();

try {
    // Check for employee records with 0 total meals
    $employee_zero_query = "SELECT id, emp_name, emp_number,
        (breakfast_count + breakfast_countx + lunch_count + lunch_countx +
         dinner_count + dinner_countx + snack1_countx + snack2_count +
         snack2_countx + snack3_countx) as total_meals
        FROM meal_requests_employee
        HAVING total_meals = 0";

    $employee_zero_records = $db->fetchAll($employee_zero_query);

    echo "<h3>Employee Records with 0 Total Meals:</h3>";
    if (empty($employee_zero_records)) {
        echo "<p style='color: green;'>✓ No employee records with 0 meals found.</p>";
    } else {
        echo "<p><strong>Found " . count($employee_zero_records) . " employee records with 0 meals:</strong></p>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Employee</th><th>EPF</th><th>Total Meals</th></tr>";
        foreach ($employee_zero_records as $record) {
            echo "<tr>";
            echo "<td>" . $record['id'] . "</td>";
            echo "<td>" . htmlspecialchars($record['emp_name']) . "</td>";
            echo "<td>" . htmlspecialchars($record['emp_number']) . "</td>";
            echo "<td>" . $record['total_meals'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        // Delete these records
        $delete_employee = "DELETE FROM meal_requests_employee WHERE
            (breakfast_count + breakfast_countx + lunch_count + lunch_countx +
             dinner_count + dinner_countx + snack1_countx + snack2_count +
             snack2_countx + snack3_countx) = 0";

        $db->query($delete_employee);
        $deleted_count = count($employee_zero_records);
        echo "<p style='color: blue;'>🗑️ Deleted $deleted_count employee records with 0 meals.</p>";
    }

    // Check for visitor records with 0 total meals
    $visitor_zero_query = "SELECT id, requesting_emp_name, visitor_names,
        (breakfast_count + lunch_count + dinner_count + snack1_count + snack2_count) as total_meals
        FROM meal_requests_visitor
        HAVING total_meals = 0";

    $visitor_zero_records = $db->fetchAll($visitor_zero_query);

    echo "<h3>Visitor Records with 0 Total Meals:</h3>";
    if (empty($visitor_zero_records)) {
        echo "<p style='color: green;'>✓ No visitor records with 0 meals found.</p>";
    } else {
        echo "<p><strong>Found " . count($visitor_zero_records) . " visitor records with 0 meals:</strong></p>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Requesting Employee</th><th>Visitors</th><th>Total Meals</th></tr>";
        foreach ($visitor_zero_records as $record) {
            echo "<tr>";
            echo "<td>" . $record['id'] . "</td>";
            echo "<td>" . htmlspecialchars($record['requesting_emp_name']) . "</td>";
            echo "<td>" . htmlspecialchars($record['visitor_names'] ?? 'N/A') . "</td>";
            echo "<td>" . $record['total_meals'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        // Delete these records
        $delete_visitor = "DELETE FROM meal_requests_visitor WHERE
            (breakfast_count + lunch_count + dinner_count + snack1_count + snack2_count) = 0";

        $db->query($delete_visitor);
        $deleted_visitor_count = count($visitor_zero_records);
        echo "<p style='color: blue;'>🗑️ Deleted $deleted_visitor_count visitor records with 0 meals.</p>";
    }

    echo "<h2 style='color: green;'>✅ Cleanup completed successfully!</h2>";
    echo "<p><a href='meal_request_details.php'>View Updated Request Details</a></p>";
    echo "<p><a href='index.php'>Back to Meal Management</a></p>";

} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Cleanup failed!</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>