-- ============================================================================
-- RCMP Migration 009: Post-Migration Validation
-- Rotary Club of Virar — Management Platform
-- Date: 2026-07-16
-- Purpose: Validate migration success — run AFTER all previous files
-- Prerequisite: 008_seed_lookup_data.sql completed successfully
-- ============================================================================

-- ============================================================================
-- SECTION 1: ROW COUNT VALIDATION
-- ============================================================================
-- Compare these against pre-migration counts from 001_backup_check.sql
-- Existing tables: counts must match exactly
-- New tables: committees=0, committee_members=0, committee_position=4,
--             website_display=0, donation_allocation=0

SELECT '=== POST-MIGRATION ROW COUNTS ===' AS '';

SELECT 'admins' AS `table`, COUNT(*) AS `row_count` FROM admins
UNION ALL SELECT 'members', COUNT(*) FROM members
UNION ALL SELECT 'donors', COUNT(*) FROM donors
UNION ALL SELECT 'donations', COUNT(*) FROM donations
UNION ALL SELECT 'events', COUNT(*) FROM events
UNION ALL SELECT 'projects', COUNT(*) FROM projects
UNION ALL SELECT 'collaborations', COUNT(*) FROM collaborations
UNION ALL SELECT 'rotary_years', COUNT(*) FROM rotary_years
UNION ALL SELECT 'leadership_assignments', COUNT(*) FROM leadership_assignments
UNION ALL SELECT 'site_content', COUNT(*) FROM site_content
UNION ALL SELECT 'event_polling', COUNT(*) FROM event_polling
UNION ALL SELECT 'event_reports', COUNT(*) FROM event_reports
UNION ALL SELECT 'project_reports', COUNT(*) FROM project_reports
UNION ALL SELECT 'donation_report', COUNT(*) FROM donation_report
UNION ALL SELECT 'donation_status_history', COUNT(*) FROM donation_status_history
UNION ALL SELECT 'collaboration_reports', COUNT(*) FROM collaboration_reports
UNION ALL SELECT 'password_reset_tokens', COUNT(*) FROM password_reset_tokens
UNION ALL SELECT 'announcements', COUNT(*) FROM announcements
UNION ALL SELECT 'contact_messages', COUNT(*) FROM contact_messages
UNION ALL SELECT 'media_gallery', COUNT(*) FROM media_gallery
UNION ALL SELECT 'gallery_media', COUNT(*) FROM gallery_media
UNION ALL SELECT 'committee_position', COUNT(*) FROM committee_position
UNION ALL SELECT 'committees', COUNT(*) FROM committees
UNION ALL SELECT 'committee_members', COUNT(*) FROM committee_members
UNION ALL SELECT 'website_display', COUNT(*) FROM website_display
UNION ALL SELECT 'donation_allocation', COUNT(*) FROM donation_allocation;

-- ============================================================================
-- SECTION 2: TABLE EXISTENCE CHECK
-- ============================================================================

SELECT '=== NEW TABLES EXISTENCE CHECK ===' AS '';

SELECT
    TABLE_NAME,
    TABLE_ROWS,
    ENGINE,
    TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
      'committee_position', 'committees', 'committee_members',
      'website_display', 'donation_allocation'
  )
ORDER BY TABLE_NAME;

-- ============================================================================
-- SECTION 3: NEW COLUMNS VERIFICATION
-- ============================================================================

SELECT '=== NEW COLUMNS VERIFICATION ===' AS '';

-- events.year_id
SELECT
    CASE
        WHEN COUNT(*) = 1 THEN 'PASS: events.year_id exists'
        ELSE 'FAIL: events.year_id missing'
    END AS check_result
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'events'
  AND COLUMN_NAME = 'year_id';

-- projects.year_id
SELECT
    CASE
        WHEN COUNT(*) = 1 THEN 'PASS: projects.year_id exists'
        ELSE 'FAIL: projects.year_id missing'
    END AS check_result
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'projects'
  AND COLUMN_NAME = 'year_id';

-- donations.status_updated_by
SELECT
    CASE
        WHEN COUNT(*) = 1 THEN 'PASS: donations.status_updated_by exists'
        ELSE 'FAIL: donations.status_updated_by missing'
    END AS check_result
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'donations'
  AND COLUMN_NAME = 'status_updated_by';

-- donations.status_updated_role
SELECT
    CASE
        WHEN COUNT(*) = 1 THEN 'PASS: donations.status_updated_role exists'
        ELSE 'FAIL: donations.status_updated_role missing'
    END AS check_result
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'donations'
  AND COLUMN_NAME = 'status_updated_role';

-- rotary_years.created_at preserved (Step 4 removed for PHP compatibility)
SELECT
    CASE
        WHEN COUNT(*) = 1 THEN 'PASS: rotary_years.created_at preserved (PHP compat)'
        ELSE 'FAIL: rotary_years.created_at unexpectedly removed'
    END AS check_result
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'rotary_years'
  AND COLUMN_NAME = 'created_at';

