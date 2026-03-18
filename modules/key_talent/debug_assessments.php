<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';

$user = getCurrentUser();
$database = new Database();

// Check if user is manager or superadmin
if (!in_array($user['account_type'], ['manager', 'superadmin'])) {
    echo "Access denied";
    exit();
}

echo "<h3>Debug: Assessment Data</h3>";

try {
    // Show all assessments
    echo "<h4>All KTI Assessments:</h4>";
    $all_assessments = $database->fetchAll("SELECT * FROM kti_assessments ORDER BY employee_epf");

    if (empty($all_assessments)) {
        echo "<p>No assessments found in database.</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>ID</th><th>Form ID</th><th>Manager EPF</th><th>Employee EPF</th><th>Employee Name</th><th>Submitted At</th></tr>";
        foreach ($all_assessments as $assessment) {
            $highlight = ($assessment['employee_epf'] === '33') ? 'background: #ffcccc;' : '';
            echo "<tr style='{$highlight}'>";
            echo "<td>{$assessment['id']}</td>";
            echo "<td>{$assessment['form_id']}</td>";
            echo "<td>{$assessment['manager_epf']}</td>";
            echo "<td>{$assessment['employee_epf']}</td>";
            echo "<td>{$assessment['employee_name']}</td>";
            echo "<td>{$assessment['submitted_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // Show current user info
    echo "<h4>Current User Info:</h4>";
    echo "<p>EPF: {$user['epf_number']}, Account Type: {$user['account_type']}</p>";

    // Show current form info
    $form_id = isset($_GET['form_id']) ? (int)$_GET['form_id'] : 2;
    echo "<h4>Form ID being checked: {$form_id}</h4>";

    // Show specific check for EPF 33
    echo "<h4>Specific check for EPF 33:</h4>";
    $check_33 = $database->fetchAll("SELECT * FROM kti_assessments WHERE employee_epf = '33' AND form_id = ?", [$form_id]);

    if (empty($check_33)) {
        echo "<p style='color: green;'>✅ No assessment found for EPF 33 in form {$form_id}</p>";
    } else {
        echo "<p style='color: red;'>❌ Assessment exists for EPF 33:</p>";
        foreach ($check_33 as $assessment) {
            echo "<ul>";
            echo "<li>Manager EPF: {$assessment['manager_epf']}</li>";
            echo "<li>Form ID: {$assessment['form_id']}</li>";
            echo "<li>Employee EPF: {$assessment['employee_epf']}</li>";
            echo "<li>Submitted: {$assessment['submitted_at']}</li>";
            echo "</ul>";
        }
    }

    // Test the LEFT JOIN specifically
    echo "<h4>Testing LEFT JOIN for EPF 33:</h4>";
    $manager_epf = $user['epf_number']; // or specific manager EPF
    echo "<p>Testing with Manager EPF: {$manager_epf}, Form ID: {$form_id}</p>";

    $join_test = $database->fetchAll("
        SELECT
            e.epf_number,
            e.name,
            ka.id as assessment_id,
            ka.manager_epf as assessment_manager,
            ka.form_id as assessment_form
        FROM employees e
        LEFT JOIN kti_assessments ka ON e.epf_number = ka.employee_epf AND ka.manager_epf = ? AND ka.form_id = ?
        WHERE e.epf_number = '33'
    ", [$manager_epf, $form_id]);

    if (!empty($join_test)) {
        $result = $join_test[0];
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Value</th></tr>";
        echo "<tr><td>Employee EPF</td><td>{$result['epf_number']}</td></tr>";
        echo "<tr><td>Employee Name</td><td>{$result['name']}</td></tr>";
        echo "<tr><td>Assessment ID</td><td>" . ($result['assessment_id'] ?: 'NULL') . "</td></tr>";
        echo "<tr><td>Assessment Manager</td><td>" . ($result['assessment_manager'] ?: 'NULL') . "</td></tr>";
        echo "<tr><td>Assessment Form</td><td>" . ($result['assessment_form'] ?: 'NULL') . "</td></tr>";
        echo "</table>";

        if ($result['assessment_id']) {
            echo "<p style='color: green;'>✅ JOIN found assessment - employee should be FILTERED OUT</p>";
        } else {
            echo "<p style='color: red;'>❌ JOIN did NOT find assessment - employee would APPEAR in dropdown</p>";
            echo "<p><strong>This is the problem!</strong> The LEFT JOIN conditions don't match the existing assessment data.</p>";
        }
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='assessment_form.php?form_id={$form_id}'>Back to Assessment Form</a></p>";
?>