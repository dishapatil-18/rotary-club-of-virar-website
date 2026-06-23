<?php
// ===============================
// Project Management (Admin Only)
// ===============================

// Start session and check access
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

// Include DB connection
require __DIR__ . '/../includes/db_connect.php';

// Initialize variables
$editMode = false;
$project_id = $title = $description = $start_date = $end_date = $status = $image_url = $collaborator = "";

// ======================
// ADD / EDIT FORM SUBMIT
// ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $status = $_POST['status'];
    $collaborator = trim($_POST['collaborator'] ?? '');

    // Handle image upload (optional)
    if (!empty($_FILES['image']['name'])) {
        $uploadDir = '../uploads/projects/';
        if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
        $fileName = time() . '_' . basename($_FILES['image']['name']);
        $targetFile = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
            $image_url = 'uploads/projects/' . $fileName;
        }
    } else {
        $image_url = $_POST['existing_image'] ?? '';
    }

    // INSERT new project
    if (isset($_POST['add_project'])) {
        $stmt = $conn->prepare("INSERT INTO projects (title, description, start_date, end_date, status, image_url, collaborator) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $title, $description, $start_date, $end_date, $status, $image_url, $collaborator);
        $stmt->execute();
        $stmt->close();
        echo "<script>alert('✅ Project added successfully!'); window.location.href='project_action.php';</script>";
        exit;
    }

    // UPDATE existing project
    if (isset($_POST['update_project']) && isset($_POST['project_id'])) {
        $pid = intval($_POST['project_id']);
        $stmt = $conn->prepare("UPDATE projects SET title=?, description=?, start_date=?, end_date=?, status=?, image_url=?, collaborator=? WHERE project_id=?");
        $stmt->bind_param("sssssssi", $title, $description, $start_date, $end_date, $status, $image_url, $collaborator, $pid);
        $stmt->execute();
        $stmt->close();
        echo "<script>alert('✅ Project updated successfully!'); window.location.href='project_action.php';</script>";
        exit;
    }
}

// ===================
// DELETE PROJECT
// ===================
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM projects WHERE project_id=?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();
    echo "<script>alert('🗑️ Project deleted successfully!'); window.location.href='project_action.php';</script>";
    exit;
}

// ===================
// EDIT PROJECT (LOAD DATA)
// ===================
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM projects WHERE project_id=?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows === 1) {
        $editMode = true;
        $row = $result->fetch_assoc();
        $project_id = $row['project_id'];
        $title = $row['title'];
        $description = $row['description'];
        $start_date = $row['start_date'];
        $end_date = $row['end_date'];
        $status = $row['status'];
        $image_url = $row['image_url'];
        $collaborator = $row['collaborator'];
    }
    $stmt->close();
}

// ===================
// FETCH ALL PROJECTS
// ===================
$projects = [];
$res = $conn->query("SELECT * FROM projects ORDER BY start_date DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $projects[] = $row;
    }
}
?>

