<?php
require_once '../../config/config.php';

$db = new Database();

echo "<h2>Inject Sample Skills Data</h2>";

// Sample skills data for each category
$sample_skills = [
    1 => [ // Technical Skills
        1 => ['name' => 'Programming & Software Development', 'desc' => 'Proficiency in coding languages, frameworks, and software development methodologies'],
        2 => ['name' => 'Data Analysis & Database Management', 'desc' => 'Ability to analyze data, create reports, and manage database systems'],
        3 => ['name' => 'System Administration', 'desc' => 'Managing servers, networks, and IT infrastructure'],
        4 => ['name' => 'Quality Assurance & Testing', 'desc' => 'Testing procedures, quality control, and compliance standards'],
        5 => ['name' => 'Technical Documentation', 'desc' => 'Creating technical manuals, process documentation, and user guides']
    ],
    2 => [ // Leadership & Management
        1 => ['name' => 'Team Leadership', 'desc' => 'Leading teams, delegating tasks, and motivating team members'],
        2 => ['name' => 'Strategic Planning', 'desc' => 'Developing long-term plans and strategic initiatives'],
        3 => ['name' => 'Performance Management', 'desc' => 'Setting goals, conducting evaluations, and managing performance'],
        4 => ['name' => 'Decision Making', 'desc' => 'Making informed decisions under pressure and uncertainty'],
        5 => ['name' => 'Change Management', 'desc' => 'Leading organizational change and transformation initiatives']
    ],
    3 => [ // Communication & Interpersonal Skills
        1 => ['name' => 'Verbal Communication', 'desc' => 'Clear and effective spoken communication in various settings'],
        2 => ['name' => 'Written Communication', 'desc' => 'Professional writing skills for reports, emails, and documentation'],
        3 => ['name' => 'Presentation Skills', 'desc' => 'Delivering engaging and informative presentations'],
        4 => ['name' => 'Active Listening', 'desc' => 'Listening effectively and understanding others perspectives'],
        5 => ['name' => 'Conflict Resolution', 'desc' => 'Managing and resolving workplace conflicts diplomatically']
    ],
    4 => [ // Adaptability & Learning Agility
        1 => ['name' => 'Continuous Learning', 'desc' => 'Commitment to ongoing professional development and skill acquisition'],
        2 => ['name' => 'Flexibility & Adaptation', 'desc' => 'Adapting quickly to changing circumstances and new environments'],
        3 => ['name' => 'Resilience', 'desc' => 'Bouncing back from setbacks and maintaining performance under stress'],
        4 => ['name' => 'Digital Literacy', 'desc' => 'Keeping up with new technologies and digital tools'],
        5 => ['name' => 'Cross-functional Collaboration', 'desc' => 'Working effectively across different departments and disciplines']
    ],
    5 => [ // Innovation & Creativity
        1 => ['name' => 'Creative Problem Solving', 'desc' => 'Generating innovative solutions to complex challenges'],
        2 => ['name' => 'Design Thinking', 'desc' => 'Using human-centered design approaches to solve problems'],
        3 => ['name' => 'Process Improvement', 'desc' => 'Identifying and implementing process enhancements and optimizations'],
        4 => ['name' => 'Innovation Management', 'desc' => 'Leading innovation initiatives and managing creative projects'],
        5 => ['name' => 'Research & Development', 'desc' => 'Conducting research and developing new products or services']
    ],
    6 => [ // Problem-Solving & Critical Thinking
        1 => ['name' => 'Analytical Thinking', 'desc' => 'Breaking down complex problems into manageable components'],
        2 => ['name' => 'Data-Driven Decision Making', 'desc' => 'Using data and evidence to inform decisions and solutions'],
        3 => ['name' => 'Root Cause Analysis', 'desc' => 'Identifying underlying causes of problems rather than just symptoms'],
        4 => ['name' => 'Logical Reasoning', 'desc' => 'Applying logical principles to evaluate information and arguments'],
        5 => ['name' => 'Systems Thinking', 'desc' => 'Understanding how different parts of an organization interact and influence each other']
    ]
];

try {
    // Get year 2026 ID
    $year = $db->fetch("SELECT id FROM skill_matrix_years WHERE year = 2026");
    if (!$year) {
        echo "<p style='color: red;'>Error: Year 2026 not found!</p>";
        exit;
    }
    $year_id = $year['id'];

    echo "<p>Using Year 2026 (ID: $year_id)</p>";

    // Clear existing skills for 2026
    echo "<h3>Clearing existing skills for 2026...</h3>";
    $deleted = $db->query("DELETE FROM skill_matrix_skills WHERE year_id = ?", [$year_id]);
    echo "<p>✅ Cleared existing skills</p>";

    // Insert sample skills
    echo "<h3>Inserting sample skills...</h3>";
    $total_inserted = 0;

    foreach ($sample_skills as $category_id => $skills) {
        // Get category name
        $category = $db->fetch("SELECT name FROM skill_matrix_categories WHERE id = ?", [$category_id]);
        $category_name = $category['name'] ?? "Category $category_id";

        echo "<h4>$category_name:</h4>";
        echo "<ol>";

        foreach ($skills as $display_order => $skill_data) {
            try {
                $db->query("INSERT INTO skill_matrix_skills (year_id, category_id, skill_name, skill_description, display_order) VALUES (?, ?, ?, ?, ?)",
                    [$year_id, $category_id, $skill_data['name'], $skill_data['desc'], $display_order]);

                echo "<li>✅ " . htmlspecialchars($skill_data['name']) . "</li>";
                $total_inserted++;
            } catch (Exception $e) {
                echo "<li>❌ Error: " . htmlspecialchars($skill_data['name']) . " - " . $e->getMessage() . "</li>";
            }
        }
        echo "</ol>";
    }

    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<strong>✅ Success!</strong> Inserted $total_inserted sample skills across all 6 categories.";
    echo "</div>";

    echo "<h3>Summary:</h3>";
    echo "<ul>";
    echo "<li><strong>Technical Skills:</strong> 5 skills added</li>";
    echo "<li><strong>Leadership & Management:</strong> 5 skills added</li>";
    echo "<li><strong>Communication & Interpersonal:</strong> 5 skills added</li>";
    echo "<li><strong>Adaptability & Learning:</strong> 5 skills added</li>";
    echo "<li><strong>Innovation & Creativity:</strong> 5 skills added</li>";
    echo "<li><strong>Problem-Solving & Critical Thinking:</strong> 5 skills added</li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<strong>❌ Error:</strong> " . $e->getMessage();
    echo "</div>";
}

echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li><a href='setup_working.php?year_id=1'>Check Setup Form</a> - All categories should now show 5 skills each</li>";
echo "<li><a href='check_db_data.php'>Verify Database</a> - Confirm all 30 skills are saved</li>";
echo "<li><a href='assessment_working.php'>Test Assessment Form</a> - Skills should be available for assessment</li>";
echo "</ol>";
?>