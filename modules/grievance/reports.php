<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

// Check if user has permission to access reports & analytics
requirePermission('grievance', 'reports_analytics');

$db = new Database();

$user = getCurrentUser();

// Check if user has access to this page
if (!isSuperAdmin() && !hasModulePermission($db, 'grievance.reports')) {
    header('Location: ../../dashboard.php');
    exit;
}

// Get user location and account type for access control
$account_type = $user['account_type'] ?? 'user';
$user_location = $user['location'] ?? $user['location_name'] ?? null;

// Handle export requests
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    $format = $_GET['format'] ?? 'pdf';

    if ($export_type === 'monthly_summary') {
        // Handle monthly summary export
        header('Content-Type: application/json');
        echo json_encode(['message' => 'Export functionality will be implemented']);
        exit();
    }
}

// Get date range for filtering
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Today
$location_filter = $_GET['location'] ?? '';
$category_filter = $_GET['category'] ?? '';

// Build WHERE clause for regular grievances
$regular_where_conditions = ["submission_date BETWEEN ? AND ?"];
$regular_params = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];

// Build WHERE clause for anonymous grievances
$anonymous_where_conditions = ["submission_date BETWEEN ? AND ?"];
$anonymous_params = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];

// Apply access control filters
if ($account_type === 'manager') {
    // Manager can see cases from their department AND hierarchical reporting chain
    $manager_epf_conditions = [];

    // Direct department cases
    if (!empty($user['department'])) {
        $regular_where_conditions[] = "employee_department = ?";
        $regular_params[] = $user['department'];
        $anonymous_where_conditions[] = "submitter_department = ?";
        $anonymous_params[] = $user['department'];
    }

    // Also include cases from subordinate supervisors (hierarchical chain)
    $subordinate_supervisors = $db->fetchAll(
        "SELECT epf_number FROM employees WHERE reports_to = ? AND account_type = 'supervisor' AND is_active = 1",
        [$user['epf_number']]
    );

    if (!empty($subordinate_supervisors)) {
        $all_subordinate_employees = [];
        foreach ($subordinate_supervisors as $supervisor) {
            $supervisor_employees = $db->fetchAll(
                "SELECT epf_number FROM employees WHERE reports_to = ? AND is_active = 1",
                [$supervisor['epf_number']]
            );
            if (!empty($supervisor_employees)) {
                $all_subordinate_employees = array_merge($all_subordinate_employees, array_column($supervisor_employees, 'epf_number'));
            }
        }

        if (!empty($all_subordinate_employees)) {
            $placeholders = str_repeat('?,', count($all_subordinate_employees) - 1) . '?';
            $regular_where_conditions[] = "employee_epf IN ($placeholders)";
            $regular_params = array_merge($regular_params, $all_subordinate_employees);
        }
    }
} elseif ($account_type === 'supervisor') {
    if (!empty($user['location']) && !empty($user['department'])) {
        $regular_where_conditions[] = "(employee_location = ? AND employee_department = ?)";
        $regular_params[] = $user['location'];
        $regular_params[] = $user['department'];
        $anonymous_where_conditions[] = "(submitter_location = ? AND submitter_department = ?)";
        $anonymous_params[] = $user['location'];
        $anonymous_params[] = $user['department'];
    }
} elseif ($account_type === 'admin' && $user_location) {
    // Admin users can only see grievances from their assigned location
    $regular_where_conditions[] = "employee_location = ?";
    $regular_params[] = $user_location;
    $anonymous_where_conditions[] = "submitter_location = ?";
    $anonymous_params[] = $user_location;
}

// Apply additional location filter (only if user is SuperAdmin)
if (!empty($location_filter) && $account_type === 'superadmin') {
    $regular_where_conditions[] = "employee_location = ?";
    $regular_params[] = $location_filter;
    $anonymous_where_conditions[] = "submitter_location = ?";
    $anonymous_params[] = $location_filter;
}

if (!empty($category_filter)) {
    $regular_where_conditions[] = "category = ?";
    $regular_params[] = $category_filter;
    $anonymous_where_conditions[] = "category = ?";
    $anonymous_params[] = $category_filter;
}

$regular_where_clause = implode(' AND ', $regular_where_conditions);
$anonymous_where_clause = implode(' AND ', $anonymous_where_conditions);

