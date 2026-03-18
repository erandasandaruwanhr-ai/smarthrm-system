<?php
session_start();
require_once '../../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Get appraisal ID
$appraisal_id = $_GET['id'] ?? null;

if (!$appraisal_id) {
    header("Location: executive_marks_data.php");
    exit();
}

try {
    $db = new Database();

    // Get complete appraisal data
    $sql = "SELECT * FROM executive_appraisals WHERE id = ?";
    $appraisal = $db->fetch($sql, [$appraisal_id]);

    if (!$appraisal) {
        header("Location: executive_marks_data.php?error=not_found");
        exit();
    }

} catch (Exception $e) {
    $error_message = "Error fetching appraisal data: " . $e->getMessage();
}

function displayRating($value, $label) {
    $rating = $value ? $value : 'Not rated';
    $stars = '';
    if ($value) {
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $value) {
                $stars .= '<i class="fas fa-star text-warning"></i>';
            } else {
                $stars .= '<i class="far fa-star text-muted"></i>';
            }
        }
    }
    return "<div class='rating-display mb-2'>
                <strong>{$label}:</strong> {$rating} {$stars}
            </div>";
}

function displayYesNo($value) {
    if ($value === 'yes') {
        return '<span class="badge bg-success">Yes</span>';
    } elseif ($value === 'no') {
        return '<span class="badge bg-danger">No</span>';
    }
    return '<span class="badge bg-secondary">Not answered</span>';
}

function formatServiceYears($decimal_years) {
    if (empty($decimal_years) || !is_numeric($decimal_years)) {
        return 'Not specified';
    }

    // Convert to float to ensure proper calculation
    $decimal_years = (float) $decimal_years;

    $years = floor($decimal_years);
    $months = round(($decimal_years - $years) * 12);

    // Handle edge case where rounding gives us 12 months
    if ($months == 12) {
        $years += 1;
        $months = 0;
    }

    $parts = [];
    if ($years > 0) {
        $parts[] = $years . ' year' . ($years != 1 ? 's' : '');
    }
    if ($months > 0) {
        $parts[] = $months . ' month' . ($months != 1 ? 's' : '');
    }

    if (empty($parts)) {
        return '0 months';
    }

    return implode(', ', $parts);
}

