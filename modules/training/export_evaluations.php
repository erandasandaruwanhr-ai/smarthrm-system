<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

// Check permissions
$db = new Database();
if (!isSuperAdmin() && !hasModulePermission($db, 'training.view')) {
    header('Location: ../../dashboard.php');
    exit;
}

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="training_evaluations_' . date('Y-m-d') . '.xls"');
header('Cache-Control: max-age=0');

// Get all training evaluations with related data - using safe column selection
$evaluations = $db->fetchAll("
    SELECT
        te.*,
        tp.training_cost
    FROM training_evaluations te
    LEFT JOIN training_plans tp ON te.training_plan_id = tp.id
    ORDER BY te.training_plan_id DESC
");

// Start HTML table for Excel
echo '<table border="1">';

// Headers - simplified to essential fields
echo '<tr>';
echo '<th>Training Plan ID</th>';
echo '<th>Training ID</th>';
echo '<th>Training Name</th>';
echo '<th>Trainee Name</th>';
echo '<th>Trainee EPF</th>';
echo '<th>Training Start Date</th>';
echo '<th>Training End Date</th>';
echo '<th>Training Institute</th>';
echo '<th>Training Cost (LKR)</th>';
echo '<th>Immediate Application Rating</th>';
echo '<th>Performance Improvement Rating</th>';
echo '<th>Recommend to Colleague Rating</th>';
echo '<th>Most Valuable Part</th>';
echo '<th>Areas for Improvement</th>';
echo '<th>Additional Comments</th>';
echo '<th>Submitted By</th>';
echo '</tr>';

// Data rows - simplified to match headers
foreach ($evaluations as $evaluation) {
    echo '<tr>';
    echo '<td>' . htmlspecialchars($evaluation['training_plan_id'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($evaluation['training_id'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($evaluation['training_name'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($evaluation['trainee_name'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($evaluation['trainee_epf'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($evaluation['training_start_date'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($evaluation['training_end_date'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($evaluation['training_institute'] ?? '') . '</td>';
    echo '<td>' . number_format($evaluation['training_cost'] ?? 0, 2) . '</td>';
    echo '<td>' . htmlspecialchars($evaluation['immediate_application_rating'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($evaluation['performance_improvement_rating'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($evaluation['recommend_to_colleague_rating'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($evaluation['most_valuable_part'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($evaluation['areas_for_improvement'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($evaluation['additional_comments'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($evaluation['submitted_by'] ?? '') . '</td>';
    echo '</tr>';
}

echo '</table>';
?>