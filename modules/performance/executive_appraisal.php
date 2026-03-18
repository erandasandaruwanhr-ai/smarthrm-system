<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/performance_auth.php';

// Check if user has executive appraisal permission
requirePerformancePermission('performance.executive_appraisal');

$user = getCurrentUser();
$db = new Database();

$message = '';
$error = '';

// Get active appraisal years from setup
$active_years = $db->fetchAll("
    SELECT DISTINCT appraisal_year
    FROM executive_appraisal_periods
    WHERE status = 'active'
    ORDER BY appraisal_year ASC
");

// Get selected year (prefer current year if it's active, otherwise use first active year)
$current_year = date('Y');
$default_year = null;

// First, check if current year is in active years
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

$selected_year = $_GET['year'] ?? $_POST['appraisal_year'] ?? $default_year;

// Get EPF parameter if provided
$selected_epf = $_GET['epf'] ?? '';

// Helper function to safely get POST data
function getPostValue($key, $default = '') {
    return $_POST[$key] ?? $default;
}

// If form is submitted
if ($_POST) {
    try {
        $data = [
            'appraisal_year' => getPostValue('appraisal_year'),
            'appraiser_epf' => getPerformanceUserEPF(),
            'appraiser_name' => $user['name'],
            'appraisee_epf' => getPostValue('appraisee_epf'),
            'appraisee_name' => getPostValue('appraisee_name'),
            'designation' => getPostValue('designation'),
            'department' => getPostValue('department'),
            'location' => getPostValue('location'),
            'joining_date' => getPostValue('joining_date'),
            'service_years' => is_numeric(getPostValue('service_years')) ? (float)getPostValue('service_years') : null,
            'evaluation_date' => getPostValue('evaluation_date'),

            // Category 1: Competency Evaluation
            'competency_technical_skills' => getPostValue('competency_technical_skills', null),
            'competency_technical_comments' => getPostValue('competency_technical_comments'),
            'competency_communication' => getPostValue('competency_communication', null),
            'competency_communication_comments' => getPostValue('competency_communication_comments'),
            'competency_teamwork' => getPostValue('competency_teamwork', null),
            'competency_teamwork_comments' => getPostValue('competency_teamwork_comments'),
            'competency_leadership' => getPostValue('competency_leadership', null),
            'competency_leadership_comments' => getPostValue('competency_leadership_comments'),
            'competency_problem_solving' => getPostValue('competency_problem_solving', null),
            'competency_problem_solving_comments' => getPostValue('competency_problem_solving_comments'),
            'competency_adaptability' => getPostValue('competency_adaptability', null),
            'competency_adaptability_comments' => getPostValue('competency_adaptability_comments'),
            'competency_time_management' => getPostValue('competency_time_management', null),
            'competency_time_management_comments' => getPostValue('competency_time_management_comments'),
            'competency_customer_focus' => getPostValue('competency_customer_focus', null),
            'competency_customer_focus_comments' => getPostValue('competency_customer_focus_comments'),
            'competency_remark' => getPostValue('competency_remark'),

            // Category 2: Achievements
            'achievement_1' => getPostValue('achievement_1'),
            'achievement_2' => getPostValue('achievement_2'),
            'achievement_3' => getPostValue('achievement_3'),

            // Category 3: Areas for development
            'development_competency_1' => getPostValue('development_competency_1'),
            'development_plan_1' => getPostValue('development_plan_1'),
            'development_competency_2' => getPostValue('development_competency_2'),
            'development_plan_2' => getPostValue('development_plan_2'),

            // Category 4: Core Values
            'core_values_respectful_self' => getPostValue('core_values_respectful_self', null),
            'core_values_respectful_manager' => getPostValue('core_values_respectful_manager', null),
            'core_values_passionate_self' => getPostValue('core_values_passionate_self', null),
            'core_values_passionate_manager' => getPostValue('core_values_passionate_manager', null),
            'core_values_reliable_self' => getPostValue('core_values_reliable_self', null),
            'core_values_reliable_manager' => getPostValue('core_values_reliable_manager', null),

            // Category 5: Attitudes and Behaviors
            'attitude_promises_self' => getPostValue('attitude_promises_self', null),
            'attitude_promises_manager' => getPostValue('attitude_promises_manager', null),
            'attitude_trust_self' => getPostValue('attitude_trust_self', null),
            'attitude_trust_manager' => getPostValue('attitude_trust_manager', null),
            'attitude_improvement_self' => getPostValue('attitude_improvement_self', null),
            'attitude_improvement_manager' => getPostValue('attitude_improvement_manager', null),
            'attitude_teamwork_self' => getPostValue('attitude_teamwork_self', null),
            'attitude_teamwork_manager' => getPostValue('attitude_teamwork_manager', null),
            'attitude_decisions_self' => getPostValue('attitude_decisions_self', null),
            'attitude_decisions_manager' => getPostValue('attitude_decisions_manager', null),
            'attitude_communication_self' => getPostValue('attitude_communication_self', null),
            'attitude_communication_manager' => getPostValue('attitude_communication_manager', null),
            'attitude_principles_self' => getPostValue('attitude_principles_self', null),
            'attitude_principles_manager' => getPostValue('attitude_principles_manager', null),
            'attitude_customer_self' => getPostValue('attitude_customer_self', null),
            'attitude_customer_manager' => getPostValue('attitude_customer_manager', null),

            // Category 6: Objectives
            'objective_1' => getPostValue('objective_1'),
            'objective_1_evaluation' => getPostValue('objective_1_evaluation'),
            'objective_2' => getPostValue('objective_2'),
            'objective_2_evaluation' => getPostValue('objective_2_evaluation'),
            'objective_3' => getPostValue('objective_3'),
            'objective_3_evaluation' => getPostValue('objective_3_evaluation'),

            // Category 7: Development and Training
            'development_training' => getPostValue('development_training'),

            // Category 8: Future Growth
            'future_growth' => getPostValue('future_growth'),

            // Category 9: Manager Performance Feedback
            'manager_performance_feedback' => getPostValue('manager_performance_feedback'),
            'manager_improvement_areas' => getPostValue('manager_improvement_areas'),

            // Category 10: Other Discussion Areas
            'discussion_point_1' => getPostValue('discussion_point_1'),
            'discussion_point_2' => getPostValue('discussion_point_2'),
            'discussion_point_3' => getPostValue('discussion_point_3'),

            // Category 11: Compliance Section
            'compliance_q1' => getPostValue('compliance_q1') ?: null,
            'compliance_q1_comments' => getPostValue('compliance_q1_comments'),
            'compliance_q2' => getPostValue('compliance_q2') ?: null,
            'compliance_q2_comments' => getPostValue('compliance_q2_comments'),
            'compliance_q3' => getPostValue('compliance_q3') ?: null,
            'compliance_q3_comments' => getPostValue('compliance_q3_comments'),

            'status' => getPostValue('action') === 'submit' ? 'submitted' : 'draft',
            'submitted_at' => getPostValue('action') === 'submit' ? date('Y-m-d H:i:s') : null
        ];

        // Check if appraisal already exists
        $existing = $db->fetch(
            "SELECT id FROM executive_appraisals WHERE appraisal_year = ? AND appraisee_epf = ?",
            [$data['appraisal_year'], $data['appraisee_epf']]
        );

        if ($existing) {
            // Update existing
            $db->update('executive_appraisals', $data, 'id = ?', [$existing['id']]);
            $message = 'Executive appraisal updated successfully!';
        } else {
            // Insert new
            $db->insert('executive_appraisals', $data);
            $message = 'Executive appraisal saved successfully!';
        }

    } catch (Exception $e) {
        $error = 'Error saving appraisal: ' . $e->getMessage();
    }
}

// 13.8.3: Check if logged user EPF matches supervisor EPF in executive list (13.2.8)
// Get executives that the current user can appraise (including those with draft appraisals, excluding completed ones)
$executive_members = $db->fetchAll("
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
        TIMESTAMPDIFF(YEAR, e.joined_date, CURDATE()) as service_years_numeric,
        ea.status as appraisal_status
    FROM employees e
    LEFT JOIN employment_levels el ON e.employment_level_id = el.id
    LEFT JOIN executive_appraisals ea ON (
        CONVERT(ea.appraisee_epf USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(e.epf_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
        AND ea.appraisal_year = ?
    )
    LEFT JOIN employees level1_supervisor ON CAST(e.reports_to AS CHAR) COLLATE utf8mb4_unicode_ci = CAST(level1_supervisor.epf_number AS CHAR) COLLATE utf8mb4_unicode_ci
    LEFT JOIN employees level2_supervisor ON CAST(level1_supervisor.reports_to AS CHAR) COLLATE utf8mb4_unicode_ci = CAST(level2_supervisor.epf_number AS CHAR) COLLATE utf8mb4_unicode_ci
    LEFT JOIN employees level3_supervisor ON CAST(level2_supervisor.reports_to AS CHAR) COLLATE utf8mb4_unicode_ci = CAST(level3_supervisor.epf_number AS CHAR) COLLATE utf8mb4_unicode_ci
    WHERE e.is_active = 1
    AND e.employment_level_id IN (3, 4, 5, 6, 7)
    AND (
        CASE
            WHEN level1_supervisor.employment_level_id IN (1, 2, 3) THEN level1_supervisor.epf_number
            WHEN level2_supervisor.employment_level_id IN (1, 2, 3) THEN level2_supervisor.epf_number
            WHEN level3_supervisor.employment_level_id IN (1, 2, 3) THEN level3_supervisor.epf_number
            ELSE NULL
        END = ?
    )
    ORDER BY
        CASE
            WHEN ea.status IS NULL THEN 1
            WHEN ea.status = 'draft' THEN 2
            WHEN ea.status = 'submitted' THEN 3
            WHEN ea.status = 'approved' THEN 4
            ELSE 5
        END, e.name
", [$selected_year, getPerformanceUserEPF()]); // Include draft appraisals for editing

// Get count of completed appraisals for information
$completed_count = 0;
try {
    $completed_result = $db->fetch("
        SELECT COUNT(*) as count
        FROM executive_appraisals ea
        JOIN employees e ON CONVERT(e.epf_number USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(ea.appraisee_epf USING utf8mb4) COLLATE utf8mb4_unicode_ci
        WHERE ea.appraisal_year = ?
        AND ea.status IN ('submitted', 'approved')
        AND e.employment_level_id IN (3, 4, 5, 6, 7)
        AND e.is_active = 1
        AND CONVERT(e.reports_to USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci
    ", [$selected_year, getPerformanceUserEPF()]);
    $completed_count = $completed_result['count'] ?? 0;
} catch (Exception $e) {
    // Ignore errors, just set count to 0
    error_log("Error counting completed executive appraisals: " . $e->getMessage());
}

// Load existing appraisal data if EPF is selected
$existing_appraisal = null;
$is_readonly = false;
if (!empty($selected_epf)) {
    try {
        $existing_appraisal = $db->fetch("
            SELECT *
            FROM executive_appraisals
            WHERE appraisal_year = ? AND appraisee_epf = ?
        ", [$selected_year, $selected_epf]);

        // Set readonly mode if appraisal is submitted or approved
        if ($existing_appraisal && in_array($existing_appraisal['status'], ['submitted', 'approved'])) {
            $is_readonly = true;
        }
    } catch (Exception $e) {
        error_log("Error loading existing appraisal: " . $e->getMessage());
    }
}

// Helper function to get value from existing data or empty
function getExistingValue($field, $default = '') {
    global $existing_appraisal;
    return $existing_appraisal[$field] ?? $default;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Executive Appraisal Form</title>
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
            margin-left: 280px; /* Fallback value */
            min-height: 100vh;
            position: relative;
            z-index: 1;
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
            flex-wrap: wrap;
        }

        .rating-option {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .rating-option input[type="radio"] {
            transform: scale(1.2);
        }

        /* Enhanced styling for compliance radio buttons */
        .form-check-input[type="radio"] {
            width: 20px;
            height: 20px;
            margin: 0;
            border: 2px solid #007bff;
            background-color: transparent;
            transform: scale(1.2);
            cursor: pointer;
        }

        .form-check-input[type="radio"]:checked {
            background-color: #007bff !important;
            border-color: #007bff !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='2' fill='%23fff'/%3e%3c/svg%3e") !important;
        }

        .form-check-input[type="radio"]:focus {
            border-color: #80bdff;
            outline: 0;
            box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25);
        }

        /* Compliance table styling */
        .compliance-table td.text-center {
            background-color: #f8f9fa;
            vertical-align: middle;
            padding: 12px;
            border: 1px solid #dee2e6;
        }

        .compliance-table td.text-center:hover {
            background-color: #e9ecef;
        }

        /* Form actions styling to ensure visibility */
        .category-section:last-of-type {
            margin-bottom: 3rem;
            position: relative;
            z-index: 10;
        }

        .category-section .d-flex.gap-3 {
            position: relative;
            z-index: 10;
            padding: 1rem 0;
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
            border-left: 4px solid #28a745;
        }

        .dual-rating-box {
            background: #f0f8ff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }

        .rating-scale-info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
        }

        .rating-group-inline {
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .rating-label {
            display: flex;
            align-items: center;
            gap: 3px;
            margin: 0;
            font-size: 14px;
            cursor: pointer;
        }

        .rating-label input[type="radio"] {
            margin: 0;
        }

        .card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
        }

        .readonly-form {
            background-color: #f8f9fa;
        }

        .readonly-form input,
        .readonly-form select,
        .readonly-form textarea {
            background-color: #e9ecef !important;
            color: #495057 !important;
            border-color: #ced4da !important;
            pointer-events: none;
        }

        .readonly-form .nav-tabs .nav-link {
            pointer-events: auto !important;
            cursor: pointer !important;
        }

        .readonly-form .nav-tabs {
            pointer-events: auto !important;
        }

        .readonly-banner {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
            padding: 10px 20px;
            margin-bottom: 20px;
            border-radius: 10px;
            text-align: center;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }

            .dashboard-content {
                padding: 1rem;
            }
        }

        /* Ensure submit buttons are always visible above any fixed elements */
        .btn {
            position: relative;
            z-index: 100;
        }

        /* Ensure form content stays within main-content bounds */
        #executiveAppraisalForm {
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
        }

        /* Force form actions to stay within container */
        #executiveAppraisalForm .category-section:last-of-type {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            margin-right: 0;
            padding-right: 1rem;
        }

        /* Ensure all form content aligns with main content positioning */
        .dashboard-content form {
            margin-left: 0 !important;
            padding-left: 0 !important;
            position: relative;
            width: 100%;
        }

        /* Specific fix for button container */
        .dashboard-content .d-flex.gap-3 {
            margin-left: 0 !important;
            padding-left: 0 !important;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }

        /* Reduce width and align submit button category-section to match others */
        .category-section:has(.d-flex.gap-3) {
            max-width: calc(100% - 2rem) !important;
            width: auto !important;
            margin-left: 0 !important;
            margin-right: auto !important;
        }

        /* Alternative selector for older browsers */
        .category-section:last-of-type {
            max-width: calc(100% - 2rem) !important;
            width: auto !important;
            margin-left: 0 !important;
            margin-right: auto !important;
        }

        /* Ensure consistent category section alignment across all sections */
        .dashboard-content .category-section {
            margin-left: 0 !important;
            margin-right: 0 !important;
            max-width: 100% !important;
            display: block !important;
        }

        /* Override for submit button section specifically */
        .dashboard-content .category-section:last-of-type {
            margin-left: 0 !important;
            margin-right: 0 !important;
            padding-left: 25px !important;
            padding-right: 25px !important;
            width: 100% !important;
            max-width: 100% !important;
            position: relative !important;
            left: 0 !important;
            right: 0 !important;
            box-sizing: border-box !important;
        }

        /* Absolute positioning fix to ensure buttons stay within main content */
        .main-content .category-section:last-of-type {
            position: relative !important;
            margin-left: 0 !important;
            transform: none !important;
            left: auto !important;
            right: auto !important;
        }

        /* Emergency fix - force buttons to stay within dashboard boundaries */
        .main-content form .category-section:last-of-type,
        .dashboard-content form .category-section:last-of-type {
            contain: layout !important;
            overflow: hidden !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
            padding-left: 25px !important;
            padding-right: 25px !important;
            max-width: none !important;
            width: auto !important;
            display: block !important;
            position: static !important;
        }

        /* Specific targeting for form actions container */
        .form-actions-container {
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            padding: 25px !important;
            position: relative !important;
            left: 0 !important;
            right: 0 !important;
            transform: none !important;
            display: block !important;
            box-sizing: border-box !important;
        }

        .form-buttons-wrapper {
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            position: relative !important;
            display: flex !important;
        }

        /* Nuclear option - override any positioning that might be interfering */
        .dashboard-content .form-actions-container,
        .main-content .form-actions-container,
        form .form-actions-container {
            position: static !important;
            left: auto !important;
            right: auto !important;
            top: auto !important;
            bottom: auto !important;
            transform: none !important;
            translateX: 0 !important;
            translateY: 0 !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
            float: none !important;
            clear: both !important;
        }

        /* Additional fallback for main content positioning */
        @media (min-width: 769px) {
            .main-content {
                margin-left: 280px !important;
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
                        <h1><i class="fas fa-user-tie me-3"></i>Executive Performance Appraisal</h1>
                        <p>Comprehensive executive performance evaluation and assessment form</p>
                    </div>
                    <div class="logo-container">
                        <img src="../../jiffy-logo.svg" alt="Jiffy Logo" class="page-header-logo">
                    </div>
                </div>
            </div>
            <!-- Introduction Section -->
            <div class="mb-4">
                <button type="button" class="btn btn-info btn-lg" data-bs-toggle="collapse" data-bs-target="#introductionContent" aria-expanded="false" aria-controls="introductionContent">
                    <i class="fas fa-info-circle me-2"></i>Performance Evaluation Assessment - Introduction
                    <i class="fas fa-chevron-down ms-2"></i>
                </button>

                <div class="collapse mt-3" id="introductionContent">
                    <div class="card border-info">
                        <div class="card-header bg-info text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-clipboard-list me-2"></i>Welcome to the Performance Evaluation Assessment
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="lead">
                                This document is designed to facilitate an in-depth evaluation of your performance over the past year and to identify opportunities for growth and development. Performance evaluations are crucial for personal advancement, employee motivation, and overall team success.
                            </p>
                            <p>
                                As part of this process, you will review your achievements, reflect on areas for improvement, and collaborate with your Manager/Supervisor to set clear, actionable goals for the future.
                            </p>
                            <p>
                                This assessment is to guide you through key areas, ensuring meaningful self-reflection and discussions that contribute to your professional growth.
                            </p>

                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="fas fa-question-circle me-2"></i>How to Use This Assessment</h6>
                                        </div>
                                        <div class="card-body">
                                            <ul class="mb-0">
                                                <li><strong>Reflection (Employee):</strong> Take time to consider your past performance, achievements, and challenges as you complete your sections of the assessment.</li>
                                                <li><strong>Engagement (Both):</strong> Use the evaluation as a platform for open conversation. The manager will provide feedback, and both parties can share insights.</li>
                                                <li><strong>Objective/Goal Setting (Both):</strong> Collaboratively define clear, measurable goals. The employee should outline aspirations, while the manager provides guidance and aligns goals with team or company objectives.</li>
                                                <li><strong>Future Growth (Employee):</strong> Highlight areas where you want to develop or advance within the company. The manager can offer insights or suggestions to align these aspirations with organizational needs.</li>
                                                <li><strong>Follow-Up (Manager):</strong> The manager will schedule follow-up meetings to review progress and ensure continued support for agreed-upon actions.</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="fas fa-users me-2"></i>Section Responsibilities</h6>
                                        </div>
                                        <div class="card-body">
                                            <ul class="mb-0">
                                                <li><strong>Achievements and Reflection:</strong> To be completed by the employee.</li>
                                                <li><strong>Performance Feedback and Recommendations:</strong> To be completed by the manager with employee input during the discussion.</li>
                                                <li><strong>Objectives/Goals and Development Plans:</strong> A shared section completed collaboratively.</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-success mt-4 mb-0" style="border-left: 4px solid #28a745;">
                                <h6><i class="fas fa-target me-2"></i>Goal of This Performance Evaluation</h6>
                                <p class="mb-0">
                                    The goal of this performance evaluation is to inspire future success, enhance open communication, and align your career path with organizational goals. Use this opportunity to share your aspirations, strengthen collaboration with your Manager/Supervisor, and take charge of your professional development.
                                </p>
                            </div>
                        </div>
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
                    <a href="executive_appraisal_setup.php" class="alert-link">Executive Appraisal Setup</a> before creating appraisals.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($completed_count > 0): ?>
                <div class="alert alert-info alert-dismissible fade show">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong><?= $completed_count ?></strong> executive<?= $completed_count > 1 ? 's have' : ' has' ?> already completed appraisal<?= $completed_count > 1 ? 's' : '' ?> for year <strong><?= $selected_year ?></strong> and <?= $completed_count > 1 ? 'are' : 'is' ?> not shown in the dropdown.
                    <span class="badge bg-secondary ms-2"><?= count($executive_members) ?> remaining</span>
                    <a href="executive_marks_data.php?year=<?= $selected_year ?>" class="alert-link">View completed appraisals</a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (empty($executive_members)): ?>
                <?php if ($completed_count > 0): ?>
                    <div class="alert alert-warning alert-dismissible fade show">
                        <i class="fas fa-users me-2"></i>All eligible executives have completed their appraisals for year <?= $selected_year ?>.
                        <a href="executive_marks_data.php?year=<?= $selected_year ?>" class="alert-link">View completed appraisals</a>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>No executives found for appraisal.</strong><br>
                        You can only appraise executives who report directly to you (where your EPF matches their supervisor EPF in the system).
                    </div>
                <?php endif; ?>
            <?php else: ?>

            <?php if ($is_readonly): ?>
                <div class="readonly-banner">
                    <i class="fas fa-eye me-2"></i>
                    This appraisal has been <?= ucfirst(getExistingValue('status', 'completed')) ?> and is now in read-only mode
                </div>
            <?php endif; ?>

            <form method="POST" id="executiveAppraisalForm" class="<?= $is_readonly ? 'readonly-form' : '' ?>">
                <!-- Practical Information Section -->
                <div class="category-section">
                    <div class="category-header">
                        <h4 class="mb-0"><i class="fas fa-info-circle me-2"></i>Practical Information</h4>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><strong>Name Manager:</strong></label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><strong>Select Employee:</strong></label>
                                <select name="appraisee_epf" id="appraisee_select" class="form-select" required>
                                    <option value="">Choose Executive</option>
                                    <?php if (empty($executive_members)): ?>
                                        <option value="" disabled>No executives available for appraisal</option>
                                    <?php else: ?>
                                        <?php foreach ($executive_members as $executive): ?>
                                            <?php
                                                $status_text = '';
                                                $status_class = '';
                                                if ($executive['appraisal_status'] == 'submitted') {
                                                    $status_text = ' - ✅ Submitted';
                                                    $status_class = 'text-success';
                                                } elseif ($executive['appraisal_status'] == 'approved') {
                                                    $status_text = ' - ✅ Approved';
                                                    $status_class = 'text-success';
                                                } elseif ($executive['appraisal_status'] == 'draft') {
                                                    $status_text = ' - 📝 Draft';
                                                    $status_class = 'text-warning';
                                                } else {
                                                    $status_text = ' - ⚪ Not Started';
                                                    $status_class = 'text-muted';
                                                }
                                            ?>
                                            <option value="<?= htmlspecialchars($executive['epf_number']) ?>"
                                                    data-name="<?= htmlspecialchars($executive['name']) ?>"
                                                    data-designation="<?= htmlspecialchars($executive['designation']) ?>"
                                                    data-department="<?= htmlspecialchars($executive['department']) ?>"
                                                    data-location="<?= htmlspecialchars($executive['location']) ?>"
                                                    data-joining="<?= $executive['joining_date'] ?>"
                                                    data-service="<?= $executive['service_years'] ?>"
                                                    data-service-numeric="<?= $executive['service_years_numeric'] ?>"
                                                    data-status="<?= htmlspecialchars($executive['appraisal_status'] ?? 'not_started') ?>"
                                                    class="<?= $status_class ?>"
                                                    <?= $selected_epf == $executive['epf_number'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($executive['name']) ?> (<?= htmlspecialchars($executive['epf_number']) ?>)<?= $status_text ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><strong>Name employee:</strong></label>
                                <input type="text" name="appraisee_name" id="appraisee_name" class="form-control" value="<?= htmlspecialchars(getExistingValue('appraisee_name', '')) ?>" readonly>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><strong>EPF Number:</strong></label>
                                        <input type="text" name="appraisee_epf_display" id="appraisee_epf_display" class="form-control" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><strong>Department:</strong></label>
                                        <input type="text" name="department" id="department" class="form-control" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><strong>Job title:</strong></label>
                                <input type="text" name="designation" id="designation" class="form-control" value="<?= htmlspecialchars(getExistingValue('designation', '')) ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><strong>Employed since:</strong></label>
                                <input type="date" name="joining_date" id="joining_date" class="form-control" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><strong>Period of Evaluation:</strong></label>
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
                                <label class="form-label"><strong>Date:</strong></label>
                                <input type="date" name="evaluation_date" class="form-control" value="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Hidden fields for processing -->
                    <input type="hidden" name="location" id="location">
                    <input type="hidden" name="service_years" id="service_years_numeric">
                </div>

                <!-- Rating Scale Information -->
                <div class="rating-scale-info">
                    <h6><i class="fas fa-info-circle me-2"></i>Rating Scale:</h6>
                    <div class="row text-center">
                        <div class="col">
                            <strong>5</strong><br>Outstanding: Consistently exceeds expectations
                        </div>
                        <div class="col">
                            <strong>4</strong><br>Exceeds Expectations: Often performs above requirements
                        </div>
                        <div class="col">
                            <strong>3</strong><br>Meets Expectations: Consistently meets job standards
                        </div>
                        <div class="col">
                            <strong>2</strong><br>Needs Improvement: Performance below expectations
                        </div>
                        <div class="col">
                            <strong>1</strong><br>Unsatisfactory: Performance significantly below expectations
                        </div>
                    </div>
                </div>

                <!-- Form Navigation Tabs -->
                <div class="category-section">
                    <ul class="nav nav-tabs" id="executiveAppraisalTabs" role="tablist">
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
                                Category 7-11
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content mt-4" id="executiveAppraisalTabContent">
                        <!-- Tab 1: Categories 1-2 -->
                        <div class="tab-pane fade show active" id="cat1" role="tabpanel">
                            <!-- 1 Competency Evaluation -->
                            <div class="category-section">
                                <div class="category-header">
                                    <h4 class="mb-0">Category 1 — Competency Evaluation</h4>
                                    <small>Every job has their specific competences. These are evaluated on a yearly end individual basis, looking at the relevant competences of the role, please rate your employee per competence and provide additional comments, using the rating below. The Competency skills can be adjusted where needed.</small>
                                </div>

                                <div class="alert alert-info mb-4">
                                    <div class="table-responsive">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Rating</th>
                                                    <th>Description</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr><td><strong>5</strong></td><td>Outstanding: Consistently exceeds expectations.</td></tr>
                                                <tr><td><strong>4</strong></td><td>Exceeds Expectations: Often performs above requirements.</td></tr>
                                                <tr><td><strong>3</strong></td><td>Meets Expectations: Consistently meets job standards.</td></tr>
                                                <tr><td><strong>2</strong></td><td>Needs Improvement: Performance below expectations.</td></tr>
                                                <tr><td><strong>1</strong></td><td>Unsatisfactory: Performance significantly below expectations.</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 30%;">Competency</th>
                                                <th style="width: 20%;">Rating</th>
                                                <th style="width: 50%;">Comments</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><strong>Technical Skills</strong></td>
                                                <td class="text-center">
                                                    <div class="rating-group-inline">
                                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                                            <label class="rating-label">
                                                                <input type="radio" name="competency_technical_skills" value="<?= $i ?>" <?= getExistingValue('competency_technical_skills') == $i ? 'checked' : '' ?>> <?= $i ?>
                                                            </label>
                                                        <?php endfor; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <textarea name="competency_technical_comments" class="form-control" rows="2" placeholder="Technical skills comments..."></textarea>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td><strong>Communication Skills</strong></td>
                                                <td class="text-center">
                                                    <div class="rating-group-inline">
                                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                                            <label class="rating-label">
                                                                <input type="radio" name="competency_communication" value="<?= $i ?>"> <?= $i ?>
                                                            </label>
                                                        <?php endfor; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <textarea name="competency_communication_comments" class="form-control" rows="2" placeholder="Communication skills comments..."></textarea>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td><strong>Teamwork</strong></td>
                                                <td class="text-center">
                                                    <div class="rating-group-inline">
                                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                                            <label class="rating-label">
                                                                <input type="radio" name="competency_teamwork" value="<?= $i ?>"> <?= $i ?>
                                                            </label>
                                                        <?php endfor; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <textarea name="competency_teamwork_comments" class="form-control" rows="2" placeholder="Teamwork comments..."></textarea>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td><strong>Leadership (if applicable)</strong></td>
                                                <td class="text-center">
                                                    <div class="rating-group-inline">
                                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                                            <label class="rating-label">
                                                                <input type="radio" name="competency_leadership" value="<?= $i ?>"> <?= $i ?>
                                                            </label>
                                                        <?php endfor; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <textarea name="competency_leadership_comments" class="form-control" rows="2" placeholder="Leadership comments..."></textarea>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td><strong>Problem-Solving</strong></td>
                                                <td class="text-center">
                                                    <div class="rating-group-inline">
                                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                                            <label class="rating-label">
                                                                <input type="radio" name="competency_problem_solving" value="<?= $i ?>"> <?= $i ?>
                                                            </label>
                                                        <?php endfor; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <textarea name="competency_problem_solving_comments" class="form-control" rows="2" placeholder="Problem-solving comments..."></textarea>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td><strong>Adaptability</strong></td>
                                                <td class="text-center">
                                                    <div class="rating-group-inline">
                                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                                            <label class="rating-label">
                                                                <input type="radio" name="competency_adaptability" value="<?= $i ?>"> <?= $i ?>
                                                            </label>
                                                        <?php endfor; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <textarea name="competency_adaptability_comments" class="form-control" rows="2" placeholder="Adaptability comments..."></textarea>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td><strong>Time Management</strong></td>
                                                <td class="text-center">
                                                    <div class="rating-group-inline">
                                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                                            <label class="rating-label">
                                                                <input type="radio" name="competency_time_management" value="<?= $i ?>"> <?= $i ?>
                                                            </label>
                                                        <?php endfor; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <textarea name="competency_time_management_comments" class="form-control" rows="2" placeholder="Time management comments..."></textarea>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td><strong>Customer Focus</strong></td>
                                                <td class="text-center">
                                                    <div class="rating-group-inline">
                                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                                            <label class="rating-label">
                                                                <input type="radio" name="competency_customer_focus" value="<?= $i ?>"> <?= $i ?>
                                                            </label>
                                                        <?php endfor; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <textarea name="competency_customer_focus_comments" class="form-control" rows="2" placeholder="Customer focus comments..."></textarea>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                <!-- Category 2: Achievements -->
                <div class="category-section">
                    <div class="category-header">
                        <h4 class="mb-0">Category 2 — Achievements</h4>
                        <small>Please take a moment to reflect on your key achievements or proud moments over the past period, specifically in relation to your competencies.</small>
                    </div>

                    <div class="question-box">
                        <h6>Achievement 1</h6>
                        <textarea name="achievement_1" class="form-control" rows="3"
                                  placeholder="Describe the first key achievement..."></textarea>
                    </div>

                    <div class="question-box">
                        <h6>Achievement 2</h6>
                        <textarea name="achievement_2" class="form-control" rows="3"
                                  placeholder="Describe the second key achievement..."></textarea>
                    </div>

                    <div class="question-box">
                        <h6>Achievement 3</h6>
                        <textarea name="achievement_3" class="form-control" rows="3"
                                  placeholder="Describe the third key achievement..."></textarea>
                    </div>
                </div>
                        </div>

                        <!-- Tab 2: Categories 3-4 -->
                        <div class="tab-pane fade" id="cat3" role="tabpanel">
                            <!-- Category 3: Areas for competencies development -->
                <div class="category-section">
                    <div class="category-header">
                        <h4 class="mb-0">Category 3 — Areas for Competencies Development</h4>
                        <small>Please identify key competencies that need improvement and propose actionable recommendations for development.</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="question-box">
                                <h6>Competency 1</h6>
                                <textarea name="development_competency_1" class="form-control" rows="3"
                                          placeholder="Identify competency that needs development..."></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="question-box">
                                <h6>Development Plan (for Competency 1)</h6>
                                <textarea name="development_plan_1" class="form-control" rows="3"
                                          placeholder="Actionable development plan..."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="question-box">
                                <h6>Competency 2</h6>
                                <textarea name="development_competency_2" class="form-control" rows="3"
                                          placeholder="Identify second competency that needs development..."></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="question-box">
                                <h6>Development Plan (for Competency 2)</h6>
                                <textarea name="development_plan_2" class="form-control" rows="3"
                                          placeholder="Actionable development plan for second competency..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2 Employee's Fulfillment of Values, Attitudes, and Behaviors -->
                <div class="category-section">
                    <div class="category-header">
                        <h4 class="mb-0">Category 4 — Core Values</h4>
                        <small>Evaluate the employee's adherence to key company values, attitudes, and behaviors. Rate the values, attitudes and behaviors with a score. A score of 1 is the lowest, a score of 5 is the highest.</small>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40%;">Core values</th>
                                    <th style="width: 30%;">How I rate myself</th>
                                    <th style="width: 30%;">Rating by Manager</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Respectful - We are open and inviting</strong></td>
                                    <td class="text-center">
                                        <div class="rating-group-inline">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <label class="rating-label">
                                                    <input type="radio" name="core_values_respectful_self" value="<?= $i ?>"> <?= $i ?>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="rating-group-inline">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <label class="rating-label">
                                                    <input type="radio" name="core_values_respectful_manager" value="<?= $i ?>"> <?= $i ?>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Passionate - We care</strong></td>
                                    <td class="text-center">
                                        <div class="rating-group-inline">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <label class="rating-label">
                                                    <input type="radio" name="core_values_passionate_self" value="<?= $i ?>"> <?= $i ?>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="rating-group-inline">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <label class="rating-label">
                                                    <input type="radio" name="core_values_passionate_manager" value="<?= $i ?>"> <?= $i ?>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Reliable - We deliver</strong></td>
                                    <td class="text-center">
                                        <div class="rating-group-inline">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <label class="rating-label">
                                                    <input type="radio" name="core_values_reliable_self" value="<?= $i ?>"> <?= $i ?>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="rating-group-inline">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <label class="rating-label">
                                                    <input type="radio" name="core_values_reliable_manager" value="<?= $i ?>"> <?= $i ?>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                        </div>

                        <!-- Tab 3: Categories 5-6 -->
                        <div class="tab-pane fade" id="cat5" role="tabpanel">
                            <!-- Category 5: Attitudes and Behaviors -->
                <div class="category-section">
                    <div class="category-header">
                        <h4 class="mb-0">Category 5 — Attitudes and Behaviors</h4>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40%;">Attitudes and Behaviors</th>
                                    <th style="width: 30%;">How I rate myself</th>
                                    <th style="width: 30%;">Rating by Manager</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>I do as I say and keep my promises</strong></td>
                                    <td class="text-center">
                                        <div class="rating-group-inline">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <label class="rating-label">
                                                    <input type="radio" name="attitude_promises_self" value="<?= $i ?>"> <?= $i ?>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="rating-group-inline">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <label class="rating-label">
                                                    <input type="radio" name="attitude_promises_manager" value="<?= $i ?>"> <?= $i ?>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>I trust people and am loyal to decisions made</strong></td>
                                    <td class="text-center">
                                        <div class="rating-group-inline">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <label class="rating-label">
                                                    <input type="radio" name="attitude_trust_self" value="<?= $i ?>"> <?= $i ?>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="rating-group-inline">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <label class="rating-label">
                                                    <input type="radio" name="attitude_trust_manager" value="<?= $i ?>"> <?= $i ?>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>I seek continuous improvements and innovations</strong></td>
                                    <td class="text-center">
                                        <div class="rating-group-inline">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <label class="rating-label">
                                                    <input type="radio" name="attitude_improvement_self" value="<?= $i ?>"> <?= $i ?>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="rating-group-inline">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <label class="rating-label">
                                                    <input type="radio" name="attitude_improvement_manager" value="<?= $i ?>"> <?= $i ?>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>I work together for a common goal and build relationships internally and externally</strong></td>
                                    <td class="text-center">
                                        <div class="rating-group-inline">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <label class="rating-label">
                                                    <input type="radio" name="attitude_teamwork_self" value="<?= $i ?>"> <?= $i ?>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="rating-group-inline">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <label class="rating-label">
                                                    <input type="radio" name="attitude_teamwork_manager" value="<?= $i ?>"> <?= $i ?>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>I make decisions based on facts, teamwork, and involvement</strong></td>
                                    <td class="text-center">
                                        <div class="rating-group-inline">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <label class="rating-label">
                                                    <input type="radio" name="attitude_decisions_self" value="<?= $i ?>"> <?= $i ?>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="rating-group-inline">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <label class="rating-label">
                                                    <input type="radio" name="attitude_decisions_manager" value="<?= $i ?>"> <?= $i ?>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>I communicate properly and welcome constructive feedback</strong></td>
                                    <td class="text-center">
                                        <div class="rating-group-inline">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <label class="rating-label">
                                                    <input type="radio" name="attitude_communication_self" value="<?= $i ?>"> <?= $i ?>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="rating-group-inline">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <label class="rating-label">
                                                    <input type="radio" name="attitude_communication_manager" value="<?= $i ?>"> <?= $i ?>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>I follow the working principles and share my knowledge and experience</strong></td>
                                    <td class="text-center">
                                        <div class="rating-group-inline">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <label class="rating-label">
                                                    <input type="radio" name="attitude_principles_self" value="<?= $i ?>"> <?= $i ?>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="rating-group-inline">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <label class="rating-label">
                                                    <input type="radio" name="attitude_principles_manager" value="<?= $i ?>"> <?= $i ?>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>I focus on customer satisfaction and take responsibility</strong></td>
                                    <td class="text-center">
                                        <div class="rating-group-inline">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <label class="rating-label">
                                                    <input type="radio" name="attitude_customer_self" value="<?= $i ?>"> <?= $i ?>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="rating-group-inline">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <label class="rating-label">
                                                    <input type="radio" name="attitude_customer_manager" value="<?= $i ?>"> <?= $i ?>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>




                </div>

                <!-- Category 6: Objectives planning and evaluation -->
                <div class="category-section">
                    <div class="category-header">
                        <h4 class="mb-0">Category 6 — Objectives Planning and Evaluation</h4>
                        <small>Set personal objectives using SMART criteria (Specific, Measurable, Achievable, Relevant, Time-bound)</small>
                    </div>

                    <div class="alert alert-info">
                        <p>
                            This section should be used to set your personal objectives in relation to your position. In the next performance evaluation assessment, these objectives will be evaluated by your Manager/Supervisor. In case your eligible for our bonus program, your target document will be an addendum to this performance evaluation assessment.
                        </p>

                        <h6><i class="fas fa-lightbulb me-2"></i>Note: all objectives should be formulated using the SMART criteria:</h6>
                        <ul class="mb-0">
                            <li><strong>S</strong> – Specific: Clearly define the objective, avoiding ambiguity. Focus on what needs to be achieved.<br>
                                <em>Example: "Increase sales by 10% in the North Region."</em>
                            </li>
                            <li><strong>M</strong> – Measurable: Ensure the objective can be quantified or measured to track progress.<br>
                                <em>Example: "Achieve 90% customer satisfaction in post-service surveys."</em>
                            </li>
                            <li><strong>A</strong> – Achievable: Set realistic and attainable goals considering available resources and constraints.<br>
                                <em>Example: "Train 15 employees on the new CRM software within three months."</em>
                            </li>
                            <li><strong>R</strong> – Relevant: Align the objective with broader company or team goals.<br>
                                <em>Example: "Launch a new product that meets identified customer needs and aligns with our strategic goals."</em>
                            </li>
                            <li><strong>T</strong> – Time-bound: Define a specific timeframe or deadline for achieving the goal.<br>
                                <em>Example: "Complete the project proposal by December 31, 2024."</em>
                            </li>
                        </ul>
                    </div>

                    <div class="question-box">
                        <h6>Objective 1:</h6>
                        <textarea name="objective_1" class="form-control" rows="4"
                                  placeholder="Define the first SMART objective..."></textarea>
                        <br>
                        <h6>Evaluation:</h6>
                        <textarea name="objective_1_evaluation" class="form-control" rows="3"
                                  placeholder="Evaluation of Objective 1 achievement..."><?= htmlspecialchars(getExistingValue('objective_1_evaluation', '')) ?></textarea>
                    </div>

                    <div class="question-box">
                        <h6>Objective 2:</h6>
                        <textarea name="objective_2" class="form-control" rows="4"
                                  placeholder="Define the second SMART objective..."></textarea>
                        <br>
                        <h6>Evaluation:</h6>
                        <textarea name="objective_2_evaluation" class="form-control" rows="3"
                                  placeholder="Evaluation of Objective 2 achievement..."><?= htmlspecialchars(getExistingValue('objective_2_evaluation', '')) ?></textarea>
                    </div>

                    <div class="question-box">
                        <h6>Objective 3:</h6>
                        <textarea name="objective_3" class="form-control" rows="4"
                                  placeholder="Define the third SMART objective..."></textarea>
                        <br>
                        <h6>Evaluation:</h6>
                        <textarea name="objective_3_evaluation" class="form-control" rows="3"
                                  placeholder="Evaluation of Objective 3 achievement..."><?= htmlspecialchars(getExistingValue('objective_3_evaluation', '')) ?></textarea>
                    </div>
                </div>
                        </div>

                        <!-- Tab 7: Categories 7-11 -->
                        <div class="tab-pane fade" id="cat7" role="tabpanel">
                            <!-- Category 7: Development and Training -->
                            <div class="category-section">
                                <div class="category-header">
                                    <h4 class="mb-0">Category 7 — Development and Training</h4>
                                </div>

                                <div class="alert alert-info">
                                    <p class="mb-0">
                                        This section should list specific requirements for any training or development. These activities are not restricted to training courses, and may include attachments, projects, coaching, planned experience or any other suitable activity that will enhance the skills, knowledge and behavior required in the employee's position or for his/her further development.
                                    </p>
                                </div>

                                <div class="question-box">
                                    <textarea name="development_training" class="form-control" rows="6"
                                              placeholder="List specific training and development requirements..."><?= htmlspecialchars(getExistingValue('development_training', '')) ?></textarea>
                                </div>
                            </div>

                            <!-- Category 8: Future Growth -->
                            <div class="category-section">
                                <div class="category-header">
                                    <h4 class="mb-0">Category 8 — Future Growth</h4>
                                </div>

                                <div class="alert alert-info">
                                    <p class="mb-0">
                                        This section should document any areas within the department or company where the employee expresses a particular interest in growing, contributing, or gaining additional experience.
                                    </p>
                                </div>

                                <div class="question-box">
                                    <textarea name="future_growth" class="form-control" rows="6"
                                              placeholder="Document areas of interest for growth and additional experience..."><?= htmlspecialchars(getExistingValue('future_growth', '')) ?></textarea>
                                </div>
                            </div>

                            <!-- Category 9: Feedback on Manager's/Supervisor's Performance -->
                            <div class="category-section">
                                <div class="category-header">
                                    <h4 class="mb-0">Category 9 — Feedback on Manager's/Supervisor's Performance</h4>
                                </div>

                                <div class="question-box">
                                    <p>How would you evaluate your Manager's/Supervisor's performance during this past period? Please share specific feedback on their leadership, communication, support, and overall management style.</p>
                                    <textarea name="manager_performance_feedback" class="form-control" rows="5"
                                              placeholder="Provide specific feedback on manager's performance..."><?= htmlspecialchars(getExistingValue('manager_performance_feedback', '')) ?></textarea>
                                </div>

                                <div class="question-box">
                                    <h6>Improvement areas Manager/Supervisor:</h6>
                                    <textarea name="manager_improvement_areas" class="form-control" rows="4"
                                              placeholder="Identify specific areas where your manager could improve..."><?= htmlspecialchars(getExistingValue('manager_improvement_areas', '')) ?></textarea>
                                </div>
                            </div>

                            <!-- Category 10: Other Areas of Discussion/Reflection/Feedback -->
                            <div class="category-section">
                                <div class="category-header">
                                    <h4 class="mb-0">Category 10 — Other Areas of Discussion/Reflection/Feedback Manager</h4>
                                </div>

                                <div class="alert alert-info">
                                    <p class="mb-0">
                                        This section is intended to record any additional points or topics discussed during the performance evaluation that are not covered under the specified sections above.
                                    </p>
                                </div>

                                <div class="question-box">
                                    <h6>Point 1</h6>
                                    <textarea name="discussion_point_1" class="form-control" rows="3"
                                              placeholder="Additional discussion point 1..."></textarea>
                                </div>

                                <div class="question-box">
                                    <h6>Point 2</h6>
                                    <textarea name="discussion_point_2" class="form-control" rows="3"
                                              placeholder="Additional discussion point 2..."></textarea>
                                </div>

                                <div class="question-box">
                                    <h6>Point 3</h6>
                                    <textarea name="discussion_point_3" class="form-control" rows="3"
                                              placeholder="Additional discussion point 3..."></textarea>
                                </div>
                            </div>

                            <!-- Category 11: Compliance Section -->
                            <div class="category-section">
                                <div class="category-header">
                                    <h4 class="mb-0">Category 11 — Compliance</h4>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-bordered compliance-table">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 60%;">Question</th>
                                                <th style="width: 10%;">Yes</th>
                                                <th style="width: 10%;">No</th>
                                                <th style="width: 20%;">Comments</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>Have you ever been in a situation described in the code of conduct directive that made you feel oppressed?</td>
                                                <td class="text-center">
                                                    <input type="radio" name="compliance_q1" value="yes" class="form-check-input" <?= getExistingValue('compliance_q1') == 'yes' ? 'checked' : '' ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input type="radio" name="compliance_q1" value="no" class="form-check-input" <?= getExistingValue('compliance_q1') == 'no' ? 'checked' : '' ?>>
                                                </td>
                                                <td>
                                                    <textarea name="compliance_q1_comments" class="form-control" rows="2" placeholder="Comments..."><?= htmlspecialchars(getExistingValue('compliance_q1_comments', '')) ?></textarea>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Do you know whether other employees have been in such a situation?</td>
                                                <td class="text-center">
                                                    <input type="radio" name="compliance_q2" value="yes" class="form-check-input" <?= getExistingValue('compliance_q2') == 'yes' ? 'checked' : '' ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input type="radio" name="compliance_q2" value="no" class="form-check-input" <?= getExistingValue('compliance_q2') == 'no' ? 'checked' : '' ?>>
                                                </td>
                                                <td>
                                                    <textarea name="compliance_q2_comments" class="form-control" rows="2" placeholder="Comments..."><?= htmlspecialchars(getExistingValue('compliance_q2_comments', '')) ?></textarea>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>If one of the above questions is answered "yes", is the local Compliance Manager informed about this?</td>
                                                <td class="text-center">
                                                    <input type="radio" name="compliance_q3" value="yes" class="form-check-input" <?= getExistingValue('compliance_q3') == 'yes' ? 'checked' : '' ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input type="radio" name="compliance_q3" value="no" class="form-check-input" <?= getExistingValue('compliance_q3') == 'no' ? 'checked' : '' ?>>
                                                </td>
                                                <td>
                                                    <textarea name="compliance_q3_comments" class="form-control" rows="2" placeholder="Comments..."><?= htmlspecialchars(getExistingValue('compliance_q3_comments', '')) ?></textarea>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Form Actions -->
                            <div class="category-section form-actions-container">
                                <div class="d-flex gap-3 form-buttons-wrapper">
                                    <?php if (!$is_readonly): ?>
                                        <button type="submit" name="action" value="draft" class="btn btn-secondary">
                                            <i class="fas fa-save me-2"></i>Save as Draft
                                        </button>
                                        <button type="submit" name="action" value="submit" class="btn btn-success">
                                            <i class="fas fa-paper-plane me-2"></i>Submit Executive Appraisal
                                        </button>
                                    <?php endif; ?>
                                    <a href="executive_list.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </a>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
                        </div>
                        <!-- End Tab 7: Categories 7-11 -->
                    </div>
                    <!-- End tab-content -->
                </div>
                <!-- End category-section with tabs -->

            </form>

            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-fill executive details when selected
        document.getElementById('appraisee_select')?.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];

            if (selectedOption.value) {
                // Populate basic employee info from dropdown data
                document.getElementById('appraisee_name').value = selectedOption.dataset.name;
                document.getElementById('appraisee_epf_display').value = selectedOption.value;
                document.getElementById('designation').value = selectedOption.dataset.designation;
                document.getElementById('department').value = selectedOption.dataset.department;
                document.getElementById('location').value = selectedOption.dataset.location;
                document.getElementById('joining_date').value = selectedOption.dataset.joining;
                document.getElementById('service_years_numeric').value = selectedOption.dataset.serviceNumeric;

                // Load existing appraisal data via AJAX
                loadAppraisalData(selectedOption.value);
            } else {
                // Clear all fields
                clearForm();
            }
        });

        function loadAppraisalData(epf) {
            const year = document.querySelector('[name="appraisal_year"]').value;

            fetch(`ajax/get_appraisal_data.php?year=${year}&epf=${epf}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data) {
                        // Populate form with existing appraisal data
                        populateFormData(data.data);

                        // Set readonly mode if needed
                        if (data.readonly) {
                            setReadOnlyMode(true, data.status);
                        } else {
                            setReadOnlyMode(false);
                        }
                    } else {
                        // No existing data, enable edit mode
                        setReadOnlyMode(false);
                    }
                })
                .catch(error => {
                    console.error('Error loading appraisal data:', error);
                });
        }

        function populateFormData(data) {
            // Populate rating fields (1-5 scale)
            const ratingFields = [
                'competency_technical_skills', 'competency_communication', 'competency_teamwork',
                'competency_leadership', 'competency_problem_solving', 'competency_adaptability',
                'competency_time_management', 'competency_customer_focus',
                'core_values_respectful_self', 'core_values_respectful_manager',
                'core_values_passionate_self', 'core_values_passionate_manager',
                'core_values_reliable_self', 'core_values_reliable_manager',
                'attitude_promises_self', 'attitude_promises_manager',
                'attitude_trust_self', 'attitude_trust_manager',
                'attitude_improvement_self', 'attitude_improvement_manager',
                'attitude_teamwork_self', 'attitude_teamwork_manager',
                'attitude_decisions_self', 'attitude_decisions_manager',
                'attitude_communication_self', 'attitude_communication_manager',
                'attitude_principles_self', 'attitude_principles_manager',
                'attitude_customer_self', 'attitude_customer_manager'
            ];

            ratingFields.forEach(field => {
                if (data[field]) {
                    const radio = document.querySelector(`input[name="${field}"][value="${data[field]}"]`);
                    if (radio) radio.checked = true;
                }
            });

            // Populate compliance questions (yes/no)
            // Use setTimeout to ensure DOM elements are fully rendered in all tabs
            setTimeout(() => {
                const complianceFields = ['compliance_q1', 'compliance_q2', 'compliance_q3'];
                complianceFields.forEach(field => {
                    if (data[field]) {
                        // Try multiple times to find the element in case it's in an inactive tab
                        const radio = document.querySelector(`input[name="${field}"][value="${data[field]}"]`);
                        if (radio) {
                            radio.checked = true;
                        } else {
                            console.warn(`Could not find radio button for ${field} with value ${data[field]}`);
                        }
                    }
                });
            }, 100);

            // Populate text areas and inputs
            const textFields = [
                'competency_technical_comments', 'competency_communication_comments',
                'competency_teamwork_comments', 'competency_leadership_comments',
                'competency_problem_solving_comments', 'competency_adaptability_comments',
                'competency_time_management_comments', 'competency_customer_focus_comments',
                'competency_remark', 'achievement_1', 'achievement_2', 'achievement_3',
                'development_competency_1', 'development_plan_1', 'development_competency_2',
                'development_plan_2', 'objective_1', 'objective_2', 'objective_3',
                'objective_1_evaluation', 'objective_2_evaluation', 'objective_3_evaluation',
                'development_training', 'future_growth', 'manager_performance_feedback',
                'manager_improvement_areas', 'discussion_point_1', 'discussion_point_2',
                'discussion_point_3', 'compliance_q1_comments', 'compliance_q2_comments',
                'compliance_q3_comments'
            ];

            textFields.forEach(field => {
                const element = document.querySelector(`[name="${field}"]`);
                if (element && data[field]) {
                    element.value = data[field];
                }
            });

            // Populate evaluation date
            if (data.evaluation_date) {
                const evalDate = document.querySelector('[name="evaluation_date"]');
                if (evalDate) evalDate.value = data.evaluation_date;
            }
        }

        function setReadOnlyMode(readonly, status = null) {
            const form = document.getElementById('executiveAppraisalForm');
            const submitButtons = document.querySelectorAll('button[type="submit"]');

            if (readonly) {
                form.classList.add('readonly-form');
                submitButtons.forEach(btn => btn.style.display = 'none');

                // Show readonly banner
                showReadOnlyBanner(status);
            } else {
                form.classList.remove('readonly-form');
                submitButtons.forEach(btn => btn.style.display = 'inline-block');

                // Hide readonly banner
                hideReadOnlyBanner();
            }
        }

        function showReadOnlyBanner(status) {
            let existingBanner = document.querySelector('.readonly-banner');
            if (!existingBanner) {
                const banner = document.createElement('div');
                banner.className = 'readonly-banner';
                banner.innerHTML = `<i class="fas fa-eye me-2"></i>This appraisal has been ${status || 'completed'} and is now in read-only mode`;

                const form = document.getElementById('executiveAppraisalForm');
                form.parentNode.insertBefore(banner, form);
            }
        }

        function hideReadOnlyBanner() {
            const banner = document.querySelector('.readonly-banner');
            if (banner) {
                banner.remove();
            }
        }

        function clearForm() {
            // Clear basic fields
            document.getElementById('appraisee_name').value = '';
            document.getElementById('appraisee_epf_display').value = '';
            document.getElementById('designation').value = '';
            document.getElementById('department').value = '';
            document.getElementById('location').value = '';
            document.getElementById('joining_date').value = '';
            document.getElementById('service_years_numeric').value = '';

            // Clear all radio buttons
            document.querySelectorAll('input[type="radio"]').forEach(radio => {
                radio.checked = false;
            });

            // Clear all text areas and inputs
            document.querySelectorAll('textarea, input[type="text"], input[type="date"]').forEach(field => {
                if (field.name !== 'appraisal_year') { // Don't clear the year
                    field.value = '';
                }
            });

            // Remove readonly mode
            setReadOnlyMode(false);
        }

        // Auto-load data on page load if EPF is pre-selected
        document.addEventListener('DOMContentLoaded', function() {
            const epfSelect = document.getElementById('appraisee_select');
            if (epfSelect && epfSelect.value) {
                loadAppraisalData(epfSelect.value);
            }
        });

        // Handle year selection change
        document.querySelector('[name="appraisal_year"]')?.addEventListener('change', function(e) {
            const selectedYear = e.target.value;
            if (selectedYear) {
                // Reload page with selected year parameter
                const url = new URL(window.location);
                url.searchParams.set('year', selectedYear);
                window.location.href = url.toString();
            }
        });

        // Form validation
        document.getElementById('executiveAppraisalForm')?.addEventListener('submit', function(e) {
            const appraiseeEpf = document.querySelector('[name="appraisee_epf"]').value;

            if (!appraiseeEpf) {
                e.preventDefault();
                alert('Please select an executive for appraisal.');
                return;
            }

            const action = e.submitter.value;
            if (action === 'submit') {
                if (!confirm('Are you sure you want to submit this executive appraisal? This action cannot be undone.')) {
                    e.preventDefault();
                }
            }
        });
    </script>
</body>
</html>