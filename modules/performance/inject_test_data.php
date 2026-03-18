<?php
// Test Data Injection for Executive Appraisal Form
// Creates sample data for testing purposes
// Logged in user: EPF 40, Testing with employee EPF 502

require_once '../../config/config.php';

try {
    $db = new Database();
    echo "Starting test data injection for executive appraisal...\n\n";

    // Test data setup
    $current_year = date('Y');
    $appraiser_epf = '40';  // Logged in user
    $appraiser_name = 'Test Manager';
    $appraisee_epf = '502'; // Employee being appraised
    $appraisee_name = 'Test Employee';

    // Check if test record already exists
    $check_sql = "SELECT COUNT(*) as count FROM executive_appraisals
                  WHERE appraiser_epf = ? AND appraisee_epf = ? AND appraisal_year = ?";
    $existing = $db->fetch($check_sql, [$appraiser_epf, $appraisee_epf, $current_year]);

    if ($existing['count'] > 0) {
        echo "⚠️ Test record already exists. Deleting existing record first...\n";
        $delete_sql = "DELETE FROM executive_appraisals
                       WHERE appraiser_epf = ? AND appraisee_epf = ? AND appraisal_year = ?";
        $db->query($delete_sql, [$appraiser_epf, $appraisee_epf, $current_year]);
        echo "✓ Existing record deleted.\n\n";
    }

    // Insert comprehensive test data
    $insert_sql = "INSERT INTO executive_appraisals (
        appraisal_year, appraiser_epf, appraiser_name, appraisee_epf, appraisee_name,
        designation, department, location, joining_date, service_years, evaluation_date,

        -- Category 1: Competency Evaluation
        competency_technical_skills, competency_technical_comments,
        competency_communication, competency_communication_comments,
        competency_teamwork, competency_teamwork_comments,
        competency_leadership, competency_leadership_comments,
        competency_problem_solving, competency_problem_solving_comments,
        competency_adaptability, competency_adaptability_comments,
        competency_time_management, competency_time_management_comments,
        competency_customer_focus, competency_customer_focus_comments,
        competency_remark,

        -- Category 2: Achievements
        achievement_1, achievement_2, achievement_3,

        -- Category 3: Areas for Development
        development_competency_1, development_plan_1,
        development_competency_2, development_plan_2,

        -- Category 4: Core Values
        core_values_respectful_self, core_values_respectful_manager,
        core_values_passionate_self, core_values_passionate_manager,
        core_values_reliable_self, core_values_reliable_manager,

        -- Category 5: Attitudes and Behaviors
        attitude_promises_self, attitude_promises_manager,
        attitude_trust_self, attitude_trust_manager,
        attitude_improvement_self, attitude_improvement_manager,
        attitude_teamwork_self, attitude_teamwork_manager,
        attitude_decisions_self, attitude_decisions_manager,
        attitude_communication_self, attitude_communication_manager,
        attitude_principles_self, attitude_principles_manager,
        attitude_customer_self, attitude_customer_manager,

        -- Category 6: Objectives
        objective_1, objective_2, objective_3,
        objective_1_evaluation, objective_2_evaluation, objective_3_evaluation,

        -- Category 7: Development and Training
        development_training,

        -- Category 8: Future Growth
        future_growth,

        -- Category 9: Manager Performance Feedback
        manager_performance_feedback, manager_improvement_areas,

        -- Category 10: Other Discussion Areas
        discussion_point_1, discussion_point_2, discussion_point_3,

        -- Category 11: Compliance
        compliance_q1, compliance_q1_comments,
        compliance_q2, compliance_q2_comments,
        compliance_q3, compliance_q3_comments,

        -- System fields
        status, created_at, updated_at
    ) VALUES (
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?,

        -- Category 1: Competency Evaluation (1-5 ratings + comments)
        4, 'Demonstrates strong technical skills with room for advanced certifications',
        4, 'Communicates effectively with team and stakeholders',
        5, 'Excellent team player, always willing to help others',
        3, 'Shows leadership potential but needs more experience in leading large projects',
        4, 'Good problem-solving skills, approaches issues methodically',
        4, 'Adapts well to changing requirements and new technologies',
        3, 'Generally manages time well but occasionally struggles with multiple deadlines',
        5, 'Always puts customer needs first and maintains professional service',
        'Overall strong performance across all competencies with clear development opportunities identified.',

        -- Category 2: Achievements
        'Successfully led the implementation of new CRM system, resulting in 25% improvement in customer response time',
        'Mentored 3 junior team members, all of whom received positive performance reviews',
        'Completed advanced project management certification ahead of schedule',

        -- Category 3: Areas for Development
        'Advanced Leadership Skills', 'Enroll in leadership development program and take on larger team leadership role',
        'Strategic Planning', 'Participate in strategic planning workshops and work with senior management on long-term projects',

        -- Category 4: Core Values (1-5 ratings)
        4, 4,  -- Respectful
        5, 4,  -- Passionate
        4, 4,  -- Reliable

        -- Category 5: Attitudes and Behaviors (1-5 ratings)
        4, 4,  -- Promises
        4, 4,  -- Trust
        5, 4,  -- Improvement
        5, 5,  -- Teamwork
        4, 4,  -- Decisions
        4, 4,  -- Communication
        4, 4,  -- Principles
        5, 5,  -- Customer focus

        -- Category 6: Objectives
        'Increase team productivity by 15% through process improvements and automation tools by Q4 " . $current_year . "',
        'Complete PMP certification and apply project management best practices to at least 2 major projects',
        'Develop and implement a knowledge sharing system to improve team collaboration and reduce project delivery time by 10%',
        'Objective 1 achieved with 18% productivity increase through successful implementation of automated workflows',
        'PMP certification completed in Q2, successfully applied methodologies to 3 major projects with improved outcomes',
        'Knowledge sharing system implemented and resulted in 12% reduction in delivery time, exceeding the target',

        -- Category 7: Development and Training
        'Requires advanced project management training and leadership development courses. Interested in pursuing Agile/Scrum master certification to enhance team management capabilities.',

        -- Category 8: Future Growth
        'Aspires to senior management role within 2-3 years. Interested in cross-functional experience in business development and strategic planning. Willing to take on additional responsibilities and mentor junior staff.',

        -- Category 9: Manager Performance Feedback
        'Manager provides clear direction and support. Good at giving constructive feedback and recognizing achievements. Could improve on providing more frequent check-ins during complex projects.',
        'More regular one-on-one meetings during project phases. Clearer communication of long-term departmental goals and how individual contributions align with company objectives.',

        -- Category 10: Other Discussion Areas
        'Work-life balance: Exploring flexible work arrangements to optimize productivity and personal well-being',
        'Career progression: Discussing potential lateral moves to gain broader business experience before vertical promotion',
        'Team dynamics: Improving cross-departmental collaboration and communication protocols',

        -- Category 11: Compliance
        'yes', 'All company policies and procedures are followed consistently. Regular completion of mandatory training programs.',
        'yes', 'Maintains confidentiality of sensitive information and follows data protection protocols appropriately.',
        'no', 'Minor improvement needed in documentation of certain processes. Will implement better record-keeping practices.',

        -- System fields
        'draft', NOW(), NOW()
    )";

    $params = [
        $current_year, $appraiser_epf, $appraiser_name, $appraisee_epf, $appraisee_name,
        'Senior Executive', 'Information Technology', 'Head Office', '2020-01-15', '4', date('Y-m-d')
    ];

    $db->query($insert_sql, $params);
    $inserted_id = $db->lastInsertId();

    echo "✓ Test data injection completed successfully!\n\n";
    echo "=== TEST RECORD DETAILS ===\n";
    echo "Record ID: $inserted_id\n";
    echo "Appraisal Year: $current_year\n";
    echo "Appraiser (Manager): EPF $appraiser_epf - $appraiser_name\n";
    echo "Appraisee (Employee): EPF $appraisee_epf - $appraisee_name\n";
    echo "Status: Draft\n";
    echo "Evaluation Date: " . date('Y-m-d') . "\n\n";

    echo "=== CATEGORIES POPULATED ===\n";
    echo "✓ Category 1: Competency Evaluation (8 competencies with ratings & comments)\n";
    echo "✓ Category 2: Achievements (3 achievements)\n";
    echo "✓ Category 3: Areas for Development (2 competencies with development plans)\n";
    echo "✓ Category 4: Core Values (3 values with self & manager ratings)\n";
    echo "✓ Category 5: Attitudes and Behaviors (8 behaviors with self & manager ratings)\n";
    echo "✓ Category 6: Objectives (3 objectives with evaluations)\n";
    echo "✓ Category 7: Development and Training\n";
    echo "✓ Category 8: Future Growth\n";
    echo "✓ Category 9: Manager Performance Feedback\n";
    echo "✓ Category 10: Other Discussion Areas (3 points)\n";
    echo "✓ Category 11: Compliance (3 questions with responses)\n\n";

    echo "🎯 You can now test the form by logging in as EPF 40 and viewing/editing the appraisal for EPF 502.\n";

} catch (Exception $e) {
    echo "Error injecting test data: " . $e->getMessage() . "\n";
}
?>