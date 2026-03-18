-- Clean up unused columns from executive_appraisals table
-- Remove columns that are not being used in the current form structure

USE smarthrm_db;

-- Drop old training columns that are not used in current form
ALTER TABLE executive_appraisals DROP COLUMN IF EXISTS training_mandatory;
ALTER TABLE executive_appraisals DROP COLUMN IF EXISTS training_professional;
ALTER TABLE executive_appraisals DROP COLUMN IF EXISTS training_skills;
ALTER TABLE executive_appraisals DROP COLUMN IF EXISTS training_leadership;

-- Drop old feedback columns that are not used in current form
ALTER TABLE executive_appraisals DROP COLUMN IF EXISTS feedback_strengths;
ALTER TABLE executive_appraisals DROP COLUMN IF EXISTS feedback_improvements;
ALTER TABLE executive_appraisals DROP COLUMN IF EXISTS feedback_action_plan;
ALTER TABLE executive_appraisals DROP COLUMN IF EXISTS feedback_manager_comments;
ALTER TABLE executive_appraisals DROP COLUMN IF EXISTS feedback_self_reflection;

-- Show updated table structure
DESCRIBE executive_appraisals;

-- Count remaining columns
SELECT COUNT(*) as total_columns
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'smarthrm_db'
AND TABLE_NAME = 'executive_appraisals';