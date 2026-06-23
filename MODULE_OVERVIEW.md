# Module Overview

---

## 1. Home Page

**Purpose:** Landing page showcasing the club's featured projects, upcoming events, impact statistics, gallery, areas of focus, and a call to action for visitors.

**Files:**
- `index.php` (main page)
- `includes/footer.php` (shared footer)
- `config/club_settings.php` (club info used in hero/footer)

**Database Tables Used:**
- `projects` — featured project cards (1 per status: Upcoming, Ongoing, Completed)
- `events` — upcoming events display
- `members` — member count stat
- `media_gallery` — latest gallery images (up to 6)
- `site_content` — hero text, about preview text, area images, activities section text

**Key Features:**
- Hero section with editable heading/description/image (via site_content.php)
- About preview with editable title/description/image
- 7 Areas of Rotary focus with editable images
- Featured activity cards (one per status: Upcoming, Ongoing, Completed)
- Impact statistics (members, projects, events, donations)
- Gallery section (latest 6 active images)
- Call-to-action for joining Rotary

---

## 2. About Us

**Purpose:** Provides club mission, values, impact metrics, and areas of focus.

**Files:**
- `aboutus.php`
- `includes/footer.php`

**Database Tables Used:**
- `projects` — project count for impact stats
- `site_content` — banner title/description/image, about description, images 1-3

**Key Features:**
- Editable banner section (title, description, image)
- "Who We Are" section with club description (editable)
- Values cards (Fellowship, Integrity, Service)
- Impact metrics (projects completed, health beneficiaries, trees planted)
- Areas of Focus cards (Education, Healthcare, Environment, Women Empowerment, Youth Development, Community Welfare, Peace and Harmony)
- Founder's quote section
- Editable images (3 slots)

---

## 3. Activities

**Purpose:** Central listing of all club events and projects with advanced filtering, detail views, and collaboration proposal submission.

**Files:**
- `activities.php`
- `includes/footer.php`

**Database Tables Used:**
- `events` — event records with dates, location, images
- `projects` — project records with status, collaborator, images
- `event_polling` — RSVP/voting responses per event
- `collaborations` — proposal submissions

**Key Features:**
- Filter buttons: All / Events / Projects / Upcoming / Ongoing / Completed
- Client-side card rendering via JavaScript
- Event detail modal with RSVP polling (Yes/No/Maybe) — members and guests can vote
- Project detail slide-in panel
- Poll results displayed as a bar chart
- Admin can view detailed voter lists
- Event status auto-calculated from start/end dates
- Collaboration proposal submission form (name, email, proposal)

**JavaScript Architecture:**
- `SERVER_EVENTS` / `DATA_EVENTS` — event data from PHP
- `SERVER_PROJECTS` / `DATA_PROJECTS` — project data from PHP
- `ALL_ITEMS` — merged array for "All" filter
- `buildEventList()` — renders event cards by status
- `filterActivities(type)` — filters and re-renders
- `openDetail(item)` — opens appropriate detail view
- AJAX endpoints for poll voting and fetching vote counts

---

## 4. Media Gallery

**Purpose:** Visual gallery displaying event and project photos with lightbox viewing.

**Files:**
- `mediaGallery.php`
- `includes/footer.php`

**Database Tables Used:**
- `media_gallery` — media entries with title, caption, category, file path, status

**Key Features:**
- Image grid with lightbox modal
- Category filtering
- Responsive grid layout (via Tailwind)
- Status-based filtering (only `active` images displayed)

---

## 5. Team

**Purpose:** Showcases club leadership and board of directors with Rotary Year awareness.

**Files:**
- `team.php`
- `includes/footer.php`

**Database Tables Used:**
- `members` — member records with role, photo, bio, display order
- `rotary_years` — year selector (current and previous years)
- `leadership_assignments` — leadership roles per year

**Key Features:**
- Leadership section (President, Secretary, Treasurer) with crown icon and prominent cards
- Board of Directors section (all other active members)
- Rotary Year selector (current + previous years)
- Member detail modal with full profile
- Photo with fallback to initials
- Contact info display controlled by `show_contact` flag
- Sortable by `display_order`

---

## 6. Donations

