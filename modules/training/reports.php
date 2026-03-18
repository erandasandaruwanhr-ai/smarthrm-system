<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/location_access.php';

$user = getCurrentUser();
$db = new Database();

// Check module permissions
if (!isSuperAdmin() && !hasModulePermission($db, 'training.view')) {
    header('Location: ../../dashboard.php');
    exit;
}

// Get user location and account type for access control
$account_type = $user['account_type'] ?? 'user';
$user_location = $user['location'] ?? $user['location_name'] ?? null;

// Build role-based filter for training data
$tr_filter = '';
$tr_params = [];

if ($account_type === 'superadmin') {
    // No filter
} elseif ($account_type === 'admin') {
    $tr_filter = ' AND tr.location = ?';
    $tr_params = [$user_location];
} elseif (in_array($account_type, ['manager', 'supervisor'])) {
    $accessible = getAccessibleEmployeesByLocation($db, $user);
    $accessible_epfs = array_column($accessible, 'epf_number');
    if (!empty($accessible_epfs)) {
        $placeholders = str_repeat('?,', count($accessible_epfs) - 1) . '?';
        $tr_filter = " AND tr.epf_number IN ($placeholders)";
        $tr_params = $accessible_epfs;
    } else {
        $tr_filter = ' AND 1=0';
    }
} else {
    $tr_filter = ' AND tr.epf_number = ?';
    $tr_params = [$user['epf_number']];
}

// Get filter parameters
$year = $_GET['year'] ?? date('Y');
$department = $_GET['department'] ?? '';
$location = $_GET['location'] ?? '';

// Build filter conditions with role-based access control
$where_conditions = ["tr.year = ?"];
$params = array_merge([$year], $tr_params);

// Add role-based filter
if ($tr_filter) {
    $where_conditions[] = substr($tr_filter, 5); // Remove the " AND " prefix
}

if ($department) {
    $where_conditions[] = "tr.department = ?";
    $params[] = $department;
}

if ($location) {
    $where_conditions[] = "tr.location = ?";
    $params[] = $location;
}

$where_clause = implode(' AND ', $where_conditions);

