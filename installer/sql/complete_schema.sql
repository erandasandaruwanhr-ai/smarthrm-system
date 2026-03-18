-- SmartHRM Installation Schema
-- Complete database structure for installation

DROP DATABASE IF EXISTS smarthrm_db;
CREATE DATABASE smarthrm_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smarthrm_db;

-- System Settings
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Account Types
CREATE TABLE account_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type_name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255),
    level_name VARCHAR(100),
    level INT NOT NULL DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Locations
CREATE TABLE locations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    location_name VARCHAR(100) NOT NULL UNIQUE,
    address TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Employment Levels
CREATE TABLE employment_levels (
    id INT PRIMARY KEY AUTO_INCREMENT,
    level_name VARCHAR(100) NOT NULL UNIQUE,
    level_order INT NOT NULL,
    description VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Dropdown Options
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

-- Employees
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
    account_type VARCHAR(50) DEFAULT 'user',
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

-- Permissions
CREATE TABLE permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    module_name VARCHAR(100) NOT NULL,
    action VARCHAR(50) NOT NULL,
    action_name VARCHAR(100),
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Account Permissions
CREATE TABLE account_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_type_id INT,
    permission_id INT,
    module_name VARCHAR(100),
    action_name VARCHAR(100),
    is_granted TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (account_type_id) REFERENCES account_types(id) ON DELETE CASCADE,
    INDEX idx_account_type (account_type_id),
    INDEX idx_module (module_name)
);

-- Role Permissions
CREATE TABLE role_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role VARCHAR(50) NOT NULL,
    module VARCHAR(50) NOT NULL,
    permission VARCHAR(100) NOT NULL,
    granted TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_role_module (role, module),
    INDEX idx_permission (permission)
);

-- Training Types
CREATE TABLE training_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type_name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Users table (if needed for compatibility)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    epf_number VARCHAR(20) NOT NULL UNIQUE,
    username VARCHAR(50),
    email VARCHAR(100),
    password_hash VARCHAR(255),
    role VARCHAR(50) DEFAULT 'user',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (epf_number) REFERENCES employees(epf_number) ON DELETE CASCADE
);

-- Additional tables for complete system functionality

-- Meal Requests
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

-- Vehicles
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

-- Transport Requests
CREATE TABLE transport_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    emp_number VARCHAR(20) NOT NULL,
    emp_name VARCHAR(255),
    emp_location VARCHAR(100),
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
    INDEX idx_status (status),
    INDEX idx_date (request_date)
);

-- Grievances
CREATE TABLE grievances (
    id INT PRIMARY KEY AUTO_INCREMENT,
    case_id VARCHAR(20) NOT NULL UNIQUE,
    epf_number VARCHAR(20) NOT NULL,
    employee_location VARCHAR(100),
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
    INDEX idx_case_id (case_id),
    INDEX idx_status (status),
    INDEX idx_category (category)
);

-- Anonymous Grievances
CREATE TABLE anonymous_grievances (
    id INT PRIMARY KEY AUTO_INCREMENT,
    case_id VARCHAR(20) NOT NULL UNIQUE,
    category VARCHAR(100),
    urgency VARCHAR(50),
    subject VARCHAR(200),
    description TEXT,
    incident_date DATE,
    incident_location VARCHAR(100),
    witnesses TEXT,
    status VARCHAR(50) DEFAULT 'Opened',
    assigned_to VARCHAR(20),
    resolution TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_case_id (case_id),
    INDEX idx_status (status)
);

-- Employee Requests
CREATE TABLE employee_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    request_id VARCHAR(20) NOT NULL UNIQUE,
    epf_number VARCHAR(20) NOT NULL,
    name VARCHAR(255),
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
    INDEX idx_request_id (request_id),
    INDEX idx_status (status),
    INDEX idx_type (request_type)
);

-- Notifications
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL DEFAULT '',
    description TEXT NOT NULL,
    type ENUM('employee', 'security', 'meal', 'transport', 'grievance', 'admin', 'system') NOT NULL,
    action_needed TINYINT(1) NOT NULL DEFAULT 0,
    module_link VARCHAR(255) DEFAULT '#',
    target_users TEXT,
    target_roles TEXT,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    expires_at DATETIME NULL DEFAULT NULL,
    INDEX idx_type (type),
    INDEX idx_created_at (created_at),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    INDEX idx_reference (reference_type, reference_id)
);