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

// Set headers for CSV download
$filename = 'training_plans_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');

// Create file pointer connected to output stream
$output = fopen('php://output', 'w');

// Add BOM for proper UTF-8 encoding in Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV Headers
fputcsv($output, [
    'Training Plan ID',
    'Training Name',
    'Training Institute',
    'Trainee EPF',
    'Trainee Name',
    'Training Cost',
    'Start Date',
    'End Date',
    'Budget Exceed %',
    'Status',
    'Requirement ID',
    'Created Date',
    'Updated Date'
]);

// Get training plans data
$training_plans = $db->fetchAll("
    SELECT
        tp.id,
        tp.training_name,
        tp.training_institute,
        tp.trainee_epf,
        tp.trainee_name,
        tp.training_cost,
        tp.training_start_date,
        tp.training_end_date,
        tp.budgeted_cost_exceed_percentage,
        tp.status,
        tp.requirement_id,
        tp.created_at,
        tp.updated_at
    FROM training_plans tp
    ORDER BY tp.created_at DESC
");

// Output data rows
foreach ($training_plans as $plan) {
    fputcsv($output, [
        $plan['id'] ?? '',
        $plan['training_name'] ?? '',
        $plan['training_institute'] ?? '',
        $plan['trainee_epf'] ?? '',
        $plan['trainee_name'] ?? '',
        number_format($plan['training_cost'], 2) ?? '',
        $plan['training_start_date'] ?? '',
        $plan['training_end_date'] ?? '',
        $plan['budgeted_cost_exceed_percentage'] ? number_format($plan['budgeted_cost_exceed_percentage'], 2) . '%' : '0.00%',
        ucfirst($plan['status'] ?? ''),
        $plan['requirement_id'] ?? '',
        $plan['created_at'] ?? '',
        $plan['updated_at'] ?? ''
    ]);
}

// Close file pointer
fclose($output);
exit;
?>