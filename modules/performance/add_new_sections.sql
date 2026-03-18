-- Add new sections to executive_appraisals table
USE smarthrm_db;

-- Category 4: Development and Training
ALTER TABLE executive_appraisals ADD COLUMN development_training TEXT COMMENT 'Development and Training requirements';

-- Category 5: Future Growth
ALTER TABLE executive_appraisals ADD COLUMN future_growth TEXT COMMENT 'Future Growth interests';

-- Category 7: Manager Performance Feedback
ALTER TABLE executive_appraisals ADD COLUMN manager_performance_feedback TEXT COMMENT 'Feedback on Manager Performance';
ALTER TABLE executive_appraisals ADD COLUMN manager_improvement_areas TEXT COMMENT 'Manager Improvement Areas';

-- Category 8: Other Discussion Areas
ALTER TABLE executive_appraisals ADD COLUMN discussion_point_1 TEXT COMMENT 'Discussion Point 1';
ALTER TABLE executive_appraisals ADD COLUMN discussion_point_2 TEXT COMMENT 'Discussion Point 2';
ALTER TABLE executive_appraisals ADD COLUMN discussion_point_3 TEXT COMMENT 'Discussion Point 3';

-- Compliance Section
ALTER TABLE executive_appraisals ADD COLUMN compliance_q1 ENUM('yes','no') COMMENT 'Compliance Question 1';
ALTER TABLE executive_appraisals ADD COLUMN compliance_q1_comments TEXT COMMENT 'Compliance Q1 Comments';
ALTER TABLE executive_appraisals ADD COLUMN compliance_q2 ENUM('yes','no') COMMENT 'Compliance Question 2';
ALTER TABLE executive_appraisals ADD COLUMN compliance_q2_comments TEXT COMMENT 'Compliance Q2 Comments';
ALTER TABLE executive_appraisals ADD COLUMN compliance_q3 ENUM('yes','no') COMMENT 'Compliance Question 3';
ALTER TABLE executive_appraisals ADD COLUMN compliance_q3_comments TEXT COMMENT 'Compliance Q3 Comments';

-- Show updated table structure
DESCRIBE executive_appraisals;