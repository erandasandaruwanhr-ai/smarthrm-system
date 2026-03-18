-- SQL script to remove 'In Progress' from grievance status ENUM
-- Run this in your phpMyAdmin or MySQL console

-- Update any existing records with 'In Progress' status to 'Under Supervisory Review'
UPDATE grievances
SET status = 'Under Supervisory Review'
WHERE status = 'In Progress';

-- Now modify the ENUM to remove 'In Progress'
ALTER TABLE grievances
MODIFY COLUMN status ENUM(
    'Open',
    'Under Supervisory Review',
    'Under Managerial Review',
    'Resolved',
    'Closed',
    'Reopen Requested',
    'Reopened'
) DEFAULT 'Open';

-- Check the updated structure
SHOW COLUMNS FROM grievances WHERE Field = 'status';

-- Show any updated records
SELECT case_id, status, created_at
FROM grievances
WHERE status IN ('Under Supervisory Review', 'Under Managerial Review', 'Resolved', 'Closed', 'Reopened', 'Open')
ORDER BY created_at DESC
LIMIT 10;

-- Optional: Add a comment to the table noting the change
ALTER TABLE grievances COMMENT = 'Grievances table - In Progress status removed from workflow on 2026-02-22';