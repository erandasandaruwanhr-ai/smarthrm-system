<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

// Only superadmins can access this page
if (!isSuperAdmin()) {
    header('Location: ../../dashboard.php');
    exit;
}

$message = '';
$error = '';

// Define the role hierarchy and permissions
$roles = [
    'user' => [
        'name' => 'User',
        'level' => 1,
        'description' => 'Basic employee - can only access own data and basic functions',
        'data_scope' => 'self_only'
    ],
    'supervisor' => [
        'name' => 'Supervisor',
        'level' => 2,
        'description' => 'Can access own data and data of direct reports',
        'data_scope' => 'direct_reports'
    ],
    'manager' => [
        'name' => 'Manager',
        'level' => 3,
        'description' => 'Can access data of all employees in reporting hierarchy',
        'data_scope' => 'reporting_hierarchy'
    ],
    'admin' => [
        'name' => 'Admin',
        'level' => 4,
        'description' => 'Can access all data within assigned location',
        'data_scope' => 'location_all'
    ],
    'superadmin' => [
        'name' => 'SuperAdmin',
        'level' => 5,
        'description' => 'Full system access - all locations and all data',
        'data_scope' => 'global_all'
    ]
];

// Define all system modules and their permissions
$modules = [
    'dashboard' => [
        'name' => 'Dashboard',
        'permissions' => ['view' => 'View Dashboard']
    ],
    'admin' => [
        'name' => 'Administration',
        'permissions' => [
            'system_config' => 'System Configuration',
            'account_types' => 'Account Types Management',
            'account_types_all' => 'Full Account Types Access',
            'employment_levels' => 'Employment Levels',
            'employment_levels_all' => 'Full Employment Levels Access',
            'locations' => 'Location Management',
            'locations_all' => 'Full Location Management Access',
            'password_management' => 'Password Management',
            'dropdown_management' => 'Dropdown Management',
            'dropdown_management_all' => 'Full Dropdown Management Access',
            'permissions' => 'Permission Management',
            'add_employment_level' => 'Add Employment Level',
            'edit_employment_level' => 'Edit Employment Level',
            'delete_employment_level' => 'Delete Employment Level',
            'save_system_config' => 'Save System Configuration',
            'reset_password' => 'Reset Password',
            'deactivate_account' => 'Deactivate Account',
            'add_account_type' => 'Add Account Type',
            'edit_account_type' => 'Edit Account Type',
            'delete_account_type' => 'Delete Account Type',
            'save_permissions' => 'Save Permissions',
            'reset_permissions' => 'Reset Permissions',
            'add_dropdown_item' => 'Add Dropdown Item',
            'edit_dropdown_item' => 'Edit Dropdown Item',
            'delete_dropdown_item' => 'Delete Dropdown Item',
            'add_location' => 'Add Location',
            'edit_location' => 'Edit Location',
            'delete_location' => 'Delete Location',
            'clear_notifications' => 'Clear Notifications'
        ]
    ],
    'employee' => [
        'name' => 'Employee Management',
        'permissions' => [
            'view' => 'View Employee Dashboard',
            'employee_form' => 'Employee Registration Form',
            'employee_list' => 'Employee List',
            'data_monitor' => 'Employee Data Monitoring',
            'org_chart' => 'Organization Chart',
            'bulk_upload' => 'Bulk Employee Upload',
            'add' => 'Add New Employee',
            'add_employee' => 'Add Employee (Alternative)',
            'export' => 'Export Employee Data',
            'export_employees' => 'Export Employees (Alternative)',
            'delete' => 'Delete Employee',
            'template' => 'Download Templates',
            'view_own' => 'View Own Employee Data',
            'view_all' => 'View All Employee Data',
            'filter_search' => 'Filter and Search Employees',
            'view_details' => 'View Employee Details',
            'edit' => 'Edit Employee Information'
        ]
    ],
    'meal' => [
        'name' => 'Meal Management',
        'permissions' => [
            'view' => 'View Meal Dashboard',
            'employee_meal_request' => 'Submit Employee Meal Requests',
            'my_meal_history' => 'View Own Meal History',
            'visitor_meal_request' => 'Submit Visitor Meal Requests',
            'employee_meal_counter' => 'Employee Meal Counter',
            'visitor_meal_counter' => 'Visitor Meal Counter',
            'visitor_request_list' => 'Visitor Request List',
            'meal_analytics' => 'Meal Analytics',
            'meal_time_manager' => 'Meal Time Management',
            'meal_reports' => 'Meal Reports',
            'daily_overview' => 'Daily Meal Overview',
            'meal_request_details' => 'Meal Request Details',
            'submit_employee_request' => 'Submit Employee Meal Request',
            'reset_employee_request' => 'Reset Employee Meal Request Form',
            'submit_visitor_request' => 'Submit Visitor Meal Request',
            'reset_visitor_request' => 'Reset Visitor Meal Request Form',
            'filter_visitor_requests' => 'Filter Visitor Requests',
            'view_visitor_details' => 'View Visitor Request Details'
        ]
    ],
    'transport' => [
        'name' => 'Transport Management',
        'permissions' => [
            'view' => 'View Transport Dashboard',
            'vehicle_register' => 'Vehicle Registration',
            'vehicle_pool' => 'Vehicle Pool Management',
            'vehicle_providers' => 'Vehicle Providers',
            'transport_request' => 'Transport Requests',
            'driver_pool' => 'Driver Pool',
            'transport_allocation' => 'Transport Allocation',
            'transport_reports' => 'Transport Reports',
            'allocation_button' => 'Allocation Button Access',
            'details_button' => 'Details Button Access',
            'cancel_button' => 'Cancel Button Access',
            'start_button' => 'Start Button Access',
            'submit_request' => 'Submit Transport Request',
            'cancel_request' => 'Cancel Transport Request',
            'add_vehicle' => 'Add/Edit Vehicle',
            'delete_vehicle' => 'Delete Vehicle',
            'filter_vehicles' => 'Filter Vehicles',
            'edit_vehicle_status' => 'Edit Vehicle Status',
            'allocate_vehicle' => 'Allocate Vehicle',
            'complete_trip' => 'Complete Trip',
            'join_trip' => 'Join Trip',
            'add_driver' => 'Add/Edit Driver',
            'delete_driver' => 'Delete Driver',
            'edit_driver_status' => 'Edit Driver Status',
            'import_drivers' => 'Import Drivers',
            'search_employees' => 'Search Employees',
            'generate_reports' => 'Generate Transport Reports'
        ]
    ],
    'grievance' => [
        'name' => 'Grievance Management',
        'permissions' => [
            'view' => 'View Grievance Dashboard',
            'submit_grievance' => 'Submit Grievances',
            'grievance_list' => 'View Grievance List',
            'my_grievances' => 'View Own Grievances',
            'reports' => 'Grievance Reports',
            'reports_analytics' => 'Grievance Reports & Analytics',
            'case_management' => 'Grievance Case Management',
            'filter_grievances' => 'Filter Grievances',
            'submit_grievance_form' => 'Submit Grievance Form',
            'generate_reports' => 'Generate Grievance Reports',
            'update_case_status' => 'Update Case Status',
            'add_case_note' => 'Add Case Note',
            'escalate_case' => 'Escalate Case',
            'assign_case' => 'Assign Case',
            'resolve_case' => 'Resolve Case'
        ]
    ],
    'requests' => [
        'name' => 'Employee Requests',
        'permissions' => [
            'view' => 'View Requests Dashboard',
            'submit_request' => 'Submit Requests',
            'my_requests' => 'View Own Requests',
            'all_requests' => 'View All Requests',
            'reports' => 'Request Reports',
            'submit_request_form' => 'Submit Request Form',
            'generate_reports' => 'Generate Request Reports',
            'approve_request' => 'Approve Request'
        ]
    ],
    'events' => [
        'name' => 'Event Management',
        'permissions' => [
            'view' => 'View Events Dashboard',
            'event_management' => 'Event Management',
            'calendar_view' => 'Calendar View',
            'event_list' => 'Event List',
            'add_event' => 'Add Event',
            'edit_event' => 'Edit Event',
            'delete_event' => 'Delete Event'
        ]
    ],
    'medical' => [
        'name' => 'Medical Insurance',
        'permissions' => [
            'view' => 'View Medical Dashboard',
            'monthly_claims' => 'Monthly Claims',
            'balance_checker' => 'Balance Checker',
            'spectacles_claims' => 'Spectacles Claims',
            'allocate_limits' => 'Allocate Limits',
            'reports' => 'Medical Reports',
            'submit_claim' => 'Submit Medical Claim',
            'generate_reports' => 'Generate Medical Reports'
        ]
    ],
    'onboarding' => [
        'name' => 'Onboarding',
        'permissions' => [
            'view' => 'View Onboarding Dashboard',
            'new_onboarding' => 'New Onboarding',
            'onboarding_list' => 'Onboarding List',
            'onboarding_reports' => 'Onboarding Reports',
            'submit_onboarding' => 'Submit Onboarding',
            'add_notes' => 'Add Onboarding Notes',
            'generate_reports' => 'Generate Onboarding Reports'
        ]
    ],
    'offboarding' => [
        'name' => 'Offboarding',
        'permissions' => [
            'view' => 'View Offboarding Dashboard',
            'resignation_form' => 'Resignation Form',
            'exit_interview' => 'Exit Interview',
            'clearance_form' => 'Clearance Form',
            'resignation_approvals' => 'Resignation Approvals',
            'clearance_verification' => 'Clearance Verification',
            'offboarding_list' => 'Offboarding List',
            'offboarding_reports' => 'Offboarding Reports',
            'resignation_history' => 'Resignation History',
            'retention_analytics' => 'Retention Analytics'
        ]
    ],
    'training' => [
        'name' => 'Training Management',
        'permissions' => [
            'view' => 'View Training Dashboard',
            'requirements' => 'Training Requirements',
            'budget' => 'Training Budget',
            'plans' => 'Training Plans',
            'evaluation' => 'Training Evaluation',
            'feedback' => 'Training Feedback',
            'tracker' => 'Training Tracker',
            'management' => 'Training Management',
            'reports' => 'Training Reports'
        ]
    ],
    'goals' => [
        'name' => 'Goal Setting',
        'permissions' => [
            'view' => 'View Goals Dashboard',
            'executive_list' => 'Executive List',
            'form_setup' => 'Goal Form Setup',
            'goal_forms' => 'Goal Forms',
            'kpi_tracker' => 'KPI Tracker',
            'mid_year_review' => 'Mid Year Review',
            'final_evaluation' => 'Final Evaluation',
            'reports_analytics' => 'Reports & Analytics',
            'system_settings' => 'System Settings'
        ]
    ],
    'performance' => [
        'name' => 'Performance Management',
        'permissions' => [
            'view' => 'View Performance Dashboard',
            'staff_list' => 'Staff List',
            'staff_appraisal_setup' => 'Staff Appraisal Setup',
            'staff_appraisal' => 'Staff Appraisal',
            'staff_marks_data' => 'Staff Marks Data',
            'executive_list' => 'Executive List',
            'executive_appraisal_setup' => 'Executive Appraisal Setup',
            'executive_appraisal' => 'Executive Appraisal',
            'executive_marks_data' => 'Executive Marks Data',
            'leave_utilization' => 'Leave Utilization',
            'reports_analytics' => 'Reports & Analytics'
        ]
    ],
    'key_talent' => [
        'name' => 'Key Talent Identification',
        'permissions' => [
            'view' => 'View Key Talent Dashboard',
            'talent_candidates' => 'Talent Candidates',
            'kti_form_setup' => 'KTI Form Setup',
            'assessment_form' => 'Assessment Form',
            'marks_allocations' => 'Marks Allocations',
            'talent_grid' => 'Talent Grid',
            'talent_reports' => 'Talent Reports',
            'submit_assessment' => 'Submit Assessment',
            'setup_forms' => 'Setup Forms',
            'generate_reports' => 'Generate Reports'
        ]
    ],
    'skill' => [
        'name' => 'Skill Matrix',
        'permissions' => [
            'view' => 'View Dashboard/Module',
            'view_skill_matrix' => 'View Skill Matrix',
            'complete_skill_assessment' => 'Complete Skill Assessment',
            'skill_assessment' => 'Skill Assessment',
            'assessment_reports' => 'Assessment Reports',
            'employee_list' => 'Access Employee List',
            'matrix_setup' => 'Matrix Setup'
        ]
    ],
    'jobs' => [
        'name' => 'Jobs & Recruitment',
        'permissions' => [
            'view' => 'View Jobs Dashboard',
            'apply_job' => 'Apply for Jobs',
            'post_job' => 'Post Job Openings',
            'manage_applications' => 'Manage Job Applications'
        ]
    ]
];

