<?php
require_once '../../config/config.php';

$db = new Database();

echo "<h2>Database Check for Skill Matrix</h2>";

try {
    // Check skill_matrix_years table
    echo "<h3>1. Years Table (skill_matrix_years):</h3>";
    $years = $db->fetchAll("SELECT * FROM skill_matrix_years ORDER BY year DESC");
    if (!empty($years)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Year</th><th>Created At</th></tr>";
        foreach ($years as $year) {
            echo "<tr>";
            echo "<td>" . $year['id'] . "</td>";
            echo "<td>" . $year['year'] . "</td>";
            echo "<td>" . ($year['created_at'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No years found in the database.</p>";
    }

    // Check skill_matrix_categories table
    echo "<h3>2. Categories Table (skill_matrix_categories):</h3>";
    $categories = $db->fetchAll("SELECT * FROM skill_matrix_categories ORDER BY display_order");
    if (!empty($categories)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Display Order</th><th>Created At</th></tr>";
        foreach ($categories as $category) {
            echo "<tr>";
            echo "<td>" . $category['id'] . "</td>";
            echo "<td>" . htmlspecialchars($category['name']) . "</td>";
            echo "<td>" . $category['display_order'] . "</td>";
            echo "<td>" . ($category['created_at'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No categories found in the database.</p>";
    }

    // Check skill_matrix_skills table
    echo "<h3>3. Skills Table (skill_matrix_skills):</h3>";
    $skills = $db->fetchAll("SELECT s.*, y.year, c.name as category_name
                            FROM skill_matrix_skills s
                            LEFT JOIN skill_matrix_years y ON s.year_id = y.id
                            LEFT JOIN skill_matrix_categories c ON s.category_id = c.id
                            ORDER BY y.year DESC, c.display_order, s.display_order");

    if (!empty($skills)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Year</th><th>Category</th><th>Skill Name</th><th>Description</th><th>Display Order</th><th>Created At</th></tr>";
        foreach ($skills as $skill) {
            echo "<tr>";
            echo "<td>" . $skill['id'] . "</td>";
            echo "<td>" . $skill['year'] . "</td>";
            echo "<td>" . htmlspecialchars($skill['category_name']) . "</td>";
            echo "<td>" . htmlspecialchars($skill['skill_name']) . "</td>";
            echo "<td>" . htmlspecialchars($skill['skill_description']) . "</td>";
            echo "<td>" . $skill['display_order'] . "</td>";
            echo "<td>" . ($skill['created_at'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No skills found in the database.</p>";
    }

    // Count skills by year and category
    echo "<h3>4. Skills Count Summary:</h3>";
    $skillCounts = $db->fetchAll("SELECT y.year, c.name as category_name, COUNT(s.id) as skill_count
                                 FROM skill_matrix_years y
                                 CROSS JOIN skill_matrix_categories c
                                 LEFT JOIN skill_matrix_skills s ON y.id = s.year_id AND c.id = s.category_id
                                 GROUP BY y.id, c.id
                                 ORDER BY y.year DESC, c.display_order");

    if (!empty($skillCounts)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Year</th><th>Category</th><th>Skills Count</th></tr>";
        foreach ($skillCounts as $count) {
            echo "<tr>";
            echo "<td>" . $count['year'] . "</td>";
            echo "<td>" . htmlspecialchars($count['category_name']) . "</td>";
            echo "<td>" . $count['skill_count'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='setup_working.php'>Back to Setup</a></p>";
?>