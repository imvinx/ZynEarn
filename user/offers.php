<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAuth();
$user = getCurrentUser();
$pageTitle = 'Offers';
$userId = $user['id'];
$db = getDB();

$stmt = $db->query("SELECT DISTINCT category FROM offerwall_offers WHERE status = 'active' AND category IS NOT NULL ORDER BY category");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $db->query("SELECT DISTINCT device_type FROM offerwall_offers WHERE status = 'active' AND device_type IS NOT NULL ORDER BY device_type");
$devices = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $db->prepare("SELECT o.*, oc.status as comp_status FROM offerwall_offers o LEFT JOIN offerwall_completions oc ON oc.offer_id = o.id AND oc.user_id = ? WHERE o.status = 'active' ORDER BY o.payout_amount DESC LIMIT 12");
$stmt->execute([$userId]);
$offers = $stmt->fetchAll();

$stmt = $db->prepare("SELECT oc.*, o.title as offer_title, o.icon as offer_icon FROM offerwall_completions oc JOIN offerwall_offers o ON oc.offer_id = o.id WHERE oc.user_id = ? ORDER BY oc.created_at DESC LIMIT 10");
$stmt->execute([$userId]);
$recentCompletions = $stmt->fetchAll();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<style>
.offers-filter-bar {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    flex-wrap: wrap;
    margin-bottom: var(--space-6);
    padding: var(--space-4);
    background: var(--card-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
}
.offers-filter-bar select, .offers-filter-bar input {
    padding: var(--space-2) var(--space-3);
    background: var(--bg-input);
    border: 1px solid var(--border-primary);
    border-radius: var(--radius-md);
    color: var(--text-primary);
    font-size: var(--text-sm);
}
.offers-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: var(--space-4);
}
.offer-card {
    background: var(--card-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    padding: var(--space-5);
    transition: var(--transition);
    display: flex;
    flex-direction: column;
}
.offer-card:hover {
    border-color: var(--border-light);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}
.offer-card-header {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    margin-bottom: var(--space-3);
}
.offer-card-icon {
    width: 48px;
    height: 48px;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--text-xl);
    flex-shrink: 0;
    background: rgba(108,92,231,0.15);
    color: var(--primary);
}
.offer-card-title {
    font-size: var(--text-sm);
    font-weight: var(--weight-semibold);
    color: var(--text-primary);
    flex: 1;
}
.offer-card-desc {
    font-size: var(--text-xs);
    color: var(--text-secondary);
    margin-bottom: var(--space-3);
    flex: 1;
}
.offer-card-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: var(--space-3);
    border-top: 1px solid var(--border-primary);
}
.offer-card-payout {
    font-family: var(--font-display);
    font-size: var(--text-lg);
    font-weight: var(--weight-bold);
    color: var(--success);
}
.offer-card-category {
    font-size: var(--text-xs);
    color: var(--text-tertiary);
    padding: 2px 8px;
    background: rgba(108,92,231,0.1);
    border-radius: var(--radius-full);
}
.completions-list {
    display: flex;
    flex-direction: column;
    gap: var(--space-2);
}
.completion-item {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    padding: var(--space-3);
    background: var(--bg-card-hover);
    border-radius: var(--radius-md);
    font-size: var(--text-sm);
}
.completion-item img {
    width: 32px;
    height: 32px;
    border-radius: var(--radius-sm);
    object-fit: cover;
}
.completion-info {
    flex: 1;
}
.completion-title {
    color: var(--text-primary);
    font-weight: var(--weight-medium);
}
.completion-date {
    font-size: var(--text-xs);
    color: var(--text-tertiary);
    margin-top: 2px;
}
.completion-payout {
    font-family: var(--font-mono);
    color: var(--success);
    font-weight: var(--weight-semibold);
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
            <a href="/user/offers.php" class="sidebar-nav-item active"><span class="sidebar-nav-icon"><i class="fas fa-th"></i></span><span class="sidebar-nav-text">Offers</span></a>
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
            <a href="/user/profile.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-user"></i></span><span class="sidebar-nav-text">Profile</span></a>
            <a href="/auth/logout.php" class="sidebar-nav-item logout-item"><span class="sidebar-nav-icon"><i class="fas fa-sign-out-alt"></i></span><span class="sidebar-nav-text">Logout</span></a>
        </div>
    </aside>
    <main class="main-content">
        <div class="dashboard-top-header">
            <div class="dashboard-greeting">
                <h1>Offerwall</h1>
                <p>Complete offers and earn rewards</p>
            </div>
        </div>

        <div class="offers-filter-bar">
            <select id="filterCategory" onchange="loadOffers(1)">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= sanitize($cat) ?>"><?= sanitize($cat) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="filterPayout" onchange="loadOffers(1)">
                <option value="">Any Payout</option>
                <option value="low">Low (&lt; $0.50)</option>
                <option value="medium">Medium ($0.50 - $2.00)</option>
                <option value="high">High (&gt; $2.00)</option>
            </select>
            <select id="filterDevice" onchange="loadOffers(1)">
                <option value="">All Devices</option>
                <?php foreach ($devices as $d): ?>
                <option value="<?= sanitize($d) ?>"><?= ucfirst(sanitize($d)) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-primary" onclick="loadOffers(1)"><i class="fas fa-search"></i> Filter</button>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 300px; gap: var(--space-6);">
            <div>
                <div class="dashboard-panel-header">
                    <div class="dashboard-panel-title">Available Offers</div>
                </div>
                <div class="offers-grid" id="offersContainer">
                    <?php foreach ($offers as $offer): ?>
                    <div class="offer-card">
                        <div class="offer-card-header">
                            <div class="offer-card-icon"><i class="fas fa-tag"></i></div>
                            <div class="offer-card-title"><?= sanitize($offer['title']) ?></div>
                        </div>
                        <div class="offer-card-desc"><?= sanitize(mb_substr($offer['description'] ?? 'Complete this offer to earn rewards', 0, 100)) ?></div>
                        <div class="offer-card-meta">
                            <span class="offer-card-payout">+<?= formatCurrency($offer['payout_amount']) ?></span>
                            <span class="offer-card-category"><?= sanitize($offer['category'] ?? 'General') ?></span>
                        </div>
                        <button class="btn btn-sm btn-primary btn-block mt-3" onclick="startOffer(<?= $offer['id'] ?>)">Start</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div id="offersPagination" class="pagination" style="margin-top: var(--space-4); display: flex; justify-content: center; gap: var(--space-2);"></div>
            </div>
            <div>
                <div class="dashboard-panel">
                    <div class="dashboard-panel-header">
                        <div class="dashboard-panel-title">Recent Completions</div>
                    </div>
                    <?php if (count($recentCompletions) > 0): ?>
                    <div class="completions-list">
                        <?php foreach ($recentCompletions as $c): ?>
                        <div class="completion-item">
                            <div class="completion-info">
                                <div class="completion-title"><?= sanitize($c['offer_title']) ?></div>
                                <div class="completion-date"><?= timeAgo($c['created_at']) ?></div>
                            </div>
                            <span class="completion-payout">+<?= formatCurrency($c['payout']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div style="padding: var(--space-6); text-align: center; color: var(--text-tertiary);">
                        <i class="fas fa-clock" style="font-size: var(--text-3xl); opacity: 0.3; margin-bottom: var(--space-2); display: block;"></i>
                        <p style="font-size: var(--text-sm);">No completions yet</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="bottom-nav-spacer"></div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadOffers(1);
});

