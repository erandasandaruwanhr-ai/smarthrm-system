<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

if (!isSuperAdmin()) {
    header('Location: ../../dashboard.php');
    exit();
}

$db = new Database();

// Clear all existing permissions
$db->query("DELETE FROM account_permissions");

// Define hierarchical permissions
$permissions_by_level = [
    // Level 1 - User: Only their own data
    1 => [
        'dashboard.view',
        'employee.view_own',
        'employee.edit_own',
        'meal.employee_meal.view',
        'meal.employee_meal.submit'
    ],

    // Level 2 - Supervisor: View all reports, edit direct reports
    2 => [
        'dashboard.view',
        'employee.view',
        'employee.view_reports',
        'employee.edit_direct_reports',
        'employee.employee_list.view',
        'employee.org_chart.view',
        'employee.org_chart.tree_view',
        'employee.org_chart.list_view',
        'employee.data_monitor.view',
        'employee.data_monitor.gender_chart',
        'employee.data_monitor.location_chart',
        'employee.data_monitor.employment_chart',
        'employee.data_monitor.age_chart',
        'employee.data_monitor.department_chart',
        'meal.view',
        'meal.employee_meal.view',
        'meal.employee_meal.submit',
        'meal.employee_counter.view',
        'transport.view',
        'transport.transport_request.view',
        'transport.transport_request.submit'
    ],

    // Level 3 - Manager: All reports access
    3 => [
        'dashboard.view',
        'employee.view',
        'employee.view_reports',
        'employee.edit_reports',
        'employee.employee_form.view',
        'employee.employee_form.edit',
        'employee.employee_list.view',
        'employee.employee_list.filter',
        'employee.org_chart.view',
        'employee.org_chart.tree_view',
        'employee.org_chart.list_view',
        'employee.org_chart.export_pdf',
        'employee.org_chart.export_png',
        'employee.data_monitor.view',
        'employee.data_monitor.gender_chart',
        'employee.data_monitor.location_chart',
        'employee.data_monitor.employment_chart',
        'employee.data_monitor.age_chart',
        'employee.data_monitor.department_chart',
        'meal.view',
        'meal.employee_meal.view',
        'meal.employee_meal.submit',
        'meal.visitor_meal.view',
        'meal.visitor_meal.submit',
        'meal.employee_counter.view',
        'meal.visitor_counter.view',
        'meal.request_view.view',
        'meal.request_view.edit',
        'meal.charts.view',
        'transport.view',
        'transport.vehicle_pool.view',
        'transport.transport_request.view',
        'transport.transport_request.submit',
        'transport.transport_allocation.view',
        'grievance.view',
        'grievance.submission.view',
        'grievance.submission.submit',
        'grievance.case_management.view'
    ],

    // Level 4 - Admin: Location-wide access
    4 => [
        'dashboard.view',
        'employee.view',
        'employee.view_location',
        'employee.edit_location',
        'employee.employee_form.view',
        'employee.employee_form.add',
        'employee.employee_form.edit',
        'employee.employee_form.delete',
        'employee.employee_form.bulk_upload',
        'employee.employee_form.template_download',
        'employee.employee_list.view',
        'employee.employee_list.filter',
        'employee.org_chart.view',
        'employee.org_chart.tree_view',
        'employee.org_chart.list_view',
        'employee.org_chart.export_pdf',
        'employee.org_chart.export_png',
        'employee.org_chart.print',
        'employee.data_monitor.view',
        'employee.data_monitor.gender_chart',
        'employee.data_monitor.location_chart',
        'employee.data_monitor.employment_chart',
        'employee.data_monitor.age_chart',
        'employee.data_monitor.department_chart',
        'admin.view',
        'admin.locations.view',
        'admin.locations.edit',
        'admin.employment_levels.view',
        'admin.dropdown_management.view',
        'admin.dropdown_management.add',
        'admin.dropdown_management.edit',
        'admin.dropdown_management.delete',
        'admin.password_management.view',
        'admin.password_management.reset_password',
        'meal.view',
        'meal.employee_meal.view',
        'meal.employee_meal.submit',
        'meal.visitor_meal.view',
        'meal.visitor_meal.submit',
        'meal.employee_counter.view',
        'meal.visitor_counter.view',
        'meal.request_view.view',
        'meal.request_view.edit',
        'meal.request_view.delete',
        'meal.charts.view',
        'meal.time_manager.view',
        'meal.time_manager.edit',
        'transport.view',
        'transport.vehicle_register.view',
        'transport.vehicle_register.add',
        'transport.vehicle_register.edit',
        'transport.vehicle_register.delete',
        'transport.vehicle_pool.view',
        'transport.vehicle_pool.in_out_switch',
        'transport.transport_request.view',
        'transport.transport_request.submit',
        'transport.driver_pool.view',
        'transport.driver_pool.add',
        'transport.driver_pool.delete',
        'transport.driver_pool.duty_switch',
        'transport.transport_allocation.view',
        'transport.transport_allocation.assign',
        'transport.time_management.view',
        'transport.time_management.edit',
        'grievance.view',
        'grievance.submission.view',
        'grievance.submission.submit',
        'grievance.case_management.view',
        'grievance.case_management.assign',
        'grievance.case_management.update_status',
        'grievance.case_management.add_notes',
        'grievance.case_management.upload_docs',
        'grievance.resolution.view',
        'grievance.resolution.approve',
        'grievance.resolution.reject'
    ]
];

