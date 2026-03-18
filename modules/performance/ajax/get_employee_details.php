<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

try {
    require_once '../../../config/config.php';
    require_once '../../../includes/auth_check.php';
    require_once '../../../includes/performance_auth.php';

    // Check if user has staff list access
    requirePerformancePermission('performance.staff_list');
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Setup error: ' . $e->getMessage()]);
    exit;
}

$user = getCurrentUser();
$db = new Database();

if (!isset($_GET['epf']) || empty($_GET['epf'])) {
    echo json_encode(['success' => false, 'message' => 'EPF number is required']);
    exit;
}

$epf_number = $_GET['epf'];

// Debug: Log the request
error_log("Employee details request for EPF: " . $epf_number);

try {
    // Get comprehensive employee details
    $employee = $db->fetch("
        SELECT
            e.*,
            CASE
                WHEN e.location_id = 1 THEN '7C'
                WHEN e.location_id = 2 THEN 'Pannala'
                WHEN e.location_id = 3 THEN 'Kobeigane'
                WHEN e.location_id = 4 THEN 'JECOE'
                WHEN e.location_id = 5 THEN 'Head Office'
                ELSE 'Unknown'
            END as location_name,
            supervisor.epf_number as supervisor_epf,
            supervisor.name as supervisor_name,
            supervisor.designation as supervisor_designation,
            COALESCE(el.level_name, 'Unknown Level') as employment_level_name,
            el.level_name as employment_level,
            el.id as employment_level_id
        FROM employees e
        LEFT JOIN employees supervisor ON e.reports_to = supervisor.epf_number
        LEFT JOIN employment_levels el ON e.employment_level_id = el.id
        WHERE e.epf_number = ? AND e.is_active = 1
    ", [$epf_number]);

    if (!$employee) {
        echo json_encode(['success' => false, 'message' => 'Employee not found']);
        exit;
    }

    // Get appraisal history
    $appraisal_history = $db->fetchAll("
        SELECT
            appraisal_year,
            status,
            created_at,
            appraiser_name,
            -- Calculate total score
            (COALESCE(job_knowledge_q1, 0) + COALESCE(job_knowledge_q2, 0) + COALESCE(job_knowledge_q3, 0) +
             COALESCE(creativity_q1, 0) + COALESCE(quality_awareness_q1, 0) + COALESCE(quality_awareness_q2, 0) +
             COALESCE(goal_achievement_q1, 0) + COALESCE(teamwork_q1, 0) + COALESCE(teamwork_q2, 0) +
             COALESCE(teamwork_q3, 0) + COALESCE(teamwork_q4, 0) + COALESCE(cultural_awareness_q1, 0) +
             COALESCE(cultural_awareness_q2, 0) + COALESCE(cultural_awareness_q3, 0) + COALESCE(cultural_awareness_q4, 0) +
             COALESCE(cultural_awareness_q5, 0) + COALESCE(cultural_awareness_q6, 0) + COALESCE(safety_consciousness_q1, 0) +
             COALESCE(safety_consciousness_q2, 0) + COALESCE(safety_consciousness_q3, 0) + COALESCE(safety_consciousness_q4, 0) +
             COALESCE(discipline_q1, 0) + COALESCE(discipline_q2, 0) + COALESCE(discipline_q3, 0) + COALESCE(discipline_q4, 0)) as total_score
        FROM staff_appraisals
        WHERE appraisee_epf = ?
        ORDER BY appraisal_year DESC
        LIMIT 5
    ", [$epf_number]);

    // Get leave utilization for current year
    $current_year = date('Y');
    $leave_data = $db->fetch("
        SELECT * FROM leave_utilization
        WHERE epf_number = ? AND year = ?
    ", [$epf_number, $current_year]);

    // Get subordinates count if this employee is a supervisor
    $subordinates_count = $db->fetch("
        SELECT COUNT(*) as count
        FROM employees
        WHERE reports_to = ? AND is_active = 1
    ", [$epf_number])['count'] ?? 0;

    // Get department colleagues count
    $colleagues_count = $db->fetch("
        SELECT COUNT(*) as count
        FROM employees
        WHERE department = ? AND epf_number != ? AND is_active = 1
    ", [$employee['department'], $epf_number])['count'] ?? 0;

    // Calculate service years more precisely
    $joining_date = new DateTime($employee['joined_date']);
    $current_date = new DateTime();
    $service_interval = $joining_date->diff($current_date);
    $service_years_calculated = $service_interval->y;
    $service_months = $service_interval->m;

    // Function to get performance grade
    function getPerformanceGrade($score, $maxScore) {
        if ($maxScore == 0) return ['grade' => 'N/A', 'percentage' => 0];
        $percentage = round(($score / $maxScore) * 100, 1);

        if ($percentage >= 90) $grade = 'A+';
        elseif ($percentage >= 80) $grade = 'A';
        elseif ($percentage >= 70) $grade = 'B+';
        elseif ($percentage >= 60) $grade = 'B';
        elseif ($percentage >= 50) $grade = 'C';
        else $grade = 'D';

        return ['grade' => $grade, 'percentage' => $percentage];
    }

    // Process appraisal history with grades
    foreach ($appraisal_history as &$appraisal) {
        $performance = getPerformanceGrade($appraisal['total_score'], 125);
        $appraisal['grade'] = $performance['grade'];
        $appraisal['percentage'] = $performance['percentage'];
    }

    $response = [
        'success' => true,
        'employee' => $employee,
        'appraisal_history' => $appraisal_history,
        'leave_data' => $leave_data,
        'subordinates_count' => $subordinates_count,
        'colleagues_count' => $colleagues_count,
        'service_years_calculated' => $service_years_calculated,
        'service_months' => $service_months,
        'current_year' => $current_year
    ];

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>