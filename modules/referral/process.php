<?php
require_once __DIR__ . '/../../includes/functions.php';

class ReferralHandler {

    public function processReferralClick($referralCode) {
        if (empty($referralCode)) return false;

        $db = getDB();
        $stmt = $db->prepare("SELECT id, username FROM users WHERE referral_code = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$referralCode]);
        $referrer = $stmt->fetch();

        if (!$referrer) return false;

        $cookieName = 'zyn_ref_' . md5($referralCode);
        $cookieValue = $referrer['id'] . '|' . $referralCode;
        $expiry = time() + (86400 * 30);

        setcookie($cookieName, $cookieValue, [
            'expires' => $expiry,
            'path' => COOKIE_PATH,
            'domain' => COOKIE_DOMAIN,
            'secure' => COOKIE_SECURE,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        $_SESSION['referral'] = [
            'referrer_id' => $referrer['id'],
            'code' => $referralCode,
            'landed_at' => time()
        ];

        $stmt = $db->prepare("INSERT INTO referral_clicks (referrer_id, referral_code, ip_address, user_agent, landed_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$referrer['id'], $referralCode, getClientIP(), $_SERVER['HTTP_USER_AGENT'] ?? '']);

        return $referrer;
    }

    public function processReferralRegistration($newUserId) {
        $db = getDB();
        $referrerId = null;
        $referralCode = null;

        if (isset($_SESSION['referral']['referrer_id'])) {
            $referrerId = $_SESSION['referral']['referrer_id'];
            $referralCode = $_SESSION['referral']['code'];
        } else {
            foreach ($_COOKIE as $key => $value) {
                if (strpos($key, 'zyn_ref_') === 0) {
                    $parts = explode('|', $value);
                    if (count($parts) === 2) {
                        $referrerId = (int)$parts[0];
                        $referralCode = $parts[1];
                    }
                    break;
                }
            }
        }

        if (!$referrerId || $referrerId == $newUserId) return false;

        $stmt = $db->prepare("SELECT id FROM referrals WHERE referred_id = ? LIMIT 1");
        $stmt->execute([$newUserId]);
        if ($stmt->fetch()) return false;

        $stmt = $db->prepare("SELECT id FROM users WHERE id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$referrerId]);
        if (!$stmt->fetch()) return false;

        $stmt = $db->prepare("INSERT INTO referrals (referrer_id, referred_id, referral_code, level, status, commission_rate) VALUES (?, ?, ?, 1, 'active', ?)");
        $rate = REFERRAL_COMMISSION_LEVELS[1] ?? 10;
        $stmt->execute([$referrerId, $newUserId, $referralCode, $rate]);

        $referralId = $db->lastInsertId();

        addEarning($referrerId, 'referral', 0, 'New referral joined', $referralId);
        addNotification($referrerId, 'referral', 'New Referral!', 'Someone joined using your referral link.');
        addNotification($newUserId, 'system', 'Welcome!', 'You were referred by a friend.');

        addXP($referrerId, XP_PER_REFERRAL);

        $this->processMultiLevelReferrals($newUserId, $referrerId, 2);

        unset($_SESSION['referral']);
        foreach ($_COOKIE as $key => $value) {
            if (strpos($key, 'zyn_ref_') === 0) {
                setcookie($key, '', time() - 3600, COOKIE_PATH);
            }
        }

        return true;
    }

    public function processEarningCommission($userId, $earnedAmount, $source = '') {
        if ($earnedAmount <= 0) return;

        $db = getDB();

        $stmt = $db->prepare("
            WITH RECURSIVE referral_chain AS (
                SELECT referrer_id, 1 as level, referred_id
                FROM referrals WHERE referred_id = ?
                UNION ALL
                SELECT r.referrer_id, rc.level + 1, r.referred_id
                FROM referrals r
                JOIN referral_chain rc ON r.referred_id = rc.referrer_id
                WHERE rc.level < 5
            )
            SELECT referrer_id, level FROM referral_chain ORDER BY level
        ");
        $stmt->execute([$userId]);
        $chain = $stmt->fetchAll();

        $levels = REFERRAL_COMMISSION_LEVELS;

        foreach ($chain as $link) {
            $level = (int)$link['level'];
            $referrerId = (int)$link['referrer_id'];

            if (!isset($levels[$level])) continue;

            $commissionRate = $levels[$level] / 100;
            $commissionAmount = $earnedAmount * $commissionRate;

            if ($commissionAmount <= 0) continue;

            $stmt = $db->prepare("SELECT id FROM referral_commissions WHERE referrer_id = ? AND referred_id = ? AND level = ? AND DATE(created_at) = CURDATE() LIMIT 1");
            $stmt->execute([$referrerId, $userId, $level]);
            if ($stmt->fetch()) continue;

            $description = ($source ? ucfirst($source) . ' ' : '') . 'referral commission - Level ' . $level;
            $earningResult = addEarning($referrerId, 'referral', $commissionAmount, $description);

            if ($earningResult) {
                $stmt = $db->prepare("INSERT INTO referral_commissions (referrer_id, referred_id, amount, level, source) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$referrerId, $userId, $commissionAmount, $level, $source]);
            }
        }
    }

    public function processMultiLevelReferrals($newUserId, $directReferrerId, $maxDepth = 5) {
        $db = getDB();
        $currentReferrer = $directReferrerId;
        $currentLevel = 2;

        while ($currentReferrer && $currentLevel <= $maxDepth) {
            $stmt = $db->prepare("SELECT referrer_id FROM referrals WHERE referred_id = ? LIMIT 1");
            $stmt->execute([$currentReferrer]);
            $parent = $stmt->fetch();

            if (!$parent) break;

            $stmt = $db->prepare("SELECT id FROM referrals WHERE referrer_id = ? AND referred_id = ? AND level = ? LIMIT 1");
            $stmt->execute([$parent['referrer_id'], $newUserId, $currentLevel]);
            if ($stmt->fetch()) {
                $currentReferrer = $parent['referrer_id'];
                $currentLevel++;
                continue;
            }

            $stmt = $db->prepare("INSERT IGNORE INTO referrals (referrer_id, referred_id, referral_code, level, status, commission_rate) VALUES (?, ?, NULL, ?, 'active', ?)");
            $rate = REFERRAL_COMMISSION_LEVELS[$currentLevel] ?? 0;
            $stmt->execute([$parent['referrer_id'], $newUserId, $currentLevel, $rate]);

            $currentReferrer = $parent['referrer_id'];
            $currentLevel++;
        }
    }

    public function getReferralStats($userId) {
        $db = getDB();

        $stmt = $db->prepare("SELECT COUNT(*) as total FROM referrals WHERE referrer_id = ?");
        $stmt->execute([$userId]);
        $total = $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM referrals WHERE referrer_id = ? AND status = 'active'");
        $stmt->execute([$userId]);
        $active = $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM earnings WHERE user_id = ? AND type = 'referral' AND status = 'credited'");
        $stmt->execute([$userId]);
        $earnings = $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) as clicks FROM referral_clicks WHERE referrer_id = ? AND DATE(landed_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute([$userId]);
        $clicks30d = $stmt->fetchColumn();

        return [
            'total_referrals' => (int)$total,
            'active_referrals' => (int)$active,
            'total_earnings' => (float)$earnings,
            'clicks_30d' => (int)$clicks30d,
            'conversion_rate' => $clicks30d > 0 ? round(($active / max($clicks30d, 1)) * 100, 1) : 0
        ];
    }
}

$action = $_GET['action'] ?? '';
$handler = new ReferralHandler();

switch ($action) {
    case 'click':
        $code = $_GET['code'] ?? $_POST['code'] ?? '';
        if (empty($code)) {
            header('Location: ' . APP_URL . '/auth/register.php');
            exit;
        }
        $handler->processReferralClick($code);
        header('Location: ' . APP_URL . '/auth/register.php?ref=' . urlencode($code));
        exit;

    case 'register':
        $newUserId = (int)($_POST['user_id'] ?? $_GET['user_id'] ?? 0);
        if ($newUserId <= 0) {
            jsonResponse(['success' => false, 'error' => 'Invalid user'], 400);
        }
        $handler->processReferralRegistration($newUserId);
        jsonResponse(['success' => true]);
        break;

    case 'commission':
        $userId = (int)($_POST['user_id'] ?? $_GET['user_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? $_GET['amount'] ?? 0);
        $source = $_POST['source'] ?? $_GET['source'] ?? '';

        if ($userId <= 0 || $amount <= 0) {
            jsonResponse(['success' => false, 'error' => 'Invalid parameters'], 400);
        }

        $handler->processEarningCommission($userId, $amount, $source);
        jsonResponse(['success' => true]);
        break;

    case 'stats':
        $userId = (int)($_GET['user_id'] ?? $_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        }
        jsonResponse(['success' => true, 'data' => $handler->getReferralStats($userId)]);
        break;

    default:
        jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
}
