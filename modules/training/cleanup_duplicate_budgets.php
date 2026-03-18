<?php
require_once '../../config/config.php';

$db = new Database();

echo "<h3>Cleaning up duplicate budget entries...</h3>";

try {
    // Find duplicate budget entries (same requirement_id)
    $duplicates = $db->fetchAll("
        SELECT requirement_id, COUNT(*) as count
        FROM training_budget
        GROUP BY requirement_id
        HAVING COUNT(*) > 1
    ");

    echo "<p>Found " . count($duplicates) . " requirements with duplicate budget entries.</p>";

    foreach ($duplicates as $duplicate) {
        $requirement_id = $duplicate['requirement_id'];
        echo "<p>Processing requirement ID: $requirement_id (has {$duplicate['count']} entries)</p>";

        // Get all budget entries for this requirement, ordered by creation date (keep the latest)
        $budget_entries = $db->fetchAll("
            SELECT id, approval_date
            FROM training_budget
            WHERE requirement_id = ?
            ORDER BY approval_date DESC, id DESC
        ", [$requirement_id]);

        // Keep the first (latest) entry, delete the rest
        $keep_id = $budget_entries[0]['id'];
        echo "<p>Keeping budget entry ID: $keep_id</p>";

        for ($i = 1; $i < count($budget_entries); $i++) {
            $delete_id = $budget_entries[$i]['id'];
            $db->query("DELETE FROM training_budget WHERE id = ?", [$delete_id]);
            echo "<p>Deleted duplicate budget entry ID: $delete_id</p>";
        }
    }

    echo "<p>✅ Cleanup completed!</p>";
    echo "<p><a href='budget.php'>Go back to Budget Management</a></p>";

} catch (Exception $e) {
    echo "<p>❌ Error during cleanup: " . $e->getMessage() . "</p>";
}
?>