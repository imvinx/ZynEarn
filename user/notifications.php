<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAuth();
$user = getCurrentUser();
$pageTitle = 'Notifications';
$userId = $user['id'];
$db = getDB();

$filter = $_GET['type'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = '';
$params = [$userId];
if ($filter !== 'all') {
    $where = ' AND type = ?';
    $params[] = $filter;
}

$stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?$where");
$stmt->execute($params);
$totalCount = $stmt->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

$stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ?$where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$notifications = $stmt->fetchAll();

$stmt = $db->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$unreadCount = $stmt->fetch()['unread'];

$stmt = $db->prepare("SELECT DISTINCT type FROM notifications WHERE user_id = ? ORDER BY type");
$stmt->execute([$userId]);
$types = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<style>
.notif-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-6); flex-wrap: wrap; gap: var(--space-4); }
.notif-actions { display: flex; gap: var(--space-2); }
.notif-filter { display: flex; gap: var(--space-2); margin-bottom: var(--space-4); flex-wrap: wrap; }
.notif-filter-btn { padding: var(--space-2) var(--space-4); border-radius: var(--radius-full); font-size: var(--text-sm); font-weight: var(--weight-medium); border: 1px solid var(--border-primary); background: var(--bg-card); color: var(--text-secondary); cursor: pointer; transition: var(--transition); }
.notif-filter-btn:hover { border-color: var(--border-light); color: var(--text-primary); }
.notif-filter-btn.active { background: var(--primary); border-color: var(--primary); color: white; }
.notif-item { display: flex; align-items: flex-start; gap: var(--space-3); padding: var(--space-4); border-radius: var(--radius-md); transition: var(--transition); margin-bottom: var(--space-2); cursor: pointer; }
.notif-item:hover { background: var(--bg-card-hover); }
.notif-item.unread { background: rgba(108,92,231,0.05); border-left: 3px solid var(--primary); }
.notif-icon { width: 40px; height: 40px; border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; font-size: var(--text-base); flex-shrink: 0; }
.notif-content { flex: 1; min-width: 0; }
.notif-title { font-size: var(--text-sm); font-weight: var(--weight-medium); color: var(--text-primary); }
.notif-message { font-size: var(--text-xs); color: var(--text-secondary); margin-top: 2px; }
.notif-time { font-size: 10px; color: var(--text-tertiary); margin-top: var(--space-1); }
.notif-empty { text-align: center; padding: var(--space-10); color: var(--text-tertiary); }
.notif-empty i { font-size: var(--text-5xl); opacity: 0.3; margin-bottom: var(--space-4); display: block; }
@media (max-width: 768px) {
    .notif-header { flex-direction: column; align-items: flex-start; }
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
            <a href="/user/notifications.php" class="sidebar-nav-item active"><span class="sidebar-nav-icon"><i class="fas fa-bell"></i></span><span class="sidebar-nav-text">Notifications</span></a>
            <a href="/user/leaderboard.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-trophy"></i></span><span class="sidebar-nav-text">Leaderboard</span></a>
            <a href="/user/support.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-headset"></i></span><span class="sidebar-nav-text">Support</span></a>
            <a href="/auth/logout.php" class="sidebar-nav-item logout-item"><span class="sidebar-nav-icon"><i class="fas fa-sign-out-alt"></i></span><span class="sidebar-nav-text">Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <div class="dashboard-top-header">
            <div class="dashboard-greeting">
                <h1>Notifications</h1>
                <p>Stay updated with your account activity</p>
            </div>
            <div class="dashboard-actions">
                <div class="balance-display" onclick="toggleBalanceVisibility()">
                    <span class="balance-display-icon"><i class="fas fa-eye" id="eyeIcon"></i></span>
                    <span class="balance-display-amount" id="headerBalance"><?= formatCurrency(getUserBalance($userId)) ?></span>
                </div>
            </div>
        </div>

        <div class="notif-header">
            <div>
                <span style="font-size: var(--text-sm); color: var(--text-secondary);"><?= $unreadCount ?> unread / <?= $totalCount ?> total</span>
            </div>
            <div class="notif-actions">
                <?php if ($unreadCount > 0): ?>
                <button class="btn btn-sm btn-primary" onclick="markAllRead()"><i class="fas fa-check-double"></i> Mark All Read</button>
                <?php endif; ?>
                <?php if ($totalCount > 0): ?>
                <button class="btn btn-sm btn-ghost" style="color: var(--danger);" onclick="clearAll()"><i class="fas fa-trash"></i> Clear All</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="notif-filter">
            <button class="notif-filter-btn <?= $filter === 'all' ? 'active' : '' ?>" onclick="filterBy('all')">All</button>
            <?php foreach (['earning', 'referral', 'withdrawal', 'achievement', 'system', 'promotion'] as $t): ?>
            <?php if (in_array($t, $types)): ?>
            <button class="notif-filter-btn <?= $filter === $t ? 'active' : '' ?>" onclick="filterBy('<?= $t ?>')"><?= ucfirst($t) ?></button>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <div class="dashboard-panel" id="notifList">
            <?php if (count($notifications) > 0): ?>
            <?php foreach ($notifications as $n): ?>
            <div class="notif-item <?= !$n['is_read'] ? 'unread' : '' ?>" data-id="<?= $n['id'] ?>" onclick="markRead(<?= $n['id'] ?>, this)">
                <div class="notif-icon" style="background: rgba(<?= $n['type'] === 'earning' ? '0,184,148' : ($n['type'] === 'achievement' ? '108,92,231' : ($n['type'] === 'referral' ? '116,185,255' : ($n['type'] === 'withdrawal' ? '253,203,110' : '225,112,85'))) ?>,0.15); color: <?= $n['type'] === 'earning' ? 'var(--success)' : ($n['type'] === 'achievement' ? 'var(--primary)' : ($n['type'] === 'referral' ? 'var(--info)' : ($n['type'] === 'withdrawal' ? 'var(--warning)' : 'var(--danger)'))) ?>;">
                    <i class="fas fa-<?= $n['type'] === 'earning' ? 'coins' : ($n['type'] === 'achievement' ? 'trophy' : ($n['type'] === 'referral' ? 'user-plus' : ($n['type'] === 'withdrawal' ? 'cash-register' : 'bell'))) ?>"></i>
                </div>
                <div class="notif-content">
                    <div class="notif-title"><?= sanitize($n['title']) ?></div>
                    <div class="notif-message"><?= sanitize($n['message']) ?></div>
                    <div class="notif-time"><?= timeAgo($n['created_at']) ?></div>
                </div>
                <?php if (!$n['is_read']): ?>
                <span class="badge badge-primary badge-sm" style="align-self: center;">New</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="notif-empty">
                <i class="fas fa-bell"></i>
                <p style="font-size: var(--text-base); color: var(--text-secondary); margin-bottom: var(--space-2);">No notifications yet</p>
                <p style="font-size: var(--text-sm); color: var(--text-tertiary);">You'll see notifications here when something happens.</p>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination" style="margin-top: var(--space-6);">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?type=<?= $filter ?>&page=<?= $i ?>" class="page-link <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <div class="bottom-nav-spacer"></div>
    </main>
</div>

<div class="modal-backdrop" id="clearModalBackdrop"></div>
<div class="modal modal-sm" id="clearModal">
    <div class="modal-header">
        <div class="modal-title">Clear All Notifications</div>
        <button class="modal-close" onclick="document.getElementById('clearModal').classList.remove('active');document.getElementById('clearModalBackdrop').classList.remove('active');"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
        <p style="color: var(--text-secondary); font-size: var(--text-sm); margin-bottom: var(--space-4);">Are you sure you want to clear all notifications? This action cannot be undone.</p>
        <div style="display: flex; gap: var(--space-3);">
            <button class="btn btn-ghost" onclick="document.getElementById('clearModal').classList.remove('active');document.getElementById('clearModalBackdrop').classList.remove('active');">Cancel</button>
            <button class="btn btn-danger" onclick="confirmClear()"><i class="fas fa-trash"></i> Clear All</button>
        </div>
    </div>
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

function markRead(id, el) {
    if (!el.classList.contains('unread')) return;
    const formData = new FormData();
    formData.append('id', id);
    formData.append('csrf_token', '<?= csrf_token() ?>');
    fetch('/user/ajax/mark_notification_read.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            el.classList.remove('unread');
            const badge = el.querySelector('.badge');
            if (badge) badge.remove();
        }
    });
}

function markAllRead() {
    const formData = new FormData();
    formData.append('csrf_token', '<?= csrf_token() ?>');
    fetch('/user/ajax/mark_all_read.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.querySelectorAll('.notif-item.unread').forEach(el => {
                el.classList.remove('unread');
                const badge = el.querySelector('.badge');
                if (badge) badge.remove();
            });
            location.reload();
        }
    });
}

function clearAll() {
    document.getElementById('clearModal').classList.add('active');
    document.getElementById('clearModalBackdrop').classList.add('active');
}

function confirmClear() {
    const formData = new FormData();
    formData.append('csrf_token', '<?= csrf_token() ?>');
    fetch('/user/ajax/clear_notifications.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Failed to clear');
        }
    });
}

function filterBy(type) {
    window.location.href = '?type=' + type;
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
