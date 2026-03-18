-- SmartHRM Database Schema
-- Compatible with MySQL 8.0+ and Laragon setup

CREATE DATABASE IF NOT EXISTS smarthrm_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smarthrm_db;

-- System Settings (Module 1.1)
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Account Types (Module 1.2)
CREATE TABLE account_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type_name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255),
    level INT NOT NULL DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Permissions (Module 1.3)
CREATE TABLE permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    module_name VARCHAR(100) NOT NULL,
    action VARCHAR(50) NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Account Type Permissions (Module 1.3)
CREATE TABLE account_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_type_id INT,
    permission_id INT,
    is_granted TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (account_type_id) REFERENCES account_types(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_account_permission (account_type_id, permission_id)
);

-- Locations (Module 1.4)
CREATE TABLE locations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    location_name VARCHAR(100) NOT NULL UNIQUE,
    address TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Employment Levels (Module 1.5)
CREATE TABLE employment_levels (
    id INT PRIMARY KEY AUTO_INCREMENT,
    level_name VARCHAR(100) NOT NULL UNIQUE,
    level_order INT NOT NULL,
    description VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Dropdown Options (Module 1.7)
CREATE TABLE dropdown_options (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category VARCHAR(100) NOT NULL,
    option_value VARCHAR(255) NOT NULL,
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category)
);

-- Employees (Module 2.1)
CREATE TABLE employees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    epf_number VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    designation VARCHAR(100),
    department VARCHAR(100),
    nic VARCHAR(20),
    birthday DATE,
    joined_date DATE,
    gender ENUM('Male', 'Female'),
    employment_level_id INT,
    location_id INT,
    reports_to VARCHAR(20),
    account_type_id INT DEFAULT 1,
    password_hash VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employment_level_id) REFERENCES employment_levels(id),
    FOREIGN KEY (location_id) REFERENCES locations(id),
    FOREIGN KEY (account_type_id) REFERENCES account_types(id),
    INDEX idx_epf (epf_number),
    INDEX idx_reports_to (reports_to),
    INDEX idx_location (location_id),
    INDEX idx_department (department)
);

-- Meal Requests (Module 3.1 & 3.2)
CREATE TABLE meal_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    epf_number VARCHAR(20) NOT NULL,
    request_type ENUM('employee', 'visitor') DEFAULT 'employee',
    request_date DATE NOT NULL,
    meal_type VARCHAR(100),
    breakfast_count INT DEFAULT 0,
    breakfast_countx INT DEFAULT 0,
    snack1_countx INT DEFAULT 0,
    lunch_count INT DEFAULT 0,
    lunch_countx INT DEFAULT 0,
    snack2_count INT DEFAULT 0,
    snack2_countx INT DEFAULT 0,
    dinner_count INT DEFAULT 0,
    dinner_countx INT DEFAULT 0,
    snack3_countx INT DEFAULT 0,
    special_remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (epf_number) REFERENCES employees(epf_number),
    INDEX idx_date (request_date),
    INDEX idx_type (request_type)
);

-- Vehicle Registry (Module 4.1)
CREATE TABLE vehicles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    vehicle_type ENUM('van', 'car') NOT NULL,
    plate_number VARCHAR(20) NOT NULL UNIQUE,
    seat_capacity INT NOT NULL,
    location_id INT,
    is_available TINYINT(1) DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES locations(id)
);

-- Driver Pool (Module 4.4)
CREATE TABLE drivers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    epf_number VARCHAR(20) NOT NULL,
    location_id INT,
    is_on_duty TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (epf_number) REFERENCES employees(epf_number),
    FOREIGN KEY (location_id) REFERENCES locations(id)
);

-- Transport Requests (Module 4.3)
CREATE TABLE transport_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    epf_number VARCHAR(20) NOT NULL,
    needed_for ENUM('myself', 'team') DEFAULT 'myself',
    purpose VARCHAR(255),
    trip_description TEXT,
    passenger_capacity INT,
    request_date DATE,
    request_time TIME,
    status ENUM('pending', 'approved', 'assigned', 'completed', 'cancelled') DEFAULT 'pending',
    assigned_vehicle_id INT,
    assigned_driver_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (epf_number) REFERENCES employees(epf_number),
    FOREIGN KEY (assigned_vehicle_id) REFERENCES vehicles(id),
    FOREIGN KEY (assigned_driver_id) REFERENCES drivers(id)
);

-- Grievance Cases (Module 5.1)
CREATE TABLE grievances (
    id INT PRIMARY KEY AUTO_INCREMENT,
    case_id VARCHAR(20) NOT NULL UNIQUE,
    epf_number VARCHAR(20) NOT NULL,
    is_anonymous TINYINT(1) DEFAULT 0,
    category VARCHAR(100),
    urgency VARCHAR(50),
    subject VARCHAR(200),
    description TEXT,
    incident_date DATE,
    incident_location VARCHAR(100),
    witnesses TEXT,
    status VARCHAR(50) DEFAULT 'Open',
    assigned_to VARCHAR(20),
    resolution TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (epf_number) REFERENCES employees(epf_number),
    INDEX idx_case_id (case_id),
    INDEX idx_status (status),
    INDEX idx_category (category)
);

