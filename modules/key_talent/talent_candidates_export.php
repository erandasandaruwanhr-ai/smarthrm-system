<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

$user = getCurrentUser();
$database = new Database();

// Get filter parameters
$manager_filter = isset($_GET['manager_epf']) ? trim($_GET['manager_epf']) : '';

// Get talent candidates list (same query as in talent_candidates.php)
try {
    $candidates_query = "
        WITH RECURSIVE manager_hierarchy AS (
            -- Base case: start with each employee's immediate supervisor
            SELECT
                epf_number as employee_epf,
                reports_to as current_epf,
                1 as level_depth
            FROM employees
            WHERE reports_to IS NOT NULL

            UNION ALL

            -- Recursive case: go up the chain
            SELECT
                mh.employee_epf,
                e.reports_to as current_epf,
                mh.level_depth + 1
            FROM manager_hierarchy mh
            JOIN employees e ON mh.current_epf = e.epf_number
            WHERE e.employment_level_id NOT IN (2, 3)
            AND e.reports_to IS NOT NULL
            AND mh.level_depth < 10
        ),
        ultimate_managers AS (
            SELECT
                mh.employee_epf,
                e.epf_number as manager_epf,
                e.name as manager_name,
                ROW_NUMBER() OVER (PARTITION BY mh.employee_epf ORDER BY mh.level_depth) as rn
            FROM manager_hierarchy mh
            JOIN employees e ON mh.current_epf = e.epf_number
            WHERE e.employment_level_id IN (2, 3)
        )
        SELECT
            e.epf_number,
            e.name,
            e.designation,
            e.department,
            e.location_id as location,
            e.joined_date as date_of_joining,
            TIMESTAMPDIFF(YEAR, e.joined_date, CURDATE()) as service_years,
            TIMESTAMPDIFF(MONTH, e.joined_date, CURDATE()) % 12 as service_months,
            um.manager_epf,
            um.manager_name,
            e.nic,
            e.birthday,
            e.gender,
            el.level_name as employment_level
        FROM employees e
        LEFT JOIN ultimate_managers um ON e.epf_number = um.employee_epf AND um.rn = 1
        LEFT JOIN employment_levels el ON e.employment_level_id = el.id" .
        ($manager_filter ? " WHERE um.manager_epf = ?" : "") . "
        ORDER BY CAST(e.epf_number AS UNSIGNED)
    ";

    $candidates = $database->fetchAll($candidates_query, $manager_filter ? [$manager_filter] : []);

} catch (Exception $e) {
    $candidates = [];
    $error_message = "Error loading talent candidates: " . $e->getMessage();
}

// Set headers for CSV download
$filename = 'talent_candidates_export_' . date('Y-m-d_H-i-s') . '.csv';
if ($manager_filter) {
    $filename = 'talent_candidates_manager_' . $manager_filter . '_' . date('Y-m-d_H-i-s') . '.csv';
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Create file pointer
$output = fopen('php://output', 'w');

// Add CSV headers
$headers = [
    'EPF Number',
    'Employee Name',
    'Designation',
    'Department',
    'Location',
    'Joining Date',
    'Service Years',
    'Service Months',
    'Total Service',
    'Manager EPF',
    'Manager Name',
    'NIC',
    'Birthday',
    'Gender',
    'Employment Level'
];

fputcsv($output, $headers);

// Add candidate data
foreach ($candidates as $candidate) {
    // Calculate total service display
    $years = (int)$candidate['service_years'];
    $months = (int)$candidate['service_months'];
    $total_service = '';
    if ($years > 0) {
        $total_service = $years . ' years';
        if ($months > 0) {
            $total_service .= ' ' . $months . ' months';
        }
    } else {
        $total_service = $months . ' months';
    }

    $row = [
        $candidate['epf_number'],
        $candidate['name'],
        $candidate['designation'],
        $candidate['department'],
        $candidate['location'],
        $candidate['date_of_joining'],
        $candidate['service_years'],
        $candidate['service_months'],
        $total_service,
        $candidate['manager_epf'] ?: 'No Manager',
        $candidate['manager_name'] ?: 'No Manager',
        $candidate['nic'],
        $candidate['birthday'],
        $candidate['gender'],
        $candidate['employment_level']
    ];
    fputcsv($output, $row);
}

fclose($output);
exit();
?>