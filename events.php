<?php
// events.php
session_start();
include 'includes/db_connect.php'; // <-- must exist and expose $conn (mysqli)

// -----------------------------
// User / role logic
// -----------------------------
$userId   = isset($_SESSION['admin_id']) ? intval($_SESSION['admin_id']) : null;
$userRoleRaw = $_SESSION['admin_role'] ?? null;
$userName = $_SESSION['admin_name'] ?? 'Guest';

// Map to the frontend roles used in the UI: 'admin' | 'member' | 'guest'
if (in_array($userRoleRaw, ['President','Secretary','Treasurer'], true)) {
    $jsUserRole = 'admin';
    $isAdmin = true;
} elseif ($userRoleRaw === 'Member') {
    $jsUserRole = 'member';
    $isAdmin = false;
} else {
    // guest or any other role — force userId null to avoid FK constraint errors
    $jsUserRole = 'guest';
    $isAdmin = false;
    $userId = null;
}

// -----------------------------
// AJAX endpoints (voting & counts)
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    header('Content-Type: application/json; charset=utf-8');

    // Helper: run a query and return JSON error on failure
    function execOrFail($stmt) {
        if (!$stmt->execute()) {
            echo json_encode(['status'=>'error','message'=>'Database error: '.$stmt->error]);
            exit;
        }
        return $stmt;
    }

    try {

    // 1) Submit a vote (member or guest) -> returns JSON with updated counts
    if (isset($_POST['poll_event_id']) && isset($_POST['vote'])) {
        $event_id = intval($_POST['poll_event_id']);
        $vote = trim($_POST['vote']);
        $allowed = ['Yes','No','Maybe'];
        if (!in_array($vote, $allowed, true)) {
            echo json_encode(['status'=>'error','message'=>'Invalid vote']);
            exit;
        }

        // Member or Admin (has member_id)
        if ($jsUserRole === 'member' || $jsUserRole === 'admin') {
            if (!$userId) {
                echo json_encode(['status'=>'error','message'=>'Not logged in as member']);
                exit;
            }
            // Check if this member already has a vote for this event
            $stmt = $conn->prepare("SELECT poll_id FROM event_polling WHERE event_id = ? AND member_id = ?");
            $stmt->bind_param("ii", $event_id, $userId);
            execOrFail($stmt);
            $res = $stmt->get_result();

            if ($res && $res->num_rows > 0) {
                // Update
                $stmt2 = $conn->prepare("UPDATE event_polling SET is_attending = ?, guest_name = NULL, submitted_on = NOW() WHERE event_id = ? AND member_id = ?");
                $stmt2->bind_param("sii", $vote, $event_id, $userId);
                execOrFail($stmt2);
                $stmt2->close();
            } else {
                // Insert
                $stmt2 = $conn->prepare("INSERT INTO event_polling (event_id, member_id, is_attending, guest_name, submitted_on) VALUES (?, ?, ?, NULL, NOW())");
                $stmt2->bind_param("iis", $event_id, $userId, $vote);
                execOrFail($stmt2);
                $stmt2->close();
            }
            $stmt->close();
        } else {
            // Guest vote - guest_name required
            $guest_name = isset($_POST['guest_name']) ? trim($_POST['guest_name']) : '';
            if ($guest_name === '') {
                echo json_encode(['status'=>'error','message'=>'Guest name required']);
                exit;
            }
            // Insert a guest vote (guest may post multiple times)
            $stmtG = $conn->prepare("INSERT INTO event_polling (event_id, member_id, is_attending, guest_name, submitted_on) VALUES (?, NULL, ?, ?, NOW())");
            $stmtG->bind_param("iss", $event_id, $vote, $guest_name);
            execOrFail($stmtG);
            $stmtG->close();
        }

        // After insert/update, compute current counts
        $counts = ['Yes'=>0,'No'=>0,'Maybe'=>0];
        $stmtC = $conn->prepare("SELECT is_attending, COUNT(*) as cnt FROM event_polling WHERE event_id = ? GROUP BY is_attending");
        $stmtC->bind_param("i", $event_id);
        execOrFail($stmtC);
        $resC = $stmtC->get_result();
        while ($r = $resC->fetch_assoc()) {
            $k = $r['is_attending'];
            $counts[$k] = (int)$r['cnt'];
        }
        $stmtC->close();

        echo json_encode(['status'=>'success','counts'=>$counts]);
        exit;
    }

    // 2) Fetch counts for an event (guest uses this to view live counts)
    if (isset($_POST['fetch_counts']) && isset($_POST['event_id'])) {
        $event_id = intval($_POST['event_id']);
        $counts = ['Yes'=>0,'No'=>0,'Maybe'=>0];
        $stmtC = $conn->prepare("SELECT is_attending, COUNT(*) as cnt FROM event_polling WHERE event_id = ? GROUP BY is_attending");
        $stmtC->bind_param("i", $event_id);
        execOrFail($stmtC);
        $resC = $stmtC->get_result();
        while ($r = $resC->fetch_assoc()) {
            $k = $r['is_attending'];
            $counts[$k] = (int)$r['cnt'];
        }
        $stmtC->close();
        echo json_encode(['status'=>'success','counts'=>$counts]);
        exit;
    }

    // Unknown POST action -> return generic error
    echo json_encode(['status'=>'error','message'=>'Unknown POST request']);
    exit;

    } catch (Throwable $e) {
        echo json_encode(['status'=>'error','message'=>'Server error: '.$e->getMessage()]);
        exit;
    }
}

