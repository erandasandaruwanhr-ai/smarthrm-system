<?php
// Sidebar Navigation for SmartHRM System
$current_page = basename($_SERVER['PHP_SELF']);
$current_module = basename(dirname($_SERVER['PHP_SELF']));

// Include auth check which contains the core permission functions
require_once __DIR__ . '/../config/config.php';

// Check if auth_check functions are available, if not include them
if (!function_exists('hasAdminAccess')) {
    require_once __DIR__ . '/auth_check.php';
}
?>

<div class="sidebar">
    <div class="sidebar-header">
        <h4><i class="fas fa-building"></i> SmartHRM</h4>
        <small>HR Management System</small>
    </div>

    <div class="scroll-container">
        <div class="sidebar-menu">
            <div class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>dashboard.php"
                   class="sidebar-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
            </div>

            <?php
            // Initialize database connection for permission checking
            $db = new Database();

            foreach (MODULES as $module_id => $module):
                // Check if user has permission to access this module
                $canAccess = false;

                // Get notification counts for specific modules
                $notification_count = 0;

                switch ($module_id) {
                    case 1: // Admin Panel - Only specific admin permissions or SuperAdmin
                        $canAccess = hasModulePermission($db, 'admin.system_config') ||
                                   hasModulePermission($db, 'admin.password_management') ||
                                   hasModulePermission($db, 'admin.account_types') ||
                                   hasModulePermission($db, 'admin.employment_levels') ||
                                   hasModulePermission($db, 'admin.locations') ||
                                   hasModulePermission($db, 'admin.dropdown_management') ||
                                   isSuperAdmin();
                        break;
                    case 2: // Employee Data - Strict permission check
                        $canAccess = hasModulePermission($db, 'employee.view') ||
                                   hasModulePermission($db, 'employee.view_own') ||
                                   hasModulePermission($db, 'employee.view_direct_reports') ||
                                   hasModulePermission($db, 'employee.view_hierarchy') ||
                                   hasModulePermission($db, 'employee.employee_list') ||
                                   hasModulePermission($db, 'employee.employee_form') ||
                                   hasModulePermission($db, 'employee.data_monitor') ||
                                   hasModulePermission($db, 'employee.org_chart') ||
                                   hasModulePermission($db, 'employee.location_wide') ||
                                   isSuperAdmin();
                        break;
                    case 3: // Meal Management - Strict permission check
                        $canAccess = hasModulePermission($db, 'meal.employee_meal_request') ||
                                   hasModulePermission($db, 'meal.my_meal_history') ||
                                   hasModulePermission($db, 'meal.visitor_meal_request') ||
                                   hasModulePermission($db, 'meal.employee_meal_counter') ||
                                   hasModulePermission($db, 'meal.visitor_meal_counter') ||
                                   hasModulePermission($db, 'meal.meal_analytics') ||
                                   hasModulePermission($db, 'meal.meal_time_manager') ||
                                   hasModulePermission($db, 'meal.meal_reports') ||
                                   hasModulePermission($db, 'meal.daily_overview') ||
                                   hasModulePermission($db, 'meal.view') ||
                                   isSuperAdmin();
                        break;
                    case 4: // Transport - Strict permission check
                        $canAccess = hasModulePermission($db, 'transport.transport_request') ||
                                   hasModulePermission($db, 'transport.transport_allocation') ||
                                   hasModulePermission($db, 'transport.vehicle_register') ||
                                   hasModulePermission($db, 'transport.vehicle_pool') ||
                                   hasModulePermission($db, 'transport.driver_pool') ||
                                   hasModulePermission($db, 'transport.view') ||
                                   isSuperAdmin();

                        // Get transport notifications count (safe integration)
                        if ($canAccess) {
                            try {
                                require_once __DIR__ . '/../modules/transport/TransportNotifications.php';
                                $transport_notifications = TransportNotifications::getInstance($db);
                                $user_data = getCurrentUser();
                                $notification_count = $transport_notifications->getTransportNotificationCount($user_data);
                            } catch (Exception $e) {
                                // Silent fail - don't break sidebar functionality
                                error_log("Transport notification error in sidebar: " . $e->getMessage());
                                $notification_count = 0;
                            }
                        }
                        break;
                    case 5: // Grievance - Strict permission check
                        $canAccess = hasModulePermission($db, 'grievance.submit_grievance') ||
                                   hasModulePermission($db, 'grievance.my_grievances') ||
                                   hasModulePermission($db, 'grievance.view_team_grievances') ||
                                   hasModulePermission($db, 'grievance.case_management') ||
                                   hasModulePermission($db, 'grievance.grievance_list') ||
                                   hasModulePermission($db, 'grievance.reports') ||
                                   hasModulePermission($db, 'grievance.view') ||
                                   isSuperAdmin();

                        // Get grievance notifications count (safe integration)
                        if ($canAccess) {
                            try {
                                $user_data = getCurrentUser();
                                $user_account_type = $user_data['account_type'] ?? 'user';

                                if (in_array($user_account_type, ['supervisor', 'manager', 'admin', 'superadmin'])) {
                                    $user_location = $user_data['location'] ?? $user_data['location_name'] ?? null;

                                    // Get location from database if not in session
                                    if (!$user_location && isset($user_data['epf_number'])) {
                                        $location_data = $db->fetch("
                                            SELECT l.location_name
                                            FROM employees e
                                            LEFT JOIN locations l ON e.location_id = l.id
                                            WHERE e.epf_number = ?
                                        ", [$user_data['epf_number']]);

                                        if ($location_data) {
                                            $user_location = $location_data['location_name'];
                                        }
                                    }

                                    if ($user_account_type === 'superadmin') {
                                        // SuperAdmin sees all normal grievances + all anonymous grievances
                                        $normal_result = $db->fetch("
                                            SELECT COUNT(*) as count
                                            FROM grievances g
                                            WHERE g.status IN ('Open', 'Under Supervisory Review', 'Under Managerial Review', 'In Progress', 'Reopened')
                                        ");
                                        $normal_count = $normal_result ? (int)$normal_result['count'] : 0;

                                        $anonymous_result = $db->fetch("
                                            SELECT COUNT(*) as count
                                            FROM anonymous_grievances ag
                                            WHERE ag.status IN ('Opened', 'Reviewing', 'Action', 'Under Investigation', 'Open')
                                        ");
                                        $anonymous_count = $anonymous_result ? (int)$anonymous_result['count'] : 0;

                                        $notification_count = $normal_count + $anonymous_count;

                                    } elseif (in_array($user_account_type, ['admin', 'manager', 'supervisor'])) {
                                        // Admin/Manager/Supervisor sees normal grievances from their location only
                                        if ($user_location) {
                                            $result = $db->fetch("
                                                SELECT COUNT(*) as count
                                                FROM grievances g
                                                WHERE g.status IN ('Open', 'Under Supervisory Review', 'Under Managerial Review', 'In Progress', 'Reopened')
                                                AND g.employee_location = ?
                                            ", [$user_location]);
                                            $notification_count = $result ? (int)$result['count'] : 0;
                                        }
                                    }
                                }
                            } catch (Exception $e) {
                                // Silent fail - don't break sidebar functionality
                                error_log("Grievance notification error in sidebar: " . $e->getMessage());
                                $notification_count = 0;
                            }
                        }
                        break;
                    case 6: // Employee Requests - Strict permission check
                        $canAccess = hasModulePermission($db, 'requests.submit_request') ||
                                   hasModulePermission($db, 'requests.my_requests') ||
                                   hasModulePermission($db, 'requests.view_team_requests') ||
                                   hasModulePermission($db, 'requests.all_requests') ||
                                   hasModulePermission($db, 'requests.approve_supervisor') ||
                                   hasModulePermission($db, 'requests.approve_manager') ||
                                   hasModulePermission($db, 'requests.approve_admin') ||
                                   hasModulePermission($db, 'requests.reports') ||
                                   hasModulePermission($db, 'requests.view') ||
                                   isSuperAdmin();

                        // Get request notifications count for SuperAdmin only (safe integration)
                        if ($canAccess) {
                            try {
                                $user_data = getCurrentUser();
                                $user_account_type = $user_data['account_type'] ?? 'user';

                                if ($user_account_type === 'superadmin') {
                                    // Only SuperAdmin gets request notifications
                                    $result = $db->fetch("
                                        SELECT COUNT(*) as count
                                        FROM employee_requests er
                                        WHERE er.status IN ('Pending Supervisor', 'Processing', 'Documents Pending', 'Pending Manager')
                                    ");
                                    $notification_count = $result ? (int)$result['count'] : 0;
                                }
                            } catch (Exception $e) {
                                // Silent fail - don't break sidebar functionality
                                error_log("Request notification error in sidebar: " . $e->getMessage());
                                $notification_count = 0;
                            }
                        }
                        break;
                    case 7: // Event Calendar - Strict permission check
                        $canAccess = hasModulePermission($db, 'events.calendar_view') ||
                                   hasModulePermission($db, 'events.event_list') ||
                                   hasModulePermission($db, 'events.event_management') ||
                                   hasModulePermission($db, 'events.view') ||
                                   isSuperAdmin();
                        break;
                    case 8: // Medical - Strict permission check (admin has NO access)
                        $canAccess = hasModulePermission($db, 'medical.monthly_claims') ||
                                   hasModulePermission($db, 'medical.balance_checker') ||
                                   hasModulePermission($db, 'medical.spectacles_claims') ||
                                   hasModulePermission($db, 'medical.view') ||
                                   isSuperAdmin();
                        break;
                    case 9: // Onboarding - Strict permission check (admin access controlled by permissions)
                        $canAccess = hasModulePermission($db, 'onboarding.new_onboarding') ||
                                   hasModulePermission($db, 'onboarding.onboarding_list') ||
                                   hasModulePermission($db, 'onboarding.onboarding_reports') ||
                                   hasModulePermission($db, 'onboarding.view') ||
                                   isSuperAdmin();
                        break;
                    case 10: // Offboarding - Strict permission check
                        $canAccess = hasModulePermission($db, 'offboarding.resignation_form') ||
                                   hasModulePermission($db, 'offboarding.exit_interview') ||
                                   hasModulePermission($db, 'offboarding.clearance_form') ||
                                   hasModulePermission($db, 'offboarding.resignation_approvals') ||
                                   hasModulePermission($db, 'offboarding.clearance_verification') ||
                                   hasModulePermission($db, 'offboarding.offboarding_list') ||
                                   hasModulePermission($db, 'offboarding.offboarding_reports') ||
                                   hasModulePermission($db, 'offboarding.view') ||
                                   isSuperAdmin();
                        break;
                    case 11: // Training - Strict permission check
                        $canAccess = hasModulePermission($db, 'training.requirements') ||
                                   hasModulePermission($db, 'training.plans') ||
                                   hasModulePermission($db, 'training.evaluation') ||
                                   hasModulePermission($db, 'training.feedback') ||
                                   hasModulePermission($db, 'training.tracker') ||
                                   hasModulePermission($db, 'training.budget') ||
                                   hasModulePermission($db, 'training.management') ||
                                   hasModulePermission($db, 'training.reports') ||
                                   hasModulePermission($db, 'training.view') ||
                                   isSuperAdmin();
                        break;
                    case 12: // Goal Setting - Strict permission check
                        $canAccess = hasModulePermission($db, 'goals.goal_forms') ||
                                   hasModulePermission($db, 'goals.executive_list') ||
                                   hasModulePermission($db, 'goals.kpi_tracker') ||
                                   hasModulePermission($db, 'goals.mid_year_review') ||
                                   hasModulePermission($db, 'goals.final_evaluation') ||
                                   hasModulePermission($db, 'goals.form_setup') ||
                                   hasModulePermission($db, 'goals.reports_analytics') ||
                                   hasModulePermission($db, 'goals.view') ||
                                   isSuperAdmin();
                        break;
                    case 13: // Performance Appraisal - Strict permission check
                        $canAccess = hasModulePermission($db, 'performance.staff_appraisal') ||
                                   hasModulePermission($db, 'performance.staff_marks_data') ||
                                   hasModulePermission($db, 'performance.staff_list') ||
                                   hasModulePermission($db, 'performance.staff_appraisal_setup') ||
                                   hasModulePermission($db, 'performance.executive_list') ||
                                   hasModulePermission($db, 'performance.executive_appraisal') ||
                                   hasModulePermission($db, 'performance.executive_appraisal_setup') ||
                                   hasModulePermission($db, 'performance.reports_analytics') ||
                                   hasModulePermission($db, 'performance.view') ||
                                   isSuperAdmin();
                        break;
                    case 14: // Key Talent Identification - Strict permission check
                        $canAccess = hasModulePermission($db, 'key_talent.assessment_form') ||
                                   hasModulePermission($db, 'key_talent.talent_grid') ||
                                   hasModulePermission($db, 'key_talent.talent_candidates') ||
                                   hasModulePermission($db, 'key_talent.kti_form_setup') ||
                                   hasModulePermission($db, 'key_talent.marks_allocations') ||
                                   hasModulePermission($db, 'key_talent.talent_reports') ||
                                   hasModulePermission($db, 'key_talent.view') ||
                                   isSuperAdmin();
                        break;
                    case 15: // Skill Matrix - Strict permission check
                        $canAccess = hasModulePermission($db, 'skill.view') ||
                                   hasModulePermission($db, 'skill.view_skill_matrix') ||
                                   hasModulePermission($db, 'skill.complete_skill_assessment') ||
                                   hasModulePermission($db, 'skill.skill_assessment') ||
                                   hasModulePermission($db, 'skill.assessment_reports') ||
                                   hasModulePermission($db, 'skill.employee_list') ||
                                   hasModulePermission($db, 'skill.matrix_setup') ||
                                   isSuperAdmin();
                        break;
                    default:
                        // For unknown modules, deny access by default
                        $canAccess = isSuperAdmin();
                        break;
                }

                // Only show the module if user has access
                if ($canAccess):
            ?>
                <div class="sidebar-item">
                    <a href="<?php echo BASE_URL . $module['url']; ?>"
                       class="sidebar-link <?php echo ($current_module == basename(dirname($module['url']))) ? 'active' : ''; ?>">
                        <i class="<?php echo $module['icon']; ?>"></i>
                        <?php echo $module['name']; ?>
                        <div class="notification-bell" data-module="<?php echo $module_id; ?>">
                            <i class="fas fa-bell"></i>
                            <span class="notification-count <?php echo ($notification_count > 0) ? '' : 'hidden'; ?>" data-count="<?php echo $notification_count; ?>"><?php echo $notification_count; ?></span>
                        </div>
                    </a>
                </div>
            <?php
                endif;

                // DEBUG: Show if module 6 is being processed
                if ($module_id == 6) {
                    echo "<!-- DEBUG: Module 6 processed. canAccess = " . ($canAccess ? 'TRUE' : 'FALSE') . " -->";
                }
            endforeach;
            ?>

        </div>
    </div>

    <!-- Fixed Logout at Bottom -->
    <div class="sidebar-footer">
        <a href="<?php echo BASE_URL; ?>auth/logout.php" class="sidebar-link logout-link">
            <i class="fas fa-sign-out-alt"></i>
            Logout
        </a>
    </div>

</div>

<style>
:root {
    --sidebar-width: 280px;
}

.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    width: var(--sidebar-width);
    background: linear-gradient(180deg, #007bff 0%, #0056b3 100%);
    color: white;
    overflow-y: auto;
    z-index: 1000;
    transition: all 0.3s ease;
    box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15);
}

.sidebar-header {
    padding: 2rem 1.5rem;
    text-align: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.15);
    background: rgba(255, 255, 255, 0.05);
    position: relative;
    overflow: hidden;
}

.sidebar-header::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 100px;
    height: 100px;
    background: rgba(255, 255, 255, 0.08);
    border-radius: 50%;
    transform: translate(30px, -30px);
}

.sidebar-header h4 {
    margin: 0;
    font-weight: 700;
    color: white;
    font-size: 1.4rem;
    position: relative;
    z-index: 2;
}

.sidebar-header small {
    color: rgba(255, 255, 255, 0.85);
    font-weight: 500;
    position: relative;
    z-index: 2;
}

.sidebar-menu {
    padding: 1rem 0;
}

.sidebar-item {
    margin: 0.5rem 0;
}

.sidebar-link {
    display: flex;
    align-items: center;
    padding: 0.85rem 1.5rem;
    color: rgba(255, 255, 255, 0.9);
    text-decoration: none;
    transition: all 0.3s ease;
    border-left: 3px solid transparent;
    position: relative;
    font-weight: 500;
}

.sidebar-link:hover,
.sidebar-link.active {
    background: rgba(255, 255, 255, 0.15);
    color: white;
    border-left-color: white;
    text-decoration: none;
    padding-left: 2rem;
    box-shadow: inset 0 0 20px rgba(255, 255, 255, 0.1);
}

.sidebar-link i {
    width: 20px;
    margin-right: 0.75rem;
    font-size: 1rem;
}

.main-content {
    margin-left: var(--sidebar-width);
    min-height: 100vh;
}

.scroll-container {
    max-height: calc(100vh - 220px);
    overflow-y: auto;
}

.scroll-container::-webkit-scrollbar {
    width: 6px;
}

.scroll-container::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
}

.scroll-container::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 10px;
}

