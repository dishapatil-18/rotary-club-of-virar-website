<?php
//-----------------------------------------------
// donation_action.php
// Donations & Donors Management Panel
//-----------------------------------------------

session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

require __DIR__ . '/../includes/db_connect.php';
require __DIR__ . '/../includes/send_email.php';

// We'll still accept ?tab=donations or ?tab=donors for initial render
$tab = $_GET['tab'] ?? 'donations';  // Default tab

// ---------------------------------------------
// ADD / UPDATE DONATION
// ---------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_donation'])) {
    $donation_id = intval($_POST['donation_id'] ?? 0);
    $donor_id = intval($_POST['donor_id'] ?? 0);
    $donation_type = trim($_POST['donation_type'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $amount = $_POST['amount'] !== '' ? floatval($_POST['amount']) : null;
    $screenshot_path = $_POST['existing_screenshot'] ?? '';
    $status = trim($_POST['status'] ?? 'Pending Verification');
    $pickup_option_raw = trim($_POST['pickup_option'] ?? '');
    $utr_number = trim($_POST['utr_number'] ?? '');

    if ($donor_id <= 0 || $donation_type === '') {
        echo "<script>alert('Please select donor and donation type.'); window.history.back();</script>";
        exit;
    }

    // --------- HANDLE FILE UPLOAD ---------
    if (!empty($_FILES['screenshot']['name'])) {
        $dir = __DIR__ . '/../uploads/donations/';
        if (!file_exists($dir)) mkdir($dir, 0777, true);

        $ext = strtolower(pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp','gif'];

        if (!in_array($ext, $allowed)) {
            echo "<script>alert('Invalid screenshot format.'); window.history.back();</script>";
            exit;
        }

        $filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $finalPath = $dir . $filename;

        if (move_uploaded_file($_FILES['screenshot']['tmp_name'], $finalPath)) {
            $screenshot_path = 'uploads/donations/' . $filename;
        }
    }

    // --------- INSERT NEW DONATION ---------
    if ($donation_id === 0) {
        $pickup_json = $pickup_option_raw !== '' ? $pickup_option_raw : '{"option":"","address":"","date":"","notes":""}';
        $stmt = $conn->prepare("INSERT INTO donations (donor_id, donation_type, description, amount, utr_number, screenshot_path, pickup_option, status, date) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("issdssss", $donor_id, $donation_type, $description, $amount, $utr_number, $screenshot_path, $pickup_json, $status);
        $stmt->execute();
        $stmt->close();
        echo "<script>alert('Donation added successfully'); window.location='donation_action.php?tab=donations';</script>";
        exit;
    }

    // --------- UPDATE DONATION ---------
    else {
        // Fetch current donation status and donor info before updating
        $oldStatus = '';
        $donorEmail = '';
        $donorName = '';
        $prev = $conn->prepare("SELECT d.status, dn.email, dn.name FROM donations d JOIN donors dn ON d.donor_id = dn.donor_id WHERE d.donation_id = ?");
        $prev->bind_param("i", $donation_id);
        $prev->execute();
        $prevRes = $prev->get_result();
        if ($r = $prevRes->fetch_assoc()) {
            $oldStatus = $r['status'];
            $donorEmail = $r['email'];
            $donorName = $r['name'];
        }
        $prev->close();

        $pickup_json = $pickup_option_raw !== '' ? $pickup_option_raw : '{"option":"","address":"","date":"","notes":""}';
        $adminName = $_SESSION['admin_name'] ?? 'Admin';
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE donations 
                                SET donor_id=?, donation_type=?, description=?, amount=?, utr_number=?, screenshot_path=?, pickup_option=?, status=?, status_updated_at=?, status_updated_by=? 
                                WHERE donation_id=?");
        $stmt->bind_param("issdssssssi", $donor_id, $donation_type, $description, $amount, $utr_number, $screenshot_path, $pickup_json, $status, $now, $adminName, $donation_id);
        $stmt->execute();
        $stmt->close();

        // Send email if status changed and donor has email
        $emailNote = '';
        if ($oldStatus !== $status && $donorEmail !== '') {
            $donorTemplateMap = [
                'Verified' => ['file' => 'donation_verified.php', 'func' => 'getDonationVerifiedContent'],
                'Contacted' => ['file' => 'donation_contacted.php', 'func' => 'getDonationContactedContent'],
                'Received' => ['file' => 'donation_received.php', 'func' => 'getDonationReceivedContent'],
                'Completed' => ['file' => 'donation_completed.php', 'func' => 'getDonationCompletedContent'],
            ];
            if (isset($donorTemplateMap[$status])) {
                $tmpl = $donorTemplateMap[$status];
                require_once __DIR__ . '/../includes/email_templates/' . $tmpl['file'];
                $content = $tmpl['func']($donorName);
                $mailResult = sendEmail($donorEmail, $content['subject'], $content['body']);
                if (!$mailResult['success']) {
                    $emailNote = ' (Email notification failed)';
                } else {
                    $emailNote = ' (Email sent)';
                }
            }
        }

        echo "<script>alert('Donation updated successfully!" . ($emailNote ?? '') . "'); window.location='donation_action.php?tab=donations';</script>";
        exit;
    }
}

// ---------------------------------------------
// DELETE DONATION
// ---------------------------------------------
if (isset($_GET['delete_donation'])) {
    $id = intval($_GET['delete_donation']);
    // optionally remove screenshot file here (not mandatory)
    $stmt = $conn->prepare("DELETE FROM donations WHERE donation_id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    echo "<script>alert('Donation deleted'); window.location='donation_action.php?tab=donations';</script>";
    exit;
}

// ---------------------------------------------
// ADD / UPDATE DONOR
// ---------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_donor'])) {
    $donor_id = intval($_POST['donor_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = trim($_POST['social_role'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $occupation = trim($_POST['occupation'] ?? '');

    if ($name === '') {
        echo "<script>alert('Name is required.'); window.history.back();</script>";
        exit;
    }

    // Add new donor
    if ($donor_id === 0) {
        $stmt = $conn->prepare("INSERT INTO donors (name, phone_number, email, social_role, address, occupation, created_at) 
                                VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssss", $name, $phone, $email, $role, $address, $occupation);
        $stmt->execute();
        $stmt->close();
        echo "<script>alert('Donor added'); window.location='donation_action.php?tab=donors';</script>";
        exit;
    }

    // Update donor
    else {
        $stmt = $conn->prepare("UPDATE donors SET name=?, phone_number=?, email=?, social_role=?, address=?, occupation=? WHERE donor_id=?");
        $stmt->bind_param("ssssssi", $name, $phone, $email, $role, $address, $occupation, $donor_id);
        $stmt->execute();
        $stmt->close();
        echo "<script>alert('Donor updated'); window.location='donation_action.php?tab=donors';</script>";
        exit;
    }
}

// ---------------------------------------------
// DELETE DONOR
// ---------------------------------------------
if (isset($_GET['delete_donor'])) {
    $id = intval($_GET['delete_donor']);
    $stmt = $conn->prepare("DELETE FROM donors WHERE donor_id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    echo "<script>alert('Donor deleted'); window.location='donation_action.php?tab=donors';</script>";
    exit;
}

// ---------------------------------------------
// FETCH DATA
// ---------------------------------------------
$donorsList = [];
$res1 = $conn->query("SELECT * FROM donors ORDER BY donor_id DESC");
while ($d = $res1->fetch_assoc()) $donorsList[] = $d;

$donationsList = [];
$res2 = $conn->query("
    SELECT dn.name AS donor_name, d.* 
    FROM donations d 
    JOIN donors dn ON d.donor_id = dn.donor_id
    ORDER BY d.date DESC
");
while ($d = $res2->fetch_assoc()) $donationsList[] = $d;

// For editing (if query param present)
$editDonation = null;
if (isset($_GET['edit_donation'])) {
    foreach ($donationsList as $dd) {
        if ($dd['donation_id'] == $_GET['edit_donation']) $editDonation = $dd;
    }
}

$editDonor = null;
if (isset($_GET['edit_donor'])) {
    foreach ($donorsList as $dd) {
        if ($dd['donor_id'] == $_GET['edit_donor']) $editDonor = $dd;
    }
}
$pageTitle = 'Manage Donations';
$activeNav = 'donations';
require __DIR__ . '/includes/admin_head.php';
require __DIR__ . '/includes/admin_header.php';
?>

<div class="card"><div class="card-body">

    <!-- TAB SWITCH (JS will switch without reload) -->
    <div class="flex space-x-4 mb-6">
        <button id="tab-donations-btn" class="btn <?= $tab=='donations' ? 'btn-primary' : 'btn-ghost' ?>"><i data-lucide="heart-handshake" class="w-4 h-4"></i> Add New Donation</button>
        <button id="tab-donors-btn" class="btn <?= $tab=='donors' ? 'btn-primary' : 'btn-ghost' ?>"><i data-lucide="user-plus" class="w-4 h-4"></i> Add New Donor</button>
    </div>

    <!-- ------------------------------ -->
    <!-- SECTION: DONATIONS (rendered, visibility controlled by JS) -->
    <!-- ------------------------------ -->
    <div id="tab-donations" class="<?= $tab=='donations' ? '' : 'hidden' ?>">

        <h2 class="text-2xl font-bold mb-4"><?= $editDonation ? "Edit Donation" : "Add New Donation" ?></h2>

        <!-- Add/Edit Donation Form -->
        <form method="POST" enctype="multipart/form-data" class="mb-8 space-y-4 bg-gray-50 p-6 rounded-lg border">
            <input type="hidden" name="donation_id" value="<?= $editDonation['donation_id'] ?? 0 ?>">
            <input type="hidden" name="existing_screenshot" value="<?= htmlspecialchars($editDonation['screenshot_path'] ?? '') ?>">

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="form-label">Donor</label>
                    <select name="donor_id" required class="form-input">
                        <option value="">Select Donor</option>
                        <?php foreach ($donorsList as $d): ?>
                            <option value="<?= $d['donor_id'] ?>"
                                <?= isset($editDonation) && $editDonation['donor_id']==$d['donor_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label">Donation Type</label>
                    <select id="donation_type" name="donation_type" required class="form-input">
                        <?php
                        $types = ['Monetary Donation','School Supplies','Food & Grocery','Clothes & Blankets','Medical Support','Volunteer Support','Other'];
                        $selectedType = $editDonation['donation_type'] ?? '';
                        ?>
                        <option value="">Select Type</option>
                        <?php foreach($types as $t): ?>
                            <option value="<?= $t ?>" <?= $selectedType===$t ? 'selected' : '' ?>><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-input" value="<?= htmlspecialchars($editDonation['description'] ?? '') ?>">
                </div>
            </div>

            <!-- Conditional money fields -->
            <div id="money-fields" class="mt-2 <?= (isset($editDonation) && (stripos($editDonation['donation_type'], 'Monetary') !== false)) ? '' : 'hidden' ?>">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="form-label">Amount (₹)</label>
                        <input type="number" step="0.01" name="amount" class="form-input" value="<?= $editDonation['amount'] ?? '' ?>">
                    </div>
                    <div>
                        <label class="form-label">UTR Number</label>
                        <input type="text" name="utr_number" class="form-input" value="<?= htmlspecialchars($editDonation['utr_number'] ?? '') ?>">
                    </div>
                    <div class="md:col-span-2">
                        <label class="form-label">Screenshot (receipt / proof)</label>
                        <input type="file" name="screenshot" class="form-input">
                        <?php if ($editDonation && $editDonation['screenshot_path']): ?>
                            <img src="../<?= htmlspecialchars($editDonation['screenshot_path']) ?>" class="mt-2 h-24 rounded shadow-sm">
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Status field -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div>
                    <label class="form-label">Status</label>
                    <select name="status" class="form-input">
                        <?php
                        $statuses = ['Pending Verification','Verified','Contacted','Received','Completed'];
                        $curStatus = $editDonation['status'] ?? 'Pending Verification';
                        ?>
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s ?>" <?= $curStatus === $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Pickup/Delivery Info</label>
                    <?php
                    $pickupDisplay = '—';
                    if (isset($editDonation['pickup_option']) && $editDonation['pickup_option'] !== '') {
                        $po = json_decode($editDonation['pickup_option'], true);
                        if ($po && isset($po['option'])) {
                            $optLabel = $po['option'] === 'deliver' ? 'Self Delivery' : ($po['option'] === 'pickup' ? 'Arrange Pickup' : 'N/A');
                            $pickupDisplay = $optLabel;
                            if ($po['option'] === 'pickup' && !empty($po['address'])) {
                                $pickupDisplay .= ' — ' . $po['address'];
                            }
                        }
                    }
                    ?>
                    <input type="text" readonly class="form-input bg-gray-100 text-gray-600" value="<?= htmlspecialchars($pickupDisplay) ?>">
                </div>
            </div>

            <div class="flex justify-end space-x-3 mt-4">
                <a href="dashboard.php" class="btn btn-secondary"><i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Dashboard</a>
                <a href="donation_action.php?tab=donations" class="btn btn-secondary">Cancel</a>
                <button type="submit" name="save_donation" class="btn btn-danger"><i data-lucide="save" class="w-4 h-4"></i>
                    <?= $editDonation ? 'Update Donation' : 'Add Donation' ?>
                </button>
            </div>
        </form>

        <!-- Donation Table -->
        <h3 class="text-xl font-bold mb-4">All Donations</h3>

        <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Donor</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Amount</th>
                    <th>UTR</th>
                    <th>Proof</th>
                    <th>Pickup/Delivery</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>

            <tbody>
                <?php if (count($donationsList) === 0): ?>
                    <tr><td colspan="11" class="p-4 text-center text-gray-500">No donations yet.</td></tr>
                <?php else: ?>
                <?php foreach ($donationsList as $d): ?>
                <?php
                    $pickupLabel = '—';
                    if (!empty($d['pickup_option'])) {
                        $po = json_decode($d['pickup_option'], true);
                        if ($po && isset($po['option'])) {
                            $pickupLabel = $po['option'] === 'deliver' ? 'Self Delivery' : ($po['option'] === 'pickup' ? 'Pickup' : '—');
                        }
                    }
                    $statusColor = 'badge-gray';
                    if ($d['status'] === 'Pending Verification') $statusColor = 'badge-yellow';
                    elseif ($d['status'] === 'Verified') $statusColor = 'badge-green';
                    elseif ($d['status'] === 'Contacted') $statusColor = 'badge-blue';
                    elseif ($d['status'] === 'Received') $statusColor = 'badge-purple';
                    elseif ($d['status'] === 'Completed') $statusColor = 'badge-green';
                ?>
                <tr>
                    <td><?= $d['donation_id'] ?></td>
                    <td><?= htmlspecialchars($d['donor_name']) ?></td>
                    <td><?= htmlspecialchars($d['donation_type']) ?></td>
                    <td class="max-w-[150px] truncate" title="<?= htmlspecialchars($d['description'] ?? '') ?>"><?= htmlspecialchars($d['description'] ?? '—') ?></td>
                    <td><?= $d['amount'] !== null ? '₹ '.number_format($d['amount'],2) : '—' ?></td>
                    <td><?= htmlspecialchars($d['utr_number'] ?? '—') ?></td>
                    <td>
                        <?php if ($d['screenshot_path']): ?>
                            <a href="../<?= htmlspecialchars($d['screenshot_path']) ?>" target="_blank">
                                <img src="../<?= htmlspecialchars($d['screenshot_path']) ?>" class="h-10 rounded shadow-sm">
                            </a>
                        <?php else: ?> — <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($pickupLabel) ?></td>
                    <td><span class="badge <?= $statusColor ?>"><?= htmlspecialchars($d['status'] ?? 'Pending Verification') ?></span></td>
                    <td><?= $d['date'] ?></td>
                    <td class="text-center">
                        <a href="?tab=donations&edit_donation=<?= $d['donation_id'] ?>"
                           class="btn btn-edit btn-xs"><i data-lucide="edit" class="w-3 h-3"></i> Edit</a>
                        <a href="?tab=donations&delete_donation=<?= $d['donation_id'] ?>"
                           onclick="return confirm('Delete this donation?')"
                           class="btn btn-delete btn-xs"><i data-lucide="trash-2" class="w-3 h-3"></i> Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div>

    </div>

    <!-- ------------------------------ -->
    <!-- SECTION: DONORS (rendered, visibility controlled by JS) -->
    <!-- ------------------------------ -->
    <div id="tab-donors" class="<?= $tab=='donors' ? '' : 'hidden' ?> mt-6">

        <h2 class="text-2xl font-bold mb-4"><?= $editDonor ? "Edit Donor" : "Add New Donor" ?></h2>

        <!-- Add/Edit Donor Form -->
        <form method="POST" class="mb-8 grid grid-cols-1 md:grid-cols-2 gap-4 bg-gray-50 p-6 rounded-lg border">
            <input type="hidden" name="donor_id" value="<?= $editDonor['donor_id'] ?? 0 ?>">

    <div>
        <label class="form-label">Name</label>
        <input type="text" name="name" required class="form-input" value="<?= htmlspecialchars($editDonor['name'] ?? '') ?>">
    </div>

    <div>
        <label class="form-label">Phone</label>
        <input type="text" name="phone_number" class="form-input" value="<?= htmlspecialchars($editDonor['phone_number'] ?? '') ?>">
    </div>

    <div>
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($editDonor['email'] ?? '') ?>">
    </div>

    <div>
        <label class="form-label">Address</label>
        <textarea name="address" rows="2" class="form-input"><?= htmlspecialchars($editDonor['address'] ?? '') ?></textarea>
    </div>

    <div>
        <label class="form-label">Occupation</label>
        <input type="text" name="occupation" class="form-input" value="<?= htmlspecialchars($editDonor['occupation'] ?? '') ?>">
    </div>

    <div>
        <label class="form-label">Social Role</label>
        <input type="text" name="social_role" class="form-input" value="<?= htmlspecialchars($editDonor['social_role'] ?? '') ?>">
    </div>

    <div class="md:col-span-2 text-right">
        <a href="donation_action.php?tab=donors" class="btn btn-secondary">Cancel</a>
        <button type="submit" name="save_donor" class="btn btn-danger"><i data-lucide="save" class="w-4 h-4"></i>
            <?= $editDonor ? 'Update Donor' : 'Save Donor' ?>
        </button>
    </div>
        </form>

        <!-- Donors Table -->
        <h3 class="text-xl font-bold mb-4">All Donors</h3>

        <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Address</th>
                    <th>Occupation</th>
                    <th>Joined</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>

            <tbody>
                <?php if (count($donorsList) === 0): ?>
                    <tr><td colspan="8" class="p-4 text-center text-gray-500">No donors yet.</td></tr>
                <?php else: ?>
                <?php foreach ($donorsList as $d): ?>
                <tr>
                    <td><?= $d['donor_id'] ?></td>
                    <td><?= htmlspecialchars($d['name']) ?></td>
                    <td><?= htmlspecialchars($d['phone_number']) ?></td>
                    <td><?= htmlspecialchars($d['email']) ?></td>
                    <td class="max-w-[150px] truncate" title="<?= htmlspecialchars($d['address'] ?? '') ?>"><?= htmlspecialchars($d['address'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($d['occupation'] ?? ($d['social_role'] ?? '—')) ?></td>
                    <td><?= $d['created_at'] ?></td>
                    <td class="text-center">
                        <a href="?tab=donors&edit_donor=<?= $d['donor_id'] ?>" class="btn btn-edit btn-xs"><i data-lucide="edit" class="w-3 h-3"></i> Edit</a>
                        <a href="?tab=donors&delete_donor=<?= $d['donor_id'] ?>" onclick="return confirm('Delete this donor?')" class="btn btn-delete btn-xs"><i data-lucide="trash-2" class="w-3 h-3"></i> Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div>

    </div>

</div></div>

<script>
    // Smooth tab switching without full reload (keeps forms intact if user has filled)
    const tabDonationsBtn = document.getElementById('tab-donations-btn');
    const tabDonorsBtn = document.getElementById('tab-donors-btn');
    const tabDonations = document.getElementById('tab-donations');
    const tabDonors = document.getElementById('tab-donors');

    function showTab(tab) {
        if (tab === 'donors') {
            tabDonations.classList.add('hidden');
            tabDonors.classList.remove('hidden');
            tabDonationsBtn.classList.remove('btn-primary');
            tabDonationsBtn.classList.add('btn-ghost');
            tabDonorsBtn.classList.add('btn-primary');
            tabDonorsBtn.classList.remove('btn-ghost');
            history.replaceState(null, '', '?tab=donors');
        } else {
            tabDonors.classList.add('hidden');
            tabDonations.classList.remove('hidden');
            tabDonorsBtn.classList.remove('btn-primary');
            tabDonorsBtn.classList.add('btn-ghost');
            tabDonationsBtn.classList.add('btn-primary');
            tabDonationsBtn.classList.remove('btn-ghost');
            history.replaceState(null, '', '?tab=donations');
        }
    }

    tabDonationsBtn.addEventListener('click', () => showTab('donations'));
    tabDonorsBtn.addEventListener('click', () => showTab('donors'));

    // Donation-type dependent fields
    const donationType = document.getElementById('donation_type');
    const moneyFields = document.getElementById('money-fields');

    function toggleMoneyFields() {
        const v = donationType.value;
        if (v.toLowerCase().includes('monetary')) {
            moneyFields.classList.remove('hidden');
        } else {
            moneyFields.classList.add('hidden');
        }
    }
    if (donationType) {
        donationType.addEventListener('change', toggleMoneyFields);
        // run once on load
        toggleMoneyFields();
    }

    // On page load — set active based on server-side $tab
    (function initTabFromServer() {
        const q = new URLSearchParams(location.search);
        const t = q.get('tab') || '<?= $tab ?>';
        showTab(t);
    })();
</script>


<?php require __DIR__ . '/includes/admin_footer.php'; ?>
