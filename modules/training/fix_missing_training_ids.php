<?php
require_once '../../config/config.php';

$db = new Database();

echo "<h3>Fixing Missing Training IDs...</h3>";

try {
    // Update training_plans that are missing training_id
    echo "<h4>Step 1: Updating Training Plans</h4>";
    $plans_without_training_id = $db->fetchAll("
        SELECT tp.id, tr.training_id
        FROM training_plans tp
        JOIN training_requirements tr ON tp.requirement_id = tr.id
        WHERE tp.training_id IS NULL
    ");

    if ($plans_without_training_id) {
        foreach ($plans_without_training_id as $plan) {
            $db->query("UPDATE training_plans SET training_id = ? WHERE id = ?", [
                $plan['training_id'],
                $plan['id']
            ]);
            echo "<p>✅ Updated training plan ID {$plan['id']} with Training ID: {$plan['training_id']}</p>";
        }
    } else {
        echo "<p>ℹ️ All training plans already have Training IDs</p>";
    }

    // Update training_evaluations that are missing training_id
    echo "<h4>Step 2: Updating Training Evaluations</h4>";
    $evals_without_training_id = $db->fetchAll("
        SELECT te.id, tp.training_id
        FROM training_evaluations te
        JOIN training_plans tp ON te.training_plan_id = tp.id
        WHERE te.training_id IS NULL
    ");

    if ($evals_without_training_id) {
        foreach ($evals_without_training_id as $eval) {
            $db->query("UPDATE training_evaluations SET training_id = ? WHERE id = ?", [
                $eval['training_id'],
                $eval['id']
            ]);
            echo "<p>✅ Updated evaluation ID {$eval['id']} with Training ID: {$eval['training_id']}</p>";
        }
    } else {
        echo "<p>ℹ️ All evaluations already have Training IDs</p>";
    }

    // Update training_budget that are missing training_id
    echo "<h4>Step 3: Updating Training Budget</h4>";
    $budgets_without_training_id = $db->fetchAll("
        SELECT tb.id, tr.training_id
        FROM training_budget tb
        JOIN training_requirements tr ON tb.requirement_id = tr.id
        WHERE tb.training_id IS NULL
    ");

    if ($budgets_without_training_id) {
        foreach ($budgets_without_training_id as $budget) {
            $db->query("UPDATE training_budget SET training_id = ? WHERE id = ?", [
                $budget['training_id'],
                $budget['id']
            ]);
            echo "<p>✅ Updated budget ID {$budget['id']} with Training ID: {$budget['training_id']}</p>";
        }
    } else {
        echo "<p>ℹ️ All budget entries already have Training IDs</p>";
    }

    echo "<h4>✅ Fix Complete!</h4>";
    echo "<p><a href='debug_training_ids.php'>Check Status Again</a></p>";
    echo "<p><a href='feedback.php'>Test Feedback Form</a></p>";

} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>