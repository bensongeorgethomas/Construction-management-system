# Security Policy

## Reporting a Vulnerability

If you discover a security vulnerability in this project, please report it responsibly:

1. **DO NOT** open a public GitHub issue
2. Email the maintainer directly (contact info in README)
3. Provide detailed information about the vulnerability
4. Allow reasonable time for a fix before public disclosure

## Known Security Considerations

### Before Deployment

**CRITICAL:** This application requires proper configuration before deployment:

1. **Environment Variables**
   - Copy `.env.example` to `.env`
   - Fill in all required credentials
   - NEVER commit `.env` to version control
   - Use strong, unique passwords

2. **Database Security**
   - Change default database credentials
   - Use a strong database password
   - Limit database user permissions
   - Enable SSL for database connections in production

3. **Session Security**
   - Set `SESSION_SECURE=true` in production (requires HTTPS)
   - Configure appropriate session lifetime
   - Use HTTPS in production

4. **File Permissions**
   - Set appropriate directory permissions (755 for directories, 644 for files)
   - Ensure `uploads/` directory is NOT web-executable
   - `.htaccess` file prevents PHP execution in uploads directory

### Security Features Implemented

✅ **Input Validation**
- File upload validation with MIME type checking
- SQL injection protection via prepared statements
- XSS protection via output escaping

✅ **Authentication & Sessions**
- Password hashing with PHP's `password_hash()`
- Secure session configuration (HttpOnly, SameSite)
- Session timeout configuration

✅ **CSRF Protection**
- Token-based CSRF protection available in `includes/csrf.php`
- Must be implemented in all state-changing forms

✅ **File Upload Security**
- Whitelist of allowed file types
- MIME type validation
- File size limits
- Sanitized filenames
- PHP execution blocked in uploads directory

### Known Limitations

⚠️ **Areas for Improvement**

1. **Rate Limiting**
   - No built-in rate limiting for login attempts
   - Recommend implementing after 5 failed attempts

2. **Email Security**
   - OTP codes sent via email (SMS would be more secure)
   - Email verification required but can be spoofed

3. **Password Policy**
   - Basic password requirements only
   - Consider enforcing stronger password policies

4. **Logging**
   - Limited security event logging
   - Recommend implementing comprehensive audit logs

5. **HTTPS**
   - Application works over HTTP for development
   - **MUST use HTTPS in production**

### Production Deployment Checklist

Before deploying to production:

- [ ] Configure `.env` with production credentials
- [ ] Set `SESSION_SECURE=true` in `.env`
- [ ] Enable HTTPS/SSL certificate
- [ ] Set strong database password
- [ ] Restrict database user permissions
- [ ] Set proper file permissions (755/644)
- [ ] Disable error display (`display_errors=Off`)
- [ ] Enable error logging
- [ ] Update `conn.php.example` if needed
- [ ] Remove any test/development files
- [ ] Review all user input validation
- [ ] Test CSRF protection on all forms
- [ ] Verify file upload restrictions
- [ ] Set up regular database backups
- [ ] Configure firewall rules

### Responsible Disclosure

This project is intended for educational purposes and portfolio demonstration. While security best practices have been implemented, it should undergo a professional security audit before use in production environments handling sensitive data.

### Updates

Security updates and patches will be released as needed. Monitor the repository for security-related commits and updates.

---

**Last Updated:** February 2026
