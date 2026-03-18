# SmartHRM Installation Package

This is the complete installation package for the SmartHRM system. It provides a web-based installation wizard that will set up your SmartHRM system with all required database tables, data, and configurations.

## 📋 What's Included

### Database Tables
- `account_permissions` - User permission mappings
- `account_types` - User role definitions
- `dropdown_options` - System dropdown values
- `employees` - Employee records
- `employment_levels` - Job level hierarchy
- `locations` - Company location data
- `permissions` - System permissions
- `role_permissions` - Role-based permissions
- `system_settings` - Application settings
- `training_types` - Training category definitions
- Plus all other required tables for full functionality

### Installation Features
- ✅ System requirements checking
- ✅ Database connection testing
- ✅ Automated database creation
- ✅ Complete schema installation
- ✅ Core data population
- ✅ Administrator account setup
- ✅ Environment configuration
- ✅ Security optimization

## 🚀 Installation Instructions

### Step 1: Upload Files
1. Upload the entire `installer` folder to your web server
2. Ensure the parent directory (where SmartHRM will be installed) is writable

### Step 2: Run Installation
1. Open your browser and navigate to: `http://yourdomain.com/path/to/installer/`
2. Follow the installation wizard:
   - **Requirements Check**: Verifies server compatibility
   - **Database Setup**: Configure database connection
   - **Admin Account**: Set up administrator credentials
   - **Installation**: Automated setup process
   - **Completion**: Final setup and access information

### Step 3: Post-Installation
1. Delete the `installer` directory for security
2. Login with the admin credentials you created
3. Configure additional settings as needed

## 📋 System Requirements

### Required
- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher
- **Extensions**: MySQLi, JSON, Session support
- **Permissions**: Write access to installation directory

### Recommended
- **PHP**: 8.0 or higher
- **Extensions**: PDO MySQL, cURL, GD, OpenSSL
- **Memory**: 128MB or higher
- **Apache**: mod_rewrite enabled

## 🔧 Manual Installation (Alternative)

If you prefer manual installation:

### 1. Database Setup
```sql
-- Import the database schema
SOURCE installer/sql/complete_schema.sql;

-- Import the core data
SOURCE installer/sql/essential_data.sql;
```

### 2. Configuration
1. Copy `installer/config/.env.example` to `.env`
2. Update database credentials and settings
3. Set appropriate file permissions

### 3. File Structure
Ensure these directories exist and are writable:
- `uploads/`
- `logs/`
- `config/`

## 🔐 Default Credentials

After installation:
- **Username**: ADMIN001
- **Password**: As configured during installation

## 📁 File Structure

```
installer/
├── assets/           # Installation assets
├── css/             # Installer stylesheets
├── js/              # Installer JavaScript
├── includes/        # PHP backend scripts
├── sql/             # Database files
├── config/          # Configuration files
├── index.php        # Main installation page
└── README.md        # This file
```

## 🛠️ Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Verify database credentials
   - Ensure MySQL server is running
   - Check firewall settings

2. **Permission Errors**
   - Set directory permissions to 755
   - Set file permissions to 644
   - Ensure web server can write to directories

3. **Requirements Not Met**
   - Update PHP version
   - Install missing extensions
   - Contact hosting provider for assistance

4. **Installation Hangs**
   - Increase PHP memory limit
   - Increase max execution time
   - Check server error logs

### Error Logs
Check these locations for error information:
- PHP error logs
- Web server error logs
- Installation error messages

## 🔒 Security Notes

### Post-Installation Security
1. **Delete installer directory** immediately after installation
2. Change default admin password
3. Review file permissions
4. Configure SSL/HTTPS
5. Set up regular backups

### Production Settings
- Disable debug mode
- Enable error logging
- Configure proper file permissions
- Use strong passwords
- Regular security updates

## 📞 Support

For installation support:
1. Check system requirements
2. Review error logs
3. Contact technical support with specific error messages

## 📝 Installation Log

The installer will create these files:
- `.env` - Environment configuration
- `.htaccess.production` - Production web server config
- Installation logs in appropriate directories

---

**Important**: Always backup your data before installation and keep the installer package for future reference or re-installation needs.