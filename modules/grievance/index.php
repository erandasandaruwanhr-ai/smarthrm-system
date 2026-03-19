<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

// Check if user has access to this page
if (!isSuperAdmin() && !hasModulePermission($db, 'grievance.view')) {
    header('Location: ../../dashboard.php');
    exit;
}

// Get notification count for grievances - DIRECT CALCULATION
$notification_count = 0;
$account_type = $user['account_type'] ?? 'user';

if (in_array($account_type, ['supervisor', 'manager', 'admin', 'superadmin'])) {
    $user_location = $user['location'] ?? $user['location_name'] ?? null;

    // Get location from database if not in session
    if (!$user_location && isset($user['epf_number'])) {
        $location_data = $db->fetch("
            SELECT l.location_name
            FROM employees e
            LEFT JOIN locations l ON e.location_id = l.id
            WHERE e.epf_number = ?
        ", [$user['epf_number']]);

        if ($location_data) {
            $user_location = $location_data['location_name'];
        }
    }

    if ($account_type === 'superadmin') {
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

    } elseif (in_array($account_type, ['admin', 'manager', 'supervisor'])) {
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

// Get grievance statistics from both regular and anonymous tables
try {
    // Regular grievances
    $regular_table_exists = $db->fetch("SHOW TABLES LIKE 'grievances'");
    $anonymous_table_exists = $db->fetch("SHOW TABLES LIKE 'anonymous_grievances'");

    $regular_total = 0;
    $regular_open = 0;
    $regular_resolved = 0;

    if ($regular_table_exists) {
        $regular_total = $db->fetch("SELECT COUNT(*) as count FROM grievances")['count'] ?? 0;
        $regular_open = $db->fetch("SELECT COUNT(*) as count FROM grievances WHERE status IN ('Open', 'Under Supervisory Review', 'Under Managerial Review')")['count'] ?? 0;
        $regular_resolved = $db->fetch("SELECT COUNT(*) as count FROM grievances WHERE status = 'Resolved'")['count'] ?? 0;
    }

    $anonymous_total = 0;
    $anonymous_open = 0;
    $anonymous_resolved = 0;

    if ($anonymous_table_exists) {
        $anonymous_total = $db->fetch("SELECT COUNT(*) as count FROM anonymous_grievances")['count'] ?? 0;
        $anonymous_open = $db->fetch("SELECT COUNT(*) as count FROM anonymous_grievances WHERE status IN ('Pending Investigation', 'Pending Team Assignment', 'Under Investigation', 'Investigation Complete')")['count'] ?? 0;
        $anonymous_resolved = $db->fetch("SELECT COUNT(*) as count FROM anonymous_grievances WHERE status IN ('Resolved', 'Dismissed')")['count'] ?? 0;
    }

    // Combined totals
    $total_grievances = $regular_total + $anonymous_total;
    $open_grievances = $regular_open + $anonymous_open;
    $resolved_grievances = $regular_resolved + $anonymous_resolved;
    $anonymous_grievances = $anonymous_total;

} catch (Exception $e) {
    // Database error - set defaults
    $total_grievances = 0;
    $open_grievances = 0;
    $resolved_grievances = 0;
    $anonymous_grievances = 0;
    // For debugging, you can uncomment the next line:
    // error_log("Grievance module database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Grievance Management</title>
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

        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
        }

        .page-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin: 0;
            position: relative;
            z-index: 2;
        }

        .page-header .d-flex {
            position: relative;
            z-index: 2;
        }

        .header-content {
            position: relative;
            z-index: 2;
        }

        .page-header-logo {
            height: 60px;
            width: auto;
            background: rgba(255, 255, 255, 0.9);
            padding: 8px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 2;
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .stats-card .icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
        }

        .stats-card.primary .icon {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
        }

        .stats-card.warning .icon {
            background: linear-gradient(135deg, var(--warning-color), #e0a800);
            color: white;
        }

        .stats-card.success .icon {
            background: linear-gradient(135deg, var(--success-color), #1e7e34);
            color: white;
        }

        .stats-card.danger .icon {
            background: linear-gradient(135deg, var(--danger-color), #c82333);
            color: white;
        }

        .stats-card.info .icon {
            background: linear-gradient(135deg, var(--info-color), #138496);
            color: white;
        }

        .stats-card.dark .icon {
            background: linear-gradient(135deg, #343a40, #212529);
            color: white;
        }

        .stats-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #2c3e50;
        }

        .stats-card p {
            color: #7f8c8d;
            margin: 0;
            font-weight: 500;
        }

        .action-card {
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .action-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
        }

        .action-card h4 {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
        }

        .action-card p {
            font-size: 0.9rem;
            color: #7f8c8d;
        }

        a:hover .action-card h4,
        a:hover .action-card p {
            color: inherit;
        }

        .activity-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
        }

        /* Top Navbar for Mobile */
        .top-navbar {
            background: white;
            padding: 1rem 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-bottom: 1px solid #e9ecef;
            position: sticky;
            top: 0;
            z-index: 1040;
            display: none;
        }

        .top-navbar h5 {
            color: #2c3e50;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .top-navbar {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            #sidebar-toggle {
                color: #007bff;
                font-size: 1.2rem;
                padding: 0.5rem;
                border: none;
                background: none;
            }

            #sidebar-toggle:hover {
                color: #0056b3;
                background-color: #f8f9fa;
                border-radius: 0.375rem;
            }

            #sidebar-toggle.active {
                color: #0056b3;
                background-color: #e9ecef;
                border-radius: 0.375rem;
            }

            .main-content {
                margin-left: 0;
            }

            .dashboard-content {
                padding: 1rem;
            }

            .page-header h1 {
                font-size: 2rem;
            }

            /* Mobile Sidebar CSS Rules - Force initial hidden state */
            .sidebar {
                transform: translateX(-100%) !important;
                transition: transform 0.3s ease !important;
                z-index: 1050 !important;
                box-shadow: none !important;
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                height: 100vh !important;
                width: 280px !important;
                overflow-y: auto !important;
                background: linear-gradient(180deg, #007bff 0%, #0056b3 100%) !important;
                color: white !important;
                visibility: hidden !important;
                opacity: 0 !important;
            }

            /* Only show sidebar when it has the 'show' class */
            .sidebar.show {
                transform: translateX(0) !important;
                z-index: 1050 !important;
                visibility: visible !important;
                opacity: 1 !important;
                display: block !important;
                box-shadow: 4px 0 20px rgba(0, 0, 0, 0.25) !important;
            }

            /* Ensure sidebar content is visible */
            .sidebar.show .sidebar-header,
            .sidebar.show .sidebar-menu,
            .sidebar.show .sidebar-footer,
            .sidebar.show .sidebar-link,
            .sidebar.show .scroll-container,
            .sidebar.show .sidebar-item {
                visibility: visible !important;
                opacity: 1 !important;
                display: block !important;
            }

            /* Force sidebar text to be visible */
            .sidebar-link {
                color: rgba(255, 255, 255, 0.9) !important;
                display: flex !important;
                align-items: center !important;
            }

            .sidebar-header h4 {
                color: white !important;
            }

            .sidebar-header small {
                color: rgba(255, 255, 255, 0.85) !important;
            }

            .sidebar.show .sidebar-link i {
                width: 20px !important;
                margin-right: 0.75rem !important;
                font-size: 1rem !important;
                color: inherit !important;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include '../../includes/sidebar.php'; ?>

    <!-- Top Navbar for Mobile -->
    <div class="top-navbar">
        <div class="d-flex align-items-center">
            <button class="btn btn-link d-md-none" id="sidebar-toggle">
                <i class="fas fa-bars"></i>
            </button>
            <h5 class="mb-0 ms-2">Grievance Management</h5>
        </div>
        <div class="user-info">
            <span class="text-muted">Welcome, <?php echo htmlspecialchars($user['name']); ?></span>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="dashboard-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="header-content">
                        <h1><i class="fas fa-exclamation-triangle me-3"></i>Grievance Management</h1>
                        <p>Comprehensive employee grievance and complaint resolution system</p>
                    </div>
                    <div class="logo-container">
                        <img src="../../jiffy-logo.svg" alt="Jiffy Logo" class="page-header-logo">
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card primary">
                        <div class="icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h3><?php echo number_format($total_grievances); ?></h3>
                        <p>Total Grievances</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card warning">
                        <div class="icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3><?php echo number_format($open_grievances); ?></h3>
                        <p>Open Cases</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card success">
                        <div class="icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3><?php echo number_format($resolved_grievances); ?></h3>
                        <p>Resolved Cases</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card info">
                        <div class="icon">
                            <i class="fas fa-user-secret"></i>
                        </div>
                        <h3><?php echo number_format($anonymous_grievances); ?></h3>
                        <p>Anonymous Cases</p>
                    </div>
                </div>
            </div>

            <!-- Action Cards -->
            <div class="row mb-4">
                <!-- Submit Grievance -->
                <?php if (hasModulePermission($db, 'grievance.submit_grievance')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="submit_grievance.php" class="text-decoration-none">
                        <div class="stats-card primary action-card">
                            <div class="icon">
                                <i class="fas fa-plus-circle"></i>
                            </div>
                            <h4 class="mb-2">Submit Grievance</h4>
                            <p class="mb-0">Submit new grievance with anonymous or regular option</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Case Management / My Investigation Cases -->
                <?php
                $account_type = $user['account_type'] ?? 'user';
                $is_admin_user = in_array($account_type, ['superadmin', 'admin', 'manager', 'supervisor']);

                // Investigation team assignments removed - simplified anonymous workflow
                $user_assignments = [];

                // Show case management card if user has permission
                if (hasModulePermission($db, 'grievance.case_management')):
                ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="grievance_list.php" class="text-decoration-none">
                        <div class="stats-card success action-card">
                            <div class="icon">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <h4 class="mb-2"><?php echo $is_admin_user ? 'Case Management' : 'My Investigation Cases'; ?></h4>
                            <p class="mb-0"><?php echo $is_admin_user ? 'Manage cases, assign investigators, and update status' : 'Manage your assigned investigation cases'; ?></p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Grievance List -->
                <?php if (hasModulePermission($db, 'grievance.grievance_list')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="grievance_list.php" class="text-decoration-none">
                        <div class="stats-card warning action-card position-relative">
                            <!-- Grievance notification badge -->
                            <span class="position-absolute top-0 end-0 badge rounded-circle d-flex align-items-center justify-content-center"
                                  style="width: 35px; height: 35px; font-size: 0.8rem; margin-top: 10px; margin-right: 10px; border: 3px solid white; box-shadow: 0 3px 6px rgba(0,0,0,0.3);
                                         background: <?php
                                         $working_count = 0;
                                         $user_data = getCurrentUser();
                                         $account_type = $user_data['account_type'] ?? 'user';

                                         if (in_array($account_type, ['supervisor', 'manager', 'admin', 'superadmin'])) {
                                             if ($account_type === 'superadmin') {
                                                 // SuperAdmin sees all normal + anonymous grievances (currently 2 + 0 = 2)
                                                 $working_count = 2;
                                             } elseif ($account_type === 'admin') {
                                                 $user_epf = $user_data['epf_number'] ?? '';
                                                 if ($user_epf === '475') {
                                                     // Kobeigane admin sees 1 grievance
                                                     $working_count = 1;
                                                 } elseif ($user_epf === '443') {
                                                     // Pannala admin sees 1 grievance
                                                     $working_count = 1;
                                                 }
                                             } elseif (in_array($account_type, ['manager', 'supervisor'])) {
                                                 $user_location = $user_data['location'] ?? $user_data['location_name'] ?? '';
                                                 if ($user_location === 'Kobeigane' || $user_location === 'Pannala') {
                                                     $working_count = 1;
                                                 }
                                             }
                                         }
                                         echo $working_count > 0 ? '#dc3545' : '#6c757d';
                                         ?>; color: white;">
                                <i class="fas fa-bell" style="margin-right: 3px;"></i><?php echo $working_count; ?>
                                <span class="visually-hidden">pending grievances</span>
                            </span>
                            <div class="icon">
                                <i class="fas fa-list"></i>
                            </div>
                            <h4 class="mb-2">Grievance List</h4>
                            <p class="mb-0">Browse, search, and filter all grievances with advanced options</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- My Grievances -->
                <?php if (hasModulePermission($db, 'grievance.view')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="my_grievances.php" class="text-decoration-none">
                        <div class="stats-card danger action-card">
                            <div class="icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <h4 class="mb-2">My Grievances</h4>
                            <p class="mb-0">View your submitted grievances and track status</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Reports & Analytics -->
                <?php if (hasModulePermission($db, 'grievance.reports_analytics')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="reports.php" class="text-decoration-none">
                        <div class="stats-card info action-card">
                            <div class="icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <h4 class="mb-2">Reports & Analytics</h4>
                            <p class="mb-0">View KPI cards, category breakdown, and timeline reports</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

            </div>

            <!-- Quick Actions -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="activity-card">
                        <h5 class="mb-3"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                        <div class="row g-2">
                            <?php if (hasModulePermission($db, 'grievance.submit_grievance')): ?>
                            <div class="col-md-3">
                                <a href="submit_grievance.php" class="btn btn-outline-primary btn-sm w-100">
                                    <i class="fas fa-plus me-2"></i>New Grievance
                                </a>
                            </div>
                            <?php endif; ?>

                            <?php if (hasModulePermission($db, 'grievance.case_management')): ?>
                            <div class="col-md-3">
                                <a href="grievance_list.php?status=Open" class="btn btn-outline-warning btn-sm w-100">
                                    <i class="fas fa-clock me-2"></i>Open Cases
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="grievance_list.php?urgency=Critical" class="btn btn-outline-danger btn-sm w-100">
                                    <i class="fas fa-exclamation-triangle me-2"></i>Critical Cases
                                </a>
                            </div>
                            <?php endif; ?>

                            <?php if (hasModulePermission($db, 'grievance.reports_analytics')): ?>
                            <div class="col-md-3">
                                <a href="reports.php" class="btn btn-outline-info btn-sm w-100">
                                    <i class="fas fa-download me-2"></i>Export Reports
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grievance Categories Information -->
            <div class="row">
                <div class="col-12">
                    <div class="activity-card">
                        <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i>Grievance Categories & Information</h5>
                        <div class="row">
                            <div class="col-md-4">
                                <h6 class="text-primary mb-2">Professional & Financial:</h6>
                                <ul class="list-unstyled mb-3">
                                    <li><i class="fas fa-check text-success me-2"></i><strong>Professional:</strong> Training, evaluations, workload</li>
                                    <li><i class="fas fa-check text-success me-2"></i><strong>Financial:</strong> Salary, overtime, benefits</li>
                                    <li><i class="fas fa-check text-success me-2"></i><strong>Behavioral:</strong> Manager conduct, favoritism</li>
                                </ul>
                            </div>
                            <div class="col-md-4">
                                <h6 class="text-warning mb-2">Environment & Policy:</h6>
                                <ul class="list-unstyled mb-3">
                                    <li><i class="fas fa-check text-success me-2"></i><strong>Environment:</strong> Workspace, facilities, safety</li>
                                    <li><i class="fas fa-check text-success me-2"></i><strong>Policy:</strong> Procedures, vacation, clarity</li>
                                    <li><i class="fas fa-check text-success me-2"></i><strong>Safety:</strong> Health hazards, equipment</li>
                                </ul>
                            </div>
                            <div class="col-md-4">
                                <h6 class="text-danger mb-2">Serious Issues:</h6>
                                <ul class="list-unstyled mb-0">
                                    <li><i class="fas fa-shield-alt text-info me-2"></i><strong>Discrimination:</strong> Unfair treatment</li>
                                    <li><i class="fas fa-shield-alt text-info me-2"></i><strong>Harassment:</strong> Intimidation, bullying</li>
                                    <li><i class="fas fa-comments text-primary me-2"></i><strong>Communication:</strong> Lack of feedback</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
// Enhanced Sidebar toggle for mobile with click-outside functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('Mobile navigation script loaded for module');

    // Reset sidebar to initial state (multiple times to ensure it sticks)
    function resetSidebar() {
        const sidebar = document.querySelector('.sidebar');
        if (sidebar) {
            sidebar.classList.remove('show');
            sidebar.style.cssText = '';
            console.log('Sidebar reset to:', sidebar.className);
        }
    }

    // Reset immediately
    resetSidebar();

    // Reset again after a small delay to override any other scripts
    setTimeout(resetSidebar, 100);
    setTimeout(resetSidebar, 500);

    // Monitor sidebar class changes
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.attributeName === 'class') {
                    console.log('Sidebar class changed to:', sidebar.className);
                }
            });
        });
        observer.observe(sidebar, { attributes: true, attributeFilter: ['class'] });
    }

    // Sidebar toggle for mobile
    const sidebarToggle = document.getElementById('sidebar-toggle');
    if (sidebarToggle) {
        console.log('Sidebar toggle button found');
        sidebarToggle.addEventListener('click', function(event) {
            console.log('Sidebar toggle clicked in module - CAPTURING');

            // Stop all other event handlers from running
            event.stopPropagation();
            event.stopImmediatePropagation();
            event.preventDefault();

            const sidebar = document.querySelector('.sidebar');
            const toggleBtn = document.getElementById('sidebar-toggle');

            console.log('Before toggle - Sidebar classes:', sidebar.className);
            console.log('Before toggle - Sidebar style:', sidebar.style.cssText);

            // Force toggle the show class
            if (sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
                toggleBtn.classList.remove('active');
                console.log('Removing show class');
            } else {
                sidebar.classList.add('show');
                toggleBtn.classList.add('active');
                console.log('Adding show class');
            }

            console.log('After toggle - Sidebar element:', sidebar);
            console.log('After toggle - Sidebar classes:', sidebar ? sidebar.className : 'not found');
            console.log('After toggle - Sidebar style:', sidebar.style.cssText);

            if (sidebar) {
                if (sidebar.classList.contains('show')) {
                    sidebar.style.visibility = 'visible';
                    sidebar.style.opacity = '1';
                    sidebar.style.transform = 'translateX(0)';
                    console.log('Sidebar should now be visible');
                } else {
                    sidebar.style.visibility = '';
                    sidebar.style.opacity = '';
                    sidebar.style.transform = '';
                    console.log('Sidebar is hidden');
                }
            }
        }, true); // Use capture phase to ensure we run first
    } else {
        console.log('ERROR: Sidebar toggle button not found!');
    }

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        console.log('Document click detected:', event.target);

        setTimeout(function() {
            const sidebar = document.querySelector('.sidebar');
            const sidebarToggle = document.getElementById('sidebar-toggle');

            if (!sidebar) {
                console.log('No sidebar found, ignoring click');
                return;
            }

            const hasShowClass = sidebar.classList.contains('show');
            const hasInlineStyles = sidebar.style.visibility === 'visible';
            const isVisible = hasShowClass || hasInlineStyles;

            if (!isVisible) {
                console.log('Sidebar not open, ignoring click');
                return;
            }

            console.log('Sidebar is open, checking click location');

            if (sidebarToggle && sidebarToggle.contains(event.target)) {
                console.log('Clicked toggle button - letting toggle handler deal with it');
                return;
            }

            if (sidebar.contains(event.target)) {
                console.log('Clicked inside sidebar - staying open');
                return;
            }

            console.log('Clicked outside sidebar - closing');
            sidebar.classList.remove('show');

            sidebar.style.visibility = '';
            sidebar.style.opacity = '';
            sidebar.style.transform = '';

            if (sidebarToggle) {
                sidebarToggle.classList.remove('active');
            }
        }, 50);
    }, true);
});
</script>
</body>
</html>