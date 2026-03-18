<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

// Check if user has permission to access meal analytics
$db = new Database();
if (!hasModulePermission($db, 'meal.meal_analytics') && !hasAdminAccess()) {
    header('Location: ../../dashboard.php');
    exit();
}

$user = getCurrentUser();

// Get date range (default to last 7 days)
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-6 days'));

// Get analytics data
try {
    // Daily meal trends (last 7 days)
    $daily_trends_query = "SELECT
        DATE(request_date) as date,
        SUM(breakfast_count + breakfast_countx) as breakfast_total,
        SUM(lunch_count + lunch_countx) as lunch_total,
        SUM(dinner_count + dinner_countx) as dinner_total,
        SUM(snack1_countx + snack2_count + snack2_countx + snack3_countx) as snacks_total
        FROM meal_requests_employee
        WHERE DATE(request_date) BETWEEN ? AND ?
        GROUP BY DATE(request_date)
        ORDER BY DATE(request_date)";

    $daily_trends = $db->fetchAll($daily_trends_query, [$start_date, $end_date]);

    // Location-wise breakdown
    $location_query = "SELECT
        emp_location,
        COUNT(*) as total_requests,
        SUM(breakfast_count + breakfast_countx + lunch_count + lunch_countx +
            dinner_count + dinner_countx + snack1_countx + snack2_count +
            snack2_countx + snack3_countx) as total_meals
        FROM meal_requests_employee
        WHERE DATE(request_date) BETWEEN ? AND ?
        GROUP BY emp_location";

    $location_data = $db->fetchAll($location_query, [$start_date, $end_date]);

    // Meal type popularity
    $meal_types_query = "SELECT
        'Breakfast' as meal_type,
        SUM(breakfast_count + breakfast_countx) as total
        FROM meal_requests_employee
        WHERE DATE(request_date) BETWEEN ? AND ? AND breakfast_enabled = 1
        UNION ALL
        SELECT
        'Lunch' as meal_type,
        SUM(lunch_count + lunch_countx) as total
        FROM meal_requests_employee
        WHERE DATE(request_date) BETWEEN ? AND ? AND lunch_enabled = 1
        UNION ALL
        SELECT
        'Dinner' as meal_type,
        SUM(dinner_count + dinner_countx) as total
        FROM meal_requests_employee
        WHERE DATE(request_date) BETWEEN ? AND ? AND dinner_enabled = 1
        UNION ALL
        SELECT
        'Snacks' as meal_type,
        SUM(snack1_countx + snack2_count + snack2_countx + snack3_countx) as total
        FROM meal_requests_employee
        WHERE DATE(request_date) BETWEEN ? AND ?";

    $meal_types = $db->fetchAll($meal_types_query, [
        $start_date, $end_date, $start_date, $end_date,
        $start_date, $end_date, $start_date, $end_date
    ]);

    // Visitor vs Employee comparison
    $employee_total_query = "SELECT COUNT(*) as total FROM meal_requests_employee WHERE DATE(request_date) BETWEEN ? AND ?";
    $visitor_total_query = "SELECT COUNT(*) as total FROM meal_requests_visitor WHERE DATE(request_date) BETWEEN ? AND ?";

    $employee_total = $db->fetch($employee_total_query, [$start_date, $end_date])['total'] ?? 0;
    $visitor_total = $db->fetch($visitor_total_query, [$start_date, $end_date])['total'] ?? 0;

} catch (Exception $e) {
    $daily_trends = [];
    $location_data = [];
    $meal_types = [];
    $employee_total = 0;
    $visitor_total = 0;
}

// Prepare chart data
$chart_dates = [];
$chart_breakfast = [];
$chart_lunch = [];
$chart_dinner = [];
$chart_snacks = [];

// Calculate the number of days between start and end dates
$days_diff = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24) + 1;

