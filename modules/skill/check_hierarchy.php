<?php
require_once '../../config/config.php';

try {
    $db = new Database();

    echo "<h3>Employee Hierarchy Analysis</h3>";

    // Check employment levels table
    echo "<h4>Employment Levels:</h4>";
    try {
        $levels = $db->fetchAll("SELECT * FROM employment_levels ORDER BY id");
        foreach ($levels as $level) {
            echo "- Level " . $level['id'] . ": " . htmlspecialchars($level['level_name'] ?? $level['name'] ?? 'Unknown') . "<br>";
        }
    } catch (Exception $e) {
        echo "Error loading employment levels: " . $e->getMessage() . "<br>";
    }

    // Sample employees with their reporting structure
    echo "<br><h4>Sample Employee Reporting Chain:</h4>";
    try {
        $employees = $db->fetchAll("SELECT
            e.epf_number,
            e.name,
            e.employment_level_id,
            el.level_name as employment_level,
            e.reports_to,
            supervisor.name as reports_to_name,
            supervisor.employment_level_id as supervisor_level
        FROM employees e
        LEFT JOIN employment_levels el ON e.employment_level_id = el.id
        LEFT JOIN employees supervisor ON e.reports_to = supervisor.epf_number
        WHERE e.is_active = 1
        ORDER BY e.employment_level_id, e.name
        LIMIT 10");

        if (!empty($employees)) {
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr style='background: #f0f0f0;'>";
            echo "<th style='padding: 8px;'>EPF</th>";
            echo "<th style='padding: 8px;'>Name</th>";
            echo "<th style='padding: 8px;'>Level ID</th>";
            echo "<th style='padding: 8px;'>Level Name</th>";
            echo "<th style='padding: 8px;'>Reports To</th>";
            echo "<th style='padding: 8px;'>Reports To Name</th>";
            echo "<th style='padding: 8px;'>Supervisor Level</th>";
            echo "</tr>";

            foreach ($employees as $emp) {
                echo "<tr>";
                echo "<td style='padding: 5px;'>" . htmlspecialchars($emp['epf_number']) . "</td>";
                echo "<td style='padding: 5px;'>" . htmlspecialchars($emp['name']) . "</td>";
                echo "<td style='padding: 5px;'>" . ($emp['employment_level_id'] ?? 'NULL') . "</td>";
                echo "<td style='padding: 5px;'>" . htmlspecialchars($emp['employment_level'] ?? 'N/A') . "</td>";
                echo "<td style='padding: 5px;'>" . htmlspecialchars($emp['reports_to'] ?? 'N/A') . "</td>";
                echo "<td style='padding: 5px;'>" . htmlspecialchars($emp['reports_to_name'] ?? 'N/A') . "</td>";
                echo "<td style='padding: 5px;'>" . ($emp['supervisor_level'] ?? 'NULL') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "No employee data found.";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "<br>";
    }

    // Test hierarchy traversal for a specific employee
    echo "<br><h4>Hierarchy Traversal Test:</h4>";
    echo "Let's find the actual manager for a sample employee...<br><br>";

    try {
        // Get a sample employee who is not a manager
        $sampleEmployee = $db->fetch("SELECT * FROM employees
                                     WHERE is_active = 1
                                     AND employment_level_id > 3
                                     AND reports_to IS NOT NULL
                                     LIMIT 1");

        if ($sampleEmployee) {
            echo "<strong>Sample Employee:</strong> " . htmlspecialchars($sampleEmployee['name']) .
                 " (EPF: " . $sampleEmployee['epf_number'] . ", Level: " . $sampleEmployee['employment_level_id'] . ")<br>";

            // Traverse up the hierarchy to find manager (level 3) or GM (level 2)
            $currentEpf = $sampleEmployee['reports_to'];
            $level = 0;
            $manager = null;

            while ($currentEpf && $level < 10) { // Prevent infinite loops
                $level++;

                $superior = $db->fetch("SELECT epf_number, name, employment_level_id, reports_to
                                       FROM employees
                                       WHERE epf_number = ? AND is_active = 1", [$currentEpf]);

                if (!$superior) break;

                echo "Level $level: " . htmlspecialchars($superior['name']) .
                     " (EPF: " . $superior['epf_number'] . ", Employment Level: " . $superior['employment_level_id'] . ")<br>";

                // Check if this person is a Manager (level 3) or GM (level 2)
                if ($superior['employment_level_id'] == 3 || $superior['employment_level_id'] == 2) {
                    $manager = $superior;
                    echo "<strong style='color: green;'>✓ FOUND MANAGER: " . htmlspecialchars($manager['name']) . "</strong><br>";
                    break;
                }

                $currentEpf = $superior['reports_to'];
            }

            if (!$manager) {
                echo "<strong style='color: red;'>✗ No manager (level 2-3) found in hierarchy</strong><br>";
            }
        } else {
            echo "No suitable sample employee found for testing.";
        }
    } catch (Exception $e) {
        echo "Hierarchy test error: " . $e->getMessage() . "<br>";
    }

} catch (Exception $e) {
    echo "Database error: " . $e->getMessage();
}
?>

<style>
body { font-family: Arial, sans-serif; padding: 20px; }
table { margin: 10px 0; }
th { background-color: #f0f0f0; }
</style>