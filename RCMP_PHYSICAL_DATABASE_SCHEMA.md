# RCMP Physical Database Schema

## Rotary Club of Virar — Management Platform

**Phase:** 2 — Session 6  
**Status:** Physical Schema Design (Implementation of Approved Architecture)  
**Date:** 2026-07-16  
**Source of Truth:** Approved Logical Database (Sessions 1–4.5) + Enterprise ER Diagram (Session 5)

---

## Engineering Governance Principle

Every physical design decision in this document is traceable to an approved logical business rule. For each decision, the following five fields are documented:

1. **Decision** — The physical implementation choice
2. **Business Rule** — The approved logical requirement it implements
3. **Architectural Source** — The session that approved this rule
4. **Reason for Implementation** — Why this physical choice satisfies the rule
5. **Expected Benefit** — What operational value this provides

---

## Global Schema Standards

| Standard | Value | Business Justification |
|----------|-------|----------------------|
| Engine | InnoDB | Required for foreign key support and transactional integrity |
| Charset | utf8mb4 | Supports full Unicode including Hindi/Devanagari names (Indian club context) |
| Collation | utf8mb4_unicode_ci | Case-insensitive comparison for names and text fields |
| ID Strategy | INT AUTO_INCREMENT | Simple, efficient, sufficient for club-scale data volumes |
| Boolean Columns | TINYINT(1) | MySQL has no native BOOLEAN; TINYINT(1) with 0/1 is standard practice |
| Timestamps | DATETIME | Explicit timestamps without timezone ambiguity (IST club operations) |
| Monetary Values | DECIMAL(12,2) | Supports amounts up to ₹9,999,999,999.99 with exact precision |
| Soft Deletes | Not Used | Approved Logical Database uses CASCADE deletes, not soft deletes |

---

## DOMAIN 1: Authentication & Security

### Table 1.1: `admins`

**Entity Type:** System  
**Business Owner:** Super Admin  
**Purpose:** Admin authentication and role management

| Column | MySQL Type | Nullable | Default | Business Rule | Architectural Source |
|--------|-----------|----------|---------|---------------|---------------------|
| `admin_id` | INT | NO | AUTO_INCREMENT | Unique admin identifier | PK convention |
| `name` | VARCHAR(255) | NO | '' | Admin's full name; required for display and audit trail | Business Analysis |
| `email` | VARCHAR(255) | NO | '' | Login credential; must be unique per admin | Business Rule: one account per email |
| `password` | VARCHAR(255) | NO | '' | bcrypt hash via `password_hash()`; 60-char hash + overhead | Security Standard |
| `role` | ENUM('super_admin','President','Secretary','Treasurer','IT Admin') | NO | 'IT Admin' | Fixed set of admin roles; ENUM enforces valid values | Business Architecture |
| `photo_url` | VARCHAR(500) | NO | '' | Path to profile photo; empty string = no photo | Presentation requirement |
| `status` | ENUM('active','inactive') | NO | 'active' | Account status; only 'active' accounts can login | Security Rule |
| `phone` | VARCHAR(20) | NO | '' | Contact phone; optional field | Contact Information |
| `created_at` | DATETIME | NO | CURRENT_TIMESTAMP | When this admin account was created; audit trail | Session 4.5: business event = account creation |

**Constraints:**
- PRIMARY KEY: `admin_id`
- UNIQUE: `email` (one account per email)
- ENUM constraint on `role` (5 values)
- ENUM constraint on `status` (2 values)

**Indexes:**
| Index | Columns | Justification |
|-------|---------|---------------|
| PRIMARY | `admin_id` | Auto PK lookup |
| UNIQUE | `email` | Login queries by email; uniqueness enforcement |

**Delete Rule:** No cascade — admins are referenced by donation_status_history (SET NULL) and collaborations (SET NULL). Admin records are preserved for audit integrity.

---

### Table 1.2: `password_reset_tokens`

**Entity Type:** System  
**Business Owner:** System (auto-generated)  
**Purpose:** Secure password reset token lifecycle

| Column | MySQL Type | Nullable | Default | Business Rule | Architectural Source |
|--------|-----------|----------|---------|---------------|---------------------|
| `id` | INT | NO | AUTO_INCREMENT | Unique token record identifier | PK convention |
| `admin_id` | INT | NO | — | Links token to admin requesting reset | Security Flow |
| `token` | VARCHAR(128) | NO | — | Cryptographically random token; unique per request | Security Standard |
| `expires_at` | DATETIME | NO | — | Token expires 30 minutes from creation | Security Rule: 30-min window |
| `used` | TINYINT(1) | NO | 0 | Whether token has been consumed; single-use enforcement | Security Rule: single use |
| `created_at` | DATETIME | NO | CURRENT_TIMESTAMP | When token was generated; for expiry calculation | System timestamp |

**Constraints:**
- PRIMARY KEY: `id`
- UNIQUE: `token` (one token per request)
- FOREIGN KEY: `admin_id` → `admins(admin_id)` ON DELETE CASCADE

**Indexes:**
| Index | Columns | Justification |
|-------|---------|---------------|
| PRIMARY | `id` | Auto PK lookup |
| UNIQUE | `token` | Token lookup during reset verification; must be unique |
| FK | `admin_id` | Cascade delete when admin is removed |

**Delete Rule:** CASCADE — when an admin account is deleted, their reset tokens are meaningless.

---

## DOMAIN 2: Member & Governance

### Table 2.1: `members`

**Entity Type:** Core Business  
**Business Owner:** Club Secretary  
**Purpose:** Club members displayed on Team and Contact pages

| Column | MySQL Type | Nullable | Default | Business Rule | Architectural Source |
|--------|-----------|----------|---------|---------------|---------------------|
| `member_id` | INT | NO | AUTO_INCREMENT | Unique member identifier | PK convention |
| `name` | VARCHAR(255) | NO | '' | Full name; displayed on website | Business Analysis |
| `email` | VARCHAR(255) | NO | '' | Email address; may be used for contact | Contact Information |
| `phone_number` | VARCHAR(20) | NO | '' | Phone number; optional for public display | Contact Information |
| `address` | TEXT | NO | '' | Residential address; shown in member modal | Contact Information |
| `role` | VARCHAR(100) | NO | 'Member' | Current role title (President/Secretary/Treasurer/Member) | Business Rule: role reflects current assignment |
| `status` | VARCHAR(20) | NO | 'Active' | Active or Inactive; only Active members shown on website | Business Rule: membership status |
| `joined_date` | DATE | NO | '0000-00-00' | Date joined the club; business event timestamp | Business Analysis |
| `photo_url` | VARCHAR(500) | NO | '' | Path to profile photo | Presentation requirement |
| `profession` | VARCHAR(255) | NO | '' | Professional occupation; shown on website | Member Profile |
| `short_bio` | TEXT | NO | '' | Brief biography; shown on member card/modal | Member Profile |
| `display_order` | INT | NO | 0 | Sort order on website; lower = first | Presentation requirement |
| `show_contact` | TINYINT(1) | NO | 0 | Whether to show contact info publicly on website | Privacy Rule |

