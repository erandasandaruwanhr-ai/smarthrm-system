<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

echo "<h2>Debug User Role Information</h2>";

echo "<h3>Current User Details:</h3>";
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Field</th><th>Value</th></tr>";
foreach ($user as $key => $value) {
    echo "<tr><td><strong>$key</strong></td><td>" . htmlspecialchars($value ?? 'NULL') . "</td></tr>";
}
echo "</table>";

echo "<h3>Role Checks:</h3>";
echo "<ul>";
echo "<li>isAdmin(): " . (isAdmin() ? 'TRUE' : 'FALSE') . "</li>";
echo "<li>isSuperAdmin(): " . (isSuperAdmin() ? 'TRUE' : 'FALSE') . "</li>";
echo "<li>user['role']: " . htmlspecialchars($user['role'] ?? 'NULL') . "</li>";
echo "<li>user['role'] === 'manager': " . (($user['role'] ?? '') === 'manager' ? 'TRUE' : 'FALSE') . "</li>";
echo "</ul>";

// Check employment level
echo "<h3>Employment Level Check:</h3>";
$employment_level = $db->fetch("SELECT employment_level_id, el.level_name
                               FROM employees e
                               LEFT JOIN employment_levels el ON e.employment_level_id = el.id
                               WHERE e.epf_number = ?", [$user['epf_number']]);
if ($employment_level) {
    echo "<p>Employment Level ID: " . $employment_level['employment_level_id'] . "</p>";
    echo "<p>Employment Level Name: " . htmlspecialchars($employment_level['level_name'] ?? 'Unknown') . "</p>";
} else {
    echo "<p>No employment level found</p>";
}

// Check who reports to this user
echo "<h3>Direct Reports:</h3>";
$direct_reports = $db->fetchAll("SELECT epf_number, name FROM employees WHERE reports_to = ? AND is_active = 1", [$user['epf_number']]);
if ($direct_reports) {
    echo "<ol>";
    foreach ($direct_reports as $report) {
        echo "<li>" . htmlspecialchars($report['epf_number']) . " - " . htmlspecialchars($report['name']) . "</li>";
    }
    echo "</ol>";
} else {
    echo "<p>No direct reports found</p>";
}

echo "<p><a href='employee_list.php'>Back to Employee List</a></p>";
?>