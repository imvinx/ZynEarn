<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAuth();
$user = getCurrentUser();
$pageTitle = 'Profile';
$userId = $user['id'];
$db = getDB();

$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total_earned FROM earnings WHERE user_id = ? AND status = 'credited'");
$stmt->execute([$userId]);
$totalEarned = $stmt->fetch()['total_earned'];

$stmt = $db->prepare("SELECT COUNT(*) as total_refs FROM referrals WHERE referrer_id = ?");
$stmt->execute([$userId]);
$totalRefs = $stmt->fetch()['total_refs'];

$stmt = $db->prepare("SELECT COUNT(*) as total_achievements FROM user_achievements WHERE user_id = ?");
$stmt->execute([$userId]);
$totalAchievements = $stmt->fetch()['total_achievements'];

$stmt = $db->prepare("SELECT * FROM user_sessions WHERE user_id = ? AND is_active = 1 ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$userId]);
$sessions = $stmt->fetchAll();

$stmt = $db->prepare("SELECT * FROM kyc_verifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$userId]);
$kyc = $stmt->fetch();

$stmt = $db->prepare("SELECT * FROM user_settings WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$userSettings = $stmt->fetch();

$timezones = ['UTC', 'America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles', 'Europe/London', 'Europe/Paris', 'Europe/Berlin', 'Asia/Tokyo', 'Asia/Shanghai', 'Asia/Kolkata', 'Asia/Dubai', 'Australia/Sydney', 'Pacific/Auckland'];
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<style>
.profile-grid { display: grid; grid-template-columns: 320px 1fr; gap: var(--space-6); }
.profile-sidebar { background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-lg); padding: var(--space-6); text-align: center; }
.profile-avatar-wrap { position: relative; width: 120px; height: 120px; margin: 0 auto var(--space-4); }
.profile-avatar { width: 120px; height: 120px; border-radius: var(--radius-full); object-fit: cover; border: 4px solid var(--border-primary); }
.profile-avatar-overlay { position: absolute; inset: 0; border-radius: var(--radius-full); background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; opacity: 0; transition: var(--transition); cursor: pointer; }
.profile-avatar-wrap:hover .profile-avatar-overlay { opacity: 1; }
.profile-avatar-overlay i { font-size: var(--text-2xl); color: white; }
.profile-name { font-family: var(--font-display); font-size: var(--text-xl); font-weight: var(--weight-bold); color: var(--text-primary); }
.profile-username { font-size: var(--text-sm); color: var(--text-tertiary); margin-bottom: var(--space-4); }
.profile-stats { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: var(--space-2); margin: var(--space-4) 0; padding: var(--space-4); background: var(--bg-card-hover); border-radius: var(--radius-md); }
.profile-stat { text-align: center; }
.profile-stat-value { font-family: var(--font-display); font-size: var(--text-lg); font-weight: var(--weight-bold); color: var(--text-primary); }
.profile-stat-label { font-size: 10px; color: var(--text-tertiary); text-transform: uppercase; letter-spacing: 0.05em; }
.profile-form-card { background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-lg); padding: var(--space-6); }
.section-divider { height: 1px; background: var(--border-primary); margin: var(--space-6) 0; }
.kyc-status { display: inline-flex; align-items: center; gap: var(--space-2); padding: var(--space-2) var(--space-4); border-radius: var(--radius-full); font-size: var(--text-sm); font-weight: var(--weight-semibold); }
.kyc-status.verified { background: rgba(0,184,148,0.1); color: var(--success); }
.kyc-status.pending { background: rgba(253,203,110,0.1); color: var(--warning); }
.kyc-status.unverified { background: rgba(225,112,85,0.1); color: var(--danger); }
.avatar-preview { width: 120px; height: 120px; border-radius: var(--radius-full); object-fit: cover; border: 3px solid var(--border-primary); }
@media (max-width: 992px) {
    .profile-grid { grid-template-columns: 1fr; }
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
            <a href="/user/profile.php" class="sidebar-nav-item active"><span class="sidebar-nav-icon"><i class="fas fa-user"></i></span><span class="sidebar-nav-text">Profile</span></a>
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
                <h1>My Profile</h1>
                <p>Manage your account information and preferences</p>
            </div>
            <div class="dashboard-actions">
                <div class="balance-display" onclick="toggleBalanceVisibility()">
                    <span class="balance-display-icon"><i class="fas fa-eye" id="eyeIcon"></i></span>
                    <span class="balance-display-amount" id="headerBalance"><?= formatCurrency(getUserBalance($userId)) ?></span>
                </div>
            </div>
        </div>

        <div class="profile-grid">
            <div>
                <div class="profile-sidebar">
                    <div class="profile-avatar-wrap">
                        <?php if (!empty($user['avatar'])): ?>
                        <img src="<?= sanitize($user['avatar']) ?>" alt="Avatar" class="profile-avatar" id="profileAvatar">
                        <?php else: ?>
                        <div class="profile-avatar" style="background: var(--gradient-primary); display: flex; align-items: center; justify-content: center; font-size: var(--text-4xl); color: white; font-weight: var(--weight-bold);" id="profileAvatar">
                            <?= strtoupper(substr($user['username'], 0, 1)) ?>
                        </div>
                        <?php endif; ?>
                        <div class="profile-avatar-overlay" onclick="document.getElementById('avatarInput').click()">
                            <i class="fas fa-camera"></i>
                        </div>
                    </div>
                    <input type="file" id="avatarInput" accept="image/*" style="display: none;" onchange="uploadAvatar(this)">
                    <div class="profile-name"><?= sanitize($user['full_name'] ?? $user['username']) ?></div>
                    <div class="profile-username">@<?= sanitize($user['username']) ?></div>
                    <div class="profile-stats">
                        <div class="profile-stat">
                            <div class="profile-stat-value"><?= formatCurrency($totalEarned) ?></div>
                            <div class="profile-stat-label">Earned</div>
                        </div>
                        <div class="profile-stat">
                            <div class="profile-stat-value"><?= $totalRefs ?></div>
                            <div class="profile-stat-label">Referrals</div>
                        </div>
                        <div class="profile-stat">
                            <div class="profile-stat-value"><?= $totalAchievements ?></div>
                            <div class="profile-stat-label">Awards</div>
                        </div>
                    </div>
                    <div id="kycStatus" style="margin-top: var(--space-4);">
                        <?php if ($kyc && $kyc['status'] === 'verified'): ?>
                        <span class="kyc-status verified"><i class="fas fa-check-circle"></i> KYC Verified</span>
                        <?php elseif ($kyc && $kyc['status'] === 'pending'): ?>
                        <span class="kyc-status pending"><i class="fas fa-clock"></i> KYC Pending</span>
                        <?php else: ?>
                        <span class="kyc-status unverified"><i class="fas fa-times-circle"></i> KYC Not Submitted</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="dashboard-panel" style="margin-top: var(--space-6);">
                    <div class="dashboard-panel-header">
                        <div class="dashboard-panel-title"><i class="fas fa-shield-alt"></i> KYC Verification</div>
                    </div>
                    <div style="text-align: center; padding: var(--space-4);">
                        <p style="font-size: var(--text-sm); color: var(--text-secondary); margin-bottom: var(--space-4);">Upload a government-issued ID to verify your identity and unlock higher withdrawal limits.</p>
                        <form id="kycForm" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <div class="form-group">
                                <label class="form-label">Document Type</label>
                                <select class="form-select" name="document_type" required>
                                    <option value="">Select document type</option>
                                    <option value="passport">Passport</option>
                                    <option value="drivers_license">Driver's License</option>
                                    <option value="national_id">National ID Card</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Upload Document</label>
                                <input type="file" class="form-input" name="document" accept="image/*,application/pdf" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block" onclick="submitKYC(event)"><i class="fas fa-upload"></i> Submit KYC</button>
                        </form>
                        <div id="kycResult" style="margin-top: var(--space-3); display: none;"></div>
                    </div>
                </div>
            </div>

            <div>
                <div class="profile-form-card">
                    <h3 style="font-family: var(--font-display); font-size: var(--text-lg); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-4);"><i class="fas fa-edit"></i> Edit Profile</h3>
                    <form id="profileForm" onsubmit="updateProfile(event)">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                            <div class="form-group">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-input" name="username" value="<?= sanitize($user['username']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-input" name="email" value="<?= sanitize($user['email']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-input" name="full_name" value="<?= sanitize($user['full_name'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-input" name="phone" value="<?= sanitize($user['phone'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Location</label>
                                <input type="text" class="form-input" name="location" value="<?= sanitize($user['location'] ?? '') ?>" placeholder="City, Country">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Timezone</label>
                                <select class="form-select" name="timezone">
                                    <?php foreach ($timezones as $tz): ?>
                                    <option value="<?= $tz ?>" <?= ($userSettings['timezone'] ?? 'UTC') === $tz ? 'selected' : '' ?>><?= $tz ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Bio</label>
                            <textarea class="form-input" name="bio" rows="3" placeholder="Tell us about yourself..."><?= sanitize($user['bio'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Language</label>
                            <select class="form-select" name="language">
                                <option value="en" <?= ($userSettings['language'] ?? 'en') === 'en' ? 'selected' : '' ?>>English</option>
                                <option value="es" <?= ($userSettings['language'] ?? '') === 'es' ? 'selected' : '' ?>>Spanish</option>
                                <option value="fr" <?= ($userSettings['language'] ?? '') === 'fr' ? 'selected' : '' ?>>French</option>
                                <option value="de" <?= ($userSettings['language'] ?? '') === 'de' ? 'selected' : '' ?>>German</option>
                                <option value="pt" <?= ($userSettings['language'] ?? '') === 'pt' ? 'selected' : '' ?>>Portuguese</option>
                                <option value="hi" <?= ($userSettings['language'] ?? '') === 'hi' ? 'selected' : '' ?>>Hindi</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary" id="profileBtn"><i class="fas fa-save"></i> Save Changes</button>
                    </form>
                    <div id="profileResult" style="margin-top: var(--space-3); display: none;"></div>
                </div>

                <div class="profile-form-card" style="margin-top: var(--space-6);">
                    <h3 style="font-family: var(--font-display); font-size: var(--text-lg); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-4);"><i class="fas fa-lock"></i> Change Password</h3>
                    <form id="passwordForm" onsubmit="changePassword(event)">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <div style="display: grid; grid-template-columns: 1fr; gap: var(--space-4); max-width: 400px;">
                            <div class="form-group">
                                <label class="form-label">Current Password</label>
                                <input type="password" class="form-input" name="current_password" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-input" name="new_password" required minlength="8">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" class="form-input" name="confirm_password" required minlength="8">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-warning" id="passwordBtn"><i class="fas fa-key"></i> Update Password</button>
                    </form>
                    <div id="passwordResult" style="margin-top: var(--space-3); display: none;"></div>
                </div>

                <div class="profile-form-card" style="margin-top: var(--space-6);">
                    <h3 style="font-family: var(--font-display); font-size: var(--text-lg); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-4);"><i class="fas fa-shield-alt"></i> Two-Factor Authentication</h3>
                    <div style="display: flex; align-items: center; justify-content: space-between; padding: var(--space-4); background: var(--bg-card-hover); border-radius: var(--radius-md);">
                        <div>
                            <div style="font-size: var(--text-sm); font-weight: var(--weight-medium); color: var(--text-primary);">2FA Protection</div>
                            <div style="font-size: var(--text-xs); color: var(--text-secondary);">Add an extra layer of security to your account</div>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" class="toggle-input" <?= !empty($user['2fa_enabled']) ? 'checked' : '' ?> onchange="toggle2FA(this)">
                            <span class="toggle-track"></span>
                        </label>
                    </div>
                    <div id="2faResult" style="margin-top: var(--space-3); display: none;"></div>
                </div>

                <div class="profile-form-card" style="margin-top: var(--space-6);">
                    <h3 style="font-family: var(--font-display); font-size: var(--text-lg); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-4);"><i class="fas fa-bell"></i> Notification Preferences</h3>
                    <form id="notifPrefForm" onsubmit="return false;">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                            <label class="toggle toggle-row">
                                <input type="checkbox" class="toggle-input" name="notif_earnings" <?= !isset($userSettings['notif_earnings']) || $userSettings['notif_earnings'] ? 'checked' : '' ?>>
                                <span class="toggle-track"></span>
                                <span class="toggle-label">Earnings alerts</span>
                            </label>
                            <label class="toggle toggle-row">
                                <input type="checkbox" class="toggle-input" name="notif_withdrawals" <?= !isset($userSettings['notif_withdrawals']) || $userSettings['notif_withdrawals'] ? 'checked' : '' ?>>
                                <span class="toggle-track"></span>
                                <span class="toggle-label">Withdrawal updates</span>
                            </label>
                            <label class="toggle toggle-row">
                                <input type="checkbox" class="toggle-input" name="notif_referrals" <?= !isset($userSettings['notif_referrals']) || $userSettings['notif_referrals'] ? 'checked' : '' ?>>
                                <span class="toggle-track"></span>
                                <span class="toggle-label">Referral notifications</span>
                            </label>
                            <label class="toggle toggle-row">
                                <input type="checkbox" class="toggle-input" name="notif_promotions" <?= !isset($userSettings['notif_promotions']) || $userSettings['notif_promotions'] ? 'checked' : '' ?>>
                                <span class="toggle-track"></span>
                                <span class="toggle-label">Promotions & offers</span>
                            </label>
                            <label class="toggle toggle-row">
                                <input type="checkbox" class="toggle-input" name="notif_system" <?= !isset($userSettings['notif_system']) || $userSettings['notif_system'] ? 'checked' : '' ?>>
                                <span class="toggle-track"></span>
                                <span class="toggle-label">System announcements</span>
                            </label>
                        </div>
                    </form>
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

function uploadAvatar(input) {
    if (!input.files || !input.files[0]) return;
    const formData = new FormData();
    formData.append('avatar', input.files[0]);
    formData.append('csrf_token', '<?= csrf_token() ?>');
    fetch('/user/ajax/upload_avatar.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Upload failed');
        }
    })
    .catch(() => alert('Network error'));
}

function updateProfile(e) {
    e.preventDefault();
    const btn = document.getElementById('profileBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    const formData = new FormData(document.getElementById('profileForm'));
    fetch('/user/ajax/update_profile.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        const result = document.getElementById('profileResult');
        result.style.display = 'block';
        if (data.success) {
            result.className = 'alert alert-success';
            result.innerHTML = '<i class="fas fa-check-circle"></i> ' + (data.message || 'Profile updated successfully');
            setTimeout(() => location.reload(), 1500);
        } else {
            result.className = 'alert alert-danger';
            result.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + (data.error || 'Update failed');
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
    })
    .catch(() => {
        document.getElementById('profileResult').style.display = 'block';
        document.getElementById('profileResult').className = 'alert alert-danger';
        document.getElementById('profileResult').innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
    });
}

function changePassword(e) {
    e.preventDefault();
    const newPass = document.querySelector('#passwordForm input[name="new_password"]').value;
    const confirmPass = document.querySelector('#passwordForm input[name="confirm_password"]').value;
    if (newPass !== confirmPass) {
        document.getElementById('passwordResult').style.display = 'block';
        document.getElementById('passwordResult').className = 'alert alert-danger';
        document.getElementById('passwordResult').innerHTML = '<i class="fas fa-exclamation-circle"></i> Passwords do not match';
        return;
    }
    const btn = document.getElementById('passwordBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
    const formData = new FormData(document.getElementById('passwordForm'));
    fetch('/user/ajax/change_password.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        const result = document.getElementById('passwordResult');
        result.style.display = 'block';
        if (data.success) {
            result.className = 'alert alert-success';
            result.innerHTML = '<i class="fas fa-check-circle"></i> ' + (data.message || 'Password changed successfully');
            document.getElementById('passwordForm').reset();
        } else {
            result.className = 'alert alert-danger';
            result.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + (data.error || 'Failed to change password');
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-key"></i> Update Password';
    })
    .catch(() => {
        document.getElementById('passwordResult').style.display = 'block';
        document.getElementById('passwordResult').className = 'alert alert-danger';
        document.getElementById('passwordResult').innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-key"></i> Update Password';
    });
}

function submitKYC(e) {
    e.preventDefault();
    const formData = new FormData(document.getElementById('kycForm'));
    fetch('/user/ajax/submit_kyc.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        const result = document.getElementById('kycResult');
        result.style.display = 'block';
        if (data.success) {
            result.className = 'alert alert-success';
            result.innerHTML = '<i class="fas fa-check-circle"></i> KYC submitted for review';
            setTimeout(() => location.reload(), 2000);
        } else {
            result.className = 'alert alert-danger';
            result.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + (data.error || 'KYC submission failed');
        }
    })
    .catch(() => {
        document.getElementById('kycResult').style.display = 'block';
        document.getElementById('kycResult').className = 'alert alert-danger';
        document.getElementById('kycResult').innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error';
    });
}

function toggle2FA(el) {
    const formData = new FormData();
    formData.append('enabled', el.checked ? '1' : '0');
    formData.append('csrf_token', '<?= csrf_token() ?>');
    fetch('/user/ajax/toggle_2fa.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        const result = document.getElementById('2faResult');
        result.style.display = 'block';
        if (data.success) {
            result.className = 'alert alert-success';
            result.innerHTML = '<i class="fas fa-check-circle"></i> ' + (data.message || '2FA updated');
        } else {
            el.checked = !el.checked;
            result.className = 'alert alert-danger';
            result.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + (data.error || 'Failed');
        }
        setTimeout(() => { result.style.display = 'none'; }, 3000);
    });
}

document.querySelectorAll('#notifPrefForm .toggle-input').forEach(input => {
    input.addEventListener('change', function() {
        const formData = new FormData();
        formData.append(this.name, this.checked ? '1' : '0');
        formData.append('csrf_token', '<?= csrf_token() ?>');
        fetch('/user/ajax/save_notif_prefs.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
    });
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
