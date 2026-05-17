<?php
require_once __DIR__ . '/../../includes/functions.php';

class RazorpayGateway {
    private $keyId;
    private $keySecret;
    private $apiBase;

    public function __construct() {
        $this->keyId = RAZORPAY_KEY_ID;
        $this->keySecret = RAZORPAY_KEY_SECRET;
        $this->apiBase = 'https://api.razorpay.com/v1';
    }

    private function request($method, $endpoint, $params = []) {
        $ch = curl_init($this->apiBase . $endpoint);
        $curlOpts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $this->keyId . ':' . $this->keySecret,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 30
        ];

        if ($method === 'POST') {
            $curlOpts[CURLOPT_POST] = true;
            $curlOpts[CURLOPT_POSTFIELDS] = json_encode($params);
        }

        curl_setopt_array($ch, $curlOpts);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);

        if ($httpCode >= 400) {
            logError('Razorpay API error', ['http_code' => $httpCode, 'response' => $data]);
            return ['success' => false, 'error' => $data['error']['description'] ?? 'Razorpay API error'];
        }

        return ['success' => true, 'data' => $data];
    }

    public function createOrder($amount, $currency, $orderId, $notes = []) {
        $params = [
            'amount' => round($amount * 100),
            'currency' => strtoupper($currency),
            'receipt' => $orderId,
            'notes' => array_merge(['order_id' => $orderId], $notes)
        ];

        $result = $this->request('POST', '/orders', $params);
        if (!$result['success']) return $result;

        return [
            'success' => true,
            'orderId' => $result['data']['id'] ?? '',
            'amount' => ($result['data']['amount'] ?? 0) / 100,
            'currency' => $result['data']['currency'] ?? $currency,
            'status' => $result['data']['status'] ?? '',
            'receipt' => $result['data']['receipt'] ?? $orderId
        ];
    }

    public function verifyPayment($razorpayOrderId, $razorpayPaymentId, $razorpaySignature) {
        $expectedSignature = hash_hmac('sha256', $razorpayOrderId . '|' . $razorpayPaymentId, $this->keySecret);

        if (!hash_equals($expectedSignature, $razorpaySignature)) {
            return ['success' => false, 'error' => 'Invalid signature'];
        }

        $result = $this->request('GET', '/payments/' . $razorpayPaymentId);
        if (!$result['success']) return $result;

        $payment = $result['data'];
        $status = $payment['status'] ?? '';

        return [
            'success' => true,
            'paid' => $status === 'captured' || $status === 'authorized',
            'status' => $status,
            'amount' => ($payment['amount'] ?? 0) / 100,
            'currency' => $payment['currency'] ?? 'INR',
            'paymentId' => $razorpayPaymentId,
            'orderId' => $razorpayOrderId
        ];
    }

    public function handleWebhook() {
        $body = file_get_contents('php://input');
        $webhookSignature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

        $webhookSecret = getenv('RAZORPAY_WEBHOOK_SECRET') ?: '';
        if (!empty($webhookSecret)) {
            $expectedSignature = hash_hmac('sha256', $body, $webhookSecret);
            if (!hash_equals($expectedSignature, $webhookSignature)) {
                http_response_code(403);
                exit;
            }
        }

        $event = json_decode($body, true);
        $eventType = $event['event'] ?? '';

        if ($eventType === 'payment.captured') {
            $payment = $event['payload']['payment']['entity'] ?? [];
            $orderId = $payment['notes']['order_id'] ?? $payment['notes']['receipt'] ?? '';
            $razorpayOrderId = $payment['order_id'] ?? '';
            $amount = ($payment['amount'] ?? 0) / 100;
            $paymentId = $payment['id'] ?? '';

            if (empty($orderId)) {
                http_response_code(200);
                exit;
            }

            $db = getDB();
            $stmt = $db->prepare("SELECT id, user_id, amount FROM deposits WHERE transaction_id = ? AND gateway = 'razorpay' AND status = 'pending' LIMIT 1");
            $stmt->execute([$orderId]);
            $deposit = $stmt->fetch();

            if ($deposit) {
                $stmt = $db->prepare("UPDATE deposits SET status = 'completed', gateway_transaction_id = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$paymentId, $deposit['id']]);

                addEarning($deposit['user_id'], 'deposit', $amount, 'Deposit via Razorpay');
                $bonusAmount = min($amount * (DEPOSIT_BONUS_PERCENTAGE / 100), MAX_DEPOSIT_BONUS);
                if ($bonusAmount > 0) {
                    addEarning($deposit['user_id'], 'bonus', $bonusAmount, 'Deposit bonus (' . DEPOSIT_BONUS_PERCENTAGE . '%)');
                }
            }
        }

        if ($eventType === 'payment.failed') {
            $payment = $event['payload']['payment']['entity'] ?? [];
            $orderId = $payment['notes']['order_id'] ?? $payment['notes']['receipt'] ?? '';

            if ($orderId) {
                $db = getDB();
                $stmt = $db->prepare("UPDATE deposits SET status = 'failed', updated_at = NOW() WHERE transaction_id = ? AND gateway = 'razorpay'");
                $stmt->execute([$orderId]);
            }
        }

        http_response_code(200);
        exit;
    }
}

