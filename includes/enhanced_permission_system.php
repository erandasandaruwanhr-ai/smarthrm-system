<?php
/**
 * Enhanced Permission Management System
 * Supports hierarchical permissions based on account types:
 * - User: Self data only
 * - Supervisor: Self + direct reports
 * - Manager: Self + department + all reports
 * - Admin: Self + location + all departments
 * - Superadmin: All data across all locations and departments
 */

require_once __DIR__ . '/location_access.php';

/**
 * Data Access Levels based on Account Types
 */
const DATA_ACCESS_LEVELS = [
    1 => 'SELF',           // User - own data only
    2 => 'REPORTS',        // Supervisor - self + direct reports
    3 => 'DEPARTMENT',     // Manager - self + department + all reports
    4 => 'LOCATION',       // Admin - self + location + all departments
    5 => 'ALL'             // Superadmin - all data across all locations
];

/**
 * Check if user can view specific employee data
 * @param Database $db
 * @param string $target_epf_number EPF number of the employee whose data is being accessed
 * @return bool
 */
function canViewEmployeeDataEnhanced($db, $target_epf_number) {
    $user = function_exists('getCurrentUser') ? getCurrentUser() : null;
    if (!$user) {
        return false; // No user context, deny access
    }
    $user_epf = $user['epf_number'] ?? '';
    $account_level = $user['account_level'] ?? 1;

    // Superadmin can view all
    if ($account_level >= 5) {
        return true;
    }

    // Self access
    if ($target_epf_number === $user_epf) {
        return true;
    }

    // Admin - location based access
    if ($account_level >= 4) {
        return canAccessEmployeeByLocationEnhanced($db, $user, $target_epf_number);
    }

    // Manager - department based access
    if ($account_level >= 3) {
        return canAccessEmployeeByDepartmentEnhanced($db, $user, $target_epf_number);
    }

    // Supervisor - reporting relationship
    if ($account_level >= 2) {
        return canAccessEmployeeByReportingEnhanced($db, $user, $target_epf_number);
    }

    return false;
}

/**
 * Check if user can edit specific employee data
 * @param Database $db
 * @param string $target_epf_number
 * @return bool
 */
function canEditEmployeeDataEnhanced($db, $target_epf_number) {
    $user = function_exists('getCurrentUser') ? getCurrentUser() : null;
    if (!$user) {
        return false; // No user context, deny access
    }
    $user_epf = $user['epf_number'] ?? '';
    $account_level = $user['account_level'] ?? 1;

    // Users can only edit their own data
    if ($account_level == 1) {
        return $target_epf_number === $user_epf && hasModulePermission($db, 'employee.edit_own');
    }

    // Check view permission first, then check specific edit permissions
    if (!canViewEmployeeData($db, $target_epf_number)) {
        return false;
    }

    // Check module-specific edit permissions
    if ($account_level >= 4 && hasModulePermission($db, 'employee.edit_location')) {
        return true;
    }

    if ($account_level >= 3 && hasModulePermission($db, 'employee.edit_department')) {
        return true;
    }

    if ($account_level >= 2 && hasModulePermission($db, 'employee.edit_reports')) {
        return true;
    }

    return false;
}

/**
 * Check if user can delete specific employee data
 * @param Database $db
 * @param string $target_epf_number
 * @return bool
 */
function canDeleteEmployeeDataEnhanced($db, $target_epf_number) {
    $user = function_exists('getCurrentUser') ? getCurrentUser() : null;
    if (!$user) {
        return false; // No user context, deny access
    }
    $account_level = $user['account_level'] ?? 1;

    // Users cannot delete data
    if ($account_level < 3) {
        return false;
    }

    // Check view permission first
    if (!canViewEmployeeDataEnhanced($db, $target_epf_number)) {
        return false;
    }

    // Check delete permissions
    return hasModulePermission($db, 'employee.delete');
}

/**
 * Check if user can submit requests on behalf of employee
 * @param Database $db
 * @param string $target_epf_number
 * @param string $request_type
 * @return bool
 */
function canSubmitRequestFor($db, $target_epf_number, $request_type = '') {
    $user = function_exists('getCurrentUser') ? getCurrentUser() : null;
    if (!$user) {
        return false;
    }
    $user_epf = $user['epf_number'] ?? '';
    $account_level = $user['account_level'] ?? 1;

    // Self submission
    if ($target_epf_number === $user_epf) {
        return hasModulePermission($db, "requests.submit") ||
               hasModulePermission($db, "meal.employee_request.submit") ||
               hasModulePermission($db, "transport.request.submit");
    }

    // Only supervisors and above can submit for others
    if ($account_level < 2) {
        return false;
    }

    // Check if can access the employee data
    if (!canViewEmployeeDataEnhanced($db, $target_epf_number)) {
        return false;
    }

    return hasModulePermission($db, "requests.submit_for_others");
}

/**
 * Check if user can approve requests
 * @param Database $db
 * @param string $requester_epf_number
 * @param string $request_type
 * @return bool
 */
