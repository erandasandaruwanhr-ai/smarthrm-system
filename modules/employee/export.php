<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

// Check if user has permission to export
requirePermission('employee', 'export_data');

// Get filter parameters (same as employee_list.php)
$location_filter = $_GET['location'] ?? '';
$department_filter = $_GET['department'] ?? '';
$gender_filter = $_GET['gender'] ?? '';
$employment_level_filter = $_GET['employment_level'] ?? '';
$status_filter = $_GET['status'] ?? 'active';
$search = $_GET['search'] ?? '';

// Build WHERE clause for filters
$where_conditions = ["1=1"];
$params = [];

// Apply location/department access control
$accessible_employees = getAccessibleEmployeesByLocation($db, $user);
$accessible_epf_numbers = array_column($accessible_employees, 'epf_number');

if (empty($accessible_epf_numbers)) {
    $where_conditions[] = "0=1"; // No access to any employees
} else {
    $epf_placeholders = str_repeat('?,', count($accessible_epf_numbers) - 1) . '?';
    $where_conditions[] = "e.epf_number IN ($epf_placeholders)";
    $params = array_merge($params, $accessible_epf_numbers);
}

// Additional filters
if ($location_filter) {
    $where_conditions[] = "e.location_id = ?";
    $params[] = $location_filter;
}

if ($department_filter) {
    $where_conditions[] = "e.department = ?";
    $params[] = $department_filter;
}

if ($gender_filter) {
    $where_conditions[] = "e.gender = ?";
    $params[] = $gender_filter;
}

if ($employment_level_filter) {
    $where_conditions[] = "e.employment_level_id = ?";
    $params[] = $employment_level_filter;
}

if ($status_filter === 'active') {
    $where_conditions[] = "e.is_active = 1";
} elseif ($status_filter === 'inactive') {
    $where_conditions[] = "e.is_active = 0";
}

if ($search) {
    $where_conditions[] = "(e.epf_number LIKE ? OR e.name LIKE ? OR e.designation LIKE ? OR e.department LIKE ? OR e.nic LIKE ?)";
    $search_term = '%' . $search . '%';
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
}

$where_clause = implode(' AND ', $where_conditions);

// Get employees data for export
$query = "
    SELECT e.epf_number,
           e.name,
           e.designation,
           e.department,
           e.nic,
           e.birthday,
           e.age,
           e.joined_date,
           e.service,
           e.gender,
           el.level_name as employment_level,
           l.location_name,
           e.reports_to,
           supervisor.name as supervisor_name,
           CASE WHEN e.is_active = 1 THEN 'Active' ELSE 'Inactive' END as status,
           e.created_at,
           e.updated_at
    FROM employees e
    LEFT JOIN locations l ON e.location_id = l.id
    LEFT JOIN employment_levels el ON e.employment_level_id = el.id
    LEFT JOIN employees supervisor ON e.reports_to = supervisor.epf_number
    WHERE $where_clause
    ORDER BY e.name ASC
";

$employees = $db->fetchAll($query, $params);

// Set headers for CSV download
$filename = 'employees_export_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Create file pointer
$output = fopen('php://output', 'w');

// Add CSV headers
$headers = [
    'EPF Number',
    'Name',
    'Designation',
    'Department',
    'NIC',
    'Birthday',
    'Age',
    'Joined Date',
    'Service',
    'Gender',
    'Employment Level',
    'Location',
    'Reports To EPF',
    'Supervisor Name',
    'Status',
    'Created At',
    'Updated At'
];

fputcsv($output, $headers);

// Add employee data
foreach ($employees as $employee) {
    $row = [
        $employee['epf_number'],
        $employee['name'],
        $employee['designation'],
        $employee['department'],
        $employee['nic'],
        $employee['birthday'],
        $employee['age'],
        $employee['joined_date'],
        $employee['service'],
        $employee['gender'],
        $employee['employment_level'],
        $employee['location_name'],
        $employee['reports_to'],
        $employee['supervisor_name'],
        $employee['status'],
        $employee['created_at'],
        $employee['updated_at']
    ];
    fputcsv($output, $row);
}

fclose($output);
exit();
?>