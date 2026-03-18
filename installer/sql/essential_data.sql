-- SmartHRM Core Data Insert
-- Essential data for system functionality

-- Insert Default Data
INSERT INTO account_types (id, type_name, description, level_name, level) VALUES
(1, 'user', 'Regular User', 'Employee', 1),
(2, 'supervisor', 'Supervisor', 'Supervisor', 2),
(3, 'manager', 'Manager', 'Manager', 3),
(4, 'admin', 'Administrator', 'Administrator', 4),
(5, 'superadmin', 'Super Administrator', 'Super Administrator', 5);

INSERT INTO locations (id, location_name) VALUES
(1, '7C'),
(2, 'Pannala'),
(3, 'Kobeigane'),
(4, 'JECOE'),
(5, 'Head Office');

INSERT INTO employment_levels (id, level_name, level_order) VALUES
(1, 'MD', 1),
(2, 'GM', 2),
(3, 'Manager', 3),
(4, 'Assistant Manager', 4),
(5, 'Senior Executive', 5),
(6, 'Executive', 6),
(7, 'Junior Executive', 7),
(8, 'Supervisor', 8),
(9, 'Staff', 9);

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
    gender, employment_level_id, location_id, account_type, account_type_id, password_hash
) VALUES (
    'ADMIN001', 'System Administrator', 'System Admin', 'IT',
    '123456789V', '1990-01-01', '2024-01-01', 'Male',
    1, 5, 'superadmin', 5, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
);

-- Role Permissions for Key Talent Module
INSERT INTO role_permissions (role, module, permission, granted) VALUES
('user', 'key_talent', 'view', 1),
('user', 'key_talent', 'talent_grid', 1),

('supervisor', 'key_talent', 'view', 1),
('supervisor', 'key_talent', 'assessment_form', 1),
('supervisor', 'key_talent', 'assessment_results', 1),
('supervisor', 'key_talent', 'candidates_list', 1),
('supervisor', 'key_talent', 'conduct_assessment', 1),
('supervisor', 'key_talent', 'reports', 1),
('supervisor', 'key_talent', 'talent_grid', 1),

('manager', 'key_talent', 'view', 1),
('manager', 'key_talent', 'assessment_form', 1),
('manager', 'key_talent', 'assessment_results', 1),
('manager', 'key_talent', 'candidates_list', 1),
('manager', 'key_talent', 'conduct_assessment', 1),
('manager', 'key_talent', 'reports', 1),
('manager', 'key_talent', 'talent_grid', 1),

('admin', 'key_talent', 'view', 1),
('admin', 'key_talent', 'assessment_form', 1),
('admin', 'key_talent', 'assessment_results', 1),
('admin', 'key_talent', 'candidates_list', 1),
('admin', 'key_talent', 'conduct_assessment', 1),
('admin', 'key_talent', 'form_setup', 1),
('admin', 'key_talent', 'reports', 1),
('admin', 'key_talent', 'talent_grid', 1),

('superadmin', 'key_talent', 'view', 1),
('superadmin', 'key_talent', 'assessment_form', 1),
('superadmin', 'key_talent', 'assessment_results', 1),
('superadmin', 'key_talent', 'candidates_list', 1),
('superadmin', 'key_talent', 'conduct_assessment', 1),
('superadmin', 'key_talent', 'form_setup', 1),
('superadmin', 'key_talent', 'reports', 1),
('superadmin', 'key_talent', 'talent_grid', 1);

-- Role Permissions for Skill Matrix Module
INSERT INTO role_permissions (role, module, permission, granted) VALUES
('user', 'skill', 'view', 1),
('user', 'skill', 'view_skill_matrix', 1),
('user', 'skill', 'complete_skill_assessment', 1),

('supervisor', 'skill', 'view', 1),
('supervisor', 'skill', 'view_skill_matrix', 1),
('supervisor', 'skill', 'complete_skill_assessment', 1),
('supervisor', 'skill', 'skill_assessment', 1),
('supervisor', 'skill', 'employee_list', 1),
('supervisor', 'skill', 'assessment_reports', 1),

('manager', 'skill', 'view', 1),
('manager', 'skill', 'view_skill_matrix', 1),
('manager', 'skill', 'complete_skill_assessment', 1),
('manager', 'skill', 'skill_assessment', 1),
('manager', 'skill', 'employee_list', 1),
('manager', 'skill', 'assessment_reports', 1),
('manager', 'skill', 'matrix_setup', 1),

('admin', 'skill', 'view', 1),
('admin', 'skill', 'view_skill_matrix', 1),
('admin', 'skill', 'complete_skill_assessment', 1),
('admin', 'skill', 'skill_assessment', 1),
('admin', 'skill', 'employee_list', 1),
('admin', 'skill', 'assessment_reports', 1),
('admin', 'skill', 'matrix_setup', 1),

('superadmin', 'skill', 'view', 1),
('superadmin', 'skill', 'view_skill_matrix', 1),
('superadmin', 'skill', 'complete_skill_assessment', 1),
('superadmin', 'skill', 'skill_assessment', 1),
('superadmin', 'skill', 'employee_list', 1),
('superadmin', 'skill', 'assessment_reports', 1),
('superadmin', 'skill', 'matrix_setup', 1);

