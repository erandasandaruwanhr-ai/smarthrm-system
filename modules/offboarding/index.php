<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';
require_once '../../includes/location_access.php';

$user = getCurrentUser();
$database = new Database();

// Check if user has access to Offboarding module
if (!isSuperAdmin() && !hasModulePermission($database, 'offboarding.view')) {
    header('Location: ../../dashboard.php');
    exit;
}

// Build role-based filter for resignations
$account_type = $user['account_type'] ?? 'user';
$resign_filter = '';
$resign_params = [];

if ($account_type === 'superadmin') {
    // No filter
} elseif ($account_type === 'admin') {
    $resign_filter = ' AND r.employee_epf IN (SELECT epf_number FROM employees WHERE location_id = ?)';
    $resign_params = [$user['location_id']];
} elseif (in_array($account_type, ['manager', 'supervisor'])) {
    $accessible = getAccessibleEmployeesByLocation($database, $user);
    $accessible_epfs = array_column($accessible, 'epf_number');
    if (!empty($accessible_epfs)) {
        $placeholders = str_repeat('?,', count($accessible_epfs) - 1) . '?';
        $resign_filter = " AND r.employee_epf IN ($placeholders)";
        $resign_params = $accessible_epfs;
    } else {
        $resign_filter = ' AND 1=0';
    }
} else {
    $resign_filter = ' AND r.employee_epf = ?';
    $resign_params = [$user['epf_number']];
}

// Get offboarding statistics
$total_resignations_query = "SELECT COUNT(*) as count FROM resignations r WHERE 1=1" . $resign_filter;
$total_resignations = $database->fetchAll($total_resignations_query, $resign_params)[0]['count'] ?? 0;

$pending_approvals_query = "SELECT COUNT(*) as count FROM resignations r WHERE r.status = 'pending'" . $resign_filter;
$pending_approvals = $database->fetchAll($pending_approvals_query, $resign_params)[0]['count'] ?? 0;

$approved_resignations_query = "SELECT COUNT(*) as count FROM resignations r WHERE r.status = 'approved'" . $resign_filter;
$approved_resignations = $database->fetchAll($approved_resignations_query, $resign_params)[0]['count'] ?? 0;

$completed_offboarding_query = "SELECT COUNT(*) as count FROM resignations r WHERE r.status = 'completed'" . $resign_filter;
$completed_offboarding = $database->fetchAll($completed_offboarding_query, $resign_params)[0]['count'] ?? 0;

// Get recent activity (role-filtered)
$recent_activity_query = "SELECT r.*, e.department, e.designation
                         FROM resignations r
                         LEFT JOIN employees e ON r.employee_epf = e.epf_number
                         WHERE 1=1" . $resign_filter . "
                         ORDER BY r.created_at DESC
                         LIMIT 5";
