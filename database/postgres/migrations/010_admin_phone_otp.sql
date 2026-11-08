-- Admin mobile numbers are required by the OTP login flow.
ALTER TABLE public.admins
    ADD COLUMN IF NOT EXISTS phone varchar(20);
