<?php
// Admin/event_action.php
// ======================
// Event Management (Admin Only) - Add / Edit / Delete
// ======================

session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

// Include DB connection
require __DIR__ . '/../includes/db_connect.php';

// Init
$editMode = false;
$event_id = $title = $description = $start_date = $end_date = $image_url = $location = $category = $status = "";

// ---------- HANDLE POST: Add / Update Event ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_event']) || isset($_POST['update_event']))) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    $location = trim($_POST['location'] ?? '');
    $category = trim($_POST['category'] ?? '');

    if ($title === '' || $start_date === null) {
        echo "<script>alert('Please provide event title and start date.'); window.history.back();</script>";
        exit;
    }
    if ($end_date === null) $end_date = $start_date;

    // Handle image upload
    $uploadedImagePath = $_POST['existing_image'] ?? '';
    if (!empty($_FILES['image']['name'])) {
        $uploadDir = __DIR__ . '/../uploads/events/';
        if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

        $originalName = basename($_FILES['image']['name']);
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp','gif'];

        if (!in_array($ext, $allowed)) {
            echo "<script>alert('Invalid image type. Allowed: jpg,jpeg,png,webp,gif'); window.history.back();</script>";
            exit;
        }

        $newFile = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $target = $uploadDir . $newFile;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
            $uploadedImagePath = 'uploads/events/' . $newFile;
        } else {
            echo "<script>alert('Failed to move uploaded file.'); window.history.back();</script>";
            exit;
        }
    }

    // INSERT new event
    if (isset($_POST['add_event'])) {
        $stmt = $conn->prepare("INSERT INTO events (title, description, start_date, end_date, location, category, image_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $title, $description, $start_date, $end_date, $location, $category, $uploadedImagePath);

        if ($stmt->execute()) {
            $stmt->close();
            echo "<script>alert('✅ Event added successfully'); window.location.href='event_action.php';</script>";
            exit;
        } else {
            $err = $stmt->error;
            $stmt->close();
            echo "<script>alert('DB error: ". addslashes($err) ."'); window.history.back();</script>";
            exit;
        }
    }

    // UPDATE event
    if (isset($_POST['update_event']) && !empty($_POST['event_id'])) {
        $eid = intval($_POST['event_id']);
        $stmt = $conn->prepare("UPDATE events SET title=?, description=?, start_date=?, end_date=?, image_url=?, location=?, category=? WHERE event_id=?");
        $stmt->bind_param("sssssssi", $title, $description, $start_date, $end_date, $uploadedImagePath, $location, $category, $eid);

        if ($stmt->execute()) {
            $stmt->close();
            echo "<script>alert('✅ Event updated successfully'); window.location.href='event_action.php';</script>";
            exit;
        } else {
            $err = $stmt->error;
            $stmt->close();
            echo "<script>alert('DB error: ". addslashes($err) ."'); window.history.back();</script>";
            exit;
        }
    }
}

// ---------- HANDLE DELETE ----------
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);

    $stmt = $conn->prepare("SELECT image_url FROM events WHERE event_id = ?");
    $stmt->bind_param("i", $del_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $img = null;

    if ($res && $row = $res->fetch_assoc()) $img = $row['image_url'];
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM events WHERE event_id = ?");
    $stmt->bind_param("i", $del_id);

    if ($stmt->execute()) {
        $stmt->close();

        if ($img && file_exists(__DIR__ . '/../' . $img)) {
            @unlink(__DIR__ . '/../' . $img);
        }

        echo "<script>alert('Event deleted'); window.location.href='event_action.php';</script>";
        exit;
    } else {
        $err = $stmt->error;
        $stmt->close();
        echo "<script>alert('DB error: ". addslashes($err) ."'); window.history.back();</script>";
        exit;
    }
}

// ---------- LOAD for EDIT ----------
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);

    $stmt = $conn->prepare("SELECT * FROM events WHERE event_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows === 1) {
        $editMode = true;
        $row = $res->fetch_assoc();
        $event_id = $row['event_id'];
        $title = $row['title'];
        $description = $row['description'];
        $start_date = $row['start_date'];
        $end_date = $row['end_date'];
        $image_url = $row['image_url'];
        $location = $row['location'];
        $category = $row['category'];
    }
    $stmt->close();
}

