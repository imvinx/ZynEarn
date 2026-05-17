<?php
require_once __DIR__ . '/../includes/auth_middleware.php';

if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/user/dashboard.php');
    exit;
}

$clientId = getenv('DISCORD_CLIENT_ID') ?: '';
$clientSecret = getenv('DISCORD_CLIENT_SECRET') ?: '';
$redirectUri = APP_URL . '/auth/discord.php';

$action = $_GET['action'] ?? 'login';

if ($action === 'callback') {
    header('Content-Type: application/json');

    $code = $_GET['code'] ?? '';

    if (empty($code)) {
        echo json_encode(['success' => false, 'error' => 'Authorization code missing.']);
        exit;
    }

    $tokenUrl = 'https://discord.com/api/oauth2/token';
    $postData = [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $redirectUri
    ];

    $ch = curl_init($tokenUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
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

    $userUrl = 'https://discord.com/api/users/@me';
    $ch = curl_init($userUrl);
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

    $discordUser = json_decode($userResponse, true);
    $discordId = $discordUser['id'] ?? '';
    $email = $discordUser['email'] ?? '';
    $username = $discordUser['username'] ?? '';
    $discriminator = $discordUser['discriminator'] ?? '0';
    $avatarHash = $discordUser['avatar'] ?? '';

    if (empty($discordId)) {
        echo json_encode(['success' => false, 'error' => 'Failed to retrieve user information.']);
        exit;
    }

    $avatar = '';
    if (!empty($avatarHash)) {
        $ext = strpos($avatarHash, 'a_') === 0 ? 'gif' : 'png';
        $avatar = "https://cdn.discordapp.com/avatars/{$discordId}/{$avatarHash}.{$ext}";
    }

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM users WHERE discord_id = ? LIMIT 1");
        $stmt->execute([$discordId]);
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

        if (!empty($email)) {
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $existingUser = $stmt->fetch();

            if ($existingUser) {
                $stmt = $db->prepare("UPDATE users SET discord_id = ?, email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?");
                $stmt->execute([$discordId, $existingUser['id']]);

                if ($existingUser['status'] === 'suspended' || $existingUser['status'] === 'banned') {
                    echo json_encode(['success' => false, 'error' => 'Your account has been ' . $existingUser['status'] . '.']);
                    exit;
                }

                createSession($existingUser['id']);
                echo json_encode(['success' => true, 'redirect' => APP_URL . '/user/dashboard.php']);
                exit;
            }
        }

        $genUsername = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $username));
        if ($discriminator !== '0' && $discriminator !== '0000') {
            $genUsername .= '_' . $discriminator;
        }
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$genUsername]);
        if ($stmt->fetch()) {
            $genUsername .= '_' . random_int(100, 999);
        }

        $referralCode = createReferralCode();
        $stmt = $db->prepare("INSERT INTO users (username, email, discord_id, avatar, referral_code, email_verified_at, status, joined_at, last_ip) VALUES (?, ?, ?, ?, ?, NOW(), 'active', NOW(), ?)");
        $stmt->execute([$genUsername, $email, $discordId, $avatar, $referralCode, getClientIP()]);
        $userId = $db->lastInsertId();

        createSession($userId);
        echo json_encode(['success' => true, 'redirect' => APP_URL . '/user/dashboard.php', 'message' => 'Welcome to ZynEarn!']);
    } catch (Exception $e) {
        logError('Discord OAuth error', ['message' => $e->getMessage()]);
        echo json_encode(['success' => false, 'error' => 'Authentication failed. Please try again.']);
    }
    exit;
}

$params = http_build_query([
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => 'identify email'
]);

$authUrl = 'https://discord.com/api/oauth2/authorize?' . $params;
header('Location: ' . $authUrl);
exit;
