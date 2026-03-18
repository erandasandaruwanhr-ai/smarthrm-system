-- Skill Matrix Module Database Schema
-- Module 15 - Skill Matrix Tables

USE smarthrm_db;

-- Table to store skill matrix years (15.2.1)
CREATE TABLE IF NOT EXISTS skill_matrix_years (
    id INT PRIMARY KEY AUTO_INCREMENT,
    year INT NOT NULL UNIQUE,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table to store skill categories (15.2.2)
CREATE TABLE IF NOT EXISTS skill_matrix_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table to store individual skills under each category (15.2.2.1 to 15.2.2.6)
CREATE TABLE IF NOT EXISTS skill_matrix_skills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    year_id INT NOT NULL,
    category_id INT NOT NULL,
    skill_name VARCHAR(255) NOT NULL,
    skill_description TEXT,
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (year_id) REFERENCES skill_matrix_years(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES skill_matrix_categories(id) ON DELETE CASCADE,
    UNIQUE KEY unique_year_category_skill (year_id, category_id, skill_name)
);

-- Table to store skill assessments (15.3)
CREATE TABLE IF NOT EXISTS skill_matrix_assessments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    year_id INT NOT NULL,
    employee_epf VARCHAR(20) NOT NULL,
    manager_epf VARCHAR(20) NOT NULL,
    skill_id INT NOT NULL,
    target_rating INT NOT NULL CHECK (target_rating BETWEEN 1 AND 5),
    current_rating INT NOT NULL CHECK (current_rating BETWEEN 1 AND 5),
    gap_rating INT NOT NULL CHECK (gap_rating BETWEEN 1 AND 5),
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (year_id) REFERENCES skill_matrix_years(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skill_matrix_skills(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_epf) REFERENCES employees(epf_number) ON DELETE CASCADE,
    FOREIGN KEY (manager_epf) REFERENCES employees(epf_number) ON DELETE CASCADE,
    UNIQUE KEY unique_assessment (year_id, employee_epf, skill_id)
);

-- Insert default skill categories
INSERT IGNORE INTO skill_matrix_categories (id, name, display_order) VALUES
(1, 'Technical Skills', 1),
(2, 'Leadership & Management', 2),
(3, 'Communication & Interpersonal Skills', 3),
(4, 'Adaptability & Learning Agility', 4),
(5, 'Innovation & Creativity', 5),
(6, 'Problem-Solving & Critical Thinking', 6);

-- Create indexes for better performance
CREATE INDEX idx_skill_matrix_assessments_employee ON skill_matrix_assessments(employee_epf);
CREATE INDEX idx_skill_matrix_assessments_manager ON skill_matrix_assessments(manager_epf);
CREATE INDEX idx_skill_matrix_assessments_year ON skill_matrix_assessments(year_id);
CREATE INDEX idx_skill_matrix_skills_year_category ON skill_matrix_skills(year_id, category_id);