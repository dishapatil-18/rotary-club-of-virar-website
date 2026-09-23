<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';

$needsSuperAdmin = false;
$checkResult = $conn->query("SELECT admin_id FROM admins WHERE role = 'super_admin' LIMIT 1");
if ($checkResult && $checkResult->num_rows > 0) {
    $needsSuperAdmin = true;
}

if ($needsSuperAdmin && (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'super_admin')) {
    header("Location: ../login.php");
    exit;
}

$messages = [];
$errors = [];

// STEP 1: Alter admins table role enum
$r = $conn->query("SHOW COLUMNS FROM admins LIKE 'role'");
if ($r && $row = $r->fetch_assoc()) {
    $currentEnum = $row['Type'];
    if (strpos($currentEnum, 'super_admin') === false) {
        $newEnum = str_replace("enum('", "enum('super_admin','", $currentEnum);
        $sql = "ALTER TABLE admins MODIFY COLUMN role $newEnum";
        if ($conn->query($sql)) {
            $messages[] = "Added 'super_admin' to admins.role enum.";
        } else {
            $errors[] = "Failed to alter admins.role: " . $conn->error;
        }
    } else {
        $messages[] = "admins.role already has 'super_admin'.";
    }
} else {
    $errors[] = "Could not read admins table: " . $conn->error;
}

// STEP 2: Create rotary_years table
$sql = "CREATE TABLE IF NOT EXISTS rotary_years (
    id INT AUTO_INCREMENT PRIMARY KEY,
    year_name VARCHAR(20) NOT NULL UNIQUE,
    is_current TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sql)) {
    $messages[] = "rotary_years table ready.";
} else {
    $errors[] = "Failed to create rotary_years: " . $conn->error;
}

// STEP 3: Create leadership_assignments table
$sql = "CREATE TABLE IF NOT EXISTS leadership_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rotary_year_id INT NOT NULL,
    member_id INT NOT NULL,
    role VARCHAR(50) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rotary_year_id) REFERENCES rotary_years(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    UNIQUE KEY unique_year_role (rotary_year_id, role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sql)) {
    $messages[] = "leadership_assignments table ready.";
} else {
    $errors[] = "Failed to create leadership_assignments: " . $conn->error;
}

// STEP 4: Create site_content table
$sql = "CREATE TABLE IF NOT EXISTS site_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page VARCHAR(50) NOT NULL,
    section VARCHAR(100) NOT NULL,
    content TEXT,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_page_section (page, section)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sql)) {
    $messages[] = "site_content table ready.";
} else {
    $errors[] = "Failed to create site_content: " . $conn->error;
}

// STEP 5: Seed default rotary years
$currentYear = (int)date('Y');
$prevYear = $currentYear - 1;
$nextYear = $currentYear + 1;
$yearName = $prevYear . '-' . substr((string)$currentYear, -2);
$yearNameNext = $currentYear . '-' . substr((string)$nextYear, -2);

$r = $conn->query("SELECT COUNT(*) as c FROM rotary_years");
$yearCount = ($r && $row = $r->fetch_assoc()) ? (int)$row['c'] : 0;

if ($yearCount == 0) {
    $stmt = $conn->prepare("INSERT INTO rotary_years (year_name, is_current) VALUES (?, 1)");
    $stmt->bind_param("s", $yearName);
    if ($stmt->execute()) {
        $messages[] = "Created current rotary year: $yearName.";
    } else {
        $errors[] = "Failed to seed year: " . $stmt->error;
    }
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO rotary_years (year_name, is_current) VALUES (?, 0)");
    $stmt->bind_param("s", $yearNameNext);
    $stmt->execute();
    $stmt->close();
    $messages[] = "Created next rotary year: $yearNameNext.";
} else {
    $messages[] = "Rotary years already seeded ($yearCount years exist).";
}

// STEP 6: Ensure admins table has status column with proper ENUM
$r = $conn->query("SHOW COLUMNS FROM admins LIKE 'status'");
if ($r) {
    if ($r->num_rows == 0) {
        $sql = "ALTER TABLE admins ADD COLUMN status ENUM('active','inactive') DEFAULT 'active' AFTER photo_url";
        if ($conn->query($sql)) {
            $messages[] = "Added 'status' column to admins table.";
        } else {
            $errors[] = "Failed to add status column: " . $conn->error;
        }
    } else {
        $col = $r->fetch_assoc();
        if (strpos($col['Type'], 'enum') === false) {
            $sql = "ALTER TABLE admins MODIFY COLUMN status ENUM('active','inactive') DEFAULT 'active'";
            if ($conn->query($sql)) {
                $messages[] = "Updated admins.status to ENUM('active','inactive').";
            } else {
                $errors[] = "Failed to modify status column: " . $conn->error;
            }
        } else {
            $messages[] = "admins.status column already properly configured.";
        }
    }
} else {
    $errors[] = "Could not read admins table: " . $conn->error;
}

