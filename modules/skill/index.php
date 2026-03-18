<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/location_access.php';

$user = getCurrentUser();
$db = new Database();

// Check user permissions
$isAdmin = isAdmin() || isSuperAdmin();
$canManageSkills = $isAdmin || hasModulePermission($db, 'skill.matrix_setup');
$canViewSkills = $isAdmin || hasModulePermission($db, 'skill.view_skill_matrix') || hasModulePermission($db, 'skill.complete_skill_assessment');

// Redirect if no access at all
if (!$isAdmin && !$canManageSkills && !$canViewSkills) {
    header('Location: ../../dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Skill Matrix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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

        .stats-card h4 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #2c3e50;
        }

        .stats-card p {
            color: #7f8c8d;
            margin: 0 0 1rem;
            font-weight: 400;
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
                        <h1><i class="fas fa-clipboard-list me-3"></i>Skill Matrix</h1>
                        <p>Comprehensive skill assessment and competency tracking system for employee development</p>
                    </div>
                    <div class="logo-container">
                        <img src="../../jiffy-logo.svg" alt="Jiffy Logo" class="page-header-logo">
                    </div>
                </div>
            </div>

            <!-- Action Cards -->
            <div class="row mb-4">
                <!-- Skill Matrix Setup -->
                <?php if (hasModulePermission($db, 'skill.matrix_setup')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="setup_working.php" class="text-decoration-none">
                        <div class="stats-card warning action-card">
                            <div class="icon">
                                <i class="fas fa-cogs"></i>
                            </div>
                            <h4 class="mb-2">Skill Matrix Setup</h4>
                            <p class="mb-0">Configure skill categories and assessment criteria</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Skill Assessment -->
                <?php if (hasModulePermission($db, 'skill.skill_assessment')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="assessment_working.php" class="text-decoration-none">
                        <div class="stats-card success action-card">
                            <div class="icon">
                                <i class="fas fa-clipboard-check"></i>
                            </div>
                            <h4 class="mb-2">Skill Assessment</h4>
                            <p class="mb-0">Conduct assessments for team members</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Assessment Reports -->
                <?php if (hasModulePermission($db, 'skill.assessment_reports')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="reports_working.php" class="text-decoration-none">
                        <div class="stats-card info action-card">
                            <div class="icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <h4 class="mb-2">Assessment Reports</h4>
                            <p class="mb-0">View reports and analyze skill gaps</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Employee List -->
                <?php if (hasModulePermission($db, 'skill.employee_list')): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="employee_list.php" class="text-decoration-none">
                        <div class="stats-card primary action-card">
                            <div class="icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h4 class="mb-2">Employee List</h4>
                            <p class="mb-0">View employees eligible for assessment</p>
                        </div>
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Information Panel -->
            <div class="row">
                <div class="col-12">
                    <div class="activity-card">
                        <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i>Skill Matrix Overview</h5>
                        <div class="row">
                            <div class="col-md-4">
                                <h6 class="text-primary mb-2">Skill Assessment</h6>
                                <p class="small text-muted mb-3">Evaluate employee competency across different skill categories with structured scoring criteria.</p>
                            </div>
                            <div class="col-md-4">
                                <h6 class="text-success mb-2">Competency Tracking</h6>
                                <p class="small text-muted mb-3">Monitor skill development progress and identify training needs for continuous improvement.</p>
                            </div>
                            <div class="col-md-4">
                                <h6 class="text-warning mb-2">Skills Development</h6>
                                <ul class="small text-muted mb-0">
                                    <li><strong>Technical Skills:</strong> Job-specific competencies</li>
                                    <li><strong>Soft Skills:</strong> Communication & leadership</li>
                                    <li><strong>Professional Skills:</strong> Industry knowledge</li>
                                    <li><strong>Growth Areas:</strong> Development opportunities</li>
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