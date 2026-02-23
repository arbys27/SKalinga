-- Add trusted device tracking for monthly OTP verification
-- MySQL syntax for local testing

-- Add column to track when OTP was last verified (if not already present)
ALTER TABLE users
ADD COLUMN IF NOT EXISTS last_otp_verified_at TIMESTAMP NULL DEFAULT NULL;

-- Add index for efficient querying
ALTER TABLE users
ADD INDEX IF NOT EXISTS idx_users_last_otp_verified_at (id, last_otp_verified_at);
