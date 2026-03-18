<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$db = new Database();

echo "<h1>🔧 Add Anonymous Case Status</h1>";

try {
    echo "<h2>Adding 'Pending Investigation' status for anonymous cases...</h2>";

    // Update the grievances status ENUM to include new anonymous workflow status
    $alter_sql = "ALTER TABLE `grievances`
                  MODIFY `status` ENUM(
                      'Open',
                      'Under Supervisory Review',
                      'Under Managerial Review',
                      'Pending Investigation',
                      'Under Investigation',
                      'Resolved'
                  ) DEFAULT 'Open'";

    $db->query($alter_sql);

    echo "<p style='color: green;'>✅ <strong>SUCCESS!</strong> Status ENUM updated.</p>";

    echo "<h3>Available statuses now:</h3>";
    echo "<ul>";
    echo "<li><strong>Open</strong> - Initial status for non-anonymous cases</li>";
    echo "<li><strong>Under Supervisory Review</strong> - Non-anonymous workflow</li>";
    echo "<li><strong>Under Managerial Review</strong> - Non-anonymous workflow</li>";
    echo "<li><strong>Pending Investigation</strong> - Anonymous cases waiting for team assignment</li>";
    echo "<li><strong>Under Investigation</strong> - Anonymous cases with active investigation team</li>";
    echo "<li><strong>Resolved</strong> - Final status for all cases</li>";
    echo "</ul>";

    echo "<p><a href='submit_grievance.php'>← Test Anonymous Submission</a> | <a href='index.php'>← Back to Module</a></p>";

} catch (Exception $e) {
    echo "<div style='color: red;'>";
    echo "<h3>❌ Error</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
h1 { color: #007bff; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
ul { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
li { margin: 8px 0; }
</style>