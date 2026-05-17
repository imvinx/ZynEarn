<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAuth();
$user = getCurrentUser();
$pageTitle = 'Shortlinks';
$userId = $user['id'];
$db = getDB();

$stmt = $db->prepare("SELECT * FROM shortlinks WHERE status = 'active' AND (max_visits IS NULL OR total_clicks < max_visits) ORDER BY payout_per_click DESC");
$stmt->execute();
$shortlinks = $stmt->fetchAll();

$stmt = $db->prepare("SELECT sl.title, sc.rewarded, sc.created_at FROM shortlink_clicks sc JOIN shortlinks sl ON sc.shortlink_id = sl.id WHERE sc.user_id = ? ORDER BY sc.created_at DESC LIMIT 10");
$stmt->execute([$userId]);
$clickHistory = $stmt->fetchAll();

$totalBalance = getUserBalance($userId);
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<style>
.shortlinks-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: var(--space-4);
}
.shortlink-card {
    background: var(--card-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    padding: var(--space-5);
    transition: var(--transition);
    display: flex;
    flex-direction: column;
}
.shortlink-card:hover { border-color: var(--border-light); transform: translateY(-2px); box-shadow: var(--shadow-md); }
.shortlink-card-title {
    font-size: var(--text-base);
    font-weight: var(--weight-semibold);
    color: var(--text-primary);
    margin-bottom: var(--space-2);
}
.shortlink-card-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: var(--space-3);
}
.shortlink-card-payout {
    font-family: var(--font-display);
    font-size: var(--text-lg);
    font-weight: var(--weight-bold);
    color: var(--success);
}
.shortlink-card-clicks {
    font-size: var(--text-xs);
    color: var(--text-tertiary);
}
.shortlink-countdown {
    font-family: var(--font-mono);
    font-size: var(--text-sm);
    color: var(--warning);
    display: none;
    margin-top: var(--space-2);
    text-align: center;
}
.history-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--space-3);
    background: var(--bg-card-hover);
    border-radius: var(--radius-md);
    margin-bottom: var(--space-2);
    font-size: var(--text-sm);
}
.history-title { flex: 1; color: var(--text-primary); font-weight: var(--weight-medium); }
.history-status { font-size: var(--text-xs); }
.history-date { font-size: var(--text-xs); color: var(--text-tertiary); }
</style>

