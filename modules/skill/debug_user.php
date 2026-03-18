<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once 'hierarchy_functions.php';

$user = getCurrentUser();
$db = new Database();

echo "<h3>Debug Current User</h3>";

echo "<h4>Logged-in User Details:</h4>";
echo "<pre>" . print_r($user, true) . "</pre>";

// Check user's employment level
try {
    $userDetails = $db->fetch("SELECT e.*, el.level_name
                              FROM employees e
                              LEFT JOIN employment_levels el ON e.employment_level_id = el.id
                              WHERE e.epf_number = ?", [$user['epf_number']]);

    echo "<h4>User Employment Details:</h4>";
    echo "EPF: " . htmlspecialchars($userDetails['epf_number']) . "<br>";
    echo "Name: " . htmlspecialchars($userDetails['name']) . "<br>";
    echo "Employment Level ID: " . $userDetails['employment_level_id'] . "<br>";
    echo "Employment Level Name: " . htmlspecialchars($userDetails['level_name']) . "<br>";

    echo "<h4>Is this user a Manager or GM?</h4>";
    if ($userDetails['employment_level_id'] == 3) {
        echo "<span style='color: green;'>✓ This user is a MANAGER (Level 3)</span><br>";
    } elseif ($userDetails['employment_level_id'] == 2) {
        echo "<span style='color: green;'>✓ This user is a GM (Level 2)</span><br>";
    } else {
        echo "<span style='color: red;'>✗ This user is NOT a Manager or GM (Level " . $userDetails['employment_level_id'] . ")</span><br>";
        echo "<strong>Note:</strong> Only Managers (Level 3) and GMs (Level 2) can conduct skill assessments.<br>";
    }

} catch (Exception $e) {
    echo "Error loading user details: " . $e->getMessage();
}

// Test employees under this manager
echo "<br><h4>Employees Under This Manager:</h4>";
try {
    $employees = getEmployeesUnderManager($db, $user['epf_number']);

    if (empty($employees)) {
        echo "<span style='color: red;'>No employees found under this manager.</span><br>";
        echo "This could mean:<br>";
        echo "1. This user is not a Manager (Level 3) or GM (Level 2)<br>";
        echo "2. No employees report to this manager hierarchically<br>";
        echo "3. There's an issue with the hierarchy function<br>";
    } else {
        echo "<span style='color: green;'>Found " . count($employees) . " employees:</span><br>";
        foreach ($employees as $emp) {
            echo "- " . htmlspecialchars($emp['name']) . " (EPF: " . $emp['epf_number'] . ")<br>";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Alternative: Check direct reports
echo "<br><h4>Alternative - Direct Reports:</h4>";
try {
    $directReports = $db->fetchAll("SELECT epf_number, name FROM employees WHERE reports_to = ? AND is_active = 1", [$user['epf_number']]);

    if (empty($directReports)) {
        echo "No direct reports found.<br>";
    } else {
        echo "Direct reports (" . count($directReports) . "):<br>";
        foreach ($directReports as $emp) {
            echo "- " . htmlspecialchars($emp['name']) . " (EPF: " . $emp['epf_number'] . ")<br>";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

<style>
body { font-family: Arial, sans-serif; padding: 20px; }
</style>