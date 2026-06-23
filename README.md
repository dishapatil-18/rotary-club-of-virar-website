# Rotary Club of Virar — Website

## Project Overview

Official website for the Rotary Club of Virar (District 3141). The site serves as a public-facing portal showcasing the club's activities, events, projects, team, media gallery, and donation system. It includes a full admin dashboard with role-based access control, content management, donation pipeline, collaboration management, and super admin capabilities for Rotary Year management, leadership assignment, and website content editing.

## Features

### Public Pages
- **Home Page** (`index.php`) — Hero section, about preview, featured projects, upcoming events, impact stats, areas of focus, gallery
- **About Us** (`aboutus.php`) — Club mission, values, impact metrics, 7 Rotary areas of focus, founder's quote
- **Activities** (`activities.php`) — Events + Projects listing with filtering (All/Events/Projects, Upcoming/Ongoing/Completed), event detail modals with RSVP polling (Yes/No/Maybe), project detail slide-in panel, collaboration proposal submission
- **Media Gallery** (`mediaGallery.php`) — Image grid with lightbox, category filtering, event/project association
- **Team** (`team.php`) — Leadership (President, Secretary, Treasurer) and Board of Directors with detail modals, Rotary Year selector
- **Donations** (`donate.php`) — 5-step donation form: donor info, category (Monetary/School Supplies/Food/Clothes/Medical/Volunteer/Other), payment details (amount+UTR/QR or in-kind description), delivery/pickup, review & submit
- **Contact Us** (`contact.php`) — Contact form with CSRF protection, club info cards, leadership display, Google Maps embed, social links

### Admin Dashboard
- **Dashboard** (`Admin/dashboard.php`) — Stats summary (members, events, projects, donations, collaborations, donation amount), quick action cards, engagement line chart, donation type doughnut chart
- **Event Management** — CRUD for events (title, description, dates, location, category, image upload)
- **Project Management** — CRUD for projects (title, description, dates, status, collaborator, image upload)
- **Member Management** — CRUD for team members (name, role, photo, bio, display order, show_contact toggle, CSV export)
- **Donation Management** — Full pipeline: Pending Verification -> Verified -> Contacted -> Received -> Completed, email notifications at each step
- **Donor Management** — CRUD for donor records linked to donations
- **Collaboration Management** — View/Approve/Reject collaboration proposals with email notifications
- **Media Gallery** — Upload and manage gallery images (via `admin_add_media.php` and `gallery_action.php`)
- **Event Polling** — View poll results and voter lists per event
- **Reports** — Event reports, project reports, donation reports, collaboration reports

### Super Admin Features
- **Admin Account Management** (`Admin/admins_management.php`) — Create/reset passwords/change roles/toggle active status for admin accounts
- **Rotary Year Management** (`Admin/rotary_years.php`) — Add new Rotary Years, set current year, edit year names
- **Leadership Management** (`Admin/leadership_transfer.php`) — Assign President, Secretary, Treasurer per Rotary Year; view current leadership and historical teams
- **Website Content Management** (`Admin/site_content.php`) — Edit text and images for Home, About, and Contact pages

### Security Features
- CSRF token validation on all forms (`includes/csrf_helper.php`)
- Secure session management with HttpOnly, SameSite=Lax cookies (`includes/session_security.php`)
- Session timeout (30 minutes of inactivity)
- Session regeneration after login
- bcrypt password hashing (`password_hash()` / `password_verify()`)
- Rate limiting (10 failed login attempts before temporary lockout)
- Upload validation (file type, size checks via `includes/upload_helper.php`)
- Prepared statements for all SQL queries
- Environment-based configuration (`.env` file)

### Email System
- PHPMailer v6.9.3 via SMTP (Gmail)
- Automated notifications for donation status updates (Verified, Contacted, Received, Completed)
- Automated notifications for collaboration approval/rejection
- Password reset email with expiring token
- Email templates in `includes/email_templates/`

## Technology Stack

| Technology | Usage |
|------------|-------|
| **PHP 8.x** | Server-side backend (vanilla PHP, no framework) |
| **MySQL / MariaDB** | Relational database (`rotary`) |
| **HTML5** | Page structure |
| **CSS3 / Tailwind CSS** | Styling (CDN-based, no build step) |
| **JavaScript (Vanilla)** | Client-side interactivity, AJAX, filtering, modals |
| **Chart.js** | Admin dashboard charts (CDN) |
| **SweetAlert2** | Admin notification popups (CDN) |
| **Lucide Icons** | Admin dashboard icons (CDN) |
| **Font Awesome 6** | Public-facing icons (CDN) |
| **PHPMailer v6.9.3** | Email sending (local library in `includes/phpmailer/`) |

## Installation Guide

### Prerequisites
- XAMPP / WAMP / LAMP stack (PHP 8.0+ recommended)
- MySQL 5.7+ or MariaDB 10.3+
- Web server (Apache / Nginx)

### Local Setup Steps

1. **Copy project files**
   ```bash
   # Place all files in your web server root
   # Example: C:\xampp\htdocs\rotary-club-virar\
   ```

2. **Start Apache & MySQL** via XAMPP Control Panel (or equivalent)

3. **Create the database**
   - Open phpMyAdmin: `http://localhost/phpmyadmin`
   - Create a new database named `rotary`
   - Character set: `utf8mb4` / `utf8mb4_unicode_ci`

4. **Configure environment variables**
   - Copy `.env.example` to `.env`
   - Edit `.env` with your database credentials:
     ```env
     DB_HOST=localhost
     DB_NAME=rotary
     DB_USER=root
     DB_PASS=your_password
     ```

5. **Run database migration**
   - Navigate to: `http://localhost/rotary-club-virar/Admin/db_migration.php`
   - This creates all required tables: `admins`, `rotary_years`, `leadership_assignments`, `site_content`, `password_reset_tokens`
   - It also seeds default data and creates a Super Admin account

