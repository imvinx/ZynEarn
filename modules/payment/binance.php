<?php
require_once __DIR__ . '/../../includes/functions.php';

class BinanceGateway {
    private $apiKey;
    private $secretKey;
    private $baseUrl;
    private $merchantCode;

    public function __construct() {
        $this->apiKey = BINANCE_API_KEY;
        $this->secretKey = BINANCE_SECRET_KEY;
        $this->merchantCode = getenv('BINANCE_MERCHANT_CODE') ?: '';
        $this->baseUrl = getenv('BINANCE_SANDBOX') ? 'https://api.sandbox.binance.com' : 'https://api.binance.com';
    }

    public function createPayment($amount, $currency, $orderId, $description = '') {
        $timestamp = round(microtime(true) * 1000);
        $params = [
            'env' => ['terminalType' => 'WEB'],
            'merchantTradeNo' => $orderId,
            'orderAmount' => number_format($amount, 2, '.', ''),
            'currency' => strtoupper($currency),
            'goods' => [
                'goodsType' => '02',
                'goodsCategory' => 'Z000',
                'referenceGoodsId' => $orderId,
                'goodsName' => $description ?: 'Deposit to ' . APP_NAME,
                'goodsDetail' => $description ?: 'Account deposit'
            ],
            'returnUrl' => APP_URL . '/user/wallet.php',
            'cancelUrl' => APP_URL . '/user/deposit.php'
        ];

        $payload = json_encode($params);
        $signature = $this->generateSignature($payload, $timestamp);

        $ch = curl_init($this->baseUrl . '/binancepay/openapi/v2/order');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'BinancePay-Timestamp: ' . $timestamp,
                'BinancePay-Nonce: ' . bin2hex(random_bytes(16)),
                'BinancePay-Certificate-SN: ' . $this->apiKey,
                'BinancePay-Signature: ' . $signature
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            logError('Binance payment creation failed', ['http_code' => $httpCode, 'response' => $response]);
            return ['success' => false, 'error' => 'Payment gateway error'];
        }

        $data = json_decode($response, true);
        if (($data['status'] ?? '') !== 'SUCCESS') {
            return ['success' => false, 'error' => $data['errorMessage'] ?? 'Payment creation failed'];
        }

