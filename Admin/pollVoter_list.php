<?php
// Admin/polling_Guest_list.php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

require __DIR__ . '/../includes/db_connect.php';

// Fetch all polling results
$sql = "
    SELECT 
        ep.poll_id,
        ep.guest_name,
        ep.is_attending,
        ep.submitted_on,
        ep.member_id,
        ep.event_id,
        e.title AS event_title,
        e.start_date
    FROM event_polling ep
    INNER JOIN events e ON ep.event_id = e.event_id
    ORDER BY ep.submitted_on DESC
";
$result = $conn->query($sql);

// Fetch members
$memberNames = [];
$memberQuery = $conn->query("SELECT member_id, name FROM members");
while ($row = $memberQuery->fetch_assoc()) {
    $memberNames[$row['member_id']] = $row['name'];
}

// Count Yes/No/Maybe
$countQuery = $conn->query("
    SELECT is_attending, COUNT(*) AS total
    FROM event_polling
    GROUP BY is_attending
");
$yes = $no = $maybe = 0;
while ($row = $countQuery->fetch_assoc()) {
    if ($row['is_attending'] === 'Yes') $yes = $row['total'];
    if ($row['is_attending'] === 'No') $no = $row['total'];
    if ($row['is_attending'] === 'Maybe') $maybe = $row['total'];
}

// Votes per event
$eventVotesQuery = $conn->query("
    SELECT e.title, COUNT(*) AS votes
    FROM event_polling ep
    INNER JOIN events e ON ep.event_id = e.event_id
    GROUP BY ep.event_id
");
$eventLabels = [];
$eventVotes = [];
while ($row = $eventVotesQuery->fetch_assoc()) {
    $eventLabels[] = $row['title'];
    $eventVotes[] = $row['votes'];
}
?>
<?php
$pageTitle = 'Poll Voters';
$activeNav = 'voters';
require __DIR__ . '/includes/admin_head.php';
require __DIR__ . '/includes/admin_header.php';
?>
<!-- Chart.js (not in admin_head) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .badge-yes { background-color: #d1fae5; color: #166534; padding: 0.125rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
    .badge-no { background-color: #fee2e2; color: #991b1b; padding: 0.125rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
    .badge-maybe { background-color: #fef3c9; color: #854d0e; padding: 0.125rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
</style>

<div class="card">
    <div class="card-body">

        <!-- Export Buttons -->
        <div class="flex gap-4 mb-6">
            <button onclick="exportCSV()"
                class="btn btn-primary">
                Export CSV
            </button>
            <!--<button onclick="exportExcel()"
                class="px-4 py-2 bg-green-500 text-white rounded-lg shadow hover:bg-green-600">
                Export Excel
            </button>
            <button onclick="window.print()"
                class="px-4 py-2 bg-red-500 text-white rounded-lg shadow hover:bg-red-600">
                Export PDF
            </button> -->
        </div>

        <!-- Graphs Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-10">

            <!-- Pie Chart: Yes / No / Maybe -->
            <div class="bg-white rounded-xl p-6 shadow">
                <h2 class="text-xl font-bold text-gray-700 mb-4">Attendance Summary</h2>
                <canvas id="pieChart"></canvas>
            </div>

            <!-- Bar Chart: Votes per event -->
            <div class="bg-white rounded-xl p-6 shadow">
                <h2 class="text-xl font-bold text-gray-700 mb-4">Votes Per Event</h2>
                <canvas id="barChart"></canvas>
            </div>

        </div>

        <div class="overflow-x-auto">
            <table id="pollTable" class="data-table">
                <thead>
                    <tr>
                        <th>Event Title</th>
                        <th>Event Date</th>
                        <th>Name</th>
                        <th>Response</th>
                        <th>Submitted On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <?php
                                if (!empty($row['member_id']) && isset($memberNames[$row['member_id']])) {
                                    $displayName = $memberNames[$row['member_id']] . " (Member)";
                                } else {
                                    $displayName = $row['guest_name'] . " (Guest)";
                                }
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2"><?= htmlspecialchars($row['event_title']) ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($row['start_date']) ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($displayName) ?></td>
                                <td class="px-4 py-2" data-status="<?= htmlspecialchars($row['is_attending']) ?>"><?= htmlspecialchars($row['is_attending']) ?></td>
                                <td class="px-4 py-2 text-gray-500">
                                    <?= date("d M Y, h:i A", strtotime($row['submitted_on'])) ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-6 text-gray-500">No polling responses found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-6 text-center">
            <a href="dashboard.php" class="btn btn-yellow">
                <i data-lucide="arrow-left" class="w-5 h-5"></i> Back to Dashboard
            </a>
        </div>

    </div>
</div>

<!-- JS for Charts -->
<script>
    // PIE Chart (Yes/No/Maybe)
    new Chart(document.getElementById('pieChart'), {
        type: 'pie',
        data: {
            labels: ['Yes', 'No', 'Maybe'],
            datasets: [{
                data: [<?= $yes ?>, <?= $no ?>, <?= $maybe ?>],
                backgroundColor: ['#10B981', '#EF4444', '#F59E0B']
            }]
        }
    });

    // BAR Chart (Votes per Event)
    new Chart(document.getElementById('barChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($eventLabels) ?>,
            datasets: [{
                label: 'Votes',
                data: <?= json_encode($eventVotes) ?>,
                backgroundColor: '#3B82F6'
            }]
        }
    });

    // CSV Export
    function exportCSV() {
        let table = document.getElementById("pollTable");
        let rows = [...table.rows];
        let csv = rows.map(row => [...row.cells].map(c => `"${c.innerText}"`).join(",")).join("\n");

        let blob = new Blob([csv], { type: "text/csv" });
        let url = URL.createObjectURL(blob);

        let a = document.createElement("a");
        a.href = url;
        a.download = "polling_guest_list.csv";
        a.click();
    }

    // Excel Export
    function exportExcel() {
        let tableHTML = document.getElementById("pollTable").outerHTML;
        let blob = new Blob([tableHTML], { type: "application/vnd.ms-excel" });

        let url = URL.createObjectURL(blob);
        let a = document.createElement("a");
        a.href = url;
        a.download = "polling_guest_list.xls";
        a.click();
    }

    // Badge styling on DOM load
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('#pollTable tbody td[data-status]').forEach(td => {
            let text = td.getAttribute('data-status');
            let cls = '';
            if (text === 'Yes') cls = 'badge-yes';
            else if (text === 'No') cls = 'badge-no';
            else if (text === 'Maybe') cls = 'badge-maybe';
            td.innerHTML = '<span class="' + cls + '">' + text + '</span>';
        });
    });
</script>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
