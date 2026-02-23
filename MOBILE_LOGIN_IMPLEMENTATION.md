# SKalinga Youth Portal - Mobile Login & SMS OTP Implementation

## Overview

This implementation converts the SKalinga Youth Portal authentication system from email-based login to **mobile number-based login** with **SMS OTP verification** using the **SMS API Philippines** service.

### What's Changed ✨

| Feature | Before | After |
|---------|--------|-------|
| **Login Method** | Email + Password | Mobile Number + Password + OTP |
| **Registration** | Optional phone verification | Required phone verification with OTP |
| **SMS Provider** | None | SMS API Philippines (Free) |
| **API Key** | N/A | `sk-e481790680e0f0783c3cc8af` |

---

## System Architecture

### Authentication Flow

#### **Login Flow:**
```
1. User enters mobile number (11 digits) + password
   ↓
2. System validates credentials with database
   ↓
3. SMS OTP sent to mobile number via SMS API
   ↓
4. User enters 6-digit OTP
   ↓
5. OTP verified, session created, redirect to portal
```

#### **Registration Flow:**
```
1. User fills registration form
   ↓
2. User enters mobile number and clicks "Verify"
   ↓
3. SMS OTP sent to mobile number
   ↓
4. User enters OTP to verify phone
   ↓
5. Registration form submitted with phone_verified flag
   ↓
6. Account created, user redirected to login
```

---

## Files Created/Modified

### New API Endpoints Created

#### 1. **`api/send_login_otp.php`** ⭐
Sends OTP to user's phone during login
- **Method:** POST
- **Parameters:** 
  - `phone`: 11-digit Philippine mobile number (e.g., `09123456789`)
  - `password`: User's password
- **Response:** OTP sent message and session data stored
- **SMS Format:** `"Your SKalinga login code is: 123456. Valid for 10 minutes."`

#### 2. **`api/verify_login_otp.php`** ⭐
Verifies OTP and completes login authentication
- **Method:** POST
- **Parameters:**
  - `otp`: 6-digit OTP code
- **Response:** Session created, redirect URL provided
- **Session Variables Set:**
  - `user_id`
  - `member_id`
  - `phone`
  - `authenticated`

#### 3. **`api/send_registration_otp.php`** ⭐
Sends OTP to new user's phone during registration
- **Method:** POST
- **Parameters:**
  - `phone`: 11-digit Philippine mobile number
- **Validation:** Checks if phone not already registered
- **Response:** OTP generation success/failure

#### 4. **`api/verify_registration_otp.php`** ⭐
Verifies OTP during user registration
- **Method:** POST
- **Parameters:**
  - `otp`: 6-digit OTP code
- **Session Variables Set:**
  - `phone_verified`: true/1
  - `verified_phone`: The verified phone number
- **OTP Expiration:** 10 minutes
- **Attempts:** Unlimited (no lockout)

### Modified Files

#### 1. **`index.html`** (Login Page)
**Changes:**
- Email field → Mobile number field (`09123456789` format)
- Added OTP verification step with 6 individual input fields
- Two-step login flow: Phone+Password → OTP Verification
- Mobile number validation: 11-digit pattern matching
- OTP input with auto-advance on digit entry
- Paste support for OTP codes

**New Elements:**
```html
<!-- Step 1: Phone & Password -->
<input type="tel" id="login-phone" name="phone" placeholder="09123456789" pattern="[0-9]{11}">

<!-- Step 2: OTP Input -->
<div class="otp-input-group">
  <input type="text" class="otp-input-field" id="login-otp-1" maxlength="1">
  <!-- ... otp-2 through otp-6 ... -->
</div>
```

**JavaScript Changes:**
- New `setupLoginOtpInputs()` function for OTP input handling
- Mobile number validation regex: `/[0-9]{11}/`
- Two-step form display toggle
- Back button to return to phone/password entry

