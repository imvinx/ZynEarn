<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAuth();
$user = getCurrentUser();
$pageTitle = 'Tasks';
$userId = $user['id'];
$db = getDB();

$stmt = $db->prepare("SELECT * FROM tasks WHERE status = 'active' ORDER BY reward_amount DESC");
$stmt->execute();
$tasks = $stmt->fetchAll();

$stmt = $db->prepare("SELECT ts.*, t.title as task_title, t.type as task_type, t.reward_amount FROM task_submissions ts JOIN tasks t ON ts.task_id = t.id WHERE ts.user_id = ? ORDER BY ts.created_at DESC LIMIT 10");
$stmt->execute([$userId]);
$submissions = $stmt->fetchAll();

$platformIcons = [
    'social' => 'fa-share-alt', 'youtube' => 'fa-youtube', 'telegram' => 'fa-telegram',
    'discord' => 'fa-discord', 'twitter' => 'fa-twitter', 'instagram' => 'fa-instagram',
    'facebook' => 'fa-facebook', 'custom' => 'fa-link'
];
$platformColors = [
    'social' => 'primary', 'youtube' => 'danger', 'telegram' => 'info',
    'discord' => 'primary', 'twitter' => 'info', 'instagram' => 'accent',
    'facebook' => 'info', 'custom' => 'secondary'
];
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<style>
.tasks-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: var(--space-4);
}
.task-card {
    background: var(--card-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    padding: var(--space-5);
    transition: var(--transition);
    display: flex;
    flex-direction: column;
}
.task-card:hover {
    border-color: var(--border-light);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}
.task-card-header {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    margin-bottom: var(--space-3);
}
.task-card-icon {
    width: 44px;
    height: 44px;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--text-xl);
    flex-shrink: 0;
}
.task-card-title {
    flex: 1;
    font-size: var(--text-sm);
    font-weight: var(--weight-semibold);
    color: var(--text-primary);
}
.task-card-desc {
    font-size: var(--text-xs);
    color: var(--text-secondary);
    margin-bottom: var(--space-2);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.task-card-req {
    font-size: var(--text-xs);
    color: var(--text-tertiary);
    margin-bottom: var(--space-3);
    padding: var(--space-2);
    background: var(--bg-card-hover);
    border-radius: var(--radius-sm);
}
.task-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: var(--space-3);
    border-top: 1px solid var(--border-primary);
    margin-top: auto;
}
.task-card-reward {
    font-family: var(--font-display);
    font-size: var(--text-base);
    font-weight: var(--weight-bold);
    color: var(--success);
}
.submission-item {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    padding: var(--space-3);
    background: var(--bg-card-hover);
    border-radius: var(--radius-md);
    margin-bottom: var(--space-2);
}
.submission-item:last-child { margin-bottom: 0; }
.submission-info { flex: 1; }
.submission-title { font-size: var(--text-sm); color: var(--text-primary); font-weight: var(--weight-medium); }
.submission-date { font-size: var(--text-xs); color: var(--text-tertiary); }
</style>

