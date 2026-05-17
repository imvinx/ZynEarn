<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAuth();
$user = getCurrentUser();
$pageTitle = 'Deposit';
$userId = $user['id'];
$db = getDB();

$stmt = $db->prepare("SELECT * FROM deposits WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$userId]);
$deposits = $stmt->fetchAll();

$bonusPercent = DEPOSIT_BONUS_PERCENTAGE;
$maxBonus = MAX_DEPOSIT_BONUS;

$gateways = [
    ['id' => 'paypal', 'name' => 'PayPal', 'icon' => 'fa-paypal', 'color' => 'info', 'desc' => 'Fast and secure payments worldwide'],
    ['id' => 'stripe', 'name' => 'Stripe', 'icon' => 'fa-credit-card', 'color' => 'primary', 'desc' => 'Credit/debit card payments'],
    ['id' => 'razorpay', 'name' => 'Razorpay', 'icon' => 'fa-rupee-sign', 'color' => 'info', 'desc' => 'Indian payment gateway (UPI/NetBanking/Cards)'],
    ['id' => 'crypto', 'name' => 'Cryptocurrency', 'icon' => 'fa-bitcoin', 'color' => 'warning', 'desc' => 'BTC, ETH, USDT, and more'],
    ['id' => 'binance', 'name' => 'Binance Pay', 'icon' => 'fa-coins', 'color' => 'accent', 'desc' => 'Pay with Binance account'],
];

$quickAmounts = [5, 10, 25, 50, 100, 250];
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<style>
.deposit-grid { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-6); }
.bonus-banner { background: linear-gradient(135deg, #00B894 0%, #55EFC4 100%); border-radius: var(--radius-lg); padding: var(--space-6); color: white; display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-6); }
.bonus-banner-text h3 { font-family: var(--font-display); font-size: var(--text-xl); font-weight: var(--weight-bold); }
.bonus-banner-text p { font-size: var(--text-sm); opacity: 0.9; margin-top: 2px; }
.bonus-banner-amount { font-family: var(--font-display); font-size: var(--text-3xl); font-weight: var(--weight-bold); }
.gateway-list { display: flex; flex-direction: column; gap: var(--space-3); }
.gateway-option { display: flex; align-items: center; gap: var(--space-4); padding: var(--space-4); background: var(--card-bg); border: 2px solid var(--border-primary); border-radius: var(--radius-lg); cursor: pointer; transition: var(--transition); }
.gateway-option:hover { border-color: var(--border-light); }
.gateway-option.selected { border-color: var(--primary); background: rgba(108,92,231,0.05); }
.gateway-option-icon { width: 48px; height: 48px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: var(--text-xl); flex-shrink: 0; }
.gateway-option-info { flex: 1; }
.gateway-option-name { font-size: var(--text-sm); font-weight: var(--weight-semibold); color: var(--text-primary); }
.gateway-option-desc { font-size: var(--text-xs); color: var(--text-secondary); margin-top: 2px; }
.quick-amounts { display: grid; grid-template-columns: repeat(6, 1fr); gap: var(--space-2); margin: var(--space-4) 0; }
.quick-amount-btn { padding: var(--space-2) var(--space-1); background: var(--bg-card-hover); border: 1px solid var(--border-primary); border-radius: var(--radius-md); color: var(--text-primary); font-family: var(--font-display); font-weight: var(--weight-semibold); font-size: var(--text-sm); cursor: pointer; transition: var(--transition); }
.quick-amount-btn:hover { border-color: var(--primary); background: rgba(108,92,231,0.1); }
.quick-amount-btn.active { border-color: var(--primary); background: rgba(108,92,231,0.15); color: var(--primary); }
.bonus-calc { background: var(--bg-card-hover); border-radius: var(--radius-md); padding: var(--space-4); margin: var(--space-4) 0; }
.bonus-calc-row { display: flex; justify-content: space-between; align-items: center; padding: var(--space-2) 0; font-size: var(--text-sm); }
.bonus-calc-row.total { border-top: 1px solid var(--border-primary); margin-top: var(--space-2); padding-top: var(--space-3); font-weight: var(--weight-bold); color: var(--text-primary); }
.bonus-calc-label { color: var(--text-secondary); }
.bonus-calc-value { color: var(--text-primary); font-family: var(--font-mono); }
.bonus-calc-value.bonus { color: var(--success); }
.qr-section { background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-lg); padding: var(--space-6); text-align: center; display: none; }
.qr-section.active { display: block; }
.qr-code { width: 200px; height: 200px; background: white; border-radius: var(--radius-md); margin: var(--space-4) auto; display: flex; align-items: center; justify-content: center; }
.qr-address { font-family: var(--font-mono); font-size: var(--text-xs); color: var(--text-secondary); word-break: break-all; padding: var(--space-3); background: var(--bg-card-hover); border-radius: var(--radius-md); margin: var(--space-3) 0; }
.gateway-instructions { font-size: var(--text-sm); color: var(--text-secondary); line-height: 1.6; }
@media (max-width: 992px) {
    .deposit-grid { grid-template-columns: 1fr; }
    .quick-amounts { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 480px) {
    .quick-amounts { grid-template-columns: repeat(2, 1fr); }
    .bonus-banner { flex-direction: column; text-align: center; gap: var(--space-3); }
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
            <a href="/user/withdraw.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-cash-register"></i></span><span class="sidebar-nav-text">Withdraw</span></a>
            <a href="/user/deposit.php" class="sidebar-nav-item active"><span class="sidebar-nav-icon"><i class="fas fa-plus-circle"></i></span><span class="sidebar-nav-text">Deposit</span></a>
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
                <h1>Deposit Funds</h1>
                <p>Add funds to your account and get bonus rewards</p>
            </div>
            <div class="dashboard-actions">
                <div class="balance-display" onclick="toggleBalanceVisibility()">
                    <span class="balance-display-icon"><i class="fas fa-eye" id="eyeIcon"></i></span>
                    <span class="balance-display-amount" id="headerBalance"><?= formatCurrency(getUserBalance($userId)) ?></span>
                </div>
            </div>
        </div>

        <div class="bonus-banner">
            <div class="bonus-banner-text">
                <h3><i class="fas fa-gift"></i> Deposit Bonus</h3>
                <p>Get <?= $bonusPercent ?>% bonus on every deposit up to <?= formatCurrency($maxBonus) ?>!</p>
            </div>
            <div class="bonus-banner-amount">+<?= $bonusPercent ?>%</div>
        </div>

        <div class="deposit-grid">
            <div>
                <div class="dashboard-panel">
                    <div class="dashboard-panel-header">
                        <div class="dashboard-panel-title"><i class="fas fa-credit-card"></i> Select Payment Method</div>
                    </div>
                    <div class="gateway-list" id="gatewayList">
                        <?php foreach ($gateways as $gw): ?>
                        <div class="gateway-option" data-gateway="<?= $gw['id'] ?>" onclick="selectGateway(this, '<?= $gw['id'] ?>')">
                            <div class="gateway-option-icon" style="background: rgba(<?= $gw['id'] === 'paypal' ? '0,116,178' : ($gw['id'] === 'stripe' ? '108,92,231' : ($gw['id'] === 'razorpay' ? '0,116,178' : ($gw['id'] === 'crypto' ? '253,203,110' : '253,121,168'))) ?>,0.15); color: var(--<?= $gw['color'] ?>);">
                                <i class="fab <?= $gw['icon'] ?>"></i>
                            </div>
                            <div class="gateway-option-info">
                                <div class="gateway-option-name"><?= $gw['name'] ?></div>
                                <div class="gateway-option-desc"><?= $gw['desc'] ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div style="margin-top: var(--space-6);">
                        <div class="form-group">
                            <label class="form-label">Deposit Amount (<?= APP_CURRENCY ?>)</label>
                            <input type="number" class="form-input" name="amount" id="depositAmount" min="1" step="0.01" required placeholder="Enter amount" oninput="calculateBonus()">
                        </div>

                        <div class="quick-amounts">
                            <?php foreach ($quickAmounts as $qa): ?>
                            <button type="button" class="quick-amount-btn" onclick="setQuickAmount(this, <?= $qa ?>)">$<?= $qa ?></button>
                            <?php endforeach; ?>
                        </div>

                        <div class="bonus-calc" id="bonusCalc" style="display: none;">
                            <div class="bonus-calc-row">
                                <span class="bonus-calc-label">Deposit Amount</span>
                                <span class="bonus-calc-value" id="calcAmount">$0.00</span>
                            </div>
                            <div class="bonus-calc-row">
                                <span class="bonus-calc-label">Bonus (<?= $bonusPercent ?>%)</span>
                                <span class="bonus-calc-value bonus" id="calcBonus">+$0.00</span>
                            </div>
                            <div class="bonus-calc-row total">
                                <span class="bonus-calc-label">Total You Get</span>
                                <span class="bonus-calc-value" id="calcTotal" style="color: var(--success);">$0.00</span>
                            </div>
                        </div>

                        <button class="btn btn-primary btn-block btn-lg" id="depositBtn" disabled onclick="initiateDeposit()">
                            <i class="fas fa-arrow-right"></i> Proceed to Deposit
                        </button>
                        <div id="depositResult" style="margin-top: var(--space-3); display: none;"></div>
                    </div>
                </div>
            </div>

            <div>
                <div class="qr-section" id="qrSection">
                    <h3 style="font-family: var(--font-display); font-size: var(--text-lg); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-2);" id="qrTitle">Crypto Deposit</h3>
                    <p style="font-size: var(--text-sm); color: var(--text-secondary); margin-bottom: var(--space-4);" id="qrDesc">Send the exact amount to the address below</p>
                    <div class="qr-code" id="qrCode">
                        <i class="fas fa-qrcode" style="font-size: 80px; color: #000;"></i>
                    </div>
                    <div class="qr-address" id="qrAddress">bc1qxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx</div>
                    <button class="btn btn-sm btn-ghost" onclick="copyAddress()"><i class="fas fa-copy"></i> Copy Address</button>
                </div>

                <div class="dashboard-panel" style="margin-top: var(--space-6);">
                    <div class="dashboard-panel-header">
                        <div class="dashboard-panel-title"><i class="fas fa-info-circle"></i> Payment Instructions</div>
                    </div>
                    <div class="gateway-instructions" id="gatewayInstructions">
                        <p>Select a payment method above to see instructions.</p>
                    </div>
                </div>

                <div class="dashboard-panel" style="margin-top: var(--space-6);">
                    <div class="dashboard-panel-header">
                        <div class="dashboard-panel-title"><i class="fas fa-history"></i> Deposit History</div>
                    </div>
                    <div class="table-container">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Method</th>
                                    <th>Amount</th>
                                    <th>Bonus</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($deposits) > 0): ?>
                                <?php foreach ($deposits as $d): ?>
                                <tr>
                                    <td><span class="badge badge-primary badge-sm"><?= ucfirst($d['gateway']) ?></span></td>
                                    <td><span class="text-mono" style="font-weight: var(--weight-semibold);"><?= formatCurrency($d['amount']) ?></span></td>
                                    <td><span class="text-mono" style="color: var(--success);">+<?= formatCurrency($d['bonus']) ?></span></td>
                                    <td><span class="badge badge-<?= $d['status'] === 'completed' ? 'success' : ($d['status'] === 'pending' ? 'warning' : ($d['status'] === 'processing' ? 'info' : 'danger')) ?> badge-sm"><?= ucfirst($d['status']) ?></span></td>
                                    <td><span class="text-mono" style="font-size: var(--text-xs); color: var(--text-tertiary);"><?= date('M d, H:i', strtotime($d['created_at'])) ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <tr><td colspan="5"><div class="table-empty"><div class="table-empty-icon"><i class="fas fa-inbox"></i></div><p>No deposits yet.</p></div></td></tr>
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
    if (balanceVisible) {
        el.textContent = '<?= formatCurrency(getUserBalance($userId)) ?>';
        icon.className = 'fas fa-eye';
    } else {
        el.textContent = '••••••';
        icon.className = 'fas fa-eye-slash';
    }
}

let selectedGateway = '';

const instructions = {
    paypal: '<h4 style="color: var(--text-primary); margin-bottom: var(--space-2);">PayPal Instructions</h4><ol style="padding-left: var(--space-5);"><li>Click "Proceed to Deposit" below.</li><li>You will be redirected to PayPal.</li><li>Log in and complete the payment.</li><li>Funds are credited instantly.</li></ol>',
    stripe: '<h4 style="color: var(--text-primary); margin-bottom: var(--space-2);">Stripe Instructions</h4><ol style="padding-left: var(--space-5);"><li>Click "Proceed to Deposit" below.</li><li>Enter your card details on the secure Stripe checkout.</li><li>Payment is processed immediately.</li><li>Funds are credited to your account.</li></ol>',
    razorpay: '<h4 style="color: var(--text-primary); margin-bottom: var(--space-2);">Razorpay Instructions</h4><ol style="padding-left: var(--space-5);"><li>Click "Proceed to Deposit" below.</li><li>Choose UPI, NetBanking, or Card.</li><li>Complete the payment on Razorpay.</li><li>Funds are credited instantly.</li></ol>',
    crypto: '<h4 style="color: var(--text-primary); margin-bottom: var(--space-2);">Crypto Instructions</h4><ol style="padding-left: var(--space-5);"><li>Select the cryptocurrency you want to use.</li><li>Send the exact amount to the displayed address.</li><li>Wait for network confirmations (usually 1-30 min).</li><li>Funds are credited after 3 confirmations.</li></ol>',
    binance: '<h4 style="color: var(--text-primary); margin-bottom: var(--space-2);">Binance Pay Instructions</h4><ol style="padding-left: var(--space-5);"><li>Click "Proceed to Deposit" below.</li><li>Scan the QR code with Binance app.</li><li>Confirm the payment in Binance.</li><li>Funds are credited instantly.</li></ol>'
};

function selectGateway(el, gateway) {
    document.querySelectorAll('.gateway-option').forEach(g => g.classList.remove('selected'));
    el.classList.add('selected');
    selectedGateway = gateway;
    document.getElementById('depositBtn').disabled = false;
    document.getElementById('gatewayInstructions').innerHTML = instructions[gateway] || '<p>Selected payment method.</p>';
    if (gateway === 'crypto' || gateway === 'binance') {
        document.getElementById('qrSection').classList.add('active');
        document.getElementById('qrTitle').textContent = gateway === 'crypto' ? 'Crypto Deposit' : 'Binance Pay';
        document.getElementById('qrDesc').textContent = gateway === 'crypto' ? 'Send funds to the address below' : 'Scan with Binance app';
    } else {
        document.getElementById('qrSection').classList.remove('active');
    }
}

function setQuickAmount(el, amount) {
    document.getElementById('depositAmount').value = amount;
    document.querySelectorAll('.quick-amount-btn').forEach(b => b.classList.remove('active'));
    el.classList.add('active');
    calculateBonus();
}

function calculateBonus() {
    const amount = parseFloat(document.getElementById('depositAmount').value) || 0;
    const calc = document.getElementById('bonusCalc');
    if (amount <= 0) { calc.style.display = 'none'; return; }
    calc.style.display = 'block';
    const bonus = Math.min(amount * <?= $bonusPercent ?> / 100, <?= $maxBonus ?>);
    const total = amount + bonus;
    document.getElementById('calcAmount').textContent = '<?= APP_CURRENCY_SYMBOL ?>' + amount.toFixed(2);
    document.getElementById('calcBonus').textContent = '+<?= APP_CURRENCY_SYMBOL ?>' + bonus.toFixed(2);
    document.getElementById('calcTotal').textContent = '<?= APP_CURRENCY_SYMBOL ?>' + total.toFixed(2);
}

function initiateDeposit() {
    if (!selectedGateway) { showResult('Please select a payment method', 'danger'); return; }
    const amount = parseFloat(document.getElementById('depositAmount').value);
    if (!amount || amount <= 0) { showResult('Please enter a valid amount', 'danger'); return; }
    const btn = document.getElementById('depositBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    const formData = new FormData();
    formData.append('gateway', selectedGateway);
    formData.append('amount', amount);
    formData.append('csrf_token', '<?= csrf_token() ?>');
    fetch('/user/ajax/create_deposit.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (data.redirect) {
                window.location.href = data.redirect;
            } else {
                showResult(data.message || 'Deposit initiated successfully!', 'success');
                setTimeout(() => location.reload(), 2000);
            }
        } else {
            showResult(data.error || 'Deposit failed', 'danger');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-arrow-right"></i> Proceed to Deposit';
        }
    })
    .catch(() => {
        showResult('Network error. Please try again.', 'danger');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-arrow-right"></i> Proceed to Deposit';
    });
}

function showResult(msg, type) {
    const el = document.getElementById('depositResult');
    el.style.display = 'block';
    el.className = 'alert alert-' + type;
    el.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + msg;
}

function copyAddress() {
    const addr = document.getElementById('qrAddress').textContent;
    navigator.clipboard.writeText(addr).catch(() => {});
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
