<?php
require_once __DIR__ . '/functions.php';

session_name(SESSION_NAME);
session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME,
    'path' => COOKIE_PATH,
    'domain' => COOKIE_DOMAIN,
    'secure' => COOKIE_SECURE,
    'httponly' => COOKIE_HTTP_ONLY,
    'samesite' => COOKIE_SAME_SITE
]);
session_start();

if (isset($_COOKIE[SESSION_NAME]) && !isset($_SESSION['user_id'])) {
    $db = getDB();
    $stmt = $db->prepare("SELECT user_id FROM user_sessions WHERE session_token = ? AND is_active = 1 AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$_COOKIE[SESSION_NAME]]);
    $session = $stmt->fetch();
    
    if ($session) {
        $_SESSION['user_id'] = $session['user_id'];
    }
}
