<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

// Get complete employee data for the current user
// First try to use session data, then fall back to database lookup
if (!isset($user['epf_number']) || $user['epf_number'] === null) {
    // Try to get from session first
    if (isset($_SESSION['epf_number'])) {
        $user['epf_number'] = $_SESSION['epf_number'];
        $user['name'] = $_SESSION['name'] ?? null;
        $user['location'] = $_SESSION['location_name'] ?? null;
        $user['department'] = $_SESSION['department'] ?? null;
    } else {
        // Fall back to database lookup via users table
        $employee_data = $db->fetch("
            SELECT e.epf_number, e.name, e.designation, e.department, l.location_name
            FROM users u
            LEFT JOIN employees e ON u.employee_id = e.id
            LEFT JOIN locations l ON e.location_id = l.id
            WHERE u.id = ?
        ", [$user['id']]);

        if ($employee_data) {
            $user['epf_number'] = $employee_data['epf_number'];
            $user['name'] = $employee_data['name'];
            $user['location'] = $employee_data['location_name'] ?? $employee_data['department'];
            $user['designation'] = $employee_data['designation'];
            $user['department'] = $employee_data['department'];
        }
    }
}

// Final fallback - direct lookup by EPF from employees table if we have it
if (isset($user['epf_number']) && $user['epf_number'] && (!isset($user['name']) || !$user['name'])) {
    $employee_data = $db->fetch("
        SELECT e.*, l.location_name
        FROM employees e
        LEFT JOIN locations l ON e.location_id = l.id
        WHERE e.epf_number = ? AND e.is_active = 1
    ", [$user['epf_number']]);

    if ($employee_data) {
        $user['name'] = $employee_data['name'];
        $user['location'] = $employee_data['location_name'] ?? $employee_data['department'];
        $user['designation'] = $employee_data['designation'];
        $user['department'] = $employee_data['department'];
    }
}

// Debug: Uncomment to see user data
// echo "<pre>Session Data:\n"; print_r($_SESSION); echo "\nUser Data:\n"; print_r($user); echo "</pre>";

$message = '';
$request_id = $_GET['id'] ?? null;
$request_data = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'submit' || $action === 'edit') {
        $request_type = $_POST['request_type'];
        $purpose = trim($_POST['purpose']);
        $departure_location = trim($_POST['departure_location']);
        $destination = trim($_POST['destination']);
        $departure_date = $_POST['departure_date'];
        $departure_time = $_POST['departure_time'];
        $return_date = !empty($_POST['return_date']) ? $_POST['return_date'] : null;
        $return_time = !empty($_POST['return_time']) ? $_POST['return_time'] : null;
        $passenger_count = intval($_POST['passenger_count']);
        $passenger_names = trim($_POST['passenger_names']);
        $special_requirements = trim($_POST['special_requirements']);
        $urgency_level = $_POST['urgency_level'];

        // Validate required fields
        if (empty($purpose) || empty($departure_location) || empty($destination) ||
            empty($departure_date) || empty($departure_time) || empty($passenger_names)) {
            $message = "Error: Please fill in all required fields.";
        } else {
            try {
                if ($action === 'submit') {
                    // Insert new request
                    $query = "INSERT INTO transport_requests (
                        emp_id, emp_number, emp_name, emp_location, request_type, purpose,
                        departure_location, destination, departure_date, departure_time,
                        return_date, return_time, passenger_count, passenger_names,
                        special_requirements, urgency_level, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";

                    $db->execute($query, [
                        $user['id'],
                        $user['epf_number'] ?? $user['emp_number'] ?? $user['username'] ?? 'N/A',
                        $user['name'] ?? 'N/A',
                        $user['location'] ?? 'Head Office',
                        $request_type, $purpose, $departure_location, $destination,
                        $departure_date, $departure_time, $return_date, $return_time,
                        $passenger_count, $passenger_names, $special_requirements, $urgency_level
                    ]);

                    $message = "Transport request submitted successfully! Request ID: " . $db->lastInsertId();

                } else {
                    // Update existing request
                    $query = "UPDATE transport_requests SET
                        request_type = ?, purpose = ?, departure_location = ?, destination = ?,
                        departure_date = ?, departure_time = ?, return_date = ?, return_time = ?,
                        passenger_count = ?, passenger_names = ?, special_requirements = ?,
                        urgency_level = ?, updated_at = NOW()
                        WHERE id = ? AND emp_id = ?";

                    $db->execute($query, [
                        $request_type, $purpose, $departure_location, $destination,
                        $departure_date, $departure_time, $return_date, $return_time,
                        $passenger_count, $passenger_names, $special_requirements,
                        $urgency_level, $request_id, $user['id']
                    ]);

                    $message = "Transport request updated successfully!";
                }

                // Clear form data after successful submission
                if ($action === 'submit') {
                    $_POST = [];
                }
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
            }
        }
    } elseif ($action === 'cancel' && $request_id) {
        try {
            $db->execute("UPDATE transport_requests SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND emp_id = ?",
                        [$request_id, $user['id']]);
            $message = "Transport request cancelled successfully!";
        } catch (Exception $e) {
            $message = "Error cancelling request: " . $e->getMessage();
        }
    }
}

