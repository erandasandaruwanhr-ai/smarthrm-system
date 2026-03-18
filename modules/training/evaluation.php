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

// Handle evaluation submission
if ($_POST && isset($_POST['submit_evaluation'])) {
    try {
        // First check if an evaluation already exists for this training plan
        $existing_evaluation = $db->fetch("SELECT id FROM training_evaluations WHERE training_plan_id = ?", [$_POST['training_plan_id']]);

        if ($existing_evaluation) {
            $error = "An evaluation for this training plan already exists. Each training can only be evaluated once.";
        } else {
            // Get training_id from the training plan
        $training_plan = $db->fetch("SELECT training_id FROM training_plans WHERE id = ?", [$_POST['training_plan_id']]);
        $training_id = $training_plan['training_id'];

        $db->query("INSERT INTO training_evaluations (
            training_plan_id, training_id, training_name, training_institute, training_start_date, training_end_date,
            trainee_epf, trainee_name, registration_process, environment_rating, duration_rating,
            objectives_clear, applicability_rating, pacing_rating, theory_practical_balance,
            instructor_knowledgeable, instructor_engaging, presentation_clarity,
            immediate_application_rating, performance_improvement_rating, recommend_to_colleague_rating,
            most_valuable_part, areas_for_improvement, additional_comments, submitted_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", [
            $_POST['training_plan_id'],
            $training_id,
            $_POST['training_name'],
            $_POST['training_institute'],
            $_POST['training_start_date'],
            $_POST['training_end_date'] ?: null,
            $_POST['trainee_epf'],
            $_POST['trainee_name'],
            $_POST['registration_process'],
            $_POST['environment_rating'],
            $_POST['duration_rating'],
            $_POST['objectives_clear'],
            $_POST['applicability_rating'],
            $_POST['pacing_rating'],
            $_POST['theory_practical_balance'],
            $_POST['instructor_knowledgeable'],
            $_POST['instructor_engaging'],
            $_POST['presentation_clarity'],
            $_POST['immediate_application_rating'],
            $_POST['performance_improvement_rating'],
            $_POST['recommend_to_colleague_rating'],
            $_POST['most_valuable_part'] ?: null,
            $_POST['areas_for_improvement'] ?: null,
            $_POST['additional_comments'] ?: null,
            $user['epf_number']
        ]);

            $message = "Training evaluation submitted successfully!";
        }
    } catch (Exception $e) {
        $error = "Error submitting evaluation: " . $e->getMessage();
    }
}

