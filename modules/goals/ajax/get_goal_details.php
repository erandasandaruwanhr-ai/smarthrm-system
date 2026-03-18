<?php
require_once '../../../config/config.php';
require_once '../../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

// Check if user has access to Goal Setting module
if (!isSuperAdmin() && !hasModulePermission($db, 'goals.view')) {
    echo '<div class="alert alert-danger">Access denied</div>';
    exit;
}

if (!isset($_POST['record_id']) || !is_numeric($_POST['record_id'])) {
    echo '<div class="alert alert-danger">Invalid record ID</div>';
    exit;
}

$record_id = $_POST['record_id'];

try {
    // Get employee goal record with employee details
    $goal_record = $db->fetch("
        SELECT
            r.*,
            e.name as employee_name,
            e.designation,
            e.department,
            COALESCE(l.location_name, 'Not Set') as employee_location,
            m.name as manager_name
        FROM executive_goal_records r
        JOIN employees e ON r.employee_epf = e.epf_number
        LEFT JOIN locations l ON e.location_id = l.id
        JOIN employees m ON r.manager_epf = m.epf_number
        WHERE r.id = ?
    ", [$record_id]);

    if (!$goal_record) {
        echo '<div class="alert alert-warning">Goal record not found</div>';
        exit;
    }

    // Get goal details
    $goal_details = $db->fetchAll("
        SELECT * FROM goal_details
        WHERE record_id = ?
        ORDER BY goal_section, goal_subsection
    ", [$record_id]);

    function getProgressBadge($progress) {
        switch ($progress) {
            case 'YS': return '<span class="badge bg-warning">Yet to Start</span>';
            case 'IP': return '<span class="badge bg-info">In Progress</span>';
            case 'C': return '<span class="badge bg-success">Completed</span>';
            default: return '<span class="badge bg-secondary">Not Set</span>';
        }
    }

    function getRatingBadge($rating) {
        if (!$rating) return '<span class="badge bg-secondary">Not Set</span>';

        switch (strtoupper($rating)) {
            case 'A': return '<span class="badge bg-success">A - Excellent</span>';
            case 'B': return '<span class="badge bg-primary">B - Good</span>';
            case 'C': return '<span class="badge bg-warning text-dark">C - Satisfactory</span>';
            case 'D': return '<span class="badge bg-orange text-white">D - Needs Improvement</span>';
            case 'E': return '<span class="badge bg-danger">E - Unsatisfactory</span>';
            default: return '<span class="badge bg-secondary">' . htmlspecialchars($rating) . '</span>';
        }
    }

    function calculateServiceYears($joining_date) {
        if (empty($joining_date)) {
            return 'Not Available';
        }

        try {
            $start = new DateTime($joining_date);
            $end = new DateTime();
            $interval = $start->diff($end);

            if ($interval->m > 0) {
                return $interval->y . ' years, ' . $interval->m . ' months';
            } else {
                return $interval->y . ' years';
            }
        } catch (Exception $e) {
            return 'Invalid Date';
        }
    }

    // Display the goal details
    ?>

    <!-- Employee Information -->
    <div class="card mb-3">
        <div class="card-header bg-primary text-white">
            <h6 class="mb-0"><i class="fas fa-user me-2"></i>Employee Information</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-1"><strong>EPF No:</strong> <?= htmlspecialchars($goal_record['employee_epf']) ?></p>
                    <p class="mb-1"><strong>Name:</strong> <?= htmlspecialchars($goal_record['employee_name']) ?></p>
                    <p class="mb-1"><strong>Designation:</strong> <?= htmlspecialchars($goal_record['designation']) ?></p>
                    <p class="mb-0"><strong>Department:</strong> <?= htmlspecialchars($goal_record['department']) ?></p>
                </div>
                <div class="col-md-6">
                    <p class="mb-1"><strong>Location:</strong> <?= htmlspecialchars($goal_record['employee_location']) ?></p>
                    <p class="mb-1"><strong>Joining Date:</strong> <?= !empty($goal_record['joining_date']) ? date('Y-m-d', strtotime($goal_record['joining_date'])) : 'Not Available' ?></p>
                    <p class="mb-1"><strong>Service Years:</strong> <?= !empty($goal_record['joining_date']) ? calculateServiceYears($goal_record['joining_date']) : 'Not Available' ?></p>
                    <p class="mb-0"><strong>Manager:</strong> <?= htmlspecialchars($goal_record['manager_name']) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Goal Details -->
    <?php if (!empty($goal_details)): ?>
        <?php
        // Find the COMPLETE_FORM record
        $complete_form_record = null;
        foreach ($goal_details as $goal) {
            if ($goal['goal_subsection'] == 'COMPLETE_FORM') {
                $complete_form_record = $goal;
                break;
            }
        }
        ?>

        <?php if ($complete_form_record): ?>
        <div class="card mb-3">
            <div class="card-header bg-success text-white">
                <h6 class="mb-0"><i class="fas fa-tasks me-2"></i>Goal Details</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 10%;">Goal S/N</th>
                                <th style="width: 20%;">Main Goals</th>
                                <th style="width: 30%;">Activities</th>
                                <th style="width: 20%;">Measurement Criteria</th>
                                <th style="width: 10%;">Weightage</th>
                                <th style="width: 10%;">Mid-Year Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Display sections 1-6 from the COMPLETE_FORM row
                            for ($i = 1; $i <= 6; $i++) {
                                $section_main_goals = $complete_form_record["section_{$i}_main_goals"] ?? '';
                                $section_weightage = $complete_form_record["section_{$i}_weightage"] ?? 0;

                                // Only show sections that have content
                                if (!empty($section_main_goals)) {
                                    // Add section header row first
                            ?>
                            <tr>
                                <td class="text-center bg-light"><strong>12.3.3.<?= $i ?></strong></td>
                                <td colspan="5" class="bg-light text-center">
                                    <em>Main Goal Section <?= $i ?></em>
                                </td>
                            </tr>
                            <?php
                                    // Count how many activities exist for this section
                                    $activity_count = 0;
                                    for ($j = 1; $j <= 6; $j++) {
                                        $activity = $complete_form_record["activities_{$i}_{$j}"] ?? '';
                                        if (!empty($activity)) {
                                            $activity_count++;
                                        }
                                    }

                                    // Display each activity as a separate row with merged Goal S/N, Main Goals, and Weightage
                                    $row_number = 0;
                                    for ($j = 1; $j <= 6; $j++) {
                                        $activity = $complete_form_record["activities_{$i}_{$j}"] ?? '';
                                        $criterion = $complete_form_record["measurement_criteria_{$i}_{$j}"] ?? '';
                                        $sub_mid_year_progress = $complete_form_record["activities_{$i}_{$j}_mid_year_progress"] ?? '';

                                        // Only show activities that have content
                                        if (!empty($activity)) {
                                            $row_number++;
                            ?>
                            <tr>
                                <?php if ($row_number == 1): ?>
                                <td rowspan="<?= $activity_count ?>" class="align-middle text-center"><small><strong>12.3.3.<?= $i ?></strong></small></td>
                                <td rowspan="<?= $activity_count ?>" class="align-middle"><small><?= htmlspecialchars($section_main_goals) ?></small></td>
                                <?php endif; ?>
                                <td><small><?= htmlspecialchars($activity) ?></small></td>
                                <td><small><?= htmlspecialchars($criterion) ?></small></td>
                                <?php if ($row_number == 1): ?>
                                <td rowspan="<?= $activity_count ?>" class="align-middle text-center"><?= number_format($section_weightage, 1) ?>%</td>
                                <?php endif; ?>
                                <td class="text-center"><?= getProgressBadge($sub_mid_year_progress) ?></td>
                            </tr>
                            <?php
                                        }
                                    }
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Progress Summary -->
        <div class="card mb-3">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Progress Summary</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <h6>Form Status</h6>
                        <p class="mb-0">
                            <strong>Saved:</strong>
                            <span class="badge bg-<?= $goal_record['form_saved'] == 'Y' ? 'success' : 'secondary' ?>">
                                <?= $goal_record['form_saved'] == 'Y' ? 'Yes' : 'No' ?>
                            </span>
                        </p>
                        <?php if ($goal_record['created_date']): ?>
                            <small class="text-muted">Created: <?= date('M d, Y H:i', strtotime($goal_record['created_date'])) ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <h6>Mid-Year Review</h6>
                        <p class="mb-0">
                            <strong>Completed:</strong>
                            <span class="badge bg-<?= $goal_record['mid_year_progress'] == 'Y' ? 'success' : 'secondary' ?>">
                                <?= $goal_record['mid_year_progress'] == 'Y' ? 'Yes' : 'No' ?>
                            </span>
                        </p>
                        <?php if ($goal_record['mid_year_status']): ?>
                            <small class="text-muted">Status: <?= htmlspecialchars($goal_record['mid_year_status']) ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <h6>Final Evaluation</h6>
                        <p class="mb-0">
                            <strong>Completed:</strong>
                            <span class="badge bg-<?= $goal_record['final_evaluation'] == 'Y' ? 'success' : 'secondary' ?>">
                                <?= $goal_record['final_evaluation'] == 'Y' ? 'Yes' : 'No' ?>
                            </span>
                        </p>
                        <?php if ($goal_record['final_performance_grade']): ?>
                            <?= getRatingBadge($goal_record['final_performance_grade']) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Agreements -->
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h6 class="mb-0"><i class="fas fa-handshake me-2"></i>Agreements</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Employee Agreement</h6>
                        <?php if ($goal_record['employee_agreement_date']): ?>
                            <p class="mb-0 text-success">
                                <i class="fas fa-check me-1"></i>
                                Agreed on <?= date('M d, Y H:i', strtotime($goal_record['employee_agreement_date'])) ?>
                            </p>
                        <?php else: ?>
                            <p class="mb-0 text-muted">
                                <i class="fas fa-times me-1"></i>
                                Not yet agreed
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <h6>Manager Agreement</h6>
                        <?php if ($goal_record['manager_agreement_date']): ?>
                            <p class="mb-0 text-success">
                                <i class="fas fa-check me-1"></i>
                                Agreed on <?= date('M d, Y H:i', strtotime($goal_record['manager_agreement_date'])) ?>
                            </p>
                        <?php else: ?>
                            <p class="mb-0 text-muted">
                                <i class="fas fa-times me-1"></i>
                                Not yet agreed
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            No goal details found for this record.
        </div>
    <?php endif; ?>

    <?php

} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error loading goal details: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>