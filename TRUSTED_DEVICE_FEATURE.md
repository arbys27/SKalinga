# 30-Day Trusted Device Feature

## Overview

Users no longer need to verify OTP on every login. Once they verify their phone number with OTP, they can trust the device for 30 days and skip OTP verification during subsequent logins.

## How It Works

### First Login (or after 30 days)
1. User enters phone number and password
2. Checkbox option: "Trust this device for 30 days" (unchecked by default)
3. User submits the form
4. OTP code is sent to their phone
5. User enters the 6-digit code
6. If "Trust this device" was checked, device is marked as trusted
7. User is logged in

### Subsequent Logins (within 30 days) - If Device is Trusted
1. User enters phone number and password
2. Option: Check "Trust this device for 30 days" checkbox
3. User submits the form
4. **NO OTP required** - User is logged in immediately
5. Message: "Welcome back! Login successful."

### After 30 Days
- Device trust expires automatically
- User needs to verify OTP again on next login
- Can check "Trust this device" again to extend for another 30 days

## Database Changes Required

### For Supabase (PostgreSQL)
Run the SQL migration:
```sql
ALTER TABLE users
ADD COLUMN IF NOT EXISTS last_otp_verified_at TIMESTAMP DEFAULT NULL;

CREATE INDEX IF NOT EXISTS idx_users_last_otp_verified_at 
ON users(id, last_otp_verified_at);
```

**File**: `database/add_trusted_device_supabase.sql`

### For Local Testing (MySQL)
Run the SQL migration:
```sql
ALTER TABLE users
ADD COLUMN IF NOT EXISTS last_otp_verified_at TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE users
ADD INDEX IF NOT EXISTS idx_users_last_otp_verified_at (id, last_otp_verified_at);
```

**File**: `database/add_trusted_device_mysql.sql`

## Frontend Changes

### Changes to `index.html`
1. Added checkbox on login form: "Trust this device for 30 days"
2. Modified login submission to:
   - Send `trust_device` flag to backend
   - Handle `skip_otp: true` response from backend
   - Redirect directly to `youth-portal.html` if OTP is skipped
3. Enhanced OTP auto-submit logic - still works when OTP screen is shown

### User Experience
- Checkbox is **unchecked by default** (users must explicitly choose to trust)
- If checkbox is checked AND device was trusted within 30 days:
  - OTP screen is not shown
  - User is logged in immediately
- If checkbox is unchecked OR device trust has expired:
  - OTP screen appears as normal
  - User must enter 6-digit code

## API Changes

### `api/send_login_otp.php`
**New Parameters:**
- `trust_device` (1 or 0): Whether user wants to trust this device

**New Logic:**
1. Checks `users.last_otp_verified_at` column
2. If timestamp exists AND is within 30 days AND `trust_device=1`:
   - Returns `"skip_otp": true`
   - Creates authenticated session immediately
   - No OTP sent to phone
3. Otherwise:
   - Generates and sends OTP as normal
   - Returns `"skip_otp": false`

**Response Example (Trusted Device):**
```json
{
  "success": true,
  "message": "Welcome back! Login successful.",
  "skip_otp": true,
  "redirect": "youth-portal.html"
}
```

**Response Example (OTP Required):**
```json
{
  "success": true,
  "message": "Login code sent to your phone",
  "skip_otp": false,
  "phone": "09123456789"
}
```

### `api/verify_login_otp.php`
**New Logic:**
1. Checks if `login_trust_device` flag was set in session
2. If flag is set:
   - Updates `users.last_otp_verified_at` to current timestamp
3. Uses this timestamp on next login to determine if device is trusted

## Security Considerations

✅ **Secure Implementation:**
- Device trust is tied to successful OTP verification
- 30-day window is reasonable security-usability tradeoff
- User must explicitly check the "Trust this device" box (opt-in)
- After 30 days, OTP verification required again automatically
- Password is always required for every login attempt

⚠️ **When to Worry:**
- If device is compromised, attacker can access account for up to 30 days
- Recommended: Logout on shared devices before leaving

## Testing Checklist

- [ ] Run database migration (add `last_otp_verified_at` column)
- [ ] First login: Enter phone/password, trust device checkbox checked, verify OTP
- [ ] Second login (same day): Phone/password alone, should skip OTP
- [ ] Third login (with checkbox UNCHECKED): Should require OTP again even if within 30 days
- [ ] Test after 30 days: Should require OTP verification again
- [ ] Verify auto-submit still works when OTP is required

## Troubleshooting

### OTP still appears on second login
**Solution:** Make sure "Trust this device" checkbox was checked on first login

### Getting "Column 'last_otp_verified_at' doesn't exist" error
**Solution:** Run the database migration SQL file in your database

### Device is trusted but OTP is still required
**Solution:** Checkbox might not have been checked. Try again with checkbox enabled.

## Future Enhancements

- [ ] Show "Device trusted until: [date]" message
- [ ] Add device management page (view/remove trusted devices)
- [ ] Option to force OTP verification anytime
- [ ] Support for multiple devices with different trust dates
- [ ] "Remember me" on specific devices only (not account-wide)
