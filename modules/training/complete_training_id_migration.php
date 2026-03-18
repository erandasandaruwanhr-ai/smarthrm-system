<?php
require_once '../../config/config.php';

$db = new Database();

echo "<h3>Completing Training ID migration...</h3>";

try {
    echo "<h4>Adding remaining indexes...</h4>";

    // Add indexes for training_id columns (skip if they already exist)
    try {
        $db->query("ALTER TABLE training_requirements ADD INDEX idx_training_id (training_id)");
        echo "<p>✅ Added index for training_requirements.training_id</p>";
    } catch (Exception $e) {
        echo "<p>ℹ️ Index for training_requirements.training_id already exists</p>";
    }

    try {
        $db->query("ALTER TABLE training_budget ADD INDEX idx_training_id (training_id)");
        echo "<p>✅ Added index for training_budget.training_id</p>";
    } catch (Exception $e) {
        echo "<p>ℹ️ Index for training_budget.training_id already exists</p>";
    }

    try {
        $db->query("ALTER TABLE training_plans ADD INDEX idx_training_id (training_id)");
        echo "<p>✅ Added index for training_plans.training_id</p>";
    } catch (Exception $e) {
        echo "<p>ℹ️ Index for training_plans.training_id already exists</p>";
    }

    try {
        $db->query("ALTER TABLE training_evaluations ADD INDEX idx_training_id (training_id)");
        echo "<p>✅ Added index for training_evaluations.training_id</p>";
    } catch (Exception $e) {
        echo "<p>ℹ️ Index for training_evaluations.training_id already exists</p>";
    }

    try {
        $db->query("ALTER TABLE training_feedback ADD INDEX idx_training_id (training_id)");
        echo "<p>✅ Added index for training_feedback.training_id</p>";
    } catch (Exception $e) {
        echo "<p>ℹ️ Index for training_feedback.training_id already exists</p>";
    }

    echo "<h4>Generating Training IDs for existing records...</h4>";

    // Generate training IDs for existing training requirements
    $existing_requirements = $db->fetchAll("SELECT id, year FROM training_requirements WHERE training_id IS NULL ORDER BY id ASC");

    if ($existing_requirements) {
        foreach ($existing_requirements as $index => $req) {
            $sequence = $index + 1;
            $training_id = sprintf("TR-%s-%03d", $req['year'], $sequence);
            $db->query("UPDATE training_requirements SET training_id = ? WHERE id = ?", [$training_id, $req['id']]);
            echo "<p>Generated Training ID: $training_id for requirement ID: {$req['id']}</p>";
        }
    } else {
        echo "<p>ℹ️ All training requirements already have Training IDs</p>";
    }

    // Update related tables with Training IDs
    echo "<h4>Linking related records to Training IDs...</h4>";

    // Update training_budget records
    $budget_updates = $db->query("
        UPDATE training_budget tb
        JOIN training_requirements tr ON tb.requirement_id = tr.id
        SET tb.training_id = tr.training_id
        WHERE tb.training_id IS NULL
    ");
    echo "<p>✅ Updated training_budget records with Training IDs</p>";

    // Update training_plans records
    $plan_updates = $db->query("
        UPDATE training_plans tp
        JOIN training_requirements tr ON tp.requirement_id = tr.id
        SET tp.training_id = tr.training_id
        WHERE tp.training_id IS NULL
    ");
    echo "<p>✅ Updated training_plans records with Training IDs</p>";

    // Update training_evaluations records
    $eval_updates = $db->query("
        UPDATE training_evaluations te
        JOIN training_plans tp ON te.training_plan_id = tp.id
        JOIN training_requirements tr ON tp.requirement_id = tr.id
        SET te.training_id = tr.training_id
        WHERE te.training_id IS NULL
    ");
    echo "<p>✅ Updated training_evaluations records with Training IDs</p>";

    // Check current state
    echo "<h4>Current Training ID Status:</h4>";

    $requirements_count = $db->fetch("SELECT COUNT(*) as count FROM training_requirements WHERE training_id IS NOT NULL");
    echo "<p>Training Requirements with Training ID: {$requirements_count['count']}</p>";

    $budget_count = $db->fetch("SELECT COUNT(*) as count FROM training_budget WHERE training_id IS NOT NULL");
    echo "<p>Budget entries with Training ID: {$budget_count['count']}</p>";

    $plans_count = $db->fetch("SELECT COUNT(*) as count FROM training_plans WHERE training_id IS NOT NULL");
    echo "<p>Training Plans with Training ID: {$plans_count['count']}</p>";

    $eval_count = $db->fetch("SELECT COUNT(*) as count FROM training_evaluations WHERE training_id IS NOT NULL");
    echo "<p>Evaluations with Training ID: {$eval_count['count']}</p>";

    echo "<p>✅ <strong>Training ID migration completed successfully!</strong></p>";
    echo "<p>🚀 <strong>Ready to implement Training ID system in all modules!</strong></p>";

    echo "<p><a href='requirements.php'>Go to Training Requirements</a></p>";

} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>