**Constraints:**
- PRIMARY KEY: `member_id`

**Indexes:**
| Index | Columns | Justification |
|-------|---------|---------------|
| PRIMARY | `member_id` | Auto PK lookup |
| `status` | `status` | Frequent queries: "all active members" for website display |

**Delete Rule:** No CASCADE from members — children use SET NULL or CASCADE on their side.

---

### Table 2.2: `rotary_years`

**Entity Type:** Core Business  
**Business Owner:** Club Secretary  
**Purpose:** Rotary year definitions (July–June cycle)

| Column | MySQL Type | Nullable | Default | Business Rule | Architectural Source |
|--------|-----------|----------|---------|---------------|---------------------|
| `id` | INT | NO | AUTO_INCREMENT | Unique year identifier | PK convention |
| `year_name` | VARCHAR(20) | NO | — | Year label e.g. "2025-26"; human-readable, unique | Business Rule: one record per Rotary year |
| `is_current` | TINYINT(1) | NO | 0 | Whether this is the active Rotary year | Business Rule: single current year |

**Constraints:**
- PRIMARY KEY: `id`
- UNIQUE: `year_name` (one record per year label)

**Note on `created_at`:** Removed per Session 4.5 review. A Rotary Year is defined externally by Rotary International; its creation in the system is not a meaningful business event.

**Indexes:**
| Index | Columns | Justification |
|-------|---------|---------------|
| PRIMARY | `id` | Auto PK lookup |
| UNIQUE | `year_name` | Year lookup by name; uniqueness enforcement |
| `is_current` | `is_current` | Frequent query: "find the current year" |

**Delete Rule:** No cascade from rotary_years — children use CASCADE on their side (leadership_assignments).

---

### Table 2.3: `leadership_assignments`

**Entity Type:** Junction  
**Business Owner:** Club Secretary  
**Purpose:** Maps members to leadership roles per Rotary year

| Column | MySQL Type | Nullable | Default | Business Rule | Architectural Source |
|--------|-----------|----------|---------|---------------|---------------------|
| `id` | INT | NO | AUTO_INCREMENT | Unique assignment identifier | PK convention |
| `rotary_year_id` | INT | NO | — | The Rotary year for this assignment | M:N resolution: members ↔ rotary_years |
| `member_id` | INT | NO | — | The member assigned to this role | M:N resolution: members ↔ rotary_years |
| `role` | VARCHAR(50) | NO | — | Role: President, Secretary, or Treasurer | Business Rule: three officer roles per year |
| `created_at` | DATETIME | NO | CURRENT_TIMESTAMP | When this assignment was recorded; audit trail | Governance Audit |

**Constraints:**
- PRIMARY KEY: `id`
- UNIQUE: `(rotary_year_id, role)` — one person per role per year
- FOREIGN KEY: `rotary_year_id` → `rotary_years(id)` ON DELETE CASCADE
- FOREIGN KEY: `member_id` → `members(member_id)` ON DELETE CASCADE

**Indexes:**
| Index | Columns | Justification |
|-------|---------|---------------|
| PRIMARY | `id` | Auto PK lookup |
| UNIQUE | `(rotary_year_id, role)` | Business rule: one person per role per year |
| FK | `rotary_year_id` | Cascade delete; year-based queries |
| FK | `member_id` | Cascade delete; member-based queries |

**Delete Rule:** CASCADE on both FKs — if a year or member is deleted, assignments are meaningless.

---

### Table 2.4: `committees`

**Entity Type:** Core Business  
**Business Owner:** Club Secretary  
**Purpose:** Committee definitions (Standing, Ad-hoc, District-level)

| Column | MySQL Type | Nullable | Default | Business Rule | Architectural Source |
|--------|-----------|----------|---------|---------------|---------------------|
| `committee_id` | INT | NO | AUTO_INCREMENT | Unique committee identifier | PK convention |
| `committee_name` | VARCHAR(255) | NO | — | Committee name e.g. "Community Service Committee" | Business Rule: named committees |
| `description` | TEXT | NO | '' | Committee purpose/scope description | Committee Governance |
| `rotary_year_id` | INT | NO | — | The Rotary year this committee belongs to | Business Rule: committees are year-scoped |
| `is_active` | TINYINT(1) | NO | 1 | Whether this committee is currently active | Lifecycle Rule |
| `created_at` | DATETIME | NO | CURRENT_TIMESTAMP | When this committee was created; audit trail | Governance Audit |

**Constraints:**
- PRIMARY KEY: `committee_id`
- FOREIGN KEY: `rotary_year_id` → `rotary_years(id)` ON DELETE CASCADE

**Indexes:**
| Index | Columns | Justification |
|-------|---------|---------------|
| PRIMARY | `committee_id` | Auto PK lookup |
| FK | `rotary_year_id` | Cascade delete; year-based committee listing |

**Delete Rule:** CASCADE — if a year is deleted, committees for that year are meaningless.

---

### Table 2.5: `committee_position`

**Entity Type:** Lookup  
**Business Owner:** Super Admin (managed via admin panel)  
**Purpose:** Controlled vocabulary for committee role titles

| Column | MySQL Type | Nullable | Default | Business Rule | Architectural Source |
|--------|-----------|----------|---------|---------------|---------------------|
| `position_id` | INT | NO | AUTO_INCREMENT | Unique position identifier | PK convention |
| `position_name` | VARCHAR(100) | NO | — | Position title e.g. "Chairperson" | Governance: controlled vocabulary |
| `display_order` | INT | NO | 0 | Sort order for admin dropdowns | Presentation: ordered list |
| `is_active` | TINYINT(1) | NO | 1 | Whether this position is currently usable | Lifecycle: soft disable |

**Constraints:**
- PRIMARY KEY: `position_id`
- UNIQUE: `position_name` (one record per position title)

**Seed Data:** Chairperson, Co-Chair, Secretary, Member (per Rotary governance documents)

**Indexes:**
| Index | Columns | Justification |
|-------|---------|---------------|
| PRIMARY | `position_id` | Auto PK lookup |
| UNIQUE | `position_name` | Governance: prevent duplicate position titles |

**Delete Rule:** No CASCADE from committee_position — referenced by committee_members with RESTRICT.

---

### Table 2.6: `committee_members`

**Entity Type:** Junction  
**Business Owner:** Club Secretary  
**Purpose:** Maps members to committee roles per committee

| Column | MySQL Type | Nullable | Default | Business Rule | Architectural Source |
|--------|-----------|----------|---------|---------------|---------------------|
| `id` | INT | NO | AUTO_INCREMENT | Unique assignment identifier | PK convention |
| `committee_id` | INT | NO | — | The committee this member serves on | M:N resolution: committees ↔ members |
| `member_id` | INT | NO | — | The member assigned to this committee | M:N resolution: committees ↔ members |
| `position_id` | INT | NO | — | The position/role within the committee | Governance: standardized position from lookup |
| `joined_date` | DATE | NO | '0000-00-00' | When this member joined the committee | Governance: historical tracking |
| `created_at` | DATETIME | NO | CURRENT_TIMESTAMP | When this assignment was recorded; audit trail | Governance Audit |

