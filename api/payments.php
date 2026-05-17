<?php

class API_payments
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
            case '/payments/withdraw':
                return $this->withdraw();
            case '/payments/withdrawals':
                return $this->withdrawals();
            case '/payments/deposit':
                return $this->deposit();
            case '/payments/webhook':
                return $this->webhook();
            case '/payments/methods':
                return $this->methods();
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

    private function withdraw(): array
    {
        $input = $this->getInput();

        $method = $input['method'] ?? '';
        $amount = (float)($input['amount'] ?? 0);
        $accountDetails = $input['account_details'] ?? [];

        if (empty($method)) {
            http_response_code(422);
            return ['success' => false, 'error' => 'Payment method is required'];
        }

        $minWithdrawal = (float)($_ENV['MIN_WITHDRAWAL'] ?? 5.0);
        $maxWithdrawal = (float)($_ENV['MAX_WITHDRAWAL'] ?? 1000.0);

        if ($amount < $minWithdrawal) {
            http_response_code(422);
            return ['success' => false, 'error' => "Minimum withdrawal amount is {$minWithdrawal}"];
        }

        if ($amount > $maxWithdrawal) {
            http_response_code(422);
            return ['success' => false, 'error' => "Maximum withdrawal amount is {$maxWithdrawal}"];
        }

        $stmt = $this->db->prepare("SELECT balance, total_earned, email, username FROM users WHERE id = ?");
        $stmt->execute([$this->userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            http_response_code(404);
            return ['success' => false, 'error' => 'User not found'];
        }

        if ((float)$user['balance'] < $amount) {
            http_response_code(422);
            return ['success' => false, 'error' => 'Insufficient balance'];
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*) as pending_count
            FROM withdrawals
            WHERE user_id = ? AND status = 'pending'
        ");
        $stmt->execute([$this->userId]);
        $pending = $stmt->fetch(PDO::FETCH_ASSOC);

        $maxPending = (int)($_ENV['MAX_PENDING_WITHDRAWALS'] ?? 3);

        if ((int)$pending['pending_count'] >= $maxPending) {
            http_response_code(429);
            return ['success' => false, 'error' => "Maximum {$maxPending} pending withdrawals allowed"];
        }

        $stmt = $this->db->prepare("
            SELECT id, name, min_amount, max_amount, fee_type, fee_amount, fee_percent, instructions, status
            FROM payment_methods
            WHERE slug = ? AND status = 'active' AND withdrawal_enabled = 1
            LIMIT 1
        ");
        $stmt->execute([$method]);
        $paymentMethod = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$paymentMethod) {
            http_response_code(422);
            return ['success' => false, 'error' => 'Invalid or disabled payment method'];
        }

        if ($amount < (float)$paymentMethod['min_amount']) {
            http_response_code(422);
            return ['success' => false, 'error' => "Minimum for {$paymentMethod['name']} is {$paymentMethod['min_amount']}"];
        }

        if ($paymentMethod['max_amount'] && $amount > (float)$paymentMethod['max_amount']) {
            http_response_code(422);
            return ['success' => false, 'error' => "Maximum for {$paymentMethod['name']} is {$paymentMethod['max_amount']}"];
        }

        $fee = 0;
        if ($paymentMethod['fee_type'] === 'fixed') {
            $fee = (float)$paymentMethod['fee_amount'];
        } elseif ($paymentMethod['fee_type'] === 'percent') {
            $fee = round($amount * ((float)$paymentMethod['fee_percent'] / 100), 2);
        }

        $netAmount = $amount - $fee;
        $withdrawalFee = $fee;

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                INSERT INTO withdrawals (user_id, amount, fee, net_amount, payment_method, account_details, status, ip_address, user_agent, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $this->userId,
                $amount,
                $withdrawalFee,
                $netAmount,
                $method,
                json_encode($accountDetails),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            $withdrawalId = $this->db->lastInsertId();

            $stmt = $this->db->prepare("
                UPDATE users SET balance = balance - ?, pending_withdrawal = pending_withdrawal + ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$amount, $amount, $this->userId]);

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Withdrawal request submitted successfully',
                'data' => [
                    'withdrawal_id' => (int)$withdrawalId,
                    'amount' => $amount,
                    'fee' => $withdrawalFee,
                    'net_amount' => $netAmount,
                    'method' => $method,
                    'status' => 'pending',
                    'estimated_completion' => date('Y-m-d H:i:s', time() + 86400),
                    'instructions' => $paymentMethod['instructions'] ?? ''
                ]
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            http_response_code(500);
            return ['success' => false, 'error' => 'Withdrawal request failed. Please try again.'];
        }
    }

    private function withdrawals(): array
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $status = $_GET['status'] ?? '';

        $where = "WHERE user_id = ?";
        $params = [$this->userId];

        if (!empty($status) && in_array($status, ['pending', 'processing', 'completed', 'failed', 'cancelled'])) {
            $where .= " AND status = ?";
            $params[] = $status;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM withdrawals {$where}");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare("
            SELECT id, amount, fee, net_amount, payment_method, account_details, status, admin_note,
                   created_at, updated_at, completed_at
            FROM withdrawals {$where}
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);
        $withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $withdrawals = array_map(function($w) {
            $w['amount'] = (float)$w['amount'];
            $w['fee'] = (float)$w['fee'];
            $w['net_amount'] = (float)$w['net_amount'];
            $w['account_details'] = json_decode($w['account_details'], true);
            return $w;
        }, $withdrawals);

        return [
            'success' => true,
            'data' => $withdrawals,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ];
    }

    private function deposit(): array
    {
        $input = $this->getInput();

        $method = $input['method'] ?? '';
        $amount = (float)($input['amount'] ?? 0);

        if (empty($method)) {
            http_response_code(422);
            return ['success' => false, 'error' => 'Payment method is required'];
        }

        $minDeposit = (float)($_ENV['MIN_DEPOSIT'] ?? 1.0);
        $maxDeposit = (float)($_ENV['MAX_DEPOSIT'] ?? 10000.0);

        if ($amount < $minDeposit) {
            http_response_code(422);
            return ['success' => false, 'error' => "Minimum deposit amount is {$minDeposit}"];
        }

        if ($amount > $maxDeposit) {
            http_response_code(422);
            return ['success' => false, 'error' => "Maximum deposit amount is {$maxDeposit}"];
        }

        $stmt = $this->db->prepare("
            SELECT id, name, slug, fee_type, fee_amount, fee_percent, status
            FROM payment_methods
            WHERE slug = ? AND status = 'active' AND deposit_enabled = 1
            LIMIT 1
        ");
        $stmt->execute([$method]);
        $paymentMethod = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$paymentMethod) {
            http_response_code(422);
            return ['success' => false, 'error' => 'Invalid or disabled deposit method'];
        }

        $fee = 0;
        if ($paymentMethod['fee_type'] === 'fixed') {
            $fee = (float)$paymentMethod['fee_amount'];
        } elseif ($paymentMethod['fee_type'] === 'percent') {
            $fee = round($amount * ((float)$paymentMethod['fee_percent'] / 100), 2);
        }

        $totalAmount = $amount + $fee;

        $transactionId = 'DEP-' . strtoupper(bin2hex(random_bytes(8)));

        $stmt = $this->db->prepare("
            INSERT INTO deposits (user_id, transaction_id, amount, fee, total_amount, payment_method, status, ip_address, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW(), NOW())
        ");
        $stmt->execute([
            $this->userId,
            $transactionId,
            $amount,
            $fee,
            $totalAmount,
            $method,
            $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
        $depositId = $this->db->lastInsertId();

        $paymentUrl = $this->generatePaymentUrl($depositId, $transactionId, $totalAmount, $method, $paymentMethod['name']);

        return [
            'success' => true,
            'message' => 'Deposit initiated',
            'data' => [
                'deposit_id' => (int)$depositId,
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'fee' => $fee,
                'total_amount' => $totalAmount,
                'method' => $method,
                'status' => 'pending',
                'payment_url' => $paymentUrl
            ]
        ];
    }

    private function webhook(): array
    {
        $input = $this->getInput();
        $payload = file_get_contents('php://input');
        $webhookData = json_decode($payload, true) ?: $input;

        $gateway = $_GET['gateway'] ?? $webhookData['gateway'] ?? '';
        $event = $webhookData['event'] ?? $webhookData['type'] ?? '';

        $signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? $webhookData['signature'] ?? '';

        $gatewayConfigs = [
            'stripe' => ['secret' => $_ENV['STRIPE_WEBHOOK_SECRET'] ?? ''],
            'paypal' => ['secret' => $_ENV['PAYPAL_WEBHOOK_SECRET'] ?? ''],
            'paystack' => ['secret' => $_ENV['PAYSTACK_WEBHOOK_SECRET'] ?? ''],
            'flutterwave' => ['secret' => $_ENV['FLUTTERWAVE_WEBHOOK_SECRET'] ?? ''],
            'coinbase' => ['secret' => $_ENV['COINBASE_WEBHOOK_SECRET'] ?? ''],
            'nowpayments' => ['secret' => $_ENV['NOWPAYMENTS_WEBHOOK_SECRET'] ?? '']
        ];

        $secret = $gatewayConfigs[$gateway]['secret'] ?? '';
        $computedSignature = hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($computedSignature, $signature)) {
            http_response_code(401);
            return ['success' => false, 'error' => 'Invalid webhook signature'];
        }

        $transactionId = $webhookData['transaction_id'] ?? $webhookData['txid'] ?? '';
        $status = $webhookData['status'] ?? '';

        switch ($status) {
            case 'completed':
            case 'success':
            case 'confirmed':
            case 'succeeded':
                $newStatus = 'completed';
                break;
            case 'failed':
            case 'cancelled':
            case 'expired':
                $newStatus = 'failed';
                break;
            case 'processing':
            case 'pending':
            default:
                $newStatus = 'processing';
                break;
        }

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                SELECT id, user_id, amount, status FROM deposits WHERE transaction_id = ? LIMIT 1
            ");
            $stmt->execute([$transactionId]);
            $deposit = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($deposit) {
                $stmt = $this->db->prepare("
                    UPDATE deposits SET status = ?, gateway_response = ?, updated_at = NOW()
                    WHERE transaction_id = ?
                ");
                $stmt->execute([$newStatus, json_encode($webhookData), $transactionId]);

                if ($newStatus === 'completed' && $deposit['status'] !== 'completed') {
                    $stmt = $this->db->prepare("
                        UPDATE users SET balance = balance + ?, total_deposited = total_deposited + ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$deposit['amount'], $deposit['amount'], $deposit['user_id']]);

                    $stmt = $this->db->prepare("
                        INSERT INTO earnings (user_id, amount, type, description, reference_id, status, created_at)
                        VALUES (?, ?, 'deposit', 'Deposit via {$gateway}', ?, 'completed', NOW())
                    ");
                    $stmt->execute([$deposit['user_id'], $deposit['amount'], $transactionId]);
                }
            } else {
                $stmt = $this->db->prepare("
                    SELECT id, user_id, amount, status FROM withdrawals WHERE transaction_id = ? LIMIT 1
                ");
                $stmt->execute([$transactionId]);
                $withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($withdrawal) {
                    $stmt = $this->db->prepare("
                        UPDATE withdrawals SET status = ?, gateway_response = ?, completed_at = NOW(), updated_at = NOW()
                        WHERE transaction_id = ?
                    ");
                    $stmt->execute([$newStatus, json_encode($webhookData), $transactionId]);

                    if ($newStatus === 'completed' && $withdrawal['status'] !== 'completed') {
                        $stmt = $this->db->prepare("
                            UPDATE users SET total_withdrawn = total_withdrawn + ?, pending_withdrawal = pending_withdrawal - ?, updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$withdrawal['amount'], $withdrawal['amount'], $withdrawal['user_id']]);
                    } elseif ($newStatus === 'failed' && $withdrawal['status'] !== 'failed') {
                        $stmt = $this->db->prepare("
                            UPDATE users SET balance = balance + ?, pending_withdrawal = pending_withdrawal - ?, updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$withdrawal['amount'], $withdrawal['amount'], $withdrawal['user_id']]);
                    }
                }
            }

            $this->db->commit();

            http_response_code(200);
            return ['success' => true, 'message' => 'Webhook processed'];
        } catch (Exception $e) {
            $this->db->rollBack();
            http_response_code(500);
            return ['success' => false, 'error' => 'Webhook processing failed'];
        }
    }

    private function methods(): array
    {
        $type = $_GET['type'] ?? 'withdrawal';

        $column = $type === 'deposit' ? 'deposit_enabled' : 'withdrawal_enabled';

        $stmt = $this->db->prepare("
            SELECT id, name, slug, description, min_amount, max_amount, fee_type, fee_amount, fee_percent,
                   instructions, logo, processing_time, {$column} as enabled
            FROM payment_methods
            WHERE status = 'active' AND {$column} = 1
            ORDER BY sort_order ASC, name ASC
        ");
        $stmt->execute();
        $methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $methods = array_map(function($m) {
            $m['min_amount'] = (float)$m['min_amount'];
            $m['max_amount'] = $m['max_amount'] ? (float)$m['max_amount'] : null;
            $m['fee_amount'] = (float)$m['fee_amount'];
            $m['fee_percent'] = (float)$m['fee_percent'];
            return $m;
        }, $methods);

        return [
            'success' => true,
            'data' => $methods
        ];
    }

    private function generatePaymentUrl(int $depositId, string $transactionId, float $amount, string $method, string $methodName): string
    {
        return APP_URL . "/payment/checkout.php?deposit_id={$depositId}&txid={$transactionId}&method={$method}";
    }
}
