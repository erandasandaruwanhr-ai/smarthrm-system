<?php
// Clean up unused columns from executive_appraisals table
// Remove columns that are empty and not used in the current form

require_once '../../config/config.php';

try {
    $db = new Database();
    echo "Starting cleanup of unused columns from executive_appraisals table...\n\n";

    // List of unused columns to remove
    $columns_to_drop = [
        // Old training columns (not used in current form structure)
        'training_mandatory',
        'training_professional',
        'training_skills',
        'training_leadership',

        // Old feedback columns (not used in current form structure)
        'feedback_strengths',
        'feedback_improvements',
        'feedback_action_plan',
        'feedback_manager_comments',
        'feedback_self_reflection'
    ];

    $dropped_columns = 0;
    $missing_columns = 0;

    foreach ($columns_to_drop as $column_name) {
        try {
            // Check if column exists
            $check_sql = "SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS
                         WHERE TABLE_SCHEMA = 'smarthrm_db'
                         AND TABLE_NAME = 'executive_appraisals'
                         AND COLUMN_NAME = '$column_name'";

            $result = $db->fetch($check_sql);

            if ($result['count'] > 0) {
                // Column exists, drop it
                $sql = "ALTER TABLE executive_appraisals DROP COLUMN $column_name";
                $db->query($sql);
                echo "✓ Dropped column: $column_name\n";
                $dropped_columns++;
            } else {
                echo "- Column doesn't exist: $column_name\n";
                $missing_columns++;
            }

        } catch (Exception $e) {
            echo "✗ Error dropping column $column_name: " . $e->getMessage() . "\n";
        }
    }

    echo "\n=== CLEANUP SUMMARY ===\n";
    echo "Columns dropped: $dropped_columns\n";
    echo "Columns already missing: $missing_columns\n";
    echo "Total attempted: " . count($columns_to_drop) . "\n";

    // Show final table structure summary
    $total_columns = $db->fetch("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'smarthrm_db' AND TABLE_NAME = 'executive_appraisals'");
    echo "\nRemaining columns in executive_appraisals table: " . $total_columns['count'] . "\n";

    echo "\n✓ Database cleanup completed successfully!\n";
    echo "All unused columns have been removed to optimize the table structure.\n";

} catch (Exception $e) {
    echo "Database connection error: " . $e->getMessage() . "\n";
}
?>