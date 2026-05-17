<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/payments.php';
require_once __DIR__ . '/../config/earnings.php';

function getDB() {
    return Database::getInstance()->getConnection();
}

function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(strip_tags(trim($input ?? '')), ENT_QUOTES, 'UTF-8');
}

function escape($input) {
    $db = getDB();
    return substr($db->quote($input), 1, -1);
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validateUsername($username) {
    return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
}

function generateToken($length = TOKEN_LENGTH) {
    return bin2hex(random_bytes($length / 2));
}

function generateOTP($length = OTP_LENGTH) {
    $otp = '';
    for ($i = 0; $i < $length; $i++) {
        $otp .= random_int(0, 9);
    }
    return $otp;
}

function hashPassword($password) {
    return password_hash(PEPPER . $password, HASH_ALGO, HASH_OPTIONS);
}

function verifyPassword($password, $hash) {
    return password_verify(PEPPER . $password, $hash);
}

function createSession($userId) {
    $db = getDB();
    $token = generateToken(128);
    $expires = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
    
    $stmt = $db->prepare("INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, device_type, expires_at) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $userId,
        $token,
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        detectDevice(),
        $expires
    ]);
    
    $_SESSION['user_id'] = $userId;
    $_SESSION['session_token'] = $token;
    $_SESSION['login_time'] = time();
    
    setcookie(SESSION_NAME, $token, [
        'expires' => time() + SESSION_LIFETIME,
        'path' => COOKIE_PATH,
        'domain' => COOKIE_DOMAIN,
        'secure' => COOKIE_SECURE,
        'httponly' => COOKIE_HTTP_ONLY,
        'samesite' => COOKIE_SAME_SITE
    ]);
    
    return $token;
}

function destroySession() {
    if (isset($_SESSION['session_token'])) {
        $db = getDB();
        $stmt = $db->prepare("UPDATE user_sessions SET is_active = 0 WHERE session_token = ?");
        $stmt->execute([$_SESSION['session_token']]);
    }
    
    $_SESSION = [];
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    if (isset($_COOKIE[SESSION_NAME])) {
        setcookie(SESSION_NAME, '', time() - 3600, COOKIE_PATH);
    }
    session_destroy();
}

function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function isLoggedIn() {
    return getCurrentUser() !== null;
}

function isAdmin() {
    $user = getCurrentUser();
    return $user && $user['role'] === 'admin';
}

function requireAuth() {
    if (!isLoggedIn()) {
        if (isAjaxRequest()) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized', 'redirect' => '/auth/login.php']);
            exit;
        }
        header('Location: ' . APP_URL . '/auth/login.php');
        exit;
    }
}

function requireAdmin() {
    requireAuth();
    if (!isAdmin()) {
        if (isAjaxRequest()) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
}

function detectDevice() {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (preg_match('/mobile|android|iphone|ipad|ipod/i', $ua)) {
        return 'mobile';
    } elseif (preg_match('/tablet|ipad/i', $ua)) {
        return 'tablet';
    }
    return 'desktop';
}

function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function jsonResponse($data, $statusCode = 200) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function errorResponse($message, $statusCode = 400) {
    return jsonResponse(['success' => false, 'error' => $message], $statusCode);
}

function successResponse($data = [], $message = 'Success') {
    return jsonResponse(['success' => true, 'message' => $message, 'data' => $data]);
}

function getClientIP() {
    $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '127.0.0.1';
}

function isVPN($ip) {
    $vpnIps = ['10.', '172.16.', '192.168.', '127.'];
    foreach ($vpnIps as $prefix) {
        if (strpos($ip, $prefix) === 0) return true;
    }
    return false;
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateToken(CSRF_TOKEN_LENGTH);
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf($token) {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function formatCurrency($amount, $currency = APP_CURRENCY) {
    return APP_CURRENCY_SYMBOL . number_format($amount, 2);
}

function formatNumber($number) {
    if ($number >= 1000000) {
        return number_format($number / 1000000, 1) . 'M';
    } elseif ($number >= 1000) {
        return number_format($number / 1000, 1) . 'K';
    }
    return number_format($number);
}

function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M d, Y', $timestamp);
}

function getUserBalance($userId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total_earned FROM earnings WHERE user_id = ? AND status = 'credited'");
    $stmt->execute([$userId]);
    $earned = $stmt->fetch()['total_earned'];
    
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total_withdrawn FROM withdrawals WHERE user_id = ? AND status = 'completed'");
    $stmt->execute([$userId]);
    $withdrawn = $stmt->fetch()['total_withdrawn'];
    
    return $earned - $withdrawn;
}

function addEarning($userId, $type, $amount, $description = '', $referenceId = null) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO earnings (user_id, type, amount, status, description, reference_id) VALUES (?, ?, ?, 'credited', ?, ?)");
    $result = $stmt->execute([$userId, $type, $amount, $description, $referenceId]);
    
    if ($result) {
        $xpAmount = floor($amount * XP_PER_EARN * 100);
        if ($xpAmount > 0) {
            addXP($userId, $xpAmount);
        }
        checkAchievements($userId);
    }
    
    return $result;
}

function addXP($userId, $amount) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET xp_points = xp_points + ? WHERE id = ?");
    $stmt->execute([$amount, $userId]);
    updateLevel($userId);
}

