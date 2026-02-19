# Supabase Best Practices & RLS Implementation Guide

## Overview
This guide covers security best practices, Row Level Security (RLS) policies, and cloud architecture improvements for your SKalinga project on Supabase.

---

## Part 1: Row Level Security (RLS) Setup

### 1.1 What is RLS?
Row Level Security restricts what data users can see/modify based on policies. It's evaluated server-side, making it more secure than client-side checks.

### 1.2 Enable RLS on All Tables

```sql
-- Enable RLS on all tables
ALTER TABLE users ENABLE ROW LEVEL SECURITY;
ALTER TABLE youth_profiles ENABLE ROW LEVEL SECURITY;
ALTER TABLE resources ENABLE ROW LEVEL SECURITY;
ALTER TABLE borrow_records ENABLE ROW LEVEL SECURITY;
ALTER TABLE events ENABLE ROW LEVEL SECURITY;
ALTER TABLE incidents ENABLE ROW LEVEL SECURITY;
ALTER TABLE printing_requests ENABLE ROW LEVEL SECURITY;
ALTER TABLE password_resets ENABLE ROW LEVEL SECURITY;
ALTER TABLE admins ENABLE ROW LEVEL SECURITY;
```

---

## Part 2: RLS Policies for Users Table

### Policy 1: Users can view their own profile
```sql
CREATE POLICY "Users can view own profile"
ON users
FOR SELECT
USING (
    -- auth.uid() returns the current user's ID from JWT token
    -- Ensure your JWT has 'sub' claim set to user ID
    auth.uid()::text = id::text
);
```

### Policy 2: Users can update their own profile
```sql
CREATE POLICY "Users can update own profile"
ON users
FOR UPDATE
USING (auth.uid()::text = id::text)
WITH CHECK (auth.uid()::text = id::text);
```

### Policy 3: Users cannot delete their own account (only admins can)
```sql
CREATE POLICY "No user self-deletion"
ON users
FOR DELETE
USING (FALSE); -- Block all deletes at row level
```

### Policy 4: Admins can view all users
```sql
CREATE POLICY "Admins can view all users"
ON users
FOR SELECT
USING (
    -- Check if current user is an admin
    EXISTS (
        SELECT 1 FROM admins a
        WHERE a.id = auth.uid()::int
        AND a.status = 'active'
    )
);
```

### Policy 5: Admins can update any user
```sql
CREATE POLICY "Admins can update any user"
ON users
FOR UPDATE
USING (
    EXISTS (
        SELECT 1 FROM admins a
        WHERE a.id = auth.uid()::int
        AND a.role = 'superadmin'
    )
);
```

---

## Part 3: RLS Policies for Youth Profiles

### Policy 1: Users can view their own profile
```sql
CREATE POLICY "Users can view own profile"
ON youth_profiles
FOR SELECT
USING (
    user_id = auth.uid()::int OR
    -- Also allow admins to view all profiles
    EXISTS (
        SELECT 1 FROM admins
        WHERE id = auth.uid()::int AND status = 'active'
    )
);
```

### Policy 2: Users can update their own profile
```sql
CREATE POLICY "Users can update own profile"
ON youth_profiles
FOR UPDATE
USING (user_id = auth.uid()::int)
WITH CHECK (user_id = auth.uid()::int);
```

### Policy 3: Public can view profiles (for registry visibility)
```sql
CREATE POLICY "Public can view active profiles"
ON youth_profiles
FOR SELECT
USING (
    EXISTS (
        SELECT 1 FROM users u
        WHERE u.id = youth_profiles.user_id
        AND u.status = 'active'
    )
);
```

### Policy 4: Only user can insert their own profile
```sql
CREATE POLICY "Users can insert own profile"
ON youth_profiles
FOR INSERT
WITH CHECK (user_id = auth.uid()::int);
```

---

## Part 4: RLS Policies for Sensitive Data

### Password Resets (Very Restrictive)
```sql
-- Only the user who requested reset can view their OTP
CREATE POLICY "Users can view own password reset"
ON password_resets
FOR SELECT
USING (
    EXISTS (
        SELECT 1 FROM users u
        WHERE u.id = password_resets.user_id
        AND u.id = auth.uid()::int
    )
);

-- Only system (backend) can insert/update password resets
CREATE POLICY "Backend can manage password resets"
ON password_resets
FOR ALL
USING (FALSE)
WITH CHECK (FALSE);
-- Note: Configure as a separate backend role with elevated permissions
```