**Constraints:**
- PRIMARY KEY: `id`
- UNIQUE: `(committee_id, member_id)` — one membership per member per committee
- FOREIGN KEY: `committee_id` → `committees(committee_id)` ON DELETE CASCADE
- FOREIGN KEY: `member_id` → `members(member_id)` ON DELETE CASCADE
- FOREIGN KEY: `position_id` → `committee_position(position_id)` ON DELETE RESTRICT

**Indexes:**
| Index | Columns | Justification |
|-------|---------|---------------|
| PRIMARY | `id` | Auto PK lookup |
| UNIQUE | `(committee_id, member_id)` | Business rule: one membership per member per committee |
| FK | `committee_id` | Cascade delete; committee member listing |
| FK | `member_id` | Cascade delete; member's committee assignments |
| FK | `position_id` | RESTRICT delete; prevent deleting in-use positions |

**Delete Rule:** CASCADE on committee_id and member_id; RESTRICT on position_id (cannot delete a position that is in use).

---

## DOMAIN 3: Events

### Table 3.1: `events`

**Entity Type:** Core Business  
**Business Owner:** Club Secretary  
**Purpose:** Club events displayed on Activities and Home pages

| Column | MySQL Type | Nullable | Default | Business Rule | Architectural Source |
|--------|-----------|----------|---------|---------------|---------------------|
| `event_id` | INT | NO | AUTO_INCREMENT | Unique event identifier | PK convention |
| `title` | VARCHAR(255) | NO | '' | Event title | Business Analysis |
| `description` | TEXT | NO | '' | Event description; may be long-form | Business Analysis |
| `start_date` | DATETIME | NO | '0000-00-00 00:00:00' | Event start date/time; business timestamp | Business Rule: event scheduling |
| `end_date` | DATETIME | NO | '0000-00-00 00:00:00' | Event end date/time; used for status computation | Business Rule: event duration |
| `location` | VARCHAR(255) | NO | '' | Event venue/location | Business Analysis |
| `category` | VARCHAR(100) | NO | '' | Event category for filtering | Presentation: category filter |
| `image_url` | VARCHAR(500) | NO | '' | Path to event hero image | Presentation requirement |
| `rotary_year_id` | INT | NULL | NULL | Optional link to Rotary year; single FK per Session 4.5 | Session 4.5: Simplified from junction table |

**Constraints:**
- PRIMARY KEY: `event_id`
- FOREIGN KEY: `rotary_year_id` → `rotary_years(id)` ON DELETE SET NULL

**Note:** `rotary_year_id` is nullable. Events may exist without a year link (e.g., entered without date context). SET NULL preserves the event if the year is deleted.

**Indexes:**
| Index | Columns | Justification |
|-------|---------|---------------|
| PRIMARY | `event_id` | Auto PK lookup |
| `rotary_year_id` | `rotary_year_id` | Year-based event filtering (Activities page) |
| `start_date` | `start_date` | Date-range queries for upcoming/completed events |

**Delete Rule:** No CASCADE from events — children use CASCADE on their side (event_polling, event_reports).

---

### Table 3.2: `event_polling`

**Entity Type:** Junction  
**Business Owner:** Club Secretary  
**Purpose:** Stores RSVP/voting responses for events

| Column | MySQL Type | Nullable | Default | Business Rule | Architectural Source |
|--------|-----------|----------|---------|---------------|---------------------|
| `poll_id` | INT | NO | AUTO_INCREMENT | Unique response identifier | PK convention |
| `event_id` | INT | NO | — | The event being responded to | M:N resolution: events ↔ members |
| `member_id` | INT | NULL | NULL | Voting member; nullable for guest responses | Business Rule: guest RSVP support |
| `is_attending` | VARCHAR(10) | NO | — | Response: Yes, No, or Maybe | Business Rule: three-state RSVP |
| `guest_name` | VARCHAR(255) | NO | '' | Guest name if not a member | Business Rule: non-member guests |
| `submitted_on` | DATETIME | NO | '0000-00-00 00:00:00' | When the response was submitted; business event | Business Event: response timestamp |

**Constraints:**
- PRIMARY KEY: `poll_id`
- FOREIGN KEY: `event_id` → `events(event_id)` ON DELETE CASCADE
- FOREIGN KEY: `member_id` → `members(member_id)` ON DELETE SET NULL

**Indexes:**
| Index | Columns | Justification |
|-------|---------|---------------|
| PRIMARY | `poll_id` | Auto PK lookup |
| FK | `event_id` | Cascade delete; event-based polling results |
| FK | `member_id` | SET NULL; member-based response history |
| `(event_id, member_id)` | Composite | Prevent duplicate responses per member per event (application-level enforcement) |

**Delete Rule:** CASCADE on event_id (event deleted = polling meaningless); SET NULL on member_id (member deleted = response preserved as anonymous).

---

### Table 3.3: `event_reports`

**Entity Type:** Report  
**Business Owner:** Club Secretary  
**Purpose:** Post-event reports and documentation

| Column | MySQL Type | Nullable | Default | Business Rule | Architectural Source |
|--------|-----------|----------|---------|---------------|---------------------|
| `report_id` | INT | NO | AUTO_INCREMENT | Unique report identifier | PK convention |
| `event_id` | INT | NO | — | The event being reported on | Report links to parent entity |
| `details` | TEXT | NO | '' | Report content; may be extensive | Business Rule: event documentation |
| `submitted_by` | VARCHAR(255) | NO | '' | Name/ID of report submitter | Audit: who filed the report |
| `date` | DATETIME | NO | '0000-00-00 00:00:00' | Report submission date; business event | Business Event: report filed |

**Constraints:**
- PRIMARY KEY: `report_id`
- FOREIGN KEY: `event_id` → `events(event_id)` ON DELETE CASCADE

**Indexes:**
| Index | Columns | Justification |
|-------|---------|---------------|
| PRIMARY | `report_id` | Auto PK lookup |
| FK | `event_id` | Cascade delete; event-based report lookup |

**Delete Rule:** CASCADE — if an event is deleted, its reports are meaningless.

---

## DOMAIN 4: Projects

### Table 4.1: `projects`

**Entity Type:** Core Business  
**Business Owner:** Club Secretary  
**Purpose:** Club projects displayed on Activities and Home pages

| Column | MySQL Type | Nullable | Default | Business Rule | Architectural Source |
|--------|-----------|----------|---------|---------------|---------------------|
| `project_id` | INT | NO | AUTO_INCREMENT | Unique project identifier | PK convention |
| `title` | VARCHAR(255) | NO | '' | Project title | Business Analysis |
| `description` | TEXT | NO | '' | Project description; may be long-form | Business Analysis |
| `start_date` | DATE | NO | '0000-00-00' | Project start date | Business Rule: project timeline |
| `end_date` | DATE | NO | '0000-00-00' | Project end date | Business Rule: project duration |
| `status` | VARCHAR(20) | NO | 'Upcoming' | Project status: Upcoming, Ongoing, or Completed | Business Rule: project lifecycle |
| `image_url` | VARCHAR(500) | NO | '' | Path to project hero image | Presentation requirement |
| `collaborator` | VARCHAR(255) | NO | '' | Collaborating organization/individual | Business Analysis |
| `created_at` | DATETIME | NO | CURRENT_TIMESTAMP | When this project was entered; audit trail | Admin Audit |
| `rotary_year_id` | INT | NULL | NULL | Optional link to Rotary year; single FK per Session 4.5 | Session 4.5: Simplified from junction table |

