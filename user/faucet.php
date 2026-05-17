<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAuth();
$user = getCurrentUser();
$pageTitle = 'Faucet';
$userId = $user['id'];
$db = getDB();

$vipTier = $user['vip_tier'] ?? 'free';
$multiplier = getMembershipMultiplier($vipTier);
$baseReward = FAUCET_BASE_REWARD;
$maxReward = FAUCET_MAX_REWARD;
$bonusMultiplier = FAUCET_BONUS_MULTIPLIER;

$stmt = $db->prepare("SELECT created_at FROM faucet_claims WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$userId]);
$lastClaim = $stmt->fetch();
$cooldown = FAUCET_COOLDOWN;
$remaining = $lastClaim ? max(0, $cooldown - (time() - strtotime($lastClaim['created_at']))) : 0;

$stmt = $db->prepare("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM faucet_claims WHERE user_id = ?");
$stmt->execute([$userId]);
$claimStats = $stmt->fetch();

$stmt = $db->prepare("SELECT * FROM faucet_claims WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$userId]);
$claimHistory = $stmt->fetchAll();

$totalBalance = getUserBalance($userId);
$autoFaucet = $vipTier === 'vip' || $vipTier === 'platinum';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<style>
.faucet-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--space-6);
}
.faucet-main {
    text-align: center;
    background: var(--card-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    padding: var(--space-8);
}
.faucet-icon {
    font-size: var(--text-6xl);
    color: var(--info);
    margin-bottom: var(--space-4);
    animation: faucetDrip 2s ease-in-out infinite;
}
@keyframes faucetDrip {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-8px); }
}
.faucet-reward-display {
    font-family: var(--font-display);
    font-size: var(--text-4xl);
    font-weight: var(--weight-bold);
    color: var(--primary);
    margin-bottom: var(--space-2);
}
.faucet-multipliers {
    display: flex;
    justify-content: center;
    gap: var(--space-4);
    margin-bottom: var(--space-6);
    flex-wrap: wrap;
}
.faucet-multiplier {
    padding: var(--space-2) var(--space-4);
    background: var(--bg-card-hover);
    border-radius: var(--radius-full);
    font-size: var(--text-sm);
    color: var(--text-secondary);
}
.faucet-multiplier.active {
    background: rgba(108,92,231,0.15);
    color: var(--primary);
    border: 1px solid var(--primary);
}
.faucet-claim-btn {
    width: 200px;
    height: 200px;
    border-radius: var(--radius-full);
    background: var(--gradient-primary);
    color: var(--white);
    font-family: var(--font-display);
    font-size: var(--text-2xl);
    font-weight: var(--weight-bold);
    border: none;
    cursor: pointer;
    transition: var(--transition);
    box-shadow: var(--shadow-glow);
    margin: var(--space-4) auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: var(--space-2);
}
.faucet-claim-btn:hover { transform: scale(1.05); box-shadow: 0 0 40px rgba(108, 92, 231, 0.5); }
.faucet-claim-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
.faucet-claim-btn .sub {
    font-size: var(--text-sm);
    font-weight: var(--weight-regular);
    opacity: 0.8;
}
.faucet-timer {
    font-family: var(--font-mono);
    font-size: var(--text-2xl);
    color: var(--warning);
    font-weight: var(--weight-bold);
    margin-top: var(--space-4);
}
.faucet-claim-result {
    margin-top: var(--space-4);
    padding: var(--space-4);
    border-radius: var(--radius-md);
    display: none;
}
.faucet-claim-result.success {
    display: block;
    background: rgba(0,184,148,0.1);
    border: 1px solid var(--success);
}
.faucet-claim-result.error {
    display: block;
    background: rgba(225,112,85,0.1);
    border: 1px solid var(--danger);
}
.claim-result-amount {
    font-family: var(--font-display);
    font-size: var(--text-3xl);
    font-weight: var(--weight-bold);
    color: var(--success);
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
.history-amount { font-family: var(--font-mono); color: var(--success); font-weight: var(--weight-semibold); }
.history-multiplier { font-size: var(--text-xs); color: var(--text-tertiary); }
.history-date { font-size: var(--text-xs); color: var(--text-tertiary); }
.auto-faucet-badge {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    padding: var(--space-2) var(--space-4);
    background: rgba(108,92,231,0.15);
    border: 1px solid var(--primary);
    border-radius: var(--radius-full);
    font-size: var(--text-sm);
    color: var(--primary);
    font-weight: var(--weight-semibold);
    margin-bottom: var(--space-4);
}
@media (max-width: 992px) {
    .faucet-layout { grid-template-columns: 1fr; }
    .faucet-claim-btn { width: 160px; height: 160px; font-size: var(--text-xl); }
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
            <a href="/user/faucet.php" class="sidebar-nav-item active"><span class="sidebar-nav-icon"><i class="fas fa-water"></i></span><span class="sidebar-nav-text">Faucet</span></a>
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
                <h1>Faucet</h1>
                <p>Claim free rewards every few minutes</p>
            </div>
            <div class="balance-display">
                <span class="balance-display-amount" id="balanceDisplay"><?= formatCurrency($totalBalance) ?></span>
            </div>
        </div>

        <div class="faucet-layout">
            <div>
                <div class="faucet-main">
                    <?php if ($autoFaucet): ?>
                    <div class="auto-faucet-badge"><i class="fas fa-bolt"></i> VIP Auto-Faucet Active</div>
                    <?php endif; ?>
                    <div class="faucet-icon"><i class="fas fa-water"></i></div>
                    <div class="faucet-reward-display">
                        <span id="rewardDisplay"><?= formatCurrency($baseReward * $multiplier) ?></span>
                    </div>
                    <div style="font-size: var(--text-sm); color: var(--text-secondary); margin-bottom: var(--space-4);">per claim</div>
                    <div class="faucet-multipliers">
                        <span class="faucet-multiplier">Base: <?= formatCurrency($baseReward) ?></span>
                        <span class="faucet-multiplier <?= $multiplier > 1 ? 'active' : '' ?>">VIP: <?= $multiplier ?>x</span>
                        <span class="faucet-multiplier">Max: <?= formatCurrency($maxReward) ?></span>
                    </div>
                    <button class="faucet-claim-btn" id="claimBtn" onclick="claimFaucet()" <?= $remaining > 0 ? 'disabled' : '' ?>>
                        <i class="fas fa-hand-holding-water"></i>
                        <span>Claim</span>
                        <span class="sub">Free Reward</span>
                    </button>
                    <div class="faucet-timer" id="faucetTimer"><?= $remaining > 0 ? gmdate('i:s', $remaining) : '' ?></div>
                    <div class="faucet-claim-result" id="claimResult"></div>
                </div>
            </div>
            <div>
                <div class="dashboard-panel">
                    <div class="dashboard-panel-header">
                        <div class="dashboard-panel-title">Claim Stats</div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); margin-bottom: var(--space-4);">
                        <div style="text-align: center; padding: var(--space-4); background: var(--bg-card-hover); border-radius: var(--radius-md);">
                            <div style="font-family: var(--font-display); font-size: var(--text-2xl); font-weight: var(--weight-bold); color: var(--text-primary);"><?= $claimStats['count'] ?></div>
                            <div style="font-size: var(--text-xs); color: var(--text-tertiary);">Total Claims</div>
                        </div>
                        <div style="text-align: center; padding: var(--space-4); background: var(--bg-card-hover); border-radius: var(--radius-md);">
                            <div style="font-family: var(--font-display); font-size: var(--text-2xl); font-weight: var(--weight-bold); color: var(--success);"><?= formatCurrency($claimStats['total']) ?></div>
                            <div style="font-size: var(--text-xs); color: var(--text-tertiary);">Total Earned</div>
                        </div>
                    </div>
                </div>

                <div class="dashboard-panel" style="margin-top: var(--space-4);">
                    <div class="dashboard-panel-header">
                        <div class="dashboard-panel-title">Claim History</div>
                    </div>
                    <?php if (count($claimHistory) > 0): ?>
                    <?php foreach ($claimHistory as $ch): ?>
                    <div class="history-item">
                        <span class="history-amount">+<?= formatCurrency($ch['amount']) ?></span>
                        <span class="history-multiplier"><?= $ch['reward_multiplier'] ?>x</span>
                        <span class="history-date"><?= timeAgo($ch['created_at']) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div style="padding: var(--space-6); text-align: center; color: var(--text-tertiary);">
                        <i class="fas fa-clock" style="font-size: var(--text-3xl); opacity: 0.3; margin-bottom: var(--space-2); display: block;"></i>
                        <p style="font-size: var(--text-sm);">No claims yet</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="bottom-nav-spacer"></div>
    </main>
</div>

<script>
let faucetCooldown = <?= $remaining ?>;
let isClaiming = false;

function claimFaucet() {
    if (isClaiming || faucetCooldown > 0) return;
    isClaiming = true;

    const btn = document.getElementById('claimBtn');
    const result = document.getElementById('claimResult');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Claiming...</span><span class="sub">Please wait</span>';
    result.className = 'faucet-claim-result';
    result.style.display = 'none';

    const formData = new FormData();
    formData.append('csrf_token', '<?= csrf_token() ?>');

    fetch('/user/ajax/faucet_claim.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        isClaiming = false;
        if (data.success) {
            result.className = 'faucet-claim-result success';
            result.style.display = 'block';
            result.innerHTML = '<div class="claim-result-amount">+' + data.formatted_amount + '</div><div style="font-size: var(--text-sm); color: var(--text-secondary);">' + (data.message || 'Claimed successfully!') + '</div>';

            if (data.new_balance_formatted) {
                document.getElementById('balanceDisplay').textContent = data.new_balance_formatted;
            }

            btn.innerHTML = '<i class="fas fa-hand-holding-water"></i><span>Claim</span><span class="sub">Free Reward</span>';

            if (data.cooldown) {
                faucetCooldown = data.cooldown;
                startFaucetTimer();
            }

            fireConfetti();
        } else {
            result.className = 'faucet-claim-result error';
            result.style.display = 'block';
            result.innerHTML = '<div style="color: var(--danger); font-weight: var(--weight-semibold);">' + (data.error || 'Claim failed') + '</div>';
            btn.innerHTML = '<i class="fas fa-hand-holding-water"></i><span>Claim</span><span class="sub">Free Reward</span>';
            btn.disabled = false;
        }
    })
    .catch(() => {
        isClaiming = false;
        btn.innerHTML = '<i class="fas fa-hand-holding-water"></i><span>Claim</span><span class="sub">Free Reward</span>';
        btn.disabled = false;
    });
}

