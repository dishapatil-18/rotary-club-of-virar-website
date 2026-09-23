<?php
// ===============================
// Member Management (Admin Only)
// ===============================

session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

require __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/audit_log.php';

$editMode = false;
$member_id = $name = $email = $phone = $address = $role = $status = $joined_date = "";

// ======================
// ================= CSV EXPORT FOR MEMBERS ==================
if (isset($_GET['export_members'])) {

    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=members_list.csv");

    $output = fopen("php://output", "w");
    fputcsv($output, ["Name", "Email", "Role", "Status", "Joined Date", "Profession", "Display Order", "Show Contact"]);

    $sql = "SELECT name, email, role, status, joined_date, profession, display_order, show_contact FROM members ORDER BY joined_date DESC";
    $res = $conn->query($sql);

    while ($row = $res->fetch_assoc()) {
        fputcsv($output, [
            $row['name'],
            $row['email'],
            $row['role'],
            $row['status'],
            $row['joined_date'],
            $row['profession'] ?? '',
            $row['display_order'] ?? 0,
            $row['show_contact'] ?? 0
        ]);
    }

    fclose($output);
    exit;
}
// ======================
// ADD / EDIT FORM SUBMIT
// ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $role = $_POST['role'];
    $status = $_POST['status'];
    $joined_date = $_POST['joined_date'];

    if ($name === "" || $email === "") {
        echo "<script>alert('Name and Email are required!'); window.history.back();</script>";
        exit;
    }

    // Add Member
    if (isset($_POST['add_member'])) {
        $stmt = $conn->prepare("INSERT INTO members (name, email, phone_number, address, role, status, joined_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $name, $email, $phone, $address, $role, $status, $joined_date);
        $stmt->execute();
        $stmt->close();
        logAudit($conn, 'Members', 'Member Added', 'Added member "' . $name . '".', 'INFO', 'success');
        echo "<script>alert('✅ Member added successfully!'); window.location.href='member_action.php';</script>";
        exit;
    }

    // Update Member
    if (isset($_POST['update_member']) && isset($_POST['member_id'])) {
        $id = intval($_POST['member_id']);
        $stmt = $conn->prepare("UPDATE members SET name=?, email=?, phone_number=?, address=?, role=?, status=?, joined_date=? WHERE member_id=?");
        $stmt->bind_param("sssssssi", $name, $email, $phone, $address, $role, $status, $joined_date, $id);
        $stmt->execute();
        $stmt->close();
        logAudit($conn, 'Members', 'Member Updated', 'Updated member "' . $name . '" (ID ' . $id . ').', 'INFO', 'success');
        echo "<script>alert('✅ Member updated successfully!'); window.location.href='member_action.php';</script>";
        exit;
    }
}

// ===================
// DELETE MEMBER
// ===================
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM members WHERE member_id=?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();
    logAudit($conn, 'Members', 'Member Deleted', 'Deleted member ID ' . $delete_id . '.', 'WARNING', 'success');
    echo "<script>alert('🗑️ Member deleted successfully!'); window.location.href='member_action.php';</script>";
    exit;
}

// ===================
// EDIT MEMBER (LOAD DATA)
// ===================
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
    }
    $stmt->close();
}

// ===================
// FETCH ALL MEMBERS
// ===================
$members = [];
$res = $conn->query("SELECT * FROM members ORDER BY joined_date DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $members[] = $row;
    }
}
?>
<?php
$pageTitle = 'Member List';
$activeNav = 'member-list';
require __DIR__ . '/includes/admin_head.php';
require __DIR__ . '/includes/admin_header.php';
?>
<div class="card">
    <div class="card-body">
        <div class="flex justify-end mb-4">
            <div>
                <a href="member_List.php?export_members=1"
                    class="btn btn-yellow btn-sm">
                    <i data-lucide="download" class="w-4 h-4"></i> Export CSV
                </a>
            </div>
        </div>
        <?php if (empty($members)): ?>
            <p class="text-gray-500">No members found.</p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th> </th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Profession</th>
                        <th>Order</th>
                        <th>Status</th>
                        <th>Joined Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $m): ?>
                        <tr>
                            <td><?= $m['member_id'] ?></td>
                            <td><?= htmlspecialchars($m['name']) ?></td>
                            <td><?= htmlspecialchars($m['email']) ?></td>
                            <td><?= htmlspecialchars($m['role']) ?></td>
                            <td><?= htmlspecialchars($m['profession'] ?? '') ?></td>
                            <td><?= intval($m['display_order'] ?? 0) ?></td>
                            <td><span class="badge <?= (strtolower($m['status']) === 'active') ? 'badge-green' : 'badge-red' ?>"><?= htmlspecialchars($m['status']) ?></span></td>
                            <td><?= htmlspecialchars($m['joined_date']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                        <tr>
                            <td colspan="9">
                            <div class="flex justify-end">
                                <button onclick="window.location.href = 'dashboard.php'" class="btn btn-ghost btn-sm">
                                    <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Dashboard
                                </button>
                            </div>
                            </td>
                        </tr>
                </tfoot>
            </table>
        <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>