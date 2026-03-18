<?php
// Authentication Check and Session Management

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/location_access.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Allow access to password change page if user has temporary session for password change
    $current_page = basename($_SERVER['PHP_SELF']);
    if ($current_page === 'change_password.php' && isset($_SESSION['password_change_required'])) {
        // Allow access to password change page
        return;
    }
    header('Location: ' . BASE_URL . 'auth/login.php');
    exit();
}

// Check session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    // Session expired
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL . 'auth/login.php?timeout=1');
    exit();
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Check if user needs to change password and redirect to change password page
$current_page = basename($_SERVER['PHP_SELF']);
$excluded_pages = ['change_password.php', 'logout.php'];
if (!in_array($current_page, $excluded_pages) && isset($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $db = new Database();

        // Check if password change is required for the current user
        $columns = $db->fetchAll("DESCRIBE employees");
        $available_columns = array_column($columns, 'Field');

        if (in_array('password_change_required', $available_columns)) {
            $user = $db->fetch("SELECT password_change_required FROM employees WHERE id = ?", [$_SESSION['user_id']]);
            if ($user && $user['password_change_required'] == 1) {
                // Set temporary session data and redirect to password change
                $_SESSION['temp_user_id'] = $_SESSION['user_id'];
                $_SESSION['temp_epf_number'] = $_SESSION['epf_number'];
                $_SESSION['temp_name'] = $_SESSION['name'];
                $_SESSION['password_change_required'] = true;

                // Clear main session data
                unset($_SESSION['user_id']);
                unset($_SESSION['epf_number']);
                unset($_SESSION['name']);
                unset($_SESSION['account_type']);
                unset($_SESSION['account_type_id']);
                unset($_SESSION['account_level']);
                unset($_SESSION['location_id']);
                unset($_SESSION['location_name']);
                unset($_SESSION['employment_level']);
                unset($_SESSION['department']);
                unset($_SESSION['reports_to']);

                header('Location: ' . BASE_URL . 'auth/change_password.php');
                exit();
            }
        }
    } catch (Exception $e) {
        // If there's an error checking password change requirement, log it but don't block access
        error_log("Error checking password change requirement: " . $e->getMessage());
    }
}

// Permission checking function
function hasPermission($required_level) {
    return isset($_SESSION['account_level']) && $_SESSION['account_level'] >= $required_level;
}

// Check if user is admin or superadmin (Updated for new role system)
function isAdmin() {
    $role = getUserRole();
    return in_array($role, ['admin', 'superadmin']);
}

// Check if user is superadmin (Updated for new role system)
function isSuperAdmin() {
    $role = getUserRole();
    return $role === 'superadmin';
}

// Check if user is manager or above (Updated for new role system)
function isManager() {
    $role = getUserRole();
    return in_array($role, ['manager', 'admin', 'superadmin']);
}

// Check if user is supervisor or above (Updated for new role system)
function isSupervisor() {
    $role = getUserRole();
    return in_array($role, ['supervisor', 'manager', 'admin', 'superadmin']);
}

/**
 * Get current user's role from new role-based system
 * @param Database $db
 * @return string
 */
function getUserRole($db = null) {
    if (!isset($_SESSION['user_id'])) {
        return 'user'; // Default fallback
    }

    if (!$db) {
        try {
            $db = new Database();
        } catch (Exception $e) {
            return 'user';
        }
    }

    try {
        $user = $db->fetch("SELECT account_type FROM employees WHERE id = ?", [$_SESSION['user_id']]);
        return $user['account_type'] ?? 'user';
    } catch (Exception $e) {
        error_log("Error getting user role: " . $e->getMessage());
        return 'user'; // Safe fallback
    }
}

/**
 * Check if current user has permission for specific module/action using new role-based system
 * @param Database $db
 * @param string $module_permission Permission string (e.g., "employee.view" or "admin.system_config")
 * @return bool
 */
function hasModulePermission($db, $module_permission) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    // Get user role
    $user_role = getUserRole($db);

    // SuperAdmin has all permissions
    if ($user_role === 'superadmin') {
        return true;
    }

    // Parse module.permission format
    $parts = explode('.', $module_permission, 2);
    if (count($parts) !== 2) {
        // If not in module.permission format, treat as legacy
        return hasLegacyPermission($db, $module_permission);
    }

    $module = $parts[0];
    $permission = $parts[1];

    try {
        // Check role-based permissions
        $result = $db->fetch(
            "SELECT COUNT(*) as count FROM role_permissions
             WHERE role = ? AND module = ? AND permission = ? AND granted = 1",
            [$user_role, $module, $permission]
        );

        return $result['count'] > 0;
    } catch (Exception $e) {
        error_log("Error checking role permission: " . $e->getMessage());
        // Fall back to legacy system
        return hasLegacyPermission($db, $module_permission);
    }
}