**Constraints:**
- PRIMARY KEY: `project_id`
- FOREIGN KEY: `rotary_year_id` → `rotary_years(id)` ON DELETE SET NULL

**Note:** `rotary_year_id` is nullable. Projects may exist without a year link. SET NULL preserves the project if the year is deleted.

**Indexes:**
| Index | Columns | Justification |
|-------|---------|---------------|
| PRIMARY | `project_id` | Auto PK lookup |
| `rotary_year_id` | `rotary_year_id` | Year-based project filtering |
| `status` | `status` | Status-based filtering (Upcoming/Ongoing/Completed) |

**Delete Rule:** No CASCADE from projects — children use CASCADE on their side (project_reports).

---

### Table 4.2: `project_reports`

**Entity Type:** Report  
**Business Owner:** Club Secretary  
**Purpose:** Project completion reports with financial tracking

| Column | MySQL Type | Nullable | Default | Business Rule | Architectural Source |
|--------|-----------|----------|---------|---------------|---------------------|
| `project_id` | INT | NO | — | The project being reported on; also PK | Report links to parent; 1:1 or 1:N |
| `year` | INT | NO | 0 | Report year (e.g. 2025) | Business Rule: annual project reporting |
| `summary` | TEXT | NO | '' | Project summary narrative | Business Analysis |
| `funds_raised` | DECIMAL(12,2) | NO | 0.00 | Total funds raised for the project | Financial Tracking |
| `expenditure` | DECIMAL(12,2) | NO | 0.00 | Total expenditure on the project | Financial Tracking |
| `achievements` | TEXT | NO | '' | Key achievements and outcomes | Business Analysis |
| `created_at` | DATETIME | NO | CURRENT_TIMESTAMP | When this report was created; audit trail | Admin Audit |

**Constraints:**
- PRIMARY KEY: `project_id` (composite with `year` — see note)
- FOREIGN KEY: `project_id` → `projects(project_id)` ON DELETE CASCADE

**Note:** The current schema uses `project_id` as both PK and FK. For annual reporting (multiple reports per project per year), the PK should be a composite of `(project_id, year)`. This is a physical schema decision that implements the approved logical rule "a project may have 0 or more annual reports."

**Corrected Primary Key:** `PRIMARY KEY (project_id, year)`

**Indexes:**
| Index | Columns | Justification |
|-------|---------|---------------|
| PRIMARY | `(project_id, year)` | Composite PK: one report per project per year |
| FK | `project_id` | Cascade delete; project-based report lookup |

**Delete Rule:** CASCADE — if a project is deleted, its reports are meaningless.

---

## DOMAIN 5: Donations

### Table 5.1: `donors`

**Entity Type:** Core Business  
**Business Owner:** Club Treasurer  
**Purpose:** Donor information from donation form

| Column | MySQL Type | Nullable | Default | Business Rule | Architectural Source |
|--------|-----------|----------|---------|---------------|---------------------|
| `donor_id` | INT | NO | AUTO_INCREMENT | Unique donor identifier | PK convention |
| `name` | VARCHAR(255) | NO | '' | Donor's full name; required | Business Rule: donor identification |
| `phone_number` | VARCHAR(20) | NO | '' | Contact phone | Contact Information |
| `email` | VARCHAR(255) | NO | '' | Email address; used for donation status emails | Communication: email notifications |
| `social_role` | VARCHAR(100) | NO | '' | Social/professional role; optional | Donor Profile |
| `address` | TEXT | NO | '' | Residential/business address | Donor Profile |
| `occupation` | VARCHAR(255) | NO | '' | Occupation | Donor Profile |
| `created_at` | DATETIME | NO | CURRENT_TIMESTAMP | When donor record was created; first contact date | Business Event: first contact |

**Constraints:**
- PRIMARY KEY: `donor_id`

**Indexes:**
| Index | Columns | Justification |
|-------|---------|---------------|
| PRIMARY | `donor_id` | Auto PK lookup |

**Delete Rule:** No CASCADE from donors — children use CASCADE on their side (donations).

---

### Table 5.2: `donations`

**Entity Type:** Core Business  
**Business Owner:** Club Treasurer  
**Purpose:** Donation records linked to donors

| Column | MySQL Type | Nullable | Default | Business Rule | Architectural Source |
|--------|-----------|----------|---------|---------------|---------------------|
| `donation_id` | INT | NO | AUTO_INCREMENT | Unique donation identifier | PK convention |
| `donor_id` | INT | NO | — | The donor who made this donation | Business Rule: every donation has a donor |
| `donation_type` | VARCHAR(50) | NO | '' | Donation type: Monetary, School Supplies, Food & Grocery, etc. | Business Rule: categorized donations |
| `description` | TEXT | NO | '' | Description; primarily for in-kind donations | Business Analysis |
| `amount` | DECIMAL(12,2) | NULL | NULL | Monetary amount; NULL for non-monetary donations | Business Rule: monetary vs in-kind |
| `utr_number` | VARCHAR(100) | NO | '' | UTR / transaction reference; for monetary donations | Financial: payment tracking |
| `screenshot_path` | VARCHAR(500) | NO | '' | Path to payment screenshot; proof of donation | Verification: payment evidence |
| `pickup_option` | VARCHAR(50) | NO | '' | JSON: Self Delivery or Arrange Pickup with details | Logistics: delivery coordination |
| `status` | VARCHAR(30) | NO | 'Pending Verification' | Pipeline status | Business Rule: donation lifecycle |
| `date` | DATETIME | NO | '0000-00-00 00:00:00' | When the donation was made; business event | Business Event: donation timestamp |
| `status_updated_by` | VARCHAR(255) | NO | '' | Admin name who last updated status | Audit: who processed |
| `status_updated_role` | VARCHAR(50) | NULL | NULL | Admin role of person who updated status | Audit: role context |

**Constraints:**
- PRIMARY KEY: `donation_id`
- FOREIGN KEY: `donor_id` → `donors(donor_id)` ON DELETE CASCADE

**Indexes:**
| Index | Columns | Justification |
|-------|---------|---------------|
| PRIMARY | `donation_id` | Auto PK lookup |
| FK | `donor_id` | Cascade delete; donor-based donation listing |
| `status` | `status` | Pipeline filtering (Pending/Contacted/Completed) |
| `date` | `date` | Date-range reporting queries |

**Delete Rule:** CASCADE — if a donor is deleted, their donation records follow.

---

### Table 5.3: `donation_status_history`

**Entity Type:** Audit  
**Business Owner:** Club Treasurer  
**Purpose:** Audit trail of donation pipeline status changes