// Get completed training plans that need evaluation with role-based filtering
$completed_plans = $db->fetchAll("
    SELECT tp.*
    FROM training_plans tp
    JOIN training_requirements tr ON tp.requirement_id = tr.id
    LEFT JOIN training_evaluations te ON tp.id = te.training_plan_id
    WHERE tp.status = 'completed'
    AND te.id IS NULL" . $tr_filter . "
    ORDER BY tp.training_end_date DESC, tp.training_start_date DESC
", $tr_params);

// Get all evaluations for listing with role-based filtering
$evaluations = $db->fetchAll("
    SELECT
        te.*,
        tp.training_cost,
        tp.status as plan_status
    FROM training_evaluations te
    JOIN training_plans tp ON te.training_plan_id = tp.id
    JOIN training_requirements tr ON tp.requirement_id = tr.id
    WHERE 1=1" . $tr_filter . "
    ORDER BY te.submitted_at DESC
", $tr_params);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Training Evaluation</title>
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
                        <h1><i class="fas fa-star-half-alt me-3"></i>Training Evaluation</h1>
                        <p class="mb-0">Collect trainee feedback on logistics, content, instructors, and learning impact</p>
                    </div>
                </div>
            </div>

            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Training Management</a></li>
                    <li class="breadcrumb-item active">Training Evaluation</li>
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
                <ul class="nav nav-pills mb-4" id="evaluationTabs">
                    <li class="nav-item">
                        <button class="nav-link active" id="form-tab" data-bs-toggle="pill" data-bs-target="#evaluation-form">
                            <i class="fas fa-edit me-2"></i>Submit Evaluation
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="list-tab" data-bs-toggle="pill" data-bs-target="#evaluations-list">
                            <i class="fas fa-list me-2"></i>View Evaluations
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="evaluationTabContent">
                    <!-- Evaluation Form Tab -->
                    <div class="tab-pane fade show active" id="evaluation-form">
                        <?php if (empty($completed_plans)): ?>
                        <div class="alert alert-info">
                            <h6 class="alert-heading">No Training Plans Available</h6>
                            <p class="mb-0">There are no completed training plans that require evaluation at this time.</p>
                        </div>
                        <?php else: ?>
                        <form method="POST">
                            <div class="row mb-4">
                                <div class="col-12 mb-3">
                                    <label for="training_plan_id" class="form-label">Select Completed Training <span class="text-danger">*</span></label>
                                    <select class="form-select" name="training_plan_id" id="trainingPlanSelect" required onchange="fillTrainingDetails()">
                                        <option value="">Select a completed training to evaluate</option>
                                        <?php foreach ($completed_plans as $plan): ?>
                                        <option value="<?php echo $plan['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($plan['training_name']); ?>"
                                                data-institute="<?php echo htmlspecialchars($plan['training_institute']); ?>"
                                                data-start="<?php echo $plan['training_start_date']; ?>"
                                                data-end="<?php echo $plan['training_end_date'] ?: ''; ?>"
                                                data-trainee-epf="<?php echo $plan['trainee_epf']; ?>"
                                                data-trainee-name="<?php echo htmlspecialchars($plan['trainee_name']); ?>">
                                            <?php echo htmlspecialchars($plan['training_name']); ?> -
                                            <?php echo htmlspecialchars($plan['trainee_name']); ?>
                                            (<?php echo date('M j, Y', strtotime($plan['training_start_date'])); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Hidden fields auto-filled from training plan -->
                            <input type="hidden" name="training_name" id="trainingName">
                            <input type="hidden" name="training_institute" id="trainingInstitute">
                            <input type="hidden" name="training_start_date" id="trainingStartDate">
                            <input type="hidden" name="training_end_date" id="trainingEndDate">
                            <input type="hidden" name="trainee_epf" id="traineeEpf">
                            <input type="hidden" name="trainee_name" id="traineeName">

                            <!-- 11.5.2 Logistics and Organization -->
                            <div class="rating-group">
                                <h6><i class="fas fa-cogs me-2"></i>Logistics and Organization</h6>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Was the registration process smooth?</label>
                                        <div class="d-flex gap-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="registration_process" id="regY" value="Y" checked>
                                                <label class="form-check-label" for="regY">Yes</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="registration_process" id="regN" value="N">
                                                <label class="form-check-label" for="regN">No</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Training environment rating</label>
                                        <select class="form-select" name="environment_rating" required>
                                            <option value="Poor">Poor</option>
                                            <option value="Fair">Fair</option>
                                            <option value="Good" selected>Good</option>
                                            <option value="Excellent">Excellent</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Training duration</label>
                                        <select class="form-select" name="duration_rating" required>
                                            <option value="Too Short">Too Short</option>
                                            <option value="Just Right" selected>Just Right</option>
                                            <option value="Too Long">Too Long</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- 11.5.3 Content and Relevance -->
                            <div class="rating-group">
                                <h6><i class="fas fa-book me-2"></i>Content and Relevance</h6>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Were the objectives clear?</label>
                                        <div class="d-flex gap-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="objectives_clear" id="objY" value="Y" checked>
                                                <label class="form-check-label" for="objY">Yes</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="objectives_clear" id="objN" value="N">
                                                <label class="form-check-label" for="objN">No</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Content applicability to your job</label>
                                        <select class="form-select" name="applicability_rating" required>
                                            <option value="Not Relevant">Not Relevant</option>
                                            <option value="Somewhat Relevant">Somewhat Relevant</option>
                                            <option value="Very Relevant" selected>Very Relevant</option>
                                            <option value="Extremely Relevant">Extremely Relevant</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Training pace</label>
                                        <select class="form-select" name="pacing_rating" required>
                                            <option value="Too Fast">Too Fast</option>
                                            <option value="Just Right" selected>Just Right</option>
                                            <option value="Too Slow">Too Slow</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Theory vs Practical balance</label>
                                        <select class="form-select" name="theory_practical_balance" required>
                                            <option value="Too Much Theory">Too Much Theory</option>
                                            <option value="Good Balance" selected>Good Balance</option>
                                            <option value="Too Much Practical">Too Much Practical</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- 11.5.4 Instructor Effectiveness -->
                            <div class="rating-group">
                                <h6><i class="fas fa-chalkboard-teacher me-2"></i>Instructor Effectiveness</h6>

                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Instructor was knowledgeable</label>
                                        <div class="d-flex gap-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="instructor_knowledgeable" id="instKnowY" value="Y" checked>
                                                <label class="form-check-label" for="instKnowY">Yes</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="instructor_knowledgeable" id="instKnowN" value="N">
                                                <label class="form-check-label" for="instKnowN">No</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Instructor was engaging</label>
                                        <div class="d-flex gap-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="instructor_engaging" id="instEngY" value="Y" checked>
                                                <label class="form-check-label" for="instEngY">Yes</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="instructor_engaging" id="instEngN" value="N">
                                                <label class="form-check-label" for="instEngN">No</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Presentation was clear</label>
                                        <div class="d-flex gap-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="presentation_clarity" id="presClearY" value="Y" checked>
                                                <label class="form-check-label" for="presClearY">Yes</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="presentation_clarity" id="presClearN" value="N">
                                                <label class="form-check-label" for="presClearN">No</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="presentation_clarity" id="presClearNA" value="N/A">
                                                <label class="form-check-label" for="presClearNA">N/A</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 11.5.5 Impact and Future Action (1-5 scale) -->
                            <div class="rating-group">
                                <h6><i class="fas fa-chart-line me-2"></i>Impact and Future Action (Rate 1-5, where 5 is highest)</h6>

                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Immediate application potential</label>
                                        <select class="form-select" name="immediate_application_rating" required>
                                            <option value="">Select rating</option>
                                            <option value="1">1 - Very Low</option>
                                            <option value="2">2 - Low</option>
                                            <option value="3">3 - Moderate</option>
                                            <option value="4">4 - High</option>
                                            <option value="5">5 - Very High</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Expected performance improvement</label>
                                        <select class="form-select" name="performance_improvement_rating" required>
                                            <option value="">Select rating</option>
                                            <option value="1">1 - Very Low</option>
                                            <option value="2">2 - Low</option>
                                            <option value="3">3 - Moderate</option>
                                            <option value="4">4 - High</option>
                                            <option value="5">5 - Very High</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Would recommend to colleague</label>
                                        <select class="form-select" name="recommend_to_colleague_rating" required>
                                            <option value="">Select rating</option>
                                            <option value="1">1 - Definitely Not</option>
                                            <option value="2">2 - Probably Not</option>
                                            <option value="3">3 - Maybe</option>
                                            <option value="4">4 - Probably</option>
                                            <option value="5">5 - Definitely</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- 11.5.6 Open-Ended Feedback -->
                            <div class="rating-group">
                                <h6><i class="fas fa-comments me-2"></i>Additional Feedback</h6>

                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <label for="most_valuable_part" class="form-label">What was the most valuable part of the training?</label>
                                        <textarea class="form-control" name="most_valuable_part" rows="3" placeholder="Describe what you found most beneficial..."></textarea>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label for="areas_for_improvement" class="form-label">What areas could be improved?</label>
                                        <textarea class="form-control" name="areas_for_improvement" rows="3" placeholder="Suggest improvements for future trainings..."></textarea>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label for="additional_comments" class="form-label">Additional comments</label>
                                        <textarea class="form-control" name="additional_comments" rows="3" placeholder="Any other feedback or suggestions..."></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="text-center">
                                <button type="submit" name="submit_evaluation" class="btn btn-success btn-lg">
                                    <i class="fas fa-save me-2"></i>Submit Training Evaluation
                                </button>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>

                    <!-- Evaluations List Tab -->
                    <div class="tab-pane fade" id="evaluations-list">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5>Training Evaluations</h5>
                            <a href="export_evaluations.php" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-file-excel me-1"></i>Export
                            </a>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped" id="evaluationsTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Training Name</th>
                                        <th>Trainee</th>
                                        <th>Institute</th>
                                        <th>Training Date</th>
                                        <th>Overall Rating</th>
                                        <th>Submitted</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($evaluations as $eval): ?>
                                    <tr>
                                        <td><?php echo $eval['id']; ?></td>
                                        <td><?php echo htmlspecialchars($eval['training_name']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($eval['trainee_name']); ?>
                                            <br><small class="text-muted"><?php echo $eval['trainee_epf']; ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($eval['training_institute']); ?></td>
                                        <td>
                                            <?php echo date('M j, Y', strtotime($eval['training_start_date'])); ?>
                                            <?php if ($eval['training_end_date']): ?>
                                            <br><small class="text-muted">to <?php echo date('M j, Y', strtotime($eval['training_end_date'])); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $avg_rating = ($eval['immediate_application_rating'] + $eval['performance_improvement_rating'] + $eval['recommend_to_colleague_rating']) / 3;
                                            $rating_class = $avg_rating >= 4 ? 'success' : ($avg_rating >= 3 ? 'warning' : 'danger');
                                            ?>
                                            <span class="badge bg-<?php echo $rating_class; ?>">
                                                <?php echo number_format($avg_rating, 1); ?>/5
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($eval['submitted_at'])); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="viewEvaluation(<?php echo $eval['id']; ?>)">
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

    <!-- Evaluation Details Modal -->
    <div class="modal fade" id="evaluationModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Training Evaluation Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="evaluationDetails">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading evaluation details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="printEvaluation()">
                        <i class="fas fa-print me-1"></i>Print
                    </button>
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
            $('#evaluationsTable').DataTable({
                pageLength: 25,
                order: [[0, 'desc']],
                columnDefs: [
                    { orderable: false, targets: [7] }
                ]
            });
        });

        function fillTrainingDetails() {
            const select = document.getElementById('trainingPlanSelect');
            const option = select.options[select.selectedIndex];

            if (option.value) {
                document.getElementById('trainingName').value = option.dataset.name;
                document.getElementById('trainingInstitute').value = option.dataset.institute;
                document.getElementById('trainingStartDate').value = option.dataset.start;
                document.getElementById('trainingEndDate').value = option.dataset.end;
                document.getElementById('traineeEpf').value = option.dataset.traineeEpf;
                document.getElementById('traineeName').value = option.dataset.traineeName;
            } else {
                document.getElementById('trainingName').value = '';
                document.getElementById('trainingInstitute').value = '';
                document.getElementById('trainingStartDate').value = '';
                document.getElementById('trainingEndDate').value = '';
                document.getElementById('traineeEpf').value = '';
                document.getElementById('traineeName').value = '';
            }
        }

        function viewEvaluation(id) {
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('evaluationModal'));
            modal.show();

            // Load evaluation details via AJAX
            $.ajax({
                url: 'ajax/get_evaluation_details.php',
                method: 'GET',
                data: { evaluation_id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        displayEvaluationDetails(response.evaluation);
                    } else {
                        document.getElementById('evaluationDetails').innerHTML =
                            '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>' +
                            (response.message || 'Error loading evaluation details') + '</div>';
                    }
                },
                error: function() {
                    document.getElementById('evaluationDetails').innerHTML =
                        '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>' +
                        'Error connecting to server. Please try again.</div>';
                }
            });
        }

        function displayEvaluationDetails(eval) {
            const details = `
                <div class="row">
                    <!-- Training Information -->
                    <div class="col-12 mb-4">
                        <h6 class="border-bottom pb-2 text-primary"><i class="fas fa-graduation-cap me-2"></i>Training Information</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Training Name:</strong><br>
                                <span class="text-muted">${eval.training_name}</span>
                            </div>
                            <div class="col-md-6">
                                <strong>Training Institute:</strong><br>
                                <span class="text-muted">${eval.training_institute}</span>
                            </div>
                            <div class="col-md-4 mt-3">
                                <strong>Start Date:</strong><br>
                                <span class="text-muted">${new Date(eval.training_start_date).toLocaleDateString()}</span>
                            </div>
                            <div class="col-md-4 mt-3">
                                <strong>End Date:</strong><br>
                                <span class="text-muted">${eval.training_end_date ? new Date(eval.training_end_date).toLocaleDateString() : 'N/A'}</span>
                            </div>
                            <div class="col-md-4 mt-3">
                                <strong>Trainee:</strong><br>
                                <span class="text-muted">${eval.trainee_name} (${eval.trainee_epf})</span>
                            </div>
                        </div>
                    </div>

                    <!-- Administrative Evaluation -->
                    <div class="col-md-6 mb-4">
                        <h6 class="border-bottom pb-2 text-primary"><i class="fas fa-clipboard-check me-2"></i>Administrative Evaluation</h6>
                        <div class="mb-2"><strong>Registration Process:</strong> <span class="badge bg-${getRatingColor(eval.registration_process)}">${eval.registration_process}</span></div>
                        <div class="mb-2"><strong>Environment Rating:</strong> ${renderStars(eval.environment_rating)}</div>
                        <div class="mb-2"><strong>Duration Rating:</strong> ${renderStars(eval.duration_rating)}</div>
                    </div>

                    <!-- Content Evaluation -->
                    <div class="col-md-6 mb-4">
                        <h6 class="border-bottom pb-2 text-primary"><i class="fas fa-book me-2"></i>Content Evaluation</h6>
                        <div class="mb-2"><strong>Objectives Clear:</strong> <span class="badge bg-${getRatingColor(eval.objectives_clear)}">${eval.objectives_clear}</span></div>
                        <div class="mb-2"><strong>Applicability Rating:</strong> ${renderStars(eval.applicability_rating)}</div>
                        <div class="mb-2"><strong>Pacing Rating:</strong> ${renderStars(eval.pacing_rating)}</div>
                        <div class="mb-2"><strong>Theory/Practical Balance:</strong> <span class="badge bg-${getRatingColor(eval.theory_practical_balance)}">${eval.theory_practical_balance}</span></div>
                    </div>

                    <!-- Instructor Evaluation -->
                    <div class="col-md-6 mb-4">
                        <h6 class="border-bottom pb-2 text-primary"><i class="fas fa-chalkboard-teacher me-2"></i>Instructor Evaluation</h6>
                        <div class="mb-2"><strong>Knowledgeable:</strong> <span class="badge bg-${getRatingColor(eval.instructor_knowledgeable)}">${eval.instructor_knowledgeable}</span></div>
                        <div class="mb-2"><strong>Engaging:</strong> <span class="badge bg-${getRatingColor(eval.instructor_engaging)}">${eval.instructor_engaging}</span></div>
                        <div class="mb-2"><strong>Presentation Clarity:</strong> <span class="badge bg-${getRatingColor(eval.presentation_clarity)}">${eval.presentation_clarity}</span></div>
                    </div>

                    <!-- Overall Assessment -->
                    <div class="col-md-6 mb-4">
                        <h6 class="border-bottom pb-2 text-primary"><i class="fas fa-chart-line me-2"></i>Overall Assessment</h6>
                        <div class="mb-2"><strong>Immediate Application:</strong> ${renderStars(eval.immediate_application_rating)}</div>
                        <div class="mb-2"><strong>Performance Improvement:</strong> ${renderStars(eval.performance_improvement_rating)}</div>
                        <div class="mb-2"><strong>Recommend to Colleague:</strong> ${renderStars(eval.recommend_to_colleague_rating)}</div>
                    </div>

                    <!-- Comments -->
                    <div class="col-12">
                        <h6 class="border-bottom pb-2 text-primary"><i class="fas fa-comments me-2"></i>Additional Feedback</h6>
                        ${eval.most_valuable_part ? `
                            <div class="mb-3">
                                <strong>Most Valuable Part:</strong>
                                <p class="text-muted mt-1">${eval.most_valuable_part}</p>
                            </div>
                        ` : ''}
                        ${eval.areas_for_improvement ? `
                            <div class="mb-3">
                                <strong>Areas for Improvement:</strong>
                                <p class="text-muted mt-1">${eval.areas_for_improvement}</p>
                            </div>
                        ` : ''}
                        ${eval.additional_comments ? `
                            <div class="mb-3">
                                <strong>Additional Comments:</strong>
                                <p class="text-muted mt-1">${eval.additional_comments}</p>
                            </div>
                        ` : ''}
                    </div>

                    <!-- Submission Info -->
                    <div class="col-12 mt-3">
                        <div class="alert alert-info">
                            <small><strong>Submitted on:</strong> ${new Date(eval.submitted_at).toLocaleString()} by ${eval.submitted_by}</small>
                        </div>
                    </div>
                </div>
            `;

            document.getElementById('evaluationDetails').innerHTML = details;
        }

        function renderStars(rating) {
            if (!rating) return '<span class="text-muted">Not rated</span>';

            let stars = '';
            for (let i = 1; i <= 5; i++) {
                stars += `<i class="fas fa-star ${i <= rating ? 'text-warning' : 'text-muted'}"></i> `;
            }
            return stars + `<span class="text-muted">(${rating}/5)</span>`;
        }

        function getRatingColor(rating) {
            switch(rating?.toLowerCase()) {
                case 'excellent': return 'success';
                case 'good': return 'primary';
                case 'satisfactory': return 'info';
                case 'poor': return 'warning';
                case 'very poor': return 'danger';
                case 'yes': return 'success';
                case 'no': return 'danger';
                default: return 'secondary';
            }
        }

        function printEvaluation() {
            window.print();
        }
    </script>
</body>
</html>