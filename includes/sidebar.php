<?php
$currentPage = basename($_SERVER['SCRIPT_NAME'], '.php');

$navItems = [
    'dashboard' => ['label' => 'Dashboard', 'icon' => 'fas fa-home', 'color' => '#6C5CE7', 'file' => 'dashboard'],
    'wallet' => ['label' => 'Wallet', 'icon' => 'fas fa-wallet', 'color' => '#00B894', 'file' => 'wallet'],
    'earnings' => ['label' => 'Earnings', 'icon' => 'fas fa-coins', 'color' => '#FDCB6E', 'file' => 'earnings'],
    'offers' => ['label' => 'Offers', 'icon' => 'fas fa-tag', 'color' => '#E17055', 'file' => 'offers'],
    'tasks' => ['label' => 'Tasks', 'icon' => 'fas fa-tasks', 'color' => '#0984E3', 'file' => 'tasks'],
    'quizzes' => ['label' => 'Quizzes', 'icon' => 'fas fa-question-circle', 'color' => '#A29BFE', 'file' => 'quizzes'],
    'scratch-cards' => ['label' => 'Scratch Cards', 'icon' => 'fas fa-gift', 'color' => '#FD79A8', 'file' => 'scratch-cards'],
    'spin-wheel' => ['label' => 'Spin Wheel', 'icon' => 'fas fa-circle-notch', 'color' => '#6C5CE7', 'file' => 'spin-wheel'],
    'shortlinks' => ['label' => 'Shortlinks', 'icon' => 'fas fa-link', 'color' => '#00CEC9', 'file' => 'shortlinks'],
    'faucet' => ['label' => 'Faucet', 'icon' => 'fas fa-tint', 'color' => '#74B9FF', 'file' => 'faucet'],
    'videos' => ['label' => 'Videos', 'icon' => 'fas fa-video', 'color' => '#E17055', 'file' => 'videos'],
    'surveys' => ['label' => 'Surveys', 'icon' => 'fas fa-clipboard-list', 'color' => '#00B894', 'file' => 'surveys'],
    'daily-rewards' => ['label' => 'Daily Rewards', 'icon' => 'fas fa-calendar-day', 'color' => '#FDCB6E', 'file' => 'daily-rewards'],
    'achievements' => ['label' => 'Achievements', 'icon' => 'fas fa-trophy', 'color' => '#FFEAA7', 'file' => 'achievements'],
    'missions' => ['label' => 'Missions', 'icon' => 'fas fa-flag', 'color' => '#DFE6E9', 'file' => 'missions'],
    'referrals' => ['label' => 'Referrals', 'icon' => 'fas fa-users', 'color' => '#A29BFE', 'file' => 'referrals'],
    'leaderboard' => ['label' => 'Leaderboard', 'icon' => 'fas fa-chart-bar', 'color' => '#FAB1A0', 'file' => 'leaderboard'],
    'withdraw' => ['label' => 'Withdraw', 'icon' => 'fas fa-arrow-up', 'color' => '#55EFC4', 'file' => 'withdraw'],
    'deposit' => ['label' => 'Deposit', 'icon' => 'fas fa-arrow-down', 'color' => '#81ECEC', 'file' => 'deposit'],
    'profile' => ['label' => 'Profile', 'icon' => 'fas fa-user-cog', 'color' => '#74B9FF', 'file' => 'profile'],
    'settings' => ['label' => 'Settings', 'icon' => 'fas fa-cog', 'color' => '#636E72', 'file' => 'settings'],
    'notifications' => ['label' => 'Notifications', 'icon' => 'fas fa-bell', 'color' => '#E17055', 'file' => 'notifications'],
    'support' => ['label' => 'Support', 'icon' => 'fas fa-headset', 'color' => '#00CEC9', 'file' => 'support']
];

