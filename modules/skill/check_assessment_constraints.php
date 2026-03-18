<?php
require_once '../../config/config.php';

$db = new Database();

echo "<h2>Check Assessment Table Constraints</h2>";

try {
    // Check table structure
    echo "<h3>Table Structure:</h3>";
    $structure = $db->fetchAll("DESCRIBE skill_matrix_assessments");

    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($structure as $field) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($field['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($field['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($field['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($field['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($field['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($field['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Check constraints
    echo "<h3>Table Constraints:</h3>";
    $constraints = $db->fetchAll("SELECT * FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = 'smarthrm_db' AND TABLE_NAME = 'skill_matrix_assessments'");

    if (!empty($constraints)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Constraint Name</th><th>Constraint Type</th></tr>";
        foreach ($constraints as $constraint) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($constraint['CONSTRAINT_NAME']) . "</td>";
            echo "<td>" . htmlspecialchars($constraint['CONSTRAINT_TYPE']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // Check check constraints specifically
    echo "<h3>Check Constraints Details:</h3>";
    $check_constraints = $db->fetchAll("SELECT * FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = 'smarthrm_db' AND TABLE_NAME = 'skill_matrix_assessments'");

    if (!empty($check_constraints)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Constraint Name</th><th>Check Clause</th></tr>";
        foreach ($check_constraints as $check) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($check['CONSTRAINT_NAME']) . "</td>";
            echo "<td>" . htmlspecialchars($check['CHECK_CLAUSE']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        // Try alternative method for MySQL 8.0+
        echo "<p>Checking constraints using SHOW CREATE TABLE...</p>";
        $create_table = $db->fetchAll("SHOW CREATE TABLE skill_matrix_assessments");
        if (!empty($create_table)) {
            echo "<pre>" . htmlspecialchars($create_table[0]['Create Table']) . "</pre>";
        }
    }

    // Check existing data to see what values are valid
    echo "<h3>Sample of Existing Valid Data:</h3>";
    $sample_data = $db->fetchAll("SELECT * FROM skill_matrix_assessments LIMIT 5");

    if (!empty($sample_data)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Year ID</th><th>Employee EPF</th><th>Manager EPF</th><th>Skill ID</th><th>Current</th><th>Target</th><th>Gap</th></tr>";
        foreach ($sample_data as $row) {
            echo "<tr>";
            echo "<td>" . $row['year_id'] . "</td>";
            echo "<td>" . $row['employee_epf'] . "</td>";
            echo "<td>" . $row['manager_epf'] . "</td>";
            echo "<td>" . $row['skill_id'] . "</td>";
            echo "<td>" . $row['current_rating'] . "</td>";
            echo "<td>" . $row['target_rating'] . "</td>";
            echo "<td>" . $row['gap_rating'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No existing data to analyze.</p>";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='inject_assessment_data.php'>Back to Data Injection</a></p>";
?>