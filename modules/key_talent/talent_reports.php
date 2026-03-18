<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

$user = getCurrentUser();
$database = new Database();

// Check if user has reports permission
if (!isSuperAdmin() && !hasModulePermission($database, 'key_talent.reports')) {
    header('Location: index.php');
    exit();
}

// Get available years
try {
    $years_query = "SELECT DISTINCT f.year FROM kti_forms f
                    JOIN kti_assessments a ON f.id = a.form_id
                    ORDER BY f.year DESC";
    $available_years = $database->fetchAll($years_query);
} catch (Exception $e) {
    $available_years = [];
}

// Get selected year and manager filter
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : (count($available_years) > 0 ? $available_years[0]['year'] : date('Y'));
$manager_filter = isset($_GET['manager_epf']) ? trim($_GET['manager_epf']) : '';

// Get list of managers for filter
try {
    $managers_query = "SELECT DISTINCT manager_epf, manager_name
                      FROM kti_assessments a
                      JOIN kti_forms f ON a.form_id = f.id
                      WHERE f.year = ?
                      ORDER BY manager_name";
    $available_managers = $database->fetchAll($managers_query, [$selected_year]);
} catch (Exception $e) {
    $available_managers = [];
}

// Apply role-based filtering
if ($user['account_type'] === 'manager') {
    $manager_filter = $user['epf_number'];
}

// Get assessment data for reports
try {
    $assessments_query = "
        SELECT a.*, f.year, f.part_a_title, f.part_b_title
        FROM kti_assessments a
        JOIN kti_forms f ON a.form_id = f.id
        WHERE f.year = ?";

    $params = [$selected_year];

    if ($manager_filter) {
        $assessments_query .= " AND a.manager_epf = ?";
        $params[] = $manager_filter;
    }

    $assessments_query .= " ORDER BY a.part_a_total_score DESC, a.part_b_total_score DESC";
    $assessments = $database->fetchAll($assessments_query, $params);

    // Calculate statistics
    $total_assessments = count($assessments);
    $departments = array_unique(array_column($assessments, 'employee_department'));
    $total_departments = count($departments);

    // Group by talent categories
    $talent_categories = [
        'High_High' => [],      // The Unicorn
        'High_Moderate' => [],  // The Veteran
        'High_Low' => [],       // Workhorse
        'Moderate_High' => [],  // The Rocket
        'Moderate_Moderate' => [], // The Backbone
        'Moderate_Low' => [],   // The Settler
        'Low_High' => [],       // Dysfunctional Genius
        'Low_Moderate' => [],   // The Sleeping Giant
        'Low_Low' => []         // The Wake-Up Call
    ];

    // Group by departments
    $department_stats = [];

    foreach ($assessments as $assessment) {
        $key = $assessment['performance_category'] . '_' . $assessment['potential_category'];
        if (isset($talent_categories[$key])) {
            $talent_categories[$key][] = $assessment;
        }

        $dept = $assessment['employee_department'];
        if (!isset($department_stats[$dept])) {
            $department_stats[$dept] = [
                'total' => 0,
                'avg_performance' => 0,
                'avg_potential' => 0,
                'high_performers' => 0,
                'high_potential' => 0
            ];
        }

        $department_stats[$dept]['total']++;
        $department_stats[$dept]['avg_performance'] += $assessment['part_a_total_score'];
        $department_stats[$dept]['avg_potential'] += $assessment['part_b_total_score'];

        if ($assessment['part_a_total_score'] >= 76) {
            $department_stats[$dept]['high_performers']++;
        }
        if ($assessment['part_b_total_score'] >= 76) {
            $department_stats[$dept]['high_potential']++;
        }
    }

    // Calculate averages
    foreach ($department_stats as $dept => &$stats) {
        if ($stats['total'] > 0) {
            $stats['avg_performance'] = round($stats['avg_performance'] / $stats['total'], 1);
            $stats['avg_potential'] = round($stats['avg_potential'] / $stats['total'], 1);
        }
    }

    // Top performers
    $top_performers = array_slice(
        array_filter($assessments, function($a) { return $a['part_a_total_score'] >= 76; }),
        0, 10
    );

    // High potential employees
    $high_potential = array_slice(
        array_filter($assessments, function($a) { return $a['part_b_total_score'] >= 76; }),
        0, 10
    );

} catch (Exception $e) {
    $assessments = [];
    $talent_categories = [];
    $department_stats = [];
    $top_performers = [];
    $high_potential = [];
    $error_message = "Error loading report data: " . $e->getMessage();
}

