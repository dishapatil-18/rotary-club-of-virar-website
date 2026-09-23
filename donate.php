<?php
session_start();
require __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/config/club_settings.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/website_settings.php';
$ws = getWebsiteSettings($conn);

// ==================== CONFIGURABLE SETTINGS ====================
// Bank Details
$bank_account_name = 'Rotary Club of Virar';
$bank_account_no   = '055100100002900';
$bank_ifsc         = 'NKGS0000055';
$bank_name         = 'NKGSB Bank';
// Club Address (for self-delivery) — see config/club_settings.php
$club_phone   = CLUB_PHONE;

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    // ======================== SAVE DONOR ========================
    if ($action === 'save_donor') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $occupation = trim($_POST['occupation'] ?? '');

        if ($name === '' || $phone === '' || $email === '') {
            echo json_encode(['success' => false, 'message' => 'Name, phone and email are required.']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
            exit;
        }

        if (!preg_match('/^[0-9+\-\s()]{7,15}$/', $phone)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid phone number.']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO donors (name, phone_number, email, address, occupation, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssss", $name, $phone, $email, $address, $occupation);
        if ($stmt->execute()) {
            $donor_id = $conn->insert_id;
            echo json_encode(['success' => true, 'donor_id' => $donor_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error while saving donor.']);
        }
        $stmt->close();
        exit;
    }

    // ======================== SAVE DONATION ========================
    if ($action === 'save_donation') {
        $donor_id = intval($_POST['donor_id'] ?? 0);
        $donation_type = trim($_POST['donation_type'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $amount_raw = trim($_POST['amount'] ?? '');
        $utr_number = trim($_POST['utr_number'] ?? '');
        $pickup_option = trim($_POST['pickup_option'] ?? '');
        $pickup_address = trim($_POST['pickup_address'] ?? '');
        $pickup_date = trim($_POST['pickup_date'] ?? '');
        $pickup_notes = trim($_POST['pickup_notes'] ?? '');

        if ($donor_id <= 0 || $donation_type === '') {
            echo json_encode(['success' => false, 'message' => 'Invalid donor or donation type.']);
            exit;
        }

        $is_money = (strtolower($donation_type) === 'monetary donation' || strtolower($donation_type) === 'money');

        // Monetary validation
        if ($is_money) {
            if ($amount_raw === '' || !is_numeric($amount_raw) || floatval($amount_raw) <= 0) {
                echo json_encode(['success' => false, 'message' => 'Please enter a valid donation amount.']);
                exit;
            }
            if ($utr_number === '') {
                echo json_encode(['success' => false, 'message' => 'Please provide the UTR / Transaction Reference Number.']);
                exit;
            }
            if (empty($_FILES['proof_file']['name'])) {
                echo json_encode(['success' => false, 'message' => 'Please upload the payment screenshot.']);
                exit;
            }
        }

        $amount = ($amount_raw !== '' && is_numeric($amount_raw)) ? floatval($amount_raw) : null;

        // Build pickup_option JSON (only for non-monetary)
        $pickup_data = [
            'option' => $pickup_option,
            'address' => $pickup_address,
            'date' => $pickup_date,
            'notes' => $pickup_notes
        ];
        $pickup_json = json_encode($pickup_data);

        // Handle file upload for proof
        $proof_path = '';
        if (!empty($_FILES['proof_file']['name'])) {
            require_once __DIR__ . '/includes/upload_helper.php';

            $validation = validateUpload($_FILES['proof_file'], ['jpg', 'jpeg', 'png', 'pdf'], 5242880);
            if (!$validation['valid']) {
                echo json_encode(['success' => false, 'message' => $validation['errors'][0]]);
                exit;
            }

            $ext = strtolower(pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION));
            $upload_dir = secureUploadPath('uploads/donations/');

            $filename = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            if (move_uploaded_file($_FILES['proof_file']['tmp_name'], $upload_dir . '/' . $filename)) {
                $proof_path = 'uploads/donations/' . $filename;
            }
        }

        $stmt = $conn->prepare("INSERT INTO donations (donor_id, donation_type, description, amount, utr_number, screenshot_path, pickup_option, status, date) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending Verification', NOW())");
        $stmt->bind_param("issdsss", $donor_id, $donation_type, $description, $amount, $utr_number, $proof_path, $pickup_json);
        if ($stmt->execute()) {
            $donation_id = $conn->insert_id;
            $hstmt = $conn->prepare("INSERT INTO donation_status_history (donation_id, previous_status, new_status, admin_id, admin_name, admin_role, remarks, updated_at) VALUES (?, NULL, 'Pending Verification', NULL, NULL, NULL, '', NOW())");
            $hstmt->bind_param("i", $donation_id);
            $hstmt->execute();
            $hstmt->close();
            echo json_encode(['success' => true, 'donation_id' => $donation_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error while saving donation.']);
        }
        $stmt->close();
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($ws['website_name']) ?> - Donate</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
    <style>
        :root {
            --rotary-blue: #0A2342;
            --rotary-dark-blue: #1a365d;
            --rotary-yellow: #FFC000;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f7f9fb;
        }
        input:focus, textarea:focus, select:focus {
            border-color: var(--rotary-yellow) !important;
            box-shadow: 0 0 0 2px rgba(255, 192, 0, 0.5);
        }
        @keyframes draw {
            0% { stroke-dashoffset: 300; }
            100% { stroke-dashoffset: 0; }
        }
        .checkmark-icon {
            stroke-dasharray: 300;
            stroke-dashoffset: 300;
            animation: draw 2s ease-out forwards;
        }
        .btn-hover {
            transition: all 0.2s ease-in-out;
        }
        .btn-hover:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .category-card {
            transition: all 0.2s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }
        .category-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        .category-card.selected {
            border-color: var(--rotary-yellow);
            background-color: #fffbeb;
            box-shadow: 0 0 0 3px rgba(255, 192, 0, 0.3);
        }
        .fade-in {
            animation: fadeIn 0.4s ease forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .payment-card {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            border: 2px solid #f59e0b;
        }
    </style>
</head>
<body class="min-h-screen">

    <header class="py-8 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 rounded-2xl shadow-xl bg-[var(--rotary-dark-blue)] flex flex-col md:flex-row justify-between items-center text-white">
            <div class="flex items-center space-x-4 mb-4 md:mb-0">
                <img src="<?= e($ws['website_logo']) ?>" alt="<?= e($ws['website_short_name']) ?> Logo" class="h-16 w-16 rounded-full object-cover border-2 border-[var(--rotary-yellow)]">
                <div>
                    <h1 class="text-4xl font-extrabold">Donation Dashboard</h1>
                    <p class="mt-1 text-base text-gray-300">Contribute to make a lasting impact.</p>
                </div>
            </div>
            <button onclick="window.location.href='index.php'"
                    class="px-6 py-3 bg-white text-gray-800 font-semibold rounded-lg hover:bg-yellow-400 hover:text-white transition duration-150 flex items-center space-x-2 shadow-md">
                <i data-lucide="home" class="w-5 h-5"></i>
                <span>Back to Home</span>
            </button>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

        <!-- STEP INDICATOR -->
        <div id="step-indicator" class="flex items-center justify-center space-x-2 sm:space-x-4 mb-8 text-sm">
            <div class="flex items-center space-x-1 sm:space-x-2">
                <span id="ind-1" class="w-8 h-8 rounded-full bg-[var(--rotary-yellow)] text-[var(--rotary-blue)] font-bold flex items-center justify-center text-xs">1</span>
                <span class="hidden sm:inline text-gray-600 font-medium">Donor Details</span>
            </div>
            <div class="w-8 sm:w-12 h-0.5 bg-gray-300"></div>
            <div class="flex items-center space-x-1 sm:space-x-2">
                <span id="ind-2" class="w-8 h-8 rounded-full bg-gray-300 text-gray-500 font-bold flex items-center justify-center text-xs">2</span>
                <span class="hidden sm:inline text-gray-400">Category</span>
            </div>
            <div class="w-8 sm:w-12 h-0.5 bg-gray-300"></div>
            <div class="flex items-center space-x-1 sm:space-x-2">
                <span id="ind-3" class="w-8 h-8 rounded-full bg-gray-300 text-gray-500 font-bold flex items-center justify-center text-xs">3</span>
                <span class="hidden sm:inline text-gray-400">Details</span>
            </div>
            <div class="w-8 sm:w-12 h-0.5 bg-gray-300"></div>
            <div class="flex items-center space-x-1 sm:space-x-2">
                <span id="ind-4" class="w-8 h-8 rounded-full bg-gray-300 text-gray-500 font-bold flex items-center justify-center text-xs">4</span>
                <span class="hidden sm:inline text-gray-400">Delivery</span>
            </div>
            <div class="w-8 sm:w-12 h-0.5 bg-gray-300"></div>
            <div class="flex items-center space-x-1 sm:space-x-2">
                <span id="ind-5" class="w-8 h-8 rounded-full bg-gray-300 text-gray-500 font-bold flex items-center justify-center text-xs">5</span>
                <span class="hidden sm:inline text-gray-400">Submit</span>
            </div>
        </div>

        <!-- =============== STEP 1: DONOR DETAILS =============== -->
        <div id="step-1" class="bg-white p-8 sm:p-10 rounded-xl shadow-xl border-t-4 border-[var(--rotary-blue)] transition duration-500 fade-in">
            <h2 class="text-3xl font-bold text-gray-800 mb-2">Become a Donor — Make a Difference Today!</h2>
            <p class="text-gray-600 mb-6">Fill in your details to support our cause.</p>

            <form id="donor-form">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="full-name" class="block text-sm font-medium text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                        <input type="text" id="full-name" name="name" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-yellow-500 transition duration-150">
                    </div>
                    <div>
                        <label for="phone-number" class="block text-sm font-medium text-gray-700 mb-1">Mobile Number <span class="text-red-500">*</span></label>
                        <input type="tel" id="phone-number" name="phone" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-yellow-500 transition duration-150"
                               placeholder="<?= CLUB_PHONE ?>">
                    </div>
                    <div>
                        <label for="email-address" class="block text-sm font-medium text-gray-700 mb-1">Email Address <span class="text-red-500">*</span></label>
                        <input type="email" id="email-address" name="email" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-yellow-500 transition duration-150">
                    </div>
                    <div>
                        <label for="occupation" class="block text-sm font-medium text-gray-700 mb-1">Occupation</label>
                        <input type="text" id="occupation" name="occupation"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-yellow-500 transition duration-150"
                               placeholder="e.g. Teacher, Business, Student">
                    </div>
                    <div class="md:col-span-2">
                        <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                        <textarea id="address" name="address" rows="2"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg resize-none focus:ring-yellow-500 transition duration-150"
                                  placeholder="Your complete address"></textarea>
                    </div>
                </div>

                <p id="step1-error" class="text-red-500 text-sm mt-4 hidden">Please fill out all required fields.</p>
                <p id="step1-ajax-error" class="text-red-500 text-sm mt-2 hidden"></p>

                <div class="flex justify-end space-x-4 mt-8">
                    <button type="button" onclick="submitDonorAndProceed()"
                            class="btn-hover px-8 py-3 bg-[var(--rotary-yellow)] text-[var(--rotary-blue)] font-bold rounded-lg hover:bg-yellow-600 transition duration-150 shadow-md">
                        Continue →
                    </button>
                </div>
            </form>
        </div>

        <!-- =============== STEP 2: DONATION CATEGORY =============== -->
        <div id="step-2" class="bg-white p-8 sm:p-10 rounded-xl shadow-xl border-t-4 border-[var(--rotary-yellow)] mt-10 hidden fade-in">
            <h2 class="text-3xl font-bold text-gray-800 mb-2">Select Donation Category</h2>
            <p class="text-gray-600 mb-6">Choose what you would like to donate.</p>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                <div class="category-card rounded-xl p-4 text-center bg-white border border-gray-200 shadow-sm" data-category="Monetary Donation" onclick="selectCategory(this)">
                    <div class="w-12 h-12 mx-auto mb-2 flex items-center justify-center rounded-full bg-yellow-100">
                        <i data-lucide="indian-rupee" class="w-6 h-6 text-yellow-600"></i>
                    </div>
                    <p class="font-semibold text-gray-800 text-sm">Monetary Donation</p>
                </div>
                <div class="category-card rounded-xl p-4 text-center bg-white border border-gray-200 shadow-sm" data-category="School Supplies" onclick="selectCategory(this)">
                    <div class="w-12 h-12 mx-auto mb-2 flex items-center justify-center rounded-full bg-blue-100">
                        <i data-lucide="backpack" class="w-6 h-6 text-blue-600"></i>
                    </div>
                    <p class="font-semibold text-gray-800 text-sm">School Supplies</p>
                </div>
                <div class="category-card rounded-xl p-4 text-center bg-white border border-gray-200 shadow-sm" data-category="Food & Grocery" onclick="selectCategory(this)">
                    <div class="w-12 h-12 mx-auto mb-2 flex items-center justify-center rounded-full bg-green-100">
                        <i data-lucide="utensils" class="w-6 h-6 text-green-600"></i>
                    </div>
                    <p class="font-semibold text-gray-800 text-sm">Food & Grocery</p>
                </div>
                <div class="category-card rounded-xl p-4 text-center bg-white border border-gray-200 shadow-sm" data-category="Clothes & Blankets" onclick="selectCategory(this)">
                    <div class="w-12 h-12 mx-auto mb-2 flex items-center justify-center rounded-full bg-red-100">
                        <i data-lucide="shirt" class="w-6 h-6 text-red-600"></i>
                    </div>
                    <p class="font-semibold text-gray-800 text-sm">Clothes & Blankets</p>
                </div>
                <div class="category-card rounded-xl p-4 text-center bg-white border border-gray-200 shadow-sm" data-category="Medical Support" onclick="selectCategory(this)">
                    <div class="w-12 h-12 mx-auto mb-2 flex items-center justify-center rounded-full bg-purple-100">
                        <i data-lucide="heart-pulse" class="w-6 h-6 text-purple-600"></i>
                    </div>
                    <p class="font-semibold text-gray-800 text-sm">Medical Support</p>
                </div>
                <div class="category-card rounded-xl p-4 text-center bg-white border border-gray-200 shadow-sm" data-category="Volunteer Support" onclick="selectCategory(this)">
                    <div class="w-12 h-12 mx-auto mb-2 flex items-center justify-center rounded-full bg-teal-100">
                        <i data-lucide="hand-heart" class="w-6 h-6 text-teal-600"></i>
                    </div>
                    <p class="font-semibold text-gray-800 text-sm">Volunteer Support</p>
                </div>
                <div class="category-card rounded-xl p-4 text-center bg-white border border-gray-200 shadow-sm" data-category="Other" onclick="selectCategory(this)">
                    <div class="w-12 h-12 mx-auto mb-2 flex items-center justify-center rounded-full bg-gray-100">
                        <i data-lucide="package" class="w-6 h-6 text-gray-600"></i>
                    </div>
                    <p class="font-semibold text-gray-800 text-sm">Other</p>
                </div>
            </div>

            <p id="step2-error" class="text-red-500 text-sm hidden mb-4">Please select a donation category.</p>

            <div class="flex justify-between items-center">
                <button type="button" onclick="goToStep(1)"
                        class="px-6 py-2 border border-gray-300 text-gray-600 font-medium rounded-lg hover:bg-gray-100 transition duration-150">
                    ← Back
                </button>
                <button type="button" onclick="confirmCategory()"
                        class="btn-hover px-8 py-3 bg-[var(--rotary-yellow)] text-[var(--rotary-blue)] font-bold rounded-lg hover:bg-yellow-600 transition duration-150 shadow-md">
                    Continue →
                </button>
            </div>
        </div>

        <!-- =============== STEP 3: DONATION DETAILS =============== -->
        <div id="step-3" class="bg-white p-8 sm:p-10 rounded-xl shadow-xl border-t-4 border-green-500 mt-10 hidden fade-in">
            <h2 class="text-3xl font-bold text-gray-800 mb-2">Donation Details</h2>
            <p id="step3-subtitle" class="text-gray-600 mb-6">Provide details about your donation.</p>

            <form id="donation-details-form">
                <input type="hidden" id="selected-category-input" name="donation_type" value="">

                <!-- ========== MONETARY DONATION FIELDS ========== -->
                <div id="monetary-fields" class="hidden">
                    <!-- Amount -->
                    <div class="mb-6">
                        <label for="donation-amount" class="block text-sm font-medium text-gray-700 mb-1">Donation Amount (₹) <span class="text-red-500">*</span></label>
                        <input type="number" id="donation-amount" name="amount" min="1" step="0.01" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-yellow-500 transition duration-150"
                               placeholder="Enter amount in INR">
                    </div>

                    <!-- UTR / Transaction Reference Number -->
                    <div class="mb-6">
                        <label for="utr-number" class="block text-sm font-medium text-gray-700 mb-1">UTR / Transaction Reference Number <span class="text-red-500">*</span></label>
                        <input type="text" id="utr-number" name="utr_number" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-yellow-500 transition duration-150"
                               placeholder="e.g. N232425ABCDE12345">
                        <p class="text-xs text-gray-400 mt-1">Enter the UTR number from your bank transaction or payment app.</p>
                    </div>

                    <!-- Payment Information Card -->
                    <div class="payment-card rounded-xl p-6 mb-6 shadow-md">
                        <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                            <i data-lucide="landmark" class="w-5 h-5 mr-2 text-yellow-600"></i>
                            Complete Your Payment
                        </h3>
                        <div class="text-gray-700 space-y-3">
                            <p class="font-bold text-lg text-[var(--rotary-blue)]"><?= htmlspecialchars($bank_account_name) ?></p>
                            <div class="bg-white p-4 rounded-lg border border-yellow-200 text-sm space-y-2 shadow-sm">
                                <p><span class="font-semibold">Account Name:</span> <?= htmlspecialchars($bank_account_name) ?></p>
                                <p><span class="font-semibold">Account No.:</span> <span class="font-mono text-base font-bold text-[var(--rotary-blue)]"><?= htmlspecialchars($bank_account_no) ?></span></p>
                                <p><span class="font-semibold">IFSC Code:</span> <span class="font-mono font-bold"><?= htmlspecialchars($bank_ifsc) ?></span></p>
                                <p><span class="font-semibold">Bank:</span> <?= htmlspecialchars($bank_name) ?></p>
                            </div>
                            <div class="bg-blue-50 p-4 rounded-lg border border-blue-200 text-sm text-blue-800">
                                <i data-lucide="info" class="w-4 h-4 inline mr-1"></i>
                                Please transfer the donation to the above bank account and upload the payment screenshot for verification.
                            </div>
                            <div class="bg-green-50 p-3 rounded-lg border border-green-200 text-xs text-green-800">
                                <i data-lucide="badge-check" class="w-4 h-4 inline mr-1"></i>
                                All contributions are eligible for 80G tax exemption.
                            </div>
                        </div>
                    </div>

                    <!-- Payment Screenshot Upload -->
                    <div class="mb-6">
                        <label for="proof-upload" class="block text-sm font-medium text-gray-700 mb-1">
                            Upload Payment Screenshot <span class="text-red-500">*</span>
                        </label>
                        <input type="file" id="proof-upload" name="proof_file" accept=".jpg,.jpeg,.png,.pdf" required
                               class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-yellow-100 file:text-yellow-700 hover:file:bg-yellow-200"/>
                        <p id="proof-status" class="text-green-600 text-sm mt-1 hidden">✓ Payment Proof Uploaded Successfully</p>
                        <p id="proof-error" class="text-red-500 text-sm mt-1 hidden">Please upload a payment screenshot.</p>
                    </div>

                    <!-- Confirmation Checkbox -->
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <label class="flex items-start space-x-3 cursor-pointer">
                            <input type="checkbox" id="payment-confirm" class="mt-1 accent-yellow-500 w-5 h-5">
                            <span class="text-sm text-gray-700 font-medium">
                                I confirm that I have completed the payment to <?= e($ws['website_name']) ?>. <span class="text-red-500">*</span>
                            </span>
                        </label>
                        <p id="confirm-error" class="text-red-500 text-sm mt-1 hidden">Please confirm that you have completed the payment.</p>
                    </div>
                </div>

                <!-- ========== NON-MONETARY FIELDS ========== -->
                <div id="non-monetary-fields" class="hidden">
                    <div class="mb-6">
                        <label for="donation-description" class="block text-sm font-medium text-gray-700 mb-1">Describe Your Donation <span class="text-red-500">*</span></label>
                        <textarea id="donation-description" name="description" rows="4" required
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg resize-none focus:ring-yellow-500 transition duration-150"
                                  placeholder="Describe what you are donating..."></textarea>
                        <p id="description-hint" class="text-xs text-gray-400 mt-1"></p>
                    </div>
                </div>

                <p id="step3-error" class="text-red-500 text-sm mt-4 hidden">Please fill out required fields.</p>

                <div class="flex justify-between items-center mt-8">
                    <button type="button" onclick="goToStep(2)"
                            class="px-6 py-2 border border-gray-300 text-gray-600 font-medium rounded-lg hover:bg-gray-100 transition duration-150">
                        ← Back
                    </button>
                    <button type="button" onclick="confirmDonationDetails()"
                            class="btn-hover px-8 py-3 bg-[var(--rotary-yellow)] text-[var(--rotary-blue)] font-bold rounded-lg hover:bg-yellow-600 transition duration-150 shadow-md">
                        Continue →
                    </button>
                </div>
            </form>
        </div>

        <!-- =============== STEP 4: DELIVERY / PICKUP (Non-Monetary Only) =============== -->
        <div id="step-4" class="bg-white p-8 sm:p-10 rounded-xl shadow-xl border-t-4 border-purple-500 mt-10 hidden fade-in">
            <h2 class="text-3xl font-bold text-gray-800 mb-2">Delivery or Pickup</h2>
            <p class="text-gray-600 mb-6">How would you like to get your donation to us?</p>

            <form id="pickup-form">
                <div class="space-y-4 mb-6">
                    <label class="flex items-start p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-[var(--rotary-yellow)] transition duration-150" onclick="selectPickupOption('deliver')">
                        <input type="radio" name="pickup_option" value="deliver" class="mt-1 mr-3 accent-yellow-500">
                        <div>
                            <p class="font-semibold text-gray-800">I will deliver the donation myself</p>
                            <p class="text-sm text-gray-500 mt-1">You can drop off your donation at our club address.</p>
                        </div>
                    </label>
                    <label class="flex items-start p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-[var(--rotary-yellow)] transition duration-150" onclick="selectPickupOption('pickup')">
                        <input type="radio" name="pickup_option" value="pickup" class="mt-1 mr-3 accent-yellow-500">
                        <div>
                            <p class="font-semibold text-gray-800">Please arrange pickup</p>
                            <p class="text-sm text-gray-500 mt-1">Our team will come to collect your donation.</p>
                        </div>
                    </label>
                </div>

                <div id="club-address-box" class="hidden bg-blue-50 p-4 rounded-lg border border-blue-200 mb-6">
                    <h4 class="font-semibold text-[var(--rotary-blue)] mb-2">Our Address</h4>
                    <p class="text-gray-700 text-sm font-medium"><?= CLUB_NAME ?></p>
                    <p class="text-gray-700 text-sm">
                        <?= CLUB_ADDRESS_LINE1 ?><br>
                        <?= CLUB_ADDRESS_LINE2 ?><br>
                        <?= CLUB_ADDRESS_LINE3 ?><br>
                        <?= CLUB_ADDRESS_LINE4 ?><br>
                        <?= CLUB_ADDRESS_LINE5 ?><br>
                        <?= CLUB_ADDRESS_LINE6 ?><br>
                        <?= CLUB_ADDRESS_LINE7 ?>
                    </p>
                    <p class="text-gray-700 text-sm mt-1">Contact: <?= htmlspecialchars($club_phone) ?></p>
                    <div class="flex flex-wrap gap-2 mt-3">
                        <button type="button" onclick="copyAddress()" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition duration-150 text-sm font-medium text-gray-700">
                            <i data-lucide="copy" class="w-4 h-4"></i> Copy Address
                        </button>
                        <a href="<?= CLUB_MAP_URL ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 px-4 py-2 bg-[var(--rotary-blue)] text-white rounded-lg hover:bg-blue-900 transition duration-150 text-sm font-medium">
                            <i data-lucide="map" class="w-4 h-4"></i> View on Map
                        </a>
                    </div>
                </div>

                <div id="pickup-details-box" class="hidden space-y-4 mb-6">
                    <div>
                        <label for="pickup-address" class="block text-sm font-medium text-gray-700 mb-1">Pickup Address <span class="text-red-500">*</span></label>
                        <textarea id="pickup-address" name="pickup_address" rows="2"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg resize-none focus:ring-yellow-500 transition duration-150"
                                  placeholder="Enter the address for pickup"></textarea>
                    </div>
                    <div>
                        <label for="pickup-date" class="block text-sm font-medium text-gray-700 mb-1">Preferred Pickup Date <span class="text-red-500">*</span></label>
                        <input type="date" id="pickup-date" name="pickup_date"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-yellow-500 transition duration-150">
                    </div>
                    <div>
                        <label for="pickup-notes" class="block text-sm font-medium text-gray-700 mb-1">Additional Notes</label>
                        <textarea id="pickup-notes" name="pickup_notes" rows="2"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg resize-none focus:ring-yellow-500 transition duration-150"
                                  placeholder="Any special instructions for pickup"></textarea>
                    </div>
                </div>

                <p id="step4-error" class="text-red-500 text-sm hidden mb-4">Please select a delivery/pickup option.</p>

                <div class="flex justify-between items-center">
                    <button type="button" onclick="goToStep(3)"
                            class="px-6 py-2 border border-gray-300 text-gray-600 font-medium rounded-lg hover:bg-gray-100 transition duration-150">
                        ← Back
                    </button>
                    <button type="button" onclick="navigateFromStep4()"
                            class="btn-hover px-8 py-3 bg-[var(--rotary-yellow)] text-[var(--rotary-blue)] font-bold rounded-lg hover:bg-yellow-600 transition duration-150 shadow-md">
                        Continue →
                    </button>
                </div>
            </form>
        </div>

        <!-- =============== STEP 5: REVIEW & SUBMIT =============== -->
        <div id="step-5" class="bg-white p-8 sm:p-10 rounded-xl shadow-xl border-t-4 border-red-500 mt-10 hidden fade-in">
            <h2 class="text-3xl font-bold text-gray-800 mb-2">Review & Submit</h2>
            <p class="text-gray-600 mb-6">Please review your donation details before submitting.</p>

            <div class="bg-gray-50 rounded-lg p-6 mb-6 space-y-4">
                <div>
                    <h3 class="font-bold text-[var(--rotary-blue)] mb-2">Donor Information</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                        <p><span class="font-semibold text-gray-600">Name:</span> <span id="review-name"></span></p>
                        <p><span class="font-semibold text-gray-600">Phone:</span> <span id="review-phone"></span></p>
                        <p><span class="font-semibold text-gray-600">Email:</span> <span id="review-email"></span></p>
                        <p><span class="font-semibold text-gray-600">Occupation:</span> <span id="review-occupation"></span></p>
                        <p class="sm:col-span-2"><span class="font-semibold text-gray-600">Address:</span> <span id="review-address"></span></p>
                    </div>
                </div>
                <hr class="border-gray-200">
                <div>
                    <h3 class="font-bold text-[var(--rotary-blue)] mb-2">Donation Details</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                        <p><span class="font-semibold text-gray-600">Category:</span> <span id="review-category"></span></p>
                        <p><span class="font-semibold text-gray-600">Amount:</span> <span id="review-amount"></span></p>
                        <p id="review-utr-row" class="hidden sm:col-span-2"><span class="font-semibold text-gray-600">UTR Number:</span> <span id="review-utr"></span></p>
                        <p class="sm:col-span-2"><span class="font-semibold text-gray-600">Description:</span> <span id="review-description"></span></p>
                    </div>
                </div>
                <hr class="border-gray-200">
                <div id="review-pickup-section">
                    <h3 class="font-bold text-[var(--rotary-blue)] mb-2">Delivery / Pickup</h3>
                    <div class="grid grid-cols-1 gap-2 text-sm">
                        <p><span class="font-semibold text-gray-600">Option:</span> <span id="review-pickup-option"></span></p>
                        <p id="review-pickup-address-row" class="hidden"><span class="font-semibold text-gray-600">Pickup Address:</span> <span id="review-pickup-address"></span></p>
                        <p id="review-pickup-date-row" class="hidden"><span class="font-semibold text-gray-600">Pickup Date:</span> <span id="review-pickup-date"></span></p>
                        <p id="review-pickup-notes-row" class="hidden"><span class="font-semibold text-gray-600">Notes:</span> <span id="review-pickup-notes"></span></p>
                    </div>
                </div>
            </div>

            <p id="step5-error" class="text-red-500 text-sm hidden mb-4">Please complete all previous steps.</p>

            <div class="flex justify-between items-center">
                <button type="button" id="step5-back-btn" onclick="goBackFromStep5()"
                        class="px-6 py-2 border border-gray-300 text-gray-600 font-medium rounded-lg hover:bg-gray-100 transition duration-150">
                    ← Back
                </button>
                <button type="button" onclick="submitDonation()" id="submit-btn"
                        class="btn-hover px-10 py-3 bg-green-600 text-white font-bold rounded-lg hover:bg-green-700 transition duration-150 shadow-md">
                    <span id="submit-btn-text">Submit Donation</span>
                    <span id="submit-btn-spinner" class="hidden"><svg class="animate-spin h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Submitting...</span>
                </button>
            </div>
        </div>

        <!-- =============== SUCCESS MODAL =============== -->
        <div id="success-modal" class="fixed inset-0 bg-black bg-opacity-70 hidden items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-md w-full transform transition-all duration-300 scale-100 border-t-8 border-green-500 text-center">
                <div class="w-20 h-20 mx-auto mb-4 flex items-center justify-center rounded-full bg-green-100">
                    <i data-lucide="heart" class="w-10 h-10 text-green-600 fill-green-600"></i>
                </div>
                <h3 class="text-3xl font-bold text-gray-800 mb-2">Thank You ❤️</h3>
                <p class="text-gray-600 mb-6 leading-relaxed">
                    Thank you for supporting <?= e($ws['website_name']) ?>.<br><br>
                    Your donation request has been submitted successfully.<br><br>
                    Our team will review your details and contact you shortly.
                </p>
                <button onclick="closeSuccessModal()"
                        class="px-8 py-3 bg-[var(--rotary-yellow)] text-[var(--rotary-blue)] font-bold rounded-lg hover:bg-yellow-600 transition duration-150 shadow-md">
                    Done
                </button>
            </div>
        </div>

    </main>

    <script>
        lucide.createIcons();

        // Global state
        let donorData = {};
        let donationId = null;
        let donorId = null;
        let selectedCategory = '';
        let isMoneyCategory = false;
        let uploadComplete = false;
        let stepHistory = [1];

        // ======================== STEP NAVIGATION ========================
        function goToStep(step) {
            for (let i = 1; i <= 5; i++) {
                const el = document.getElementById('step-' + i);
                if (el) el.classList.add('hidden');
            }
            const nextEl = document.getElementById('step-' + step);
            if (nextEl) {
                nextEl.classList.remove('hidden');
                nextEl.classList.add('fade-in');
            }
            updateIndicator(step);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function updateIndicator(step) {
            for (let i = 1; i <= 5; i++) {
                const ind = document.getElementById('ind-' + i);
                if (!ind) continue;
                if (i < step) {
                    ind.className = 'w-8 h-8 rounded-full bg-green-500 text-white font-bold flex items-center justify-center text-xs';
                } else if (i === step) {
                    ind.className = 'w-8 h-8 rounded-full bg-[var(--rotary-yellow)] text-[var(--rotary-blue)] font-bold flex items-center justify-center text-xs';
                } else {
                    ind.className = 'w-8 h-8 rounded-full bg-gray-300 text-gray-500 font-bold flex items-center justify-center text-xs';
                }
            }
        }

        function goBackFromStep5() {
            if (isMoneyCategory) {
                goToStep(3);
            } else {
                goToStep(4);
            }
        }

        // ======================== STEP 1: DONOR ========================
        function submitDonorAndProceed() {
            const name = document.getElementById('full-name').value.trim();
            const phone = document.getElementById('phone-number').value.trim();
            const email = document.getElementById('email-address').value.trim();
            const address = document.getElementById('address').value.trim();
            const occupation = document.getElementById('occupation').value.trim();
            const errEl = document.getElementById('step1-error');
            const ajaxErr = document.getElementById('step1-ajax-error');

            errEl.classList.add('hidden');
            ajaxErr.classList.add('hidden');

            if (!name || !phone || !email) {
                errEl.textContent = 'Please fill out all required fields.';
                errEl.classList.remove('hidden');
                return;
            }

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                errEl.textContent = 'Please enter a valid email address.';
                errEl.classList.remove('hidden');
                return;
            }

            const phoneRegex = /^[0-9+\-\s()]{7,15}$/;
            if (!phoneRegex.test(phone)) {
                errEl.textContent = 'Please enter a valid phone number.';
                errEl.classList.remove('hidden');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'save_donor');
            formData.append('name', name);
            formData.append('phone', phone);
            formData.append('email', email);
            formData.append('address', address);
            formData.append('occupation', occupation);

            fetch('donate.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        donorId = data.donor_id;
                        donorData = { name, phone, email, address, occupation };
                        goToStep(2);
                    } else {
                        ajaxErr.textContent = data.message || 'Failed to save donor details.';
                        ajaxErr.classList.remove('hidden');
                    }
                })
                .catch(() => {
                    ajaxErr.textContent = 'Network error. Please try again.';
                    ajaxErr.classList.remove('hidden');
                });
        }

        // ======================== STEP 2: CATEGORY ========================
        function selectCategory(el) {
            document.querySelectorAll('.category-card').forEach(c => c.classList.remove('selected'));
            el.classList.add('selected');
            selectedCategory = el.dataset.category;
            document.getElementById('step2-error').classList.add('hidden');
        }

        function confirmCategory() {
            if (!selectedCategory) {
                document.getElementById('step2-error').classList.remove('hidden');
                return;
            }
            document.getElementById('selected-category-input').value = selectedCategory;

            isMoneyCategory = (selectedCategory === 'Monetary Donation');

            // Show/hide fields based on category
            document.getElementById('monetary-fields').classList.toggle('hidden', !isMoneyCategory);
            document.getElementById('non-monetary-fields').classList.toggle('hidden', isMoneyCategory);

            document.getElementById('step3-subtitle').textContent = isMoneyCategory
                ? 'Enter the amount, transfer to the bank account, and upload the payment proof.'
                : 'Describe the items you wish to donate.';

            const hints = {
                'School Supplies': 'Example:\n50 Notebooks\n20 Pens\n10 School Bags',
                'Food & Grocery': 'Example:\n50 Food Packets\n25kg Rice\n10 Grocery Kits',
                'Clothes & Blankets': 'Example:\n15 Blankets\n20 Jackets',
                'Medical Support': 'Example:\nMedicines\nFirst Aid Kits\nMedical Equipment',
                'Volunteer Support': 'Tell us how you would like to help.',
                'Other': 'Describe your donation.'
            };

            const hintEl = document.getElementById('description-hint');
            if (hints[selectedCategory]) {
                hintEl.textContent = hints[selectedCategory];
            } else {
                hintEl.textContent = '';
            }

            const descEl = document.getElementById('donation-description');
            if (descEl && hints[selectedCategory]) {
                descEl.placeholder = hints[selectedCategory];
            }

            goToStep(3);
        }

        // ======================== STEP 3: DONATION DETAILS ========================
        // File upload handler
        document.addEventListener('DOMContentLoaded', () => {
            const proofUpload = document.getElementById('proof-upload');
            if (proofUpload) {
                proofUpload.addEventListener('change', function() {
                    const proofStatus = document.getElementById('proof-status');
                    const proofError = document.getElementById('proof-error');
                    proofStatus.classList.add('hidden');
                    proofError.classList.add('hidden');

                    if (this.files && this.files[0]) {
                        const file = this.files[0];
                        const ext = file.name.split('.').pop().toLowerCase();
                        const allowed = ['jpg', 'jpeg', 'png', 'pdf'];
                        if (allowed.indexOf(ext) === -1) {
                            alert('Invalid file type. Allowed: JPG, JPEG, PNG, PDF.');
                            this.value = '';
                            uploadComplete = false;
                            return;
                        }
                        if (file.size > 5 * 1024 * 1024) {
                            alert('File too large. Max 5MB allowed.');
                            this.value = '';
                            uploadComplete = false;
                            return;
                        }
                        proofStatus.classList.remove('hidden');
                        uploadComplete = true;
                    } else {
                        uploadComplete = false;
                    }
                });
            }
        });

        function confirmDonationDetails() {
            const category = selectedCategory;
            const errEl = document.getElementById('step3-error');
            errEl.classList.add('hidden');

            if (isMoneyCategory) {
                // Validate amount
                const amount = document.getElementById('donation-amount').value.trim();
                if (!amount || isNaN(amount) || parseFloat(amount) <= 0) {
                    errEl.textContent = 'Please enter a valid donation amount.';
                    errEl.classList.remove('hidden');
                    return;
                }

                // Validate UTR
                const utr = document.getElementById('utr-number').value.trim();
                if (!utr) {
                    errEl.textContent = 'Please enter the UTR / Transaction Reference Number.';
                    errEl.classList.remove('hidden');
                    return;
                }

                // Validate screenshot
                const proofFile = document.getElementById('proof-upload');
                if (!proofFile || !proofFile.files || !proofFile.files[0]) {
                    document.getElementById('proof-error').classList.remove('hidden');
                    errEl.textContent = 'Please upload the payment screenshot.';
                    errEl.classList.remove('hidden');
                    return;
                }
                document.getElementById('proof-error').classList.add('hidden');

                // Validate confirmation checkbox
                const confirmed = document.getElementById('payment-confirm').checked;
                if (!confirmed) {
                    document.getElementById('confirm-error').classList.remove('hidden');
                    errEl.textContent = 'Please confirm that you have completed the payment.';
                    errEl.classList.remove('hidden');
                    return;
                }
                document.getElementById('confirm-error').classList.add('hidden');

                donorData.amount = amount;
                donorData.utr = utr;
                donorData.description = 'Monetary Donation';

                // Money donations: skip step 4, go directly to step 5
                populateReviewAndGoToStep5();

            } else {
                const description = document.getElementById('donation-description').value.trim();
                if (!description) {
                    errEl.textContent = 'Please describe your donation.';
                    errEl.classList.remove('hidden');
                    return;
                }
                donorData.description = description;
                donorData.amount = '';
                donorData.utr = '';

                // Non-money: go to step 4
                goToStep(4);
            }

            donorData.category = category;
        }

        function populateReviewAndGoToStep5() {
            // Populate review
            document.getElementById('review-name').textContent = donorData.name;
            document.getElementById('review-phone').textContent = donorData.phone;
            document.getElementById('review-email').textContent = donorData.email;
            document.getElementById('review-occupation').textContent = donorData.occupation || 'Not provided';
            document.getElementById('review-address').textContent = donorData.address || 'Not provided';
            document.getElementById('review-category').textContent = donorData.category;
            document.getElementById('review-amount').textContent = donorData.amount ? '₹ ' + parseFloat(donorData.amount).toLocaleString('en-IN', {minimumFractionDigits: 2}) : 'N/A (in-kind)';
            document.getElementById('review-description').textContent = donorData.description || 'Monetary Donation';

            // Show/hide UTR row
            const utrRow = document.getElementById('review-utr-row');
            if (donorData.utr) {
                utrRow.classList.remove('hidden');
                document.getElementById('review-utr').textContent = donorData.utr;
            } else {
                utrRow.classList.add('hidden');
            }

            // Hide pickup section for monetary
            document.getElementById('review-pickup-section').classList.add('hidden');

            goToStep(5);
        }

        // ======================== COPY ADDRESS ========================
        function copyAddress() {
            const addr = [
                '<?= CLUB_NAME ?>',
                '<?= CLUB_ADDRESS_LINE1 ?>',
                '<?= CLUB_ADDRESS_LINE2 ?>',
                '<?= CLUB_ADDRESS_LINE3 ?>',
                '<?= CLUB_ADDRESS_LINE4 ?>',
                '<?= CLUB_ADDRESS_LINE5 ?>',
                '<?= CLUB_ADDRESS_LINE6 ?>',
                '<?= CLUB_ADDRESS_LINE7 ?>',
            ].join('\n');
            navigator.clipboard.writeText(addr).then(() => {
                const btn = event.currentTarget;
                const orig = btn.innerHTML;
                btn.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i> Copied!';
                setTimeout(() => { btn.innerHTML = orig; }, 2000);
            }).catch(() => {
                alert('Failed to copy address. Please copy manually.');
            });
        }

        // ======================== STEP 4: DELIVERY/PICKUP ========================
        function selectPickupOption(option) {
            document.querySelectorAll('input[name="pickup_option"]').forEach(r => r.checked = false);
            if (option === 'deliver') {
                document.querySelector('input[name="pickup_option"][value="deliver"]').checked = true;
                document.getElementById('club-address-box').classList.remove('hidden');
                document.getElementById('pickup-details-box').classList.add('hidden');
            } else {
                document.querySelector('input[name="pickup_option"][value="pickup"]').checked = true;
                document.getElementById('club-address-box').classList.add('hidden');
                document.getElementById('pickup-details-box').classList.remove('hidden');
            }
            document.getElementById('step4-error').classList.add('hidden');
        }

        function navigateFromStep4() {
            const selected = document.querySelector('input[name="pickup_option"]:checked');
            if (!selected) {
                document.getElementById('step4-error').classList.remove('hidden');
                return;
            }
            const option = selected.value;
            donorData.pickupOption = option;

            if (option === 'deliver') {
                donorData.pickupAddress = '';
                donorData.pickupDate = '';
                donorData.pickupNotes = '';
            } else {
                const addr = document.getElementById('pickup-address').value.trim();
                const date = document.getElementById('pickup-date').value;
                if (!addr || !date) {
                    document.getElementById('step4-error').textContent = 'Please fill in pickup address and date.';
                    document.getElementById('step4-error').classList.remove('hidden');
                    return;
                }
                donorData.pickupAddress = addr;
                donorData.pickupDate = date;
                donorData.pickupNotes = document.getElementById('pickup-notes').value.trim();
            }

            // Populate review
            document.getElementById('review-name').textContent = donorData.name;
            document.getElementById('review-phone').textContent = donorData.phone;
            document.getElementById('review-email').textContent = donorData.email;
            document.getElementById('review-occupation').textContent = donorData.occupation || 'Not provided';
            document.getElementById('review-address').textContent = donorData.address || 'Not provided';
            document.getElementById('review-category').textContent = donorData.category;
            document.getElementById('review-amount').textContent = 'N/A (in-kind)';
            document.getElementById('review-description').textContent = donorData.description;

            // Show pickup section
            document.getElementById('review-pickup-section').classList.remove('hidden');
            document.getElementById('review-utr-row').classList.add('hidden');

            const optLabel = option === 'deliver' ? 'I will deliver myself' : 'Please arrange pickup';
            document.getElementById('review-pickup-option').textContent = optLabel;

            if (option === 'pickup') {
                document.getElementById('review-pickup-address-row').classList.remove('hidden');
                document.getElementById('review-pickup-date-row').classList.remove('hidden');
                document.getElementById('review-pickup-notes-row').classList.remove('hidden');
                document.getElementById('review-pickup-address').textContent = donorData.pickupAddress;
                document.getElementById('review-pickup-date').textContent = donorData.pickupDate;
                document.getElementById('review-pickup-notes').textContent = donorData.pickupNotes || 'None';
            } else {
                document.getElementById('review-pickup-address-row').classList.add('hidden');
                document.getElementById('review-pickup-date-row').classList.add('hidden');
                document.getElementById('review-pickup-notes-row').classList.add('hidden');
            }

            goToStep(5);
        }

        // ======================== STEP 5: SUBMIT ========================
        function submitDonation() {
            const btn = document.getElementById('submit-btn');
            const btnText = document.getElementById('submit-btn-text');
            const btnSpinner = document.getElementById('submit-btn-spinner');
            const errEl = document.getElementById('step5-error');

            errEl.classList.add('hidden');
            btn.disabled = true;
            btnText.classList.add('hidden');
            btnSpinner.classList.remove('hidden');

            const formData = new FormData();
            formData.append('action', 'save_donation');
            formData.append('donor_id', donorId);
            formData.append('donation_type', donorData.category);
            formData.append('description', donorData.description);
            formData.append('amount', donorData.amount || '');
            formData.append('utr_number', donorData.utr || '');
            formData.append('pickup_option', donorData.pickupOption || '');
            formData.append('pickup_address', donorData.pickupAddress || '');
            formData.append('pickup_date', donorData.pickupDate || '');
            formData.append('pickup_notes', donorData.pickupNotes || '');

            // Append proof file if monetary
            const proofFile = document.getElementById('proof-upload');
            if (proofFile && proofFile.files && proofFile.files[0]) {
                formData.append('proof_file', proofFile.files[0]);
            }

            fetch('donate.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    btn.disabled = false;
                    btnText.classList.remove('hidden');
                    btnSpinner.classList.add('hidden');

                    if (data.success) {
                        donationId = data.donation_id;
                        showSuccessModal();
                    } else {
                        errEl.textContent = data.message || 'Failed to submit donation.';
                        errEl.classList.remove('hidden');
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    btnText.classList.remove('hidden');
                    btnSpinner.classList.add('hidden');
                    errEl.textContent = 'Network error. Please try again.';
                    errEl.classList.remove('hidden');
                });
        }

        // ======================== SUCCESS MODAL ========================
        function showSuccessModal() {
            document.getElementById('success-modal').classList.add('flex');
            document.getElementById('success-modal').classList.remove('hidden');
        }

        function closeSuccessModal() {
            document.getElementById('success-modal').classList.remove('flex');
            document.getElementById('success-modal').classList.add('hidden');
            window.location.href = 'index.php';
        }

        // Initialize step indicator
        document.addEventListener('DOMContentLoaded', () => {
            updateIndicator(1);
        });
    </script>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
