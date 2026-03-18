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

// Handle feedback submission
if ($_POST && isset($_POST['submit_feedback'])) {
    try {
        // Get training_id from the training plan
        $training_plan = $db->fetch("SELECT training_id FROM training_plans WHERE id = ?", [$_POST['training_plan_id']]);
        $training_id = $training_plan['training_id'];

        $db->query("INSERT INTO training_feedback (
            training_plan_id, training_id, trainee_name, evaluator_name, evaluator_epf, review_date,
            skill_transfer_rating, performance_improvement_rating, knowledge_sharing_rating, autonomy_rating,
            critical_gap_closure, productivity_change, error_reduction, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", [
            $_POST['training_plan_id'],
            $training_id,
            $_POST['trainee_name'],
            $_POST['evaluator_name'],
            $_POST['evaluator_epf'],
            $_POST['review_date'],
            $_POST['skill_transfer_rating'],
            $_POST['performance_improvement_rating'],
            $_POST['knowledge_sharing_rating'],
            $_POST['autonomy_rating'],
            $_POST['critical_gap_closure'],
            $_POST['productivity_change'],
            $_POST['error_reduction'],
            $_POST['submit_type'] === 'submit_for_review' ? 'submitted_for_review' : 'draft'
        ]);

        $status_text = $_POST['submit_type'] === 'submit_for_review' ? 'submitted for review' : 'saved as draft';
        $message = "Training effectiveness feedback {$status_text} successfully!";
    } catch (Exception $e) {
        $error = "Error submitting feedback: " . $e->getMessage();
    }
}

// Handle feedback status updates
if ($_POST && isset($_POST['update_feedback_status'])) {
    try {
        $db->query("UPDATE training_feedback SET status = ? WHERE id = ?", [$_POST['new_status'], $_POST['feedback_id']]);
        $message = "Feedback status updated successfully!";
    } catch (Exception $e) {
        $error = "Error updating feedback status: " . $e->getMessage();
    }
}

