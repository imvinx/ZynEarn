<?php
require_once __DIR__ . '/../../includes/functions.php';

class PayPalGateway {
    private $clientId;
    private $clientSecret;
    private $baseUrl;
    private $accessToken;

    public function __construct() {
        $this->clientId = PAYPAL_CLIENT_ID;
        $this->clientSecret = PAYPAL_CLIENT_SECRET;
        $this->baseUrl = PAYPAL_MODE === 'sandbox' ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        $this->accessToken = null;
    }

    private function getAccessToken() {
        if ($this->accessToken) return $this->accessToken;

        $ch = curl_init($this->baseUrl . '/v1/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_USERPWD => $this->clientId . ':' . $this->clientSecret,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Accept-Language: en_US'],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            logError('PayPal auth failed', ['http_code' => $httpCode]);
            throw new Exception('Failed to authenticate with PayPal');
        }

        $data = json_decode($response, true);
        $this->accessToken = $data['access_token'] ?? '';
        return $this->accessToken;
    }

    public function createPayment($amount, $currency, $orderId, $description = '') {
        $accessToken = $this->getAccessToken();

        $params = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $orderId,
                'description' => $description ?: 'Deposit to ' . APP_NAME,
                'amount' => [
                    'currency_code' => strtoupper($currency),
                    'value' => number_format($amount, 2, '.', '')
                ]
            ]],
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                        'brand_name' => APP_NAME,
                        'locale' => 'en-US',
                        'landing_page' => 'LOGIN',
                        'user_action' => 'PAY_NOW',
                        'return_url' => APP_URL . '/user/wallet.php?gateway=paypal&status=success',
                        'cancel_url' => APP_URL . '/user/deposit.php?gateway=paypal&status=cancelled'
                    ]
                ]
            ]
        ];

        $ch = curl_init($this->baseUrl . '/v2/checkout/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($params),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
                'PayPal-Request-Id: ' . $orderId
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 201) {
            logError('PayPal order creation failed', ['http_code' => $httpCode, 'response' => $response]);
            return ['success' => false, 'error' => 'Payment creation failed'];
        }

        $data = json_decode($response, true);
        $approvalUrl = '';
        foreach ($data['links'] ?? [] as $link) {
            if ($link['rel'] === 'payer-action') {
                $approvalUrl = $link['href'];
                break;
            }
        }

        return [
            'success' => true,
            'orderId' => $data['id'] ?? '',
            'approvalUrl' => $approvalUrl,
            'status' => $data['status'] ?? ''
        ];
    }

    public function capturePayment($paypalOrderId) {
        $accessToken = $this->getAccessToken();

        $ch = curl_init($this->baseUrl . '/v2/checkout/orders/' . $paypalOrderId . '/capture');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 201) {
            return ['success' => false, 'error' => 'Capture failed'];
        }

        $data = json_decode($response, true);
        $status = $data['status'] ?? '';

        $captureAmount = 0;
        $transactionId = '';
        if (!empty($data['purchase_units'][0]['payments']['captures'][0])) {
            $capture = $data['purchase_units'][0]['payments']['captures'][0];
            $captureAmount = floatval($capture['amount']['value'] ?? 0);
            $transactionId = $capture['id'] ?? '';
        }

        return [
            'success' => true,
            'paid' => $status === 'COMPLETED',
            'status' => $status,
            'amount' => $captureAmount,
            'transactionId' => $transactionId,
            'orderId' => $paypalOrderId
        ];
    }

    public function handleWebhook() {
        $body = file_get_contents('php://input');
        $headers = getallheaders();
        $webhookId = getenv('PAYPAL_WEBHOOK_ID') ?: '';

        $ch = curl_init($this->baseUrl . '/v1/notifications/verify-webhook-signature');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'auth_algo' => $headers['Paypal-Auth-Algo'] ?? '',
                'cert_url' => $headers['Paypal-Cert-Url'] ?? '',
                'transmission_id' => $headers['Paypal-Transmission-Id'] ?? '',
                'transmission_sig' => $headers['Paypal-Transmission-Sig'] ?? '',
                'transmission_time' => $headers['Paypal-Transmission-Time'] ?? '',
                'webhook_id' => $webhookId,
                'webhook_event' => json_decode($body, true)
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->getAccessToken()
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $verifyResponse = curl_exec($ch);
        curl_close($ch);

        $verifyData = json_decode($verifyResponse, true);
        if (($verifyData['verification_status'] ?? '') !== 'SUCCESS') {
            http_response_code(403);
            exit;
        }

        $event = json_decode($body, true);
        $eventType = $event['event_type'] ?? '';

        if ($eventType === 'CHECKOUT.ORDER.APPROVED') {
            $orderId = $event['resource']['id'] ?? '';
            if ($orderId) {
                $db = getDB();
                $stmt = $db->prepare("UPDATE deposits SET gateway_data = JSON_SET(COALESCE(gateway_data, '{}'), '$.approved', '1') WHERE gateway = 'paypal' AND gateway_transaction_id = ? AND status = 'pending'");
                $stmt->execute([$orderId]);
            }
        }

        if ($eventType === 'PAYMENT.CAPTURE.COMPLETED') {
            $resource = $event['resource'] ?? [];
            $transactionId = $resource['id'] ?? '';
            $amount = floatval($resource['amount']['value'] ?? 0);
            $orderId = $resource['supplementary_data']['related_ids']['order_id'] ?? '';

            $db = getDB();
            $stmt = $db->prepare("SELECT id, user_id, amount FROM deposits WHERE gateway = 'paypal' AND gateway_transaction_id = ? AND status = 'pending' LIMIT 1");
            $stmt->execute([$orderId]);
            $deposit = $stmt->fetch();

            if ($deposit) {
                $stmt = $db->prepare("UPDATE deposits SET status = 'completed', gateway_transaction_id = CONCAT(gateway_transaction_id, ',', ?), updated_at = NOW() WHERE id = ?");
                $stmt->execute([$transactionId, $deposit['id']]);

                addEarning($deposit['user_id'], 'deposit', $amount, 'Deposit via PayPal');
                $bonusAmount = min($amount * (DEPOSIT_BONUS_PERCENTAGE / 100), MAX_DEPOSIT_BONUS);
                if ($bonusAmount > 0) {
                    addEarning($deposit['user_id'], 'bonus', $bonusAmount, 'Deposit bonus (' . DEPOSIT_BONUS_PERCENTAGE . '%)');
                }
            }
        }

        http_response_code(200);
        exit;
    }
}

