-- Add columns for single-row goal storage
-- This script adds all necessary columns to store the entire goal form in one row

-- Add section-level merged columns (Main Goals, Weightage, Ratings)
ALTER TABLE goal_details
ADD COLUMN section_1_main_goals TEXT AFTER section_mid_year_progress,
ADD COLUMN section_1_weightage DECIMAL(5,2) AFTER section_1_main_goals,
ADD COLUMN section_1_achieved_percentage DECIMAL(5,2) AFTER section_1_weightage,
ADD COLUMN section_1_self_rating DECIMAL(3,1) AFTER section_1_achieved_percentage,
ADD COLUMN section_1_supervisor_rating DECIMAL(3,1) AFTER section_1_self_rating,
ADD COLUMN section_1_final_rating DECIMAL(3,1) AFTER section_1_supervisor_rating,
ADD COLUMN section_1_mid_year_progress ENUM('YS', 'IP', 'C') AFTER section_1_final_rating;

ALTER TABLE goal_details
ADD COLUMN section_2_main_goals TEXT AFTER section_1_mid_year_progress,
ADD COLUMN section_2_weightage DECIMAL(5,2) AFTER section_2_main_goals,
ADD COLUMN section_2_achieved_percentage DECIMAL(5,2) AFTER section_2_weightage,
ADD COLUMN section_2_self_rating DECIMAL(3,1) AFTER section_2_achieved_percentage,
ADD COLUMN section_2_supervisor_rating DECIMAL(3,1) AFTER section_2_self_rating,
ADD COLUMN section_2_final_rating DECIMAL(3,1) AFTER section_2_supervisor_rating,
ADD COLUMN section_2_mid_year_progress ENUM('YS', 'IP', 'C') AFTER section_2_final_rating;

ALTER TABLE goal_details
ADD COLUMN section_3_main_goals TEXT AFTER section_2_mid_year_progress,
ADD COLUMN section_3_weightage DECIMAL(5,2) AFTER section_3_main_goals,
ADD COLUMN section_3_achieved_percentage DECIMAL(5,2) AFTER section_3_weightage,
ADD COLUMN section_3_self_rating DECIMAL(3,1) AFTER section_3_achieved_percentage,
ADD COLUMN section_3_supervisor_rating DECIMAL(3,1) AFTER section_3_self_rating,
ADD COLUMN section_3_final_rating DECIMAL(3,1) AFTER section_3_supervisor_rating,
ADD COLUMN section_3_mid_year_progress ENUM('YS', 'IP', 'C') AFTER section_3_final_rating;

ALTER TABLE goal_details
ADD COLUMN section_4_main_goals TEXT AFTER section_3_mid_year_progress,
ADD COLUMN section_4_weightage DECIMAL(5,2) AFTER section_4_main_goals,
ADD COLUMN section_4_achieved_percentage DECIMAL(5,2) AFTER section_4_weightage,
ADD COLUMN section_4_self_rating DECIMAL(3,1) AFTER section_4_achieved_percentage,
ADD COLUMN section_4_supervisor_rating DECIMAL(3,1) AFTER section_4_self_rating,
ADD COLUMN section_4_final_rating DECIMAL(3,1) AFTER section_4_supervisor_rating,
ADD COLUMN section_4_mid_year_progress ENUM('YS', 'IP', 'C') AFTER section_4_final_rating;

ALTER TABLE goal_details
ADD COLUMN section_5_main_goals TEXT AFTER section_4_mid_year_progress,
ADD COLUMN section_5_weightage DECIMAL(5,2) AFTER section_5_main_goals,
ADD COLUMN section_5_achieved_percentage DECIMAL(5,2) AFTER section_5_weightage,
ADD COLUMN section_5_self_rating DECIMAL(3,1) AFTER section_5_achieved_percentage,
ADD COLUMN section_5_supervisor_rating DECIMAL(3,1) AFTER section_5_self_rating,
ADD COLUMN section_5_final_rating DECIMAL(3,1) AFTER section_5_supervisor_rating,
ADD COLUMN section_5_mid_year_progress ENUM('YS', 'IP', 'C') AFTER section_5_final_rating;

