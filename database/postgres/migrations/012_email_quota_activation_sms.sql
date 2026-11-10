CREATE TABLE IF NOT EXISTS public.mail_usage (
    month char(7) NOT NULL,
    provider varchar(20) NOT NULL,
    sent_count integer NOT NULL DEFAULT 0 CHECK (sent_count >= 0),
    last_sent_at timestamptz,
    PRIMARY KEY (month, provider),
    CHECK (month ~ '^[0-9]{4}-[0-9]{2}$')
);

ALTER TABLE public.mail_usage ENABLE ROW LEVEL SECURITY;

ALTER TABLE public.users ADD COLUMN IF NOT EXISTS phone_verified_at timestamptz;

CREATE TABLE IF NOT EXISTS public.registration_sms_challenges (
    id uuid PRIMARY KEY,
    masterlist_id integer NOT NULL REFERENCES public.kk_masterlist(id) ON DELETE CASCADE,
    phone varchar(16) NOT NULL,
    phone_hash char(64) NOT NULL,
    code_hash char(64) NOT NULL,
    expires_at timestamptz NOT NULL,
    attempts integer NOT NULL DEFAULT 0 CHECK (attempts >= 0),
    max_attempts integer NOT NULL DEFAULT 5 CHECK (max_attempts = 5),
    ip varchar(45) NOT NULL,
    created_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_sent_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
    verified_at timestamptz,
    consumed_at timestamptz
);

CREATE INDEX IF NOT EXISTS idx_registration_sms_phone_created
    ON public.registration_sms_challenges (phone_hash, created_at);
CREATE INDEX IF NOT EXISTS idx_registration_sms_ip_created
    ON public.registration_sms_challenges (ip, created_at);
CREATE INDEX IF NOT EXISTS idx_registration_sms_expiry
    ON public.registration_sms_challenges (expires_at);

ALTER TABLE public.registration_sms_challenges ENABLE ROW LEVEL SECURITY;
