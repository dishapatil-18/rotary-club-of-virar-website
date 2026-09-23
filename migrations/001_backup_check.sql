-- ============================================================================
-- RCMP MIGRATION 001: PRE-MIGRATION BACKUP & VALIDATION CHECKS
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
-- Record the baseline state of the database BEFORE any schema changes.
-- This script performs READ-ONLY checks. It modifies nothing.
--
-- It must be run FIRST (before 002_alter_existing_tables.sql) to:
--   1. Capture pre-migration row counts (for post-migration comparison)
--   2. Detect data issues that would block ALTER statements
--   3. Record current schema state (for rollback reference)
--   4. Verify new tables do not already exist
--
-- PREREQUISITES
-- -------------
--   - Full database backup completed (rotary_pre_migration.sql)
--   - Uploads directory backed up (uploads/)
--   - Configuration files backed up (.env, config/)
--   - Application is in maintenance mode (no concurrent writes)
--
-- DEPENDENCIES
-- ------------
--   None. This script is read-only and safe to run at any time.
--
-- EXECUTION
-- ---------
--   mysql -u root -p rotary < 001_backup_check.sql
--
--   OR run in phpMyAdmin / MySQL Workbench / command line.
--
-- EXPECTED DURATION
-- -----------------
--   < 5 seconds (read-only queries on small tables)
--
-- OUTPUT
-- ------
--   Section 1: Pre-migration row counts (21 tables)
--   Section 2: Duplicate detection for leadership_assignments
--   Section 3: project_reports primary key definition
--   Section 4: Current foreign key constraints
--   Section 5: Current index listing
--   Section 6: Table structure snapshots (tables being modified)
--   Section 7: New table existence check (all must not exist)
--   Section 8: GO / NO-GO decision
--
-- ROLLBACK
-- --------
--   None needed. This script is read-only.
--
-- ============================================================================

-- ============================================================================
-- SECTION 1: PRE-MIGRATION ROW COUNTS
-- ============================================================================
-- PURPOSE: Capture baseline row counts for all 21 existing tables.
--          After migration, run 009_validation_queries.sql and compare.
--          Row counts for existing tables MUST match exactly.
--
-- ACTION: Record these numbers. You will need them later.
-- ============================================================================

SELECT '=== SECTION 1: PRE-MIGRATION ROW COUNTS ===' AS '';

SELECT '01. admins'                   AS `table`, COUNT(*) AS `rows` FROM admins
UNION ALL SELECT '02. members',                  COUNT(*) FROM members
UNION ALL SELECT '03. donors',                   COUNT(*) FROM donors
UNION ALL SELECT '04. donations',                COUNT(*) FROM donations
UNION ALL SELECT '05. events',                   COUNT(*) FROM events
UNION ALL SELECT '06. projects',                 COUNT(*) FROM projects
UNION ALL SELECT '07. collaborations',           COUNT(*) FROM collaborations
UNION ALL SELECT '08. rotary_years',             COUNT(*) FROM rotary_years
UNION ALL SELECT '09. leadership_assignments',   COUNT(*) FROM leadership_assignments
UNION ALL SELECT '10. site_content',             COUNT(*) FROM site_content
UNION ALL SELECT '11. event_polling',            COUNT(*) FROM event_polling
UNION ALL SELECT '12. event_reports',            COUNT(*) FROM event_reports
UNION ALL SELECT '13. project_reports',          COUNT(*) FROM project_reports
UNION ALL SELECT '14. donation_report',          COUNT(*) FROM donation_report
UNION ALL SELECT '15. donation_status_history',  COUNT(*) FROM donation_status_history
UNION ALL SELECT '16. collaboration_reports',    COUNT(*) FROM collaboration_reports
UNION ALL SELECT '17. password_reset_tokens',    COUNT(*) FROM password_reset_tokens
UNION ALL SELECT '18. announcements',            COUNT(*) FROM announcements
UNION ALL SELECT '19. contact_messages',         COUNT(*) FROM contact_messages
UNION ALL SELECT '20. media_gallery',            COUNT(*) FROM media_gallery
UNION ALL SELECT '21. gallery_media',            COUNT(*) FROM gallery_media
ORDER BY `table`;