ALTER TABLE goal_details
ADD COLUMN section_6_main_goals TEXT AFTER section_5_mid_year_progress,
ADD COLUMN section_6_weightage DECIMAL(5,2) AFTER section_6_main_goals,
ADD COLUMN section_6_achieved_percentage DECIMAL(5,2) AFTER section_6_weightage,
ADD COLUMN section_6_self_rating DECIMAL(3,1) AFTER section_6_achieved_percentage,
ADD COLUMN section_6_supervisor_rating DECIMAL(3,1) AFTER section_6_self_rating,
ADD COLUMN section_6_final_rating DECIMAL(3,1) AFTER section_6_supervisor_rating,
ADD COLUMN section_6_mid_year_progress ENUM('YS', 'IP', 'C') AFTER section_6_final_rating;

-- Add individual cell columns for Activities (6 sections × 6 sub-items = 36 columns)
ALTER TABLE goal_details
ADD COLUMN activities_1_1 TEXT AFTER section_6_mid_year_progress,
ADD COLUMN activities_1_2 TEXT AFTER activities_1_1,
ADD COLUMN activities_1_3 TEXT AFTER activities_1_2,
ADD COLUMN activities_1_4 TEXT AFTER activities_1_3,
ADD COLUMN activities_1_5 TEXT AFTER activities_1_4,
ADD COLUMN activities_1_6 TEXT AFTER activities_1_5;

ALTER TABLE goal_details
ADD COLUMN activities_2_1 TEXT AFTER activities_1_6,
ADD COLUMN activities_2_2 TEXT AFTER activities_2_1,
ADD COLUMN activities_2_3 TEXT AFTER activities_2_2,
ADD COLUMN activities_2_4 TEXT AFTER activities_2_3,
ADD COLUMN activities_2_5 TEXT AFTER activities_2_4,
ADD COLUMN activities_2_6 TEXT AFTER activities_2_5;

ALTER TABLE goal_details
ADD COLUMN activities_3_1 TEXT AFTER activities_2_6,
ADD COLUMN activities_3_2 TEXT AFTER activities_3_1,
ADD COLUMN activities_3_3 TEXT AFTER activities_3_2,
ADD COLUMN activities_3_4 TEXT AFTER activities_3_3,
ADD COLUMN activities_3_5 TEXT AFTER activities_3_4,
ADD COLUMN activities_3_6 TEXT AFTER activities_3_5;

ALTER TABLE goal_details
ADD COLUMN activities_4_1 TEXT AFTER activities_3_6,
ADD COLUMN activities_4_2 TEXT AFTER activities_4_1,
ADD COLUMN activities_4_3 TEXT AFTER activities_4_2,
ADD COLUMN activities_4_4 TEXT AFTER activities_4_3,
ADD COLUMN activities_4_5 TEXT AFTER activities_4_4,
ADD COLUMN activities_4_6 TEXT AFTER activities_4_5;

ALTER TABLE goal_details
ADD COLUMN activities_5_1 TEXT AFTER activities_4_6,
ADD COLUMN activities_5_2 TEXT AFTER activities_5_1,
ADD COLUMN activities_5_3 TEXT AFTER activities_5_2,
ADD COLUMN activities_5_4 TEXT AFTER activities_5_3,
ADD COLUMN activities_5_5 TEXT AFTER activities_5_4,
ADD COLUMN activities_5_6 TEXT AFTER activities_5_5;

ALTER TABLE goal_details
ADD COLUMN activities_6_1 TEXT AFTER activities_5_6,
ADD COLUMN activities_6_2 TEXT AFTER activities_6_1,
ADD COLUMN activities_6_3 TEXT AFTER activities_6_2,
ADD COLUMN activities_6_4 TEXT AFTER activities_6_3,
ADD COLUMN activities_6_5 TEXT AFTER activities_6_4,
ADD COLUMN activities_6_6 TEXT AFTER activities_6_5;

