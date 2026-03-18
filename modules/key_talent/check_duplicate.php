<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

$user = getCurrentUser();
$database = new Database();

// Check if user is manager or superadmin
if (!in_array($user['account_type'], ['manager', 'superadmin'])) {
    echo json_encode(['error' => 'Access denied']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit();
}

$form_id = (int)($_POST['form_id'] ?? 0);
$employee_epf = $_POST['employee_epf'] ?? '';

if (!$form_id || !$employee_epf) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit();
}

try {
    // Check for duplicate assessment
    $duplicate_check = "SELECT id FROM kti_assessments WHERE form_id = ? AND manager_epf = ? AND employee_epf = ?";
    $existing_assessment = $database->fetchAll($duplicate_check, [$form_id, $user['epf_number'], $employee_epf]);

    if (!empty($existing_assessment)) {
        echo json_encode([
            'exists' => true,
            'message' => 'Assessment already completed for this employee.'
        ]);
    } else {
        echo json_encode([
            'exists' => false,
            'message' => 'Employee can be assessed.'
        ]);
    }

} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>