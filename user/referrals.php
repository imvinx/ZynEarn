<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAuth();
$user = getCurrentUser();
$pageTitle = 'Referrals';
$userId = $user['id'];
$db = getDB();

$referralCode = $user['referral_code'] ?? '';
if (empty($referralCode)) {
    $referralCode = createReferralCode();
    $stmt = $db->prepare("UPDATE users SET referral_code = ? WHERE id = ?");
    $stmt->execute([$referralCode, $userId]);
}
$referralLink = APP_URL . '/auth/register.php?ref=' . $referralCode;

$stmt = $db->prepare("SELECT COUNT(*) as total FROM referrals WHERE referrer_id = ?");
$stmt->execute([$userId]);
$totalReferrals = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as active FROM referrals WHERE referrer_id = ? AND status = 'active'");
$stmt->execute([$userId]);
$activeReferrals = $stmt->fetch()['active'];

$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as referral_earnings FROM earnings WHERE user_id = ? AND type = 'referral' AND status = 'credited'");
$stmt->execute([$userId]);
$referralEarnings = $stmt->fetch()['referral_earnings'];

$commissionRate = REFERRAL_COMMISSION_LEVELS[1] ?? 10;

$stmt = $db->prepare("
    SELECT r.*, u.username, u.avatar, u.created_at as joined_date, u.level,
    (SELECT COALESCE(SUM(amount), 0) FROM earnings WHERE user_id = r.referred_id AND status = 'credited') as total_earned,
    (SELECT COALESCE(SUM(amount), 0) FROM earnings WHERE user_id = ? AND reference_id = r.id AND type = 'referral') as commission_earned
    FROM referrals r JOIN users u ON r.referred_id = u.id WHERE r.referrer_id = ? ORDER BY r.created_at DESC
");
$stmt->execute([$userId, $userId]);
$referrals = $stmt->fetchAll();

$stmt = $db->prepare("
    SELECT u.username, u.avatar, COUNT(r.id) as referral_count
    FROM referrals r JOIN users u ON r.referrer_id = u.id
    GROUP BY r.referrer_id ORDER BY referral_count DESC LIMIT 10
");
$stmt->execute();
$leaderboard = $stmt->fetchAll();

$milestones = [
    ['count' => 1, 'reward' => 0.50, 'label' => 'First Referral', 'icon' => 'fa-user-plus'],
    ['count' => 5, 'reward' => 2.00, 'label' => '5 Referrals', 'icon' => 'fa-users'],
    ['count' => 10, 'reward' => 5.00, 'label' => '10 Referrals', 'icon' => 'fa-users'],
    ['count' => 25, 'reward' => 12.50, 'label' => '25 Referrals', 'icon' => 'fa-star'],
    ['count' => 50, 'reward' => 25.00, 'label' => '50 Referrals', 'icon' => 'fa-trophy'],
    ['count' => 100, 'reward' => 50.00, 'label' => '100 Referrals', 'icon' => 'fa-crown'],
];

$stmt = $db->prepare("SELECT COALESCE(SUM(e.amount), 0) as level_earnings FROM earnings e JOIN referrals r ON e.reference_id = r.id WHERE e.user_id = ? AND e.type = 'referral' AND e.status = 'credited'");
$stmt->execute([$userId]);
$totalCommission = $stmt->fetch()['level_earnings'];

$levelEarnings = [];
foreach (REFERRAL_COMMISSION_LEVELS as $level => $rate) {
    $stmt = $db->prepare("SELECT COALESCE(SUM(e.amount), 0) FROM earnings e JOIN referrals r ON e.reference_id = r.id WHERE e.user_id = ? AND e.type = 'referral' AND e.status = 'credited' AND r.level = ?");
    $stmt->execute([$userId, $level]);
    $levelEarnings[$level] = $stmt->fetchColumn();
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<style>
.referral-header { margin-bottom: var(--space-6); }
.referral-header h1 { font-family: var(--font-display); font-size: var(--text-2xl); font-weight: var(--weight-bold); color: var(--text-primary); }
.referral-header p { font-size: var(--text-sm); color: var(--text-secondary); margin-top: 2px; }
.referral-link-card { background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-lg); padding: var(--space-6); position: relative; overflow: hidden; }
.referral-link-card::before { content: ''; position: absolute; top: -50%; right: -30%; width: 200px; height: 200px; background: radial-gradient(circle, rgba(108, 92, 231, 0.1) 0%, transparent 70%); pointer-events: none; }
.referral-link-title { font-family: var(--font-display); font-size: var(--text-lg); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-1); }
.referral-link-sub { font-size: var(--text-sm); color: var(--text-secondary); margin-bottom: var(--space-4); }
.referral-link-box { display: flex; align-items: center; gap: var(--space-2); padding: var(--space-3) var(--space-4); background: var(--bg-card-hover); border: 2px dashed var(--border-light); border-radius: var(--radius-md); margin-bottom: var(--space-4); }
.referral-link-box input { flex: 1; background: none; border: none; color: var(--primary); font-family: var(--font-mono); font-size: var(--text-sm); font-weight: var(--weight-semibold); outline: none; }
.copy-btn { padding: var(--space-1) var(--space-3); background: var(--gradient-primary); color: var(--white); border: none; border-radius: var(--radius-sm); font-size: var(--text-xs); font-weight: var(--weight-semibold); cursor: pointer; transition: var(--transition); }
.copy-btn:hover { box-shadow: var(--shadow-glow); }
.share-buttons { display: flex; gap: var(--space-2); flex-wrap: wrap; }
.share-btn { display: inline-flex; align-items: center; gap: var(--space-2); padding: var(--space-2) var(--space-4); border: none; border-radius: var(--radius-md); font-size: var(--text-sm); font-weight: var(--weight-semibold); cursor: pointer; transition: var(--transition); color: var(--white); text-decoration: none; }
.share-btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
.share-btn.whatsapp { background: #25D366; }
.share-btn.telegram { background: #0088CC; }
.share-btn.twitter { background: #1DA1F2; }
.share-btn.facebook { background: #1877F2; }
.share-btn.email { background: var(--text-secondary); }
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: var(--space-4); margin: var(--space-6) 0; }
.stat-card { background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-lg); padding: var(--space-5); transition: var(--transition); }
.stat-card:hover { border-color: var(--border-light); transform: translateY(-2px); box-shadow: var(--shadow-md); }
.stat-icon { width: 40px; height: 40px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: var(--text-lg); margin-bottom: var(--space-3); }
.stat-value { font-family: var(--font-display); font-size: var(--text-2xl); font-weight: var(--weight-bold); color: var(--text-primary); }
.stat-label { font-size: var(--text-xs); color: var(--text-tertiary); margin-top: var(--space-1); }
.tree-container { background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-lg); padding: var(--space-6); overflow-x: auto; }
.tree { display: flex; flex-direction: column; align-items: center; }
.tree-level { display: flex; justify-content: center; gap: var(--space-3); position: relative; padding: var(--space-2); flex-wrap: wrap; }
.tree-level-label { width: 100%; text-align: center; font-size: 10px; color: var(--text-tertiary); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: var(--space-2); }
.tree-node { width: 48px; height: 48px; border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; font-size: var(--text-xs); font-weight: var(--weight-bold); position: relative; transition: var(--transition); cursor: default; border: 2px solid transparent; }
.tree-node:hover { transform: scale(1.15); z-index: 2; }
.tree-node.level-1 { background: var(--gradient-primary); color: white; border-color: var(--primary-light); width: 52px; height: 52px; }
.tree-node.level-2 { background: rgba(108,92,231,0.2); color: var(--primary-light); border-color: rgba(108,92,231,0.3); }
.tree-node.level-3 { background: rgba(0,206,201,0.15); color: var(--secondary-light); border-color: rgba(0,206,201,0.25); }
.tree-node.level-4 { background: rgba(253,121,168,0.12); color: var(--accent); border-color: rgba(253,121,168,0.2); }
.tree-node.level-5 { background: rgba(116,185,255,0.1); color: var(--info); border-color: rgba(116,185,255,0.15); }
.tree-connector { width: 2px; height: 16px; background: var(--border-light); margin: 0 auto; }
.tree-row { display: flex; flex-direction: column; align-items: center; }
.commission-breakdown { background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-lg); padding: var(--space-6); }
.commission-level { display: flex; align-items: center; justify-content: space-between; padding: var(--space-3) 0; border-bottom: 1px solid var(--border-primary); }
.commission-level:last-child { border-bottom: none; }
.commission-level-info { display: flex; align-items: center; gap: var(--space-3); }
.commission-level-badge { width: 32px; height: 32px; border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; font-size: var(--text-xs); font-weight: var(--weight-bold); background: rgba(108,92,231,0.15); color: var(--primary); }
.commission-level-rate { font-size: var(--text-xs); color: var(--text-tertiary); }
.commission-level-earnings { font-family: var(--font-display); font-size: var(--text-lg); font-weight: var(--weight-bold); color: var(--success); }
.milestone-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-4); }
.milestone-card { background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-lg); padding: var(--space-5); text-align: center; transition: var(--transition); }
.milestone-card:hover { border-color: var(--border-light); transform: translateY(-2px); }
.milestone-card.unlocked { border-color: var(--success); background: rgba(0,184,148,0.05); }
.milestone-icon { font-size: var(--text-2xl); margin-bottom: var(--space-2); color: var(--text-tertiary); }
.milestone-card.unlocked .milestone-icon { color: var(--success); }
.milestone-count { font-family: var(--font-display); font-size: var(--text-lg); font-weight: var(--weight-bold); color: var(--text-primary); }
.milestone-label { font-size: var(--text-xs); color: var(--text-secondary); margin-top: var(--space-1); }
.milestone-reward { font-size: var(--text-sm); color: var(--success); font-weight: var(--weight-semibold); margin-top: var(--space-2); display: block; }
.leaderboard-card { display: flex; align-items: center; gap: var(--space-3); padding: var(--space-3); border-radius: var(--radius-md); transition: var(--transition); }
.leaderboard-card:hover { background: var(--bg-card-hover); }
.leaderboard-rank { width: 28px; height: 28px; border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; font-size: var(--text-xs); font-weight: var(--weight-bold); flex-shrink: 0; }
.leaderboard-rank.gold { background: rgba(253,203,110,0.2); color: var(--warning); }
.leaderboard-rank.silver { background: rgba(200,200,220,0.15); color: #c0c0d0; }
.leaderboard-rank.bronze { background: rgba(225,112,85,0.15); color: var(--danger); }
.leaderboard-rank.default { background: rgba(108,92,231,0.1); color: var(--text-tertiary); }
.leaderboard-avatar { width: 36px; height: 36px; border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; font-size: var(--text-sm); font-weight: var(--weight-bold); flex-shrink: 0; }
.leaderboard-info { flex: 1; }
.leaderboard-name { font-size: var(--text-sm); font-weight: var(--weight-medium); color: var(--text-primary); }
.leaderboard-count { font-size: var(--text-xs); color: var(--text-tertiary); }
.invite-form { background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-lg); padding: var(--space-6); }
@media (max-width: 992px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .milestone-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 480px) {
    .stats-grid { grid-template-columns: 1fr; }
    .milestone-grid { grid-template-columns: 1fr; }
    .share-buttons { flex-direction: column; }
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
            <a href="/user/referrals.php" class="sidebar-nav-item active"><span class="sidebar-nav-icon"><i class="fas fa-users"></i></span><span class="sidebar-nav-text">Referrals</span></a>
            <a href="/user/withdraw.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-cash-register"></i></span><span class="sidebar-nav-text">Withdraw</span></a>
            <a href="/user/deposit.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-plus-circle"></i></span><span class="sidebar-nav-text">Deposit</span></a>
        </div>
        <div class="sidebar-section">
            <div class="sidebar-section-title">Account</div>
            <a href="/user/profile.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-user"></i></span><span class="sidebar-nav-text">Profile</span></a>
            <a href="/user/settings.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-cog"></i></span><span class="sidebar-nav-text">Settings</span></a>
            <a href="/user/notifications.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-bell"></i></span><span class="sidebar-nav-text">Notifications</span></a>
            <a href="/user/leaderboard.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-trophy"></i></span><span class="sidebar-nav-text">Leaderboard</span></a>
            <a href="/user/support.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-headset"></i></span><span class="sidebar-nav-text">Support</span></a>
            <a href="/auth/logout.php" class="sidebar-nav-item logout-item"><span class="sidebar-nav-icon"><i class="fas fa-sign-out-alt"></i></span><span class="sidebar-nav-text">Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <div class="dashboard-top-header">
            <div class="dashboard-greeting">
                <h1>Referral Program</h1>
                <p>Invite friends and earn commissions from their earnings</p>
            </div>
            <div class="dashboard-actions">
                <div class="balance-display" onclick="toggleBalanceVisibility()">
                    <span class="balance-display-icon"><i class="fas fa-eye" id="eyeIcon"></i></span>
                    <span class="balance-display-amount" id="headerBalance"><?= formatCurrency(getUserBalance($userId)) ?></span>
                </div>
            </div>
        </div>

        <div class="referral-link-card">
            <div class="referral-link-title"><i class="fas fa-link" style="color: var(--primary);"></i> Your Referral Link</div>
            <div class="referral-link-sub">Share this link with friends and earn <strong><?= $commissionRate ?>%</strong> commission on their earnings!</div>
            <div class="referral-link-box">
                <input type="text" id="referralLink" value="<?= $referralLink ?>" readonly>
                <button class="copy-btn" onclick="copyReferralLink()"><i class="fas fa-copy"></i> <span id="copyText">Copy</span></button>
            </div>
            <div class="share-buttons">
                <a class="share-btn whatsapp" href="https://wa.me/?text=Join%20me%20on%20<?= urlencode(APP_NAME) ?>%20and%20start%20earning!%20<?= urlencode($referralLink) ?>" target="_blank" onclick="trackShare('whatsapp')"><i class="fab fa-whatsapp"></i> WhatsApp</a>
                <a class="share-btn telegram" href="https://t.me/share/url?url=<?= urlencode($referralLink) ?>&text=Join%20me%20on%20<?= urlencode(APP_NAME) ?>%20and%20start%20earning!" target="_blank" onclick="trackShare('telegram')"><i class="fab fa-telegram-plane"></i> Telegram</a>
                <a class="share-btn twitter" href="https://twitter.com/intent/tweet?text=Join%20me%20on%20<?= urlencode(APP_NAME) ?>%20and%20start%20earning!&url=<?= urlencode($referralLink) ?>" target="_blank" onclick="trackShare('twitter')"><i class="fab fa-twitter"></i> Twitter</a>
                <a class="share-btn facebook" href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($referralLink) ?>&quote=Join%20me%20on%20<?= urlencode(APP_NAME) ?>%20and%20start%20earning!" target="_blank" onclick="trackShare('facebook')"><i class="fab fa-facebook-f"></i> Facebook</a>
                <a class="share-btn email" href="mailto:?subject=Join%20me%20on%20<?= urlencode(APP_NAME) ?>&body=Join%20me%20on%20<?= urlencode(APP_NAME) ?>%20and%20start%20earning!%20<?= urlencode($referralLink) ?>" target="_blank" onclick="trackShare('email')"><i class="fas fa-envelope"></i> Email</a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(108,92,231,0.15); color: var(--primary);"><i class="fas fa-users"></i></div>
                <div class="stat-value" id="totalReferrals"><?= $totalReferrals ?></div>
                <div class="stat-label">Total Referrals</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(0,206,201,0.15); color: var(--secondary);"><i class="fas fa-user-check"></i></div>
                <div class="stat-value"><?= $activeReferrals ?></div>
                <div class="stat-label">Active Referrals</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(0,184,148,0.15); color: var(--success);"><i class="fas fa-coins"></i></div>
                <div class="stat-value" id="referralEarnings"><?= formatCurrency($referralEarnings) ?></div>
                <div class="stat-label">Referral Earnings</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(253,203,110,0.15); color: var(--warning);"><i class="fas fa-percentage"></i></div>
                <div class="stat-value"><?= $commissionRate ?>%</div>
                <div class="stat-label">Commission Rate</div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-6); margin-bottom: var(--space-6);">
            <div class="commission-breakdown">
                <h3 style="font-family: var(--font-display); font-size: var(--text-lg); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-4);">Commission Breakdown</h3>
                <?php foreach (REFERRAL_COMMISSION_LEVELS as $level => $rate): ?>
                <div class="commission-level">
                    <div class="commission-level-info">
                        <div class="commission-level-badge"><?= $level ?></div>
                        <div>
                            <div style="font-size: var(--text-sm); font-weight: var(--weight-medium); color: var(--text-primary);">Level <?= $level ?></div>
                            <div class="commission-level-rate"><?= $rate ?>% commission</div>
                        </div>
                    </div>
                    <div class="commission-level-earnings"><?= formatCurrency($levelEarnings[$level] ?? 0) ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <div>
                <div class="invite-form">
                    <h3 style="font-family: var(--font-display); font-size: var(--text-lg); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-4);"><i class="fas fa-envelope" style="color: var(--primary);"></i> Invite by Email</h3>
                    <form id="inviteForm" onsubmit="sendInvite(event)">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <div class="form-group">
                            <label class="form-label">Friend's Email</label>
                            <input type="email" class="form-input" name="email" id="inviteEmail" required placeholder="Enter their email address">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Personal Message (optional)</label>
                            <textarea class="form-input" name="message" id="inviteMessage" rows="3" placeholder="Hey! Join ZynEarn and start earning money online..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block" id="inviteBtn">
                            <i class="fas fa-paper-plane"></i> Send Invitation
                        </button>
                    </form>
                    <div id="inviteResult" style="margin-top: var(--space-3); display: none;"></div>
                </div>

                <div class="dashboard-panel" style="margin-top: var(--space-6);">
                    <div class="dashboard-panel-header">
                        <div class="dashboard-panel-title"><i class="fas fa-trophy" style="color: var(--warning);"></i> Top Referrers</div>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: var(--space-1);">
                        <?php $rank = 1; foreach ($leaderboard as $ref): ?>
                        <div class="leaderboard-card">
                            <div class="leaderboard-rank <?= $rank === 1 ? 'gold' : ($rank === 2 ? 'silver' : ($rank === 3 ? 'bronze' : 'default')) ?>"><?= $rank ?></div>
                            <div class="leaderboard-avatar" style="background: var(--gradient-primary); color: white;"><?= strtoupper(substr($ref['username'], 0, 1)) ?></div>
                            <div class="leaderboard-info">
                                <div class="leaderboard-name"><?= sanitize($ref['username']) ?></div>
                                <div class="leaderboard-count"><?= $ref['referral_count'] ?> referrals</div>
                            </div>
                        </div>
                        <?php $rank++; endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="tree-container" style="margin-bottom: var(--space-6);">
            <h3 style="font-family: var(--font-display); font-size: var(--text-lg); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-4);"><i class="fas fa-sitemap" style="color: var(--primary);"></i> Referral Tree</h3>
            <div class="tree" id="referralTree">
                <div class="tree-row">
                    <div class="tree-node level-1" title="You (Level 1)"><?= strtoupper(substr($user['username'], 0, 2)) ?></div>
                </div>
                <div class="tree-connector"></div>
                <div class="tree-level">
                    <div class="tree-level-label">Level 1 (<?= $totalReferrals ?>)</div>
                    <?php for ($i = 0; $i < min($totalReferrals, 5); $i++): ?>
                    <div class="tree-node level-2" title="<?= sanitize($referrals[$i]['username'] ?? 'Referral') ?>"><?= strtoupper(substr($referrals[$i]['username'] ?? '?', 0, 2)) ?></div>
                    <?php endfor; ?>
                    <?php if ($totalReferrals > 5): ?>
                    <div class="tree-node level-2" style="font-size: 10px;">+<?= $totalReferrals - 5 ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div style="margin-bottom: var(--space-6);">
            <h3 style="font-family: var(--font-display); font-size: var(--text-lg); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-4);"><i class="fas fa-flag-checkered" style="color: var(--warning);"></i> Referral Milestones</h3>
            <div class="milestone-grid">
                <?php foreach ($milestones as $ms): ?>
                <div class="milestone-card <?= $totalReferrals >= $ms['count'] ? 'unlocked' : '' ?>">
                    <div class="milestone-icon"><i class="fas <?= $ms['icon'] ?>"></i></div>
                    <div class="milestone-count"><?= $ms['count'] ?></div>
                    <div class="milestone-label"><?= $ms['label'] ?></div>
                    <span class="milestone-reward">+<?= formatCurrency($ms['reward']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="dashboard-panel">
            <div class="dashboard-panel-header">
                <div class="dashboard-panel-title"><i class="fas fa-list"></i> Referral List</div>
            </div>
            <div class="table-container">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Joined</th>
                            <th>Status</th>
                            <th>Total Earned</th>
                            <th>Commission</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($referrals) > 0): ?>
                        <?php foreach ($referrals as $ref): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar avatar-xs" style="background: var(--gradient-primary); color: white;"><?= strtoupper(substr($ref['username'], 0, 1)) ?></div>
                                    <span style="font-size: var(--text-sm); font-weight: var(--weight-medium); color: var(--text-primary);"><?= sanitize($ref['username']) ?></span>
                                </div>
                            </td>
                            <td><span class="text-mono" style="font-size: var(--text-xs); color: var(--text-tertiary);"><?= date('M d, Y', strtotime($ref['joined_date'])) ?></span></td>
                            <td><span class="badge badge-<?= $ref['status'] === 'active' ? 'success' : 'neutral' ?> badge-sm"><?= ucfirst($ref['status']) ?></span></td>
                            <td><span style="font-size: var(--text-sm); font-weight: var(--weight-semibold); color: var(--success);"><?= formatCurrency($ref['total_earned']) ?></span></td>
                            <td><span style="font-size: var(--text-sm); font-weight: var(--weight-semibold); color: var(--primary);"><?= formatCurrency($ref['commission_earned']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr><td colspan="5"><div class="table-empty"><div class="table-empty-icon"><i class="fas fa-users"></i></div><p>No referrals yet. Share your link to start earning!</p></div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
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
        el.textContent = '••••••';
        icon.className = 'fas fa-eye-slash';
    }
}

