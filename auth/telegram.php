<?php
require_once __DIR__ . '/../includes/auth_middleware.php';

if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/user/dashboard.php');
    exit;
}

$botToken = getenv('TELEGRAM_BOT_TOKEN') ?: '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$id = $input['id'] ?? '';
$firstName = $input['first_name'] ?? '';
$lastName = $input['last_name'] ?? '';
$username = $input['username'] ?? '';
$photoUrl = $input['photo_url'] ?? '';
$authDate = $input['auth_date'] ?? '';
$hash = $input['hash'] ?? '';

if (empty($id) || empty($authDate) || empty($hash)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
    exit;
}

$checkArray = [
    'auth_date' => $authDate,
    'first_name' => $firstName,
    'id' => $id,
    'last_name' => $lastName,
    'photo_url' => $photoUrl,
    'username' => $username
];

$checkArray = array_filter($checkArray, function($v) {
    return $v !== '' && $v !== null;
});
ksort($checkArray);

$checkString = '';
foreach ($checkArray as $key => $value) {
    $checkString .= $key . '=' . $value . "\n";
}
$checkString = rtrim($checkString, "\n");

$secretKey = hash('sha256', $botToken, true);
$expectedHash = hash_hmac('sha256', $checkString, $secretKey);

if (strcmp($hash, $expectedHash) !== 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid authentication data.']);
    exit;
}

if (abs(time() - (int)$authDate) > 86400) {
    echo json_encode(['success' => false, 'error' => 'Authentication data expired.']);
    exit;
}

$displayName = $firstName;
if (!empty($lastName)) {
    $displayName .= ' ' . $lastName;
}
$telegramUsername = $username;
$avatar = $photoUrl ?: '';

try {
    $db = getDB();

    $stmt = $db->prepare("SELECT * FROM users WHERE telegram_id = ? LIMIT 1");
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    if ($user) {
        if ($user['status'] === 'suspended' || $user['status'] === 'banned') {
            echo json_encode(['success' => false, 'error' => 'Your account has been ' . $user['status'] . '.']);
            exit;
        }

        $stmt = $db->prepare("UPDATE users SET last_login = NOW(), last_ip = ?, avatar = COALESCE(?, avatar) WHERE id = ?");
        $stmt->execute([getClientIP(), $avatar, $user['id']]);

        createSession($user['id']);
        echo json_encode(['success' => true, 'redirect' => APP_URL . '/user/dashboard.php']);
        exit;
    }

    $genUsername = !empty($telegramUsername) ? $telegramUsername : 'tg_' . $id;
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$genUsername]);
    if ($stmt->fetch()) {
        $genUsername = 'tg_' . $id . '_' . random_int(100, 999);
    }

    $referralCode = createReferralCode();
    $stmt = $db->prepare("INSERT INTO users (username, telegram_id, avatar, referral_code, email_verified_at, status, joined_at, last_ip) VALUES (?, ?, ?, ?, NOW(), 'active', NOW(), ?)");
    $stmt->execute([$genUsername, $id, $avatar, $referralCode, getClientIP()]);
    $userId = $db->lastInsertId();

    createSession($userId);
    echo json_encode(['success' => true, 'redirect' => APP_URL . '/user/dashboard.php', 'message' => 'Welcome to ZynEarn!']);
} catch (Exception $e) {
    logError('Telegram auth error', ['message' => $e->getMessage()]);
    echo json_encode(['success' => false, 'error' => 'Authentication failed. Please try again.']);
}
