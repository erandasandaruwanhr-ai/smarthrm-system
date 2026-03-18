-- Update Database Structure for New Goal Setting Design
-- This script updates the existing goal setting tables to match the new merged design

-- 1. Update goal_setting_templates table to support section-based structure
ALTER TABLE goal_setting_templates
ADD COLUMN section_number INT AFTER goal_subsection,
ADD COLUMN is_section_header ENUM('Y', 'N') DEFAULT 'N' AFTER section_number;

-- 2. Update goal_details table for new merged structure
ALTER TABLE goal_details
ADD COLUMN section_number INT AFTER goal_subsection,
ADD COLUMN section_main_goals TEXT AFTER measurement_criteria,
ADD COLUMN section_weightage DECIMAL(5,2) AFTER section_main_goals,
ADD COLUMN section_achieved_percentage DECIMAL(5,2) AFTER mid_year_progress,
ADD COLUMN section_self_rating DECIMAL(3,1) AFTER section_achieved_percentage,
ADD COLUMN section_supervisor_rating DECIMAL(3,1) AFTER section_self_rating,
ADD COLUMN section_final_rating DECIMAL(3,1) AFTER section_supervisor_rating,
ADD COLUMN section_mid_year_progress ENUM('YS', 'IP', 'C') AFTER section_final_rating;

-- 3. Create indexes for better performance
CREATE INDEX idx_goal_details_section ON goal_details(section_number);
CREATE INDEX idx_templates_section ON goal_setting_templates(section_number);

-- 4. Update existing template data to include section numbers
UPDATE goal_setting_templates SET
    section_number = 1,
    is_section_header = CASE
        WHEN goal_subsection = '12.3.3.1' THEN 'Y'
        ELSE 'N'
    END
WHERE goal_subsection LIKE '12.3.3.1%';

UPDATE goal_setting_templates SET
    section_number = 2,
    is_section_header = CASE
        WHEN goal_subsection = '12.3.3.2' THEN 'Y'
        ELSE 'N'
    END
WHERE goal_subsection LIKE '12.3.3.2%';

UPDATE goal_setting_templates SET
    section_number = 3,
    is_section_header = CASE
        WHEN goal_subsection = '12.3.3.3' THEN 'Y'
        ELSE 'N'
    END
WHERE goal_subsection LIKE '12.3.3.3%';

UPDATE goal_setting_templates SET
    section_number = 4,
    is_section_header = CASE
        WHEN goal_subsection = '12.3.3.4' THEN 'Y'
        ELSE 'N'
    END
WHERE goal_subsection LIKE '12.3.3.4%';

UPDATE goal_setting_templates SET
    section_number = 5,
    is_section_header = CASE
        WHEN goal_subsection = '12.3.3.5' THEN 'Y'
        ELSE 'N'
    END
WHERE goal_subsection LIKE '12.3.3.5%';

UPDATE goal_setting_templates SET
    section_number = 6,
    is_section_header = CASE
        WHEN goal_subsection = '12.3.3.6' THEN 'Y'
        ELSE 'N'
    END
WHERE goal_subsection LIKE '12.3.3.6%';

-- 5. Update existing goal_details data to include section numbers
UPDATE goal_details SET
    section_number = 1
WHERE goal_subsection LIKE '12.3.3.1%';

UPDATE goal_details SET
    section_number = 2
WHERE goal_subsection LIKE '12.3.3.2%';

UPDATE goal_details SET
    section_number = 3
WHERE goal_subsection LIKE '12.3.3.3%';

UPDATE goal_details SET
    section_number = 4
WHERE goal_subsection LIKE '12.3.3.4%';

UPDATE goal_details SET
    section_number = 5
WHERE goal_subsection LIKE '12.3.3.5%';

UPDATE goal_details SET
    section_number = 6
WHERE goal_subsection LIKE '12.3.3.6%';

-- 6. Create a view for easy section-based data retrieval
CREATE OR REPLACE VIEW goal_sections_view AS
SELECT
    gd.record_id,
    gd.section_number,
    MAX(CASE WHEN gd.goal_subsection LIKE '%.1' THEN gd.main_goals END) as section_main_goals,
    MAX(CASE WHEN gd.goal_subsection LIKE '%.1' THEN gd.weightage END) as section_weightage,
    MAX(CASE WHEN gd.goal_subsection LIKE '%.1' THEN gd.achieved_percentage END) as section_achieved_percentage,
    MAX(CASE WHEN gd.goal_subsection LIKE '%.1' THEN gd.self_rating END) as section_self_rating,
    MAX(CASE WHEN gd.goal_subsection LIKE '%.1' THEN gd.supervisor_rating END) as section_supervisor_rating,
    MAX(CASE WHEN gd.goal_subsection LIKE '%.1' THEN gd.final_rating END) as section_final_rating,
    MAX(CASE WHEN gd.goal_subsection LIKE '%.1' THEN gd.mid_year_progress END) as section_mid_year_progress,
    COUNT(*) as sub_items_count
FROM goal_details gd
WHERE gd.section_number IS NOT NULL
GROUP BY gd.record_id, gd.section_number
ORDER BY gd.record_id, gd.section_number;

-- 7. Optional: Create stored procedure for section data updates
DELIMITER //
CREATE PROCEDURE UpdateSectionData(
    IN p_record_id INT,
    IN p_section_number INT,
    IN p_main_goals TEXT,
    IN p_weightage DECIMAL(5,2),
    IN p_achieved_percentage DECIMAL(5,2),
    IN p_self_rating DECIMAL(3,1),
    IN p_supervisor_rating DECIMAL(3,1),
    IN p_final_rating DECIMAL(3,1),
    IN p_mid_year_progress ENUM('YS', 'IP', 'C')
)
BEGIN
    -- Update all sub-items in the section with merged data
    UPDATE goal_details
    SET
        section_main_goals = p_main_goals,
        section_weightage = p_weightage,
        section_achieved_percentage = p_achieved_percentage,
        section_self_rating = p_self_rating,
        section_supervisor_rating = p_supervisor_rating,
        section_final_rating = p_final_rating,
        section_mid_year_progress = p_mid_year_progress,
        updated_date = NOW()
    WHERE record_id = p_record_id
    AND section_number = p_section_number;
END //
DELIMITER ;

-- 8. Verification queries (run these to check the updates)
-- SELECT section_number, COUNT(*) as items_count FROM goal_setting_templates GROUP BY section_number;
-- SELECT section_number, COUNT(*) as items_count FROM goal_details GROUP BY section_number;
-- SELECT * FROM goal_sections_view LIMIT 10;

-- Note: Backup your database before running this script!
-- Run: mysqldump -u username -p smarthrm_db > backup_before_structure_update.sql