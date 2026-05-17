<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAuth();
$user = getCurrentUser();
$pageTitle = 'Crypto Mining';
$userId = $user['id'];
$db = getDB();

$stmt = $db->prepare("SELECT * FROM mining_sessions WHERE user_id = ? ORDER BY started_at DESC LIMIT 1");
$stmt->execute([$userId]);
$activeSession = $stmt->fetch();

$stmt = $db->prepare("SELECT hash_rate, mining_level FROM user_mining WHERE user_id = ?");
$stmt->execute([$userId]);
$miningData = $stmt->fetch();
if (!$miningData) {
    $db->prepare("INSERT INTO user_mining (user_id, hash_rate, mining_level) VALUES (?, 1.0, 1)")->execute([$userId]);
    $miningData = ['hash_rate' => 1.0, 'mining_level' => 1];
}

$baseHashRate = (float)$miningData['hash_rate'];
$miningLevel = (int)$miningData['mining_level'];

$upgradeCost = $miningLevel * 5;

$stmt = $db->prepare("SELECT * FROM mining_sessions WHERE user_id = ? ORDER BY started_at DESC LIMIT 20");
$stmt->execute([$userId]);
$miningHistory = $stmt->fetchAll();

$isMining = $activeSession && $activeSession['ended_at'] === null;
$miningEarnings = 0;
if ($isMining) {
    $elapsed = time() - strtotime($activeSession['started_at']);
    $miningEarnings = $baseHashRate * 0.00001 * ($elapsed / 60);
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<style>
.mining-header { display: flex; align-items: center; gap: var(--space-6); margin-bottom: var(--space-6); flex-wrap: wrap; }
.mining-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-6); }
.mining-stat-card { background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-lg); padding: var(--space-5); text-align: center; }
.mining-stat-value { font-family: var(--font-display); font-size: var(--text-2xl); font-weight: var(--weight-bold); color: var(--primary); }
.mining-stat-label { font-size: var(--text-xs); color: var(--text-tertiary); margin-top: var(--space-1); }
.mining-rig { background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-lg); padding: var(--space-6); text-align: center; position: relative; overflow: hidden; margin-bottom: var(--space-6); }
.mining-rig.active { border-color: var(--success); }
.mining-rig::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; }
.mining-rig.active::before { background: var(--gradient-success); }
.mining-rig:not(.active)::before { background: var(--gradient-primary); }
.mining-rig-icon { font-size: var(--text-5xl); margin-bottom: var(--space-4); }
.mining-rig-icon.active { animation: miningPulse 1.5s ease-in-out infinite; }
.mining-rig-hash { font-family: var(--font-mono); font-size: var(--text-3xl); font-weight: var(--weight-bold); color: var(--primary); margin-bottom: var(--space-2); }
.mining-rig-label { font-size: var(--text-sm); color: var(--text-secondary); margin-bottom: var(--space-4); }
.mining-rig-earnings { font-family: var(--font-display); font-size: var(--text-2xl); font-weight: var(--weight-bold); color: var(--success); margin-bottom: var(--space-4); }
.upgrade-card { background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-lg); padding: var(--space-6); text-align: center; }
.upgrade-card h3 { font-family: var(--font-display); font-size: var(--text-lg); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-2); }
.upgrade-card p { font-size: var(--text-sm); color: var(--text-secondary); margin-bottom: var(--space-4); }
@keyframes miningPulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.1); opacity: 0.7; }
}
@keyframes hashGlow {
    0%, 100% { box-shadow: 0 0 5px rgba(0,184,148,0.3); }
    50% { box-shadow: 0 0 20px rgba(0,184,148,0.6); }
}
.mining-rig.active { animation: hashGlow 2s ease-in-out infinite; }
</style>