$action = $_GET['action'] ?? '';
$gateway = new PayPalGateway();

switch ($action) {
    case 'create':
        $amount = floatval($_POST['amount'] ?? 0);
        $currency = $_POST['currency'] ?? APP_CURRENCY;
        $userId = $_SESSION['user_id'] ?? 0;

        if ($amount <= 0 || !$userId) {
            jsonResponse(['success' => false, 'error' => 'Invalid request'], 400);
        }

        $orderId = 'PP_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(6));
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO deposits (user_id, amount, gateway, transaction_id, status) VALUES (?, ?, 'paypal', ?, 'pending')");
        $stmt->execute([$userId, $amount, $orderId]);

        $result = $gateway->createPayment($amount, $currency, $orderId);
        if ($result['success']) {
            $stmt = $db->prepare("UPDATE deposits SET gateway_transaction_id = ? WHERE transaction_id = ?");
            $stmt->execute([$result['orderId'], $orderId]);
        }
        jsonResponse($result);
        break;

    case 'capture':
        $paypalOrderId = $_POST['order_id'] ?? '';
        $token = $_POST['token'] ?? '';

        if (empty($paypalOrderId)) {
            jsonResponse(['success' => false, 'error' => 'Missing order ID'], 400);
        }

        $result = $gateway->capturePayment($paypalOrderId);
        if ($result['success'] && $result['paid']) {
            $db = getDB();
            $stmt = $db->prepare("SELECT id, user_id, amount FROM deposits WHERE gateway = 'paypal' AND gateway_transaction_id = ? AND status = 'pending' LIMIT 1");
            $stmt->execute([$paypalOrderId]);
            $deposit = $stmt->fetch();

            if ($deposit) {
                $stmt = $db->prepare("UPDATE deposits SET status = 'completed', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$deposit['id']]);

                addEarning($deposit['user_id'], 'deposit', $result['amount'], 'Deposit via PayPal');
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
