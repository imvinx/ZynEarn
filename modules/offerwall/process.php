<?php
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

$provider = $_POST['provider'] ?? $_GET['provider'] ?? '';
$userId = $_POST['user_id'] ?? $_GET['user_id'] ?? 0;
$offerId = $_POST['offer_id'] ?? $_GET['offer_id'] ?? 0;
$transactionId = $_POST['transaction_id'] ?? $_GET['transaction_id'] ?? '';
$payout = floatval($_POST['payout'] ?? $_GET['payout'] ?? 0);
$signature = $_POST['signature'] ?? $_GET['signature'] ?? '';
$status = $_POST['status'] ?? $_GET['status'] ?? 'completed';

if (empty($provider) || empty($userId) || empty($transactionId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$allowedProviders = ['offertoro', 'adgate', 'revenuewall', 'ayet', 'kiwiwall', 'cpagrip', 'persona'];
if (!in_array($provider, $allowedProviders)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid provider']);
    exit;
}

$db = getDB();

$stmt = $db->prepare("SELECT id FROM users WHERE id = ? AND status = 'active' LIMIT 1");
$stmt->execute([$userId]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit;
}

$stmt = $db->prepare("SELECT id FROM offerwall_completions WHERE transaction_id = ? AND provider = ? LIMIT 1");
$stmt->execute([$transactionId, $provider]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Duplicate completion', 'status' => 'duplicate']);
    exit;
}

$stmt = $db->prepare("SELECT secret_key FROM offerwall_providers WHERE name = ? AND status = 'active' LIMIT 1");
$stmt->execute([$provider]);
$providerData = $stmt->fetch();

if ($providerData) {
    $expectedSignature = md5($userId . $offerId . $payout . $providerData['secret_key']);
    if (!hash_equals($expectedSignature, $signature)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid signature']);
        exit;
    }
}

$stmt = $db->prepare("INSERT INTO offerwall_completions (user_id, provider, offer_id, transaction_id, payout, status, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
$result = $stmt->execute([$userId, $provider, $offerId, $transactionId, $payout, $status, getClientIP()]);

if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to record completion']);
    exit;
}

$completionId = $db->lastInsertId();

$creditStatus = ($status === 'completed') ? 'credited' : 'pending';
$description = $provider . ' offer completion #' . $offerId;
$earningResult = addEarning($userId, 'offerwall', $payout, $description, $completionId);

if ($earningResult) {
    processReferralCommission($userId, $payout, 'offerwall');
}

echo json_encode([
    'success' => true,
    'status' => $creditStatus,
    'amount' => $payout,
    'completion_id' => $completionId
]);
exit;

function processReferralCommission($userId, $amount, $source) {
    $db = getDB();
    $stmt = $db->prepare("SELECT referrer_id, level FROM referrals WHERE referred_id = ?");
    $stmt->execute([$userId]);
    $referral = $stmt->fetch();

    if (!$referral) return;

    $levels = REFERRAL_COMMISSION_LEVELS;
    $currentReferrer = $referral['referrer_id'];
    $currentLevel = 1;

    while ($currentReferrer && isset($levels[$currentLevel])) {
        $commissionRate = $levels[$currentLevel] / 100;
        $commissionAmount = $amount * $commissionRate;

        if ($commissionAmount > 0) {
            $description = $source . ' referral commission Level ' . $currentLevel;
            addEarning($currentReferrer, 'referral', $commissionAmount, $description);

            $stmt = $db->prepare("INSERT INTO referral_commissions (referrer_id, referred_id, amount, level, source) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$currentReferrer, $userId, $commissionAmount, $currentLevel, $source]);
        }

        $stmt = $db->prepare("SELECT referrer_id, level FROM referrals WHERE referred_id = ?");
        $stmt->execute([$currentReferrer]);
        $next = $stmt->fetch();

        $currentReferrer = $next ? $next['referrer_id'] : null;
        $currentLevel++;
    }
}
