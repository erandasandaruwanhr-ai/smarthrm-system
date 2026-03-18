-- Goal Setting Module Database Tables
-- Module 12: Goal Setting System

-- 1. Goal Setting Periods Table
CREATE TABLE IF NOT EXISTS goal_setting_periods (
    id INT PRIMARY KEY AUTO_INCREMENT,
    year INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active', 'closed', 'draft') DEFAULT 'draft',
    created_by VARCHAR(20) NOT NULL,
    created_date DATETIME NOT NULL,
    updated_date DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_year (year)
);

-- 2. Goal Setting Templates Table (12.3.3 structure)
CREATE TABLE IF NOT EXISTS goal_setting_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    period_id INT NOT NULL,
    goal_section VARCHAR(10) NOT NULL, -- 12.3.3.1, 12.3.3.2, etc.
    goal_subsection VARCHAR(15) NOT NULL, -- 12.3.3.1.1, 12.3.3.1.2, etc.
    main_goals TEXT,
    activities TEXT,
    measurement_criteria TEXT,
    weightage DECIMAL(5,2),
    created_date DATETIME NOT NULL,
    FOREIGN KEY (period_id) REFERENCES goal_setting_periods(id) ON DELETE CASCADE,
    INDEX idx_period_section (period_id, goal_section)
);

-- 3. Executive Goal Setting Records Table
CREATE TABLE IF NOT EXISTS executive_goal_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    period_id INT NOT NULL,
    employee_epf VARCHAR(20) NOT NULL,
    manager_epf VARCHAR(20) NOT NULL,
    form_saved ENUM('Y', 'N') DEFAULT 'N',
    mid_year_progress ENUM('Y', 'N') DEFAULT 'N',
    final_evaluation ENUM('Y', 'N') DEFAULT 'N',
    final_grade VARCHAR(10),
    employee_agreement_date DATETIME,
    manager_agreement_date DATETIME,
    mid_year_status ENUM('Progressing Well', 'Need Improvements'),
    mid_year_comments TEXT,
    final_performance_grade ENUM('A', 'B', 'C', 'D'),
    final_agreement_date DATETIME,
    created_date DATETIME NOT NULL,
    updated_date DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (period_id) REFERENCES goal_setting_periods(id) ON DELETE CASCADE,
    UNIQUE KEY unique_employee_period (employee_epf, period_id),
    INDEX idx_employee (employee_epf),
    INDEX idx_manager (manager_epf),
    INDEX idx_period (period_id)
);

-- 4. Goal Details Table (Individual goal entries)
CREATE TABLE IF NOT EXISTS goal_details (
    id INT PRIMARY KEY AUTO_INCREMENT,
    record_id INT NOT NULL,
    goal_section VARCHAR(10) NOT NULL,
    goal_subsection VARCHAR(15) NOT NULL,
    main_goals TEXT,
    activities TEXT,
    measurement_criteria TEXT,
    weightage DECIMAL(5,2),
    mid_year_progress ENUM('YS', 'IP', 'C'), -- Yet to Start, In Progress, Completed
    achieved_percentage DECIMAL(5,2),
    self_rating DECIMAL(3,1),
    supervisor_rating DECIMAL(3,1),
    final_rating DECIMAL(3,1),
    created_date DATETIME NOT NULL,
    updated_date DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (record_id) REFERENCES executive_goal_records(id) ON DELETE CASCADE,
    INDEX idx_record_section (record_id, goal_section)
);

-- 5. Goal Setting Status Table (for tracking different phases)
CREATE TABLE IF NOT EXISTS goal_setting_status (
    id INT PRIMARY KEY AUTO_INCREMENT,
    status_name VARCHAR(50) NOT NULL,
    status_key VARCHAR(30) NOT NULL UNIQUE,
    is_active ENUM('Y', 'N') DEFAULT 'N',
    created_date DATETIME NOT NULL,
    updated_date DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default status records
INSERT INTO goal_setting_status (status_name, status_key, is_active, created_date) VALUES
('Goal Setting Form Creation', 'form_creation', 'N', NOW()),
('Mid-Year Progress Review', 'mid_year_review', 'N', NOW()),
('Final Performance Evaluation', 'final_evaluation', 'N', NOW())
ON DUPLICATE KEY UPDATE status_name = VALUES(status_name);