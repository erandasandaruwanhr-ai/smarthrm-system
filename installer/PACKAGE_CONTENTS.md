# SmartHRM Installation Package - Complete Contents

## 📦 Package Overview
Complete installation package for SmartHRM with web-based installation wizard, database setup, and all required data.

## 📁 Directory Structure

```
installer/
├── 📄 index.php                    # Main installation wizard
├── 📄 post_install.php            # Post-installation security
├── 📄 .htaccess                   # Installer security rules
├── 📄 README.md                   # Detailed documentation
├── 📄 INSTALLATION_GUIDE.txt      # Quick start guide
├── 📄 PACKAGE_CONTENTS.md          # This file
│
├── 📁 css/
│   └── 📄 installer.css           # Installation wizard styles
│
├── 📁 js/
│   └── 📄 installer.js            # Installation wizard logic
│
├── 📁 includes/
│   ├── 📄 requirements_check.php  # System requirements checker
│   ├── 📄 test_connection.php     # Database connection tester
│   ├── 📄 install_step.php        # Installation step processor
│   └── 📄 cleanup.php            # Post-install cleanup
│
├── 📁 sql/
│   ├── 📄 complete_schema.sql     # Complete database schema
│   └── 📄 essential_data.sql      # Core system data
│
├── 📁 config/
│   └── 📄 config.php             # Installer configuration
│
└── 📁 assets/                     # (Reserved for future assets)
```

## 🗄️ Database Tables Included

### Core System Tables
- **system_settings** - Application configuration
- **account_types** - User role definitions (user, supervisor, manager, admin, superadmin)
- **locations** - Company locations (7C, Pannala, Kobeigane, JECOE, Head Office)
- **employment_levels** - Job hierarchy (MD, GM, Manager, etc.)
- **dropdown_options** - System dropdown values

### User Management
- **employees** - Employee records and authentication
- **permissions** - System permission definitions
- **account_permissions** - User permission mappings
- **role_permissions** - Role-based permission system

### Module Support Tables
- **training_types** - Training categories
- **meal_requests** - Meal management
- **transport_requests** - Transport management
- **vehicles** - Vehicle registry
- **grievances** - Grievance management
- **anonymous_grievances** - Anonymous grievance handling
- **employee_requests** - Employee request system
- **notifications** - System notification system
- **file_uploads** - File management system

### Essential Data Included
- Default admin user (EPF: ADMIN001)
- All permission mappings for Key Talent and Skill Matrix modules
- Company locations and departments
- Employment levels and hierarchy
- Dropdown options for all modules
- System settings and configurations

## 🚀 Installation Features

### Requirements Checker
- ✅ PHP version validation (≥7.4)
- ✅ Required extension checks (MySQLi, JSON, etc.)
- ✅ Optional extension recommendations
- ✅ File permission verification
- ✅ Memory and execution time checks
- ✅ Apache mod_rewrite detection

### Database Setup
- ✅ Connection testing with retry
- ✅ Automatic database creation
- ✅ Complete schema installation
- ✅ Data population with validation
- ✅ Permission system setup

### Configuration Management
- ✅ Automatic .env file generation
- ✅ Production-ready settings
- ✅ Security configuration
- ✅ Module feature flags
- ✅ Company customization

### Security Features
- ✅ Post-installation cleanup
- ✅ Installer self-deletion
- ✅ Security headers
- ✅ File access protection
- ✅ Session management

## 📋 Installation Process

### Step 1: Requirements Check
Validates server environment and displays detailed status for:
- PHP version and extensions
- File permissions
- Memory and execution limits
- Database connectivity requirements

### Step 2: Database Configuration
- Database connection parameters
- Real-time connection testing
- Automatic database creation if needed
- Configuration preview

### Step 3: System Configuration
- Administrator account setup
- Company information
- Base URL configuration
- Security settings

### Step 4: Installation Execution
- Database schema creation
- Data population
- Permission setup
- Configuration file generation
- Progress tracking with visual feedback

### Step 5: Completion & Security
- Installation summary
- Security recommendations
- Post-installation cleanup options
- Direct access to the system

## 🔐 Default Credentials

After installation:
- **Username**: ADMIN001
- **Password**: As configured during installation
- **Role**: Super Administrator
- **Permissions**: Full system access

## 📊 Included Permissions

### Key Talent Identification Module
- User: View, Talent Grid
- Supervisor: Assessment, Results, Candidates, Reports
- Manager: Full access including Form Setup
- Admin/SuperAdmin: Complete module access

### Skill Matrix Module
- User: View, Complete assessments
- Supervisor: Team assessments and reports
- Manager: Matrix setup and full management
- Admin/SuperAdmin: Complete system access

## 🔧 Technical Specifications

### Supported Environments
- **PHP**: 7.4+ (8.0+ recommended)
- **MySQL**: 5.7+ / MariaDB 10.3+
- **Web Server**: Apache 2.4+ / Nginx 1.18+
- **Memory**: 128MB+ (256MB recommended)

### File Permissions
- Directories: 755
- Files: 644
- Writable directories: uploads/, logs/, config/

### Security Features
- SQL injection prevention
- XSS protection
- CSRF token validation
- File upload security
- Session management
- Password hashing (PHP password_hash)

## 📞 Support & Troubleshooting

### Common Solutions
1. **Permission Issues**: Set correct file permissions (755/644)
2. **Database Errors**: Verify credentials and MySQL service
3. **Memory Errors**: Increase PHP memory_limit
4. **Timeout Issues**: Increase max_execution_time

### Installation Logs
The installer creates comprehensive logs for troubleshooting:
- Environment configuration (.env)
- Installation timestamp
- Database setup confirmation

---

**Total Package Size**: ~50 files
**Installation Time**: 2-5 minutes (depending on server performance)
**Post-Installation**: Ready-to-use SmartHRM system with full functionality