<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

$user = getCurrentUser();
$db = new Database();

// Check permissions
$isAdmin = isAdmin() || isSuperAdmin();
$canViewSkills = $isAdmin || hasModulePermission($db, 'skills.view_skill_matrix') || hasModulePermission($db, 'skills.complete_skill_assessment');

if (!$canViewSkills) {
    header("Location: index.php");
    exit();
}

// Get parameters
$year_id = isset($_GET['year']) ? intval($_GET['year']) : 1;
$export_format = isset($_GET['format']) ? $_GET['format'] : 'excel';

try {
    // Get year info
    $year_info = $db->fetch("SELECT year FROM skill_matrix_years WHERE id = ?", [$year_id]);
    $year_name = $year_info['year'] ?? $year_id;

    // Get all skills in order (to create consistent columns)
    $skills = $db->fetchAll("SELECT s.id, s.skill_name, s.category_id, c.name as category_name
                            FROM skill_matrix_skills s
                            JOIN skill_matrix_categories c ON s.category_id = c.id
                            WHERE s.year_id = ?
                            ORDER BY c.display_order, s.display_order", [$year_id]);

    // Get all assessment data
    $assessments_data = $db->fetchAll("SELECT
                                        a.*,
                                        e.name as employee_name,
                                        e.designation,
                                        e.department,
                                        m.name as manager_name,
                                        s.skill_name,
                                        c.name as category_name
                                      FROM skill_matrix_assessments a
                                      JOIN employees e ON a.employee_epf = e.epf_number
                                      LEFT JOIN employees m ON a.manager_epf = m.epf_number
                                      JOIN skill_matrix_skills s ON a.skill_id = s.id
                                      JOIN skill_matrix_categories c ON s.category_id = c.id
                                      WHERE a.year_id = ?
                                      ORDER BY CAST(a.employee_epf AS UNSIGNED), c.display_order, s.display_order", [$year_id]);

    // Group assessments by employee
    $employees_data = [];
    foreach ($assessments_data as $assessment) {
        $epf = $assessment['employee_epf'];

        if (!isset($employees_data[$epf])) {
            $employees_data[$epf] = [
                'epf' => $epf,
                'name' => $assessment['employee_name'],
                'designation' => $assessment['designation'],
                'department' => $assessment['department'],
                'manager' => $assessment['manager_name'],
                'date' => date('n/j/Y G:i', strtotime($assessment['submitted_at'])),
                'skills' => []
            ];
        }

        $employees_data[$epf]['skills'][$assessment['skill_id']] = [
            'target' => $assessment['target_rating'],
            'current' => $assessment['current_rating'],
            'gap' => $assessment['gap_rating']
        ];
    }

    // Calculate summary statistics for each employee
    foreach ($employees_data as $epf => &$employee) {
        $targets = [];
        $currents = [];
        $gaps = [];

        foreach ($employee['skills'] as $skill_data) {
            $targets[] = $skill_data['target'];
            $currents[] = $skill_data['current'];
            $gaps[] = $skill_data['gap'];
        }

        if (!empty($targets)) {
            $employee['target_avg'] = round(array_sum($targets) / count($targets), 1);
            $employee['current_avg'] = round(array_sum($currents) / count($currents), 1);
            $employee['gap_avg'] = round(array_sum($gaps) / count($gaps), 1);
            $employee['achievement_percent'] = $employee['target_avg'] > 0 ? round(($employee['current_avg'] / $employee['target_avg']) * 100, 1) : 0;
        } else {
            $employee['target_avg'] = 0;
            $employee['current_avg'] = 0;
            $employee['gap_avg'] = 0;
            $employee['achievement_percent'] = 0;
        }
    }

    if ($export_format === 'excel') {
        // Set Excel headers
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="skill_assessments_pivoted_' . $year_name . '_' . date('Y-m-d') . '.xls"');

        echo '<html><head><style>';
        echo 'table { border-collapse: collapse; }';
        echo 'th, td { border: 1px solid black; padding: 5px; text-align: center; }';
        echo '.rotated { writing-mode: vertical-rl; text-orientation: mixed; min-width: 30px; max-width: 30px; height: 150px; }';
        echo '.employee-info { background-color: #f0f0f0; text-align: left; }';
        echo '</style></head><body>';

        echo '<table>';

        // Create header row
        echo '<tr>';
        echo '<th class="employee-info">Employee EPF</th>';
        echo '<th class="employee-info">Employee Name</th>';
        echo '<th class="employee-info">Designation</th>';
        echo '<th class="employee-info">Department</th>';
        echo '<th class="employee-info">Manager</th>';
        echo '<th class="employee-info">Assessment Date</th>';

        // Add skill columns grouped by category
        $current_category = '';
        foreach ($skills as $skill) {
            if ($skill['category_name'] !== $current_category) {
                $current_category = $skill['category_name'];
                // Add category separator (could be styling in real Excel)
            }
            echo '<th class="rotated">' . htmlspecialchars($skill['skill_name']) . ' (T)</th>';
            echo '<th class="rotated">' . htmlspecialchars($skill['skill_name']) . ' (C)</th>';
            echo '<th class="rotated">' . htmlspecialchars($skill['skill_name']) . ' (G)</th>';
        }

        // Add summary statistics columns
        echo '<th class="employee-info">Target Avg</th>';
        echo '<th class="employee-info">Current Avg</th>';
        echo '<th class="employee-info">Gap Avg</th>';
        echo '<th class="employee-info">Achievement %</th>';
        echo '</tr>';

        // Add category header row
        echo '<tr>';
        echo '<td colspan="6" class="employee-info"><strong>Categories:</strong></td>';
        $current_category = '';
        foreach ($skills as $skill) {
            if ($skill['category_name'] !== $current_category) {
                $current_category = $skill['category_name'];
            }
            echo '<td colspan="3" style="background-color: #e0e0e0;"><small>' . htmlspecialchars($current_category) . '</small></td>';
        }
        // Add summary statistics header
        echo '<td colspan="4" style="background-color: #d0d0d0;"><strong>Summary Stats</strong></td>';
        echo '</tr>';

        // Data rows
        foreach ($employees_data as $employee) {
            echo '<tr>';
            echo '<td class="employee-info">' . htmlspecialchars($employee['epf']) . '</td>';
            echo '<td class="employee-info">' . htmlspecialchars($employee['name']) . '</td>';
            echo '<td class="employee-info">' . htmlspecialchars($employee['designation']) . '</td>';
            echo '<td class="employee-info">' . htmlspecialchars($employee['department']) . '</td>';
            echo '<td class="employee-info">' . htmlspecialchars($employee['manager']) . '</td>';
            echo '<td class="employee-info">' . htmlspecialchars($employee['date']) . '</td>';

            // Add skill data
            foreach ($skills as $skill) {
                $skill_data = $employee['skills'][$skill['id']] ?? null;
                echo '<td>' . ($skill_data ? $skill_data['target'] : '-') . '</td>';
                echo '<td>' . ($skill_data ? $skill_data['current'] : '-') . '</td>';
                echo '<td>' . ($skill_data ? $skill_data['gap'] : '-') . '</td>';
            }

            // Add summary statistics data
            echo '<td class="employee-info">' . $employee['target_avg'] . '</td>';
            echo '<td class="employee-info">' . $employee['current_avg'] . '</td>';
            echo '<td class="employee-info">' . $employee['gap_avg'] . '</td>';
            echo '<td class="employee-info">' . $employee['achievement_percent'] . '%</td>';
            echo '</tr>';
        }

        echo '</table>';
        echo '</body></html>';
        exit;
    }

} catch (Exception $e) {
    echo "Error generating export: " . $e->getMessage();
}

// If not exporting, show preview
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHRM - Pivoted Assessment Export</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .rotated-header {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            min-width: 40px;
            max-width: 40px;
            height: 120px;
            font-size: 11px;
        }
        .skill-header {
            background-color: #f8f9fa;
        }
        .employee-info {
            background-color: #e9ecef;
        }
    </style>
</head>
<body>
    <div class="container-fluid p-4">
        <h2>Skill Assessment Export - Pivoted Format</h2>

        <div class="mb-3">
            <a href="?year=<?php echo $year_id; ?>&format=excel" class="btn btn-success">
                <i class="fas fa-file-excel"></i> Download Excel
            </a>
            <a href="assessment_working.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Assessments
            </a>
        </div>

        <div class="alert alert-info">
            <strong>Format:</strong> Each employee gets one row with all skills as columns.
            Skills are grouped by category and show Target (T), Current (C), and Gap (G) for each skill.
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead>
                    <tr>
                        <th class="employee-info">EPF</th>
                        <th class="employee-info">Name</th>
                        <th class="employee-info">Designation</th>
                        <th class="employee-info">Department</th>
                        <th class="employee-info">Manager</th>
                        <th class="employee-info">Date</th>
                        <?php foreach ($skills as $skill): ?>
                        <th class="rotated-header skill-header"><?php echo htmlspecialchars($skill['skill_name']); ?> (T/C/G)</th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($employees_data, 0, 5) as $employee): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($employee['epf']); ?></td>
                        <td><?php echo htmlspecialchars($employee['name']); ?></td>
                        <td><?php echo htmlspecialchars($employee['designation']); ?></td>
                        <td><?php echo htmlspecialchars($employee['department']); ?></td>
                        <td><?php echo htmlspecialchars($employee['manager']); ?></td>
                        <td><?php echo htmlspecialchars($employee['date']); ?></td>
                        <?php foreach ($skills as $skill):
                            $skill_data = $employee['skills'][$skill['id']] ?? null;
                        ?>
                        <td style="font-size: 11px;">
                            <?php if ($skill_data): ?>
                                <?php echo $skill_data['target']; ?>/<?php echo $skill_data['current']; ?>/<?php echo $skill_data['gap']; ?>
                            <?php else: ?>
                                -/-/-
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p class="text-muted">Showing first 5 employees as preview. Download Excel for complete data.</p>
    </div>
</body>
</html>