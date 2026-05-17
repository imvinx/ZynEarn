<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAuth();
$user = getCurrentUser();
$pageTitle = 'Earnings';
$userId = $user['id'];
$db = getDB();

$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM earnings WHERE user_id = ? AND status = 'credited'");
$stmt->execute([$userId]);
$totalEarned = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as month FROM earnings WHERE user_id = ? AND status = 'credited' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
$stmt->execute([$userId]);
$monthEarned = $stmt->fetch()['month'];

$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as today FROM earnings WHERE user_id = ? AND status = 'credited' AND DATE(created_at) = CURDATE()");
$stmt->execute([$userId]);
$todayEarned = $stmt->fetch()['today'];

$types = ['offerwall','shortlink','faucet','quiz','scratch','spin','video','survey','task','referral','bonus','daily','achievement'];
$breakdown = [];
foreach ($types as $t) {
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM earnings WHERE user_id = ? AND type = ? AND status = 'credited'");
    $stmt->execute([$userId, $t]);
    $breakdown[$t] = (float)$stmt->fetch()['total'];
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<style>
.earnings-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: var(--space-5);
    margin-bottom: var(--space-6);
}
.earnings-stat-card {
    background: var(--card-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    padding: var(--space-5);
    text-align: center;
}
.earnings-stat-value {
    font-family: var(--font-display);
    font-size: var(--text-3xl);
    font-weight: var(--weight-bold);
}
.earnings-stat-label {
    font-size: var(--text-sm);
    color: var(--text-secondary);
    margin-top: var(--space-1);
}
.chart-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--space-6);
    margin-bottom: var(--space-6);
}
.chart-box {
    background: var(--card-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    padding: var(--space-5);
}
.chart-box-title {
    font-family: var(--font-display);
    font-size: var(--text-base);
    font-weight: var(--weight-semibold);
    color: var(--text-primary);
    margin-bottom: var(--space-4);
}
.chart-container {
    position: relative;
    width: 100%;
    height: 260px;
}
.filter-bar {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    flex-wrap: wrap;
    margin-bottom: var(--space-4);
}
.filter-bar select, .filter-bar input {
    padding: var(--space-2) var(--space-3);
    background: var(--bg-input);
    border: 1px solid var(--border-primary);
    border-radius: var(--radius-md);
    color: var(--text-primary);
    font-size: var(--text-sm);
}
.filter-bar .btn {
    flex-shrink: 0;
}
@media (max-width: 992px) {
    .earnings-stats { grid-template-columns: 1fr; }
    .chart-section { grid-template-columns: 1fr; }
}
</style>

<div class="dashboard-wrapper">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-section">
            <div class="sidebar-section-title">Main Menu</div>
            <a href="/user/dashboard.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-home"></i></span><span class="sidebar-nav-text">Dashboard</span></a>
            <a href="/user/wallet.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-wallet"></i></span><span class="sidebar-nav-text">Wallet</span></a>
            <a href="/user/earnings.php" class="sidebar-nav-item active"><span class="sidebar-nav-icon"><i class="fas fa-chart-line"></i></span><span class="sidebar-nav-text">Earnings</span></a>
            <a href="/user/offers.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-th"></i></span><span class="sidebar-nav-text">Offers</span></a>
            <a href="/user/tasks.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-check-circle"></i></span><span class="sidebar-nav-text">Tasks</span></a>
            <a href="/user/quizzes.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-brain"></i></span><span class="sidebar-nav-text">Quizzes</span></a>
            <a href="/user/scratch.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-hand-paper"></i></span><span class="sidebar-nav-text">Scratch</span></a>
            <a href="/user/spin.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-circle-notch"></i></span><span class="sidebar-nav-text">Spin</span></a>
            <a href="/user/shortlinks.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-link"></i></span><span class="sidebar-nav-text">Shortlinks</span></a>
            <a href="/user/faucet.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-water"></i></span><span class="sidebar-nav-text">Faucet</span></a>
            <a href="/user/videos.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-video"></i></span><span class="sidebar-nav-text">Videos</span></a>
            <a href="/user/surveys.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-clipboard-list"></i></span><span class="sidebar-nav-text">Surveys</span></a>
            <a href="/user/daily.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-calendar-day"></i></span><span class="sidebar-nav-text">Daily</span></a>
            <a href="/user/achievements.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-trophy"></i></span><span class="sidebar-nav-text">Achievements</span></a>
            <a href="/user/missions.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-rocket"></i></span><span class="sidebar-nav-text">Missions</span></a>
        </div>
        <div class="sidebar-section">
            <div class="sidebar-section-title">Account</div>
            <a href="/user/referrals.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-users"></i></span><span class="sidebar-nav-text">Referrals</span></a>
            <a href="/user/withdraw.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-cash-register"></i></span><span class="sidebar-nav-text">Withdraw</span></a>
            <a href="/user/profile.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-user"></i></span><span class="sidebar-nav-text">Profile</span></a>
            <a href="/auth/logout.php" class="sidebar-nav-item logout-item"><span class="sidebar-nav-icon"><i class="fas fa-sign-out-alt"></i></span><span class="sidebar-nav-text">Logout</span></a>
        </div>
    </aside>
    <main class="main-content">
        <div class="dashboard-top-header">
            <div class="dashboard-greeting">
                <h1>Earnings History</h1>
                <p>Track all your earnings in one place</p>
            </div>
        </div>

        <div class="earnings-stats">
            <div class="earnings-stat-card">
                <div class="earnings-stat-value" style="color: var(--primary);"><?= formatCurrency($totalEarned) ?></div>
                <div class="earnings-stat-label">Total Earned</div>
            </div>
            <div class="earnings-stat-card">
                <div class="earnings-stat-value" style="color: var(--success);"><?= formatCurrency($monthEarned) ?></div>
                <div class="earnings-stat-label">This Month</div>
            </div>
            <div class="earnings-stat-card">
                <div class="earnings-stat-value" style="color: var(--accent);"><?= formatCurrency($todayEarned) ?></div>
                <div class="earnings-stat-label">Today</div>
            </div>
        </div>

        <div class="chart-section">
            <div class="chart-box">
                <div class="chart-box-title">Earnings by Type</div>
                <div class="chart-container"><canvas id="pieChart"></canvas></div>
            </div>
            <div class="chart-box">
                <div class="chart-box-title">Monthly Trend</div>
                <div class="chart-container"><canvas id="trendChart"></canvas></div>
            </div>
        </div>

        <div class="dashboard-panel">
            <div class="dashboard-panel-header">
                <div class="dashboard-panel-title">Earnings Log</div>
                <div style="display: flex; gap: var(--space-2); align-items: center;">
                    <button class="btn btn-sm btn-outline-light" onclick="exportCSV()"><i class="fas fa-download"></i> Export CSV</button>
                </div>
            </div>
            <div class="filter-bar">
                <select id="filterType" onchange="loadEarnings(1)">
                    <option value="">All Types</option>
                    <?php foreach ($types as $t): ?>
                    <option value="<?= $t ?>"><?= ucfirst(str_replace('_', ' ', $t)) ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="filterStatus" onchange="loadEarnings(1)">
                    <option value="">All Status</option>
                    <option value="credited">Credited</option>
                    <option value="pending">Pending</option>
                    <option value="rejected">Rejected</option>
                </select>
                <input type="date" id="filterDateFrom" onchange="loadEarnings(1)" placeholder="From">
                <input type="date" id="filterDateTo" onchange="loadEarnings(1)" placeholder="To">
                <button class="btn btn-sm btn-primary" onclick="loadEarnings(1)"><i class="fas fa-search"></i> Search</button>
            </div>
            <div class="table-container" id="earningsTableContainer">
                <div style="text-align: center; padding: var(--space-8);"><i class="fas fa-spinner fa-spin" style="font-size: var(--text-2xl); color: var(--primary);"></i><p style="margin-top: var(--space-2); color: var(--text-tertiary);">Loading...</p></div>
            </div>
            <div id="earningsPagination" class="pagination" style="margin-top: var(--space-4); display: flex; justify-content: center; gap: var(--space-2);"></div>
        </div>
        <div class="bottom-nav-spacer"></div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const breakdown = <?= json_encode($breakdown) ?>;

document.addEventListener('DOMContentLoaded', function() {
    loadEarnings(1);
    renderPieChart();
    renderTrendChart();
});

function renderPieChart() {
    const ctx = document.getElementById('pieChart').getContext('2d');
    const isDark = document.body.getAttribute('data-theme') !== 'light';
    const textColor = isDark ? '#8888aa' : '#666688';

    const labels = [];
    const values = [];
    const colors = ['#6C5CE7','#00CEC9','#FD79A8','#00B894','#FDCB6E','#74B9FF','#E17055','#A29BFE','#55EFC4','#F39C12','#0984E3','#E84393','#D63031'];

    for (const [type, val] of Object.entries(breakdown)) {
        if (val > 0) {
            labels.push(type.charAt(0).toUpperCase() + type.slice(1));
            values.push(val);
        }
    }

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: colors.slice(0, labels.length),
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: textColor, font: { size: 11 }, padding: 12 }
                }
            }
        }
    });
}