try {
    // Check if both tables exist
    $regular_table_exists = $db->fetch("SHOW TABLES LIKE 'grievances'");
    $anonymous_table_exists = $db->fetch("SHOW TABLES LIKE 'anonymous_grievances'");

    if ($regular_table_exists || $anonymous_table_exists) {
        // KPI Calculations - Combined from both tables
        $total_cases = 0;

        if ($regular_table_exists) {
            $total_cases += $db->fetch("SELECT COUNT(*) as count FROM grievances WHERE $regular_where_clause", $regular_params)['count'] ?? 0;
        }

        if ($anonymous_table_exists) {
            $total_cases += $db->fetch("SELECT COUNT(*) as count FROM anonymous_grievances WHERE $anonymous_where_clause", $anonymous_params)['count'] ?? 0;
        }

        // Open cases - Regular and Anonymous have different statuses
        $open_cases = 0;

        if ($regular_table_exists) {
            $open_cases += $db->fetch("SELECT COUNT(*) as count FROM grievances WHERE status IN ('Open', 'Under Supervisory Review', 'Under Managerial Review') AND $regular_where_clause", $regular_params)['count'] ?? 0;
        }

        if ($anonymous_table_exists) {
            $open_cases += $db->fetch("SELECT COUNT(*) as count FROM anonymous_grievances WHERE status IN ('Opened', 'Pending Team Assignment', 'Team Investigation', 'Investigation Complete', 'HR Review') AND $anonymous_where_clause", $anonymous_params)['count'] ?? 0;
        }

        // Overdue cases - Combined
        $overdue_cases = 0;

        if ($regular_table_exists) {
            $overdue_cases += $db->fetch("
                SELECT COUNT(*) as count FROM grievances
                WHERE status NOT IN ('Resolved', 'Closed')
                AND DATEDIFF(NOW(), submission_date) > 7
                AND $regular_where_clause", $regular_params)['count'] ?? 0;
        }

        if ($anonymous_table_exists) {
            $overdue_cases += $db->fetch("
                SELECT COUNT(*) as count FROM anonymous_grievances
                WHERE status NOT IN ('Resolved')
                AND DATEDIFF(NOW(), submission_date) > 7
                AND $anonymous_where_clause", $anonymous_params)['count'] ?? 0;
        }

        // Resolved cases - Combined
        $resolved_cases = 0;

        if ($regular_table_exists) {
            $resolved_cases += $db->fetch("SELECT COUNT(*) as count FROM grievances WHERE status = 'Resolved' AND $regular_where_clause", $regular_params)['count'] ?? 0;
        }

        if ($anonymous_table_exists) {
            $resolved_cases += $db->fetch("SELECT COUNT(*) as count FROM anonymous_grievances WHERE status = 'Resolved' AND $anonymous_where_clause", $anonymous_params)['count'] ?? 0;
        }

        // Average resolution time (for resolved cases) - Combined calculation
        $total_days = 0;
        $resolved_count = 0;

        if ($regular_table_exists && $resolved_cases > 0) {
            $regular_resolution = $db->fetch("
                SELECT SUM(DATEDIFF(updated_at, submission_date)) as total_days, COUNT(*) as count
                FROM grievances
                WHERE status = 'Resolved' AND $regular_where_clause", $regular_params);
            $total_days += $regular_resolution['total_days'] ?? 0;
            $resolved_count += $regular_resolution['count'] ?? 0;
        }

        if ($anonymous_table_exists && $resolved_cases > 0) {
            $anonymous_resolution = $db->fetch("
                SELECT SUM(DATEDIFF(updated_at, submission_date)) as total_days, COUNT(*) as count
                FROM anonymous_grievances
                WHERE status = 'Resolved' AND $anonymous_where_clause", $anonymous_params);
            $total_days += $anonymous_resolution['total_days'] ?? 0;
            $resolved_count += $anonymous_resolution['count'] ?? 0;
        }

        $avg_resolution = $resolved_count > 0 ? round($total_days / $resolved_count, 1) : 0;

        // SLA Compliance Rate - Combined
        $sla_compliant = 0;

        if ($regular_table_exists) {
            $sla_compliant += $db->fetch("
                SELECT COUNT(*) as count FROM grievances
                WHERE status = 'Resolved'
                AND DATEDIFF(updated_at, submission_date) <= 7
                AND $regular_where_clause", $regular_params)['count'] ?? 0;
        }

        if ($anonymous_table_exists) {
            $sla_compliant += $db->fetch("
                SELECT COUNT(*) as count FROM anonymous_grievances
                WHERE status = 'Resolved'
                AND DATEDIFF(updated_at, submission_date) <= 7
                AND $anonymous_where_clause", $anonymous_params)['count'] ?? 0;
        }

        $sla_compliance_rate = $resolved_cases > 0 ? round(($sla_compliant / $resolved_cases) * 100, 1) : 0;

        // Category breakdown - Combined
        $category_breakdown = [];

        if ($total_cases > 0) {
            // Build UNION query for category breakdown
            $union_parts = [];
            $union_params = [];

            if ($regular_table_exists) {
                $union_parts[] = "SELECT category FROM grievances WHERE $regular_where_clause";
                $union_params = array_merge($union_params, $regular_params);
            }

            if ($anonymous_table_exists) {
                $union_parts[] = "SELECT category FROM anonymous_grievances WHERE $anonymous_where_clause";
                $union_params = array_merge($union_params, $anonymous_params);
            }

            if (!empty($union_parts)) {
                $category_data = $db->fetchAll("
                    SELECT category, COUNT(*) as count,
                           ROUND(COUNT(*) * 100.0 / $total_cases, 1) as percentage
                    FROM (" . implode(" UNION ALL ", $union_parts) . ") AS combined_categories
                    GROUP BY category
                    ORDER BY count DESC", $union_params);

                $category_breakdown = $category_data;
            }
        }

        // Monthly trends (last 6 months) - Combined
        $monthly_trends = [];

        // Build UNION query for monthly trends
        $monthly_union_parts = [];
        $monthly_union_params = [];

        if ($regular_table_exists) {
            $monthly_union_parts[] = "
                SELECT DATE_FORMAT(submission_date, '%Y-%m') as month,
                       CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END as is_resolved
                FROM grievances
                WHERE submission_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                AND $regular_where_clause";
            $monthly_union_params = array_merge($monthly_union_params, $regular_params);
        }

        if ($anonymous_table_exists) {
            $monthly_union_parts[] = "
                SELECT DATE_FORMAT(submission_date, '%Y-%m') as month,
                       CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END as is_resolved
                FROM anonymous_grievances
                WHERE submission_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                AND $anonymous_where_clause";
            $monthly_union_params = array_merge($monthly_union_params, $anonymous_params);
        }

        if (!empty($monthly_union_parts)) {
            $monthly_trends = $db->fetchAll("
                SELECT month,
                       COUNT(*) as total_cases,
                       SUM(is_resolved) as resolved_cases,
                       ROUND(SUM(is_resolved) * 100.0 / COUNT(*), 1) as resolution_rate
                FROM (" . implode(" UNION ALL ", $monthly_union_parts) . ") AS combined_monthly
                GROUP BY month
                ORDER BY month DESC
                LIMIT 6", $monthly_union_params);
        }

        // Location breakdown (if user can see multiple locations) - Combined
        $location_breakdown = [];
        if (in_array($account_type, ['superadmin', 'admin']) && $total_cases > 0) {
            // Build UNION query for location breakdown
            $location_union_parts = [];
            $location_union_params = [];

            if ($regular_table_exists) {
                $location_union_parts[] = "SELECT employee_location as location FROM grievances WHERE $regular_where_clause";
                $location_union_params = array_merge($location_union_params, $regular_params);
            }

            if ($anonymous_table_exists) {
                $location_union_parts[] = "SELECT submitter_location as location FROM anonymous_grievances WHERE $anonymous_where_clause";
                $location_union_params = array_merge($location_union_params, $anonymous_params);
            }

            if (!empty($location_union_parts)) {
                $location_breakdown = $db->fetchAll("
                    SELECT location, COUNT(*) as count,
                           ROUND(COUNT(*) * 100.0 / $total_cases, 1) as percentage
                    FROM (" . implode(" UNION ALL ", $location_union_parts) . ") AS combined_locations
                    WHERE location IS NOT NULL AND location != ''
                    GROUP BY location
                    ORDER BY count DESC", $location_union_params);
            }
        }

        // Additional Analytics Data

        // Status distribution for pie chart
        $status_distribution = [];
        $status_union_parts = [];
        $status_union_params = [];

        if ($regular_table_exists) {
            $status_union_parts[] = "SELECT status FROM grievances WHERE $regular_where_clause";
            $status_union_params = array_merge($status_union_params, $regular_params);
        }

        if ($anonymous_table_exists) {
            $status_union_parts[] = "SELECT status FROM anonymous_grievances WHERE $anonymous_where_clause";
            $status_union_params = array_merge($status_union_params, $anonymous_params);
        }

        if (!empty($status_union_parts) && $total_cases > 0) {
            $status_distribution = $db->fetchAll("
                SELECT status, COUNT(*) as count,
                       ROUND(COUNT(*) * 100.0 / $total_cases, 1) as percentage
                FROM (" . implode(" UNION ALL ", $status_union_parts) . ") AS combined_status
                GROUP BY status
                ORDER BY count DESC", $status_union_params);
        }

        // Urgency breakdown
        $urgency_breakdown = [];
        $urgency_union_parts = [];
        $urgency_union_params = [];

        if ($regular_table_exists) {
            $urgency_union_parts[] = "SELECT urgency FROM grievances WHERE $regular_where_clause";
            $urgency_union_params = array_merge($urgency_union_params, $regular_params);
        }

        if ($anonymous_table_exists) {
            $urgency_union_parts[] = "SELECT urgency FROM anonymous_grievances WHERE $anonymous_where_clause";
            $urgency_union_params = array_merge($urgency_union_params, $anonymous_params);
        }

        if (!empty($urgency_union_parts) && $total_cases > 0) {
            $urgency_breakdown = $db->fetchAll("
                SELECT urgency, COUNT(*) as count,
                       ROUND(COUNT(*) * 100.0 / $total_cases, 1) as percentage
                FROM (" . implode(" UNION ALL ", $urgency_union_parts) . ") AS combined_urgency
                GROUP BY urgency
                ORDER BY
                    CASE urgency
                        WHEN 'Critical' THEN 1
                        WHEN 'High' THEN 2
                        WHEN 'Medium' THEN 3
                        WHEN 'Low' THEN 4
                        ELSE 5
                    END", $urgency_union_params);
        }

        // Case type distribution (Anonymous vs Regular)
        $case_type_distribution = [];
        if ($total_cases > 0) {
            $regular_count = 0;
            $anonymous_count = 0;

            if ($regular_table_exists) {
                $regular_count = $db->fetch("SELECT COUNT(*) as count FROM grievances WHERE $regular_where_clause", $regular_params)['count'] ?? 0;
            }

            if ($anonymous_table_exists) {
                $anonymous_count = $db->fetch("SELECT COUNT(*) as count FROM anonymous_grievances WHERE $anonymous_where_clause", $anonymous_params)['count'] ?? 0;
            }

            $case_type_distribution = [
                ['type' => 'Regular Cases', 'count' => $regular_count, 'percentage' => round(($regular_count / $total_cases) * 100, 1)],
                ['type' => 'Anonymous Cases', 'count' => $anonymous_count, 'percentage' => round(($anonymous_count / $total_cases) * 100, 1)]
            ];
        }

    } else {
        // Tables don't exist yet
        $total_cases = 0;
        $open_cases = 0;
        $overdue_cases = 0;
        $resolved_cases = 0;
        $avg_resolution = 0;
        $sla_compliance_rate = 0;
        $category_breakdown = [];
        $monthly_trends = [];
        $location_breakdown = [];
        $status_distribution = [];
        $urgency_breakdown = [];
        $case_type_distribution = [];
    }
} catch (Exception $e) {
    // Set default values on error
    $total_cases = 0;
    $open_cases = 0;
    $overdue_cases = 0;
    $resolved_cases = 0;
    $avg_resolution = 0;
    $sla_compliance_rate = 0;
    $category_breakdown = [];
    $monthly_trends = [];
    $location_breakdown = [];
    $status_distribution = [];
    $urgency_breakdown = [];
    $case_type_distribution = [];
}

// Get available categories and locations for filters
$categories = [
    'Professional (Career & Work)',
    'Financial (Money & Compensation)',
    'Behavioral (People & Conduct)',
    'Environment (Physical Workspace)',
    'Policy (Rules & Procedures)',
    'Safety (Health & Security)',
    'Discrimination (Unfair Treatment)',
    'Harassment (Inappropriate Behavior)',
    'Communication (Information & Feedback)'
];

// Restrict location options for admin users
if ($account_type === 'admin' && $user_location) {
    // Admin users only see their own location
    $locations = [$user_location => $user_location];
} else {
    // SuperAdmin and other users see all locations
    $locations = LOCATIONS;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Grievance Reports</title>
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
        }
        }
        .top-navbar h5 { color: #007bff; font-weight: 600; margin: 0; }
        @media (max-width: 768px) { .main-content { margin-left: 0 !important; } }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .bg-primary-light { background-color: rgba(0, 123, 255, 0.1); }
        .bg-success-light { background-color: rgba(40, 167, 69, 0.1); }
        .bg-warning-light { background-color: rgba(255, 193, 7, 0.1); }
        .chart-container { position: relative; height: 300px; margin-bottom: 20px; }
        .chart-container canvas { max-height: 300px !important; }
        .small-chart { height: 200px !important; }
        .metric-card { transition: transform 0.2s; }
        .metric-card:hover { transform: translateY(-2px); }
        .bg-info-light { background-color: rgba(23, 162, 184, 0.1); }
        .bg-danger-light { background-color: rgba(220, 53, 69, 0.1); }
        .text-primary { color: #007bff !important; }
        .text-success { color: #28a745 !important; }
        .text-warning { color: #ffc107 !important; }
        .text-info { color: #17a2b8 !important; }
        .text-danger { color: #dc3545 !important; }

        .report-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }

        .report-card:hover {
            transform: translateY(-2px);
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 20px;
        }

        .progress-custom {
            height: 8px;
            border-radius: 10px;
        }

        .filter-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: none;
            border-radius: 15px;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        <h1><i class="fas fa-chart-bar me-3"></i>Grievance Reports</h1>
                        <p>Generate analytics and reports on grievance trends and outcomes</p>
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
                    <li class="breadcrumb-item"><a href="index.php">Grievance Management</a></li>
                    <li class="breadcrumb-item active">Reports</li>
                </ol>
            </nav>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1><i class="fas fa-chart-bar me-2"></i>Reports & Analytics</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="index.php">Grievance Module</a></li>
                                <li class="breadcrumb-item active">Reports</li>
                            </ol>
                        </nav>
                    </div>
                    <div class="action-buttons">
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Module
                        </a>
                    </div>
                </div>
            </div>

            <!-- Filters Card -->
            <div class="form-card">
                <div class="card-header-modern">
                    <h5><i class="fas fa-filter me-2"></i>Report Filters</h5>
                </div>
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="date_from" class="form-label">From Date</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="date_to" class="form-label">To Date</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                        </div>
                        <?php if (in_array($account_type, ['superadmin', 'admin'])): ?>
                        <div class="col-md-3">
                            <label for="location_filter" class="form-label">Location</label>
                            <select class="form-select" id="location_filter" name="location">
                                <option value="">All Locations</option>
                                <?php foreach ($locations as $id => $location): ?>
                                    <option value="<?php echo $location; ?>" <?php echo $location_filter === $location ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($location); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-3">
                            <label for="category_filter" class="form-label">Category</label>
                            <select class="form-select" id="category_filter" name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category; ?>" <?php echo $category_filter === $category ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Apply Filters
                            </button>
                            <a href="reports.php" class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-times me-2"></i>Clear Filters
                            </a>
                        </div>
                    </form>
            </div>

            <!-- KPI Cards -->
            <div class="row mb-4">
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="report-card card h-100">
                        <div class="card-body text-center">
                            <div class="icon bg-primary-light text-primary mb-2" style="width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <h3 class="mb-1"><?php echo $total_cases; ?></h3>
                            <p class="text-muted mb-0">Total Cases</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="report-card card h-100">
                        <div class="card-body text-center">
                            <div class="icon bg-warning-light text-warning mb-2" style="width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h3 class="mb-1"><?php echo $open_cases; ?></h3>
                            <p class="text-muted mb-0">Open Cases</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="report-card card h-100">
                        <div class="card-body text-center">
                            <div class="icon bg-danger-light text-danger mb-2" style="width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <h3 class="mb-1"><?php echo $overdue_cases; ?></h3>
                            <p class="text-muted mb-0" title="Cases open for more than 7 days">Overdue Cases</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="report-card card h-100">
                        <div class="card-body text-center">
                            <div class="icon bg-success-light text-success mb-2" style="width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h3 class="mb-1"><?php echo round($avg_resolution, 1); ?></h3>
                            <p class="text-muted mb-0">Avg Resolution (Days)</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="report-card card h-100">
                        <div class="card-body text-center">
                            <div class="icon bg-info-light text-info mb-2" style="width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <h3 class="mb-1"><?php echo $sla_compliance_rate; ?>%</h3>
                            <p class="text-muted mb-0" title="Cases resolved within 7 days">SLA Compliance</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="report-card card h-100">
                        <div class="card-body text-center">
                            <div class="icon bg-success-light text-success mb-2" style="width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                <i class="fas fa-thumbs-up"></i>
                            </div>
                            <h3 class="mb-1"><?php echo $resolved_cases; ?></h3>
                            <p class="text-muted mb-0">Resolved Cases</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row mb-4">
                <!-- Category Breakdown Chart -->
                <div class="col-lg-6 mb-4">
                    <div class="content-card">
                        <div class="card-header-modern">
                            <h5><i class="fas fa-chart-pie me-2"></i>Category Breakdown</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="categoryChart"></canvas>
                            </div>
                            <div class="mt-3">
                                <?php foreach (array_slice($category_breakdown, 0, 5) as $category): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-truncate"><?php echo htmlspecialchars(explode('(', $category['category'])[0]); ?></span>
                                    <span class="badge bg-primary"><?php echo $category['count']; ?> (<?php echo $category['percentage']; ?>%)</span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly Trends Chart -->
                <div class="col-lg-6 mb-4">
                    <div class="content-card">
                        <div class="card-header-modern">
                            <h5><i class="fas fa-chart-line me-2"></i>Monthly Trends (Last 6 Months)</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="trendsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Location Breakdown (if applicable) -->
            <?php if (!empty($location_breakdown)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="content-card">
                        <div class="card-header-modern">
                            <h5><i class="fas fa-map-marker-alt me-2"></i>Location Breakdown</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($location_breakdown as $location): ?>
                                <div class="col-md-2 mb-3">
                                    <div class="text-center">
                                        <div class="progress progress-custom mb-2">
                                            <div class="progress-bar bg-info" style="width: <?php echo $location['percentage']; ?>%"></div>
                                        </div>
                                        <h6><?php echo $location['count']; ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($location['location'] ?? 'Unknown'); ?></small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Additional Analytics Charts -->
            <div class="row mb-4">
                <!-- Status Distribution Pie Chart -->
                <div class="col-lg-6 mb-4">
                    <div class="content-card">
                        <div class="card-header-modern">
                            <h5><i class="fas fa-chart-pie me-2"></i>Status Distribution</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container small-chart">
                                <canvas id="statusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Urgency Breakdown -->
                <div class="col-lg-6 mb-4">
                    <div class="content-card">
                        <div class="card-header-modern">
                            <h5><i class="fas fa-exclamation-triangle me-2"></i>Priority Distribution</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container small-chart">
                                <canvas id="urgencyChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Case Type Distribution -->
            <div class="row mb-4">
                <div class="col-lg-6 mb-4">
                    <div class="content-card">
                        <div class="card-header-modern">
                            <h5><i class="fas fa-user-secret me-2"></i>Case Type Distribution</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container small-chart">
                                <canvas id="caseTypeChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Resolution Performance -->
                <div class="col-lg-6 mb-4">
                    <div class="content-card">
                        <div class="card-header-modern">
                            <h5><i class="fas fa-tachometer-alt me-2"></i>Resolution Performance</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="border-end">
                                        <h3 class="text-success mb-1"><?php echo $resolved_cases; ?></h3>
                                        <small class="text-muted">Resolved Cases</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <h3 class="text-primary mb-1"><?php echo number_format($avg_resolution, 1); ?> days</h3>
                                    <small class="text-muted">Avg Resolution Time</small>
                                </div>
                            </div>
                            <div class="mt-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>SLA Compliance</span>
                                    <span class="fw-bold"><?php echo $sla_compliance_rate; ?>%</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-<?php echo $sla_compliance_rate >= 80 ? 'success' : ($sla_compliance_rate >= 60 ? 'warning' : 'danger'); ?>"
                                         style="width: <?php echo $sla_compliance_rate; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Export Options -->
            <div class="row">
                <div class="col-12">
                    <div class="form-card">
                        <div class="card-header-modern">
                            <h5><i class="fas fa-download me-2"></i>Export Reports</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <a href="#" class="btn btn-outline-primary btn-sm w-100" onclick="exportReport('summary', 'pdf')">
                                        <i class="fas fa-file-pdf me-2"></i>Summary Report (PDF)
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="#" class="btn btn-outline-success btn-sm w-100" onclick="exportReport('summary', 'excel')">
                                        <i class="fas fa-file-excel me-2"></i>Summary Report (Excel)
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="#" class="btn btn-outline-info btn-sm w-100" onclick="exportReport('detailed', 'pdf')">
                                        <i class="fas fa-file-alt me-2"></i>Detailed Report (PDF)
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="#" class="btn btn-outline-warning btn-sm w-100" onclick="exportReport('trends', 'excel')">
                                        <i class="fas fa-chart-line me-2"></i>Trends Report (Excel)
                                    </a>
                                </div>
                            </div>
                            <div class="alert alert-info mt-3 mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                Export functionality will generate comprehensive reports based on your current filters and access permissions.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        // Category Breakdown Chart
        const categoryData = <?php echo json_encode($category_breakdown); ?>;
        if (categoryData.length > 0) {
            const ctx1 = document.getElementById('categoryChart').getContext('2d');
            new Chart(ctx1, {
                type: 'doughnut',
                data: {
                    labels: categoryData.map(item => item.category.split('(')[0].trim()),
                    datasets: [{
                        data: categoryData.map(item => item.count),
                        backgroundColor: [
                            '#007bff', '#28a745', '#ffc107', '#dc3545', '#17a2b8',
                            '#6f42c1', '#fd7e14', '#20c997', '#e83e8c'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }

        // Monthly Trends Chart
        const trendsData = <?php echo json_encode($monthly_trends); ?>;
        if (trendsData.length > 0) {
            const ctx2 = document.getElementById('trendsChart').getContext('2d');
            new Chart(ctx2, {
                type: 'line',
                data: {
                    labels: trendsData.map(item => item.month).reverse(),
                    datasets: [{
                        label: 'Total Cases',
                        data: trendsData.map(item => item.total_cases).reverse(),
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Resolved Cases',
                        data: trendsData.map(item => item.resolved_cases).reverse(),
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Status Distribution Chart
        const statusData = <?php echo json_encode($status_distribution); ?>;
        if (statusData.length > 0) {
            const ctx3 = document.getElementById('statusChart').getContext('2d');
            new Chart(ctx3, {
                type: 'pie',
                data: {
                    labels: statusData.map(item => item.status),
                    datasets: [{
                        data: statusData.map(item => item.count),
                        backgroundColor: [
                            '#28a745', '#007bff', '#ffc107', '#dc3545', '#17a2b8',
                            '#6f42c1', '#fd7e14', '#20c997', '#e83e8c', '#6c757d'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Urgency Distribution Chart
        const urgencyData = <?php echo json_encode($urgency_breakdown); ?>;
        if (urgencyData.length > 0) {
            const ctx4 = document.getElementById('urgencyChart').getContext('2d');
            new Chart(ctx4, {
                type: 'bar',
                data: {
                    labels: urgencyData.map(item => item.urgency),
                    datasets: [{
                        data: urgencyData.map(item => item.count),
                        backgroundColor: urgencyData.map(item => {
                            switch(item.urgency) {
                                case 'Critical': return '#dc3545';
                                case 'High': return '#fd7e14';
                                case 'Medium': return '#ffc107';
                                case 'Low': return '#28a745';
                                default: return '#6c757d';
                            }
                        })
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }

        // Case Type Distribution Chart
        const caseTypeData = <?php echo json_encode($case_type_distribution); ?>;
        if (caseTypeData.length > 0) {
            const ctx5 = document.getElementById('caseTypeChart').getContext('2d');
            new Chart(ctx5, {
                type: 'doughnut',
                data: {
                    labels: caseTypeData.map(item => item.type),
                    datasets: [{
                        data: caseTypeData.map(item => item.count),
                        backgroundColor: ['#007bff', '#17a2b8']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Export function
        function exportReport(type, format) {
            const currentParams = new URLSearchParams(window.location.search);
            currentParams.set('export', type);
            currentParams.set('format', format);

            // For now, show alert - actual export would be implemented server-side
            alert(`Export ${type} report as ${format.toUpperCase()} - This functionality will be implemented with proper report generation.`);

            // Actual implementation would redirect to export handler:
            // window.location.href = 'reports.php?' + currentParams.toString();
        }
    </script>
</body>
</html>