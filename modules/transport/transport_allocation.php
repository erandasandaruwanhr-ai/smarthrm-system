<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();


// Get user location and account type for access control
$account_type = $user['account_type'] ?? 'user';
$user_location = $user['location'] ?? $user['location_name'] ?? null;

// Debug: Check what location data we have (only for non-AJAX requests)
if (!isset($_GET['ajax'])) {
    echo "<!-- DEBUG LOCATION DATA: EPF=" . ($user['epf_number'] ?? 'null') . ", location=" . ($user['location'] ?? 'null') . ", location_name=" . ($user['location_name'] ?? 'null') . ", user_location=" . ($user_location ?? 'null') . " -->";
}

// Fallback: Get location from database if session doesn't have it
if (!$user_location && $user['epf_number']) {
    $user_location_data = $db->fetch("
        SELECT l.location_name
        FROM employees e
        LEFT JOIN locations l ON e.location_id = l.id
        WHERE e.epf_number = ?
    ", [$user['epf_number']]);

    if ($user_location_data) {
        $user_location = $user_location_data['location_name'];
        if (!isset($_GET['ajax'])) {
            echo "<!-- DEBUG: Got location from database: " . $user_location . " -->";
        }
    }
}

// Auto-update vehicle status for completed trips (based on scheduled_return time)
$auto_complete_query = "
    UPDATE vehicles v
    JOIN transport_allocations ta ON v.id = ta.vehicle_id
    SET v.status = 'available', v.updated_at = NOW()
    WHERE ta.status = 'scheduled'
    AND ta.scheduled_return IS NOT NULL
    AND ta.scheduled_return < NOW()
    AND v.status = 'in_use'
";
$db->execute($auto_complete_query);

// Handle AJAX request for checking availability
if (isset($_GET['ajax']) && $_GET['ajax'] === 'check_availability') {
    // Debug: Log AJAX request parameters
    error_log("=== AJAX AVAILABILITY CHECK ===");
    error_log("GET Parameters: " . json_encode($_GET));

    $date = $_GET['date'] ?? '';
    $return_date = $_GET['return_date'] ?? '';
    $allocation_type = $_GET['allocation_type'] ?? 'time_based';
    $departure_time = $_GET['departure_time'] ?? '';
    $return_time = $_GET['return_time'] ?? '';
    $request_id = $_GET['request_id'] ?? null;

    error_log("Parsed Parameters - Date: $date, Departure: $departure_time, Return: $return_time");

    // Get trip location and user role
    $trip_location = null;
    $user_location = $user['location'] ?? null;
    $is_superadmin = (isset($user['role']) && $user['role'] === 'superadmin');

    if ($request_id) {
        $request_details = $db->fetch("SELECT departure_location FROM transport_requests WHERE id = ?", [$request_id]);
        if ($request_details) {
            $trip_location = $request_details['departure_location'];
        }
    }

    if (empty($date)) {
        echo json_encode(['error' => 'Date is required']);
        exit;
    }

    // Debug: Log what we're checking (BEFORE conversion)
    error_log("BEFORE conversion - date: $date, departure: $departure_time, return: $return_time, type: $allocation_type");

    // Convert JavaScript datetime format (YYYY-MM-DDTHH:MM) to MySQL datetime format (YYYY-MM-DD HH:MM:SS)
    if (!empty($departure_time)) {
        $departure_time = str_replace('T', ' ', $departure_time) . ':00';
    }
    if (!empty($return_time)) {
        $return_time = str_replace('T', ' ', $return_time) . ':00';
    }

    // Debug: Log what we're checking (AFTER conversion)
    error_log("AFTER conversion - departure: $departure_time, return: $return_time");

    // Build availability query based on allocation type
    if ($allocation_type === 'full_day') {
        // For full day allocation, check for any allocation on the same date
        $vehicle_query = "
            SELECT v.*
            FROM vehicles v
            WHERE v.status IN ('available', 'in_use')  -- Check allocations, not just status
            AND v.id NOT IN (
                SELECT DISTINCT ta.vehicle_id
                FROM transport_allocations ta
                WHERE ta.status IN ('scheduled', 'in_progress')
                AND DATE(ta.scheduled_departure) = ?
            )
            ORDER BY v.vehicle_type, v.vehicle_number
        ";
        $params = [$date];
    } else {
        // For time-based allocation, check for time conflicts only if we have departure time
        if (!empty($departure_time)) {
            $vehicle_query = "
                SELECT v.*
                FROM vehicles v
                WHERE v.status IN ('available', 'in_use')  -- Vehicle can be in_use but available for other times
                AND v.id NOT IN (
                    SELECT DISTINCT ta.vehicle_id
                    FROM transport_allocations ta
                    WHERE ta.status IN ('scheduled', 'in_progress')
                    AND (
                        -- Check for time conflicts on same day
                        (DATE(ta.scheduled_departure) = ? AND (
                            -- Two time ranges overlap if: start1 < end2 AND end1 > start2
                            -- For our case: new_start < existing_end AND new_end > existing_start
                            " . (!empty($return_time) ?
                            "-- Check if new trip (with return) overlaps with existing
                            (? < COALESCE(ta.scheduled_return, DATE_ADD(ta.scheduled_departure, INTERVAL 1 DAY))
                             AND ? > ta.scheduled_departure)"
                            :
                            "-- For trips without return time, just check if departure time conflicts
                            -- New trip starts before existing ends (assuming min 1 hour for trips without return)
                            (? < COALESCE(ta.scheduled_return, DATE_ADD(ta.scheduled_departure, INTERVAL 1 HOUR))
                             AND ? >= ta.scheduled_departure)") . "
                        ))
                    )
                )
                ORDER BY v.vehicle_type, v.vehicle_number
            ";

            $params = [$date, $departure_time];
            if (!empty($return_time)) {
                // For trips with return time: new_start < existing_end AND new_end > existing_start
                $params[] = $return_time; // new_end > existing_start
            } else {
                // For trips without return: check if departure conflicts
                $params[] = $departure_time; // second use of departure time
            }
        } else {
            // Fallback to simple date-based checking
            $vehicle_query = "
                SELECT v.*
                FROM vehicles v
                WHERE v.status IN ('available', 'in_use')  -- Vehicle can be in_use but available for other times
                AND v.id NOT IN (
                    SELECT DISTINCT ta.vehicle_id
                    FROM transport_allocations ta
                    WHERE ta.status IN ('scheduled', 'in_progress')
                    AND DATE(ta.scheduled_departure) = ?
                )
                ORDER BY v.vehicle_type, v.vehicle_number
            ";
            $params = [$date];
        }
    }

    // Debug the actual query and parameters
    error_log("Vehicle query params: " . json_encode($params));
    error_log("Executing vehicle query for date=$date, departure=$departure_time, return=$return_time");

    // Show the actual SQL being executed (for debugging)
    $debug_query = $vehicle_query;
    foreach ($params as $param) {
        $debug_query = preg_replace('/\?/', "'$param'", $debug_query, 1);
    }
    error_log("Actual SQL query: " . str_replace("\n", " ", $debug_query));

    $available_vehicles = $db->fetchAll($vehicle_query, $params);

    // Log available vehicle IDs
    $vehicle_ids = array_map(function($v) { return $v['id'] . '(' . $v['vehicle_number'] . ')'; }, $available_vehicles);
    error_log("Available vehicles: " . implode(', ', $vehicle_ids));

    // Debug: Show what's blocking vehicles if in debug mode
    if (isset($_GET['debug']) && $_GET['debug'] == '1') {
        $blocking_query = "
            SELECT ta.vehicle_id, ta.scheduled_departure, ta.scheduled_return, v.vehicle_number,
                   CASE
                       WHEN ? < COALESCE(ta.scheduled_return, DATE_ADD(ta.scheduled_departure, INTERVAL 1 HOUR))
                            AND ? >= ta.scheduled_departure
                       THEN CONCAT('BLOCKS: Overlap detected (',
                                   DATE_FORMAT(ta.scheduled_departure, '%H:%i'), '-',
                                   IFNULL(DATE_FORMAT(ta.scheduled_return, '%H:%i'), 'ongoing'), ')')
                       ELSE CONCAT('Available after: ', IFNULL(DATE_FORMAT(ta.scheduled_return, '%H:%i'), 'unknown'))
                   END as conflict_reason
            FROM transport_allocations ta
            JOIN vehicles v ON ta.vehicle_id = v.id
            WHERE ta.status IN ('scheduled', 'in_progress')
            AND DATE(ta.scheduled_departure) = ?
            ORDER BY ta.scheduled_departure
        ";
        $blocking = $db->fetchAll($blocking_query, [$departure_time, $departure_time, $date]);
        error_log("Existing allocations on $date with conflict check: " . json_encode($blocking));
        error_log("Available vehicles found: " . count($available_vehicles));

        // Specifically check vehicle ID 4 (KJ-2853)
        $kj_check = "
            SELECT '4' as vehicle_id, 'KJ-2853' as vehicle_number,
                   (SELECT COUNT(*) FROM transport_allocations ta
                    WHERE ta.vehicle_id = 4
                    AND ta.status IN ('scheduled', 'in_progress')
                    AND DATE(ta.scheduled_departure) = ?
                    AND (? < COALESCE(ta.scheduled_return, DATE_ADD(ta.scheduled_departure, INTERVAL 1 HOUR))
                         AND " . (!empty($return_time) ? "? > ta.scheduled_departure" : "? >= ta.scheduled_departure") . ")
                   ) as conflicts
        ";
        $kj_result = $db->fetch($kj_check, array_merge([$date, $departure_time], !empty($return_time) ? [$return_time] : [$departure_time]));
        error_log("KJ-2853 (ID 4) conflict check: " . json_encode($kj_result));
    }

    // Build driver availability query - now includes both drivers and service providers
    // Get service providers separately from the service_providers table
    $service_provider_condition = "";
    $filter_by_location = false;

    if (!$is_superadmin) {
        // Non-superadmins need location filtering
        if ($trip_location) {
            $service_provider_condition = "AND sp.locations LIKE CONCAT('%', ?, '%')";
            $filter_by_location = true;
        } elseif ($user_location) {
            $service_provider_condition = "AND sp.locations LIKE CONCAT('%', ?, '%')";
            $trip_location = $user_location; // Use user location as filter
            $filter_by_location = true;
        }
    }
    // Superadmins see all service providers (no additional condition)

    if ($allocation_type === 'full_day') {
        // For full day allocation, check for any allocation on the same date
        // Get regular drivers who are available
        $driver_query = "
            SELECT d.id, d.emp_number, d.emp_name, d.phone, d.location, 'driver' as provider_type
            FROM drivers d
            WHERE d.status = 'active' AND d.is_on_duty = 1
            AND (d.driver_type IS NULL OR d.driver_type = 'employee')
            AND d.id NOT IN (
                SELECT DISTINCT ta.driver_id
                FROM transport_allocations ta
                WHERE ta.status IN ('scheduled', 'in_progress')
                AND DATE(ta.scheduled_departure) = ?
            )
            ORDER BY d.emp_name
        ";
        $driver_params = [$date];
    } else {
        // For time-based allocation, check for time conflicts only if we have departure time
        if (!empty($departure_time)) {
            $driver_query = "
                SELECT d.id, d.emp_number, d.emp_name, d.phone, d.location, 'driver' as provider_type
                FROM drivers d
                WHERE d.status = 'active' AND d.is_on_duty = 1
                AND (d.driver_type IS NULL OR d.driver_type = 'employee')
                AND d.id NOT IN (
                    SELECT DISTINCT ta.driver_id
                    FROM transport_allocations ta
                    WHERE ta.status IN ('scheduled', 'in_progress')
                    AND (
                        -- Check for time conflicts on same day
                        (DATE(ta.scheduled_departure) = ? AND (
                            -- Two time ranges overlap if: start1 < end2 AND end1 > start2
                            -- For our case: new_start < existing_end AND new_end > existing_start
                            " . (!empty($return_time) ?
                            "-- Check if new trip (with return) overlaps with existing
                            (? < COALESCE(ta.scheduled_return, DATE_ADD(ta.scheduled_departure, INTERVAL 1 DAY))
                             AND ? > ta.scheduled_departure)"
                            :
                            "-- For trips without return time, just check if departure time conflicts
                            -- New trip starts before existing ends (assuming min 1 hour for trips without return)
                            (? < COALESCE(ta.scheduled_return, DATE_ADD(ta.scheduled_departure, INTERVAL 1 HOUR))
                             AND ? >= ta.scheduled_departure)") . "
                        ))
                    )
                )
                ORDER BY d.emp_name
            ";

            $driver_params = [$date, $departure_time];
            if (!empty($return_time)) {
                // For trips with return time: new_start < existing_end AND new_end > existing_start
                $driver_params[] = $return_time; // new_end > existing_start
            } else {
                // For trips without return: check if departure conflicts
                $driver_params[] = $departure_time; // second use of departure time
            }
        } else {
            // Fallback to simple date-based checking
            $driver_query = "
                SELECT d.id, d.emp_number, d.emp_name, d.phone, d.location, 'driver' as provider_type
                FROM drivers d
                WHERE d.status = 'active' AND d.is_on_duty = 1
                AND (d.driver_type IS NULL OR d.driver_type = 'employee')
                AND d.id NOT IN (
                    SELECT DISTINCT ta.driver_id
                    FROM transport_allocations ta
                    WHERE ta.status IN ('scheduled', 'in_progress')
                    AND DATE(ta.scheduled_departure) = ?
                )
                ORDER BY d.emp_name
            ";
            $driver_params = [$date];
        }
    }

    // Get regular drivers
    $regular_drivers = $db->fetchAll($driver_query, $driver_params);

    // Get service providers (they're always available for multiple trips)
    // For now, let's always show all active service providers for testing
    $sp_query = "SELECT sp.id, sp.vendor_code as emp_number, sp.company_name as emp_name,
                        sp.phone, sp.locations as location, 'service_provider' as provider_type
                 FROM service_providers sp
                 WHERE sp.status = 'active'
                 ORDER BY sp.company_name";

    $sp_params = [];
    // Temporarily disable location filtering for testing
    // if ($filter_by_location && $trip_location) {
    //     $sp_params[] = $trip_location;
    // }

    $service_providers = $db->fetchAll($sp_query, $sp_params);

    // Debug logging
    error_log("=== SERVICE PROVIDER DEBUG ===");
    error_log("User role: " . ($user['role'] ?? 'unknown'));
    error_log("Is superadmin: " . ($is_superadmin ? 'true' : 'false'));
    error_log("Trip location: " . ($trip_location ?? 'null'));
    error_log("User location: " . ($user_location ?? 'null'));
    error_log("Filter by location: " . ($filter_by_location ? 'true' : 'false'));
    error_log("Service provider query: " . $sp_query);
    error_log("Service provider params: " . json_encode($sp_params));
    error_log("Service providers found: " . count($service_providers));
    error_log("Service providers data: " . json_encode($service_providers));
    error_log("Regular drivers found: " . count($regular_drivers));
    error_log("=== END DEBUG ===");

    // Combine regular drivers only (service providers will go to vehicles)
    $available_drivers = $regular_drivers;

    // Add service providers to vehicles list with special formatting
    $service_provider_vehicles = [];
    foreach ($service_providers as $sp) {
        $service_provider_vehicles[] = [
            'id' => 'sp_' . $sp['id'], // Prefix with sp_ to distinguish
            'vehicle_number' => $sp['emp_number'] . ' (Service Provider)',
            'vehicle_type' => 'Service Provider',
            'brand' => $sp['emp_name'],
            'model' => 'External Fleet',
            'seating_capacity' => 'Various',
            'location' => $sp['location'],
            'status' => 'available',
            'provider_type' => 'service_provider',
            'phone' => $sp['phone']
        ];
    }

    // Combine vehicles with service providers
    $all_vehicles = array_merge($service_provider_vehicles, $available_vehicles);

    // Debug: Log final response data
    error_log("=== FINAL AJAX RESPONSE ===");
    error_log("Available vehicles count: " . count($all_vehicles));
    error_log("Available drivers count: " . count($available_drivers));
    error_log("Service providers in vehicles: " . count($service_provider_vehicles));
    error_log("Regular vehicles: " . count($available_vehicles));

    echo json_encode([
        'vehicles' => $all_vehicles,
        'drivers' => $available_drivers
    ]);
    exit;
}

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

$message = '';

// Handle allocation submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'allocate') {
        $request_id = intval($_POST['request_id']);

        // Handle service provider vs regular vehicle/driver
        $vehicle_input = $_POST['vehicle_id'];
        $driver_input = $_POST['driver_id'] ?? '';
        $is_service_provider = false;
        $service_provider_id = null;
        $driver_id = null;
        $vehicle_id = null;

        if (strpos($vehicle_input, 'sp_') === 0) {
            // This is a service provider selected from vehicle dropdown
            $is_service_provider = true;
            $service_provider_id = intval(substr($vehicle_input, 3));
            $vehicle_id = 0; // Service providers don't use regular vehicles
            $driver_id = 0; // Service providers provide their own drivers
        } else {
            // Regular vehicle selection
            $vehicle_id = intval($vehicle_input);
            $driver_id = intval($driver_input);
        }

        $scheduled_departure = $_POST['scheduled_departure'];
        $scheduled_return = !empty($_POST['scheduled_return']) ? $_POST['scheduled_return'] : null;
        $allocation_type = $_POST['allocation_type'] ?? 'time_based';

        try {
            // Start transaction
            $db->getConnection()->beginTransaction();

            // Check if this request already has an active allocation
            $existing_allocation = $db->fetch(
                "SELECT id FROM transport_allocations
                 WHERE request_id = ? AND status IN ('scheduled', 'in_progress')",
                [$request_id]
            );

            if ($existing_allocation) {
                // If editing mode (indicated by allocation_id parameter), update existing allocation
                $allocation_id = $_POST['allocation_id'] ?? null;
                if ($allocation_id) {
                    // Update existing allocation instead of creating new one
                    if ($is_service_provider) {
                        $db->execute("UPDATE transport_allocations SET
                                     vehicle_id = ?, driver_id = 0, service_provider_id = ?,
                                     scheduled_departure = ?, scheduled_return = ?, updated_at = NOW()
                                     WHERE id = ?",
                                    [$vehicle_id, $service_provider_id, $scheduled_departure, $scheduled_return, $allocation_id]);
                    } else {
                        $db->execute("UPDATE transport_allocations SET
                                     vehicle_id = ?, driver_id = ?, service_provider_id = NULL,
                                     scheduled_departure = ?, scheduled_return = ?, updated_at = NOW()
                                     WHERE id = ?",
                                    [$vehicle_id, $driver_id, $scheduled_departure, $scheduled_return, $allocation_id]);
                    }

                    // Update transport request
                    $db->execute("UPDATE transport_requests SET
                                 allocated_vehicle_id = ?,
                                 allocated_driver_id = ?,
                                 scheduled_departure = ?,
                                 scheduled_return = ?,
                                 updated_at = NOW()
                                 WHERE id = ?",
                                [$vehicle_id, ($is_service_provider ? 0 : $driver_id), $scheduled_departure, $scheduled_return, $request_id]);

                    // Update vehicle status (only for regular vehicles, not service providers)
                    if (!$is_service_provider && $vehicle_id > 0) {
                        $db->execute("UPDATE vehicles SET status = 'in_use', updated_at = NOW() WHERE id = ?", [$vehicle_id]);
                    }

                    $db->getConnection()->commit();
                    $message = "Transport allocation updated successfully!";
                    return; // Skip the regular allocation creation process
                } else {
                    $db->getConnection()->rollBack();
                    $message = "Error: This transport request is already allocated. Please cancel the existing allocation first.";
                    throw new Exception($message);
                }
            }

            // Update transport request
            $db->execute("UPDATE transport_requests SET
                         status = 'allocated',
                         allocated_vehicle_id = ?,
                         allocated_driver_id = ?,
                         scheduled_departure = ?,
                         scheduled_return = ?,
                         allocated_at = NOW(),
                         updated_at = NOW()
                         WHERE id = ?",
                        [$vehicle_id, ($is_service_provider ? 0 : $driver_id), $scheduled_departure, $scheduled_return, $request_id]);

            // Update vehicle status (only for regular vehicles, not service providers)
            if (!$is_service_provider && $vehicle_id > 0) {
                $db->execute("UPDATE vehicles SET status = 'in_use', updated_at = NOW() WHERE id = ?", [$vehicle_id]);
            }

            // Create allocation record
            if ($is_service_provider) {
                // For service providers, set driver_id to 0 and use service_provider_id
                $db->execute("INSERT INTO transport_allocations
                             (request_id, vehicle_id, driver_id, service_provider_id, allocated_by, scheduled_departure, scheduled_return, status, created_at)
                             VALUES (?, ?, 0, ?, ?, ?, ?, 'scheduled', NOW())",
                            [$request_id, $vehicle_id, $service_provider_id, $user['id'], $scheduled_departure, $scheduled_return]);
            } else {
                // For regular drivers
                $db->execute("INSERT INTO transport_allocations
                             (request_id, vehicle_id, driver_id, service_provider_id, allocated_by, scheduled_departure, scheduled_return, status, created_at)
                             VALUES (?, ?, ?, NULL, ?, ?, ?, 'scheduled', NOW())",
                            [$request_id, $vehicle_id, $driver_id, $user['id'], $scheduled_departure, $scheduled_return]);
            }

            // Commit transaction
            $db->getConnection()->commit();

            $message = "Transport allocation completed successfully!";

            // Send notification for transport allocation
        } catch (Exception $e) {
            $db->getConnection()->rollBack();
            $message = "Error: " . $e->getMessage();
        }
    } elseif ($action === 'complete') {
        $allocation_id = intval($_POST['allocation_id']);

        try {
            // Start transaction
            $db->getConnection()->beginTransaction();

            // Get allocation details
            $allocation = $db->fetch("SELECT * FROM transport_allocations WHERE id = ?", [$allocation_id]);

            // Update allocation status to completed (no end readings required)
            $db->execute("UPDATE transport_allocations SET
                         status = 'completed',
                         actual_return = NOW(),
                         updated_at = NOW()
                         WHERE id = ?",
                        [$allocation_id]);

            // Update transport request
            $db->execute("UPDATE transport_requests SET
                         status = 'completed',
                         actual_return = NOW(),
                         completion_notes = ?,
                         updated_at = NOW()
                         WHERE id = ?",
                        [$completion_notes, $allocation['request_id']]);

            // Update vehicle status back to available (only for regular vehicles, not service providers)
            if ($allocation['vehicle_id']) {
                $db->execute("UPDATE vehicles SET status = 'available', updated_at = NOW() WHERE id = ?", [$allocation['vehicle_id']]);
            }

            // Commit transaction
            $db->getConnection()->commit();

            $message = "Transport completed successfully!";
        } catch (Exception $e) {
            $db->getConnection()->rollBack();
            $message = "Error: " . $e->getMessage();
        }
    } elseif ($action === 'start') {
        $allocation_id = intval($_POST['allocation_id']);

        try {
            // Update allocation status to in_progress (no initial readings required)
            $db->execute("UPDATE transport_allocations SET
                         status = 'in_progress',
                         updated_at = NOW()
                         WHERE id = ?",
                        [$allocation_id]);

            // Update transport request status - use 'allocated' since 'in_progress' is not valid for transport_requests
            $allocation = $db->fetch("SELECT request_id FROM transport_allocations WHERE id = ?", [$allocation_id]);
            if ($allocation) {
                $db->execute("UPDATE transport_requests SET status = 'allocated', updated_at = NOW() WHERE id = ?", [$allocation['request_id']]);
            }

            $message = "Trip started successfully!";
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    } elseif ($action === 'join_trip') {
        $allocation_id = intval($_POST['allocation_id']);
        $passenger_name = trim($_POST['passenger_name']);
        $passenger_contact = trim($_POST['passenger_contact']);

        // Validate input
        if ($allocation_id <= 0) {
            $message = "Error: Invalid allocation ID.";
        } elseif (empty($passenger_name)) {
            $message = "Error: Passenger name is required.";
        } else {

        try {
            // Check if user has already joined this trip
            $existing_passenger = $db->fetch("
                SELECT id FROM transport_passengers
                WHERE allocation_id = ? AND passenger_name = ?
            ", [$allocation_id, $passenger_name]);

            if ($existing_passenger) {
                $message = "Error: You have already joined this trip.";
            } else {
                // Get allocation and check available space (handle both regular vehicles and service providers)
                $allocation = $db->fetch("
                    SELECT ta.*, tr.passenger_count,
                           CASE
                               WHEN ta.service_provider_id IS NOT NULL THEN sp.seating_capacity
                               ELSE v.seating_capacity
                           END as seating_capacity
                    FROM transport_allocations ta
                    JOIN transport_requests tr ON ta.request_id = tr.id
                    LEFT JOIN vehicles v ON ta.vehicle_id = v.id AND ta.vehicle_id > 0
                    LEFT JOIN service_providers sp ON ta.service_provider_id = sp.id
                    WHERE ta.id = ?
                ", [$allocation_id]);

                if (!$allocation) {
                    $message = "Error: Trip not found or no longer available.";
                } elseif ($allocation['passenger_count'] >= $allocation['seating_capacity']) {
                    $message = "Error: No available space in this vehicle.";
                } else {
                    // Get a valid user ID or use NULL if current user ID doesn't exist in users table
                    $added_by_id = null;
                    if (isset($user['id'])) {
                        $user_exists = $db->fetch("SELECT id FROM users WHERE id = ?", [$user['id']]);
                        if ($user_exists) {
                            $added_by_id = $user['id'];
                        }
                    }

                    // Add passenger to allocation
                    $db->execute("
                        INSERT INTO transport_passengers (allocation_id, passenger_name, passenger_contact, added_by, created_at)
                        VALUES (?, ?, ?, ?, NOW())
                    ", [$allocation_id, $passenger_name, $passenger_contact, $added_by_id]);

                    // Update passenger count in transport request
                    $new_count = $allocation['passenger_count'] + 1;
                    $db->execute("UPDATE transport_requests SET passenger_count = ? WHERE id = ?",
                               [$new_count, $allocation['request_id']]);

                    $message = "Successfully joined the trip!";
                }
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
        }
    } elseif ($action === 'cancel') {
        $allocation_id = intval($_POST['allocation_id']);

        try {
            // Start transaction
            $db->getConnection()->beginTransaction();

            // Get allocation details
            $allocation = $db->fetch("SELECT * FROM transport_allocations WHERE id = ?", [$allocation_id]);

            // Delete the allocation record
            $db->execute("DELETE FROM transport_allocations WHERE id = ?", [$allocation_id]);

            // Delete the transport request completely (this is what user wants - complete cancellation)
            $db->execute("DELETE FROM transport_requests WHERE id = ?", [$allocation['request_id']]);

            // Delete any related transport passengers records
            $db->execute("DELETE FROM transport_passengers WHERE allocation_id = ?", [$allocation_id]);

            // Update vehicle status back to available (only for regular vehicles, not service providers)
            if ($allocation['vehicle_id'] && $allocation['vehicle_id'] > 0) {
                $db->execute("UPDATE vehicles SET status = 'available', updated_at = NOW() WHERE id = ?", [$allocation['vehicle_id']]);
            }

            // Commit transaction
            $db->getConnection()->commit();

            $message = "Transport request cancelled and deleted successfully!";
        } catch (Exception $e) {
            $db->getConnection()->rollBack();
            $message = "Error: " . $e->getMessage();
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$date_filter = $_GET['date'] ?? ''; // Keep for backward compatibility
$date_from_filter = $_GET['date_from'] ?? '';
$date_to_filter = $_GET['date_to'] ?? '';

// Debug: Log filter values
error_log("Current Allocations Debug - Status filter: " . $status_filter);
error_log("Current Allocations Debug - Date from filter: '" . $date_from_filter . "'");
error_log("Current Allocations Debug - Date to filter: '" . $date_to_filter . "'");
error_log("Current Allocations Debug - Legacy date filter: '" . $date_filter . "'");
error_log("Current Allocations Debug - User location: " . ($user_location ?? 'not set'));
error_log("Current Allocations Debug - Account type: " . $account_type);

// Get pending requests for allocation - transport module is public
$pending_requests = $db->fetchAll("
    SELECT tr.*
    FROM transport_requests tr
    WHERE tr.status = 'pending'
    ORDER BY tr.urgency_level DESC, tr.created_at ASC
", []);

// Note: Vehicle and driver availability will be checked dynamically based on requested date
// This is a placeholder query - actual availability is determined by JavaScript based on selected date
$all_vehicles = $db->fetchAll("
    SELECT * FROM vehicles
    WHERE status = 'available'
    ORDER BY vehicle_type, vehicle_number
");

$all_drivers = $db->fetchAll("
    SELECT * FROM drivers
    WHERE status = 'active' AND is_on_duty = 1
    ORDER BY emp_name
");

// Initial empty arrays - will be populated by JavaScript based on selected date
$available_vehicles = [];
$available_drivers = [];

// Get available rides (scheduled trips with space for ride sharing) with admin location filtering
// Check if current user has already joined each trip
$current_user_name = $user['name'] ?? '';
$rides_condition = "ta.status = 'scheduled'
    AND (
        (ta.service_provider_id IS NOT NULL AND tr.passenger_count < sp.seating_capacity) -- Service providers use their capacity
        OR
        (v.id IS NOT NULL AND tr.passenger_count < v.seating_capacity) -- Regular vehicles
    )
    AND (
        (ta.service_provider_id IS NOT NULL AND (sp.seating_capacity - tr.passenger_count) > 0)
        OR
        (v.id IS NOT NULL AND (v.seating_capacity - tr.passenger_count) > 0)
    )
    AND DATE(ta.scheduled_departure) >= CURDATE()";

$rides_params = [$current_user_name];

// Transport module is public - no location restrictions for available rides

$available_rides = $db->fetchAll("
    SELECT ta.*,
           tr.departure_location, tr.destination, tr.departure_date, tr.departure_time, tr.passenger_count,
           CASE
               WHEN ta.service_provider_id IS NOT NULL THEN CONCAT(sp.vendor_code, ' (Service Provider)')
               ELSE v.vehicle_number
           END as vehicle_number,
           CASE
               WHEN ta.service_provider_id IS NOT NULL THEN sp.seating_capacity
               ELSE v.seating_capacity
           END as seating_capacity,
           CASE
               WHEN ta.service_provider_id IS NOT NULL THEN 'Service Provider'
               ELSE v.vehicle_type
           END as vehicle_type,
           CASE
               WHEN ta.service_provider_id IS NOT NULL THEN sp.company_name
               ELSE v.brand
           END as brand,
           CASE
               WHEN ta.service_provider_id IS NOT NULL THEN 'External Fleet'
               ELSE v.model
           END as model,
           COALESCE(d.emp_name, sp.company_name) as driver_name,
           COALESCE(d.phone, sp.phone) as driver_phone,
           -- Calculate actual occupied seats: original passenger count + ride-share passengers
           (tr.passenger_count + COALESCE(passengers_joined.total_joined, 0)) as actual_occupied,
           CASE
               WHEN ta.service_provider_id IS NOT NULL THEN (sp.seating_capacity - (tr.passenger_count + COALESCE(passengers_joined.total_joined, 0)))
               ELSE (v.seating_capacity - (tr.passenger_count + COALESCE(passengers_joined.total_joined, 0)))
           END as available_space,
           CASE
               WHEN tp.id IS NOT NULL THEN 1
               ELSE 0
           END as user_already_joined
    FROM transport_allocations ta
    JOIN transport_requests tr ON ta.request_id = tr.id
    LEFT JOIN vehicles v ON ta.vehicle_id = v.id AND ta.vehicle_id > 0
    LEFT JOIN drivers d ON ta.driver_id = d.id AND ta.driver_id > 0
    LEFT JOIN service_providers sp ON ta.service_provider_id = sp.id
    LEFT JOIN transport_passengers tp ON ta.id = tp.allocation_id
        AND tp.passenger_name = ?
    LEFT JOIN (
        SELECT allocation_id, COUNT(*) as total_joined
        FROM transport_passengers
        GROUP BY allocation_id
    ) passengers_joined ON ta.id = passengers_joined.allocation_id
    WHERE $rides_condition
    ORDER BY ta.scheduled_departure ASC
", $rides_params);

// Build allocation query with filters and admin location restrictions
$where_conditions = ["ta.status != 'cancelled'"];
$params = [];

// Transport module is public - no location restrictions for current allocations

if ($status_filter !== 'all') {
    $where_conditions[] = 'ta.status = ?';
    $params[] = $status_filter;
}

// Handle date range filtering
if (!empty($date_from_filter) && !empty($date_to_filter)) {
    // Both from and to dates specified
    $where_conditions[] = 'DATE(ta.scheduled_departure) BETWEEN ? AND ?';
    $params[] = $date_from_filter;
    $params[] = $date_to_filter;
} elseif (!empty($date_from_filter)) {
    // Only from date specified
    $where_conditions[] = 'DATE(ta.scheduled_departure) >= ?';
    $params[] = $date_from_filter;
} elseif (!empty($date_to_filter)) {
    // Only to date specified
    $where_conditions[] = 'DATE(ta.scheduled_departure) <= ?';
    $params[] = $date_to_filter;
} elseif ($date_filter && $date_filter !== '') {
    // Legacy single date filter (for backward compatibility)
    $where_conditions[] = 'DATE(ta.scheduled_departure) = ?';
    $params[] = $date_filter;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Debug: Log the final query
error_log("Current Allocations Debug - Where clause: " . $where_clause);
error_log("Current Allocations Debug - Query params: " . json_encode($params));

// Get current allocations with comprehensive request details
$allocations = $db->fetchAll("
    SELECT ta.*,
           tr.purpose, tr.departure_location, tr.destination, tr.departure_date, tr.departure_time,
           tr.return_date, tr.return_time, tr.passenger_count, tr.passenger_names,
           tr.special_requirements, tr.urgency_level, tr.request_type,
           tr.emp_name as requester_name, tr.emp_number as requester_emp_number,
           tr.emp_location as requester_location,
           v.vehicle_number, v.vehicle_type, v.brand, v.model, v.seating_capacity,
           COALESCE(d.emp_name, sp.company_name) as driver_name,
           COALESCE(d.emp_number, sp.vendor_code) as driver_emp_number,
           COALESCE(d.license_number, 'Service Provider') as license_number,
           COALESCE(d.phone, sp.phone) as driver_phone
    FROM transport_allocations ta
    JOIN transport_requests tr ON ta.request_id = tr.id
    LEFT JOIN vehicles v ON ta.vehicle_id = v.id
    LEFT JOIN drivers d ON ta.driver_id = d.id
    LEFT JOIN service_providers sp ON ta.service_provider_id = sp.id
    $where_clause
    ORDER BY ta.scheduled_departure DESC
", $params);

// Debug: Log the allocations found
error_log("Current Allocations Debug - Number of allocations found: " . count($allocations));
if (!empty($allocations)) {
    foreach ($allocations as $index => $alloc) {
        error_log("Allocation $index: ID=" . $alloc['id'] . ", Status=" . $alloc['status'] . ", Date=" . $alloc['scheduled_departure']);
    }
}

// Get statistics with admin location filtering
$stats_condition = '';
$stats_params = [];

// Transport module is public - no location restrictions for statistics

$stats = [
    'pending' => count($pending_requests),
    'scheduled' => $db->fetch("SELECT COUNT(*) as count FROM transport_allocations ta WHERE ta.status = 'scheduled'", [])['count'] ?? 0,
    'in_progress' => $db->fetch("SELECT COUNT(*) as count FROM transport_allocations ta WHERE ta.status = 'in_progress'", [])['count'] ?? 0,
    'completed_today' => $db->fetch("SELECT COUNT(*) as count FROM transport_allocations ta WHERE ta.status = 'completed' AND DATE(ta.updated_at) = CURDATE()", [])['count'] ?? 0
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Transport Allocation</title>
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

        .allocation-form-step {
            background: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-left: 4px solid #007bff;
        }
        .allocation-form-step h6 {
            color: #007bff;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        .vehicle-option {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .vehicle-option:hover {
            border-color: #007bff;
            background: #f8f9fa;
        }
        .vehicle-option.selected {
            border-color: #007bff;
            background: #e7f3ff;
        }
        .time-section {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
        }
        .time-display {
            font-size: 1.1rem;
            font-weight: 600;
            color: #495057;
        }

        /* Tab Styling */
        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
        }

        .nav-tabs .nav-link {
            border: none;
            border-bottom: 3px solid transparent;
            color: #6c757d;
            font-weight: 500;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .nav-tabs .nav-link:hover {
            border-bottom-color: #007bff;
            color: #007bff;
            background-color: rgba(0, 123, 255, 0.1);
        }

        .nav-tabs .nav-link.active {
            color: #007bff;
            border-bottom-color: #007bff;
            background-color: rgba(0, 123, 255, 0.1);
            font-weight: 600;
        }

        .nav-tabs .badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
        }

        .tab-content {
            min-height: 400px;
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

            .nav-tabs .nav-link {
                padding: 0.5rem 0.75rem;
                font-size: 0.9rem;
            }

            .nav-tabs .nav-link i {
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
                        <h1><i class="fas fa-tasks me-3"></i>Transport Allocation</h1>
                        <p>Assign vehicles and drivers to transport requests</p>
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
                    <li class="breadcrumb-item active">Transport Allocation</li>
                </ol>
            </nav>

        <?php if ($message): ?>
        <div class="alert alert-<?php echo strpos($message, 'Error') !== false ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="icon" style="background-color: rgba(255, 193, 7, 0.1); color: #ffc107;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h4><?php echo number_format($stats['pending']); ?></h4>
                    <p>Pending Requests</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="icon" style="background-color: rgba(0, 123, 255, 0.1); color: #007bff;">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h4><?php echo number_format($stats['scheduled']); ?></h4>
                    <p>Scheduled</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="icon" style="background-color: rgba(23, 162, 184, 0.1); color: #17a2b8;">
                        <i class="fas fa-route"></i>
                    </div>
                    <h4><?php echo number_format($stats['in_progress']); ?></h4>
                    <p>In Progress</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="icon" style="background-color: rgba(40, 167, 69, 0.1); color: #28a745;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h4><?php echo number_format($stats['completed_today']); ?></h4>
                    <p>Completed Today</p>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <ul class="nav nav-tabs nav-fill mb-4" id="transportTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending-allocation" type="button" role="tab">
                    <i class="fas fa-clock me-2"></i>Pending Allocation
                    <?php if (!empty($pending_requests)): ?>
                        <span class="badge bg-danger ms-1"><?php echo count($pending_requests); ?></span>
                    <?php endif; ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="rides-tab" data-bs-toggle="tab" data-bs-target="#available-rides" type="button" role="tab">
                    <i class="fas fa-users me-2"></i>Available Rides
                    <?php if (!empty($available_rides)): ?>
                        <span class="badge bg-success ms-1"><?php echo count($available_rides); ?></span>
                    <?php endif; ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="current-tab" data-bs-toggle="tab" data-bs-target="#current-allocations" type="button" role="tab">
                    <i class="fas fa-route me-2"></i>Current Allocations
                    <span class="badge bg-primary ms-1"><?php echo count($allocations); ?></span>
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="transportTabsContent">

            <!-- Pending Allocation Tab -->
            <div class="tab-pane fade show active" id="pending-allocation" role="tabpanel" aria-labelledby="pending-tab">
                <!-- Pending Requests for Allocation -->
        <?php if (!empty($pending_requests)): ?>
        <div class="card border-0 shadow mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Pending Allocation (<?php echo count($pending_requests); ?>)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Request Details</th>
                                <th>Employee</th>
                                <th>Schedule</th>
                                <th>Passengers</th>
                                <th>Urgency</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_requests as $request): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($request['departure_location']); ?></strong>
                                    <i class="fas fa-arrow-right mx-2 text-muted"></i>
                                    <strong><?php echo htmlspecialchars($request['destination']); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($request['purpose']); ?></small>
                                    <br>
                                    <?php if ($request['request_type'] == 'one_way'): ?>
                                        <span class="badge bg-info"><i class="fas fa-arrow-right me-1"></i>One Way</span>
                                    <?php elseif ($request['request_type'] == 'round_trip'): ?>
                                        <span class="badge bg-primary"><i class="fas fa-exchange-alt me-1"></i>Round Trip</span>
                                    <?php elseif ($request['request_type'] == 'multi_destination'): ?>
                                        <span class="badge bg-warning text-dark"><i class="fas fa-map-marked-alt me-1"></i>Multi-Stop</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($request['emp_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($request['emp_number']); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo date('M d, Y', strtotime($request['departure_date'])); ?></strong><br>
                                    <small class="text-muted"><?php echo date('H:i', strtotime($request['departure_time'])); ?></small>
                                </td>
                                <td><?php echo $request['passenger_count']; ?></td>
                                <td>
                                    <?php
                                    $urgency_colors = [
                                        'low' => 'secondary',
                                        'medium' => 'info',
                                        'high' => 'warning',
                                        'urgent' => 'danger'
                                    ];
                                    $color = $urgency_colors[$request['urgency_level']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst($request['urgency_level']); ?></span>
                                </td>
                                <td>
                                    <?php
                                    // Only admin and superadmin can allocate transport
                                    $can_allocate = in_array($account_type, ['admin', 'superadmin']);

                                    // Admin users can only allocate requests from employees in their location
                                    if ($can_allocate && $account_type === 'admin' && $user_location) {
                                        $can_allocate = ($request['emp_location'] === $user_location);

                                        // Debug: Add HTML comment to see the logic (only for non-AJAX requests)
                                        if (!isset($_GET['ajax'])) {
                                            echo "<!-- DEBUG: User EPF: " . $user['epf_number'] . ", User Location: " . $user_location . ", Employee Location: " . $request['emp_location'] . ", Can Allocate: " . ($can_allocate ? 'YES' : 'NO') . " -->";
                                        }
                                    }

                                    if ($can_allocate): ?>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="allocateTransport(<?php echo $request['id']; ?>)">
                                        <i class="fas fa-plus me-1"></i>Allocate
                                    </button>
                                    <?php else: ?>
                                    <span class="text-muted">
                                        <i class="fas fa-lock me-1"></i>Restricted
                                    </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
            </div>
            <!-- End Pending Allocation Tab -->

            <!-- Available Rides Tab -->
            <div class="tab-pane fade" id="available-rides" role="tabpanel" aria-labelledby="rides-tab">
                <!-- Available Rides for Ride Sharing - Available to ALL users -->
        <div class="card border-0 shadow mb-4">
            <div class="card-header bg-success text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>Available Rides - Join Existing Trips
                        <?php if (!empty($available_rides)): ?>
                            <span class="badge bg-light text-success ms-2"><?php echo count($available_rides); ?> Available</span>
                        <?php endif; ?>
                    </h5>
                    <div class="text-end">
                        <small><i class="fas fa-info-circle me-1"></i>Find rides to share and save costs</small>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($available_rides)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-car-side fa-3x text-muted mb-3 d-block"></i>
                        <h6 class="text-muted">No Available Rides</h6>
                        <p class="text-muted small">There are currently no scheduled trips with available seats for ride sharing.</p>
                        <small class="text-muted">Check back later or submit your own transport request.</small>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th><i class="fas fa-route me-1"></i>Route</th>
                                    <th><i class="fas fa-car me-1"></i>Vehicle & Driver</th>
                                    <th><i class="fas fa-calendar me-1"></i>Schedule</th>
                                    <th><i class="fas fa-users me-1"></i>Available Space</th>
                                    <th><i class="fas fa-hand-paper me-1"></i>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($available_rides as $ride): ?>
                                <tr>
                                    <td>
                                        <div class="route-info">
                                            <strong class="text-primary"><?php echo htmlspecialchars($ride['departure_location']); ?></strong>
                                            <i class="fas fa-arrow-right mx-2 text-success"></i>
                                            <strong class="text-primary"><?php echo htmlspecialchars($ride['destination']); ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($ride['vehicle_number']); ?></strong>
                                            <span class="badge bg-secondary ms-1"><?php echo htmlspecialchars($ride['vehicle_type']); ?></span>
                                        </div>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-user me-1"></i>Driver: <?php echo htmlspecialchars($ride['driver_name']); ?>
                                            <?php if ($ride['driver_phone']): ?>
                                                <br><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($ride['driver_phone']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div>
                                            <strong class="text-dark"><?php echo date('M d, Y', strtotime($ride['departure_date'])); ?></strong>
                                        </div>
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i><?php echo date('g:i A', strtotime($ride['departure_time'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php
                                        $space = $ride['available_space'];
                                        $occupied = $ride['actual_occupied'];
                                        $total = $ride['seating_capacity'];
                                        ?>
                                        <?php if ($space > 0): ?>
                                            <span class="badge bg-success fs-6">
                                                <i class="fas fa-check-circle me-1"></i><?php echo $space; ?> seat<?php echo $space > 1 ? 's' : ''; ?> available
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger fs-6">
                                                <i class="fas fa-times-circle me-1"></i>Full
                                            </span>
                                        <?php endif; ?>
                                        <div class="mt-1">
                                            <small class="text-muted">
                                                <i class="fas fa-users me-1"></i><?php echo $occupied; ?>/<?php echo $total; ?> occupied
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($ride['user_already_joined']): ?>
                                            <button type="button" class="btn btn-info btn-sm" disabled>
                                                <i class="fas fa-check-circle me-1"></i>Already Joined
                                            </button>
                                        <?php elseif ($space > 0): ?>
                                            <button type="button" class="btn btn-success btn-sm fw-bold" onclick="joinTrip(<?php echo $ride['id']; ?>)">
                                                <i class="fas fa-plus-circle me-1"></i>Join Trip
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-secondary btn-sm" disabled>
                                                <i class="fas fa-ban me-1"></i>Full
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
            </div>
            <!-- End Available Rides Tab -->

            <!-- Current Allocations Tab -->
            <div class="tab-pane fade" id="current-allocations" role="tabpanel" aria-labelledby="current-tab">
                <!-- Current Allocations -->
        <div class="card border-0 shadow">
            <div class="card-header bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Current Allocations</h5>
                        <?php if ($status_filter !== 'all' || !empty($date_from_filter) || !empty($date_to_filter) || !empty($date_filter)): ?>
                        <small class="text-light">
                            <i class="fas fa-filter me-1"></i>Filtered:
                            <?php if ($status_filter !== 'all'): ?>Status: <?php echo ucfirst($status_filter); ?> <?php endif; ?>
                            <?php if (!empty($date_from_filter) && !empty($date_to_filter)): ?>
                                Date: <?php echo date('M d, Y', strtotime($date_from_filter)); ?> - <?php echo date('M d, Y', strtotime($date_to_filter)); ?>
                            <?php elseif (!empty($date_from_filter)): ?>
                                Date: From <?php echo date('M d, Y', strtotime($date_from_filter)); ?>
                            <?php elseif (!empty($date_to_filter)): ?>
                                Date: To <?php echo date('M d, Y', strtotime($date_to_filter)); ?>
                            <?php elseif (!empty($date_filter)): ?>
                                Date: <?php echo date('M d, Y', strtotime($date_filter)); ?>
                            <?php endif; ?>
                        </small>
                        <?php endif; ?>
                    </div>

                    <!-- Filters -->
                    <form method="GET" class="d-flex flex-wrap gap-2 align-items-center">
                        <select class="form-select form-select-sm" name="status" style="width: auto;">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="scheduled" <?php echo $status_filter === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                            <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                        <div class="d-flex gap-1 align-items-center">
                            <label class="form-label mb-0 text-white small">From:</label>
                            <input type="date" class="form-control form-control-sm" name="date_from" value="<?php echo htmlspecialchars($date_from_filter); ?>" style="width: 140px;">
                        </div>
                        <div class="d-flex gap-1 align-items-center">
                            <label class="form-label mb-0 text-white small">To:</label>
                            <input type="date" class="form-control form-control-sm" name="date_to" value="<?php echo htmlspecialchars($date_to_filter); ?>" style="width: 140px;">
                        </div>
                        <button type="submit" class="btn btn-sm btn-light">Filter</button>
                        <a href="?<?php echo http_build_query(array_filter($_GET, function($key) { return !in_array($key, ['status', 'date', 'date_from', 'date_to']); }, ARRAY_FILTER_USE_KEY)); ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
                    </form>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Trip Details</th>
                                <th>Vehicle & Driver</th>
                                <th>Schedule</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($allocations)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    <i class="fas fa-calendar-times fa-3x text-muted mb-3 d-block"></i>
                                    <span class="text-muted">No allocations found for the selected criteria</span>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($allocations as $allocation): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($allocation['departure_location']); ?></strong>
                                        <i class="fas fa-arrow-right mx-2 text-muted"></i>
                                        <strong><?php echo htmlspecialchars($allocation['destination']); ?></strong>
                                        <br><small class="text-muted">
                                            <?php echo htmlspecialchars($allocation['requester_name']); ?> (<?php echo $allocation['passenger_count']; ?> passengers)
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($allocation['service_provider_id']): ?>
                                            <strong class="text-warning"><i class="fas fa-truck me-1"></i><?php echo htmlspecialchars($allocation['driver_name']); ?></strong>
                                            <small class="text-muted d-block">Service Provider</small>
                                            <small class="text-muted d-block"><?php echo htmlspecialchars($allocation['driver_emp_number']); ?></small>
                                            <small class="text-muted d-block"><?php echo htmlspecialchars($allocation['driver_phone'] ?: 'N/A'); ?></small>
                                        <?php else: ?>
                                            <strong><?php echo htmlspecialchars($allocation['vehicle_number']); ?></strong>
                                            <small class="text-muted d-block"><?php echo htmlspecialchars($allocation['brand'] . ' ' . $allocation['model']); ?></small>
                                            <strong><?php echo htmlspecialchars($allocation['driver_name']); ?></strong>
                                            <small class="text-muted d-block"><?php echo htmlspecialchars($allocation['driver_phone'] ?: 'N/A'); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo date('M d, Y H:i', strtotime($allocation['scheduled_departure'])); ?></strong>
                                        <?php if ($allocation['scheduled_return']): ?>
                                        <br><small class="text-muted">Return: <?php echo date('M d, Y H:i', strtotime($allocation['scheduled_return'])); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_colors = [
                                            'scheduled' => 'primary',
                                            'in_progress' => 'info',
                                            'completed' => 'success',
                                            'cancelled' => 'danger'
                                        ];
                                        $color = $status_colors[$allocation['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?>"><?php echo ucwords(str_replace('_', ' ', $allocation['status'])); ?></span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php
                                            // Check if current user is the assigned driver
                                            // For service providers, only admins can manage
                                            $is_assigned_driver = false;
                                            if (!$allocation['service_provider_id']) {
                                                $is_assigned_driver = ($user['epf_number'] ?? '') === ($allocation['driver_emp_number'] ?? '');
                                            }

                                            // Permission-based button visibility with location restrictions
                                            $can_view_details = hasModulePermission($db, 'transport.details_button');

                                            // Edit and Cancel: Only admin and superadmin can manage allocations
                                            $can_edit = in_array($account_type, ['admin', 'superadmin']);
                                            $can_cancel = in_array($account_type, ['admin', 'superadmin']);

                                            if (($can_edit || $can_cancel) && $account_type === 'admin' && $user_location) {
                                                $can_edit = $can_edit && ($allocation['departure_location'] === $user_location);
                                                $can_cancel = $can_cancel && ($allocation['departure_location'] === $user_location);
                                            }

                                            // Start/Complete: Only admin/superadmin (location-based) OR assigned driver
                                            $can_start_admin = ($account_type === 'superadmin' ||
                                                              ($account_type === 'admin' && $user_location && $allocation['departure_location'] === $user_location));

                                            $can_start = ($can_start_admin || $is_assigned_driver);
                                            ?>

                                            <?php if ($can_view_details): ?>
                                                <button type="button" class="btn btn-info" onclick="viewDetails(<?php echo $allocation['id']; ?>)" title="View Request Details">
                                                    <i class="fas fa-eye"></i> Details
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($can_edit && $allocation['status'] === 'scheduled'): ?>
                                                <button type="button" class="btn btn-warning" onclick="editAllocation(<?php echo $allocation['id']; ?>)" title="Edit Allocation">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($allocation['status'] === 'scheduled'): ?>
                                                <?php if ($can_start): ?>
                                                    <button type="button" class="btn btn-success" onclick="startTrip(<?php echo $allocation['id']; ?>)">
                                                        <i class="fas fa-play"></i> Start
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($can_cancel): ?>
                                                    <button type="button" class="btn btn-danger" onclick="cancelAllocation(<?php echo $allocation['id']; ?>)">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </button>
                                                <?php endif; ?>
                                            <?php elseif ($allocation['status'] === 'in_progress'): ?>
                                                <?php if ($can_start): ?>
                                                    <button type="button" class="btn btn-warning" onclick="completeTrip(<?php echo $allocation['id']; ?>)">
                                                        <i class="fas fa-check"></i> Complete
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
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

    <!-- Allocation Modal -->
    <div class="modal fade" id="allocationModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-plus-circle me-2"></i>Allocate Transport
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="allocationForm">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="allocate">
                        <input type="hidden" name="request_id" id="allocateRequestId">
                        <input type="hidden" name="scheduled_departure" id="scheduledDepartureHidden">
                        <input type="hidden" name="scheduled_return" id="scheduledReturnHidden">
                        <input type="hidden" name="allocation_type" id="allocationTypeHidden">

                        <!-- Trip Type Indicator -->
                        <div class="alert alert-info mb-4" id="tripTypeIndicator" style="display:none;">
                            <i class="fas fa-info-circle me-2"></i>
                            <span id="tripTypeMessage"></span>
                        </div>


                        <!-- Step 1: Vehicle & Driver Selection -->
                        <div class="allocation-form-step">
                            <h6><i class="fas fa-car me-2"></i>Step 1: Select Vehicle & Driver</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-car me-2 text-primary"></i>Vehicle *
                                    </label>
                                    <select class="form-select form-select-lg" name="vehicle_id" required id="vehicleSelect">
                                        <option value="">Choose a vehicle...</option>
                                        <?php foreach ($all_vehicles as $vehicle): ?>
                                        <option value="<?php echo $vehicle['id']; ?>"
                                                data-type="<?php echo htmlspecialchars($vehicle['vehicle_type']); ?>"
                                                data-capacity="<?php echo $vehicle['seating_capacity']; ?>"
                                                data-brand="<?php echo htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']); ?>">
                                            <?php echo htmlspecialchars($vehicle['vehicle_number']); ?> -
                                            <?php echo htmlspecialchars($vehicle['vehicle_type']); ?>
                                            (<?php echo $vehicle['seating_capacity']; ?> seats)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text" id="vehicleAvailabilityText">
                                        <i class="fas fa-info-circle me-1"></i>Select a date first to see available vehicles
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-user-tie me-2 text-success"></i>Driver *
                                    </label>
                                    <select class="form-select form-select-lg" name="driver_id" required id="driverSelect">
                                        <option value="">Choose a driver or service provider...</option>
                                        <?php
                                        // Group drivers by type
                                        $service_providers = array_filter($all_drivers, function($d) { return $d['driver_type'] === 'service_provider'; });
                                        $regular_drivers = array_filter($all_drivers, function($d) { return $d['driver_type'] !== 'service_provider'; });
                                        ?>

                                        <?php if (!empty($service_providers)): ?>
                                        <optgroup label="🚚 Service Providers (Multiple Trips Allowed)">
                                            <?php foreach ($service_providers as $provider): ?>
                                            <option value="<?php echo $provider['id']; ?>"
                                                    data-type="service_provider"
                                                    data-phone="<?php echo htmlspecialchars($provider['phone'] ?? ''); ?>">
                                                🚚 <?php echo htmlspecialchars($provider['emp_name']); ?>
                                                <?php if ($provider['contact_person']): ?>(Contact: <?php echo htmlspecialchars($provider['contact_person']); ?>)<?php endif; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <?php endif; ?>

                                        <?php if (!empty($regular_drivers)): ?>
                                        <optgroup label="👤 Company Drivers">
                                            <?php foreach ($regular_drivers as $driver): ?>
                                            <option value="<?php echo $driver['id']; ?>"
                                                    data-type="driver"
                                                    data-license="<?php echo htmlspecialchars($driver['license_type'] ?? 'N/A'); ?>"
                                                    data-phone="<?php echo htmlspecialchars($driver['phone'] ?? ''); ?>">
                                                <?php echo htmlspecialchars($driver['emp_name']); ?> -
                                                <?php echo htmlspecialchars($driver['license_type'] ?? 'General'); ?>
                                                <?php if ($driver['phone']): ?>(<?php echo htmlspecialchars($driver['phone']); ?>)<?php endif; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <?php endif; ?>
                                    </select>
                                    <div class="form-text" id="driverAvailabilityText">
                                        <i class="fas fa-info-circle me-1"></i>Select a date first to see available drivers
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Schedule -->
                        <div class="allocation-form-step">
                            <h6><i class="fas fa-calendar-alt me-2"></i>Step 2: Set Schedule</h6>
                            <div class="alert alert-info mb-3">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Note:</strong> Times are pre-filled from the original request. You can modify them if needed.
                            </div>

                            <div class="row">
                                <!-- Departure Time -->
                                <div class="col-md-6">
                                    <div class="time-section">
                                        <label class="form-label fw-bold text-success">
                                            <i class="fas fa-play me-2"></i>Departure Time *
                                        </label>

                                        <div class="row mb-3">
                                            <div class="col-6">
                                                <label class="form-label small">Date</label>
                                                <input type="date" class="form-control" id="departureDate" required min="<?php echo date('Y-m-d'); ?>">
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label small">Time</label>
                                                <div class="row">
                                                    <div class="col-4">
                                                        <select class="form-select form-select-sm" id="departureHour" required>
                                                            <option value="">Hr</option>
                                                            <?php for ($h = 1; $h <= 12; $h++): ?>
                                                                <option value="<?php echo $h; ?>"><?php echo $h; ?></option>
                                                            <?php endfor; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-4">
                                                        <select class="form-select form-select-sm" id="departureMinute" required>
                                                            <option value="">Min</option>
                                                            <option value="0">00</option>
                                                            <option value="15">15</option>
                                                            <option value="30">30</option>
                                                            <option value="45">45</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-4">
                                                        <select class="form-select form-select-sm" id="departureAmPm" required>
                                                            <option value="">AM/PM</option>
                                                            <option value="AM">AM</option>
                                                            <option value="PM">PM</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="alert alert-light mb-0">
                                            <div class="time-display" id="departureDisplay">
                                                <i class="fas fa-clock me-2"></i>Select date and time
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Allocation Type -->
                                <div class="col-12 mb-4">
                                    <div class="card border-warning">
                                        <div class="card-header bg-warning bg-opacity-10">
                                            <h6 class="card-title mb-0">
                                                <i class="fas fa-calendar-alt me-2"></i>Allocation Type
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="allocationType" id="fullDayAllocation" value="full_day">
                                                        <label class="form-check-label" for="fullDayAllocation">
                                                            <strong>Full Day Allocation</strong>
                                                            <small class="text-muted d-block">Vehicle/Driver will be unavailable for the entire day</small>
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="allocationType" id="timeBasedAllocation" value="time_based" checked>
                                                        <label class="form-check-label" for="timeBasedAllocation">
                                                            <strong>Time-Based Allocation</strong>
                                                            <small class="text-muted d-block">Specify exact departure and return times</small>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Return Time -->
                                <div class="col-md-6">
                                    <div class="time-section">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <label class="form-label fw-bold text-primary mb-0">
                                                <i class="fas fa-stop me-2"></i>Return Time (Optional)
                                            </label>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="enableReturnTime">
                                                <label class="form-check-label small" for="enableReturnTime">
                                                    Enable return allocation
                                                </label>
                                            </div>
                                        </div>

                                        <div id="returnTimeFields" style="opacity: 0.5; pointer-events: none;">
                                            <div class="row mb-3">
                                                <div class="col-6">
                                                    <label class="form-label small">Date</label>
                                                    <input type="date" class="form-control" id="returnDate" min="<?php echo date('Y-m-d'); ?>">
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small">Time</label>
                                                    <div class="row">
                                                        <div class="col-4">
                                                            <select class="form-select form-select-sm" id="returnHour">
                                                                <option value="">Hr</option>
                                                                <?php for ($h = 1; $h <= 12; $h++): ?>
                                                                    <option value="<?php echo $h; ?>"><?php echo $h; ?></option>
                                                                <?php endfor; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-4">
                                                            <select class="form-select form-select-sm" id="returnMinute">
                                                                <option value="">Min</option>
                                                                <option value="0">00</option>
                                                                <option value="15">15</option>
                                                                <option value="30">30</option>
                                                                <option value="45">45</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-4">
                                                            <select class="form-select form-select-sm" id="returnAmPm">
                                                                <option value="">AM/PM</option>
                                                                <option value="AM">AM</option>
                                                                <option value="PM">PM</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="alert alert-light mb-0">
                                                <div class="time-display" id="returnDisplay">
                                                    <i class="fas fa-clock me-2"></i>Toggle switch to enable return time
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check me-2"></i>Allocate Transport
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Complete Trip Modal removed - now completes directly -->

    <!-- Start Trip Modal removed - now starts directly -->

    <!-- Join Trip Modal -->
    <div class="modal fade" id="joinTripModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Join Existing Trip</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="join_trip">
                        <input type="hidden" name="allocation_id" id="joinAllocationId">

                        <div class="mb-3">
                            <label class="form-label">Passenger Name *</label>
                            <input type="text" class="form-control" name="passenger_name" required
                                   value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>"
                                   placeholder="Full name of passenger">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="tel" class="form-control" name="passenger_contact"
                                   placeholder="Phone number for contact">
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> By joining this trip, you will be added to the passenger list.
                            Please coordinate with the driver for pickup location and any special instructions.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Join Trip</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Request Details Modal -->
    <div class="modal fade" id="requestDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Transport Request Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="requestDetailsContent">
                        <!-- Content will be loaded dynamically -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
            </div>
            <!-- End Current Allocations Tab -->

        </div>
        <!-- End Tab Content -->

    </div>

    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Tab functionality and localStorage
        document.addEventListener('DOMContentLoaded', function() {
            // Remember last active tab
            const lastTab = localStorage.getItem('transport_allocation_active_tab');
            if (lastTab) {
                const tabTrigger = new bootstrap.Tab(document.querySelector(`[data-bs-target="#${lastTab}"]`));
                tabTrigger.show();
            }

            // Save active tab to localStorage
            document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
                tab.addEventListener('shown.bs.tab', function (e) {
                    const targetId = e.target.getAttribute('data-bs-target').substring(1);
                    localStorage.setItem('transport_allocation_active_tab', targetId);
                });
            });

            // Add badge animations for new content
            const badges = document.querySelectorAll('.nav-tabs .badge');
            badges.forEach(badge => {
                if (parseInt(badge.textContent) > 0) {
                    badge.classList.add('animate__animated', 'animate__pulse', 'animate__infinite');
                }
            });
        });

        // Get request data from PHP
        const pendingRequests = <?php
        // Ensure we have valid data and handle any encoding issues
        $safe_requests = $pending_requests ?? [];
        // Remove any potentially problematic fields that might contain invalid characters
        foreach ($safe_requests as &$request) {
            // Ensure all string fields are properly cleaned
            foreach ($request as $key => &$value) {
                if (is_string($value)) {
                    $value = htmlspecialchars_decode($value, ENT_QUOTES);
                }
            }
        }
        echo json_encode($safe_requests, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        ?>;

        // Ensure pendingRequests is available globally
        window.pendingRequests = pendingRequests;

        // Full allocateTransport function
        window.allocateTransport = function(requestId) {
            console.log('allocateTransport called with requestId:', requestId);
            try {
                // Check if required elements exist
                const allocateRequestIdElement = document.getElementById('allocateRequestId');
                if (!allocateRequestIdElement) {
                    console.error('allocateRequestId element not found');
                    alert('Error: Modal form element not found');
                    return;
                }

                console.log('Setting request ID:', requestId);
                allocateRequestIdElement.value = requestId;

                // Find the request data
                const request = window.pendingRequests.find(r => r.id == requestId);
                if (request) {
                    console.log('Found request data:', request);
                    prefillRequestData(request);
                } else {
                    console.warn('Request data not found for ID:', requestId);
                }

                // Reset the allocation form
                if (typeof resetAllocationForm === 'function') {
                    resetAllocationForm();
                } else {
                    console.warn('resetAllocationForm function not found');
                }

                // Open the modal
                const allocationModalElement = document.getElementById('allocationModal');
                if (!allocationModalElement) {
                    console.error('allocationModal element not found');
                    alert('Error: Allocation modal not found');
                    return;
                }

                // Check if Bootstrap is available
                if (typeof bootstrap === 'undefined') {
                    console.error('Bootstrap not loaded');
                    alert('Error: Bootstrap not loaded');
                    return;
                }

                console.log('Opening allocation modal...');
                const modal = new bootstrap.Modal(allocationModalElement);
                modal.show();

                // Auto-check availability after modal opens
                allocationModalElement.addEventListener('shown.bs.modal', function() {
                    console.log('Modal opened, checking availability...');
                    setTimeout(function() {
                        if (typeof checkAvailability === 'function') {
                            checkAvailability();
                        } else {
                            console.warn('checkAvailability function not found');
                        }
                    }, 500);
                }, { once: true });

            } catch (error) {
                console.error('Error in allocateTransport function:', error);
                alert('Error opening allocation modal: ' + error.message);
            }
        };

        // Test that function is properly assigned
        console.log('allocateTransport function defined:', typeof window.allocateTransport);

        // Test function accessibility
        console.log('window.allocateTransport:', window.allocateTransport);

        // Add a global test function
        window.testAllocateTransport = function() {
            console.log('Test function called');
            window.allocateTransport(999);
        };

        function prefillRequestData(request) {
            // Show trip type indicator
            const tripIndicator = document.getElementById('tripTypeIndicator');
            const tripMessage = document.getElementById('tripTypeMessage');

            // Handle allocation type based on trip type
            if (request.request_type === 'one_way') {
                // Show trip type message
                tripIndicator.style.display = 'block';
                tripIndicator.className = 'alert alert-info mb-4';
                tripMessage.innerHTML = '<strong>One-Way Trip:</strong> <span class="text-danger">IMPORTANT:</span> Please set the estimated return time so the vehicle can be allocated for other trips. Without a return time, the vehicle will be marked as unavailable for the rest of the day.';

                // For one-way trips, default to time-based allocation
                document.getElementById('timeBasedAllocation').checked = true;
                document.getElementById('fullDayAllocation').checked = false;
                document.getElementById('allocationTypeHidden').value = 'time_based';

                // Suggest enabling return time for proper availability management
                document.getElementById('enableReturnTime').checked = true;
                document.getElementById('enableReturnTime').disabled = false;
                enableReturnTimeFields(true);

                // Update return display to emphasize importance
                const returnDisplay = document.getElementById('returnDisplay');
                if (returnDisplay) {
                    returnDisplay.innerHTML = '<i class="fas fa-exclamation-triangle me-2 text-warning"></i>Set return time when vehicle will be available again';
                }

                // Auto-set return time to 1 hour after departure as default
                if (request.departure_date && request.departure_time) {
                    document.getElementById('returnDate').value = request.departure_date;

                    // Add 1 hour to departure time as estimated return
                    const [hour24, minute] = request.departure_time.split(':');
                    let returnHour24 = parseInt(hour24) + 1; // Add 1 hour
                    if (returnHour24 >= 24) returnHour24 = 23; // Cap at 11 PM

                    const returnHour12 = returnHour24 == 0 ? 12 : (returnHour24 > 12 ? returnHour24 - 12 : returnHour24);
                    const returnAmpm = returnHour24 < 12 ? 'AM' : 'PM';

                    document.getElementById('returnHour').value = returnHour12;
                    document.getElementById('returnMinute').value = minute;
                    document.getElementById('returnAmPm').value = returnAmpm;
                }
            } else if (request.request_type === 'round_trip') {
                // Show trip type message
                tripIndicator.style.display = 'block';
                tripIndicator.className = 'alert alert-warning mb-4';
                tripMessage.innerHTML = '<strong>Round Trip:</strong> Vehicle will be reserved for both departure and return journey. Please set return time to ensure availability.';

                // For round trips, automatically enable return time
                document.getElementById('timeBasedAllocation').checked = true;
                document.getElementById('fullDayAllocation').checked = false;
                document.getElementById('allocationTypeHidden').value = 'time_based';

                // Automatically enable return time for round trips
                document.getElementById('enableReturnTime').checked = true;
                document.getElementById('enableReturnTime').disabled = false;
                enableReturnTimeFields(true);
            } else if (request.request_type === 'multi_destination') {
                // Show trip type message
                tripIndicator.style.display = 'block';
                tripIndicator.className = 'alert alert-danger mb-4';
                tripMessage.innerHTML = '<strong>Multi-Destination Trip:</strong> Vehicle will be reserved for the entire day to handle multiple stops. Full day allocation recommended.';

                // For multi-destination, suggest full day allocation
                document.getElementById('fullDayAllocation').checked = true;
                document.getElementById('timeBasedAllocation').checked = false;
                document.getElementById('allocationTypeHidden').value = 'full_day';
                toggleAllocationTypeFields();
            }

            // Pre-fill departure date and time from request
            if (request.departure_date && request.departure_time) {
                document.getElementById('departureDate').value = request.departure_date;

                // Convert departure time to 12-hour format
                const [hour24, minute] = request.departure_time.split(':');
                const hour12 = hour24 == 0 ? 12 : (hour24 > 12 ? hour24 - 12 : parseInt(hour24));
                const ampm = hour24 < 12 ? 'AM' : 'PM';

                // Find closest 15-minute interval
                const exactMinute = parseInt(minute);
                const closestMinute = Math.round(exactMinute / 15) * 15;
                const finalMinute = closestMinute >= 60 ? 0 : closestMinute;

                document.getElementById('departureHour').value = hour12;
                document.getElementById('departureMinute').value = finalMinute;
                document.getElementById('departureAmPm').value = ampm;
            }

            // Pre-fill return date and time if exists (for round trips)
            if (request.return_date && request.return_time && request.request_type === 'round_trip') {
                document.getElementById('returnDate').value = request.return_date;

                // Convert return time to 12-hour format
                const [returnHour24, returnMinute] = request.return_time.split(':');
                const returnHour12 = returnHour24 == 0 ? 12 : (returnHour24 > 12 ? returnHour24 - 12 : parseInt(returnHour24));
                const returnAmpm = returnHour24 < 12 ? 'AM' : 'PM';

                // Find closest 15-minute interval
                const exactReturnMinute = parseInt(returnMinute);
                const closestReturnMinute = Math.round(exactReturnMinute / 15) * 15;
                const finalReturnMinute = closestReturnMinute >= 60 ? 0 : closestReturnMinute;

                document.getElementById('returnHour').value = returnHour12;
                document.getElementById('returnMinute').value = finalReturnMinute;
                document.getElementById('returnAmPm').value = returnAmpm;
            }

            // Update displays after pre-filling
            setTimeout(() => {
                updateDepartureTime();
                updateReturnTime();
            }, 100);
        }

        function resetAllocationForm() {
            // Clear dropdowns
            document.querySelector('select[name="vehicle_id"]').value = '';
            document.querySelector('select[name="driver_id"]').value = '';

            // Hide trip type indicator
            document.getElementById('tripTypeIndicator').style.display = 'none';

            // Reset allocation type to time-based
            document.getElementById('timeBasedAllocation').checked = true;
            document.getElementById('fullDayAllocation').checked = false;
            document.getElementById('allocationTypeHidden').value = 'time_based';
            toggleAllocationTypeFields();

            // Reset return time toggle
            document.getElementById('enableReturnTime').checked = false;
            document.getElementById('enableReturnTime').disabled = false;
            enableReturnTimeFields(false);

            // Clear hidden fields
            document.getElementById('scheduledDepartureHidden').value = '';
            document.getElementById('scheduledReturnHidden').value = '';
        }

        function enableReturnTimeFields(enable) {
            const fieldsContainer = document.getElementById('returnTimeFields');
            const displayElement = document.getElementById('returnDisplay');

            if (enable) {
                fieldsContainer.style.opacity = '1';
                fieldsContainer.style.pointerEvents = 'auto';
                displayElement.innerHTML = '<i class="fas fa-clock me-2"></i>Select return date and time';
            } else {
                fieldsContainer.style.opacity = '0.5';
                fieldsContainer.style.pointerEvents = 'none';
                displayElement.innerHTML = '<i class="fas fa-clock me-2"></i>Toggle switch to enable return time';

                // Clear return time fields
                document.getElementById('returnDate').value = '';
                document.getElementById('returnHour').value = '';
                document.getElementById('returnMinute').value = '';
                document.getElementById('returnAmPm').value = '';
                document.getElementById('scheduledReturnHidden').value = '';
            }
        }

        function toggleAllocationTypeFields() {
            const isFullDay = document.getElementById('fullDayAllocation').checked;
            const returnTimeSection = document.querySelector('.col-md-6:has(#returnTimeFields)');
            const returnTimeContainer = document.getElementById('returnTimeFields');
            const enableReturnTimeSwitch = document.getElementById('enableReturnTime');

            // Update hidden field
            document.getElementById('allocationTypeHidden').value = isFullDay ? 'full_day' : 'time_based';

            if (isFullDay) {
                // Hide return time section for full day allocation
                if (returnTimeSection) {
                    returnTimeSection.style.opacity = '0.3';
                    returnTimeSection.style.pointerEvents = 'none';
                }

                // Disable return time switch and clear return time
                enableReturnTimeSwitch.checked = false;
                enableReturnTimeSwitch.disabled = true;
                enableReturnTimeFields(false);

                // Show full day message
                const returnDisplay = document.getElementById('returnDisplay');
                if (returnDisplay) {
                    returnDisplay.innerHTML = '<i class="fas fa-calendar-day me-2 text-warning"></i>Full day allocation - no return time needed';
                }
            } else {
                // Show return time section for time-based allocation
                if (returnTimeSection) {
                    returnTimeSection.style.opacity = '1';
                    returnTimeSection.style.pointerEvents = 'auto';
                }

                // Enable return time switch
                enableReturnTimeSwitch.disabled = false;

                // Reset return time display
                const returnDisplay = document.getElementById('returnDisplay');
                if (returnDisplay) {
                    returnDisplay.innerHTML = '<i class="fas fa-clock me-2"></i>Toggle switch to enable return time';
                }
            }
        }

        // Time handling functions
        function updateDepartureTime() {
            const date = document.getElementById('departureDate').value;
            const hour = document.getElementById('departureHour').value;
            const minute = document.getElementById('departureMinute').value;
            const ampm = document.getElementById('departureAmPm').value;

            if (date && hour && minute !== '' && ampm) {
                let hour24 = parseInt(hour);
                if (ampm === 'PM' && hour24 !== 12) {
                    hour24 += 12;
                } else if (ampm === 'AM' && hour24 === 12) {
                    hour24 = 0;
                }

                const timeString = String(hour24).padStart(2, '0') + ':' + String(minute).padStart(2, '0');
                const fullDateTime = date + 'T' + timeString;

                document.getElementById('scheduledDepartureHidden').value = fullDateTime;

                // Update display
                const displayDate = new Date(date + 'T' + timeString).toLocaleDateString();
                const displayTime = new Date('2000-01-01T' + timeString).toLocaleTimeString([], {hour: 'numeric', minute: '2-digit', hour12: true});
                document.getElementById('departureDisplay').innerHTML = `<i class="fas fa-calendar-check me-2 text-success"></i><strong>${displayDate}</strong> at <strong>${displayTime}</strong>`;
            }
        }

        function updateReturnTime() {
            const date = document.getElementById('returnDate').value;
            const hour = document.getElementById('returnHour').value;
            const minute = document.getElementById('returnMinute').value;
            const ampm = document.getElementById('returnAmPm').value;

            if (date && hour && minute !== '' && ampm) {
                let hour24 = parseInt(hour);
                if (ampm === 'PM' && hour24 !== 12) {
                    hour24 += 12;
                } else if (ampm === 'AM' && hour24 === 12) {
                    hour24 = 0;
                }

                const timeString = String(hour24).padStart(2, '0') + ':' + String(minute).padStart(2, '0');
                const fullDateTime = date + 'T' + timeString;

                document.getElementById('scheduledReturnHidden').value = fullDateTime;

                // Update display
                const displayDate = new Date(date + 'T' + timeString).toLocaleDateString();
                const displayTime = new Date('2000-01-01T' + timeString).toLocaleTimeString([], {hour: 'numeric', minute: '2-digit', hour12: true});
                document.getElementById('returnDisplay').innerHTML = `<i class="fas fa-calendar-check me-2 text-primary"></i><strong>${displayDate}</strong> at <strong>${displayTime}</strong>`;
            } else if (!date && !hour && !minute && !ampm) {
                document.getElementById('scheduledReturnHidden').value = '';
                document.getElementById('returnDisplay').innerHTML = '<i class="fas fa-clock me-2"></i>Optional return time';
            }
        }

        // Function to check vehicle and driver availability for selected dates
        function checkAvailability() {
            console.log('=== CHECK AVAILABILITY CALLED ===');

            const departureDate = document.getElementById('departureDate').value;
            const returnDate = document.getElementById('returnDate').value;
            const isFullDay = document.getElementById('fullDayAllocation').checked;
            const departureTime = document.getElementById('scheduledDepartureHidden').value;
            const returnTime = document.getElementById('scheduledReturnHidden').value;
            const requestId = document.getElementById('allocateRequestId').value;

            console.log('Departure date:', departureDate);
            console.log('Request ID:', requestId);

            if (!departureDate) {
                console.log('No departure date, exiting checkAvailability');
                return;
            }

            // For testing, always allow checking availability even without request ID

            // Show loading state
            document.getElementById('vehicleAvailabilityText').innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Checking availability...';
            document.getElementById('driverAvailabilityText').innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Checking availability...';

            // Disable dropdowns while loading
            document.getElementById('vehicleSelect').disabled = true;
            document.getElementById('driverSelect').disabled = true;

            // AJAX call to check availability
            const params = new URLSearchParams({
                ajax: 'check_availability',
                date: departureDate,
                allocation_type: isFullDay ? 'full_day' : 'time_based',
                debug: '1' // Enable debug mode
            });

            if (requestId) {
                params.append('request_id', requestId);
            }

            if (returnDate && !isFullDay) {
                params.append('return_date', returnDate);
            }

            if (!isFullDay && departureTime) {
                params.append('departure_time', departureTime);
            }

            if (!isFullDay && returnTime) {
                params.append('return_time', returnTime);
            }

            // Debug: log what we're checking
            console.log('Checking availability for:', {
                date: departureDate,
                departure_time: departureTime,
                return_time: returnTime,
                allocation_type: isFullDay ? 'full_day' : 'time_based'
            });

            fetch('?' + params.toString())
                .then(response => response.json())
                .then(data => {
                    console.log('=== AVAILABILITY CHECK RESPONSE ===');
                    console.log('Full response:', data);
                    console.log('Vehicles count:', data.vehicles ? data.vehicles.length : 0);
                    console.log('Drivers count:', data.drivers ? data.drivers.length : 0);
                    console.log('Drivers data:', data.drivers);

                    if (data.error) {
                        console.error('Error:', data.error);
                        return;
                    }

                    updateVehicleOptions(data.vehicles);
                    updateDriverOptions(data.drivers);
                })
                .catch(error => {
                    console.error('Error checking availability:', error);
                    document.getElementById('vehicleAvailabilityText').innerHTML = '<i class="fas fa-exclamation-triangle text-danger me-1"></i>Error checking availability';
                    document.getElementById('driverAvailabilityText').innerHTML = '<i class="fas fa-exclamation-triangle text-danger me-1"></i>Error checking availability';
                })
                .finally(() => {
                    // Re-enable dropdowns
                    document.getElementById('vehicleSelect').disabled = false;
                    document.getElementById('driverSelect').disabled = false;
                });
        }

        function updateVehicleOptions(availableVehicles) {
            console.log('=== UPDATE VEHICLE OPTIONS ===');
            console.log('Available vehicles received:', availableVehicles);

            const vehicleSelect = document.getElementById('vehicleSelect');
            const vehicleText = document.getElementById('vehicleAvailabilityText');
            const departureTime = document.getElementById('scheduledDepartureHidden').value;
            const returnTime = document.getElementById('scheduledReturnHidden').value;

            // Clear current selection and rebuild options completely
            vehicleSelect.innerHTML = '<option value="">Choose a vehicle...</option>';

            // Separate service providers and regular vehicles
            const serviceProviders = availableVehicles.filter(v => v.provider_type === 'service_provider');
            const regularVehicles = availableVehicles.filter(v => !v.provider_type || v.provider_type !== 'service_provider');

            console.log('Service providers filtered:', serviceProviders);
            console.log('Regular vehicles filtered:', regularVehicles);

            // Add service providers group first
            if (serviceProviders.length > 0) {
                console.log('Creating service provider optgroup...');
                const optgroup = document.createElement('optgroup');
                optgroup.label = '🚚 Service Providers (Multiple Trips Allowed)';
                serviceProviders.forEach(provider => {
                    console.log('Adding service provider vehicle:', provider);
                    const option = document.createElement('option');
                    option.value = provider.id; // This will be sp_X format
                    option.setAttribute('data-type', 'service_provider');
                    option.setAttribute('data-phone', provider.phone || '');
                    option.textContent = `🚚 ${provider.brand} (${provider.vehicle_number})`;
                    optgroup.appendChild(option);
                    console.log('Added vehicle option:', option.textContent);
                });
                vehicleSelect.appendChild(optgroup);
                console.log('Service provider optgroup added to vehicle select');
            } else {
                console.log('No service providers to add to vehicles');
            }

            // Add regular vehicles group
            if (regularVehicles.length > 0) {
                const optgroup = document.createElement('optgroup');
                optgroup.label = '🚗 Company Vehicles';
                regularVehicles.forEach(vehicle => {
                    const option = document.createElement('option');
                    option.value = vehicle.id;
                    option.setAttribute('data-type', 'vehicle');
                    option.textContent = `${vehicle.vehicle_number} - ${vehicle.vehicle_type} ${vehicle.brand} ${vehicle.model} (${vehicle.seating_capacity} seats)`;
                    optgroup.appendChild(option);
                });
                vehicleSelect.appendChild(optgroup);
            }

            // Show detailed availability message
            const timeRange = departureTime ?
                (returnTime ? `${new Date(departureTime).toLocaleTimeString()} - ${new Date(returnTime).toLocaleTimeString()}` :
                `from ${new Date(departureTime).toLocaleTimeString()}`) :
                'selected date(s)';

            const spCount = serviceProviders.length;
            const vehicleCount = regularVehicles.length;
            const totalCount = availableVehicles.length;

            vehicleText.innerHTML = `<i class="fas fa-check-circle text-success me-1"></i>${totalCount} available for ${timeRange}: ${vehicleCount} vehicles, ${spCount} service providers`;
        }

        function updateDriverOptions(availableDrivers) {
            console.log('=== UPDATE DRIVER OPTIONS ===');
            console.log('Available drivers received:', availableDrivers);

            const driverSelect = document.getElementById('driverSelect');
            const driverText = document.getElementById('driverAvailabilityText');

            // Clear current selection and rebuild options - now only for regular drivers
            driverSelect.innerHTML = '<option value="">Choose a driver...</option>';

            // Only show regular drivers (service providers are now in vehicle dropdown)
            const regularDrivers = availableDrivers.filter(d => d.provider_type === 'driver');

            console.log('Regular drivers filtered:', regularDrivers);

            // Add regular drivers
            if (regularDrivers.length > 0) {
                regularDrivers.forEach(driver => {
                    const option = document.createElement('option');
                    option.value = driver.id;
                    option.setAttribute('data-type', 'driver');
                    option.setAttribute('data-phone', driver.phone || '');
                    option.textContent = driver.emp_name + ' - EPF: ' + driver.emp_number;
                    if (driver.phone) {
                        option.textContent += ' (' + driver.phone + ')';
                    }
                    driverSelect.appendChild(option);
                });
            }

            const driverCount = regularDrivers.length;

            driverText.innerHTML = `<i class="fas fa-check-circle text-success me-1"></i>${driverCount} available drivers`;
        }

        // Handle vehicle selection change - make driver optional for service providers
        function handleVehicleSelection() {
            const vehicleSelect = document.getElementById('vehicleSelect');
            const driverSelect = document.getElementById('driverSelect');

            if (vehicleSelect && driverSelect) {
                vehicleSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const isServiceProvider = selectedOption.getAttribute('data-type') === 'service_provider';

                    if (isServiceProvider) {
                        // Service provider selected - driver is not required
                        driverSelect.required = false;
                        driverSelect.value = ''; // Clear driver selection
                        driverSelect.disabled = true; // Disable driver selection

                        // Update driver text to show it's not needed
                        const driverText = document.getElementById('driverAvailabilityText');
                        if (driverText) {
                            driverText.innerHTML = '<i class="fas fa-info-circle text-info me-1"></i>Driver not required - Service provider includes driver';
                        }
                    } else {
                        // Regular vehicle selected - driver is required
                        driverSelect.required = true;
                        driverSelect.disabled = false; // Enable driver selection

                        // Restore normal driver availability text
                        const driverText = document.getElementById('driverAvailabilityText');
                        if (driverText && driverText.innerHTML.includes('not required')) {
                            driverText.innerHTML = '<i class="fas fa-info-circle text-warning me-1"></i>Select a date first to see available drivers';
                        }
                    }
                });
            }
        }

        // Add event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize vehicle selection handler
            handleVehicleSelection();

            // Departure time listeners
            const departureDateField = document.getElementById('departureDate');
            if (departureDateField) {
                departureDateField.addEventListener('change', function() {
                    console.log('=== DATE CHANGED ===');
                    console.log('Departure date changed to:', this.value);
                    updateDepartureTime();
                    console.log('About to call checkAvailability...');
                    checkAvailability();
                });
                console.log('Departure date listener added successfully');

                // Also trigger on input event for better responsiveness
                departureDateField.addEventListener('input', function() {
                    if (this.value) {
                        console.log('=== DATE INPUT ===');
                        console.log('Departure date input to:', this.value);
                        updateDepartureTime();
                        checkAvailability();
                    }
                });
                console.log('Departure date input listener added successfully');
            } else {
                console.log('ERROR: departureDate field not found!');
            }
            document.getElementById('departureHour').addEventListener('change', updateDepartureTime);
            document.getElementById('departureMinute').addEventListener('change', updateDepartureTime);
            document.getElementById('departureAmPm').addEventListener('change', updateDepartureTime);

            // Return time listeners
            document.getElementById('returnDate').addEventListener('change', function() {
                updateReturnTime();
                checkAvailability();
            });
            document.getElementById('returnHour').addEventListener('change', updateReturnTime);
            document.getElementById('returnMinute').addEventListener('change', updateReturnTime);
            document.getElementById('returnAmPm').addEventListener('change', updateReturnTime);

            // Return time toggle listener
            document.getElementById('enableReturnTime').addEventListener('change', function() {
                enableReturnTimeFields(this.checked);
                checkAvailability();
            });

            // Allocation type listeners
            document.getElementById('fullDayAllocation').addEventListener('change', function() {
                // Check if this contradicts the trip type
                const requestId = document.getElementById('allocateRequestId').value;
                const request = pendingRequests.find(r => r.id == requestId);

                if (request && request.request_type === 'round_trip' && this.checked) {
                    if (confirm('Round trips typically need specific return times. Are you sure you want to allocate for the full day?')) {
                        toggleAllocationTypeFields();
                        checkAvailability();
                    } else {
                        document.getElementById('timeBasedAllocation').checked = true;
                        this.checked = false;
                    }
                } else {
                    toggleAllocationTypeFields();
                    checkAvailability();
                }
            });

            document.getElementById('timeBasedAllocation').addEventListener('change', function() {
                // Check if this contradicts the trip type
                const requestId = document.getElementById('allocateRequestId').value;
                const request = pendingRequests.find(r => r.id == requestId);

                if (request && request.request_type === 'multi_destination' && this.checked) {
                    if (confirm('Multi-destination trips typically need the vehicle for the full day. Are you sure you want time-based allocation?')) {
                        toggleAllocationTypeFields();
                        checkAvailability();
                    } else {
                        document.getElementById('fullDayAllocation').checked = true;
                        this.checked = false;
                    }
                } else {
                    toggleAllocationTypeFields();
                    checkAvailability();
                }
            });
        });

        function startTrip(allocationId) {
            if (confirm('Start this trip now?')) {
                // Create a form and submit directly
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="start">
                    <input type="hidden" name="allocation_id" value="${allocationId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function completeTrip(allocationId) {
            if (confirm('Complete this trip now?')) {
                // Create a form and submit directly
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="complete">
                    <input type="hidden" name="allocation_id" value="${allocationId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Form validation for complete trip modal - removed (no longer needed)

        function cancelAllocation(allocationId) {
            if (confirm('⚠️ WARNING: This will PERMANENTLY DELETE the transport request!\n\nThe trip will be cancelled and cannot be recovered. The vehicle/driver will be made available.\n\nAre you sure you want to proceed?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="allocation_id" value="${allocationId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function editAllocation(allocationId) {
            // Find the allocation data
            const allocations = <?php echo json_encode($allocations); ?>;
            const allocation = allocations.find(a => a.id == allocationId);

            if (allocation) {
                // Set edit mode flag
                window.isEditingAllocation = true;
                window.editingAllocationId = allocationId;

                // Find the original request and pre-populate the allocation modal
                allocateTransport(allocation.request_id);

                // Update modal title to indicate editing
                setTimeout(() => {
                    const modal = document.getElementById('allocationModal');
                    const title = modal.querySelector('.modal-title');
                    if (title) {
                        title.innerHTML = '<i class="fas fa-edit me-2"></i>Edit Transport Allocation';
                    }

                    // Add hidden field for allocation_id
                    const form = modal.querySelector('form');
                    if (form && !form.querySelector('input[name="allocation_id"]')) {
                        const hiddenField = document.createElement('input');
                        hiddenField.type = 'hidden';
                        hiddenField.name = 'allocation_id';
                        hiddenField.value = allocationId;
                        form.appendChild(hiddenField);
                    }
                }, 500);
            } else {
                alert('Allocation data not found');
            }
        }

        function joinTrip(allocationId) {
            // Find the clicked button and disable it immediately to prevent double-clicking
            const buttons = document.querySelectorAll('button[onclick*="joinTrip(' + allocationId + ')"]');
            buttons.forEach(button => {
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Opening...';
            });

            document.getElementById('joinAllocationId').value = allocationId;
            new bootstrap.Modal(document.getElementById('joinTripModal')).show();

            // Re-enable button if modal is closed without submitting
            const modal = document.getElementById('joinTripModal');
            modal.addEventListener('hidden.bs.modal', function() {
                buttons.forEach(button => {
                    button.disabled = false;
                    button.innerHTML = '<i class="fas fa-plus-circle me-1"></i>Join Trip';
                });
            }, { once: true });
        }

        function viewDetails(allocationId) {
            // Find the allocation data from the PHP data
            const allocations = <?php echo json_encode($allocations); ?>;
            const allocation = allocations.find(a => a.id == allocationId);

            if (allocation) {
                const content = document.getElementById('requestDetailsContent');

                // Format passenger names
                const passengerNames = allocation.passenger_names ? allocation.passenger_names.split('\n').filter(name => name.trim()).join(', ') : 'N/A';

                content.innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0"><i class="fas fa-user me-2"></i>Requester Information</h6>
                                </div>
                                <div class="card-body">
                                    <p><strong>Name:</strong> ${allocation.requester_name}</p>
                                    <p><strong>EPF Number:</strong> ${allocation.requester_emp_number}</p>
                                    <p><strong>Location:</strong> ${allocation.requester_location || 'N/A'}</p>
                                </div>
                            </div>

                            <div class="card mt-3">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0"><i class="fas fa-route me-2"></i>Trip Information</h6>
                                </div>
                                <div class="card-body">
                                    <p><strong>Type:</strong> <span class="badge bg-info">${allocation.request_type.replace('_', ' ')}</span></p>
                                    <p><strong>Purpose:</strong> ${allocation.purpose}</p>
                                    <p><strong>Route:</strong> ${allocation.departure_location} → ${allocation.destination}</p>
                                    <p><strong>Urgency:</strong> <span class="badge bg-${allocation.urgency_level === 'urgent' ? 'danger' : allocation.urgency_level === 'high' ? 'warning' : 'secondary'}">${allocation.urgency_level}</span></p>
                                    ${allocation.special_requirements ? `<p><strong>Special Requirements:</strong> ${allocation.special_requirements}</p>` : ''}
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-warning text-dark">
                                    <h6 class="mb-0"><i class="fas fa-calendar me-2"></i>Schedule Details</h6>
                                </div>
                                <div class="card-body">
                                    <p><strong>Departure:</strong> ${new Date(allocation.departure_date + ' ' + allocation.departure_time).toLocaleString()}</p>
                                    ${allocation.return_date ? `<p><strong>Return:</strong> ${new Date(allocation.return_date + ' ' + allocation.return_time).toLocaleString()}</p>` : '<p><strong>Type:</strong> One Way Trip</p>'}
                                    <p><strong>Scheduled Departure:</strong> ${new Date(allocation.scheduled_departure).toLocaleString()}</p>
                                    ${allocation.scheduled_return ? `<p><strong>Scheduled Return:</strong> ${new Date(allocation.scheduled_return).toLocaleString()}</p>` : ''}
                                </div>
                            </div>

                            <div class="card mt-3">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0"><i class="fas fa-users me-2"></i>Passenger Details</h6>
                                </div>
                                <div class="card-body">
                                    <p><strong>Count:</strong> ${allocation.passenger_count} passenger(s)</p>
                                    <p><strong>Vehicle Capacity:</strong> ${allocation.seating_capacity} seats</p>
                                    <p><strong>Available Space:</strong> ${allocation.seating_capacity - allocation.passenger_count} seat(s)</p>
                                    <p><strong>Passenger Names:</strong><br><span class="text-muted">${passengerNames}</span></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-dark text-white">
                                    <h6 class="mb-0"><i class="fas fa-car me-2"></i>Vehicle & Driver Assignment</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>Vehicle:</strong> ${allocation.vehicle_number}</p>
                                            <p><strong>Type:</strong> ${allocation.vehicle_type}</p>
                                            <p><strong>Model:</strong> ${allocation.brand} ${allocation.model}</p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Driver:</strong> ${allocation.driver_name}</p>
                                            <p><strong>EPF:</strong> ${allocation.driver_emp_number}</p>
                                            <p><strong>License:</strong> ${allocation.license_number}</p>
                                            <p><strong>Contact:</strong> ${allocation.driver_phone || 'N/A'}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                new bootstrap.Modal(document.getElementById('requestDetailsModal')).show();
            }
        }

    </script>
</body>
</html>