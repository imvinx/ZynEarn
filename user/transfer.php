<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAuth();
$user = getCurrentUser();
$pageTitle = 'Transfer';
$userId = $user['id'];
$db = getDB();

$stmt = $db->prepare("SELECT * FROM transfers WHERE sender_id = ? OR recipient_id = ? ORDER BY created_at DESC LIMIT 20");
$stmt->execute([$userId, $userId]);
$transferHistory = $stmt->fetchAll();

$transferFee = 0.02;
$transferFeePercent = 2;
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<style>
.transfer-card { background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-lg); padding: var(--space-6); margin-bottom: var(--space-6); max-width: 500px; }
.transfer-card h2 { font-family: var(--font-display); font-size: var(--text-xl); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-4); }
.fee-info { display: flex; align-items: center; gap: var(--space-2); padding: var(--space-3); background: rgba(253,203,110,0.1); border-radius: var(--radius-md); font-size: var(--text-sm); color: var(--text-secondary); margin-bottom: var(--space-4); }
.fee-info i { color: var(--warning); }
.transfer-note { font-size: var(--text-xs); color: var(--text-tertiary); margin-top: var(--space-2); }
</style>

<div class="dashboard-wrapper">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-section">
            <div class="sidebar-section-title">Main Menu</div>
            <a href="/user/dashboard.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-home"></i></span><span class="sidebar-nav-text">Dashboard</span></a>
            <a href="/user/wallet.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-wallet"></i></span><span class="sidebar-nav-text">Wallet</span></a>
            <a href="/user/earnings.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-chart-line"></i></span><span class="sidebar-nav-text">Earnings</span></a>
            <a href="/user/transfer.php" class="sidebar-nav-item active"><span class="sidebar-nav-icon"><i class="fas fa-exchange-alt"></i></span><span class="sidebar-nav-text">Transfer</span></a>
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
                <h1>Transfer</h1>
                <p>Send balance to other users</p>
            </div>
            <div class="dashboard-actions">
                <div class="balance-display" onclick="toggleBalanceVisibility()">
                    <span class="balance-display-icon"><i class="fas fa-eye" id="eyeIcon"></i></span>
                    <span class="balance-display-amount" id="headerBalance"><?= formatCurrency(getUserBalance($userId)) ?></span>
                </div>
            </div>
        </div>

        <div class="two-col-grid">
            <div class="transfer-card">
                <h2><i class="fas fa-paper-plane" style="color: var(--primary);"></i> Send Balance</h2>
                <form id="transferForm" onsubmit="submitTransfer(event)">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <div class="form-group">
                        <label class="form-label">Recipient</label>
                        <input type="text" class="form-input" name="recipient" id="recipientInput" required placeholder="Username or email address">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Amount (<?= APP_CURRENCY ?>)</label>
                        <input type="number" class="form-input" name="amount" id="transferAmount" min="0.01" step="0.01" required placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Note (optional)</label>
                        <input type="text" class="form-input" name="note" id="transferNote" maxlength="200" placeholder="What's this for?">
                    </div>
                    <div class="fee-info">
                        <i class="fas fa-info-circle"></i>
                        <span>A fee of <strong><?= $transferFeePercent ?>% (<?= APP_CURRENCY_SYMBOL ?><span id="feeDisplay">0.00</span>)</strong> applies to this transfer</span>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block" id="transferBtn">
                        <i class="fas fa-paper-plane"></i> Send Transfer
                    </button>
                </form>
                <div id="transferResult" style="margin-top: var(--space-3); display: none;"></div>
            </div>

            <div>
                <div class="dashboard-panel">
                    <div class="dashboard-panel-header">
                        <div class="dashboard-panel-title"><i class="fas fa-history"></i> Transfer History</div>
                    </div>
                    <div class="table-container">
                        <table class="table table-sm">
                            <thead><tr><th>From/To</th><th>Amount</th><th>Note</th><th>Date</th></tr></thead>
                            <tbody>
                                <?php if (count($transferHistory) > 0): ?>
                                <?php foreach ($transferHistory as $t): ?>
                                <tr>
                                    <td>
                                        <?php if ($t['sender_id'] == $userId): ?>
                                        <span style="color: var(--danger); font-size: var(--text-sm);">To #<?= $t['recipient_id'] ?></span>
                                        <?php else: ?>
                                        <span style="color: var(--success); font-size: var(--text-sm);">From #<?= $t['sender_id'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($t['sender_id'] == $userId): ?>
                                        <span style="color: var(--danger); font-weight: var(--weight-semibold);">-<?= formatCurrency($t['amount']) ?></span>
                                        <?php else: ?>
                                        <span style="color: var(--success); font-weight: var(--weight-semibold);">+<?= formatCurrency($t['amount']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span style="font-size: var(--text-xs); color: var(--text-tertiary);"><?= sanitize(mb_substr($t['note'] ?? '-', 0, 30)) ?></span></td>
                                    <td><span class="text-mono" style="font-size: var(--text-xs); color: var(--text-tertiary);"><?= date('M d, H:i', strtotime($t['created_at'])) ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <tr><td colspan="4"><div class="table-empty"><div class="table-empty-icon"><i class="fas fa-exchange-alt"></i></div><p>No transfers yet</p></div></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
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

const transferFeePercent = <?= $transferFeePercent ?>;
document.getElementById('transferAmount')?.addEventListener('input', function() {
    const amount = parseFloat(this.value) || 0;
    const fee = amount * (transferFeePercent / 100);
    document.getElementById('feeDisplay').textContent = fee.toFixed(2);
});

function submitTransfer(e) {
    e.preventDefault();
    const btn = document.getElementById('transferBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    const formData = new FormData(document.getElementById('transferForm'));

    fetch('/user/ajax/submit_transfer.php', {
        method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData
    })
    .then(r => r.json())
    .then(data => {
        const result = document.getElementById('transferResult');
        result.style.display = 'block';
        if (data.success) {
            result.className = 'alert alert-success';
            result.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
            document.getElementById('transferForm').reset();
            document.getElementById('feeDisplay').textContent = '0.00';
            if (data.balance !== undefined) {
                document.getElementById('headerBalance').textContent = '<?= APP_CURRENCY_SYMBOL ?>' + data.balance.toFixed(2);
            }
            setTimeout(() => location.reload(), 1500);
        } else {
            result.className = 'alert alert-danger';
            result.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + (data.error || 'Transfer failed');
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Transfer';
        setTimeout(() => { if (!data.success) result.style.display = 'none'; }, 5000);
    })
    .catch(() => {
        const result = document.getElementById('transferResult');
        result.style.display = 'block';
        result.className = 'alert alert-danger';
        result.innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Transfer';
    });
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
