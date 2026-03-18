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

// Define realistic permissions that match the actual database structure
$realistic_permission_matrix = [
    'Dashboard' => [
        'dashboard.access' => 'Access Dashboard'
    ],
    'Admin Panel' => [
        'admin.access_admin_panel' => 'Access Admin Panel',
        'admin.view_system_statistics' => 'View System Statistics',
        'admin.manage_system_configuration' => 'Manage System Configuration',
        'admin.manage_account_types' => 'Manage Account Types',
        'admin.manage_permissions' => 'Manage Permissions',
        'admin.manage_locations' => 'Manage Locations',
        'admin.manage_employment_levels' => 'Manage Employment Levels',
        'admin.manage_password_policies' => 'Manage Password Policies',
        'admin.manage_dropdown_options' => 'Manage Dropdown Options',
        'admin.view_system_information' => 'View System Information'
    ],
    'Employee Management' => [
        'employee.view_own_profile' => 'View Own Profile',
        'employee.search_colleagues' => 'Search Colleagues',
        'employee.view_team_profiles' => 'View Team Profiles',
        'employee.view_department_employees' => 'View Department Employees',
        'employee.view_location_employees' => 'View Location Employees',
        'employee.add_employees' => 'Add New Employees',
        'employee.edit_location_employees' => 'Edit Location Employees',
        'employee.delete_employees' => 'Delete Employees',
        'employee.bulk_upload_employees' => 'Bulk Upload Employees',
        'employee.export_location_data' => 'Export Location Data',
        'employee.view_org_chart' => 'View Organization Chart',
        'employee.manage_all_employees' => 'Manage All Employees (System Wide)'
    ],
    'Meal Management' => [
        'meal.submit_employee_request' => 'Submit Employee Meal Requests',
        'meal.view_employee_counter' => 'View Employee Meal Counter',
        'meal.view_team_meal_requests' => 'View Team Meal Requests',
        'meal.approve_team_meal_requests' => 'Approve Team Meal Requests',
        'meal.manage_meal_time_settings' => 'Manage Meal Time Settings',
        'meal.view_meal_analytics' => 'View Meal Analytics',
        'meal.manage_visitor_requests' => 'Manage Visitor Requests',
        'meal.view_visitor_counter' => 'View Visitor Counter',
        'meal.manage_location_meals' => 'Manage Location Meals',
        'meal.manage_all_meals_system_wide' => 'Manage All Meals (System Wide)'
    ],
    'Transport Management' => [
        'transport.submit_request' => 'Submit Transport Requests',
        'transport.view_allocation_status' => 'View Allocation Status',
        'transport.view_team_requests' => 'View Team Requests',
        'transport.approve_team_transport' => 'Approve Team Transport',
        'transport.manage_vehicle_pool' => 'Manage Vehicle Pool',
        'transport.manage_driver_pool' => 'Manage Driver Pool',
        'transport.manage_vehicle_register' => 'Manage Vehicle Register',
        'transport.full_transport_allocation' => 'Full Transport Allocation',
        'transport.manage_all_transport_system_wide' => 'Manage All Transport (System Wide)'
    ],
    'Grievance Management' => [
        'grievance.submit_grievance' => 'Submit Grievances',
        'grievance.view_my_grievances' => 'View My Grievances',
        'grievance.view_grievance_details' => 'View Grievance Details',
        'grievance.view_team_grievances' => 'View Team Grievances',
        'grievance.participate_case_management' => 'Participate in Case Management',
        'grievance.access_case_management' => 'Access Case Management',
        'grievance.view_investigation_teams' => 'View Investigation Teams',
        'grievance.manage_investigation_teams' => 'Manage Investigation Teams',
        'grievance.full_case_management' => 'Full Case Management',
        'grievance.manage_all_grievances_system_wide' => 'Manage All Grievances (System Wide)'
    ],
    'Employee Requests' => [
        'requests.submit_basic_requests' => 'Submit Basic Requests',
        'requests.view_own_requests' => 'View Own Requests',
        'requests.approve_team_leave' => 'Approve Team Leave',
        'requests.approve_team_overtime' => 'Approve Team Overtime',
        'requests.manage_department_requests' => 'Manage Department Requests',
        'requests.manage_location_requests' => 'Manage Location Requests',
        'requests.manage_all_requests_system_wide' => 'Manage All Requests (System Wide)'
    ],
    'Event Calendar' => [
        'events.view_calendar' => 'View Calendar',
        'events.manage_location_events' => 'Manage Location Events',
        'events.manage_all_events_system_wide' => 'Manage All Events (System Wide)'
    ],
    'Medical Management' => [
        'medical.view_own_medical_info' => 'View Own Medical Info',
        'medical.manage_location_medical' => 'Manage Location Medical',
        'medical.manage_all_medical_system_wide' => 'Manage All Medical (System Wide)'
    ],
    'Onboarding' => [
        'onboarding.manage_location_onboarding' => 'Manage Location Onboarding',
        'onboarding.manage_all_onboarding_system_wide' => 'Manage All Onboarding (System Wide)'
    ],
    'Offboarding' => [
        'offboarding.manage_location_offboarding' => 'Manage Location Offboarding',
        'offboarding.manage_all_offboarding_system_wide' => 'Manage All Offboarding (System Wide)'
    ],
    'Training' => [
        'training.manage_location_training' => 'Manage Location Training',
        'training.manage_all_training_system_wide' => 'Manage All Training (System Wide)'
    ],
    'Goal Setting' => [
        'goals.view_own_goals' => 'View Own Goals',
        'goals.set_own_goals' => 'Set Own Goals',
        'goals.review_team_goals' => 'Review Team Goals',
        'goals.manage_location_goals' => 'Manage Location Goals',
        'goals.manage_all_goals_system_wide' => 'Manage All Goals (System Wide)'
    ],
    'Performance Appraisal' => [
        'performance.complete_self_appraisal' => 'Complete Self Appraisal',
        'performance.conduct_team_appraisals' => 'Conduct Team Appraisals',
        'performance.manage_location_performance' => 'Manage Location Performance',
        'performance.manage_all_performance_system_wide' => 'Manage All Performance (System Wide)'
    ],
    'Talent Management' => [
        'talent.manage_location_talent' => 'Manage Location Talent',
        'talent.manage_all_talent_system_wide' => 'Manage All Talent (System Wide)'
    ],
    'Job Descriptions' => [
        'jobs.view_job_descriptions' => 'View Job Descriptions',
        'jobs.manage_location_jobs' => 'Manage Location Jobs',
        'jobs.manage_all_jobs_system_wide' => 'Manage All Jobs (System Wide)'
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
        .main-content { margin-left: 0; padding: 2rem; }
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
    <div class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-shield-alt me-2"></i>Realistic Permission Management</h2>
                <p class="text-muted mb-0">Manage permissions that match actual system functions</p>
            </div>
            <a href="../admin/" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Admin
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