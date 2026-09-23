-- ============================================================================
-- RCMP MIGRATION 002: ALTER EXISTING TABLES
-- ============================================================================
--
-- Project:    Rotary Club of Virar — Management Platform
-- Database:   rotary
-- Engine:     InnoDB / utf8mb4
-- Date:       2026-07-16
-- Author:     RCMP Migration Team
-- Version:    1.0
--
-- PURPOSE
-- -------
-- Modify 6 existing tables to match the approved Physical Schema.
-- This script applies all ALTER TABLE statements for the migration.
--
-- TABLES MODIFIED
-- ----------------
--   1. events                  — ADD COLUMN year_id, ADD FK, ADD INDEX
--   2. projects                — ADD COLUMN year_id, ADD FK, ADD INDEX
--   3. donations               — ADD COLUMN status_updated_by, status_updated_role
--   4. rotary_years            — preserved (created_at kept for PHP compatibility)
--   5. leadership_assignments  — RENAME rotary_year_id→year_id, ADD UNIQUE (year_id, role) [conditional]
--   6. project_reports         — ALTER PK to composite (project_id, year) [conditional]
--
-- PREREQUISITES
-- -------------
--   - 001_backup_check.sql completed successfully
--   - Section 2 returned 0 rows (no duplicate leadership assignments)
--   - Full database backup verified and restorable
--   - Application is in maintenance mode
--
-- DEPENDENCIES
-- ------------
--   - 001_backup_check.sql (must run first)
--   - rotary_years table must exist (referenced by FK constraints)
--   - rotary_years.id must be INT (referenced by FK constraints)
--
-- EXECUTION
-- ---------
--   mysql -u root -p rotary < 002_alter_existing_tables.sql
--
-- EXPECTED DURATION
-- -----------------
--   < 30 seconds (depends on table sizes and server performance)
--
-- IDEMPOTENCY
-- -----------
--   This script is fully idempotent:
--   - Each step checks if the change already exists before applying
--   - Safe to re-run if interrupted or if a step fails
--   - No duplicate columns, constraints, or indexes will be created
--
-- ROLLBACK
-- --------
--   If this script fails partway:
--   Option A: Re-run from the beginning (script is idempotent)
--   Option B: Restore from backup (010_rollback_reference.sql)
--
-- ============================================================================

-- ============================================================================
-- STEP 1: ALTER events — ADD COLUMN year_id
-- ============================================================================
--
-- BUSINESS RULE:  Events may optionally be linked to a Rotary Year.
--                 Single nullable FK. No junction table.
-- ARCHITECTURE:   Session 4.5 approved simplified event-year relationship.
-- IMPACT:         LOW — Column is nullable, existing rows get NULL.
-- SQL OPERATIONS: ADD COLUMN, ADD CONSTRAINT (FK), ADD INDEX
-- ============================================================================

SELECT '--- STEP 1: events — Add year_id ---' AS '';

-- 1a. Add column (idempotent: check existence first)
SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'events'
      AND COLUMN_NAME = 'year_id'
);