// STEP 7: Ensure exactly ONE Super Admin with environment-based credentials
$superEmail = env('SUPER_ADMIN_EMAIL', '') ?: 'admin@example.com';
$superPasswordRaw = env('SUPER_ADMIN_PASSWORD', '');
$forceReset = strtolower(env('RESET_SUPER_ADMIN_PASSWORD', 'false')) === 'true';

if ($superPasswordRaw === '') {
    $superPasswordRaw = bin2hex(random_bytes(8));
    $messages[] = "No SUPER_ADMIN_PASSWORD set in .env. Generated temporary password: $superPasswordRaw";
}
$superPassword = password_hash($superPasswordRaw, PASSWORD_DEFAULT);

// Remove any super_admin accounts that are not the designated one
$delStmt = $conn->prepare("DELETE FROM admins WHERE role = 'super_admin' AND email != ?");
$delStmt->bind_param("s", $superEmail);
$delStmt->execute();
$delStmt->close();

// Check if the designated super admin exists
$rStmt = $conn->prepare("SELECT admin_id, password FROM admins WHERE email = ?");
$rStmt->bind_param("s", $superEmail);
$rStmt->execute();
$r = $rStmt->get_result();
if ($r && $r->num_rows > 0) {
    $existingAdmin = $r->fetch_assoc();
    $hasValidHash = !empty($existingAdmin['password']) && strlen($existingAdmin['password']) === 60
                    && password_verify($superPasswordRaw, $existingAdmin['password']);

    if ($hasValidHash && !$forceReset) {
        $messages[] = "Super Admin account exists with valid password. Skipping password overwrite. Set RESET_SUPER_ADMIN_PASSWORD=true in .env to force reset.";
    } else {
        $updateStmt = $conn->prepare("UPDATE admins SET password = ?, role = 'super_admin', status = 'active' WHERE email = ?");
        $updateStmt->bind_param("ss", $superPassword, $superEmail);
        if ($updateStmt->execute()) {
            $reason = $forceReset ? 'RESET_SUPER_ADMIN_PASSWORD=true was set' : 'password hash was missing or invalid';
            $messages[] = "Super Admin password updated ($reason). (Email: $superEmail)";
        } else {
            $errors[] = "Failed to update super admin: " . $updateStmt->error;
        }
        $updateStmt->close();
    }
} else {
    // Create new Super Admin
    $name = 'Super Admin';
    $stmt = $conn->prepare("INSERT INTO admins (name, email, password, role, photo_url, phone, created_at) VALUES (?, ?, ?, 'super_admin', '', '', NOW())");
    $stmt->bind_param("sss", $name, $superEmail, $superPassword);
    if ($stmt->execute()) {
        $messages[] = "Super Admin account created (Email: $superEmail).";
    } else {
        $errors[] = "Failed to create super admin: " . $stmt->error;
    }
    $stmt->close();
}
$rStmt->close();

// Ensure no duplicate super_admin accounts exist (keep only the first one if duplicates somehow exist)
$dupStmt = $conn->prepare("DELETE FROM admins WHERE role = 'super_admin' AND admin_id NOT IN (SELECT admin_id FROM (SELECT MIN(admin_id) as admin_id FROM admins WHERE role = 'super_admin') AS t)");
if ($dupStmt) { $dupStmt->execute(); $dupStmt->close(); }

// STEP 8: Create password_reset_tokens table
$sql = "CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    token VARCHAR(128) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(admin_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sql)) {
    $messages[] = "password_reset_tokens table ready.";
} else {
    $errors[] = "Failed to create password_reset_tokens: " . $conn->error;
}

