-- Update anonymous_grievance_notes action_type ENUM to include new investigation note types
ALTER TABLE `anonymous_grievance_notes`
MODIFY COLUMN `action_type` ENUM(
    'Submission',               -- Initial anonymous submission
    'Investigation Assignment', -- Team assigned
    'Investigation start',      -- Investigation team starting work
    'Investigation evidence',   -- Evidence collected during investigation
    'Final report',            -- Final investigation report
    'Investigation Report',     -- Team findings (legacy)
    'Investigation Progress',   -- Team updates (legacy)
    'Evidence Added',          -- New evidence (legacy)
    'Superadmin Review',       -- Superadmin notes
    'Resolution',              -- Final closure
    'Dismissal'                -- Case dismissed
) NOT NULL;