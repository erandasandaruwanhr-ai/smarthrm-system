<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/performance_auth.php';

// Check if user has view marks permission
requirePerformancePermission('performance.view_marks');

$user = getCurrentUser();
$db = new Database();

// Get appraisal ID
$appraisal_id = $_GET['id'] ?? null;
if (!$appraisal_id) {
    header('Location: staff_marks_data.php');
    exit;
}

// Get appraisal details
$appraisal = $db->fetch("
    SELECT *
    FROM staff_appraisals
    WHERE id = ?
", [$appraisal_id]);

if (!$appraisal) {
    header('Location: staff_marks_data.php?error=1');
    exit;
}

// Calculate totals and percentages
$category_scores = [
    'Job Knowledge & Skills' => [
        'questions' => ['job_knowledge_q1', 'job_knowledge_q2', 'job_knowledge_q3'],
        'max_score' => 15,
        'total' => ($appraisal['job_knowledge_q1'] ?? 0) + ($appraisal['job_knowledge_q2'] ?? 0) + ($appraisal['job_knowledge_q3'] ?? 0)
    ],
    'Creativity/Innovation' => [
        'questions' => ['creativity_q1'],
        'max_score' => 5,
        'total' => ($appraisal['creativity_q1'] ?? 0)
    ],
    'Quality Awareness' => [
        'questions' => ['quality_awareness_q1', 'quality_awareness_q2'],
        'max_score' => 10,
        'total' => ($appraisal['quality_awareness_q1'] ?? 0) + ($appraisal['quality_awareness_q2'] ?? 0)
    ],
    'Goal Achievement' => [
        'questions' => ['goal_achievement_q1'],
        'max_score' => 5,
        'total' => ($appraisal['goal_achievement_q1'] ?? 0)
    ],
    'Teamwork' => [
        'questions' => ['teamwork_q1', 'teamwork_q2', 'teamwork_q3', 'teamwork_q4'],
        'max_score' => 20,
        'total' => ($appraisal['teamwork_q1'] ?? 0) + ($appraisal['teamwork_q2'] ?? 0) + ($appraisal['teamwork_q3'] ?? 0) + ($appraisal['teamwork_q4'] ?? 0)
    ],
    'Cultural Awareness' => [
        'questions' => ['cultural_awareness_q1', 'cultural_awareness_q2', 'cultural_awareness_q3', 'cultural_awareness_q4', 'cultural_awareness_q5', 'cultural_awareness_q6'],
        'max_score' => 30,
        'total' => ($appraisal['cultural_awareness_q1'] ?? 0) + ($appraisal['cultural_awareness_q2'] ?? 0) + ($appraisal['cultural_awareness_q3'] ?? 0) + ($appraisal['cultural_awareness_q4'] ?? 0) + ($appraisal['cultural_awareness_q5'] ?? 0) + ($appraisal['cultural_awareness_q6'] ?? 0)
    ],
    'Safety Consciousness' => [
        'questions' => ['safety_consciousness_q1', 'safety_consciousness_q2', 'safety_consciousness_q3', 'safety_consciousness_q4'],
        'max_score' => 20,
        'total' => ($appraisal['safety_consciousness_q1'] ?? 0) + ($appraisal['safety_consciousness_q2'] ?? 0) + ($appraisal['safety_consciousness_q3'] ?? 0) + ($appraisal['safety_consciousness_q4'] ?? 0)
    ],
    'Discipline' => [
        'questions' => ['discipline_q1', 'discipline_q2'],
        'max_score' => 10,
        'total' => ($appraisal['discipline_q1'] ?? 0) + ($appraisal['discipline_q2'] ?? 0)
    ]
];

$overall_total = array_sum(array_column($category_scores, 'total'));
$overall_max = array_sum(array_column($category_scores, 'max_score'));

function getPerformanceGrade($score, $maxScore) {
    if ($maxScore == 0) return 'N/A';
    $percentage = ($score / $maxScore) * 100;
    if ($percentage >= 90) return 'A+';
    if ($percentage >= 80) return 'A';
    if ($percentage >= 70) return 'B+';
    if ($percentage >= 60) return 'B';
    if ($percentage >= 50) return 'C';
    return 'D';
}

function getProgressBarColor($score, $maxScore) {
    if ($maxScore == 0) return 'bg-secondary';
    $percentage = ($score / $maxScore) * 100;
    if ($percentage >= 80) return 'bg-success';
    if ($percentage >= 60) return 'bg-info';
    if ($percentage >= 40) return 'bg-warning';
    return 'bg-danger';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Staff Appraisal Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .header-section {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .score-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-left: 5px solid #007bff;
        }
        .overall-score {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
        }
        .grade-display {
            font-size: 4rem;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .info-box {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .progress-custom {
            height: 25px;
            border-radius: 15px;
        }
        .question-detail {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-left: 3px solid #007bff;
        }
        .status-badge {
            font-size: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
        }
        @media print {
            .no-print { display: none; }
            .header-section { background: #007bff !important; }
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <div class="header-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-0"><i class="fas fa-clipboard-check me-3"></i>Staff Appraisal Details</h1>
                    <p class="mb-0 opacity-75">Detailed performance evaluation report</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="no-print">
                        <button class="btn btn-light me-2" onclick="window.print()">
                            <i class="fas fa-print me-1"></i>Print
                        </button>
                        <button class="btn btn-outline-light" onclick="window.close()">
                            <i class="fas fa-times me-1"></i>Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Employee Information -->
        <div class="info-box">
            <div class="row">
                <div class="col-md-6">
                    <h5 class="text-primary mb-3"><i class="fas fa-user me-2"></i>Employee Information</h5>
                    <table class="table table-borderless">
                        <tr>
                            <td class="fw-bold">Name:</td>
                            <td><?= htmlspecialchars($appraisal['appraisee_name']) ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold">EPF Number:</td>
                            <td><?= htmlspecialchars($appraisal['appraisee_epf']) ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Designation:</td>
                            <td><?= htmlspecialchars($appraisal['designation']) ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Department:</td>
                            <td><?= htmlspecialchars($appraisal['department']) ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Location:</td>
                            <td><?= htmlspecialchars($appraisal['location']) ?></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h5 class="text-primary mb-3"><i class="fas fa-calendar me-2"></i>Appraisal Information</h5>
                    <table class="table table-borderless">
                        <tr>
                            <td class="fw-bold">Appraisal Year:</td>
                            <td><?= htmlspecialchars($appraisal['appraisal_year']) ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Appraiser:</td>
                            <td><?= htmlspecialchars($appraisal['appraiser_name']) ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Joining Date:</td>
                            <td><?= date('d/m/Y', strtotime($appraisal['joining_date'])) ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Service Years:</td>
                            <td><?php
                                // Display service years - calculate from joining date if not available
                                if (!empty($appraisal['service_years']) && $appraisal['service_years'] !== null) {
                                    if (is_numeric($appraisal['service_years'])) {
                                        echo number_format($appraisal['service_years'], 1) . ' years';
                                    } else {
                                        echo htmlspecialchars($appraisal['service_years']);
                                    }
                                } elseif (!empty($appraisal['joining_date'])) {
                                    $joining_date = new DateTime($appraisal['joining_date']);
                                    $current_date = new DateTime();
                                    $interval = $current_date->diff($joining_date);
                                    $years = $interval->y;
                                    $months = $interval->m;
                                    if ($years > 0) {
                                        echo $years . ' years';
                                        if ($months > 0) {
                                            echo ' ' . $months . ' months';
                                        }
                                    } else {
                                        echo $months . ' months';
                                    }
                                } else {
                                    echo 'N/A';
                                }
                            ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Status:</td>
                            <td>
                                <span class="badge status-badge
                                    <?= $appraisal['status'] === 'approved' ? 'bg-success' :
                                       ($appraisal['status'] === 'submitted' ? 'bg-info' :
                                       ($appraisal['status'] === 'rejected' ? 'bg-danger' : 'bg-warning')) ?>">
                                    <?= ucfirst($appraisal['status']) ?>
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Overall Score -->
        <div class="overall-score">
            <div class="row align-items-center">
                <div class="col-md-4 text-center">
                    <div class="grade-display"><?= getPerformanceGrade($overall_total, $overall_max) ?></div>
                    <h5>Overall Grade</h5>
                </div>
                <div class="col-md-4 text-center">
                    <h2 class="mb-0"><?= $overall_total ?> / <?= $overall_max ?></h2>
                    <h5>Total Score</h5>
                </div>
                <div class="col-md-4 text-center">
                    <h2 class="mb-0"><?= $overall_max > 0 ? round(($overall_total / $overall_max) * 100) : 0 ?>%</h2>
                    <h5>Performance Percentage</h5>
                </div>
            </div>
        </div>

        <!-- Category Breakdown -->
        <div class="row">
            <?php foreach ($category_scores as $category => $data): ?>
                <?php
                $percentage = $data['max_score'] > 0 ? round(($data['total'] / $data['max_score']) * 100) : 0;
                $progressColor = getProgressBarColor($data['total'], $data['max_score']);
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="score-card">
                        <h6 class="text-primary mb-3"><?= $category ?></h6>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="fw-bold"><?= $data['total'] ?> / <?= $data['max_score'] ?></span>
                            <span class="text-muted"><?= $percentage ?>%</span>
                        </div>
                        <div class="progress progress-custom">
                            <div class="progress-bar <?= $progressColor ?>"
                                 style="width: <?= $percentage ?>%"
                                 aria-valuenow="<?= $percentage ?>"
                                 aria-valuemin="0"
                                 aria-valuemax="100">
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Leave Utilization -->
        <?php if ($appraisal['annual_leave_utilization'] || $appraisal['casual_leave_utilization'] || $appraisal['medical_leave_utilization'] || $appraisal['no_pay_days']): ?>
        <div class="info-box">
            <h5 class="text-primary mb-3"><i class="fas fa-calendar-times me-2"></i>Leave Utilization</h5>
            <div class="row">
                <div class="col-md-3">
                    <div class="text-center">
                        <h4 class="text-info"><?= $appraisal['annual_leave_utilization'] ?? 0 ?></h4>
                        <small>Annual Leave Days</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <h4 class="text-success"><?= $appraisal['casual_leave_utilization'] ?? 0 ?></h4>
                        <small>Casual Leave Days</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <h4 class="text-warning"><?= $appraisal['medical_leave_utilization'] ?? 0 ?></h4>
                        <small>Medical Leave Days</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <h4 class="text-danger"><?= $appraisal['no_pay_days'] ?? 0 ?></h4>
                        <small>No Pay Days</small>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Remarks and Comments -->
        <div class="info-box">
            <h5 class="text-primary mb-3"><i class="fas fa-comments me-2"></i>Remarks and Comments</h5>
            <div class="row">
                <?php if ($appraisal['job_knowledge_remark']): ?>
                <div class="col-md-6">
                    <h6>Job Knowledge & Skills</h6>
                    <p class="text-muted"><?= htmlspecialchars($appraisal['job_knowledge_remark']) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($appraisal['creativity_remark']): ?>
                <div class="col-md-6">
                    <h6>Creativity/Innovation</h6>
                    <p class="text-muted"><?= htmlspecialchars($appraisal['creativity_remark']) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($appraisal['quality_awareness_remark']): ?>
                <div class="col-md-6">
                    <h6>Quality Awareness</h6>
                    <p class="text-muted"><?= htmlspecialchars($appraisal['quality_awareness_remark']) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($appraisal['goal_achievement_remark']): ?>
                <div class="col-md-6">
                    <h6>Goal Achievement</h6>
                    <p class="text-muted"><?= htmlspecialchars($appraisal['goal_achievement_remark']) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($appraisal['teamwork_remark']): ?>
                <div class="col-md-6">
                    <h6>Teamwork</h6>
                    <p class="text-muted"><?= htmlspecialchars($appraisal['teamwork_remark']) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($appraisal['cultural_awareness_remark']): ?>
                <div class="col-md-6">
                    <h6>Cultural Awareness</h6>
                    <p class="text-muted"><?= htmlspecialchars($appraisal['cultural_awareness_remark']) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($appraisal['safety_consciousness_remark']): ?>
                <div class="col-md-6">
                    <h6>Safety Consciousness</h6>
                    <p class="text-muted"><?= htmlspecialchars($appraisal['safety_consciousness_remark']) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($appraisal['discipline_remark']): ?>
                <div class="col-md-6">
                    <h6>Discipline</h6>
                    <p class="text-muted"><?= htmlspecialchars($appraisal['discipline_remark']) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Additional Information -->
        <?php if ($appraisal['additional_comments'] || $appraisal['special_talents'] || $appraisal['future_expectations']): ?>
        <div class="info-box">
            <h5 class="text-primary mb-3"><i class="fas fa-info-circle me-2"></i>Additional Information</h5>

            <?php if ($appraisal['additional_comments']): ?>
            <div class="mb-3">
                <h6>Additional Comments</h6>
                <p class="text-muted"><?= nl2br(htmlspecialchars($appraisal['additional_comments'])) ?></p>
            </div>
            <?php endif; ?>

            <?php if ($appraisal['special_talents']): ?>
            <div class="mb-3">
                <h6>Special Talents/Skills</h6>
                <p class="text-muted"><?= nl2br(htmlspecialchars($appraisal['special_talents'])) ?></p>
            </div>
            <?php endif; ?>

            <?php if ($appraisal['future_expectations']): ?>
            <div class="mb-3">
                <h6>Future Expectations/Development Areas</h6>
                <p class="text-muted"><?= nl2br(htmlspecialchars($appraisal['future_expectations'])) ?></p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Disciplinary Actions -->
        <?php if ($appraisal['discipline_q3'] || $appraisal['discipline_q4']): ?>
        <div class="info-box">
            <h5 class="text-danger mb-3"><i class="fas fa-exclamation-triangle me-2"></i>Disciplinary Actions</h5>
            <?php if ($appraisal['discipline_q3']): ?>
            <div class="alert alert-warning">
                <i class="fas fa-warning me-2"></i>Employee received verbal warnings during the appraisal period
            </div>
            <?php endif; ?>
            <?php if ($appraisal['discipline_q4']): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>Employee received written warnings during the appraisal period
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="text-center py-4 text-muted">
            <small>Generated on <?= date('d/m/Y H:i:s') ?> | SmartHRM Performance Management System</small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>