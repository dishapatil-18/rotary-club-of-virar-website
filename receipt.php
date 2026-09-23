<?php
// receipt.php
require_once __DIR__ . '/includes/session_security.php';
secureSessionStart();

// Require admin authentication to protect PII
if (!isset($_SESSION['admin_id'])) {
    header("HTTP/1.1 403 Forbidden");
    die("Access denied.");
}

require __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/website_settings.php';
$ws = getWebsiteSettings($conn);

$donation_id = isset($_GET['donation_id']) ? intval($_GET['donation_id']) : 0;
if ($donation_id <= 0) {
    die("Invalid donation id.");
}

$sql = "SELECT dn.donation_id, dn.donation_type, dn.description, dn.amount, dn.utr_number, dn.screenshot_path, dn.pickup_option, dn.status, dn.date,
               dr.name AS donor_name, dr.phone_number, dr.email, dr.address, dr.occupation
        FROM donations dn
        JOIN donors dr ON dn.donor_id = dr.donor_id
        WHERE dn.donation_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $donation_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    die("Donation not found.");
}
$row = $res->fetch_assoc();
$stmt->close();

// Parse pickup option
$pickupLabel = '—';
if (!empty($row['pickup_option'])) {
    $po = json_decode($row['pickup_option'], true);
    if ($po && isset($po['option'])) {
        if ($po['option'] === 'deliver') {
            $pickupLabel = 'Self Delivery to Club';
        } elseif ($po['option'] === 'pickup') {
            $addr = htmlspecialchars($po['address'] ?? '');
            $date = htmlspecialchars($po['date'] ?? '');
            $pickupLabel = "Pickup requested from: $addr" . ($date ? " on $date" : "");
        }
    }
}

$txref = "RCB-DON-" . str_pad($row['donation_id'], 5, "0", STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Receipt — <?= htmlspecialchars($txref) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body { font-family: Inter, sans-serif; background:#f3f6fb; padding:20px; }
    .receipt { max-width:800px; margin:0 auto; background:#fff; padding:28px; border-radius:8px; box-shadow:0 8px 30px rgba(10,35,66,.06); }
    .muted { color:#666; font-size:0.95rem; }
  </style>
</head>
<body>
  <div class="receipt">
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center space-x-3">
        <img src="<?= e($ws['website_logo']) ?>" alt="<?= e($ws['website_short_name']) ?> Logo" class="h-12 w-12 rounded-full border-2 border-[#FFC000]">
        <div>
          <h1 class="text-2xl font-bold"><?= e($ws['website_name']) ?></h1>
          <p class="muted">Donation Receipt</p>
        </div>
      </div>
      <div class="text-right">
        <p class="muted">Receipt ID</p>
        <h3 class="font-semibold"><?= htmlspecialchars($txref) ?></h3>
        <p class="muted"><?= date("F j, Y, g:i a", strtotime($row['date'])) ?></p>
        <p class="text-xs mt-1">
          <span class="px-2 py-1 rounded text-xs font-semibold
            <?php
            $s = $row['status'] ?? 'Pending Verification';
            if ($s === 'Pending Verification') echo 'bg-yellow-100 text-yellow-800';
            elseif ($s === 'Verified') echo 'bg-green-100 text-green-800';
            elseif ($s === 'Contacted') echo 'bg-blue-100 text-blue-800';
            elseif ($s === 'Received') echo 'bg-purple-100 text-purple-800';
            elseif ($s === 'Completed') echo 'bg-emerald-100 text-emerald-800';
            else echo 'bg-gray-100 text-gray-700';
            ?>">
            <?= htmlspecialchars($s) ?>
          </span>
        </p>
      </div>
    </div>

    <section class="mb-6">
      <h3 class="font-semibold mb-2">Donor Details</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
        <div><span class="muted">Name:</span><div><?= htmlspecialchars($row['donor_name']) ?></div></div>
        <div><span class="muted">Phone:</span><div><?= htmlspecialchars($row['phone_number']) ?></div></div>
        <div><span class="muted">Email:</span><div><?= htmlspecialchars($row['email']) ?></div></div>
        <div><span class="muted">Occupation:</span><div><?= htmlspecialchars($row['occupation'] ?? '—') ?></div></div>
        <?php if (!empty($row['address'])): ?>
        <div class="md:col-span-2"><span class="muted">Address:</span><div><?= nl2br(htmlspecialchars($row['address'])) ?></div></div>
        <?php endif; ?>
      </div>
    </section>

    <section class="mb-6">
      <h3 class="font-semibold mb-2">Donation Details</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
        <div><span class="muted">Donation Type:</span><div><?= htmlspecialchars($row['donation_type']) ?></div></div>
        <div>
          <span class="muted">Amount:</span>
          <div><?= $row['amount'] !== null ? '₹ ' . number_format($row['amount'],2) : '— (in-kind)' ?></div>
        </div>
        <?php if (!empty($row['utr_number'])): ?>
        <div><span class="muted">UTR Number:</span><div><?= htmlspecialchars($row['utr_number']) ?></div></div>
        <?php endif; ?>
        <?php if (!empty($row['description'])): ?>
        <div class="md:col-span-2"><span class="muted">Description:</span><div><?= nl2br(htmlspecialchars($row['description'])) ?></div></div>
        <?php endif; ?>
        <div class="md:col-span-2"><span class="muted">Delivery / Pickup:</span><div><?= $pickupLabel ?></div></div>
      </div>
    </section>

    <?php if (!empty($row['screenshot_path'])): ?>
    <section class="mb-6">
      <h3 class="font-semibold mb-2">Payment Proof</h3>
      <img src="<?= htmlspecialchars($row['screenshot_path']) ?>" class="max-h-40 rounded shadow-sm border">
    </section>
    <?php endif; ?>

    <section class="mb-6 muted">
      <p>Thank you for your generous support. This receipt confirms the donation recorded in our system.</p>
      <p class="mt-2">All contributions to <?= e($ws['website_name']) ?> are eligible for 80G tax exemption under the Income Tax Act, 1961. For official registration & tax-exemption queries, contact the club admin.</p>
    </section>

    <div class="flex items-center justify-between">
      <button onclick="window.print()" class="px-4 py-2 bg-[#0A2342] text-white rounded hover:bg-blue-900 transition">Print Receipt</button>
      <a href="index.php" class="muted underline">Back to website</a>
    </div>
  </div>
</body>
</html>
