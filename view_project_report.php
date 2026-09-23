<?php
session_start();
include 'includes/db_connect.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/website_settings.php';
$ws = getWebsiteSettings($conn);

// Restrict to authorized admins only
$allowedReportRoles = ['super_admin', 'President', 'Secretary', 'Treasurer'];
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_role']) || !in_array($_SESSION['admin_role'], $allowedReportRoles)) {
    header("Location: login.php");
    exit;
}

$project_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$project_id) { header("Location: activities.php"); exit; }

// Fetch project with report
$stmt = $conn->prepare("
    SELECT p.*, pr.year, pr.summary, pr.funds_raised, pr.expenditure, pr.achievements, pr.created_at AS report_created_at
    FROM projects p
    LEFT JOIN project_reports pr ON p.project_id = pr.project_id
    WHERE p.project_id = ?
");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$res = $stmt->get_result();
$project = $res->fetch_assoc();
$stmt->close();

if (!$project) { header("Location: activities.php"); exit; }

// Fetch associated media from gallery
$media = [];
$stmt = $conn->prepare("SELECT * FROM media_gallery WHERE reference_type = 'project' AND reference_id = ? AND status = 'active' ORDER BY created_at DESC");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $media[] = $row;
$stmt->close();

$pageTitle = htmlspecialchars($project['title']) . ' - Project Report';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <style>
        :root { --rotary-blue: #0A2342; --rotary-yellow: #FFC000; --rotary-indigo: #4F46E5; --rotary-green: #10B981; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; color: var(--rotary-blue); }
        .report-hero { background: linear-gradient(135deg, #0A2342 0%, #1a2a6c 40%, #2d1b69 100%); position: relative; overflow: hidden; }
        .report-hero::before { content: ''; position: absolute; inset: 0; background: radial-gradient(circle at 30% 50%, rgba(255,192,0,0.08) 0%, transparent 50%); }
        .section-title { font-family: 'Playfair Display', serif; font-weight: 800; }
        .media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 16px; }
        .media-item { aspect-ratio: 4/3; object-fit: cover; border-radius: 12px; transition: transform 0.3s; cursor: pointer; }
        .media-item:hover { transform: scale(1.03); }
        .gallery-placeholder { border: 2px dashed #d1d5db; border-radius: 12px; min-height: 160px; display: flex; align-items: center; justify-content: center; flex-direction: column; color: #9ca3af; background: #f9fafb; }
        .badge-rotary { background: var(--rotary-yellow); color: var(--rotary-blue); padding: 4px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; display: inline-block; }
        .back-link { transition: all 0.3s; }
        .back-link:hover { color: var(--rotary-yellow); }
    </style>
</head>
<body>
    <!-- Nav -->
    <header class="bg-white shadow-sm sticky top-0 z-40">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img src="<?= e($ws['website_logo']) ?>" alt="<?= e($ws['website_short_name']) ?>" class="w-9 h-9 rounded-full">
                <span class="font-bold text-lg" style="color:var(--rotary-blue)"><?= e($ws['website_name']) ?></span>
            </div>
            <a href="activities.php" class="back-link text-sm font-semibold flex items-center gap-1.5" style="color:var(--rotary-blue)">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Activities
            </a>
        </div>
    </header>

    <!-- Hero -->
    <section class="report-hero text-white">
        <div class="max-w-6xl mx-auto px-4 py-16 md:py-20 relative z-10">
            <span class="badge-rotary mb-4 inline-block">Project Report</span>
            <h1 class="section-title text-3xl md:text-5xl leading-tight mb-4"><?= htmlspecialchars($project['title']) ?></h1>
            <div class="flex flex-wrap gap-4 text-sm text-gray-300">
                <span class="flex items-center gap-1.5"><i data-lucide="calendar" class="w-4 h-4 text-yellow-400"></i> <?= htmlspecialchars($project['start_date']) ?> — <?= htmlspecialchars($project['end_date']) ?></span>
                <?php if ($project['year']): ?>
                <span class="flex items-center gap-1.5"><i data-lucide="clock" class="w-4 h-4 text-yellow-400"></i> Rotary Year <?= htmlspecialchars($project['year']) ?></span>
                <?php endif; ?>
                <?php if ($project['collaborator']): ?>
                <span class="flex items-center gap-1.5"><i data-lucide="handshake" class="w-4 h-4 text-yellow-400"></i> <?= htmlspecialchars($project['collaborator']) ?></span>
                <?php endif; ?>
                <span class="flex items-center gap-1.5"><i data-lucide="flag" class="w-4 h-4 text-yellow-400"></i> <?= htmlspecialchars($project['status']) ?></span>
            </div>
        </div>
    </section>

    <!-- Content -->
    <main class="max-w-6xl mx-auto px-4 py-10">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main -->
            <div class="lg:col-span-2 space-y-8">

                <!-- Cover Image -->
                <div>
                    <h2 class="section-title text-xl font-bold mb-3 flex items-center gap-2">
                        <i data-lucide="image" class="w-5 h-5 text-yellow-500"></i> Cover Image
                    </h2>
                    <?php if ($project['image_url']): ?>
                        <img src="<?= htmlspecialchars($project['image_url']) ?>" alt="<?= htmlspecialchars($project['title']) ?>" class="w-full rounded-xl shadow-lg object-cover max-h-96" onerror="this.style.display='none'">
                    <?php else: ?>
                        <div class="gallery-placeholder p-8">
                            <i data-lucide="image-off" class="w-10 h-10 mb-2"></i>
                            <p class="text-sm font-medium">No cover image uploaded yet</p>
                            <p class="text-xs mt-1">Admin can upload via Project edit form</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Objective & Summary -->
                <div class="bg-white rounded-xl shadow-sm border p-6">
                    <h2 class="section-title text-xl font-bold mb-4 flex items-center gap-2">
                        <i data-lucide="file-text" class="w-5 h-5 text-yellow-500"></i> Project Summary
                    </h2>
                    <p class="text-gray-700 leading-relaxed"><?= nl2br(htmlspecialchars($project['description'])) ?></p>
                </div>

                <!-- Report Details -->
                <?php if ($project['summary']): ?>
                <div class="bg-white rounded-xl shadow-sm border p-6">
                    <h2 class="section-title text-xl font-bold mb-4 flex items-center gap-2">
                        <i data-lucide="clipboard-list" class="w-5 h-5 text-yellow-500"></i> Detailed Report
                    </h2>
                    <p class="text-gray-700 leading-relaxed"><?= nl2br(htmlspecialchars($project['summary'])) ?></p>
                </div>
                <?php endif; ?>

                <!-- Achievements -->
                <?php if ($project['achievements']): ?>
                <div class="bg-white rounded-xl shadow-sm border p-6">
                    <h2 class="section-title text-xl font-bold mb-4 flex items-center gap-2">
                        <i data-lucide="award" class="w-5 h-5 text-yellow-500"></i> Achievements & Impact
                    </h2>
                    <p class="text-gray-700 leading-relaxed"><?= nl2br(htmlspecialchars($project['achievements'])) ?></p>
                </div>
                <?php endif; ?>

                <!-- Additional Photos -->
                <div>
                    <h2 class="section-title text-xl font-bold mb-4 flex items-center gap-2">
                        <i data-lucide="images" class="w-5 h-5 text-yellow-500"></i> Additional Photos
                    </h2>
                    <?php
                    $additional = array_filter($media, fn($m) => $m['media_path'] !== $project['image_url']);
                    if (!empty($additional)): ?>
                        <div class="media-grid">
                            <?php foreach ($additional as $m): ?>
                                <img src="<?= htmlspecialchars($m['media_path']) ?>"
                                     alt="<?= htmlspecialchars($m['title'] ?? 'Photo') ?>"
                                     class="media-item shadow"
                                     onclick="openPreview(this.src)"
                                     onerror="this.style.display='none'">
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="gallery-placeholder p-8">
                            <i data-lucide="image-plus" class="w-10 h-10 mb-2"></i>
                            <p class="text-sm font-medium">No additional photos yet</p>
                            <p class="text-xs mt-1 text-center">Admin can upload images via <a href="Admin/admin_add_media.php" class="text-yellow-600 font-semibold hover:underline">Media Gallery</a> (select "Project" category)</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Quick Info -->
                <div class="bg-white rounded-xl shadow-sm border p-5">
                    <h3 class="font-bold text-sm uppercase tracking-wider text-gray-500 mb-3">Quick Info</h3>
                    <div class="space-y-3 text-sm">
                        <div><span class="font-semibold text-gray-700">Status</span><br><span class="badge-rotary mt-1 inline-block"><?= htmlspecialchars($project['status']) ?></span></div>
                        <div><span class="font-semibold text-gray-700">Start Date</span><br><span class="text-gray-600"><?= htmlspecialchars($project['start_date']) ?></span></div>
                        <div><span class="font-semibold text-gray-700">End Date</span><br><span class="text-gray-600"><?= htmlspecialchars($project['end_date']) ?></span></div>
                        <?php if ($project['year']): ?>
                        <div><span class="font-semibold text-gray-700">Rotary Year</span><br><span class="text-gray-600"><?= htmlspecialchars($project['year']) ?></span></div>
                        <?php endif; ?>
                        <?php if ($project['collaborator']): ?>
                        <div><span class="font-semibold text-gray-700">Collaborator</span><br><span class="text-gray-600"><?= htmlspecialchars($project['collaborator']) ?></span></div>
                        <?php endif; ?>
                        <?php if ($project['funds_raised']): ?>
                        <div><span class="font-semibold text-gray-700">Funds Raised</span><br><span class="text-gray-600"><?= htmlspecialchars($project['funds_raised']) ?></span></div>
                        <?php endif; ?>
                        <?php if ($project['expenditure']): ?>
                        <div><span class="font-semibold text-gray-700">Expenditure</span><br><span class="text-gray-600"><?= htmlspecialchars($project['expenditure']) ?></span></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Rotary Area of Focus -->
                <div class="bg-white rounded-xl shadow-sm border p-5">
                    <h3 class="font-bold text-sm uppercase tracking-wider text-gray-500 mb-3">Area of Focus</h3>
                    <p class="text-sm text-gray-700"><?php
                        $focus = match(true) {
                            stripos($project['title'] . $project['description'], 'health') !== false ||
                            stripos($project['title'] . $project['description'], 'medical') !== false ||
                            stripos($project['title'] . $project['description'], 'cancer') !== false ||
                            stripos($project['title'] . $project['description'], 'blood') !== false => 'Disease Prevention & Treatment',
                            stripos($project['title'] . $project['description'], 'tree') !== false ||
                            stripos($project['title'] . $project['description'], 'environment') !== false ||
                            stripos($project['title'] . $project['description'], 'beach') !== false => 'Environmental Protection',
                            stripos($project['title'] . $project['description'], 'education') !== false ||
                            stripos($project['title'] . $project['description'], 'training') !== false ||
                            stripos($project['title'] . $project['description'], 'seminar') !== false ||
                            stripos($project['title'] . $project['description'], 'exhibition') !== false => 'Basic Education & Literacy',
                            stripos($project['title'] . $project['description'], 'women') !== false ||
                            stripos($project['title'] . $project['description'], 'menstrual') !== false ||
                            stripos($project['title'] . $project['description'], 'hygiene') !== false => 'Maternal & Child Health',
                            stripos($project['title'] . $project['description'], 'youth') !== false ||
                            stripos($project['title'] . $project['description'], 'exchange') !== false ||
                            stripos($project['title'] . $project['description'], 'rmun') !== false ||
                            stripos($project['title'] . $project['description'], 'rypn') !== false => 'Youth Development',
                            stripos($project['title'] . $project['description'], 'disability') !== false ||
                            stripos($project['title'] . $project['description'], 'blind') !== false ||
                            stripos($project['title'] . $project['description'], 'visually') !== false ||
                            stripos($project['title'] . $project['description'], 'dignity') !== false => 'Service to People with Disabilities',
                            stripos($project['title'] . $project['description'], 'diwali') !== false ||
                            stripos($project['title'] . $project['description'], 'christmas') !== false ||
                            stripos($project['title'] . $project['description'], 'republic') !== false ||
                            stripos($project['title'] . $project['description'], 'celebration') !== false => 'Community Engagement',
                            default => 'Community Service'
                        };
                        echo htmlspecialchars($focus);
                    ?></p>
                </div>

                <!-- Report Images -->
                <div class="bg-white rounded-xl shadow-sm border p-5">
                    <h3 class="font-bold text-sm uppercase tracking-wider text-gray-500 mb-3">Report Images</h3>
                    <?php
                    $reportImages = array_filter($media, fn($m) => $m['media_path'] === $project['image_url'] || empty($additional));
                    if (!empty($media)): ?>
                        <div class="grid grid-cols-2 gap-2">
                            <?php foreach (array_slice($media, 0, 4) as $m): ?>
                                <img src="<?= htmlspecialchars($m['media_path']) ?>"
                                     class="rounded-lg object-cover aspect-square cursor-pointer hover:opacity-80 transition"
                                     onclick="openPreview(this.src)"
                                     onerror="this.style.display='none'">
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="gallery-placeholder p-4 text-center">
                            <i data-lucide="file-image" class="w-8 h-8 mb-1 mx-auto"></i>
                            <p class="text-xs">No report images uploaded</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Image Preview Modal -->
    <div id="preview-modal" class="fixed inset-0 bg-black/80 z-50 hidden items-center justify-center p-4" onclick="closePreview(event)">
        <button class="absolute top-4 right-4 text-white/70 hover:text-white z-10" onclick="closePreview()"><i data-lucide="x" class="w-8 h-8"></i></button>
        <img id="preview-img" src="" class="max-w-full max-h-[90vh] rounded-lg shadow-2xl" onclick="event.stopPropagation()">
    </div>

    <script>
        lucide.createIcons();
        function openPreview(src) {
            document.getElementById('preview-img').src = src;
            document.getElementById('preview-modal').classList.remove('hidden');
            document.getElementById('preview-modal').classList.add('flex');
            document.body.style.overflow = 'hidden';
        }
        function closePreview(e) {
            if (e && e.target !== e.currentTarget) return;
            document.getElementById('preview-modal').classList.add('hidden');
            document.getElementById('preview-modal').classList.remove('flex');
            document.body.style.overflow = '';
        }
    </script>
</body>
</html>