<div class="dashboard-wrapper">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-section">
            <div class="sidebar-section-title">Main Menu</div>
            <a href="/user/dashboard.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-home"></i></span><span class="sidebar-nav-text">Dashboard</span></a>
            <a href="/user/wallet.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-wallet"></i></span><span class="sidebar-nav-text">Wallet</span></a>
            <a href="/user/earnings.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-chart-line"></i></span><span class="sidebar-nav-text">Earnings</span></a>
            <a href="/user/games.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-gamepad"></i></span><span class="sidebar-nav-text">Games</span></a>
            <a href="/user/mining.php" class="sidebar-nav-item active"><span class="sidebar-nav-icon"><i class="fas fa-microchip"></i></span><span class="sidebar-nav-text">Mining</span></a>
            <a href="/user/referrals.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-users"></i></span><span class="sidebar-nav-text">Referrals</span></a>
            <a href="/user/withdraw.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-cash-register"></i></span><span class="sidebar-nav-text">Withdraw</span></a>
            <a href="/user/deposit.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-plus-circle"></i></span><span class="sidebar-nav-text">Deposit</span></a>
        </div>
        <div class="sidebar-section">
            <div class="sidebar-section-title">Account</div>
            <a href="/user/profile.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-user"></i></span><span class="sidebar-nav-text">Profile</span></a>
            <a href="/user/settings.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-cog"></i></span><span class="sidebar-nav-text">Settings</span></a>
            <a href="/user/support.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-headset"></i></span><span class="sidebar-nav-text">Support</span></a>
            <a href="/auth/logout.php" class="sidebar-nav-item logout-item"><span class="sidebar-nav-icon"><i class="fas fa-sign-out-alt"></i></span><span class="sidebar-nav-text">Logout</span></a>
        </div>
    </aside>
    <main class="main-content">
        <div class="dashboard-top-header">
            <div class="dashboard-greeting">
                <h1>Crypto Mining</h1>
                <p>Simulate mining and earn passive rewards</p>
            </div>
            <div class="dashboard-actions">
                <div class="balance-display" onclick="toggleBalanceVisibility()">
                    <span class="balance-display-icon"><i class="fas fa-eye" id="eyeIcon"></i></span>
                    <span class="balance-display-amount" id="headerBalance"><?= formatCurrency(getUserBalance($userId)) ?></span>
                </div>
            </div>
        </div>

        <div class="mining-stats-grid">
            <div class="mining-stat-card">
                <div class="mining-stat-value"><?= number_format($baseHashRate, 1) ?> H/s</div>
                <div class="mining-stat-label">Hash Rate</div>
            </div>
            <div class="mining-stat-card">
                <div class="mining-stat-value">Level <?= $miningLevel ?></div>
                <div class="mining-stat-label">Mining Level</div>
            </div>
            <div class="mining-stat-card">
                <div class="mining-stat-value" id="miningEarningsDisplay"><?= formatCurrency($miningEarnings) ?></div>
                <div class="mining-stat-label">Session Earnings</div>
            </div>
            <div class="mining-stat-card">
                <div class="mining-stat-value" style="color: <?= $isMining ? 'var(--success)' : 'var(--text-tertiary)' ?>;" id="miningStatus">
                    <?= $isMining ? 'Active' : 'Idle' ?>
                </div>
                <div class="mining-stat-label">Status</div>
            </div>
        </div>

        <div class="mining-rig <?= $isMining ? 'active' : '' ?>" id="miningRig">
            <div class="mining-rig-icon <?= $isMining ? 'active' : '' ?>" id="miningIcon">
                <i class="fas fa-microchip"></i>
            </div>
            <div class="mining-rig-hash" id="miningHashDisplay"><?= number_format($baseHashRate, 1) ?> H/s</div>
            <div class="mining-rig-label">Current Hash Rate</div>
            <div class="mining-rig-earnings" id="miningSessionEarnings"><?= formatCurrency($miningEarnings) ?></div>
            <div>
                <?php if ($isMining): ?>
                <button class="btn btn-danger btn-lg" id="miningToggleBtn" onclick="toggleMining()"><i class="fas fa-stop"></i> Stop Mining</button>
                <?php else: ?>
                <button class="btn btn-success btn-lg" id="miningToggleBtn" onclick="toggleMining()"><i class="fas fa-play"></i> Start Mining</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="two-col-grid">
            <div class="upgrade-card">
                <h3><i class="fas fa-arrow-up" style="color: var(--primary);"></i> Upgrade</h3>
                <p>Increase your hash rate to earn more. Current: <strong><?= number_format($baseHashRate, 1) ?> H/s</strong></p>
                <div style="margin-bottom: var(--space-4);">
                    <div style="display: flex; justify-content: space-between; font-size: var(--text-sm); color: var(--text-secondary); margin-bottom: var(--space-2);">
                        <span>Next upgrade: <?= number_format($baseHashRate + 0.5, 1) ?> H/s</span>
                        <span>Cost: <?= formatCurrency($upgradeCost) ?></span>
                    </div>
                </div>
                <button class="btn btn-primary btn-block" id="upgradeBtn" onclick="upgradeMining()" <?= getUSerBalance($userId) < $upgradeCost ? 'disabled' : '' ?>>
                    <i class="fas fa-rocket"></i> Upgrade (<?= formatCurrency($upgradeCost) ?>)
                </button>
                <div id="upgradeMessage" style="margin-top: var(--space-3); display: none;"></div>
            </div>
        </div>

        <div class="dashboard-panel" style="margin-top: var(--space-6);">
            <div class="dashboard-panel-header">
                <div class="dashboard-panel-title"><i class="fas fa-history"></i> Mining History</div>
            </div>
            <div class="table-container">
                <table class="table table-sm">
                    <thead><tr><th>Started</th><th>Ended</th><th>Earnings</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php if (count($miningHistory) > 0): ?>
                        <?php foreach ($miningHistory as $m): ?>
                        <tr>
                            <td><span class="text-mono" style="font-size: var(--text-xs); color: var(--text-tertiary);"><?= date('M d, H:i', strtotime($m['started_at'])) ?></span></td>
                            <td><span class="text-mono" style="font-size: var(--text-xs); color: var(--text-tertiary);"><?= $m['ended_at'] ? date('M d, H:i', strtotime($m['ended_at'])) : 'In progress' ?></span></td>
                            <td><span style="color: var(--success); font-weight: var(--weight-semibold);">+<?= formatCurrency($m['earnings'] ?? 0) ?></span></td>
                            <td><span class="badge badge-<?= $m['ended_at'] ? 'neutral' : 'success' ?> badge-sm"><?= $m['ended_at'] ? 'Completed' : 'Active' ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr><td colspan="4"><div class="table-empty"><div class="table-empty-icon"><i class="fas fa-microchip"></i></div><p>No mining sessions yet</p></div></td></tr>
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
    if (balanceVisible) { el.textContent = '<?= formatCurrency(getUserBalance($userId)) ?>'; icon.className = 'fas fa-eye'; }
    else { el.textContent = '••••••'; icon.className = 'fas fa-eye-slash'; }
}