// -----------------------------
// Build JS-friendly events object
// -----------------------------
function map_event_row_to_js($row, $conn, $isAdmin) {
    // counts
    $counts = ['Yes'=>0,'No'=>0,'Maybe'=>0];
    $stmt = $conn->prepare("SELECT is_attending, COUNT(*) AS cnt FROM event_polling WHERE event_id = ? GROUP BY is_attending");
    $stmt->bind_param("i", $row['event_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $counts[$r['is_attending']] = (int)$r['cnt'];
    }
    $stmt->close();

    // admin details
    $details = [];
    if ($isAdmin) {
        $stmt2 = $conn->prepare("SELECT ep.poll_id, ep.member_id, ep.is_attending, ep.guest_name, ep.submitted_on, m.name AS member_name
                                 FROM event_polling ep
                                 LEFT JOIN members m ON ep.member_id = m.member_id
                                 WHERE ep.event_id = ?
                                 ORDER BY ep.submitted_on DESC");
        $stmt2->bind_param("i", $row['event_id']);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        while ($d = $res2->fetch_assoc()) {
            $details[] = [
                'poll_id' => (int)$d['poll_id'],
                'member_id' => $d['member_id'] !== null ? (int)$d['member_id'] : null,
                'name' => $d['member_name'] ?? null,
                'is_attending' => $d['is_attending'],
                'guest_name' => $d['guest_name'],
                'submitted_on' => $d['submitted_on'],
            ];
        }
        $stmt2->close();
    }

    // determine isPolling using poll_enabled from DB (and also check status/date)
    // poll_enabled = 1 => allowed, 0 => disabled by admin
    $isPolling = !empty($row['poll_enabled']) ? (bool)$row['poll_enabled'] : false;

    $today = strtotime('today');
    $startTs = !empty($row['start_date']) ? strtotime($row['start_date']) : null;
    $endTs = !empty($row['end_date']) ? strtotime($row['end_date']) : $startTs;

    $computedStatus = 'upcoming';
    if ($endTs !== null && $endTs < $today) {
        $computedStatus = 'completed';
    } elseif ($startTs !== null && $startTs <= $today && $endTs !== null && $endTs >= $today) {
        $computedStatus = 'ongoing';
    }

    $isPolling = !empty($row['poll_enabled']) ? (bool)$row['poll_enabled'] : false;
    if ($computedStatus !== 'upcoming') $isPolling = false;

    return [
        'id' => (int)$row['event_id'],
        'title' => $row['title'],
        'date' => !empty($row['start_date']) ? date('M j, Y', strtotime($row['start_date'])) : '',
        'startDate' => !empty($row['start_date']) ? $row['start_date'] : '',
        'endDate' => !empty($row['end_date']) ? $row['end_date'] : '',
        'location' => $row['location'] ?? '',
        'isPolling' => $isPolling,
        'hasCertificates' => !empty($row['certificates_enabled']) ? (bool)$row['certificates_enabled'] : false,
        'description' => $row['description'] ?? '',
        'image_url' => $row['image_url'] ?? '',
        'pollingCount' => ['yes' => $counts['Yes'], 'no' => $counts['No'], 'maybe' => $counts['Maybe']],
        'pollingDetails' => $details,
        '_type' => 'event',
        '_status' => $computedStatus
    ];
}

$allEventsJS = ['upcoming'=>[], 'ongoing'=>[], 'completed'=>[]];

// Query events table
$sql = "SELECT * FROM events ORDER BY start_date ASC";
if ($res = $conn->query($sql)) {
    while ($row = $res->fetch_assoc()) {
        $mapped = map_event_row_to_js($row, $conn, $isAdmin);
        $allEventsJS[$mapped['_status']][] = $mapped;
    }
    $res->free();
}

// Prepare JSON for embedding in JS
$jsAllEvents = json_encode($allEventsJS, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
$jsUserRoleEscaped = htmlspecialchars($jsUserRole, ENT_QUOTES, 'UTF-8');
$jsUserNameEscaped = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rotary Club Events & Polling</title>
    <!-- Load Tailwind CSS --><script src="https://cdn.tailwindcss.com"></script>
    <!-- Load Lucide icons for clean UI elements --><script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
    <style>
        /* Custom CSS variables for theme consistency */
        :root {
            --rotary-blue: #0A2342;
            --rotary-dark-blue: #1a365d;
            --rotary-yellow: #FFC000;
            --secondary-blue: #0056A0;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f7f9fb;
        }

        /* Input Focus Glow */
        input:focus {
            border-color: var(--rotary-yellow) !important;
            box-shadow: 0 0 0 2px rgba(255, 192, 0, 0.5); 
        }

        /* Header Match */
        .header-bg {
            background-color: var(--rotary-dark-blue);
        }

        /* Tab button active state */
        .tab-button.active {
            color: var(--rotary-yellow);
            border-bottom: 3px solid var(--rotary-yellow);
            font-weight: 600;
            transform: translateY(-2px);
            transition: all 0.2s ease-in-out;
        }
        .tab-button {
            transition: all 0.2s ease-in-out;
        }
        
        /* Card Hover Effect */
        .card-hover {
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }

        /* Polling Modal Blur/Overlay */
        .modal-overlay {
            background-color: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            transition: opacity 0.3s ease-in-out;
        }
        .modal-content {
            transition: transform 0.3s ease-in-out;
        }
        /* --- From projects.php: collaboration & report styles --- */
        .collaboration-form {
            border-top: 5px solid #facc15;
            border-top-left-radius: 1rem;
            border-top-right-radius: 1rem;
        }
        .heart-bounce {
            animation: bounce 0.8s infinite alternate;
            transform-origin: bottom;
        }
        @keyframes bounce {
            0% { transform: translateY(0) scale(1); }
            100% { transform: translateY(-15px) scale(1.1); }
        }
        .tab-button.active {
            color: #eab308;
            border-bottom: 3px solid #eab308;
            font-weight: 600;
        }
        input:focus, textarea:focus {
            border-color: #facc15 !important;
            box-shadow: 0 0 0 1px #facc15;
        }
        .submit-proposal-btn { transition: all 0.2s; }
        .submit-proposal-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="min-h-screen">

    <!-- 1. Header Section (Matching Projects Dashboard Style) -->
    <header class="py-8 bg-gray-50"> 
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 rounded-2xl shadow-xl header-bg flex flex-col md:flex-row justify-between items-center text-white">
            <!-- Logo & Title -->
            <div class="flex items-center space-x-4 mb-4 md:mb-0">
                <img src="assets/uploads/Logo/rotary-icon.png" alt="Rotary Logo" class="h-16 w-16 rounded-full object-cover border-2 border-yellow-400">
                <div>
                    <h1 class="text-4xl font-extrabold">Events & Polling</h1>
                    <p class="mt-1 text-base text-gray-300">Join our upcoming events and track our recent successes.</p>
                </div>
            </div>
            <!-- Back to Home Button -->
            <button onclick="window.location.href='index.php'"
                    class="px-6 py-3 bg-white text-gray-800 font-semibold rounded-lg hover:bg-yellow-400 hover:text-white transition duration-150 flex items-center space-x-2 shadow-md">
                <i data-lucide="home" class="w-5 h-5"></i>
                <span>Back to Home</span>
            </button>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Action Button (Polling Modal Trigger) -->
        <div class="flex justify-end mb-10">
            <button onclick="openPollingModal(SERVER_USER_ROLE)"
                    class="px-6 py-3 bg-[var(--rotary-yellow)] text-[var(--rotary-dark-blue)] font-bold rounded-lg hover:bg-yellow-600 transition duration-150 shadow-md flex items-center space-x-2">
                <i data-lucide="bar-chart-3" class="w-4 h-4 inline mr-2"></i>
                Open Event Poll
            </button>
        </div>

        <!-- Tabs Navigation -->
        <div class="border-b border-gray-200 mb-8">
            <nav class="flex space-x-6 sm:space-x-10" aria-label="Tabs">
                <button data-tab="upcoming" class="tab-button active pb-4 text-lg font-medium text-gray-500 hover:text-yellow-600 transition duration-150" onclick="switchTab('upcoming')">
                    Upcoming Events
                </button>
                <button data-tab="completed" class="tab-button pb-4 text-lg font-medium text-gray-500 hover:text-yellow-600 transition duration-150" onclick="switchTab('completed')">
                    Completed Events
                </button>
            </nav>
        </div>

        <!-- Events Content Container -->
        <div id="events-content" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <!-- Content will be dynamically injected here -->
        </div>

    </main>

    <!-- Event Report Modal -->
    <div id="report-modal" class="fixed inset-0 bg-black bg-opacity-70 hidden items-center justify-center z-50 p-4" onclick="toggleReportModal()">
        <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-4xl w-full h-5/6 overflow-y-auto transform transition-all duration-300 scale-100" onclick="event.stopPropagation()">
            <h2 class="text-3xl font-bold text-indigo-800 mb-4 border-b pb-2">Event Report: 2024</h2>
            <div class="text-gray-700 space-y-6">
                <p><strong>Overview:</strong> This year’s events celebrated impactful community engagement, new partnerships, and record participation across all initiatives.</p>
                <h3 class="font-bold text-xl pt-2">Highlights</h3>
                <ul class="list-disc pl-6 space-y-2">
                    <li>Record attendance at the Annual Cultural Festival.</li>
                    <li>Introduced the “Green Earth” awareness campaign.</li>
                    <li>Hosted three inter-city Rotary collaborations.</li>
                </ul>
                <p class="mt-6 italic text-sm text-gray-500">This is a summarized version of our 2024 Event Report. The complete document is available upon request.</p>
            </div>
            <button onclick="toggleReportModal()"
                    class="mt-8 px-6 py-2 bg-indigo-500 text-white font-semibold rounded-lg hover:bg-indigo-600 transition duration-150">
                Close Report
            </button>
        </div>
    </div>

    

    <!-- Polling Modal -->
    <div id="polling-modal" class="fixed inset-0 modal-overlay hidden items-center justify-center z-50 p-4" onclick="closePollingModal()">
        <div id="polling-content" class="bg-white rounded-xl shadow-2xl p-6 sm:p-8 max-w-lg w-full transform scale-95 modal-content border-t-8 border-[var(--rotary-blue)]" onclick="event.stopPropagation()">
            
            <button onclick="closePollingModal()" class="absolute top-4 right-4 text-gray-400 hover:text-red-500 transition">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
         
            <h3 class="text-2xl font-bold text-[var(--rotary-blue)] mb-2" id="poll-title">Event Polling: Annual Gala Attendance</h3>
            <p class="text-gray-600 mb-6" id="poll-subtitle">Are you planning to attend the event?</p>

            <!-- Dynamic Content Area -->
            <div id="poll-dynamic-view">
                <!-- Content injected by JS based on user type (guest/member/admin/results) -->
            </div>
        </div>
    </div>

    <!-- Footer Include Line (PHP) -->
    <?php include 'includes/footer.php'; ?>

    <script>
        // Server-provided events and user role (embedded by PHP)
        const SERVER_EVENTS = <?php echo $jsAllEvents ?: json_encode(['upcoming'=>[],'completed'=>[]]); ?>;
        const SERVER_USER_ROLE = "<?php echo $jsUserRoleEscaped; ?>";
        const SERVER_USER_NAME = "<?php echo $jsUserNameEscaped; ?>";

        // --- If server returned events, use them, otherwise fall back to the mock data below ---
        const hasServerEvents = (SERVER_EVENTS && (SERVER_EVENTS.upcoming.length || SERVER_EVENTS.completed.length));

        // --- MOCK DATA (kept as fallback for UI) ---
        const MOCK_EVENTS = [
            // Upcoming
            { id: 1, title: "Annual Blood Donation Drive", status: "upcoming", date: "Nov 25, 2025", location: "Community Hall, Virar West", description: "Our most vital service event, aimed at replenishing local blood banks.", imageUrl: "https://placehold.co/400x250/F87171/FFFFFF?text=Blood+Drive" },
            { id: 2, title: "Youth Digital Literacy Workshop", status: "upcoming", date: "Dec 10, 2025", location: "Local Library Annex", description: "Training local youth in essential digital skills for career readiness.", imageUrl: "https://placehold.co/400x250/0056A0/FFFFFF?text=Workshop" },
            { id: 3, title: "Founders' Day Dinner Gala", status: "upcoming", date: "Jan 15, 2026", location: "The Grand Regency", description: "A formal event celebrating our club's history and recognizing key contributors.", imageUrl: "https://placehold.co/400x250/0A2342/FFC000?text=Gala" },
            
            // Completed
            { id: 4, title: "Mega Tree Plantation Drive", status: "completed", date: "Sep 18, 2024", location: "Global City Park", description: "Successfully planted over 500 saplings across the park area.", imageUrl: "https://placehold.co/400x250/10B981/FFFFFF?text=Tree+Planting" },
            { id: 5, title: "Rural Health Camp", status: "completed", date: "Aug 01, 2024", location: "Jungle Village outskirts", description: "Provided free medical check-ups and basic medicines to 800+ villagers.", imageUrl: "https://placehold.co/400x250/4F46E5/FFFFFF?text=Health+Camp" },
            { id: 6, title: "Water Sanitation Project Phase I", status: "completed", date: "May 20, 2024", location: "East Virar School", description: "Installed modern filtration systems in three schools.", imageUrl: "https://placehold.co/400x250/FFC000/0A2342?text=Water+Project" },
        ];

        // Use data: prefer SERVER_EVENTS if available
        const DATA_EVENTS = hasServerEvents ? (() => {
            const list = [];
            // convert server shape to UI-friendly items
            const upstream = [];
            (SERVER_EVENTS.upcoming || []).forEach(e => upstream.push(Object.assign({}, {
                id: e.id,
                title: e.title,
                status: 'upcoming',
                date: e.date || '',
                location: e.location || '',
                description: e.description || '',
                imageUrl: e.image_url || 'https://placehold.co/400x250/0A2342/FFC000?text=Event',
                pollingCount: e.pollingCount || { yes:0, no:0, maybe:0 },
                pollingDetails: e.pollingDetails || [],
                isPolling: typeof e.isPolling !== 'undefined' ? e.isPolling : true
            })));
            const comp = [];
            (SERVER_EVENTS.completed || []).forEach(e => comp.push(Object.assign({}, {
                id: e.id,
                title: e.title,
                status: 'completed',
                date: e.date || '',
                location: e.location || '',
                description: e.description || '',
                imageUrl: e.image_url || 'https://placehold.co/400x250/0A2342/FFC000?text=Event',
                pollingCount: e.pollingCount || { yes:0, no:0, maybe:0 },
                pollingDetails: e.pollingDetails || [],
                isPolling: typeof e.isPolling !== 'undefined' ? e.isPolling : false
            })));
            return { upcoming: upstream, completed: comp };
        })() : { upcoming: MOCK_EVENTS.filter(e=>e.status==='upcoming'), completed: MOCK_EVENTS.filter(e=>e.status==='completed') };

        // --- the rest of your existing client-side JS starts below (unchanged) ---
    </script>

    <script>
        // Mock Polling Data (used only by client simulation)
        const MOCK_POLL_RESULTS = { "Yes": 0, "Maybe": 0, "No": 0 };
        const MOCK_ADMIN_VOTES = [ { name: "John Smith", vote: "Yes" }, { name: "Priya Kulkarni", vote: "No" }, { name: "Rajesh Verma", vote: "Yes" }, { name: "Sonia Desai", vote: "Maybe" } ];
        let currentView = 'upcoming'; // Tracks the active tab
        let currentPollUserType = SERVER_USER_ROLE || 'member'; // start from server role
        let selectedEventId = null;

        // --- EVENT CARD RENDERING (re-used from UI) ---
        const renderEventCard = (event) => {
            const dateObj = new Date(event.date);
            const isUpcoming = event.status === 'upcoming';
            const statusColor = isUpcoming ? 'bg-green-500' : 'bg-blue-500';
            const buttonText = isUpcoming ? 'Register Now' : 'View Impact Report';

            return `
                <div class="bg-white rounded-xl shadow-lg overflow-hidden card-hover border-t-4 border-b-4 ${isUpcoming ? 'border-yellow-500' : 'border-gray-300'}">
                    <div class="relative overflow-hidden">
                        <img src="${event.imageUrl}" alt="${event.title}" class="w-full h-48 object-cover transition-transform duration-500 group-hover:scale-105">
                        <div class="absolute top-0 right-0 ${statusColor} text-white font-bold p-2 text-sm rounded-bl-lg">
                            <i data-lucide="calendar" class="w-4 h-4 inline mr-1"></i> ${isUpcoming ? 'Upcoming' : 'Completed'}
                        </div>
                    </div>
                    <div class="p-5">
                        <h3 class="text-xl font-bold text-gray-800 mb-2">${event.title}</h3>
                        <p class="text-sm text-gray-600 mb-3">${(event.description||'').substring(0, 100)}...</p>

                        <div class="space-y-2 text-sm text-gray-700 mb-4 pt-2 border-t border-gray-100">
                            <p class="flex items-center">
                                <i data-lucide="clock" class="w-4 h-4 mr-2 text-yellow-600"></i> 
                                ${event.date || ''}
                            </p>
                            <p class="flex items-center">
                                <i data-lucide="map-pin" class="w-4 h-4 mr-2 text-yellow-600"></i> 
                                ${event.location || ''}
                            </p>
                        </div>

                        <div class="flex gap-2">
                            <button onclick="openPollingForEvent(${event.id})"
                                    class="flex-1 px-4 py-2 text-sm bg-[var(--rotary-blue)] text-white font-medium rounded-lg hover:bg-[var(--secondary-blue)] transition duration-150 shadow-md">
                                Open Poll
                            </button>
                            <a href="view_event_report.php?id=${event.id}"
                               class="flex-1 px-4 py-2 text-sm bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition duration-150 shadow-md text-center">
                                View Report
                            </a>
                        </div>
                    </div>
                </div>
            `;
        };

        const renderEvents = (status) => {
            currentView = status;
            const contentDiv = document.getElementById('events-content');
            const list = (DATA_EVENTS[status] || []);
            if (list.length === 0) {
                contentDiv.innerHTML = `<div class="lg:col-span-3 text-center py-12 bg-white rounded-xl shadow-lg">
                    <i data-lucide="alert-triangle" class="w-8 h-8 mx-auto text-yellow-600 mb-3"></i>
                    <p class="text-xl text-gray-600">No ${status} events found.</p>
                </div>`;
            } else {
                contentDiv.innerHTML = list.map(renderEventCard).join('');
            }
            lucide.createIcons(); // Re-initialize icons
        };

        const switchTab = (status) => {
            // Update button active state
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });
            const el = document.querySelector(`[data-tab="${status}"]`);
            if (el) el.classList.add('active');

            renderEvents(status);
        };

        // Event action when clicking Register/View - for now open relevant URL or modal
        function openEventAction(eventId, title, status) {
            if (status === 'upcoming') {
                // Goes to registration page (membership form) - open in new tab per your requirement
                window.open('membership_form.php?event_id=' + encodeURIComponent(eventId), '_blank');
            } else {
                // Completed -> view detailed report (could link to event_report_action.php)
                // For now we show the report modal from UI
                toggleReportModal();
            }
        }
        function openPollingForEvent(eventId) {
            // ✅ store selected event
            selectedEventId = eventId;

            // ✅ find event from both upcoming + completed
            const event = [...DATA_EVENTS.upcoming, ...DATA_EVENTS.completed]
                .find(e => e.id === eventId);

            if (!event) {
                alert('Event not found');
                return;
            }

            // ✅ update modal title
            const titleEl = document.getElementById('poll-title');
            const subtitleEl = document.getElementById('poll-subtitle');

            titleEl.textContent = 'Event Polling: ' + event.title;

            // ✅ check if polling enabled
            if (event.isPolling) {
                subtitleEl.textContent = 'Are you planning to attend this event?';
            } else {
                subtitleEl.textContent = '⚠ Polling is disabled for this event by Admin.';
            }

            // ✅ open modal with correct view
            updateModalView(event.isPolling ? SERVER_USER_ROLE : 'results');

            const modal = document.getElementById('polling-modal');
            const content = document.getElementById('polling-content');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            setTimeout(() => {
                content.classList.remove('scale-95');
                content.classList.add('scale-100');
            }, 10);
            document.body.style.overflow = 'hidden';
        }

        // --- POLLING MODAL RENDERING (client-side simulation; server endpoints exist above) ---
        const renderMemberView = () => {
            return `
                <div class="text-center space-y-4">
                    <p class="text-gray-700 font-semibold text-lg">Your Vote:</p>
                    <div class="grid grid-cols-3 gap-4">
                        <button onclick="submitVote('Yes')" class="px-4 py-3 bg-green-500 text-white font-bold rounded-lg shadow-md hover:bg-green-600 transition card-hover">
                            <i data-lucide="check" class="w-5 h-5 inline mr-1"></i> Yes
                        </button>
                        <button onclick="submitVote('Maybe')" class="px-4 py-3 bg-yellow-500 text-white font-bold rounded-lg shadow-md hover:bg-yellow-600 transition card-hover">
                            <i data-lucide="help-circle" class="w-5 h-5 inline mr-1"></i> Maybe
                        </button>
                        <button onclick="submitVote('No')" class="px-4 py-3 bg-red-500 text-white font-bold rounded-lg shadow-md hover:bg-red-600 transition card-hover">
                            <i data-lucide="x" class="w-5 h-5 inline mr-1"></i> No
                        </button>
                    </div>
                    <button onclick="updateModalView('results')" class="mt-4 text-sm text-gray-500 hover:text-gray-800 transition">View Current Results</button>
                </div>
            `;
        };

        const renderGuestView = () => {
            return `
                <div class="bg-gray-50 p-4 rounded-lg">
                    <p class="text-gray-700 font-semibold mb-4">Enter your name and vote:</p>
                    <div class="mb-4">
                        <label for="guest-name" class="block text-sm font-medium text-gray-700 mb-1">Your Name</label>
                        <input type="text" id="guest-name" placeholder="Enter your name" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-yellow-500">
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <button onclick="submitGuestVote('Yes')" class="px-4 py-3 bg-green-500 text-white font-bold rounded-lg shadow-md hover:bg-green-600 transition card-hover">
                            <i data-lucide="check" class="w-5 h-5 inline mr-1"></i> Yes
                        </button>
                        <button onclick="submitGuestVote('Maybe')" class="px-4 py-3 bg-yellow-500 text-white font-bold rounded-lg shadow-md hover:bg-yellow-600 transition card-hover">
                            <i data-lucide="help-circle" class="w-5 h-5 inline mr-1"></i> Maybe
                        </button>
                        <button onclick="submitGuestVote('No')" class="px-4 py-3 bg-red-500 text-white font-bold rounded-lg shadow-md hover:bg-red-600 transition card-hover">
                            <i data-lucide="x" class="w-5 h-5 inline mr-1"></i> No
                        </button>
                    </div>
                    <button onclick="updateModalView('results')" class="mt-4 text-sm text-gray-500 hover:text-gray-800 transition">View Current Results</button>
                </div>
            `;
        };
        
        const renderAdminView = () => {
            const totalVotes = MOCK_ADMIN_VOTES.length;
            const voteList = MOCK_ADMIN_VOTES.map(v => `
                <li class="flex justify-between items-center py-2 border-b last:border-b-0">
                    <span class="font-medium">${v.name}</span>
                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full 
                        ${v.vote === 'Yes' ? 'bg-green-100 text-green-800' : v.vote === 'Maybe' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'}">
                        ${v.vote}
                    </span>
                </li>
            `).join('');

            return `
                <div class="space-y-4">
                    <p class="font-bold text-lg text-indigo-700">Admin View: Detailed Member Votes (Total: ${totalVotes})</p>
                    <ul class="max-h-64 overflow-y-auto bg-white p-3 rounded-lg border">
                        ${voteList}
                    </ul>
                    <button onclick="updateModalView('results')" class="mt-4 w-full px-4 py-2 bg-indigo-500 text-white font-bold rounded-lg hover:bg-indigo-600 transition">
                        View Summary Chart
                    </button>
                </div>
            `;
        };

        const renderResultsView = () => {
            const results = MOCK_POLL_RESULTS;
            const total = Object.values(results).reduce((sum, count) => sum + count, 0);

            const getPercentage = (count) => (total > 0 ? (count / total) * 100 : 0).toFixed(0);

            return `
                <div class="space-y-4">
                    <p class="font-bold text-lg text-gray-800">Current Poll Results (Total Votes: ${total})</p>
                    
                    ${['Yes', 'Maybe', 'No'].map(vote => {
                        const count = results[vote] || 0;
                        const percent = getPercentage(count);
                        const color = vote === 'Yes' ? 'bg-green-500' : vote === 'Maybe' ? 'bg-yellow-500' : 'bg-red-500';
                        const text_color = vote === 'Yes' ? 'text-green-800' : vote === 'Maybe' ? 'text-yellow-800' : 'text-red-800';
                        
                        return `
                            <div>
                                <div class="flex justify-between mb-1 text-sm font-medium">
                                    <span>${vote} (${count} votes)</span>
                                    <span class="${text_color}">${percent}%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                    <div class="h-2.5 rounded-full ${color}" style="width: ${percent}%"></div>
                                </div>
                            </div>
                        `;
                    }).join('')}
                    
                    <button onclick="updateModalView('member')" class="mt-4 w-full px-4 py-2 bg-gray-200 text-gray-700 font-bold rounded-lg hover:bg-gray-300 transition">
                        Go Back to Voting
                    </button>
                </div>
            `;
        };

        const updateModalView = (viewType) => {
            currentPollUserType = viewType;
            const dynamicContent = document.getElementById('poll-dynamic-view');
            dynamicContent.innerHTML = '';
            //document.getElementById('poll-title').textContent = "Event Polling: Annual Gala Attendance";
            //document.getElementById('poll-subtitle').textContent = "Are you planning to attend the event?";

            switch (viewType) {
                case 'member':
                    dynamicContent.innerHTML = renderMemberView();
                    break;
                case 'guest':
                    dynamicContent.innerHTML = renderGuestView();
                    document.getElementById('poll-subtitle').textContent = "Enter your name and cast your vote.";
                    break;
                case 'admin':
                    dynamicContent.innerHTML = renderAdminView();
                    document.getElementById('poll-title').textContent = "Admin Poll Dashboard: Detailed Votes";
                    document.getElementById('poll-subtitle').textContent = "Management view for tracking member attendance responses.";
                    break;
                case 'results':
                    dynamicContent.innerHTML = renderResultsView();
                    document.getElementById('poll-subtitle').textContent = "Real-time summary of attendance responses.";
                    break;
                default:
                    dynamicContent.innerHTML = `<p class="text-red-500">Invalid poll view.</p>`;
            }
            lucide.createIcons(); // Re-initialize icons in the modal
        };

        const openPollingModal = (initialView = (SERVER_USER_ROLE || 'member')) => {
        // Pick the FIRST event in current tab (upcoming / completed)
        const activeList = DATA_EVENTS[currentView] || [];
            const currentEvent = activeList.find(e => e.id === selectedEventId) || activeList[0] || null;

            if (!currentEvent) {
                alert('No event available for polling in this section.');
                return;
            }

            // Always sync selectedEventId so submitVote/submitGuestVote work
            selectedEventId = currentEvent.id;

            const titleEl = document.getElementById('poll-title');
            const subtitleEl = document.getElementById('poll-subtitle');

            // Set event name in modal title
            titleEl.textContent = 'Event Polling: ' + currentEvent.title;

            // Show message based on poll_enabled (isPolling)
            if (currentEvent.isPolling) {
                subtitleEl.textContent = 'Are you planning to attend this event?';
            } else {
                subtitleEl.textContent = '⚠ Polling is disabled for this event by Admin.';
            }

            // If polling is disabled, still show modal but only results / info
            updateModalView(currentEvent.isPolling ? initialView : 'results');

            const modal = document.getElementById('polling-modal');
            const content = document.getElementById('polling-content');
            
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            // Trigger scale-in animation
            setTimeout(() => {
                content.classList.remove('scale-95');
                content.classList.add('scale-100');
            }, 10);
            document.body.style.overflow = 'hidden';
        };


        const closePollingModal = () => {
            const modal = document.getElementById('polling-modal');
            const content = document.getElementById('polling-content');

            content.classList.remove('scale-100');
            content.classList.add('scale-95');
            
            // Wait for animation to finish before hiding
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                document.body.style.overflow = '';
            }, 300);
        };

        // Submit vote -> calls server endpoint (AJAX) if you want live update
        const submitVote = (vote) => {
            const eventId = selectedEventId;
            if (!eventId) {
                alert('No event selected for polling.');
                return;
            }

            // Check polling status for this event
            const activeList = DATA_EVENTS[currentView] || [];
            const currentEvent = activeList.find(e => e.id === eventId) || activeList[0] || null;
            if (!currentEvent) {
                alert('No event found for voting.');
                return;
            }
            if (!currentEvent.isPolling) {
                alert('Polling is disabled for this event by Admin.');
                return;
            }

    // existing guest/member fetch() code stays below...


            // If user is guest, ask for name
            if (SERVER_USER_ROLE === 'guest') {
                const guestName = prompt('Please enter your name to submit guest vote:');
                if (!guestName) return;
                fetch('', {
                    method: 'POST',
                    headers: {'Content-Type':'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({ poll_event_id: eventId, vote: vote, guest_name: guestName })
                }).then(r => {
                    if (!r.ok) throw new Error('Server returned ' + r.status);
                    return r.json();
                }).then(j=>{
                    if (j.status === 'success') {
                        if (j.counts) {
                            MOCK_POLL_RESULTS.Yes = j.counts.Yes;
                            MOCK_POLL_RESULTS.No = j.counts.No;
                            MOCK_POLL_RESULTS.Maybe = j.counts.Maybe;
                        }
                        updateModalView('results');
                    } else {
                        alert('Error: ' + (j.message || 'Could not submit vote'));
                    }
                }).catch(e => alert('Error: ' + e.message));
            } else {
                // member/admin
                fetch('', {
                    method: 'POST',
                    headers: {'Content-Type':'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({ poll_event_id: eventId, vote: vote })
                }).then(r => {
                    if (!r.ok) throw new Error('Server returned ' + r.status);
                    return r.json();
                }).then(j=>{
                    if (j.status === 'success') {
                        if (j.counts) {
                            MOCK_POLL_RESULTS.Yes = j.counts.Yes;
                            MOCK_POLL_RESULTS.No = j.counts.No;
                            MOCK_POLL_RESULTS.Maybe = j.counts.Maybe;
                        }
                        updateModalView('results');
                    } else {
                        alert('Error: ' + (j.message || 'Could not submit vote'));
                    }
                }).catch(e => alert('Error: ' + e.message));
            }
        };
        
        const submitGuestVote = (vote) => {
            const guestName = document.getElementById('guest-name').value.trim();
            if (!guestName) {
                alert('Please enter your name to vote.');
                return;
            }
            const eventId = selectedEventId;
            if (!eventId) {
                alert('No event selected for polling.');
                return;
            }
            const activeList = [...DATA_EVENTS.upcoming, ...DATA_EVENTS.completed];
            const currentEvent = activeList.find(e => e.id === eventId);
            if (!currentEvent || !currentEvent.isPolling) {
                alert('Polling is disabled for this event.');
                return;
            }
            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({ poll_event_id: eventId, vote: vote, guest_name: guestName })
            })
            .then(r => {
                if (!r.ok) throw new Error('Server returned ' + r.status);
                return r.json();
            })
            .then(j => {
                if (j.status === 'success') {
                    if (j.counts) {
                        MOCK_POLL_RESULTS.Yes = j.counts.Yes;
                        MOCK_POLL_RESULTS.No = j.counts.No;
                        MOCK_POLL_RESULTS.Maybe = j.counts.Maybe;
                    }
                    updateModalView('results');
                } else {
                    alert('Error: ' + (j.message || 'Could not submit vote'));
                }
            })
            .catch(e => alert('Error: ' + e.message));
        };

        const handleGuestLogin = () => {
            const guestName = document.getElementById('guest-name').value.trim();
            if (guestName) {
                alert(`Welcome, ${guestName}. Viewing public poll results now.`);
                updateModalView('results');
            } else {
                alert('Please enter your name to proceed.');
            }
        };
        // --- Collaboration form & report modal helpers ---
        const handleProposalSubmit = (event) => {
            event.preventDefault();
            const name = document.getElementById('collab-name').value.trim();
            const email = document.getElementById('collab-email').value.trim();
            const proposal = document.getElementById('collab-proposal').value.trim();

            // Basic validation
            if (!name || !email || !proposal) {
                alert('⚠️ Please fill in all fields before submitting.');
                return;
            }

            // Send data to backend (collaboration_action.php)
            fetch('collaboration_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    name: name,
                    email: email,
                    proposal: proposal
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Reset form and show popup
                    document.getElementById('collaboration-form').reset();
                    document.getElementById('char-count').textContent = '150 characters remaining';
                    
                    const sm = document.getElementById('success-modal');
                    sm.classList.remove('hidden');
                    sm.classList.add('flex');
                    document.body.style.overflow = 'hidden';
                } else {
                    alert('❌ Failed to submit. Please try again.');
                }
            })
            .catch(err => {
                console.error('Error submitting proposal:', err);
                alert('⚠️ Network error while submitting proposal.');
            });
        };


        const closeSuccessModal = () => {
            const sm = document.getElementById('success-modal');
            sm.classList.add('hidden'); sm.classList.remove('flex');
            document.body.style.overflow = '';
        };

        // Event report modal toggle
        const toggleReportModal = () => {
            const modal = document.getElementById('report-modal');
            const isHidden = modal.classList.contains('hidden');
            modal.classList.toggle('hidden', !isHidden);
            modal.classList.toggle('flex', isHidden);
            document.body.style.overflow = isHidden ? 'hidden' : '';
        };

        // Character counter for proposal textarea (initialize listener)
        document.addEventListener('DOMContentLoaded', () => {
            const proposalTextarea = document.getElementById('collab-proposal');
            const charCountDisplay = document.getElementById('char-count');
            if (proposalTextarea && charCountDisplay) {
                const maxChars = parseInt(proposalTextarea.getAttribute('maxlength') || '150', 10);
                const updateCharCount = () => {
                    const remaining = maxChars - proposalTextarea.value.length;
                    charCountDisplay.textContent = `${remaining} characters remaining`;
                    charCountDisplay.classList.toggle('text-red-500', remaining <= 10);
                    charCountDisplay.classList.toggle('text-gray-500', remaining > 10);
                };
                proposalTextarea.addEventListener('input', updateCharCount);
                updateCharCount();
            }

            // init icons and default data render
            lucide.createIcons();
            // Use server data if available
            switchTab('upcoming');
            // Expose some functions globally for dev/test
            window.openPollingModal = openPollingModal;
            window.closePollingModal = closePollingModal;
            window.updateModalView = updateModalView;
            window.submitVote = submitVote;
            window.submitGuestVote = submitGuestVote;
            window.handleGuestLogin = handleGuestLogin;
        });
    </script>
</body>
</html>