        return [
            'success' => true,
            'checkoutUrl' => $data['data']['checkoutUrl'] ?? '',
            'prepayId' => $data['data']['prepayId'] ?? '',
            'transactionId' => $data['data']['transactionId'] ?? ''
        ];
    }

    public function verifyPayment($prepayId) {
        $timestamp = round(microtime(true) * 1000);
        $params = ['prepayId' => $prepayId];
        $payload = json_encode($params);
        $signature = $this->generateSignature($payload, $timestamp);

        $ch = curl_init($this->baseUrl . '/binancepay/openapi/v2/order/query');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'BinancePay-Timestamp: ' . $timestamp,
                'BinancePay-Nonce: ' . bin2hex(random_bytes(16)),
                'BinancePay-Certificate-SN: ' . $this->apiKey,
                'BinancePay-Signature: ' . $signature
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return ['success' => false, 'error' => 'Query failed'];
        }

        $data = json_decode($response, true);
        if (($data['status'] ?? '') !== 'SUCCESS') {
            return ['success' => false, 'error' => $data['errorMessage'] ?? 'Query failed'];
        }

        $orderData = $data['data'];
        $status = $orderData['status'] ?? '';

        return [
            'success' => true,
            'paid' => $status === 'PAID' || $status === 'COMPLETED',
            'status' => $status,
            'amount' => $orderData['totalFee'] ?? 0,
            'currency' => $orderData['orderCurrency'] ?? 'USDT',
            'transactionId' => $orderData['transactionId'] ?? ''
        ];
    }

    public function handleWebhook() {
        $body = file_get_contents('php://input');
        $headers = getallheaders();
        $signature = $headers['Binancepay-Signature'] ?? '';
        $timestamp = $headers['Binancepay-Timestamp'] ?? '';

        if (empty($signature) || empty($timestamp)) {
            http_response_code(400);
            echo json_encode(['returnCode' => 'FAILED', 'returnMessage' => 'Missing signature']);
            exit;
        }

        $expectedSign = $this->generateSignature($body, $timestamp);
        if (!hash_equals($expectedSign, $signature)) {
            http_response_code(403);
            echo json_encode(['returnCode' => 'FAILED', 'returnMessage' => 'Invalid signature']);
            exit;
        }

        $event = json_decode($body, true);
        if (!$event || ($event['bizStatus'] ?? '') !== 'PAY_SUCCESS') {
            http_response_code(200);
            echo json_encode(['returnCode' => 'SUCCESS', 'returnMessage' => 'OK']);
            exit;
        }

        $data = $event['data'] ?? [];
        $orderId = $data['merchantTradeNo'] ?? '';
        $amount = floatval($data['totalFee'] ?? 0);
        $currency = $data['orderCurrency'] ?? 'USDT';
        $transactionId = $data['transactionId'] ?? '';

        if (empty($orderId)) {
            http_response_code(200);
            echo json_encode(['returnCode' => 'SUCCESS', 'returnMessage' => 'OK']);
            exit;
        }

        $db = getDB();
        $stmt = $db->prepare("SELECT id, status FROM deposits WHERE transaction_id = ? AND gateway = 'binance' LIMIT 1");
        $stmt->execute([$orderId]);
        $deposit = $stmt->fetch();

        if (!$deposit || $deposit['status'] === 'completed') {
            http_response_code(200);
            echo json_encode(['returnCode' => 'SUCCESS', 'returnMessage' => 'OK']);
            exit;
        }

        $stmt = $db->prepare("UPDATE deposits SET status = 'completed', gateway_transaction_id = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$transactionId, $deposit['id']]);

        $stmt = $db->prepare("SELECT user_id, amount FROM deposits WHERE id = ?");
        $stmt->execute([$deposit['id']]);
        $depositData = $stmt->fetch();

        if ($depositData) {
            $bonusAmount = min($depositData['amount'] * (DEPOSIT_BONUS_PERCENTAGE / 100), MAX_DEPOSIT_BONUS);
            addEarning($depositData['user_id'], 'deposit', $depositData['amount'], 'Deposit via Binance Pay');
            if ($bonusAmount > 0) {
                addEarning($depositData['user_id'], 'bonus', $bonusAmount, 'Deposit bonus (' . DEPOSIT_BONUS_PERCENTAGE . '%)');
            }
        }

        http_response_code(200);
        echo json_encode(['returnCode' => 'SUCCESS', 'returnMessage' => 'OK']);
        exit;
    }

    private function generateSignature($payload, $timestamp) {
        $message = $timestamp . "\n" . $this->merchantCode . "\n" . $payload . "\n";
        return strtoupper(hash_hmac('sha512', $message, $this->secretKey));
    }
}

$action = $_GET['action'] ?? '';
$gateway = new BinanceGateway();

switch ($action) {
    case 'create':
        $amount = floatval($_POST['amount'] ?? 0);
        $currency = $_POST['currency'] ?? 'USDT';
        $userId = $_SESSION['user_id'] ?? 0;

        if ($amount <= 0 || !$userId) {
            jsonResponse(['success' => false, 'error' => 'Invalid request'], 400);
        }

        $orderId = 'DEP_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(8));
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO deposits (user_id, amount, gateway, transaction_id, status) VALUES (?, ?, 'binance', ?, 'pending')");
        $stmt->execute([$userId, $amount, $orderId]);

        $result = $gateway->createPayment($amount, $currency, $orderId, 'Deposit to ' . APP_NAME);
        if ($result['success']) {
            $stmt = $db->prepare("UPDATE deposits SET gateway_data = ? WHERE transaction_id = ?");
            $stmt->execute([json_encode(['prepayId' => $result['prepayId']]), $orderId]);
        }
        jsonResponse($result);
        break;

    case 'verify':
        $prepayId = $_POST['prepay_id'] ?? '';
        if (empty($prepayId)) {
            jsonResponse(['success' => false, 'error' => 'Missing prepay ID'], 400);
        }
        jsonResponse($gateway->verifyPayment($prepayId));
        break;

    case 'webhook':
        $gateway->handleWebhook();
        break;

    default:
        jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
}
