<?php
require_once __DIR__ . '/../../includes/auth_middleware.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please log in to get recommendations.']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $db = getDB();

    $stmt = $db->prepare("
        SELECT type, COUNT(*) as count, COALESCE(SUM(amount), 0) as total
        FROM earnings
        WHERE user_id = ? AND status = 'credited'
        GROUP BY type
        ORDER BY total DESC
    ");
    $stmt->execute([$userId]);
    $earnings = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT membership_tier FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    $tier = $user['membership_tier'] ?? 'free';

    $stmt = $db->prepare("SELECT COUNT(*) FROM referrals WHERE referrer_id = ?");
    $stmt->execute([$userId]);
    $referralCount = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT xp_points, level FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $xpData = $stmt->fetch();
    $xpPoints = $xpData['xp_points'] ?? 0;
    $level = $xpData['level'] ?? 1;

    $stmt = $db->prepare("SELECT COUNT(*) FROM user_achievements WHERE user_id = ?");
    $stmt->execute([$userId]);
    $achievementCount = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT streak_days FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $streakDays = (int)($stmt->fetchColumn() ?: 0);

    $recommendations = [];
    $earningTypes = [];
    foreach ($earnings as $e) {
        $earningTypes[$e['type']] = ['count' => $e['count'], 'total' => $e['total']];
    }

    $multiplier = getMembershipMultiplier($tier);

    $allMethods = [
        'offerwall' => ['name' => 'Offerwall', 'icon' => 'fa-table-cells-large', 'color' => '#6c5ce7', 'desc' => 'Complete offers from top advertisers', 'potential' => 50, 'difficulty' => 'Medium'],
        'surveys' => ['name' => 'Surveys', 'icon' => 'fa-poll', 'color' => '#00cec9', 'desc' => 'Share your opinion and get paid', 'potential' => 15, 'difficulty' => 'Easy'],
        'tasks' => ['name' => 'Tasks', 'icon' => 'fa-check-double', 'color' => '#fd79a8', 'desc' => 'Complete simple signups and downloads', 'potential' => 10, 'difficulty' => 'Easy'],
        'shortlinks' => ['name' => 'Shortlinks', 'icon' => 'fa-link', 'color' => '#fdcb6e', 'desc' => 'Earn per link click', 'potential' => 5, 'difficulty' => 'Very Easy'],
        'faucet' => ['name' => 'Faucet', 'icon' => 'fa-water', 'color' => '#74b9ff', 'desc' => 'Claim free rewards every 5 minutes', 'potential' => 2, 'difficulty' => 'Very Easy'],
        'spin' => ['name' => 'Spin Wheel', 'icon' => 'fa-spinner', 'color' => '#e17055', 'desc' => 'Try your luck and win big', 'potential' => 100, 'difficulty' => 'Easy'],
        'scratch' => ['name' => 'Scratch Cards', 'icon' => 'fa-rectangle-ad', 'color' => '#55efc4', 'desc' => 'Instant win scratch cards', 'potential' => 25, 'difficulty' => 'Easy'],
        'quizzes' => ['name' => 'Quizzes', 'icon' => 'fa-question-circle', 'color' => '#a29bfe', 'desc' => 'Test your knowledge and earn', 'potential' => 3, 'difficulty' => 'Medium'],
        'videos' => ['name' => 'Videos', 'icon' => 'fa-video', 'color' => '#fab1a0', 'desc' => 'Watch ads and earn rewards', 'potential' => 2, 'difficulty' => 'Very Easy'],
        'referrals' => ['name' => 'Referrals', 'icon' => 'fa-user-friends', 'color' => '#00b894', 'desc' => 'Invite friends and earn 20% commission', 'potential' => 100, 'difficulty' => 'Medium']
    ];

    foreach ($allMethods as $key => $method) {
        $historicalData = $earningTypes[$key] ?? ['count' => 0, 'total' => 0];
        $score = 0;

        if ($historicalData['total'] > 0) {
            $score += min($historicalData['total'] * 10, 50);
            $score += min($historicalData['count'] * 2, 20);
        }

        if ($key === 'referrals' && $referralCount > 0) {
            $score += min($referralCount * 5, 30);
        }

        if ($key === 'surveys' || $key === 'tasks' || $key === 'shortlinks') {
            $score += 10;
        }

        if ($key === 'spin' || $key === 'scratch') {
            $score += 5;
        }

        if ($key === 'referrals') {
            $score += $referralCount === 0 ? 15 : 5;
        }

        $score += $historicalData['total'] === 0 ? 5 : 0;

        $recommendations[] = [
            'type' => $key,
            'name' => $method['name'],
            'icon' => $method['icon'],
            'color' => $method['color'],
            'description' => $method['desc'],
            'potential_earnings' => $method['potential'] * $multiplier,
            'difficulty' => $method['difficulty'],
            'historical_count' => (int)$historicalData['count'],
            'historical_total' => (float)$historicalData['total'],
            'score' => $score,
            'badge' => $score >= 40 ? 'Hot' : ($score >= 25 ? 'Recommended' : 'Try it')
        ];
    }

    usort($recommendations, function($a, $b) {
        return $b['score'] - $a['score'];
    });

    $topPick = $recommendations[0] ?? null;
    $diversify = [];

    foreach ($recommendations as $r) {
        if ($r['historical_count'] === 0 && $r['score'] >= 15) {
            $diversify[] = $r;
        }
    }
    usort($diversify, function($a, $b) {
        return $b['potential_earnings'] - $a['potential_earnings'];
    });

    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total_earned FROM earnings WHERE user_id = ? AND status = 'credited'");
    $stmt->execute([$userId]);
    $totalEarned = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total_earned FROM earnings WHERE user_id = ? AND status = 'credited' AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute([$userId]);
    $weeklyEarned = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total_earned FROM earnings WHERE user_id = ? AND status = 'credited' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt->execute([$userId]);
    $dailyEarned = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    $unreadNotifs = $stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'user_context' => [
            'membership_tier' => $tier,
            'earnings_multiplier' => $multiplier,
            'level' => (int)$level,
            'xp_points' => (int)$xpPoints,
            'streak_days' => $streakDays,
            'referrals' => (int)$referralCount,
            'achievements' => (int)$achievementCount,
            'total_earned' => (float)$totalEarned,
            'weekly_earned' => (float)$weeklyEarned,
            'daily_earned' => (float)$dailyEarned,
            'unread_notifications' => (int)$unreadNotifs
        ],
        'top_pick' => $topPick,
        'recommendations' => array_slice($recommendations, 0, 6),
        'try_something_new' => array_slice($diversify, 0, 3),
        'insights' => [
            'total_earning_methods_used' => count($earningTypes),
            'most_used_method' => !empty($earnings) ? $earnings[0]['type'] : null,
            'consistency_score' => $streakDays > 7 ? 'high' : ($streakDays > 2 ? 'medium' : 'low'),
            'next_level_xp' => getNextLevelXP($level, $xpPoints)
        ]
    ]);
} catch (Exception $e) {
    logError('AI recommend error', ['message' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to generate recommendations.']);
}

function getNextLevelXP($currentLevel, $currentXP) {
    $thresholds = LEVEL_THRESHOLDS ?? [
        1 => 0, 2 => 100, 3 => 250, 4 => 500, 5 => 1000,
        6 => 2000, 7 => 3500, 8 => 5000, 9 => 7500, 10 => 10000,
        11 => 15000, 12 => 25000, 13 => 50000, 14 => 100000, 15 => 250000
    ];

    $nextLevel = min($currentLevel + 1, 15);
    if (isset($thresholds[$nextLevel])) {
        return max(0, $thresholds[$nextLevel] - $currentXP);
    }
    return 0;
}
