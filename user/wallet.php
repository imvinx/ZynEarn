<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAuth();
$user = getCurrentUser();
$pageTitle = 'Wallet';
$userId = $user['id'];
$db = getDB();

$totalBalance = getUserBalance($userId);

$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as pending FROM earnings WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$userId]);
$pendingEarnings = $stmt->fetch()['pending'];

$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as lifetime FROM earnings WHERE user_id = ? AND status = 'credited'");
$stmt->execute([$userId]);
$lifetimeEarnings = $stmt->fetch()['lifetime'];

$stmt = $db->prepare("SELECT * FROM user_wallets WHERE user_id = ? ORDER BY is_default DESC, created_at ASC");
$stmt->execute([$userId]);
$wallets = $stmt->fetchAll();

$stmt = $db->prepare("SELECT id, type, amount, status, description, created_at FROM earnings WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$userId]);
$recentTransactions = $stmt->fetchAll();

$chartLabels = [];
$chartData = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM earnings WHERE user_id = ? AND status = 'credited' AND DATE(created_at) = ?");
    $stmt->execute([$userId, $date]);
    $dayTotal = $stmt->fetch()['total'];
    $chartLabels[] = date('M d', strtotime($date));
    $chartData[] = (float)$dayTotal;
}

$walletTypes = [
    'upi' => ['label' => 'UPI', 'icon' => 'fa-mobile-alt', 'color' => 'primary'],
    'paytm' => ['label' => 'Paytm', 'icon' => 'fa-paypal', 'color' => 'secondary'],
    'paypal' => ['label' => 'PayPal', 'icon' => 'fa-paypal', 'color' => 'info'],
    'crypto' => ['label' => 'Cryptocurrency', 'icon' => 'fa-bitcoin', 'color' => 'warning'],
    'binance' => ['label' => 'Binance', 'icon' => 'fa-coins', 'color' => 'accent'],
    'faucetpay' => ['label' => 'FaucetPay', 'icon' => 'fa-water', 'color' => 'success'],
];

$depositGateways = [
    ['id' => 'paypal', 'name' => 'PayPal', 'icon' => 'fa-paypal', 'color' => 'info'],
    ['id' => 'stripe', 'name' => 'Stripe', 'icon' => 'fa-credit-card', 'color' => 'primary'],
    ['id' => 'razorpay', 'name' => 'Razorpay', 'icon' => 'fa-rupee-sign', 'color' => 'info'],
    ['id' => 'crypto', 'name' => 'Cryptocurrency', 'icon' => 'fa-bitcoin', 'color' => 'warning'],
];
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<style>
.wallet-summary {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: var(--space-4);
    margin-bottom: var(--space-6);
}
.wallet-summary-card {
    background: var(--card-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    padding: var(--space-5);
    transition: var(--transition);
}
.wallet-summary-card:hover {
    border-color: var(--border-light);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}
.wallet-summary-label {
    font-size: var(--text-xs);
    color: var(--text-tertiary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: var(--space-2);
}
.wallet-summary-amount {
    font-family: var(--font-display);
    font-size: var(--text-2xl);
    font-weight: var(--weight-bold);
    color: var(--text-primary);
}
.wallet-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--space-6);
}
.wallet-card {
    background: var(--card-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    padding: var(--space-5);
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: var(--space-4);
}
.wallet-card:hover {
    border-color: var(--border-light);
}
.wallet-card-icon {
    width: 48px;
    height: 48px;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--text-xl);
    flex-shrink: 0;
}
.wallet-card-info {
    flex: 1;
    min-width: 0;
}
.wallet-card-type {
    font-size: var(--text-sm);
    font-weight: var(--weight-semibold);
    color: var(--text-primary);
}
.wallet-card-address {
    font-size: var(--text-xs);
    color: var(--text-secondary);
    font-family: var(--font-mono);
    word-break: break-all;
    margin-top: 2px;
}
.wallet-card-actions {
    display: flex;
    gap: var(--space-2);
}
.wallet-card-empty {
    background: var(--card-bg);
    border: 2px dashed var(--border-primary);
    border-radius: var(--radius-lg);
    padding: var(--space-6);
    text-align: center;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: var(--space-2);
}
.wallet-card-empty:hover {
    border-color: var(--primary);
    background: rgba(108, 92, 231, 0.05);
}
.wallet-card-empty i {
    font-size: var(--text-3xl);
    color: var(--text-tertiary);
}
.wallet-card-empty span {
    font-size: var(--text-sm);
    color: var(--text-secondary);
}
.gateway-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: var(--space-4);
}
.gateway-card {
    background: var(--card-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    padding: var(--space-5);
    text-align: center;
    cursor: pointer;
    transition: var(--transition);
}
.gateway-card:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}
.gateway-card-icon {
    font-size: var(--text-3xl);
    margin-bottom: var(--space-2);
}
.gateway-card-name {
    font-size: var(--text-sm);
    font-weight: var(--weight-semibold);
    color: var(--text-primary);
}
.chart-container {
    position: relative;
    width: 100%;
    height: 250px;
}
@media (max-width: 992px) {
    .wallet-summary { grid-template-columns: repeat(2, 1fr); }
    .wallet-grid { grid-template-columns: 1fr; }
    .gateway-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 480px) {
    .wallet-summary { grid-template-columns: 1fr; }
    .gateway-grid { grid-template-columns: 1fr; }
}
</style>

