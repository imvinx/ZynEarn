<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAuth();
$user = getCurrentUser();
$pageTitle = 'Videos';
$userId = $user['id'];
$db = getDB();

$stmt = $db->prepare("SELECT * FROM earnings WHERE user_id = ? AND type IN ('video','ad') AND status = 'credited' ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$userId]);
$watchHistory = $stmt->fetchAll();

$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM earnings WHERE user_id = ? AND type IN ('video','ad') AND status = 'credited'");
$stmt->execute([$userId]);
$totalVideoEarned = $stmt->fetch()['total'];

$totalBalance = getUserBalance($userId);

$videos = [
    ['id' => 1, 'title' => 'Watch & Earn - Ad 1', 'duration' => 15, 'reward' => AD_REWARD, 'url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ'],
    ['id' => 2, 'title' => 'Watch & Earn - Ad 2', 'duration' => 30, 'reward' => VIDEO_REWARD, 'url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ'],
    ['id' => 3, 'title' => 'Watch & Earn - Ad 3', 'duration' => 20, 'reward' => AD_REWARD, 'url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ'],
    ['id' => 4, 'title' => 'Watch & Earn - Ad 4', 'duration' => 45, 'reward' => VIDEO_REWARD, 'url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ'],
];
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<style>
.videos-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: var(--space-4);
}
.video-card {
    background: var(--card-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    transition: var(--transition);
}
.video-card:hover { border-color: var(--border-light); transform: translateY(-2px); box-shadow: var(--shadow-md); }
.video-thumb {
    position: relative;
    height: 160px;
    background: var(--bg-card-hover);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--text-4xl);
    color: var(--primary);
}
.video-thumb .play-overlay {
    position: absolute;
    width: 56px;
    height: 56px;
    border-radius: var(--radius-full);
    background: rgba(108,92,231,0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
    font-size: var(--text-xl);
    transition: var(--transition);
}
.video-card:hover .play-overlay { transform: scale(1.15); }
.video-info {
    padding: var(--space-4);
}
.video-title {
    font-size: var(--text-sm);
    font-weight: var(--weight-semibold);
    color: var(--text-primary);
    margin-bottom: var(--space-2);
}
.video-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.video-duration {
    font-size: var(--text-xs);
    color: var(--text-tertiary);
}
.video-reward {
    font-family: var(--font-display);
    font-size: var(--text-base);
    font-weight: var(--weight-bold);
    color: var(--success);
}
.watch-container {
    display: none;
    max-width: 700px;
    margin: 0 auto;
}
.watch-player {
    width: 100%;
    aspect-ratio: 16/9;
    background: #000;
    border-radius: var(--radius-lg);
    overflow: hidden;
    margin-bottom: var(--space-4);
}
.watch-player iframe {
    width: 100%;
    height: 100%;
    border: none;
}
.watch-timer {
    text-align: center;
    font-family: var(--font-mono);
    font-size: var(--text-3xl);
    font-weight: var(--weight-bold);
    color: var(--primary);
    margin-bottom: var(--space-4);
}
.history-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--space-3);
    background: var(--bg-card-hover);
    border-radius: var(--radius-md);
    margin-bottom: var(--space-2);
    font-size: var(--text-sm);
}
.history-type { font-weight: var(--weight-medium); color: var(--text-primary); }
.history-amount { font-family: var(--font-mono); color: var(--success); font-weight: var(--weight-semibold); }
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
            <a href="/user/scratch.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-hand-paper"></i></span><span class="sidebar-nav-text">Scratch</span></a>
            <a href="/user/spin.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-circle-notch"></i></span><span class="sidebar-nav-text">Spin</span></a>
            <a href="/user/shortlinks.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-link"></i></span><span class="sidebar-nav-text">Shortlinks</span></a>
            <a href="/user/faucet.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-water"></i></span><span class="sidebar-nav-text">Faucet</span></a>
            <a href="/user/videos.php" class="sidebar-nav-item active"><span class="sidebar-nav-icon"><i class="fas fa-video"></i></span><span class="sidebar-nav-text">Videos</span></a>
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
                <h1>Watch & Earn</h1>
                <p>Watch short videos and ads to earn rewards</p>
            </div>
            <div class="balance-display">
                <span class="balance-display-amount" id="balanceDisplay"><?= formatCurrency($totalBalance) ?></span>
            </div>
        </div>

        <div id="videosListView">
            <div style="display: grid; grid-template-columns: 1fr 300px; gap: var(--space-6);">
                <div>
                    <div class="dashboard-panel-header">
                        <div class="dashboard-panel-title">Available Videos</div>
                    </div>
                    <div class="videos-grid">
                        <?php foreach ($videos as $v): ?>
                        <div class="video-card">
                            <div class="video-thumb" onclick="startWatching(<?= $v['id'] ?>)">
                                <i class="fas fa-play-circle"></i>
                                <div class="play-overlay"><i class="fas fa-play"></i></div>
                            </div>
                            <div class="video-info">
                                <div class="video-title"><?= sanitize($v['title']) ?></div>
                                <div class="video-meta">
                                    <span class="video-duration"><i class="far fa-clock"></i> <?= $v['duration'] ?>s</span>
                                    <span class="video-reward">+<?= formatCurrency($v['reward']) ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <div class="dashboard-panel">
                        <div class="dashboard-panel-header">
                            <div class="dashboard-panel-title">Stats</div>
                        </div>
                        <div style="text-align: center; padding: var(--space-4); background: var(--bg-card-hover); border-radius: var(--radius-md); margin-bottom: var(--space-4);">
                            <div style="font-family: var(--font-display); font-size: var(--text-2xl); font-weight: var(--weight-bold); color: var(--success);"><?= formatCurrency($totalVideoEarned) ?></div>
                            <div style="font-size: var(--text-xs); color: var(--text-tertiary);">Total Video Earnings</div>
                        </div>
                    </div>
                    <div class="dashboard-panel" style="margin-top: var(--space-4);">
                        <div class="dashboard-panel-header">
                            <div class="dashboard-panel-title">Watch History</div>
                        </div>
                        <?php if (count($watchHistory) > 0): ?>
                        <?php foreach ($watchHistory as $wh): ?>
                        <div class="history-item">
                            <span class="history-type"><?= ucfirst($wh['type']) ?></span>
                            <span class="history-amount">+<?= formatCurrency($wh['amount']) ?></span>
                            <span class="history-date"><?= timeAgo($wh['created_at']) ?></span>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <div style="padding: var(--space-6); text-align: center; color: var(--text-tertiary);">
                            <i class="fas fa-clock" style="font-size: var(--text-3xl); opacity: 0.3; margin-bottom: var(--space-2); display: block;"></i>
                            <p style="font-size: var(--text-sm);">No videos watched yet</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="watch-container" id="watchContainer">
            <div class="watch-player">
                <iframe id="videoPlayer" src="" allow="autoplay; encrypted-media" allowfullscreen></iframe>
            </div>
            <div style="text-align: center;">
                <div class="watch-timer" id="watchTimer">0s</div>
                <div style="font-size: var(--text-sm); color: var(--text-secondary); margin-bottom: var(--space-4);">Watch the full ad to earn your reward</div>
                <div class="progress" style="max-width: 400px; margin: 0 auto;">
                    <div class="progress-bar" id="watchProgress" style="width: 0%;"></div>
                </div>
            </div>
        </div>

        <div class="bottom-nav-spacer"></div>
    </main>
