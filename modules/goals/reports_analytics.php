<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

// Check if user has access to Reports & Analytics
if (!isSuperAdmin() && !hasModulePermission($db, 'goals.reports_analytics')) {
    header('Location: ../../dashboard.php');
    exit;
}

// Get available years
$years = $db->fetchAll("
    SELECT year, status FROM goal_setting_periods
    ORDER BY year DESC
");

$selected_year = $_GET['year'] ?? '';

// Analytics data
$analytics = [];
$department_stats = [];
$grade_distribution = [];
$progress_overview = [];
$detailed_records = [];

if ($selected_year) {
    $period = $db->fetch("SELECT id FROM goal_setting_periods WHERE year = ?", [$selected_year]);

    if ($period) {
        // Overall Analytics
        $analytics = $db->fetch("
            SELECT
                COUNT(*) as total_employees,
                SUM(CASE WHEN form_saved = 'Y' THEN 1 ELSE 0 END) as forms_completed,
                SUM(CASE WHEN mid_year_progress = 'Y' THEN 1 ELSE 0 END) as mid_year_completed,
                SUM(CASE WHEN final_evaluation = 'Y' THEN 1 ELSE 0 END) as final_evaluations,
                ROUND(AVG(CASE WHEN form_saved = 'Y' THEN 100 ELSE 0 END), 2) as completion_rate,
                ROUND(AVG(CASE WHEN mid_year_progress = 'Y' THEN 100 ELSE 0 END), 2) as mid_year_rate,
                ROUND(AVG(CASE WHEN final_evaluation = 'Y' THEN 100 ELSE 0 END), 2) as final_evaluation_rate
            FROM executive_goal_records r
            WHERE r.period_id = ?
        ", [$period['id']]) ?: [];

        // Department-wise Statistics
        $department_stats = $db->fetchAll("
            SELECT
                e.department,
                COUNT(*) as total_employees,
                SUM(CASE WHEN r.form_saved = 'Y' THEN 1 ELSE 0 END) as forms_completed,
                SUM(CASE WHEN r.mid_year_progress = 'Y' THEN 1 ELSE 0 END) as mid_year_completed,
                SUM(CASE WHEN r.final_evaluation = 'Y' THEN 1 ELSE 0 END) as final_evaluations,
                ROUND(AVG(CASE WHEN r.form_saved = 'Y' THEN 100 ELSE 0 END), 1) as completion_rate,
                ROUND(AVG(CASE WHEN r.final_evaluation = 'Y' THEN 100 ELSE 0 END), 1) as final_evaluation_rate
            FROM executive_goal_records r
            JOIN employees e ON r.employee_epf = e.epf_number
            WHERE r.period_id = ?
            GROUP BY e.department
            ORDER BY e.department
        ", [$period['id']]);

        // Grade Distribution
        $grade_distribution = $db->fetchAll("
            SELECT
                final_performance_grade as grade,
                COUNT(*) as count,
                ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM executive_goal_records WHERE period_id = ? AND final_performance_grade IS NOT NULL), 1) as percentage
            FROM executive_goal_records
            WHERE period_id = ? AND final_performance_grade IS NOT NULL
            GROUP BY final_performance_grade
            ORDER BY final_performance_grade
        ", [$period['id'], $period['id']]);

        // Progress Overview (Mid-Year Status)
        $progress_overview = $db->fetchAll("
            SELECT
                mid_year_status,
                COUNT(*) as count,
                ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM executive_goal_records WHERE period_id = ? AND mid_year_status IS NOT NULL), 1) as percentage
            FROM executive_goal_records
            WHERE period_id = ? AND mid_year_status IS NOT NULL
            GROUP BY mid_year_status
            ORDER BY mid_year_status
        ", [$period['id'], $period['id']]);

        // Detailed Records for Export/View
        $detailed_records = $db->fetchAll("
            SELECT
                r.employee_epf,
                e.name as employee_name,
                e.designation,
                e.department,
                COALESCE(l.location_name, 'Not Set') as location,
                m.name as manager_name,
                r.form_saved,
                r.mid_year_progress,
                r.mid_year_status,
                r.final_evaluation,
                r.final_performance_grade,
                r.created_date,
                r.employee_agreement_date,
                r.manager_agreement_date
            FROM executive_goal_records r
            JOIN employees e ON r.employee_epf = e.epf_number
            LEFT JOIN locations l ON e.location_id = l.id
            JOIN employees m ON r.manager_epf = m.epf_number
            WHERE r.period_id = ?
            ORDER BY e.department, e.name
        ", [$period['id']]);
    }
}

function getGradeBadgeClass($grade) {
    switch (strtoupper($grade)) {
        case 'A': return 'bg-success';
        case 'B': return 'bg-primary';
        case 'C': return 'bg-warning text-dark';
        case 'D': return 'bg-danger';
        default: return 'bg-secondary';
    }
}

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'Progressing Well': return 'bg-success';
        case 'Need Improvements': return 'bg-warning text-dark';
        case 'Below Expectations': return 'bg-danger';
        default: return 'bg-secondary';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Goal Setting Reports & Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
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

        .stats-card.success .icon {
            background: linear-gradient(135deg, var(--success-color), #1e7e34);
            color: white;
        }

        .stats-card.warning .icon {
            background: linear-gradient(135deg, var(--warning-color), #e0a800);
            color: white;
        }

        .stats-card.info .icon {
            background: linear-gradient(135deg, var(--info-color), #138496);
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

        .chart-container {
            position: relative;
            height: 300px;
            margin: 1rem 0;
        }

        .progress-custom {
            height: 25px;
            border-radius: 15px;
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
                        <h1><i class="fas fa-chart-pie me-3"></i>Goal Setting Reports & Analytics</h1>
                        <p>Comprehensive goal setting reports, analytics, and performance insights</p>
                    </div>
                    <div class="logo-container">
                        <img src="../../jiffy-logo.svg" alt="Jiffy Logo" class="page-header-logo">
                    </div>
                </div>
            </div>

            <!-- Year Selection -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-calendar me-2"></i>Select Year for Analytics</h5>
                        </div>
                        <div class="card-body">
                            <form method="GET">
                                <div class="mb-3">
                                    <select name="year" class="form-select" onchange="this.form.submit()">
                                        <option value="">Choose Year...</option>
                                        <?php foreach ($years as $year_data): ?>
                                            <option value="<?= $year_data['year'] ?>" <?= $selected_year == $year_data['year'] ? 'selected' : '' ?>>
                                                <?= $year_data['year'] ?> (<?= ucfirst($year_data['status']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <?php if ($selected_year && !empty($analytics)): ?>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-download me-2"></i>Export Options</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button class="btn btn-outline-success" onclick="exportToCSV()">
                                    <i class="fas fa-file-csv me-2"></i>Export to CSV
                                </button>
                                <button class="btn btn-outline-primary" onclick="window.print()">
                                    <i class="fas fa-print me-2"></i>Print Report
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($selected_year && !empty($analytics)): ?>

            <!-- Overall Statistics -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card primary">
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3><?= number_format($analytics['total_employees']) ?></h3>
                        <p>Total Employees</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card success">
                        <div class="icon">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <h3><?= number_format($analytics['forms_completed']) ?></h3>
                        <p>Forms Completed</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card warning">
                        <div class="icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <h3><?= number_format($analytics['mid_year_completed']) ?></h3>
                        <p>Mid-Year Reviews</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card info">
                        <div class="icon">
                            <i class="fas fa-award"></i>
                        </div>
                        <h3><?= number_format($analytics['final_evaluations']) ?></h3>
                        <p>Final Evaluations</p>
                    </div>
                </div>
            </div>

            <!-- Progress Overview -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Completion Progress</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <small>Form Completion</small>
                                    <small><?= $analytics['completion_rate'] ?>%</small>
                                </div>
                                <div class="progress progress-custom">
                                    <div class="progress-bar bg-success" style="width: <?= $analytics['completion_rate'] ?>%"></div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <small>Mid-Year Reviews</small>
                                    <small><?= $analytics['mid_year_rate'] ?>%</small>
                                </div>
                                <div class="progress progress-custom">
                                    <div class="progress-bar bg-warning" style="width: <?= $analytics['mid_year_rate'] ?>%"></div>
                                </div>
                            </div>
                            <div class="mb-0">
                                <div class="d-flex justify-content-between">
                                    <small>Final Evaluations</small>
                                    <small><?= $analytics['final_evaluation_rate'] ?>%</small>
                                </div>
                                <div class="progress progress-custom">
                                    <div class="progress-bar bg-info" style="width: <?= $analytics['final_evaluation_rate'] ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($grade_distribution)): ?>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Grade Distribution</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="gradeChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Mid-Year Status Overview -->
            <?php if (!empty($progress_overview)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Mid-Year Progress Status</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($progress_overview as $status): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="text-center p-3 border rounded">
                                        <span class="badge <?= getStatusBadgeClass($status['mid_year_status']) ?> fs-6 mb-2">
                                            <?= htmlspecialchars($status['mid_year_status']) ?>
                                        </span>
                                        <h4 class="mb-1"><?= $status['count'] ?></h4>
                                        <small class="text-muted"><?= $status['percentage'] ?>% of total</small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Department-wise Statistics -->
            <?php if (!empty($department_stats)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-building me-2"></i>Department-wise Statistics</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Department</th>
                                            <th>Total Employees</th>
                                            <th>Forms Completed</th>
                                            <th>Mid-Year Reviews</th>
                                            <th>Final Evaluations</th>
                                            <th>Completion Rate</th>
                                            <th>Final Evaluation Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($department_stats as $dept): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($dept['department']) ?></strong></td>
                                            <td><?= $dept['total_employees'] ?></td>
                                            <td><?= $dept['forms_completed'] ?></td>
                                            <td><?= $dept['mid_year_completed'] ?></td>
                                            <td><?= $dept['final_evaluations'] ?></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-success" style="width: <?= $dept['completion_rate'] ?>%">
                                                        <?= $dept['completion_rate'] ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-info" style="width: <?= $dept['final_evaluation_rate'] ?>%">
                                                        <?= $dept['final_evaluation_rate'] ?>%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Detailed Records -->
            <?php if (!empty($detailed_records)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Detailed Employee Records - <?= $selected_year ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="detailedTable" class="table table-striped table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>EPF</th>
                                            <th>Name</th>
                                            <th>Designation</th>
                                            <th>Department</th>
                                            <th>Manager</th>
                                            <th>Form Saved</th>
                                            <th>Mid-Year Status</th>
                                            <th>Final Grade</th>
                                            <th>Created Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($detailed_records as $record): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($record['employee_epf']) ?></td>
                                            <td><?= htmlspecialchars($record['employee_name']) ?></td>
                                            <td><?= htmlspecialchars($record['designation']) ?></td>
                                            <td><?= htmlspecialchars($record['department']) ?></td>
                                            <td><?= htmlspecialchars($record['manager_name']) ?></td>
                                            <td>
                                                <span class="badge <?= $record['form_saved'] == 'Y' ? 'bg-success' : 'bg-danger' ?>">
                                                    <?= $record['form_saved'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($record['mid_year_status']): ?>
                                                    <span class="badge <?= getStatusBadgeClass($record['mid_year_status']) ?>">
                                                        <?= htmlspecialchars($record['mid_year_status']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($record['final_performance_grade']): ?>
                                                    <span class="badge <?= getGradeBadgeClass($record['final_performance_grade']) ?>">
                                                        <?= htmlspecialchars($record['final_performance_grade']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= date('M d, Y', strtotime($record['created_date'])) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php elseif ($selected_year): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                No data found for year <?= $selected_year ?>. Please ensure goal setting records exist for this year.
            </div>
            <?php endif; ?>

            <!-- Navigation -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Goal Setting
                        </a>
                        <a href="kpi_tracker.php" class="btn btn-primary">
                            <i class="fas fa-tasks me-2"></i>KPI Tracker
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#detailedTable').DataTable({
                responsive: true,
                pageLength: 25,
                order: [[3, 'asc'], [1, 'asc']], // Order by department, then name
                dom: 'Bfrtip',
                buttons: [
                    'copy', 'csv', 'excel', 'pdf', 'print'
                ]
            });

            // Grade Distribution Chart
            <?php if (!empty($grade_distribution)): ?>
            const gradeCtx = document.getElementById('gradeChart').getContext('2d');
            const gradeChart = new Chart(gradeCtx, {
                type: 'doughnut',
                data: {
                    labels: [
                        <?php foreach ($grade_distribution as $grade): ?>
                            '<?= $grade['grade'] ?> Grade (<?= $grade['percentage'] ?>%)',
                        <?php endforeach; ?>
                    ],
                    datasets: [{
                        data: [
                            <?php foreach ($grade_distribution as $grade): ?>
                                <?= $grade['count'] ?>,
                            <?php endforeach; ?>
                        ],
                        backgroundColor: [
                            <?php foreach ($grade_distribution as $grade): ?>
                                '<?php
                                    switch ($grade['grade']) {
                                        case 'A': echo '#28a745';
                                            break;
                                        case 'B': echo '#007bff';
                                            break;
                                        case 'C': echo '#ffc107';
                                            break;
                                        case 'D': echo '#dc3545';
                                            break;
                                        default: echo '#6c757d';
                                    }
                                ?>',
                            <?php endforeach; ?>
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
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
            <?php endif; ?>
        });

        // Export to CSV function
        function exportToCSV() {
            <?php if (!empty($detailed_records)): ?>
            const data = [
                ['EPF', 'Name', 'Designation', 'Department', 'Location', 'Manager', 'Form Saved', 'Mid-Year Progress', 'Mid-Year Status', 'Final Evaluation', 'Final Grade', 'Created Date'],
                <?php foreach ($detailed_records as $record): ?>
                [
                    '<?= htmlspecialchars($record['employee_epf']) ?>',
                    '<?= htmlspecialchars($record['employee_name']) ?>',
                    '<?= htmlspecialchars($record['designation']) ?>',
                    '<?= htmlspecialchars($record['department']) ?>',
                    '<?= htmlspecialchars($record['location']) ?>',
                    '<?= htmlspecialchars($record['manager_name']) ?>',
                    '<?= $record['form_saved'] ?>',
                    '<?= $record['mid_year_progress'] ?>',
                    '<?= htmlspecialchars($record['mid_year_status'] ?? '') ?>',
                    '<?= $record['final_evaluation'] ?>',
                    '<?= htmlspecialchars($record['final_performance_grade'] ?? '') ?>',
                    '<?= date('Y-m-d', strtotime($record['created_date'])) ?>'
                ],
                <?php endforeach; ?>
            ];

            const csvContent = data.map(row => row.map(field => `"${field}"`).join(',')).join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'goal_setting_report_<?= $selected_year ?>.csv';
            a.click();
            window.URL.revokeObjectURL(url);
            <?php endif; ?>
        }

        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    </script>
</body>
</html>