-- ============================================================================
-- SECTION 2: CRITICAL DATA CHECK — leadership_assignments DUPLICATES
-- ============================================================================
-- PURPOSE: Detect duplicate (rotary_year_id, role) pairs.
--
-- BUSINESS RULE: One person per role per year (Session 4.5).
--                A UNIQUE constraint will be added in 002_alter_existing_tables.sql.
--
-- IF THIS SECTION RETURNS ROWS:
--   → STOP. Do NOT proceed to 002.
--   → Resolve duplicates manually (keep the most recent by created_at).
--   → Then re-run this check.
--
-- EXPECTED RESULT: Empty set (0 rows)
-- ============================================================================

SELECT '=== SECTION 2: leadership_assignments DUPLICATE CHECK ===' AS '';

SELECT
    la.rotary_year_id,
    ry.year_name,
    la.role,
    COUNT(*) AS occurrence_count
FROM leadership_assignments la
LEFT JOIN rotary_years ry ON la.rotary_year_id = ry.id
GROUP BY la.rotary_year_id, ry.year_name, la.role
HAVING COUNT(*) > 1;

-- ============================================================================
-- SECTION 3: project_reports PRIMARY KEY DEFINITION
-- ============================================================================
-- PURPOSE: Determine if project_reports has a single PK (project_id)
--          or a composite PK (project_id, year).
--
-- BUSINESS RULE: One report per project per year requires composite PK.
--
-- 002_alter_existing_tables.sql handles this conditionally:
--   - If single PK → alter to composite
--   - If composite → skip (no change needed)
-- ============================================================================

SELECT '=== SECTION 3: project_reports PRIMARY KEY DEFINITION ===' AS '';

SELECT
    COLUMN_NAME,
    SEQ_IN_INDEX,
    INDEX_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'project_reports'
  AND INDEX_NAME = 'PRIMARY'
ORDER BY SEQ_IN_INDEX;

-- ============================================================================
-- SECTION 4: CURRENT FOREIGN KEY CONSTRAINTS
-- ============================================================================
-- PURPOSE: Record all existing FK constraints for rollback reference.
--          After migration, compare against this snapshot.
-- ============================================================================

SELECT '=== SECTION 4: EXISTING FOREIGN KEY CONSTRAINTS ===' AS '';

SELECT
    kcu.TABLE_NAME,
    kcu.CONSTRAINT_NAME,
    kcu.COLUMN_NAME,
    kcu.REFERENCED_TABLE_NAME,
    kcu.REFERENCED_COLUMN_NAME,
    rc.DELETE_RULE,
    rc.UPDATE_RULE
FROM information_schema.KEY_COLUMN_USAGE kcu
JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
    ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
    AND kcu.TABLE_SCHEMA = rc.CONSTRAINT_SCHEMA
WHERE kcu.TABLE_SCHEMA = DATABASE()
  AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY kcu.TABLE_NAME, kcu.CONSTRAINT_NAME;

-- ============================================================================
-- SECTION 5: CURRENT INDEX LISTING
-- ============================================================================
-- PURPOSE: Record all existing indexes for reference.
--          007_create_indexes.sql will add new indexes after this point.
-- ============================================================================

SELECT '=== SECTION 5: EXISTING INDEXES ===' AS '';

SELECT
    TABLE_NAME,
    INDEX_NAME,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ', ') AS index_columns,
    CASE NON_UNIQUE WHEN 0 THEN 'UNIQUE' ELSE 'NON-UNIQUE' END AS uniqueness,
    INDEX_TYPE
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
GROUP BY TABLE_NAME, INDEX_NAME, NON_UNIQUE, INDEX_TYPE
ORDER BY TABLE_NAME, INDEX_NAME;

