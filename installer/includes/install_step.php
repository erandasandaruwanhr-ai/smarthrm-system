<?php
header('Content-Type: application/json');

/**
 * SmartHRM Installation Step Handler
 * Processes each step of the installation
 */

try {
    // Get POST data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        throw new Exception('Invalid request data');
    }

    $step = $data['step'] ?? 0;
    $db_config = [
        'host' => $data['db_host'] ?? 'localhost',
        'port' => $data['db_port'] ?? '3306',
        'name' => $data['db_name'] ?? 'smarthrm_db',
        'username' => $data['db_username'] ?? '',
        'password' => $data['db_password'] ?? '',
    ];

    // Create database connection
    $dsn = "mysql:host={$db_config['host']}";
    if (!empty($db_config['port'])) {
        $dsn .= ";port={$db_config['port']}";
    }

    $pdo = new PDO($dsn, $db_config['username'], $db_config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $response = ['success' => true, 'message' => ''];

    switch ($step) {
        case 1:
            // Create database
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_config['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$db_config['name']}`");
            $response['message'] = 'Database created successfully';
            break;

        case 2:
            // Create tables
            $pdo->exec("USE `{$db_config['name']}`");
            $schema_sql = file_get_contents(__DIR__ . '/../sql/complete_schema.sql');

            // Remove CREATE DATABASE statements as we've already selected the database
            $schema_sql = preg_replace('/DROP DATABASE.*?;/s', '', $schema_sql);
            $schema_sql = preg_replace('/CREATE DATABASE.*?;/s', '', $schema_sql);
            $schema_sql = preg_replace('/USE.*?;/s', '', $schema_sql);

            // Execute schema
            $pdo->exec($schema_sql);
            $response['message'] = 'Database tables created successfully';
            break;

        case 3:
            // Insert core data
            $pdo->exec("USE `{$db_config['name']}`");
            $data_sql = file_get_contents(__DIR__ . '/../sql/essential_data.sql');

            // Update password if provided
            if (!empty($data['admin_password'])) {
                $password_hash = password_hash($data['admin_password'], PASSWORD_DEFAULT);
                $data_sql = str_replace(
                    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
                    $password_hash,
                    $data_sql
                );
            }

            // Update company name if provided
            if (!empty($data['company_name'])) {
                $data_sql = str_replace(
                    "'PB Pictures'",
                    "'" . addslashes($data['company_name']) . "'",
                    $data_sql
                );
            }

            $pdo->exec($data_sql);
            $response['message'] = 'Core data inserted successfully';
            break;

        case 4:
            // Set up additional permissions and configurations
            $pdo->exec("USE `{$db_config['name']}`");

            // Additional permission setup if needed
            // This step can be extended for more complex permission setup

            $response['message'] = 'Permissions configured successfully';
            break;

        case 5:
            // Finalize installation - create .env file and cleanup
            $env_content = generateEnvFile($data);

            $env_path = dirname(__DIR__, 2) . '/.env';

            // Backup existing .env if it exists
            if (file_exists($env_path)) {
                copy($env_path, $env_path . '.backup.' . date('Y-m-d-H-i-s'));
            }

            if (file_put_contents($env_path, $env_content) === false) {
                throw new Exception('Could not create .env file. Please ensure the directory is writable.');
            }

            // Create .htaccess for production
            $htaccess_source = dirname(__DIR__, 2) . '/.htaccess.hosting';
            $htaccess_dest = dirname(__DIR__, 2) . '/.htaccess';

            if (file_exists($htaccess_source)) {
                copy($htaccess_source, $htaccess_dest . '.production');
            }

            $response['message'] = 'Installation completed successfully';
            $response['next_url'] = rtrim($data['base_url'] ?? '', '/') . '/dashboard.php';
            break;

        default:
            throw new Exception('Invalid installation step');
    }

    echo json_encode($response);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Generate .env file content
 */
function generateEnvFile($data) {
    $base_url = rtrim($data['base_url'] ?? '', '/') . '/';
    $domain = parse_url($base_url, PHP_URL_HOST) ?? 'localhost';

    $env_content = <<<ENV
# SmartHRM System Environment Configuration
# Generated by Installation Wizard on {date}
# ==========================================

# Application Settings
APP_NAME="SmartHRM System"
APP_VERSION="1.0.0"
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE="Asia/Colombo"

# Base URL Configuration
BASE_URL="{$base_url}"
DOMAIN="{$domain}"

# Database Configuration
DB_CONNECTION=mysql
DB_HOST={$data['db_host']}
DB_PORT={$data['db_port']}
DB_DATABASE={$data['db_name']}
DB_USERNAME={$data['db_username']}
DB_PASSWORD={$data['db_password']}
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

# Security Settings
SESSION_TIMEOUT=3600
SESSION_NAME=SMARTHRM_SESSION
SESSION_SECURE=true
SESSION_HTTPONLY=true
SESSION_SAMESITE=Strict

# Company Settings
COMPANY_NAME="{$data['company_name']}"

# File Upload Configuration
MAX_FILE_SIZE=10485760
UPLOAD_DIR="uploads/"
ALLOWED_FILE_TYPES="pdf,jpg,jpeg,png,docx,xlsx"

# Date Format Settings
DATE_FORMAT="Y-m-d"
DATETIME_FORMAT="Y-m-d H:i:s"
DISPLAY_DATE_FORMAT="d/m/Y"
DISPLAY_DATETIME_FORMAT="d/m/Y H:i"

# Production Settings
QUERY_LOG=false
SLOW_QUERY_LOG=false
DEBUG_MODE=false
ERROR_REPORTING=0
DISPLAY_ERRORS=false

# Security Headers
SECURITY_HEADERS=true
CSRF_PROTECTION=true
XSS_PROTECTION=true

# Module Feature Flags
ENABLE_MEAL_MODULE=true
ENABLE_TRANSPORT_MODULE=true
ENABLE_GRIEVANCE_MODULE=true
ENABLE_REQUESTS_MODULE=true
ENABLE_EVENTS_MODULE=true
ENABLE_MEDICAL_MODULE=true
ENABLE_ONBOARDING_MODULE=true
ENABLE_OFFBOARDING_MODULE=true
ENABLE_TRAINING_MODULE=true
ENABLE_GOALS_MODULE=true
ENABLE_PERFORMANCE_MODULE=true
ENABLE_KEY_TALENT_MODULE=true
ENABLE_SKILL_MODULE=true

# Installation
INSTALLATION_DATE="{date}"
INSTALLATION_VERSION="1.0.0"
ENV;

    return str_replace('{date}', date('Y-m-d H:i:s'), $env_content);
}
?>