// ---------- FETCH ALL EVENTS ----------
$events = [];
$res = $conn->query("SELECT *, CASE WHEN end_date < CURDATE() THEN 'Completed' WHEN start_date <= CURDATE() AND end_date >= CURDATE() THEN 'Ongoing' ELSE 'Upcoming' END AS computed_status FROM events ORDER BY start_date DESC");
if ($res) {
    while ($r = $res->fetch_assoc()) $events[] = $r;
}
?>
<?php
$pageTitle = 'Manage Events';
$activeNav = 'events';
require __DIR__ . '/includes/admin_head.php';
require __DIR__ . '/includes/admin_header.php';
?>
<!-- Event Form Card -->
<div class="card">
    <div class="card-body">
        <h2 class="text-xl font-bold text-gray-800 mb-6"><?= $editMode ? 'Edit Event' : 'Add New Event' ?></h2>

    <form method="POST" enctype="multipart/form-data" class="space-y-4">
        <?php if ($editMode): ?>
            <input type="hidden" name="event_id" value="<?= intval($event_id) ?>">
            <input type="hidden" name="existing_image" value="<?= htmlspecialchars($image_url) ?>">
        <?php endif; ?>

        <div>
            <label class="form-label">Event Title</label>
            <input type="text" name="title" required value="<?= htmlspecialchars($title) ?>" class="form-input" />
        </div>

        <div>
            <label class="form-label">Description</label>
            <textarea name="description" rows="3" required class="form-textarea"><?= htmlspecialchars($description) ?></textarea>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="form-label">Start Date</label>
                <input type="date" name="start_date" required value="<?= htmlspecialchars($start_date) ?>" class="form-input" />
            </div>
            <div>
                <label class="form-label">End Date</label>
                <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?: htmlspecialchars($start_date) ?>" class="form-input" />
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="form-label">Location</label>
                <input type="text" name="location" value="<?= htmlspecialchars($location) ?>" class="form-input" />
            </div>
            <div>
                <label class="form-label">Category</label>
                <select name="category" class="form-select">
                    <option value="">-- Select Category --</option>
                    <option value="Health Camps" <?= $category === 'Health Camps' ? 'selected' : '' ?>>Health Camps</option>
                    <option value="Environment" <?= $category === 'Environment' ? 'selected' : '' ?>>Environment</option>
                    <option value="Community Service" <?= $category === 'Community Service' ? 'selected' : '' ?>>Community Service</option>
                    <option value="Youth Development" <?= $category === 'Youth Development' ? 'selected' : '' ?>>Youth Development</option>
                    <option value="Training & Education" <?= $category === 'Training & Education' ? 'selected' : '' ?>>Training & Education</option>
                    <option value="Fellowship" <?= $category === 'Fellowship' ? 'selected' : '' ?>>Fellowship</option>
                </select>
            </div>
        </div>

        <div>
            <label class="form-label">Status (auto-calculated)</label>
            <?php
                $today = date('Y-m-d');
                if ($start_date !== '' && $end_date !== '') {
                    if ($end_date < $today) $cs = 'Completed';
                    elseif ($start_date <= $today && $end_date >= $today) $cs = 'Ongoing';
                    else $cs = 'Upcoming';
                } else {
                    $cs = 'Upcoming';
                }
            ?>
            <span class="badge <?= $cs === 'Completed' ? 'badge-red' : ($cs === 'Ongoing' ? 'badge-yellow' : 'badge-green') ?>" style="display:inline-block;padding:4px 14px;border-radius:20px;font-size:0.85rem;font-weight:600;">
                <?= $cs ?>
            </span>
            <small class="text-gray-500 ml-2">(computed from dates)</small>
        </div>

            <div>
                <label class="form-label">Cover Image</label>
                <input type="file" name="image" accept="image/*" class="form-input" />
                <p class="text-xs text-gray-500 mt-1">This image will appear as the Cover Image on the event report page.</p>
                <?php if ($editMode && $image_url): ?>
                    <img src="../<?= htmlspecialchars($image_url) ?>" class="mt-3 h-28 rounded-lg shadow" alt="event image" />
                <?php endif; ?>
            </div>
            <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-3 text-sm text-indigo-700">
                <i data-lucide="info" class="w-4 h-4 inline mr-1"></i>
                <strong>Additional Photos:</strong> Upload more images for this event via
                <a href="admin_add_media.php" class="font-semibold underline">Media Gallery</a>
                (select "Event" category and choose this event).
            </div>

        <div class="flex justify-end space-x-3">
            <button onclick="window.location.href='dashboard.php'" class="btn btn-secondary"><i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Dashboard</button>
            <a href="event_action.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" name="<?= $editMode ? 'update_event' : 'add_event' ?>" class="btn btn-primary">
                <i data-lucide="save" class="w-4 h-4"></i> <?= $editMode ? 'Update Event' : 'Add Event' ?>
            </button>
        </div>
    </form>
    </div>
</div>

<!-- All Events List -->
<div class="card">
    <div class="card-body">
        <h2 class="text-xl font-bold text-gray-800 mb-4">All Events</h2>

    <?php if (empty($events)): ?>
        <p class="text-gray-500">No events found.</p>
    <?php else: ?>
        <table class="data-table">
            <thead>
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $e): 
                    $cs = $e['computed_status'] ?? 'Upcoming';
                ?>
                    <tr>
                        <td class="text-center"><?= intval($e['event_id']) ?></td>
                        <td><?= htmlspecialchars($e['title']) ?></td>
                        <td><?= htmlspecialchars($e['start_date']) ?></td>
                        <td><?= htmlspecialchars($e['end_date']) ?></td>
                        <td><?= htmlspecialchars($e['location']) ?></td>
                        <td>
                            <?php if ($cs === 'Upcoming'): ?>
                                <span class="badge badge-green">Upcoming</span>
                            <?php elseif ($cs === 'Ongoing'): ?>
                                <span class="badge" style="background:#f59e0b;color:#fff;">Ongoing</span>
                            <?php else: ?>
                                <span class="badge badge-red">Completed</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center space-x-2">
                            <a href="?edit=<?= intval($e['event_id']) ?>" class="btn btn-edit btn-sm"><i data-lucide="edit" class="w-3 h-3"></i> Edit</a>
                            <a href="?delete=<?= intval($e['event_id']) ?>" onclick="return confirm('Delete this event?')" class="btn btn-delete btn-sm"><i data-lucide="trash-2" class="w-3 h-3"></i> Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
