<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';
require_once '../../includes/location_access.php';

$user = getCurrentUser();
$database = new Database();

// Check if user has access to Key Talent module
if (!isSuperAdmin() && !hasModulePermission($database, 'key_talent.view')) {
    header('Location: ../../dashboard.php');
    exit;
}

// Build role-based filter
$account_type = $user['account_type'] ?? 'user';
$emp_filter = '';
$emp_params = [];

if ($account_type === 'superadmin') {
    // No filter
} elseif ($account_type === 'admin') {
    $emp_filter = ' AND location_id = ?';
    $emp_params = [$user['location_id']];
} elseif (in_array($account_type, ['manager', 'supervisor'])) {
    $accessible = getAccessibleEmployeesByLocation($database, $user);
    $accessible_epfs = array_column($accessible, 'epf_number');
    if (!empty($accessible_epfs)) {
        $placeholders = str_repeat('?,', count($accessible_epfs) - 1) . '?';
        $emp_filter = " AND epf_number IN ($placeholders)";
        $emp_params = $accessible_epfs;
    } else {
        $emp_filter = ' AND 1=0';
    }
} else {
    $emp_filter = ' AND epf_number = ?';
    $emp_params = [$user['epf_number']];
}

// Get Key Talent Identification statistics
try {
    // Total employees in the system (talent candidate pool) - filtered by role
    $total_employees_query = "SELECT COUNT(*) as count FROM employees WHERE 1=1" . $emp_filter;
    $total_employees = $database->fetchAll($total_employees_query, $emp_params)[0]['count'] ?? 0;

    // Total managers who can assess (account_type = 'manager') - filtered by role
    $mgr_filter = str_replace('epf_number', 'epf_number', $emp_filter);
    $total_managers_query = "SELECT COUNT(*) as count FROM employees WHERE account_type = 'manager'" . $emp_filter;
    $total_managers = $database->fetchAll($total_managers_query, $emp_params)[0]['count'] ?? 0;

    // Total assessments completed (will need to create table)
    $completed_assessments = 0; // Placeholder for now

    // Available assessment years
    $assessment_years = 0; // Placeholder for now

} catch (Exception $e) {
    $total_employees = 0;
    $total_managers = 0;
    $completed_assessments = 0;
    $assessment_years = 0;
}

// Get current year for default setup
$current_year = date('Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Key Talent Identification</title>
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
                        <h1><i class="fas fa-star me-3"></i>Key Talent Identification</h1>
                        <p>Comprehensive talent assessment and identification system for strategic workforce planning</p>
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
                            <i class="fas fa-users"></i>
                        </div>
                        <h3><?php echo number_format($total_employees); ?></h3>
                        <p>Total Talent Pool</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card success">
                        <div class="icon">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <h3><?php echo number_format($total_managers); ?></h3>
                        <p>Active Assessors</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card warning">
                        <div class="icon">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <h3><?php echo number_format($completed_assessments); ?></h3>
                        <p>Assessments Completed</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="stats-card info">
                        <div class="icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h3><?php echo $current_year; ?></h3>
                        <p>Current Assessment Year</p>
                    </div>
                </div>
            </div>

            <!-- Action Cards -->
            <div class="row mb-4">
                <!-- Talent Candidates List -->
                <?php if (hasModulePermission($database, 'key_talent.candidates_list')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="talent_candidates.php" class="text-decoration-none">
                        <div class="stats-card primary action-card">
                            <div class="icon">
                                <i class="fas fa-address-book"></i>
                            </div>
                            <h4 class="mb-2">Talent Candidates List</h4>
                            <p class="mb-0">Complete list of all employees eligible for talent assessment</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- KTI Form Setup -->
                <?php if (hasModulePermission($database, 'key_talent.form_setup')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="kti_form_setup.php" class="text-decoration-none">
                        <div class="stats-card warning action-card">
                            <div class="icon">
                                <i class="fas fa-cog"></i>
                            </div>
                            <h4 class="mb-2">KTI Form Setup</h4>
                            <p class="mb-0">Configure assessment questions and scoring criteria</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Assessment Form -->
                <?php if (hasModulePermission($database, 'key_talent.conduct_assessment')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="assessment_form.php" class="text-decoration-none">
                        <div class="stats-card success action-card">
                            <div class="icon">
                                <i class="fas fa-clipboard-check"></i>
                            </div>
                            <h4 class="mb-2">Conduct Assessment</h4>
                            <p class="mb-0">Evaluate team members using structured framework</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Assessment Results -->
                <?php if (hasModulePermission($database, 'key_talent.assessment_results')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="marks_allocations.php" class="text-decoration-none">
                        <div class="stats-card info action-card">
                            <div class="icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <h4 class="mb-2">Assessment Results</h4>
                            <p class="mb-0">View submitted assessments with detailed breakdown</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Talent Grid Dashboard -->
                <?php if (hasModulePermission($database, 'key_talent.talent_grid')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="talent_grid.php" class="text-decoration-none">
                        <div class="stats-card primary action-card">
                            <div class="icon">
                                <i class="fas fa-th"></i>
                            </div>
                            <h4 class="mb-2">9-Box Talent Grid</h4>
                            <p class="mb-0">Interactive talent matrix by Performance vs Potential</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Reports & Analytics -->
                <?php if (hasModulePermission($database, 'key_talent.reports')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="talent_reports.php" class="text-decoration-none">
                        <div class="stats-card danger action-card">
                            <div class="icon">
                                <i class="fas fa-download"></i>
                            </div>
                            <h4 class="mb-2">Export & Reports</h4>
                            <p class="mb-0">Download comprehensive talent reports and analytics</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Information Panel -->
            <div class="row">
                <div class="col-12">
                    <div class="activity-card">
                        <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i>Key Talent Identification Overview</h5>
                        <div class="row">
                            <div class="col-md-4">
                                <h6 class="text-primary mb-2">Performance Assessment</h6>
                                <p class="small text-muted mb-3">Evaluates current job performance across 10 key dimensions with weighted scoring from 0-100%.</p>
                            </div>
                            <div class="col-md-4">
                                <h6 class="text-success mb-2">Potential Assessment</h6>
                                <p class="small text-muted mb-3">Measures future leadership potential and growth capacity through 10 structured criteria.</p>
                            </div>
                            <div class="col-md-4">
                                <h6 class="text-warning mb-2">9-Box Talent Matrix</h6>
                                <ul class="small text-muted mb-0">
                                    <li><strong>High Performers:</strong> Ready for promotion</li>
                                    <li><strong>Solid Citizens:</strong> Consistent contributors</li>
                                    <li><strong>Rising Stars:</strong> Developing potential</li>
                                    <li><strong>Expert Contributors:</strong> Technical specialists</li>
                                </ul>
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