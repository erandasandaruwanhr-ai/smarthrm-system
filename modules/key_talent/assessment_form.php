<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

$user = getCurrentUser();
$database = new Database();

// Check if user has assessment form permission
if (!isSuperAdmin() && !hasModulePermission($database, 'key_talent.assessment_form')) {
    header('Location: index.php');
    exit();
}

// Get filter parameters (EXACT same logic as talent_candidates.php)
$manager_filter = isset($_GET['manager_epf']) ? trim($_GET['manager_epf']) : '';

// For managers, automatically filter to show only their employees
if ($user['account_type'] === 'manager') {
    $manager_filter = $user['epf_number'];
}

// Get list of managers for filter dropdown (same as talent_candidates.php)
$managers_query = "
    SELECT DISTINCT
        epf_number as manager_epf,
        name as manager_name
    FROM employees
    WHERE employment_level_id IN (2, 3)
    ORDER BY CAST(epf_number AS UNSIGNED)
";
$available_managers = $database->fetchAll($managers_query);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_assessment') {
    try {
        $form_id = (int)$_POST['form_id'];
        $employee_epf = $_POST['employee_epf'];

        // Get employee details
        $employee_query = "SELECT *, joined_date as date_of_joining, location_id as location FROM employees WHERE epf_number = ?";
        $employee = $database->fetchAll($employee_query, [$employee_epf])[0] ?? null;

        if (!$employee) {
            throw new Exception("Employee not found");
        }

        // Check for duplicate assessment
        $duplicate_check = "SELECT id FROM kti_assessments WHERE form_id = ? AND manager_epf = ? AND employee_epf = ?";
        $existing_assessment = $database->fetchAll($duplicate_check, [$form_id, $user['epf_number'], $employee_epf]);

        if (!empty($existing_assessment)) {
            throw new Exception("Assessment already completed for employee {$employee['name']} (EPF: {$employee_epf}). Each employee can only be assessed once per form. Please select a different employee or check the Marks Allocations page to view existing assessments.");
        }

        // Calculate service years (handle potential null values)
        $join_date = $employee['date_of_joining'] ?? $employee['joined_date'] ?? null;
        $service_years = 0;
        if ($join_date && $join_date !== '0000-00-00') {
            $join_timestamp = strtotime($join_date);
            if ($join_timestamp !== false) {
                $service_years = (time() - $join_timestamp) / (365.25 * 24 * 3600);
                $service_years = round($service_years, 2);
            }
        }

        // Calculate scores
        $part_a_total = 0;
        $part_b_total = 0;
        $answers_data = [];

        // Get questions for scoring
        $questions_query = "SELECT * FROM kti_questions WHERE form_id = ? ORDER BY part, question_number";
        $questions = $database->fetchAll($questions_query, [$form_id]);

        foreach ($questions as $question) {
            $answer_key = 'question_' . $question['id'];
            if (isset($_POST[$answer_key])) {
                $selected_answer = (int)$_POST[$answer_key];
                $score_percentage = $question["answer_{$selected_answer}_percentage"];

                $answers_data[] = [
                    'question_id' => $question['id'],
                    'selected_answer' => $selected_answer,
                    'score_percentage' => $score_percentage
                ];

                if ($question['part'] === 'A') {
                    $part_a_total += $score_percentage;
                } else {
                    $part_b_total += $score_percentage;
                }
            }
        }

        // Determine categories
        $performance_category = $part_a_total >= 76 ? 'High' : ($part_a_total >= 41 ? 'Moderate' : 'Low');
        $potential_category = $part_b_total >= 76 ? 'High' : ($part_b_total >= 41 ? 'Moderate' : 'Low');

        // Determine talent box category
        $talent_box_categories = [
            'High_High' => 'The Unicorn - High Potential Talent',
            'High_Moderate' => 'The Veteran - Consistent Deliverer',
            'High_Low' => 'Workhorse - Expert in Role',
            'Moderate_High' => 'The Rocket - Emerging Talent',
            'Moderate_Moderate' => 'The Backbone - Solid Citizen',
            'Moderate_Low' => 'The Settler - Limited Growth',
            'Low_High' => 'Dysfunctional Genius - Monitor & Coach',
            'Low_Moderate' => 'The Sleeping Giant - Retain & Develop',
            'Low_Low' => 'The Wake-Up Call - Performance Review'
        ];

        $talent_box_key = $performance_category . '_' . $potential_category;
        $talent_box_category = $talent_box_categories[$talent_box_key] ?? 'Unclassified';

        // Insert assessment
        $assessment_query = "INSERT INTO kti_assessments
                           (form_id, manager_epf, manager_name, employee_epf, employee_name,
                            employee_designation, employee_department, employee_location,
                            employee_joining_date, employee_service_years, part_a_total_score,
                            part_b_total_score, performance_category, potential_category,
                            talent_box_category) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $database->execute($assessment_query, [
            $form_id,
            $user['epf_number'],
            $user['name'] ?? '',
            $employee['epf_number'],
            $employee['name'] ?? '',
            $employee['designation'] ?? '',
            $employee['department'] ?? '',
            $employee['location'] ?? '',
            $join_date,
            $service_years,
            $part_a_total,
            $part_b_total,
            $performance_category,
            $potential_category,
            $talent_box_category
        ]);

        $assessment_id = $database->lastInsertId();

        // Insert individual answers
        foreach ($answers_data as $answer) {
            $answer_query = "INSERT INTO kti_assessment_answers (assessment_id, question_id, selected_answer, score_percentage) VALUES (?, ?, ?, ?)";
            $database->execute($answer_query, [
                $assessment_id, $answer['question_id'], $answer['selected_answer'], $answer['score_percentage']
            ]);
        }

        $success_message = "Assessment completed successfully! Performance: {$part_a_total}%, Potential: {$part_b_total}%";

    } catch (Exception $e) {
        $error_message = "Error submitting assessment: " . $e->getMessage();
    }
}

