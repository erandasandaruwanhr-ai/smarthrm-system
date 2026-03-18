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
    header('Location: executive_marks_data.php');
    exit;
}

// Get appraisal details
$appraisal = $db->fetch("
    SELECT *
    FROM executive_appraisals
    WHERE id = ?
", [$appraisal_id]);

if (!$appraisal) {
    header('Location: executive_marks_data.php?error=1');
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
$competency_total = ($appraisal['competency_technical_skills'] ?? 0) + ($appraisal['competency_communication'] ?? 0) + ($appraisal['competency_leadership'] ?? 0) + ($appraisal['competency_decision_making'] ?? 0);
$goal_total = ($appraisal['goal_achievement_q1'] ?? 0) + ($appraisal['goal_achievement_q2'] ?? 0) + ($appraisal['goal_achievement_q3'] ?? 0);
$leadership_total = ($appraisal['leadership_team_building'] ?? 0) + ($appraisal['leadership_delegation'] ?? 0) + ($appraisal['leadership_motivation'] ?? 0) + ($appraisal['leadership_conflict_resolution'] ?? 0);
$strategic_total = ($appraisal['strategic_planning'] ?? 0) + ($appraisal['strategic_analysis'] ?? 0) + ($appraisal['strategic_innovation'] ?? 0);
$communication_total = ($appraisal['communication_clarity'] ?? 0) + ($appraisal['communication_stakeholder'] ?? 0) + ($appraisal['collaboration_cross_functional'] ?? 0);

$overall_total = $competency_total + $goal_total + $leadership_total + $strategic_total + $communication_total;
$overall_max = 85; // Total possible score
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Performance Appraisal Report - <?= htmlspecialchars($appraisal['appraisee_name']) ?></title>
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
            border-bottom: 3px solid #6f42c1;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .company-info {
            margin-bottom: 10px;
        }

        .report-title {
            font-size: 24px;
            font-weight: bold;
            color: #6f42c1;
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
            background-color: #6f42c1;
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
            border: 2px solid #6f42c1;
            padding: 20px;
            margin: 20px 0;
            background-color: #f8f9fa;
        }

        .grade-display {
            font-size: 48px;
            font-weight: bold;
            color: #6f42c1;
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
            color: #6f42c1;
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
        <div class="report-title">EXECUTIVE PERFORMANCE APPRAISAL REPORT</div>
        <p>Year: <?= htmlspecialchars($appraisal['appraisal_year']) ?></p>
    </div>

    <!-- Executive Information -->
    <div class="section-header">Executive Information</div>
    <table class="info-table">
        <tr>
            <td class="label">Executive Name</td>
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
                <td>Competency Evaluation</td>
                <td><?= $competency_total ?></td>
                <td>20</td>
                <td><?= round(($competency_total / 20) * 100) ?>%</td>
            </tr>
            <tr>
                <td>Goal Achievement</td>
                <td><?= $goal_total ?></td>
                <td>15</td>
                <td><?= round(($goal_total / 15) * 100) ?>%</td>
            </tr>
            <tr>
                <td>Leadership & Management</td>
                <td><?= $leadership_total ?></td>
                <td>20</td>
                <td><?= round(($leadership_total / 20) * 100) ?>%</td>
            </tr>
            <tr>
                <td>Strategic Thinking</td>
                <td><?= $strategic_total ?></td>
                <td>15</td>
                <td><?= round(($strategic_total / 15) * 100) ?>%</td>
            </tr>
            <tr>
                <td>Communication & Collaboration</td>
                <td><?= $communication_total ?></td>
                <td>15</td>
                <td><?= round(($communication_total / 15) * 100) ?>%</td>
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

    <!-- Remarks Section -->
    <div class="section-header">Remarks and Comments</div>
    <div class="remarks-section">
        <?php
        $remarks = [
            'Technical Skills' => $appraisal['competency_technical_comments'],
            'Goal Achievement' => $appraisal['goal_achievement_comments'],
            'Leadership & Management' => $appraisal['leadership_comments'],
            'Strategic Thinking' => $appraisal['strategic_comments'],
            'Communication & Collaboration' => $appraisal['communication_comments']
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
    <?php if ($appraisal['achievements_highlights'] || $appraisal['development_plan'] || $appraisal['future_goals']): ?>
    <div class="section-header">Additional Information</div>
    <div class="remarks-section">
        <?php if ($appraisal['achievements_highlights']): ?>
        <div class="remarks-box">
            <div class="remarks-title">Key Achievements & Highlights:</div>
            <div><?= nl2br(htmlspecialchars($appraisal['achievements_highlights'])) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($appraisal['development_plan']): ?>
        <div class="remarks-box">
            <div class="remarks-title">Development Plan:</div>
            <div><?= nl2br(htmlspecialchars($appraisal['development_plan'])) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($appraisal['future_goals']): ?>
        <div class="remarks-box">
            <div class="remarks-title">Future Goals & Expectations:</div>
            <div><?= nl2br(htmlspecialchars($appraisal['future_goals'])) ?></div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Signature Section -->
    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line"></div>
            <div>Executive Signature</div>
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
        <p>Performance Management Module - Executive Appraisal Report</p>
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