-- Anonymous Grievances Table (Separate from regular grievances)
CREATE TABLE IF NOT EXISTS `anonymous_grievances` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `case_id` VARCHAR(50) UNIQUE NOT NULL,

    -- Anonymous submission info (no employee details)
    `submission_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `submitter_location` VARCHAR(50), -- Where submitter works (for context)
    `submitter_department` VARCHAR(50), -- Department context

    -- Incident details
    `category` VARCHAR(100) NOT NULL,
    `urgency` ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Medium',
    `subject` VARCHAR(200) NOT NULL,
    `description` TEXT NOT NULL,
    `incident_date` DATE,
    `incident_location` VARCHAR(50),
    `witnesses` TEXT,

    -- Anonymous workflow specific statuses
    `status` ENUM(
        'Pending Investigation',     -- Initial status
        'Under Investigation',       -- Investigation team assigned
        'Investigation Complete',    -- Team finished, awaiting superadmin review
        'Resolved',                 -- Closed by superadmin
        'Dismissed'                 -- Dismissed by superadmin
    ) DEFAULT 'Pending Investigation',

    -- Investigation team
    `assigned_team_id` INT,
    `resolution` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX `idx_case_id` (`case_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_category` (`category`),
    INDEX `idx_urgency` (`urgency`),
    INDEX `idx_submission_date` (`submission_date`),
    INDEX `idx_assigned_team` (`assigned_team_id`)
);

-- Anonymous Investigation Teams (Separate from regular investigators)
CREATE TABLE IF NOT EXISTS `anonymous_investigation_teams` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `anonymous_grievance_id` INT NOT NULL,
    `team_leader_epf` VARCHAR(20) NOT NULL,
    `team_leader_name` VARCHAR(100) NOT NULL,
    `member1_epf` VARCHAR(20),
    `member1_name` VARCHAR(100),
    `member2_epf` VARCHAR(20),
    `member2_name` VARCHAR(100),
    `assigned_by` VARCHAR(20) NOT NULL, -- Superadmin EPF
    `assigned_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('Active', 'Completed') DEFAULT 'Active',

    FOREIGN KEY (`anonymous_grievance_id`) REFERENCES `anonymous_grievances`(`id`) ON DELETE CASCADE,
    INDEX `idx_grievance_id` (`anonymous_grievance_id`),
    INDEX `idx_team_leader` (`team_leader_epf`),
    INDEX `idx_assigned_by` (`assigned_by`)
);

-- Anonymous Case Notes (Separate timeline)
CREATE TABLE IF NOT EXISTS `anonymous_grievance_notes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `anonymous_grievance_id` INT NOT NULL,
    `user_epf` VARCHAR(20) NOT NULL,
    `user_name` VARCHAR(100) NOT NULL,
    `action_type` ENUM(
        'Submission',               -- Initial anonymous submission
        'Investigation Assignment', -- Team assigned
        'Investigation Report',     -- Team findings
        'Investigation Progress',   -- Team updates
        'Evidence Added',          -- New evidence
        'Superadmin Review',       -- Superadmin notes
        'Resolution',              -- Final closure
        'Dismissal'                -- Case dismissed
    ) NOT NULL,
    `note` TEXT NOT NULL,
    `is_confidential` BOOLEAN DEFAULT FALSE, -- For internal superadmin notes
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`anonymous_grievance_id`) REFERENCES `anonymous_grievances`(`id`) ON DELETE CASCADE,
    INDEX `idx_grievance_id` (`anonymous_grievance_id`),
    INDEX `idx_user_epf` (`user_epf`),
    INDEX `idx_action_type` (`action_type`),
    INDEX `idx_created_at` (`created_at`)
);

-- Anonymous Evidence Files (Separate storage)
CREATE TABLE IF NOT EXISTS `anonymous_grievance_evidence` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `anonymous_grievance_id` INT NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_type` VARCHAR(50) NOT NULL,
    `file_size` INT NOT NULL,
    `uploaded_by` VARCHAR(20) NOT NULL,
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`anonymous_grievance_id`) REFERENCES `anonymous_grievances`(`id`) ON DELETE CASCADE,
    INDEX `idx_grievance_id` (`anonymous_grievance_id`),
    INDEX `idx_uploaded_by` (`uploaded_by`)
);

-- Create link table for foreign key to anonymous investigation teams
ALTER TABLE `anonymous_grievances`
ADD CONSTRAINT `fk_assigned_team`
FOREIGN KEY (`assigned_team_id`) REFERENCES `anonymous_investigation_teams`(`id`) ON DELETE SET NULL;