// Get request data for editing
if ($request_id) {
    // Try to match by both user ID and employee ID for compatibility
    $request_data = $db->fetch("
        SELECT * FROM transport_requests tr
        WHERE tr.id = ?
        AND (tr.emp_id = ? OR tr.emp_id IN (
            SELECT u.employee_id FROM users u WHERE u.id = ?
        ))",
        [$request_id, $user['id'], $user['id']]);

    // If still not found, try matching by EPF number
    if (!$request_data && isset($user['epf_number'])) {
        $request_data = $db->fetch("
            SELECT * FROM transport_requests
            WHERE id = ? AND emp_number = ?",
            [$request_id, $user['epf_number']]);
    }
}

// Get user's recent requests - try multiple approaches
$recent_requests = [];

// First try: by emp_id
$recent_requests = $db->fetchAll("
    SELECT * FROM transport_requests
    WHERE emp_id = ?
    ORDER BY created_at DESC LIMIT 10",
    [$user['id']]
);

// If no results and we have EPF number, try by emp_number
if (empty($recent_requests) && !empty($user['epf_number'])) {
    $recent_requests = $db->fetchAll("
        SELECT * FROM transport_requests
        WHERE emp_number = ?
        ORDER BY created_at DESC LIMIT 10",
        [$user['epf_number']]
    );
}

// If still no results, try by emp_name
if (empty($recent_requests) && !empty($user['name'])) {
    $recent_requests = $db->fetchAll("
        SELECT * FROM transport_requests
        WHERE emp_name = ?
        ORDER BY created_at DESC LIMIT 10",
        [$user['name']]
    );
}

// Debug: Uncomment to see data (remove in production)
// echo "<pre>User Data:\n";
// echo "User ID: " . $user['id'] . "\n";
// echo "EPF Number: " . ($user['epf_number'] ?? 'NULL') . "\n";
// echo "Name: " . ($user['name'] ?? 'NULL') . "\n";
// echo "Recent requests count: " . count($recent_requests) . "\n";
// if (!empty($recent_requests)) {
//     echo "First request: "; print_r($recent_requests[0]);
// } else {
//     echo "No recent requests found.\n";
//     // Check if table has any data at all
//     try {
//         $all_requests = $db->fetchAll("SELECT COUNT(*) as total FROM transport_requests");
//         echo "Total requests in database: " . $all_requests[0]['total'] . "\n";
//     } catch (Exception $e) {
//         echo "Error checking total requests: " . $e->getMessage() . "\n";
//     }
// }
// echo "</pre>";