<div class="dashboard-wrapper">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-section">
            <div class="sidebar-section-title">Main Menu</div>
            <a href="/user/dashboard.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-home"></i></span><span class="sidebar-nav-text">Dashboard</span></a>
            <a href="/user/wallet.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-wallet"></i></span><span class="sidebar-nav-text">Wallet</span></a>
            <a href="/user/earnings.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-chart-line"></i></span><span class="sidebar-nav-text">Earnings</span></a>
            <a href="/user/offers.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-th"></i></span><span class="sidebar-nav-text">Offers</span></a>
            <a href="/user/tasks.php" class="sidebar-nav-item active"><span class="sidebar-nav-icon"><i class="fas fa-check-circle"></i></span><span class="sidebar-nav-text">Tasks</span></a>
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
                <h1>Social Tasks</h1>
                <p>Complete social media tasks and earn rewards</p>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 300px; gap: var(--space-6);">
            <div>
                <div class="dashboard-panel-header">
                    <div class="dashboard-panel-title">Available Tasks</div>
                </div>
                <div class="tasks-grid" id="tasksContainer">
                    <?php if (count($tasks) > 0): ?>
                    <?php foreach ($tasks as $task): 
                        $icon = $platformIcons[$task['type']] ?? 'fa-link';
                        $color = $platformColors[$task['type']] ?? 'primary';
                    ?>
                    <div class="task-card" data-id="<?= $task['id'] ?>">
                        <div class="task-card-header">
                            <div class="task-card-icon" style="background: rgba(108,92,231,0.15); color: var(--<?= $color ?>);"><i class="fab <?= $icon ?>"></i></div>
                            <div class="task-card-title"><?= sanitize($task['title']) ?></div>
                        </div>
                        <div class="task-card-desc"><?= sanitize($task['description'] ?? '') ?></div>
                        <?php if ($task['requirements']): ?>
                        <div class="task-card-req"><i class="fas fa-info-circle"></i> <?= sanitize($task['requirements']) ?></div>
                        <?php endif; ?>
                        <div class="task-card-footer">
                            <span class="task-card-reward">+<?= formatCurrency($task['reward_amount']) ?></span>
                            <button class="btn btn-sm btn-primary" onclick="openTaskSubmit(<?= $task['id'] ?>)"><i class="fas fa-check"></i> Verify</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: var(--space-12); color: var(--text-tertiary);">
                        <i class="fas fa-tasks" style="font-size: var(--text-5xl); opacity: 0.3; margin-bottom: var(--space-4); display: block;"></i>
                        <p>No tasks available right now. Check back later!</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <div class="dashboard-panel">
                    <div class="dashboard-panel-header">
                        <div class="dashboard-panel-title">My Submissions</div>
                    </div>
                    <?php if (count($submissions) > 0): ?>
                    <?php foreach ($submissions as $s): ?>
                    <div class="submission-item">
                        <div class="submission-info">
                            <div class="submission-title"><?= sanitize($s['task_title']) ?></div>
                            <div class="submission-date"><?= timeAgo($s['created_at']) ?></div>
                        </div>
                        <span class="badge badge-<?= $s['status'] === 'approved' ? 'success' : ($s['status'] === 'rejected' ? 'danger' : 'warning') ?> badge-sm"><?= ucfirst($s['status']) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div style="padding: var(--space-6); text-align: center; color: var(--text-tertiary);">
                        <i class="fas fa-inbox" style="font-size: var(--text-3xl); opacity: 0.3; margin-bottom: var(--space-2); display: block;"></i>
                        <p style="font-size: var(--text-sm);">No submissions yet</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="bottom-nav-spacer"></div>
    </main>
</div>

<div class="modal-backdrop" id="taskModalBackdrop"></div>
<div class="modal modal-sm" id="taskModal">
    <div class="modal-header">
        <div class="modal-title">Submit Task Proof</div>
        <button class="modal-close" onclick="closeTaskModal()"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
        <form id="taskForm" onsubmit="submitTask(event)">
            <input type="hidden" name="task_id" id="taskId" value="">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <p id="taskInstructions" style="font-size: var(--text-sm); color: var(--text-secondary); margin-bottom: var(--space-4);"></p>
            <div class="form-group">
                <label class="form-label">Proof URL (screenshot link)</label>
                <input type="url" class="form-input" name="proof_url" required placeholder="https://imgur.com/... or similar">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Submit</button>
        </form>
    </div>
</div>

<script>
function openTaskSubmit(taskId) {
    document.getElementById('taskId').value = taskId;
    document.getElementById('taskInstructions').textContent = 'Please provide a URL to your screenshot or proof of completion.';
    document.getElementById('taskModal').classList.add('active');
    document.getElementById('taskModalBackdrop').classList.add('active');
}

function closeTaskModal() {
    document.getElementById('taskModal').classList.remove('active');
    document.getElementById('taskModalBackdrop').classList.remove('active');
}

function submitTask(e) {
    e.preventDefault();
    const formData = new FormData(document.getElementById('taskForm'));

    fetch('/user/ajax/submit_task.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closeTaskModal();
            location.reload();
        } else {
            alert(data.error || 'Submission failed');
        }
    })
    .catch(() => alert('Network error'));
}

document.getElementById('taskModalBackdrop').addEventListener('click', closeTaskModal);
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
