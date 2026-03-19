<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/location_access.php';

$user = getCurrentUser();
$db = new Database();

// Check if user has access to this page
if (!isSuperAdmin() && !hasModulePermission($db, 'meal.view')) {
    header('Location: ../../dashboard.php');
    exit;
}

// Get user role info for data filtering
$account_type = $user['account_type'] ?? 'user';
$user_location = $user['location'] ?? $user['location_name'] ?? null;
$user_epf = $user['epf_number'] ?? '';

// Build role-based filter for meal queries
$emp_filter = '';
$emp_params = [];
$visitor_filter = '';
$visitor_params = [];

if ($account_type === 'superadmin') {
    // No filter - sees all
} elseif ($account_type === 'admin') {
    $emp_filter = ' AND emp_location = ?';
    $emp_params = [$user_location];
    $visitor_filter = ' AND requesting_emp_location = ?';
    $visitor_params = [$user_location];
} elseif (in_array($account_type, ['manager', 'supervisor'])) {
    $accessible = getAccessibleEmployeesByLocation($db, $user);
    $accessible_epfs = array_column($accessible, 'epf_number');
    if (!empty($accessible_epfs)) {
        $placeholders = str_repeat('?,', count($accessible_epfs) - 1) . '?';
        $emp_filter = " AND emp_number IN ($placeholders)";
        $emp_params = $accessible_epfs;
        $visitor_filter = " AND requesting_emp_number IN ($placeholders)";
        $visitor_params = $accessible_epfs;
    } else {
        $emp_filter = ' AND 1=0';
        $visitor_filter = ' AND 1=0';
    }
} else {
    // Regular user - self only
    $emp_filter = ' AND emp_number = ?';
    $emp_params = [$user_epf];
    $visitor_filter = ' AND requesting_emp_number = ?';
    $visitor_params = [$user_epf];
}

// Create tables if they don't exist
try {
    // Create employee meal requests table
    $create_employee_table = "CREATE TABLE IF NOT EXISTS meal_requests_employee (
        id INT AUTO_INCREMENT PRIMARY KEY,
        emp_number VARCHAR(50) NOT NULL,
        emp_name VARCHAR(100) NOT NULL,
        emp_location VARCHAR(100) NOT NULL,
        meal_type VARCHAR(50) NOT NULL DEFAULT 'Employee Meal',
        request_date DATE NOT NULL,
        breakfast_enabled TINYINT DEFAULT 0,
        breakfast_count INT DEFAULT 0,
        breakfast_countx INT DEFAULT 0,
        snack1_countx INT DEFAULT 0,
        lunch_enabled TINYINT DEFAULT 0,
        lunch_count INT DEFAULT 0,
        lunch_countx INT DEFAULT 0,
        snack2_enabled TINYINT DEFAULT 0,
        snack2_count INT DEFAULT 0,
        snack2_countx INT DEFAULT 0,
        dinner_enabled TINYINT DEFAULT 0,
        dinner_count INT DEFAULT 0,
        dinner_countx INT DEFAULT 0,
        snack3_countx INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $db->query($create_employee_table);

    // Create visitor meal requests table
    $create_visitor_table = "CREATE TABLE IF NOT EXISTS meal_requests_visitor (
        id INT AUTO_INCREMENT PRIMARY KEY,
        requesting_emp_number VARCHAR(50) NOT NULL,
        requesting_emp_name VARCHAR(100) NOT NULL,
        requesting_emp_location VARCHAR(100) NOT NULL,
        visitor_names TEXT,
        visit_purpose VARCHAR(100),
        visitor_remarks TEXT,
        request_date DATE NOT NULL,
        breakfast_needed TINYINT DEFAULT 0,
        breakfast_menu VARCHAR(100),
        breakfast_count INT DEFAULT 0,
        breakfast_remarks TEXT,
        lunch_needed TINYINT DEFAULT 0,
        lunch_menu VARCHAR(100),
        lunch_count INT DEFAULT 0,
        lunch_remarks TEXT,
        dinner_needed TINYINT DEFAULT 0,
        dinner_menu VARCHAR(100),
        dinner_count INT DEFAULT 0,
        dinner_remarks TEXT,
        snack1_needed TINYINT DEFAULT 0,
        snack1_count INT DEFAULT 0,
        snack1_remarks TEXT,
        snack2_needed TINYINT DEFAULT 0,
        snack2_count INT DEFAULT 0,
        snack2_remarks TEXT,
        status VARCHAR(20) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $db->query($create_visitor_table);

    // Add new columns to existing visitor table if they don't exist
    $alter_columns = [
        ["visitor_names", "ALTER TABLE meal_requests_visitor ADD COLUMN visitor_names TEXT AFTER requesting_emp_location"],
        ["visit_purpose", "ALTER TABLE meal_requests_visitor ADD COLUMN visit_purpose VARCHAR(100) AFTER visitor_names"],
        ["visitor_remarks", "ALTER TABLE meal_requests_visitor ADD COLUMN visitor_remarks TEXT AFTER visit_purpose"]
    ];

    foreach ($alter_columns as [$column_name, $alter_sql]) {
        try {
            // Check if column exists
            $column_check = $db->query("SHOW COLUMNS FROM meal_requests_visitor LIKE '$column_name'");
            if (empty($column_check)) {
                $db->query($alter_sql);
            }
        } catch (Exception $alter_e) {
            // Column might already exist or other error, continue
        }
    }

} catch (Exception $e) {
    // Log error but continue with default values
    error_log("Table creation error: " . $e->getMessage());
}

