<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

// Get system date format
$date_format_setting = $db->fetch("SELECT setting_value FROM system_settings WHERE setting_key = 'date_format'");
$date_format = $date_format_setting['setting_value'] ?? 'Y-m-d';

// Function to format example dates
function formatExampleDate($date, $format) {
    $datetime = new DateTime($date);
    return $datetime->format($format);
}

// Check if user has permission
if (!hasModulePermission($db, 'employee.template') && !hasAdminAccess()) {
    header('Location: index.php?error=access_denied');
    exit();
}

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="employee_template.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Create file pointer
$output = fopen('php://output', 'w');

// Add CSV headers with all required fields from 2.1
$headers = [
    'EPF Number',
    'Name',
    'Designation',
    'Department',
    'NIC',
    'Birthday (YYYY-MM-DD)',
    'Joined Date (YYYY-MM-DD)',
    'Gender (Male/Female)',
    'Employment Level',
    'Location',
    'Reports To (EPF Number)'
];

fputcsv($output, $headers);

// Add sample data row for reference
$sample_data = [
    'EMP001',
    'John Doe',
    'Software Engineer',
    'IT',
    '123456789V',
    formatExampleDate('1990-01-15', $date_format), // Birthday in system format
    formatExampleDate('2020-03-01', $date_format), // Joined date in system format
    'Male',
    'Executive',
    '7C',
    'EMP002'
];

fputcsv($output, $sample_data);

// Add some helpful instructions as comments
$format_example = $date_format == 'Y-m-d' ? 'YYYY-MM-DD (e.g., 1990-01-15 for birthday, 2020-03-01 for joined date)' :
                 ($date_format == 'd/m/Y' ? 'DD/MM/YYYY (e.g., 15/01/1990 for birthday, 01/03/2020 for joined date)' :
                  'MM/DD/YYYY (e.g., 01/15/1990 for birthday, 03/01/2020 for joined date)');

$instructions = [
    '# Instructions:',
    '# 1. Fill in employee data starting from row 3',
    '# 2. EPF Number must be unique',
    "# 3. Date format: $format_example",
    '# 4. Gender: Male or Female only',
    '# 5. Employment Level: MD, GM, Manager, Assistant Manager, Senior Executive, Executive, Junior Executive, Supervisor, Staff',
    '# 6. Location: 7C, Pannala, Kobeigane, JECOE, Head Office',
    '# 7. Reports To: Leave empty if no reporting manager',
    '# 8. Delete these instruction rows before uploading',
    '# 9. Age and Service will be calculated automatically from dates',
    "# 10. IMPORTANT: Use $format_example format for ALL dates"
];

foreach ($instructions as $instruction) {
    fputcsv($output, [$instruction]);
}

fclose($output);
exit();
?>