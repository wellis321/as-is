-- Add 'subprocess' and 'parallel' to the step_type ENUM.
-- Run once after deploy. Safe to run again — the MODIFY is idempotent.

ALTER TABLE steps
    MODIFY COLUMN step_type
        ENUM('start', 'task', 'decision', 'end', 'subprocess', 'parallel')
        NOT NULL DEFAULT 'task';
