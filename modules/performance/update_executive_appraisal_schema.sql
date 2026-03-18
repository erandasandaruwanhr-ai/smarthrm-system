-- Executive Appraisal Database Schema Updates
-- Add missing fields for complete executive appraisal form according to specifications 13.7.1 - 13.7.3.2.3

USE smarthrm_db;

-- Add missing columns for Category 7: Training (13.7.3.1)
ALTER TABLE executive_appraisals ADD COLUMN training_mandatory TEXT COMMENT '13.7.3.1.1 Mandatory Training Required';
ALTER TABLE executive_appraisals ADD COLUMN training_professional TEXT COMMENT '13.7.3.1.2 Professional Development Training Desired';
ALTER TABLE executive_appraisals ADD COLUMN training_skills TEXT COMMENT '13.7.3.1.3 Skills Enhancement Training';
ALTER TABLE executive_appraisals ADD COLUMN training_leadership TEXT COMMENT '13.7.3.1.4 Leadership Development Training';

-- Add missing columns for Category 8: Growth and feedback (13.7.3.2)
ALTER TABLE executive_appraisals ADD COLUMN feedback_strengths TEXT COMMENT '13.7.3.2.1 Key Strengths';
ALTER TABLE executive_appraisals ADD COLUMN feedback_improvements TEXT COMMENT '13.7.3.2.2 Areas for Improvement';
ALTER TABLE executive_appraisals ADD COLUMN feedback_action_plan TEXT COMMENT '13.7.3.2.3 Action Plan for Growth';
ALTER TABLE executive_appraisals ADD COLUMN feedback_manager_comments TEXT COMMENT '13.7.3.2.4 Manager Additional Comments';
ALTER TABLE executive_appraisals ADD COLUMN feedback_self_reflection TEXT COMMENT '13.7.3.2.5 Employee Self-Reflection';

-- Show updated table structure
DESCRIBE executive_appraisals;