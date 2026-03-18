-- Add seating_capacity column to service_providers table
ALTER TABLE service_providers
ADD COLUMN seating_capacity INT DEFAULT 8 AFTER vehicle_types;

-- Update existing service providers with default capacity
UPDATE service_providers SET seating_capacity = 8 WHERE seating_capacity IS NULL;