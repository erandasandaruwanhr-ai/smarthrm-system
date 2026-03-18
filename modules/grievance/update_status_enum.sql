-- Update grievances table to use only required status values
-- Run this to update existing installations

ALTER TABLE `grievances` MODIFY COLUMN `status` ENUM(
    'Open',
    'Under Supervisory Review',
    'Under Managerial Review',
    'Resolved'
) DEFAULT 'Open';

-- Update any existing status values that are no longer valid
UPDATE `grievances` SET `status` = 'Resolved' WHERE `status` IN ('Closed', 'In Progress');
UPDATE `grievances` SET `status` = 'Open' WHERE `status` = 'Reopened';