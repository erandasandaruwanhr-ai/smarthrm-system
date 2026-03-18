<?php
// Database schema update for executive appraisal form
require_once '../../config/config.php';

try {
    $db = new Database();

    echo "Updating executive_appraisals table schema...\n";

    // Category 7: Training (13.7.3.1)
    $training_columns = [
        "training_mandatory TEXT COMMENT '13.7.3.1.1 Mandatory Training Required'",
        "training_professional TEXT COMMENT '13.7.3.1.2 Professional Development Training Desired'",
        "training_skills TEXT COMMENT '13.7.3.1.3 Skills Enhancement Training'",
        "training_leadership TEXT COMMENT '13.7.3.1.4 Leadership Development Training'"
    ];

    // Category 8: Growth and feedback (13.7.3.2)
    $feedback_columns = [
        "feedback_strengths TEXT COMMENT '13.7.3.2.1 Key Strengths'",
        "feedback_improvements TEXT COMMENT '13.7.3.2.2 Areas for Improvement'",
        "feedback_action_plan TEXT COMMENT '13.7.3.2.3 Action Plan for Growth'",
        "feedback_manager_comments TEXT COMMENT '13.7.3.2.4 Manager Additional Comments'",
        "feedback_self_reflection TEXT COMMENT '13.7.3.2.5 Employee Self-Reflection'"
    ];

    $all_columns = array_merge($training_columns, $feedback_columns);

    foreach ($all_columns as $column) {
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

    echo "\nSchema update completed!\n";

} catch (Exception $e) {
    echo "Database connection error: " . $e->getMessage() . "\n";
}
?>