$collapsibleSections = [
    'earn' => ['label' => 'Earn Money', 'icon' => 'fas fa-dollar-sign', 'items' => ['offers', 'tasks', 'surveys', 'quizzes', 'shortlinks', 'faucet', 'videos']],
    'fun' => ['label' => 'Fun & Rewards', 'icon' => 'fas fa-gamepad', 'items' => ['scratch-cards', 'spin-wheel', 'daily-rewards', 'achievements', 'missions']],
    'finance' => ['label' => 'Finance', 'icon' => 'fas fa-chart-line', 'items' => ['wallet', 'earnings', 'deposit', 'withdraw']]
];

function isActive($file): string {
    global $currentPage;
    return $currentPage === $file ? 'active' : '';
}

function isSectionOpen($items): bool {
    global $currentPage;
    foreach ($items as $key) {
        if ($currentPage === $key) return true;
    }
    return false;
}

$userAvatar = $_SESSION['user']['avatar'] ?? '/zyn-earn/assets/images/default-avatar.png';
$userName = $_SESSION['user']['username'] ?? 'User';
$userEmail = $_SESSION['user']['email'] ?? '';
$userBalance = number_format($_SESSION['user']['balance'] ?? 0.00, 2);
$unreadNotifications = $_SESSION['unread_notifications'] ?? 0;
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="/zyn-earn/dashboard.php" class="sidebar-logo">
            <img src="/zyn-earn/assets/images/logo-icon.png" alt="ZynEarn" class="logo-icon">
            <span class="logo-text">Zyn<span class="text-primary">Earn</span></span>
        </a>
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar-wrapper">
            <img src="<?php echo htmlspecialchars($userAvatar); ?>" alt="<?php echo htmlspecialchars($userName); ?>" class="user-avatar" loading="lazy">
            <span class="online-indicator"></span>
        </div>
        <div class="user-info">
            <h4 class="user-name"><?php echo htmlspecialchars($userName); ?></h4>
            <span class="user-email"><?php echo htmlspecialchars($userEmail); ?></span>
        </div>
        <div class="user-balance">
            <span class="balance-label">Balance</span>
            <span class="balance-amount" data-balance="<?php echo $userBalance; ?>">$<?php echo $userBalance; ?></span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav-list">
            <?php foreach (['dashboard'] as $key): $item = $navItems[$key]; ?>
            <li class="nav-item <?php echo isActive($key); ?>">
                <a href="/zyn-earn/<?php echo $item['file']; ?>.php" class="nav-link" data-tooltip="<?php echo $item['label']; ?>">
                    <span class="nav-icon" style="color: <?php echo $item['color']; ?>"><i class="<?php echo $item['icon']; ?>"></i></span>
                    <span class="nav-label"><?php echo $item['label']; ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>

        <?php foreach ($collapsibleSections as $sectionKey => $section): ?>
        <div class="nav-section <?php echo isSectionOpen($section['items']) ? 'open' : ''; ?>">
            <button class="nav-section-toggle" data-section="<?php echo $sectionKey; ?>">
                <span class="section-icon" style="color: <?php echo $navItems[$section['items'][0]]['color'] ?? '#6C5CE7'; ?>"><i class="<?php echo $section['icon']; ?>"></i></span>
                <span class="section-label"><?php echo $section['label']; ?></span>
                <i class="fas fa-chevron-down section-arrow"></i>
            </button>
            <ul class="nav-section-items">
                <?php foreach ($section['items'] as $key): $item = $navItems[$key]; ?>
                <li class="nav-item <?php echo isActive($key); ?>">
                    <a href="/zyn-earn/<?php echo $item['file']; ?>.php" class="nav-link" data-tooltip="<?php echo $item['label']; ?>">
                        <span class="nav-icon" style="color: <?php echo $item['color']; ?>"><i class="<?php echo $item['icon']; ?>"></i></span>
                        <span class="nav-label"><?php echo $item['label']; ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endforeach; ?>

        <ul class="nav-list nav-bottom">
            <li class="nav-item <?php echo isActive('notifications'); ?>">
                <a href="/zyn-earn/notifications.php" class="nav-link" data-tooltip="Notifications">
                    <span class="nav-icon" style="color: #E17055"><i class="fas fa-bell"></i></span>
                    <span class="nav-label">Notifications</span>
                    <?php if ($unreadNotifications > 0): ?>
                    <span class="nav-badge" id="notificationBadge"><?php echo $unreadNotifications > 99 ? '99+' : $unreadNotifications; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item <?php echo isActive('support'); ?>">
                <a href="/zyn-earn/support.php" class="nav-link" data-tooltip="Support">
                    <span class="nav-icon" style="color: #00CEC9"><i class="fas fa-headset"></i></span>
                    <span class="nav-label">Support</span>
                </a>
            </li>
            <li class="nav-item <?php echo isActive('profile'); ?>">
                <a href="/zyn-earn/profile.php" class="nav-link" data-tooltip="Profile">
                    <span class="nav-icon" style="color: #74B9FF"><i class="fas fa-user-cog"></i></span>
                    <span class="nav-label">Profile</span>
                </a>
            </li>
            <li class="nav-item <?php echo isActive('settings'); ?>">
                <a href="/zyn-earn/settings.php" class="nav-link" data-tooltip="Settings">
                    <span class="nav-icon" style="color: #636E72"><i class="fas fa-cog"></i></span>
                    <span class="nav-label">Settings</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <a href="/zyn-earn/logout.php" class="btn-logout" id="logoutBtn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<nav class="bottom-nav" id="bottomNav">
    <a href="/zyn-earn/dashboard.php" class="bottom-nav-item <?php echo isActive('dashboard'); ?>">
        <i class="fas fa-home"></i>
        <span>Home</span>
    </a>
    <a href="/zyn-earn/earnings.php" class="bottom-nav-item <?php echo isActive('earnings'); ?>">
        <i class="fas fa-coins"></i>
        <span>Earnings</span>
    </a>
    <a href="/zyn-earn/tasks.php" class="bottom-nav-item <?php echo isActive('tasks'); ?>">
        <i class="fas fa-tasks"></i>
        <span>Tasks</span>
    </a>
    <a href="/zyn-earn/withdraw.php" class="bottom-nav-item <?php echo isActive('withdraw'); ?>">
        <i class="fas fa-arrow-up"></i>
        <span>Withdraw</span>
    </a>
    <a href="/zyn-earn/profile.php" class="bottom-nav-item <?php echo isActive('profile'); ?>">
        <i class="fas fa-user"></i>
        <span>Profile</span>
    </a>
</nav>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const navSectionToggles = document.querySelectorAll('.nav-section-toggle');

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('mobile-open');
            sidebarOverlay.classList.remove('active');
        });
    }

    navSectionToggles.forEach(function(toggle) {
        toggle.addEventListener('click', function() {
            const section = this.closest('.nav-section');
            section.classList.toggle('open');
            localStorage.setItem('nav_section_' + this.dataset.section, section.classList.contains('open'));
        });
    });

    const savedStates = ['earn', 'fun', 'finance'];
    savedStates.forEach(function(key) {
        const state = localStorage.getItem('nav_section_' + key);
        if (state !== null) {
            const section = document.querySelector('.nav-section-toggle[data-section="' + key + '"]');
            if (section) {
                const parent = section.closest('.nav-section');
                if (state === 'true') {
                    parent.classList.add('open');
                } else {
                    parent.classList.remove('open');
                }
            }
        }
    });

    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to logout?')) {
                e.preventDefault();
            }
        });
    }

    if (window.innerWidth <= 768) {
        sidebar.classList.add('collapsed');
    }
});

window.addEventListener('resize', function() {
    const sidebar = document.getElementById('sidebar');
    if (window.innerWidth <= 768) {
        sidebar.classList.add('collapsed');
    }
});
</script>
