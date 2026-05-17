<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAuth();
$user = getCurrentUser();
$pageTitle = 'Missions';
$userId = $user['id'];
$db = getDB();

$stmt = $db->prepare("SELECT * FROM missions WHERE status = 'active' AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW()) ORDER BY FIELD(duration_type, 'daily', 'weekly', 'monthly'), id ASC");
$stmt->execute();
$allMissions = $stmt->fetchAll();

$dailyMissions = [];
$weeklyMissions = [];
$monthlyMissions = [];

foreach ($allMissions as $m) {
    $stmt = $db->prepare("SELECT * FROM user_missions WHERE user_id = ? AND mission_id = ?");
    $stmt->execute([$userId, $m['id']]);
    $userMission = $stmt->fetch();

    $m['user_progress'] = $userMission ? $userMission['progress'] : 0;
    $m['completed'] = $userMission ? (bool)$userMission['completed'] : false;
    $m['reward_claimed'] = $userMission ? (bool)$userMission['reward_claimed'] : false;

    switch ($m['duration_type']) {
        case 'daily': $dailyMissions[] = $m; break;
        case 'weekly': $weeklyMissions[] = $m; break;
        case 'monthly': $monthlyMissions[] = $m; break;
    }
}

// Fallback missions if DB is empty
if (!count($allMissions)) {
    $fallback = [
        ['id' => 0, 'title' => 'Complete 5 Shortlinks', 'description' => 'Visit and earn from 5 shortlinks', 'requirements' => json_encode(['type' => 'shortlinks', 'target' => 5]), 'rewards' => json_encode(['amount' => 0.05, 'xp' => 10]), 'duration_type' => 'daily'],
        ['id' => 0, 'title' => 'Spin the Wheel 3 Times', 'description' => 'Try your luck on the wheel', 'requirements' => json_encode(['type' => 'spins', 'target' => 3]), 'rewards' => json_encode(['amount' => 0.10, 'xp' => 20]), 'duration_type' => 'daily'],
        ['id' => 0, 'title' => 'Earn $1.00', 'description' => 'Earn a total of $1.00 from any source', 'requirements' => json_encode(['type' => 'earnings', 'target' => 1.00]), 'rewards' => json_encode(['amount' => 0.25, 'xp' => 50]), 'duration_type' => 'daily'],
        ['id' => 0, 'title' => 'Complete 25 Offers', 'description' => 'Complete 25 offerwall offers', 'requirements' => json_encode(['type' => 'offers', 'target' => 25]), 'rewards' => json_encode(['amount' => 0.50, 'xp' => 100]), 'duration_type' => 'weekly'],
        ['id' => 0, 'title' => 'Refer 3 Friends', 'description' => 'Get 3 friends to join via your referral link', 'requirements' => json_encode(['type' => 'referrals', 'target' => 3]), 'rewards' => json_encode(['amount' => 1.00, 'xp' => 200]), 'duration_type' => 'weekly'],
        ['id' => 0, 'title' => 'Earn $25.00', 'description' => 'Earn a total of $25.00 this month', 'requirements' => json_encode(['type' => 'earnings', 'target' => 25.00]), 'rewards' => json_encode(['amount' => 5.00, 'xp' => 500]), 'duration_type' => 'monthly'],
    ];
    foreach ($fallback as $f) {
        $f['user_progress'] = 0;
        $f['completed'] = false;
        $f['reward_claimed'] = false;
        switch ($f['duration_type']) {
            case 'daily': $dailyMissions[] = $f; break;
            case 'weekly': $weeklyMissions[] = $f; break;
            case 'monthly': $monthlyMissions[] = $f; break;
        }
    }
}

