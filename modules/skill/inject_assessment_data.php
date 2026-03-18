<?php
require_once '../../config/config.php';

$db = new Database();

echo "<h2>Inject Sample Assessment Data</h2>";

try {
    // Get year 2026 ID
    $year = $db->fetch("SELECT id FROM skill_matrix_years WHERE year = 2026");
    if (!$year) {
        echo "<p style='color: red;'>Error: Year 2026 not found!</p>";
        exit;
    }
    $year_id = $year['id'];

    // Get sample employees (first few EPF numbers)
    $employees = $db->fetchAll("SELECT epf_number, name FROM employees WHERE is_active = 1 ORDER BY CAST(epf_number AS UNSIGNED) LIMIT 5");

    if (empty($employees)) {
        echo "<p style='color: red;'>Error: No active employees found!</p>";
        exit;
    }

    // Get manager EPF (using first manager we find)
    $manager = $db->fetch("SELECT epf_number FROM employees WHERE employment_level_id IN (2,3) AND is_active = 1 LIMIT 1");
    $manager_epf = $manager['epf_number'] ?? '91';

    // Get all skills
    $skills = $db->fetchAll("SELECT id, skill_name, category_id FROM skill_matrix_skills WHERE year_id = ? ORDER BY category_id, display_order", [$year_id]);

    if (empty($skills)) {
        echo "<p style='color: red;'>Error: No skills found for year 2026!</p>";
        exit;
    }

    echo "<h3>Creating Sample Assessment Data...</h3>";
    echo "<p>Using Manager EPF: $manager_epf</p>";

    // Clear existing assessment data
    $db->query("DELETE FROM skill_matrix_assessments WHERE year_id = ?", [$year_id]);
    echo "<p>✅ Cleared existing assessment data</p>";

    $total_assessments = 0;

    foreach ($employees as $employee) {
        $employee_epf = $employee['epf_number'];
        $employee_name = $employee['name'];

        echo "<h4>Creating assessments for EPF $employee_epf - " . htmlspecialchars($employee_name) . "</h4>";

        $assessments_for_employee = 0;

        // Create assessments for ALL skills
        foreach ($skills as $index => $skill) {
            // Create assessment for every skill
            {
                // Generate realistic ratings (following existing successful pattern)
                $current = rand(1, 5);

                // Target is usually same or higher than current (but ensure valid gap)
                $target = rand($current, 5);

                // Gap calculation - use absolute value to match existing data pattern
                $gap = abs($target - $current);

                // Ensure gap is within reasonable bounds (existing data shows gap = 2)
                if ($gap > 4) {
                    $gap = rand(1, 3); // Keep gap reasonable
                }

                try {
                    $db->query("INSERT INTO skill_matrix_assessments (year_id, employee_epf, manager_epf, skill_id, target_rating, current_rating, gap_rating) VALUES (?, ?, ?, ?, ?, ?, ?)",
                        [$year_id, $employee_epf, $manager_epf, $skill['id'], $target, $current, $gap]);

                    echo "✅ " . htmlspecialchars($skill['skill_name']) . " - Current: $current, Target: $target, Gap: $gap<br>";
                    $assessments_for_employee++;
                    $total_assessments++;
                } catch (Exception $e) {
                    echo "❌ Error with " . htmlspecialchars($skill['skill_name']) . ": " . $e->getMessage() . "<br>";
                }
            }
        }

        echo "<p><strong>Created $assessments_for_employee assessments for this employee</strong></p><br>";
    }

    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<strong>✅ Success!</strong> Created $total_assessments sample assessments for " . count($employees) . " employees.";
    echo "</div>";

    // Show summary by employee
    echo "<h3>Assessment Summary:</h3>";
    $summary = $db->fetchAll("SELECT
                                e.epf_number,
                                e.name,
                                COUNT(a.id) as assessment_count,
                                AVG(a.current_rating) as avg_current,
                                AVG(a.target_rating) as avg_target,
                                AVG(a.gap_rating) as avg_gap
                              FROM employees e
                              LEFT JOIN skill_matrix_assessments a ON e.epf_number = a.employee_epf AND a.year_id = ?
                              WHERE e.is_active = 1 AND a.id IS NOT NULL
                              GROUP BY e.epf_number, e.name
                              ORDER BY CAST(e.epf_number AS UNSIGNED)", [$year_id]);

    if (!empty($summary)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>EPF</th><th>Employee Name</th><th>Assessments</th><th>Avg Current</th><th>Avg Target</th><th>Avg Gap</th></tr>";
        foreach ($summary as $row) {
            echo "<tr>";
            echo "<td>" . $row['epf_number'] . "</td>";
            echo "<td>" . htmlspecialchars($row['name']) . "</td>";
            echo "<td>" . $row['assessment_count'] . "</td>";
            echo "<td>" . number_format($row['avg_current'], 1) . "</td>";
            echo "<td>" . number_format($row['avg_target'], 1) . "</td>";
            echo "<td>" . number_format($row['avg_gap'], 1) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

} catch (Exception $e) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<strong>❌ Error:</strong> " . $e->getMessage();
    echo "</div>";
}

echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li><a href='assessment_working.php?year=1&employee=" . ($employees[0]['epf_number'] ?? '7') . "'>View Assessment Form</a> - Should show existing assessments</li>";
echo "<li><a href='reports.php'>View Reports</a> - Should show assessment data</li>";
echo "<li><strong>Check Database:</strong> skill_matrix_assessments table should now have sample data</li>";
echo "</ol>";
?>