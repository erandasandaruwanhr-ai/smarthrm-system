<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting debug...<br>";

try {
    require_once '../../config/config.php';
    echo "✓ Config loaded<br>";

    require_once '../../includes/auth_check.php';
    echo "✓ Auth check loaded<br>";

    $user = getCurrentUser();
    echo "✓ Current user: " . htmlspecialchars($user['name']) . "<br>";

    $db = new Database();
    echo "✓ Database connected<br>";

    // Check if tables exist
    $tables = ['skill_matrix_years', 'skill_matrix_categories', 'skill_matrix_skills', 'skill_matrix_assessments'];

    foreach ($tables as $table) {
        $result = $db->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            echo "✓ Table $table exists<br>";
        } else {
            echo "✗ Table $table missing<br>";
        }
    }

    // Test queries
    $result = $db->query("SELECT COUNT(*) as count FROM skill_matrix_categories");
    $row = $result->fetch_assoc();
    echo "✓ Categories in database: " . $row['count'] . "<br>";

    $result = $db->query("SELECT * FROM skill_matrix_years ORDER BY year DESC");
    echo "✓ Years in database: " . $result->num_rows . "<br>";

    echo "<br><strong>Debug completed successfully!</strong><br>";
    echo "<a href='setup_form.php'>Try setup form again</a>";

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
}
?>