function updateLevel($userId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT xp_points FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    $level = 1;
    foreach (LEVEL_THRESHOLDS as $lvl => $threshold) {
        if ($user['xp_points'] >= $threshold) {
            $level = $lvl;
        }
    }
    
    $stmt = $db->prepare("UPDATE users SET level = ? WHERE id = ?");
    $stmt->execute([$level, $userId]);
}

function checkAchievements($userId) {
    $db = getDB();
    $achievements = $db->query("SELECT * FROM achievements")->fetchAll();
    
    foreach ($achievements as $achievement) {
        $stmt = $db->prepare("SELECT id FROM user_achievements WHERE user_id = ? AND achievement_id = ?");
        $stmt->execute([$userId, $achievement['id']]);
        if ($stmt->fetch()) continue;
        
        $unlocked = false;
        switch ($achievement['requirement_type']) {
            case 'earnings':
                $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM earnings WHERE user_id = ? AND status = 'credited'");
                $stmt->execute([$userId]);
                $total = $stmt->fetchColumn();
                $unlocked = $total >= $achievement['requirement_value'];
                break;
            case 'referrals':
                $stmt = $db->prepare("SELECT COUNT(*) FROM referrals WHERE referrer_id = ?");
                $stmt->execute([$userId]);
                $unlocked = $stmt->fetchColumn() >= $achievement['requirement_value'];
                break;
            case 'level':
                $stmt = $db->prepare("SELECT level FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $unlocked = $stmt->fetchColumn() >= $achievement['requirement_value'];
                break;
            case 'streak':
                $stmt = $db->prepare("SELECT streak_days FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $unlocked = $stmt->fetchColumn() >= $achievement['requirement_value'];
                break;
        }
        
        if ($unlocked) {
            $stmt = $db->prepare("INSERT INTO user_achievements (user_id, achievement_id) VALUES (?, ?)");
            $stmt->execute([$userId, $achievement['id']]);
            
            if ($achievement['reward_amount'] > 0) {
                addEarning($userId, 'achievement', $achievement['reward_amount'], 'Achievement: ' . $achievement['name']);
            }
            if ($achievement['xp_reward'] > 0) {
                addXP($userId, $achievement['xp_reward']);
            }
            
            addNotification($userId, 'achievement', 'Achievement Unlocked!', 'You unlocked: ' . $achievement['name']);
        }
    }
}

function addNotification($userId, $type, $title, $message, $data = null) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO notifications (user_id, type, title, message, data) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$userId, $type, $title, $message, $data ? json_encode($data) : null]);
}

function logAdminAction($adminId, $action, $details = []) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO admin_logs (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$adminId, $action, json_encode($details), getClientIP()]);
}

function getSetting($key, $default = null) {
    static $settings = null;
    if ($settings === null) {
        $db = getDB();
        $result = $db->query("SELECT `key`, `value` FROM site_settings")->fetchAll();
        $settings = [];
        foreach ($result as $row) {
            $settings[$row['key']] = $row['value'];
        }
    }
    return $settings[$key] ?? $default;
}

function updateSetting($key, $value) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO site_settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?");
    return $stmt->execute([$key, $value, $value]);
}

function createReferralCode() {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $code = '';
    for ($i = 0; $i < 8; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

function getMembershipMultiplier($tier) {
    $multipliers = VIP_MULTIPLIERS;
    return $multipliers[$tier] ?? 1.0;
}

function applyMultiplier($amount, $tier) {
    return $amount * getMembershipMultiplier($tier);
}

function isRateLimited($key, $maxRequests = RATE_LIMIT_REQUESTS, $window = RATE_LIMIT_WINDOW) {
    if (!RATE_LIMIT_ENABLED) return false;
    
    $cacheFile = CACHE_DIR . '/ratelimit_' . md5($key) . '.cache';
    $now = time();
    
    $attempts = [];
    if (file_exists($cacheFile)) {
        $attempts = json_decode(file_get_contents($cacheFile), true) ?: [];
        $attempts = array_filter($attempts, function($t) use ($now, $window) {
            return $t > ($now - $window);
        });
    }
    
    if (count($attempts) >= $maxRequests) {
        return true;
    }
    
    $attempts[] = $now;
    file_put_contents($cacheFile, json_encode($attempts));
    return false;
}

function logError($message, $context = []) {
    $logFile = __DIR__ . '/../system/logs/error_' . date('Y-m-d') . '.log';
    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message . ' | Context: ' . json_encode($context) . PHP_EOL;
    file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}