function calculateServiceYearsFromDate($joining_date) {
    if (empty($joining_date)) {
        return 'Not specified';
    }

    try {
        $joinDate = new DateTime($joining_date);
        $currentDate = new DateTime();
        $interval = $joinDate->diff($currentDate);

        $years = $interval->y;
        $months = $interval->m;

        $parts = [];
        if ($years > 0) {
            $parts[] = $years . ' year' . ($years != 1 ? 's' : '');
        }
        if ($months > 0) {
            $parts[] = $months . ' month' . ($months != 1 ? 's' : '');
        }

        if (empty($parts)) {
            return '0 months';
        }

        return implode(', ', $parts);
    } catch (Exception $e) {
        return 'Not specified';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Appraisal Details - SmartHRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        .detail-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .category-header {
            background: #f8f9fa;
            border-radius: 10px 10px 0 0;
            padding: 1rem;
            border-bottom: 2px solid #e9ecef;
        }

        .rating-display {
            margin-bottom: 0.5rem;
        }

        .rating-display .fas.fa-star,
        .rating-display .far.fa-star {
            font-size: 0.9rem;
            margin-left: 0.2rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .objective-box {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .compliance-item {
            padding: 1rem;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-0">
                        <i class="fas fa-file-alt me-3"></i>Executive Appraisal Details
                    </h1>
                    <p class="mb-0 mt-2 opacity-75">
                        Complete appraisal data for <?= htmlspecialchars($appraisal['appraisee_name']) ?>
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="no-print">
                        <a href="executive_marks_data.php" class="btn btn-outline-light me-2">
                            <i class="fas fa-arrow-left me-2"></i>Back to Tracking
                        </a>
                        <button onclick="window.print()" class="btn btn-light">
                            <i class="fas fa-print me-2"></i>Print
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- Basic Information -->
        <div class="detail-card">
            <div class="category-header">
                <h3 class="mb-0"><i class="fas fa-info-circle text-primary me-2"></i>Basic Information</h3>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <div>
                        <strong>Employee EPF:</strong> <?= htmlspecialchars($appraisal['appraisee_epf']) ?><br>
                        <strong>Employee Name:</strong> <?= htmlspecialchars($appraisal['appraisee_name']) ?><br>
                        <strong>Designation:</strong> <?= htmlspecialchars($appraisal['designation']) ?><br>
                        <strong>Department:</strong> <?= htmlspecialchars($appraisal['department']) ?><br>
                        <strong>Location:</strong> <?= htmlspecialchars($appraisal['location']) ?>
                    </div>
                    <div>
                        <strong>Manager EPF:</strong> <?= htmlspecialchars($appraisal['appraiser_epf']) ?><br>
                        <strong>Manager Name:</strong> <?= htmlspecialchars($appraisal['appraiser_name']) ?><br>
                        <strong>Joining Date:</strong> <?= $appraisal['joining_date'] ? date('M d, Y', strtotime($appraisal['joining_date'])) : 'Not specified' ?><br>
                        <strong>Service Years:</strong> <?= calculateServiceYearsFromDate($appraisal['joining_date']) ?><br>
                        <strong>Evaluation Date:</strong> <?= $appraisal['evaluation_date'] ? date('M d, Y', strtotime($appraisal['evaluation_date'])) : 'Not set' ?>
                    </div>
                </div>
                <div class="text-center mt-3">
                    <span class="status-badge <?= $appraisal['status'] === 'submitted' ? 'bg-success' : ($appraisal['status'] === 'draft' ? 'bg-warning' : 'bg-info') ?>">
                        Status: <?= ucfirst($appraisal['status']) ?>
                    </span>
                    <span class="status-badge bg-info ms-2">
                        Year: <?= htmlspecialchars($appraisal['appraisal_year']) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Category 1: Competency Evaluation -->
        <div class="detail-card">
            <div class="category-header">
                <h3 class="mb-0"><i class="fas fa-chart-line text-success me-2"></i>Category 1 — Competency Evaluation</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <?= displayRating($appraisal['competency_technical_skills'], 'Technical Skills') ?>
                        <?= displayRating($appraisal['competency_communication'], 'Communication') ?>
                        <?= displayRating($appraisal['competency_teamwork'], 'Teamwork') ?>
                        <?= displayRating($appraisal['competency_leadership'], 'Leadership') ?>
                    </div>
                    <div class="col-md-6">
                        <?= displayRating($appraisal['competency_problem_solving'], 'Problem Solving') ?>
                        <?= displayRating($appraisal['competency_adaptability'], 'Adaptability') ?>
                        <?= displayRating($appraisal['competency_time_management'], 'Time Management') ?>
                        <?= displayRating($appraisal['competency_customer_focus'], 'Customer Focus') ?>
                    </div>
                </div>

                <h5 class="mt-4 mb-3">Comments</h5>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Technical Skills:</strong><br><?= htmlspecialchars($appraisal['competency_technical_comments'] ?? 'No comments') ?></p>
                        <p><strong>Communication:</strong><br><?= htmlspecialchars($appraisal['competency_communication_comments'] ?? 'No comments') ?></p>
                        <p><strong>Teamwork:</strong><br><?= htmlspecialchars($appraisal['competency_teamwork_comments'] ?? 'No comments') ?></p>
                        <p><strong>Leadership:</strong><br><?= htmlspecialchars($appraisal['competency_leadership_comments'] ?? 'No comments') ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Problem Solving:</strong><br><?= htmlspecialchars($appraisal['competency_problem_solving_comments'] ?? 'No comments') ?></p>
                        <p><strong>Adaptability:</strong><br><?= htmlspecialchars($appraisal['competency_adaptability_comments'] ?? 'No comments') ?></p>
                        <p><strong>Time Management:</strong><br><?= htmlspecialchars($appraisal['competency_time_management_comments'] ?? 'No comments') ?></p>
                        <p><strong>Customer Focus:</strong><br><?= htmlspecialchars($appraisal['competency_customer_focus_comments'] ?? 'No comments') ?></p>
                    </div>
                </div>

                <?php if ($appraisal['competency_remark']): ?>
                    <div class="mt-3 p-3" style="background: #fff3cd; border-radius: 8px;">
                        <h6><i class="fas fa-comment-alt text-warning me-2"></i>Overall Competency Remarks:</h6>
                        <p class="mb-0"><?= htmlspecialchars($appraisal['competency_remark']) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Category 2: Achievements -->
        <div class="detail-card">
            <div class="category-header">
                <h3 class="mb-0"><i class="fas fa-trophy text-warning me-2"></i>Category 2 — Achievements</h3>
            </div>
            <div class="card-body">
                <?php for ($i = 1; $i <= 3; $i++): ?>
                    <?php if ($appraisal["achievement_$i"]): ?>
                        <div class="achievement-item mb-3 p-3" style="background: #d4edda; border-radius: 8px;">
                            <h6><i class="fas fa-star text-success me-2"></i>Achievement <?= $i ?></h6>
                            <p class="mb-0"><?= htmlspecialchars($appraisal["achievement_$i"]) ?></p>
                        </div>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if (!$appraisal['achievement_1'] && !$appraisal['achievement_2'] && !$appraisal['achievement_3']): ?>
                    <p class="text-muted text-center py-3">No achievements recorded</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Category 3: Areas for Development -->
        <div class="detail-card">
            <div class="category-header">
                <h3 class="mb-0"><i class="fas fa-arrow-up text-info me-2"></i>Category 3 — Areas for Competencies Development</h3>
            </div>
            <div class="card-body">
                <?php for ($i = 1; $i <= 2; $i++): ?>
                    <?php if ($appraisal["development_competency_$i"]): ?>
                        <div class="development-item mb-4 p-3" style="background: #cce7ff; border-radius: 8px;">
                            <h6><i class="fas fa-lightbulb text-info me-2"></i>Development Area <?= $i ?></h6>
                            <p><strong>Competency:</strong> <?= htmlspecialchars($appraisal["development_competency_$i"]) ?></p>
                            <?php if ($appraisal["development_plan_$i"]): ?>
                                <p><strong>Development Plan:</strong> <?= htmlspecialchars($appraisal["development_plan_$i"]) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if (!$appraisal['development_competency_1'] && !$appraisal['development_competency_2']): ?>
                    <p class="text-muted text-center py-3">No development areas recorded</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Category 4: Core Values -->
        <div class="detail-card">
            <div class="category-header">
                <h3 class="mb-0"><i class="fas fa-heart text-danger me-2"></i>Category 4 — Core Values</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <h6><i class="fas fa-handshake text-primary me-2"></i>Respectful</h6>
                        <?= displayRating($appraisal['core_values_respectful_self'], 'Self Rating') ?>
                        <?= displayRating($appraisal['core_values_respectful_manager'], 'Manager Rating') ?>
                    </div>
                    <div class="col-md-4">
                        <h6><i class="fas fa-fire text-danger me-2"></i>Passionate</h6>
                        <?= displayRating($appraisal['core_values_passionate_self'], 'Self Rating') ?>
                        <?= displayRating($appraisal['core_values_passionate_manager'], 'Manager Rating') ?>
                    </div>
                    <div class="col-md-4">
                        <h6><i class="fas fa-shield-alt text-success me-2"></i>Reliable</h6>
                        <?= displayRating($appraisal['core_values_reliable_self'], 'Self Rating') ?>
                        <?= displayRating($appraisal['core_values_reliable_manager'], 'Manager Rating') ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category 5: Attitudes and Behaviors -->
        <div class="detail-card">
            <div class="category-header">
                <h3 class="mb-0"><i class="fas fa-user-check text-purple me-2"></i>Category 5 — Attitudes and Behaviors</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>I live up to my promises and commitments</h6>
                        <?= displayRating($appraisal['attitude_promises_self'], 'Self Rating') ?>
                        <?= displayRating($appraisal['attitude_promises_manager'], 'Manager Rating') ?>
                        <hr>

                        <h6>I trust people and am loyal to decisions made</h6>
                        <?= displayRating($appraisal['attitude_trust_self'], 'Self Rating') ?>
                        <?= displayRating($appraisal['attitude_trust_manager'], 'Manager Rating') ?>
                        <hr>

                        <h6>I look for ways to improve and innovate</h6>
                        <?= displayRating($appraisal['attitude_improvement_self'], 'Self Rating') ?>
                        <?= displayRating($appraisal['attitude_improvement_manager'], 'Manager Rating') ?>
                        <hr>

                        <h6>I work together for a common goal</h6>
                        <?= displayRating($appraisal['attitude_teamwork_self'], 'Self Rating') ?>
                        <?= displayRating($appraisal['attitude_teamwork_manager'], 'Manager Rating') ?>
                    </div>
                    <div class="col-md-6">
                        <h6>I make decisions based on facts and teamwork</h6>
                        <?= displayRating($appraisal['attitude_decisions_self'], 'Self Rating') ?>
                        <?= displayRating($appraisal['attitude_decisions_manager'], 'Manager Rating') ?>
                        <hr>

                        <h6>I communicate properly and welcome feedback</h6>
                        <?= displayRating($appraisal['attitude_communication_self'], 'Self Rating') ?>
                        <?= displayRating($appraisal['attitude_communication_manager'], 'Manager Rating') ?>
                        <hr>

                        <h6>I act according to my principles</h6>
                        <?= displayRating($appraisal['attitude_principles_self'], 'Self Rating') ?>
                        <?= displayRating($appraisal['attitude_principles_manager'], 'Manager Rating') ?>
                        <hr>

                        <h6>I am always open to the needs of the customer</h6>
                        <?= displayRating($appraisal['attitude_customer_self'], 'Self Rating') ?>
                        <?= displayRating($appraisal['attitude_customer_manager'], 'Manager Rating') ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category 6: Objectives -->
        <div class="detail-card">
            <div class="category-header">
                <h3 class="mb-0"><i class="fas fa-target text-primary me-2"></i>Category 6 — Objectives Planning and Evaluation</h3>
            </div>
            <div class="card-body">
                <?php for ($i = 1; $i <= 3; $i++): ?>
                    <?php if ($appraisal["objective_$i"]): ?>
                        <div class="objective-box">
                            <h6><i class="fas fa-bullseye text-primary me-2"></i>Objective <?= $i ?></h6>
                            <p><strong>Objective:</strong> <?= htmlspecialchars($appraisal["objective_$i"]) ?></p>
                            <?php if ($appraisal["objective_{$i}_evaluation"]): ?>
                                <p><strong>Evaluation:</strong> <?= htmlspecialchars($appraisal["objective_{$i}_evaluation"]) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Category 7: Development and Training -->
        <?php if ($appraisal['development_training']): ?>
        <div class="detail-card">
            <div class="category-header">
                <h3 class="mb-0"><i class="fas fa-graduation-cap text-success me-2"></i>Category 7 — Development and Training</h3>
            </div>
            <div class="card-body">
                <p><?= htmlspecialchars($appraisal['development_training']) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Category 8: Future Growth -->
        <?php if ($appraisal['future_growth']): ?>
        <div class="detail-card">
            <div class="category-header">
                <h3 class="mb-0"><i class="fas fa-seedling text-success me-2"></i>Category 8 — Future Growth</h3>
            </div>
            <div class="card-body">
                <p><?= htmlspecialchars($appraisal['future_growth']) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Category 9: Manager Performance Feedback -->
        <?php if ($appraisal['manager_performance_feedback'] || $appraisal['manager_improvement_areas']): ?>
        <div class="detail-card">
            <div class="category-header">
                <h3 class="mb-0"><i class="fas fa-comments text-info me-2"></i>Category 9 — Feedback on Manager's/Supervisor's Performance</h3>
            </div>
            <div class="card-body">
                <?php if ($appraisal['manager_performance_feedback']): ?>
                    <h6><i class="fas fa-thumbs-up text-success me-2"></i>Performance Feedback</h6>
                    <p><?= htmlspecialchars($appraisal['manager_performance_feedback']) ?></p>
                <?php endif; ?>

                <?php if ($appraisal['manager_improvement_areas']): ?>
                    <h6><i class="fas fa-arrow-up text-warning me-2"></i>Improvement Areas</h6>
                    <p><?= htmlspecialchars($appraisal['manager_improvement_areas']) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Category 10: Other Discussion Areas -->
        <?php if ($appraisal['discussion_point_1'] || $appraisal['discussion_point_2'] || $appraisal['discussion_point_3']): ?>
        <div class="detail-card">
            <div class="category-header">
                <h3 class="mb-0"><i class="fas fa-conversation text-primary me-2"></i>Category 10 — Other Areas of Discussion/Reflection/Feedback</h3>
            </div>
            <div class="card-body">
                <?php for ($i = 1; $i <= 3; $i++): ?>
                    <?php if ($appraisal["discussion_point_$i"]): ?>
                        <div class="mb-3 p-3" style="background: #f8f9fa; border-radius: 8px;">
                            <h6><i class="fas fa-comment text-primary me-2"></i>Discussion Point <?= $i ?></h6>
                            <p class="mb-0"><?= htmlspecialchars($appraisal["discussion_point_$i"]) ?></p>
                        </div>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Category 11: Compliance -->
        <div class="detail-card">
            <div class="category-header">
                <h3 class="mb-0"><i class="fas fa-check-circle text-success me-2"></i>Category 11 — Compliance</h3>
            </div>
            <div class="card-body">
                <?php for ($i = 1; $i <= 3; $i++): ?>
                    <div class="compliance-item">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0">Compliance Question <?= $i ?></h6>
                            <?= displayYesNo($appraisal["compliance_q$i"]) ?>
                        </div>
                        <?php if ($appraisal["compliance_q{$i}_comments"]): ?>
                            <p class="mb-0 text-muted"><?= htmlspecialchars($appraisal["compliance_q{$i}_comments"]) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Timestamps -->
        <div class="detail-card">
            <div class="category-header">
                <h3 class="mb-0"><i class="fas fa-clock text-muted me-2"></i>Timestamps</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <strong>Created:</strong><br>
                        <?= date('M d, Y H:i', strtotime($appraisal['created_at'])) ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Last Updated:</strong><br>
                        <?= date('M d, Y H:i', strtotime($appraisal['updated_at'])) ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Submitted:</strong><br>
                        <?= $appraisal['submitted_at'] ? date('M d, Y H:i', strtotime($appraisal['submitted_at'])) : 'Not submitted' ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="text-center mb-5 no-print">
            <a href="executive_marks_data.php" class="btn btn-secondary me-2">
                <i class="fas fa-arrow-left me-2"></i>Back to Tracking
            </a>
            <button onclick="window.print()" class="btn btn-outline-primary">
                <i class="fas fa-print me-2"></i>Print Appraisal
            </button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>