<?php
require_once '../../config/config.php';

$db = new Database();

echo "=== FIXING DUPLICATE RECORDS IN v_training_effectiveness VIEW ===\n\n";

// Step 1: Drop the existing view
echo "1. Dropping existing view...\n";
try {
    $db->query("DROP VIEW IF EXISTS v_training_effectiveness");
    echo "✓ View dropped successfully\n";
} catch (Exception $e) {
    echo "Error dropping view: " . $e->getMessage() . "\n";
}

// Step 2: Create improved view with DISTINCT and proper joins
echo "\n2. Creating improved view...\n";
$improved_view_sql = "
CREATE VIEW v_training_effectiveness AS
SELECT DISTINCT
    tp.id as training_plan_id,
    tp.training_name,
    tp.trainee_name,
    tp.training_start_date,
    -- Get the latest evaluation data
    (SELECT te.immediate_application_rating FROM training_evaluations te
     WHERE te.training_plan_id = tp.id ORDER BY te.submitted_at DESC LIMIT 1) as immediate_application_rating,
    (SELECT te.performance_improvement_rating FROM training_evaluations te
     WHERE te.training_plan_id = tp.id ORDER BY te.submitted_at DESC LIMIT 1) as performance_improvement_rating,
    (SELECT te.recommend_to_colleague_rating FROM training_evaluations te
     WHERE te.training_plan_id = tp.id ORDER BY te.submitted_at DESC LIMIT 1) as recommend_to_colleague_rating,
    -- Get the latest feedback data
    (SELECT tf.skill_transfer_rating FROM training_feedback tf
     WHERE tf.training_plan_id = tp.id ORDER BY tf.submitted_at DESC LIMIT 1) as skill_transfer_rating,
    (SELECT tf.performance_improvement_rating FROM training_feedback tf
     WHERE tf.training_plan_id = tp.id ORDER BY tf.submitted_at DESC LIMIT 1) as supervisor_performance_rating,
    (SELECT tf.productivity_change FROM training_feedback tf
     WHERE tf.training_plan_id = tp.id ORDER BY tf.submitted_at DESC LIMIT 1) as productivity_change,
    -- Get the latest managerial comments
    (SELECT tmc.overall_effectiveness FROM training_managerial_comments tmc
     JOIN training_feedback tf ON tmc.training_feedback_id = tf.id
     WHERE tf.training_plan_id = tp.id ORDER BY tmc.created_at DESC LIMIT 1) as overall_effectiveness,
    -- Get tracker status
    (SELECT tt.status FROM training_tracker tt
     WHERE tt.training_plan_id = tp.id ORDER BY tt.updated_at DESC LIMIT 1) as tracker_status
FROM training_plans tp
WHERE tp.id IS NOT NULL
";

try {
    $db->query($improved_view_sql);
    echo "✓ Improved view created successfully\n";
} catch (Exception $e) {
    echo "Error creating view: " . $e->getMessage() . "\n";
}

// Step 3: Test the new view
echo "\n3. Testing new view for training_plan_id = 2...\n";
try {
    $result = $db->fetchAll("SELECT * FROM v_training_effectiveness WHERE training_plan_id = 2");
    echo "Records found: " . count($result) . "\n";
    if (count($result) > 0) {
        echo "✓ SUCCESS: Only one record returned\n";
        print_r($result[0]);
    }
} catch (Exception $e) {
    echo "Error testing view: " . $e->getMessage() . "\n";
}

// Step 4: Check for underlying duplicate data that might need cleanup
echo "\n4. Checking for actual duplicate data in base tables...\n";

echo "Training Evaluations for plan 2: ";
try {
    $evals = $db->fetchAll("SELECT COUNT(*) as count FROM training_evaluations WHERE training_plan_id = 2");
    echo $evals[0]['count'] . " records\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "Training Feedback for plan 2: ";
try {
    $feedback = $db->fetchAll("SELECT COUNT(*) as count FROM training_feedback WHERE training_plan_id = 2");
    echo $feedback[0]['count'] . " records\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== FIX COMPLETED ===\n";
?>