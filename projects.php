<?php
session_start();
include 'includes/db_connect.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/website_settings.php';
$ws = getWebsiteSettings($conn);

// Handle AJAX form submission for collaborations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_collab') {
    // Expecting: name, email, proposal
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $proposal = trim($_POST['proposal'] ?? '');

    // Basic validation
    if ($name === '' || $email === '' || $proposal === '') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }

    // Prepared statement to insert collaboration
    $stmt = $conn->prepare("INSERT INTO collaborations (name, email, proposal, submitted_on) VALUES (?, ?, ?, NOW())");
    if ($stmt === false) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'DB prepare failed.']);
        exit;
    }
    $stmt->bind_param('sss', $name, $email, $proposal);
    $ok = $stmt->execute();
    if ($ok) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => 'Proposal submitted. Thank you!']);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'DB insert failed.']);
    }
    $stmt->close();
    exit;
}

// Fetch projects from DB
$projects = [];
$sql = "SELECT project_id, title, description, status, start_date, end_date, collaborator, image_url, created_at
        FROM projects
        ORDER BY created_at DESC";
if ($res = $conn->query($sql)) {
    while ($row = $res->fetch_assoc()) {
        // Normalize/ensure keys exist and convert dates to ISO strings for JS
        $projects[] = [
            'id' => (int)$row['project_id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'status' => $row['status'],
            'startDate' => $row['start_date'],
            'endDate' => $row['end_date'],
            'collaborator' => $row['collaborator'],
            'imageUrl' => $row['image_url'] ?: '',
            'createdAt' => $row['created_at']
        ];
    }
    $res->close();
}

// Helper: if projects images are stored as relative path, ensure path is usable in HTML.
// We will use imageUrl as-is; client JS will fallback to placeholder if empty.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($ws['website_name']) ?> - Our Projects</title>
    <!-- Load Tailwind CSS --><script src="https://cdn.tailwindcss.com"></script>
    <!-- Load Lucide icons for clean UI elements --><script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
    <style>
        /* Custom CSS for interactivity and aesthetics */
        :root {
            --primary-color: #facc15; /* Tailwind yellow-400 */
            --primary-dark: #eab308; /* Tailwind yellow-600 */
            --header-bg-color: #1a365d; /* Dark blue for header */
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f7f9fb;
        }

        /* Collaboration Form Top Border & Shadow */
        .collaboration-form {
            border-top: 5px solid var(--primary-color);
            border-top-left-radius: 1rem;
            border-top-right-radius: 1rem;
        }

        /* Success Modal Animation */
        .heart-bounce {
            animation: bounce 0.8s infinite alternate;
            transform-origin: bottom;
        }

        @keyframes bounce {
            0% { transform: translateY(0) scale(1); }
            100% { transform: translateY(-15px) scale(1.1); }
        }

        /* Tab button active state */
        .tab-button.active {
            color: var(--primary-dark);
            border-bottom: 3px solid var(--primary-dark);
            font-weight: 600;
        }

        /* Input Focus Styling */
        input:focus, textarea:focus {
            border-color: var(--primary-color) !important;
            box-shadow: 0 0 0 1px var(--primary-color);
        }

        /* Submit button hover effect */
        .submit-proposal-btn {
            transition: all 0.2s;
        }
        .submit-proposal-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
        }

        /* Slide-in details panel (from right) */
        #detail-panel {
            position: fixed;
            top: 0;
            right: -100%;
            height: 100vh;
            width: min(540px, 95%);
            background: #ffffff;
            box-shadow: -20px 0 50px rgba(10,35,66,0.2);
            z-index: 60;
            transition: right 300ms ease;
            overflow-y: auto;
        }
        #detail-panel.open { right: 0; }
        #detail-panel .panel-header {
            background: linear-gradient(90deg, #0A2342, #4F46E5);
            color: #fff;
            padding: 24px;
        }
        #detail-panel .panel-body { padding: 20px; }
        #detail-panel img { max-width: 100%; height: auto; border-radius: 8px; }

        .card-hover { transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);}
        .card-hover:hover { transform: translateY(-5px) scale(1.02); box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15), 0 3px 6px rgba(0, 0, 0, 0.1);}
    </style>
