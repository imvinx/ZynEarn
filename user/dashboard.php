<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAuth();
$user = getCurrentUser();
$pageTitle = 'Dashboard';

$userId = $user['id'];
$db = getDB();

// Balance data
$totalBalance = getUserBalance($userId);

$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as today_earned FROM earnings WHERE user_id = ? AND status = 'credited' AND DATE(created_at) = CURDATE()");
$stmt->execute([$userId]);
$todayEarned = $stmt->fetch()['today_earned'];

$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as lifetime_earned FROM earnings WHERE user_id = ? AND status = 'credited'");
$stmt->execute([$userId]);
$lifetimeEarned = $stmt->fetch()['lifetime_earned'];

$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as yesterday_earned FROM earnings WHERE user_id = ? AND status = 'credited' AND DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)");
$stmt->execute([$userId]);
$yesterdayEarned = $stmt->fetch()['yesterday_earned'];

$todayChange = $yesterdayEarned > 0 ? round((($todayEarned - $yesterdayEarned) / $yesterdayEarned) * 100, 1) : ($todayEarned > 0 ? 100 : 0);

// Daily bonus
$stmt = $db->prepare("SELECT * FROM daily_logins WHERE user_id = ? ORDER BY claimed_at DESC LIMIT 1");
$stmt->execute([$userId]);
$lastBonus = $stmt->fetch();
$streakDays = $user['streak_days'] ?? 0;

$bonusAmount = min(DAILY_BONUS_BASE + ($streakDays * DAILY_BONUS_INCREMENT), MAX_STREAK_BONUS);

// Chart data (last 7 days)
$chartDays = 7;
$chartLabels = [];
$chartData = [];
$chartTotal = 0;
for ($i = $chartDays - 1; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM earnings WHERE user_id = ? AND status = 'credited' AND DATE(created_at) = ?");
    $stmt->execute([$userId, $date]);
    $dayTotal = $stmt->fetch()['total'];
    $chartLabels[] = date('D', strtotime($date));
    $chartData[] = (float)$dayTotal;
    $chartTotal += $dayTotal;
}

