<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

echo "<h2>Adding Sample Meal Request Data</h2>";

$db = new Database();

try {
    // Get existing employees for each location
    $employees_query = "SELECT epf_number, name, location_id FROM employees WHERE is_active = 1 ORDER BY location_id, epf_number";
    $employees = $db->fetchAll($employees_query);

    // Map location IDs to names using config
    $locations_map = LOCATIONS;

    // Add location name to employee data
    foreach ($employees as &$emp) {
        $emp['location_name'] = $locations_map[$emp['location_id']] ?? 'Unknown';
    }

    if (empty($employees)) {
        echo "<p style='color: red;'>No active employees found. Please add employee data first.</p>";
        exit;
    }

    // Group employees by location
    $employees_by_location = [];
    foreach ($employees as $emp) {
        $location = $emp['location_name'];
        if (!isset($employees_by_location[$location])) {
            $employees_by_location[$location] = [];
        }
        $employees_by_location[$location][] = $emp;
    }

    echo "<h3>Available employees by location:</h3>";
    foreach ($employees_by_location as $location => $emps) {
        echo "<p><strong>$location:</strong> " . count($emps) . " employees</p>";
    }

    $today = date('Y-m-d');
    $meal_types = ['Employee Meal', 'Employee Special'];
    $visitor_menu_types = ['Employee Meal', 'Employee Special', 'Seafood - Foreigner', 'Chicken - Foreigner', 'Veg - Foreigner', 'Chicken - Local', 'Fish - Local', 'Veg - Local'];

    // Insert sample employee meal requests
    echo "<h3>Creating sample employee meal requests for today ($today):</h3>";

    $employee_insert_query = "INSERT INTO meal_requests_employee (
        emp_number, emp_name, emp_location, meal_type, request_date,
        breakfast_enabled, breakfast_count, breakfast_countx, snack1_countx,
        lunch_enabled, lunch_count, lunch_countx,
        snack2_enabled, snack2_count, snack2_countx,
        dinner_enabled, dinner_count, dinner_countx, snack3_countx
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $employee_requests_added = 0;
    $locations = ['7C', 'Pannala', 'Kobeigane', 'JECOE', 'Head Office'];

    foreach ($locations as $location) {
        if (!isset($employees_by_location[$location]) || empty($employees_by_location[$location])) {
            echo "<p style='color: orange;'>⚠️ No employees found for location: $location</p>";
            continue;
        }

        // Get 3-5 employees from this location for sample data
        $location_employees = array_slice($employees_by_location[$location], 0, min(5, count($employees_by_location[$location])));

        foreach ($location_employees as $emp) {
            // Random meal selection for variety
            $has_breakfast = rand(0, 1);
            $has_lunch = rand(0, 1);
            $has_dinner = rand(0, 3) > 0 ? 1 : 0; // 75% chance for dinner
            $has_snack2 = rand(0, 2) > 0 ? 1 : 0; // 66% chance for snack

            // Ensure at least one meal is selected
            if (!$has_breakfast && !$has_lunch && !$has_dinner && !$has_snack2) {
                $has_lunch = 1; // Default to lunch
            }

            $breakfast_countx = $has_breakfast ? rand(0, 2) : 0;
            $lunch_countx = $has_lunch ? rand(0, 1) : 0;
            $dinner_countx = $has_dinner ? rand(0, 1) : 0;
            $snack1_countx = rand(0, 2);
            $snack2_countx = $has_snack2 ? rand(0, 1) : 0;
            $snack3_countx = rand(0, 1);

            $meal_type = $meal_types[rand(0, count($meal_types) - 1)];

            $db->query($employee_insert_query, [
                $emp['epf_number'], $emp['name'], $emp['location_name'], $meal_type, $today,
                $has_breakfast, $has_breakfast ? 1 : 0, $breakfast_countx, $snack1_countx,
                $has_lunch, $has_lunch ? 1 : 0, $lunch_countx,
                $has_snack2, $has_snack2 ? 1 : 0, $snack2_countx,
                $has_dinner, $has_dinner ? 1 : 0, $dinner_countx, $snack3_countx
            ]);

            $total_meals = ($has_breakfast ? 1 : 0) + $breakfast_countx + $snack1_countx +
                          ($has_lunch ? 1 : 0) + $lunch_countx + ($has_snack2 ? 1 : 0) + $snack2_countx +
                          ($has_dinner ? 1 : 0) + $dinner_countx + $snack3_countx;

            echo "<p>✓ Added request for <strong>" . htmlspecialchars($emp['name']) . "</strong> (EPF: " . htmlspecialchars($emp['epf_number']) . ") at <strong>$location</strong> - $total_meals meals</p>";
            $employee_requests_added++;
        }
    }

    echo "<h3>Creating sample visitor meal requests for today ($today):</h3>";

    $visitor_insert_query = "INSERT INTO meal_requests_visitor (
        requesting_emp_number, requesting_emp_name, requesting_emp_location,
        visitor_names, visit_purpose, visitor_remarks, request_date,
        breakfast_needed, breakfast_menu, breakfast_count, breakfast_remarks,
        lunch_needed, lunch_menu, lunch_count, lunch_remarks,
        dinner_needed, dinner_menu, dinner_count, dinner_remarks,
        snack1_needed, snack1_count, snack1_remarks,
        snack2_needed, snack2_count, snack2_remarks,
        status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $visit_purposes = ['Training', 'Consultation', 'Audit', 'Meeting', 'Inspection', 'Maintenance', 'Vendor Visit', 'Client Visit'];
    $visitor_names_samples = [
        'John Smith, Jane Doe',
        'Michael Johnson',
        'Sarah Williams, David Brown, Lisa Davis',
        'Robert Wilson',
        'Emily Taylor, James Anderson'
    ];

    $visitor_requests_added = 0;

    foreach ($locations as $location) {
        if (!isset($employees_by_location[$location]) || empty($employees_by_location[$location])) {
            continue;
        }

        // Add 1-2 visitor requests per location
        $visitor_count = rand(1, 2);
        for ($i = 0; $i < $visitor_count; $i++) {
            $requesting_emp = $employees_by_location[$location][0]; // Use first employee

            $visitor_names = $visitor_names_samples[rand(0, count($visitor_names_samples) - 1)];
            $visit_purpose = $visit_purposes[rand(0, count($visit_purposes) - 1)];
            $visitor_remarks = "Sample visitor request for testing purposes";

            $has_breakfast = rand(0, 2) > 0 ? 1 : 0;
            $has_lunch = 1; // Always include lunch for visitors
            $has_dinner = rand(0, 1);
            $has_snack1 = rand(0, 3) > 0 ? 1 : 0;
            $has_snack2 = rand(0, 3) > 0 ? 1 : 0;

            $breakfast_menu = $has_breakfast ? $visitor_menu_types[rand(0, count($visitor_menu_types) - 1)] : '';
            $lunch_menu = $visitor_menu_types[rand(0, count($visitor_menu_types) - 1)];
            $dinner_menu = $has_dinner ? $visitor_menu_types[rand(0, count($visitor_menu_types) - 1)] : '';

            $breakfast_count = $has_breakfast ? rand(2, 5) : 0;
            $lunch_count = rand(2, 6);
            $dinner_count = $has_dinner ? rand(2, 4) : 0;
            $snack1_count = $has_snack1 ? rand(1, 3) : 0;
            $snack2_count = $has_snack2 ? rand(1, 3) : 0;

            $db->query($visitor_insert_query, [
                $requesting_emp['epf_number'], $requesting_emp['name'], $requesting_emp['location_name'],
                $visitor_names, $visit_purpose, $visitor_remarks, $today,
                $has_breakfast, $breakfast_menu, $breakfast_count, $has_breakfast ? 'Special dietary requirements' : '',
                $has_lunch, $lunch_menu, $lunch_count, 'Standard lunch service',
                $has_dinner, $dinner_menu, $dinner_count, $has_dinner ? 'Evening meeting meal' : '',
                $has_snack1, $snack1_count, $has_snack1 ? 'Morning refreshments' : '',
                $has_snack2, $snack2_count, $has_snack2 ? 'Afternoon tea' : '',
                'pending'
            ]);

            $total_visitor_meals = $breakfast_count + $lunch_count + $dinner_count + $snack1_count + $snack2_count;

            echo "<p>✓ Added visitor request by <strong>" . htmlspecialchars($requesting_emp['name']) . "</strong> at <strong>$location</strong>";
            echo "<br>&nbsp;&nbsp;&nbsp;&nbsp;Visitors: <em>" . htmlspecialchars($visitor_names) . "</em>";
            echo "<br>&nbsp;&nbsp;&nbsp;&nbsp;Purpose: <strong>$visit_purpose</strong> - $total_visitor_meals meals</p>";
            $visitor_requests_added++;
        }
    }

    echo "<h2 style='color: green;'>✅ Sample data added successfully!</h2>";
    echo "<p><strong>Summary:</strong></p>";
    echo "<ul>";
    echo "<li>Employee meal requests: <strong>$employee_requests_added</strong></li>";
    echo "<li>Visitor meal requests: <strong>$visitor_requests_added</strong></li>";
    echo "<li>Date: <strong>$today</strong></li>";
    echo "</ul>";

    echo "<p><a href='meal_request_details.php?date=$today' class='btn btn-success'>📋 View Today's Request Details</a></p>";
    echo "<p><a href='daily_overview.php?date=$today' class='btn btn-info'>📊 View Today's Overview</a></p>";
    echo "<p><a href='index.php' class='btn btn-secondary'>🏠 Back to Meal Management</a></p>";

} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Error adding sample data!</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>

<style>
.btn {
    display: inline-block;
    padding: 8px 16px;
    margin: 5px;
    background: #007bff;
    color: white;
    text-decoration: none;
    border-radius: 4px;
}
.btn-success { background: #28a745; }
.btn-info { background: #17a2b8; }
.btn-secondary { background: #6c757d; }
</style>