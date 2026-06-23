<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';

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
if ($superPasswordRaw === '') {
    $superPasswordRaw = bin2hex(random_bytes(8));
    $messages[] = "No SUPER_ADMIN_PASSWORD set in .env. Generated temporary password: $superPasswordRaw";
}
$superPassword = password_hash($superPasswordRaw, PASSWORD_DEFAULT);

// Remove any super_admin accounts that are not the designated one
$conn->query("DELETE FROM admins WHERE role = 'super_admin' AND email != '$superEmail'");

// Check if the designated super admin exists
$r = $conn->query("SELECT admin_id FROM admins WHERE email = '$superEmail'");
if ($r && $r->num_rows > 0) {
    // Update existing Super Admin password and ensure role is correct
    $conn->query("UPDATE admins SET password = '$superPassword', role = 'super_admin', status = 'active' WHERE email = '$superEmail'");
    $messages[] = "Super Admin account updated (Email: $superEmail).";
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

// Ensure no duplicate super_admin accounts exist (keep only the first one if duplicates somehow exist)
$conn->query("DELETE FROM admins WHERE role = 'super_admin' AND admin_id NOT IN (SELECT admin_id FROM (SELECT MIN(admin_id) as admin_id FROM admins WHERE role = 'super_admin') AS t)");

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
    ['home', 'hero_heading', 'Service Above Self — Since 2020'],
    ['home', 'hero_description', 'Rotary Club of Virar is a vibrant community of leaders united by a shared commitment to service, fellowship, and positive change in the Virar region.'],
    ['about', 'about_description', 'Rotary Club of Virar has been at the heart of community service, bringing together dedicated individuals who strive to make a meaningful impact through fellowship and action.'],
    ['contact', 'contact_description', "We're here to listen, collaborate, and make a difference. Reach out to us anytime — your ideas and feedback matter."],
];
foreach ($defaultContent as $item) {
    $stmt = $conn->prepare("INSERT IGNORE INTO site_content (page, section, content) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $item[0], $item[1], $item[2]);
    $stmt->execute();
    $stmt->close();
}
$messages[] = "Default site content seeded.";

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