#### 2. **`youth-register.html`** (Registration Page)
**Changes:**
- Updated OTP endpoint calls from `send_sms_otp.php` → `send_registration_otp.php`
- Updated OTP verification endpoint from `verify_sms_otp.php` → `verify_registration_otp.php`
- Session-based phone verification instead of POST-based
- Phone already has verification flow, kept as-is
- Verify button only enabled with valid 11-digit phone number
- OTP sent/verified messages with visual feedback

**Key Features:**
- Verify button disabled until valid phone entered
- 60-second cooldown timer between OTP requests
- Auto-focus on OTP input field
- Success/error messages with visual indicators
- Phone field disabled after successful verification

#### 3. **`api/register.php`** (Registration Handler)
**Changes:**
- Added session start for phone verification checking
- Support both POST field and SESSION variable for phone verification
- Check against `$_SESSION['phone_verified']` in addition to POST data
- Added phone duplicate check before insertion
- Clear OTP session data after successful registration
- Error handling for phone already registered

**Updated Validation:**
```php
$phone_verified = (int)($_POST['phone-verified'] ?? ($_SESSION['phone_verified'] ? 1 : 0));
if (!$phone_verified) $errors[] = "Phone number must be verified via OTP";
```

#### 4. **`api/login.php`** (Login Handler)
**Changes:**
- Support both email (legacy) and phone number login
- Auto-detect login field type (email or phone)
- If phone login: query `youth_profiles.phone` field
- If email login: query `users.email` field (backward compatible)
- Updated error messages to be generic (don't reveal which field failed)

**New SQL Query for Phone Login:**
```php
SELECT u.id, u.member_id, u.password_hash, u.status, 
       u.email, p.firstname, p.lastname
FROM users u
LEFT JOIN youth_profiles p ON u.id = p.user_id
WHERE p.phone = ?
```

---

## SMS API Configuration

### SMS API Philippines Integration

**API Endpoint:** `https://sms-api-ph-gceo.onrender.com/send/sms`

**Authentication:**
- **API Key:** `sk-e481790680e0f0783c3cc8af`
- **Header:** `x-api-key: sk-e481790680e0f0783c3cc8af`
- **Content-Type:** `application/json`

**Request Format:**
```json
{
  "recipient": "+639123456789",
  "message": "Your SKalinga verification code is: 123456. Valid for 10 minutes."
}
```

**Response Format:**
```json
{
  "success": true,
  "message": "SMS sent successfully",
  "recipient": "+639123456789",
  "sms_response": {
    "state": "Pending",
    "isHashed": false,
    "isEncrypted": false
  }
}
```

**Features:**
- **Rate Limits:** 1 SMS per 10 seconds
- **Fallback:** SMS → Email → Push Notification
- **Recipient Format:** Must be `+63XXXXXXXXXXX` format
- **Cost:** FREE forever
- **Region:** Philippine numbers only

**Phone Number Conversion:**
```php
// Input: 09123456789
// Convert to: +639123456789
$formatted_phone = '+63' . substr($phone, 1);
```

---

## Database Changes Required

### Add Phone Verification Fields to Users Table

Run this SQL migration:
```sql
ALTER TABLE `users` 
ADD COLUMN `phone_verified` TINYINT(1) DEFAULT 0 COMMENT 'Phone verification status' 
AFTER `email_verified_at`;

ALTER TABLE `users`
ADD COLUMN `phone_verified_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Phone verification timestamp'
AFTER `phone_verified`;

CREATE INDEX idx_users_phone_verified ON `users`(`phone_verified`);
```

**File:** `database/add_phone_verification.sql`

---

## Session Management

### OTP Session Data

**During Registration:**
```php
$_SESSION['registration_otp']      // 6-digit OTP
$_SESSION['registration_phone']    // Phone number being verified
$_SESSION['otp_expires_at']       // Unix timestamp, expires in 10 min
$_SESSION['phone_verified']       // Set to true after verification
$_SESSION['verified_phone']       // Verified phone stored for registration
```

**During Login:**
```php
$_SESSION['login_otp']            // 6-digit OTP
$_SESSION['login_phone']          // Phone number for login
$_SESSION['login_user_id']        // User ID for session creation
$_SESSION['login_member_id']      // Member ID
$_SESSION['login_firstname']      // User's first name
$_SESSION['login_lastname']       // User's last name
$_SESSION['otp_expires_at']       // TTL: 10 minutes
```

**After Successful Login:**
```php
$_SESSION['user_id']              // Authenticated user ID
$_SESSION['member_id']            // Member ID
$_SESSION['phone']                // Phone number
$_SESSION['authenticated']        // true
```

---

## Security Features

### OTP Security
✅ **6-digit random OTP** generated via `str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT)`
✅ **10-minute expiration** - OTPs automatically expire after 600 seconds
✅ **Session-based storage** - OTPs never stored in database
✅ **One-time verification** - OTP deleted after validation
✅ **No rate limiting on OTP entry** - User can retry as many times as needed
✅ **HTTPS enforcement** - Recommended for production

### Password Security
✅ **Bcrypt hashing** with PASSWORD_DEFAULT algorithm
✅ **Minimum 8 characters** enforced
✅ **Password verification** using `password_verify()`
✅ **No plain text storage** - Only hashes stored in database

### Phone Security
✅ **Format validation** - 11-digit Philippine numbers only
✅ **Duplicate check** - Phone can only be used once
✅ **CORS headers** for API security
✅ **POST-only endpoints** - GET requests rejected

---

## Implementation Checklist

- [x] Create `send_login_otp.php` endpoint
- [x] Create `verify_login_otp.php` endpoint
- [x] Create `send_registration_otp.php` endpoint
- [x] Create `verify_registration_otp.php` endpoint
- [x] Update `index.html` login form (email → mobile)
- [x] Update `index.html` JavaScript for OTP flow
- [x] Update `youth-register.html` API endpoints
- [x] Update `api/register.php` for session-based verification
- [x] Update `api/login.php` for mobile number support
- [x] Create database migration SQL
- [ ] **Run database migration** (ADD phone_verified fields)
- [ ] Test login with mobile number
- [ ] Test registration with phone OTP
- [ ] Verify SMS delivery via SMS API
- [ ] Test OTP expiration (10-minute timeout)
- [ ] Test error handling and validation
- [ ] Clear browser cookies before testing

---

## Testing Guide

### Test Login Flow
1. Navigate to `index.html`
2. Enter phone: `09123456789`
3. Enter password: (registered password)
4. Click "Continue"
5. Should see OTP input screen
6. Check phone or email (fallback) for OTP code
7. Enter 6-digit OTP
8. Should redirect to `youth-portal.html`

### Test Registration Flow
1. Navigate to `youth-register.html`
2. Fill all form fields
3. Enter mobile number: `09987654321`
4. Click "Verify" button
5. Check phone/email for OTP
6. Enter 6-digit OTP
7. Click verify button next to OTP
8. Complete registration
9. Should redirect to login page

### Test OTP Expiration
1. Request OTP
2. Wait 11 minutes
3. Try to enter OTP
4. Should see "OTP has expired" error

### Test Error Cases
- ❌ Invalid phone format (not 11 digits)
- ❌ Phone already registered
- ❌ Incorrect OTP (5 times in a row)
- ❌ Expired OTP
- ❌ Wrong password with correct phone
- ❌ Network error during SMS send

---

## Troubleshooting

### SMS Not Sending
**Problem:** User completes login but doesn't receive SMS
**Solutions:**
1. Check API key is correct: `sk-e481790680e0f0783c3cc8af`
2. Verify phone number format (must be 11 digits starting with 09)
3. Check SMS API Philippines service status
4. Check server error logs: `error_log()` output
5. Ensure phone number not blacklisted/blocked

### OTP Verification Failed
**Problem:** User enters correct OTP but gets "Invalid OTP" error
**Solutions:**
1. Check OTP expiration (10 minutes from request time)
2. Verify request went to `verify_registration_otp.php` not `verify_login_otp.php`
3. Clear browser cookies and try again
4. Check server session settings in `php.ini`
5. Ensure `session_start()` called before accessing `$_SESSION`

### Database Field Missing
**Problem:** "Column not found" error on phone_verified
**Solutions:**
1. Run migration: `database/add_phone_verification.sql` in phpMyAdmin
2. Verify using: `SHOW COLUMNS FROM users;`
3. Check that fields exist: `phone_verified`, `phone_verified_at`

### Login Redirect Issue
**Problem:** After OTP verification, user not redirected
**Solutions:**
1. Check browser console for JavaScript errors
2. Verify session is being created in `verify_login_otp.php`
3. Check that `verify_login_otp.php` returns proper JSON
4. Clear cookies: `document.cookie = "PHPSESSID=;`
5. Check CORS headers are correct

