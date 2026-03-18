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
$department = $_GET['department'] ?? '';
$location = $_GET['location'] ?? '';

// Build filter conditions
$where_conditions = ["tr.year = ?"];
$params = [$year];

if ($department) {
    $where_conditions[] = "tr.department = ?";
    $params[] = $department;
}

if ($location) {
    $where_conditions[] = "tr.location = ?";
    $params[] = $location;
}

$where_clause = implode(' AND ', $where_conditions);

// Set headers for CSV download
$filename = 'comprehensive_training_report_' . $year .
           ($department ? '_' . str_replace(' ', '_', $department) : '') .
           ($location ? '_' . str_replace(' ', '_', $location) : '') .
           '_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');

// Create file pointer connected to output stream
$output = fopen('php://output', 'w');

// Add BOM for proper UTF-8 encoding in Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write summary header
fputcsv($output, ['COMPREHENSIVE TRAINING REPORT - ' . $year]);
fputcsv($output, ['Generated on: ' . date('Y-m-d H:i:s')]);
if ($department) fputcsv($output, ['Department: ' . $department]);
if ($location) fputcsv($output, ['Location: ' . $location]);
fputcsv($output, []); // Empty row

// Get and write summary statistics
$summary_stats = $db->fetch("
    SELECT
        COUNT(DISTINCT tr.id) as total_requirements,
        COUNT(DISTINCT tb.id) as budgeted_items,
        SUM(tb.budget_amount) as total_budget_allocated,
        COUNT(DISTINCT tp.id) as total_plans,
        SUM(tp.training_cost) as total_actual_cost,
        COUNT(DISTINCT te.id) as evaluations_completed,
        COUNT(DISTINCT tf.id) as feedback_completed,
        COUNT(DISTINCT tmc.id) as management_reviews,
        AVG(CASE WHEN te.immediate_application_rating > 0 THEN
            (te.immediate_application_rating + te.performance_improvement_rating + te.recommend_to_colleague_rating) / 3
            ELSE NULL END) as avg_trainee_rating,
        AVG(CASE WHEN tf.skill_transfer_rating > 0 THEN
            (tf.skill_transfer_rating + tf.performance_improvement_rating + tf.knowledge_sharing_rating + tf.autonomy_rating) / 4
            ELSE NULL END) as avg_supervisor_rating
    FROM training_requirements tr
    LEFT JOIN training_budget tb ON tr.id = tb.requirement_id AND tb.add_to_budget = 1
    LEFT JOIN training_plans tp ON tr.id = tp.requirement_id
    LEFT JOIN training_evaluations te ON tp.id = te.training_plan_id
    LEFT JOIN training_feedback tf ON tp.id = tf.training_plan_id
    LEFT JOIN training_managerial_comments tmc ON tf.id = tmc.training_feedback_id
    WHERE $where_clause
", $params);

// Write summary section
fputcsv($output, ['SUMMARY STATISTICS']);
fputcsv($output, ['Metric', 'Value']);
fputcsv($output, ['Total Training Requirements', $summary_stats['total_requirements'] ?? 0]);
fputcsv($output, ['Budgeted Items', $summary_stats['budgeted_items'] ?? 0]);
fputcsv($output, ['Total Budget Allocated', 'Rs. ' . number_format($summary_stats['total_budget_allocated'] ?? 0, 2)]);
fputcsv($output, ['Total Training Plans', $summary_stats['total_plans'] ?? 0]);
fputcsv($output, ['Total Actual Cost', 'Rs. ' . number_format($summary_stats['total_actual_cost'] ?? 0, 2)]);
fputcsv($output, ['Evaluations Completed', $summary_stats['evaluations_completed'] ?? 0]);
fputcsv($output, ['Feedback Completed', $summary_stats['feedback_completed'] ?? 0]);
fputcsv($output, ['Management Reviews', $summary_stats['management_reviews'] ?? 0]);
fputcsv($output, ['Average Trainee Rating', number_format($summary_stats['avg_trainee_rating'] ?? 0, 2) . '/5']);
fputcsv($output, ['Average Supervisor Rating', number_format($summary_stats['avg_supervisor_rating'] ?? 0, 2) . '/5']);

$budget_utilization = 0;
if (($summary_stats['total_budget_allocated'] ?? 0) > 0) {
    $budget_utilization = (($summary_stats['total_actual_cost'] ?? 0) / $summary_stats['total_budget_allocated']) * 100;
}
fputcsv($output, ['Budget Utilization', number_format($budget_utilization, 2) . '%']);
fputcsv($output, []); // Empty row

// Training by Type
fputcsv($output, ['TRAINING BY TYPE']);
fputcsv($output, ['Training Type', 'Count', 'Total Cost', 'Average Rating']);

$training_by_type = $db->fetchAll("
    SELECT
        tr.training_type,
        COUNT(*) as count,
        SUM(tp.training_cost) as total_cost,
        AVG(CASE WHEN te.immediate_application_rating > 0 THEN
            (te.immediate_application_rating + te.performance_improvement_rating + te.recommend_to_colleague_rating) / 3
            ELSE NULL END) as avg_rating
    FROM training_requirements tr
    JOIN training_plans tp ON tr.id = tp.requirement_id
    LEFT JOIN training_evaluations te ON tp.id = te.training_plan_id
    WHERE $where_clause
    GROUP BY tr.training_type
    ORDER BY total_cost DESC
", $params);

foreach ($training_by_type as $type) {
    fputcsv($output, [
        ucfirst($type['training_type']),
        $type['count'],
        'Rs. ' . number_format($type['total_cost'] ?? 0, 2),
        number_format($type['avg_rating'] ?? 0, 2) . '/5'
    ]);
}
fputcsv($output, []); // Empty row

// Detailed Training Records
fputcsv($output, ['DETAILED TRAINING RECORDS']);
fputcsv($output, [
    'Training ID',
    'Training Name',
    'Training Type',
    'Trainee Name',
    'Trainee EPF',
    'Department',
    'Location',
    'Training Institute',
    'Start Date',
    'End Date',
    'Training Cost',
    'Status',
    'Evaluation Rating',
    'Supervisor Rating',
    'Overall Effectiveness'
]);

// Get detailed records
$detailed_records = $db->fetchAll("
    SELECT
        COALESCE(tp.id, tr.id) as training_ref,
        COALESCE(tf.training_id, CONCAT('REQ-', tr.id)) as training_id,
        tp.training_name,
        tr.training_type,
        tr.employee_name as trainee_name,
        tr.epf_number as trainee_epf,
        tr.department,
        tr.location,
        tp.training_institute,
        tp.training_start_date,
        tp.training_end_date,
        tp.training_cost,
        COALESCE(tp.status, tr.status) as status,
        ROUND((te.immediate_application_rating + te.performance_improvement_rating + te.recommend_to_colleague_rating) / 3, 2) as eval_rating,
        ROUND((tf.skill_transfer_rating + tf.performance_improvement_rating + tf.knowledge_sharing_rating + tf.autonomy_rating) / 4, 2) as supervisor_rating,
        tmc.overall_effectiveness
    FROM training_requirements tr
    LEFT JOIN training_plans tp ON tr.id = tp.requirement_id
    LEFT JOIN training_evaluations te ON tp.id = te.training_plan_id
    LEFT JOIN training_feedback tf ON tp.id = tf.training_plan_id
    LEFT JOIN training_managerial_comments tmc ON tf.id = tmc.training_feedback_id
    WHERE $where_clause
    ORDER BY tr.created_at DESC
", $params);

foreach ($detailed_records as $record) {
    fputcsv($output, [
        $record['training_id'] ?? '',
        $record['training_name'] ?? $record['training_requirement'] ?? '',
        ucfirst($record['training_type'] ?? ''),
        $record['trainee_name'] ?? '',
        $record['trainee_epf'] ?? '',
        $record['department'] ?? '',
        $record['location'] ?? '',
        $record['training_institute'] ?? '',
        $record['training_start_date'] ?? '',
        $record['training_end_date'] ?? '',
        $record['training_cost'] ? 'Rs. ' . number_format($record['training_cost'], 2) : '',
        ucfirst($record['status'] ?? ''),
        $record['eval_rating'] ? $record['eval_rating'] . '/5' : '',
        $record['supervisor_rating'] ? $record['supervisor_rating'] . '/5' : '',
        $record['overall_effectiveness'] ?? ''
    ]);
}

// Close file pointer
fclose($output);
exit;
?>