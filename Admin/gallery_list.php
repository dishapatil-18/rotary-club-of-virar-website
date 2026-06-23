<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}
require __DIR__ . '/../includes/db_connect.php';

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    // Get file path before deleting
    $stmt = $conn->prepare("SELECT media_path FROM media_gallery WHERE media_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $file = __DIR__ . '/../' . $row['media_path'];
        if (file_exists($file)) @unlink($file);
    }
    $stmt->close();

    $del = $conn->prepare("DELETE FROM media_gallery WHERE media_id = ?");
    $del->bind_param("i", $id);
    $del->execute();
    $del->close();
    header("Location: gallery_list.php?deleted=1");
    exit;
}

// Handle toggle status
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $conn->query("UPDATE media_gallery SET status = IF(status='active','inactive','active') WHERE media_id = $id");
    header("Location: gallery_list.php");
    exit;
}

// Fetch all media
$media = [];
$res = $conn->query("SELECT m.*, 
    COALESCE(p.title, e.title, a.message) AS ref_title
    FROM media_gallery m
    LEFT JOIN projects p ON m.reference_type = 'project' AND m.reference_id = p.project_id
    LEFT JOIN events e ON m.reference_type = 'event' AND m.reference_id = e.event_id
    LEFT JOIN announcements a ON m.reference_type = 'announcement' AND m.reference_id = a.announcement_id
    ORDER BY m.created_at DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) $media[] = $row;
}
?>
<?php
$pageTitle = 'Gallery Management';
$activeNav = 'gallery';
require __DIR__ . '/includes/admin_head.php';
require __DIR__ . '/includes/admin_header.php';
?>
<div class="card">
<div class="card-body">

    <?php if (isset($_GET['deleted'])): ?>
    <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm flex items-center gap-2">
        <i data-lucide="check-circle" class="w-4 h-4"></i> Media deleted successfully.
    </div>
    <?php endif; ?>

    <?php if (empty($media)): ?>
    <div class="bg-white rounded-2xl shadow-xl p-12 text-center border-t-4 border-yellow-400">
        <i data-lucide="image-off" class="w-12 h-12 text-gray-300 mx-auto mb-3"></i>
        <p class="text-gray-500 font-medium">No media found.</p>
        <a href="admin_add_media.php" class="mt-3 inline-block text-sm text-[var(--rotary-blue)] font-semibold hover:text-yellow-600">Upload your first media</a>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <?php foreach ($media as $m): ?>
        <div class="item bg-white rounded-xl shadow-md overflow-hidden border border-gray-100 transition-all duration-200 group">
            <!-- Thumbnail -->
            <div class="relative h-40 bg-gray-100 overflow-hidden">
                <?php
                $imgPath = '../' . htmlspecialchars($m['media_path']);
                $fallback = '../assets/images/projects/default.png';
                ?>
                <img src="<?= $imgPath ?>" alt="<?= htmlspecialchars($m['title'] ?? '') ?>"
                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                     onerror="this.src='<?= $fallback ?>'">
                <!-- Status badge -->
                <span class="absolute top-2 right-2 badge <?= $m['status'] === 'active' ? 'badge-green' : 'badge-gray' ?>">
                    <?= $m['status'] ?>
                </span>
                <!-- Reference badge -->
                <span class="absolute top-2 left-2 text-xs font-semibold px-2 py-0.5 rounded-full bg-[var(--rotary-blue)] text-yellow-300">
                    <?= htmlspecialchars(ucfirst($m['reference_type'] ?? 'other')) ?>
                </span>
            </div>
            <!-- Info -->
            <div class="p-3">
                <p class="text-sm font-semibold text-gray-800 truncate"><?= htmlspecialchars($m['title'] ?? 'Untitled') ?></p>
                <p class="text-xs text-gray-500 mt-0.5 truncate"><?= htmlspecialchars($m['ref_title'] ?? '') ?></p>
                <p class="text-xs text-gray-400 mt-1"><?= $m['media_date'] ? date('d M Y', strtotime($m['media_date'])) : '' ?></p>
                <!-- Actions -->
                <div class="flex items-center gap-2 mt-2 pt-2 border-t border-gray-100">
                    <a href="?toggle=<?= $m['media_id'] ?>" class="btn btn-ghost btn-xs <?= $m['status'] === 'active' ? '' : 'text-green-600 hover:text-green-800' ?>">
                        <?= $m['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                    </a>
                    <a href="?delete=<?= $m['media_id'] ?>" onclick="return confirm('Delete this media?')" class="btn btn-delete btn-xs">
                        Delete
                    </a>
                    <button onclick="previewImage('<?= $imgPath ?>', '<?= htmlspecialchars($m['title'] ?? 'Untitled', ENT_QUOTES) ?>')" class="btn btn-primary btn-xs ml-auto">
                        Preview
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
</div>

<!-- Preview Modal -->
<div id="previewModal" class="modal-bg fixed inset-0 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full overflow-hidden">
        <div class="flex justify-between items-center p-4 border-b border-gray-100">
            <h3 id="previewTitle" class="font-bold text-gray-800 text-sm"></h3>
            <button onclick="closePreview()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <div class="p-4">
            <img id="previewImg" src="" alt="Preview" class="w-full h-auto max-h-[70vh] object-contain rounded-lg">
        </div>
    </div>
</div>

<script>
function previewImage(src, title) {
    document.getElementById('previewImg').src = src;
    document.getElementById('previewTitle').textContent = title;
    const modal = document.getElementById('previewModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closePreview() {
    const modal = document.getElementById('previewModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

document.getElementById('previewModal').addEventListener('click', function(e) {
    if (e.target === this) closePreview();
});
</script>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