| Column | MySQL Type | Nullable | Default | Business Rule | Architectural Source |
|--------|-----------|----------|---------|---------------|---------------------|
| `history_id` | INT | NO | AUTO_INCREMENT | Unique history record identifier | PK convention |
| `donation_id` | INT | NO | — | The donation whose status changed | Audit: links to parent |
| `previous_status` | VARCHAR(50) | NULL | NULL | Status before change; NULL for initial record | Audit: before-state |
| `new_status` | VARCHAR(50) | NO | — | Status after change | Audit: after-state |
| `admin_id` | INT | NULL | NULL | Admin who made the change; nullable if deleted | Audit: who changed |
| `admin_name` | VARCHAR(255) | NULL | NULL | Admin name (denormalized for display after admin deletion) | Audit: display name |
| `admin_role` | VARCHAR(50) | NULL | NULL | Admin role at time of change | Audit: role context |
| `remarks` | TEXT | NULL | NULL | Internal remarks about this status change | Audit: operational notes |
| `updated_at` | DATETIME | NO | CURRENT_TIMESTAMP | When this status change was recorded; business event | Business Event: status transition |

**Constraints:**
- PRIMARY KEY: `history_id`
- FOREIGN KEY: `donation_id` → `donations(donation_id)` ON DELETE CASCADE
- FOREIGN KEY: `admin_id` → `admins(admin_id)` ON DELETE SET NULL

**Indexes:**
| Index | Columns | Justification |
|-------|---------|---------------|
| PRIMARY | `history_id` | Auto PK lookup |
| FK | `donation_id` | Cascade delete; donation-based history timeline |
| FK | `admin_id` | SET NULL; admin-based activity log |

**Delete Rule:** CASCADE on donation_id (donation deleted = history meaningless); SET NULL on admin_id (admin deleted = history preserved for audit).

---

### Table 5.4: `donation_allocation`

**Entity Type:** Junction (Optional)  
**Business Owner:** Club Treasurer  
**Purpose:** Optional allocation of donations to specific projects, events, or fund purposes

| Column | MySQL Type | Nullable | Default | Business Rule | Architectural Source |
|--------|-----------|----------|---------|---------------|---------------------|
| `allocation_id` | INT | NO | AUTO_INCREMENT | Unique allocation identifier | PK convention |
| `donation_id` | INT | NO | — | The donation being allocated | Business Rule: allocation belongs to a donation |
| `project_id` | INT | NULL | NULL | Target project; NULL if not project-specific | Session 4.5: optional allocation target |
| `event_id` | INT | NULL | NULL | Target event; NULL if not event-specific | Session 4.5: optional allocation target |
| `fund_purpose` | VARCHAR(255) | NO | '' | Free-text purpose (e.g. "General Fund", "Medical Camp 2025") | Business Rule: unrestricted/split donations |
| `amount` | DECIMAL(12,2) | NO | 0.00 | Portion of donation allocated to this purpose | Business Rule: split allocation support |
| `allocated_at` | DATETIME | NO | CURRENT_TIMESTAMP | When this allocation was made; business event | Business Event: allocation decision |
| `allocated_by` | INT | NULL | NULL | Admin who made the allocation; nullable if deleted | Audit: who allocated |

**Constraints:**
- PRIMARY KEY: `allocation_id`
- FOREIGN KEY: `donation_id` → `donations(donation_id)` ON DELETE CASCADE
- FOREIGN KEY: `project_id` → `projects(project_id)` ON DELETE SET NULL
- FOREIGN KEY: `event_id` → `events(event_id)` ON DELETE SET NULL
- FOREIGN KEY: `allocated_by` → `admins(admin_id)` ON DELETE SET NULL

**Business Rules Enforced:**
- A donation with 0 allocation records = unrestricted donation
- A donation with 1 allocation record = fully restricted
- A donation with multiple allocation records = split donation
- `project_id` and `event_id` are both nullable — allocation can be to a named fund purpose without a specific project/event link

**Indexes:**
| Index | Columns | Justification |
|-------|---------|---------------|
| PRIMARY | `allocation_id` | Auto PK lookup |
| FK | `donation_id` | Cascade delete; donation-based allocation listing |
| FK | `project_id` | SET NULL; project-based allocation reporting |
| FK | `event_id` | SET NULL; event-based allocation reporting |

**Delete Rule:** CASCADE on donation_id; SET NULL on project_id, event_id, and allocated_by.

---

### Table 5.5: `donation_report`

**Entity Type:** Report  
**Business Owner:** Club Treasurer  
**Purpose:** Donation verification and processing reports

| Column | MySQL Type | Nullable | Default | Business Rule | Architectural Source |
|--------|-----------|----------|---------|---------------|---------------------|
| `report_id` | INT | NO | AUTO_INCREMENT | Unique report identifier | PK convention |
| `donation_id` | INT | NO | — | The donation being reported on | Report links to parent entity |
| `report_summary` | TEXT | NO | '' | Report content/summary | Business Analysis |
| `verified_by` | VARCHAR(255) | NO | '' | Admin name who verified the donation | Audit: verification trail |
| `created_on` | DATETIME | NO | '0000-00-00 00:00:00' | Report creation timestamp; business event | Business Event: report filed |

**Constraints:**
- PRIMARY KEY: `report_id`
- FOREIGN KEY: `donation_id` → `donations(donation_id)` ON DELETE CASCADE

**Indexes:**
| Index | Columns | Justification |
|-------|---------|---------------|
| PRIMARY | `report_id` | Auto PK lookup |
| FK | `donation_id` | Cascade delete; donation-based report lookup |

**Delete Rule:** CASCADE — if a donation is deleted, its reports are meaningless.

---

## DOMAIN 6: Website & Content

### Table 6.1: `website_display`

**Entity Type:** Display/Presentation  
**Business Owner:** Web Admin / Club Secretary  
**Purpose:** Presentation metadata for website team display

| Column | MySQL Type | Nullable | Default | Business Rule | Architectural Source |
|--------|-----------|----------|---------|---------------|---------------------|
| `id` | INT | NO | AUTO_INCREMENT | Unique record identifier | PK convention |
| `member_id` | INT | NO | — | The member this display record is for | Session 4.5: display metadata per member |
| `display_group` | ENUM('leadership','committee','board','hidden') | NO | 'board' | Which group this member appears in on the website | Session 4.5: display grouping |
| `display_order` | INT | NO | 0 | Sort order within the group; lower = first | Presentation: ordered display |
| `custom_title` | VARCHAR(255) | NULL | NULL | Override title; NULL = use computed role from leadership_assignments | Session 4.5: display-only metadata |
| `is_visible` | TINYINT(1) | NO | 1 | Whether this member appears on the website | Presentation: visibility toggle |

**Constraints:**
- PRIMARY KEY: `id`
- UNIQUE: `member_id` (one display record per member)
- FOREIGN KEY: `member_id` → `members(member_id)` ON DELETE CASCADE
- ENUM constraint on `display_group` (4 values)

**Note:** This table stores ONLY presentation metadata. It does NOT store role information, committee membership, or leadership assignments. Those come from `leadership_assignments` and `committee_members` respectively.

