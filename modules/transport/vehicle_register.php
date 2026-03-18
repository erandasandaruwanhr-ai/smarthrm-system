<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

// Get user location and account type for access control
$account_type = $user['account_type'] ?? 'user';
$user_location = $user['location'] ?? $user['location_name'] ?? null;

$message = '';
$vehicle_id = $_GET['id'] ?? null;
$vehicle_data = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $vehicle_number = trim($_POST['vehicle_number']);
        $vehicle_type = trim($_POST['vehicle_type']);
        $brand = trim($_POST['brand']);
        $model = trim($_POST['model']);
        $year = trim($_POST['year']);
        $color = trim($_POST['color']);
        $fuel_type = trim($_POST['fuel_type']);
        $seating_capacity = intval($_POST['seating_capacity']);
        $status = $_POST['status'];
        $location = trim($_POST['location']);
        $insurance_expiry = !empty($_POST['insurance_expiry']) ? $_POST['insurance_expiry'] : null;
        $license_expiry = !empty($_POST['license_expiry']) ? $_POST['license_expiry'] : null;
        $notes = trim($_POST['notes']);

        try {
            if ($action === 'add') {
                $query = "INSERT INTO vehicles (vehicle_number, vehicle_type, brand, model, year, color,
                         fuel_type, seating_capacity, status, location, insurance_expiry, license_expiry,
                         notes, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

                $db->execute($query, [
                    $vehicle_number, $vehicle_type, $brand, $model, $year, $color,
                    $fuel_type, $seating_capacity, $status, $location, $insurance_expiry,
                    $license_expiry, $notes, $user['id']
                ]);

                $message = "Vehicle added successfully!";
            } else {
                $query = "UPDATE vehicles SET vehicle_number = ?, vehicle_type = ?, brand = ?,
                         model = ?, year = ?, color = ?, fuel_type = ?, seating_capacity = ?,
                         status = ?, location = ?, insurance_expiry = ?, license_expiry = ?,
                         notes = ?, updated_at = NOW() WHERE id = ?";

                $db->execute($query, [
                    $vehicle_number, $vehicle_type, $brand, $model, $year, $color,
                    $fuel_type, $seating_capacity, $status, $location, $insurance_expiry,
                    $license_expiry, $notes, $vehicle_id
                ]);

                $message = "Vehicle updated successfully!";

            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    } elseif ($action === 'delete' && $vehicle_id) {
        try {
            $delete_condition = "id = ?";
            $delete_params = [$vehicle_id];

            // Admin users can only delete vehicles from their location
            if ($account_type === 'admin' && $user_location) {
                $delete_condition .= " AND location = ?";
                $delete_params[] = $user_location;
            }

            $deleted_rows = $db->execute("DELETE FROM vehicles WHERE " . $delete_condition, $delete_params);

            if ($deleted_rows > 0) {
                $message = "Vehicle deleted successfully!";
            } else {
                $message = "Error: Vehicle not found or access denied.";
            }
            $vehicle_id = null;
        } catch (Exception $e) {
            $message = "Error deleting vehicle: " . $e->getMessage();
        }
    }
}

// Get vehicle data for editing with location restriction for admin users
if ($vehicle_id) {
    $edit_condition = "id = ?";
    $edit_params = [$vehicle_id];

    if ($account_type === 'admin' && $user_location) {
        $edit_condition .= " AND location = ?";
        $edit_params[] = $user_location;
    }

    $vehicle_data = $db->fetch("SELECT * FROM vehicles WHERE " . $edit_condition, $edit_params);
}

// Apply location filtering for admin users
$location_condition = '';
$params = [];
if ($account_type === 'admin' && $user_location) {
    $location_condition = ' WHERE location = ?';
    $params = [$user_location];
}

// Get vehicles based on user access level
$query = "SELECT * FROM vehicles" . $location_condition . " ORDER BY vehicle_number";
$vehicles = $db->fetchAll($query, $params);

