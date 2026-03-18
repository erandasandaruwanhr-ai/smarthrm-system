<?php
// Database schema update for objective evaluation fields
require_once '../../config/config.php';

try {
    $db = new Database();

    echo "Adding objective evaluation columns to executive_appraisals table...\n";

    // Objective evaluation columns
    $evaluation_columns = [
        "objective_1_evaluation TEXT COMMENT 'Evaluation of Objective 1 achievement'",
        "objective_2_evaluation TEXT COMMENT 'Evaluation of Objective 2 achievement'",
        "objective_3_evaluation TEXT COMMENT 'Evaluation of Objective 3 achievement'"
    ];

    foreach ($evaluation_columns as $column) {
        try {
            $column_name = explode(' ', $column)[0];

            // Check if column exists
            $check_sql = "SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS
                         WHERE TABLE_SCHEMA = 'smarthrm_db'
                         AND TABLE_NAME = 'executive_appraisals'
                         AND COLUMN_NAME = '$column_name'";

            $result = $db->fetch($check_sql);

            if ($result['count'] == 0) {
                $sql = "ALTER TABLE executive_appraisals ADD COLUMN $column";
                $db->query($sql);
                echo "✓ Added column: $column_name\n";
            } else {
                echo "- Column already exists: $column_name\n";
            }
        } catch (Exception $e) {
            echo "✗ Error adding column $column_name: " . $e->getMessage() . "\n";
        }
    }

    echo "\nObjective evaluation schema update completed!\n";

    // Show final table structure summary
    $total_columns = $db->fetch("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'smarthrm_db' AND TABLE_NAME = 'executive_appraisals'");
    echo "Total columns in executive_appraisals: " . $total_columns['count'] . "\n";

} catch (Exception $e) {
    echo "Database connection error: " . $e->getMessage() . "\n";
}
?>