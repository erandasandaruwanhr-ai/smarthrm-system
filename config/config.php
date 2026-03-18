<?php
// SmartHRM System Configuration

// Set timezone for accurate meal deadline checking
date_default_timezone_set('Asia/Colombo');

// System Settings
define('APP_NAME', 'SmartHRM System');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'https://smarthrm-system-production.up.railway.app/');

// Default credentials for first login
define('DEFAULT_PASSWORD', 'smarthrm123@@@');

// Account Types (from Plan 1.2)
define('ACCOUNT_TYPES', [
    1 => 'user',
    2 => 'supervisor',
    3 => 'manager',
    4 => 'admin',
    5 => 'superadmin'
]);

// Employment Levels (from Plan 1.5)
define('EMPLOYMENT_LEVELS', [
    1 => 'MD',
    2 => 'GM',
    3 => 'Manager',
    4 => 'Assistant Manager',
    5 => 'Senior Executive',
    6 => 'Executive',
    7 => 'Junior Executive',
    8 => 'Supervisor',
    9 => 'Staff'
]);

// Locations (from Plan 1.4)
define('LOCATIONS', [
    1 => '7C',
    2 => 'Pannala',
    3 => 'Kobeigane',
    4 => 'JECOE',
    5 => 'Head Office'
]);

// System Modules
define('MODULES', [
    1 => ['name' => 'Admin Panel', 'icon' => 'fas fa-cogs', 'url' => 'modules/admin/'],
    2 => ['name' => 'Employee Data', 'icon' => 'fas fa-users', 'url' => 'modules/employee/'],
    3 => ['name' => 'Meal Management', 'icon' => 'fas fa-utensils', 'url' => 'modules/meal/'],
    4 => ['name' => 'Transport', 'icon' => 'fas fa-car', 'url' => 'modules/transport/'],
    5 => ['name' => 'Grievance', 'icon' => 'fas fa-exclamation-triangle', 'url' => 'modules/grievance/'],
    6 => ['name' => 'Employee Requests', 'icon' => 'fas fa-file-alt', 'url' => 'modules/requests/'],
    7 => ['name' => 'Event Calendar', 'icon' => 'fas fa-calendar', 'url' => 'modules/events/'],
    8 => ['name' => 'Medical', 'icon' => 'fas fa-stethoscope', 'url' => 'modules/medical/'],
    9 => ['name' => 'Onboarding', 'icon' => 'fas fa-user-plus', 'url' => 'modules/onboarding/'],
    10 => ['name' => 'Offboarding', 'icon' => 'fas fa-user-minus', 'url' => 'modules/offboarding/'],
    11 => ['name' => 'Training', 'icon' => 'fas fa-graduation-cap', 'url' => 'modules/training/'],
    12 => ['name' => 'Goal Setting', 'icon' => 'fas fa-bullseye', 'url' => 'modules/goals/'],
    13 => ['name' => 'Performance Appraisal', 'icon' => 'fas fa-chart-line', 'url' => 'modules/performance/'],
    14 => ['name' => 'Key Talent Identification', 'icon' => 'fas fa-star', 'url' => 'modules/key_talent/'],
    15 => ['name' => 'Skill Matrix', 'icon' => 'fas fa-clipboard-list', 'url' => 'modules/skill/']
]);

// File Upload Settings
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_FILE_TYPES', ['pdf', 'jpg', 'jpeg', 'png', 'docx', 'xlsx']);
define('UPLOAD_DIR', 'uploads/');

// Date Format
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'd/m/Y');
define('DISPLAY_DATETIME_FORMAT', 'd/m/Y H:i');

// Pagination
define('RECORDS_PER_PAGE', 25);

// Session timeout (in seconds)
define('SESSION_TIMEOUT', 3600); // 1 hour

// Include database configuration
require_once 'database.php';
?>
