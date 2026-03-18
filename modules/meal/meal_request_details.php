<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

// Check if user has permission to access meal request details
$db = new Database();
if (!hasModulePermission($db, 'meal.meal_request_details') && !hasAdminAccess()) {
    header('Location: ../../dashboard.php');
    exit();
}

$user = getCurrentUser();

// Get date range filters (default to last 7 days)
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$selected_location = $_GET['location'] ?? '';

// Get user location and account type for access control
$account_type = $user['account_type'] ?? 'user';
$user_location = $user['location'] ?? $user['location_name'] ?? null;

// Available locations
$locations = ['7C', 'Pannala', 'Kobeigane', 'JECOE', 'Head Office'];

// Apply hierarchical location filtering
if ($account_type === 'admin' && $user_location) {
    // Admins can only see their own location data
    $selected_location = $user_location; // Force admin's location
    $locations = [$user_location]; // Only show admin's location in filter
} elseif ($account_type === 'superadmin') {
    // SuperAdmins can see all locations
    // Keep existing functionality
} else {
    // Other users shouldn't have access, but if they do, restrict to their location
    if ($user_location) {
        $selected_location = $user_location;
        $locations = [$user_location];
    }
}

// Get detailed meal requests
try {
    // Employee meal requests with details
    $employee_query = "SELECT
        emp_number,
        emp_name,
        emp_location,
        meal_type,
        request_date,
        breakfast_enabled,
        breakfast_count,
        breakfast_countx,
        lunch_enabled,
        lunch_count,
        lunch_countx,
        dinner_enabled,
        dinner_count,
        dinner_countx,
        snack1_countx,
        snack2_enabled,
        snack2_count,
        snack2_countx,
        snack3_countx,
        created_at,
        (breakfast_count + breakfast_countx + lunch_count + lunch_countx +
         dinner_count + dinner_countx + snack1_countx + snack2_count +
         snack2_countx + snack3_countx) as total_meals
        FROM meal_requests_employee
        WHERE DATE(request_date) BETWEEN ? AND ?
        AND (breakfast_count + breakfast_countx + lunch_count + lunch_countx +
             dinner_count + dinner_countx + snack1_countx + snack2_count +
             snack2_countx + snack3_countx) > 0";

    $employee_params = [$date_from, $date_to];

    if ($selected_location) {
        $employee_query .= " AND emp_location = ?";
        $employee_params[] = $selected_location;
    }

    $employee_query .= " ORDER BY emp_location, emp_name";
    $employee_requests = $db->fetchAll($employee_query, $employee_params);

    // Visitor meal requests with details
    $visitor_query = "SELECT
        requesting_emp_number,
        requesting_emp_name,
        requesting_emp_location,
        visitor_names,
        visit_purpose,
        visitor_remarks,
        request_date,
        breakfast_needed,
        breakfast_menu,
        breakfast_count,
        breakfast_remarks,
        lunch_needed,
        lunch_menu,
        lunch_count,
        lunch_remarks,
        dinner_needed,
        dinner_menu,
        dinner_count,
        dinner_remarks,
        snack1_needed,
        snack1_count,
        snack1_remarks,
        snack2_needed,
        snack2_count,
        snack2_remarks,
        status,
        created_at,
        (breakfast_count + lunch_count + dinner_count + snack1_count + snack2_count) as total_meals
        FROM meal_requests_visitor
        WHERE DATE(request_date) BETWEEN ? AND ?
        AND (breakfast_count + lunch_count + dinner_count + snack1_count + snack2_count) > 0";

    $visitor_params = [$date_from, $date_to];

    if ($selected_location) {
        $visitor_query .= " AND requesting_emp_location = ?";
        $visitor_params[] = $selected_location;
    }

    $visitor_query .= " ORDER BY requesting_emp_location, requesting_emp_name";
    $visitor_requests = $db->fetchAll($visitor_query, $visitor_params);

    // Summary statistics
    $summary_query = "SELECT
        emp_location,
        COUNT(*) as request_count,
        SUM(breakfast_count + breakfast_countx + lunch_count + lunch_countx +
            dinner_count + dinner_countx + snack1_countx + snack2_count +
            snack2_countx + snack3_countx) as total_meals
        FROM meal_requests_employee
        WHERE DATE(request_date) BETWEEN ? AND ?";

    if ($selected_location) {
        $summary_query .= " AND emp_location = ? GROUP BY emp_location";
        $summary_stats = $db->fetchAll($summary_query, $employee_params);
    } else {
        $summary_stats = $db->fetchAll($summary_query . " GROUP BY emp_location ORDER BY emp_location", [$date_from, $date_to]);
    }

} catch (Exception $e) {
    $employee_requests = [];
    $visitor_requests = [];
    $summary_stats = [];
    $error_message = "Error fetching data: " . $e->getMessage();
}

