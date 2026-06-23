# Deployment Guide — Rotary Club of Virar

---

## Hosting Requirements

| Requirement | Minimum Specification |
|-------------|----------------------|
| Web Server | Apache 2.4+ or Nginx 1.18+ |
| PHP | PHP 8.0 or higher |
| MySQL | MySQL 5.7+ or MariaDB 10.3+ |
| Storage | 500 MB+ (for images, uploads, and code) |
| RAM | 512 MB+ |
| SSL | Recommended (Let's Encrypt free) |

**Recommended Hosting:** Any shared hosting or VPS that supports PHP and MySQL (e.g., Hostinger, Bluehost, SiteGround, DigitalOcean).

---

## PHP Version

- **Required:** PHP 8.0 or higher
- **Extensions needed:**
  - `mysqli` — MySQL database connection
  - `mbstring` — Multibyte string support
  - `gd` or `imagick` — Image processing (for file uploads)
  - `openssl` — SMTP/email encryption
  - `json` — JSON encoding/decoding (used in AJAX)
  - `fileinfo` — File type validation (used in uploads)
- **Recommended:** PHP 8.2 or 8.3

**Verify requirements:**
```bash
php -v
php -m | grep -E "mysqli|mbstring|gd|openssl|json|fileinfo"
```

---

## MySQL Version

- **Required:** MySQL 5.7+ or MariaDB 10.3+
- **Recommended:** MySQL 8.0 or MariaDB 10.6+
- **Charset:** `utf8mb4` (for full Unicode/emojis support)
- **Collation:** `utf8mb4_unicode_ci`

---

## Environment Variables (.env)

The project uses a `.env` file for configuration instead of hardcoded values in PHP files.

**Setup:**
1. Copy `.env.example` to `.env`
2. Edit `.env` with production values

```env
# Database Configuration
DB_HOST=localhost
DB_NAME=rotary
DB_USER=your_db_user
DB_PASS=your_db_password

# SMTP Configuration (for email notifications)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your-smtp-email@gmail.com
SMTP_PASS=your-16-char-app-password
SMTP_ENCRYPTION=tls
SMTP_FROM_EMAIL=your-smtp-email@gmail.com
SMTP_FROM_NAME=Your Club Name
```

The `.env` file is loaded by `config/env.php`, which is required by `includes/db_connect.php` and `includes/email_config.php`.

**Security:** Ensure `.env` is NOT web-accessible. Add this to `.htaccess`:
```
<Files .env>
    Order allow,deny
    Deny from all
</Files>
```

---

## SMTP Requirements

For email notifications (donation updates, collaboration approvals, password resets), the site uses PHPMailer with Gmail SMTP.

**To set up Gmail App Password:**
1. Go to your Google Account -> Security -> 2-Step Verification (enable it)
2. Go to App Passwords (search in Google Account settings)
3. Select "Mail" and your device
4. Copy the 16-character password
5. Paste it as `SMTP_PASS` in `.env`

**SMTP Configuration (in `.env`):**
| Parameter | Value |
|-----------|-------|
| Host | `smtp.gmail.com` |
| Port | `587` |
| Encryption | `tls` |
| Username | `your-smtp-email@gmail.com` (set in `.env`) |
| Password | Google App Password |

---

## Database Setup

### Method 1: Migration Script (Recommended)
1. Create the `rotary` database in phpMyAdmin
2. Navigate to `https://yourdomain.com/Admin/db_migration.php`
3. The script creates all tables and seeds default data:
   - `admins` table (with Super Admin account)
   - `rotary_years`, `leadership_assignments`, `site_content`, `password_reset_tokens`
   - Default site content and current/next rotary years

### Method 2: Manual Import
1. Export the database from your development environment via phpMyAdmin
2. Create the database on production:
   ```sql
   CREATE DATABASE rotary CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. Import the SQL file via phpMyAdmin or CLI:
   ```bash
   mysql -u username -p rotary < /path/to/database-dump.sql
   ```

---

## File Permissions

Set the following directory permissions after deployment:

| Path | Permission | Purpose |
|------|------------|---------|
| `uploads/` | 755 (dirs) / 644 (files) | Uploaded event, project, member images |
| `uploads/events/` | 755 | Event images |
| `uploads/projects/` | 755 | Project images |
| `uploads/members/` | 755 | Member photos |
| `uploads/media/` | 755 | Gallery media |
| `uploads/gallery/` | 755 | Gallery uploads |
| `uploads/donations/` | 755 | Donation screenshots |
| `uploads/site_content/` | 755 | Site content images |
| `logs/` | 755 | Email error logs (must be writable) |

**Command examples (Linux):**
```bash
find /path/to/project/uploads -type d -exec chmod 755 {} \;
find /path/to/project/uploads -type f -exec chmod 644 {} \;
chmod 755 /path/to/project/logs
```

**On shared hosting:** Use the hosting control panel's File Manager or FTP client to set permissions.

---

## Super Admin Access

After running the migration script, a Super Admin account is created with credentials configured via environment variables:

- Set `SUPER_ADMIN_EMAIL` and `SUPER_ADMIN_PASSWORD` in `.env`
- If `SUPER_ADMIN_PASSWORD` is not set, a random password is generated and displayed during migration

**IMPORTANT:** Change the password immediately after first login via the Settings page in the admin dashboard.

---

## Domain Setup

1. **Point domain to hosting:**
   - Update nameservers to your hosting provider's nameservers
   - Or set an A record pointing to your server's IP address

2. **Configure web server:**
   - Set document root to the project directory (e.g., `/var/www/rotary-club-virar`)
   - Ensure `index.php` is set as the default directory index

3. **Test all pages:**
   - `https://yourdomain.com/`
   - `https://yourdomain.com/aboutus.php`
   - `https://yourdomain.com/activities.php`
   - `https://yourdomain.com/login.php` (admin login)

---

## SSL Requirements

- **Strongly recommended for all production deployments**
- Required for:
  - Secure admin login (password transmission)
  - Payment/donation page security (user trust)
  - Gmail SMTP (some hosts require SSL for outbound connections)
- **Free option:** Let's Encrypt via Certbot
  ```bash
  sudo apt install certbot python3-certbot-apache
  sudo certbot --apache -d yourdomain.com -d www.yourdomain.com
  ```
- **Paid options:** Shared hosting typically includes free SSL via AutoSSL or cPanel

---

## Post-Deployment Checklist

- [ ] All pages load without PHP errors
- [ ] Database connection works (check pages that query DB)
- [ ] Admin login works at `/login.php`
- [ ] File uploads work (test in Events, Projects, Members, Gallery)
- [ ] Email sending works (trigger a donation status change or password reset)
- [ ] Image paths are correct (check event/project/member photos)
- [ ] Social media links point to correct URLs
- [ ] Google Maps embed loads on Contact page
- [ ] QR code image loads on Donate page
- [ ] Mobile menu works on small screens
- [ ] All filter buttons work on Activities page
- [ ] Event polling / RSVP works
- [ ] Donation form submits correctly
- [ ] Contact form works
- [ ] Footer links are correct
- [ ] SSL certificate is valid and HTTPS redirects work
- [ ] `.env` file is not accessible via web browser
- [ ] Password reset flow works (forgot password -> email -> reset)

---

## Backup Procedure

### Database Backup
- Use phpMyAdmin Export or mysqldump:
  ```bash
  mysqldump -u username -p rotary > rotary-backup-$(date +%Y%m%d).sql
  ```

### Files Backup
- Back up the entire project directory, especially:
  - `uploads/` (user-uploaded content)
  - `.env` (configuration — keep secure)

---

## Recovery Procedure

1. Restore the database from the latest SQL backup
2. Restore project files from file backup
3. Re-create `.env` with proper credentials
4. Run `Admin/db_migration.php` to ensure all tables exist
5. Test all functionality

---

## Troubleshooting

| Issue | Likely Cause | Solution |
|-------|-------------|----------|
| "Connection failed" | Wrong DB credentials | Check `.env` file values |
| White screen / no output | PHP error | Enable `display_errors` in `php.ini` temporarily |
| Images not loading | Wrong upload path | Check file permissions and URL paths |
| Email not sending | SMTP password placeholder | Set real Gmail App Password in `.env` |
| Admin login fails | Wrong password or DB issue | Verify admin exists in `admins` table and status is `active` |
| 404 on admin pages | Wrong base URL | Check that `.php` files exist in the `Admin/` directory |
| "Access denied" | Insufficient role | Only Super Admin can access admin management, rotary years, leadership, and site content |
| Password reset email not received | SMTP not configured | Check SMTP settings in `.env` and email logs in `logs/email_log.txt` |
