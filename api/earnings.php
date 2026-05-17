<?php

class API_earnings
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
            case '/earnings':
                return $this->index();
            case '/earnings/stats':
                return $this->stats();
            case '/earnings/claim':
                return $this->claim();
            case '/earnings/balance':
                return $this->balance();
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

        $type = $_GET['type'] ?? '';
        $status = $_GET['status'] ?? '';
        $from = $_GET['from'] ?? '';
        $to = $_GET['to'] ?? '';
        $sort = $_GET['sort'] ?? 'created_at';
        $order = strtoupper($_GET['order'] ?? 'DESC');

        $allowedSorts = ['created_at', 'amount', 'type', 'status'];
        if (!in_array($sort, $allowedSorts)) $sort = 'created_at';
        if (!in_array($order, ['ASC', 'DESC'])) $order = 'DESC';

        $where = "WHERE user_id = ?";
        $params = [$this->userId];

        if (!empty($type)) {
            $where .= " AND type = ?";
            $params[] = $type;
        }

        if (!empty($status)) {
            $where .= " AND status = ?";
            $params[] = $status;
        }

        if (!empty($from)) {
            $where .= " AND created_at >= ?";
            $params[] = $from . ' 00:00:00';
        }

        if (!empty($to)) {
            $where .= " AND created_at <= ?";
            $params[] = $to . ' 23:59:59';
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM earnings {$where}");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare("
            SELECT id, user_id, amount, type, description, reference_id, status,
                   created_at, updated_at
            FROM earnings {$where}
            ORDER BY {$sort} {$order}
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);
        $earnings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $earnings = array_map(function($e) {
            $e['amount'] = (float)$e['amount'];
            return $e;
        }, $earnings);

        return [
            'success' => true,
            'data' => $earnings,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit),
                'types' => $this->getEarningTypes()
            ]
        ];
    }

    private function stats(): array
    {
        $period = $_GET['period'] ?? 'all';

        $dateFilter = '';
        switch ($period) {
            case 'today':
                $dateFilter = "AND DATE(created_at) = CURDATE()";
                break;
            case 'week':
                $dateFilter = "AND YEARWEEK(created_at) = YEARWEEK(CURDATE())";
                break;
            case 'month':
                $dateFilter = "AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
                break;
            case 'year':
                $dateFilter = "AND YEAR(created_at) = YEAR(CURDATE())";
                break;
            default:
                $period = 'all';
                break;
        }

        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) as total_transactions,
                COALESCE(SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END), 0) as total_earned,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) as pending_amount,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
                COUNT(DISTINCT DATE(created_at)) as active_days,
                COALESCE(AVG(CASE WHEN status = 'completed' THEN amount ELSE NULL END), 0) as avg_earning
            FROM earnings
            WHERE user_id = ? {$dateFilter}
        ");
        $stmt->execute([$this->userId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        $stats['total_earned'] = (float)$stats['total_earned'];
        $stats['pending_amount'] = (float)$stats['pending_amount'];
        $stats['avg_earning'] = (float)$stats['avg_earning'];
        $stats['total_transactions'] = (int)$stats['total_transactions'];
        $stats['completed_count'] = (int)$stats['completed_count'];
        $stats['pending_count'] = (int)$stats['pending_count'];
        $stats['active_days'] = (int)$stats['active_days'];
        $stats['period'] = $period;

        $stmt = $this->db->prepare("
            SELECT type, COUNT(*) as count, COALESCE(SUM(amount), 0) as total
            FROM earnings
            WHERE user_id = ? AND status = 'completed' {$dateFilter}
            GROUP BY type
            ORDER BY total DESC
        ");
        $stmt->execute([$this->userId]);
        $typeBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $typeBreakdown = array_map(function($t) {
            $t['total'] = (float)$t['total'];
            $t['count'] = (int)$t['count'];
            return $t;
        }, $typeBreakdown);

        $stats['by_type'] = $typeBreakdown;

        $stmt = $this->db->prepare("
            SELECT DATE(created_at) as date, COALESCE(SUM(amount), 0) as daily_total, COUNT(*) as transactions
            FROM earnings
            WHERE user_id = ? AND status = 'completed' {$dateFilter}
            GROUP BY DATE(created_at)
            ORDER BY date DESC
            LIMIT 30
        ");
        $stmt->execute([$this->userId]);
        $dailyHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dailyHistory = array_map(function($d) {
            $d['daily_total'] = (float)$d['daily_total'];
            $d['transactions'] = (int)$d['transactions'];
            return $d;
        }, $dailyHistory);

        $stats['daily_history'] = $dailyHistory;

        $stmt = $this->db->prepare("SELECT balance, total_earned, total_withdrawn FROM users WHERE id = ?");
        $stmt->execute([$this->userId]);
        $userBalances = $stmt->fetch(PDO::FETCH_ASSOC);

        $stats['balances'] = [
            'available' => (float)($userBalances['balance'] ?? 0),
            'total_earned' => (float)($userBalances['total_earned'] ?? 0),
            'total_withdrawn' => (float)($userBalances['total_withdrawn'] ?? 0)
        ];

        return [
            'success' => true,
            'data' => $stats
        ];
    }

    private function claim(): array
    {
        $input = $this->getInput();

        $type = $input['type'] ?? '';
        $referenceId = $input['reference_id'] ?? '';

        if (empty($type)) {
            http_response_code(422);
            return ['success' => false, 'error' => 'Earning type is required'];
        }

        $allowedTypes = ['offer', 'task', 'survey', 'quiz', 'scratch_card', 'spin_wheel',
                         'shortlink', 'faucet', 'video', 'daily_reward', 'achievement', 'mission', 'bonus'];

        if (!in_array($type, $allowedTypes)) {
            http_response_code(422);
            return ['success' => false, 'error' => 'Invalid earning type'];
        }

        $amount = 0;
        $description = '';

        switch ($type) {
            case 'daily_reward':
                $stmt = $this->db->prepare("
                    SELECT day, reward_amount FROM daily_rewards
                    WHERE user_id = ? AND DATE(claimed_at) = CURDATE() AND claimed = 1
                ");
                $stmt->execute([$this->userId]);
                if ($stmt->fetch()) {
                    http_response_code(409);
                    return ['success' => false, 'error' => 'Daily reward already claimed today'];
                }

                $stmt = $this->db->prepare("
                    SELECT dr.day, dr.reward_amount
                    FROM user_daily_rewards udr
                    JOIN daily_rewards_config dr ON udr.day = dr.day
                    WHERE udr.user_id = ?
                    ORDER BY udr.day DESC
                    LIMIT 1
                ");
                $stmt->execute([$this->userId]);
                $rewardData = $stmt->fetch(PDO::FETCH_ASSOC);

                $stmt = $this->db->prepare("
                    SELECT COALESCE(MAX(day), 0) as current_day
                    FROM user_daily_rewards
                    WHERE user_id = ?
                ");
                $stmt->execute([$this->userId]);
                $currentDay = (int)$stmt->fetchColumn();

                if ($rewardData) {
                    $currentDay = $rewardData['day'];
                }

                $nextDay = $currentDay + 1;

                $stmt = $this->db->prepare("
                    SELECT day, reward_amount FROM daily_rewards_config WHERE day = ?
                ");
                $stmt->execute([$nextDay]);
                $nextReward = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$nextReward) {
                    $nextDay = 1;
                    $stmt = $this->db->prepare("SELECT day, reward_amount FROM daily_rewards_config WHERE day = 1");
                    $stmt->execute();
                    $nextReward = $stmt->fetch(PDO::FETCH_ASSOC);
                }

                if (!$nextReward) {
                    $nextReward = ['day' => $nextDay, 'reward_amount' => 0.10];
                }

                $amount = (float)$nextReward['reward_amount'];
                $description = "Daily reward - Day {$nextDay}";
                $referenceId = $nextDay;
                break;

            case 'faucet':
                $stmt = $this->db->prepare("
                    SELECT today_claims, max_claims FROM user_faucet_claims
                    WHERE user_id = ? AND DATE(last_reset) = CURDATE()
                ");
                $stmt->execute([$this->userId]);
                $faucetData = $stmt->fetch(PDO::FETCH_ASSOC);

                $maxClaims = (int)($_ENV['FAUCET_MAX_CLAIMS'] ?? 10);

                if ($faucetData && (int)$faucetData['today_claims'] >= $maxClaims) {
                    http_response_code(429);
                    return ['success' => false, 'error' => 'Daily faucet claim limit reached'];
                }

                $minAmount = (float)($_ENV['FAUCET_MIN_AMOUNT'] ?? 0.0001);
                $maxAmount = (float)($_ENV['FAUCET_MAX_AMOUNT'] ?? 0.001);
                $amount = round(mt_rand($minAmount * 10000, $maxAmount * 10000) / 10000, 4);
                $description = "Faucet claim";
                break;

            case 'scratch_card':
                $stmt = $this->db->prepare("
                    SELECT id, amount, status FROM scratch_cards
                    WHERE user_id = ? AND id = ? AND status = 'unclaimed'
                ");
                $stmt->execute([$this->userId, $referenceId]);
                $card = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$card) {
                    http_response_code(404);
                    return ['success' => false, 'error' => 'Scratch card not found or already claimed'];
                }

                $amount = (float)$card['amount'];
                $description = "Scratch card reward";
                break;

            default:
                http_response_code(501);
                return ['success' => false, 'error' => "Claim handler for {$type} not implemented"];
        }

        if ($amount <= 0) {
            http_response_code(422);
            return ['success' => false, 'error' => 'Invalid earning amount'];
        }

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                INSERT INTO earnings (user_id, amount, type, description, reference_id, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 'completed', NOW(), NOW())
            ");
            $stmt->execute([$this->userId, $amount, $type, $description, $referenceId]);
            $earningId = $this->db->lastInsertId();

            $stmt = $this->db->prepare("
                UPDATE users SET balance = balance + ?, total_earned = total_earned + ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$amount, $amount, $this->userId]);

            switch ($type) {
                case 'daily_reward':
                    $stmt = $this->db->prepare("
                        INSERT INTO user_daily_rewards (user_id, day, reward_amount, created_at)
                        VALUES (?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE reward_amount = VALUES(reward_amount), created_at = NOW()
                    ");
                    $stmt->execute([$this->userId, $referenceId, $amount]);
                    break;

                case 'faucet':
                    $stmt = $this->db->prepare("
                        INSERT INTO user_faucet_claims (user_id, today_claims, last_reset, created_at)
                        VALUES (?, 1, NOW(), NOW())
                        ON DUPLICATE KEY UPDATE today_claims = today_claims + 1, last_reset = NOW()
                    ");
                    $stmt->execute([$this->userId]);
                    break;

                case 'scratch_card':
                    $stmt = $this->db->prepare("
                        UPDATE scratch_cards SET status = 'claimed', claimed_at = NOW() WHERE id = ? AND user_id = ?
                    ");
                    $stmt->execute([$referenceId, $this->userId]);
                    break;
            }

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Reward claimed successfully',
                'data' => [
                    'earning_id' => (int)$earningId,
                    'amount' => $amount,
                    'type' => $type,
                    'balance' => $this->getUserBalance()
                ]
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            http_response_code(500);
            return ['success' => false, 'error' => 'Failed to claim reward. Please try again.'];
        }
    }

    private function balance(): array
    {
        $stmt = $this->db->prepare("
            SELECT balance, total_earned, total_withdrawn, pending_withdrawal
            FROM users WHERE id = ?
        ");
        $stmt->execute([$this->userId]);
        $balances = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$balances) {
            http_response_code(404);
            return ['success' => false, 'error' => 'User not found'];
        }

        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(amount), 0) as pending_earnings
            FROM earnings WHERE user_id = ? AND status = 'pending'
        ");
        $stmt->execute([$this->userId]);
        $pendingEarnings = (float)$stmt->fetchColumn();

        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(amount), 0) as pending_withdrawals
            FROM withdrawals WHERE user_id = ? AND status = 'pending'
        ");
        $stmt->execute([$this->userId]);
        $pendingWithdrawals = (float)$stmt->fetchColumn();

        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(amount), 0) as today_earned
            FROM earnings WHERE user_id = ? AND status = 'completed' AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$this->userId]);
        $todayEarned = (float)$stmt->fetchColumn();

        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(amount), 0) as week_earned
            FROM earnings WHERE user_id = ? AND status = 'completed' AND YEARWEEK(created_at) = YEARWEEK(CURDATE())
        ");
        $stmt->execute([$this->userId]);
        $weekEarned = (float)$stmt->fetchColumn();

        return [
            'success' => true,
            'data' => [
                'available_balance' => (float)$balances['balance'],
                'total_earned' => (float)$balances['total_earned'],
                'total_withdrawn' => (float)$balances['total_withdrawn'],
                'pending_earnings' => $pendingEarnings,
                'pending_withdrawals' => $pendingWithdrawals,
                'today_earned' => $todayEarned,
                'week_earned' => $weekEarned,
                'currency' => $_ENV['CURRENCY'] ?? 'USD',
                'min_withdrawal' => (float)($_ENV['MIN_WITHDRAWAL'] ?? 5.0)
            ]
        ];
    }

    private function getUserBalance(): float
    {
        $stmt = $this->db->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute([$this->userId]);
        return (float)$stmt->fetchColumn();
    }

    private function getEarningTypes(): array
    {
        $stmt = $this->db->prepare("
            SELECT DISTINCT type FROM earnings WHERE user_id = ? ORDER BY type
        ");
        $stmt->execute([$this->userId]);
        $types = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $types;
    }
}