// Group requests by location for better display
function groupByLocation($requests, $location_field = 'emp_location') {
    $grouped = [];
    foreach ($requests as $request) {
        $loc = $request[$location_field];
        if (!isset($grouped[$loc])) {
            $grouped[$loc] = [];
        }
        $grouped[$loc][] = $request;
    }
    return $grouped;
}

$employee_by_location = groupByLocation($employee_requests);
$visitor_by_location = groupByLocation($visitor_requests, 'requesting_emp_location');

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Meal Request Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --accent-pink: #e91e63;
            --accent-orange: #ff6b35;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --sidebar-width: 280px;
            --gradient-primary: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            --gradient-pink: linear-gradient(135deg, var(--accent-pink), #c2185b);
        }

        body {
            background: linear-gradient(135deg, #f5f7fa, #c3cfe2);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }

        .main-content {
            margin-left: 280px !important;
            min-height: 100vh;
            width: calc(100% - 280px) !important;
            position: relative;
        }

        .dashboard-content {
            background: transparent;
            padding: 2rem;
            min-height: calc(100vh - 40px);
        }

        .page-header {
            background: var(--gradient-primary);
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
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .form-card:hover, .content-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
        }

        .form-card::before, .content-card::before {
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

        .breadcrumb {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
        }

        /* Enhanced Summary Cards */
        .summary-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: none;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .summary-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--card-color);
        }

        .summary-card.employees {
            --card-color: var(--accent-pink);
        }

        .summary-card.visitors {
            --card-color: var(--accent-orange);
        }

        .summary-card.meals {
            --card-color: var(--primary-color);
        }

        .summary-card h3 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--card-color);
        }

        .summary-card p {
            color: #6c757d;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
        }

        .summary-icon {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--card-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            opacity: 0.1;
        }

        /* Enhanced Tab Navigation */
        .nav-tabs {
            border: none;
            margin-bottom: 2rem;
        }

        .nav-tabs .nav-link {
            border: none;
            border-radius: 15px 15px 0 0;
            background: #f8f9fa;
            color: #6c757d;
            font-weight: 600;
            padding: 1rem 1.5rem;
            margin-right: 0.5rem;
            transition: all 0.3s ease;
        }

        .nav-tabs .nav-link:hover {
            background: #e9ecef;
            color: #2c3e50;
        }

        .nav-tabs .nav-link.active {
            background: var(--accent-pink);
            color: white;
            box-shadow: 0 5px 15px rgba(233, 30, 99, 0.3);
        }

        .nav-tabs .nav-link .badge {
            background: rgba(255,255,255,0.2) !important;
        }

        /* Enhanced Filters */
        .filter-section {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            position: relative;
        }

        .filter-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
            border-radius: 20px 20px 0 0;
        }

        .filter-section .form-control, .filter-section .form-select {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .filter-section .form-control:focus, .filter-section .form-select:focus {
            border-color: var(--accent-pink);
            box-shadow: 0 0 0 0.2rem rgba(233, 30, 99, 0.25);
        }

        .filter-section .btn {
            border-radius: 12px;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
        }

        .filter-section .btn-primary {
            background: var(--gradient-pink);
            border: none;
        }

        .filter-section .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(233, 30, 99, 0.3);
        }

        .filter-section .btn-success {
            background: var(--gradient-primary);
            border: none;
        }

        .filter-section .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        /* Enhanced Badges */
        .meal-badge {
            font-size: 0.8em;
            margin-right: 5px;
            border-radius: 25px;
            padding: 0.5rem 1rem;
            font-weight: 600;
        }

        .badge.bg-warning {
            background: linear-gradient(135deg, #ffc107, #fd7e14) !important;
        }

        .badge.bg-primary {
            background: var(--gradient-primary) !important;
        }

        .badge.bg-success {
            background: linear-gradient(135deg, #28a745, #20c997) !important;
        }

        .badge.bg-info {
            background: linear-gradient(135deg, #17a2b8, #138496) !important;
        }

        .badge.bg-dark {
            background: linear-gradient(135deg, #343a40, #23272b) !important;
        }

        .badge.bg-secondary {
            background: linear-gradient(135deg, #6c757d, #545b62) !important;
        }

        /* Empty State Enhancements */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 20px;
            margin: 2rem 0;
        }

        .empty-state i {
            color: #dee2e6;
            margin-bottom: 1.5rem;
        }

        .empty-state h4 {
            color: #6c757d;
            margin-bottom: 1rem;
        }

        .empty-state p {
            color: #8e9297;
            max-width: 400px;
            margin: 0 auto;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }

            .dashboard-content {
                padding: 1rem;
            }

            .page-header h1 {
                font-size: 2rem;
            }

            .page-header {
                padding: 1.5rem;
            }

            .summary-card h3 {
                font-size: 2rem;
            }

            .nav-tabs .nav-link {
                padding: 0.75rem 1rem;
                margin-right: 0.25rem;
                font-size: 0.9rem;
            }

            .filter-section {
                padding: 1.5rem;
            }
        }

        .location-header {
            background: var(--gradient-pink);
            color: white;
            padding: 1.5rem 2rem;
            border-radius: 15px;
            margin: 20px 0 15px 0;
            box-shadow: 0 8px 25px rgba(233, 30, 99, 0.3);
            position: relative;
            overflow: hidden;
        }

        .location-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20px;
            width: 100px;
            height: 200px;
            background: rgba(255,255,255,0.1);
            border-radius: 50px;
            transform: rotate(15deg);
        }

        .location-header h4 {
            margin: 0;
            font-weight: 700;
            position: relative;
            z-index: 2;
        }

        .location-header small {
            opacity: 0.9;
            position: relative;
            z-index: 2;
        }
        .meal-badge {
            font-size: 0.8em;
            margin-right: 5px;
        }
        .visitor-card {
            border-left: 5px solid var(--accent-orange);
            background: #fff;
            border-radius: 20px;
            transition: all 0.3s ease;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .visitor-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 50px rgba(255, 107, 53, 0.2);
        }

        .visitor-card .card-header {
            background: var(--gradient-primary) !important;
            border: none;
            border-radius: 20px 20px 0 0 !important;
            padding: 1.5rem;
        }

        .visitor-card .card-body {
            padding: 2rem;
        }

        .visitor-card .card-footer {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border: none;
            border-radius: 0 0 20px 20px;
            padding: 1.5rem;
        }
        .employee-card {
            border-left: 5px solid var(--accent-pink);
            background: #fff;
            border-radius: 15px;
            transition: all 0.3s ease;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .employee-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(233, 30, 99, 0.2);
        }

        .employee-card .table {
            margin-bottom: 0;
        }

        .employee-card .table th {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            color: #2c3e50;
            font-weight: 600;
            border: none;
            padding: 1rem 0.75rem;
        }

        .employee-card .table td {
            padding: 1rem 0.75rem;
            border-color: #f8f9fa;
            vertical-align: middle;
        }

        .employee-card .table tbody tr:hover {
            background: linear-gradient(135deg, #fff5f8, #fce4ec);
        }
        .print-hide {
            display: none !important;
        }
        @media print {
            .no-print { display: none !important; }
            .print-show { display: block !important; }
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
            <div class="page-header no-print">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="header-content">
                        <h1><i class="fas fa-info-circle me-3"></i>Meal Request Details</h1>
                        <p>View detailed information about specific meal requests</p>
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
                    <li class="breadcrumb-item active">Meal Request Details</li>
                </ol>
            </nav>

            <!-- Enhanced Filters -->
            <div class="filter-section no-print">
                <div class="d-flex align-items-center mb-4">
                    <div class="flex-grow-1">
                        <h5 class="mb-1"><i class="fas fa-filter me-2 text-primary"></i>Advanced Filters</h5>
                        <p class="text-muted mb-0">Customize your meal request view</p>
                    </div>
                    <div class="d-flex gap-2">
                        <span class="badge bg-light text-dark">Date Range: <?php echo count(array_diff(range(strtotime($date_from), strtotime($date_to), 86400))); ?> days</span>
                        <?php if ($selected_location): ?>
                            <span class="badge bg-primary"><?php echo htmlspecialchars($selected_location); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <form method="GET" class="row g-4 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold"><i class="fas fa-calendar-day me-1"></i>From Date</label>
                        <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold"><i class="fas fa-calendar-day me-1"></i>To Date</label>
                        <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold"><i class="fas fa-map-marker-alt me-1"></i>Location Filter</label>
                        <select class="form-select" name="location">
                            <?php if ($account_type === 'superadmin'): ?>
                            <option value="">🌍 All Locations</option>
                            <?php endif; ?>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?php echo $loc; ?>" <?php echo $selected_location === $loc ? 'selected' : ''; ?>>
                                    🏢 <?php echo htmlspecialchars($loc); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Apply Filters
                            </button>
                            <button type="button" class="btn btn-success" onclick="window.print()">
                                <i class="fas fa-print me-2"></i>Print Report
                            </button>
                        </div>
                    </div>
                </form>
            </div>

        <!-- Print Header -->
        <div class="print-show d-none">
            <div class="text-center mb-4">
                <h2>SmartHRM - Detailed Meal Requests</h2>
                <h4>Date Range: <?php echo date('F j, Y', strtotime($date_from)); ?> - <?php echo date('F j, Y', strtotime($date_to)); ?></h4>
                <?php if ($selected_location): ?>
                    <h5>Location: <?php echo htmlspecialchars($selected_location); ?></h5>
                <?php endif; ?>
                <hr>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Enhanced Summary Statistics -->
        <div class="row mb-4">
            <div class="col-md-4 mb-4">
                <div class="summary-card employees">
                    <div class="summary-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3><?php echo number_format(count($employee_requests)); ?></h3>
                    <p>Employee Requests</p>
                    <div class="mt-3">
                        <small class="text-muted">Active meal orders</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="summary-card visitors">
                    <div class="summary-icon">
                        <i class="fas fa-user-friends"></i>
                    </div>
                    <h3><?php echo number_format(count($visitor_requests)); ?></h3>
                    <p>Visitor Requests</p>
                    <div class="mt-3">
                        <small class="text-muted">Guest meal arrangements</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="summary-card meals">
                    <div class="summary-icon">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <h3>
                        <?php
                        $total_meals = array_sum(array_column($employee_requests, 'total_meals')) +
                                      array_sum(array_column($visitor_requests, 'total_meals'));
                        echo number_format($total_meals);
                        ?>
                    </h3>
                    <p>Total Meals</p>
                    <div class="mt-3">
                        <small class="text-muted">Combined meal count</small>
                    </div>
                </div>
            </div>
        </div>

            <!-- Enhanced Tab Navigation -->
            <div class="content-card">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h5 class="mb-1"><i class="fas fa-list-alt me-2 text-primary"></i>Detailed Request View</h5>
                        <p class="text-muted mb-0">Browse through meal requests by category</p>
                    </div>
                    <div class="d-flex gap-2">
                        <span class="badge" style="background: var(--accent-pink);">Total: <?php echo count($employee_requests) + count($visitor_requests); ?> Requests</span>
                    </div>
                </div>
                <ul class="nav nav-tabs" id="mealRequestTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="employee-tab" data-bs-toggle="tab" data-bs-target="#employee-requests" type="button" role="tab">
                            <i class="fas fa-users me-2"></i>Employee Requests
                            <span class="badge ms-2"><?php echo count($employee_requests); ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="visitor-tab" data-bs-toggle="tab" data-bs-target="#visitor-requests" type="button" role="tab">
                            <i class="fas fa-user-friends me-2"></i>Visitor Requests
                            <span class="badge ms-2"><?php echo count($visitor_requests); ?></span>
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="mealRequestTabContent">
                    <!-- Employee Requests Tab -->
                    <div class="tab-pane fade show active" id="employee-requests" role="tabpanel">
                        <?php
                        $all_locations = $selected_location ? [$selected_location] : $locations;
                        foreach ($all_locations as $location):
                            $emp_requests = $employee_by_location[$location] ?? [];
                            if (empty($emp_requests)) continue;
                        ?>
                            <div class="location-header">
                                <h4><i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars($location); ?></h4>
                                <small><?php echo count($emp_requests); ?> Employee Requests</small>
                            </div>

                            <div class="card border-0 shadow mb-4 employee-card">
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Employee</th>
                                                    <th>Meal Type</th>
                                                    <th class="text-center">Breakfast</th>
                                                    <th class="text-center">Lunch</th>
                                                    <th class="text-center">Dinner</th>
                                                    <th class="text-center">Snack 1</th>
                                                    <th class="text-center">Snack 2</th>
                                                    <th class="text-center">Snack 3</th>
                                                    <th class="text-center">Total</th>
                                                    <th class="text-center no-print">Time</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($emp_requests as $req): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($req['emp_name']); ?></strong><br>
                                                        <small class="text-muted">EPF: <?php echo htmlspecialchars($req['emp_number']); ?></small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($req['meal_type']); ?></td>
                                                    <td class="text-center">
                                                        <?php
                                                        $breakfast_total = $req['breakfast_count'] + $req['breakfast_countx'];
                                                        echo $breakfast_total > 0 ? $breakfast_total : '-';
                                                        ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php
                                                        $lunch_total = $req['lunch_count'] + $req['lunch_countx'];
                                                        echo $lunch_total > 0 ? $lunch_total : '-';
                                                        ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php
                                                        $dinner_total = $req['dinner_count'] + $req['dinner_countx'];
                                                        echo $dinner_total > 0 ? $dinner_total : '-';
                                                        ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php echo $req['snack1_countx'] > 0 ? $req['snack1_countx'] : '-'; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php echo $req['snack2_count'] > 0 ? $req['snack2_count'] : '-'; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php echo $req['snack3_countx'] > 0 ? $req['snack3_countx'] : '-'; ?>
                                                    </td>
                                                    <td class="text-center"><strong><?php echo $req['total_meals']; ?></strong></td>
                                                    <td class="text-center no-print">
                                                        <small><?php echo date('g:i A', strtotime($req['created_at'])); ?></small>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (empty($employee_requests)): ?>
                            <div class="empty-state">
                                <i class="fas fa-users" style="font-size: 4rem;"></i>
                                <h4>No Employee Meal Requests</h4>
                                <p>No employee meal requests were found for the selected date range and location. Try adjusting your filters or check a different time period.</p>
                                <div class="mt-4">
                                    <a href="employee_meal_request.php" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Create New Request
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Visitor Requests Tab -->
                    <div class="tab-pane fade" id="visitor-requests" role="tabpanel">
                        <?php
                        foreach ($all_locations as $location):
                            $vis_requests = $visitor_by_location[$location] ?? [];
                            if (empty($vis_requests)) continue;
                        ?>
                            <div class="location-header">
                                <h4><i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars($location); ?></h4>
                                <small><?php echo count($vis_requests); ?> Visitor Requests</small>
                            </div>

                            <div class="row">
                                <?php foreach ($vis_requests as $req): ?>
                                    <div class="col-lg-6 mb-4">
                                        <div class="card border-0 shadow visitor-card h-100">
                                            <div class="card-header bg-info text-white">
                                                <h6 class="mb-0">
                                                    <i class="fas fa-user-friends me-2"></i>
                                                    <?php echo htmlspecialchars($req['visitor_names'] ?? 'N/A'); ?>
                                                </h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-12 mb-3">
                                                        <h6 class="text-primary">Requesting Employee:</h6>
                                                        <p class="mb-1"><strong><?php echo htmlspecialchars($req['requesting_emp_name']); ?></strong></p>
                                                        <p class="mb-1"><small>EPF: <?php echo htmlspecialchars($req['requesting_emp_number']); ?></small></p>
                                                    </div>

                                                    <div class="col-12 mb-3">
                                                        <h6 class="text-info">Visit Details:</h6>
                                                        <p class="mb-1"><strong>Purpose:</strong> <?php echo htmlspecialchars($req['visit_purpose'] ?? 'N/A'); ?></p>
                                                        <?php if ($req['visitor_remarks']): ?>
                                                            <p class="mb-1"><strong>Remarks:</strong> <?php echo htmlspecialchars($req['visitor_remarks']); ?></p>
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="col-12">
                                                        <h6 class="text-success">Meal Orders:</h6>
                                                        <div class="table-responsive">
                                                            <table class="table table-sm">
                                                                <thead>
                                                                    <tr>
                                                                        <th>Meal</th>
                                                                        <th>Menu</th>
                                                                        <th class="text-center">Count</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php if ($req['breakfast_needed']): ?>
                                                                    <tr>
                                                                        <td><span class="badge bg-warning text-dark">Breakfast</span></td>
                                                                        <td><?php echo htmlspecialchars($req['breakfast_menu']); ?></td>
                                                                        <td class="text-center"><?php echo $req['breakfast_count']; ?></td>
                                                                    </tr>
                                                                    <?php endif; ?>

                                                                    <?php if ($req['lunch_needed']): ?>
                                                                    <tr>
                                                                        <td><span class="badge bg-primary">Lunch</span></td>
                                                                        <td><?php echo htmlspecialchars($req['lunch_menu']); ?></td>
                                                                        <td class="text-center"><?php echo $req['lunch_count']; ?></td>
                                                                    </tr>
                                                                    <?php endif; ?>

                                                                    <?php if ($req['dinner_needed']): ?>
                                                                    <tr>
                                                                        <td><span class="badge bg-dark">Dinner</span></td>
                                                                        <td><?php echo htmlspecialchars($req['dinner_menu']); ?></td>
                                                                        <td class="text-center"><?php echo $req['dinner_count']; ?></td>
                                                                    </tr>
                                                                    <?php endif; ?>

                                                                    <?php if ($req['snack1_needed'] && $req['snack1_count'] > 0): ?>
                                                                    <tr>
                                                                        <td><span class="badge bg-secondary">Snack 1</span></td>
                                                                        <td>Standard</td>
                                                                        <td class="text-center"><?php echo $req['snack1_count']; ?></td>
                                                                    </tr>
                                                                    <?php endif; ?>

                                                                    <?php if ($req['snack2_needed'] && $req['snack2_count'] > 0): ?>
                                                                    <tr>
                                                                        <td><span class="badge bg-secondary">Snack 2</span></td>
                                                                        <td>Standard</td>
                                                                        <td class="text-center"><?php echo $req['snack2_count']; ?></td>
                                                                    </tr>
                                                                    <?php endif; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="card-footer bg-light">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h5><span class="badge bg-success">Total: <?php echo $req['total_meals']; ?> meals</span></h5>
                                                        <span class="badge bg-<?php echo $req['status'] === 'pending' ? 'warning' : 'success'; ?>">
                                                            <?php echo ucfirst($req['status']); ?>
                                                        </span>
                                                    </div>
                                                    <div class="text-end no-print">
                                                        <small class="text-muted">
                                                            <?php echo date('M j, Y g:i A', strtotime($req['created_at'])); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>

                        <?php if (empty($visitor_requests)): ?>
                            <div class="empty-state">
                                <i class="fas fa-user-friends" style="font-size: 4rem;"></i>
                                <h4>No Visitor Meal Requests</h4>
                                <p>No visitor meal requests were found for the selected date range and location. Try adjusting your filters or check a different time period.</p>
                                <div class="mt-4">
                                    <a href="visitor_meal_request.php" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Create New Request
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (empty($employee_requests) && empty($visitor_requests)): ?>
                <div class="content-card">
                    <div class="empty-state">
                        <i class="fas fa-utensils" style="font-size: 5rem;"></i>
                        <h4>No Meal Requests Found</h4>
                        <p>No meal requests were submitted between <strong><?php echo date('F j, Y', strtotime($date_from)); ?></strong> and <strong><?php echo date('F j, Y', strtotime($date_to)); ?></strong>
                        <?php echo $selected_location ? 'at <strong>' . htmlspecialchars($selected_location) . '</strong>' : ''; ?>.</p>
                        <div class="mt-4 d-flex gap-3 justify-content-center">
                            <a href="employee_meal_request.php" class="btn btn-primary">
                                <i class="fas fa-users me-2"></i>Employee Request
                            </a>
                            <a href="visitor_meal_request.php" class="btn btn-outline-primary">
                                <i class="fas fa-user-friends me-2"></i>Visitor Request
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>