$action = $_GET['action'] ?? '';
$gateway = new RazorpayGateway();

switch ($action) {
    case 'create_order':
        $amount = floatval($_POST['amount'] ?? 0);
        $currency = $_POST['currency'] ?? 'INR';
        $userId = $_SESSION['user_id'] ?? 0;

        if ($amount <= 0 || !$userId) {
            jsonResponse(['success' => false, 'error' => 'Invalid request'], 400);
        }

        $orderId = 'RZP_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(6));
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO deposits (user_id, amount, gateway, transaction_id, status) VALUES (?, ?, 'razorpay', ?, 'pending')");
        $stmt->execute([$userId, $amount, $orderId]);

        $result = $gateway->createOrder($amount, $currency, $orderId, ['user_id' => (string)$userId]);
        if ($result['success']) {
            $stmt = $db->prepare("UPDATE deposits SET gateway_transaction_id = ? WHERE transaction_id = ?");
            $stmt->execute([$result['orderId'], $orderId]);
            $result['keyId'] = $this->keyId;
            $result['userName'] = $db->prepare("SELECT username FROM users WHERE id = ?")->execute([$userId]);
        }
        jsonResponse($result);
        break;

    case 'verify':
        $razorpayOrderId = $_POST['razorpay_order_id'] ?? '';
        $razorpayPaymentId = $_POST['razorpay_payment_id'] ?? '';
        $razorpaySignature = $_POST['razorpay_signature'] ?? '';

        if (empty($razorpayOrderId) || empty($razorpayPaymentId) || empty($razorpaySignature)) {
            jsonResponse(['success' => false, 'error' => 'Missing payment verification data'], 400);
        }

        $result = $gateway->verifyPayment($razorpayOrderId, $razorpayPaymentId, $razorpaySignature);
        if ($result['success'] && $result['paid']) {
            $db = getDB();
            $stmt = $db->prepare("SELECT id, user_id, amount FROM deposits WHERE gateway_transaction_id = ? AND gateway = 'razorpay' AND status = 'pending' LIMIT 1");
            $stmt->execute([$razorpayOrderId]);
            $deposit = $stmt->fetch();

            if ($deposit) {
                $stmt = $db->prepare("UPDATE deposits SET status = 'completed', gateway_transaction_id = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$razorpayPaymentId, $deposit['id']]);

                addEarning($deposit['user_id'], 'deposit', $result['amount'], 'Deposit via Razorpay');
                $bonusAmount = min($deposit['amount'] * (DEPOSIT_BONUS_PERCENTAGE / 100), MAX_DEPOSIT_BONUS);
                if ($bonusAmount > 0) {
                    addEarning($deposit['user_id'], 'bonus', $bonusAmount, 'Deposit bonus (' . DEPOSIT_BONUS_PERCENTAGE . '%)');
                }
            }
        }
        jsonResponse($result);
        break;

    case 'webhook':
        $gateway->handleWebhook();
        break;

    default:
        jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
}
