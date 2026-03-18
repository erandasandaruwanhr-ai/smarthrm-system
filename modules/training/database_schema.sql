-- =====================================================
-- SmartHRM Training Module Database Schema
-- Module 11 - Comprehensive Training Management System
-- =====================================================

-- 1. Training Requirements (Section 11.1)
CREATE TABLE training_requirements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    year YEAR NOT NULL,
    training_requirement VARCHAR(500) NOT NULL,
    training_type ENUM('awareness', 'certificate', 'diploma', 'other') NOT NULL,
    proposed_period ENUM('1st Quarter', '2nd Quarter', '3rd Quarter', '4th Quarter') NOT NULL,
    epf_number VARCHAR(20) NOT NULL,
    employee_name VARCHAR(200) NOT NULL,
    location VARCHAR(100) NOT NULL,
    department VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by VARCHAR(20),
    status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    INDEX idx_year (year),
    INDEX idx_epf (epf_number),
    INDEX idx_status (status),
    INDEX idx_period (proposed_period)
);

-- 2. Training Budget Management (Section 11.2)
CREATE TABLE training_budget (
    id INT PRIMARY KEY AUTO_INCREMENT,
    budget_year YEAR NOT NULL,
    requirement_id INT NOT NULL,
    add_to_budget BOOLEAN DEFAULT FALSE,
    budget_amount DECIMAL(12,2) DEFAULT 0.00,
    budget_approved_by VARCHAR(200),
    approval_date TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (requirement_id) REFERENCES training_requirements(id) ON DELETE CASCADE,
    INDEX idx_budget_year (budget_year),
    INDEX idx_requirement (requirement_id),
    INDEX idx_approved (add_to_budget)
);

-- 3. Training Plans (Section 11.4)
CREATE TABLE training_plans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    training_name VARCHAR(300) NOT NULL,
    training_institute VARCHAR(300) NOT NULL,
    trainee_epf VARCHAR(20) NOT NULL,
    trainee_name VARCHAR(200) NOT NULL,
    training_cost DECIMAL(12,2) NOT NULL,
    training_start_date DATE NOT NULL,
    training_end_date DATE NULL,
    budgeted_cost_exceed_percentage DECIMAL(5,2) DEFAULT 0.00,
    budget_id INT NULL,
    requirement_id INT NOT NULL,
    status ENUM('planned', 'ongoing', 'completed', 'cancelled') DEFAULT 'planned',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (budget_id) REFERENCES training_budget(id),
    FOREIGN KEY (requirement_id) REFERENCES training_requirements(id),
    INDEX idx_trainee_epf (trainee_epf),
    INDEX idx_start_date (training_start_date),
    INDEX idx_status (status)
);

-- 4. Training Evaluation Forms (Section 11.5)
CREATE TABLE training_evaluations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    training_plan_id INT NOT NULL,

    -- Auto-filled from training plan
    training_name VARCHAR(300),
    training_institute VARCHAR(300),
    training_start_date DATE,
    training_end_date DATE,
    trainee_epf VARCHAR(20),
    trainee_name VARCHAR(200),

    -- 11.5.2 Logistics and Organization
    registration_process ENUM('Y', 'N') DEFAULT 'Y',
    environment_rating ENUM('Poor', 'Fair', 'Good', 'Excellent') DEFAULT 'Good',
    duration_rating ENUM('Too Short', 'Just Right', 'Too Long') DEFAULT 'Just Right',

    -- 11.5.3 Content and Relevance
    objectives_clear ENUM('Y', 'N') DEFAULT 'Y',
    applicability_rating ENUM('Not Relevant', 'Somewhat Relevant', 'Very Relevant', 'Extremely Relevant') DEFAULT 'Very Relevant',
    pacing_rating ENUM('Too Fast', 'Just Right', 'Too Slow') DEFAULT 'Just Right',
    theory_practical_balance ENUM('Too Much Theory', 'Good Balance', 'Too Much Practical') DEFAULT 'Good Balance',

    -- 11.5.4 Instructor Effectiveness
    instructor_knowledgeable ENUM('Y', 'N') DEFAULT 'Y',
    instructor_engaging ENUM('Y', 'N') DEFAULT 'Y',
    presentation_clarity ENUM('Y', 'N', 'N/A') DEFAULT 'Y',

    -- 11.5.5 Impact and Future Action (1-5 scale)
    immediate_application_rating TINYINT CHECK (immediate_application_rating BETWEEN 1 AND 5),
    performance_improvement_rating TINYINT CHECK (performance_improvement_rating BETWEEN 1 AND 5),
    recommend_to_colleague_rating TINYINT CHECK (recommend_to_colleague_rating BETWEEN 1 AND 5),

    -- 11.5.6 Open-Ended Feedback
    most_valuable_part TEXT,
    areas_for_improvement TEXT,
    additional_comments TEXT,

    -- Metadata
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    submitted_by VARCHAR(20),

    FOREIGN KEY (training_plan_id) REFERENCES training_plans(id) ON DELETE CASCADE,
    INDEX idx_training_plan (training_plan_id),
    INDEX idx_submitted_date (submitted_at)
);

