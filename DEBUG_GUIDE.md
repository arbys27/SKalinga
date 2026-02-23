# SKalinga Mobile Login - Debugging Guide

## Step-by-Step Debug

### Step 1: Check API Connectivity
1. Open browser and go to: `http://localhost:8000/test_api.php` (or your local URL)
2. You'll see a JSON with test results
3. Check:
   - ✅ `database.status` = "connected" (Supabase connection)
   - ✅ `session.status` = "working" (PHP sessions working)
   - ✅ `sms_api.status` = "reachable" (SMS API accessible)
   - ✅ All files exist in your project

**If any of these fail, note it down!**

---

### Step 2: Open Browser DevTools
1. Right-click → Inspect (or press F12)
2. Go to **Console** tab
3. Go to **Network** tab

---

### Step 3: Test Phone Verification (OTP Sending)

1. **Fill registration form** with test data:
   - First Name: `Juan`
   - Last Name: `Dela Cruz`
   - Birthday: Any valid date
   - Gender: Male
   - Email: `test@example.com`
   - Address: `123 Main St, Brgy San Antonio`
   - **Phone: `09123456789`** ← Important for testing
   - Password: `Test123456`

2. **Click "Verify" button** to send OTP

3. **In Network tab**, look for:
   - Request to: `api/send_registration_otp.php`
   - Status should be: `200`
   - Response should show: `"success": true`

4. **In Console tab**, you should see:
   - Message about OTP being sent
   - ✓ OTP sent to your number. Enter it below

**If you see error:**
- Check Network Response → it should show what went wrong
- Common errors:
  - "Valid 11-digit phone number is required" → Phone format wrong
  - "Phone number already registered" → Phone used before
  - "An error occurred" → Backend error

---

### Step 4: Check Server Error Logs

Your error log file is at:
```
c:\xampp\apache\logs\error.log
```

Open this file and look for recent entries from `send_registration_otp.php` or `verify_registration_otp.php`

You should see logs like:
```
[2026-02-23 ...] === send_registration_otp.php START ===
[2026-02-23 ...] POST data: Array ( [phone] => 09123456789 )
[2026-02-23 ...] Phone received: '09123456789'
[2026-02-23 ...] Phone check - Database query executed
```

---

### Step 5: Test OTP Verification

After sending OTP:

1. **Manually enter OTP**: Since SMS might not work in testing, the OTP is displayed in server logs or generated:
   - OTP format: `123456` (6 digits)
   - Check server logs for the generated OTP value

2. **Enter OTP in form** and click verify button

3. **In Network tab**, check:
   - Request to: `api/verify_registration_otp.php`
   - Status: `200`
   - Response: `"success": true`

4. You should see: **✓ Phone verified successfully**

---

### Step 6: Test Registration Submission

1. **Fill remaining fields**:
   - Create Password: `Test123456` (min 8 chars)
   - Confirm Password: `Test123456`
   - Check privacy consent

2. **Click "Create Account"** button

3. **In Network tab**, check:
   - Request: `api/register.php`
   - Status: `200`
   - Response should show: `"member_id"` and `"redirect"`

4. **In Console**, check for success message

---

## Common Issues & Fixes

### Issue 1: "An error occurred. Please try again." on registration

**Cause:** Database error during user/profile insertion

**Debug Steps:**
1. Check `c:\xampp\apache\logs\error.log` for detailed error
2. Look for lines with "Exception during registration:"
3. Common causes:
   - `phone_verified` column doesn't exist → Run database migration
   - `youth_profiles` table missing phone column
   - `users` table structure mismatch

**Fix:** 
```sql
-- In Supabase SQL Editor, run:
SELECT column_name, data_type FROM information_schema.columns 
WHERE table_name = 'users' 
ORDER BY ordinal_position;
```

Check that these columns exist:
- `phone_verified` (BOOLEAN)
- `phone_verified_at` (TIMESTAMP)

---

### Issue 2: OTP Not Sending (No error shown)

**Cause:** SMS API unreachable or network issue

**Debug Steps:**
1. Check error log for "SMS API Response Code:"
2. If code is `0` → Network issue
3. If code is `429` → Rate limited
4. If code is `401` → Invalid API key

**Fix:**
- Ensure internet connection is stable
- Check API key in `send_registration_otp.php`: `sk-e481790680e0f0783c3cc8af`
- Wait a few seconds between requests (rate limit is 1/10s)

---

### Issue 3: "No pending verification" when verifying OTP

**Cause:** Session not carrying over between requests

**Debug Steps:**
1. Check session configuration in `php.ini`
2. Verify cookies are enabled in browser
3. Check server logs show session data

**Fix:**
1. In browser, go to DevTools → Application → Cookies
2. Look for `PHPSESSID` cookie
3. Should exist and be persistent
4. If missing, session isn't working properly

---

### Issue 4: "Phone number already registered"

**Cause:** Phone was used in previous test

**Fix:**
1. Use a different phone number: `09987654321`
2. Or delete old test user from database:
```sql
-- In Supabase SQL Editor:
DELETE FROM youth_profiles WHERE phone = '09123456789';
DELETE FROM users WHERE email = 'test@example.com';
```

---

## Check Server Logs

To see detailed error messages:

1. **Open error log in real-time:**
   ```powershell
   # Open PowerShell as admin
   Get-Content -Path "c:\xampp\apache\logs\error.log" -Wait
   ```
   This will show new errors as they happen.

2. **Search for specific errors:**
   ```powershell
   Select-String -Path "c:\xampp\apache\logs\error.log" -Pattern "registration"
   ```

---

## Database Verification

Check if migration was applied:

```sql
-- In Supabase SQL Editor:
SELECT * FROM information_schema.columns 
WHERE table_name = 'users' 
AND column_name IN ('phone_verified', 'phone_verified_at');
```

Should return 2 rows.

---

## Quick Test Checklist

- [ ] Test API connectivity works (test_api.php shows all green)
- [ ] Verify button enables with valid 11-digit phone
- [ ] OTP request returns success in Network tab
- [ ] Server log shows OTP generated (6 digits)
- [ ] Can enter OTP and verify
- [ ] Phone shows as verified
- [ ] Rest of form can be filled
- [ ] Registration button submits without error
- [ ] User is created in database
- [ ] Can log in with phone number

---

## Report Issues With

When reporting an issue, include:

1. **Server error log output** (from `c:\xampp\apache\logs\error.log`)
2. **Network tab screenshot** (showing request/response)
3. **Browser console errors** (if any)
4. **Steps to reproduce** the issue

This will help fix the problem faster!

---

## Need More Help?

Check the **full implementation guide**:
`MOBILE_LOGIN_IMPLEMENTATION.md`
