-- Add columns for trip metrics to transport_allocations table
-- These columns store data when a trip is completed

-- Add odometer reading columns
ALTER TABLE transport_allocations
ADD COLUMN odometer_start INT NULL AFTER notes,
ADD COLUMN odometer_end INT NULL AFTER odometer_start;

-- Add fuel level columns
ALTER TABLE transport_allocations
ADD COLUMN fuel_start DECIMAL(10,2) NULL AFTER odometer_end,
ADD COLUMN fuel_end DECIMAL(10,2) NULL AFTER fuel_start;

-- Add actual return time (when trip was actually completed)
ALTER TABLE transport_allocations
ADD COLUMN actual_return DATETIME NULL AFTER scheduled_return;

-- Add index for better performance on completed trips queries
ALTER TABLE transport_allocations
ADD INDEX idx_status_actual_return (status, actual_return);

-- Update any existing completed trips to have actual_return = updated_at if not set
UPDATE transport_allocations
SET actual_return = updated_at
WHERE status = 'completed' AND actual_return IS NULL;