<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

// Only allow superadmin to run this
if (!isSuperAdmin()) {
    die("Access denied. Only superadmin can run database setup.");
}

$db = new Database();

echo "<h2>Setting up Goal Setting Module Database Tables...</h2>";

// Ensure we're in the correct directory
$current_dir = __DIR__;
echo "<p>Current directory: $current_dir</p>";

try {
    // Read SQL file
    $sql_file = $current_dir . '/database_setup.sql';
    echo "<p>Looking for SQL file at: $sql_file</p>";

    if (!file_exists($sql_file)) {
        throw new Exception("database_setup.sql file not found at: $sql_file");
    }

    $sql = file_get_contents($sql_file);

    if (!$sql) {
        throw new Exception("Could not read database_setup.sql file");
    }

    echo "<p>SQL file loaded successfully. Size: " . strlen($sql) . " bytes</p>";

    // Split SQL into individual statements
    $statements = explode(';', $sql);

    foreach ($statements as $statement) {
        $statement = trim($statement);

        // Skip empty statements and comments
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }

        echo "<p>Executing: " . htmlspecialchars(substr($statement, 0, 50)) . "...</p>";

        try {
            $db->query($statement);
            echo "<p style='color:green;'>✓ Success</p>";
        } catch (Exception $e) {
            echo "<p style='color:orange;'>⚠ Warning: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }

    echo "<h3 style='color:green;'>✅ Database setup completed successfully!</h3>";
    echo "<p>Goal Setting Module is now ready to use.</p>";

} catch (Exception $e) {
    echo "<h3 style='color:red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</h3>";
}
?>