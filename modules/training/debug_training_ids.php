<?php
require_once '../../config/config.php';

$db = new Database();

echo "<h3>Debug: Training IDs in Database</h3>";

// Check training plans and their training_ids
echo "<h4>Training Plans:</h4>";
$plans = $db->fetchAll("SELECT id, training_id, training_name, trainee_name, status FROM training_plans ORDER BY id DESC");

if ($plans) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th style='padding: 5px;'>Plan ID</th><th style='padding: 5px;'>Training ID</th><th style='padding: 5px;'>Training Name</th><th style='padding: 5px;'>Trainee</th><th style='padding: 5px;'>Status</th></tr>";
    foreach ($plans as $plan) {
        $training_id = $plan['training_id'] ?: 'NULL';
        echo "<tr>";
        echo "<td style='padding: 5px;'>{$plan['id']}</td>";
        echo "<td style='padding: 5px; font-weight: bold; color: " . ($plan['training_id'] ? 'green' : 'red') . ";'>$training_id</td>";
        echo "<td style='padding: 5px;'>" . substr($plan['training_name'], 0, 30) . "</td>";
        echo "<td style='padding: 5px;'>{$plan['trainee_name']}</td>";
        echo "<td style='padding: 5px;'>{$plan['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No training plans found.</p>";
}

// Check available trainings for feedback
echo "<h4>Available Trainings for Feedback (Same query as feedback.php):</h4>";
$available_trainings = $db->fetchAll("
    SELECT
        tp.id as plan_id,
        tp.training_id,
        tp.training_name,
        tp.trainee_name,
        tp.trainee_epf,
        tp.training_start_date,
        tp.training_end_date,
        te.id as evaluation_id
    FROM training_plans tp
    JOIN training_evaluations te ON tp.id = te.training_plan_id
    LEFT JOIN training_feedback tf ON tp.id = tf.training_plan_id
    WHERE tp.status = 'completed'
    AND tf.id IS NULL
    ORDER BY tp.training_end_date DESC
");

if ($available_trainings) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th style='padding: 5px;'>Plan ID</th><th style='padding: 5px;'>Training ID</th><th style='padding: 5px;'>Training Name</th><th style='padding: 5px;'>Trainee</th><th style='padding: 5px;'>Status</th></tr>";
    foreach ($available_trainings as $training) {
        $training_id = $training['training_id'] ?: 'NULL';
        echo "<tr>";
        echo "<td style='padding: 5px;'>{$training['plan_id']}</td>";
        echo "<td style='padding: 5px; font-weight: bold; color: " . ($training['training_id'] ? 'green' : 'red') . ";'>$training_id</td>";
        echo "<td style='padding: 5px;'>" . substr($training['training_name'], 0, 30) . "</td>";
        echo "<td style='padding: 5px;'>{$training['trainee_name']}</td>";
        echo "<td style='padding: 5px;'>Available for feedback</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No trainings available for feedback.</p>";
}

// Show fix options
echo "<h4>Fix Options:</h4>";
echo "<p><a href='fix_missing_training_ids.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Fix Missing Training IDs</a></p>";
echo "<p><a href='feedback.php'>Go back to Feedback</a></p>";
?>