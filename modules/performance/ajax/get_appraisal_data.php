<?php
require_once '../../../config/config.php';
require_once '../../../includes/auth_check.php';
require_once '../../../includes/performance_auth.php';

// Check if user has executive appraisal permission
if (!checkPerformancePermission('performance.executive_appraisal')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

$user = getCurrentUser();
$db = new Database();

// Get parameters
$appraisal_year = $_GET['year'] ?? '';
$appraisee_epf = $_GET['epf'] ?? '';

if (empty($appraisal_year) || empty($appraisee_epf)) {
    echo json_encode(['success' => false, 'message' => 'Year and EPF are required']);
    exit;
}

try {
    // Load existing appraisal data
    $appraisal_data = $db->fetch("
        SELECT *
        FROM executive_appraisals
        WHERE appraisal_year = ? AND appraisee_epf = ?
    ", [$appraisal_year, $appraisee_epf]);

    if ($appraisal_data) {
        // Determine if readonly based on status
        $is_readonly = in_array($appraisal_data['status'], ['submitted', 'approved']);

        echo json_encode([
            'success' => true,
            'data' => $appraisal_data,
            'readonly' => $is_readonly,
            'status' => $appraisal_data['status']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'data' => null,
            'readonly' => false,
            'status' => null
        ]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error loading appraisal data: ' . $e->getMessage()]);
}
?>