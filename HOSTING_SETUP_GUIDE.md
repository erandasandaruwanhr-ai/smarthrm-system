# SmartHRM Hosting Setup Guide

## Issues Fixed in This Package

1. **Database Import Error**: Fixed `expires_at` column MySQL compatibility issue
2. **Missing CSS/Assets**: Optimized .htaccess to allow asset loading while maintaining security
3. **Environment Configuration**: Created production-ready environment files

## Files Included

1. `fix_expires_at.sql` - Database fix for MySQL compatibility
2. `.htaccess.hosting` - Hosting-optimized .htaccess file
3. `.env.production` - Production environment configuration

## Step-by-Step Setup Instructions

### 1. Database Setup

Upload your database to phpMyAdmin and if you get the `expires_at` error:

```sql
-- Run this SQL command in your hosting phpMyAdmin:
ALTER TABLE notifications MODIFY COLUMN expires_at DATETIME NULL DEFAULT NULL;
```

Or import the `fix_expires_at.sql` file provided.

### 2. .htaccess Configuration

**Replace your current `.htaccess` with `.htaccess.hosting`:**

1. Rename current `.htaccess` to `.htaccess.backup`
2. Rename `.htaccess.hosting` to `.htaccess`

This ensures:
- CSS, JS, and image files load properly
- Basic security is maintained
- Simplified rules for shared hosting compatibility

### 3. Environment Configuration

1. Copy `.env.production` to `.env`
2. Update these critical settings:

```env
# Update these for your hosting environment
BASE_URL="https://yourdomain.com/path/to/smarthrm/"
DOMAIN="yourdomain.com"

# Update database settings from your hosting panel
DB_HOST=localhost
DB_DATABASE=your_database_name
DB_USERNAME=your_db_username
DB_PASSWORD=your_db_password

# Change default password for security
DEFAULT_PASSWORD="YourSecurePassword123!"
```

### 4. File Permissions

Set these file permissions on your hosting server:

```bash
chmod 755 /public_html/smarthrm/
chmod 644 /public_html/smarthrm/.env
chmod 644 /public_html/smarthrm/.htaccess
chmod 755 /public_html/smarthrm/uploads/
chmod 755 /public_html/smarthrm/assets/
```

### 5. Verify Setup

1. **Test Database Connection**: Visit `yourdomain.com/smarthrm/` - login page should appear
2. **Test CSS Loading**: Verify styles are loading (no plain HTML appearance)
3. **Test Login**: Use default credentials (EPF: ADMIN001, Password: from .env file)

## Common Issues & Solutions

### Issue: "Execute Error: SQLSTATE[42000]: Syntax error or access violation: 1067 Invalid default value for 'expires_at'"

**Solution**: Run the SQL fix:
```sql
ALTER TABLE notifications MODIFY COLUMN expires_at DATETIME NULL DEFAULT NULL;
```

### Issue: Missing CSS/Styles (Plain HTML appearance)

**Solutions**:
1. Use the `.htaccess.hosting` file provided
2. Verify file permissions: `chmod 755 assets/` `chmod 644 assets/css/*`
3. Check if CDN links are accessible (Bootstrap, FontAwesome)

### Issue: Database Connection Failed

**Solutions**:
1. Update `.env` file with correct database credentials
2. Verify database exists and user has proper permissions
3. Contact hosting support for database connection details

### Issue: Login Page Shows But Can't Login

**Solutions**:
1. Import the complete database schema
2. Verify default admin user exists:
```sql
SELECT * FROM employees WHERE epf_number = 'ADMIN001';
```
3. Reset password if needed:
```sql
UPDATE employees SET password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' WHERE epf_number = 'ADMIN001';
```

## Production Checklist

- [ ] Database imported successfully
- [ ] `.env` file configured with production settings
- [ ] `.htaccess.hosting` renamed to `.htaccess`
- [ ] File permissions set correctly
- [ ] Default password changed
- [ ] Login page displays with proper styling
- [ ] Admin login works
- [ ] Dashboard loads with all data and styling

## Support

If you continue to experience issues:

1. Check your hosting error logs
2. Verify PHP version compatibility (requires PHP 7.4+)
3. Ensure all required PHP extensions are enabled (mysqli, pdo_mysql)
4. Contact your hosting provider for specific configuration requirements

## Security Notes

- Change the default password immediately after first login
- Keep the `.env` file secure and never expose it publicly
- Regularly update the system and monitor for security updates