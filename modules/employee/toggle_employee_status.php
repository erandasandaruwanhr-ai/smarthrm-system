<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

// Check if user has permission to modify employees
if (!isAdmin() && !isManager()) {
    header('Location: employee_list.php?error=Access denied');
    exit;
}

$db = new Database();
$id = $_GET['id'] ?? null;
$status = $_GET['status'] ?? null;

if (!$id || !in_array($status, ['0', '1'])) {
    header('Location: employee_list.php?error=Invalid request');
    exit;
}

try {
    // Get employee info for logging
    $employee = $db->fetch("SELECT epf_number, name, is_active FROM employees WHERE id = ?", [$id]);

    if (!$employee) {
        header('Location: employee_list.php?error=Employee not found');
        exit;
    }

    // Update employee status
    $db->execute("UPDATE employees SET is_active = ? WHERE id = ?", [$status, $id]);

    // Log the action
    $action = $status === '1' ? 'activated' : 'made inactive';
    $user = getCurrentUser();

    // You can add audit logging here if needed
    // logActivity($user['id'], "Employee {$action}", "Employee {$employee['epf_number']} - {$employee['name']} was {$action}");

    $message = "Employee " . htmlspecialchars($employee['name']) . " has been successfully {$action}";
    header("Location: employee_list.php?message=" . urlencode($message));
    exit;

} catch (Exception $e) {
    error_log("Error toggling employee status: " . $e->getMessage());
    header('Location: employee_list.php?error=An error occurred while updating employee status');
    exit;
}
?>