# Project Structure

```
rotary-club-virar/
│
├── .env                                # Environment variables (DB, SMTP)
├── .env.example                        # Environment template
├── .gitignore                          # Git ignore rules
│
├── aboutus.php                         # About Us page
├── activities.php                      # Activities page (Events + Projects with filters/polls/collaboration)
├── contact.php                         # Contact Us page (form, map, leadership, CSRF)
├── donate.php                          # Multi-step donation form
├── events.php                          # Events listing page
├── forgot_password.php                 # Password reset request page
├── index.php                           # Home page
├── login.php                           # Admin login page (with CSRF)
├── login_action.php                    # AJAX login handler
├── logout.php                          # Public logout
├── mediaGallery.php                    # Media Gallery page
├── projects.php                        # Projects listing page
├── receipt.php                         # Donation receipt page
├── reset_password.php                  # Password reset form (token-based)
├── team.php                            # Team/Leadership page (Rotary Year selector)
├── view_event_report.php               # Event report view
├── view_project_report.php             # Project report view
│
├── admin_test_email.php                # Email test utility
│
├── Admin/                              # Admin Dashboard Backend
│   ├── dashboard.php                   # Main dashboard (stats, charts, quick actions)
│   ├── logout.php                      # Admin logout
│   ├── change_password.php             # Password change form
│   ├── event_action.php                # Events CRUD (Add/Edit/Delete/List)
│   ├── event_report_action.php         # Event reports management
│   ├── project_action.php              # Projects CRUD (Add/Edit/Delete/List)
│   ├── project_report.php              # Project reports viewer
│   ├── project_report_action.php       # Project reports management
│   ├── member_action.php               # Members CRUD (Add/Edit/Delete/List)
│   ├── member_List.php                 # Member list with CSV export
│   ├── donation_action.php             # Donations & Donors management (with email notifications)
│   ├── donation_report_action.php      # Donation reports management
│   ├── collaboration_action.php        # Collaboration proposals (Approve/Reject/Delete with email)
│   ├── collaboration_report.php        # Collaboration reports viewer
│   ├── admin_add_media.php             # Media upload form
│   ├── gallery_action.php              # Gallery media upload handler
│   ├── gallery_list.php                # Gallery media listing
│   ├── addEvent_poll.php               # Event poll management
│   ├── pollVoter_list.php              # Event poll voter list
│   │
│   │── Super Admin Features (role-gated)
│   ├── admins_management.php           # Admin Account Management (Super Admin only)
│   ├── rotary_years.php                # Rotary Year Management (Super Admin only)
│   ├── leadership_transfer.php         # Leadership Management (Super Admin only)
│   ├── site_content.php                # Website Content Management (Super Admin only)
│   │
│   ├── db_migration.php                # DB migration/setup script (creates all tables)
│   ├── admin_functions.php             # Shared admin functions (isSuperAdmin, getCurrentRotaryYear, etc.)
│   │
│   └── includes/                       # Admin shared components
│       ├── admin_head.php              # Admin CSS/head (Tailwind, Chart.js, SweetAlert2, Lucide)
│       ├── admin_header.php            # Admin sidebar navigation + top header
│       └── admin_footer.php            # Admin footer JS
│
├── assets/                             # Static Assets
│   ├── images/                         # Static images
│   │   ├── donations/                  # Donation-related images (QR code)
│   │   ├── Events/                     # Static event images
│   │   ├── logos/                      # Logo files
│   │   └── projects/                   # Default project images
│   │
│   └── uploads/                        # Uploaded assets
│       ├── admins/                     # Admin profile photos
│       ├── donations/                  # Donation-related uploads
│       ├── events/                     # Event images
│       ├── gallery/                    # Gallery media (via gallery_action.php)
│       ├── Logo/                       # Logo files and hero images
│       ├── members/                    # Member profile photos
│       └── projects/                   # Project images
│
├── config/                             # Configuration Files
│   ├── club_settings.php               # Centralized club settings (name, address, phone, social)
│   └── env.php                         # Environment variable loader (.env parser)
│
├── includes/                           # Shared Includes
│   ├── db_connect.php                  # Database connection (reads .env via config/env.php)
│   ├── footer.php                      # Site-wide footer
│   ├── email_config.php                # SMTP configuration constants (reads .env)
│   ├── send_email.php                  # Email sending function (PHPMailer wrapper)
│   ├── csrf_helper.php                 # CSRF token generation and validation
│   ├── session_security.php            # Secure session management (start, regenerate, timeout, destroy)
│   ├── upload_helper.php               # File upload validation and secure path handling
│   │
│   ├── phpmailer/                      # PHPMailer v6.9.3 library (manual install, no Composer)
│   │   ├── PHPMailer.php               # Main PHPMailer class
│   │   ├── SMTP.php                    # SMTP transport class
│   │   ├── POP3.php                    # POP3 authentication class
│   │   ├── Exception.php               # Custom exception class
│   │   ├── OAuth.php                   # OAuth2 authentication
│   │   ├── OAuthTokenProvider.php      # OAuth2 token provider interface
│   │   └── DSNConfigurator.php         # DSN string configurator
│   │
│   └── email_templates/                # Email notification templates
│       ├── donation_verified.php       # Donation verified notification
│       ├── donation_contacted.php      # Donor contacted notification
│       ├── donation_received.php       # Donation received notification
│       ├── donation_completed.php      # Donation completed notification
│       ├── collaboration_approved.php  # Collaboration approved notification
│       └── collaboration_rejected.php  # Collaboration rejected notification
│
├── uploads/                            # User Uploads (dynamic content)
│   ├── .htaccess                       # Deny direct directory access
│   ├── donations/                      # Donation screenshots
│   ├── events/                         # Event images (uploaded via admin)
│   ├── gallery/                        # Gallery media (uploaded via admin gallery_action.php)
│   ├── media/                          # Gallery media (uploaded via admin_add_media.php)
│   ├── members/                        # Member photos (uploaded via admin)
│   ├── projects/                       # Project images (uploaded via admin)
│   └── site_content/                   # Content images (uploaded via site_content.php)
│
├── logs/                               # Log Files
│   ├── .htaccess                       # Deny direct directory access
│   └── email_log.txt                   # Email sending failure log
│
├── README.md                           # Project overview and setup guide
├── PROJECT_HANDOVER.md                 # Handover documentation
├── DATABASE_STRUCTURE.md               # Database schema documentation
├── MODULE_OVERVIEW.md                  # Module-by-module documentation
├── DEPLOYMENT_GUIDE.md                 # Production deployment instructions
├── ADMIN_CREDENTIALS_TEMPLATE.md       # Admin credentials template
└── PROJECT_STRUCTURE.md                # This file
```

