<?php
session_start();
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

// Check if user is logged in and has permission management access
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../auth/login.php');
    exit();
}

// Only allow level 5 (Superadmin) to access this
if (($_SESSION['account_level'] ?? 1) < 5) {
    header('Location: ../../dashboard.php');
    exit();
}

$user = [
    'name' => $_SESSION['name'] ?? 'Unknown',
    'account_level' => $_SESSION['account_level'] ?? 1
];

$success_message = '';
$error_message = '';
$db = new Database();

// Handle form submission for permission updates
if ($_POST && isset($_POST['account_type_id']) && isset($_POST['save_permissions'])) {
    $account_type_id = intval($_POST['account_type_id']);
    $submitted_permissions = $_POST['permissions'] ?? [];

    try {
        // Get account type name for logging
        $account_type = $db->fetch("SELECT type_name FROM account_types WHERE id = ?", [$account_type_id]);
        $account_type_name = $account_type ? $account_type['type_name'] : 'Unknown';

        // Clear existing permissions for this account type
        $db->query("DELETE FROM permission_assignments WHERE account_type_id = ?", [$account_type_id]);

        // Insert new permissions
        $permission_count = 0;
        foreach ($submitted_permissions as $permission_key) {
            $db->query(
                "INSERT INTO permission_assignments (account_type_id, permission_key, is_granted) VALUES (?, ?, 1)",
                [$account_type_id, $permission_key]
            );
            $permission_count++;
        }

        $success_message = "Successfully updated permissions for {$account_type_name}. Applied {$permission_count} permissions.";
    } catch (Exception $e) {
        $error_message = "Error updating permissions: " . $e->getMessage();
    }
}

