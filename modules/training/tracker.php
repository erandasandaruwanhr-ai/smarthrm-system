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

// Build role-based filter for training data
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

$message = '';
$error = '';

// Auto-update tracker entries based on training plans
if ($_POST && isset($_POST['sync_tracker'])) {
    try {
        // Get all training plans that don't have tracker entries
        $plans_without_tracker = $db->fetchAll("
            SELECT tp.*
            FROM training_plans tp
            LEFT JOIN training_tracker tt ON tp.id = tt.training_plan_id
            WHERE tt.id IS NULL
        ");

        $count = 0;
        foreach ($plans_without_tracker as $plan) {
            $training_id = 'TRN-' . str_pad($plan['id'], 4, '0', STR_PAD_LEFT);

            $stmt = $db->prepare("INSERT INTO training_tracker (training_plan_id, training_id, trainee_name, training_title, training_date, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $plan['id'],
                $training_id,
                $plan['trainee_name'],
                $plan['training_name'],
                $plan['training_start_date'],
                match($plan['status']) {
                    'completed' => 'Completed',
                    'cancelled' => 'Cancelled',
                    default => 'Pending'
                }
            ]);
            $count++;
        }

        // Update tracker statuses based on training plan changes
        $db->query("
            UPDATE training_tracker tt
            JOIN training_plans tp ON tt.training_plan_id = tp.id
            SET tt.status = CASE
                WHEN tp.status = 'completed' THEN 'Completed'
                WHEN tp.status = 'cancelled' THEN 'Cancelled'
                ELSE 'Pending'
            END
        ");

        // Update evaluation status
        $db->query("
            UPDATE training_tracker tt
            SET tt.evaluation_submitted = 'Y'
            WHERE EXISTS (
                SELECT 1 FROM training_evaluations te
                WHERE te.training_plan_id = tt.training_plan_id
            )
        ");

        // Update feedback status
        $db->query("
            UPDATE training_tracker tt
            SET tt.feedback_submitted = 'Y'
            WHERE EXISTS (
                SELECT 1 FROM training_feedback tf
                WHERE tf.training_plan_id = tt.training_plan_id
            )
        ");

        // Update managerial comments status
        $db->query("
            UPDATE training_tracker tt
            SET tt.managerial_comments_submitted = 'Y'
            WHERE EXISTS (
                SELECT 1 FROM training_managerial_comments tmc
                JOIN training_feedback tf ON tmc.training_feedback_id = tf.id
                WHERE tf.training_plan_id = tt.training_plan_id
            )
        ");

        $message = "Tracker synchronized successfully! Added $count new entries and updated all statuses.";
    } catch (Exception $e) {
        $error = "Error synchronizing tracker: " . $e->getMessage();
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$year_filter = $_GET['year'] ?? date('Y');

// Build tracker query with role-based filters
$tracker_params = array_merge([$year_filter], $tr_params);

if ($status_filter) {
    $tr_filter .= " AND tp.status = ?";
    $tracker_params[] = $status_filter;
}

$tracker_entries = $db->fetchAll("
    SELECT
        tr.id,
        tr.training_id,
        tr.training_requirement as training_title,
        tr.employee_name as trainee_name,
        tr.epf_number as trainee_epf,
        tp.training_institute,
        tp.training_cost,
        tp.training_start_date,
        tp.training_end_date,
        tp.status,
        CASE WHEN te.id IS NOT NULL THEN 'Yes' ELSE 'No' END as evaluation_submitted,
        CASE WHEN tf.id IS NOT NULL THEN 'Yes' ELSE 'No' END as feedback_submitted,
        CASE WHEN tmc.id IS NOT NULL THEN 'Yes' ELSE 'No' END as managerial_comments_submitted,
        tr.created_at
    FROM training_requirements tr
    LEFT JOIN training_plans tp ON tr.id = tp.requirement_id
    LEFT JOIN training_evaluations te ON tp.id = te.training_plan_id
    LEFT JOIN training_feedback tf ON tp.id = tf.training_plan_id
    LEFT JOIN training_managerial_comments tmc ON tf.id = tmc.training_feedback_id
    WHERE tr.year = ?" . $tr_filter . "
    ORDER BY tp.training_start_date DESC, tr.created_at DESC
", $tracker_params);

// Get statistics from actual training tables with role-based filtering
$stats = $db->fetch("
    SELECT
        COUNT(DISTINCT tr.id) as total_trainings,
        SUM(CASE WHEN tp.status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN tp.status IN ('pending', 'ongoing') THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN tp.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        COUNT(DISTINCT te.id) as evaluations_submitted,
        COUNT(DISTINCT tf.id) as feedback_submitted
    FROM training_requirements tr
    LEFT JOIN training_plans tp ON tr.id = tp.requirement_id
    LEFT JOIN training_evaluations te ON tp.id = te.training_plan_id
    LEFT JOIN training_feedback tf ON tp.id = tf.training_plan_id
    WHERE tr.year = ?" . $tr_filter . "
", array_merge([$year_filter], $tr_params));

// Calculate completion rates
$completion_rate = $stats['total_trainings'] > 0 ? round(($stats['completed'] / $stats['total_trainings']) * 100, 1) : 0;
$evaluation_rate = $stats['completed'] > 0 ? round(($stats['evaluations_submitted'] / $stats['completed']) * 100, 1) : 0;
$feedback_rate = $stats['completed'] > 0 ? round(($stats['feedback_submitted'] / $stats['completed']) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Training Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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

        .content-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 2rem;
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
            font-size: 1.3rem;
            margin: 0 auto 1rem;
        }

        .stats-card h4 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #2c3e50;
        }

        .stats-card p {
            color: #7f8c8d;
            margin: 0;
            font-weight: 500;
        }

        .progress-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            margin: 0 auto 1rem;
            position: relative;
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
                        <h1><i class="fas fa-chart-line me-3"></i>Training Tracker</h1>
                        <p class="mb-0">Monitor training progress, completion status, and evaluation submissions for <?php echo $year_filter; ?></p>
                    </div>
                </div>
            </div>

            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Training Management</a></li>
                    <li class="breadcrumb-item active">Training Tracker</li>
                </ol>
            </nav>

            <!-- Messages -->
            <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="stats-card">
                        <div class="icon" style="background-color: rgba(52, 152, 219, 0.1); color: #3498db;">
                            <i class="fas fa-list"></i>
                        </div>
                        <h4><?php echo number_format($stats['total_trainings'] ?? 0); ?></h4>
                        <p class="mb-0">Total Trainings</p>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="stats-card">
                        <div class="icon" style="background-color: rgba(46, 204, 113, 0.1); color: #2ecc71;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h4><?php echo number_format($stats['completed'] ?? 0); ?></h4>
                        <p class="mb-0">Completed</p>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="stats-card">
                        <div class="icon" style="background-color: rgba(241, 196, 15, 0.1); color: #f1c40f;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h4><?php echo number_format($stats['pending'] ?? 0); ?></h4>
                        <p class="mb-0">Pending</p>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="stats-card">
                        <div class="progress-circle" style="background: conic-gradient(#2ecc71 <?php echo $completion_rate * 3.6; ?>deg, #ecf0f1 0deg);">
                            <?php echo $completion_rate; ?>%
                        </div>
                        <p class="mb-0">Completion Rate</p>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="stats-card">
                        <div class="progress-circle" style="background: conic-gradient(#3498db <?php echo $evaluation_rate * 3.6; ?>deg, #ecf0f1 0deg);">
                            <?php echo $evaluation_rate; ?>%
                        </div>
                        <p class="mb-0">Evaluation Rate</p>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <div class="stats-card">
                        <div class="progress-circle" style="background: conic-gradient(#e74c3c <?php echo $feedback_rate * 3.6; ?>deg, #ecf0f1 0deg);">
                            <?php echo $feedback_rate; ?>%
                        </div>
                        <p class="mb-0">Feedback Rate</p>
                    </div>
                </div>
            </div>

            <!-- Training Tracker -->
            <div class="content-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5>Training Progress Tracker</h5>
                    <div class="d-flex gap-2">
                        <form method="POST" class="d-inline">
                            <button type="submit" name="sync_tracker" class="btn btn-primary btn-sm">
                                <i class="fas fa-sync me-1"></i>Sync Tracker
                            </button>
                        </form>
                        <a href="export_tracker.php?year=<?php echo $year_filter; ?>&status=<?php echo $status_filter; ?>" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-file-excel me-1"></i>Export
                        </a>
                    </div>
                </div>

                <!-- Filters -->
                <form method="GET" class="row mb-4">
                    <div class="col-md-3">
                        <select class="form-select" name="year" onchange="this.form.submit()">
                            <option value="<?php echo date('Y')-1; ?>" <?php echo $year_filter == date('Y')-1 ? 'selected' : ''; ?>><?php echo date('Y')-1; ?></option>
                            <option value="<?php echo date('Y'); ?>" <?php echo $year_filter == date('Y') ? 'selected' : ''; ?>><?php echo date('Y'); ?></option>
                            <option value="<?php echo date('Y')+1; ?>" <?php echo $year_filter == date('Y')+1 ? 'selected' : ''; ?>><?php echo date('Y')+1; ?></option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="status" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Completed" <?php echo $status_filter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="Cancelled" <?php echo $status_filter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped" id="trackerTable">
                        <thead>
                            <tr>
                                <th>Training ID</th>
                                <th>Training Title</th>
                                <th>Trainee</th>
                                <th>Training Date</th>
                                <th>Institute</th>
                                <th>Status</th>
                                <th>Evaluation</th>
                                <th>Feedback</th>
                                <th>Mgmt Comments</th>
                                <th>Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tracker_entries as $entry): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($entry['training_id']); ?></strong></td>
                                <td>
                                    <?php echo htmlspecialchars($entry['training_title']); ?>
                                    <br><small class="text-muted">Cost: LKR <?php echo number_format($entry['training_cost'] ?? 0, 2); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($entry['trainee_name']); ?></td>
                                <td>
                                    <?php if ($entry['training_start_date']): ?>
                                    <strong><?php echo date('M j, Y', strtotime($entry['training_start_date'])); ?></strong>
                                    <?php if ($entry['training_end_date'] && $entry['training_end_date'] !== $entry['training_start_date']): ?>
                                    <br><small class="text-muted">to <?php echo date('M j, Y', strtotime($entry['training_end_date'])); ?></small>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span class="text-muted">Not scheduled</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($entry['training_institute']); ?></td>
                                <td>
                                    <span class="badge bg-<?php
                                        echo match($entry['status']) {
                                            'completed' => 'success',
                                            'cancelled' => 'danger',
                                            'ongoing' => 'primary',
                                            default => 'warning'
                                        };
                                    ?>">
                                        <?php echo ucfirst($entry['status'] ?? 'pending'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $entry['evaluation_submitted'] === 'Yes' ? 'success' : 'secondary'; ?>">
                                        <i class="fas fa-<?php echo $entry['evaluation_submitted'] === 'Yes' ? 'check' : 'times'; ?>"></i>
                                        <?php echo $entry['evaluation_submitted'] === 'Yes' ? 'Done' : 'Pending'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $entry['feedback_submitted'] === 'Yes' ? 'success' : 'secondary'; ?>">
                                        <i class="fas fa-<?php echo $entry['feedback_submitted'] === 'Yes' ? 'check' : 'times'; ?>"></i>
                                        <?php echo $entry['feedback_submitted'] === 'Yes' ? 'Done' : 'Pending'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $entry['managerial_comments_submitted'] === 'Yes' ? 'success' : 'secondary'; ?>">
                                        <i class="fas fa-<?php echo $entry['managerial_comments_submitted'] === 'Yes' ? 'check' : 'times'; ?>"></i>
                                        <?php echo $entry['managerial_comments_submitted'] === 'Yes' ? 'Done' : 'Pending'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $progress = 0;
                                    if (strtolower($entry['status']) === 'completed') $progress += 40;
                                    if ($entry['evaluation_submitted'] === 'Yes') $progress += 25;
                                    if ($entry['feedback_submitted'] === 'Yes') $progress += 25;
                                    if ($entry['managerial_comments_submitted'] === 'Yes') $progress += 10;
                                    ?>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-<?php echo $progress >= 100 ? 'success' : ($progress >= 50 ? 'info' : 'warning'); ?>"
                                             role="progressbar" style="width: <?php echo $progress; ?>%">
                                            <?php echo $progress; ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#trackerTable').DataTable({
                pageLength: 25,
                order: [[3, 'desc']],
                columnDefs: [
                    { orderable: false, targets: [9] }
                ]
            });
        });
    </script>
</body>
</html>