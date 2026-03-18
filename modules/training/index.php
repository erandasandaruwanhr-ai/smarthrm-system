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

// Build role-based filter for training requirements
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

// Get statistics for dashboard cards
try {
    $stats = [];
    $current_year = date('Y');

    // Training Requirements
    $req_params = array_merge([$current_year], $tr_params);
    $stats['requirements'] = $db->fetch("SELECT COUNT(*) as count FROM training_requirements tr WHERE tr.year = ?" . $tr_filter, $req_params)['count'] ?? 0;

    // Approved Budget Items
    $stats['budgeted'] = $db->fetch("SELECT COUNT(*) as count FROM training_budget tb JOIN training_requirements tr ON tb.requirement_id = tr.id WHERE tr.year = ? AND tb.add_to_budget = 1" . $tr_filter, $req_params)['count'] ?? 0;

    // Planned Trainings
    $stats['planned'] = $db->fetch("SELECT COUNT(*) as count FROM training_plans tp JOIN training_requirements tr ON tp.requirement_id = tr.id WHERE tr.year = ?" . $tr_filter, $req_params)['count'] ?? 0;

    // Completed Evaluations
    $stats['evaluations'] = $db->fetch("SELECT COUNT(*) as count FROM training_evaluations te JOIN training_plans tp ON te.training_plan_id = tp.id JOIN training_requirements tr ON tp.requirement_id = tr.id WHERE tr.year = ?" . $tr_filter, $req_params)['count'] ?? 0;

    // Total Budget Amount
    $budget_result = $db->fetch("SELECT SUM(tb.budget_amount) as total FROM training_budget tb JOIN training_requirements tr ON tb.requirement_id = tr.id WHERE tr.year = ? AND tb.add_to_budget = 1" . $tr_filter, $req_params);
    $stats['budget'] = $budget_result['total'] ?? 0;

} catch (Exception $e) {
    $stats = [
        'requirements' => 0,
        'budgeted' => 0,
        'planned' => 0,
        'evaluations' => 0,
        'budget' => 0
    ];
}

// Check if we have any training data (to show setup message or not)
$has_data = ($stats['requirements'] > 0 || $stats['budgeted'] > 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Training Management</title>
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
                        <h1><i class="fas fa-graduation-cap me-3"></i>Training Management</h1>
                        <p>Comprehensive training lifecycle management system for <?php echo date('Y'); ?></p>
                    </div>
                    <div class="logo-container">
                        <img src="../../jiffy-logo.svg" alt="Jiffy Logo" class="page-header-logo">
                    </div>
                </div>
            </div>

            <?php if (!$has_data): ?>
            <!-- Setup Notice -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="activity-card">
                        <h5 class="mb-3"><i class="fas fa-info-circle me-2 text-primary"></i>Getting Started</h5>
                        <p class="text-muted mb-3">Welcome to Training Management! To get started, set up the database and begin with training requirements.</p>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="setup_database.php" class="btn btn-primary">
                                <i class="fas fa-database me-2"></i>Setup Database
                            </a>
                            <a href="requirements.php" class="btn btn-success">
                                <i class="fas fa-clipboard-list me-2"></i>Add Requirements
                            </a>
                            <a href="budget.php" class="btn btn-info">
                                <i class="fas fa-money-check-alt me-2"></i>Manage Budget
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card primary">
                        <div class="icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <h3><?php echo number_format($stats['requirements']); ?></h3>
                        <p>Requirements</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card success">
                        <div class="icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3><?php echo number_format($stats['budgeted']); ?></h3>
                        <p>Budgeted Items</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card info">
                        <div class="icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h3><?php echo number_format($stats['planned']); ?></h3>
                        <p>Planned Trainings</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card warning">
                        <div class="icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <h3><?php echo number_format($stats['evaluations']); ?></h3>
                        <p>Evaluations</p>
                    </div>
                </div>
            </div>

            <!-- Action Cards -->
            <div class="row mb-4">
                <?php if (hasModulePermission($db, 'training.requirements')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="requirements.php" class="text-decoration-none">
                        <div class="stats-card primary action-card">
                            <div class="icon">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <h4 class="mb-2">Training Requirements</h4>
                            <p class="mb-0">Manage training needs assessment and approval workflows</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <?php if (hasModulePermission($db, 'training.budget')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="budget.php" class="text-decoration-none">
                        <div class="stats-card success action-card">
                            <div class="icon">
                                <i class="fas fa-money-check-alt"></i>
                            </div>
                            <h4 class="mb-2">Budget Management</h4>
                            <p class="mb-0">Plan, approve, and track training budgets with cost analysis</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <?php if (hasModulePermission($db, 'training.plans')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="plans.php" class="text-decoration-none">
                        <div class="stats-card info action-card">
                            <div class="icon">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <h4 class="mb-2">Training Plans</h4>
                            <p class="mb-0">Create and manage detailed training schedules</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <?php if (hasModulePermission($db, 'training.evaluation')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="evaluation.php" class="text-decoration-none">
                        <div class="stats-card warning action-card">
                            <div class="icon">
                                <i class="fas fa-star-half-alt"></i>
                            </div>
                            <h4 class="mb-2">Training Evaluation</h4>
                            <p class="mb-0">Collect trainee feedback on training effectiveness</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <?php if (hasModulePermission($db, 'training.feedback')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="feedback.php" class="text-decoration-none">
                        <div class="stats-card danger action-card">
                            <div class="icon">
                                <i class="fas fa-comments"></i>
                            </div>
                            <h4 class="mb-2">Effectiveness Feedback</h4>
                            <p class="mb-0">Supervisor assessments on skill transfer and ROI</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <?php if (hasModulePermission($db, 'training.tracker')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="tracker.php" class="text-decoration-none">
                        <div class="stats-card primary action-card">
                            <div class="icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <h4 class="mb-2">Training Tracker</h4>
                            <p class="mb-0">Monitor training progress and completion status</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <?php if (hasModulePermission($db, 'training.management')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="management.php" class="text-decoration-none">
                        <div class="stats-card success action-card">
                            <div class="icon">
                                <i class="fas fa-cogs"></i>
                            </div>
                            <h4 class="mb-2">Management Review</h4>
                            <p class="mb-0">Managerial comments and strategic decisions</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <?php if (hasModulePermission($db, 'training.reports')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="reports.php" class="text-decoration-none">
                        <div class="stats-card info action-card">
                            <div class="icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <h4 class="mb-2">Reports & Analytics</h4>
                            <p class="mb-0">Comprehensive training reports and ROI analysis</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

            </div>

            <!-- Quick Actions -->
            <div class="row">
                <div class="col-12">
                    <div class="activity-card">
                        <h5 class="mb-3"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                        <div class="row g-2">
                            <div class="col-md-3">
                                <a href="requirements.php" class="btn btn-outline-primary btn-sm w-100">
                                    <i class="fas fa-plus me-2"></i>New Requirement
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="budget.php" class="btn btn-outline-success btn-sm w-100">
                                    <i class="fas fa-money-check-alt me-2"></i>Manage Budget
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="plans.php" class="btn btn-outline-warning btn-sm w-100">
                                    <i class="fas fa-calendar-plus me-2"></i>Schedule Training
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="tracker.php" class="btn btn-outline-info btn-sm w-100">
                                    <i class="fas fa-chart-line me-2"></i>View Progress
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
        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    </script>
</body>
</html>