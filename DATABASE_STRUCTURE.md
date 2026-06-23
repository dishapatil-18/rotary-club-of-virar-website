# Database Structure

## Overview

- **Database Name:** `rotary`
- **Charset:** `utf8mb4`
- **Engine:** InnoDB (recommended for all tables)
- **Migration Script:** `Admin/db_migration.php` — run this script to create/update all tables

There is no SQL dump file. Use `Admin/db_migration.php` to set up the database schema.

---

## Core Tables

### Table: `admins`

**Purpose:** Stores admin login credentials for the dashboard.

| Column | Type | Description |
|--------|------|-------------|
| `admin_id` | INT (PK, auto-increment) | Unique identifier |
| `name` | VARCHAR(255) | Admin's full name |
| `email` | VARCHAR(255) | Login email (unique) |
| `password` | VARCHAR(255) | bcrypt-hashed password (`password_hash()`) |
| `role` | ENUM('super_admin','President','Secretary','Treasurer','IT Admin') | Admin role |
| `photo_url` | VARCHAR(500) | Path to admin profile photo |
| `status` | ENUM('active','inactive') DEFAULT 'active' | Account status |
| `phone` | VARCHAR(20) | Contact phone number |
| `created_at` | DATETIME | Account creation timestamp |

**Relationships:** Referenced by `password_reset_tokens` (via `admin_id`)

**Notes:**
- Only one Super Admin allowed (enforced in `Admin/admins_management.php`)
- Super Admin cannot be disabled or have role changed
- Login requires `status = 'active'`

---

### Table: `members`

**Purpose:** Stores team/leadership members displayed on Team and Contact pages.

| Column | Type | Description |
|--------|------|-------------|
| `member_id` | INT (PK, auto-increment) | Unique identifier |
| `name` | VARCHAR(255) | Member's full name |
| `email` | VARCHAR(255) | Email address |
| `phone_number` | VARCHAR(20) | Contact phone |
| `address` | TEXT | Residential address |
| `role` | VARCHAR(100) | Role title: `President`, `Secretary`, `Treasurer`, `Member`, or custom |
| `status` | VARCHAR(20) | `Active` or `Inactive` |
| `joined_date` | DATE | Date joined the club |
| `photo_url` | VARCHAR(500) | Path to profile photo |
| `profession` | VARCHAR(255) | Professional occupation |
| `short_bio` | TEXT | Brief biography |
| `display_order` | INT | Sort order (lower = displayed first) |
| `show_contact` | TINYINT(1) | Whether to show contact info publicly |

**Relationships:** Referenced by `event_polling` (via `member_id`), `leadership_assignments` (via `member_id`)

---

### Table: `events`

**Purpose:** Stores club events displayed on Activities and Home pages.

| Column | Type | Description |
|--------|------|-------------|
| `event_id` | INT (PK, auto-increment) | Unique identifier |
| `title` | VARCHAR(255) | Event title |
| `description` | TEXT | Event description |
| `start_date` | DATETIME | Event start date/time |
| `end_date` | DATETIME | Event end date/time |
| `location` | VARCHAR(255) | Event location/venue |
| `category` | VARCHAR(100) | Event category |
| `image_url` | VARCHAR(500) | Path to event image |

**Relationships:** Referenced by `event_polling` (via `event_id`), `event_reports` (via `event_id`)

**Notes:**
- Event status (Upcoming/Ongoing/Completed) is computed dynamically from `start_date` and `end_date`
- No `poll_enabled` or `certificates_enabled` columns in current implementation

---

### Table: `projects`

**Purpose:** Stores club projects displayed on Activities and Home pages.

| Column | Type | Description |
|--------|------|-------------|
| `project_id` | INT (PK, auto-increment) | Unique identifier |
| `title` | VARCHAR(255) | Project title |
| `description` | TEXT | Project description |
| `start_date` | DATE | Project start date |
| `end_date` | DATE | Project end date |
| `status` | VARCHAR(20) | `Upcoming`, `Ongoing`, or `Completed` |
| `image_url` | VARCHAR(500) | Path to project image |
| `collaborator` | VARCHAR(255) | Collaborating organization/individual |
| `created_at` | DATETIME | Record creation timestamp |

**Relationships:** Referenced by `project_reports` (via `project_id`)

---

### Table: `donors`

**Purpose:** Stores donor information submitted via the donation form.

