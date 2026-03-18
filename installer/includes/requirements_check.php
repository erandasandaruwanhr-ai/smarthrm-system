<?php
header('Content-Type: application/json');

/**
 * SmartHRM Installation Requirements Checker
 * Checks system requirements and returns status
 */

$requirements = [];

// PHP Version Check
$requirements['php_version'] = [
    'name' => 'PHP Version (>= 7.4)',
    'status' => version_compare(PHP_VERSION, '7.4.0', '>=') ? 'ok' : 'error',
    'message' => version_compare(PHP_VERSION, '7.4.0', '>=') ?
        'Current version: ' . PHP_VERSION :
        'Current version: ' . PHP_VERSION . '. Please upgrade to PHP 7.4 or higher.'
];

// MySQL Extension Check
$requirements['mysqli'] = [
    'name' => 'MySQL/MySQLi Extension',
    'status' => extension_loaded('mysqli') ? 'ok' : 'error',
    'message' => extension_loaded('mysqli') ?
        'MySQLi extension is available' :
        'MySQLi extension is required but not installed'
];

// PDO MySQL Extension Check
$requirements['pdo_mysql'] = [
    'name' => 'PDO MySQL Extension',
    'status' => extension_loaded('pdo_mysql') ? 'ok' : 'warning',
    'message' => extension_loaded('pdo_mysql') ?
        'PDO MySQL extension is available' :
        'PDO MySQL extension is recommended for better database performance'
];

// JSON Extension Check
$requirements['json'] = [
    'name' => 'JSON Extension',
    'status' => extension_loaded('json') ? 'ok' : 'error',
    'message' => extension_loaded('json') ?
        'JSON extension is available' :
        'JSON extension is required but not installed'
];

// Session Support Check
$requirements['session'] = [
    'name' => 'Session Support',
    'status' => function_exists('session_start') ? 'ok' : 'error',
    'message' => function_exists('session_start') ?
        'Session support is available' :
        'Session support is required but not available'
];

// File Upload Support
$requirements['file_uploads'] = [
    'name' => 'File Uploads',
    'status' => ini_get('file_uploads') ? 'ok' : 'warning',
    'message' => ini_get('file_uploads') ?
        'File uploads are enabled' :
        'File uploads are disabled - some features may not work'
];

// Memory Limit Check
$memory_limit = ini_get('memory_limit');
$memory_bytes = return_bytes($memory_limit);
$requirements['memory_limit'] = [
    'name' => 'Memory Limit (>= 128MB)',
    'status' => $memory_bytes >= 128 * 1024 * 1024 ? 'ok' : 'warning',
    'message' => "Current limit: $memory_limit" .
        ($memory_bytes < 128 * 1024 * 1024 ? ' (Recommended: 128MB or higher)' : '')
];

// Max Execution Time Check
$max_execution_time = ini_get('max_execution_time');
$requirements['max_execution_time'] = [
    'name' => 'Max Execution Time',
    'status' => $max_execution_time == 0 || $max_execution_time >= 30 ? 'ok' : 'warning',
    'message' => $max_execution_time == 0 ?
        'No time limit set' :
        "Current limit: {$max_execution_time} seconds"
];

// Directory Permissions Check
$base_dir = dirname(__DIR__, 2);
$write_dirs = [
    'uploads' => $base_dir . '/uploads',
    'logs' => $base_dir . '/logs',
    'config' => $base_dir . '/config',
];

foreach ($write_dirs as $name => $dir) {
    $dir_exists = is_dir($dir);
    $is_writable = $dir_exists ? is_writable($dir) : false;

    if (!$dir_exists) {
        // Try to create directory
        $created = @mkdir($dir, 0755, true);
        $dir_exists = $created;
        $is_writable = $created;
    }

    $requirements["writable_$name"] = [
        'name' => "Writable: /$name directory",
        'status' => $dir_exists && $is_writable ? 'ok' : 'error',
        'message' => $dir_exists && $is_writable ?
            'Directory is writable' :
            ($dir_exists ? 'Directory exists but is not writable' : 'Directory does not exist')
    ];
}

// Root Directory Write Check
$requirements['root_writable'] = [
    'name' => 'Root Directory Writable',
    'status' => is_writable($base_dir) ? 'ok' : 'warning',
    'message' => is_writable($base_dir) ?
        'Root directory is writable' :
        'Root directory is not writable - .env file creation may fail'
];

// .htaccess Support Check (if Apache)
if (function_exists('apache_get_modules')) {
    $mod_rewrite = in_array('mod_rewrite', apache_get_modules());
    $requirements['mod_rewrite'] = [
        'name' => 'Apache mod_rewrite',
        'status' => $mod_rewrite ? 'ok' : 'warning',
        'message' => $mod_rewrite ?
            'mod_rewrite is enabled' :
            'mod_rewrite is not enabled - URL rewriting may not work'
    ];
}

// cURL Extension Check
$requirements['curl'] = [
    'name' => 'cURL Extension',
    'status' => extension_loaded('curl') ? 'ok' : 'warning',
    'message' => extension_loaded('curl') ?
        'cURL extension is available' :
        'cURL extension is recommended for external API calls'
];

// GD Extension Check
$requirements['gd'] = [
    'name' => 'GD Extension',
    'status' => extension_loaded('gd') ? 'ok' : 'warning',
    'message' => extension_loaded('gd') ?
        'GD extension is available for image processing' :
        'GD extension is recommended for image processing features'
];

// OpenSSL Check
$requirements['openssl'] = [
    'name' => 'OpenSSL Extension',
    'status' => extension_loaded('openssl') ? 'ok' : 'warning',
    'message' => extension_loaded('openssl') ?
        'OpenSSL extension is available' :
        'OpenSSL extension is recommended for security features'
];

echo json_encode($requirements);

/**
 * Convert memory limit string to bytes
 */
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int) $val;

    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }

    return $val;
}
?>