<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

$user = getCurrentUser();
$database = new Database();

// Check if user has assessment results permission
if (!isSuperAdmin() && !hasModulePermission($database, 'key_talent.assessment_results')) {
    header('Location: index.php');
    exit();
}

// Get available years for filtering
try {
    $years_query = "SELECT DISTINCT year FROM kti_forms ORDER BY year DESC";
    $available_years = $database->fetchAll($years_query);
} catch (Exception $e) {
    $available_years = [];
}

// Get filter parameters
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : (count($available_years) > 0 ? $available_years[0]['year'] : date('Y'));
$selected_department = isset($_GET['department']) ? $_GET['department'] : '';

// Get assessments based on user role and filters
try {
    $assessments_query = "
        SELECT
            a.*,
            f.year,
            f.part_a_title,
            f.part_b_title
        FROM kti_assessments a
        JOIN kti_forms f ON a.form_id = f.id
        WHERE f.year = ?";

    $params = [$selected_year];

    // Role-based filtering
    if ($user['account_type'] === 'manager') {
        $assessments_query .= " AND a.manager_epf = ?";
        $params[] = $user['epf_number'];
    }

    // Department filtering
    if ($selected_department) {
        $assessments_query .= " AND a.employee_department = ?";
        $params[] = $selected_department;
    }

    $assessments_query .= " ORDER BY a.part_a_total_score DESC, a.part_b_total_score DESC";
    $assessments = $database->fetchAll($assessments_query, $params);

    // Get departments for filtering
    $departments_query = "SELECT DISTINCT employee_department FROM kti_assessments WHERE employee_department IS NOT NULL ORDER BY employee_department";
    $departments = $database->fetchAll($departments_query);

    // Calculate statistics
    $total_assessments = count($assessments);
    $high_performers = count(array_filter($assessments, function($a) { return $a['part_a_total_score'] >= 76; }));
    $high_potential = count(array_filter($assessments, function($a) { return $a['part_b_total_score'] >= 76; }));
    $avg_performance = $total_assessments > 0 ? array_sum(array_column($assessments, 'part_a_total_score')) / $total_assessments : 0;
    $avg_potential = $total_assessments > 0 ? array_sum(array_column($assessments, 'part_b_total_score')) / $total_assessments : 0;

} catch (Exception $e) {
    $assessments = [];
    $departments = [];
    $total_assessments = 0;
    $high_performers = 0;
    $high_potential = 0;
    $avg_performance = 0;
    $avg_potential = 0;
    $error_message = "Error loading assessments: " . $e->getMessage();
}

// Function to get performance category badge
function getPerformanceBadge($score) {
    if ($score >= 76) return 'bg-success';
    if ($score >= 41) return 'bg-warning text-dark';
    return 'bg-danger';
}

