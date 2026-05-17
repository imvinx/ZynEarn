<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAuth();
$user = getCurrentUser();
$pageTitle = 'Daily Rewards';
$userId = $user['id'];
$db = getDB();

$streakDays = $user['streak_days'] ?? 0;
$lastClaim = $user['last_daily_claim'] ?? null;

$stmt = $db->prepare("SELECT * FROM daily_rewards ORDER BY day_number ASC");
$stmt->execute();
$allRewards = $stmt->fetchAll();

if (!count($allRewards)) {
    for ($d = 1; $d <= 30; $d++) {
        $amt = DAILY_BONUS_BASE + ($d - 1) * DAILY_BONUS_INCREMENT;
        $stmt = $db->prepare("INSERT IGNORE INTO daily_rewards (day_number, reward_amount) VALUES (?, ?)");
        $stmt->execute([$d, min($amt, MAX_STREAK_BONUS)]);
    }
    $stmt = $db->query("SELECT * FROM daily_rewards ORDER BY day_number ASC");
    $allRewards = $stmt->fetchAll();
}

$stmt = $db->prepare("SELECT day_number FROM daily_logins WHERE user_id = ? AND reward_claimed = 1");
$stmt->execute([$userId]);
$claimedDays = $stmt->fetchAll(PDO::FETCH_COLUMN);

$today = date('Y-m-d');
$canClaimToday = $lastClaim !== $today;

$nextClaimTime = 0;
if ($lastClaim === $today) {
    $nextMidnight = strtotime('tomorrow midnight');
    $nextClaimTime = $nextMidnight - time();
}