function canApproveRequest($db, $requester_epf_number, $request_type = '') {
    $user = function_exists('getCurrentUser') ? getCurrentUser() : null;
    if (!$user) {
        return false;
    }
    $account_level = $user['account_level'] ?? 1;

    // Users cannot approve
    if ($account_level < 2) {
        return false;
    }

    // Check if can access the requester's data
    if (!canViewEmployeeDataEnhanced($db, $requester_epf_number)) {
        return false;
    }

    // Check approval permissions based on level
    if ($account_level >= 4 && hasModulePermission($db, 'requests.approval.admin')) {
        return true;
    }

    if ($account_level >= 3 && hasModulePermission($db, 'requests.approval.manager')) {
        return true;
    }

    if ($account_level >= 2 && hasModulePermission($db, 'requests.approval.supervisor')) {
        return true;
    }

    return false;
}

/**
 * Get accessible employees for current user
 * @param Database $db
 * @param array $filters Optional filters
 * @return array
 */
function getAccessibleEmployees($db, $filters = []) {
    $user = function_exists('getCurrentUser') ? getCurrentUser() : null;
    if (!$user) {
        return [];
    }
    $account_level = $user['account_level'] ?? 1;
    $user_epf = $user['epf_number'] ?? '';

    $where_conditions = ["e.is_active = 1"];
    $params = [];

    // Apply data access restrictions
    switch ($account_level) {
        case 1: // User - self only
            $where_conditions[] = "e.epf_number = ?";
            $params[] = $user_epf;
            break;

        case 2: // Supervisor - self + direct reports
            $where_conditions[] = "(e.epf_number = ? OR e.reports_to = ?)";
            $params[] = $user_epf;
            $params[] = $user_epf;
            break;

        case 3: // Manager - self + department + all reports in hierarchy
            $reporting_hierarchy = getReportingHierarchy($db, $user_epf);
            $all_epf_numbers = array_merge([$user_epf], $reporting_hierarchy);

            // Also include department colleagues
            $user_department = $user['department'] ?? '';
            if ($user_department) {
                $where_conditions[] = "(e.epf_number IN (" . str_repeat('?,', count($all_epf_numbers) - 1) . "?) OR e.department = ?)";
                $params = array_merge($params, $all_epf_numbers, [$user_department]);
            } else {
                $where_conditions[] = "e.epf_number IN (" . str_repeat('?,', count($all_epf_numbers) - 1) . "?)";
                $params = array_merge($params, $all_epf_numbers);
            }
            break;

        case 4: // Admin - location based
            $user_location_id = $user['location_id'] ?? 0;
            if ($user_location_id) {
                $where_conditions[] = "e.location_id = ?";
                $params[] = $user_location_id;
            }
            break;

        case 5: // Superadmin - all employees
            // No additional restrictions
            break;
    }

    // Apply additional filters
    if (!empty($filters['location_id'])) {
        $where_conditions[] = "e.location_id = ?";
        $params[] = $filters['location_id'];
    }

    if (!empty($filters['department'])) {
        $where_conditions[] = "e.department = ?";
        $params[] = $filters['department'];
    }

    if (!empty($filters['employment_level_id'])) {
        $where_conditions[] = "e.employment_level_id = ?";
        $params[] = $filters['employment_level_id'];
    }

    $where_clause = implode(' AND ', $where_conditions);

    $query = "
        SELECT e.*,
               l.location_name,
               el.level_name as employment_level_name,
               supervisor.name as supervisor_name
        FROM employees e
        LEFT JOIN locations l ON e.location_id = l.id
        LEFT JOIN employment_levels el ON e.employment_level_id = el.id
        LEFT JOIN employees supervisor ON e.reports_to = supervisor.epf_number
        WHERE $where_clause
        ORDER BY e.name ASC
    ";

    return $db->fetchAll($query, $params);
}

/**
 * Get reporting hierarchy for a manager
 * @param Database $db
 * @param string $manager_epf
 * @param array $visited
 * @return array
 */
function getReportingHierarchy($db, $manager_epf, $visited = []) {
    // Prevent infinite loops
    if (in_array($manager_epf, $visited)) {
        return [];
    }

    $visited[] = $manager_epf;
    $hierarchy = [];

    // Get direct reports
    $direct_reports = $db->fetchAll(
        "SELECT epf_number FROM employees WHERE reports_to = ? AND is_active = 1",
        [$manager_epf]
    );

    foreach ($direct_reports as $report) {
        $epf = $report['epf_number'];
        $hierarchy[] = $epf;

        // Recursively get their reports
        $sub_hierarchy = getReportingHierarchy($db, $epf, $visited);
        $hierarchy = array_merge($hierarchy, $sub_hierarchy);
    }

    return array_unique($hierarchy);
}

/**
 * Check location-based access for admin users
 */
function canAccessEmployeeByLocationEnhanced($db, $user, $target_epf_number) {
    $user_location_id = $user['location_id'] ?? 0;

    if (!$user_location_id) {
        return false;
    }

    $target_employee = $db->fetch(
        "SELECT location_id FROM employees WHERE epf_number = ?",
        [$target_epf_number]
    );

    return $target_employee && $target_employee['location_id'] == $user_location_id;
}

