<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/location_access.php';

// Check if user has permission to view org chart
$db = new Database();
if (!hasModulePermission($db, 'employee.org_chart') && !hasAdminAccess()) {
    header('Location: ../../dashboard.php');
    exit();
}

$user = getCurrentUser();

// Get filter parameters
$display_type = $_GET['display'] ?? 'tree';
$location_filter = $_GET['location'] ?? '';
$department_filter = $_GET['department'] ?? '';

// Get accessible employees based on location/department access
$accessible_employees = getAccessibleEmployeesByLocation($db, $user);

// Apply additional filters
if ($location_filter) {
    $accessible_employees = array_filter($accessible_employees, function($emp) use ($location_filter) {
        return $emp['location_id'] == $location_filter;
    });
}

if ($department_filter) {
    $accessible_employees = array_filter($accessible_employees, function($emp) use ($department_filter) {
        return $emp['department'] == $department_filter;
    });
}

// Get accessible employee EPF numbers for filtering
$accessible_epf_numbers = array_column($accessible_employees, 'epf_number');

if (empty($accessible_epf_numbers)) {
    $employees_with_details = [];
} else {
    // Build query with proper filtering
    $epf_placeholders = str_repeat('?,', count($accessible_epf_numbers) - 1) . '?';

    // Get employees with reporting relationships (same as employee_list.php)
    $query = "
        SELECT e.*,
               l.location_name,
               el.level_name as employment_level_name,
               el.level_order,
               supervisor.name as supervisor_name
        FROM employees e
        LEFT JOIN locations l ON e.location_id = l.id
        LEFT JOIN employment_levels el ON e.employment_level_id = el.id
        LEFT JOIN employees supervisor ON e.reports_to = supervisor.epf_number
        WHERE e.epf_number IN ($epf_placeholders)
        AND e.is_active = 1
        ORDER BY el.level_order ASC, e.name ASC
    ";

    $employees_with_details = $db->fetchAll($query, $accessible_epf_numbers);
}

// Employees are already sorted by the query (level_order ASC, name ASC)

// Build hierarchy tree based on "Reports To" relationships and employment levels
function buildHierarchyTree($employees) {
    $tree = [];
    $lookup = [];

    // Sort employees by level_order to ensure proper hierarchy
    usort($employees, function($a, $b) {
        return ($a['level_order'] ?? 9) <=> ($b['level_order'] ?? 9);
    });

    // Create lookup array
    foreach ($employees as $employee) {
        $lookup[$employee['epf_number']] = $employee;
        $lookup[$employee['epf_number']]['children'] = [];
    }

    // Build tree structure
    foreach ($employees as $employee) {
        if (empty($employee['reports_to']) || !isset($lookup[$employee['reports_to']])) {
            // Top-level employee (no manager or manager not in accessible list)
            $tree[] = &$lookup[$employee['epf_number']];
        } else {
            // Employee reports to someone
            $lookup[$employee['reports_to']]['children'][] = &$lookup[$employee['epf_number']];
        }
    }

    // Sort tree by level order (MD first, then GM, etc.)
    usort($tree, function($a, $b) {
        return ($a['level_order'] ?? 9) <=> ($b['level_order'] ?? 9);
    });

    // Sort children within each node
    sortTreeByLevel($tree);

    return $tree;
}

// Recursive function to sort children by level order
function sortTreeByLevel(&$tree) {
    foreach ($tree as &$node) {
        if (!empty($node['children'])) {
            usort($node['children'], function($a, $b) {
                return ($a['level_order'] ?? 9) <=> ($b['level_order'] ?? 9);
            });
            sortTreeByLevel($node['children']);
        }
    }
}

// For the new vertical layout, we just use the employees array directly
$hierarchy = $employees_with_details;

// Get filter options
$locations = getAccessibleLocations($db, $user);
$departments = getAccessibleDepartments($db, $user);