// Handle report exports
if (isset($_POST['export_type'])) {
    $export_type = $_POST['export_type'];

    try {
        switch ($export_type) {
            case 'summary_csv':
                exportSummaryCSV($assessments, $selected_year, $manager_filter);
                break;
            case 'detailed_csv':
                exportDetailedCSV($assessments, $selected_year, $manager_filter);
                break;
            case 'department_csv':
                exportDepartmentCSV($department_stats, $selected_year, $manager_filter);
                break;
        }
    } catch (Exception $e) {
        $error_message = "Export error: " . $e->getMessage();
    }
}

// Export functions
function exportSummaryCSV($assessments, $year, $manager_filter) {
    $filename = "talent_summary_{$year}" . ($manager_filter ? "_manager_{$manager_filter}" : "") . ".csv";

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // Header
    fputcsv($output, [
        'Employee EPF', 'Employee Name', 'Designation', 'Department',
        'Performance Score', 'Potential Score', 'Talent Category', 'Manager'
    ]);

    // Data
    foreach ($assessments as $assessment) {
        fputcsv($output, [
            $assessment['employee_epf'],
            $assessment['employee_name'],
            $assessment['employee_designation'],
            $assessment['employee_department'],
            $assessment['part_a_total_score'] . '%',
            $assessment['part_b_total_score'] . '%',
            $assessment['talent_box_category'],
            $assessment['manager_name']
        ]);
    }

    fclose($output);
    exit;
}

function exportDetailedCSV($assessments, $year, $manager_filter) {
    $filename = "talent_detailed_{$year}" . ($manager_filter ? "_manager_{$manager_filter}" : "") . ".csv";

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // Header
    fputcsv($output, [
        'Employee EPF', 'Employee Name', 'Designation', 'Department', 'Location',
        'Joining Date', 'Service Years', 'Performance Category', 'Performance Score',
        'Potential Category', 'Potential Score', 'Talent Box Category',
        'Manager EPF', 'Manager Name', 'Assessment Date'
    ]);

    // Data
    foreach ($assessments as $assessment) {
        fputcsv($output, [
            $assessment['employee_epf'],
            $assessment['employee_name'],
            $assessment['employee_designation'],
            $assessment['employee_department'],
            $assessment['employee_location'],
            $assessment['employee_joining_date'],
            $assessment['employee_service_years'],
            $assessment['performance_category'],
            $assessment['part_a_total_score'] . '%',
            $assessment['potential_category'],
            $assessment['part_b_total_score'] . '%',
            $assessment['talent_box_category'],
            $assessment['manager_epf'],
            $assessment['manager_name'],
            date('Y-m-d', strtotime($assessment['submitted_at']))
        ]);
    }

    fclose($output);
    exit;
}

function exportDepartmentCSV($department_stats, $year, $manager_filter) {
    $filename = "department_analysis_{$year}" . ($manager_filter ? "_manager_{$manager_filter}" : "") . ".csv";

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // Header
    fputcsv($output, [
        'Department', 'Total Employees', 'Average Performance', 'Average Potential',
        'High Performers', 'High Potential', 'Performance Rate', 'Potential Rate'
    ]);

    // Data
    foreach ($department_stats as $dept => $stats) {
        $perf_rate = $stats['total'] > 0 ? round(($stats['high_performers'] / $stats['total']) * 100, 1) : 0;
        $pot_rate = $stats['total'] > 0 ? round(($stats['high_potential'] / $stats['total']) * 100, 1) : 0;

        fputcsv($output, [
            $dept,
            $stats['total'],
            $stats['avg_performance'] . '%',
            $stats['avg_potential'] . '%',
            $stats['high_performers'],
            $stats['high_potential'],
            $perf_rate . '%',
            $pot_rate . '%'
        ]);
    }

    fclose($output);
    exit;
}