| Column | Type | Description |
|--------|------|-------------|
| `donor_id` | INT (PK, auto-increment) | Unique identifier |
| `name` | VARCHAR(255) | Donor's full name |
| `phone_number` | VARCHAR(20) | Contact phone |
| `email` | VARCHAR(255) | Email address |
| `social_role` | VARCHAR(100) | Optional social/professional role |
| `address` | TEXT | Residential/business address |
| `occupation` | VARCHAR(255) | Occupation |
| `created_at` | DATETIME | Record creation timestamp |

**Relationships:** Referenced by `donations` (via `donor_id`)

---

### Table: `donations`

**Purpose:** Stores donation records linked to donors.

| Column | Type | Description |
|--------|------|-------------|
| `donation_id` | INT (PK, auto-increment) | Unique identifier |
| `donor_id` | INT (FK -> donors) | The donor who made the donation |
| `donation_type` | VARCHAR(50) | `Money` or `Kind` |
| `description` | TEXT | Description of in-kind donation |
| `amount` | DECIMAL(12,2) | Monetary amount |
| `utr_number` | VARCHAR(100) | UTR / transaction reference number |
| `screenshot_path` | VARCHAR(500) | Path to payment screenshot |
| `pickup_option` | VARCHAR(50) | `Self Delivery` or `Arrange Pickup` |
| `status` | VARCHAR(30) | Pipeline: `Pending Verification`, `Verified`, `Contacted`, `Received`, `Completed` |
| `date` | DATETIME | Donation date/timestamp |

**Foreign Keys:**
- `donor_id` -> `donors(donor_id)` ON DELETE CASCADE

**Relationships:** Referenced by `donation_report` (via `donation_id`)

---

### Table: `collaborations`

**Purpose:** Stores collaboration/submission proposals from the Activities/Projects pages.

| Column | Type | Description |
|--------|------|-------------|
| `collab_id` | INT (PK, auto-increment) | Unique identifier |
| `name` | VARCHAR(255) | Proposer's name |
| `email` | VARCHAR(255) | Proposer's email |
| `proposal` | TEXT | Proposal details |
| `submitted_on` / `submitted_at` | DATETIME | Submission timestamp |
| `status` | VARCHAR(20) | `Pending`, `Approved`, or `Rejected` |
| `reviewed_by` | INT (nullable) | Admin ID who reviewed |
| `reviewed_at` | DATETIME (nullable) | Review timestamp |

**Relationships:** Referenced by `collaboration_reports` (via `collab_id`)

---

### Table: `media_gallery`

**Purpose:** Primary gallery table for media displayed on the Media Gallery page and Home page. Also used for event/project report media associations.

| Column | Type | Description |
|--------|------|-------------|
| `media_id` | INT (PK, auto-increment) | Unique identifier |
| `reference_type` | VARCHAR(50) | Linked entity type (`event`, `project`, `announcement`, or empty) |
| `reference_id` | INT | Linked entity ID |
| `title` | VARCHAR(255) | Media title |
| `description` | TEXT | Media description/caption |
| `media_type` | VARCHAR(20) | `image` (currently only image is supported) |
| `media_path` | VARCHAR(500) | Relative path to media file (e.g., `uploads/media/...`) |
| `media_date` | VARCHAR(100) | Associated date |
| `uploaded_by` | VARCHAR(255) | Admin name who uploaded |
| `status` | VARCHAR(20) | `active` or `inactive` |
| `created_at` | DATETIME | Record creation timestamp |

**Usage:** Used by `index.php`, `mediaGallery.php`, `Admin/admin_add_media.php`, `Admin/gallery_list.php`, `view_event_report.php`, `view_project_report.php`

---

### Table: `gallery_media`

**Purpose:** Secondary gallery table used by `Admin/gallery_action.php` for admin-side gallery upload.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT (PK, auto-increment) | Unique identifier |
| `title` | VARCHAR(255) | Media title |
| `caption` | TEXT | Media caption |
| `media_type` | VARCHAR(20) | `image` or `video` |
| `category` | VARCHAR(100) | Gallery category |
| `media_url` | VARCHAR(500) | URL/path to media file |
| `thumbnail_url` | VARCHAR(500) | Thumbnail path |
| `uploaded_by` | VARCHAR(255) | Admin name who uploaded |
| `upload_date` | DATETIME | Upload timestamp |