-- Account Permissions for Key Talent Module
INSERT INTO account_permissions (account_type_id, module_name, action_name) VALUES
(1, 'Key Talent Identification', 'key_talent.view'),
(1, 'Key Talent Identification', 'key_talent.talent_grid'),

(2, 'Key Talent Identification', 'key_talent.view'),
(2, 'Key Talent Identification', 'key_talent.assessment_form'),
(2, 'Key Talent Identification', 'key_talent.assessment_results'),
(2, 'Key Talent Identification', 'key_talent.candidates_list'),
(2, 'Key Talent Identification', 'key_talent.conduct_assessment'),
(2, 'Key Talent Identification', 'key_talent.reports'),
(2, 'Key Talent Identification', 'key_talent.talent_grid'),

(3, 'Key Talent Identification', 'key_talent.view'),
(3, 'Key Talent Identification', 'key_talent.assessment_form'),
(3, 'Key Talent Identification', 'key_talent.assessment_results'),
(3, 'Key Talent Identification', 'key_talent.candidates_list'),
(3, 'Key Talent Identification', 'key_talent.conduct_assessment'),
(3, 'Key Talent Identification', 'key_talent.form_setup'),
(3, 'Key Talent Identification', 'key_talent.reports'),
(3, 'Key Talent Identification', 'key_talent.talent_grid'),

(4, 'Key Talent Identification', 'key_talent.view'),
(4, 'Key Talent Identification', 'key_talent.assessment_form'),
(4, 'Key Talent Identification', 'key_talent.assessment_results'),
(4, 'Key Talent Identification', 'key_talent.candidates_list'),
(4, 'Key Talent Identification', 'key_talent.conduct_assessment'),
(4, 'Key Talent Identification', 'key_talent.form_setup'),
(4, 'Key Talent Identification', 'key_talent.reports'),
(4, 'Key Talent Identification', 'key_talent.talent_grid'),

(5, 'Key Talent Identification', 'key_talent.view'),
(5, 'Key Talent Identification', 'key_talent.assessment_form'),
(5, 'Key Talent Identification', 'key_talent.assessment_results'),
(5, 'Key Talent Identification', 'key_talent.candidates_list'),
(5, 'Key Talent Identification', 'key_talent.conduct_assessment'),
(5, 'Key Talent Identification', 'key_talent.form_setup'),
(5, 'Key Talent Identification', 'key_talent.reports'),
(5, 'Key Talent Identification', 'key_talent.talent_grid');

-- Account Permissions for Skill Matrix Module
INSERT INTO account_permissions (account_type_id, module_name, action_name) VALUES
(1, 'Skill Matrix', 'skill.view'),
(1, 'Skill Matrix', 'skill.view_skill_matrix'),
(1, 'Skill Matrix', 'skill.complete_skill_assessment'),

(2, 'Skill Matrix', 'skill.view'),
(2, 'Skill Matrix', 'skill.view_skill_matrix'),
(2, 'Skill Matrix', 'skill.complete_skill_assessment'),
(2, 'Skill Matrix', 'skill.skill_assessment'),
(2, 'Skill Matrix', 'skill.employee_list'),
(2, 'Skill Matrix', 'skill.assessment_reports'),

(3, 'Skill Matrix', 'skill.view'),
(3, 'Skill Matrix', 'skill.view_skill_matrix'),
(3, 'Skill Matrix', 'skill.complete_skill_assessment'),
(3, 'Skill Matrix', 'skill.skill_assessment'),
(3, 'Skill Matrix', 'skill.employee_list'),
(3, 'Skill Matrix', 'skill.assessment_reports'),
(3, 'Skill Matrix', 'skill.matrix_setup'),

(4, 'Skill Matrix', 'skill.view'),
(4, 'Skill Matrix', 'skill.view_skill_matrix'),
(4, 'Skill Matrix', 'skill.complete_skill_assessment'),
(4, 'Skill Matrix', 'skill.skill_assessment'),
(4, 'Skill Matrix', 'skill.employee_list'),
(4, 'Skill Matrix', 'skill.assessment_reports'),
(4, 'Skill Matrix', 'skill.matrix_setup'),

(5, 'Skill Matrix', 'skill.view'),
(5, 'Skill Matrix', 'skill.view_skill_matrix'),
(5, 'Skill Matrix', 'skill.complete_skill_assessment'),
(5, 'Skill Matrix', 'skill.skill_assessment'),
(5, 'Skill Matrix', 'skill.employee_list'),
(5, 'Skill Matrix', 'skill.assessment_reports'),
(5, 'Skill Matrix', 'skill.matrix_setup');

-- Training Types
INSERT INTO training_types (type_name, description) VALUES
('Technical Training', 'Technical skills development'),
('Safety Training', 'Health and safety protocols'),
('Leadership Training', 'Management and leadership skills'),
('Compliance Training', 'Regulatory and policy compliance'),
('Soft Skills Training', 'Communication and interpersonal skills');