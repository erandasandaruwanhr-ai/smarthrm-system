<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/performance_auth.php';

// Check if user has reports permission
requirePerformancePermission('performance.reports');

$user = getCurrentUser();
$db = new Database();

// Get selected year (default to current year)
$selected_year = $_GET['year'] ?? date('Y');

// Get report type
$report_type = $_GET['type'] ?? 'overview';

// Get available years
$available_years = $db->fetchAll("
    SELECT DISTINCT appraisal_year as year FROM staff_appraisals
    UNION
    SELECT DISTINCT appraisal_year as year FROM executive_appraisals
    ORDER BY year DESC
");

// Overview Statistics
$staff_stats = $db->fetch("
    SELECT
        COUNT(*) as total,
        COUNT(CASE WHEN status IN ('submitted', 'approved') THEN 1 END) as completed,
        COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft,
        AVG(CASE WHEN status IN ('submitted', 'approved') THEN
            (COALESCE(job_knowledge_q1, 0) + COALESCE(job_knowledge_q2, 0) + COALESCE(job_knowledge_q3, 0) +
             COALESCE(creativity_q1, 0) + COALESCE(quality_awareness_q1, 0) + COALESCE(quality_awareness_q2, 0) +
             COALESCE(goal_achievement_q1, 0) + COALESCE(teamwork_q1, 0) + COALESCE(teamwork_q2, 0) +
             COALESCE(teamwork_q3, 0) + COALESCE(teamwork_q4, 0) + COALESCE(cultural_awareness_q1, 0) +
             COALESCE(cultural_awareness_q2, 0) + COALESCE(cultural_awareness_q3, 0) + COALESCE(cultural_awareness_q4, 0) +
             COALESCE(cultural_awareness_q5, 0) + COALESCE(cultural_awareness_q6, 0) + COALESCE(safety_consciousness_q1, 0) +
             COALESCE(safety_consciousness_q2, 0) + COALESCE(safety_consciousness_q3, 0) + COALESCE(safety_consciousness_q4, 0) +
             COALESCE(discipline_q1, 0) + COALESCE(discipline_q2, 0) + COALESCE(discipline_q3, 0) + COALESCE(discipline_q4, 0))
        END) as avg_score
    FROM staff_appraisals
    WHERE appraisal_year = ?
", [$selected_year]) ?? ['total' => 0, 'completed' => 0, 'draft' => 0, 'avg_score' => 0];

$executive_stats = $db->fetch("
    SELECT
        COUNT(*) as total,
        COUNT(CASE WHEN status IN ('submitted', 'approved') THEN 1 END) as completed,
        COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft,
        AVG(CASE WHEN status IN ('submitted', 'approved') THEN
            (COALESCE(competency_technical_skills, 0) + COALESCE(competency_communication, 0) +
             COALESCE(competency_teamwork, 0) + COALESCE(competency_leadership, 0) +
             COALESCE(competency_problem_solving, 0) + COALESCE(competency_adaptability, 0) +
             COALESCE(competency_time_management, 0) + COALESCE(competency_customer_focus, 0) +
             COALESCE(core_values_respectful_manager, 0) + COALESCE(core_values_passionate_manager, 0) +
             COALESCE(core_values_reliable_manager, 0) + COALESCE(attitude_promises_manager, 0) +
             COALESCE(attitude_trust_manager, 0) + COALESCE(attitude_improvement_manager, 0) +
             COALESCE(attitude_teamwork_manager, 0) + COALESCE(attitude_decisions_manager, 0) +
             COALESCE(attitude_communication_manager, 0) + COALESCE(attitude_principles_manager, 0) +
             COALESCE(attitude_customer_manager, 0))
        END) as avg_score
    FROM executive_appraisals
    WHERE appraisal_year = ?
", [$selected_year]) ?? ['total' => 0, 'completed' => 0, 'draft' => 0, 'avg_score' => 0];

// Department-wise analysis
$dept_staff_analysis = $db->fetchAll("
    SELECT
        department,
        COUNT(*) as total,
        COUNT(CASE WHEN status IN ('submitted', 'approved') THEN 1 END) as completed,
        AVG(CASE WHEN status IN ('submitted', 'approved') THEN
            (COALESCE(job_knowledge_q1, 0) + COALESCE(job_knowledge_q2, 0) + COALESCE(job_knowledge_q3, 0) +
             COALESCE(creativity_q1, 0) + COALESCE(quality_awareness_q1, 0) + COALESCE(quality_awareness_q2, 0) +
             COALESCE(goal_achievement_q1, 0) + COALESCE(teamwork_q1, 0) + COALESCE(teamwork_q2, 0) +
             COALESCE(teamwork_q3, 0) + COALESCE(teamwork_q4, 0) + COALESCE(cultural_awareness_q1, 0) +
             COALESCE(cultural_awareness_q2, 0) + COALESCE(cultural_awareness_q3, 0) + COALESCE(cultural_awareness_q4, 0) +
             COALESCE(cultural_awareness_q5, 0) + COALESCE(cultural_awareness_q6, 0) + COALESCE(safety_consciousness_q1, 0) +
             COALESCE(safety_consciousness_q2, 0) + COALESCE(safety_consciousness_q3, 0) + COALESCE(safety_consciousness_q4, 0) +
             COALESCE(discipline_q1, 0) + COALESCE(discipline_q2, 0) + COALESCE(discipline_q3, 0) + COALESCE(discipline_q4, 0))
        END) as avg_score
    FROM staff_appraisals
    WHERE appraisal_year = ? AND department IS NOT NULL
    GROUP BY department
    ORDER BY avg_score DESC
", [$selected_year]);

$dept_executive_analysis = $db->fetchAll("
    SELECT
        department,
        COUNT(*) as total,
        COUNT(CASE WHEN status IN ('submitted', 'approved') THEN 1 END) as completed,
        AVG(CASE WHEN status IN ('submitted', 'approved') THEN
            (COALESCE(competency_technical_skills, 0) + COALESCE(competency_communication, 0) +
             COALESCE(competency_teamwork, 0) + COALESCE(competency_leadership, 0) +
             COALESCE(competency_problem_solving, 0) + COALESCE(competency_adaptability, 0) +
             COALESCE(competency_time_management, 0) + COALESCE(competency_customer_focus, 0) +
             COALESCE(core_values_respectful_manager, 0) + COALESCE(core_values_passionate_manager, 0) +
             COALESCE(core_values_reliable_manager, 0) + COALESCE(attitude_promises_manager, 0) +
             COALESCE(attitude_trust_manager, 0) + COALESCE(attitude_improvement_manager, 0) +
             COALESCE(attitude_teamwork_manager, 0) + COALESCE(attitude_decisions_manager, 0) +
             COALESCE(attitude_communication_manager, 0) + COALESCE(attitude_principles_manager, 0) +
             COALESCE(attitude_customer_manager, 0))
        END) as avg_score
    FROM executive_appraisals
    WHERE appraisal_year = ? AND department IS NOT NULL
    GROUP BY department
    ORDER BY avg_score DESC
", [$selected_year]);

// Top performers
$top_staff_performers = $db->fetchAll("
    SELECT
        appraisee_name,
        appraisee_epf,
        department,
        designation,
        (COALESCE(job_knowledge_q1, 0) + COALESCE(job_knowledge_q2, 0) + COALESCE(job_knowledge_q3, 0) +
         COALESCE(creativity_q1, 0) + COALESCE(quality_awareness_q1, 0) + COALESCE(quality_awareness_q2, 0) +
         COALESCE(goal_achievement_q1, 0) + COALESCE(teamwork_q1, 0) + COALESCE(teamwork_q2, 0) +
         COALESCE(teamwork_q3, 0) + COALESCE(teamwork_q4, 0) + COALESCE(cultural_awareness_q1, 0) +
         COALESCE(cultural_awareness_q2, 0) + COALESCE(cultural_awareness_q3, 0) + COALESCE(cultural_awareness_q4, 0) +
         COALESCE(cultural_awareness_q5, 0) + COALESCE(cultural_awareness_q6, 0) + COALESCE(safety_consciousness_q1, 0) +
         COALESCE(safety_consciousness_q2, 0) + COALESCE(safety_consciousness_q3, 0) + COALESCE(safety_consciousness_q4, 0) +
         COALESCE(discipline_q1, 0) + COALESCE(discipline_q2, 0) + COALESCE(discipline_q3, 0) + COALESCE(discipline_q4, 0)) as total_score
    FROM staff_appraisals
    WHERE appraisal_year = ? AND status IN ('submitted', 'approved')
    ORDER BY total_score DESC
    LIMIT 10
", [$selected_year]);

$top_executive_performers = $db->fetchAll("
    SELECT
        appraisee_name,
        appraisee_epf,
        department,
        designation,
        (COALESCE(competency_technical_skills, 0) + COALESCE(competency_communication, 0) +
         COALESCE(competency_teamwork, 0) + COALESCE(competency_leadership, 0) +
         COALESCE(competency_problem_solving, 0) + COALESCE(competency_adaptability, 0) +
         COALESCE(competency_time_management, 0) + COALESCE(competency_customer_focus, 0) +
         COALESCE(core_values_respectful_manager, 0) + COALESCE(core_values_passionate_manager, 0) +
         COALESCE(core_values_reliable_manager, 0) + COALESCE(attitude_promises_manager, 0) +
         COALESCE(attitude_trust_manager, 0) + COALESCE(attitude_improvement_manager, 0) +
         COALESCE(attitude_teamwork_manager, 0) + COALESCE(attitude_decisions_manager, 0) +
         COALESCE(attitude_communication_manager, 0) + COALESCE(attitude_principles_manager, 0) +
         COALESCE(attitude_customer_manager, 0)) as total_score
    FROM executive_appraisals
    WHERE appraisal_year = ? AND status IN ('submitted', 'approved')
    ORDER BY total_score DESC
    LIMIT 10
", [$selected_year]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Performance Reports & Analytics (13.10)</title>
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
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
        }

        .report-nav {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
        }

        .performance-badge {
            font-size: 0.8em;
            padding: 0.3em 0.6em;
            border-radius: 15px;
        }

        .card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
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
        .top-performer {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
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
                        <h1><i class="fas fa-chart-pie me-3"></i>Performance Reports & Analytics</h1>
                        <p>Comprehensive performance data analysis and reporting dashboard</p>
                    </div>
                    <div class="logo-container">
                        <img src="../../jiffy-logo.svg" alt="Jiffy Logo" class="page-header-logo">
                    </div>
                </div>
            </div>
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-0">
                            <i class="fas fa-chart-pie" style="color: #6f42c1;" me-2></i>Performance Reports & Analytics
                        </h1>
                        <p class="text-muted mb-0">Comprehensive performance analysis and insights (13.10)</p>
                    </div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="index.php">Performance</a></li>
                            <li class="breadcrumb-item active">Reports & Analytics</li>
                        </ol>
                    </nav>
                </div>

                <!-- Report Navigation -->
                <div class="report-nav">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <label class="form-label">Select Year</label>
                            <select id="yearSelect" class="form-select">
                                <?php foreach ($available_years as $year): ?>
                                    <option value="<?= $year['year'] ?>" <?= $year['year'] == $selected_year ? 'selected' : '' ?>>
                                        <?= $year['year'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Report Type</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="reportType" id="overview" value="overview" <?= $report_type === 'overview' ? 'checked' : '' ?>>
                                <label class="btn btn-outline-primary" for="overview">Overview</label>

                                <input type="radio" class="btn-check" name="reportType" id="departmental" value="departmental" <?= $report_type === 'departmental' ? 'checked' : '' ?>>
                                <label class="btn btn-outline-primary" for="departmental">Department Analysis</label>

                                <input type="radio" class="btn-check" name="reportType" id="performance" value="performance" <?= $report_type === 'performance' ? 'checked' : '' ?>>
                                <label class="btn btn-outline-primary" for="performance">Top Performers</label>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button class="btn btn-success" onclick="generateReport()">
                                    <i class="fas fa-chart-bar me-1"></i>Generate
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($report_type === 'overview'): ?>
                    <!-- Overview Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card stats-card">
                                <div class="card-body text-center">
                                    <h3 class="mb-0"><?= $staff_stats['total'] + $executive_stats['total'] ?></h3>
                                    <small>Total Appraisals</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h3 class="mb-0"><?= $staff_stats['completed'] + $executive_stats['completed'] ?></h3>
                                    <small>Completed</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <h3 class="mb-0"><?= $staff_stats['draft'] + $executive_stats['draft'] ?></h3>
                                    <small>In Draft</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h3 class="mb-0">
                                        <?php
                                        $total = $staff_stats['total'] + $executive_stats['total'];
                                        $completed = $staff_stats['completed'] + $executive_stats['completed'];
                                        echo $total > 0 ? round(($completed / $total) * 100) : 0;
                                        ?>%
                                    </h3>
                                    <small>Completion Rate</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Appraisal Status Distribution</h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="statusChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Staff vs Executive Appraisals</h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="categoryChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Performance Summary -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Staff Performance Summary</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <h4 class="text-primary"><?= $staff_stats['total'] ?></h4>
                                            <small class="text-muted">Total Staff</small>
                                        </div>
                                        <div class="col-4">
                                            <h4 class="text-success"><?= $staff_stats['completed'] ?></h4>
                                            <small class="text-muted">Completed</small>
                                        </div>
                                        <div class="col-4">
                                            <h4 class="text-info"><?= round($staff_stats['avg_score'] ?? 0, 1) ?></h4>
                                            <small class="text-muted">Avg Score</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Executive Performance Summary</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <h4 class="text-primary"><?= $executive_stats['total'] ?></h4>
                                            <small class="text-muted">Total Executives</small>
                                        </div>
                                        <div class="col-4">
                                            <h4 class="text-success"><?= $executive_stats['completed'] ?></h4>
                                            <small class="text-muted">Completed</small>
                                        </div>
                                        <div class="col-4">
                                            <h4 class="text-info"><?= round($executive_stats['avg_score'] ?? 0, 1) ?></h4>
                                            <small class="text-muted">Avg Score</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php elseif ($report_type === 'departmental'): ?>
                    <!-- Department Analysis -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Staff Performance by Department</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($dept_staff_analysis)): ?>
                                        <div class="text-center text-muted">
                                            <i class="fas fa-chart-bar" style="font-size: 2em;"></i>
                                            <p class="mt-2">No staff data available</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Department</th>
                                                        <th>Total</th>
                                                        <th>Completed</th>
                                                        <th>Avg Score</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($dept_staff_analysis as $dept): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($dept['department']) ?></td>
                                                            <td><?= $dept['total'] ?></td>
                                                            <td><?= $dept['completed'] ?></td>
                                                            <td>
                                                                <span class="performance-badge bg-primary text-white">
                                                                    <?= round($dept['avg_score'] ?? 0, 1) ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Executive Performance by Department</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($dept_executive_analysis)): ?>
                                        <div class="text-center text-muted">
                                            <i class="fas fa-chart-bar" style="font-size: 2em;"></i>
                                            <p class="mt-2">No executive data available</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Department</th>
                                                        <th>Total</th>
                                                        <th>Completed</th>
                                                        <th>Avg Score</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($dept_executive_analysis as $dept): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($dept['department']) ?></td>
                                                            <td><?= $dept['total'] ?></td>
                                                            <td><?= $dept['completed'] ?></td>
                                                            <td>
                                                                <span class="performance-badge bg-success text-white">
                                                                    <?= round($dept['avg_score'] ?? 0, 1) ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php elseif ($report_type === 'performance'): ?>
                    <!-- Top Performers -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-star text-warning me-2"></i>Top Staff Performers
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($top_staff_performers)): ?>
                                        <div class="text-center text-muted">
                                            <i class="fas fa-trophy" style="font-size: 2em;"></i>
                                            <p class="mt-2">No completed staff appraisals</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($top_staff_performers as $index => $performer): ?>
                                            <div class="top-performer">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="mb-0">
                                                            <i class="fas fa-medal me-1"></i>
                                                            #<?= $index + 1 ?> <?= htmlspecialchars($performer['appraisee_name']) ?>
                                                        </h6>
                                                        <small><?= htmlspecialchars($performer['designation']) ?> | <?= htmlspecialchars($performer['department']) ?></small><br>
                                                        <small>EPF: <?= htmlspecialchars($performer['appraisee_epf']) ?></small>
                                                    </div>
                                                    <div class="text-center">
                                                        <h4 class="mb-0"><?= round($performer['total_score'], 1) ?></h4>
                                                        <small>Score</small>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-crown text-warning me-2"></i>Top Executive Performers
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($top_executive_performers)): ?>
                                        <div class="text-center text-muted">
                                            <i class="fas fa-trophy" style="font-size: 2em;"></i>
                                            <p class="mt-2">No completed executive appraisals</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($top_executive_performers as $index => $performer): ?>
                                            <div class="top-performer" style="background: linear-gradient(45deg, #6f42c1, #e83e8c);">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="mb-0">
                                                            <i class="fas fa-crown me-1"></i>
                                                            #<?= $index + 1 ?> <?= htmlspecialchars($performer['appraisee_name']) ?>
                                                        </h6>
                                                        <small><?= htmlspecialchars($performer['designation']) ?> | <?= htmlspecialchars($performer['department']) ?></small><br>
                                                        <small>EPF: <?= htmlspecialchars($performer['appraisee_epf']) ?></small>
                                                    </div>
                                                    <div class="text-center">
                                                        <h4 class="mb-0"><?= round($performer['total_score'], 1) ?></h4>
                                                        <small>Score</small>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Export Options -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-download text-info me-2"></i>Export Options
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <button class="btn btn-outline-success w-100" onclick="exportExcel()">
                                    <i class="fas fa-file-excel me-1"></i>Export to Excel
                                </button>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-outline-danger w-100" onclick="exportPDF()">
                                    <i class="fas fa-file-pdf me-1"></i>Export to PDF
                                </button>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-outline-info w-100" onclick="printReport()">
                                    <i class="fas fa-print me-1"></i>Print Report
                                </button>
                            </div>
                            <div class="col-md-3">
                                <a href="index.php" class="btn btn-secondary w-100">
                                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize charts if on overview page
        <?php if ($report_type === 'overview'): ?>
        // Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Draft'],
                datasets: [{
                    data: [<?= $staff_stats['completed'] + $executive_stats['completed'] ?>, <?= $staff_stats['draft'] + $executive_stats['draft'] ?>],
                    backgroundColor: ['#28a745', '#ffc107']
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

        // Category Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(categoryCtx, {
            type: 'bar',
            data: {
                labels: ['Staff', 'Executive'],
                datasets: [{
                    label: 'Total',
                    data: [<?= $staff_stats['total'] ?>, <?= $executive_stats['total'] ?>],
                    backgroundColor: '#007bff'
                }, {
                    label: 'Completed',
                    data: [<?= $staff_stats['completed'] ?>, <?= $executive_stats['completed'] ?>],
                    backgroundColor: '#28a745'
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
        <?php endif; ?>

        function generateReport() {
            const year = document.getElementById('yearSelect').value;
            const reportType = document.querySelector('input[name="reportType"]:checked').value;
            window.location.href = `reports_analytics.php?year=${year}&type=${reportType}`;
        }

        function exportExcel() {
            alert('Excel export functionality would be implemented here');
        }

        function exportPDF() {
            alert('PDF export functionality would be implemented here');
        }

        function printReport() {
            window.print();
        }

        // Auto-refresh chart when selections change
        document.getElementById('yearSelect').addEventListener('change', function() {
            if (document.querySelector('input[name="reportType"]:checked').value === 'overview') {
                generateReport();
            }
        });

        document.querySelectorAll('input[name="reportType"]').forEach(radio => {
            radio.addEventListener('change', generateReport);
        });

        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    </script>
    </div>
</body>
</html>