<div class="dashboard-wrapper">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-section">
            <div class="sidebar-section-title">Main Menu</div>
            <a href="/user/dashboard.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-home"></i></span><span class="sidebar-nav-text">Dashboard</span></a>
            <a href="/user/wallet.php" class="sidebar-nav-item active"><span class="sidebar-nav-icon"><i class="fas fa-wallet"></i></span><span class="sidebar-nav-text">Wallet</span></a>
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
            <a href="/user/daily.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-calendar-day"></i></span><span class="sidebar-nav-text">Daily</span></a>
            <a href="/user/achievements.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-trophy"></i></span><span class="sidebar-nav-text">Achievements</span></a>
            <a href="/user/missions.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-rocket"></i></span><span class="sidebar-nav-text">Missions</span></a>
        </div>
        <div class="sidebar-section">
            <div class="sidebar-section-title">Account</div>
            <a href="/user/referrals.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-users"></i></span><span class="sidebar-nav-text">Referrals</span></a>
            <a href="/user/withdraw.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-cash-register"></i></span><span class="sidebar-nav-text">Withdraw</span></a>
            <a href="/user/deposit.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-plus-circle"></i></span><span class="sidebar-nav-text">Deposit</span></a>
            <a href="/user/profile.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-user"></i></span><span class="sidebar-nav-text">Profile</span></a>
            <a href="/user/settings.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-cog"></i></span><span class="sidebar-nav-text">Settings</span></a>
            <a href="/auth/logout.php" class="sidebar-nav-item logout-item"><span class="sidebar-nav-icon"><i class="fas fa-sign-out-alt"></i></span><span class="sidebar-nav-text">Logout</span></a>
        </div>
    </aside>
    <main class="main-content">
        <div class="dashboard-top-header">
            <div class="dashboard-greeting">
                <h1>Wallet</h1>
                <p>Manage your balances and withdrawal addresses</p>
            </div>
            <div class="dashboard-actions">
                <div class="balance-display" onclick="toggleBalanceVisibility()">
                    <span class="balance-display-icon"><i class="fas fa-eye" id="eyeIcon"></i></span>
                    <span class="balance-display-amount" id="headerBalance"><?= formatCurrency($totalBalance) ?></span>
                </div>
            </div>
        </div>

        <div class="wallet-summary">
            <div class="wallet-summary-card">
                <div class="wallet-summary-label">Total Balance</div>
                <div class="wallet-summary-amount" style="color: var(--primary);"><?= formatCurrency($totalBalance) ?></div>
            </div>
            <div class="wallet-summary-card">
                <div class="wallet-summary-label">Available for Withdrawal</div>
                <div class="wallet-summary-amount" style="color: var(--success);"><?= formatCurrency($totalBalance) ?></div>
            </div>
            <div class="wallet-summary-card">
                <div class="wallet-summary-label">Pending Earnings</div>
                <div class="wallet-summary-amount" style="color: var(--warning);"><?= formatCurrency($pendingEarnings) ?></div>
            </div>
            <div class="wallet-summary-card">
                <div class="wallet-summary-label">Lifetime Earnings</div>
                <div class="wallet-summary-amount" style="color: var(--accent);"><?= formatCurrency($lifetimeEarnings) ?></div>
            </div>
        </div>

        <div class="wallet-grid">
            <div>
                <div class="dashboard-panel">
                    <div class="dashboard-panel-header">
                        <div class="dashboard-panel-title">Wallet Addresses</div>
                        <button class="btn btn-sm btn-primary" onclick="openAddWallet()"><i class="fas fa-plus"></i> Add</button>
                    </div>
                    <div id="walletList" style="display: flex; flex-direction: column; gap: var(--space-3);">
                        <?php if (count($wallets) > 0): ?>
                        <?php foreach ($wallets as $w): 
                            $wt = $walletTypes[$w['wallet_type']] ?? ['label' => ucfirst($w['wallet_type']), 'icon' => 'fa-wallet', 'color' => 'primary'];
                        ?>
                        <div class="wallet-card" data-id="<?= $w['id'] ?>">
                            <div class="wallet-card-icon wallet-card-icon-<?= $wt['color'] ?>" style="background: rgba(108,92,231,0.15); color: var(--<?= $wt['color'] ?>);">
                                <i class="fas <?= $wt['icon'] ?>"></i>
                            </div>
                            <div class="wallet-card-info">
                                <div class="wallet-card-type"><?= $wt['label'] ?> <?= $w['is_default'] ? '<span class="badge badge-primary badge-sm">Default</span>' : '' ?></div>
                                <div class="wallet-card-address"><?= sanitize($w['wallet_address']) ?></div>
                            </div>
                            <div class="wallet-card-actions">
                                <button class="btn btn-sm btn-ghost" onclick="editWallet(<?= $w['id'] ?>)"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-sm btn-ghost" style="color: var(--danger);" onclick="deleteWallet(<?= $w['id'] ?>)"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <div style="text-align: center; padding: var(--space-8); color: var(--text-tertiary);">
                            <i class="fas fa-wallet" style="font-size: var(--text-4xl); opacity: 0.3; margin-bottom: var(--space-3); display: block;"></i>
                            <p style="font-size: var(--text-sm);">No wallet addresses added yet.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="dashboard-panel" style="margin-top: var(--space-6);">
                    <div class="dashboard-panel-header">
                        <div class="dashboard-panel-title">Deposit</div>
                    </div>
                    <div class="gateway-grid" id="gatewayGrid">
                        <?php foreach ($depositGateways as $gw): ?>
                        <div class="gateway-card" onclick="openDepositModal('<?= $gw['id'] ?>')">
                            <div class="gateway-card-icon" style="color: var(--<?= $gw['color'] ?>);"><i class="fas <?= $gw['icon'] ?>"></i></div>
                            <div class="gateway-card-name"><?= $gw['name'] ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div>
                <div class="dashboard-panel">
                    <div class="dashboard-panel-header">
                        <div class="dashboard-panel-title">Balance History (30 days)</div>
                    </div>
                    <div class="chart-container">
                        <canvas id="balanceChart"></canvas>
                    </div>
                </div>

                <div class="dashboard-panel" style="margin-top: var(--space-6);">
                    <div class="dashboard-panel-header">
                        <div class="dashboard-panel-title">Recent Transactions</div>
                        <a href="/user/earnings.php" class="dashboard-panel-action">View All</a>
                    </div>
                    <div class="table-container">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($recentTransactions) > 0): ?>
                                <?php foreach ($recentTransactions as $tx): ?>
                                <tr>
                                    <td><span class="badge badge-<?= $tx['type'] === 'referral' ? 'info' : ($tx['type'] === 'bonus' ? 'warning' : 'primary') ?> badge-sm"><?= ucfirst(str_replace('_', ' ', $tx['type'])) ?></span></td>
                                    <td><span class="text-mono" style="color: var(--success); font-weight: var(--weight-semibold);">+<?= formatCurrency($tx['amount']) ?></span></td>
                                    <td><span class="text-mono" style="font-size: var(--text-xs); color: var(--text-tertiary);"><?= date('M d, H:i', strtotime($tx['created_at'])) ?></span></td>
                                    <td><span class="badge badge-<?= $tx['status'] === 'credited' ? 'success' : ($tx['status'] === 'pending' ? 'warning' : 'danger') ?> badge-sm"><?= ucfirst($tx['status']) ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <tr><td colspan="4"><div class="table-empty"><div class="table-empty-icon"><i class="fas fa-inbox"></i></div><p>No transactions yet.</p></div></td></tr>
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

