<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';
require_once '../../includes/location_access.php';

// Check if user has onboarding access
$user = getCurrentUser();
$db = new Database();
if (!isSuperAdmin() && !hasModulePermission($db, 'onboarding.view')) {
    header('Location: ../../dashboard.php');
    exit();
}

$database = new Database();

// Build role-based filter
$account_type = $user['account_type'] ?? 'user';
$epf_filter = '';
$epf_params = [];

if ($account_type === 'superadmin') {
    // No filter
} elseif ($account_type === 'admin') {
    $epf_filter = ' AND e.location_id = ?';
    $epf_params = [$user['location_id']];
} elseif (in_array($account_type, ['manager', 'supervisor'])) {
    $accessible = getAccessibleEmployeesByLocation($db, $user);
    $accessible_epfs = array_column($accessible, 'epf_number');
    if (!empty($accessible_epfs)) {
        $placeholders = str_repeat('?,', count($accessible_epfs) - 1) . '?';
        $epf_filter = " AND e.epf_number IN ($placeholders)";
        $epf_params = $accessible_epfs;
    } else {
        $epf_filter = ' AND 1=0';
    }
} else {
    $epf_filter = ' AND e.epf_number = ?';
    $epf_params = [$user['epf_number']];
}

// Get onboarding statistics
$total_employees_query = "SELECT COUNT(DISTINCT e.epf_number) as count
                         FROM employees e
                         WHERE e.is_active = 1" . $epf_filter;
$total_employees = $database->fetchAll($total_employees_query, $epf_params)[0]['count'];

// For onboarding tracker stats, filter by employees accessible to the user
$onboard_filter = '';
$onboard_params = [];
if ($account_type === 'superadmin') {
    // No filter
} elseif ($account_type === 'admin') {
    $onboard_filter = ' AND ot.epf_number IN (SELECT epf_number FROM employees WHERE location_id = ?)';
    $onboard_params = [$user['location_id']];
} elseif (in_array($account_type, ['manager', 'supervisor'])) {
    if (!empty($accessible_epfs)) {
        $placeholders = str_repeat('?,', count($accessible_epfs) - 1) . '?';
        $onboard_filter = " AND ot.epf_number IN ($placeholders)";
        $onboard_params = $accessible_epfs;
    } else {
        $onboard_filter = ' AND 1=0';
    }
} else {
    $onboard_filter = ' AND ot.epf_number = ?';
    $onboard_params = [$user['epf_number']];
}

$onboarded_employees_query = "SELECT COUNT(*) as count
                             FROM onboarding_tracker ot
                             WHERE ot.is_completed = 1" . $onboard_filter;
$onboarded_employees = $database->fetchAll($onboarded_employees_query, $onboard_params)[0]['count'] ?? 0;

$pending_onboarding_query = "SELECT COUNT(*) as count
                            FROM onboarding_tracker ot
                            WHERE ot.is_completed = 0" . $onboard_filter;
$pending_onboarding = $database->fetchAll($pending_onboarding_query, $onboard_params)[0]['count'] ?? 0;

// Recalculate completion percentages properly for all records first
$update_all_completion = "
UPDATE onboarding_tracker SET
completion_percentage = (
    (CASE WHEN safety_induction = 1 THEN 1 ELSE 0 END) +
    (CASE WHEN code_of_conduct = 1 THEN 1 ELSE 0 END) +
    (CASE WHEN training_evaluation = 1 OR training_evaluation_na = 1 THEN 1 ELSE 0 END) +
    (CASE WHEN performance_evaluation = 1 THEN 1 ELSE 0 END) +
    (CASE WHEN agreement = 1 THEN 1 ELSE 0 END) +
    (CASE WHEN non_compete_agreement = 1 THEN 1 ELSE 0 END) +
    (CASE WHEN medical_insurance_letter = 1 THEN 1 ELSE 0 END) +
    (CASE WHEN confirmation_letter = 1 THEN 1 ELSE 0 END)
) * 100 / 8,
is_completed = CASE WHEN (
    (CASE WHEN safety_induction = 1 THEN 1 ELSE 0 END) +
    (CASE WHEN code_of_conduct = 1 THEN 1 ELSE 0 END) +
    (CASE WHEN training_evaluation = 1 OR training_evaluation_na = 1 THEN 1 ELSE 0 END) +
    (CASE WHEN performance_evaluation = 1 THEN 1 ELSE 0 END) +
    (CASE WHEN agreement = 1 THEN 1 ELSE 0 END) +
    (CASE WHEN non_compete_agreement = 1 THEN 1 ELSE 0 END) +
    (CASE WHEN medical_insurance_letter = 1 THEN 1 ELSE 0 END) +
    (CASE WHEN confirmation_letter = 1 THEN 1 ELSE 0 END)
) = 8 THEN 1 ELSE 0 END";

try {
    $database->execute($update_all_completion);
} catch (Exception $e) {
    // Ignore errors if training_evaluation_na column doesn't exist yet
}

$avg_completion_query = "SELECT AVG(completion_percentage) as avg_completion
                        FROM onboarding_tracker
                        WHERE is_completed = 0";
