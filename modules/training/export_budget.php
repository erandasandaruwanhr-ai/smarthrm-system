<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

// Check module permissions
if (!isSuperAdmin() && !hasModulePermission($db, 'training.view')) {
    header('Location: ../../dashboard.php');
    exit;
}

// Get year parameter
$year = $_GET['year'] ?? date('Y');

// Get requirements with budget status
$requirements_query = "
    SELECT
        tr.id,
        tr.year,
        tr.training_requirement,
        tr.training_type,
        tr.proposed_period,
        tr.epf_number,
        tr.employee_name,
        tr.location,
        tr.department,
        tr.status,
        tr.created_at,
        tb.add_to_budget,
        tb.budget_amount,
        tb.budget_approved_by,
        tb.approval_date,
        creator.name as created_by_name
    FROM training_requirements tr
    LEFT JOIN training_budget tb ON tr.id = tb.requirement_id
    LEFT JOIN employees creator ON tr.created_by = creator.epf_number
    WHERE tr.year = ?
    ORDER BY tr.created_at DESC
";

$requirements = $db->fetchAll($requirements_query, [$year]);

// Set headers for CSV download
$filename = 'training_budget_export_' . $year . '_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Create file pointer
$output = fopen('php://output', 'w');

// Add CSV headers
$headers = [
    'ID',
    'Year',
    'Training Requirement',
    'Training Type',
    'Proposed Period',
    'EPF Number',
    'Employee Name',
    'Location',
    'Department',
    'Requirement Status',
    'Add to Budget',
    'Budget Amount (LKR)',
    'Budget Approved By',
    'Budget Approval Date',
    'Created By',
    'Created Date'
];

fputcsv($output, $headers);

// Add requirements data
foreach ($requirements as $req) {
    $row = [
        $req['id'],
        $req['year'],
        $req['training_requirement'],
        ucfirst($req['training_type']),
        $req['proposed_period'],
        $req['epf_number'],
        $req['employee_name'],
        $req['location'],
        $req['department'],
        ucfirst($req['status']),
        $req['add_to_budget'] === null ? 'Pending Decision' : ($req['add_to_budget'] ? 'Yes' : 'No'),
        $req['budget_amount'] ? number_format($req['budget_amount'], 2) : '0.00',
        $req['budget_approved_by'] ?: '-',
        $req['approval_date'] ?: '-',
        $req['created_by_name'] ?: $req['created_by'],
        $req['created_at']
    ];
    fputcsv($output, $row);
}

fclose($output);
exit();
?>