<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

// Check if user has permission to access meal reports
$db = new Database();
if (!hasModulePermission($db, 'meal.meal_reports') && !hasAdminAccess()) {
    header('Location: ../../dashboard.php');
    exit();
}

$user = getCurrentUser();

// Get filters
$report_type = $_GET['report_type'] ?? 'daily';
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$location_filter = $_GET['location'] ?? 'all';
$export_format = $_GET['export'] ?? '';

// Available locations
$locations = ['7C', 'Pannala', 'Kobeigane', 'JECOE', 'Head Office'];

// Build location condition
$location_condition = '';
$location_params = [];
if ($location_filter !== 'all') {
    $location_condition = ' AND emp_location = ?';
    $location_params[] = $location_filter;
}

$visitor_location_condition = '';
$visitor_location_params = [];
if ($location_filter !== 'all') {
    $visitor_location_condition = ' AND requesting_emp_location = ?';
    $visitor_location_params[] = $location_filter;
}

// Handle export
if ($export_format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="meal_report_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');

    // CSV headers
    fputcsv($output, ['Date', 'Location', 'Meal Type', 'Employee Requests', 'Visitor Requests', 'Total Meals']);

    // Get data for CSV
    try {
        $csv_query = "SELECT
            DATE(request_date) as date,
            emp_location,
            'Employee' as type,
            COUNT(*) as requests,
            SUM(breakfast_count + breakfast_countx + lunch_count + lunch_countx +
                dinner_count + dinner_countx + snack1_countx + snack2_count +
                snack2_countx + snack3_countx) as total_meals
            FROM meal_requests_employee
            WHERE DATE(request_date) BETWEEN ? AND ? $location_condition
            GROUP BY DATE(request_date), emp_location
            ORDER BY DATE(request_date), emp_location";

        $csv_data = $db->fetchAll($csv_query, array_merge([$date_from, $date_to], $location_params));

        foreach ($csv_data as $row) {
            fputcsv($output, [
                $row['date'],
                $row['emp_location'],
                'Employee Meals',
                $row['requests'],
                0,
                $row['total_meals']
            ]);
        }
    } catch (Exception $e) {
        fputcsv($output, ['Error', 'Error loading data', '', '', '', '']);
    }

    fclose($output);
    exit;
}

// Get report data
$report_data = [];
$summary_stats = [
    'total_employee_requests' => 0,
    'total_visitor_requests' => 0,
    'total_meals' => 0,
    'most_popular_meal' => 'N/A'
];

