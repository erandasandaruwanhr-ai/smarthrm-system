<?php
/**
 * Permission Helper Functions - Fixed Version
 * Works with existing auth_check.php without conflicts
 */

/**
 * NOTE: Core permission functions (hasAdminAccess, isAdmin, isSuperAdmin, hasModulePermission, canManagePermissions)
 * are already defined in auth_check.php and will be available when that file is included.
 */

/**
 * Simple wrapper functions that use existing auth_check.php functions
 */

// Admin Panel Permissions
function canAccessAdminPanel($db) {
    return hasModulePermission($db, 'admin.view');
}

function canManageSystemConfig($db) {
    return hasModulePermission($db, 'admin.system_config.color_management') ||
           hasModulePermission($db, 'admin.system_config.calendar_setup');
}

function canManageAccountTypes($db) {
    return hasModulePermission($db, 'admin.account_types.view') ||
           hasModulePermission($db, 'admin.account_types.add');
}

// canManagePermissions() is now defined in auth_check.php - use that one instead

function canManageLocations($db) {
    return hasModulePermission($db, 'admin.locations.view') ||
           hasModulePermission($db, 'admin.locations.add');
}

function canManageEmploymentLevels($db) {
    return hasModulePermission($db, 'admin.employment_levels.view') ||
           hasModulePermission($db, 'admin.employment_levels.add');
}

// canManagePasswords() is now defined in auth_check.php - use that one instead

function canManageDropdowns($db) {
    return hasModulePermission($db, 'admin.dropdown_management.view') ||
           hasModulePermission($db, 'admin.dropdown_management.add');
}

// Employee Module Permissions
function canAccessEmployeeModule($db) {
    return hasModulePermission($db, 'employee.view');
}

function canAddEmployees($db) {
    return hasModulePermission($db, 'employee.create');
}

function canEditEmployees($db) {
    return hasModulePermission($db, 'employee.edit_reports') ||
           hasModulePermission($db, 'employee.edit_location') ||
           hasModulePermission($db, 'employee.edit_all');
}

function canDeleteEmployees($db) {
    return hasModulePermission($db, 'employee.delete');
}

function canViewEmployeeList($db) {
    return hasModulePermission($db, 'employee.view');
}

function canViewDataMonitor($db) {
    return hasModulePermission($db, 'employee.data_monitor.view');
}

function canViewOrgChart($db) {
    return hasModulePermission($db, 'employee.org_chart.view');
}

function canExportOrgChart($db) {
    return hasModulePermission($db, 'employee.org_chart.export');
}

// Meal Management Permissions
function canAccessMealModule($db) {
    return hasModulePermission($db, 'meal.view');
}

function canSubmitEmployeeMealRequest($db) {
    return hasModulePermission($db, 'meal.employee_request.submit');
}

function canSubmitVisitorMealRequest($db) {
    return hasModulePermission($db, 'meal.visitor_request.submit');
}

function canViewMealCounters($db) {
    return hasModulePermission($db, 'meal.counters.view');
}

function canManageMealRequests($db) {
    return hasModulePermission($db, 'meal.requests.manage');
}

function canManageMealTimeSettings($db) {
    return hasModulePermission($db, 'meal.time_settings.manage');
}

// Transport Module Permissions
function canAccessTransportModule($db) {
    return hasModulePermission($db, 'transport.view');
}

function canManageVehicles($db) {
    return hasModulePermission($db, 'transport.vehicle.manage');
}

function canManageVehiclePool($db) {
    return hasModulePermission($db, 'transport.vehicle_pool.manage');
}

function canSubmitTransportRequest($db) {
    return hasModulePermission($db, 'transport.request.submit');
}

function canManageDrivers($db) {
    return hasModulePermission($db, 'transport.drivers.manage');
}

function canManageTransportAllocation($db) {
    return hasModulePermission($db, 'transport.allocation.manage');
}

