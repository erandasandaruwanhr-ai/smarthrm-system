<?php
require_once '../../config/config.php';

echo "<h2>🔧 Update Anonymous Note Types</h2>\n";

try {
    $db = new Database();

    echo "<h3>Step 1: Update database schema for note types</h3>\n";

    // First update the anonymous_grievances status ENUM
    $status_sql = "ALTER TABLE `anonymous_grievances`
                   MODIFY COLUMN `status` ENUM(
                       'Opened',
                       'Pending Team Assignment',
                       'Team Investigation',
                       'Investigation Complete',
                       'HR Review',
                       'Resolved',
                       'Pending Investigation',   -- Legacy
                       'Under Investigation',     -- Legacy
                       'Dismissed'               -- Legacy
                   ) DEFAULT 'Opened'";

    $db->query($status_sql);
    echo "✅ Updated anonymous_grievances status options\n";

    echo "<h3>Step 2: Update note types schema</h3>\n";

    $sql = "ALTER TABLE `anonymous_grievance_notes`
            MODIFY COLUMN `action_type` ENUM(
                'Submission',               -- Initial anonymous submission
                'Investigation Assignment', -- Team assigned
                'Investigation start',      -- Investigation team starting work
                'Investigation evidence',   -- Evidence collected during investigation
                'Final report',            -- Final investigation report
                'HR Review',               -- HR department review
                'HR resolution',           -- HR final resolution
                'Investigation Report',     -- Team findings (legacy)
                'Investigation Progress',   -- Team updates (legacy)
                'Evidence Added',          -- New evidence (legacy)
                'Superadmin Review',       -- Superadmin notes (legacy)
                'Resolution',              -- Final closure (legacy)
                'Dismissal'                -- Case dismissed (legacy)
            ) NOT NULL";

    $db->query($sql);
    echo "✅ Database schema updated successfully\n";

    echo "\n<h3>Step 2: Verify new note types</h3>\n";
    $columns = $db->fetch("SHOW COLUMNS FROM anonymous_grievance_notes WHERE Field = 'action_type'");
    echo "✅ action_type column definition:\n";
    echo "- Type: " . $columns['Type'] . "\n";

    echo "\n<h3>Updated Status Options:</h3>\n";
    echo "<strong>Available Status Options:</strong>\n";
    echo "- Opened\n";
    echo "- Pending Team Assignment\n";
    echo "- Team Investigation\n";
    echo "- Investigation Complete\n";
    echo "- HR Review\n";
    echo "- Resolved\n\n";

    echo "<h3>Available Note Types:</h3>\n";
    echo "<strong>Investigation Team Members:</strong>\n";
    echo "- Investigation start\n";
    echo "- Investigation evidence\n";
    echo "- Final report\n\n";
    echo "<strong>Superadmin/HR:</strong>\n";
    echo "- Investigation start\n";
    echo "- Investigation evidence\n";
    echo "- Final report\n";
    echo "- HR Review\n";
    echo "- HR resolution\n";

    echo "\n<p>✅ Update completed successfully!</p>\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>