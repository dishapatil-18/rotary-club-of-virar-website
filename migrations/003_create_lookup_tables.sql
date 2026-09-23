-- ============================================================================
-- RCMP MIGRATION 003: CREATE LOOKUP TABLES
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
-- Create the committee_position lookup table.
-- This table provides a controlled vocabulary for committee role titles,
-- ensuring governance consistency and standardized historical reporting.
--
-- TABLES CREATED
-- ---------------
--   1. committee_position — Lookup table for committee role titles
--
-- PREREQUISITES
-- -------------
--   - 002_alter_existing_tables.sql completed successfully
--   - Full database backup verified
--   - Application is in maintenance mode
--
-- DEPENDENCIES
-- ------------
--   - 002_alter_existing_tables.sql (must run first)
--   - No table dependencies (standalone lookup table)
--
-- EXECUTION
-- ---------
--   mysql -u root -p rotary < 003_create_lookup_tables.sql
--
-- EXPECTED DURATION
-- -----------------
--   < 2 seconds (single CREATE TABLE statement)
--
-- IDEMPOTENCY
-- -----------
--   This script is fully idempotent:
--   - Uses CREATE TABLE IF NOT EXISTS
--   - Safe to re-run if interrupted
--   - No duplicate tables or constraints will be created
--
-- SEED DATA
-- ---------
--   Seed data (Chairperson, Co-Chair, Secretary, Member) is in
--   008_seed_lookup_data.sql — a separate file for clean separation.
--
-- ROLLBACK
-- --------
--   DROP TABLE IF EXISTS committee_position;
--
-- ============================================================================

-- ============================================================================
-- TABLE: committee_position
-- ============================================================================
--
-- ENTITY TYPE:   Lookup
-- BUSINESS OWNER: Super Admin (managed via admin panel)
-- PURPOSE:       Controlled vocabulary for committee role titles
--
-- BUSINESS RULES:
--   - Committee positions are defined by Rotary governance documents
--   - A lookup table enforces controlled vocabulary at the data level
--   - Prevents typos/variation across years
--   - Ensures governance reports are consistent
--   - Super Admin may add new position titles without code changes
--
-- REFERENCED BY:  committee_members.position_id (ON DELETE RESTRICT)
--                → Cannot delete a position that is in use
--
-- SEED DATA:     4 default positions (see 008_seed_lookup_data.sql)
--                Chairperson, Co-Chair, Secretary, Member
--
-- COLUMNS:
--   position_id    INT AUTO_INCREMENT PK    Unique identifier
--   position_name  VARCHAR(100) NOT NULL    Position title (unique)
--   display_order  INT NOT NULL DEFAULT 0   Sort order for admin dropdowns
--   is_active      TINYINT(1) NOT NULL DEFAULT 1   Whether position is usable
--
-- CONSTRAINTS:
--   PRIMARY KEY:  position_id
--   UNIQUE:       position_name (one record per position title)
--
-- INDEXES:
--   PRIMARY on position_id (auto)
--   UNIQUE on position_name (auto from constraint)
--
-- ============================================================================

SELECT '--- Creating committee_position lookup table ---' AS '';

CREATE TABLE IF NOT EXISTS committee_position (
    position_id   INT           AUTO_INCREMENT,
    position_name VARCHAR(100)  NOT NULL,
    display_order INT           NOT NULL DEFAULT 0,
    is_active     TINYINT(1)    NOT NULL DEFAULT 1,
    PRIMARY KEY (position_id),
    CONSTRAINT unique_position_name
        UNIQUE (position_name)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

SELECT 'STEP 1 COMPLETE: committee_position table created (or already exists)' AS status;

-- ============================================================================
-- LOOKUP TABLES COMPLETE
-- ============================================================================
--
-- SUMMARY:
--   committee_position: Created with 4 columns, 1 UNIQUE constraint
--
-- NEXT STEP: Execute 004_create_master_tables.sql
-- ============================================================================

SELECT '=== 003_create_lookup_tables.sql COMPLETE ===' AS status;
SELECT 'Next step: Execute 004_create_master_tables.sql' AS next_step;

-- ============================================================================
-- END OF 003_create_lookup_tables.sql
-- ============================================================================