-- ============================================================================
-- SECTION 6: TABLE STRUCTURE SNAPSHOTS — Tables Being Modified
-- ============================================================================
-- PURPOSE: Record the current CREATE TABLE statements for tables that
--          will be modified by 002_alter_existing_tables.sql.
--          Use these for rollback reference if needed.
-- ============================================================================

SELECT '=== SECTION 6a: events CURRENT STRUCTURE ===' AS '';
SHOW CREATE TABLE events\G

SELECT '=== SECTION 6b: projects CURRENT STRUCTURE ===' AS '';
SHOW CREATE TABLE projects\G

SELECT '=== SECTION 6c: donations CURRENT STRUCTURE ===' AS '';
SHOW CREATE TABLE donations\G

SELECT '=== SECTION 6d: rotary_years CURRENT STRUCTURE ===' AS '';
SHOW CREATE TABLE rotary_years\G

SELECT '=== SECTION 6e: leadership_assignments CURRENT STRUCTURE ===' AS '';
SHOW CREATE TABLE leadership_assignments\G

SELECT '=== SECTION 6f: project_reports CURRENT STRUCTURE ===' AS '';
SHOW CREATE TABLE project_reports\G

-- ============================================================================
-- SECTION 7: VERIFY NEW TABLES DO NOT EXIST
-- ============================================================================
-- PURPOSE: Ensure none of the 5 new tables already exist.
--          If any already exist, investigate before proceeding.
--
-- EXPECTED RESULT: All 5 checks return "PASS"
-- ============================================================================

SELECT '=== SECTION 7: NEW TABLE EXISTENCE CHECK ===' AS '';

SELECT
    CASE
        WHEN COUNT(*) = 0 THEN 'PASS: committee_position does not exist (safe to create)'
        ELSE 'WARNING: committee_position ALREADY EXISTS — investigate before proceeding'
    END AS `committee_position`
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'committee_position';

SELECT
    CASE
        WHEN COUNT(*) = 0 THEN 'PASS: committees does not exist (safe to create)'
        ELSE 'WARNING: committees ALREADY EXISTS — investigate before proceeding'
    END AS `committees`
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'committees';

SELECT
    CASE
        WHEN COUNT(*) = 0 THEN 'PASS: committee_members does not exist (safe to create)'
        ELSE 'WARNING: committee_members ALREADY EXISTS — investigate before proceeding'
    END AS `committee_members`
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'committee_members';

SELECT
    CASE
        WHEN COUNT(*) = 0 THEN 'PASS: website_display does not exist (safe to create)'
        ELSE 'WARNING: website_display ALREADY EXISTS — investigate before proceeding'
    END AS `website_display`
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'website_display';

SELECT
    CASE
        WHEN COUNT(*) = 0 THEN 'PASS: donation_allocation does not exist (safe to create)'
        ELSE 'WARNING: donation_allocation ALREADY EXISTS — investigate before proceeding'
    END AS `donation_allocation`
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'donation_allocation';

-- ============================================================================
-- SECTION 8: GO / NO-GO DECISION
-- ============================================================================
-- PURPOSE: Final pre-flight summary. Review all sections above before proceeding.
--
-- DECISION CRITERIA:
--   → Section 2 must return 0 rows (no duplicates in leadership_assignments)
--   → Section 7 must return all PASS (no pre-existing new tables)
--   → Section 6 must show expected column lists (no unexpected modifications)
--
-- IF ANY CHECK FAILS:
--   → Do NOT proceed to 002_alter_existing_tables.sql
--   → Resolve the issue first
--   → Re-run this script
--
-- IF ALL CHECKS PASS:
--   → Safe to proceed to 002_alter_existing_tables.sql
-- ============================================================================

SELECT '=== SECTION 8: PRE-MIGRATION CHECKS COMPLETE ===' AS '';

SELECT 'All read-only checks completed. Review results above.' AS message;
SELECT 'If Section 2 returned 0 rows and Section 7 shows all PASS:' AS next_step;
SELECT '  → Safe to proceed to 002_alter_existing_tables.sql' AS action;

-- ============================================================================
-- END OF 001_backup_check.sql
-- ============================================================================
