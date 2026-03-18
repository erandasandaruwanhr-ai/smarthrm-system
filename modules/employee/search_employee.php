<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

header('Content-Type: application/json');

if ($_POST && !empty($_POST['epf_number'])) {
    $db = new Database();
    $epf_number = $_POST['epf_number'];

    $employee = $db->fetch(
        "SELECT epf_number, name, designation FROM employees WHERE epf_number = ? AND is_active = 1",
        [$epf_number]
    );

    if ($employee) {
        echo json_encode([
            'success' => true,
            'employee' => $employee
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Employee not found'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'EPF number is required'
    ]);
}
?>