<?php

/**
 * Role-based Access Control Functions
 * Handles hierarchical access for supervisors and managers
 */

/**
 * Get employees that the current user can access based on hierarchy
 * @param Database $db
 * @param array $user Current user data
 * @param string $additional_where Additional WHERE conditions
 * @return array List of accessible employees
 */
function getAccessibleEmployees($db, $user, $additional_where = '') {
    $epf_number = $user['epf_number'] ?? '';
    $account_type = $user['account_type'] ?? 'user';

    // SuperAdmin and Admin can see all employees
    if (in_array($account_type, ['superadmin', 'admin'])) {
        $query = "SELECT e.* FROM employees e";
        if ($additional_where) {
            $query .= " WHERE " . $additional_where;
        }
        $query .= " ORDER BY e.name ASC";
        return $db->fetchAll($query);
    }

    // Manager can see all employees in their department and below
    if ($account_type === 'manager') {
        $user_employee = $db->fetch("SELECT department FROM employees WHERE epf_number = ?", [$epf_number]);
        if ($user_employee) {
            $department = $user_employee['department'];
            $query = "SELECT e.* FROM employees e WHERE e.department = ?";
            $params = [$department];

            if ($additional_where) {
                $query .= " AND " . $additional_where;
            }
            $query .= " ORDER BY e.name ASC";
            return $db->fetchAll($query, $params);
        }
    }

    // Supervisor can see direct reports and their sub-reports
    if ($account_type === 'supervisor') {
        // Get direct reports
        $direct_reports = $db->fetchAll(
            "SELECT e.* FROM employees e WHERE e.reports_to = ?
             UNION
             SELECT e.* FROM employees e WHERE e.epf_number = ?",
            [$epf_number, $epf_number]
        );

        // Get sub-reports (employees reporting to direct reports)
        $all_reports = [];
        foreach ($direct_reports as $report) {
            $sub_reports = $db->fetchAll(
                "SELECT e.* FROM employees e WHERE e.reports_to = ?",
                [$report['epf_number']]
            );
            $all_reports = array_merge($all_reports, $sub_reports);
        }

        // Combine direct and sub-reports
        $accessible_employees = array_merge($direct_reports, $all_reports);

        // Remove duplicates and apply additional filters
        $unique_employees = [];
        $seen_ids = [];

        foreach ($accessible_employees as $employee) {
            if (!in_array($employee['id'], $seen_ids)) {
                // Apply additional where conditions if provided
                if ($additional_where) {
                    // This is a simplified check - in production, you'd want to parse and apply the WHERE clause properly
                    $include = true;
                    // Add your custom WHERE condition logic here if needed
                }
                if ($include ?? true) {
                    $unique_employees[] = $employee;
                    $seen_ids[] = $employee['id'];
                }
            }
        }

        return $unique_employees;
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
 * Check if current user can access specific employee
 * @param Database $db
 * @param array $user Current user data
 * @param string $target_epf_number Target employee EPF number
 * @return bool
 */
function canAccessEmployee($db, $user, $target_epf_number) {
    $accessible_employees = getAccessibleEmployees($db, $user);

    foreach ($accessible_employees as $employee) {
        if ($employee['epf_number'] === $target_epf_number) {
            return true;
        }
    }

    return false;
}

/**
 * Get employee count statistics based on user's access level
 * @param Database $db
 * @param array $user Current user data
 * @return array Statistics array
 */
function getAccessibleEmployeeStats($db, $user) {
    $accessible_employees = getAccessibleEmployees($db, $user);

    $stats = [
        'total_employees' => count($accessible_employees),
        'by_location' => [],
        'by_department' => [],
        'by_gender' => ['Male' => 0, 'Female' => 0],
        'by_employment_level' => []
    ];

    foreach ($accessible_employees as $employee) {
        // Location stats
        $location = $employee['location_id'] ?? 'Unknown';
        $stats['by_location'][$location] = ($stats['by_location'][$location] ?? 0) + 1;

        // Department stats
        $department = $employee['department'] ?? 'Unknown';
        $stats['by_department'][$department] = ($stats['by_department'][$department] ?? 0) + 1;

        // Gender stats
        $gender = $employee['gender'] ?? 'Unknown';
        if (isset($stats['by_gender'][$gender])) {
            $stats['by_gender'][$gender]++;
        }

        // Employment level stats
        $level = $employee['employment_level_id'] ?? 'Unknown';
        $stats['by_employment_level'][$level] = ($stats['by_employment_level'][$level] ?? 0) + 1;
    }

    return $stats;
}

/**
 * Get reporting hierarchy tree for current user
 * @param Database $db
 * @param array $user Current user data
 * @return array Hierarchy tree
 */
function getReportingHierarchy($db, $user) {
    $epf_number = $user['epf_number'] ?? '';
    $account_type = $user['account_type'] ?? 'user';

    // SuperAdmin and Admin can see full hierarchy
    if (in_array($account_type, ['superadmin', 'admin'])) {
        return buildFullHierarchy($db);
    }

    // Manager can see department hierarchy
    if ($account_type === 'manager') {
        $user_employee = $db->fetch("SELECT department FROM employees WHERE epf_number = ?", [$epf_number]);
        if ($user_employee) {
            return buildDepartmentHierarchy($db, $user_employee['department']);
        }
    }

    // Supervisor can see their team hierarchy
    if ($account_type === 'supervisor') {
        return buildTeamHierarchy($db, $epf_number);
    }

    // Regular user sees only themselves
    $self = $db->fetch("SELECT * FROM employees WHERE epf_number = ?", [$epf_number]);
    return $self ? [$self] : [];
}

/**
 * Build full organizational hierarchy (for admins)
 */
function buildFullHierarchy($db) {
    $all_employees = $db->fetchAll("SELECT * FROM employees ORDER BY name ASC");
    return buildHierarchyTree($all_employees);
}

/**
 * Build department hierarchy (for managers)
 */
function buildDepartmentHierarchy($db, $department) {
    $dept_employees = $db->fetchAll("SELECT * FROM employees WHERE department = ? ORDER BY name ASC", [$department]);
    return buildHierarchyTree($dept_employees);
}

/**
 * Build team hierarchy (for supervisors)
 */
function buildTeamHierarchy($db, $supervisor_epf) {
    $team_employees = $db->fetchAll(
        "SELECT e.* FROM employees e
         WHERE e.reports_to = ? OR e.epf_number = ?
         ORDER BY e.name ASC",
        [$supervisor_epf, $supervisor_epf]
    );

    // Also get sub-reports
    $sub_reports = [];
    foreach ($team_employees as $employee) {
        if ($employee['epf_number'] !== $supervisor_epf) {
            $subs = $db->fetchAll("SELECT * FROM employees WHERE reports_to = ?", [$employee['epf_number']]);
            $sub_reports = array_merge($sub_reports, $subs);
        }
    }

    $all_team = array_merge($team_employees, $sub_reports);
    return buildHierarchyTree($all_team);
}

/**
 * Build hierarchy tree structure from flat employee array
 */
function buildHierarchyTree($employees) {
    $tree = [];
    $lookup = [];

    // Create lookup array
    foreach ($employees as $employee) {
        $lookup[$employee['epf_number']] = $employee;
        $lookup[$employee['epf_number']]['children'] = [];
    }

    // Build tree
    foreach ($employees as $employee) {
        if (empty($employee['reports_to']) || !isset($lookup[$employee['reports_to']])) {
            // Root level employee
            $tree[] = &$lookup[$employee['epf_number']];
        } else {
            // Child employee
            $lookup[$employee['reports_to']]['children'][] = &$lookup[$employee['epf_number']];
        }
    }

    return $tree;
}

/**
 * Filter query results based on user access level
 * @param Database $db
 * @param array $user Current user data
 * @param string $base_query Base SQL query
 * @param array $params Query parameters
 * @return array Filtered results
 */
function filterQueryByAccess($db, $user, $base_query, $params = []) {
    $account_type = $user['account_type'] ?? 'user';
    $epf_number = $user['epf_number'] ?? '';

    // SuperAdmin and Admin see everything
    if (in_array($account_type, ['superadmin', 'admin'])) {
        return $db->fetchAll($base_query, $params);
    }

    // Add access restrictions to query
    $accessible_epf_numbers = [];
    $accessible_employees = getAccessibleEmployees($db, $user);

    foreach ($accessible_employees as $employee) {
        $accessible_epf_numbers[] = $employee['epf_number'];
    }

    if (empty($accessible_epf_numbers)) {
        return [];
    }

    // Modify query to include only accessible employees
    $epf_placeholders = str_repeat('?,', count($accessible_epf_numbers) - 1) . '?';

    // This is a simple implementation - you may need to modify based on your specific query structure
    if (strpos($base_query, 'WHERE') !== false) {
        $modified_query = $base_query . " AND e.epf_number IN ($epf_placeholders)";
    } else {
        $modified_query = $base_query . " WHERE e.epf_number IN ($epf_placeholders)";
    }

    $all_params = array_merge($params, $accessible_epf_numbers);

    return $db->fetchAll($modified_query, $all_params);
}
?>