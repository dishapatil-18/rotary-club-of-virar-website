<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: ../login.php"); exit; }
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/admin_functions.php';
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
        'about_banner_image' => ['about', 'banner_image'],
        'about_image_1' => ['about', 'about_image_1'],
        'about_image_2' => ['about', 'about_image_2'],
        'about_image_3' => ['about', 'about_image_3'],
        'contact_image' => ['contact', 'contact_image'],
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
}

$homeContent = getSiteContent($conn, 'home');
$aboutContent = getSiteContent($conn, 'about');
$contactContent = getSiteContent($conn, 'contact');

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
                                <input type="text" name="content_home_hero_heading" value="<?= e($homeContent['hero_heading'] ?? 'Service Above Self — Since 2020') ?>" maxlength="100">
                                <div class="char-counter">Max 100 characters</div>
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
                        </div>
                    </div>

                    <!-- About Preview -->
                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:var(--rotary-blue);margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #f1f5f9;">About Preview Section</h3>
                        <div class="field-group">
                            <div>
                                <label>About Preview Title</label>
                                <input type="text" name="content_home_about_title" value="<?= e($homeContent['about_title'] ?? 'Who We Are') ?>" maxlength="100">
                            </div>
                            <div>
                                <label>About Preview Description</label>
                                <textarea name="content_home_about_description" rows="4" maxlength="1000"><?= e($homeContent['about_description'] ?? '') ?></textarea>
                                <div class="char-counter">Max 1000 characters</div>
                            </div>
                            <div>
                                <label>About Preview Image</label>
                                <div class="img-upload-wrap">
                                    <img class="img-preview" src="<?= e(imgPath($homeContent['about_image'] ?? '../assets/uploads/Logo/areas-of-focus.jpeg')) ?>" alt="About Preview" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22160%22 height=%22100%22><rect fill=%22%23f1f5f9%22 width=%22160%22 height=%22100%22/><text x=%2250%%22 y=%2250%%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%2394a3b8%22 font-size=%2212%22>No Image</text></svg>'">
                                    <div class="file-input">
                                        <input type="file" name="about_image" accept="image/jpeg,image/png,image/webp">
                                        <div style="font-size:11px;color:#94a3b8;margin-top:2px;">JPEG, PNG or WebP. Leave empty to keep current.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Areas of Rotary -->
                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:var(--rotary-blue);margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #f1f5f9;">Areas of Rotary Images</h3>
                        <p style="font-size:12px;color:#94a3b8;margin:0 0 12px;">Upload images for each of the 7 Rotary areas of focus.</p>
                        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;">
                            <?php
                            $areaLabels = ['Livelihood', 'Education', 'Peace & Harmony', 'Community', 'Water & Sanitation', 'Environment', 'Health'];
                            for ($i = 1; $i <= 7; $i++):
                                $imgKey = 'area_image_' . $i;
                                $currentImg = $homeContent[$imgKey] ?? $areaDefaults[$i-1];
                            ?>
                            <div>
                                <label style="font-size:12px;"><?= $areaLabels[$i-1] ?></label>
                                <div class="img-upload-wrap" style="flex-direction:column;align-items:stretch;gap:8px;">
                                    <img class="img-preview" style="width:100%;height:120px;" src="<?= e(imgPath($currentImg)) ?>" alt="<?= $areaLabels[$i-1] ?>" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22280%22 height=%22120%22><rect fill=%22%23f1f5f9%22 width=%22280%22 height=%22120%22/><text x=%2250%%22 y=%2250%%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%2394a3b8%22 font-size=%2212%22>No Image</text></svg>'">
                                    <div class="file-input">
                                        <input type="file" name="<?= $imgKey ?>" accept="image/jpeg,image/png,image/webp" style="font-size:12px;padding:6px;">
                                    </div>
                                </div>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- Featured Activities -->
                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:var(--rotary-blue);margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #f1f5f9;">Featured Activities Section</h3>
                        <div class="field-group">
                            <div>
                                <label>Featured Activities Heading</label>
                                <input type="text" name="content_home_activities_heading" value="<?= e($homeContent['activities_heading'] ?? 'Featured Activities') ?>" maxlength="100">
                            </div>
                            <div>
                                <label>Featured Activities Description</label>
                                <textarea name="content_home_activities_description" rows="3" maxlength="500"><?= e($homeContent['activities_description'] ?? '') ?></textarea>
                                <div class="char-counter">Max 500 characters</div>
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

                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:var(--rotary-blue);margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #f1f5f9;">Banner Section</h3>
                        <div class="field-group">
                            <div>
                                <label>Banner Title</label>
                                <input type="text" name="content_about_banner_title" value="<?= e($aboutContent['banner_title'] ?? 'About Rotary Club of Virar') ?>" maxlength="200">
                            </div>
                            <div>
                                <label>Banner Description</label>
                                <textarea name="content_about_banner_description" rows="3" maxlength="500"><?= e($aboutContent['banner_description'] ?? '') ?></textarea>
                                <div class="char-counter">Max 500 characters</div>
                            </div>
                            <div>
                                <label>Banner Image</label>
                                <div class="img-upload-wrap">
                                    <img class="img-preview" src="<?= e(imgPath($aboutContent['banner_image'] ?? '../assets/uploads/Logo/rotary-icon.png')) ?>" alt="Banner" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22160%22 height=%22100%22><rect fill=%22%23f1f5f9%22 width=%22160%22 height=%22100%22/><text x=%2250%%22 y=%2250%%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%2394a3b8%22 font-size=%2212%22>No Image</text></svg>'">
                                    <div class="file-input">
                                        <input type="file" name="about_banner_image" accept="image/jpeg,image/png,image/webp">
                                        <div style="font-size:11px;color:#94a3b8;margin-top:2px;">JPEG, PNG or WebP.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:var(--rotary-blue);margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #f1f5f9;">Main Content</h3>
                        <div>
                            <label>About Description</label>
                            <textarea name="content_about_about_description" rows="8" maxlength="5000"><?= e($aboutContent['about_description'] ?? '') ?></textarea>
                            <div class="char-counter">Max 5000 characters</div>
                        </div>
                    </div>

                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:var(--rotary-blue);margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #f1f5f9;">Images</h3>
                        <p style="font-size:12px;color:#94a3b8;margin:0 0 12px;">Upload images to display on the About page.</p>
                        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;">
                            <?php for ($i = 1; $i <= 3; $i++):
                                $imgKey = 'about_image_' . $i;
                                $currentImg = $aboutContent[$imgKey] ?? '';
                            ?>
                            <div>
                                <label>Image <?= $i ?></label>
                                <div class="img-upload-wrap" style="flex-direction:column;align-items:stretch;gap:8px;">
                                    <img class="img-preview" style="width:100%;height:130px;" src="<?= e(imgPath($currentImg ?: '../assets/uploads/Logo/rotary-icon.png')) ?>" alt="About Image <?= $i ?>" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22260%22 height=%22130%22><rect fill=%22%23f1f5f9%22 width=%22260%22 height=%22130%22/><text x=%2250%%22 y=%2250%%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%2394a3b8%22 font-size=%2212%22>No Image</text></svg>'">
                                    <div class="file-input">
                                        <input type="file" name="about_image_<?= $i ?>" accept="image/jpeg,image/png,image/webp" style="font-size:12px;padding:6px;">
                                    </div>
                                </div>
                            </div>
                            <?php endfor; ?>
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

                    <div>
                        <h3 style="font-size:14px;font-weight:700;color:var(--rotary-blue);margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #f1f5f9;">Banner Section</h3>
                        <div class="field-group">
                            <div>
                                <label>Banner Title</label>
                                <input type="text" name="content_contact_banner_title" value="<?= e($contactContent['banner_title'] ?? "Let's Connect") ?>" maxlength="200">
                            </div>
                            <div>
                                <label>Banner Description</label>
                                <textarea name="content_contact_banner_description" rows="3" maxlength="500"><?= e($contactContent['banner_description'] ?? '') ?></textarea>
                                <div class="char-counter">Max 500 characters</div>
                            </div>
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
                                <label>Contact Description</label>
                                <textarea name="content_contact_contact_description" rows="5" maxlength="2000"><?= e($contactContent['contact_description'] ?? '') ?></textarea>
                                <div class="char-counter">Max 2000 characters</div>
                            </div>
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
    // Fallback
    document.querySelectorAll('.page-tab').forEach(el => {
        if (el.textContent.trim().toLowerCase().includes(tab === 'home' ? 'home' : tab === 'about' ? 'about' : 'contact')) {
            if (tab === 'home' && el.textContent.includes('Home')) el.classList.add('active');
            else if (tab === 'about' && el.textContent.includes('About')) el.classList.add('active');
            else if (tab === 'contact' && el.textContent.includes('Contact')) el.classList.add('active');
        }
    });
}
</script>
</body>
</html>