$recent_activity = $database->fetchAll($recent_activity_query, $resign_params);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Employee Offboarding</title>
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

        .activity-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
        }

        .activity-item {
            padding: 1rem;
            border-left: 3px solid var(--primary-color);
            background: #f8f9fa;
            border-radius: 0 8px 8px 0;
            margin-bottom: 1rem;
        }

        .activity-item:last-child {
            margin-bottom: 0;
        }

        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
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
                        <h1><i class="fas fa-user-minus me-3"></i>Employee Offboarding</h1>
                        <p>Comprehensive employee resignation and exit process management</p>
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
                            <i class="fas fa-file-signature"></i>
                        </div>
                        <h3><?php echo number_format($total_resignations); ?></h3>
                        <p>Total Resignations</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card warning">
                        <div class="icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3><?php echo number_format($pending_approvals); ?></h3>
                        <p>Pending Approvals</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card success">
                        <div class="icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3><?php echo number_format($approved_resignations); ?></h3>
                        <p>Approved Resignations</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card danger">
                        <div class="icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <h3><?php echo number_format($completed_offboarding); ?></h3>
                        <p>Completed Exits</p>
                    </div>
                </div>
            </div>

            <!-- Action Cards -->
            <div class="row mb-4">
                <!-- Submit Resignation -->
                <?php if (hasModulePermission($database, 'offboarding.resignation_form')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="resignation_form.php" class="text-decoration-none">
                        <div class="stats-card danger action-card">
                            <div class="icon">
                                <i class="fas fa-file-signature"></i>
                            </div>
                            <h4 class="mb-2">Submit Resignation</h4>
                            <p class="mb-0">Submit your resignation letter</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Exit Interview -->
                <?php if (hasModulePermission($database, 'offboarding.exit_interview')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="exit_interview.php" class="text-decoration-none">
                        <div class="stats-card primary action-card">
                            <div class="icon">
                                <i class="fas fa-comments"></i>
                            </div>
                            <h4 class="mb-2">Exit Interview</h4>
                            <p class="mb-0">Complete your exit interview</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Clearance Form -->
                <?php if (hasModulePermission($database, 'offboarding.clearance_form')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="clearance_form.php" class="text-decoration-none">
                        <div class="stats-card success action-card">
                            <div class="icon">
                                <i class="fas fa-clipboard-check"></i>
                            </div>
                            <h4 class="mb-2">Clearance Form</h4>
                            <p class="mb-0">Submit clearance documentation</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Resignation Approvals -->
                <?php if (hasModulePermission($database, 'offboarding.resignation_approvals')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="resignation_approvals.php" class="text-decoration-none">
                        <div class="stats-card warning action-card">
                            <div class="icon">
                                <i class="fas fa-gavel"></i>
                            </div>
                            <h4 class="mb-2">Review Resignations</h4>
                            <p class="mb-0">Approve or reject resignations</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Clearance Verification -->
                <?php if (hasModulePermission($database, 'offboarding.clearance_verification')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="clearance_verification.php" class="text-decoration-none">
                        <div class="stats-card success action-card">
                            <div class="icon">
                                <i class="fas fa-clipboard-check"></i>
                            </div>
                            <h4 class="mb-2">Final Verification</h4>
                            <p class="mb-0">Verify clearance forms</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Offboarding List -->
                <?php if (hasModulePermission($database, 'offboarding.offboarding_list')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="offboarding_list.php" class="text-decoration-none">
                        <div class="stats-card primary action-card">
                            <div class="icon">
                                <i class="fas fa-list"></i>
                            </div>
                            <h4 class="mb-2">Track Progress</h4>
                            <p class="mb-0">View all offboarding cases</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Offboarding Reports -->
                <?php if (hasModulePermission($database, 'offboarding.offboarding_reports')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="offboarding_reports.php" class="text-decoration-none">
                        <div class="stats-card primary action-card">
                            <div class="icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <h4 class="mb-2">Reports</h4>
                            <p class="mb-0">View analytics & insights</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Resignation History -->
                <?php if (hasModulePermission($database, 'offboarding.resignation_history')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="resignation_history.php" class="text-decoration-none">
                        <div class="stats-card info action-card">
                            <div class="icon">
                                <i class="fas fa-history"></i>
                            </div>
                            <h4 class="mb-2">Resignation History</h4>
                            <p class="mb-0">Complete records & timeline</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Retention Analytics -->
                <?php if (hasModulePermission($database, 'offboarding.retention_analytics')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="retention_analytics.php" class="text-decoration-none">
                        <div class="stats-card warning action-card">
                            <div class="icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <h4 class="mb-2">Retention Analytics</h4>
                            <p class="mb-0">Retention vs turnover analysis</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Recent Activity -->
            <div class="row">
                <div class="col-12">
                    <div class="activity-card">
                        <h5 class="mb-3"><i class="fas fa-history me-2"></i>Recent Activity</h5>
                        <?php if (!empty($recent_activity)): ?>
                            <?php foreach ($recent_activity as $activity): ?>
                            <div class="activity-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($activity['employee_name']); ?></h6>
                                        <p class="mb-1 text-muted">
                                            <?php echo htmlspecialchars($activity['employee_designation']); ?> -
                                            <?php echo htmlspecialchars($activity['employee_department']); ?>
                                        </p>
                                        <small class="text-muted">
                                            Submitted: <?php echo date('M d, Y', strtotime($activity['created_at'])); ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <?php
                                        $statusClass = match($activity['status']) {
                                            'pending' => 'bg-warning',
                                            'approved' => 'bg-success',
                                            'rejected' => 'bg-danger',
                                            'completed' => 'bg-info',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?> status-badge">
                                            <?php echo ucfirst($activity['status']); ?>
                                        </span>
                                        <div class="mt-1">
                                            <small class="text-muted">
                                                Last Day: <?php echo date('M d, Y', strtotime($activity['last_working_day'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>No recent offboarding activity</p>
                            </div>
                        <?php endif; ?>
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
    </script>
</body>
</html>