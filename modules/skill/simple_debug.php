<?php
require_once '../../config/config.php';

try {
    $db = new Database();

    echo "<h3>Simple Debug</h3>";

    // Check session
    if (isset($_SESSION['user_epf'])) {
        $userEpf = $_SESSION['user_epf'];
        echo "Session User EPF: $userEpf<br>";

        // Get user details
        $user = $db->fetch("SELECT * FROM employees WHERE epf_number = ?", [$userEpf]);
        if ($user) {
            echo "User: " . htmlspecialchars($user['name']) . "<br>";
            echo "Level: " . $user['employment_level_id'] . "<br>";

            // Check if this is a manager/GM
            if ($user['employment_level_id'] == 3 || $user['employment_level_id'] == 2) {
                echo "<span style='color: green;'>✓ Can conduct assessments (Manager/GM)</span><br>";

                // Get employees under this manager
                echo "<br><h4>Testing Employee Lookup:</h4>";

                // Method 1: Direct reports
                $directReports = $db->fetchAll("SELECT epf_number, name FROM employees WHERE reports_to = ? AND is_active = 1", [$userEpf]);
                echo "Direct reports: " . count($directReports) . "<br>";

                // Method 2: All employees with this person as actual manager
                require_once 'hierarchy_functions.php';
                $managedEmployees = getEmployeesUnderManager($db, $userEpf);
                echo "Managed employees (hierarchy): " . count($managedEmployees) . "<br>";

                if (!empty($managedEmployees)) {
                    echo "<h5>Sample employees:</h5>";
                    for ($i = 0; $i < min(5, count($managedEmployees)); $i++) {
                        echo "- " . htmlspecialchars($managedEmployees[$i]['name']) . " (EPF: " . $managedEmployees[$i]['epf_number'] . ")<br>";
                    }
                }

            } else {
                echo "<span style='color: red;'>✗ Cannot conduct assessments (Level " . $user['employment_level_id'] . ")</span><br>";
            }
        }
    } else {
        echo "No user session found. Please login first.<br>";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

<style>body { font-family: Arial, sans-serif; padding: 20px; }</style>