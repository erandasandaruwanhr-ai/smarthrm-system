-- Add driver_type to distinguish between regular drivers and service providers
ALTER TABLE drivers
ADD COLUMN driver_type ENUM('employee', 'service_provider') DEFAULT 'employee' AFTER id,
ADD COLUMN company_name VARCHAR(255) NULL AFTER emp_name,
ADD COLUMN contact_person VARCHAR(255) NULL AFTER company_name,
ADD COLUMN email VARCHAR(255) NULL AFTER phone,
ADD COLUMN allows_multiple_trips TINYINT(1) DEFAULT 0 AFTER is_on_duty,
ADD COLUMN service_areas TEXT NULL AFTER location;

-- Update existing records to be employee type
UPDATE drivers SET driver_type = 'employee' WHERE driver_type IS NULL;

-- For service providers, we'll use emp_number to store vendor ID or code
-- emp_name will store the service provider name
-- license fields won't be required for service providers