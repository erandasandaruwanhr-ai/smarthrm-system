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

// Handle managerial comments submission
if ($_POST && isset($_POST['submit_comments'])) {
    try {
        $db->query("INSERT INTO training_managerial_comments (
            training_feedback_id, training_evaluation_id, supervisor_observations,
            further_support_required, overall_effectiveness, supervisor_name,
            supervisor_epf, sign_off_date
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())", [
            $_POST['training_feedback_id'],
            $_POST['training_evaluation_id'] ?: null,
            $_POST['supervisor_observations'],
            $_POST['further_support_required'],
            $_POST['overall_effectiveness'],
            $_POST['supervisor_name'],
            $_POST['supervisor_epf']
        ]);

        $message = "Managerial comments submitted successfully!";
    } catch (Exception $e) {
        $error = "Error submitting comments: " . $e->getMessage();
    }
}

// Get feedback entries that need managerial review with role-based filtering
$pending_reviews = $db->fetchAll("
    SELECT DISTINCT
        tf.id as feedback_id,
        tf.training_id,
        tf.trainee_name,
        tf.evaluator_name,
        tf.review_date,
        tp.training_name,
        tp.training_start_date,
        tp.training_cost,
        te.id as evaluation_id,
        ROUND((tf.skill_transfer_rating + tf.performance_improvement_rating +
               tf.knowledge_sharing_rating + tf.autonomy_rating) / 4, 1) as avg_feedback_rating,
        ROUND((te.immediate_application_rating + te.performance_improvement_rating +
               te.recommend_to_colleague_rating) / 3, 1) as avg_evaluation_rating
    FROM training_feedback tf
    JOIN training_plans tp ON tf.training_plan_id = tp.id
    JOIN training_requirements tr ON tp.requirement_id = tr.id
    LEFT JOIN training_evaluations te ON tp.id = te.training_plan_id
    LEFT JOIN training_managerial_comments tmc ON tf.id = tmc.training_feedback_id
    WHERE tf.status = 'submitted_for_review'
    AND tmc.id IS NULL" . $tr_filter . "
    ORDER BY tf.review_date DESC
", $tr_params);

// Get completed managerial reviews with role-based filtering
$completed_reviews = $db->fetchAll("
    SELECT DISTINCT
        tmc.id,
        tmc.training_feedback_id,
        tmc.training_evaluation_id,
        tmc.supervisor_observations,
        tmc.further_support_required,
        tmc.overall_effectiveness,
        tmc.supervisor_name,
        tmc.supervisor_epf,
        tmc.sign_off_date,
        tmc.created_at,
        tmc.updated_at,
        tf.trainee_name,
        tf.training_id,
        tf.review_date,
        tp.training_name,
        tp.training_start_date,
        tp.training_cost
    FROM training_managerial_comments tmc
    JOIN training_feedback tf ON tmc.training_feedback_id = tf.id
    JOIN training_plans tp ON tf.training_plan_id = tp.id
    JOIN training_requirements tr ON tp.requirement_id = tr.id
    WHERE 1=1" . $tr_filter . "
    ORDER BY tmc.sign_off_date DESC
", $tr_params);

// Get training effectiveness summary for dashboard with role-based filtering
$summary_params = array_merge([date('Y')], $tr_params);
$effectiveness_summary = $db->fetchAll("
    SELECT DISTINCT
        tp.training_name,
        tp.trainee_name,
        tp.training_cost,
        tp.training_start_date,
        ROUND((tf.skill_transfer_rating + tf.performance_improvement_rating +
               tf.knowledge_sharing_rating + tf.autonomy_rating) / 4, 1) as avg_rating,
        tf.productivity_change,
        tf.critical_gap_closure,
        tmc.overall_effectiveness,
        tmc.supervisor_observations
    FROM training_plans tp
    JOIN training_feedback tf ON tp.id = tf.training_plan_id
    JOIN training_requirements tr ON tp.requirement_id = tr.id
    LEFT JOIN training_managerial_comments tmc ON tf.id = tmc.training_feedback_id
    WHERE tp.status = 'completed'
    AND YEAR(tp.training_start_date) = ?" . $tr_filter . "
    ORDER BY tp.training_start_date DESC
    LIMIT 10
", $summary_params);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Management Review</title>
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

        .review-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }

        .review-card:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .effectiveness-card {
            border-left: 4px solid;
            padding: 1rem;
            margin-bottom: 1rem;
            background: white;
            border-radius: 0 10px 10px 0;
            transition: all 0.3s ease;
        }

        .effectiveness-card:hover {
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }

        .effectiveness-card.high-impact {
            border-left-color: var(--success-color);
        }

        .effectiveness-card.medium-impact {
            border-left-color: var(--warning-color);
        }

        .effectiveness-card.low-impact {
            border-left-color: var(--danger-color);
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
                        <h1><i class="fas fa-cogs me-3"></i>Management Review</h1>
                        <p class="mb-0">Managerial comments, action plans, and strategic training decisions</p>
                    </div>
                </div>
            </div>

            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Training Management</a></li>
                    <li class="breadcrumb-item active">Management Review</li>
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
                <ul class="nav nav-pills mb-4" id="managementTabs">
                    <li class="nav-item">
                        <button class="nav-link active" id="pending-tab" data-bs-toggle="pill" data-bs-target="#pending-reviews">
                            <i class="fas fa-clock me-2"></i>Pending Reviews (<?php echo count($pending_reviews); ?>)
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="effectiveness-tab" data-bs-toggle="pill" data-bs-target="#effectiveness-summary">
                            <i class="fas fa-chart-line me-2"></i>Effectiveness Summary
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="completed-tab" data-bs-toggle="pill" data-bs-target="#completed-reviews">
                            <i class="fas fa-check me-2"></i>Completed Reviews
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="managementTabContent">
                    <!-- Pending Reviews Tab -->
                    <div class="tab-pane fade show active" id="pending-reviews">
                        <?php if (empty($pending_reviews)): ?>
                        <div class="alert alert-info">
                            <h6 class="alert-heading">No Pending Reviews</h6>
                            <p class="mb-0">There are no training feedback submissions requiring managerial review at this time.</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($pending_reviews as $review): ?>
                        <div class="review-card">
                            <div class="row">
                                <div class="col-md-8">
                                    <h6 class="mb-2"><?php echo htmlspecialchars($review['training_name']); ?></h6>
                                    <p class="mb-1"><strong>Trainee:</strong> <?php echo htmlspecialchars($review['trainee_name']); ?></p>
                                    <p class="mb-1"><strong>Evaluator:</strong> <?php echo htmlspecialchars($review['evaluator_name']); ?></p>
                                    <p class="mb-1"><strong>Review Date:</strong> <?php echo date('M j, Y', strtotime($review['review_date'])); ?></p>
                                    <p class="mb-1"><strong>Training Cost:</strong> LKR <?php echo number_format($review['training_cost'], 2); ?></p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <div class="mb-2">
                                        <span class="badge bg-primary">Feedback Rating: <?php echo $review['avg_feedback_rating']; ?>/5</span>
                                    </div>
                                    <?php if ($review['avg_evaluation_rating']): ?>
                                    <div class="mb-2">
                                        <span class="badge bg-info">Evaluation Rating: <?php echo $review['avg_evaluation_rating']; ?>/5</span>
                                    </div>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="reviewTraining(<?php echo $review['feedback_id']; ?>, <?php echo $review['evaluation_id'] ?: 'null'; ?>, '<?php echo addslashes($review['training_name']); ?>', '<?php echo addslashes($review['trainee_name']); ?>')">
                                        <i class="fas fa-edit me-1"></i>Review
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Effectiveness Summary Tab -->
                    <div class="tab-pane fade" id="effectiveness-summary">
                        <h6 class="mb-3">Recent Training Effectiveness (<?php echo date('Y'); ?>)</h6>
                        <?php if (empty($effectiveness_summary)): ?>
                        <div class="alert alert-info">
                            <p class="mb-0">No completed training effectiveness data available for this year.</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($effectiveness_summary as $summary): ?>
                        <?php
                        $impact_class = '';
                        if ($summary['avg_rating'] >= 4) {
                            $impact_class = 'high-impact';
                        } elseif ($summary['avg_rating'] >= 3) {
                            $impact_class = 'medium-impact';
                        } else {
                            $impact_class = 'low-impact';
                        }
                        ?>
                        <div class="effectiveness-card <?php echo $impact_class; ?>">
                            <div class="row">
                                <div class="col-md-8">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($summary['training_name']); ?></h6>
                                    <p class="mb-1"><strong>Trainee:</strong> <?php echo htmlspecialchars($summary['trainee_name']); ?></p>
                                    <p class="mb-1">
                                        <strong>Date:</strong> <?php echo date('M j, Y', strtotime($summary['training_start_date'])); ?> |
                                        <strong>Cost:</strong> LKR <?php echo number_format($summary['training_cost'], 2); ?>
                                    </p>
                                    <?php if ($summary['supervisor_observations']): ?>
                                    <p class="mb-0 small text-muted">
                                        <em><?php echo htmlspecialchars(substr($summary['supervisor_observations'], 0, 120)); ?>...</em>
                                    </p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-end">
                                        <div class="mb-1">
                                            <span class="badge bg-<?php echo $impact_class === 'high-impact' ? 'success' : ($impact_class === 'medium-impact' ? 'warning' : 'danger'); ?>">
                                                <?php echo $summary['avg_rating']; ?>/5 Rating
                                            </span>
                                        </div>
                                        <div class="mb-1">
                                            <span class="badge bg-<?php echo $summary['productivity_change'] === 'Increased' ? 'success' : ($summary['productivity_change'] === 'Decreased' ? 'danger' : 'secondary'); ?>">
                                                <?php echo $summary['productivity_change']; ?>
                                            </span>
                                        </div>
                                        <?php if ($summary['overall_effectiveness']): ?>
                                        <div class="mb-1">
                                            <span class="badge bg-<?php echo $summary['overall_effectiveness'] === 'Y' ? 'success' : 'danger'; ?>">
                                                <?php echo $summary['overall_effectiveness'] === 'Y' ? 'Effective' : 'Not Effective'; ?>
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Completed Reviews Tab -->
                    <div class="tab-pane fade" id="completed-reviews">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6>Completed Management Reviews</h6>
                            <a href="export_management_reviews.php" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-file-excel me-1"></i>Export
                            </a>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped" id="reviewsTable">
                                <thead>
                                    <tr>
                                        <th>Training</th>
                                        <th>Trainee</th>
                                        <th>Supervisor</th>
                                        <th>Review Date</th>
                                        <th>Effectiveness</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($completed_reviews as $review): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($review['training_name']); ?>
                                            <br><small class="text-muted"><?php echo date('M j, Y', strtotime($review['training_start_date'])); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($review['trainee_name']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($review['supervisor_name']); ?>
                                            <br><small class="text-muted"><?php echo $review['supervisor_epf']; ?></small>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($review['sign_off_date'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $review['overall_effectiveness'] === 'Y' ? 'success' : 'danger'; ?>">
                                                <?php echo $review['overall_effectiveness'] === 'Y' ? 'Effective' : 'Not Effective'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="viewReview(<?php echo $review['id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
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

    <!-- Management Review Modal -->
    <div class="modal fade" id="reviewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Management Review & Comments</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Training:</strong> <span id="modalTrainingName"></span>
                            </div>
                            <div class="col-md-6">
                                <strong>Trainee:</strong> <span id="modalTraineeName"></span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="supervisor_observations" class="form-label">Supervisor Observations <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="supervisor_observations" rows="4"
                                    placeholder="Provide detailed observations on the trainee's performance improvement, skill application, and behavioral changes..." required></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="further_support_required" class="form-label">Further Support Required</label>
                            <textarea class="form-control" name="further_support_required" rows="3"
                                    placeholder="Describe any additional support, resources, or follow-up training needed..."></textarea>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Overall Training Effectiveness <span class="text-danger">*</span></label>
                                <div class="d-flex gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="overall_effectiveness" id="effY" value="Y" checked>
                                        <label class="form-check-label" for="effY">Effective</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="overall_effectiveness" id="effN" value="N">
                                        <label class="form-check-label" for="effN">Not Effective</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="supervisor_name" class="form-label">Supervisor Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="supervisor_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="supervisor_epf" class="form-label">Supervisor EPF <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="supervisor_epf" value="<?php echo htmlspecialchars($user['epf_number'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="training_feedback_id" id="modalFeedbackId">
                        <input type="hidden" name="training_evaluation_id" id="modalEvaluationId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="submit_comments" class="btn btn-primary">Submit Review</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Review Details Modal -->
    <div class="modal fade" id="viewReviewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Review Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Training:</strong> <span id="viewTrainingName"></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Training ID:</strong> <span id="viewTrainingId"></span>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Trainee:</strong> <span id="viewTraineeName"></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Overall Effectiveness:</strong> <span id="viewOverallEffectiveness"></span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <strong>Supervisor Observations:</strong>
                        <div class="mt-2 p-3 bg-light border rounded">
                            <span id="viewSupervisorObservations"></span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <strong>Further Support Required:</strong>
                        <div class="mt-2 p-3 bg-light border rounded">
                            <span id="viewFurtherSupport"></span>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Supervisor Name:</strong> <span id="viewSupervisorName"></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Supervisor EPF:</strong> <span id="viewSupervisorEpf"></span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <strong>Sign-off Date:</strong> <span id="viewSignOffDate"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
            $('#reviewsTable').DataTable({
                pageLength: 25,
                order: [[3, 'desc']],
                columnDefs: [
                    { orderable: false, targets: [5] }
                ]
            });
        });

        function reviewTraining(feedbackId, evaluationId, trainingName, traineeName) {
            document.getElementById('modalFeedbackId').value = feedbackId;
            document.getElementById('modalEvaluationId').value = evaluationId || '';
            document.getElementById('modalTrainingName').textContent = trainingName;
            document.getElementById('modalTraineeName').textContent = traineeName;

            // Get supervisor details for the trainee
            getSupervisorDetails(traineeName);

            new bootstrap.Modal(document.getElementById('reviewModal')).show();
        }

        function getSupervisorDetails(traineeName) {
            $.ajax({
                url: 'ajax/get_supervisor_details.php',
                method: 'POST',
                data: { trainee_name: traineeName },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.supervisor) {
                        document.querySelector('input[name="supervisor_name"]').value = response.supervisor.name;
                        document.querySelector('input[name="supervisor_epf"]').value = response.supervisor.epf_number;
                    } else {
                        // Clear fields if no supervisor found
                        document.querySelector('input[name="supervisor_name"]').value = '';
                        document.querySelector('input[name="supervisor_epf"]').value = '';
                    }
                },
                error: function() {
                    console.log('Error getting supervisor details');
                }
            });
        }

        function viewReview(id) {
            $.ajax({
                url: 'ajax/get_review_details.php',
                method: 'GET',
                data: { review_id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showReviewDetails(response.review);
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error loading review details');
                }
            });
        }

        function showReviewDetails(review) {
            const modal = new bootstrap.Modal(document.getElementById('viewReviewModal'));

            // Populate review details
            document.getElementById('viewTrainingName').textContent = review.training_name;
            document.getElementById('viewTraineeName').textContent = review.trainee_name;
            document.getElementById('viewTrainingId').textContent = review.training_id;
            document.getElementById('viewSupervisorObservations').textContent = review.supervisor_observations;
            document.getElementById('viewFurtherSupport').textContent = review.further_support_required || 'None specified';
            // Convert Y/N to Effective/Not Effective for display
            const effectiveness = (review.overall_effectiveness === 'Y' || review.overall_effectiveness === 'Effective') ? 'Effective' : 'Not Effective';
            const badgeClass = (review.overall_effectiveness === 'Y' || review.overall_effectiveness === 'Effective') ? 'success' : 'danger';

            document.getElementById('viewOverallEffectiveness').innerHTML =
                '<span class="badge bg-' + badgeClass + '">' + effectiveness + '</span>';
            document.getElementById('viewSupervisorName').textContent = review.supervisor_name;
            document.getElementById('viewSupervisorEpf').textContent = review.supervisor_epf;
            document.getElementById('viewSignOffDate').textContent = new Date(review.sign_off_date).toLocaleDateString();

            modal.show();
        }
    </script>
</body>
</html>