function renderTrendChart() {
    const ctx = document.getElementById('trendChart').getContext('2d');
    const isDark = document.body.getAttribute('data-theme') !== 'light';
    const textColor = isDark ? '#8888aa' : '#666688';
    const gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';

    fetch('/user/ajax/earnings_trend.php', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Earnings',
                        data: data.values,
                        borderColor: '#6C5CE7',
                        backgroundColor: function(context) {
                            const gradient = ctx.createLinearGradient(0, 0, 0, context.chart.height);
                            gradient.addColorStop(0, 'rgba(108, 92, 231, 0.3)');
                            gradient.addColorStop(1, 'rgba(108, 92, 231, 0.0)');
                            return gradient;
                        },
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#6C5CE7',
                        pointRadius: 3,
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: isDark ? '#1a1a35' : '#ffffff',
                            titleColor: textColor,
                            bodyColor: isDark ? '#e0e0ff' : '#1a1a2e',
                            borderColor: isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)',
                            borderWidth: 1,
                            cornerRadius: 8,
                            padding: 12,
                            callbacks: {
                                label: function(context) {
                                    return '<?= APP_CURRENCY_SYMBOL ?>' + context.parsed.y.toFixed(4);
                                }
                            }
                        }
                    },
                    scales: {
                        x: { grid: { color: gridColor, drawBorder: false }, ticks: { color: textColor, font: { size: 10 } } },
                        y: { grid: { color: gridColor, drawBorder: false }, ticks: { color: textColor, font: { size: 10 }, callback: function(v) { return '<?= APP_CURRENCY_SYMBOL ?>' + v.toFixed(2); } }, beginAtZero: true }
                    }
                }
            });
        }
    });
}

