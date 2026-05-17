<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAuth();
$user = getCurrentUser();
$pageTitle = 'Achievements';
$userId = $user['id'];
$db = getDB();

$stmt = $db->prepare("SELECT a.*, ua.unlocked_at, ua.claimed_reward FROM achievements a LEFT JOIN user_achievements ua ON ua.achievement_id = a.id AND ua.user_id = ? ORDER BY a.requirement_value ASC");
$stmt->execute([$userId]);
$achievements = $stmt->fetchAll();

$stmt = $db->prepare("SELECT COUNT(*) as total FROM user_achievements WHERE user_id = ?");
$stmt->execute([$userId]);
$totalUnlocked = $stmt->fetchColumn();

$totalAchievements = count($achievements);

// Progress per achievement
$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM earnings WHERE user_id = ? AND status = 'credited'");
$stmt->execute([$userId]);
$totalEarned = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM referrals WHERE referrer_id = ?");
$stmt->execute([$userId]);
$totalReferrals = $stmt->fetchColumn();

$userLevel = $user['level'] ?? 1;
$streakDays = $user['streak_days'] ?? 0;

$achievementProgresses = [];
foreach ($achievements as $a) {
    $progress = 0;
    switch ($a['requirement_type']) {
        case 'earnings': $progress = $totalEarned; break;
        case 'referrals': $progress = $totalReferrals; break;
        case 'level': $progress = $userLevel; break;
        case 'streak': $progress = $streakDays; break;
    }
    $achievementProgresses[$a['id']] = [
        'progress' => $progress,
        'percent' => $a['requirement_value'] > 0 ? min(100, round(($progress / $a['requirement_value']) * 100)) : 0
    ];
}