**Indexes:**
| Index | Columns | Justification |
|-------|---------|---------------|
| PRIMARY | `id` | Auto PK lookup |
| UNIQUE | `member_id` | One display record per member; fast lookup for website rendering |
| `display_group` | `display_group` | Website rendering: filter by group |
| `(display_group, display_order)` | Composite | Website rendering: ordered listing within a group |

**Delete Rule:** CASCADE — if a member is deleted, their display metadata is meaningless.

---

### Table 6.2: `site_content`

**Entity Type:** CMS  
**Business Owner:** Web Admin  
**Purpose:** CMS key-value content for Home, About, Contact, Footer pages

| Column | MySQL Type | Nullable | Default | Business Rule | Architectural Source |
|--------|-----------|----------|---------|---------------|---------------------|
| `id` | INT | NO | AUTO_INCREMENT | Unique content identifier | PK convention |
| `page` | VARCHAR(50) | NO | — | Page identifier: home, about, contact, footer, settings, contact_social | Business Rule: page-scoped content |
| `section` | VARCHAR(100) | NO | — | Section identifier within the page | Business Rule: section-scoped content |
| `content` | TEXT | NULL | NULL | The editable content (text or image path) | CMS: key-value storage |
| `updated_at` | DATETIME | NO | CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | Last update timestamp; auto-maintained | Business Event: content edit |

**Constraints:**
- PRIMARY KEY: `id`
- UNIQUE: `(page, section)` — one content entry per page/section pair

**Indexes:**
| Index | Columns | Justification |
|-------|---------|---------------|
| PRIMARY | `id` | Auto PK lookup |
| UNIQUE | `(page, section)` | CMS lookup: get all content for a page; enforce uniqueness |

**Delete Rule:** No FK relationships — standalone CMS table.

---

### Table 6.3: `media_gallery`

**Entity Type:** Display/Presentation  
**Business Owner:** Web Admin  
**Purpose:** Primary media gallery for frontend display and report media associations

| Column | MySQL Type | Nullable | Default | Business Rule | Architectural Source |
|--------|-----------|----------|---------|---------------|---------------------|
| `media_id` | INT | NO | AUTO_INCREMENT | Unique media identifier | PK convention |
| `reference_type` | VARCHAR(50) | NO | '' | Linked entity type: event, project, announcement, or empty | Polymorphic: multi-entity association |
| `reference_id` | INT | NO | 0 | Linked entity ID; 0 = standalone media | Polymorphic: entity reference |
| `title` | VARCHAR(255) | NO | '' | Media title | Presentation |
| `description` | TEXT | NO | '' | Media description/caption | Presentation |
| `media_type` | VARCHAR(20) | NO | 'image' | Media type: currently only 'image' supported | Media: type classification |
| `media_path` | VARCHAR(500) | NO | '' | Relative path to media file | File storage: upload path |
| `media_date` | VARCHAR(100) | NO | '' | Associated date; stored as string for flexibility | Presentation: date label |
| `uploaded_by` | VARCHAR(255) | NO | '' | Admin name who uploaded | Audit: upload attribution |
| `status` | VARCHAR(20) | NO | 'active' | Media status: active or inactive | Lifecycle: visibility toggle |
| `created_at` | DATETIME | NO | CURRENT_TIMESTAMP | When this media was uploaded; business event | Business Event: upload timestamp |

**Constraints:**
- PRIMARY KEY: `media_id`

**Note on Polymorphic Association:** `reference_type` + `reference_id` implement a polymorphic relationship. This is NOT a formal FK — it is an application-level reference. MySQL does not enforce FK across polymorphic boundaries. The application must ensure referential integrity.

**Indexes:**
| Index | Columns | Justification |
|-------|---------|---------------|
| PRIMARY | `media_id` | Auto PK lookup |
| `(reference_type, reference_id)` | Composite | Polymorphic lookup: "all media for event X" |
| `status` | `status` | Filtering: show only active media on frontend |

**Delete Rule:** No FK — polymorphic references are application-managed.

---

### Table 6.4: `gallery_media`

**Entity Type:** Display/Presentation  
**Business Owner:** Web Admin  
**Purpose:** Secondary gallery table for admin-side gallery uploads

| Column | MySQL Type | Nullable | Default | Business Rule | Architectural Source |
|--------|-----------|----------|---------|---------------|---------------------|
| `id` | INT | NO | AUTO_INCREMENT | Unique media identifier | PK convention |
| `title` | VARCHAR(255) | NO | '' | Media title | Presentation |
| `caption` | TEXT | NO | '' | Media caption | Presentation |
| `media_type` | VARCHAR(20) | NO | 'image' | Media type: image or video | Media: type classification |
| `category` | VARCHAR(100) | NO | '' | Gallery category for filtering | Presentation: category grouping |
| `media_url` | VARCHAR(500) | NO | '' | URL/path to media file | File storage: upload path |
| `thumbnail_url` | VARCHAR(500) | NO | '' | Thumbnail path for grid display | Presentation: thumbnail |
| `uploaded_by` | VARCHAR(255) | NO | '' | Admin name who uploaded | Audit: upload attribution |
| `upload_date` | DATETIME | NO | CURRENT_TIMESTAMP | When this media was uploaded; business event | Business Event: upload timestamp |

**Constraints:**
- PRIMARY KEY: `id`

**Note:** This table is a legacy secondary gallery table. It coexists with `media_gallery` for backward compatibility. Future development should consolidate to `media_gallery`.

**Indexes:**
| Index | Columns | Justification |
|-------|---------|---------------|
| PRIMARY | `id` | Auto PK lookup |
| `category` | `category` | Category-based gallery filtering |

**Delete Rule:** No FK relationships — standalone table.

---

### Table 6.5: `contact_messages`

**Entity Type:** Standalone Content  
**Business Owner:** Club Secretary  
**Purpose:** Contact form submissions from public website

| Column | MySQL Type | Nullable | Default | Business Rule | Architectural Source |
|--------|-----------|----------|---------|---------------|---------------------|
| `id` | INT | NO | AUTO_INCREMENT | Unique message identifier | PK convention |
| `name` | VARCHAR(255) | NO | — | Sender's name; required | Business Rule: contact form fields |
| `email` | VARCHAR(255) | NO | — | Sender's email; required | Business Rule: contact form fields |
| `subject` | VARCHAR(255) | NO | — | Message subject; required | Business Rule: contact form fields |
| `message` | TEXT | NO | — | Message body; required | Business Rule: contact form fields |
| `submitted_at` | DATETIME | NO | CURRENT_TIMESTAMP | When the message was sent; business event | Business Event: message received |
| `is_read` | TINYINT(1) | NO | 0 | Whether admin has read this message | Lifecycle: read/unread tracking |

**Constraints:**
- PRIMARY KEY: `id`

**Indexes:**
| Index | Columns | Justification |
|-------|---------|---------------|
| PRIMARY | `id` | Auto PK lookup |
| `is_read` | `is_read` | Admin inbox: filter unread messages |

**Delete Rule:** No FK relationships — standalone form submission table.