// Define realistic permissions based on ACTUAL SYSTEM FILES and user requirements
$realistic_permission_matrix = [
    'Dashboard' => [
        'dashboard.access' => 'Access Dashboard'
    ],

    'Admin Panel (SuperAdmin Only)' => [
        'admin.access_admin_panel' => 'Access Admin Panel (index.php)',
        'admin.system_configuration' => 'System Configuration (system_config.php)',
        'admin.account_types' => 'Account Types Management (account_types.php)',
        'admin.permission_management' => 'Permission Management (permissions_central.php)',
        'admin.locations' => 'Location Management (locations.php)',
        'admin.employment_levels' => 'Employment Levels (employment_levels.php)',
        'admin.password_management' => 'Password Management (password_management_enhanced.php)',
        'admin.dropdown_management' => 'Dropdown Management (dropdown_management.php)'
    ],

    'Employee Data' => [
        // 2.1 Employee Form (SuperAdmin)
        'employee.employee_form' => 'Employee Form (employee_form.php)',
        'employee.add_employee' => 'Add New Employee',
        'employee.edit_employee' => 'Edit Employee Data',

        // 2.2 Employee List (SuperAdmin, Admin, Manager, Supervisor)
        'employee.employee_list' => 'Employee List (employee_list.php)',
        'employee.view_own_profile' => 'View Own Profile (All levels)',
        'employee.view_team_profiles' => 'View Team Profiles (Supervisor+)',
        'employee.view_department_employees' => 'View Department Employees (Manager+)',
        'employee.view_location_employees' => 'View Location Employees (Admin+)',
        'employee.view_all_employees' => 'View All Employees (SuperAdmin)',
        'employee.search_employees' => 'Search Employees (All levels)',
        'employee.export_employees' => 'Export Employee Data (Admin+)',
        'employee.bulk_upload' => 'Bulk Upload Employees (Admin+)',
        'employee.delete_employee' => 'Delete Employee (Admin+)',

        // 2.3 Data Monitor (SuperAdmin, Admin, Manager, Supervisor)
        'employee.data_monitor' => 'Data Monitor (data_monitor.php)',
        'employee.view_analytics' => 'View Employee Analytics',

        // 2.4 Organizational Chart (SuperAdmin, Admin, Manager, Supervisor)
        'employee.org_chart' => 'Organizational Chart (org_chart.php)',
        'employee.view_org_chart' => 'View Organization Chart'
    ],

    'Meal Management' => [
        // 3.1 Employee Meal Request (All levels)
        'meal.employee_meal_request' => 'Employee Meal Request (employee_meal_request.php)',

        // 3.2 My Meal History (All levels)
        'meal.my_meal_history' => 'My Meal History (my_meal_history.php)',

        // 3.3 Visitor Meal Request (SuperAdmin, Admin, Manager, Supervisor)
        'meal.visitor_meal_request' => 'Visitor Meal Request (visitor_meal_request.php)',

        // 3.4 Employee Meal Counter (SuperAdmin, Admin)
        'meal.employee_meal_counter' => 'Employee Meal Counter (employee_meal_counter.php)',

        // 3.5 Visitor Meal Counter (SuperAdmin, Admin)
        'meal.visitor_meal_counter' => 'Visitor Meal Counter (visitor_meal_counter.php)',

        // 3.6 Visitor Request Management (SuperAdmin, Admin, Manager, Supervisor)
        'meal.visitor_request_management' => 'Visitor Request Management (visitor_request_list.php)',

        // 3.7 Meal Analytics (SuperAdmin, Admin)
        'meal.meal_analytics' => 'Meal Analytics (meal_analytics.php)',

        // 3.8 Meal Time Manager (SuperAdmin only)
        'meal.meal_time_manager' => 'Meal Time Manager (meal_time_manager.php)',

        // 3.9 Quick Reports (SuperAdmin, Admin)
        'meal.meal_reports' => 'Meal Reports (meal_reports.php)',

        // 3.10 Today's Overview (SuperAdmin, Admin)
        'meal.daily_overview' => 'Daily Overview (daily_overview.php)',

        // 3.11 Detailed Request List (SuperAdmin, Admin)
        'meal.meal_request_details' => 'Meal Request Details (meal_request_details.php)'
    ],

    'Transport' => [
        // 4.1 Vehicle Register (SuperAdmin, Admin)
        'transport.vehicle_register' => 'Vehicle Register (vehicle_register.php)',

        // 4.2 Vehicle Pool (SuperAdmin, Admin)
        'transport.vehicle_pool' => 'Vehicle Pool (vehicle_pool.php)',

        // 4.3 Transport Request (All levels)
        'transport.transport_request' => 'Transport Request (transport_request.php)',

        // 4.4 Driver Pool (SuperAdmin, Admin)
        'transport.driver_pool' => 'Driver Pool (driver_pool.php)',

        // 4.5 Transport Allocation (All levels access, different buttons per level)
        'transport.transport_allocation' => 'Transport Allocation (transport_allocation.php)',
        'transport.allocation_button' => 'Allocation Button (SuperAdmin, Admin)',
        'transport.join_trip_button' => 'Join Trip Button (All levels)',
        'transport.details_button' => 'Details Button (All levels)',
        'transport.start_button' => 'Start Button (Allocated driver only)',
        'transport.cancel_button' => 'Cancel Button (SuperAdmin only)',

        // 4.6 Transport Reports (SuperAdmin, Admin)
        'transport.transport_reports' => 'Transport Reports (transport_reports.php)'
    ],

    'Grievance' => [
        // 5.1 Submit Grievance (All levels)
        'grievance.submit_grievance' => 'Submit Grievance (submit_grievance.php)',

        // 5.2 Case Management (SuperAdmin, Admin, Manager, Supervisor)
        'grievance.case_management' => 'Case Management (case_management.php)',
        'grievance.anonymous_case_management' => 'Anonymous Case Management (SuperAdmin only)',
        'grievance.assigned_case_management' => 'Assigned Case Management (User assigned by SuperAdmin)',

        // 5.3 Grievance List (SuperAdmin, Admin)
        'grievance.grievance_list' => 'Grievance List (grievance_list.php)',

        // 5.4 Reports & Analytics (SuperAdmin, Admin)
        'grievance.reports_analytics' => 'Reports & Analytics (reports.php)',

        // 5.5 My Grievances (All levels)
        'grievance.my_grievances' => 'My Grievances (my_grievances.php)',

        // 5.6 Investigation Team (SuperAdmin only)
        'grievance.investigation_team' => 'Investigation Team (investigation_team.php)',

        // Hierarchical access
        'grievance.view_subordinate_grievances' => 'View Subordinate Grievances (Supervisor)',
        'grievance.view_department_grievances' => 'View Department Grievances (Manager)',
        'grievance.view_location_grievances' => 'View Location Grievances (Admin)',
        'grievance.view_all_grievances' => 'View All Grievances (SuperAdmin)'
    ],

    'Employee Requests' => [
        // 6.1 Submit Request (All levels: SuperAdmin, Admin, Manager, Supervisor, User)
        'employee_requests.submit_request' => 'Submit Request (submit_request.php)',

        // 6.2 My Requests (All levels: SuperAdmin, Admin, Manager, Supervisor, User)
        'employee_requests.view_own_requests' => 'My Requests (my_requests.php)',

        // 6.3 All Requests (SuperAdmin only)
        'employee_requests.view_all_requests' => 'All Requests (all_requests.php)',

        // 6.4 Reports & Analytics (SuperAdmin only)
        'employee_requests.view_reports' => 'Reports & Analytics (reports.php)'
    ]
];

