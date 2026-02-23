-- Add trusted device tracking for monthly OTP verification
-- PostgreSQL syntax for Supabase

-- Add column to track when OTP was last verified
ALTER TABLE users
ADD COLUMN IF NOT EXISTS last_otp_verified_at TIMESTAMP DEFAULT NULL;

-- Add index for efficient querying
CREATE INDEX IF NOT EXISTS idx_users_last_otp_verified_at 
ON users(id, last_otp_verified_at);

-- Comment explaining the column
COMMENT ON COLUMN users.last_otp_verified_at IS 
'Timestamp of last successful OTP verification. Used to determine if device is trusted for 30 days.';