</div>

<script>
let watchingVideoId = null;
let watchTimer = null;
let watchSeconds = 0;
let watchDuration = 0;

function startWatching(videoId) {
    const videos = <?= json_encode($videos) ?>;
    const video = videos.find(v => v.id === videoId);
    if (!video) return;

    watchingVideoId = videoId;
    watchDuration = video.duration;
    watchSeconds = 0;

    document.getElementById('videosListView').style.display = 'none';
    document.getElementById('watchContainer').style.display = 'block';
    document.getElementById('videoPlayer').src = video.url;
    document.getElementById('watchTimer').textContent = '0s';
    document.getElementById('watchProgress').style.width = '0%';

    if (watchTimer) clearInterval(watchTimer);
    watchTimer = setInterval(() => {
        watchSeconds++;
        document.getElementById('watchTimer').textContent = watchSeconds + 's';
        const pct = Math.min(100, (watchSeconds / watchDuration) * 100);
        document.getElementById('watchProgress').style.width = pct + '%';

        if (watchSeconds >= watchDuration) {
            clearInterval(watchTimer);
            completeWatch(videoId);
        }
    }, 1000);
}

function completeWatch(videoId) {
    const formData = new FormData();
    formData.append('type', 'video');
    formData.append('video_id', videoId);
    formData.append('csrf_token', '<?= csrf_token() ?>');

    fetch('/user/ajax/complete_video.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('watchTimer').textContent = '✅ +' + data.formatted_amount + ' earned!';
            document.getElementById('watchTimer').style.color = 'var(--success)';
            if (data.new_balance_formatted) {
                document.getElementById('balanceDisplay').textContent = data.new_balance_formatted;
            }
            setTimeout(() => {
                document.getElementById('watchContainer').style.display = 'none';
                document.getElementById('videosListView').style.display = 'block';
                document.getElementById('videoPlayer').src = '';
                document.getElementById('watchTimer').style.color = '';
            }, 3000);
        } else {
            alert(data.error || 'Failed to credit reward');
        }
    });
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