function loadEarnings(page) {
    const type = document.getElementById('filterType').value;
    const status = document.getElementById('filterStatus').value;
    const from = document.getElementById('filterDateFrom').value;
    const to = document.getElementById('filterDateTo').value;

    const container = document.getElementById('earningsTableContainer');
    container.innerHTML = '<div style="text-align: center; padding: var(--space-8);"><i class="fas fa-spinner fa-spin" style="font-size: var(--text-2xl); color: var(--primary);"></i><p style="margin-top: var(--space-2); color: var(--text-tertiary);">Loading...</p></div>';

    const params = new URLSearchParams({ page, type, status, from, to });
    fetch('/user/ajax/earnings_list.php?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            container.innerHTML = data.html;
            renderPagination(data.total_pages, data.current_page);
        }
    });
}

function renderPagination(totalPages, currentPage) {
    const el = document.getElementById('earningsPagination');
    if (totalPages <= 1) { el.innerHTML = ''; return; }
    let html = '';
    for (let i = 1; i <= totalPages; i++) {
        html += '<button class="btn btn-sm ' + (i === currentPage ? 'btn-primary' : 'btn-outline-light') + '" onclick="loadEarnings(' + i + ')">' + i + '</button>';
    }
    el.innerHTML = html;
}

function exportCSV() {
    const type = document.getElementById('filterType').value;
    const status = document.getElementById('filterStatus').value;
    const from = document.getElementById('filterDateFrom').value;
    const to = document.getElementById('filterDateTo').value;
    window.location.href = '/user/ajax/export_earnings.php?type=' + type + '&status=' + status + '&from=' + from + '&to=' + to;
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
