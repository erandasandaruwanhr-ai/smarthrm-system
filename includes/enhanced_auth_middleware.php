<?php
/**
 * Enhanced Authentication & Authorization Middleware
 * Provides comprehensive permission checking with data access control
 */

require_once __DIR__ . '/enhanced_permission_system.php';
require_once __DIR__ . '/permission_config.php';

/**
 * Enhanced permission middleware class
 */
class PermissionMiddleware {
    private $db;
    private $user;
    private $cache = [];

    public function __construct($db) {
        $this->db = $db;
        $this->user = function_exists('getCurrentUser') ? getCurrentUser() : null;
    }

    /**
     * Check if user has permission for a specific action with context
     * @param string $permission
     * @param array $context
     * @return bool
     */
    public function check($permission, $context = []) {
        // Cache key for this check
        $cache_key = md5($permission . serialize($context) . $this->user['epf_number']);

        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }

        $result = $this->performCheck($permission, $context);
        $this->cache[$cache_key] = $result;

        // Log access attempt
        $this->logAccess($permission, $context, $result);

        return $result;
    }

    /**
     * Perform the actual permission check
     * @param string $permission
     * @param array $context
     * @return bool
     */
    private function performCheck($permission, $context) {
        // Check if user context is available
        if (!$this->user) {
            return false;
        }

        // Basic module permission check first
        if (!hasModulePermission($this->db, $permission)) {
            return false;
        }

        // If no context, basic permission is sufficient
        if (empty($context)) {
            return true;
        }

        // Apply data access control based on context
        return $this->checkDataAccess($permission, $context);
    }

    /**
     * Check data access permissions based on context
     * @param string $permission
     * @param array $context
     * @return bool
     */
    private function checkDataAccess($permission, $context) {
        $account_level = $this->user['account_level'] ?? 1;

        // Superadmin always has access
        if ($account_level >= 5) {
            return true;
        }

        // Check specific context types
        if (isset($context['employee_epf'])) {
            return $this->checkEmployeeAccess($permission, $context['employee_epf']);
        }

        if (isset($context['location_id'])) {
            return $this->checkLocationAccess($permission, $context['location_id']);
        }

        if (isset($context['department'])) {
            return $this->checkDepartmentAccess($permission, $context['department']);
        }

        if (isset($context['request_id'])) {
            return $this->checkRequestAccess($permission, $context['request_id']);
        }

        // Default to basic permission check
        return true;
    }

    /**
     * Check employee-specific access
     * @param string $permission
     * @param string $employee_epf
     * @return bool
     */
    private function checkEmployeeAccess($permission, $employee_epf) {
        $action_type = $this->getActionType($permission);
        $user_epf = $this->user['epf_number'] ?? '';

        // Self access
        if ($employee_epf === $user_epf) {
            return true;
        }

        switch ($action_type) {
            case 'view':
                return canViewEmployeeDataEnhanced($this->db, $employee_epf);
            case 'edit':
            case 'update':
                return canEditEmployeeDataEnhanced($this->db, $employee_epf);
            case 'delete':
                return canDeleteEmployeeDataEnhanced($this->db, $employee_epf);
            default:
                return canViewEmployeeDataEnhanced($this->db, $employee_epf);
        }
    }

    /**
     * Check location-specific access
     * @param string $permission
     * @param int $location_id
     * @return bool
     */
    private function checkLocationAccess($permission, $location_id) {
        $account_level = $this->user['account_level'] ?? 1;
        $user_location_id = $this->user['location_id'] ?? 0;

        // Admin level and above can access if it's their location or they're superadmin
        if ($account_level >= 4) {
            return $account_level >= 5 || $location_id == $user_location_id;
        }

        // Lower levels cannot access location-specific data
        return false;
    }

    /**
     * Check department-specific access
     * @param string $permission
     * @param string $department
     * @return bool
     */
    private function checkDepartmentAccess($permission, $department) {
        $account_level = $this->user['account_level'] ?? 1;
        $user_department = $this->user['department'] ?? '';

        // Manager level and above can access if it's their department
        if ($account_level >= 3) {
            return $account_level >= 5 || $department === $user_department;
        }

        // Supervisor can only access if they have reports in the department
        if ($account_level >= 2) {
            return $this->hasReportsInDepartment($department);
        }

        return false;
    }

    /**
     * Check request-specific access
     * @param string $permission
     * @param int $request_id
     * @return bool
     */
    private function checkRequestAccess($permission, $request_id) {
        try {
            // Get request details
            $request = $this->db->fetch(
                "SELECT requester_epf, department, location_id FROM requests WHERE id = ?",
                [$request_id]
            );

            if (!$request) {
                return false;
            }

            // Check if user can access the requester's data
            return $this->checkEmployeeAccess($permission, $request['requester_epf']);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check if supervisor has reports in department
     * @param string $department
     * @return bool
     */
    private function hasReportsInDepartment($department) {
        $user_epf = $this->user['epf_number'] ?? '';

        try {
            $reports = $this->db->fetch(
                "SELECT COUNT(*) as count FROM employees WHERE reports_to = ? AND department = ?",
                [$user_epf, $department]
            );

            return $reports['count'] > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get action type from permission string
     * @param string $permission
     * @return string
     */
    private function getActionType($permission) {
        if (strpos($permission, '.view') !== false) return 'view';
        if (strpos($permission, '.edit') !== false) return 'edit';
        if (strpos($permission, '.update') !== false) return 'update';
        if (strpos($permission, '.delete') !== false) return 'delete';
        if (strpos($permission, '.create') !== false) return 'create';
        if (strpos($permission, '.add') !== false) return 'create';

        return 'view'; // Default to view
    }

    /**
     * Log access attempt
     * @param string $permission
     * @param array $context
     * @param bool $granted
     */
    private function logAccess($permission, $context, $granted) {
        try {
            $log_context = array_merge($context, [
                'permission' => $permission,
                'granted' => $granted,
                'account_level' => $this->user['account_level'] ?? 1
            ]);

            logAccessAttempt($this->db, 'permission_check', $log_context);
        } catch (Exception $e) {
            error_log("Failed to log access attempt: " . $e->getMessage());
        }
    }

    /**
     * Enforce permission with automatic redirect on failure
     * @param string $permission
     * @param array $context
     * @param string $redirect_url
     */
    public function enforce($permission, $context = [], $redirect_url = '../../dashboard.php') {
        if (!$this->check($permission, $context)) {
            // Log unauthorized access attempt
            $this->logAccess($permission . '_DENIED', $context, false);

            // Redirect to appropriate page
            header("Location: $redirect_url");
            exit();
        }
    }

    /**
     * Get accessible data based on user's permission level
     * @param string $data_type
     * @param array $filters
     * @return array
     */
    public function getAccessibleData($data_type, $filters = []) {
        switch ($data_type) {
            case 'employees':
                return getAccessibleEmployees($this->db, $filters);
            case 'locations':
                return getAccessibleLocationsEnhanced($this->db);
            case 'departments':
                return getAccessibleDepartmentsEnhanced($this->db);
            default:
                return [];
        }
    }

    /**
     * Check if user can perform bulk operations
     * @param string $operation
     * @param array $target_items
     * @return bool
     */
    public function canBulkOperation($operation, $target_items) {
        // Admin and above can do bulk operations
        if (($this->user['account_level'] ?? 1) >= 4) {
            return true;
        }

        // Check individual access for each item
        foreach ($target_items as $item) {
            if (!$this->check($operation, $item)) {
                return false;
            }
        }

        return true;
    }
}

/**
 * Global permission checking functions for backward compatibility
 */

/**
 * Quick permission check
 * @param string $permission
 * @param array $context
 * @return bool
 */
function checkPermission($permission, $context = []) {
    global $db;
    if (!$db) {
        $db = new Database();
    }

    $middleware = new PermissionMiddleware($db);
    return $middleware->check($permission, $context);
}

/**
 * Enforce permission with redirect
 * @param string $permission
 * @param array $context
 * @param string $redirect_url
 */
function enforcePermission($permission, $context = [], $redirect_url = '../../dashboard.php') {
    global $db;
    if (!$db) {
        $db = new Database();
    }

    $middleware = new PermissionMiddleware($db);
    $middleware->enforce($permission, $context, $redirect_url);
}

/**
 * Get filtered data based on permissions
 * @param string $data_type
 * @param array $filters
 * @return array
 */
function getPermissionFilteredData($data_type, $filters = []) {
    global $db;
    if (!$db) {
        $db = new Database();
    }

    $middleware = new PermissionMiddleware($db);
    return $middleware->getAccessibleData($data_type, $filters);
}

/**
 * Check if user can view specific employee
 * @param string $employee_epf
 * @return bool
 */
function canViewEmployee($employee_epf) {
    return checkPermission('employee.view', ['employee_epf' => $employee_epf]);
}

/**
 * Check if user can edit specific employee
 * @param string $employee_epf
 * @return bool
 */
function canEditEmployee($employee_epf) {
    return checkPermission('employee.edit', ['employee_epf' => $employee_epf]);
}

/**
 * Check if user can approve request
 * @param int $request_id
 * @return bool
 */
function canApproveSpecificRequest($request_id) {
    return checkPermission('requests.approval.approve', ['request_id' => $request_id]);
}

/**
 * UI Helper function to conditionally render content based on permissions
 * @param string $permission
 * @param array $context
 * @param string $content
 * @return string
 */
function renderIfPermitted($permission, $context, $content) {
    return checkPermission($permission, $context) ? $content : '';
}

/**
 * UI Helper to render buttons based on permissions
 * @param string $permission
 * @param array $context
 * @param string $url
 * @param string $text
 * @param string $class
 * @param string $icon
 * @return string
 */
function renderPermissionButton($permission, $context, $url, $text, $class = 'btn-primary', $icon = '') {
    if (!checkPermission($permission, $context)) {
        return '';
    }

    $icon_html = $icon ? "<i class='$icon me-2'></i>" : '';
    return "<a href='$url' class='btn $class'>$icon_html$text</a>";
}

/**
 * Get user's effective permissions for debugging
 * @return array
 */
function getUserEffectivePermissions() {
    global $db;
    if (!$db) {
        $db = new Database();
    }

    $user = getCurrentUser();
    $account_type_id = $user['account_type_id'] ?? 1;

    if (($user['account_level'] ?? 1) >= 5) {
        return ['ALL_PERMISSIONS'];
    }

    try {
        $permissions = $db->fetchAll(
            "SELECT permission_key FROM permission_assignments
             WHERE account_type_id = ? AND is_granted = 1",
            [$account_type_id]
        );

        return array_column($permissions, 'permission_key');
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Permission debugging function for development
 * @param string $permission
 * @param array $context
 * @return array
 */
function debugPermissionCheck($permission, $context = []) {
    global $db;
    if (!$db) {
        $db = new Database();
    }

    $user = getCurrentUser();
    $middleware = new PermissionMiddleware($db);

    return [
        'user' => $user['name'] ?? 'Unknown',
        'epf' => $user['epf_number'] ?? 'Unknown',
        'account_type' => $user['account_type'] ?? 'Unknown',
        'account_level' => $user['account_level'] ?? 1,
        'permission' => $permission,
        'context' => $context,
        'has_basic_permission' => hasModulePermission($db, $permission),
        'has_contextual_permission' => $middleware->check($permission, $context),
        'effective_permissions_count' => count(getUserEffectivePermissions())
    ];
}
?>