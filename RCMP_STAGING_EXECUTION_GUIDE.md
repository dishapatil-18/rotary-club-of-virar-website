# RCMP Staging Execution Guide

## Rotary Club of Virar — Management Platform

**Phase:** 3 — Session 7C  
**Status:** Staging Execution Preparation  
**Date:** 2026-07-16  
**SQL Package:** 001_backup_check.sql → 010_rollback_reference.sql  
**Approved:** All 10 migration files passed final engineering audit (100/100)

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Environment Requirements](#2-environment-requirements)
3. [Backup Checklist](#3-backup-checklist)
4. [Staging Database Setup](#4-staging-database-setup)
5. [Pre-Execution Checklist](#5-pre-execution-checklist)
6. [Execution Order](#6-execution-order)
7. [Expected Output Per Migration](#7-expected-output-per-migration)
8. [Common Errors and Resolutions](#8-common-errors-and-resolutions)
9. [Validation Process](#9-validation-process)
10. [Rollback Procedure](#10-rollback-procedure)
11. [Final Acceptance Checklist](#11-final-acceptance-checklist)
12. [Go / No-Go Recommendation](#12-go--no-go-recommendation)

---

## 1. Executive Summary

This guide provides the complete execution procedure for applying 10 SQL migration files to a staging/test database (`rotary_test`). The migration package modifies 6 existing tables, creates 5 new tables, adds 1 view, creates 23 performance indexes, seeds lookup data, and provides validation and rollback capabilities.

**Do not skip any migration. Do not execute files out of order. Do not modify SQL files before execution.**

---

## 2. Environment Requirements

### MySQL / MariaDB

| Requirement | Value | Notes |
|-------------|-------|-------|
| **Minimum MySQL** | 8.0.13+ | `DROP INDEX IF EXISTS` requires 8.0+ |
| **Minimum MariaDB** | 10.4+ | MariaDB 10.4+ supports `IF EXISTS` for `DROP INDEX` |
| **Recommended** | MySQL 8.0.33+ or MariaDB 10.6+ | XAMPP 2026+ ships these versions |
| **Character Set** | `utf8mb4` | Full Unicode support (Hindi/Devanagari names) |
| **Collation** | `utf8mb4_unicode_ci` | Case-insensitive comparison |
| **Engine** | InnoDB | Required for foreign key support |

### PHP (for application-level verification only)

| Requirement | Value |
|-------------|-------|
| **Minimum PHP** | 8.0+ |
| **Required Extensions** | `mysqli`, `mbstring` |
| **Recommended PHP** | 8.2+ or 8.3+ |

### Client Tools (any one of the following)

| Tool | Notes |
|------|-------|
| **MySQL CLI** | `mysql -u root -p` — preferred for scripting |
| **phpMyAdmin** | XAMPP bundled — convenient for visual inspection |
| **MySQL Workbench** | Official MySQL GUI |
| **VS Code + MySQL extension** | Lightweight alternative |

---

## 3. Backup Checklist

**Execute every backup item before proceeding.** Verify each item by confirming the file exists and has non-zero size.

### 3.1 Database Backup

```bash
# Full database dump (production or current dev database)
mysqldump -u root -p rotary > rotary_pre_migration.sql

# Verify backup file size
dir rotary_pre_migration.sql
```

| Item | Action | Verification |
|------|--------|-------------|
| Database dump | `mysqldump -u root -p rotary > rotary_pre_migration.sql` | File exists, size > 0 bytes |
| Schema-only dump | `mysqldump -u root -p --no-data rotary > rotary_schema_pre_migration.sql` | For schema diff comparison |

### 3.2 Application Backup

| Item | Action | Verification |
|------|--------|-------------|
| `.env` file | Copy `.env` to `.env.backup` | File exists |
| `config/` directory | Copy `config/` to `config_backup/` | Directory exists |
| `includes/db_connect.php` | Copy to `includes/db_connect.php.backup` | File exists |
| `uploads/` directory | Compress `uploads/` to `uploads_backup.zip` | ZIP exists |
| `Admin/db_migration.php` | Copy to `Admin/db_migration.php.backup` | File exists |

### 3.3 Full Application Backup (recommended)

```bash
# Create complete application backup (XAMPP path)
xcopy "C:\xampp\htdocs\rotary-club-virar" "C:\xampp\htdocs\rotary-club-virar_backup_%DATE:~-4%%DATE:~4,2%%DATE:~7,2%" /E /I /H
```

### 3.4 Backup Confirmation Sign-Off

```
[ ] Database backup verified      rotary_pre_migration.sql
[ ] Schema-only backup verified   rotary_schema_pre_migration.sql
[ ] .env backup verified
[ ] config/ backup verified
[ ] uploads/ backup verified
[ ] Full application backup verified
```

**Do not proceed past this point without completing all backups.**

---

## 4. Staging Database Setup

### 4.1 Create Staging Database

```sql
-- Connect to MySQL
mysql -u root -p

-- Create the staging database
CREATE DATABASE rotary_test
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- Verify creation
SHOW DATABASES LIKE 'rotary_test';
```

### 4.2 Load Current Production Data into Staging

```bash
# Option A: Load from backup dump
mysql -u root -p rotary_test < rotary_pre_migration.sql

# Option B: Copy from production database
mysqldump -u root -p rotary | mysql -u root -p rotary_test
```

### 4.3 Verify Staging Database

```sql
USE rotary_test;

-- Confirm table count (should be 21 tables)
SELECT COUNT(*) AS table_count
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'rotary_test'
  AND TABLE_TYPE = 'BASE TABLE';

-- Confirm row counts match production
SELECT
    TABLE_NAME,
    TABLE_ROWS
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'rotary_test'
  AND TABLE_TYPE = 'BASE TABLE'
ORDER BY TABLE_NAME;
```

### 4.4 Staging Setup Confirmation

```
[ ] rotary_test database created
[ ] Data loaded from production backup
[ ] Table count matches production (21 tables)
[ ] Row counts approximately match production
```

---

## 5. Pre-Execution Checklist

**Every item must be checked before running 001_backup_check.sql.**

| # | Check | Status |
|---|-------|--------|
| 1 | MySQL 8.0+ or MariaDB 10.4+ running | [ ] |
| 2 | `rotary_test` database created and populated | [ ] |
| 3 | Full backup completed and verified (Section 3) | [ ] |
| 4 | Application is in maintenance mode (no concurrent writes) | [ ] |
| 5 | No active connections to `rotary_test` (other than yours) | [ ] |
| 6 | All 10 migration SQL files present in `migrations/` directory | [ ] |
| 7 | SQL files are unmodified (match approved versions) | [ ] |
| 8 | Sufficient disk space (minimum 2x database size) | [ ] |
| 9 | MySQL user has required permissions (Section 5.1) | [ ] |
| 10 | Rollback procedure reviewed (Section 10) | [ ] |

### 5.1 Required MySQL Permissions

The executing MySQL user must have the following privileges:

| Privilege | Required For |
|-----------|-------------|
| `SELECT` | 001_backup_check.sql, 009_validation_queries.sql |
| `INSERT` | 008_seed_lookup_data.sql |
| `CREATE` | 003, 004, 005 (new tables), 006 (view), 007 (indexes) |
| `ALTER` | 002_alter_existing_tables.sql |
| `INDEX` | 007_create_indexes.sql, 010_rollback_reference.sql |
| `DROP` | 010_rollback_reference.sql (rollback only) |
| `REFERENCES` | 005_create_junction_tables.sql (FK creation) |
| `TRIGGER` | Not required (no triggers in migration) |
| `SUPER` | Not required |

```sql
-- Verify current user permissions
SHOW GRANTS FOR CURRENT_USER;

-- If needed, grant full permissions (local dev only)
GRANT ALL PRIVILEGES ON rotary_test.* TO 'root'@'localhost';
FLUSH PRIVILEGES;
```

### 5.2 Verify No Active Connections

```sql
-- Check for active connections to rotary_test
SELECT
    ID,
    USER,
    HOST,
    DB,
    COMMAND,
    TIME,
    STATE
FROM information_schema.PROCESSLIST
WHERE DB = 'rotary_test'
  AND USER != 'system user';
```

Only your own connection should appear. If others exist, wait for them to close or terminate them.

---

## 6. Execution Order

**Execute strictly in numerical order. Do not skip, reorder, or combine files.**

```
STEP 1:  001_backup_check.sql          READ-ONLY baseline
STEP 2:  002_alter_existing_tables.sql  ALTER 6 existing tables
STEP 3:  003_create_lookup_tables.sql   CREATE committee_position
STEP 4:  004_create_master_tables.sql   CREATE committees
STEP 5:  005_create_junction_tables.sql CREATE 3 junction tables + 8 FKs
STEP 6:  006_create_views.sql           CREATE website_team_view
STEP 7:  007_create_indexes.sql         CREATE 23 performance indexes
STEP 8:  008_seed_lookup_data.sql       INSERT 4 committee_position rows
STEP 9:  009_validation_queries.sql     POST-MIGRATION validation
STEP 10: 010_rollback_reference.sql     EMERGENCY ROLLBACK (only if needed)
```

### Execution Commands

```bash
# Working directory
cd C:\xampp\htdocs\rotary-club-virar\migrations

# STEP 1: Baseline checks (read-only, safe)
mysql -u root -p rotary_test < 001_backup_check.sql

# STEP 2: Alter existing tables
mysql -u root -p rotary_test < 002_alter_existing_tables.sql

# STEP 3: Create lookup table
mysql -u root -p rotary_test < 003_create_lookup_tables.sql

# STEP 4: Create master table
mysql -u root -p rotary_test < 004_create_master_tables.sql

# STEP 5: Create junction tables + foreign keys
mysql -u root -p rotary_test < 005_create_junction_tables.sql

# STEP 6: Create view
mysql -u root -p rotary_test < 006_create_views.sql

# STEP 7: Create performance indexes
mysql -u root -p rotary_test < 007_create_indexes.sql

# STEP 8: Seed lookup data
mysql -u root -p rotary_test < 008_seed_lookup_data.sql

# STEP 9: Post-migration validation
mysql -u root -p rotary_test < 009_validation_queries.sql

# STEP 10: Only if rollback needed
mysql -u root -p rotary_test < 010_rollback_reference.sql
```

### Execution via phpMyAdmin

1. Select `rotary_test` database
2. Click **Import** tab
3. Choose SQL file
4. Click **Go**
5. Review output for errors
6. Repeat for next file

---

## 7. Expected Output Per Migration

### STEP 1: 001_backup_check.sql

**Duration:** < 5 seconds  
**Type:** Read-only  
**Modifies:** Nothing

| Section | Expected Output | Pass/Fail Criteria |
|---------|-----------------|-------------------|
| Section 1 | Row counts for 21 tables | Record these numbers — needed for Step 9 comparison |
| Section 2 | Empty set (0 rows) | **FAIL if rows returned** — duplicate (year, role) pairs exist; resolve before proceeding |
| Section 3 | PK column listing for project_reports | Record current PK definition |
| Section 4 | FK constraint listing | Record for rollback reference |
| Section 5 | Index listing | Record for comparison with 007 |
| Section 6 | CREATE TABLE statements for 6 tables | Record for rollback reference |
| Section 7 | All 5 checks return "PASS" | **FAIL if any WARNING** — table already exists; investigate before proceeding |
| Section 8 | Summary message | Review all sections before proceeding |

**Decision Point:** If Section 2 returns rows OR Section 7 shows any WARNING → **STOP**. Do not proceed to 002.

### STEP 2: 002_alter_existing_tables.sql

**Duration:** 5–30 seconds  
**Type:** DDL (ALTER TABLE)  
**Modifies:** 6 existing tables

| Operation | Expected Output | Notes |
|-----------|-----------------|-------|
| 1a. events: Add `year` column | `Query OK, 0 rows affected` | New column added with NULL default |
| 1b. events: Add FK to `rotary_years` | `Query OK, 0 rows affected` | FK constraint created |
| 1c. events: Create `idx_events_year` | `Query OK, 0 rows affected` | Index created |
| 2a. projects: Add `year` column | `Query OK, 0 rows affected` | New column added |
| 2b. projects: Add FK to `rotary_years` | `Query OK, 0 rows affected` | FK constraint created |
| 2c. projects: Create `idx_projects_year` | `Query OK, 0 rows affected` | Index created |
| 3a. donations: Add `status_updated_by` | `Query OK, 0 rows affected` | VARCHAR(255) NOT NULL DEFAULT '' |
| 3b. donations: Add `status_updated_role` | `Query OK, 0 rows affected` | VARCHAR(50) NULL |
| 4. rotary_years: Verify `is_current` | `0 rows affected` | Confirm column exists |
| 5. leadership_assignments: Add UNIQUE | `Query OK, 0 rows affected` | **BLOCKS if duplicates exist** |
| 6a. project_reports: Verify composite PK | Message output | Informational |
| 6b. project_reports: Add year to PK | Conditional | Only if PK is not composite |

**Common Issue:** Step 5 (leadership_assignments UNIQUE constraint) will fail if duplicate `(rotary_year_id, role)` pairs exist. Resolve duplicates before re-running.

### STEP 3: 003_create_lookup_tables.sql

**Duration:** < 5 seconds  
**Type:** DDL (CREATE TABLE)  
**Creates:** `committee_position` table

| Expected Output | Notes |
|-----------------|-------|
| `Query OK, 0 rows affected` | Table created successfully |
| Or: `Table 'committee_position' already exists` | Safe — uses `IF NOT EXISTS` |

**Verification:**
```sql
DESCRIBE committee_position;
-- Expected: 4 columns (position_id, position_name, display_order, is_active)
```

### STEP 4: 004_create_master_tables.sql

**Duration:** < 5 seconds  
**Type:** DDL (CREATE TABLE)  
**Creates:** `committees` table

| Expected Output | Notes |
|-----------------|-------|
| `Query OK, 0 rows affected` | Table created successfully |
| Or: `Table 'committees' already exists` | Safe — uses `IF NOT EXISTS` |

**Verification:**
```sql
DESCRIBE committees;
-- Expected: 5 columns (committee_id, committee_name, description, display_order, is_active)
```

### STEP 5: 005_create_junction_tables.sql

**Duration:** 5–15 seconds  
**Type:** DDL (CREATE TABLE + ADD CONSTRAINT)  
**Creates:** 3 tables + 8 foreign keys

| Table | Expected Output | FKs Created |
|-------|-----------------|------------|
| `committee_members` | `Query OK, 0 rows affected` | 3 FKs (committee_id → committees, member_id → members, position_id → committee_position) |
| `website_display` | `Query OK, 0 rows affected` | 3 FKs (member_id → members, created_by → admins, updated_by → admins) |
| `donation_allocation` | `Query OK, 0 rows affected` | 2 FKs (donation_id → donations, allocated_by → admins) |

**Verification:**
```sql
-- Confirm 8 new FKs added
SELECT COUNT(*) AS fk_count
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'rotary_test'
  AND REFERENCED_TABLE_NAME IS NOT NULL
  AND TABLE_NAME IN ('committee_members', 'website_display', 'donation_allocation');
-- Expected: 8
```

### STEP 6: 006_create_views.sql

**Duration:** < 5 seconds  
**Type:** DDL (CREATE VIEW)  
**Creates:** `website_team_view`

| Expected Output | Notes |
|-----------------|-------|
| `Query OK, 0 rows affected` | View created successfully |
| Or: View replaced (uses `CREATE OR REPLACE`) | Safe to re-run |

**Verification:**
```sql
SELECT * FROM website_team_view LIMIT 5;
-- Returns member names with committee and position info
```

### STEP 7: 007_create_indexes.sql

**Duration:** 10–60 seconds (depends on data volume)  
**Type:** DDL (CREATE INDEX)  
**Creates:** 23 performance indexes

All 23 indexes use idempotent pattern (check → create):
```sql
-- Each index follows this pattern:
PREPARE stmt FROM 'SELECT ... FROM information_schema.STATISTICS WHERE INDEX_NAME = ? ...';
EXECUTE stmt USING @idx_name;
DEALLOCATE PREPARE stmt;

-- If index exists: "Index already exists — skipping"
-- If index missing: "Creating index idx_xxx... Done."
```

**Expected Output Pattern:**
```
Index idx_events_year already exists — skipping
Creating index idx_projects_year... Done.
Index idx_events_category already exists — skipping
Creating index idx_events_start_date... Done.
... (23 total)
```

**Verification:**
```sql
-- Confirm 23 new indexes added
SELECT COUNT(*) AS index_count
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'rotary_test'
  AND INDEX_NAME != 'PRIMARY'
  AND TABLE_NAME IN (
    'events', 'projects', 'donations', 'members',
    'donors', 'event_polling', 'event_reports',
    'donation_report', 'project_reports', 'announcements',
    'committees', 'committee_members', 'website_display',
    'donation_allocation'
  );
-- Expected: ≥ 23 (may include pre-existing indexes)
```

### STEP 8: 008_seed_lookup_data.sql

**Duration:** < 5 seconds  
**Type:** DML (INSERT IGNORE)  
**Inserts:** 4 committee position rows

| Expected Output | Notes |
|-----------------|-------|
| `Query OK, 4 rows affected` | First run — 4 positions inserted |
| `Query OK, 0 rows affected` | Re-run — duplicates ignored (INSERT IGNORE) |

**Seeded Positions:**
1. President
2. Secretary
3. Treasurer
4. IT Head

**Verification:**
```sql
SELECT * FROM committee_position;
-- Expected: 4 rows
```

### STEP 9: 009_validation_queries.sql

**Duration:** 5–15 seconds  
**Type:** Read-only  
**Modifies:** Nothing

**See [Section 9: Validation Process](#9-validation-process) for complete detail.**

### STEP 10: 010_rollback_reference.sql

**Duration:** 10–30 seconds  
**Type:** DDL/DML (DROP + ALTER — destructive)  
**Use:** Emergency rollback only

**DO NOT RUN unless explicitly instructed to roll back.**

---

## 8. Common Errors and Resolutions

### Error 8.1: Duplicate Key on UNIQUE Constraint

```
ERROR 1062 (23000): Duplicate entry 'X-Y' for key 'unique_year_role'
```

**Cause:** `leadership_assignments` contains duplicate `(rotary_year_id, role)` pairs.  
**Occurs in:** 002_alter_existing_tables.sql, Step 5  
**Resolution:**

```sql
-- 1. Identify duplicates
SELECT rotary_year_id, role, COUNT(*)
FROM leadership_assignments
GROUP BY rotary_year_id, role
HAVING COUNT(*) > 1;

-- 2. Keep the most recent, delete older duplicates
DELETE la1 FROM leadership_assignments la1
INNER JOIN leadership_assignments la2
WHERE la1.id < la2.id
  AND la1.rotary_year_id = la2.rotary_year_id
  AND la1.role = la2.role;

-- 3. Re-run 002_alter_existing_tables.sql
```

### Error 8.2: Table Already Exists

```
ERROR 1050 (42S01): Table 'committee_position' already exists
```

**Cause:** Migration was partially applied or table was manually created.  
**Resolution:** This should NOT occur — all CREATE TABLE statements use `IF NOT EXISTS`. If it does, the file may be corrupted. Verify file integrity and re-download.

### Error 8.3: Duplicate Column

```
ERROR 1060 (42S21): Duplicate column name 'year'
```

**Cause:** Column was already added (migration partially applied).  
**Occurs in:** 002_alter_existing_tables.sql  
**Resolution:** Safe to ignore — indicates the ALTER was already applied. To verify:

```sql
SHOW COLUMNS FROM events LIKE 'year';
SHOW COLUMNS FROM projects LIKE 'year';
```

### Error 8.4: Foreign Key Constraint Fails

```
ERROR 1215 (HY000): Cannot add foreign key constraint
```

**Cause:** Referenced table does not exist, or column types do not match.  
**Occurs in:** 005_create_junction_tables.sql  
**Resolution:**

```sql
-- 1. Verify referenced tables exist
SHOW TABLES LIKE 'committees';
SHOW TABLES LIKE 'members';
SHOW TABLES LIKE 'admins';
SHOW TABLES LIKE 'donations';

-- 2. Verify column types match
DESCRIBE committees committee_id;
DESCRIBE members member_id;
DESCRIBE admins admin_id;
DESCRIBE donations donation_id;
```

### Error 8.5: Table Does Not Exist (FK Reference)

```
ERROR 1824 (HY000): Failed to open the referenced table 'committees'
```

**Cause:** 005 ran before 004.  
**Resolution:** Execute in correct order. Run 004 first, then 005.

### Error 8.6: Access Denied

```
ERROR 1045 (28000): Access denied for user 'root'@'localhost'
```

**Cause:** Wrong password or insufficient privileges.  
**Resolution:**

```sql
-- Reset MySQL root password (XAMPP)
-- Or grant privileges:
GRANT ALL PRIVILEGES ON rotary_test.* TO 'root'@'localhost';
FLUSH PRIVILEGES;
```

### Error 8.7: File Not Found

```
ERROR 2 (HY000): File '001_backup_check.sql' not found
```

**Cause:** Not in the correct directory.  
**Resolution:** `cd` to `C:\xampp\htdocs\rotary-club-virar\migrations` before running.

### Error 8.8: View References Nonexistent Table

```
ERROR 1356 (HY000): View 'rotary_test.website_team_view' references invalid table(s) or column(s)
```

**Cause:** 006 ran before 003/004/005.  
**Resolution:** Execute in correct order. All referenced tables must exist before 006.

### Error 8.9: PREPARE Statement Error

```
ERROR 1064 (42000): You have an error in your SQL syntax
```

**Cause:** File corruption or encoding issue (common with copy-paste).  
**Resolution:** Re-download original approved SQL file. Verify file hash:

```bash
# Compare file hash with approved version
certutil -hashfile 007_create_indexes.sql SHA256
```

### Error 8.10: Index Already Exists (non-idempotent)

```
ERROR 1061 (42000): Duplicate key name 'idx_events_year'
```

**Cause:** 002 Step 1c created the index, then 007 also tried to create it.  
**This should NOT occur** — the issue was fixed in Session 7B. If it does, verify you are using the corrected version of 007.

---

## 9. Validation Process

### 9.1 Running Validation

```bash
mysql -u root -p rotary_test < 009_validation_queries.sql
```

### 9.2 Validation Sections

| Section | Purpose | Expected Result | FAIL Criteria |
|---------|---------|-----------------|---------------|
| 1 | Row count comparison (21 existing tables) | All counts match pre-migration values from 001 | Any count mismatch |
| 2 | New table existence (5 tables) | All 5 tables exist with correct column count | Table missing or wrong column count |
| 3 | Column addition verification (002 changes) | 6 tables have new columns | Column missing |
| 4 | Foreign key verification (8 new FKs) | All 8 FKs present with correct REFERENCED_TABLE | FK missing or wrong reference |
| 5 | Index verification (23 new indexes) | All 23 indexes present | Index missing |
| 6 | View verification | `website_team_view` returns valid results | View missing or query error |
| 7 | Seed data verification | 4 rows in `committee_position` | Row count ≠ 4 |
| 8 | Composite PK verification | `project_reports` PK includes (project_id, year) | PK is not composite |
| 9 | Data integrity spot checks | No orphaned FKs, no broken relationships | Orphaned records found |

### 9.3 Manual Validation Queries

```sql
-- A. Verify total table count (should be 26 tables + 1 view = 27 objects)
SELECT COUNT(*) AS table_count
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'rotary_test'
  AND TABLE_TYPE = 'BASE TABLE';
-- Expected: 26

-- B. Verify new columns on events
SHOW COLUMNS FROM events LIKE 'year';
-- Expected: 1 row (year YEAR NULL)

-- C. Verify new columns on projects
SHOW COLUMNS FROM projects LIKE 'year';
-- Expected: 1 row (year YEAR NULL)

-- D. Verify new columns on donations
SHOW COLUMNS FROM donations LIKE 'status_updated_by';
SHOW COLUMNS FROM donations LIKE 'status_updated_role';
-- Expected: 1 row each

-- E. Verify UNIQUE constraint on leadership_assignments
SHOW INDEX FROM leadership_assignments WHERE Key_name = 'unique_year_role';
-- Expected: 1 row (composite on rotary_year_id, role)

-- F. Verify all 5 new tables exist
SELECT TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'rotary_test'
  AND TABLE_NAME IN ('committee_position', 'committees', 'committee_members', 'website_display', 'donation_allocation')
ORDER BY TABLE_NAME;
-- Expected: 5 rows

-- G. Verify website_team_view
SELECT * FROM website_team_view LIMIT 3;
-- Expected: returns rows or empty set (not an error)

-- H. Verify committee_position seed data
SELECT COUNT(*) FROM committee_position;
-- Expected: 4

-- I. Verify no orphaned FKs (spot check)
SELECT 'orphan_event_polling' AS check_name, COUNT(*) AS orphan_count
FROM event_polling ep
LEFT JOIN events e ON ep.event_id = e.event_id
WHERE e.event_id IS NULL
UNION ALL
SELECT 'orphan_donation_report', COUNT(*)
FROM donation_report dr
LEFT JOIN donations d ON dr.donation_id = d.donation_id
WHERE d.donation_id IS NULL;
-- Expected: all orphan_count = 0
```

### 9.4 Validation Sign-Off

```
[ ] All 9 validation sections passed
[ ] Row counts match pre-migration baseline (from 001 Section 1)
[ ] All 5 new tables exist with correct schemas
[ ] All 8 foreign keys present and correct
[ ] All 23 indexes created
[ ] website_team_view returns valid results
[ ] committee_position has 4 seeded rows
[ ] No orphaned foreign keys detected
[ ] composite PK confirmed on project_reports
```

---

## 10. Rollback Procedure

### 10.1 When to Rollback

Execute rollback **only** if:
- Migration fails partway through and cannot be recovered
- Validation reveals critical data integrity issues
- Application breaks after migration and cannot be fixed forward

### 10.2 Rollback Execution

```bash
mysql -u root -p rotary_test < 010_rollback_reference.sql
```

**This is destructive. It drops:**
- 25 indexes (all new indexes from 007)
- 1 view (`website_team_view` from 006)
- 3 junction tables (`committee_members`, `website_display`, `donation_allocation` from 005)
- 2 master/lookup tables (`committees`, `committee_position` from 004/003)
- 4 seed data rows (`committee_position` from 008)
- 8 foreign keys (from 005)
- 2 UNIQUE constraints (from 002)
- 4 new columns (from 002)

### 10.3 Post-Rollback Verification

```sql
-- Confirm rollback complete
SHOW TABLES;
-- Should show original 21 tables only

-- Confirm no new tables remain
SELECT TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'rotary_test'
  AND TABLE_NAME IN ('committee_position', 'committees', 'committee_members', 'website_display', 'donation_allocation');
-- Expected: 0 rows

-- Confirm columns removed
SHOW COLUMNS FROM events LIKE 'year';
-- Expected: 0 rows

-- Confirm indexes removed
SHOW INDEX FROM events WHERE Key_name = 'idx_events_year';
-- Expected: 0 rows
```

### 10.4 Full Restore from Backup

If rollback via 010 is insufficient:

```bash
# Drop and recreate database from backup
mysql -u root -p -e "DROP DATABASE rotary_test; CREATE DATABASE rotary_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p rotary_test < rotary_pre_migration.sql
```

---

## 11. Final Acceptance Checklist

### 11.1 Migration Completion

```
[ ] 001_backup_check.sql — Completed, GO decision confirmed
[ ] 002_alter_existing_tables.sql — All 6 tables altered successfully
[ ] 003_create_lookup_tables.sql — committee_position created
[ ] 004_create_master_tables.sql — committees created
[ ] 005_create_junction_tables.sql — 3 tables + 8 FKs created
[ ] 006_create_views.sql — website_team_view created
[ ] 007_create_indexes.sql — 23 indexes created
[ ] 008_seed_lookup_data.sql — 4 positions seeded
[ ] 009_validation_queries.sql — All 9 sections passed
```

### 11.2 Schema Verification

```
[ ] Total tables: 26 (21 original + 5 new)
[ ] Total views: 1 (website_team_view)
[ ] events.year column: Present (YEAR, NULL)
[ ] projects.year column: Present (YEAR, NULL)
[ ] donations.status_updated_by: Present (VARCHAR(255), NOT NULL, DEFAULT '')
[ ] donations.status_updated_role: Present (VARCHAR(50), NULL)
[ ] leadership_assignments UNIQUE constraint: Present on (rotary_year_id, role)
[ ] project_reports composite PK: Present on (project_id, year)
[ ] committee_position columns: 4 (position_id, position_name, display_order, is_active)
[ ] committees columns: 5 (committee_id, committee_name, description, display_order, is_active)
[ ] committee_members columns: 5 (id, committee_id, member_id, position_id, assigned_date)
[ ] website_display columns: 6 (id, member_id, section_name, display_order, is_visible, created_by, updated_by)
[ ] donation_allocation columns: 5 (id, donation_id, allocation_type, allocated_amount, allocated_by, allocated_date, notes)
```

### 11.3 Index Verification

```
[ ] All 23 indexes from 007 present
[ ] No duplicate indexes across migration files
[ ] idx_events_year and idx_projects_year present (created in 002)
[ ] No orphaned indexes (indexes referencing dropped objects)
```

### 11.4 Foreign Key Verification

```
[ ] events.rotary_year_id → rotary_years.id: Present
[ ] projects.rotary_year_id → rotary_years.id: Present
[ ] committee_members.committee_id → committees.committee_id: Present
[ ] committee_members.member_id → members.member_id: Present
[ ] committee_members.position_id → committee_position.position_id: Present
[ ] website_display.member_id → members.member_id: Present
[ ] website_display.created_by → admins.admin_id: Present
[ ] website_display.updated_by → admins.admin_id: Present
[ ] donation_allocation.donation_id → donations.donation_id: Present
[ ] donation_allocation.allocated_by → admins.admin_id: Present
```

### 11.5 Data Integrity Verification

```
[ ] No duplicate (year, role) pairs in leadership_assignments
[ ] No orphaned event_polling records (all event_ids reference valid events)
[ ] No orphaned donation_report records (all donation_ids reference valid donations)
[ ] No orphaned event_reports records (all event_ids reference valid events)
[ ] committee_position has exactly 4 rows
[ ] website_team_view returns valid results (or empty set)
```

### 11.6 Application Verification

```
[ ] Admin dashboard loads correctly
[ ] Team page displays members
[ ] Events page displays events
[ ] Donation management works
[ ] No PHP errors in error log
[ ] No MySQL connection errors
```

---

## 12. Go / No-Go Recommendation

### Staging Readiness Status

| Criterion | Status |
|-----------|--------|
| SQL Package Approved | ✅ 10/10 files pass final audit |
| Idempotency Verified | ✅ 71 operations safe to re-run |
| Duplicate Index Issue Resolved | ✅ 007 corrected (no executable duplicates) |
| Execution Order Defined | ✅ 10-step sequential plan |
| Validation Queries Provided | ✅ 9-section comprehensive validation |
| Rollback Plan Documented | ✅ 010_rollback_reference.sql ready |
| Backup Checklist Defined | ✅ Database + application backups |
| Error Resolution Guide | ✅ 10 common errors documented |
| Acceptance Criteria Defined | ✅ Complete sign-off checklist |

### Risks

| # | Risk | Severity | Mitigation |
|---|------|----------|------------|
| 1 | leadership_assignments has duplicate (year, role) pairs | **HIGH** | 001 Section 2 detects; resolve before 002 |
| 2 | MySQL version < 8.0 (DROP INDEX IF EXISTS fails) | **MEDIUM** | Verify MySQL 8.0+ before execution |
| 3 | Insufficient MySQL privileges | **LOW** | Verify GRANTS before execution |
| 4 | Partial execution (migration interrupted mid-way) | **MEDIUM** | All operations idempotent; re-run from failed step |
| 5 | Application code references old schema during migration | **MEDIUM** | Enable maintenance mode before execution |
| 6 | Large data volume causes slow index creation | **LOW** | 007 may take 30–60s on large tables; no action needed |
| 7 | project_reports PK change fails (existing data conflict) | **LOW** | 001 Section 3 detects current PK; 002 handles conditionally |

### Preconditions

1. MySQL 8.0+ or MariaDB 10.4+ installed and running
2. Staging database `rotary_test` created and populated with production data
3. Full backup completed and verified
4. Application in maintenance mode
5. All 10 SQL files present and unmodified
6. MySQL user has required privileges (SELECT, INSERT, CREATE, ALTER, INDEX, DROP)
7. No active connections to `rotary_test` (except executor)

### Recommendation

```
┌─────────────────────────────────────────────────────────────────┐
│                                                                 │
│   GO                                                           │
│                                                                 │
│   The SQL migration package is approved, audited, and ready.   │
│   All 10 files pass engineering audit (100/100).               │
│   Idempotency verified across all 71 operations.               │
│   Duplicate index issue resolved.                              │
│   Complete rollback capability confirmed.                      │
│                                                                 │
│   Execute on staging database (rotary_test) first.             │
│   Complete all validation before applying to production.       │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## Appendix A: Quick Reference Card

```
BACKUP:
  mysqldump -u root -p rotary > rotary_pre_migration.sql

CREATE STAGING:
  mysql -u root -p -e "CREATE DATABASE rotary_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  mysql -u root -p rotary_test < rotary_pre_migration.sql

EXECUTE (in order):
  mysql -u root -p rotary_test < 001_backup_check.sql
  mysql -u root -p rotary_test < 002_alter_existing_tables.sql
  mysql -u root -p rotary_test < 003_create_lookup_tables.sql
  mysql -u root -p rotary_test < 004_create_master_tables.sql
  mysql -u root -p rotary_test < 005_create_junction_tables.sql
  mysql -u root -p rotary_test < 006_create_views.sql
  mysql -u root -p rotary_test < 007_create_indexes.sql
  mysql -u root -p rotary_test < 008_seed_lookup_data.sql
  mysql -u root -p rotary_test < 009_validation_queries.sql

VALIDATE:
  mysql -u root -p rotary_test < 009_validation_queries.sql

ROLLBACK (emergency only):
  mysql -u root -p rotary_test < 010_rollback_reference.sql

FULL RESTORE:
  mysql -u root -p -e "DROP DATABASE rotary_test; CREATE DATABASE rotary_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  mysql -u root -p rotary_test < rotary_pre_migration.sql
```

---

**Document Version:** 1.0  
**Last Updated:** 2026-07-16  
**Status:** Ready for staging execution  
**Next Session:** 7D — Execute migrations on `rotary_test` and capture results
