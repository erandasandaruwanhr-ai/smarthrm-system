-- SQL script to remove Grievance module tables from SmartHRM
-- Execute this script in phpMyAdmin or MySQL command line

USE smarthrm_db;

-- Drop all grievance-related tables
DROP TABLE IF EXISTS grievance_notifications;
DROP TABLE IF EXISTS grievance_appeals;
DROP TABLE IF EXISTS grievance_resolutions;
DROP TABLE IF EXISTS grievance_investigations;
DROP TABLE IF EXISTS grievance_actions;
DROP TABLE IF EXISTS grievance_sla_settings;
DROP TABLE IF EXISTS grievances;

-- Display success message
SELECT 'All grievance module tables have been removed successfully!' AS message;