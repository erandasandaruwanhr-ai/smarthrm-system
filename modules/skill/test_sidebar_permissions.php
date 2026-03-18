<?php
session_start();
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

try {
    $db = new Database();
    $user = getCurrentUser();

    echo "<h3>Sidebar Permissions Test</h3>";
    echo "<p><strong>User:</strong> " . htmlspecialchars($user['name']) . " (" . htmlspecialchars($user['account_type']) . ")</p>";

    echo "<h4>Module Access Test:</h4>";

    // Test each module access using the same logic as sidebar
    $module_tests = [
        1 => ['name' => 'Admin Panel', 'permissions' => ['admin.system_config', 'admin.password_management']],
        2 => ['name' => 'Employee Data', 'permissions' => ['employee.view', 'employee.employee_list', 'employee.data_monitor', 'employee.org_chart']],
        3 => ['name' => 'Meal Management', 'permissions' => ['meal.employee_meal_request', 'meal.my_meal_history', 'meal.visitor_meal_request', 'meal.employee_meal_counter', 'meal.meal_time_manager']],
        4 => ['name' => 'Transport', 'permissions' => ['transport.transport_request', 'transport.transport_allocation', 'transport.vehicle_register', 'transport.vehicle_pool', 'transport.driver_pool']],
        5 => ['name' => 'Grievance', 'permissions' => ['grievance.submit_grievance', 'grievance.my_grievances', 'grievance.case_management', 'grievance.grievance_list', 'grievance.reports_analytics']],
        6 => ['name' => 'Employee Requests', 'permissions' => ['requests.submit_request', 'requests.my_requests', 'requests.all_requests', 'requests.approve_request']],
        7 => ['name' => 'Event Calendar', 'permissions' => ['events.calendar_view', 'events.add_event', 'events.event_management']],
        8 => ['name' => 'Medical', 'permissions' => ['medical.submit_claim', 'medical.balance_checker', 'medical.monthly_claims']],
        9 => ['name' => 'Onboarding', 'permissions' => ['onboarding.new_onboarding', 'onboarding.onboarding_list', 'onboarding.submit_onboarding']],
        10 => ['name' => 'Offboarding', 'permissions' => ['offboarding.resignation_form', 'offboarding.clearance_form', 'offboarding.exit_interview']],
        11 => ['name' => 'Training', 'permissions' => ['training.requirements', 'training.plans', 'training.evaluation']],
        12 => ['name' => 'Goal Setting', 'permissions' => ['goals.goal_forms', 'goals.submit_goal_form', 'goals.kpi_tracker', 'goals.executive_list']],
        13 => ['name' => 'Performance Appraisal', 'permissions' => ['performance.staff_appraisal', 'performance.executive_appraisal', 'performance.submit_appraisal', 'performance.executive_list']],
        14 => ['name' => 'Key Talent Identification', 'permissions' => ['key_talent.assessment_form', 'key_talent.talent_grid', 'key_talent.talent_candidates']],
        15 => ['name' => 'Skill Matrix', 'permissions' => ['skills.view_skill_matrix', 'skills.complete_skill_assessment', 'skills.manage_location_skills']]
    ];

    foreach ($module_tests as $module_id => $module_info) {
        echo "<div style='margin: 10px 0; padding: 10px; border: 1px solid #ddd; border-radius: 5px;'>";
        echo "<strong>Module {$module_id}: {$module_info['name']}</strong><br>";

        $hasAnyPermission = false;
        $permissions_status = [];

        foreach ($module_info['permissions'] as $permission) {
            $hasPermission = hasModulePermission($db, $permission);
            $permissions_status[] = "$permission: " . ($hasPermission ? '✓' : '✗');
            if ($hasPermission) {
                $hasAnyPermission = true;
            }
        }

        // Check admin/superadmin override
        $isAdminOrSuperAdmin = isAdmin() || isSuperAdmin();
        if ($isAdminOrSuperAdmin) {
            $hasAnyPermission = true;
        }

        echo "<small>" . implode(', ', $permissions_status) . "</small><br>";
        echo "<strong>Access Result: </strong>";
        if ($hasAnyPermission) {
            echo "<span style='color: green;'>✓ GRANTED</span>";
        } else {
            echo "<span style='color: red;'>✗ DENIED</span>";
        }

        if ($isAdminOrSuperAdmin) {
            echo " <small>(Admin/SuperAdmin override)</small>";
        }

        echo "</div>";
    }

    echo "<br><h4>Admin/SuperAdmin Status:</h4>";
    echo "isAdmin(): " . (isAdmin() ? '<span style="color: green;">✓ TRUE</span>' : '<span style="color: red;">✗ FALSE</span>') . "<br>";
    echo "isSuperAdmin(): " . (isSuperAdmin() ? '<span style="color: green;">✓ TRUE</span>' : '<span style="color: red;">✗ FALSE</span>') . "<br>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

<style>
body { font-family: Arial, sans-serif; padding: 20px; }
</style>