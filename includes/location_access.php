<?php

/**
 * Location/Department-based Access Control Functions
 * Controls what employees supervisors and managers can see
 */

/**
 * Get employees accessible to current user based on hierarchical reporting structure
 * @param Database $db
 * @param array $user Current user data
 * @param string $additional_where Additional WHERE conditions
 * @return array List of accessible employees
 */
function getAccessibleEmployeesByLocation($db, $user, $additional_where = '') {
    $account_type = $user['account_type'] ?? 'user';
    $location_id = $user['location_id'] ?? null;
    $epf_number = $user['epf_number'] ?? '';

    // SuperAdmin can see all employees from all locations
    if ($account_type === 'superadmin') {
        $query = "SELECT e.* FROM employees e";
        if ($additional_where) {
            $query .= " WHERE " . $additional_where;
        }
        $query .= " ORDER BY e.name ASC";
        return $db->fetchAll($query);
    }

    // Admin can see all employees in their location only
    if ($account_type === 'admin') {
        $query = "SELECT e.* FROM employees e WHERE e.location_id = ?";
        $params = [$location_id];

        if ($additional_where) {
            $query .= " AND " . $additional_where;
        }
        $query .= " ORDER BY e.name ASC";
        return $db->fetchAll($query, $params);
    }

    // Manager and Supervisor can see only employees who report to them (hierarchical)
    if (in_array($account_type, ['manager', 'supervisor'])) {
        return getHierarchicalReports($db, $epf_number, $additional_where);
    }

    // Regular user can only see themselves
    $query = "SELECT e.* FROM employees e WHERE e.epf_number = ?";
    $params = [$epf_number];

    if ($additional_where) {
        $query .= " AND " . $additional_where;
    }

    return $db->fetchAll($query, $params);
}

/**
 * Get all employees who report to a specific manager/supervisor (including indirect reports)
 * @param Database $db
 * @param string $manager_epf EPF number of manager/supervisor
 * @param string $additional_where Additional WHERE conditions
 * @return array List of reporting employees
 */
function getHierarchicalReports($db, $manager_epf, $additional_where = '') {
    $accessible_employees = [];

    // Include the manager/supervisor themselves
    $manager_query = "SELECT e.* FROM employees e WHERE e.epf_number = ?";
    $manager_params = [$manager_epf];
    if ($additional_where) {
        $manager_query .= " AND " . $additional_where;
    }
    $manager_data = $db->fetchAll($manager_query, $manager_params);
    $accessible_employees = array_merge($accessible_employees, $manager_data);

    // Get direct reports
    $direct_reports = getDirectReports($db, $manager_epf, $additional_where);
    $accessible_employees = array_merge($accessible_employees, $direct_reports);

    // Get indirect reports recursively
    foreach ($direct_reports as $direct_report) {
        $indirect_reports = getHierarchicalReports($db, $direct_report['epf_number'], $additional_where);
        $accessible_employees = array_merge($accessible_employees, $indirect_reports);
    }

    // Remove duplicates based on EPF number
    $unique_employees = [];
    $seen_epf = [];

    foreach ($accessible_employees as $employee) {
        if (!in_array($employee['epf_number'], $seen_epf)) {
            $unique_employees[] = $employee;
            $seen_epf[] = $employee['epf_number'];
        }
    }

    // Sort by name
    usort($unique_employees, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });

    return $unique_employees;
}

/**
 * Get employees who directly report to a specific manager/supervisor
 * @param Database $db
 * @param string $manager_epf EPF number of manager/supervisor
 * @param string $additional_where Additional WHERE conditions
 * @return array List of direct report employees
 */
function getDirectReports($db, $manager_epf, $additional_where = '') {
    $query = "SELECT e.* FROM employees e WHERE e.reports_to = ?";
    $params = [$manager_epf];

    if ($additional_where) {
        $query .= " AND " . $additional_where;
    }
    $query .= " ORDER BY e.name ASC";

    return $db->fetchAll($query, $params);
}

/**
 * Get employee count statistics based on user's location/department access
 * @param Database $db
 * @param array $user Current user data
 * @return array Statistics array
 */
function getLocationBasedStats($db, $user) {
    $accessible_employees = getAccessibleEmployeesByLocation($db, $user);

    $stats = [
        'total_employees' => count($accessible_employees),
        'by_location' => [],
        'by_department' => [],
        'by_gender' => ['Male' => 0, 'Female' => 0],
        'active_employees' => 0
    ];

    foreach ($accessible_employees as $employee) {
        // Location stats
        $location_name = getLocationName($db, $employee['location_id']);
        $stats['by_location'][$location_name] = ($stats['by_location'][$location_name] ?? 0) + 1;

        // Department stats
        $department = $employee['department'] ?? 'Unknown';
        $stats['by_department'][$department] = ($stats['by_department'][$department] ?? 0) + 1;

        // Gender stats
        $gender = $employee['gender'] ?? 'Unknown';
        if (isset($stats['by_gender'][$gender])) {
            $stats['by_gender'][$gender]++;
        }

        // Active employees
        if ($employee['is_active']) {
            $stats['active_employees']++;
        }
    }

    return $stats;
}

