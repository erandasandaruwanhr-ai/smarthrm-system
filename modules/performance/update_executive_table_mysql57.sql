-- Executive Appraisals Table Update for MySQL 5.7 compatibility
-- Add all missing fields for the restructured executive appraisal form
-- Note: MySQL 5.7 doesn't support "ADD COLUMN IF NOT EXISTS", so we need to check manually

USE smarthrm_db;

-- Category 7: Development and Training
ALTER TABLE executive_appraisals
ADD COLUMN development_training TEXT COMMENT 'Development and Training requirements';

-- Category 8: Future Growth
ALTER TABLE executive_appraisals
ADD COLUMN future_growth TEXT COMMENT 'Future Growth interests';

-- Category 9: Manager Performance Feedback
ALTER TABLE executive_appraisals
ADD COLUMN manager_performance_feedback TEXT COMMENT 'Feedback on Manager Performance';

ALTER TABLE executive_appraisals
ADD COLUMN manager_improvement_areas TEXT COMMENT 'Manager Improvement Areas';

-- Category 10: Other Discussion Areas
ALTER TABLE executive_appraisals
ADD COLUMN discussion_point_1 TEXT COMMENT 'Discussion Point 1';

ALTER TABLE executive_appraisals
ADD COLUMN discussion_point_2 TEXT COMMENT 'Discussion Point 2';

ALTER TABLE executive_appraisals
ADD COLUMN discussion_point_3 TEXT COMMENT 'Discussion Point 3';

-- Category 11: Compliance Section
ALTER TABLE executive_appraisals
ADD COLUMN compliance_q1 ENUM('yes','no') COMMENT 'Compliance Question 1';

ALTER TABLE executive_appraisals
ADD COLUMN compliance_q1_comments TEXT COMMENT 'Compliance Q1 Comments';

ALTER TABLE executive_appraisals
ADD COLUMN compliance_q2 ENUM('yes','no') COMMENT 'Compliance Question 2';

ALTER TABLE executive_appraisals
ADD COLUMN compliance_q2_comments TEXT COMMENT 'Compliance Q2 Comments';

ALTER TABLE executive_appraisals
ADD COLUMN compliance_q3 ENUM('yes','no') COMMENT 'Compliance Question 3';

ALTER TABLE executive_appraisals
ADD COLUMN compliance_q3_comments TEXT COMMENT 'Compliance Q3 Comments';

-- Additional missing field for evaluation date
ALTER TABLE executive_appraisals
ADD COLUMN evaluation_date DATE COMMENT 'Evaluation Date';

-- Show updated table structure
DESCRIBE executive_appraisals;

-- Count total columns
SELECT COUNT(*) as total_columns
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'smarthrm_db'
AND TABLE_NAME = 'executive_appraisals';