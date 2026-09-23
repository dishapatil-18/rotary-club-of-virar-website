-- ============================================================================
-- RCMP Migration 007: Create Performance Indexes
-- Rotary Club of Virar — Management Platform
-- Date: 2026-07-16
-- Purpose: Create 23 performance indexes across existing and new tables
-- Prerequisite: 006_create_views.sql completed successfully
-- Note: FK constraint indexes are created automatically by MySQL.
--       These are ADDITIONAL performance indexes for query optimization.
--
-- EXCLUDED INDEXES (created idempotently in 002_alter_existing_tables.sql):
--   - idx_events_year   (002 Step 1c)
--   - idx_projects_year (002 Step 2c)
--   Total excluded: 2  |  Total created here: 23  |  Grand total: 25
--
-- IDEMPOTENCY
-- -----------
--   This script is fully idempotent:
--   - Each CREATE INDEX checks information_schema before executing
--   - Safe to re-run if interrupted or if a step fails
--   - No duplicate index errors will occur
-- ============================================================================

-- ============================================================================
-- EXISTING TABLES — New Indexes
-- ============================================================================

-- events: Date-range queries
-- NOTE: idx_events_year is created in 002 Step 1c (idempotent)
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND INDEX_NAME = 'idx_events_start'
);
SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_events_start ON events(start_date)',
    'SELECT "SKIP — idx_events_start already exists" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- projects: Status-based filtering
-- NOTE: idx_projects_year is created in 002 Step 2c (idempotent)
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND INDEX_NAME = 'idx_projects_status'
);
SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_projects_status ON projects(status)',
    'SELECT "SKIP — idx_projects_status already exists" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- donations: Pipeline filtering and date-range reporting
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'donations' AND INDEX_NAME = 'idx_donations_status'
);
SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_donations_status ON donations(status)',
    'SELECT "SKIP — idx_donations_status already exists" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'donations' AND INDEX_NAME = 'idx_donations_date'
);
SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_donations_date ON donations(date)',
    'SELECT "SKIP — idx_donations_date already exists" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- members: Active members frequent query
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'members' AND INDEX_NAME = 'idx_members_status'
);
SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_members_status ON members(status)',
    'SELECT "SKIP — idx_members_status already exists" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rotary_years: Find current year
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rotary_years' AND INDEX_NAME = 'idx_rotary_years_current'
);
SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_rotary_years_current ON rotary_years(is_current)',
    'SELECT "SKIP — idx_rotary_years_current already exists" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- contact_messages: Unread message filtering (status column: 'new','read','replied')
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_messages' AND INDEX_NAME = 'idx_contact_unread'
);
SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_contact_unread ON contact_messages(status)',
    'SELECT "SKIP — idx_contact_unread already exists" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- collaborations: Proposal status filtering
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'collaborations' AND INDEX_NAME = 'idx_collab_status'
);
SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_collab_status ON collaborations(status)',
    'SELECT "SKIP — idx_collab_status already exists" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- media_gallery: Polymorphic lookup and active filtering
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'media_gallery' AND INDEX_NAME = 'idx_media_ref'
);
SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_media_ref ON media_gallery(reference_type, reference_id)',
    'SELECT "SKIP — idx_media_ref already exists" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'media_gallery' AND INDEX_NAME = 'idx_media_status'
);
SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_media_status ON media_gallery(status)',
    'SELECT "SKIP — idx_media_status already exists" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- gallery_media: Category-based filtering
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gallery_media' AND INDEX_NAME = 'idx_gallery_category'
);
SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_gallery_category ON gallery_media(category)',
    'SELECT "SKIP — idx_gallery_category already exists" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- event_polling: Member-based response history and duplicate prevention
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event_polling' AND INDEX_NAME = 'idx_event_polling_member'
);
SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_event_polling_member ON event_polling(member_id)',
    'SELECT "SKIP — idx_event_polling_member already exists" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event_polling' AND INDEX_NAME = 'idx_event_polling_composite'
);
SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_event_polling_composite ON event_polling(event_id, member_id)',
    'SELECT "SKIP — idx_event_polling_composite already exists" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- donation_status_history: Donation-based history and admin activity
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'donation_status_history' AND INDEX_NAME = 'idx_donation_history_donation'
);
SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_donation_history_donation ON donation_status_history(donation_id)',
    'SELECT "SKIP — idx_donation_history_donation already exists" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'donation_status_history' AND INDEX_NAME = 'idx_donation_history_admin'
);
SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_donation_history_admin ON donation_status_history(admin_id)',
    'SELECT "SKIP — idx_donation_history_admin already exists" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- password_reset_tokens: Admin-based token lookup
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'password_reset_tokens' AND INDEX_NAME = 'idx_password_reset_admin'
);
SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_password_reset_admin ON password_reset_tokens(admin_id)',
    'SELECT "SKIP — idx_password_reset_admin already exists" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================================
-- NEW TABLES — Indexes
-- ============================================================================

-- website_display: Website rendering performance
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'website_display' AND INDEX_NAME = 'idx_website_display_group'
);
SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_website_display_group ON website_display(display_group)',
    'SELECT "SKIP — idx_website_display_group already exists" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'website_display' AND INDEX_NAME = 'idx_website_display_order'
);
SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_website_display_order ON website_display(display_group, display_order)',
    'SELECT "SKIP — idx_website_display_order already exists" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- committee_members: Committee listing and member assignment lookup
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'committee_members' AND INDEX_NAME = 'idx_committee_members_committee'
);
SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_committee_members_committee ON committee_members(committee_id)',
    'SELECT "SKIP — idx_committee_members_committee already exists" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'committee_members' AND INDEX_NAME = 'idx_committee_members_member'
);
SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_committee_members_member ON committee_members(member_id)',
    'SELECT "SKIP — idx_committee_members_member already exists" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- donation_allocation: Allocation reporting by project/event
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'donation_allocation' AND INDEX_NAME = 'idx_donation_alloc_donation'
);
SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_donation_alloc_donation ON donation_allocation(donation_id)',
    'SELECT "SKIP — idx_donation_alloc_donation already exists" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'donation_allocation' AND INDEX_NAME = 'idx_donation_alloc_project'
);
SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_donation_alloc_project ON donation_allocation(project_id)',
    'SELECT "SKIP — idx_donation_alloc_project already exists" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'donation_allocation' AND INDEX_NAME = 'idx_donation_alloc_event'
);
SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_donation_alloc_event ON donation_allocation(event_id)',
    'SELECT "SKIP — idx_donation_alloc_event already exists" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================================
-- INDEX SUMMARY
-- ============================================================================
-- 002_alter_existing_tables.sql: 2 indexes (idx_events_year, idx_projects_year)
-- 007_create_indexes.sql:        23 indexes (above)
-- Grand total:                   25 performance indexes
-- ============================================================================

SELECT 'ALL 23 INDEXES CREATED (idempotent) — 25 TOTAL WITH 002' AS status;

-- ============================================================================
-- INDEXES COMPLETE
-- ============================================================================
-- Next step: Execute 008_seed_lookup_data.sql
-- ============================================================================

-- ============================================================================
-- END OF 007_create_indexes.sql
-- ============================================================================
