<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

// Check if user has permission to access visitor meal counter
$db = new Database();
if (!hasModulePermission($db, 'meal.visitor_meal_counter') && !hasAdminAccess()) {
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
    $location_condition = ' AND requesting_emp_location = ?';
    $params[] = $location_filter;
}

// Get visitor meal counter data
try {
    // Breakfast totals
    $breakfast_query = "SELECT
        requesting_emp_location,
        SUM(breakfast_count) as total
        FROM meal_requests_visitor
        WHERE DATE(request_date) = ? AND breakfast_needed = 1 $location_condition
        GROUP BY requesting_emp_location";

    $breakfast_data = $db->fetchAll($breakfast_query, $params);

    // Lunch totals
    $lunch_query = "SELECT
        requesting_emp_location,
        SUM(lunch_count) as total
        FROM meal_requests_visitor
        WHERE DATE(request_date) = ? AND lunch_needed = 1 $location_condition
        GROUP BY requesting_emp_location";

    $lunch_data = $db->fetchAll($lunch_query, $params);

    // Dinner totals
    $dinner_query = "SELECT
        requesting_emp_location,
        SUM(dinner_count) as total
        FROM meal_requests_visitor
        WHERE DATE(request_date) = ? AND dinner_needed = 1 $location_condition
        GROUP BY requesting_emp_location";

    $dinner_data = $db->fetchAll($dinner_query, $params);

    // Snack 1 totals
    $snack1_query = "SELECT
        requesting_emp_location,
        SUM(snack1_count) as total
        FROM meal_requests_visitor
        WHERE DATE(request_date) = ? AND snack1_needed = 1 $location_condition
        GROUP BY requesting_emp_location";

    $snack1_data = $db->fetchAll($snack1_query, $params);

    // Snack 2 totals
    $snack2_query = "SELECT
        requesting_emp_location,
        SUM(snack2_count) as total
        FROM meal_requests_visitor
        WHERE DATE(request_date) = ? AND snack2_needed = 1 $location_condition
        GROUP BY requesting_emp_location";

    $snack2_data = $db->fetchAll($snack2_query, $params);

} catch (Exception $e) {
    $breakfast_data = [];
    $lunch_data = [];
    $dinner_data = [];
    $snack1_data = [];
    $snack2_data = [];
}

// Helper function to get data for location
function getLocationData($data, $location) {
    foreach ($data as $row) {
        if ($row['requesting_emp_location'] === $location) {
            return $row;
        }
    }
    return null;
}

