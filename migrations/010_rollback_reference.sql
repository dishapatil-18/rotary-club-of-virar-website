-- ============================================================================
-- RCMP Migration 010: Rollback Reference
-- Rotary Club of Virar — Management Platform
-- Date: 2026-07-16
-- Purpose: Emergency rollback — drop all new objects in reverse order
-- WARNING: Only execute if migration must be reversed
-- Prerequisite: None (standalone emergency script)
-- ============================================================================

-- ============================================================================
-- PHASE A: DROP VIEW (reverse of 006)
-- ============================================================================

DROP VIEW IF EXISTS website_team_view;

SELECT 'ROLLBACK A: website_team_view dropped' AS status;

-- ============================================================================
-- PHASE B: DROP NEW TABLES (reverse of 005, 004, 003)
-- ============================================================================
-- Drop in reverse dependency order

DROP TABLE IF EXISTS donation_allocation;
DROP TABLE IF EXISTS website_display;
DROP TABLE IF EXISTS committee_members;
DROP TABLE IF EXISTS committees;
DROP TABLE IF EXISTS committee_position;

SELECT 'ROLLBACK B: All new tables dropped' AS status;

-- ============================================================================
-- PHASE C: DROP NEW INDEXES (reverse of 007)
-- ============================================================================

-- Existing table indexes
DROP INDEX IF EXISTS idx_events_year ON events;
DROP INDEX IF EXISTS idx_events_start ON events;
DROP INDEX IF EXISTS idx_projects_year ON projects;
DROP INDEX IF EXISTS idx_projects_status ON projects;
DROP INDEX IF EXISTS idx_donations_status ON donations;
DROP INDEX IF EXISTS idx_donations_date ON donations;
DROP INDEX IF EXISTS idx_members_status ON members;
DROP INDEX IF EXISTS idx_rotary_years_current ON rotary_years;
DROP INDEX IF EXISTS idx_contact_unread ON contact_messages;
DROP INDEX IF EXISTS idx_collab_status ON collaborations;
DROP INDEX IF EXISTS idx_media_ref ON media_gallery;
DROP INDEX IF EXISTS idx_media_status ON media_gallery;
DROP INDEX IF EXISTS idx_gallery_category ON gallery_media;
DROP INDEX IF EXISTS idx_event_polling_member ON event_polling;
DROP INDEX IF EXISTS idx_event_polling_composite ON event_polling;
DROP INDEX IF EXISTS idx_donation_history_donation ON donation_status_history;
DROP INDEX IF EXISTS idx_donation_history_admin ON donation_status_history;
DROP INDEX IF EXISTS idx_password_reset_admin ON password_reset_tokens;

SELECT 'ROLLBACK C: All new indexes dropped' AS status;

-- ============================================================================
-- PHASE D: REVERSE ALTER STATEMENTS (reverse of 002)
-- ============================================================================

-- D1: Restore rotary_years.created_at
ALTER TABLE rotary_years
    ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

SELECT 'ROLLBACK D1: rotary_years.created_at restored' AS status;

-- D2: Remove donations audit columns
ALTER TABLE donations
    DROP COLUMN IF EXISTS status_updated_by,
    DROP COLUMN IF EXISTS status_updated_role;

SELECT 'ROLLBACK D2: donations.audit columns removed' AS status;

-- D3: Remove leadership_assignments UNIQUE constraint
ALTER TABLE leadership_assignments
    DROP INDEX IF EXISTS unique_year_role;

SELECT 'ROLLBACK D3: leadership_assignments.UNIQUE constraint removed' AS status;

-- D4: Remove projects.rotary_year_id
ALTER TABLE projects
    DROP FOREIGN KEY IF EXISTS fk_projects_year;
ALTER TABLE projects
    DROP COLUMN IF EXISTS rotary_year_id;

SELECT 'ROLLBACK D4: projects.rotary_year_id removed' AS status;

-- D5: Remove events.rotary_year_id
ALTER TABLE events
    DROP FOREIGN KEY IF EXISTS fk_events_year;
ALTER TABLE events
    DROP COLUMN IF EXISTS rotary_year_id;

SELECT 'ROLLBACK D5: events.rotary_year_id removed' AS status;

-- D6: Restore project_reports PK if it was changed
-- NOTE: Only run if project_reports had a single PK before migration
-- Check current PK first:
-- SHOW CREATE TABLE project_reports;
-- If PK is (project_id, year) and it was changed from single PK:
-- ALTER TABLE project_reports DROP PRIMARY KEY, ADD PRIMARY KEY (project_id);

-- ============================================================================
-- PHASE E: VERIFY ROLLBACK
-- ============================================================================

SELECT '=== POST-ROLLBACK TABLE COUNT ===' AS '';

SELECT
    CASE
        WHEN COUNT(*) = 21 THEN CONCAT('PASS: ', COUNT(*), ' tables (back to original)')
        ELSE CONCAT('WARNING: ', COUNT(*), ' tables (expected 21)')
    END AS check_result
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_TYPE = 'BASE TABLE';

-- ============================================================================
-- ROLLBACK COMPLETE
-- ============================================================================
-- After rollback:
--   1. Verify application works (login, website, admin panels)
--   2. Check error logs
--   3. Restore database from backup if data was affected
--   4. Document root cause
--   5. Plan remediation
-- ============================================================================