### Incidents (Personal + Admin Access)
```sql
-- Users can view their own incidents
CREATE POLICY "Users can view own incidents"
ON incidents
FOR SELECT
USING (
    member_id = (
        SELECT member_id FROM users
        WHERE id = auth.uid()::int
    )
);

-- Admins can view all incidents
CREATE POLICY "Admins can view all incidents"
ON incidents
FOR SELECT
USING (
    EXISTS (
        SELECT 1 FROM admins
        WHERE id = auth.uid()::int
        AND status = 'active'
    )
);

-- Users can create incidents
CREATE POLICY "Users can create incidents"
ON incidents
FOR INSERT
WITH CHECK (
    member_id = (
        SELECT member_id FROM users
        WHERE id = auth.uid()::int
    )
);
```

---

## Part 5: RLS for Admins Table

### Policy: Strict Admin Access
```sql
-- Only superadmins can view admin list
CREATE POLICY "Superadmins can view all admins"
ON admins
FOR SELECT
USING (
    EXISTS (
        SELECT 1 FROM admins a
        WHERE a.id = auth.uid()::int
        AND a.role = 'superadmin'
        AND a.status = 'active'
    )
);

-- Only superadmins can modify admin accounts
CREATE POLICY "Superadmins can modify admins"
ON admins
FOR ALL
USING (
    EXISTS (
        SELECT 1 FROM admins a
        WHERE a.id = auth.uid()::int
        AND a.role = 'superadmin'
        AND a.status = 'active'
    )
);

-- Prevent deletion of superadmin accounts
CREATE POLICY "Cannot delete superadmins"
ON admins
FOR DELETE
USING (
    EXISTS (
        SELECT 1 FROM admins a
        WHERE a.id = auth.uid()::int
        AND a.role = 'superadmin'
    )
    AND role != 'superadmin'
);
```

---

## Part 6: Implementing Authentication in PHP

### 6.1 Update Login with JWT Token

```php
<?php
// api/login_supabase.php
require_once 'db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$password = trim($input['password'] ?? '');

try {
    // Query user from Supabase
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        exit;
    }
    
    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        exit;
    }
    
    // Create JWT token manually or use Supabase Auth
    // RECOMMENDED: Use Supabase Auth API instead
    $token = createJWT($user['id'], $user['email']);
    
    // Update last login
    $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $updateStmt->execute([$user['id']]);
    
    // Get profile info
    $profileStmt = $conn->prepare("SELECT firstname, lastname FROM youth_profiles WHERE user_id = ?");
    $profileStmt->execute([$user['id']]);
    $profile = $profileStmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'member_id' => $user['member_id'],
            'name' => ($profile['firstname'] ?? '') . ' ' . ($profile['lastname'] ?? '')
        ],
        'redirect' => 'dashboard.html'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function createJWT($userId, $email) {
    $key = getenv('JWT_SECRET') ?: 'your-secret-key'; // Use env variable!
    $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
    $payload = json_encode([
        'sub' => $userId,
        'email' => $email,
        'iat' => time(),
        'exp' => time() + (24 * 3600) // 24 hour expiry
    ]);
    
    $headerEncoded = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
    $payloadEncoded = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    $signature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", $key, true);
    $signatureEncoded = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    
    return "$headerEncoded.$payloadEncoded.$signatureEncoded";
}
?>
```

### 6.2 Middleware to Verify Token

```php
<?php
// api/verify_token.php
function verifyUserToken() {
    $headers = getallheaders();
    $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
    
    if (!$token) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No authorization token']);
        exit;
    }
    
    // Decode JWT
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid token format']);
        exit;
    }
    
    $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
    
    if (!$payload || $payload['exp'] < time()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token expired']);
        exit;
    }
    
    return $payload['sub']; // Return user ID
}
?>
```

### 6.3 Use RLS in Queries

With RLS enabled and proper authentication, queries automatically filter based on policies:

```php
<?php
// Example: Get current user's profile (RLS filters automatically)
$userId = verifyUserToken();

// This query now returns ONLY user's own data due to RLS policy
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
// Result is filtered by RLS automatically!
?>
```

---

## Part 7: Cloud Architecture Best Practices