// Add permissions for each account level
foreach ($permissions_by_level as $account_type_id => $permissions) {
    foreach ($permissions as $permission) {
        // Parse permission to get module and action
        $parts = explode('.', $permission);
        $module = $parts[0];

        $db->query(
            "INSERT INTO account_permissions (account_type_id, module_name, action_name) VALUES (?, ?, ?)",
            [$account_type_id, $module, $permission]
        );
    }
}

// Add ALL permissions for superadmin (level 5)
$all_permissions = [
    'dashboard.view',
    'admin.view',
    'admin.system_config.view',
    'admin.system_config.color_management',
    'admin.system_config.calendar_setup',
    'admin.account_types.view',
    'admin.account_types.add',
    'admin.account_types.edit',
    'admin.account_types.delete',
    'admin.permissions.view',
    'admin.permissions.assign',
    'admin.locations.view',
    'admin.locations.add',
    'admin.locations.edit',
    'admin.locations.delete',
    'admin.employment_levels.view',
    'admin.employment_levels.add',
    'admin.employment_levels.edit',
    'admin.employment_levels.delete',
    'admin.password_management.view',
    'admin.password_management.reset_password',
    'admin.password_management.change_default',
    'admin.dropdown_management.view',
    'admin.dropdown_management.add',
    'admin.dropdown_management.edit',
    'admin.dropdown_management.delete',
    'employee.view',
    'employee.employee_form.view',
    'employee.employee_form.add',
    'employee.employee_form.edit',
    'employee.employee_form.delete',
    'employee.employee_form.bulk_upload',
    'employee.employee_form.template_download',
    'employee.employee_list.view',
    'employee.employee_list.filter',
    'employee.data_monitor.view',
    'employee.data_monitor.gender_chart',
    'employee.data_monitor.location_chart',
    'employee.data_monitor.employment_chart',
    'employee.data_monitor.age_chart',
    'employee.data_monitor.department_chart',
    'employee.org_chart.view',
    'employee.org_chart.tree_view',
    'employee.org_chart.list_view',
    'employee.org_chart.export_pdf',
    'employee.org_chart.export_png',
    'employee.org_chart.print',
    'meal.view',
    'meal.employee_meal.view',
    'meal.employee_meal.submit',
    'meal.visitor_meal.view',
    'meal.visitor_meal.submit',
    'meal.employee_counter.view',
    'meal.visitor_counter.view',
    'meal.request_view.view',
    'meal.request_view.edit',
    'meal.request_view.delete',
    'meal.charts.view',
    'meal.time_manager.view',
    'meal.time_manager.edit',
    'transport.view',
    'transport.vehicle_register.view',
    'transport.vehicle_register.add',
    'transport.vehicle_register.edit',
    'transport.vehicle_register.delete',
    'transport.vehicle_pool.view',
    'transport.vehicle_pool.in_out_switch',
    'transport.transport_request.view',
    'transport.transport_request.submit',
    'transport.driver_pool.view',
    'transport.driver_pool.add',
    'transport.driver_pool.delete',
    'transport.driver_pool.duty_switch',
    'transport.transport_allocation.view',
    'transport.transport_allocation.assign',
    'transport.time_management.view',
    'transport.time_management.edit',
    'grievance.view',
    'grievance.submission.view',
    'grievance.submission.submit',
    'grievance.case_management.view',
    'grievance.case_management.assign',
    'grievance.case_management.update_status',
    'grievance.case_management.add_notes',
    'grievance.case_management.upload_docs',
    'grievance.resolution.view',
    'grievance.resolution.approve',
    'grievance.resolution.reject',
    'grievance.resolution.request_revision',
    'grievance.appeal.view',
    'grievance.appeal.submit_appeal',
    'grievance.appeal.review_appeal',
    'grievance.appeal.final_decision'
];

foreach ($all_permissions as $permission) {
    $parts = explode('.', $permission);
    $module = $parts[0];

    $db->query(
        "INSERT INTO account_permissions (account_type_id, module_name, action_name) VALUES (?, ?, ?)",
        [5, $module, $permission]
    );
}

$total_permissions = $db->fetchAll("SELECT COUNT(*) as total FROM account_permissions")[0]['total'];

echo "✅ Default permissions setup completed!\n";
echo "📊 Total permissions created: " . $total_permissions . "\n";
echo "🔐 Hierarchical access control is now active.\n";
echo "\nPermissions by Level:\n";

for ($level = 1; $level <= 5; $level++) {
    $count = $db->fetchAll("SELECT COUNT(*) as count FROM account_permissions WHERE account_type_id = ?", [$level])[0]['count'];
    $level_names = [1 => 'User', 2 => 'Supervisor', 3 => 'Manager', 4 => 'Admin', 5 => 'Superadmin'];
    echo "Level {$level} ({$level_names[$level]}): {$count} permissions\n";
}
?>