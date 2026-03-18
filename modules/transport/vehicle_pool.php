<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

// Check if user has access to this page
if (!isSuperAdmin() && !hasModulePermission($db, 'transport.vehicle_pool')) {
    header('Location: ../../dashboard.php');
    exit;
}

// Get user location and account type for access control
$account_type = $user['account_type'] ?? 'user';
$user_location = $user['location'] ?? $user['location_name'] ?? null;

$message = '';
$view_type = $_GET['view'] ?? 'vehicles';

// Handle service provider form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_service_provider') {
    $company_name = trim($_POST['company_name']);
    $contact_person = trim($_POST['contact_person']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $locations = $_POST['locations'] ?? [];
    $vehicle_types = trim($_POST['vehicle_types']);
    $seating_capacity = intval($_POST['seating_capacity']);
    $notes = trim($_POST['notes']);

    if (empty($locations)) {
        $message = "Error: Please select at least one service location.";
    } else {
        // Generate vendor code automatically
        $count_query = "SELECT COUNT(*) as count FROM service_providers";
        $result = $db->fetch($count_query);
        $next_number = $result['count'] + 1;
        $vendor_code = 'SP' . str_pad($next_number, 3, '0', STR_PAD_LEFT);

        // Process locations
        $locations_str = implode(', ', $locations);

        try {
            $query = "INSERT INTO service_providers (vendor_code, company_name, contact_person,
                     phone, email, locations, vehicle_types, seating_capacity, notes, status, created_by, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NULL, NOW())";
            $db->execute($query, [$vendor_code, $company_name, $contact_person,
                                 $phone, $email, $locations_str, $vehicle_types, $seating_capacity, $notes]);
            $message = "Service provider $company_name added successfully (Code: $vendor_code)!";
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    }
}