---

## API Response Examples

### Success: OTP Sent (Login)
```json
{
  "success": true,
  "message": "Login code sent to your phone",
  "phone": "09123456789"
}
```

### Success: OTP Verified (Login)
```json
{
  "success": true,
  "message": "Login successful!",
  "member_id": "SK-2026-1234",
  "redirect": "youth-portal.html"
}
```

### Success: Registration Complete
```json
{
  "success": true,
  "message": "Registration successful!",
  "member_id": "SK-2026-5678",
  "user_id": 15,
  "redirect": "index.html"
}
```

### Error: Invalid Phone Format
```json
{
  "success": false,
  "message": "Valid 11-digit phone number is required"
}
```

### Error: Phone Already Registered
```json
{
  "success": false,
  "message": "Phone number already registered"
}
```

### Error: Invalid OTP
```json
{
  "success": false,
  "message": "Invalid OTP. Please try again."
}
```

### Error: OTP Expired
```json
{
  "success": false,
  "message": "OTP has expired. Please request a new one."
}
```

---

## FAQ

**Q: Can I still log in with email?**  
A: Yes! The `login.php` endpoint supports both email and phone. If you send an email field, it will authenticate with email (backward compatible).

**Q: What if user loses their phone?**  
A: Implement account recovery via admin dashboard - admin can verify user identity and reset their account to allow re-registration with new phone number.

