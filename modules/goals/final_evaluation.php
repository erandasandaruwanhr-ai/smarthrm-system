<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

// Check if user has access to Final Evaluation
if (!isSuperAdmin() && !hasModulePermission($db, 'goals.final_evaluation')) {
    header('Location: ../../dashboard.php');
    exit;
}

$message = '';
$error = '';

// Handle form submission
if ($_POST) {
    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'save_final_evaluation') {
            $record_id = $_POST['record_id'];
            $final_performance_grade = $_POST['final_performance_grade'];

            // Get the record to check which employee this belongs to
            $target_record = $db->fetch("SELECT employee_epf FROM executive_goal_records WHERE id = ?", [$record_id]);

            // Prevent users/supervisors/admins from editing their own final evaluations
            $current_user_account_type = $user['account_type'] ?? 'user';
            $current_user_epf = $user['epf_number'];
            $editing_own_evaluation = ($target_record && $target_record['employee_epf'] === $current_user_epf);

            if ($current_user_account_type === 'user' ||
                ($editing_own_evaluation && in_array($current_user_account_type, ['supervisor', 'admin']))) {
                $role_description = $current_user_account_type === 'user' ? 'Users' : ucfirst($current_user_account_type) . 's';
                throw new Exception("Access denied. {$role_description} can only view their own final evaluations, not edit them. Please contact your manager if you need to make changes.");
            }

            // Update the main record
            $updates = [
                'final_evaluation' => 'Y',
                'final_performance_grade' => $final_performance_grade,
                'updated_date' => date('Y-m-d H:i:s')
            ];

            $db->update('executive_goal_records', $updates, 'id = ?', [$record_id]);

            // Update individual goal evaluations (both old format and new individual activity format)
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'achieved_percentage_') === 0) {
                    $goal_detail_id = str_replace('achieved_percentage_', '', $key);
                    if (is_numeric($goal_detail_id)) {
                        $updates = ['achieved_percentage' => (float)$value];

                        // Also update ratings if provided
                        if (isset($_POST['self_rating_' . $goal_detail_id])) {
                            $updates['self_rating'] = (float)$_POST['self_rating_' . $goal_detail_id];
                        }
                        if (isset($_POST['supervisor_rating_' . $goal_detail_id])) {
                            $updates['supervisor_rating'] = (float)$_POST['supervisor_rating_' . $goal_detail_id];
                        }
                        if (isset($_POST['final_rating_' . $goal_detail_id])) {
                            $updates['final_rating'] = (float)$_POST['final_rating_' . $goal_detail_id];
                        }

                        $updates['updated_date'] = date('Y-m-d H:i:s');
                        $db->update('goal_details', $updates, 'id = ?', [$goal_detail_id]);
                    }
                } elseif (strpos($key, 'activities_') === 0 && strpos($key, '_achieved_status') !== false) {
                    // Handle individual activity achieved status (activities_1_1_achieved_status, etc.)
                    $column_name = $key;
                    $update_value = !empty($value) && in_array($value, ['YS', 'IP', 'C']) ? $value : null;
                    $db->update('goal_details', [
                        $column_name => $update_value,
                        'updated_date' => date('Y-m-d H:i:s')
                    ], 'record_id = ? AND goal_subsection = ?', [$record_id, 'COMPLETE_FORM']);
                } elseif (strpos($key, 'section_') === 0 && (strpos($key, '_self_rating') !== false ||
                          strpos($key, '_supervisor_rating') !== false || strpos($key, '_final_rating') !== false)) {
                    // Handle section-level ratings (section_1_self_rating, etc.)
                    $column_name = $key;
                    $update_value = !empty($value) && in_array($value, ['A', 'B', 'C', 'D', 'E']) ? $value : null;
                    $db->update('goal_details', [
                        $column_name => $update_value,
                        'updated_date' => date('Y-m-d H:i:s')
                    ], 'record_id = ? AND goal_subsection = ?', [$record_id, 'COMPLETE_FORM']);
                }
            }

            // Handle final agreement
            if (isset($_POST['employee_final_agreement'])) {
                $db->update('executive_goal_records', [
                    'employee_agreement_date' => date('Y-m-d H:i:s')
                ], 'id = ?', [$record_id]);
            }

            if (isset($_POST['manager_final_agreement'])) {
                $db->update('executive_goal_records', [
                    'manager_agreement_date' => date('Y-m-d H:i:s')
                ], 'id = ?', [$record_id]);
            }

            $message = "Final performance evaluation saved successfully!";

        } elseif ($action === 'toggle_final_evaluation_status' && isSuperAdmin()) {
            $status_key = 'final_evaluation';
            $current_status = $db->fetch("SELECT is_active FROM goal_setting_status WHERE status_key = ?", [$status_key]);

            if ($current_status) {
                $new_status = $current_status['is_active'] == 'Y' ? 'N' : 'Y';
                $db->update('goal_setting_status', ['is_active' => $new_status], 'status_key = ?', [$status_key]);
                $message = "Final Performance Evaluation status " . ($new_status == 'Y' ? 'activated' : 'deactivated') . " successfully!";
            }
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Check if final evaluation is active
$final_evaluation_active = $db->fetch("SELECT is_active FROM goal_setting_status WHERE status_key = 'final_evaluation'")['is_active'] ?? 'N';

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

if ($selected_year && $final_evaluation_active == 'Y') {
    $period = $db->fetch("SELECT * FROM goal_setting_periods WHERE year = ?", [$selected_year]);

    if ($period) {
        // Get employees who have completed mid-year review - apply role-based access
        $current_user_epf = $user['epf_number'];
        $account_type = $user['account_type'] ?? 'user';

        if (isSuperAdmin()) {
            // SuperAdmin can see all employees who completed mid-year review
            $employees = $db->fetchAll("
                SELECT DISTINCT
                    r.employee_epf,
                    e.name as employee_name,
                    e.department,
                    r.id as record_id,
                    r.mid_year_progress,
                    r.final_evaluation
                FROM executive_goal_records r
                JOIN employees e ON r.employee_epf = e.epf_number
                WHERE r.period_id = ? AND r.mid_year_progress = 'Y'
                ORDER BY e.department, e.name
            ", [$period['id']]);
        } elseif ($account_type === 'manager') {
            // Managers can see themselves + their subordinates who completed mid-year review
            $employees = $db->fetchAll("
                SELECT DISTINCT
                    r.employee_epf,
                    e.name as employee_name,
                    e.department,
                    r.id as record_id,
                    r.mid_year_progress,
                    r.final_evaluation
                FROM executive_goal_records r
                JOIN employees e ON r.employee_epf = e.epf_number
                LEFT JOIN employees s1 ON e.reports_to = s1.epf_number
                LEFT JOIN employees s2 ON s1.reports_to = s2.epf_number
                WHERE r.period_id = ? AND r.mid_year_progress = 'Y'
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
            // Users, Supervisors, and Admins can only see their own final evaluations
            $employees = $db->fetchAll("
                SELECT DISTINCT
                    r.employee_epf,
                    e.name as employee_name,
                    e.department,
                    r.id as record_id,
                    r.mid_year_progress,
                    r.final_evaluation
                FROM executive_goal_records r
                JOIN employees e ON r.employee_epf = e.epf_number
                WHERE r.period_id = ? AND r.mid_year_progress = 'Y' AND e.epf_number = ?
                ORDER BY e.name
            ", [$period['id'], $current_user_epf]);

            // Auto-select themselves if they have completed mid-year review
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
                // Determine if user is viewing their own final evaluation
                $viewing_own_evaluation = ($selected_employee === $current_user_epf);

                // Read-only logic: Same as goal forms and mid-year review
                // - Users: Always read-only (can only view their own)
                // - Supervisors/Admins: Read-only when viewing their own
                // - Managers: Can edit their own and subordinates'
                // - SuperAdmin: Can edit all
                if ($account_type === 'user' ||
                    ($viewing_own_evaluation && in_array($account_type, ['supervisor', 'admin']))) {
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

function getProgressBadge($progress) {
    switch ($progress) {
        case 'YS': return '<span class="badge progress-badge-ys">Yet to Start</span>';
        case 'IP': return '<span class="badge progress-badge-ip">In Progress</span>';
        case 'C': return '<span class="badge progress-badge-c">Completed</span>';
        default: return '<span class="badge bg-secondary">Not Set</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Final Performance Evaluation</title>
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
            background: linear-gradient(135deg, var(--success-color), #1e7e34);
            color: white;
            border-radius: 10px;
            padding: 1.5rem;
        }

        .evaluation-card {
            background: linear-gradient(135deg, var(--danger-color), #c82333);
            color: white;
            border-radius: 10px;
            padding: 1.5rem;
        }

        .agreement-section {
            background: #f8f9fa;
            border: 2px solid var(--success-color);
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 2rem;
        }

        .progress-badge-ys { background-color: #dc3545; }
        .progress-badge-ip { background-color: #ffc107; color: #212529; }
        .progress-badge-c { background-color: #28a745; }

        .status-inactive {
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
        }

        .grade-radio {
            margin-bottom: 1rem;
            padding: 0.75rem;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .grade-radio:has(input:checked) {
            border-color: var(--success-color);
            background-color: rgba(40, 167, 69, 0.1);
        }

        .grade-a { border-left: 5px solid #28a745; }
        .grade-b { border-left: 5px solid #007bff; }
        .grade-c { border-left: 5px solid #ffc107; }
        .grade-d { border-left: 5px solid #dc3545; }

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

        .goal-section-header {
            background: linear-gradient(135deg, var(--info-color), #138496);
            color: white;
            padding: 0.75rem;
            border-radius: 8px;
            font-weight: bold;
            text-align: center;
        }

        .evaluation-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .evaluation-table th {
            background: var(--primary-color);
            color: white;
            text-align: center;
            font-weight: 600;
            padding: 1rem 0.5rem;
            border: none;
        }

        .evaluation-table td {
            padding: 0.75rem;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }

        .rating-input {
            width: 80px;
            text-align: center;
            border: 2px solid #dee2e6;
            border-radius: 5px;
            padding: 0.5rem;
        }

        .rating-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
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
                        <h1><i class="fas fa-award me-3"></i>Final Performance Evaluation</h1>
                        <p>Complete final performance evaluations and ratings</p>
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
                                    <h6>Final Performance Evaluation Status</h6>
                                    <p class="mb-0 text-muted">
                                        Currently: <strong><?= $final_evaluation_active == 'Y' ? 'ACTIVE' : 'CLOSED' ?></strong>
                                    </p>
                                </div>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_final_evaluation_status">
                                    <button type="submit" class="btn <?= $final_evaluation_active == 'Y' ? 'btn-danger' : 'btn-success' ?>">
                                        <i class="fas <?= $final_evaluation_active == 'Y' ? 'fa-stop' : 'fa-play' ?> me-2"></i>
                                        <?= $final_evaluation_active == 'Y' ? 'Deactivate' : 'Activate' ?> Final Evaluation
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Check if Final Evaluation is Active -->
            <?php if ($final_evaluation_active != 'Y'): ?>
            <div class="status-inactive">
                <i class="fas fa-stop-circle fa-3x mb-3"></i>
                <h3>Final Performance Evaluation is Currently Closed</h3>
                <p class="mb-0">Final performance evaluation is not currently active.
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
                                    <div class="form-text">Shows employees with completed mid-year reviews</div>
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
                                                <?php if ($emp['final_evaluation'] == 'Y'): ?>
                                                    ✓
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">✓ indicates final evaluation completed</div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Final Performance Evaluation Form -->
            <?php if ($goal_record): ?>
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


                <!-- Final Performance Evaluation Table -->
                <form method="POST" id="finalEvaluationForm">
                    <input type="hidden" name="action" value="save_final_evaluation">
                    <input type="hidden" name="record_id" value="<?= $goal_record['id'] ?>">

                    <div class="card mb-4">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0">
                                <i class="fas fa-clipboard-list me-2"></i>Final Performance Evaluation
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table evaluation-table mb-0">
                                    <thead class="table-dark">
                                        <tr>
                                            <th style="width: 8%;">Goal S/N #</th>
                                            <th style="width: 15%;">Main Goals</th>
                                            <th style="width: 15%;">Activities</th>
                                            <th style="width: 17%;">Measurement Criteria</th>
                                            <th style="width: 8%;">Weightage (%)</th>
                                            <th style="width: 8%;">Mid-Year Progress</th>
                                            <th style="width: 8%;">Achieved</th>
                                            <th style="width: 7%;">Self Rating</th>
                                            <th style="width: 7%;">Supervisor Rating</th>
                                            <th style="width: 7%;">Final Rating</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($goal_details)): ?>
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
                                                    // Display sections 1-6 from the COMPLETE_FORM row with section headers and merged columns
                                                    for ($i = 1; $i <= 6; $i++) {
                                                        $section_main_goals = $goal["section_{$i}_main_goals"] ?? '';
                                                        $section_weightage = $goal["section_{$i}_weightage"] ?? 0;

                                                        // Only show sections that have content
                                                        if (!empty($section_main_goals)) {
                                                            // Add section header row first
                                                    ?>
                                                    <tr>
                                                        <td class="goal-section-header"><strong>12.3.3.<?= $i ?></strong></td>
                                                        <td colspan="9" class="goal-section-header text-center">
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

                                                            // Display each activity as a separate row with merged Goal S/N, Main Goals, and Weightage
                                                            $row_number = 0;
                                                            for ($j = 1; $j <= 6; $j++) {
                                                                $activity = $goal["activities_{$i}_{$j}"] ?? '';
                                                                $criterion = $goal["measurement_criteria_{$i}_{$j}"] ?? '';
                                                                $sub_mid_year_progress = $goal["activities_{$i}_{$j}_mid_year_progress"] ?? '';
                                                                $achieved_status = $goal["activities_{$i}_{$j}_achieved_status"] ?? '';

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
                                                        <td class="text-center"><?= getProgressBadge($sub_mid_year_progress) ?></td>
                                                        <td>
                                                            <select class="form-select form-select-sm" name="activities_<?= $i ?>_<?= $j ?>_achieved_status">
                                                                <option value="">Select...</option>
                                                                <option value="YS" <?= $achieved_status == 'YS' ? 'selected' : '' ?>>YS - Yet to Start</option>
                                                                <option value="IP" <?= $achieved_status == 'IP' ? 'selected' : '' ?>>IP - In Progress</option>
                                                                <option value="C" <?= $achieved_status == 'C' ? 'selected' : '' ?>>C - Completed</option>
                                                            </select>
                                                        </td>
                                                        <?php if ($row_number == 1): ?>
                                                        <td rowspan="<?= $activity_count ?>" class="align-middle">
                                                            <?php $section_self_rating = $goal["section_{$i}_self_rating"] ?? ''; ?>
                                                            <select class="form-select form-select-sm" name="section_<?= $i ?>_self_rating">
                                                                <option value="">Select...</option>
                                                                <option value="A" <?= $section_self_rating == 'A' ? 'selected' : '' ?>>A - Excellent</option>
                                                                <option value="B" <?= $section_self_rating == 'B' ? 'selected' : '' ?>>B - Good</option>
                                                                <option value="C" <?= $section_self_rating == 'C' ? 'selected' : '' ?>>C - Satisfactory</option>
                                                                <option value="D" <?= $section_self_rating == 'D' ? 'selected' : '' ?>>D - Needs Improvement</option>
                                                                <option value="E" <?= $section_self_rating == 'E' ? 'selected' : '' ?>>E - Unsatisfactory</option>
                                                            </select>
                                                        </td>
                                                        <td rowspan="<?= $activity_count ?>" class="align-middle">
                                                            <?php $section_supervisor_rating = $goal["section_{$i}_supervisor_rating"] ?? ''; ?>
                                                            <select class="form-select form-select-sm" name="section_<?= $i ?>_supervisor_rating">
                                                                <option value="">Select...</option>
                                                                <option value="A" <?= $section_supervisor_rating == 'A' ? 'selected' : '' ?>>A - Excellent</option>
                                                                <option value="B" <?= $section_supervisor_rating == 'B' ? 'selected' : '' ?>>B - Good</option>
                                                                <option value="C" <?= $section_supervisor_rating == 'C' ? 'selected' : '' ?>>C - Satisfactory</option>
                                                                <option value="D" <?= $section_supervisor_rating == 'D' ? 'selected' : '' ?>>D - Needs Improvement</option>
                                                                <option value="E" <?= $section_supervisor_rating == 'E' ? 'selected' : '' ?>>E - Unsatisfactory</option>
                                                            </select>
                                                        </td>
                                                        <td rowspan="<?= $activity_count ?>" class="align-middle">
                                                            <?php $section_final_rating = $goal["section_{$i}_final_rating"] ?? ''; ?>
                                                            <select class="form-select form-select-sm" name="section_<?= $i ?>_final_rating">
                                                                <option value="">Select...</option>
                                                                <option value="A" <?= $section_final_rating == 'A' ? 'selected' : '' ?>>A - Excellent</option>
                                                                <option value="B" <?= $section_final_rating == 'B' ? 'selected' : '' ?>>B - Good</option>
                                                                <option value="C" <?= $section_final_rating == 'C' ? 'selected' : '' ?>>C - Satisfactory</option>
                                                                <option value="D" <?= $section_final_rating == 'D' ? 'selected' : '' ?>>D - Needs Improvement</option>
                                                                <option value="E" <?= $section_final_rating == 'E' ? 'selected' : '' ?>>E - Unsatisfactory</option>
                                                            </select>
                                                        </td>
                                                        <?php endif; ?>
                                                    </tr>
                                                    <?php
                                                                }
                                                            }
                                                        }
                                                    }
                                                    ?>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                        <tr>
                                            <td colspan="10" class="text-center">
                                                <div class="alert alert-warning mb-0">
                                                    <h6><i class="fas fa-exclamation-triangle me-2"></i>No Goal Details Available</h6>
                                                    <p class="mb-0">Please complete the <a href="goal_forms.php?year=<?= $selected_year ?>&employee=<?= $selected_employee ?>">Goal Form</a> first.</p>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Agreement on Goals -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-handshake me-2"></i>Agreement on Goals
                            </h5>
                            <small class="text-light">(At the commencement of the New Appraisal Year)</small>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" disabled
                                               <?= !empty($goal_record['employee_agreement_date']) ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold">
                                            Employee Agreement
                                        </label>
                                        <div class="form-text">I agree to the goals set for this appraisal period</div>
                                        <?php if (!empty($goal_record['employee_agreement_date'])): ?>
                                            <small class="text-success">
                                                <i class="fas fa-check me-1"></i>
                                                Agreed on <?= date('M d, Y H:i', strtotime($goal_record['employee_agreement_date'])) ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" disabled
                                               <?= !empty($goal_record['manager_agreement_date']) ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold">
                                            Manager Agreement
                                        </label>
                                        <div class="form-text">Goals set by: <?= htmlspecialchars($goal_record['manager_name'] ?? 'R. C. Gavesh Siriwansa') ?></div>
                                        <?php if (!empty($goal_record['manager_agreement_date'])): ?>
                                            <small class="text-success">
                                                <i class="fas fa-check me-1"></i>
                                                Agreed on <?= date('M d, Y H:i', strtotime($goal_record['manager_agreement_date'])) ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Mid-Year Progress Review Reference -->
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-line me-2"></i>Mid-Year Progress Review (Reference Only)
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-info mb-3">Overall Status</h6>
                                    <div class="mb-3">
                                        <?php
                                        $overall_status = $goal_record['mid_year_status'] ?? '';
                                        $status_class = '';
                                        switch($overall_status) {
                                            case 'Progressing Well':
                                                $status_class = 'text-success';
                                                break;
                                            case 'Need Improvements':
                                                $status_class = 'text-warning';
                                                break;
                                            case 'Below Expectations':
                                                $status_class = 'text-danger';
                                                break;
                                            default:
                                                $status_class = 'text-muted';
                                        }
                                        ?>
                                        <span class="badge fs-6 <?= $status_class ?> bg-light border border-secondary">
                                            <?= htmlspecialchars($overall_status ?: 'Not Set') ?>
                                        </span>
                                    </div>

                                    <h6 class="text-info mb-3">Comments</h6>
                                    <div class="border rounded p-3 bg-light">
                                        <small class="text-muted">
                                            <?= htmlspecialchars($goal_record['mid_year_comments'] ?: 'No comments provided') ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-info mb-3">Agreement Details</h6>
                                    <div class="mb-3">
                                        <strong>Employee Agreement:</strong><br>
                                        <small class="text-muted">
                                            <?php if (!empty($goal_record['employee_agreement_date'])): ?>
                                                <i class="fas fa-check text-success me-1"></i>
                                                Agreed on <?= date('M d, Y H:i', strtotime($goal_record['employee_agreement_date'])) ?>
                                            <?php else: ?>
                                                <i class="fas fa-times text-danger me-1"></i>
                                                Not yet agreed
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Manager Agreement:</strong><br>
                                        <small class="text-muted">
                                            <?php if (!empty($goal_record['manager_agreement_date'])): ?>
                                                <i class="fas fa-check text-success me-1"></i>
                                                Agreed on <?= date('M d, Y H:i', strtotime($goal_record['manager_agreement_date'])) ?>
                                            <?php else: ?>
                                                <i class="fas fa-times text-danger me-1"></i>
                                                Not yet agreed
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Overall Grade Selection -->
                    <div class="evaluation-card mb-4">
                        <h5 class="mb-3"><i class="fas fa-star me-2"></i>Overall Final Performance Grade</h5>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="grade-radio grade-a">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="final_performance_grade" id="grade_a" value="A"
                                               <?= ($goal_record['final_performance_grade'] ?? '') == 'A' ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold" for="grade_a">
                                            Grade A - Excellent
                                        </label>
                                        <small class="text-light d-block">Outstanding performance exceeding all expectations</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="grade-radio grade-b">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="final_performance_grade" id="grade_b" value="B"
                                               <?= ($goal_record['final_performance_grade'] ?? '') == 'B' ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold" for="grade_b">
                                            Grade B - Good
                                        </label>
                                        <small class="text-light d-block">Consistently meets and often exceeds expectations</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="grade-radio grade-c">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="final_performance_grade" id="grade_c" value="C"
                                               <?= ($goal_record['final_performance_grade'] ?? '') == 'C' ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold text-dark" for="grade_c">
                                            Grade C - Satisfactory
                                        </label>
                                        <small class="text-dark d-block">Meets most expectations with room for improvement</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="grade-radio grade-d">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="final_performance_grade" id="grade_d" value="D"
                                               <?= ($goal_record['final_performance_grade'] ?? '') == 'D' ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold" for="grade_d">
                                            Grade D - Needs Improvement
                                        </label>
                                        <small class="text-light d-block">Below expectations requiring immediate attention</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Agreement Section -->
                    <div class="agreement-section">
                        <h5 class="text-success mb-3"><i class="fas fa-handshake me-2"></i>Final Agreement</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="employee_final_agreement" id="employee_agreement"
                                           <?= !empty($goal_record['employee_agreement_date']) ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="employee_agreement">
                                        Employee Agreement
                                    </label>
                                    <div class="form-text">I agree with the final performance evaluation</div>
                                    <?php if (!empty($goal_record['employee_agreement_date'])): ?>
                                        <small class="text-success">
                                            <i class="fas fa-check me-1"></i>
                                            Agreed on <?= date('M d, Y H:i', strtotime($goal_record['employee_agreement_date'])) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="manager_final_agreement" id="manager_agreement"
                                           <?= !empty($goal_record['manager_agreement_date']) ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="manager_agreement">
                                        Manager Agreement
                                    </label>
                                    <div class="form-text">I approve the final performance evaluation</div>
                                    <?php if (!empty($goal_record['manager_agreement_date'])): ?>
                                        <small class="text-success">
                                            <i class="fas fa-check me-1"></i>
                                            Agreed on <?= date('M d, Y H:i', strtotime($goal_record['manager_agreement_date'])) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="d-flex justify-content-center mt-4">
                        <button type="submit" class="btn btn-success btn-lg px-5">
                            <i class="fas fa-save me-2"></i>Save Final Performance Evaluation
                        </button>
                    </div>
                </form>
            <?php endif; ?>

            <?php endif; ?>

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
                            <a href="reports_analytics.php" class="btn btn-primary">
                                <i class="fas fa-chart-pie me-2"></i>Reports & Analytics
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

        // Validate that final performance grade is selected before submission
        document.querySelector('#finalEvaluationForm')?.addEventListener('submit', function(e) {
            const radios = document.querySelectorAll('input[name="final_performance_grade"]');
            let checked = false;

            radios.forEach(radio => {
                if (radio.checked) checked = true;
            });

            if (!checked) {
                e.preventDefault();
                alert('Please select a final performance grade before saving.');
                return false;
            }
        });

        // Auto-calculate averages or final ratings (optional enhancement)
        document.querySelectorAll('input[type="number"]').forEach(input => {
            input.addEventListener('input', function() {
                // Auto-calculate final rating based on achieved percentage
                if (this.name.includes('achieved_percentage_')) {
                    const goalId = this.name.split('_')[2];
                    const percentage = parseFloat(this.value) || 0;

                    // Simple auto-rating based on percentage
                    let autoRating = 0;
                    if (percentage >= 90) autoRating = 5;
                    else if (percentage >= 80) autoRating = 4;
                    else if (percentage >= 70) autoRating = 3;
                    else if (percentage >= 60) autoRating = 2;
                    else if (percentage >= 50) autoRating = 1;

                    const finalRatingInput = document.querySelector(`input[name="final_rating_${goalId}"]`);
                    if (finalRatingInput && !finalRatingInput.value) {
                        finalRatingInput.value = autoRating.toFixed(1);
                    }
                }
            });
        });

        // Highlight grade selection
        document.querySelectorAll('input[name="final_performance_grade"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.grade-radio').forEach(div => {
                    div.classList.remove('border-success');
                });

                if (this.checked) {
                    this.closest('.grade-radio').classList.add('border-success');
                }
            });
        });

        // Set initial grade highlighting
        const checkedGrade = document.querySelector('input[name="final_performance_grade"]:checked');
        if (checkedGrade) {
            checkedGrade.closest('.grade-radio').classList.add('border-success');
        }
    </script>
</body>
</html>