<?php
$pageTitle = 'Manage Projects';
$activeNav = 'projects';
require __DIR__ . '/includes/admin_head.php';
require __DIR__ . '/includes/admin_header.php';
?>
    <div class="card">
        <div class="card-body">
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <?php if ($editMode): ?>
                <input type="hidden" name="project_id" value="<?= $project_id ?>">
                <input type="hidden" name="existing_image" value="<?= htmlspecialchars($image_url) ?>">
            <?php endif; ?>

            <div>
                <label class="form-label">Project Title</label>
                <input type="text" name="title" required value="<?= htmlspecialchars($title) ?>" class="form-input">
            </div>

            <div>
                <label class="form-label">Description</label>
                <textarea name="description" rows="3" required class="form-textarea"><?= htmlspecialchars($description) ?></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" required value="<?= htmlspecialchars($start_date) ?>" class="form-input">
                </div>
                <div>
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" required value="<?= htmlspecialchars($end_date) ?>" class="form-input">
                </div>
            </div>

            <div>
                <label class="form-label">Status</label>
                <select name="status" required class="form-select">
                    <option value="Upcoming" <?= $status === 'Upcoming' ? 'selected' : '' ?>>Upcoming</option>
                    <option value="Ongoing" <?= $status === 'Ongoing' ? 'selected' : '' ?>>Ongoing</option>
                    <option value="Completed" <?= $status === 'Completed' ? 'selected' : '' ?>>Completed</option>
                </select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="form-label">Collaborator (Optional)</label>
                <input type="text" name="collaborator" value="<?= htmlspecialchars($collaborator) ?>" placeholder="e.g. Edu Foundation" class="form-input">
            </div>
            <div>
                <label class="form-label">Cover Image</label>
                <input type="file" name="image" accept="image/*" class="form-input">
                <p class="text-xs text-gray-500 mt-1">This image will appear as the Cover Image on the project report page.</p>
            </div>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm text-blue-700">
                <i data-lucide="info" class="w-4 h-4 inline mr-1"></i>
                <strong>Additional Photos:</strong> Upload more images for this project via
                <a href="admin_add_media.php" class="font-semibold underline">Media Gallery</a>
                (select "Project" category and choose this project).
            </div>
            </div>
                <?php if ($editMode && $image_url): ?>
                    <img src="../<?= htmlspecialchars($image_url) ?>" class="mt-3 h-32 rounded-lg shadow">
                <?php endif; ?>
            </div>

            <div class="flex justify-end space-x-3">
                <button onclick="window.location.href = 'dashboard.php'" class="btn btn-secondary">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Dashboard
                </button>
                <a href="project_action.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" name="<?= $editMode ? 'update_project' : 'add_project' ?>" class="btn btn-primary">
                    <i data-lucide="folder-plus" class="w-4 h-4"></i> <?= $editMode ? 'Update Project' : 'Add Project' ?>
                </button>
            </div>
        </form>
        </div>
    </div>

    <!-- ================= VIEW ALL PROJECTS ================= -->
    <div class="card">
        <div class="card-body">
            <div class="flex items-center justify-between mb-4">
            <h2 class="text-2xl font-bold text-gray-800">All Projects</h2>
            <a href="project_action.php" class="btn btn-primary btn-sm"><i data-lucide="list" class="w-3 h-3"></i> View All Projects</a>
        </div>
        <?php if (empty($projects)): ?>
            <p class="text-gray-500">No projects found.</p>
        <?php else: ?>
            <table class="data-table">
                <thead class="bg-gray-100">
                    <tr>
                        <th>#</th>
                        <th class="text-left">Title</th>
                        <th class="text-left">Collaborator</th>
                        <th class="text-left">Status</th>
                        <th class="text-left">Start</th>
                        <th class="text-left">End</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $p): ?>
                        <tr>
                            <td class="text-center"><?= $p['project_id'] ?></td>
                            <td><?= htmlspecialchars($p['title']) ?></td>
                            <td><?= htmlspecialchars($p['collaborator'] ?? '-') ?></td>
                            <td>
                                <?php
                                $s = $p['status'];
                                if ($s === 'Active'): ?>
                                    <span class="badge badge-green">Active</span>
                                <?php elseif ($s === 'Completed'): ?>
                                    <span class="badge badge-blue">Completed</span>
                                <?php elseif ($s === 'On Hold'): ?>
                                    <span class="badge badge-yellow">On Hold</span>
                                <?php else: ?>
                                    <span class="badge badge-gray"><?= htmlspecialchars($s) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($p['start_date']) ?></td>
                            <td><?= htmlspecialchars($p['end_date']) ?></td>
                            <td class="text-center">
                                <a href="?edit=<?= $p['project_id'] ?>" class="btn btn-edit btn-sm"><i data-lucide="edit" class="w-3 h-3"></i> Edit</a>
                                <a href="?delete=<?= $p['project_id'] ?>" onclick="return confirm('Delete this project?')" class="btn btn-delete btn-sm"><i data-lucide="trash-2" class="w-3 h-3"></i> Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        </div>
    </div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
