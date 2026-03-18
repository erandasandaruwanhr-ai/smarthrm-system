<?php
require_once '../../config/config.php';

try {
    $db = new Database();

    echo "<h3>Checking Database Schema</h3>";

    // Check employees table columns
    echo "<h4>Employees Table Columns:</h4>";
    $result = $db->query("DESCRIBE employees");
    $columns = $result->fetchAll();
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")<br>";
    }

    // Check locations table columns
    echo "<br><h4>Locations Table Columns:</h4>";
    try {
        $result = $db->query("DESCRIBE locations");
        $columns = $result->fetchAll();
        foreach ($columns as $column) {
            echo "- " . $column['Field'] . " (" . $column['Type'] . ")<br>";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "<br>";
    }

    // Sample data from locations
    echo "<br><h4>Sample Locations Data:</h4>";
    try {
        $locations = $db->fetchAll("SELECT * FROM locations LIMIT 5");
        if (!empty($locations)) {
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr>";
            foreach (array_keys($locations[0]) as $header) {
                echo "<th style='padding: 5px;'>" . htmlspecialchars($header) . "</th>";
            }
            echo "</tr>";
            foreach ($locations as $location) {
                echo "<tr>";
                foreach ($location as $value) {
                    echo "<td style='padding: 5px;'>" . htmlspecialchars($value) . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "No location data found.";
        }
    } catch (Exception $e) {
        echo "Error loading locations: " . $e->getMessage() . "<br>";
    }

    // Check employees with location relationship
    echo "<br><h4>Sample Employee-Location Join:</h4>";
    try {
        $result = $db->fetchAll("SELECT e.epf_number, e.name as employee_name, e.location_id, l.*
                                 FROM employees e
                                 LEFT JOIN locations l ON e.location_id = l.id
                                 LIMIT 3");
        if (!empty($result)) {
            echo "<pre>" . print_r($result, true) . "</pre>";
        } else {
            echo "No employee data found.";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "<br>";
    }

} catch (Exception $e) {
    echo "Database error: " . $e->getMessage();
}
?>

<style>
body { font-family: Arial, sans-serif; padding: 20px; }
table { margin: 10px 0; }
</style>