-- Employee Requests (Module 6.2)
CREATE TABLE employee_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    request_id VARCHAR(20) NOT NULL UNIQUE,
    epf_number VARCHAR(20) NOT NULL,
    request_type VARCHAR(100),
    subject VARCHAR(200),
    details TEXT,
    start_date DATE,
    end_date DATE,
    reason TEXT,
    urgency ENUM('Normal', 'High', 'Urgent') DEFAULT 'Normal',
    status VARCHAR(50) DEFAULT 'Pending',
    current_stage VARCHAR(50) DEFAULT 'Supervisor',
    supervisor_remarks TEXT,
    admin_remarks TEXT,
    manager_remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (epf_number) REFERENCES employees(epf_number),
    INDEX idx_request_id (request_id),
    INDEX idx_status (status),
    INDEX idx_type (request_type)
);

-- Events (Module 7.1)
CREATE TABLE events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id VARCHAR(20) NOT NULL UNIQUE,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    event_date DATE,
    start_time TIME,
    end_time TIME,
    location_id INT,
    venue_details VARCHAR(255),
    status ENUM('Pending', 'Done', 'Postponed', 'Cancelled') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES locations(id),
    INDEX idx_event_id (event_id),
    INDEX idx_date (event_date),
    INDEX idx_status (status)
);

-- File Uploads
CREATE TABLE file_uploads (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reference_type VARCHAR(50),
    reference_id INT,
    original_filename VARCHAR(255),
    stored_filename VARCHAR(255),
    file_path VARCHAR(500),
    file_size INT,
    mime_type VARCHAR(100),
    uploaded_by VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES employees(epf_number),
    INDEX idx_reference (reference_type, reference_id)
);

-- Insert Default Data
INSERT INTO account_types (type_name, description, level) VALUES
('user', 'Regular User', 1),
('supervisor', 'Supervisor', 2),
('manager', 'Manager', 3),
('admin', 'Administrator', 4),
('superadmin', 'Super Administrator', 5);

INSERT INTO locations (location_name) VALUES
('7C'),
('Pannala'),
('Kobeigane'),
('JECOE'),
('Head Office');

INSERT INTO employment_levels (level_name, level_order) VALUES
('MD', 1),
('GM', 2),
('Manager', 3),
('Assistant Manager', 4),
('Senior Executive', 5),
('Executive', 6),
('Junior Executive', 7),
('Supervisor', 8),
('Staff', 9);

INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('system_name', 'SmartHRM System', 'Application Name'),
('theme_color', '#007bff', 'Primary System Color'),
('date_format', 'Y-m-d', 'System Date Format'),
('time_format', 'H:i:s', 'System Time Format'),
('session_timeout', '3600', 'Session Timeout in Seconds'),
('file_max_size', '10485760', 'Maximum File Upload Size in Bytes'),
('company_name', 'PB Pictures', 'Company Name');

-- Insert Gender Options
INSERT INTO dropdown_options (category, option_value, display_order) VALUES
('gender', 'Male', 1),
('gender', 'Female', 2);

-- Insert Meal Types
INSERT INTO dropdown_options (category, option_value, display_order) VALUES
('meal_type', 'Employee Meal', 1),
('meal_type', 'Employee Special', 2),
('meal_type', 'Seafood - Foreigner', 3),
('meal_type', 'Chicken - Foreigner', 4),
('meal_type', 'Veg - Foreigner', 5),
('meal_type', 'Chicken - Local', 6),
('meal_type', 'Fish - Local', 7),
('meal_type', 'Veg - Local', 8);

-- Insert Transport Purposes
INSERT INTO dropdown_options (category, option_value, display_order) VALUES
('transport_purpose', 'Transport between two plants', 1),
('transport_purpose', 'Government', 2),
('transport_purpose', 'Banks', 3),
('transport_purpose', 'Purchasing', 4),
('transport_purpose', 'Event', 5),
('transport_purpose', 'Training', 6),
('transport_purpose', 'Other', 7);

-- Insert Departments
INSERT INTO dropdown_options (category, option_value, display_order) VALUES
('department', 'Finance', 1),
('department', 'HR', 2),
('department', 'IT', 3),
('department', 'Maintenance', 4),
('department', 'Material Processing', 5),
('department', 'Production', 6),
('department', 'QHS', 7),
('department', 'Supply Chain & Logistics', 8);

-- Insert Grievance Categories
INSERT INTO dropdown_options (category, option_value, display_order) VALUES
('grievance_category', 'Professional', 1),
('grievance_category', 'Financial', 2),
('grievance_category', 'Behavioral', 3),
('grievance_category', 'Environment', 4),
('grievance_category', 'Policy', 5);

-- Insert Urgency Levels
INSERT INTO dropdown_options (category, option_value, display_order) VALUES
('urgency_level', 'Low', 1),
('urgency_level', 'Medium', 2),
('urgency_level', 'High', 3),
('urgency_level', 'Critical', 4);

-- Insert Request Types
INSERT INTO dropdown_options (category, option_value, display_order) VALUES
('request_type', 'Salary Slip Originals', 1),
('request_type', 'Bank Documents Fillup', 2),
('request_type', 'Service Letter', 3),
('request_type', 'Training', 4),
('request_type', 'Other', 5);

-- Create default superadmin user
INSERT INTO employees (
    epf_number, name, designation, department, nic, birthday, joined_date,
    gender, employment_level_id, location_id, account_type_id, password_hash
) VALUES (
    'ADMIN001', 'System Administrator', 'System Admin', 'IT',
    '123456789V', '1990-01-01', '2024-01-01', 'Male',
    1, 5, 5, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
);