-- 5. Training Feedback & Effectiveness (Section 11.6)
CREATE TABLE training_feedback (
    id INT PRIMARY KEY AUTO_INCREMENT,
    training_plan_id INT NOT NULL,

    -- 11.6.1 Administrative Reference
    training_id VARCHAR(50),
    trainee_name VARCHAR(200),
    evaluator_name VARCHAR(200),
    evaluator_epf VARCHAR(20),
    review_date DATE NOT NULL,

    -- 11.6.2 Post-Training Competency Assessment (1-5 scale)
    skill_transfer_rating TINYINT CHECK (skill_transfer_rating BETWEEN 1 AND 5),
    performance_improvement_rating TINYINT CHECK (performance_improvement_rating BETWEEN 1 AND 5),
    knowledge_sharing_rating TINYINT CHECK (knowledge_sharing_rating BETWEEN 1 AND 5),
    autonomy_rating TINYINT CHECK (autonomy_rating BETWEEN 1 AND 5),

    -- 11.6.3 Operational Impact
    critical_gap_closure ENUM('Y', 'N') DEFAULT 'N',
    productivity_change ENUM('Increased', 'Remained Constant', 'Decreased') DEFAULT 'Remained Constant',
    error_reduction ENUM('Y', 'N', 'N/A') DEFAULT 'N/A',

    -- Metadata
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('draft', 'submitted_for_review', 'completed') DEFAULT 'draft',

    FOREIGN KEY (training_plan_id) REFERENCES training_plans(id) ON DELETE CASCADE,
    INDEX idx_training_plan (training_plan_id),
    INDEX idx_review_date (review_date),
    INDEX idx_status (status)
);

-- 6. Managerial Comments & Action Plan (Section 11.7)
CREATE TABLE training_managerial_comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    training_feedback_id INT NOT NULL,
    training_evaluation_id INT NULL,

    -- 11.7 Comments and Actions
    supervisor_observations TEXT,
    further_support_required TEXT,
    overall_effectiveness ENUM('Y', 'N') DEFAULT 'Y',

    -- 11.7.4-11.7.6 Sign-off
    supervisor_name VARCHAR(200),
    supervisor_epf VARCHAR(20),
    sign_off_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (training_feedback_id) REFERENCES training_feedback(id) ON DELETE CASCADE,
    FOREIGN KEY (training_evaluation_id) REFERENCES training_evaluations(id),
    INDEX idx_feedback (training_feedback_id),
    INDEX idx_evaluation (training_evaluation_id)
);

