-- Add objective evaluation columns to executive_appraisals table
USE smarthrm_db;

-- Add evaluation columns for objectives
ALTER TABLE executive_appraisals ADD COLUMN objective_1_evaluation TEXT COMMENT 'Evaluation of Objective 1 achievement';
ALTER TABLE executive_appraisals ADD COLUMN objective_2_evaluation TEXT COMMENT 'Evaluation of Objective 2 achievement';
ALTER TABLE executive_appraisals ADD COLUMN objective_3_evaluation TEXT COMMENT 'Evaluation of Objective 3 achievement';

-- Show updated table structure
DESCRIBE executive_appraisals;