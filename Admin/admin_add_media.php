<?php
// admin_add_media.php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}
require_once "../includes/db_connect.php";

// =====================
// HANDLE FORM SUBMISSION
// =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $reference_type = $_POST['reference_type'] ?? '';
    $title          = trim($_POST['title'] ?? '');
    $description    = trim($_POST['description'] ?? '');
    $media_date     = $_POST['media_date'] ?? null;
    $status         = $_POST['status'] ?? 'active';
    $year_id        = !empty($_POST['year_id']) ? intval($_POST['year_id']) : null;

    if ($reference_type === 'project') {
        $reference_id = $_POST['project_id'] ?? '';
    } elseif ($reference_type === 'event') {
        $reference_id = $_POST['event_id'] ?? '';
    } elseif ($reference_type === 'announcement') {
        $reference_id = $_POST['announcement_id'] ?? '';
    } else {
        $reference_id = '';
    }

    if ($reference_type && $reference_id && !empty($_FILES['media_files']['name'][0])) {

        require_once __DIR__ . '/../includes/upload_helper.php';

        $uploadDir = "../uploads/media/";
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $allowedTypes = ['jpg', 'jpeg', 'png', 'webp'];
        $fileCount = count($_FILES['media_files']['name']);

        if ($fileCount > 7) {
            die("<script>alert('Maximum 7 images allowed.'); window.history.back();</script>");
        }

        for ($i = 0; $i < $fileCount; $i++) {

            $fileName = $_FILES['media_files']['name'][$i];
            $tmpName  = $_FILES['media_files']['tmp_name'][$i];
            $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedTypes)) {
                continue;
            }

            // Validate per-file size (5MB max)
            $singleFile = [
                'error' => $_FILES['media_files']['error'][$i],
                'size'  => $_FILES['media_files']['size'][$i],
                'name'  => $fileName,
            ];
            $v = validateUpload($singleFile, $allowedTypes, 5242880);
            if (!$v['valid']) {
                continue;
            }

            $newName = time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
            $filePath = $uploadDir . $newName;

            if (move_uploaded_file($tmpName, $filePath)) {

                $relativePath = "uploads/media/" . $newName;

                $stmt = $conn->prepare("
                    INSERT INTO media_gallery
                    (reference_type, reference_id, title, description, media_type,
                     media_path, uploaded_by, media_date, status, year_id)
                    VALUES (?, ?, ?, ?, 'image', ?, 'Admin', ?, ?, ?)
                ");

                $stmt->bind_param(
                    "sisssssi",
                    $reference_type,
                    $reference_id,
                    $title,
                    $description,
                    $relativePath,
                    $media_date,
                    $status,
                    $year_id
                );

                $stmt->execute();
            }
        }

        header("Location: admin_add_media.php?success=1");
        exit;
    }
}

// FETCH DROPDOWN DATA
$projects = mysqli_query($conn, "SELECT project_id, title FROM projects ORDER BY created_at DESC");
$events   = mysqli_query($conn, "SELECT event_id, title, start_date FROM events ORDER BY start_date DESC");
$anncs    = mysqli_query($conn, "SELECT announcement_id, message FROM announcements ORDER BY date_posted DESC");
$allYears = [];
$yrRes = $conn->query("SELECT id, year_name, is_current FROM rotary_years ORDER BY year_name DESC");
if ($yrRes) {
    while ($y = $yrRes->fetch_assoc()) $allYears[] = $y;
}
?>
<?php
$pageTitle = 'Upload Media';
$activeNav = 'media';
require __DIR__ . '/includes/admin_head.php';
require __DIR__ . '/includes/admin_header.php';
?>