---

### Table 6.6: `announcements`

**Entity Type:** Standalone Content  
**Business Owner:** Club Secretary  
**Purpose:** Announcement records for media gallery reference associations

| Column | MySQL Type | Nullable | Default | Business Rule | Architectural Source |
|--------|-----------|----------|---------|---------------|---------------------|
| `announcement_id` | INT | NO | AUTO_INCREMENT | Unique announcement identifier | PK convention |
| `message` | TEXT | NO | '' | Announcement content | Business Analysis |
| `date_posted` | DATETIME | NO | '0000-00-00 00:00:00' | When the announcement was posted; business event | Business Event: announcement published |

**Constraints:**
- PRIMARY KEY: `announcement_id`

**Indexes:**
| Index | Columns | Justification |
|-------|---------|---------------|
| PRIMARY | `announcement_id` | Auto PK lookup |

**Delete Rule:** No FK relationships — referenced polymorphically by media_gallery.

---

## DOMAIN 7: Collaboration

### Table 7.1: `collaborations`

**Entity Type:** Core Business  
**Business Owner:** Club Secretary  
**Purpose:** Collaboration/submission proposals from public website

| Column | MySQL Type | Nullable | Default | Business Rule | Architectural Source |
|--------|-----------|----------|---------|---------------|---------------------|
| `collab_id` | INT | NO | AUTO_INCREMENT | Unique collaboration identifier | PK convention |
| `name` | VARCHAR(255) | NO | '' | Proposer's name | Business Rule: proposal requires identity |
| `email` | VARCHAR(255) | NO | '' | Proposer's email | Contact: follow-up communication |
| `proposal` | TEXT | NO | '' | Proposal details; may be extensive | Business Rule: proposal content |
| `submitted_on` | DATETIME | NO | '0000-00-00 00:00:00' | When the proposal was submitted; business event | Business Event: submission timestamp |
| `status` | VARCHAR(20) | NO | 'Pending' | Proposal status: Pending, Approved, or Rejected | Business Rule: review lifecycle |
| `reviewed_by` | INT | NULL | NULL | Admin ID who reviewed; nullable if not yet reviewed | Audit: reviewer identification |
| `reviewed_at` | DATETIME | NULL | NULL | When the review was completed; nullable if not yet reviewed | Business Event: review timestamp |

**Constraints:**
- PRIMARY KEY: `collab_id`
- FOREIGN KEY: `reviewed_by` → `admins(admin_id)` ON DELETE SET NULL

**Indexes:**
| Index | Columns | Justification |
|-------|---------|---------------|
| PRIMARY | `collab_id` | Auto PK lookup |
| `status` | `status` | Filtering: Pending/Approved/Rejected views |
| FK | `reviewed_by` | SET NULL; admin-based review history |

**Delete Rule:** SET NULL on reviewed_by (admin deleted = proposal preserved, reviewer anonymized).

---

### Table 7.2: `collaboration_reports`

**Entity Type:** Report  
**Business Owner:** Club Secretary  
**Purpose:** Collaboration proposal review reports

| Column | MySQL Type | Nullable | Default | Business Rule | Architectural Source |
|--------|-----------|----------|---------|---------------|---------------------|
| `report_id` | INT | NO | AUTO_INCREMENT | Unique report identifier | PK convention |
| `collab_id` | INT | NO | — | The collaboration being reported on | Report links to parent entity |
| `report_summary` | TEXT | NO | '' | Report content/summary | Business Analysis |

**Constraints:**
- PRIMARY KEY: `report_id`
- FOREIGN KEY: `collab_id` → `collaborations(collab_id)` ON DELETE CASCADE

**Indexes:**
| Index | Columns | Justification |
|-------|---------|---------------|
| PRIMARY | `report_id` | Auto PK lookup |
| FK | `collab_id` | Cascade delete; collaboration-based report lookup |

**Delete Rule:** CASCADE — if a collaboration is deleted, its reports are meaningless.

---

## DOMAIN 8: Derived View

### View 8.1: `website_team_view`

**Entity Type:** Derived View  
**Business Owner:** Web Admin / Club Secretary  
**Purpose:** Unified view of who appears on the website team page, per Rotary year

**Business Rule (Session 4.5):** The Website Team is computed from three independent sources of truth:
1. `leadership_assignments` — who holds what leadership role per year
2. `committee_members` — who serves on which committee per year
3. `website_display` — how each member appears on the website

**Architecture Source:** Session 4.5 approved that the Website Team should NOT be a standalone junction table.

**SQL Definition:**

```sql
CREATE OR REPLACE VIEW website_team_view AS
-- LEADERSHIP GROUP
SELECT
    'leadership' AS display_group,
    la.rotary_year_id,
    m.member_id,
    m.name,
    m.email,
    m.phone_number,
    m.photo_url,
    m.short_bio,
    m.profession,
    la.role AS computed_role,
    COALESCE(wd.custom_title, la.role) AS display_title,
    COALESCE(wd.display_order, FIELD(la.role, 'President', 'Secretary', 'Treasurer') * 100) AS sort_order,
    COALESCE(wd.is_visible, 1) AS is_visible
FROM leadership_assignments la
JOIN members m ON la.member_id = m.member_id
LEFT JOIN website_display wd ON m.member_id = wd.member_id

UNION ALL

-- COMMITTEE GROUP
SELECT
    'committee' AS display_group,
    c.rotary_year_id,
    m.member_id,
    m.name,
    m.email,
    m.phone_number,
    m.photo_url,
    m.short_bio,
    m.profession,
    cp.position_name AS computed_role,
    COALESCE(wd.custom_title, cp.position_name) AS display_title,
    COALESCE(wd.display_order, 500 + cm.id) AS sort_order,
    COALESCE(wd.is_visible, 1) AS is_visible
FROM committee_members cm
JOIN committees c ON cm.committee_id = c.committee_id
JOIN members m ON cm.member_id = m.member_id
JOIN committee_position cp ON cm.position_id = cp.position_id
LEFT JOIN website_display wd ON m.member_id = wd.member_id

UNION ALL

-- BOARD GROUP
SELECT
    'board' AS display_group,
    NULL AS rotary_year_id,
    m.member_id,
    m.name,
    m.email,
    m.phone_number,
    m.photo_url,
    m.short_bio,
    m.profession,
    'Board Member' AS computed_role,
    COALESCE(wd.custom_title, 'Board Member') AS display_title,
    COALESCE(wd.display_order, 1000) AS sort_order,
    COALESCE(wd.is_visible, 1) AS is_visible
FROM members m
LEFT JOIN website_display wd ON m.member_id = wd.member_id
WHERE m.status = 'Active'
  AND wd.display_group = 'board';
```

**Business Benefit:** Single query for the Team page. Application code filters by `rotary_year_id` and `is_visible`, then sorts by `sort_order`. No redundant data. No manual sync needed.

---

## COMPLETE INDEX STRATEGY

### Index Decision Documentation

Every index in this schema has a documented business justification. No indexes are added for theoretical flexibility.