// Get available forms
try {
    $forms_query = "SELECT * FROM kti_forms ORDER BY year DESC";
    $forms = $database->fetchAll($forms_query);
} catch (Exception $e) {
    $forms = [];
}

// Get employees FIRST - EXACT same logic as talent_candidates.php (runs on every page load)
$employees = [];

try {
    // Get current form ID for assessment check (needed for query construction)
    $current_form_id = isset($_GET['form_id']) ? (int)$_GET['form_id'] :
                      (isset($_POST['form_id']) ? (int)$_POST['form_id'] : 0);

    // Determine which manager to check for existing assessments (needed for query construction)
    // For managers: always their own EPF
    // For superadmins: the filtered manager's EPF (if filtering), otherwise don't filter by assessment
    if ($user['account_type'] === 'manager') {
        $assessment_manager_epf = $user['epf_number'];
    } elseif ($user['account_type'] === 'superadmin' && $manager_filter) {
        $assessment_manager_epf = $manager_filter;
    } else {
        $assessment_manager_epf = null; // Don't filter by assessment for superadmin without manager filter
    }

    /*
     * RECURSIVE MANAGER HIERARCHY LOOKUP LOGIC (from talent_candidates.php):
     * 1. Start with each employee's IMMEDIATE SUPERVISOR
     * 2. Keep going up through supervisor → supervisor → supervisor...
     * 3. Stop only when it finds a manager at level 2 (GM) or level 3 (Manager)
     * 4. Handle deep hierarchies up to 10 levels deep (prevents infinite loops)
     * 5. Show the ultimate manager - not just immediate supervisor
     */
    $employees_query = "
        WITH RECURSIVE manager_hierarchy AS (
            -- Base case: start with each employee's immediate supervisor
            SELECT
                epf_number as employee_epf,
                reports_to as current_epf,
                1 as level_depth
            FROM employees
            WHERE reports_to IS NOT NULL

            UNION ALL

            -- Recursive case: go up the chain
            SELECT
                mh.employee_epf,
                e.reports_to as current_epf,
                mh.level_depth + 1
            FROM manager_hierarchy mh
            JOIN employees e ON mh.current_epf = e.epf_number
            WHERE e.employment_level_id NOT IN (2, 3)
            AND e.reports_to IS NOT NULL
            AND mh.level_depth < 10
        ),
        ultimate_managers AS (
            SELECT
                mh.employee_epf,
                e.epf_number as manager_epf,
                e.name as manager_name,
                ROW_NUMBER() OVER (PARTITION BY mh.employee_epf ORDER BY mh.level_depth) as rn
            FROM manager_hierarchy mh
            JOIN employees e ON mh.current_epf = e.epf_number
            WHERE e.employment_level_id IN (2, 3)
        )
        SELECT
            e.epf_number,
            e.name,
            e.designation,
            e.department,
            e.location_id as location,
            e.joined_date as date_of_joining,
            um.manager_epf,
            um.manager_name
        FROM employees e
        LEFT JOIN ultimate_managers um ON e.epf_number = um.employee_epf AND um.rn = 1";

    // No LEFT JOIN needed - using NOT EXISTS in WHERE clause for cleaner logic

    // Build WHERE clause (same structure as talent_candidates.php)
    $where_conditions = [];

    // Add assessment filter - exclude employees already assessed by the CURRENT MANAGER
    if ($current_form_id > 0) {
        if ($user['account_type'] === 'manager') {
            // For managers: exclude employees THEY have already assessed (using their actual EPF)
            $where_conditions[] = "NOT EXISTS (SELECT 1 FROM kti_assessments ka2 WHERE ka2.employee_epf = e.epf_number AND ka2.manager_epf = ? AND ka2.form_id = ?)";
        } elseif ($user['account_type'] === 'superadmin' && $manager_filter) {
            // For superadmins: exclude employees assessed by the FILTERED manager
            $where_conditions[] = "NOT EXISTS (SELECT 1 FROM kti_assessments ka2 WHERE ka2.employee_epf = e.epf_number AND ka2.manager_epf = ? AND ka2.form_id = ?)";
        }
    }

    if ($manager_filter) {
        $where_conditions[] = "um.manager_epf = ?";
    }

    if (!empty($where_conditions)) {
        $employees_query .= " WHERE " . implode(" AND ", $where_conditions);
    }

    $employees_query .= " ORDER BY CAST(e.epf_number AS UNSIGNED)";

    // Build parameters array
    $params = [];

    // Add assessment filtering parameters
    if ($current_form_id > 0) {
        if ($user['account_type'] === 'manager') {
            $params[] = $user['epf_number']; // manager_epf for LEFT JOIN
            $params[] = $current_form_id;     // form_id for LEFT JOIN
        } elseif ($user['account_type'] === 'superadmin' && $manager_filter) {
            $params[] = $manager_filter;      // manager_epf for LEFT JOIN
            $params[] = $current_form_id;     // form_id for LEFT JOIN
        }
    }

    // Add manager filter parameter for WHERE clause
    if ($manager_filter) {
        $params[] = $manager_filter;
    }

    // Debug logging BEFORE query execution
    error_log("=== ASSESSMENT FORM QUERY DEBUG ===");
    error_log("User: " . $user['epf_number'] . " (" . $user['account_type'] . ")");
    error_log("Manager Filter: " . ($manager_filter ?: 'None'));
    error_log("Form ID: " . $current_form_id);

    // Show which assessment filtering is being applied
    if ($current_form_id > 0) {
        if ($user['account_type'] === 'manager') {
            error_log("Assessment Filter: EXCLUDING employees already assessed by manager EPF {$user['epf_number']} in form {$current_form_id}");
        } elseif ($user['account_type'] === 'superadmin' && $manager_filter) {
            error_log("Assessment Filter: EXCLUDING employees already assessed by manager EPF {$manager_filter} in form {$current_form_id}");
        } else {
            error_log("Assessment Filter: None (superadmin without manager filter)");
        }
    } else {
        error_log("Assessment Filter: None (no form selected)");
    }
    error_log("Query: " . str_replace(["\n", "  "], [" ", " "], $employees_query));
    error_log("Params: [" . implode(", ", $params) . "]");

    $employees = $database->fetchAll($employees_query, $params);

    // Debug logging AFTER query execution
    error_log("=== RESULTS DEBUG ===");
    error_log("- Logged-in User EPF: " . $user['epf_number']);
    error_log("- User Account Type: " . $user['account_type']);
    error_log("- Assessment Manager EPF: " . ($assessment_manager_epf ?: 'None'));
    error_log("- Current Form ID: " . $current_form_id);
    error_log("- Manager Filter Applied: " . ($manager_filter ?: 'None'));
    error_log("- Found " . count($employees) . " unassessed employees");

    // Check if EPF 33 is in the results (the problematic employee)
    $epf_33_found = false;
    foreach ($employees as $emp) {
        if ($emp['epf_number'] === '33') {
            $epf_33_found = true;
            error_log("❌ PROBLEM: EPF 33 found in results - Manager: " . ($emp['manager_epf'] ?: 'None'));
            break;
        }
    }
    if (!$epf_33_found) {
        error_log("✅ Good: EPF 33 NOT in results (correctly filtered)");
    }

    if (count($employees) === 0) {
        error_log("- No employees found! Manager filter: " . $manager_filter);
    } else {
        foreach ($employees as $emp) {
            error_log("- Employee: " . $emp['epf_number'] . " (" . $emp['name'] . ") -> Manager: " . ($emp['manager_epf'] ?: 'None'));
        }
    }

    // Get total employee count for comparison (only if form is selected)
    if ($current_form_id > 0) {
        $total_employees_query = "
            WITH RECURSIVE manager_hierarchy AS (
                SELECT epf_number as employee_epf, reports_to as current_epf, 1 as level_depth
                FROM employees WHERE reports_to IS NOT NULL
                UNION ALL
                SELECT mh.employee_epf, e.reports_to as current_epf, mh.level_depth + 1
                FROM manager_hierarchy mh JOIN employees e ON mh.current_epf = e.epf_number
                WHERE e.employment_level_id NOT IN (2, 3) AND e.reports_to IS NOT NULL AND mh.level_depth < 10
            ),
            ultimate_managers AS (
                SELECT mh.employee_epf, e.epf_number as manager_epf,
                       ROW_NUMBER() OVER (PARTITION BY mh.employee_epf ORDER BY mh.level_depth) as rn
                FROM manager_hierarchy mh JOIN employees e ON mh.current_epf = e.epf_number
                WHERE e.employment_level_id IN (2, 3)
            )
            SELECT COUNT(*) as total_count FROM employees e
            LEFT JOIN ultimate_managers um ON e.epf_number = um.employee_epf AND um.rn = 1
            WHERE 1=1" . ($manager_filter ? " AND um.manager_epf = ?" : "");

        $total_params = $manager_filter ? [$manager_filter] : [];
        $total_result = $database->fetchAll($total_employees_query, $total_params);
        $total_employees = $total_result[0]['total_count'] ?? 0;
        $assessed_employees = $total_employees - count($employees);

        error_log("- Total employees under manager: " . $total_employees);
        error_log("- Already assessed: " . $assessed_employees);
        error_log("- Remaining to assess: " . count($employees));

        // Make variables available to HTML
        $GLOBALS['total_employees'] = $total_employees;
        $GLOBALS['assessed_employees'] = $assessed_employees;
        $GLOBALS['remaining_employees'] = count($employees);
    }

} catch (Exception $e) {
    error_log("Error loading employees: " . $e->getMessage());
}

