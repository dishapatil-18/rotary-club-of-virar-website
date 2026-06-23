<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}
require __DIR__ . '/../includes/db_connect.php';

$editMode = false;
$member_id = $name = $email = $phone = $address = $role = $status = $joined_date = $photo_url = $profession = $short_bio = "";
$display_order = 0;
$show_contact = 0;

// Handle image upload helper
function handleMemberImageUpload($fieldName, $existingPath = '') {
    if (empty($_FILES[$fieldName]['name'])) return $existingPath;

    $uploadDir = __DIR__ . '/../uploads/members/';
    if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

    $ext = strtolower(pathinfo($_FILES[$fieldName]['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowed)) return $existingPath;

    $newName = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (move_uploaded_file($_FILES[$fieldName]['tmp_name'], $uploadDir . $newName)) {
        return 'uploads/members/' . $newName;
    }
    return $existingPath;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $role = $_POST['role'];
    $status = $_POST['status'];
    $joined_date = $_POST['joined_date'];
    $profession = trim($_POST['profession'] ?? '');
    $short_bio = trim($_POST['short_bio'] ?? '');
    $display_order = intval($_POST['display_order'] ?? 0);
    $show_contact = isset($_POST['show_contact']) ? 1 : 0;

    if ($name === "" || $email === "") {
        echo "<script>alert('Name and Email are required!'); window.history.back();</script>";
        exit;
    }

    if (isset($_POST['add_member'])) {
        $photo_url = handleMemberImageUpload('image', '');
        $stmt = $conn->prepare("INSERT INTO members (name, email, phone_number, address, role, status, joined_date, photo_url, profession, short_bio, display_order, show_contact) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssssssii", $name, $email, $phone, $address, $role, $status, $joined_date, $photo_url, $profession, $short_bio, $display_order, $show_contact);
        $stmt->execute();
        $stmt->close();
        echo "<script>alert('Member added successfully!'); window.location.href='member_action.php';</script>";
        exit;
    }

    if (isset($_POST['update_member']) && isset($_POST['member_id'])) {
        $id = intval($_POST['member_id']);
        $photo_url = handleMemberImageUpload('image', $_POST['existing_photo'] ?? '');
        $stmt = $conn->prepare("UPDATE members SET name=?, email=?, phone_number=?, address=?, role=?, status=?, joined_date=?, photo_url=?, profession=?, short_bio=?, display_order=?, show_contact=? WHERE member_id=?");
        $stmt->bind_param("ssssssssssiii", $name, $email, $phone, $address, $role, $status, $joined_date, $photo_url, $profession, $short_bio, $display_order, $show_contact, $id);
        $stmt->execute();
        $stmt->close();
        echo "<script>alert('Member updated successfully!'); window.location.href='member_action.php';</script>";
        exit;
    }
}

if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM members WHERE member_id=?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();
    echo "<script>alert('Member deleted successfully!'); window.location.href='member_action.php';</script>";
    exit;
}

if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM members WHERE member_id=?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows === 1) {
        $editMode = true;
        $row = $result->fetch_assoc();
        $member_id = $row['member_id'];
        $name = $row['name'];
        $email = $row['email'];
        $phone = $row['phone_number'];
        $address = $row['address'];
        $role = $row['role'];
        $status = $row['status'];
        $joined_date = $row['joined_date'];
        $photo_url = $row['photo_url'];
        $profession = $row['profession'] ?? '';
        $short_bio = $row['short_bio'] ?? '';
        $display_order = intval($row['display_order'] ?? 0);
        $show_contact = intval($row['show_contact'] ?? 0);
    }
    $stmt->close();
}

$members = [];
$res = $conn->query("SELECT * FROM members ORDER BY display_order ASC, name ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) $members[] = $row;
}
?>
<?php
$pageTitle = 'Manage Members';
$activeNav = 'members';
require __DIR__ . '/includes/admin_head.php';
require __DIR__ . '/includes/admin_header.php';
?>

