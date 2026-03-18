<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

// Only allow superadmin to run this
if (!isSuperAdmin()) {
    die("Access denied. Only superadmin can run database setup.");
}

$db = new Database();
echo "<h2>Goal Setting Database Debug Setup</h2>";

try {
    // First, check current database
    $current_db = $db->fetch("SELECT DATABASE() as db_name");
    echo "<p><strong>Current Database:</strong> " . $current_db['db_name'] . "</p>";

    // Check if tables already exist
    $tables = $db->fetchAll("SHOW TABLES LIKE 'goal_setting%'");
    echo "<h3>Existing Goal Setting Tables:</h3>";
    if (empty($tables)) {
        echo "<p style='color:red;'>No goal setting tables found</p>";
    } else {
        foreach ($tables as $table) {
            echo "<p>✓ " . array_values($table)[0] . "</p>";
        }
    }

    // Read and execute SQL file
    $sql_file = __DIR__ . '/database_setup.sql';
    echo "<p><strong>Reading SQL file:</strong> $sql_file</p>";

    if (!file_exists($sql_file)) {
        throw new Exception("database_setup.sql file not found");
    }

    $sql = file_get_contents($sql_file);
    echo "<p><strong>SQL file size:</strong> " . strlen($sql) . " bytes</p>";

    // Split into statements and execute each one
    $statements = explode(';', $sql);
    $successful = 0;
    $failed = 0;

    echo "<h3>Executing SQL Statements:</h3>";
    echo "<div style='max-height:400px; overflow-y:scroll; border:1px solid #ccc; padding:10px;'>";

    foreach ($statements as $i => $statement) {
        $statement = trim($statement);

        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }

        echo "<p><strong>Statement " . ($i+1) . ":</strong><br>";
        echo "<code>" . htmlspecialchars(substr($statement, 0, 100)) . "...</code></p>";

        try {
            $result = $db->query($statement);
            echo "<p style='color:green; margin-left:20px;'>✓ SUCCESS</p>";
            $successful++;
        } catch (Exception $e) {
            echo "<p style='color:red; margin-left:20px;'>✗ ERROR: " . htmlspecialchars($e->getMessage()) . "</p>";
            $failed++;
        }

        echo "<hr>";
    }

    echo "</div>";

    echo "<h3>Execution Summary:</h3>";
    echo "<p>Successful: $successful</p>";
    echo "<p>Failed: $failed</p>";

    // Check tables again after execution
    $tables_after = $db->fetchAll("SHOW TABLES LIKE 'goal_setting%'");
    echo "<h3>Goal Setting Tables After Execution:</h3>";
    if (empty($tables_after)) {
        echo "<p style='color:red;'>❌ No goal setting tables found - setup failed</p>";
    } else {
        echo "<p style='color:green;'>✅ Tables created successfully:</p>";
        foreach ($tables_after as $table) {
            $table_name = array_values($table)[0];
            echo "<p>✓ $table_name</p>";

            // Count records in each table
            $count = $db->fetch("SELECT COUNT(*) as count FROM `$table_name`");
            echo "<p style='margin-left:20px; color:blue;'>Records: " . $count['count'] . "</p>";
        }
    }

} catch (Exception $e) {
    echo "<h3 style='color:red;'>❌ Critical Error:</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
?>