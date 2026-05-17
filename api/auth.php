<?php

class API_auth
{
    private $db;
    private $method;
    private $route;

    public function __construct($db, $method, $route)
    {
        $this->db = $db;
        $this->method = $method;
        $this->route = $route;
    }

    public function handle()
    {
        $input = $this->getInput();

        switch ($this->route) {
            case '/auth/login':
                return $this->login($input);
            case '/auth/register':
                return $this->register($input);
            case '/auth/verify':
                return $this->verify($input);
            case '/auth/reset-password':
                return $this->resetPassword($input);
            case '/auth/profile':
                if ($this->method === 'GET') {
                    return $this->getProfile();
                } elseif ($this->method === 'PUT' || $this->method === 'PATCH') {
                    return $this->updateProfile($input);
                }
                return ['success' => false, 'error' => 'Method not allowed'];
            default:
                return ['success' => false, 'error' => 'Not found'];
        }
    }

    private function getInput(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (stripos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            return is_array($data) ? $data : [];
        }

        if ($this->method === 'GET') {
            return $_GET;
        }

        return $_POST;
    }

    private function validateInput(array $data, array $rules): ?string
    {
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;

            if (in_array('required', $rule) && (empty($value) && $value !== '0')) {
                return "Field '{$field}' is required";
            }

            if (!empty($value)) {
                if (in_array('email', $rule) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return "Field '{$field}' must be a valid email";
                }

                if (in_array('username', $rule) && !preg_match('/^[a-zA-Z0-9_]{3,20}$/', $value)) {
                    return "Field '{$field}' must be 3-20 alphanumeric characters";
                }

                if (isset($rule['min']) && strlen($value) < $rule['min']) {
                    return "Field '{$field}' must be at least {$rule['min']} characters";
                }

                if (isset($rule['max']) && strlen($value) > $rule['max']) {
                    return "Field '{$field}' must be at most {$rule['max']} characters";
                }
            }
        }

