<?php
require_once '../../config/config.php';

$db = new Database();

echo "<h2>Debug Form Submission</h2>";

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>POST Data Received:</h3>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";

    if (isset($_POST['add_skills'])) {
        $year_id = intval($_POST['year_id']);
        echo "<h3>Processing Skills for Year ID: $year_id</h3>";

        // Get categories
        $categories = $db->fetchAll("SELECT * FROM skill_matrix_categories ORDER BY display_order");

        $added = 0;
        $debug_info = [];

        foreach ($categories as $category) {
            $category_id = $category['id'];
            $category_name = $category['name'];

            echo "<h4>Category: $category_name (ID: $category_id)</h4>";

            for ($i = 1; $i <= 5; $i++) {
                $skill_name_field = "category_{$category_id}_skill_{$i}_name";
                $skill_desc_field = "category_{$category_id}_skill_{$i}_description";

                $skill_name = trim($_POST[$skill_name_field] ?? '');
                $skill_description = trim($_POST[$skill_desc_field] ?? '');

                echo "Skill $i: <br>";
                echo "- Field name: $skill_name_field<br>";
                echo "- Skill name: '$skill_name'<br>";
                echo "- Description: '$skill_description'<br>";

                if (!empty($skill_name)) {
                    try {
                        $result = $db->query("INSERT INTO skill_matrix_skills (year_id, category_id, skill_name, skill_description, display_order) VALUES (?, ?, ?, ?, ?)",
                            [$year_id, $category_id, $skill_name, $skill_description, $i]);
                        $added++;
                        echo "<span style='color: green;'>✅ Successfully added!</span><br>";
                    } catch (Exception $e) {
                        echo "<span style='color: red;'>❌ Error: " . $e->getMessage() . "</span><br>";
                    }
                } else {
                    echo "<span style='color: orange;'>⚠️ Empty skill name, skipped</span><br>";
                }
                echo "<br>";
            }
        }

        echo "<h3>Summary: Added $added skills total</h3>";
    }
} else {
    // Show a simple form for testing
    echo "<h3>Test Form Submission</h3>";

    $categories = $db->fetchAll("SELECT * FROM skill_matrix_categories ORDER BY display_order");
    $years = $db->fetchAll("SELECT * FROM skill_matrix_years ORDER BY year DESC");

    echo "<form method='POST'>";
    echo "<label>Select Year:</label><br>";
    echo "<select name='year_id' required>";
    foreach ($years as $year) {
        echo "<option value='" . $year['id'] . "'>" . $year['year'] . "</option>";
    }
    echo "</select><br><br>";

    foreach ($categories as $category) {
        echo "<h4>" . htmlspecialchars($category['name']) . "</h4>";
        for ($i = 1; $i <= 2; $i++) { // Just 2 skills for testing
            echo "Skill $i Name: <input type='text' name='category_" . $category['id'] . "_skill_" . $i . "_name' placeholder='Test Skill $i'><br>";
            echo "Skill $i Desc: <input type='text' name='category_" . $category['id'] . "_skill_" . $i . "_description' placeholder='Test Description $i'><br><br>";
        }
    }

    echo "<button type='submit' name='add_skills'>Test Submit</button>";
    echo "</form>";
}

echo "<p><a href='setup_working.php'>Back to Setup</a></p>";
?>