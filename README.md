# SKalinga Youth Registration System - Database Setup

## Prerequisites
- XAMPP installed and running
- Apache and MySQL services started in XAMPP Control Panel

## Database Setup Instructions

### Step 1: Start XAMPP
1. Open XAMPP Control Panel
2. Start Apache and MySQL services

### Step 2: Create Database
1. Open your browser and go to: `http://localhost/phpmyadmin`
2. Click on "New" in the left sidebar
3. Database name: `skalinga_youth`
4. Collation: `utf8_general_ci`
5. Click "Create"

### Step 3: Create Tables
Alternatively, you can run the setup script:
1. Open your browser and go to: `http://localhost/SKalinga/api/setup_database.php`
2. This will automatically create the database and tables

### Manual Table Creation (if needed)

#### youth_registrations table:
```sql
CREATE TABLE youth_registrations (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    member_id VARCHAR(20) UNIQUE NOT NULL,
    firstname VARCHAR(50) NOT NULL,
    lastname VARCHAR(50) NOT NULL,
    birthday DATE NOT NULL,
    age INT(3) NOT NULL,
    gender ENUM('Male', 'Female', 'Other', 'Prefer not to say') NOT NULL,
    contact VARCHAR(15) NOT NULL,
    address TEXT NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive') DEFAULT 'active',
    barangay VARCHAR(100) DEFAULT 'San Antonio'
);
```

#### print_requests table:
```sql
CREATE TABLE print_requests (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    member_id VARCHAR(20) NOT NULL,
    request_type ENUM('Print', 'Xerox') NOT NULL,
    pages INT(11) NOT NULL,
    print_type ENUM('Black & White', 'Colored') NOT NULL,
    purpose VARCHAR(100) NOT NULL,
    documents TEXT,
    status ENUM('Pending', 'Finished') DEFAULT 'Pending',
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES youth_registrations(member_id) ON DELETE CASCADE
);
```

## File Structure
```
SKalinga/
├── api/
│   ├── db_connect.php          # Database connection
│   ├── register.php            # Registration handler
│   ├── login.php               # Login handler
│   └── setup_database.php      # Database setup script
├── assets/
│   ├── css/
│   └── images/
├── youth-register.html         # Registration form
├── index.html                  # Login page
├── youth-portal.html           # Youth dashboard
└── README.md                   # This file
```

## Testing the System
1. Place the entire `SKalinga` folder in your XAMPP `htdocs` directory
2. Open browser and go to: `http://localhost/SKalinga/youth-register.html`
3. Fill out the registration form and submit
4. Check phpMyAdmin to verify data was saved
5. Go to: `http://localhost/SKalinga/index.html`
6. Login with the registered email and password
7. Verify successful login redirects to youth portal

## Features Implemented
- ✅ User registration with validation
- ✅ Password hashing and security
- ✅ Unique member ID generation
- ✅ Email uniqueness validation
- ✅ User login system
- ✅ Session management
- ✅ AJAX form submissions
- ✅ Database relationships for future features

## Database Configuration
- **Host**: localhost
- **Username**: root
- **Password**: (empty)
- **Database**: skalinga_youth

## Security Notes
- Passwords are hashed using PHP's `password_hash()` function
- Member IDs are auto-generated and unique
- Email addresses must be unique
- All inputs are validated server-side

## Troubleshooting
- Make sure XAMPP is running
- Check that the `api` folder has proper permissions
- Verify database credentials in `db_connect.php`
- Check PHP error logs if registration fails