// Recent transactions
$stmt = $db->prepare("SELECT id, type, amount, description, status, created_at FROM earnings WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$userId]);
$recentTransactions = $stmt->fetchAll();

// Referral data
$stmt = $db->prepare("SELECT COUNT(*) as total FROM referrals WHERE referrer_id = ?");
$stmt->execute([$userId]);
$totalReferrals = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as referral_earnings FROM earnings WHERE user_id = ? AND type = 'referral' AND status = 'credited'");
$stmt->execute([$userId]);
$referralEarnings = $stmt->fetch()['referral_earnings'];

$referralCode = $user['referral_code'] ?? '';
if (empty($referralCode)) {
    $referralCode = createReferralCode();
    $stmt = $db->prepare("UPDATE users SET referral_code = ? WHERE id = ?");
    $stmt->execute([$referralCode, $userId]);
}
$referralLink = APP_URL . '/auth/register.php?ref=' . $referralCode;

// Achievements
$stmt = $db->prepare("SELECT a.*, ua.unlocked_at FROM user_achievements ua JOIN achievements a ON ua.achievement_id = a.id WHERE ua.user_id = ? ORDER BY ua.unlocked_at DESC LIMIT 3");
$stmt->execute([$userId]);
$recentAchievements = $stmt->fetchAll();

$stmt = $db->prepare("SELECT COUNT(*) as total FROM user_achievements WHERE user_id = ?");
$stmt->execute([$userId]);
$totalAchievements = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM achievements");
$stmt->execute();
$totalAllAchievements = $stmt->fetch()['total'];

// Next achievement
$stmt = $db->prepare("SELECT * FROM achievements WHERE id NOT IN (SELECT achievement_id FROM user_achievements WHERE user_id = ?) ORDER BY requirement_value ASC LIMIT 1");
$stmt->execute([$userId]);
$nextAchievement = $stmt->fetch();

$nextAchievementProgress = 0;
if ($nextAchievement) {
    switch ($nextAchievement['requirement_type']) {
        case 'earnings':
            $progress = $lifetimeEarned;
            break;
        case 'referrals':
            $progress = $totalReferrals;
            break;
        case 'level':
            $progress = $user['level'];
            break;
        case 'streak':
            $progress = $streakDays;
            break;
        default:
            $progress = 0;
    }
    $nextAchievementProgress = $nextAchievement['requirement_value'] > 0 ? min(100, round(($progress / $nextAchievement['requirement_value']) * 100)) : 0;
}

// Level & XP
$currentLevel = $user['level'] ?? 1;
$currentXP = $user['xp_points'] ?? 0;
$levelThresholds = LEVEL_THRESHOLDS;
$currentLevelXP = $levelThresholds[$currentLevel] ?? 0;
$nextLevelXP = $levelThresholds[$currentLevel + 1] ?? ($currentLevelXP + 1000);
$xpProgress = $nextLevelXP > $currentLevelXP ? min(100, round((($currentXP - $currentLevelXP) / ($nextLevelXP - $currentLevelXP)) * 100)) : 0;

$levelTitles = ['Newbie', 'Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond', 'Elite', 'Master', 'Grandmaster', 'Legend', 'Mythic', 'Immortal', 'Transcendent', 'Cosmic', 'Godlike'];
$rankTitle = $levelTitles[min($currentLevel, count($levelTitles)) - 1] ?? 'Legendary';

// Daily missions
$missions = [
    ['title' => 'Complete 5 Shortlinks', 'icon' => 'fa-link', 'progress' => 0, 'target' => 5, 'reward' => 0.05, 'type' => 'shortlinks'],
    ['title' => 'Spin the Wheel', 'icon' => 'fa-circle-notch', 'progress' => 0, 'target' => 1, 'reward' => 0.02, 'type' => 'spin'],
    ['title' => 'Visit Daily Bonus', 'icon' => 'fa-gift', 'progress' => $streakDays > 0 ? 1 : 0, 'target' => 1, 'reward' => $bonusAmount, 'type' => 'daily_bonus'],
];

$missionProgress = [];

$stmt = $db->prepare("SELECT COUNT(*) as count FROM shortlink_clicks WHERE user_id = ? AND DATE(created_at) = CURDATE()");
$stmt->execute([$userId]);
$missionProgress['shortlinks'] = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM spin_results WHERE user_id = ? AND DATE(created_at) = CURDATE()");
$stmt->execute([$userId]);
$missionProgress['spin'] = $stmt->fetch()['count'] ?? 0;

$missionProgress['daily_bonus'] = $streakDays > 0 ? 1 : 0;

foreach ($missions as &$mission) {
    $mission['progress'] = $missionProgress[$mission['type']] ?? 0;
    if ($mission['progress'] >= $mission['target']) {
        $stmt = $db->prepare("SELECT id FROM daily_mission_claims WHERE user_id = ? AND mission_type = ? AND DATE(claimed_at) = CURDATE()");
        $stmt->execute([$userId, $mission['type']]);
        $mission['claimed'] = $stmt->fetch() ? true : false;
    } else {
        $mission['claimed'] = false;
    }
}
unset($mission);

// Recent activity
$stmt = $db->prepare("
    (SELECT 'earning' as action_type, type as sub_type, amount as value, description, created_at FROM earnings WHERE user_id = ? AND status = 'credited')
    UNION ALL
    (SELECT 'notification' as action_type, type as sub_type, NULL as value, message as description, created_at FROM notifications WHERE user_id = ?)
    UNION ALL
    (SELECT 'referral' as action_type, 'referral' as sub_type, NULL as value, CONCAT('New referral joined') as description, created_at FROM referrals WHERE referrer_id = ?)
    ORDER BY created_at DESC LIMIT 10
");
$stmt->execute([$userId, $userId, $userId]);
$recentActivity = $stmt->fetchAll();

// VIP data
$vipTier = $user['vip_tier'] ?? 'free';
$vipTiers = ['free' => 0, 'silver' => 1, 'gold' => 2, 'platinum' => 3, 'vip' => 4];
$vipBenefits = [
    'free' => ['Basic earnings multiplier', 'Standard withdrawal limit', 'Daily bonus access'],
    'silver' => ['1.2x earnings multiplier', 'Lower withdrawal fees', 'Priority support', 'Daily bonus +20%'],
    'gold' => ['1.5x earnings multiplier', 'No withdrawal fees', 'VIP support', 'Daily bonus +50%', 'Exclusive offers'],
    'platinum' => ['2.0x earnings multiplier', 'Instant withdrawals', '24/7 priority support', 'Daily bonus +100%', 'Exclusive offers', 'Custom rewards'],
    'vip' => ['3.0x earnings multiplier', 'Instant withdrawals (no min)', 'Dedicated account manager', 'Daily bonus +200%', 'Exclusive offers', 'Custom rewards', 'Early access to features'],
];
$currentTierBenefits = $vipBenefits[$vipTier] ?? $vipBenefits['free'];
$currentTierIndex = $vipTiers[$vipTier] ?? 0;
$nextTier = array_keys($vipTiers)[$currentTierIndex + 1] ?? null;

// Notifications
$stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();

$stmt = $db->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND `read` = 0");
$stmt->execute([$userId]);
$unreadCount = $stmt->fetch()['unread'];

// Mission reset countdown
$nextMidnight = strtotime('tomorrow midnight');
$countdownSeconds = $nextMidnight - time();
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<style>
.dashboard-wrapper {
    display: flex;
    min-height: calc(100vh - var(--header-height));
    padding-top: var(--header-height);
}
.sidebar-nav-item.logout-item {
    margin-top: auto;
    color: var(--danger);
}
.sidebar-nav-item.logout-item:hover {
    background: rgba(225, 112, 85, 0.1);
}
.main-content {
    flex: 1;
    margin-left: var(--sidebar-width);
    padding: var(--space-6);
    transition: margin-left 0.3s ease;
    min-height: calc(100vh - var(--header-height));
}
.main-content.expanded {
    margin-left: var(--sidebar-collapsed-width);
}
.dashboard-top-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: var(--space-6);
    flex-wrap: wrap;
    gap: var(--space-4);
}
.dashboard-greeting h1 {
    font-family: var(--font-display);
    font-size: var(--text-2xl);
    font-weight: var(--weight-bold);
    color: var(--text-primary);
}
.dashboard-greeting p {
    font-size: var(--text-sm);
    color: var(--text-secondary);
    margin-top: 2px;
}
.dashboard-actions {
    display: flex;
    align-items: center;
    gap: var(--space-3);
}
.balance-display {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    padding: var(--space-3) var(--space-5);
    background: var(--bg-card);
    border: 1px solid var(--border-primary);
    border-radius: var(--radius-lg);
    cursor: pointer;
    transition: var(--transition);
    user-select: none;
}
.balance-display:hover {
    border-color: var(--primary);
    background: var(--bg-card-hover);
}
.balance-display-amount {
    font-family: var(--font-display);
    font-size: var(--text-xl);
    font-weight: var(--weight-bold);
    color: var(--primary);
}
.balance-display-icon {
    color: var(--text-tertiary);
    font-size: var(--text-lg);
}
.quick-earn-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: var(--space-4);
    margin-bottom: var(--space-6);
}
.quick-earn-card {
    background: var(--card-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    padding: var(--space-5);
    text-align: center;
    transition: var(--transition);
    cursor: pointer;
    position: relative;
    overflow: hidden;
}
.quick-earn-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-md);
    border-color: var(--border-light);
}
.quick-earn-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    transition: var(--transition);
}
.quick-earn-card:hover::before {
    height: 4px;
}
.quick-earn-card.primary::before { background: var(--gradient-primary); }
.quick-earn-card.secondary::before { background: var(--gradient-secondary); }
.quick-earn-card.accent::before { background: var(--gradient-accent); }
.quick-earn-card.success::before { background: var(--gradient-success); }
.quick-earn-card.warning::before { background: var(--gradient-warning); }
.quick-earn-card.info::before { background: var(--gradient-info); }
.quick-earn-icon {
    font-size: var(--text-3xl);
    margin-bottom: var(--space-3);
    display: block;
}
.quick-earn-title {
    font-size: var(--text-sm);
    font-weight: var(--weight-semibold);
    color: var(--text-primary);
    margin-bottom: var(--space-1);
}
.quick-earn-sub {
    font-size: var(--text-xs);
    color: var(--text-secondary);
}
.quick-earn-potential {
    font-size: var(--text-xs);
    color: var(--success);
    font-weight: var(--weight-semibold);
    margin-top: var(--space-2);
    display: block;
}
.streak-indicator {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 10px;
    background: rgba(253, 203, 110, 0.15);
    color: var(--warning);
    border-radius: var(--radius-full);
    font-size: 10px;
    font-weight: var(--weight-bold);
    margin-top: var(--space-2);
}
.referral-widget {
    background: var(--card-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    padding: var(--space-6);
    position: relative;
    overflow: hidden;
}
.referral-widget::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -30%;
    width: 200px;
    height: 200px;
    background: radial-gradient(circle, rgba(108, 92, 231, 0.1) 0%, transparent 70%);
    pointer-events: none;
}
.referral-link-box {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    padding: var(--space-3) var(--space-4);
    background: var(--bg-card-hover);
    border: 2px dashed var(--border-light);
    border-radius: var(--radius-md);
    margin: var(--space-4) 0;
}
.referral-link-box input {
    flex: 1;
    background: none;
    border: none;
    color: var(--primary);
    font-family: var(--font-mono);
    font-size: var(--text-sm);
    font-weight: var(--weight-semibold);
    outline: none;
}
.referral-link-box .copy-btn {
    padding: var(--space-1) var(--space-3);
    background: var(--gradient-primary);
    color: var(--white);
    border: none;
    border-radius: var(--radius-sm);
    font-size: var(--text-xs);
    font-weight: var(--weight-semibold);
    cursor: pointer;
    transition: var(--transition);
}
.referral-link-box .copy-btn:hover {
    box-shadow: var(--shadow-glow);
}
.referral-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--space-4);
    margin-top: var(--space-4);
}
.referral-stat {
    text-align: center;
    padding: var(--space-4);
    background: var(--bg-card-hover);
    border-radius: var(--radius-md);
}
.referral-stat-value {
    font-family: var(--font-display);
    font-size: var(--text-2xl);
    font-weight: var(--weight-bold);
    color: var(--text-primary);
}
.referral-stat-label {
    font-size: var(--text-xs);
    color: var(--text-tertiary);
    margin-top: var(--space-1);
}
.achievement-progress-card {
    background: var(--card-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    padding: var(--space-6);
}
.achievement-list {
    display: flex;
    flex-direction: column;
    gap: var(--space-3);
    margin-top: var(--space-4);
}
.achievement-item {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    padding: var(--space-3);
    background: var(--bg-card-hover);
    border-radius: var(--radius-md);
    transition: var(--transition);
}
.achievement-item:hover {
    background: var(--bg-card-active);
}
.achievement-item-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-full);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--text-lg);
    flex-shrink: 0;
    background: var(--gradient-primary);
    color: var(--white);
}
.achievement-item-info {
    flex: 1;
    min-width: 0;
}
.achievement-item-name {
    font-size: var(--text-sm);
    font-weight: var(--weight-medium);
    color: var(--text-primary);
}
.achievement-item-date {
    font-size: var(--text-xs);
    color: var(--text-tertiary);
}
.level-card {
    background: var(--gradient-midnight);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    padding: var(--space-6);
    color: var(--white);
    position: relative;
    overflow: hidden;
}
.level-card::after {
    content: '';
    position: absolute;
    bottom: -30%;
    right: -20%;
    width: 150px;
    height: 150px;
    background: radial-gradient(circle, rgba(108, 92, 231, 0.2) 0%, transparent 70%);
    pointer-events: none;
}
.level-badge {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    padding: var(--space-1) var(--space-4);
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: var(--radius-full);
    font-size: var(--text-xs);
    font-weight: var(--weight-semibold);
    color: var(--white);
    margin-bottom: var(--space-4);
}
.level-number {
    font-family: var(--font-display);
    font-size: var(--text-4xl);
    font-weight: var(--weight-bold);
    margin-bottom: var(--space-1);
}
.level-rank {
    font-size: var(--text-sm);
    opacity: 0.8;
    margin-bottom: var(--space-4);
}
.level-progress-label {
    display: flex;
    justify-content: space-between;
    font-size: var(--text-xs);
    opacity: 0.8;
    margin-bottom: var(--space-2);
}
.mission-card {
    background: var(--card-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    padding: var(--space-5);
    transition: var(--transition);
}
.mission-card:hover {
    border-color: var(--border-light);
}
.mission-header {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    margin-bottom: var(--space-3);
}
.mission-icon {
    width: 36px;
    height: 36px;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--text-base);
    flex-shrink: 0;
    background: rgba(108, 92, 231, 0.15);
    color: var(--primary);
}
.mission-title {
    flex: 1;
    font-size: var(--text-sm);
    font-weight: var(--weight-medium);
    color: var(--text-primary);
}
.mission-progress {
    display: flex;
    align-items: center;
    gap: var(--space-3);
}
.mission-progress .progress {
    flex: 1;
}
.mission-count {
    font-size: var(--text-xs);
    color: var(--text-secondary);
    white-space: nowrap;
}
.mission-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: var(--space-3);
    padding-top: var(--space-3);
    border-top: 1px solid var(--border-primary);
}
.mission-reward {
    font-size: var(--text-xs);
    color: var(--success);
    font-weight: var(--weight-semibold);
}
.mission-reset {
    font-size: var(--text-xs);
    color: var(--text-tertiary);
    font-family: var(--font-mono);
}
.activity-feed {
    max-height: 360px;
    overflow-y: auto;
}
.activity-item {
    display: flex;
    align-items: flex-start;
    gap: var(--space-3);
    padding: var(--space-3) 0;
    border-bottom: 1px solid var(--border-primary);
}
.activity-item:last-child {
    border-bottom: none;
}
.activity-icon {
    width: 32px;
    height: 32px;
    border-radius: var(--radius-full);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--text-xs);
    flex-shrink: 0;
    margin-top: 2px;
}
.activity-icon-earning { background: rgba(0, 184, 148, 0.15); color: var(--success); }
.activity-icon-referral { background: rgba(108, 92, 231, 0.15); color: var(--primary); }
.activity-icon-notification { background: rgba(253, 203, 110, 0.15); color: var(--warning); }
.activity-icon-login { background: rgba(116, 185, 255, 0.15); color: var(--info); }
.activity-content {
    flex: 1;
    min-width: 0;
}
.activity-text {
    font-size: var(--text-sm);
    color: var(--text-primary);
}
.activity-text .highlight {
    color: var(--success);
    font-weight: var(--weight-semibold);
}
.activity-time {
    font-size: var(--text-xs);
    color: var(--text-tertiary);
    margin-top: 2px;
}
.vip-card {
    background: linear-gradient(135deg, #1a1a3e 0%, #2d1b69 50%, #1a1a3e 100%);
    border: 1px solid rgba(108, 92, 231, 0.3);
    border-radius: var(--radius-lg);
    padding: var(--space-6);
    color: var(--white);
    position: relative;
    overflow: hidden;
}
.vip-card::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: conic-gradient(from 0deg, transparent, rgba(108, 92, 231, 0.1), transparent, rgba(0, 206, 201, 0.05), transparent);
    animation: rotate 10s linear infinite;
    pointer-events: none;
}
.vip-badge {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    padding: var(--space-2) var(--space-4);
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: var(--radius-full);
    font-size: var(--text-sm);
    font-weight: var(--weight-semibold);
    text-transform: capitalize;
    position: relative;
    z-index: 1;
}
.vip-tier-icon { color: var(--warning); }
.vip-benefits {
    list-style: none;
    padding: 0;
    margin: var(--space-4) 0;
    position: relative;
    z-index: 1;
}
.vip-benefits li {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    padding: var(--space-2) 0;
    font-size: var(--text-sm);
    opacity: 0.9;
}
.vip-benefits li i {
    color: var(--success);
    font-size: var(--text-xs);
}
.notification-panel {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    width: 400px;
    max-width: 100%;
    background: var(--bg-modal);
    border-left: 1px solid var(--border-primary);
    box-shadow: var(--shadow-lg);
    z-index: var(--z-modal);
    transform: translateX(100%);
    transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
}
.notification-panel.active {
    transform: translateX(0);
}
.notification-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--space-5) var(--space-6);
    border-bottom: 1px solid var(--border-primary);
    flex-shrink: 0;
}
.notification-panel-title {
    font-family: var(--font-display);
    font-size: var(--text-lg);
    font-weight: var(--weight-semibold);
    color: var(--text-primary);
}
.notification-panel-close {
    width: 36px;
    height: 36px;
    border-radius: var(--radius-full);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-tertiary);
    font-size: var(--text-xl);
    cursor: pointer;
    transition: var(--transition);
    background: none;
    border: none;
}
.notification-panel-close:hover {
    background: var(--bg-card-hover);
    color: var(--text-primary);
}
.notification-list {
    flex: 1;
    overflow-y: auto;
    padding: var(--space-4) var(--space-6);
}
.notification-item {
    display: flex;
    align-items: flex-start;
    gap: var(--space-3);
    padding: var(--space-4);
    border-radius: var(--radius-md);
    transition: var(--transition);
    margin-bottom: var(--space-2);
    cursor: pointer;
}
.notification-item:hover {
    background: var(--bg-card-hover);
}
.notification-item.unread {
    background: rgba(108, 92, 231, 0.05);
    border-left: 3px solid var(--primary);
}
.notification-item-icon {
    width: 36px;
    height: 36px;
    border-radius: var(--radius-full);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--text-sm);
    flex-shrink: 0;
}
.notification-item-icon.achievement { background: rgba(0, 184, 148, 0.15); color: var(--success); }
.notification-item-icon.earning { background: rgba(108, 92, 231, 0.15); color: var(--primary); }
.notification-item-icon.system { background: rgba(253, 203, 110, 0.15); color: var(--warning); }
.notification-item-icon.referral { background: rgba(116, 185, 255, 0.15); color: var(--info); }
.notification-item-content {
    flex: 1;
    min-width: 0;
}
.notification-item-title {
    font-size: var(--text-sm);
    font-weight: var(--weight-medium);
    color: var(--text-primary);
}
.notification-item-message {
    font-size: var(--text-xs);
    color: var(--text-secondary);
    margin-top: 2px;
}
.notification-item-time {
    font-size: 10px;
    color: var(--text-tertiary);
    margin-top: var(--space-1);
}
.notification-panel-footer {
    padding: var(--space-4) var(--space-6);
    border-top: 1px solid var(--border-primary);
    display: flex;
    gap: var(--space-3);
    flex-shrink: 0;
}
.notification-panel-footer .btn {
    flex: 1;
}
.notification-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: var(--overlay);
    z-index: calc(var(--z-modal) - 1);
    opacity: 0;
    visibility: hidden;
    transition: var(--transition);
}
.notification-overlay.active {
    opacity: 1;
    visibility: visible;
}
.two-col-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--space-6);
    margin-bottom: var(--space-6);
}
.three-col-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: var(--space-6);
    margin-bottom: var(--space-6);
}
.chart-container {
    position: relative;
    width: 100%;
    height: 280px;
}
.chart-controls {
    display: flex;
    gap: var(--space-2);
}
.chart-controls .btn {
    padding: var(--space-1) var(--space-3);
    font-size: var(--text-xs);
}
.chart-total {
    font-family: var(--font-display);
    font-size: var(--text-2xl);
    font-weight: var(--weight-bold);
    color: var(--text-primary);
}
.chart-total-label {
    font-size: var(--text-xs);
    color: var(--text-tertiary);
}
.balance-cards-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: var(--space-5);
    margin-bottom: var(--space-6);
}
.balance-card {
    border-radius: var(--radius-lg);
    padding: var(--space-5);
    color: var(--white);
    position: relative;
    overflow: hidden;
    transition: var(--transition);
}
.balance-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
}
.balance-card.primary {
    background: linear-gradient(135deg, #6C5CE7 0%, #A29BFE 100%);
}
.balance-card.success {
    background: linear-gradient(135deg, #00B894 0%, #55EFC4 100%);
}
.balance-card.accent {
    background: linear-gradient(135deg, #FD79A8 0%, #E84393 100%);
}
.balance-card::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -30%;
    width: 150px;
    height: 150px;
    border-radius: var(--radius-full);
    background: rgba(255, 255, 255, 0.1);
    pointer-events: none;
}
.balance-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: var(--space-3);
    position: relative;
    z-index: 1;
}
.balance-card-label {
    font-size: var(--text-sm);
    opacity: 0.9;
    font-weight: var(--weight-medium);
}
.balance-card-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-md);
    background: rgba(255, 255, 255, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--text-lg);
}
.balance-card-amount {
    font-family: var(--font-display);
    font-size: var(--text-3xl);
    font-weight: var(--weight-bold);
    position: relative;
    z-index: 1;
    line-height: 1.2;
}
.balance-card-change {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 10px;
    border-radius: var(--radius-full);
    font-size: var(--text-xs);
    font-weight: var(--weight-semibold);
    margin-top: var(--space-2);
    position: relative;
    z-index: 1;
    background: rgba(255, 255, 255, 0.2);
}
@keyframes countUp {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.count-animate {
    animation: countUp 0.6s ease forwards;
}
@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
@media (max-width: 1200px) {
    .balance-cards-row { grid-template-columns: repeat(2, 1fr); }
    .two-col-grid { grid-template-columns: 1fr; }
    .three-col-grid { grid-template-columns: 1fr; }
}
@media (max-width: 992px) {
    .balance-cards-row { grid-template-columns: 1fr; }
    .main-content { margin-left: 0; padding: var(--space-4); }
    .sidebar { transform: translateX(-100%); }
    .sidebar.mobile-open { transform: translateX(0); }
    .sidebar-overlay.active { display: block; }
    .bottom-nav { display: block; }
    .main-content { padding-bottom: calc(var(--bottom-nav-height) + var(--space-6)); }
}
@media (max-width: 768px) {
    .dashboard-top-header { flex-direction: column; align-items: flex-start; }
    .dashboard-actions { width: 100%; justify-content: space-between; }
    .quick-earn-grid { grid-template-columns: repeat(3, 1fr); }
    .notification-panel { width: 100%; }
    .dashboard-greeting h1 { font-size: var(--text-xl); }
    .balance-card-amount { font-size: var(--text-2xl); }
}
@media (max-width: 480px) {
    .quick-earn-grid { grid-template-columns: repeat(2, 1fr); }
    .referral-stats { grid-template-columns: 1fr; }
    .balance-display-amount { font-size: var(--text-lg); }
}
</style>

<div class="dashboard-wrapper">
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-section">
            <div class="sidebar-section-title">Main Menu</div>
            <a href="/user/dashboard.php" class="sidebar-nav-item active">
                <span class="sidebar-nav-icon"><i class="fas fa-home"></i></span>
                <span class="sidebar-nav-text">Dashboard</span>
            </a>
            <a href="/user/wallet.php" class="sidebar-nav-item">
                <span class="sidebar-nav-icon"><i class="fas fa-wallet"></i></span>
                <span class="sidebar-nav-text">Wallet</span>
            </a>
            <a href="/user/earnings.php" class="sidebar-nav-item">
                <span class="sidebar-nav-icon"><i class="fas fa-chart-line"></i></span>
                <span class="sidebar-nav-text">Earnings</span>
            </a>
            <a href="/user/offers.php" class="sidebar-nav-item">
                <span class="sidebar-nav-icon"><i class="fas fa-th"></i></span>
                <span class="sidebar-nav-text">Offers</span>
            </a>
            <a href="/user/tasks.php" class="sidebar-nav-item">
                <span class="sidebar-nav-icon"><i class="fas fa-check-circle"></i></span>
                <span class="sidebar-nav-text">Tasks</span>
            </a>
            <a href="/user/quizzes.php" class="sidebar-nav-item">
                <span class="sidebar-nav-icon"><i class="fas fa-brain"></i></span>
                <span class="sidebar-nav-text">Quizzes</span>
            </a>
            <a href="/user/games.php" class="sidebar-nav-item">
                <span class="sidebar-nav-icon"><i class="fas fa-gamepad"></i></span>
                <span class="sidebar-nav-text">Games</span>
            </a>
            <a href="/user/referrals.php" class="sidebar-nav-item">
                <span class="sidebar-nav-icon"><i class="fas fa-users"></i></span>
                <span class="sidebar-nav-text">Referrals</span>
            </a>
            <a href="/user/withdraw.php" class="sidebar-nav-item">
                <span class="sidebar-nav-icon"><i class="fas fa-cash-register"></i></span>
                <span class="sidebar-nav-text">Withdraw</span>
            </a>
            <a href="/user/deposit.php" class="sidebar-nav-item">
                <span class="sidebar-nav-icon"><i class="fas fa-plus-circle"></i></span>
                <span class="sidebar-nav-text">Deposit</span>
            </a>
        </div>
        <div class="sidebar-section">
            <div class="sidebar-section-title">Account</div>
            <a href="/user/profile.php" class="sidebar-nav-item">
                <span class="sidebar-nav-icon"><i class="fas fa-user"></i></span>
                <span class="sidebar-nav-text">Profile</span>
            </a>
            <a href="/user/settings.php" class="sidebar-nav-item">
                <span class="sidebar-nav-icon"><i class="fas fa-cog"></i></span>
                <span class="sidebar-nav-text">Settings</span>
            </a>
            <a href="/user/support.php" class="sidebar-nav-item">
                <span class="sidebar-nav-icon"><i class="fas fa-headset"></i></span>
                <span class="sidebar-nav-text">Support</span>
            </a>
            <a href="/auth/logout.php" class="sidebar-nav-item logout-item">
                <span class="sidebar-nav-icon"><i class="fas fa-sign-out-alt"></i></span>
                <span class="sidebar-nav-text">Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <!-- Top Header -->
        <div class="dashboard-top-header">
            <div class="dashboard-greeting">
                <h1>Welcome back, <?= sanitize(explode(' ', $user['full_name'] ?? $user['username'])[0]) ?>!</h1>
                <p>Here's your earnings overview for today</p>
            </div>
            <div class="dashboard-actions">
                <div class="balance-display" onclick="toggleBalanceVisibility()" title="Toggle balance visibility">
                    <span class="balance-display-icon"><i class="fas fa-eye" id="eyeIcon"></i></span>
                    <span class="balance-display-amount" id="headerBalance"><?= formatCurrency($totalBalance) ?></span>
                </div>
                <button class="notification-btn" onclick="toggleNotificationPanel()">
                    <i class="fas fa-bell"></i>
                    <?php if ($unreadCount > 0): ?>
                    <span class="notification-badge" id="notifBadge"><?= $unreadCount ?></span>
                    <?php endif; ?>
                </button>
                <div class="dropdown">
                    <button class="dropdown-toggle" onclick="this.nextElementSibling.classList.toggle('active')">
                        <div class="avatar avatar-sm" style="background: var(--gradient-primary);">
                            <?= strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)) ?>
                        </div>
                    </button>
                    <div class="dropdown-menu">
                        <div class="dropdown-header"><?= sanitize($user['full_name'] ?? $user['username']) ?></div>
                        <a href="/user/profile.php" class="dropdown-item">
                            <span class="dropdown-item-icon"><i class="fas fa-user"></i></span>
                            Profile
                        </a>
                        <a href="/user/settings.php" class="dropdown-item">
                            <span class="dropdown-item-icon"><i class="fas fa-cog"></i></span>
                            Settings
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="/auth/logout.php" class="dropdown-item dropdown-item-danger">
                            <span class="dropdown-item-icon"><i class="fas fa-sign-out-alt"></i></span>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Balance Cards Row -->
        <div class="balance-cards-row">
            <div class="balance-card primary">
                <div class="balance-card-header">
                    <span class="balance-card-label">Total Balance</span>
                    <span class="balance-card-icon"><i class="fas fa-wallet"></i></span>
                </div>
                <div class="balance-card-amount"><?= formatCurrency($totalBalance) ?></div>
                <?php if ($todayChange != 0): ?>
                <span class="balance-card-change <?= $todayChange >= 0 ? '' : '' ?>">
                    <i class="fas fa-<?= $todayChange >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                    <?= $todayChange >= 0 ? '+' : '' ?><?= $todayChange ?>%
                </span>
                <?php endif; ?>
            </div>
            <div class="balance-card success">
                <div class="balance-card-header">
                    <span class="balance-card-label">Today's Earnings</span>
                    <span class="balance-card-icon"><i class="fas fa-clock"></i></span>
                </div>
                <div class="balance-card-amount" id="todayEarnings"><?= formatCurrency($todayEarned) ?></div>
                <?php if ($todayEarned > 0): ?>
                <span class="balance-card-change"><i class="fas fa-arrow-up"></i> Active</span>
                <?php endif; ?>
            </div>
            <div class="balance-card accent">
                <div class="balance-card-header">
                    <span class="balance-card-label">Lifetime Earnings</span>
                    <span class="balance-card-icon"><i class="fas fa-trophy"></i></span>
                </div>
                <div class="balance-card-amount"><?= formatCurrency($lifetimeEarned) ?></div>
                <span class="balance-card-change"><i class="fas fa-check-circle"></i> All Time</span>
            </div>
        </div>

        <!-- Two Column Layout -->
        <div class="two-col-grid">
            <!-- Left Column -->
            <div>
                <!-- Earnings Chart -->
                <div class="dashboard-panel">
                    <div class="dashboard-panel-header">
                        <div>
                            <div class="dashboard-panel-title">Earnings Overview</div>
                            <div class="chart-total-label">Total for period: <span class="chart-total" id="chartTotal"><?= formatCurrency($chartTotal) ?></span></div>
                        </div>
                        <div class="chart-controls" id="chartControls">
                            <button class="btn btn-sm btn-primary" data-days="7" onclick="loadChart(7)">7d</button>
                            <button class="btn btn-sm btn-outline-light" data-days="30" onclick="loadChart(30)">30d</button>
                            <button class="btn btn-sm btn-outline-light" data-days="90" onclick="loadChart(90)">90d</button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="earningsChart"></canvas>
                    </div>
                </div>

                <!-- Quick Earn Grid -->
                <div class="dashboard-panel" style="margin-top: var(--space-6);">
                    <div class="dashboard-panel-header">
                        <div class="dashboard-panel-title">Quick Earn</div>
                        <a href="/user/earnings.php" class="dashboard-panel-action">View All</a>
                    </div>
                    <div class="quick-earn-grid">
                        <div class="quick-earn-card primary" onclick="window.location.href='/user/daily-bonus.php'">
                            <span class="quick-earn-icon"><i class="fas fa-gift" style="color: var(--primary);"></i></span>
                            <div class="quick-earn-title">Daily Bonus</div>
                            <div class="quick-earn-sub">Claim your reward</div>
                            <span class="quick-earn-potential">Up to <?= formatCurrency(MAX_STREAK_BONUS) ?></span>
                            <?php if ($streakDays > 0): ?>
                            <div class="streak-indicator"><i class="fas fa-fire"></i> <?= $streakDays ?> day streak</div>
                            <?php endif; ?>
                        </div>
                        <div class="quick-earn-card secondary" onclick="window.location.href='/user/offers.php'">
                            <span class="quick-earn-icon"><i class="fas fa-clipboard-list" style="color: var(--secondary);"></i></span>
                            <div class="quick-earn-title">Offerwall</div>
                            <div class="quick-earn-sub">Complete offers</div>
                            <span class="quick-earn-potential">Up to $5.00</span>
                        </div>
                        <div class="quick-earn-card accent" onclick="window.location.href='/user/spin.php'">
                            <span class="quick-earn-icon"><i class="fas fa-circle-notch" style="color: var(--accent);"></i></span>
                            <div class="quick-earn-title">Spin Wheel</div>
                            <div class="quick-earn-sub">Try your luck</div>
                            <span class="quick-earn-potential">Up to $1.00</span>
                        </div>
                        <div class="quick-earn-card success" onclick="window.location.href='/user/shortlinks.php'">
                            <span class="quick-earn-icon"><i class="fas fa-link" style="color: var(--success);"></i></span>
                            <div class="quick-earn-title">Shortlinks</div>
                            <div class="quick-earn-sub">Visit & earn</div>
                            <span class="quick-earn-potential"><?= formatCurrency(SHORTLINK_BASE_PAYOUT) ?> each</span>
                        </div>
                        <div class="quick-earn-card warning" onclick="window.location.href='/user/faucet.php'">
                            <span class="quick-earn-icon"><i class="fas fa-water" style="color: var(--warning);"></i></span>
                            <div class="quick-earn-title">Faucet</div>
                            <div class="quick-earn-sub">Free rewards</div>
                            <span class="quick-earn-potential">Up to <?= formatCurrency(FAUCET_MAX_REWARD) ?></span>
                        </div>
                        <div class="quick-earn-card info" onclick="window.location.href='/user/referrals.php'">
                            <span class="quick-earn-icon"><i class="fas fa-share-alt" style="color: var(--info);"></i></span>
                            <div class="quick-earn-title">Refer & Earn</div>
                            <div class="quick-earn-sub">Invite friends</div>
                            <span class="quick-earn-potential">Unlimited</span>
                        </div>
                    </div>
                </div>

                <!-- Recent Transactions -->
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
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge badge-<?= $tx['type'] === 'referral' ? 'info' : ($tx['type'] === 'bonus' ? 'warning' : 'primary') ?> badge-sm">
                                                <?= ucfirst(str_replace('_', ' ', $tx['type'])) ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td><span class="text-mono" style="color: var(--success); font-weight: var(--weight-semibold);">+<?= formatCurrency($tx['amount']) ?></span></td>
                                    <td><span class="text-mono" style="font-size: var(--text-xs); color: var(--text-tertiary);"><?= date('M d, H:i', strtotime($tx['created_at'])) ?></span></td>
                                    <td>
                                        <span class="badge badge-<?= $tx['status'] === 'credited' ? 'success' : ($tx['status'] === 'pending' ? 'warning' : 'danger') ?> badge-sm">
                                            <?= ucfirst($tx['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="4">
                                        <div class="table-empty">
                                            <div class="table-empty-icon"><i class="fas fa-inbox"></i></div>
                                            <p>No transactions yet. Start earning!</p>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Level & XP Card -->
                <div class="level-card">
                    <div class="level-badge">
                        <i class="fas fa-crown"></i>
                        Level <?= $currentLevel ?>
                    </div>
                    <div class="level-number"><?= $currentLevel ?></div>
                    <div class="level-rank"><?= $rankTitle ?></div>
                    <div class="level-progress-label">
                        <span><?= formatNumber($currentXP) ?> XP</span>
                        <span><?= formatNumber($nextLevelXP) ?> XP</span>
                    </div>
                    <div class="progress progress-lg" style="background: rgba(255,255,255,0.15);">
                        <div class="progress-bar" style="width: <?= $xpProgress ?>%;"></div>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: var(--text-xs); opacity: 0.7; margin-top: var(--space-2);">
                        <span>Level <?= $currentLevel ?></span>
                        <span>Level <?= $currentLevel + 1 ?></span>
                    </div>
                </div>

                <!-- Daily Missions -->
                <div class="dashboard-panel" style="margin-top: var(--space-6);">
                    <div class="dashboard-panel-header">
                        <div class="dashboard-panel-title">Daily Missions</div>
                        <span style="font-size: var(--text-xs); color: var(--text-tertiary); font-family: var(--font-mono);" id="missionTimer">
                            Resets in <span id="countdownDisplay"><?= gmdate('H:i:s', $countdownSeconds) ?></span>
                        </span>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: var(--space-4);">
                        <?php foreach ($missions as $mission): ?>
                        <div class="mission-card">
                            <div class="mission-header">
                                <div class="mission-icon"><i class="fas <?= $mission['icon'] ?>"></i></div>
                                <div class="mission-title"><?= $mission['title'] ?></div>
                            </div>
                            <div class="mission-progress">
                                <div class="progress">
                                    <div class="progress-bar <?= $mission['progress'] >= $mission['target'] ? 'progress-bar-success' : '' ?>" style="width: <?= min(100, round(($mission['progress'] / $mission['target']) * 100)) ?>%;"></div>
                                </div>
                                <span class="mission-count"><?= $mission['progress'] ?>/<?= $mission['target'] ?></span>
                            </div>
                            <div class="mission-footer">
                                <span class="mission-reward"><i class="fas fa-coins"></i> <?= formatCurrency($mission['reward']) ?></span>
                                <?php if ($mission['progress'] >= $mission['target'] && !$mission['claimed']): ?>
                                <button class="btn btn-sm btn-success claim-mission-btn" data-type="<?= $mission['type'] ?>" onclick="claimMission(this)">Claim</button>
                                <?php elseif ($mission['claimed']): ?>
                                <span class="badge badge-success badge-sm"><i class="fas fa-check"></i> Claimed</span>
                                <?php else: ?>
                                <span class="badge badge-neutral badge-sm">In Progress</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Referral Widget -->
                <div class="dashboard-panel" style="margin-top: var(--space-6);">
                    <div class="dashboard-panel-header">
                        <div class="dashboard-panel-title">
                            <i class="fas fa-users" style="color: var(--primary);"></i>
                            Referral Program
                        </div>
                        <a href="/user/referrals.php" class="dashboard-panel-action">Manage</a>
                    </div>
                    <p style="font-size: var(--text-sm); color: var(--text-secondary); margin-bottom: var(--space-3);">Share your referral link and earn rewards for every friend who joins!</p>
                    <div class="referral-link-box">
                        <input type="text" id="referralLink" value="<?= $referralLink ?>" readonly>
                        <button class="copy-btn" onclick="copyReferralLink()"><i class="fas fa-copy"></i> Copy</button>
                    </div>
                    <div class="referral-stats">
                        <div class="referral-stat">
                            <div class="referral-stat-value"><?= $totalReferrals ?></div>
                            <div class="referral-stat-label">Total Referrals</div>
                        </div>
                        <div class="referral-stat">
                            <div class="referral-stat-value"><?= formatCurrency($referralEarnings) ?></div>
                            <div class="referral-stat-label">Referral Earnings</div>
                        </div>
                    </div>
                    <button class="btn btn-primary btn-block btn-sm" onclick="shareReferral()" style="margin-top: var(--space-4);">
                        <i class="fas fa-share-alt"></i> Invite More Friends
                    </button>
                </div>

                <!-- Achievements Progress -->
                <div class="dashboard-panel" style="margin-top: var(--space-6);">
                    <div class="dashboard-panel-header">
                        <div class="dashboard-panel-title">
                            <i class="fas fa-trophy" style="color: var(--warning);"></i>
                            Achievements
                        </div>
                        <span class="badge badge-warning badge-sm"><?= $totalAchievements ?>/<?= $totalAllAchievements ?> Unlocked</span>
                    </div>
                    <?php if (count($recentAchievements) > 0): ?>
                    <div class="achievement-list">
                        <?php foreach ($recentAchievements as $ach): ?>
                        <div class="achievement-item">
                            <div class="achievement-item-icon"><i class="fas fa-star"></i></div>
                            <div class="achievement-item-info">
                                <div class="achievement-item-name"><?= sanitize($ach['name']) ?></div>
                                <div class="achievement-item-date"><?= timeAgo($ach['unlocked_at']) ?></div>
                            </div>
                            <?php if ($ach['reward_amount'] > 0): ?>
                            <span class="badge badge-success badge-sm">+<?= formatCurrency($ach['reward_amount']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div style="text-align: center; padding: var(--space-6) 0; color: var(--text-tertiary);">
                        <i class="fas fa-star" style="font-size: var(--text-4xl); opacity: 0.3; margin-bottom: var(--space-3); display: block;"></i>
                        <p style="font-size: var(--text-sm);">No achievements unlocked yet.<br>Keep earning to unlock your first!</p>
                    </div>
                    <?php endif; ?>
                    <?php if ($nextAchievement): ?>
                    <div style="margin-top: var(--space-4); padding-top: var(--space-4); border-top: 1px solid var(--border-primary);">
                        <div class="progress-label">
                            <span style="font-size: var(--text-xs); color: var(--text-secondary);">Next: <?= sanitize($nextAchievement['name']) ?></span>
                            <span style="font-size: var(--text-xs); color: var(--text-secondary);"><?= $nextAchievementProgress ?>%</span>
                        </div>
                        <div class="progress progress-sm">
                            <div class="progress-bar progress-bar-warning" style="width: <?= $nextAchievementProgress ?>%;"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- VIP Membership Card -->
                <div class="vip-card" style="margin-top: var(--space-6);">
                    <div style="position: relative; z-index: 1;">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="vip-badge">
                                <i class="fas fa-crown vip-tier-icon"></i>
                                <?= ucfirst($vipTier) ?> Member
                            </div>
                            <?php if ($nextTier): ?>
                            <span class="badge badge-gradient badge-sm">Upgrade to <?= ucfirst($nextTier) ?></span>
                            <?php endif; ?>
                        </div>
                        <ul class="vip-benefits">
                            <?php foreach ($currentTierBenefits as $benefit): ?>
                            <li><i class="fas fa-check-circle"></i> <?= $benefit ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if ($nextTier): ?>
                        <button class="btn btn-primary btn-block btn-sm" onclick="window.location.href='/user/vip.php'">
                            <i class="fas fa-arrow-up"></i> Upgrade to <?= ucfirst($nextTier) ?> — <?= formatCurrency(VIP_MEMBERSHIP_COSTS[$nextTier] ?? 0) ?>
                        </button>
                        <?php else: ?>
                        <div class="badge badge-gradient" style="display: block; text-align: center; padding: var(--space-3); font-size: var(--text-sm);">
                            <i class="fas fa-check-circle"></i> Maximum Tier Reached
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Activity Feed -->
                <div class="dashboard-panel" style="margin-top: var(--space-6);">
                    <div class="dashboard-panel-header">
                        <div class="dashboard-panel-title">
                            <i class="fas fa-history"></i>
                            Recent Activity
                        </div>
                    </div>
                    <div class="activity-feed">
                        <?php if (count($recentActivity) > 0): ?>
                        <?php foreach ($recentActivity as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon activity-icon-<?= $activity['action_type'] === 'earning' ? 'earning' : ($activity['action_type'] === 'referral' ? 'referral' : 'notification') ?>">
                                <i class="fas fa-<?= $activity['action_type'] === 'earning' ? 'coins' : ($activity['action_type'] === 'referral' ? 'user-plus' : 'bell') ?>"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text">
                                    <?php if ($activity['action_type'] === 'earning'): ?>
                                    Earned <span class="highlight">+<?= formatCurrency($activity['value']) ?></span> from <?= ucfirst(str_replace('_', ' ', $activity['sub_type'])) ?>
                                    <?php else: ?>
                                    <?= sanitize($activity['description']) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="activity-time"><?= timeAgo($activity['created_at']) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <div style="text-align: center; padding: var(--space-8); color: var(--text-tertiary);">
                            <i class="fas fa-clock" style="font-size: var(--text-4xl); opacity: 0.3; margin-bottom: var(--space-3); display: block;"></i>
                            <p style="font-size: var(--text-sm);">No recent activity</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer padding for bottom nav on mobile -->
        <div class="bottom-nav-spacer" style="height: 0;"></div>
    </main>
</div>

<!-- Notification Panel -->
<div class="notification-overlay" id="notifOverlay" onclick="toggleNotificationPanel()"></div>
<div class="notification-panel" id="notifPanel">
    <div class="notification-panel-header">
        <div class="notification-panel-title">
            <i class="fas fa-bell"></i> Notifications
            <?php if ($unreadCount > 0): ?>
            <span class="badge badge-danger badge-sm ml-2"><?= $unreadCount ?> new</span>
            <?php endif; ?>
        </div>
        <button class="notification-panel-close" onclick="toggleNotificationPanel()"><i class="fas fa-times"></i></button>
    </div>
    <div class="notification-list" id="notificationList">
        <?php if (count($notifications) > 0): ?>
        <?php foreach ($notifications as $notif): ?>
        <div class="notification-item <?= $notif['is_read'] ? '' : 'unread' ?>" data-id="<?= $notif['id'] ?>" onclick="markNotificationRead(<?= $notif['id'] ?>)">
            <div class="notification-item-icon <?= $notif['type'] ?>">
                <i class="fas fa-<?= $notif['type'] === 'achievement' ? 'trophy' : ($notif['type'] === 'earning' ? 'coins' : ($notif['type'] === 'referral' ? 'user-plus' : 'info-circle')) ?>"></i>
            </div>
            <div class="notification-item-content">
                <div class="notification-item-title"><?= sanitize($notif['title']) ?></div>
                <div class="notification-item-message"><?= sanitize($notif['message']) ?></div>
                <div class="notification-item-time"><?= timeAgo($notif['created_at']) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div style="text-align: center; padding: var(--space-12); color: var(--text-tertiary);">
            <i class="fas fa-bell-slash" style="font-size: var(--text-5xl); opacity: 0.3; margin-bottom: var(--space-4); display: block;"></i>
            <p style="font-size: var(--text-sm);">No notifications yet</p>
        </div>
        <?php endif; ?>
    </div>
    <?php if (count($notifications) > 0): ?>
    <div class="notification-panel-footer">
        <button class="btn btn-outline-light btn-sm" onclick="markAllRead()"><i class="fas fa-check-double"></i> Mark All Read</button>
        <button class="btn btn-ghost btn-sm" style="color: var(--danger);" onclick="clearNotifications()"><i class="fas fa-trash"></i> Clear All</button>
    </div>
    <?php endif; ?>
</div>

<!-- Bottom Navigation (Mobile) -->
<nav class="bottom-nav">
    <div class="bottom-nav-inner">
        <a href="/user/dashboard.php" class="bottom-nav-item active">
            <span class="bottom-nav-icon"><i class="fas fa-home"></i></span>
            <span class="bottom-nav-label">Home</span>
        </a>
        <a href="/user/earnings.php" class="bottom-nav-item">
            <span class="bottom-nav-icon"><i class="fas fa-chart-line"></i></span>
            <span class="bottom-nav-label">Earnings</span>
        </a>
        <a href="/user/wallet.php" class="bottom-nav-item">
            <span class="bottom-nav-icon"><i class="fas fa-wallet"></i></span>
            <span class="bottom-nav-label">Wallet</span>
        </a>
        <a href="/user/referrals.php" class="bottom-nav-item">
            <span class="bottom-nav-icon"><i class="fas fa-users"></i></span>
            <span class="bottom-nav-label">Referrals</span>
        </a>
        <a href="/user/profile.php" class="bottom-nav-item">
            <span class="bottom-nav-icon"><i class="fas fa-user"></i></span>
            <span class="bottom-nav-label">Profile</span>
        </a>
    </div>
</nav>

<script>
// Chart variables
let earningsChart = null;

// Initialize chart on load
document.addEventListener('DOMContentLoaded', function() {
    loadChart(7);
    startBalancePolling();
    startMissionTimer();
});

function loadChart(days) {
    const controls = document.querySelectorAll('#chartControls .btn');
    controls.forEach(btn => {
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-outline-light');
        if (parseInt(btn.dataset.days) === days) {
            btn.classList.remove('btn-outline-light');
            btn.classList.add('btn-primary');
        }
    });

    fetch('/user/ajax/chart_data.php?days=' + days, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            renderChart(data.labels, data.values);
            document.getElementById('chartTotal').textContent = '<?= APP_CURRENCY_SYMBOL ?>' + data.total.toFixed(2);
        }
    })
    .catch(() => {
        // Fallback to initial PHP data for 7 days
        if (days === 7) {
            renderChart(<?= json_encode($chartLabels) ?>, <?= json_encode($chartData) ?>);
        }
    });
}

function renderChart(labels, values) {
    const ctx = document.getElementById('earningsChart').getContext('2d');

    if (earningsChart) {
        earningsChart.destroy();
    }

    const isDark = document.body.getAttribute('data-theme') !== 'light';
    const textColor = isDark ? '#8888aa' : '#666688';
    const gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';

    earningsChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Earnings',
                data: values,
                borderColor: '#6C5CE7',
                backgroundColor: function(context) {
                    const gradient = ctx.createLinearGradient(0, 0, 0, context.chart.height);
                    gradient.addColorStop(0, 'rgba(108, 92, 231, 0.3)');
                    gradient.addColorStop(1, 'rgba(108, 92, 231, 0.0)');
                    return gradient;
                },
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#6C5CE7',
                pointBorderColor: isDark ? '#0a0a1a' : '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                borderWidth: 3
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
                    ticks: { color: textColor, font: { size: 11 } }
                },
                y: {
                    grid: { color: gridColor, drawBorder: false },
                    ticks: {
                        color: textColor,
                        font: { size: 11 },
                        callback: function(value) {
                            return '<?= APP_CURRENCY_SYMBOL ?>' + value.toFixed(2);
                        }
                    },
                    beginAtZero: true
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });
}

