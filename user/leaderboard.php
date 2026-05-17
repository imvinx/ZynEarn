<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAuth();
$user = getCurrentUser();
$pageTitle = 'Leaderboard';
$userId = $user['id'];
$db = getDB();

$tab = $_GET['tab'] ?? 'earners';
$period = $_GET['period'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$periodWhere = '';
if ($period === 'weekly') {
    $periodWhere = ' AND e.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
} elseif ($period === 'monthly') {
    $periodWhere = ' AND e.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
}

$leaderboard = [];
$totalCount = 0;

switch ($tab) {
    case 'earners':
        $stmt = $db->prepare("SELECT u.id, u.username, u.avatar, u.level, COALESCE(SUM(e.amount), 0) as total FROM users u JOIN earnings e ON u.id = e.user_id WHERE e.status = 'credited'$periodWhere GROUP BY u.id ORDER BY total DESC LIMIT $perPage OFFSET $offset");
        $stmt->execute();
        $leaderboard = $stmt->fetchAll();
        $stmt = $db->prepare("SELECT COUNT(DISTINCT u.id) FROM users u JOIN earnings e ON u.id = e.user_id WHERE e.status = 'credited'$periodWhere");
        $stmt->execute();
        $totalCount = $stmt->fetchColumn();
        break;
    case 'referrers':
        $stmt = $db->prepare("SELECT u.id, u.username, u.avatar, u.level, COUNT(r.id) as total FROM users u JOIN referrals r ON u.id = r.referrer_id GROUP BY u.id ORDER BY total DESC LIMIT $perPage OFFSET $offset");
        $stmt->execute();
        $leaderboard = $stmt->fetchAll();
        $stmt = $db->prepare("SELECT COUNT(DISTINCT referrer_id) FROM referrals");
        $stmt->execute();
        $totalCount = $stmt->fetchColumn();
        break;
    case 'level':
        $stmt = $db->prepare("SELECT id, username, avatar, level, xp_points FROM users ORDER BY level DESC, xp_points DESC LIMIT $perPage OFFSET $offset");
        $stmt->execute();
        $leaderboard = $stmt->fetchAll();
        $totalCount = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        break;
    case 'active':
        $stmt = $db->prepare("SELECT u.id, u.username, u.avatar, u.level, COUNT(l.id) as total FROM users u JOIN activity_log l ON u.id = l.user_id WHERE l.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY u.id ORDER BY total DESC LIMIT $perPage OFFSET $offset");
        $stmt->execute();
        $leaderboard = $stmt->fetchAll();
        $stmt = $db->prepare("SELECT COUNT(DISTINCT l.user_id) FROM activity_log l WHERE l.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stmt->execute();
        $totalCount = $stmt->fetchColumn();
        break;
}
$totalPages = ceil($totalCount / $perPage);

$userRank = 0;
foreach ($leaderboard as $i => $entry) {
    if ($entry['id'] == $userId) {
        $userRank = $offset + $i + 1;
        break;
    }
}
if ($userRank === 0) {
    $userRank = '--';
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<style>
.leaderboard-tabs { display: flex; gap: var(--space-1); margin-bottom: var(--space-6); border-bottom: 1px solid var(--border-primary); padding-bottom: 0; overflow-x: auto; }
.leaderboard-tab { padding: var(--space-3) var(--space-5); font-size: var(--text-sm); font-weight: var(--weight-medium); color: var(--text-secondary); border: none; background: none; cursor: pointer; transition: var(--transition); border-bottom: 2px solid transparent; white-space: nowrap; }
.leaderboard-tab:hover { color: var(--text-primary); }
.leaderboard-tab.active { color: var(--primary); border-bottom-color: var(--primary); }
.period-filter { display: flex; gap: var(--space-2); margin-bottom: var(--space-4); }
.period-btn { padding: var(--space-1) var(--space-3); border-radius: var(--radius-full); font-size: var(--text-xs); font-weight: var(--weight-medium); border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-secondary); cursor: pointer; transition: var(--transition); }
.period-btn:hover { border-color: var(--border-light); color: var(--text-primary); }
.period-btn.active { background: var(--primary); border-color: var(--primary); color: white; }
.lb-card { display: flex; align-items: center; gap: var(--space-4); padding: var(--space-4); border-radius: var(--radius-md); transition: var(--transition); margin-bottom: var(--space-2); }
.lb-card:hover { background: var(--bg-card-hover); }
.lb-card.highlight { background: rgba(108,92,231,0.08); border: 1px solid rgba(108,92,231,0.2); }
.lb-rank { width: 36px; height: 36px; border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; font-size: var(--text-sm); font-weight: var(--weight-bold); flex-shrink: 0; }
.lb-rank.gold { background: linear-gradient(135deg, #FDCB6E, #F39C12); color: white; }
.lb-rank.silver { background: linear-gradient(135deg, #dfe6e9, #b2bec3); color: #2d3436; }
.lb-rank.bronze { background: linear-gradient(135deg, #E17055, #D63031); color: white; }
.lb-rank.default { background: rgba(108,92,231,0.1); color: var(--text-tertiary); }
.lb-avatar { width: 40px; height: 40px; border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; font-size: var(--text-base); font-weight: var(--weight-bold); flex-shrink: 0; }
.lb-info { flex: 1; }
.lb-name { font-size: var(--text-sm); font-weight: var(--weight-medium); color: var(--text-primary); display: flex; align-items: center; gap: var(--space-2); }
.lb-level { font-size: var(--text-xs); color: var(--text-tertiary); }
.lb-value { font-family: var(--font-display); font-size: var(--text-lg); font-weight: var(--weight-bold); color: var(--success); }
.lb-empty { text-align: center; padding: var(--space-10); color: var(--text-tertiary); }
.lb-empty i { font-size: var(--text-5xl); opacity: 0.3; margin-bottom: var(--space-4); display: block; }
@media (max-width: 768px) {
    .leaderboard-tabs { gap: 0; }
    .leaderboard-tab { padding: var(--space-3) var(--space-3); font-size: var(--text-xs); }
}
</style>

<div class="dashboard-wrapper">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-section">
            <div class="sidebar-section-title">Main Menu</div>
            <a href="/user/dashboard.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-home"></i></span><span class="sidebar-nav-text">Dashboard</span></a>
            <a href="/user/wallet.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-wallet"></i></span><span class="sidebar-nav-text">Wallet</span></a>
            <a href="/user/earnings.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-chart-line"></i></span><span class="sidebar-nav-text">Earnings</span></a>
            <a href="/user/offers.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-th"></i></span><span class="sidebar-nav-text">Offers</span></a>
            <a href="/user/tasks.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-check-circle"></i></span><span class="sidebar-nav-text">Tasks</span></a>
            <a href="/user/referrals.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-users"></i></span><span class="sidebar-nav-text">Referrals</span></a>
            <a href="/user/withdraw.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-cash-register"></i></span><span class="sidebar-nav-text">Withdraw</span></a>
            <a href="/user/deposit.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-plus-circle"></i></span><span class="sidebar-nav-text">Deposit</span></a>
        </div>
        <div class="sidebar-section">
            <div class="sidebar-section-title">Account</div>
            <a href="/user/profile.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-user"></i></span><span class="sidebar-nav-text">Profile</span></a>
            <a href="/user/settings.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-cog"></i></span><span class="sidebar-nav-text">Settings</span></a>
            <a href="/user/notifications.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-bell"></i></span><span class="sidebar-nav-text">Notifications</span></a>
            <a href="/user/leaderboard.php" class="sidebar-nav-item active"><span class="sidebar-nav-icon"><i class="fas fa-trophy"></i></span><span class="sidebar-nav-text">Leaderboard</span></a>
            <a href="/user/support.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-headset"></i></span><span class="sidebar-nav-text">Support</span></a>
            <a href="/auth/logout.php" class="sidebar-nav-item logout-item"><span class="sidebar-nav-icon"><i class="fas fa-sign-out-alt"></i></span><span class="sidebar-nav-text">Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <div class="dashboard-top-header">
            <div class="dashboard-greeting">
                <h1>Leaderboard</h1>
                <p>See how you rank against other users</p>
            </div>
            <div class="dashboard-actions">
                <div class="balance-display" onclick="toggleBalanceVisibility()">
                    <span class="balance-display-icon"><i class="fas fa-eye" id="eyeIcon"></i></span>
                    <span class="balance-display-amount" id="headerBalance"><?= formatCurrency(getUserBalance($userId)) ?></span>
                </div>
            </div>
        </div>

        <div>
            <div class="leaderboard-tabs" id="leaderboardTabs">
                <button class="leaderboard-tab <?= $tab === 'earners' ? 'active' : '' ?>" data-tab="earners" onclick="switchLeaderboardTab('earners')"><i class="fas fa-coins"></i> Top Earners</button>
                <button class="leaderboard-tab <?= $tab === 'referrers' ? 'active' : '' ?>" data-tab="referrers" onclick="switchLeaderboardTab('referrers')"><i class="fas fa-users"></i> Top Referrers</button>
                <button class="leaderboard-tab <?= $tab === 'level' ? 'active' : '' ?>" data-tab="level" onclick="switchLeaderboardTab('level')"><i class="fas fa-level-up-alt"></i> Highest Level</button>
                <button class="leaderboard-tab <?= $tab === 'active' ? 'active' : '' ?>" data-tab="active" onclick="switchLeaderboardTab('active')"><i class="fas fa-bolt"></i> Most Active</button>
            </div>

            <?php if ($tab === 'earners'): ?>
            <div class="period-filter">
                <button class="period-btn <?= $period === 'all' ? 'active' : '' ?>" onclick="setPeriod('all')">All Time</button>
                <button class="period-btn <?= $period === 'monthly' ? 'active' : '' ?>" onclick="setPeriod('monthly')">Monthly</button>
                <button class="period-btn <?= $period === 'weekly' ? 'active' : '' ?>" onclick="setPeriod('weekly')">Weekly</button>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($userRank !== '--'): ?>
        <div style="margin-bottom: var(--space-4); padding: var(--space-3) var(--space-4); background: rgba(108,92,231,0.08); border-radius: var(--radius-md); display: flex; align-items: center; gap: var(--space-3);">
            <i class="fas fa-flag" style="color: var(--primary);"></i>
            <span style="font-size: var(--text-sm); color: var(--text-secondary);">Your rank: <strong style="color: var(--text-primary);">#<?= $userRank ?></strong></span>
        </div>
        <?php endif; ?>

        <div class="dashboard-panel" id="leaderboardContent">
            <?php if (count($leaderboard) > 0): ?>
            <?php $rank = $offset + 1; foreach ($leaderboard as $entry): ?>
            <div class="lb-card <?= $entry['id'] == $userId ? 'highlight' : '' ?>">
                <div class="lb-rank <?= $rank === 1 ? 'gold' : ($rank === 2 ? 'silver' : ($rank === 3 ? 'bronze' : 'default')) ?>"><?= $rank ?></div>
                <?php if (!empty($entry['avatar'])): ?>
                <img src="<?= sanitize($entry['avatar']) ?>" alt="" class="lb-avatar">
                <?php else: ?>
                <div class="lb-avatar" style="background: var(--gradient-primary); color: white;"><?= strtoupper(substr($entry['username'], 0, 1)) ?></div>
                <?php endif; ?>
                <div class="lb-info">
                    <div class="lb-name"><?= sanitize($entry['username']) ?> <?= $entry['id'] == $userId ? '<span class="badge badge-primary badge-xs">You</span>' : '' ?></div>
                    <div class="lb-level">Level <?= $entry['level'] ?? 1 ?></div>
                </div>
                <div class="lb-value">
                    <?php if ($tab === 'earners'): ?><?= formatCurrency($entry['total']) ?>
                    <?php elseif ($tab === 'referrers'): ?><?= $entry['total'] ?> refs
                    <?php elseif ($tab === 'level'): ?>Lv.<?= $entry['level'] ?>
                    <?php elseif ($tab === 'active'): ?><?= $entry['total'] ?> actions
                    <?php endif; ?>
                </div>
            </div>
            <?php $rank++; endforeach; ?>
            <?php else: ?>
            <div class="lb-empty">
                <i class="fas fa-trophy"></i>
                <p style="font-size: var(--text-base); color: var(--text-secondary);">No data yet</p>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination" style="margin-top: var(--space-6);">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?tab=<?= $tab ?>&period=<?= $period ?>&page=<?= $i ?>" class="page-link <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <div class="bottom-nav-spacer"></div>
    </main>
</div>

<script>
let balanceVisible = true;
function toggleBalanceVisibility() {
    balanceVisible = !balanceVisible;
    const el = document.getElementById('headerBalance');
    const icon = document.getElementById('eyeIcon');
    if (balanceVisible) {
        el.textContent = '<?= formatCurrency(getUserBalance($userId)) ?>';
        icon.className = 'fas fa-eye';
    } else {
        el.textContent = '\u2022\u2022\u2022\u2022\u2022\u2022';
        icon.className = 'fas fa-eye-slash';
    }
}

function switchLeaderboardTab(tab) {
    window.location.href = '?tab=' + tab + '&period=<?= $period ?>&page=1';
}

function setPeriod(period) {
    window.location.href = '?tab=<?= $tab ?>&period=' + period + '&page=1';
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