| # | Table | Index | Columns | Business Justification |
|---|-------|-------|---------|----------------------|
| I01 | admins | PRIMARY | admin_id | PK lookup (automatic) |
| I02 | admins | UNIQUE | email | Login by email; uniqueness enforcement |
| I03 | password_reset_tokens | PRIMARY | id | PK lookup (automatic) |
| I04 | password_reset_tokens | UNIQUE | token | Token verification during reset |
| I05 | password_reset_tokens | FK | admin_id | Cascade delete |
| I06 | members | PRIMARY | member_id | PK lookup (automatic) |
| I07 | members | IDX | status | Frequent "active members" queries |
| I08 | rotary_years | PRIMARY | id | PK lookup (automatic) |
| I09 | rotary_years | UNIQUE | year_name | Year lookup by name |
| I10 | rotary_years | IDX | is_current | Frequent "find current year" query |
| I11 | leadership_assignments | PRIMARY | id | PK lookup (automatic) |
| I12 | leadership_assignments | UNIQUE | (rotary_year_id, role) | One person per role per year |
| I13 | leadership_assignments | FK | rotary_year_id | Cascade delete |
| I14 | leadership_assignments | FK | member_id | Cascade delete |
| I15 | committees | PRIMARY | committee_id | PK lookup (automatic) |
| I16 | committees | FK | rotary_year_id | Cascade delete |
| I17 | committee_position | PRIMARY | position_id | PK lookup (automatic) |
| I18 | committee_position | UNIQUE | position_name | Prevent duplicate positions |
| I19 | committee_members | PRIMARY | id | PK lookup (automatic) |
| I20 | committee_members | UNIQUE | (committee_id, member_id) | One membership per member per committee |
| I21 | committee_members | FK | committee_id | Cascade delete |
| I22 | committee_members | FK | member_id | Cascade delete |
| I23 | committee_members | FK | position_id | RESTRICT delete |
| I24 | website_display | PRIMARY | id | PK lookup (automatic) |
| I25 | website_display | UNIQUE | member_id | One display record per member |
| I26 | website_display | IDX | display_group | Website rendering: filter by group |
| I27 | website_display | IDX | (display_group, display_order) | Ordered listing within a group |
| I28 | events | PRIMARY | event_id | PK lookup (automatic) |
| I29 | events | FK | rotary_year_id | Year-based filtering |
| I30 | events | IDX | start_date | Date-range queries |
| I31 | event_polling | PRIMARY | poll_id | PK lookup (automatic) |
| I32 | event_polling | FK | event_id | Cascade delete |
| I33 | event_polling | FK | member_id | SET NULL |
| I34 | event_polling | IDX | (event_id, member_id) | Prevent duplicate responses |
| I35 | event_reports | PRIMARY | report_id | PK lookup (automatic) |
| I36 | event_reports | FK | event_id | Cascade delete |
| I37 | projects | PRIMARY | project_id | PK lookup (automatic) |
| I38 | projects | FK | rotary_year_id | Year-based filtering |
| I39 | projects | IDX | status | Status-based filtering |
| I40 | project_reports | PRIMARY | (project_id, year) | One report per project per year |
| I41 | project_reports | FK | project_id | Cascade delete |
| I42 | donors | PRIMARY | donor_id | PK lookup (automatic) |
| I43 | donations | PRIMARY | donation_id | PK lookup (automatic) |
| I44 | donations | FK | donor_id | Cascade delete |
| I45 | donations | IDX | status | Pipeline filtering |
| I46 | donations | IDX | date | Date-range reporting |
| I47 | donation_status_history | PRIMARY | history_id | PK lookup (automatic) |
| I48 | donation_status_history | FK | donation_id | Cascade delete |
| I49 | donation_status_history | FK | admin_id | SET NULL |
| I50 | donation_allocation | PRIMARY | allocation_id | PK lookup (automatic) |
| I51 | donation_allocation | FK | donation_id | Cascade delete |
| I52 | donation_allocation | FK | project_id | SET NULL |
| I53 | donation_allocation | FK | event_id | SET NULL |
| I54 | donation_report | PRIMARY | report_id | PK lookup (automatic) |
| I55 | donation_report | FK | donation_id | Cascade delete |
| I56 | media_gallery | PRIMARY | media_id | PK lookup (automatic) |
| I57 | media_gallery | IDX | (reference_type, reference_id) | Polymorphic lookup |
| I58 | media_gallery | IDX | status | Active media filtering |
| I59 | gallery_media | PRIMARY | id | PK lookup (automatic) |
| I60 | gallery_media | IDX | category | Category-based filtering |
| I61 | site_content | PRIMARY | id | PK lookup (automatic) |
| I62 | site_content | UNIQUE | (page, section) | CMS lookup by page |
| I63 | contact_messages | PRIMARY | id | PK lookup (automatic) |
| I64 | contact_messages | IDX | is_read | Unread message filtering |
| I65 | announcements | PRIMARY | announcement_id | PK lookup (automatic) |
| I66 | collaborations | PRIMARY | collab_id | PK lookup (automatic) |
| I67 | collaborations | IDX | status | Proposal status filtering |
| I68 | collaborations | FK | reviewed_by | SET NULL |
| I69 | collaboration_reports | PRIMARY | report_id | PK lookup (automatic) |
| I70 | collaboration_reports | FK | collab_id | Cascade delete |

**Total: 70 indexes** (26 PRIMARY, 7 UNIQUE, 18 FK, 19 composite/secondary)

---

## COMPLETE FOREIGN KEY MAP

### Cascade Rule Summary

| Delete Rule | Count | Relationships |
|------------|-------|---------------|
| CASCADE | 15 | admin→tokens, year→leadership, member→leadership, event→polling, event→reports, project→reports, donor→donations, donation→history, donation→allocation, donation→report, committee→members, member→committee, member→website_display, collab→reports, event→year |
| SET NULL | 7 | admin→collaborations, admin→history, member→polling, project→allocation, event→allocation, admin→allocation, year→events, year→projects |
| RESTRICT | 1 | position→committee_members |
| **Total** | **23** | |

**Business Justification for Each Rule:**
- **CASCADE:** Child entity has no meaning without parent. Deleting parent = deleting child.
- **SET NULL:** Child entity has independent meaning. Deleting parent = child becomes anonymous/unlinked.
- **RESTRICT:** Parent entity must not be deleted while children reference it. Prevents orphaned data.

---

## SCHEMA STATISTICS

| Metric | Value |
|--------|-------|
| Total Tables | 26 |
| Total Views | 1 |
| Total Columns | 162 |
| Total Primary Keys | 26 |
| Total Foreign Keys | 23 |
| Total Unique Constraints | 7 |
| Total Indexes | 70 |
| Total ENUM Columns | 3 (admins.role, admins.status, website_display.display_group) |
| Total TEXT Columns | 14 |
| Total DECIMAL Columns | 4 |
| Total DATETIME Columns | 22 |
| Total DATE Columns | 5 |
| Total TINYINT(1) Columns | 7 |

---

*Document generated as part of RCMP Phase 2 — Session 6*  
*Physical Database Schema Design*  
*Rotary Club of Virar — Management Platform*
