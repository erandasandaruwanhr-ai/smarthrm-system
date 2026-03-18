<?php
require_once '../../../config/config.php';
require_once '../../../includes/auth_check.php';

header('Content-Type: application/json');

$db = new Database();

if (!isset($_GET['evaluation_id']) || empty($_GET['evaluation_id'])) {
    echo json_encode(['success' => false, 'message' => 'Evaluation ID is required']);
    exit;
}

$evaluation_id = trim($_GET['evaluation_id']);

try {
    // Query to get evaluation details
    $query = "
        SELECT
            te.*,
            tp.training_cost,
            tp.status as plan_status,
            creator.name as submitted_by_name
        FROM training_evaluations te
        LEFT JOIN training_plans tp ON te.training_plan_id = tp.id
        LEFT JOIN employees creator ON te.submitted_by = creator.epf_number
        WHERE te.id = ?
        LIMIT 1
    ";

    $evaluation = $db->fetch($query, [$evaluation_id]);

    if ($evaluation) {
        // Use submitted_by_name if available, otherwise fall back to submitted_by EPF
        $evaluation['submitted_by'] = $evaluation['submitted_by_name'] ?: $evaluation['submitted_by'];

        echo json_encode([
            'success' => true,
            'evaluation' => $evaluation
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Evaluation not found with ID: ' . $evaluation_id
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>