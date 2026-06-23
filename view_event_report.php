<?php
session_start();
include 'includes/db_connect.php';

$event_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$event_id) { header("Location: activities.php"); exit; }

// Fetch event with report
$stmt = $conn->prepare("
    SELECT e.*, er.details AS report_details, er.submitted_by, er.date AS report_date
    FROM events e
    LEFT JOIN event_reports er ON e.event_id = er.event_id
    WHERE e.event_id = ?
");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$res = $stmt->get_result();
$event = $res->fetch_assoc();
$stmt->close();

if (!$event) { header("Location: activities.php"); exit; }

$today = date('Y-m-d');
$computedStatus = 'upcoming';
if ($event['end_date'] < $today) $computedStatus = 'completed';
elseif ($event['start_date'] <= $today && $event['end_date'] >= $today) $computedStatus = 'ongoing';

// Fetch associated media from gallery
$media = [];
$stmt = $conn->prepare("SELECT * FROM media_gallery WHERE reference_type = 'event' AND reference_id = ? AND status = 'active' ORDER BY created_at DESC");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $media[] = $row;
$stmt->close();

$pageTitle = htmlspecialchars($event['title']) . ' - Event Report';
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
        .report-hero { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 40%, #0A2342 100%); position: relative; overflow: hidden; }
        .report-hero::before { content: ''; position: absolute; inset: 0; background: radial-gradient(circle at 70% 30%, rgba(79,70,229,0.1) 0%, transparent 50%); }
        .section-title { font-family: 'Playfair Display', serif; font-weight: 800; }
        .media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 16px; }
        .media-item { aspect-ratio: 4/3; object-fit: cover; border-radius: 12px; transition: transform 0.3s; cursor: pointer; }
        .media-item:hover { transform: scale(1.03); }
        .gallery-placeholder { border: 2px dashed #d1d5db; border-radius: 12px; min-height: 160px; display: flex; align-items: center; justify-content: center; flex-direction: column; color: #9ca3af; background: #f9fafb; }
        .badge-rotary { background: var(--rotary-yellow); color: var(--rotary-blue); padding: 4px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; display: inline-block; }
        .back-link { transition: all 0.3s; }
        .back-link:hover { color: var(--rotary-yellow); }
        .status-badge { padding: 3px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; display: inline-block; text-transform: uppercase; letter-spacing: 0.5px; }
    </style>
</head>
<body>
    <header class="bg-white shadow-sm sticky top-0 z-40">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img src="assets/uploads/Logo/rotary-icon.png" alt="Rotary" class="w-9 h-9 rounded-full">
                <span class="font-bold text-lg" style="color:var(--rotary-blue)">Rotary Club of Virar</span>
            </div>
            <a href="activities.php" class="back-link text-sm font-semibold flex items-center gap-1.5" style="color:var(--rotary-blue)">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Activities
            </a>
        </div>
    </header>

    <section class="report-hero text-white">
        <div class="max-w-6xl mx-auto px-4 py-16 md:py-20 relative z-10">
            <span class="badge-rotary mb-4 inline-block">Event Report</span>
            <h1 class="section-title text-3xl md:text-5xl leading-tight mb-4"><?= htmlspecialchars($event['title']) ?></h1>
            <div class="flex flex-wrap gap-4 text-sm text-gray-300">
                <span class="flex items-center gap-1.5"><i data-lucide="calendar" class="w-4 h-4 text-indigo-400"></i> <?= htmlspecialchars($event['start_date']) ?></span>
                <?php if ($event['location']): ?>
                <span class="flex items-center gap-1.5"><i data-lucide="map-pin" class="w-4 h-4 text-indigo-400"></i> <?= htmlspecialchars($event['location']) ?></span>
                <?php endif; ?>
                <?php if ($event['category']): ?>
                <span class="flex items-center gap-1.5"><i data-lucide="tag" class="w-4 h-4 text-indigo-400"></i> <?= htmlspecialchars($event['category']) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <main class="max-w-6xl mx-auto px-4 py-10">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-8">

                <!-- Cover Image -->
                <div>
                    <h2 class="section-title text-xl font-bold mb-3 flex items-center gap-2">
                        <i data-lucide="image" class="w-5 h-5 text-indigo-500"></i> Cover Image
                    </h2>
                    <?php if ($event['image_url']): ?>
                        <img src="../<?= htmlspecialchars($event['image_url']) ?>" alt="<?= htmlspecialchars($event['title']) ?>" class="w-full rounded-xl shadow-lg object-cover max-h-96" onerror="this.style.display='none'">
                    <?php else: ?>
                        <div class="gallery-placeholder p-8">
                            <i data-lucide="image-off" class="w-10 h-10 mb-2"></i>
                            <p class="text-sm font-medium">No cover image uploaded yet</p>
                            <p class="text-xs mt-1">Admin can upload via Event edit form</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Event Summary -->
                <div class="bg-white rounded-xl shadow-sm border p-6">
                    <h2 class="section-title text-xl font-bold mb-4 flex items-center gap-2">
                        <i data-lucide="file-text" class="w-5 h-5 text-indigo-500"></i> Event Summary
                    </h2>
                    <p class="text-gray-700 leading-relaxed"><?= nl2br(htmlspecialchars($event['description'])) ?></p>
                </div>

                <!-- Report Details -->
                <?php if ($event['report_details']): ?>
                <div class="bg-white rounded-xl shadow-sm border p-6">
                    <h2 class="section-title text-xl font-bold mb-4 flex items-center gap-2">
                        <i data-lucide="clipboard-list" class="w-5 h-5 text-indigo-500"></i> Detailed Report
                    </h2>
                    <p class="text-gray-700 leading-relaxed"><?= nl2br(htmlspecialchars($event['report_details'])) ?></p>
                </div>
                <?php endif; ?>

                <!-- Additional Photos -->
                <div>
                    <h2 class="section-title text-xl font-bold mb-4 flex items-center gap-2">
                        <i data-lucide="images" class="w-5 h-5 text-indigo-500"></i> Event Photos
                    </h2>
                    <?php
                    $additional = array_filter($media, fn($m) => $m['media_path'] !== $event['image_url']);
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
                            <p class="text-sm font-medium">No event photos yet</p>
                            <p class="text-xs mt-1 text-center">Admin can upload images via <a href="Admin/admin_add_media.php" class="text-indigo-600 font-semibold hover:underline">Media Gallery</a> (select "Event" category)</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <div class="bg-white rounded-xl shadow-sm border p-5">
                    <h3 class="font-bold text-sm uppercase tracking-wider text-gray-500 mb-3">Event Info</h3>
                    <div class="space-y-3 text-sm">
                        <div>
                            <span class="font-semibold text-gray-700">Status</span><br>
                            <span class="status-badge mt-1 inline-block <?= $computedStatus === 'completed' ? 'bg-red-100 text-red-700' : ($computedStatus === 'ongoing' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700') ?>">
                                <?= $computedStatus ?>
                            </span>
                        </div>
                        <div><span class="font-semibold text-gray-700">Date</span><br><span class="text-gray-600"><?= htmlspecialchars($event['start_date']) ?></span></div>
                        <?php if ($event['end_date'] && $event['end_date'] !== $event['start_date']): ?>
                        <div><span class="font-semibold text-gray-700">End Date</span><br><span class="text-gray-600"><?= htmlspecialchars($event['end_date']) ?></span></div>
                        <?php endif; ?>
                        <?php if ($event['location']): ?>
                        <div><span class="font-semibold text-gray-700">Venue</span><br><span class="text-gray-600"><?= htmlspecialchars($event['location']) ?></span></div>
                        <?php endif; ?>
                        <?php if ($event['category']): ?>
                        <div><span class="font-semibold text-gray-700">Category</span><br><span class="text-gray-600"><?= htmlspecialchars($event['category']) ?></span></div>
                        <?php endif; ?>
                        <?php if ($event['submitted_by']): ?>
                        <div><span class="font-semibold text-gray-700">Reported by</span><br><span class="text-gray-600"><?= htmlspecialchars($event['submitted_by']) ?></span></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border p-5">
                    <h3 class="font-bold text-sm uppercase tracking-wider text-gray-500 mb-3">Key Activities</h3>
                    <ul class="text-sm text-gray-700 space-y-2">
                        <?php
                        $activities = [];
                        $desc_lower = strtolower($event['description'] ?? '');
                        if (stripos($desc_lower, 'blood donation') !== false) $activities[] = 'Blood Donation Drive';
                        if (stripos($desc_lower, 'health') !== false || stripos($desc_lower, 'medical') !== false || stripos($desc_lower, 'screening') !== false) $activities[] = 'Health & Wellness Activities';
                        if (stripos($desc_lower, 'tree') !== false || stripos($desc_lower, 'plantation') !== false) $activities[] = 'Tree Plantation';
                        if (stripos($desc_lower, 'seminar') !== false || stripos($desc_lower, 'training') !== false || stripos($desc_lower, 'workshop') !== false || stripos($desc_lower, 'education') !== false) $activities[] = 'Educational Session';
                        if (stripos($desc_lower, 'cultural') !== false || stripos($desc_lower, 'festival') !== false || stripos($desc_lower, 'celebration') !== false || stripos($desc_lower, 'fellowship') !== false) $activities[] = 'Cultural & Fellowship Activities';
                        if (stripos($desc_lower, 'networking') !== false || stripos($desc_lower, 'installation') !== false || stripos($desc_lower, 'conference') !== false) $activities[] = 'Networking & Leadership';
                        if (stripos($desc_lower, 'quiz') !== false || stripos($desc_lower, 'competition') !== false || stripos($desc_lower, 'debate') !== false) $activities[] = 'Competitions & Quizzes';
                        if (stripos($desc_lower, 'visit') !== false || stripos($desc_lower, 'industrial') !== false) $activities[] = 'Industrial/Community Visits';
                        if (stripos($desc_lower, 'awareness') !== false || stripos($desc_lower, 'scheme') !== false) $activities[] = 'Awareness Programs';
                        if (empty($activities)) $activities[] = 'Community Engagement Activities';
                        foreach ($activities as $a): ?>
                            <li class="flex items-start gap-2"><i data-lucide="check-circle" class="w-4 h-4 text-green-500 mt-0.5 flex-shrink-0"></i> <?= htmlspecialchars($a) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="bg-white rounded-xl shadow-sm border p-5">
                    <h3 class="font-bold text-sm uppercase tracking-wider text-gray-500 mb-3">Report Images</h3>
                    <?php if (!empty($media)): ?>
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