// Location options
$locations = ['7C', 'Pannala', 'Kobeigane', 'JECOE', 'Head Office'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Transport Request</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
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
            background: linear-gradient(135deg, #f5f7fa, #c3cfe2);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-content {
            margin-left: 280px !important;
            min-height: 100vh;
            width: calc(100% - 280px) !important;
            position: relative;
        }

        .dashboard-content {
            background: transparent;
            padding: 2rem;
            min-height: calc(100vh - 40px);
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
            padding: 2.5rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
        }

        .page-header::after {
            content: '';
            position: absolute;
            bottom: -30px;
            left: -30px;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }

        .page-header h1 {
            font-size: 2.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 3;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .page-header p {
            font-size: 1.2rem;
            opacity: 0.9;
            margin: 0;
            position: relative;
            z-index: 3;
            font-weight: 400;
        }

        .page-header .d-flex {
            position: relative;
            z-index: 3;
        }

        .header-content {
            position: relative;
            z-index: 3;
        }

        .page-header-logo {
            height: 60px;
            width: auto;
            background: rgba(255, 255, 255, 0.9);
            padding: 8px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 3;
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

        .section-header {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-left: 4px solid #007bff;
            margin: 1.5rem 0 1rem 0;
            padding: 0.75rem 1rem;
            border-radius: 0.375rem;
        }
        .form-step {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            padding: 1.5rem;
        }
        .step-number {
            background: #007bff;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 0.75rem;
        }
        .required-field::after {
            content: " *";
            color: #dc3545;
            font-weight: bold;
        }
        .form-control:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .info-tooltip {
            cursor: help;
            color: #6c757d;
        }
        .passenger-info {
            background: #f8f9fa;
            border-radius: 0.375rem;
            padding: 1rem;
            border: 1px solid #dee2e6;
        }

                /* Enhanced Form Cards */
        .form-card, .content-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .form-card:hover, .content-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
        }

        .form-card::before, .content-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--info-color));
        }

        .card-header-modern {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-bottom: 2px solid #e9ecef;
            border-radius: 20px 20px 0 0 !important;
            padding: 1.5rem 2rem;
            margin: -2rem -2rem 2rem -2rem;
            position: relative;
        }

        .card-header-modern::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--info-color));
            border-radius: 20px 20px 0 0;
        }

        .card-header-modern h5 {
            color: #2c3e50;
            font-weight: 600;
            margin: 0;
            font-size: 1.25rem;
        }

        .card-header-modern i {
            color: var(--primary-color);
        }

        /* Enhanced Form Controls */
        .form-control, .form-select, .form-check-input {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .form-control, .form-select {
            padding: 0.75rem 1rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
            transform: translateY(-2px);
        }

        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .form-label i {
            color: var(--primary-color);
            margin-right: 0.5rem;
        }

        /* Enhanced Buttons */
        .btn {
            border-radius: 12px;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            border: none;
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success-color), #1e7e34);
            border: none;
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--warning-color), #fd7e14);
            border: none;
            color: #212529;
        }

        .btn-outline-secondary {
            border: 2px solid var(--secondary-color);
            color: var(--secondary-color);
        }

        /* Enhanced Request Status Badges */
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background: linear-gradient(135deg, #ffc107, #fd7e14);
            color: #212529;
        }

        .status-approved {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }

        .status-rejected {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }

        .status-completed {
            background: linear-gradient(135deg, #6f42c1, #5a6268);
            color: white;
        }

        .status-cancelled {
            background: linear-gradient(135deg, #6c757d, #545b62);
            color: white;
        }

        /* Enhanced Breadcrumb */
        .breadcrumb {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
        }

        /* Form Section Dividers */
        .form-section {
            border-left: 4px solid var(--primary-color);
            padding-left: 1rem;
            margin: 1.5rem 0;
        }

        .form-section h6 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Step Indicator */
        .step-indicator {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            position: relative;
        }

        .step-indicator::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 10%;
            right: 10%;
            height: 2px;
            background: #e9ecef;
            transform: translateY(-50%);
            z-index: 1;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            min-width: 80px;
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
            font-weight: 600;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }

        .step-title {
            font-size: 0.85rem;
            font-weight: 500;
            color: #6c757d;
            text-align: center;
        }

        .step.active .step-number {
            background: var(--primary-color);
            color: white;
            transform: scale(1.1);
        }

        .step.active .step-title {
            color: var(--primary-color);
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .step-indicator {
                overflow-x: auto;
                justify-content: flex-start;
                gap: 2rem;
            }
            .step {
                min-width: 60px;
                flex-shrink: 0;
            }
            .step-title {
                font-size: 0.75rem;
            }
        }

        /* Modern Tab Styling */
        .nav-tabs-modern {
            border: none;
            background: white;
            border-radius: 15px;
            padding: 0.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .nav-tabs-modern .nav-link {
            border: none;
            border-radius: 10px;
            color: #6c757d;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            margin: 0 0.25rem;
            transition: all 0.3s ease;
            background: transparent;
        }

        .nav-tabs-modern .nav-link:hover {
            color: var(--primary-color);
            background: rgba(0, 123, 255, 0.1);
            transform: translateY(-2px);
        }

        .nav-tabs-modern .nav-link.active {
            color: white;
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
        }

        .nav-tabs-modern .badge {
            font-size: 0.7rem;
        }

        /* Request History Cards */
        .request-history-card {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            border: 2px solid #f8f9fa;
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .request-history-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }

        .request-details {
            flex-grow: 1;
        }

        .request-details small {
            line-height: 1.4;
        }

        .request-actions {
            margin-top: auto;
        }

        /* Tab Content */
        .tab-pane {
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .nav-tabs-modern {
                padding: 0.25rem;
            }
            .nav-tabs-modern .nav-link {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }
        }

        /* Simple tab content */
        #history-content {
            display: none;
            width: 100%;
            min-height: 400px;
        }

        #form-content {
            display: block;
            width: 100%;
            min-height: 400px;
        }

        #form-content {
            min-height: 200px;
        }

        /* Enhanced Request Cards */
        .request-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            border-left: 4px solid #e9ecef;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .request-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
            border-left-color: var(--primary-color);
        }

        .request-card.status-pending {
            border-left-color: #ffc107;
        }

        .request-card.status-approved {
            border-left-color: #28a745;
        }

        .request-card.status-rejected {
            border-left-color: #dc3545;
        }

        /* Enhanced Alert Styling */
        .alert {
            border-radius: 15px;
            border: none;
            padding: 1rem 1.5rem;
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }

        .alert::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            width: 4px;
            background: currentColor;
            opacity: 0.8;
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(32, 201, 151, 0.1));
            color: #155724;
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(200, 35, 51, 0.1));
            color: #721c24;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        }

        .empty-state i {
            color: #dee2e6;
            margin-bottom: 1rem;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(1.05); }
            100% { opacity: 1; transform: scale(1); }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }

            .dashboard-content {
                padding: 1rem;
            }

            .page-header h1 {
                font-size: 2rem;
            }

            .form-card, .content-card {
                margin-bottom: 1rem;
                padding: 1.5rem;
            }

            .btn {
                padding: 0.6rem 1.2rem;
                font-size: 0.9rem;
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
                        <h1><i class="fas fa-route me-3"></i>Transport Request</h1>
                        <p>Submit requests for employee transportation services</p>
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
                    <li class="breadcrumb-item"><a href="index.php">Transport Management</a></li>
                    <li class="breadcrumb-item active">Transport Request</li>
                </ol>
            </nav>

        <?php if ($message): ?>
        <div class="alert alert-<?php echo strpos($message, 'Error') !== false ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Simple Tab Buttons -->
        <div class="mb-4">
            <button class="btn btn-primary me-2" id="form-btn" onclick="switchTab(false)">
                <i class="fas fa-plus me-2"></i>New Request
            </button>
            <button class="btn btn-outline-primary" id="requests-btn" onclick="switchTab(true)">
                <i class="fas fa-history me-2"></i>Recent Requests (<?php echo count($recent_requests); ?>)
            </button>
        </div>

            <!-- Form and Recent Requests displayed directly -->


            <!-- Tab Content -->
            <div>
                <!-- Form Tab -->
                <div id="form-content">
                    <div class="form-card">
                        <div class="card-header-modern">
                            <h5>
                                <i class="fas fa-<?php echo $request_id ? 'edit' : 'plus'; ?> me-2"></i>
                                <?php echo $request_id ? 'Edit Transport Request' : 'New Transport Request'; ?>
                            </h5>
                        </div>
                        <!-- Step Progress Indicator -->
                        <div class="step-indicator mb-4">
                            <div class="step active" data-step="1">
                                <div class="step-number">1</div>
                                <div class="step-title">Trip Info</div>
                            </div>
                            <div class="step" data-step="2">
                                <div class="step-number">2</div>
                                <div class="step-title">Your Details</div>
                            </div>
                            <div class="step" data-step="3">
                                <div class="step-number">3</div>
                                <div class="step-title">Passengers</div>
                            </div>
                            <div class="step" data-step="4">
                                <div class="step-number">4</div>
                                <div class="step-title">Travel Details</div>
                            </div>
                        </div>

                        <form method="POST" id="transportForm">
                            <input type="hidden" name="action" value="<?php echo $request_id ? 'edit' : 'submit'; ?>">

                            <!-- Step 1: Basic Trip Information -->
                            <div class="form-section">
                                <h6><i class="fas fa-route me-2"></i>Trip Information</h6>
                                <div class="form-step">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label"><i class="fas fa-route"></i>Trip Type *</label>
                                            <select class="form-select" name="request_type" required>
                                                <option value="">🚗 Choose your trip type...</option>
                                                <option value="one_way" <?php echo ($request_data['request_type'] ?? $_POST['request_type'] ?? '') === 'one_way' ? 'selected' : ''; ?>>
                                                    ➡️ One Way Trip
                                                </option>
                                                <option value="round_trip" <?php echo ($request_data['request_type'] ?? $_POST['request_type'] ?? '') === 'round_trip' ? 'selected' : ''; ?>>
                                                    🔄 Round Trip (Return same day)
                                                </option>
                                                <option value="multi_destination" <?php echo ($request_data['request_type'] ?? $_POST['request_type'] ?? '') === 'multi_destination' ? 'selected' : ''; ?>>
                                                    🗺️ Multiple Destinations
                                                </option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label"><i class="fas fa-exclamation-triangle"></i>Priority Level *</label>
                                            <select class="form-select" name="urgency_level" required>
                                                <option value="">⚡ How urgent is this?</option>
                                                <option value="low" <?php echo ($request_data['urgency_level'] ?? $_POST['urgency_level'] ?? '') === 'low' ? 'selected' : ''; ?>>
                                                    🟢 Low - Can wait (3-5 days)
                                                </option>
                                                <option value="medium" <?php echo ($request_data['urgency_level'] ?? $_POST['urgency_level'] ?? '') === 'medium' ? 'selected' : ''; ?>>
                                                    🟡 Medium - Normal (1-2 days)
                                                </option>
                                                <option value="high" <?php echo ($request_data['urgency_level'] ?? $_POST['urgency_level'] ?? '') === 'high' ? 'selected' : ''; ?>>
                                                    🟠 High - Soon (Within 24 hours)
                                                </option>
                                                <option value="urgent" <?php echo ($request_data['urgency_level'] ?? $_POST['urgency_level'] ?? '') === 'urgent' ? 'selected' : ''; ?>>
                                                    🔴 Urgent - ASAP (Emergency)
                                                </option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label"><i class="fas fa-clipboard"></i>Purpose of Travel *</label>
                                        <textarea class="form-control" name="purpose" rows="3" required
                                                  placeholder="📝 Please describe why you need transport (e.g., Client meeting, Site visit, Training, etc.)"><?php echo htmlspecialchars($request_data['purpose'] ?? $_POST['purpose'] ?? ''); ?></textarea>
                                        <div class="form-text">
                                            <i class="fas fa-lightbulb text-warning"></i> Be specific - this helps us assign the right vehicle and driver
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 2: Employee Information -->
                            <div class="form-section">
                                <h6><i class="fas fa-user-circle me-2"></i>Employee Information</h6>
                                <div class="form-step">
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label"><i class="fas fa-id-badge"></i>Employee Number</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['epf_number'] ?? $user['emp_number'] ?? $user['username'] ?? 'N/A'); ?>" readonly style="background: linear-gradient(135deg, #f8f9fa, #e9ecef);">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label"><i class="fas fa-user"></i>Employee Name</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['name'] ?? 'N/A'); ?>" readonly style="background: linear-gradient(135deg, #f8f9fa, #e9ecef);">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label"><i class="fas fa-building"></i>Your Location</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['location'] ?? 'Head Office'); ?>" readonly style="background: linear-gradient(135deg, #f8f9fa, #e9ecef);">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 3: Passenger Information -->
                            <div class="form-section">
                                <h6><i class="fas fa-users me-2"></i>Passenger Information</h6>
                                <div class="form-step">
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label class="form-label required-field">
                                                <i class="fas fa-users me-2"></i>Number of Passengers *
                                            </label>
                                            <input type="number" class="form-control" name="passenger_count" min="1" max="50" required
                                                   value="<?php echo htmlspecialchars($request_data['passenger_count'] ?? $_POST['passenger_count'] ?? '1'); ?>"
                                                   onchange="updatePassengerHint(this.value)">
                                            <div class="form-text">Maximum 50 passengers per request</div>
                                        </div>
                                        <div class="col-md-8">
                                            <div class="alert alert-light mb-0">
                                                <i class="fas fa-info-circle text-primary me-2"></i>
                                                <span id="passengerHint">🚗 Individual transport - Car or small vehicle suitable</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label required-field">
                                            <i class="fas fa-list me-2"></i>Passenger Names *
                                        </label>
                                        <div id="passengerNameContainer">
                                            <!-- Dynamic passenger name fields will be generated here -->
                                        </div>
                                        <textarea class="form-control d-none" name="passenger_names" id="passenger_names_hidden" required><?php echo htmlspecialchars($request_data['passenger_names'] ?? $_POST['passenger_names'] ?? ''); ?></textarea>
                                        <div class="form-text">
                                            <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                                            Required for security and insurance purposes
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">
                                            <i class="fas fa-wheelchair me-2"></i>Special Requirements
                                        </label>
                                        <textarea class="form-control" name="special_requirements" rows="2"
                                                  placeholder="Any special needs? (e.g., wheelchair access, extra luggage space, child seats, etc.)"><?php echo htmlspecialchars($request_data['special_requirements'] ?? $_POST['special_requirements'] ?? ''); ?></textarea>
                                        <div class="form-text">Optional - helps us prepare the right vehicle</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 4: Travel Details -->
                            <div class="form-section">
                                <h6><i class="fas fa-map-marked-alt me-2"></i>Travel Details</h6>
                                <div class="form-step">
                                    <!-- Enhanced Location Details -->
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <label class="form-label">
                                                <i class="fas fa-map-marker-alt text-success"></i>Pickup Location *
                                            </label>
                                            <input type="text" class="form-control" name="departure_location" required
                                                   value="<?php echo htmlspecialchars($request_data['departure_location'] ?? $_POST['departure_location'] ?? ''); ?>"
                                                   placeholder="🏢 e.g., Head Office, 7C, Pannala">
                                            <div class="form-text">
                                                <i class="fas fa-info-circle text-primary"></i> Where should the vehicle pick you up?
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">
                                                <i class="fas fa-map-marker-alt text-danger me-2"></i>Destination *
                                            </label>
                                            <input type="text" class="form-control" name="destination" required
                                                   value="<?php echo htmlspecialchars($request_data['destination'] ?? $_POST['destination'] ?? ''); ?>"
                                                   placeholder="e.g., Client office, Bank, Airport">
                                            <div class="form-text">Where are you going?</div>
                                        </div>
                                    </div>

                                <!-- Departure Schedule -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label required-field">
                                            <i class="fas fa-calendar me-2"></i>Departure Date
                                        </label>
                                        <input type="date" class="form-control" name="departure_date" required
                                               value="<?php echo htmlspecialchars($request_data['departure_date'] ?? $_POST['departure_date'] ?? ''); ?>"
                                               min="<?php echo date('Y-m-d'); ?>">
                                        <div class="form-text">When do you need to leave?</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label required-field">
                                            <i class="fas fa-clock me-2"></i>Departure Time
                                        </label>
                                        <div class="row">
                                            <div class="col-4">
                                                <select class="form-select" name="departure_hour" required>
                                                    <option value="">Hour</option>
                                                    <?php
                                                    $selected_time = $request_data['departure_time'] ?? $_POST['departure_time'] ?? '';
                                                    $selected_hour = '';
                                                    if ($selected_time) {
                                                        $time_parts = explode(':', $selected_time);
                                                        $hour_24 = intval($time_parts[0]);
                                                        $selected_hour = $hour_24 == 0 ? 12 : ($hour_24 > 12 ? $hour_24 - 12 : $hour_24);
                                                    }
                                                    for ($h = 1; $h <= 12; $h++) {
                                                        $sel = ($selected_hour == $h) ? 'selected' : '';
                                                        echo "<option value=\"$h\" $sel>$h</option>";
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                            <div class="col-4">
                                                <select class="form-select" name="departure_minute" required>
                                                    <option value="">Min</option>
                                                    <?php
                                                    $selected_minute = '';
                                                    if ($selected_time) {
                                                        $time_parts = explode(':', $selected_time);
                                                        $selected_minute = intval($time_parts[1]);
                                                    }
                                                    $minutes = [0, 15, 30, 45];
                                                    foreach ($minutes as $m) {
                                                        $sel = ($selected_minute == $m) ? 'selected' : '';
                                                        $display = sprintf('%02d', $m);
                                                        echo "<option value=\"$m\" $sel>$display</option>";
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                            <div class="col-4">
                                                <select class="form-select" name="departure_ampm" required>
                                                    <option value="">AM/PM</option>
                                                    <?php
                                                    $selected_ampm = '';
                                                    if ($selected_time) {
                                                        $hour_24 = intval(explode(':', $selected_time)[0]);
                                                        $selected_ampm = $hour_24 < 12 ? 'AM' : 'PM';
                                                    }
                                                    echo '<option value="AM"' . ($selected_ampm == 'AM' ? ' selected' : '') . '>AM</option>';
                                                    echo '<option value="PM"' . ($selected_ampm == 'PM' ? ' selected' : '') . '>PM</option>';
                                                    ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-text">Pick-up time (15-minute intervals)</div>
                                        <input type="hidden" name="departure_time" id="departure_time_hidden">
                                    </div>
                                </div>

                                <!-- Return Details (conditional) -->
                                <div id="returnDetails" style="display: none;">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Return Journey:</strong> Please specify when you need to return
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">
                                                <i class="fas fa-calendar me-2"></i>Return Date
                                            </label>
                                            <input type="date" class="form-control" name="return_date"
                                                   value="<?php echo htmlspecialchars($request_data['return_date'] ?? $_POST['return_date'] ?? ''); ?>"
                                                   min="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">
                                                <i class="fas fa-clock me-2"></i>Return Time
                                            </label>
                                            <div class="row">
                                                <div class="col-4">
                                                    <select class="form-select" name="return_hour">
                                                        <option value="">Hour</option>
                                                        <?php
                                                        $selected_return_time = $request_data['return_time'] ?? $_POST['return_time'] ?? '';
                                                        $selected_return_hour = '';
                                                        if ($selected_return_time) {
                                                            $time_parts = explode(':', $selected_return_time);
                                                            $hour_24 = intval($time_parts[0]);
                                                            $selected_return_hour = $hour_24 == 0 ? 12 : ($hour_24 > 12 ? $hour_24 - 12 : $hour_24);
                                                        }
                                                        for ($h = 1; $h <= 12; $h++) {
                                                            $sel = ($selected_return_hour == $h) ? 'selected' : '';
                                                            echo "<option value=\"$h\" $sel>$h</option>";
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                                <div class="col-4">
                                                    <select class="form-select" name="return_minute">
                                                        <option value="">Min</option>
                                                        <?php
                                                        $selected_return_minute = '';
                                                        if ($selected_return_time) {
                                                            $time_parts = explode(':', $selected_return_time);
                                                            $selected_return_minute = intval($time_parts[1]);
                                                        }
                                                        $minutes = [0, 15, 30, 45];
                                                        foreach ($minutes as $m) {
                                                            $sel = ($selected_return_minute == $m) ? 'selected' : '';
                                                            $display = sprintf('%02d', $m);
                                                            echo "<option value=\"$m\" $sel>$display</option>";
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                                <div class="col-4">
                                                    <select class="form-select" name="return_ampm">
                                                        <option value="">AM/PM</option>
                                                        <?php
                                                        $selected_return_ampm = '';
                                                        if ($selected_return_time) {
                                                            $hour_24 = intval(explode(':', $selected_return_time)[0]);
                                                            $selected_return_ampm = $hour_24 < 12 ? 'AM' : 'PM';
                                                        }
                                                        echo '<option value="AM"' . ($selected_return_ampm == 'AM' ? ' selected' : '') . '>AM</option>';
                                                        echo '<option value="PM"' . ($selected_return_ampm == 'PM' ? ' selected' : '') . '>PM</option>';
                                                        ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <input type="hidden" name="return_time" id="return_time_hidden">
                                        </div>
                                    </div>
                                </div>
                            </div>


                            <!-- Submit Buttons -->
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <?php if ($request_id && ($request_data['status'] ?? '') === 'pending' && hasModulePermission($db, 'transport.cancel_request')): ?>
                                    <button type="button" class="btn btn-danger" onclick="cancelRequest()">
                                        <i class="fas fa-times me-2"></i>Cancel Request
                                    </button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-secondary" onclick="resetForm()">
                                    <i class="fas fa-undo me-2"></i>Reset
                                </button>
                                <?php if (hasModulePermission($db, 'transport.submit_request')): ?>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i>
                                    <?php echo $request_id ? 'Update Request' : 'Submit Request'; ?>
                                </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="content-card mt-4" style="display: none;">
                    <div class="card-header-modern">
                        <h5><i class="fas fa-history me-2"></i>Recent Requests (<?php echo count($recent_requests); ?>)</h5>
                    </div>

                    <?php if (empty($recent_requests)): ?>
                    <div class="p-4 text-center">
                        <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No recent requests found</p>
                    </div>
                    <?php else: ?>
                    <div class="row g-3 p-3">
                        <?php foreach ($recent_requests as $request): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo htmlspecialchars($request['destination']); ?></h6>
                                    <p class="card-text">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i>
                                            <?php echo date('M d, Y', strtotime($request['departure_date'])); ?><br>
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo date('H:i', strtotime($request['departure_time'])); ?><br>
                                            <i class="fas fa-users me-1"></i>
                                            <?php echo $request['passenger_count']; ?> passenger(s)
                                        </small>
                                    </p>
                                    <span class="badge bg-<?php echo $request['status'] === 'pending' ? 'warning' : ($request['status'] === 'completed' ? 'success' : 'primary'); ?>">
                                        <?php echo ucfirst($request['status']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>

    <!-- Cancel Confirmation Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cancel Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to cancel this transport request?</p>
                    <p class="text-danger"><small>This action cannot be undone.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No, Keep Request</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="cancel">
                        <button type="submit" class="btn btn-danger">Yes, Cancel Request</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Simple tab functionality - just hide/show existing content
        function switchTab(showRequests) {
            const formCard = document.querySelector('.form-card');
            const requestsCard = document.querySelector('.content-card.mt-4');

            if (showRequests) {
                if (formCard) formCard.style.display = 'none';
                if (requestsCard) requestsCard.style.display = 'block';
                // Update button styles
                document.getElementById('form-btn').classList.remove('btn-primary');
                document.getElementById('form-btn').classList.add('btn-outline-primary');
                document.getElementById('requests-btn').classList.remove('btn-outline-primary');
                document.getElementById('requests-btn').classList.add('btn-primary');
            } else {
                if (formCard) formCard.style.display = 'block';
                if (requestsCard) requestsCard.style.display = 'none';
                // Update button styles
                document.getElementById('requests-btn').classList.remove('btn-primary');
                document.getElementById('requests-btn').classList.add('btn-outline-primary');
                document.getElementById('form-btn').classList.remove('btn-outline-primary');
                document.getElementById('form-btn').classList.add('btn-primary');
            }
        }

        // Show/hide return details based on request type
        document.querySelector('select[name="request_type"]').addEventListener('change', function() {
            const returnDetails = document.getElementById('returnDetails');
            if (this.value === 'round_trip' || this.value === 'multi_destination') {
                returnDetails.style.display = 'block';
                returnDetails.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else {
                returnDetails.style.display = 'none';
            }
        });

        // Initialize return details visibility on page load
        document.addEventListener('DOMContentLoaded', function() {
            const requestType = document.querySelector('select[name="request_type"]').value;
            if (requestType === 'round_trip' || requestType === 'multi_destination') {
                document.getElementById('returnDetails').style.display = 'block';
            }
        });

        // Update passenger hint based on count
        function updatePassengerHint(count) {
            const hint = document.getElementById('passengerHint');
            if (count == 1) {
                hint.innerHTML = 'Individual transport - Car or small vehicle suitable';
            } else if (count <= 4) {
                hint.innerHTML = `Small group (${count} people) - Car or SUV recommended`;
            } else if (count <= 8) {
                hint.innerHTML = `Medium group (${count} people) - Van or minibus needed`;
            } else if (count <= 15) {
                hint.innerHTML = `Large group (${count} people) - Minibus required`;
            } else if (count <= 50) {
                hint.innerHTML = `Very large group (${count} people) - Full-size bus required`;
            } else {
                hint.innerHTML = 'Group too large - please contact transport department directly';
            }

            // Update passenger name fields
            updatePassengerNameFields(count);
        }

        // Create dynamic passenger name input fields
        function updatePassengerNameFields(count) {
            const container = document.getElementById('passengerNameContainer');
            const currentCount = parseInt(count) || 0;

            // Clear existing fields
            container.innerHTML = '';

            if (currentCount > 0) {
                const existingNames = document.getElementById('passenger_names_hidden').value.split('\n').filter(name => name.trim());

                // Determine column layout based on passenger count
                let colSize, fieldsPerRow;
                if (currentCount <= 2) {
                    colSize = '12'; fieldsPerRow = 1;
                } else if (currentCount <= 4) {
                    colSize = '6'; fieldsPerRow = 2;
                } else if (currentCount <= 6) {
                    colSize = '4'; fieldsPerRow = 3;
                } else {
                    colSize = '3'; fieldsPerRow = 4;
                }

                // Create rows and fields
                let html = '';
                for (let i = 1; i <= currentCount; i++) {
                    const existingName = existingNames[i-1] ? existingNames[i-1].replace(/^\d+\.\s*/, '').trim() : '';

                    // Start new row
                    if ((i - 1) % fieldsPerRow === 0) {
                        html += '<div class="row">';
                    }

                    // Add field
                    html += `
                        <div class="col-md-${colSize} mb-3">
                            <label class="form-label small">
                                <i class="fas fa-user me-1"></i>Passenger ${i}
                            </label>
                            <input type="text"
                                   class="form-control passenger-name-input"
                                   id="passenger_${i}"
                                   placeholder="Enter passenger ${i} name"
                                   value="${existingName}"
                                   required>
                        </div>
                    `;

                    // Close row
                    if (i % fieldsPerRow === 0 || i === currentCount) {
                        html += '</div>';
                    }
                }

                container.innerHTML = html;

                // Add event listeners to all passenger name inputs
                const inputs = container.querySelectorAll('.passenger-name-input');
                inputs.forEach((input, index) => {
                    input.addEventListener('input', updatePassengerNamesTextarea);
                    input.addEventListener('blur', updatePassengerNamesTextarea);

                    // Add Enter key navigation to next field
                    input.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            const nextIndex = index + 1;
                            if (nextIndex < inputs.length) {
                                inputs[nextIndex].focus();
                                inputs[nextIndex].select();
                            } else {
                                // If last field, move focus to special requirements field
                                const specialReqField = document.querySelector('textarea[name="special_requirements"]');
                                if (specialReqField) {
                                    specialReqField.focus();
                                }
                            }
                        }
                    });

                    // Add automatic focus to first empty field
                    if (index === 0 && !input.value.trim()) {
                        setTimeout(() => input.focus(), 100);
                    }
                });

                // Initial update of the hidden textarea
                updatePassengerNamesTextarea();
            }
        }

        // Update the hidden textarea with formatted passenger names
        function updatePassengerNamesTextarea() {
            const inputs = document.querySelectorAll('.passenger-name-input');
            const names = [];

            inputs.forEach((input, index) => {
                if (input.value.trim()) {
                    names.push(`${index + 1}. ${input.value.trim()}`);
                }
            });

            document.getElementById('passenger_names_hidden').value = names.join('\n');
        }

        // Combine departure time dropdowns
        function updateDepartureTime() {
            const hour = document.querySelector('select[name="departure_hour"]').value;
            const minute = document.querySelector('select[name="departure_minute"]').value;
            const ampm = document.querySelector('select[name="departure_ampm"]').value;

            if (hour && minute !== '' && ampm) {
                let hour24 = parseInt(hour);
                if (ampm === 'PM' && hour24 !== 12) {
                    hour24 += 12;
                } else if (ampm === 'AM' && hour24 === 12) {
                    hour24 = 0;
                }
                const timeString = String(hour24).padStart(2, '0') + ':' + String(minute).padStart(2, '0');
                document.getElementById('departure_time_hidden').value = timeString;
            }
        }

        // Combine return time dropdowns
        function updateReturnTime() {
            const hour = document.querySelector('select[name="return_hour"]').value;
            const minute = document.querySelector('select[name="return_minute"]').value;
            const ampm = document.querySelector('select[name="return_ampm"]').value;

            if (hour && minute !== '' && ampm) {
                let hour24 = parseInt(hour);
                if (ampm === 'PM' && hour24 !== 12) {
                    hour24 += 12;
                } else if (ampm === 'AM' && hour24 === 12) {
                    hour24 = 0;
                }
                const timeString = String(hour24).padStart(2, '0') + ':' + String(minute).padStart(2, '0');
                document.getElementById('return_time_hidden').value = timeString;
            } else {
                document.getElementById('return_time_hidden').value = '';
            }
        }

        // Add event listeners for time dropdowns
        document.addEventListener('DOMContentLoaded', function() {
            // Departure time listeners
            document.querySelector('select[name="departure_hour"]').addEventListener('change', updateDepartureTime);
            document.querySelector('select[name="departure_minute"]').addEventListener('change', updateDepartureTime);
            document.querySelector('select[name="departure_ampm"]').addEventListener('change', updateDepartureTime);

            // Return time listeners
            document.querySelector('select[name="return_hour"]').addEventListener('change', updateReturnTime);
            document.querySelector('select[name="return_minute"]').addEventListener('change', updateReturnTime);
            document.querySelector('select[name="return_ampm"]').addEventListener('change', updateReturnTime);

            // Passenger count listener
            document.querySelector('input[name="passenger_count"]').addEventListener('change', function() {
                updatePassengerHint(this.value);
            });

            // Initialize time values and passenger fields on page load
            updateDepartureTime();
            updateReturnTime();

            // Initialize passenger name fields based on current count
            const currentCount = document.querySelector('input[name="passenger_count"]').value;
            if (currentCount) {
                updatePassengerHint(currentCount);
            }
        });

        function resetForm() {
            if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
                document.getElementById('transportForm').reset();
                document.getElementById('returnDetails').style.display = 'none';
            }
        }

        function cancelRequest() {
            new bootstrap.Modal(document.getElementById('cancelModal')).show();
        }

        // Form validation
        document.getElementById('transportForm').addEventListener('submit', function(e) {
            const requestType = document.querySelector('select[name="request_type"]').value;
            const departureDate = document.querySelector('input[name="departure_date"]').value;
            const returnDate = document.querySelector('input[name="return_date"]');
            const returnTime = document.querySelector('input[name="return_time"]');

            // Clear empty date/time values to prevent SQL errors
            if (returnDate && returnDate.value === '') {
                returnDate.removeAttribute('name');
            }
            if (returnTime && returnTime.value === '') {
                returnTime.removeAttribute('name');
            }

            // Validate return date logic
            if ((requestType === 'round_trip' || requestType === 'multi_destination') && returnDate.value) {
                if (new Date(returnDate.value) < new Date(departureDate)) {
                    e.preventDefault();
                    alert('Return date cannot be before departure date.');
                    return false;
                }
            }
        });
    </script>
</body>
</html>