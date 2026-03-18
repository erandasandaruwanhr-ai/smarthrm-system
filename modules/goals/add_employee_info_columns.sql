-- Add employee information columns to goal_details table

ALTER TABLE goal_details
ADD COLUMN employee_epf VARCHAR(10) AFTER goal_subsection,
ADD COLUMN employee_name VARCHAR(255) AFTER employee_epf,
ADD COLUMN employee_designation VARCHAR(255) AFTER employee_name,
ADD COLUMN employee_department VARCHAR(255) AFTER employee_designation,
ADD COLUMN employee_location VARCHAR(255) AFTER employee_department,
ADD COLUMN employee_joining_date DATE AFTER employee_location,
ADD COLUMN employee_service_years VARCHAR(50) AFTER employee_joining_date,
ADD COLUMN employee_manager_epf VARCHAR(10) AFTER employee_service_years,
ADD COLUMN employee_manager_name VARCHAR(255) AFTER employee_manager_epf;

-- Create index for employee EPF for faster lookups
CREATE INDEX idx_goal_details_employee_epf ON goal_details(employee_epf);