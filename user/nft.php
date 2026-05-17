<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAuth();
$user = getCurrentUser();
$pageTitle = 'NFT Marketplace';
$userId = $user['id'];
$db = getDB();

$tab = $_GET['tab'] ?? 'marketplace';

$stmt = $db->query("SELECT * FROM nfts WHERE status = 'listed' ORDER BY created_at DESC");
$marketplaceNfts = $stmt->fetchAll();

$stmt = $db->prepare("SELECT n.*, un.purchased_at FROM nfts n JOIN user_nfts un ON n.id = un.nft_id WHERE un.user_id = ? ORDER BY un.purchased_at DESC");
$stmt->execute([$userId]);
$myNfts = $stmt->fetchAll();

$stmt = $db->prepare("SELECT COUNT(*) FROM user_nfts WHERE user_id = ?");
$stmt->execute([$userId]);
$ownedCount = $stmt->fetchColumn();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<style>
.nft-tabs { display: flex; gap: var(--space-2); margin-bottom: var(--space-6); border-bottom: 1px solid var(--border-primary); padding-bottom: 0; }
.nft-tab { padding: var(--space-3) var(--space-5); font-size: var(--text-sm); font-weight: var(--weight-medium); color: var(--text-secondary); cursor: pointer; transition: var(--transition); border-bottom: 2px solid transparent; margin-bottom: -1px; background: none; border-top: none; border-left: none; border-right: none; }
.nft-tab:hover { color: var(--text-primary); }
.nft-tab.active { color: var(--primary); border-bottom-color: var(--primary); }
.nft-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: var(--space-5); }
.nft-card { background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-lg); overflow: hidden; transition: var(--transition); }
.nft-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); border-color: var(--border-light); }
.nft-image { width: 100%; aspect-ratio: 1; background: var(--gradient-midnight); display: flex; align-items: center; justify-content: center; font-size: var(--text-5xl); color: rgba(255,255,255,0.3); position: relative; }
.nft-image .nft-rarity { position: absolute; top: var(--space-3); right: var(--space-3); padding: 2px 10px; border-radius: var(--radius-full); font-size: 10px; font-weight: var(--weight-bold); text-transform: uppercase; }
.rarity-common { background: rgba(150,150,170,0.2); color: #9999bb; }
.rarity-uncommon { background: rgba(0,206,201,0.2); color: var(--secondary); }
.rarity-rare { background: rgba(108,92,231,0.2); color: var(--primary); }
.rarity-epic { background: rgba(253,121,168,0.2); color: var(--accent); }
.rarity-legendary { background: rgba(253,203,110,0.2); color: var(--warning); }
.nft-info { padding: var(--space-4); }
.nft-name { font-size: var(--text-base); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-1); }
.nft-creator { font-size: var(--text-xs); color: var(--text-tertiary); margin-bottom: var(--space-3); }
.nft-footer { display: flex; align-items: center; justify-content: space-between; padding-top: var(--space-3); border-top: 1px solid var(--border-primary); }
.nft-price { font-family: var(--font-display); font-size: var(--text-lg); font-weight: var(--weight-bold); color: var(--success); }
.nft-owned-badge { font-size: var(--text-xs); color: var(--primary); font-weight: var(--weight-semibold); }
.empty-state { text-align: center; padding: var(--space-10); color: var(--text-tertiary); }
.empty-state i { font-size: var(--text-5xl); opacity: 0.3; margin-bottom: var(--space-4); display: block; }
.empty-state p { font-size: var(--text-sm); }
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
            <a href="/user/nft.php" class="sidebar-nav-item active"><span class="sidebar-nav-icon"><i class="fas fa-image"></i></span><span class="sidebar-nav-text">NFTs</span></a>
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
                <h1>NFT Marketplace</h1>
                <p>Buy, sell, and collect unique NFTs</p>
            </div>
            <div class="dashboard-actions">
                <div class="balance-display" onclick="toggleBalanceVisibility()">
                    <span class="balance-display-icon"><i class="fas fa-eye" id="eyeIcon"></i></span>
                    <span class="balance-display-amount" id="headerBalance"><?= formatCurrency(getUserBalance($userId)) ?></span>
                </div>
            </div>
        </div>

        <div class="nft-tabs">
            <button class="nft-tab <?= $tab === 'marketplace' ? 'active' : '' ?>" onclick="switchTab('marketplace')">
                <i class="fas fa-store"></i> Marketplace
            </button>
            <button class="nft-tab <?= $tab === 'my' ? 'active' : '' ?>" onclick="switchTab('my')">
                <i class="fas fa-images"></i> My NFTs (<?= $ownedCount ?>)
            </button>
        </div>

        <div id="marketplaceTab" class="tab-content" style="display: <?= $tab === 'marketplace' ? 'block' : 'none' ?>;">
            <?php if (count($marketplaceNfts) > 0): ?>
            <div class="nft-grid">
                <?php foreach ($marketplaceNfts as $nft): ?>
                <div class="nft-card">
                    <div class="nft-image">
                        <i class="fas fa-paint-brush"></i>
                        <span class="nft-rarity rarity-<?= sanitize($nft['rarity']) ?>"><?= sanitize($nft['rarity']) ?></span>
                    </div>
                    <div class="nft-info">
                        <div class="nft-name"><?= sanitize($nft['name']) ?></div>
                        <div class="nft-creator">Created by <?= sanitize($nft['creator']) ?></div>
                        <div class="nft-footer">
                            <span class="nft-price"><?= formatCurrency($nft['price']) ?></span>
                            <button class="btn btn-sm btn-primary" onclick="buyNft(<?= $nft['id'] ?>)">
                                <i class="fas fa-shopping-cart"></i> Buy
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-store"></i>
                <p>No NFTs listed yet</p>
            </div>
            <?php endif; ?>
        </div>

        <div id="myNftsTab" class="tab-content" style="display: <?= $tab === 'my' ? 'block' : 'none' ?>;">
            <?php if (count($myNfts) > 0): ?>
            <div class="nft-grid">
                <?php foreach ($myNfts as $nft): ?>
                <div class="nft-card">
                    <div class="nft-image">
                        <i class="fas fa-paint-brush"></i>
                        <span class="nft-rarity rarity-<?= sanitize($nft['rarity']) ?>"><?= sanitize($nft['rarity']) ?></span>
                    </div>
                    <div class="nft-info">
                        <div class="nft-name"><?= sanitize($nft['name']) ?></div>
                        <div class="nft-creator">Created by <?= sanitize($nft['creator']) ?></div>
                        <div class="nft-footer">
                            <span class="nft-owned-badge"><i class="fas fa-check-circle"></i> Owned</span>
                            <span class="text-mono" style="font-size: var(--text-xs); color: var(--text-tertiary);"><?= date('M d, Y', strtotime($nft['purchased_at'])) ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-images"></i>
                <p>You don't own any NFTs yet</p>
            </div>
            <?php endif; ?>
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

function switchTab(tab) {
    document.querySelectorAll('.nft-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(t => t.style.display = 'none');
    document.querySelector('.nft-tab.' + (tab === 'marketplace' ? '' : '') + '[onclick="switchTab(\'' + tab + '\')"]').classList.add('active');
    document.getElementById(tab + 'Tab').style.display = 'block';
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    history.replaceState(null, '', url);
}

function buyNft(nftId) {
    if (!confirm('Confirm purchase of this NFT?')) return;
    const formData = new FormData();
    formData.append('nft_id', nftId);
    formData.append('csrf_token', '<?= csrf_token() ?>');

    fetch('/user/ajax/buy_nft.php', {
        method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('NFT purchased successfully!');
            location.reload();
        } else {
            alert(data.error || 'Purchase failed');
        }
    })
    .catch(() => alert('Network error'));
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