function copyReferralLink() {
    const input = document.getElementById('referralLink');
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).then(() => {
        document.getElementById('copyText').textContent = 'Copied!';
        setTimeout(() => document.getElementById('copyText').textContent = 'Copy', 2000);
    }).catch(() => {
        document.execCommand('copy');
        document.getElementById('copyText').textContent = 'Copied!';
        setTimeout(() => document.getElementById('copyText').textContent = 'Copy', 2000);
    });
    fetch('/user/ajax/track_share.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=copy&csrf_token=<?= csrf_token() ?>'
    });
}

function trackShare(platform) {
    fetch('/user/ajax/track_share.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=share&platform=' + platform + '&csrf_token=<?= csrf_token() ?>'
    });
}

function sendInvite(e) {
    e.preventDefault();
    const btn = document.getElementById('inviteBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    const formData = new FormData(document.getElementById('inviteForm'));
    fetch('/user/ajax/send_invite.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        const result = document.getElementById('inviteResult');
        result.style.display = 'block';
        if (data.success) {
            result.className = 'alert alert-success';
            result.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
            document.getElementById('inviteForm').reset();
        } else {
            result.className = 'alert alert-danger';
            result.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + (data.error || 'Failed to send invitation');
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Invitation';
        setTimeout(() => { result.style.display = 'none'; }, 5000);
    })
    .catch(() => {
        const result = document.getElementById('inviteResult');
        result.style.display = 'block';
        result.className = 'alert alert-danger';
        result.innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error. Please try again.';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Invitation';
    });
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