<div class="dashboard-wrapper">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-section">
            <div class="sidebar-section-title">Main Menu</div>
            <a href="/user/dashboard.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-home"></i></span><span class="sidebar-nav-text">Dashboard</span></a>
            <a href="/user/wallet.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-wallet"></i></span><span class="sidebar-nav-text">Wallet</span></a>
            <a href="/user/earnings.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-chart-line"></i></span><span class="sidebar-nav-text">Earnings</span></a>
            <a href="/user/offers.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-th"></i></span><span class="sidebar-nav-text">Offers</span></a>
            <a href="/user/tasks.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-check-circle"></i></span><span class="sidebar-nav-text">Tasks</span></a>
            <a href="/user/quizzes.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-brain"></i></span><span class="sidebar-nav-text">Quizzes</span></a>
            <a href="/user/scratch.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-hand-paper"></i></span><span class="sidebar-nav-text">Scratch</span></a>
            <a href="/user/spin.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-circle-notch"></i></span><span class="sidebar-nav-text">Spin</span></a>
            <a href="/user/shortlinks.php" class="sidebar-nav-item active"><span class="sidebar-nav-icon"><i class="fas fa-link"></i></span><span class="sidebar-nav-text">Shortlinks</span></a>
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
                <h1>Shortlinks</h1>
                <p>Visit links and earn rewards</p>
            </div>
            <div class="balance-display">
                <span class="balance-display-amount" id="balanceDisplay"><?= formatCurrency($totalBalance) ?></span>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 300px; gap: var(--space-6);">
            <div>
                <div class="dashboard-panel-header">
                    <div class="dashboard-panel-title">Available Links</div>
                </div>
                <div class="shortlinks-grid">
                    <?php if (count($shortlinks) > 0): ?>
                    <?php foreach ($shortlinks as $link): ?>
                    <div class="shortlink-card" data-id="<?= $link['id'] ?>">
                        <div class="shortlink-card-title"><i class="fas fa-link" style="color: var(--primary); margin-right: var(--space-2);"></i> <?= sanitize($link['title']) ?></div>
                        <div class="shortlink-card-meta">
                            <span class="shortlink-card-payout">+<?= formatCurrency($link['payout_per_click']) ?></span>
                            <span class="shortlink-card-clicks"><?= $link['total_clicks'] ?> clicks<?= $link['max_visits'] ? ' / ' . $link['max_visits'] : '' ?></span>
                        </div>
                        <button class="btn btn-sm btn-primary btn-block" onclick="visitLink(<?= $link['id'] ?>, this)"><i class="fas fa-external-link-alt"></i> Visit & Earn</button>
                        <div class="shortlink-countdown" id="countdown_<?= $link['id'] ?>"></div>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: var(--space-12); color: var(--text-tertiary);">
                        <i class="fas fa-link" style="font-size: var(--text-5xl); opacity: 0.3; margin-bottom: var(--space-4); display: block;"></i>
                        <p>No shortlinks available right now.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <div class="dashboard-panel">
                    <div class="dashboard-panel-header">
                        <div class="dashboard-panel-title">Click History</div>
                    </div>
                    <?php if (count($clickHistory) > 0): ?>
                    <?php foreach ($clickHistory as $ch): ?>
                    <div class="history-item">
                        <span class="history-title"><?= sanitize($ch['title']) ?></span>
                        <span class="badge badge-<?= $ch['rewarded'] ? 'success' : 'warning' ?> badge-sm"><?= $ch['rewarded'] ? 'Paid' : 'Pending' ?></span>
                        <span class="history-date"><?= timeAgo($ch['created_at']) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div style="padding: var(--space-6); text-align: center; color: var(--text-tertiary);">
                        <i class="fas fa-clock" style="font-size: var(--text-3xl); opacity: 0.3; margin-bottom: var(--space-2); display: block;"></i>
                        <p style="font-size: var(--text-sm);">No clicks yet</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="bottom-nav-spacer"></div>
    </main>
</div>

<script>
function visitLink(linkId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

    const formData = new FormData();
    formData.append('link_id', linkId);
    formData.append('csrf_token', '<?= csrf_token() ?>');

    fetch('/user/ajax/visit_shortlink.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.open(data.url, '_blank');
            btn.innerHTML = '<i class="fas fa-check"></i> Verifying...';
            startVerificationCountdown(linkId, btn, data.wait_seconds || 10);
        } else {
            alert(data.error || 'Failed');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-external-link-alt"></i> Visit & Earn';
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-external-link-alt"></i> Visit & Earn';
    });
}

function startVerificationCountdown(linkId, btn, seconds) {
    const countdownEl = document.getElementById('countdown_' + linkId);
    countdownEl.style.display = 'block';
    btn.style.display = 'none';

    let remaining = seconds;
    const interval = setInterval(() => {
        remaining--;
        countdownEl.textContent = '⏱ ' + remaining + 's until reward...';
        if (remaining <= 0) {
            clearInterval(interval);
            verifyVisit(linkId, countdownEl);
        }
    }, 1000);
}

function verifyVisit(linkId, countdownEl) {
    const formData = new FormData();
    formData.append('link_id', linkId);
    formData.append('csrf_token', '<?= csrf_token() ?>');

    fetch('/user/ajax/verify_shortlink.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            countdownEl.textContent = '✅ +' + data.formatted_amount + ' earned!';
            countdownEl.style.color = 'var(--success)';
            if (data.new_balance_formatted) {
                document.getElementById('balanceDisplay').textContent = data.new_balance_formatted;
            }
            setTimeout(() => {
                countdownEl.style.display = 'none';
                const btn = countdownEl.parentElement.querySelector('.btn');
                if (btn) {
                    btn.style.display = '';
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-external-link-alt"></i> Visit & Earn';
                }
            }, 3000);
        } else {
            countdownEl.textContent = '❌ ' + (data.error || 'Verification failed');
            countdownEl.style.color = 'var(--danger)';
            setTimeout(() => {
                countdownEl.style.display = 'none';
                const btn = countdownEl.parentElement.querySelector('.btn');
                if (btn) {
                    btn.style.display = '';
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-external-link-alt"></i> Visit & Earn';
                }
            }, 3000);
        }
    });
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
