<?php
require_once '../../config/config.php';

$db = new Database();

echo "=== CHECKING FOR DUPLICATE DATA IN TRAINING TABLES ===\n\n";

// Check training_managerial_comments for duplicates
echo "1. Checking training_managerial_comments table:\n";
$comments = $db->fetchAll("
    SELECT
        id,
        training_feedback_id,
        training_evaluation_id,
        supervisor_name,
        supervisor_epf,
        sign_off_date,
        overall_effectiveness
    FROM training_managerial_comments
    ORDER BY id
");

echo "Total records: " . count($comments) . "\n";
foreach ($comments as $comment) {
    echo "ID: {$comment['id']}, Feedback ID: {$comment['training_feedback_id']}, Supervisor: {$comment['supervisor_name']}, Date: {$comment['sign_off_date']}\n";
}

// Check for duplicate combinations
echo "\n2. Checking for duplicate feedback_id entries:\n";
$duplicates = $db->fetchAll("
    SELECT
        training_feedback_id,
        COUNT(*) as count
    FROM training_managerial_comments
    GROUP BY training_feedback_id
    HAVING COUNT(*) > 1
");

if (count($duplicates) > 0) {
    echo "Found duplicate entries:\n";
    foreach ($duplicates as $dup) {
        echo "Feedback ID: {$dup['training_feedback_id']} has {$dup['count']} records\n";
    }
} else {
    echo "No duplicates found in training_managerial_comments\n";
}

// Check training_feedback table
echo "\n3. Checking training_feedback table:\n";
$feedback = $db->fetchAll("
    SELECT
        id,
        training_plan_id,
        trainee_name,
        evaluator_name,
        review_date,
        status
    FROM training_feedback
    WHERE training_plan_id = 2
");

echo "Training feedback records for plan 2: " . count($feedback) . "\n";
foreach ($feedback as $fb) {
    echo "ID: {$fb['id']}, Plan: {$fb['training_plan_id']}, Trainee: {$fb['trainee_name']}, Status: {$fb['status']}\n";
}

// Check training_plans table
echo "\n4. Checking training_plans table for ID 2:\n";
$plans = $db->fetchAll("SELECT * FROM training_plans WHERE id = 2");
echo "Training plan records with ID 2: " . count($plans) . "\n";
foreach ($plans as $plan) {
    echo "ID: {$plan['id']}, Name: {$plan['training_name']}, Trainee: {$plan['trainee_name']}\n";
}
?>