/**
 * Legacy permission check for backwards compatibility
 * @param Database $db
 * @param string $module_permission
 * @return bool
 */
function hasLegacyPermission($db, $module_permission) {
    $account_type_id = $_SESSION['account_type_id'] ?? null;
    if (!$account_type_id) {
        return false;
    }

    try {
        // First try permission_assignments table
        $permission = $db->fetch(
            "SELECT id FROM permission_assignments
             WHERE account_type_id = ? AND permission_key = ? AND is_granted = 1",
            [$account_type_id, $module_permission]
        );

        if ($permission !== false) {
            return true;
        }

        // Fall back to account_permissions table
        $permission = $db->fetch(
            "SELECT id FROM account_permissions
             WHERE account_type_id = ? AND action_name = ?",
            [$account_type_id, $module_permission]
        );

        return $permission !== false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Require permission or show access denied page
 * @param string $module Module name (e.g., 'admin', 'employee', 'meal')
 * @param string $permission Permission name (e.g., 'view', 'employee_form', 'system_config')
 */
function requirePermission($module, $permission) {
    try {
        $db = new Database();
        if (!hasModulePermission($db, "$module.$permission")) {
            showAccessDeniedPopup($module, $permission);
            exit;
        }
    } catch (Exception $e) {
        showAccessDeniedPopup($module, $permission);
        exit;
    }
}

/**
 * Show access denied popup
 * @param string $module
 * @param string $permission
 */
function showAccessDeniedPopup($module = '', $permission = '') {
    $user_role = getUserRole();
    $user_name = $_SESSION['name'] ?? 'Unknown User';

    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Access Denied</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            body {
                background: linear-gradient(135deg, #1a1a1a 0%, #2d0a0a 50%, #000000 100%);
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                margin: 0;
                padding: 0;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                overflow: hidden;
            }
            .popup-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.95);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 9999;
                backdrop-filter: blur(5px);
            }
            .popup-content {
                background: linear-gradient(145deg, #1c1c1c, #2a0000);
                border-radius: 20px;
                padding: 40px;
                max-width: 550px;
                width: 90%;
                text-align: center;
                box-shadow:
                    0 0 50px rgba(255, 0, 0, 0.3),
                    0 20px 60px rgba(0, 0, 0, 0.8),
                    inset 0 1px 0 rgba(255, 255, 255, 0.1);
                border: 2px solid #8B0000;
                animation: terrorSlide 0.5s ease, pulse 2s infinite alternate;
                position: relative;
            }
            @keyframes terrorSlide {
                0% { transform: scale(0.3) rotate(-10deg); opacity: 0; }
                50% { transform: scale(1.1) rotate(2deg); }
                100% { transform: scale(1) rotate(0deg); opacity: 1; }
            }
            @keyframes pulse {
                0% { box-shadow: 0 0 50px rgba(255, 0, 0, 0.3), 0 20px 60px rgba(0, 0, 0, 0.8); }
                100% { box-shadow: 0 0 80px rgba(255, 0, 0, 0.6), 0 20px 60px rgba(0, 0, 0, 0.8); }
            }
            .popup-content::before {
                content: '';
                position: absolute;
                top: -2px;
                left: -2px;
                right: -2px;
                bottom: -2px;
                background: linear-gradient(45deg, #ff0000, #8B0000, #ff4444, #8B0000);
                border-radius: 20px;
                z-index: -1;
                animation: borderGlow 1.5s linear infinite;
            }
            @keyframes borderGlow {
                0% { background-position: 0% 50%; }
                100% { background-position: 100% 50%; }
            }
            .error-icon {
                font-size: 5rem;
                color: #ff0000;
                margin-bottom: 20px;
                text-shadow: 0 0 20px rgba(255, 0, 0, 0.8);
                animation: iconShake 0.5s ease-in-out infinite alternate;
            }
            @keyframes iconShake {
                0% { transform: rotate(-2deg); }
                100% { transform: rotate(2deg); }
            }
            .popup-title {
                color: #ff0000;
                font-size: 2.2rem;
                font-weight: 900;
                margin-bottom: 20px;
                text-transform: uppercase;
                letter-spacing: 3px;
                text-shadow:
                    0 0 10px rgba(255, 0, 0, 0.8),
                    0 0 20px rgba(255, 0, 0, 0.5),
                    0 0 30px rgba(255, 0, 0, 0.3);
                animation: titleFlicker 1s ease-in-out infinite alternate;
            }
            @keyframes titleFlicker {
                0% { opacity: 0.8; }
                100% { opacity: 1; }
            }
            .popup-message {
                color: #ffcccc;
                margin-bottom: 25px;
                line-height: 1.6;
                font-size: 1.1rem;
                font-weight: 600;
                text-shadow: 0 2px 4px rgba(0, 0, 0, 0.8);
            }
            .violation-warning {
                background: linear-gradient(145deg, #330000, #1a0000);
                border: 2px solid #8B0000;
                border-radius: 15px;
                padding: 20px;
                margin: 25px 0;
                box-shadow: inset 0 0 20px rgba(255, 0, 0, 0.1);
            }
            .violation-warning h4 {
                color: #ff4444;
                font-size: 1.3rem;
                margin-bottom: 15px;
                text-transform: uppercase;
                letter-spacing: 2px;
            }
            .user-info {
                background: linear-gradient(145deg, #2a0000, #1a0000);
                border-radius: 15px;
                padding: 20px;
                margin: 25px 0;
                border: 1px solid #8B0000;
                box-shadow: inset 0 0 15px rgba(255, 0, 0, 0.1);
            }
            .user-info p {
                margin: 8px 0;
                font-weight: 600;
                color: #ffaaaa;
                font-size: 1.1rem;
            }
            .user-info strong {
                color: #ff6666;
            }
            .consequences {
                background: #1a0000;
                border: 2px dashed #8B0000;
                border-radius: 10px;
                padding: 15px;
                margin: 20px 0;
                color: #ff9999;
                font-size: 0.95rem;
                font-weight: 500;
            }
            .popup-button {
                background: linear-gradient(145deg, #8B0000, #4d0000);
                color: #ffffff;
                border: 2px solid #ff0000;
                padding: 15px 40px;
                border-radius: 30px;
                font-size: 1.2rem;
                font-weight: 700;
                cursor: pointer;
                text-transform: uppercase;
                letter-spacing: 2px;
                transition: all 0.3s;
                box-shadow: 0 5px 20px rgba(255, 0, 0, 0.3);
                text-shadow: 0 2px 4px rgba(0, 0, 0, 0.8);
            }
            .popup-button:hover {
                background: linear-gradient(145deg, #ff0000, #8B0000);
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(255, 0, 0, 0.5);
            }
            .security-notice {
                position: absolute;
                top: -15px;
                right: -15px;
                background: #ff0000;
                color: white;
                padding: 5px 15px;
                border-radius: 20px;
                font-size: 0.8rem;
                font-weight: bold;
                transform: rotate(15deg);
                box-shadow: 0 3px 10px rgba(0, 0, 0, 0.3);
                animation: securityBlink 1s ease-in-out infinite;
            }
            @keyframes securityBlink {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.7; }
            }
        </style>
    </head>
    <body>
        <div class="popup-overlay">
            <div class="popup-content">
                <div class="security-notice">SECURITY BREACH</div>

                <div class="error-icon">
                    <i class="fas fa-ban"></i>
                </div>

                <div class="popup-title">ACCESS VIOLATION</div>

                <div class="violation-warning">
                    <h4><i class="fas fa-skull-crossbones me-2"></i>UNAUTHORIZED ACCESS DETECTED</h4>
                    <div class="popup-message">
                        You have attempted to access a restricted area without proper authorization. This incident will be logged and reported to the security team.
                    </div>
                </div>

                <div class="user-info">
                    <p><strong>Violator:</strong> <?php echo htmlspecialchars($user_name); ?></p>
                    <p><strong>Current Role:</strong> <?php echo htmlspecialchars(ucfirst($user_role)); ?></p>
                    <p><strong>Timestamp:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                    <?php if($module && $permission): ?>
                    <p><strong>Attempted Access:</strong> <?php echo htmlspecialchars("$module.$permission"); ?></p>
                    <?php endif; ?>
                    <p><strong>IP Address:</strong> <?php echo $_SERVER['REMOTE_ADDR'] ?? 'Unknown'; ?></p>
                </div>

                <div class="consequences">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>WARNING:</strong> Repeated unauthorized access attempts may result in:
                    <br>• Account suspension • Security investigation • Disciplinary action
                </div>

                <div style="margin-top: 25px;">
                    <button class="popup-button" onclick="goBack()">
                        <i class="fas fa-times me-2"></i>ACKNOWLEDGE & RETREAT
                    </button>
                </div>
            </div>
        </div>

        <script>
            function goBack() {
                if (window.history.length > 1) {
                    window.history.back();
                } else {
                    window.location.href = '<?php echo BASE_URL; ?>dashboard/';
                }
            }

            // Auto-focus the button for keyboard accessibility
            document.querySelector('.popup-button').focus();
        </script>
    </body>
    </html>
    <?php
    exit;
}

/**
 * Check if current user has admin panel access (using new permission system)
 */
function hasAdminAccess() {
    // SuperAdmin always has access
    if (isSuperAdmin()) {
        return true;
    }

    // Check new permission system
    try {
        $db = new Database();
        return hasModulePermission($db, 'admin.access_admin_panel');
    } catch (Exception $e) {
        // Fall back to level-based access
        return isAdmin();
    }
}

/**
 * Check if current user can manage passwords (using new permission system)
 */
function canManagePasswords() {
    // SuperAdmin always has access
    if (isSuperAdmin()) {
        return true;
    }

    // Check new permission system
    try {
        $db = new Database();
        return hasModulePermission($db, 'admin.password_management.all');
    } catch (Exception $e) {
        // Fall back to level-based access
        return isAdmin();
    }
}

/**
 * Check if current user can manage permissions (using new permission system)
 */
function canManagePermissions() {
    // SuperAdmin always has access
    if (isSuperAdmin()) {
        return true;
    }

    // Check new permission system
    try {
        $db = new Database();
        return hasModulePermission($db, 'admin.permissions.all');
    } catch (Exception $e) {
        // Fall back to level-based access
        return isSuperAdmin(); // Only superadmin by default
    }
}

// Get current user info
function getCurrentUser() {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'epf_number' => $_SESSION['epf_number'] ?? null,
        'name' => $_SESSION['name'] ?? null,
        'account_type' => $_SESSION['account_type'] ?? null,
        'account_type_id' => $_SESSION['account_type_id'] ?? null,
        'account_level' => $_SESSION['account_level'] ?? null,
        'location_id' => $_SESSION['location_id'] ?? null,
        'location_name' => $_SESSION['location_name'] ?? null,
        'location' => $_SESSION['location_name'] ?? null, // Add this for backward compatibility
        'employment_level' => $_SESSION['employment_level'] ?? null,
        'department' => $_SESSION['department'] ?? null,
        'reports_to' => $_SESSION['reports_to'] ?? null
    ];
}

/**
 * Get current user's employee data from database
 * @param Database $db
 * @return array|null
 */
function getCurrentUserEmployee($db) {
    $user = getCurrentUser();
    $epf_number = $user['epf_number'] ?? '';

    if (!$epf_number) {
        return null;
    }

    return $db->fetch("SELECT * FROM employees WHERE epf_number = ?", [$epf_number]);
}

/**
 * Check if current user can access specific employee's data
 * @param Database $db
 * @param string $target_epf_number EPF number of employee to access
 * @return bool
 */
function canAccessEmployeeData($db, $target_epf_number) {
    $user = getCurrentUser();
    $account_type = $user['account_type'] ?? 'user';
    $user_epf = $user['epf_number'] ?? '';
    $user_location_id = $user['location_id'] ?? null;

    // SuperAdmin can access all employee data
    if ($account_type === 'superadmin') {
        return true;
    }

    // Admin can access all employees in their location
    if ($account_type === 'admin') {
        $target_employee = $db->fetch("SELECT location_id FROM employees WHERE epf_number = ?", [$target_epf_number]);
        return $target_employee && $target_employee['location_id'] == $user_location_id;
    }

    // Manager/Supervisor can access employees in their hierarchy
    if (in_array($account_type, ['manager', 'supervisor'])) {
        $accessible_employees = getHierarchicalReports($db, $user_epf);
        $accessible_epf_numbers = array_column($accessible_employees, 'epf_number');
        return in_array($target_epf_number, $accessible_epf_numbers);
    }

    // Regular user can only access their own data
    return $user_epf === $target_epf_number;
}

/**
 * Check if current user can edit specific employee's data
 * @param Database $db
 * @param string $target_epf_number EPF number of employee to edit
 * @return bool
 */
function canEditEmployeeData($db, $target_epf_number) {
    $user = getCurrentUser();
    $account_type = $user['account_type'] ?? 'user';

    // SuperAdmin can edit all employee data
    if ($account_type === 'superadmin') {
        return true;
    }

    // Admin can edit employees in their location
    if ($account_type === 'admin') {
        return canAccessEmployeeData($db, $target_epf_number);
    }

    // Manager can edit their direct and indirect reports
    if ($account_type === 'manager') {
        return canAccessEmployeeData($db, $target_epf_number);
    }

    // Supervisor can edit their direct reports only
    if ($account_type === 'supervisor') {
        $user_epf = $user['epf_number'] ?? '';
        $direct_reports = getDirectReports($db, $user_epf);
        $direct_epf_numbers = array_column($direct_reports, 'epf_number');
        return in_array($target_epf_number, $direct_epf_numbers) || $user_epf === $target_epf_number;
    }

    // Regular user can only edit their own data
    $user_epf = $user['epf_number'] ?? '';
    return $user_epf === $target_epf_number;
}

/**
 * Check if current user can delete specific employee's data
 * @param Database $db
 * @param string $target_epf_number EPF number of employee to delete
 * @return bool
 */
function canDeleteEmployeeData($db, $target_epf_number) {
    $user = getCurrentUser();
    $account_type = $user['account_type'] ?? 'user';

    // Only SuperAdmin and Admin can delete employee data
    if ($account_type === 'superadmin') {
        return true;
    }

    if ($account_type === 'admin') {
        return canAccessEmployeeData($db, $target_epf_number);
    }

    // Managers and below cannot delete employee data
    return false;
}

/**
 * Check if current user can access specific module
 * @param Database $db
 * @param string $module Module name (e.g., 'admin', 'employee', 'meal')
 * @return bool
 */
function canAccessModule($db, $module) {
    $user = getCurrentUser();
    $account_type = $user['account_type'] ?? 'user';

    // SuperAdmin can access all modules
    if ($account_type === 'superadmin') {
        return true;
    }

    // Admin can access most modules except some restricted ones
    if ($account_type === 'admin') {
        $restricted_admin_modules = ['admin.permissions', 'admin.account_types'];
        return !in_array($module, $restricted_admin_modules);
    }

    // Check specific module permissions
    return hasModulePermission($db, $module . '.view');
}

/**
 * Get filtered employee list based on user's access level
 * @param Database $db
 * @param string $additional_where Additional WHERE conditions
 * @param array $params Additional parameters
 * @return array
 */
function getFilteredEmployeeList($db, $additional_where = '', $params = []) {
    $user = getCurrentUser();
    return getAccessibleEmployeesByLocation($db, $user, $additional_where);
}

/**
 * Apply data access filter to any SQL query involving employees
 * @param Database $db
 * @param string $base_query Base SQL query (must have 'e' as employees table alias)
 * @param array $params Query parameters
 * @return array
 */
function applyDataAccessFilter($db, $base_query, $params = []) {
    $user = getCurrentUser();
    return filterEmployeeQueryByLocation($db, $user, $base_query, $params);
}

/**
 * Check if current user can create new employee records
 * @return bool
 */
function canCreateEmployee() {
    $user = getCurrentUser();
    $account_type = $user['account_type'] ?? 'user';

    // Only Admin and SuperAdmin can create employees
    return in_array($account_type, ['admin', 'superadmin']);
}

/**
 * Get access level summary for current user
 * @return array
 */
function getUserAccessSummary() {
    $user = getCurrentUser();
    $account_type = $user['account_type'] ?? 'user';

    $access_rules = [
        'user' => [
            'description' => 'Can only view and edit their own data',
            'data_scope' => 'Own data only',
            'can_create' => false,
            'can_edit_others' => false,
            'can_delete' => false
        ],
        'supervisor' => [
            'description' => 'Can view all employees reporting to them, edit direct reports',
            'data_scope' => 'Direct and indirect reports',
            'can_create' => false,
            'can_edit_others' => 'Direct reports only',
            'can_delete' => false
        ],
        'manager' => [
            'description' => 'Can view and edit all employees reporting to them',
            'data_scope' => 'Direct and indirect reports',
            'can_create' => false,
            'can_edit_others' => 'All reports',
            'can_delete' => false
        ],
        'admin' => [
            'description' => 'Can view, edit and manage all employees in their location',
            'data_scope' => 'All employees in assigned location',
            'can_create' => true,
            'can_edit_others' => 'Location-wide',
            'can_delete' => 'Location-wide'
        ],
        'superadmin' => [
            'description' => 'Full access to all employee data across all locations',
            'data_scope' => 'All employees, all locations',
            'can_create' => true,
            'can_edit_others' => 'System-wide',
            'can_delete' => 'System-wide'
        ]
    ];

    return $access_rules[$account_type] ?? $access_rules['user'];
}
?>