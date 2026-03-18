<?php
// Helper functions for finding actual managers in hierarchy

/**
 * Find the actual manager (level 3) or GM (level 2) for an employee
 * Traverses up the reporting hierarchy until it finds someone with employment_level_id = 3 or 2
 */
function findActualManager($db, $employeeEpf) {
    try {
        // Get the employee's direct supervisor
        $employee = $db->fetch("SELECT reports_to FROM employees WHERE epf_number = ? AND is_active = 1", [$employeeEpf]);

        if (!$employee || !$employee['reports_to']) {
            return null;
        }

        $currentEpf = $employee['reports_to'];
        $level = 0;

        // Traverse up the hierarchy
        while ($currentEpf && $level < 10) { // Prevent infinite loops
            $level++;

            $superior = $db->fetch("SELECT epf_number, name, employment_level_id, reports_to
                                   FROM employees
                                   WHERE epf_number = ? AND is_active = 1", [$currentEpf]);

            if (!$superior) {
                break;
            }

            // Check if this person is a Manager (level 3) or GM (level 2)
            if ($superior['employment_level_id'] == 3 || $superior['employment_level_id'] == 2) {
                return [
                    'epf_number' => $superior['epf_number'],
                    'name' => $superior['name'],
                    'employment_level_id' => $superior['employment_level_id']
                ];
            }

            $currentEpf = $superior['reports_to'];
        }

        return null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Get employee list with actual managers (not just direct supervisors)
 */
function getEmployeeListWithActualManagers($db) {
    try {
        // Get all active employees
        $sql = "SELECT
            e.epf_number,
            e.name,
            e.designation,
            e.department,
            e.location_id,
            l.location_name,
            e.joined_date,
            TIMESTAMPDIFF(YEAR, e.joined_date, CURDATE()) as service_years
        FROM employees e
        LEFT JOIN locations l ON e.location_id = l.id
        WHERE e.is_active = 1
        ORDER BY CAST(e.epf_number AS UNSIGNED)";

        $employees = $db->fetchAll($sql);

        // For each employee, find their actual manager
        foreach ($employees as $index => $employee) {
            $manager = findActualManager($db, $employee['epf_number']);

            $employees[$index]['manager_epf'] = $manager ? $manager['epf_number'] : null;
            $employees[$index]['manager_name'] = $manager ? $manager['name'] : null;
        }

        return $employees;
    } catch (Exception $e) {
        throw $e;
    }
}

/**
 * Get employees under a specific manager (including all their subordinates in hierarchy)
 */
function getEmployeesUnderManager($db, $managerEpf) {
    try {
        $employees = getEmployeeListWithActualManagers($db);

        // Filter employees who have this person as their actual manager
        $result = [];
        foreach ($employees as $employee) {
            if ($employee['manager_epf'] == $managerEpf) {
                $result[] = $employee;
            }
        }

        return $result;
    } catch (Exception $e) {
        throw $e;
    }
}
?>