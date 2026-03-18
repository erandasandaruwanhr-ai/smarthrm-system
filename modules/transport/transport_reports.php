<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

// Check if user has access to this page
if (!isSuperAdmin() && !hasModulePermission($db, 'transport.transport_reports')) {
    header('Location: ../../dashboard.php');
    exit;
}

// Get user location and account type for access control
$account_type = $user['account_type'] ?? 'user';
$user_location = $user['location'] ?? $user['location_name'] ?? null;

// Get date range filter
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Today
$report_type = $_GET['report_type'] ?? 'overview';

// Apply location filtering for admin users
$location_condition = '';
$location_params = [];
if ($account_type === 'admin' && $user_location) {
    $location_condition = ' AND tr.departure_location = ?';
    $location_params = [$user_location];
}

// Get comprehensive statistics for the date range
$stats = [
    'total_requests' => $db->fetch("
        SELECT COUNT(*) as count
        FROM transport_requests tr
        WHERE DATE(tr.created_at) BETWEEN ? AND ? $location_condition
    ", array_merge([$start_date, $end_date], $location_params))['count'] ?? 0,

    'completed_trips' => $db->fetch("
        SELECT COUNT(*) as count
        FROM transport_allocations ta
        JOIN transport_requests tr ON ta.request_id = tr.id
        WHERE ta.status = 'completed'
        AND DATE(ta.created_at) BETWEEN ? AND ? $location_condition
    ", array_merge([$start_date, $end_date], $location_params))['count'] ?? 0,

    'active_vehicles' => $db->fetch("
        SELECT (
            COUNT(DISTINCT ta.vehicle_id) +
            COUNT(DISTINCT ta.service_provider_id)
        ) as count
        FROM transport_allocations ta
        JOIN transport_requests tr ON ta.request_id = tr.id
        WHERE DATE(ta.created_at) BETWEEN ? AND ? $location_condition
    ", array_merge([$start_date, $end_date], $location_params))['count'] ?? 0,

    'active_drivers' => $db->fetch("
        SELECT (
            COUNT(DISTINCT CASE WHEN ta.driver_id IS NOT NULL THEN ta.driver_id END) +
            COUNT(DISTINCT CASE WHEN ta.service_provider_id IS NOT NULL THEN ta.service_provider_id END)
        ) as count
        FROM transport_allocations ta
        JOIN transport_requests tr ON ta.request_id = tr.id
        WHERE DATE(ta.created_at) BETWEEN ? AND ? $location_condition
    ", array_merge([$start_date, $end_date], $location_params))['count'] ?? 0
];

// Get monthly request trends (last 12 months)
// First, generate all months in the last 12 months
$months = [];
$current_date = new DateTime(); // This will be current server date
for ($i = 11; $i >= 0; $i--) {
    $date = clone $current_date;
    $date->sub(new DateInterval('P' . $i . 'M'));
    $months[] = $date->format('Y-m');
}


// Get the actual data from database with location filtering for admin users
$monthly_data = $db->fetchAll("
    SELECT
        DATE_FORMAT(tr.created_at, '%Y-%m') as month,
        COUNT(*) as requests,
        COUNT(CASE WHEN tr.id IN (SELECT request_id FROM transport_allocations WHERE status = 'completed') THEN 1 END) as completed
    FROM transport_requests tr
    WHERE tr.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) $location_condition
    GROUP BY DATE_FORMAT(tr.created_at, '%Y-%m')
", $location_params);

// Create an associative array for easy lookup
$data_by_month = [];
foreach ($monthly_data as $data) {
    $data_by_month[$data['month']] = $data;
}

// Build the final array with all months
$monthly_trends = [];
foreach ($months as $month) {
    if (isset($data_by_month[$month])) {
        $monthly_trends[] = $data_by_month[$month];
    } else {
        $monthly_trends[] = [
            'month' => $month,
            'requests' => 0,
            'completed' => 0
        ];
    }
}

// Get vehicle and service provider utilization data with location filtering
if ($account_type === 'admin' && $user_location) {
    // For admin users, only show vehicles/service providers that have been used for trips from their location
    $vehicle_usage = $db->fetchAll("
        SELECT * FROM (
            SELECT
                v.vehicle_number,
                v.brand,
                v.model,
                'Company Vehicle' as provider_type,
                COUNT(ta.id) as total_trips,
                COUNT(CASE WHEN ta.status = 'completed' THEN 1 END) as completed_trips,
                ROUND(AVG(CASE WHEN ta.status = 'completed' THEN 1 ELSE 0 END) * 100, 2) as completion_rate
            FROM vehicles v
            INNER JOIN transport_allocations ta ON v.id = ta.vehicle_id
            INNER JOIN transport_requests tr ON ta.request_id = tr.id
            WHERE DATE(ta.created_at) BETWEEN ? AND ?
            AND tr.departure_location = ?
            GROUP BY v.id, v.vehicle_number, v.brand, v.model

            UNION ALL

            SELECT
                sp.vendor_code as vehicle_number,
                sp.company_name as brand,
                'Service Provider' as model,
                'Service Provider' as provider_type,
                COUNT(ta.id) as total_trips,
                COUNT(CASE WHEN ta.status = 'completed' THEN 1 END) as completed_trips,
                ROUND(AVG(CASE WHEN ta.status = 'completed' THEN 1 ELSE 0 END) * 100, 2) as completion_rate
            FROM service_providers sp
            INNER JOIN transport_allocations ta ON sp.id = ta.service_provider_id
            INNER JOIN transport_requests tr ON ta.request_id = tr.id
            WHERE DATE(ta.created_at) BETWEEN ? AND ?
            AND tr.departure_location = ?
            GROUP BY sp.id, sp.vendor_code, sp.company_name
        ) combined
        ORDER BY total_trips DESC
    ", [$start_date, $end_date, $user_location, $start_date, $end_date, $user_location]);
} else {
    // SuperAdmin sees all vehicles and service providers
    $vehicle_usage = $db->fetchAll("
        SELECT * FROM (
            SELECT
                v.vehicle_number,
                v.brand,
                v.model,
                'Company Vehicle' as provider_type,
                COUNT(ta.id) as total_trips,
                COUNT(CASE WHEN ta.status = 'completed' THEN 1 END) as completed_trips,
                ROUND(AVG(CASE WHEN ta.status = 'completed' THEN 1 ELSE 0 END) * 100, 2) as completion_rate
            FROM vehicles v
            LEFT JOIN transport_allocations ta ON v.id = ta.vehicle_id
                AND DATE(ta.created_at) BETWEEN ? AND ?
            GROUP BY v.id, v.vehicle_number, v.brand, v.model

            UNION ALL

            SELECT
                sp.vendor_code as vehicle_number,
                sp.company_name as brand,
                'Service Provider' as model,
                'Service Provider' as provider_type,
                COUNT(ta.id) as total_trips,
                COUNT(CASE WHEN ta.status = 'completed' THEN 1 END) as completed_trips,
                ROUND(AVG(CASE WHEN ta.status = 'completed' THEN 1 ELSE 0 END) * 100, 2) as completion_rate
            FROM service_providers sp
            LEFT JOIN transport_allocations ta ON sp.id = ta.service_provider_id
                AND DATE(ta.created_at) BETWEEN ? AND ?
            GROUP BY sp.id, sp.vendor_code, sp.company_name
        ) combined
        ORDER BY total_trips DESC
    ", [$start_date, $end_date, $start_date, $end_date]);
}

// Get driver and service provider performance data with location filtering
$driver_location_condition = '';
$driver_params = [$start_date, $end_date];
$sp_driver_params = [$start_date, $end_date];

if ($account_type === 'admin' && $user_location) {
    $driver_location_condition = ' AND d.location = ?';
    $driver_params[] = $user_location;

    // For service providers, filter by those serving the admin's location
    $sp_driver_condition = ' AND sp.locations LIKE ?';
    $sp_driver_params[] = "%{$user_location}%";
} else {
    $sp_driver_condition = '';
}

$driver_performance = $db->fetchAll("
    SELECT * FROM (
        SELECT
            d.emp_name,
            d.emp_number,
            d.license_number,
            'Employee Driver' as driver_type,
            COUNT(ta.id) as total_trips,
            COUNT(CASE WHEN ta.status = 'completed' THEN 1 END) as completed_trips,
            ROUND(AVG(CASE WHEN ta.status = 'completed' THEN 1 ELSE 0 END) * 100, 2) as completion_rate,
            d.experience_years
        FROM drivers d
        LEFT JOIN transport_allocations ta ON d.id = ta.driver_id
            AND DATE(ta.created_at) BETWEEN ? AND ?
        WHERE (d.driver_type = 'employee' OR d.driver_type IS NULL) $driver_location_condition
        GROUP BY d.id, d.emp_name, d.emp_number, d.license_number, d.experience_years

        UNION ALL

        SELECT
            sp.company_name as emp_name,
            sp.vendor_code as emp_number,
            'Service Provider' as license_number,
            'Service Provider' as driver_type,
            COUNT(ta.id) as total_trips,
            COUNT(CASE WHEN ta.status = 'completed' THEN 1 END) as completed_trips,
            ROUND(AVG(CASE WHEN ta.status = 'completed' THEN 1 ELSE 0 END) * 100, 2) as completion_rate,
            NULL as experience_years
        FROM service_providers sp
        LEFT JOIN transport_allocations ta ON sp.id = ta.service_provider_id
            AND DATE(ta.created_at) BETWEEN ? AND ?
        WHERE 1=1 $sp_driver_condition
        GROUP BY sp.id, sp.company_name, sp.vendor_code
    ) combined
    ORDER BY total_trips DESC
", array_merge($driver_params, $sp_driver_params));

// Routes analysis removed - not using predefined routes

// Get status distribution with location filtering
$status_distribution = $db->fetchAll("
    SELECT
        ta.status,
        COUNT(*) as count
    FROM transport_allocations ta
    JOIN transport_requests tr ON ta.request_id = tr.id
    WHERE DATE(ta.created_at) BETWEEN ? AND ? $location_condition
    GROUP BY ta.status
    ORDER BY count DESC
", array_merge([$start_date, $end_date], $location_params));

// Get urgency level analysis with location filtering
$urgency_analysis = $db->fetchAll("
    SELECT
        tr.urgency_level,
        COUNT(*) as count,
        ROUND(AVG(CASE WHEN ta.status = 'completed' THEN 1 ELSE 0 END) * 100, 2) as completion_rate
    FROM transport_requests tr
    LEFT JOIN transport_allocations ta ON tr.id = ta.request_id
    WHERE DATE(tr.created_at) BETWEEN ? AND ? $location_condition
    GROUP BY tr.urgency_level
    ORDER BY count DESC
", array_merge([$start_date, $end_date], $location_params));

// Get filter parameters
$location_filter = $_GET['location'] ?? '';
$tab = $_GET['tab'] ?? 'charts';

// Get locations for filter dropdown (restrict for admin users)
if ($account_type === 'admin' && $user_location) {
    // Admin users only see their own location
    $all_locations = [['location_name' => $user_location]];
} else {
    // SuperAdmin sees all locations
    $all_locations = $db->fetchAll("SELECT DISTINCT location_name FROM locations ORDER BY location_name");
}

// Build where clause for completed trips
$trip_where = ["ta.status = 'completed'"];
$trip_params = [];

// Apply admin location restriction first
if ($account_type === 'admin' && $user_location) {
    $trip_where[] = "tr.departure_location = ?";
    $trip_params[] = $user_location;
}

if (!empty($start_date) && !empty($end_date)) {
    $trip_where[] = "DATE(ta.actual_return) BETWEEN ? AND ?";
    $trip_params[] = $start_date;
    $trip_params[] = $end_date;
}

// Only apply location filter if user is SuperAdmin (admin users are already restricted)
if (!empty($location_filter) && $account_type === 'superadmin') {
    $trip_where[] = "(tr.departure_location = ? OR tr.destination = ?)";
    $trip_params[] = $location_filter;
    $trip_params[] = $location_filter;
}

$trip_where_clause = !empty($trip_where) ? 'WHERE ' . implode(' AND ', $trip_where) : '';

// Get completed trips with all details
$completed_trips = $db->fetchAll("
    SELECT ta.*,
           tr.purpose, tr.departure_location, tr.destination,
           tr.departure_date, tr.departure_time,
           tr.return_date, tr.return_time,
           tr.passenger_count, tr.passenger_names,
           tr.special_requirements, tr.urgency_level, tr.request_type,
           tr.emp_name as requester_name, tr.emp_number as requester_emp_number,
           tr.emp_location as requester_location,
           COALESCE(v.vehicle_number, 'Service Provider') as vehicle_number,
           COALESCE(v.vehicle_type, sp.vehicle_types) as vehicle_type,
           COALESCE(v.brand, '') as brand,
           COALESCE(v.model, '') as model,
           COALESCE(d.emp_name, sp.company_name) as driver_name,
           COALESCE(d.emp_number, sp.vendor_code) as driver_code,
           ta.odometer_start, ta.odometer_end,
           ta.fuel_start, ta.fuel_end,
           ta.notes as completion_notes,
           TIMESTAMPDIFF(HOUR, ta.scheduled_departure, ta.actual_return) as trip_duration_hours,
           (ta.odometer_end - ta.odometer_start) as distance_km
    FROM transport_allocations ta
    JOIN transport_requests tr ON ta.request_id = tr.id
    LEFT JOIN vehicles v ON ta.vehicle_id = v.id
    LEFT JOIN drivers d ON ta.driver_id = d.id
    LEFT JOIN service_providers sp ON ta.service_provider_id = sp.id
    $trip_where_clause
    ORDER BY ta.actual_return DESC
", $trip_params);

// Calculate trip statistics
$trip_stats = [
    'total_trips' => count($completed_trips),
    'total_distance' => array_sum(array_column($completed_trips, 'distance_km')),
    'total_fuel' => 0,
    'avg_duration' => 0
];

if (count($completed_trips) > 0) {
    $total_fuel_consumed = 0;
    $valid_fuel_count = 0;
    $total_duration = 0;
    $valid_duration_count = 0;

    foreach ($completed_trips as $trip) {
        if ($trip['fuel_start'] && $trip['fuel_end']) {
            $fuel_used = $trip['fuel_start'] - $trip['fuel_end'];
            if ($fuel_used > 0) {
                $total_fuel_consumed += $fuel_used;
                $valid_fuel_count++;
            }
        }
        if ($trip['trip_duration_hours'] > 0) {
            $total_duration += $trip['trip_duration_hours'];
            $valid_duration_count++;
        }
    }

    $trip_stats['total_fuel'] = $total_fuel_consumed;
    $trip_stats['avg_duration'] = $valid_duration_count > 0 ? round($total_duration / $valid_duration_count, 1) : 0;
}

// Get location-wise breakdown with admin location filtering
$location_breakdown = $db->fetchAll("
    SELECT
        COALESCE(l.location_name, tr.emp_location, tr.departure_location, 'Unknown') as location,
        COUNT(tr.id) as total_requests,
        COUNT(CASE WHEN ta.status = 'completed' THEN 1 END) as completed_requests,
        COUNT(CASE WHEN tr.urgency_level = 'urgent' THEN 1 END) as urgent_requests,
        ROUND(AVG(tr.passenger_count), 1) as avg_passengers
    FROM transport_requests tr
    LEFT JOIN transport_allocations ta ON tr.id = ta.request_id
    LEFT JOIN employees e ON tr.emp_number = e.epf_number
    LEFT JOIN locations l ON e.location_id = l.id
    WHERE DATE(tr.created_at) BETWEEN ? AND ? $location_condition
    GROUP BY COALESCE(l.location_name, tr.emp_location, tr.departure_location, 'Unknown')
    HAVING COUNT(tr.id) > 0
    ORDER BY total_requests DESC
", array_merge([$start_date, $end_date], $location_params));

// Get department-wise statistics with admin location filtering
$department_stats = $db->fetchAll("
    SELECT
        COALESCE(e.department, 'Unknown Department') as department,
        COUNT(tr.id) as requests,
        ROUND(AVG(tr.passenger_count), 1) as avg_passengers,
        COUNT(CASE WHEN tr.urgency_level = 'urgent' THEN 1 END) as urgent_count
    FROM transport_requests tr
    LEFT JOIN employees e ON tr.emp_number = e.epf_number
    WHERE DATE(tr.created_at) BETWEEN ? AND ? $location_condition
    GROUP BY e.department
    HAVING COUNT(tr.id) > 0
    ORDER BY requests DESC
    LIMIT 10
", array_merge([$start_date, $end_date], $location_params));

// Get location vs vehicle/service provider utilization with admin location filtering
if ($account_type === 'admin' && $user_location) {
    // For admin users, show all vehicles from their location + all service providers serving their location
    $location_vehicle_usage = $db->fetchAll("
        SELECT * FROM (
            -- Company vehicles from admin's location (with usage count)
            SELECT
                ? as location,
                v.vehicle_number,
                v.vehicle_type,
                'Company Vehicle' as provider_type,
                COALESCE(vehicle_usage.usage_count, 0) as usage_count
            FROM vehicles v
            LEFT JOIN (
                SELECT ta.vehicle_id, COUNT(*) as usage_count
                FROM transport_allocations ta
                JOIN transport_requests tr ON ta.request_id = tr.id
                WHERE DATE(ta.created_at) BETWEEN ? AND ?
                AND tr.departure_location = ?
                GROUP BY ta.vehicle_id
            ) vehicle_usage ON v.id = vehicle_usage.vehicle_id
            WHERE v.location = ?

            UNION ALL

            -- Service providers serving admin's location (with usage count)
            SELECT
                ? as location,
                sp.company_name as vehicle_number,
                'Service Provider' as vehicle_type,
                'Service Provider' as provider_type,
                COALESCE(sp_usage.usage_count, 0) as usage_count
            FROM service_providers sp
            LEFT JOIN (
                SELECT ta.service_provider_id, COUNT(*) as usage_count
                FROM transport_allocations ta
                JOIN transport_requests tr ON ta.request_id = tr.id
                WHERE DATE(ta.created_at) BETWEEN ? AND ?
                AND tr.departure_location = ?
                GROUP BY ta.service_provider_id
            ) sp_usage ON sp.id = sp_usage.service_provider_id
            WHERE sp.locations LIKE ?
        ) combined
        ORDER BY location ASC, usage_count DESC
    ", [$user_location, $start_date, $end_date, $user_location, $user_location, $user_location, $start_date, $end_date, $user_location, "%{$user_location}%"]);
} else {
    // For SuperAdmin, show original query
    $location_vehicle_usage = $db->fetchAll("
        SELECT * FROM (
            SELECT
                COALESCE(l.location_name, tr.emp_location, tr.departure_location, 'Unknown') as location,
                v.vehicle_number,
                v.vehicle_type,
                'Company Vehicle' as provider_type,
                COUNT(ta.id) as usage_count
            FROM transport_allocations ta
            JOIN transport_requests tr ON ta.request_id = tr.id
            JOIN vehicles v ON ta.vehicle_id = v.id
            LEFT JOIN employees e ON tr.emp_number = e.epf_number
            LEFT JOIN locations l ON e.location_id = l.id
            WHERE DATE(ta.created_at) BETWEEN ? AND ?
            GROUP BY COALESCE(l.location_name, tr.emp_location, tr.departure_location, 'Unknown'),
                     v.id, v.vehicle_number, v.vehicle_type

            UNION ALL

            SELECT
                COALESCE(l.location_name, tr.emp_location, tr.departure_location, 'Unknown') as location,
                sp.company_name as vehicle_number,
                'Service Provider' as vehicle_type,
                'Service Provider' as provider_type,
                COUNT(ta.id) as usage_count
            FROM transport_allocations ta
            JOIN transport_requests tr ON ta.request_id = tr.id
            JOIN service_providers sp ON ta.service_provider_id = sp.id
            LEFT JOIN employees e ON tr.emp_number = e.epf_number
            LEFT JOIN locations l ON e.location_id = l.id
            WHERE DATE(ta.created_at) BETWEEN ? AND ?
            GROUP BY COALESCE(l.location_name, tr.emp_location, tr.departure_location, 'Unknown'),
                     sp.id, sp.company_name
        ) combined
        ORDER BY location ASC, usage_count DESC
    ", [$start_date, $end_date, $start_date, $end_date]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Transport Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
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
    </style>
    <style>
        .chart-container {
            position: relative;
            height: 400px;
            margin-bottom: 20px;
        }
        .report-card {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            border: none;
            border-radius: 10px;
            transition: transform 0.2s;
        }
        .report-card:hover {
            transform: translateY(-5px);
        }
        .metric-card {
            background: white;
            border: 1px solid #e3e6f0;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: box-shadow 0.2s;
        }
        .metric-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .metric-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #5a5c69;
        }
        .metric-label {
            color: #858796;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
        }
        .nav-tabs .nav-link {
            color: #495057;
            font-weight: 500;
        }
        .nav-tabs .nav-link.active {
            color: #007bff;
            border-color: #dee2e6 #dee2e6 #fff;
        }
        .trip-table {
            font-size: 0.9rem;
        }
        .trip-stats {
            display: flex;
            justify-content: space-around;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .trip-stat-item {
            text-align: center;
        }
        .trip-stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #007bff;
        }
        .trip-stat-label {
            color: #6c757d;
            font-size: 0.9rem;
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
                        <h1><i class="fas fa-file-alt me-3"></i>Transport Reports</h1>
                        <p>Generate comprehensive transport usage and analytics reports</p>
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
                    <li class="breadcrumb-item active">Transport Reports</li>
                </ol>
            </nav>

        <!-- Tab Navigation -->
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $tab === 'charts' ? 'active' : ''; ?>"
                   href="?tab=charts&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&report_type=<?php echo $report_type; ?>&location=<?php echo $location_filter; ?>">
                    <i class="fas fa-chart-bar me-2"></i>Charts & Analytics
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $tab === 'trips' ? 'active' : ''; ?>"
                   href="?tab=trips&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&location=<?php echo $location_filter; ?>">
                    <i class="fas fa-list me-2"></i>Completed Trips (<?php echo count($completed_trips); ?>)
                </a>
            </li>
        </ul>

        <!-- Filter Controls -->
        <div class="card border-0 shadow mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
                    <?php if ($tab === 'charts'): ?>
                    <div class="col-md-3">
                        <label class="form-label">Report Type</label>
                        <select class="form-select" name="report_type">
                            <option value="overview" <?php echo $report_type === 'overview' ? 'selected' : ''; ?>>Overview</option>
                            <option value="vehicles" <?php echo $report_type === 'vehicles' ? 'selected' : ''; ?>>Vehicle Analysis</option>
                            <option value="drivers" <?php echo $report_type === 'drivers' ? 'selected' : ''; ?>>Driver Performance</option>
                            <option value="locations" <?php echo $report_type === 'locations' ? 'selected' : ''; ?>>Location Analysis</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-3">
                        <label class="form-label">Location Filter</label>
                        <select class="form-select" name="location">
                            <option value="">All Locations</option>
                            <?php foreach ($all_locations as $loc): ?>
                            <option value="<?php echo htmlspecialchars($loc['location_name']); ?>"
                                    <?php echo $location_filter === $loc['location_name'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($loc['location_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-chart-line me-2"></i>Generate Report
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($tab === 'charts'): ?>
        <!-- Charts Tab Content -->

        <?php
        // Calculate service provider vs company vehicle metrics
        $sp_trips = 0;
        $cv_trips = 0;
        foreach ($vehicle_usage as $v) {
            if ($v['provider_type'] === 'Service Provider') {
                $sp_trips += $v['total_trips'];
            } else {
                $cv_trips += $v['total_trips'];
            }
        }
        $total_trips = $sp_trips + $cv_trips;
        $sp_percentage = $total_trips > 0 ? round(($sp_trips / $total_trips) * 100, 1) : 0;
        $cv_percentage = $total_trips > 0 ? round(($cv_trips / $total_trips) * 100, 1) : 0;
        ?>

        <!-- Key Metrics -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="metric-card">
                    <div class="metric-value text-primary"><?php echo number_format($stats['total_requests']); ?></div>
                    <div class="metric-label">Total Requests</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="metric-card">
                    <div class="metric-value text-success"><?php echo number_format($stats['completed_trips']); ?></div>
                    <div class="metric-label">Completed Trips</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="metric-card">
                    <div class="metric-value text-info"><?php echo number_format($stats['active_vehicles']); ?></div>
                    <div class="metric-label">Active Vehicles/Providers</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="metric-card">
                    <div class="metric-value text-warning"><?php echo number_format($stats['active_drivers']); ?></div>
                    <div class="metric-label">Active Drivers/Providers</div>
                </div>
            </div>
        </div>

        <!-- Service Provider vs Company Vehicle Comparison -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow">
                    <div class="card-header bg-gradient bg-primary text-white">
                        <h5 class="m-0">
                            <i class="fas fa-balance-scale me-2"></i>Fleet Utilization Comparison
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <h6 class="text-muted">Company Vehicles</h6>
                                <div class="display-6 text-info"><?php echo number_format($cv_trips); ?></div>
                                <p class="text-muted">Trips (<?php echo $cv_percentage; ?>%)</p>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar bg-info" style="width: <?php echo $cv_percentage; ?>%"></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <h6 class="text-muted">Service Providers</h6>
                                <div class="display-6 text-warning"><?php echo number_format($sp_trips); ?></div>
                                <p class="text-muted">Trips (<?php echo $sp_percentage; ?>%)</p>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar bg-warning" style="width: <?php echo $sp_percentage; ?>%"></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <h6 class="text-muted">Total Operations</h6>
                                <div class="display-6 text-success"><?php echo number_format($total_trips); ?></div>
                                <p class="text-muted">Combined Trips</p>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar bg-success" style="width: 100%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Monthly Trends Chart -->
            <div class="col-xl-8 mb-4">
                <div class="card border-0 shadow">
                    <div class="card-header">
                        <h5 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-chart-line me-2"></i>Request Trends (Last 12 Months)
                            <?php
                            $total_in_period = array_sum(array_column($monthly_trends, 'requests'));
                            if ($total_in_period > 0): ?>
                            <span class="badge bg-success ms-2"><?php echo $total_in_period; ?> Total</span>
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="trendsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Distribution -->
            <div class="col-xl-4 mb-4">
                <div class="card border-0 shadow">
                    <div class="card-header">
                        <h5 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-chart-pie me-2"></i>Status Distribution
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($report_type === 'vehicles' || $report_type === 'overview'): ?>
        <!-- Vehicle Performance -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow">
                    <div class="card-header">
                        <h5 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-car me-2"></i>Vehicle & Service Provider Utilization
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-8">
                                <div class="chart-container">
                                    <canvas id="vehicleChart"></canvas>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Vehicle/Provider</th>
                                                <th>Trips</th>
                                                <th>Rate</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($vehicle_usage as $vehicle): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($vehicle['vehicle_number']); ?></strong>
                                                    <?php if ($vehicle['provider_type'] === 'Service Provider'): ?>
                                                    <br><small class="text-warning"><i class="fas fa-truck"></i> <?php echo htmlspecialchars($vehicle['brand']); ?></small>
                                                    <?php else: ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $vehicle['total_trips']; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $vehicle['completion_rate'] > 80 ? 'success' : ($vehicle['completion_rate'] > 60 ? 'warning' : 'danger'); ?>">
                                                        <?php echo $vehicle['completion_rate']; ?>%
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($report_type === 'drivers' || $report_type === 'overview'): ?>
        <!-- Driver Performance -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow">
                    <div class="card-header">
                        <h5 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-user-tie me-2"></i>Driver & Service Provider Performance
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-8">
                                <div class="chart-container">
                                    <canvas id="driverChart"></canvas>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Driver/Provider</th>
                                                <th>Trips</th>
                                                <th>Rate</th>
                                                <th>Type</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($driver_performance as $driver): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($driver['emp_name']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($driver['emp_number']); ?></small>
                                                </td>
                                                <td><?php echo $driver['total_trips']; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $driver['completion_rate'] > 80 ? 'success' : ($driver['completion_rate'] > 60 ? 'warning' : 'danger'); ?>">
                                                        <?php echo $driver['completion_rate']; ?>%
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($driver['driver_type'] === 'Service Provider'): ?>
                                                    <span class="badge bg-warning text-dark">SP</span>
                                                    <?php else: ?>
                                                    <span class="badge bg-info"><?php echo $driver['experience_years'] ?? 0; ?>y</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Request Urgency Analysis -->
        <?php if (!empty($urgency_analysis) && count(array_filter($urgency_analysis, function($item) { return $item['count'] > 0; })) > 0): ?>
        <div class="row mb-4">
            <div class="col-lg-12">
                <div class="card border-0 shadow">
                    <div class="card-header">
                        <h5 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-exclamation-triangle me-2"></i>Request Urgency Analysis
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="chart-container">
                                    <canvas id="urgencyChart"></canvas>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Urgency Level</th>
                                                <th>Count</th>
                                                <th>Completion Rate</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($urgency_analysis as $urgency): ?>
                                            <?php if ($urgency['count'] > 0): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-<?php
                        $urgency_colors = [
                            'urgent' => 'danger',  // Red
                            'high' => 'primary',   // Blue
                            'medium' => 'warning', // Yellow
                            'low' => 'success'     // Green
                        ];
                        echo $urgency_colors[strtolower($urgency['urgency_level'])] ?? 'secondary';
                    ?>">
                                                        <?php echo ucwords($urgency['urgency_level'] ?? 'normal'); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $urgency['count']; ?></td>
                                                <td><?php echo $urgency['completion_rate']; ?>%</td>
                                            </tr>
                                            <?php endif; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($report_type === 'locations' || $report_type === 'overview'): ?>
        <!-- Location Analysis -->
        <div class="row mb-4">
            <?php if (!empty($location_breakdown) && count($location_breakdown) > 0): ?>
            <div class="col-lg-8">
                <div class="card border-0 shadow">
                    <div class="card-header">
                        <h5 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-map-marker-alt me-2"></i>Requests by Location
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="locationChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($department_stats) && count($department_stats) > 0): ?>
            <div class="col-lg-4">
                <div class="card border-0 shadow">
                    <div class="card-header">
                        <h5 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-building me-2"></i>Department Breakdown
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="departmentChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Location Details Table -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow">
                    <div class="card-header">
                        <h5 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-table me-2"></i>Location-wise Transport Statistics
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Location</th>
                                        <th>Total Requests</th>
                                        <th>Completed</th>
                                        <th>Completion Rate</th>
                                        <th>Urgent Requests</th>
                                        <th>Avg. Passengers</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($location_breakdown as $location): ?>
                                    <?php
                                        $completion_rate = $location['total_requests'] > 0
                                            ? round(($location['completed_requests'] / $location['total_requests']) * 100, 1)
                                            : 0;
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($location['location']); ?></strong></td>
                                        <td><?php echo number_format($location['total_requests']); ?></td>
                                        <td><?php echo number_format($location['completed_requests']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $completion_rate > 80 ? 'success' : ($completion_rate > 60 ? 'warning' : 'danger'); ?>">
                                                <?php echo $completion_rate; ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($location['urgent_requests'] > 0): ?>
                                                <span class="badge bg-danger"><?php echo $location['urgent_requests']; ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $location['avg_passengers'] ?: 'N/A'; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Location vs Vehicle Usage -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow">
                    <div class="card-header">
                        <h5 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-car-side me-2"></i>Vehicle Usage by Location
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Location</th>
                                        <th>Vehicle</th>
                                        <th>Type</th>
                                        <th>Usage Count</th>
                                        <th>Usage Bar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $max_usage = max(array_column($location_vehicle_usage, 'usage_count'));
                                    $current_location = '';
                                    ?>
                                    <?php foreach ($location_vehicle_usage as $usage): ?>
                                    <?php
                                        $usage_percentage = $max_usage > 0 ? ($usage['usage_count'] / $max_usage) * 100 : 0;
                                        $show_location = $usage['location'] !== $current_location;
                                        $current_location = $usage['location'];
                                    ?>
                                    <tr>
                                        <td>
                                            <?php if ($show_location): ?>
                                                <strong><?php echo htmlspecialchars($usage['location']); ?></strong>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($usage['vehicle_number']); ?>
                                            <?php if ($usage['provider_type'] === 'Service Provider'): ?>
                                            <span class="badge bg-warning text-dark ms-1">SP</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><small class="text-muted"><?php echo htmlspecialchars($usage['vehicle_type']); ?></small></td>
                                        <td><strong><?php echo $usage['usage_count']; ?></strong></td>
                                        <td>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-primary" style="width: <?php echo $usage_percentage; ?>%"></div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <!-- Trips Tab Content -->

        <!-- Trip Statistics -->
        <div class="trip-stats mb-4">
            <div class="trip-stat-item">
                <div class="trip-stat-value"><?php echo number_format($trip_stats['total_trips']); ?></div>
                <div class="trip-stat-label">Total Trips</div>
            </div>
            <div class="trip-stat-item">
                <div class="trip-stat-value"><?php echo number_format($trip_stats['total_distance']); ?> km</div>
                <div class="trip-stat-label">Total Distance</div>
            </div>
            <div class="trip-stat-item">
                <div class="trip-stat-value"><?php echo number_format($trip_stats['total_fuel'], 1); ?> L</div>
                <div class="trip-stat-label">Fuel Consumed</div>
            </div>
            <div class="trip-stat-item">
                <div class="trip-stat-value"><?php echo $trip_stats['avg_duration']; ?> hrs</div>
                <div class="trip-stat-label">Avg Duration</div>
            </div>
        </div>

        <!-- Completed Trips Table -->
        <div class="card border-0 shadow">
            <div class="card-header">
                <h5 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-check-circle me-2"></i>Completed Transport Trips
                    <?php if (!empty($location_filter)): ?>
                    <span class="badge bg-info ms-2">Location: <?php echo htmlspecialchars($location_filter); ?></span>
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover trip-table">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Route</th>
                                <th>Requester</th>
                                <th>Vehicle/Provider</th>
                                <th>Driver/Company</th>
                                <th>Passengers</th>
                                <th>Distance</th>
                                <th>Fuel Used</th>
                                <th>Duration</th>
                                <th>Notes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($completed_trips)): ?>
                            <tr>
                                <td colspan="12" class="text-center py-4">
                                    <i class="fas fa-info-circle fa-3x text-muted mb-3 d-block"></i>
                                    <span class="text-muted">No completed trips found for the selected criteria</span>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($completed_trips as $index => $trip): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <strong><?php echo date('M d, Y', strtotime($trip['actual_return'])); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo date('h:i A', strtotime($trip['actual_return'])); ?></small>
                                    </td>
                                    <td>
                                        <strong class="text-primary"><?php echo htmlspecialchars($trip['departure_location']); ?></strong>
                                        <i class="fas fa-arrow-right mx-1 text-success"></i>
                                        <strong class="text-primary"><?php echo htmlspecialchars($trip['destination']); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($trip['requester_name']); ?>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($trip['requester_emp_number']); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($trip['service_provider_id']): ?>
                                            <span class="badge bg-warning text-dark">
                                                <i class="fas fa-truck me-1"></i>Service Provider
                                            </span>
                                        <?php else: ?>
                                            <strong><?php echo htmlspecialchars($trip['vehicle_number']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($trip['vehicle_type']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($trip['driver_name'] ?? ''); ?>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($trip['driver_code'] ?? ''); ?></small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary"><?php echo $trip['passenger_count']; ?></span>
                                    </td>
                                    <td>
                                        <?php if ($trip['distance_km'] > 0): ?>
                                            <strong><?php echo number_format($trip['distance_km']); ?> km</strong>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $fuel_used = 0;
                                        if ($trip['fuel_start'] && $trip['fuel_end']) {
                                            $fuel_used = $trip['fuel_start'] - $trip['fuel_end'];
                                        }
                                        ?>
                                        <?php if ($fuel_used > 0): ?>
                                            <strong><?php echo number_format($fuel_used, 1); ?> L</strong>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($trip['trip_duration_hours'] > 0): ?>
                                            <?php echo $trip['trip_duration_hours']; ?> hrs
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($trip['completion_notes'])): ?>
                                            <small><?php echo htmlspecialchars(substr($trip['completion_notes'], 0, 50)); ?>
                                            <?php echo strlen($trip['completion_notes']) > 50 ? '...' : ''; ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-info" onclick="viewTripDetails(<?php echo $trip['id']; ?>)"
                                                data-trip='<?php echo json_encode($trip); ?>'>
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Trip Details Modal -->
    <div class="modal fade" id="tripDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Trip Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="tripDetailsContent">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // View Trip Details Function
        function viewTripDetails(tripId) {
            const button = event.currentTarget;
            const tripData = JSON.parse(button.getAttribute('data-trip'));

            let fuelInfo = '-';
            if (tripData.fuel_start && tripData.fuel_end) {
                const fuelUsed = tripData.fuel_start - tripData.fuel_end;
                fuelInfo = `Start: ${tripData.fuel_start}L, End: ${tripData.fuel_end}L, Used: ${fuelUsed.toFixed(1)}L`;
            }

            let distanceInfo = '-';
            if (tripData.odometer_start && tripData.odometer_end) {
                distanceInfo = `Start: ${tripData.odometer_start}km, End: ${tripData.odometer_end}km, Distance: ${tripData.distance_km}km`;
            }

            const content = `
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary">Route Information</h6>
                        <p><strong>From:</strong> ${tripData.departure_location}</p>
                        <p><strong>To:</strong> ${tripData.destination}</p>
                        <p><strong>Purpose:</strong> ${tripData.purpose || '-'}</p>
                        <p><strong>Urgency:</strong> <span class="badge bg-${
                            tripData.urgency_level ? (
                                tripData.urgency_level.toLowerCase() === 'urgent' ? 'danger' :
                                tripData.urgency_level.toLowerCase() === 'high' ? 'primary' :
                                tripData.urgency_level.toLowerCase() === 'medium' ? 'warning' :
                                tripData.urgency_level.toLowerCase() === 'low' ? 'success' : 'info'
                            ) : 'secondary'
                        }">${tripData.urgency_level ? tripData.urgency_level.charAt(0).toUpperCase() + tripData.urgency_level.slice(1) : 'Normal'}</span></p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary">Schedule Information</h6>
                        <p><strong>Scheduled:</strong> ${new Date(tripData.scheduled_departure).toLocaleString()}</p>
                        <p><strong>Completed:</strong> ${new Date(tripData.actual_return).toLocaleString()}</p>
                        <p><strong>Duration:</strong> ${tripData.trip_duration_hours} hours</p>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary">Vehicle/Provider Details</h6>
                        <p><strong>Vehicle:</strong> ${tripData.vehicle_number}</p>
                        <p><strong>Type:</strong> ${tripData.vehicle_type || '-'}</p>
                        <p><strong>Driver:</strong> ${tripData.driver_name}</p>
                        <p><strong>Code:</strong> ${tripData.driver_code}</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary">Trip Metrics</h6>
                        <p><strong>Passengers:</strong> ${tripData.passenger_count}</p>
                        <p><strong>Odometer:</strong> ${distanceInfo}</p>
                        <p><strong>Fuel:</strong> ${fuelInfo}</p>
                    </div>
                </div>
                ${tripData.completion_notes ? `
                <hr>
                <div class="row">
                    <div class="col-12">
                        <h6 class="text-primary">Completion Notes</h6>
                        <p>${tripData.completion_notes}</p>
                    </div>
                </div>
                ` : ''}
                ${tripData.passenger_names ? `
                <hr>
                <div class="row">
                    <div class="col-12">
                        <h6 class="text-primary">Passenger Details</h6>
                        <p>${tripData.passenger_names}</p>
                    </div>
                </div>
                ` : ''}
            `;

            document.getElementById('tripDetailsContent').innerHTML = content;
            new bootstrap.Modal(document.getElementById('tripDetailsModal')).show();
        }

        <?php if ($tab === 'charts'): ?>
        // Initialize charts when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Check if Chart.js is loaded
            if (typeof Chart === 'undefined') {
                console.error('Chart.js not loaded');
                return;
            }

            try {
                // Monthly Trends Chart
                const trendsCanvas = document.getElementById('trendsChart');
                if (trendsCanvas) {
                    new Chart(trendsCanvas, {
            type: 'line',
            data: {
                labels: [<?php echo implode(',', array_map(function($item) { return '"' . date('M Y', strtotime($item['month'] . '-01')) . '"'; }, $monthly_trends)); ?>],
                datasets: [{
                    label: 'Requests',
                    data: [<?php echo implode(',', array_column($monthly_trends, 'requests')); ?>],
                    borderColor: 'rgb(0, 123, 255)',  // Blue
                    backgroundColor: 'rgba(0, 123, 255, 0.2)',
                    tension: 0.1
                }, {
                    label: 'Completed',
                    data: [<?php echo implode(',', array_column($monthly_trends, 'completed')); ?>],
                    borderColor: 'rgb(40, 167, 69)',  // Green
                    backgroundColor: 'rgba(40, 167, 69, 0.2)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
                    });
                }

                // Status Distribution Chart
                const statusCtxElement = document.getElementById('statusChart');
                if (statusCtxElement) {
                    const statusCtx = statusCtxElement.getContext('2d');
                    new Chart(statusCtx, {
                        type: 'doughnut',
                        data: {
                            labels: [<?php echo implode(',', array_map(function($item) { return '"' . ucwords(str_replace('_', ' ', $item['status'])) . '"'; }, $status_distribution)); ?>],
                            datasets: [{
                                data: [<?php echo implode(',', array_column($status_distribution, 'count')); ?>],
                                backgroundColor: [
                                    '#007bff',  // Blue
                                    '#28a745',  // Green
                                    '#ffc107',  // Amber
                                    '#17a2b8',  // Cyan
                                    '#6610f2'   // Purple
                                ]
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false
                        }
                    });
                }

                <?php if ($report_type === 'vehicles' || $report_type === 'overview'): ?>
                // Vehicle Utilization Chart
                const vehicleCtx = document.getElementById('vehicleChart').getContext('2d');
                new Chart(vehicleCtx, {
                    type: 'bar',
                    data: {
                        labels: [<?php echo implode(',', array_map(function($item) {
                            $label = $item['vehicle_number'];
                            if ($item['provider_type'] === 'Service Provider') {
                                $label = substr($item['brand'], 0, 15) . ' (' . $item['vehicle_number'] . ')';
                            }
                            return '"' . $label . '"';
                        }, array_slice($vehicle_usage, 0, 10))); ?>],
                        datasets: [{
                            label: 'Total Trips',
                            data: [<?php echo implode(',', array_column(array_slice($vehicle_usage, 0, 10), 'total_trips')); ?>],
                            backgroundColor: 'rgba(23, 162, 184, 0.6)',  // Cyan
                            borderColor: 'rgba(23, 162, 184, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
                <?php endif; ?>

                <?php if ($report_type === 'drivers' || $report_type === 'overview'): ?>
                // Driver Performance Chart
                const driverCtx = document.getElementById('driverChart').getContext('2d');
                new Chart(driverCtx, {
                    type: 'bar',
                    data: {
                        labels: [<?php echo implode(',', array_map(function($item) {
                            $name = substr($item['emp_name'], 0, 12);
                            if ($item['driver_type'] === 'Service Provider') {
                                $name .= ' (SP)';
                            }
                            return '"' . $name . '"';
                        }, array_slice($driver_performance, 0, 10))); ?>],
                        datasets: [{
                            label: 'Completion Rate (%)',
                            data: [<?php echo implode(',', array_column(array_slice($driver_performance, 0, 10), 'completion_rate')); ?>],
                            backgroundColor: 'rgba(40, 167, 69, 0.6)',  // Green
                            borderColor: 'rgba(40, 167, 69, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100
                            }
                        }
                    }
                });
                <?php endif; ?>

                <?php if (!empty($urgency_analysis) && count(array_filter($urgency_analysis, function($item) { return $item['count'] > 0; })) > 0): ?>
                // Urgency Analysis Chart
                const urgencyCtx = document.getElementById('urgencyChart').getContext('2d');

                // Map urgency levels to colors
                const urgencyColorMap = {
                    'urgent': '#dc3545',  // Red
                    'high': '#007bff',    // Blue
                    'medium': '#ffc107',  // Yellow
                    'low': '#28a745',     // Green
                    'normal': '#17a2b8'   // Cyan
                };

                // Get colors in the same order as the data
                const urgencyColors = [
                    <?php echo implode(',', array_map(function($item) {
                        $colors = [
                            'urgent' => "'#dc3545'",  // Red
                            'high' => "'#007bff'",    // Blue
                            'medium' => "'#ffc107'",  // Yellow
                            'low' => "'#28a745'",     // Green
                            'normal' => "'#17a2b8'"   // Cyan
                        ];
                        return $colors[strtolower($item['urgency_level'])] ?? "'#6c757d'";
                    }, $urgency_analysis)); ?>
                ];

                new Chart(urgencyCtx, {
                    type: 'pie',
                    data: {
                        labels: [<?php echo implode(',', array_map(function($item) { return '"' . ucwords($item['urgency_level']) . '"'; }, $urgency_analysis)); ?>],
                        datasets: [{
                            data: [<?php echo implode(',', array_column($urgency_analysis, 'count')); ?>],
                            backgroundColor: urgencyColors
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
                <?php endif; ?>

                <?php if ($report_type === 'locations' || $report_type === 'overview'): ?>
                <?php if (!empty($location_breakdown) && count($location_breakdown) > 0): ?>
                // Location Breakdown Chart
                const locationCtx = document.getElementById('locationChart').getContext('2d');
                new Chart(locationCtx, {
                    type: 'bar',
                    data: {
                        labels: [<?php echo implode(',', array_map(function($item) { return '"' . substr($item['location'], 0, 15) . '"'; }, array_slice($location_breakdown, 0, 10))); ?>],
                        datasets: [{
                            label: 'Total Requests',
                            data: [<?php echo implode(',', array_column(array_slice($location_breakdown, 0, 10), 'total_requests')); ?>],
                            backgroundColor: 'rgba(0, 123, 255, 0.6)',  // Professional blue
                            borderColor: 'rgba(0, 123, 255, 1)',
                            borderWidth: 1
                        }, {
                            label: 'Completed Requests',
                            data: [<?php echo implode(',', array_column(array_slice($location_breakdown, 0, 10), 'completed_requests')); ?>],
                            backgroundColor: 'rgba(40, 167, 69, 0.6)',  // Green
                            borderColor: 'rgba(40, 167, 69, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            title: {
                                display: true,
                                text: 'Transport Requests by Employee Location'
                            }
                        }
                    }
                });
                <?php endif; ?>
                <?php endif; ?>

                <?php if (!empty($department_stats) && count($department_stats) > 0): ?>
                // Department Breakdown Chart
                const departmentCtx = document.getElementById('departmentChart').getContext('2d');
                new Chart(departmentCtx, {
                    type: 'doughnut',
                    data: {
                        labels: [<?php echo implode(',', array_map(function($item) { return '"' . substr($item['department'], 0, 12) . '"'; }, array_slice($department_stats, 0, 8))); ?>],
                        datasets: [{
                            data: [<?php echo implode(',', array_column(array_slice($department_stats, 0, 8), 'requests')); ?>],
                            backgroundColor: [
                                '#007bff',  // Blue
                                '#28a745',  // Green
                                '#ffc107',  // Amber
                                '#17a2b8',  // Cyan
                                '#6f42c1',  // Purple
                                '#fd7e14',  // Orange
                                '#20c997',  // Teal
                                '#6c757d'   // Gray
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'bottom',
                                labels: {
                                    fontSize: 10,
                                    usePointStyle: true
                                }
                            },
                            title: {
                                display: true,
                                text: 'Requests by Department'
                            }
                        }
                    }
                });
                <?php endif; ?>

            } catch (error) {
                console.error('Chart initialization error:', error);
            }
        });
    </script>
    <?php endif; // End of charts tab check ?>
</body>
</html>