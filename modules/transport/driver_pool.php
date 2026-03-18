<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

// Get user location and account type for access control
$account_type = $user['account_type'] ?? 'user';
$user_location = $user['location'] ?? $user['location_name'] ?? null;

$message = '';
$driver_id = $_GET['id'] ?? null;
$driver_data = null;

// Handle AJAX requests for duty toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    $driver_id = intval($_POST['driver_id']);
    $is_on_duty = intval($_POST['is_on_duty']);

    try {
        // Admin users can only update drivers from their location
        $update_condition = "id = ?";
        $update_params = [$is_on_duty, $driver_id];

        if ($account_type === 'admin' && $user_location) {
            $update_condition .= " AND location = ?";
            $update_params[] = $user_location;
        }

        $query = "UPDATE drivers SET is_on_duty = ?, updated_at = NOW() WHERE " . $update_condition;
        $affected_rows = $db->execute($query, $update_params);

        if ($affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Driver duty status updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Driver not found or access denied']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle driver form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Handle service provider submission
    if ($action === 'add_service_provider') {
        $company_name = trim($_POST['company_name']);
        $contact_person = trim($_POST['contact_person']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $locations = $_POST['locations'] ?? [];
        $notes = trim($_POST['notes']);

        // Validate locations
        if (empty($locations)) {
            $message = "Error: Please select at least one service location.";
        } else {
            // Generate vendor code automatically
            $vendor_code_query = "SELECT COUNT(*) as count FROM drivers WHERE driver_type = 'service_provider'";
            $result = $db->fetch($vendor_code_query);
            $next_number = $result['count'] + 1;
            $vendor_code = 'SP' . str_pad($next_number, 3, '0', STR_PAD_LEFT); // SP001, SP002, etc.

            // Process locations
            $primary_location = $locations[0]; // First selected location as primary
            $service_areas_str = implode(', ', $locations);
            $full_notes = "Service Locations: " . $service_areas_str . "\n" . $notes;

            try {
                // No need to check vendor code uniqueness as it's auto-generated
                // Service providers don't need license numbers since they have their own drivers

                // Proceed with insertion directly
                $query = "INSERT INTO drivers (driver_type, emp_number, emp_name, company_name, contact_person,
                             phone, email, location, service_areas, notes, status, is_on_duty, allows_multiple_trips,
                             license_number, license_type, created_by, created_at)
                             VALUES ('service_provider', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', 1, 1, NULL, NULL, ?, NOW())";
                    $db->execute($query, [$vendor_code, $company_name, $company_name, $contact_person,
                                         $phone, $email, $primary_location, $service_areas_str, $full_notes, $user['id']]);
                $message = "Service provider $company_name added successfully (Code: $vendor_code)!";
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
            }
        } // Close else block
    } elseif ($action === 'add' || $action === 'edit') {
        $emp_number = trim($_POST['emp_number']);
        $emp_name = trim($_POST['emp_name']);
        $license_number = trim($_POST['license_number']);
        $license_type = trim($_POST['license_type']);
        $license_expiry = $_POST['license_expiry'];
        $experience_years = intval($_POST['experience_years']);
        $status = $_POST['status'];
        $location = trim($_POST['location']);
        $phone = trim($_POST['phone']);
        $notes = trim($_POST['notes']);

        try {
            if ($action === 'add') {
                $query = "INSERT INTO drivers (emp_number, emp_name, license_number, license_type,
                         license_expiry, experience_years, status, location, phone, notes, created_by, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

                $db->execute($query, [
                    $emp_number, $emp_name, $license_number, $license_type,
                    $license_expiry, $experience_years, $status, $location, $phone, $notes, $user['id']
                ]);

                $message = "Driver added successfully!";
            } else {
                $query = "UPDATE drivers SET emp_number = ?, emp_name = ?, license_number = ?,
                         license_type = ?, license_expiry = ?, experience_years = ?, status = ?,
                         location = ?, phone = ?, notes = ?, updated_at = NOW() WHERE id = ?";

                $db->execute($query, [
                    $emp_number, $emp_name, $license_number, $license_type,
                    $license_expiry, $experience_years, $status, $location, $phone, $notes, $driver_id
                ]);

                $message = "Driver updated successfully!";
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    } elseif ($action === 'delete' && $driver_id) {
        try {
            $delete_condition = "id = ?";
            $delete_params = [$driver_id];

            // Admin users can only delete drivers from their location
            if ($account_type === 'admin' && $user_location) {
                $delete_condition .= " AND location = ?";
                $delete_params[] = $user_location;
            }

            $affected_rows = $db->execute("DELETE FROM drivers WHERE " . $delete_condition, $delete_params);

            if ($affected_rows > 0) {
                $message = "Driver deleted successfully!";
            } else {
                $message = "Error: Driver not found or access denied.";
            }
            $driver_id = null;
        } catch (Exception $e) {
            $message = "Error deleting driver: " . $e->getMessage();
        }
    }
}

// Get driver data for editing with location restriction for admin users
if ($driver_id) {
    $edit_condition = "id = ?";
    $edit_params = [$driver_id];

    if ($account_type === 'admin' && $user_location) {
        $edit_condition .= " AND location = ?";
        $edit_params[] = $user_location;
    }

    $driver_data = $db->fetch("SELECT * FROM drivers WHERE " . $edit_condition, $edit_params);
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$location_filter = $_GET['location'] ?? 'all';
$view_type = $_GET['view'] ?? 'drivers'; // 'drivers' or 'providers'
$duty_filter = $_GET['duty'] ?? 'all';

// Build query with filters and admin location restrictions
$where_conditions = [];
$params = [];

// Filter by driver type
if ($view_type === 'providers') {
    $where_conditions[] = "driver_type = 'service_provider'";
} else {
    $where_conditions[] = "(driver_type = 'employee' OR driver_type IS NULL)";
}

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

if ($duty_filter !== 'all') {
    $where_conditions[] = 'is_on_duty = ?';
    $params[] = $duty_filter === 'on' ? 1 : 0;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get only regular drivers (service providers are now only in vehicle_pool.php)
$drivers = $db->fetchAll("SELECT * FROM drivers $where_clause ORDER BY emp_name", $params);

// Get filter options with admin location restriction
$location_condition_filter = ($account_type === 'admin' && $user_location) ? "WHERE location = '$user_location'" : "WHERE location IS NOT NULL AND location != ''";
$locations = $db->fetchAll("SELECT DISTINCT location FROM drivers $location_condition_filter ORDER BY location");

// Get statistics with admin location restriction
$stats_condition = ($account_type === 'admin' && $user_location) ? "WHERE location = '$user_location'" : "";

$stats = [
    'total' => $db->fetch("SELECT COUNT(*) as count FROM drivers $stats_condition")['count'] ?? 0,
    'active' => $db->fetch("SELECT COUNT(*) as count FROM drivers $stats_condition " . ($stats_condition ? "AND" : "WHERE") . " status = 'active'")['count'] ?? 0,
    'on_duty' => $db->fetch("SELECT COUNT(*) as count FROM drivers $stats_condition " . ($stats_condition ? "AND" : "WHERE") . " is_on_duty = 1")['count'] ?? 0,
    'available' => $db->fetch("SELECT COUNT(*) as count FROM drivers $stats_condition " . ($stats_condition ? "AND" : "WHERE") . " status = 'active' AND is_on_duty = 1")['count'] ?? 0
];

$license_types = ['Light Vehicle', 'Heavy Vehicle', 'Motorcycle', 'Bus', 'Truck'];
$system_locations = ['7C', 'Pannala', 'Kobeigane', 'JECOE', 'Head Office'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Driver Pool</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        /* Force correct layout for transport sub-module pages */
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
                margin-left: 0;
            }
            .dashboard-content {
                padding: 1rem;
            }
            .page-header h1 {
                font-size: 2rem;
            }
        }

        .duty-toggle {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .duty-toggle:hover {
            transform: scale(1.1);
        }
        .driver-card {
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .driver-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        .driver-card.on-duty {
            border-color: #28a745;
        }
        .driver-card.off-duty {
            border-color: #6c757d;
        }
        .status-active { background-color: #d4edda; }
        .status-inactive { background-color: #f8d7da; }
        .status-suspended { background-color: #fff3cd; }
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
                        <h1><i class="fas fa-user-tie me-3"></i>Driver Pool</h1>
                        <p>Manage driver availability with on/off switches and duty status</p>
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
                    <li class="breadcrumb-item active">Driver Pool</li>
                </ol>
            </nav>

            <!-- Action Buttons -->
            <div class="content-card no-print mb-3">
                <div class="card-header-modern">
                    <h5><i class="fas fa-tools me-2"></i>Quick Actions</h5>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#serviceProviderModal">
                        <i class="fas fa-truck me-2"></i>Add Service Provider
                    </button>
                    <a href="search_employees.php" class="btn btn-success">
                        <i class="fas fa-search me-2"></i>Search EPF & Add Drivers
                    </a>
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

        <!-- View Type Tabs -->
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link <?php echo $view_type === 'drivers' ? 'active' : ''; ?>"
                   href="?view=drivers">
                    <i class="fas fa-user-tie me-2"></i>Company Drivers
                </a>
            </li>
        </ul>

        <!-- Enhanced Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <div class="icon" style="background: linear-gradient(135deg, #007bff, #0056b3); color: white;">
                        <i class="fas fa-users"></i>
                    </div>
                    <h4><?php echo number_format($stats['total']); ?></h4>
                    <p>Total Drivers</p>
                    <div class="mt-2">
                        <small class="text-muted">🚗 All registered drivers</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <div class="icon" style="background: linear-gradient(135deg, #28a745, #20c997); color: white;">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <h4><?php echo number_format($stats['active']); ?></h4>
                    <p>Active Drivers</p>
                    <div class="mt-2">
                        <small class="text-success">✅ Ready to drive</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <div class="icon" style="background: linear-gradient(135deg, #ffc107, #fd7e14); color: white;">
                        <i class="fas fa-toggle-on"></i>
                    </div>
                    <h4><?php echo number_format($stats['on_duty']); ?></h4>
                    <p>On Duty</p>
                    <div class="mt-2">
                        <small class="text-warning">🟢 Currently available</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <div class="icon" style="background: linear-gradient(135deg, #17a2b8, #138496); color: white;">
                        <i class="fas fa-car-side"></i>
                    </div>
                    <h4><?php echo number_format($stats['available']); ?></h4>
                    <p>Available</p>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Driver Form -->
            <div class="col-lg-4">
                <div class="card border-0 shadow mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-<?php echo $driver_id ? 'edit' : 'plus'; ?> me-2"></i>
                            <?php echo $driver_id ? 'Edit Driver' : 'Add New Driver'; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="<?php echo $driver_id ? 'edit' : 'add'; ?>">

                            <div class="mb-3">
                                <label class="form-label">Employee Number *</label>
                                <input type="text" class="form-control" name="emp_number"
                                       value="<?php echo htmlspecialchars($driver_data['emp_number'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Driver Name *</label>
                                <input type="text" class="form-control" name="emp_name"
                                       value="<?php echo htmlspecialchars($driver_data['emp_name'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">License Number *</label>
                                <input type="text" class="form-control" name="license_number"
                                       value="<?php echo htmlspecialchars($driver_data['license_number'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">License Type</label>
                                <select class="form-select" name="license_type">
                                    <option value="">Select Type</option>
                                    <?php foreach ($license_types as $type): ?>
                                        <option value="<?php echo $type; ?>"
                                                <?php echo ($driver_data['license_type'] ?? '') === $type ? 'selected' : ''; ?>>
                                            <?php echo $type; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">License Expiry</label>
                                <input type="date" class="form-control" name="license_expiry"
                                       value="<?php echo htmlspecialchars($driver_data['license_expiry'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Experience (Years)</label>
                                <input type="number" class="form-control" name="experience_years" min="0" max="50"
                                       value="<?php echo htmlspecialchars($driver_data['experience_years'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Status *</label>
                                <select class="form-select" name="status" required>
                                    <option value="active" <?php echo ($driver_data['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo ($driver_data['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="suspended" <?php echo ($driver_data['status'] ?? '') === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Location</label>
                                <select class="form-select" name="location">
                                    <option value="">Select Location</option>
                                    <?php foreach ($system_locations as $location): ?>
                                        <option value="<?php echo $location; ?>"
                                                <?php echo ($driver_data['location'] ?? '') === $location ? 'selected' : ''; ?>>
                                            <?php echo $location; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone"
                                       value="<?php echo htmlspecialchars($driver_data['phone'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars($driver_data['notes'] ?? ''); ?></textarea>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>
                                    <?php echo $driver_id ? 'Update Driver' : 'Add Driver'; ?>
                                </button>
                                <?php if ($driver_id): ?>
                                    <a href="driver_pool.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>Cancel Edit
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card border-0 shadow">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Location</label>
                                <select class="form-select" name="location">
                                    <option value="all" <?php echo $location_filter === 'all' ? 'selected' : ''; ?>>All Locations</option>
                                    <?php foreach ($locations as $location): ?>
                                        <option value="<?php echo htmlspecialchars($location['location']); ?>"
                                                <?php echo $location_filter === $location['location'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($location['location']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Duty Status</label>
                                <select class="form-select" name="duty">
                                    <option value="all" <?php echo $duty_filter === 'all' ? 'selected' : ''; ?>>All</option>
                                    <option value="on" <?php echo $duty_filter === 'on' ? 'selected' : ''; ?>>On Duty</option>
                                    <option value="off" <?php echo $duty_filter === 'off' ? 'selected' : ''; ?>>Off Duty</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-dark w-100">
                                <i class="fas fa-search me-2"></i>Filter
                            </button>
                        </form>
                    </div>
                </div>

            <!-- Driver Grid -->
            <div class="col-lg-8">
                <div class="row" id="driverGrid">
                    <?php if (empty($drivers)): ?>
                    <div class="col-12">
                        <div class="card border-0 shadow">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-user-tie fa-4x text-muted mb-3"></i>
                                <h4 class="text-muted">No drivers found</h4>
                                <p class="text-muted">Try adjusting your filter criteria or add new drivers</p>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                        <?php foreach ($drivers as $driver): ?>
                        <div class="col-lg-6 col-md-6 mb-4">
                            <div class="card driver-card h-100 status-<?php echo $driver['status']; ?> <?php echo $driver['is_on_duty'] ? 'on-duty' : 'off-duty'; ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div class="flex-grow-1">
                                            <h5 class="fw-bold mb-1">
                                                <?php if ($driver['driver_type'] === 'service_provider'): ?>
                                                    <i class="fas fa-truck text-warning me-1"></i>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($driver['emp_name']); ?>
                                            </h5>
                                            <small class="text-muted">
                                                <?php if ($driver['driver_type'] === 'service_provider'): ?>
                                                    Vendor: <?php echo htmlspecialchars($driver['emp_number']); ?>
                                                <?php else: ?>
                                                    EPF: <?php echo htmlspecialchars($driver['emp_number']); ?>
                                                <?php endif; ?>
                                            </small>
                                            <?php if ($driver['driver_type'] === 'service_provider' && $driver['contact_person']): ?>
                                                <br><small class="text-info">Contact: <?php echo htmlspecialchars($driver['contact_person']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button"
                                                    data-bs-toggle="dropdown">
                                                <i class="fas fa-cog"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="?id=<?php echo $driver['id']; ?>">
                                                    <i class="fas fa-edit me-2"></i>Edit Details
                                                </a></li>
                                                <li><button class="dropdown-item text-danger" onclick="deleteDriver(<?php echo $driver['id']; ?>, '<?php echo htmlspecialchars($driver['emp_name']); ?>')">
                                                    <i class="fas fa-trash me-2"></i>Delete
                                                </button></li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="row text-center">
                                            <div class="col-6">
                                                <small class="text-muted d-block">License</small>
                                                <strong><?php echo htmlspecialchars($driver['license_type'] ?: 'N/A'); ?></strong>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted d-block">Experience</small>
                                                <strong><?php echo $driver['experience_years'] ?? 0; ?> years</strong>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="row text-center">
                                            <div class="col-6">
                                                <small class="text-muted d-block">Location</small>
                                                <strong><?php echo htmlspecialchars($driver['location'] ?: 'N/A'); ?></strong>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted d-block">Phone</small>
                                                <strong><?php echo htmlspecialchars($driver['phone'] ?: 'N/A'); ?></strong>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-center">
                                        <div class="btn-group w-100" role="group">
                                            <button type="button" class="btn btn-sm <?php echo $driver['is_on_duty'] ? 'btn-success' : 'btn-outline-success'; ?> duty-toggle"
                                                    data-driver-id="<?php echo $driver['id']; ?>" data-duty="1"
                                                    <?php echo $driver['status'] !== 'active' ? 'disabled' : ''; ?>>
                                                <i class="fas fa-toggle-on me-1"></i>On Duty
                                            </button>
                                            <button type="button" class="btn btn-sm <?php echo !$driver['is_on_duty'] ? 'btn-secondary' : 'btn-outline-secondary'; ?> duty-toggle"
                                                    data-driver-id="<?php echo $driver['id']; ?>" data-duty="0"
                                                    <?php echo $driver['status'] !== 'active' ? 'disabled' : ''; ?>>
                                                <i class="fas fa-toggle-off me-1"></i>Off Duty
                                            </button>
                                        </div>

                                        <?php if ($driver['status'] !== 'active'): ?>
                                        <small class="text-muted d-block mt-2">
                                            Status: <?php echo ucfirst($driver['status']); ?>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
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
                    <p>Are you sure you want to delete driver <strong id="driverName"></strong>?</p>
                    <p class="text-danger"><small>This action cannot be undone.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="driver_id" id="deleteDriverId">
                        <button type="submit" class="btn btn-danger">Delete Driver</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Service Provider Modal -->
    <div class="modal fade" id="serviceProviderModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">
                        <i class="fas fa-truck me-2"></i>Add Transport Service Provider
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_service_provider">

                        <div class="alert alert-info mb-4">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Service Provider Information:</strong><br>
                            Service providers are external transport companies that can handle multiple trips simultaneously.
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
                                <label class="form-label">Contact Person</label>
                                <input type="text" class="form-control" name="contact_person"
                                       placeholder="Primary contact name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" name="phone"
                                       placeholder="+94 XX XXX XXXX">
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
                                <div class="border rounded p-2" style="max-height: 200px; overflow-y: auto;">
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
                                    <hr class="my-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="selectAllLocations">
                                        <label class="form-check-label fw-bold" for="selectAllLocations">Select All Locations</label>
                                    </div>
                                </div>
                                <small class="text-muted">Select all locations where this provider operates</small>
                            </div>
                        </div>


                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="3"
                                      placeholder="Additional information, service details, vehicle types available, pricing, etc."></textarea>
                        </div>

                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Important:</strong> Service providers can be allocated to multiple trips simultaneously as they have their own fleet of vehicles.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-plus me-2"></i>Add Service Provider
                        </button>
                    </div>
                </form>
            </div>
        </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle Select All Locations checkbox
        document.getElementById('selectAllLocations')?.addEventListener('change', function() {
            const locationCheckboxes = document.querySelectorAll('.location-check');
            locationCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Update Select All checkbox when individual checkboxes change
        document.querySelectorAll('.location-check')?.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allChecked = Array.from(document.querySelectorAll('.location-check')).every(cb => cb.checked);
                const selectAllCheckbox = document.getElementById('selectAllLocations');
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = allChecked;
                }
            });
        });

        // Form validation for service provider
        document.getElementById('serviceProviderForm')?.addEventListener('submit', function(e) {
            const checkedLocations = document.querySelectorAll('.location-check:checked');
            if (checkedLocations.length === 0) {
                e.preventDefault();
                alert('Please select at least one service location.');
                return false;
            }
        });
    </script>
    <script>
        // Duty toggle functionality
        document.querySelectorAll('.duty-toggle').forEach(button => {
            button.addEventListener('click', function() {
                if (this.disabled) return;

                const driverId = this.dataset.driverId;
                const duty = this.dataset.duty;

                // Show loading state
                const allButtons = document.querySelectorAll(`[data-driver-id="${driverId}"]`);
                allButtons.forEach(btn => {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                });

                // Make AJAX request
                fetch('driver_pool.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ajax=1&driver_id=${driverId}&is_on_duty=${duty}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Reload page to update UI
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                        // Reset buttons
                        allButtons.forEach(btn => {
                            btn.disabled = false;
                            btn.innerHTML = btn.dataset.duty === '1' ? '<i class="fas fa-toggle-on me-1"></i>On Duty' : '<i class="fas fa-toggle-off me-1"></i>Off Duty';
                        });
                    }
                })
                .catch(error => {
                    alert('Network error occurred');
                    // Reset buttons
                    allButtons.forEach(btn => {
                        btn.disabled = false;
                        btn.innerHTML = btn.dataset.duty === '1' ? '<i class="fas fa-toggle-on me-1"></i>On Duty' : '<i class="fas fa-toggle-off me-1"></i>Off Duty';
                    });
                });
            });
        });

        function deleteDriver(driverId, driverName) {
            document.getElementById('driverName').textContent = driverName;
            document.getElementById('deleteDriverId').value = driverId;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>