// Handle service provider edit submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_service_provider') {
    $sp_id = intval($_POST['sp_id']);
    $company_name = trim($_POST['company_name']);
    $contact_person = trim($_POST['contact_person']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $locations = $_POST['locations'] ?? [];
    $vehicle_types = trim($_POST['vehicle_types']);
    $seating_capacity = intval($_POST['seating_capacity']);
    $notes = trim($_POST['notes']);

    if (empty($locations)) {
        $message = "Error: Please select at least one service location.";
    } else {
        // Process locations
        $locations_str = implode(', ', $locations);

        try {
            $query = "UPDATE service_providers SET
                     company_name = ?, contact_person = ?, phone = ?, email = ?,
                     locations = ?, vehicle_types = ?, seating_capacity = ?, notes = ?
                     WHERE id = ?";
            $db->execute($query, [$company_name, $contact_person, $phone, $email,
                                 $locations_str, $vehicle_types, $seating_capacity, $notes, $sp_id]);
            $message = "Service provider $company_name updated successfully!";
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    }
}

// Handle AJAX requests for getting service provider data
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_service_provider') {
    header('Content-Type: application/json');
    $sp_id = intval($_GET['id']);

    try {
        $sp = $db->fetch("SELECT * FROM service_providers WHERE id = ?", [$sp_id]);
        if ($sp) {
            echo json_encode(['success' => true, 'data' => $sp]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Service provider not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle AJAX requests for status toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    $vehicle_id = intval($_POST['vehicle_id']);
    $new_status = $_POST['status'];

    // Validate status
    $allowed_statuses = ['available', 'in_use', 'maintenance', 'out_of_service'];
    if (!in_array($new_status, $allowed_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }

    try {
        // Admin users can only update vehicles from their location
        $update_condition = "id = ?";
        $update_params = [$new_status, $vehicle_id];

        if ($account_type === 'admin' && $user_location) {
            $update_condition .= " AND location = ?";
            $update_params[] = $user_location;
        }

        $query = "UPDATE vehicles SET status = ?, updated_at = NOW() WHERE " . $update_condition;
        $affected_rows = $db->execute($query, $update_params);

        if ($affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Vehicle status updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Vehicle not found or access denied']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle bulk status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $vehicle_ids = $_POST['vehicle_ids'] ?? [];
    $bulk_status = $_POST['bulk_status'] ?? '';

    if (!empty($vehicle_ids) && !empty($bulk_status)) {
        try {
            $placeholders = str_repeat('?,', count($vehicle_ids) - 1) . '?';
            $bulk_condition = "id IN ($placeholders)";
            $params = array_merge([$bulk_status], $vehicle_ids);

            // Admin users can only update vehicles from their location
            if ($account_type === 'admin' && $user_location) {
                $bulk_condition .= " AND location = ?";
                $params[] = $user_location;
            }

            $query = "UPDATE vehicles SET status = ?, updated_at = NOW() WHERE " . $bulk_condition;
            $affected_rows = $db->execute($query, $params);

            if ($affected_rows > 0) {
                $message = "Selected vehicles status updated successfully!";
            } else {
                $message = "No vehicles updated. Check if vehicles exist or access permissions.";
            }
        } catch (Exception $e) {
            $message = "Error updating vehicles: " . $e->getMessage();
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$location_filter = $_GET['location'] ?? 'all';
$type_filter = $_GET['type'] ?? 'all';

// Build query with filters and admin location restrictions
$where_conditions = [];
$params = [];

// Apply admin location restriction first
if ($account_type === 'admin' && $user_location) {
    $where_conditions[] = 'location = ?';
    $params[] = $user_location;
}

if ($status_filter !== 'all') {
    $where_conditions[] = 'status = ?';
    $params[] = $status_filter;
}

if ($location_filter !== 'all') {
    $where_conditions[] = 'location = ?';
    $params[] = $location_filter;
}

if ($type_filter !== 'all') {
    $where_conditions[] = 'vehicle_type = ?';
    $params[] = $type_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get vehicles or service providers based on view type
if ($view_type === 'providers') {
    // Build service provider specific filters
    $sp_where_conditions = [];
    $sp_params = [];

    // Admin users can only see service providers that serve their location
    if ($account_type === 'admin' && $user_location) {
        $sp_where_conditions[] = 'locations LIKE ?';
        $sp_params[] = "%{$user_location}%";
    }

    if ($location_filter !== 'all') {
        $sp_where_conditions[] = 'locations LIKE ?';
        $sp_params[] = "%{$location_filter}%";
    }

    $sp_where_clause = !empty($sp_where_conditions) ? 'WHERE ' . implode(' AND ', $sp_where_conditions) : '';

    // Get service providers with filters
    $vehicles = $db->fetchAll("SELECT * FROM service_providers $sp_where_clause ORDER BY company_name", $sp_params);

    // Get locations once for all service providers
    $all_locations = $db->fetchAll("SELECT * FROM locations ORDER BY location_name");

} else {
    // Get vehicles with filters
    $vehicles = $db->fetchAll("SELECT * FROM vehicles $where_clause ORDER BY vehicle_number", $params);
}

// Get filter options with admin location restriction
$location_condition_filter = ($account_type === 'admin' && $user_location) ? "WHERE location = '$user_location'" : "WHERE location IS NOT NULL AND location != ''";
$locations = $db->fetchAll("SELECT DISTINCT location FROM vehicles $location_condition_filter ORDER BY location");

$type_condition_filter = ($account_type === 'admin' && $user_location) ? "WHERE location = '$user_location'" : "";
$vehicle_types = $db->fetchAll("SELECT DISTINCT vehicle_type FROM vehicles $type_condition_filter ORDER BY vehicle_type");

// Get statistics with admin location restriction
$stats_condition = ($account_type === 'admin' && $user_location) ? "WHERE location = '$user_location'" : "";

$stats = [
    'total' => $db->fetch("SELECT COUNT(*) as count FROM vehicles $stats_condition")['count'] ?? 0,
    'available' => $db->fetch("SELECT COUNT(*) as count FROM vehicles $stats_condition " . ($stats_condition ? "AND" : "WHERE") . " status = 'available'")['count'] ?? 0,
    'in_use' => $db->fetch("SELECT COUNT(*) as count FROM vehicles $stats_condition " . ($stats_condition ? "AND" : "WHERE") . " status = 'in_use'")['count'] ?? 0,
    'maintenance' => $db->fetch("SELECT COUNT(*) as count FROM vehicles $stats_condition " . ($stats_condition ? "AND" : "WHERE") . " status = 'maintenance'")['count'] ?? 0,
    'out_of_service' => $db->fetch("SELECT COUNT(*) as count FROM vehicles $stats_condition " . ($stats_condition ? "AND" : "WHERE") . " status = 'out_of_service'")['count'] ?? 0
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Vehicle Pool</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        /* Modern Layout & Base Styles */
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --sidebar-width: 280px;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            padding-left: 0;
            width: calc(100% - var(--sidebar-width));
        }

        .dashboard-content {
            background: #f8f9fa;
            padding: 2rem;
            min-height: calc(100vh - 40px);
            margin-left: 0;
        }

        .page-header {
            background: linear-gradient(135deg, #007bff, #0056b3) !important;
            color: white !important;
            padding: 2rem !important;
            border-radius: 15px !important;
            margin-bottom: 2rem !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
            position: relative !important;
            overflow: hidden !important;
        }

        .page-header::before {
            content: '' !important;
            position: absolute !important;
            top: 0 !important;
            right: 0 !important;
            width: 200px !important;
            height: 200px !important;
            background: rgba(255, 255, 255, 0.1) !important;
            border-radius: 50% !important;
            transform: translate(50px, -50px) !important;
        }

        .page-header h1 {
            font-size: 2.5rem !important;
            font-weight: 700 !important;
            margin-bottom: 0.5rem !important;
            position: relative !important;
            z-index: 2 !important;
            color: white !important;
        }

        .page-header p {
            font-size: 1.1rem !important;
            opacity: 0.9 !important;
            margin: 0 !important;
            position: relative !important;
            z-index: 2 !important;
            color: white !important;
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
            padding: 0.5rem 0;
            margin-bottom: 1rem;
        }

        .breadcrumb a {
            color: #007bff;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            color: #0056b3;
        }

        .breadcrumb-item.active {
            color: #6c757d;
        }

        .content-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            margin-left: 0;
            margin-right: 0;
        }

        .card-header-modern {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            color: #2c3e50;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
            padding: 1rem 1.5rem;
            margin: -1.5rem -1.5rem 1.5rem -1.5rem;
            border-radius: 15px 15px 0 0;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }
            .dashboard-content {
                padding: 1rem !important;
            }
            .page-header h1 {
                font-size: 2rem !important;
            }
        }
    </style>
    <style>
        /* Enhanced Vehicle Cards */
        .vehicle-card {
            background: white;
            border-radius: 20px;
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            border: none;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
            min-height: 400px;
        }

        .vehicle-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #007bff, #17a2b8);
            transition: all 0.3s ease;
        }

        .vehicle-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .vehicle-card:hover::before {
            height: 6px;
            background: linear-gradient(90deg, #0056b3, #138496);
        }

        .vehicle-card.selected {
            border: 2px solid #007bff;
            box-shadow: 0 0 0 4px rgba(0,123,255,0.1);
        }

        /* Enhanced Status Styling */
        .status-available {
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(32, 201, 151, 0.1));
            border-left: 5px solid #28a745;
        }

        .status-in_use {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.1), rgba(253, 126, 20, 0.1));
            border-left: 5px solid #ffc107;
        }

        .status-maintenance {
            background: linear-gradient(135deg, rgba(23, 162, 184, 0.1), rgba(19, 132, 150, 0.1));
            border-left: 5px solid #17a2b8;
        }

        .status-out_of_service {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(200, 35, 51, 0.1));
            border-left: 5px solid #dc3545;
        }

        /* Enhanced Status Toggle Buttons */
        .status-toggle {
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            position: relative;
            overflow: hidden;
        }

        .status-toggle::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .status-toggle:hover::before {
            left: 100%;
        }

        .status-toggle:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }

        .status-toggle.active {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }

        /* Enhanced Statistics Cards */
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.25rem 1rem;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #007bff, #17a2b8);
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.12);
        }

        .stats-card .icon {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.75rem;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .stats-card:hover .icon {
            transform: scale(1.05);
        }

        .stats-card h4 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0.25rem 0;
            color: #2c3e50;
        }

        .stats-card p {
            color: #6c757d;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin: 0;
            font-size: 0.85rem;
        }

        .stats-card .mt-2 {
            margin-top: 0.5rem !important;
        }

        .stats-card .mt-2 small {
            font-size: 0.7rem;
            line-height: 1.2;
        }

        /* Enhanced Filter Card */
        .filter-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border: none;
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .filter-card .card-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            padding: 1.5rem 2rem;
        }

        .filter-card .card-header h5 {
            color: white;
            margin: 0;
            font-weight: 600;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .filter-card .card-body {
            padding: 2rem;
        }

        /* Enhanced Form Controls */
        .form-select, .form-control {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .form-select:focus, .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
            transform: translateY(-2px);
        }

        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
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
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffc107, #fd7e14);
            border: none;
            color: #212529;
        }

        .btn-outline-secondary {
            border: 2px solid #6c757d;
            color: #6c757d;
        }

        /* Enhanced Tabs */
        .nav-tabs {
            border: none;
            margin-bottom: 2rem;
        }

        .nav-tabs .nav-link {
            border: none;
            border-radius: 15px 15px 0 0;
            background: #f8f9fa;
            color: #6c757d;
            font-weight: 600;
            padding: 1rem 1.5rem;
            margin-right: 0.5rem;
            transition: all 0.3s ease;
        }

        .nav-tabs .nav-link:hover {
            background: #e9ecef;
            color: #2c3e50;
            transform: translateY(-2px);
        }

        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            box-shadow: 0 5px 15px rgba(0, 123, 255, 0.3);
        }

        /* Enhanced Modal */
        .modal-content {
            border-radius: 20px;
            border: none;
            overflow: hidden;
        }

        .modal-header {
            border-bottom: none;
            padding: 2rem 2rem 1rem;
        }

        .modal-body {
            padding: 1rem 2rem 2rem;
        }

        .modal-footer {
            border-top: none;
            padding: 1rem 2rem 2rem;
        }

        /* Enhanced Alert */
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

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        }

        .empty-state i {
            color: #dee2e6;
            margin-bottom: 1.5rem;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(1.05); }
            100% { opacity: 1; transform: scale(1); }
        }

        /* Vehicle Type Icons */
        .vehicle-type-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .vehicle-type-icon::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            transition: all 0.3s ease;
            transform: translate(-50%, -50%);
        }

        .vehicle-card:hover .vehicle-type-icon::before {
            width: 100%;
            height: 100%;
        }

        /* Layout Grid Enhancement */
        #vehicleGrid {
            margin: 0 -15px;
        }

        #vehicleGrid .col-xl-3,
        #vehicleGrid .col-lg-4,
        #vehicleGrid .col-md-6 {
            padding: 0 15px;
        }

        /* Responsive Enhancements */
        @media (max-width: 1200px) {
            .vehicle-card {
                min-height: 380px;
            }
        }

        @media (max-width: 768px) {
            .stats-card {
                margin-bottom: 1rem;
            }

            .vehicle-card {
                min-height: 350px;
            }

            .status-toggle {
                font-size: 0.7rem;
                padding: 0.5rem 0.75rem;
            }

            .btn {
                padding: 0.6rem 1.2rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="dashboard-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="header-content">
                        <h1><i class="fas fa-toggle-on me-3"></i>Vehicle Pool</h1>
                        <p>Control vehicle availability with in/out switches and manage fleet status</p>
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
                    <li class="breadcrumb-item active">Vehicle Pool</li>
                </ol>
            </nav>

            <!-- Action Buttons -->
            <div class="content-card no-print mb-3">
                <div class="card-header-modern">
                    <h5><i class="fas fa-tools me-2"></i>Quick Actions</h5>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="vehicle_register.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add Vehicle
                    </a>
                    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#serviceProviderModal">
                        <i class="fas fa-truck me-2"></i>Add Service Provider
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Transport
                    </a>
                </div>
            </div>

        <?php if ($message): ?>
        <div class="alert alert-<?php echo strpos($message, 'Error') !== false ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>


        <?php
        // Get view type parameter
        $view_type = $_GET['view'] ?? 'vehicles';
        ?>

        <!-- View Type Tabs -->
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link <?php echo $view_type === 'vehicles' ? 'active' : ''; ?>"
                   href="?view=vehicles">
                    <i class="fas fa-car me-2"></i>Company Vehicles
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $view_type === 'providers' ? 'active' : ''; ?>"
                   href="?view=providers">
                    <i class="fas fa-truck me-2"></i>Service Providers
                </a>
            </li>
        </ul>

        <!-- Enhanced Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-2-4 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="icon" style="background: linear-gradient(135deg, #007bff, #0056b3); color: white;">
                        <i class="fas fa-car"></i>
                    </div>
                    <h4><?php echo number_format($stats['total']); ?></h4>
                    <p>Total Fleet</p>
                    <div class="mt-2">
                        <small class="text-muted">🚗 Company vehicles</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-2-4 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="icon" style="background: linear-gradient(135deg, #28a745, #20c997); color: white;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h4><?php echo number_format($stats['available']); ?></h4>
                    <p>Available</p>
                    <div class="mt-2">
                        <small class="text-success">✅ Ready for use</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-2-4 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="icon" style="background: linear-gradient(135deg, #ffc107, #fd7e14); color: white;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h4><?php echo number_format($stats['in_use']); ?></h4>
                    <p>In Use</p>
                    <div class="mt-2">
                        <small class="text-warning">⏱️ Currently assigned</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-2-4 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="icon" style="background: linear-gradient(135deg, #17a2b8, #138496); color: white;">
                        <i class="fas fa-tools"></i>
                    </div>
                    <h4><?php echo number_format($stats['maintenance']); ?></h4>
                    <p>Maintenance</p>
                    <div class="mt-2">
                        <small class="text-info">🔧 Under service</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-2-4 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="icon" style="background: linear-gradient(135deg, #dc3545, #c82333); color: white;">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <h4><?php echo number_format($stats['out_of_service']); ?></h4>
                    <p>Out of Service</p>
                    <div class="mt-2">
                        <small class="text-danger">❌ Needs attention</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Filters and Bulk Actions -->
        <div class="filter-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-sliders-h me-2"></i>Smart Filters & Bulk Actions</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-4 align-items-end mb-4">
                    <div class="col-md-3">
                        <label class="form-label"><i class="fas fa-signal me-1"></i>Vehicle Status</label>
                        <select class="form-select" name="status">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>🔍 All Status</option>
                            <option value="available" <?php echo $status_filter === 'available' ? 'selected' : ''; ?>>✅ Available</option>
                            <option value="in_use" <?php echo $status_filter === 'in_use' ? 'selected' : ''; ?>>⏱️ In Use</option>
                            <option value="maintenance" <?php echo $status_filter === 'maintenance' ? 'selected' : ''; ?>>🔧 Maintenance</option>
                            <option value="out_of_service" <?php echo $status_filter === 'out_of_service' ? 'selected' : ''; ?>>❌ Out of Service</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label"><i class="fas fa-map-marker-alt me-1"></i>Location</label>
                        <select class="form-select" name="location">
                            <option value="all" <?php echo $location_filter === 'all' ? 'selected' : ''; ?>>🌍 All Locations</option>
                            <?php foreach ($locations as $location): ?>
                                <option value="<?php echo htmlspecialchars($location['location']); ?>"
                                        <?php echo $location_filter === $location['location'] ? 'selected' : ''; ?>>
                                    🏢 <?php echo htmlspecialchars($location['location']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label"><i class="fas fa-car me-1"></i>Vehicle Type</label>
                        <select class="form-select" name="type">
                            <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>🚗 All Types</option>
                            <?php foreach ($vehicle_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type['vehicle_type']); ?>"
                                        <?php echo $type_filter === $type['vehicle_type'] ? 'selected' : ''; ?>>
                                    <?php
                                    $icons = ['Car' => '🚗', 'Van' => '🚐', 'Bus' => '🚌', 'Truck' => '🚛', 'Motorcycle' => '🏍️'];
                                    echo $icons[$type['vehicle_type']] ?? '🚗';
                                    ?> <?php echo htmlspecialchars($type['vehicle_type']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>Apply Filters
                        </button>
                    </div>
                </form>

                <!-- Enhanced Bulk Actions -->
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-primary" id="selectAll">
                            <i class="fas fa-check-double me-2"></i>Select All
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="clearSelection">
                            <i class="fas fa-times me-2"></i>Clear Selection
                        </button>
                        <span class="badge bg-info align-self-center" id="selectedCount" style="display: none;">0 selected</span>
                    </div>
                    <div id="bulkActions" style="display: none;">
                        <form method="POST" class="d-flex align-items-center gap-2" id="bulkForm">
                            <input type="hidden" name="bulk_action" value="1">
                            <input type="hidden" name="vehicle_ids" id="selectedIds">
                            <select class="form-select" name="bulk_status" required style="min-width: 200px;">
                                <option value="">🔄 Change Status To...</option>
                                <option value="available">✅ Available</option>
                                <option value="in_use">⏱️ In Use</option>
                                <option value="maintenance">🔧 Maintenance</option>
                                <option value="out_of_service">❌ Out of Service</option>
                            </select>
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-magic me-2"></i>Update Selected
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Vehicle Grid -->
        <div class="row" id="vehicleGrid">
            <?php if (empty($vehicles)): ?>
            <div class="col-12">
                <div class="empty-state">
                    <i class="fas fa-car fa-4x"></i>
                    <h4 class="text-muted mt-3">No vehicles found</h4>
                    <p class="text-muted mb-4">Try adjusting your filter criteria or add new vehicles to your fleet</p>
                    <div class="d-flex gap-2 justify-content-center">
                        <a href="vehicle_register.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Add Vehicle
                        </a>
                        <button onclick="window.location.href='?'" class="btn btn-outline-secondary">
                            <i class="fas fa-refresh me-2"></i>Clear Filters
                        </button>
                    </div>
                </div>
            </div>
            <?php else: ?>
                <?php foreach ($vehicles as $vehicle): ?>
                <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
                    <?php if ($view_type === 'providers'): ?>
                        <!-- Service Provider Card -->
                        <div class="card vehicle-card h-100 border-warning">
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <div class="bg-warning bg-opacity-10 text-warning rounded-circle d-inline-flex p-3 mb-2">
                                        <i class="fas fa-truck fa-2x"></i>
                                    </div>
                                    <h5 class="fw-bold"><?php echo htmlspecialchars($vehicle['company_name']); ?></h5>
                                    <p class="text-muted mb-1">
                                        <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($vehicle['vendor_code']); ?></span>
                                    </p>
                                </div>

                                <div class="mb-3">
                                    <small class="text-muted d-block">Contact Person</small>
                                    <strong><?php echo htmlspecialchars($vehicle['contact_person'] ?: 'N/A'); ?></strong>
                                </div>

                                <div class="mb-3">
                                    <small class="text-muted d-block">Phone</small>
                                    <strong><?php echo htmlspecialchars($vehicle['phone'] ?: 'N/A'); ?></strong>
                                </div>

                                <div class="mb-3">
                                    <small class="text-muted d-block">Email</small>
                                    <small><?php echo htmlspecialchars($vehicle['email'] ?: 'N/A'); ?></small>
                                </div>

                                <div class="mb-3">
                                    <small class="text-muted d-block">Service Locations</small>
                                    <div><?php
                                        $locs = explode(',', $vehicle['locations'] ?? '');
                                        foreach ($locs as $loc):
                                            if (trim($loc)): ?>
                                            <span class="badge bg-light text-dark me-1"><?php echo htmlspecialchars(trim($loc)); ?></span>
                                        <?php endif; endforeach; ?>
                                    </div>
                                </div>

                                <?php if ($vehicle['vehicle_types']): ?>
                                <div class="mb-3">
                                    <small class="text-muted d-block">Vehicle Types</small>
                                    <small><?php echo htmlspecialchars($vehicle['vehicle_types']); ?></small>
                                </div>
                                <?php endif; ?>

                                <div class="mb-3">
                                    <small class="text-muted d-block">Seating Capacity</small>
                                    <strong><?php echo $vehicle['seating_capacity'] ?? '8'; ?> passengers</strong>
                                </div>

                                <div class="text-center">
                                    <div id="viewMode<?php echo $vehicle['id']; ?>">
                                        <button class="btn btn-outline-primary btn-sm me-2" onclick="
                                            document.getElementById('viewMode<?php echo $vehicle['id']; ?>').style.display = 'none';
                                            document.getElementById('editMode<?php echo $vehicle['id']; ?>').style.display = 'block';
                                            ">
                                            <i class="fas fa-edit me-1"></i>Edit
                                        </button>
                                    </div>

                                    <div id="editMode<?php echo $vehicle['id']; ?>" style="display: none;" class="border rounded p-3 bg-light">
                                        <form method="POST" style="margin: 0;">
                                            <input type="hidden" name="action" value="edit_service_provider">
                                            <input type="hidden" name="sp_id" value="<?php echo $vehicle['id']; ?>">

                                            <h6 class="text-primary mb-3"><i class="fas fa-edit me-2"></i>Edit <?php echo htmlspecialchars($vehicle['company_name']); ?></h6>

                                            <div class="row">
                                                <div class="col-md-6 mb-2">
                                                    <label class="form-label small mb-1"><strong>Company Name *</strong></label>
                                                    <input type="text" name="company_name"
                                                           value="<?php echo htmlspecialchars($vehicle['company_name'] ?? ''); ?>"
                                                           class="form-control form-control-sm" required>
                                                </div>
                                                <div class="col-md-6 mb-2">
                                                    <label class="form-label small mb-1"><strong>Contact Person *</strong></label>
                                                    <input type="text" name="contact_person"
                                                           value="<?php echo htmlspecialchars($vehicle['contact_person'] ?? ''); ?>"
                                                           class="form-control form-control-sm" required>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6 mb-2">
                                                    <label class="form-label small mb-1"><strong>Phone *</strong></label>
                                                    <input type="tel" name="phone"
                                                           value="<?php echo htmlspecialchars($vehicle['phone'] ?? ''); ?>"
                                                           class="form-control form-control-sm" required>
                                                </div>
                                                <div class="col-md-6 mb-2">
                                                    <label class="form-label small mb-1"><strong>Email</strong></label>
                                                    <input type="email" name="email"
                                                           value="<?php echo htmlspecialchars($vehicle['email'] ?? ''); ?>"
                                                           class="form-control form-control-sm">
                                                </div>
                                            </div>

                                            <div class="mb-2">
                                                <label class="form-label small mb-1"><strong>Service Locations *</strong></label>
                                                <div class="row">
                                                    <?php
                                                    $current_locations = explode(',', $vehicle['locations'] ?? '');
                                                    $current_locations = array_map('trim', $current_locations);
                                                    // Use pre-fetched locations to avoid query in loop
                                                    foreach ($all_locations as $location):
                                                    ?>
                                                    <div class="col-md-4 mb-1">
                                                        <div class="form-check form-check-sm">
                                                            <input class="form-check-input" type="checkbox"
                                                                   name="locations[]" value="<?php echo htmlspecialchars($location['location_name']); ?>"
                                                                   id="editLoc<?php echo $vehicle['id']; ?>_<?php echo $location['id']; ?>"
                                                                   <?php echo in_array($location['location_name'], $current_locations) ? 'checked' : ''; ?>>
                                                            <label class="form-check-label small" for="editLoc<?php echo $vehicle['id']; ?>_<?php echo $location['id']; ?>">
                                                                <?php echo htmlspecialchars($location['location_name']); ?>
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6 mb-2">
                                                    <label class="form-label small mb-1"><strong>Vehicle Types</strong></label>
                                                    <input type="text" name="vehicle_types"
                                                           value="<?php echo htmlspecialchars($vehicle['vehicle_types'] ?? ''); ?>"
                                                           class="form-control form-control-sm"
                                                           placeholder="e.g., Car, Van, Bus, Truck">
                                                </div>
                                                <div class="col-md-6 mb-2">
                                                    <label class="form-label small mb-1"><strong>Seating Capacity *</strong></label>
                                                    <input type="number" name="seating_capacity"
                                                           value="<?php echo $vehicle['seating_capacity'] ?? '8'; ?>"
                                                           class="form-control form-control-sm"
                                                           min="1" max="50" required>
                                                </div>
                                            </div>

                                            <div class="mb-2">
                                                <label class="form-label small mb-1"><strong>Notes</strong></label>
                                                <textarea name="notes" class="form-control form-control-sm" rows="2"
                                                          placeholder="Additional information, pricing details, etc."><?php echo htmlspecialchars($vehicle['notes'] ?? ''); ?></textarea>
                                            </div>

                                            <div class="d-flex gap-2">
                                                <button type="submit" class="btn btn-success btn-sm">
                                                    <i class="fas fa-save me-1"></i>Save
                                                </button>
                                                <button type="button" class="btn btn-secondary btn-sm" onclick="
                                                    document.getElementById('editMode<?php echo $vehicle['id']; ?>').style.display = 'none';
                                                    document.getElementById('viewMode<?php echo $vehicle['id']; ?>').style.display = 'block';
                                                    ">
                                                    <i class="fas fa-times me-1"></i>Cancel
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Company Vehicle Card -->
                        <div class="card vehicle-card h-100 status-<?php echo $vehicle['status']; ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input vehicle-checkbox" type="checkbox"
                                               value="<?php echo $vehicle['id']; ?>" data-vehicle-id="<?php echo $vehicle['id']; ?>">
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button"
                                                data-bs-toggle="dropdown">
                                            <i class="fas fa-cog"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="vehicle_register.php?id=<?php echo $vehicle['id']; ?>">
                                                <i class="fas fa-edit me-2"></i>Edit Details
                                            </a></li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="text-center mb-3">
                                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex p-3 mb-2">
                                        <i class="fas fa-car fa-2x"></i>
                                    </div>
                                    <h5 class="fw-bold"><?php echo htmlspecialchars($vehicle['vehicle_number']); ?></h5>
                                    <p class="text-muted mb-1"><?php echo htmlspecialchars($vehicle['vehicle_type']); ?></p>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars(trim($vehicle['brand'] . ' ' . $vehicle['model']) ?: 'N/A'); ?>
                                    </small>
                                </div>

                                <div class="mb-3">
                                    <div class="row text-center">
                                        <div class="col-6">
                                            <small class="text-muted d-block">Location</small>
                                            <strong><?php echo htmlspecialchars($vehicle['location'] ?: 'N/A'); ?></strong>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Capacity</small>
                                            <strong><?php echo $vehicle['seating_capacity'] ?: 'N/A'; ?></strong>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-center">
                                    <div class="btn-group w-100" role="group">
                                        <button type="button" class="btn btn-sm btn-outline-success status-toggle
                                                <?php echo $vehicle['status'] === 'available' ? 'active' : ''; ?>"
                                                data-vehicle-id="<?php echo $vehicle['id']; ?>" data-status="available">
                                            Available
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-warning status-toggle
                                                <?php echo $vehicle['status'] === 'in_use' ? 'active' : ''; ?>"
                                                data-vehicle-id="<?php echo $vehicle['id']; ?>" data-status="in_use">
                                            In Use
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-info status-toggle
                                                <?php echo $vehicle['status'] === 'maintenance' ? 'active' : ''; ?>"
                                                data-vehicle-id="<?php echo $vehicle['id']; ?>" data-status="maintenance">
                                            Maintenance
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger status-toggle
                                                <?php echo $vehicle['status'] === 'out_of_service' ? 'active' : ''; ?>"
                                                data-vehicle-id="<?php echo $vehicle['id']; ?>" data-status="out_of_service">
                                            Out of Service
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Status toggle functionality
        document.querySelectorAll('.status-toggle').forEach(button => {
            button.addEventListener('click', function() {
                const vehicleId = this.dataset.vehicleId;
                const status = this.dataset.status;

                // Show loading state
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                // Make AJAX request
                fetch('vehicle_pool.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ajax=1&vehicle_id=${vehicleId}&status=${status}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update UI
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                        this.disabled = false;
                        this.innerHTML = status.replace('_', ' ');
                    }
                })
                .catch(error => {
                    alert('Network error occurred');
                    this.disabled = false;
                    this.innerHTML = status.replace('_', ' ');
                });
            });
        });

        // Selection functionality
        const checkboxes = document.querySelectorAll('.vehicle-checkbox');
        const selectAllBtn = document.getElementById('selectAll');
        const clearSelectionBtn = document.getElementById('clearSelection');
        const bulkActions = document.getElementById('bulkActions');

        function updateBulkActions() {
            const selected = document.querySelectorAll('.vehicle-checkbox:checked');
            const selectedCount = document.getElementById('selectedCount');

            if (selected.length > 0) {
                bulkActions.style.display = 'flex';
                selectedCount.style.display = 'inline-block';
                selectedCount.textContent = `${selected.length} selected`;
                const ids = Array.from(selected).map(cb => cb.value);
                document.getElementById('selectedIds').value = JSON.stringify(ids);
            } else {
                bulkActions.style.display = 'none';
                selectedCount.style.display = 'none';
            }
        }

        checkboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                updateBulkActions();
                // Toggle card selection visual
                const card = this.closest('.vehicle-card');
                if (this.checked) {
                    card.classList.add('selected');
                } else {
                    card.classList.remove('selected');
                }
            });
        });

        selectAllBtn.addEventListener('click', function() {
            checkboxes.forEach(cb => {
                cb.checked = true;
                cb.closest('.vehicle-card').classList.add('selected');
            });
            updateBulkActions();
        });

        clearSelectionBtn.addEventListener('click', function() {
            checkboxes.forEach(cb => {
                cb.checked = false;
                cb.closest('.vehicle-card').classList.remove('selected');
            });
            updateBulkActions();
        });
    </script>

    <!-- Service Provider Modal -->
    <div class="modal fade" id="serviceProviderModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="vehicle_pool.php">
                    <input type="hidden" name="action" value="add_service_provider">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title">
                            <i class="fas fa-truck me-2"></i>Register Service Provider
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Service providers are external transport companies that provide vehicles with drivers for your transport needs.
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Vendor Code</label>
                                <input type="text" class="form-control" readonly
                                       placeholder="Auto-generated" style="background-color: #f8f9fa;">
                                <small class="text-muted">Automatically generated (SP001, SP002, etc.)</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Company Name *</label>
                                <input type="text" class="form-control" name="company_name" required
                                       placeholder="e.g., Quick Transport Services">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contact Person *</label>
                                <input type="text" class="form-control" name="contact_person" required
                                       placeholder="Primary contact name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone *</label>
                                <input type="text" class="form-control" name="phone" required
                                       placeholder="Contact number">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email"
                                       placeholder="contact@example.com">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Service Location(s) *</label>
                                <div class="border rounded p-2" style="max-height: 150px; overflow-y: auto;">
                                    <div class="form-check">
                                        <input class="form-check-input location-check" type="checkbox" name="locations[]" value="Head Office" id="locHeadOffice">
                                        <label class="form-check-label" for="locHeadOffice">Head Office</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input location-check" type="checkbox" name="locations[]" value="Pannala" id="locPannala">
                                        <label class="form-check-label" for="locPannala">Pannala Plant</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input location-check" type="checkbox" name="locations[]" value="Kobeigane" id="locKobeigane">
                                        <label class="form-check-label" for="locKobeigane">Kobeigane</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input location-check" type="checkbox" name="locations[]" value="7C" id="loc7C">
                                        <label class="form-check-label" for="loc7C">7C</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input location-check" type="checkbox" name="locations[]" value="JECOE" id="locJECOE">
                                        <label class="form-check-label" for="locJECOE">JECOE</label>
                                    </div>
                                </div>
                                <small class="text-muted">Select all locations where this provider operates</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Available Vehicle Types</label>
                            <input type="text" class="form-control" name="vehicle_types"
                                   placeholder="e.g., Car, Van, Bus, Truck">
                            <small class="text-muted">Types of vehicles this provider can supply</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Maximum Seating Capacity *</label>
                            <input type="number" class="form-control" name="seating_capacity" required
                                   min="1" max="50" value="8"
                                   placeholder="Maximum passengers this provider can handle">
                            <small class="text-muted">Used to calculate available space for ride sharing</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="3"
                                      placeholder="Additional information, pricing details, special conditions, etc."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-plus me-2"></i>Register Service Provider
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Service Provider Modal -->
    <div class="modal fade" id="editServiceProviderModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Edit Service Provider
                    </h5>
                    <button type="button" class="btn-close btn-close-white" onclick="document.getElementById('editServiceProviderModal').style.display='none'; return false;"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="edit_service_provider">
                    <input type="hidden" name="sp_id" id="editSpId">

                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Company Name *</label>
                                    <input type="text" class="form-control" name="company_name" id="editCompanyName" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Contact Person *</label>
                                    <input type="text" class="form-control" name="contact_person" id="editContactPerson" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Phone *</label>
                                    <input type="tel" class="form-control" name="phone" id="editPhone" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" id="editEmail">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Service Locations *</label>
                            <div class="row" id="editLocationsContainer">
                                <?php
                                $locations = $db->fetchAll("SELECT * FROM locations WHERE status = 'active' ORDER BY location_name");
                                foreach ($locations as $location):
                                ?>
                                <div class="col-md-4 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input location-check-edit" type="checkbox"
                                               name="locations[]" value="<?php echo htmlspecialchars($location['location_name']); ?>"
                                               id="editLocation<?php echo $location['id']; ?>">
                                        <label class="form-check-label" for="editLocation<?php echo $location['id']; ?>">
                                            <?php echo htmlspecialchars($location['location_name']); ?>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <small class="text-muted">Select all locations where this provider operates</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Available Vehicle Types</label>
                            <input type="text" class="form-control" name="vehicle_types" id="editVehicleTypes"
                                   placeholder="e.g., Car, Van, Bus, Truck">
                            <small class="text-muted">Types of vehicles this provider can supply</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Maximum Seating Capacity *</label>
                            <input type="number" class="form-control" name="seating_capacity" id="editSeatingCapacity" required
                                   min="1" max="50" value="8"
                                   placeholder="Maximum passengers this provider can handle">
                            <small class="text-muted">Used to calculate available space for ride sharing</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" id="editNotes" rows="3"
                                      placeholder="Additional information, pricing details, special conditions, etc."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('editServiceProviderModal').style.display='none'; return false;">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Service Provider
                        </button>
                    </div>
                </form>
            </div>
        </div>

        </div>
    </div>

    <!-- Simple test script first -->
    <script>
        function editServiceProvider(spId) {
            alert('Working! SP ID: ' + spId);
        }
    </script>

    <script>
        /* Temporarily commenting out complex script
        // Define function immediately - no dependencies
        window.editServiceProvider = function(spId) {
            alert('Function called with SP ID: ' + spId);

            // For now, just show the modal with empty form for testing
            const modal = document.getElementById('editServiceProviderModal');
            if (modal) {
                document.getElementById('editSpId').value = spId;
                document.getElementById('editCompanyName').value = 'Test Company';
                document.getElementById('editContactPerson').value = 'Test Person';
                document.getElementById('editPhone').value = '1234567890';
                document.getElementById('editEmail').value = 'test@test.com';
                document.getElementById('editVehicleTypes').value = 'Van';
                document.getElementById('editSeatingCapacity').value = '10';
                document.getElementById('editNotes').value = 'Test notes';

                // Try to show modal without Bootstrap first
                modal.style.display = 'block';
                modal.classList.add('show');
                modal.style.backgroundColor = 'rgba(0,0,0,0.5)';
                alert('Modal should be showing now');

                // Also try Bootstrap if available
                if (typeof bootstrap !== 'undefined') {
                    const bootstrapModal = new bootstrap.Modal(modal);
                    bootstrapModal.show();
                }
            } else {
                alert('Modal element not found!');
            }
        };

        // Form validation for service provider
        document.getElementById('serviceProviderModal')?.addEventListener('submit', function(e) {
            const form = e.target.querySelector('form');
            const checkedLocations = form.querySelectorAll('.location-check:checked');
            if (checkedLocations.length === 0) {
                e.preventDefault();
                alert('Please select at least one service location.');
                return false;
            }
        });

        // Form validation for edit service provider
        document.getElementById('editServiceProviderModal')?.addEventListener('submit', function(e) {
            const form = e.target.querySelector('form');
            const checkedLocations = form.querySelectorAll('.location-check-edit:checked');

            if (checkedLocations.length === 0) {
                e.preventDefault();
                alert('Please select at least one service location.');
                return false;
            }
        });
        */
    </script>
</body>
</html>