// Default permission sets for each role - These reflect the actual permissions in the database
$default_permissions = [
    'user' => [
        'dashboard' => ['view'],
        'employee' => ['view_own'], // Only own data
        'meal' => ['employee_meal_request', 'my_meal_history'],
        'grievance' => ['submit_grievance', 'my_grievances'],
        'requests' => ['submit_request', 'my_requests'],
        'events' => ['calendar_view', 'event_list'],
        'medical' => ['view', 'monthly_claims', 'balance_checker', 'spectacles_claims'],
        'transport' => ['view', 'transport_request'],
        'skill' => ['view', 'view_skill_matrix', 'complete_skill_assessment'],
        'offboarding' => ['resignation_form', 'exit_interview']
    ],
    'supervisor' => [
        'dashboard' => ['view'],
        'employee' => ['view_own', 'view_direct_reports', 'employee_list', 'export_data'], // Direct reports
        'meal' => ['employee_meal_request', 'my_meal_history', 'visitor_meal_request', 'daily_overview'],
        'grievance' => ['submit_grievance', 'my_grievances', 'view_team_grievances', 'case_management'],
        'requests' => ['submit_request', 'my_requests', 'view_team_requests', 'approve_supervisor'],
        'events' => ['calendar_view', 'event_list'],
        'medical' => ['view', 'monthly_claims', 'balance_checker', 'spectacles_claims'],
        'transport' => ['view', 'transport_request', 'transport_allocation'],
        'skill' => ['view', 'view_skill_matrix', 'complete_skill_assessment', 'skill_assessment', 'assessment_reports', 'employee_list'],
        'performance' => ['staff_appraisal', 'staff_marks_data'],
        'offboarding' => ['resignation_form', 'exit_interview']
    ],
    'manager' => [
        'dashboard' => ['view'],
        'employee' => ['view_own', 'view_hierarchy', 'employee_list', 'org_chart', 'export_data'], // Reporting hierarchy
        'meal' => ['view', 'employee_meal_request', 'my_meal_history', 'visitor_meal_request', 'meal_analytics', 'daily_overview'],
        'grievance' => ['view', 'submit_grievance', 'my_grievances', 'grievance_list', 'reports', 'case_management'],
        'requests' => ['view', 'submit_request', 'my_requests', 'all_requests', 'reports', 'approve_manager'],
        'events' => ['view', 'event_management', 'calendar_view', 'event_list'],
        'medical' => ['view', 'monthly_claims', 'balance_checker', 'spectacles_claims'],
        'transport' => ['view', 'transport_request', 'transport_allocation', 'transport_reports'],
        'onboarding' => ['view', 'new_onboarding', 'onboarding_list'],
        'offboarding' => ['view', 'resignation_approvals', 'exit_interview', 'offboarding_list'],
        'training' => ['view', 'requirements', 'plans', 'evaluation', 'feedback', 'tracker'],
        'goals' => ['view', 'executive_list', 'goal_forms', 'kpi_tracker', 'mid_year_review', 'final_evaluation'],
        'performance' => ['view', 'staff_list', 'staff_appraisal', 'staff_marks_data', 'executive_list', 'executive_appraisal'],
        'key_talent' => ['view', 'talent_candidates', 'assessment_form', 'talent_grid'],
        'skill' => ['view', 'view_skill_matrix', 'complete_skill_assessment', 'skill_assessment', 'assessment_reports', 'employee_list', 'matrix_setup']
    ],
    'admin' => [
        'dashboard' => ['view'],
        'admin' => ['system_config', 'account_types', 'employment_levels', 'locations', 'password_management', 'dropdown_management'],
        'employee' => ['view', 'employee_form', 'employee_list', 'data_monitor', 'org_chart', 'bulk_upload', 'export_data', 'location_wide'], // Same location
        'meal' => ['view', 'employee_meal_request', 'my_meal_history', 'visitor_meal_request', 'employee_meal_counter', 'visitor_meal_counter', 'meal_analytics', 'meal_time_manager', 'meal_reports'],
        'grievance' => ['view', 'submit_grievance', 'grievance_list', 'reports', 'case_management'],
        'requests' => ['view', 'submit_request', 'my_requests', 'all_requests', 'reports', 'approve_admin'],
        'events' => ['view', 'event_management', 'calendar_view', 'event_list'],
        'transport' => ['view', 'vehicle_register', 'vehicle_pool', 'transport_request', 'driver_pool', 'transport_allocation', 'transport_reports'],
        // NO medical access for admin as requested
        'onboarding' => ['view', 'new_onboarding', 'onboarding_list', 'onboarding_reports'],
        'offboarding' => ['view', 'resignation_form', 'exit_interview', 'clearance_form', 'resignation_approvals', 'clearance_verification', 'offboarding_list', 'offboarding_reports'],
        'training' => ['view', 'requirements', 'budget', 'plans', 'evaluation', 'feedback', 'tracker', 'management', 'reports'],
        'goals' => ['view', 'executive_list', 'form_setup', 'goal_forms', 'kpi_tracker', 'mid_year_review', 'final_evaluation', 'reports_analytics'],
        'performance' => ['view', 'staff_list', 'staff_appraisal_setup', 'staff_appraisal', 'staff_marks_data', 'executive_list', 'executive_appraisal_setup', 'executive_appraisal', 'reports_analytics'],
        'key_talent' => ['view', 'talent_candidates', 'kti_form_setup', 'assessment_form', 'marks_allocations', 'talent_grid', 'talent_reports'],
        'skill' => ['view', 'view_skill_matrix', 'complete_skill_assessment', 'skill_assessment', 'assessment_reports', 'employee_list', 'matrix_setup']
    ],
    'superadmin' => [
        'dashboard' => ['view'],
        'admin' => ['view', 'system_config', 'account_types', 'employment_levels', 'locations', 'password_management', 'dropdown_management', 'permissions_management'],
        'employee' => ['view', 'employee_form', 'employee_list', 'data_monitor', 'org_chart', 'bulk_upload', 'export_data', 'view_all'],
        'meal' => ['view', 'employee_meal_request', 'my_meal_history', 'visitor_meal_request', 'employee_meal_counter', 'visitor_meal_counter', 'meal_analytics', 'meal_time_manager', 'meal_reports', 'daily_overview'],
        'grievance' => ['view', 'submit_grievance', 'grievance_list', 'my_grievances', 'reports', 'case_management'],
        'requests' => ['view', 'submit_request', 'my_requests', 'all_requests', 'reports', 'approve_supervisor', 'approve_manager', 'approve_admin'],
        'events' => ['view', 'event_management', 'calendar_view', 'event_list'],
        'transport' => ['view', 'vehicle_register', 'vehicle_pool', 'transport_request', 'driver_pool', 'transport_allocation', 'transport_reports'],
        'medical' => ['view', 'monthly_claims', 'balance_checker', 'spectacles_claims', 'allocate_limits', 'reports'],
        'onboarding' => ['view', 'new_onboarding', 'onboarding_list', 'onboarding_reports'],
        'offboarding' => ['view', 'resignation_form', 'exit_interview', 'clearance_form', 'resignation_approvals', 'clearance_verification', 'offboarding_list', 'offboarding_reports', 'resignation_history', 'retention_analytics'],
        'training' => ['view', 'requirements', 'budget', 'plans', 'evaluation', 'feedback', 'tracker', 'management', 'reports'],
        'goals' => ['view', 'executive_list', 'form_setup', 'goal_forms', 'kpi_tracker', 'mid_year_review', 'final_evaluation', 'reports', 'system_settings'],
        'performance' => ['view', 'staff_list', 'staff_setup', 'staff_appraisal', 'staff_marks', 'executive_list', 'executive_setup', 'executive_appraisal', 'executive_tracker', 'leave_utilization', 'reports'],
        'key_talent' => ['view', 'candidates_list', 'form_setup', 'conduct_assessment', 'assessment_results', 'talent_grid', 'reports'],
        'skill' => ['view', 'view_skill_matrix', 'complete_skill_assessment', 'skill_assessment', 'assessment_reports', 'employee_list', 'matrix_setup']
    ]
];