**Note:** Both `media_gallery` and `gallery_media` tables exist with different column structures. `media_gallery` is used for frontend display, report views, and admin listing; `gallery_media` is used by the `gallery_action.php` upload handler. These may need consolidation.

---

### Table: `event_polling`

**Purpose:** Stores RSVP/voting responses for events (Yes / No / Maybe).

| Column | Type | Description |
|--------|------|-------------|
| `poll_id` | INT (PK, auto-increment) | Unique identifier |
| `event_id` | INT (FK -> events) | The event being polled |
| `member_id` | INT (FK -> members, nullable) | Voting member (null if guest) |
| `is_attending` | VARCHAR(10) | Response: `Yes`, `No`, or `Maybe` |
| `guest_name` | VARCHAR(255) | Guest name if not a member (nullable) |
| `submitted_on` | DATETIME | Timestamp of vote submission |

**Foreign Keys:**
- `event_id` -> `events(event_id)` ON DELETE CASCADE
- `member_id` -> `members(member_id)` ON DELETE SET NULL

---

## Report Tables

### Table: `event_reports`

| Column | Type | Description |
|--------|------|-------------|
| `report_id` | INT (PK, auto-increment) | Unique identifier |
| `event_id` | INT (FK -> events) | The event being reported on |
| `details` | TEXT | Report content/details |
| `submitted_by` | VARCHAR(255) | Name/ID of submitter |
| `date` | DATETIME | Report submission date |

**Foreign Keys:** `event_id` -> `events(event_id)` ON DELETE CASCADE

---

### Table: `project_reports`

| Column | Type | Description |
|--------|------|-------------|
| `project_id` | INT (PK, FK -> projects) | The project being reported on |
| `year` | YEAR/INT | Report year |
| `summary` | TEXT | Project summary |
| `funds_raised` | DECIMAL(12,2) | Total funds raised |
| `expenditure` | DECIMAL(12,2) | Total expenditure |
| `achievements` | TEXT | Key achievements |
| `created_at` | DATETIME | Report creation timestamp |

**Foreign Keys:** `project_id` -> `projects(project_id)` ON DELETE CASCADE

---

### Table: `donation_report`

| Column | Type | Description |
|--------|------|-------------|
| `report_id` | INT (PK, auto-increment) | Unique identifier |
| `donation_id` | INT (FK -> donations) | The donation being reported on |
| `report_summary` | TEXT | Report content |
| `verified_by` | VARCHAR(255) | Admin name who verified |
| `created_on` | DATETIME | Report creation timestamp |

**Foreign Keys:** `donation_id` -> `donations(donation_id)` ON DELETE CASCADE

---

### Table: `collaboration_reports`

| Column | Type | Description |
|--------|------|-------------|
| `report_id` | INT (PK, auto-increment) | Unique identifier |
| `collab_id` | INT (FK -> collaborations) | The collaboration being reported on |
| `report_summary` | TEXT | Report content |

**Foreign Keys:** `collab_id` -> `collaborations(collab_id)` ON DELETE CASCADE

---

## New Tables (Added via db_migration.php)

### Table: `rotary_years`

**Purpose:** Defines Rotary years for leadership assignment and historical tracking.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT (PK, auto-increment) | Unique identifier |
| `year_name` | VARCHAR(20) NOT NULL UNIQUE | Year identifier (e.g., `2025-26`) |
| `is_current` | TINYINT(1) DEFAULT 0 | Whether this is the current active year |
| `created_at` | DATETIME DEFAULT CURRENT_TIMESTAMP | Record creation timestamp |

**Relationships:** Referenced by `leadership_assignments` (via `rotary_year_id`)

---

### Table: `leadership_assignments`

**Purpose:** Maps members to leadership roles for specific Rotary years.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT (PK, auto-increment) | Unique identifier |
| `rotary_year_id` | INT (FK -> rotary_years) | The Rotary year |
| `member_id` | INT (FK -> members) | The assigned member |
| `role` | VARCHAR(50) | Role: `President`, `Secretary`, or `Treasurer` |
| `created_at` | DATETIME DEFAULT CURRENT_TIMESTAMP | Record creation timestamp |

**Foreign Keys:**
- `rotary_year_id` -> `rotary_years(id)` ON DELETE CASCADE
- `member_id` -> `members(member_id)` ON DELETE CASCADE
- UNIQUE KEY on `(rotary_year_id, role)` — one role per year

