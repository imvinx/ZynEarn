<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAuth();
$user = getCurrentUser();
$pageTitle = 'Spin Wheel';
$userId = $user['id'];
$db = getDB();

$stmt = $db->prepare("SELECT * FROM spin_wheels WHERE id = 1");
$stmt->execute();
$wheel = $stmt->fetch();

if (!$wheel) {
    $segments = [
        ['label' => '$0.50', 'color' => '#6C5CE7', 'amount' => 0.5],
        ['label' => '$0.10', 'color' => '#00CEC9', 'amount' => 0.1],
        ['label' => '$0.25', 'color' => '#FD79A8', 'amount' => 0.25],
        ['label' => '$0.05', 'color' => '#00B894', 'amount' => 0.05],
        ['label' => '$1.00', 'color' => '#FDCB6E', 'amount' => 1.0],
        ['label' => '$0.02', 'color' => '#74B9FF', 'amount' => 0.02],
        ['label' => '$0.75', 'color' => '#E17055', 'amount' => 0.75],
        ['label' => 'Try Again', 'color' => '#636E72', 'amount' => 0],
    ];
    $spinCost = SPIN_COST;
    $cooldown = SPIN_COOLDOWN;
} else {
    $segments = json_decode($wheel['segments'], true) ?: [];
    $spinCost = $wheel['spin_cost'];
    $cooldown = $wheel['cooldown_minutes'] * 60;
}

$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM earnings WHERE user_id = ? AND type = 'spin' AND status = 'credited'");
$stmt->execute([$userId]);
$totalSpinEarned = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as count FROM spin_results WHERE user_id = ?");
$stmt->execute([$userId]);
$totalSpins = $stmt->fetch()['count'];

$stmt = $db->prepare("SELECT sr.*, sw.name as wheel_name FROM spin_results sr JOIN spin_wheels sw ON sr.wheel_id = sw.id WHERE sr.user_id = ? ORDER BY sr.created_at DESC LIMIT 10");
$stmt->execute([$userId]);
$spinHistory = $stmt->fetchAll();

$stmt = $db->prepare("SELECT created_at FROM spin_results WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$userId]);
$lastSpin = $stmt->fetch();
$cooldownRemaining = $lastSpin ? max(0, $cooldown - (time() - strtotime($lastSpin['created_at']))) : 0;

$totalBalance = getUserBalance($userId);
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<style>
.spin-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--space-6);
    align-items: start;
}
.spin-wheel-container {
    text-align: center;
    background: var(--card-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    padding: var(--space-6);
}
.spin-canvas-wrap {
    position: relative;
    width: 320px;
    height: 320px;
    margin: 0 auto;
}
#spinCanvas {
    width: 320px;
    height: 320px;
}
.spin-pointer {
    position: absolute;
    top: -10px;
    left: 50%;
    transform: translateX(-50%);
    width: 0;
    height: 0;
    border-left: 14px solid transparent;
    border-right: 14px solid transparent;
    border-top: 24px solid var(--danger);
    z-index: 2;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
}
.spin-center-btn {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 64px;
    height: 64px;
    border-radius: var(--radius-full);
    background: var(--gradient-primary);
    color: var(--white);
    font-weight: var(--weight-bold);
    font-size: var(--text-sm);
    border: 4px solid var(--bg-card);
    cursor: pointer;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition);
    box-shadow: var(--shadow-glow);
}
.spin-center-btn:hover { transform: translate(-50%, -50%) scale(1.1); }
.spin-center-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.spin-info {
    display: flex;
    justify-content: center;
    gap: var(--space-4);
    margin-top: var(--space-4);
}
.spin-stat {
    text-align: center;
    padding: var(--space-3);
    background: var(--bg-card-hover);
    border-radius: var(--radius-md);
    min-width: 100px;
}
.spin-stat-value {
    font-family: var(--font-display);
    font-size: var(--text-lg);
    font-weight: var(--weight-bold);
    color: var(--text-primary);
}
.spin-stat-label {
    font-size: var(--text-xs);
    color: var(--text-tertiary);
}
.spin-history-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--space-3);
    background: var(--bg-card-hover);
    border-radius: var(--radius-md);
    margin-bottom: var(--space-2);
    font-size: var(--text-sm);
}
.spin-history-reward {
    font-family: var(--font-mono);
    font-weight: var(--weight-semibold);
}
.spin-history-reward.win { color: var(--success); }
.spin-history-reward.lose { color: var(--danger); }
.spin-history-date { font-size: var(--text-xs); color: var(--text-tertiary); }
.cooldown-timer {
    font-family: var(--font-mono);
    font-size: var(--text-lg);
    color: var(--warning);
    font-weight: var(--weight-bold);
}
.win-popup {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: var(--overlay);
    z-index: var(--z-modal-backdrop);
    display: none;
    align-items: center;
    justify-content: center;
}
.win-popup.active { display: flex; }
.win-popup-content {
    background: var(--bg-modal);
    border: 1px solid var(--border-primary);
    border-radius: var(--radius-xl);
    padding: var(--space-8);
    text-align: center;
    max-width: 400px;
    width: 90%;
    animation: popIn 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}