// Handle form submission
if ($_POST) {
    try {
        if (isset($_POST['reset_permissions'])) {
            $role = $_POST['role'];

            if (isset($default_permissions[$role])) {
                // Clear existing permissions for this role
                $db->query("DELETE FROM role_permissions WHERE role = ?", [$role]);

                // Insert default permissions
                $count = 0;
                foreach ($default_permissions[$role] as $module => $permissions) {
                    foreach ($permissions as $permission) {
                        $db->query(
                            "INSERT INTO role_permissions (role, module, permission, granted) VALUES (?, ?, ?, 1)",
                            [$role, $module, $permission]
                        );
                        $count++;
                    }
                }

                $message = "Reset permissions for " . $roles[$role]['name'] . " to defaults. Applied {$count} permissions.";
            } else {
                $error = "Invalid role selected.";
            }

        } elseif (isset($_POST['update_permissions'])) {
            $role = $_POST['role'];
            $submitted_permissions = $_POST['permissions'] ?? [];

            // Clear existing permissions for this role
            $db->query("DELETE FROM role_permissions WHERE role = ?", [$role]);

            // Insert new permissions
            $count = 0;
            foreach ($submitted_permissions as $module_permission) {
                list($module, $permission) = explode('.', $module_permission);
                $db->query(
                    "INSERT INTO role_permissions (role, module, permission, granted) VALUES (?, ?, ?, 1)",
                    [$role, $module, $permission]
                );
                $count++;
            }

            $message = "Updated permissions for " . $roles[$role]['name'] . ". Applied {$count} permissions.";
        }
    } catch (Exception $e) {
        $error = "Error updating permissions: " . $e->getMessage();
    }
}

