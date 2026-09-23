-- ============================================================================
-- RCMP Migration 008: Seed Lookup Data
-- Rotary Club of Virar — Management Platform
-- Date: 2026-07-16
-- Purpose: Seed committee_position lookup table with default positions
-- Prerequisite: 007_create_indexes.sql completed successfully
-- ============================================================================

-- ============================================================================
-- SEED: committee_position
-- ============================================================================
-- Standard positions from Rotary governance documents
-- display_order: Lower = higher priority in dropdowns
-- is_active: All default to active (1)
-- ============================================================================

INSERT IGNORE INTO committee_position (position_name, display_order, is_active) VALUES
    ('Chairperson', 1, 1),
    ('Co-Chair', 2, 1),
    ('Secretary', 3, 1),
    ('Member', 4, 1);

SELECT CONCAT(ROW_COUNT(), ' committee_position rows inserted') AS status;

-- Verify seed data
SELECT position_id, position_name, display_order, is_active
FROM committee_position
ORDER BY display_order;

-- ============================================================================
-- SEED DATA COMPLETE
-- ============================================================================
-- Next step: Execute 009_validation_queries.sql
-- ============================================================================