// Function to get talent box color
function getTalentBoxColor($category) {
    $colors = [
        'The Unicorn - High Potential Talent' => 'bg-primary',
        'The Rocket - Emerging Talent' => 'bg-success',
        'The Veteran - Consistent Deliverer' => 'bg-info',
        'The Backbone - Solid Citizen' => 'bg-secondary',
        'Workhorse - Expert in Role' => 'bg-warning text-dark',
        'Dysfunctional Genius - Monitor & Coach' => 'bg-danger',
        'The Sleeping Giant - Retain & Develop' => 'bg-dark',
        'The Settler - Limited Growth' => 'bg-light text-dark',
        'The Wake-Up Call - Performance Review' => 'bg-danger'
    ];
    return $colors[$category] ?? 'bg-secondary';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Assessment Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.2rem;
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
            font-size: 0.9rem;
        }

        .content-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 2rem;
        }

        .filter-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .table {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .table thead th {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
            font-weight: 600;
            border: none;
            padding: 1rem;
            font-size: 0.9rem;
        }

        .table tbody td {
            padding: 0.75rem 1rem;
            vertical-align: middle;
            border-color: #e9ecef;
            font-size: 0.9rem;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .score-bar {
            height: 20px;
            border-radius: 10px;
            overflow: hidden;
            background: #e9ecef;
            position: relative;
        }

        .score-fill {
            height: 100%;
            transition: width 0.3s ease;
        }

        .score-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-weight: 600;
            font-size: 0.8rem;
            color: white;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }

        .badge {
            font-size: 0.75rem;
            padding: 0.35rem 0.65rem;
            border-radius: 20px;
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

        .action-buttons .btn {
            margin-left: 0.5rem;
        }

        .talent-box {
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            white-space: nowrap;
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

            .content-card {
                padding: 1rem;
            }

            .table-responsive {
                font-size: 0.8rem;
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
                        <h1><i class="fas fa-chart-bar me-3"></i>Assessment Results</h1>
                        <p>View submitted assessments with detailed mark breakdown for Performance and Potential</p>
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

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card primary">
                        <div class="icon">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <h3><?php echo number_format($total_assessments); ?></h3>
                        <p>Total Assessments</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card success">
                        <div class="icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <h3><?php echo number_format($high_performers); ?></h3>
                        <p>High Performers (76%+)</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card warning">
                        <div class="icon">
                            <i class="fas fa-rocket"></i>
                        </div>
                        <h3><?php echo number_format($high_potential); ?></h3>
                        <p>High Potential (76%+)</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card info">
                        <div class="icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3><?php echo number_format($avg_performance, 1); ?>%</h3>
                        <p>Avg Performance Score</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filter-card">
                <form method="GET" class="row align-items-end">
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
                    <div class="col-md-3">
                        <label for="department" class="form-label">Department</label>
                        <select name="department" id="department" class="form-control" onchange="this.form.submit()">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['employee_department']; ?>" <?php echo $dept['employee_department'] == $selected_department ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['employee_department']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="button" class="btn btn-outline-secondary" onclick="window.location.href='marks_allocations.php'">
                            <i class="fas fa-undo me-1"></i>Reset
                        </button>
                    </div>
                    <div class="col-md-3 text-end">
                        <a href="talent_grid.php?year=<?php echo $selected_year; ?>" class="btn btn-primary">
                            <i class="fas fa-th me-2"></i>View Talent Grid
                        </a>
                    </div>
                </form>
            </div>

            <!-- Assessment Results Table -->
            <div class="content-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <i class="fas fa-table me-2"></i><?php echo $selected_year; ?> Assessment Results
                        <?php if ($selected_department): ?>
                        <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($selected_department); ?></span>
                        <?php endif; ?>
                    </h5>
                    <small class="text-muted">Last updated: <?php echo date('M d, Y H:i'); ?></small>
                </div>

                <?php if (!empty($assessments)): ?>
                <div class="table-responsive">
                    <table id="assessmentsTable" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Designation</th>
                                <th>Department</th>
                                <th>Performance</th>
                                <th>Potential</th>
                                <th>Category</th>
                                <th>Talent Box</th>
                                <th>Manager</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assessments as $assessment): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="me-2">
                                            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center"
                                                 style="width: 35px; height: 35px; font-size: 0.8rem; color: white;">
                                                <?php echo strtoupper(substr($assessment['employee_name'], 0, 2)); ?>
                                            </div>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($assessment['employee_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($assessment['employee_epf']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($assessment['employee_designation']); ?></td>
                                <td>
                                    <span class="badge bg-light text-dark">
                                        <?php echo htmlspecialchars($assessment['employee_department']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="score-bar">
                                        <div class="score-fill <?php echo getPerformanceBadge($assessment['part_a_total_score']); ?>"
                                             style="width: <?php echo min($assessment['part_a_total_score'], 100); ?>%"></div>
                                        <div class="score-text"><?php echo number_format($assessment['part_a_total_score'], 1); ?>%</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="score-bar">
                                        <div class="score-fill <?php echo getPerformanceBadge($assessment['part_b_total_score']); ?>"
                                             style="width: <?php echo min($assessment['part_b_total_score'], 100); ?>%"></div>
                                        <div class="score-text"><?php echo number_format($assessment['part_b_total_score'], 1); ?>%</div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?php echo getPerformanceBadge($assessment['part_a_total_score']); ?>">
                                        <?php echo $assessment['performance_category']; ?>
                                    </span>
                                    /
                                    <span class="badge <?php echo getPerformanceBadge($assessment['part_b_total_score']); ?>">
                                        <?php echo $assessment['potential_category']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="talent-box <?php echo getTalentBoxColor($assessment['talent_box_category']); ?>">
                                        <?php echo htmlspecialchars($assessment['talent_box_category']); ?>
                                    </span>
                                </td>
                                <td>
                                    <small>
                                        <?php echo htmlspecialchars($assessment['manager_name']); ?>
                                        <br><span class="text-muted"><?php echo htmlspecialchars($assessment['manager_epf']); ?></span>
                                    </small>
                                </td>
                                <td>
                                    <small><?php echo date('M d, Y', strtotime($assessment['submitted_at'])); ?></small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Assessment Results Found</h5>
                    <p class="text-muted">
                        <?php if (!empty($available_years)): ?>
                        No assessments have been completed for <?php echo $selected_year; ?><?php echo $selected_department ? ' in ' . htmlspecialchars($selected_department) : ''; ?>.
                        <?php else: ?>
                        No assessment forms have been created yet.
                        <?php endif; ?>
                    </p>
                    <?php if ($user['account_type'] === 'manager' || $user['account_type'] === 'superadmin'): ?>
                    <a href="assessment_form.php" class="btn btn-success">
                        <i class="fas fa-plus me-2"></i>Start New Assessment
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Summary Analytics -->
            <?php if (!empty($assessments)): ?>
            <div class="row">
                <div class="col-md-6">
                    <div class="content-card">
                        <h6><i class="fas fa-chart-pie me-2"></i>Performance Distribution</h6>
                        <div class="row text-center">
                            <?php
                            $perf_high = count(array_filter($assessments, function($a) { return $a['part_a_total_score'] >= 76; }));
                            $perf_moderate = count(array_filter($assessments, function($a) { return $a['part_a_total_score'] >= 41 && $a['part_a_total_score'] < 76; }));
                            $perf_low = count(array_filter($assessments, function($a) { return $a['part_a_total_score'] < 41; }));
                            ?>
                            <div class="col-4">
                                <h4 class="text-success"><?php echo $perf_high; ?></h4>
                                <small class="text-muted">High (76%+)</small>
                            </div>
                            <div class="col-4">
                                <h4 class="text-warning"><?php echo $perf_moderate; ?></h4>
                                <small class="text-muted">Moderate (41-75%)</small>
                            </div>
                            <div class="col-4">
                                <h4 class="text-danger"><?php echo $perf_low; ?></h4>
                                <small class="text-muted">Low (0-40%)</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="content-card">
                        <h6><i class="fas fa-rocket me-2"></i>Potential Distribution</h6>
                        <div class="row text-center">
                            <?php
                            $pot_high = count(array_filter($assessments, function($a) { return $a['part_b_total_score'] >= 76; }));
                            $pot_moderate = count(array_filter($assessments, function($a) { return $a['part_b_total_score'] >= 41 && $a['part_b_total_score'] < 76; }));
                            $pot_low = count(array_filter($assessments, function($a) { return $a['part_b_total_score'] < 41; }));
                            ?>
                            <div class="col-4">
                                <h4 class="text-success"><?php echo $pot_high; ?></h4>
                                <small class="text-muted">High (76%+)</small>
                            </div>
                            <div class="col-4">
                                <h4 class="text-warning"><?php echo $pot_moderate; ?></h4>
                                <small class="text-muted">Moderate (41-75%)</small>
                            </div>
                            <div class="col-4">
                                <h4 class="text-danger"><?php echo $pot_low; ?></h4>
                                <small class="text-muted">Low (0-40%)</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#assessmentsTable').DataTable({
                "order": [[ 3, "desc" ]], // Sort by performance score desc
                "pageLength": 25,
                "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                "responsive": true,
                "columnDefs": [
                    { "width": "20%", "targets": 0 }, // Employee
                    { "width": "15%", "targets": 1 }, // Designation
                    { "width": "10%", "targets": 2 }, // Department
                    { "width": "12%", "targets": 3 }, // Performance
                    { "width": "12%", "targets": 4 }, // Potential
                    { "width": "10%", "targets": 5 }, // Category
                    { "width": "15%", "targets": 6 }, // Talent Box
                    { "width": "10%", "targets": 7 }, // Manager
                    { "width": "8%", "targets": 8 }   // Date
                ],
                "language": {
                    "search": "Search assessments:",
                    "lengthMenu": "Show _MENU_ assessments",
                    "info": "Showing _START_ to _END_ of _TOTAL_ assessments",
                    "infoEmpty": "No assessments available",
                    "infoFiltered": "(filtered from _MAX_ total assessments)"
                }
            });
        });

        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    </script>
</body>
</html>