**Purpose:** Multi-step donation form for monetary and in-kind contributions.

**Files:**
- `donate.php`
- `receipt.php`
- `includes/footer.php`

**Database Tables Used:**
- `donors` — donor contact information
- `donations` — donation records with type, amount, status, UTR, screenshot

**Key Features:**
- 5-step form wizard:
  1. **Donor Details** (name, phone, email, occupation, address)
  2. **Category** (Monetary, School Supplies, Food & Grocery, Clothes & Blankets, Medical Support, Volunteer Support, Other)
  3. **Details** (amount + UTR for monetary, description for in-kind)
  4. **Delivery/Pickup** (self-delivery or arrange pickup with address/date/notes)
  5. **Review & Submit** (full summary before AJAX submission)
- QR code payment display for UPI/scan-and-pay
- File upload for payment screenshots (JPG, JPEG, PNG, WEBP, GIF)
- AJAX-based step progression

---

## 7. Contact Us

**Purpose:** Contact page with contact form, club info, leadership display, map, and social links.

**Files:**
- `contact.php`
- `includes/footer.php`

**Database Tables Used:**
- `members` — leadership info (President, Secretary, Treasurer)
- `site_content` — banner title/description/image, contact description, contact image
- `contact_messages` — stores form submissions (created on first request)

**Key Features:**
- Animated hero section with editable content
- Contact form with CSRF protection (name, email, subject, message)
- Contact info cards (phone, email, address from `CLUB_*` constants)
- Leadership cards with photo/initials, role badge, bio, contact info
- Google Maps embed using `CLUB_MAP_URL`
- Social media links (Instagram, Facebook)
- Form submissions stored in `contact_messages` table

---

## 8. Admin Dashboard

**Purpose:** Full administrative backend for managing all site content.

**Files:**
- `Admin/dashboard.php` — main dashboard with stats and charts
- `Admin/includes/admin_head.php` — shared admin CSS/head (Tailwind, Chart.js, SweetAlert2, Lucide)
- `Admin/includes/admin_header.php` — shared sidebar + top header navigation
- `Admin/includes/admin_footer.php` — shared footer JS
- `Admin/admin_functions.php` — shared functions (`isSuperAdmin()`, `getCurrentRotaryYear()`, `getLeadershipForYear()`, `getSiteContent()`, `e()`)

**Management Modules:**

| Module | File | Purpose |
|--------|------|---------|
| Events | `Admin/event_action.php` | CRUD for events with image upload |
| Event Reports | `Admin/event_report_action.php` | Event reporting |
| Projects | `Admin/project_action.php` | CRUD for projects with status & collaborator |
| Project Reports | `Admin/project_report.php` | Project reports viewer |
| | `Admin/project_report_action.php` | Project report management |
| Members | `Admin/member_action.php` | CRUD for members with photo, bio, display order |
| | `Admin/member_List.php` | Member list with CSV export |
| Donations | `Admin/donation_action.php` | Full donation pipeline with email notifications |
| | `Admin/donation_report_action.php` | Donation reports |
| Collaborations | `Admin/collaboration_action.php` | Approve/reject proposals with email notifications |
| | `Admin/collaboration_report.php` | Collaboration reports |
| Media Gallery | `Admin/admin_add_media.php` | Upload media form (to media_gallery) |
| | `Admin/gallery_action.php` | Gallery upload handler (to gallery_media) |
| | `Admin/gallery_list.php` | Gallery media listing |
| Event Polling | `Admin/addEvent_poll.php` | Poll management per event |
| | `Admin/pollVoter_list.php` | Voter list per event |
| Admin Accounts | `Admin/admins_management.php` | Super Admin only: create, reset password, change role, toggle status |
| Rotary Years | `Admin/rotary_years.php` | Super Admin only: add, set current, edit years |
| Leadership | `Admin/leadership_transfer.php` | Super Admin only: assign President/Secretary/Treasurer per year |
| Site Content | `Admin/site_content.php` | Super Admin only: edit Home/About/Contact page content and images |
| Settings | `Admin/change_password.php` | Admin password change |
| Logout | `Admin/logout.php` | Session termination |

