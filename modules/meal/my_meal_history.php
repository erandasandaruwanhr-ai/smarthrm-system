<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

// Check if user has permission to access meal history
$db = new Database();
if (!hasModulePermission($db, 'meal.my_meal_history') && !hasModulePermission($db, 'meal.employee_meal_request') && !hasAdminAccess()) {
    header('Location: ../../dashboard.php');
    exit();
}

$user = getCurrentUser();

// Get filter parameters - check for "show_all" parameter
$show_all = isset($_GET['show_all']);
if ($show_all) {
    $date_from = '2020-01-01'; // Very early date to show all records
    $date_to = date('Y-m-d', strtotime('+1 year')); // Future date to ensure we get everything
} else {
    $date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-60 days')); // Extended to 60 days to capture more history
    $date_to = $_GET['date_to'] ?? date('Y-m-d', strtotime('+7 days')); // Include future dates up to 1 week
}
$meal_type_filter = $_GET['meal_type'] ?? '';

// Debug information (remove in production)
$debug_info = "";
if (isset($_GET['debug'])) {
    $debug_info = "<div class='alert alert-info'>";
    $debug_info .= "<h5>Debug Info:</h5>";
    $debug_info .= "<p><strong>User EPF:</strong> " . htmlspecialchars($user['epf_number'] ?? 'NOT SET') . "</p>";
    $debug_info .= "<p><strong>Show All Mode:</strong> " . ($show_all ? 'YES' : 'NO') . "</p>";
    $debug_info .= "<p><strong>Date From:</strong> " . htmlspecialchars($date_from) . "</p>";
    $debug_info .= "<p><strong>Date To:</strong> " . htmlspecialchars($date_to) . "</p>";
    $debug_info .= "<p><strong>Meal Type Filter:</strong> " . htmlspecialchars($meal_type_filter ?: 'None') . "</p>";
    $debug_info .= "<p><strong>Current Date:</strong> " . date('Y-m-d H:i:s') . "</p>";
    $debug_info .= "<p><strong>Max Allowed Date:</strong> " . date('Y-m-d', strtotime('+7 days')) . " (1 week ahead)</p>";
    $debug_info .= "</div>";
}

// Get user's meal requests
try {
    $query = "SELECT * FROM meal_requests_employee
              WHERE emp_number = ?
              AND request_date BETWEEN ? AND ?";

    $params = [$user['epf_number'] ?? '', $date_from, $date_to];

    if ($meal_type_filter) {
        $query .= " AND meal_type = ?";
        $params[] = $meal_type_filter;
    }

    $query .= " ORDER BY request_date DESC, created_at DESC";

    $meal_requests = $db->fetchAll($query, $params);

    if (isset($_GET['debug'])) {
        $debug_info .= "<div class='alert alert-warning'>";
        $debug_info .= "<p><strong>Query:</strong> " . htmlspecialchars($query) . "</p>";
        $debug_info .= "<p><strong>Parameters:</strong> " . htmlspecialchars(implode(', ', $params)) . "</p>";
        $debug_info .= "<p><strong>Results Found:</strong> " . count($meal_requests) . "</p>";
        $debug_info .= "</div>";
    }

    // Get statistics
    $total_requests = count($meal_requests);
    $total_meals = 0;
    $total_breakfast = 0;
    $total_lunch = 0;
    $total_dinner = 0;
    $total_snacks = 0;

    foreach ($meal_requests as $request) {
        $total_breakfast += ($request['breakfast_count'] + $request['breakfast_countx']);
        $total_lunch += ($request['lunch_count'] + $request['lunch_countx']);
        $total_dinner += ($request['dinner_count'] + $request['dinner_countx']);
        $total_snacks += ($request['snack1_countx'] + $request['snack2_count'] + $request['snack2_countx'] + $request['snack3_countx']);

        $total_meals += ($request['breakfast_count'] + $request['breakfast_countx'] +
                        $request['lunch_count'] + $request['lunch_countx'] +
                        $request['dinner_count'] + $request['dinner_countx'] +
                        $request['snack1_countx'] + $request['snack2_count'] +
                        $request['snack2_countx'] + $request['snack3_countx']);
    }

} catch (Exception $e) {
    $meal_requests = [];
    $total_requests = 0;
    $total_meals = 0;
    $total_breakfast = 0;
    $total_lunch = 0;
    $total_dinner = 0;
    $total_snacks = 0;
}

