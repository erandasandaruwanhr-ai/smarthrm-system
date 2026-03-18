<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/performance_auth.php';

// Check if user has executive appraisal permission
requirePerformancePermission('performance.executive_appraisal');

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
                'type' => 'executive',
                'created_by' => getPerformanceUserEPF(),
                'created_date' => date('Y-m-d H:i:s')
            ];

            // Check if period already exists for this year and type
            $existing = $db->fetch("
                SELECT id FROM executive_appraisal_periods
                WHERE appraisal_year = ? AND type = 'executive'
            ", [$_POST['appraisal_year']]);

            if ($existing) {
                $error = "Executive appraisal period already exists for year {$_POST['appraisal_year']}!";
            } else {
                $db->insert('executive_appraisal_periods', $data);
                $message = "Executive appraisal period created successfully for year {$_POST['appraisal_year']}!";
            }

        } elseif ($action === 'update_status') {
            $period_id = $_POST['period_id'];
            $new_status = $_POST['status'];

            $db->update('executive_appraisal_periods',
                ['status' => $new_status],
                'id = ?',
                [$period_id]
            );
            $message = "Executive appraisal period status updated successfully!";

        } elseif ($action === 'bulk_assign') {
            $period_id = $_POST['period_id'];
            $supervisor_epf = getPerformanceUserEPF();

            // Get executives under current supervisor
            $executives = $db->fetchAll("
                SELECT e.epf_number, e.name
                FROM employees e
                WHERE e.is_active = 1
                AND e.employment_level_id IN (3, 4, 5, 6, 7)
                AND e.reports_to = ?
            ", [$supervisor_epf]);

            $assigned_count = 0;
            foreach ($executives as $executive) {
                // Check if already assigned
                $existing = $db->fetch("
                    SELECT id FROM executive_assignments
                    WHERE period_id = ? AND executive_epf = ?
                ", [$period_id, $executive['epf_number']]);

                if (!$existing) {
                    $db->insert('executive_assignments', [
                        'period_id' => $period_id,
                        'executive_epf' => $executive['epf_number'],
                        'executive_name' => $executive['name'],
                        'supervisor_epf' => $supervisor_epf,
                        'supervisor_name' => $user['name'],
                        'assigned_date' => date('Y-m-d H:i:s'),
                        'status' => 'pending'
                    ]);
                    $assigned_count++;
                }
            }

            $message = "Successfully assigned $assigned_count executives for appraisal.";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get existing executive appraisal periods
$appraisal_periods = $db->fetchAll("
    SELECT * FROM executive_appraisal_periods
    WHERE type = 'executive'
    ORDER BY appraisal_year DESC, created_date DESC
");

// Get assignments for current user
$current_assignments = $db->fetchAll("
    SELECT
        ea.*,
        eap.appraisal_year,
        eap.status as period_status,
        CASE WHEN ea_data.id IS NOT NULL THEN 'completed' ELSE 'pending' END as appraisal_status
    FROM executive_assignments ea
    JOIN executive_appraisal_periods eap ON ea.period_id = eap.id
    LEFT JOIN executive_appraisals ea_data ON (ea_data.appraisee_epf = ea.executive_epf AND ea_data.appraisal_year = eap.appraisal_year)
    WHERE ea.supervisor_epf = ?
    ORDER BY eap.appraisal_year DESC, ea.executive_name
", [getPerformanceUserEPF()]);

// Get current year for default (start from 2025)
$current_year = max(2025, date('Y'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Executive Appraisal Setup (13.7)</title>
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

        .setup-card {
            border-left: 4px solid var(--success-color);
            margin-bottom: 20px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
        }

        .period-card {
            border: 1px solid #dee2e6;
            border-radius: 15px;
            margin-bottom: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .assignment-card {
            border: 1px solid #dee2e6;
            border-radius: 15px;
            margin-bottom: 10px;
            padding: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
        }

        .period-status, .assignment-status {
            font-weight: bold;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
        }

        .status-active { background: #d4edda; color: #155724; }
        .status-closed { background: #d1ecf1; color: #0c5460; }
        .status-draft { background: #fff3cd; color: #856404; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-completed { background: #d4edda; color: #155724; }

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
                        <h1><i class="fas fa-cogs me-3"></i>Executive Appraisal Setup</h1>
                        <p>Configure executive performance appraisal periods and assignments</p>
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
                        <h1 class="h3 mb-0"><i class="fas fa-user-tie text-success me-2"></i>Executive Appraisal Setup</h1>
                        <p class="text-muted mb-0">Configure executive appraisal periods and assignments (13.7)</p>
                    </div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="index.php">Performance</a></li>
                            <li class="breadcrumb-item active">Executive Setup</li>
                        </ol>
                    </nav>
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

                <div class="row">
                    <!-- Left Column: Setup -->
                    <div class="col-md-6">
                        <!-- Create New Appraisal Period -->
                        <div class="card setup-card mb-4">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-plus-circle text-success me-2"></i>Create Executive Appraisal Period
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="create_period">
                                    <div class="row">
                                        <div class="col-12">
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
                                        <div class="col-6">
                                            <div class="mb-3">
                                                <label class="form-label">Period Start Date</label>
                                                <input type="date" name="start_date" class="form-control"
                                                       value="2025-01-01" required>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="mb-3">
                                                <label class="form-label">Period End Date</label>
                                                <input type="date" name="end_date" class="form-control"
                                                       value="2025-12-31" required>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-success w-100">
                                                <i class="fas fa-save me-1"></i>Create Executive Period
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Existing Appraisal Periods -->
                        <div class="card">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-calendar text-success me-2"></i>Executive Appraisal Periods
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($appraisal_periods)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-calendar-plus text-muted" style="font-size: 2em;"></i>
                                        <p class="mt-2 text-muted">No periods created yet.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($appraisal_periods as $period): ?>
                                        <div class="period-card">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="card-title mb-0">
                                                        <i class="fas fa-calendar-alt text-success me-1"></i>
                                                        Year <?= htmlspecialchars($period['appraisal_year']) ?>
                                                    </h6>
                                                    <span class="period-status status-<?= strtolower($period['status']) ?>">
                                                        <?= ucfirst($period['status']) ?>
                                                    </span>
                                                </div>

                                                <div class="small text-muted mb-3">
                                                    <div><i class="fas fa-play me-1"></i><?= date('M d, Y', strtotime($period['start_date'])) ?> - <?= date('M d, Y', strtotime($period['end_date'])) ?></div>
                                                    <div><i class="fas fa-user me-1"></i>Created by: <?= htmlspecialchars($period['created_by']) ?></div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-8">
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="update_status">
                                                            <input type="hidden" name="period_id" value="<?= $period['id'] ?>">
                                                            <div class="input-group input-group-sm">
                                                                <select name="status" class="form-select">
                                                                    <option value="draft" <?= $period['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                                                                    <option value="active" <?= $period['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                                    <option value="closed" <?= $period['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                                                                </select>
                                                                <button type="submit" class="btn btn-outline-success btn-sm">
                                                                    <i class="fas fa-save"></i>
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                    <div class="col-4">
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="bulk_assign">
                                                            <input type="hidden" name="period_id" value="<?= $period['id'] ?>">
                                                            <button type="submit" class="btn btn-outline-primary btn-sm w-100"
                                                                    title="Assign your executives">
                                                                <i class="fas fa-users"></i> Assign
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Assignments -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-tasks text-success me-2"></i>My Executive Assignments
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($current_assignments)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-user-friends text-muted" style="font-size: 2em;"></i>
                                        <h6 class="mt-2 text-muted">No Assignments Yet</h6>
                                        <p class="text-muted small">Create a period and assign executives to get started.</p>
                                    </div>
                                <?php else: ?>
                                    <?php
                                    $grouped_assignments = [];
                                    foreach ($current_assignments as $assignment) {
                                        $grouped_assignments[$assignment['appraisal_year']][] = $assignment;
                                    }
                                    ?>
                                    <?php foreach ($grouped_assignments as $year => $assignments): ?>
                                        <h6 class="text-success border-bottom pb-2 mb-3">
                                            <i class="fas fa-calendar me-1"></i>Year <?= $year ?>
                                        </h6>
                                        <?php foreach ($assignments as $assignment): ?>
                                            <div class="assignment-card">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <strong><?= htmlspecialchars($assignment['executive_name']) ?></strong><br>
                                                        <small class="text-muted">EPF: <?= htmlspecialchars($assignment['executive_epf']) ?></small><br>
                                                        <small class="text-muted">Assigned: <?= date('M d, Y', strtotime($assignment['assigned_date'])) ?></small>
                                                    </div>
                                                    <div class="text-end">
                                                        <span class="assignment-status status-<?= $assignment['appraisal_status'] ?>">
                                                            <?= ucfirst($assignment['appraisal_status']) ?>
                                                        </span>
                                                        <?php if ($assignment['appraisal_status'] === 'pending' && $assignment['period_status'] === 'active'): ?>
                                                            <div class="mt-2">
                                                                <a href="executive_appraisal.php?epf=<?= urlencode($assignment['executive_epf']) ?>&year=<?= $year ?>"
                                                                   class="btn btn-outline-success btn-sm">
                                                                    <i class="fas fa-edit me-1"></i>Start
                                                                </a>
                                                            </div>
                                                        <?php elseif ($assignment['appraisal_status'] === 'completed'): ?>
                                                            <div class="mt-2">
                                                                <a href="executive_appraisal.php?epf=<?= urlencode($assignment['executive_epf']) ?>&year=<?= $year ?>"
                                                                   class="btn btn-outline-info btn-sm">
                                                                    <i class="fas fa-eye me-1"></i>View
                                                                </a>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
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
                                <a href="executive_list.php" class="btn btn-outline-success w-100 mb-3">
                                    <i class="fas fa-user-tie d-block mb-2" style="font-size: 1.5em;"></i>
                                    <small>Executive List (13.2)</small>
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="executive_appraisal.php" class="btn btn-outline-primary w-100 mb-3">
                                    <i class="fas fa-clipboard-check d-block mb-2" style="font-size: 1.5em;"></i>
                                    <small>Executive Appraisal (13.8)</small>
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="executive_marks_data.php" class="btn btn-outline-info w-100 mb-3">
                                    <i class="fas fa-chart-line d-block mb-2" style="font-size: 1.5em;"></i>
                                    <small>Marks Data (13.9)</small>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    </script>
</body>
</html>