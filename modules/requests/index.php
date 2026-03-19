<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/location_access.php';

$user = getCurrentUser();
$db = new Database();

// Get employee requests statistics with role-based filtering
try {
    // Check if tables exist
    $requests_table_exists = $db->fetch("SHOW TABLES LIKE 'employee_requests'");

    $total_requests = 0;
    $progress_requests = 0;
    $completed_requests = 0;
    $rejected_requests = 0;

    if ($requests_table_exists) {
        // Build role-based EPF filter
        $account_type = $user['account_type'] ?? 'user';
        $epf_filter = '';
        $epf_params = [];

        if ($account_type === 'superadmin') {
            // No filter - sees all
        } elseif ($account_type === 'admin') {
            $epf_filter = ' AND e.location_id = ?';
            $epf_params = [$user['location_id']];
        } elseif (in_array($account_type, ['manager', 'supervisor'])) {
            $accessible = getAccessibleEmployeesByLocation($db, $user);
            $accessible_epfs = array_column($accessible, 'epf_number');
            if (!empty($accessible_epfs)) {
                $placeholders = str_repeat('?,', count($accessible_epfs) - 1) . '?';
                $epf_filter = " AND er.epf_number IN ($placeholders)";
                $epf_params = $accessible_epfs;
            } else {
                $epf_filter = ' AND 1=0';
            }
        } else {
            // Regular user - self only
            $epf_filter = ' AND er.epf_number = ?';
            $epf_params = [$user['epf_number']];
        }

        // For superadmin, simple queries. For others, join with employees for filtering
        $needs_join = in_array($account_type, ['admin']);
        $base_from = $needs_join
            ? "FROM employee_requests er JOIN employees e ON er.epf_number = e.epf_number WHERE 1=1"
            : "FROM employee_requests er WHERE 1=1";

        // Total requests
        $total_requests = $db->fetch("SELECT COUNT(*) as count $base_from $epf_filter", $epf_params)['count'] ?? 0;

        // In-progress requests
        $progress_statuses = ['Request Submitted', 'Documents Pending', 'Processing'];
        $status_placeholders = str_repeat('?,', count($progress_statuses) - 1) . '?';
        $progress_params = array_merge($epf_params, $progress_statuses);
        $progress_requests = $db->fetch("SELECT COUNT(*) as count $base_from $epf_filter AND er.status IN ($status_placeholders)", $progress_params)['count'] ?? 0;

        // Completed requests
        $completed_params = array_merge($epf_params, ['Completed']);
        $completed_requests = $db->fetch("SELECT COUNT(*) as count $base_from $epf_filter AND er.status = ?", $completed_params)['count'] ?? 0;

        // Rejected requests
        $rejected_params = array_merge($epf_params, ['Rejected']);
        $rejected_requests = $db->fetch("SELECT COUNT(*) as count $base_from $epf_filter AND er.status = ?", $rejected_params)['count'] ?? 0;
    }

} catch (Exception $e) {
    // Database error - set defaults
    $total_requests = 0;
    $progress_requests = 0;
    $completed_requests = 0;
    $rejected_requests = 0;
    error_log("Employee Requests module database error: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Employee Requests</title>
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
            <h5 class="mb-0 ms-2">Employee Requests</h5>
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
                        <h1><i class="fas fa-file-alt me-3"></i>Employee Requests</h1>
                        <p>Comprehensive employee service request management and tracking system</p>
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
                        <h3><?php echo number_format($total_requests); ?></h3>
                        <p>Total Requests</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card warning">
                        <div class="icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3><?php echo number_format($progress_requests); ?></h3>
                        <p>Pending Requests</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card success">
                        <div class="icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3><?php echo number_format($completed_requests); ?></h3>
                        <p>Completed Requests</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card danger">
                        <div class="icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <h3><?php echo number_format($rejected_requests); ?></h3>
                        <p>Rejected Requests</p>
                    </div>
                </div>
            </div>

            <!-- Action Cards -->
            <div class="row mb-4">
                <!-- Submit Request -->
                <?php if (hasModulePermission($db, 'requests.submit_request')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="submit_request.php" class="text-decoration-none">
                        <div class="stats-card primary action-card">
                            <div class="icon">
                                <i class="fas fa-plus"></i>
                            </div>
                            <h4 class="mb-2">Submit Request</h4>
                            <p class="mb-0">Submit new employee service request with documentation</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- My Requests -->
                <?php if (hasModulePermission($db, 'requests.my_requests')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="my_requests.php" class="text-decoration-none">
                        <div class="stats-card info action-card">
                            <div class="icon">
                                <i class="fas fa-user-edit"></i>
                            </div>
                            <h4 class="mb-2">My Requests</h4>
                            <p class="mb-0">View, track, and manage your submitted requests</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- All Requests (Team/Management) -->
                <?php if (hasModulePermission($db, 'requests.all_requests') ||
                         hasModulePermission($db, 'requests.approve_admin') ||
                         hasModulePermission($db, 'requests.approve_manager') ||
                         hasModulePermission($db, 'requests.approve_supervisor') ||
                         hasModulePermission($db, 'requests.view_team_requests')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="all_requests.php" class="text-decoration-none">
                        <div class="stats-card warning action-card position-relative">
                            <div class="icon">
                                <i class="fas fa-users-cog"></i>
                            </div>
                            <h4 class="mb-2">All Requests</h4>
                            <p class="mb-0">View and manage employee requests workflow</p>

                            <!-- Notification Badge for SuperAdmin only -->
                            <span class="position-absolute top-0 end-0 badge rounded-circle d-flex align-items-center justify-content-center"
                                  style="width: 35px; height: 35px; font-size: 0.8rem; margin-top: 10px; margin-right: 10px; border: 3px solid white; box-shadow: 0 3px 6px rgba(0,0,0,0.3);
                                         background: <?php
                                         $request_count = 0;
                                         if (($user['account_type'] ?? '') === 'superadmin') {
                                             try {
                                                 $result = $db->fetch("
                                                     SELECT COUNT(*) as count
                                                     FROM employee_requests er
                                                     WHERE er.status IN ('Pending Supervisor', 'Processing', 'Documents Pending', 'Pending Manager')
                                                 ");
                                                 $request_count = $result ? (int)$result['count'] : 0;
                                             } catch (Exception $e) {
                                                 $request_count = 0;
                                             }
                                         }
                                         echo $request_count > 0 ? '#dc3545' : '#6c757d';
                                         ?>; color: white;">
                                <i class="fas fa-bell" style="margin-right: 3px;"></i><?php echo $request_count; ?>
                            </span>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Reports & Analytics -->
                <?php if (hasModulePermission($db, 'requests.reports')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="reports.php" class="text-decoration-none">
                        <div class="stats-card success action-card">
                            <div class="icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <h4 class="mb-2">Reports & Analytics</h4>
                            <p class="mb-0">Generate reports and analyze request trends</p>
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
                            <?php if (hasModulePermission($db, 'requests.submit_request')): ?>
                            <div class="col-md-3">
                                <a href="submit_request.php?type=Salary Slip Originals" class="btn btn-outline-primary btn-sm w-100">
                                    <i class="fas fa-file-invoice me-2"></i>Salary Slip
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="submit_request.php?type=Service Letter" class="btn btn-outline-success btn-sm w-100">
                                    <i class="fas fa-certificate me-2"></i>Service Letter
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="submit_request.php?type=Bank Documents Fillup" class="btn btn-outline-warning btn-sm w-100">
                                    <i class="fas fa-university me-2"></i>Bank Documents
                                </a>
                            </div>
                            <?php endif; ?>

                            <?php if (hasModulePermission($db, 'requests.reports')): ?>
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

            <!-- Request Information -->
            <div class="row">
                <div class="col-12">
                    <div class="activity-card">
                        <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i>Request Types & Information</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-primary mb-2">Available Request Types:</h6>
                                <ul class="list-unstyled mb-3">
                                    <li><i class="fas fa-check text-success me-2"></i><strong>Salary Slip Originals:</strong> Request original salary documents</li>
                                    <li><i class="fas fa-check text-success me-2"></i><strong>Bank Documents:</strong> Banking-related document completion</li>
                                    <li><i class="fas fa-check text-success me-2"></i><strong>Service Letter:</strong> Employment verification letter</li>
                                    <li><i class="fas fa-check text-success me-2"></i><strong>Other:</strong> Miscellaneous employee service requests</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-success mb-2">Processing Information:</h6>
                                <ul class="list-unstyled mb-0">
                                    <li><i class="fas fa-clock text-warning me-2"></i><strong>Normal Processing:</strong> 3-7 business days</li>
                                    <li><i class="fas fa-bolt text-danger me-2"></i><strong>Urgent Requests:</strong> Priority handling</li>
                                    <li><i class="fas fa-envelope text-info me-2"></i><strong>Status Updates:</strong> Email notifications sent</li>
                                    <li><i class="fas fa-download text-primary me-2"></i><strong>Document Delivery:</strong> Available for pickup/email</li>
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