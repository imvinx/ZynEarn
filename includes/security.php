<?php
class Security {
    public static function sanitizeOutput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeOutput'], $data);
        }
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    
    public static function validateInput($data, $type = 'string') {
        switch ($type) {
            case 'email':
                return filter_var($data, FILTER_VALIDATE_EMAIL);
            case 'url':
                return filter_var($data, FILTER_VALIDATE_URL);
            case 'int':
                return filter_var($data, FILTER_VALIDATE_INT);
            case 'float':
                return filter_var($data, FILTER_VALIDATE_FLOAT);
            case 'ip':
                return filter_var($data, FILTER_VALIDATE_IP);
            case 'boolean':
                return filter_var($data, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            default:
                return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
        }
    }
    
    public static function encryptData($data, $key) {
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt(json_encode($data), 'aes-256-cbc', $key, 0, $iv);
        return base64_encode($iv . '::' . $encrypted);
    }
    
    public static function decryptData($data, $key) {
        $parts = explode('::', base64_decode($data), 2);
        if (count($parts) !== 2) return null;
        list($iv, $encrypted) = $parts;
        $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
        return json_decode($decrypted, true);
    }
    
    public static function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function verifyCSRFToken($token) {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public static function checkBruteForce($ip, $type = 'login') {
        $db = getDB();
        $window = $type === 'login' ? LOGIN_TIMEOUT : REGISTER_TIMEOUT;
        $maxAttempts = $type === 'login' ? MAX_LOGIN_ATTEMPTS : MAX_REGISTER_ATTEMPTS;
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND) AND success = 0");
        $stmt->execute([$ip, $window]);
        
        return $stmt->fetchColumn() >= $maxAttempts;
    }
    
    public static function logAttempt($ip, $username, $success) {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO login_attempts (ip_address, username, attempted_at, success) VALUES (?, ?, NOW(), ?)");
        return $stmt->execute([$ip, $username, $success ? 1 : 0]);
    }
    
    public static function isVPN($ip) {
        $privateRanges = [
            '10.0.0.0|10.255.255.255',
            '172.16.0.0|172.31.255.255',
            '192.168.0.0|192.168.255.255',
            '127.0.0.0|127.255.255.255'
        ];
        
        $ipLong = ip2long($ip);
        if ($ipLong === false) return false;
        
        foreach ($privateRanges as $range) {
            list($start, $end) = explode('|', $range);
            $startLong = ip2long($start);
            $endLong = ip2long($end);
            if ($ipLong >= $startLong && $ipLong <= $endLong) {
                return true;
            }
        }
        return false;
    }
    
    public static function generateAPIKey() {
        return 'zyn_' . bin2hex(random_bytes(32));
    }
    
    public static function validateAPIKey($key) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM api_keys WHERE api_key = ? AND status = 'active' AND (expires_at IS NULL OR expires_at > NOW())");
        $stmt->execute([$key]);
        return $stmt->fetch();
    }
    
    public static function rateLimit($key, $maxRequests = 60, $window = 60) {
        if (!RATE_LIMIT_ENABLED) return false;
        
        $cacheKey = 'ratelimit_' . md5($key);
        $cacheFile = CACHE_DIR . '/' . $cacheKey . '.json';
        
        $data = ['count' => 0, 'reset' => time() + $window];
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true) ?: $data;
        }
        
        if (time() > $data['reset']) {
            $data = ['count' => 0, 'reset' => time() + $window];
        }
        
        $data['count']++;
        file_put_contents($cacheFile, json_encode($data));
        
        return $data['count'] > $maxRequests;
    }
    
    public static function detectBot() {
        $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
        $bots = ['bot', 'crawler', 'spider', 'scraper', 'curl', 'wget', 'python', 'ruby', 'perl', 'php', 'java', 'node', 'go-http-client', 'headless'];
        
        foreach ($bots as $bot) {
            if (strpos($ua, $bot) !== false) {
                return true;
            }
        }
        
        if (empty($ua)) return true;
        
        if (isset($_SERVER['HTTP_SEC_CH_UA']) && empty($_SERVER['HTTP_SEC_CH_UA'])) {
            return true;
        }
        
        return false;
    }
    
    public static function validateWithdrawal($userId, $amount, $method) {
        $db = getDB();
        
        $balance = getUserBalance($userId);
        if ($amount > $balance) {
            return 'Insufficient balance';
        }
        
        if ($amount < MIN_WITHDRAWAL) {
            return 'Minimum withdrawal is ' . formatCurrency(MIN_WITHDRAWAL);
        }
        
        if ($amount > MAX_WITHDRAWAL) {
            return 'Maximum withdrawal is ' . formatCurrency(MAX_WITHDRAWAL);
        }
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM withdrawals WHERE user_id = ? AND status = 'pending'");
        $stmt->execute([$userId]);
        if ($stmt->fetchColumn() >= 3) {
            return 'You have too many pending withdrawals';
        }
        
        return null;
    }
}