// Balance visibility toggle
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

// Notification panel
function toggleNotificationPanel() {
    document.getElementById('notifPanel').classList.toggle('active');
    document.getElementById('notifOverlay').classList.toggle('active');
}

function markNotificationRead(id) {
    fetch('/user/ajax/mark_notification_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'id=' + id + '&csrf_token=<?= csrf_token() ?>'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const item = document.querySelector('.notification-item[data-id="' + id + '"]');
            if (item) {
                item.classList.remove('unread');
            }
            updateUnreadCount();
        }
    });
}

function markAllRead() {
    fetch('/user/ajax/mark_all_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'csrf_token=<?= csrf_token() ?>'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.querySelectorAll('.notification-item.unread').forEach(el => el.classList.remove('unread'));
            updateUnreadCount();
        }
    });
}

function clearNotifications() {
    if (!confirm('Clear all notifications?')) return;
    fetch('/user/ajax/clear_notifications.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'csrf_token=<?= csrf_token() ?>'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('notificationList').innerHTML =
                '<div style="text-align: center; padding: var(--space-12); color: var(--text-tertiary);">' +
                '<i class="fas fa-bell-slash" style="font-size: var(--text-5xl); opacity: 0.3; margin-bottom: var(--space-4); display: block;"></i>' +
                '<p style="font-size: var(--text-sm);">No notifications yet</p></div>';
            updateUnreadCount();
            document.querySelector('.notification-panel-footer')?.remove();
        }
    });
}

