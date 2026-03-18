-- Update grievances table to include all required status values
-- Run this to add missing status options

ALTER TABLE `grievances` MODIFY COLUMN `status` ENUM(
    'Open',
    'Under Supervisory Review',
    'Under Managerial Review',
    'In Progress',
    'Resolved',
    'Closed',
    'Reopened'
) DEFAULT 'Open';

-- This will allow the case management system to use all workflow statuses
-- No data updates needed as existing statuses are preserved