<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAuth();
$user = getCurrentUser();
$pageTitle = 'Reward Shop';
$userId = $user['id'];
$db = getDB();

$stmt = $db->query("SELECT * FROM rewards WHERE status = 'active' ORDER BY price ASC");
$rewards = $stmt->fetchAll();

$stmt = $db->prepare("SELECT rh.*, r.name as reward_name, r.image as reward_image FROM reward_redemptions rh JOIN rewards r ON rh.reward_id = r.id WHERE rh.user_id = ? ORDER BY rh.created_at DESC LIMIT 20");
$stmt->execute([$userId]);
$redemptionHistory = $stmt->fetchAll();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<style>
.rewards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: var(--space-5); margin-bottom: var(--space-6); }
.reward-card { background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-lg); overflow: hidden; transition: var(--transition); }
.reward-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); border-color: var(--border-light); }
.reward-card-image { width: 100%; height: 160px; background: var(--gradient-midnight); display: flex; align-items: center; justify-content: center; font-size: var(--text-4xl); color: rgba(255,255,255,0.2); }
.reward-card-body { padding: var(--space-4); }
.reward-card-name { font-size: var(--text-base); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-1); }
.reward-card-desc { font-size: var(--text-xs); color: var(--text-secondary); margin-bottom: var(--space-3); min-height: 32px; }
.reward-card-footer { display: flex; align-items: center; justify-content: space-between; padding-top: var(--space-3); border-top: 1px solid var(--border-primary); }
.reward-card-price { font-family: var(--font-display); font-size: var(--text-lg); font-weight: var(--weight-bold); color: var(--success); }
.reward-type-badge { font-size: 10px; padding: 2px 8px; border-radius: var(--radius-full); font-weight: var(--weight-semibold); text-transform: uppercase; }
.reward-type-giftcard { background: rgba(108,92,231,0.15); color: var(--primary); }
.reward-type-digital { background: rgba(0,206,201,0.15); color: var(--secondary); }
.reward-type-physical { background: rgba(253,121,168,0.15); color: var(--accent); }
</style>

<div class="dashboard-wrapper">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-section">
            <div class="sidebar-section-title">Main Menu</div>
            <a href="/user/dashboard.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-home"></i></span><span class="sidebar-nav-text">Dashboard</span></a>
            <a href="/user/wallet.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-wallet"></i></span><span class="sidebar-nav-text">Wallet</span></a>
            <a href="/user/earnings.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-chart-line"></i></span><span class="sidebar-nav-text">Earnings</span></a>
            <a href="/user/rewards.php" class="sidebar-nav-item active"><span class="sidebar-nav-icon"><i class="fas fa-gift"></i></span><span class="sidebar-nav-text">Rewards</span></a>
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
                <h1>Reward Shop</h1>
                <p>Redeem your earnings for awesome rewards</p>
            </div>
            <div class="dashboard-actions">
                <div class="balance-display" onclick="toggleBalanceVisibility()">
                    <span class="balance-display-icon"><i class="fas fa-eye" id="eyeIcon"></i></span>
                    <span class="balance-display-amount" id="headerBalance"><?= formatCurrency(getUserBalance($userId)) ?></span>
                </div>
            </div>
        </div>

        <?php if (count($rewards) > 0): ?>
        <div class="rewards-grid">
            <?php foreach ($rewards as $reward): ?>
            <div class="reward-card">
                <div class="reward-card-image">
                    <i class="fas fa-<?= $reward['type'] === 'giftcard' ? 'gift' : ($reward['type'] === 'digital' ? 'download' : 'box') ?>"></i>
                </div>
                <div class="reward-card-body">
                    <div class="reward-card-name"><?= sanitize($reward['name']) ?></div>
                    <div class="reward-card-desc"><?= sanitize(mb_substr($reward['description'] ?? '', 0, 80)) ?></div>
                    <div style="margin-bottom: var(--space-2);">
                        <span class="reward-type-badge reward-type-<?= sanitize($reward['type']) ?>"><?= ucfirst(sanitize($reward['type'])) ?></span>
                    </div>
                    <div class="reward-card-footer">
                        <span class="reward-card-price"><?= formatCurrency($reward['price']) ?></span>
                        <button class="btn btn-sm btn-success" onclick="redeemReward(<?= $reward['id'] ?>, '<?= sanitize($reward['name']) ?>', <?= $reward['price'] ?>)" <?= getUserBalance($userId) < $reward['price'] ? 'disabled' : '' ?>>
                            <i class="fas fa-shopping-cart"></i> Redeem
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state" style="text-align: center; padding: var(--space-10); color: var(--text-tertiary);">
            <i class="fas fa-gift" style="font-size: var(--text-5xl); opacity: 0.3; margin-bottom: var(--space-4); display: block;"></i>
            <p style="font-size: var(--text-sm);">No rewards available yet</p>
        </div>
        <?php endif; ?>

        <div class="dashboard-panel">
            <div class="dashboard-panel-header">
                <div class="dashboard-panel-title"><i class="fas fa-history"></i> Redemption History</div>
            </div>
            <div class="table-container">
                <table class="table table-sm">
                    <thead><tr><th>Reward</th><th>Price</th><th>Status</th><th>Date</th><th>Details</th></tr></thead>
                    <tbody>
                        <?php if (count($redemptionHistory) > 0): ?>
                        <?php foreach ($redemptionHistory as $rh): ?>
                        <tr>
                            <td><span style="font-size: var(--text-sm); font-weight: var(--weight-medium); color: var(--text-primary);"><?= sanitize($rh['reward_name']) ?></span></td>
                            <td><span class="text-mono" style="color: var(--success); font-weight: var(--weight-semibold);"><?= formatCurrency($rh['price_paid']) ?></span></td>
                            <td><span class="badge badge-<?= $rh['status'] === 'completed' ? 'success' : ($rh['status'] === 'pending' ? 'warning' : 'danger') ?> badge-sm"><?= ucfirst($rh['status']) ?></span></td>
                            <td><span class="text-mono" style="font-size: var(--text-xs); color: var(--text-tertiary);"><?= date('M d, H:i', strtotime($rh['created_at'])) ?></span></td>
                            <td><span style="font-size: var(--text-xs); color: var(--text-tertiary);"><?= sanitize($rh['details'] ?? '-') ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr><td colspan="5"><div class="table-empty"><div class="table-empty-icon"><i class="fas fa-inbox"></i></div><p>No redemptions yet</p></div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="bottom-nav-spacer"></div>
    </main>
