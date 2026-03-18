<?php
// Test database connection without auth
require_once '../../config/config.php';

echo "<h3>Database Test</h3>";

try {
    $db = new Database();
    echo "✓ Database connected<br>";

    // Check if tables exist
    $tables = ['skill_matrix_years', 'skill_matrix_categories', 'skill_matrix_skills', 'skill_matrix_assessments'];

    foreach ($tables as $table) {
        $result = $db->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            echo "✓ Table $table exists<br>";

            // Count records
            $countResult = $db->query("SELECT COUNT(*) as count FROM $table");
            $count = $countResult->fetch_assoc();
            echo "&nbsp;&nbsp;→ Records: " . $count['count'] . "<br>";
        } else {
            echo "✗ Table $table missing<br>";
        }
    }

    echo "<br><h4>Test Queries</h4>";

    // Test categories
    $result = $db->query("SELECT * FROM skill_matrix_categories ORDER BY display_order");
    echo "<strong>Categories:</strong><br>";
    while ($row = $result->fetch_assoc()) {
        echo "- " . htmlspecialchars($row['name']) . " (Order: " . $row['display_order'] . ")<br>";
    }

    echo "<br><strong>Years:</strong><br>";
    $result = $db->query("SELECT * FROM skill_matrix_years ORDER BY year DESC");
    if ($result->num_rows == 0) {
        echo "No years added yet.<br>";
    } else {
        while ($row = $result->fetch_assoc()) {
            echo "- " . $row['year'] . " (ID: " . $row['id'] . ")<br>";
        }
    }

    echo "<br><strong style='color: green;'>✅ Database test completed successfully!</strong><br>";
    echo "<br><strong>Next steps:</strong><br>";
    echo "1. <a href='setup_simple.php'>Try Simple Setup Form</a><br>";
    echo "2. <a href='index.php'>Go to Main Dashboard</a><br>";

} catch (Exception $e) {
    echo "<strong style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</strong><br>";
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