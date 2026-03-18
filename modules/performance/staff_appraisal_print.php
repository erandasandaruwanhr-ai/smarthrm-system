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

// Calculate totals
$job_knowledge_total = ($appraisal['job_knowledge_q1'] ?? 0) + ($appraisal['job_knowledge_q2'] ?? 0) + ($appraisal['job_knowledge_q3'] ?? 0);
$creativity_total = ($appraisal['creativity_q1'] ?? 0);
$quality_total = ($appraisal['quality_awareness_q1'] ?? 0) + ($appraisal['quality_awareness_q2'] ?? 0);
$goal_total = ($appraisal['goal_achievement_q1'] ?? 0);
$teamwork_total = ($appraisal['teamwork_q1'] ?? 0) + ($appraisal['teamwork_q2'] ?? 0) + ($appraisal['teamwork_q3'] ?? 0) + ($appraisal['teamwork_q4'] ?? 0);
$cultural_total = ($appraisal['cultural_awareness_q1'] ?? 0) + ($appraisal['cultural_awareness_q2'] ?? 0) + ($appraisal['cultural_awareness_q3'] ?? 0) + ($appraisal['cultural_awareness_q4'] ?? 0) + ($appraisal['cultural_awareness_q5'] ?? 0) + ($appraisal['cultural_awareness_q6'] ?? 0);
$safety_total = ($appraisal['safety_consciousness_q1'] ?? 0) + ($appraisal['safety_consciousness_q2'] ?? 0) + ($appraisal['safety_consciousness_q3'] ?? 0) + ($appraisal['safety_consciousness_q4'] ?? 0);
$discipline_total = ($appraisal['discipline_q1'] ?? 0) + ($appraisal['discipline_q2'] ?? 0);

