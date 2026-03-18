<?php
require_once '../../config/config.php';

$db = new Database();

echo "<h3>Adding Training ID columns to all training tables...</h3>";

try {
    // Add training_id column to training_requirements table
    echo "<p>Adding training_id column to training_requirements table...</p>";
    $db->query("ALTER TABLE training_requirements ADD COLUMN training_id VARCHAR(20) NULL AFTER id");
    echo "<p>✅ Added training_id to training_requirements</p>";

    // Add training_id column to training_budget table
    echo "<p>Adding training_id column to training_budget table...</p>";
    $db->query("ALTER TABLE training_budget ADD COLUMN training_id VARCHAR(20) NULL AFTER id");
    echo "<p>✅ Added training_id to training_budget</p>";

    // Add training_id column to training_plans table
    echo "<p>Adding training_id column to training_plans table...</p>";
    $db->query("ALTER TABLE training_plans ADD COLUMN training_id VARCHAR(20) NULL AFTER id");
    echo "<p>✅ Added training_id to training_plans</p>";

    // Add training_id column to training_evaluations table
    echo "<p>Adding training_id column to training_evaluations table...</p>";
    $db->query("ALTER TABLE training_evaluations ADD COLUMN training_id VARCHAR(20) NULL AFTER id");
    echo "<p>✅ Added training_id to training_evaluations</p>";

    // Add training_id column to training_feedback table
    echo "<p>Adding training_id column to training_feedback table...</p>";
    $db->query("ALTER TABLE training_feedback ADD COLUMN training_id VARCHAR(20) NULL AFTER id");
    echo "<p>✅ Added training_id to training_feedback</p>";

    echo "<h4>Creating indexes for better performance...</h4>";

    // Add indexes for training_id columns
    $db->query("ALTER TABLE training_requirements ADD INDEX idx_training_id (training_id)");
    $db->query("ALTER TABLE training_budget ADD INDEX idx_training_id (training_id)");
    $db->query("ALTER TABLE training_plans ADD INDEX idx_training_id (training_id)");
    $db->query("ALTER TABLE training_evaluations ADD INDEX idx_training_id (training_id)");
    $db->query("ALTER TABLE training_feedback ADD INDEX idx_training_id (training_id)");

    echo "<p>✅ Added indexes for all training_id columns</p>";

    echo "<h4>Generating Training IDs for existing records...</h4>";

    // Generate training IDs for existing training requirements
    $existing_requirements = $db->fetchAll("SELECT id, year FROM training_requirements WHERE training_id IS NULL ORDER BY id ASC");
    foreach ($existing_requirements as $index => $req) {
        $sequence = $index + 1;
        $training_id = sprintf("TR-%s-%03d", $req['year'], $sequence);
        $db->query("UPDATE training_requirements SET training_id = ? WHERE id = ?", [$training_id, $req['id']]);
        echo "<p>Generated Training ID: $training_id for requirement ID: {$req['id']}</p>";
    }

    echo "<p>✅ Database schema updated successfully!</p>";
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Database tables now have training_id columns</li>";
    echo "<li>✅ Existing training requirements have been assigned Training IDs</li>";
    echo "<li>🔄 Now we can implement Training ID system in all PHP files</li>";
    echo "</ul>";

    echo "<p><a href='requirements.php'>Go to Training Requirements</a></p>";

} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>This might be because the columns already exist or there's a database connection issue.</p>";
}
?>