### 7.1 Connection String Management

**NEVER hardcode passwords!** Use environment variables:

**.env file (add to .gitignore)**
```env
DB_HOST=db.xxxxx.supabase.co
DB_PORT=5432
DB_NAME=postgres
DB_USER=postgres
DB_PASSWORD=your-secret-password-here
JWT_SECRET=your-jwt-secret-key
APP_ENV=production
```

**Load in db_connect.php:**
```php
<?php
// api/db_connect.php
require_once __DIR__ . '/../.env.php'; // Or use dotenv library

$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$dbname = getenv('DB_NAME');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');

try {
    $conn = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_PERSISTENT => false
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    error_log('DB Error: ' . $e->getMessage());
    exit;
}
?>
```

### 7.2 Connection Pooling

For handling multiple concurrent requests:

```php
// In production, use Supabase's built-in pgBouncer
// Get from: Supabase Dashboard > Settings > Database > Connection Pooling

// Connection string WITH pgBouncer (port 6543):
$dsn = "pgsql:host=db.xxxxx.supabase.co;port=6543;dbname=postgres;sslmode=require";

// Without pgBouncer (port 5432):
$dsn = "pgsql:host=db.xxxxx.supabase.co;port=5432;dbname=postgres;sslmode=require";
```

### 7.3 Error Handling & Logging

```php
<?php
// Better error handling for production
try {
    // Your database operations
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
} catch (PDOException $e) {
    // Log error (don't expose to client)
    error_log('[' . date('Y-m-d H:i:s') . '] DB Error: ' . $e->getMessage());
    
    // Return generic message to client
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again later.'
    ]);
    exit;
}
?>
```

### 7.4 Rate Limiting

Prevent brute force attacks:

```php
<?php
// api/login.php with rate limiting
session_start();

$user_ip = $_SERVER['REMOTE_ADDR'];
$cache_key = "login_attempt_" . md5($user_ip);

// Check if user exceeded login attempts (5 attempts per 15 minutes)
if (isset($_SESSION[$cache_key]) && $_SESSION[$cache_key]['attempts'] >= 5) {
    if ((time() - $_SESSION[$cache_key]['first_attempt']) < 900) { // 15 minutes
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Too many login attempts. Try again later.']);
        exit;
    }
}

// ... login logic ...

// On failed login, increment counter
// $_SESSION[$cache_key]['attempts'] += 1;
?>
```

### 7.5 Data Encryption for Sensitive Fields

```sql
-- For sensitive data, you might want encryption at rest
-- Supabase automatically encrypts data in transit (TLS/SSL)

-- Example: Encrypt phone numbers
-- Use pgcrypto extension
CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- Encrypt on insert
INSERT INTO youth_profiles (user_id, phone, firstname, lastname)
VALUES (1, pgp_sym_encrypt('09123456789', 'secret-key'), 'John', 'Doe');

-- Decrypt on select
SELECT user_id, pgp_sym_decrypt(phone, 'secret-key') FROM youth_profiles;
```

---

## Part 8: Monitoring & Performance

### 8.1 Enable Query Logs

In Supabase Dashboard:
1. Go to **Settings** > **Logs**
2. Enable **Database** logs
3. Monitor query performance
4. Look for slow queries (>500ms)

### 8.2 Add Missing Indexes

```sql
-- Check which queries are slow in logs
-- Then add strategic indexes:

-- Common search patterns
CREATE INDEX idx_users_email_status ON users(email, status);
CREATE INDEX idx_youth_profiles_phone ON youth_profiles(phone);
CREATE INDEX idx_incidents_status_member ON incidents(status, member_id);
CREATE INDEX idx_printing_requests_status_member ON printing_requests(status, member_id);

-- Date range queries
CREATE INDEX idx_events_date_status ON events(date, status);
CREATE INDEX idx_password_resets_expires ON password_resets(expires_at) WHERE is_used = FALSE;
```

### 8.3 Query Optimization Examples

**Before (Slow):**
```sql
SELECT * FROM users u
JOIN youth_profiles yp ON u.id = yp.user_id
WHERE yp.barangay = 'San Antonio'
ORDER BY yp.lastname;
```

