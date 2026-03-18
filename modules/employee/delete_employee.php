<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

// Check if user has permission to delete employees
requirePermission('employee', 'delete');

$employee_id = $_GET['id'] ?? null;

if ($employee_id) {
    try {
        // Check if employee exists
        $employee = $db->fetch("SELECT * FROM employees WHERE id = ?", [$employee_id]);
        if (!$employee) {
            throw new Exception("Employee not found.");
        }

        // Check if this employee is referenced by others as "reports_to"
        $references = $db->fetch("SELECT COUNT(*) as count FROM employees WHERE reports_to = ?", [$employee['epf_number']]);
        if ($references['count'] > 0) {
            throw new Exception("Cannot delete this employee as they are a reporting manager for other employees. Please reassign reporting relationships first.");
        }

        // Soft delete the employee
        $db->update('employees',
            ['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$employee_id]
        );

        header('Location: employee_list.php?message=Employee deleted successfully');
        exit();

    } catch (Exception $e) {
        header('Location: employee_list.php?error=' . urlencode($e->getMessage()));
        exit();
    }
} else {
    header('Location: employee_list.php?error=Invalid employee ID');
    exit();
}
?>