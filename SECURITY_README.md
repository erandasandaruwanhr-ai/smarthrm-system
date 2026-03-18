# SmartHRM Security Configuration

## Overview
This document outlines the security configuration implemented for the SmartHRM system, including environment variables, Apache security rules, and best practices.

## Files Created

### 1. Environment Configuration
- **`.env`** - Main environment configuration file (DO NOT commit to version control)
- **`.env.example`** - Template for environment configuration
- **`config/env.php`** - Environment loader class

### 2. Security Files
- **`.htaccess`** - Main Apache security and performance configuration
- **`config/.htaccess`** - Blocks access to configuration directory
- **`includes/.htaccess`** - Blocks access to includes directory
- **`uploads/.htaccess`** - Secure file upload handling
- **`database/.htaccess`** - Blocks access to database directory
- **`.gitignore`** - Prevents sensitive files from being committed

### 3. Error Pages
- **`error/404.php`** - Custom 404 page
- **`error/403.php`** - Custom 403 page
- **`error/500.php`** - Custom 500 page

## Security Features Implemented

### 1. Environment Protection
- **`.env` file protection**: Blocked from web access
- **Sensitive file blocking**: Config files, version control, etc.
- **Database credentials**: Moved to environment variables

### 2. Upload Security
- **PHP execution blocked** in upload directories
- **File type restrictions**: Only specific extensions allowed
- **Script execution prevention**: Blocks .pl, .py, .sh, etc.

### 3. Apache Security Headers
- **X-Frame-Options**: Prevents clickjacking
- **X-XSS-Protection**: Browser XSS protection
- **X-Content-Type-Options**: MIME type sniffing protection
- **Content-Security-Policy**: Basic CSP implementation
- **Referrer-Policy**: Controls referrer information

### 4. Access Control
- **Directory browsing disabled**
- **Sensitive directories blocked**: config/, includes/, database/
- **Bad bot protection**: Blocks malicious crawlers
- **SQL injection protection**: Filters dangerous query patterns

### 5. Performance Optimization
- **Compression enabled**: Gzip compression for static files
- **Browser caching**: Cache headers for static assets
- **File size limits**: Upload limits configured

## Configuration Instructions

### 1. Environment Setup
1. Copy `.env.example` to `.env`
2. Update database credentials in `.env`
3. Change `BASE_URL` to match your domain
4. Update `DEFAULT_PASSWORD` for production

### 2. Production Security Checklist
- [ ] Change default passwords
- [ ] Update database credentials
- [ ] Set `APP_DEBUG=false`
- [ ] Set `APP_ENV=production`
- [ ] Enable HTTPS and update security headers
- [ ] Configure proper file permissions (644 for files, 755 for directories)
- [ ] Remove or secure error pages in production

### 3. File Permissions (Linux/Unix)
```bash
# Set proper permissions
chmod 644 .env
chmod 644 .htaccess
chmod 755 uploads/
chmod 644 config/*
chmod 600 config/database.php  # More restrictive for DB config
```

### 4. Database Security
- Create dedicated database user with minimal privileges
- Use strong passwords
- Enable SSL connection if available
- Regular security updates

## Environment Variables Reference

### Core Settings
- `APP_NAME` - Application name
- `APP_ENV` - Environment (local/production/staging)
- `APP_DEBUG` - Debug mode (true/false)
- `BASE_URL` - Application base URL

### Database
- `DB_HOST` - Database host
- `DB_PORT` - Database port
- `DB_DATABASE` - Database name
- `DB_USERNAME` - Database username
- `DB_PASSWORD` - Database password

### Security
- `SESSION_TIMEOUT` - Session timeout in seconds
- `DEFAULT_PASSWORD` - Initial system password
- `CSRF_PROTECTION` - Enable CSRF protection
- `XSS_PROTECTION` - Enable XSS protection

### File Uploads
- `MAX_FILE_SIZE` - Maximum file size in bytes
- `UPLOAD_DIR` - Upload directory path
- `ALLOWED_FILE_TYPES` - Comma-separated allowed extensions

## Security Best Practices

### 1. Regular Maintenance
- Update PHP and Apache regularly
- Review and update security rules
- Monitor error logs for suspicious activity
- Regular security audits

### 2. Access Control
- Implement proper user roles and permissions
- Use strong password policies
- Enable two-factor authentication if available
- Regular access review

### 3. Data Protection
- Regular database backups
- Encrypt sensitive data
- Secure file storage
- Data retention policies

### 4. Monitoring
- Enable access logging
- Monitor failed login attempts
- Set up security alerts
- Regular security scans

## Troubleshooting

### 1. .htaccess Issues
If .htaccess causes 500 errors:
- Check Apache configuration allows .htaccess
- Verify mod_rewrite is enabled
- Check file permissions
- Test rules incrementally

### 2. Environment Variables Not Loading
- Verify .env file exists and is readable
- Check file permissions
- Ensure env.php is included in config
- Check for syntax errors in .env

### 3. Upload Issues
- Check upload directory permissions
- Verify MAX_FILE_SIZE setting
- Check Apache upload limits
- Review .htaccess rules in uploads/

## Support
For security issues or questions:
1. Review this documentation
2. Check Apache error logs
3. Verify file permissions
4. Test with minimal .htaccess rules

## Version
- **Created**: March 2026
- **Version**: 1.0.0
- **Compatible with**: Apache 2.4+, PHP 7.4+, MySQL 5.7+