<div class="modal-backdrop" id="walletModalBackdrop"></div>
<div class="modal modal-sm" id="walletModal">
    <div class="modal-header">
        <div class="modal-title" id="walletModalTitle">Add Wallet Address</div>
        <button class="modal-close" onclick="closeWalletModal()"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
        <form id="walletForm" onsubmit="saveWallet(event)">
            <input type="hidden" name="id" id="walletId" value="">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="form-group">
                <label class="form-label">Wallet Type</label>
                <select class="form-select" name="wallet_type" id="walletType" required>
                    <option value="">Select type</option>
                    <?php foreach ($walletTypes as $key => $wt): ?>
                    <option value="<?= $key ?>"><?= $wt['label'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Wallet Address</label>
                <input type="text" class="form-input" name="wallet_address" id="walletAddress" required placeholder="Enter your wallet address">
            </div>
            <div class="form-group">
                <label class="toggle">
                    <input type="checkbox" class="toggle-input" name="is_default" value="1">
                    <span class="toggle-track"></span>
                    <span class="toggle-label">Set as default</span>
                </label>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Save Wallet</button>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="depositModalBackdrop"></div>
<div class="modal modal-sm" id="depositModal">
    <div class="modal-header">
        <div class="modal-title">Deposit</div>
        <button class="modal-close" onclick="closeDepositModal()"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
        <form id="depositForm" onsubmit="submitDeposit(event)">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="gateway" id="depositGateway" value="">
            <div class="form-group">
                <label class="form-label">Amount (<?= APP_CURRENCY ?>)</label>
                <input type="number" class="form-input" name="amount" id="depositAmount" min="1" step="0.01" required placeholder="Enter amount">
            </div>
            <div id="depositInfo" style="padding: var(--space-3); background: rgba(108,92,231,0.1); border-radius: var(--radius-md); margin-bottom: var(--space-4); font-size: var(--text-sm); color: var(--text-secondary); display: none;">
                <i class="fas fa-info-circle"></i> You get <strong id="bonusDisplay" style="color: var(--success);">+10%</strong> deposit bonus!
            </div>
            <button type="submit" class="btn btn-primary btn-block">Proceed to Deposit</button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const chartLabels = <?= json_encode($chartLabels) ?>;
const chartData = <?= json_encode($chartData) ?>;

document.addEventListener('DOMContentLoaded', function() {
    renderChart();
});

function renderChart() {
    const ctx = document.getElementById('balanceChart').getContext('2d');
    const isDark = document.body.getAttribute('data-theme') !== 'light';
    const textColor = isDark ? '#8888aa' : '#666688';
    const gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Earnings',
                data: chartData,
                backgroundColor: 'rgba(108, 92, 231, 0.6)',
                borderColor: '#6C5CE7',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: isDark ? '#1a1a35' : '#ffffff',
                    titleColor: textColor,
                    bodyColor: isDark ? '#e0e0ff' : '#1a1a2e',
                    borderColor: isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)',
                    borderWidth: 1,
                    cornerRadius: 8,
                    padding: 12,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return '<?= APP_CURRENCY_SYMBOL ?>' + context.parsed.y.toFixed(4);
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { color: gridColor, drawBorder: false },
                    ticks: { color: textColor, font: { size: 10 }, maxRotation: 45 }
                },
                y: {
                    grid: { color: gridColor, drawBorder: false },
                    ticks: {
                        color: textColor,
                        font: { size: 10 },
                        callback: function(value) {
                            return '<?= APP_CURRENCY_SYMBOL ?>' + value.toFixed(2);
                        }
                    },
                    beginAtZero: true
                }
            }
        }
    });
}

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

