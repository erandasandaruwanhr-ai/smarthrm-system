<?php
require_once '../../config/config.php';

$db = new Database();

echo "<h2>Debug Saved Skills</h2>";

try {
    // Get year 2026 ID
    $year = $db->fetch("SELECT id FROM skill_matrix_years WHERE year = 2026");
    if (!$year) {
        echo "<p style='color: red;'>Year 2026 not found!</p>";
        exit;
    }
    $year_id = $year['id'];

    // Get all categories
    $categories = $db->fetchAll("SELECT * FROM skill_matrix_categories ORDER BY display_order");

    echo "<h3>Skills by Category for Year 2026 (ID: $year_id):</h3>";

    foreach ($categories as $category) {
        $category_id = $category['id'];
        $category_name = $category['name'];

        echo "<h4>$category_name (ID: $category_id)</h4>";

        // Get skills for this category and year
        $skills = $db->fetchAll("SELECT * FROM skill_matrix_skills
                                WHERE year_id = ? AND category_id = ?
                                ORDER BY display_order", [$year_id, $category_id]);

        if (!empty($skills)) {
            echo "<table border='1' style='border-collapse: collapse; margin-bottom: 20px;'>";
            echo "<tr><th>Display Order</th><th>Skill Name</th><th>Description</th><th>Created</th></tr>";
            foreach ($skills as $skill) {
                echo "<tr>";
                echo "<td>" . $skill['display_order'] . "</td>";
                echo "<td>" . htmlspecialchars($skill['skill_name']) . "</td>";
                echo "<td>" . htmlspecialchars($skill['skill_description']) . "</td>";
                echo "<td>" . ($skill['created_at'] ?? 'N/A') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='color: orange;'>No skills found for this category.</p>";
        }
    }

    // Check the skills loading logic from setup_working.php
    echo "<h3>Simulate Form Loading Logic:</h3>";
    $existing_skills_by_category = [];
    $all_existing_skills = $db->fetchAll("SELECT * FROM skill_matrix_skills WHERE year_id = ? ORDER BY category_id, display_order", [$year_id]);

    foreach ($all_existing_skills as $skill) {
        $existing_skills_by_category[$skill['category_id']][] = $skill;
    }

    echo "<h4>Skills Grouped by Category (as form loads them):</h4>";
    foreach ($categories as $category) {
        $category_id = $category['id'];
        $existing_skills_for_category = $existing_skills_by_category[$category_id] ?? [];

        echo "<strong>" . htmlspecialchars($category['name']) . ":</strong><br>";
        if (!empty($existing_skills_for_category)) {
            foreach ($existing_skills_for_category as $skill) {
                echo "- Position " . $skill['display_order'] . ": " . htmlspecialchars($skill['skill_name']) . "<br>";
            }
        } else {
            echo "- No skills found<br>";
        }
        echo "<br>";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='setup_working.php?year_id=1'>Back to Setup</a></p>";
?>