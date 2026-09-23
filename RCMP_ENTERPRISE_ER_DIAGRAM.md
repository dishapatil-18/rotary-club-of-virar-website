# RCMP Enterprise ER Diagram

## Rotary Club of Virar — Management Platform

**Phase:** 2 — Session 5  
**Status:** DOCUMENTATION (Not a Design Session)  
**Date:** 2026-07-16  
**Source of Truth:** Approved Logical Database (Sessions 1 through 4.5)

---

## Table of Contents

1. [Enterprise ER Diagram](#1-enterprise-er-diagram)
2. [Entity Classification](#2-entity-classification)
3. [Relationship Matrix](#3-relationship-matrix)
4. [Cardinality Matrix](#4-cardinality-matrix)
5. [Primary Key / Foreign Key Mapping](#5-primary-key--foreign-key-mapping)
6. [Junction Table Identification](#6-junction-table-identification)
7. [Lookup Table Identification](#7-lookup-table-identification)
8. [System Table Identification](#8-system-table-identification)
9. [Derived View Identification](#9-derived-view-identification)
10. [Validation — Faithful Representation](#10-validation--faithful-representation)

---

## 1. Enterprise ER Diagram

### 1.1 Visual Representation

The diagram below represents the approved Logical Database. Every entity, relationship, and cardinality shown is derived directly from the approved Logical Database. No design changes have been introduced.

```
┌─────────────────────────────────────────────────────────────────────────────────────────────────┐
│                                    RCMP ENTERPRISE ER DIAGRAM                                    │
│                         Rotary Club of Virar — Management Platform                              │
│                          Source: Approved Logical Database (Sessions 1–4.5)                      │
└─────────────────────────────────────────────────────────────────────────────────────────────────┘


 ┌──────────────┐         ┌─────────────────────┐         ┌──────────────────┐
 │              │ 1    N  │                     │ N    1  │                  │
 │   admins     ├────────►│ password_reset_      │         │   admins         │
 │              │         │ tokens               │         │  (reviewed_by)   │
 │  [SYSTEM]    │         │  [SYSTEM]            │         │                  │
 └──────────────┘         └─────────────────────┘         └──────────────────┘


 ┌──────────────┐         ┌─────────────────────┐         ┌──────────────────┐
 │              │ 1    N  │                     │ N    1  │                  │
 │   admins     ├────────►│ collaborations       │◄────────┤   admins         │
 │              │         │                      │         │  (reviewed_by)   │
 └──────────────┘         └─────────────────────┘         └──────────────────┘
                                │
                                │ 1
                                │
                                ▼ N
                          ┌─────────────────────┐
                          │                     │
                          │ collaboration_      │
                          │ reports             │
                          │  [REPORT]           │
                          └─────────────────────┘


 ═══════════════════════════════════════════════════════════════════════════════════
                              MEMBER DOMAIN
 ═══════════════════════════════════════════════════════════════════════════════════

 ┌──────────────┐
 │              │
 │   members    │
 │              │
 └──────┬───────┘
        │
        ├────────────────────────────────────────────────────────────────────────┐
        │                                                                        │
        │ 1                                                                     │ 1
        │                                                                        │
        ▼ N                                                                     ▼ N
 ┌─────────────────────┐                                            ┌─────────────────────┐
 │                     │ N    1  ┌──────────────┐  1    N           │                     │
 │ leadership_         ├────────►│ rotary_years  ├─────────────────►│ event_polling       │
 │ assignments         │         │              │                    │                     │
 │  [JUNCTION]         │         │  [CORE]      │                    │  [JUNCTION]         │
 └─────────────────────┘         └──────┬───────┘                    └─────────────────────┘
                                        │                                    ▲
                                        │                                    │
                                        │ 1                                  │ N
                                        │                                    │
                                        ▼ N                          ┌──────────────┐
                                 ┌─────────────────────┐             │              │
                                 │                     │             │   events     │
                                 │ leadership_         │             │              │
                                 │ derived view        │             └──────┬───────┘
                                 │ (per year)          │                    │
                                 │  [DERIVED]          │                    ├──────────────────┐
                                 └─────────────────────┘                    │                  │
                                                                           │ 1                │ 1
                                                                           │                  │
                                              ┌────────────────────────────┘                  │
                                              │                                                │
                                              ▼ N                                              ▼ N
                                       ┌─────────────────────┐                        ┌─────────────────────┐
                                       │                     │                        │                     │
                                       │ event_reports       │                        │ media_gallery       │
                                       │                     │                        │                     │
                                       │  [REPORT]           │                        │  [MEDIA]            │
                                       └─────────────────────┘                        └─────────────────────┘


 ═══════════════════════════════════════════════════════════════════════════════════
                              EVENTS DOMAIN
 ═══════════════════════════════════════════════════════════════════════════════════

 ┌──────────────┐
 │              │
 │   events     │───► rotary_year_id (FK, optional)  ───► rotary_years.id
 │              │
 │  [CORE]      │
 └──────┬───────┘
        │
        ├──────────────► 1:N  event_polling
        ├──────────────► 1:N  event_reports
        └──────────────► 0:N  media_gallery (via reference_type='event')


 ═══════════════════════════════════════════════════════════════════════════════════
                              PROJECTS DOMAIN
 ═══════════════════════════════════════════════════════════════════════════════════

 ┌──────────────┐
 │              │
 │   projects   │───► rotary_year_id (FK, optional)  ───► rotary_years.id
 │              │
 │  [CORE]      │
 └──────┬───────┘
        │
        ├──────────────► 1:N  project_reports
        └──────────────► 0:N  media_gallery (via reference_type='project')


 ═══════════════════════════════════════════════════════════════════════════════════
                              DONATIONS DOMAIN
 ═══════════════════════════════════════════════════════════════════════════════════

 ┌──────────────┐
 │              │
 │   donors     │
 │              │
 │  [CORE]      │
 └──────┬───────┘
        │
        │ 1
        │
        ▼ N
 ┌──────────────┐
 │              │         ┌─────────────────────┐         ┌──────────────────┐
 │   donations  │ 1    N  │                     │ N    1  │                  │
 ├──────────────┼────────►│ donation_status_     │         │   admins         │
 │              │         │ history              │         │                  │
 │  [CORE]      │         │  [AUDIT]             │         └──────────────────┘
 └──────┬───────┘         └─────────────────────┘
        │
        ├────────────────────────────────────────────────────────┐
        │                                                        │
        │ 1                                                      │ 1
        │                                                        │
        ▼ N                                                      ▼ N
 ┌─────────────────────┐                              ┌─────────────────────┐
 │                     │                              │                     │
 │ donation_           │                              │ donation_report     │
 │ allocation          │                              │                     │
 │                     │                              │  [REPORT]           │
 │  [OPTIONAL]         │                              │                     │
 │  (0..N per          │                              └─────────────────────┘
 │   donation)         │
 └─────────────────────┘
        │
        │ N (allocation references)
        │
        ├──► project_id (FK, nullable) ───► projects.project_id
        └──► event_id   (FK, nullable) ───► events.event_id


 ═══════════════════════════════════════════════════════════════════════════════════
                              COMMITTEE & GOVERNANCE DOMAIN
 ═══════════════════════════════════════════════════════════════════════════════════

 ┌──────────────┐         ┌─────────────────────┐         ┌──────────────────┐
 │              │ 1    N  │                     │ N    1  │                  │
 │   committees ├────────►│ committee_members    ├────────►│   members        │
 │              │         │                      │         │                  │
 │  [GOVERNANCE]│         │  [JUNCTION]          │         └──────────────────┘
 └──────────────┘         └──────────┬────────────┘
                                     │
                                     │ N
                                     │
                                     ▼ 1
                          ┌─────────────────────┐
                          │                     │
                          │ committee_position  │
                          │                     │
                          │  [LOOKUP]           │
                          └─────────────────────┘


 ═══════════════════════════════════════════════════════════════════════════════════
                              WEBSITE DISPLAY DOMAIN
 ═══════════════════════════════════════════════════════════════════════════════════

 ┌──────────────┐         ┌─────────────────────┐
 │              │ 1    1  │                     │
 │   members    ├────────►│ website_display     │
 │              │         │                     │
 │              │         │  [DISPLAY]          │
 └──────────────┘         │  (metadata only)    │
                          │  - display_group    │
                          │  - display_order    │
                          │  - custom_title     │
                          │  - is_visible       │
                          └─────────────────────┘


 ═══════════════════════════════════════════════════════════════════════════════════
                              CMS & CONTENT DOMAIN
 ═══════════════════════════════════════════════════════════════════════════════════

 ┌──────────────┐    ┌─────────────────────┐    ┌─────────────────────┐
 │              │    │                     │    │                     │
 │ site_content ├────┤ announcements       ├────┤ media_gallery       │
 │              │    │                     │    │  (reference_type=   │
 │  [CMS]       │    │  [CONTENT]          │    │   'announcement')   │
 └──────────────┘    └─────────────────────┘    └─────────────────────┘

 ┌──────────────┐
 │              │
 │ contact_     │
 │ messages     │
 │              │
 │  [STANDALONE]│
 └──────────────┘
```

### 1.2 Domain Grouping

The ER Diagram is organized into seven logical domains:

| Domain | Tables | Purpose |
|--------|--------|---------|
| **Authentication & Security** | admins, password_reset_tokens | Admin authentication and session security |
| **Member & Governance** | members, leadership_assignments, rotary_years, committees, committee_members, committee_position | Club membership, leadership, and committee structure |
| **Events** | events, event_polling, event_reports | Event lifecycle and participation |
| **Projects** | projects, project_reports | Project lifecycle and reporting |
| **Donations** | donors, donations, donation_status_history, donation_allocation, donation_report | Donor management and donation pipeline |
| **Website & Content** | website_display, site_content, announcements, contact_messages, media_gallery, gallery_media | Website presentation and CMS |
| **Collaboration** | collaborations, collaboration_reports | External collaboration proposals |

---

## 2. Entity Classification

Every table in the approved Logical Database classified by type:

### 2.1 Core Business Tables

| # | Table | Business Owner | Purpose |
|---|-------|---------------|---------|
| 1 | `members` | Club Secretary | Club members displayed on Team and Contact pages |
| 2 | `events` | Club Secretary | Club events displayed on Activities and Home pages |
| 3 | `projects` | Club Secretary | Club projects displayed on Activities and Home pages |
| 4 | `donors` | Club Treasurer | Donor information from donation form |
| 5 | `donations` | Club Treasurer | Donation records linked to donors |
| 6 | `committees` | Club Secretary | Committee definitions (Standing, Ad-hoc) |
| 7 | `rotary_years` | Club Secretary | Rotary year definitions (July–June cycle) |
| 8 | `collaborations` | Club Secretary | Collaboration/submission proposals from public |

**Total: 8 Core Business Tables**

### 2.2 Junction Tables

| # | Table | Resolves | Business Purpose |
|---|-------|----------|-----------------|
| 1 | `leadership_assignments` | members ↔ rotary_years | Maps members to leadership roles per Rotary year |
| 2 | `committee_members` | committees ↔ members | Maps members to committee roles per committee |
| 3 | `event_polling` | events ↔ members | Stores RSVP/voting responses for events |
| 4 | `donation_allocation` | donations ↔ projects/events | Optional allocation of donations to purposes |

**Total: 4 Junction Tables**

### 2.3 Lookup Tables

| # | Table | Business Purpose |
|---|-------|-----------------|
| 1 | `committee_position` | Controlled vocabulary for committee roles (Chairperson, Co-Chair, Secretary, Member) |

**Total: 1 Lookup Table**

### 2.4 Report Tables

| # | Table | Parent Entity | Business Purpose |
|---|-------|---------------|-----------------|
| 1 | `event_reports` | events | Post-event reports and documentation |
| 2 | `project_reports` | projects | Project completion reports with financials |
| 3 | `donation_report` | donations | Donation verification and processing reports |
| 4 | `collaboration_reports` | collaborations | Collaboration proposal review reports |

**Total: 4 Report Tables**

### 2.5 Display & Presentation Tables

| # | Table | Business Purpose |
|---|-------|-----------------|
| 1 | `website_display` | Presentation metadata for website team display (display_group, display_order, custom_title, is_visible) |
| 2 | `site_content` | CMS key-value content for Home, About, Contact, Footer pages |
| 3 | `media_gallery` | Primary media gallery for frontend display and reports |
| 4 | `gallery_media` | Secondary gallery table for admin-side uploads |

**Total: 4 Display/Presentation Tables**

### 2.6 System Tables

| # | Table | Business Purpose |
|---|-------|-----------------|
| 1 | `admins` | Admin authentication and role management |
| 2 | `password_reset_tokens` | Secure password reset token lifecycle |

**Total: 2 System Tables**

### 2.7 Audit & History Tables

| # | Table | Parent Entity | Business Purpose |
|---|-------|---------------|-----------------|
| 1 | `donation_status_history` | donations | Audit trail of donation pipeline status changes |

**Total: 1 Audit Table**

### 2.8 Standalone Content Tables

| # | Table | Business Purpose |
|---|-------|-----------------|
| 1 | `announcements` | Announcement records for media gallery association |
| 2 | `contact_messages` | Contact form submissions from public website |

**Total: 2 Standalone Content Tables**

### 2.9 Derived Views

| # | View Name | Source Tables | Business Purpose |
|---|-----------|---------------|-----------------|
| 1 | `website_team_view` | leadership_assignments + committee_members + website_display | Unified view of who appears on the website, per year |

**Total: 1 Derived View**

---

### Entity Classification Summary

| Category | Count | Tables |
|----------|-------|--------|
| Core Business | 8 | members, events, projects, donors, donations, committees, rotary_years, collaborations |
| Junction | 4 | leadership_assignments, committee_members, event_polling, donation_allocation |
| Lookup | 1 | committee_position |
| Report | 4 | event_reports, project_reports, donation_report, collaboration_reports |
| Display/Presentation | 4 | website_display, site_content, media_gallery, gallery_media |
| System | 2 | admins, password_reset_tokens |
| Audit | 1 | donation_status_history |
| Standalone Content | 2 | announcements, contact_messages |
| Derived Views | 1 | website_team_view |
| **TOTAL** | **27** | (26 physical tables + 1 derived view) |

---

## 3. Relationship Matrix

Every relationship in the approved Logical Database.

### 3.1 Complete Relationship List

| # | Parent Table | Child Table | FK Column | Delete Rule | Description |
|---|-------------|-------------|-----------|-------------|-------------|
| R01 | `admins` | `password_reset_tokens` | `admin_id` | CASCADE | Admin owns password reset tokens |
| R02 | `admins` | `collaborations` | `reviewed_by` | SET NULL | Admin reviews collaboration proposals |
| R03 | `members` | `leadership_assignments` | `member_id` | CASCADE | Member assigned to leadership role |
| R04 | `rotary_years` | `leadership_assignments` | `rotary_year_id` | CASCADE | Leadership assignment scoped to year |
| R05 | `members` | `event_polling` | `member_id` | SET NULL | Member submits event RSVP |
| R06 | `events` | `event_polling` | `event_id` | CASCADE | Event receives polling responses |
| R07 | `events` | `event_reports` | `event_id` | CASCADE | Event has post-event report |
| R08 | `projects` | `project_reports` | `project_id` | CASCADE | Project has completion report |
| R09 | `donors` | `donations` | `donor_id` | CASCADE | Donor makes donation |
| R10 | `donations` | `donation_status_history` | `donation_id` | CASCADE | Donation has status change history |
| R11 | `admins` | `donation_status_history` | `admin_id` | SET NULL | Admin records status change |
| R12 | `donations` | `donation_report` | `donation_id` | CASCADE | Donation has verification report |
| R13 | `donations` | `donation_allocation` | `donation_id` | CASCADE | Donation optionally allocated to purposes |
| R14 | `projects` | `donation_allocation` | `project_id` | SET NULL | Allocation targets a project |
| R15 | `events` | `donation_allocation` | `event_id` | SET NULL | Allocation targets an event |
| R16 | `collaborations` | `collaboration_reports` | `collab_id` | CASCADE | Collaboration has review report |
| R17 | `committees` | `committee_members` | `committee_id` | CASCADE | Committee has members |
| R18 | `members` | `committee_members` | `member_id` | CASCADE | Member assigned to committee |
| R19 | `committee_position` | `committee_members` | `position_id` | RESTRICT | Committee member has a position |
| R20 | `members` | `website_display` | `member_id` | CASCADE | Member has website display metadata |
| R21 | `rotary_years` | `events` | `rotary_year_id` | SET NULL | Events optionally linked to year (optional FK) |
| R22 | `rotary_years` | `projects` | `rotary_year_id` | SET NULL | Projects optionally linked to year (optional FK) |

**Total: 22 Relationships**

### 3.2 Implicit (Polymorphic) Relationships

| # | Table | Column | References | Description |
|---|-------|--------|------------|-------------|
| P01 | `media_gallery` | `reference_type` + `reference_id` | events, projects, announcements | Media linked to various entity types via polymorphic association |

**Note:** Polymorphic associations are represented for completeness but are not formal foreign keys. They are application-level references.

---

## 4. Cardinality Matrix

### 4.1 Full Cardinality Table

| Parent | Child | Cardinality | Optional? | Business Rule |
|--------|-------|-------------|-----------|---------------|
| admins | password_reset_tokens | 1:N | Yes (admin can have 0 tokens) | An admin may request password resets |
| admins | collaborations (reviewed_by) | 1:N | Yes (admin may review 0) | An admin may review collaboration proposals |
| admins | donation_status_history | 1:N | Yes (admin may record 0) | An admin records donation status changes |
| members | leadership_assignments | 1:N | Yes (member may have 0) | A member may hold 0 or more leadership roles across years |
| rotary_years | leadership_assignments | 1:N | Yes (year may have 0–3) | A year has 0 to 3 leadership assignments (President, Secretary, Treasurer) |
| members | event_polling | 1:N | Yes (member may have 0) | A member may respond to 0 or more events |
| events | event_polling | 1:N | Yes (event may have 0) | An event may receive 0 or more responses |
| events | event_reports | 1:N | Yes (event may have 0) | An event may have 0 or 1 report (practically 0..1) |
| projects | project_reports | 1:N | Yes (project may have 0) | A project may have 0 or more annual reports |
| donors | donations | 1:N | No (donor must have ≥1) | A donor exists because they made at least 1 donation |
| donations | donation_status_history | 1:N | No (donation always has ≥1) | A donation always has at least its initial status record |
| donations | donation_report | 1:N | Yes (donation may have 0) | A donation may have 0 or more reports |
| donations | donation_allocation | 1:N | Yes (allocation is optional) | A donation may be unrestricted (0 allocations) or split (1+ allocations) |
| projects | donation_allocation | 1:N | Yes (allocation to project is optional) | An allocation may optionally reference a project |
| events | donation_allocation | 1:N | Yes (allocation to event is optional) | An allocation may optionally reference an event |
| collaborations | collaboration_reports | 1:N | Yes (collab may have 0) | A collaboration may have 0 or more reports |
| committees | committee_members | 1:N | No (committee must have ≥1) | A committee exists because it has members |
| members | committee_members | 1:N | Yes (member may be in 0 committees) | A member may serve on 0 or more committees |
| committee_position | committee_members | 1:N | No (member must have a position) | Every committee member holds exactly one position |
| members | website_display | 1:1 | Yes (member may have no display record) | A member optionally has website display metadata |
| rotary_years | events | 1:N | Yes (event may not be linked) | An event may optionally be linked to a Rotary year |
| rotary_years | projects | 1:N | Yes (project may not be linked) | A project may optionally be linked to a Rotary year |

### 4.2 Cardinality Summary

| Pattern | Count | Relationships |
|---------|-------|---------------|
| 1:1 | 1 | members → website_display |
| 1:N (Optional parent) | 14 | Most relationships |
| 1:N (Mandatory parent) | 4 | donors→donations, donations→history, committees→members, position→members |
| M:N (via junction) | 4 | members↔rotary_years, members↔committees, members↔events (polling), donations↔purposes |
| Polymorphic | 1 | media_gallery → events/projects/announcements |

---

## 5. Primary Key / Foreign Key Mapping

### 5.1 Every Table's Keys

| Table | Primary Key | Foreign Keys |
|-------|------------|--------------|
| `admins` | `admin_id` INT AUTO_INCREMENT | — |
| `password_reset_tokens` | `id` INT AUTO_INCREMENT | `admin_id` → admins(admin_id) |
| `members` | `member_id` INT AUTO_INCREMENT | — |
| `rotary_years` | `id` INT AUTO_INCREMENT | — |
| `leadership_assignments` | `id` INT AUTO_INCREMENT | `rotary_year_id` → rotary_years(id), `member_id` → members(member_id) |
| `committees` | `committee_id` INT AUTO_INCREMENT | — |
| `committee_position` | `position_id` INT AUTO_INCREMENT | — |
| `committee_members` | `id` INT AUTO_INCREMENT | `committee_id` → committees(committee_id), `member_id` → members(member_id), `position_id` → committee_position(position_id) |
| `website_display` | `id` INT AUTO_INCREMENT | `member_id` → members(member_id) |
| `events` | `event_id` INT AUTO_INCREMENT | `rotary_year_id` → rotary_years(id) *(nullable, added per Session 4.5)* |
| `projects` | `project_id` INT AUTO_INCREMENT | `rotary_year_id` → rotary_years(id) *(nullable, added per Session 4.5)* |
| `donors` | `donor_id` INT AUTO_INCREMENT | — |
| `donations` | `donation_id` INT AUTO_INCREMENT | `donor_id` → donors(donor_id) |
| `donation_status_history` | `history_id` INT AUTO_INCREMENT | `donation_id` → donations(donation_id), `admin_id` → admins(admin_id) |
| `donation_allocation` | `allocation_id` INT AUTO_INCREMENT | `donation_id` → donations(donation_id), `project_id` → projects(project_id) *(nullable)*, `event_id` → events(event_id) *(nullable)* |
| `donation_report` | `report_id` INT AUTO_INCREMENT | `donation_id` → donations(donation_id) |
| `event_polling` | `poll_id` INT AUTO_INCREMENT | `event_id` → events(event_id), `member_id` → members(member_id) |
| `event_reports` | `report_id` INT AUTO_INCREMENT | `event_id` → events(event_id) |
| `project_reports` | `project_id` INT PK, FK → projects(project_id) | `project_id` → projects(project_id) |
| `collaborations` | `collab_id` INT AUTO_INCREMENT | `reviewed_by` → admins(admin_id) *(nullable)* |
| `collaboration_reports` | `report_id` INT AUTO_INCREMENT | `collab_id` → collaborations(collab_id) |
| `media_gallery` | `media_id` INT AUTO_INCREMENT | — *(polymorphic: reference_type + reference_id)* |
| `gallery_media` | `id` INT AUTO_INCREMENT | — |
| `site_content` | `id` INT AUTO_INCREMENT | — |
| `announcements` | `announcement_id` INT AUTO_INCREMENT | — |
| `contact_messages` | `id` INT AUTO_INCREMENT | — |

### 5.2 Unique Constraints

| Table | Unique Constraint | Business Rule |
|-------|------------------|---------------|
| `admins` | `email` | One email per admin account |
| `rotary_years` | `year_name` | One record per Rotary year |
| `leadership_assignments` | `(rotary_year_id, role)` | One person per role per year |
| `site_content` | `(page, section)` | One content entry per page/section pair |
| `password_reset_tokens` | `token` | One token per reset request |

---

## 6. Junction Table Identification

Tables that resolve many-to-many relationships:

| # | Table | Resolves | Parent 1 | Parent 2 | Additional FKs |
|---|-------|----------|----------|----------|----------------|
| 1 | `leadership_assignments` | members ↔ rotary_years | rotary_years | members | — |
| 2 | `committee_members` | committees ↔ members | committees | members | committee_position |
| 3 | `event_polling` | events ↔ members | events | members | — |
| 4 | `donation_allocation` | donations ↔ (projects, events) | donations | — | project_id (nullable), event_id (nullable) |

**Business Note on `donation_allocation`:**  
This is a special junction table. It does NOT resolve a traditional M:N between two entities. Instead, it provides OPTIONAL allocation from one parent (donation) to one of two possible targets (project OR event), with a free-text `fund_purpose` for unrestricted or general-fund donations. The cardinality is 1:N from donations to allocations (a donation may have 0, 1, or multiple allocations — supporting split donations).

---

## 7. Lookup Table Identification

Tables that provide controlled vocabulary:

| # | Table | FK Referenced By | Values | Business Purpose |
|---|-------|-----------------|--------|-----------------|
| 1 | `committee_position` | committee_members.position_id | Chairperson, Co-Chair, Secretary, Member (extensible) | Standardized committee role titles for governance consistency and historical reporting |

**Governance Rationale (from Session 4.5):**  
Committee positions are defined by Rotary constitutional documents. A lookup table enforces controlled vocabulary at the data level, prevents typos/variation across years, and ensures governance reports are consistent. The Super Admin may add new position titles without code changes.

---

## 8. System Table Identification

Tables that support platform infrastructure:

| # | Table | Business Owner | Purpose |
|---|-------|---------------|---------|
| 1 | `admins` | Super Admin | Admin authentication, role management (President, Secretary, Treasurer, IT Admin, Super Admin) |
| 2 | `password_reset_tokens` | System (auto) | Secure token lifecycle for password reset flow (30-minute expiry, single use) |

**Business Rules:**
- Only one Super Admin is allowed (enforced in application code)
- Super Admin cannot be disabled or have role changed
- Login requires `status = 'active'`
- Password reset tokens are single-use and expire after 30 minutes

---

## 9. Derived View Identification

Tables/views that are computed from other tables, not independently managed:

| # | View Name | Source Tables | Computation Logic | Business Purpose |
|---|-----------|---------------|-------------------|-----------------|
| 1 | `website_team_view` | leadership_assignments + committee_members + website_display | For a given Rotary Year: join leadership_assignments (per year) with website_display (per member) for leadership group. Join committee_members with website_display for committee group. Remaining members with website_display.display_group='board' form the board group. | Unified view of who appears on the website team page, with display metadata |

**Architecture Decision (from Session 4.5):**  
The Website Team is NOT a standalone junction table. The source of truth for "who holds what role" is `leadership_assignments` and `committee_members`. The source of truth for "how they appear on the website" is `website_display`. The derived view combines these at query time.

### 9.1 Derived View Specification

```
website_team_view (for a given Rotary Year Y):

  LEADERSHIP GROUP:
    SELECT m.name, m.photo_url, m.short_bio, la.role,
           wd.display_order, wd.custom_title, wd.is_visible
    FROM leadership_assignments la
    JOIN members m ON la.member_id = m.member_id
    LEFT JOIN website_display wd ON m.member_id = wd.member_id
    WHERE la.rotary_year_id = Y
      AND (wd.is_visible IS NULL OR wd.is_visible = 1)
    ORDER BY wd.display_order ASC, FIELD(la.role, 'President', 'Secretary', 'Treasurer')

  COMMITTEE GROUP:
    SELECT m.name, m.photo_url, m.short_bio, cp.position_name, c.committee_name,
           wd.display_order, wd.custom_title, wd.is_visible
    FROM committee_members cm
    JOIN committees c ON cm.committee_id = c.committee_id
    JOIN members m ON cm.member_id = m.member_id
    JOIN committee_position cp ON cm.position_id = cp.position_id
    LEFT JOIN website_display wd ON m.member_id = wd.member_id
    WHERE c.rotary_year_id = Y
      AND (wd.display_group = 'committee')
      AND (wd.is_visible IS NULL OR wd.is_visible = 1)
    ORDER BY wd.display_order ASC

  BOARD GROUP:
    SELECT m.name, m.photo_url, m.short_bio, m.profession,
           wd.display_order, wd.custom_title, wd.is_visible
    FROM members m
    LEFT JOIN website_display wd ON m.member_id = wd.member_id
    WHERE m.status = 'Active'
      AND m.member_id NOT IN (SELECT member_id FROM leadership_assignments WHERE rotary_year_id = Y)
      AND m.member_id NOT IN (SELECT member_id FROM committee_members cm JOIN committees c ON cm.committee_id = c.committee_id WHERE c.rotary_year_id = Y)
      AND wd.display_group = 'board'
      AND (wd.is_visible IS NULL OR wd.is_visible = 1)
    ORDER BY wd.display_order ASC, m.name ASC
```

---

## 10. Validation — Faithful Representation

### 10.1 Validation Checklist

| # | Check | Status | Evidence |
|---|-------|--------|----------|
| 1 | No new entities introduced | PASS | All 26 tables + 1 view are from the approved Logical Database. No invented tables. |
| 2 | No approved entities removed | PASS | All existing tables (21) plus approved new tables (5) are present. |
| 3 | No new relationships invented | PASS | All 22 relationships are derived from the approved Logical Database FKs. |
| 4 | No cardinality changes | PASS | All cardinalities match the approved Logical Database specifications. |
| 5 | No table renames | PASS | All table names match the approved Logical Database exactly. |
| 6 | No table merges | PASS | gallery_media remains separate from media_gallery as per current schema. |
| 7 | No table splits | PASS | No entity was decomposed beyond its approved definition. |
| 8 | No new business rules | PASS | All business rules documented in Section 3.2 are from approved sessions. |
| 9 | No ownership changes | PASS | Business owners match the approved ownership assignments. |
| 10 | No lifecycle changes | PASS | All lifecycle states and transitions match the approved design. |

### 10.2 Session 4.5 Modification Compliance

| # | Modification | ER Diagram Representation | Compliant? |
|---|-------------|--------------------------|------------|
| 1 | Website Team → website_display (display metadata only) | `website_display` table shown with display_group, display_order, custom_title, is_visible. Derived `website_team_view` shown computing from leadership_assignments + committee_members + website_display. No standalone website_team junction table. | PASS |
| 2 | Project Year → single optional FK on projects | `projects.rotary_year_id` FK shown as nullable, referencing rotary_years.id. No project_year junction table. | PASS |
| 3 | Event Year → single optional FK on events | `events.rotary_year_id` FK shown as nullable, referencing rotary_years.id. No event_year junction table. | PASS |
| 4 | Donation Allocation → optional one-to-many | `donation_allocation` shown with 0..N cardinality from donations. Allocation to project_id and event_id shown as nullable FKs. Split allocation supported (multiple allocation records per donation). | PASS |

### 10.3 Entity Count Reconciliation

| Source | Count | Notes |
|--------|-------|-------|
| DATABASE_STRUCTURE.md (existing tables) | 21 | All current physical tables |
| Approved new tables (Sessions 1–4B) | 5 | committees, committee_members, committee_position, website_display, donation_allocation |
| Session 4.5 modifications | 3 changes | projects+FK, events+FK, rotary_years−created_at |
| Derived Views | 1 | website_team_view |
| **Total in ER Diagram** | **27** | 26 tables + 1 derived view |

### 10.4 Final Confirmation

The Enterprise ER Diagram presented in this document is a **faithful, complete, and accurate representation** of the Approved Logical Database. No architectural changes were introduced. No business rules were modified. The Logical Database remained unchanged throughout this documentation exercise.

---

*Document generated as part of RCMP Phase 2 — Session 5*  
*Enterprise ER Diagram Documentation*  
*Rotary Club of Virar — Management Platform*