// Category metadata
$category_info = [
    'High_High' => ['title' => 'The Unicorn', 'color' => 'primary', 'emoji' => '🦄'],
    'High_Moderate' => ['title' => 'The Veteran', 'color' => 'info', 'emoji' => '🎖️'],
    'High_Low' => ['title' => 'Workhorse', 'color' => 'secondary', 'emoji' => '🐴'],
    'Moderate_High' => ['title' => 'The Rocket', 'color' => 'success', 'emoji' => '🚀'],
    'Moderate_Moderate' => ['title' => 'The Backbone', 'color' => 'light', 'emoji' => '🏛️'],
    'Moderate_Low' => ['title' => 'The Settler', 'color' => 'warning', 'emoji' => '🛋️'],
    'Low_High' => ['title' => 'Dysfunctional Genius', 'color' => 'danger', 'emoji' => '🌀'],
    'Low_Moderate' => ['title' => 'The Sleeping Giant', 'color' => 'dark', 'emoji' => '😴'],
    'Low_Low' => ['title' => 'The Wake-Up Call', 'color' => 'danger', 'emoji' => '⏰']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Talent Reports & Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.css" rel="stylesheet">
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

        .breadcrumb {
            background: transparent;
            padding: 0;
        }

        .breadcrumb-item a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
        }

        .breadcrumb-item.active {
            color: white;
        }

        .content-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 2rem;
        }

        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #007bff;
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-2px);
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #007bff;
            line-height: 1;
        }

        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .export-section {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .chart-container {
            position: relative;
            height: 400px;
            margin: 1rem 0;
        }

        .table-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .category-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .btn {
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
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

            .stats-number {
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
            <!-- Page Header -->
            <div class="page-header">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="header-content">
                        <h1><i class="fas fa-chart-line me-3"></i>Talent Reports & Analytics</h1>
                        <p>Comprehensive talent analytics and exportable reports</p>
                    </div>
                    <div class="logo-container">
                        <img src="../../jiffy-logo.svg" alt="Jiffy Logo" class="page-header-logo">
                    </div>
                </div>
            </div>

            <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="filter-section">
                <h5 class="mb-3"><i class="fas fa-filter me-2"></i>Report Filters</h5>
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="year" class="form-label">Assessment Year</label>
                        <select name="year" id="year" class="form-control" onchange="this.form.submit()">
                            <?php foreach ($available_years as $year): ?>
                            <option value="<?php echo $year['year']; ?>" <?php echo $year['year'] == $selected_year ? 'selected' : ''; ?>>
                                <?php echo $year['year']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($user['account_type'] === 'superadmin'): ?>
                    <div class="col-md-4">
                        <label for="manager_epf" class="form-label">Manager Filter</label>
                        <select name="manager_epf" id="manager_epf" class="form-control" onchange="this.form.submit()">
                            <option value="">All Managers</option>
                            <?php foreach ($available_managers as $manager): ?>
                            <option value="<?php echo $manager['manager_epf']; ?>" <?php echo $manager['manager_epf'] == $manager_filter ? 'selected' : ''; ?>>
                                EPF <?php echo $manager['manager_epf']; ?> - <?php echo htmlspecialchars($manager['manager_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="button" class="btn btn-outline-secondary" onclick="location.href='talent_reports.php'">
                                <i class="fas fa-times me-1"></i>Clear Filters
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <?php if (!empty($assessments)): ?>
            <!-- Summary Statistics -->
            <div class="row">
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo $total_assessments; ?></div>
                        <div class="stats-label">Total Assessments</div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo $total_departments; ?></div>
                        <div class="stats-label">Departments Covered</div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo count($talent_categories['High_High']); ?></div>
                        <div class="stats-label">High-Performers (Unicorns)</div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo count($talent_categories['Moderate_High']); ?></div>
                        <div class="stats-label">High Potential (Rockets)</div>
                    </div>
                </div>
            </div>

            <!-- Export Section -->
            <div class="export-section">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-2"><i class="fas fa-download me-2"></i>Export Reports</h5>
                        <p class="mb-0">Download comprehensive talent data in CSV format for further analysis</p>
                    </div>
                    <div class="col-md-6 text-end">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="year" value="<?php echo $selected_year; ?>">
                            <input type="hidden" name="manager_epf" value="<?php echo $manager_filter; ?>">

                            <button type="submit" name="export_type" value="summary_csv" class="btn btn-light me-2">
                                <i class="fas fa-table me-1"></i>Summary CSV
                            </button>
                            <button type="submit" name="export_type" value="detailed_csv" class="btn btn-light me-2">
                                <i class="fas fa-list me-1"></i>Detailed CSV
                            </button>
                            <button type="submit" name="export_type" value="department_csv" class="btn btn-light">
                                <i class="fas fa-building me-1"></i>Department CSV
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row">
                <div class="col-md-6">
                    <div class="content-card">
                        <h6><i class="fas fa-chart-pie me-2"></i>Talent Distribution</h6>
                        <div class="chart-container">
                            <canvas id="talentDistributionChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="content-card">
                        <h6><i class="fas fa-chart-bar me-2"></i>Department Performance</h6>
                        <div class="chart-container">
                            <canvas id="departmentChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Talent Categories Breakdown -->
            <div class="content-card">
                <h6><i class="fas fa-th-large me-2"></i>Talent Categories Breakdown</h6>
                <div class="row">
                    <?php foreach ($talent_categories as $key => $employees): ?>
                    <?php
                    $info = $category_info[$key];
                    $count = count($employees);
                    $percentage = $total_assessments > 0 ? round(($count / $total_assessments) * 100, 1) : 0;
                    ?>
                    <div class="col-lg-4 col-md-6 mb-3">
                        <div class="card border-<?php echo $info['color']; ?>">
                            <div class="card-body text-center">
                                <div style="font-size: 2rem;"><?php echo $info['emoji']; ?></div>
                                <h6 class="card-title mt-2"><?php echo $info['title']; ?></h6>
                                <div class="h4 text-<?php echo $info['color']; ?>"><?php echo $count; ?></div>
                                <small class="text-muted"><?php echo $percentage; ?>% of total</small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Department Analysis -->
            <?php if (!empty($department_stats)): ?>
            <div class="table-container">
                <h6><i class="fas fa-building me-2"></i>Department Analysis</h6>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Total</th>
                                <th>Avg Performance</th>
                                <th>Avg Potential</th>
                                <th>High Performers</th>
                                <th>High Potential</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($department_stats as $dept => $stats): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($dept); ?></strong></td>
                                <td><?php echo $stats['total']; ?></td>
                                <td>
                                    <span class="badge bg-primary"><?php echo $stats['avg_performance']; ?>%</span>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $stats['avg_potential']; ?>%</span>
                                </td>
                                <td>
                                    <?php echo $stats['high_performers']; ?>
                                    <small class="text-muted">
                                        (<?php echo $stats['total'] > 0 ? round(($stats['high_performers'] / $stats['total']) * 100, 1) : 0; ?>%)
                                    </small>
                                </td>
                                <td>
                                    <?php echo $stats['high_potential']; ?>
                                    <small class="text-muted">
                                        (<?php echo $stats['total'] > 0 ? round(($stats['high_potential'] / $stats['total']) * 100, 1) : 0; ?>%)
                                    </small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Top Performers & High Potential Lists -->
            <div class="row">
                <?php if (!empty($top_performers)): ?>
                <div class="col-md-6">
                    <div class="table-container">
                        <h6><i class="fas fa-star me-2"></i>Top Performers (Performance ≥ 76%)</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Score</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($top_performers, 0, 10) as $emp): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($emp['employee_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($emp['employee_designation']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($emp['employee_department']); ?></td>
                                        <td>
                                            <span class="badge bg-success"><?php echo $emp['part_a_total_score']; ?>%</span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($high_potential)): ?>
                <div class="col-md-6">
                    <div class="table-container">
                        <h6><i class="fas fa-rocket me-2"></i>High Potential (Potential ≥ 76%)</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Score</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($high_potential, 0, 10) as $emp): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($emp['employee_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($emp['employee_designation']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($emp['employee_department']); ?></td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $emp['part_b_total_score']; ?>%</span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php else: ?>
            <!-- Empty State -->
            <div class="content-card">
                <div class="text-center py-5">
                    <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Report Data Available</h5>
                    <p class="text-muted">
                        No assessments have been completed for <?php echo $selected_year; ?>.
                        Complete some assessments to generate reports.
                    </p>
                    <a href="assessment_form.php" class="btn btn-success">
                        <i class="fas fa-plus me-2"></i>Start Assessment
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script>
        <?php if (!empty($assessments)): ?>
        // Talent Distribution Pie Chart
        const talentDistData = {
            labels: [
                <?php foreach ($talent_categories as $key => $employees): ?>
                '<?php echo $category_info[$key]['title']; ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                data: [
                    <?php foreach ($talent_categories as $key => $employees): ?>
                    <?php echo count($employees); ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: [
                    '#007bff', '#17a2b8', '#6c757d', '#28a745', '#f8f9fa',
                    '#ffc107', '#dc3545', '#343a40', '#dc3545'
                ]
            }]
        };

        new Chart(document.getElementById('talentDistributionChart'), {
            type: 'doughnut',
            data: talentDistData,
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

        // Department Performance Chart
        const deptData = {
            labels: [
                <?php foreach ($department_stats as $dept => $stats): ?>
                '<?php echo addslashes($dept); ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                label: 'Avg Performance',
                data: [
                    <?php foreach ($department_stats as $dept => $stats): ?>
                    <?php echo $stats['avg_performance']; ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: 'rgba(0, 123, 255, 0.8)'
            }, {
                label: 'Avg Potential',
                data: [
                    <?php foreach ($department_stats as $dept => $stats): ?>
                    <?php echo $stats['avg_potential']; ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: 'rgba(23, 162, 184, 0.8)'
            }]
        };

        new Chart(document.getElementById('departmentChart'), {
            type: 'bar',
            data: deptData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>