-- 7. Training Tracker (Section 11.8)
CREATE TABLE training_tracker (
    id INT PRIMARY KEY AUTO_INCREMENT,
    training_plan_id INT NOT NULL,

    -- Auto-filled from training plan
    training_id VARCHAR(50),
    trainee_name VARCHAR(200),
    training_title VARCHAR(300),
    training_date DATE,

    -- Status tracking
    status ENUM('Pending', 'Completed', 'Cancelled') DEFAULT 'Pending',
    evaluation_submitted ENUM('Y', 'N', 'N/A') DEFAULT 'N',
    feedback_submitted ENUM('Y', 'N', 'N/A') DEFAULT 'N',
    managerial_comments_submitted ENUM('Y', 'N', 'N/A') DEFAULT 'N',

    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (training_plan_id) REFERENCES training_plans(id) ON DELETE CASCADE,
    INDEX idx_training_plan (training_plan_id),
    INDEX idx_status (status),
    INDEX idx_training_date (training_date)
);

-- 8. Training Types Master Data
CREATE TABLE training_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 9. Training Institutes Master Data
CREATE TABLE training_institutes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    institute_name VARCHAR(300) NOT NULL,
    institute_address TEXT,
    contact_person VARCHAR(200),
    contact_phone VARCHAR(20),
    contact_email VARCHAR(100),
    website_url VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 10. Training Documents/Certificates
CREATE TABLE training_documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    training_plan_id INT NOT NULL,
    document_type ENUM('certificate', 'attendance', 'evaluation', 'material', 'other') NOT NULL,
    document_name VARCHAR(300) NOT NULL,
    document_path VARCHAR(500) NOT NULL,
    file_size INT,
    mime_type VARCHAR(100),
    uploaded_by VARCHAR(20),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (training_plan_id) REFERENCES training_plans(id) ON DELETE CASCADE,
    INDEX idx_training_plan (training_plan_id),
    INDEX idx_document_type (document_type)
);

-- =====================================================
-- Insert Master Data
-- =====================================================

-- Training Types
INSERT INTO training_types (type_name, description) VALUES
('awareness', 'Awareness training programs'),
('certificate', 'Certificate courses and programs'),
('diploma', 'Diploma and degree programs'),
('other', 'Other specialized training programs');

-- Sample Training Institutes (can be expanded)
INSERT INTO training_institutes (institute_name, institute_address, contact_person) VALUES
('Internal Training Center', 'Company Premises', 'HR Department'),
('External Training Provider', 'To be specified', 'Training Coordinator'),
('Online Learning Platform', 'Virtual/Online', 'E-Learning Admin');

-- =====================================================
-- Views for Reporting
-- =====================================================

-- Training Summary View
CREATE VIEW v_training_summary AS
SELECT
    tr.year,
    tr.department,
    tr.location,
    COUNT(tr.id) as total_requirements,
    COUNT(tb.id) as budgeted_items,
    SUM(tb.budget_amount) as total_budget,
    COUNT(tp.id) as planned_trainings,
    COUNT(te.id) as completed_evaluations,
    COUNT(tf.id) as completed_feedback
FROM training_requirements tr
LEFT JOIN training_budget tb ON tr.id = tb.requirement_id AND tb.add_to_budget = TRUE
LEFT JOIN training_plans tp ON tr.id = tp.requirement_id
LEFT JOIN training_evaluations te ON tp.id = te.training_plan_id
LEFT JOIN training_feedback tf ON tp.id = tf.training_plan_id
GROUP BY tr.year, tr.department, tr.location;

-- Training Effectiveness View
CREATE VIEW v_training_effectiveness AS
SELECT
    tp.id as training_plan_id,
    tp.training_name,
    tp.trainee_name,
    tp.training_start_date,
    te.immediate_application_rating,
    te.performance_improvement_rating,
    te.recommend_to_colleague_rating,
    tf.skill_transfer_rating,
    tf.performance_improvement_rating as supervisor_performance_rating,
    tf.productivity_change,
    tmc.overall_effectiveness,
    tt.status as tracker_status
FROM training_plans tp
LEFT JOIN training_evaluations te ON tp.id = te.training_plan_id
LEFT JOIN training_feedback tf ON tp.id = tf.training_plan_id
LEFT JOIN training_managerial_comments tmc ON tf.id = tmc.training_feedback_id
LEFT JOIN training_tracker tt ON tp.id = tt.training_plan_id;