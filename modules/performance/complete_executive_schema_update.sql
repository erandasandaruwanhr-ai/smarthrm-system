-- Complete Executive Appraisal Database Schema Update
-- Add all missing fields for the restructured form

USE smarthrm_db;

-- Add evaluation_date field
ALTER TABLE executive_appraisals ADD COLUMN evaluation_date DATE COMMENT 'Evaluation Date';

-- Show updated table structure
DESCRIBE executive_appraisals;