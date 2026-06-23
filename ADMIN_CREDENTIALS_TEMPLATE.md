# Admin Credentials Template

> **SECURITY NOTICE**
>
> Do NOT commit real passwords to this file or any other file in the repository.
>
> Passwords are stored as bcrypt hashes in the `admins` table of the `rotary` database.
>
> Share actual credentials with authorized admins through a secure channel (not in code/docs).

---

## Admin Roles & Access Levels

The admin dashboard supports the following roles:

| Role | Access Level |
|------|-------------|
| **Super Admin** | Full access including Admin Accounts, Rotary Years, Leadership Management, Website Content Management |
| **President** | Full CRUD access to all content modules (Events, Projects, Members, Donations, Collaborations, Gallery, Reports) |
| **Secretary** | Full CRUD access to all content modules |
| **Treasurer** | Full CRUD access to all content modules |
| **IT Admin** | Full CRUD access to all content modules |

**Note:** President, Secretary, Treasurer, and IT Admin have identical feature access but different role labels for organizational purposes.

---

## Default Super Admin (created by db_migration.php)

The Super Admin credentials are configured via environment variables:

| Parameter | Description |
|-----------|-------------|
| `SUPER_ADMIN_EMAIL` | Email address for the Super Admin (default: `admin@example.com`) |
| `SUPER_ADMIN_PASSWORD` | Password for the Super Admin (if empty, a random password is generated and displayed) |

**Change the password immediately after first login.**

---

## Adding a New Admin

Only Super Admin can create new admin accounts (via Admin Dashboard -> Admin Accounts).

1. **Log in** as Super Admin
2. Go to **Admin Accounts** in the sidebar
3. Fill in: Name, Email, Role (President/Secretary/Treasurer/IT Admin), Password (min 8 chars), Phone
4. Click **Create Admin Account**
5. Share credentials with the new admin through a secure channel

---

## Resetting an Admin Password

**From the admin dashboard (Super Admin only):**
1. Log in as Super Admin
2. Go to **Admin Accounts**
3. Click **Reset Pwd** for the target admin
4. Enter the new password (min 8 characters)
5. Share the new password securely

**From login page (self-service):**
1. Go to `forgot_password.php`
2. Enter your admin email
3. Check your email for the reset link (expires in 30 minutes)
4. Click the link and set a new password

---

## Changing Your Own Password

1. Log in to the dashboard
2. Go to **Settings** (sidebar)
3. Enter current password, new password, and confirm
4. Click **Change Password**

---

## Admin Roles in Database

```sql
-- admins table structure
-- role can be: super_admin, President, Secretary, Treasurer, IT Admin
-- status can be: active, inactive

-- Insert a new admin (generate hash first)
INSERT INTO admins (name, email, password, role, phone, photo_url, created_at)
VALUES ('Full Name', 'email@example.com', '$2y$10$...bcrypt-hash...', 'President', '+91 9XXXXXXXX', '', NOW());

-- Reset password
UPDATE admins SET password = '$2y$10$...new-hash...' WHERE email = 'admin@example.com';

-- Toggle admin status
UPDATE admins SET status = 'inactive' WHERE admin_id = 2;
```

---

## Password Storage Details

- **Algorithm:** `password_hash()` with `PASSWORD_BCRYPT` (cost factor: 10 by default)
- **Verification:** `password_verify()` in `login_action.php` and `login.php`
- **Rate limiting:** 10 failed attempts before temporary lockout (session-based, separate counters for form and AJAX login)
- **Password reset:** Token-based with 30-minute expiry, single-use tokens stored in `password_reset_tokens` table

---

## Security Recommendations

1. **Use strong passwords:** Minimum 8 characters with mixed case, numbers, and symbols
2. **Rotate passwords periodically:** Every 90 days recommended
3. **Enable HTTPS:** Always use SSL/TLS for admin login pages
4. **Monitor login attempts:** Check for unusual activity patterns
5. **Remove unused admin accounts:** Set status to `inactive` when someone leaves the role
6. **Change default credentials:** Immediately change the default Super Admin password
7. **Protect `.env` file:** Ensure it's not web-accessible; contains database and SMTP credentials
