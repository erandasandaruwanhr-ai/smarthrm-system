<?php
require_once '../../config/config.php';

$db = new Database();

echo "<h2>Database Constraint Check</h2>";

try {
    // Check table structure
    echo "<h3>skill_matrix_skills Table Structure:</h3>";
    $structure = $db->fetchAll("DESCRIBE skill_matrix_skills");

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

    // Check constraints/indexes
    echo "<h3>Table Indexes and Constraints:</h3>";
    $indexes = $db->fetchAll("SHOW INDEX FROM skill_matrix_skills");

    if (!empty($indexes)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Key Name</th><th>Column Name</th><th>Unique</th><th>Index Type</th></tr>";
        foreach ($indexes as $index) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($index['Key_name']) . "</td>";
            echo "<td>" . htmlspecialchars($index['Column_name']) . "</td>";
            echo "<td>" . ($index['Non_unique'] == 0 ? 'YES' : 'NO') . "</td>";
            echo "<td>" . htmlspecialchars($index['Index_type']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // Check existing data that might be causing conflicts
    echo "<h3>Existing Skills for Year 1:</h3>";
    $existing_skills = $db->fetchAll("SELECT * FROM skill_matrix_skills WHERE year_id = 1 ORDER BY category_id, display_order");

    if (!empty($existing_skills)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Category ID</th><th>Skill Name</th><th>Display Order</th></tr>";
        foreach ($existing_skills as $skill) {
            $highlight = ($skill['skill_name'] == 'aaaa') ? 'background: yellow;' : '';
            echo "<tr style='$highlight'>";
            echo "<td>" . $skill['id'] . "</td>";
            echo "<td>" . $skill['category_id'] . "</td>";
            echo "<td>" . htmlspecialchars($skill['skill_name']) . "</td>";
            echo "<td>" . $skill['display_order'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<h3>Solution:</h3>";
echo "<p>The constraint 'unique_year_category_skill' means you cannot have duplicate skill names within the same year and category.</p>";
echo "<p>You need to either:</p>";
echo "<ol>";
echo "<li>Delete the existing 'aaaa' skill from category 2</li>";
echo "<li>Choose a different skill name</li>";
echo "<li>Update the existing skill instead of inserting a new one</li>";
echo "</ol>";

echo "<p><a href='setup_working.php?year_id=1'>Back to Setup</a></p>";
?>