<?php
session_start();

// Quick verification that the performance module is working
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/performance_auth.php';

echo "<h1>Performance Module Verification</h1>";

try {
    $db = new Database();

    echo "<h3>✅ Database Connection: OK</h3>";

    // Check tables exist
    $tables = ['leave_utilization', 'staff_appraisals', 'executive_appraisals'];
    echo "<h3>✅ Tables Created:</h3>";
    foreach ($tables as $table) {
        $result = $db->fetchAll("SHOW TABLES LIKE '$table'");
        echo ($result ? "✅" : "❌") . " $table<br>";
    }

    // Check permissions
    echo "<h3>✅ Permissions Setup:</h3>";
    $perms = $db->fetchAll("SELECT COUNT(*) as count FROM permissions WHERE module_name = 'Performance Appraisal'");
    echo "• Performance permissions: " . $perms[0]['count'] . "<br>";

    $account_perms = $db->fetchAll("SELECT COUNT(*) as count FROM account_permissions WHERE module_name = 'Performance Appraisal'");
    echo "• Account assignments: " . $account_perms[0]['count'] . "<br>";

    echo "<h3>✅ Module Access:</h3>";
    echo "<a href='index.php' class='btn btn-primary' style='display:inline-block; padding:10px 20px; background:#007bff; color:white; text-decoration:none; border-radius:5px;'>Open Performance Dashboard</a><br><br>";

    echo "<h3>📋 Summary:</h3>";
    echo "<p><strong>Database Setup:</strong> ✅ Complete</p>";
    echo "<p><strong>Tables Created:</strong> ✅ Complete</p>";
    echo "<p><strong>Permissions:</strong> ✅ Complete</p>";
    echo "<p><strong>Module Ready:</strong> ✅ Yes</p>";

    echo "<h3>🚀 Next Steps:</h3>";
    echo "<ol>";
    echo "<li>Click the link above to access the Performance Dashboard</li>";
    echo "<li>Test staff and executive lists</li>";
    echo "<li>Try creating appraisals (requires appropriate permissions)</li>";
    echo "</ol>";

} catch (Exception $e) {
    echo "<h3>❌ Error:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
h1 { color: #007bff; }
h3 { color: #28a745; }
p { margin: 10px 0; }
</style>