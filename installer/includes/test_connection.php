<?php
header('Content-Type: application/json');

/**
 * SmartHRM Database Connection Tester
 * Tests database connection with provided credentials
 */

try {
    // Get POST data
    $db_host = $_POST['db_host'] ?? 'localhost';
    $db_port = $_POST['db_port'] ?? '3306';
    $db_name = $_POST['db_name'] ?? '';
    $db_username = $_POST['db_username'] ?? '';
    $db_password = $_POST['db_password'] ?? '';

    // Validate required fields
    if (empty($db_host) || empty($db_name) || empty($db_username)) {
        echo json_encode([
            'success' => false,
            'message' => 'Host, database name, and username are required'
        ]);
        exit;
    }

    // Create DSN
    $dsn = "mysql:host={$db_host}";
    if (!empty($db_port)) {
        $dsn .= ";port={$db_port}";
    }

    // First, test connection without database name to check credentials
    try {
        $pdo = new PDO($dsn, $db_username, $db_password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Test if we can access the database
        $dsn_with_db = $dsn . ";dbname={$db_name}";
        $pdo_db = new PDO($dsn_with_db, $db_username, $db_password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Database connection successful! Database exists and is accessible.',
            'database_exists' => true
        ]);

    } catch (PDOException $e) {
        // If database doesn't exist, try to create it
        if (strpos($e->getMessage(), 'Unknown database') !== false) {
            try {
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

                // Test the new database
                $dsn_with_db = $dsn . ";dbname={$db_name}";
                $pdo_db = new PDO($dsn_with_db, $db_username, $db_password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Database connection successful! Database created successfully.',
                    'database_exists' => false,
                    'database_created' => true
                ]);

            } catch (PDOException $create_error) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Database does not exist and could not be created: ' . $create_error->getMessage()
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Database connection failed: ' . $e->getMessage()
            ]);
        }
    }

} catch (PDOException $e) {
    // Connection failed completely
    $error_message = $e->getMessage();

    // Provide more user-friendly error messages
    if (strpos($error_message, 'Access denied') !== false) {
        $error_message = 'Access denied. Please check your username and password.';
    } elseif (strpos($error_message, "Can't connect") !== false || strpos($error_message, 'Connection refused') !== false) {
        $error_message = 'Cannot connect to database server. Please check the host and port.';
    } elseif (strpos($error_message, 'Unknown MySQL server host') !== false) {
        $error_message = 'Unknown database server host. Please check the hostname.';
    }

    echo json_encode([
        'success' => false,
        'message' => $error_message
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred: ' . $e->getMessage()
    ]);
}
?>