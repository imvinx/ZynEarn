<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAuth();
$user = getCurrentUser();
$pageTitle = 'Scratch Cards';
$userId = $user['id'];
$db = getDB();

$stmt = $db->prepare("SELECT * FROM scratch_cards WHERE status = 'active' ORDER BY price ASC");
$stmt->execute();
$cards = $stmt->fetchAll();

$stmt = $db->prepare("SELECT sp.*, sc.name as card_name, sc.price as card_price FROM scratch_plays sp JOIN scratch_cards sc ON sp.card_id = sc.id WHERE sp.user_id = ? ORDER BY sp.played_at DESC LIMIT 10");
$stmt->execute([$userId]);
$history = $stmt->fetchAll();

$totalBalance = getUserBalance($userId);
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<style>
.scratch-shop {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: var(--space-4);
    margin-bottom: var(--space-6);
}
.scratch-card-shop {
    background: var(--card-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    padding: var(--space-5);
    text-align: center;
    transition: var(--transition);
    cursor: pointer;
}
.scratch-card-shop:hover {
    border-color: var(--primary);
    transform: translateY(-4px);
    box-shadow: var(--shadow-md);
}
.scratch-card-shop-icon {
    font-size: var(--text-4xl);
    margin-bottom: var(--space-3);
    color: var(--accent);
}
.scratch-card-shop-name {
    font-size: var(--text-base);
    font-weight: var(--weight-semibold);
    color: var(--text-primary);
}
.scratch-card-shop-price {
    font-family: var(--font-display);
    font-size: var(--text-xl);
    font-weight: var(--weight-bold);
    color: var(--primary);
    margin: var(--space-2) 0;
}
.scratch-card-shop-rewards {
    font-size: var(--text-xs);
    color: var(--text-secondary);
    margin-bottom: var(--space-3);
}
.scratch-play-area {
    max-width: 400px;
    margin: 0 auto var(--space-6);
    text-align: center;
}
.scratch-canvas-wrap {
    position: relative;
    width: 100%;
    max-width: 320px;
    margin: 0 auto;
    border-radius: var(--radius-lg);
    overflow: hidden;
    border: 3px solid var(--border-primary);
}
.scratch-canvas-wrap canvas {
    display: block;
    width: 100%;
    cursor: crosshair;
}
.scratch-prize {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    background: var(--card-bg);
    z-index: 0;
}
.scratch-prize-amount {
    font-family: var(--font-display);
    font-size: var(--text-4xl);
    font-weight: var(--weight-bold);
    color: var(--success);
}
.scratch-prize-label {
    font-size: var(--text-sm);
    color: var(--text-secondary);
    margin-top: var(--space-2);
}
.scratch-win {
    animation: winPulse 0.5s ease infinite alternate;
}
@keyframes winPulse {
    from { transform: scale(1); }
    to { transform: scale(1.05); }
}
.history-item {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    padding: var(--space-3);
    background: var(--bg-card-hover);
    border-radius: var(--radius-md);
    margin-bottom: var(--space-2);
}
.history-card-name { flex: 1; font-size: var(--text-sm); font-weight: var(--weight-medium); color: var(--text-primary); }
.history-result { font-family: var(--font-mono); font-size: var(--text-sm); font-weight: var(--weight-semibold); }
.history-result.win { color: var(--success); }
.history-result.lose { color: var(--danger); }
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
            <a href="/user/scratch.php" class="sidebar-nav-item active"><span class="sidebar-nav-icon"><i class="fas fa-hand-paper"></i></span><span class="sidebar-nav-text">Scratch</span></a>
            <a href="/user/spin.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-circle-notch"></i></span><span class="sidebar-nav-text">Spin</span></a>
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
                <h1>Scratch Cards</h1>
                <p>Buy cards and scratch to win prizes!</p>
            </div>
            <div class="balance-display">
                <span class="balance-display-amount" id="balanceDisplay"><?= formatCurrency($totalBalance) ?></span>
            </div>
        </div>

        <div id="shopView">
            <div class="dashboard-panel-header">
                <div class="dashboard-panel-title">Choose a Card</div>
            </div>
            <div class="scratch-shop">
                <?php foreach ($cards as $card): 
                    $rewards = json_decode($card['rewards'], true);
                    $maxReward = 0;
                    if ($rewards) foreach ($rewards as $r) { if (($r['amount'] ?? 0) > $maxReward) $maxReward = $r['amount']; }
                ?>
                <div class="scratch-card-shop" onclick="buyCard(<?= $card['id'] ?>)">
                    <div class="scratch-card-shop-icon"><i class="fas fa-gift"></i></div>
                    <div class="scratch-card-shop-name"><?= sanitize($card['name']) ?></div>
                    <div class="scratch-card-shop-price"><?= formatCurrency($card['price']) ?></div>
                    <div class="scratch-card-shop-rewards">Win up to <?= formatCurrency($maxReward) ?></div>
                    <button class="btn btn-sm btn-primary btn-block">Buy Now</button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="scratch-play-area" id="playView" style="display: none;">
            <h3 id="playCardName" style="margin-bottom: var(--space-4);">Scratch to Reveal!</h3>
            <div class="scratch-canvas-wrap">
                <div class="scratch-prize" id="scratchPrize">
                    <div class="scratch-prize-amount" id="prizeAmount"></div>
                    <div class="scratch-prize-label">Your Prize</div>
                </div>
                <canvas id="scratchCanvas" width="320" height="240"></canvas>
            </div>
            <button class="btn btn-outline-light mt-4" onclick="backToShop()">Back to Shop</button>
        </div>

        <div class="dashboard-panel" style="margin-top: var(--space-6);">
            <div class="dashboard-panel-header">
                <div class="dashboard-panel-title">Play History</div>
            </div>
            <?php if (count($history) > 0): ?>
            <?php foreach ($history as $h): ?>
            <div class="history-item">
                <div class="history-card-name"><?= sanitize($h['card_name']) ?></div>
                <span class="history-result <?= $h['won'] ? 'win' : 'lose' ?>"><?= $h['won'] ? '+' . formatCurrency($h['reward_amount']) : 'Lost' ?></span>
                <span class="history-date"><?= timeAgo($h['played_at']) ?></span>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div style="padding: var(--space-6); text-align: center; color: var(--text-tertiary);">
                <i class="fas fa-history" style="font-size: var(--text-3xl); opacity: 0.3; margin-bottom: var(--space-2); display: block;"></i>
                <p style="font-size: var(--text-sm);">No plays yet. Buy a card to start!</p>
            </div>
            <?php endif; ?>
        </div>
        <div class="bottom-nav-spacer"></div>
    </main>
</div>

<script>
let currentCardId = null;
let scratchRevealed = false;

function buyCard(cardId) {
    const formData = new FormData();
    formData.append('card_id', cardId);
    formData.append('csrf_token', '<?= csrf_token() ?>');

    fetch('/user/ajax/buy_scratch.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            currentCardId = cardId;
            document.getElementById('shopView').style.display = 'none';
            document.getElementById('playView').style.display = 'block';
            document.getElementById('playCardName').textContent = data.card_name || 'Scratch to Reveal!';
            document.getElementById('prizeAmount').textContent = data.formatted_reward || '<?= APP_CURRENCY_SYMBOL ?>0.00';
            document.getElementById('balanceDisplay').textContent = data.new_balance_formatted || document.getElementById('balanceDisplay').textContent;
            if (data.won) document.getElementById('scratchPrize').classList.add('scratch-win');
            setTimeout(() => initScratchCanvas(), 100);
        } else {
            alert(data.error || 'Failed to buy card');
        }
    });
}

