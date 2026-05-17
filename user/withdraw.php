<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAuth();
$user = getCurrentUser();
$pageTitle = 'Withdraw';
$userId = $user['id'];
$db = getDB();

$totalBalance = getUserBalance($userId);
$pendingBalance = 0;
$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as pending FROM earnings WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$userId]);
$pendingBalance = $stmt->fetch()['pending'];

$stmt = $db->prepare("SELECT * FROM user_wallets WHERE user_id = ? ORDER BY is_default DESC, created_at ASC");
$stmt->execute([$userId]);
$wallets = $stmt->fetchAll();

$stmt = $db->prepare("SELECT * FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$userId]);
$withdrawals = $stmt->fetchAll();

$feePercent = WITHDRAWAL_FEE_PERCENTAGE;
$feeFixed = WITHDRAWAL_FEE_FIXED;
$minWithdrawal = MIN_WITHDRAWAL;
$maxWithdrawal = MAX_WITHDRAWAL;

$walletTypes = [
    'upi' => ['label' => 'UPI', 'icon' => 'fa-mobile-alt'],
    'paytm' => ['label' => 'Paytm', 'icon' => 'fa-paypal'],
    'paypal' => ['label' => 'PayPal', 'icon' => 'fa-paypal'],
    'crypto' => ['label' => 'Cryptocurrency', 'icon' => 'fa-bitcoin'],
    'binance' => ['label' => 'Binance', 'icon' => 'fa-coins'],
    'faucetpay' => ['label' => 'FaucetPay', 'icon' => 'fa-water'],
];

$quickAmounts = [5, 10, 25, 50, 100];
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<style>
.withdraw-grid { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-6); }
.balance-card-large { background: linear-gradient(135deg, #6C5CE7 0%, #A29BFE 100%); border-radius: var(--radius-lg); padding: var(--space-8); color: white; position: relative; overflow: hidden; }
.balance-card-large::after { content: ''; position: absolute; top: -50%; right: -30%; width: 200px; height: 200px; border-radius: var(--radius-full); background: rgba(255,255,255,0.1); pointer-events: none; }
.balance-large-label { font-size: var(--text-sm); opacity: 0.9; margin-bottom: var(--space-2); }
.balance-large-amount { font-family: var(--font-display); font-size: var(--text-4xl); font-weight: var(--weight-bold); position: relative; z-index: 1; }
.balance-large-sub { font-size: var(--text-xs); opacity: 0.7; margin-top: var(--space-2); position: relative; z-index: 1; }
.withdraw-form-card { background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-lg); padding: var(--space-6); }
.fee-breakdown { background: var(--bg-card-hover); border-radius: var(--radius-md); padding: var(--space-4); margin: var(--space-4) 0; }
.fee-row { display: flex; justify-content: space-between; align-items: center; padding: var(--space-2) 0; font-size: var(--text-sm); }
.fee-row.total { border-top: 1px solid var(--border-primary); margin-top: var(--space-2); padding-top: var(--space-3); font-weight: var(--weight-bold); color: var(--text-primary); }
.fee-label { color: var(--text-secondary); }
.fee-value { color: var(--text-primary); font-family: var(--font-mono); }
.quick-amounts { display: grid; grid-template-columns: repeat(5, 1fr); gap: var(--space-2); margin: var(--space-4) 0; }
.quick-amount-btn { padding: var(--space-2); background: var(--bg-card-hover); border: 1px solid var(--border-primary); border-radius: var(--radius-md); color: var(--text-primary); font-family: var(--font-display); font-weight: var(--weight-semibold); font-size: var(--text-sm); cursor: pointer; transition: var(--transition); }
.quick-amount-btn:hover { border-color: var(--primary); background: rgba(108,92,231,0.1); }
.quick-amount-btn.active { border-color: var(--primary); background: rgba(108,92,231,0.15); color: var(--primary); }
.limit-info { display: flex; justify-content: space-between; font-size: var(--text-xs); color: var(--text-tertiary); margin-top: var(--space-2); }
.processing-time { display: inline-flex; align-items: center; gap: var(--space-2); padding: var(--space-2) var(--space-4); background: rgba(0,184,148,0.1); border-radius: var(--radius-full); font-size: var(--text-xs); color: var(--success); font-weight: var(--weight-semibold); }
.status-timeline { display: flex; flex-direction: column; gap: var(--space-3); padding: var(--space-4); }
.status-step { display: flex; align-items: flex-start; gap: var(--space-3); position: relative; }
.status-step:not(:last-child)::after { content: ''; position: absolute; left: 11px; top: 28px; bottom: -16px; width: 2px; background: var(--border-primary); }
.status-step.completed::after { background: var(--success); }
.status-step.active::after { background: var(--primary); }
.status-dot { width: 24px; height: 24px; border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; font-size: 10px; flex-shrink: 0; margin-top: 2px; }
.status-dot.pending { background: rgba(253,203,110,0.15); color: var(--warning); border: 2px solid var(--warning); }
.status-dot.completed { background: rgba(0,184,148,0.15); color: var(--success); border: 2px solid var(--success); }
.status-dot.active { background: rgba(108,92,231,0.15); color: var(--primary); border: 2px solid var(--primary); }
.status-dot.cancelled { background: rgba(225,112,85,0.15); color: var(--danger); border: 2px solid var(--danger); }
.status-info { flex: 1; }
.status-label { font-size: var(--text-sm); font-weight: var(--weight-medium); color: var(--text-primary); }
.status-date { font-size: var(--text-xs); color: var(--text-tertiary); }
@media (max-width: 992px) {
    .withdraw-grid { grid-template-columns: 1fr; }
    .quick-amounts { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 480px) {
    .quick-amounts { grid-template-columns: repeat(2, 1fr); }
}
</style>

<div class="dashboard-wrapper">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-section">
            <div class="sidebar-section-title">Main Menu</div>
            <a href="/user/dashboard.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-home"></i></span><span class="sidebar-nav-text">Dashboard</span></a>
            <a href="/user/wallet.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-wallet"></i></span><span class="sidebar-nav-text">Wallet</span></a>
            <a href="/user/earnings.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-chart-line"></i></span><span class="sidebar-nav-text">Earnings</span></a>
            <a href="/user/offers.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-th"></i></span><span class="sidebar-nav-text">Offers</span></a>
            <a href="/user/tasks.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-check-circle"></i></span><span class="sidebar-nav-text">Tasks</span></a>
            <a href="/user/referrals.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-users"></i></span><span class="sidebar-nav-text">Referrals</span></a>
            <a href="/user/withdraw.php" class="sidebar-nav-item active"><span class="sidebar-nav-icon"><i class="fas fa-cash-register"></i></span><span class="sidebar-nav-text">Withdraw</span></a>
            <a href="/user/deposit.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-plus-circle"></i></span><span class="sidebar-nav-text">Deposit</span></a>
        </div>
        <div class="sidebar-section">
            <div class="sidebar-section-title">Account</div>
            <a href="/user/profile.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-user"></i></span><span class="sidebar-nav-text">Profile</span></a>
            <a href="/user/settings.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-cog"></i></span><span class="sidebar-nav-text">Settings</span></a>
            <a href="/user/notifications.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-bell"></i></span><span class="sidebar-nav-text">Notifications</span></a>
            <a href="/user/leaderboard.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-trophy"></i></span><span class="sidebar-nav-text">Leaderboard</span></a>
            <a href="/user/support.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-headset"></i></span><span class="sidebar-nav-text">Support</span></a>
            <a href="/auth/logout.php" class="sidebar-nav-item logout-item"><span class="sidebar-nav-icon"><i class="fas fa-sign-out-alt"></i></span><span class="sidebar-nav-text">Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <div class="dashboard-top-header">
            <div class="dashboard-greeting">
                <h1>Withdraw Funds</h1>
                <p>Request a withdrawal to your preferred payment method</p>
            </div>
            <div class="dashboard-actions">
                <div class="balance-display" onclick="toggleBalanceVisibility()">
                    <span class="balance-display-icon"><i class="fas fa-eye" id="eyeIcon"></i></span>
                    <span class="balance-display-amount" id="headerBalance"><?= formatCurrency($totalBalance) ?></span>
                </div>
            </div>
        </div>

        <div class="withdraw-grid">
            <div>
                <div class="balance-card-large">
                    <div class="balance-large-label">Available Balance</div>
                    <div class="balance-large-amount"><?= formatCurrency($totalBalance) ?></div>
                    <div class="balance-large-sub">Includes pending earnings: <?= formatCurrency($pendingBalance) ?></div>
                </div>

                <div class="withdraw-form-card" style="margin-top: var(--space-6);">
                    <h3 style="font-family: var(--font-display); font-size: var(--text-lg); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-4);">Withdrawal Request</h3>
                    <form id="withdrawForm" onsubmit="submitWithdraw(event)">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        
                        <div class="form-group">
                            <label class="form-label">Payment Method</label>
                            <select class="form-select" name="payment_method" id="paymentMethod" required onchange="updateWallets()">
                                <option value="">Select payment method</option>
                                <?php foreach ($walletTypes as $key => $wt): ?>
                                <option value="<?= $key ?>"><?= $wt['label'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Saved Wallet</label>
                            <select class="form-select" name="wallet_id" id="walletSelect" required>
                                <option value="">Select wallet</option>
                                <?php foreach ($wallets as $w): ?>
                                <option value="<?= $w['id'] ?>" data-type="<?= $w['wallet_type'] ?>" <?= $w['is_default'] ? 'selected' : '' ?>>
                                    <?= sanitize($walletTypes[$w['wallet_type']]['label'] ?? ucfirst($w['wallet_type'])) ?> - <?= sanitize(substr($w['wallet_address'], 0, 20)) ?>...
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div style="margin-top: var(--space-1);"><a href="/user/wallet.php" style="font-size: var(--text-xs); color: var(--primary);">Add new wallet</a></div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Amount (<?= APP_CURRENCY ?>)</label>
                            <input type="number" class="form-input" name="amount" id="withdrawAmount" min="<?= $minWithdrawal ?>" max="<?= $maxWithdrawal ?>" step="0.01" required placeholder="Enter amount" oninput="calculateFee()">
                        </div>

                        <div class="quick-amounts">
                            <?php foreach ($quickAmounts as $qa): ?>
                            <button type="button" class="quick-amount-btn" onclick="setQuickAmount(<?= $qa ?>)">$<?= $qa ?></button>
                            <?php endforeach; ?>
                        </div>

                        <div class="limit-info">
                            <span>Min: <?= formatCurrency($minWithdrawal) ?></span>
                            <span>Max: <?= formatCurrency($maxWithdrawal) ?></span>
                        </div>

                        <div class="fee-breakdown" id="feeBreakdown" style="display: none;">
                            <div class="fee-row">
                                <span class="fee-label">Withdrawal Amount</span>
                                <span class="fee-value" id="feeAmount">$0.00</span>
                            </div>
                            <div class="fee-row">
                                <span class="fee-label">Fee (<?= $feePercent ?>% + <?= formatCurrency($feeFixed) ?>)</span>
                                <span class="fee-value" id="feeValue">$0.00</span>
                            </div>
                            <div class="fee-row">
                                <span class="fee-label" style="color: var(--success);">You Receive</span>
                                <span class="fee-value" id="youReceive" style="color: var(--success); font-weight: var(--weight-bold);">$0.00</span>
                            </div>
                        </div>

                        <div class="processing-time" style="margin-bottom: var(--space-4);">
                            <i class="fas fa-clock"></i> Estimated processing: 24-48 hours
                        </div>

                        <button type="submit" class="btn btn-primary btn-block btn-lg" id="withdrawBtn">
                            <i class="fas fa-paper-plane"></i> Request Withdrawal
                        </button>
                    </form>
                    <div id="withdrawResult" style="margin-top: var(--space-3); display: none;"></div>
                </div>
            </div>

            <div>
                <div class="dashboard-panel">
                    <div class="dashboard-panel-header">
                        <div class="dashboard-panel-title"><i class="fas fa-history"></i> Recent Withdrawals</div>
                        <a href="/user/earnings.php?filter=withdrawals" class="dashboard-panel-action">View All</a>
                    </div>
                    <div class="table-container">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Method</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($withdrawals) > 0): ?>
                                <?php foreach ($withdrawals as $w): ?>
                                <tr>
                                    <td>
                                        <span class="badge badge-primary badge-sm">
                                            <i class="fas <?= $walletTypes[$w['payment_method']]['icon'] ?? 'fa-wallet' ?>"></i>
                                            <?= sanitize($walletTypes[$w['payment_method']]['label'] ?? ucfirst($w['payment_method'])) ?>
                                        </span>
                                    </td>
                                    <td><span class="text-mono" style="font-weight: var(--weight-semibold); color: var(--text-primary);"><?= formatCurrency($w['amount']) ?></span></td>
                                    <td>
                                        <span class="badge badge-<?= $w['status'] === 'completed' ? 'success' : ($w['status'] === 'pending' ? 'warning' : ($w['status'] === 'processing' ? 'info' : 'danger')) ?> badge-sm">
                                            <?= ucfirst($w['status']) ?>
                                        </span>
                                    </td>
                                    <td><span class="text-mono" style="font-size: var(--text-xs); color: var(--text-tertiary);"><?= date('M d, H:i', strtotime($w['created_at'])) ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <tr><td colspan="4"><div class="table-empty"><div class="table-empty-icon"><i class="fas fa-inbox"></i></div><p>No withdrawal requests yet.</p></div></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="dashboard-panel" style="margin-top: var(--space-6);">
                    <div class="dashboard-panel-header">
                        <div class="dashboard-panel-title"><i class="fas fa-clock"></i> Status Tracking</div>
                    </div>
                    <div id="statusTimeline" class="status-timeline">
                        <div class="status-step completed">
                            <div class="status-dot completed"><i class="fas fa-check" style="font-size: 10px;"></i></div>
                            <div class="status-info">
                                <div class="status-label">Request Submitted</div>
                                <div class="status-date">Your withdrawal request is received</div>
                            </div>
                        </div>
                        <div class="status-step active">
                            <div class="status-dot active"><i class="fas fa-hourglass-half" style="font-size: 10px;"></i></div>
                            <div class="status-info">
                                <div class="status-label">Under Review</div>
                                <div class="status-date">Admin is reviewing your request</div>
                            </div>
                        </div>
                        <div class="status-step">
                            <div class="status-dot pending"><i class="fas fa-clock" style="font-size: 10px;"></i></div>
                            <div class="status-info">
                                <div class="status-label">Processing Payment</div>
                                <div class="status-date">Payment is being processed</div>
                            </div>
                        </div>
                        <div class="status-step">
                            <div class="status-dot pending"><i class="fas fa-check-circle" style="font-size: 10px;"></i></div>
                            <div class="status-info">
                                <div class="status-label">Completed</div>
                                <div class="status-date">Funds sent to your wallet</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="bottom-nav-spacer"></div>
    </main>
</div>

<div class="modal-backdrop" id="successModalBackdrop"></div>
<div class="modal modal-sm" id="successModal">
    <div class="modal-header">
        <div class="modal-title">Withdrawal Submitted</div>
        <button class="modal-close" onclick="closeSuccessModal()"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body" style="text-align: center; padding: var(--space-8);">
        <div style="font-size: var(--text-5xl); color: var(--success); margin-bottom: var(--space-4);"><i class="fas fa-check-circle"></i></div>
        <h3 style="font-family: var(--font-display); color: var(--text-primary); margin-bottom: var(--space-2);">Withdrawal Requested!</h3>
        <p style="color: var(--text-secondary); font-size: var(--text-sm); margin-bottom: var(--space-4);">Your withdrawal of <strong id="successAmount" style="color: var(--success);">$0.00</strong> has been submitted for processing.</p>
        <div class="processing-time" style="margin-bottom: var(--space-4);"><i class="fas fa-clock"></i> Estimated processing time: 24-48 hours</div>
        <button class="btn btn-primary" onclick="closeSuccessModal()">Done</button>
    </div>
</div>

<script>
let balanceVisible = true;
function toggleBalanceVisibility() {
    balanceVisible = !balanceVisible;
    const el = document.getElementById('headerBalance');
    const icon = document.getElementById('eyeIcon');
    if (balanceVisible) {
        el.textContent = '<?= formatCurrency($totalBalance) ?>';
        icon.className = 'fas fa-eye';
    } else {
        el.textContent = '••••••';
        icon.className = 'fas fa-eye-slash';
    }
}

function updateWallets() {
    const method = document.getElementById('paymentMethod').value;
    const select = document.getElementById('walletSelect');
    Array.from(select.options).forEach(opt => {
        if (opt.value === '') return;
        opt.style.display = opt.dataset.type === method ? '' : 'none';
    });
    if (select.value && Array.from(select.options).find(o => o.value === select.value).style.display === 'none') {
        select.value = '';
    }
}

document.getElementById('paymentMethod').addEventListener('change', updateWallets);

function setQuickAmount(amount) {
    document.getElementById('withdrawAmount').value = amount;
    document.querySelectorAll('.quick-amount-btn').forEach(b => b.classList.remove('active'));
    event.target.classList.add('active');
    calculateFee();
}

function calculateFee() {
    const amount = parseFloat(document.getElementById('withdrawAmount').value) || 0;
    const breakdown = document.getElementById('feeBreakdown');
    if (amount <= 0) { breakdown.style.display = 'none'; return; }
    breakdown.style.display = 'block';
    const fee = (amount * <?= $feePercent ?> / 100) + <?= $feeFixed ?>;
    const receive = Math.max(0, amount - fee);
    document.getElementById('feeAmount').textContent = '<?= APP_CURRENCY_SYMBOL ?>' + amount.toFixed(2);
    document.getElementById('feeValue').textContent = '<?= APP_CURRENCY_SYMBOL ?>' + fee.toFixed(2);
    document.getElementById('youReceive').textContent = '<?= APP_CURRENCY_SYMBOL ?>' + receive.toFixed(2);
}

function submitWithdraw(e) {
    e.preventDefault();
    const btn = document.getElementById('withdrawBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    const result = document.getElementById('withdrawResult');
    result.style.display = 'none';
    const formData = new FormData(document.getElementById('withdrawForm'));
    fetch('/user/ajax/submit_withdraw.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('successAmount').textContent = '<?= APP_CURRENCY_SYMBOL ?>' + (parseFloat(document.getElementById('withdrawAmount').value) || 0).toFixed(2);
            document.getElementById('successModal').classList.add('active');
            document.getElementById('successModalBackdrop').classList.add('active');
            document.getElementById('withdrawForm').reset();
            document.getElementById('feeBreakdown').style.display = 'none';
            document.querySelectorAll('.quick-amount-btn').forEach(b => b.classList.remove('active'));
            location.reload();
        } else {
            result.style.display = 'block';
            result.className = 'alert alert-danger';
            result.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + (data.error || 'Withdrawal failed');
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Request Withdrawal';
    })
    .catch(() => {
        result.style.display = 'block';
        result.className = 'alert alert-danger';
        result.innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error. Please try again.';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Request Withdrawal';
    });
}

function closeSuccessModal() {
    document.getElementById('successModal').classList.remove('active');
    document.getElementById('successModalBackdrop').classList.remove('active');
}
document.getElementById('successModalBackdrop').addEventListener('click', closeSuccessModal);
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
