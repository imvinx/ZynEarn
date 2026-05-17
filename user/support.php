<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAuth();
$user = getCurrentUser();
$pageTitle = 'Support';
$userId = $user['id'];
$db = getDB();

$action = $_GET['action'] ?? 'list';
$ticketId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($action === 'view' && $ticketId > 0) {
    $stmt = $db->prepare("SELECT * FROM support_tickets WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$ticketId, $userId]);
    $ticket = $stmt->fetch();
    if (!$ticket) {
        header('Location: /user/support.php');
        exit;
    }
    $stmt = $db->prepare("SELECT * FROM ticket_replies WHERE ticket_id = ? ORDER BY created_at ASC");
    $stmt->execute([$ticketId]);
    $replies = $stmt->fetchAll();
}

$stmt = $db->prepare("SELECT * FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$tickets = $stmt->fetchAll();

$categories = ['general', 'payment', 'account', 'technical', 'bug_report', 'feature_request', 'other'];
$priorities = ['low', 'medium', 'high', 'urgent'];
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<style>
.support-layout { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-6); }
.ticket-card { background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-lg); padding: var(--space-5); transition: var(--transition); cursor: pointer; }
.ticket-card:hover { border-color: var(--border-light); transform: translateY(-2px); }
.ticket-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: var(--space-3); }
.ticket-subject { font-size: var(--text-sm); font-weight: var(--weight-semibold); color: var(--text-primary); }
.ticket-preview { font-size: var(--text-xs); color: var(--text-secondary); margin-bottom: var(--space-3); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.ticket-footer { display: flex; align-items: center; justify-content: space-between; font-size: var(--text-xs); color: var(--text-tertiary); }
.ticket-reply-view { max-width: 800px; margin: 0 auto; }
.reply-item { padding: var(--space-4); border-radius: var(--radius-md); margin-bottom: var(--space-4); }
.reply-item.admin { background: rgba(108,92,231,0.05); border-left: 3px solid var(--primary); }
.reply-item.user { background: var(--bg-card-hover); border-left: 3px solid var(--text-tertiary); }
.reply-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-2); }
.reply-author { font-size: var(--text-sm); font-weight: var(--weight-semibold); color: var(--text-primary); }
.reply-time { font-size: var(--text-xs); color: var(--text-tertiary); }
.reply-content { font-size: var(--text-sm); color: var(--text-secondary); line-height: 1.6; }
.badge-priority-low { background: rgba(0,184,148,0.1); color: var(--success); }
.badge-priority-medium { background: rgba(253,203,110,0.1); color: var(--warning); }
.badge-priority-high { background: rgba(225,112,85,0.1); color: var(--danger); }
.badge-priority-urgent { background: rgba(225,112,85,0.2); color: var(--danger); }
.badge-status-open { background: rgba(108,92,231,0.1); color: var(--primary); }
.badge-status-closed { background: rgba(108,92,231,0.1); color: var(--text-tertiary); }
@media (max-width: 992px) {
    .support-layout { grid-template-columns: 1fr; }
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
            <a href="/user/deposit.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-plus-circle"></i></span><span class="sidebar-nav-text">Deposit</span></a>
        </div>
        <div class="sidebar-section">
            <div class="sidebar-section-title">Account</div>
            <a href="/user/profile.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-user"></i></span><span class="sidebar-nav-text">Profile</span></a>
            <a href="/user/settings.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-cog"></i></span><span class="sidebar-nav-text">Settings</span></a>
            <a href="/user/notifications.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-bell"></i></span><span class="sidebar-nav-text">Notifications</span></a>
            <a href="/user/leaderboard.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-trophy"></i></span><span class="sidebar-nav-text">Leaderboard</span></a>
            <a href="/user/support.php" class="sidebar-nav-item active"><span class="sidebar-nav-icon"><i class="fas fa-headset"></i></span><span class="sidebar-nav-text">Support</span></a>
            <a href="/auth/logout.php" class="sidebar-nav-item logout-item"><span class="sidebar-nav-icon"><i class="fas fa-sign-out-alt"></i></span><span class="sidebar-nav-text">Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <div class="dashboard-top-header">
            <div class="dashboard-greeting">
                <h1><?= $action === 'view' ? 'Ticket #' . $ticketId : 'Support' ?></h1>
                <p><?= $action === 'view' ? 'Viewing ticket details' : 'Get help from our support team' ?></p>
            </div>
            <div class="dashboard-actions">
                <div class="balance-display" onclick="toggleBalanceVisibility()">
                    <span class="balance-display-icon"><i class="fas fa-eye" id="eyeIcon"></i></span>
                    <span class="balance-display-amount" id="headerBalance"><?= formatCurrency(getUserBalance($userId)) ?></span>
                </div>
            </div>
        </div>

        <?php if ($action === 'view' && isset($ticket)): ?>
        <div class="ticket-reply-view">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-4); flex-wrap: wrap; gap: var(--space-3);">
                <div style="display: flex; align-items: center; gap: var(--space-3);">
                    <a href="/user/support.php" class="btn btn-sm btn-ghost"><i class="fas fa-arrow-left"></i> Back</a>
                    <h2 style="font-family: var(--font-display); font-size: var(--text-xl); font-weight: var(--weight-semibold); color: var(--text-primary);"><?= sanitize($ticket['subject']) ?></h2>
                </div>
                <div style="display: flex; gap: var(--space-2);">
                    <span class="badge badge-status-<?= $ticket['status'] ?>"><?= ucfirst($ticket['status']) ?></span>
                    <span class="badge badge-priority-<?= $ticket['priority'] ?>"><?= ucfirst($ticket['priority']) ?></span>
                    <?php if ($ticket['status'] === 'open'): ?>
                    <button class="btn btn-sm btn-ghost" style="color: var(--danger);" onclick="closeTicket(<?= $ticketId ?>)"><i class="fas fa-times"></i> Close</button>
                    <?php endif; ?>
                </div>
            </div>

            <div style="background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-lg); padding: var(--space-6); margin-bottom: var(--space-4);">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-3);">
                    <span style="font-size: var(--text-sm); font-weight: var(--weight-semibold); color: var(--text-primary);"><?= sanitize($user['username']) ?></span>
                    <span style="font-size: var(--text-xs); color: var(--text-tertiary);"><?= date('M d, Y H:i', strtotime($ticket['created_at'])) ?></span>
                </div>
                <div style="font-size: var(--text-sm); color: var(--text-secondary); line-height: 1.6; white-space: pre-wrap;"><?= sanitize($ticket['message']) ?></div>
            </div>

            <?php foreach ($replies as $reply): ?>
            <div class="reply-item <?= $reply['is_admin'] ? 'admin' : 'user' ?>">
                <div class="reply-header">
                    <span class="reply-author"><?= $reply['is_admin'] ? 'Support Team' : sanitize($user['username']) ?></span>
                    <span class="reply-time"><?= timeAgo($reply['created_at']) ?></span>
                </div>
                <div class="reply-content"><?= nl2br(sanitize($reply['message'])) ?></div>
            </div>
            <?php endforeach; ?>

            <?php if ($ticket['status'] === 'open'): ?>
            <div class="profile-form-card" style="margin-top: var(--space-6);">
                <h3 style="font-family: var(--font-display); font-size: var(--text-lg); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-4);">Reply to Ticket</h3>
                <form id="replyForm" onsubmit="replyTicket(event, <?= $ticketId ?>)">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <div class="form-group">
                        <textarea class="form-input" name="message" rows="4" required placeholder="Type your reply..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" id="replyBtn"><i class="fas fa-reply"></i> Send Reply</button>
                </form>
                <div id="replyResult" style="margin-top: var(--space-3); display: none;"></div>
            </div>
            <?php endif; ?>
            <div id="closeResult" style="margin-top: var(--space-3); display: none;"></div>
        </div>
        <?php else: ?>
        <div class="support-layout">
            <div>
                <div class="dashboard-panel">
                    <div class="dashboard-panel-header">
                        <div class="dashboard-panel-title"><i class="fas fa-ticket-alt"></i> My Tickets</div>
                    </div>
                    <?php if (count($tickets) > 0): ?>
                    <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                        <?php foreach ($tickets as $t): ?>
                        <a href="/user/support.php?action=view&id=<?= $t['id'] ?>" class="ticket-card" style="text-decoration: none;">
                            <div class="ticket-header">
                                <div class="ticket-subject"><?= sanitize($t['subject']) ?></div>
                                <span class="badge badge-status-<?= $t['status'] ?> badge-sm"><?= ucfirst($t['status']) ?></span>
                            </div>
                            <div class="ticket-preview"><?= sanitize(substr($t['message'], 0, 120)) ?>...</div>
                            <div class="ticket-footer">
                                <span class="badge badge-priority-<?= $t['priority'] ?> badge-sm"><?= ucfirst($t['priority']) ?></span>
                                <span><?= timeAgo($t['created_at']) ?></span>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="table-empty">
                        <div class="table-empty-icon"><i class="fas fa-tickets"></i></div>
                        <p>No support tickets yet. Create one below.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <div class="dashboard-panel">
                    <div class="dashboard-panel-header">
                        <div class="dashboard-panel-title"><i class="fas fa-plus-circle"></i> Create Ticket</div>
                    </div>
                    <form id="ticketForm" onsubmit="createTicket(event)">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <div class="form-group">
                            <label class="form-label">Subject</label>
                            <input type="text" class="form-input" name="subject" required placeholder="Brief description of your issue">
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                            <div class="form-group">
                                <label class="form-label">Category</label>
                                <select class="form-select" name="category">
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat ?>"><?= ucfirst(str_replace('_', ' ', $cat)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Priority</label>
                                <select class="form-select" name="priority">
                                    <?php foreach ($priorities as $p): ?>
                                    <option value="<?= $p ?>" <?= $p === 'medium' ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Message</label>
                            <textarea class="form-input" name="message" rows="5" required placeholder="Describe your issue in detail..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block" id="ticketBtn"><i class="fas fa-paper-plane"></i> Submit Ticket</button>
                    </form>
                    <div id="ticketResult" style="margin-top: var(--space-3); display: none;"></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
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
        el.textContent = '\u2022\u2022\u2022\u2022\u2022\u2022';
        icon.className = 'fas fa-eye-slash';
    }
}