**Q: How long is OTP valid?**  
A: 10 minutes (600 seconds) from request time. After that, user must request a new OTP.

**Q: Can the same OTP be used multiple times?**  
A: No. OTP is deleted from session immediately after successful verification.

**Q: Is SMS really free?**  
A: Yes! SMS API Philippines is 100% free for Philippine developers. No credit card required.

**Q: What's the rate limit on sending SMS?**  
A: 1 SMS per 10 seconds per API key. Multiple users can send simultaneously, limit applies per key.

**Q: Can I customize OTP message?**  
A: Yes! Edit the `$sms_message` variable in `/api/send_*_otp.php` files.

**Q: What if SMS fails to send?**  
A: SMS API has 3-channel fallback (SMS → Email → Push). If all fail, user won't receive code but endpoint still returns success to allow manual retry.

---

## Next Steps

1. **Run Database Migration**
   ```sql
   -- In phpMyAdmin, open skalinga_youth database
   -- Go to SQL tab and paste contents of database/add_phone_verification.sql
   ```

2. **Test the Flow**
   - Log out completely
   - Clear cookies
   - Test registration with new phone number
   - Test login with phone + OTP

3. **Monitor SMS Delivery**
   - Check application logs for OTP requests
   - Verify users receive SMS within 1-2 seconds
   - Monitor SMS API quota usage

4. **Handle Failures Gracefully**
   - Add retry logic for SMS failures
   - Implement rate limiting per phone number
   - Add analytics for OTP success/failure rates

---

## Support

For issues with implementation:
1. Check `error_log()` in server logs
2. Enable `error_reporting(E_ALL)` temporarily
3. Test SMS API directly via cURL
4. Review session configuration in `php.ini`

For SMS API support:
- Visit: https://sms-api-ph.netlify.app/
- Contact: Support via their website

---

**Implementation Date:** February 23, 2026  
**API Key:** `sk-e481790680e0f0783c3cc8af`  
**SMS Provider:** SMS API Philippines  
**Status:** ✅ Ready for Testing
