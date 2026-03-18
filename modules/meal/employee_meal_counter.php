<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

// Check if user has permission to access employee meal counter
$db = new Database();
if (!hasModulePermission($db, 'meal.employee_meal_counter') && !hasAdminAccess()) {
    header('Location: ../../dashboard.php');
    exit();
}

$user = getCurrentUser();

// Get selected date (default to today)
$selected_date = $_GET['date'] ?? date('Y-m-d');

// Get user location and account type for access control
$account_type = $user['account_type'] ?? 'user';
$user_location = $user['location'] ?? $user['location_name'] ?? null;

// Available locations (from plan 1.7.2)
$locations = ['7C', 'Pannala', 'Kobeigane', 'JECOE', 'Head Office'];

// Apply hierarchical location filtering
if ($account_type === 'admin' && $user_location) {
    // Admins can only see their own location data
    $location_filter = $user_location;
    $locations = [$user_location]; // Only show admin's location in filter
} elseif ($account_type === 'superadmin') {
    // SuperAdmins can see all locations
    $location_filter = $_GET['location'] ?? 'all';
} else {
    // Other users shouldn't have access, but if they do, restrict to their location
    $location_filter = $user_location ?? 'all';
}

// Build location filter condition
$location_condition = '';
$params = [$selected_date];
if ($location_filter !== 'all') {
    $location_condition = ' AND emp_location = ?';
    $params[] = $location_filter;
}

// Get employee meal counter data
try {
    // Breakfast totals
    $breakfast_query = "SELECT
        emp_location,
        SUM(breakfast_count) as count_total,
        SUM(breakfast_countx) as countx_total,
        (SUM(breakfast_count) + SUM(breakfast_countx)) as grand_total
        FROM meal_requests_employee
        WHERE DATE(request_date) = ? AND breakfast_enabled = 1 $location_condition
        GROUP BY emp_location";

    $breakfast_data = $db->fetchAll($breakfast_query, $params);

    // Lunch totals
    $lunch_query = "SELECT
        emp_location,
        SUM(lunch_count) as count_total,
        SUM(lunch_countx) as countx_total,
        (SUM(lunch_count) + SUM(lunch_countx)) as grand_total
        FROM meal_requests_employee
        WHERE DATE(request_date) = ? AND lunch_enabled = 1 $location_condition
        GROUP BY emp_location";

    $lunch_data = $db->fetchAll($lunch_query, $params);

    // Snack 2 totals
    $snack2_query = "SELECT
        emp_location,
        SUM(snack2_count) as count_total,
        SUM(snack2_countx) as countx_total,
        (SUM(snack2_count) + SUM(snack2_countx)) as grand_total
        FROM meal_requests_employee
        WHERE DATE(request_date) = ? AND snack2_enabled = 1 $location_condition
        GROUP BY emp_location";

    $snack2_data = $db->fetchAll($snack2_query, $params);

    // Dinner totals
    $dinner_query = "SELECT
        emp_location,
        SUM(dinner_count) as count_total,
        SUM(dinner_countx) as countx_total,
        (SUM(dinner_count) + SUM(dinner_countx)) as grand_total
        FROM meal_requests_employee
        WHERE DATE(request_date) = ? AND dinner_enabled = 1 $location_condition
        GROUP BY emp_location";

    $dinner_data = $db->fetchAll($dinner_query, $params);

    // Snack 3 totals
    $snack3_query = "SELECT
        emp_location,
        SUM(snack3_countx) as total
        FROM meal_requests_employee
        WHERE DATE(request_date) = ? AND snack3_countx > 0 $location_condition
        GROUP BY emp_location";

    $snack3_data = $db->fetchAll($snack3_query, $params);

    // Snack 1 totals
    $snack1_query = "SELECT
        emp_location,
        SUM(snack1_countx) as total
        FROM meal_requests_employee
        WHERE DATE(request_date) = ? AND snack1_countx > 0 $location_condition
        GROUP BY emp_location";

    $snack1_data = $db->fetchAll($snack1_query, $params);

} catch (Exception $e) {
    $breakfast_data = [];
    $lunch_data = [];
    $snack2_data = [];
    $dinner_data = [];
    $snack3_data = [];
    $snack1_data = [];
}

