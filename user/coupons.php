<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAuth();
$user = getCurrentUser();
$pageTitle = 'Coupons';
$userId = $user['id'];
$db = getDB();

$stmt = $db->query("SELECT * FROM coupons WHERE status = 'active' AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY created_at DESC");
$availableCoupons = $stmt->fetchAll();

$stmt = $db->prepare("SELECT * FROM coupon_redemptions WHERE user_id = ? ORDER BY redeemed_at DESC LIMIT 20");
$stmt->execute([$userId]);
$redemptionHistory = $stmt->fetchAll();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<style>
.coupon-input-card { background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-lg); padding: var(--space-6); margin-bottom: var(--space-6); }
.coupon-input-card h2 { font-family: var(--font-display); font-size: var(--text-xl); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-2); }
.coupon-input-card p { font-size: var(--text-sm); color: var(--text-secondary); margin-bottom: var(--space-4); }
.coupon-input-group { display: flex; gap: var(--space-3); }
.coupon-input-group input { flex: 1; text-transform: uppercase; letter-spacing: 0.1em; font-family: var(--font-mono); }
.available-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-6); }
.available-card { background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-lg); padding: var(--space-5); border-left: 3px solid var(--primary); }
.available-code { font-family: var(--font-mono); font-size: var(--text-sm); font-weight: var(--weight-bold); color: var(--primary); margin-bottom: var(--space-1); }
.available-desc { font-size: var(--text-xs); color: var(--text-secondary); margin-bottom: var(--space-2); }
.available-reward { font-size: var(--text-sm); font-weight: var(--weight-semibold); color: var(--success); }
</style>

<div class="dashboard-wrapper">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-section">
            <div class="sidebar-section-title">Main Menu</div>
            <a href="/user/dashboard.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-home"></i></span><span class="sidebar-nav-text">Dashboard</span></a>
            <a href="/user/wallet.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-wallet"></i></span><span class="sidebar-nav-text">Wallet</span></a>
            <a href="/user/earnings.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-chart-line"></i></span><span class="sidebar-nav-text">Earnings</span></a>
            <a href="/user/coupons.php" class="sidebar-nav-item active"><span class="sidebar-nav-icon"><i class="fas fa-ticket-alt"></i></span><span class="sidebar-nav-text">Coupons</span></a>
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
                <h1>Coupons</h1>
                <p>Redeem coupon codes for bonus earnings</p>
            </div>
            <div class="dashboard-actions">
                <div class="balance-display" onclick="toggleBalanceVisibility()">
                    <span class="balance-display-icon"><i class="fas fa-eye" id="eyeIcon"></i></span>
                    <span class="balance-display-amount" id="headerBalance"><?= formatCurrency(getUserBalance($userId)) ?></span>
                </div>
            </div>
        </div>

        <div class="coupon-input-card">
            <h2><i class="fas fa-ticket-alt" style="color: var(--primary);"></i> Redeem Coupon</h2>
            <p>Enter a coupon code below to claim your reward</p>
            <form id="couponForm" onsubmit="redeemCoupon(event)">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <div class="coupon-input-group">
                    <input type="text" class="form-input" name="coupon_code" id="couponCode" placeholder="ENTER CODE" maxlength="50" required style="text-transform: uppercase;">
                    <button type="submit" class="btn btn-primary" id="couponBtn"><i class="fas fa-check"></i> Redeem</button>
                </div>
            </form>
            <div id="couponResult" style="margin-top: var(--space-3); display: none;"></div>
        </div>

        <?php if (count($availableCoupons) > 0): ?>
        <h3 style="font-family: var(--font-display); font-size: var(--text-lg); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-4);">
            <i class="fas fa-list"></i> Available Coupons
        </h3>
        <div class="available-grid">
            <?php foreach ($availableCoupons as $cp): ?>
            <div class="available-card">
                <div class="available-code"><?= sanitize($cp['code']) ?></div>
                <div class="available-desc"><?= sanitize($cp['description'] ?? 'No description') ?></div>
                <div class="available-reward">+<?= formatCurrency($cp['reward_amount']) ?></div>
                <?php if ($cp['max_uses'] > 0): ?>
                <div style="font-size: 10px; color: var(--text-tertiary); margin-top: var(--space-1);">Uses left: <?= max(0, $cp['max_uses'] - ($cp['current_uses'] ?? 0)) ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="dashboard-panel">
            <div class="dashboard-panel-header">
                <div class="dashboard-panel-title"><i class="fas fa-history"></i> Redemption History</div>
            </div>
            <div class="table-container">
                <table class="table table-sm">
                    <thead><tr><th>Code</th><th>Reward</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php if (count($redemptionHistory) > 0): ?>
                        <?php foreach ($redemptionHistory as $rh): ?>
                        <tr>
                            <td><span class="badge badge-primary badge-sm text-mono"><?= sanitize($rh['coupon_code']) ?></span></td>
                            <td><span style="color: var(--success); font-weight: var(--weight-semibold);">+<?= formatCurrency($rh['reward_amount']) ?></span></td>
                            <td><span class="text-mono" style="font-size: var(--text-xs); color: var(--text-tertiary);"><?= date('M d, H:i', strtotime($rh['redeemed_at'])) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr><td colspan="3"><div class="table-empty"><div class="table-empty-icon"><i class="fas fa-ticket-alt"></i></div><p>No coupons redeemed yet</p></div></td></tr>
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

function redeemCoupon(e) {
    e.preventDefault();
    const btn = document.getElementById('couponBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Redeeming...';
    const formData = new FormData(document.getElementById('couponForm'));

    fetch('/user/ajax/redeem_coupon.php', {
        method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData
    })
    .then(r => r.json())
    .then(data => {
        const result = document.getElementById('couponResult');
        result.style.display = 'block';
        if (data.success) {
            result.className = 'alert alert-success';
            result.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
            document.getElementById('couponCode').value = '';
            if (data.balance !== undefined) {
                document.getElementById('headerBalance').textContent = '<?= APP_CURRENCY_SYMBOL ?>' + data.balance.toFixed(2);
            }
        } else {
            result.className = 'alert alert-danger';
            result.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + (data.error || 'Invalid coupon');
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Redeem';
        setTimeout(() => { result.style.display = 'none'; }, 5000);
    })
    .catch(() => {
        const result = document.getElementById('couponResult');
        result.style.display = 'block';
        result.className = 'alert alert-danger';
        result.innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Redeem';
    });
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