// Get meal statistics (role-based filtered)
try {
    // Today's meal requests count
    $today_requests_query = "SELECT COUNT(*) as count FROM meal_requests_employee WHERE DATE(request_date) = CURDATE()" . $emp_filter;
    $today_requests_result = $db->fetch($today_requests_query, $emp_params);
    $today_requests = $today_requests_result ? $today_requests_result['count'] : 0;

    // This week's meal requests count
    $week_requests_query = "SELECT COUNT(*) as count FROM meal_requests_employee WHERE YEARWEEK(request_date) = YEARWEEK(NOW())" . $emp_filter;
    $week_requests_result = $db->fetch($week_requests_query, $emp_params);
    $week_requests = $week_requests_result ? $week_requests_result['count'] : 0;

    // Pending visitor meal requests
    $visitor_requests_query = "SELECT COUNT(*) as count FROM meal_requests_visitor WHERE status = 'pending'" . $visitor_filter;
    $visitor_requests_result = $db->fetch($visitor_requests_query, $visitor_params);
    $visitor_requests = $visitor_requests_result ? $visitor_requests_result['count'] : 0;

    // Total meal orders today
    $total_meals_query = "SELECT
        COALESCE(SUM(breakfast_count + breakfast_countx), 0) +
        COALESCE(SUM(lunch_count + lunch_countx), 0) +
        COALESCE(SUM(dinner_count + dinner_countx), 0) +
        COALESCE(SUM(snack1_countx), 0) +
        COALESCE(SUM(snack2_count + snack2_countx), 0) +
        COALESCE(SUM(snack3_countx), 0) as total
        FROM meal_requests_employee
        WHERE DATE(request_date) = CURDATE()" . $emp_filter;
    $total_meals_result = $db->fetch($total_meals_query, $emp_params);
    $total_meals = $total_meals_result ? $total_meals_result['total'] : 0;

} catch (Exception $e) {
    // If tables don't exist yet or other error, set defaults
    $today_requests = 0;
    $week_requests = 0;
    $visitor_requests = 0;
    $total_meals = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Meal Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/notifications.css" rel="stylesheet">
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

        .stats-card.purple .icon {
            background: linear-gradient(135deg, #6f42c1, #5a2d91);
            color: white;
        }

        .stats-card.orange .icon {
            background: linear-gradient(135deg, #fd7e14, #e55100);
            color: white;
        }

        .stats-card.teal .icon {
            background: linear-gradient(135deg, #20c997, #17a085);
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
            <h5 class="mb-0 ms-2">Meal Management</h5>
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
                        <h1><i class="fas fa-utensils me-3"></i>Meal Management</h1>
                        <p>Comprehensive employee and visitor meal request management and dining services</p>
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
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <h3><?php echo number_format($today_requests); ?></h3>
                        <p>Today's Requests</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card info">
                        <div class="icon">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                        <h3><?php echo number_format($week_requests); ?></h3>
                        <p>This Week's Requests</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card warning">
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3><?php echo number_format($visitor_requests); ?></h3>
                        <p>Pending Visitor Requests</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card success">
                        <div class="icon">
                            <i class="fas fa-bowl-food"></i>
                        </div>
                        <h3><?php echo number_format($total_meals); ?></h3>
                        <p>Total Meals Today</p>
                    </div>
                </div>
            </div>

            <!-- Action Cards -->
            <div class="row mb-4">
                <!-- Employee Meal Request -->
                <?php if (hasModulePermission($db, 'meal.employee_meal_request')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="employee_meal_request.php" class="text-decoration-none">
                        <div class="stats-card primary action-card">
                            <div class="notification-bell-module" data-submodule="employee_meal_request">
                                <i class="fas fa-bell"></i>
                                <span class="notification-count-module hidden" data-count="0">0</span>
                            </div>
                            <div class="icon">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <h4 class="mb-2">Employee Meal Request</h4>
                            <p class="mb-0">Submit daily meal requests for employees including breakfast, lunch, dinner and snacks</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- My Meal History -->
                <?php if (hasModulePermission($db, 'meal.my_meal_history')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="my_meal_history.php" class="text-decoration-none">
                        <div class="stats-card success action-card">
                            <div class="notification-bell-module" data-submodule="my_meal_history">
                                <i class="fas fa-bell"></i>
                                <span class="notification-count-module hidden" data-count="0">0</span>
                            </div>
                            <div class="icon">
                                <i class="fas fa-history"></i>
                            </div>
                            <h4 class="mb-2">My Meal History</h4>
                            <p class="mb-0">View your meal request history and track whether your orders are recorded properly</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Visitor Meal Request -->
                <?php if (hasModulePermission($db, 'meal.visitor_meal_request')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="visitor_meal_request.php" class="text-decoration-none">
                        <div class="stats-card info action-card">
                            <div class="notification-bell-module" data-submodule="visitor_meal_request">
                                <i class="fas fa-bell"></i>
                                <span class="notification-count-module hidden" data-count="0">0</span>
                            </div>
                            <div class="icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h4 class="mb-2">Visitor Meal Request</h4>
                            <p class="mb-0">Submit meal requests for visitors with special menu options and requirements</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Employee Meal Counter -->
                <?php if (hasModulePermission($db, 'meal.employee_meal_counter')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="employee_meal_counter.php" class="text-decoration-none">
                        <div class="stats-card warning action-card">
                            <div class="notification-bell-module" data-submodule="employee_meal_counter">
                                <i class="fas fa-bell"></i>
                                <span class="notification-count-module hidden" data-count="0">0</span>
                            </div>
                            <div class="icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <h4 class="mb-2">Employee Meal Counter</h4>
                            <p class="mb-0">View total counts and statistics for employee meal requests by location and meal type</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Visitor Meal Counter -->
                <?php if (hasModulePermission($db, 'meal.visitor_meal_counter')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="visitor_meal_counter.php" class="text-decoration-none">
                        <div class="stats-card danger action-card">
                            <div class="notification-bell-module" data-submodule="visitor_meal_counter">
                                <i class="fas fa-bell"></i>
                                <span class="notification-count-module hidden" data-count="0">0</span>
                            </div>
                            <div class="icon">
                                <i class="fas fa-chart-pie"></i>
                            </div>
                            <h4 class="mb-2">Visitor Meal Counter</h4>
                            <p class="mb-0">Track visitor meal requests and counts across all locations and meal types</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Visitor Request Management -->
                <?php if (hasModulePermission($db, 'meal.visitor_request_list')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="visitor_request_list.php" class="text-decoration-none">
                        <div class="stats-card primary action-card">
                            <div class="notification-bell-module" data-submodule="visitor_request_management">
                                <i class="fas fa-bell"></i>
                                <span class="notification-count-module hidden" data-count="0">0</span>
                            </div>
                            <div class="icon">
                                <i class="fas fa-list"></i>
                            </div>
                            <h4 class="mb-2">Visitor Request Management</h4>
                            <p class="mb-0">View, edit and delete visitor meal requests with complete details and status</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Meal Analytics -->
                <?php if (hasModulePermission($db, 'meal.meal_analytics')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="meal_analytics.php" class="text-decoration-none">
                        <div class="stats-card purple action-card">
                            <div class="notification-bell-module" data-submodule="meal_analytics">
                                <i class="fas fa-bell"></i>
                                <span class="notification-count-module hidden" data-count="0">0</span>
                            </div>
                            <div class="icon">
                                <i class="fas fa-chart-area"></i>
                            </div>
                            <h4 class="mb-2">Meal Analytics</h4>
                            <p class="mb-0">Graphical charts and analytics from employee and visitor meal data</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Meal Time Manager -->
                <?php if (hasModulePermission($db, 'meal.meal_time_manager')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="meal_time_manager.php" class="text-decoration-none">
                        <div class="stats-card danger action-card">
                            <div class="notification-bell-module" data-submodule="meal_time_manager">
                                <i class="fas fa-bell"></i>
                                <span class="notification-count-module hidden" data-count="0">0</span>
                            </div>
                            <div class="icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h4 class="mb-2">Meal Time Manager</h4>
                            <p class="mb-0">Set ordering deadlines and time limits for different meal types and forms</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Quick Reports -->
                <?php if (hasModulePermission($db, 'meal.meal_reports')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="meal_reports.php" class="text-decoration-none">
                        <div class="stats-card dark action-card">
                            <div class="notification-bell-module" data-submodule="quick_reports">
                                <i class="fas fa-bell"></i>
                                <span class="notification-count-module hidden" data-count="0">0</span>
                            </div>
                            <div class="icon">
                                <i class="fas fa-file-export"></i>
                            </div>
                            <h4 class="mb-2">Quick Reports</h4>
                            <p class="mb-0">Generate and export meal reports for daily operations and planning</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Today's Overview -->
                <?php if (hasModulePermission($db, 'meal.daily_overview')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="daily_overview.php" class="text-decoration-none">
                        <div class="stats-card teal action-card">
                            <div class="notification-bell-module" data-submodule="todays_overview">
                                <i class="fas fa-bell"></i>
                                <span class="notification-count-module hidden" data-count="0">0</span>
                            </div>
                            <div class="icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <h4 class="mb-2">Today's Overview</h4>
                            <p class="mb-0">Quick overview of today's meal orders, counts and status by location</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Detailed Request List -->
                <?php if (hasModulePermission($db, 'meal.meal_request_details')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="meal_request_details.php" class="text-decoration-none">
                        <div class="stats-card orange action-card">
                            <div class="notification-bell-module" data-submodule="detailed_request_list">
                                <i class="fas fa-bell"></i>
                                <span class="notification-count-module hidden" data-count="0">0</span>
                            </div>
                            <div class="icon">
                                <i class="fas fa-list-alt"></i>
                            </div>
                            <h4 class="mb-2">Detailed Request List</h4>
                            <p class="mb-0">View detailed list of who requested meals today by location with complete information</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="row">
                <div class="col-12">
                    <div class="activity-card">
                        <h5 class="mb-3"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                        <div class="row g-2">
                            <?php if (hasModulePermission($db, 'meal.employee_meal_request')): ?>
                            <div class="col-md-3">
                                <a href="employee_meal_request.php" class="btn btn-outline-primary btn-sm w-100">
                                    <i class="fas fa-plus me-2"></i>New Employee Request
                                </a>
                            </div>
                            <?php endif; ?>
                            <?php if (hasModulePermission($db, 'meal.visitor_meal_request')): ?>
                            <div class="col-md-3">
                                <a href="visitor_meal_request.php" class="btn btn-outline-info btn-sm w-100">
                                    <i class="fas fa-user-friends me-2"></i>New Visitor Request
                                </a>
                            </div>
                            <?php endif; ?>
                            <?php if (hasModulePermission($db, 'meal.daily_overview')): ?>
                            <div class="col-md-3">
                                <a href="daily_overview.php" class="btn btn-outline-success btn-sm w-100">
                                    <i class="fas fa-eye me-2"></i>Today's Overview
                                </a>
                            </div>
                            <?php endif; ?>
                            <?php if (hasModulePermission($db, 'meal.meal_reports')): ?>
                            <div class="col-md-3">
                                <a href="meal_reports.php" class="btn btn-outline-dark btn-sm w-100">
                                    <i class="fas fa-download me-2"></i>Generate Reports
                                </a>
                            </div>
                            <?php endif; ?>
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
    <script src="../../assets/js/notifications.js"></script>
</body>
</html>