function initScratchCanvas() {
    const canvas = document.getElementById('scratchCanvas');
    const ctx = canvas.getContext('2d');
    scratchRevealed = false;

    const dpr = window.devicePixelRatio || 1;
    canvas.width = 320 * dpr;
    canvas.height = 240 * dpr;
    ctx.scale(dpr, dpr);

    ctx.fillStyle = '#C0C0C0';
    ctx.fillRect(0, 0, 320, 240);
    ctx.fillStyle = '#999';
    ctx.font = 'bold 18px Inter, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('Scratch here!', 160, 125);

    let isDrawing = false;

    function getPos(e) {
        const rect = canvas.getBoundingClientRect();
        const touch = e.touches ? e.touches[0] : e;
        return { x: (touch.clientX - rect.left) * (320 / rect.width), y: (touch.clientY - rect.top) * (240 / rect.height) };
    }

    function scratch(pos) {
        if (scratchRevealed) return;
        ctx.globalCompositeOperation = 'destination-out';
        ctx.beginPath();
        ctx.arc(pos.x, pos.y, 18, 0, Math.PI * 2);
        ctx.fill();
        ctx.globalCompositeOperation = 'source-over';
        checkReveal();
    }

    function checkReveal() {
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        let transparent = 0;
        for (let i = 3; i < imageData.data.length; i += 4) {
            if (imageData.data[i] === 0) transparent++;
        }
        const percent = (transparent / (canvas.width * canvas.height)) * 100;
        if (percent > 45 && !scratchRevealed) {
            scratchRevealed = true;
            ctx.clearRect(0, 0, 320, 240);
            fireConfetti();
        }
    }

    canvas.onmousedown = (e) => { isDrawing = true; scratch(getPos(e)); };
    canvas.onmousemove = (e) => { if (isDrawing) scratch(getPos(e)); };
    canvas.onmouseup = () => { isDrawing = false; };
    canvas.onmouseleave = () => { isDrawing = false; };
    canvas.ontouchstart = (e) => { e.preventDefault(); isDrawing = true; scratch(getPos(e)); };
    canvas.ontouchmove = (e) => { e.preventDefault(); if (isDrawing) scratch(getPos(e)); };
    canvas.ontouchend = () => { isDrawing = false; };
}

function fireConfetti() {
    const colors = ['#6C5CE7', '#00CEC9', '#FD79A8', '#00B894', '#FDCB6E', '#74B9FF'];
    for (let i = 0; i < 60; i++) {
        const el = document.createElement('div');
        el.style.cssText = `
            position: fixed; width: 8px; height: 8px; background: ${colors[i % colors.length]};
            border-radius: 2px; top: -10px; left: ${Math.random() * 100}vw;
            z-index: 9999; pointer-events: none;
            animation: confettiFall ${1 + Math.random() * 2}s ease-out forwards;
            animation-delay: ${Math.random() * 0.5}s;
        `;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 3000);
    }
}

const styleSheet = document.createElement('style');
styleSheet.textContent = `
@keyframes confettiFall {
    0% { transform: translateY(0) rotate(0deg); opacity: 1; }
    100% { transform: translateY(100vh) rotate(720deg); opacity: 0; }
}`;
document.head.appendChild(styleSheet);

function backToShop() {
    document.getElementById('playView').style.display = 'none';
    document.getElementById('shopView').style.display = 'block';
    currentCardId = null;
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
