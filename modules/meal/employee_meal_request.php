<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

$message = '';
$message_type = '';

// Get meal time settings for deadline checking
function checkMealDeadlines($db, $request_date, $meal_selections) {
    $current_time = date('H:i:s');
    $current_date = date('Y-m-d');
    $blocked_meals = [];

    // Get active meal time settings for employees
    $settings_query = "SELECT * FROM meal_time_settings WHERE setting_type = 'employee' AND is_active = 1";
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
        $emp_number = $_POST['emp_number'];
        $emp_name = $_POST['emp_name'];
        $emp_location = $_POST['emp_location'];
        $meal_type = $_POST['meal_type'];

        // Handle single or multiple date logic
        $request_mode = $_POST['request_mode'] ?? 'single';

        if ($request_mode === 'single') {
            // Single day processing (existing logic)
            $dates_to_process = [$_POST['single_date']];

            // Breakfast
            $breakfast = isset($_POST['breakfast']) ? 1 : 0;
            $breakfast_count = $breakfast ? 1 : 0;
            $breakfast_countx = isset($_POST['breakfast_countx']) ? (int)$_POST['breakfast_countx'] : 0;

            // Snack 1
            $snack1_countx = isset($_POST['snack1_countx']) ? (int)$_POST['snack1_countx'] : 0;

            // Lunch
            $lunch = isset($_POST['lunch']) ? 1 : 0;
            $lunch_count = $lunch ? 1 : 0;
            $lunch_countx = isset($_POST['lunch_countx']) ? (int)$_POST['lunch_countx'] : 0;

            // Snack 2
            $snack2_count = isset($_POST['snack2_count']) ? (int)$_POST['snack2_count'] : 0;
            $snack2 = $snack2_count > 0 ? 1 : 0;
            $snack2_countx = 0; // No longer using extra count

            // Dinner
            $dinner = isset($_POST['dinner']) ? 1 : 0;
            $dinner_count = $dinner ? 1 : 0;
            $dinner_countx = isset($_POST['dinner_countx']) ? (int)$_POST['dinner_countx'] : 0;

            // Snack 3
            $snack3_countx = isset($_POST['snack3_countx']) ? (int)$_POST['snack3_countx'] : 0;

            // Validate that at least one meal is selected for single day
            $total_meals = $breakfast_count + $breakfast_countx + $snack1_countx +
                          $lunch_count + $lunch_countx + $snack2_count + $snack2_countx +
                          $dinner_count + $dinner_countx + $snack3_countx;

            if ($total_meals <= 0) {
                throw new Exception('Please select at least one meal for your request.');
            }

            // Check meal deadlines
            $meal_selections = [
                'breakfast' => $breakfast || $breakfast_countx > 0,
                'snack1' => $snack1_countx > 0,
                'lunch' => $lunch || $lunch_countx > 0,
                'snack2' => $snack2_count > 0,
                'dinner' => $dinner || $dinner_countx > 0,
                'snack3' => $snack3_countx > 0
            ];

            $blocked_meals = checkMealDeadlines($db, $_POST['single_date'], $meal_selections);
            if (!empty($blocked_meals)) {
                throw new Exception('Cannot place order - deadline has passed for: ' . implode(', ', $blocked_meals));
            }

            $meal_requests = [[
                'date' => $_POST['single_date'],
                'breakfast' => $breakfast,
                'breakfast_count' => $breakfast_count,
                'breakfast_countx' => $breakfast_countx,
                'snack1_countx' => $snack1_countx,
                'lunch' => $lunch,
                'lunch_count' => $lunch_count,
                'lunch_countx' => $lunch_countx,
                'snack2' => $snack2,
                'snack2_count' => $snack2_count,
                'snack2_countx' => $snack2_countx,
                'dinner' => $dinner,
                'dinner_count' => $dinner_count,
                'dinner_countx' => $dinner_countx,
                'snack3_countx' => $snack3_countx
            ]];

        } else {
            // Multiple days processing (new logic)
            $meal_requests = [];

            // Check if using unified meal configuration
            if ($_POST['meal_config_mode'] === 'unified') {
                // Unified meals - same for all days
                $unified_meals = $_POST['unified_meals'] ?? [];

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
                $has_meal_selected = isset($unified_meals['breakfast']) || isset($unified_meals['lunch']) ||
                                   isset($unified_meals['dinner']) || isset($unified_meals['snack1']) ||
                                   isset($unified_meals['snack2']) || isset($unified_meals['snack3']);

                if (!$has_meal_selected) {
                    throw new Exception('Please select at least one meal for the multiple day request.');
                }

                // Create meal selection array for deadline checking
                $meal_selections = [
                    'breakfast' => isset($unified_meals['breakfast']),
                    'snack1' => isset($unified_meals['snack1']),
                    'lunch' => isset($unified_meals['lunch']),
                    'snack2' => isset($unified_meals['snack2']),
                    'dinner' => isset($unified_meals['dinner']),
                    'snack3' => isset($unified_meals['snack3'])
                ];

                while ($current_date <= $end_date_obj) {
                    $date_str = $current_date->format('Y-m-d');

                    // Check meal deadlines for each date
                    $blocked_meals = checkMealDeadlines($db, $date_str, $meal_selections);
                    if (!empty($blocked_meals)) {
                        throw new Exception('Cannot place order for ' . $date_str . ' - deadline has passed for: ' . implode(', ', $blocked_meals));
                    }

                    $meal_requests[] = [
                        'date' => $date_str,
                        'breakfast' => isset($unified_meals['breakfast']) ? 1 : 0,
                        'breakfast_count' => isset($unified_meals['breakfast']) ? 1 : 0,
                        'breakfast_countx' => 0,
                        'snack1_countx' => isset($unified_meals['snack1']) ? 1 : 0,
                        'lunch' => isset($unified_meals['lunch']) ? 1 : 0,
                        'lunch_count' => isset($unified_meals['lunch']) ? 1 : 0,
                        'lunch_countx' => 0,
                        'snack2' => isset($unified_meals['snack2']) ? 1 : 0,
                        'snack2_count' => isset($unified_meals['snack2']) ? 1 : 0,
                        'snack2_countx' => 0,
                        'dinner' => isset($unified_meals['dinner']) ? 1 : 0,
                        'dinner_count' => isset($unified_meals['dinner']) ? 1 : 0,
                        'dinner_countx' => 0,
                        'snack3_countx' => isset($unified_meals['snack3']) ? 1 : 0
                    ];
                    $current_date->add(new DateInterval('P1D'));
                }
            } else {
                // Individual meals - different for each day
                $meals_data = $_POST['meals'] ?? [];

                foreach ($meals_data as $date => $day_meals) {
                    // Check meal deadlines for each individual date
                    $meal_selections = [
                        'breakfast' => isset($day_meals['breakfast']) || ($day_meals['breakfast_countx'] ?? 0) > 0,
                        'snack1' => ($day_meals['snack1_countx'] ?? 0) > 0,
                        'lunch' => isset($day_meals['lunch']) || ($day_meals['lunch_countx'] ?? 0) > 0,
                        'snack2' => ($day_meals['snack2_count'] ?? 0) > 0,
                        'dinner' => isset($day_meals['dinner']) || ($day_meals['dinner_countx'] ?? 0) > 0,
                        'snack3' => ($day_meals['snack3_countx'] ?? 0) > 0
                    ];

                    $blocked_meals = checkMealDeadlines($db, $date, $meal_selections);
                    if (!empty($blocked_meals)) {
                        throw new Exception('Cannot place order for ' . $date . ' - deadline has passed for: ' . implode(', ', $blocked_meals));
                    }

                    $meal_requests[] = [
                        'date' => $date,
                        'breakfast' => isset($day_meals['breakfast']) ? 1 : 0,
                        'breakfast_count' => isset($day_meals['breakfast']) ? 1 : 0,
                        'breakfast_countx' => (int)($day_meals['breakfast_countx'] ?? 0),
                        'snack1_countx' => (int)($day_meals['snack1_countx'] ?? 0),
                        'lunch' => isset($day_meals['lunch']) ? 1 : 0,
                        'lunch_count' => isset($day_meals['lunch']) ? 1 : 0,
                        'lunch_countx' => (int)($day_meals['lunch_countx'] ?? 0),
                        'snack2' => ($day_meals['snack2_count'] ?? 0) > 0 ? 1 : 0,
                        'snack2_count' => (int)($day_meals['snack2_count'] ?? 0),
                        'snack2_countx' => 0,
                        'dinner' => isset($day_meals['dinner']) ? 1 : 0,
                        'dinner_count' => isset($day_meals['dinner']) ? 1 : 0,
                        'dinner_countx' => (int)($day_meals['dinner_countx'] ?? 0),
                        'snack3_countx' => (int)($day_meals['snack3_countx'] ?? 0)
                    ];
                }
            }
        }

        // Final validation - ensure we have meal requests to process
        if (empty($meal_requests)) {
            throw new Exception('No valid meal requests to process. Please check your form data.');
        }

        // Create table if doesn't exist
        $create_table = "CREATE TABLE IF NOT EXISTS meal_requests_employee (
            id INT AUTO_INCREMENT PRIMARY KEY,
            emp_number VARCHAR(50) NOT NULL,
            emp_name VARCHAR(100) NOT NULL,
            emp_location VARCHAR(100) NOT NULL,
            meal_type VARCHAR(50) NOT NULL DEFAULT 'Employee Meal',
            request_date DATE NOT NULL,
            breakfast_enabled TINYINT DEFAULT 0,
            breakfast_count INT DEFAULT 0,
            breakfast_countx INT DEFAULT 0,
            snack1_countx INT DEFAULT 0,
            lunch_enabled TINYINT DEFAULT 0,
            lunch_count INT DEFAULT 0,
            lunch_countx INT DEFAULT 0,
            snack2_enabled TINYINT DEFAULT 0,
            snack2_count INT DEFAULT 0,
            snack2_countx INT DEFAULT 0,
            dinner_enabled TINYINT DEFAULT 0,
            dinner_count INT DEFAULT 0,
            dinner_countx INT DEFAULT 0,
            snack3_countx INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $db->query($create_table);

        // Insert meal requests for each date
        $insert_query = "INSERT INTO meal_requests_employee (
            emp_number, emp_name, emp_location, meal_type, request_date,
            breakfast_enabled, breakfast_count, breakfast_countx, snack1_countx,
            lunch_enabled, lunch_count, lunch_countx,
            snack2_enabled, snack2_count, snack2_countx,
            dinner_enabled, dinner_count, dinner_countx, snack3_countx
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $requests_count = 0;

        foreach ($meal_requests as $meal_request) {
            $result = $db->query($insert_query, [
                $emp_number, $emp_name, $emp_location, $meal_type, $meal_request['date'],
                $meal_request['breakfast'], $meal_request['breakfast_count'], $meal_request['breakfast_countx'], $meal_request['snack1_countx'],
                $meal_request['lunch'], $meal_request['lunch_count'], $meal_request['lunch_countx'],
                $meal_request['snack2'], $meal_request['snack2_count'], $meal_request['snack2_countx'],
                $meal_request['dinner'], $meal_request['dinner_count'], $meal_request['dinner_countx'], $meal_request['snack3_countx']
            ]);

            if ($result) {
                $requests_count++;
            }
        }

        if ($requests_count == 1) {
            $message = 'Meal request submitted successfully!';
        } else {
            $message = "Meal requests submitted successfully for $requests_count days!";
        }


        $message_type = 'success';


    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get dropdown data for meal types (from plan 1.7.5)
$meal_types = [
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
    <title>SmartHRM - Employee Meal Request</title>
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

        /* Enhanced Meal Request Styles */
        .meal-section {
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .meal-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .meal-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .meal-card.selected {
            border: 2px solid var(--primary-color);
            transform: translateY(-2px);
        }

        .meal-header {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
        }

        .meal-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 80%;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transform: translateX(-50%);
        }

        .meal-body {
            padding: 1.2rem;
        }

        .meal-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.2rem;
        }

        .breakfast-theme {
            background: linear-gradient(135deg, #ff9a56, #ffb347);
        }

        .lunch-theme {
            background: linear-gradient(135deg, #26d0ce, #1abc9c);
        }

        .dinner-theme {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        .snack-theme {
            background: linear-gradient(135deg, #f093fb, #f5576c);
        }

        .count-selector {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.75rem;
        }

        .count-btn {
            width: 35px;
            height: 35px;
            border: none;
            border-radius: 8px;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .count-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
        }

        .count-btn:disabled {
            background: #e9ecef;
            color: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .count-display {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-weight: 600;
            color: var(--primary-color);
            min-width: 60px;
            text-align: center;
        }

        .request-mode-selector {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }

        .mode-option {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .mode-option:hover {
            border-color: var(--primary-color);
            background: rgba(0, 123, 255, 0.05);
        }

        .mode-option.active {
            border-color: var(--primary-color);
            background: linear-gradient(135deg, rgba(0, 123, 255, 0.1), rgba(0, 123, 255, 0.05));
        }

        .mode-icon {
            font-size: 2rem;
            margin-bottom: 0.75rem;
            color: var(--primary-color);
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
            width: 35px;
            height: 35px;
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
            top: 17px;
            left: 60%;
            right: -40%;
            height: 2px;
            background: #e9ecef;
        }

        .step.completed .step-line {
            background: var(--success-color);
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
            justify-content: between;
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
            border: 2px solid #dee2e6;
            border-radius: 6px;
            background: white;
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .animated-checkbox input[type="checkbox"]:checked + .checkmark {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }

        .checkmark::after {
            content: '';
            position: absolute;
            left: 7px;
            top: 3px;
            width: 6px;
            height: 12px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg) scale(0);
            transition: transform 0.2s ease;
        }

        .animated-checkbox input[type="checkbox"]:checked + .checkmark::after {
            transform: rotate(45deg) scale(1);
        }

        .date-range-picker {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid #dee2e6;
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
            padding: 0.4rem 1rem;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .quick-date-btn:hover {
            border-color: var(--primary-color);
            background: rgba(0, 123, 255, 0.05);
        }

        .meal-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        @media (max-width: 768px) {
            .meal-grid {
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
                        <h1><i class="fas fa-utensils me-3"></i>Employee Meal Request</h1>
                        <p>Submit your daily meal requests and preferences</p>
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
                    <li class="breadcrumb-item active">Employee Meal Request</li>
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

            <!-- Step Progress Indicator -->
            <div class="step-indicator">
                <div class="step active" id="step1">
                    <div class="step-number">1</div>
                    <div class="step-title">Basic Info</div>
                    <div class="step-line"></div>
                </div>
                <div class="step" id="step2">
                    <div class="step-number">2</div>
                    <div class="step-title">Date Selection</div>
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

            <!-- Meal Request Form -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="form-card">
                        <div class="card-header-modern">
                            <h5><i class="fas fa-utensils me-2"></i>Daily Meal Request Form</h5>
                        </div>
                        <form method="POST" id="mealRequestForm">
                            <input type="hidden" name="request_mode" id="hiddenRequestMode" value="single">
                            <input type="hidden" name="meal_config_mode" id="hiddenMealConfigMode" value="unified">
                            <!-- Employee Information (Auto-filled) -->
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <label class="form-label">Employee Number</label>
                                    <input type="text" class="form-control" name="emp_number" value="<?php echo htmlspecialchars($user['epf_number']); ?>" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Employee Name</label>
                                    <input type="text" class="form-control" name="emp_name" value="<?php echo htmlspecialchars($user['name']); ?>" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Location</label>
                                    <input type="text" class="form-control" name="emp_location" value="<?php echo htmlspecialchars($user['location_name'] ?? 'N/A'); ?>" readonly>
                                </div>
                            </div>

                            <!-- Request Mode Selection with Modern Cards -->
                            <div class="request-mode-selector">
                                <h6 class="mb-3">Choose Request Type</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mode-option active" onclick="selectRequestMode('single')" id="singleModeCard">
                                            <div class="mode-icon">
                                                <i class="fas fa-calendar-day"></i>
                                            </div>
                                            <h6 class="mb-2">Single Day</h6>
                                            <p class="text-muted mb-0">Request meals for one specific day</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mode-option" onclick="selectRequestMode('multiple')" id="multipleModeCard">
                                            <div class="mode-icon">
                                                <i class="fas fa-calendar-week"></i>
                                            </div>
                                            <h6 class="mb-2">Multiple Days</h6>
                                            <p class="text-muted mb-0">Request meals for several days at once</p>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" id="requestMode" value="single">
                                <input type="hidden" name="meal_type" value="Employee Meal">
                            </div>

                            <!-- Single Date Section -->
                            <div id="singleDateSection" class="mb-4">
                                <div class="date-range-picker">
                                    <label class="form-label mb-3">
                                        <i class="fas fa-calendar-alt me-2"></i>Select Request Date <span class="text-danger">*</span>
                                    </label>

                                    <!-- Quick Date Buttons -->
                                    <div class="quick-date-buttons">
                                        <button type="button" class="quick-date-btn" onclick="setQuickDate('today')">Today</button>
                                        <button type="button" class="quick-date-btn" onclick="setQuickDate('tomorrow')">Tomorrow</button>
                                        <button type="button" class="quick-date-btn" onclick="setQuickDate('day-after')">Day After Tomorrow</button>
                                    </div>

                                    <input type="date" class="form-control" name="single_date" id="singleDateInput" min="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>

                            <!-- Multiple Dates Section -->
                            <div id="multipleDatesSection" class="mb-4" style="display: none;">
                                <div class="date-range-picker">
                                    <label class="form-label mb-3">
                                        <i class="fas fa-calendar-week me-2"></i>Select Date Range <span class="text-danger">*</span>
                                    </label>

                                    <!-- Quick Range Buttons -->
                                    <div class="quick-date-buttons">
                                        <button type="button" class="quick-date-btn" onclick="setQuickRange('week')">This Week</button>
                                        <button type="button" class="quick-date-btn" onclick="setQuickRange('next3days')">Next 3 Days</button>
                                        <button type="button" class="quick-date-btn" onclick="setQuickRange('next5days')">Next 5 Days</button>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <label class="form-label small">Start Date</label>
                                            <input type="date" class="form-control" name="start_date" id="startDateInput" min="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" value="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small">End Date</label>
                                            <input type="date" class="form-control" name="end_date" id="endDateInput" min="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Single Day Meal Sections -->
                            <div id="singleDayMeals" class="meal-grid">
                                <!-- Breakfast -->
                                <div class="meal-section">
                                    <div class="meal-card" id="breakfastCard">
                                        <div class="meal-header breakfast-theme">
                                            <div class="d-flex align-items-center">
                                                <div class="meal-icon bg-light text-warning">
                                                    <i class="fas fa-coffee"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="animated-checkbox">
                                                        <input type="checkbox" id="breakfast" name="breakfast" onchange="toggleMealCard('breakfast')">
                                                        <span class="checkmark"></span>
                                                    </div>
                                                    <label class="fw-bold text-white ms-3 mb-0" for="breakfast">Breakfast</label>
                                                    <p class="text-white-50 small mb-0">Start your day right</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="meal-body">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <span class="small text-muted">Base Quantity</span>
                                                <span class="badge bg-light text-dark">1</span>
                                            </div>
                                            <div class="count-selector">
                                                <span class="small text-muted me-2">Extra:</span>
                                                <button type="button" class="count-btn" onclick="decrementCount('breakfast_countx')">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <div class="count-display" id="breakfast_countx_display">0</div>
                                                <button type="button" class="count-btn" onclick="incrementCount('breakfast_countx')">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                                <input type="hidden" name="breakfast_countx" id="breakfast_countx" value="0">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Lunch -->
                                <div class="meal-section">
                                    <div class="meal-card" id="lunchCard">
                                        <div class="meal-header lunch-theme">
                                            <div class="d-flex align-items-center">
                                                <div class="meal-icon bg-light text-success">
                                                    <i class="fas fa-bowl-food"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="animated-checkbox">
                                                        <input type="checkbox" id="lunch" name="lunch" onchange="toggleMealCard('lunch')">
                                                        <span class="checkmark"></span>
                                                    </div>
                                                    <label class="fw-bold text-white ms-3 mb-0" for="lunch">Lunch</label>
                                                    <p class="text-white-50 small mb-0">Main meal of the day</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="meal-body">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <span class="small text-muted">Base Quantity</span>
                                                <span class="badge bg-light text-dark">1</span>
                                            </div>
                                            <div class="count-selector">
                                                <span class="small text-muted me-2">Extra:</span>
                                                <button type="button" class="count-btn" onclick="decrementCount('lunch_countx')">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <div class="count-display" id="lunch_countx_display">0</div>
                                                <button type="button" class="count-btn" onclick="incrementCount('lunch_countx')">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                                <input type="hidden" name="lunch_countx" id="lunch_countx" value="0">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Dinner -->
                                <div class="meal-section">
                                    <div class="meal-card" id="dinnerCard">
                                        <div class="meal-header dinner-theme">
                                            <div class="d-flex align-items-center">
                                                <div class="meal-icon bg-light text-primary">
                                                    <i class="fas fa-utensils"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="animated-checkbox">
                                                        <input type="checkbox" id="dinner" name="dinner" onchange="toggleMealCard('dinner')">
                                                        <span class="checkmark"></span>
                                                    </div>
                                                    <label class="fw-bold text-white ms-3 mb-0" for="dinner">Dinner</label>
                                                    <p class="text-white-50 small mb-0">End your day well</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="meal-body">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <span class="small text-muted">Base Quantity</span>
                                                <span class="badge bg-light text-dark">1</span>
                                            </div>
                                            <div class="count-selector">
                                                <span class="small text-muted me-2">Extra:</span>
                                                <button type="button" class="count-btn" onclick="decrementCount('dinner_countx')">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <div class="count-display" id="dinner_countx_display">0</div>
                                                <button type="button" class="count-btn" onclick="incrementCount('dinner_countx')">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                                <input type="hidden" name="dinner_countx" id="dinner_countx" value="0">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Snacks Combined Card -->
                                <div class="meal-section">
                                    <div class="meal-card" id="snacksCard">
                                        <div class="meal-header snack-theme">
                                            <div class="d-flex align-items-center">
                                                <div class="meal-icon bg-light text-info">
                                                    <i class="fas fa-cookie"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="fw-bold text-white mb-0">Snacks</h6>
                                                    <p class="text-white-50 small mb-0">Light bites throughout the day</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="meal-body">
                                            <!-- Snack 1 -->
                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="fw-medium">Morning Snack</span>
                                                    <i class="fas fa-cookie text-muted"></i>
                                                </div>
                                                <div class="count-selector">
                                                    <span class="small text-muted me-2">Qty:</span>
                                                    <button type="button" class="count-btn" onclick="decrementCount('snack1_countx')">
                                                        <i class="fas fa-minus"></i>
                                                    </button>
                                                    <div class="count-display" id="snack1_countx_display">0</div>
                                                    <button type="button" class="count-btn" onclick="incrementCount('snack1_countx')">
                                                        <i class="fas fa-plus"></i>
                                                    </button>
                                                    <input type="hidden" name="snack1_countx" id="snack1_countx" value="0">
                                                </div>
                                            </div>

                                            <!-- Snack 2 -->
                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="fw-medium">Afternoon Snack</span>
                                                    <i class="fas fa-cookie-bite text-muted"></i>
                                                </div>
                                                <div class="count-selector">
                                                    <span class="small text-muted me-2">Qty:</span>
                                                    <button type="button" class="count-btn" onclick="decrementCount('snack2_count')">
                                                        <i class="fas fa-minus"></i>
                                                    </button>
                                                    <div class="count-display" id="snack2_count_display">0</div>
                                                    <button type="button" class="count-btn" onclick="incrementCount('snack2_count')">
                                                        <i class="fas fa-plus"></i>
                                                    </button>
                                                    <input type="hidden" name="snack2_count" id="snack2_count" value="0">
                                                </div>
                                            </div>

                                            <!-- Snack 3 -->
                                            <div>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="fw-medium">Evening Snack</span>
                                                    <i class="fas fa-candy-cane text-muted"></i>
                                                </div>
                                                <div class="count-selector">
                                                    <span class="small text-muted me-2">Qty:</span>
                                                    <button type="button" class="count-btn" onclick="decrementCount('snack3_countx')">
                                                        <i class="fas fa-minus"></i>
                                                    </button>
                                                    <div class="count-display" id="snack3_countx_display">0</div>
                                                    <button type="button" class="count-btn" onclick="incrementCount('snack3_countx')">
                                                        <i class="fas fa-plus"></i>
                                                    </button>
                                                    <input type="hidden" name="snack3_countx" id="snack3_countx" value="0">
                                                </div>
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
                                        <h6 class="mb-0"><i class="fas fa-cogs me-2"></i>Meal Configuration Options</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="sameForAllDays" checked onchange="toggleCustomization()">
                                                    <label class="form-check-label fw-bold" for="sameForAllDays">
                                                        Use same meal configuration for all days
                                                    </label>
                                                    <small class="text-muted d-block">Turn off to customize meals for individual days</small>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div id="selectedDaysCount" class="alert alert-info mb-0 py-2">
                                                    <i class="fas fa-calendar-alt me-2"></i><span id="daysCountText">0 days selected</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Unified Meal Selection (when same for all days) -->
                                <div id="unifiedMealSelection">
                                    <!-- This will contain the standard meal selection form -->
                                </div>

                                <!-- Individual Day Customization (when different for each day) -->
                                <div id="individualDayMeals" style="display: none;">
                                    <!-- Dynamic individual day sections will be generated here -->
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="text-center mt-4">
                                <?php if (hasModulePermission($db, 'meal.reset_employee_request')): ?>
                                <button type="reset" class="btn btn-outline-secondary btn-lg me-3" onclick="resetForm()">
                                    <i class="fas fa-undo me-2"></i>Reset Form
                                </button>
                                <?php endif; ?>
                                <?php if (hasModulePermission($db, 'meal.submit_employee_request')): ?>
                                <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                    <i class="fas fa-save me-2"></i>Submit Meal Request
                                </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Floating Summary Sidebar -->
                <div class="col-lg-4">
                    <div class="floating-summary">
                        <div class="d-flex align-items-center mb-3">
                            <i class="fas fa-clipboard-list text-primary me-2"></i>
                            <h6 class="mb-0">Meal Summary</h6>
                        </div>

                        <div id="summaryContent">
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-utensils fa-2x mb-3"></i>
                                <p class="mb-0">Select meals to see summary</p>
                            </div>
                        </div>

                        <hr>

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
        // Enhanced Interactive Functions

        // Request Mode Selection
        function selectRequestMode(mode) {
            // Update visual selection
            document.querySelectorAll('.mode-option').forEach(option => {
                option.classList.remove('active');
            });

            if (mode === 'single') {
                document.getElementById('singleModeCard').classList.add('active');
            } else {
                document.getElementById('multipleModeCard').classList.add('active');
            }

            // Update hidden field and trigger mode change
            document.getElementById('requestMode').value = mode;
            toggleRequestMode();
            updateProgress();
            updateSummary();
        }

        // Quick Date Functions
        function setQuickDate(type) {
            const today = new Date();
            let targetDate = new Date();

            switch(type) {
                case 'today':
                    targetDate = today;
                    break;
                case 'tomorrow':
                    targetDate.setDate(today.getDate() + 1);
                    break;
                case 'day-after':
                    targetDate.setDate(today.getDate() + 2);
                    break;
            }

            document.getElementById('singleDateInput').value = targetDate.toISOString().split('T')[0];
            updateSummary();
            updateProgress();
        }

        function setQuickRange(type) {
            const today = new Date();
            let startDate = new Date();
            let endDate = new Date();

            switch(type) {
                case 'week':
                    startDate = today;
                    endDate.setDate(today.getDate() + 6);
                    break;
                case 'next3days':
                    startDate = today;
                    endDate.setDate(today.getDate() + 2);
                    break;
                case 'next5days':
                    startDate = today;
                    endDate.setDate(today.getDate() + 4);
                    break;
            }

            document.getElementById('startDateInput').value = startDate.toISOString().split('T')[0];
            document.getElementById('endDateInput').value = endDate.toISOString().split('T')[0];
            generateMultipleDayMeals();
            updateSummary();
            updateProgress();
        }

        // Count Management Functions
        function incrementCount(fieldName) {
            const field = document.getElementById(fieldName);
            const display = document.getElementById(fieldName + '_display');
            let currentValue = parseInt(field.value);

            if (currentValue < 5) { // Max limit
                currentValue++;
                field.value = currentValue;
                display.textContent = currentValue;
                updateSummary();
            }
        }

        function decrementCount(fieldName) {
            const field = document.getElementById(fieldName);
            const display = document.getElementById(fieldName + '_display');
            let currentValue = parseInt(field.value);

            if (currentValue > 0) {
                currentValue--;
                field.value = currentValue;
                display.textContent = currentValue;
                updateSummary();
            }
        }

        // Meal Card Toggle
        function toggleMealCard(mealType) {
            const checkbox = document.getElementById(mealType);
            const card = document.getElementById(mealType + 'Card');

            if (checkbox.checked) {
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }

            updateSummary();
        }

        // Progress Management
        function updateProgress() {
            const requestMode = document.getElementById('requestMode').value;
            let progress = 25; // Step 1 always completed (basic info)

            // Step 2: Date selection
            if (requestMode === 'single') {
                if (document.getElementById('singleDateInput').value) {
                    progress = 50;
                    updateStepStatus('step2', 'completed');
                }
            } else {
                if (document.getElementById('startDateInput').value && document.getElementById('endDateInput').value) {
                    progress = 50;
                    updateStepStatus('step2', 'completed');
                }
            }

            // Step 3: Meal selection
            if (hasSelectedMeals()) {
                progress = 75;
                updateStepStatus('step3', 'completed');
            }

            // Step 4: Ready to submit
            if (progress === 75) {
                updateStepStatus('step4', 'active');
            }

            document.getElementById('progressBar').style.width = progress + '%';
        }

        function updateStepStatus(stepId, status) {
            const step = document.getElementById(stepId);
            step.className = 'step ' + status;
        }

        function hasSelectedMeals() {
            const requestMode = document.getElementById('requestMode').value;

            if (requestMode === 'single') {
                // Single Day validation
                const checkboxes = ['breakfast', 'lunch', 'dinner'];
                const counts = ['snack1_countx', 'snack2_count', 'snack3_countx', 'breakfast_countx', 'lunch_countx', 'dinner_countx'];

                // Check main meal checkboxes
                for (let checkbox of checkboxes) {
                    if (document.getElementById(checkbox) && document.getElementById(checkbox).checked) {
                        return true;
                    }
                }

                // Check snack counts
                for (let count of counts) {
                    const field = document.getElementById(count);
                    if (field && parseInt(field.value) > 0) {
                        return true;
                    }
                }
            } else if (requestMode === 'multiple') {
                // Multiple Days validation
                const mealConfigMode = document.getElementById('hiddenMealConfigMode').value;

                if (mealConfigMode === 'unified') {
                    // Check unified meal selection
                    const unifiedMeals = ['unified_breakfast', 'unified_lunch', 'unified_dinner', 'unified_snack1', 'unified_snack2', 'unified_snack3'];
                    for (let mealId of unifiedMeals) {
                        const checkbox = document.getElementById(mealId);
                        if (checkbox && checkbox.checked) {
                            return true;
                        }
                    }
                } else {
                    // Check individual day selections (would need dynamic checking based on generated date ranges)
                    // For now, check if any dynamically generated meal checkboxes are checked
                    const allCheckboxes = document.querySelectorAll('input[type="checkbox"][name^="meals["]');
                    for (let checkbox of allCheckboxes) {
                        if (checkbox.checked) {
                            return true;
                        }
                    }
                }
            }

            return false;
        }

        // Summary Update
        function updateSummary() {
            const requestMode = document.getElementById('requestMode').value;
            const requestType = document.getElementById('requestType');
            const selectedDates = document.getElementById('selectedDates');
            const totalMeals = document.getElementById('totalMeals');
            const summaryContent = document.getElementById('summaryContent');

            // Update request type
            requestType.textContent = requestMode === 'single' ? 'Single Day' : 'Multiple Days';

            // Update selected dates
            if (requestMode === 'single') {
                const singleDate = document.getElementById('singleDateInput').value;
                selectedDates.textContent = singleDate ? formatDate(singleDate) : 'Not selected';
            } else {
                const startDate = document.getElementById('startDateInput').value;
                const endDate = document.getElementById('endDateInput').value;
                if (startDate && endDate) {
                    selectedDates.textContent = `${formatDate(startDate)} to ${formatDate(endDate)}`;
                } else {
                    selectedDates.textContent = 'Not selected';
                }
            }

            // Calculate total meals
            let mealCount = 0;
            const selectedMeals = [];

            // Check main meals
            if (document.getElementById('breakfast') && document.getElementById('breakfast').checked) {
                mealCount++;
                selectedMeals.push('Breakfast');
            }
            if (document.getElementById('lunch') && document.getElementById('lunch').checked) {
                mealCount++;
                selectedMeals.push('Lunch');
            }
            if (document.getElementById('dinner') && document.getElementById('dinner').checked) {
                mealCount++;
                selectedMeals.push('Dinner');
            }

            // Check snacks
            const snackCounts = [
                {id: 'snack1_countx', name: 'Morning Snack'},
                {id: 'snack2_count', name: 'Afternoon Snack'},
                {id: 'snack3_countx', name: 'Evening Snack'},
                {id: 'breakfast_countx', name: 'Extra Breakfast'},
                {id: 'lunch_countx', name: 'Extra Lunch'},
                {id: 'dinner_countx', name: 'Extra Dinner'}
            ];

            snackCounts.forEach(snack => {
                const field = document.getElementById(snack.id);
                if (field && parseInt(field.value) > 0) {
                    mealCount += parseInt(field.value);
                    selectedMeals.push(`${snack.name} (${field.value})`);
                }
            });

            totalMeals.textContent = mealCount;

            // Update summary content
            if (selectedMeals.length > 0) {
                summaryContent.innerHTML = selectedMeals.map(meal =>
                    `<div class="d-flex justify-content-between align-items-center py-1">
                        <span class="small">${meal}</span>
                        <i class="fas fa-check text-success"></i>
                    </div>`
                ).join('');
            } else {
                summaryContent.innerHTML = `
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-utensils fa-2x mb-3"></i>
                        <p class="mb-0">Select meals to see summary</p>
                    </div>
                `;
            }
        }

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', {
                weekday: 'short',
                month: 'short',
                day: 'numeric'
            });
        }

        // Reset Form Function
        function resetForm() {
            // Reset all checkboxes
            document.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                cb.checked = false;
            });

            // Reset all count fields
            document.querySelectorAll('input[type="hidden"]').forEach(field => {
                if (field.id.includes('count')) {
                    field.value = '0';
                }
            });

            // Reset count displays
            document.querySelectorAll('.count-display').forEach(display => {
                display.textContent = '0';
            });

            // Reset meal cards
            document.querySelectorAll('.meal-card').forEach(card => {
                card.classList.remove('selected');
            });

            // Reset to single mode
            selectRequestMode('single');

            // Reset progress
            updateProgress();
            updateSummary();
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
                generateMultipleDayMeals();
            }
        }

        function generateMultipleDayMeals() {
            const startDate = document.querySelector('input[name="start_date"]').value;
            const endDate = document.querySelector('input[name="end_date"]').value;

            if (!startDate || !endDate) return;

            const start = new Date(startDate);
            const end = new Date(endDate);

            // Calculate number of days
            const daysDiff = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
            document.getElementById('daysCountText').textContent = `${daysDiff} days selected`;

            // Generate unified meal form for "same for all days" mode
            generateUnifiedMealForm();

            // Generate individual day forms for customization mode
            generateIndividualDayForms();
        }

        function generateUnifiedMealForm() {
            const unifiedMealSelection = document.getElementById('unifiedMealSelection');
            unifiedMealSelection.innerHTML = `
                <div class="card border-success">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="fas fa-utensils me-2"></i>Meal Selection (Applied to All Days)</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Breakfast -->
                            <div class="col-lg-6 mb-3">
                                <div class="card bg-light">
                                    <div class="card-header bg-warning text-dark">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="unified_breakfast" name="unified_meals[breakfast]">
                                            <label class="form-check-label fw-bold" for="unified_breakfast">
                                                <i class="fas fa-coffee me-2"></i>Breakfast
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Lunch -->
                            <div class="col-lg-6 mb-3">
                                <div class="card bg-light">
                                    <div class="card-header bg-success text-white">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="unified_lunch" name="unified_meals[lunch]">
                                            <label class="form-check-label fw-bold" for="unified_lunch">
                                                <i class="fas fa-bowl-food me-2"></i>Lunch
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Dinner -->
                            <div class="col-lg-6 mb-3">
                                <div class="card bg-light">
                                    <div class="card-header bg-primary text-white">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="unified_dinner" name="unified_meals[dinner]">
                                            <label class="form-check-label fw-bold" for="unified_dinner">
                                                <i class="fas fa-utensils me-2"></i>Dinner
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Snacks -->
                            <div class="col-lg-6 mb-3">
                                <div class="card bg-light">
                                    <div class="card-header bg-info text-white">
                                        <h6 class="mb-0"><i class="fas fa-cookie me-2"></i>Snacks</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-12 mb-2">
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input" id="unified_snack1" name="unified_meals[snack1]">
                                                    <label class="form-check-label" for="unified_snack1">
                                                        <i class="fas fa-cookie me-2"></i>Snack 1
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-12 mb-2">
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input" id="unified_snack2" name="unified_meals[snack2]">
                                                    <label class="form-check-label" for="unified_snack2">
                                                        <i class="fas fa-cookie-bite me-2"></i>Snack 2
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input" id="unified_snack3" name="unified_meals[snack3]">
                                                    <label class="form-check-label" for="unified_snack3">
                                                        <i class="fas fa-candy-cane me-2"></i>Snack 3
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        function generateIndividualDayForms() {
            const startDate = document.querySelector('input[name="start_date"]').value;
            const endDate = document.querySelector('input[name="end_date"]').value;

            if (!startDate || !endDate) return;

            const start = new Date(startDate);
            const end = new Date(endDate);
            const individualDayMeals = document.getElementById('individualDayMeals');

            // Clear existing content
            individualDayMeals.innerHTML = '';

            const currentDate = new Date(start);
            while (currentDate <= end) {
                const dateStr = currentDate.toISOString().split('T')[0];
                const dateDisplay = currentDate.toLocaleDateString('en-US', {
                    weekday: 'short',
                    month: 'short',
                    day: 'numeric'
                });

                const daySection = generateCompactDayMealSection(dateStr, dateDisplay);
                individualDayMeals.appendChild(daySection);

                currentDate.setDate(currentDate.getDate() + 1);
            }
        }

        function generateCompactDayMealSection(dateStr, dateDisplay) {
            const dayDiv = document.createElement('div');
            dayDiv.className = 'card mb-3 border-primary';
            dayDiv.innerHTML = `
                <div class="card-header bg-primary text-white py-2">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <h6 class="mb-0"><i class="fas fa-calendar-day me-2"></i>${dateDisplay}</h6>
                        </div>
                        <div class="col-md-9">
                            <div class="row">
                                <div class="col-3">
                                    <div class="form-check form-check-inline">
                                        <input type="checkbox" class="form-check-input" id="breakfast_${dateStr}" name="meals[${dateStr}][breakfast]">
                                        <label class="form-check-label text-white small" for="breakfast_${dateStr}">Breakfast</label>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="form-check form-check-inline">
                                        <input type="checkbox" class="form-check-input" id="lunch_${dateStr}" name="meals[${dateStr}][lunch]">
                                        <label class="form-check-label text-white small" for="lunch_${dateStr}">Lunch</label>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="form-check form-check-inline">
                                        <input type="checkbox" class="form-check-input" id="dinner_${dateStr}" name="meals[${dateStr}][dinner]">
                                        <label class="form-check-label text-white small" for="dinner_${dateStr}">Dinner</label>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <button type="button" class="btn btn-sm btn-outline-light" onclick="toggleDayDetails('${dateStr}')">
                                        <i class="fas fa-plus" id="toggle_icon_${dateStr}"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body collapse" id="details_${dateStr}">
                    <div class="row">
                        <div class="col-4">
                            <label class="form-label small">Breakfast Extra</label>
                            <input type="number" class="form-control form-control-sm" name="meals[${dateStr}][breakfast_countx]" min="0" value="0">
                        </div>
                        <div class="col-4">
                            <label class="form-label small">Lunch Extra</label>
                            <input type="number" class="form-control form-control-sm" name="meals[${dateStr}][lunch_countx]" min="0" value="0">
                        </div>
                        <div class="col-4">
                            <label class="form-label small">Dinner Extra</label>
                            <input type="number" class="form-control form-control-sm" name="meals[${dateStr}][dinner_countx]" min="0" value="0">
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-3">
                            <label class="form-label small">Snack 1</label>
                            <input type="number" class="form-control form-control-sm" name="meals[${dateStr}][snack1_countx]" min="0" value="0">
                        </div>
                        <div class="col-3">
                            <label class="form-label small">Snack 2</label>
                            <input type="number" class="form-control form-control-sm" name="meals[${dateStr}][snack2_count]" min="0" value="0">
                        </div>
                        <div class="col-3">
                            <label class="form-label small">Snack 3</label>
                            <input type="number" class="form-control form-control-sm" name="meals[${dateStr}][snack3_countx]" min="0" value="0">
                        </div>
                    </div>
                </div>
            `;
            return dayDiv;
        }

        function toggleCustomization() {
            const sameForAll = document.getElementById('sameForAllDays').checked;
            const unifiedSection = document.getElementById('unifiedMealSelection');
            const individualSection = document.getElementById('individualDayMeals');

            // Update hidden field to track mode
            document.getElementById('hiddenMealConfigMode').value = sameForAll ? 'unified' : 'individual';

            if (sameForAll) {
                unifiedSection.style.display = 'block';
                individualSection.style.display = 'none';
            } else {
                unifiedSection.style.display = 'none';
                individualSection.style.display = 'block';
            }
        }

        function toggleDayDetails(dateStr) {
            const details = document.getElementById(`details_${dateStr}`);
            const icon = document.getElementById(`toggle_icon_${dateStr}`);

            if (details.classList.contains('show')) {
                details.classList.remove('show');
                icon.className = 'fas fa-plus';
            } else {
                details.classList.add('show');
                icon.className = 'fas fa-minus';
            }
        }

        function generateDayMealSection(dateStr, dateDisplay) {
            const dayDiv = document.createElement('div');
            dayDiv.className = 'card mb-4 border-primary';
            dayDiv.innerHTML = `
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="fas fa-calendar-day me-2"></i>${dateDisplay}</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Breakfast -->
                        <div class="col-lg-6 mb-3">
                            <div class="card bg-light">
                                <div class="card-header bg-warning text-dark">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="breakfast_${dateStr}" name="meals[${dateStr}][breakfast]">
                                        <label class="form-check-label fw-bold" for="breakfast_${dateStr}">
                                            <i class="fas fa-coffee me-2"></i>Breakfast
                                        </label>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12">
                                            <label class="form-label small">Extra Count</label>
                                            <input type="number" class="form-control form-control-sm" name="meals[${dateStr}][breakfast_countx]" min="0" value="0">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Lunch -->
                        <div class="col-lg-6 mb-3">
                            <div class="card bg-light">
                                <div class="card-header bg-success text-white">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="lunch_${dateStr}" name="meals[${dateStr}][lunch]">
                                        <label class="form-check-label fw-bold" for="lunch_${dateStr}">
                                            <i class="fas fa-bowl-food me-2"></i>Lunch
                                        </label>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12">
                                            <label class="form-label small">Extra Count</label>
                                            <input type="number" class="form-control form-control-sm" name="meals[${dateStr}][lunch_countx]" min="0" value="0">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dinner -->
                        <div class="col-lg-6 mb-3">
                            <div class="card bg-light">
                                <div class="card-header bg-primary text-white">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="dinner_${dateStr}" name="meals[${dateStr}][dinner]">
                                        <label class="form-check-label fw-bold" for="dinner_${dateStr}">
                                            <i class="fas fa-utensils me-2"></i>Dinner
                                        </label>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12">
                                            <label class="form-label small">Extra Count</label>
                                            <input type="number" class="form-control form-control-sm" name="meals[${dateStr}][dinner_countx]" min="0" value="0">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Snacks -->
                        <div class="col-lg-6 mb-3">
                            <div class="card bg-light">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0"><i class="fas fa-cookie me-2"></i>Snacks</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-6">
                                            <label class="form-label small">Snack 1</label>
                                            <input type="number" class="form-control form-control-sm" name="meals[${dateStr}][snack1_countx]" min="0" value="0">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small">Snack 2</label>
                                            <input type="number" class="form-control form-control-sm" name="meals[${dateStr}][snack2_count]" min="0" value="0">
                                        </div>
                                        <div class="col-6 mt-2">
                                            <label class="form-label small">Snack 3</label>
                                            <input type="number" class="form-control form-control-sm" name="meals[${dateStr}][snack3_countx]" min="0" value="0">
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

        // Enhanced Event Listeners and Initialization
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize date change listeners
            const startDateInput = document.querySelector('input[name="start_date"]');
            const endDateInput = document.querySelector('input[name="end_date"]');
            const singleDateInput = document.querySelector('input[name="single_date"]');

            if (startDateInput && endDateInput) {
                startDateInput.addEventListener('change', function() {
                    if (document.getElementById('requestMode').value === 'multiple') {
                        generateMultipleDayMeals();
                        updateSummary();
                        updateProgress();
                    }
                });

                endDateInput.addEventListener('change', function() {
                    if (document.getElementById('requestMode').value === 'multiple') {
                        generateMultipleDayMeals();
                        updateSummary();
                        updateProgress();
                    }
                });
            }

            if (singleDateInput) {
                singleDateInput.addEventListener('change', function() {
                    updateSummary();
                    updateProgress();
                });
            }

            // Initialize checkboxes change listeners
            document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    if (this.name === 'breakfast' || this.name === 'lunch' || this.name === 'dinner') {
                        toggleMealCard(this.name);
                    }
                    updateSummary();
                    updateProgress();
                });
            });

            // Initialize form submission validation
            document.getElementById('mealRequestForm').addEventListener('submit', function(e) {
                if (!hasSelectedMeals()) {
                    e.preventDefault();
                    alert('Please select at least one meal before submitting your request.');
                    return false;
                }

                // Show loading state
                const submitBtn = document.getElementById('submitBtn');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
                submitBtn.disabled = true;
            });

            // Initialize summary
            updateSummary();
            updateProgress();

            // Add smooth transitions
            document.querySelectorAll('.meal-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });

                card.addEventListener('mouseleave', function() {
                    if (!this.classList.contains('selected')) {
                        this.style.transform = 'translateY(0)';
                    }
                });
            });
        });

        // Form validation for multiple dates
        document.getElementById('mealRequestForm').addEventListener('submit', function(e) {
            const requestMode = document.getElementById('requestMode').value;

            if (requestMode === 'multiple') {
                const startDate = new Date(document.querySelector('input[name="start_date"]').value);
                const endDate = new Date(document.querySelector('input[name="end_date"]').value);

                if (endDate <= startDate) {
                    e.preventDefault();
                    alert('End date must be after start date');
                    return false;
                }

                const daysDiff = Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24));
                if (daysDiff > 7) {
                    e.preventDefault();
                    alert('Maximum 7 days can be requested at once');
                    return false;
                }
            }
        });
    </script>
        </div> <!-- Close dashboard-content -->
    </div> <!-- Close main-content -->
</body>
</html>