@keyframes popIn {
    from { transform: scale(0.5); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}
.win-popup-icon {
    font-size: var(--text-6xl);
    margin-bottom: var(--space-4);
}
.win-popup-amount {
    font-family: var(--font-display);
    font-size: var(--text-4xl);
    font-weight: var(--weight-bold);
    color: var(--success);
    margin-bottom: var(--space-2);
}
.win-popup-label {
    font-size: var(--text-lg);
    color: var(--text-secondary);
    margin-bottom: var(--space-6);
}
@media (max-width: 992px) {
    .spin-layout { grid-template-columns: 1fr; }
    .spin-canvas-wrap { width: 260px; height: 260px; }
    #spinCanvas { width: 260px; height: 260px; }
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
            <a href="/user/spin.php" class="sidebar-nav-item active"><span class="sidebar-nav-icon"><i class="fas fa-circle-notch"></i></span><span class="sidebar-nav-text">Spin</span></a>
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
                <h1>Spin & Win</h1>
                <p>Try your luck on the wheel!</p>
            </div>
            <div class="balance-display">
                <span class="balance-display-amount" id="balanceDisplay"><?= formatCurrency($totalBalance) ?></span>
            </div>
        </div>

        <div class="spin-layout">
            <div>
                <div class="spin-wheel-container">
                    <div class="spin-canvas-wrap">
                        <div class="spin-pointer"></div>
                        <canvas id="spinCanvas" width="320" height="320"></canvas>
                        <button class="spin-center-btn" id="spinBtn" onclick="performSpin()">SPIN</button>
                    </div>
                    <div class="spin-info">
                        <div class="spin-stat">
                            <div class="spin-stat-value"><?= $totalSpins ?></div>
                            <div class="spin-stat-label">Total Spins</div>
                        </div>
                        <div class="spin-stat">
                            <div class="spin-stat-value" style="color: var(--success);"><?= formatCurrency($totalSpinEarned) ?></div>
                            <div class="spin-stat-label">Total Won</div>
                        </div>
                        <div class="spin-stat">
                            <div class="cooldown-timer" id="cooldownTimer"><?php if ($cooldownRemaining > 0): ?><?= gmdate('i:s', $cooldownRemaining) ?><?php else: ?>Ready<?php endif; ?></div>
                            <div class="spin-stat-label">Cooldown</div>
                        </div>
                    </div>
                </div>
            </div>
            <div>
                <div class="dashboard-panel">
                    <div class="dashboard-panel-header">
                        <div class="dashboard-panel-title">Spin History</div>
                    </div>
                    <?php if (count($spinHistory) > 0): ?>
                    <?php foreach ($spinHistory as $sh): ?>
                    <div class="spin-history-item">
                        <span><?= sanitize($sh['reward'] ?? 'Spin') ?></span>
                        <span class="spin-history-reward <?= $sh['reward'] && $sh['reward'] !== 'Try Again' ? 'win' : 'lose' ?>"><?= $sh['reward'] ?? 'No win' ?></span>
                        <span class="spin-history-date"><?= timeAgo($sh['created_at']) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div style="padding: var(--space-6); text-align: center; color: var(--text-tertiary);">
                        <i class="fas fa-history" style="font-size: var(--text-3xl); opacity: 0.3; margin-bottom: var(--space-2); display: block;"></i>
                        <p style="font-size: var(--text-sm);">No spins yet</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="bottom-nav-spacer"></div>
    </main>
</div>

<div class="win-popup" id="winPopup">
    <div class="win-popup-content">
        <div class="win-popup-icon">🎉</div>
        <div class="win-popup-amount" id="winAmount">+$0.00</div>
        <div class="win-popup-label" id="winLabel">You won!</div>
        <button class="btn btn-primary" onclick="closeWinPopup()">Awesome!</button>
    </div>
</div>

<script>
const segments = <?= json_encode($segments) ?>;
let wheelRotation = 0;
let isSpinning = false;
let cooldownRemaining = <?= $cooldownRemaining ?>;

const canvas = document.getElementById('spinCanvas');
const ctx = canvas.getContext('2d');
const W = 320, H = 320;
const CX = W / 2, CY = H / 2;
const RADIUS = 150;

function drawWheel(rotation) {
    ctx.clearRect(0, 0, W, H);
    const sliceAngle = (Math.PI * 2) / segments.length;

    segments.forEach((seg, i) => {
        const startAngle = rotation + i * sliceAngle;
        const endAngle = startAngle + sliceAngle;

        ctx.fillStyle = seg.color || '#6C5CE7';
        ctx.beginPath();
        ctx.moveTo(CX, CY);
        ctx.arc(CX, CY, RADIUS, startAngle, endAngle);
        ctx.closePath();
        ctx.fill();

        ctx.strokeStyle = 'rgba(255,255,255,0.2)';
        ctx.lineWidth = 1;
        ctx.stroke();

        const labelAngle = startAngle + sliceAngle / 2;
        ctx.save();
        ctx.translate(CX + Math.cos(labelAngle) * (RADIUS * 0.65), CY + Math.sin(labelAngle) * (RADIUS * 0.65));
        ctx.rotate(labelAngle + Math.PI / 2);
        ctx.fillStyle = '#fff';
        ctx.font = 'bold 12px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(seg.label || '', 0, 0);
        ctx.restore();
    });

    ctx.fillStyle = '#1a1a35';
    ctx.beginPath();
    ctx.arc(CX, CY, 28, 0, Math.PI * 2);
    ctx.fill();
}

function performSpin() {
    if (isSpinning) return;
    if (cooldownRemaining > 0) {
        alert('Please wait for cooldown: ' + Math.ceil(cooldownRemaining / 60) + 'm ' + (cooldownRemaining % 60) + 's');
        return;
    }

    isSpinning = true;
    document.getElementById('spinBtn').disabled = true;

    const formData = new FormData();
    formData.append('wheel_id', <?= $wheel ? $wheel['id'] : 1 ?>);
    formData.append('csrf_token', '<?= csrf_token() ?>');

    fetch('/user/ajax/spin_action.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const targetIndex = data.segment_index || 0;
            const targetAngle = targetIndex * (Math.PI * 2 / segments.length);
            const spins = 5 + Math.random() * 3;
            const totalRotation = spins * Math.PI * 2 - targetAngle + wheelRotation;

            animateSpin(totalRotation, () => {
                const reward = data.reward || 'Try Again';
                if (data.won && data.amount > 0) {
                    document.getElementById('winAmount').textContent = '+' + data.formatted_amount;
                    document.getElementById('winLabel').textContent = 'Congratulations! You won ' + data.formatted_amount + '!';
                    document.getElementById('winPopup').classList.add('active');
                    fireConfetti();
                }
                document.getElementById('balanceDisplay').textContent = data.new_balance_formatted;
                if (data.cooldown) {
                    cooldownRemaining = data.cooldown;
                    startCooldown();
                }
                isSpinning = false;
                document.getElementById('spinBtn').disabled = false;
            });
        } else {
            alert(data.error || 'Spin failed');
            isSpinning = false;
            document.getElementById('spinBtn').disabled = false;
        }
    })
    .catch(() => {
        isSpinning = false;
        document.getElementById('spinBtn').disabled = false;
    });
}

