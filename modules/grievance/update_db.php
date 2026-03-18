<?php
require_once '../../config/config.php';

echo "<h2>Grievance Module Database Update</h2>";

try {
    $db = new Database();

    echo "<h3>Updating database status enum...</h3>";
    echo "<pre>";

    // Read the update SQL file
    $sql_content = file_get_contents('update_status_complete.sql');

    if (!$sql_content) {
        throw new Exception('Could not read update_status_complete.sql file');
    }

    // Split SQL statements
    $statements = array_filter(array_map('trim', explode(';', $sql_content)));

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
    echo "Update completed!\n";
    echo "✅ Successful statements: $success_count\n";
    echo "❌ Failed statements: $error_count\n";

    // Verify status enum
    echo "\n<h3>Verifying status enum...</h3>";
    try {
        $result = $db->fetch("SHOW COLUMNS FROM grievances LIKE 'status'");
        if ($result) {
            echo "✅ Status column type: " . $result['Type'] . "\n";

            // Check if all required statuses are available
            $required_statuses = ['Open', 'Under Supervisory Review', 'Under Managerial Review', 'In Progress', 'Resolved', 'Closed'];
            $enum_values = $result['Type'];

            $all_present = true;
            foreach ($required_statuses as $status) {
                if (strpos($enum_values, $status) === false) {
                    echo "❌ Missing status: $status\n";
                    $all_present = false;
                } else {
                    echo "✅ Status available: $status\n";
                }
            }

            if ($all_present) {
                echo "\n✅ All required status values are available!\n";
            }
        } else {
            echo "❌ Could not verify status column\n";
        }
    } catch (Exception $e) {
        echo "❌ Error verifying status: " . $e->getMessage() . "\n";
    }

    echo "</pre>";

    echo "<p><a href='case_management.php?id=3' class='btn btn-primary'>Test Case Management</a> ";
    echo "<a href='index.php' class='btn btn-secondary'>Back to Module</a></p>";

} catch (Exception $e) {
    echo "<div class='alert alert-danger'>";
    echo "<h3>Update Failed</h3>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
pre { background: #f8f9fa; padding: 15px; border-radius: 5px; }
.btn { padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin-right: 10px; }
.btn:hover { background: #0056b3; }
.btn-secondary { background: #6c757d; }
.btn-secondary:hover { background: #545b62; }
.alert { padding: 15px; margin: 10px 0; border-radius: 5px; }
.alert-danger { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
</style>