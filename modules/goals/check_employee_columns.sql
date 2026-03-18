-- Check what employee columns exist in goal_details table
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'goal_details'
  AND TABLE_SCHEMA = 'smarthrm_db'
  AND COLUMN_NAME LIKE 'employee_%'
ORDER BY ORDINAL_POSITION;