function openAddWallet() {
    document.getElementById('walletId').value = '';
    document.getElementById('walletForm').reset();
    document.getElementById('walletModalTitle').textContent = 'Add Wallet Address';
    document.getElementById('walletModal').classList.add('active');
    document.getElementById('walletModalBackdrop').classList.add('active');
}

function editWallet(id) {
    fetch('/user/ajax/get_wallet.php?id=' + id, { headers: { 'X-Requested-With': 'XMLHttpRequest' }})
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('walletId').value = data.wallet.id;
            document.getElementById('walletType').value = data.wallet.wallet_type;
            document.getElementById('walletAddress').value = data.wallet.wallet_address;
            document.querySelector('#walletForm input[name="is_default"]').checked = data.wallet.is_default == 1;
            document.getElementById('walletModalTitle').textContent = 'Edit Wallet Address';
            document.getElementById('walletModal').classList.add('active');
            document.getElementById('walletModalBackdrop').classList.add('active');
        }
    });
}

function closeWalletModal() {
    document.getElementById('walletModal').classList.remove('active');
    document.getElementById('walletModalBackdrop').classList.remove('active');
}

function saveWallet(e) {
    e.preventDefault();
    const form = document.getElementById('walletForm');
    const formData = new FormData(form);

    fetch('/user/ajax/save_wallet.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closeWalletModal();
            location.reload();
        } else {
            alert(data.error || 'Failed to save wallet');
        }
    })
    .catch(() => alert('Network error'));
}

