<?php
require_once '../../config/config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>Transport Module Diagnosis</h3>";

try {
    $db = new Database();

    // Show all tables in the database
    echo "<h4>All Tables in Database:</h4>";
    $all_tables = $db->fetchAll("SHOW TABLES");
    foreach ($all_tables as $table) {
        $table_name = array_values($table)[0];
        echo "<p>📋 Table: <strong>$table_name</strong></p>";

        // Show columns for each table
        try {
            $columns = $db->fetchAll("SHOW COLUMNS FROM `$table_name`");
            echo "<ul>";
            foreach ($columns as $col) {
                echo "<li>{$col['Field']} ({$col['Type']})</li>";
            }
            echo "</ul>";
        } catch (Exception $e) {
            echo "<p style='color:red;'>Error showing columns: " . $e->getMessage() . "</p>";
        }
        echo "<hr>";
    }

    echo "<h4>Testing Each Query Individually:</h4>";

    // Test each problematic query one by one
    $queries = [
        "Basic vehicle count" => "SELECT COUNT(*) as count FROM vehicles",
        "Vehicle status query" => "SELECT COUNT(*) as count FROM vehicles WHERE status = 'available'",
        "Driver status query" => "SELECT COUNT(*) as count FROM drivers WHERE status = 'active'",
        "Request status query" => "SELECT COUNT(*) as count FROM transport_requests WHERE status = 'pending'",
        "Show vehicles table" => "SHOW COLUMNS FROM vehicles",
        "Show drivers table" => "SHOW COLUMNS FROM drivers",
        "Show requests table" => "SHOW COLUMNS FROM transport_requests"
    ];

    foreach ($queries as $desc => $query) {
        echo "<div style='background:#f8f9fa; padding:10px; margin:10px 0; border-left:4px solid #007bff;'>";
        echo "<strong>$desc:</strong><br>";
        echo "<code>$query</code><br>";

        try {
            $result = $db->fetchAll($query);
            echo "<span style='color:green;'>✅ SUCCESS</span><br>";
            if (isset($result[0]['count'])) {
                echo "Count: " . $result[0]['count'];
            } else {
                echo "Rows returned: " . count($result);
            }
        } catch (Exception $e) {
            echo "<span style='color:red;'>❌ ERROR: " . htmlspecialchars($e->getMessage()) . "</span>";
        }
        echo "</div>";
    }

    echo "<h4>Check for Table Conflicts:</h4>";

    // Check if there are multiple tables with similar names
    $transport_tables = $db->fetchAll("SHOW TABLES LIKE '%vehicle%'");
    echo "<p>Tables containing 'vehicle': " . count($transport_tables) . "</p>";

    $driver_tables = $db->fetchAll("SHOW TABLES LIKE '%driver%'");
    echo "<p>Tables containing 'driver': " . count($driver_tables) . "</p>";

    $request_tables = $db->fetchAll("SHOW TABLES LIKE '%request%'");
    echo "<p>Tables containing 'request': " . count($request_tables) . "</p>";

} catch (Exception $e) {
    echo "<div style='background:#f8d7da; color:#721c24; padding:15px; border-radius:5px;'>";
    echo "<strong>Database Connection Error:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
code { background: #e9ecef; padding: 2px 4px; border-radius: 3px; }
</style>