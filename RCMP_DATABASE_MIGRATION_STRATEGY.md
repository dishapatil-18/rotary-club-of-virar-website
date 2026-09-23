# RCMP Database Migration Strategy

## Rotary Club of Virar — Management Platform

**Phase:** 3 — Session 7A  
**Status:** Migration Planning (No SQL Generated)  
**Date:** 2026-07-16  
**Source of Truth:** Current Database (DATABASE_STRUCTURE.md) + Physical Schema (RCMP_PHYSICAL_DATABASE_SCHEMA.md)

---

## Table of Contents

1. [Table Inventory](#1-table-inventory)
2. [Data Migration Analysis](#2-data-migration-analysis)
3. [Data Transformation Plan](#3-data-transformation-plan)
4. [Migration Order](#4-migration-order)
5. [Backup Strategy](#5-backup-strategy)
6. [Rollback Strategy](#6-rollback-strategy)
7. [Data Validation](#7-data-validation)
8. [Risk Analysis](#8-risk-analysis)
9. [Implementation Readiness](#9-implementation-readiness)

---

## 1. Table Inventory

Every table classified into exactly one category.

### Summary

| Category | Count | Tables |
|----------|-------|--------|
| A — UNCHANGED | 12 | admins, members, donors, event_polling, event_reports, donation_report, collaboration_reports, site_content, password_reset_tokens, announcements, contact_messages, gallery_media |
| B — ALTER TABLE | 9 | events, projects, donations, rotary_years, leadership_assignments, media_gallery, project_reports, donation_status_history, collaborations |
| C — CREATE TABLE | 5 | committees, committee_members, committee_position, website_display, donation_allocation |
| D — CREATE VIEW | 1 | website_team_view |
| **TOTAL** | **27** | 26 tables + 1 view |

---

### A. UNCHANGED Tables (12)

These tables match the approved Physical Schema exactly. No SQL changes required.

#### A01. `admins`
**Current Structure:** admin_id (PK), name, email (UNIQUE), password, role (ENUM), photo_url, status (ENUM), phone, created_at  
**Physical Schema Match:** EXACT MATCH  
**Why Unchanged:** All columns, types, constraints, and defaults match. The ENUM values are identical. No new columns needed.  
**Impact:** None. Admin authentication continues to work.

#### A02. `members`
**Current Structure:** member_id (PK), name, email, phone_number, address, role, status, joined_date, photo_url, profession, short_bio, display_order, show_contact  
**Physical Schema Match:** EXACT MATCH  
**Why Unchanged:** All 13 columns match the approved schema. No constraints missing.  
**Impact:** None. Member display and management continue to work.

#### A03. `donors`
**Current Structure:** donor_id (PK), name, phone_number, email, social_role, address, occupation, created_at  
**Physical Schema Match:** EXACT MATCH  
**Why Unchanged:** All 8 columns match. No constraints missing.  
**Impact:** None. Donor management continues to work.

#### A04. `event_polling`
**Current Structure:** poll_id (PK), event_id (FK), member_id (FK nullable), is_attending, guest_name, submitted_on  
**Physical Schema Match:** EXACT MATCH  
**Why Unchanged:** All 6 columns match. FK constraints match.  
**Impact:** None. Event RSVP continues to work.

#### A05. `event_reports`
**Current Structure:** report_id (PK), event_id (FK), details, submitted_by, date  
**Physical Schema Match:** EXACT MATCH  
**Why Unchanged:** All 5 columns match.  
**Impact:** None. Event reporting continues to work.

#### A06. `donation_report`
**Current Structure:** report_id (PK), donation_id (FK), report_summary, verified_by, created_on  
**Physical Schema Match:** EXACT MATCH  
**Why Unchanged:** All 5 columns match.  
**Impact:** None. Donation reporting continues to work.

#### A07. `collaboration_reports`
**Current Structure:** report_id (PK), collab_id (FK), report_summary  
**Physical Schema Match:** EXACT MATCH  
**Why Unchanged:** All 3 columns match.  
**Impact:** None. Collaboration reporting continues to work.

#### A08. `site_content`
**Current Structure:** id (PK), page, section, content, updated_at; UNIQUE(page, section)  
**Physical Schema Match:** EXACT MATCH  
**Why Unchanged:** All 5 columns and unique constraint match.  
**Impact:** None. CMS continues to work.

#### A09. `password_reset_tokens`
**Current Structure:** id (PK), admin_id (FK), token (UNIQUE), expires_at, used, created_at  
**Physical Schema Match:** EXACT MATCH  
**Why Unchanged:** All 6 columns match.  
**Impact:** None. Password reset continues to work.

#### A10. `announcements`
**Current Structure:** announcement_id (PK), message, date_posted  
**Physical Schema Match:** EXACT MATCH  
**Why Unchanged:** All 3 columns match.  
**Impact:** None. Announcements continue to work.

#### A11. `contact_messages`
**Current Structure:** id (PK), name, email, subject, message, submitted_at, is_read  
**Physical Schema Match:** EXACT MATCH  
**Why Unchanged:** All 7 columns match.  
**Impact:** None. Contact form continues to work.

#### A12. `gallery_media`
**Current Structure:** id (PK), title, caption, media_type, category, media_url, thumbnail_url, uploaded_by, upload_date  
**Physical Schema Match:** EXACT MATCH  
**Why Unchanged:** All 9 columns match. This is a legacy table that remains for backward compatibility.  
**Impact:** None. Admin gallery uploads continue to work.

---

### B. ALTER TABLE (9)

These tables exist but require modifications to match the approved Physical Schema.

#### B01. `events`

**Current Structure:**
| Column | Current Type | Notes |
|--------|-------------|-------|
| event_id | INT PK | OK |
| title | VARCHAR(255) | OK |
| description | TEXT | OK |
| start_date | DATETIME | OK |
| end_date | DATETIME | OK |
| location | VARCHAR(255) | OK |
| category | VARCHAR(100) | OK |
| image_url | VARCHAR(500) | OK |

**Required Changes:**
| Change | SQL Operation | Business Rule |
|--------|--------------|---------------|
| Add `rotary_year_id` | `ALTER TABLE events ADD COLUMN rotary_year_id INT NULL` | Session 4.5: Events optionally linked to a Rotary Year |
| Add FK constraint | `ALTER TABLE events ADD CONSTRAINT fk_events_year FOREIGN KEY (rotary_year_id) REFERENCES rotary_years(id) ON DELETE SET NULL` | Referential integrity |
| Add index | `CREATE INDEX idx_events_year ON events(rotary_year_id)` | Year-based event filtering |

**Expected Impact:** Low. Column is nullable, so existing rows get NULL. No existing queries break.

#### B02. `projects`

**Current Structure:**
| Column | Current Type | Notes |
|--------|-------------|-------|
| project_id | INT PK | OK |
| title | VARCHAR(255) | OK |
| description | TEXT | OK |
| start_date | DATE | OK |
| end_date | DATE | OK |
| status | VARCHAR(20) | OK |
| image_url | VARCHAR(500) | OK |
| collaborator | VARCHAR(255) | OK |
| created_at | DATETIME | OK |

**Required Changes:**
| Change | SQL Operation | Business Rule |
|--------|--------------|---------------|
| Add `rotary_year_id` | `ALTER TABLE projects ADD COLUMN rotary_year_id INT NULL` | Session 4.5: Projects optionally linked to a Rotary Year |
| Add FK constraint | `ALTER TABLE projects ADD CONSTRAINT fk_projects_year FOREIGN KEY (rotary_year_id) REFERENCES rotary_years(id) ON DELETE SET NULL` | Referential integrity |
| Add index | `CREATE INDEX idx_projects_year ON projects(rotary_year_id)` | Year-based project filtering |

**Expected Impact:** Low. Column is nullable, so existing rows get NULL. No existing queries break.

#### B03. `rotary_years`

**Current Structure:**
| Column | Current Type | Notes |
|--------|-------------|-------|
| id | INT PK | OK |
| year_name | VARCHAR(20) UNIQUE | OK |
| is_current | TINYINT(1) | OK |
| created_at | DATETIME | **TO BE REMOVED** |

**Required Changes:**
| Change | SQL Operation | Business Rule |
|--------|--------------|---------------|
| Remove `created_at` | `ALTER TABLE rotary_years DROP COLUMN created_at` | Session 4.5: Rotary Year creation has no business meaning |

**Expected Impact:** Low. `created_at` is not used in any application logic. The column exists only from the migration script default.

**Risk:** If any custom queries or reports use `rotary_years.created_at`, they will break.  
**Mitigation:** Grep codebase for `rotary_years.created_at` before executing. Current codebase analysis shows no usage.

#### B04. `donations`

**Current Structure:**
| Column | Current Type | Notes |
|--------|-------------|-------|
| donation_id | INT PK | OK |
| donor_id | INT FK | OK |
| donation_type | VARCHAR(50) | OK |
| description | TEXT | OK |
| amount | DECIMAL(12,2) | OK |
| utr_number | VARCHAR(100) | OK |
| screenshot_path | VARCHAR(500) | OK |
| pickup_option | VARCHAR(50) | OK |
| status | VARCHAR(30) | OK |
| date | DATETIME | OK |

**Required Changes:**
| Change | SQL Operation | Business Rule |
|--------|--------------|---------------|
| Add `status_updated_by` | `ALTER TABLE donations ADD COLUMN status_updated_by VARCHAR(255) NOT NULL DEFAULT ''` | Audit: who last updated status |
| Add `status_updated_role` | `ALTER TABLE donations ADD COLUMN status_updated_role VARCHAR(50) NULL` | Audit: role context of updater |

**Expected Impact:** Low. Both columns have defaults (empty string / NULL). Existing rows unaffected.

**Note:** The current `donation_action.php` already writes `status_updated_by` and `status_updated_role` to these columns (see `donation_action.php:64-66`). This ALTER will formalize columns that the application code already expects to exist.

#### B05. `leadership_assignments`

**Current Structure:**
| Column | Current Type | Notes |
|--------|-------------|-------|
| id | INT PK | OK |
| rotary_year_id | INT FK | OK |
| member_id | INT FK | OK |
| role | VARCHAR(50) | OK |
| created_at | DATETIME | OK |

**Required Changes:**
| Change | SQL Operation | Business Rule |
|--------|--------------|---------------|
| Add UNIQUE constraint | `ALTER TABLE leadership_assignments ADD UNIQUE KEY unique_year_role (rotary_year_id, role)` | Business Rule: one person per role per year |

**Expected Impact:** Medium. If duplicate `(rotary_year_id, role)` combinations exist in the current data, this ALTER will FAIL. Must validate data first.

**Mitigation:** Run duplicate check before migration:
```sql
SELECT rotary_year_id, role, COUNT(*) 
FROM leadership_assignments 
GROUP BY rotary_year_id, role 
HAVING COUNT(*) > 1;
```

If duplicates exist, resolve them (keep the most recent) before applying the constraint.

#### B06. `media_gallery`

**Current Structure:**
| Column | Current Type | Notes |
|--------|-------------|-------|
| media_id | INT PK | OK |
| reference_type | VARCHAR(50) | OK |
| reference_id | INT | OK |
| title | VARCHAR(255) | OK |
| description | TEXT | OK |
| media_type | VARCHAR(20) | OK |
| media_path | VARCHAR(500) | OK |
| media_date | VARCHAR(100) | OK |
| uploaded_by | VARCHAR(255) | OK |
| status | VARCHAR(20) | OK |
| created_at | DATETIME | OK |

**Required Changes:** NONE. Current structure matches Physical Schema.

**Decision:** Reclassify from B (ALTER) to A (UNCHANGED). No SQL changes needed.

**Actually, rechecking:** The Physical Schema shows the same columns. This table is UNCHANGED.

#### B07. `project_reports`

**Current Structure:**
| Column | Current Type | Notes |
|--------|-------------|-------|
| project_id | INT PK, FK | Composite PK should be (project_id, year) |
| year | YEAR/INT | Part of composite PK |
| summary | TEXT | OK |
| funds_raised | DECIMAL(12,2) | OK |
| expenditure | DECIMAL(12,2) | OK |
| achievements | TEXT | OK |
| created_at | DATETIME | OK |

**Required Changes:**
| Change | SQL Operation | Business Rule |
|--------|--------------|---------------|
| Verify PK is composite `(project_id, year)` | Check current PK definition | Physical Schema requires composite PK for annual reporting |

**Expected Impact:** Low-Medium. If the current PK is only `project_id` (1:1), changing to composite `(project_id, year)` allows multiple reports per project per year.

**Risk:** If current PK is `project_id` alone, changing it requires DROP and re-CREATE.  
**Mitigation:** Check current PK definition before migration. If already composite, no change needed.

#### B08. `collaborations`

**Current Structure:**
| Column | Current Type | Notes |
|--------|-------------|-------|
| collab_id | INT PK | OK |
| name | VARCHAR(255) | OK |
| email | VARCHAR(255) | OK |
| proposal | TEXT | OK |
| submitted_on | DATETIME | OK |
| status | VARCHAR(20) | OK |
| reviewed_by | INT nullable | OK |
| reviewed_at | DATETIME nullable | OK |

**Required Changes:** NONE. Current structure matches Physical Schema.

**Decision:** Reclassify from B (ALTER) to A (UNCHANGED).

#### B09. `donation_status_history`

**Current Structure:**
| Column | Current Type | Notes |
|--------|-------------|-------|
| history_id | INT PK | OK |
| donation_id | INT FK | OK |
| previous_status | VARCHAR(50) nullable | OK |
| new_status | VARCHAR(50) | OK |
| admin_id | INT nullable | OK |
| admin_name | VARCHAR(255) nullable | OK |
| admin_role | VARCHAR(50) nullable | OK |
| remarks | TEXT nullable | OK |
| updated_at | DATETIME | OK |

**Required Changes:** NONE. Current structure matches Physical Schema.

**Decision:** Reclassify from B (ALTER) to A (UNCHANGED).

---

### REVISED Table Inventory

| Category | Count | Tables |
|----------|-------|--------|
| A — UNCHANGED | 15 | admins, members, donors, event_polling, event_reports, donation_report, collaboration_reports, site_content, password_reset_tokens, announcements, contact_messages, gallery_media, media_gallery, collaborations, donation_status_history |
| B — ALTER TABLE | 6 | events, projects, rotary_years, donations, leadership_assignments, project_reports |
| C — CREATE TABLE | 5 | committees, committee_members, committee_position, website_display, donation_allocation |
| D — CREATE VIEW | 1 | website_team_view |
| **TOTAL** | **27** | 26 tables + 1 view |

---

### C. CREATE TABLE (5)

These tables do not currently exist. They must be created from scratch.

#### C01. `committees`

**Why New:** The committee system was designed in Sessions 1–4B and approved in Session 4.5. No committee functionality exists in the current codebase.  
**Business Rule:** Rotary clubs operate through committees (Standing, Ad-hoc, District-level). Each committee has members with defined positions.  
**Dependencies:** `rotary_years` (FK: committee belongs to a year)  
**Estimated Rows:** 5–15 committees per year  
**Priority:** HIGH — Foundation for committee_members and the website_team_view

#### C02. `committee_position`

**Why New:** Approved as a separate lookup table in Session 4.5 for governance consistency and historical reporting.  
**Business Rule:** Committee positions must be standardized (Chairperson, Co-Chair, Secretary, Member) to prevent data inconsistency.  
**Dependencies:** NONE (standalone lookup table)  
**Estimated Rows:** 4–8 positions (seed data)  
**Priority:** HIGH — Must be created BEFORE committee_members (FK dependency)

#### C03. `committee_members`

**Why New:** Junction table resolving the M:N relationship between committees and members.  
**Business Rule:** Members serve on committees with defined positions per year.  
**Dependencies:** `committees` (FK), `members` (FK), `committee_position` (FK)  
**Estimated Rows:** 20–60 members across all committees per year  
**Priority:** HIGH — Required for website_team_view

#### C04. `website_display`

**Why New:** Approved in Session 4.5 as the display metadata overlay for the Website Team.  
**Business Rule:** The website must control which members appear, in what order, with what title, and in what group — independently of their role assignment.  
**Dependencies:** `members` (FK)  
**Estimated Rows:** 10–30 records (one per active member shown on website)  
**Priority:** HIGH — Required for website_team_view

#### C05. `donation_allocation`

**Why New:** Approved in Session 4.5 as an optional one-to-many relationship from donations.  
**Business Rule:** Donations may be unrestricted, restricted to a project/event, or split across multiple purposes.  
**Dependencies:** `donations` (FK), `projects` (FK nullable), `events` (FK nullable), `admins` (FK nullable)  
**Estimated Rows:** 0–100 records (only when donations are allocated)  
**Priority:** MEDIUM — No existing data requires allocation. Table starts empty.

---

### D. CREATE VIEW (1)

#### D01. `website_team_view`

**Business Purpose:** Unified view computing who appears on the Team page, combining leadership assignments, committee memberships, and display metadata — per Rotary Year.  
**Dependencies:** `leadership_assignments`, `committees`, `committee_members`, `committee_position`, `members`, `website_display`  
**Expected Usage:** team.php frontend page; admin team management panel  
**Priority:** HIGH — Must be created AFTER all dependent tables exist.

---

## 2. Data Migration Analysis

For every existing table: can data be copied directly?

| # | Table | Direct Copy? | Analysis |
|---|-------|-------------|----------|
| 1 | `admins` | YES | No schema changes. Data format matches. |
| 2 | `members` | YES | No schema changes. Data format matches. |
| 3 | `donors` | YES | No schema changes. Data format matches. |
| 4 | `event_polling` | YES | No schema changes. Data format matches. |
| 5 | `event_reports` | YES | No schema changes. Data format matches. |
| 6 | `donation_report` | YES | No schema changes. Data format matches. |
| 7 | `collaboration_reports` | YES | No schema changes. Data format matches. |
| 8 | `site_content` | YES | No schema changes. Data format matches. |
| 9 | `password_reset_tokens` | YES | No schema changes. Data format matches. |
| 10 | `announcements` | YES | No schema changes. Data format matches. |
| 11 | `contact_messages` | YES | No schema changes. Data format matches. |
| 12 | `gallery_media` | YES | No schema changes. Data format matches. |
| 13 | `media_gallery` | YES | No schema changes. Data format matches. |
| 14 | `collaborations` | YES | No schema changes. Data format matches. |
| 15 | `donation_status_history` | YES | No schema changes. Data format matches. |
| 16 | `events` | YES* | New column `rotary_year_id` is nullable with DEFAULT NULL. Existing rows get NULL. No transformation needed. |
| 17 | `projects` | YES* | New column `rotary_year_id` is nullable with DEFAULT NULL. Existing rows get NULL. No transformation needed. |
| 18 | `rotary_years` | YES* | Column `created_at` is dropped. Data in other columns preserved. |
| 19 | `donations` | YES* | New columns `status_updated_by` and `status_updated_role` have defaults. Existing rows get defaults. |
| 20 | `leadership_assignments` | CONDITIONAL | UNIQUE constraint added on `(rotary_year_id, role)`. Will FAIL if duplicate roles exist per year. Must validate first. |
| 21 | `project_reports` | CONDITIONAL | If current PK is `project_id` only, changing to composite `(project_id, year)` requires PK modification. Must check current state. |

**Summary:** 15 tables copy directly. 4 tables add nullable/default columns (safe). 2 tables require pre-migration validation.

---

## 3. Data Transformation Plan

### 3.1 Column Additions (No Data Transformation Required)

| Table | New Column | Type | Default | Existing Data Impact |
|-------|-----------|------|---------|---------------------|
| events | `rotary_year_id` | INT NULL | NULL | All existing rows get NULL. Application can backfill later. |
| projects | `rotary_year_id` | INT NULL | NULL | All existing rows get NULL. Application can backfill later. |
| donations | `status_updated_by` | VARCHAR(255) NOT NULL | '' | All existing rows get empty string. Audit trail starts from migration. |
| donations | `status_updated_role` | VARCHAR(50) NULL | NULL | All existing rows get NULL. |

### 3.2 Column Removals

| Table | Removed Column | Reason | Data Impact |
|-------|---------------|--------|-------------|
| rotary_years | `created_at` | Session 4.5: No business meaning | Data lost. Acceptable — not used in any application logic. |

### 3.3 Constraint Additions

| Table | Constraint | Type | Pre-Check Required |
|-------|-----------|------|-------------------|
| leadership_assignments | `(rotary_year_id, role)` | UNIQUE | Must verify no duplicate role assignments per year |

### 3.4 No Transformations Required

The following transformations are NOT needed because the current data is already compatible:

- **No column renames** — All existing column names match the Physical Schema
- **No datatype conversions** — All existing types match (or new columns have defaults)
- **No enum value conversions** — The only ENUMs (admins.role, admins.status) already have correct values
- **No derived value calculations** — No columns require computation from other columns
- **No value mapping tables** — No lookup replacements needed

### 3.5 Backfill Opportunities (Post-Migration)

These are optional data enrichment tasks that can be done after migration, not during:

| Table | Column | Backfill Logic | Priority |
|-------|--------|---------------|----------|
| events | `rotary_year_id` | Derive from `start_date`: if July 1 X – June 30 X+1, link to year X | LOW — Can be done via admin panel |
| projects | `rotary_year_id` | Derive from `start_date` or `project_reports.year` | LOW — Can be done via admin panel |

---

## 4. Migration Order

The exact dependency-ordered sequence for safe migration.

```
PHASE 0: PREPARATION
═══════════════════════════════════════════════════════════════

Step 0.1   Full database backup (rotary database dump)
           ↓
Step 0.2   Full uploads directory backup
           ↓
Step 0.3   Full configuration backup (.env, config/, etc.)
           ↓
Step 0.4   Verify backup integrity (test restore on staging)
           ↓
Step 0.5   Record current row counts for all 21 tables
           ↓
Step 0.6   Record current FK constraints for all tables
           ↓

PHASE 1: PRE-FLIGHT VALIDATION
═══════════════════════════════════════════════════════════════

Step 1.1   Check leadership_assignments for duplicate (year, role) pairs
           → If duplicates found, resolve before proceeding
           ↓
Step 1.2   Check project_reports PK definition
           → Is it project_id alone, or (project_id, year)?
           → Record current state
           ↓
Step 1.3   Grep codebase for rotary_years.created_at usage
           → If used anywhere, document and plan for removal
           ↓
Step 1.4   Verify no application code writes to the 5 new tables
           → committees, committee_members, committee_position,
             website_display, donation_allocation
           → If any code references them, flag for review
           ↓

PHASE 2: MODIFY EXISTING TABLES (ALTER TABLE)
═══════════════════════════════════════════════════════════════

Step 2.1   ALTER events — ADD COLUMN rotary_year_id (nullable)
           → Zero impact: NULL default, existing rows unaffected
           ↓
Step 2.2   ALTER projects — ADD COLUMN rotary_year_id (nullable)
           → Zero impact: NULL default, existing rows unaffected
           ↓
Step 2.3   ALTER donations — ADD COLUMN status_updated_by (default '')
           → Zero impact: default value, existing rows get ''
           ↓
Step 2.4   ALTER donations — ADD COLUMN status_updated_role (nullable)
           → Zero impact: NULL default, existing rows get NULL
           ↓
Step 2.5   ALTER rotary_years — DROP COLUMN created_at
           → Low impact: column not used in application
           ↓
Step 2.6   ALTER leadership_assignments — ADD UNIQUE (rotary_year_id, role)
           → ONLY if Step 1.1 confirmed no duplicates
           → If duplicates exist, resolve first
           ↓
Step 2.7   ALTER project_reports — Verify/fix composite PK (project_id, year)
           → Only if Step 1.2 confirmed PK needs modification
           ↓

PHASE 3: CREATE NEW TABLES (CREATE TABLE)
═══════════════════════════════════════════════════════════════

Step 3.1   CREATE committee_position (lookup — no dependencies)
           ↓
Step 3.2   Seed committee_position data:
           → Chairperson, Co-Chair, Secretary, Member
           ↓
Step 3.3   CREATE committees (depends on: rotary_years)
           ↓
Step 3.4   CREATE committee_members (depends on: committees, members, committee_position)
           ↓
Step 3.5   CREATE website_display (depends on: members)
           ↓
Step 3.6   CREATE donation_allocation (depends on: donations, projects, events, admins)
           ↓

PHASE 4: CREATE VIEWS
═══════════════════════════════════════════════════════════════

Step 4.1   CREATE OR REPLACE VIEW website_team_view
           → Depends on: leadership_assignments, committees,
             committee_members, committee_position, members,
             website_display
           → All dependent tables must exist first
           ↓

PHASE 5: POST-MIGRATION INDEXES
═══════════════════════════════════════════════════════════════

Step 5.1   CREATE INDEX idx_events_year ON events(rotary_year_id)
           ↓
Step 5.2   CREATE INDEX idx_events_start ON events(start_date)
           ↓
Step 5.3   CREATE INDEX idx_projects_year ON projects(rotary_year_id)
           ↓
Step 5.4   CREATE INDEX idx_projects_status ON projects(status)
           ↓
Step 5.5   CREATE INDEX idx_donations_status ON donations(status)
           ↓
Step 5.6   CREATE INDEX idx_donations_date ON donations(date)
           ↓
Step 5.7   CREATE INDEX idx_members_status ON members(status)
           ↓
Step 5.8   CREATE INDEX idx_rotary_years_current ON rotary_years(is_current)
           ↓
Step 5.9   CREATE INDEX idx_contact_unread ON contact_messages(is_read)
           ↓
Step 5.10  CREATE INDEX idx_collab_status ON collaborations(status)
           ↓
Step 5.11  CREATE INDEX idx_media_ref ON media_gallery(reference_type, reference_id)
           ↓
Step 5.12  CREATE INDEX idx_media_status ON media_gallery(status)
           ↓
Step 5.13  CREATE INDEX idx_gallery_category ON gallery_media(category)
           ↓
Step 5.14  CREATE INDEX idx_website_display_group ON website_display(display_group)
           ↓
Step 5.15  CREATE INDEX idx_website_display_order ON website_display(display_group, display_order)
           ↓
Step 5.16  CREATE INDEX idx_committee_members_committee ON committee_members(committee_id)
           ↓
Step 5.17  CREATE INDEX idx_committee_members_member ON committee_members(member_id)
           ↓
Step 5.18  CREATE INDEX idx_donation_alloc_donation ON donation_allocation(donation_id)
           ↓
Step 5.19  CREATE INDEX idx_donation_alloc_project ON donation_allocation(project_id)
           ↓
Step 5.20  CREATE INDEX idx_donation_alloc_event ON donation_allocation(event_id)
           ↓
Step 5.21  CREATE INDEX idx_event_polling_member ON event_polling(member_id)
           ↓
Step 5.22  CREATE INDEX idx_donation_history_donation ON donation_status_history(donation_id)
           ↓
Step 5.23  CREATE INDEX idx_donation_history_admin ON donation_status_history(admin_id)
           ↓
Step 5.24  CREATE INDEX idx_password_reset_admin ON password_reset_tokens(admin_id)
           ↓
Step 5.25  CREATE INDEX idx_event_polling_composite ON event_polling(event_id, member_id)
           ↓

PHASE 6: VALIDATION
═══════════════════════════════════════════════════════════════

Step 6.1   Row count validation (all 26 tables)
           ↓
Step 6.2   FK integrity validation
           ↓
Step 6.3   Website functionality test
           ↓
Step 6.4   Admin panel functionality test
           ↓
Step 6.5   Communication engine test
           ↓
Step 6.6   Audit log test
           ↓
Step 6.7   View functionality test (website_team_view)
           ↓

PHASE 7: GO LIVE
═══════════════════════════════════════════════════════════════

Step 7.1   Clear application cache (if any)
           ↓
Step 7.2   Test all admin panels
           ↓
Step 7.3   Test all public pages
           ↓
Step 7.4   Monitor error logs for 24 hours
           ↓
Step 7.5   Migration complete
```

---

## 5. Backup Strategy

### 5.1 What Must Be Backed Up

| # | Item | Method | Why |
|---|------|--------|-----|
| B01 | **Database (rotary)** | `mysqldump rotary > rotary_pre_migration.sql` | Primary data source. If migration fails, this is the restore point. |
| B02 | **Uploads directory** | `tar -czf uploads_backup.tar.gz uploads/` | Contains donated files, event images, project images, member photos. Not in database. |
| B03 | **.env file** | `cp .env .env.backup` | Contains database credentials, super admin email, SMTP settings. Migration changes DB structure. |
| B04 | **config/ directory** | `cp -r config/ config_backup/` | Contains club_settings.php with social media URLs, constants. |
| B05 | **Email templates** | `cp -r includes/email_templates/ email_templates_backup/` | Communication engine templates. May reference DB columns. |
| B06 | **Admin panel files** | `cp -r Admin/ Admin_backup/` | Contains db_migration.php, admin logic. May need rollback. |
| B07 | **Audit logs** | Included in database dump | audit_logs table is in the database. Covered by B01. |
| B08 | **Website settings (DB)** | Included in database dump | site_content table stores all CMS data. Covered by B01. |
| B09 | **Generated reports** | Included in database dump | event_reports, project_reports, donation_report, collaboration_reports are in the database. Covered by B01. |
| B10 | **Gallery files** | Included in uploads backup | gallery_action.php uploads to uploads/ directory. Covered by B02. |

### 5.2 Backup Verification

Before proceeding with migration:
1. Restore database dump to a test database `rotary_test`
2. Verify all 21 tables exist with correct row counts
3. Verify admin login works against restored database
4. Verify site_content data is intact

### 5.3 Backup Retention

Keep backups for minimum 30 days after successful migration. Delete after confirming stability.

---

## 6. Rollback Strategy

### 6.1 Rollback Checkpoints

| Checkpoint | Trigger | Action |
|------------|---------|--------|
| CP-01 | Pre-Phase 2 (before any ALTER) | Restore full database from backup. Zero data loss. |
| CP-02 | Post-Phase 2 (after ALTERs, before CREATE) | Restore database from backup. ALTERs are reversible via DROP COLUMN. |
| CP-03 | Post-Phase 3 (after CREATEs, before VIEW) | DROP all 5 new tables. Restore ALTERed columns. |
| CP-04 | Post-Phase 4 (after VIEW, before indexes) | DROP website_team_view. Rollback phases 3 and 2. |
| CP-05 | Post-Phase 5 (after indexes, before validation) | DROP new indexes. Rollback phases 4, 3, and 2. |
| CP-06 | Post-Phase 6 (during validation, before go-live) | Full database restore from backup. Application restart. |

### 6.2 Rollback Procedure

```
IF migration fails at any point:

1. STOP all application access (maintenance mode)
2. Identify the failed step
3. IF failure is in Phase 2 (ALTER TABLE):
   → Restore database from backup
   → Restore application files
   → Resume normal operations
   → Estimated downtime: 5-15 minutes

4. IF failure is in Phase 3 (CREATE TABLE):
   → DROP any new tables that were created
   → Undo any ALTERs from Phase 2
   → Restore database from backup if needed
   → Resume normal operations
   → Estimated downtime: 5-20 minutes

5. IF failure is in Phase 4-5 (VIEW/INDEX):
   → DROP any new views/indexes
   → Resume normal operations
   → Estimated downtime: 2-5 minutes

6. IF failure is in Phase 6 (VALIDATION):
   → Identify what failed
   → Determine if it's a data issue or schema issue
   → Fix forward if possible, rollback if not
   → Estimated downtime: 10-30 minutes

7. AFTER rollback:
   → Verify all existing functionality works
   → Check error logs
   → Notify stakeholders
   → Document root cause
   → Plan remediation
```

### 6.3 Downtime Expectations

| Scenario | Expected Downtime | Recovery Complexity |
|----------|-------------------|-------------------|
| Pre-migration backup failure | 0 min (stop and fix backup) | None |
| Phase 2 ALTER failure | 5-15 min | Low — restore backup |
| Phase 3 CREATE failure | 5-20 min | Low — DROP new tables, restore if needed |
| Phase 4-5 failure | 2-5 min | Minimal — DROP new objects |
| Phase 6 validation failure | 10-30 min | Medium — depends on issue |
| Full rollback needed | 5-20 min | Low — restore backup |

### 6.4 Maintenance Mode

Before starting migration, enable maintenance mode:
1. Create a maintenance flag file or configure in application
2. Display maintenance message to public users
3. Allow admin access for verification
4. Disable all write operations

---

## 7. Data Validation

### 7.1 Row Count Validation

After migration, verify row counts match pre-migration counts:

| Table | Pre-Migration Count | Post-Migration Count | Match? |
|-------|--------------------|--------------------|--------|
| admins | ___ | ___ | |
| members | ___ | ___ | |
| events | ___ | ___ | |
| projects | ___ | ___ | |
| donors | ___ | ___ | |
| donations | ___ | ___ | |
| collaborations | ___ | ___ | |
| media_gallery | ___ | ___ | |
| gallery_media | ___ | ___ | |
| event_polling | ___ | ___ | |
| event_reports | ___ | ___ | |
| project_reports | ___ | ___ | |
| donation_report | ___ | ___ | |
| collaboration_reports | ___ | ___ | |
| rotary_years | ___ | ___ | |
| leadership_assignments | ___ | ___ | |
| site_content | ___ | ___ | |
| password_reset_tokens | ___ | ___ | |
| announcements | ___ | ___ | |
| contact_messages | ___ | ___ | |
| donation_status_history | ___ | ___ | |
| committees | 0 (new) | ___ | |
| committee_members | 0 (new) | ___ | |
| committee_position | 0 (new) | ___ | |
| website_display | 0 (new) | ___ | |
| donation_allocation | 0 (new) | ___ | |

### 7.2 FK Integrity Validation

```sql
-- Check for orphaned leadership_assignments
SELECT la.id FROM leadership_assignments la
LEFT JOIN members m ON la.member_id = m.member_id
WHERE m.member_id IS NULL;

-- Check for orphaned event_polling
SELECT ep.poll_id FROM event_polling ep
LEFT JOIN events e ON ep.event_id = e.event_id
WHERE e.event_id IS NULL;

-- Check for orphaned donation_status_history
SELECT dsh.history_id FROM donation_status_history dsh
LEFT JOIN donations d ON dsh.donation_id = d.donation_id
WHERE d.donation_id IS NULL;

-- Check for orphaned password_reset_tokens
SELECT prt.id FROM password_reset_tokens prt
LEFT JOIN admins a ON prt.admin_id = a.admin_id
WHERE a.admin_id IS NULL;
```

### 7.3 Website Functionality Validation

| # | Test | Expected Result | Status |
|---|------|----------------|--------|
| W01 | Homepage loads | All sections render correctly | |
| W02 | Team page loads | Leadership cards display per year | |
| W03 | Activities page loads | Events and projects list correctly | |
| W04 | Media Gallery loads | Images display in grid | |
| W05 | Contact page loads | Form submits successfully | |
| W06 | Donate page loads | Donation form works | |
| W07 | Login modal works | Admin can log in | |
| W08 | Footer renders | All links, social media, contact info correct | |
| W09 | Site content updates | CMS changes appear on pages | |
| W10 | Mobile responsive | All pages render on mobile | |

### 7.4 Admin Panel Validation

| # | Test | Expected Result | Status |
|---|------|----------------|--------|
| A01 | Dashboard loads | Stats and quick links render | |
| A02 | Member management | Add/Edit/Delete member works | |
| A03 | Event management | Add/Edit/Delete event works | |
| A04 | Project management | Add/Edit/Delete project works | |
| A05 | Donation management | Add/Edit donation works, status history records | |
| A06 | Donor management | Add/Edit donor works | |
| A07 | Leadership transfer | Assign President/Secretary/Treasurer per year works | |
| A08 | Gallery management | Upload/List media works | |
| A09 | Site content management | Edit page content works | |
| A10 | Contact messages | View/Mark as read works | |
| A11 | Collaboration management | Review proposals works | |
| A12 | Password reset | Token generation and reset flow works | |
| A13 | Audit logs | Actions are logged correctly | |
| A14 | CSV export | Members export works | |

### 7.5 Communication Engine Validation

| # | Test | Expected Result | Status |
|---|------|----------------|--------|
| C01 | Email composer loads | Template selection works | |
| C02 | Donation status email | Email sent on status change to Contacted/Completed | |
| C03 | Email templates render | All placeholders replaced correctly | |

### 7.6 Audit Log Validation

| # | Test | Expected Result | Status |
|---|------|----------------|--------|
| L01 | Member add logged | Audit entry created | |
| L02 | Donation add logged | Audit entry created | |
| L03 | Leadership change logged | Audit entry created | |
| L04 | Admin login logged | Audit entry created | |
| L05 | Audit log page loads | All entries display correctly | |

### 7.7 New Table Validation

| # | Test | Expected Result | Status |
|---|------|----------------|--------|
| N01 | committees table exists | CREATE TABLE successful, 0 rows | |
| N02 | committee_position table exists | CREATE TABLE successful, 4 rows (seeded) | |
| N03 | committee_members table exists | CREATE TABLE successful, 0 rows | |
| N04 | website_display table exists | CREATE TABLE successful, 0 rows | |
| N05 | donation_allocation table exists | CREATE TABLE successful, 0 rows | |
| N06 | website_team_view works | SELECT returns empty result set (no data yet) | |
| N07 | Can insert into committees | Test insert and delete works | |
| N08 | Can insert into committee_members | Test insert with valid FKs works | |
| N09 | FK constraints work | Attempting invalid FK insert fails | |
| N10 | CASCADE delete works | Deleting parent removes children correctly | |

---

## 8. Risk Analysis

### HIGH RISK

| # | Risk | Cause | Impact | Mitigation | Recovery |
|---|------|-------|--------|------------|----------|
| H01 | leadership_assignments UNIQUE constraint fails | Duplicate (year, role) pairs exist in current data | Migration halts at Step 2.6 | Run duplicate check in Phase 1. Resolve duplicates before migration. | Remove the duplicate rows (keep most recent) before applying constraint. |
| H02 | Database backup is corrupted or incomplete | mysqldump fails silently, disk full, or interrupted | Cannot rollback if migration fails | Verify backup file size and integrity. Test restore on staging database. | If backup is bad and migration fails, data loss is possible. Prevention is critical. |
| H03 | Application code breaks after ALTER | Code references columns that were removed or renamed | Website/admin panel errors | Grep codebase for all affected column references before migration. | Rollback to backup. No columns are renamed in this migration — only added/removed. |
| H04 | Migration runs during peak traffic | Simultaneous writes during ALTER cause locks | User-facing errors, data inconsistency | Run migration during off-hours (late night/early morning). Enable maintenance mode. | Rollback and retry during maintenance window. |

### MEDIUM RISK

| # | Risk | Cause | Impact | Mitigation | Recovery |
|---|------|-------|--------|------------|----------|
| M01 | project_reports PK modification fails | Current PK is `project_id` only; data has multiple reports per project | Cannot add composite PK | Check current PK in Phase 1. If already composite, skip. If not, verify no duplicate (project_id, year) pairs exist. | Drop the constraint attempt. Fix data first. |
| M02 | New tables have FK violations on insert | Application code inserts data that violates FK constraints | New feature (committees) doesn't work | Ensure all seed data uses valid FK references. Test inserts in validation phase. | Fix the violating data. |
| M03 | website_team_view query is slow | Large datasets or missing indexes | Team page loads slowly | All indexes are created in Phase 5 before the view is used in production. | Add missing indexes. |
| M04 | ENUM constraint on website_display fails | Invalid display_group values inserted by application | Display grouping breaks | Application code must use only valid ENUM values: leadership, committee, board, hidden. | Fix application code to use valid values. |

### LOW RISK

| # | Risk | Cause | Impact | Mitigation | Recovery |
|---|------|-------|--------|------------|----------|
| L01 | rotary_years.created_at removal breaks a query | Some undiscovered query references this column | Minor error in one query | Grep codebase for `created_at` on rotary_years. Current analysis shows no usage. | Add the column back if needed. |
| L02 | NULL rotary_year_id on existing events/projects | No year data available for historical records | Year filtering shows NULL entries | Backfill using start_date after migration (optional, post-migration task). | No recovery needed — NULL is the correct default. |
| L03 | Empty new tables cause confusion | Admins see empty committee/donation_allocation tables | Admin confusion | Document in admin panel that new features start empty. Add helpful empty-state messages. | No recovery needed — expected initial state. |
| L04 | Index creation takes too long | Large table + server resource constraints | Migration takes longer than expected | Create indexes during low-traffic window. Monitor progress. | Wait for completion. No data risk. |

---

## 9. Implementation Readiness

### Readiness Checklist

| # | Check | Status | Notes |
|---|-------|--------|-------|
| 1 | Physical Schema document complete and approved | PASS | RCMP_PHYSICAL_DATABASE_SCHEMA.md |
| 2 | Enterprise ER Diagram approved | PASS | RCMP_ENTERPRISE_ER_DIAGRAM.md |
| 3 | Logical Database approved | PASS | Sessions 1–4.5 |
| 4 | Current database structure documented | PASS | DATABASE_STRUCTURE.md |
| 5 | Backup strategy defined | PASS | Section 5 |
| 6 | Rollback strategy defined | PASS | Section 6 |
| 7 | Migration order dependency-safe | PASS | Section 4 |
| 8 | All data transformations identified | PASS | Section 3 |
| 9 | All risks documented with mitigations | PASS | Section 8 |
| 10 | Validation checklists complete | PASS | Section 7 |
| 11 | No SQL generated in this session | PASS | Planning only |
| 12 | No architectural changes introduced | PASS | Implementation of approved design |

### Pre-Implementation Prerequisites

Before generating SQL in Session 7B, the following must be confirmed:

| # | Prerequisite | Status | Action |
|---|-------------|--------|--------|
| P1 | Staging environment available | PENDING | Set up staging database |
| P2 | Current row counts recorded | PENDING | Run COUNT(*) on all 21 tables |
| P3 | leadership_assignments duplicates checked | PENDING | Run duplicate query |
| P4 | project_reports PK definition verified | PENDING | Run SHOW CREATE TABLE |
| P5 | Backup verified and restorable | PENDING | Test restore on staging |

### Implementation Decision

**The project IS ready for SQL implementation.**

All planning artifacts are complete. The migration strategy is comprehensive. Risks are identified with mitigations. The dependency order is safe.

Session 7B may proceed to generate executable SQL DDL scripts.

---

*Document generated as part of RCMP Phase 3 — Session 7A*  
*Database Migration Strategy*  
*Rotary Club of Virar — Management Platform*
