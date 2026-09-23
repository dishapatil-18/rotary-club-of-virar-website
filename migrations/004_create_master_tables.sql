-- ============================================================================
-- RCMP Migration 004: Create Master Tables
-- Rotary Club of Virar — Management Platform
-- Date: 2026-07-16
-- Purpose: Create committees master table
-- Prerequisite: 003_create_lookup_tables.sql completed successfully
-- ============================================================================

-- ============================================================================
-- TABLE: committees
-- ============================================================================
-- Entity Type: Core Business
-- Business Owner: Club Secretary
-- Purpose: Committee definitions (Standing, Ad-hoc, District-level)
-- Dependencies: rotary_years (FK: committee belongs to a year)
-- ============================================================================

CREATE TABLE IF NOT EXISTS committees (
    committee_id INT AUTO_INCREMENT,
    committee_name VARCHAR(255) NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    year_id INT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (committee_id),
    CONSTRAINT fk_committees_year
        FOREIGN KEY (year_id) REFERENCES rotary_years(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'STEP 1 COMPLETE: committees table created' AS status;

-- ============================================================================
-- MASTER TABLES COMPLETE
-- ============================================================================
-- Next step: Execute 005_create_junction_tables.sql
-- ============================================================================