// Account types
$account_types_info = [
    1 => ['name' => 'User', 'color' => 'secondary'],
    2 => ['name' => 'Supervisor', 'color' => 'info'],
    3 => ['name' => 'Manager', 'color' => 'warning'],
    4 => ['name' => 'Admin', 'color' => 'primary'],
    5 => ['name' => 'Superadmin', 'color' => 'danger']
];

// Get current account type (default to User)
$current_account_type = intval($_GET['account_type'] ?? 1);

// Get existing permissions for the current account type
$existing_permissions = [];
try {
    $permissions_result = $db->fetchAll(
        "SELECT permission_key FROM permission_assignments WHERE account_type_id = ? AND is_granted = 1",
        [$current_account_type]
    );
    foreach ($permissions_result as $perm) {
        $existing_permissions[] = $perm['permission_key'];
    }
} catch (Exception $e) {
    // Handle database error gracefully
    $existing_permissions = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Realistic Permission Management - SmartHRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .main-content { margin-left: var(--sidebar-width); padding: 2rem; }
        :root {
            --sidebar-width: 280px;
        }
        .permission-section {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .section-header {
            border-bottom: 2px solid #f8f9fa;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }
        .section-title {
            color: #333;
            font-weight: 600;
            margin-bottom: 0;
        }
        .permission-item {
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            background: #f8f9fa;
            border-left: 4px solid #dee2e6;
            transition: all 0.3s ease;
        }
        .permission-item:hover {
            background: #e9ecef;
            border-left-color: #007bff;
        }
        .permission-item.checked {
            background: #d4edda;
            border-left-color: #28a745;
        }
        .form-check-input:checked {
            background-color: #28a745;
            border-color: #28a745;
        }
        .account-type-badge {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
        }
        .summary-card {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-shield-alt me-2"></i>Realistic Permission Management</h2>
                <p class="text-muted mb-0">Manage permissions that match actual system functions</p>
            </div>
            <a href="../../dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-home me-2"></i>Dashboard
            </a>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Account Type Selection -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-users me-2"></i>Select Account Type</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($account_types_info as $type_id => $type_info): ?>
                                <div class="col-md-2 col-sm-4 col-6 mb-3">
                                    <a href="?account_type=<?php echo $type_id; ?>"
                                       class="btn btn-outline-<?php echo $type_info['color']; ?> w-100 <?php echo $current_account_type == $type_id ? 'active' : ''; ?>">
                                        <?php echo $type_info['name']; ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Current Permissions Summary -->
        <div class="summary-card">
            <div class="row">
                <div class="col-md-8">
                    <h4><i class="fas fa-info-circle me-2"></i>Current Configuration</h4>
                    <p class="mb-0">
                        Managing permissions for: <span class="badge bg-<?php echo $account_types_info[$current_account_type]['color']; ?> account-type-badge"><?php echo $account_types_info[$current_account_type]['name']; ?></span>
                    </p>
                    <small>These permissions match the actual functions used in the system</small>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="d-flex flex-column">
                        <strong>Total Permissions: <?php echo count($existing_permissions); ?></strong>
                        <small>Active realistic permissions</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Permission Form -->
        <form method="POST" id="permissionForm">
            <input type="hidden" name="account_type_id" value="<?php echo $current_account_type; ?>">

            <?php foreach ($realistic_permission_matrix as $module_name => $module_permissions): ?>
                <div class="permission-section">
                    <div class="section-header">
                        <h5 class="section-title">
                            <i class="fas fa-cog me-2"></i><?php echo $module_name; ?>
                        </h5>
                        <small class="text-muted">
                            <?php
                            $module_count = 0;
                            foreach ($module_permissions as $perm_key => $perm_desc) {
                                if (in_array($perm_key, $existing_permissions)) {
                                    $module_count++;
                                }
                            }
                            echo "{$module_count} of " . count($module_permissions) . " permissions enabled";
                            ?>
                        </small>
                    </div>

                    <div class="row">
                        <?php foreach ($module_permissions as $permission_key => $permission_description): ?>
                            <?php $is_checked = in_array($permission_key, $existing_permissions); ?>
                            <div class="col-lg-6 col-12">
                                <div class="permission-item <?php echo $is_checked ? 'checked' : ''; ?>">
                                    <div class="form-check">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               name="permissions[]"
                                               value="<?php echo htmlspecialchars($permission_key); ?>"
                                               id="perm_<?php echo str_replace('.', '_', $permission_key); ?>"
                                               <?php echo $is_checked ? 'checked' : ''; ?>
                                               onchange="updatePermissionItemStyle(this)">
                                        <label class="form-check-label w-100" for="perm_<?php echo str_replace('.', '_', $permission_key); ?>">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($permission_description); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($permission_key); ?></small>
                                                </div>
                                                <div>
                                                    <?php if ($is_checked): ?>
                                                        <i class="fas fa-check-circle text-success"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-circle text-muted"></i>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Save Button -->
            <div class="text-center mt-4">
                <button type="submit" name="save_permissions" class="btn btn-success btn-lg">
                    <i class="fas fa-save me-2"></i>Save Permissions for <?php echo $account_types_info[$current_account_type]['name']; ?>
                </button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updatePermissionItemStyle(checkbox) {
            const permissionItem = checkbox.closest('.permission-item');
            const icon = permissionItem.querySelector('.fa-circle, .fa-check-circle');

            if (checkbox.checked) {
                permissionItem.classList.add('checked');
                icon.className = 'fas fa-check-circle text-success';
            } else {
                permissionItem.classList.remove('checked');
                icon.className = 'fas fa-circle text-muted';
            }
        }

        // Update permission counts when checkboxes change
        document.addEventListener('change', function(e) {
            if (e.target.type === 'checkbox' && e.target.name === 'permissions[]') {
                // Update section counts
                updateSectionCounts();
            }
        });

        function updateSectionCounts() {
            document.querySelectorAll('.permission-section').forEach(section => {
                const checkboxes = section.querySelectorAll('input[type="checkbox"]');
                const checkedBoxes = section.querySelectorAll('input[type="checkbox"]:checked');
                const countText = section.querySelector('.text-muted small');

                if (countText) {
                    countText.textContent = `${checkedBoxes.length} of ${checkboxes.length} permissions enabled`;
                }
            });
        }
    </script>
</body>
</html>