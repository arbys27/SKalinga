-- Add quantity column to borrow_records table if it doesn't exist
ALTER TABLE borrow_records ADD COLUMN quantity INT DEFAULT 1 AFTER borrower_name;

-- This will allow tracking how many units were borrowed in each record