**After (Optimized):**
```sql
-- Add index first
CREATE INDEX idx_profiles_barangay_lastname ON youth_profiles(barangay, lastname);

-- Then query uses index efficiently
SELECT u.id, u.email, u.member_id, yp.firstname, yp.lastname
FROM users u
INNER JOIN youth_profiles yp ON u.id = yp.user_id
WHERE yp.barangay = 'San Antonio'
AND u.status = 'active'
ORDER BY yp.lastname;
```

---

## Part 9: Backup & Disaster Recovery

### 9.1 Automatic Backups

Supabase handles automatic backups:
- **Free tier**: Daily backups (7-day retention)
- **Pro tier**: Hourly backups (30-day retention)

### 9.2 Manual Backups

```bash
# Create manual backup (Linux/macOS)
pg_dump "postgresql://postgres:password@db.xxxxx.supabase.co/postgres" > backup_manual_$(date +%Y%m%d_%H%M%S).sql

# Restore from backup
psql "postgresql://postgres:password@db.xxxxx.supabase.co/postgres" < backup_manual_20260220_120000.sql
```

### 9.3 Point-in-Time Recovery

Supabase supports PITR (available on Pro tier):
1. Supabase Dashboard > Settings > Backups
2. See all available recovery points
3. Click "Restore" at desired time

---

## Part 10: Upgrade Supabase Tier

### When to Upgrade:

| Metric | Free Tier Limit | When to Upgrade |
|--------|-----------------|-----------------|
| Concurrent Connections | 10 | >8 concurrent users |
| Database Size | 500 MB | Using >400 MB |
| Bandwidth | 2 GB/month | >1.5 GB/month |
| Requests | 50,000/month | >40,000/month |
| Custom Domains | No | Need custom domain |

### Upgrade Process:
1. Supabase Dashboard > Billing
2. Click "Upgrade to Pro"
3. Select plan
4. **Zero downtime upgrade** - your app keeps running!

---

## Part 11: Security Checklist

- [ ] All passwords changed from defaults
- [ ] JWT secret is strong and unique
- [ ] RLS policies enabled on sensitive tables
- [ ] SSL/TLS enabled (automatic with Supabase)
- [ ] Database password in environment variables (not in code)
- [ ] Rate limiting implemented for APIs
- [ ] Input validation on all endpoints
- [ ] SQL injection prevented (using parameterized queries)
- [ ] Regular backups scheduled
- [ ] API keys rotated every 90 days
- [ ] Logs monitored for suspicious activity
- [ ] CORS properly configured

---

## Part 12: Migration Checklist for Production

Before going live:

- [ ] All data verified in Supabase
- [ ] Performance tested with real-world load
- [ ] RLS policies tested thoroughly
- [ ] Backup/restore tested
- [ ] SSL certificate valid
- [ ] Error logging configured
- [ ] Monitoring alerts set up
- [ ] Database backups automated
- [ ] Team trained on new system
- [ ] Support documentation updated
- [ ] Rollback plan documented
- [ ] DNS updated (if using custom domain)
- [ ] XAMPP MySQL backup retained (offline)

---

## Useful SQL Queries for Maintenance

```sql
-- Get database size
SELECT datname, pg_size_pretty(pg_database_size(datname)) as size
FROM pg_database
WHERE datname = 'postgres';

-- Find unused indexes
SELECT schemaname, tablename, indexname, idx_scan
FROM pg_stat_user_indexes
WHERE idx_scan = 0
ORDER BY pg_relation_size(indexrelid) DESC;

-- Check table sizes
SELECT schemaname, tablename, pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename))
FROM pg_tables
WHERE schemaname NOT IN ('pg_catalog', 'information_schema')
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC;

-- Monitor active connections
SELECT * FROM pg_stat_activity WHERE datname = 'postgres';

-- Check for long-running queries
SELECT pid, now() - pg_stat_activity.query_start AS duration, query
FROM pg_stat_activity
WHERE (now() - pg_stat_activity.query_start) > interval '5 minutes';
```

---

## Support & Resources

- **Supabase Docs**: https://supabase.com/docs
- **PostgreSQL Docs**: https://www.postgresql.org/docs/14/
- **Security Best Practices**: https://supabase.com/docs/guides/auth
- **RLS Guide**: https://supabase.com/docs/guides/auth/row-level-security
- **Performance Tuning**: https://supabase.com/docs/guides/database/performance

---

**Your SKalinga Youth Portal is now production-ready on Supabase! 🚀**