// Get available meal types for filter
try {
    $meal_types_query = "SELECT DISTINCT meal_type FROM meal_requests_employee
                        WHERE emp_number = ? AND meal_type IS NOT NULL AND meal_type != ''";
    $meal_types = $db->fetchAll($meal_types_query, [$user['epf_number']]);
} catch (Exception $e) {
    $meal_types = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - My Meal History</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/fontawesome.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .meal-card {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            transition: all 0.3s ease;
            margin-bottom: 15px;
        }

        .meal-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .meal-type-badge {
            font-size: 0.8rem;
            padding: 4px 8px;
        }

        .meal-count {
            background: #f8f9fa;
            border-radius: 20px;
            padding: 5px 10px;
            font-size: 0.9rem;
            margin: 2px;
        }

        .meal-count.active {
            background: #007bff;
            color: white;
        }

        .stats-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .filter-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .no-data {
            text-align: center;
            padding: 50px 20px;
            color: #6c757d;
        }

        .date-badge {
            background: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.85rem;
        }

        .meal-details {
            background: #ffffff;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
        }

        .meal-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .meal-item:last-child {
            border-bottom: none;
        }

        /* Enhanced Meal History Styles */
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: none;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
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

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .breakfast-theme { background: linear-gradient(135deg, #ff9a56, #ffb347); }
        .lunch-theme { background: linear-gradient(135deg, #26d0ce, #1abc9c); }
        .dinner-theme { background: linear-gradient(135deg, #667eea, #764ba2); }
        .snack-theme { background: linear-gradient(135deg, #f093fb, #f5576c); }
        .total-theme { background: linear-gradient(135deg, #4facfe, #00f2fe); }
        .request-theme { background: linear-gradient(135deg, #43e97b, #38f9d7); }

        .filter-panel {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }

        .view-toggle {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 0.5rem;
            display: inline-flex;
            margin-bottom: 1.5rem;
        }

        .view-btn {
            background: transparent;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            color: #6c757d;
            font-weight: 500;
        }

        .view-btn.active {
            background: var(--primary-color);
            color: white;
            box-shadow: 0 2px 8px rgba(0, 123, 255, 0.3);
        }

        .timeline-view {
            position: relative;
        }

        .timeline-item {
            position: relative;
            padding: 1.5rem;
            background: white;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin-left: 2rem;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -2rem;
            top: 50%;
            transform: translateY(-50%);
            width: 12px;
            height: 12px;
            background: var(--primary-color);
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.2);
        }

        .timeline-line {
            position: absolute;
            left: -1.44rem;
            top: 0;
            bottom: -2rem;
            width: 2px;
            background: linear-gradient(180deg, var(--primary-color), rgba(0, 123, 255, 0.2));
        }

        .timeline-item:last-child .timeline-line {
            display: none;
        }

        .meal-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .meal-history-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: none;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .meal-history-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .meal-date-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 1rem;
            margin: -1.5rem -1.5rem 1.5rem -1.5rem;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .meal-type-indicator {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .meal-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .meal-summary-item {
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .meal-summary-item:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }

        .meal-icon-wrapper {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            font-size: 1.2rem;
        }

        .meal-count-display {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2c3e50;
        }

        .meal-name {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .empty-state-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
        }

        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }

        .quick-stats {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .quick-stat {
            flex: 1;
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 12px;
            text-align: center;
        }

        .search-box {
            position: relative;
            margin-bottom: 1rem;
        }

        .search-box input {
            padding-left: 3rem;
            border-radius: 25px;
            border: 1px solid #e9ecef;
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        .date-range-picker {
            display: flex;
            gap: 1rem;
            align-items: center;
            margin-bottom: 1rem;
        }

        .preset-dates {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        .preset-btn {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .preset-btn:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
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

            .meal-grid {
                grid-template-columns: 1fr;
            }

            .timeline-item {
                margin-left: 1rem;
            }

            .date-range-picker {
                flex-direction: column;
                align-items: stretch;
            }

            .quick-stats {
                flex-direction: column;
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
                        <h1><i class="fas fa-history me-3"></i>My Meal History</h1>
                        <p>View your meal request history and status updates</p>
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
                    <li class="breadcrumb-item active">My Meal History</li>
                </ol>
            </nav>

        <!-- Debug Information -->
        <?php if (!empty($debug_info)): ?>
            <?php echo $debug_info; ?>
        <?php endif; ?>

        <!-- Show All Notice -->
        <?php if ($show_all): ?>
        <div class="alert alert-success">
            <i class="fas fa-eye me-2"></i>
            <strong>Showing All Records:</strong> Displaying all your meal requests from all dates.
        </div>
        <?php endif; ?>

        <!-- Statistics Dashboard -->
        <div class="row mb-4">
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stat-icon request-theme">
                        <i class="fas fa-file-alt text-white"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($total_requests); ?></div>
                    <div class="stat-label">Total Requests</div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stat-icon total-theme">
                        <i class="fas fa-utensils text-white"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($total_meals); ?></div>
                    <div class="stat-label">Total Meals</div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stat-icon breakfast-theme">
                        <i class="fas fa-coffee text-white"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($total_breakfast); ?></div>
                    <div class="stat-label">Breakfast Meals</div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stat-icon lunch-theme">
                        <i class="fas fa-bowl-food text-white"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($total_lunch); ?></div>
                    <div class="stat-label">Lunch Meals</div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stat-icon dinner-theme">
                        <i class="fas fa-moon text-white"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($total_dinner); ?></div>
                    <div class="stat-label">Dinner Meals</div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stat-icon snack-theme">
                        <i class="fas fa-cookie text-white"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($total_snacks); ?></div>
                    <div class="stat-label">Snacks & Extras</div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <?php if (!empty($meal_requests)): ?>
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="chart-container">
                    <h6 class="mb-3"><i class="fas fa-chart-line me-2"></i>Meal Trends</h6>
                    <canvas id="mealTrendsChart" height="100"></canvas>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-container">
                    <h6 class="mb-3"><i class="fas fa-chart-pie me-2"></i>Meal Distribution</h6>
                    <canvas id="mealDistributionChart"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Enhanced Filter Panel -->
        <div class="filter-panel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0"><i class="fas fa-filter me-2"></i>Filter & Search</h6>
                <div class="view-toggle">
                    <button type="button" class="view-btn active" onclick="setView('grid')" id="gridViewBtn">
                        <i class="fas fa-th-large me-2"></i>Grid
                    </button>
                    <button type="button" class="view-btn" onclick="setView('timeline')" id="timelineViewBtn">
                        <i class="fas fa-timeline me-2"></i>Timeline
                    </button>
                    <button type="button" class="view-btn" onclick="setView('list')" id="listViewBtn">
                        <i class="fas fa-list me-2"></i>List
                    </button>
                </div>
            </div>

            <!-- Quick Date Presets -->
            <div class="preset-dates">
                <button type="button" class="preset-btn" onclick="setDateRange('today')">Today</button>
                <button type="button" class="preset-btn" onclick="setDateRange('week')">This Week</button>
                <button type="button" class="preset-btn" onclick="setDateRange('month')">This Month</button>
                <button type="button" class="preset-btn" onclick="setDateRange('last7')">Last 7 Days</button>
                <button type="button" class="preset-btn" onclick="setDateRange('last30')">Last 30 Days</button>
                <button type="button" class="preset-btn" onclick="setDateRange('all')" <?php echo $show_all ? 'style="background: var(--primary-color); color: white;"' : ''; ?>>All Time</button>
            </div>

            <form method="GET" id="filterForm">
                <div class="row g-3">
                    <div class="col-lg-3 col-md-6">
                        <label for="date_from" class="form-label">From Date</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label for="date_to" class="form-label">To Date</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label for="meal_type" class="form-label">Meal Type</label>
                        <select class="form-select" id="meal_type" name="meal_type">
                            <option value="">All Types</option>
                            <?php foreach ($meal_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type['meal_type']); ?>"
                                        <?php echo ($meal_type_filter === $type['meal_type']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type['meal_type']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search me-2"></i>Apply
                        </button>
                        <a href="my_meal_history.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Clear
                        </a>
                    </div>
                </div>

                <!-- Live Search -->
                <div class="search-box mt-3">
                    <i class="fas fa-search"></i>
                    <input type="text" class="form-control" id="liveSearch" placeholder="Search by date, meal type, or location..." onkeyup="filterResults()">
                </div>
            </form>
        </div>

        <!-- Meal Requests Display -->
        <div id="mealRequestsContainer">
            <?php if (empty($meal_requests)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <h4 class="mb-3">No meal requests found</h4>
                    <p class="text-muted mb-4">You haven't made any meal requests in the selected date range.</p>
                    <a href="employee_meal_request.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-plus me-2"></i>Make Your First Request
                    </a>
                </div>
            <?php else: ?>

                <!-- Grid View -->
                <div id="gridView" class="meal-grid">
                    <?php foreach ($meal_requests as $request): ?>
                        <div class="meal-history-card" data-date="<?php echo $request['request_date']; ?>" data-type="<?php echo htmlspecialchars($request['meal_type']); ?>" data-location="<?php echo htmlspecialchars($request['emp_location']); ?>">
                            <div class="meal-date-header">
                                <div>
                                    <h6 class="mb-0"><?php echo date('D, M d', strtotime($request['request_date'])); ?></h6>
                                    <small class="opacity-75"><?php echo date('Y', strtotime($request['request_date'])); ?></small>
                                </div>
                                <div class="meal-type-indicator">
                                    <?php echo htmlspecialchars($request['meal_type'] ?? 'Employee'); ?>
                                </div>
                            </div>

                            <div class="meal-summary-grid">
                                <?php
                                $meals = [
                                    ['name' => 'Breakfast', 'icon' => 'fa-coffee', 'theme' => 'breakfast-theme', 'count' => ($request['breakfast_count'] + $request['breakfast_countx'])],
                                    ['name' => 'Lunch', 'icon' => 'fa-bowl-food', 'theme' => 'lunch-theme', 'count' => ($request['lunch_count'] + $request['lunch_countx'])],
                                    ['name' => 'Dinner', 'icon' => 'fa-moon', 'theme' => 'dinner-theme', 'count' => ($request['dinner_count'] + $request['dinner_countx'])],
                                    ['name' => 'Snacks', 'icon' => 'fa-cookie', 'theme' => 'snack-theme', 'count' => ($request['snack1_countx'] + $request['snack2_count'] + $request['snack2_countx'] + $request['snack3_countx'])]
                                ];

                                foreach ($meals as $meal):
                                    if ($meal['count'] > 0): ?>
                                <div class="meal-summary-item">
                                    <div class="meal-icon-wrapper <?php echo $meal['theme']; ?>">
                                        <i class="fas <?php echo $meal['icon']; ?> text-white"></i>
                                    </div>
                                    <div class="meal-count-display"><?php echo $meal['count']; ?></div>
                                    <div class="meal-name"><?php echo $meal['name']; ?></div>
                                </div>
                                <?php endif; endforeach; ?>
                            </div>

                            <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                                <div class="text-muted small">
                                    <i class="fas fa-calendar me-1"></i>
                                    Requested: <?php echo date('M j, g:i A', strtotime($request['created_at'])); ?>
                                </div>
                                <div class="text-muted small">
                                    <i class="fas fa-map-marker-alt me-1"></i>
                                    <?php echo htmlspecialchars($request['emp_location']); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Timeline View -->
                <div id="timelineView" class="timeline-view" style="display: none;">
                    <?php foreach ($meal_requests as $index => $request): ?>
                        <div class="timeline-item" data-date="<?php echo $request['request_date']; ?>" data-type="<?php echo htmlspecialchars($request['meal_type']); ?>" data-location="<?php echo htmlspecialchars($request['emp_location']); ?>">
                            <?php if ($index < count($meal_requests) - 1): ?>
                                <div class="timeline-line"></div>
                            <?php endif; ?>

                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h6 class="mb-1"><?php echo date('l, F j, Y', strtotime($request['request_date'])); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($request['meal_type'] ?? 'Employee Meal'); ?></small>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted d-block">Requested</small>
                                    <small class="fw-bold"><?php echo date('g:i A', strtotime($request['created_at'])); ?></small>
                                </div>
                            </div>

                            <div class="row">
                                <?php
                                $meals = [
                                    ['name' => 'Breakfast', 'icon' => 'fa-coffee', 'color' => 'warning', 'count' => ($request['breakfast_count'] + $request['breakfast_countx'])],
                                    ['name' => 'Lunch', 'icon' => 'fa-bowl-food', 'color' => 'success', 'count' => ($request['lunch_count'] + $request['lunch_countx'])],
                                    ['name' => 'Dinner', 'icon' => 'fa-moon', 'color' => 'info', 'count' => ($request['dinner_count'] + $request['dinner_countx'])],
                                    ['name' => 'Snacks', 'icon' => 'fa-cookie', 'color' => 'secondary', 'count' => ($request['snack1_countx'] + $request['snack2_count'] + $request['snack2_countx'] + $request['snack3_countx'])]
                                ];

                                foreach ($meals as $meal):
                                    if ($meal['count'] > 0): ?>
                                <div class="col-md-3 col-6 mb-2">
                                    <div class="d-flex align-items-center p-2 bg-light rounded">
                                        <i class="fas <?php echo $meal['icon']; ?> text-<?php echo $meal['color']; ?> me-2"></i>
                                        <span class="fw-medium"><?php echo $meal['name']; ?></span>
                                        <span class="badge bg-<?php echo $meal['color']; ?> ms-auto"><?php echo $meal['count']; ?></span>
                                    </div>
                                </div>
                                <?php endif; endforeach; ?>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                                <small class="text-muted">
                                    <i class="fas fa-map-marker-alt me-1"></i>
                                    <?php echo htmlspecialchars($request['emp_location']); ?>
                                </small>
                                <?php
                                $total_for_day = ($request['breakfast_count'] + $request['breakfast_countx'] +
                                               $request['lunch_count'] + $request['lunch_countx'] +
                                               $request['dinner_count'] + $request['dinner_countx'] +
                                               $request['snack1_countx'] + $request['snack2_count'] +
                                               $request['snack2_countx'] + $request['snack3_countx']);
                                ?>
                                <span class="badge bg-primary"><?php echo $total_for_day; ?> meals</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- List View -->
                <div id="listView" style="display: none;">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Breakfast</th>
                                    <th>Lunch</th>
                                    <th>Dinner</th>
                                    <th>Snacks</th>
                                    <th>Total</th>
                                    <th>Location</th>
                                    <th>Requested</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($meal_requests as $request): ?>
                                <tr data-date="<?php echo $request['request_date']; ?>" data-type="<?php echo htmlspecialchars($request['meal_type']); ?>" data-location="<?php echo htmlspecialchars($request['emp_location']); ?>">
                                    <td>
                                        <strong><?php echo date('M j', strtotime($request['request_date'])); ?></strong><br>
                                        <small class="text-muted"><?php echo date('Y', strtotime($request['request_date'])); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($request['meal_type'] ?? 'Employee'); ?></span>
                                    </td>
                                    <td><?php echo ($request['breakfast_count'] + $request['breakfast_countx']) ?: '-'; ?></td>
                                    <td><?php echo ($request['lunch_count'] + $request['lunch_countx']) ?: '-'; ?></td>
                                    <td><?php echo ($request['dinner_count'] + $request['dinner_countx']) ?: '-'; ?></td>
                                    <td><?php echo ($request['snack1_countx'] + $request['snack2_count'] + $request['snack2_countx'] + $request['snack3_countx']) ?: '-'; ?></td>
                                    <td>
                                        <?php
                                        $total = ($request['breakfast_count'] + $request['breakfast_countx'] +
                                               $request['lunch_count'] + $request['lunch_countx'] +
                                               $request['dinner_count'] + $request['dinner_countx'] +
                                               $request['snack1_countx'] + $request['snack2_count'] +
                                               $request['snack2_countx'] + $request['snack3_countx']);
                                        ?>
                                        <span class="badge bg-primary"><?php echo $total; ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($request['emp_location']); ?></td>
                                    <td>
                                        <small><?php echo date('M j, g:i A', strtotime($request['created_at'])); ?></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Summary Footer -->
                <div class="text-center mt-4">
                    <div class="bg-light rounded p-3">
                        <small class="text-muted">
                            Showing <strong><?php echo count($meal_requests); ?></strong> request(s) from
                            <strong><?php echo date('M d, Y', strtotime($date_from)); ?></strong> to
                            <strong><?php echo date('M d, Y', strtotime($date_to)); ?></strong>
                            <?php if (!$show_all): ?>
                            <br><em>Note: Includes future requests up to 1 week ahead</em>
                            <?php endif; ?>
                        </small>
                    </div>
                </div>

            <?php endif; ?>
        </div>
    </div>

    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        let mealTrendsChart = null;
        let mealDistributionChart = null;

        // View Management
        function setView(viewType) {
            // Update button states
            document.querySelectorAll('.view-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById(viewType + 'ViewBtn').classList.add('active');

            // Show/hide views
            const views = ['gridView', 'timelineView', 'listView'];
            views.forEach(view => {
                const element = document.getElementById(view);
                if (element) {
                    element.style.display = view === viewType + 'View' ? 'block' : 'none';
                }
            });

            // Store preference
            localStorage.setItem('mealHistoryView', viewType);
        }

        // Date Range Presets
        function setDateRange(preset) {
            const today = new Date();
            const dateFrom = document.getElementById('date_from');
            const dateTo = document.getElementById('date_to');

            let fromDate, toDate;

            switch(preset) {
                case 'today':
                    fromDate = toDate = today;
                    break;
                case 'week':
                    fromDate = new Date(today.getFullYear(), today.getMonth(), today.getDate() - today.getDay());
                    toDate = new Date(fromDate.getTime() + 6 * 24 * 60 * 60 * 1000);
                    break;
                case 'month':
                    fromDate = new Date(today.getFullYear(), today.getMonth(), 1);
                    toDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                    break;
                case 'last7':
                    fromDate = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
                    toDate = today;
                    break;
                case 'last30':
                    fromDate = new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000);
                    toDate = today;
                    break;
                case 'all':
                    window.location.href = 'my_meal_history.php?show_all=1';
                    return;
            }

            if (fromDate && toDate) {
                dateFrom.value = fromDate.toISOString().split('T')[0];
                dateTo.value = toDate.toISOString().split('T')[0];

                // Auto-submit form
                document.getElementById('filterForm').submit();
            }
        }

        // Live Search Filtering
        function filterResults() {
            const searchTerm = document.getElementById('liveSearch').value.toLowerCase();
            const cards = document.querySelectorAll('.meal-history-card, .timeline-item, tr[data-date]');

            cards.forEach(card => {
                const date = card.getAttribute('data-date');
                const type = card.getAttribute('data-type');
                const location = card.getAttribute('data-location');

                const searchText = `${date} ${type} ${location}`.toLowerCase();
                const matches = searchText.includes(searchTerm);

                if (card.tagName === 'TR') {
                    card.style.display = matches ? '' : 'none';
                } else {
                    card.style.display = matches ? 'block' : 'none';
                }
            });

            updateVisibleCount();
        }

        function updateVisibleCount() {
            const currentView = document.querySelector('.view-btn.active').textContent.trim().split(' ')[0].toLowerCase();
            let visibleCount = 0;

            if (currentView === 'grid') {
                visibleCount = document.querySelectorAll('.meal-history-card[style=""], .meal-history-card:not([style])').length;
            } else if (currentView === 'timeline') {
                visibleCount = document.querySelectorAll('.timeline-item[style=""], .timeline-item:not([style])').length;
            } else {
                visibleCount = document.querySelectorAll('tr[data-date][style=""], tr[data-date]:not([style])').length;
            }

            // Update summary if exists
            const summaryText = document.querySelector('.text-center .bg-light small');
            if (summaryText && document.getElementById('liveSearch').value) {
                summaryText.innerHTML = `<strong>${visibleCount}</strong> matching results found`;
            }
        }

        // Initialize Charts
        function initializeCharts() {
            <?php if (!empty($meal_requests)): ?>
            // Prepare data for charts
            const mealData = <?php echo json_encode($meal_requests); ?>;

            // Meal Distribution Chart
            const distributionCtx = document.getElementById('mealDistributionChart');
            if (distributionCtx) {
                const breakfast = <?php echo $total_breakfast; ?>;
                const lunch = <?php echo $total_lunch; ?>;
                const dinner = <?php echo $total_dinner; ?>;
                const snacks = <?php echo $total_snacks; ?>;

                mealDistributionChart = new Chart(distributionCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Breakfast', 'Lunch', 'Dinner', 'Snacks'],
                        datasets: [{
                            data: [breakfast, lunch, dinner, snacks],
                            backgroundColor: [
                                'rgba(255, 154, 86, 0.8)',
                                'rgba(38, 208, 206, 0.8)',
                                'rgba(102, 126, 234, 0.8)',
                                'rgba(240, 147, 251, 0.8)'
                            ],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 20,
                                    usePointStyle: true
                                }
                            }
                        }
                    }
                });
            }

            // Meal Trends Chart
            const trendsCtx = document.getElementById('mealTrendsChart');
            if (trendsCtx) {
                // Group data by date
                const dateGroups = {};
                mealData.forEach(request => {
                    const date = request.request_date;
                    if (!dateGroups[date]) {
                        dateGroups[date] = {
                            breakfast: 0,
                            lunch: 0,
                            dinner: 0,
                            snacks: 0
                        };
                    }
                    dateGroups[date].breakfast += parseInt(request.breakfast_count) + parseInt(request.breakfast_countx);
                    dateGroups[date].lunch += parseInt(request.lunch_count) + parseInt(request.lunch_countx);
                    dateGroups[date].dinner += parseInt(request.dinner_count) + parseInt(request.dinner_countx);
                    dateGroups[date].snacks += parseInt(request.snack1_countx) + parseInt(request.snack2_count) +
                                            parseInt(request.snack2_countx) + parseInt(request.snack3_countx);
                });

                const sortedDates = Object.keys(dateGroups).sort();

                mealTrendsChart = new Chart(trendsCtx, {
                    type: 'line',
                    data: {
                        labels: sortedDates.map(date => new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })),
                        datasets: [
                            {
                                label: 'Breakfast',
                                data: sortedDates.map(date => dateGroups[date].breakfast),
                                borderColor: 'rgba(255, 154, 86, 1)',
                                backgroundColor: 'rgba(255, 154, 86, 0.1)',
                                tension: 0.4
                            },
                            {
                                label: 'Lunch',
                                data: sortedDates.map(date => dateGroups[date].lunch),
                                borderColor: 'rgba(38, 208, 206, 1)',
                                backgroundColor: 'rgba(38, 208, 206, 0.1)',
                                tension: 0.4
                            },
                            {
                                label: 'Dinner',
                                data: sortedDates.map(date => dateGroups[date].dinner),
                                borderColor: 'rgba(102, 126, 234, 1)',
                                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                                tension: 0.4
                            },
                            {
                                label: 'Snacks',
                                data: sortedDates.map(date => dateGroups[date].snacks),
                                borderColor: 'rgba(240, 147, 251, 1)',
                                backgroundColor: 'rgba(240, 147, 251, 0.1)',
                                tension: 0.4
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        },
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            title: {
                                display: false
                            }
                        }
                    }
                });
            }
            <?php endif; ?>
        }

        // Initialize everything when DOM loads
        document.addEventListener('DOMContentLoaded', function() {
            // Set date constraints
            const dateFrom = document.getElementById('date_from');
            const dateTo = document.getElementById('date_to');

            const today = new Date();
            const oneWeekAhead = new Date(today.getTime() + (7 * 24 * 60 * 60 * 1000));
            const maxDate = oneWeekAhead.toISOString().split('T')[0];
            dateTo.max = maxDate;

            // Date change handlers
            dateFrom.addEventListener('change', function() {
                dateTo.min = this.value;
            });

            dateTo.addEventListener('change', function() {
                dateFrom.max = this.value;
            });

            // Initialize charts
            initializeCharts();

            // Restore view preference
            const savedView = localStorage.getItem('mealHistoryView');
            if (savedView && ['grid', 'timeline', 'list'].includes(savedView)) {
                setView(savedView);
            }

            // Add smooth hover animations
            document.querySelectorAll('.stats-card, .meal-history-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });

                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>