</div>

<div class="modal-backdrop" id="redeemModalBackdrop"></div>
<div class="modal modal-sm" id="redeemModal">
    <div class="modal-header">
        <div class="modal-title">Confirm Redemption</div>
        <button class="modal-close" onclick="closeRedeemModal()"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
        <form id="redeemForm" onsubmit="submitRedeem(event)">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="reward_id" id="redeemRewardId" value="">
            <p style="font-size: var(--text-sm); color: var(--text-secondary); margin-bottom: var(--space-4);">
                Redeem <strong id="redeemRewardName" style="color: var(--text-primary);"></strong> for <strong id="redeemRewardPrice" style="color: var(--success);"></strong>?
            </p>
            <div class="form-group">
                <label class="form-label">Details (email, address, etc.)</label>
                <textarea class="form-input" name="details" id="redeemDetails" rows="3" placeholder="Enter any required information for this reward"></textarea>
            </div>
            <button type="submit" class="btn btn-success btn-block" id="redeemSubmitBtn">
                <i class="fas fa-check"></i> Confirm Redemption
            </button>
        </form>
        <div id="redeemResult" style="margin-top: var(--space-3); display: none;"></div>
    </div>
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

function redeemReward(id, name, price) {
    document.getElementById('redeemRewardId').value = id;
    document.getElementById('redeemRewardName').textContent = name;
    document.getElementById('redeemRewardPrice').textContent = '<?= APP_CURRENCY_SYMBOL ?>' + price.toFixed(2);
    document.getElementById('redeemResult').style.display = 'none';
    document.getElementById('redeemModal').classList.add('active');
    document.getElementById('redeemModalBackdrop').classList.add('active');
}

function closeRedeemModal() {
    document.getElementById('redeemModal').classList.remove('active');
    document.getElementById('redeemModalBackdrop').classList.remove('active');
}

function submitRedeem(e) {
    e.preventDefault();
    const btn = document.getElementById('redeemSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    const formData = new FormData(document.getElementById('redeemForm'));

    fetch('/user/ajax/redeem_reward.php', {
        method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData
    })
    .then(r => r.json())
    .then(data => {
        const result = document.getElementById('redeemResult');
        result.style.display = 'block';
        if (data.success) {
            result.className = 'alert alert-success';
            result.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
            setTimeout(() => location.reload(), 1500);
        } else {
            result.className = 'alert alert-danger';
            result.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + (data.error || 'Redemption failed');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> Confirm Redemption';
        }
    })
    .catch(() => {
        const result = document.getElementById('redeemResult');
        result.style.display = 'block';
        result.className = 'alert alert-danger';
        result.innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Confirm Redemption';
    });
}

document.getElementById('redeemModalBackdrop').addEventListener('click', closeRedeemModal);
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