$totalBalance = getUserBalance($userId);
$vipMultiplier = getMembershipMultiplier($user['vip_tier'] ?? 'free');
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<style>
.daily-header {
    text-align: center;
    margin-bottom: var(--space-6);
}
.daily-streak {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    padding: var(--space-2) var(--space-5);
    background: rgba(253,203,110,0.15);
    border: 1px solid var(--warning);
    border-radius: var(--radius-full);
    font-size: var(--text-base);
    font-weight: var(--weight-bold);
    color: var(--warning);
    margin-bottom: var(--space-4);
}
.daily-streak i { color: var(--warning); }
.daily-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: var(--space-3);
    margin-bottom: var(--space-6);
}
.daily-day {
    background: var(--card-bg);
    border: 1px solid var(--border-primary);
    border-radius: var(--radius-md);
    padding: var(--space-3);
    text-align: center;
    transition: var(--transition);
    position: relative;
    min-height: 100px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
.daily-day.claimed {
    border-color: var(--success);
    background: rgba(0,184,148,0.05);
}
.daily-day.current {
    border-color: var(--primary);
    background: rgba(108,92,231,0.1);
    box-shadow: 0 0 20px rgba(108,92,231,0.2);
    animation: dailyPulse 2s ease-in-out infinite;
}
.daily-day.locked {
    opacity: 0.5;
}
.daily-day.future {
    opacity: 0.4;
}
@keyframes dailyPulse {
    0%, 100% { box-shadow: 0 0 10px rgba(108,92,231,0.2); }
    50% { box-shadow: 0 0 25px rgba(108,92,231,0.4); }
}
.daily-day-number {
    font-size: var(--text-xs);
    font-weight: var(--weight-bold);
    color: var(--text-tertiary);
    position: absolute;
    top: 6px;
    left: 8px;
}
.daily-day-reward {
    font-family: var(--font-display);
    font-size: var(--text-lg);
    font-weight: var(--weight-bold);
    color: var(--success);
}
.daily-day-label {
    font-size: 10px;
    color: var(--text-tertiary);
    margin-top: 2px;
}
.daily-day-check {
    position: absolute;
    top: 4px;
    right: 6px;
    color: var(--success);
    font-size: var(--text-sm);
}
.daily-day-bonus {
    font-size: 10px;
    color: var(--warning);
    font-weight: var(--weight-semibold);
    background: rgba(253,203,110,0.2);
    padding: 1px 6px;
    border-radius: var(--radius-full);
    margin-top: 2px;
}
.claim-section {
    text-align: center;
    padding: var(--space-6);
    background: var(--card-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    max-width: 400px;
    margin: 0 auto;
}
.claim-btn-large {
    width: 180px;
    height: 180px;
    border-radius: var(--radius-full);
    background: var(--gradient-primary);
    color: var(--white);
    font-family: var(--font-display);
    font-size: var(--text-xl);
    font-weight: var(--weight-bold);
    border: none;
    cursor: pointer;
    transition: var(--transition);
    box-shadow: var(--shadow-glow);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: var(--space-2);
    margin: var(--space-4) auto;
}
.claim-btn-large:hover { transform: scale(1.05); }
.claim-btn-large:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
.claim-btn-large .sub { font-size: var(--text-sm); font-weight: var(--weight-regular); opacity: 0.8; }
.claim-timer {
    font-family: var(--font-mono);
    font-size: var(--text-xl);
    color: var(--warning);
    font-weight: var(--weight-bold);
}
@media (max-width: 992px) {
    .daily-grid { grid-template-columns: repeat(5, 1fr); }
}
@media (max-width: 768px) {
    .daily-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 480px) {
    .daily-grid { grid-template-columns: repeat(2, 1fr); }
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
            <a href="/user/daily.php" class="sidebar-nav-item active"><span class="sidebar-nav-icon"><i class="fas fa-calendar-day"></i></span><span class="sidebar-nav-text">Daily</span></a>
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
                <h1>Daily Rewards</h1>
                <p>Claim your reward every day and build your streak!</p>
            </div>
            <div class="balance-display">
                <span class="balance-display-amount" id="balanceDisplay"><?= formatCurrency($totalBalance) ?></span>
            </div>
        </div>

        <div class="daily-header">
            <div class="daily-streak"><i class="fas fa-fire"></i> <?= $streakDays ?> Day Streak</div>
        </div>

        <div class="daily-grid">
            <?php 
            $todayDayNum = ($streakDays % 30) + 1;
            foreach ($allRewards as $r): 
                $dayNum = $r['day_number'];
                $isClaimed = in_array($dayNum, $claimedDays);
                $isCurrent = $dayNum === $todayDayNum && $canClaimToday;
                $isLocked = $dayNum < $todayDayNum && !$isClaimed;
                $isFuture = $dayNum > $todayDayNum;
                $bonusDays = [5, 10, 15, 20, 25, 30];
                $isBonus = in_array($dayNum, $bonusDays);
                $rewardWithMultiplier = $r['reward_amount'] * $vipMultiplier;
            ?>
            <div class="daily-day <?= $isClaimed ? 'claimed' : ($isCurrent ? 'current' : ($isFuture ? 'future' : 'locked')) ?>">
                <span class="daily-day-number">Day <?= $dayNum ?></span>
                <?php if ($isClaimed): ?>
                <span class="daily-day-check"><i class="fas fa-check-circle"></i></span>
                <?php endif; ?>
                <div class="daily-day-reward">+<?= formatCurrency(min($rewardWithMultiplier, MAX_STREAK_BONUS)) ?></div>
                <div class="daily-day-label"><?= $isClaimed ? 'Claimed' : ($isCurrent ? 'Claim Now' : ($isFuture ? 'Coming' : 'Missed')) ?></div>
                <?php if ($isBonus): ?>
                <div class="daily-day-bonus">⭐ Bonus</div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="claim-section">
            <div style="font-size: var(--text-sm); color: var(--text-secondary); margin-bottom: var(--space-2);">Today's Reward</div>
            <div style="font-family: var(--font-display); font-size: var(--text-3xl); font-weight: var(--weight-bold); color: var(--success);">
                +<?= formatCurrency(min(($allRewards[$todayDayNum - 1]['reward_amount'] ?? DAILY_BONUS_BASE) * $vipMultiplier, MAX_STREAK_BONUS)) ?>
            </div>
            <div style="font-size: var(--text-xs); color: var(--text-tertiary); margin-bottom: var(--space-2);">
                <?php if ($vipMultiplier > 1): ?><?= $vipMultiplier ?>x VIP Multiplier active<?php endif; ?>
            </div>
            <button class="claim-btn-large" id="dailyClaimBtn" onclick="claimDailyReward()" <?= !$canClaimToday ? 'disabled' : '' ?>>
                <i class="fas fa-gift"></i>
                <span><?= $canClaimToday ? 'Claim Now' : 'Claimed' ?></span>
                <span class="sub">Daily Reward</span>
            </button>
            <div class="claim-timer" id="dailyTimer">
                <?php if (!$canClaimToday && $nextClaimTime > 0): ?>
                Next in: <?= gmdate('H:i:s', $nextClaimTime) ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="bottom-nav-spacer"></div>
    </main>
</div>

<script>
let dailyTimerSeconds = <?= $nextClaimTime ?>;
let dailyTimerInterval = null;

document.addEventListener('DOMContentLoaded', function() {
    if (dailyTimerSeconds > 0) startDailyTimer();
});

function claimDailyReward() {
    const btn = document.getElementById('dailyClaimBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Claiming...</span><span class="sub">Please wait</span>';

    const formData = new FormData();
    formData.append('csrf_token', '<?= csrf_token() ?>');

    fetch('/user/ajax/claim_daily.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            btn.innerHTML = '<i class="fas fa-check"></i><span>Claimed!</span><span class="sub">Come back tomorrow</span>';
            btn.style.background = 'var(--gradient-success)';
            if (data.new_balance_formatted) {
                document.getElementById('balanceDisplay').textContent = data.new_balance_formatted;
            }
            dailyTimerSeconds = data.next_claim_in || 86400;
            startDailyTimer();
            location.reload();
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-gift"></i><span>Claim Now</span><span class="sub">Daily Reward</span>';
            alert(data.error || 'Claim failed');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-gift"></i><span>Claim Now</span><span class="sub">Daily Reward</span>';
    });
}

function startDailyTimer() {
    if (dailyTimerInterval) clearInterval(dailyTimerInterval);
    const timer = document.getElementById('dailyTimer');
    const btn = document.getElementById('dailyClaimBtn');

    dailyTimerInterval = setInterval(() => {
        dailyTimerSeconds--;
        if (dailyTimerSeconds <= 0) {
            timer.textContent = '';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-gift"></i><span>Claim Now</span><span class="sub">Daily Reward</span>';
            btn.style.background = '';
            clearInterval(dailyTimerInterval);
            return;
        }
        const h = Math.floor(dailyTimerSeconds / 3600);
        const m = Math.floor((dailyTimerSeconds % 3600) / 60);
        const s = dailyTimerSeconds % 60;
        timer.textContent = 'Next in: ' + String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    }, 1000);
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
