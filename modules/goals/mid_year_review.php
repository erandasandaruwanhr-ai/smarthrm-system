<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

// Check if user has access to Mid Year Review
if (!isSuperAdmin() && !hasModulePermission($db, 'goals.mid_year_review')) {
    header('Location: ../../dashboard.php');
    exit;
}

$message = '';
$error = '';

// Handle form submission
if ($_POST) {
    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'save_mid_year_progress') {
            $record_id = $_POST['record_id'];
            $mid_year_status = $_POST['mid_year_status'];
            $mid_year_comments = $_POST['mid_year_comments'] ?? '';

            // Get the record to check which employee this belongs to
            $target_record = $db->fetch("SELECT employee_epf FROM executive_goal_records WHERE id = ?", [$record_id]);

            // Prevent users/supervisors/admins from editing their own mid-year reviews
            $current_user_account_type = $user['account_type'] ?? 'user';
            $current_user_epf = $user['epf_number'];
            $editing_own_review = ($target_record && $target_record['employee_epf'] === $current_user_epf);

            if ($current_user_account_type === 'user' ||
                ($editing_own_review && in_array($current_user_account_type, ['supervisor', 'admin']))) {
                $role_description = $current_user_account_type === 'user' ? 'Users' : ucfirst($current_user_account_type) . 's';
                throw new Exception("Access denied. {$role_description} can only view their own mid-year reviews, not edit them. Please contact your manager if you need to make changes.");
            }

            // Update the main record
            $updates = [
                'mid_year_progress' => 'Y',
                'mid_year_status' => $mid_year_status,
                'mid_year_comments' => $mid_year_comments,
                'updated_date' => date('Y-m-d H:i:s')
            ];

            $db->update('executive_goal_records', $updates, 'id = ?', [$record_id]);

            // Update individual goal progress and section progress
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'mid_year_progress_') === 0) {
                    $goal_detail_id = str_replace('mid_year_progress_', '', $key);
                    if (is_numeric($goal_detail_id)) {
                        $db->update('goal_details', [
                            'mid_year_progress' => $value,
                            'updated_date' => date('Y-m-d H:i:s')
                        ], 'id = ?', [$goal_detail_id]);
                    }
                } elseif (strpos($key, 'section_') === 0 && strpos($key, '_mid_year_progress') !== false) {
                    // Handle section-based progress updates (section_1_mid_year_progress, etc.)
                    $column_name = $key;
                    $db->update('goal_details', [
                        $column_name => $value,
                        'updated_date' => date('Y-m-d H:i:s')
                    ], 'record_id = ? AND goal_subsection = ?', [$record_id, 'COMPLETE_FORM']);
                } elseif (strpos($key, 'activities_') === 0 && strpos($key, '_mid_year_progress') !== false) {
                    // Handle individual activity progress updates (activities_1_1_mid_year_progress, etc.)
                    $column_name = $key;
                    // Only update if it's a valid ENUM value (not empty)
                    $update_value = !empty($value) && in_array($value, ['YS', 'IP', 'C']) ? $value : null;
                    $db->update('goal_details', [
                        $column_name => $update_value,
                        'updated_date' => date('Y-m-d H:i:s')
                    ], 'record_id = ? AND goal_subsection = ?', [$record_id, 'COMPLETE_FORM']);
                }
            }

            // Handle employee agreement
            if (isset($_POST['employee_mid_year_agreement'])) {
                $db->update('executive_goal_records', [
                    'employee_agreement_date' => date('Y-m-d H:i:s')
                ], 'id = ?', [$record_id]);
            }

            // Handle manager agreement
            if (isset($_POST['manager_mid_year_agreement'])) {
                $db->update('executive_goal_records', [
                    'manager_agreement_date' => date('Y-m-d H:i:s')
                ], 'id = ?', [$record_id]);
            }

            $message = "Mid-year progress saved successfully!";

        } elseif ($action === 'toggle_mid_year_status' && isSuperAdmin()) {
            $status_key = 'mid_year_review';
            $current_status = $db->fetch("SELECT is_active FROM goal_setting_status WHERE status_key = ?", [$status_key]);

            if ($current_status) {
                $new_status = $current_status['is_active'] == 'Y' ? 'N' : 'Y';
                $db->update('goal_setting_status', ['is_active' => $new_status], 'status_key = ?', [$status_key]);
                $message = "Mid-Year Review status " . ($new_status == 'Y' ? 'activated' : 'deactivated') . " successfully!";
            }
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Check if mid-year review is active
$mid_year_active = $db->fetch("SELECT is_active FROM goal_setting_status WHERE status_key = 'mid_year_review'")['is_active'] ?? 'N';

// Get available years
$years = $db->fetchAll("
    SELECT year, status FROM goal_setting_periods
    WHERE status IN ('active', 'closed')
    ORDER BY year DESC
");

// Get selected year data
$selected_year = $_GET['year'] ?? '';
$selected_employee = $_GET['employee'] ?? '';

$period = null;
$employees = [];
$employee_data = null;
$goal_record = null;
$goal_details = [];

if ($selected_year && $mid_year_active == 'Y') {
    $period = $db->fetch("SELECT * FROM goal_setting_periods WHERE year = ?", [$selected_year]);

    if ($period) {
        // Get employees who have saved goal forms - apply role-based access
        $current_user_epf = $user['epf_number'];
        $account_type = $user['account_type'] ?? 'user';

        if (isSuperAdmin()) {
            // SuperAdmin can see all employees with saved goals
            $employees = $db->fetchAll("
                SELECT DISTINCT
                    r.employee_epf,
                    e.name as employee_name,
                    e.department,
                    r.id as record_id,
                    r.form_saved,
                    r.mid_year_progress
                FROM executive_goal_records r
                JOIN employees e ON r.employee_epf = e.epf_number
                WHERE r.period_id = ? AND r.form_saved = 'Y'
                ORDER BY e.department, e.name
            ", [$period['id']]);
        } elseif ($account_type === 'manager') {
            // Managers can see themselves + their subordinates with saved goals
            $employees = $db->fetchAll("
                SELECT DISTINCT
                    r.employee_epf,
                    e.name as employee_name,
                    e.department,
                    r.id as record_id,
                    r.form_saved,
                    r.mid_year_progress
                FROM executive_goal_records r
                JOIN employees e ON r.employee_epf = e.epf_number
                LEFT JOIN employees s1 ON e.reports_to = s1.epf_number
                LEFT JOIN employees s2 ON s1.reports_to = s2.epf_number
                WHERE r.period_id = ? AND r.form_saved = 'Y'
                AND (
                    -- Include themselves
                    e.epf_number = ?
                    OR
                    -- Direct reports
                    e.reports_to = ?
                    OR
                    -- Skip-level reports where direct supervisor is not a manager
                    (s1.employment_level_id NOT IN (2, 3) AND s2.epf_number = ?)
                )
                ORDER BY CASE WHEN e.epf_number = ? THEN 0 ELSE 1 END, e.department, e.name
            ", [$period['id'], $current_user_epf, $current_user_epf, $current_user_epf, $current_user_epf]);
        } else {
            // Users, Supervisors, and Admins can only see their own mid-year reviews
            $employees = $db->fetchAll("
                SELECT DISTINCT
                    r.employee_epf,
                    e.name as employee_name,
                    e.department,
                    r.id as record_id,
                    r.form_saved,
                    r.mid_year_progress
                FROM executive_goal_records r
                JOIN employees e ON r.employee_epf = e.epf_number
                WHERE r.period_id = ? AND r.form_saved = 'Y' AND e.epf_number = ?
                ORDER BY e.name
            ", [$period['id'], $current_user_epf]);

            // Auto-select themselves if they have a saved goal record
            if (!empty($employees) && !$selected_employee) {
                $selected_employee = $current_user_epf;
            }
        }

        // If employee selected, get their data and goals
        if ($selected_employee) {
            $goal_record = $db->fetch("
                SELECT r.*, e.name as employee_name, e.designation, e.department,
                       l.location_name as location, e.joined_date as joining_date, m.name as manager_name
                FROM executive_goal_records r
                JOIN employees e ON r.employee_epf = e.epf_number
                JOIN employees m ON r.manager_epf = m.epf_number
                LEFT JOIN locations l ON e.location_id = l.id
                WHERE r.employee_epf = ? AND r.period_id = ?
            ", [$selected_employee, $period['id']]);

            // Determine if form should be read-only
            $is_read_only = false;
            if ($goal_record) {
                // Determine if user is viewing their own mid-year review
                $viewing_own_review = ($selected_employee === $current_user_epf);

                // Read-only logic: Same as goal forms
                // - Users: Always read-only (can only view their own)
                // - Supervisors/Admins: Read-only when viewing their own
                // - Managers: Can edit their own and subordinates'
                // - SuperAdmin: Can edit all
                if ($account_type === 'user' ||
                    ($viewing_own_review && in_array($account_type, ['supervisor', 'admin']))) {
                    $is_read_only = true;
                }
            }

            if ($goal_record) {
                $goal_details = $db->fetchAll("
                    SELECT gd.*, gt.main_goals as template_main_goals,
                           gt.activities as template_activities,
                           gt.measurement_criteria as template_measurement_criteria
                    FROM goal_details gd
                    LEFT JOIN goal_setting_templates gt ON gt.goal_subsection = gd.goal_subsection
                                                        AND gt.period_id = ?
                    WHERE gd.record_id = ?
                    ORDER BY gd.goal_section, gd.goal_subsection
                ", [$period['id'], $goal_record['id']]);

                // Debug: Log what we found
                error_log("Goal details count: " . count($goal_details));
                foreach ($goal_details as $gd) {
                    error_log("Goal: " . $gd['goal_subsection'] . " - Main Goals: " . ($gd['main_goals'] ?? 'null'));
                }
            }
        }
    }
}

function calculateServiceYears($joining_date) {
    $join = new DateTime($joining_date);
    $now = new DateTime();
    $interval = $join->diff($now);

    $years = $interval->y;
    $months = $interval->m;

    if ($months > 0) {
        return $years . ' years, ' . $months . ' months';
    } else {
        return $years . ' years';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Mid-Year Progress Review</title>
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

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .employee-info-card {
            background: linear-gradient(135deg, var(--warning-color), #e0a800);
            color: white;
            border-radius: 10px;
            padding: 1.5rem;
        }

        .progress-status-card {
            background: linear-gradient(135deg, var(--info-color), #138496);
            color: white;
            border-radius: 10px;
            padding: 1.5rem;
        }

        .agreement-section {
            background: #f8f9fa;
            border: 2px solid var(--warning-color);
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 2rem;
        }

        .progress-badge-ys { background-color: #dc3545; }
        .progress-badge-ip { background-color: #ffc107; color: #212529; }
        .progress-badge-c { background-color: #28a745; }

        .goal-section-header {
            background: linear-gradient(135deg, var(--info-color), #138496);
            color: white;
            padding: 0.75rem;
            border-radius: 8px;
            font-weight: bold;
            text-align: center;
        }

        .status-inactive {
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
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

        /* Read-only form styling */
        .form-control[readonly],
        .form-control[disabled],
        .form-check-input[disabled] {
            background-color: #f8f9fa !important;
            border-color: #dee2e6 !important;
            opacity: 0.8;
            cursor: not-allowed;
        }

        .form-control[readonly]:focus {
            box-shadow: none !important;
            border-color: #dee2e6 !important;
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
                        <h1><i class="fas fa-chart-bar me-3"></i>Mid-Year Progress Review</h1>
                        <p>Update and review mid-year progress status (YS / IP / C)</p>
                    </div>
                    <div class="logo-container">
                        <img src="../../jiffy-logo.svg" alt="Jiffy Logo" class="page-header-logo">
                    </div>
                </div>
            </div>

            <!-- Success/Error Messages -->
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

            <!-- System Status Control (Superadmin only) -->
            <?php if (isSuperAdmin()): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="fas fa-sliders-h me-2"></i>System Control</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6>Mid-Year Review Status</h6>
                                    <p class="mb-0 text-muted">
                                        Currently: <strong><?= $mid_year_active == 'Y' ? 'ACTIVE' : 'CLOSED' ?></strong>
                                    </p>
                                </div>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_mid_year_status">
                                    <button type="submit" class="btn <?= $mid_year_active == 'Y' ? 'btn-danger' : 'btn-success' ?>">
                                        <i class="fas <?= $mid_year_active == 'Y' ? 'fa-stop' : 'fa-play' ?> me-2"></i>
                                        <?= $mid_year_active == 'Y' ? 'Deactivate' : 'Activate' ?> Mid-Year Review
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Check if Mid-Year Review is Active -->
            <?php if ($mid_year_active != 'Y'): ?>
            <div class="status-inactive">
                <i class="fas fa-stop-circle fa-3x mb-3"></i>
                <h3>Mid-Year Review is Currently Closed</h3>
                <p class="mb-0">Mid-year progress review is not currently active.
                <?php if (isSuperAdmin()): ?>
                    Use the system control above to activate it.
                <?php else: ?>
                    Contact your system administrator to activate it.
                <?php endif; ?>
                </p>
            </div>
            <?php else: ?>

            <!-- Year and Employee Selection -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-calendar me-2"></i>Select Year</h5>
                        </div>
                        <div class="card-body">
                            <form method="GET">
                                <div class="mb-3">
                                    <select name="year" class="form-select" onchange="this.form.submit()">
                                        <option value="">Choose Year...</option>
                                        <?php foreach ($years as $year_data): ?>
                                            <option value="<?= $year_data['year'] ?>" <?= $selected_year == $year_data['year'] ? 'selected' : '' ?>>
                                                <?= $year_data['year'] ?> (<?= ucfirst($year_data['status']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Shows employees with saved goal forms</div>
                                </div>
                                <?php if ($selected_employee): ?>
                                    <input type="hidden" name="employee" value="<?= $selected_employee ?>">
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>

                <?php if ($selected_year && !empty($employees)): ?>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-users me-2"></i>Select Employee</h5>
                        </div>
                        <div class="card-body">
                            <form method="GET">
                                <input type="hidden" name="year" value="<?= $selected_year ?>">
                                <div class="mb-3">
                                    <select name="employee" class="form-select" onchange="this.form.submit()">
                                        <option value="">Choose Employee...</option>
                                        <?php foreach ($employees as $emp): ?>
                                            <option value="<?= $emp['employee_epf'] ?>" <?= $selected_employee == $emp['employee_epf'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($emp['employee_epf']) ?> - <?= htmlspecialchars($emp['employee_name']) ?>
                                                <?php if ($emp['mid_year_progress'] == 'Y'): ?>
                                                    ✓
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">✓ indicates mid-year review completed</div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Mid-Year Progress Review Form -->
            <?php if ($goal_record): ?>
            <form method="POST"<?= $is_read_only ? ' onsubmit="return false;"' : '' ?>>
                <input type="hidden" name="action" value="save_mid_year_progress">
                <input type="hidden" name="record_id" value="<?= $goal_record['id'] ?>">

                <!-- Employee Information -->
                <div class="employee-info-card mb-4">
                    <div class="row">
                        <div class="col-md-6">
                            <h5><i class="fas fa-user me-2"></i>Employee Information</h5>
                            <p class="mb-2"><strong>EPF No:</strong> <?= htmlspecialchars($goal_record['employee_epf']) ?></p>
                            <p class="mb-2"><strong>Name:</strong> <?= htmlspecialchars($goal_record['employee_name']) ?></p>
                            <p class="mb-2"><strong>Designation:</strong> <?= htmlspecialchars($goal_record['designation']) ?></p>
                            <p class="mb-0"><strong>Department:</strong> <?= htmlspecialchars($goal_record['department']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-2"><strong>Location:</strong> <?= htmlspecialchars($goal_record['location']) ?></p>
                            <p class="mb-2"><strong>Joining Date:</strong> <?= date('Y-m-d', strtotime($goal_record['joining_date'])) ?></p>
                            <p class="mb-2"><strong>Service Years:</strong> <?= calculateServiceYears($goal_record['joining_date']) ?></p>
                            <p class="mb-0"><strong>Manager:</strong> <?= htmlspecialchars($goal_record['manager_name']) ?></p>
                        </div>
                    </div>
                </div>

                <!-- Goal Progress Review -->
                <div class="card mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">
                            <i class="fas fa-clipboard-check me-2"></i>Goal Progress Review - <?= $selected_year ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($goal_details)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-dark">
                                    <tr>
                                        <th style="width: 10%;">Goal S/N #</th>
                                        <th style="width: 20%;">Main Goals</th>
                                        <th style="width: 20%;">Activities</th>
                                        <th style="width: 25%;">Measurement Criteria</th>
                                        <th style="width: 10%;">Weightage (%)</th>
                                        <th style="width: 15%;">Mid-Year Progress</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $current_section = '';
                                    foreach ($goal_details as $goal):
                                        // Skip section headers but show COMPLETE_FORM and actual goal entries
                                        if (substr_count($goal['goal_subsection'], '.') == 2 && $goal['goal_subsection'] != 'COMPLETE_FORM') {
                                            $current_section = $goal['goal_section'];
                                            continue;
                                        }
                                    ?>
                                        <?php if ($goal['goal_subsection'] == 'COMPLETE_FORM'): ?>
                                            <?php
                                            // Display sections 1-6 from the COMPLETE_FORM row with merged Goal S/N and Main Goals columns
                                            for ($i = 1; $i <= 6; $i++) {
                                                $section_main_goals = $goal["section_{$i}_main_goals"] ?? '';
                                                $section_weightage = $goal["section_{$i}_weightage"] ?? 0;
                                                $section_progress = $goal["section_{$i}_mid_year_progress"] ?? '';

                                                // Only show sections that have content
                                                if (!empty($section_main_goals)) {
                                                    // Add section header row first
                                            ?>
                                            <tr>
                                                <td class="goal-section-header"><strong>12.3.3.<?= $i ?></strong></td>
                                                <td colspan="5" class="goal-section-header text-center">
                                                    <em>Main Goal Section <?= $i ?></em>
                                                </td>
                                            </tr>
                                            <?php
                                                    // Count how many activities exist for this section
                                                    $activity_count = 0;
                                                    for ($j = 1; $j <= 6; $j++) {
                                                        $activity = $goal["activities_{$i}_{$j}"] ?? '';
                                                        if (!empty($activity)) {
                                                            $activity_count++;
                                                        }
                                                    }

                                                    // Display each activity as a separate row with merged Goal S/N and Main Goals
                                                    $row_number = 0;
                                                    for ($j = 1; $j <= 6; $j++) {
                                                        $activity = $goal["activities_{$i}_{$j}"] ?? '';
                                                        $criterion = $goal["measurement_criteria_{$i}_{$j}"] ?? '';
                                                        $sub_progress = $goal["activities_{$i}_{$j}_mid_year_progress"] ?? '';

                                                        // Only show activities that have content
                                                        if (!empty($activity)) {
                                                            $row_number++;
                                            ?>
                                            <tr>
                                                <?php if ($row_number == 1): ?>
                                                <td rowspan="<?= $activity_count ?>" class="align-middle"><small><strong>12.3.3.<?= $i ?></strong></small></td>
                                                <td rowspan="<?= $activity_count ?>" class="align-middle"><small><?= htmlspecialchars($section_main_goals) ?></small></td>
                                                <?php endif; ?>
                                                <td><small><?= htmlspecialchars($activity) ?></small></td>
                                                <td><small><?= htmlspecialchars($criterion) ?></small></td>
                                                <?php if ($row_number == 1): ?>
                                                <td rowspan="<?= $activity_count ?>" class="align-middle text-center"><?= number_format($section_weightage, 1) ?>%</td>
                                                <?php endif; ?>
                                                <td>
                                                    <select class="form-select form-select-sm" name="activities_<?= $i ?>_<?= $j ?>_mid_year_progress">
                                                        <option value="">Select...</option>
                                                        <option value="YS" <?= $sub_progress == 'YS' ? 'selected' : '' ?>>YS - Yet to Start</option>
                                                        <option value="IP" <?= $sub_progress == 'IP' ? 'selected' : '' ?>>IP - In Progress</option>
                                                        <option value="C" <?= $sub_progress == 'C' ? 'selected' : '' ?>>C - Completed</option>
                                                    </select>
                                                </td>
                                            </tr>
                                            <?php
                                                        }
                                                    }
                                                }
                                            }
                                            ?>
                                        <?php else: ?>
                                        <tr>
                                            <td><small><strong><?= htmlspecialchars($goal['goal_subsection']) ?></strong></small></td>
                                            <td><small><?= htmlspecialchars($goal['main_goals'] ?: $goal['template_main_goals'] ?: '') ?></small></td>
                                            <td><small><?= htmlspecialchars($goal['activities'] ?: $goal['template_activities'] ?: '') ?></small></td>
                                            <td><small><?= htmlspecialchars($goal['measurement_criteria'] ?: $goal['template_measurement_criteria'] ?: '') ?></small></td>
                                            <td class="text-center"><?= number_format($goal['weightage'] ?? 0, 1) ?>%</td>
                                            <td>
                                                <select class="form-select form-select-sm" name="mid_year_progress_<?= $goal['id'] ?>">
                                                    <option value="">Select...</option>
                                                    <option value="YS" <?= $goal['mid_year_progress'] == 'YS' ? 'selected' : '' ?>>YS - Yet to Start</option>
                                                    <option value="IP" <?= $goal['mid_year_progress'] == 'IP' ? 'selected' : '' ?>>IP - In Progress</option>
                                                    <option value="C" <?= $goal['mid_year_progress'] == 'C' ? 'selected' : '' ?>>C - Completed</option>
                                                </select>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No goal details found for this employee. Please ensure their goal setting form has been saved.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Mid-Year Review Progress Section -->
                <div class="progress-status-card mb-4">
                    <h5><i class="fas fa-chart-line me-2"></i>Mid-Year Review Progress</h5>
                    <p class="mb-3"><em>Overall Progress Status (tick applicable):</em></p>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="mid_year_status"
                                       value="Progressing Well" id="progressWell"
                                       <?= $goal_record['mid_year_status'] == 'Progressing Well' ? 'checked' : '' ?>
                                       <?= $is_read_only ? 'disabled' : '' ?>>
                                <label class="form-check-label" for="progressWell">
                                    <strong>Progressing Well</strong>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="mid_year_status"
                                       value="Need Improvements" id="needImprovements"
                                       <?= $goal_record['mid_year_status'] == 'Need Improvements' ? 'checked' : '' ?>
                                       <?= $is_read_only ? 'disabled' : '' ?>>
                                <label class="form-check-label" for="needImprovements">
                                    <strong>Need Improvements</strong>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="mid_year_status"
                                       value="Below Expectations" id="belowExpectations"
                                       <?= $goal_record['mid_year_status'] == 'Below Expectations' ? 'checked' : '' ?>
                                       <?= $is_read_only ? 'disabled' : '' ?>>
                                <label class="form-check-label" for="belowExpectations">
                                    <strong>Below Expectations</strong>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label"><strong>Comments (Optional):</strong></label>
                        <textarea class="form-control" name="mid_year_comments" rows="3"
                                  placeholder="Additional comments about mid-year progress..."
                                  <?= $is_read_only ? 'readonly' : '' ?>><?= htmlspecialchars($goal_record['mid_year_comments'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Agreement Section -->
                <div class="agreement-section">
                    <h6><i class="fas fa-handshake me-2"></i>Mid-Year Review Agreement</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="employee_mid_year_agreement"
                                       id="employeeMidYearAgreement"
                                       <?= $goal_record['employee_agreement_date'] ? 'checked' : '' ?>
                                       <?= $is_read_only ? 'disabled' : '' ?>>
                                <label class="form-check-label" for="employeeMidYearAgreement">
                                    <strong>Employee Agreement</strong><br>
                                    <small class="text-muted">I acknowledge this mid-year review</small>
                                    <?php if ($goal_record['employee_agreement_date']): ?>
                                        <br><small class="text-success">Agreed on: <?= date('M d, Y H:i', strtotime($goal_record['employee_agreement_date'])) ?></small>
                                    <?php endif; ?>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="manager_mid_year_agreement"
                                       id="managerMidYearAgreement"
                                       <?= $goal_record['manager_agreement_date'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="managerMidYearAgreement">
                                    <strong>Manager Agreement</strong><br>
                                    <small class="text-muted">Mid-year review completed by manager</small>
                                    <?php if ($goal_record['manager_agreement_date']): ?>
                                        <br><small class="text-success">Agreed on: <?= date('M d, Y H:i', strtotime($goal_record['manager_agreement_date'])) ?></small>
                                    <?php endif; ?>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!$is_read_only): ?>
                <div class="mt-4 text-center">
                    <button type="submit" class="btn btn-warning btn-lg">
                        <i class="fas fa-save me-2"></i>Save Mid-Year Progress
                    </button>
                </div>
                <?php else: ?>
                <div class="mt-4 text-center">
                    <div class="alert alert-info">
                        <i class="fas fa-eye me-2"></i>
                        <strong>View Only Mode</strong> - You are viewing your mid-year review in read-only mode.
                    </div>
                </div>
                <?php endif; ?>
            </form>
            <?php endif; ?>

            <?php endif; // End Mid-Year Active Check ?>

            <!-- Navigation -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Goal Setting
                        </a>
                        <div>
                            <a href="kpi_tracker.php" class="btn btn-info me-2">
                                <i class="fas fa-tasks me-2"></i>KPI Tracker
                            </a>
                            <a href="final_evaluation.php" class="btn btn-success">
                                <i class="fas fa-award me-2"></i>Final Evaluation
                            </a>
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

        // Add read-only protection
        <?php if ($is_read_only): ?>
        // Prevent form submission for read-only users
        document.querySelector('form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            alert('❌ Form submission blocked!\n\nYou are viewing your mid-year review in read-only mode. Only managers can edit mid-year reviews for themselves and their subordinates.\n\nIf you need changes to your review, please contact your manager.');
            return false;
        });
        <?php else: ?>
        // Validate that at least one progress status is selected before submission
        document.querySelector('form')?.addEventListener('submit', function(e) {
            const radios = document.querySelectorAll('input[name="mid_year_status"]');
            let checked = false;

            radios.forEach(radio => {
                if (radio.checked) checked = true;
            });

            if (!checked) {
                e.preventDefault();
                alert('Please select an overall progress status before saving.');
                return false;
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>