/**
 * Check if current user can access specific employee based on location/department
 * @param Database $db
 * @param array $user Current user data
 * @param int $employee_id Target employee ID
 * @return bool
 */
function canAccessEmployeeByLocation($db, $user, $employee_id) {
    $accessible_employees = getAccessibleEmployeesByLocation($db, $user);

    foreach ($accessible_employees as $employee) {
        if ($employee['id'] == $employee_id) {
            return true;
        }
    }

    return false;
}

/**
 * Get location name by ID
 * @param Database $db
 * @param int $location_id
 * @return string
 */
function getLocationName($db, $location_id) {
    if (!$location_id) return 'Unknown';

    $location = $db->fetch("SELECT location_name FROM locations WHERE id = ?", [$location_id]);
    return $location ? $location['location_name'] : 'Unknown';
}

/**
 * Filter employee queries based on user's hierarchical access
 * @param Database $db
 * @param array $user Current user data
 * @param string $base_query Base SQL query (should have 'e' as employees table alias)
 * @param array $params Query parameters
 * @return array Filtered results
 */
function filterEmployeeQueryByLocation($db, $user, $base_query, $params = []) {
    $account_type = $user['account_type'] ?? 'user';
    $location_id = $user['location_id'] ?? null;
    $epf_number = $user['epf_number'] ?? '';

    // SuperAdmin sees everything
    if ($account_type === 'superadmin') {
        return $db->fetchAll($base_query, $params);
    }

    // Admin sees all employees in their location
    if ($account_type === 'admin') {
        $additional_conditions = ["e.location_id = ?"];
        $additional_params = [$location_id];

        $filter_clause = implode(' AND ', $additional_conditions);
        if (strpos($base_query, 'WHERE') !== false) {
            $modified_query = $base_query . " AND " . $filter_clause;
        } else {
            $modified_query = $base_query . " WHERE " . $filter_clause;
        }

        $all_params = array_merge($params, $additional_params);
        return $db->fetchAll($modified_query, $all_params);
    }

    // For managers and supervisors, get accessible EPF numbers first
    if (in_array($account_type, ['manager', 'supervisor'])) {
        $accessible_employees = getHierarchicalReports($db, $epf_number);

        if (empty($accessible_employees)) {
            return []; // No accessible employees
        }

        $accessible_epf_numbers = array_column($accessible_employees, 'epf_number');
        $placeholders = str_repeat('?,', count($accessible_epf_numbers) - 1) . '?';

        if (strpos($base_query, 'WHERE') !== false) {
            $modified_query = $base_query . " AND e.epf_number IN ($placeholders)";
        } else {
            $modified_query = $base_query . " WHERE e.epf_number IN ($placeholders)";
        }

        $all_params = array_merge($params, $accessible_epf_numbers);
        return $db->fetchAll($modified_query, $all_params);
    }

    // Regular user sees only themselves
    $additional_conditions = ["e.epf_number = ?"];
    $additional_params = [$epf_number];

    $filter_clause = implode(' AND ', $additional_conditions);
    if (strpos($base_query, 'WHERE') !== false) {
        $modified_query = $base_query . " AND " . $filter_clause;
    } else {
        $modified_query = $base_query . " WHERE " . $filter_clause;
    }

    $all_params = array_merge($params, $additional_params);
    return $db->fetchAll($modified_query, $all_params);
}

/**
 * Get accessible locations for current user
 * @param Database $db
 * @param array $user Current user data
 * @return array List of accessible locations
 */
function getAccessibleLocations($db, $user) {
    $account_type = $user['account_type'] ?? 'user';
    $location_id = $user['location_id'] ?? null;

    // SuperAdmin and Admin can see all locations
    if (in_array($account_type, ['superadmin', 'admin'])) {
        return $db->fetchAll("SELECT * FROM locations ORDER BY location_name ASC");
    }

    // Manager and Supervisor can only see their location
    if (in_array($account_type, ['manager', 'supervisor']) && $location_id) {
        return $db->fetchAll("SELECT * FROM locations WHERE id = ?", [$location_id]);
    }

    return [];
}

/**
 * Get accessible departments for current user
 * @param Database $db
 * @param array $user Current user data
 * @return array List of accessible departments
 */
function getAccessibleDepartments($db, $user) {
    $account_type = $user['account_type'] ?? 'user';
    $department = $user['department'] ?? null;
    $location_id = $user['location_id'] ?? null;

    // SuperAdmin can see all departments
    if ($account_type === 'superadmin') {
        return $db->fetchAll("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL ORDER BY department ASC");
    }

    // Admin can see all departments
    if ($account_type === 'admin') {
        return $db->fetchAll("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL ORDER BY department ASC");
    }

    // Manager can see all departments in their location
    if ($account_type === 'manager' && $location_id) {
        return $db->fetchAll("SELECT DISTINCT department FROM employees WHERE location_id = ? AND department IS NOT NULL ORDER BY department ASC", [$location_id]);
    }

    // Supervisor can only see their department
    if ($account_type === 'supervisor' && $department) {
        return [['department' => $department]];
    }

    return [];
}
?>