$totalBalance = getUserBalance($userId);
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<style>
.achievements-stats {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-6);
    margin-bottom: var(--space-6);
    text-align: center;
}
.achievements-stat {
    padding: var(--space-4) var(--space-6);
    background: var(--card-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
}
.achievements-stat-value {
    font-family: var(--font-display);
    font-size: var(--text-3xl);
    font-weight: var(--weight-bold);
    color: var(--text-primary);
}
.achievements-stat-label {
    font-size: var(--text-sm);
    color: var(--text-secondary);
    margin-top: var(--space-1);
}
.achievements-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: var(--space-4);
}
.achievement-badge {
    background: var(--card-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    padding: var(--space-5);
    text-align: center;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}
.achievement-badge.unlocked {
    border-color: var(--success);
    background: rgba(0,184,148,0.05);
}
.achievement-badge.locked {
    opacity: 0.6;
    filter: grayscale(0.5);
}
.achievement-badge:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}
.achievement-badge-icon {
    font-size: var(--text-4xl);
    margin-bottom: var(--space-3);
}
.achievement-badge.unlocked .achievement-badge-icon { color: var(--warning); }
.achievement-badge.locked .achievement-badge-icon { color: var(--text-tertiary); }
.achievement-badge-name {
    font-size: var(--text-sm);
    font-weight: var(--weight-semibold);
    color: var(--text-primary);
    margin-bottom: var(--space-1);
}
.achievement-badge-desc {
    font-size: var(--text-xs);
    color: var(--text-secondary);
    margin-bottom: var(--space-3);
}
.achievement-badge-progress {
    margin-bottom: var(--space-3);
}
.achievement-badge-reward {
    font-size: var(--text-xs);
    color: var(--success);
    font-weight: var(--weight-semibold);
}
.achievement-badge-status {
    display: inline-flex;
    align-items: center;
    gap: var(--space-1);
    padding: var(--space-1) var(--space-3);
    border-radius: var(--radius-full);
    font-size: 10px;
    font-weight: var(--weight-bold);
    margin-top: var(--space-2);
}
.achievement-badge.unlocked .achievement-badge-status {
    background: rgba(0,184,148,0.15);
    color: var(--success);
}
.achievement-badge.locked .achievement-badge-status {
    background: rgba(255,255,255,0.05);
    color: var(--text-tertiary);
}
.achievement-badge .progress {
    height: 6px;
}
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
            <a href="/user/shortlinks.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-link"></i></span><span class="sidebar-nav-text">Shortlinks</span></a>
            <a href="/user/faucet.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-water"></i></span><span class="sidebar-nav-text">Faucet</span></a>
            <a href="/user/videos.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-video"></i></span><span class="sidebar-nav-text">Videos</span></a>
            <a href="/user/surveys.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-clipboard-list"></i></span><span class="sidebar-nav-text">Surveys</span></a>
            <a href="/user/daily.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-calendar-day"></i></span><span class="sidebar-nav-text">Daily</span></a>
            <a href="/user/achievements.php" class="sidebar-nav-item active"><span class="sidebar-nav-icon"><i class="fas fa-trophy"></i></span><span class="sidebar-nav-text">Achievements</span></a>
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
                <h1>Achievements</h1>
                <p>Unlock achievements and earn bonus rewards</p>
            </div>
            <div class="balance-display">
                <span class="balance-display-amount"><?= formatCurrency($totalBalance) ?></span>
            </div>
        </div>

        <div class="achievements-stats">
            <div class="achievements-stat">
                <div class="achievements-stat-value" style="color: var(--success);"><?= $totalUnlocked ?></div>
                <div class="achievements-stat-label">Unlocked</div>
            </div>
            <div class="achievements-stat">
                <div class="achievements-stat-value"><?= $totalAchievements ?></div>
                <div class="achievements-stat-label">Total</div>
            </div>
            <div class="achievements-stat">
                <div class="achievements-stat-value" style="color: var(--primary);"><?= $totalAchievements > 0 ? round(($totalUnlocked / $totalAchievements) * 100) : 0 ?>%</div>
                <div class="achievements-stat-label">Progress</div>
            </div>
        </div>

        <div class="achievements-grid">
            <?php foreach ($achievements as $a): 
                $isUnlocked = !empty($a['unlocked_at']);
                $progress = $achievementProgresses[$a['id']];
                $claimable = $isUnlocked && empty($a['claimed_reward']) && $a['reward_amount'] > 0;
            ?>
            <div class="achievement-badge <?= $isUnlocked ? 'unlocked' : 'locked' ?>" data-id="<?= $a['id'] ?>">
                <div class="achievement-badge-icon"><i class="fas fa-<?= $isUnlocked ? 'trophy' : 'lock' ?>"></i></div>
                <div class="achievement-badge-name"><?= sanitize($a['name']) ?></div>
                <div class="achievement-badge-desc"><?= sanitize($a['description'] ?? '') ?></div>
                <?php if (!$isUnlocked): ?>
                <div class="achievement-badge-progress">
                    <div class="progress">
                        <div class="progress-bar" style="width: <?= $progress['percent'] ?>%;"></div>
                    </div>
                    <div style="font-size: 10px; color: var(--text-tertiary); margin-top: 2px;"><?= number_format($progress['progress']) ?> / <?= number_format($a['requirement_value']) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($a['reward_amount'] > 0): ?>
                <div class="achievement-badge-reward"><i class="fas fa-coins"></i> +<?= formatCurrency($a['reward_amount']) ?></div>
                <?php endif; ?>
                <?php if ($isUnlocked): ?>
                    <?php if ($claimable): ?>
                    <button class="btn btn-sm btn-success btn-block mt-2" onclick="claimAchievement(<?= $a['id'] ?>)">Claim Reward</button>
                    <?php else: ?>
                    <span class="achievement-badge-status"><i class="fas fa-check"></i> Unlocked</span>
                    <?php endif; ?>
                <?php else: ?>
                <span class="achievement-badge-status">Locked</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="bottom-nav-spacer"></div>
    </main>
</div>

<script>
function claimAchievement(id) {
    const formData = new FormData();
    formData.append('achievement_id', id);
    formData.append('csrf_token', '<?= csrf_token() ?>');

    fetch('/user/ajax/claim_achievement.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const card = document.querySelector('.achievement-badge[data-id="' + id + '"]');
            if (card) {
                const btn = card.querySelector('.btn');
                if (btn) {
                    btn.remove();
                    const status = document.createElement('span');
                    status.className = 'achievement-badge-status';
                    status.innerHTML = '<i class="fas fa-check"></i> Unlocked';
                    card.appendChild(status);
                }
            }
            location.reload();
        } else {
            alert(data.error || 'Failed to claim');
        }
    });
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