function startFaucetTimer() {
    const timer = document.getElementById('faucetTimer');
    const btn = document.getElementById('claimBtn');
    const interval = setInterval(() => {
        faucetCooldown--;
        if (faucetCooldown <= 0) {
            timer.textContent = '';
            btn.disabled = false;
            clearInterval(interval);
            return;
        }
        const m = Math.floor(faucetCooldown / 60);
        const s = faucetCooldown % 60;
        timer.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    }, 1000);
}

function fireConfetti() {
    const colors = ['#6C5CE7', '#00CEC9', '#FD79A8', '#00B894', '#FDCB6E'];
    for (let i = 0; i < 50; i++) {
        const el = document.createElement('div');
        el.style.cssText = `position:fixed;width:6px;height:6px;background:${colors[i%colors.length]};border-radius:50%;top:-10px;left:${Math.random()*100}vw;z-index:9999;pointer-events:none;animation:faucetConfetti ${1+Math.random()*2}s linear forwards;animation-delay:${Math.random()*0.5}s;`;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 3000);
    }
}
const fcStyle = document.createElement('style');
fcStyle.textContent = '@keyframes faucetConfetti{0%{transform:translateY(0) rotate(0deg);opacity:1}100%{transform:translateY(100vh) rotate(720deg);opacity:0}}';
document.head.appendChild(fcStyle);

if (faucetCooldown > 0) startFaucetTimer();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