---

## Folder Purpose Summary

| Folder | Purpose |
|--------|---------|
| `Admin/` | Admin dashboard backend — all CRUD operations, reports, management interfaces. Protected by session authentication. |
| `Admin/includes/` | Shared admin UI components — head (CSS/libs), header (sidebar navigation), footer (JS). |
| `assets/images/` | Static images used across the site (logos, default images, static event/project images). |
| `assets/uploads/` | Uploaded content organized by type (admins, donations, events, gallery, Logo, members, projects). |
| `config/` | Configuration files — `club_settings.php` (club info), `env.php` (environment loader). |
| `includes/` | Shared PHP includes — database connection, footer, PHPMailer library, email sending, email templates, CSRF, session security, upload helper. |
| `includes/phpmailer/` | External library (PHPMailer v6.9.3) — provides SMTP email sending capability. Manually installed (no Composer). |
| `includes/email_templates/` | PHP functions that return email subject/body arrays for each notification type. |
| `uploads/` | Dynamic user-uploaded content (event images, project images, member photos, gallery media, donation screenshots). Must be writable by the web server. |
| `logs/` | Application logs — currently only used for email failure logging. |

## Public Pages vs Admin Pages

### Public Pages (accessible without login)
| File | Route |
|------|-------|
| `index.php` | `/` |
| `aboutus.php` | `/aboutus.php` |
| `activities.php` | `/activities.php` |
| `mediaGallery.php` | `/media-gallery` (or `/mediaGallery.php`) |
| `team.php` | `/team` (or `/team.php`) |
| `donate.php` | `/donate` (or `/donate.php`) |
| `contact.php` | `/contact` (or `/contact.php`) |
| `events.php` | `/events.php` |
| `projects.php` | `/projects.php` |

### Authentication Pages
| File | Route | Purpose |
|------|-------|---------|
| `login.php` | `/login.php` | Admin login (CSRF protected) |
| `login_action.php` | `/login_action.php` | AJAX login handler |
| `forgot_password.php` | `/forgot_password.php` | Password reset request |
| `reset_password.php` | `/reset_password.php?token=...` | Password reset form |
| `logout.php` | `/logout.php` | Logout |

### Admin Pages (require login — redirect to `login.php` if not authenticated)
| File | Route | Access |
|------|-------|--------|
| `Admin/dashboard.php` | `/admin/dashboard` | All roles |
| `Admin/event_action.php` | `/admin/events` | All roles |
| `Admin/project_action.php` | `/admin/projects` | All roles |
| `Admin/member_action.php` | `/admin/members` | All roles |
| `Admin/donation_action.php` | `/admin/donations` | All roles |
| `Admin/collaboration_action.php` | `/admin/collaborations` | All roles |
| `Admin/gallery_list.php` | `/admin/gallery` | All roles |
| `Admin/change_password.php` | `/admin/settings` | All roles |
| `Admin/admins_management.php` | `/admin/admins` | Super Admin only |
| `Admin/rotary_years.php` | `/admin/rotary-years` | Super Admin only |
| `Admin/leadership_transfer.php` | `/admin/leadership` | Super Admin only |
| `Admin/site_content.php` | `/admin/site-content` | Super Admin only |