function animateSpin(target, callback) {
    const startRotation = wheelRotation;
    const duration = 4000;
    const start = performance.now();

    function step(now) {
        const elapsed = now - start;
        const progress = Math.min(elapsed / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 4);
        wheelRotation = startRotation + (target - startRotation) * eased;
        drawWheel(wheelRotation);

        if (progress < 1) {
            requestAnimationFrame(step);
        } else {
            wheelRotation = target % (Math.PI * 2);
            drawWheel(wheelRotation);
            if (callback) callback();
        }
    }
    requestAnimationFrame(step);
}

function startCooldown() {
    const timer = document.getElementById('cooldownTimer');
    const interval = setInterval(() => {
        cooldownRemaining--;
        if (cooldownRemaining <= 0) {
            timer.textContent = 'Ready';
            timer.style.color = 'var(--success)';
            clearInterval(interval);
            return;
        }
        const m = Math.floor(cooldownRemaining / 60);
        const s = cooldownRemaining % 60;
        timer.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    }, 1000);
}

function closeWinPopup() {
    document.getElementById('winPopup').classList.remove('active');
}

function fireConfetti() {
    const colors = ['#6C5CE7', '#00CEC9', '#FD79A8', '#00B894', '#FDCB6E'];
    for (let i = 0; i < 80; i++) {
        const el = document.createElement('div');
        el.style.cssText = `
            position: fixed; width: ${4 + Math.random() * 6}px; height: ${4 + Math.random() * 6}px;
            background: ${colors[i % colors.length]}; border-radius: 2px;
            top: -10px; left: ${Math.random() * 100}vw;
            z-index: 9999; pointer-events: none;
            animation: confettiDrop ${1.5 + Math.random() * 2}s linear forwards;
            animation-delay: ${Math.random() * 0.8}s;
        `;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 4000);
    }
}

const confettiStyle = document.createElement('style');
confettiStyle.textContent = `
@keyframes confettiDrop {
    0% { transform: translateY(0) rotate(0deg) scale(1); opacity: 1; }
    100% { transform: translateY(100vh) rotate(720deg) scale(0.5); opacity: 0; }
}`;
document.head.appendChild(confettiStyle);

drawWheel(0);
if (cooldownRemaining > 0) startCooldown();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