---

### Table: `site_content`

**Purpose:** Stores editable content for public pages (Home, About, Contact).

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT (PK, auto-increment) | Unique identifier |
| `page` | VARCHAR(50) NOT NULL | Page identifier (`home`, `about`, `contact`) |
| `section` | VARCHAR(100) NOT NULL | Section identifier within the page |
| `content` | TEXT | The editable content (text or image path) |
| `updated_at` | DATETIME | Last update timestamp |

**UNIQUE KEY:** `(page, section)` — one content entry per page/section pair

**Content Sections:**
- **Home:** `hero_heading`, `hero_description`, `hero_image`, `about_title`, `about_description`, `about_image`, `area_image_1` through `area_image_7`, `activities_heading`, `activities_description`
- **About:** `banner_title`, `banner_description`, `banner_image`, `about_description`, `about_image_1`, `about_image_2`, `about_image_3`
- **Contact:** `banner_title`, `banner_description`, `contact_image`, `contact_description`

---

### Table: `password_reset_tokens`

**Purpose:** Stores password reset tokens for admin password reset flow.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT (PK, auto-increment) | Unique identifier |
| `admin_id` | INT (FK -> admins) | The admin requesting reset |
| `token` | VARCHAR(128) NOT NULL UNIQUE | Secure random token |
| `expires_at` | DATETIME NOT NULL | Token expiration (30 minutes from creation) |
| `used` | TINYINT(1) DEFAULT 0 | Whether token has been used |
| `created_at` | DATETIME DEFAULT CURRENT_TIMESTAMP | Record creation timestamp |

**Foreign Keys:** `admin_id` -> `admins(admin_id)` ON DELETE CASCADE

---

### Table: `announcements`

**Purpose:** Stores announcement records used for media gallery reference associations.

| Column | Type | Description |
|--------|------|-------------|
| `announcement_id` | INT (PK, auto-increment) | Unique identifier |
| `message` | TEXT | Announcement content |
| `date_posted` | DATETIME | Posting timestamp |

**Relationships:** Referenced by `media_gallery` (via `reference_type = 'announcement'` and `reference_id`)

---

### Table: `contact_messages`

**Purpose:** Stores contact form submissions from the Contact page.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT (PK, auto-increment) | Unique identifier |
| `name` | VARCHAR(255) NOT NULL | Sender's name |
| `email` | VARCHAR(255) NOT NULL | Sender's email |
| `subject` | VARCHAR(255) NOT NULL | Message subject |
| `message` | TEXT NOT NULL | Message body |
| `submitted_at` | DATETIME NOT NULL | Submission timestamp |
| `is_read` | TINYINT(1) DEFAULT 0 | Whether the message has been read |

**Note:** This table is created automatically by `contact.php` on first request (no migration needed).

---

## Entity Relationship Summary

```
admins (standalone auth)
  │
  ├── reviewed collaborations (via reviewed_by, implicit)
  │
  └── password_reset_tokens (via admin_id)
  │
members (standalone)
  │
  ├── event_polling (via member_id, nullable)
  │
  └── leadership_assignments (via member_id)
  │
rotary_years ─── leadership_assignments (via rotary_year_id)
  │
events ───── event_polling (via event_id)
  │
  └──── event_reports (via event_id)
  │
projects ─── project_reports (via project_id)
  │
collaborations ─── collaboration_reports (via collab_id)
  │
donors ─── donations (via donor_id)
             │
             └── donation_report (via donation_id)
```

## Notes

- The `event_polling` table does not have `poll_enabled` or `certificates_enabled` columns in events
- No `donation_files` table exists in the current implementation
- The `announcements` table exists but has no dedicated admin UI
- `media_gallery` and `gallery_media` are separate tables with different column structures
- All ID columns use auto-increment
- Foreign key relationships use InnoDB engine
- Run `Admin/db_migration.php` to create/update all tables (this creates: `rotary_years`, `leadership_assignments`, `site_content`, `password_reset_tokens`, and alters `admins`)
- `contact_messages` and `announcements` tables are not created by the migration script; they are created dynamically by `contact.php` and may need manual creation or SQL import
- Use `SHOW CREATE TABLE` in MySQL for exact column definitions of the live database
