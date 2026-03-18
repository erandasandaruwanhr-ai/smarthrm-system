<?php
require_once '../../config/config.php';

$db = new Database();

// Read the SQL file
$sqlFile = __DIR__ . '/create_skill_matrix_tables.sql';
$sql = file_get_contents($sqlFile);

if ($sql === false) {
    die('Error reading SQL file');
}

// Split SQL into individual statements
$statements = explode(';', $sql);

$success = true;
$errors = [];

foreach ($statements as $statement) {
    $statement = trim($statement);
    if (empty($statement) || strpos($statement, '--') === 0) {
        continue;
    }

    try {
        $db->query($statement);
        echo "✓ Executed: " . substr($statement, 0, 50) . "...\n<br>";
    } catch (Exception $e) {
        $success = false;
        $error = "Error executing statement: " . $e->getMessage();
        $errors[] = $error;
        echo "✗ " . $error . "\n<br>";
        echo "Statement: " . $statement . "\n<br><br>";
    }
}

if ($success) {
    echo "\n<br><strong>✓ All tables created successfully!</strong>";
} else {
    echo "\n<br><strong>✗ Some errors occurred during table creation.</strong>";
    foreach ($errors as $error) {
        echo "\n<br>- " . $error;
    }
}
?>