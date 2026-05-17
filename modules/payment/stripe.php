<?php
require_once __DIR__ . '/../../includes/functions.php';

class StripeGateway {
    private $secretKey;
    private $webhookSecret;
    private $apiBase;

    public function __construct() {
        $this->secretKey = STRIPE_SECRET_KEY;
        $this->webhookSecret = STRIPE_WEBHOOK_SECRET;
        $this->apiBase = 'https://api.stripe.com/v1';
    }

    private function request($method, $endpoint, $params = []) {
        $ch = curl_init($this->apiBase . $endpoint);
        $curlOpts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->secretKey,
                'Content-Type: application/x-www-form-urlencoded',
                'Stripe-Version: 2023-10-16'
            ],
            CURLOPT_TIMEOUT => 30
        ];

        if ($method === 'POST') {
            $curlOpts[CURLOPT_POST] = true;
            $curlOpts[CURLOPT_POSTFIELDS] = http_build_query($params);
        } elseif ($method === 'GET') {
            $curlOpts[CURLOPT_HTTPGET] = true;
            if (!empty($params)) {
                $curlOpts[CURLOPT_URL] .= '?' . http_build_query($params);
            }
        }

        curl_setopt_array($ch, $curlOpts);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);

        if ($httpCode >= 400) {
            logError('Stripe API error', ['http_code' => $httpCode, 'response' => $data]);
            return ['success' => false, 'error' => $data['error']['message'] ?? 'Stripe API error'];
        }

        return ['success' => true, 'data' => $data];
    }

    public function createPaymentIntent($amount, $currency, $orderId, $metadata = []) {
        $params = [
            'amount' => round($amount * 100),
            'currency' => strtolower($currency),
            'description' => 'Deposit to ' . APP_NAME,
            'metadata' => array_merge(['order_id' => $orderId], $metadata),
            'automatic_payment_methods' => ['enabled' => true]
        ];

        $result = $this->request('POST', '/payment_intents', $params);
        if (!$result['success']) return $result;

        return [
            'success' => true,
            'clientSecret' => $result['data']['client_secret'] ?? '',
            'paymentIntentId' => $result['data']['id'] ?? '',
            'amount' => $result['data']['amount'] / 100,
            'status' => $result['data']['status'] ?? ''
        ];
    }

    public function confirmPayment($paymentIntentId) {
        $result = $this->request('GET', '/payment_intents/' . $paymentIntentId);
        if (!$result['success']) return $result;

        $data = $result['data'];
        return [
            'success' => true,
            'paid' => $data['status'] === 'succeeded',
            'status' => $data['status'] ?? '',
            'amount' => ($data['amount_received'] ?? 0) / 100,
            'currency' => $data['currency'] ?? '',
            'paymentIntentId' => $paymentIntentId
        ];
    }

    public function handleWebhook() {
        $body = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

        if (empty($this->webhookSecret)) {
            http_response_code(500);
            exit;
        }

        $payload = $body;
        $sigHeader = $signature;

        $timestamp = 0;
        $signatures = [];
        foreach (explode(',', $sigHeader) as $part) {
            $part = trim($part);
            if (strpos($part, 't=') === 0) {
                $timestamp = intval(substr($part, 2));
            } elseif (strpos($part, 'v1=') === 0) {
                $signatures[] = substr($part, 3);
            }
        }

        if (empty($signatures)) {
            http_response_code(403);
            exit;
        }

        $signedPayload = $timestamp . '.' . $payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $this->webhookSecret);

        $valid = false;
        foreach ($signatures as $sig) {
            if (hash_equals($expectedSignature, $sig)) {
                $valid = true;
                break;
            }
        }

        if (!$valid) {
            http_response_code(403);
            exit;
        }

        if (abs(time() - $timestamp) > 300) {
            http_response_code(400);
            exit;
        }

        $event = json_decode($body, true);
        $eventType = $event['type'] ?? '';

        if ($eventType === 'payment_intent.succeeded') {
            $paymentIntent = $event['data']['object'] ?? [];
            $orderId = $paymentIntent['metadata']['order_id'] ?? '';
            $amount = ($paymentIntent['amount_received'] ?? 0) / 100;
            $paymentIntentId = $paymentIntent['id'] ?? '';

            if (empty($orderId)) {
                http_response_code(200);
                exit;
            }

            $db = getDB();
            $stmt = $db->prepare("SELECT id, user_id, amount FROM deposits WHERE transaction_id = ? AND gateway = 'stripe' AND status = 'pending' LIMIT 1");
            $stmt->execute([$orderId]);
            $deposit = $stmt->fetch();

            if ($deposit) {
                $stmt = $db->prepare("UPDATE deposits SET status = 'completed', gateway_transaction_id = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$paymentIntentId, $deposit['id']]);

                addEarning($deposit['user_id'], 'deposit', $amount, 'Deposit via Stripe');
                $bonusAmount = min($amount * (DEPOSIT_BONUS_PERCENTAGE / 100), MAX_DEPOSIT_BONUS);
                if ($bonusAmount > 0) {
                    addEarning($deposit['user_id'], 'bonus', $bonusAmount, 'Deposit bonus (' . DEPOSIT_BONUS_PERCENTAGE . '%)');
                }
            }
        }

        if ($eventType === 'payment_intent.payment_failed') {
            $paymentIntent = $event['data']['object'] ?? [];
            $orderId = $paymentIntent['metadata']['order_id'] ?? '';

            if ($orderId) {
                $db = getDB();
                $stmt = $db->prepare("UPDATE deposits SET status = 'failed', updated_at = NOW() WHERE transaction_id = ? AND gateway = 'stripe'");
                $stmt->execute([$orderId]);
            }
        }

        http_response_code(200);
        exit;
    }
}

$action = $_GET['action'] ?? '';
$gateway = new StripeGateway();

switch ($action) {
    case 'create_intent':
        $amount = floatval($_POST['amount'] ?? 0);
        $currency = $_POST['currency'] ?? APP_CURRENCY;
        $userId = $_SESSION['user_id'] ?? 0;

        if ($amount <= 0 || !$userId) {
            jsonResponse(['success' => false, 'error' => 'Invalid request'], 400);
        }

        $orderId = 'STR_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(6));
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO deposits (user_id, amount, gateway, transaction_id, status) VALUES (?, ?, 'stripe', ?, 'pending')");
        $stmt->execute([$userId, $amount, $orderId]);

        $result = $gateway->createPaymentIntent($amount, $currency, $orderId, ['user_id' => (string)$userId]);
        if ($result['success']) {
            $stmt = $db->prepare("UPDATE deposits SET gateway_transaction_id = ? WHERE transaction_id = ?");
            $stmt->execute([$result['paymentIntentId'], $orderId]);
        }
        jsonResponse($result);
        break;

    case 'confirm':
        $paymentIntentId = $_POST['payment_intent_id'] ?? '';
        if (empty($paymentIntentId)) {
            jsonResponse(['success' => false, 'error' => 'Missing payment intent ID'], 400);
        }

        $result = $gateway->confirmPayment($paymentIntentId);
        if ($result['success'] && $result['paid']) {
            $db = getDB();
            $stmt = $db->prepare("SELECT id, user_id, amount FROM deposits WHERE gateway_transaction_id = ? AND gateway = 'stripe' AND status = 'pending' LIMIT 1");
            $stmt->execute([$paymentIntentId]);
            $deposit = $stmt->fetch();

            if ($deposit) {
                $stmt = $db->prepare("UPDATE deposits SET status = 'completed', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$deposit['id']]);

                addEarning($deposit['user_id'], 'deposit', $result['amount'], 'Deposit via Stripe');
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