function deleteWallet(id) {
    if (!confirm('Delete this wallet address?')) return;
    const formData = new FormData();
    formData.append('id', id);
    formData.append('csrf_token', '<?= csrf_token() ?>');

    fetch('/user/ajax/delete_wallet.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.querySelector('.wallet-card[data-id="' + id + '"]')?.remove();
        } else {
            alert(data.error || 'Failed to delete');
        }
    });
}

function openDepositModal(gateway) {
    document.getElementById('depositGateway').value = gateway;
    document.getElementById('depositInfo').style.display = 'block';
    document.getElementById('bonusDisplay').textContent = '+<?= DEPOSIT_BONUS_PERCENTAGE ?>%';
    document.getElementById('depositModal').classList.add('active');
    document.getElementById('depositModalBackdrop').classList.add('active');
}

function closeDepositModal() {
    document.getElementById('depositModal').classList.remove('active');
    document.getElementById('depositModalBackdrop').classList.remove('active');
}

function submitDeposit(e) {
    e.preventDefault();
    const formData = new FormData(document.getElementById('depositForm'));

    fetch('/user/ajax/create_deposit.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.redirect) {
            window.location.href = data.redirect;
        } else {
            alert(data.error || 'Deposit failed');
        }
    })
    .catch(() => alert('Network error'));
}

document.getElementById('walletModalBackdrop').addEventListener('click', closeWalletModal);
document.getElementById('depositModalBackdrop').addEventListener('click', closeDepositModal);
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
