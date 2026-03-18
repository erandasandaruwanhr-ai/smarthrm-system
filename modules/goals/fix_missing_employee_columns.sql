-- Fix missing employee information columns in goal_details table

-- The previous script missed the location column, let's check what we have and add what's missing
DESCRIBE goal_details;

-- Add the missing employee_location column (it was missed in the first script)
ALTER TABLE goal_details
MODIFY COLUMN employee_location VARCHAR(255) DEFAULT NULL;

-- Verify all employee columns are present
SELECT COLUMN_NAME
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'goal_details'
  AND TABLE_SCHEMA = 'smarthrm_db'
  AND COLUMN_NAME LIKE 'employee_%'
ORDER BY ORDINAL_POSITION;