// Get comprehensive training data using the view
$training_data = $db->fetchAll("
    SELECT * FROM v_training_summary
    WHERE year = ?
    " . ($department ? "AND department = ?" : "") . "
    " . ($location ? "AND location = ?" : "") . "
    ORDER BY department, location
", array_filter([$year, $department, $location]));

// Get effectiveness data
$effectiveness_data = $db->fetchAll("
    SELECT * FROM v_training_effectiveness
    WHERE YEAR(training_start_date) = ?
    ORDER BY training_start_date DESC
", [$year]);

// Get detailed training statistics
$detailed_stats = $db->fetch("
    SELECT
        COUNT(DISTINCT tr.id) as total_requirements,
        COUNT(DISTINCT tb.id) as budgeted_items,
        SUM(tb.budget_amount) as total_budget_allocated,
        COUNT(DISTINCT tp.id) as total_plans,
        SUM(tp.training_cost) as total_actual_cost,
        COUNT(DISTINCT te.id) as evaluations_completed,
        COUNT(DISTINCT tf.id) as feedback_completed,
        COUNT(DISTINCT tmc.id) as management_reviews,
        AVG(CASE WHEN te.immediate_application_rating > 0 THEN
            (te.immediate_application_rating + te.performance_improvement_rating + te.recommend_to_colleague_rating) / 3
            ELSE NULL END) as avg_trainee_rating,
        AVG(CASE WHEN tf.skill_transfer_rating > 0 THEN
            (tf.skill_transfer_rating + tf.performance_improvement_rating + tf.knowledge_sharing_rating + tf.autonomy_rating) / 4
            ELSE NULL END) as avg_supervisor_rating
    FROM training_requirements tr
    LEFT JOIN training_budget tb ON tr.id = tb.requirement_id AND tb.add_to_budget = 1
    LEFT JOIN training_plans tp ON tr.id = tp.requirement_id
    LEFT JOIN training_evaluations te ON tp.id = te.training_plan_id
    LEFT JOIN training_feedback tf ON tp.id = tf.training_plan_id
    LEFT JOIN training_managerial_comments tmc ON tf.id = tmc.training_feedback_id
    WHERE $where_clause
", $params);

// Calculate ROI and effectiveness metrics
$roi = 0;
$budget_utilization = 0;
if ($detailed_stats['total_budget_allocated'] > 0) {
    $budget_utilization = ($detailed_stats['total_actual_cost'] / $detailed_stats['total_budget_allocated']) * 100;
}

// Get departments and locations for filters
$departments = $db->fetchAll("SELECT DISTINCT department FROM training_requirements WHERE year = ? ORDER BY department", [$year]);
$locations = $db->fetchAll("SELECT DISTINCT location FROM training_requirements WHERE year = ? ORDER BY location", [$year]);

// Get training by type
$training_by_type = $db->fetchAll("
    SELECT
        tr.training_type,
        COUNT(*) as count,
        SUM(tp.training_cost) as total_cost,
        AVG(CASE WHEN te.immediate_application_rating > 0 THEN
            (te.immediate_application_rating + te.performance_improvement_rating + te.recommend_to_colleague_rating) / 3
            ELSE NULL END) as avg_rating
    FROM training_requirements tr
    JOIN training_plans tp ON tr.id = tp.requirement_id
    LEFT JOIN training_evaluations te ON tp.id = te.training_plan_id
    WHERE $where_clause
    GROUP BY tr.training_type
    ORDER BY total_cost DESC
", $params);

// Get top performing trainings
$top_trainings = $db->fetchAll("
    SELECT
        tp.training_name,
        tp.training_institute,
        tp.trainee_name,
        tp.training_cost,
        tp.training_start_date,
        AVG(CASE WHEN te.immediate_application_rating > 0 THEN
            (te.immediate_application_rating + te.performance_improvement_rating + te.recommend_to_colleague_rating) / 3
            ELSE NULL END) as avg_evaluation,
        AVG(CASE WHEN tf.skill_transfer_rating > 0 THEN
            (tf.skill_transfer_rating + tf.performance_improvement_rating + tf.knowledge_sharing_rating + tf.autonomy_rating) / 4
            ELSE NULL END) as avg_feedback,
        MAX(tf.productivity_change) as productivity_change
    FROM training_plans tp
    JOIN training_requirements tr ON tp.requirement_id = tr.id
    LEFT JOIN training_evaluations te ON tp.id = te.training_plan_id
    LEFT JOIN training_feedback tf ON tp.id = tf.training_plan_id
    WHERE $where_clause AND tp.status = 'completed'
    GROUP BY tp.id, tp.training_name, tp.training_institute, tp.trainee_name, tp.training_cost, tp.training_start_date
    HAVING avg_evaluation >= 4 OR avg_feedback >= 4
    ORDER BY avg_evaluation DESC, avg_feedback DESC
    LIMIT 10
", $params);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Training Reports & Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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

        .header-content {
            position: relative;
            z-index: 2;
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
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
            border-left: 4px solid;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .metric-value {
            font-size: 2rem;
            font-weight: 700;
            margin: 1rem 0;
            color: #2c3e50;
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin: 2rem 0;
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

        @media print {
            .no-print {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
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
            <div class="page-header no-print">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="header-content">
                        <h1><i class="fas fa-chart-bar me-3"></i>Training Reports & Analytics</h1>
                        <p class="mb-0">Comprehensive training reports with export capabilities and ROI analysis for <?php echo $year; ?></p>
                    </div>
                </div>
            </div>

            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4 no-print">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Training Management</a></li>
                    <li class="breadcrumb-item active">Reports & Analytics</li>
                </ol>
            </nav>

            <!-- Filters -->
            <div class="content-card no-print">
                <form method="GET" class="row align-items-end">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Year</label>
                        <select class="form-select" name="year" onchange="this.form.submit()">
                            <option value="<?php echo date('Y')-2; ?>" <?php echo $year == date('Y')-2 ? 'selected' : ''; ?>><?php echo date('Y')-2; ?></option>
                            <option value="<?php echo date('Y')-1; ?>" <?php echo $year == date('Y')-1 ? 'selected' : ''; ?>><?php echo date('Y')-1; ?></option>
                            <option value="<?php echo date('Y'); ?>" <?php echo $year == date('Y') ? 'selected' : ''; ?>><?php echo date('Y'); ?></option>
                            <option value="<?php echo date('Y')+1; ?>" <?php echo $year == date('Y')+1 ? 'selected' : ''; ?>><?php echo date('Y')+1; ?></option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Department</label>
                        <select class="form-select" name="department" onchange="this.form.submit()">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept['department']); ?>" <?php echo $department === $dept['department'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['department']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Location</label>
                        <select class="form-select" name="location" onchange="this.form.submit()">
                            <option value="">All Locations</option>
                            <?php foreach ($locations as $loc): ?>
                            <option value="<?php echo htmlspecialchars($loc['location']); ?>" <?php echo $location === $loc['location'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($loc['location']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="btn-group w-100">
                            <button type="button" class="btn btn-outline-success" onclick="window.print()">
                                <i class="fas fa-print me-1"></i>Print
                            </button>
                            <a href="export_comprehensive_report.php?year=<?php echo $year; ?>&department=<?php echo $department; ?>&location=<?php echo $location; ?>" class="btn btn-success">
                                <i class="fas fa-file-excel me-1"></i>Export
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Key Metrics -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card" style="border-left-color: #3498db;">
                        <i class="fas fa-clipboard-list text-primary mb-2" style="font-size: 2rem;"></i>
                        <div class="metric-value text-primary"><?php echo number_format($detailed_stats['total_requirements']); ?></div>
                        <p class="mb-0">Total Requirements</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card" style="border-left-color: #2ecc71;">
                        <i class="fas fa-graduation-cap text-success mb-2" style="font-size: 2rem;"></i>
                        <div class="metric-value text-success"><?php echo number_format($detailed_stats['total_plans']); ?></div>
                        <p class="mb-0">Training Plans</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card" style="border-left-color: #e74c3c;">
                        <i class="fas fa-dollar-sign text-danger mb-2" style="font-size: 2rem;"></i>
                        <div class="metric-value text-danger">LKR <?php echo number_format($detailed_stats['total_actual_cost'], 0); ?></div>
                        <p class="mb-0">Total Investment</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card" style="border-left-color: #f39c12;">
                        <i class="fas fa-percentage text-warning mb-2" style="font-size: 2rem;"></i>
                        <div class="metric-value text-warning"><?php echo number_format($budget_utilization, 1); ?>%</div>
                        <p class="mb-0">Budget Utilization</p>
                    </div>
                </div>
            </div>

            <!-- Training Effectiveness Charts -->
            <div class="content-card">
                <h5 class="mb-4">Training Effectiveness Analysis</h5>
                <div class="row">
                    <div class="col-md-6">
                        <h6>Training by Type</h6>
                        <div class="chart-container">
                            <canvas id="trainingTypeChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Rating Comparison</h6>
                        <div class="chart-container">
                            <canvas id="ratingChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Performing Trainings -->
            <div class="content-card">
                <h5 class="mb-3">Top Performing Trainings</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Training Name</th>
                                <th>Institute</th>
                                <th>Trainee</th>
                                <th>Cost</th>
                                <th>Date</th>
                                <th>Evaluation Score</th>
                                <th>Supervisor Rating</th>
                                <th>Impact</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_trainings as $training): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($training['training_name']); ?></td>
                                <td><?php echo htmlspecialchars($training['training_institute']); ?></td>
                                <td><?php echo htmlspecialchars($training['trainee_name']); ?></td>
                                <td>LKR <?php echo number_format($training['training_cost'], 2); ?></td>
                                <td><?php echo date('M j, Y', strtotime($training['training_start_date'])); ?></td>
                                <td>
                                    <?php if ($training['avg_evaluation']): ?>
                                    <span class="badge bg-<?php echo $training['avg_evaluation'] >= 4 ? 'success' : ($training['avg_evaluation'] >= 3 ? 'warning' : 'danger'); ?>">
                                        <?php echo number_format($training['avg_evaluation'], 1); ?>/5
                                    </span>
                                    <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($training['avg_feedback']): ?>
                                    <span class="badge bg-<?php echo $training['avg_feedback'] >= 4 ? 'success' : ($training['avg_feedback'] >= 3 ? 'warning' : 'danger'); ?>">
                                        <?php echo number_format($training['avg_feedback'], 1); ?>/5
                                    </span>
                                    <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $training['productivity_change'] === 'Increased' ? 'success' : ($training['productivity_change'] === 'Decreased' ? 'danger' : 'secondary'); ?>">
                                        <?php echo $training['productivity_change'] ?? 'N/A'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Department Summary -->
            <div class="content-card">
                <h5 class="mb-3">Department-wise Training Summary</h5>
                <div class="table-responsive">
                    <table class="table table-striped" id="summaryTable">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Location</th>
                                <th>Requirements</th>
                                <th>Budgeted</th>
                                <th>Budget Amount</th>
                                <th>Planned</th>
                                <th>Evaluations</th>
                                <th>Feedback</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($training_data as $data): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($data['department']); ?></td>
                                <td><?php echo htmlspecialchars($data['location']); ?></td>
                                <td><?php echo number_format($data['total_requirements']); ?></td>
                                <td><?php echo number_format($data['budgeted_items']); ?></td>
                                <td>LKR <?php echo number_format($data['total_budget'], 2); ?></td>
                                <td><?php echo number_format($data['planned_trainings']); ?></td>
                                <td><?php echo number_format($data['completed_evaluations']); ?></td>
                                <td><?php echo number_format($data['completed_feedback']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Key Insights -->
            <div class="content-card">
                <h5 class="mb-3">Key Insights & Recommendations</h5>
                <div class="row">
                    <div class="col-md-6">
                        <h6>Performance Metrics</h6>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Average Trainee Rating</span>
                                <strong><?php echo number_format($detailed_stats['avg_trainee_rating'] ?? 0, 1); ?>/5</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Average Supervisor Rating</span>
                                <strong><?php echo number_format($detailed_stats['avg_supervisor_rating'] ?? 0, 1); ?>/5</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Evaluation Completion Rate</span>
                                <strong><?php echo $detailed_stats['total_plans'] > 0 ? number_format(($detailed_stats['evaluations_completed'] / $detailed_stats['total_plans']) * 100, 1) : 0; ?>%</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Feedback Completion Rate</span>
                                <strong><?php echo $detailed_stats['total_plans'] > 0 ? number_format(($detailed_stats['feedback_completed'] / $detailed_stats['total_plans']) * 100, 1) : 0; ?>%</strong>
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Recommendations</h6>
                        <div class="alert alert-info">
                            <h6 class="alert-heading">Training Optimization</h6>
                            <ul class="mb-0">
                                <li>Focus on high-impact training types with ratings above 4.0</li>
                                <li>Improve evaluation completion rates in departments with low submission</li>
                                <li>Consider cost-effective alternatives for low-performing trainings</li>
                                <li>Enhance follow-up support for trainings with productivity concerns</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#summaryTable').DataTable({
                pageLength: 25,
                order: [[4, 'desc']], // Order by budget amount
                dom: 'Bfrtip'
            });

            // Training Type Chart
            const trainingTypeData = <?php echo json_encode($training_by_type); ?>;
            if (trainingTypeData.length > 0) {
                const ctx1 = document.getElementById('trainingTypeChart').getContext('2d');
                new Chart(ctx1, {
                    type: 'doughnut',
                    data: {
                        labels: trainingTypeData.map(item => item.training_type.charAt(0).toUpperCase() + item.training_type.slice(1)),
                        datasets: [{
                            data: trainingTypeData.map(item => item.count),
                            backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0']
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

            // Rating Comparison Chart
            const ctx2 = document.getElementById('ratingChart').getContext('2d');
            new Chart(ctx2, {
                type: 'bar',
                data: {
                    labels: ['Trainee Rating', 'Supervisor Rating'],
                    datasets: [{
                        label: 'Average Rating',
                        data: [<?php echo $detailed_stats['avg_trainee_rating'] ?? 0; ?>, <?php echo $detailed_stats['avg_supervisor_rating'] ?? 0; ?>],
                        backgroundColor: ['#36A2EB', '#4BC0C0'],
                        borderColor: ['#36A2EB', '#4BC0C0'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 5
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>