$overall_total = $job_knowledge_total + $creativity_total + $quality_total + $goal_total + $teamwork_total + $cultural_total + $safety_total + $discipline_total;
$overall_max = 115; // Total possible score
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Performance Appraisal Report - <?= htmlspecialchars($appraisal['appraisee_name']) ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
            line-height: 1.4;
        }

        .header {
            text-align: center;
            border-bottom: 3px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .company-info {
            margin-bottom: 10px;
        }

        .report-title {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
            margin: 15px 0;
        }

        .info-section {
            margin-bottom: 25px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .info-table td {
            padding: 8px 12px;
            border: 1px solid #ddd;
            vertical-align: top;
        }

        .info-table .label {
            background-color: #f8f9fa;
            font-weight: bold;
            width: 30%;
        }

        .section-header {
            background-color: #007bff;
            color: white;
            padding: 10px 15px;
            margin: 20px 0 10px 0;
            font-weight: bold;
            font-size: 16px;
        }

        .score-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .score-table th,
        .score-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }

        .score-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }

        .total-row {
            background-color: #e9ecef;
            font-weight: bold;
        }

        .grade-box {
            text-align: center;
            border: 2px solid #007bff;
            padding: 20px;
            margin: 20px 0;
            background-color: #f8f9fa;
        }

        .grade-display {
            font-size: 48px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 10px;
        }

        .remarks-section {
            margin: 20px 0;
        }

        .remarks-box {
            border: 1px solid #ddd;
            padding: 15px;
            background-color: #f9f9f9;
            margin-bottom: 15px;
        }

        .remarks-title {
            font-weight: bold;
            color: #007bff;
            margin-bottom: 8px;
        }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #666;
            font-size: 12px;
        }

        .signature-section {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }

        .signature-box {
            text-align: center;
            width: 200px;
            border-top: 1px solid #333;
            padding-top: 10px;
        }

        @media print {
            body { margin: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="company-info">
            <h2 style="margin: 0;">SmartHRM - HR Management System</h2>
            <p style="margin: 5px 0;">Performance Management Module</p>
        </div>
        <div class="report-title">STAFF PERFORMANCE APPRAISAL REPORT</div>
        <p>Year: <?= htmlspecialchars($appraisal['appraisal_year']) ?></p>
    </div>

    <!-- Employee Information -->
    <div class="section-header">Employee Information</div>
    <table class="info-table">
        <tr>
            <td class="label">Employee Name</td>
            <td><?= htmlspecialchars($appraisal['appraisee_name']) ?></td>
            <td class="label">EPF Number</td>
            <td><?= htmlspecialchars($appraisal['appraisee_epf']) ?></td>
        </tr>
        <tr>
            <td class="label">Designation</td>
            <td><?= htmlspecialchars($appraisal['designation']) ?></td>
            <td class="label">Department</td>
            <td><?= htmlspecialchars($appraisal['department']) ?></td>
        </tr>
        <tr>
            <td class="label">Location</td>
            <td><?= htmlspecialchars($appraisal['location']) ?></td>
            <td class="label">Joining Date</td>
            <td><?= date('d/m/Y', strtotime($appraisal['joining_date'])) ?></td>
        </tr>
        <tr>
            <td class="label">Service Years</td>
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
            <td class="label">Appraiser</td>
            <td><?= htmlspecialchars($appraisal['appraiser_name']) ?></td>
        </tr>
    </table>

    <!-- Performance Scores -->
    <div class="section-header">Performance Evaluation Scores</div>
    <table class="score-table">
        <thead>
            <tr>
                <th>Category</th>
                <th>Score Achieved</th>
                <th>Maximum Score</th>
                <th>Percentage</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Job Knowledge & Skills</td>
                <td><?= $job_knowledge_total ?></td>
                <td>15</td>
                <td><?= round(($job_knowledge_total / 15) * 100) ?>%</td>
            </tr>
            <tr>
                <td>Creativity/Innovation</td>
                <td><?= $creativity_total ?></td>
                <td>5</td>
                <td><?= round(($creativity_total / 5) * 100) ?>%</td>
            </tr>
            <tr>
                <td>Quality Awareness</td>
                <td><?= $quality_total ?></td>
                <td>10</td>
                <td><?= round(($quality_total / 10) * 100) ?>%</td>
            </tr>
            <tr>
                <td>Goal Achievement</td>
                <td><?= $goal_total ?></td>
                <td>5</td>
                <td><?= round(($goal_total / 5) * 100) ?>%</td>
            </tr>
            <tr>
                <td>Teamwork</td>
                <td><?= $teamwork_total ?></td>
                <td>20</td>
                <td><?= round(($teamwork_total / 20) * 100) ?>%</td>
            </tr>
            <tr>
                <td>Cultural Awareness</td>
                <td><?= $cultural_total ?></td>
                <td>30</td>
                <td><?= round(($cultural_total / 30) * 100) ?>%</td>
            </tr>
            <tr>
                <td>Safety Consciousness</td>
                <td><?= $safety_total ?></td>
                <td>20</td>
                <td><?= round(($safety_total / 20) * 100) ?>%</td>
            </tr>
            <tr>
                <td>Discipline</td>
                <td><?= $discipline_total ?></td>
                <td>10</td>
                <td><?= round(($discipline_total / 10) * 100) ?>%</td>
            </tr>
            <tr class="total-row">
                <td><strong>OVERALL TOTAL</strong></td>
                <td><strong><?= $overall_total ?></strong></td>
                <td><strong><?= $overall_max ?></strong></td>
                <td><strong><?= round(($overall_total / $overall_max) * 100) ?>%</strong></td>
            </tr>
        </tbody>
    </table>

    <!-- Grade Display -->
    <div class="grade-box">
        <div class="grade-display"><?= getPerformanceGrade($overall_total, $overall_max) ?></div>
        <div><strong>Overall Performance Grade</strong></div>
    </div>

    <!-- Leave Utilization -->
    <?php if ($appraisal['annual_leave_utilization'] || $appraisal['casual_leave_utilization'] || $appraisal['medical_leave_utilization'] || $appraisal['no_pay_days']): ?>
    <div class="section-header">Leave Utilization</div>
    <table class="info-table">
        <tr>
            <td class="label">Annual Leave Days</td>
            <td><?= $appraisal['annual_leave_utilization'] ?? 0 ?></td>
            <td class="label">Casual Leave Days</td>
            <td><?= $appraisal['casual_leave_utilization'] ?? 0 ?></td>
        </tr>
        <tr>
            <td class="label">Medical Leave Days</td>
            <td><?= $appraisal['medical_leave_utilization'] ?? 0 ?></td>
            <td class="label">No Pay Days</td>
            <td><?= $appraisal['no_pay_days'] ?? 0 ?></td>
        </tr>
    </table>
    <?php endif; ?>

    <!-- Remarks Section -->
    <div class="section-header">Remarks and Comments</div>
    <div class="remarks-section">
        <?php
        $remarks = [
            'Job Knowledge & Skills' => $appraisal['job_knowledge_remark'],
            'Creativity/Innovation' => $appraisal['creativity_remark'],
            'Quality Awareness' => $appraisal['quality_awareness_remark'],
            'Goal Achievement' => $appraisal['goal_achievement_remark'],
            'Teamwork' => $appraisal['teamwork_remark'],
            'Cultural Awareness' => $appraisal['cultural_awareness_remark'],
            'Safety Consciousness' => $appraisal['safety_consciousness_remark'],
            'Discipline' => $appraisal['discipline_remark']
        ];

        foreach ($remarks as $category => $remark) {
            if (!empty($remark)) {
                echo "<div class='remarks-box'>";
                echo "<div class='remarks-title'>$category:</div>";
                echo "<div>" . nl2br(htmlspecialchars($remark)) . "</div>";
                echo "</div>";
            }
        }
        ?>
    </div>

    <!-- Additional Information -->
    <?php if ($appraisal['additional_comments'] || $appraisal['special_talents'] || $appraisal['future_expectations']): ?>
    <div class="section-header">Additional Information</div>
    <div class="remarks-section">
        <?php if ($appraisal['additional_comments']): ?>
        <div class="remarks-box">
            <div class="remarks-title">Additional Comments:</div>
            <div><?= nl2br(htmlspecialchars($appraisal['additional_comments'])) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($appraisal['special_talents']): ?>
        <div class="remarks-box">
            <div class="remarks-title">Special Talents/Skills:</div>
            <div><?= nl2br(htmlspecialchars($appraisal['special_talents'])) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($appraisal['future_expectations']): ?>
        <div class="remarks-box">
            <div class="remarks-title">Future Expectations/Development Areas:</div>
            <div><?= nl2br(htmlspecialchars($appraisal['future_expectations'])) ?></div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Disciplinary Actions -->
    <?php if ($appraisal['discipline_q3'] || $appraisal['discipline_q4']): ?>
    <div class="section-header">Disciplinary Actions During Appraisal Period</div>
    <div class="remarks-section">
        <?php if ($appraisal['discipline_q3']): ?>
        <div class="remarks-box" style="border-color: #ffc107;">
            <strong>⚠ Verbal Warnings Issued:</strong> Yes
        </div>
        <?php endif; ?>
        <?php if ($appraisal['discipline_q4']): ?>
        <div class="remarks-box" style="border-color: #dc3545;">
            <strong>⚠ Written Warnings Issued:</strong> Yes
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Signature Section -->
    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line"></div>
            <div>Employee Signature</div>
            <div>Date: ___________</div>
        </div>
        <div class="signature-box">
            <div class="signature-line"></div>
            <div>Appraiser Signature</div>
            <div>Date: ___________</div>
        </div>
        <div class="signature-box">
            <div class="signature-line"></div>
            <div>HR Manager Signature</div>
            <div>Date: ___________</div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>This document was generated automatically by SmartHRM System on <?= date('d/m/Y H:i:s') ?></p>
        <p>Performance Management Module - Staff Appraisal Report</p>
    </div>

    <script>
        // Auto print when page loads
        window.onload = function() {
            setTimeout(() => {
                window.print();
            }, 1000);
        };
    </script>
</body>
</html>