$avg_completion = round($database->fetchAll($avg_completion_query)[0]['avg_completion'] ?? 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Onboarding Tracker</title>
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

        .content-wrapper {
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
            z-index: 1;
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

        .stats-card.success .icon {
            background: linear-gradient(135deg, var(--success-color), #1e7e34);
            color: white;
        }

        .stats-card.warning .icon {
            background: linear-gradient(135deg, var(--warning-color), #e0a800);
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

        a:hover .action-card h4 {
            color: #2c3e50;
        }

        a:hover .action-card p {
            color: #7f8c8d;
        }

        /* Module Notification Bell Styles */
        .notification-bell-module {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 10;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .notification-bell-module i {
            color: #FFA500;
            filter: drop-shadow(0 0 3px rgba(255, 165, 0, 0.6));
        }

        .notification-bell-module:hover i {
            color: #FFD700;
            transform: scale(1.2);
            filter: drop-shadow(0 0 6px rgba(255, 165, 0, 0.9));
        }

        .notification-count-module {
            position: absolute;
            top: -10px;
            right: -10px;
            background: #FF4444;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
            animation: pulse-notification 2s infinite;
        }

        .notification-count-module.hidden {
            display: none;
        }

        .action-card {
            position: relative;
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

            .content-wrapper {
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
            <h5 class="mb-0 ms-2">Onboarding Tracker</h5>
        </div>
        <div class="user-info">
            <span class="text-muted">Welcome, <?php echo htmlspecialchars($user['name']); ?></span>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-wrapper">
            <!-- Page Header -->
            <div class="page-header">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="header-content">
                        <h1><i class="fas fa-user-plus me-3"></i>Onboarding Tracker</h1>
                        <p>Comprehensive employee onboarding management system</p>
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
                            <i class="fas fa-users"></i>
                        </div>
                        <h3><?php echo number_format($total_employees); ?></h3>
                        <p>Total Employees</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card success">
                        <div class="icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3><?php echo number_format($onboarded_employees); ?></h3>
                        <p>Completed Onboarding</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card warning">
                        <div class="icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3><?php echo number_format($pending_onboarding); ?></h3>
                        <p>Pending Onboarding</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card info">
                        <div class="icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3><?php echo $avg_completion; ?>%</h3>
                        <p>Average Progress</p>
                    </div>
                </div>
            </div>

            <!-- Action Cards -->
            <div class="row mb-4">
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="new_onboarding.php" class="text-decoration-none">
                        <div class="stats-card primary action-card">
                            <div class="notification-bell-module" data-submodule="start_new">
                                <i class="fas fa-bell"></i>
                                <span class="notification-count-module">0</span>
                            </div>
                            <div class="icon">
                                <i class="fas fa-plus-circle"></i>
                            </div>
                            <h4 class="mb-2">Start New</h4>
                            <p class="mb-0">Begin onboarding process</p>
                        </div>
                    </a>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="onboarding_list.php" class="text-decoration-none">
                        <div class="stats-card success action-card">
                            <div class="notification-bell-module" data-submodule="track_progress">
                                <i class="fas fa-bell"></i>
                                <span class="notification-count-module">0</span>
                            </div>
                            <div class="icon">
                                <i class="fas fa-list-check"></i>
                            </div>
                            <h4 class="mb-2">Track Progress</h4>
                            <p class="mb-0">Manage ongoing processes</p>
                        </div>
                    </a>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="onboarding_reports.php" class="text-decoration-none">
                        <div class="stats-card info action-card">
                            <div class="notification-bell-module" data-submodule="analytics">
                                <i class="fas fa-bell"></i>
                                <span class="notification-count-module">0</span>
                            </div>
                            <div class="icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <h4 class="mb-2">Analytics</h4>
                            <p class="mb-0">View reports & metrics</p>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Quick Access Section -->
            <div class="row mt-5">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light border-0">
                            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Onboarding Checklist Items</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <ul class="list-unstyled">
                                        <li class="mb-2"><i class="fas fa-shield-alt text-primary me-2"></i>Safety Induction</li>
                                        <li class="mb-2"><i class="fas fa-book text-info me-2"></i>Code of Conduct</li>
                                        <li class="mb-2"><i class="fas fa-graduation-cap text-success me-2"></i>Training Evaluation <small class="text-muted">(Flexible: N/A or Track)</small></li>
                                        <li class="mb-2"><i class="fas fa-star text-warning me-2"></i>Probation Evaluation</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <ul class="list-unstyled">
                                        <li class="mb-2"><i class="fas fa-handshake text-primary me-2"></i>Agreement</li>
                                        <li class="mb-2"><i class="fas fa-ban text-danger me-2"></i>Non-Compete Agreement</li>
                                        <li class="mb-2"><i class="fas fa-heartbeat text-success me-2"></i>Medical Insurance Letter</li>
                                        <li class="mb-2"><i class="fas fa-certificate text-info me-2"></i>Confirmation Letter</li>
                                    </ul>
                                </div>
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