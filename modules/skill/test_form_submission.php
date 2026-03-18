<?php
require_once '../../config/config.php';

$db = new Database();

echo "<h2>Test Form Submission Data</h2>";

// Simulate what the form would send
echo "<h3>Expected Form Field Names for Year 2026:</h3>";

try {
    $categories = $db->fetchAll("SELECT * FROM skill_matrix_categories ORDER BY display_order");

    echo "<ul>";
    foreach ($categories as $category) {
        echo "<li><strong>" . htmlspecialchars($category['name']) . " (ID: " . $category['id'] . ")</strong><br>";
        for ($i = 1; $i <= 5; $i++) {
            echo "- category_" . $category['id'] . "_skill_" . $i . "_name<br>";
            echo "- category_" . $category['id'] . "_skill_" . $i . "_description<br>";
        }
        echo "</li><br>";
    }
    echo "</ul>";

    // Check if 2026 data exists
    echo "<h3>Skills for Year 2026:</h3>";
    $year2026 = $db->fetch("SELECT id FROM skill_matrix_years WHERE year = 2026");

    if ($year2026) {
        $year_id = $year2026['id'];
        $skills2026 = $db->fetchAll("SELECT s.*, c.name as category_name
                                    FROM skill_matrix_skills s
                                    LEFT JOIN skill_matrix_categories c ON s.category_id = c.id
                                    WHERE s.year_id = ?
                                    ORDER BY c.display_order, s.display_order", [$year_id]);

        if (!empty($skills2026)) {
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>Category</th><th>Skill Name</th><th>Description</th><th>Order</th></tr>";
            foreach ($skills2026 as $skill) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($skill['category_name']) . "</td>";
                echo "<td>" . htmlspecialchars($skill['skill_name']) . "</td>";
                echo "<td>" . htmlspecialchars($skill['skill_description']) . "</td>";
                echo "<td>" . $skill['display_order'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='color: orange;'>No skills found for year 2026. The form data may not have been submitted yet.</p>";
        }
    } else {
        echo "<p style='color: red;'>Year 2026 not found in database.</p>";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='setup_working.php'>Back to Setup</a></p>";
?>