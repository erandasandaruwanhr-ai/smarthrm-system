<?php
/**
 * Permission Helper Functions
 * Central permission checking system for all SmartHRM modules
 * Works with existing auth_check.php functions
 */

/**
 * Check if user has specific module permission (wrapper for existing function)
 * @param Database $db
 * @param string $permission Permission string (e.g., 'employee.view', 'admin.locations.edit')
 * @return bool
 */
function checkModulePermission($db, $permission) {
    return hasModulePermission($db, $permission);
}

/**
 * Check if user has specific permission using user array
 * @param Database $db
 * @param array $user Current user data
 * @param string $permission Permission string (e.g., 'employee.view', 'admin.locations.edit')
 * @return bool
 */
function userHasPermission($db, $user, $permission) {
    $account_type = $user['account_type'] ?? 'user';

    // Superadmin always has all permissions
    if ($account_type === 'superadmin') {
        return true;
    }

    // Use existing hasModulePermission function
    return hasModulePermission($db, $permission);
}

/**
 * Check multiple permissions (user needs ALL permissions)
 * @param Database $db
 * @param array $user Current user data
 * @param array $permissions Array of permission strings
 * @return bool
 */
function hasAllPermissions($db, $user, $permissions) {
    foreach ($permissions as $permission) {
        if (!userHasPermission($db, $user, $permission)) {
            return false;
        }
    }
    return true;
}

/**
 * Check multiple permissions (user needs ANY permission)
 * @param Database $db
 * @param array $user Current user data
 * @param array $permissions Array of permission strings
 * @return bool
 */
function hasAnyPermission($db, $user, $permissions) {
    foreach ($permissions as $permission) {
        if (userHasPermission($db, $user, $permission)) {
            return true;
        }
    }
    return false;
}

/**
 * Get user's account type ID
 * @param Database $db
 * @param string $account_type
 * @return int|null
 */
function getUserAccountTypeId($db, $account_type) {
    $result = $db->fetch("SELECT id FROM account_types WHERE type_name = ?", [$account_type]);
    return $result ? $result['id'] : null;
}

/**
 * Get all permissions for a user
 * @param Database $db
 * @param array $user Current user data
 * @return array
 */
function getUserPermissions($db, $user) {
    $account_type = $user['account_type'] ?? 'user';
    $account_type_id = getUserAccountTypeId($db, $account_type);

    if (!$account_type_id) {
        return [];
    }

    $permissions = $db->fetchAll(
        "SELECT action_name FROM account_permissions WHERE account_type_id = ?",
        [$account_type_id]
    );

    return array_column($permissions, 'action_name');
}

/**
 * Module-specific permission checks
 * These align with your existing permissions.php structure
 */

// Admin Panel Permissions
function canAccessAdminPanel($db, $user) {
    return userHasPermission($db, $user, 'admin.view');
}

function canManageSystemConfig($db, $user) {
    return hasAnyPermission($db, $user, [
        'admin.system_config.color_management',
        'admin.system_config.calendar_setup'
    ]);
}

function canManageAccountTypes($db, $user) {
    return hasAnyPermission($db, $user, [
        'admin.account_types.view',
        'admin.account_types.add',
        'admin.account_types.edit',
        'admin.account_types.delete'
    ]);
}

function canManagePermissions($db, $user) {
    return hasPermission($db, $user, 'admin.permissions.assign');
}

function canManageLocations($db, $user) {
    return hasAnyPermission($db, $user, [
        'admin.locations.view',
        'admin.locations.add',
        'admin.locations.edit',
        'admin.locations.delete'
    ]);
}

function canManageEmploymentLevels($db, $user) {
    return hasAnyPermission($db, $user, [
        'admin.employment_levels.view',
        'admin.employment_levels.add',
        'admin.employment_levels.edit',
        'admin.employment_levels.delete'
    ]);
}

function canManagePasswords($db, $user) {
    return hasAnyPermission($db, $user, [
        'admin.password_management.reset_password',
        'admin.password_management.change_default'
    ]);
}