// Get level-specific color coding - Global function
function getLevelColor($level) {
    $colors = [
        1 => '#8B0000',  // MD - Dark Red
        2 => '#FF4500',  // GM - Orange Red
        3 => '#FFD700',  // Manager - Gold
        4 => '#32CD32',  // Assistant Manager - Lime Green
        5 => '#00CED1',  // Senior Executive - Dark Turquoise
        6 => '#1E90FF',  // Executive - Dodger Blue
        7 => '#9370DB',  // Junior Executive - Medium Purple
        8 => '#FF1493',  // Supervisor - Deep Pink
        9 => '#708090'   // Staff - Slate Gray
    ];
    return $colors[$level] ?? '#6c757d';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Organizational Chart</title>
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

        .org-chart {
            padding: 20px;
            position: relative;
            min-height: 100vh;
            width: 100%;
        }

        /* Full Screen Styles */
        .fullscreen-mode {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            z-index: 9999 !important;
            background: white !important;
            overflow: auto !important;
            padding: 20px !important;
        }

        .fullscreen-mode .org-chart {
            width: 100% !important;
            min-height: 100vh !important;
        }

        /* Vertical Organization Chart Styles */
        .org-chart-vertical {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 30px;
            padding: 20px;
            min-width: max-content;
        }

        .employment-level {
            width: 100%;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            background: #fafafa;
        }

        .level-header .level-title {
            font-weight: bold;
            font-size: 1.4em;
            margin: 0;
            padding: 10px 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .level-employees {
            min-height: 80px;
        }

        .org-box {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            margin: 5px;
        }

        .org-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
        }

        .staff-group-box {
            max-width: 250px;
        }

        .individual-employee {
            max-width: 220px;
        }

        /* Location Tree View Styles */
        .location-org-structure {
            padding: 20px;
            min-width: max-content;
        }

        .top-level-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            border: 2px solid #e9ecef;
        }

        .top-level-box {
            background: white;
            border-radius: 12px;
            padding: 15px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            min-width: 180px;
            position: relative;
        }

        .top-level-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .location-card {
            border-radius: 15px !important;
            overflow: hidden;
            transition: transform 0.3s;
            min-height: 400px;
        }

        .location-card:hover {
            transform: translateY(-5px);
        }

        .location-card .card-header {
            border-radius: 0 !important;
            font-weight: bold;
        }

        .location-employee-box {
            background: white;
            border-radius: 10px;
            padding: 10px;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
            margin-bottom: 10px;
            position: relative;
        }

        .location-employee-box:hover {
            transform: translateY(-2px);
        }

        .staff-group-location {
            background: white;
            border-radius: 10px;
            padding: 5px;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
            margin-bottom: 10px;
            position: relative;
        }

        .staff-group-location:hover {
            transform: translateY(-2px);
        }

        .reporting-line {
            border-left: 2px solid #007bff;
            border-bottom: 2px solid #007bff;
            border-bottom-left-radius: 5px;
        }

        .locations-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
        }

        /* Top Management Hierarchy Styles */
        .management-hierarchy {
            background: #ffffff;
            border-radius: 15px;
            padding: 30px;
        }

        .hierarchy-level {
            position: relative;
        }

        .hierarchy-employee-box {
            background: white;
            border-radius: 10px;
            padding: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .hierarchy-employee-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .hierarchy-line-down {
            position: relative;
            height: 30px;
            width: 2px;
            background: #007bff;
            margin: 0 auto;
        }

        .hierarchy-line-down::before {
            content: '';
            position: absolute;
            bottom: -5px;
            left: -4px;
            width: 10px;
            height: 10px;
            background: #007bff;
            transform: rotate(45deg);
        }

        /* Location Hierarchy Styles */
        .location-hierarchy {
            padding: 10px 0;
        }

        .location-level {
            position: relative;
        }

        .location-hierarchy-line {
            position: relative;
            height: 20px;
            width: 1px;
            background: #6c757d;
            margin: 0 auto;
        }

        .location-hierarchy-line::before {
            content: '';
            position: absolute;
            bottom: -3px;
            left: -3px;
            width: 7px;
            height: 7px;
            background: #6c757d;
            transform: rotate(45deg);
        }

        .location-employee-box {
            background: white;
            border-radius: 8px;
            padding: 8px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
            margin: 2px;
            min-width: 120px;
            max-width: 140px;
        }

        .location-employee-box:hover {
            transform: translateY(-2px);
        }

        .staff-group-compact {
            transition: transform 0.2s;
        }

        .staff-group-compact:hover {
            transform: translateY(-2px);
        }

        /* Actual Reporting Line Styles */
        .reporting-node {
            position: relative;
        }

        .reporting-line-to-children {
            position: relative;
            height: 15px;
            width: 1px;
            background: #007bff;
            margin: 5px auto;
        }

        .reporting-line-to-children::before {
            content: '';
            position: absolute;
            bottom: -3px;
            left: -3px;
            width: 7px;
            height: 7px;
            background: #007bff;
            transform: rotate(45deg);
        }

        .external-supervisor-note {
            padding: 5px;
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            margin-bottom: 10px;
        }

        .external-line {
            height: 10px;
            width: 1px;
            background: #6c757d;
            margin: 5px auto;
            border-style: dashed;
        }

        .supervisor-with-staff {
            position: relative;
        }

        .staff-group-under-supervisor {
            position: relative;
            margin-top: 10px;
        }

        /* Enhanced Container */
        .scrollable-org-chart {
            border: 2px solid #dee2e6;
            border-radius: 15px;
            background: #f8f9fa;
            position: relative;
            width: 100%;
            min-height: calc(100vh - 200px);
        }

        /* Fullscreen Toggle Button */
        .fullscreen-toggle {
            position: absolute;
            top: 15px;
            right: 15px;
            z-index: 1001;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            color: white;
            padding: 10px 15px;
            border-radius: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }

        .fullscreen-toggle:hover {
            background: linear-gradient(135deg, #5a67d8, #6b46c1);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }

        /* Enhanced Tree View */
        .tree-view {
            min-width: max-content;
            padding: 30px;
        }

        .employee-box {
            background: white;
            border: 2px solid #007bff;
            border-radius: 10px;
            padding: 15px;
            margin: 10px;
            text-align: center;
            min-width: 200px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
        }

        .employee-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .employee-box.level-1 { border-color: #dc3545; background: linear-gradient(135deg, #fff 0%, #ffe6e6 100%); }
        .employee-box.level-2 { border-color: #fd7e14; background: linear-gradient(135deg, #fff 0%, #fff2e6 100%); }
        .employee-box.level-3 { border-color: #ffc107; background: linear-gradient(135deg, #fff 0%, #fffbе6 100%); }
        .employee-box.level-4 { border-color: #28a745; background: linear-gradient(135deg, #fff 0%, #e6ffe6 100%); }
        .employee-box.level-5 { border-color: #20c997; background: linear-gradient(135deg, #fff 0%, #e6fff9 100%); }
        .employee-box.level-6 { border-color: #17a2b8; background: linear-gradient(135deg, #fff 0%, #e6f9ff 100%); }
        .employee-box.level-7 { border-color: #6f42c1; background: linear-gradient(135deg, #fff 0%, #f2e6ff 100%); }
        .employee-box.level-8 { border-color: #e83e8c; background: linear-gradient(135deg, #fff 0%, #ffe6f2 100%); }
        .employee-box.level-9 { border-color: #6c757d; background: linear-gradient(135deg, #fff 0%, #f0f0f0 100%); }

        /* Enhanced Organizational Chart Layout (Plan.md 2.4.7) */
        .org-chart-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100%;
            padding: 20px;
            position: relative;
        }

        .org-level {
            display: flex;
            justify-content: center;
            width: 100%;
            margin: 40px 0; /* Plan.md 2.4.7.10 - minimum 80px between levels */
            position: relative;
        }

        .level-container {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 40px; /* Plan.md 2.4.7.11 - minimum 40px between boxes */
            position: relative;
        }

        /* Individual Employee and Staff Group Boxes */
        .org-box {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            position: relative;
            min-width: 180px;
            max-width: 220px;
            text-align: center;
        }

        .org-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
            border-color: #007bff;
        }

        /* Employee Box Styling (Plan.md 2.4.5) */
        .employee-box {
            border-left: 4px solid #007bff;
        }

        /* Staff Group Box Styling (Plan.md 2.4.6) */
        .staff-group-box {
            border-left: 4px solid #708090;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        /* Level-specific styling with colors from getLevelColor() */
        .org-box[data-level="1"] { border-left-color: #8B0000; } /* MD */
        .org-box[data-level="2"] { border-left-color: #FF4500; } /* GM */
        .org-box[data-level="3"] { border-left-color: #FFD700; } /* Manager */
        .org-box[data-level="4"] { border-left-color: #32CD32; } /* Assistant Manager */
        .org-box[data-level="5"] { border-left-color: #00CED1; } /* Senior Executive */
        .org-box[data-level="6"] { border-left-color: #1E90FF; } /* Executive */
        .org-box[data-level="7"] { border-left-color: #9370DB; } /* Junior Executive */
        .org-box[data-level="8"] { border-left-color: #FF1493; } /* Supervisor */
        .org-box[data-level="9"] { border-left-color: #708090; } /* Staff */

        /* Connection Lines (Plan.md 2.4.8 - 90-degree angles only) */
        .org-level:not(:last-child)::after {
            content: '';
            position: absolute;
            bottom: -20px;
            left: 50%;
            transform: translateX(-50%);
            width: 3px;
            height: 20px;
            background: linear-gradient(to bottom, #6c757d, #adb5bd);
            z-index: 1;
        }

        .employee-row {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            margin: 20px 0;
        }

        .children-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 30px;
        }

        /* Connection Lines */
        .connection-line {
            position: absolute;
            background: linear-gradient(135deg, #667eea, #764ba2);
            z-index: 1;
            border-radius: 2px;
        }

        .vertical-line {
            width: 3px;
            background: linear-gradient(to bottom, #667eea, #764ba2);
        }

        .horizontal-line {
            height: 3px;
            background: linear-gradient(to right, #667eea, #764ba2);
        }

        /* Tree Structure */
        .tree-node {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            margin: 0 15px;
        }

        .node-children {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            margin-top: 50px;
            position: relative;
            gap: 30px;
            flex-wrap: wrap;
        }

        /* Vertical line from parent to children */
        .node-children::before {
            content: '';
            position: absolute;
            top: -25px;
            left: 50%;
            transform: translateX(-50%);
            width: 3px;
            height: 25px;
            background: linear-gradient(to bottom, #667eea, #764ba2);
            z-index: 1;
        }

        /* Horizontal line connecting all siblings */
        .node-children::after {
            content: '';
            position: absolute;
            top: -25px;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(to right, #667eea, #764ba2);
            z-index: 0;
        }

        /* Individual connection lines for each child */
        .tree-node:not(:only-child)::before {
            content: '';
            position: absolute;
            top: -25px;
            left: 50%;
            transform: translateX(-50%);
            width: 3px;
            height: 25px;
            background: linear-gradient(to bottom, #667eea, #764ba2);
            z-index: 2;
        }

        .list-view .employee-item {
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin: 5px 0;
            background: white;
        }

        /* List View - Enable Normal Scrolling */
        .list-view {
            overflow-y: auto !important;
            overflow-x: hidden !important;
            max-height: calc(100vh - 300px) !important;
            cursor: default !important;
            user-select: text !important;
        }

        /* Override drag navigation for list view */
        .scrollable-org-chart:has(.list-view) {
            overflow: hidden !important;
        }

        .scrollable-org-chart .list-view {
            overflow-y: auto !important;
            height: 100% !important;
            cursor: default !important;
        }

        /* Enhanced List View Styling */
        .list-view {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%) !important;
            border-radius: 15px !important;
            padding: 20px !important;
        }

        /* Force left alignment for all list view content */
        .list-view .row,
        .list-view .col-md-1,
        .list-view .col-md-2,
        .list-view .col-md-3,
        .list-view .col-md-4,
        .list-view .col-md-5,
        .list-view .col-md-6,
        .list-view .col-md-7,
        .list-view .col-md-8,
        .list-view .col-md-9,
        .list-view .col-md-10,
        .list-view .col-md-11,
        .list-view .col-md-12 {
            text-align: left !important;
            justify-content: flex-start !important;
        }

        .list-view .employee-row * {
            text-align: left !important;
        }

        .list-view .badge {
            text-align: center !important;
        }

        /* Fix flexbox alignment issues */
        .list-view .d-flex {
            justify-content: flex-start !important;
            align-items: flex-start !important;
        }

        .list-view .row {
            justify-content: flex-start !important;
            align-items: flex-start !important;
        }

        /* Override any Bootstrap centering classes */
        .list-view .align-items-center {
            align-items: flex-start !important;
        }

        .list-view .align-items-end {
            align-items: flex-start !important;
        }

        .list-view .align-items-stretch {
            align-items: flex-start !important;
        }

        /* Ensure all Bootstrap columns start from left */
        .list-view [class*="col-"] {
            display: flex !important;
            flex-direction: column !important;
            align-items: flex-start !important;
            justify-content: flex-start !important;
            text-align: left !important;
        }

        /* Special handling for col-md-1 which has d-flex */
        .list-view .col-md-1.d-flex {
            flex-direction: row !important;
            align-items: center !important;
            justify-content: flex-start !important;
        }

        /* Override Bootstrap's justify-content defaults */
        .list-view .justify-content-center {
            justify-content: flex-start !important;
        }

        .list-view .justify-content-end {
            justify-content: flex-start !important;
        }

        .list-view .justify-content-around {
            justify-content: flex-start !important;
        }

        .list-view .justify-content-between {
            justify-content: flex-start !important;
        }

        .list-view .justify-content-evenly {
            justify-content: flex-start !important;
        }

        /* Force text content inside divs to start from left */
        .list-view .employee-row div {
            text-align: left !important;
            justify-content: flex-start !important;
            align-items: flex-start !important;
            padding-left: 0 !important;
            margin-left: 0 !important;
        }

        .list-view .employee-row h6,
        .list-view .employee-row small,
        .list-view .employee-row p,
        .list-view .employee-row span:not(.badge) {
            text-align: left !important;
            margin-left: 0 !important;
            padding-left: 0 !important;
            display: block !important;
            width: 100% !important;
        }

        /* Remove any default Bootstrap padding/margins */
        .list-view .col-md-1,
        .list-view .col-md-2,
        .list-view .col-md-3 {
            padding-left: 0 !important;
            padding-right: 8px !important;
        }

        /* Force content containers to have no padding */
        .list-view .employee-row > .row > div > div {
            padding: 0 !important;
            margin: 0 !important;
            width: 100% !important;
        }

        /* Override Bootstrap's default column padding for tight left alignment */
        .list-view .row {
            margin-left: 0 !important;
            margin-right: 0 !important;
        }

        .list-view .row > * {
            padding-left: 0 !important;
            padding-right: 8px !important;
        }

        /* Force absolute left positioning */
        .list-view .col-md-1,
        .list-view .col-md-2,
        .list-view .col-md-3 {
            padding-left: 0 !important;
        }

        /* Zero out all possible spacing sources */
        .list-view .employee-row {
            padding-left: 0 !important;
        }

        .list-view .employee-row .row {
            padding-left: 0 !important;
            margin-left: 0 !important;
        }

        .list-view .employee-row [class*="col-"] {
            padding-left: 0 !important;
            margin-left: 0 !important;
        }

        .list-view .employee-row [class*="col-"] > div {
            padding-left: 0 !important;
            margin-left: 0 !important;
        }

        /* Ensure text elements touch the left edge */
        .list-view .fw-bold,
        .list-view .fw-semibold,
        .list-view .text-muted {
            margin: 0 !important;
            padding: 0 !important;
            text-align: left !important;
            display: block !important;
        }

        /* Fix flexbox alignment in all columns */
        .list-view .col-md-1.d-flex,
        .list-view .col-md-2.d-flex,
        .list-view .col-md-3.d-flex {
            justify-content: flex-start !important;
            align-items: flex-start !important;
            text-align: left !important;
        }

        .list-view [class*="col-"].d-flex {
            justify-content: flex-start !important;
            align-items: flex-start !important;
            text-align: left !important;
        }

        /* Force all column content to align left */
        .list-view [class*="col-"] > div,
        .list-view [class*="col-"] > span,
        .list-view [class*="col-"] > p,
        .list-view [class*="col-"] > h1,
        .list-view [class*="col-"] > h2,
        .list-view [class*="col-"] > h3,
        .list-view [class*="col-"] > h4,
        .list-view [class*="col-"] > h5,
        .list-view [class*="col-"] > h6 {
            text-align: left !important;
            display: flex !important;
            flex-direction: column !important;
            align-items: flex-start !important;
            justify-content: flex-start !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        /* Ultra-aggressive left alignment for all possible elements */
        .list-view * {
            text-align: left !important;
        }

        .list-view .employee-row * {
            justify-content: flex-start !important;
            align-items: flex-start !important;
            text-align: left !important;
        }

        .list-view .col-md-1 *,
        .list-view .col-md-2 *,
        .list-view .col-md-3 * {
            text-align: left !important;
            justify-content: flex-start !important;
            align-items: flex-start !important;
            margin-left: 0 !important;
            padding-left: 0 !important;
        }

        /* TARGETED FIX: Override only Bootstrap grid centering */
        .list-view .employee-row {
            display: flex !important;
            width: 100% !important;
            text-align: left !important;
        }

        .list-view .employee-row > div[class*="col-"] {
            text-align: left !important;
            display: block !important;
            padding: 0 8px !important;
            margin: 0 !important;
        }

        /* Specific column widths to match Bootstrap but without centering */
        .list-view .col-md-1 { flex: 0 0 8.333333% !important; }
        .list-view .col-md-2 { flex: 0 0 16.666667% !important; }
        .list-view .col-md-3 { flex: 0 0 25% !important; }

        /* Force all content inside columns to left align */
        .list-view .col-md-1 *,
        .list-view .col-md-2 *,
        .list-view .col-md-3 * {
            text-align: left !important;
            display: block !important;
            width: 100% !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        /* Override Bootstrap's default centered grid behavior */
        .list-view .container,
        .list-view .container-fluid {
            text-align: left !important;
        }

        .list-view .row {
            text-align: left !important;
            display: flex !important;
            justify-content: flex-start !important;
            align-items: flex-start !important;
        }

        .list-view [class^="col-"],
        .list-view [class*=" col-"] {
            text-align: left !important;
            display: flex !important;
            flex-direction: column !important;
            align-items: flex-start !important;
            justify-content: flex-start !important;
            padding-left: 0 !important;
            margin-left: 0 !important;
        }

        /* Expandable employee item enhancements */
        .expandable-employee-item {
            position: relative;
        }

        .expandable-employee-item::before {
            content: '';
            position: absolute;
            left: -10px;
            top: 50%;
            width: 2px;
            height: 100%;
            background: linear-gradient(to bottom, transparent 0%, #007bff 50%, transparent 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .expandable-employee-item:hover::before {
            opacity: 0.3;
        }

        /* Avatar transitions */
        .employee-avatar {
            transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94) !important;
        }

        /* Badge animations */
        .badge {
            transition: all 0.2s ease !important;
        }

        .employee-row:hover .badge {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        /* Enhanced expand button */
        .expand-btn {
            transition: all 0.2s ease !important;
            border: 2px solid #007bff !important;
            background: white !important;
            color: #007bff !important;
        }

        .expand-btn:hover {
            background: #007bff !important;
            color: white !important;
            transform: scale(1.1) !important;
        }

        .expand-btn.expanded {
            background: #28a745 !important;
            border-color: #28a745 !important;
            color: white !important;
        }

        /* Children container animation */
        .children-container {
            animation-duration: 0.3s;
            animation-fill-mode: both;
        }

        .children-container.expanding {
            animation-name: expandDown;
        }

        .children-container.collapsing {
            animation-name: collapseUp;
        }

        @keyframes expandDown {
            from {
                opacity: 0;
                max-height: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                max-height: 1000px;
                transform: translateY(0);
            }
        }

        @keyframes collapseUp {
            from {
                opacity: 1;
                max-height: 1000px;
                transform: translateY(0);
            }
            to {
                opacity: 0;
                max-height: 0;
                transform: translateY(-10px);
            }
        }

        .list-view .level-indent-1 { margin-left: 0px; }
        .list-view .level-indent-2 { margin-left: 20px; }
        .list-view .level-indent-3 { margin-left: 40px; }
        .list-view .level-indent-4 { margin-left: 60px; }
        .list-view .level-indent-5 { margin-left: 80px; }
        .list-view .level-indent-6 { margin-left: 100px; }
        .list-view .level-indent-7 { margin-left: 120px; }
        .list-view .level-indent-8 { margin-left: 140px; }
        .list-view .level-indent-9 { margin-left: 160px; }

        .export-buttons {
            position: sticky;
            top: 20px;
            z-index: 1000;
            background: white;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        @media print {
            .export-buttons, .top-navbar, .sidebar, nav { display: none !important; }
            .main-content { margin-left: 0 !important; }
        }

        /* Employment Level Alignment and Spacing */
        .employment-level {
            position: relative;
            margin-bottom: 120px !important; /* Much larger space for reporting lines */
            padding-top: 80px !important; /* Extra padding at top for lines */
            width: 100% !important; /* Use full available width */
            min-width: max-content !important; /* Expand as needed for content */
        }

        .level-employees {
            display: flex !important;
            justify-content: center !important;
            align-items: flex-start !important; /* Align all boxes at the same vertical level */
            flex-wrap: nowrap !important; /* Single horizontal row - no wrapping */
            gap: 20px !important; /* Spacing between employees */
            padding: 30px 20px !important; /* Extra padding for lines */
            min-height: 200px !important; /* Consistent height for each level */
            overflow: visible !important; /* NO SCROLL BARS */
        }

        /* Individual Employee Box Standardization */
        .individual-employee {
            flex: 0 0 auto !important; /* Prevent flex stretching */
            position: relative !important;
            margin: 15px !important; /* Consistent margins for line spacing */
        }

        /* Staff Group Alignment */
        .staff-groups {
            display: flex !important;
            justify-content: center !important;
            align-items: flex-start !important; /* Same vertical alignment as individual boxes */
            flex-wrap: nowrap !important; /* Single horizontal row for staff groups too */
            gap: 20px !important;
            padding: 20px !important;
            overflow: visible !important; /* NO SCROLL BARS */
        }

        .staff-group-compact {
            flex: 0 0 auto !important;
            position: relative !important;
            margin: 15px !important;
            min-height: 160px !important; /* Match individual employee box height */
        }

        /* Org Chart Vertical Structure */
        .org-chart-vertical {
            position: relative;
            padding: 40px 20px; /* Extra padding for reporting lines */
            min-height: 100vh;
            background: white;
            min-width: 100%; /* Use full width */
            overflow-x: visible; /* Allow content to extend beyond container */
        }

        /* Scrollable Container - Mouse Drag Navigation */
        .scrollable-org-chart {
            overflow: hidden !important; /* No scroll bars */
            width: 100% !important;
            height: calc(100vh - 200px) !important;
            cursor: grab !important;
            user-select: none !important;
        }

        .scrollable-org-chart:active {
            cursor: grabbing !important;
        }

        /* Fullscreen Mode - Use Entire Screen */
        .fullscreen-mode .scrollable-org-chart {
            width: 100vw !important;
            height: 100vh !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .fullscreen-mode .main-content {
            margin-left: 0 !important; /* No sidebar space in fullscreen */
            width: 100vw !important;
        }

        /* Level Title Styling */
        .level-header {
            margin-bottom: 20px !important;
            text-align: center !important;
        }

        .level-title {
            font-size: 1.4rem !important;
            font-weight: 600 !important;
            margin: 0 !important;
            padding: 10px 20px !important;
            border-radius: 25px !important;
            display: inline-block !important;
        }

        /* SVG Line Container - Scale with zoom */
        .simple-reporting-svg {
            transform-origin: top left;
            transition: none !important; /* No animation to prevent lag during zoom */
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
                        <h1><i class="fas fa-sitemap me-3"></i>Organization Chart</h1>
                        <p>Visual representation of company hierarchy and reporting structure</p>
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
                    <li class="breadcrumb-item"><a href="index.php">Employee Management</a></li>
                    <li class="breadcrumb-item active">Organization Chart</li>
                </ol>
            </nav>

            <!-- Export Buttons -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3><i class="fas fa-sitemap me-2"></i>Organizational Structure</h3>
                    <p class="text-muted mb-0">Interactive view of company hierarchy</p>
                </div>
                <div>
                    <button onclick="toggleFullscreen()" class="btn btn-primary btn-sm me-2" id="fullscreenBtn">
                        <i class="fas fa-expand me-1"></i>Full Screen
                    </button>
                    <button onclick="window.print()" class="btn btn-outline-primary btn-sm me-2">
                        <i class="fas fa-print me-1"></i>Print
                    </button>
                    <button onclick="exportToPNG()" class="btn btn-outline-success btn-sm me-2">
                        <i class="fas fa-image me-1"></i>Export PNG
                    </button>
                    <button onclick="exportToPDF()" class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-file-pdf me-1"></i>Export PDF
                    </button>
                </div>
            </div>

            <!-- Filters and Display Type -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <!-- 2.4.1 Display Type -->
                        <div class="col-md-3">
                            <label class="form-label">Display Type</label>
                            <select class="form-select" name="display">
                                <option value="tree" <?php echo $display_type == 'tree' ? 'selected' : ''; ?>>Tree View</option>
                                <option value="location_tree" <?php echo $display_type == 'location_tree' ? 'selected' : ''; ?>>Location Tree View</option>
                                <option value="list" <?php echo $display_type == 'list' ? 'selected' : ''; ?>>List View</option>
                            </select>
                        </div>

                        <!-- 2.4.2 Filter by Location -->
                        <div class="col-md-3">
                            <label class="form-label">Filter by Location</label>
                            <select class="form-select" name="location">
                                <option value="">All Locations</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?php echo $location['id']; ?>"
                                            <?php echo $location_filter == $location['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($location['location_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- 2.4.3 Filter by Department -->
                        <div class="col-md-3">
                            <label class="form-label">Filter by Department</label>
                            <select class="form-select" name="department">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept['department']); ?>"
                                            <?php echo $department_filter == $dept['department'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['department']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter me-2"></i>Apply Filters
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Organizational Chart Display -->
            <div class="card border-0 shadow-sm" id="orgChartContainer">
                <button class="fullscreen-toggle" onclick="toggleFullscreen()" id="fullscreenToggle">
                    <i class="fas fa-expand" id="fullscreenIcon"></i>
                </button>
                <div class="card-body p-0">
                    <div class="scrollable-org-chart" id="scrollableContainer">
                        <?php if ($display_type === 'tree'): ?>
                            <!-- 2.4.1.1 Tree View -->
                            <div class="org-chart tree-view" id="treeView">
                            <?php
                            // Enhanced org chart functions following Plan.md requirements

                            function renderTreeView($employees, $level = 0) {
                                if (empty($employees)) return;

                                // Group employees by employment level (1-9)
                                $employeesByLevel = [];
                                for ($i = 1; $i <= 9; $i++) {
                                    $employeesByLevel[$i] = [];
                                }

                                // Organize employees by their employment levels
                                foreach ($employees as $employee) {
                                    $level_order = $employee['level_order'] ?? 9;
                                    $employeesByLevel[$level_order][] = $employee;
                                }

                                // Render each employment level vertically
                                echo '<div class="org-chart-vertical">';

                                for ($level = 1; $level <= 9; $level++) {
                                    if (!empty($employeesByLevel[$level])) {
                                        renderEmploymentLevel($level, $employeesByLevel[$level]);
                                    }
                                }

                                echo '</div>';
                            }

                            // Render employment level with proper grouping
                            function renderEmploymentLevel($level, $employees) {
                                $levelNames = [
                                    1 => 'MD', 2 => 'GM', 3 => 'Manager', 4 => 'Assistant Manager',
                                    5 => 'Senior Executive', 6 => 'Executive', 7 => 'Junior Executive',
                                    8 => 'Supervisor', 9 => 'Staff'
                                ];

                                $levelName = $levelNames[$level] ?? 'Unknown';
                                $levelColor = getLevelColor($level);

                                echo '<div class="employment-level mb-4" data-level="' . $level . '">';
                                echo '<div class="level-header text-center mb-3">';
                                echo '<h5 class="level-title" style="color: ' . $levelColor . '; border-bottom: 2px solid ' . $levelColor . '; display: inline-block; padding-bottom: 5px;">';
                                echo $levelName;
                                echo '</h5>';
                                echo '</div>';

                                echo '<div class="level-employees d-flex flex-wrap justify-content-center align-items-start gap-3" style="margin: 40px 0; min-height: 120px; padding: 20px 0;">';

                                if ($level == 9) {
                                    // Staff level - group by supervisor and location
                                    renderStaffGroups($employees);
                                } else {
                                    // Individual boxes for levels 1-8
                                    foreach ($employees as $employee) {
                                        renderIndividualEmployee($employee, $levelColor);
                                    }
                                }

                                echo '</div>';
                                echo '</div>';
                            }

                            // Render staff groups by supervisor and location
                            function renderStaffGroups($staffEmployees) {
                                $staffGroups = [];

                                // Group staff by supervisor and location
                                foreach ($staffEmployees as $employee) {
                                    if (!empty($employee['reports_to'])) {
                                        $key = $employee['reports_to'] . '_' . ($employee['location_name'] ?? 'Unknown');
                                        if (!isset($staffGroups[$key])) {
                                            $staffGroups[$key] = [
                                                'supervisor_epf' => $employee['reports_to'],
                                                'supervisor_name' => $employee['supervisor_name'] ?? 'Unknown Supervisor',
                                                'location_name' => $employee['location_name'] ?? 'Unknown',
                                                'staff_count' => 0,
                                                'staff_list' => []
                                            ];
                                        }
                                        $staffGroups[$key]['staff_count']++;
                                        $staffGroups[$key]['staff_list'][] = $employee;
                                    }
                                }

                                // Render each staff group
                                foreach ($staffGroups as $group) {
                                    renderStaffGroupBox($group);
                                }
                            }

                            // Render individual staff group box
                            function renderStaffGroupBox($group) {
                                $levelColor = getLevelColor(9);

                                echo '<div class="staff-group-box org-box" style="border: 2px solid ' . $levelColor . '; min-width: 200px;">';
                                echo '<div class="group-header text-center" style="background: ' . $levelColor . '; color: white; padding: 8px; margin: -1px -1px 10px -1px;">';
                                echo '<strong>Staff Group</strong>';
                                echo '</div>';

                                echo '<div class="group-content text-center p-2">';
                                echo '<div class="supervisor-info mb-2">';
                                echo '<strong>Reports to:</strong><br>';
                                echo '<span class="text-primary">' . htmlspecialchars($group['supervisor_name']) . '</span><br>';
                                echo '<small class="text-muted">EPF: ' . htmlspecialchars($group['supervisor_epf']) . '</small>';
                                echo '</div>';

                                echo '<div class="staff-count mb-2">';
                                echo '<span class="badge bg-primary fs-6">Total: ' . $group['staff_count'] . ' Staff</span>';
                                echo '</div>';

                                echo '<div class="location-info">';
                                echo '<i class="fas fa-map-marker-alt text-primary me-1"></i>';
                                echo '<span class="text-muted">' . htmlspecialchars($group['location_name']) . '</span>';
                                echo '</div>';
                                echo '</div>';

                                echo '</div>';
                            }

                            // Render individual employee box
                            function renderIndividualEmployee($employee, $levelColor) {
                                $epfNumber = $employee['epf_number'] ?? '';
                                $reportsTo = $employee['reports_to'] ?? '';

                                echo '<div class="individual-employee org-box employee-with-lines" ';
                                echo 'data-epf="' . htmlspecialchars($epfNumber) . '" ';
                                echo 'data-reports-to="' . htmlspecialchars($reportsTo) . '" ';
                                echo 'style="border: 2px solid ' . $levelColor . '; min-width: 220px; max-width: 220px; height: 160px; margin: 5px; position: relative; background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); overflow: hidden;">';

                                // Employee Avatar
                                echo '<div class="employee-avatar mx-auto mb-2" style="background: ' . $levelColor . '; width: 50px; height: 50px; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 18px; margin-top: 10px;">';
                                echo strtoupper(substr($employee['name'] ?? 'U', 0, 1));
                                echo '</div>';

                                // Employee Info
                                echo '<div class="employee-info text-center p-2" style="overflow: hidden; height: 100px;">';
                                echo '<h6 class="employee-name mb-1" style="font-size: 0.85rem; line-height: 1.1; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin: 0 5px;">' . htmlspecialchars($employee['name'] ?? 'Unknown') . '</h6>';
                                echo '<div class="employee-epf text-muted mb-1" style="font-size: 0.7rem; margin: 2px 0;">EPF: ' . htmlspecialchars($epfNumber) . '</div>';
                                echo '<div class="employee-designation text-primary mb-1" style="font-size: 0.75rem; line-height: 1.2; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin: 2px 5px;">' . htmlspecialchars($employee['designation'] ?? 'N/A') . '</div>';
                                echo '<div class="employee-level badge mb-1" style="background: ' . $levelColor . '; font-size: 0.65rem; padding: 2px 6px; margin: 3px auto;">' . htmlspecialchars($employee['employment_level_name'] ?? 'N/A') . '</div>';
                                echo '<div class="employee-location text-muted" style="font-size: 0.65rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin: 0 5px;">';
                                echo '<i class="fas fa-map-marker-alt me-1"></i>' . htmlspecialchars($employee['location_name'] ?? 'N/A');
                                echo '</div>';
                                echo '</div>';

                                echo '</div>';
                            }

                            // Group staff members by supervisor (Plan.md 2.4.4.5)
                            function groupStaffBySupervisor($employees) {
                                $grouped = [];
                                $staffGroups = [];

                                foreach ($employees as $employee) {
                                    // Check if employee is Staff level (level_order = 9) AND has a valid supervisor
                                    if (($employee['level_order'] ?? 9) == 9 && !empty($employee['reports_to'])) {
                                        $supervisor = $employee['reports_to'];

                                        if (!isset($staffGroups[$supervisor])) {
                                            $staffGroups[$supervisor] = [
                                                'type' => 'staff_group',
                                                'supervisor_epf' => $supervisor,
                                                'supervisor_name' => '',
                                                'location_name' => $employee['location_name'] ?? 'Unknown',
                                                'level_order' => 9,
                                                'staff_count' => 0,
                                                'staff_list' => []
                                            ];
                                        }

                                        $staffGroups[$supervisor]['staff_count']++;
                                        $staffGroups[$supervisor]['staff_list'][] = $employee;

                                        // Get supervisor name from the first staff member's reference
                                        if (empty($staffGroups[$supervisor]['supervisor_name'])) {
                                            // Find supervisor name from employees list
                                            foreach ($employees as $emp) {
                                                if ($emp['epf_number'] == $supervisor) {
                                                    $staffGroups[$supervisor]['supervisor_name'] = $emp['name'];
                                                    break;
                                                }
                                            }
                                        }
                                    } else if (($employee['level_order'] ?? 9) != 9) {
                                        // Non-staff employees remain individual
                                        $grouped[] = $employee;
                                    }
                                    // Staff members without valid supervisors are excluded
                                }

                                // Add staff groups to the main array
                                foreach ($staffGroups as $group) {
                                    $grouped[] = $group;
                                }

                                return $grouped;
                            }

                            // Render individual employee box (Plan.md 2.4.5)
                            function renderEmployeeNode($employee) {
                                $level_class = 'level-' . ($employee['level_order'] ?? 9);
                                $level_color = getLevelColor($employee['level_order'] ?? 9);

                                echo '<div class="org-box employee-box ' . $level_class . '" data-level="' . ($employee['level_order'] ?? 9) . '">';

                                // Employee Avatar
                                echo '<div class="employee-avatar" style="background: ' . $level_color . '; width: 50px; height: 50px; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 18px; margin: 0 auto 10px auto;">';
                                echo strtoupper(substr($employee['name'] ?? 'U', 0, 1));
                                echo '</div>';

                                // Employee Details (Plan.md 2.4.5.1 - 2.4.5.5)
                                echo '<div class="employee-info text-center">';
                                echo '<h6 class="emp-name mb-1 fw-bold">' . htmlspecialchars($employee['name']) . '</h6>';
                                echo '<div class="emp-epf small text-secondary">EPF: ' . htmlspecialchars($employee['epf_number']) . '</div>';
                                echo '<div class="emp-designation small text-muted">' . htmlspecialchars($employee['designation']) . '</div>';
                                echo '<span class="badge bg-primary small mt-1">' . htmlspecialchars($employee['level_name'] ?? 'Staff') . '</span><br>';
                                echo '<span class="badge bg-info small mt-1">' . htmlspecialchars($employee['location_name']) . '</span>';
                                echo '</div>';

                                echo '</div>';
                            }

                            // Render staff group box (Plan.md 2.4.6)
                            function renderStaffGroupNode($group) {
                                echo '<div class="org-box staff-group-box" data-level="9">';

                                // Staff Group Avatar
                                echo '<div class="staff-avatar" style="background: #708090; width: 50px; height: 50px; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; margin: 0 auto 10px auto;">';
                                echo '<i class="fas fa-users"></i>';
                                echo '</div>';

                                // Staff Group Details (Plan.md 2.4.6.1 - 2.4.6.4)
                                echo '<div class="staff-info text-center">';
                                echo '<h6 class="staff-label mb-1 fw-bold">Staff Group</h6>';
                                echo '<div class="reports-to small"><strong>Reports to:</strong><br>' . htmlspecialchars($group['supervisor_name']) . '</div>';
                                echo '<div class="reports-to-epf small text-secondary">EPF: ' . htmlspecialchars($group['supervisor_epf']) . '</div>';
                                echo '<span class="badge bg-success mt-1">Total: ' . $group['staff_count'] . ' Staff</span><br>';
                                echo '<span class="badge bg-info small mt-1">' . htmlspecialchars($group['location_name']) . '</span>';
                                echo '</div>';

                                echo '</div>';
                            }

                            // Render complete organizational chart with levels (Plan.md 2.4.7)
                            function renderOrganizationalChart($employees) {
                                if (empty($employees)) return;

                                echo '<div class="org-chart-container">';

                                // Group employees by level (Plan.md 2.4.7.1 - 2.4.7.9)
                                $levels = [];
                                foreach ($employees as $employee) {
                                    $level = $employee['level_order'] ?? 9;
                                    $levels[$level][] = $employee;
                                }

                                // Sort levels and render each level
                                ksort($levels);

                                foreach ($levels as $level_num => $level_employees) {
                                    echo '<div class="org-level" data-level="' . $level_num . '">';
                                    echo '<div class="level-container">';

                                    foreach ($level_employees as $employee) {
                                        if (isset($employee['type']) && $employee['type'] === 'staff_group') {
                                            renderStaffGroupNode($employee);
                                        } else {
                                            renderEmployeeNode($employee);
                                        }
                                    }

                                    echo '</div>';
                                    echo '</div>';
                                }

                                echo '</div>';
                            }


                            if (empty($hierarchy)) {
                                echo '<div class="text-center py-5">';
                                echo '<i class="fas fa-sitemap fa-3x text-muted mb-3"></i>';
                                echo '<p class="text-muted">No organizational structure found with current filters</p>';
                                echo '</div>';
                            } else {
                                renderTreeView($hierarchy);
                            }
                            ?>
                            </div>
                        <?php elseif ($display_type === 'location_tree'): ?>
                            <!-- 2.4.1.3 Location Tree View -->
                            <div class="org-chart location-tree-view" id="locationTreeView">
                            <?php
                            function renderLocationTreeView($employees) {
                                if (empty($employees)) return;

                                // Group all employees by their employment level
                                $levelGroups = [];
                                for ($i = 1; $i <= 9; $i++) {
                                    $levelGroups[$i] = [];
                                }

                                foreach ($employees as $employee) {
                                    $level = $employee['level_order'] ?? 9;
                                    $levelGroups[$level][] = $employee;
                                }

                                echo '<div class="simple-org-chart">';

                                // Render each level vertically
                                for ($level = 1; $level <= 9; $level++) {
                                    if (!empty($levelGroups[$level])) {
                                        renderSimpleLevel($level, $levelGroups[$level]);
                                    }
                                }

                                echo '</div>';

                                // Add JavaScript to draw reporting lines
                                echo '<script>';
                                echo 'console.log("Location tree view rendered, setting up line drawing...");';
                                echo 'setTimeout(function() { ';
                                echo '  console.log("Timeout fired, attempting to draw lines...");';
                                echo '  if (typeof drawSimpleReportingLines === "function") {';
                                echo '    drawSimpleReportingLines();';
                                echo '  } else {';
                                echo '    console.error("drawSimpleReportingLines function not found");';
                                echo '  }';
                                echo '}, 1000);';
                                echo '</script>';
                            }

                            function renderSimpleLevel($level, $employees) {
                                $levelNames = [
                                    1 => 'Managing Director',
                                    2 => 'General Manager',
                                    3 => 'Manager',
                                    4 => 'Assistant Manager',
                                    5 => 'Senior Executive',
                                    6 => 'Executive',
                                    7 => 'Junior Executive',
                                    8 => 'Supervisor',
                                    9 => 'Staff'
                                ];

                                $levelColor = getLevelColor($level);
                                $levelName = $levelNames[$level] ?? 'Level ' . $level;

                                echo '<div class="simple-level mb-5" data-level="' . $level . '">';
                                echo '<div class="level-header text-center mb-4">';
                                echo '<h4 style="color: ' . $levelColor . '; padding: 15px; background: white; border-radius: 10px; display: inline-block; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">';
                                echo 'Level ' . $level . ' - ' . $levelName;
                                echo '</h4>';
                                echo '</div>';

                                echo '<div class="level-employees d-flex flex-wrap justify-content-center gap-4">';

                                // Render all levels as individual employee boxes for line connections
                                foreach ($employees as $employee) {
                                    renderSimpleEmployeeBox($employee);
                                }

                                echo '</div>';
                                echo '</div>';
                            }

                            function renderSimpleEmployeeBox($employee) {
                                $level = $employee['level_order'] ?? 9;
                                $levelColor = getLevelColor($level);

                                echo '<div class="simple-employee-box" ';
                                echo 'data-epf="' . htmlspecialchars($employee['epf_number']) . '" ';
                                echo 'data-reports-to="' . htmlspecialchars($employee['reports_to'] ?? '') . '" ';
                                echo 'style="border: 2px solid ' . $levelColor . '; background: white; border-radius: 12px; padding: 20px; min-width: 250px; text-align: center; position: relative; z-index: 10;">';

                                // Employee Avatar
                                echo '<div class="employee-avatar mx-auto mb-3" style="background: ' . $levelColor . '; width: 60px; height: 60px; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.5em;">';
                                echo strtoupper(substr($employee['name'] ?? 'U', 0, 1));
                                echo '</div>';

                                // Employee Info
                                echo '<h5 class="employee-name mb-2">' . htmlspecialchars($employee['name'] ?? 'Unknown') . '</h5>';
                                echo '<div class="employee-epf text-muted mb-2">EPF: ' . htmlspecialchars($employee['epf_number'] ?? 'N/A') . '</div>';
                                echo '<div class="employee-designation text-primary mb-2">' . htmlspecialchars($employee['designation'] ?? 'N/A') . '</div>';
                                echo '<div class="employee-location mb-2">';
                                echo '<i class="fas fa-map-marker-alt text-info me-1"></i>' . htmlspecialchars($employee['location_name'] ?? 'N/A');
                                echo '</div>';

                                // Show reporting info
                                if (!empty($employee['reports_to']) && !empty($employee['supervisor_name'])) {
                                    echo '<div class="reports-to-info" style="background: #e3f2fd; padding: 8px; border-radius: 6px; margin-top: 10px;">';
                                    echo '<small><strong>Reports to:</strong><br>' . htmlspecialchars($employee['supervisor_name']) . '</small>';
                                    echo '</div>';
                                }

                                echo '</div>';
                            }

                            function renderSimpleStaffGroups($staffEmployees) {
                                $staffGroups = [];

                                // Group staff by supervisor
                                foreach ($staffEmployees as $employee) {
                                    if (!empty($employee['reports_to'])) {
                                        $supervisor = $employee['reports_to'];
                                        if (!isset($staffGroups[$supervisor])) {
                                            $staffGroups[$supervisor] = [
                                                'supervisor_epf' => $supervisor,
                                                'supervisor_name' => $employee['supervisor_name'] ?? 'Unknown Supervisor',
                                                'staff_count' => 0,
                                                'staff_list' => []
                                            ];
                                        }
                                        $staffGroups[$supervisor]['staff_count']++;
                                        $staffGroups[$supervisor]['staff_list'][] = $employee;
                                    }
                                }

                                // Render each staff group
                                foreach ($staffGroups as $group) {
                                    $levelColor = getLevelColor(9);

                                    echo '<div class="simple-staff-group" ';
                                    echo 'data-supervisor-epf="' . htmlspecialchars($group['supervisor_epf']) . '" ';
                                    echo 'style="border: 2px solid ' . $levelColor . '; background: white; border-radius: 12px; padding: 20px; min-width: 250px; text-align: center; position: relative; z-index: 10;">';

                                    echo '<div class="group-header mb-3" style="background: ' . $levelColor . '; color: white; padding: 10px; margin: -20px -20px 15px -20px; border-radius: 10px 10px 0 0;">';
                                    echo '<h6 class="mb-0">Staff Group</h6>';
                                    echo '</div>';

                                    echo '<div class="supervisor-info mb-3">';
                                    echo '<strong>Reports to:</strong><br>';
                                    echo '<span class="text-primary">' . htmlspecialchars($group['supervisor_name']) . '</span>';
                                    echo '</div>';

                                    echo '<div class="staff-count">';
                                    echo '<span class="badge bg-primary fs-5">Total: ' . $group['staff_count'] . ' Staff</span>';
                                    echo '</div>';

                                    // Show first location of staff members
                                    if (!empty($group['staff_list'])) {
                                        $location = $group['staff_list'][0]['location_name'] ?? 'Unknown';
                                        echo '<div class="location-info mt-2">';
                                        echo '<i class="fas fa-map-marker-alt text-info me-1"></i>' . htmlspecialchars($location);
                                        echo '</div>';
                                    }

                                    echo '</div>';
                                }
                            }

                            function renderLocationHierarchy($topLevelEmployees, $locationEmployees) {
                                echo '<div class="location-org-structure">';

                                // Render top management hierarchy (MD -> GM -> Managers)
                                if (!empty($topLevelEmployees)) {
                                    echo '<div class="top-level-section mb-5">';
                                    echo '<h4 class="text-center mb-4" style="color: #007bff; border-bottom: 2px solid #007bff; display: inline-block; padding-bottom: 10px;">Top Management Hierarchy</h4>';

                                    renderTopManagementHierarchy($topLevelEmployees);

                                    echo '</div>';
                                }

                                // Render location cards with employees
                                echo '<div class="locations-section">';
                                echo '<div class="row g-4">';

                                foreach ($locationEmployees as $locationName => $employees) {
                                    renderLocationCard($locationName, $employees, $topLevelEmployees);
                                }

                                echo '</div>';
                                echo '</div>';
                                echo '</div>';
                            }

                            function renderTopManagementHierarchy($topLevelEmployees) {
                                // Organize by level: MD (1), GM (2), Managers (3)
                                $managementLevels = [
                                    1 => [], // MD
                                    2 => [], // GM
                                    3 => []  // Managers
                                ];

                                foreach ($topLevelEmployees as $employee) {
                                    $level = $employee['level_order'] ?? 9;
                                    if (isset($managementLevels[$level])) {
                                        $managementLevels[$level][] = $employee;
                                    }
                                }

                                // Render hierarchical structure
                                echo '<div class="management-hierarchy">';

                                // Level 1: MD
                                if (!empty($managementLevels[1])) {
                                    echo '<div class="hierarchy-level level-1 text-center mb-4">';
                                    echo '<div class="level-title mb-3"><strong style="color: #8B0000;">Level 1 - Managing Director</strong></div>';
                                    echo '<div class="level-employees d-flex justify-content-center gap-3">';
                                    foreach ($managementLevels[1] as $employee) {
                                        renderHierarchyEmployeeBox($employee, 1);
                                    }
                                    echo '</div>';
                                    if (!empty($managementLevels[2])) {
                                        echo '<div class="hierarchy-line-down"></div>';
                                    }
                                    echo '</div>';
                                }

                                // Level 2: GM
                                if (!empty($managementLevels[2])) {
                                    echo '<div class="hierarchy-level level-2 text-center mb-4">';
                                    echo '<div class="level-title mb-3"><strong style="color: #FF4500;">Level 2 - General Manager</strong></div>';
                                    echo '<div class="level-employees d-flex justify-content-center gap-3">';
                                    foreach ($managementLevels[2] as $employee) {
                                        renderHierarchyEmployeeBox($employee, 2);
                                    }
                                    echo '</div>';
                                    if (!empty($managementLevels[3])) {
                                        echo '<div class="hierarchy-line-down"></div>';
                                    }
                                    echo '</div>';
                                }

                                // Level 3: Managers
                                if (!empty($managementLevels[3])) {
                                    echo '<div class="hierarchy-level level-3 text-center mb-4">';
                                    echo '<div class="level-title mb-3"><strong style="color: #FFD700;">Level 3 - Managers</strong></div>';
                                    echo '<div class="level-employees d-flex flex-wrap justify-content-center gap-3">';
                                    foreach ($managementLevels[3] as $employee) {
                                        renderHierarchyEmployeeBox($employee, 3);
                                    }
                                    echo '</div>';
                                    echo '</div>';
                                }

                                echo '</div>';
                            }

                            function renderHierarchyEmployeeBox($employee, $level) {
                                $levelColor = getLevelColor($level);

                                echo '<div class="hierarchy-employee-box" style="border: 2px solid ' . $levelColor . '; position: relative; min-width: 180px; max-width: 180px; overflow: hidden; border-radius: 8px;">';

                                // Employee Avatar
                                echo '<div class="employee-avatar mx-auto mb-2" style="background: ' . $levelColor . '; width: 45px; height: 45px; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">';
                                echo strtoupper(substr($employee['name'] ?? 'U', 0, 1));
                                echo '</div>';

                                // Employee Info
                                echo '<div class="employee-info text-center p-2" style="overflow: hidden; padding-bottom: 10px;">';
                                echo '<h6 class="employee-name mb-1" style="font-size: 0.8em; line-height: 1.1; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin: 0 3px;">' . htmlspecialchars($employee['name'] ?? 'Unknown') . '</h6>';
                                echo '<div class="employee-epf text-muted mb-1" style="font-size: 0.7em; margin: 2px 0;">EPF: ' . htmlspecialchars($employee['epf_number'] ?? 'N/A') . '</div>';
                                echo '<div class="employee-level badge" style="background: ' . $levelColor . '; font-size: 0.65em; padding: 2px 6px;">' . htmlspecialchars($employee['level_name'] ?? 'N/A') . '</div>';
                                echo '</div>';

                                echo '</div>';
                            }

                            function renderLocationCard($locationName, $employees, $allEmployees) {
                                echo '<div class="col-xl-4 col-lg-6 col-md-12">';
                                echo '<div class="location-card card shadow-lg border-0">';
                                echo '<div class="card-header text-white text-center" style="background: linear-gradient(45deg, #007bff, #0056b3);">';
                                echo '<h5 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>' . htmlspecialchars($locationName) . '</h5>';
                                echo '</div>';
                                echo '<div class="card-body p-3">';

                                // Render hierarchical structure within location for levels 4-9
                                renderLocationLevelHierarchy($employees, $allEmployees);

                                echo '</div>';
                                echo '</div>';
                                echo '</div>';
                            }

                            function renderLocationLevelHierarchy($employees, $allEmployees) {
                                // Group employees by level for clear organization
                                $levelGroups = [];
                                foreach ($employees as $employee) {
                                    $level = $employee['level_order'] ?? 9;
                                    if (!isset($levelGroups[$level])) {
                                        $levelGroups[$level] = [];
                                    }
                                    $levelGroups[$level][] = $employee;
                                }

                                $levelNames = [
                                    4 => 'Assistant Manager',
                                    5 => 'Senior Executive',
                                    6 => 'Executive',
                                    7 => 'Junior Executive',
                                    8 => 'Supervisor',
                                    9 => 'Staff'
                                ];

                                // Sort levels
                                ksort($levelGroups);

                                echo '<div class="location-hierarchy">';

                                // Render each level
                                foreach ($levelGroups as $level => $levelEmployees) {
                                    echo '<div class="location-level level-' . $level . ' mb-3" data-level="' . $level . '">';
                                    echo '<div class="level-title text-center mb-2">';
                                    echo '<small style="color: ' . getLevelColor($level) . '; font-weight: bold;">Level ' . $level . ' - ' . $levelNames[$level] . '</small>';
                                    echo '</div>';

                                    if ($level == 9) {
                                        // Staff level - group by supervisor
                                        renderLocationStaffGroupsWithLines($levelEmployees, $allEmployees);
                                    } else {
                                        // Individual employees for levels 4-8
                                        echo '<div class="level-employees d-flex flex-wrap justify-content-center gap-2">';
                                        foreach ($levelEmployees as $employee) {
                                            renderLocationEmployeeWithReportingInfo($employee, $allEmployees);
                                        }
                                        echo '</div>';
                                    }

                                    echo '</div>';
                                }

                                echo '</div>';

                                // Add JavaScript for drawing reporting lines
                                echo '<script>';
                                echo 'document.addEventListener("DOMContentLoaded", function() {';
                                echo '    drawReportingLines();';
                                echo '});';
                                echo '</script>';
                            }

                            function renderLocationEmployeeWithReportingInfo($employee, $allEmployees) {
                                $level = $employee['level_order'] ?? 9;
                                $levelColor = getLevelColor($level);
                                $epfNumber = $employee['epf_number'];
                                $reportsTo = $employee['reports_to'] ?? '';

                                // Find supervisor name
                                $supervisorName = 'No Supervisor';
                                if (!empty($reportsTo)) {
                                    foreach ($allEmployees as $supervisor) {
                                        if ($supervisor['epf_number'] == $reportsTo) {
                                            $supervisorName = $supervisor['name'];
                                            break;
                                        }
                                    }
                                }

                                echo '<div class="location-employee-box employee-with-lines" ';
                                echo 'data-epf="' . htmlspecialchars($epfNumber) . '" ';
                                echo 'data-reports-to="' . htmlspecialchars($reportsTo) . '" ';
                                echo 'data-supervisor-name="' . htmlspecialchars($supervisorName) . '" ';
                                echo 'style="border: 1px solid ' . $levelColor . '; position: relative;">';

                                // Employee Avatar (smaller)
                                echo '<div class="employee-avatar mx-auto mb-1" style="background: ' . $levelColor . '; width: 35px; height: 35px; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.9em;">';
                                echo strtoupper(substr($employee['name'] ?? 'U', 0, 1));
                                echo '</div>';

                                // Employee Info (compact)
                                echo '<div class="employee-info text-center">';
                                echo '<h6 class="employee-name mb-0" style="font-size: 0.75em;">' . htmlspecialchars($employee['name'] ?? 'Unknown') . '</h6>';
                                echo '<div class="employee-epf text-muted" style="font-size: 0.65em;">EPF: ' . htmlspecialchars($epfNumber) . '</div>';

                                // Show reporting info
                                if (!empty($reportsTo)) {
                                    echo '<div class="reports-to-info" style="font-size: 0.6em; color: #007bff; margin-top: 2px;">';
                                    echo 'Reports to: ' . htmlspecialchars($supervisorName);
                                    echo '</div>';
                                }

                                echo '<div class="employee-level badge" style="background: ' . $levelColor . '; font-size: 0.6em;">' . htmlspecialchars($employee['level_name'] ?? 'N/A') . '</div>';
                                echo '</div>';

                                echo '</div>';
                            }

                            function renderLocationStaffGroupsWithLines($staffEmployees, $allEmployees) {
                                $staffGroups = [];

                                // Group staff by supervisor
                                foreach ($staffEmployees as $employee) {
                                    if (!empty($employee['reports_to'])) {
                                        $supervisor = $employee['reports_to'];
                                        if (!isset($staffGroups[$supervisor])) {
                                            // Find supervisor name
                                            $supervisorName = 'Unknown Supervisor';
                                            foreach ($allEmployees as $sup) {
                                                if ($sup['epf_number'] == $supervisor) {
                                                    $supervisorName = $sup['name'];
                                                    break;
                                                }
                                            }

                                            $staffGroups[$supervisor] = [
                                                'supervisor_epf' => $supervisor,
                                                'supervisor_name' => $supervisorName,
                                                'staff_count' => 0,
                                                'staff_list' => []
                                            ];
                                        }
                                        $staffGroups[$supervisor]['staff_count']++;
                                        $staffGroups[$supervisor]['staff_list'][] = $employee;
                                    }
                                }

                                // Render each staff group
                                echo '<div class="staff-groups d-flex flex-wrap justify-content-center gap-2">';
                                foreach ($staffGroups as $group) {
                                    $levelColor = getLevelColor(9);

                                    echo '<div class="staff-group-compact staff-group-with-lines" ';
                                    echo 'data-supervisor-epf="' . htmlspecialchars($group['supervisor_epf']) . '" ';
                                    echo 'data-supervisor-name="' . htmlspecialchars($group['supervisor_name']) . '" ';
                                    echo 'style="border: 1px solid ' . $levelColor . '; border-radius: 8px; padding: 8px; background: white; min-width: 140px; text-align: center;">';

                                    echo '<div class="group-header" style="background: ' . $levelColor . '; color: white; padding: 4px; margin: -8px -8px 6px -8px; border-radius: 7px 7px 0 0; font-size: 0.7em;">';
                                    echo '<strong>Staff Group</strong>';
                                    echo '</div>';

                                    echo '<div class="group-content" style="font-size: 0.75em;">';
                                    echo '<div class="supervisor-info mb-1">';
                                    echo '<strong>Reports to:</strong><br>';
                                    echo '<span class="text-primary">' . htmlspecialchars($group['supervisor_name']) . '</span>';
                                    echo '</div>';

                                    echo '<div class="staff-count">';
                                    echo '<span class="badge bg-primary" style="font-size: 0.7em;">Total: ' . $group['staff_count'] . '</span>';
                                    echo '</div>';
                                    echo '</div>';

                                    echo '</div>';
                                }
                                echo '</div>';
                            }

                            function buildLocationReportingHierarchy($employees, $allEmployees) {
                                // Create lookup for all employees (including those outside location for supervisor names)
                                $employeeLookup = [];
                                foreach ($allEmployees as $emp) {
                                    $employeeLookup[$emp['epf_number']] = $emp;
                                }

                                // Add current location employees to lookup
                                foreach ($employees as $emp) {
                                    $employeeLookup[$emp['epf_number']] = $emp;
                                }

                                // Build parent-child relationships
                                $children = [];
                                $rootEmployees = [];

                                foreach ($employees as $employee) {
                                    $empId = $employee['epf_number'];
                                    $supervisorId = $employee['reports_to'];

                                    if (empty($supervisorId)) {
                                        // No supervisor - could be top level or orphaned
                                        $rootEmployees[] = $employee;
                                    } else {
                                        // Check if supervisor exists in location employees
                                        $supervisorInLocation = false;
                                        foreach ($employees as $potentialSupervisor) {
                                            if ($potentialSupervisor['epf_number'] == $supervisorId) {
                                                $supervisorInLocation = true;
                                                break;
                                            }
                                        }

                                        if ($supervisorInLocation) {
                                            // Supervisor is in same location - add to children
                                            if (!isset($children[$supervisorId])) {
                                                $children[$supervisorId] = [];
                                            }
                                            $children[$supervisorId][] = $employee;
                                        } else {
                                            // Supervisor is outside location - treat as root for this location
                                            $employee['external_supervisor'] = isset($employeeLookup[$supervisorId]) ? $employeeLookup[$supervisorId]['name'] : 'External Supervisor';
                                            $rootEmployees[] = $employee;
                                        }
                                    }
                                }

                                // Attach children to their parents
                                foreach ($rootEmployees as &$root) {
                                    $root['children'] = attachChildrenToParent($root['epf_number'], $children);
                                }

                                return $rootEmployees;
                            }

                            function attachChildrenToParent($parentId, $allChildren) {
                                $children = [];
                                if (isset($allChildren[$parentId])) {
                                    foreach ($allChildren[$parentId] as $child) {
                                        $child['children'] = attachChildrenToParent($child['epf_number'], $allChildren);
                                        $children[] = $child;
                                    }
                                }
                                return $children;
                            }

                            function renderLocationReportingTree($employees, $depth = 0) {
                                foreach ($employees as $employee) {
                                    $level = $employee['level_order'] ?? 9;
                                    $indentStyle = $depth > 0 ? 'margin-left: ' . ($depth * 20) . 'px;' : '';

                                    echo '<div class="reporting-node mb-2" style="' . $indentStyle . '">';

                                    // Show external supervisor info if exists
                                    if (isset($employee['external_supervisor'])) {
                                        echo '<div class="external-supervisor-note text-center mb-1">';
                                        echo '<small class="text-muted">Reports to: ' . htmlspecialchars($employee['external_supervisor']) . ' (External)</small>';
                                        echo '<div class="external-line"></div>';
                                        echo '</div>';
                                    }

                                    if ($level == 9) {
                                        // Staff level - group by this employee if they have staff reporting to them
                                        if (!empty($employee['children'])) {
                                            $staffChildren = array_filter($employee['children'], function($child) {
                                                return ($child['level_order'] ?? 9) == 9;
                                            });

                                            if (!empty($staffChildren)) {
                                                renderStaffGroupForSupervisor($employee, $staffChildren);
                                            } else {
                                                renderLocationEmployeeBox($employee, false);
                                            }
                                        } else {
                                            renderLocationEmployeeBox($employee, false);
                                        }
                                    } else {
                                        // Individual employee box
                                        renderLocationEmployeeBox($employee, false);
                                    }

                                    // Render reporting line to children if they exist
                                    if (!empty($employee['children'])) {
                                        echo '<div class="reporting-line-to-children"></div>';
                                        renderLocationReportingTree($employee['children'], $depth + 1);
                                    }

                                    echo '</div>';
                                }
                            }

                            function renderStaffGroupForSupervisor($supervisor, $staffList) {
                                $levelColor = getLevelColor(8); // Supervisor color

                                echo '<div class="supervisor-with-staff mb-3">';

                                // Supervisor box
                                renderLocationEmployeeBox($supervisor, false);

                                // Staff group below
                                echo '<div class="staff-group-under-supervisor mt-2" style="border: 1px solid ' . getLevelColor(9) . '; border-radius: 8px; padding: 10px; background: #f9f9f9;">';
                                echo '<div class="text-center mb-2">';
                                echo '<small class="text-muted">Staff reporting to ' . htmlspecialchars($supervisor['name']) . '</small>';
                                echo '</div>';

                                echo '<div class="staff-count text-center">';
                                echo '<span class="badge bg-primary">Total: ' . count($staffList) . ' Staff Members</span>';
                                echo '</div>';
                                echo '</div>';

                                echo '</div>';
                            }

                            function renderLocationStaffGroupsHierarchy($staffEmployees, $allEmployees) {
                                $staffGroups = [];

                                // Group staff by supervisor
                                foreach ($staffEmployees as $employee) {
                                    if (!empty($employee['reports_to'])) {
                                        $supervisor = $employee['reports_to'];
                                        if (!isset($staffGroups[$supervisor])) {
                                            $staffGroups[$supervisor] = [
                                                'supervisor_epf' => $supervisor,
                                                'supervisor_name' => $employee['supervisor_name'] ?? 'Unknown Supervisor',
                                                'staff_count' => 0,
                                                'staff_list' => []
                                            ];
                                        }
                                        $staffGroups[$supervisor]['staff_count']++;
                                        $staffGroups[$supervisor]['staff_list'][] = $employee;
                                    }
                                }

                                // Render each staff group
                                echo '<div class="staff-groups d-flex flex-wrap justify-content-center gap-2">';
                                foreach ($staffGroups as $group) {
                                    $levelColor = getLevelColor(9);

                                    echo '<div class="staff-group-compact" style="border: 1px solid ' . $levelColor . '; border-radius: 8px; padding: 8px; background: white; min-width: 140px; text-align: center;">';
                                    echo '<div class="group-header" style="background: ' . $levelColor . '; color: white; padding: 4px; margin: -8px -8px 6px -8px; border-radius: 7px 7px 0 0; font-size: 0.7em;">';
                                    echo '<strong>Staff Group</strong>';
                                    echo '</div>';

                                    echo '<div class="group-content" style="font-size: 0.75em;">';
                                    echo '<div class="supervisor-info mb-1">';
                                    echo '<strong>Reports to:</strong><br>';
                                    echo '<span class="text-primary">' . htmlspecialchars($group['supervisor_name']) . '</span>';
                                    echo '</div>';

                                    echo '<div class="staff-count">';
                                    echo '<span class="badge bg-primary" style="font-size: 0.7em;">Total: ' . $group['staff_count'] . '</span>';
                                    echo '</div>';
                                    echo '</div>';

                                    echo '</div>';
                                }
                                echo '</div>';
                            }

                            function renderLocationEmployeeBox($employee, $isTopLevel = false) {
                                $level = $employee['level_order'] ?? 9;
                                $levelColor = getLevelColor($level);
                                $boxClass = $isTopLevel ? 'top-level-box' : 'location-employee-box';

                                if ($isTopLevel) {
                                    // Top level styling (larger)
                                    echo '<div class="' . $boxClass . ' mb-3" style="border: 2px solid ' . $levelColor . '; position: relative;" data-epf="' . htmlspecialchars($employee['epf_number']) . '">';

                                    // Employee Avatar
                                    echo '<div class="employee-avatar mx-auto mb-2" style="background: ' . $levelColor . '; width: 45px; height: 45px; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">';
                                    echo strtoupper(substr($employee['name'] ?? 'U', 0, 1));
                                    echo '</div>';

                                    // Employee Info
                                    echo '<div class="employee-info text-center p-2">';
                                    echo '<h6 class="employee-name mb-1" style="font-size: 0.9em;">' . htmlspecialchars($employee['name'] ?? 'Unknown') . '</h6>';
                                    echo '<div class="employee-epf text-muted" style="font-size: 0.8em;">EPF: ' . htmlspecialchars($employee['epf_number'] ?? 'N/A') . '</div>';
                                    echo '<div class="employee-level badge" style="background: ' . $levelColor . '; font-size: 0.75em;">' . htmlspecialchars($employee['level_name'] ?? 'N/A') . '</div>';
                                    echo '</div>';
                                } else {
                                    // Location card styling (compact)
                                    echo '<div class="' . $boxClass . '" style="border: 1px solid ' . $levelColor . '; position: relative;" data-epf="' . htmlspecialchars($employee['epf_number']) . '">';

                                    // Employee Avatar (smaller)
                                    echo '<div class="employee-avatar mx-auto mb-1" style="background: ' . $levelColor . '; width: 35px; height: 35px; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.9em;">';
                                    echo strtoupper(substr($employee['name'] ?? 'U', 0, 1));
                                    echo '</div>';

                                    // Employee Info (compact)
                                    echo '<div class="employee-info text-center">';
                                    echo '<h6 class="employee-name mb-0" style="font-size: 0.75em;">' . htmlspecialchars($employee['name'] ?? 'Unknown') . '</h6>';
                                    echo '<div class="employee-epf text-muted" style="font-size: 0.65em;">EPF: ' . htmlspecialchars($employee['epf_number'] ?? 'N/A') . '</div>';
                                    echo '<div class="employee-level badge" style="background: ' . $levelColor . '; font-size: 0.6em;">' . htmlspecialchars($employee['level_name'] ?? 'N/A') . '</div>';
                                    echo '</div>';
                                }

                                echo '</div>';
                            }

                            function renderLocationStaffGroups($staffEmployees, $allEmployees) {
                                $staffGroups = [];

                                // Group staff by supervisor
                                foreach ($staffEmployees as $employee) {
                                    if (!empty($employee['reports_to'])) {
                                        $supervisor = $employee['reports_to'];
                                        if (!isset($staffGroups[$supervisor])) {
                                            $staffGroups[$supervisor] = [
                                                'supervisor_epf' => $supervisor,
                                                'supervisor_name' => $employee['supervisor_name'] ?? 'Unknown Supervisor',
                                                'staff_count' => 0,
                                                'staff_list' => []
                                            ];
                                        }
                                        $staffGroups[$supervisor]['staff_count']++;
                                        $staffGroups[$supervisor]['staff_list'][] = $employee;
                                    }
                                }

                                // Render each staff group
                                foreach ($staffGroups as $group) {
                                    $levelColor = getLevelColor(9);

                                    echo '<div class="staff-group-location mb-3" style="border: 2px solid ' . $levelColor . '; position: relative;" data-supervisor="' . htmlspecialchars($group['supervisor_epf']) . '">';
                                    echo '<div class="group-header text-center" style="background: ' . $levelColor . '; color: white; padding: 6px;">';
                                    echo '<strong>Staff Group</strong>';
                                    echo '</div>';

                                    echo '<div class="group-content text-center p-2">';
                                    echo '<div class="supervisor-info mb-1">';
                                    echo '<strong>Reports to:</strong><br>';
                                    echo '<span class="text-primary" style="font-size: 0.9em;">' . htmlspecialchars($group['supervisor_name']) . '</span>';
                                    echo '</div>';

                                    echo '<div class="staff-count">';
                                    echo '<span class="badge bg-primary">Total: ' . $group['staff_count'] . ' Staff</span>';
                                    echo '</div>';
                                    echo '</div>';

                                    echo '</div>';

                                    // Add reporting line
                                    renderReportingLinesForStaff($group, $allEmployees);
                                }
                            }

                            function renderReportingLines($employee, $allEmployees) {
                                if (!empty($employee['reports_to'])) {
                                    echo '<div class="reporting-line" data-from="' . htmlspecialchars($employee['epf_number']) . '" data-to="' . htmlspecialchars($employee['reports_to']) . '" style="position: absolute; top: -10px; left: 50%; width: 2px; height: 10px; background: #007bff; z-index: 5;"></div>';
                                }
                            }

                            function renderReportingLinesForStaff($group, $allEmployees) {
                                echo '<div class="reporting-line" data-from="staff_' . htmlspecialchars($group['supervisor_epf']) . '" data-to="' . htmlspecialchars($group['supervisor_epf']) . '" style="position: absolute; top: -10px; left: 50%; width: 2px; height: 10px; background: #007bff; z-index: 5;"></div>';
                            }

                            if (empty($hierarchy)) {
                                echo '<div class="text-center py-5">';
                                echo '<i class="fas fa-sitemap fa-3x text-muted mb-3"></i>';
                                echo '<p class="text-muted">No organizational structure found with current filters</p>';
                                echo '</div>';
                            } else {
                                renderLocationTreeView($hierarchy);
                            }
                            ?>
                            </div>
                        <?php elseif ($display_type === 'list'): ?>
                            <!-- 2.4.1.2 List View -->
                            <div class="list-view p-4" id="listView">
                            <?php
                            // Build hierarchy tree for expandable list
                            function buildExpandableHierarchy($employees) {
                                $tree = [];
                                $lookup = [];

                                // Create lookup array
                                foreach ($employees as $employee) {
                                    $lookup[$employee['epf_number']] = $employee;
                                    $lookup[$employee['epf_number']]['children'] = [];
                                }

                                // Build tree structure
                                foreach ($employees as $employee) {
                                    if (empty($employee['reports_to']) || !isset($lookup[$employee['reports_to']])) {
                                        // Top-level employee (no manager or manager not in accessible list)
                                        $tree[] = &$lookup[$employee['epf_number']];
                                    } else {
                                        // Employee reports to someone
                                        $lookup[$employee['reports_to']]['children'][] = &$lookup[$employee['epf_number']];
                                    }
                                }

                                // Sort tree by level order (MD first, then GM, etc.)
                                usort($tree, function($a, $b) {
                                    return ($a['level_order'] ?? 9) <=> ($b['level_order'] ?? 9);
                                });

                                return $tree;
                            }

                            function renderExpandableListView($employees, $level = 0) {
                                foreach ($employees as $employee) {
                                    $epf = htmlspecialchars($employee['epf_number']);
                                    $hasChildren = !empty($employee['children']);
                                    $indent_style = 'margin-left: ' . ($level * 20) . 'px;';

                                    echo '<div class="expandable-employee-item" style="' . $indent_style . '">';
                                    echo '<div class="employee-row" data-epf="' . $epf . '" style="';
                                    echo 'padding: 12px 15px 12px 0; border: 1px solid #e0e0e0; border-radius: 8px; margin: 6px 0; background: white; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: all 0.2s ease;';
                                    echo $hasChildren ? ' border-left: 4px solid #007bff;' : ' border-left: 4px solid #e9ecef;';
                                    echo '">';

                                    echo '<div style="display: flex; width: 100%; text-align: left;">';

                                    // Expand/Collapse button + Avatar
                                    echo '<div style="flex: 0 0 8.333333%; padding: 0 8px; text-align: left; display: flex; align-items: center;">';
                                    if ($hasChildren) {
                                        echo '<button class="expand-btn btn btn-sm btn-primary" data-epf="' . $epf . '" style="width: 24px; height: 24px; padding: 0; border-radius: 50%; box-shadow: 0 1px 3px rgba(0,123,255,0.3); margin-right: 6px;">';
                                        echo '<i class="fas fa-plus" style="font-size: 9px;"></i>';
                                        echo '</button>';
                                    } else {
                                        echo '<div style="width: 24px; height: 24px; border: 1px solid #e9ecef; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #f8f9fa; margin-right: 6px; color: #6c757d;">';
                                        echo '<i class="fas fa-user" style="font-size: 8px;"></i>';
                                        echo '</div>';
                                    }

                                    // Enhanced avatar with level-based colors
                                    $level_order = $employee['level_order'] ?? 9;
                                    $avatar_colors = [
                                        1 => 'background: linear-gradient(135deg, #8B0000, #DC143C);', // MD
                                        2 => 'background: linear-gradient(135deg, #FF4500, #FF6347);', // GM
                                        3 => 'background: linear-gradient(135deg, #FFD700, #FFA500);', // Manager
                                        4 => 'background: linear-gradient(135deg, #32CD32, #228B22);', // Asst Manager
                                        5 => 'background: linear-gradient(135deg, #00CED1, #008B8B);', // Sr Executive
                                        6 => 'background: linear-gradient(135deg, #1E90FF, #0000CD);', // Executive
                                        7 => 'background: linear-gradient(135deg, #9370DB, #8A2BE2);', // Jr Executive
                                        8 => 'background: linear-gradient(135deg, #FF1493, #DC143C);', // Supervisor
                                        9 => 'background: linear-gradient(135deg, #708090, #2F4F4F);'  // Staff
                                    ];
                                    $avatar_color = $avatar_colors[$level_order] ?? $avatar_colors[9];

                                    echo '<div class="employee-avatar" style="width: 32px; height: 32px; ' . $avatar_color . ' color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.15); border: 2px solid white; margin-right: 8px;">';
                                    echo strtoupper(substr($employee['name'], 0, 1));
                                    echo '</div>';
                                    echo '</div>';

                                    // Employee info - Enhanced layout
                                    echo '<div style="flex: 0 0 25%; padding: 0 8px; text-align: left;">';
                                    echo '<div style="text-align: left !important; padding: 0 !important; margin: 0 !important; width: 100% !important;">';
                                    echo '<h6 style="font-size: 14px; color: #2c3e50; text-align: left !important; margin: 0 !important; padding: 0 !important; display: block !important; width: 100% !important; font-weight: 600;">' . htmlspecialchars($employee['name']);
                                    if ($hasChildren) {
                                        echo ' <span class="badge bg-primary ms-2 rounded-pill" style="font-size: 10px; vertical-align: middle;">+' . count($employee['children']) . '</span>';
                                    }
                                    echo '</h6>';
                                    echo '<small style="font-size: 11px; text-align: left !important; margin: 0 !important; padding: 0 !important; display: block !important; color: #6c757d;">EPF: ' . $epf . '</small>';
                                    echo '</div>';
                                    echo '</div>';

                                    // Designation - Better styling
                                    echo '<div style="flex: 0 0 16.666667%; padding: 0 8px; text-align: left;">';
                                    echo '<div style="text-align: left !important; margin: 0 !important; padding: 6px 0; width: 100% !important;">';
                                    echo '<div style="font-size: 13px; color: #495057; line-height: 1.3; text-align: left !important; margin: 0 !important; padding: 0 !important; display: block !important; font-weight: 500;">' . htmlspecialchars($employee['designation'] ?? 'N/A') . '</div>';
                                    echo '</div>';
                                    echo '</div>';

                                    // Level - Enhanced badge
                                    echo '<div style="flex: 0 0 16.666667%; padding: 0 8px; text-align: left;">';
                                    $level_colors = [
                                        'MD' => 'bg-danger',
                                        'GM' => 'bg-warning text-dark',
                                        'Manager' => 'bg-success',
                                        'Assistant Manager' => 'bg-info',
                                        'Senior Executive' => 'bg-primary',
                                        'Executive' => 'bg-secondary',
                                        'Junior Executive' => 'bg-dark',
                                        'Supervisor' => 'bg-info',
                                        'Staff' => 'bg-light text-dark'
                                    ];
                                    $level_name = $employee['employment_level_name'] ?? 'N/A';
                                    $level_badge_class = $level_colors[$level_name] ?? 'bg-secondary';
                                    echo '<span class="badge ' . $level_badge_class . ' rounded-pill" style="font-size: 11px; padding: 6px 12px; font-weight: 600;">' . htmlspecialchars($level_name) . '</span>';
                                    echo '</div>';

                                    // Department - Left aligned
                                    echo '<div style="flex: 0 0 16.666667%; padding: 0 8px; text-align: left;">';
                                    echo '<div style="font-size: 13px; color: #6c757d; font-weight: 500; line-height: 1.4; text-align: left !important;">';
                                    echo '<i class="fas fa-building me-2 text-primary" style="font-size: 12px;"></i>';
                                    echo htmlspecialchars($employee['department'] ?? 'N/A');
                                    echo '</div>';
                                    echo '</div>';

                                    // Location - Left aligned with better icon
                                    echo '<div style="flex: 0 0 16.666667%; padding: 0 8px; text-align: left;">';
                                    echo '<div style="font-size: 13px; color: #6c757d; font-weight: 500; line-height: 1.4; text-align: left !important;">';
                                    echo '<i class="fas fa-map-marker-alt me-2 text-danger" style="font-size: 12px;"></i>';
                                    echo htmlspecialchars($employee['location_name'] ?? 'N/A');
                                    echo '</div>';
                                    echo '</div>';

                                    echo '</div>'; // End row
                                    echo '</div>'; // End employee-row

                                    // Children container (initially hidden)
                                    if ($hasChildren) {
                                        echo '<div class="children-container" id="children-' . $epf . '" style="display: none;">';
                                        renderExpandableListView($employee['children'], $level + 1);
                                        echo '</div>';
                                    }

                                    echo '</div>'; // End expandable-employee-item
                                }
                            }

                            if (empty($hierarchy)) {
                                echo '<div class="text-center py-5">';
                                echo '<i class="fas fa-list fa-3x text-muted mb-3"></i>';
                                echo '<p class="text-muted">No organizational structure found with current filters</p>';
                                echo '</div>';
                            } else {
                                // Build expandable hierarchy
                                $expandableHierarchy = buildExpandableHierarchy($employees_with_details);

                                // Header
                                echo '<div class="mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 15px; box-shadow: 0 4px 15px rgba(102,126,234,0.4);">';
                                echo '<div class="row align-items-start" style="text-align: left !important;">';
                                echo '<div class="col-md-1" style="text-align: left !important;"><strong style="font-size: 14px;"><i class="fas fa-expand-alt me-2"></i>Expand</strong></div>';
                                echo '<div class="col-md-3" style="text-align: left !important;"><strong style="font-size: 14px;"><i class="fas fa-user me-2"></i>Employee Details</strong></div>';
                                echo '<div class="col-md-2" style="text-align: left !important;"><strong style="font-size: 14px;"><i class="fas fa-briefcase me-2"></i>Position</strong></div>';
                                echo '<div class="col-md-2" style="text-align: left !important;"><strong style="font-size: 14px;"><i class="fas fa-layer-group me-2"></i>Level</strong></div>';
                                echo '<div class="col-md-2" style="text-align: left !important;"><strong style="font-size: 14px;"><i class="fas fa-building me-2"></i>Department</strong></div>';
                                echo '<div class="col-md-2" style="text-align: left !important;"><strong style="font-size: 14px;"><i class="fas fa-map-marker-alt me-2"></i>Location</strong></div>';
                                echo '</div>';
                                echo '</div>';

                                // Instructions
                                echo '<div class="alert alert-info mb-3" style="border-left: 4px solid #007bff;">';
                                echo '<i class="fas fa-info-circle me-2"></i>';
                                echo '<strong>Hierarchical View:</strong> Click the <i class="fas fa-plus text-primary mx-1"></i> button to expand and see who reports to each person. ';
                                echo 'Blue border indicates employees with subordinates.';
                                echo '</div>';

                                // Render expandable list
                                if (empty($expandableHierarchy)) {
                                    echo '<div class="text-center py-4">';
                                    echo '<i class="fas fa-users-slash fa-2x text-muted mb-2"></i>';
                                    echo '<p class="text-muted">No top-level managers found in the filtered results</p>';
                                    echo '</div>';
                                } else {
                                    echo '<div id="expandableHierarchy">';
                                    renderExpandableListView($expandableHierarchy);
                                    echo '</div>';
                                }
                            }
                            ?>
                            </div>
                        <?php else: ?>
                            <!-- Invalid Display Type -->
                            <div class="alert alert-warning text-center p-5">
                                <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                                <h4>Invalid Display Type</h4>
                                <p>The requested display type '<?php echo htmlspecialchars($display_type); ?>' is not supported.</p>
                                <p>Please use 'tree', 'location_tree', or 'list' as the display parameter.</p>
                                <a href="?display=tree" class="btn btn-primary">Switch to Tree View</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script>
        let isFullscreen = false;

        // Enhanced Fullscreen Toggle
        function toggleFullscreen() {
            const container = document.getElementById('orgChartContainer');
            const btn = document.getElementById('fullscreenBtn');
            const icon = document.getElementById('fullscreenIcon');

            if (!isFullscreen) {
                // Enter fullscreen
                container.classList.add('fullscreen-mode');
                btn.innerHTML = '<i class="fas fa-compress me-1"></i>Exit Full Screen';
                icon.className = 'fas fa-compress';
                isFullscreen = true;

                // Disable body scroll
                document.body.style.overflow = 'hidden';

                // Focus on the scrollable container
                const scrollableContainer = document.getElementById('scrollableContainer');
                if (scrollableContainer) {
                    scrollableContainer.focus();
                }
            } else {
                // Exit fullscreen
                container.classList.remove('fullscreen-mode');
                btn.innerHTML = '<i class="fas fa-expand me-1"></i>Full Screen';
                icon.className = 'fas fa-expand';
                isFullscreen = false;

                // Enable body scroll
                document.body.style.overflow = 'auto';
            }
        }

        // Keyboard shortcuts for fullscreen
        document.addEventListener('keydown', function(e) {
            // F11 or F key for fullscreen toggle
            if (e.key === 'F11' || (e.key === 'f' && e.ctrlKey)) {
                e.preventDefault();
                toggleFullscreen();
            }

            // ESC key to exit fullscreen
            if (e.key === 'Escape' && isFullscreen) {
                toggleFullscreen();
            }
        });

        // Mouse Drag Navigation (No Scroll Bars)
        let isDragging = false;
        let startX, startY;
        let translateX = 0, translateY = 0;
        let initialTranslateX = 0, initialTranslateY = 0;

        const scrollableContainer = document.getElementById('scrollableContainer');
        const orgChartContent = scrollableContainer?.querySelector('.org-chart-vertical, .location-tree-view');
        const listView = scrollableContainer?.querySelector('.list-view');

        // Disable drag navigation for list view
        if (scrollableContainer && orgChartContent && !listView) {
            scrollableContainer.addEventListener('mousedown', function(e) {
                isDragging = true;
                startX = e.clientX;
                startY = e.clientY;
                initialTranslateX = translateX;
                initialTranslateY = translateY;
                this.style.cursor = 'grabbing';
                e.preventDefault();
            });

            document.addEventListener('mousemove', function(e) {
                if (!isDragging) return;
                e.preventDefault();

                const deltaX = e.clientX - startX;
                const deltaY = e.clientY - startY;

                translateX = initialTranslateX + deltaX;
                translateY = initialTranslateY + deltaY;

                // Apply transform to move content
                orgChartContent.style.transform = `translate(${translateX}px, ${translateY}px) scale(${currentZoom})`;
            });

            document.addEventListener('mouseup', function() {
                if (isDragging) {
                    isDragging = false;
                    scrollableContainer.style.cursor = 'grab';
                }
            });

            // Keyboard navigation for fullscreen
            document.addEventListener('keydown', function(e) {
                if (isFullscreen) {
                    const moveAmount = 50;
                    let moved = false;

                    switch(e.key) {
                        case 'ArrowUp':
                            translateY += moveAmount;
                            moved = true;
                            break;
                        case 'ArrowDown':
                            translateY -= moveAmount;
                            moved = true;
                            break;
                        case 'ArrowLeft':
                            translateX += moveAmount;
                            moved = true;
                            break;
                        case 'ArrowRight':
                            translateX -= moveAmount;
                            moved = true;
                            break;
                        case 'Home':
                            translateX = 0;
                            translateY = 0;
                            moved = true;
                            break;
                    }

                    if (moved) {
                        e.preventDefault();
                        orgChartContent.style.transform = `translate(${translateX}px, ${translateY}px) scale(${currentZoom})`;
                    }
                }
            });
        }

        // 2.4.10.2 Export as PNG
        function exportToPNG() {
            const element = document.getElementById('orgChartContainer');
            html2canvas(element, {
                backgroundColor: '#ffffff',
                scale: 2,
                useCORS: true
            }).then(canvas => {
                const link = document.createElement('a');
                link.download = 'organizational_chart_' + new Date().toISOString().slice(0, 10) + '.png';
                link.href = canvas.toDataURL();
                link.click();
            });
        }

        // 2.4.10.1 Export as PDF
        function exportToPDF() {
            const element = document.getElementById('orgChartContainer');
            html2canvas(element, {
                backgroundColor: '#ffffff',
                scale: 1,
                useCORS: true
            }).then(canvas => {
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF('l', 'mm', 'a4');

                const imgData = canvas.toDataURL('image/png');
                const imgWidth = 290;
                const pageHeight = 210;
                const imgHeight = (canvas.height * imgWidth) / canvas.width;
                let heightLeft = imgHeight;

                let position = 0;

                pdf.addImage(imgData, 'PNG', 10, position, imgWidth, imgHeight);
                heightLeft -= pageHeight;

                while (heightLeft >= 0) {
                    position = heightLeft - imgHeight;
                    pdf.addPage();
                    pdf.addImage(imgData, 'PNG', 10, position, imgWidth, imgHeight);
                    heightLeft -= pageHeight;
                }

                pdf.save('organizational_chart_' + new Date().toISOString().slice(0, 10) + '.pdf');
            });
        }

        // Sidebar toggle
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

        // Initialize cursor style for scrollable container
        if (scrollableContainer) {
            if (listView) {
                scrollableContainer.style.cursor = 'default';
                scrollableContainer.style.userSelect = 'text';
            } else {
                scrollableContainer.style.cursor = 'grab';
            }
        }

        // Function to draw organizational chart lines with 90-degree turns
        function drawSimpleReportingLines() {
            // Skip line drawing for list view
            if (document.querySelector('.list-view')) {
                console.log('List view detected - skipping line drawing');
                return;
            }

            console.log('=== STARTING LINE DRAWING DEBUG ===');

            try {
                // Step 1: Find container
                const container = document.getElementById('scrollableContainer');
                console.log('Container found:', !!container);
                if (!container) {
                    console.error('ERROR: scrollableContainer not found');
                    return;
                }

                // Step 2: Find content area
                const content = container.querySelector('.org-chart.location-tree-view');
                console.log('Content area found:', !!content);
                if (!content) {
                    console.error('ERROR: location-tree-view content not found');
                    console.log('Available elements in container:', container.innerHTML.substring(0, 500));
                    return;
                }

                // Step 3: Clean existing SVGs
                const existingSvgs = content.querySelectorAll('.simple-reporting-svg');
                existingSvgs.forEach(svg => svg.remove());
                console.log('Cleaned existing SVGs:', existingSvgs.length);

                // Step 4: Find employee elements
                const employees = content.querySelectorAll('[data-epf][data-reports-to]');
                const staffGroups = content.querySelectorAll('[data-supervisor-epf]');

                console.log('Found employees with reporting data:', employees.length);
                console.log('Found staff groups:', staffGroups.length);

                if (employees.length === 0 && staffGroups.length === 0) {
                    console.error('ERROR: No elements found with reporting data');
                    console.log('Content HTML sample:', content.innerHTML.substring(0, 1000));
                    return;
                }

                // Step 5: Create SVG overlay that properly inherits transforms
                const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                svg.classList.add('simple-reporting-svg');
                svg.style.position = 'absolute';
                svg.style.top = '0';
                svg.style.left = '0';
                svg.style.width = '100%';
                svg.style.height = '100%';
                svg.style.pointerEvents = 'none';
                svg.style.zIndex = '1';
                svg.style.overflow = 'visible';
                // Ensure SVG scales with content by being a direct child
                content.style.position = 'relative';
                content.appendChild(svg);
                console.log('SVG overlay created for zoom compatibility');

                // Step 6: Log all employee data
                let connectionCount = 0;
                employees.forEach(emp => {
                    const epf = emp.getAttribute('data-epf');
                    const reportsTo = emp.getAttribute('data-reports-to');
                    console.log(`Employee ${epf} reports to: "${reportsTo}"`);

                    if (reportsTo && reportsTo.trim() !== '') {
                        const supervisorBox = content.querySelector(`[data-epf="${reportsTo}"]`);
                        if (supervisorBox) {
                            drawRobustLine(svg, emp, supervisorBox, content);
                            connectionCount++;
                            console.log(`✓ Connected ${epf} → ${reportsTo}`);
                        } else {
                            console.warn(`✗ Supervisor ${reportsTo} not found for ${epf}`);
                        }
                    }
                });

                // Step 7: Handle staff groups
                staffGroups.forEach(staffGroup => {
                    const supervisorEpf = staffGroup.getAttribute('data-supervisor-epf');
                    if (supervisorEpf && supervisorEpf.trim() !== '') {
                        const supervisorBox = content.querySelector(`[data-epf="${supervisorEpf}"]`);
                        if (supervisorBox) {
                            drawRobustLine(svg, staffGroup, supervisorBox, content);
                            connectionCount++;
                            console.log(`✓ Connected staff group → ${supervisorEpf}`);
                        }
                    }
                });

                console.log(`=== COMPLETED: ${connectionCount} connections drawn ===`);

            } catch (error) {
                console.error('CRITICAL ERROR in drawSimpleReportingLines:', error);
                console.error('Error stack:', error.stack);
            }
        }

        function drawRobustLine(svg, subordinateElement, supervisorElement, content) {
            try {
                // Use offsetLeft/offsetTop which gives us coordinates in the pre-transform space
                const startX = Math.round(subordinateElement.offsetLeft + subordinateElement.offsetWidth / 2);
                const startY = Math.round(subordinateElement.offsetTop);
                const endX = Math.round(supervisorElement.offsetLeft + supervisorElement.offsetWidth / 2);
                const endY = Math.round(supervisorElement.offsetTop + supervisorElement.offsetHeight);

                console.log(`Drawing smart-routed line: (${startX}, ${startY}) → (${endX}, ${endY})`);

                // Validate coordinates
                if (isNaN(startX) || isNaN(startY) || isNaN(endX) || isNaN(endY)) {
                    console.error('Invalid coordinates calculated');
                    return;
                }

                // Get all employee boxes AND level headers for collision detection
                const allEmployeeBoxes = content.querySelectorAll('.simple-employee-box, .simple-staff-group');
                const allLevelHeaders = content.querySelectorAll('.level-header');

                // Create obstacles from employee boxes
                const employeeObstacles = Array.from(allEmployeeBoxes).map(box => ({
                    left: box.offsetLeft - 10,
                    right: box.offsetLeft + box.offsetWidth + 10,
                    top: box.offsetTop - 10,
                    bottom: box.offsetTop + box.offsetHeight + 10,
                    element: box,
                    type: 'employee'
                })).filter(obstacle =>
                    obstacle.element !== subordinateElement &&
                    obstacle.element !== supervisorElement
                );

                // Create obstacles from level headers
                const headerObstacles = Array.from(allLevelHeaders).map(header => ({
                    left: header.offsetLeft - 20,
                    right: header.offsetLeft + header.offsetWidth + 20,
                    top: header.offsetTop - 15,
                    bottom: header.offsetTop + header.offsetHeight + 15,
                    element: header,
                    type: 'header'
                }));

                // Combine all obstacles
                const obstacles = [...employeeObstacles, ...headerObstacles];

                console.log(`Total obstacles: ${obstacles.length} (${employeeObstacles.length} employees + ${headerObstacles.length} headers)`);
                headerObstacles.forEach((header, i) => {
                    console.log(`Header ${i}: top=${header.top}, bottom=${header.bottom}, left=${header.left}, right=${header.right}`);
                });

                // Calculate smart routing path that avoids all obstacles
                const pathData = calculateSmartRoute(startX, startY, endX, endY, obstacles);

                // Create line group
                const lineGroup = document.createElementNS('http://www.w3.org/2000/svg', 'g');
                lineGroup.classList.add('org-line');

                // Create main path
                const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                path.setAttribute('d', pathData);
                path.setAttribute('stroke', '#007bff');
                path.setAttribute('stroke-width', '2');
                path.setAttribute('fill', 'none');
                path.setAttribute('stroke-linejoin', 'round');
                path.setAttribute('stroke-linecap', 'round');

                // Create arrow pointing to supervisor
                const arrow = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
                arrow.setAttribute('points', `${endX},${endY} ${endX-4},${endY+8} ${endX+4},${endY+8}`);
                arrow.setAttribute('fill', '#007bff');

                // Create start dot
                const startDot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                startDot.setAttribute('cx', startX);
                startDot.setAttribute('cy', startY);
                startDot.setAttribute('r', '2');
                startDot.setAttribute('fill', '#007bff');

                // Add elements to group
                lineGroup.appendChild(path);
                lineGroup.appendChild(arrow);
                lineGroup.appendChild(startDot);

                // Add group to SVG
                svg.appendChild(lineGroup);

                console.log('Smart-routed line successfully drawn');

            } catch (error) {
                console.error('Error in drawRobustLine:', error);
                console.error('Error stack:', error.stack);
            }
        }

        function calculateSmartRoute(startX, startY, endX, endY, obstacles) {
            console.log(`Calculating smart route from (${startX}, ${startY}) to (${endX}, ${endY})`);

            // ALWAYS use structured routing (L, |, T shapes) - NEVER diagonal lines
            // Even when path is clear, use L-shaped connections for consistency

            // Try different clearance levels to avoid obstacles
            const margins = [25, 35, 50, 70];

            for (let margin of margins) {
                console.log(`Trying L-shaped route with margin ${margin}`);

                // Standard L-shaped route: creates | (vertical) + — (horizontal) + | (vertical)
                // This ensures consistent 90-degree turns for all connections
                const route = generateConsistentLRoute(startX, startY, endX, endY, margin);

                if (route && !routeIntersectsObstacles(route, obstacles)) {
                    console.log(`L-shaped route with margin ${margin} succeeded - using structured line (no diagonal)`);
                    return route;
                }
            }

            // Ultimate fallback: Large margin L-shaped route
            console.log('Standard margins failed, using large margin L-route (structured line only)');
            const safeMargin = 100;
            return generateConsistentLRoute(startX, startY, endX, endY, safeMargin);
        }

        function generateConsistentLRoute(startX, startY, endX, endY, margin) {
            // Creates structured lines: | (vertical) + — (horizontal) + | (vertical)
            // This creates uniform L/T-shaped connections and keeps lines clear of level headers

            // Calculate the horizontal corridor position - ensure it's well above both boxes AND level headers
            // Increased clearance to prevent lines from crossing through level header text
            const levelHeaderClearance = -180; // Negative clearance for testing
            const corridorY = Math.min(startY, endY) - margin - levelHeaderClearance;

            // Create the structured path: | up -> — across -> | down (L or T shape)
            return `M ${startX} ${startY} L ${startX} ${corridorY} L ${endX} ${corridorY} L ${endX} ${endY}`;
        }

        function generateVerticalFirstRoute(startX, startY, endX, endY, margin) {
            // Legacy function - now redirects to consistent L-route
            return generateConsistentLRoute(startX, startY, endX, endY, margin);
        }

        function generateHorizontalFirstRoute(startX, startY, endX, endY, margin) {
            // Legacy function - now redirects to consistent L-route
            return generateConsistentLRoute(startX, startY, endX, endY, margin);
        }

        function generateHighRoute(startX, startY, endX, endY, obstacles, margin) {
            // Legacy function - now redirects to consistent L-route
            return generateConsistentLRoute(startX, startY, endX, endY, margin);
        }

        function generateLowRoute(startX, startY, endX, endY, obstacles, margin) {
            // Legacy function - now redirects to consistent L-route
            return generateConsistentLRoute(startX, startY, endX, endY, margin);
        }

        function generateSideRoute(startX, startY, endX, endY, obstacles, margin, side) {
            // Legacy function - now redirects to consistent L-route
            return generateConsistentLRoute(startX, startY, endX, endY, margin);
        }

        function generateClearRoute(startX, startY, endX, endY, obstacles, margin) {
            // Find a clear horizontal corridor
            let corridorY = startY - margin;

            // Check if any obstacles block this corridor
            let attempts = 0;
            while (attempts < 5) {
                const corridorClear = !obstacles.some(obstacle =>
                    corridorY > obstacle.top && corridorY < obstacle.bottom &&
                    ((startX >= obstacle.left && startX <= obstacle.right) ||
                     (endX >= obstacle.left && endX <= obstacle.right) ||
                     (startX < obstacle.left && endX > obstacle.right))
                );

                if (corridorClear) {
                    return `M ${startX} ${startY} L ${startX} ${corridorY} L ${endX} ${corridorY} L ${endX} ${endY}`;
                }

                corridorY -= margin;
                attempts++;
            }

            return null; // No clear route found
        }

        function pathIntersectsObstacles(x1, y1, x2, y2, obstacles) {
            console.log(`Testing path (${x1}, ${y1}) → (${x2}, ${y2}) against ${obstacles.length} obstacles`);

            for (let i = 0; i < obstacles.length; i++) {
                const obstacle = obstacles[i];
                console.log(`  Obstacle ${i}: left=${obstacle.left}, top=${obstacle.top}, right=${obstacle.right}, bottom=${obstacle.bottom}`);

                const intersects = lineIntersectsRectangle(x1, y1, x2, y2, obstacle.left, obstacle.top, obstacle.right, obstacle.bottom);
                console.log(`  Intersection result: ${intersects}`);

                if (intersects) {
                    console.log(`Path intersects obstacle ${i}!`);
                    return true;
                }
            }

            console.log('Path is clear of all obstacles');
            return false;
        }

        function routeIntersectsObstacles(pathData, obstacles) {
            // Parse path and check each segment
            const segments = parsePathSegments(pathData);
            return segments.some(segment =>
                obstacles.some(obstacle =>
                    lineIntersectsRectangle(segment.x1, segment.y1, segment.x2, segment.y2,
                                          obstacle.left, obstacle.top, obstacle.right, obstacle.bottom)
                )
            );
        }

        function parsePathSegments(pathData) {
            const segments = [];
            const commands = pathData.match(/[ML]\s*[\d\.\-\s,]+/g) || [];
            let currentX = 0, currentY = 0;

            commands.forEach(cmd => {
                const type = cmd[0];
                const coords = cmd.slice(1).trim().split(/[\s,]+/).map(Number);

                if (type === 'M') {
                    currentX = coords[0];
                    currentY = coords[1];
                } else if (type === 'L') {
                    const newX = coords[0];
                    const newY = coords[1];
                    segments.push({ x1: currentX, y1: currentY, x2: newX, y2: newY });
                    currentX = newX;
                    currentY = newY;
                }
            });

            return segments;
        }

        function lineIntersectsRectangle(x1, y1, x2, y2, rectLeft, rectTop, rectRight, rectBottom) {
            // Simpler and more reliable collision detection
            // Check if line passes through the rectangle area

            // First check if either endpoint is inside the rectangle
            if (pointInRectangle(x1, y1, rectLeft, rectTop, rectRight, rectBottom) ||
                pointInRectangle(x2, y2, rectLeft, rectTop, rectRight, rectBottom)) {
                return true;
            }

            // Check if line crosses any edge of the rectangle
            return (lineIntersectsLineSegment(x1, y1, x2, y2, rectLeft, rectTop, rectRight, rectTop) ||      // Top edge
                    lineIntersectsLineSegment(x1, y1, x2, y2, rectRight, rectTop, rectRight, rectBottom) ||   // Right edge
                    lineIntersectsLineSegment(x1, y1, x2, y2, rectRight, rectBottom, rectLeft, rectBottom) || // Bottom edge
                    lineIntersectsLineSegment(x1, y1, x2, y2, rectLeft, rectBottom, rectLeft, rectTop));      // Left edge
        }

        function lineIntersectsLineSegment(x1, y1, x2, y2, x3, y3, x4, y4) {
            const denom = (x1 - x2) * (y3 - y4) - (y1 - y2) * (x3 - x4);
            if (denom === 0) return false;

            const t = ((x1 - x3) * (y3 - y4) - (y1 - y3) * (x3 - x4)) / denom;
            const u = -((x1 - x2) * (y1 - y3) - (y1 - y2) * (x1 - x3)) / denom;

            return t >= 0 && t <= 1 && u >= 0 && u <= 1;
        }

        function pointInRectangle(x, y, rectLeft, rectTop, rectRight, rectBottom) {
            return x >= rectLeft && x <= rectRight && y >= rectTop && y <= rectBottom;
        }

        function drawOrgChartLines(supervisorEpf, subordinates) {
            // Find the supervisor element
            const supervisorElement = document.querySelector(`[data-epf="${supervisorEpf}"]`);

            if (!supervisorElement || subordinates.length === 0) {
                console.log('Supervisor not found or no subordinates for EPF:', supervisorEpf);
                return;
            }

            // Verify each subordinate actually reports to this supervisor
            const validSubordinates = subordinates.filter(sub => {
                const reportsTo = sub.getAttribute('data-reports-to');
                return reportsTo === supervisorEpf;
            });

            if (validSubordinates.length === 0) {
                console.log('No valid reporting relationships found for supervisor:', supervisorEpf);
                return;
            }

            // Get container
            const container = document.getElementById('scrollableContainer');
            if (!container) return;

            // Get the content area that moves with the transform
            const content = container.querySelector('.org-chart-vertical, .location-tree-view');
            if (!content) return;

            // Create or get SVG INSIDE the moving content area
            let svg = content.querySelector('.simple-reporting-svg');
            if (!svg) {
                svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                svg.classList.add('simple-reporting-svg');
                svg.style.position = 'absolute';
                svg.style.top = '0';
                svg.style.left = '0';
                svg.style.width = '100%';
                svg.style.height = '100%';
                svg.style.pointerEvents = 'none';
                svg.style.zIndex = '1';
                svg.style.overflow = 'visible';
                content.style.position = 'relative';
                content.appendChild(svg);
            }

            // Calculate positions relative to the content area (not container)
            const contentRect = content.getBoundingClientRect();
            const supervisorRect = supervisorElement.getBoundingClientRect();

            // Supervisor connection point (bottom edge of box)
            const supervisorX = Math.round(supervisorRect.left - contentRect.left + supervisorRect.width / 2) + 0.5;
            const supervisorY = supervisorRect.top - contentRect.top + supervisorRect.height;

            // Calculate positions for all VALID subordinates with proper spacing
            const subordinatePositions = validSubordinates.map(subordinate => {
                const rect = subordinate.getBoundingClientRect();
                const epf = subordinate.getAttribute('data-epf');
                return {
                    element: subordinate,
                    epf: epf,
                    boxTop: rect.top - contentRect.top, // Top edge of subordinate box
                    boxBottom: rect.top - contentRect.top + rect.height,
                    boxLeft: rect.left - contentRect.left,
                    boxRight: rect.left - contentRect.left + rect.width,
                    boxCenterX: Math.round(rect.left - contentRect.left + rect.width / 2) + 0.5,
                    boxCenterY: rect.top - contentRect.top + rect.height / 2
                };
            });

            // Draw individual lines for each subordinate
            subordinatePositions.forEach(sub => {
                drawIndividualReportingLine(svg, sub, supervisorX, supervisorY, supervisorEpf);
            });
        }

        // New function to draw individual reporting lines with proper routing
        function drawIndividualReportingLine(svg, subordinate, supervisorX, supervisorY, supervisorEpf) {
            const spacing = 100; // Spacing to clear employee boxes

            // Start from top center of subordinate box
            const startX = subordinate.boxCenterX;
            const startY = subordinate.boxTop;

            // End at bottom center of supervisor box
            const endX = supervisorX;
            const endY = supervisorY;

            // Create unique line group for this relationship
            const lineGroup = document.createElementNS('http://www.w3.org/2000/svg', 'g');
            lineGroup.setAttribute('data-subordinate', subordinate.epf);
            lineGroup.setAttribute('data-supervisor', supervisorEpf);
            lineGroup.classList.add('reporting-relationship');

            // Determine routing path to avoid collisions
            let pathData;

            if (Math.abs(startX - endX) < 50) {
                // Direct vertical line if close horizontally
                const midY = startY - spacing;
                pathData = `M ${startX} ${startY} L ${startX} ${midY} L ${endX} ${midY} L ${endX} ${endY}`;
            } else {
                // L-shaped routing for distant connections
                const midY = Math.min(startY, endY) - spacing;
                pathData = `M ${startX} ${startY} L ${startX} ${midY} L ${endX} ${midY} L ${endX} ${endY}`;
            }

            // Create path element
            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', pathData);
            path.setAttribute('stroke', '#007bff');
            path.setAttribute('stroke-width', '3');
            path.setAttribute('fill', 'none');
            path.setAttribute('stroke-linejoin', 'round');
            path.setAttribute('stroke-linecap', 'round');
            path.classList.add('simple-reporting-line');

            // Add arrow pointing to supervisor (pointing upward)
            const arrow = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
            arrow.setAttribute('points', `${endX},${endY} ${endX-4},${endY+8} ${endX+4},${endY+8}`);
            arrow.setAttribute('fill', '#007bff');
            arrow.classList.add('simple-reporting-line');

            // Add connection dot at turn
            const dot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            dot.setAttribute('cx', endX);
            dot.setAttribute('cy', Math.min(startY, endY) - spacing);
            dot.setAttribute('r', '2');
            dot.setAttribute('fill', '#007bff');
            dot.classList.add('simple-reporting-line');

            // Add all elements to group
            lineGroup.appendChild(path);
            lineGroup.appendChild(arrow);
            lineGroup.appendChild(dot);

            // Add group to SVG
            svg.appendChild(lineGroup);
        }

        function drawLShape(svg, subordinate, supervisorX, supervisorY) {
            // Use much larger spacing to completely avoid overlapping boxes
            const subBoxTop = subordinate.boxTop;
            const subBoxCenterX = subordinate.boxCenterX;
            const spacing = 120; // Large spacing to clear employee boxes completely

            // Start well above the subordinate box
            const startX = subBoxCenterX;
            const startY = subBoxTop;

            // Intermediate point with large clearance
            const midY = startY - spacing;

            // End well below supervisor box
            const endX = supervisorX;
            const endY = supervisorY - 30; // Large gap from supervisor

            // Create path that routes around boxes
            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            const pathData = `M ${startX} ${startY} L ${startX} ${midY} L ${endX} ${midY} L ${endX} ${endY}`;

            path.setAttribute('d', pathData);
            path.setAttribute('stroke', '#007bff');
            path.setAttribute('stroke-width', '2');
            path.setAttribute('fill', 'none');
            path.setAttribute('stroke-linejoin', 'round');
            path.classList.add('simple-reporting-line');

            // Add arrow pointing to supervisor
            const arrowHead = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
            arrowHead.setAttribute('points', `${endX},${endY} ${endX-4},${endY-8} ${endX+4},${endY-8}`);
            arrowHead.setAttribute('fill', '#007bff');
            arrowHead.classList.add('simple-reporting-line');

            // Add small connection dots at corners
            const dot1 = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            dot1.setAttribute('cx', startX);
            dot1.setAttribute('cy', midY);
            dot1.setAttribute('r', '2');
            dot1.setAttribute('fill', '#007bff');
            dot1.classList.add('simple-reporting-line');

            const dot2 = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            dot2.setAttribute('cx', endX);
            dot2.setAttribute('cy', midY);
            dot2.setAttribute('r', '2');
            dot2.setAttribute('fill', '#007bff');
            dot2.classList.add('simple-reporting-line');

            svg.appendChild(path);
            svg.appendChild(arrowHead);
            svg.appendChild(dot1);
            svg.appendChild(dot2);
        }

        function drawMultipleSubordinateLines(svg, subordinatePositions, supervisorX, supervisorY) {
            // Use large spacing to completely avoid overlapping with employee boxes
            const spacing = 150; // Large space above subordinate boxes to avoid overlap

            // Find the range of subordinate X positions (using box centers)
            const subordinateXs = subordinatePositions.map(sub => sub.boxCenterX);
            const minX = Math.min(...subordinateXs) - 30; // Larger margins
            const maxX = Math.max(...subordinateXs) + 30;

            // Calculate horizontal line Y position (well above all subordinate boxes)
            const subordinateTops = subordinatePositions.map(sub => sub.boxTop);
            const highestSubordinateTop = Math.min(...subordinateTops);
            const horizontalY = highestSubordinateTop - spacing;

            // Draw main vertical line from supervisor (starting from supervisor box bottom)
            const mainVertical = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            mainVertical.setAttribute('x1', supervisorX);
            mainVertical.setAttribute('y1', supervisorY); // Start from bottom of supervisor box
            mainVertical.setAttribute('x2', supervisorX);
            mainVertical.setAttribute('y2', horizontalY);
            mainVertical.setAttribute('stroke', '#007bff');
            mainVertical.setAttribute('stroke-width', '2');
            mainVertical.classList.add('simple-reporting-line');
            svg.appendChild(mainVertical);

            // Draw horizontal connector line with extended margins
            const horizontalLine = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            horizontalLine.setAttribute('x1', minX);
            horizontalLine.setAttribute('y1', horizontalY);
            horizontalLine.setAttribute('x2', maxX);
            horizontalLine.setAttribute('y2', horizontalY);
            horizontalLine.setAttribute('stroke', '#007bff');
            horizontalLine.setAttribute('stroke-width', '2');
            horizontalLine.classList.add('simple-reporting-line');
            svg.appendChild(horizontalLine);

            // Draw vertical lines to each subordinate with large clearance
            subordinatePositions.forEach(sub => {
                const verticalLine = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                verticalLine.setAttribute('x1', sub.boxCenterX);
                verticalLine.setAttribute('y1', horizontalY);
                verticalLine.setAttribute('x2', sub.boxCenterX);
                verticalLine.setAttribute('y2', sub.boxTop);
                verticalLine.setAttribute('stroke', '#007bff');
                verticalLine.setAttribute('stroke-width', '2');
                verticalLine.classList.add('simple-reporting-line');
                svg.appendChild(verticalLine);

                // Add arrow pointing to subordinate box
                const arrow = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
                arrow.setAttribute('points', `${sub.boxCenterX},${sub.boxTop} ${sub.boxCenterX-3},${sub.boxTop-6} ${sub.boxCenterX+3},${sub.boxTop-6}`);
                arrow.setAttribute('fill', '#007bff');
                arrow.classList.add('simple-reporting-line');
                svg.appendChild(arrow);

                // Add connection dot
                const dot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                dot.setAttribute('cx', sub.boxCenterX);
                dot.setAttribute('cy', horizontalY);
                dot.setAttribute('r', '2');
                dot.setAttribute('fill', '#007bff');
                dot.classList.add('simple-reporting-line');
                svg.appendChild(dot);
            });

            // Add main connection dot at supervisor junction
            const mainDot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            mainDot.setAttribute('cx', supervisorX);
            mainDot.setAttribute('cy', horizontalY);
            mainDot.setAttribute('r', '3');
            mainDot.setAttribute('fill', '#007bff');
            mainDot.classList.add('simple-reporting-line');
            svg.appendChild(mainDot);

            // Add arrow pointing to supervisor
            const supervisorArrow = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
            supervisorArrow.setAttribute('points', `${supervisorX},${supervisorEndY} ${supervisorX-4},${supervisorEndY-8} ${supervisorX+4},${supervisorEndY-8}`);
            supervisorArrow.setAttribute('fill', '#007bff');
            supervisorArrow.classList.add('simple-reporting-line');
            svg.appendChild(supervisorArrow);
        }

        function drawSimpleLineTo(fromElement, toEpf) {
            // Find the supervisor element
            const supervisorElement = document.querySelector(`[data-epf="${toEpf}"]`);

            if (!supervisorElement) {
                console.log('Supervisor not found for EPF:', toEpf);
                return;
            }

            // Get container
            const container = document.getElementById('scrollableContainer');
            if (!container) return;

            // Get positions relative to container
            const containerRect = container.getBoundingClientRect();
            const fromRect = fromElement.getBoundingClientRect();
            const toRect = supervisorElement.getBoundingClientRect();

            // Calculate positions within container
            const fromX = Math.round(fromRect.left - containerRect.left + fromRect.width / 2) + 0.5;
            const fromY = fromRect.top - containerRect.top;
            const toX = Math.round(toRect.left - containerRect.left + toRect.width / 2) + 0.5;
            const toY = toRect.top - containerRect.top + toRect.height;

            // Create or get SVG
            let svg = container.querySelector('.simple-reporting-svg');
            if (!svg) {
                svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                svg.classList.add('simple-reporting-svg');
                svg.style.position = 'absolute';
                svg.style.top = '0';
                svg.style.left = '0';
                svg.style.width = '100%';
                svg.style.height = '100%';
                svg.style.pointerEvents = 'none';
                svg.style.zIndex = '1';
                container.appendChild(svg);
            }

            // Draw 90-degree turn path (L-shape, T-shape, or 4-way)
            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');

            // Calculate path with 90-degree turns only
            let pathData = '';

            // Start from employee (bottom of box)
            pathData += `M ${fromX} ${fromY}`;

            // Go up from employee
            const upDistance = 30;
            const upY = fromY - upDistance;
            pathData += ` L ${fromX} ${upY}`;

            // Go horizontally toward supervisor
            pathData += ` L ${toX} ${upY}`;

            // Go down to supervisor (top of box)
            pathData += ` L ${toX} ${toY}`;

            path.setAttribute('d', pathData);
            path.setAttribute('stroke', '#007bff');
            path.setAttribute('stroke-width', '2');
            path.setAttribute('fill', 'none');
            path.setAttribute('stroke-linejoin', 'round');
            path.classList.add('simple-reporting-line');

            // Add arrow at the end (pointing to supervisor)
            const arrowHead = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
            const arrowSize = 6;
            arrowHead.setAttribute('points',
                `${toX},${toY} ${toX-arrowSize},${toY-arrowSize} ${toX+arrowSize},${toY-arrowSize}`
            );
            arrowHead.setAttribute('fill', '#007bff');
            arrowHead.classList.add('simple-reporting-line');

            // Add small circles at turn points for professional look
            const circle1 = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            circle1.setAttribute('cx', fromX);
            circle1.setAttribute('cy', upY);
            circle1.setAttribute('r', '2');
            circle1.setAttribute('fill', '#007bff');
            circle1.classList.add('simple-reporting-line');

            const circle2 = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            circle2.setAttribute('cx', toX);
            circle2.setAttribute('cy', upY);
            circle2.setAttribute('r', '2');
            circle2.setAttribute('fill', '#007bff');
            circle2.classList.add('simple-reporting-line');

            svg.appendChild(path);
            svg.appendChild(arrowHead);
            svg.appendChild(circle1);
            svg.appendChild(circle2);
        }

        // Pass display type to JavaScript
        const displayType = '<?php echo $display_type; ?>';

        // Zoom functionality
        let currentZoom = 1;
        const minZoom = 0.5;
        const maxZoom = 3;
        const zoomStep = 0.1;

        function addZoomFunctionality() {
            const container = document.getElementById('scrollableContainer');
            if (!container) return;

            // Only add zoom for tree and location views, NOT for list view
            if (displayType === 'list') {
                return; // Exit early for list view - allow normal scrolling
            }

            // Add mouse wheel zoom only for tree/location views
            container.addEventListener('wheel', function(e) {
                e.preventDefault();

                const delta = e.deltaY > 0 ? -zoomStep : zoomStep;
                const newZoom = Math.min(Math.max(currentZoom + delta, minZoom), maxZoom);

                if (newZoom !== currentZoom) {
                    currentZoom = newZoom;
                    applyZoom();
                }
            });

            // Add zoom controls UI only for tree/location views
            const zoomControls = document.createElement('div');
            zoomControls.className = 'zoom-controls';
            zoomControls.style.cssText = `
                position: absolute;
                top: 60px;
                right: 15px;
                z-index: 1002;
                display: flex;
                flex-direction: column;
                gap: 5px;
                background: white;
                padding: 10px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            `;

            zoomControls.innerHTML = `
                <button class="zoom-btn" onclick="zoomIn()" style="background: #007bff; color: white; border: none; padding: 8px; border-radius: 4px; cursor: pointer;">
                    <i class="fas fa-plus"></i>
                </button>
                <span class="zoom-level" style="font-size: 12px; text-align: center; color: #666;">100%</span>
                <button class="zoom-btn" onclick="zoomOut()" style="background: #007bff; color: white; border: none; padding: 8px; border-radius: 4px; cursor: pointer;">
                    <i class="fas fa-minus"></i>
                </button>
                <button class="zoom-btn" onclick="resetZoom()" style="background: #6c757d; color: white; border: none; padding: 8px; border-radius: 4px; cursor: pointer; font-size: 10px;">
                    Reset
                </button>
            `;

            container.appendChild(zoomControls);
        }

        function applyZoom() {
            const container = document.getElementById('scrollableContainer');
            if (!container) return;

            // Apply zoom and translation together to the org chart content
            const orgChartContent = container.querySelector('.org-chart-vertical, .location-tree-view');
            if (orgChartContent) {
                orgChartContent.style.transform = `translate(${translateX}px, ${translateY}px) scale(${currentZoom})`;
                orgChartContent.style.transformOrigin = 'top left';
            }

            // Update zoom level display
            const zoomLevel = container.querySelector('.zoom-level');
            if (zoomLevel) {
                zoomLevel.textContent = Math.round(currentZoom * 100) + '%';
            }

            // Redraw lines immediately after zoom for better responsiveness
            requestAnimationFrame(() => {
                if (typeof drawSimpleReportingLines === 'function') {
                    drawSimpleReportingLines();
                }
            });
        }

        // Global zoom functions
        window.zoomIn = function() {
            const newZoom = Math.min(currentZoom + zoomStep, maxZoom);
            if (newZoom !== currentZoom) {
                currentZoom = newZoom;
                applyZoom();
            }
        };

        window.zoomOut = function() {
            const newZoom = Math.max(currentZoom - zoomStep, minZoom);
            if (newZoom !== currentZoom) {
                currentZoom = newZoom;
                applyZoom();
            }
        };

        window.resetZoom = function() {
            currentZoom = 1;
            applyZoom();
        };

        // Initialize zoom functionality
        document.addEventListener('DOMContentLoaded', function() {
            addZoomFunctionality();

            // Only draw lines for tree views, not list view
            if (!document.querySelector('.list-view')) {
                setTimeout(function() {
                    console.log('DOMContentLoaded: Attempting to draw lines...');
                    if (typeof drawSimpleReportingLines === 'function') {
                        drawSimpleReportingLines();
                    }
                }, 100);
            } else {
                console.log('List view detected - skipping line drawing initialization');
            }
        });

        // Redraw lines on window resize with debouncing (tree views only)
        let resizeTimeout;
        window.addEventListener('resize', function() {
            // Skip resize line drawing for list view
            if (document.querySelector('.list-view')) {
                return;
            }

            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                if (typeof drawSimpleReportingLines === 'function') {
                    drawSimpleReportingLines();
                }
            }, 100);
        });

        // Expandable List View Functionality
        function initializeExpandableList() {
            const expandButtons = document.querySelectorAll('.expand-btn');

            expandButtons.forEach(function(button) {
                button.addEventListener('click', function(e) {
                    e.stopPropagation();

                    const epf = this.getAttribute('data-epf');
                    const childrenContainer = document.getElementById('children-' + epf);
                    const icon = this.querySelector('i');
                    const employeeRow = this.closest('.employee-row');

                    if (childrenContainer) {
                        const isExpanded = childrenContainer.style.display !== 'none';

                        if (isExpanded) {
                            // Collapse
                            childrenContainer.style.display = 'none';
                            icon.className = 'fas fa-plus';
                            this.className = 'expand-btn btn btn-sm btn-outline-primary me-2';
                            employeeRow.style.backgroundColor = 'white';
                        } else {
                            // Expand
                            childrenContainer.style.display = 'block';
                            icon.className = 'fas fa-minus';
                            this.className = 'expand-btn btn btn-sm btn-success me-2';
                            employeeRow.style.backgroundColor = '#f8f9fa';
                        }
                    }
                });
            });

            // Also allow clicking on the entire row to expand/collapse
            document.querySelectorAll('.employee-row').forEach(function(row) {
                const epf = row.getAttribute('data-epf');
                const expandBtn = row.querySelector('.expand-btn');

                if (expandBtn) {
                    row.addEventListener('click', function(e) {
                        if (e.target.closest('.expand-btn')) return; // Don't double-trigger
                        expandBtn.click();
                    });

                    // Add enhanced hover effects for all rows
                    row.style.transition = 'all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
                    row.addEventListener('mouseenter', function() {
                        this.style.transform = 'translateX(8px) translateY(-2px)';
                        this.style.boxShadow = '0 8px 25px rgba(0,123,255,0.15), 0 3px 10px rgba(0,0,0,0.1)';
                        this.style.borderColor = '#007bff';
                        this.style.backgroundColor = '#f8f9fa';

                        // Enhance avatar on hover
                        const avatar = this.querySelector('.employee-avatar');
                        if (avatar) {
                            avatar.style.transform = 'scale(1.1)';
                            avatar.style.boxShadow = '0 4px 12px rgba(0,0,0,0.3)';
                        }
                    });
                    row.addEventListener('mouseleave', function() {
                        this.style.transform = 'translateX(0) translateY(0)';
                        this.style.boxShadow = '0 2px 4px rgba(0,0,0,0.08)';
                        this.style.borderColor = '#e0e0e0';
                        this.style.backgroundColor = 'white';

                        // Reset avatar
                        const avatar = this.querySelector('.employee-avatar');
                        if (avatar) {
                            avatar.style.transform = 'scale(1)';
                            avatar.style.boxShadow = '0 3px 6px rgba(0,0,0,0.2)';
                        }
                    });
                }
            });
        }

        // Initialize expandable list when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initializeExpandableList();
        });

        // Add keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.target.closest('.list-view')) {
                if (e.key === 'ArrowRight') {
                    // Expand all visible collapsed items
                    document.querySelectorAll('.expand-btn .fa-plus').forEach(btn => {
                        if (btn.closest('.expand-btn').offsetParent !== null) { // Check if visible
                            btn.closest('.expand-btn').click();
                        }
                    });
                } else if (e.key === 'ArrowLeft') {
                    // Collapse all visible expanded items
                    document.querySelectorAll('.expand-btn .fa-minus').forEach(btn => {
                        if (btn.closest('.expand-btn').offsetParent !== null) { // Check if visible
                            btn.closest('.expand-btn').click();
                        }
                    });
                }
            }
        });
    </script>
</body>
</html>