function loadOffers(page) {
    const category = document.getElementById('filterCategory').value;
    const payout = document.getElementById('filterPayout').value;
    const device = document.getElementById('filterDevice').value;

    const container = document.getElementById('offersContainer');
    container.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: var(--space-8);"><i class="fas fa-spinner fa-spin" style="font-size: var(--text-2xl); color: var(--primary);"></i><p style="margin-top: var(--space-2); color: var(--text-tertiary);">Loading...</p></div>';

    const params = new URLSearchParams({ page, category, payout, device });
    fetch('/user/ajax/offers_list.php?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            container.innerHTML = data.html;
            renderPagination(data.total_pages, data.current_page);
        }
    });
}

function renderPagination(totalPages, currentPage) {
    const el = document.getElementById('offersPagination');
    if (totalPages <= 1) { el.innerHTML = ''; return; }
    let html = '';
    for (let i = 1; i <= totalPages; i++) {
        html += '<button class="btn btn-sm ' + (i === currentPage ? 'btn-primary' : 'btn-outline-light') + '" onclick="loadOffers(' + i + ')">' + i + '</button>';
    }
    el.innerHTML = html;
}

function startOffer(offerId) {
    const formData = new FormData();
    formData.append('offer_id', offerId);
    formData.append('csrf_token', '<?= csrf_token() ?>');

    fetch('/user/ajax/start_offer.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.url) {
            window.open(data.url, '_blank');
        } else {
            alert(data.error || 'Failed to start offer');
        }
    });
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
