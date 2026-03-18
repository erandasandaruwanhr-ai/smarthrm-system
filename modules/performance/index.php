<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/location_access.php';

$user = getCurrentUser();
$db = new Database();

// Check if user has access to Performance module
if (!isSuperAdmin() && !hasModulePermission($db, 'performance.view')) {
    header('Location: ../../dashboard.php');
    exit;
}

// Build role-based filter for performance queries
$account_type = $user['account_type'] ?? 'user';
$appraisal_filter = '';
$appraisal_params = [];
$emp_filter = '';
$emp_params = [];

if ($account_type === 'superadmin') {
    // No filter
} elseif ($account_type === 'admin') {
    $appraisal_filter = ' AND appraisee_epf COLLATE utf8mb4_0900_ai_ci IN (SELECT epf_number FROM employees WHERE location_id = ?)';
    $appraisal_params = [$user['location_id']];
    $emp_filter = ' AND e.location_id = ?';
    $emp_params = [$user['location_id']];
} elseif (in_array($account_type, ['manager', 'supervisor'])) {
    $accessible = getAccessibleEmployeesByLocation($db, $user);
    $accessible_epfs = array_column($accessible, 'epf_number');
    if (!empty($accessible_epfs)) {
        $placeholders = str_repeat('?,', count($accessible_epfs) - 1) . '?';
        $appraisal_filter = " AND appraisee_epf COLLATE utf8mb4_0900_ai_ci IN ($placeholders)";
        $appraisal_params = $accessible_epfs;
        $emp_filter = " AND e.epf_number IN ($placeholders)";
        $emp_params = $accessible_epfs;
    } else {
        $appraisal_filter = ' AND 1=0';
        $emp_filter = ' AND 1=0';
    }
} else {
    $appraisal_filter = ' AND appraisee_epf COLLATE utf8mb4_0900_ai_ci = ?';
    $appraisal_params = [$user['epf_number']];
    $emp_filter = ' AND e.epf_number = ?';
    $emp_params = [$user['epf_number']];
}

// Get performance statistics (role-filtered)
$year_params_staff = array_merge($appraisal_params);
$total_staff_appraisals = $db->fetch("SELECT COUNT(*) as count FROM staff_appraisals WHERE appraisal_year = YEAR(NOW())" . $appraisal_filter, $year_params_staff)['count'] ?? 0;
$total_executive_appraisals = $db->fetch("SELECT COUNT(*) as count FROM executive_appraisals WHERE appraisal_year = YEAR(NOW())" . $appraisal_filter, $year_params_staff)['count'] ?? 0;
$pending_staff_appraisals = $db->fetch("SELECT COUNT(*) as count FROM staff_appraisals WHERE status = 'draft' AND appraisal_year = YEAR(NOW())" . $appraisal_filter, $year_params_staff)['count'] ?? 0;
$pending_executive_appraisals = $db->fetch("SELECT COUNT(*) as count FROM executive_appraisals WHERE status = 'draft' AND appraisal_year = YEAR(NOW())" . $appraisal_filter, $year_params_staff)['count'] ?? 0;

