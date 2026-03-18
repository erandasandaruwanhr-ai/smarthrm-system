<?php
require_once '../../config/config.php';
require_once '../../includes/auth_check.php';

if (!isSuperAdmin()) {
    die("Access denied.");
}

$db = new Database();

echo "<h2>Goal Templates Debug</h2>";

try {
    // Get templates for year 2026
    $templates = $db->fetchAll("
        SELECT goal_section, goal_subsection, main_goals, activities, measurement_criteria, weightage
        FROM goal_setting_templates gst
        INNER JOIN goal_setting_periods gsp ON gst.period_id = gsp.id
        WHERE gsp.year = 2026
        ORDER BY goal_section, goal_subsection
    ");

    echo "<h3>Total Templates: " . count($templates) . "</h3>";

    foreach ($templates as $i => $template) {
        $dots = substr_count($template['goal_subsection'], '.');
        $type = ($dots == 2) ? "MAIN SECTION" : "SUB-ITEM";

        echo "<p><strong>[$i]</strong> " . $template['goal_subsection'] . " | Dots: $dots | Type: $type</p>";
    }

    // Test the logic
    echo "<h3>Section Counter Test:</h3>";
    $section_counter = 0;
    $sub_item_counter = 0;

    foreach ($templates as $template) {
        if (substr_count($template['goal_subsection'], '.') == 2) {
            $section_counter++;
            $sub_item_counter = 0;
            echo "<p style='color:blue;'><strong>MAIN SECTION:</strong> " . $template['goal_subsection'] . " | Counter now: $section_counter</p>";
        } else {
            $sub_item_counter++;
            echo "<p style='color:green;'>SUB-ITEM: " . $template['goal_subsection'] . " | Section: $section_counter | Sub: $sub_item_counter</p>";
            if ($sub_item_counter == 1) {
                echo "<p style='color:red;'>→ Would show section number: $section_counter</p>";
            }
        }
    }

} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}
?>