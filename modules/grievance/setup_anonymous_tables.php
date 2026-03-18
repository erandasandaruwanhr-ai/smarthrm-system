<?php
// Setup script to ensure anonymous tables exist
require_once '../../config/config.php';

try {
    $db = new Database();

    echo "=== Setting up Anonymous Grievance Tables ===\n\n";

    // Read the SQL file
    $sql_content = file_get_contents(__DIR__ . '/create_anonymous_tables.sql');

    if ($sql_content === false) {
        throw new Exception("Could not read create_anonymous_tables.sql file");
    }

    // Split by semicolons and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql_content)));

    foreach ($statements as $statement) {
        if (!empty($statement)) {
            echo "Executing: " . substr($statement, 0, 50) . "...\n";
            try {
                $db->query($statement);
                echo "✅ Success\n";
            } catch (Exception $e) {
                // If table already exists, that's okay
                if (strpos($e->getMessage(), 'already exists') !== false) {
                    echo "ℹ️  Table already exists\n";
                } else {
                    echo "❌ Error: " . $e->getMessage() . "\n";
                }
            }
        }
    }

    echo "\n=== Checking Anonymous Tables ===\n";

    // Verify tables exist
    $tables = [
        'anonymous_grievances',
        'anonymous_investigation_teams',
        'anonymous_grievance_notes',
        'anonymous_grievance_evidence'
    ];

    foreach ($tables as $table) {
        $exists = $db->fetch("SHOW TABLES LIKE '$table'");
        if ($exists) {
            $count = $db->fetch("SELECT COUNT(*) as count FROM $table")['count'];
            echo "✅ $table: $count records\n";
        } else {
            echo "❌ $table: NOT FOUND\n";
        }
    }

    echo "\n=== Testing Sample Assignment ===\n";

    // Check if we need to create a test assignment
    $test_case = $db->fetch("SELECT id FROM anonymous_grievances LIMIT 1");
    if ($test_case) {
        echo "✅ Found anonymous case ID: " . $test_case['id'] . "\n";

        $existing_team = $db->fetch("SELECT * FROM anonymous_investigation_teams WHERE anonymous_grievance_id = ?", [$test_case['id']]);
        if ($existing_team) {
            echo "✅ Team assignment exists:\n";
            echo "   Leader: " . $existing_team['team_leader_name'] . " (" . $existing_team['team_leader_epf'] . ")\n";
            echo "   Member 1: " . ($existing_team['member1_name'] ?? 'None') . " (" . ($existing_team['member1_epf'] ?? 'None') . ")\n";
            echo "   Member 2: " . ($existing_team['member2_name'] ?? 'None') . " (" . ($existing_team['member2_epf'] ?? 'None') . ")\n";
        } else {
            echo "ℹ️  No team assignment found for this case\n";
        }
    } else {
        echo "ℹ️  No anonymous cases found\n";
    }

    echo "\n=== Setup Complete ===\n";

} catch (Exception $e) {
    echo "❌ Setup failed: " . $e->getMessage() . "\n";
}
?>