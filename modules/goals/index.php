<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/location_access.php';

$user = getCurrentUser();
$db = new Database();

// Check if user has access to Goal Setting module
if (!isSuperAdmin() && !hasModulePermission($db, 'goals.view')) {
    header('Location: ../../dashboard.php');
    exit;
}

// Build role-based filter for goal records
$account_type = $user['account_type'] ?? 'user';
$record_filter = '';
$record_params = [];
$emp_filter = '';
$emp_params = [];

if ($account_type === 'superadmin') {
    // No filter
} elseif ($account_type === 'admin') {
    $record_filter = ' AND egr.employee_epf IN (SELECT epf_number FROM employees WHERE location_id = ?)';
    $record_params = [$user['location_id']];
    $emp_filter = ' AND location_id = ?';
    $emp_params = [$user['location_id']];
} elseif (in_array($account_type, ['manager', 'supervisor'])) {
    $accessible = getAccessibleEmployeesByLocation($db, $user);
    $accessible_epfs = array_column($accessible, 'epf_number');
    if (!empty($accessible_epfs)) {
        $placeholders = str_repeat('?,', count($accessible_epfs) - 1) . '?';
        $record_filter = " AND egr.employee_epf IN ($placeholders)";
        $record_params = $accessible_epfs;
        $emp_filter = " AND epf_number IN ($placeholders)";
        $emp_params = $accessible_epfs;
    } else {
        $record_filter = ' AND 1=0';
        $emp_filter = ' AND 1=0';
    }
} else {
    $record_filter = ' AND egr.employee_epf = ?';
    $record_params = [$user['epf_number']];
    $emp_filter = ' AND epf_number = ?';
    $emp_params = [$user['epf_number']];
}

// Get Goal Setting statistics (filtered by role)
$total_periods = $db->fetch("SELECT COUNT(*) as count FROM goal_setting_periods")['count'] ?? 0;
$active_periods = $db->fetch("SELECT COUNT(*) as count FROM goal_setting_periods WHERE status = 'active'")['count'] ?? 0;
$total_records = $db->fetch("SELECT COUNT(*) as count FROM executive_goal_records egr WHERE 1=1" . $record_filter, $record_params)['count'] ?? 0;
$completed_evaluations = $db->fetch("SELECT COUNT(*) as count FROM executive_goal_records egr WHERE egr.final_evaluation = 'Y'" . $record_filter, $record_params)['count'] ?? 0;

// Get executive employees for goal setting (employment levels 1.5.3 to 1.5.7)
$executive_count = $db->fetch("
    SELECT COUNT(*) as count
    FROM employees
    WHERE is_active = 1
    AND employment_level_id IN (3, 4, 5, 6, 7)" . $emp_filter, $emp_params)['count'] ?? 0;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Goal Setting Management</title>
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

        .stats-card.warning .icon {
            background: linear-gradient(135deg, var(--warning-color), #e0a800);
            color: white;
        }

        .stats-card.success .icon {
            background: linear-gradient(135deg, var(--success-color), #1e7e34);
            color: white;
        }

        .stats-card.danger .icon {
            background: linear-gradient(135deg, var(--danger-color), #c82333);
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

        .action-card {
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .action-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
        }

        .action-card h4 {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
        }

        .action-card p {
            font-size: 0.9rem;
            color: #7f8c8d;
        }

        a:hover .action-card h4,
        a:hover .action-card p {
            color: inherit;
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
                        <h1><i class="fas fa-bullseye me-3"></i>Goal Setting Management</h1>
                        <p>Executive performance goal setting and tracking system</p>
                    </div>
                    <div class="logo-container">
                        <img src="../../jiffy-logo.svg" alt="Jiffy Logo" class="page-header-logo">
                    </div>
                </div>
            </div>


            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card primary">
                        <div class="icon">
                            <i class="fas fa-calendar"></i>
                        </div>
                        <h3><?php echo number_format($total_periods); ?></h3>
                        <p>Goal Setting Periods</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card success">
                        <div class="icon">
                            <i class="fas fa-play-circle"></i>
                        </div>
                        <h3><?php echo number_format($active_periods); ?></h3>
                        <p>Active Periods</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card info">
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3><?php echo number_format($executive_count); ?></h3>
                        <p>Executive Employees</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card warning">
                        <div class="icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3><?php echo number_format($completed_evaluations); ?></h3>
                        <p>Completed Evaluations</p>
                    </div>
                </div>
            </div>

            <!-- Action Cards -->
            <div class="row mb-4">
                <!-- Executive List -->
                <?php if (hasModulePermission($db, 'goals.executive_list')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="executive_list.php" class="text-decoration-none">
                        <div class="stats-card primary action-card">
                            <div class="icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h4 class="mb-2">Executive Appraisal List</h4>
                            <p class="mb-0">View executives eligible for goal setting (Employment levels 1.5.3-1.5.7)</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Goal Setting Form Setup (Superadmin only) -->
                <?php if (isSuperAdmin()): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="form_setup.php" class="text-decoration-none">
                        <div class="stats-card success action-card">
                            <div class="icon">
                                <i class="fas fa-cogs"></i>
                            </div>
                            <h4 class="mb-2">Goal Setting Form Setup</h4>
                            <p class="mb-0">Configure yearly goal setting forms and allocations</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Goal Setting Forms -->
                <?php if (hasModulePermission($db, 'goals.goal_forms')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="goal_forms.php" class="text-decoration-none">
                        <div class="stats-card info action-card">
                            <div class="icon">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <h4 class="mb-2">Goal Setting Forms</h4>
                            <p class="mb-0">Create and manage employee goal setting forms</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- KPI Completion Tracker -->
                <?php if (hasModulePermission($db, 'goals.kpi_tracker')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="kpi_tracker.php" class="text-decoration-none">
                        <div class="stats-card warning action-card">
                            <div class="icon">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <h4 class="mb-2">KPI Completion Tracker</h4>
                            <p class="mb-0">Track goal setting completion status and progress</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Mid-Year Progress Review -->
                <?php if (hasModulePermission($db, 'goals.mid_year_review')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="mid_year_review.php" class="text-decoration-none">
                        <div class="stats-card danger action-card">
                            <div class="icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <h4 class="mb-2">Mid-Year Progress Review</h4>
                            <p class="mb-0">Update and review mid-year progress status</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Final Performance Evaluation -->
                <?php if (hasModulePermission($db, 'goals.final_evaluation')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="final_evaluation.php" class="text-decoration-none">
                        <div class="stats-card primary action-card">
                            <div class="icon">
                                <i class="fas fa-award"></i>
                            </div>
                            <h4 class="mb-2">Final Performance Evaluation</h4>
                            <p class="mb-0">Complete final performance evaluations and ratings</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Reports & Analytics -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="reports_analytics.php" class="text-decoration-none">
                        <div class="stats-card success action-card">
                            <div class="icon">
                                <i class="fas fa-chart-pie"></i>
                            </div>
                            <h4 class="mb-2">Reports & Analytics</h4>
                            <p class="mb-0">View comprehensive goal setting reports and analytics</p>
                        </div>
                    </a>
                </div>

                <!-- System Settings (Superadmin only) -->
                <?php if (isSuperAdmin()): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="system_settings.php" class="text-decoration-none">
                        <div class="stats-card info action-card">
                            <div class="icon">
                                <i class="fas fa-sliders-h"></i>
                            </div>
                            <h4 class="mb-2">System Settings</h4>
                            <p class="mb-0">Configure goal setting system status and settings</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    </script>
</body>
</html>