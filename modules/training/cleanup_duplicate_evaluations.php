<?php
require_once '../../config/config.php';

$db = new Database();

echo "<h3>Cleaning up duplicate evaluation entries...</h3>";

try {
    // Find duplicate evaluation entries (same training_plan_id)
    $duplicates = $db->fetchAll("
        SELECT training_plan_id, COUNT(*) as count
        FROM training_evaluations
        GROUP BY training_plan_id
        HAVING COUNT(*) > 1
    ");

    echo "<p>Found " . count($duplicates) . " training plans with duplicate evaluations.</p>";

    foreach ($duplicates as $duplicate) {
        $training_plan_id = $duplicate['training_plan_id'];
        echo "<p>Processing training plan ID: $training_plan_id (has {$duplicate['count']} evaluations)</p>";

        // Get all evaluation entries for this training plan, ordered by submission date (keep the latest)
        $evaluation_entries = $db->fetchAll("
            SELECT id, submitted_at, training_name, trainee_name
            FROM training_evaluations
            WHERE training_plan_id = ?
            ORDER BY submitted_at DESC, id DESC
        ", [$training_plan_id]);

        // Keep the first (latest) entry, delete the rest
        $keep_id = $evaluation_entries[0]['id'];
        $keep_info = $evaluation_entries[0];
        echo "<p>Keeping evaluation ID: $keep_id (Training: {$keep_info['training_name']}, Trainee: {$keep_info['trainee_name']}, Submitted: {$keep_info['submitted_at']})</p>";

        for ($i = 1; $i < count($evaluation_entries); $i++) {
            $delete_id = $evaluation_entries[$i]['id'];
            $delete_info = $evaluation_entries[$i];
            $db->query("DELETE FROM training_evaluations WHERE id = ?", [$delete_id]);
            echo "<p>Deleted duplicate evaluation ID: $delete_id (Submitted: {$delete_info['submitted_at']})</p>";
        }
    }

    echo "<p>✅ Cleanup completed!</p>";
    echo "<p><a href='evaluation.php'>Go back to Training Evaluations</a></p>";

    // Show remaining evaluations
    echo "<h4>Remaining Evaluations:</h4>";
    $remaining = $db->fetchAll("
        SELECT id, training_name, trainee_name, submitted_at
        FROM training_evaluations
        ORDER BY submitted_at DESC
    ");

    if ($remaining) {
        echo "<table border='1' style='border-collapse: collapse; margin-top: 10px;'>";
        echo "<tr><th style='padding: 5px;'>ID</th><th style='padding: 5px;'>Training</th><th style='padding: 5px;'>Trainee</th><th style='padding: 5px;'>Submitted</th></tr>";
        foreach ($remaining as $eval) {
            echo "<tr>";
            echo "<td style='padding: 5px;'>{$eval['id']}</td>";
            echo "<td style='padding: 5px;'>" . substr($eval['training_name'], 0, 30) . "</td>";
            echo "<td style='padding: 5px;'>{$eval['trainee_name']}</td>";
            echo "<td style='padding: 5px;'>{$eval['submitted_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No evaluations found.</p>";
    }

} catch (Exception $e) {
    echo "<p>❌ Error during cleanup: " . $e->getMessage() . "</p>";
}
?>