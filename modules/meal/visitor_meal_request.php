<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

// Check if user has permission to access visitor meal request
$db = new Database();
if (!hasModulePermission($db, 'meal.visitor_meal_request') && !hasAdminAccess()) {
    header('Location: ../../dashboard.php');
    exit();
}

$user = getCurrentUser();

$message = '';
$message_type = '';

// Get meal time settings for deadline checking
function checkVisitorMealDeadlines($db, $request_date, $meal_selections) {
    $current_time = date('H:i:s');
    $current_date = date('Y-m-d');
    $blocked_meals = [];

    // Get active meal time settings for visitors
    $settings_query = "SELECT * FROM meal_time_settings WHERE setting_type = 'visitor' AND is_active = 1";
    $settings = $db->fetchAll($settings_query);

    foreach ($settings as $setting) {
        $meal_name = $setting['meal_name'];
        $deadline_time = $setting['order_deadline_time'];
        $date_offset = $setting['order_deadline_date_offset'];

        // Calculate the actual deadline date
        $meal_date = new DateTime($request_date);
        $deadline_date = clone $meal_date;
        $deadline_date->sub(new DateInterval('P' . $date_offset . 'D'));
        $deadline_date_str = $deadline_date->format('Y-m-d');

        // Check if deadline has passed
        $deadline_passed = false;
        if ($current_date > $deadline_date_str) {
            $deadline_passed = true;
        } elseif ($current_date == $deadline_date_str && $current_time > $deadline_time) {
            $deadline_passed = true;
        }

        // If deadline passed and meal is selected, add to blocked list
        if ($deadline_passed && isset($meal_selections[$meal_name]) && $meal_selections[$meal_name]) {
            $blocked_meals[] = ucfirst($meal_name) . " (deadline was " . date('g:i A', strtotime($deadline_time)) . " on " . $deadline_date_str . ")";
        }
    }

    return $blocked_meals;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Get form data
        $requesting_emp_number = $_POST['requesting_emp_number'];
        $requesting_emp_name = $_POST['requesting_emp_name'];
        $requesting_emp_location = $_POST['requesting_emp_location'];
        $visitor_names = $_POST['visitor_names'] ?? '';
        $visit_purpose = $_POST['visit_purpose'] ?? '';
        $visitor_remarks = $_POST['visitor_remarks'] ?? '';

        // Validate visitor details
        if (empty($visitor_names)) {
            throw new Exception('Visitor name(s) are required.');
        }
        if (empty($visit_purpose)) {
            throw new Exception('Visit purpose is required.');
        }

        // Handle single or multiple date logic
        $request_mode = $_POST['request_mode'] ?? 'single';

        if ($request_mode === 'single') {
            // Single day processing (existing logic)
            $breakfast_needed = isset($_POST['breakfast_needed']) ? 1 : 0;
            $breakfast_menu = $_POST['breakfast_menu'] ?? '';
            $breakfast_count = (int)($_POST['breakfast_count'] ?? 0);
            $breakfast_remarks = $_POST['breakfast_remarks'] ?? '';

            $lunch_needed = isset($_POST['lunch_needed']) ? 1 : 0;
            $lunch_menu = $_POST['lunch_menu'] ?? '';
            $lunch_count = (int)($_POST['lunch_count'] ?? 0);
            $lunch_remarks = $_POST['lunch_remarks'] ?? '';

            $dinner_needed = isset($_POST['dinner_needed']) ? 1 : 0;
            $dinner_menu = $_POST['dinner_menu'] ?? '';
            $dinner_count = (int)($_POST['dinner_count'] ?? 0);
            $dinner_remarks = $_POST['dinner_remarks'] ?? '';

            $snack1_needed = isset($_POST['snack1_needed']) ? 1 : 0;
            $snack1_count = (int)($_POST['snack1_count'] ?? 0);
            $snack1_remarks = $_POST['snack1_remarks'] ?? '';

            $snack2_needed = isset($_POST['snack2_needed']) ? 1 : 0;
            $snack2_count = (int)($_POST['snack2_count'] ?? 0);
            $snack2_remarks = $_POST['snack2_remarks'] ?? '';

            // Check meal deadlines for visitor requests
            $meal_selections = [
                'breakfast' => $breakfast_needed && $breakfast_count > 0,
                'lunch' => $lunch_needed && $lunch_count > 0,
                'dinner' => $dinner_needed && $dinner_count > 0,
                'snack1' => $snack1_needed && $snack1_count > 0,
                'snack2' => $snack2_needed && $snack2_count > 0
            ];

            $blocked_meals = checkVisitorMealDeadlines($db, $_POST['single_date'], $meal_selections);
            if (!empty($blocked_meals)) {
                throw new Exception('Cannot place order - deadline has passed for: ' . implode(', ', $blocked_meals));
            }

            $visitor_requests = [[
                'date' => $_POST['single_date'],
                'breakfast_needed' => $breakfast_needed,
                'breakfast_menu' => $breakfast_menu,
                'breakfast_count' => $breakfast_count,
                'breakfast_remarks' => $breakfast_remarks,
                'lunch_needed' => $lunch_needed,
                'lunch_menu' => $lunch_menu,
                'lunch_count' => $lunch_count,
                'lunch_remarks' => $lunch_remarks,
                'dinner_needed' => $dinner_needed,
                'dinner_menu' => $dinner_menu,
                'dinner_count' => $dinner_count,
                'dinner_remarks' => $dinner_remarks,
                'snack1_needed' => $snack1_needed,
                'snack1_count' => $snack1_count,
                'snack1_remarks' => $snack1_remarks,
                'snack2_needed' => $snack2_needed,
                'snack2_count' => $snack2_count,
                'snack2_remarks' => $snack2_remarks
            ]];

        } else {
            // Multiple days processing (new logic)
            $visitor_requests = [];

            // Check if using unified visitor meal configuration
            if ($_POST['visitor_meal_config_mode'] === 'unified') {
                // Unified visitor meals - same for all days
                $unified_visitor_meals = $_POST['unified_visitor_meals'] ?? [];

                $start_date = $_POST['start_date'];
                $end_date = $_POST['end_date'];

                if (empty($start_date) || empty($end_date)) {
                    throw new Exception('Start date and end date are required for multiple day requests.');
                }

                $current_date = new DateTime($start_date);
                $end_date_obj = new DateTime($end_date);

                if ($end_date_obj < $current_date) {
                    throw new Exception('End date cannot be before start date.');
                }

                // Check if at least one meal is selected
                $has_meal_selected = isset($unified_visitor_meals['breakfast_needed']) ||
                                   isset($unified_visitor_meals['lunch_needed']) ||
                                   isset($unified_visitor_meals['dinner_needed']) ||
                                   isset($unified_visitor_meals['snack1_needed']) ||
                                   isset($unified_visitor_meals['snack2_needed']);

                if (!$has_meal_selected) {
                    throw new Exception('Please select at least one meal for the multiple day visitor request.');
                }

                while ($current_date <= $end_date_obj) {
                    $visitor_requests[] = [
                        'date' => $current_date->format('Y-m-d'),
                        'breakfast_needed' => isset($unified_visitor_meals['breakfast_needed']) ? 1 : 0,
                        'breakfast_menu' => $unified_visitor_meals['breakfast_menu'] ?? '',
                        'breakfast_count' => (int)($unified_visitor_meals['breakfast_count'] ?? 1),
                        'breakfast_remarks' => $unified_visitor_meals['breakfast_remarks'] ?? '',
                        'lunch_needed' => isset($unified_visitor_meals['lunch_needed']) ? 1 : 0,
                        'lunch_menu' => $unified_visitor_meals['lunch_menu'] ?? '',
                        'lunch_count' => (int)($unified_visitor_meals['lunch_count'] ?? 1),
                        'lunch_remarks' => $unified_visitor_meals['lunch_remarks'] ?? '',
                        'dinner_needed' => isset($unified_visitor_meals['dinner_needed']) ? 1 : 0,
                        'dinner_menu' => $unified_visitor_meals['dinner_menu'] ?? '',
                        'dinner_count' => (int)($unified_visitor_meals['dinner_count'] ?? 1),
                        'dinner_remarks' => $unified_visitor_meals['dinner_remarks'] ?? '',
                        'snack1_needed' => isset($unified_visitor_meals['snack1_needed']) ? 1 : 0,
                        'snack1_count' => (int)($unified_visitor_meals['snack1_count'] ?? 1),
                        'snack1_remarks' => '',
                        'snack2_needed' => isset($unified_visitor_meals['snack2_needed']) ? 1 : 0,
                        'snack2_count' => (int)($unified_visitor_meals['snack2_count'] ?? 1),
                        'snack2_remarks' => ''
                    ];
                    $current_date->add(new DateInterval('P1D'));
                }
            } else {
                // Individual visitor meals - different for each day
                $visitor_meals_data = $_POST['visitor_meals'] ?? [];

                foreach ($visitor_meals_data as $date => $day_meals) {
                    $visitor_requests[] = [
                        'date' => $date,
                        'breakfast_needed' => isset($day_meals['breakfast_needed']) ? 1 : 0,
                        'breakfast_menu' => $day_meals['breakfast_menu'] ?? '',
                        'breakfast_count' => (int)($day_meals['breakfast_count'] ?? 1),
                        'breakfast_remarks' => $day_meals['breakfast_remarks'] ?? '',
                        'lunch_needed' => isset($day_meals['lunch_needed']) ? 1 : 0,
                        'lunch_menu' => $day_meals['lunch_menu'] ?? '',
                        'lunch_count' => (int)($day_meals['lunch_count'] ?? 1),
                        'lunch_remarks' => $day_meals['lunch_remarks'] ?? '',
                        'dinner_needed' => isset($day_meals['dinner_needed']) ? 1 : 0,
                        'dinner_menu' => $day_meals['dinner_menu'] ?? '',
                        'dinner_count' => (int)($day_meals['dinner_count'] ?? 1),
                        'dinner_remarks' => $day_meals['dinner_remarks'] ?? '',
                        'snack1_needed' => isset($day_meals['snack1_needed']) ? 1 : 0,
                        'snack1_count' => (int)($day_meals['snack1_count'] ?? 1),
                        'snack1_remarks' => '',
                        'snack2_needed' => isset($day_meals['snack2_needed']) ? 1 : 0,
                        'snack2_count' => (int)($day_meals['snack2_count'] ?? 1),
                        'snack2_remarks' => ''
                    ];
                }
            }
        }

        // Final validation - ensure we have visitor requests to process
        if (empty($visitor_requests)) {
            throw new Exception('No valid visitor meal requests to process. Please check your form data.');
        }

        // Insert visitor meal requests for each date
        $insert_query = "INSERT INTO meal_requests_visitor (
            requesting_emp_number, requesting_emp_name, requesting_emp_location,
            visitor_names, visit_purpose, visitor_remarks, request_date,
            breakfast_needed, breakfast_menu, breakfast_count, breakfast_remarks,
            lunch_needed, lunch_menu, lunch_count, lunch_remarks,
            dinner_needed, dinner_menu, dinner_count, dinner_remarks,
            snack1_needed, snack1_count, snack1_remarks,
            snack2_needed, snack2_count, snack2_remarks,
            status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $requests_count = 0;
        foreach ($visitor_requests as $visitor_request) {
            $db->query($insert_query, [
                $requesting_emp_number, $requesting_emp_name, $requesting_emp_location,
                $visitor_names, $visit_purpose, $visitor_remarks, $visitor_request['date'],
                $visitor_request['breakfast_needed'], $visitor_request['breakfast_menu'], $visitor_request['breakfast_count'], $visitor_request['breakfast_remarks'],
                $visitor_request['lunch_needed'], $visitor_request['lunch_menu'], $visitor_request['lunch_count'], $visitor_request['lunch_remarks'],
                $visitor_request['dinner_needed'], $visitor_request['dinner_menu'], $visitor_request['dinner_count'], $visitor_request['dinner_remarks'],
                $visitor_request['snack1_needed'], $visitor_request['snack1_count'], $visitor_request['snack1_remarks'],
                $visitor_request['snack2_needed'], $visitor_request['snack2_count'], $visitor_request['snack2_remarks'],
                'pending'
            ]);
            $requests_count++;
        }

        if ($requests_count == 1) {
            $message = 'Visitor meal request submitted successfully!';
        } else {
            $message = "Visitor meal requests submitted successfully for $requests_count days!";
        }
        $message_type = 'success';


    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get menu types (from plan 1.7.5 including "Employee Meal")
$visitor_menu_types = [
    'Employee Meal',
    'Employee Special',
    'Seafood - Foreigner',
    'Chicken - Foreigner',
    'Veg - Foreigner',
    'Chicken - Local',
    'Fish - Local',
    'Veg - Local'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Visitor Meal Request</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/fontawesome.min.css" rel="stylesheet">
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

        .form-card, .content-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 2rem;
        }

        .card-header-modern {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-bottom: 1px solid #dee2e6;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem 2rem;
            margin: -2rem -2rem 2rem -2rem;
        }

        .card-header-modern h5 {
            color: #2c3e50;
            font-weight: 600;
            margin: 0;
        }

        .breadcrumb {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
        }

        /* Enhanced Visitor Meal Request Styles */
        .step-indicator {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
        }

        .step {
            flex: 1;
            text-align: center;
            position: relative;
        }

        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .step.active .step-number {
            background: var(--primary-color);
            color: white;
        }

        .step.completed .step-number {
            background: var(--success-color);
            color: white;
        }

        .step-line {
            position: absolute;
            top: 20px;
            left: 60%;
            right: -40%;
            height: 2px;
            background: #e9ecef;
        }

        .step.completed .step-line {
            background: var(--success-color);
        }

        .visitor-info-section {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .visitor-info-section .form-control,
        .visitor-info-section .form-select {
            background: rgba(255, 255, 255, 0.95);
            border: none;
        }

        .meal-card-modern {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .meal-card-modern:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .meal-card-modern.selected {
            border: 2px solid var(--primary-color);
            transform: translateY(-2px);
        }

        .meal-header-modern {
            padding: 1.5rem;
            position: relative;
        }

        .meal-header-modern::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 80%;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transform: translateX(-50%);
        }

        .meal-icon-modern {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.5rem;
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .visitor-request-mode {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }

        .mode-card {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .mode-card:hover {
            border-color: var(--primary-color);
            background: rgba(0, 123, 255, 0.05);
        }

        .mode-card.active {
            border-color: var(--primary-color);
            background: linear-gradient(135deg, rgba(0, 123, 255, 0.1), rgba(0, 123, 255, 0.05));
        }

        .mode-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        .date-selection-panel {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            padding: 2rem;
            border: 1px solid #dee2e6;
            margin-bottom: 2rem;
        }

        .quick-date-buttons {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .quick-date-btn {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 20px;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .quick-date-btn:hover {
            border-color: var(--primary-color);
            background: rgba(0, 123, 255, 0.05);
        }

        .visitor-meal-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .floating-summary {
            position: sticky;
            top: 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            padding: 1.5rem;
            margin-left: 1rem;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f1f3f4;
        }

        .summary-item:last-child {
            border-bottom: none;
        }

        .animated-checkbox {
            position: relative;
            display: inline-block;
        }

        .animated-checkbox input[type="checkbox"] {
            opacity: 0;
            position: absolute;
        }

        .checkmark {
            width: 24px;
            height: 24px;
            border: 2px solid rgba(255, 255, 255, 0.7);
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.2);
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .animated-checkbox input[type="checkbox"]:checked + .checkmark {
            background: white;
            border-color: white;
        }

        .checkmark::after {
            content: '';
            position: absolute;
            left: 7px;
            top: 3px;
            width: 6px;
            height: 12px;
            border: solid #007bff;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg) scale(0);
            transition: transform 0.2s ease;
        }

        .animated-checkbox input[type="checkbox"]:checked + .checkmark::after {
            transform: rotate(45deg) scale(1);
        }

        .visitor-details {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }

        .timeline-progress {
            background: #e9ecef;
            height: 4px;
            border-radius: 2px;
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .timeline-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--success-color));
            border-radius: 2px;
            transition: width 0.5s ease;
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

            .visitor-meal-grid {
                grid-template-columns: 1fr;
            }

            .floating-summary {
                position: static;
                margin-left: 0;
                margin-top: 2rem;
            }

            .step-indicator {
                flex-direction: column;
            }

            .step {
                margin-bottom: 1rem;
            }

            .step-line {
                display: none;
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
                        <h1><i class="fas fa-user-friends me-3"></i>Visitor Meal Request</h1>
                        <p>Submit meal requests for external visitors</p>
                    </div>
                    <div class="logo-container">
                        <img src="../../jiffy-logo.svg" alt="Jiffy Logo" class="page-header-logo">
                    </div>
                </div>
            </div>

            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Meal Management</a></li>
                    <li class="breadcrumb-item active">Visitor Meal Request</li>
                </ol>
            </nav>

        <!-- Alert Messages -->
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

            <!-- Progress Indicator -->
            <div class="step-indicator">
                <div class="step active" id="step1">
                    <div class="step-number">1</div>
                    <div class="step-title">Visitor Info</div>
                    <div class="step-line"></div>
                </div>
                <div class="step" id="step2">
                    <div class="step-number">2</div>
                    <div class="step-title">Request Type</div>
                    <div class="step-line"></div>
                </div>
                <div class="step" id="step3">
                    <div class="step-number">3</div>
                    <div class="step-title">Meal Selection</div>
                    <div class="step-line"></div>
                </div>
                <div class="step" id="step4">
                    <div class="step-number">4</div>
                    <div class="step-title">Review & Submit</div>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="timeline-progress">
                <div class="timeline-progress-bar" id="progressBar" style="width: 25%;"></div>
            </div>

            <!-- Visitor Meal Request Form -->
            <div class="row">
                <div class="col-lg-8">
                    <form method="POST" id="visitorMealRequestForm">
                        <input type="hidden" name="request_mode" id="hiddenRequestMode" value="single">
                        <input type="hidden" name="visitor_meal_config_mode" id="hiddenVisitorMealConfigMode" value="unified">

                        <!-- Visitor Information Section -->
                        <div class="visitor-info-section">
                            <div class="d-flex align-items-center mb-4">
                                <div class="meal-icon-modern">
                                    <i class="fas fa-user-friends"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Visitor Information</h6>
                                    <small class="opacity-75">Enter visitor details and purpose</small>
                                </div>
                            </div>

                            <!-- Requesting Employee Information -->
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <label class="form-label text-white">Requesting Person EPF</label>
                                    <input type="text" class="form-control" name="requesting_emp_number" value="<?php echo htmlspecialchars($user['epf_number']); ?>" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label text-white">Requesting Person Name</label>
                                    <input type="text" class="form-control" name="requesting_emp_name" value="<?php echo htmlspecialchars($user['name']); ?>" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label text-white">Location</label>
                                    <input type="text" class="form-control" name="requesting_emp_location" value="<?php echo htmlspecialchars($user['location_name'] ?? 'N/A'); ?>" readonly>
                                </div>
                            </div>

                            <!-- Visitor Details -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label text-white">Visitor Name(s) <span class="text-warning">*</span></label>
                                    <input type="text" class="form-control" name="visitor_names" placeholder="e.g. John Smith, Jane Doe" required>
                                    <small class="text-white-50">Enter visitor name(s) separated by commas</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-white">Visit Purpose <span class="text-warning">*</span></label>
                                    <select class="form-select" name="visit_purpose" required>
                                        <option value="">Select Purpose</option>
                                        <option value="Training">Training</option>
                                        <option value="Consultation">Consultation</option>
                                        <option value="Audit">Audit</option>
                                        <option value="Meeting">Meeting</option>
                                        <option value="Inspection">Inspection</option>
                                        <option value="Maintenance">Maintenance</option>
                                        <option value="Vendor Visit">Vendor Visit</option>
                                        <option value="Client Visit">Client Visit</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Additional Remarks -->
                            <div class="row">
                                <div class="col-12">
                                    <label class="form-label text-white">Additional Remarks</label>
                                    <textarea class="form-control" name="visitor_remarks" rows="3" placeholder="Organization, special dietary requirements, allergies, etc."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Request Mode Selection -->
                        <div class="visitor-request-mode">
                            <h6 class="mb-3">Choose Request Type</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mode-card active" onclick="selectRequestMode('single')" id="singleModeCard">
                                        <div class="mode-icon">
                                            <i class="fas fa-calendar-day"></i>
                                        </div>
                                        <h6 class="mb-2">Single Day</h6>
                                        <p class="text-muted mb-0">Request meals for one specific day</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mode-card" onclick="selectRequestMode('multiple')" id="multipleModeCard">
                                        <div class="mode-icon">
                                            <i class="fas fa-calendar-week"></i>
                                        </div>
                                        <h6 class="mb-2">Multiple Days</h6>
                                        <p class="text-muted mb-0">Request meals for several days</p>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" id="requestMode" value="single">
                        </div>

                            <!-- Single Date Section -->
                            <div id="singleDateSection" class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Request Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="single_date" min="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>

                            <!-- Multiple Dates Section -->
                            <div id="multipleDatesSection" class="row mb-4" style="display: none;">
                                <div class="col-md-6">
                                    <label class="form-label">Start Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="start_date" min="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">End Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="end_date" min="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                                </div>
                            </div>

                            <!-- Single Day Meal Sections for Visitors -->
                            <div id="singleDayMeals" class="row">
                                <!-- Breakfast -->
                                <div class="col-lg-6 mb-4">
                                    <div class="card bg-light">
                                        <div class="card-header bg-warning text-dark">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="breakfast_needed" name="breakfast_needed" onchange="toggleMealSection('breakfast')">
                                                <label class="form-check-label fw-bold" for="breakfast_needed">
                                                    <i class="fas fa-coffee me-2"></i>Breakfast Needed
                                                </label>
                                            </div>
                                        </div>
                                        <div class="card-body" id="breakfast_section" style="display: none;">
                                            <div class="mb-3">
                                                <label class="form-label">Breakfast Menu</label>
                                                <select class="form-select" name="breakfast_menu">
                                                    <option value="">Select Menu</option>
                                                    <?php foreach ($visitor_menu_types as $menu): ?>
                                                        <option value="<?php echo htmlspecialchars($menu); ?>"><?php echo htmlspecialchars($menu); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Count</label>
                                                <input type="number" class="form-control" name="breakfast_count" min="1" value="1">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Special Remarks</label>
                                                <textarea class="form-control" name="breakfast_remarks" rows="2" placeholder="Any special requirements..."></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Lunch -->
                                <div class="col-lg-6 mb-4">
                                    <div class="card bg-light">
                                        <div class="card-header bg-success text-white">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="lunch_needed" name="lunch_needed" onchange="toggleMealSection('lunch')">
                                                <label class="form-check-label fw-bold" for="lunch_needed">
                                                    <i class="fas fa-bowl-food me-2"></i>Lunch Needed
                                                </label>
                                            </div>
                                        </div>
                                        <div class="card-body" id="lunch_section" style="display: none;">
                                            <div class="mb-3">
                                                <label class="form-label">Lunch Menu</label>
                                                <select class="form-select" name="lunch_menu">
                                                    <option value="">Select Menu</option>
                                                    <?php foreach ($visitor_menu_types as $menu): ?>
                                                        <option value="<?php echo htmlspecialchars($menu); ?>"><?php echo htmlspecialchars($menu); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Count</label>
                                                <input type="number" class="form-control" name="lunch_count" min="1" value="1">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Special Remarks</label>
                                                <textarea class="form-control" name="lunch_remarks" rows="2" placeholder="Any special requirements..."></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Dinner -->
                                <div class="col-lg-6 mb-4">
                                    <div class="card bg-light">
                                        <div class="card-header bg-primary text-white">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="dinner_needed" name="dinner_needed" onchange="toggleMealSection('dinner')">
                                                <label class="form-check-label fw-bold" for="dinner_needed">
                                                    <i class="fas fa-utensils me-2"></i>Dinner Needed
                                                </label>
                                            </div>
                                        </div>
                                        <div class="card-body" id="dinner_section" style="display: none;">
                                            <div class="mb-3">
                                                <label class="form-label">Dinner Menu</label>
                                                <select class="form-select" name="dinner_menu">
                                                    <option value="">Select Menu</option>
                                                    <?php foreach ($visitor_menu_types as $menu): ?>
                                                        <option value="<?php echo htmlspecialchars($menu); ?>"><?php echo htmlspecialchars($menu); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Count</label>
                                                <input type="number" class="form-control" name="dinner_count" min="1" value="1">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Special Remarks</label>
                                                <textarea class="form-control" name="dinner_remarks" rows="2" placeholder="Any special requirements..."></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Snack 1 -->
                                <div class="col-lg-6 mb-4">
                                    <div class="card bg-light">
                                        <div class="card-header bg-info text-white">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="snack1_needed" name="snack1_needed" onchange="toggleMealSection('snack1')">
                                                <label class="form-check-label fw-bold" for="snack1_needed">
                                                    <i class="fas fa-cookie me-2"></i>Snack 1 Needed
                                                </label>
                                            </div>
                                        </div>
                                        <div class="card-body" id="snack1_section" style="display: none;">
                                            <div class="mb-3">
                                                <label class="form-label">Count</label>
                                                <input type="number" class="form-control" name="snack1_count" min="1" value="1">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Special Remarks</label>
                                                <textarea class="form-control" name="snack1_remarks" rows="2" placeholder="Any special requirements..."></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Snack 2 -->
                                <div class="col-lg-6 mb-4">
                                    <div class="card bg-light">
                                        <div class="card-header bg-secondary text-white">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="snack2_needed" name="snack2_needed" onchange="toggleMealSection('snack2')">
                                                <label class="form-check-label fw-bold" for="snack2_needed">
                                                    <i class="fas fa-cookie-bite me-2"></i>Snack 2 Needed
                                                </label>
                                            </div>
                                        </div>
                                        <div class="card-body" id="snack2_section" style="display: none;">
                                            <div class="mb-3">
                                                <label class="form-label">Count</label>
                                                <input type="number" class="form-control" name="snack2_count" min="1" value="1">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Special Remarks</label>
                                                <textarea class="form-control" name="snack2_remarks" rows="2" placeholder="Any special requirements..."></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Multiple Days Meal Sections -->
                            <div id="multipleDayMeals" style="display: none;">
                                <!-- Customization Options -->
                                <div class="card mb-4 border-warning">
                                    <div class="card-header bg-warning text-dark">
                                        <h6 class="mb-0"><i class="fas fa-cogs me-2"></i>Visitor Meal Configuration</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="visitorSameForAllDays" checked onchange="toggleVisitorCustomization()">
                                                    <label class="form-check-label fw-bold" for="visitorSameForAllDays">
                                                        Use same meal configuration for all days
                                                    </label>
                                                    <small class="text-muted d-block">Turn off to customize meals for individual days</small>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div id="visitorSelectedDaysCount" class="alert alert-info mb-0 py-2">
                                                    <i class="fas fa-calendar-alt me-2"></i><span id="visitorDaysCountText">0 days selected</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Unified Visitor Meal Selection -->
                                <div id="unifiedVisitorMealSelection">
                                    <!-- Standard visitor meal form will be generated here -->
                                </div>

                                <!-- Individual Day Visitor Customization -->
                                <div id="individualDayVisitorMeals" style="display: none;">
                                    <!-- Compact individual day sections will be generated here -->
                                </div>
                            </div>

                        <!-- Submit Button -->
                        <div class="text-center mt-4">
                            <?php if (hasModulePermission($db, 'meal.reset_visitor_request')): ?>
                            <button type="reset" class="btn btn-outline-secondary btn-lg me-3" onclick="resetForm()">
                                <i class="fas fa-undo me-2"></i>Reset Form
                            </button>
                            <?php endif; ?>
                            <?php if (hasModulePermission($db, 'meal.submit_visitor_request')): ?>
                            <button type="submit" class="btn btn-info btn-lg" id="submitBtn">
                                <i class="fas fa-save me-2"></i>Submit Visitor Request
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Floating Summary Sidebar -->
                <div class="col-lg-4">
                    <div class="floating-summary">
                        <div class="d-flex align-items-center mb-3">
                            <i class="fas fa-clipboard-list text-primary me-2"></i>
                            <h6 class="mb-0">Request Summary</h6>
                        </div>

                        <div id="summaryContent">
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-user-friends fa-2x mb-3"></i>
                                <p class="mb-0">Fill visitor info to see summary</p>
                            </div>
                        </div>

                        <hr>

                        <div class="summary-item">
                            <span class="fw-medium">Visitor(s):</span>
                            <span id="visitorNames" class="text-muted">Not entered</span>
                        </div>

                        <div class="summary-item">
                            <span class="fw-medium">Visit Purpose:</span>
                            <span id="visitPurpose" class="text-muted">Not selected</span>
                        </div>

                        <div class="summary-item">
                            <span class="fw-medium">Selected Date(s):</span>
                            <span id="selectedDates" class="text-muted">Not selected</span>
                        </div>

                        <div class="summary-item">
                            <span class="fw-medium">Total Meals:</span>
                            <span id="totalMeals" class="badge bg-primary">0</span>
                        </div>

                        <div class="summary-item">
                            <span class="fw-medium">Request Type:</span>
                            <span id="requestType" class="text-info">Single Day</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Modern Request Mode Selection
        function selectRequestMode(mode) {
            // Update visual selection
            document.querySelectorAll('.mode-card').forEach(card => {
                card.classList.remove('active');
            });

            if (mode === 'single') {
                document.getElementById('singleModeCard').classList.add('active');
            } else {
                document.getElementById('multipleModeCard').classList.add('active');
            }

            // Update hidden field and trigger mode change
            document.getElementById('requestMode').value = mode;
            document.getElementById('hiddenRequestMode').value = mode;
            toggleRequestMode();
            updateProgress();
            updateSummary();
        }

        // Progress Management
        function updateProgress() {
            const mode = document.getElementById('requestMode').value;
            const visitorName = document.querySelector('input[name="visitor_names"]').value;
            const visitPurpose = document.querySelector('select[name="visit_purpose"]').value;

            let progress = 25; // Step 1 always started

            // Step 1: Visitor info
            if (visitorName && visitPurpose) {
                updateStepStatus('step1', 'completed');
                progress = 50;
                updateStepStatus('step2', 'active');
            }

            // Step 2: Request type selected
            updateStepStatus('step2', 'completed');
            progress = 75;
            updateStepStatus('step3', 'active');

            // Step 3: Meals selected
            if (hasSelectedMeals()) {
                updateStepStatus('step3', 'completed');
                progress = 100;
                updateStepStatus('step4', 'active');
            }

            document.getElementById('progressBar').style.width = progress + '%';
        }

        function updateStepStatus(stepId, status) {
            const step = document.getElementById(stepId);
            step.className = 'step ' + status;
        }

        function hasSelectedMeals() {
            // Check single day meals
            if (document.getElementById('requestMode').value === 'single') {
                return ['breakfast_needed', 'lunch_needed', 'dinner_needed', 'snack1_needed', 'snack2_needed']
                    .some(id => document.getElementById(id) && document.getElementById(id).checked);
            }
            // Add multiple day check logic if needed
            return false;
        }

        // Summary Update
        function updateSummary() {
            const visitorNames = document.querySelector('input[name="visitor_names"]').value;
            const visitPurpose = document.querySelector('select[name="visit_purpose"]').value;
            const requestMode = document.getElementById('requestMode').value;

            // Update summary fields
            document.getElementById('visitorNames').textContent = visitorNames || 'Not entered';
            document.getElementById('visitPurpose').textContent = visitPurpose || 'Not selected';
            document.getElementById('requestType').textContent = requestMode === 'single' ? 'Single Day' : 'Multiple Days';

            // Update date display
            if (requestMode === 'single') {
                const singleDate = document.querySelector('input[name="single_date"]').value;
                document.getElementById('selectedDates').textContent = singleDate ? formatDate(singleDate) : 'Not selected';
            } else {
                const startDate = document.querySelector('input[name="start_date"]').value;
                const endDate = document.querySelector('input[name="end_date"]').value;
                if (startDate && endDate) {
                    document.getElementById('selectedDates').textContent = `${formatDate(startDate)} to ${formatDate(endDate)}`;
                } else {
                    document.getElementById('selectedDates').textContent = 'Not selected';
                }
            }

            // Calculate meal count
            let mealCount = 0;
            if (requestMode === 'single') {
                ['breakfast_needed', 'lunch_needed', 'dinner_needed', 'snack1_needed', 'snack2_needed'].forEach(mealId => {
                    const checkbox = document.getElementById(mealId);
                    if (checkbox && checkbox.checked) {
                        mealCount++;
                    }
                });
            }
            document.getElementById('totalMeals').textContent = mealCount;

            updateProgress();
        }

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', {
                weekday: 'short',
                month: 'short',
                day: 'numeric'
            });
        }

        function toggleRequestMode() {
            const requestMode = document.getElementById('requestMode').value;
            const singleSection = document.getElementById('singleDateSection');
            const multipleSection = document.getElementById('multipleDatesSection');
            const singleDayMeals = document.getElementById('singleDayMeals');
            const multipleDayMeals = document.getElementById('multipleDayMeals');

            // Update hidden input
            document.getElementById('hiddenRequestMode').value = requestMode;

            if (requestMode === 'single') {
                singleSection.style.display = 'block';
                multipleSection.style.display = 'none';
                singleDayMeals.style.display = 'block';
                multipleDayMeals.style.display = 'none';
                // Set required attributes
                document.querySelector('input[name="single_date"]').required = true;
                document.querySelector('input[name="start_date"]').required = false;
                document.querySelector('input[name="end_date"]').required = false;
            } else {
                singleSection.style.display = 'none';
                multipleSection.style.display = 'block';
                singleDayMeals.style.display = 'none';
                multipleDayMeals.style.display = 'block';
                // Set required attributes
                document.querySelector('input[name="single_date"]').required = false;
                document.querySelector('input[name="start_date"]').required = true;
                document.querySelector('input[name="end_date"]').required = true;

                // Generate meal sections for date range
                generateMultipleDayVisitorMeals();
            }
        }

        function generateMultipleDayVisitorMeals() {
            const startDate = document.querySelector('input[name="start_date"]').value;
            const endDate = document.querySelector('input[name="end_date"]').value;

            if (!startDate || !endDate) return;

            const start = new Date(startDate);
            const end = new Date(endDate);

            // Calculate number of days
            const daysDiff = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
            document.getElementById('visitorDaysCountText').textContent = `${daysDiff} days selected`;

            // Generate unified visitor meal form
            generateUnifiedVisitorMealForm();

            // Generate individual day visitor forms
            generateIndividualDayVisitorForms();
        }

        function generateUnifiedVisitorMealForm() {
            const unifiedVisitorMealSelection = document.getElementById('unifiedVisitorMealSelection');
            unifiedVisitorMealSelection.innerHTML = `
                <div class="card border-info">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-users me-2"></i>Visitor Meal Selection (Applied to All Days)</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Breakfast -->
                            <div class="col-lg-6 mb-3">
                                <div class="card bg-light">
                                    <div class="card-header bg-warning text-dark">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="unified_visitor_breakfast" name="unified_visitor_meals[breakfast_needed]">
                                            <label class="form-check-label fw-bold" for="unified_visitor_breakfast">
                                                <i class="fas fa-coffee me-2"></i>Breakfast Needed
                                            </label>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-2">
                                            <label class="form-label small">Menu</label>
                                            <select class="form-select form-select-sm" name="unified_visitor_meals[breakfast_menu]">
                                                <option value="">Select Menu</option>
                                                <?php foreach ($visitor_menu_types as $menu): ?>
                                                    <option value="<?php echo htmlspecialchars($menu); ?>"><?php echo htmlspecialchars($menu); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small">Count</label>
                                            <input type="number" class="form-control form-control-sm" name="unified_visitor_meals[breakfast_count]" min="1" value="1">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small">Remarks</label>
                                            <textarea class="form-control form-control-sm" name="unified_visitor_meals[breakfast_remarks]" rows="2"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Lunch -->
                            <div class="col-lg-6 mb-3">
                                <div class="card bg-light">
                                    <div class="card-header bg-success text-white">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="unified_visitor_lunch" name="unified_visitor_meals[lunch_needed]">
                                            <label class="form-check-label fw-bold" for="unified_visitor_lunch">
                                                <i class="fas fa-bowl-food me-2"></i>Lunch Needed
                                            </label>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-2">
                                            <label class="form-label small">Menu</label>
                                            <select class="form-select form-select-sm" name="unified_visitor_meals[lunch_menu]">
                                                <option value="">Select Menu</option>
                                                <?php foreach ($visitor_menu_types as $menu): ?>
                                                    <option value="<?php echo htmlspecialchars($menu); ?>"><?php echo htmlspecialchars($menu); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small">Count</label>
                                            <input type="number" class="form-control form-control-sm" name="unified_visitor_meals[lunch_count]" min="1" value="1">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small">Remarks</label>
                                            <textarea class="form-control form-control-sm" name="unified_visitor_meals[lunch_remarks]" rows="2"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Dinner -->
                            <div class="col-lg-6 mb-3">
                                <div class="card bg-light">
                                    <div class="card-header bg-primary text-white">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="unified_visitor_dinner" name="unified_visitor_meals[dinner_needed]">
                                            <label class="form-check-label fw-bold" for="unified_visitor_dinner">
                                                <i class="fas fa-utensils me-2"></i>Dinner Needed
                                            </label>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-2">
                                            <label class="form-label small">Menu</label>
                                            <select class="form-select form-select-sm" name="unified_visitor_meals[dinner_menu]">
                                                <option value="">Select Menu</option>
                                                <?php foreach ($visitor_menu_types as $menu): ?>
                                                    <option value="<?php echo htmlspecialchars($menu); ?>"><?php echo htmlspecialchars($menu); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small">Count</label>
                                            <input type="number" class="form-control form-control-sm" name="unified_visitor_meals[dinner_count]" min="1" value="1">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small">Remarks</label>
                                            <textarea class="form-control form-control-sm" name="unified_visitor_meals[dinner_remarks]" rows="2"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Snacks -->
                            <div class="col-lg-6 mb-3">
                                <div class="card bg-light">
                                    <div class="card-header bg-secondary text-white">
                                        <h6 class="mb-0"><i class="fas fa-cookie me-2"></i>Snacks</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-check mb-2">
                                            <input type="checkbox" class="form-check-input" id="unified_visitor_snack1" name="unified_visitor_meals[snack1_needed]">
                                            <label class="form-check-label" for="unified_visitor_snack1">Snack 1</label>
                                        </div>
                                        <input type="number" class="form-control form-control-sm mb-2" name="unified_visitor_meals[snack1_count]" min="1" value="1">

                                        <div class="form-check mb-2">
                                            <input type="checkbox" class="form-check-input" id="unified_visitor_snack2" name="unified_visitor_meals[snack2_needed]">
                                            <label class="form-check-label" for="unified_visitor_snack2">Snack 2</label>
                                        </div>
                                        <input type="number" class="form-control form-control-sm" name="unified_visitor_meals[snack2_count]" min="1" value="1">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        function generateIndividualDayVisitorForms() {
            const startDate = document.querySelector('input[name="start_date"]').value;
            const endDate = document.querySelector('input[name="end_date"]').value;

            if (!startDate || !endDate) return;

            const start = new Date(startDate);
            const end = new Date(endDate);
            const individualDayVisitorMeals = document.getElementById('individualDayVisitorMeals');

            // Clear existing content
            individualDayVisitorMeals.innerHTML = '';

            const currentDate = new Date(start);
            while (currentDate <= end) {
                const dateStr = currentDate.toISOString().split('T')[0];
                const dateDisplay = currentDate.toLocaleDateString('en-US', {
                    weekday: 'short',
                    month: 'short',
                    day: 'numeric'
                });

                const daySection = generateCompactDayVisitorMealSection(dateStr, dateDisplay);
                individualDayVisitorMeals.appendChild(daySection);

                currentDate.setDate(currentDate.getDate() + 1);
            }
        }

        function generateCompactDayVisitorMealSection(dateStr, dateDisplay) {
            const dayDiv = document.createElement('div');
            dayDiv.className = 'card mb-3 border-info';
            dayDiv.innerHTML = `
                <div class="card-header bg-info text-white py-2">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <h6 class="mb-0"><i class="fas fa-calendar-day me-2"></i>${dateDisplay}</h6>
                        </div>
                        <div class="col-md-9">
                            <div class="row">
                                <div class="col-2">
                                    <div class="form-check form-check-inline">
                                        <input type="checkbox" class="form-check-input" id="breakfast_${dateStr}" name="visitor_meals[${dateStr}][breakfast_needed]">
                                        <label class="form-check-label text-white small" for="breakfast_${dateStr}">Breakfast</label>
                                    </div>
                                </div>
                                <div class="col-2">
                                    <div class="form-check form-check-inline">
                                        <input type="checkbox" class="form-check-input" id="lunch_${dateStr}" name="visitor_meals[${dateStr}][lunch_needed]">
                                        <label class="form-check-label text-white small" for="lunch_${dateStr}">Lunch</label>
                                    </div>
                                </div>
                                <div class="col-2">
                                    <div class="form-check form-check-inline">
                                        <input type="checkbox" class="form-check-input" id="dinner_${dateStr}" name="visitor_meals[${dateStr}][dinner_needed]">
                                        <label class="form-check-label text-white small" for="dinner_${dateStr}">Dinner</label>
                                    </div>
                                </div>
                                <div class="col-2">
                                    <div class="form-check form-check-inline">
                                        <input type="checkbox" class="form-check-input" id="snack1_${dateStr}" name="visitor_meals[${dateStr}][snack1_needed]">
                                        <label class="form-check-label text-white small" for="snack1_${dateStr}">Snack1</label>
                                    </div>
                                </div>
                                <div class="col-2">
                                    <div class="form-check form-check-inline">
                                        <input type="checkbox" class="form-check-input" id="snack2_${dateStr}" name="visitor_meals[${dateStr}][snack2_needed]">
                                        <label class="form-check-label text-white small" for="snack2_${dateStr}">Snack2</label>
                                    </div>
                                </div>
                                <div class="col-2">
                                    <button type="button" class="btn btn-sm btn-outline-light" onclick="toggleVisitorDayDetails('${dateStr}')">
                                        <i class="fas fa-plus" id="visitor_toggle_icon_${dateStr}"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body collapse" id="visitor_details_${dateStr}">
                    <div class="row">
                        <div class="col-4">
                            <label class="form-label small">Breakfast Menu & Count</label>
                            <select class="form-select form-select-sm mb-1" name="visitor_meals[${dateStr}][breakfast_menu]">
                                <option value="">Select Menu</option>
                                <?php foreach ($visitor_menu_types as $menu): ?>
                                    <option value="<?php echo htmlspecialchars($menu); ?>"><?php echo htmlspecialchars($menu); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" class="form-control form-control-sm" name="visitor_meals[${dateStr}][breakfast_count]" min="1" value="1">
                        </div>
                        <div class="col-4">
                            <label class="form-label small">Lunch Menu & Count</label>
                            <select class="form-select form-select-sm mb-1" name="visitor_meals[${dateStr}][lunch_menu]">
                                <option value="">Select Menu</option>
                                <?php foreach ($visitor_menu_types as $menu): ?>
                                    <option value="<?php echo htmlspecialchars($menu); ?>"><?php echo htmlspecialchars($menu); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" class="form-control form-control-sm" name="visitor_meals[${dateStr}][lunch_count]" min="1" value="1">
                        </div>
                        <div class="col-4">
                            <label class="form-label small">Dinner Menu & Count</label>
                            <select class="form-select form-select-sm mb-1" name="visitor_meals[${dateStr}][dinner_menu]">
                                <option value="">Select Menu</option>
                                <?php foreach ($visitor_menu_types as $menu): ?>
                                    <option value="<?php echo htmlspecialchars($menu); ?>"><?php echo htmlspecialchars($menu); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" class="form-control form-control-sm" name="visitor_meals[${dateStr}][dinner_count]" min="1" value="1">
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-6">
                            <label class="form-label small">Snack 1 Count</label>
                            <input type="number" class="form-control form-control-sm" name="visitor_meals[${dateStr}][snack1_count]" min="1" value="1">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Snack 2 Count</label>
                            <input type="number" class="form-control form-control-sm" name="visitor_meals[${dateStr}][snack2_count]" min="1" value="1">
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-12">
                            <label class="form-label small">Special Remarks</label>
                            <textarea class="form-control form-control-sm" name="visitor_meals[${dateStr}][remarks]" rows="2" placeholder="Any special requirements for this day..."></textarea>
                        </div>
                    </div>
                </div>
            `;
            return dayDiv;
        }

        function toggleVisitorCustomization() {
            const sameForAll = document.getElementById('visitorSameForAllDays').checked;
            const unifiedSection = document.getElementById('unifiedVisitorMealSelection');
            const individualSection = document.getElementById('individualDayVisitorMeals');

            // Update hidden field to track mode
            document.getElementById('hiddenVisitorMealConfigMode').value = sameForAll ? 'unified' : 'individual';

            if (sameForAll) {
                unifiedSection.style.display = 'block';
                individualSection.style.display = 'none';
            } else {
                unifiedSection.style.display = 'none';
                individualSection.style.display = 'block';
            }
        }

        function toggleVisitorDayDetails(dateStr) {
            const details = document.getElementById(`visitor_details_${dateStr}`);
            const icon = document.getElementById(`visitor_toggle_icon_${dateStr}`);

            if (details.classList.contains('show')) {
                details.classList.remove('show');
                icon.className = 'fas fa-plus';
            } else {
                details.classList.add('show');
                icon.className = 'fas fa-minus';
            }
        }

        function generateDayVisitorMealSection(dateStr, dateDisplay) {
            const dayDiv = document.createElement('div');
            dayDiv.className = 'card mb-4 border-info';
            dayDiv.innerHTML = `
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-calendar-day me-2"></i>${dateDisplay}</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Breakfast -->
                        <div class="col-lg-6 mb-3">
                            <div class="card bg-light">
                                <div class="card-header bg-warning text-dark">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="breakfast_needed_${dateStr}" name="visitor_meals[${dateStr}][breakfast_needed]">
                                        <label class="form-check-label fw-bold" for="breakfast_needed_${dateStr}">
                                            <i class="fas fa-coffee me-2"></i>Breakfast Needed
                                        </label>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="mb-2">
                                        <label class="form-label small">Breakfast Menu</label>
                                        <select class="form-select form-select-sm" name="visitor_meals[${dateStr}][breakfast_menu]">
                                            <option value="">Select Menu</option>
                                            <?php foreach ($visitor_menu_types as $menu): ?>
                                                <option value="<?php echo htmlspecialchars($menu); ?>"><?php echo htmlspecialchars($menu); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small">Count</label>
                                        <input type="number" class="form-control form-control-sm" name="visitor_meals[${dateStr}][breakfast_count]" min="1" value="1">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small">Special Remarks</label>
                                        <textarea class="form-control form-control-sm" name="visitor_meals[${dateStr}][breakfast_remarks]" rows="2" placeholder="Any special requirements..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Lunch -->
                        <div class="col-lg-6 mb-3">
                            <div class="card bg-light">
                                <div class="card-header bg-success text-white">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="lunch_needed_${dateStr}" name="visitor_meals[${dateStr}][lunch_needed]">
                                        <label class="form-check-label fw-bold" for="lunch_needed_${dateStr}">
                                            <i class="fas fa-bowl-food me-2"></i>Lunch Needed
                                        </label>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="mb-2">
                                        <label class="form-label small">Lunch Menu</label>
                                        <select class="form-select form-select-sm" name="visitor_meals[${dateStr}][lunch_menu]">
                                            <option value="">Select Menu</option>
                                            <?php foreach ($visitor_menu_types as $menu): ?>
                                                <option value="<?php echo htmlspecialchars($menu); ?>"><?php echo htmlspecialchars($menu); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small">Count</label>
                                        <input type="number" class="form-control form-control-sm" name="visitor_meals[${dateStr}][lunch_count]" min="1" value="1">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small">Special Remarks</label>
                                        <textarea class="form-control form-control-sm" name="visitor_meals[${dateStr}][lunch_remarks]" rows="2" placeholder="Any special requirements..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dinner -->
                        <div class="col-lg-6 mb-3">
                            <div class="card bg-light">
                                <div class="card-header bg-primary text-white">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="dinner_needed_${dateStr}" name="visitor_meals[${dateStr}][dinner_needed]">
                                        <label class="form-check-label fw-bold" for="dinner_needed_${dateStr}">
                                            <i class="fas fa-utensils me-2"></i>Dinner Needed
                                        </label>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="mb-2">
                                        <label class="form-label small">Dinner Menu</label>
                                        <select class="form-select form-select-sm" name="visitor_meals[${dateStr}][dinner_menu]">
                                            <option value="">Select Menu</option>
                                            <?php foreach ($visitor_menu_types as $menu): ?>
                                                <option value="<?php echo htmlspecialchars($menu); ?>"><?php echo htmlspecialchars($menu); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small">Count</label>
                                        <input type="number" class="form-control form-control-sm" name="visitor_meals[${dateStr}][dinner_count]" min="1" value="1">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small">Special Remarks</label>
                                        <textarea class="form-control form-control-sm" name="visitor_meals[${dateStr}][dinner_remarks]" rows="2" placeholder="Any special requirements..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Snacks -->
                        <div class="col-lg-6 mb-3">
                            <div class="card bg-light">
                                <div class="card-header bg-secondary text-white">
                                    <h6 class="mb-0"><i class="fas fa-cookie me-2"></i>Snacks</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 mb-2">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="snack1_needed_${dateStr}" name="visitor_meals[${dateStr}][snack1_needed]">
                                                <label class="form-check-label small" for="snack1_needed_${dateStr}">Snack 1</label>
                                            </div>
                                            <input type="number" class="form-control form-control-sm mt-1" name="visitor_meals[${dateStr}][snack1_count]" min="1" value="1">
                                        </div>
                                        <div class="col-12 mb-2">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="snack2_needed_${dateStr}" name="visitor_meals[${dateStr}][snack2_needed]">
                                                <label class="form-check-label small" for="snack2_needed_${dateStr}">Snack 2</label>
                                            </div>
                                            <input type="number" class="form-control form-control-sm mt-1" name="visitor_meals[${dateStr}][snack2_count]" min="1" value="1">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            return dayDiv;
        }

        // Listen for date changes in multiple mode
        document.addEventListener('DOMContentLoaded', function() {
            const startDateInput = document.querySelector('input[name="start_date"]');
            const endDateInput = document.querySelector('input[name="end_date"]');

            if (startDateInput && endDateInput) {
                startDateInput.addEventListener('change', function() {
                    if (document.getElementById('requestMode').value === 'multiple') {
                        generateMultipleDayVisitorMeals();
                    }
                });

                endDateInput.addEventListener('change', function() {
                    if (document.getElementById('requestMode').value === 'multiple') {
                        generateMultipleDayVisitorMeals();
                    }
                });
            }
        });

        function toggleMealSection(mealType) {
            const checkbox = document.getElementById(mealType + '_needed');
            const section = document.getElementById(mealType + '_section');

            if (checkbox.checked) {
                section.style.display = 'block';
            } else {
                section.style.display = 'none';
            }
        }

        function resetForm() {
            // Hide all meal sections
            ['breakfast', 'lunch', 'dinner', 'snack1', 'snack2'].forEach(mealType => {
                document.getElementById(mealType + '_section').style.display = 'none';
            });
        }

        // Form validation
        document.getElementById('visitorMealRequestForm').addEventListener('submit', function(e) {
            const requestMode = document.getElementById('requestMode').value;

            // Validate date range for multiple dates
            if (requestMode === 'multiple') {
                const startDate = new Date(document.querySelector('input[name="start_date"]').value);
                const endDate = new Date(document.querySelector('input[name="end_date"]').value);

                if (endDate <= startDate) {
                    e.preventDefault();
                    alert('End date must be after start date');
                    return false;
                }

                const daysDiff = Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24));
                if (daysDiff > 30) {
                    e.preventDefault();
                    alert('Maximum 30 days can be requested at once');
                    return false;
                }
            }

            // Validate at least one meal is selected
            const checkboxes = ['breakfast_needed', 'lunch_needed', 'dinner_needed', 'snack1_needed', 'snack2_needed'];
            const isAnyChecked = checkboxes.some(id => document.getElementById(id).checked);

            if (!isAnyChecked) {
                e.preventDefault();
                alert('Please select at least one meal option for the visitor.');
                return false;
            }
        });
    </script>
</body>
</html>