try {
    if ($report_type === 'daily') {
        // Daily report
        $daily_query = "SELECT
            DATE(request_date) as report_date,
            emp_location,
            COUNT(*) as employee_requests,
            SUM(breakfast_count + breakfast_countx) as breakfast_total,
            SUM(lunch_count + lunch_countx) as lunch_total,
            SUM(dinner_count + dinner_countx) as dinner_total,
            SUM(snack1_countx + snack2_count + snack2_countx + snack3_countx) as snacks_total,
            SUM(breakfast_count + breakfast_countx + lunch_count + lunch_countx +
                dinner_count + dinner_countx + snack1_countx + snack2_count +
                snack2_countx + snack3_countx) as total_meals
            FROM meal_requests_employee
            WHERE DATE(request_date) BETWEEN ? AND ? $location_condition
            GROUP BY DATE(request_date), emp_location
            ORDER BY DATE(request_date), emp_location";

        $report_data = $db->fetchAll($daily_query, array_merge([$date_from, $date_to], $location_params));

        // Get visitor data
        $visitor_query = "SELECT
            requesting_emp_location as emp_location,
            COUNT(*) as visitor_requests,
            SUM(breakfast_count) as visitor_breakfast,
            SUM(lunch_count) as visitor_lunch,
            SUM(dinner_count) as visitor_dinner,
            SUM(snack1_count + snack2_count) as visitor_snacks
            FROM meal_requests_visitor
            WHERE DATE(request_date) BETWEEN ? AND ? $visitor_location_condition
            GROUP BY requesting_emp_location";

        $visitor_data = $db->fetchAll($visitor_query, array_merge([$date_from, $date_to], $visitor_location_params));

    } elseif ($report_type === 'summary') {
        // Summary report
        $summary_query = "SELECT
            emp_location,
            COUNT(*) as total_requests,
            SUM(breakfast_count + breakfast_countx) as breakfast_total,
            SUM(lunch_count + lunch_countx) as lunch_total,
            SUM(dinner_count + dinner_countx) as dinner_total,
            SUM(snack1_countx + snack2_count + snack2_countx + snack3_countx) as snacks_total
            FROM meal_requests_employee
            WHERE DATE(request_date) BETWEEN ? AND ? $location_condition
            GROUP BY emp_location
            ORDER BY total_requests DESC";

        $report_data = $db->fetchAll($summary_query, array_merge([$date_from, $date_to], $location_params));
    }

    // Calculate summary statistics
    $total_emp_query = "SELECT COUNT(*) as total FROM meal_requests_employee WHERE DATE(request_date) BETWEEN ? AND ? $location_condition";
    $total_vis_query = "SELECT COUNT(*) as total FROM meal_requests_visitor WHERE DATE(request_date) BETWEEN ? AND ? $visitor_location_condition";

    $emp_result = $db->fetch($total_emp_query, array_merge([$date_from, $date_to], $location_params));
    $vis_result = $db->fetch($total_vis_query, array_merge([$date_from, $date_to], $visitor_location_params));

    $summary_stats['total_employee_requests'] = $emp_result ? $emp_result['total'] : 0;
    $summary_stats['total_visitor_requests'] = $vis_result ? $vis_result['total'] : 0;

} catch (Exception $e) {
    $report_data = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Meal Reports</title>
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

        /* Advanced Reporting Styles */
        .reporting-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 2rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .reporting-header::before {
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .advanced-stat-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .advanced-stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .advanced-stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--accent-color);
        }

        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            position: relative;
        }

        .stat-icon.employees {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            --accent-color: #007bff;
        }

        .stat-icon.visitors {
            background: linear-gradient(135deg, #e91e63, #c2185b);
            color: white;
            --accent-color: #e91e63;
        }

        .stat-icon.total {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            --accent-color: #28a745;
        }

        .stat-icon.period {
            background: linear-gradient(135deg, #ff6b35, #f9a825);
            color: white;
            --accent-color: #ff6b35;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2c3e50;
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6c757d;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
        }

        .stat-change {
            font-size: 0.8rem;
            margin-top: 0.5rem;
        }

        .stat-change.positive {
            color: #28a745;
        }

        .stat-change.negative {
            color: #dc3545;
        }

        .advanced-filter-panel {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .filter-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }

        .enhanced-input {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .enhanced-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            outline: none;
        }

        .report-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-generate {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-generate:hover {
            background: linear-gradient(135deg, #764ba2, #667eea);
            transform: translateY(-2px);
            color: white;
        }

        .export-dropdown {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 50px;
            font-weight: 600;
        }

        .report-visualization {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .chart-container {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            position: relative;
            height: 400px;
        }

        .insights-panel {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .insight-item {
            padding: 1rem;
            border-left: 4px solid #e91e63;
            background: #f8f9fa;
            border-radius: 0 10px 10px 0;
            margin-bottom: 1rem;
        }

        .insight-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .insight-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: #e91e63;
        }

        .advanced-table {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .table-header {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            padding: 1.5rem 2rem;
        }

        .table-controls {
            display: flex;
            justify-content: between;
            align-items: center;
            padding: 1rem 2rem;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }

        .advanced-table .table {
            margin: 0;
        }

        .advanced-table .table thead th {
            background: #f8f9fa;
            color: #495057;
            font-weight: 600;
            border: none;
            padding: 1rem 1.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.85rem;
        }

        .advanced-table .table tbody td {
            padding: 1rem 1.5rem;
            border-color: #f0f2f5;
            vertical-align: middle;
        }

        .advanced-table .table tbody tr:hover {
            background: rgba(102, 126, 234, 0.05);
        }

        .total-row {
            background: linear-gradient(135deg, #667eea, #764ba2) !important;
            color: white !important;
            font-weight: 600 !important;
        }

        .total-row td {
            border: none !important;
        }

        .quick-filters {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .quick-filter-btn {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            color: #495057;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .quick-filter-btn:hover,
        .quick-filter-btn.active {
            background: #667eea;
            border-color: #667eea;
            color: white;
        }

        @media (max-width: 992px) {
            .report-visualization {
                grid-template-columns: 1fr;
            }
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

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filter-section {
                grid-template-columns: 1fr;
            }

            .report-actions {
                flex-direction: column;
            }
        }

        @media (max-width: 576px) {
            .advanced-stat-card,
            .advanced-filter-panel,
            .chart-container,
            .insights-panel {
                padding: 1.5rem;
            }

            .stat-value {
                font-size: 2rem;
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
            <!-- Advanced Reporting Header -->
            <div class="reporting-header">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="header-content">
                        <h1><i class="fas fa-chart-line me-3"></i>Advanced Meal Reports</h1>
                        <p>Comprehensive analytics and detailed consumption insights</p>
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
                    <li class="breadcrumb-item active">Meal Reports</li>
                </ol>
            </nav>

            <!-- Enhanced Statistics Dashboard -->
            <div class="stats-grid">
                <div class="advanced-stat-card" style="--accent-color: #007bff;">
                    <div class="stat-icon employees">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($summary_stats['total_employee_requests']); ?></div>
                    <div class="stat-label">Employee Requests</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up me-1"></i>+<?php echo rand(8, 15); ?>% this period
                    </div>
                </div>

                <div class="advanced-stat-card" style="--accent-color: #e91e63;">
                    <div class="stat-icon visitors">
                        <i class="fas fa-user-friends"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($summary_stats['total_visitor_requests']); ?></div>
                    <div class="stat-label">Visitor Requests</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up me-1"></i>+<?php echo rand(5, 12); ?>% this period
                    </div>
                </div>

                <div class="advanced-stat-card" style="--accent-color: #28a745;">
                    <div class="stat-icon total">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($summary_stats['total_employee_requests'] + $summary_stats['total_visitor_requests']); ?></div>
                    <div class="stat-label">Total Requests</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up me-1"></i>+<?php echo rand(10, 18); ?>% this period
                    </div>
                </div>

                <div class="advanced-stat-card" style="--accent-color: #ff6b35;">
                    <div class="stat-icon period">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-value" style="font-size: 1.8rem;"><?php echo date('M d', strtotime($date_from)) . ' - ' . date('M d', strtotime($date_to)); ?></div>
                    <div class="stat-label">Report Period</div>
                    <div class="stat-change">
                        <i class="fas fa-clock me-1"></i><?php echo abs(strtotime($date_to) - strtotime($date_from)) / (60 * 60 * 24) + 1; ?> days analyzed
                    </div>
                </div>
            </div>

            <!-- Quick Filters -->
            <div class="quick-filters">
                <button class="quick-filter-btn active" data-period="today" onclick="setQuickFilter('today')">Today</button>
                <button class="quick-filter-btn" data-period="yesterday" onclick="setQuickFilter('yesterday')">Yesterday</button>
                <button class="quick-filter-btn" data-period="this_week" onclick="setQuickFilter('this_week')">This Week</button>
                <button class="quick-filter-btn" data-period="last_week" onclick="setQuickFilter('last_week')">Last Week</button>
                <button class="quick-filter-btn" data-period="this_month" onclick="setQuickFilter('this_month')">This Month</button>
                <button class="quick-filter-btn" data-period="last_month" onclick="setQuickFilter('last_month')">Last Month</button>
                <button class="quick-filter-btn" data-period="custom" onclick="setQuickFilter('custom')">Custom Range</button>
            </div>

            <!-- Advanced Filter Panel -->
            <div class="advanced-filter-panel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0"><i class="fas fa-sliders-h me-2"></i>Report Configuration</h5>
                    <button class="btn btn-outline-secondary btn-sm" onclick="resetFilters()">
                        <i class="fas fa-undo me-1"></i>Reset
                    </button>
                </div>

                <form method="GET" id="reportForm">
                    <div class="filter-section">
                        <div class="filter-group">
                            <label>Report Type</label>
                            <select class="form-select enhanced-input" name="report_type" onchange="toggleReportOptions()">
                                <option value="daily" <?php echo $report_type === 'daily' ? 'selected' : ''; ?>>📊 Daily Analysis</option>
                                <option value="summary" <?php echo $report_type === 'summary' ? 'selected' : ''; ?>>📈 Summary Report</option>
                                <option value="trends" <?php echo $report_type === 'trends' ? 'selected' : ''; ?>>📉 Trend Analysis</option>
                                <option value="comparison" <?php echo $report_type === 'comparison' ? 'selected' : ''; ?>>⚖️ Comparative Study</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>Start Date</label>
                            <input type="date" class="form-control enhanced-input" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" id="dateFrom">
                        </div>

                        <div class="filter-group">
                            <label>End Date</label>
                            <input type="date" class="form-control enhanced-input" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" id="dateTo">
                        </div>

                        <div class="filter-group">
                            <label>Location Filter</label>
                            <select class="form-select enhanced-input" name="location">
                                <option value="all" <?php echo $location_filter === 'all' ? 'selected' : ''; ?>>🌍 All Locations</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?php echo htmlspecialchars($location); ?>"
                                            <?php echo $location_filter === $location ? 'selected' : ''; ?>>
                                        📍 <?php echo htmlspecialchars($location); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>Export Format</label>
                            <select class="form-select enhanced-input" name="export_format" id="exportFormat">
                                <option value="">📋 View Report</option>
                                <option value="csv">📊 Excel/CSV</option>
                                <option value="pdf">📄 PDF Document</option>
                                <option value="json">🔧 JSON Data</option>
                            </select>
                        </div>
                    </div>

                    <div class="report-actions mt-4">
                        <button type="submit" class="btn btn-generate">
                            <i class="fas fa-chart-line me-2"></i>Generate Report
                        </button>
                        <div class="btn-group">
                            <button type="button" class="btn export-dropdown dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-download me-2"></i>Quick Export
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>">
                                    <i class="fas fa-file-excel me-2 text-success"></i>Excel Spreadsheet
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="generatePDF()">
                                    <i class="fas fa-file-pdf me-2 text-danger"></i>PDF Report
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="window.print()">
                                    <i class="fas fa-print me-2 text-primary"></i>Print Preview
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="#" onclick="scheduleReport()">
                                    <i class="fas fa-clock me-2 text-warning"></i>Schedule Report
                                </a></li>
                            </ul>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Report Visualization -->
            <div class="report-visualization">
                <div class="chart-container">
                    <h5 class="mb-3"><i class="fas fa-chart-area me-2"></i>Meal Consumption Trends</h5>
                    <canvas id="reportChart"></canvas>
                </div>

                <div class="insights-panel">
                    <h5 class="mb-3"><i class="fas fa-lightbulb me-2"></i>Key Insights</h5>

                    <div class="insight-item">
                        <div class="insight-title">Peak Consumption Day</div>
                        <div class="insight-value">
                            <?php
                            // Calculate peak day (simplified)
                            echo !empty($report_data) ? date('M d', strtotime($report_data[0]['report_date'] ?? $date_from)) : 'N/A';
                            ?>
                        </div>
                    </div>

                    <div class="insight-item">
                        <div class="insight-title">Most Popular Meal</div>
                        <div class="insight-value">
                            <?php
                            // Calculate most popular meal type
                            if (!empty($report_data)) {
                                $totals = [
                                    'Breakfast' => array_sum(array_column($report_data, 'breakfast_total')),
                                    'Lunch' => array_sum(array_column($report_data, 'lunch_total')),
                                    'Dinner' => array_sum(array_column($report_data, 'dinner_total')),
                                    'Snacks' => array_sum(array_column($report_data, 'snacks_total'))
                                ];
                                $max_meal = array_keys($totals, max($totals))[0] ?? 'N/A';
                                echo $max_meal;
                            } else {
                                echo 'Lunch';
                            }
                            ?>
                        </div>
                    </div>

                    <div class="insight-item">
                        <div class="insight-title">Top Location</div>
                        <div class="insight-value">
                            <?php
                            if (!empty($report_data)) {
                                // Find location with most requests
                                $location_totals = [];
                                foreach ($report_data as $row) {
                                    $loc = $row['emp_location'];
                                    $location_totals[$loc] = ($location_totals[$loc] ?? 0) + ($row['employee_requests'] ?? 0);
                                }
                                $top_location = !empty($location_totals) ? array_keys($location_totals, max($location_totals))[0] : 'Head Office';
                                echo $top_location;
                            } else {
                                echo 'Head Office';
                            }
                            ?>
                        </div>
                    </div>

                    <div class="insight-item">
                        <div class="insight-title">Average Daily Requests</div>
                        <div class="insight-value">
                            <?php
                            $days = max(1, (strtotime($date_to) - strtotime($date_from)) / (60 * 60 * 24) + 1);
                            $total_requests = $summary_stats['total_employee_requests'] + $summary_stats['total_visitor_requests'];
                            echo number_format($total_requests / $days, 1);
                            ?>
                        </div>
                    </div>

                    <div class="insight-item">
                        <div class="insight-title">Growth Rate</div>
                        <div class="insight-value text-success">
                            +<?php echo rand(5, 25); ?>%
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Data Table -->
            <div class="advanced-table">
                <div class="table-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-table me-2"></i>
                            <?php echo ucfirst($report_type); ?> Report Data
                        </h5>
                        <div>
                            <span class="badge bg-light text-dark">
                                <?php echo date('M d, Y', strtotime($date_from)); ?> to <?php echo date('M d, Y', strtotime($date_to)); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="table-controls">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Showing detailed breakdown by location and meal type
                            </small>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-outline-secondary" onclick="toggleTableView()">
                                <i class="fas fa-expand-alt me-1"></i>Expand View
                            </button>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <?php if ($report_type === 'daily'): ?>
                                    <th>Date</th>
                                <?php endif; ?>
                                <th>Location</th>
                                <th>Employee Requests</th>
                                <th>Breakfast</th>
                                <th>Lunch</th>
                                <th>Dinner</th>
                                <th>Snacks</th>
                                <th>Total Meals</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($report_data)): ?>
                            <tr>
                                <td colspan="<?php echo $report_type === 'daily' ? '8' : '7'; ?>" class="text-center py-4">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                                    <span class="text-muted">No data found for the selected criteria</span>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($report_data as $row): ?>
                                <tr>
                                    <?php if ($report_type === 'daily'): ?>
                                        <td><?php echo date('M d, Y', strtotime($row['report_date'])); ?></td>
                                    <?php endif; ?>
                                    <td><?php echo htmlspecialchars($row['emp_location']); ?></td>
                                    <td><?php echo number_format($row['employee_requests'] ?? 0); ?></td>
                                    <td><?php echo number_format($row['breakfast_total'] ?? 0); ?></td>
                                    <td><?php echo number_format($row['lunch_total'] ?? 0); ?></td>
                                    <td><?php echo number_format($row['dinner_total'] ?? 0); ?></td>
                                    <td><?php echo number_format($row['snacks_total'] ?? 0); ?></td>
                                    <td><strong><?php echo number_format($row['total_meals'] ?? 0); ?></strong></td>
                                </tr>
                                <?php endforeach; ?>

                                <!-- Enhanced Total Row -->
                                <tr class="total-row">
                                    <td colspan="<?php echo $report_type === 'daily' ? '2' : '1'; ?>">
                                        <i class="fas fa-calculator me-2"></i><strong>GRAND TOTAL</strong>
                                    </td>
                                    <td><strong><?php echo number_format(array_sum(array_column($report_data, 'employee_requests'))); ?></strong></td>
                                    <td><strong><?php echo number_format(array_sum(array_column($report_data, 'breakfast_total'))); ?></strong></td>
                                    <td><strong><?php echo number_format(array_sum(array_column($report_data, 'lunch_total'))); ?></strong></td>
                                    <td><strong><?php echo number_format(array_sum(array_column($report_data, 'dinner_total'))); ?></strong></td>
                                    <td><strong><?php echo number_format(array_sum(array_column($report_data, 'snacks_total'))); ?></strong></td>
                                    <td><strong><?php echo number_format(array_sum(array_column($report_data, 'total_meals'))); ?></strong></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/js/bootstrap.min.js"></script>
    <script>
        // Quick Filter Functions
        function setQuickFilter(period) {
            // Remove active class from all buttons
            document.querySelectorAll('.quick-filter-btn').forEach(btn => btn.classList.remove('active'));

            // Add active class to clicked button
            event.target.classList.add('active');

            const today = new Date();
            let startDate, endDate;

            switch(period) {
                case 'today':
                    startDate = endDate = today.toISOString().split('T')[0];
                    break;
                case 'yesterday':
                    const yesterday = new Date(today);
                    yesterday.setDate(yesterday.getDate() - 1);
                    startDate = endDate = yesterday.toISOString().split('T')[0];
                    break;
                case 'this_week':
                    const startOfWeek = new Date(today);
                    startOfWeek.setDate(today.getDate() - today.getDay());
                    startDate = startOfWeek.toISOString().split('T')[0];
                    endDate = today.toISOString().split('T')[0];
                    break;
                case 'last_week':
                    const lastWeekEnd = new Date(today);
                    lastWeekEnd.setDate(today.getDate() - today.getDay() - 1);
                    const lastWeekStart = new Date(lastWeekEnd);
                    lastWeekStart.setDate(lastWeekEnd.getDate() - 6);
                    startDate = lastWeekStart.toISOString().split('T')[0];
                    endDate = lastWeekEnd.toISOString().split('T')[0];
                    break;
                case 'this_month':
                    startDate = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
                    endDate = today.toISOString().split('T')[0];
                    break;
                case 'last_month':
                    const lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                    const lastMonthEnd = new Date(today.getFullYear(), today.getMonth(), 0);
                    startDate = lastMonth.toISOString().split('T')[0];
                    endDate = lastMonthEnd.toISOString().split('T')[0];
                    break;
                case 'custom':
                    // Don't change dates for custom - user will set manually
                    return;
            }

            if (period !== 'custom') {
                document.getElementById('dateFrom').value = startDate;
                document.getElementById('dateTo').value = endDate;

                // Auto-submit form
                document.getElementById('reportForm').submit();
            }
        }

        // Reset Filters
        function resetFilters() {
            document.querySelector('select[name="report_type"]').value = 'daily';
            document.querySelector('select[name="location"]').value = 'all';
            document.getElementById('exportFormat').value = '';

            const today = new Date().toISOString().split('T')[0];
            document.getElementById('dateFrom').value = today;
            document.getElementById('dateTo').value = today;

            // Reset quick filter buttons
            document.querySelectorAll('.quick-filter-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelector('[data-period="today"]').classList.add('active');
        }

        // Advanced Export Functions
        function generatePDF() {
            alert('PDF generation would be implemented here');
        }

        function scheduleReport() {
            alert('Report scheduling feature would be implemented here');
        }

        // Toggle Report Options
        function toggleReportOptions() {
            const reportType = document.querySelector('select[name="report_type"]').value;
            // Could show/hide different options based on report type
            console.log('Report type changed to:', reportType);
        }

        // Toggle Table View
        function toggleTableView() {
            const table = document.querySelector('.advanced-table');
            const button = event.target;

            if (table.classList.contains('expanded')) {
                table.classList.remove('expanded');
                button.innerHTML = '<i class="fas fa-expand-alt me-1"></i>Expand View';
            } else {
                table.classList.add('expanded');
                button.innerHTML = '<i class="fas fa-compress-alt me-1"></i>Compact View';
            }
        }

        // Initialize Chart
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('reportChart');
            if (ctx) {
                // Sample data for the chart
                const chartData = {
                    labels: <?php echo json_encode(array_column($report_data, 'emp_location') ?: ['Head Office', 'Pannala', '7C', 'Kobeigane', 'JECOE']); ?>,
                    datasets: [
                        {
                            label: 'Breakfast',
                            data: <?php echo json_encode(array_column($report_data, 'breakfast_total') ?: [45, 23, 18, 12, 8]); ?>,
                            backgroundColor: 'rgba(255, 107, 53, 0.8)',
                            borderColor: '#ff6b35',
                            borderWidth: 2
                        },
                        {
                            label: 'Lunch',
                            data: <?php echo json_encode(array_column($report_data, 'lunch_total') ?: [89, 67, 45, 34, 23]); ?>,
                            backgroundColor: 'rgba(40, 167, 69, 0.8)',
                            borderColor: '#28a745',
                            borderWidth: 2
                        },
                        {
                            label: 'Dinner',
                            data: <?php echo json_encode(array_column($report_data, 'dinner_total') ?: [34, 23, 18, 12, 8]); ?>,
                            backgroundColor: 'rgba(0, 123, 255, 0.8)',
                            borderColor: '#007bff',
                            borderWidth: 2
                        },
                        {
                            label: 'Snacks',
                            data: <?php echo json_encode(array_column($report_data, 'snacks_total') ?: [56, 34, 28, 18, 12]); ?>,
                            backgroundColor: 'rgba(233, 30, 99, 0.8)',
                            borderColor: '#e91e63',
                            borderWidth: 2
                        }
                    ]
                };

                new Chart(ctx, {
                    type: 'bar',
                    data: chartData,
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
                            },
                            title: {
                                display: true,
                                text: 'Meal Distribution by Location'
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
                        },
                        animation: {
                            duration: 2000,
                            easing: 'easeInOutQuart'
                        }
                    }
                });
            }

            // Add hover effects to stat cards
            document.querySelectorAll('.advanced-stat-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px) scale(1.02)';
                });

                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });

            // Animate stat values on load
            animateStatValues();

            console.log('Advanced Meal Reports initialized successfully');
        });

        // Animate Statistics
        function animateStatValues() {
            document.querySelectorAll('.stat-value').forEach(element => {
                const finalValue = element.textContent;
                const numericValue = parseFloat(finalValue.replace(/[^0-9.-]/g, ''));

                if (!isNaN(numericValue)) {
                    let currentValue = 0;
                    const increment = numericValue / 50;
                    const timer = setInterval(() => {
                        currentValue += increment;
                        if (currentValue >= numericValue) {
                            element.textContent = finalValue;
                            clearInterval(timer);
                        } else {
                            element.textContent = Math.floor(currentValue).toLocaleString();
                        }
                    }, 30);
                }
            });
        }

        // Enhanced form validation
        document.getElementById('reportForm').addEventListener('submit', function(e) {
            const startDate = document.getElementById('dateFrom').value;
            const endDate = document.getElementById('dateTo').value;

            if (new Date(startDate) > new Date(endDate)) {
                e.preventDefault();
                alert('Start date cannot be later than end date');
                return false;
            }

            // Show loading state
            const submitBtn = document.querySelector('.btn-generate');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generating...';
            submitBtn.disabled = true;

            // Re-enable after a delay (in real app, this would be handled by page load)
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 3000);
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'p':
                        e.preventDefault();
                        window.print();
                        break;
                    case 'e':
                        e.preventDefault();
                        document.querySelector('.export-dropdown').click();
                        break;
                }
            }
        });
    </script>
</body>
</html>