<div class="card">
    <div class="card-body">

    <!-- Success Message -->
    <?php if (isset($_GET['success'])): ?>
    <div class="mb-5 px-4 py-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm flex items-center gap-2">
        <i data-lucide="check-circle" class="w-4 h-4 flex-shrink-0"></i>
        Media uploaded successfully.
    </div>
    <?php endif; ?>

    <!-- Form -->
    <div class="bg-white rounded-2xl shadow-xl p-6 sm:p-8 border-t-4 border-yellow-400">
        <form method="POST" enctype="multipart/form-data" class="space-y-5">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Media Category *</label>
                    <select name="reference_type" id="reference_type" required class="form-select">
                        <option value="">Select Category</option>
                        <option value="project">Project</option>
                        <option value="event">Event</option>
                        <option value="announcement">Announcement</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Rotary Year</label>
                    <select name="year_id" class="form-select">
                        <option value="">-- Select Year --</option>
                        <?php foreach ($allYears as $y): ?>
                        <option value="<?= $y['id'] ?>" <?= $y['is_current'] ? 'selected' : '' ?>><?= htmlspecialchars($y['year_name']) ?> <?= $y['is_current'] ? '(Current)' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <!-- Project Dropdown -->
            <div id="project_box" class="hidden">
                <label class="form-label">Select Project *</label>
                <select name="project_id" class="form-select">
                    <option value="">Select Project</option>
                    <?php while($p = mysqli_fetch_assoc($projects)): ?>
                    <option value="<?= $p['project_id'] ?>"><?= htmlspecialchars($p['title']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- Event Dropdown -->
            <div id="event_box" class="hidden">
                <label class="form-label">Select Event *</label>
                <select name="event_id" class="form-select">
                    <option value="">Select Event</option>
                    <?php while($e = mysqli_fetch_assoc($events)): ?>
                    <option value="<?= $e['event_id'] ?>"><?= htmlspecialchars($e['title']) ?> (<?= $e['start_date'] ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- Announcement Dropdown -->
            <div id="announcement_box" class="hidden">
                <label class="form-label">Select Announcement *</label>
                <select name="announcement_id" class="form-select">
                    <option value="">Select Announcement</option>
                    <?php while($a = mysqli_fetch_assoc($anncs)): ?>
                    <option value="<?= $a['announcement_id'] ?>"><?= htmlspecialchars(mb_strimwidth($a['message'], 0, 60, '...')) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Title</label>
                    <input type="text" name="title" class="form-input" placeholder="Optional title">
                </div>
                <div>
                    <label class="form-label">Media Date</label>
                    <input type="date" name="media_date" class="form-input">
                </div>
            </div>

            <div>
                <label class="form-label">Description</label>
                <textarea name="description" rows="2" class="form-textarea" placeholder="Optional description"></textarea>
            </div>

            <div>
                <label class="form-label">Upload Images (Max 7) *</label>
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-yellow-400 transition-colors cursor-pointer" onclick="document.getElementById('fileInput').click()">
                    <i data-lucide="upload" class="w-8 h-8 text-gray-400 mx-auto mb-2"></i>
                    <p class="text-sm text-gray-500">Click to browse or drag &amp; drop</p>
                    <p class="text-xs text-gray-400 mt-1">JPG, PNG, WebP only</p>
                    <input id="fileInput" type="file" name="media_files[]" multiple required accept=".jpg,.jpeg,.png,.webp" class="hidden" onchange="updateFileLabel(this)">
                </div>
                <p id="fileLabel" class="text-xs text-gray-500 mt-1.5"></p>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="btn btn-yellow">
                    <i data-lucide="upload-cloud" class="w-4 h-4"></i>Upload Media
                </button>
                <a href="dashboard.php" class="btn btn-secondary">
                    Cancel
                </a>
            </div>

        </form>
    </div>

    <div class="text-center mt-5">
        <a href="dashboard.php" class="text-sm text-[var(--rotary-blue)] font-semibold hover:text-yellow-600 transition-colors inline-flex items-center gap-1">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Dashboard
        </a>
    </div>
    </div>
</div>

<script>
const ref = document.getElementById('reference_type');
const boxes = {
    project: document.getElementById('project_box'),
    event: document.getElementById('event_box'),
    announcement: document.getElementById('announcement_box')
};

ref.addEventListener('change', () => {
    Object.values(boxes).forEach(b => { if (b) b.classList.add('hidden'); });
    if (boxes[ref.value]) boxes[ref.value].classList.remove('hidden');
});

function updateFileLabel(input) {
    const label = document.getElementById('fileLabel');
    if (input.files.length > 0) {
        label.textContent = input.files.length + ' file(s) selected';
    } else {
        label.textContent = '';
    }
}
</script>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
