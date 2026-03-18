-- Grievance Module Database Schema
-- Based on Plan.md Section 5 specifications

-- Main grievances table
CREATE TABLE IF NOT EXISTS `grievances` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `case_id` VARCHAR(20) UNIQUE NOT NULL,
    `employee_epf` VARCHAR(20) NOT NULL,
    `employee_name` VARCHAR(100) NOT NULL,
    `employee_location` VARCHAR(50),
    `employee_department` VARCHAR(50),
    `is_anonymous` BOOLEAN DEFAULT FALSE,
    `submission_date` DATETIME NOT NULL,
    `category` ENUM(
        'Professional (Career & Work)',
        'Financial (Money & Compensation)',
        'Behavioral (People & Conduct)',
        'Environment (Physical Workspace)',
        'Policy (Rules & Procedures)',
        'Safety (Health & Security)',
        'Discrimination (Unfair Treatment)',
        'Harassment (Inappropriate Behavior)',
        'Communication (Information & Feedback)'
    ) NOT NULL,
    `urgency` ENUM('Low', 'Medium', 'High', 'Critical') NOT NULL,
    `subject` VARCHAR(200) NOT NULL,
    `description` TEXT NOT NULL,
    `incident_date` DATE,
    `incident_location` VARCHAR(50),
    `witnesses` TEXT,
    `status` ENUM(
        'Open',
        'Under Supervisory Review',
        'Under Managerial Review',
        'Resolved'
    ) DEFAULT 'Open',
    `assigned_investigator` INT,
    `resolution` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX `idx_employee_epf` (`employee_epf`),
    INDEX `idx_status` (`status`),
    INDEX `idx_category` (`category`),
    INDEX `idx_urgency` (`urgency`),
    INDEX `idx_submission_date` (`submission_date`),
    INDEX `idx_assigned_investigator` (`assigned_investigator`)
);

-- Evidence files table
CREATE TABLE IF NOT EXISTS `grievance_evidence` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `grievance_id` INT NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_type` VARCHAR(50) NOT NULL,
    `file_size` INT NOT NULL,
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`grievance_id`) REFERENCES `grievances`(`id`) ON DELETE CASCADE
);

-- Case notes and actions table
CREATE TABLE IF NOT EXISTS `grievance_notes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `grievance_id` INT NOT NULL,
    `user_epf` VARCHAR(20) NOT NULL,
    `user_name` VARCHAR(100) NOT NULL,
    `action_type` ENUM(
        'Status Update',
        'Assignment',
        'Note Added',
        'Investigation Report',
        'Supervisor Review',
        'Manager Review',
        'Resolution'
    ) NOT NULL,
    `note` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`grievance_id`) REFERENCES `grievances`(`id`) ON DELETE CASCADE,
    INDEX `idx_grievance_id` (`grievance_id`),
    INDEX `idx_user_epf` (`user_epf`)
);

-- Investigation team assignments table
CREATE TABLE IF NOT EXISTS `grievance_investigators` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `grievance_id` INT NOT NULL,
    `team_leader_epf` VARCHAR(20) NOT NULL,
    `team_leader_name` VARCHAR(100) NOT NULL,
    `member1_epf` VARCHAR(20),
    `member1_name` VARCHAR(100),
    `member2_epf` VARCHAR(20),
    `member2_name` VARCHAR(100),
    `assigned_by` VARCHAR(20) NOT NULL,
    `assigned_date` DATETIME NOT NULL,
    `status` ENUM('Active', 'Completed') DEFAULT 'Active',

    FOREIGN KEY (`grievance_id`) REFERENCES `grievances`(`id`) ON DELETE CASCADE,
    INDEX `idx_grievance_id` (`grievance_id`),
    INDEX `idx_team_leader` (`team_leader_epf`)
);

-- Investigation reports table
CREATE TABLE IF NOT EXISTS `grievance_reports` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `grievance_id` INT NOT NULL,
    `submitted_by` VARCHAR(20) NOT NULL,
    `report_content` TEXT NOT NULL,
    `findings` TEXT,
    `recommendations` TEXT,
    `submitted_date` DATETIME NOT NULL,
    `reviewed_by` VARCHAR(20),
    `reviewed_date` DATETIME,
    `status` ENUM('Draft', 'Submitted', 'Under Review', 'Approved', 'Rejected') DEFAULT 'Draft',

    FOREIGN KEY (`grievance_id`) REFERENCES `grievances`(`id`) ON DELETE CASCADE,
    INDEX `idx_grievance_id` (`grievance_id`),
    INDEX `idx_submitted_by` (`submitted_by`)
);

-- Sample data for testing (optional)
INSERT INTO `grievances` (
    `case_id`, `employee_epf`, `employee_name`, `employee_location`,
    `employee_department`, `is_anonymous`, `submission_date`,
    `category`, `urgency`, `subject`, `description`,
    `incident_date`, `incident_location`
) VALUES
('GRV-2026-000001', 'EMP001', 'John Doe', '7C', 'Production', FALSE, NOW(),
 'Professional (Career & Work)', 'Medium', 'Training Request Denied',
 'My request for safety training was denied without proper justification.',
 '2026-02-10', '7C'),

('GRV-2026-000002', 'ANON', 'Anonymous', 'Pannala', 'HR', TRUE, NOW(),
 'Harassment (Inappropriate Behavior)', 'High', 'Workplace Harassment',
 'Experiencing inappropriate behavior from supervisor during meetings.',
 '2026-02-08', 'Pannala');