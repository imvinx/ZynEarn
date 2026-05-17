<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAuth();
$user = getCurrentUser();
$pageTitle = 'Settings';
$userId = $user['id'];
$db = getDB();

$stmt = $db->prepare("SELECT * FROM user_settings WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$settings = $stmt->fetch();

$stmt = $db->prepare("SELECT * FROM user_sessions WHERE user_id = ? AND is_active = 1 ORDER BY last_activity DESC");
$stmt->execute([$userId]);
$sessions = $stmt->fetchAll();

$currentSessionToken = $_SESSION['session_token'] ?? '';

$timezones = ['UTC', 'America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles', 'Europe/London', 'Europe/Paris', 'Europe/Berlin', 'Asia/Tokyo', 'Asia/Shanghai', 'Asia/Kolkata', 'Asia/Dubai', 'Australia/Sydney', 'Pacific/Auckland'];
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<style>
.settings-tabs { display: flex; gap: var(--space-1); margin-bottom: var(--space-6); border-bottom: 1px solid var(--border-primary); padding-bottom: 0; overflow-x: auto; }
.settings-tab { padding: var(--space-3) var(--space-5); font-size: var(--text-sm); font-weight: var(--weight-medium); color: var(--text-secondary); border: none; background: none; cursor: pointer; transition: var(--transition); border-bottom: 2px solid transparent; white-space: nowrap; }
.settings-tab:hover { color: var(--text-primary); }
.settings-tab.active { color: var(--primary); border-bottom-color: var(--primary); }
.settings-panel { display: none; }
.settings-panel.active { display: block; }
.settings-section { background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-lg); padding: var(--space-6); margin-bottom: var(--space-6); }
.settings-section-title { font-family: var(--font-display); font-size: var(--text-lg); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-4); }
.settings-row { display: flex; align-items: center; justify-content: space-between; padding: var(--space-4) 0; border-bottom: 1px solid var(--border-primary); }
.settings-row:last-child { border-bottom: none; }
.settings-row-info { flex: 1; }
.settings-row-label { font-size: var(--text-sm); font-weight: var(--weight-medium); color: var(--text-primary); }
.settings-row-desc { font-size: var(--text-xs); color: var(--text-secondary); margin-top: 2px; }
.session-card { display: flex; align-items: center; gap: var(--space-4); padding: var(--space-4); background: var(--bg-card-hover); border-radius: var(--radius-md); margin-bottom: var(--space-3); }
.session-card.current { border: 1px solid var(--primary); }
.session-icon { width: 40px; height: 40px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; background: rgba(108,92,231,0.15); color: var(--primary); font-size: var(--text-lg); flex-shrink: 0; }
.session-info { flex: 1; }
.session-device { font-size: var(--text-sm); font-weight: var(--weight-medium); color: var(--text-primary); }
.session-details { font-size: var(--text-xs); color: var(--text-secondary); }
.session-time { font-size: 10px; color: var(--text-tertiary); margin-top: 2px; }
.color-schemes { display: flex; gap: var(--space-3); flex-wrap: wrap; }
.color-scheme { width: 40px; height: 40px; border-radius: var(--radius-full); cursor: pointer; border: 3px solid transparent; transition: var(--transition); }
.color-scheme:hover { transform: scale(1.1); }
.color-scheme.active { border-color: var(--primary); }
.theme-option { display: flex; align-items: center; gap: var(--space-3); padding: var(--space-3) var(--space-4); border-radius: var(--radius-md); cursor: pointer; transition: var(--transition); border: 2px solid transparent; }
.theme-option:hover { background: var(--bg-card-hover); }
.theme-option.active { border-color: var(--primary); background: rgba(108,92,231,0.05); }
.theme-option-icon { font-size: var(--text-2xl); }
@media (max-width: 768px) { .settings-tabs { gap: 0; } .settings-tab { padding: var(--space-3) var(--space-3); font-size: var(--text-xs); } }
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
            <a href="/user/settings.php" class="sidebar-nav-item active"><span class="sidebar-nav-icon"><i class="fas fa-cog"></i></span><span class="sidebar-nav-text">Settings</span></a>
            <a href="/user/notifications.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-bell"></i></span><span class="sidebar-nav-text">Notifications</span></a>
            <a href="/user/leaderboard.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-trophy"></i></span><span class="sidebar-nav-text">Leaderboard</span></a>
            <a href="/user/support.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-headset"></i></span><span class="sidebar-nav-text">Support</span></a>
            <a href="/auth/logout.php" class="sidebar-nav-item logout-item"><span class="sidebar-nav-icon"><i class="fas fa-sign-out-alt"></i></span><span class="sidebar-nav-text">Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <div class="dashboard-top-header">
            <div class="dashboard-greeting">
                <h1>Settings</h1>
                <p>Customize your account experience</p>
            </div>
            <div class="dashboard-actions">
                <div class="balance-display" onclick="toggleBalanceVisibility()">
                    <span class="balance-display-icon"><i class="fas fa-eye" id="eyeIcon"></i></span>
                    <span class="balance-display-amount" id="headerBalance"><?= formatCurrency(getUserBalance($userId)) ?></span>
                </div>
            </div>
        </div>

        <div class="settings-tabs" id="settingsTabs">
            <button class="settings-tab active" data-tab="account" onclick="switchTab(this, 'account')"><i class="fas fa-sliders-h"></i> Account</button>
            <button class="settings-tab" data-tab="notifications" onclick="switchTab(this, 'notifications')"><i class="fas fa-bell"></i> Notifications</button>
            <button class="settings-tab" data-tab="privacy" onclick="switchTab(this, 'privacy')"><i class="fas fa-lock"></i> Privacy</button>
            <button class="settings-tab" data-tab="appearance" onclick="switchTab(this, 'appearance')"><i class="fas fa-palette"></i> Appearance</button>
            <button class="settings-tab" data-tab="sessions" onclick="switchTab(this, 'sessions')"><i class="fas fa-laptop"></i> Sessions</button>
        </div>

        <div class="settings-panel active" id="panel-account">
            <div class="settings-section">
                <div class="settings-section-title"><i class="fas fa-envelope"></i> Email Preferences</div>
                <form id="accountSettingsForm">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="section" value="account">
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-input" name="email" value="<?= sanitize($user['email']) ?>">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                        <div class="form-group">
                            <label class="form-label">Language</label>
                            <select class="form-select" name="language">
                                <option value="en" <?= ($settings['language'] ?? 'en') === 'en' ? 'selected' : '' ?>>English</option>
                                <option value="es" <?= ($settings['language'] ?? '') === 'es' ? 'selected' : '' ?>>Spanish</option>
                                <option value="fr" <?= ($settings['language'] ?? '') === 'fr' ? 'selected' : '' ?>>French</option>
                                <option value="de" <?= ($settings['language'] ?? '') === 'de' ? 'selected' : '' ?>>German</option>
                                <option value="pt" <?= ($settings['language'] ?? '') === 'pt' ? 'selected' : '' ?>>Portuguese</option>
                                <option value="hi" <?= ($settings['language'] ?? '') === 'hi' ? 'selected' : '' ?>>Hindi</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Timezone</label>
                            <select class="form-select" name="timezone">
                                <?php foreach ($timezones as $tz): ?>
                                <option value="<?= $tz ?>" <?= ($settings['timezone'] ?? 'UTC') === $tz ? 'selected' : '' ?>><?= $tz ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" onclick="saveSettings(event)"><i class="fas fa-save"></i> Save Account Settings</button>
                </form>
                <div id="accountResult" style="margin-top: var(--space-3); display: none;"></div>
            </div>
        </div>

        <div class="settings-panel" id="panel-notifications">
            <div class="settings-section">
                <div class="settings-section-title"><i class="fas fa-bell"></i> Notification Preferences</div>
                <p style="font-size: var(--text-sm); color: var(--text-secondary); margin-bottom: var(--space-4);">Choose which notifications you want to receive.</p>
                <form id="notifSettingsForm">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="section" value="notifications">
                    <div class="settings-row">
                        <div class="settings-row-info">
                            <div class="settings-row-label">Earnings</div>
                            <div class="settings-row-desc">When you earn from tasks, offers, or surveys</div>
                        </div>
                        <label class="toggle"><input type="checkbox" class="toggle-input" name="notif_earnings" <?= (!isset($settings['notif_earnings']) || $settings['notif_earnings']) ? 'checked' : '' ?>><span class="toggle-track"></span></label>
                    </div>
                    <div class="settings-row">
                        <div class="settings-row-info">
                            <div class="settings-row-label">Withdrawals</div>
                            <div class="settings-row-desc">Status updates on your withdrawal requests</div>
                        </div>
                        <label class="toggle"><input type="checkbox" class="toggle-input" name="notif_withdrawals" <?= (!isset($settings['notif_withdrawals']) || $settings['notif_withdrawals']) ? 'checked' : '' ?>><span class="toggle-track"></span></label>
                    </div>
                    <div class="settings-row">
                        <div class="settings-row-info">
                            <div class="settings-row-label">Referrals</div>
                            <div class="settings-row-desc">When someone joins using your referral link</div>
                        </div>
                        <label class="toggle"><input type="checkbox" class="toggle-input" name="notif_referrals" <?= (!isset($settings['notif_referrals']) || $settings['notif_referrals']) ? 'checked' : '' ?>><span class="toggle-track"></span></label>
                    </div>
                    <div class="settings-row">
                        <div class="settings-row-info">
                            <div class="settings-row-label">Promotions</div>
                            <div class="settings-row-desc">Special offers, bonuses, and promotional events</div>
                        </div>
                        <label class="toggle"><input type="checkbox" class="toggle-input" name="notif_promotions" <?= (!isset($settings['notif_promotions']) || $settings['notif_promotions']) ? 'checked' : '' ?>><span class="toggle-track"></span></label>
                    </div>
                    <div class="settings-row">
                        <div class="settings-row-info">
                            <div class="settings-row-label">System</div>
                            <div class="settings-row-desc">Maintenance updates and system announcements</div>
                        </div>
                        <label class="toggle"><input type="checkbox" class="toggle-input" name="notif_system" <?= (!isset($settings['notif_system']) || $settings['notif_system']) ? 'checked' : '' ?>><span class="toggle-track"></span></label>
                    </div>
                    <button type="submit" class="btn btn-primary" onclick="saveSettings(event)"><i class="fas fa-save"></i> Save Notification Settings</button>
                </form>
                <div id="notifResult" style="margin-top: var(--space-3); display: none;"></div>
            </div>
        </div>

        <div class="settings-panel" id="panel-privacy">
            <div class="settings-section">
                <div class="settings-section-title"><i class="fas fa-lock"></i> Privacy Settings</div>
                <form id="privacySettingsForm">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="section" value="privacy">
                    <div class="settings-row">
                        <div class="settings-row-info">
                            <div class="settings-row-label">Profile Visibility</div>
                            <div class="settings-row-desc">Make your profile visible to other users on leaderboard</div>
                        </div>
                        <label class="toggle"><input type="checkbox" class="toggle-input" name="profile_visible" <?= (!isset($settings['profile_visible']) || $settings['profile_visible']) ? 'checked' : '' ?>><span class="toggle-track"></span></label>
                    </div>
                    <div class="settings-row">
                        <div class="settings-row-info">
                            <div class="settings-row-label">Online Status</div>
                            <div class="settings-row-desc">Show when you are online to other users</div>
                        </div>
                        <label class="toggle"><input type="checkbox" class="toggle-input" name="online_status" <?= (!isset($settings['online_status']) || $settings['online_status']) ? 'checked' : '' ?>><span class="toggle-track"></span></label>
                    </div>
                    <div class="settings-row">
                        <div class="settings-row-info">
                            <div class="settings-row-label">Show Earnings</div>
                            <div class="settings-row-desc">Display your total earnings on your public profile</div>
                        </div>
                        <label class="toggle"><input type="checkbox" class="toggle-input" name="show_earnings" <?= (!isset($settings['show_earnings']) || $settings['show_earnings']) ? 'checked' : '' ?>><span class="toggle-track"></span></label>
                    </div>
                    <button type="submit" class="btn btn-primary" onclick="saveSettings(event)"><i class="fas fa-save"></i> Save Privacy Settings</button>
                </form>
                <div id="privacyResult" style="margin-top: var(--space-3); display: none;"></div>
            </div>
        </div>

        <div class="settings-panel" id="panel-appearance">
            <div class="settings-section">
                <div class="settings-section-title"><i class="fas fa-palette"></i> Theme</div>
                <p style="font-size: var(--text-sm); color: var(--text-secondary); margin-bottom: var(--space-4);">Choose your preferred theme appearance.</p>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-4); margin-bottom: var(--space-6);">
                    <div class="theme-option <?= (!isset($settings['theme']) || $settings['theme'] === 'dark') ? 'active' : '' ?>" onclick="setTheme('dark', this)">
                        <div class="theme-option-icon"><i class="fas fa-moon" style="color: var(--primary);"></i></div>
                        <div>
                            <div style="font-size: var(--text-sm); font-weight: var(--weight-medium); color: var(--text-primary);">Dark</div>
                            <div style="font-size: var(--text-xs); color: var(--text-secondary);">Default dark mode</div>
                        </div>
                    </div>
                    <div class="theme-option <?= ($settings['theme'] ?? '') === 'light' ? 'active' : '' ?>" onclick="setTheme('light', this)">
                        <div class="theme-option-icon"><i class="fas fa-sun" style="color: var(--warning);"></i></div>
                        <div>
                            <div style="font-size: var(--text-sm); font-weight: var(--weight-medium); color: var(--text-primary);">Light</div>
                            <div style="font-size: var(--text-xs); color: var(--text-secondary);">Bright and clean</div>
                        </div>
                    </div>
                    <div class="theme-option <?= ($settings['theme'] ?? '') === 'system' ? 'active' : '' ?>" onclick="setTheme('system', this)">
                        <div class="theme-option-icon"><i class="fas fa-desktop" style="color: var(--secondary);"></i></div>
                        <div>
                            <div style="font-size: var(--text-sm); font-weight: var(--weight-medium); color: var(--text-primary);">System</div>
                            <div style="font-size: var(--text-xs); color: var(--text-secondary);">Follows device theme</div>
                        </div>
                    </div>
                </div>
                <div class="settings-section-title" style="margin-top: var(--space-6);"><i class="fas fa-paint-brush"></i> Color Scheme</div>
                <p style="font-size: var(--text-sm); color: var(--text-secondary); margin-bottom: var(--space-4);">Choose your accent color.</p>
                <div class="color-schemes">
                    <div class="color-scheme <?= (!isset($settings['color_scheme']) || $settings['color_scheme'] === 'purple') ? 'active' : '' ?>" style="background: #6C5CE7;" onclick="setColorScheme('purple', this)"></div>
                    <div class="color-scheme <?= ($settings['color_scheme'] ?? '') === 'blue' ? 'active' : '' ?>" style="background: #3498DB;" onclick="setColorScheme('blue', this)"></div>
                    <div class="color-scheme <?= ($settings['color_scheme'] ?? '') === 'green' ? 'active' : '' ?>" style="background: #00B894;" onclick="setColorScheme('green', this)"></div>
                    <div class="color-scheme <?= ($settings['color_scheme'] ?? '') === 'red' ? 'active' : '' ?>" style="background: #E17055;" onclick="setColorScheme('red', this)"></div>
                    <div class="color-scheme <?= ($settings['color_scheme'] ?? '') === 'pink' ? 'active' : '' ?>" style="background: #FD79A8;" onclick="setColorScheme('pink', this)"></div>
                    <div class="color-scheme <?= ($settings['color_scheme'] ?? '') === 'teal' ? 'active' : '' ?>" style="background: #00CEC9;" onclick="setColorScheme('teal', this)"></div>
                </div>
                <div id="appearanceResult" style="margin-top: var(--space-3); display: none;"></div>
            </div>
        </div>

        <div class="settings-panel" id="panel-sessions">
            <div class="settings-section">
                <div class="settings-section-title"><i class="fas fa-laptop"></i> Active Sessions</div>
                <p style="font-size: var(--text-sm); color: var(--text-secondary); margin-bottom: var(--space-4);">These are the devices currently logged into your account.</p>
                <div id="sessionsList">
                    <?php if (count($sessions) > 0): ?>
                    <?php foreach ($sessions as $s): ?>
                    <?php $isCurrent = $s['session_token'] === $currentSessionToken; ?>
                    <div class="session-card <?= $isCurrent ? 'current' : '' ?>" data-session="<?= sanitize($s['session_token']) ?>">
                        <div class="session-icon"><i class="fas fa-<?= $s['device_type'] === 'mobile' ? 'mobile-alt' : ($s['device_type'] === 'tablet' ? 'tablet-alt' : 'laptop') ?>"></i></div>
                        <div class="session-info">
                            <div class="session-device"><?= sanitize($s['device_type'] ?? 'Unknown') ?> <?= $isCurrent ? '<span class="badge badge-primary badge-sm">Current</span>' : '' ?></div>
                            <div class="session-details"><?= sanitize($s['ip_address'] ?? 'Unknown IP') ?> &middot; <?= sanitize(substr($s['user_agent'] ?? '', 0, 60)) ?>...</div>
                            <div class="session-time">Last active: <?= timeAgo($s['last_activity'] ?? $s['created_at']) ?></div>
                        </div>
                        <?php if (!$isCurrent): ?>
                        <button class="btn btn-sm btn-ghost" style="color: var(--danger);" onclick="terminateSession(this, '<?= sanitize($s['session_token']) ?>')"><i class="fas fa-times"></i></button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div style="text-align: center; padding: var(--space-8); color: var(--text-tertiary);">
                        <i class="fas fa-laptop" style="font-size: var(--text-4xl); opacity: 0.3; margin-bottom: var(--space-3); display: block;"></i>
                        <p style="font-size: var(--text-sm);">No active sessions.</p>
                    </div>
                    <?php endif; ?>
                </div>
                <div id="sessionResult" style="margin-top: var(--space-3); display: none;"></div>
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
        el.textContent = '\u2022\u2022\u2022\u2022\u2022\u2022';
        icon.className = 'fas fa-eye-slash';
    }
}

