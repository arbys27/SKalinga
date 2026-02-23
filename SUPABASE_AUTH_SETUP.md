# Supabase Auth Setup Guide - SKalinga Youth Portal

## Overview
Your login system has been migrated from PHP to **Supabase Auth**, which is cloud-based, secure, and works perfectly on mobile devices via Vercel.

---

## Step 1: Setup Supabase Auth Provider

### 1.1 Enable Email Authentication
1. Go to [Supabase Dashboard](https://app.supabase.com)
2. Select your project: `dljukwzdbkxkbngiqzmm`
3. Go to **Authentication > Providers**
4. Click on **Email**
5. Enable it and save
6. Go to **Configuration > Email Templates**
7. Customize the password reset email template if needed

### 1.2 Setup Email Redirect URL
1. In **Authentication > URL Configuration**
2. Add your Vercel deployed URL to **Redirect URLs**:
   ```
   https://s-kalinga.vercel.app/reset-password.html
   https://s-kalinga.vercel.app
   ```

---

## Step 2: Files Already Updated

✅ **index.html** - Login form now uses `supabaseClient.auth.signInWithPassword()`
✅ **reset-password.html** - New page for handling password reset via email link
✅ **js/session-checker.js** - Helper functions for protecting pages and checking authentication

---

## Step 3: Update Protected Pages

All pages that require login should:

1. **Include these scripts in the `<head>`:**
   ```html
   <!-- Supabase Client Library -->
   <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
   <!-- Session Checker Helper -->
   <script src="js/session-checker.js"></script>
   ```

2. **Add this code before any page-specific JavaScript:**
   ```javascript
   // Check authentication on page load
   window.addEventListener('load', async function() {
     const user = await checkAuthSession();
     if (!user) {
       // User is not logged in, redirected automatically
       return;
     }
     
     // Your page logic here
     console.log('User authenticated:', user.email);
   });
   ```

### Pages to Update:
- [ ] `youth-portal.html`
- [ ] `profile.html`
- [ ] `dashboard-incidents.html`
- [ ] `dashboard-events.html`
- [ ] `dashboard-health.html`
- [ ] `dashboard-printing.html`
- [ ] `dashboard-public_disclosure.html`
- [ ] `dashboard-resources.html`
- [ ] `dashboard-settings.html`
- [ ] `report.html`
- [ ] `print.html`
- [ ] `borrow.html`
- [ ] `events.html`
- [ ] `health.html`
- [ ] `public-disclosure.html`
- [ ] `youth-registry.html`

---

## Step 4: Update User Registration Page

File: `youth-register.html`

Replace the registration form handler with:

```javascript
/* Supabase Configuration */
const { createClient } = supabase;
const supabaseClient = createClient(SUPABASE_URL, SUPABASE_ANON_KEY);

/* Registration Handler */
document.getElementById('registrationForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  
  const email = document.getElementById('email').value.trim();
  const password = document.getElementById('password').value;
  const confirmPassword = document.getElementById('confirm-password').value;
  const firstName = document.getElementById('firstname').value.trim();
  const lastName = document.getElementById('lastname').value.trim();
  const birthDate = document.getElementById('birthdate').value;
  const gender = document.getElementById('gender').value;
  const phone = document.getElementById('phone').value.trim();
  const address = document.getElementById('address').value.trim();
  
  // Validation
  if (password !== confirmPassword) {
    alert('❌ Passwords do not match');
    return;
  }
  
  if (password.length < 8) {
    alert('❌ Password must be at least 8 characters');
    return;
  }

  const btn = this.querySelector('button[type="submit"]');
  btn.disabled = true;
  btn.textContent = 'Creating Account...';

  try {
    // 1. Create Supabase Auth user
    const { data: authData, error: authError } = await supabaseClient.auth.signUp({
      email: email,
      password: password,
      options: {
        emailRedirectTo: `${window.location.origin}/youth-portal.html`
      }
    });

    if (authError) {
      throw new Error(authError.message);
    }

    // 2. Create youth profile in database
    const { data: profile, error: profileError } = await supabaseClient
      .from('youth_profiles')
      .insert([{
        user_id: authData.user.id,
        firstname: firstName,
        lastname: lastName,
        birthday: birthDate,
        gender: gender,
        phone: phone,
        address: address
      }])
      .select()
      .single();

    if (profileError) {
      throw new Error(profileError.message);
    }

    // Success
    alert('✅ Account created! Please check your email to verify your account.');
    this.reset();
    window.location.href = 'index.html';

  } catch (error) {
    console.error('Registration error:', error);
    alert('❌ ' + error.message);
    btn.disabled = false;
    btn.textContent = 'Create Account';
  }
});
```

---

## Step 5: Using Session in Your Code

### Get Current User
```javascript
const user = getCurrentUser();
console.log(user.email); // User email
console.log(user.id);    // User ID
```

### Get User Profile
```javascript
const profile = await getUserProfile();
console.log(profile.firstname);
console.log(profile.lastname);
console.log(profile.phone);
```

### Update User Profile
```javascript
try {
  const updated = await updateUserProfile({
    firstname: 'New Name',
    phone: '09123456789'
  });
  console.log('Profile updated:', updated);
} catch (error) {
  console.error('Failed to update:', error);
}
```

### Logout
```javascript
async function handleLogout() {
  await logoutUser(); // Redirects to login
}
```

### Update Email or Password
```javascript
try {
  await updateUserAuth({
    email: 'newemail@example.com'  // or password: 'newpassword'
  });
  console.log('Email updated');
} catch (error) {
  console.error('Update failed:', error);
}
```

---

## Step 6: Deprecated API Files

The following PHP API files are **no longer needed** (you can delete them):
- ❌ `api/login.php` - Replaced by Supabase Auth
- ❌ `api/register.php` - Replaced by Supabase Auth
- ❌ `api/request_password_reset.php` - Replaced by Supabase Auth
- ❌ `api/reset_password.php` - Replaced by Supabase Auth
- ❌ `api/db_connect.php` - No longer needed for frontend
- ❌ `api/db.php` - Credentials no longer exposed frontend

---

## Step 7: Backend API Updates (Keep These)

These API files still work but should be updated to use Supabase instead of local database:
- ⚠️ `api/get_user_profile.php` → Use JavaScript instead
- ⚠️ `api/update_profile.php` → Use Supabase client library
- ⚠️ `api/add_incident.php` → Use Supabase client library
- etc.

**Alternative:** Keep using PHP but connect to Supabase PostgreSQL (no change needed if using `db.php` connection string)

---

## Step 8: Test the Implementation

### Test Login
1. Go to `https://s-kalinga.vercel.app`
2. Enter credentials of a registered user in Supabase
3. Should redirect to `youth-portal.html`

### Test Password Reset
1. Click "Forgot your password?"
2. Enter email
3. Check email inbox (Supabase sends reset link)
4. Click link to open `reset-password.html`
5. Create new password

### Test on Mobile
1. Access your deployed Vercel URL on a mobile device
2. Same login flow should work

---

## Environment Variables (Optional but Recommended)

Instead of hardcoding credentials in `js/session-checker.js`, you could use a `.env.local` file (Vercel):

```env
VITE_SUPABASE_URL=https://dljukwzdbkxkbngiqzmm.supabase.co
VITE_SUPABASE_ANON_KEY=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

Then reference:
```javascript
const SUPABASE_URL = import.meta.env.VITE_SUPABASE_URL;
const SUPABASE_ANON_KEY = import.meta.env.VITE_SUPABASE_ANON_KEY;
```

---

## Troubleshooting

### "An error occurred. Please try again." on Login
**Solution:** Check browser console (F12 → Console tab) for actual error message

### Password Reset Email Not Received
**Solution:** 
1. Check spam folder
2. Verify email provider is enabled in Supabase
3. Check email templates are configured

### "Invalid or Expired Link" on Reset Password
**Solution:**
1. Make sure redirect URL matches your deployed domain
2. Reset link expires after 24 hours

### Page Still Shows After Logout
**Solution:** Clear browser cache and reload, or use hard refresh (Ctrl+Shift+R)

---

## Next Steps

1. ✅ Deploy to Vercel (static files, no PHP needed)
2. ✅ Test login on mobile
3. ✅ Update all protected pages with session checker
4. ✅ Update registration page with Supabase auth
5. ✅ Remove/archive old PHP auth files from production

---

## Support

For Supabase Auth documentation:
- [Supabase Auth Docs](https://supabase.com/docs/guides/auth)
- [JavaScript Client Library](https://supabase.com/docs/reference/javascript)
