<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: ../login.php"); exit; }
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/admin_functions.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/audit_log.php';
if (!isSuperAdmin()) { echo "<script>alert('Access denied. Super Admin only.'); window.location.href='dashboard.php';</script>"; exit; }

function imgPath($path) {
    if (!$path || str_starts_with($path, '../')) return $path ?: '';
    return '../' . $path;
}

$message = '';
$error = '';
$uploadDir = __DIR__ . '/../uploads/site_content/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Handle image upload
function handleImageUpload($file, $section) {
    global $uploadDir;
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) return null;
    $filename = $section . '_' . time() . '.' . $ext;
    $dest = $uploadDir . $filename;
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        return 'uploads/site_content/' . $filename;
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_content') {
    // Process text fields
    foreach ($_POST as $key => $value) {
        if (str_starts_with($key, 'content_')) {
            $parts = explode('_', $key, 3);
            if (count($parts) === 3) {
                $page = $parts[1];
                $section = $parts[2];
                $content = trim($value);
                $stmt = $conn->prepare("INSERT INTO site_content (page, section, content) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE content = VALUES(content), updated_at = NOW()");
                $stmt->bind_param("sss", $page, $section, $content);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
    // Process image uploads
    // Map form field names to (page, section)
    $imageFieldMap = [
        'hero_image' => ['home', 'hero_image'],
        'about_image' => ['home', 'about_image'],
        'area_image_1' => ['home', 'area_image_1'],
        'area_image_2' => ['home', 'area_image_2'],
        'area_image_3' => ['home', 'area_image_3'],
        'area_image_4' => ['home', 'area_image_4'],
        'area_image_5' => ['home', 'area_image_5'],
        'area_image_6' => ['home', 'area_image_6'],
        'area_image_7' => ['home', 'area_image_7'],
        'cta_bg_image' => ['home', 'cta_bg_image'],
        'about_banner_image' => ['about', 'banner_image'],
        'about_image_1' => ['about', 'about_image_1'],
        'about_image_2' => ['about', 'about_image_2'],
        'about_image_3' => ['about', 'about_image_3'],
        'about_hero_bg_image' => ['about', 'hero_bg_image'],
        'about_hero_logo' => ['about', 'hero_logo'],
        'about_mission_image' => ['about', 'mission_image'],
        'about_cta_bg_image' => ['about', 'cta_bg_image'],
        'contact_image' => ['contact', 'contact_image'],
        'contact_hero_bg_image' => ['contact', 'contact_hero_bg_image'],
        'footer_logo' => ['footer', 'footer_logo'],
    ];
    foreach ($imageFieldMap as $fieldName => $mapping) {
        if (isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
            $page = $mapping[0];
            $section = $mapping[1];
            $path = handleImageUpload($_FILES[$fieldName], $section);
            if ($path) {
                $stmt = $conn->prepare("INSERT INTO site_content (page, section, content) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE content = VALUES(content), updated_at = NOW()");
                $stmt->bind_param("sss", $page, $section, $path);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
    $message = 'Website content updated successfully.';
    logAudit($conn, 'Website Content', 'Home Updated', 'Website content (Home/About/Contact/Footer) updated.', 'CRITICAL', 'success');
}

$homeContent = getSiteContent($conn, 'home');
$aboutContent = getSiteContent($conn, 'about');
$contactContent = getSiteContent($conn, 'contact');
$footerContent = getSiteContent($conn, 'footer');

// Areas of Rotary default images
$areaDefaults = [
    '../assets/uploads/Logo/Livelihood.jpeg',
    '../assets/uploads/Logo/Education.jpeg',
    '../assets/uploads/Logo/Peace.jpeg',
    '../assets/uploads/Logo/Community.jpeg',
    '../assets/uploads/Logo/Water.jpeg',
    '../assets/uploads/Logo/Environment.jpeg',
    '../assets/uploads/Logo/Health Logo.jpeg',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website Content Management - Rotary Club Virar</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root { --rotary-blue: #0A2342; --rotary-yellow: #FFC000; --rotary-gold: #e6a800; }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; color: #1e293b; margin: 0; }
        .page-wrap { max-width: 960px; margin: 0 auto; padding: 32px 24px; }
        .card { background: white; border-radius: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; overflow: hidden; }
        .card-header { padding: 20px 24px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
        .card-body { padding: 20px 24px; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 9px; font-weight: 600; font-size: 13px; transition: all 0.15s; cursor: pointer; border: none; text-decoration: none; font-family: inherit; }
        .btn-primary { background: var(--rotary-blue); color: #fff; }
        .btn-primary:hover { background: #1a365d; }
        .btn-yellow { background: var(--rotary-yellow); color: var(--rotary-blue); }
        .btn-yellow:hover { background: var(--rotary-gold); }
        .btn-ghost { background: transparent; color: #64748b; }
        .btn-ghost:hover { background: #f1f5f9; }
        .btn-lg { padding: 12px 28px; font-size: 15px; }
        .btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 7px; }
        input, textarea, select { width: 100%; padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px; font-family: inherit; outline: none; transition: all 0.2s; }
        input:focus, textarea:focus, select:focus { border-color: var(--rotary-yellow); box-shadow: 0 0 0 4px rgba(255,192,0,0.1); }
        textarea { resize: vertical; min-height: 100px; }
        label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 4px; }
        .char-counter { font-size: 12px; color: #94a3b8; text-align: right; margin-top: 4px; }
        .img-preview { width: 160px; height: 100px; object-fit: cover; border-radius: 8px; border: 2px solid #e2e8f0; background: #f8fafc; }
        .img-preview-sm { width: 80px; height: 56px; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0; background: #f8fafc; }
        .field-group { display: grid; gap: 12px; }
        .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; align-items: end; }
        .img-upload-wrap { display: flex; align-items: center; gap: 16px; padding: 12px 16px; background: #f8fafc; border-radius: 10px; border: 1px dashed #e2e8f0; }
        .img-upload-wrap img { flex-shrink: 0; }
        .img-upload-wrap .file-input { flex: 1; }
        .img-upload-wrap .file-input input { font-size: 13px; padding: 8px; }
        .page-tab { display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; border-radius: 9px 9px 0 0; font-weight: 600; font-size: 13px; cursor: pointer; border: none; background: #e2e8f0; color: #64748b; transition: all 0.15s; }
        .page-tab.active { background: var(--rotary-yellow); color: var(--rotary-blue); }
        .page-tab:hover:not(.active) { background: #cbd5e1; }
        @media (max-width: 768px) { .page-wrap { padding: 16px; } .field-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="page-wrap">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
        <a href="dashboard.php" class="btn btn-ghost btn-sm"><i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Back</a>
        <h1 style="font-size:24px;font-weight:800;color:#0f172a;margin:0;">Website Content Management</h1>
    </div>

    <?php if ($message): ?><div style="background:#dcfce7;border:1px solid #bbf7d0;color:#166534;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:14px;font-weight:500;"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div style="background:#fee2e2;border:1px solid #fecaca;color:#991b1b;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:14px;font-weight:500;"><?= e($error) ?></div><?php endif; ?>

    <!-- Page Tabs -->
    <div style="display:flex;gap:4px;margin-bottom:0;overflow-x:auto;">
        <button class="page-tab active" onclick="switchTab('home')"><i data-lucide="home" style="width:16px;height:16px;"></i> Home Page</button>
        <button class="page-tab" onclick="switchTab('about')"><i data-lucide="info" style="width:16px;height:16px;"></i> About Page</button>
        <button class="page-tab" onclick="switchTab('contact')"><i data-lucide="phone" style="width:16px;height:16px;"></i> Contact Page</button>
        <button class="page-tab" onclick="switchTab('footer')"><i data-lucide="layout" style="width:16px;height:16px;"></i> Footer (Global)</button>
    </div>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="update_content">

        <!-- ============ HOME PAGE ============ -->
        <div id="tab-home" class="tab-content">
            <div class="card" style="border-radius:0 14px 14px 14px;">
                <div class="card-header">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <i data-lucide="home" style="width:18px;height:18px;color:var(--rotary-blue);"></i>
                        <h2 style="font-size:16px;font-weight:700;margin:0;">Home Page Content</h2>
                    </div>
                    <span style="font-size:12px;color:#94a3b8;">index.php</span>
                </div>
                <div class="card-body" style="display:grid;gap:24px;">

                    <!-- Hero Section -->
                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:var(--rotary-blue);margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #f1f5f9;">Hero Section</h3>
                        <div class="field-group">
                            <div>
                                <label>Hero Heading</label>
                                <input type="text" name="content_home_hero_heading" value="<?= e($homeContent['hero_heading'] ?? 'Service Above Self — Since 2020') ?>" maxlength="200">
                                <div class="char-counter">Max 200 characters</div>
                            </div>
                            <div>
                                <label>Hero Description</label>
                                <textarea name="content_home_hero_description" rows="3" maxlength="500"><?= e($homeContent['hero_description'] ?? '') ?></textarea>
                                <div class="char-counter">Max 500 characters</div>
                            </div>
                            <div>
                                <label>Hero Background Image</label>
                                <div class="img-upload-wrap">
                                    <img class="img-preview" src="<?= e(imgPath($homeContent['hero_image'] ?? '../assets/uploads/Logo/HeroSection3A.jpeg')) ?>" alt="Hero" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22160%22 height=%22100%22><rect fill=%22%23f1f5f9%22 width=%22160%22 height=%22100%22/><text x=%2250%%22 y=%2250%%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%2394a3b8%22 font-size=%2212%22>No Image</text></svg>'">
                                    <div class="file-input">
                                        <input type="file" name="hero_image" accept="image/jpeg,image/png,image/webp">
                                        <div style="font-size:11px;color:#94a3b8;margin-top:2px;">JPEG, PNG or WebP. Leave empty to keep current.</div>
                                    </div>
                                </div>
                            </div>
                            <div class="field-row">
                                <div>
                                    <label>Primary Button Text</label>
                                    <input type="text" name="content_home_hero_primary_btn_text" value="<?= e($homeContent['hero_primary_btn_text'] ?? 'View Our Activities') ?>" maxlength="50">
                                </div>
                                <div>
                                    <label>Primary Button Link</label>
                                    <input type="text" name="content_home_hero_primary_btn_link" value="<?= e($homeContent['hero_primary_btn_link'] ?? 'activities.php') ?>" maxlength="200">
                                </div>
                            </div>
                            <div class="field-row">
                                <div>
                                    <label>Secondary Button Text</label>
                                    <input type="text" name="content_home_hero_secondary_btn_text" value="<?= e($homeContent['hero_secondary_btn_text'] ?? 'Join the Movement') ?>" maxlength="50">
                                </div>
                                <div>
                                    <label>Secondary Button Link</label>
                                    <input type="text" name="content_home_hero_secondary_btn_link" value="<?= e($homeContent['hero_secondary_btn_link'] ?? '#contact-cta') ?>" maxlength="200">
                                </div>
                            </div>
                            <h4 style="font-size:13px;font-weight:600;color:var(--rotary-blue);margin:8px 0 4px;">Statistics (leave blank to use dynamic counts)</h4>
                            <div class="field-row">
                                <div>
                                    <label>Activities Count</label>
                                    <input type="text" name="content_home_hero_activities_count" value="<?= e($homeContent['hero_activities_count'] ?? '') ?>" maxlength="20" placeholder="Auto">
                                </div>
                                <div>
                                    <label>Active Members Count</label>
                                    <input type="text" name="content_home_hero_active_members_count" value="<?= e($homeContent['hero_active_members_count'] ?? '') ?>" maxlength="20" placeholder="Auto">
                                </div>
                                <div>
                                    <label>Years Serving Count</label>
                                    <input type="text" name="content_home_hero_years_serving_count" value="<?= e($homeContent['hero_years_serving_count'] ?? '') ?>" maxlength="20" placeholder="2026">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Who We Are Section -->
                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:var(--rotary-blue);margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #f1f5f9;">Who We Are Section</h3>
                        <div class="field-group">
                            <div class="field-row">
                                <div>
                                    <label>Section Badge</label>
                                    <input type="text" name="content_home_about_badge" value="<?= e($homeContent['about_badge'] ?? 'About Us') ?>" maxlength="50">
                                </div>
                                <div>
                                    <label>Heading</label>
                                    <input type="text" name="content_home_about_heading" value="<?= e($homeContent['about_heading'] ?? 'Who We Are') ?>" maxlength="100">
                                </div>
                            </div>
                            <div>
                                <label>Description</label>
                                <textarea name="content_home_about_description" rows="4" maxlength="1000"><?= e($homeContent['about_description'] ?? '') ?></textarea>
                                <div class="char-counter">Max 1000 characters</div>
                            </div>
                            <div>
                                <label>Quote Text</label>
                                <input type="text" name="content_home_about_quote_text" value="<?= e($homeContent['about_quote_text'] ?? 'Together, we make the world a better place.') ?>" maxlength="200">
                            </div>
                            <div>
                                <label>Main Image</label>
                                <div class="img-upload-wrap">
                                    <img class="img-preview" src="<?= e(imgPath($homeContent['about_image'] ?? '../assets/uploads/Logo/areas-of-focus.jpeg')) ?>" alt="About Preview" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22160%22 height=%22100%22><rect fill=%22%23f1f5f9%22 width=%22160%22 height=%22100%22/><text x=%2250%%22 y=%2250%%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%2394a3b8%22 font-size=%2212%22>No Image</text></svg>'">
                                    <div class="file-input">
                                        <input type="file" name="about_image" accept="image/jpeg,image/png,image/webp">
                                        <div style="font-size:11px;color:#94a3b8;margin-top:2px;">JPEG, PNG or WebP. Leave empty to keep current.</div>
                                    </div>
                                </div>
                            </div>
                            <div class="field-row">
                                <div>
                                    <label>Learn More Button Text</label>
                                    <input type="text" name="content_home_about_btn_text" value="<?= e($homeContent['about_btn_text'] ?? 'Learn More') ?>" maxlength="50">
                                </div>
                                <div>
                                    <label>Learn More Button Link</label>
                                    <input type="text" name="content_home_about_btn_link" value="<?= e($homeContent['about_btn_link'] ?? 'aboutus.php') ?>" maxlength="200">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Areas of Rotary -->
                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:var(--rotary-blue);margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #f1f5f9;">Areas of Rotary</h3>
                        <p style="font-size:12px;color:#94a3b8;margin:0 0 12px;">Edit content for each of the 7 Rotary areas of focus.</p>
                        <?php
                        $areaLabels = ['Livelihood', 'Education', 'Peace & Harmony', 'Community', 'Water & Sanitation', 'Environment', 'Health'];
                        $areaIcons = ['fas fa-seedling', 'fas fa-graduation-cap', 'fas fa-leaf', 'fas fa-hand-holding-heart', 'fas fa-tint', 'fas fa-leaf', 'fas fa-heartbeat'];
                        $areaDefaultDescs = ['Skills & economic empowerment', 'School kits & literacy programs', 'Promoting peace and understanding', 'Service above self in action', 'Clean water for communities', 'Tree plantation & green drives', 'Medical camps & wellness drives'];
                        for ($i = 1; $i <= 7; $i++):
                            $imgKey = 'area_image_' . $i;
                            $titleKey = 'area_title_' . $i;
                            $descKey = 'area_desc_' . $i;
                            $iconKey = 'area_icon_' . $i;
                            $currentImg = $homeContent[$imgKey] ?? $areaDefaults[$i-1];
                            $currentTitle = $homeContent[$titleKey] ?? $areaLabels[$i-1];
                            $currentDesc = $homeContent[$descKey] ?? $areaDefaultDescs[$i-1];
                            $currentIcon = $homeContent[$iconKey] ?? $areaIcons[$i-1];
                        ?>
                        <div style="border:1px solid #f1f5f9;border-radius:10px;padding:12px;margin-bottom:12px;">
                            <label style="font-size:13px;font-weight:700;color:var(--rotary-blue);margin-bottom:8px;display:block;">Area <?= $i ?>: <?= $areaLabels[$i-1] ?></label>
                            <div class="field-row" style="margin-bottom:8px;">
                                <div>
                                    <label>Title</label>
                                    <input type="text" name="content_home_<?= $titleKey ?>" value="<?= e($currentTitle) ?>" maxlength="50">
                                </div>
                                <div>
                                    <label>Icon Class</label>
                                    <input type="text" name="content_home_<?= $iconKey ?>" value="<?= e($currentIcon) ?>" maxlength="50" placeholder="fas fa-icon-name">
                                </div>
                            </div>
                            <div style="margin-bottom:8px;">
                                <label>Short Description</label>
                                <input type="text" name="content_home_<?= $descKey ?>" value="<?= e($currentDesc) ?>" maxlength="200">
                            </div>
                            <div>
                                <label>Image</label>
                                <div class="img-upload-wrap" style="flex-direction:column;align-items:stretch;gap:8px;">
                                    <img class="img-preview" style="width:100%;height:120px;" src="<?= e(imgPath($currentImg)) ?>" alt="<?= $areaLabels[$i-1] ?>" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22280%22 height=%22120%22><rect fill=%22%23f1f5f9%22 width=%22280%22 height=%22120%22/><text x=%2250%%22 y=%2250%%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%2394a3b8%22 font-size=%2212%22>No Image</text></svg>'">
                                    <div class="file-input">
                                        <input type="file" name="<?= $imgKey ?>" accept="image/jpeg,image/png,image/webp" style="font-size:12px;padding:6px;">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>

                    <!-- Featured Activities -->
                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:var(--rotary-blue);margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #f1f5f9;">Featured Activities Section</h3>
                        <div class="field-group">
                            <div class="field-row">
                                <div>
                                    <label>Section Badge</label>
                                    <input type="text" name="content_home_activities_badge" value="<?= e($homeContent['activities_badge'] ?? 'Featured') ?>" maxlength="50">
                                </div>
                                <div>
                                    <label>Heading</label>
                                    <input type="text" name="content_home_activities_heading" value="<?= e($homeContent['activities_heading'] ?? 'Featured Activities') ?>" maxlength="100">
                                </div>
                            </div>
                            <div>
                                <label>Description</label>
                                <textarea name="content_home_activities_description" rows="3" maxlength="500"><?= e($homeContent['activities_description'] ?? '') ?></textarea>
                                <div class="char-counter">Max 500 characters</div>
                            </div>
                        </div>
                    </div>

                    <!-- CTA Section -->
                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:var(--rotary-blue);margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #f1f5f9;">Call to Action Section</h3>
                        <div class="field-group">
                            <div class="field-row">
                                <div>
                                    <label>Badge</label>
                                    <input type="text" name="content_home_cta_badge" value="<?= e($homeContent['cta_badge'] ?? 'Get Involved') ?>" maxlength="50">
                                </div>
                                <div>
                                    <label>Background Image</label>
                                    <div class="img-upload-wrap">
                                        <img class="img-preview" src="<?= e(imgPath($homeContent['cta_bg_image'] ?? '')) ?>" alt="CTA Background" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22160%22 height=%22100%22><rect fill=%22%23f1f5f9%22 width=%22160%22 height=%22100%22/><text x=%2250%%22 y=%2250%%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%2394a3b8%22 font-size=%2212%22>No Image</text></svg>'">
                                        <div class="file-input">
                                            <input type="file" name="cta_bg_image" accept="image/jpeg,image/png,image/webp">
                                            <div style="font-size:11px;color:#94a3b8;margin-top:2px;">JPEG, PNG or WebP.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label>Main Heading</label>
                                <input type="text" name="content_home_cta_heading" value="<?= e($homeContent['cta_heading'] ?? 'Join Hands. Spread Smiles. Make a Difference.') ?>" maxlength="200">
                            </div>
                            <div>
                                <label>Description</label>
                                <textarea name="content_home_cta_description" rows="3" maxlength="500"><?= e($homeContent['cta_description'] ?? '') ?></textarea>
                                <div class="char-counter">Max 500 characters</div>
                            </div>
                            <div class="field-row">
                                <div>
                                    <label>Primary Button Text</label>
                                    <input type="text" name="content_home_cta_primary_btn_text" value="<?= e($homeContent['cta_primary_btn_text'] ?? 'Contact Us') ?>" maxlength="50">
                                </div>
                                <div>
                                    <label>Primary Button Link</label>
                                    <input type="text" name="content_home_cta_primary_btn_link" value="<?= e($homeContent['cta_primary_btn_link'] ?? 'contact.php') ?>" maxlength="200">
                                </div>
                            </div>
                            <div class="field-row">
                                <div>
                                    <label>Secondary Button Text</label>
                                    <input type="text" name="content_home_cta_secondary_btn_text" value="<?= e($homeContent['cta_secondary_btn_text'] ?? 'Admin Login') ?>" maxlength="50">
                                </div>
                                <div>
                                    <label>Secondary Button Link</label>
                                    <input type="text" name="content_home_cta_secondary_btn_link" value="<?= e($homeContent['cta_secondary_btn_link'] ?? '#login-modal') ?>" maxlength="200">
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- ============ ABOUT PAGE ============ -->
        <div id="tab-about" class="tab-content" style="display:none;">
            <div class="card" style="border-radius:0 14px 14px 14px;">
                <div class="card-header">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <i data-lucide="info" style="width:18px;height:18px;color:var(--rotary-blue);"></i>
                        <h2 style="font-size:16px;font-weight:700;margin:0;">About Page Content</h2>
                    </div>
                    <span style="font-size:12px;color:#94a3b8;">aboutus.php</span>
                </div>
                <div class="card-body" style="display:grid;gap:24px;">

                    <!-- Hero Section -->
                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:var(--rotary-blue);margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #f1f5f9;">Hero Section</h3>
                        <div class="field-group">
                            <div>
                                <label>Hero Heading <span style="font-weight:400;color:#94a3b8;">(use &lt;span class=&quot;highlight&quot;&gt; for highlighted word)</span></label>
                                <input type="text" name="content_about_hero_heading" value="<?= e($aboutContent['hero_heading'] ?? 'About Rotary Club of <span class="highlight">Virar</span>') ?>" maxlength="200">
                            </div>
                            <div>
                                <label>Hero Description</label>
                                <textarea name="content_about_hero_description" rows="3" maxlength="500"><?= e($aboutContent['hero_description'] ?? '') ?></textarea>
                                <div class="char-counter">Max 500 characters</div>
                            </div>
                            <div class="field-row">
                                <div>
                                    <label>Hero Background Image</label>
                                    <div class="img-upload-wrap">
                                        <img class="img-preview" src="<?= e(imgPath($aboutContent['hero_bg_image'] ?? '')) ?>" alt="Hero BG" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22160%22 height=%22100%22><rect fill=%22%23f1f5f9%22 width=%22160%22 height=%22100%22/><text x=%2250%%22 y=%2250%%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%2394a3b8%22 font-size=%2212%22>No Image</text></svg>'">
                                        <div class="file-input">
                                            <input type="file" name="about_hero_bg_image" accept="image/jpeg,image/png,image/webp">
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label>Hero Logo</label>
                                    <div class="img-upload-wrap">
                                        <img class="img-preview" src="<?= e(imgPath($aboutContent['hero_logo'] ?? '../assets/uploads/Logo/rotary-icon.png')) ?>" alt="Hero Logo" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22160%22 height=%22100%22><rect fill=%22%23f1f5f9%22 width=%22160%22 height=%22100%22/><text x=%2250%%22 y=%2250%%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%2394a3b8%22 font-size=%2212%22>No Image</text></svg>'">
                                        <div class="file-input">
                                            <input type="file" name="about_hero_logo" accept="image/jpeg,image/png,image/webp">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Our Club, Our Mission -->
                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:var(--rotary-blue);margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #f1f5f9;">Our Club, Our Mission</h3>
                        <div class="field-group">
                            <div class="field-row">
                                <div>
                                    <label>Section Badge</label>
                                    <input type="text" name="content_about_mission_badge" value="<?= e($aboutContent['mission_badge'] ?? 'Who We Are') ?>" maxlength="50">
                                </div>
                                <div>
                                    <label>Heading</label>
                                    <input type="text" name="content_about_mission_heading" value="<?= e($aboutContent['mission_heading'] ?? 'Our Club, Our Mission') ?>" maxlength="100">
                                </div>
                            </div>
                            <div>
                                <label>Description</label>
                                <textarea name="content_about_about_description" rows="6" maxlength="5000"><?= e($aboutContent['about_description'] ?? '') ?></textarea>
                                <div class="char-counter">Max 5000 characters</div>
                            </div>
                            <div>
                                <label>Right-side Image / Logo</label>
                                <div class="img-upload-wrap">
                                    <img class="img-preview" src="<?= e(imgPath($aboutContent['mission_image'] ?? '../assets/uploads/Logo/rotary-icon.png')) ?>" alt="Mission Image" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22160%22 height=%22100%22><rect fill=%22%23f1f5f9%22 width=%22160%22 height=%22100%22/><text x=%2250%%22 y=%2250%%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%2394a3b8%22 font-size=%2212%22>No Image</text></svg>'">
                                    <div class="file-input">
                                        <input type="file" name="about_mission_image" accept="image/jpeg,image/png,image/webp">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Mission Value Cards -->
                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:var(--rotary-blue);margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #f1f5f9;">Mission Value Cards</h3>
                        <?php
                        $valueLabels = ['Fellowship', 'Integrity', 'Service'];
                        $valueIcons = ['fas fa-handshake', 'fas fa-shield-alt', 'fas fa-globe-asia'];
                        $valueDescs = [
                            'Building meaningful connections among members through camaraderie, mutual respect, and shared purpose to create a strong, united community of service leaders.',
                            'Upholding the highest ethical standards in all our actions, ensuring transparency, honesty, and accountability in every service project we undertake.',
                            'Dedicating ourselves to humanitarian service that improves lives, strengthens communities, and advances global understanding and peace.',
                        ];
                        for ($vi = 1; $vi <= 3; $vi++):
                            $vIcon = $aboutContent['value_icon_' . $vi] ?? $valueIcons[$vi-1];
                            $vTitle = $aboutContent['value_title_' . $vi] ?? $valueLabels[$vi-1];
                            $vDesc = $aboutContent['value_desc_' . $vi] ?? $valueDescs[$vi-1];
                        ?>
                        <div style="border:1px solid #f1f5f9;border-radius:10px;padding:12px;margin-bottom:12px;">
                            <label style="font-size:13px;font-weight:700;color:var(--rotary-blue);margin-bottom:8px;display:block;">Card <?= $vi ?>: <?= $valueLabels[$vi-1] ?></label>
                            <div class="field-row" style="margin-bottom:8px;">
                                <div>
                                    <label>Icon Class</label>
                                    <input type="text" name="content_about_value_icon_<?= $vi ?>" value="<?= e($vIcon) ?>" maxlength="50" placeholder="fas fa-icon-name">
                                </div>
                                <div>
                                    <label>Title</label>
                                    <input type="text" name="content_about_value_title_<?= $vi ?>" value="<?= e($vTitle) ?>" maxlength="50">
                                </div>
                            </div>
                            <div>
                                <label>Description</label>
                                <textarea name="content_about_value_desc_<?= $vi ?>" rows="3" maxlength="500"><?= e($vDesc) ?></textarea>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>

                    <!-- Our Impact -->
                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:var(--rotary-blue);margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #f1f5f9;">Our Impact Section</h3>
                        <div class="field-group">
                            <div class="field-row">
                                <div>
                                    <label>Section Badge</label>
                                    <input type="text" name="content_about_impact_badge" value="<?= e($aboutContent['impact_badge'] ?? 'Our Impact') ?>" maxlength="50">
                                </div>
                                <div>
                                    <label>Heading</label>
                                    <input type="text" name="content_about_impact_heading" value="<?= e($aboutContent['impact_heading'] ?? 'Making a Difference Together') ?>" maxlength="100">
                                </div>
                            </div>
                            <?php
                            $impactLabels = ['Community Projects', 'Health Beneficiaries', 'Trees Planted'];
                            $impactIcons = ['fa-handshake', 'fa-heartbeat', 'fa-tree'];
                            $impactNumbers = ['Auto', '500+', '500+'];
                            $impactDescs = [
                                'Driving meaningful change through service projects that strengthen communities and improve lives.',
                                'Providing accessible healthcare services and wellness programs to underserved communities.',
                                'Contributing to a greener planet through tree plantation drives and environmental awareness.',
                            ];
                            for ($ii = 1; $ii <= 3; $ii++):
                                $iIcon = $aboutContent['impact_icon_' . $ii] ?? $impactIcons[$ii-1];
                                $iNumber = $aboutContent['impact_number_' . $ii] ?? $impactNumbers[$ii-1];
                                $iTitle = $aboutContent['impact_title_' . $ii] ?? $impactLabels[$ii-1];
                                $iDesc = $aboutContent['impact_desc_' . $ii] ?? $impactDescs[$ii-1];
                            ?>
                            <div style="border:1px solid #f1f5f9;border-radius:10px;padding:12px;margin-bottom:8px;">
                                <label style="font-size:13px;font-weight:700;color:var(--rotary-blue);margin-bottom:8px;display:block;">Impact Card <?= $ii ?>: <?= $impactLabels[$ii-1] ?></label>
                                <div class="field-row" style="margin-bottom:8px;">
                                    <div>
                                        <label>Icon Class</label>
                                        <input type="text" name="content_about_impact_icon_<?= $ii ?>" value="<?= e($iIcon) ?>" maxlength="50" placeholder="fa-icon-name">
                                    </div>
                                    <div>
                                        <label>Number</label>
                                        <input type="text" name="content_about_impact_number_<?= $ii ?>" value="<?= e($iNumber) ?>" maxlength="20" placeholder="Auto">
                                    </div>
                                </div>
                                <div class="field-row" style="margin-bottom:8px;">
                                    <div>
                                        <label>Title</label>
                                        <input type="text" name="content_about_impact_title_<?= $ii ?>" value="<?= e($iTitle) ?>" maxlength="50">
                                    </div>
                                    <div>
                                        <label>Description</label>
                                        <input type="text" name="content_about_impact_desc_<?= $ii ?>" value="<?= e($iDesc) ?>" maxlength="200">
                                    </div>
                                </div>
                            </div>
                            <?php endfor; ?>
                            <div>
                                <label>Bottom Impact Paragraph <span style="font-weight:400;color:#94a3b8;">(supports HTML, use &#123;&#123;count&#125;&#125; for project count)</span></label>
                                <textarea name="content_about_impact_bottom_text" rows="3" maxlength="500"><?= e($aboutContent['impact_bottom_text'] ?? '') ?></textarea>
                                <div class="char-counter">Max 500 characters</div>
                            </div>
                        </div>
                    </div>

                    <!-- Areas of Impact -->
                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:var(--rotary-blue);margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #f1f5f9;">Areas of Impact</h3>
                        <p style="font-size:12px;color:#94a3b8;margin:0 0 12px;">Edit content for each of the 7 focus areas.</p>
                        <?php
                        $focusLabels = ['Education', 'Healthcare', 'Environment', 'Women Empowerment', 'Youth Development', 'Community Welfare', 'Peace and Harmony'];
                        $focusIcons = ['fa-graduation-cap', 'fa-heartbeat', 'fa-tree', 'fa-fist-raised', 'fa-users', 'fa-home', 'fa-dove'];
                        $focusDescs = [
                            'Empowering students and youth through scholarships, digital literacy programs, and educational infrastructure support to build a brighter future.',
                            'Organizing health check-up camps, blood donation drives, and wellness awareness programs for communities in need.',
                            'Promoting tree plantation drives, waste management awareness, and sustainability initiatives for a greener planet.',
                            'Conducting skill development workshops, self-defense training, and awareness programs to empower women.',
                            'Mentoring young leaders through leadership camps, career guidance sessions, and Rotary youth exchange programs.',
                            'Supporting local communities with food drives, disaster relief, sanitation projects, and infrastructure improvements.',
                            'Promoting understanding, goodwill, and harmony through service, collaboration, and community engagement.',
                        ];
                        for ($fi = 1; $fi <= 7; $fi++):
                            $fIcon = $aboutContent['focus_icon_' . $fi] ?? $focusIcons[$fi-1];
                            $fTitle = $aboutContent['focus_title_' . $fi] ?? $focusLabels[$fi-1];
                            $fDesc = $aboutContent['focus_desc_' . $fi] ?? $focusDescs[$fi-1];
                        ?>
                        <div style="border:1px solid #f1f5f9;border-radius:10px;padding:12px;margin-bottom:8px;">
                            <label style="font-size:13px;font-weight:700;color:var(--rotary-blue);margin-bottom:8px;display:block;">Focus <?= $fi ?>: <?= $focusLabels[$fi-1] ?></label>
                            <div class="field-row" style="margin-bottom:8px;">
                                <div>
                                    <label>Icon Class</label>
                                    <input type="text" name="content_about_focus_icon_<?= $fi ?>" value="<?= e($fIcon) ?>" maxlength="50" placeholder="fa-icon-name">
                                </div>
                                <div>
                                    <label>Title</label>
                                    <input type="text" name="content_about_focus_title_<?= $fi ?>" value="<?= e($fTitle) ?>" maxlength="50">
                                </div>
                            </div>
                            <div>
                                <label>Description</label>
                                <textarea name="content_about_focus_desc_<?= $fi ?>" rows="2" maxlength="500"><?= e($fDesc) ?></textarea>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>

                    <!-- Founder Quote -->
                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:var(--rotary-blue);margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #f1f5f9;">Founder Quote</h3>
                        <div class="field-group">
                            <div>
                                <label>Quote Text</label>
                                <textarea name="content_about_quote_text" rows="3" maxlength="500"><?= e($aboutContent['quote_text'] ?? '') ?></textarea>
                                <div class="char-counter">Max 500 characters</div>
                            </div>
                            <div>
                                <label>Author Name</label>
                                <input type="text" name="content_about_quote_author" value="<?= e($aboutContent['quote_author'] ?? '~ Adv. Rtn. Paul Harris, Founder of Rotary') ?>" maxlength="200">
                            </div>
                        </div>
                    </div>

                    <!-- Call to Action -->
                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:var(--rotary-blue);margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #f1f5f9;">Call to Action</h3>
                        <div class="field-group">
                            <div>
                                <label>Background Image</label>
                                <div class="img-upload-wrap">
                                    <img class="img-preview" src="<?= e(imgPath($aboutContent['cta_bg_image'] ?? '')) ?>" alt="CTA BG" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22160%22 height=%22100%22><rect fill=%22%23f1f5f9%22 width=%22160%22 height=%22100%22/><text x=%2250%%22 y=%2250%%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%2394a3b8%22 font-size=%2212%22>No Image</text></svg>'">
                                    <div class="file-input">
                                        <input type="file" name="about_cta_bg_image" accept="image/jpeg,image/png,image/webp">
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label>Heading</label>
                                <input type="text" name="content_about_cta_heading" value="<?= e($aboutContent['cta_heading'] ?? 'Ready to Be Part of Something Bigger?') ?>" maxlength="200">
                            </div>
                            <div>
                                <label>Description</label>
                                <textarea name="content_about_cta_description" rows="3" maxlength="500"><?= e($aboutContent['cta_description'] ?? '') ?></textarea>
                                <div class="char-counter">Max 500 characters</div>
                            </div>
                            <div class="field-row">
                                <div>
                                    <label>Button 1 Text</label>
                                    <input type="text" name="content_about_cta_btn1_text" value="<?= e($aboutContent['cta_btn1_text'] ?? 'Join Rotary Club of Virar') ?>" maxlength="100">
                                </div>
                                <div>
                                    <label>Button 1 Link</label>
                                    <input type="text" name="content_about_cta_btn1_link" value="<?= e($aboutContent['cta_btn1_link'] ?? 'https://form.jotform.com/203628680518460') ?>" maxlength="500">
                                </div>
                            </div>
                            <div class="field-row">
                                <div>
                                    <label>Button 2 Text</label>
                                    <input type="text" name="content_about_cta_btn2_text" value="<?= e($aboutContent['cta_btn2_text'] ?? 'Contact Us') ?>" maxlength="100">
                                </div>
                                <div>
                                    <label>Button 2 Link</label>
                                    <input type="text" name="content_about_cta_btn2_link" value="<?= e($aboutContent['cta_btn2_link'] ?? 'contact.php') ?>" maxlength="500">
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- ============ CONTACT PAGE ============ -->
        <div id="tab-contact" class="tab-content" style="display:none;">
            <div class="card" style="border-radius:0 14px 14px 14px;">
                <div class="card-header">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <i data-lucide="phone" style="width:18px;height:18px;color:var(--rotary-blue);"></i>
                        <h2 style="font-size:16px;font-weight:700;margin:0;">Contact Page Content</h2>
                    </div>
                    <span style="font-size:12px;color:#94a3b8;">contact.php</span>
                </div>
                <div class="card-body" style="display:grid;gap:24px;">

                    <!-- Section 1: Hero -->
                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:var(--rotary-blue);margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #f1f5f9;">Contact Hero</h3>
                        <div class="field-group">
                            <div>
                                <label>Hero Background Image</label>
                                <div class="img-upload-wrap">
                                    <img class="img-preview" src="<?= e(imgPath($contactContent['contact_hero_bg_image'] ?? '')) ?>" alt="Hero BG" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22160%22 height=%22100%22><rect fill=%22%23f1f5f9%22 width=%22160%22 height=%22100%22/><text x=%2250%%22 y=%2250%%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%2394a3b8%22 font-size=%2212%22>No Image</text></svg>'">
                                    <div class="file-input">
                                        <input type="file" name="contact_hero_bg_image" accept="image/jpeg,image/png,image/webp">
                                        <div style="font-size:11px;color:#94a3b8;margin-top:2px;">JPEG, PNG or WebP. Leave empty to use gradient background.</div>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label>Hero Badge</label>
                                <input type="text" name="content_contact_contact_hero_badge" value="<?= e($contactContent['contact_hero_badge'] ?? 'Rotary Club of Virar') ?>" maxlength="100">
                            </div>
                            <div>
                                <label>Main Heading <span style="font-weight:400;color:#94a3b8;">(use &lt;span class=&quot;highlight&quot;&gt; for highlighted word)</span></label>
                                <input type="text" name="content_contact_contact_hero_heading" value="<?= e($contactContent['contact_hero_heading'] ?? "Let's <span class=\"highlight\">Connect</span>") ?>" maxlength="200">
                            </div>
                            <div>
                                <label>Hero Description</label>
                                <textarea name="content_contact_contact_hero_description" rows="3" maxlength="500"><?= e($contactContent['contact_hero_description'] ?? "We're here to listen, collaborate, and make a difference. Reach out to us anytime — your ideas and feedback matter.") ?></textarea>
                                <div class="char-counter">Max 500 characters</div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: How to Reach Us -->
                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:var(--rotary-blue);margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #f1f5f9;">How to Reach Us</h3>
                        <div class="field-group">
                            <div class="field-row">
                                <div>
                                    <label>Section Badge</label>
                                    <input type="text" name="content_contact_contact_section_badge" value="<?= e($contactContent['contact_section_badge'] ?? 'GET IN TOUCH') ?>" maxlength="50">
                                </div>
                                <div>
                                    <label>Heading</label>
                                    <input type="text" name="content_contact_contact_section_heading" value="<?= e($contactContent['contact_section_heading'] ?? 'How to Reach Us') ?>" maxlength="100">
                                </div>
                            </div>
                            <?php
                            $cardLabels = ['Call Us', 'Email Us', 'Visit Us'];
                            $cardIcons = ['fas fa-phone-alt', 'fas fa-envelope', 'fas fa-map-marker-alt'];
                            for ($ci = 1; $ci <= 3; $ci++):
                                $cIcon = $contactContent['contact_card' . $ci . '_icon'] ?? $cardIcons[$ci-1];
                                $cTitle = $contactContent['contact_card' . $ci . '_title'] ?? $cardLabels[$ci-1];
                                $cValue = $contactContent['contact_card' . $ci . '_value'] ?? '';
                            ?>
                            <div style="border:1px solid #f1f5f9;border-radius:10px;padding:12px;margin-bottom:8px;">
                                <label style="font-size:13px;font-weight:700;color:var(--rotary-blue);margin-bottom:8px;display:block;">Card <?= $ci ?>: <?= $cardLabels[$ci-1] ?></label>
                                <div class="field-row" style="margin-bottom:8px;">
                                    <div>
                                        <label>Icon Class</label>
                                        <input type="text" name="content_contact_contact_card<?= $ci ?>_icon" value="<?= e($cIcon) ?>" maxlength="50" placeholder="fas fa-icon-name">
                                    </div>
                                    <div>
                                        <label>Title</label>
                                        <input type="text" name="content_contact_contact_card<?= $ci ?>_title" value="<?= e($cTitle) ?>" maxlength="50">
                                    </div>
                                </div>
                                <div>
                                    <label><?= $ci === 1 ? 'Phone Number' : ($ci === 2 ? 'Email Address' : 'Complete Address') ?></label>
                                    <input type="text" name="content_contact_contact_card<?= $ci ?>_value" value="<?= e($cValue) ?>" maxlength="500" placeholder="<?= $ci === 1 ? '+91 77969 31555' : ($ci === 2 ? 'rotaryclubofvirar@gmail.com' : 'Shop No 12, Bhakti Building...') ?>">
                                </div>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- Section 3: Contact Form -->
                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:var(--rotary-blue);margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #f1f5f9;">Contact Form</h3>
                        <p style="font-size:12px;color:#94a3b8;margin:0 0 12px;">Only the heading text is editable. Form fields, validation, and submission logic remain unchanged.</p>
                        <div class="field-row">
                            <div>
                                <label>Badge</label>
                                <input type="text" name="content_contact_contact_form_badge" value="<?= e($contactContent['contact_form_badge'] ?? 'Send a Message') ?>" maxlength="50">
                            </div>
                            <div>
                                <label>Heading</label>
                                <input type="text" name="content_contact_contact_form_heading" value="<?= e($contactContent['contact_form_heading'] ?? "We'd Love to Hear From You") ?>" maxlength="100">
                            </div>
                        </div>
                    </div>

                    <!-- Section 4: Right Sidebar -->
                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:var(--rotary-blue);margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #f1f5f9;">Right Sidebar</h3>
                        <div class="field-group">
                            <div style="border:1px solid #f1f5f9;border-radius:10px;padding:12px;margin-bottom:8px;">
                                <label style="font-size:13px;font-weight:700;color:var(--rotary-blue);margin-bottom:8px;display:block;">Follow Us Card</label>
                                <div class="field-row" style="margin-bottom:8px;">
                                    <div>
                                        <label>Badge</label>
                                        <input type="text" name="content_contact_contact_follow_badge" value="<?= e($contactContent['contact_follow_badge'] ?? 'Follow Us') ?>" maxlength="50">
                                    </div>
                                    <div>
                                        <label>Heading</label>
                                        <input type="text" name="content_contact_contact_follow_heading" value="<?= e($contactContent['contact_follow_heading'] ?? 'Stay Connected') ?>" maxlength="100">
                                    </div>
                                </div>
                                <div class="field-row" style="margin-bottom:8px;">
                                    <div>
                                        <label>Facebook URL</label>
                                        <input type="url" name="content_contact_contact_facebook_url" value="<?= e($contactContent['contact_facebook_url'] ?? '') ?>" maxlength="500" placeholder="https://facebook.com/...">
                                    </div>
                                    <div>
                                        <label>Instagram URL</label>
                                        <input type="url" name="content_contact_contact_instagram_url" value="<?= e($contactContent['contact_instagram_url'] ?? '') ?>" maxlength="500" placeholder="https://instagram.com/...">
                                    </div>
                                </div>
                                <p style="font-size:11px;color:#94a3b8;margin:0;">Leave blank to use global footer social links. Structure is ready for more platforms later.</p>
                            </div>
                            <div style="border:1px solid #f1f5f9;border-radius:10px;padding:12px;">
                                <label style="font-size:13px;font-weight:700;color:var(--rotary-blue);margin-bottom:8px;display:block;">Other Inquiries</label>
                                <div class="field-row" style="margin-bottom:8px;">
                                    <div>
                                        <label>Badge</label>
                                        <input type="text" name="content_contact_contact_inquiries_badge" value="<?= e($contactContent['contact_inquiries_badge'] ?? 'Official') ?>" maxlength="50">
                                    </div>
                                    <div>
                                        <label>Heading</label>
                                        <input type="text" name="content_contact_contact_inquiries_heading" value="<?= e($contactContent['contact_inquiries_heading'] ?? 'Other Inquiries') ?>" maxlength="100">
                                    </div>
                                </div>
                                <div class="field-row">
                                    <div>
                                        <label>General Email</label>
                                        <input type="email" name="content_contact_contact_general_email" value="<?= e($contactContent['contact_general_email'] ?? '') ?>" maxlength="200" placeholder="rotaryclubofvirar@gmail.com">
                                    </div>
                                    <div>
                                        <label>Youth Email</label>
                                        <input type="email" name="content_contact_contact_youth_email" value="<?= e($contactContent['contact_youth_email'] ?? '') ?>" maxlength="200" placeholder="rotaryvirar.youth@gmail.com">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 5: Location -->
                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:var(--rotary-blue);margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #f1f5f9;">Location</h3>
                        <div class="field-group">
                            <div class="field-row">
                                <div>
                                    <label>Badge</label>
                                    <input type="text" name="content_contact_contact_location_badge" value="<?= e($contactContent['contact_location_badge'] ?? 'Our Location') ?>" maxlength="50">
                                </div>
                                <div>
                                    <label>Heading</label>
                                    <input type="text" name="content_contact_contact_location_heading" value="<?= e($contactContent['contact_location_heading'] ?? 'Find Us Here') ?>" maxlength="100">
                                </div>
                            </div>
                            <div>
                                <label>Google Maps Embed URL</label>
                                <input type="url" name="content_contact_contact_map_embed_url" value="<?= e($contactContent['contact_map_embed_url'] ?? '') ?>" maxlength="1000" placeholder="https://www.google.com/maps?q=...&output=embed">
                                <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Leave empty to auto-generate from address.</div>
                            </div>
                            <div>
                                <label>Full Address</label>
                                <textarea name="content_contact_contact_address" rows="3" maxlength="500"><?= e($contactContent['contact_address'] ?? '') ?></textarea>
                                <div class="char-counter">Max 500 characters</div>
                            </div>
                            <div class="field-row">
                                <div>
                                    <label>Map Button Text</label>
                                    <input type="text" name="content_contact_contact_map_btn_text" value="<?= e($contactContent['contact_map_btn_text'] ?? 'View on Map') ?>" maxlength="100">
                                </div>
                                <div>
                                    <label>Map Button URL</label>
                                    <input type="url" name="content_contact_contact_map_btn_url" value="<?= e($contactContent['contact_map_btn_url'] ?? '') ?>" maxlength="500" placeholder="https://maps.google.com/...">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 6: Bottom Quote -->
                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:var(--rotary-blue);margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #f1f5f9;">Bottom Quote</h3>
                        <div class="field-group">
                            <div>
                                <label>Quote Text</label>
                                <input type="text" name="content_contact_contact_quote_text" value="<?= e($contactContent['contact_quote_text'] ?? '"Service Above Self"') ?>" maxlength="200">
                            </div>
                            <div>
                                <label>Quote Description</label>
                                <textarea name="content_contact_contact_quote_description" rows="3" maxlength="500"><?= e($contactContent['contact_quote_description'] ?? 'The heart of Rotary beats through the dedication of its members — ordinary people doing extraordinary things for the greater good.') ?></textarea>
                                <div class="char-counter">Max 500 characters</div>
                            </div>
                        </div>
                    </div>

                    <!-- Legacy Fields (kept for backward compatibility) -->
                    <div style="border:1px dashed #e2e8f0;border-radius:10px;padding:12px;background:#f8fafc;">
                        <p style="font-size:12px;font-weight:600;color:#94a3b8;margin:0 0 8px;">Legacy Fields (backward compatible)</p>
                        <div class="field-row">
                            <div>
                                <label>Contact Image</label>
                                <div class="img-upload-wrap">
                                    <img class="img-preview" src="<?= e(imgPath($contactContent['contact_image'] ?? '../assets/uploads/Logo/rotary-icon.png')) ?>" alt="Contact" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22160%22 height=%22100%22><rect fill=%22%23f1f5f9%22 width=%22160%22 height=%22100%22/><text x=%2250%%22 y=%2250%%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%2394a3b8%22 font-size=%2212%22>No Image</text></svg>'">
                                    <div class="file-input">
                                        <input type="file" name="contact_image" accept="image/jpeg,image/png,image/webp">
                                        <div style="font-size:11px;color:#94a3b8;margin-top:2px;">JPEG, PNG or WebP.</div>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label>Contact Description (legacy)</label>
                                <textarea name="content_contact_contact_description" rows="3" maxlength="2000"><?= e($contactContent['contact_description'] ?? '') ?></textarea>
                                <div class="char-counter">Max 2000 characters</div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- ============ FOOTER (GLOBAL) ============ -->
        <div id="tab-footer" class="tab-content" style="display:none;">
            <div class="card" style="border-radius:0 14px 14px 14px;">
                <div class="card-header">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <i data-lucide="layout" style="width:18px;height:18px;color:var(--rotary-blue);"></i>
                        <h2 style="font-size:16px;font-weight:700;margin:0;">Global Footer Settings</h2>
                    </div>
                    <span style="font-size:12px;color:#94a3b8;">shared by all public pages</span>
                </div>
                <div class="card-body" style="display:grid;gap:24px;">

                    <!-- Section 1: Club Information -->
                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:var(--rotary-blue);margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #f1f5f9;">Club Information</h3>
                        <div class="field-group">
                            <div>
                                <label>Footer Logo</label>
                                <div class="img-upload-wrap">
                                    <img class="img-preview" src="<?= e(imgPath($footerContent['footer_logo'] ?? '')) ?>" alt="Footer Logo" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22160%22 height=%22100%22><rect fill=%22%23f1f5f9%22 width=%22160%22 height=%22100%22/><text x=%2250%%22 y=%2250%%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%2394a3b8%22 font-size=%2212%22>No Image</text></svg>'">
                                    <div class="file-input">
                                        <input type="file" name="footer_logo" accept="image/jpeg,image/png,image/webp">
                                        <div style="font-size:11px;color:#94a3b8;margin-top:2px;">JPEG, PNG or WebP. Leave empty to keep default.</div>
                                    </div>
                                </div>
                            </div>
                            <div class="field-row">
                                <div>
                                    <label>Club Name</label>
                                    <input type="text" name="content_footer_footer_club_name" value="<?= e($footerContent['footer_club_name'] ?? '') ?>" maxlength="100" placeholder="Rotary Club of Virar">
                                </div>
                            </div>
                            <div>
                                <label>Footer Description</label>
                                <textarea name="content_footer_footer_description" rows="3" maxlength="500"><?= e($footerContent['footer_description'] ?? '') ?></textarea>
                                <div class="char-counter">Max 500 characters</div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Quick Links -->
                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:var(--rotary-blue);margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #f1f5f9;">Quick Links</h3>
                        <p style="font-size:12px;color:#94a3b8;margin:0 0 12px;">Edit link text and destination URL for each item. Leave both empty to hide a link.</p>
                        <?php
                        $defaultLinkLabels = ['Home', 'About Us', 'Our Activities', 'Our Team', 'Media Gallery', 'Contact Us', 'Donate'];
                        $defaultLinkUrls = ['index.php', 'aboutus.php', 'activities.php', 'team.php', 'mediaGallery.php', 'contact.php', 'donate.php'];
                        for ($li = 1; $li <= 7; $li++):
                            $lt = $footerContent['footer_link_' . $li . '_text'] ?? $defaultLinkLabels[$li-1];
                            $lu = $footerContent['footer_link_' . $li . '_url'] ?? $defaultLinkUrls[$li-1];
                        ?>
                        <div style="border:1px solid #f1f5f9;border-radius:10px;padding:12px;margin-bottom:6px;">
                            <label style="font-size:13px;font-weight:700;color:var(--rotary-blue);margin-bottom:8px;display:block;">Link <?= $li ?></label>
                            <div class="field-row">
                                <div>
                                    <label>Text</label>
                                    <input type="text" name="content_footer_footer_link_<?= $li ?>_text" value="<?= e($lt) ?>" maxlength="50" placeholder="<?= $defaultLinkLabels[$li-1] ?>">
                                </div>
                                <div>
                                    <label>URL</label>
                                    <input type="text" name="content_footer_footer_link_<?= $li ?>_url" value="<?= e($lu) ?>" maxlength="200" placeholder="<?= $defaultLinkUrls[$li-1] ?>">
                                </div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>

                    <!-- Section 3: Contact Information -->
                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:var(--rotary-blue);margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #f1f5f9;">Contact Information</h3>
                        <div class="field-row">
                            <div>
                                <label>Phone Number</label>
                                <input type="text" name="content_footer_footer_phone" value="<?= e($footerContent['footer_phone'] ?? '') ?>" maxlength="50" placeholder="+91 77969 31555">
                            </div>
                            <div>
                                <label>Email Address</label>
                                <input type="email" name="content_footer_footer_email" value="<?= e($footerContent['footer_email'] ?? '') ?>" maxlength="200" placeholder="rotaryclubofvirar@gmail.com">
                            </div>
                        </div>
                        <div>
                            <label>Office Address</label>
                            <textarea name="content_footer_footer_address" rows="3" maxlength="500"><?= e($footerContent['footer_address'] ?? '') ?></textarea>
                            <div class="char-counter">Max 500 characters</div>
                        </div>
                    </div>

                    <!-- Section 4: Social Media -->
                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:var(--rotary-blue);margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #f1f5f9;">Social Media</h3>
                        <p style="font-size:12px;color:#94a3b8;margin:0 0 12px;">Only platforms with a URL provided will show their icon in the footer. Leave blank to hide.</p>
                        <div class="field-row">
                            <div>
                                <label>Facebook URL</label>
                                <input type="url" name="content_footer_footer_facebook_url" value="<?= e($footerContent['footer_facebook_url'] ?? '') ?>" maxlength="500" placeholder="https://facebook.com/...">
                            </div>
                            <div>
                                <label>Instagram URL</label>
                                <input type="url" name="content_footer_footer_instagram_url" value="<?= e($footerContent['footer_instagram_url'] ?? '') ?>" maxlength="500" placeholder="https://instagram.com/...">
                            </div>
                        </div>
                        <div class="field-row">
                            <div>
                                <label>YouTube URL</label>
                                <input type="url" name="content_footer_footer_youtube_url" value="<?= e($footerContent['footer_youtube_url'] ?? '') ?>" maxlength="500" placeholder="https://youtube.com/...">
                            </div>
                            <div>
                                <label>LinkedIn URL</label>
                                <input type="url" name="content_footer_footer_linkedin_url" value="<?= e($footerContent['footer_linkedin_url'] ?? '') ?>" maxlength="500" placeholder="https://linkedin.com/...">
                            </div>
                        </div>
                        <div>
                            <label>Twitter / X URL</label>
                            <input type="url" name="content_footer_footer_twitter_url" value="<?= e($footerContent['footer_twitter_url'] ?? '') ?>" maxlength="500" placeholder="https://twitter.com/...">
                        </div>
                    </div>

                    <!-- Section 5: Copyright -->
                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:var(--rotary-blue);margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #f1f5f9;">Copyright</h3>
                        <div>
                            <label>Copyright Text</label>
                            <input type="text" name="content_footer_footer_copyright_text" value="<?= e($footerContent['footer_copyright_text'] ?? '') ?>" maxlength="200" placeholder="Rotary Club of Virar | Service Above Self">
                            <div style="font-size:11px;color:#94a3b8;margin-top:2px;">The year is prepended automatically. Example: &copy; 2026 [your text]</div>
                        </div>
                    </div>

                    <!-- Section 6: Developer Credit -->
                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:var(--rotary-blue);margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #f1f5f9;">Bottom Credits</h3>
                        <div>
                            <label>Developer Credit</label>
                            <input type="text" name="content_footer_footer_developer_credit" value="<?= e($footerContent['footer_developer_credit'] ?? '') ?>" maxlength="200" placeholder="Designed & Developed by Rotary Club of Virar IT Team">
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div style="text-align:right;margin-top:20px;">
            <button type="submit" class="btn btn-yellow btn-lg"><i data-lucide="save" style="width:18px;height:18px;"></i> Save All Changes</button>
        </div>
    </form>
</div>

<script>
lucide.createIcons();

function switchTab(tab) {
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.page-tab').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + tab).style.display = 'block';
    document.querySelector('.page-tab[onclick="switchTab(\'' + tab + '\')"]')?.classList.add('active');
    document.querySelectorAll('.page-tab').forEach(el => {
        var txt = el.textContent.trim().toLowerCase();
        if (tab === 'home' && txt.includes('home')) el.classList.add('active');
        else if (tab === 'about' && txt.includes('about')) el.classList.add('active');
        else if (tab === 'contact' && txt.includes('contact')) el.classList.add('active');
        else if (tab === 'footer' && txt.includes('footer')) el.classList.add('active');
    });
}
</script>
</body>
</html>
