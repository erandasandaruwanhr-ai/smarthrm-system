<?php
require_once '../../config/config.php';

$db = new Database();

echo "=== INVESTIGATING DUPLICATE RECORDS FOR TRAINING PLAN ID 2 ===\n\n";

// Check training_plans table
echo "1. TRAINING_PLANS table for ID 2:\n";
$plan = $db->fetch("SELECT * FROM training_plans WHERE id = 2");
if ($plan) {
    print_r($plan);
} else {
    echo "No training plan found with ID 2\n";
}

echo "\n2. TRAINING_EVALUATIONS for training_plan_id = 2:\n";
$evaluations = $db->fetchAll("SELECT * FROM training_evaluations WHERE training_plan_id = 2");
echo "Count: " . count($evaluations) . "\n";
foreach ($evaluations as $eval) {
    echo "ID: {$eval['id']}, Submitted: {$eval['submitted_at']}\n";
}

echo "\n3. TRAINING_FEEDBACK for training_plan_id = 2:\n";
$feedback = $db->fetchAll("SELECT * FROM training_feedback WHERE training_plan_id = 2");
echo "Count: " . count($feedback) . "\n";
foreach ($feedback as $fb) {
    echo "ID: {$fb['id']}, Review Date: {$fb['review_date']}, Status: {$fb['status']}\n";
}

echo "\n4. TRAINING_MANAGERIAL_COMMENTS:\n";
$comments = $db->fetchAll("SELECT tmc.*, tf.training_plan_id FROM training_managerial_comments tmc
                          JOIN training_feedback tf ON tmc.training_feedback_id = tf.id
                          WHERE tf.training_plan_id = 2");
echo "Count: " . count($comments) . "\n";
foreach ($comments as $comment) {
    echo "ID: {$comment['id']}, Feedback ID: {$comment['training_feedback_id']}\n";
}

echo "\n5. TRAINING_TRACKER for training_plan_id = 2:\n";
$tracker = $db->fetchAll("SELECT * FROM training_tracker WHERE training_plan_id = 2");
echo "Count: " . count($tracker) . "\n";
foreach ($tracker as $track) {
    echo "ID: {$track['id']}, Status: {$track['status']}\n";
}

echo "\n6. RAW VIEW QUERY RESULT:\n";
$view_result = $db->fetchAll("SELECT * FROM v_training_effectiveness WHERE training_plan_id = 2");
echo "Count from view: " . count($view_result) . "\n";

echo "\n7. MANUAL JOIN QUERY TO REPLICATE VIEW:\n";
$manual_join = $db->fetchAll("
    SELECT
        tp.id as training_plan_id,
        tp.training_name,
        tp.trainee_name,
        tp.training_start_date,
        te.id as eval_id,
        te.immediate_application_rating,
        te.performance_improvement_rating,
        te.recommend_to_colleague_rating,
        tf.id as feedback_id,
        tf.skill_transfer_rating,
        tf.performance_improvement_rating as supervisor_performance_rating,
        tf.productivity_change,
        tmc.id as comment_id,
        tmc.overall_effectiveness,
        tt.id as tracker_id,
        tt.status as tracker_status
    FROM training_plans tp
    LEFT JOIN training_evaluations te ON tp.id = te.training_plan_id
    LEFT JOIN training_feedback tf ON tp.id = tf.training_plan_id
    LEFT JOIN training_managerial_comments tmc ON tf.id = tmc.training_feedback_id
    LEFT JOIN training_tracker tt ON tp.id = tt.training_plan_id
    WHERE tp.id = 2
");

echo "Manual join result count: " . count($manual_join) . "\n";
foreach ($manual_join as $result) {
    echo "Eval ID: {$result['eval_id']}, Feedback ID: {$result['feedback_id']}, Comment ID: {$result['comment_id']}, Tracker ID: {$result['tracker_id']}\n";
}
?>