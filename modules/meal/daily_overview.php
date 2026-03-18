<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

// Check if user has permission to access daily overview
$db = new Database();
if (!hasModulePermission($db, 'meal.daily_overview') && !hasAdminAccess()) {
    header('Location: ../../dashboard.php');
    exit();
}

$user = getCurrentUser();

// Get selected date (default to today)
$selected_date = $_GET['date'] ?? date('Y-m-d');

// Get user location and account type for access control
$account_type = $user['account_type'] ?? 'user';
$user_location = $user['location'] ?? $user['location_name'] ?? null;

// Available locations
$locations = ['7C', 'Pannala', 'Kobeigane', 'JECOE', 'Head Office'];

// Apply hierarchical location filtering
if ($account_type === 'admin' && $user_location) {
    // Admins can only see their own location data
    $locations = [$user_location]; // Only show admin's location
} elseif ($account_type === 'superadmin') {
    // SuperAdmins can see all locations (keep all locations)
    // No filtering needed
} else {
    // Other users shouldn't have access, but if they do, restrict to their location
    if ($user_location) {
        $locations = [$user_location];
    }
}

// Get today's meal data
try {
    // Build location condition for queries
    $location_condition = '';
    $query_params = [$selected_date];

    if ($account_type === 'admin' && $user_location) {
        $location_condition = ' AND emp_location = ?';
        $query_params[] = $user_location;
    }

    // Employee meal summary by location
    $employee_summary_query = "SELECT
        emp_location,
        COUNT(*) as total_requests,
        SUM(breakfast_count + breakfast_countx) as breakfast_total,
        SUM(lunch_count + lunch_countx) as lunch_total,
        SUM(dinner_count + dinner_countx) as dinner_total,
        SUM(snack1_countx) as snack1_total,
        SUM(snack2_count + snack2_countx) as snack2_total,
        SUM(snack3_countx) as snack3_total
        FROM meal_requests_employee
        WHERE DATE(request_date) = ? $location_condition
        GROUP BY emp_location
        ORDER BY emp_location";

    $employee_data = $db->fetchAll($employee_summary_query, $query_params);

    // Visitor meal summary by location (use same location condition)
    $visitor_location_condition = '';
    if ($account_type === 'admin' && $user_location) {
        $visitor_location_condition = ' AND requesting_emp_location = ?';
    }

    $visitor_summary_query = "SELECT
        requesting_emp_location as emp_location,
        COUNT(*) as total_requests,
        SUM(breakfast_count) as breakfast_total,
        SUM(lunch_count) as lunch_total,
        SUM(dinner_count) as dinner_total,
        SUM(snack1_count) as snack1_total,
        SUM(snack2_count) as snack2_total
        FROM meal_requests_visitor
        WHERE DATE(request_date) = ? $visitor_location_condition
        GROUP BY requesting_emp_location
        ORDER BY requesting_emp_location";

    $visitor_data = $db->fetchAll($visitor_summary_query, $query_params);

    // Recent meal requests (last 10) - filter by location for admins
    $recent_requests_query = "SELECT
        emp_number,
        emp_name,
        emp_location,
        (breakfast_count + breakfast_countx + lunch_count + lunch_countx +
         dinner_count + dinner_countx + snack1_countx + snack2_count +
         snack2_countx + snack3_countx) as total_meals,
        created_at
        FROM meal_requests_employee
        WHERE DATE(request_date) = ? $location_condition
        ORDER BY created_at DESC
        LIMIT 10";

    $recent_requests = $db->fetchAll($recent_requests_query, $query_params);

    // Meal time status (checking against current time)
    $time_settings_query = "SELECT * FROM meal_time_settings WHERE setting_type = 'employee' AND is_active = 1 ORDER BY meal_name";
    $time_settings = $db->fetchAll($time_settings_query);

    // Overall totals
    $totals_query = "SELECT
        COUNT(*) as total_employee_requests,
        SUM(breakfast_count + breakfast_countx + lunch_count + lunch_countx +
            dinner_count + dinner_countx + snack1_countx + snack2_count +
            snack2_countx + snack3_countx) as total_meals
        FROM meal_requests_employee
        WHERE DATE(request_date) = ?";

    $totals_result = $db->fetch($totals_query, [$selected_date]);
    $total_employee_requests = $totals_result ? $totals_result['total_employee_requests'] : 0;
    $total_meals = $totals_result ? ($totals_result['total_meals'] ?? 0) : 0;

    $visitor_totals_query = "SELECT COUNT(*) as total_visitor_requests FROM meal_requests_visitor WHERE DATE(request_date) = ?";
    $visitor_totals_result = $db->fetch($visitor_totals_query, [$selected_date]);
    $total_visitor_requests = $visitor_totals_result ? $visitor_totals_result['total_visitor_requests'] : 0;

} catch (Exception $e) {
    $employee_data = [];
    $visitor_data = [];
    $recent_requests = [];
    $time_settings = [];
    $total_employee_requests = 0;
    $total_visitor_requests = 0;
    $total_meals = 0;
}

