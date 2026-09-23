-- ============================================================================
-- RCMP Migration 005: Create Junction Tables
-- Rotary Club of Virar — Management Platform
-- Date: 2026-07-16
-- Purpose: Create committee_members, website_display, donation_allocation
-- Prerequisite: 004_create_master_tables.sql completed successfully
-- ============================================================================

-- ============================================================================
-- TABLE: committee_members
-- ============================================================================
-- Entity Type: Junction
-- Business Owner: Club Secretary
-- Purpose: Maps members to committee roles per committee
-- Dependencies: committees (FK), members (FK), committee_position (FK)
-- ============================================================================

CREATE TABLE IF NOT EXISTS committee_members (
    id INT AUTO_INCREMENT,
    committee_id INT NOT NULL,
    member_id INT NOT NULL,
    position_id INT NOT NULL,
    joined_date DATE NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_committee_member (committee_id, member_id),
    CONSTRAINT fk_cm_committee
        FOREIGN KEY (committee_id) REFERENCES committees(committee_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_cm_member
        FOREIGN KEY (member_id) REFERENCES members(member_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_cm_position
        FOREIGN KEY (position_id) REFERENCES committee_position(position_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'STEP 1 COMPLETE: committee_members table created' AS status;

-- ============================================================================
-- TABLE: website_display
-- ============================================================================
-- Entity Type: Display/Presentation
-- Business Owner: Web Admin / Club Secretary
-- Purpose: Presentation metadata for website team display
-- Dependencies: members (FK)
-- Business Rule: Stores ONLY display metadata — NOT role information
-- ============================================================================

CREATE TABLE IF NOT EXISTS website_display (
    id INT AUTO_INCREMENT,
    member_id INT NOT NULL,
    display_group ENUM('leadership','committee','board','hidden') NOT NULL DEFAULT 'board',
    display_order INT NOT NULL DEFAULT 0,
    custom_title VARCHAR(255) NULL DEFAULT NULL,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY unique_wd_member (member_id),
    CONSTRAINT fk_wd_member
        FOREIGN KEY (member_id) REFERENCES members(member_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'STEP 2 COMPLETE: website_display table created' AS status;

-- ============================================================================
-- TABLE: donation_allocation
-- ============================================================================
-- Entity Type: Junction (Optional)
-- Business Owner: Club Treasurer
-- Purpose: Optional allocation of donations to projects, events, or fund purposes
-- Dependencies: donations (FK), projects (FK nullable), events (FK nullable), admins (FK nullable)
-- Business Rules:
--   0 allocation records = unrestricted donation
--   1 allocation record  = fully restricted
--   N allocation records = split donation
-- ============================================================================

CREATE TABLE IF NOT EXISTS donation_allocation (
    allocation_id INT AUTO_INCREMENT,
    donation_id INT NOT NULL,
    project_id INT NULL DEFAULT NULL,
    event_id INT NULL DEFAULT NULL,
    fund_purpose VARCHAR(255) NOT NULL DEFAULT '',
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    allocated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    allocated_by INT NULL DEFAULT NULL,
    PRIMARY KEY (allocation_id),
    CONSTRAINT fk_da_donation
        FOREIGN KEY (donation_id) REFERENCES donations(donation_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_da_project
        FOREIGN KEY (project_id) REFERENCES projects(project_id)
        ON DELETE SET NULL,
    CONSTRAINT fk_da_event
        FOREIGN KEY (event_id) REFERENCES events(event_id)
        ON DELETE SET NULL,
    CONSTRAINT fk_da_admin
        FOREIGN KEY (allocated_by) REFERENCES admins(admin_id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'STEP 3 COMPLETE: donation_allocation table created' AS status;

-- ============================================================================
-- JUNCTION TABLES COMPLETE
-- ============================================================================
-- Next step: Execute 006_create_views.sql
-- ============================================================================
