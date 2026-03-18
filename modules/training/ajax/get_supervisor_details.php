<?php
require_once '../../../config/config.php';
require_once '../../../includes/auth_check.php';

header('Content-Type: application/json');

try {
    $db = new Database();

    if (!isset($_POST['trainee_name'])) {
        echo json_encode(['success' => false, 'message' => 'Trainee name is required']);
        exit;
    }

    $trainee_name = $_POST['trainee_name'];

    // Get trainee's supervisor details from employee table
    $supervisor = $db->fetch("
        SELECT
            supervisor.epf_number,
            supervisor.name,
            supervisor.designation
        FROM employees e
        LEFT JOIN employees supervisor ON e.reports_to = supervisor.epf_number
        WHERE e.name = ?
        AND e.is_active = 1
        AND supervisor.is_active = 1
        LIMIT 1
    ", [$trainee_name]);

    if ($supervisor) {
        echo json_encode([
            'success' => true,
            'supervisor' => $supervisor
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No supervisor found for this trainee'
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>