<?php
require_once '../../../config/config.php';
require_once '../../../includes/auth_check.php';

header('Content-Type: application/json');

$db = new Database();

if (!isset($_GET['epf_number']) || empty($_GET['epf_number'])) {
    echo json_encode(['success' => false, 'message' => 'EPF number is required']);
    exit;
}

$epf_number = trim($_GET['epf_number']);

try {
    // Query to get employee details with location information
    $query = "
        SELECT
            e.epf_number,
            e.name as employee_name,
            e.department,
            l.location_name as location
        FROM employees e
        LEFT JOIN locations l ON e.location_id = l.id
        WHERE e.epf_number = ?
        AND e.is_active = 1
        LIMIT 1
    ";

    $employee = $db->fetch($query, [$epf_number]);

    if ($employee) {
        echo json_encode([
            'success' => true,
            'employee' => [
                'epf_number' => $employee['epf_number'],
                'employee_name' => $employee['employee_name'],
                'location' => $employee['location'] ?: 'Not Specified',
                'department' => $employee['department'] ?: 'Not Specified'
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Employee not found with EPF number: ' . $epf_number
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>