function updateUnreadCount() {
    fetch('/user/ajax/unread_count.php', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        const badge = document.getElementById('notifBadge');
        if (badge) {
            if (data.count > 0) {
                badge.textContent = data.count;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }
    });
}

// Copy referral link
function copyReferralLink() {
    const input = document.getElementById('referralLink');
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).then(() => {
        const btn = input.nextElementSibling;
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        setTimeout(() => { btn.innerHTML = orig; }, 2000);
    });
}

function shareReferral() {
    const link = document.getElementById('referralLink').value;
    if (navigator.share) {
        navigator.share({ title: 'Join ZynEarn', text: 'Earn money with me on ZynEarn!', url: link });
    } else {
        copyReferralLink();
    }
}

// Claim mission
function claimMission(btn) {
    const type = btn.dataset.type;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    fetch('/user/ajax/claim_mission.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'type=' + type + '&csrf_token=<?= csrf_token() ?>'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            btn.className = 'badge badge-success badge-sm';
            btn.innerHTML = '<i class="fas fa-check"></i> Claimed';
            if (data.balance_update) {
                updateBalanceDisplay();
            }
        } else {
            btn.disabled = false;
            btn.innerHTML = 'Claim';
            alert(data.error || 'Failed to claim reward');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = 'Claim';
    });
}

