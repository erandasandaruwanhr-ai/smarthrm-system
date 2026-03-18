<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/performance_auth.php';

// Check if user has staff appraisal setup permission
requirePerformancePermission('performance.staff_appraisal');

$user = getCurrentUser();
$db = new Database();

$message = '';
$error = '';

// Handle form submission
if ($_POST) {
    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'create_period') {
            $data = [
                'appraisal_year' => $_POST['appraisal_year'],
                'start_date' => $_POST['start_date'],
                'end_date' => $_POST['end_date'],
                'status' => 'active',
                'created_by' => getPerformanceUserEPF(),
                'created_date' => date('Y-m-d H:i:s')
            ];

            $db->insert('appraisal_periods', $data);
            $message = "Appraisal period created successfully for year {$_POST['appraisal_year']}!";

        } elseif ($action === 'update_status') {
            $period_id = $_POST['period_id'];
            $new_status = $_POST['status'];

            $db->update('appraisal_periods',
                ['status' => $new_status],
                'id = ?',
                [$period_id]
            );
            $message = "Appraisal period status updated successfully!";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get existing appraisal periods
$appraisal_periods = $db->fetchAll("
    SELECT * FROM appraisal_periods
    ORDER BY appraisal_year DESC, created_date DESC
");

// Get current year for default (start from 2025)
$current_year = max(2025, date('Y'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Staff Appraisal Setup (13.4)</title>
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

        .setup-card {
            border-left: 4px solid #007bff;
            margin-bottom: 20px;
        }
        .period-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .period-status {
            font-weight: bold;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
        }
        .status-active { background: #d4edda; color: #155724; }
        .status-closed { background: #d1ecf1; color: #0c5460; }
        .status-draft { background: #fff3cd; color: #856404; }

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
<body class="bg-light">
    <!-- Sidebar -->
    <?php include '../../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="dashboard-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="header-content">
                        <h1><i class="fas fa-cogs me-3"></i>Staff Appraisal Setup</h1>
                        <p>Configure appraisal periods and settings</p>
                    </div>
                    <div class="logo-container">
                        <img src="../../jiffy-logo.svg" alt="Jiffy Logo" class="page-header-logo">
                    </div>
                </div>
            </div>

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

                <!-- Create New Appraisal Period -->
                <div class="card setup-card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-plus-circle text-primary me-2"></i>Create Appraisal Period
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="create_period">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Appraisal Year</label>
                                        <select name="appraisal_year" class="form-select" required>
                                            <option value="2025" <?= $current_year == 2025 ? 'selected' : '' ?>>2025</option>
                                            <option value="2026" <?= $current_year == 2026 ? 'selected' : '' ?>>2026</option>
                                            <option value="2027" <?= $current_year == 2027 ? 'selected' : '' ?>>2027</option>
                                            <option value="2028" <?= $current_year == 2028 ? 'selected' : '' ?>>2028</option>
                                            <option value="2029" <?= $current_year == 2029 ? 'selected' : '' ?>>2029</option>
                                            <option value="2030" <?= $current_year == 2030 ? 'selected' : '' ?>>2030</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Period Start Date</label>
                                        <input type="date" name="start_date" class="form-control"
                                               value="2025-01-01" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Period End Date</label>
                                        <input type="date" name="end_date" class="form-control"
                                               value="2025-12-31" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="submit" class="form-control btn btn-primary">
                                            <i class="fas fa-save me-1"></i>Create Period
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Existing Appraisal Periods -->
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list text-primary me-2"></i>Existing Appraisal Periods
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($appraisal_periods)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-plus text-muted" style="font-size: 3em;"></i>
                                <h5 class="mt-3 text-muted">No Appraisal Periods Found</h5>
                                <p class="text-muted">Create your first appraisal period to get started.</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($appraisal_periods as $period): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="period-card">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <h6 class="card-title mb-0">
                                                        <i class="fas fa-calendar-alt text-primary me-1"></i>
                                                        Year <?= htmlspecialchars($period['appraisal_year']) ?>
                                                    </h6>
                                                    <span class="period-status status-<?= strtolower($period['status']) ?>">
                                                        <?= ucfirst($period['status']) ?>
                                                    </span>
                                                </div>

                                                <div class="small text-muted mb-3">
                                                    <div><i class="fas fa-play me-1"></i>Start: <?= date('M d, Y', strtotime($period['start_date'])) ?></div>
                                                    <div><i class="fas fa-stop me-1"></i>End: <?= date('M d, Y', strtotime($period['end_date'])) ?></div>
                                                    <div><i class="fas fa-user me-1"></i>Created by: <?= htmlspecialchars($period['created_by']) ?></div>
                                                </div>

                                                <!-- Status Update Form -->
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="period_id" value="<?= $period['id'] ?>">
                                                    <div class="input-group input-group-sm">
                                                        <select name="status" class="form-select">
                                                            <option value="draft" <?= $period['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                                                            <option value="active" <?= $period['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                            <option value="closed" <?= $period['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                                                        </select>
                                                        <button type="submit" class="btn btn-outline-primary btn-sm">
                                                            <i class="fas fa-save"></i>
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card mt-4">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-bolt text-warning me-2"></i>Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <a href="staff_list.php" class="btn btn-outline-primary w-100 mb-3">
                                    <i class="fas fa-users d-block mb-2" style="font-size: 1.5em;"></i>
                                    <small>Staff List (13.1)</small>
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="staff_appraisal.php" class="btn btn-outline-success w-100 mb-3">
                                    <i class="fas fa-edit d-block mb-2" style="font-size: 1.5em;"></i>
                                    <small>Staff Appraisal Form (13.5)</small>
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="staff_marks_data.php" class="btn btn-outline-info w-100 mb-3">
                                    <i class="fas fa-chart-bar d-block mb-2" style="font-size: 1.5em;"></i>
                                    <small>Marks Data (13.6)</small>
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="index.php" class="btn btn-outline-secondary w-100 mb-3">
                                    <i class="fas fa-tachometer-alt d-block mb-2" style="font-size: 1.5em;"></i>
                                    <small>Performance Dashboard</small>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    </script>
</body>
</html>