// Helper function to get data for location
function getLocationData($data, $location) {
    foreach ($data as $row) {
        if ($row['emp_location'] === $location) {
            return $row;
        }
    }
    return null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Employee Meal Counter</title>
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

        /* Enhanced Counter Dashboard Styles */
        .counter-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: none;
            margin-bottom: 2rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .counter-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .counter-header {
            padding: 1.5rem;
            position: relative;
            color: white;
        }

        .counter-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 80%;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transform: translateX(-50%);
        }

        .counter-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            margin-right: 1rem;
        }

        .counter-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .counter-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .breakfast-theme { background: linear-gradient(135deg, #ff9a56, #ffb347); }
        .lunch-theme { background: linear-gradient(135deg, #26d0ce, #1abc9c); }
        .dinner-theme { background: linear-gradient(135deg, #667eea, #764ba2); }
        .snack-theme { background: linear-gradient(135deg, #f093fb, #f5576c); }
        .snack1-theme { background: linear-gradient(135deg, #4facfe, #00f2fe); }
        .snack3-theme { background: linear-gradient(135deg, #43e97b, #38f9d7); }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .location-counter {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .location-counter:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }

        .location-name {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }

        .location-count {
            font-size: 1.8rem;
            font-weight: 700;
            color: #007bff;
        }

        .filter-panel {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .filter-panel .form-control,
        .filter-panel .form-select {
            background: rgba(255, 255, 255, 0.95);
            border: none;
            border-radius: 8px;
        }

        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }

        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-item {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #007bff;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #6c757d;
            font-weight: 500;
        }

        .quick-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .quick-action-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .quick-action-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            border-color: rgba(255, 255, 255, 0.5);
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

            .summary-stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .quick-actions {
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
                        <h1><i class="fas fa-calculator me-3"></i>Employee Meal Counter</h1>
                        <p>Track and manage employee meal quantities</p>
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
                    <li class="breadcrumb-item active">Employee Meal Counter</li>
                </ol>
            </nav>

            <!-- Enhanced Filter Panel -->
            <div class="filter-panel">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h5 class="mb-1"><i class="fas fa-filter me-2"></i>Meal Counter Dashboard</h5>
                        <small class="opacity-75">Real-time meal quantity tracking for <?php echo date('M d, Y', strtotime($selected_date)); ?></small>
                    </div>
                    <div class="counter-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                </div>

                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-lg-4 col-md-6">
                        <label class="form-label text-white">Select Date</label>
                        <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($selected_date); ?>" onchange="this.form.submit()">
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <label class="form-label text-white">Location Filter</label>
                        <select class="form-select" name="location" onchange="this.form.submit()">
                            <?php if ($account_type === 'superadmin'): ?>
                            <option value="all" <?php echo $location_filter === 'all' ? 'selected' : ''; ?>>All Locations</option>
                            <?php endif; ?>
                            <?php foreach ($locations as $location): ?>
                                <option value="<?php echo htmlspecialchars($location); ?>"
                                        <?php echo $location_filter === $location ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($location); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-4 col-md-12">
                        <div class="quick-actions">
                            <button type="button" class="quick-action-btn" onclick="setToday()">
                                <i class="fas fa-calendar-day me-1"></i>Today
                            </button>
                            <button type="button" class="quick-action-btn" onclick="setYesterday()">
                                <i class="fas fa-calendar-minus me-1"></i>Yesterday
                            </button>
                            <a href="?" class="quick-action-btn">
                                <i class="fas fa-refresh me-1"></i>Reset
                            </a>
                            <button type="button" class="quick-action-btn" onclick="exportData()">
                                <i class="fas fa-download me-1"></i>Export
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <?php
            // Calculate summary statistics
            $total_breakfast = array_sum(array_column($breakfast_data, 'grand_total'));
            $total_lunch = array_sum(array_column($lunch_data, 'grand_total'));
            $total_dinner = array_sum(array_column($dinner_data, 'grand_total'));
            $total_snack1 = array_sum(array_column($snack1_data, 'total'));
            $total_snack2 = array_sum(array_column($snack2_data, 'grand_total'));
            $total_snack3 = array_sum(array_column($snack3_data, 'total'));
            $grand_total = $total_breakfast + $total_lunch + $total_dinner + $total_snack1 + $total_snack2 + $total_snack3;
            ?>

            <!-- Summary Statistics -->
            <div class="summary-stats">
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($grand_total); ?></div>
                    <div class="stat-label">Total Meals</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($total_breakfast); ?></div>
                    <div class="stat-label">Breakfast</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($total_lunch); ?></div>
                    <div class="stat-label">Lunch</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($total_dinner); ?></div>
                    <div class="stat-label">Dinner</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($total_snack1 + $total_snack2 + $total_snack3); ?></div>
                    <div class="stat-label">Snacks</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo count($locations); ?></div>
                    <div class="stat-label">Locations</div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="chart-container">
                        <h6 class="mb-3"><i class="fas fa-chart-bar me-2"></i>Meal Distribution by Location</h6>
                        <canvas id="mealDistributionChart" height="100"></canvas>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="chart-container">
                        <h6 class="mb-3"><i class="fas fa-chart-pie me-2"></i>Meal Types</h6>
                        <canvas id="mealTypesChart"></canvas>
                    </div>
                </div>
            </div>

        <!-- Enhanced Meal Counters -->
        <div class="stats-grid">
            <!-- Breakfast Counter -->
            <div class="counter-card">
                <div class="counter-header breakfast-theme">
                    <div class="d-flex align-items-center">
                        <div class="counter-icon">
                            <i class="fas fa-coffee"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="counter-number"><?php echo number_format($total_breakfast); ?></div>
                            <div class="counter-label">Breakfast Total</div>
                        </div>
                    </div>
                </div>
                <div class="p-3">
                    <div class="row">
                        <?php foreach ($locations as $location): ?>
                        <?php $data = getLocationData($breakfast_data, $location); ?>
                        <div class="col-6 col-lg-4 mb-2">
                            <div class="location-counter">
                                <div class="location-name"><?php echo htmlspecialchars($location); ?></div>
                                <div class="location-count"><?php echo $data ? number_format($data['grand_total']) : '0'; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Lunch Counter -->
            <div class="counter-card">
                <div class="counter-header lunch-theme">
                    <div class="d-flex align-items-center">
                        <div class="counter-icon">
                            <i class="fas fa-bowl-food"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="counter-number"><?php echo number_format($total_lunch); ?></div>
                            <div class="counter-label">Lunch Total</div>
                        </div>
                    </div>
                </div>
                <div class="p-3">
                    <div class="row">
                        <?php foreach ($locations as $location): ?>
                        <?php $data = getLocationData($lunch_data, $location); ?>
                        <div class="col-6 col-lg-4 mb-2">
                            <div class="location-counter">
                                <div class="location-name"><?php echo htmlspecialchars($location); ?></div>
                                <div class="location-count"><?php echo $data ? number_format($data['grand_total']) : '0'; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Dinner Counter -->
            <div class="counter-card">
                <div class="counter-header dinner-theme">
                    <div class="d-flex align-items-center">
                        <div class="counter-icon">
                            <i class="fas fa-utensils"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="counter-number"><?php echo number_format($total_dinner); ?></div>
                            <div class="counter-label">Dinner Total</div>
                        </div>
                    </div>
                </div>
                <div class="p-3">
                    <div class="row">
                        <?php foreach ($locations as $location): ?>
                        <?php $data = getLocationData($dinner_data, $location); ?>
                        <div class="col-6 col-lg-4 mb-2">
                            <div class="location-counter">
                                <div class="location-name"><?php echo htmlspecialchars($location); ?></div>
                                <div class="location-count"><?php echo $data ? number_format($data['grand_total']) : '0'; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Snack 1 Counter -->
            <div class="counter-card">
                <div class="counter-header snack1-theme">
                    <div class="d-flex align-items-center">
                        <div class="counter-icon">
                            <i class="fas fa-cookie"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="counter-number"><?php echo number_format($total_snack1); ?></div>
                            <div class="counter-label">Snack 1 Total</div>
                        </div>
                    </div>
                </div>
                <div class="p-3">
                    <div class="row">
                        <?php foreach ($locations as $location): ?>
                        <?php $data = getLocationData($snack1_data, $location); ?>
                        <div class="col-6 col-lg-4 mb-2">
                            <div class="location-counter">
                                <div class="location-name"><?php echo htmlspecialchars($location); ?></div>
                                <div class="location-count"><?php echo $data ? number_format($data['total']) : '0'; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Snack 2 Counter -->
            <div class="counter-card">
                <div class="counter-header snack-theme">
                    <div class="d-flex align-items-center">
                        <div class="counter-icon">
                            <i class="fas fa-cookie-bite"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="counter-number"><?php echo number_format($total_snack2); ?></div>
                            <div class="counter-label">Snack 2 Total</div>
                        </div>
                    </div>
                </div>
                <div class="p-3">
                    <div class="row">
                        <?php foreach ($locations as $location): ?>
                        <?php $data = getLocationData($snack2_data, $location); ?>
                        <div class="col-6 col-lg-4 mb-2">
                            <div class="location-counter">
                                <div class="location-name"><?php echo htmlspecialchars($location); ?></div>
                                <div class="location-count"><?php echo $data ? number_format($data['grand_total']) : '0'; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Snack 3 Counter -->
            <div class="counter-card">
                <div class="counter-header snack3-theme">
                    <div class="d-flex align-items-center">
                        <div class="counter-icon">
                            <i class="fas fa-candy-cane"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="counter-number"><?php echo number_format($total_snack3); ?></div>
                            <div class="counter-label">Snack 3 Total</div>
                        </div>
                    </div>
                </div>
                <div class="p-3">
                    <div class="row">
                        <?php foreach ($locations as $location): ?>
                        <?php $data = getLocationData($snack3_data, $location); ?>
                        <div class="col-6 col-lg-4 mb-2">
                            <div class="location-counter">
                                <div class="location-name"><?php echo htmlspecialchars($location); ?></div>
                                <div class="location-count"><?php echo $data ? number_format($data['total']) : '0'; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        </div>
    </div>

    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Quick date functions
        function setToday() {
            const today = new Date().toISOString().split('T')[0];
            document.querySelector('input[name="date"]').value = today;
            document.querySelector('form').submit();
        }

        function setYesterday() {
            const yesterday = new Date();
            yesterday.setDate(yesterday.getDate() - 1);
            document.querySelector('input[name="date"]').value = yesterday.toISOString().split('T')[0];
            document.querySelector('form').submit();
        }

        function exportData() {
            const date = document.querySelector('input[name="date"]').value;
            const location = document.querySelector('select[name="location"]').value;

            // Create CSV data
            let csvData = 'Meal Type,Location,Count\\n';

            // You can add CSV export logic here
            console.log('Exporting data for:', date, location);
            alert('Export functionality would be implemented here');
        }

        // Initialize charts
        function initializeCharts() {
            const mealData = {
                breakfast: <?php echo $total_breakfast; ?>,
                lunch: <?php echo $total_lunch; ?>,
                dinner: <?php echo $total_dinner; ?>,
                snacks: <?php echo $total_snack1 + $total_snack2 + $total_snack3; ?>
            };

            // Meal Types Pie Chart
            const pieCtx = document.getElementById('mealTypesChart');
            if (pieCtx) {
                new Chart(pieCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Breakfast', 'Lunch', 'Dinner', 'Snacks'],
                        datasets: [{
                            data: [mealData.breakfast, mealData.lunch, mealData.dinner, mealData.snacks],
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

            // Location Distribution Bar Chart
            const locationData = <?php echo json_encode($locations); ?>;
            const breakfastByLocation = [
                <?php foreach ($locations as $location): ?>
                    <?php $data = getLocationData($breakfast_data, $location); ?>
                    <?php echo $data ? $data['grand_total'] : 0; ?>,
                <?php endforeach; ?>
            ];
            const lunchByLocation = [
                <?php foreach ($locations as $location): ?>
                    <?php $data = getLocationData($lunch_data, $location); ?>
                    <?php echo $data ? $data['grand_total'] : 0; ?>,
                <?php endforeach; ?>
            ];
            const dinnerByLocation = [
                <?php foreach ($locations as $location): ?>
                    <?php $data = getLocationData($dinner_data, $location); ?>
                    <?php echo $data ? $data['grand_total'] : 0; ?>,
                <?php endforeach; ?>
            ];

            const barCtx = document.getElementById('mealDistributionChart');
            if (barCtx) {
                new Chart(barCtx, {
                    type: 'bar',
                    data: {
                        labels: locationData,
                        datasets: [
                            {
                                label: 'Breakfast',
                                data: breakfastByLocation,
                                backgroundColor: 'rgba(255, 154, 86, 0.8)',
                                borderColor: 'rgba(255, 154, 86, 1)',
                                borderWidth: 1
                            },
                            {
                                label: 'Lunch',
                                data: lunchByLocation,
                                backgroundColor: 'rgba(38, 208, 206, 0.8)',
                                borderColor: 'rgba(38, 208, 206, 1)',
                                borderWidth: 1
                            },
                            {
                                label: 'Dinner',
                                data: dinnerByLocation,
                                backgroundColor: 'rgba(102, 126, 234, 0.8)',
                                borderColor: 'rgba(102, 126, 234, 1)',
                                borderWidth: 1
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
                                position: 'top'
                            }
                        }
                    }
                });
            }
        }

        // Initialize everything when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();

            // Add hover animations to counter cards
            document.querySelectorAll('.counter-card, .stat-item').forEach(card => {
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