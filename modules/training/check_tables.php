<?php
require_once '../../config/config.php';

$db = new Database();

echo "<h3>Checking Training Module Database Tables</h3>";

// Check if training_requirements table exists
try {
    $result = $db->fetchAll("SELECT * FROM training_requirements LIMIT 1");
    echo "<p>✅ training_requirements table exists</p>";
} catch (Exception $e) {
    echo "<p>❌ training_requirements table: " . $e->getMessage() . "</p>";
}

// Check if training_budget table exists
try {
    $result = $db->fetchAll("SELECT * FROM training_budget LIMIT 1");
    echo "<p>✅ training_budget table exists</p>";
} catch (Exception $e) {
    echo "<p>❌ training_budget table: " . $e->getMessage() . "</p>";
}

// Check if training_plans table exists
try {
    $result = $db->fetchAll("SELECT * FROM training_plans LIMIT 1");
    echo "<p>✅ training_plans table exists</p>";
} catch (Exception $e) {
    echo "<p>❌ training_plans table: " . $e->getMessage() . "</p>";
}

// Check if training_institutes table exists
try {
    $result = $db->fetchAll("SELECT * FROM training_institutes LIMIT 1");
    echo "<p>✅ training_institutes table exists</p>";
} catch (Exception $e) {
    echo "<p>❌ training_institutes table: " . $e->getMessage() . "</p>";
}

echo "<h3>Training Requirements Data:</h3>";
try {
    $requirements = $db->fetchAll("SELECT id, training_requirement, status, employee_name, epf_number FROM training_requirements ORDER BY id DESC LIMIT 5");
    echo "<table border='1'><tr><th>ID</th><th>Training</th><th>Status</th><th>Employee</th><th>EPF</th></tr>";
    foreach ($requirements as $req) {
        echo "<tr><td>{$req['id']}</td><td>" . substr($req['training_requirement'], 0, 30) . "</td><td>{$req['status']}</td><td>{$req['employee_name']}</td><td>{$req['epf_number']}</td></tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<p>Error fetching requirements: " . $e->getMessage() . "</p>";
}
?>