function createTicket(e) {
    e.preventDefault();
    const btn = document.getElementById('ticketBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
    const formData = new FormData(document.getElementById('ticketForm'));
    fetch('/user/ajax/create_ticket.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        const result = document.getElementById('ticketResult');
        result.style.display = 'block';
        if (data.success) {
            result.className = 'alert alert-success';
            result.innerHTML = '<i class="fas fa-check-circle"></i> Ticket created! Redirecting...';
            setTimeout(() => window.location.href = '/user/support.php?action=view&id=' + data.ticket_id, 1500);
        } else {
            result.className = 'alert alert-danger';
            result.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + (data.error || 'Failed to create ticket');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Ticket';
        }
    })
    .catch(() => {
        document.getElementById('ticketResult').style.display = 'block';
        document.getElementById('ticketResult').className = 'alert alert-danger';
        document.getElementById('ticketResult').innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Ticket';
    });
}

function replyTicket(e, ticketId) {
    e.preventDefault();
    const btn = document.getElementById('replyBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    const formData = new FormData(document.getElementById('replyForm'));
    formData.append('ticket_id', ticketId);
    fetch('/user/ajax/reply_ticket.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        const result = document.getElementById('replyResult');
        result.style.display = 'block';
        if (data.success) {
            result.className = 'alert alert-success';
            result.innerHTML = '<i class="fas fa-check-circle"></i> Reply sent!';
            setTimeout(() => location.reload(), 1000);
        } else {
            result.className = 'alert alert-danger';
            result.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + (data.error || 'Failed to send reply');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-reply"></i> Send Reply';
        }
    })
    .catch(() => {
        document.getElementById('replyResult').style.display = 'block';
        document.getElementById('replyResult').className = 'alert alert-danger';
        document.getElementById('replyResult').innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-reply"></i> Send Reply';
    });
}

function closeTicket(ticketId) {
    if (!confirm('Close this ticket?')) return;
    const formData = new FormData();
    formData.append('ticket_id', ticketId);
    formData.append('csrf_token', '<?= csrf_token() ?>');
    fetch('/user/ajax/close_ticket.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        const result = document.getElementById('closeResult');
        result.style.display = 'block';
        if (data.success) {
            result.className = 'alert alert-success';
            result.innerHTML = '<i class="fas fa-check-circle"></i> Ticket closed';
            setTimeout(() => location.reload(), 1000);
        } else {
            result.className = 'alert alert-danger';
            result.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + (data.error || 'Failed to close');
        }
    });
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
