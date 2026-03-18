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
header('Content-Disposition: attachment;filename="training_feedback_' . date('Y-m-d') . '.xls"');
header('Cache-Control: max-age=0');

// Get all training feedback with related data - using safe column selection
$feedback = $db->fetchAll("
    SELECT
        tf.*,
        tp.training_name,
        tp.training_institute,
        tp.training_cost,
        tp.training_start_date,
        tp.training_end_date
    FROM training_feedback tf
    LEFT JOIN training_plans tp ON tf.training_plan_id = tp.id
    ORDER BY tf.review_date DESC
");

// Start HTML table for Excel
echo '<table border="1">';

// Headers - based on training_feedback table structure
echo '<tr>';
echo '<th>Training Plan ID</th>';
echo '<th>Training ID</th>';
echo '<th>Training Name</th>';
echo '<th>Trainee Name</th>';
echo '<th>Evaluator Name</th>';
echo '<th>Evaluator EPF</th>';
echo '<th>Review Date</th>';
echo '<th>Training Start Date</th>';
echo '<th>Training End Date</th>';
echo '<th>Training Institute</th>';
echo '<th>Training Cost (LKR)</th>';
echo '<th>Skill Transfer Rating</th>';
echo '<th>Performance Improvement Rating</th>';
echo '<th>Knowledge Sharing Rating</th>';
echo '<th>Autonomy Rating</th>';
echo '<th>Critical Gap Closure</th>';
echo '<th>Productivity Change</th>';
echo '<th>Error Reduction</th>';
echo '<th>Status</th>';
echo '</tr>';

// Data rows
foreach ($feedback as $entry) {
    echo '<tr>';
    echo '<td>' . htmlspecialchars($entry['training_plan_id'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($entry['training_id'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($entry['training_name'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($entry['trainee_name'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($entry['evaluator_name'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($entry['evaluator_epf'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($entry['review_date'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($entry['training_start_date'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($entry['training_end_date'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($entry['training_institute'] ?? '') . '</td>';
    echo '<td>' . number_format($entry['training_cost'] ?? 0, 2) . '</td>';
    echo '<td>' . htmlspecialchars($entry['skill_transfer_rating'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($entry['performance_improvement_rating'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($entry['knowledge_sharing_rating'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($entry['autonomy_rating'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($entry['critical_gap_closure'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($entry['productivity_change'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($entry['error_reduction'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($entry['status'] ?? '') . '</td>';
    echo '</tr>';
}

echo '</table>';
?>