-- Add individual cell columns for Measurement Criteria (6 sections × 6 sub-items = 36 columns)
ALTER TABLE goal_details
ADD COLUMN measurement_criteria_1_1 TEXT AFTER activities_6_6,
ADD COLUMN measurement_criteria_1_2 TEXT AFTER measurement_criteria_1_1,
ADD COLUMN measurement_criteria_1_3 TEXT AFTER measurement_criteria_1_2,
ADD COLUMN measurement_criteria_1_4 TEXT AFTER measurement_criteria_1_3,
ADD COLUMN measurement_criteria_1_5 TEXT AFTER measurement_criteria_1_4,
ADD COLUMN measurement_criteria_1_6 TEXT AFTER measurement_criteria_1_5;

ALTER TABLE goal_details
ADD COLUMN measurement_criteria_2_1 TEXT AFTER measurement_criteria_1_6,
ADD COLUMN measurement_criteria_2_2 TEXT AFTER measurement_criteria_2_1,
ADD COLUMN measurement_criteria_2_3 TEXT AFTER measurement_criteria_2_2,
ADD COLUMN measurement_criteria_2_4 TEXT AFTER measurement_criteria_2_3,
ADD COLUMN measurement_criteria_2_5 TEXT AFTER measurement_criteria_2_4,
ADD COLUMN measurement_criteria_2_6 TEXT AFTER measurement_criteria_2_5;

ALTER TABLE goal_details
ADD COLUMN measurement_criteria_3_1 TEXT AFTER measurement_criteria_2_6,
ADD COLUMN measurement_criteria_3_2 TEXT AFTER measurement_criteria_3_1,
ADD COLUMN measurement_criteria_3_3 TEXT AFTER measurement_criteria_3_2,
ADD COLUMN measurement_criteria_3_4 TEXT AFTER measurement_criteria_3_3,
ADD COLUMN measurement_criteria_3_5 TEXT AFTER measurement_criteria_3_4,
ADD COLUMN measurement_criteria_3_6 TEXT AFTER measurement_criteria_3_5;

ALTER TABLE goal_details
ADD COLUMN measurement_criteria_4_1 TEXT AFTER measurement_criteria_3_6,
ADD COLUMN measurement_criteria_4_2 TEXT AFTER measurement_criteria_4_1,
ADD COLUMN measurement_criteria_4_3 TEXT AFTER measurement_criteria_4_2,
ADD COLUMN measurement_criteria_4_4 TEXT AFTER measurement_criteria_4_3,
ADD COLUMN measurement_criteria_4_5 TEXT AFTER measurement_criteria_4_4,
ADD COLUMN measurement_criteria_4_6 TEXT AFTER measurement_criteria_4_5;

ALTER TABLE goal_details
ADD COLUMN measurement_criteria_5_1 TEXT AFTER measurement_criteria_4_6,
ADD COLUMN measurement_criteria_5_2 TEXT AFTER measurement_criteria_5_1,
ADD COLUMN measurement_criteria_5_3 TEXT AFTER measurement_criteria_5_2,
ADD COLUMN measurement_criteria_5_4 TEXT AFTER measurement_criteria_5_3,
ADD COLUMN measurement_criteria_5_5 TEXT AFTER measurement_criteria_5_4,
ADD COLUMN measurement_criteria_5_6 TEXT AFTER measurement_criteria_5_5;

ALTER TABLE goal_details
ADD COLUMN measurement_criteria_6_1 TEXT AFTER measurement_criteria_5_6,
ADD COLUMN measurement_criteria_6_2 TEXT AFTER measurement_criteria_6_1,
ADD COLUMN measurement_criteria_6_3 TEXT AFTER measurement_criteria_6_2,
ADD COLUMN measurement_criteria_6_4 TEXT AFTER measurement_criteria_6_3,
ADD COLUMN measurement_criteria_6_5 TEXT AFTER measurement_criteria_6_4,
ADD COLUMN measurement_criteria_6_6 TEXT AFTER measurement_criteria_6_5;

-- Create indexes for better performance
CREATE INDEX idx_goal_details_complete_form ON goal_details(record_id, goal_subsection);

-- Verification query
-- SELECT COUNT(*) as total_columns FROM information_schema.columns WHERE table_name = 'goal_details' AND table_schema = 'smarthrm_db';