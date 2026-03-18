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
$filename = 'management_reviews_' . date('Y-m-d_H-i-s') . '.csv';
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
    'Training Name',
    'Training Date',
    'Trainee Name',
    'Trainee EPF',
    'Supervisor Name',
    'Supervisor EPF',
    'Review Date',
    'Supervisor Observations',
    'Further Support Required',
    'Overall Effectiveness',
    'Sign-off Date',
    'Training Cost'
]);

// Get completed managerial reviews data
$completed_reviews = $db->fetchAll("
    SELECT DISTINCT
        tf.training_id,
        tp.training_name,
        tp.training_start_date,
        tf.trainee_name,
        tp.trainee_epf,
        tmc.supervisor_name,
        tmc.supervisor_epf,
        tf.review_date,
        tmc.supervisor_observations,
        tmc.further_support_required,
        tmc.overall_effectiveness,
        tmc.sign_off_date,
        tp.training_cost
    FROM training_managerial_comments tmc
    JOIN training_feedback tf ON tmc.training_feedback_id = tf.id
    JOIN training_plans tp ON tf.training_plan_id = tp.id
    ORDER BY tmc.sign_off_date DESC
");

// Output data rows
foreach ($completed_reviews as $review) {
    fputcsv($output, [
        $review['training_id'] ?? '',
        $review['training_name'] ?? '',
        $review['training_start_date'] ?? '',
        $review['trainee_name'] ?? '',
        $review['trainee_epf'] ?? '',
        $review['supervisor_name'] ?? '',
        $review['supervisor_epf'] ?? '',
        $review['review_date'] ?? '',
        $review['supervisor_observations'] ?? '',
        $review['further_support_required'] ?? '',
        $review['overall_effectiveness'] ?? '',
        $review['sign_off_date'] ?? '',
        number_format($review['training_cost'], 2) ?? ''
    ]);
}

// Close file pointer
fclose($output);
exit;
?>