// Mission countdown timer
function startMissionTimer() {
    const el = document.getElementById('countdownDisplay');
    if (!el) return;
    let seconds = <?= $countdownSeconds ?>;

    setInterval(() => {
        seconds--;
        if (seconds <= 0) {
            seconds = 86400;
            location.reload();
        }
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        el.textContent =
            String(h).padStart(2, '0') + ':' +
            String(m).padStart(2, '0') + ':' +
            String(s).padStart(2, '0');
    }, 1000);
}

// Balance polling (every 30 seconds)
function startBalancePolling() {
    setInterval(() => {
        fetch('/user/ajax/balance.php', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (balanceVisible) {
                    document.getElementById('headerBalance').textContent = data.formatted_balance;
                }
                document.getElementById('todayEarnings').textContent = data.formatted_today;
            }
        });
    }, 30000);
}

function updateBalanceDisplay() {
    fetch('/user/ajax/balance.php', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && balanceVisible) {
            document.getElementById('headerBalance').textContent = data.formatted_balance;
            document.getElementById('todayEarnings').textContent = data.formatted_today;
        }
    });
}

// Sidebar toggle for mobile
document.addEventListener('DOMContentLoaded', function() {
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    sidebarOverlay.addEventListener('click', function() {
        document.getElementById('sidebar').classList.remove('mobile-open');
        sidebarOverlay.classList.remove('active');
    });

    // Close dropdowns on outside click
    document.addEventListener('click', function(e) {
        document.querySelectorAll('.dropdown-menu.active').forEach(menu => {
            if (!menu.parentElement.contains(e.target)) {
                menu.classList.remove('active');
            }
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