<div class="space-y-6">
    <!-- Add/Edit Member Form -->
    <div class="card">
        <div class="card-body">
            <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-2">
                <i data-lucide="<?= $editMode ? 'user-check' : 'user-plus' ?>" class="w-5 h-5 text-green-500"></i>
                <?= $editMode ? "Edit Member" : "Add New Member" ?>
            </h2>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <?php if ($editMode): ?>
                <input type="hidden" name="member_id" value="<?= $member_id ?>">
                <input type="hidden" name="existing_photo" value="<?= htmlspecialchars($photo_url) ?>">
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($name) ?>" required class="form-input">
                </div>
                <div>
                    <label class="form-label">Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required class="form-input">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($phone) ?>" class="form-input">
                </div>
                <div>
                    <label class="form-label">Joined Date</label>
                    <input type="date" name="joined_date" value="<?= htmlspecialchars($joined_date) ?>" class="form-input">
                </div>
            </div>

            <div>
                <label class="form-label">Address</label>
                <textarea name="address" rows="2" class="form-textarea"><?= htmlspecialchars($address) ?></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="form-label">Role</label>
                    <select name="role" required class="form-select">
                        <option value="President" <?= $role === 'President' ? 'selected' : '' ?>>President</option>
                        <option value="Secretary" <?= $role === 'Secretary' ? 'selected' : '' ?>>Secretary</option>
                        <option value="Treasurer" <?= $role === 'Treasurer' ? 'selected' : '' ?>>Treasurer</option>
                        <option value="Member" <?= $role === 'Member' ? 'selected' : '' ?>>Member</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option>
                        <option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Photo</label>
                    <input type="file" name="image" accept="image/*" class="form-input">
                    <?php if ($editMode && $photo_url): ?>
                        <img src="../<?= htmlspecialchars($photo_url) ?>" class="mt-2 h-16 w-16 rounded-full object-cover border-2 border-green-200">
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Profession</label>
                    <input type="text" name="profession" value="<?= htmlspecialchars($profession) ?>" class="form-input" placeholder="e.g. Social Entrepreneur">
                </div>
                <div>
                    <label class="form-label">Display Order</label>
                    <input type="number" name="display_order" value="<?= $display_order ?>" class="form-input" min="0">
                </div>
            </div>

            <div>
                <label class="form-label">Short Bio</label>
                <textarea name="short_bio" rows="3" class="form-textarea" placeholder="Brief description about the member..."><?= htmlspecialchars($short_bio) ?></textarea>
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" name="show_contact" id="show_contact" value="1" <?= $show_contact ? 'checked' : '' ?> class="form-checkbox h-5 w-5 text-green-500">
                <label for="show_contact" class="form-label !mb-0">Show phone &amp; email on public team page</label>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                <a href="member_action.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" name="<?= $editMode ? 'update_member' : 'add_member' ?>" class="btn btn-primary">
                    <?= $editMode ? 'Update Member' : 'Add Member' ?>
                </button>
            </div>
        </form>
        </div>
    </div>

    <!-- Member List -->
    <div class="card">
        <div class="card-body">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
            <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i data-lucide="users" class="w-5 h-5 text-blue-500"></i>
                All Members (<?= count($members) ?>)
            </h2>
            <a href="member_List.php?export_members=1" class="btn btn-yellow btn-sm">
                <i data-lucide="download" class="w-3.5 h-3.5"></i> Export CSV
            </a>
        </div>

        <?php if (empty($members)): ?>
            <p class="text-gray-500 text-sm text-center py-8">No members found.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50">
                            <th class="p-3 text-left font-semibold text-gray-600 text-xs uppercase">#</th>
                            <th class="p-3 text-left font-semibold text-gray-600 text-xs uppercase">Name</th>
                            <th class="p-3 text-left font-semibold text-gray-600 text-xs uppercase hidden md:table-cell">Email</th>
                            <th class="p-3 text-left font-semibold text-gray-600 text-xs uppercase">Role</th>
                            <th class="p-3 text-left font-semibold text-gray-600 text-xs uppercase hidden sm:table-cell">Status</th>
                            <th class="p-3 text-left font-semibold text-gray-600 text-xs uppercase hidden lg:table-cell">Joined</th>
                            <th class="p-3 text-center font-semibold text-gray-600 text-xs uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $m): ?>
                        <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                            <td class="p-3 text-gray-500"><?= $m['member_id'] ?></td>
                            <td class="p-3 font-medium text-gray-800"><?= htmlspecialchars($m['name']) ?></td>
                            <td class="p-3 text-gray-600 hidden md:table-cell"><?= htmlspecialchars($m['email']) ?></td>
                            <td class="p-3"><?= htmlspecialchars($m['role']) ?></td>
                            <td class="p-3 hidden sm:table-cell">
                                <span class="badge <?= $m['status'] === 'Active' ? 'badge-green' : 'badge-red' ?>"><?= htmlspecialchars($m['status']) ?></span>
                            </td>
                            <td class="p-3 text-gray-500 text-xs hidden lg:table-cell"><?= htmlspecialchars($m['joined_date']) ?></td>
                            <td class="p-3 text-center space-x-1.5">
                                <a href="?edit=<?= $m['member_id'] ?>" class="btn btn-edit btn-xs">Edit</a>
                                <a href="?delete=<?= $m['member_id'] ?>" onclick="return confirm('Delete this member?')" class="btn btn-delete btn-xs">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