function switchTab(el, tab) {
    document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.settings-panel').forEach(p => p.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('panel-' + tab).classList.add('active');
}

function saveSettings(e) {
    e.preventDefault();
    const form = e.target;
    const btn = form.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    const formData = new FormData(form);
    fetch('/user/ajax/save_settings.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        const section = form.querySelector('input[name="section"]').value;
        const resultId = section + 'Result';
        const result = document.getElementById(resultId);
        result.style.display = 'block';
        if (data.success) {
            result.className = 'alert alert-success';
            result.innerHTML = '<i class="fas fa-check-circle"></i> ' + (data.message || 'Settings saved');
        } else {
            result.className = 'alert alert-danger';
            result.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + (data.error || 'Failed to save');
        }
        btn.disabled = false;
        btn.innerHTML = originalText;
        setTimeout(() => { result.style.display = 'none'; }, 3000);
    })
    .catch(() => {
        const section = form.querySelector('input[name="section"]').value;
        const result = document.getElementById(section + 'Result');
        result.style.display = 'block';
        result.className = 'alert alert-danger';
        result.innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error';
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

function setTheme(theme, el) {
    document.querySelectorAll('.theme-option').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    document.body.setAttribute('data-theme', theme);
    const formData = new FormData();
    formData.append('section', 'appearance');
    formData.append('theme', theme);
    formData.append('csrf_token', '<?= csrf_token() ?>');
    fetch('/user/ajax/save_settings.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        const result = document.getElementById('appearanceResult');
        result.style.display = 'block';
        result.className = data.success ? 'alert alert-success' : 'alert alert-danger';
        result.innerHTML = data.success ? '<i class="fas fa-check-circle"></i> Theme updated' : '<i class="fas fa-exclamation-circle"></i> ' + (data.error || 'Failed');
        setTimeout(() => { result.style.display = 'none'; }, 2000);
    });
}

function setColorScheme(scheme, el) {
    document.querySelectorAll('.color-scheme').forEach(c => c.classList.remove('active'));
    el.classList.add('active');
    const formData = new FormData();
    formData.append('section', 'appearance');
    formData.append('color_scheme', scheme);
    formData.append('csrf_token', '<?= csrf_token() ?>');
    fetch('/user/ajax/save_settings.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        const result = document.getElementById('appearanceResult');
        result.style.display = 'block';
        result.className = data.success ? 'alert alert-success' : 'alert alert-danger';
        result.innerHTML = data.success ? '<i class="fas fa-check-circle"></i> Color scheme updated' : '<i class="fas fa-exclamation-circle"></i> Failed';
        setTimeout(() => { result.style.display = 'none'; }, 2000);
    });
}

function terminateSession(btn, token) {
    if (!confirm('Terminate this session?')) return;
    btn.disabled = true;
    const formData = new FormData();
    formData.append('session_token', token);
    formData.append('csrf_token', '<?= csrf_token() ?>');
    fetch('/user/ajax/terminate_session.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        const result = document.getElementById('sessionResult');
        result.style.display = 'block';
        if (data.success) {
            result.className = 'alert alert-success';
            result.innerHTML = '<i class="fas fa-check-circle"></i> Session terminated';
            btn.closest('.session-card').remove();
        } else {
            result.className = 'alert alert-danger';
            result.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + (data.error || 'Failed');
            btn.disabled = false;
        }
        setTimeout(() => { result.style.display = 'none'; }, 3000);
    })
    .catch(() => {
        document.getElementById('sessionResult').style.display = 'block';
        document.getElementById('sessionResult').className = 'alert alert-danger';
        document.getElementById('sessionResult').innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error';
        btn.disabled = false;
    });
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>

