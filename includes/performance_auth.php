<?php
// Performance Module Authorization Functions
// Compatible with existing SmartHRM permission system

require_once __DIR__ . '/../config/database.php';

/**
 * Check if user has specific performance permission
 * @param string $action_name The action like 'performance.view'
 * @return bool
 */
function checkPerformancePermission($action_name) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['account_level'])) {
        return false;
    }

    try {
        $db = new Database();

        // Get user's account type
        $account_type_id = $_SESSION['account_level'];

        // Check if user has this permission
        $permission = $db->fetch("
            SELECT COUNT(*) as count
            FROM account_permissions
            WHERE account_type_id = ? AND action_name = ?
        ", [$account_type_id, $action_name]);

        return $permission && $permission['count'] > 0;
    } catch (Exception $e) {
        error_log("Performance permission check error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user has permission and redirect if not
 * @param string $action_name The action like 'performance.view'
 * @param string $redirect_url URL to redirect to if no permission (default: dashboard)
 */
function requirePerformancePermission($action_name, $redirect_url = '../../dashboard.php') {
    if (!checkPerformancePermission($action_name)) {
        header('Location: ' . $redirect_url);
        exit();
    }
}

/**
 * Get current user EPF number for performance module
 * @return string|null
 */
function getPerformanceUserEPF() {
    $user = getCurrentUser();
    return $user['epf_number'] ?? null;
}

/**
 * Check if user can appraise staff (has staff under them)
 * @return bool
 */
function canAppraiseStaff() {
    if (!checkPerformancePermission('performance.staff_appraisal')) {
        return false;
    }

    $epf_number = getPerformanceUserEPF();
    if (!$epf_number) {
        return false;
    }

    try {
        $db = new Database();

        // Check if user has any staff members reporting to them
        $staff_count = $db->fetch("
            SELECT COUNT(*) as count
            FROM employees e
            JOIN employment_levels el ON e.employment_level_id = el.id
            WHERE e.is_active = 1
            AND el.id IN (8, 9)
            AND e.supervisor_epf = ?
        ", [$epf_number]);

        return $staff_count && $staff_count['count'] > 0;
    } catch (Exception $e) {
        error_log("Can appraise staff check error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user can appraise executives (has executives under them)
 * @return bool
 */
function canAppraiseExecutives() {
    if (!checkPerformancePermission('performance.executive_appraisal')) {
        return false;
    }

    $epf_number = getPerformanceUserEPF();
    if (!$epf_number) {
        return false;
    }

    try {
        $db = new Database();

        // Check if user has any executives reporting to them
        $executive_count = $db->fetch("
            SELECT COUNT(*) as count
            FROM employees e
            JOIN employment_levels el ON e.employment_level_id = el.id
            WHERE e.is_active = 1
            AND el.id IN (3, 4, 5, 6, 7)
            AND e.supervisor_epf = ?
        ", [$epf_number]);

        return $executive_count && $executive_count['count'] > 0;
    } catch (Exception $e) {
        error_log("Can appraise executives check error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get performance module permissions for current user
 * @return array
 */
function getPerformancePermissions() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['account_level'])) {
        return [];
    }

    try {
        $db = new Database();

        $permissions = $db->fetchAll("
            SELECT action_name
            FROM account_permissions
            WHERE account_type_id = ?
            AND module_name = 'Performance Appraisal'
        ", [$_SESSION['account_level']]);

        return array_column($permissions, 'action_name');
    } catch (Exception $e) {
        error_log("Get performance permissions error: " . $e->getMessage());
        return [];
    }
}
?>