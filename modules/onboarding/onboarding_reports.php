<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

// Check if user is superadmin
$user = getCurrentUser();
if ($user['account_type'] !== 'superadmin') {
    header('Location: ../../dashboard.php');
    exit();
}

$database = new Database();

// Get date filters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Today

// Overall Statistics
$total_onboarding = $database->fetchAll("
    SELECT COUNT(*) as count FROM onboarding_tracker
    WHERE onboarding_start_date BETWEEN ? AND ?
", [$start_date, $end_date])[0]['count'];

$completed_onboarding = $database->fetchAll("
    SELECT COUNT(*) as count FROM onboarding_tracker
    WHERE is_completed = 1 AND onboarding_start_date BETWEEN ? AND ?
", [$start_date, $end_date])[0]['count'];

$pending_onboarding = $database->fetchAll("
    SELECT COUNT(*) as count FROM onboarding_tracker
    WHERE is_completed = 0 AND onboarding_start_date BETWEEN ? AND ?
", [$start_date, $end_date])[0]['count'];

$avg_completion_time = $database->fetchAll("
    SELECT AVG(DATEDIFF(completion_date, onboarding_start_date)) as avg_days
    FROM onboarding_tracker
    WHERE is_completed = 1 AND onboarding_start_date BETWEEN ? AND ?
", [$start_date, $end_date])[0]['avg_days'] ?? 0;

// Completion Rate by Department
$dept_stats = $database->fetchAll("
    SELECT
        employee_department,
        COUNT(*) as total,
        SUM(is_completed) as completed,
        ROUND((SUM(is_completed) / COUNT(*)) * 100, 2) as completion_rate,
        AVG(completion_percentage) as avg_progress
    FROM onboarding_tracker
    WHERE onboarding_start_date BETWEEN ? AND ?
    GROUP BY employee_department
    ORDER BY completion_rate DESC
", [$start_date, $end_date]);

// Hire Type Distribution
$hire_type_stats = $database->fetchAll("
    SELECT
        hire_type,
        COUNT(*) as count,
        SUM(is_completed) as completed,
        ROUND((SUM(is_completed) / COUNT(*)) * 100, 2) as completion_rate
    FROM onboarding_tracker
    WHERE onboarding_start_date BETWEEN ? AND ?
    GROUP BY hire_type
", [$start_date, $end_date]);

// Monthly Trends (last 6 months)
$monthly_trends = $database->fetchAll("
    SELECT
        DATE_FORMAT(onboarding_start_date, '%Y-%m') as month,
        COUNT(*) as started,
        SUM(is_completed) as completed
    FROM onboarding_tracker
    WHERE onboarding_start_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(onboarding_start_date, '%Y-%m')
    ORDER BY month DESC
");

// Checklist Item Completion Rates
$checklist_completion = $database->fetchAll("
    SELECT
        SUM(safety_induction) as safety_induction,
        SUM(code_of_conduct) as code_of_conduct,
        SUM(training_evaluation) as training_evaluation,
        SUM(performance_evaluation) as performance_evaluation,
        SUM(agreement) as agreement,
        SUM(non_compete_agreement) as non_compete_agreement,
        SUM(medical_insurance_letter) as medical_insurance_letter,
        SUM(confirmation_letter) as confirmation_letter,
        COUNT(*) as total
    FROM onboarding_tracker
    WHERE onboarding_start_date BETWEEN ? AND ?
", [$start_date, $end_date])[0];

$checklist_items = [
    'safety_induction' => 'Safety Induction',
    'code_of_conduct' => 'Code of Conduct',
    'training_evaluation' => 'Training Evaluation',
    'performance_evaluation' => 'Probation Evaluation',
    'agreement' => 'Agreement',
    'non_compete_agreement' => 'Non-Compete Agreement',
    'medical_insurance_letter' => 'Medical Insurance Letter',
    'confirmation_letter' => 'Confirmation Letter'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Onboarding Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
            z-index: 1;
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

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            height: 100%;
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

        .chart-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 2rem;
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        .chart-container.small {
            height: 200px;
        }

        .chart-card canvas {
            max-height: 300px !important;
            width: 100% !important;
            height: auto !important;
        }

        .chart-container.small canvas {
            max-height: 200px !important;
        }

        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .table th {
            background-color: #f8f9fa;
            border: none;
            font-weight: 600;
            color: #2c3e50;
        }

        .table td {
            border: none;
            vertical-align: middle;
        }

        .progress-bar {
            border-radius: 10px;
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
                        <h1><i class="fas fa-chart-bar me-3"></i>Onboarding Reports</h1>
                        <p>Generate analytics and reports on onboarding effectiveness and completion rates</p>
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
                    <li class="breadcrumb-item"><a href="index.php">Onboarding Tracker</a></li>
                    <li class="breadcrumb-item active">Onboarding Reports</li>
                </ol>
            </nav>

            <!-- Date Filter -->
            <div class="filter-card">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-2"></i>Filter Results
                        </button>
                    </div>
                    <div class="col-md-3 text-end">
                        <p class="text-muted mb-0">
                            <small>Showing data from <?php echo date('M d, Y', strtotime($start_date)); ?> to <?php echo date('M d, Y', strtotime($end_date)); ?></small>
                        </p>
                    </div>
                </form>
            </div>

            <!-- Overall Statistics -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card primary">
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3><?php echo number_format($total_onboarding); ?></h3>
                        <p>Total Onboardings</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card success">
                        <div class="icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3><?php echo number_format($completed_onboarding); ?></h3>
                        <p>Completed</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card warning">
                        <div class="icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3><?php echo number_format($pending_onboarding); ?></h3>
                        <p>In Progress</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card info">
                        <div class="icon">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <h3><?php echo round($avg_completion_time); ?></h3>
                        <p>Avg Days to Complete</p>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row">
                <!-- Hire Type Distribution -->
                <div class="col-lg-6 mb-4">
                    <div class="chart-card">
                        <h5 class="mb-4"><i class="fas fa-pie-chart me-2"></i>Hire Type Distribution</h5>
                        <div class="chart-container">
                            <canvas id="hireTypeChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Monthly Trends -->
                <div class="col-lg-6 mb-4">
                    <div class="chart-card">
                        <h5 class="mb-4"><i class="fas fa-line-chart me-2"></i>Monthly Trends</h5>
                        <div class="chart-container">
                            <canvas id="monthlyTrendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Department Performance -->
            <div class="chart-card">
                <h5 class="mb-4"><i class="fas fa-building me-2"></i>Department Performance</h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Total</th>
                                <th>Completed</th>
                                <th>Completion Rate</th>
                                <th>Average Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dept_stats as $dept): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($dept['employee_department']) ?: 'Not Specified'; ?></strong></td>
                                    <td><?php echo $dept['total']; ?></td>
                                    <td><?php echo $dept['completed']; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="progress me-2" style="width: 100px; height: 20px;">
                                                <div class="progress-bar bg-success" style="width: <?php echo $dept['completion_rate']; ?>%"></div>
                                            </div>
                                            <span><?php echo $dept['completion_rate']; ?>%</span>
                                        </div>
                                    </td>
                                    <td><?php echo round($dept['avg_progress']); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Checklist Item Completion -->
            <div class="chart-card">
                <h5 class="mb-4"><i class="fas fa-tasks me-2"></i>Checklist Item Completion Rates</h5>
                <div class="chart-container small">
                    <canvas id="checklistChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Hire Type Chart
        const hireTypeCtx = document.getElementById('hireTypeChart').getContext('2d');
        new Chart(hireTypeCtx, {
            type: 'doughnut',
            data: {
                labels: [
                    <?php foreach ($hire_type_stats as $stat): ?>
                        '<?php echo $stat['hire_type'] == 'new_hire' ? 'New Hire' : 'Casual to Cadre'; ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    data: [
                        <?php foreach ($hire_type_stats as $stat): ?>
                            <?php echo $stat['count']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: ['#007bff', '#17a2b8'],
                    borderWidth: 0
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

        // Monthly Trends Chart
        const monthlyCtx = document.getElementById('monthlyTrendChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php foreach (array_reverse($monthly_trends) as $trend): ?>
                        '<?php echo date('M Y', strtotime($trend['month'] . '-01')); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Started',
                    data: [
                        <?php foreach (array_reverse($monthly_trends) as $trend): ?>
                            <?php echo $trend['started']; ?>,
                        <?php endforeach; ?>
                    ],
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Completed',
                    data: [
                        <?php foreach (array_reverse($monthly_trends) as $trend): ?>
                            <?php echo $trend['completed']; ?>,
                        <?php endforeach; ?>
                    ],
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Checklist Completion Chart
        const checklistCtx = document.getElementById('checklistChart').getContext('2d');
        new Chart(checklistCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($checklist_items as $key => $label): ?>
                        '<?php echo $label; ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Completion Rate (%)',
                    data: [
                        <?php foreach ($checklist_items as $key => $label): ?>
                            <?php echo $checklist_completion['total'] > 0 ? round(($checklist_completion[$key] / $checklist_completion['total']) * 100, 2) : 0; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        '#007bff', '#28a745', '#ffc107', '#dc3545',
                        '#17a2b8', '#6f42c1', '#fd7e14', '#20c997'
                    ],
                    borderRadius: 5
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
                        max: 100
                    }
                }
            }
        });
    </script>
</body>
</html>