**Database Tables Used:**
- `admins` — authentication
- `events`, `projects` — content management
- `members` — team management
- `donors`, `donations` — donation management
- `collaborations` — proposal management
- `media_gallery`, `gallery_media` — gallery management
- `event_polling` — poll management
- `event_reports`, `project_reports`, `donation_report`, `collaboration_reports` — reporting
- `rotary_years`, `leadership_assignments` — super admin features
- `site_content` — content management
- `password_reset_tokens` — password reset

**Key Features:**
- Role-based access (Super Admin has additional menu items)
- Dashboard statistics (projects, events, members, donations, collaborations, donation amount)
- Chart.js line chart for monthly engagement
- Chart.js doughnut chart for donation type ratio
- SweetAlert2 confirmation dialogs
- Responsive sidebar with navigation sections
- Email notifications via PHPMailer for donation status changes and collaboration approvals/rejections

---

## 9. Super Admin Features

### Admin Account Management
- **File:** `Admin/admins_management.php`
- Create new admin accounts (President, Secretary, Treasurer, IT Admin)
- Reset passwords for any admin
- Change roles (except Super Admin)
- Toggle active/inactive status (except Super Admin)
- Only one Super Admin allowed

### Rotary Year Management
- **File:** `Admin/rotary_years.php`
- Add new Rotary Years (format: `YYYY-YY`, e.g. `2027-28`)
- Set any year as the current active year
- View all years with current/previous status

### Leadership Management
- **File:** `Admin/leadership_transfer.php`
- Select a Rotary Year
- Assign President, Secretary, Treasurer from active members
- View current leadership team
- View historical leadership teams (last 5 previous years)

### Website Content Management
- **File:** `Admin/site_content.php`
- **Home Page:** Hero heading/description/image, about preview title/description/image, 7 areas of Rotary images, featured activities heading/description
- **About Page:** Banner title/description/image, about description, 3 content images
- **Contact Page:** Banner title/description/image, contact description

---

## 10. Email System

**Purpose:** Automated email notifications for donations, collaborations, and password resets.

**Files:**
- `includes/send_email.php` — Email sending function using PHPMailer
- `includes/email_config.php` — SMTP configuration constants (reads from `.env`)
- `includes/phpmailer/` — PHPMailer v6.9.3 library (manual install, no Composer)
- `includes/email_templates/` — Email template functions (6 templates):
  - `donation_verified.php`
  - `donation_contacted.php`
  - `donation_received.php`
  - `donation_completed.php`
  - `collaboration_approved.php`
  - `collaboration_rejected.php`
- `logs/email_log.txt` — Email failure log

**How It Works:**
1. `Admin/donation_action.php` calls `sendEmail()` when donation status changes
2. `Admin/collaboration_action.php` calls `sendEmail()` when a proposal is approved or rejected
3. `forgot_password.php` calls `sendEmail()` to send password reset link
4. `sendEmail()` creates a PHPMailer instance, configures SMTP from constants, sends HTML email
5. On failure, errors are logged to `logs/email_log.txt`

**SMTP Configuration (set in `.env`):**
- Host: `smtp.gmail.com`
- Port: `587`
- Encryption: `tls`
- Username: (set via `SMTP_USER` in `.env`)
- Password: Google App Password

---

## 11. Security System

**Files:**
- `includes/csrf_helper.php` — CSRF token generation and validation
- `includes/session_security.php` — Secure session management
- `includes/upload_helper.php` — File upload validation
- `login.php` / `login_action.php` — Authentication with rate limiting
- `forgot_password.php` / `reset_password.php` — Password reset with expiring tokens

**Features:**
- **CSRF Protection:** All forms include hidden `_csrf_token` field validated server-side
- **Session Security:** HttpOnly, SameSite=Lax cookies; session timeout after 30 minutes; session regeneration on login
- **Password Hashing:** bcrypt via `password_hash()` with cost factor 10
- **Rate Limiting:** 10 failed login attempts before temporary lockout (separate counters for form and AJAX login)
- **Upload Validation:** File type and size validation; secure path handling
- **Prepared Statements:** All SQL queries use prepared statements
- **Environment Variables:** Sensitive credentials stored in `.env`, not in code
- **Password Reset:** Expiring tokens (30 minutes), single-use, bcrypt-hashed new passwords