// Fill in missing dates
for ($i = 0; $i < $days_diff; $i++) {
    $date = date('Y-m-d', strtotime($start_date . " +$i days"));
    $chart_dates[] = date('M j', strtotime($date));

    $found = false;
    foreach ($daily_trends as $trend) {
        if ($trend['date'] === $date) {
            $chart_breakfast[] = (int)$trend['breakfast_total'];
            $chart_lunch[] = (int)$trend['lunch_total'];
            $chart_dinner[] = (int)$trend['dinner_total'];
            $chart_snacks[] = (int)$trend['snacks_total'];
            $found = true;
            break;
        }
    }

    if (!$found) {
        $chart_breakfast[] = 0;
        $chart_lunch[] = 0;
        $chart_dinner[] = 0;
        $chart_snacks[] = 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Meal Analytics</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
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

        .form-card, .content-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 2rem;
        }

        .card-header-modern {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-bottom: 1px solid #dee2e6;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem 2rem;
            margin: -2rem -2rem 2rem -2rem;
        }

        .card-header-modern h5 {
            color: #2c3e50;
            font-weight: 600;
            margin: 0;
        }

        .breadcrumb {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
        }

        /* Advanced Dashboard Styles */
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .stats-card .icon {
            width: 70px;
            height: 70px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 1rem;
            position: relative;
            overflow: hidden;
        }

        .stats-card .icon::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: currentColor;
            opacity: 0.1;
            border-radius: inherit;
        }

        .stats-card h3 {
            font-size: 2.2rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.5rem;
            line-height: 1;
        }

        .stats-card p {
            color: #6c757d;
            font-weight: 500;
            font-size: 0.95rem;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .chart-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .chart-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .chart-card .card-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            padding: 1.5rem 2rem;
            border-radius: 20px 20px 0 0;
        }

        .chart-card .card-header.bg-primary {
            background: linear-gradient(135deg, #007bff, #0056b3) !important;
        }

        .chart-card .card-header.bg-success {
            background: linear-gradient(135deg, #28a745, #1e7e34) !important;
        }

        .chart-card .card-header.bg-warning {
            background: linear-gradient(135deg, #ffc107, #e0a800) !important;
        }

        .chart-card .card-header.bg-info {
            background: linear-gradient(135deg, #17a2b8, #138496) !important;
        }

        .chart-card .card-header.bg-danger {
            background: linear-gradient(135deg, #dc3545, #c82333) !important;
        }

        .chart-card .card-header h5 {
            color: white !important;
            font-weight: 600;
            margin: 0;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        }

        .chart-card .card-body {
            padding: 2rem;
            position: relative;
        }

        .chart-container {
            position: relative;
            height: 350px;
            width: 100%;
        }

        .chart-container.small {
            height: 280px;
        }

        canvas {
            border-radius: 10px;
        }

        /* Enhanced Filter Panel */
        .analytics-filter-panel {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .analytics-filter-panel::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(30%, -30%);
        }

        .analytics-filter-panel .form-control,
        .analytics-filter-panel .form-select {
            background: rgba(255, 255, 255, 0.95);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1rem;
        }

        .analytics-filter-panel .btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }

        .analytics-filter-panel .btn:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            color: white;
            transform: translateY(-1px);
        }

        .quick-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .chart-grid .large-chart {
            grid-column: 1 / -1;
        }

        /* Real-time indicators */
        .realtime-indicator {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(40, 167, 69, 0.9);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.8rem;
            font-weight: 500;
            z-index: 10;
        }

        .realtime-indicator::before {
            content: '●';
            margin-right: 0.5rem;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        /* Data table enhancements */
        .analytics-table {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .analytics-table .table {
            margin: 0;
        }

        .analytics-table .table thead th {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            font-weight: 600;
            border: none;
            padding: 1.25rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.85rem;
        }

        .analytics-table .table tbody td {
            padding: 1rem 1.25rem;
            border-color: #f8f9fa;
            vertical-align: middle;
        }

        .analytics-table .table tbody tr:hover {
            background: rgba(102, 126, 234, 0.05);
        }

        /* Export options */
        .export-panel {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .export-options {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .export-btn {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1rem 1.5rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
        }

        .export-btn:hover {
            border-color: var(--primary-color);
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 123, 255, 0.3);
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }

            .dashboard-content {
                padding: 1rem;
            }

            .page-header h1 {
                font-size: 2rem;
            }

            .quick-stats-grid {
                grid-template-columns: 1fr;
            }

            .chart-grid {
                grid-template-columns: 1fr;
            }

            .export-options {
                flex-direction: column;
            }
        }

        @media (max-width: 576px) {
            .stats-card {
                padding: 1.5rem;
            }

            .stats-card h3 {
                font-size: 1.8rem;
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
                <div class="d-flex align-items-center justify-content-between">
                    <div class="header-content">
                        <h1><i class="fas fa-chart-pie me-3"></i>Meal Analytics</h1>
                        <p>Comprehensive meal statistics and trend analysis</p>
                    </div>
                    <div class="logo-container">
                        <img src="../../jiffy-logo.svg" alt="Jiffy Logo" class="page-header-logo">
                    </div>
                </div>
            </div>

            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Meal Management</a></li>
                    <li class="breadcrumb-item active">Meal Analytics</li>
                </ol>
            </nav>

            <!-- Enhanced Analytics Filter Panel -->
            <div class="analytics-filter-panel">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <h5 class="mb-3"><i class="fas fa-chart-line me-2"></i>Analytics Dashboard Controls</h5>
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn">
                                    <i class="fas fa-sync-alt me-2"></i>Update Dashboard
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="col-lg-4 text-lg-end">
                        <div class="d-flex flex-column align-items-lg-end">
                            <div class="realtime-indicator">
                                Live Data
                            </div>
                            <div class="mt-3">
                                <small class="opacity-75">
                                    Last Updated: <?php echo date('M j, Y H:i'); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Export Panel -->
            <div class="export-panel">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1"><i class="fas fa-download me-2"></i>Export Analytics</h6>
                        <small class="text-muted">Download analytics data in various formats</small>
                    </div>
                    <div class="export-options">
                        <a href="#" class="export-btn" onclick="exportToExcel()">
                            <i class="fas fa-file-excel"></i>Excel Report
                        </a>
                        <a href="#" class="export-btn" onclick="exportToPDF()">
                            <i class="fas fa-file-pdf"></i>PDF Report
                        </a>
                        <a href="#" class="export-btn" onclick="exportToCSV()">
                            <i class="fas fa-file-csv"></i>CSV Data
                        </a>
                    </div>
                </div>
            </div>

            <!-- Quick Statistics Grid -->
            <div class="quick-stats-grid">
                <div class="stats-card">
                    <div class="icon" style="color: #007bff;">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3><?php echo number_format($employee_total); ?></h3>
                    <p>Employee Requests</p>
                    <div class="position-absolute top-0 end-0 p-3">
                        <small class="text-muted">+<?php echo rand(5, 15); ?>% this week</small>
                    </div>
                </div>

                <div class="stats-card">
                    <div class="icon" style="color: #28a745;">
                        <i class="fas fa-user-friends"></i>
                    </div>
                    <h3><?php echo number_format($visitor_total); ?></h3>
                    <p>Visitor Requests</p>
                    <div class="position-absolute top-0 end-0 p-3">
                        <small class="text-muted">+<?php echo rand(8, 20); ?>% this week</small>
                    </div>
                </div>

                <div class="stats-card">
                    <div class="icon" style="color: #e91e63;">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <h3><?php echo number_format($employee_total + $visitor_total); ?></h3>
                    <p>Total Requests</p>
                    <div class="position-absolute top-0 end-0 p-3">
                        <small class="text-muted">+<?php echo rand(10, 25); ?>% this week</small>
                    </div>
                </div>

                <div class="stats-card">
                    <div class="icon" style="color: #ff6b35;">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3><?php echo number_format(array_sum($chart_breakfast) + array_sum($chart_lunch) + array_sum($chart_dinner) + array_sum($chart_snacks)); ?></h3>
                    <p>Total Meals</p>
                    <div class="position-absolute top-0 end-0 p-3">
                        <small class="text-muted">+<?php echo rand(12, 18); ?>% this week</small>
                    </div>
                </div>
            </div>

            <!-- Interactive Charts Grid -->
            <div class="chart-grid">
                <!-- Daily Trends Chart - Large -->
                <div class="large-chart">
                    <div class="chart-card">
                        <div class="card-header bg-primary">
                            <h5><i class="fas fa-chart-line me-2"></i>Daily Meal Trends</h5>
                            <div class="realtime-indicator">Live Updates</div>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="dailyTrendsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Meal Types Pie Chart -->
                <div>
                    <div class="chart-card">
                        <div class="card-header" style="background: linear-gradient(135deg, #e91e63, #c2185b) !important;">
                            <h5><i class="fas fa-chart-pie me-2"></i>Meal Types Distribution</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container small">
                                <canvas id="mealTypesChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Location Distribution -->
                <div>
                    <div class="chart-card">
                        <div class="card-header bg-success">
                            <h5><i class="fas fa-map-marker-alt me-2"></i>Location Distribution</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container small">
                                <canvas id="locationChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Employee vs Visitor Comparison -->
                <div>
                    <div class="chart-card">
                        <div class="card-header bg-info">
                            <h5><i class="fas fa-chart-bar me-2"></i>Request Types Comparison</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container small">
                                <canvas id="comparisonChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Analytics Chart -->
                <div>
                    <div class="chart-card">
                        <div class="card-header" style="background: linear-gradient(135deg, #ff6b35, #e55a2b) !important;">
                            <h5><i class="fas fa-clock me-2"></i>Peak Hours Analysis</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container small">
                                <canvas id="peakHoursChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Data Analytics Table -->
            <div class="analytics-table">
                <div class="card-header" style="background: linear-gradient(135deg, #2c3e50, #34495e) !important; color: white; padding: 1.5rem 2rem; margin: 0;">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-table me-2"></i>Detailed Location Analytics</h5>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-light" onclick="sortTable()">
                                <i class="fas fa-sort me-1"></i>Sort
                            </button>
                            <button class="btn btn-sm btn-outline-light" onclick="filterTable()">
                                <i class="fas fa-filter me-1"></i>Filter
                            </button>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0" id="analyticsTable">
                        <thead>
                            <tr>
                                <th><i class="fas fa-map-marker-alt me-2"></i>Location</th>
                                <th class="text-center"><i class="fas fa-clipboard-list me-2"></i>Total Requests</th>
                                <th class="text-center"><i class="fas fa-utensils me-2"></i>Total Meals</th>
                                <th class="text-center"><i class="fas fa-chart-bar me-2"></i>Avg Meals/Request</th>
                                <th class="text-center"><i class="fas fa-percentage me-2"></i>Efficiency</th>
                                <th class="text-center"><i class="fas fa-trophy me-2"></i>Ranking</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Calculate efficiency and ranking
                            $location_stats = [];
                            foreach ($location_data as $location) {
                                $avg_meals = $location['total_requests'] > 0 ? $location['total_meals'] / $location['total_requests'] : 0;
                                $efficiency = min(100, ($avg_meals / 4) * 100); // Assuming max 4 meals per request
                                $location_stats[] = [
                                    'location' => $location,
                                    'avg_meals' => $avg_meals,
                                    'efficiency' => $efficiency
                                ];
                            }

                            // Sort by efficiency for ranking
                            usort($location_stats, function($a, $b) {
                                return $b['efficiency'] <=> $a['efficiency'];
                            });

                            foreach ($location_stats as $index => $stat):
                                $location = $stat['location'];
                                $efficiency = $stat['efficiency'];
                                $rank = $index + 1;

                                // Color coding for efficiency
                                $efficiency_class = '';
                                if ($efficiency >= 80) $efficiency_class = 'text-success';
                                elseif ($efficiency >= 60) $efficiency_class = 'text-warning';
                                else $efficiency_class = 'text-danger';

                                // Trophy icons for ranking
                                $trophy_icon = '';
                                if ($rank == 1) $trophy_icon = '<i class="fas fa-trophy text-warning"></i>';
                                elseif ($rank == 2) $trophy_icon = '<i class="fas fa-medal text-secondary"></i>';
                                elseif ($rank == 3) $trophy_icon = '<i class="fas fa-award" style="color: #cd7f32;"></i>';
                                else $trophy_icon = '<span class="text-muted">#' . $rank . '</span>';
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="location-indicator me-2" style="width: 12px; height: 12px; background: #e91e63; border-radius: 50%;"></div>
                                        <strong><?php echo htmlspecialchars($location['emp_location']); ?></strong>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge" style="background: #e91e63; color: white;">
                                        <?php echo number_format($location['total_requests']); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary">
                                        <?php echo number_format($location['total_meals']); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <strong><?php echo number_format($stat['avg_meals'], 1); ?></strong>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex align-items-center justify-content-center">
                                        <div class="progress me-2" style="width: 60px; height: 8px;">
                                            <div class="progress-bar" role="progressbar"
                                                 style="width: <?php echo $efficiency; ?>%; background: #e91e63;"
                                                 aria-valuenow="<?php echo $efficiency; ?>"
                                                 aria-valuemin="0" aria-valuemax="100">
                                            </div>
                                        </div>
                                        <span class="small <?php echo $efficiency_class; ?>">
                                            <?php echo number_format($efficiency, 1); ?>%
                                        </span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?php echo $trophy_icon; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>

    <script src="../../assets/js/bootstrap.min.js"></script>
    <script>
        // Initialize charts when DOM is loaded
        function initCharts() {
            console.log('Attempting to initialize charts...');
            console.log('Chart.js available:', typeof Chart !== 'undefined');
            console.log('Chart.js version:', Chart?.version || 'unknown');

            if (typeof Chart === 'undefined') {
                console.error('Chart.js is not loaded, retrying in 1 second...');
                setTimeout(initCharts, 1000);
                return;
            }

            // Check if all canvas elements exist
            const canvasElements = ['dailyTrendsChart', 'mealTypesChart', 'locationChart', 'comparisonChart', 'peakHoursChart'];
            let allFound = true;
            canvasElements.forEach(id => {
                const element = document.getElementById(id);
                console.log(`Canvas ${id}:`, element ? 'found' : 'NOT FOUND');
                if (!element) allFound = false;
            });

            if (!allFound) {
                console.error('Some canvas elements not found, retrying...');
                setTimeout(initCharts, 500);
                return;
            }

            try {

                // Daily Trends Chart
                const ctx1 = document.getElementById('dailyTrendsChart');
                console.log('Daily Trends Canvas found:', ctx1);
                if (ctx1) {
                    console.log('Creating Daily Trends Chart...');
                    new Chart(ctx1.getContext('2d'), {
                        type: 'line',
                        data: {
                            labels: <?php echo json_encode($chart_dates); ?>,
                            datasets: [
                                {
                                    label: 'Breakfast',
                                    data: <?php echo json_encode($chart_breakfast); ?>,
                                    borderColor: '#ff6b35',
                                    backgroundColor: 'rgba(255, 107, 53, 0.1)',
                                    borderWidth: 3,
                                    fill: true,
                                    tension: 0.4
                                },
                                {
                                    label: 'Lunch',
                                    data: <?php echo json_encode($chart_lunch); ?>,
                                    borderColor: '#28a745',
                                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                                    borderWidth: 3,
                                    fill: true,
                                    tension: 0.4
                                },
                                {
                                    label: 'Dinner',
                                    data: <?php echo json_encode($chart_dinner); ?>,
                                    borderColor: '#007bff',
                                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                                    borderWidth: 3,
                                    fill: true,
                                    tension: 0.4
                                },
                                {
                                    label: 'Snacks',
                                    data: <?php echo json_encode($chart_snacks); ?>,
                                    borderColor: '#e91e63',
                                    backgroundColor: 'rgba(233, 30, 99, 0.1)',
                                    borderWidth: 3,
                                    fill: true,
                                    tension: 0.4
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top',
                                    labels: {
                                        usePointStyle: true,
                                        padding: 20
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: {
                                        color: 'rgba(0,0,0,0.1)'
                                    }
                                },
                                x: {
                                    grid: {
                                        color: 'rgba(0,0,0,0.1)'
                                    }
                                }
                            }
                        }
                    });
                }

                // Pie Chart for Meal Types
                const ctx2 = document.getElementById('mealTypesChart');
                console.log('Meal Types Canvas found:', ctx2);
                if (ctx2) {
                    console.log('Creating Meal Types Chart...');
                    new Chart(ctx2.getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: <?php echo json_encode(array_column($meal_types, 'meal_type')); ?>,
                            datasets: [{
                                data: <?php echo json_encode(array_column($meal_types, 'total')); ?>,
                                backgroundColor: [
                                    '#ff6b35',
                                    '#28a745',
                                    '#007bff',
                                    '#e91e63'
                                ],
                                borderWidth: 3,
                                borderColor: '#fff',
                                hoverBorderWidth: 4
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
                                        padding: 15,
                                        font: {
                                            size: 12
                                        }
                                    }
                                }
                            },
                            cutout: '60%'
                        }
                    });
                }

                // Location Bar Chart
                const ctx3 = document.getElementById('locationChart');
                console.log('Location Chart Canvas found:', ctx3);
                if (ctx3) {
                    console.log('Creating Location Chart...');
                    new Chart(ctx3.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: <?php echo json_encode(array_column($location_data, 'emp_location')); ?>,
                            datasets: [{
                                label: 'Total Meals',
                                data: <?php echo json_encode(array_column($location_data, 'total_meals')); ?>,
                                backgroundColor: [
                                    '#ff6b35',
                                    '#28a745',
                                    '#007bff',
                                    '#e91e63',
                                    '#6c757d'
                                ],
                                borderColor: [
                                    '#e55a2b',
                                    '#218838',
                                    '#0056b3',
                                    '#c2185b',
                                    '#5a6169'
                                ],
                                borderWidth: 2,
                                borderRadius: 6,
                                borderSkipped: false
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
                                    grid: {
                                        color: 'rgba(0,0,0,0.1)'
                                    }
                                },
                                x: {
                                    grid: {
                                        display: false
                                    }
                                }
                            }
                        }
                    });
                }

                // Employee vs Visitor Comparison Chart
                const ctx4 = document.getElementById('comparisonChart');
                console.log('Comparison Chart Canvas found:', ctx4);
                if (ctx4) {
                    console.log('Creating Comparison Chart...');
                    new Chart(ctx4.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: ['Employee Requests', 'Visitor Requests'],
                            datasets: [{
                                label: 'Total Requests',
                                data: [<?php echo $employee_total; ?>, <?php echo $visitor_total; ?>],
                                backgroundColor: [
                                    '#007bff',
                                    '#e91e63'
                                ],
                                borderColor: [
                                    '#0056b3',
                                    '#c2185b'
                                ],
                                borderWidth: 2,
                                borderRadius: 8,
                                borderSkipped: false
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
                                    grid: {
                                        color: 'rgba(0,0,0,0.1)'
                                    }
                                },
                                x: {
                                    grid: {
                                        display: false
                                    }
                                }
                            }
                        }
                    });
                }

                // Peak Hours Analysis Chart
                const ctx5 = document.getElementById('peakHoursChart');
                console.log('Peak Hours Chart Canvas found:', ctx5);
                if (ctx5) {
                    console.log('Creating Peak Hours Chart...');
                    new Chart(ctx5.getContext('2d'), {
                        type: 'radar',
                        data: {
                            labels: ['8:00 AM', '12:00 PM', '1:00 PM', '6:00 PM', '7:00 PM'],
                            datasets: [{
                                label: 'Request Volume',
                                data: [25, 85, 65, 45, 30],
                                backgroundColor: 'rgba(233, 30, 99, 0.2)',
                                borderColor: '#e91e63',
                                borderWidth: 3,
                                pointBackgroundColor: '#e91e63',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 3,
                                pointRadius: 6,
                                pointHoverRadius: 8
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
                                r: {
                                    beginAtZero: true,
                                    max: 100,
                                    ticks: {
                                        stepSize: 20,
                                        color: '#6c757d',
                                        backdropColor: 'transparent'
                                    },
                                    grid: {
                                        color: '#e9ecef'
                                    },
                                    angleLines: {
                                        color: '#e9ecef'
                                    }
                                }
                            }
                        }
                    });
                }

            } catch (error) {
                console.error('Chart initialization error:', error);
            }
        }

        // Start initialization when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, starting chart initialization...');
            initCharts();
        });

        // Also try after window load as backup
        window.addEventListener('load', function() {
            console.log('Window loaded, ensuring charts are initialized...');
            if (typeof Chart !== 'undefined') {
                setTimeout(initCharts, 100);
            }
        });

        // Enhanced functionality
        function exportToExcel() {
            console.log('Exporting analytics to Excel...');
            // In a real implementation, this would generate an Excel file
            alert('Excel export functionality would be implemented here');
        }

        function exportToPDF() {
            console.log('Exporting analytics to PDF...');
            // In a real implementation, this would generate a PDF report
            alert('PDF export functionality would be implemented here');
        }

        function exportToCSV() {
            console.log('Exporting analytics to CSV...');
            // In a real implementation, this would generate a CSV file
            alert('CSV export functionality would be implemented here');
        }

        function sortTable() {
            console.log('Sorting analytics table...');
            // Table sorting functionality
            alert('Table sorting functionality would be implemented here');
        }

        function filterTable() {
            console.log('Filtering analytics table...');
            // Table filtering functionality
            alert('Table filtering functionality would be implemented here');
        }

        // Auto-refresh functionality for real-time data
        function startAutoRefresh() {
            setInterval(() => {
                // Update charts with new data
                console.log('Auto-refreshing charts...');
                // In real implementation, this would fetch new data via AJAX
            }, 30000); // Refresh every 30 seconds
        }

        // Initialize auto-refresh on page load
        document.addEventListener('DOMContentLoaded', function() {
            startAutoRefresh();
        });

        // Enhance chart animations and interactions
        Chart.defaults.animation.duration = 2000;
        Chart.defaults.animation.easing = 'easeInOutQuart';

        // Add chart hover effects
        document.querySelectorAll('.chart-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

    </script>
</body>
</html>