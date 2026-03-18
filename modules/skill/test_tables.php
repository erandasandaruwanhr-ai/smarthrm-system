<?php
require_once '../../config/config.php';

echo "<h3>Testing Skill Matrix Tables</h3>";

try {
    $db = new Database();
    echo "✓ Database connected<br><br>";

    // Check if tables exist and count records
    $tables = [
        'skill_matrix_years' => 'Assessment Years',
        'skill_matrix_categories' => 'Skill Categories',
        'skill_matrix_skills' => 'Skills',
        'skill_matrix_assessments' => 'Assessments'
    ];

    foreach ($tables as $table => $description) {
        try {
            $result = $db->query("SELECT COUNT(*) as count FROM $table");
            $count = $result->fetch();
            echo "✓ <strong>$description ($table)</strong>: " . $count['count'] . " records<br>";
        } catch (Exception $e) {
            echo "✗ <strong>$description ($table)</strong>: Table missing or error<br>";
            echo "&nbsp;&nbsp;Error: " . $e->getMessage() . "<br>";
        }
    }

    echo "<br><h4>Skill Categories:</h4>";
    try {
        $categories = $db->fetchAll("SELECT * FROM skill_matrix_categories ORDER BY display_order");
        if (empty($categories)) {
            echo "No categories found.<br>";
        } else {
            echo "<ul>";
            foreach ($categories as $category) {
                echo "<li>" . htmlspecialchars($category['name']) . " (Order: " . $category['display_order'] . ")</li>";
            }
            echo "</ul>";
        }
    } catch (Exception $e) {
        echo "Error loading categories: " . $e->getMessage() . "<br>";
    }

    echo "<br><h4>Assessment Years:</h4>";
    try {
        $years = $db->fetchAll("SELECT * FROM skill_matrix_years ORDER BY year DESC");
        if (empty($years)) {
            echo "No years configured yet. <a href='setup_working.php'>Add a year</a><br>";
        } else {
            echo "<ul>";
            foreach ($years as $year) {
                echo "<li>" . $year['year'] . " (ID: " . $year['id'] . ")</li>";
            }
            echo "</ul>";
        }
    } catch (Exception $e) {
        echo "Error loading years: " . $e->getMessage() . "<br>";
    }

    echo "<br><strong style='color: green;'>✅ Database test completed!</strong><br>";

} catch (Exception $e) {
    echo "<strong style='color: red;'>❌ Database Error: " . htmlspecialchars($e->getMessage()) . "</strong><br>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    padding: 20px;
    background: #f8f9fa;
}
a {
    display: inline-block;
    padding: 8px 16px;
    margin: 5px;
    text-decoration: none;
    background: #007bff;
    color: white;
    border-radius: 4px;
}
a:hover { background: #0056b3; }
</style>

<br>
<a href="setup_working.php">Setup Skills</a>
<a href="index.php">Main Dashboard</a>
<a href="create_tables_pdo.php">Recreate Tables</a>