$totalBalance = getUserBalance($userId);
$nextMidnight = strtotime('tomorrow midnight');
$dailyCountdown = $nextMidnight - time();
$weekEnd = strtotime('next monday midnight');
$weeklyCountdown = $weekEnd - time();
$monthEnd = strtotime('first day of next month midnight');
$monthlyCountdown = $monthEnd - time();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<style>
.missions-tabs {
    display: flex;
    gap: var(--space-2);
    margin-bottom: var(--space-6);
    border-bottom: 1px solid var(--border-primary);
    padding-bottom: var(--space-2);
}
.missions-tab {
    padding: var(--space-2) var(--space-5);
    border-radius: var(--radius-md);
    font-size: var(--text-sm);
    font-weight: var(--weight-medium);
    color: var(--text-secondary);
    cursor: pointer;
    transition: var(--transition);
    background: none;
    border: none;
}
.missions-tab:hover { color: var(--text-primary); background: var(--bg-card-hover); }
.missions-tab.active { color: var(--white); background: var(--gradient-primary); }
.missions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: var(--space-4);
}
.mission-card {
    background: var(--card-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    padding: var(--space-5);
    transition: var(--transition);
}
.mission-card:hover { border-color: var(--border-light); transform: translateY(-2px); box-shadow: var(--shadow-md); }
.mission-card.completed {
    border-color: var(--success);
    background: rgba(0,184,148,0.05);
}
.mission-card-header {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    margin-bottom: var(--space-3);
}
.mission-card-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--text-lg);
    background: rgba(108,92,231,0.15);
    color: var(--primary);
    flex-shrink: 0;
}
.mission-card-title {
    flex: 1;
    font-size: var(--text-sm);
    font-weight: var(--weight-semibold);
    color: var(--text-primary);
}
.mission-card-desc {
    font-size: var(--text-xs);
    color: var(--text-secondary);
    margin-bottom: var(--space-3);
}
.mission-card-progress {
    margin-bottom: var(--space-3);
}
.mission-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: var(--space-3);
    border-top: 1px solid var(--border-primary);
}
.mission-card-reward {
    font-size: var(--text-sm);
    font-weight: var(--weight-semibold);
    color: var(--success);
}
.mission-card-xp {
    font-size: var(--text-xs);
    color: var(--text-tertiary);
}
.mission-card .progress { height: 8px; }
.timer-badge {
    font-size: var(--text-xs);
    color: var(--text-tertiary);
    font-family: var(--font-mono);
}
@media (max-width: 768px) {
    .missions-tabs { overflow-x: auto; }
    .missions-grid { grid-template-columns: 1fr; }
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
            <a href="/user/achievements.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-trophy"></i></span><span class="sidebar-nav-text">Achievements</span></a>
            <a href="/user/missions.php" class="sidebar-nav-item active"><span class="sidebar-nav-icon"><i class="fas fa-rocket"></i></span><span class="sidebar-nav-text">Missions</span></a>
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
                <h1>Missions</h1>
                <p>Complete missions for extra rewards and XP</p>
            </div>
            <div class="balance-display">
                <span class="balance-display-amount" id="balanceDisplay"><?= formatCurrency($totalBalance) ?></span>
            </div>
        </div>

        <div class="missions-tabs">
            <button class="missions-tab active" onclick="switchMissionTab('daily', this)">Daily <span class="timer-badge" id="dailyTimer"><?= gmdate('H:i:s', $dailyCountdown) ?></span></button>
            <button class="missions-tab" onclick="switchMissionTab('weekly', this)">Weekly <span class="timer-badge" id="weeklyTimer"><?= gmdate('H:i:s', $weeklyCountdown) ?></span></button>
            <button class="missions-tab" onclick="switchMissionTab('monthly', this)">Monthly <span class="timer-badge" id="monthlyTimer"><?= gmdate('H:i:s', $monthlyCountdown) ?></span></button>
        </div>

        <div class="missions-grid" id="dailyMissions">
            <?php foreach ($dailyMissions as $m): 
                $reqs = json_decode($m['requirements'], true);
                $target = $reqs['target'] ?? 1;
                $pct = $target > 0 ? min(100, round(($m['user_progress'] / $target) * 100)) : 0;
                $rewards = json_decode($m['rewards'], true);
                $rewardAmt = $rewards['amount'] ?? 0;
                $rewardXp = $rewards['xp'] ?? 0;
            ?>
            <div class="mission-card <?= $m['completed'] ? 'completed' : '' ?>" data-type="daily">
                <div class="mission-card-header">
                    <div class="mission-card-icon"><i class="fas fa-<?= $m['completed'] ? 'check-circle' : 'rocket' ?>"></i></div>
                    <div class="mission-card-title"><?= sanitize($m['title']) ?></div>
                </div>
                <div class="mission-card-desc"><?= sanitize($m['description'] ?? '') ?></div>
                <div class="mission-card-progress">
                    <div class="progress">
                        <div class="progress-bar <?= $m['completed'] ? 'progress-bar-success' : '' ?>" style="width: <?= $pct ?>%;"></div>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: var(--text-xs); color: var(--text-tertiary); margin-top: 2px;">
                        <span><?= $m['user_progress'] ?> / <?= $target ?></span>
                        <span><?= $pct ?>%</span>
                    </div>
                </div>
                <div class="mission-card-footer">
                    <div>
                        <span class="mission-card-reward"><?= $rewardAmt > 0 ? '+' . formatCurrency($rewardAmt) : '' ?></span>
                        <?php if ($rewardXp > 0): ?><span class="mission-card-xp"> +<?= $rewardXp ?> XP</span><?php endif; ?>
                    </div>
                    <?php if ($m['completed'] && !$m['reward_claimed']): ?>
                    <button class="btn btn-sm btn-success" onclick="claimMission(<?= $m['id'] ?>, this)">Claim</button>
                    <?php elseif ($m['reward_claimed']): ?>
                    <span class="badge badge-success badge-sm"><i class="fas fa-check"></i> Claimed</span>
                    <?php else: ?>
                    <span class="badge badge-neutral badge-sm">In Progress</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="missions-grid" id="weeklyMissions" style="display: none;">
            <?php foreach ($weeklyMissions as $m): 
                $reqs = json_decode($m['requirements'], true);
                $target = $reqs['target'] ?? 1;
                $pct = $target > 0 ? min(100, round(($m['user_progress'] / $target) * 100)) : 0;
                $rewards = json_decode($m['rewards'], true);
                $rewardAmt = $rewards['amount'] ?? 0;
                $rewardXp = $rewards['xp'] ?? 0;
            ?>
            <div class="mission-card <?= $m['completed'] ? 'completed' : '' ?>" data-type="weekly">
                <div class="mission-card-header">
                    <div class="mission-card-icon"><i class="fas fa-<?= $m['completed'] ? 'check-circle' : 'rocket' ?>"></i></div>
                    <div class="mission-card-title"><?= sanitize($m['title']) ?></div>
                </div>
                <div class="mission-card-desc"><?= sanitize($m['description'] ?? '') ?></div>
                <div class="mission-card-progress">
                    <div class="progress">
                        <div class="progress-bar <?= $m['completed'] ? 'progress-bar-success' : '' ?>" style="width: <?= $pct ?>%;"></div>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: var(--text-xs); color: var(--text-tertiary); margin-top: 2px;">
                        <span><?= $m['user_progress'] ?> / <?= $target ?></span>
                        <span><?= $pct ?>%</span>
                    </div>
                </div>
                <div class="mission-card-footer">
                    <div>
                        <span class="mission-card-reward"><?= $rewardAmt > 0 ? '+' . formatCurrency($rewardAmt) : '' ?></span>
                        <?php if ($rewardXp > 0): ?><span class="mission-card-xp"> +<?= $rewardXp ?> XP</span><?php endif; ?>
                    </div>
                    <?php if ($m['completed'] && !$m['reward_claimed']): ?>
                    <button class="btn btn-sm btn-success" onclick="claimMission(<?= $m['id'] ?>, this)">Claim</button>
                    <?php elseif ($m['reward_claimed']): ?>
                    <span class="badge badge-success badge-sm"><i class="fas fa-check"></i> Claimed</span>
                    <?php else: ?>
                    <span class="badge badge-neutral badge-sm">In Progress</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="missions-grid" id="monthlyMissions" style="display: none;">
            <?php foreach ($monthlyMissions as $m): 
                $reqs = json_decode($m['requirements'], true);
                $target = $reqs['target'] ?? 1;
                $pct = $target > 0 ? min(100, round(($m['user_progress'] / $target) * 100)) : 0;
                $rewards = json_decode($m['rewards'], true);
                $rewardAmt = $rewards['amount'] ?? 0;
                $rewardXp = $rewards['xp'] ?? 0;
            ?>
            <div class="mission-card <?= $m['completed'] ? 'completed' : '' ?>" data-type="monthly">
                <div class="mission-card-header">
                    <div class="mission-card-icon"><i class="fas fa-<?= $m['completed'] ? 'check-circle' : 'rocket' ?>"></i></div>
                    <div class="mission-card-title"><?= sanitize($m['title']) ?></div>
                </div>
                <div class="mission-card-desc"><?= sanitize($m['description'] ?? '') ?></div>
                <div class="mission-card-progress">
                    <div class="progress">
                        <div class="progress-bar <?= $m['completed'] ? 'progress-bar-success' : '' ?>" style="width: <?= $pct ?>%;"></div>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: var(--text-xs); color: var(--text-tertiary); margin-top: 2px;">
                        <span><?= $m['user_progress'] ?> / <?= $target ?></span>
                        <span><?= $pct ?>%</span>
                    </div>
                </div>
                <div class="mission-card-footer">
                    <div>
                        <span class="mission-card-reward"><?= $rewardAmt > 0 ? '+' . formatCurrency($rewardAmt) : '' ?></span>
                        <?php if ($rewardXp > 0): ?><span class="mission-card-xp"> +<?= $rewardXp ?> XP</span><?php endif; ?>
                    </div>
                    <?php if ($m['completed'] && !$m['reward_claimed']): ?>
                    <button class="btn btn-sm btn-success" onclick="claimMission(<?= $m['id'] ?>, this)">Claim</button>
                    <?php elseif ($m['reward_claimed']): ?>
                    <span class="badge badge-success badge-sm"><i class="fas fa-check"></i> Claimed</span>
                    <?php else: ?>
                    <span class="badge badge-neutral badge-sm">In Progress</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="bottom-nav-spacer"></div>
    </main>
</div>

<script>
function switchMissionTab(tab, btn) {
    document.querySelectorAll('.missions-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.missions-grid').forEach(g => g.style.display = 'none');
    document.getElementById(tab + 'Missions').style.display = 'grid';
}

function claimMission(missionId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    const formData = new FormData();
    formData.append('mission_id', missionId);
    formData.append('csrf_token', '<?= csrf_token() ?>');

    fetch('/user/ajax/claim_mission.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const card = btn.closest('.mission-card');
            if (card) card.classList.add('completed');
            btn.className = 'badge badge-success badge-sm';
            btn.innerHTML = '<i class="fas fa-check"></i> Claimed';
            if (data.new_balance_formatted) {
                document.getElementById('balanceDisplay').textContent = data.new_balance_formatted;
            }
        } else {
            btn.disabled = false;
            btn.innerHTML = 'Claim';
            alert(data.error || 'Failed to claim mission reward');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = 'Claim';
    });
}

// Timers
function startTimer(id, seconds) {
    const el = document.getElementById(id);
    if (!el) return;
    setInterval(() => {
        seconds--;
        if (seconds <= 0) { el.textContent = 'Expired'; return; }
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        el.textContent = String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    }, 1000);
}
startTimer('dailyTimer', <?= $dailyCountdown ?>);
startTimer('weeklyTimer', <?= $weeklyCountdown ?>);
startTimer('monthlyTimer', <?= $monthlyCountdown ?>);
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