// Get form details and questions
$selected_form = null;
$questions = [];

if (isset($_GET['form_id']) || isset($_POST['form_id'])) {
    $form_id = isset($_GET['form_id']) ? (int)$_GET['form_id'] : (int)$_POST['form_id'];

    try {
        // Get form details
        $form_query = "SELECT * FROM kti_forms WHERE id = ?";
        $selected_form = $database->fetchAll($form_query, [$form_id])[0] ?? null;

        if ($selected_form) {
            // Get questions
            $questions_query = "SELECT * FROM kti_questions WHERE form_id = ? ORDER BY part, question_number";
            $questions = $database->fetchAll($questions_query, [$form_id]);
        }
    } catch (Exception $e) {
        $error_message = "Error loading form: " . $e->getMessage();
    }
}

// Group questions by part
$part_a_questions = array_filter($questions, function($q) { return $q['part'] === 'A'; });
$part_b_questions = array_filter($questions, function($q) { return $q['part'] === 'B'; });
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Conduct Assessment</title>
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

        .breadcrumb {
            background: transparent;
            padding: 0;
        }

        .breadcrumb-item a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
        }

        .breadcrumb-item.active {
            color: white;
        }

        .content-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 2rem;
        }

        .form-selection-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-left: 4px solid var(--primary-color);
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .form-selection-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .assessment-section {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .section-header {
            background: linear-gradient(135deg, var(--success-color), #1e7e34);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            text-align: center;
        }

        .section-header.potential {
            background: linear-gradient(135deg, var(--info-color), #138496);
        }

        .question-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--success-color);
        }

        .question-card.potential {
            border-left-color: var(--info-color);
        }

        .answer-option {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .answer-option:hover {
            border-color: var(--primary-color);
            background: #f8f9fa;
        }

        .answer-option.selected {
            border-color: var(--success-color);
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(30, 126, 52, 0.1));
        }

        .answer-score {
            background: var(--success-color);
            color: white;
            border-radius: 20px;
            padding: 0.25rem 0.75rem;
            font-weight: 600;
            font-size: 0.8rem;
            float: right;
        }

        .progress-indicator {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            position: sticky;
            top: 20px;
            z-index: 100;
        }

        .form-control {
            border-radius: 8px;
            border: 1px solid #e1e5e9;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        .btn {
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .action-buttons .btn {
            margin-left: 0.5rem;
        }

        .filter-section {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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

            .content-card, .assessment-section {
                padding: 1rem;
            }

            .progress-indicator {
                position: relative;
                top: 0;
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
                        <h1><i class="fas fa-clipboard-check me-3"></i>Conduct Assessment</h1>
                        <p>Evaluate team members using structured Performance and Potential assessment</p>
                    </div>
                    <div class="logo-container">
                        <img src="../../jiffy-logo.svg" alt="Jiffy Logo" class="page-header-logo">
                    </div>
                </div>
            </div>

            <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Manager Filter Section (same as talent_candidates.php) -->
            <?php if ($user['account_type'] === 'superadmin'): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Manager View:</strong> Select a manager to view and assess their team members only.
            </div>
            <?php elseif ($user['account_type'] === 'manager'): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Manager View:</strong> You can assess team members under your supervision.
            </div>
            <?php endif; ?>

            <!-- Manager Filter Section (same as talent_candidates.php) -->
            <?php if ($user['account_type'] === 'superadmin'): ?>
            <div class="filter-section">
                <h5 class="mb-3"><i class="fas fa-user-tie me-2"></i>Manager Filter</h5>
                <form method="GET" class="row g-3">
                    <?php if (isset($_GET['form_id'])): ?>
                    <input type="hidden" name="form_id" value="<?php echo htmlspecialchars($_GET['form_id']); ?>">
                    <?php endif; ?>
                    <div class="col-md-4">
                        <label for="manager_epf" class="form-label">
                            <i class="fas fa-user-tie me-1"></i>Filter by Manager
                        </label>
                        <select name="manager_epf" id="manager_epf" class="form-select">
                            <option value="">All Managers</option>
                            <?php foreach ($available_managers as $manager): ?>
                                <option value="<?php echo htmlspecialchars($manager['manager_epf']); ?>"
                                        <?php echo (isset($_GET['manager_epf']) && $_GET['manager_epf'] === $manager['manager_epf']) ? 'selected' : ''; ?>>
                                    EPF <?php echo htmlspecialchars($manager['manager_epf']); ?> - <?php echo htmlspecialchars($manager['manager_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter me-1"></i>Filter
                            </button>
                            <a href="assessment_form.php<?php echo isset($_GET['form_id']) ? '?form_id=' . $_GET['form_id'] : ''; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Clear
                            </a>
                        </div>
                    </div>
                </form>

                <?php if (!empty($manager_filter)): ?>
                <div class="mt-3">
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Showing employees for Manager: <strong>EPF <?php echo htmlspecialchars($manager_filter); ?></strong>
                        <?php
                        $current_manager = array_filter($available_managers, function($m) use ($manager_filter) {
                            return $m['manager_epf'] === $manager_filter;
                        });
                        if (!empty($current_manager)) {
                            $current_manager = reset($current_manager);
                            echo " - " . htmlspecialchars($current_manager['manager_name']);
                        }
                        ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!$selected_form): ?>
            <!-- Form Selection -->
            <div class="content-card">
                <h5 class="mb-4"><i class="fas fa-list me-2"></i>Select Assessment Form</h5>

                <?php if (!empty($forms)): ?>
                <div class="row">
                    <?php foreach ($forms as $form): ?>
                    <div class="col-md-6 mb-3">
                        <div class="form-selection-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="text-primary mb-2">
                                        <i class="fas fa-calendar me-2"></i><?php echo $form['year']; ?> Assessment
                                    </h6>
                                    <p class="small text-muted mb-2">
                                        <strong>Performance:</strong> <?php echo htmlspecialchars($form['part_a_title']); ?><br>
                                        <strong>Potential:</strong> <?php echo htmlspecialchars($form['part_b_title']); ?>
                                    </p>
                                </div>
                                <a href="?form_id=<?php echo $form['id']; ?>" class="btn btn-sm btn-success">
                                    <i class="fas fa-arrow-right me-1"></i>Start
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-clipboard fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Assessment Forms Available</h5>
                    <p class="text-muted">Contact your administrator to set up assessment forms.</p>
                </div>
                <?php endif; ?>
            </div>

            <?php else: ?>
            <!-- Assessment Form -->
            <form method="POST" id="assessmentForm">
                <input type="hidden" name="action" value="submit_assessment">
                <input type="hidden" name="form_id" value="<?php echo $selected_form['id']; ?>">

                <!-- Progress Indicator -->
                <div class="progress-indicator">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?php echo $selected_form['year']; ?> Assessment</strong>
                            <small class="text-muted ms-2">Progress: <span id="progressText">0%</span></small>
                        </div>
                        <div class="progress" style="width: 200px;">
                            <div class="progress-bar" id="progressBar" style="width: 0%"></div>
                        </div>
                    </div>
                </div>

                <!-- Employee Selection -->
                <div class="content-card">
                    <h5 class="mb-3"><i class="fas fa-user me-2"></i>Select Employee to Assess</h5>

                    <?php if ($current_form_id > 0): ?>
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Assessment Progress:</strong>
                        <?php if (isset($GLOBALS['total_employees'])): ?>
                            <?php echo $GLOBALS['assessed_employees']; ?> completed,
                            <?php echo $GLOBALS['remaining_employees']; ?> remaining
                            (<?php echo $GLOBALS['total_employees']; ?> total employees)
                        <?php else: ?>
                            Only unassessed employees are shown to avoid duplicates.
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-6">
                            <label for="employee_epf" class="form-label">Employee</label>
                            <select name="employee_epf" id="employee_epf" class="form-control" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['epf_number']; ?>"
                                        data-name="<?php echo htmlspecialchars($employee['name']); ?>"
                                        data-designation="<?php echo htmlspecialchars($employee['designation']); ?>"
                                        data-department="<?php echo htmlspecialchars($employee['department']); ?>">
                                    <?php echo $employee['epf_number'] . ' - ' . htmlspecialchars($employee['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div id="employeeDetails" class="mt-3" style="display: none;">
                                <small class="text-muted">
                                    <strong>Designation:</strong> <span id="empDesignation"></span><br>
                                    <strong>Department:</strong> <span id="empDepartment"></span>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Part A - Performance Assessment -->
                <div class="assessment-section">
                    <div class="section-header">
                        <h4 class="mb-2">
                            <i class="fas fa-chart-line me-2"></i>Part A - <?php echo htmlspecialchars($selected_form['part_a_title']); ?>
                        </h4>
                        <p class="mb-0">Evaluate current job performance (Total: 100%)</p>
                    </div>

                    <?php foreach ($part_a_questions as $question): ?>
                    <div class="question-card">
                        <h6 class="mb-3">
                            Q<?php echo $question['question_number']; ?>. <?php echo htmlspecialchars($question['question_text']); ?>
                        </h6>

                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <div class="answer-option" data-question="<?php echo $question['id']; ?>" data-answer="<?php echo $i; ?>">
                            <input type="radio" name="question_<?php echo $question['id']; ?>" value="<?php echo $i; ?>"
                                   id="q<?php echo $question['id']; ?>_a<?php echo $i; ?>" class="d-none">
                            <label for="q<?php echo $question['id']; ?>_a<?php echo $i; ?>" class="w-100 cursor-pointer">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span><?php echo htmlspecialchars($question["answer_{$i}_text"]); ?></span>
                                    <span class="answer-score"><?php echo $question["answer_{$i}_percentage"]; ?>%</span>
                                </div>
                            </label>
                        </div>
                        <?php endfor; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Part B - Potential Assessment -->
                <div class="assessment-section">
                    <div class="section-header potential">
                        <h4 class="mb-2">
                            <i class="fas fa-rocket me-2"></i>Part B - <?php echo htmlspecialchars($selected_form['part_b_title']); ?>
                        </h4>
                        <p class="mb-0">Assess future leadership potential (Total: 100%)</p>
                    </div>

                    <?php foreach ($part_b_questions as $question): ?>
                    <div class="question-card potential">
                        <h6 class="mb-3">
                            Q<?php echo $question['question_number']; ?>. <?php echo htmlspecialchars($question['question_text']); ?>
                        </h6>

                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <div class="answer-option" data-question="<?php echo $question['id']; ?>" data-answer="<?php echo $i; ?>">
                            <input type="radio" name="question_<?php echo $question['id']; ?>" value="<?php echo $i; ?>"
                                   id="q<?php echo $question['id']; ?>_a<?php echo $i; ?>" class="d-none">
                            <label for="q<?php echo $question['id']; ?>_a<?php echo $i; ?>" class="w-100 cursor-pointer">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span><?php echo htmlspecialchars($question["answer_{$i}_text"]); ?></span>
                                    <span class="answer-score"><?php echo $question["answer_{$i}_percentage"]; ?>%</span>
                                </div>
                            </label>
                        </div>
                        <?php endfor; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Submit Section -->
                <div class="content-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6>Assessment Summary</h6>
                            <small class="text-muted">
                                Performance Questions: <?php echo count($part_a_questions); ?> |
                                Potential Questions: <?php echo count($part_b_questions); ?> |
                                Total Questions: <?php echo count($questions); ?>
                            </small>
                        </div>
                        <button type="submit" class="btn btn-success btn-lg" id="submitBtn" disabled>
                            <i class="fas fa-check me-2"></i>Submit Assessment
                        </button>
                    </div>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Employee selection handler
        document.getElementById('employee_epf')?.addEventListener('change', function() {
            const selected = this.selectedOptions[0];
            const details = document.getElementById('employeeDetails');
            const submitBtn = document.getElementById('submitBtn');

            if (selected.value) {
                document.getElementById('empDesignation').textContent = selected.dataset.designation;
                document.getElementById('empDepartment').textContent = selected.dataset.department;
                details.style.display = 'block';

                // Check for duplicate assessment (client-side warning)
                const formId = <?php echo $form_id ?? 0; ?>;
                const managerEpf = '<?php echo $user['epf_number']; ?>';
                const employeeEpf = selected.value;

                if (formId > 0) {
                    // Show loading state
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Checking...';
                    submitBtn.disabled = true;

                    // AJAX check for duplicate assessment
                    fetch('check_duplicate.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'form_id=' + formId + '&employee_epf=' + employeeEpf
                    })
                    .then(response => response.json())
                    .then(data => {
                        // Remove any existing warnings
                        const existingWarning = details.querySelector('.alert');
                        if (existingWarning && existingWarning.classList.contains('alert-warning', 'alert-danger')) {
                            existingWarning.remove();
                        }

                        if (data.exists) {
                            // Show error - employee already assessed
                            const errorDiv = document.createElement('div');
                            errorDiv.className = 'alert alert-danger mt-2';
                            errorDiv.innerHTML = '<i class="fas fa-times-circle me-2"></i><strong>Cannot Assess:</strong> ' + data.message + ' Please select a different employee.';
                            details.appendChild(errorDiv);

                            submitBtn.innerHTML = '<i class="fas fa-times me-2"></i>Already Assessed';
                            submitBtn.disabled = true;
                        } else {
                            // Show success - can be assessed
                            const successDiv = document.createElement('div');
                            successDiv.className = 'alert alert-success mt-2';
                            successDiv.innerHTML = '<i class="fas fa-check-circle me-2"></i><strong>Ready:</strong> This employee can be assessed.';
                            details.appendChild(successDiv);
                        }

                        updateProgress();
                    })
                    .catch(error => {
                        console.error('Error checking duplicate:', error);
                        updateProgress();
                    });
                }
            } else {
                details.style.display = 'none';
            }

            updateProgress();
        });

        // Answer selection handlers
        document.querySelectorAll('.answer-option').forEach(option => {
            option.addEventListener('click', function() {
                const questionId = this.dataset.question;
                const answerId = this.dataset.answer;

                // Remove selection from other options for this question
                document.querySelectorAll(`[data-question="${questionId}"]`).forEach(opt => {
                    opt.classList.remove('selected');
                });

                // Select this option
                this.classList.add('selected');

                // Check the radio button
                document.getElementById(`q${questionId}_a${answerId}`).checked = true;

                updateProgress();
            });
        });

        function updateProgress() {
            const totalQuestions = <?php echo count($questions); ?>;
            const answeredQuestions = document.querySelectorAll('input[type="radio"]:checked').length;
            const employeeSelected = document.getElementById('employee_epf')?.value;

            const progress = employeeSelected ?
                Math.round(((answeredQuestions / totalQuestions) * 90) + 10) :
                Math.round((answeredQuestions / totalQuestions) * 90);

            document.getElementById('progressBar').style.width = progress + '%';
            document.getElementById('progressText').textContent = progress + '%';

            // Enable submit button when all questions are answered and employee is selected
            const submitBtn = document.getElementById('submitBtn');
            const employeeDetails = document.getElementById('employeeDetails');
            const hasError = employeeDetails && employeeDetails.querySelector('.alert-danger');

            if (hasError) {
                // Don't change button state if there's a duplicate error
                return;
            }

            if (answeredQuestions === totalQuestions && employeeSelected) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-check me-2"></i>Submit Assessment';
            } else {
                submitBtn.disabled = true;
                const missing = totalQuestions - answeredQuestions;
                if (!employeeSelected) {
                    submitBtn.innerHTML = '<i class="fas fa-user me-2"></i>Select Employee First';
                } else if (missing > 0) {
                    submitBtn.innerHTML = `<i class="fas fa-clock me-2"></i>${missing} Questions Remaining`;
                }
            }
        }

        // Form submission handler
        document.getElementById('assessmentForm')?.addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
            submitBtn.disabled = true;
        });

        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        // Initialize progress
        updateProgress();
    </script>
</body>
</html>