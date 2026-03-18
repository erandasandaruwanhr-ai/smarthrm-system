<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

// Check if user has access to Goal Forms
if (!isSuperAdmin() && !hasModulePermission($db, 'goals.goal_forms')) {
    header('Location: ../../dashboard.php');
    exit;
}

$message = '';
$error = '';

// Handle form submission
if ($_POST) {
    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'save_goals') {
            $employee_epf = $_POST['employee_epf'];
            $period_id = $_POST['period_id'];
            $manager_epf = $user['epf_number'];

            // Prevent users and supervisors/admins from editing their own goals
            $current_user_account_type = $user['account_type'] ?? 'user';
            $current_user_epf = $user['epf_number'];
            $viewing_own_goals = ($employee_epf === $current_user_epf);

            if ($current_user_account_type === 'user' ||
                ($viewing_own_goals && in_array($current_user_account_type, ['supervisor', 'admin']))) {
                $role_description = $current_user_account_type === 'user' ? 'Users' : ucfirst($current_user_account_type) . 's';
                throw new Exception("Access denied. {$role_description} can only view their own goals, not edit them. Please contact your manager if you need to make changes.");
            }

            // Verify manager has authority over this employee
            $employee = $db->fetch("
                SELECT e.*, l.location_name, s1.epf_number as direct_supervisor, s1.name as direct_supervisor_name,
                       s2.epf_number as upper_supervisor, s2.name as upper_supervisor_name, e.employment_level_id
                FROM employees e
                LEFT JOIN locations l ON e.location_id = l.id
                LEFT JOIN employment_levels el ON e.employment_level_id = el.id
                LEFT JOIN employees s1 ON e.reports_to = s1.epf_number
                LEFT JOIN employees s2 ON s1.reports_to = s2.epf_number
                WHERE e.epf_number = ? AND e.is_active = 1
                AND e.employment_level_id IN (3, 4, 5, 6, 7)
            ", [$employee_epf]);

            if (!$employee) {
                throw new Exception("Employee not found or not eligible for goal setting.");
            }

            $has_authority = false;
            $is_own_goals = ($employee_epf == $manager_epf); // Check if user is viewing their own goals

            if ($is_own_goals) {
                // Users can always view/edit their own goals if they are eligible
                $has_authority = true;
            } else {
                // Check if user has authority based on hierarchy
                if ($employee['reports_to'] == $manager_epf) {
                    // Direct supervisor relationship
                    $has_authority = true;
                } else {
                    // Check for skip-level authority (when direct supervisor is not a manager)
                    $direct_supervisor = $db->fetch("
                        SELECT employment_level_id, reports_to
                        FROM employees
                        WHERE epf_number = ?
                    ", [$employee['reports_to']]);

                    if ($direct_supervisor &&
                        !in_array($direct_supervisor['employment_level_id'], [2, 3]) &&
                        $direct_supervisor['reports_to'] == $manager_epf) {
                        $has_authority = true;
                    }
                }
            }

            if (!$has_authority && !isSuperAdmin()) {
                if ($is_own_goals) {
                    throw new Exception("You are not eligible for goal setting or your goals have not been set up yet.");
                } else {
                    throw new Exception("You don't have authority to set goals for this employee.");
                }
            }

            // Get proper manager information using hierarchy logic
            function getProperManager($db, $employee_epf, $supervisor_epf, $employment_level_id) {
                $current_supervisor_epf = $supervisor_epf;
                $max_depth = 5;
                $depth = 0;

                while ($current_supervisor_epf && $depth < $max_depth) {
                    $supervisor = $db->fetch("
                        SELECT epf_number, name, employment_level_id, reports_to
                        FROM employees
                        WHERE epf_number = ? AND is_active = 1
                    ", [$current_supervisor_epf]);

                    if (!$supervisor) break;

                    if (in_array($supervisor['employment_level_id'], [2, 3])) {
                        return [
                            'epf' => $supervisor['epf_number'],
                            'name' => $supervisor['name']
                        ];
                    }

                    $current_supervisor_epf = $supervisor['reports_to'];
                    $depth++;
                }

                return ['epf' => '', 'name' => ''];
            }

            $manager_info = getProperManager($db, $employee['epf_number'], $employee['reports_to'], $employee['employment_level_id']);

            // Debug: Log what data we have
            error_log("Employee data keys: " . implode(", ", array_keys($employee)));
            error_log("Employee location_name: " . ($employee['location_name'] ?? 'NULL'));
            error_log("Manager info: " . json_encode($manager_info));

            // Server-side validation: Check if weightages total exactly 100%
            $total_weightage = 0;
            for ($i = 1; $i <= 6; $i++) {
                $weightage = $_POST["goal_weightage_section_$i"] ?? 0;
                $total_weightage += floatval($weightage);
            }

            if ($total_weightage != 100) {
                // Don't throw exception - let JavaScript handle this
                $error = "Weightages must total exactly 100%. Current total: " . number_format($total_weightage, 2) . "%";
                // This will show the error without refreshing and losing data
                echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        alert('❌ SAVE BLOCKED: {$error}\\n\\nPlease adjust the weightages and try again.');
                        // Scroll to the total row
                        document.getElementById('total-weightage').scrollIntoView({behavior: 'smooth'});
                        // Focus on first weightage input
                        document.querySelector('input[name=\"goal_weightage_section_1\"]')?.focus();
                    });
                </script>";
                // Don't process the save, but don't throw exception either
                goto skip_save;
            }

            // Check if record already exists
            $existing_record = $db->fetch("
                SELECT id FROM executive_goal_records
                WHERE employee_epf = ? AND period_id = ?
            ", [$employee_epf, $period_id]);

            if ($existing_record) {
                $record_id = $existing_record['id'];
                // Update existing record
                $db->update('executive_goal_records', [
                    'form_saved' => 'Y',
                    'manager_agreement_date' => date('Y-m-d H:i:s'),
                    'updated_date' => date('Y-m-d H:i:s')
                ], 'id = ?', [$record_id]);
            } else {
                // Create new record
                $record_data = [
                    'period_id' => $period_id,
                    'employee_epf' => $employee_epf,
                    'manager_epf' => $manager_epf,
                    'form_saved' => 'Y',
                    'manager_agreement_date' => date('Y-m-d H:i:s'),
                    'created_date' => date('Y-m-d H:i:s')
                ];
                $record_id = $db->insert('executive_goal_records', $record_data);
            }

            // Delete existing goal details
            $db->query("DELETE FROM goal_details WHERE record_id = ?", [$record_id]);

            // Save ALL form data in 1 SINGLE ROW per employee
            $single_row_data = [
                'record_id' => $record_id,
                'goal_section' => '12.3.3',
                'goal_subsection' => 'COMPLETE_FORM',
                // Save employee information in the single row
                'employee_epf' => $employee['epf_number'] ?? '',
                'employee_name' => $employee['name'] ?? '',
                'employee_designation' => $employee['designation'] ?? '',
                'employee_department' => $employee['department'] ?? '',
                'employee_location' => $employee['location_name'] ?? '',
                'employee_joining_date' => $employee['joined_date'] ?? null,
                'employee_service_years' => isset($employee['joined_date']) ?
                    floor((strtotime('now') - strtotime($employee['joined_date'])) / (365.25 * 24 * 3600)) . ' years' : '',
                'employee_manager_epf' => $manager_info['epf'] ?? '',
                'employee_manager_name' => $manager_info['name'] ?? '',
                'created_date' => date('Y-m-d H:i:s')
            ];

            // Save all section-level merged data (Main Goals, Weightage, etc.)
            for ($i = 1; $i <= 6; $i++) {
                $single_row_data["section_{$i}_main_goals"] = $_POST["goal_main_goals_section_$i"] ?? '';
                $single_row_data["section_{$i}_weightage"] = !empty($_POST["goal_weightage_section_$i"]) ? $_POST["goal_weightage_section_$i"] : null;
                $single_row_data["section_{$i}_achieved_percentage"] = !empty($_POST["goal_achieved_percentage_section_$i"]) ? $_POST["goal_achieved_percentage_section_$i"] : null;
                $single_row_data["section_{$i}_self_rating"] = !empty($_POST["goal_self_rating_section_$i"]) ? $_POST["goal_self_rating_section_$i"] : null;
                $single_row_data["section_{$i}_supervisor_rating"] = !empty($_POST["goal_supervisor_rating_section_$i"]) ? $_POST["goal_supervisor_rating_section_$i"] : null;
                $single_row_data["section_{$i}_final_rating"] = !empty($_POST["goal_final_rating_section_$i"]) ? $_POST["goal_final_rating_section_$i"] : null;
                $single_row_data["section_{$i}_mid_year_progress"] = !empty($_POST["goal_mid_year_progress_section_$i"]) ? $_POST["goal_mid_year_progress_section_$i"] : null;
            }

            // Save ALL individual cell data (Activities, Measurement Criteria, Mid-Year Progress for each sub-item)
            for ($i = 1; $i <= 6; $i++) {
                for ($j = 1; $j <= 6; $j++) {
                    $activities_key = "goal_activities_12_3_3_{$i}_{$j}";
                    $measurement_key = "goal_measurement_criteria_12_3_3_{$i}_{$j}";
                    $mid_year_key = "activities_{$i}_{$j}_mid_year_progress";

                    $single_row_data["activities_{$i}_{$j}"] = $_POST[$activities_key] ?? '';
                    $single_row_data["measurement_criteria_{$i}_{$j}"] = $_POST[$measurement_key] ?? '';

                    // Mid-year progress is disabled in goal forms - only editable in mid-year review
                    // Don't save mid-year progress from disabled form fields
                    // $single_row_data[$mid_year_key] = null; // Keep existing values
                }
            }

            // Debug: Check what we're about to insert
            error_log("About to insert employee_location: '" . ($single_row_data['employee_location'] ?? 'NULL') . "'");
            error_log("About to insert employee_manager_epf: '" . ($single_row_data['employee_manager_epf'] ?? 'NULL') . "'");
            error_log("About to insert employee_manager_name: '" . ($single_row_data['employee_manager_name'] ?? 'NULL') . "'");

            // Insert ONE single record with ALL data
            $db->insert('goal_details', $single_row_data);


            // Handle employee agreement if checked
            if (isset($_POST['employee_agreement'])) {
                $db->update('executive_goal_records', [
                    'employee_agreement_date' => date('Y-m-d H:i:s')
                ], 'id = ?', [$record_id]);
            }

            $message = "Goal setting form saved successfully for " . htmlspecialchars($employee['name']) . "!";

            skip_save: // Label for goto when validation fails
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get available years - superadmins can see draft periods, others only see active
$status_condition = isSuperAdmin() ? "status IN ('active', 'draft')" : "status = 'active'";
$years = $db->fetchAll("
    SELECT year, status FROM goal_setting_periods
    WHERE $status_condition
    ORDER BY year DESC
");

// Get selected year data
$selected_year = $_GET['year'] ?? '';
$selected_employee = $_GET['employee'] ?? '';

$period = null;
$templates = [];
$employees = [];
$employee_data = null;
$existing_goals = [];

if ($selected_year) {
    $period = $db->fetch("SELECT * FROM goal_setting_periods WHERE year = ?", [$selected_year]);

    if ($period) {
        // Get templates for this period
        $templates = $db->fetchAll("
            SELECT * FROM goal_setting_templates
            WHERE period_id = ?
            ORDER BY goal_section, goal_subsection
        ", [$period['id']]);

        // Get employees that this user can set goals for
        $current_user_epf = $user['epf_number'];
        $account_type = $user['account_type'] ?? 'user';

        if (isSuperAdmin()) {
            // Superadmin can see all executives
            $employees = $db->fetchAll("
                SELECT e.epf_number, e.name, e.designation, e.department, l.location_name as location,
                       e.joined_date, el.level_name as employment_level
                FROM employees e
                LEFT JOIN locations l ON e.location_id = l.id
                LEFT JOIN employment_levels el ON e.employment_level_id = el.id
                WHERE e.is_active = 1
                AND e.employment_level_id IN (3, 4, 5, 6, 7)
                ORDER BY e.name
            ");
        } elseif ($account_type === 'manager') {
            // Managers can see themselves + their direct reports and skip-level reports based on hierarchy
            $employees = $db->fetchAll("
                SELECT e.epf_number, e.name, e.designation, e.department, l.location_name as location,
                       e.joined_date, el.level_name as employment_level
                FROM employees e
                LEFT JOIN locations l ON e.location_id = l.id
                LEFT JOIN employment_levels el ON e.employment_level_id = el.id
                LEFT JOIN employees s1 ON e.reports_to = s1.epf_number
                LEFT JOIN employment_levels el1 ON s1.employment_level_id = el1.id
                LEFT JOIN employees s2 ON s1.reports_to = s2.epf_number
                WHERE e.is_active = 1
                AND e.employment_level_id IN (3, 4, 5, 6, 7)
                AND (
                    -- Include themselves
                    e.epf_number = ?
                    OR
                    -- Direct reports where user is the direct supervisor
                    e.reports_to = ?
                    OR
                    -- Skip-level reports where direct supervisor is not a manager (levels 2-3)
                    -- and user is the upper supervisor
                    (s1.employment_level_id NOT IN (2, 3) AND s2.epf_number = ?)
                )
                ORDER BY CASE WHEN e.epf_number = ? THEN 0 ELSE 1 END, e.name
            ", [$current_user_epf, $current_user_epf, $current_user_epf, $current_user_epf]);
        } elseif (in_array($account_type, ['supervisor', 'admin'])) {
            // Supervisors and Admins can only see their own goals
            $employees = $db->fetchAll("
                SELECT e.epf_number, e.name, e.designation, e.department, l.location_name as location,
                       e.joined_date, el.level_name as employment_level
                FROM employees e
                LEFT JOIN locations l ON e.location_id = l.id
                LEFT JOIN employment_levels el ON e.employment_level_id = el.id
                WHERE e.epf_number = ? AND e.is_active = 1
                AND e.employment_level_id IN (3, 4, 5, 6, 7)
                ORDER BY e.name
            ", [$current_user_epf]);

            // For supervisors and admins, automatically select themselves if eligible
            if (!empty($employees) && !$selected_employee) {
                $selected_employee = $current_user_epf;
            }
        } else {
            // Regular users can only see their own goals (if they are eligible)
            $employees = $db->fetchAll("
                SELECT e.epf_number, e.name, e.designation, e.department, l.location_name as location,
                       e.joined_date, el.level_name as employment_level
                FROM employees e
                LEFT JOIN locations l ON e.location_id = l.id
                LEFT JOIN employment_levels el ON e.employment_level_id = el.id
                WHERE e.epf_number = ? AND e.is_active = 1
                AND e.employment_level_id IN (3, 4, 5, 6, 7)
                ORDER BY e.name
            ", [$current_user_epf]);

            // For regular users, automatically select themselves if eligible
            if (!empty($employees) && !$selected_employee) {
                $selected_employee = $current_user_epf;
            }
        }

        // If employee selected, get their data and existing goals
        if ($selected_employee) {
            $employee_data = $db->fetch("
                SELECT e.*, l.location_name as location, el.level_name as employment_level, s.name as supervisor_name
                FROM employees e
                LEFT JOIN locations l ON e.location_id = l.id
                LEFT JOIN employment_levels el ON e.employment_level_id = el.id
                LEFT JOIN employees s ON e.reports_to = s.epf_number
                WHERE e.epf_number = ?
            ", [$selected_employee]);

            // Determine if form should be read-only
            $is_read_only = false;
            if ($employee_data) {
                // Determine if user is viewing their own goals
                $viewing_own_goals = ($selected_employee === $current_user_epf);

                // Read-only logic:
                // - Users: Always read-only (can only view their own goals)
                // - Supervisors/Admins: Read-only when viewing their own goals, but can edit others if they have authority
                // - Managers: Can edit their own goals and others' goals
                // - SuperAdmin: Can edit all goals
                if ($account_type === 'user' ||
                    ($viewing_own_goals && in_array($account_type, ['supervisor', 'admin']))) {
                    $is_read_only = true;
                }
            }

            if ($employee_data) {
                // Get existing goal record
                $existing_record = $db->fetch("
                    SELECT * FROM executive_goal_records
                    WHERE employee_epf = ? AND period_id = ?
                ", [$selected_employee, $period['id']]);

                if ($existing_record) {
                    // Get existing goal details (single row format with goal_subsection = 'COMPLETE_FORM')
                    $existing_goal_data = $db->fetch("
                        SELECT * FROM goal_details
                        WHERE record_id = ? AND goal_subsection = 'COMPLETE_FORM'
                    ", [$existing_record['id']]);

                    // Store as associative array for form access
                    $existing_goals = $existing_goal_data ? ['COMPLETE_FORM' => $existing_goal_data] : [];
                }
            }
        }
    }
}

function calculateServiceYears($joined_date) {
    $join = new DateTime($joined_date);
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
    <title>SmartHRM - Goal Setting Forms</title>
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

        .goal-section-header {
            background: linear-gradient(135deg, var(--info-color), #138496);
            color: white;
            padding: 0.75rem;
            border-radius: 8px;
            font-weight: bold;
            text-align: center;
        }

        .employee-info-card {
            background: linear-gradient(135deg, var(--success-color), #1e7e34);
            color: white;
            border-radius: 10px;
            padding: 1.5rem;
        }

        .agreement-section {
            background: #f8f9fa;
            border: 2px solid var(--primary-color);
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 2rem;
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
                        <h1><i class="fas fa-clipboard-list me-3"></i>Goal Setting Forms</h1>
                        <p>Create and manage employee goal setting forms</p>
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
                            <h5 class="mb-0">
                                <i class="fas fa-<?= $account_type == 'user' ? 'user' : 'users' ?> me-2"></i>
                                <?= $account_type == 'user' ? 'My Goals' : 'Select Employee' ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if ($account_type == 'user'): ?>
                                <!-- For regular users, show their info directly -->
                                <?php if (!empty($employees)): ?>
                                    <div class="alert alert-info mb-0">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Viewing your goals:</strong>
                                        <?= htmlspecialchars($employees[0]['epf_number']) ?> - <?= htmlspecialchars($employees[0]['name']) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning mb-0">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        You are not currently eligible for goal setting. Please contact your manager if you believe this is an error.
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <!-- For managers/supervisors, show employee selection -->
                                <form method="GET">
                                    <input type="hidden" name="year" value="<?= $selected_year ?>">
                                    <div class="mb-3">
                                        <select name="employee" class="form-select" onchange="this.form.submit()">
                                            <option value="">Choose Employee...</option>
                                            <?php foreach ($employees as $emp): ?>
                                                <option value="<?= $emp['epf_number'] ?>" <?= $selected_employee == $emp['epf_number'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($emp['epf_number']) ?> - <?= htmlspecialchars($emp['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Employee Information & Goal Setting Form -->
            <?php if ($employee_data && $period): ?>
            <form method="POST"<?= $is_read_only ? ' onsubmit="return false;"' : '' ?>>
                <input type="hidden" name="action" value="save_goals">
                <input type="hidden" name="employee_epf" value="<?= $employee_data['epf_number'] ?>">
                <input type="hidden" name="period_id" value="<?= $period['id'] ?>">

                <!-- Employee Information -->
                <div class="employee-info-card mb-4">
                    <div class="row">
                        <div class="col-md-6">
                            <h5><i class="fas fa-user me-2"></i>Employee Information</h5>
                            <p class="mb-2"><strong>EPF No:</strong> <?= htmlspecialchars($employee_data['epf_number']) ?></p>
                            <p class="mb-2"><strong>Name:</strong> <?= htmlspecialchars($employee_data['name']) ?></p>
                            <p class="mb-2"><strong>Designation:</strong> <?= htmlspecialchars($employee_data['designation']) ?></p>
                            <p class="mb-0"><strong>Department:</strong> <?= htmlspecialchars($employee_data['department']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-2"><strong>Location:</strong> <?= htmlspecialchars($employee_data['location']) ?></p>
                            <p class="mb-2"><strong>Joining Date:</strong> <?= date('Y-m-d', strtotime($employee_data['joined_date'])) ?></p>
                            <p class="mb-2"><strong>Service Years:</strong> <?= calculateServiceYears($employee_data['joined_date']) ?></p>
                            <p class="mb-0"><strong>Manager:</strong> <?= htmlspecialchars($user['name']) ?> (<?= $user['epf_number'] ?>)</p>
                        </div>
                    </div>
                </div>

                <!-- Goal Setting Form -->
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-bullseye me-2"></i>Goal Setting Form - <?= $selected_year ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-dark">
                                    <tr>
                                        <th style="width: 5%;">Goal S/N #</th>
                                        <th style="width: 15%;">Main Goals</th>
                                        <th style="width: 27%;">Activities</th>
                                        <th style="width: 14%;">Measurement Criteria</th>
                                        <th style="width: 6%;">Weightage (%)</th>
                                        <th style="width: 8%;">Mid-Year</th>
                                        <th style="width: 6%;">Achieved %</th>
                                        <th style="width: 6%;">Self Rating</th>
                                        <th style="width: 6%;">Supervisor Rating</th>
                                        <th style="width: 6%;">Final Rating</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $current_section = '';
                                    $section_counter = 0;
                                    $sub_item_counter = 0;
                                    foreach ($templates as $template):
                                        if (substr_count($template['goal_subsection'], '.') == 3) { // Main section headers
                                            $current_section = $template['goal_section'];
                                            $section_counter++;
                                            $sub_item_counter = 0;
                                    ?>
                                        <tr>
                                            <td class="goal-section-header"><strong><?= htmlspecialchars($template['goal_subsection']) ?></strong></td>
                                            <td colspan="9" class="goal-section-header text-center">
                                                <em>Main Goal Section <?= substr($current_section, -1) ?></em>
                                            </td>
                                        </tr>
                                    <?php
                                        } else { // Sub-items
                                            $section_key = $template['goal_subsection'];
                                            // Use the single-row data format
                                            $existing_data = $existing_goals['COMPLETE_FORM'] ?? null;

                                            // Map template subsection to database field names
                                            // e.g., '12.3.3.1.1' -> section=1, item=1 -> 'activities_1_1', 'measurement_criteria_1_1'
                                            $section_num = $template['section_number'];
                                            $item_num = $sub_item_counter + 1;
                                            $activities_field = "activities_{$section_num}_{$item_num}";
                                            $measurement_field = "measurement_criteria_{$section_num}_{$item_num}";

                                            $sub_item_counter++;
                                    ?>
                                        <tr>
                                            <?php if ($sub_item_counter == 1): // Only show merged cell for first item in section ?>
                                                <td rowspan="6" class="text-center align-middle"><strong><?= $section_counter ?></strong></td>
                                                <td rowspan="6" class="align-middle" style="width: 15%;">
                                                    <textarea class="form-control form-control-sm"
                                                              name="goal_main_goals_section_<?= $section_counter ?>"
                                                              rows="28"
                                                              style="height: 100%; min-height: 420px; resize: vertical; vertical-align: middle; padding-top: 180px;"
                                                              placeholder="Enter main goal for section <?= $section_counter ?>..."
                                                              <?= $is_read_only ? 'readonly' : '' ?>><?= $existing_data ? htmlspecialchars($existing_data["section_{$section_counter}_main_goals"] ?? '') : htmlspecialchars($template['main_goals'] ?? '') ?></textarea>
                                                </td>
                                            <?php endif; ?>
                                            <td>
                                                <textarea class="form-control form-control-sm"
                                                          name="goal_activities_<?= str_replace('.', '_', $template['goal_subsection']) ?>"
                                                          rows="3"
                                                          style="vertical-align: middle; padding-top: 20px;"
                                                          placeholder="Enter activities..."
                                                          <?= $is_read_only ? 'readonly' : '' ?>><?= $existing_data ? htmlspecialchars($existing_data[$activities_field] ?? '') : htmlspecialchars($template['activities'] ?? '') ?></textarea>
                                            </td>
                                            <td>
                                                <textarea class="form-control form-control-sm"
                                                          name="goal_measurement_criteria_<?= str_replace('.', '_', $template['goal_subsection']) ?>"
                                                          rows="3"
                                                          style="vertical-align: middle; padding-top: 20px;"
                                                          placeholder="How to measure outcome..."
                                                          <?= $is_read_only ? 'readonly' : '' ?>><?= $existing_data ? htmlspecialchars($existing_data[$measurement_field] ?? '') : htmlspecialchars($template['measurement_criteria'] ?? '') ?></textarea>
                                            </td>
                                            <?php if ($sub_item_counter == 1): // Only show merged weightage cell for first item in section ?>
                                                <td rowspan="6" class="align-middle text-center">
                                                    <input type="number"
                                                           class="form-control form-control-sm weightage-input text-center"
                                                           name="goal_weightage_section_<?= $section_counter ?>"
                                                           value="<?= $existing_data ? $existing_data["section_{$section_counter}_weightage"] : $template['weightage'] ?>"
                                                           min="0" max="100" step="0.01"
                                                           <?= $is_read_only ? 'readonly' : '' ?>>
                                                </td>
                                            <?php endif; ?>
                                            <td class="text-center">
                                                <?php
                                                // Get current section and sub-item for Mid-Year Progress
                                                $current_section_num = substr($current_section, -1);
                                                $sub_item_num = $sub_item_counter;

                                                // Find the COMPLETE_FORM entry from existing_goals
                                                $complete_form_data = null;
                                                foreach ($existing_goals as $goal) {
                                                    if ($goal['goal_subsection'] == 'COMPLETE_FORM') {
                                                        $complete_form_data = $goal;
                                                        break;
                                                    }
                                                }

                                                $mid_year_progress = $complete_form_data["activities_{$current_section_num}_{$sub_item_num}_mid_year_progress"] ?? '';
                                                ?>
                                                <select class="form-select form-select-sm" name="activities_<?= $current_section_num ?>_<?= $sub_item_num ?>_mid_year_progress" disabled>
                                                    <option value="">Select...</option>
                                                    <option value="YS" <?= $mid_year_progress == 'YS' ? 'selected' : '' ?>>YS</option>
                                                    <option value="IP" <?= $mid_year_progress == 'IP' ? 'selected' : '' ?>>IP</option>
                                                    <option value="C" <?= $mid_year_progress == 'C' ? 'selected' : '' ?>>C</option>
                                                </select>
                                                <small class="text-muted d-block mt-1">(Use Mid-Year Review)</small>
                                            </td>
                                            <?php if ($sub_item_counter == 1): // Merged Achieved % column for first item in section ?>
                                                <td rowspan="6" class="align-middle text-center text-muted">
                                                    <small>%</small><br>
                                                    <small class="text-secondary">(Disabled)</small>
                                                </td>
                                            <?php endif; ?>
                                            <?php if ($sub_item_counter == 1): // Merged rating columns for first item in section ?>
                                                <td rowspan="6" class="align-middle text-center text-muted">
                                                    <small>Rating</small><br>
                                                    <small class="text-secondary">(Disabled)</small>
                                                </td>
                                                <td rowspan="6" class="align-middle text-center text-muted">
                                                    <small>Rating</small><br>
                                                    <small class="text-secondary">(Disabled)</small>
                                                </td>
                                                <td rowspan="6" class="align-middle text-center text-muted">
                                                    <small>Rating</small><br>
                                                    <small class="text-secondary">(Disabled)</small>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php
                                        }
                                    endforeach;
                                    ?>
                                    <tr class="table-warning">
                                        <td colspan="4" class="text-end"><strong>TOTAL</strong></td>
                                        <td class="text-center"><strong><span id="total-weightage">0.00</span>%</strong></td>
                                        <td colspan="5"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Agreement Section -->
                        <div class="agreement-section">
                            <h6><i class="fas fa-handshake me-2"></i>Agreement on Goals</h6>
                            <p class="text-muted mb-3"><em>(At the commencement of the New Appraisal Year)</em></p>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="employee_agreement" id="employeeAgreement"
                                               <?= ($existing_record && !empty($existing_record['employee_agreement_date'])) ? 'checked' : '' ?>
                                               <?= $is_read_only ? 'disabled' : '' ?>>
                                        <label class="form-check-label" for="employeeAgreement">
                                            <strong>Employee Agreement</strong><br>
                                            <small class="text-muted">I agree to the goals set for this appraisal period</small>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" checked disabled>
                                        <label class="form-check-label">
                                            <strong>Manager Agreement</strong><br>
                                            <small class="text-muted">Goals set by: <?= htmlspecialchars($user['name']) ?></small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Weightage Warning Alert -->
                        <div id="weightage-warning" class="alert alert-warning d-none" role="alert">
                            <h5><i class="fas fa-exclamation-triangle me-2"></i>Weightage Total Issue</h5>
                            <p id="weightage-warning-text" class="mb-2"></p>
                            <small class="text-muted">Please adjust the weightages above to total exactly 100% before saving.</small>
                        </div>

                        <?php if (!$is_read_only): ?>
                        <div class="mt-4 text-center">
                            <button type="submit" id="save-button" class="btn btn-success btn-lg">
                                <i class="fas fa-save me-2"></i>Save Goal Setting Form
                            </button>
                            <div id="save-button-warning" class="text-danger mt-2 d-none">
                                <small><i class="fas fa-lock me-1"></i>Save is blocked until weightages total 100%</small>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="mt-4 text-center">
                            <div class="alert alert-info">
                                <i class="fas fa-eye me-2"></i>
                                <strong>View Only Mode</strong> - You are viewing your goals in read-only mode.
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
            <?php endif; ?>

            <!-- Navigation -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Goal Setting
                        </a>
                        <a href="kpi_tracker.php" class="btn btn-primary">
                            <i class="fas fa-tasks me-2"></i>View KPI Tracker
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Calculate total weightage from section-based inputs
        function updateTotalWeightage() {
            let total = 0;
            // Get all section weightage inputs (goal_weightage_section_1, goal_weightage_section_2, etc.)
            for (let i = 1; i <= 6; i++) {
                const input = document.querySelector(`input[name="goal_weightage_section_${i}"]`);
                if (input) {
                    total += parseFloat(input.value) || 0;
                }
            }
            document.getElementById('total-weightage').textContent = total.toFixed(2);

            // Color coding based on total
            const totalElement = document.getElementById('total-weightage');
            const parentRow = totalElement.closest('tr');
            const warningDiv = document.getElementById('weightage-warning');
            const warningText = document.getElementById('weightage-warning-text');
            const saveButton = document.getElementById('save-button');
            const saveWarning = document.getElementById('save-button-warning');

            if (total === 100) {
                // Exactly 100% - Success (green)
                parentRow.classList.remove('table-danger', 'table-warning');
                parentRow.classList.add('table-success');
                totalElement.style.color = 'green';

                // Hide warning and enable save
                warningDiv.classList.add('d-none');
                saveButton.disabled = false;
                saveButton.classList.remove('btn-secondary');
                saveButton.classList.add('btn-success');
                saveWarning.classList.add('d-none');
            } else if (total > 100) {
                // Over 100% - Danger (red)
                parentRow.classList.remove('table-success', 'table-warning');
                parentRow.classList.add('table-danger');
                totalElement.style.color = 'red';

                // Show warning and disable save
                warningText.textContent = `Total is ${total.toFixed(2)}% (${(total - 100).toFixed(2)}% over 100%). Please reduce some weightages.`;
                warningDiv.classList.remove('d-none');
                warningDiv.className = 'alert alert-danger';
                saveButton.disabled = true;
                saveButton.classList.remove('btn-success');
                saveButton.classList.add('btn-secondary');
                saveWarning.classList.remove('d-none');
            } else {
                // Under 100% - Warning (orange)
                parentRow.classList.remove('table-success', 'table-danger');
                parentRow.classList.add('table-warning');
                totalElement.style.color = 'orange';

                // Show warning and disable save
                warningText.textContent = `Total is ${total.toFixed(2)}% (${(100 - total).toFixed(2)}% under 100%). Please add more weightages.`;
                warningDiv.classList.remove('d-none');
                warningDiv.className = 'alert alert-warning';
                saveButton.disabled = true;
                saveButton.classList.remove('btn-success');
                saveButton.classList.add('btn-secondary');
                saveWarning.classList.remove('d-none');
            }
        }

        // Add event listeners to section weightage inputs
        for (let i = 1; i <= 6; i++) {
            const input = document.querySelector(`input[name="goal_weightage_section_${i}"]`);
            if (input) {
                input.addEventListener('input', updateTotalWeightage);
                input.addEventListener('blur', validateWeightageTotal);
            }
        }

        // Validation function to show alert if not 100%
        function validateWeightageTotal() {
            let total = 0;
            for (let i = 1; i <= 6; i++) {
                const input = document.querySelector(`input[name="goal_weightage_section_${i}"]`);
                if (input) {
                    total += parseFloat(input.value) || 0;
                }
            }

            if (Math.abs(total - 100) > 0.01) {
                const message = total > 100
                    ? `Total weightage is ${total.toFixed(2)}%. It must equal exactly 100%.`
                    : `Total weightage is ${total.toFixed(2)}%. Please allocate the remaining ${(100 - total).toFixed(2)}%.`;

                // Show warning (you can replace with a toast or better UI)
                console.warn(message);
            }
        }

        // Form submission validation - STRICT blocking
        function validateFormSubmission(event) {
            let total = 0;
            let hasWeightages = false;

            for (let i = 1; i <= 6; i++) {
                const input = document.querySelector(`input[name="goal_weightage_section_${i}"]`);
                if (input) {
                    const value = parseFloat(input.value) || 0;
                    total += value;
                    if (value > 0) hasWeightages = true;
                }
            }

            // ALWAYS block if not exactly 100% (even if no weightages entered yet)
            if (total !== 100) {
                event.preventDefault();
                event.stopImmediatePropagation();

                const message = total > 100
                    ? `Weightages total ${total.toFixed(2)}% (over 100%). Please reduce some values.`
                    : total === 0
                        ? `Please enter weightage percentages for all sections. Total must be 100%.`
                        : `Weightages total ${total.toFixed(2)}% (under 100%). Please add ${(100 - total).toFixed(2)}% more.`;

                alert(`❌ FORM BLOCKED: ${message}\n\nSave button will only work when total is exactly 100%.`);

                // Disable submit button visually
                const submitBtn = document.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.style.opacity = '0.5';
                    setTimeout(() => {
                        submitBtn.disabled = false;
                        submitBtn.style.opacity = '1';
                    }, 2000);
                }

                // Focus on first weightage input
                document.querySelector('input[name="goal_weightage_section_1"]')?.focus();
                return false;
            }
            return true;
        }

        // Add read-only protection
        <?php if ($is_read_only): ?>
        // Prevent form submission for read-only users
        document.querySelector('form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            alert('❌ Form submission blocked!\n\nYou are viewing goals in read-only mode. Only managers can edit goal settings for themselves and their subordinates.\n\nIf you need changes to your goals, please contact your manager.');
            return false;
        });
        <?php else: ?>
        // Add form validation for editable users
        document.querySelector('form')?.addEventListener('submit', validateFormSubmission);
        <?php endif; ?>

        // Calculate initial total
        updateTotalWeightage();

        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    </script>
</body>
</html>