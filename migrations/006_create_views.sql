-- ============================================================================
-- RCMP Migration 006: Create Views
-- Rotary Club of Virar — Management Platform
-- Date: 2026-07-16
-- Purpose: Create website_team_view derived view
-- Prerequisite: 005_create_junction_tables.sql completed successfully
-- Dependencies: leadership_assignments, committees, committee_members,
--               committee_position, members, website_display
-- ============================================================================

-- ============================================================================
-- VIEW: website_team_view
-- ============================================================================
-- Entity Type: Derived View
-- Business Owner: Web Admin / Club Secretary
-- Purpose: Unified view of who appears on the website team page, per Rotary year
-- Architecture: Combines three independent sources of truth:
--   1. leadership_assignments — who holds what leadership role per year
--   2. committee_members — who serves on which committee per year
--   3. website_display — how each member appears on the website
-- ============================================================================

CREATE OR REPLACE VIEW website_team_view AS

-- ─── LEADERSHIP GROUP ──────────────────────────────────────────────
SELECT
    'leadership' AS display_group,
    la.year_id,
    m.member_id,
    m.name,
    m.email,
    m.phone_number,
    m.photo_url,
    m.short_bio,
    m.profession,
    la.role AS computed_role,
    COALESCE(wd.custom_title, la.role) AS display_title,
    COALESCE(wd.display_order, FIELD(la.role, 'President', 'Secretary', 'Treasurer') * 100) AS sort_order,
    COALESCE(wd.is_visible, 1) AS is_visible
FROM leadership_assignments la
JOIN members m ON la.member_id = m.member_id
LEFT JOIN website_display wd ON m.member_id = wd.member_id

UNION ALL

-- ─── COMMITTEE GROUP ───────────────────────────────────────────────
SELECT
    'committee' AS display_group,
    c.year_id,
    m.member_id,
    m.name,
    m.email,
    m.phone_number,
    m.photo_url,
    m.short_bio,
    m.profession,
    cp.position_name AS computed_role,
    COALESCE(wd.custom_title, cp.position_name) AS display_title,
    COALESCE(wd.display_order, 500 + cm.id) AS sort_order,
    COALESCE(wd.is_visible, 1) AS is_visible
FROM committee_members cm
JOIN committees c ON cm.committee_id = c.committee_id
JOIN members m ON cm.member_id = m.member_id
JOIN committee_position cp ON cm.position_id = cp.position_id
LEFT JOIN website_display wd ON m.member_id = wd.member_id

UNION ALL

-- ─── BOARD GROUP ───────────────────────────────────────────────────
SELECT
    'board' AS display_group,
    NULL AS rotary_year_id,
    m.member_id,
    m.name,
    m.email,
    m.phone_number,
    m.photo_url,
    m.short_bio,
    m.profession,
    'Board Member' AS computed_role,
    COALESCE(wd.custom_title, 'Board Member') AS display_title,
    COALESCE(wd.display_order, 1000) AS sort_order,
    COALESCE(wd.is_visible, 1) AS is_visible
FROM members m
LEFT JOIN website_display wd ON m.member_id = wd.member_id
WHERE m.status = 'Active'
  AND wd.display_group = 'board';

SELECT 'STEP 1 COMPLETE: website_team_view created' AS status;

-- ============================================================================
-- VIEWS COMPLETE
-- ============================================================================
-- Next step: Execute 007_create_indexes.sql
-- ============================================================================
