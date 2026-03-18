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

// Get filter parameters
$year = $_GET['year'] ?? date('Y');
$status = $_GET['status'] ?? '';

// Set headers for CSV download
$filename = 'training_tracker_' . $year . ($status ? '_' . $status : '') . '_' . date('Y-m-d_H-i-s') . '.csv';
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
    'Training ID',
    'Trainee Name',
    'Trainee EPF',
    'Training Title',
    'Training Institute',
    'Training Date',
    'Training Cost',
    'Status',
    'Evaluation Submitted',
    'Feedback Submitted',
    'Management Comments Submitted',
    'Created Date',
    'Updated Date'
]);

// Build WHERE clause for filters
$where_conditions = ["YEAR(tp.training_start_date) = ?"];
$params = [$year];

if (!empty($status)) {
    $where_conditions[] = "tt.status = ?";
    $params[] = $status;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Get training tracker data
$tracker_data = $db->fetchAll("
    SELECT
        tt.training_id,
        tt.trainee_name,
        tp.trainee_epf,
        tt.training_title,
        tp.training_institute,
        tt.training_date,
        tp.training_cost,
        tt.status,
        tt.evaluation_submitted,
        tt.feedback_submitted,
        tt.managerial_comments_submitted,
        tt.created_at,
        tt.updated_at
    FROM training_tracker tt
    JOIN training_plans tp ON tt.training_plan_id = tp.id
    {$where_clause}
    ORDER BY tt.training_date DESC
", $params);

// Output data rows
foreach ($tracker_data as $track) {
    fputcsv($output, [
        $track['training_id'] ?? '',
        $track['trainee_name'] ?? '',
        $track['trainee_epf'] ?? '',
        $track['training_title'] ?? '',
        $track['training_institute'] ?? '',
        $track['training_date'] ?? '',
        number_format($track['training_cost'], 2) ?? '',
        ucfirst($track['status'] ?? ''),
        $track['evaluation_submitted'] ?? '',
        $track['feedback_submitted'] ?? '',
        $track['managerial_comments_submitted'] ?? '',
        $track['created_at'] ?? '',
        $track['updated_at'] ?? ''
    ]);
}

// Close file pointer
fclose($output);
exit;
?>