// Calculate grand totals
function calculateGrandTotal($data) {
    $total = 0;
    foreach ($data as $row) {
        $total += $row['total'];
    }
    return $total;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Visitor Meal Counter</title>
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

        /* Enhanced Visitor Counter Dashboard Styles */
        .visitor-counter-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: none;
            margin-bottom: 2rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .visitor-counter-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .visitor-counter-header {
            padding: 1.5rem;
            position: relative;
            color: white;
        }

        .visitor-counter-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 80%;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transform: translateX(-50%);
        }

        .visitor-counter-icon {
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

        .visitor-counter-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .visitor-counter-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .visitor-breakfast-theme { background: linear-gradient(135deg, #ff9a56, #ffb347); }
        .visitor-lunch-theme { background: linear-gradient(135deg, #26d0ce, #1abc9c); }
        .visitor-dinner-theme { background: linear-gradient(135deg, #667eea, #764ba2); }
        .visitor-snack1-theme { background: linear-gradient(135deg, #4facfe, #00f2fe); }
        .visitor-snack2-theme { background: linear-gradient(135deg, #f093fb, #f5576c); }

        .visitor-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .visitor-location-counter {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .visitor-location-counter:hover {
            background: #e9ecef;
            transform: translateY(-2px);
            border-color: rgba(0, 123, 255, 0.2);
        }

        .visitor-location-name {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .visitor-location-count {
            font-size: 1.8rem;
            font-weight: 700;
            color: #007bff;
        }

        .visitor-filter-panel {
            background: linear-gradient(135deg, #f093fb, #f5576c);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .visitor-filter-panel .form-control,
        .visitor-filter-panel .form-select {
            background: rgba(255, 255, 255, 0.95);
            border: none;
            border-radius: 8px;
        }

        .visitor-chart-container {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }

        .visitor-summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .visitor-stat-item {
            background: white;
            border-radius: 12px;
            padding: 2rem 1.5rem;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .visitor-stat-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #f093fb, #f5576c);
        }

        .visitor-stat-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
        }

        .visitor-stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .visitor-stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .visitor-stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            font-weight: 500;
        }

        .visitor-export-panel {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .visitor-quick-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .visitor-quick-action-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        .visitor-quick-action-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
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

            .visitor-stats-grid {
                grid-template-columns: 1fr;
            }

            .visitor-summary-stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .visitor-quick-actions {
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
                        <h1><i class="fas fa-users me-3"></i>Visitor Meal Counter</h1>
                        <p>Track and manage visitor meal quantities</p>
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
                    <li class="breadcrumb-item active">Visitor Meal Counter</li>
                </ol>
            </nav>

            <!-- Enhanced Filter Panel -->
            <div class="visitor-filter-panel">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h5 class="mb-1"><i class="fas fa-users me-2"></i>Visitor Meal Counter Dashboard</h5>
                        <small class="opacity-75">Real-time visitor meal tracking for <?php echo date('M d, Y', strtotime($selected_date)); ?></small>
                    </div>
                    <div class="visitor-counter-icon">
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
                        <div class="visitor-quick-actions">
                            <button type="button" class="visitor-quick-action-btn" onclick="setToday()">
                                <i class="fas fa-calendar-day me-1"></i>Today
                            </button>
                            <button type="button" class="visitor-quick-action-btn" onclick="setYesterday()">
                                <i class="fas fa-calendar-minus me-1"></i>Yesterday
                            </button>
                            <a href="?" class="visitor-quick-action-btn">
                                <i class="fas fa-refresh me-1"></i>Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <?php
            // Calculate summary statistics
            $total_breakfast = calculateGrandTotal($breakfast_data);
            $total_lunch = calculateGrandTotal($lunch_data);
            $total_dinner = calculateGrandTotal($dinner_data);
            $total_snack1 = calculateGrandTotal($snack1_data);
            $total_snack2 = calculateGrandTotal($snack2_data);
            $grand_total = $total_breakfast + $total_lunch + $total_dinner + $total_snack1 + $total_snack2;
            ?>

            <!-- Summary Statistics -->
            <div class="visitor-summary-stats">
                <div class="visitor-stat-item">
                    <div class="visitor-stat-icon visitor-breakfast-theme">
                        <i class="fas fa-coffee"></i>
                    </div>
                    <div class="visitor-stat-value"><?php echo number_format($total_breakfast); ?></div>
                    <div class="visitor-stat-label">Visitor Breakfast</div>
                </div>
                <div class="visitor-stat-item">
                    <div class="visitor-stat-icon visitor-lunch-theme">
                        <i class="fas fa-bowl-food"></i>
                    </div>
                    <div class="visitor-stat-value"><?php echo number_format($total_lunch); ?></div>
                    <div class="visitor-stat-label">Visitor Lunch</div>
                </div>
                <div class="visitor-stat-item">
                    <div class="visitor-stat-icon visitor-dinner-theme">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <div class="visitor-stat-value"><?php echo number_format($total_dinner); ?></div>
                    <div class="visitor-stat-label">Visitor Dinner</div>
                </div>
                <div class="visitor-stat-item">
                    <div class="visitor-stat-icon visitor-snack1-theme">
                        <i class="fas fa-cookie"></i>
                    </div>
                    <div class="visitor-stat-value"><?php echo number_format($total_snack1 + $total_snack2); ?></div>
                    <div class="visitor-stat-label">Visitor Snacks</div>
                </div>
                <div class="visitor-stat-item">
                    <div class="visitor-stat-icon" style="background: linear-gradient(135deg, #43e97b, #38f9d7);">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div class="visitor-stat-value"><?php echo number_format($grand_total); ?></div>
                    <div class="visitor-stat-label">Total Meals</div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="visitor-chart-container">
                        <h6 class="mb-3"><i class="fas fa-chart-bar me-2"></i>Visitor Meal Distribution by Location</h6>
                        <canvas id="visitorMealChart" height="100"></canvas>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="visitor-chart-container">
                        <h6 class="mb-3"><i class="fas fa-chart-doughnut me-2"></i>Meal Types Breakdown</h6>
                        <canvas id="visitorMealTypesChart"></canvas>
                    </div>
                </div>
            </div>

        <!-- Enhanced Visitor Meal Counters -->
        <div class="visitor-stats-grid">
            <!-- Breakfast Counter -->
            <div class="visitor-counter-card">
                <div class="visitor-counter-header visitor-breakfast-theme">
                    <div class="d-flex align-items-center">
                        <div class="visitor-counter-icon">
                            <i class="fas fa-coffee"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="visitor-counter-number"><?php echo number_format($total_breakfast); ?></div>
                            <div class="visitor-counter-label">Visitor Breakfast</div>
                        </div>
                    </div>
                </div>
                <div class="p-3">
                    <div class="row">
                        <?php foreach ($locations as $location): ?>
                        <?php $data = getLocationData($breakfast_data, $location); ?>
                        <div class="col-6 col-lg-4 mb-2">
                            <div class="visitor-location-counter">
                                <div class="visitor-location-name"><?php echo htmlspecialchars($location); ?></div>
                                <div class="visitor-location-count"><?php echo $data ? number_format($data['total']) : '0'; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Lunch Counter -->
            <div class="visitor-counter-card">
                <div class="visitor-counter-header visitor-lunch-theme">
                    <div class="d-flex align-items-center">
                        <div class="visitor-counter-icon">
                            <i class="fas fa-bowl-food"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="visitor-counter-number"><?php echo number_format($total_lunch); ?></div>
                            <div class="visitor-counter-label">Visitor Lunch</div>
                        </div>
                    </div>
                </div>
                <div class="p-3">
                    <div class="row">
                        <?php foreach ($locations as $location): ?>
                        <?php $data = getLocationData($lunch_data, $location); ?>
                        <div class="col-6 col-lg-4 mb-2">
                            <div class="visitor-location-counter">
                                <div class="visitor-location-name"><?php echo htmlspecialchars($location); ?></div>
                                <div class="visitor-location-count"><?php echo $data ? number_format($data['total']) : '0'; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Dinner Counter -->
            <div class="visitor-counter-card">
                <div class="visitor-counter-header visitor-dinner-theme">
                    <div class="d-flex align-items-center">
                        <div class="visitor-counter-icon">
                            <i class="fas fa-utensils"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="visitor-counter-number"><?php echo number_format($total_dinner); ?></div>
                            <div class="visitor-counter-label">Visitor Dinner</div>
                        </div>
                    </div>
                </div>
                <div class="p-3">
                    <div class="row">
                        <?php foreach ($locations as $location): ?>
                        <?php $data = getLocationData($dinner_data, $location); ?>
                        <div class="col-6 col-lg-4 mb-2">
                            <div class="visitor-location-counter">
                                <div class="visitor-location-name"><?php echo htmlspecialchars($location); ?></div>
                                <div class="visitor-location-count"><?php echo $data ? number_format($data['total']) : '0'; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Snack 1 Counter -->
            <div class="visitor-counter-card">
                <div class="visitor-counter-header visitor-snack1-theme">
                    <div class="d-flex align-items-center">
                        <div class="visitor-counter-icon">
                            <i class="fas fa-cookie"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="visitor-counter-number"><?php echo number_format($total_snack1); ?></div>
                            <div class="visitor-counter-label">Visitor Snack 1</div>
                        </div>
                    </div>
                </div>
                <div class="p-3">
                    <div class="row">
                        <?php foreach ($locations as $location): ?>
                        <?php $data = getLocationData($snack1_data, $location); ?>
                        <div class="col-6 col-lg-4 mb-2">
                            <div class="visitor-location-counter">
                                <div class="visitor-location-name"><?php echo htmlspecialchars($location); ?></div>
                                <div class="visitor-location-count"><?php echo $data ? number_format($data['total']) : '0'; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Snack 2 Counter -->
            <div class="visitor-counter-card">
                <div class="visitor-counter-header visitor-snack2-theme">
                    <div class="d-flex align-items-center">
                        <div class="visitor-counter-icon">
                            <i class="fas fa-cookie-bite"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="visitor-counter-number"><?php echo number_format($total_snack2); ?></div>
                            <div class="visitor-counter-label">Visitor Snack 2</div>
                        </div>
                    </div>
                </div>
                <div class="p-3">
                    <div class="row">
                        <?php foreach ($locations as $location): ?>
                        <?php $data = getLocationData($snack2_data, $location); ?>
                        <div class="col-6 col-lg-4 mb-2">
                            <div class="visitor-location-counter">
                                <div class="visitor-location-name"><?php echo htmlspecialchars($location); ?></div>
                                <div class="visitor-location-count"><?php echo $data ? number_format($data['total']) : '0'; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Export Panel -->
        <div class="visitor-export-panel">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h5 class="mb-1"><i class="fas fa-download me-2"></i>Export & Reports</h5>
                    <small class="opacity-75">Download visitor meal data in various formats</small>
                </div>
                <div class="visitor-counter-icon">
                    <i class="fas fa-file-export"></i>
                </div>
            </div>
            <div class="visitor-quick-actions">
                <button class="visitor-quick-action-btn" onclick="exportToExcel()">
                    <i class="fas fa-file-excel me-2"></i>Excel Export
                </button>
                <button class="visitor-quick-action-btn" onclick="exportToPDF()">
                    <i class="fas fa-file-pdf me-2"></i>PDF Report
                </button>
                <button class="visitor-quick-action-btn" onclick="printReport()">
                    <i class="fas fa-print me-2"></i>Print
                </button>
                <button class="visitor-quick-action-btn" onclick="exportToCSV()">
                    <i class="fas fa-file-csv me-2"></i>CSV Data
                </button>
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

        function exportToExcel() {
            const date = document.querySelector('input[name="date"]').value;
            const location = document.querySelector('select[name="location"]').value;
            console.log('Exporting to Excel for date:', date, 'location:', location);
            alert('Excel export would be implemented here');
        }

        function exportToPDF() {
            const date = document.querySelector('input[name="date"]').value;
            const location = document.querySelector('select[name="location"]').value;
            console.log('Exporting to PDF for date:', date, 'location:', location);
            alert('PDF export would be implemented here');
        }

        function exportToCSV() {
            const date = document.querySelector('input[name="date"]').value;
            const location = document.querySelector('select[name="location"]').value;
            console.log('Exporting to CSV for date:', date, 'location:', location);
            alert('CSV export would be implemented here');
        }

        function printReport() {
            window.print();
        }

        // Initialize charts
        function initializeVisitorCharts() {
            const mealData = {
                breakfast: <?php echo $total_breakfast; ?>,
                lunch: <?php echo $total_lunch; ?>,
                dinner: <?php echo $total_dinner; ?>,
                snack1: <?php echo $total_snack1; ?>,
                snack2: <?php echo $total_snack2; ?>
            };

            // Visitor Meal Types Doughnut Chart
            const pieCtx = document.getElementById('visitorMealTypesChart');
            if (pieCtx) {
                new Chart(pieCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Breakfast', 'Lunch', 'Dinner', 'Snack 1', 'Snack 2'],
                        datasets: [{
                            data: [mealData.breakfast, mealData.lunch, mealData.dinner, mealData.snack1, mealData.snack2],
                            backgroundColor: [
                                'rgba(255, 154, 86, 0.8)',
                                'rgba(38, 208, 206, 0.8)',
                                'rgba(102, 126, 234, 0.8)',
                                'rgba(79, 172, 254, 0.8)',
                                'rgba(240, 147, 251, 0.8)'
                            ],
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 15,
                                    usePointStyle: true,
                                    font: {
                                        size: 11
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Visitor Location Distribution Bar Chart
            const locationData = <?php echo json_encode($locations); ?>;
            const breakfastByLocation = [
                <?php foreach ($locations as $location): ?>
                    <?php $data = getLocationData($breakfast_data, $location); ?>
                    <?php echo $data ? $data['total'] : 0; ?>,
                <?php endforeach; ?>
            ];
            const lunchByLocation = [
                <?php foreach ($locations as $location): ?>
                    <?php $data = getLocationData($lunch_data, $location); ?>
                    <?php echo $data ? $data['total'] : 0; ?>,
                <?php endforeach; ?>
            ];
            const dinnerByLocation = [
                <?php foreach ($locations as $location): ?>
                    <?php $data = getLocationData($dinner_data, $location); ?>
                    <?php echo $data ? $data['total'] : 0; ?>,
                <?php endforeach; ?>
            ];
            const snack1ByLocation = [
                <?php foreach ($locations as $location): ?>
                    <?php $data = getLocationData($snack1_data, $location); ?>
                    <?php echo $data ? $data['total'] : 0; ?>,
                <?php endforeach; ?>
            ];
            const snack2ByLocation = [
                <?php foreach ($locations as $location): ?>
                    <?php $data = getLocationData($snack2_data, $location); ?>
                    <?php echo $data ? $data['total'] : 0; ?>,
                <?php endforeach; ?>
            ];

            const barCtx = document.getElementById('visitorMealChart');
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
                            },
                            {
                                label: 'Snack 1',
                                data: snack1ByLocation,
                                backgroundColor: 'rgba(79, 172, 254, 0.8)',
                                borderColor: 'rgba(79, 172, 254, 1)',
                                borderWidth: 1
                            },
                            {
                                label: 'Snack 2',
                                data: snack2ByLocation,
                                backgroundColor: 'rgba(240, 147, 251, 0.8)',
                                borderColor: 'rgba(240, 147, 251, 1)',
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
            initializeVisitorCharts();

            // Add hover animations to counter cards
            document.querySelectorAll('.visitor-counter-card, .visitor-stat-item').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });

                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            // Add click animation to location counters
            document.querySelectorAll('.visitor-location-counter').forEach(counter => {
                counter.addEventListener('click', function() {
                    this.style.transform = 'scale(1.05)';
                    setTimeout(() => {
                        this.style.transform = 'scale(1)';
                    }, 150);
                });
            });
        });
    </script>
</body>
</html>