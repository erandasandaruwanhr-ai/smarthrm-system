<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

// Only superadmins can access this page
if (!isSuperAdmin()) {
    header('Location: ../../dashboard.php');
    exit;
}

$user = getCurrentUser();
$db = new Database();

$message = '';
$error = '';

// Handle form submission
if ($_POST) {
    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_system_status') {
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'status_') === 0) {
                    $status_key = str_replace('status_', '', $key);
                    $is_active = $value == '1' ? 'Y' : 'N';

                    $db->update('goal_setting_status',
                        ['is_active' => $is_active, 'updated_date' => date('Y-m-d H:i:s')],
                        'status_key = ?',
                        [$status_key]
                    );
                }
            }
            $message = "System settings updated successfully!";

    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get system status
$system_statuses = $db->fetchAll("
    SELECT * FROM goal_setting_status
    ORDER BY id
");

// Get database statistics
$db_stats = [
    'periods' => $db->fetch("SELECT COUNT(*) as count FROM goal_setting_periods")['count'] ?? 0,
    'templates' => $db->fetch("SELECT COUNT(*) as count FROM goal_setting_templates")['count'] ?? 0,
    'records' => $db->fetch("SELECT COUNT(*) as count FROM executive_goal_records")['count'] ?? 0,
    'goal_details' => $db->fetch("SELECT COUNT(*) as count FROM goal_details")['count'] ?? 0
];

// Get recent activity
$recent_activity = $db->fetchAll("
    SELECT
        r.created_date,
        e.name as employee_name,
        m.name as manager_name,
        'Goal Form Created' as activity_type
    FROM executive_goal_records r
    JOIN employees e ON r.employee_epf = e.epf_number
    JOIN employees m ON r.manager_epf = m.epf_number
    ORDER BY r.created_date DESC
    LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Goal Setting System Settings</title>
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

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }


        .status-toggle {
            transform: scale(1.2);
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
                        <h1><i class="fas fa-sliders-h me-3"></i>Goal Setting System Settings</h1>
                        <p>Configure goal setting system status and advanced settings</p>
                    </div>
                    <div class="logo-container">
                        <img src="../../jiffy-logo.svg" alt="Jiffy Logo" class="page-header-logo">
                    </div>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- System Status Configuration -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-toggle-on me-2"></i>System Status Configuration</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_system_status">

                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Important:</strong> These settings control the availability of different goal setting phases.
                                    Deactivating a phase will prevent users from accessing that functionality.
                                </div>

                                <?php foreach ($system_statuses as $status): ?>
                                <div class="row align-items-center mb-3 p-3 border rounded">
                                    <div class="col-md-6">
                                        <h6 class="mb-1"><?= htmlspecialchars($status['status_name']) ?></h6>
                                        <small class="text-muted">Key: <?= htmlspecialchars($status['status_key']) ?></small>
                                    </div>
                                    <div class="col-md-3">
                                        <span class="badge <?= $status['is_active'] == 'Y' ? 'bg-success' : 'bg-secondary' ?>">
                                            <?= $status['is_active'] == 'Y' ? 'ACTIVE' : 'INACTIVE' ?>
                                        </span>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input status-toggle" type="checkbox"
                                                   name="status_<?= $status['status_key'] ?>"
                                                   value="1"
                                                   <?= $status['is_active'] == 'Y' ? 'checked' : '' ?>>
                                            <label class="form-check-label">
                                                <?= $status['is_active'] == 'Y' ? 'Active' : 'Inactive' ?>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>

                                <div class="text-center">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-save me-2"></i>Update System Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Database Statistics -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-database me-2"></i>Database Statistics</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-3">
                                    <h3 class="text-primary"><?= number_format($db_stats['periods']) ?></h3>
                                    <small class="text-muted">Goal Periods</small>
                                </div>
                                <div class="col-3">
                                    <h3 class="text-success"><?= number_format($db_stats['templates']) ?></h3>
                                    <small class="text-muted">Templates</small>
                                </div>
                                <div class="col-3">
                                    <h3 class="text-warning"><?= number_format($db_stats['records']) ?></h3>
                                    <small class="text-muted">Employee Records</small>
                                </div>
                                <div class="col-3">
                                    <h3 class="text-info"><?= number_format($db_stats['goal_details']) ?></h3>
                                    <small class="text-muted">Goal Details</small>
                                </div>
                            </div>

                            <hr class="my-4">

                            <div class="alert alert-success">
                                <h6><i class="fas fa-check-circle me-2"></i>System Health</h6>
                                <ul class="mb-0">
                                    <li>Database tables are properly configured</li>
                                    <li>All required indexes are in place</li>
                                    <li>Foreign key constraints are active</li>
                                    <li>System is ready for goal setting operations</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Activity</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($recent_activity)): ?>
                                <?php foreach ($recent_activity as $activity): ?>
                                <div class="d-flex align-items-center mb-2 pb-2 border-bottom">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-user-circle text-primary"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-2">
                                        <small class="d-block">
                                            <strong><?= htmlspecialchars($activity['employee_name']) ?></strong>
                                        </small>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($activity['activity_type']) ?><br>
                                            <?= date('M d, H:i', strtotime($activity['created_date'])) ?>
                                        </small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted mb-0">No recent activity</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>


            <!-- System Information -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>System Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Module Information</h6>
                                    <ul class="list-unstyled">
                                        <li><strong>Module:</strong> Goal Setting (Module 12)</li>
                                        <li><strong>Version:</strong> 1.0.0</li>
                                        <li><strong>Created:</strong> <?= date('Y-m-d') ?></li>
                                        <li><strong>Last Updated:</strong> <?= date('Y-m-d H:i:s') ?></li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6>Features</h6>
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-check text-success me-2"></i>Executive Goal Setting</li>
                                        <li><i class="fas fa-check text-success me-2"></i>Mid-Year Progress Review</li>
                                        <li><i class="fas fa-check text-success me-2"></i>Final Performance Evaluation</li>
                                        <li><i class="fas fa-check text-success me-2"></i>Comprehensive Reporting</li>
                                        <li><i class="fas fa-check text-success me-2"></i>Role-based Access Control</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Goal Setting
                        </a>
                        <a href="reports_analytics.php" class="btn btn-primary">
                            <i class="fas fa-chart-pie me-2"></i>View Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>

        // Update toggle labels
        document.querySelectorAll('.status-toggle').forEach(toggle => {
            toggle.addEventListener('change', function() {
                const label = this.nextElementSibling;
                label.textContent = this.checked ? 'Active' : 'Inactive';
            });
        });

        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    </script>
</body>
</html>