// Helper function to get data for location
function getLocationData($data, $location, $field) {
    foreach ($data as $row) {
        if ($row['emp_location'] === $location) {
            return $row[$field] ?? 0;
        }
    }
    return 0;
}

// Current time for deadline checking
$current_time = date('H:i:s');
$current_date = date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Today's Overview</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        /* Real-Time Dashboard Styles */
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --accent-pink: #e91e63;
            --accent-orange: #ff6b35;
            --success-color: #28a745;
            --info-color: #17a2b8;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa, #c3cfe2);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }

        .main-content {
            margin-left: 280px !important;
            min-height: 100vh;
            padding: 0 !important;
            width: calc(100% - 280px) !important;
            position: relative;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
            padding: 2.5rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
        }

        .page-header::after {
            content: '';
            position: absolute;
            bottom: -30px;
            left: -30px;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }

        .page-header h1 {
            font-size: 2.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 3;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .page-header p {
            font-size: 1.2rem;
            opacity: 0.9;
            margin: 0;
            position: relative;
            z-index: 3;
            font-weight: 400;
        }

        .page-header .d-flex {
            position: relative;
            z-index: 3;
        }

        .header-content {
            position: relative;
            z-index: 3;
        }

        .page-header-logo {
            height: 60px;
            width: auto;
            background: rgba(255, 255, 255, 0.9);
            padding: 8px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 3;
        }

        .breadcrumb {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
        }

        .dashboard-content {
            padding: 2rem;
            background: transparent;
            min-height: calc(100vh - 40px);
        }

        .content-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .content-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
        }

        .content-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-pink);
        }

        .card-header-modern {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-bottom: 2px solid #e9ecef;
            border-radius: 20px 20px 0 0 !important;
            padding: 1.5rem 2rem;
            margin: -2rem -2rem 2rem -2rem;
            position: relative;
        }

        .card-header-modern::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-pink);
            border-radius: 20px 20px 0 0;
        }

        .card-header-modern h5 {
            color: #2c3e50;
            font-weight: 600;
            margin: 0;
            font-size: 1.25rem;
        }

        .form-control {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--accent-pink);
            box-shadow: 0 0 0 0.2rem rgba(233, 30, 99, 0.25);
        }

        .btn {
            border-radius: 12px;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--gradient-pink);
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(233, 30, 99, 0.3);
        }

        .btn-outline-secondary {
            border: 2px solid #6c757d;
        }

        .btn-outline-primary {
            border: 2px solid var(--primary-color);
        }

        .btn-outline-secondary:hover, .btn-outline-primary:hover {
            transform: translateY(-2px);
        }

        @media print {
            .no-print {
                display: none !important;
            }
        }

        /* Live Statistics Cards */
        .live-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .live-stat-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: none;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .live-stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .live-stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--accent-color);
        }

        .stat-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            position: relative;
        }

        .stat-icon.time {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }

        .stat-icon.employees {
            background: linear-gradient(135deg, #007bff, #0056b3);
        }

        .stat-icon.visitors {
            background: linear-gradient(135deg, var(--accent-pink), #c2185b);
        }

        .stat-icon.meals {
            background: linear-gradient(135deg, var(--accent-orange), #e55a2b);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2c3e50;
            line-height: 1;
            margin: 0.5rem 0;
        }

        .stat-label {
            color: #6c757d;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
        }

        .live-indicator {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 10px;
            height: 10px;
            background: #28a745;
            border-radius: 50%;
            animation: pulse-live 2s infinite;
        }

        @keyframes pulse-live {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.3); opacity: 0.7; }
            100% { transform: scale(1); opacity: 1; }
        }

        /* Current Time Display */
        .time-display {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .time-display::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-pink));
        }

        .current-time {
            font-size: 3rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            font-family: 'Courier New', monospace;
        }

        .current-date {
            font-size: 1.2rem;
            color: #6c757d;
            font-weight: 500;
        }

        /* Meal Status Dashboard */
        .meal-status-dashboard {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .status-header {
            background: linear-gradient(135deg, #ffc107, #fd7e14);
            color: white;
            padding: 1.5rem 2rem;
        }

        .status-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            padding: 2rem;
        }

        .meal-status-items {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .meal-status-item {
            text-align: center;
            padding: 1.5rem;
            border-radius: 15px;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }

        .meal-status-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .status-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
            transition: all 0.3s ease;
        }

        .status-open {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            box-shadow: 0 0 20px rgba(40, 167, 69, 0.3);
        }

        .status-closed {
            background: linear-gradient(135deg, #dc3545, #fd7e14);
            color: white;
            box-shadow: 0 0 20px rgba(220, 53, 69, 0.3);
        }

        .meal-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .meal-time {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Enhanced Location Cards */
        .location-dashboard {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .location-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 1.5rem 2rem;
        }

        .location-grid {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 2rem;
            padding: 2rem;
        }

        .location-chart-container {
            position: relative;
            height: 400px;
        }

        .location-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .enhanced-location-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .enhanced-location-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .enhanced-location-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--location-color);
        }

        /* Activity Feed */
        .activity-feed {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .activity-header {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
            padding: 1.5rem 2rem;
        }

        .activity-item {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #f0f2f5;
            transition: all 0.3s ease;
            position: relative;
        }

        .activity-item:hover {
            background: #f8f9fa;
            transform: translateX(5px);
        }

        .activity-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--accent-pink);
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }

        .activity-item:hover::before {
            transform: scaleY(1);
        }

        /* Quick Actions */
        .quick-actions {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .actions-header {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            padding: 1.5rem 2rem;
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            padding: 2rem;
        }

        .action-btn {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 1.5rem;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }

        .action-btn:hover {
            border-color: var(--primary-color);
            background: var(--primary-color);
            color: white;
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .action-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        @media (max-width: 992px) {
            .status-grid,
            .location-grid {
                grid-template-columns: 1fr;
            }

            .meal-status-items {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }

            .dashboard-content {
                padding: 1rem;
            }

            .live-stats-grid {
                grid-template-columns: 1fr;
            }

            .meal-status-items {
                grid-template-columns: 1fr;
            }

            .location-cards {
                grid-template-columns: 1fr;
            }

            .action-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .current-time {
                font-size: 2rem;
            }
        }

        @media (max-width: 576px) {
            .action-grid {
                grid-template-columns: 1fr;
            }

            .header-controls {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="dashboard-content">
            <!-- Page Header -->
            <div class="page-header no-print">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="header-content">
                        <h1><i class="fas fa-tachometer-alt me-3"></i>Daily Meal Overview</h1>
                        <p>Real-time monitoring and live dashboard for meal requests</p>
                    </div>
                    <div class="logo-container">
                        <img src="../../jiffy-logo.svg" alt="Jiffy Logo" class="page-header-logo">
                    </div>
                </div>
            </div>

            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="no-print">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Meal Management</a></li>
                    <li class="breadcrumb-item active">Daily Overview</li>
                </ol>
            </nav>

            <!-- Control Panel -->
            <div class="content-card no-print">
                <div class="card-header-modern">
                    <h5><i class="fas fa-cogs me-2"></i>Dashboard Controls</h5>
                </div>
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold"><i class="fas fa-calendar-day me-1"></i>Select Date</label>
                        <input type="date" class="form-control" id="dateSelector" value="<?php echo $selected_date; ?>" onchange="changeDate()">
                    </div>
                    <div class="col-md-9">
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary" onclick="refreshData()" id="refreshBtn">
                                <i class="fas fa-sync-alt me-2"></i>Live Refresh
                            </button>
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Menu
                            </a>
                            <div class="dropdown">
                                <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-user-circle me-2"></i><?php echo htmlspecialchars($user['name']); ?>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="../../logout.php">
                                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                                    </a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Current Time Display -->
            <div class="time-display">
                <div class="live-indicator"></div>
                <div class="current-time" id="realTimeDisplay"><?php echo date('g:i:s A'); ?></div>
                <div class="current-date"><?php echo date('l, F j, Y', strtotime($selected_date)); ?></div>
                <p class="mt-3 mb-0"><i class="fas fa-info-circle me-2"></i>Real-time monitoring active</p>
            </div>

            <!-- Live Statistics Grid -->
            <div class="live-stats-grid">
                <div class="live-stat-card">
                    <div class="live-indicator"></div>
                    <div class="stat-header">
                        <div class="stat-icon time">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-value" id="liveTime"><?php echo date('g:i A'); ?></div>
                    <div class="stat-label">Current Time</div>
                    <small class="text-muted">Last updated: <span id="lastUpdate">just now</span></small>
                </div>

                <div class="live-stat-card" style="--accent-color: #007bff;">
                    <div class="live-indicator"></div>
                    <div class="stat-header">
                        <div class="stat-icon employees">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_employee_requests); ?></div>
                    <div class="stat-label">Employee Requests</div>
                    <small class="text-success"><i class="fas fa-arrow-up me-1"></i>Active today</small>
                </div>

                <div class="live-stat-card" style="--accent-color: var(--accent-pink);">
                    <div class="live-indicator"></div>
                    <div class="stat-header">
                        <div class="stat-icon visitors">
                            <i class="fas fa-user-friends"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_visitor_requests); ?></div>
                    <div class="stat-label">Visitor Requests</div>
                    <small class="text-info"><i class="fas fa-clock me-1"></i>Monitored live</small>
                </div>

                <div class="live-stat-card" style="--accent-color: var(--accent-orange);">
                    <div class="live-indicator"></div>
                    <div class="stat-header">
                        <div class="stat-icon meals">
                            <i class="fas fa-utensils"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_meals); ?></div>
                    <div class="stat-label">Total Meals</div>
                    <small class="text-warning"><i class="fas fa-chart-line me-1"></i>Live count</small>
                </div>
            </div>

            <!-- Meal Status Dashboard -->
            <div class="meal-status-dashboard">
                <div class="status-header">
                    <h5 class="mb-0"><i class="fas fa-clock me-3"></i>Real-Time Meal Order Status</h5>
                    <p class="mb-0 mt-2">Live monitoring of meal ordering deadlines</p>
                </div>
                <div class="status-grid">
                    <div class="meal-status-items">
                        <?php foreach ($time_settings as $setting):
                            $is_today = $selected_date === $current_date;
                            $deadline_passed = $is_today && $current_time > $setting['order_deadline_time'];
                            $status_class = $deadline_passed ? 'status-closed' : 'status-open';
                            $status_text = $deadline_passed ? 'Closed' : 'Open';
                            $icon = $deadline_passed ? 'times-circle' : 'check-circle';
                            $badge_class = $deadline_passed ? 'bg-danger' : 'bg-success';
                        ?>
                        <div class="meal-status-item">
                            <div class="status-circle <?php echo $status_class; ?>">
                                <i class="fas fa-<?php echo $icon; ?>"></i>
                            </div>
                            <div class="meal-name"><?php echo ucfirst($setting['meal_name']); ?></div>
                            <div class="meal-time"><?php echo date('g:i A', strtotime($setting['order_deadline_time'])); ?></div>
                            <span class="status-badge <?php echo $badge_class; ?>"><?php echo $status_text; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="meal-chart-container">
                        <h6 class="text-center mb-3">Status Overview</h6>
                        <div style="position: relative; height: 250px;">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Location Dashboard -->
            <div class="location-dashboard">
                <div class="location-header">
                    <h5 class="mb-0"><i class="fas fa-map-marker-alt me-3"></i>Location-wise Meal Distribution</h5>
                    <p class="mb-0 mt-2">Real-time breakdown by office locations</p>
                </div>
                <div class="location-grid">
                    <div class="location-cards">
                        <?php
                        $location_colors = ['#e91e63', '#28A745', '#007BFF', '#6F42C1', '#FFC107'];
                        foreach ($locations as $index => $location):
                            $emp_requests = getLocationData($employee_data, $location, 'total_requests');
                            $vis_requests = getLocationData($visitor_data, $location, 'total_requests');
                            $breakfast = getLocationData($employee_data, $location, 'breakfast_total') + getLocationData($visitor_data, $location, 'breakfast_total');
                            $lunch = getLocationData($employee_data, $location, 'lunch_total') + getLocationData($visitor_data, $location, 'lunch_total');
                            $dinner = getLocationData($employee_data, $location, 'dinner_total') + getLocationData($visitor_data, $location, 'dinner_total');
                            $snacks = getLocationData($employee_data, $location, 'snack1_total') +
                                     getLocationData($employee_data, $location, 'snack2_total') +
                                     getLocationData($employee_data, $location, 'snack3_total') +
                                     getLocationData($visitor_data, $location, 'snack1_total') +
                                     getLocationData($visitor_data, $location, 'snack2_total');
                            $total_meals = $breakfast + $lunch + $dinner + $snacks;
                            $total_requests = $emp_requests + $vis_requests;
                            $color = $location_colors[$index % count($location_colors)];
                        ?>
                        <div class="enhanced-location-card" style="--location-color: <?php echo $color; ?>;">
                            <div class="d-flex align-items-center mb-3">
                                <div class="stat-icon" style="background: linear-gradient(135deg, <?php echo $color; ?>, <?php echo $color; ?>aa); width: 50px; height: 50px; margin-right: 1rem;">
                                    <i class="fas fa-building"></i>
                                </div>
                                <div>
                                    <h5 class="mb-1" style="color: <?php echo $color; ?>;"><?php echo $location; ?></h5>
                                    <small class="text-muted">Office Location</small>
                                </div>
                            </div>
                            <div class="row text-center mb-3">
                                <div class="col-6">
                                    <h4 class="text-dark mb-1"><?php echo number_format($total_meals); ?></h4>
                                    <small class="text-muted">Total Meals</small>
                                </div>
                                <div class="col-6">
                                    <h4 class="text-dark mb-1"><?php echo number_format($total_requests); ?></h4>
                                    <small class="text-muted">Requests</small>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <div class="text-center">
                                    <span class="badge" style="background: <?php echo $color; ?>; color: white;"><?php echo $emp_requests; ?></span>
                                    <br><small class="text-muted">Employees</small>
                                </div>
                                <div class="text-center">
                                    <span class="badge" style="background: <?php echo $color; ?>80; color: white;"><?php echo $vis_requests; ?></span>
                                    <br><small class="text-muted">Visitors</small>
                                </div>
                                <div class="text-center">
                                    <span class="badge" style="background: <?php echo $color; ?>60; color: white;"><?php echo $breakfast + $lunch; ?></span>
                                    <br><small class="text-muted">Main Meals</small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="location-chart-container">
                        <h6 class="text-center mb-3">Distribution Chart</h6>
                        <canvas id="locationChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="row" style="margin-top: 2rem;">

                <!-- Live Activity Feed -->
                <div class="col-lg-6 mb-4">
                    <div class="activity-feed">
                        <div class="activity-header">
                            <h5 class="mb-0"><i class="fas fa-stream me-3"></i>Live Activity Feed</h5>
                            <p class="mb-0 mt-2">Recent meal requests in real-time</p>
                        </div>
                        <div style="max-height: 500px; overflow-y: auto;">
                            <?php if (empty($recent_requests)): ?>
                                <div class="text-center py-5">
                                    <div class="stat-icon" style="background: #f8f9fa; margin: 0 auto 1rem; color: #6c757d;">
                                        <i class="fas fa-inbox"></i>
                                    </div>
                                    <h6 class="text-muted">No Activity Today</h6>
                                    <p class="text-muted">Meal requests will appear here in real-time</p>
                                </div>
                            <?php else: ?>
                                <?php foreach (array_slice($recent_requests, 0, 10) as $index => $request): ?>
                                <div class="activity-item">
                                    <div class="d-flex align-items-start">
                                        <div class="stat-icon employees me-3" style="width: 40px; height: 40px; font-size: 1rem;">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($request['emp_name']); ?></h6>
                                            <p class="mb-1 text-muted">EPF: <?php echo htmlspecialchars($request['emp_number']); ?> • <?php echo htmlspecialchars($request['emp_location']); ?></p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="badge" style="background: var(--accent-pink); color: white;"><?php echo $request['total_meals']; ?> meals ordered</span>
                                                <small class="text-muted"><?php echo date('g:i A', strtotime($request['created_at'])); ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions Panel -->
                <div class="col-lg-6 mb-4">
                    <div class="quick-actions">
                        <div class="actions-header">
                            <h5 class="mb-0"><i class="fas fa-bolt me-3"></i>Quick Actions</h5>
                            <p class="mb-0 mt-2">Instant access to key functions</p>
                        </div>
                        <div class="action-grid">
                            <a href="employee_meal_request.php" class="action-btn">
                                <div class="action-icon">
                                    <i class="fas fa-plus"></i>
                                </div>
                                <span>New Employee Request</span>
                            </a>
                            <a href="visitor_meal_request.php" class="action-btn">
                                <div class="action-icon">
                                    <i class="fas fa-user-friends"></i>
                                </div>
                                <span>New Visitor Request</span>
                            </a>
                            <a href="meal_reports.php?date_from=<?php echo $selected_date; ?>&date_to=<?php echo $selected_date; ?>" class="action-btn">
                                <div class="action-icon">
                                    <i class="fas fa-file-export"></i>
                                </div>
                                <span>Generate Report</span>
                            </a>
                            <a href="meal_analytics.php" class="action-btn">
                                <div class="action-icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <span>View Analytics</span>
                            </a>
                            <a href="meal_time_manager.php" class="action-btn">
                                <div class="action-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <span>Manage Times</span>
                            </a>
                            <a href="daily_overview.php" class="action-btn" onclick="refreshData(); return false;">
                                <div class="action-icon">
                                    <i class="fas fa-sync-alt"></i>
                                </div>
                                <span>Refresh Data</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enhanced real-time updates
        function updateAllTimes() {
            const now = new Date();
            const timeString = now.toLocaleString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });
            const timeStringShort = now.toLocaleString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });

            // Update all time displays
            const realTimeDisplay = document.getElementById('realTimeDisplay');
            const liveTime = document.getElementById('liveTime');
            const lastUpdate = document.getElementById('lastUpdate');

            if (realTimeDisplay) realTimeDisplay.textContent = timeString;
            if (liveTime) liveTime.textContent = timeStringShort;
            if (lastUpdate) lastUpdate.textContent = 'just now';
        }

        // Update times immediately and then every second
        updateAllTimes();
        setInterval(updateAllTimes, 1000);

        // Update last update time every 10 seconds
        let updateCounter = 0;
        setInterval(() => {
            updateCounter++;
            const lastUpdate = document.getElementById('lastUpdate');
            if (lastUpdate) {
                if (updateCounter < 6) {
                    lastUpdate.textContent = updateCounter * 10 + 's ago';
                } else {
                    lastUpdate.textContent = Math.floor(updateCounter / 6) + 'm ago';
                }
            }
        }, 10000);

        function changeDate() {
            const selectedDate = document.getElementById('dateSelector').value;
            window.location.href = `?date=${selectedDate}`;
        }

        function refreshData() {
            const refreshBtn = document.getElementById('refreshBtn');
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Refreshing...';
            refreshBtn.classList.add('auto-refresh');

            // Simulate refresh delay
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }

        // Auto refresh every 5 minutes
        setInterval(() => {
            if (document.getElementById('dateSelector').value === '<?php echo date('Y-m-d'); ?>') {
                refreshData();
            }
        }, 300000);

        // Meal Order Status Chart
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, Chart.js available:', typeof Chart !== 'undefined');
            try {
                const statusCtxElement = document.getElementById('statusChart');
                console.log('Status chart canvas element:', statusCtxElement);
                if (statusCtxElement) {
                    const statusCtx = statusCtxElement.getContext('2d');
                    console.log('Status chart context:', statusCtx);
        const statusData = [
            <?php
            $open_count = 0;
            $closed_count = 0;
            foreach ($time_settings as $setting):
                $is_today = $selected_date === $current_date;
                $deadline_passed = $is_today && $current_time > $setting['order_deadline_time'];
                if ($deadline_passed) {
                    $closed_count++;
                } else {
                    $open_count++;
                }
            endforeach;
            echo $open_count . ', ' . $closed_count;
            ?>
        ];

        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Open', 'Closed'],
                datasets: [{
                    data: statusData,
                    backgroundColor: ['#28A745', '#DC3545'],
                    borderWidth: 0,
                    cutout: '60%'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 10
                        }
                    }
                }
            }
                    });
                }

                // Location Chart
                const locationCtxElement = document.getElementById('locationChart');
                console.log('Location chart canvas element:', locationCtxElement);
                if (locationCtxElement) {
                    const locationCtx = locationCtxElement.getContext('2d');
                    console.log('Location chart context:', locationCtx);
        const locationLabels = <?php echo json_encode($locations); ?>;
        const locationMeals = [
            <?php
            $meal_data = [];
            foreach ($locations as $location) {
                $emp_requests = getLocationData($employee_data, $location, 'total_requests');
                $vis_requests = getLocationData($visitor_data, $location, 'total_requests');
                $breakfast = getLocationData($employee_data, $location, 'breakfast_total') + getLocationData($visitor_data, $location, 'breakfast_total');
                $lunch = getLocationData($employee_data, $location, 'lunch_total') + getLocationData($visitor_data, $location, 'lunch_total');
                $dinner = getLocationData($employee_data, $location, 'dinner_total') + getLocationData($visitor_data, $location, 'dinner_total');
                $snacks = getLocationData($employee_data, $location, 'snack1_total') +
                         getLocationData($employee_data, $location, 'snack2_total') +
                         getLocationData($employee_data, $location, 'snack3_total') +
                         getLocationData($visitor_data, $location, 'snack1_total') +
                         getLocationData($visitor_data, $location, 'snack2_total');
                $total_meals = $breakfast + $lunch + $dinner + $snacks;
                $meal_data[] = $total_meals;
            }
            echo implode(', ', $meal_data);
            ?>
        ];

        new Chart(locationCtx, {
            type: 'bar',
            data: {
                labels: locationLabels,
                datasets: [{
                    label: 'Total Meals',
                    data: locationMeals,
                    backgroundColor: ['#FF6B35', '#28A745', '#007BFF', '#6F42C1', '#FFC107'],
                    borderColor: ['#E55A2B', '#218838', '#0056B3', '#5A2D91', '#E0A800'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
                    });
                }

            } catch (error) {
                console.error('Chart initialization error:', error);
            }
        });

    </script>
</body>
</html>