// STEP 9: Seed default site content
$defaultContent = [
    // Home - Hero
    ['home', 'hero_heading', 'Service Above Self — Since 2020'],
    ['home', 'hero_description', 'Rotary Club of Virar is a vibrant community of leaders united by a shared commitment to service, fellowship, and positive change in the Virar region.'],
    ['home', 'hero_primary_btn_text', 'View Our Activities'],
    ['home', 'hero_primary_btn_link', 'activities.php'],
    ['home', 'hero_secondary_btn_text', 'Join the Movement'],
    ['home', 'hero_secondary_btn_link', '#contact-cta'],
    // Home - Who We Are
    ['home', 'about_description', 'Rotary Club of Virar has been at the heart of community service, bringing together dedicated individuals who strive to make a meaningful impact through fellowship and action.'],
    ['home', 'about_badge', 'About Us'],
    ['home', 'about_heading', 'Who We Are'],
    ['home', 'about_quote_text', 'Together, we make the world a better place.'],
    ['home', 'about_btn_text', 'Learn More'],
    ['home', 'about_btn_link', 'aboutus.php'],
    // Home - Featured Activities
    ['home', 'activities_badge', 'Featured'],
    ['home', 'activities_heading', 'Featured Activities'],
    ['home', 'activities_description', 'Discover our latest events, projects, and community drives making an impact.'],
    // Home - CTA
    ['home', 'cta_badge', 'Get Involved'],
    ['home', 'cta_heading', 'Join Hands. Spread Smiles. Make a Difference.'],
    ['home', 'cta_description', 'Be part of something bigger. Whether you volunteer, partner, or donate — every action creates lasting change.'],
    ['home', 'cta_primary_btn_text', 'Contact Us'],
    ['home', 'cta_primary_btn_link', 'contact.php'],
    ['home', 'cta_secondary_btn_text', 'Admin Login'],
    ['home', 'cta_secondary_btn_link', '#login-modal'],
    // Home - Areas of Rotary titles
    ['home', 'area_title_1', 'Livelihood'],
    ['home', 'area_title_2', 'Education'],
    ['home', 'area_title_3', 'Peace & Harmony'],
    ['home', 'area_title_4', 'Community'],
    ['home', 'area_title_5', 'Water & Sanitation'],
    ['home', 'area_title_6', 'Environment'],
    ['home', 'area_title_7', 'Health'],
    // Home - Areas of Rotary descriptions
    ['home', 'area_desc_1', 'Skills & economic empowerment'],
    ['home', 'area_desc_2', 'School kits & literacy programs'],
    ['home', 'area_desc_3', 'Promoting peace and understanding'],
    ['home', 'area_desc_4', 'Service above self in action'],
    ['home', 'area_desc_5', 'Clean water for communities'],
    ['home', 'area_desc_6', 'Tree plantation & green drives'],
    ['home', 'area_desc_7', 'Medical camps & wellness drives'],
    // Home - Areas of Rotary icons
    ['home', 'area_icon_1', 'fas fa-seedling'],
    ['home', 'area_icon_2', 'fas fa-graduation-cap'],
    ['home', 'area_icon_3', 'fas fa-leaf'],
    ['home', 'area_icon_4', 'fas fa-hand-holding-heart'],
    ['home', 'area_icon_5', 'fas fa-tint'],
    ['home', 'area_icon_6', 'fas fa-leaf'],
    ['home', 'area_icon_7', 'fas fa-heartbeat'],
    // About - Hero
    ['about', 'hero_heading', 'About Rotary Club of <span class="highlight">Virar</span>'],
    ['about', 'hero_description', 'Dedicated to Service Above Self, creating lasting impact through community service, education, healthcare, environmental initiatives, and humanitarian projects.'],
    // About - Our Club, Our Mission
    ['about', 'about_description', 'Rotary Club of Virar has been at the heart of community service, bringing together dedicated individuals who strive to make a meaningful impact through fellowship and action.'],
    ['about', 'mission_badge', 'Who We Are'],
    ['about', 'mission_heading', 'Our Club, Our Mission'],
    // About - Mission Value Cards
    ['about', 'value_icon_1', 'fas fa-handshake'],
    ['about', 'value_title_1', 'Fellowship'],
    ['about', 'value_desc_1', 'Building meaningful connections among members through camaraderie, mutual respect, and shared purpose to create a strong, united community of service leaders.'],
    ['about', 'value_icon_2', 'fas fa-shield-alt'],
    ['about', 'value_title_2', 'Integrity'],
    ['about', 'value_desc_2', 'Upholding the highest ethical standards in all our actions, ensuring transparency, honesty, and accountability in every service project we undertake.'],
    ['about', 'value_icon_3', 'fas fa-globe-asia'],
    ['about', 'value_title_3', 'Service'],
    ['about', 'value_desc_3', 'Dedicating ourselves to humanitarian service that improves lives, strengthens communities, and advances global understanding and peace.'],
    // About - Our Impact
    ['about', 'impact_badge', 'Our Impact'],
    ['about', 'impact_heading', 'Making a Difference Together'],
    ['about', 'impact_icon_1', 'fa-handshake'],
    ['about', 'impact_number_1', ''],
    ['about', 'impact_title_1', 'Community Projects'],
    ['about', 'impact_desc_1', 'Driving meaningful change through service projects that strengthen communities and improve lives.'],
    ['about', 'impact_icon_2', 'fa-heartbeat'],
    ['about', 'impact_number_2', '500+'],
    ['about', 'impact_title_2', 'Health Beneficiaries'],
    ['about', 'impact_desc_2', 'Providing accessible healthcare services and wellness programs to underserved communities.'],
    ['about', 'impact_icon_3', 'fa-tree'],
    ['about', 'impact_number_3', '500+'],
    ['about', 'impact_title_3', 'Trees Planted'],
    ['about', 'impact_desc_3', 'Contributing to a greener planet through tree plantation drives and environmental awareness.'],
    // About - Areas of Impact
    ['about', 'focus_icon_1', 'fa-graduation-cap'],
    ['about', 'focus_title_1', 'Education'],
    ['about', 'focus_desc_1', 'Empowering students and youth through scholarships, digital literacy programs, and educational infrastructure support to build a brighter future.'],
    ['about', 'focus_icon_2', 'fa-heartbeat'],
    ['about', 'focus_title_2', 'Healthcare'],
    ['about', 'focus_desc_2', 'Organizing health check-up camps, blood donation drives, and wellness awareness programs for communities in need.'],
    ['about', 'focus_icon_3', 'fa-tree'],
    ['about', 'focus_title_3', 'Environment'],
    ['about', 'focus_desc_3', 'Promoting tree plantation drives, waste management awareness, and sustainability initiatives for a greener planet.'],
    ['about', 'focus_icon_4', 'fa-fist-raised'],
    ['about', 'focus_title_4', 'Women Empowerment'],
    ['about', 'focus_desc_4', 'Conducting skill development workshops, self-defense training, and awareness programs to empower women.'],
    ['about', 'focus_icon_5', 'fa-users'],
    ['about', 'focus_title_5', 'Youth Development'],
    ['about', 'focus_desc_5', 'Mentoring young leaders through leadership camps, career guidance sessions, and Rotary youth exchange programs.'],
    ['about', 'focus_icon_6', 'fa-home'],
    ['about', 'focus_title_6', 'Community Welfare'],
    ['about', 'focus_desc_6', 'Supporting local communities with food drives, disaster relief, sanitation projects, and infrastructure improvements.'],
    ['about', 'focus_icon_7', 'fa-dove'],
    ['about', 'focus_title_7', 'Peace and Harmony'],
    ['about', 'focus_desc_7', 'Promoting understanding, goodwill, and harmony through service, collaboration, and community engagement.'],
    // About - Founder Quote
    ['about', 'quote_text', 'Success is not measured by wealth, but by the positive impact you have on others.'],
    ['about', 'quote_author', '~ Adv. Rtn. Paul Harris, Founder of Rotary'],
    // About - CTA
    ['about', 'cta_heading', 'Ready to Be Part of Something Bigger?'],
    ['about', 'cta_description', 'Join Rotary Club of Virar and help us create lasting change in our community.'],
    ['about', 'cta_btn1_text', 'Join Rotary Club of Virar'],
    ['about', 'cta_btn1_link', 'https://form.jotform.com/203628680518460'],
    ['about', 'cta_btn2_text', 'Contact Us'],
    ['about', 'cta_btn2_link', 'contact.php'],
    // Contact - Hero
    ['contact', 'contact_hero_badge', 'Rotary Club of Virar'],
    ['contact', 'contact_hero_heading', 'Let\'s <span class="highlight">Connect</span>'],
    ['contact', 'contact_hero_description', "We're here to listen, collaborate, and make a difference. Reach out to us anytime — your ideas and feedback matter."],
    // Contact - How to Reach Us
    ['contact', 'contact_section_badge', 'GET IN TOUCH'],
    ['contact', 'contact_section_heading', 'How to Reach Us'],
    // Contact - Cards
    ['contact', 'contact_card1_icon', 'fas fa-phone-alt'],
    ['contact', 'contact_card1_title', 'Call Us'],
    ['contact', 'contact_card1_value', '+91 77969 31555'],
    ['contact', 'contact_card2_icon', 'fas fa-envelope'],
    ['contact', 'contact_card2_title', 'Email Us'],
    ['contact', 'contact_card2_value', 'rotaryclubofvirar@gmail.com'],
    ['contact', 'contact_card3_icon', 'fas fa-map-marker-alt'],
    ['contact', 'contact_card3_title', 'Visit Us'],
    ['contact', 'contact_card3_value', 'Shop No 12, Bhakti Building, Bhau Nagar Road, Tirupati Nagar Phase 2, Virar West, 401303, India'],
    // Contact - Form
    ['contact', 'contact_form_badge', 'Send a Message'],
    ['contact', 'contact_form_heading', "We'd Love to Hear From You"],
    // Contact - Follow Us
    ['contact', 'contact_follow_badge', 'Follow Us'],
    ['contact', 'contact_follow_heading', 'Stay Connected'],
    ['contact', 'contact_facebook_url', 'https://www.facebook.com/share/1bZDkps7EX/?mibextid=wwXIfr'],
    ['contact', 'contact_instagram_url', 'https://www.instagram.com/rcvirar?igsh=aHl4dDFpbG9oZzlp'],
    // Contact - Other Inquiries
    ['contact', 'contact_inquiries_badge', 'Official'],
    ['contact', 'contact_inquiries_heading', 'Other Inquiries'],
    ['contact', 'contact_general_email', 'rotaryclubofvirar@gmail.com'],
    ['contact', 'contact_youth_email', 'rotaryvirar.youth@gmail.com'],
    // Contact - Location
    ['contact', 'contact_location_badge', 'Our Location'],
    ['contact', 'contact_location_heading', 'Find Us Here'],
    ['contact', 'contact_map_embed_url', ''],
    ['contact', 'contact_map_btn_text', 'View on Map'],
    ['contact', 'contact_map_btn_url', 'https://share.google/qDKzCrtvCoFXUH0z4'],
    ['contact', 'contact_address', 'Shop No 12, Bhakti Building, Bhau Nagar Road, Tirupati Nagar Phase 2, Virar West, 401303, India'],
    // Contact - Bottom Quote
    ['contact', 'contact_quote_text', '"Service Above Self"'],
    ['contact', 'contact_quote_description', 'The heart of Rotary beats through the dedication of its members — ordinary people doing extraordinary things for the greater good.'],
    // Contact - Legacy (backward compatible)
    ['contact', 'contact_description', "We're here to listen, collaborate, and make a difference. Reach out to us anytime — your ideas and feedback matter."],
    // Footer - Club Information
    ['footer', 'footer_club_name', 'Rotary Club of Virar'],
    ['footer', 'footer_description', 'Committed to service above self, we unite local leaders to create lasting change and foster goodwill in our community.'],
    // Footer - Quick Links
    ['footer', 'footer_link_1_text', 'Home'],
    ['footer', 'footer_link_1_url', 'index.php'],
    ['footer', 'footer_link_2_text', 'About Us'],
    ['footer', 'footer_link_2_url', 'aboutus.php'],
    ['footer', 'footer_link_3_text', 'Our Activities'],
    ['footer', 'footer_link_3_url', 'activities.php'],
    ['footer', 'footer_link_4_text', 'Our Team'],
    ['footer', 'footer_link_4_url', 'team.php'],
    ['footer', 'footer_link_5_text', 'Media Gallery'],
    ['footer', 'footer_link_5_url', 'mediaGallery.php'],
    ['footer', 'footer_link_6_text', 'Contact Us'],
    ['footer', 'footer_link_6_url', 'contact.php'],
    ['footer', 'footer_link_7_text', 'Donate'],
    ['footer', 'footer_link_7_url', 'donate.php'],
    // Footer - Contact Information
    ['footer', 'footer_phone', '+91 77969 31555'],
    ['footer', 'footer_email', 'rotaryclubofvirar@gmail.com'],
    ['footer', 'footer_address', 'Shop No 12, Bhakti Building, Bhau Nagar Road, Tirupati Nagar Phase 2, Virar West, 401303, India'],
    // Footer - Social Media
    ['footer', 'footer_facebook_url', 'https://www.facebook.com/share/1bZDkps7EX/?mibextid=wwXIfr'],
    ['footer', 'footer_instagram_url', 'https://www.instagram.com/rcvirar?igsh=aHl4dDFpbG9oZzlp'],
    ['footer', 'footer_youtube_url', ''],
    ['footer', 'footer_linkedin_url', ''],
    ['footer', 'footer_twitter_url', ''],
    // Footer - Copyright
    ['footer', 'footer_copyright_text', 'Rotary Club of Virar | Service Above Self'],
    // Footer - Developer Credit
    ['footer', 'footer_developer_credit', 'Designed & Developed by Rotary Club of Virar IT Team'],
    // Contact & Social Links (centralized source)
    ['contact_social', 'email', 'rotaryclubofvirar@gmail.com'],
    ['contact_social', 'phone', '+91 77969 31555'],
    ['contact_social', 'address', 'Shop No 12, Bhakti Building, Bhau Nagar Road, Tirupati Nagar Phase 2, Virar West, 401303, India'],
    ['contact_social', 'map_url', 'https://share.google/qDKzCrtvCoFXUH0z4'],
    ['contact_social', 'map_embed_url', ''],
    ['contact_social', 'facebook_url', 'https://www.facebook.com/share/1bZDkps7EX/?mibextid=wwXIfr'],
    ['contact_social', 'instagram_url', 'https://www.instagram.com/rcvirar?igsh=aHl4dDFpbG9oZzlp'],
    // Website Settings (page = 'settings')
    ['settings', 'website_logo', 'assets/uploads/Logo/rotary-icon.png'],
    ['settings', 'website_favicon', 'assets/uploads/Logo/rotary-icon.png'],
    ['settings', 'website_name', 'Rotary Club of Virar'],
    ['settings', 'website_short_name', 'RC Virar'],
    ['settings', 'browser_title', 'Rotary Club of Virar - Service Above Self'],
    ['settings', 'timezone', 'Asia/Kolkata'],
    ['settings', 'date_format', 'dd/mm/yyyy'],
    ['settings', 'copyright_year', '2026'],
    // Club Profile
    ['settings', 'club_name', 'Rotary Club of Virar'],
    ['settings', 'club_district', 'District 3131'],
    ['settings', 'club_number', ''],
    ['settings', 'club_charter_date', ''],
    ['settings', 'club_meeting_day', ''],
    ['settings', 'club_meeting_time', ''],
    ['settings', 'club_meeting_venue', ''],
    ['settings', 'club_address', 'Shop No 12, Bhakti Building, Bhau Nagar Road, Tirupati Nagar Phase 2, Virar West, 401303, India'],
    ['settings', 'club_email', 'rotaryclubofvirar@gmail.com'],
    ['settings', 'club_phone', '+91 77969 31555'],
    ['settings', 'club_website', ''],
    // System Defaults
    ['settings', 'default_upload_size', '10'],
    ['settings', 'image_quality', '85'],
    ['settings', 'default_pagination', '10'],
    ['settings', 'items_per_page', '12'],
];
foreach ($defaultContent as $item) {
    $stmt = $conn->prepare("INSERT IGNORE INTO site_content (page, section, content) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $item[0], $item[1], $item[2]);
    $stmt->execute();
    $stmt->close();
}
$messages[] = "Default site content seeded.";

// STEP 10: Create donation_status_history table
$sql = "CREATE TABLE IF NOT EXISTS donation_status_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    donation_id INT NOT NULL,
    previous_status VARCHAR(50) DEFAULT NULL,
    new_status VARCHAR(50) NOT NULL,
    admin_id INT DEFAULT NULL,
    admin_name VARCHAR(255) DEFAULT NULL,
    admin_role VARCHAR(50) DEFAULT NULL,
    remarks TEXT DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (donation_id) REFERENCES donations(donation_id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES admins(admin_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sql)) {
    $messages[] = "donation_status_history table ready.";
} else {
    $errors[] = "Failed to create donation_status_history: " . $conn->error;
}

// REPORT
echo "=== DB MIGRATION REPORT ===\n\n";
if ($messages) {
    echo "SUCCESS:\n";
    foreach ($messages as $m) echo "  * $m\n";
}
if ($errors) {
    echo "\nERRORS:\n";
    foreach ($errors as $e) echo "  ! $e\n";
}
echo "\nMigration complete.\n";