// Get completed trainings that have evaluations but no feedback yet with role-based filtering
$available_trainings = $db->fetchAll("
    SELECT
        tp.id as plan_id,
        COALESCE(te.training_id, tp.training_id) as training_id,
        te.training_name,
        te.trainee_name,
        te.trainee_epf,
        te.training_start_date,
        te.training_end_date,
        te.id as evaluation_id
    FROM training_plans tp
    JOIN training_requirements tr ON tp.requirement_id = tr.id
    JOIN training_evaluations te ON tp.id = te.training_plan_id
    LEFT JOIN training_feedback tf ON tp.id = tf.training_plan_id
    WHERE tp.status = 'completed'
    AND tf.id IS NULL" . $tr_filter . "
    ORDER BY te.training_end_date DESC
", $tr_params);

// Get all feedback entries with role-based filtering
$feedback_entries = $db->fetchAll("
    SELECT
        tf.*,
        tp.training_name,
        tp.training_start_date,
        tp.training_end_date
    FROM training_feedback tf
    JOIN training_plans tp ON tf.training_plan_id = tp.id
    JOIN training_requirements tr ON tp.requirement_id = tr.id
    WHERE 1=1" . $tr_filter . "
    ORDER BY tf.submitted_at DESC
", $tr_params);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Effectiveness Feedback</title>
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

        .nav-pills .nav-link {
            border-radius: 10px;
            margin-right: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
        }

        .rating-group {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .rating-group h6 {
            color: #2c3e50;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
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
                        <h1><i class="fas fa-comments me-3"></i>Effectiveness Feedback</h1>
                        <p class="mb-0">Supervisor assessments on skill transfer, performance improvement, and ROI</p>
                    </div>
                </div>
            </div>

            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Training Management</a></li>
                    <li class="breadcrumb-item active">Effectiveness Feedback</li>
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

            <!-- Navigation Tabs -->
            <div class="content-card">
                <ul class="nav nav-pills mb-4" id="feedbackTabs">
                    <li class="nav-item">
                        <button class="nav-link active" id="form-tab" data-bs-toggle="pill" data-bs-target="#feedback-form">
                            <i class="fas fa-edit me-2"></i>Submit Feedback
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="list-tab" data-bs-toggle="pill" data-bs-target="#feedback-list">
                            <i class="fas fa-list me-2"></i>View Feedback
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="feedbackTabContent">
                    <!-- Feedback Form Tab -->
                    <div class="tab-pane fade show active" id="feedback-form">
                        <?php if (empty($available_trainings)): ?>
                        <div class="alert alert-info">
                            <h6 class="alert-heading">No Trainings Available</h6>
                            <p class="mb-0">There are no completed trainings with evaluations that require effectiveness feedback at this time.</p>
                        </div>
                        <?php else: ?>
                        <form method="POST">
                            <!-- Training Selection -->
                            <div class="row mb-4">
                                <div class="col-12 mb-3">
                                    <label for="training_plan_id" class="form-label">Select Completed Training <span class="text-danger">*</span></label>
                                    <select class="form-select" name="training_plan_id" id="trainingSelect" required onchange="fillTrainingInfo()">
                                        <option value="">Select a training to provide effectiveness feedback</option>
                                        <?php foreach ($available_trainings as $training): ?>
                                        <option value="<?php echo $training['plan_id']; ?>"
                                                data-training-id="<?php echo $training['training_id']; ?>"
                                                data-name="<?php echo htmlspecialchars($training['training_name']); ?>"
                                                data-trainee="<?php echo htmlspecialchars($training['trainee_name']); ?>"
                                                data-epf="<?php echo $training['trainee_epf']; ?>">
                                            <?php echo htmlspecialchars($training['training_name']); ?> -
                                            <?php echo htmlspecialchars($training['trainee_name']); ?>
                                            (<?php echo date('M j, Y', strtotime($training['training_start_date'])); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Administrative Information -->
                            <div class="rating-group">
                                <h6><i class="fas fa-user-tie me-2"></i>Administrative Reference</h6>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="training_id" class="form-label">Training ID</label>
                                        <input type="text" class="form-control" name="training_id" id="trainingId" placeholder="Enter training reference ID" readonly>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="trainee_name" class="form-label">Trainee Name</label>
                                        <input type="text" class="form-control" name="trainee_name" id="traineeName" readonly>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="review_date" class="form-label">Review Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" name="review_date" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="evaluator_name" class="form-label">Evaluator Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="evaluator_name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="evaluator_epf" class="form-label">Evaluator EPF <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="evaluator_epf" value="<?php echo $user['epf_number']; ?>" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Post-Training Competency Assessment -->
                            <div class="rating-group">
                                <h6><i class="fas fa-chart-bar me-2"></i>Post-Training Competency Assessment (Rate 1-5, where 5 is highest)</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Skill Transfer Rating</label>
                                        <select class="form-select" name="skill_transfer_rating" required>
                                            <option value="">Select rating</option>
                                            <option value="1">1 - No evidence of skill transfer</option>
                                            <option value="2">2 - Minimal skill transfer</option>
                                            <option value="3">3 - Moderate skill transfer</option>
                                            <option value="4">4 - Good skill transfer</option>
                                            <option value="5">5 - Excellent skill transfer</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Performance Improvement Rating</label>
                                        <select class="form-select" name="performance_improvement_rating" required>
                                            <option value="">Select rating</option>
                                            <option value="1">1 - No improvement</option>
                                            <option value="2">2 - Slight improvement</option>
                                            <option value="3">3 - Moderate improvement</option>
                                            <option value="4">4 - Significant improvement</option>
                                            <option value="5">5 - Outstanding improvement</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Knowledge Sharing Rating</label>
                                        <select class="form-select" name="knowledge_sharing_rating" required>
                                            <option value="">Select rating</option>
                                            <option value="1">1 - No knowledge sharing</option>
                                            <option value="2">2 - Limited knowledge sharing</option>
                                            <option value="3">3 - Adequate knowledge sharing</option>
                                            <option value="4">4 - Good knowledge sharing</option>
                                            <option value="5">5 - Excellent knowledge sharing</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Autonomy Rating</label>
                                        <select class="form-select" name="autonomy_rating" required>
                                            <option value="">Select rating</option>
                                            <option value="1">1 - Requires constant supervision</option>
                                            <option value="2">2 - Requires frequent supervision</option>
                                            <option value="3">3 - Requires occasional supervision</option>
                                            <option value="4">4 - Mostly independent</option>
                                            <option value="5">5 - Completely autonomous</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Operational Impact -->
                            <div class="rating-group">
                                <h6><i class="fas fa-cogs me-2"></i>Operational Impact</h6>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Critical Gap Closure</label>
                                        <div class="d-flex gap-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="critical_gap_closure" id="gapY" value="Y">
                                                <label class="form-check-label" for="gapY">Yes</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="critical_gap_closure" id="gapN" value="N" checked>
                                                <label class="form-check-label" for="gapN">No</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Productivity Change</label>
                                        <select class="form-select" name="productivity_change" required>
                                            <option value="Increased">Increased</option>
                                            <option value="Remained Constant" selected>Remained Constant</option>
                                            <option value="Decreased">Decreased</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Error Reduction</label>
                                        <select class="form-select" name="error_reduction" required>
                                            <option value="Y">Yes</option>
                                            <option value="N">No</option>
                                            <option value="N/A" selected>N/A</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Options -->
                            <div class="text-center">
                                <button type="submit" name="submit_feedback" value="draft" class="btn btn-outline-secondary btn-lg me-3">
                                    <i class="fas fa-save me-2"></i>Save as Draft
                                    <input type="hidden" name="submit_type" value="draft">
                                </button>
                                <button type="submit" name="submit_feedback" value="submit_for_review" class="btn btn-success btn-lg" onclick="document.querySelector('input[name=submit_type]').value='submit_for_review';">
                                    <i class="fas fa-paper-plane me-2"></i>Submit for Review
                                </button>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>

                    <!-- Feedback List Tab -->
                    <div class="tab-pane fade" id="feedback-list">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5>Training Effectiveness Feedback</h5>
                            <a href="export_feedback.php" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-file-excel me-1"></i>Export
                            </a>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped" id="feedbackTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Training Name</th>
                                        <th>Trainee</th>
                                        <th>Evaluator</th>
                                        <th>Review Date</th>
                                        <th>Avg Rating</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($feedback_entries as $feedback): ?>
                                    <tr>
                                        <td><?php echo $feedback['id']; ?></td>
                                        <td><?php echo htmlspecialchars($feedback['training_name']); ?></td>
                                        <td><?php echo htmlspecialchars($feedback['trainee_name']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($feedback['evaluator_name']); ?>
                                            <br><small class="text-muted"><?php echo $feedback['evaluator_epf']; ?></small>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($feedback['review_date'])); ?></td>
                                        <td>
                                            <?php
                                            $avg_rating = ($feedback['skill_transfer_rating'] + $feedback['performance_improvement_rating'] +
                                                         $feedback['knowledge_sharing_rating'] + $feedback['autonomy_rating']) / 4;
                                            $rating_class = $avg_rating >= 4 ? 'success' : ($avg_rating >= 3 ? 'warning' : 'danger');
                                            ?>
                                            <span class="badge bg-<?php echo $rating_class; ?>">
                                                <?php echo number_format($avg_rating, 1); ?>/5
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php
                                                echo match($feedback['status']) {
                                                    'completed' => 'success',
                                                    'submitted_for_review' => 'primary',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php echo ucwords(str_replace('_', ' ', $feedback['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="viewFeedback(<?php echo $feedback['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($feedback['status'] === 'submitted_for_review'): ?>
                                                <button type="button" class="btn btn-outline-success btn-sm" onclick="updateFeedbackStatus(<?php echo $feedback['id']; ?>, 'completed')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <?php endif; ?>
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
        </div>
    </div>

    <!-- Status Update Form (Hidden) -->
    <form id="statusForm" method="POST" style="display: none;">
        <input type="hidden" name="feedback_id" id="statusFeedbackId">
        <input type="hidden" name="new_status" id="newStatus">
        <input type="hidden" name="update_feedback_status" value="1">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#feedbackTable').DataTable({
                pageLength: 25,
                order: [[0, 'desc']],
                columnDefs: [
                    { orderable: false, targets: [7] }
                ]
            });
        });

        function fillTrainingInfo() {
            const select = document.getElementById('trainingSelect');
            const option = select.options[select.selectedIndex];

            if (option.value) {
                // Use getAttribute for hyphenated data attributes
                const trainingId = option.getAttribute('data-training-id');
                const traineeName = option.getAttribute('data-trainee');

                document.getElementById('trainingId').value = trainingId || 'No Training ID';
                document.getElementById('traineeName').value = traineeName || '';

                console.log('Selected training:', option.value);
                console.log('Training ID:', trainingId);
                console.log('Trainee:', traineeName);
            } else {
                document.getElementById('trainingId').value = '';
                document.getElementById('traineeName').value = '';
            }
        }

        function updateFeedbackStatus(id, status) {
            if (confirm(`Are you sure you want to mark this feedback as ${status}?`)) {
                document.getElementById('statusFeedbackId').value = id;
                document.getElementById('newStatus').value = status;
                document.getElementById('statusForm').submit();
            }
        }

        function viewFeedback(id) {
            // Implement view functionality
            alert('View feedback details functionality will be implemented');
        }
    </script>
</body>
</html>