        return null;
    }

    private function login(array $data): array
    {
        $error = $this->validateInput($data, [
            'username' => ['required'],
            'password' => ['required', 'min' => 6]
        ]);

        if ($error) {
            http_response_code(422);
            return ['success' => false, 'error' => $error];
        }

        $username = trim($data['username']);
        $password = $data['password'];
        $remember = !empty($data['remember']);

        $stmt = $this->db->prepare("
            SELECT id, username, email, password, status, twofa_secret, ip_address,
                   email_verified_at, balance, level_id
            FROM users
            WHERE username = ? OR email = ?
            LIMIT 1
        ");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $this->logLoginAttempt($username, 'failed', 'Invalid credentials');
            http_response_code(401);
            return ['success' => false, 'error' => 'Invalid username or password'];
        }

        if (!password_verify($password, $user['password'])) {
            $this->logLoginAttempt($username, 'failed', 'Invalid password');
            http_response_code(401);
            return ['success' => false, 'error' => 'Invalid username or password'];
        }

        if ($user['status'] !== 'active') {
            http_response_code(403);
            return [
                'success' => false,
                'error' => 'Account is ' . $user['status'],
                'status' => $user['status']
            ];
        }

        if ($user['email_verified_at'] === null) {
            http_response_code(403);
            return [
                'success' => false,
                'error' => 'Email not verified',
                'requires_verification' => true
            ];
        }

        if (!empty($user['twofa_secret'])) {
            $twofaCode = $data['twofa_code'] ?? '';
            if (empty($twofaCode)) {
                http_response_code(200);
                return [
                    'success' => true,
                    'requires_2fa' => true,
                    'message' => '2FA code required'
                ];
            }
        }

        $sessionToken = bin2hex(random_bytes(32));
        $refreshToken = bin2hex(random_bytes(32));
        $expiresAt = $remember ? date('Y-m-d H:i:s', time() + 604800) : date('Y-m-d H:i:s', time() + 86400);
        $refreshExpiresAt = date('Y-m-d H:i:s', time() + 2592000);

        $stmt = $this->db->prepare("
            INSERT INTO sessions (user_id, token, refresh_token, ip_address, user_agent, expires_at, refresh_expires_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $user['id'],
            hash('sha256', $sessionToken),
            hash('sha256', $refreshToken),
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $expiresAt,
            $refreshExpiresAt
        ]);

        $this->logLoginAttempt($username, 'success', 'Login successful');

        $stmt = $this->db->prepare("UPDATE users SET last_login = NOW(), ip_address = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$_SERVER['REMOTE_ADDR'] ?? '', $user['id']]);

        return [
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'token' => $sessionToken,
                'refresh_token' => $refreshToken,
                'expires_at' => $expiresAt,
                'user' => $this->sanitizeUser($user)
            ]
        ];
    }

    private function register(array $data): array
    {
        $error = $this->validateInput($data, [
            'username' => ['required', 'username'],
            'email' => ['required', 'email'],
            'password' => ['required', 'min' => 8],
            'password_confirm' => ['required']
        ]);

        if ($error) {
            http_response_code(422);
            return ['success' => false, 'error' => $error];
        }

        if ($data['password'] !== $data['password_confirm']) {
            http_response_code(422);
            return ['success' => false, 'error' => 'Passwords do not match'];
        }

        $username = trim($data['username']);
        $email = trim(strtolower($data['email']));

        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            http_response_code(422);
            return ['success' => false, 'error' => 'Username must be 3-20 alphanumeric characters'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(422);
            return ['success' => false, 'error' => 'Invalid email address'];
        }

        $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            http_response_code(409);
            return ['success' => false, 'error' => 'Username or email already exists'];
        }

        $referralCode = $data['referral_code'] ?? null;
        $referredBy = null;

        if ($referralCode) {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE referral_code = ? AND status = 'active' LIMIT 1");
            $stmt->execute([$referralCode]);
            $referrer = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($referrer) {
                $referredBy = $referrer['id'];
            }
        }

        $hashedPassword = password_hash($data['password'], PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);

        $verificationCode = bin2hex(random_bytes(32));
        $userReferralCode = $this->generateReferralCode();

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                INSERT INTO users (username, email, password, referral_code, referred_by, verification_code, ip_address, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
            ");
            $stmt->execute([$username, $email, $hashedPassword, $userReferralCode, $referredBy, $verificationCode, $ip]);
            $userId = $this->db->lastInsertId();

            $defaultLevel = 1;
            $stmt = $this->db->prepare("SELECT id FROM levels ORDER BY min_earnings ASC LIMIT 1");
            $stmt->execute();
            $levelRow = $stmt->fetch();
            if ($levelRow) {
                $defaultLevel = $levelRow['id'];
            }
            $stmt = $this->db->prepare("UPDATE users SET level_id = ? WHERE id = ?");
            $stmt->execute([$defaultLevel, $userId]);

            if ($referredBy) {
                $stmt = $this->db->prepare("
                    INSERT INTO referrals (referrer_id, referred_id, status, created_at)
                    VALUES (?, ?, 'pending', NOW())
                ");
                $stmt->execute([$referredBy, $userId]);
            }

            $stmt = $this->db->prepare("
                INSERT INTO user_stats (user_id, created_at) VALUES (?, NOW())
            ");
            $stmt->execute([$userId]);

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            http_response_code(500);
            return ['success' => false, 'error' => 'Registration failed. Please try again.'];
        }

        $this->sendVerificationEmail($email, $username, $verificationCode);

        return [
            'success' => true,
            'message' => 'Registration successful. Please check your email to verify your account.',
            'data' => [
                'user_id' => (int)$userId,
                'requires_verification' => true
            ]
        ];
    }

    private function verify(array $data): array
    {
        $code = $data['code'] ?? $_GET['code'] ?? '';

        if (empty($code)) {
            http_response_code(422);
            return ['success' => false, 'error' => 'Verification code is required'];
        }

        $stmt = $this->db->prepare("
            SELECT id, email, username FROM users
            WHERE verification_code = ? AND email_verified_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$code]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            http_response_code(400);
            return ['success' => false, 'error' => 'Invalid or expired verification code'];
        }

        $stmt = $this->db->prepare("
            UPDATE users
            SET email_verified_at = NOW(), status = 'active', verification_code = NULL, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$user['id']]);

        return [
            'success' => true,
            'message' => 'Email verified successfully. You can now log in.'
        ];
    }

    private function resetPassword(array $data): array
    {
        $step = $data['step'] ?? 'request';

        if ($step === 'request') {
            $email = trim($data['email'] ?? '');

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(422);
                return ['success' => false, 'error' => 'Valid email is required'];
            }

            $stmt = $this->db->prepare("SELECT id, username FROM users WHERE email = ? AND status = 'active' LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $resetToken = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', time() + 3600);

                $stmt = $this->db->prepare("
                    UPDATE users SET reset_token = ?, reset_token_expires_at = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$resetToken, $expiresAt, $user['id']]);

                $this->sendPasswordResetEmail($email, $user['username'], $resetToken);
            }

            return [
                'success' => true,
                'message' => 'If the email exists, a password reset link has been sent.'
            ];
        }

        if ($step === 'reset') {
            $token = $data['token'] ?? '';
            $password = $data['password'] ?? '';
            $passwordConfirm = $data['password_confirm'] ?? '';

            if (empty($token)) {
                http_response_code(422);
                return ['success' => false, 'error' => 'Reset token is required'];
            }

            if (strlen($password) < 8) {
                http_response_code(422);
                return ['success' => false, 'error' => 'Password must be at least 8 characters'];
            }

            if ($password !== $passwordConfirm) {
                http_response_code(422);
                return ['success' => false, 'error' => 'Passwords do not match'];
            }

            $stmt = $this->db->prepare("
                SELECT id FROM users
                WHERE reset_token = ? AND reset_token_expires_at > NOW() AND status = 'active'
                LIMIT 1
            ");
            $stmt->execute([$token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                http_response_code(400);
                return ['success' => false, 'error' => 'Invalid or expired reset token'];
            }

            $hashedPassword = password_hash($password, PASSWORD_ARGON2ID);

            $stmt = $this->db->prepare("
                UPDATE users
                SET password = ?, reset_token = NULL, reset_token_expires_at = NULL, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$hashedPassword, $user['id']]);

            $stmt = $this->db->prepare("DELETE FROM sessions WHERE user_id = ?");
            $stmt->execute([$user['id']]);

            return [
                'success' => true,
                'message' => 'Password has been reset successfully. You can now log in with your new password.'
            ];
        }

        http_response_code(400);
        return ['success' => false, 'error' => 'Invalid step'];
    }

    private function getProfile(): array
    {
        $userId = $_REQUEST['auth_user_id'] ?? null;

        if (!$userId) {
            http_response_code(401);
            return ['success' => false, 'error' => 'Not authenticated'];
        }

        $stmt = $this->db->prepare("
            SELECT id, username, email, balance, total_earned, total_withdrawn, level_id,
                   referral_code, referred_by, email_verified_at, twofa_enabled,
                   avatar, first_name, last_name, phone, country, address, city, state, zip,
                   timezone, language, email_notifications, push_notifications,
                   status, created_at, last_login
            FROM users WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            http_response_code(404);
            return ['success' => false, 'error' => 'User not found'];
        }

        if ($user['level_id']) {
            $stmt = $this->db->prepare("SELECT name, badge, min_earnings, benefits FROM levels WHERE id = ?");
            $stmt->execute([$user['level_id']]);
            $level = $stmt->fetch(PDO::FETCH_ASSOC);
            $user['level'] = $level ?: null;
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*) as referrals, SUM(CASE WHEN status = 'qualified' THEN 1 ELSE 0 END) as qualified
            FROM referrals WHERE referrer_id = ?
        ");
        $stmt->execute([$userId]);
        $referralStats = $stmt->fetch(PDO::FETCH_ASSOC);
        $user['referral_stats'] = $referralStats;

        unset($user['password']);

        return [
            'success' => true,
            'data' => $user
        ];
    }

    private function updateProfile(array $data): array
    {
        $userId = $_REQUEST['auth_user_id'] ?? null;

        if (!$userId) {
            http_response_code(401);
            return ['success' => false, 'error' => 'Not authenticated'];
        }

        $allowedFields = [
            'first_name', 'last_name', 'phone', 'country', 'address', 'city', 'state', 'zip',
            'timezone', 'language', 'email_notifications', 'push_notifications', 'avatar'
        ];

        $updates = [];
        $params = [];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                if (in_array($field, ['email_notifications', 'push_notifications'])) {
                    $data[$field] = $data[$field] ? 1 : 0;
                }

                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (isset($data['current_password']) && isset($data['new_password'])) {
            $stmt = $this->db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!password_verify($data['current_password'], $user['password'])) {
                http_response_code(422);
                return ['success' => false, 'error' => 'Current password is incorrect'];
            }

            if (strlen($data['new_password']) < 8) {
                http_response_code(422);
                return ['success' => false, 'error' => 'New password must be at least 8 characters'];
            }

            $updates[] = "password = ?";
            $params[] = password_hash($data['new_password'], PASSWORD_ARGON2ID);
        }

        if (empty($updates)) {
            http_response_code(422);
            return ['success' => false, 'error' => 'No fields to update'];
        }

        $updates[] = "updated_at = NOW()";
        $params[] = $userId;

        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return [
            'success' => true,
            'message' => 'Profile updated successfully'
        ];
    }

    private function logLoginAttempt(string $username, string $status, string $message): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO login_attempts (username, ip_address, user_agent, status, message, attempted_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $username,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $status,
                $message
            ]);
        } catch (Exception $e) {
        }
    }

    private function sanitizeUser(array $user): array
    {
        unset($user['password'], $user['twofa_secret'], $user['verification_code'], $user['reset_token']);

        return [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'balance' => (float)$user['balance'],
            'level_id' => $user['level_id'],
            'status' => $user['status']
        ];
    }

    private function generateReferralCode(): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }
            $stmt = $this->db->prepare("SELECT id FROM users WHERE referral_code = ? LIMIT 1");
            $stmt->execute([$code]);
        } while ($stmt->fetch());

        return $code;
    }

    private function sendVerificationEmail(string $email, string $username, string $code): void
    {
        $subject = 'Verify Your ZynEarn Account';
        $link = APP_URL . "/verify.php?code={$code}";

        $body = "
        <html>
        <body style='font-family: Arial, sans-serif; background: #0a0a1a; color: #e0e0e0; padding: 40px;'>
            <div style='max-width: 600px; margin: 0 auto; background: #1a1a2e; border-radius: 12px; padding: 40px; border: 1px solid #2a2a4a;'>
                <h1 style='color: #6C5CE7; margin: 0 0 20px;'>Welcome to ZynEarn!</h1>
                <p style='color: #b0b0c0; line-height: 1.6;'>Hi {$username},</p>
                <p style='color: #b0b0c0; line-height: 1.6;'>Please verify your email address by clicking the button below:</p>
                <a href='{$link}' style='display: inline-block; background: #6C5CE7; color: #fff; text-decoration: none; padding: 14px 32px; border-radius: 8px; margin: 20px 0;'>Verify Email</a>
                <p style='color: #888; font-size: 12px;'>Or copy this link: {$link}</p>
                <p style='color: #888; font-size: 12px;'>This link expires in 24 hours.</p>
            </div>
        </body>
        </html>
        ";

        $this->sendMail($email, $subject, $body);
    }

    private function sendPasswordResetEmail(string $email, string $username, string $token): void
    {
        $subject = 'Reset Your ZynEarn Password';
        $link = APP_URL . "/reset-password.php?token={$token}";

        $body = "
        <html>
        <body style='font-family: Arial, sans-serif; background: #0a0a1a; color: #e0e0e0; padding: 40px;'>
            <div style='max-width: 600px; margin: 0 auto; background: #1a1a2e; border-radius: 12px; padding: 40px; border: 1px solid #2a2a4a;'>
                <h1 style='color: #6C5CE7; margin: 0 0 20px;'>Reset Your Password</h1>
                <p style='color: #b0b0c0; line-height: 1.6;'>Hi {$username},</p>
                <p style='color: #b0b0c0; line-height: 1.6;'>Click the button below to reset your password:</p>
                <a href='{$link}' style='display: inline-block; background: #6C5CE7; color: #fff; text-decoration: none; padding: 14px 32px; border-radius: 8px; margin: 20px 0;'>Reset Password</a>
                <p style='color: #888; font-size: 12px;'>Or copy this link: {$link}</p>
                <p style='color: #888; font-size: 12px;'>This link expires in 1 hour.</p>
                <p style='color: #888; font-size: 12px;'>If you didn't request this, please ignore this email.</p>
            </div>
        </body>
        </html>
        ";

        $this->sendMail($email, $subject, $body);
    }

    private function sendMail(string $to, string $subject, string $body): void
    {
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: ZynEarn <noreply@zynearn.com>\r\n";
        $headers .= "Reply-To: support@zynearn.com\r\n";
        $headers .= "X-Mailer: ZynEarn/" . APP_VERSION . "\r\n";

        mail($to, $subject, $body, $headers);
    }
}
