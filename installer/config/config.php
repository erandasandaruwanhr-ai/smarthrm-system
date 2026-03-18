<?php
/**
 * SmartHRM Installer Configuration
 * Basic configuration for installer functionality
 */

// Installer version
define('INSTALLER_VERSION', '1.0.0');

// Required PHP version
define('MIN_PHP_VERSION', '7.4.0');

// Required MySQL version
define('MIN_MYSQL_VERSION', '5.7.0');

// Installation settings
$installer_config = [
    'database' => [
        'default_host' => 'localhost',
        'default_port' => 3306,
        'default_name' => 'smarthrm_db',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci'
    ],

    'system' => [
        'default_timezone' => 'Asia/Colombo',
        'session_name' => 'SMARTHRM_INSTALLER',
        'max_execution_time' => 300,
        'memory_limit' => '256M'
    ],

    'security' => [
        'min_password_length' => 8,
        'require_https' => false, // Set to true for production
        'allowed_hosts' => [], // Empty array allows all hosts
    ],

    'files' => [
        'max_upload_size' => '10M',
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'pdf', 'docx', 'xlsx'],
        'upload_directory' => 'uploads',
    ]
];

// Start session for installer
if (session_status() === PHP_SESSION_NONE) {
    session_name($installer_config['system']['session_name']);
    session_start();
}

// Set error reporting for installer
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set time limit for installation
set_time_limit($installer_config['system']['max_execution_time']);

// Helper functions
function getInstallerConfig($key = null) {
    global $installer_config;

    if ($key === null) {
        return $installer_config;
    }

    $keys = explode('.', $key);
    $value = $installer_config;

    foreach ($keys as $keyPart) {
        if (!isset($value[$keyPart])) {
            return null;
        }
        $value = $value[$keyPart];
    }

    return $value;
}

function isInstallationComplete() {
    $env_file = dirname(__DIR__, 2) . '/.env';
    return file_exists($env_file);
}

function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname(dirname($_SERVER['REQUEST_URI']));

    return $protocol . $host . $path . '/';
}

function formatBytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }

    return round($size, $precision) . ' ' . $units[$i];
}

function generateRandomString($length = 32) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';

    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }

    return $randomString;
}

// Redirect if installation is complete and not in development mode
if (isInstallationComplete() && !isset($_GET['force'])) {
    $base_url = getBaseUrl();
    header("Location: {$base_url}dashboard.php");
    exit;
}
?>