// Get current permissions for selected role
$selected_role = $_GET['role'] ?? 'user';
$current_permissions = [];

$perms = $db->fetchAll("SELECT module, permission FROM role_permissions WHERE role = ? AND granted = 1", [$selected_role]);
foreach ($perms as $perm) {
    if (!isset($current_permissions[$perm['module']])) {
        $current_permissions[$perm['module']] = [];
    }
    $current_permissions[$perm['module']][] = $perm['permission'];
}

// COMPLETELY REBUILD modules array from database only - no mixing with predefined
$modules = [];

// Get ALL permissions that exist in the system (not just for selected role)
$all_permissions = $db->fetchAll("
    SELECT DISTINCT module, permission
    FROM role_permissions
    ORDER BY module, permission
");

// Build complete modules array with ALL permissions (for all roles to choose from)
foreach ($all_permissions as $perm) {
    $module = $perm['module'];
    $permission = $perm['permission'];

    if (!isset($modules[$module])) {
        $modules[$module] = [
            'name' => '',
            'permissions' => [],
            'order' => 999 // Default order for sorting
        ];
    }

    // Create readable names for permissions
    $permission_name_map = [
        'view' => 'View Dashboard/Module',
        'submit_employee_request' => 'Submit Employee Meal Request',
        'reset_employee_request' => 'Reset Employee Request Form',
        'submit_visitor_request' => 'Submit Visitor Meal Request',
        'reset_visitor_request' => 'Reset Visitor Request Form',
        'filter_visitor_requests' => 'Filter Visitor Requests',
        'view_visitor_details' => 'View Visitor Request Details',
        'filter_search' => 'Filter and Search Employees',
        'view_details' => 'View Employee Details',
        'edit' => 'Edit Employee Information',
        'employee_list' => 'Access Employee List',
        'org_chart' => 'View Organization Chart',
        'submit_request' => 'Submit Employee Request',
        'cancel_request' => 'Cancel Transport Request',
        'add_vehicle' => 'Add/Edit Vehicle',
        'delete_vehicle' => 'Delete Vehicle',
        'filter_vehicles' => 'Filter Vehicles',
        'edit_vehicle_status' => 'Edit Vehicle Status',
        'allocate_vehicle' => 'Allocate Vehicle',
        'complete_trip' => 'Complete Trip',
        'join_trip' => 'Join Trip',
        'add_driver' => 'Add/Edit Driver',
        'delete_driver' => 'Delete Driver',
        'edit_driver_status' => 'Edit Driver Status',
        'import_drivers' => 'Import Drivers',
        'search_employees' => 'Search Employees',
        'generate_reports' => 'Generate Reports',
        'filter_grievances' => 'Filter Grievances',
        'submit_grievance_form' => 'Submit Grievance Form',
        'update_case_status' => 'Update Case Status',
        'add_case_note' => 'Add Case Note',
        'escalate_case' => 'Escalate Case',
        'assign_case' => 'Assign Case',
        'resolve_case' => 'Resolve Case',
        'submit_request_form' => 'Submit Employee Request Form',
        'approve_request' => 'Approve Employee Request',
        'all_requests' => 'View All Employee Requests',
        'my_requests' => 'View My Employee Requests',
        'view_team_requests' => 'View Team Employee Requests',
        'approve_supervisor' => 'Approve as Supervisor',
        'approve_manager' => 'Approve as Manager',
        'approve_admin' => 'Approve as Admin',
        'add_event' => 'Add Event',
        'edit_event' => 'Edit Event',
        'delete_event' => 'Delete Event',
        'calendar_view' => 'Calendar View',
        'event_list' => 'Event List',
        'event_management' => 'Event Management',
        'submit_goal_form' => 'Submit Goal Form',
        'submit_evaluation' => 'Submit Evaluation',
        'setup_forms' => 'Setup Forms',
        'system_settings' => 'System Settings',
        'final_evaluation' => 'Final Evaluation',
        'goal_forms' => 'Goal Forms',
        'kpi_tracker' => 'KPI Tracker',
        'mid_year_review' => 'Mid Year Review',
        'executive_list' => 'Executive List',
        'reports_analytics' => 'Reports & Analytics',
        'form_setup' => 'Form Setup',
        'submit_assessment' => 'Submit Assessment',
        'assessment_form' => 'Assessment Form',
        'apply_job' => 'Apply for Job',
        'post_job' => 'Post Job Opening',
        'manage_applications' => 'Manage Job Applications',
        'submit_claim' => 'Submit Medical Claim',
        'balance_checker' => 'Balance Checker',
        'monthly_claims' => 'Monthly Claims',
        'spectacles_claims' => 'Spectacles Claims',
        'allocate_limits' => 'Allocate Limits',
        'reports' => 'Reports',
        'submit_resignation' => 'Submit Resignation',
        'approve_resignation' => 'Approve Resignation',
        'clearance_form' => 'Clearance Form',
        'exit_interview' => 'Exit Interview',
        'resignation_form' => 'Resignation Form',
        'submit_onboarding' => 'Submit Onboarding',
        'add_notes' => 'Add Notes',
        'submit_appraisal' => 'Submit Appraisal',
        'setup_appraisal' => 'Setup Appraisal',
        'submit_marks' => 'Submit Marks',
        'staff_appraisal' => 'Staff Appraisal',
        'leave_utilization' => 'Leave Utilization',
        'my_requests' => 'My Requests',
        'submit_requirements' => 'Submit Training Requirements',
        'budget_management' => 'Budget Management',
        'submit_feedback' => 'Submit Feedback',
        'evaluation' => 'Training Evaluation',
        'feedback' => 'Training Feedback',
        'requirements' => 'Training Requirements',
        'tracker' => 'Training Tracker',
        'details_button' => 'Details Button Access',
        'account_types_all' => 'Full Account Types Access',
        'add_account_type' => 'Add Account Type',
        'add_dropdown_item' => 'Add Dropdown Item',
        'add_employment_level' => 'Add Employment Level',
        'add_location' => 'Add Location',
        'clear_notifications' => 'Clear Notifications',
        'deactivate_account' => 'Deactivate Account',
        'delete_account_type' => 'Delete Account Type',
        'delete_dropdown_item' => 'Delete Dropdown Item',
        'delete_employment_level' => 'Delete Employment Level',
        'delete_location' => 'Delete Location',
        'dropdown_management_all' => 'Full Dropdown Management Access',
        'edit_account_type' => 'Edit Account Type',
        'edit_dropdown_item' => 'Edit Dropdown Item',
        'edit_employment_level' => 'Edit Employment Level',
        'edit_location' => 'Edit Location',
        'employment_levels_all' => 'Full Employment Levels Access',
        'locations_all' => 'Full Location Management Access',
        'reset_password' => 'Reset Password',
        'reset_permissions' => 'Reset Permissions',
        'save_permissions' => 'Save Permissions',
        'save_system_config' => 'Save System Configuration',
        'view_skill_matrix' => 'View Skill Matrix',
        'complete_skill_assessment' => 'Complete Skill Assessment',
        'manage_location_skills' => 'Manage Location Skills',
        'setup_skills' => 'Setup Skills & Categories',
        'assessment_form' => 'Access Assessment Forms',
        'skill_reports' => 'View Skill Reports'
    ];

    $readable_name = isset($permission_name_map[$permission])
        ? $permission_name_map[$permission]
        : ucwords(str_replace('_', ' ', $permission));

    $modules[$module]['permissions'][$permission] = $readable_name;
}

// Set proper module names and sidebar order (matching config.php MODULES order)
foreach ($modules as $module => $module_data) {
    switch ($module) {
        case 'dashboard':
            $modules[$module]['name'] = 'Dashboard';
            $modules[$module]['order'] = 0; // First in sidebar
            break;
        case 'admin':
            $modules[$module]['name'] = 'Admin Panel';
            $modules[$module]['order'] = 1; // Module 1
            break;
        case 'employee':
            $modules[$module]['name'] = 'Employee Data';
            $modules[$module]['order'] = 2; // Module 2
            break;
        case 'meal':
            $modules[$module]['name'] = 'Meal Management';
            $modules[$module]['order'] = 3; // Module 3
            break;
        case 'transport':
            $modules[$module]['name'] = 'Transport';
            $modules[$module]['order'] = 4; // Module 4
            break;
        case 'grievance':
            $modules[$module]['name'] = 'Grievance';
            $modules[$module]['order'] = 5; // Module 5
            break;
        case 'requests':
            $modules[$module]['name'] = 'Employee Requests';
            $modules[$module]['order'] = 6; // Module 6
            break;
        case 'events':
            $modules[$module]['name'] = 'Event Calendar';
            $modules[$module]['order'] = 7; // Module 7
            break;
        case 'medical':
            $modules[$module]['name'] = 'Medical';
            $modules[$module]['order'] = 8; // Module 8
            break;
        case 'onboarding':
            $modules[$module]['name'] = 'Onboarding';
            $modules[$module]['order'] = 9; // Module 9
            break;
        case 'offboarding':
            $modules[$module]['name'] = 'Offboarding';
            $modules[$module]['order'] = 10; // Module 10
            break;
        case 'training':
            $modules[$module]['name'] = 'Training';
            $modules[$module]['order'] = 11; // Module 11
            break;
        case 'goals':
            $modules[$module]['name'] = 'Goal Setting';
            $modules[$module]['order'] = 12; // Module 12
            break;
        case 'performance':
            $modules[$module]['name'] = 'Performance Appraisal';
            $modules[$module]['order'] = 13; // Module 13
            break;
        case 'key_talent':
            $modules[$module]['name'] = 'Key Talent Identification';
            $modules[$module]['order'] = 14; // Module 14
            break;
        case 'jobs':
            $modules[$module]['name'] = 'Job Descriptions';
            $modules[$module]['order'] = 15; // Module 15
            break;
        case 'skills':
            $modules[$module]['name'] = 'Skill Matrix';
            $modules[$module]['order'] = 16; // Module 16
            break;
        default:
            $modules[$module]['name'] = ucwords(str_replace('_', ' ', $module));
            $modules[$module]['order'] = 999; // Unknown modules at end
            break;
    }
}

// Sort modules by sidebar order
uasort($modules, function($a, $b) {
    return $a['order'] <=> $b['order'];
});

function hasRolePermissionCheck($module, $permission, $current_permissions) {
    return isset($current_permissions[$module]) && in_array($permission, $current_permissions[$module]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Permission Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --sidebar-width: 280px;
        }

        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }

        .dashboard-content {
            background: #f8f9fa;
            padding: 2rem;
            min-height: calc(100vh - 40px);
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(50px, -50px);
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .role-card {
            border-left: 4px solid var(--primary-color);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .role-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }

        .role-card.active {
            border-left-color: var(--success-color);
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(40, 167, 69, 0.05));
        }

        .module-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .module-card:hover {
            border-color: var(--primary-color);
        }

        .permission-item {
            padding: 0.5rem;
            margin: 0.25rem 0;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .permission-item:hover {
            background-color: #f8f9fa;
        }

        .form-check-input:checked {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }

        .role-level {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            background: var(--info-color);
            color: white;
        }

        .data-scope {
            font-size: 0.75rem;
            color: var(--secondary-color);
            font-style: italic;
        }

        .btn-custom {
            border-radius: 25px;
            padding: 0.75rem 2rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .module-toggle {
            cursor: pointer;
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
            background: #f8f9fa;
            margin: -1rem -1rem 0 -1rem;
            border-radius: 10px 10px 0 0;
        }

        .module-toggle:hover {
            background: #e9ecef;
        }

        .permissions-container {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }

        .permissions-container.show {
            max-height: none; /* Remove height restriction completely */
            height: auto; /* Allow natural height expansion */
            overflow: visible; /* Show all content */
        }

        /* Additional styles for better permission display */
        .permission-item {
            margin-bottom: 0.75rem;
            padding: 0.5rem;
            border-radius: 6px;
            background: #f8f9fa;
            transition: background-color 0.2s ease;
        }

        .permission-item:hover {
            background: #e9ecef;
        }

        .form-check-label {
            font-weight: 500;
            line-height: 1.4;
        }

        .permission-item small {
            font-size: 0.8rem;
            color: #6c757d !important;
            font-family: monospace;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }

            .dashboard-content {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include '../../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="dashboard-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h1 class="mb-0">
                            <i class="fas fa-shield-alt me-3"></i>
                            Permission Management
                        </h1>
                        <p class="mb-0 opacity-75">Configure role-based permissions for the 5 user types</p>
                    </div>
                    <div class="col-md-6 text-end">
                        <span class="badge bg-light text-dark fs-6">
                            <i class="fas fa-users me-2"></i>5 Role Types
                        </span>
                    </div>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Role Selection -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-user-tag me-2"></i>Select Role</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php foreach ($roles as $role_key => $role_info): ?>
                                <a href="?role=<?= $role_key ?>" class="text-decoration-none">
                                    <div class="role-card card-body <?= $selected_role === $role_key ? 'active' : '' ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">
                                                    <?= $role_info['name'] ?>
                                                    <span class="role-level ms-2">Level <?= $role_info['level'] ?></span>
                                                </h6>
                                                <p class="mb-1 small"><?= $role_info['description'] ?></p>
                                                <p class="mb-0 data-scope">Data Access: <?= $role_info['data_scope'] ?></p>
                                            </div>
                                            <?php if ($selected_role === $role_key): ?>
                                                <i class="fas fa-check-circle text-success"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Role Actions -->
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0"><i class="fas fa-tools me-2"></i>Role Actions</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" onsubmit="return confirm('This will reset all permissions to defaults. Continue?')">
                                <input type="hidden" name="role" value="<?= $selected_role ?>">
                                <button type="submit" name="reset_permissions" class="btn btn-warning btn-custom w-100 mb-3">
                                    <i class="fas fa-undo me-2"></i>Reset to Defaults
                                </button>
                            </form>

                            <div class="alert alert-info small">
                                <i class="fas fa-info-circle me-2"></i>
                                Reset will apply the recommended default permissions for this role type.
                                <?php if ($selected_role === 'superadmin'): ?>
                                <br><strong>SuperAdmin:</strong> Managing permissions for other roles.
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Permission Configuration -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-cog me-2"></i>
                                Permissions for: <?= $roles[$selected_role]['name'] ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (false): // Removed restriction - SuperAdmin should be able to manage permissions ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-lock-open text-success fa-4x mb-3"></i>
                                    <h4>Full System Access</h4>
                                    <p class="text-muted">SuperAdmin role has unrestricted access to all system modules and functions.</p>
                                </div>
                            <?php else: ?>
                                <form method="POST" id="permissionsForm">
                                    <input type="hidden" name="role" value="<?= $selected_role ?>">

                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <div>
                                            <h6 class="mb-0">Configure Module Permissions</h6>
                                            <small class="text-muted">Click module headers to expand/collapse</small>
                                        </div>
                                        <div>
                                            <button type="button" class="btn btn-outline-success btn-sm me-2" onclick="selectAll()">
                                                <i class="fas fa-check-double me-1"></i>Select All
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearAll()">
                                                <i class="fas fa-times me-1"></i>Clear All
                                            </button>
                                        </div>
                                    </div>

                                    <?php foreach ($modules as $module_key => $module_info): ?>
                                        <div class="module-card">
                                            <div class="module-toggle" onclick="toggleModule('<?= $module_key ?>')">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="mb-0">
                                                            <i class="fas fa-cube me-2"></i>
                                                            <?= $module_info['name'] ?>
                                                        </h6>
                                                        <small class="text-muted">
                                                            <?php
                                                            $granted = isset($current_permissions[$module_key]) ? count($current_permissions[$module_key]) : 0;
                                                            $total = count($module_info['permissions']);
                                                            echo "{$granted}/{$total} permissions granted";
                                                            ?>
                                                        </small>
                                                    </div>
                                                    <i class="fas fa-chevron-down toggle-icon" id="icon-<?= $module_key ?>"></i>
                                                </div>
                                            </div>

                                            <div class="permissions-container" id="permissions-<?= $module_key ?>">
                                                <div class="p-3">
                                                    <div class="row">
                                                        <?php foreach ($module_info['permissions'] as $permission_key => $permission_name): ?>
                                                            <div class="col-md-6">
                                                                <div class="permission-item">
                                                                    <div class="form-check">
                                                                        <input class="form-check-input permission-checkbox"
                                                                               type="checkbox"
                                                                               name="permissions[]"
                                                                               value="<?= $module_key ?>.<?= $permission_key ?>"
                                                                               id="<?= $module_key ?>_<?= $permission_key ?>"
                                                                               <?= hasRolePermissionCheck($module_key, $permission_key, $current_permissions) ? 'checked' : '' ?>>
                                                                        <label class="form-check-label" for="<?= $module_key ?>_<?= $permission_key ?>">
                                                                            <strong><?= $permission_name ?></strong>
                                                                            <br><small class="text-muted"><?= $module_key ?>.<?= $permission_key ?></small>
                                                                        </label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>

                                    <div class="text-center pt-4">
                                        <button type="submit" name="update_permissions" class="btn btn-primary btn-custom btn-lg">
                                            <i class="fas fa-save me-2"></i>Save Permissions
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary btn-custom">
                            <i class="fas fa-arrow-left me-2"></i>Back to Admin
                        </a>
                        <a href="permissions_central.php" class="btn btn-outline-primary btn-custom">
                            <i class="fas fa-eye me-2"></i>View Old System
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleModule(moduleKey) {
            const container = document.getElementById('permissions-' + moduleKey);
            const icon = document.getElementById('icon-' + moduleKey);

            if (container.classList.contains('show')) {
                container.classList.remove('show');
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            } else {
                container.classList.add('show');
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            }
        }

        function selectAll() {
            document.querySelectorAll('.permission-checkbox').forEach(checkbox => {
                checkbox.checked = true;
            });
        }

        function clearAll() {
            document.querySelectorAll('.permission-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
        }

        // Auto-expand first module
        document.addEventListener('DOMContentLoaded', function() {
            const firstModule = document.querySelector('.permissions-container');
            if (firstModule) {
                firstModule.classList.add('show');
                const firstIcon = document.querySelector('.toggle-icon');
                if (firstIcon) {
                    firstIcon.classList.remove('fa-chevron-down');
                    firstIcon.classList.add('fa-chevron-up');
                }
            }
        });
    </script>
</body>
</html>