.sidebar-footer {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 1rem 0;
    border-top: 1px solid rgba(255, 255, 255, 0.15);
    background: rgba(0, 0, 0, 0.1);
}

.logout-link {
    margin: 0 !important;
}

.logout-link:hover {
    background: rgba(220, 53, 69, 0.2) !important;
    border-left-color: #dc3545 !important;
    color: #ff6b7d !important;
}

/* Notification Bell Styles */
.notification-bell {
    position: relative;
    display: inline-block;
    margin-right: 10px;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.notification-bell i {
    color: #FFA500;
    filter: drop-shadow(0 0 2px rgba(255, 165, 0, 0.5));
}

.notification-bell:hover i {
    color: #FFD700;
    transform: scale(1.1);
    filter: drop-shadow(0 0 4px rgba(255, 165, 0, 0.8));
}

.notification-count {
    position: absolute;
    top: -8px;
    right: -8px;
    background: #FF4444; /* Red for notifications */
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 0.7rem;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    animation: pulse-notification 2s infinite;
}

.notification-count[data-count="0"],
.notification-count.hidden {
    display: none; /* Hide when no notifications */
}

.notification-count:not([data-count="0"]):not(.hidden) {
    display: flex; /* Show red circle for 1+ */
}

@keyframes pulse-notification {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.sidebar-link {
    display: flex;
    align-items: center;
    justify-content: flex-start;
}

.sidebar-link .notification-bell {
    margin-left: auto;
}

@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
    }

    .sidebar.show {
        transform: translateX(0);
    }

    .main-content {
        margin-left: 0;
    }
}
</style>

<script>
// Save and restore sidebar scroll position
document.addEventListener('DOMContentLoaded', function() {
    const scrollContainer = document.querySelector('.scroll-container');
    const sidebarLinks = document.querySelectorAll('.sidebar-link');

    if (scrollContainer) {
        // Restore scroll position on page load
        const savedScrollPosition = sessionStorage.getItem('sidebarScrollPosition');
        if (savedScrollPosition) {
            scrollContainer.scrollTop = parseInt(savedScrollPosition);
        }

        // Save scroll position before navigating
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function() {
                sessionStorage.setItem('sidebarScrollPosition', scrollContainer.scrollTop);
            });
        });

        // Also save scroll position when scrolling
        scrollContainer.addEventListener('scroll', function() {
            sessionStorage.setItem('sidebarScrollPosition', this.scrollTop);
        });
    }

    // Global sidebar toggle for mobile
    const sidebarToggle = document.getElementById('sidebar-toggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    }
});
</script>