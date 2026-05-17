<?php

class API_referrals
{
    private $db;
    private $method;
    private $route;
    private $userId;

    public function __construct($db, $method, $route)
    {
        $this->db = $db;
        $this->method = $method;
        $this->route = $route;
        $this->userId = $_REQUEST['auth_user_id'] ?? null;
    }

    public function handle()
    {
        switch ($this->route) {
            case '/referrals':
                return $this->index();
            case '/referrals/stats':
                return $this->stats();
            case '/referrals/tree':
                return $this->tree();
            case '/referrals/claim-bonus':
                return $this->claimBonus();
            default:
                http_response_code(404);
                return ['success' => false, 'error' => 'Not found'];
        }
    }

    private function getInput(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            return is_array($data) ? $data : [];
        }
        if ($this->method === 'GET') return $_GET;
        return $_POST;
    }

    private function index(): array
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $status = $_GET['status'] ?? '';

        $where = "WHERE r.referrer_id = ?";
        $params = [$this->userId];

        if (!empty($status) && in_array($status, ['pending', 'qualified', 'active'])) {
            $where .= " AND r.status = ?";
            $params[] = $status;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM referrals r {$where}");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare("
            SELECT r.id, r.referred_id, r.status, r.bonus, r.commission, r.commission_paid_at,
                   r.created_at, u.username, u.email, u.avatar, u.total_earned,
                   u.level_id, u.created_at as user_created_at, u.last_login
            FROM referrals r
            JOIN users u ON r.referred_id = u.id
            {$where}
            ORDER BY r.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);
        $referrals = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $referrals = array_map(function($r) {
            $r['bonus'] = $r['bonus'] ? (float)$r['bonus'] : null;
            $r['commission'] = $r['commission'] ? (float)$r['commission'] : null;
            $r['total_earned'] = (float)$r['total_earned'];
            return $r;
        }, $referrals);

        return [
            'success' => true,
            'data' => $referrals,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ];
    }

    private function stats(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) as total_referrals,
                SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN r.status = 'qualified' THEN 1 ELSE 0 END) as qualified,
                SUM(CASE WHEN r.status = 'active' THEN 1 ELSE 0 END) as active,
                COALESCE(SUM(r.bonus), 0) as total_bonus,
                COALESCE(SUM(r.commission), 0) as total_commission
            FROM referrals r
            WHERE r.referrer_id = ?
        ");
        $stmt->execute([$this->userId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        $stats['total_referrals'] = (int)$stats['total_referrals'];
        $stats['pending'] = (int)$stats['pending'];
        $stats['qualified'] = (int)$stats['qualified'];
        $stats['active'] = (int)$stats['active'];
        $stats['total_bonus'] = (float)$stats['total_bonus'];
        $stats['total_commission'] = (float)$stats['total_commission'];
        $stats['total_earned'] = $stats['total_bonus'] + $stats['total_commission'];

        $stmt = $this->db->prepare("
            SELECT u.username, r.bonus, r.commission, r.created_at
            FROM referrals r
            JOIN users u ON r.referred_id = u.id
            WHERE r.referrer_id = ? AND (r.bonus > 0 OR r.commission > 0)
            ORDER BY GREATEST(COALESCE(r.bonus, 0), COALESCE(r.commission, 0)) DESC
            LIMIT 10
        ");
        $stmt->execute([$this->userId]);
        $topReferrals = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stats['top_referrals'] = $topReferrals;

        $stmt = $this->db->prepare("
            SELECT DATE(r.created_at) as date, COUNT(*) as count
            FROM referrals r
            WHERE r.referrer_id = ? AND r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(r.created_at)
            ORDER BY date DESC
        ");
        $stmt->execute([$this->userId]);
        $timeline = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stats['referral_timeline'] = $timeline;

        $stmt = $this->db->prepare("SELECT referral_code FROM users WHERE id = ?");
        $stmt->execute([$this->userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $stats['referral_code'] = $user['referral_code'] ?? '';
        $stats['referral_link'] = APP_URL . "/register.php?ref=" . ($user['referral_code'] ?? '');
        $stats['commission_rate'] = (float)($_ENV['REFERRAL_COMMISSION_RATE'] ?? 10.0);
        $stats['bonus_amount'] = (float)($_ENV['REFERRAL_BONUS_AMOUNT'] ?? 1.0);
        $stats['min_earnings_for_bonus'] = (float)($_ENV['REFERRAL_MIN_EARNINGS'] ?? 5.0);

        $stmt = $this->db->prepare("
            SELECT SUM(u.total_earned) as total_referred_earnings
            FROM referrals r
            JOIN users u ON r.referred_id = u.id
            WHERE r.referrer_id = ?
        ");
        $stmt->execute([$this->userId]);
        $referredEarnings = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_referred_earnings'] = (float)($referredEarnings['total_referred_earnings'] ?? 0);

        return [
            'success' => true,
            'data' => $stats
        ];
    }

    private function tree(): array
    {
        $maxDepth = min(5, max(1, (int)($_GET['depth'] ?? 3)));

        $tree = $this->buildReferralTree($this->userId, 0, $maxDepth);

        return [
            'success' => true,
            'data' => [
                'user_id' => (int)$this->userId,
                'max_depth' => $maxDepth,
                'tree' => $tree
            ]
        ];
    }

    private function buildReferralTree(int $userId, int $currentDepth, int $maxDepth): ?array
    {
        if ($currentDepth > $maxDepth) return null;

        $stmt = $this->db->prepare("
            SELECT u.id, u.username, u.avatar, u.total_earned, u.level_id, u.created_at,
                   l.name as level_name, l.badge as level_badge
            FROM users u
            LEFT JOIN levels l ON u.level_id = l.id
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) return null;

        $user['total_earned'] = (float)$user['total_earned'];

        $stmt = $this->db->prepare("
            SELECT u.id, u.username, u.avatar, u.total_earned, u.level_id, u.created_at
            FROM referrals r
            JOIN users u ON r.referred_id = u.id
            WHERE r.referrer_id = ?
            ORDER BY u.created_at DESC
        ");
        $stmt->execute([$userId]);
        $children = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $user['children'] = [];
        foreach ($children as $child) {
            $subtree = $this->buildReferralTree((int)$child['id'], $currentDepth + 1, $maxDepth);
            if ($subtree) {
                $user['children'][] = $subtree;
            }
        }

        $user['total_children'] = count($user['children']);
        $user['depth'] = $currentDepth;

        return $user;
    }

    private function claimBonus(): array
    {
        $input = $this->getInput();

        $stmt = $this->db->prepare("
            SELECT id, referred_id, bonus, status
            FROM referrals
            WHERE referrer_id = ? AND status = 'qualified' AND bonus > 0 AND commission IS NULL
            LIMIT 1
        ");
        $stmt->execute([$this->userId]);
        $referral = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$referral) {
            $stmt = $this->db->prepare("
                SELECT id, referred_id, commission, status
                FROM referrals
                WHERE referrer_id = ? AND status = 'qualified' AND commission > 0
                LIMIT 1
            ");
            $stmt->execute([$this->userId]);
            $referral = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$referral) {
                http_response_code(404);
                return ['success' => false, 'error' => 'No available bonus to claim'];
            }
        }

        $amount = (float)($referral['bonus'] ?? $referral['commission'] ?? 0);

        if ($amount <= 0) {
            http_response_code(422);
            return ['success' => false, 'error' => 'Invalid bonus amount'];
        }

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                UPDATE users SET balance = balance + ?, total_earned = total_earned + ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$amount, $amount, $this->userId]);

            $stmt = $this->db->prepare("
                UPDATE referrals SET bonus_claimed_at = NOW(), updated_at = NOW()
                WHERE id = ? AND referrer_id = ?
            ");
            $stmt->execute([$referral['id'], $this->userId]);

            $stmt = $this->db->prepare("
                INSERT INTO earnings (user_id, amount, type, description, reference_id, status, created_at)
                VALUES (?, ?, 'referral_bonus', 'Referral bonus claimed', ?, 'completed', NOW())
            ");
            $stmt->execute([$this->userId, $amount, $referral['id']]);

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Bonus claimed successfully',
                'data' => [
                    'amount' => $amount,
                    'balance' => $this->getBalance()
                ]
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            http_response_code(500);
            return ['success' => false, 'error' => 'Failed to claim bonus'];
        }
    }

    private function getBalance(): float
    {
        $stmt = $this->db->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute([$this->userId]);
        return (float)$stmt->fetchColumn();
    }
}