function canManageDropdowns($db, $user) {
    return hasAnyPermission($db, $user, [
        'admin.dropdown_management.view',
        'admin.dropdown_management.add',
        'admin.dropdown_management.edit',
        'admin.dropdown_management.delete'
    ]);
}

// Employee Module Permissions
function canAccessEmployeeModule($db, $user) {
    return hasPermission($db, $user, 'employee.view');
}

function canAddEmployees($db, $user) {
    return hasPermission($db, $user, 'employee.employee_form.add');
}

function canEditEmployees($db, $user) {
    return hasPermission($db, $user, 'employee.employee_form.edit');
}

function canDeleteEmployees($db, $user) {
    return hasPermission($db, $user, 'employee.employee_form.delete');
}

function canViewEmployeeList($db, $user) {
    return hasPermission($db, $user, 'employee.employee_list.view');
}

function canViewDataMonitor($db, $user) {
    return hasPermission($db, $user, 'employee.data_monitor.view');
}

function canViewOrgChart($db, $user) {
    return hasPermission($db, $user, 'employee.org_chart.view');
}

function canExportOrgChart($db, $user) {
    return hasAnyPermission($db, $user, [
        'employee.org_chart.export_pdf',
        'employee.org_chart.export_png',
        'employee.org_chart.print'
    ]);
}

// Meal Management Permissions
function canAccessMealModule($db, $user) {
    return hasPermission($db, $user, 'meal.view');
}

function canSubmitEmployeeMealRequest($db, $user) {
    return hasPermission($db, $user, 'meal.employee_meal.submit');
}

function canSubmitVisitorMealRequest($db, $user) {
    return hasPermission($db, $user, 'meal.visitor_meal.submit');
}

function canViewMealCounters($db, $user) {
    return hasAnyPermission($db, $user, [
        'meal.employee_counter.view',
        'meal.visitor_counter.view'
    ]);
}

function canManageMealRequests($db, $user) {
    return hasAnyPermission($db, $user, [
        'meal.request_view.edit',
        'meal.request_view.delete'
    ]);
}

function canManageMealTimeSettings($db, $user) {
    return hasPermission($db, $user, 'meal.time_manager.edit');
}

// Transport Module Permissions
function canAccessTransportModule($db, $user) {
    return hasPermission($db, $user, 'transport.view');
}

function canManageVehicles($db, $user) {
    return hasAnyPermission($db, $user, [
        'transport.vehicle_register.add',
        'transport.vehicle_register.edit',
        'transport.vehicle_register.delete'
    ]);
}

function canManageVehiclePool($db, $user) {
    return hasPermission($db, $user, 'transport.vehicle_pool.in_out_switch');
}

function canSubmitTransportRequest($db, $user) {
    return hasPermission($db, $user, 'transport.transport_request.submit');
}

function canManageDrivers($db, $user) {
    return hasAnyPermission($db, $user, [
        'transport.driver_pool.add',
        'transport.driver_pool.delete',
        'transport.driver_pool.duty_switch'
    ]);
}

function canManageTransportAllocation($db, $user) {
    return hasPermission($db, $user, 'transport.transport_allocation.assign');
}

// Grievance Module Permissions
function canAccessGrievanceModule($db, $user) {
    return hasPermission($db, $user, 'grievance.view');
}

function canSubmitGrievance($db, $user) {
    return hasPermission($db, $user, 'grievance.submission.submit');
}

function canManageGrievanceCases($db, $user) {
    return hasAnyPermission($db, $user, [
        'grievance.case_management.assign',
        'grievance.case_management.update_status',
        'grievance.case_management.add_notes',
        'grievance.case_management.upload_docs'
    ]);
}

function canApproveGrievanceResolution($db, $user) {
    return hasAnyPermission($db, $user, [
        'grievance.resolution.approve',
        'grievance.resolution.reject',
        'grievance.resolution.request_revision'
    ]);
}

function canManageGrievanceAppeals($db, $user) {
    return hasAnyPermission($db, $user, [
        'grievance.appeal.review_appeal',
        'grievance.appeal.final_decision'
    ]);
}

/**
 * Data Access Level Checks (from permissions_new.php)
 */

function canViewOwnDataOnly($db, $user) {
    $permissions = getUserPermissions($db, $user);
    return in_array('employee.view_own', $permissions);
}