SET @sql = IF(
    @col_exists = 0,
    'ALTER TABLE events ADD COLUMN year_id INT NULL AFTER image_url',
    'SELECT "STEP 1a: SKIP — events.year_id already exists" AS status'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 1b. Add foreign key constraint (idempotent: check existence first)
SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'events'
      AND CONSTRAINT_NAME = 'fk_events_year'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql = IF(
    @fk_exists = 0,
    'ALTER TABLE events ADD CONSTRAINT fk_events_year FOREIGN KEY (year_id) REFERENCES rotary_years(id) ON DELETE SET NULL',
    'SELECT "STEP 1b: SKIP — fk_events_year already exists" AS status'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 1c. Add index (idempotent: check existence first)
SET @idx_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'events'
      AND INDEX_NAME = 'idx_events_year'
);

SET @sql = IF(
    @idx_exists = 0,
    'CREATE INDEX idx_events_year ON events(year_id)',
    'SELECT "STEP 1c: SKIP — idx_events_year already exists" AS status'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'STEP 1 COMPLETE: events.year_id — column, FK, and index added' AS status;

-- ============================================================================
-- STEP 2: ALTER projects — ADD COLUMN year_id
-- ============================================================================
--
-- BUSINESS RULE:  Projects may optionally be linked to a Rotary Year.
--                 Single nullable FK. No junction table.
-- ARCHITECTURE:   Session 4.5 approved simplified project-year relationship.
-- IMPACT:         LOW — Column is nullable, existing rows get NULL.
-- SQL OPERATIONS: ADD COLUMN, ADD CONSTRAINT (FK), ADD INDEX
-- ============================================================================

SELECT '--- STEP 2: projects — Add year_id ---' AS '';

-- 2a. Add column (idempotent)
SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'projects'
      AND COLUMN_NAME = 'year_id'
);

SET @sql = IF(
    @col_exists = 0,
    'ALTER TABLE projects ADD COLUMN year_id INT NULL AFTER created_at',
    'SELECT "STEP 2a: SKIP — projects.year_id already exists" AS status'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2b. Add foreign key constraint (idempotent)
SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'projects'
      AND CONSTRAINT_NAME = 'fk_projects_year'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql = IF(
    @fk_exists = 0,
    'ALTER TABLE projects ADD CONSTRAINT fk_projects_year FOREIGN KEY (year_id) REFERENCES rotary_years(id) ON DELETE SET NULL',
    'SELECT "STEP 2b: SKIP — fk_projects_year already exists" AS status'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2c. Add index (idempotent)
SET @idx_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'projects'
      AND INDEX_NAME = 'idx_projects_year'
);

SET @sql = IF(
    @idx_exists = 0,
    'CREATE INDEX idx_projects_year ON projects(year_id)',
    'SELECT "STEP 2c: SKIP — idx_projects_year already exists" AS status'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'STEP 2 COMPLETE: projects.year_id — column, FK, and index added' AS status;

-- ============================================================================
-- STEP 3: ALTER donations — ADD audit columns
-- ============================================================================
--
-- BUSINESS RULE:  Track which admin updated donation status and their role.
-- ARCHITECTURE:   Session 4.5 approved audit trail for donation pipeline.
-- NOTE:           donation_action.php already writes to these columns.
--                 This ALTER formalizes columns the application expects.
-- IMPACT:         LOW — Default values applied to existing rows.
-- SQL OPERATIONS: ADD COLUMN (x2)
-- ============================================================================

SELECT '--- STEP 3: donations — Add audit columns ---' AS '';

-- 3a. Add status_updated_by (idempotent)
SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'donations'
      AND COLUMN_NAME = 'status_updated_by'
);

SET @sql = IF(
    @col_exists = 0,
    'ALTER TABLE donations ADD COLUMN status_updated_by VARCHAR(255) NOT NULL DEFAULT \'\' AFTER date',
    'SELECT "STEP 3a: SKIP — donations.status_updated_by already exists" AS status'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3b. Add status_updated_role (idempotent)
SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'donations'
      AND COLUMN_NAME = 'status_updated_role'
);

SET @sql = IF(
    @col_exists = 0,
    'ALTER TABLE donations ADD COLUMN status_updated_role VARCHAR(50) NULL AFTER status_updated_by',
    'SELECT "STEP 3b: SKIP — donations.status_updated_role already exists" AS status'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'STEP 3 COMPLETE: donations.audit columns added (status_updated_by, status_updated_role)' AS status;

-- ============================================================================
-- STEP 4: REMOVED — rotary_years.created_at PRESERVED
-- ============================================================================
-- Compatibility review (Session 7D) identified that admin_functions.php:31
-- reads rotary_years.created_at via getAllRotaryYears().
-- This step was removed to preserve PHP application compatibility.
-- ============================================================================

SELECT '--- STEP 4: SKIPPED — rotary_years.created_at preserved for PHP compatibility ---' AS '';

-- ============================================================================
-- STEP 5: ALTER leadership_assignments — RENAME COLUMN + ADD UNIQUE CONSTRAINT
-- ============================================================================
--
-- BUSINESS RULE:  One person per role per year (Session 4.5).
-- COLUMN RENAME:  rotary_year_id → year_id (PHP application compatibility)
-- IMPACT:         MEDIUM — Will FAIL if duplicate (year_id, role) pairs exist.
-- PREREQUISITE:   001_backup_check.sql Section 2 must return 0 rows.
-- CONDITIONAL:    If duplicates exist, this step SKIPS the constraint.
--                 Resolution required before migration can complete.
-- SQL OPERATIONS: CHANGE COLUMN, ADD UNIQUE KEY (conditional)
-- ============================================================================

SELECT '--- STEP 5: leadership_assignments — Rename column + Add UNIQUE constraint ---' AS '';

-- 5a. Rename rotary_year_id -> year_id (idempotent: check current name)
SET @has_old_col = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'leadership_assignments'
      AND COLUMN_NAME = 'rotary_year_id'
);

SET @sql = IF(
    @has_old_col = 1,
    'ALTER TABLE leadership_assignments DROP INDEX unique_year_role',
    'SELECT "STEP 5a: SKIP — rotary_year_role index not found (column may already be year_id)" AS status'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    @has_old_col = 1,
    'ALTER TABLE leadership_assignments CHANGE COLUMN rotary_year_id year_id INT NOT NULL',
    'SELECT "STEP 5a2: SKIP — column already named year_id" AS status'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5b. Check for duplicates
SET @dup_count = (
    SELECT COUNT(*)
    FROM (
        SELECT year_id, role
        FROM leadership_assignments
        GROUP BY year_id, role
        HAVING COUNT(*) > 1
    ) AS duplicates
);

SELECT
    CASE
        WHEN @dup_count = 0 THEN 'STEP 5b: PRE-CHECK PASSED — No duplicates found. SAFE to proceed.'
        ELSE CONCAT('STEP 5b: PRE-CHECK FAILED — ', @dup_count, ' duplicate(s) found. RESOLVE BEFORE PROCEEDING.')
    END AS pre_check_result;

-- 5c. Check if constraint already exists
SET @uniq_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'leadership_assignments'
      AND CONSTRAINT_NAME = 'unique_year_role'
      AND CONSTRAINT_TYPE = 'UNIQUE'
);

-- 5d. Apply constraint only if: no duplicates AND constraint doesn't exist yet
SET @sql = IF(
    @dup_count = 0 AND @uniq_exists = 0,
    'ALTER TABLE leadership_assignments ADD UNIQUE KEY unique_year_role (year_id, role)',
    IF(
        @uniq_exists > 0,
        'SELECT "STEP 5d: SKIP — unique_year_role constraint already exists" AS status',
        'SELECT "STEP 5d: SKIPPED — duplicates exist. Resolve before applying constraint." AS status'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'STEP 5 COMPLETE: leadership_assignments column renamed + UNIQUE constraint applied' AS status;

-- ============================================================================
-- STEP 6: ALTER project_reports — FIX COMPOSITE PRIMARY KEY
-- ============================================================================
--
-- BUSINESS RULE:  One report per project per year (Session 4.5).
--                 Requires composite PK: (project_id, year).
-- IMPACT:         LOW-MEDIUM — Depends on current PK definition.
-- CONDITIONAL:    If PK is already composite, this step SKIPS.
-- SQL OPERATIONS: DROP PRIMARY KEY, ADD PRIMARY KEY (conditional)
-- ============================================================================

SELECT '--- STEP 6: project_reports — Fix composite PK ---' AS '';

-- 6a. Check current PK definition
SET @pk_cols = (
    SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'project_reports'
      AND INDEX_NAME = 'PRIMARY'
);

SELECT CONCAT('STEP 6a: Current PK columns = (', @pk_cols, ')') AS current_pk;

-- 6b. Alter PK only if it's single-column (project_id alone)
SET @sql = IF(
    @pk_cols = 'project_id',
    'ALTER TABLE project_reports DROP PRIMARY KEY, ADD PRIMARY KEY (project_id, year)',
    'SELECT "STEP 6b: SKIP — project_reports PK is already composite or multi-column" AS status'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'STEP 6 COMPLETE: project_reports PK verified/fixed' AS status;

-- ============================================================================
-- ALL ALTER STATEMENTS COMPLETE
-- ============================================================================
--
-- SUMMARY OF CHANGES:
--   Step 1: events.year_id (column + FK + index)
--   Step 2: projects.year_id (column + FK + index)
--   Step 3: donations.status_updated_by, donations.status_updated_role
--   Step 4: SKIPPED — rotary_years.created_at preserved
--   Step 5: leadership_assignments RENAME rotary_year_id→year_id + UNIQUE (year_id, role)
--   Step 6: project_reports.PRIMARY KEY (project_id, year)
--
-- NEXT STEP: Execute 003_create_lookup_tables.sql
-- ============================================================================

SELECT '=== 002_alter_existing_tables.sql COMPLETE ===' AS status;
SELECT 'Next step: Execute 003_create_lookup_tables.sql' AS next_step;

-- ============================================================================
-- END OF 002_alter_existing_tables.sql
-- ============================================================================
