<?php
require_once __DIR__ . '/../includes/auth_middleware.php';

if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/user/dashboard.php');
    exit;
}

$clientId = getenv('GOOGLE_CLIENT_ID') ?: '';
$clientSecret = getenv('GOOGLE_CLIENT_SECRET') ?: '';
$redirectUri = APP_URL . '/auth/google.php';

$action = $_GET['action'] ?? 'login';

if ($action === 'callback') {
    header('Content-Type: application/json');

    $code = $_GET['code'] ?? '';

    if (empty($code)) {
        echo json_encode(['success' => false, 'error' => 'Authorization code missing.']);
        exit;
    }

    $tokenUrl = 'https://oauth2.googleapis.com/token';
    $postData = [
        'code' => $code,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code'
    ];

    $ch = curl_init($tokenUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    $tokenResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        echo json_encode(['success' => false, 'error' => 'Failed to get access token.']);
        exit;
    }

    $tokenData = json_decode($tokenResponse, true);
    $accessToken = $tokenData['access_token'] ?? '';

    if (empty($accessToken)) {
        echo json_encode(['success' => false, 'error' => 'Access token missing.']);
        exit;
    }

    $userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
    $ch = curl_init($userInfoUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    $userResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        echo json_encode(['success' => false, 'error' => 'Failed to get user info.']);
        exit;
    }

    $googleUser = json_decode($userResponse, true);
    $googleId = $googleUser['id'] ?? '';
    $email = $googleUser['email'] ?? '';
    $name = $googleUser['name'] ?? '';
    $avatar = $googleUser['picture'] ?? '';

    if (empty($googleId) || empty($email)) {
        echo json_encode(['success' => false, 'error' => 'Failed to retrieve user information.']);
        exit;
    }

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM users WHERE google_id = ? LIMIT 1");
        $stmt->execute([$googleId]);
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

        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $existingUser = $stmt->fetch();

        if ($existingUser) {
            $stmt = $db->prepare("UPDATE users SET google_id = ?, email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?");
            $stmt->execute([$googleId, $existingUser['id']]);

            if ($existingUser['status'] === 'suspended' || $existingUser['status'] === 'banned') {
                echo json_encode(['success' => false, 'error' => 'Your account has been ' . $existingUser['status'] . '.']);
                exit;
            }

            createSession($existingUser['id']);
            echo json_encode(['success' => true, 'redirect' => APP_URL . '/user/dashboard.php']);
            exit;
        }

        $username = strtolower(str_replace(' ', '_', $name)) . '_' . substr($googleId, 0, 6);
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $username = $username . '_' . random_int(100, 999);
        }

        $referralCode = createReferralCode();
        $stmt = $db->prepare("INSERT INTO users (username, email, google_id, avatar, referral_code, email_verified_at, status, joined_at, last_ip) VALUES (?, ?, ?, ?, ?, NOW(), 'active', NOW(), ?)");
        $stmt->execute([$username, $email, $googleId, $avatar, $referralCode, getClientIP()]);
        $userId = $db->lastInsertId();

        createSession($userId);
        echo json_encode(['success' => true, 'redirect' => APP_URL . '/user/dashboard.php', 'message' => 'Welcome to ZynEarn!']);
    } catch (Exception $e) {
        logError('Google OAuth error', ['message' => $e->getMessage()]);
        echo json_encode(['success' => false, 'error' => 'Authentication failed. Please try again.']);
    }
    exit;
}

$params = http_build_query([
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => 'email profile',
    'access_type' => 'online',
    'prompt' => 'select_account'
]);

$authUrl = 'https://accounts.google.com/o/oauth2/auth?' . $params;
header('Location: ' . $authUrl);
exit;