function canViewDirectReports($db, $user) {
    $permissions = getUserPermissions($db, $user);
    return in_array('employee.view_reports', $permissions);
}

function canViewLocationData($db, $user) {
    $permissions = getUserPermissions($db, $user);
    return in_array('employee.view_location', $permissions);
}

function canViewAllData($db, $user) {
    $permissions = getUserPermissions($db, $user);
    return in_array('employee.view_all', $permissions);
}

/**
 * Permission enforcement for redirects
 * Use this in module files to enforce permissions
 */
function enforcePermission($db, $user, $required_permission, $redirect_url = '../../dashboard.php') {
    if (!hasPermission($db, $user, $required_permission)) {
        header("Location: $redirect_url");
        exit();
    }
}

function enforceAnyPermission($db, $user, $required_permissions, $redirect_url = '../../dashboard.php') {
    if (!hasAnyPermission($db, $user, $required_permissions)) {
        header("Location: $redirect_url");
        exit();
    }
}

function enforceAllPermissions($db, $user, $required_permissions, $redirect_url = '../../dashboard.php') {
    if (!hasAllPermissions($db, $user, $required_permissions)) {
        header("Location: $redirect_url");
        exit();
    }
}

/**
 * UI Helper Functions
 * Use these in templates to conditionally show/hide elements
 */

function showIfPermission($db, $user, $permission, $content) {
    if (hasPermission($db, $user, $permission)) {
        return $content;
    }
    return '';
}

function hideIfNoPermission($db, $user, $permission, $content) {
    if (!hasPermission($db, $user, $permission)) {
        return '';
    }
    return $content;
}

/**
 * Permission button/link helpers
 */
function renderActionButton($db, $user, $permission, $url, $text, $class = 'btn-primary', $icon = '') {
    if (!hasPermission($db, $user, $permission)) {
        return '';
    }

    $icon_html = $icon ? "<i class='$icon me-2'></i>" : '';
    return "<a href='$url' class='btn $class'>$icon_html$text</a>";
}

function renderActionLink($db, $user, $permission, $url, $text, $class = '', $icon = '') {
    if (!hasPermission($db, $user, $permission)) {
        return '';
    }

    $icon_html = $icon ? "<i class='$icon me-2'></i>" : '';
    $class_html = $class ? " class='$class'" : '';
    return "<a href='$url'$class_html>$icon_html$text</a>";
}

/**
 * Get filtered sidebar modules based on permissions
 */
function getAccessibleModules($db, $user) {
    $all_modules = [
        'admin' => ['permission' => 'admin.view', 'icon' => 'fas fa-cogs', 'title' => 'Admin Panel'],
        'employee' => ['permission' => 'employee.view', 'icon' => 'fas fa-users', 'title' => 'Employee Data'],
        'meal' => ['permission' => 'meal.view', 'icon' => 'fas fa-utensils', 'title' => 'Meal Management'],
        'transport' => ['permission' => 'transport.view', 'icon' => 'fas fa-bus', 'title' => 'Transport'],
        'grievance' => ['permission' => 'grievance.view', 'icon' => 'fas fa-exclamation-triangle', 'title' => 'Grievance'],
        // Add other modules as they are implemented
    ];

    $accessible_modules = [];

    foreach ($all_modules as $module_key => $module_data) {
        if (hasPermission($db, $user, $module_data['permission'])) {
            $accessible_modules[$module_key] = $module_data;
        }
    }

    return $accessible_modules;
}

/**
 * Debug function - get user's permission summary
 */
function debugUserPermissions($db, $user) {
    $permissions = getUserPermissions($db, $user);
    $account_type = $user['account_type'] ?? 'unknown';

    echo "<div class='card'>";
    echo "<div class='card-header'>Permission Debug - {$account_type}</div>";
    echo "<div class='card-body'>";
    echo "<p>Total Permissions: " . count($permissions) . "</p>";
    echo "<ul>";
    foreach ($permissions as $permission) {
        echo "<li><code>$permission</code></li>";
    }
    echo "</ul>";
    echo "</div>";
    echo "</div>";
}
?>