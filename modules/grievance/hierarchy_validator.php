<?php
// Validate hierarchical access logic works for all patterns, not just 170→494→91
session_start();
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

echo "<style>body{font-family: Arial; padding: 20px;} .card{background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; border: 1px solid #ddd;} .success{border-left: 5px solid #28a745;} .warning{border-left: 5px solid #ffc107;} .danger{border-left: 5px solid #dc3545;} table{border-collapse: collapse; width: 100%;} th,td{border: 1px solid #ddd; padding: 8px;} th{background: #f2f2f2;}</style>";

echo "<h1>🔍 Hierarchy Access Pattern Validator</h1>\n";

$user = getCurrentUser();
$db = new Database();

echo "<div class='card'><h2>Current User</h2>\n";
echo "EPF: {$user['epf_number']}<br>\n";
echo "Name: {$user['name']}<br>\n";
echo "Account Type: {$user['account_type']}<br>\n";
echo "Department: {$user['department']}<br>\n";
echo "</div>\n";

try {
    echo "<div class='card'><h2>🔍 Find All Hierarchies You Can Access</h2>\n";

    // Method 1: Direct subordinates (Supervisor access)
    $direct_subordinates = $db->fetchAll(
        "SELECT epf_number, name, department FROM employees WHERE reports_to = ? AND is_active = 1",
        [$user['epf_number']]
    );

    if (!empty($direct_subordinates)) {
        echo "<h3>✅ Direct Subordinates (Supervisor Access)</h3>\n";
        echo "<table><tr><th>EPF</th><th>Name</th><th>Department</th><th>Cross-Dept?</th></tr>\n";
        foreach ($direct_subordinates as $sub) {
            $cross_dept = ($sub['department'] !== $user['department']);
            echo "<tr" . ($cross_dept ? " style='background: #fff3cd;'" : "") . ">\n";
            echo "<td>{$sub['epf_number']}</td><td>{$sub['name']}</td><td>{$sub['department']}</td>\n";
            echo "<td>" . ($cross_dept ? "YES ⚠️" : "No") . "</td></tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "<p>No direct subordinates found.</p>\n";
    }

    // Method 2: Hierarchical subordinates (Manager access - 2 levels down)
    $hierarchical_subordinates = $db->fetchAll(
        "SELECT e1.epf_number, e1.name, e1.department, e2.epf_number as supervisor_epf, e2.name as supervisor_name
         FROM employees e1
         INNER JOIN employees e2 ON e1.reports_to = e2.epf_number
         WHERE e2.reports_to = ? AND e1.is_active = 1",
        [$user['epf_number']]
    );

    if (!empty($hierarchical_subordinates)) {
        echo "<h3>✅ Hierarchical Subordinates (Manager Access)</h3>\n";
        echo "<table><tr><th>Employee</th><th>Emp Dept</th><th>Via Supervisor</th><th>Cross-Dept Pattern?</th></tr>\n";
        foreach ($hierarchical_subordinates as $sub) {
            $pattern = "{$sub['department']} → {$user['department']}";
            $cross_dept = ($sub['department'] !== $user['department']);
            echo "<tr" . ($cross_dept ? " style='background: #fff3cd;'" : "") . ">\n";
            echo "<td>{$sub['epf_number']}: {$sub['name']}</td>\n";
            echo "<td>{$sub['department']}</td>\n";
            echo "<td>{$sub['supervisor_epf']}: {$sub['supervisor_name']}</td>\n";
            echo "<td>$pattern " . ($cross_dept ? "⚠️" : "✓") . "</td></tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "<p>No hierarchical subordinates found.</p>\n";
    }

    // Method 3: Check specific cases
    echo "<h3>🎯 Test Specific Access Cases</h3>\n";

    $all_accessible_epfs = [];

    // Add direct subordinates
    foreach ($direct_subordinates as $sub) {
        $all_accessible_epfs[] = $sub['epf_number'];
    }

    // Add hierarchical subordinates
    foreach ($hierarchical_subordinates as $sub) {
        $all_accessible_epfs[] = $sub['epf_number'];
    }

    if (!empty($all_accessible_epfs)) {
        echo "<p><strong>You should be able to access cases from these EPF numbers:</strong> " . implode(', ', $all_accessible_epfs) . "</p>\n";

        // Check which of these have actual grievance cases
        $placeholders = str_repeat('?,', count($all_accessible_epfs) - 1) . '?';
        $cases_found = $db->fetchAll(
            "SELECT employee_epf, case_id, subject, employee_department FROM grievances WHERE employee_epf IN ($placeholders) ORDER BY employee_epf",
            $all_accessible_epfs
        );

        if (!empty($cases_found)) {
            echo "<div class='card success'>\n";
            echo "<h4>✅ Cases You Should See in Grievance List</h4>\n";
            echo "<table><tr><th>Case ID</th><th>Employee</th><th>Dept</th><th>Subject</th></tr>\n";
            foreach ($cases_found as $case) {
                echo "<tr><td>{$case['case_id']}</td><td>{$case['employee_epf']}</td><td>{$case['employee_department']}</td><td>{$case['subject']}</td></tr>\n";
            }
            echo "</table>\n";
            echo "<p><a href='grievance_list.php' target='_blank'>🔗 Open Grievance List to Verify</a></p>\n";
            echo "</div>\n";
        } else {
            echo "<div class='card warning'>\n";
            echo "<h4>⚠️ No Active Cases Found</h4>\n";
            echo "<p>None of your accessible employees have grievance cases currently.</p>\n";
            echo "</div>\n";
        }
    } else {
        echo "<div class='card warning'>\n";
        echo "<h4>⚠️ No Accessible Employees</h4>\n";
        echo "<p>You have no direct or hierarchical subordinates.</p>\n";
        echo "</div>\n";
    }

    // Test the known 170→494→91 case specifically
    if ($user['epf_number'] === '91' || $user['epf_number'] === '494') {
        echo "<div class='card success'>\n";
        echo "<h3>✅ Testing Known Case: 170→494→91</h3>\n";

        $case_170 = $db->fetchAll("SELECT case_id, subject FROM grievances WHERE employee_epf = '170'");
        if (!empty($case_170)) {
            echo "<p><strong>Employee 170 cases that should be accessible:</strong></p>\n";
            foreach ($case_170 as $case) {
                echo "<p>- {$case['case_id']}: {$case['subject']}</p>\n";
            }

            if ($user['epf_number'] === '91') {
                echo "<p>As Manager 91, you should see these via <em>hierarchical access</em> (170→494→91)</p>\n";
            } else {
                echo "<p>As Supervisor 494, you should see these via <em>direct subordinate access</em> (170→494)</p>\n";
            }
        } else {
            echo "<p>No cases found for Employee 170</p>\n";
        }
        echo "</div>\n";
    }

} catch (Exception $e) {
    echo "<div class='card danger'><h3>❌ Error</h3><p>" . $e->getMessage() . "</p></div>\n";
}
?>

<div class="card">
    <h3>🧪 How to Test Other Hierarchies</h3>
    <ol>
        <li>Log in as different managers/supervisors using the <a href="login_helper.php">Login Helper</a></li>
        <li>Run this validator for each user</li>
        <li>Check if the grievance list shows the expected cases</li>
        <li>Test both same-department and cross-department hierarchies</li>
    </ol>
    <p><strong>The fix should work for ANY hierarchy pattern, not just 170→494→91</strong></p>
</div>