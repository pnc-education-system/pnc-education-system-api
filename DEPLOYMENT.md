# Production Deployment Guide

## Pre-Deployment Checklist

### 1. Environment Variables
Update `.env` file for production:

```bash
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_DATABASE=your_production_db
DB_USERNAME=your_production_user
DB_PASSWORD=your_secure_password

# JWT Security
JWT_SECRET=generate-new-secure-secret
JWT_TTL=60
JWT_REFRESH_TTL=10080
```

**Important:**
- Set `APP_DEBUG=false` to disable error details
- Generate a new `JWT_SECRET` using: `php artisan jwt:secret`
- Use strong database passwords
- Update `APP_URL` to your production domain

### 2. Security Measures Implemented
- ✅ Rate limiting on login (5 requests per minute)
- ✅ Rate limiting on password reset (3 requests per minute)
- ✅ JWT token expiration
- ✅ Refresh token revocation on logout
- ✅ Password hashing with bcrypt
- ✅ Role-Based Access Control (RBAC)
- ✅ Audit logging for auth events

### 3. Database Setup
```bash
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force
php artisan db:seed --class=UserSeeder --force
```

**Note:** Update seeded user passwords in production!

### 4. Cache Configuration
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 5. File Permissions
```bash
chmod -R 755 storage
chmod -R 755 bootstrap/cache
```

### 6. HTTPS Enforcement
Ensure your web server (Nginx/Apache) enforces HTTPS.

**Nginx Example:**
```nginx
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl;
    server_name your-domain.com;
    # SSL configuration...
}
```

### 7. CORS Configuration
If your frontend is on a different domain, configure CORS in `config/cors.php`.

### 8. Recommended Additional Security
- Enable firewall rules
- Use environment-specific secrets manager
- Set up database backups
- Enable monitoring and alerting
- Use a web application firewall (WAF)
- Implement IP whitelisting for admin routes if needed

## Production Users

After deployment, change the seeded user passwords:

1. **Admin:** admin@pnc.edu.kh
2. **Staff:** staff@pnc.edu.kh
3. **Management:** management@pnc.edu.kh

Use the password reset endpoint or update directly in database.

## Monitoring

Monitor:
- Login attempts (audit logs)
- Failed authentication attempts
- Token refresh patterns
- API response times

## Rollback Plan

Keep database backups before migrations:
```bash
mysqldump -u root -p pnc-education-system > backup.sql
```
