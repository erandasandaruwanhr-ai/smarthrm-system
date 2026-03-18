<?php
// PHP Database Update Script for Executive Appraisals Table
// Adds all missing columns with proper error handling

require_once '../../config/config.php';

try {
    $db = new Database();
    echo "Starting executive_appraisals table update...\n\n";

    // Define all missing columns to be added
    $columns_to_add = [
        // Category 7: Development and Training
        "development_training TEXT COMMENT 'Development and Training requirements'",

        // Category 8: Future Growth
        "future_growth TEXT COMMENT 'Future Growth interests'",

        // Category 9: Manager Performance Feedback
        "manager_performance_feedback TEXT COMMENT 'Feedback on Manager Performance'",
        "manager_improvement_areas TEXT COMMENT 'Manager Improvement Areas'",

        // Category 10: Other Discussion Areas
        "discussion_point_1 TEXT COMMENT 'Discussion Point 1'",
        "discussion_point_2 TEXT COMMENT 'Discussion Point 2'",
        "discussion_point_3 TEXT COMMENT 'Discussion Point 3'",

        // Category 11: Compliance Section
        "compliance_q1 ENUM('yes','no') COMMENT 'Compliance Question 1'",
        "compliance_q1_comments TEXT COMMENT 'Compliance Q1 Comments'",
        "compliance_q2 ENUM('yes','no') COMMENT 'Compliance Question 2'",
        "compliance_q2_comments TEXT COMMENT 'Compliance Q2 Comments'",
        "compliance_q3 ENUM('yes','no') COMMENT 'Compliance Question 3'",
        "compliance_q3_comments TEXT COMMENT 'Compliance Q3 Comments'",

        // Additional field
        "evaluation_date DATE COMMENT 'Evaluation Date'"
    ];

    $added_columns = 0;
    $existing_columns = 0;

    foreach ($columns_to_add as $column_definition) {
        // Extract column name from definition
        $column_name = explode(' ', $column_definition)[0];

        try {
            // Check if column already exists
            $check_sql = "SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS
                         WHERE TABLE_SCHEMA = 'smarthrm_db'
                         AND TABLE_NAME = 'executive_appraisals'
                         AND COLUMN_NAME = '$column_name'";

            $result = $db->fetch($check_sql);

            if ($result['count'] == 0) {
                // Column doesn't exist, add it
                $sql = "ALTER TABLE executive_appraisals ADD COLUMN $column_definition";
                $db->query($sql);
                echo "✓ Added column: $column_name\n";
                $added_columns++;
            } else {
                echo "- Column already exists: $column_name\n";
                $existing_columns++;
            }

        } catch (Exception $e) {
            echo "✗ Error with column $column_name: " . $e->getMessage() . "\n";
        }
    }

    echo "\n=== UPDATE SUMMARY ===\n";
    echo "Columns added: $added_columns\n";
    echo "Columns already existing: $existing_columns\n";
    echo "Total attempted: " . count($columns_to_add) . "\n";

    // Show final table structure summary
    $total_columns = $db->fetch("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'smarthrm_db' AND TABLE_NAME = 'executive_appraisals'");
    echo "\nTotal columns in executive_appraisals table: " . $total_columns['count'] . "\n";

    echo "\nExecutive appraisals table update completed successfully!\n";

} catch (Exception $e) {
    echo "Database connection error: " . $e->getMessage() . "\n";
}
?>