// Grievance Module Permissions
function canAccessGrievanceModule($db) {
    return hasModulePermission($db, 'grievance.view');
}

function canSubmitGrievance($db) {
    return hasModulePermission($db, 'grievance.submit');
}

function canManageGrievanceCases($db) {
    return hasModulePermission($db, 'grievance.case_management.assign') ||
           hasModulePermission($db, 'grievance.case_management.view');
}

function canApproveGrievanceResolution($db) {
    return hasModulePermission($db, 'grievance.resolution.approve');
}

function canManageGrievanceAppeals($db) {
    return hasModulePermission($db, 'grievance.appeal.review_appeal');
}

/**
 * Special Grievance Investigation Team Permissions
 * These preserve the existing anonymous grievance workflow
 */

/**
 * Check if current user is assigned to any anonymous grievance investigation team
 * @param Database $db
 * @return bool
 */
function isAssignedToInvestigationTeam($db) {
    if (!isset($_SESSION['epf_number'])) {
        return false;
    }

    $user_epf = $_SESSION['epf_number'];

    try {
        $assignments = $db->fetchAll(
            "SELECT ait.* FROM anonymous_investigation_teams ait
             WHERE (ait.team_leader_epf = ? OR ait.member1_epf = ? OR ait.member2_epf = ?)
             AND ait.status = 'Active'",
            [$user_epf, $user_epf, $user_epf]
        );

        return !empty($assignments);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get user's investigation team assignments
 * @param Database $db
 * @return array
 */
function getInvestigationTeamAssignments($db) {
    if (!isset($_SESSION['epf_number'])) {
        return [];
    }

    $user_epf = $_SESSION['epf_number'];

    try {
        return $db->fetchAll(
            "SELECT ait.*, ag.case_id
             FROM anonymous_investigation_teams ait
             JOIN anonymous_grievances ag ON ait.anonymous_grievance_id = ag.id
             WHERE (ait.team_leader_epf = ? OR ait.member1_epf = ? OR ait.member2_epf = ?)
             AND ait.status = 'Active'",
            [$user_epf, $user_epf, $user_epf]
        );
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Check if user can access anonymous grievance case management
 * This includes both regular permission holders AND investigation team members
 * @param Database $db
 * @return bool
 */
function canAccessAnonymousGrievanceCaseManagement($db) {
    $user = getCurrentUser();
    $account_type = $user['account_type'] ?? 'user';

    // Admin users always have access
    $is_admin_user = in_array($account_type, ['superadmin', 'admin', 'manager', 'supervisor']);
    if ($is_admin_user) {
        return true;
    }

    // Regular permission check
    if (hasModulePermission($db, 'grievance.case_management.view') ||
        hasModulePermission($db, 'grievance.case_management.assign')) {
        return true;
    }

    // Special check for investigation team assignments
    return isAssignedToInvestigationTeam($db);
}

/**
 * Check if user can view specific anonymous grievance case
 * @param Database $db
 * @param int $anonymous_grievance_id
 * @return bool
 */
function canViewSpecificAnonymousCase($db, $anonymous_grievance_id) {
    $user = getCurrentUser();
    $account_type = $user['account_type'] ?? 'user';

    // Superadmin can view all cases
    if ($account_type === 'superadmin') {
        return true;
    }

    // Check if user has general permission
    if (hasModulePermission($db, 'grievance.case_management.view')) {
        return true;
    }

    // Check if user is assigned to this specific case
    if (!isset($_SESSION['epf_number'])) {
        return false;
    }

    $user_epf = $_SESSION['epf_number'];

    try {
        $assignment = $db->fetch(
            "SELECT id FROM anonymous_investigation_teams
             WHERE anonymous_grievance_id = ?
             AND (team_leader_epf = ? OR member1_epf = ? OR member2_epf = ?)
             AND status = 'Active'",
            [$anonymous_grievance_id, $user_epf, $user_epf, $user_epf]
        );

        return !empty($assignment);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Enhanced grievance case management check that respects investigation teams
 * @param Database $db
 * @return bool
 */
function canManageGrievanceCasesEnhanced($db) {
    // First check regular permissions
    if (canManageGrievanceCases($db)) {
        return true;
    }

    // Then check investigation team assignments
    return canAccessAnonymousGrievanceCaseManagement($db);
}

/**
 * Permission enforcement for redirects - simplified
 */
function enforceModulePermission($db, $required_permission, $redirect_url = '../../dashboard.php') {
    if (!hasModulePermission($db, $required_permission)) {
        header("Location: $redirect_url");
        exit();
    }
}

/**
 * UI Helper Functions
 */
function showIfHasPermission($db, $permission, $content) {
    if (hasModulePermission($db, $permission)) {
        return $content;
    }
    return '';
}

function hideIfNoModulePermission($db, $permission, $content) {
    if (!hasModulePermission($db, $permission)) {
        return '';
    }
    return $content;
}

/**
 * Permission button helpers
 */
function renderPermissionButton($db, $permission, $url, $text, $class = 'btn-primary', $icon = '') {
    if (!hasModulePermission($db, $permission)) {
        return '';
    }

    $icon_html = $icon ? "<i class='$icon me-2'></i>" : '';
    return "<a href='$url' class='btn $class'>$icon_html$text</a>";
}

function renderPermissionLink($db, $permission, $url, $text, $class = '', $icon = '') {
    if (!hasModulePermission($db, $permission)) {
        return '';
    }

    $icon_html = $icon ? "<i class='$icon me-2'></i>" : '';
    $class_html = $class ? " class='$class'" : '';
    return "<a href='$url'$class_html>$icon_html$text</a>";
}

/**
 * Get accessible modules based on permissions
 */
function getAccessibleModules($db) {
    $modules = [];

    if (hasModulePermission($db, 'admin.view')) {
        $modules['admin'] = ['icon' => 'fas fa-cogs', 'title' => 'Admin Panel'];
    }

    if (hasModulePermission($db, 'employee.view')) {
        $modules['employee'] = ['icon' => 'fas fa-users', 'title' => 'Employee Data'];
    }

    if (hasModulePermission($db, 'meal.view')) {
        $modules['meal'] = ['icon' => 'fas fa-utensils', 'title' => 'Meal Management'];
    }

    if (hasModulePermission($db, 'transport.view')) {
        $modules['transport'] = ['icon' => 'fas fa-bus', 'title' => 'Transport'];
    }

    if (hasModulePermission($db, 'grievance.view')) {
        $modules['grievance'] = ['icon' => 'fas fa-exclamation-triangle', 'title' => 'Grievance'];
    }

    return $modules;
}

/**
 * Debug function - show user's current permissions
 */
function debugCurrentUserPermissions($db) {
    $user = getCurrentUser();
    $account_type = $user['account_type'] ?? 'unknown';
    $account_type_id = $user['account_type_id'] ?? 0;

    echo "<div class='alert alert-info'>";
    echo "<h5>Permission Debug - {$account_type}</h5>";

    if ($account_type === 'superadmin') {
        echo "<p><strong>Status:</strong> Superadmin - Has all permissions</p>";
    } else {
        $permissions = $db->fetchAll(
            "SELECT action_name FROM account_permissions WHERE account_type_id = ?",
            [$account_type_id]
        );

        echo "<p><strong>Account Type ID:</strong> $account_type_id</p>";
        echo "<p><strong>Total Permissions:</strong> " . count($permissions) . "</p>";

        if (!empty($permissions)) {
            echo "<strong>Permissions:</strong><ul>";
            foreach ($permissions as $perm) {
                echo "<li><code>" . htmlspecialchars($perm['action_name']) . "</code></li>";
            }
            echo "</ul>";
        }
    }
    echo "</div>";
}
?>