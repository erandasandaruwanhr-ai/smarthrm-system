<?php
require_once '../../config/config.php';

// Simple database installer for grievance module
echo "<h2>Grievance Module Database Installer</h2>";

try {
    $db = new Database();

    // Read the install.sql file
    $sql_content = file_get_contents('install.sql');

    if (!$sql_content) {
        throw new Exception('Could not read install.sql file');
    }

    // Split SQL statements
    $statements = array_filter(array_map('trim', explode(';', $sql_content)));

    echo "<h3>Installing database tables...</h3>";
    echo "<pre>";

    $success_count = 0;
    $error_count = 0;

    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }

        try {
            $db->execute($statement);
            echo "✅ Executed successfully: " . substr($statement, 0, 50) . "...\n";
            $success_count++;
        } catch (Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
            echo "   Statement: " . substr($statement, 0, 100) . "...\n";
            $error_count++;
        }
    }

    echo "\n";
    echo "Installation completed!\n";
    echo "✅ Successful statements: $success_count\n";
    echo "❌ Failed statements: $error_count\n";

    // Verify tables exist
    echo "\n<h3>Verifying tables...</h3>";
    $tables = ['grievances', 'grievance_evidence', 'grievance_notes', 'grievance_investigators', 'grievance_reports'];

    foreach ($tables as $table) {
        try {
            $exists = $db->fetch("SHOW TABLES LIKE '$table'");
            if ($exists) {
                echo "✅ Table '$table' exists\n";
            } else {
                echo "❌ Table '$table' does not exist\n";
            }
        } catch (Exception $e) {
            echo "❌ Error checking table '$table': " . $e->getMessage() . "\n";
        }
    }

    echo "</pre>";

    echo "<p><a href='index.php' class='btn btn-primary'>Go to Grievance Module</a></p>";

} catch (Exception $e) {
    echo "<div class='alert alert-danger'>";
    echo "<h3>Installation Failed</h3>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
pre { background: #f8f9fa; padding: 15px; border-radius: 5px; }
.btn { padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
.btn:hover { background: #0056b3; }
.alert { padding: 15px; margin: 10px 0; border-radius: 5px; }
.alert-danger { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
</style>