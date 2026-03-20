<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/performance_auth.php';

// Check if user has staff appraisal permission
requirePerformancePermission('performance.staff_appraisal');

$user = getCurrentUser();
$db = new Database();

$message = '';
$error = '';

// Get active appraisal years from setup
$active_years = $db->fetchAll("
    SELECT DISTINCT appraisal_year
    FROM appraisal_periods
    WHERE status = 'active'
    ORDER BY appraisal_year ASC
");

// Get selected year (prefer current year if it's active, otherwise use first active year)
$current_year = date('Y');
$default_year = null;

// First, check if current year (2025) is in active years
foreach ($active_years as $year_data) {
    if ($year_data['appraisal_year'] == $current_year) {
        $default_year = $current_year;
        break;
    }
}

// If current year is not active and we have active years, use the first one
if ($default_year === null && !empty($active_years)) {
    $default_year = $active_years[0]['appraisal_year'];
}

// Final fallback to current year
if ($default_year === null) {
    $default_year = $current_year;
}

$selected_year = $_GET['year'] ?? $default_year;

// Get EPF parameter if provided
$selected_epf = $_GET['epf'] ?? '';

// If form is submitted
if ($_POST) {
    try {
        $data = [
            'appraisal_year' => $_POST['appraisal_year'],
            'appraiser_epf' => getPerformanceUserEPF(),
            'appraiser_name' => $user['name'],
            'appraisee_epf' => $_POST['appraisee_epf'],
            'appraisee_name' => $_POST['appraisee_name'],
            'designation' => $_POST['designation'],
            'department' => $_POST['department'],
            'location' => $_POST['location'],
            'joining_date' => $_POST['joining_date'],
            'service_years' => is_numeric($_POST['service_years']) ? (float)$_POST['service_years'] : null,
            'annual_leave_utilization' => $_POST['annual_leave_utilization'],
            'casual_leave_utilization' => $_POST['casual_leave_utilization'],
            'medical_leave_utilization' => $_POST['medical_leave_utilization'],
            'no_pay_days' => $_POST['no_pay_days'],

            // Category 1: Job Knowledge and Skills
            'job_knowledge_q1' => $_POST['job_knowledge_q1'],
            'job_knowledge_q2' => $_POST['job_knowledge_q2'],
            'job_knowledge_q3' => $_POST['job_knowledge_q3'],
            'job_knowledge_remark' => $_POST['job_knowledge_remark'],

            // Category 2: Creativity/Innovation
            'creativity_q1' => $_POST['creativity_q1'],
            'creativity_remark' => $_POST['creativity_remark'],

            // Category 3: Awareness of Quality
            'quality_awareness_q1' => $_POST['quality_awareness_q1'],
            'quality_awareness_q2' => $_POST['quality_awareness_q2'],
            'quality_awareness_remark' => $_POST['quality_awareness_remark'],

            // Category 4: Goal Achievement
            'goal_achievement_q1' => $_POST['goal_achievement_q1'],
            'goal_achievement_remark' => $_POST['goal_achievement_remark'],

            // Category 5: Teamwork
            'teamwork_q1' => $_POST['teamwork_q1'],
            'teamwork_q2' => $_POST['teamwork_q2'],
            'teamwork_q3' => $_POST['teamwork_q3'],
            'teamwork_q4' => $_POST['teamwork_q4'],
            'teamwork_remark' => $_POST['teamwork_remark'],

            // Category 6: Cultural Awareness
            'cultural_awareness_q1' => $_POST['cultural_awareness_q1'],
            'cultural_awareness_q2' => $_POST['cultural_awareness_q2'],
            'cultural_awareness_q3' => $_POST['cultural_awareness_q3'],
            'cultural_awareness_q4' => $_POST['cultural_awareness_q4'],
            'cultural_awareness_q5' => $_POST['cultural_awareness_q5'],
            'cultural_awareness_q6' => $_POST['cultural_awareness_q6'],
            'cultural_awareness_remark' => $_POST['cultural_awareness_remark'],

            // Category 7: Safety Consciousness
            'safety_consciousness_q1' => $_POST['safety_consciousness_q1'],
            'safety_consciousness_q2' => $_POST['safety_consciousness_q2'],
            'safety_consciousness_q3' => $_POST['safety_consciousness_q3'],
            'safety_consciousness_q4' => $_POST['safety_consciousness_q4'],
            'safety_consciousness_remark' => $_POST['safety_consciousness_remark'],

            // Category 8: Discipline
            'discipline_q1' => $_POST['discipline_q1'],
            'discipline_q2' => $_POST['discipline_q2'],
            'discipline_q3' => isset($_POST['discipline_q3']) ? 1 : 0,
            'discipline_q4' => isset($_POST['discipline_q4']) ? 1 : 0,
            'discipline_remark' => $_POST['discipline_remark'],

            // Additional Information
            'additional_comments' => $_POST['additional_comments'],
            'special_talents' => $_POST['special_talents'],
            'future_expectations' => $_POST['future_expectations'],

            'status' => $_POST['action'] === 'submit' ? 'submitted' : 'draft',
            'submitted_at' => $_POST['action'] === 'submit' ? date('Y-m-d H:i:s') : null
        ];

        // Check if appraisal already exists
        $existing = $db->fetch(
            "SELECT id FROM staff_appraisals WHERE appraisal_year = ? AND appraisee_epf = ?",
            [$data['appraisal_year'], $data['appraisee_epf']]
        );

        if ($existing) {
            // Update existing
            $db->update('staff_appraisals', $data, 'id = ?', [$existing['id']]);
            $message = 'Staff appraisal updated successfully!';
        } else {
            // Insert new
            $db->insert('staff_appraisals', $data);
            $message = 'Staff appraisal saved successfully!';
        }

    } catch (Exception $e) {
        $error = 'Error saving appraisal: ' . $e->getMessage();
    }
}

// Get staff members that the current user can appraise (excluding those with completed appraisals for the selected year)
$staff_members = $db->fetchAll("
    SELECT
        e.epf_number,
        e.name,
        e.designation,
        e.department,
        CASE
            WHEN e.location_id = 1 THEN '7C'
            WHEN e.location_id = 2 THEN 'Pannala'
            WHEN e.location_id = 3 THEN 'Kobeigane'
            WHEN e.location_id = 4 THEN 'JECOE'
            WHEN e.location_id = 5 THEN 'Head Office'
            ELSE 'Unknown'
        END as location,
        e.joined_date as joining_date,
        e.service as service_years,
        COALESCE(el.level_name, 'Unknown Level') as employment_level,
        TIMESTAMPDIFF(YEAR, e.joined_date, CURDATE()) as service_years_numeric
    FROM employees e
    LEFT JOIN employment_levels el ON e.employment_level_id = el.id
    WHERE e.is_active = 1
    AND e.employment_level_id IN (8, 9)
    AND CONVERT(e.reports_to USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci
    AND NOT EXISTS (
        SELECT 1 FROM staff_appraisals sa
        WHERE CONVERT(sa.appraisee_epf USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(e.epf_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
        AND sa.appraisal_year = ?
        AND sa.status IN ('submitted', 'approved')
    )
    ORDER BY e.name
", [getPerformanceUserEPF(), $selected_year]);

// Get leave utilization data for the selected year
$leave_data = [];
if (!empty($staff_members)) {
    try {
        // Check if leave_utilization table exists and determine EPF column name
        $tables = $db->fetchAll("SHOW TABLES LIKE 'leave_utilization'");
        if (!empty($tables)) {
            $columns = $db->fetchAll("SHOW COLUMNS FROM leave_utilization");
            $leave_epf_column = 'epf_number'; // default

            foreach ($columns as $column) {
                if ($column['Field'] === 'epf_no') {
                    $leave_epf_column = 'epf_no';
                    break;
                }
            }

            $epf_list = array_column($staff_members, 'epf_number');
            $placeholders = str_repeat('?,', count($epf_list) - 1) . '?';

            $leave_data = $db->fetchAll("
                SELECT {$leave_epf_column} as epf_number, annual_leave_days, casual_leave_days, medical_leave_days, no_pay_days
                FROM leave_utilization
                WHERE year = ? AND {$leave_epf_column} IN ($placeholders)
            ", array_merge([$selected_year], $epf_list));
        }
    } catch (Exception $e) {
        // Table might not exist, continue with empty array
        error_log("Leave utilization query failed: " . $e->getMessage());
    }
}

// Convert to associative array for easier lookup
$leave_lookup = [];
foreach ($leave_data as $leave) {
    $leave_lookup[$leave['epf_number']] = $leave;
}

// Get count of completed appraisals for information
$completed_count = 0;
try {
    $completed_result = $db->fetch("
        SELECT COUNT(*) as count
        FROM staff_appraisals sa
        JOIN employees e ON CONVERT(e.epf_number USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(sa.appraisee_epf USING utf8mb4) COLLATE utf8mb4_unicode_ci
        WHERE sa.appraisal_year = ?
        AND sa.status IN ('submitted', 'approved')
        AND e.employment_level_id IN (8, 9)
        AND e.is_active = 1
        AND CONVERT(e.reports_to USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci
    ", [$selected_year, getPerformanceUserEPF()]);
    $completed_count = $completed_result['count'] ?? 0;
} catch (Exception $e) {
    // Ignore errors, just set count to 0
    error_log("Error counting completed appraisals: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Staff Appraisal Form</title>
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

        .page-header .d-flex {
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

        .rating-group {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .rating-option {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .rating-option input[type="radio"] {
            transform: scale(1.2);
        }

        .category-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
        }

        .category-header {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .question-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid var(--primary-color);
        }

        .form-section {
            margin-bottom: 40px;
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
                        <h1><i class="fas fa-clipboard-check me-3"></i>Staff Performance Appraisal</h1>
                        <p>Complete staff performance evaluation and appraisal forms</p>
                    </div>
                    <div class="logo-container">
                        <img src="../../jiffy-logo.svg" alt="Jiffy Logo" class="page-header-logo">
                    </div>
                </div>
            </div>
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (empty($active_years)): ?>
                <div class="alert alert-warning alert-dismissible fade show">
                    <i class="fas fa-info-circle me-2"></i>No active appraisal periods found. Please create an active appraisal period in
                    <a href="staff_appraisal_setup.php" class="alert-link">Staff Appraisal Setup</a> before creating appraisals.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($completed_count > 0): ?>
                <div class="alert alert-info alert-dismissible fade show">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong><?= $completed_count ?></strong> staff member<?= $completed_count > 1 ? 's have' : ' has' ?> already completed appraisal<?= $completed_count > 1 ? 's' : '' ?> for year <strong><?= $selected_year ?></strong> and <?= $completed_count > 1 ? 'are' : 'is' ?> not shown in the dropdown.
                    <span class="badge bg-secondary ms-2"><?= count($staff_members) ?> remaining</span>
                    <a href="staff_marks_data.php?year=<?= $selected_year ?>" class="alert-link">View completed appraisals</a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>


            <?php if (empty($staff_members) && !empty($active_years)): ?>
                <div class="alert alert-warning alert-dismissible fade show">
                    <i class="fas fa-users me-2"></i>All eligible staff members have completed their appraisals for year <?= $selected_year ?>.
                    <a href="staff_marks_data.php?year=<?= $selected_year ?>" class="alert-link">View completed appraisals</a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" id="appraisalForm">
                <!-- Basic Information Section -->
                <div class="category-section">
                    <div class="category-header">
                        <h4 class="mb-0"><i class="fas fa-info-circle me-2"></i>Basic Information</h4>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Appraisal Year</label>
                                <select name="appraisal_year" class="form-select" required>
                                    <option value="">Select Year</option>
                                    <?php if (empty($active_years)): ?>
                                        <option value="" disabled>No active appraisal periods found</option>
                                    <?php else: ?>
                                        <?php foreach ($active_years as $year_data): ?>
                                            <option value="<?= $year_data['appraisal_year'] ?>"
                                                    <?= $selected_year == $year_data['appraisal_year'] ? 'selected' : '' ?>>
                                                <?= $year_data['appraisal_year'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Appraiser (Your Information)</label>
                                <input type="text" class="form-control"
                                       value="<?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['epf_number']) ?>)"
                                       readonly>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Select Employee for Appraisal</label>
                                <select name="appraisee_epf" id="appraisee_select" class="form-select" required>
                                    <option value="">Choose Employee</option>
                                    <?php if (empty($staff_members)): ?>
                                        <option value="" disabled>No employees available for appraisal</option>
                                    <?php else: ?>
                                        <?php foreach ($staff_members as $employee): ?>
                                            <option value="<?= htmlspecialchars($employee['epf_number']) ?>"
                                                    data-name="<?= htmlspecialchars($employee['name']) ?>"
                                                    data-designation="<?= htmlspecialchars($employee['designation']) ?>"
                                                    data-department="<?= htmlspecialchars($employee['department']) ?>"
                                                    data-location="<?= htmlspecialchars($employee['location']) ?>"
                                                    data-joining="<?= $employee['joining_date'] ?>"
                                                    data-service="<?= $employee['service_years'] ?>"
                                                    data-service-numeric="<?= $employee['service_years_numeric'] ?>"
                                                    <?= $selected_epf == $employee['epf_number'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($employee['name']) ?> (<?= htmlspecialchars($employee['epf_number']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Auto-filled employee details -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Employee Name</label>
                                <input type="text" name="appraisee_name" id="appraisee_name" class="form-control" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Designation</label>
                                <input type="text" name="designation" id="designation" class="form-control" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Department</label>
                                <input type="text" name="department" id="department" class="form-control" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Location</label>
                                <input type="text" name="location" id="location" class="form-control" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Joining Date</label>
                                <input type="date" name="joining_date" id="joining_date" class="form-control" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Service Years</label>
                                <input type="text" id="service_years_display" class="form-control" readonly>
                                <input type="hidden" name="service_years" id="service_years_numeric">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="form-label">Annual Leave</label>
                                <input type="number" name="annual_leave_utilization" id="annual_leave" class="form-control" readonly>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="form-label">Casual Leave</label>
                                <input type="number" name="casual_leave_utilization" id="casual_leave" class="form-control" readonly>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="form-label">Medical Leave</label>
                                <input type="number" name="medical_leave_utilization" id="medical_leave" class="form-control" readonly>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">No Pay Days</label>
                                <input type="number" name="no_pay_days" id="no_pay_days" class="form-control" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rating Scale Information -->
                <div class="category-section">
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle me-2"></i>Rating Scale:</h6>
                        <div class="row text-center">
                            <div class="col">
                                <strong>5</strong><br>Outstanding
                            </div>
                            <div class="col">
                                <strong>4</strong><br>Exceeds Expectations
                            </div>
                            <div class="col">
                                <strong>3</strong><br>Meets Expectations
                            </div>
                            <div class="col">
                                <strong>2</strong><br>Needs Improvement
                            </div>
                            <div class="col">
                                <strong>1</strong><br>Unsatisfactory
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Navigation Tabs -->
                <div class="category-section">
                    <ul class="nav nav-tabs" id="appraisalTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="cat1-tab" data-bs-toggle="tab" data-bs-target="#cat1" type="button" role="tab">
                                Category 1-2
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="cat3-tab" data-bs-toggle="tab" data-bs-target="#cat3" type="button" role="tab">
                                Category 3-4
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="cat5-tab" data-bs-toggle="tab" data-bs-target="#cat5" type="button" role="tab">
                                Category 5-6
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="cat7-tab" data-bs-toggle="tab" data-bs-target="#cat7" type="button" role="tab">
                                Category 7-8
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="additional-tab" data-bs-toggle="tab" data-bs-target="#additional" type="button" role="tab">
                                Additional Info
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content mt-4" id="appraisalTabContent">
                        <!-- Tab 1: Categories 1-2 -->
                        <div class="tab-pane fade show active" id="cat1" role="tabpanel">
                            <!-- Category 1: Job Knowledge and Skills -->
                            <div class="category-section">
                    <div class="category-header">
                        <h4 class="mb-0">Category 1 — Job Knowledge and Skills</h4>
                    </div>

                    <div class="question-box">
                        <h6>Question 1: Employee has required level of knowledge on techniques and products</h6>
                        <div class="rating-group">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <div class="rating-option">
                                    <input type="radio" name="job_knowledge_q1" value="<?= $i ?>" id="jk_q1_<?= $i ?>">
                                    <label for="jk_q1_<?= $i ?>"><?= $i ?></label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="question-box">
                        <h6>Question 2: Employee is able to plan and organize job tasks to complete the workload on time</h6>
                        <div class="rating-group">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <div class="rating-option">
                                    <input type="radio" name="job_knowledge_q2" value="<?= $i ?>" id="jk_q2_<?= $i ?>">
                                    <label for="jk_q2_<?= $i ?>"><?= $i ?></label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="question-box">
                        <h6>Question 3: Employee performs the tasks efficiently and effectively with minimal instruction</h6>
                        <div class="rating-group">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <div class="rating-option">
                                    <input type="radio" name="job_knowledge_q3" value="<?= $i ?>" id="jk_q3_<?= $i ?>">
                                    <label for="jk_q3_<?= $i ?>"><?= $i ?></label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea name="job_knowledge_remark" class="form-control" rows="3"
                                  placeholder="Additional comments about job knowledge and skills..."></textarea>
                    </div>
                </div>

                            <!-- Category 2: Creativity/Innovation -->
                <div class="category-section">
                    <div class="category-header">
                        <h4 class="mb-0">Category 2 — Creativity/Innovation</h4>
                    </div>

                    <div class="question-box">
                        <h6>Question 1: Employee initiates actions to improve work processes</h6>
                        <div class="rating-group">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <div class="rating-option">
                                    <input type="radio" name="creativity_q1" value="<?= $i ?>" id="cr_q1_<?= $i ?>">
                                    <label for="cr_q1_<?= $i ?>"><?= $i ?></label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea name="creativity_remark" class="form-control" rows="3"
                                  placeholder="Additional comments about creativity and innovation..."></textarea>
                    </div>
                </div>
                        </div>

                        <!-- Tab 2: Categories 3-4 -->
                        <div class="tab-pane fade" id="cat3" role="tabpanel">
                            <!-- Category 3: Awareness of Quality -->
                <div class="category-section">
                    <div class="category-header">
                        <h4 class="mb-0">Category 3 — Awareness of Quality</h4>
                    </div>

                    <div class="question-box">
                        <h6>Question 1: Employee delivers error-free work with minimal supervision</h6>
                        <div class="rating-group">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <div class="rating-option">
                                    <input type="radio" name="quality_awareness_q1" value="<?= $i ?>" id="qa_q1_<?= $i ?>">
                                    <label for="qa_q1_<?= $i ?>"><?= $i ?></label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="question-box">
                        <h6>Question 2: Employee takes corrective actions to avoid repetition of errors</h6>
                        <div class="rating-group">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <div class="rating-option">
                                    <input type="radio" name="quality_awareness_q2" value="<?= $i ?>" id="qa_q2_<?= $i ?>">
                                    <label for="qa_q2_<?= $i ?>"><?= $i ?></label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea name="quality_awareness_remark" class="form-control" rows="3"
                                  placeholder="Additional comments about quality awareness..."></textarea>
                    </div>
                </div>

                <!-- Category 4: Goal Achievement -->
                <div class="category-section">
                    <div class="category-header">
                        <h4 class="mb-0">Category 4 — Goal Achievement</h4>
                    </div>

                    <div class="question-box">
                        <h6>Question 1: Employee meets individual and team targets consistently</h6>
                        <div class="rating-group">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <div class="rating-option">
                                    <input type="radio" name="goal_achievement_q1" value="<?= $i ?>" id="ga_q1_<?= $i ?>">
                                    <label for="ga_q1_<?= $i ?>"><?= $i ?></label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea name="goal_achievement_remark" class="form-control" rows="3"
                                  placeholder="Additional comments about goal achievement..."></textarea>
                    </div>
                </div>
                        </div>

                        <!-- Tab 3: Categories 5-6 -->
                        <div class="tab-pane fade" id="cat5" role="tabpanel">
                            <!-- Category 5: Teamwork -->
                <div class="category-section">
                    <div class="category-header">
                        <h4 class="mb-0">Category 5 — Teamwork</h4>
                    </div>

                    <div class="question-box">
                        <h6>Question 1: Employee communicates effectively with team members</h6>
                        <div class="rating-group">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <div class="rating-option">
                                    <input type="radio" name="teamwork_q1" value="<?= $i ?>" id="tw_q1_<?= $i ?>">
                                    <label for="tw_q1_<?= $i ?>"><?= $i ?></label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="question-box">
                        <h6>Question 2: Employee supports and assists colleagues when needed</h6>
                        <div class="rating-group">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <div class="rating-option">
                                    <input type="radio" name="teamwork_q2" value="<?= $i ?>" id="tw_q2_<?= $i ?>">
                                    <label for="tw_q2_<?= $i ?>"><?= $i ?></label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="question-box">
                        <h6>Question 3: Employee accepts constructive feedback positively</h6>
                        <div class="rating-group">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <div class="rating-option">
                                    <input type="radio" name="teamwork_q3" value="<?= $i ?>" id="tw_q3_<?= $i ?>">
                                    <label for="tw_q3_<?= $i ?>"><?= $i ?></label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="question-box">
                        <h6>Question 4: Employee participates actively in team activities</h6>
                        <div class="rating-group">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <div class="rating-option">
                                    <input type="radio" name="teamwork_q4" value="<?= $i ?>" id="tw_q4_<?= $i ?>">
                                    <label for="tw_q4_<?= $i ?>"><?= $i ?></label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea name="teamwork_remark" class="form-control" rows="3"
                                  placeholder="Additional comments about teamwork..."></textarea>
                    </div>
                </div>

                <!-- Category 6: Cultural Awareness -->
                <div class="category-section">
                    <div class="category-header">
                        <h4 class="mb-0">Category 6 — Cultural Awareness</h4>
                    </div>

                    <div class="question-box">
                        <h6>Question 1: Employee respects diversity and different perspectives</h6>
                        <div class="rating-group">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <div class="rating-option">
                                    <input type="radio" name="cultural_awareness_q1" value="<?= $i ?>" id="ca_q1_<?= $i ?>">
                                    <label for="ca_q1_<?= $i ?>"><?= $i ?></label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="question-box">
                        <h6>Question 2: Employee demonstrates understanding of company culture</h6>
                        <div class="rating-group">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <div class="rating-option">
                                    <input type="radio" name="cultural_awareness_q2" value="<?= $i ?>" id="ca_q2_<?= $i ?>">
                                    <label for="ca_q2_<?= $i ?>"><?= $i ?></label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="question-box">
                        <h6>Question 3: Employee adapts behavior appropriately to different situations</h6>
                        <div class="rating-group">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <div class="rating-option">
                                    <input type="radio" name="cultural_awareness_q3" value="<?= $i ?>" id="ca_q3_<?= $i ?>">
                                    <label for="ca_q3_<?= $i ?>"><?= $i ?></label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="question-box">
                        <h6>Question 4: Employee shows sensitivity to cultural differences</h6>
                        <div class="rating-group">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <div class="rating-option">
                                    <input type="radio" name="cultural_awareness_q4" value="<?= $i ?>" id="ca_q4_<?= $i ?>">
                                    <label for="ca_q4_<?= $i ?>"><?= $i ?></label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="question-box">
                        <h6>Question 5: Employee promotes inclusive behavior in the workplace</h6>
                        <div class="rating-group">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <div class="rating-option">
                                    <input type="radio" name="cultural_awareness_q5" value="<?= $i ?>" id="ca_q5_<?= $i ?>">
                                    <label for="ca_q5_<?= $i ?>"><?= $i ?></label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="question-box">
                        <h6>Question 6: Employee communicates respectfully across cultural boundaries</h6>
                        <div class="rating-group">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <div class="rating-option">
                                    <input type="radio" name="cultural_awareness_q6" value="<?= $i ?>" id="ca_q6_<?= $i ?>">
                                    <label for="ca_q6_<?= $i ?>"><?= $i ?></label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea name="cultural_awareness_remark" class="form-control" rows="3"
                                  placeholder="Additional comments about cultural awareness..."></textarea>
                    </div>
                </div>
                        </div>

                        <!-- Tab 4: Categories 7-8 -->
                        <div class="tab-pane fade" id="cat7" role="tabpanel">
                            <!-- Category 7: Safety Consciousness -->
                <div class="category-section">
                    <div class="category-header">
                        <h4 class="mb-0">Category 7 — Safety Consciousness</h4>
                    </div>

                    <div class="question-box">
                        <h6>Question 1: Employee follows all safety procedures and protocols</h6>
                        <div class="rating-group">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <div class="rating-option">
                                    <input type="radio" name="safety_consciousness_q1" value="<?= $i ?>" id="sc_q1_<?= $i ?>">
                                    <label for="sc_q1_<?= $i ?>"><?= $i ?></label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="question-box">
                        <h6>Question 2: Employee reports safety hazards promptly</h6>
                        <div class="rating-group">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <div class="rating-option">
                                    <input type="radio" name="safety_consciousness_q2" value="<?= $i ?>" id="sc_q2_<?= $i ?>">
                                    <label for="sc_q2_<?= $i ?>"><?= $i ?></label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="question-box">
                        <h6>Question 3: Employee uses protective equipment consistently</h6>
                        <div class="rating-group">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <div class="rating-option">
                                    <input type="radio" name="safety_consciousness_q3" value="<?= $i ?>" id="sc_q3_<?= $i ?>">
                                    <label for="sc_q3_<?= $i ?>"><?= $i ?></label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="question-box">
                        <h6>Question 4: Employee promotes safety awareness among colleagues</h6>
                        <div class="rating-group">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <div class="rating-option">
                                    <input type="radio" name="safety_consciousness_q4" value="<?= $i ?>" id="sc_q4_<?= $i ?>">
                                    <label for="sc_q4_<?= $i ?>"><?= $i ?></label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea name="safety_consciousness_remark" class="form-control" rows="3"
                                  placeholder="Additional comments about safety consciousness..."></textarea>
                    </div>
                </div>

                <!-- Category 8: Discipline -->
                <div class="category-section">
                    <div class="category-header">
                        <h4 class="mb-0">Category 8 — Discipline</h4>
                    </div>

                    <div class="question-box">
                        <h6>Question 1: Employee maintains punctuality and regular attendance</h6>
                        <div class="rating-group">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <div class="rating-option">
                                    <input type="radio" name="discipline_q1" value="<?= $i ?>" id="d_q1_<?= $i ?>">
                                    <label for="d_q1_<?= $i ?>"><?= $i ?></label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="question-box">
                        <h6>Question 2: Employee follows company policies and procedures</h6>
                        <div class="rating-group">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <div class="rating-option">
                                    <input type="radio" name="discipline_q2" value="<?= $i ?>" id="d_q2_<?= $i ?>">
                                    <label for="d_q2_<?= $i ?>"><?= $i ?></label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="question-box">
                        <h6>Disciplinary Actions (Check if applicable):</h6>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="discipline_q3" id="discipline_q3">
                            <label class="form-check-label" for="discipline_q3">
                                Employee has received verbal warnings during the appraisal period
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="discipline_q4" id="discipline_q4">
                            <label class="form-check-label" for="discipline_q4">
                                Employee has received written warnings during the appraisal period
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea name="discipline_remark" class="form-control" rows="3"
                                  placeholder="Additional comments about discipline..."></textarea>
                    </div>
                </div>
                        </div>

                        <!-- Tab 5: Additional Information -->
                        <div class="tab-pane fade" id="additional" role="tabpanel">
                            <!-- Additional Information Section -->
                <div class="category-section">
                    <div class="category-header">
                        <h4 class="mb-0">Additional Information</h4>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Additional Comments</label>
                        <textarea name="additional_comments" class="form-control" rows="4"
                                  placeholder="Any additional comments about the employee's performance..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Special Talents/Skills Observed</label>
                        <textarea name="special_talents" class="form-control" rows="3"
                                  placeholder="Notable skills, talents, or achievements observed..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Future Expectations/Development Areas</label>
                        <textarea name="future_expectations" class="form-control" rows="3"
                                  placeholder="Areas for development and future expectations..."></textarea>
                    </div>
                </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="category-section">
                    <div class="d-flex gap-3">
                        <button type="submit" name="action" value="draft" class="btn btn-secondary">
                            <i class="fas fa-save me-2"></i>Save as Draft
                        </button>
                        <button type="submit" name="action" value="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Submit Appraisal
                        </button>
                        
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-fill employee details when selected
        document.getElementById('appraisee_select').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];

            if (selectedOption.value) {
                document.getElementById('appraisee_name').value = selectedOption.dataset.name;
                document.getElementById('designation').value = selectedOption.dataset.designation;
                document.getElementById('department').value = selectedOption.dataset.department;
                document.getElementById('location').value = selectedOption.dataset.location;
                document.getElementById('joining_date').value = selectedOption.dataset.joining;
                document.getElementById('service_years_display').value = selectedOption.dataset.service;
                document.getElementById('service_years_numeric').value = selectedOption.dataset.serviceNumeric;

                // Load leave data if available
                const epf = selectedOption.value;
                const leaveData = <?= json_encode($leave_lookup) ?>;

                if (leaveData[epf]) {
                    document.getElementById('annual_leave').value = leaveData[epf].annual_leave_days;
                    document.getElementById('casual_leave').value = leaveData[epf].casual_leave_days;
                    document.getElementById('medical_leave').value = leaveData[epf].medical_leave_days;
                    document.getElementById('no_pay_days').value = leaveData[epf].no_pay_days;
                } else {
                    // Clear leave fields if no data
                    document.getElementById('annual_leave').value = '';
                    document.getElementById('casual_leave').value = '';
                    document.getElementById('medical_leave').value = '';
                    document.getElementById('no_pay_days').value = '';
                }
            } else {
                // Clear all fields
                document.getElementById('appraisee_name').value = '';
                document.getElementById('designation').value = '';
                document.getElementById('department').value = '';
                document.getElementById('location').value = '';
                document.getElementById('joining_date').value = '';
                document.getElementById('service_years_display').value = '';
                document.getElementById('service_years_numeric').value = '';
                document.getElementById('annual_leave').value = '';
                document.getElementById('casual_leave').value = '';
                document.getElementById('medical_leave').value = '';
                document.getElementById('no_pay_days').value = '';
            }
        });

        // Handle year selection change
        document.querySelector('[name="appraisal_year"]').addEventListener('change', function(e) {
            const selectedYear = e.target.value;
            if (selectedYear) {
                // Reload page with selected year parameter
                const url = new URL(window.location);
                url.searchParams.set('year', selectedYear);
                window.location.href = url.toString();
            }
        });

        // Form validation
        document.getElementById('appraisalForm').addEventListener('submit', function(e) {
            const appraiseeEpf = document.querySelector('[name="appraisee_epf"]').value;

            if (!appraiseeEpf) {
                e.preventDefault();
                alert('Please select an employee for appraisal.');
                return;
            }

            const action = e.submitter.value;
            if (action === 'submit') {
                if (!confirm('Are you sure you want to submit this appraisal? This action cannot be undone.')) {
                    e.preventDefault();
                }
            }
        });
    </script>
</body>
</html>