6. **Configure SMTP (optional but recommended)**
   - Edit `.env` file:
     ```env
     SMTP_HOST=smtp.gmail.com
     SMTP_PORT=587
     SMTP_USER=your-smtp-email@gmail.com
     SMTP_PASS=your-gmail-app-password
     SMTP_ENCRYPTION=tls
     SMTP_FROM_EMAIL=your-smtp-email@gmail.com
     SMTP_FROM_NAME=Your Club Name
     ```

7. **Set directory permissions**
   - Ensure `uploads/` and subdirectories are writable
   - Ensure `logs/` directory is writable (for email error logging)

8. **Access the site**
   - Public: `http://localhost/rotary-club-virar/`
   - Admin login: `http://localhost/rotary-club-virar/login.php`

## Database Setup

The application uses a MySQL database named `rotary`. Key tables:

- `admins` — Admin login credentials (supports roles: `super_admin`, `President`, `Secretary`, `Treasurer`, `IT Admin`)
- `members` — Team/leadership members
- `events` — Club events (status computed dynamically from dates)
- `projects` — Club projects
- `donors` — Donor records
- `donations` — Donation records
- `collaborations` — Collaboration proposals
- `media_gallery` / `gallery_media` — Gallery images (dual tables)
- `event_polling` — Event RSVP/voting data
- `event_reports` — Event reports
- `project_reports` — Project reports
- `donation_report` — Donation reports
- `collaboration_reports` — Collaboration reports
- `announcements` — Announcement records (for gallery associations)
- `contact_messages` — Contact form submissions
- `rotary_years` — Rotary year definitions
- `leadership_assignments` — Leadership per Rotary Year
- `site_content` — Editable website content
- `password_reset_tokens` — Password reset tokens

See `DATABASE_STRUCTURE.md` for complete schema documentation.

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `DB_HOST` | `localhost` | Database host |
| `DB_NAME` | `rotary` | Database name |
| `DB_USER` | `root` | Database username |
| `DB_PASS` | `` | Database password |
| `SMTP_HOST` | `smtp.gmail.com` | SMTP server host |
| `SMTP_PORT` | `587` | SMTP server port |
| `SMTP_USER` | `` | SMTP username (set in `.env`) |
| `SMTP_PASS` | `` | SMTP password (Gmail App Password) |
| `SMTP_ENCRYPTION` | `tls` | SMTP encryption method |
| `SMTP_FROM_EMAIL` | `` | From email address (set in `.env`) |
| `SMTP_FROM_NAME` | `` | From name (set in `.env`) |

## Admin Roles

| Role | Access |
|------|--------|
| **Super Admin** | Full access to all features including Admin Account Management, Rotary Year Management, Leadership Management, Website Content Management |
| **President** | Full CRUD access to Events, Projects, Members, Donations, Collaborations, Gallery, Reports, Settings |
| **Secretary** | Full CRUD access to Events, Projects, Members, Donations, Collaborations, Gallery, Reports, Settings |
| **Treasurer** | Full CRUD access to Events, Projects, Members, Donations, Collaborations, Gallery, Reports, Settings |

Note: Super Admin is a separate role with elevated privileges. President/Secretary/Treasurer have identical feature access but different role labels. The `IT Admin` role is also available.

## Admin Login

Access the admin panel at `http://localhost/rotary-club-virar/login.php`

After running `db_migration.php`, a Super Admin account is created.
- Email: `admin@example.com` (or set `SUPER_ADMIN_EMAIL` in `.env`)
- Password: auto-generated or set via `SUPER_ADMIN_PASSWORD` in `.env`

**Change the password immediately after first login.**

See `ADMIN_CREDENTIALS_TEMPLATE.md` for more details. Actual credentials are managed through the database.

## Deployment Instructions

### Production Setup
1. Set up a PHP 8.0+ hosting environment with MySQL
2. Copy all project files to the web root
3. Create the `rotary` database and run `Admin/db_migration.php`
4. Configure `.env` with production credentials
5. Enable HTTPS/SSL
6. Set proper file permissions (755 for dirs, 644 for files)
7. Test all pages and email sending

See `DEPLOYMENT_GUIDE.md` for detailed deployment instructions.

## Maintenance Guide

### Regular Tasks
- Monitor `logs/email_log.txt` for email failures
- Backup the database regularly via phpMyAdmin or mysqldump
- Update the current Rotary Year at the start of each new Rotary year (July 1)
- Assign new leadership at the start of each Rotary year
- Review and respond to collaboration proposals
- Process donation verifications

### Year-End Transition
1. Add the new Rotary Year in `Admin/rotary_years.php`
2. Assign new leadership in `Admin/leadership_transfer.php`
3. Set the new year as current
4. Previous year's leadership records are preserved automatically

## File Structure

See `PROJECT_STRUCTURE.md` for a complete breakdown of all files and directories.

## Security Features

- CSRF protection on all forms (`includes/csrf_helper.php`)
- Secure session handling (`includes/session_security.php`)
- 30-minute session timeout
- Session regeneration on login
- bcrypt password hashing
- Rate limiting (10 failed attempts)
- Prepared statements for SQL queries
- File upload type and size validation
- Environment-based configuration (`.env`)
- Password reset with expiring tokens

## Configuration

### Club Settings (`config/club_settings.php`)
Central configuration for club information:
- `CLUB_NAME`, `CLUB_EMAIL`, `CLUB_PHONE`
- `CLUB_ADDRESS` (multi-line and single-line)
- `CLUB_MAP_URL` (Google Maps)
- `CLUB_INSTAGRAM_URL`, `CLUB_FACEBOOK_URL`