-- ============================================================================
-- SECTION 4: FOREIGN KEY INTEGRITY CHECK
-- ============================================================================

SELECT '=== FK INTEGRITY CHECK ===' AS '';

-- Orphaned leadership_assignments
SELECT
    CASE
        WHEN COUNT(*) = 0 THEN 'PASS: No orphaned leadership_assignments'
        ELSE CONCAT('FAIL: ', COUNT(*), ' orphaned leadership_assignments')
    END AS check_result
FROM leadership_assignments la
LEFT JOIN members m ON la.member_id = m.member_id
WHERE m.member_id IS NULL;

SELECT
    CASE
        WHEN COUNT(*) = 0 THEN 'PASS: No orphaned leadership_assignments (year FK)'
        ELSE CONCAT('FAIL: ', COUNT(*), ' orphaned leadership_assignments (year FK)')
    END AS check_result
FROM leadership_assignments la
LEFT JOIN rotary_years ry ON la.year_id = ry.id
WHERE ry.id IS NULL;

-- Orphaned event_polling
SELECT
    CASE
        WHEN COUNT(*) = 0 THEN 'PASS: No orphaned event_polling'
        ELSE CONCAT('FAIL: ', COUNT(*), ' orphaned event_polling')
    END AS check_result
FROM event_polling ep
LEFT JOIN events e ON ep.event_id = e.event_id
WHERE e.event_id IS NULL;

-- Orphaned donation_status_history
SELECT
    CASE
        WHEN COUNT(*) = 0 THEN 'PASS: No orphaned donation_status_history'
        ELSE CONCAT('FAIL: ', COUNT(*), ' orphaned donation_status_history')
    END AS check_result
FROM donation_status_history dsh
LEFT JOIN donations d ON dsh.donation_id = d.donation_id
WHERE d.donation_id IS NULL;

-- Orphaned password_reset_tokens
SELECT
    CASE
        WHEN COUNT(*) = 0 THEN 'PASS: No orphaned password_reset_tokens'
        ELSE CONCAT('FAIL: ', COUNT(*), ' orphaned password_reset_tokens')
    END AS check_result
FROM password_reset_tokens prt
LEFT JOIN admins a ON prt.admin_id = a.admin_id
WHERE a.admin_id IS NULL;

-- ============================================================================
-- SECTION 5: VIEW FUNCTIONALITY CHECK
-- ============================================================================

SELECT '=== website_team_view CHECK ===' AS '';

SELECT COUNT(*) AS total_rows FROM website_team_view;

-- Check display_group distribution
SELECT display_group, COUNT(*) AS count
FROM website_team_view
GROUP BY display_group;

-- ============================================================================
-- SECTION 6: SEED DATA VERIFICATION
-- ============================================================================

SELECT '=== committee_position SEED DATA ===' AS '';

SELECT position_id, position_name, display_order, is_active
FROM committee_position
ORDER BY display_order;

-- ============================================================================
-- SECTION 7: NEW CONSTRAINTS VERIFICATION
-- ============================================================================

SELECT '=== NEW UNIQUE CONSTRAINTS ===' AS '';

SELECT
    TABLE_NAME,
    INDEX_NAME AS constraint_name,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND NON_UNIQUE = 0
  AND INDEX_NAME != 'PRIMARY'
  AND TABLE_NAME IN ('committee_position', 'committee_members', 'website_display',
                      'leadership_assignments', 'projects')
GROUP BY TABLE_NAME, INDEX_NAME
ORDER BY TABLE_NAME;

-- ============================================================================
-- SECTION 8: COMPLETE TABLE COUNT
-- ============================================================================

SELECT '=== TOTAL TABLE COUNT ===' AS '';

SELECT
    CASE
        WHEN COUNT(*) = 30 THEN CONCAT('PASS: ', COUNT(*), ' tables (expected 30)')
        ELSE CONCAT('FAIL: ', COUNT(*), ' tables (expected 30)')
    END AS check_result
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_TYPE = 'BASE TABLE';

-- Total views
SELECT
    CASE
        WHEN COUNT(*) >= 1 THEN CONCAT('PASS: ', COUNT(*), ' view(s) found')
        ELSE 'FAIL: No views found'
    END AS check_result
FROM information_schema.VIEWS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'website_team_view';

-- ============================================================================
-- SECTION 9: INDEX COUNT
-- ============================================================================

SELECT '=== TOTAL INDEX COUNT ===' AS '';

SELECT
    TABLE_NAME,
    COUNT(DISTINCT INDEX_NAME) AS index_count
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND INDEX_NAME != 'PRIMARY'
GROUP BY TABLE_NAME
ORDER BY TABLE_NAME;

-- ============================================================================
-- VALIDATION COMPLETE
-- ============================================================================
-- If all checks PASS: Migration successful. Proceed to go-live.
-- If any checks FAIL: Investigate and fix before go-live.
-- Next step: Execute 010_rollback_reference.sql ONLY if rollback needed
-- ============================================================================