</head>
<body class="min-h-screen">

    <!-- Header Section (Matching Event Page Style - Dark Blue Floating Rectangle) -->
     <header class="py-8 bg-gray-50"><div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 rounded-2xl shadow-xl bg-[var(--header-bg-color)] flex flex-col md:flex-row justify-between items-center text-white">
            <div class="flex items-center space-x-4 mb-4 md:mb-0">
                <!-- keep your logo path as requested -->
                <img src="<?= e($ws['website_logo']) ?>" alt="<?= e($ws['website_short_name']) ?> Logo" class="h-16 w-16 rounded-full object-cover border-2 border-yellow-400">
                <div>
                    <h1 class="text-4xl font-extrabold">Project Dashboard</h1>
                    <p class="mt-1 text-base text-gray-300">Explore our vision, mission, and current initiatives.</p>
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

        <!-- Action Buttons (Meet Our Team, Add Project Report, & Add Project) -->
         <div class="flex flex-col sm:flex-row gap-4 mb-10 justify-center sm:justify-end">
             
             <!-- Meet Our Team Button -->
            <button onclick="window.location.href = 'team.php'"
                    class="flex-1 sm:flex-none w-full sm:w-auto px-6 py-3 bg-yellow-500 text-white font-bold rounded-lg hover:bg-yellow-600 transition duration-150 shadow-md">
                <i data-lucide="users" class="w-4 h-4 inline mr-2"></i>
                Meet Our Team
            </button>

        </div>

        <!-- Tabs Navigation --><div class="border-b border-gray-200 mb-6">
            <nav class="flex space-x-6 sm:space-x-10" aria-label="Tabs">
                <button data-tab="all" class="tab-button active pb-4 text-sm font-medium text-gray-500 hover:text-yellow-600 transition duration-150" aria-current="page">
                    All Projects
                </button>
                <button data-tab="upcoming" class="tab-button pb-4 text-sm font-medium text-gray-500 hover:text-yellow-600 transition duration-150">
                    Upcoming
                </button>
                <button data-tab="ongoing" class="tab-button pb-4 text-sm font-medium text-gray-500 hover:text-yellow-600 transition duration-150">
                    Ongoing
                </button>
                <button data-tab="completed" class="tab-button pb-4 text-sm font-medium text-gray-500 hover:text-yellow-600 transition duration-150">
                    Completed
                </button>
            </nav>
        </div>

        <!-- Projects Content Container --><div id="projects-content" class="bg-gray-50 p-2 rounded-2xl shadow-md hover:shadow-xl transition duration-300 relative card-hover">
            <!-- Content will be dynamically injected here --></div>


        <!-- --- Collaboration Form (Left) & Report/Donation Stack (Right) --- -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mt-16">

            <!-- Left Column: Collaboration Form (2/3 width) -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-xl p-6 md:p-8 collaboration-form h-full">
                    <h2 class="text-2xl font-bold text-gray-800 mb-2">Want to Collaborate?</h2>
                    <p class="text-gray-500 mb-6">Submit your proposal below. We are excited to hear your ideas!</p>

                    <form id="collaboration-form" onsubmit="submitCollaborationForm(collaborationForm); return false;">
                        <input type="hidden" name="action" value="submit_collab">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                            <!-- Name / Organization --><div>
                                <label for="collab-name" class="block text-sm font-medium text-gray-700 mb-1">Name / Organization</label>
                                <input type="text" id="collab-name" name="name" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-yellow-500 focus:border-yellow-500 transition duration-150">
                            </div>
                            <!-- Contact Email --><div>
                                <label for="collab-email" class="block text-sm font-medium text-gray-700 mb-1">Contact Email</label>
                                <input type="email" id="collab-email" name="email" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-yellow-500 focus:border-yellow-500 transition duration-150">
                            </div>
                        </div>

                        <!-- Project Proposal --><div class="mb-6">
                            <label for="collab-proposal" class="block text-sm font-medium text-gray-700 mb-1">Project Proposal (Max 150 chars)</label>
                            <textarea id="collab-proposal" name="proposal" rows="3" maxlength="150" required
                                      placeholder="Describe your Project ideas, goals and all..."
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg resize-none focus:ring-yellow-500 focus:border-yellow-500 transition duration-150"></textarea>
                            <p id="char-count" class="text-xs text-gray-500 text-right mt-1">150 characters remaining</p>
                        </div>

                        <!-- Submit Button --><button type="submit"
                                class="submit-proposal-btn w-full px-6 py-3 bg-yellow-500 text-white font-bold rounded-lg shadow-md hover:bg-yellow-600 transition duration-150">
                            Submit Proposal
                        </button>
                    </form>
                </div>
            </div>

            <!-- Right Column: Report Card & Donation Card Stacked (1/3 width) -->
            <div class="lg:col-span-1 flex flex-col space-y-8">
                
                <!-- 1. View Project Report Card (Top of stack) -->
                <div id="project-report-card" class="bg-white rounded-xl shadow-xl p-6 border-t-4 border-indigo-500 flex flex-col items-center text-center">
                    <div class="w-20 h-20 bg-indigo-100 rounded-full flex items-center justify-center mb-4 card-hover">
                        <i data-lucide="scroll-text" class="w-8 h-8 text-indigo-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">Rotary Events</h3>
                    <p class="text-gray-600 mb-6 text-sm">
                        All Upcoming and Completed Events.
                    </p>
                    <button id="view-report-button" onclick="window.location.href = 'events.php'";
                            class="w-full px-6 py-3 bg-indigo-500 text-white font-bold rounded-lg shadow-md hover:bg-indigo-600 transition duration-150">
                        View Events.
                    </button>
                </div>
                
                <!-- 2. Donation Card (Bottom of stack) -->
                <div class="bg-white rounded-xl shadow-xl p-6 border-t-4 border-yellow-500 flex flex-col items-center text-center flex-grow">
                    <!-- Placeholder Image/Icon --><div class="w-20 h-20 bg-yellow-100 rounded-full flex items-center justify-center mb-4">
                        <i data-lucide="hand-coins" class="w-8 h-8 text-yellow-600"></i>
                    </div>

                    <h3 class="text-xl font-bold text-gray-800 mb-2">Support Our Mission</h3>
                    <p class="text-gray-600 mb-6 text-sm flex-grow">
                        Your contribution helps turn these proposals into reality and keeps the lights on!
                    </p>

                    <button onclick="window.location.href = 'donate.php'"
                            class="w-full px-6 py-3 bg-green-500 text-white font-bold rounded-lg shadow-md hover:bg-green-600 transition duration-150 mt-auto">
                        Donate Now
                    </button>
                </div>
            </div>
        </div>

    </main>

    <!-- Success Modal Popup (Hidden by default) -->
    <div id="success-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4" onclick="closeModal()">
        <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-sm w-full transform transition-all duration-300 scale-100" onclick="event.stopPropagation()">
            <div class="flex flex-col items-center text-center">
                <!-- Interactive Bouncing Heart SVG -->
                <div class="heart-bounce mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="red" stroke="red" stroke-width="0" stroke-linecap="round" stroke-linejoin="round" class="text-red-500">
                        <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>
                    </svg>
                </div>

                <h3 class="text-2xl font-bold text-gray-800 mb-2">Proposal Submitted!</h3>
                <p class="text-gray-600 mb-6">
                    Thank You for your interest in collaborating. We will review your fantastic ideas and get back to you soon.
                </p>
                <button onclick="closeModal()"
                        class="px-6 py-2 bg-yellow-500 text-white font-semibold rounded-lg hover:bg-yellow-600 transition duration-150">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Project Report Modal (New - Hidden by default) -->
    <div id="report-modal" class="fixed inset-0 bg-black bg-opacity-70 hidden items-center justify-center z-50 p-4" onclick="toggleReportModal()">
        <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-4xl w-full h-5/6 overflow-y-auto transform transition-all duration-300 scale-100" onclick="event.stopPropagation()">
            <h2 class="text-3xl font-bold text-indigo-800 mb-4 border-b pb-2">Annual Project Report: 2024</h2>
            <div class="text-gray-700 space-y-6">
                <!-- report content unchanged -->
                <p><strong>Executive Summary:</strong> The year 2024 marked significant growth and impact across all our core initiatives. We successfully launched three major projects...</p>
                <!-- ... (rest of your original report markup unchanged) -->
            </div>
            <button onclick="toggleReportModal()"
                    class="mt-8 px-6 py-2 bg-indigo-500 text-white font-semibold rounded-lg hover:bg-indigo-600 transition duration-150">
                Close Report
            </button>
        </div>
    </div>

    <!-- Slide-in Right Panel for Project Details -->
    <div id="detail-panel" aria-hidden="true">
        <div class="panel-header flex items-center justify-between">
            <div>
                <h3 id="detail-title" class="text-2xl font-bold"></h3>
                <p id="detail-status" class="text-sm opacity-90"></p>
            </div>
            <div class="flex items-center space-x-3">
                <button id="detail-close" title="Close panel" class="px-3 py-2 rounded bg-white/10 hover:bg-white/20 text-white">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
        </div>
        <div class="panel-body">
            <img id="detail-image" src="" alt="Project Image" class="mb-4" onerror="this.src='https://placehold.co/600x300/0A2342/FFC000?text=No+Image'">
            <p id="detail-desc" class="text-gray-700 mb-4"></p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm text-gray-600">
                <div><strong>Start:</strong> <span id="detail-start"></span></div>
                <div><strong>End:</strong> <span id="detail-end"></span></div>
                <div class="sm:col-span-2"><strong>Collaborator:</strong> <span id="detail-collab"></span></div>
            </div>

            <div class="mt-6 flex gap-3">
                <button onclick="closeDetailPanel()" class="px-4 py-2 bg-yellow-500 text-white rounded-lg">Close</button>
            </div>
        </div>
    </div>

    <script>
        // Insert projects data from PHP
        const projectsData = <?php echo json_encode($projects, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;

        // Utility: format date to readable string (keeps original behavior)
        const formatDate = (dateString) => {
            if (!dateString) return 'N/A';
            const d = new Date(dateString);
            if (isNaN(d)) return dateString;
            return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        };

        // Render functions (kept UI exactly; replaced console.log with openDetailPanel calls)
        const renderAllProjects = (projects) => {
            const content = document.getElementById('projects-content');
            content.className = 'space-y-6';
            content.innerHTML = projects.map(project => `
                <div class="bg-white rounded-xl shadow-lg overflow-hidden transition duration-300 hover:shadow-xl p-4 md:p-6 flex flex-col md:flex-row space-y-4 md:space-y-0 md:space-x-6 items-start">
                    <img src="${project.imageUrl || 'https://placehold.co/100x100/cccccc/ffffff?text=No+Image'}" alt="Project Image"
                         class="w-20 h-20 sm:w-24 sm:h-24 object-cover rounded-lg flex-shrink-0 border-2 border-gray-100">

                    <div class="flex-grow">
                        <h3 class="text-xl font-bold text-gray-800 mb-1">${escapeHtml(project.title)}</h3>
                        <p class="text-sm text-gray-600 mb-3">${escapeHtml(project.description)}</p>

                        <div class="grid grid-cols-2 lg:grid-cols-4 text-xs gap-y-2 mb-4">
                            <p class="text-gray-500 font-medium">
                                <span class="text-yellow-600 font-bold mr-1">Start:</span> ${formatDate(project.startDate)}
                            </p>
                            <p class="text-gray-500 font-medium">
                                <span class="text-yellow-600 font-bold mr-1">End:</span> ${formatDate(project.endDate)}
                            </p>
                            <p class="text-gray-500 font-medium col-span-2">
                                <span class="text-yellow-600 font-bold mr-1">Collaborator:</span> ${escapeHtml(project.collaborator)}
                            </p>
                        </div>

                        <button onclick="openDetailPanel(${project.id})"
                                class="px-4 py-2 text-sm bg-gray-100 text-gray-700 font-medium rounded-lg hover:bg-gray-200 transition duration-150">
                            View Details
                        </button>
                    </div>
                </div>
            `).join('');
            lucide.createIcons();
        };

        const renderCardProjects = (projects, status) => {
            const content = document.getElementById('projects-content');
            content.className = 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8';
            
            if (projects.length === 0) {
                content.innerHTML = `<div class="lg:col-span-3 text-center py-12 bg-gray-50 rounded-xl">
                    <i data-lucide="folder-open" class="w-8 h-8 mx-auto text-gray-400 mb-3"></i>
                    <p class="text-lg text-gray-600">No ${status} projects at the moment. Check back soon!</p>
                </div>`;
                lucide.createIcons();
                return;
            }

            content.innerHTML = projects.map(project => `
                <div class="bg-white rounded-xl shadow-lg overflow-hidden transition duration-300 hover:shadow-xl flex flex-col">
                    <img src="${(project.imageUrl && project.imageUrl.replace) ? project.imageUrl.replace('100x100','400x200') : (project.imageUrl || 'https://placehold.co/400x200/cccccc/ffffff?text=No+Image')}" alt="Project Image"
                         class="w-full h-40 object-cover border-b border-gray-100">

                    <div class="p-5 flex flex-col flex-grow">
                        <h3 class="text-xl font-bold text-gray-800 mb-2">${escapeHtml(project.title)}</h3>
                        <span class="inline-block px-3 py-1 text-xs font-semibold rounded-full mb-3
                              ${project.status === 'Ongoing' ? 'bg-indigo-100 text-indigo-800' :
                                project.status === 'Upcoming' ? 'bg-yellow-100 text-yellow-800' :
                                'bg-green-100 text-green-800'}">
                            ${escapeHtml(project.status)}
                        </span>

                        <p class="text-sm text-gray-600 mb-4 flex-grow">${escapeHtml((project.description||'').substring(0,100))}...</p>

                        <div class="text-xs text-gray-500 space-y-1 mb-4 pt-2 border-t border-gray-100">
                            <p><span class="font-semibold text-gray-700">Start:</span> ${formatDate(project.startDate)}</p>
                            <p><span class="font-semibold text-gray-700">End:</span> ${formatDate(project.endDate)}</p>
                            <p><span class="font-semibold text-gray-700">Partner:</span> ${escapeHtml(project.collaborator)}</p>
                        </div>

                        <button onclick="openDetailPanel(${project.id})"
                                class="mt-auto w-full px-4 py-2 text-sm bg-yellow-500 text-white font-medium rounded-lg hover:bg-yellow-600 transition duration-150">
                            View Details
                        </button>
                    </div>
                </div>
            `).join('');
            lucide.createIcons();
        };

        // Simple client-side escaping to avoid injection when using innerHTML
        function escapeHtml(unsafe) {
            if (!unsafe && unsafe !== 0) return '';
            return String(unsafe)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Tabs
        const switchTab = (tabName) => {
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            const btn = document.querySelector(`.tab-button[data-tab="${tabName}"]`);
            if (btn) btn.classList.add('active');

            if (tabName === 'all') {
                renderAllProjects(projectsData);
            } else {
                const filtered = projectsData.filter(p => p.status && p.status.toLowerCase() === tabName);
                const statusTitle = tabName.charAt(0).toUpperCase() + tabName.slice(1);
                renderCardProjects(filtered, statusTitle);
            }
        };

        // Slide-in panel functions
        const detailPanel = document.getElementById('detail-panel');
        const detailClose = document.getElementById('detail-close');
        detailClose && detailClose.addEventListener('click', closeDetailPanel);

        function openDetailPanel(projectId) {
            const project = projectsData.find(p => p.id === projectId);
            if (!project) return alert('Project not found.');

            document.getElementById('detail-title').textContent = project.title || 'Untitled';
            document.getElementById('detail-status').textContent = project.status || '';
            document.getElementById('detail-image').src = project.imageUrl || 'https://placehold.co/600x300/0A2342/FFC000?text=No+Image';
            document.getElementById('detail-desc').textContent = project.description || '';
            document.getElementById('detail-start').textContent = formatDate(project.startDate);
            document.getElementById('detail-end').textContent = formatDate(project.endDate);
            document.getElementById('detail-collab').textContent = project.collaborator || 'N/A';
            detailPanel.classList.add('open');
            detailPanel.setAttribute('aria-hidden','false');
            // Lock body scroll
            document.body.style.overflow = 'hidden';
        }

        function closeDetailPanel() {
            detailPanel.classList.remove('open');
            detailPanel.setAttribute('aria-hidden','true');
            document.body.style.overflow = '';
        }

        // Character count for proposal textarea and form submission
        document.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();

            // expose collaborationForm variable referenced in inline onsubmit attribute
            window.collaborationForm = document.getElementById('collaboration-form');

            // init tabs
            switchTab('all');
            document.querySelectorAll('.tab-button').forEach(button => {
                button.addEventListener('click', () => switchTab(button.getAttribute('data-tab')));
            });

            // char count logic
            const proposalTextarea = document.getElementById('collab-proposal');
            const charCountDisplay = document.getElementById('char-count');
            const maxChars = 150;
            const updateCharCount = () => {
                const remaining = maxChars - (proposalTextarea.value.length || 0);
                charCountDisplay.textContent = `${remaining} characters remaining`;
                charCountDisplay.classList.toggle('text-red-500', remaining <= 10);
                charCountDisplay.classList.toggle('text-gray-500', remaining > 10);
            };
            if (proposalTextarea) {
                proposalTextarea.addEventListener('input', updateCharCount);
                updateCharCount();
            }
        });

        // Submit collaboration form via AJAX to the same file
        async function submitCollaborationForm(formRef) {
            try {
                if (!formRef) formRef = document.getElementById('collaboration-form');
                const formData = new FormData(formRef);
                // ensure action is set
                formData.set('action', 'submit_collab');

                const resp = await fetch(window.location.pathname, {
                    method: 'POST',
                    body: formData,
                });

                const data = await resp.json();
                if (data.success) {
                    // show modal (your existing modal)
                    document.getElementById('collaboration-form').reset();
                    document.getElementById('char-count').textContent = '150 characters remaining';
                    document.getElementById('success-modal').classList.remove('hidden');
                    document.getElementById('success-modal').classList.add('flex');
                } else {
                    alert('Error: ' + (data.message || 'Unable to submit proposal.'));
                }
            } catch (err) {
                console.error(err);
                alert('An unexpected error occurred while submitting the proposal.');
            }
        }

        // Close success modal helper
        function closeModal() {
            const sm = document.getElementById('success-modal');
            sm.classList.add('hidden');
            sm.classList.remove('flex');
        }

        // Report modal toggle
        const reportModal = document.getElementById('report-modal');
        function toggleReportModal() {
            const hidden = reportModal.classList.contains('hidden');
            reportModal.classList.toggle('hidden', !hidden);
            reportModal.classList.toggle('flex', hidden);
            document.body.style.overflow = hidden ? 'hidden' : '';
        }
    </script>
</body>
</html>
