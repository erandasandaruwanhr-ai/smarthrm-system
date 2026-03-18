<?php
require_once '../../config/config.php';

echo "<h2>Quick Database Status Update</h2>";

try {
    $db = new Database();

    echo "<h3>Updating status enum...</h3>";
    echo "<pre>";

    // Update the status enum to include all required values
    $sql = "ALTER TABLE `grievances` MODIFY COLUMN `status` ENUM(
        'Open',
        'Under Supervisory Review',
        'Under Managerial Review',
        'In Progress',
        'Resolved',
        'Closed',
        'Reopened'
    ) DEFAULT 'Open'";

    try {
        $db->execute($sql);
        echo "✅ Status enum updated successfully\n";
    } catch (Exception $e) {
        echo "⚠️ Status enum update result: " . $e->getMessage() . "\n";
        echo "(This might be normal if already updated)\n";
    }

    // Verify the update
    $result = $db->fetch("SHOW COLUMNS FROM grievances LIKE 'status'");
    if ($result) {
        echo "\n✅ Current status column type:\n";
        echo $result['Type'] . "\n";

        // Check if all required statuses are present
        $required_statuses = ['Open', 'Under Supervisory Review', 'Under Managerial Review', 'In Progress', 'Resolved', 'Closed'];
        $enum_values = $result['Type'];

        echo "\n✅ Status validation:\n";
        foreach ($required_statuses as $status) {
            $present = strpos($enum_values, $status) !== false;
            echo ($present ? "✅" : "❌") . " $status\n";
        }
    }

    echo "\n✅ Database update completed!\n";
    echo "</pre>";

    echo "<p>";
    echo "<a href='debug_status.php?id=3' class='btn btn-primary'>Debug Case Status</a> ";
    echo "<a href='case_management.php?id=3' class='btn btn-info'>Test Case Management</a>";
    echo "</p>";

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
.btn { padding: 10px 20px; color: white; text-decoration: none; border-radius: 5px; margin-right: 10px; }
.btn-primary { background: #007bff; }
.btn-info { background: #17a2b8; }
.btn:hover { opacity: 0.9; }
.alert { padding: 15px; margin: 10px 0; border-radius: 5px; }
.alert-danger { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
</style>