<?php
require_once '../../config/config.php';
require_once 'hierarchy_functions.php';

try {
    $db = new Database();

    echo "<h3>Testing Hierarchy Functions</h3>";

    // Test the actual manager finding function
    echo "<h4>Sample Employees and Their Actual Managers:</h4>";

    $employees = $db->fetchAll("SELECT epf_number, name, employment_level_id
                               FROM employees
                               WHERE is_active = 1
                               AND employment_level_id > 3
                               ORDER BY employment_level_id DESC, name
                               LIMIT 10");

    if (!empty($employees)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th style='padding: 8px;'>Employee</th>";
        echo "<th style='padding: 8px;'>Employee Level</th>";
        echo "<th style='padding: 8px;'>Actual Manager</th>";
        echo "<th style='padding: 8px;'>Manager Level</th>";
        echo "</tr>";

        foreach ($employees as $employee) {
            $manager = findActualManager($db, $employee['epf_number']);

            echo "<tr>";
            echo "<td style='padding: 5px;'>" . htmlspecialchars($employee['name']) . " (EPF: " . $employee['epf_number'] . ")</td>";
            echo "<td style='padding: 5px;'>Level " . $employee['employment_level_id'] . "</td>";

            if ($manager) {
                echo "<td style='padding: 5px; color: green;'>" . htmlspecialchars($manager['name']) . " (EPF: " . $manager['epf_number'] . ")</td>";
                echo "<td style='padding: 5px; color: green;'>Level " . $manager['employment_level_id'] . "</td>";
            } else {
                echo "<td style='padding: 5px; color: red;'>No Manager Found</td>";
                echo "<td style='padding: 5px; color: red;'>-</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }

    // Test the employee list function
    echo "<br><h4>Testing getEmployeeListWithActualManagers Function:</h4>";
    echo "Loading first 5 employees with their actual managers...<br><br>";

    $employeeList = getEmployeeListWithActualManagers($db);

    if (!empty($employeeList)) {
        $count = 0;
        foreach ($employeeList as $employee) {
            if ($count >= 5) break;

            echo "<strong>" . htmlspecialchars($employee['name']) . "</strong> (EPF: " . $employee['epf_number'] . ")<br>";
            echo "&nbsp;&nbsp;Designation: " . htmlspecialchars($employee['designation']) . "<br>";
            echo "&nbsp;&nbsp;Department: " . htmlspecialchars($employee['department']) . "<br>";

            if ($employee['manager_epf']) {
                echo "&nbsp;&nbsp;<span style='color: green;'>✓ Manager: " . htmlspecialchars($employee['manager_name']) . " (EPF: " . $employee['manager_epf'] . ")</span><br>";
            } else {
                echo "&nbsp;&nbsp;<span style='color: red;'>✗ No manager found</span><br>";
            }
            echo "<br>";

            $count++;
        }
    }

    echo "<h4>Summary Statistics:</h4>";
    $totalEmployees = count($employeeList);
    $employeesWithManagers = 0;

    foreach ($employeeList as $employee) {
        if ($employee['manager_epf']) {
            $employeesWithManagers++;
        }
    }

    echo "Total Employees: $totalEmployees<br>";
    echo "Employees with Actual Managers: $employeesWithManagers<br>";
    echo "Coverage: " . round(($employeesWithManagers / $totalEmployees) * 100, 1) . "%<br>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

<style>
body { font-family: Arial, sans-serif; padding: 20px; }
table { margin: 10px 0; }
th { background-color: #f0f0f0; }
</style>

<br>
<a href="employee_list.php" style="display: inline-block; padding: 8px 16px; background: #007bff; color: white; text-decoration: none; border-radius: 4px;">Test Employee List</a>