/**
 * Check department-based access for manager users
 */
function canAccessEmployeeByDepartmentEnhanced($db, $user, $target_epf_number) {
    $user_department = $user['department'] ?? '';
    $user_epf = $user['epf_number'] ?? '';

    if (!$user_department) {
        return false;
    }

    // Check if target employee is in same department
    $target_employee = $db->fetch(
        "SELECT department FROM employees WHERE epf_number = ?",
        [$target_epf_number]
    );

    if ($target_employee && $target_employee['department'] === $user_department) {
        return true;
    }

    // Check if target employee reports to this manager (directly or indirectly)
    $reporting_hierarchy = getReportingHierarchy($db, $user_epf);
    return in_array($target_epf_number, $reporting_hierarchy);
}

/**
 * Check reporting relationship access for supervisor users
 */
function canAccessEmployeeByReportingEnhanced($db, $user, $target_epf_number) {
    $user_epf = $user['epf_number'] ?? '';

    // Check if target employee directly reports to this supervisor
    $target_employee = $db->fetch(
        "SELECT reports_to FROM employees WHERE epf_number = ?",
        [$target_epf_number]
    );

    return $target_employee && $target_employee['reports_to'] === $user_epf;
}

/**
 * Enhanced permission checking with data access control
 * @param Database $db
 * @param string $permission
 * @param array $context
 * @return bool
 */
function hasEnhancedPermission($db, $permission, $context = []) {
    // First check if user has the module permission
    if (!hasModulePermission($db, $permission)) {
        return false;
    }

    // If no specific context is provided, return true
    if (empty($context)) {
        return true;
    }

    // Check data access permissions based on context
    if (isset($context['target_epf_number'])) {
        $target_epf = $context['target_epf_number'];

        // Determine the required access level based on permission type
        if (strpos($permission, '.edit') !== false || strpos($permission, '.delete') !== false) {
            return canEditEmployeeData($db, $target_epf);
        } else {
            return canViewEmployeeData($db, $target_epf);
        }
    }

    return true;
}

/**
 * Get accessible locations for current user
 * @param Database $db
 * @return array
 */
function getAccessibleLocationsEnhanced($db) {
    $user = function_exists('getCurrentUser') ? getCurrentUser() : null;
    if (!$user) {
        return [];
    }
    $account_level = $user['account_level'] ?? 1;

    if ($account_level >= 5) {
        // Superadmin - all locations
        return $db->fetchAll("SELECT * FROM locations WHERE is_active = 1 ORDER BY location_name");
    } elseif ($account_level >= 4) {
        // Admin - own location
        $user_location_id = $user['location_id'] ?? 0;
        if ($user_location_id) {
            return $db->fetchAll(
                "SELECT * FROM locations WHERE id = ? AND is_active = 1",
                [$user_location_id]
            );
        }
    }

    return [];
}

/**
 * Get accessible departments for current user
 * @param Database $db
 * @return array
 */
function getAccessibleDepartmentsEnhanced($db) {
    $user = function_exists('getCurrentUser') ? getCurrentUser() : null;
    if (!$user) {
        return [];
    }
    $account_level = $user['account_level'] ?? 1;

    if ($account_level >= 5) {
        // Superadmin - all departments
        return $db->fetchAll("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL ORDER BY department");
    } elseif ($account_level >= 4) {
        // Admin - all departments in their location
        $user_location_id = $user['location_id'] ?? 0;
        if ($user_location_id) {
            return $db->fetchAll(
                "SELECT DISTINCT department FROM employees WHERE location_id = ? AND department IS NOT NULL ORDER BY department",
                [$user_location_id]
            );
        }
    } elseif ($account_level >= 3) {
        // Manager - their department
        $user_department = $user['department'] ?? '';
        if ($user_department) {
            return [['department' => $user_department]];
        }
    }

    return [];
}

/**
 * Log access attempt for audit trail
 * @param Database $db
 * @param string $action
 * @param array $context
 */
function logAccessAttempt($db, $action, $context = []) {
    try {
        $user = function_exists('getCurrentUser') ? getCurrentUser() : null;
        $user_epf = $user['epf_number'] ?? 'unknown';

        $log_data = [
            'user_epf' => $user_epf,
            'action' => $action,
            'context' => json_encode($context),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Try to insert into access_log table if it exists
        $db->query(
            "INSERT INTO access_log (user_epf, action, context, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$log_data['user_epf'], $log_data['action'], $log_data['context'],
             $log_data['ip_address'], $log_data['user_agent'], $log_data['timestamp']]
        );
    } catch (Exception $e) {
        // Log to error log if database logging fails
        error_log("Access log failed: " . $e->getMessage());
    }
}

/**
 * Create access log table if it doesn't exist
 * @param Database $db
 */
function createAccessLogTable($db) {
    try {
        $db->query("
            CREATE TABLE IF NOT EXISTS access_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_epf VARCHAR(50) NOT NULL,
                action VARCHAR(255) NOT NULL,
                context TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_epf (user_epf),
                INDEX idx_action (action),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (Exception $e) {
        error_log("Failed to create access_log table: " . $e->getMessage());
    }
}
?>