// Vehicle types and locations
$vehicle_types = ['Car', 'Van', 'Bus', 'Truck', 'Motorcycle'];
$fuel_types = ['Petrol', 'Diesel', 'Hybrid', 'Electric'];
$locations = ['7C', 'Pannala', 'Kobeigane', 'JECOE', 'Head Office'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Vehicle Register</title>
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

        .breadcrumb {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
        }

        /* Enhanced Form Styling */
        .form-control, .form-select {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
            font-weight: 500;
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

        .btn-secondary {
            background: linear-gradient(135deg, var(--secondary-color), #545b62);
            border: none;
        }

        .btn-outline-primary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-outline-danger {
            border: 2px solid var(--danger-color);
            color: var(--danger-color);
        }

        .btn-outline-danger:hover {
            background: var(--danger-color);
            border-color: var(--danger-color);
        }

        /* Enhanced Table Styling */
        .table {
            border-radius: 12px;
            overflow: hidden;
            background: white;
        }

        .table thead th {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            color: #2c3e50;
            font-weight: 600;
            border: none;
            padding: 1rem 0.75rem;
            position: relative;
        }

        .table thead th::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--primary-color), var(--info-color));
        }

        .table tbody tr {
            transition: all 0.3s ease;
        }

        .table tbody tr:hover {
            background: linear-gradient(135deg, rgba(0, 123, 255, 0.05), rgba(23, 162, 184, 0.05));
            transform: translateX(5px);
        }

        .table td {
            padding: 1rem 0.75rem;
            border-color: #f8f9fa;
            vertical-align: middle;
        }

        /* Enhanced Status Badges */
        .badge {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge.bg-success {
            background: linear-gradient(135deg, #28a745, #20c997) !important;
            box-shadow: 0 3px 10px rgba(40, 167, 69, 0.3);
        }

        .badge.bg-warning {
            background: linear-gradient(135deg, #ffc107, #fd7e14) !important;
            color: #212529 !important;
            box-shadow: 0 3px 10px rgba(255, 193, 7, 0.3);
        }

        .badge.bg-info {
            background: linear-gradient(135deg, #17a2b8, #138496) !important;
            box-shadow: 0 3px 10px rgba(23, 162, 184, 0.3);
        }

        .badge.bg-danger {
            background: linear-gradient(135deg, #dc3545, #c82333) !important;
            box-shadow: 0 3px 10px rgba(220, 53, 69, 0.3);
        }

        /* Enhanced Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
        }

        .empty-state i {
            color: #dee2e6;
            margin-bottom: 1rem;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .empty-state .text-muted {
            font-size: 1.1rem;
            margin-top: 1rem;
        }

        /* Enhanced Alert Styling */
        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .alert::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
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

        /* Enhanced Modal Styling */
        .modal-content {
            border-radius: 20px;
            border: none;
            overflow: hidden;
        }

        .modal-header {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-bottom: 2px solid #dee2e6;
            position: relative;
        }

        .modal-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--danger-color), #c82333);
        }

        .modal-title {
            color: #2c3e50;
            font-weight: 600;
        }

        /* Loading Animation */
        .loading-spinner {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 9999;
        }

        .spinner-border {
            color: var(--primary-color);
        }

        /* Vehicle Type Icons */
        .vehicle-type-icon {
            display: inline-block;
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 40px;
            margin-right: 10px;
            font-size: 1.2rem;
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
            }

            .btn {
                padding: 0.5rem 1rem;
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
                        <h1><i class="fas fa-car me-3"></i>Vehicle Register</h1>
                        <p>Register and manage company vehicles and fleet information</p>
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
                    <li class="breadcrumb-item active">Vehicle Register</li>
                </ol>
            </nav>

        <?php if ($message): ?>
        <div class="alert alert-<?php echo strpos($message, 'Error') !== false ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

            <div class="row">
                <!-- Vehicle Form -->
                <div class="col-lg-4">
                    <div class="form-card">
                        <div class="card-header-modern">
                            <h5>
                                <i class="fas fa-<?php echo $vehicle_id ? 'edit' : 'plus'; ?> me-2"></i>
                                <?php echo $vehicle_id ? 'Edit Vehicle' : 'Add New Vehicle'; ?>
                            </h5>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="action" value="<?php echo $vehicle_id ? 'edit' : 'add'; ?>">

                            <div class="form-section">
                                <h6><i class="fas fa-id-card me-2"></i>Vehicle Identification</h6>
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-hashtag"></i>Vehicle Number *</label>
                                    <input type="text" class="form-control" name="vehicle_number" placeholder="e.g., ABC-1234"
                                           value="<?php echo htmlspecialchars($vehicle_data['vehicle_number'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-car"></i>Vehicle Type *</label>
                                <select class="form-select" name="vehicle_type" required>
                                    <option value="">🚗 Select Vehicle Type</option>
                                    <?php
                                    $type_icons = [
                                        'Car' => '🚗',
                                        'Van' => '🚐',
                                        'Bus' => '🚌',
                                        'Truck' => '🚛',
                                        'Motorcycle' => '🏍️'
                                    ];
                                    foreach ($vehicle_types as $type): ?>
                                        <option value="<?php echo $type; ?>"
                                                <?php echo ($vehicle_data['vehicle_type'] ?? '') === $type ? 'selected' : ''; ?>>
                                            <?php echo $type_icons[$type] ?? '🚗'; ?> <?php echo $type; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-section">
                                <h6><i class="fas fa-cogs me-2"></i>Vehicle Specifications</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label"><i class="fas fa-industry"></i>Brand</label>
                                            <input type="text" class="form-control" name="brand" placeholder="e.g., Toyota"
                                                   value="<?php echo htmlspecialchars($vehicle_data['brand'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label"><i class="fas fa-car-side"></i>Model</label>
                                            <input type="text" class="form-control" name="model" placeholder="e.g., Camry"
                                                   value="<?php echo htmlspecialchars($vehicle_data['model'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><i class="fas fa-calendar"></i>Year</label>
                                        <input type="number" class="form-control" name="year" min="1900" max="<?php echo date('Y') + 1; ?>" placeholder="<?php echo date('Y'); ?>"
                                               value="<?php echo htmlspecialchars($vehicle_data['year'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><i class="fas fa-palette"></i>Color</label>
                                        <input type="text" class="form-control" name="color" placeholder="e.g., White"
                                               value="<?php echo htmlspecialchars($vehicle_data['color'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-gas-pump"></i>Fuel Type</label>
                                <select class="form-select" name="fuel_type">
                                    <option value="">⛽ Select Fuel Type</option>
                                    <?php
                                    $fuel_icons = [
                                        'Petrol' => '⛽',
                                        'Diesel' => '🚚',
                                        'Hybrid' => '🔋',
                                        'Electric' => '⚡'
                                    ];
                                    foreach ($fuel_types as $fuel): ?>
                                        <option value="<?php echo $fuel; ?>"
                                                <?php echo ($vehicle_data['fuel_type'] ?? '') === $fuel ? 'selected' : ''; ?>>
                                            <?php echo $fuel_icons[$fuel] ?? '⛽'; ?> <?php echo $fuel; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-users"></i>Seating Capacity</label>
                                <input type="number" class="form-control" name="seating_capacity" min="1" max="50" placeholder="Number of seats"
                                       value="<?php echo htmlspecialchars($vehicle_data['seating_capacity'] ?? ''); ?>">
                            </div>

                            <div class="form-section">
                                <h6><i class="fas fa-info-circle me-2"></i>Status & Location</h6>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-signal"></i>Status *</label>
                                <select class="form-select" name="status" required>
                                    <option value="available" <?php echo ($vehicle_data['status'] ?? '') === 'available' ? 'selected' : ''; ?>>🟢 Available</option>
                                    <option value="in_use" <?php echo ($vehicle_data['status'] ?? '') === 'in_use' ? 'selected' : ''; ?>>🟡 In Use</option>
                                    <option value="maintenance" <?php echo ($vehicle_data['status'] ?? '') === 'maintenance' ? 'selected' : ''; ?>>🔧 Maintenance</option>
                                    <option value="out_of_service" <?php echo ($vehicle_data['status'] ?? '') === 'out_of_service' ? 'selected' : ''; ?>>🔴 Out of Service</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-map-marker-alt"></i>Location</label>
                                <select class="form-select" name="location">
                                    <option value="">📍 Select Location</option>
                                    <?php foreach ($locations as $location): ?>
                                        <option value="<?php echo $location; ?>"
                                                <?php echo ($vehicle_data['location'] ?? '') === $location ? 'selected' : ''; ?>>
                                            🏢 <?php echo $location; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-section">
                                <h6><i class="fas fa-shield-alt me-2"></i>Documentation</h6>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><i class="fas fa-shield"></i>Insurance Expiry</label>
                                        <input type="date" class="form-control" name="insurance_expiry"
                                               value="<?php echo htmlspecialchars($vehicle_data['insurance_expiry'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><i class="fas fa-id-card-alt"></i>License Expiry</label>
                                        <input type="date" class="form-control" name="license_expiry"
                                               value="<?php echo htmlspecialchars($vehicle_data['license_expiry'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-sticky-note"></i>Additional Notes</label>
                                <textarea class="form-control" name="notes" rows="3" placeholder="Any additional information about the vehicle..."><?php echo htmlspecialchars($vehicle_data['notes'] ?? ''); ?></textarea>
                            </div>

                            <div class="d-grid gap-2">
                                <?php if (hasModulePermission($db, 'transport.add_vehicle')): ?>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>
                                    <?php echo $vehicle_id ? 'Update Vehicle' : 'Add Vehicle'; ?>
                                </button>
                                <?php endif; ?>
                                <?php if ($vehicle_id): ?>
                                    <a href="vehicle_register.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>Cancel Edit
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Vehicle List -->
                <div class="col-lg-8">
                    <div class="content-card">
                        <div class="card-header-modern">
                            <h5><i class="fas fa-list me-2"></i>Vehicle List</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Vehicle Number</th>
                                        <th>Type</th>
                                        <th>Brand/Model</th>
                                        <th>Status</th>
                                        <th>Location</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($vehicles)): ?>
                                    <tr>
                                        <td colspan="6" class="empty-state">
                                            <i class="fas fa-car fa-3x"></i>
                                            <div class="text-muted">No vehicles registered yet</div>
                                            <small class="text-muted">Add your first vehicle using the form on the left</small>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($vehicles as $vehicle): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="vehicle-type-icon me-2">
                                                        <i class="fas fa-car"></i>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($vehicle['vehicle_number']); ?></strong>
                                                        <?php if ($vehicle['year']): ?>
                                                            <br><small class="text-muted"><?php echo $vehicle['year']; ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                $type_icons = [
                                                    'Car' => 'fa-car',
                                                    'Van' => 'fa-shuttle-van',
                                                    'Bus' => 'fa-bus',
                                                    'Truck' => 'fa-truck',
                                                    'Motorcycle' => 'fa-motorcycle'
                                                ];
                                                $icon = $type_icons[$vehicle['vehicle_type']] ?? 'fa-car';
                                                ?>
                                                <i class="fas <?php echo $icon; ?> me-2 text-primary"></i>
                                                <?php echo htmlspecialchars($vehicle['vehicle_type']); ?>
                                            </td>
                                            <td>
                                                <?php
                                                $brand_model = trim($vehicle['brand'] . ' ' . $vehicle['model']);
                                                echo htmlspecialchars($brand_model ?: 'N/A');
                                                ?>
                                                <?php if ($vehicle['color']): ?>
                                                    <br><small class="text-muted">🎨 <?php echo htmlspecialchars($vehicle['color']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $status_colors = [
                                                    'available' => 'success',
                                                    'in_use' => 'warning',
                                                    'maintenance' => 'info',
                                                    'out_of_service' => 'danger'
                                                ];
                                                $color = $status_colors[$vehicle['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>">
                                                    <?php echo ucwords(str_replace('_', ' ', $vehicle['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($vehicle['location']): ?>
                                                    <i class="fas fa-map-marker-alt me-2 text-info"></i>
                                                    <?php echo htmlspecialchars($vehicle['location']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="?id=<?php echo $vehicle['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-outline-danger" title="Delete"
                                                            onclick="deleteVehicle(<?php echo $vehicle['id']; ?>, '<?php echo htmlspecialchars($vehicle['vehicle_number']); ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete vehicle <strong id="vehicleNumber"></strong>?</p>
                    <p class="text-danger"><small>This action cannot be undone.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <?php if (hasModulePermission($db, 'transport.delete_vehicle')): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="vehicle_id" id="deleteVehicleId">
                        <button type="submit" class="btn btn-danger">Delete Vehicle</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Spinner -->
    <div class="loading-spinner" id="loadingSpinner">
        <div class="spinner-border" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enhanced delete function with confirmation
        function deleteVehicle(vehicleId, vehicleNumber) {
            document.getElementById('vehicleNumber').textContent = vehicleNumber;
            document.getElementById('deleteVehicleId').value = vehicleId;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        // Form validation and enhancement
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const vehicleTypeSelect = document.querySelector('select[name="vehicle_type"]');
            const statusSelect = document.querySelector('select[name="status"]');

            // Dynamic vehicle type icon updates
            if (vehicleTypeSelect) {
                vehicleTypeSelect.addEventListener('change', function() {
                    const selectedType = this.value;
                    const icons = {
                        'Car': 'fa-car',
                        'Van': 'fa-shuttle-van',
                        'Bus': 'fa-bus',
                        'Truck': 'fa-truck',
                        'Motorcycle': 'fa-motorcycle'
                    };

                    // Update form visual feedback
                    if (selectedType && icons[selectedType]) {
                        this.style.background = 'linear-gradient(135deg, #e3f2fd, #bbdefb)';
                        setTimeout(() => {
                            this.style.background = '';
                        }, 500);
                    }
                });
            }

            // Status color preview
            if (statusSelect) {
                statusSelect.addEventListener('change', function() {
                    const status = this.value;
                    const colors = {
                        'available': '#28a745',
                        'in_use': '#ffc107',
                        'maintenance': '#17a2b8',
                        'out_of_service': '#dc3545'
                    };

                    if (colors[status]) {
                        this.style.borderColor = colors[status];
                        this.style.boxShadow = `0 0 0 0.2rem ${colors[status]}25`;
                        setTimeout(() => {
                            this.style.borderColor = '';
                            this.style.boxShadow = '';
                        }, 1000);
                    }
                });
            }

            // Enhanced form submission with loading
            if (form) {
                form.addEventListener('submit', function(e) {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const insuranceDate = this.querySelector('input[name="insurance_expiry"]');
                    const licenseDate = this.querySelector('input[name="license_expiry"]');

                    // Show loading state
                    if (submitBtn) {
                        const originalText = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
                        submitBtn.disabled = true;

                        // Re-enable button after 3 seconds (in case of error)
                        setTimeout(() => {
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                        }, 3000);
                    }

                    // Clear invalid date values to prevent SQL errors
                    if (insuranceDate && insuranceDate.value === '') {
                        insuranceDate.removeAttribute('value');
                    }
                    if (licenseDate && licenseDate.value === '') {
                        licenseDate.removeAttribute('value');
                    }
                });
            }

            // Real-time form validation feedback
            const requiredInputs = document.querySelectorAll('input[required], select[required]');
            requiredInputs.forEach(input => {
                input.addEventListener('blur', function() {
                    if (this.value.trim() === '') {
                        this.classList.add('is-invalid');
                        this.style.borderColor = '#dc3545';
                    } else {
                        this.classList.remove('is-invalid');
                        this.style.borderColor = '#28a745';
                        setTimeout(() => {
                            this.style.borderColor = '';
                        }, 1000);
                    }
                });

                input.addEventListener('input', function() {
                    if (this.classList.contains('is-invalid') && this.value.trim() !== '') {
                        this.classList.remove('is-invalid');
                        this.style.borderColor = '#28a745';
                        setTimeout(() => {
                            this.style.borderColor = '';
                        }, 1000);
                    }
                });
            });

            // Auto-format vehicle number input
            const vehicleNumberInput = document.querySelector('input[name="vehicle_number"]');
            if (vehicleNumberInput) {
                vehicleNumberInput.addEventListener('input', function() {
                    let value = this.value.toUpperCase().replace(/[^A-Z0-9-]/g, '');
                    this.value = value;
                });
            }
        });

        // Enhanced table row animations
        document.querySelectorAll('.table tbody tr').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.transform = 'translateX(5px)';
                this.style.background = 'linear-gradient(135deg, rgba(0, 123, 255, 0.05), rgba(23, 162, 184, 0.05))';
            });

            row.addEventListener('mouseleave', function() {
                this.style.transform = '';
                this.style.background = '';
            });
        });

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    if (alert && alert.classList.contains('show')) {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }
                }, 5000);
            });
        });
    </script>
</body>
</html>