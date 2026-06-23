<?php
// Admin/addEvent_poll.php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

require __DIR__ . '/../includes/db_connect.php';

$success = "";
$error = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_id = intval($_POST['event_id']);
    $poll_status = intval($_POST['poll_status']); // 1 = Enabled, 0 = Disabled

    $stmt = $conn->prepare("UPDATE events SET poll_enabled = ? WHERE event_id = ?");
    $stmt->bind_param("ii", $poll_status, $event_id);

    if ($stmt->execute()) {
        $success = "Event polling updated successfully!";
    } else {
        $error = "Failed to update polling status. Try again.";
    }

    $stmt->close();
}

// Fetch events list
$events = $conn->query("
    SELECT event_id, title, start_date, poll_enabled  
    FROM events 
    ORDER BY start_date ASC
");
?>
<?php
$pageTitle = 'Event Polling';
$activeNav = 'polls';
$pageSubtitle = 'Enable or disable polling for events';
require __DIR__ . '/includes/admin_head.php';
require __DIR__ . '/includes/admin_header.php';
?>
<div class="card">
  <div class="card-body">

    <!-- Success Message -->
    <?php if (!empty($success)): ?>
        <div class="mb-4 bg-green-100 text-green-700 border border-green-400 px-4 py-3 rounded-lg">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <!-- Error Message -->
    <?php if (!empty($error)): ?>
        <div class="mb-4 bg-red-100 text-red-700 border border-red-400 px-4 py-3 rounded-lg">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        
        <!-- Select Event -->
        <label class="form-label">Select Event</label>
        <select name="event_id" required
                class="form-input mb-4">
            <option value="">-- Choose Event --</option>

            <?php while ($row = $events->fetch_assoc()): ?>
                <option value="<?= $row['event_id'] ?>">
                    <?= htmlspecialchars($row['title']) ?> 
                    (<?= $row['start_date'] ?>)
                </option>
            <?php endwhile; ?>
        </select>

        <!-- Poll Status -->
        <label class="form-label"><i data-lucide="toggle-left" class="inline-block w-4 h-4 mr-1"></i> Polling Status</label>
        <select name="poll_status" required
                class="form-input mb-6">
            <option value="1">Enable Poll</option>
            <option value="0">Disable Poll</option>
        </select>

        <!-- Submit Button -->
        <button type="submit" class="btn btn-indigo w-full">
            <i data-lucide="toggle-right"></i> Save Changes
        </button>
    </form>

    <div class="mt-6 text-center">
        <a href="dashboard.php" class="btn btn-ghost btn-sm"><i data-lucide="arrow-left"></i> Back to Dashboard</a>
    </div>

</div></div>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