// Get eligible employees for staff appraisals (employment levels 8 and 9)
$staff_eligible = $db->fetch("
    SELECT COUNT(*) as count
    FROM employees e
    LEFT JOIN employment_levels el ON e.employment_level_id = el.id
    WHERE e.is_active = 1 AND e.employment_level_id IN (8, 9)" . $emp_filter, $emp_params)['count'] ?? 0;

// Get eligible employees for executive appraisals (employment levels 3-7)
$executive_eligible = $db->fetch("
    SELECT COUNT(*) as count
    FROM employees e
    LEFT JOIN employment_levels el ON e.employment_level_id = el.id
    WHERE e.is_active = 1 AND e.employment_level_id IN (3, 4, 5, 6, 7)" . $emp_filter, $emp_params)['count'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Performance Management</title>
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
                        <h1><i class="fas fa-chart-line me-3"></i>Performance Management</h1>
                        <p>Comprehensive employee performance appraisal system for <?= date('Y') ?></p>
                    </div>
                    <div class="logo-container">
                        <img src="../../jiffy-logo.svg" alt="Jiffy Logo" class="page-header-logo">
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card info">
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3><?php echo number_format($staff_eligible); ?></h3>
                        <p>Eligible Staff</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card primary">
                        <div class="icon">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <h3><?php echo number_format($executive_eligible); ?></h3>
                        <p>Eligible Executives</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card success">
                        <div class="icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3><?php echo number_format($total_staff_appraisals); ?></h3>
                        <p>Staff Appraisals</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card warning">
                        <div class="icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <h3><?php echo number_format($total_executive_appraisals); ?></h3>
                        <p>Executive Appraisals</p>
                    </div>
                </div>
            </div>

            <!-- Action Cards -->
            <div class="row mb-4">
                <!-- Staff Performance Management -->
                <?php if (isSuperAdmin() || hasModulePermission($db, 'performance.staff_list')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="staff_list.php" class="text-decoration-none">
                        <div class="stats-card info action-card">
                            <div class="icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h4 class="mb-2">Staff List</h4>
                            <p class="mb-0">View eligible staff for appraisal</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <?php if (isSuperAdmin() || hasModulePermission($db, 'performance.staff_setup')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="staff_appraisal_setup.php" class="text-decoration-none">
                        <div class="stats-card warning action-card">
                            <div class="icon">
                                <i class="fas fa-cogs"></i>
                            </div>
                            <h4 class="mb-2">Staff Setup</h4>
                            <p class="mb-0">Configure appraisal periods</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <?php if (isSuperAdmin() || hasModulePermission($db, 'performance.staff_appraisal')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="staff_appraisal.php" class="text-decoration-none">
                        <div class="stats-card success action-card">
                            <div class="icon">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <h4 class="mb-2">Staff Appraisal</h4>
                            <p class="mb-0">Conduct staff appraisals</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <?php if (isSuperAdmin() || hasModulePermission($db, 'performance.staff_marks')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="staff_marks_data.php" class="text-decoration-none">
                        <div class="stats-card primary action-card">
                            <div class="icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <h4 class="mb-2">Staff Marks</h4>
                            <p class="mb-0">View staff appraisal data</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Executive Performance Management -->
                <?php if (isSuperAdmin() || hasModulePermission($db, 'performance.executive_list')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="executive_list.php" class="text-decoration-none">
                        <div class="stats-card success action-card">
                            <div class="icon">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <h4 class="mb-2">Executive List</h4>
                            <p class="mb-0">View eligible executives</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <?php if (isSuperAdmin() || hasModulePermission($db, 'performance.executive_setup')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="executive_appraisal_setup.php" class="text-decoration-none">
                        <div class="stats-card primary action-card">
                            <div class="icon">
                                <i class="fas fa-cogs"></i>
                            </div>
                            <h4 class="mb-2">Executive Setup</h4>
                            <p class="mb-0">Configure executive periods</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <?php if (isSuperAdmin() || hasModulePermission($db, 'performance.executive_appraisal')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="executive_appraisal.php" class="text-decoration-none">
                        <div class="stats-card info action-card">
                            <div class="icon">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <h4 class="mb-2">Executive Appraisal</h4>
                            <p class="mb-0">Conduct executive appraisals</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <?php if (isSuperAdmin() || hasModulePermission($db, 'performance.executive_tracker')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="executive_marks_data.php" class="text-decoration-none">
                        <div class="stats-card danger action-card">
                            <div class="icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <h4 class="mb-2">Executive Performance Interviews Tracker</h4>
                            <p class="mb-0">Track executive performance interviews</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Additional Modules -->
                <?php if (isSuperAdmin() || hasModulePermission($db, 'performance.leave_utilization')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="leave_utilization.php" class="text-decoration-none">
                        <div class="stats-card warning action-card">
                            <div class="icon">
                                <i class="fas fa-calendar-times"></i>
                            </div>
                            <h4 class="mb-2">Leave Utilization</h4>
                            <p class="mb-0">Manage leave data</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <?php if (isSuperAdmin() || hasModulePermission($db, 'performance.reports')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="reports_analytics.php" class="text-decoration-none">
                        <div class="stats-card info action-card">
                            <div class="icon">
                                <i class="fas fa-chart-pie"></i>
                            </div>
                            <h4 class="mb-2">Reports & Analytics</h4>
                            <p class="mb-0">Performance insights</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>