let isMining = <?= $isMining ? 'true' : 'false' ?>;
let baseHashRate = <?= $baseHashRate ?>;
let sessionEarnings = <?= $miningEarnings ?>;
let sessionStart = <?= $isMining ? strtotime($activeSession['started_at']) : 0 ?>;
let miningInterval = null;

if (isMining) {
    startMiningCounter();
}

function startMiningCounter() {
    if (miningInterval) clearInterval(miningInterval);
    miningInterval = setInterval(() => {
        const elapsed = (Date.now() / 1000) - (sessionStart * 1000);
        sessionEarnings = baseHashRate * 0.00001 * ((elapsed) / 60);
        document.getElementById('miningSessionEarnings').textContent = '<?= APP_CURRENCY_SYMBOL ?>' + sessionEarnings.toFixed(6);
        document.getElementById('miningEarningsDisplay').textContent = '<?= APP_CURRENCY_SYMBOL ?>' + sessionEarnings.toFixed(6);
    }, 1000);
}

function toggleMining() {
    const btn = document.getElementById('miningToggleBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

    const formData = new FormData();
    formData.append('action', isMining ? 'stop' : 'start');
    formData.append('csrf_token', '<?= csrf_token() ?>');

    fetch('/user/ajax/toggle_mining.php', {
        method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            isMining = !isMining;
            const rig = document.getElementById('miningRig');
            const icon = document.getElementById('miningIcon');
            const status = document.getElementById('miningStatus');
            if (isMining) {
                rig.classList.add('active');
                icon.classList.add('active');
                sessionStart = Date.now() / 1000;
                sessionEarnings = 0;
                startMiningCounter();
                btn.className = 'btn btn-danger btn-lg';
                btn.innerHTML = '<i class="fas fa-stop"></i> Stop Mining';
                status.textContent = 'Active';
                status.style.color = 'var(--success)';
            } else {
                rig.classList.remove('active');
                icon.classList.remove('active');
                if (miningInterval) clearInterval(miningInterval);
                btn.className = 'btn btn-success btn-lg';
                btn.innerHTML = '<i class="fas fa-play"></i> Start Mining';
                status.textContent = 'Idle';
                status.style.color = 'var(--text-tertiary)';
            }
            if (data.balance !== undefined) {
                document.getElementById('headerBalance').textContent = '<?= APP_CURRENCY_SYMBOL ?>' + data.balance.toFixed(2);
            }
        } else {
            alert(data.error || 'Failed to toggle mining');
        }
        btn.disabled = false;
    })
    .catch(() => { btn.disabled = false; btn.innerHTML = isMining ? '<i class="fas fa-stop"></i> Stop Mining' : '<i class="fas fa-play"></i> Start Mining'; });
}

function upgradeMining() {
    const btn = document.getElementById('upgradeBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Upgrading...';

    const formData = new FormData();
    formData.append('csrf_token', '<?= csrf_token() ?>');

    fetch('/user/ajax/upgrade_mining.php', {
        method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData
    })
    .then(r => r.json())
    .then(data => {
        const msg = document.getElementById('upgradeMessage');
        msg.style.display = 'block';
        if (data.success) {
            msg.className = 'alert alert-success';
            msg.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
            location.reload();
        } else {
            msg.className = 'alert alert-danger';
            msg.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + (data.error || 'Upgrade failed');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-rocket"></i> Upgrade';
        }
    })
    .catch(() => {
        document.getElementById('upgradeMessage').style.display = 'block';
        document.getElementById('upgradeMessage').className = 'alert alert-danger';
        document.getElementById('upgradeMessage').innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-rocket"></i> Upgrade';
    });
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
