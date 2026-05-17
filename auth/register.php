<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/user/dashboard.php');
    exit;
}
$pageTitle = 'Register';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAjaxRequest()) {
    require_once __DIR__ . '/../includes/security.php';
    header('Content-Type: application/json');

    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    if (!isset($data['csrf_token']) || !verify_csrf($data['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid security token. Please refresh the page.']);
        exit;
    }

    $ip = getClientIP();
    if (isRateLimited('register_' . $ip, MAX_REGISTER_ATTEMPTS, REGISTER_TIMEOUT)) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Too many registration attempts. Please try again later.']);
        exit;
    }

    $username = sanitize($data['username'] ?? '');
    $email = sanitize($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $confirmPassword = $data['confirm_password'] ?? '';
    $referralCode = sanitize($data['referral_code'] ?? '');
    $agree = !empty($data['agree']);
    $captchaToken = $data['captcha_token'] ?? '';

    $errors = [];

    if (empty($username) || !validateUsername($username)) {
        $errors[] = 'Username must be 3-20 characters (letters, numbers, underscores).';
    }

    if (empty($email) || !validateEmail($email)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$agree) {
        $errors[] = 'You must agree to the Terms & Conditions.';
    }

    // reCAPTCHA placeholder validation
    if (empty($captchaToken)) {
        // In production, verify with Google reCAPTCHA
        // $recaptchaSecret = 'YOUR_SECRET_KEY';
        // $recaptchaVerify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$recaptchaSecret}&response={$captchaToken}");
        // $recaptchaData = json_decode($recaptchaVerify);
        // if (!$recaptchaData->success) $errors[] = 'Captcha verification failed.';
    }

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'error' => implode(' ', $errors)]);
        exit;
    }

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Username is already taken.']);
            exit;
        }

        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Email is already registered.']);
            exit;
        }

        $referrerId = null;
        if (!empty($referralCode)) {
            $stmt = $db->prepare("SELECT id, referral_code FROM users WHERE referral_code = ? LIMIT 1");
            $stmt->execute([$referralCode]);
            $referrer = $stmt->fetch();
            if ($referrer) {
                $referrerId = $referrer['id'];
            }
        }

        $ownReferralCode = createReferralCode();
        $hashedPassword = hashPassword($password);
        $otp = generateOTP();
        $otpExpiry = date('Y-m-d H:i:s', time() + OTP_EXPIRY);

        $stmt = $db->prepare("INSERT INTO users (username, email, password, referral_code, referred_by, email_otp, otp_expires_at, status, created_at, last_login_ip, email_verified) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW(), ?, 0)");
        $stmt->execute([$username, $email, $hashedPassword, $ownReferralCode, $referrerId, $otp, $otpExpiry, $ip]);
        $userId = $db->lastInsertId();

        if ($referrerId) {
            $stmt = $db->prepare("INSERT INTO referrals (referrer_id, referred_id, bonus_amount, status) VALUES (?, ?, 0, 'pending')");
            $stmt->execute([$referrerId, $userId]);
        }

        Security::logAttempt($ip, $username, true);

        // Send OTP email (placeholder)
        // mail($email, 'Verify your ' . APP_NAME . ' account', "Your OTP: $otp", "From: noreply@" . $_SERVER['HTTP_HOST']);

        $_SESSION['pending_user_id'] = $userId;
        $_SESSION['pending_email'] = $email;

        echo json_encode([
            'success' => true,
            'redirect' => '/auth/verify.php',
            'message' => 'Account created! Please verify your email.'
        ]);
    } catch (Exception $e) {
        logError('Registration error', ['message' => $e->getMessage()]);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'An unexpected error occurred. Please try again.']);
    }
    exit;
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="auth-page">
    <div class="auth-bg">
        <div class="auth-shape auth-shape-1"></div>
        <div class="auth-shape auth-shape-2"></div>
        <div class="auth-shape auth-shape-3"></div>
        <div class="auth-shape auth-shape-4"></div>
    </div>

    <div class="auth-container">
        <div class="auth-card glass-strong" id="registerCard">
            <div class="auth-header">
                <a href="<?= APP_URL ?>" class="auth-logo">
                    <div class="logo-icon">Z</div>
                    <span class="logo-text"><?= APP_NAME ?></span>
                </a>
                <h1 class="auth-title">Create Account</h1>
                <p class="auth-subtitle">Start earning money today</p>
            </div>

            <form class="auth-form" id="registerForm" novalidate>
                <?= csrf_field() ?>

                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" class="form-input" id="username" name="username" placeholder="Choose a username" required minlength="3" maxlength="20" pattern="^[a-zA-Z0-9_]+$" autocomplete="username" autofocus>
                    </div>
                    <span class="field-error"></span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" class="form-input" id="email" name="email" placeholder="Enter your email" required autocomplete="email">
                    </div>
                    <span class="field-error"></span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" class="form-input" id="password" name="password" placeholder="Create a strong password" required minlength="8" autocomplete="new-password" data-password-strength>
                        <button type="button" class="password-toggle" data-toggle="password" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength-bar">
                        <div class="password-strength-meter" id="passwordMeter"></div>
                    </div>
                    <span class="password-strength-label" id="passwordStrengthLabel"></span>
                    <span class="field-error"></span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm Password</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" class="form-input" id="confirm_password" name="confirm_password" placeholder="Repeat your password" required autocomplete="new-password">
                        <button type="button" class="password-toggle" data-toggle="password" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <span class="field-error"></span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="referral_code">Referral Code <span class="form-label-optional">(optional)</span></label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-gift input-icon"></i>
                        <input type="text" class="form-input" id="referral_code" name="referral_code" placeholder="Enter referral code" autocomplete="off">
                    </div>
                    <span class="field-error"></span>
                </div>

                <!-- reCAPTCHA Placeholder -->
                <div class="form-group">
                    <div class="captcha-placeholder" id="captchaContainer">
                        <div class="g-recaptcha" data-sitekey="YOUR_SITE_KEY"></div>
                    </div>
                    <span class="field-error"></span>
                </div>

                <div class="form-group auth-checkbox">
                    <label class="form-check">
                        <input type="checkbox" class="form-check-input" id="agree" name="agree" required>
                        <span class="form-check-text">I agree to the <a href="/terms" class="auth-link" target="_blank">Terms & Conditions</a> and <a href="/privacy" class="auth-link" target="_blank">Privacy Policy</a></span>
                    </label>
                    <span class="field-error"></span>
                </div>

                <div class="auth-notice">
                    <i class="fas fa-info-circle"></i>
                    <span>An email verification code will be sent to your address.</span>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg" id="registerBtn">
                    <span class="btn-text">Create Account</span>
                    <span class="btn-loader"><i class="fas fa-circle-notch fa-spin"></i></span>
                </button>

                <div class="auth-divider">
                    <span>Or sign up with</span>
                </div>

                <div class="auth-social">
                    <button type="button" class="btn btn-outline-light social-btn social-google" data-social="google">
                        <i class="fab fa-google"></i>
                        <span>Google</span>
                    </button>
                    <button type="button" class="btn btn-outline-light social-btn social-discord" data-social="discord">
                        <i class="fab fa-discord"></i>
                        <span>Discord</span>
                    </button>
                    <button type="button" class="btn btn-outline-light social-btn social-telegram" data-social="telegram">
                        <i class="fab fa-telegram-plane"></i>
                        <span>Telegram</span>
                    </button>
                </div>
            </form>

            <div class="auth-footer">
                <p>Already have an account? <a href="/auth/login.php" class="auth-link">Sign in</a></p>
            </div>
        </div>
    </div>
</div>

<div class="toast-container" data-toast-container></div>

<style>
.auth-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
    padding: 20px;
}

.auth-bg {
    position: fixed;
    inset: 0;
    z-index: 0;
    pointer-events: none;
}

.auth-shape {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    opacity: .15;
    animation: authFloat 20s ease-in-out infinite;
}

.auth-shape-1 { width: 600px; height: 600px; background: var(--primary); top: -10%; right: -10%; animation-delay: 0s; }
.auth-shape-2 { width: 400px; height: 400px; background: var(--secondary); bottom: -5%; left: -5%; animation-delay: -5s; }
.auth-shape-3 { width: 300px; height: 300px; background: var(--accent); top: 50%; left: 50%; transform: translate(-50%, -50%); animation-delay: -10s; }
.auth-shape-4 { width: 200px; height: 200px; background: var(--warning); bottom: 20%; right: 15%; animation-delay: -15s; }

@keyframes authFloat {
    0%, 100% { transform: translate(0, 0) scale(1); }
    25% { transform: translate(30px, -30px) scale(1.1); }
    50% { transform: translate(-20px, 20px) scale(.9); }
    75% { transform: translate(20px, 30px) scale(1.05); }
}

.auth-container { position: relative; z-index: 1; width: 100%; max-width: 460px; }

.auth-card {
    padding: 40px 36px;
    border-radius: var(--radius-xl);
    animation: scaleInBounce .6s ease;
}

.auth-header { text-align: center; margin-bottom: 28px; }

.auth-logo {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    text-decoration: none;
}

.auth-logo .logo-icon {
    width: 44px; height: 44px;
    background: var(--gradient-primary);
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: var(--font-display);
    font-size: 1.3rem;
    font-weight: 700;
    color: #fff;
}

.auth-logo .logo-text {
    font-family: var(--font-display);
    font-size: 1.5rem;
    font-weight: 700;
    background: var(--gradient-primary);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.auth-title { font-family: var(--font-display); font-size: 1.75rem; font-weight: 700; margin-bottom: 6px; }
.auth-subtitle { color: var(--text-secondary); font-size: .9rem; }

.input-icon-wrap { position: relative; }
.input-icon-wrap .form-input { padding-left: 44px; padding-right: 44px; height: 50px; }

.input-icon {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-tertiary);
    font-size: .9rem;
    pointer-events: none;
    transition: color var(--transition);
}

.form-input:focus ~ .input-icon { color: var(--primary); }

.password-toggle {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--text-tertiary);
    font-size: .9rem;
    cursor: pointer;
    padding: 4px;
    transition: color var(--transition);
}

.password-toggle:hover { color: var(--text-primary); }

.password-strength-bar {
    height: 4px;
    background: var(--border-primary);
    border-radius: var(--radius-full);
    margin-top: 8px;
    overflow: hidden;
}

.password-strength-meter {
    height: 100%;
    width: 0%;
    border-radius: var(--radius-full);
    transition: all .3s ease;
}

.password-strength-meter.strength-weak { width: 25%; background: var(--danger); }
.password-strength-meter.strength-fair { width: 50%; background: var(--warning); }
.password-strength-meter.strength-good { width: 75%; background: var(--info); }
.password-strength-meter.strength-strong { width: 100%; background: var(--success); }

.password-strength-label {
    display: block;
    font-size: .7rem;
    margin-top: 4px;
    min-height: 16px;
}

.password-strength-label.strength-weak { color: var(--danger); }
.password-strength-label.strength-fair { color: var(--warning); }
.password-strength-label.strength-good { color: var(--info); }
.password-strength-label.strength-strong { color: var(--success); }

.form-label-optional { color: var(--text-tertiary); font-weight: 400; font-size: .8rem; }

.auth-checkbox { margin-top: 4px; }

.form-check {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    cursor: pointer;
}

.form-check-input {
    width: 18px;
    height: 18px;
    accent-color: var(--primary);
    margin-top: 2px;
    flex-shrink: 0;
}

.form-check-text {
    font-size: .82rem;
    color: var(--text-secondary);
    line-height: 1.5;
}

.auth-notice {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px;
    background: rgba(108,92,231,.1);
    border: 1px solid rgba(108,92,231,.2);
    border-radius: var(--radius-md);
    font-size: .8rem;
    color: var(--text-secondary);
    margin-bottom: 16px;
}

.auth-notice i { color: var(--primary); font-size: .9rem; flex-shrink: 0; }

.captcha-placeholder {
    min-height: 78px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-input);
    border: 2px dashed var(--border-primary);
    border-radius: var(--radius-md);
    font-size: .8rem;
    color: var(--text-tertiary);
}

.auth-divider {
    display: flex;
    align-items: center;
    gap: 16px;
    margin: 20px 0;
    color: var(--text-tertiary);
    font-size: .8rem;
}

.auth-divider::before, .auth-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--glass-border);
}

.auth-social {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
}

.social-btn {
    padding: 12px 8px;
    font-size: .8rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: all var(--transition);
}

.social-btn i { font-size: 1.1rem; }
.social-btn span { font-size: .78rem; }

.social-google:hover { background: rgba(219,68,55,.15); border-color: #DB4437; color: #DB4437; }
.social-discord:hover { background: rgba(88,101,242,.15); border-color: #5865F2; color: #5865F2; }
.social-telegram:hover { background: rgba(0,136,204,.15); border-color: #0088CC; color: #0088CC; }

.auth-footer {
    text-align: center;
    margin-top: 20px;
    padding-top: 18px;
    border-top: 1px solid var(--glass-border);
}

.auth-footer p { font-size: .85rem; color: var(--text-secondary); margin: 0; }

.btn-block { width: 100%; }
.btn-lg { padding: 16px 28px; font-size: 1rem; }

.btn-text { transition: opacity var(--transition); }
.btn-loader { display: none; }
.btn.loading .btn-text { opacity: 0; }
.btn.loading .btn-loader { display: inline-flex; position: absolute; }

.field-error {
    display: block;
    font-size: .75rem;
    color: var(--danger);
    margin-top: 6px;
    min-height: 18px;
}

@media (max-width: 480px) {
    .auth-card { padding: 28px 20px; }
    .auth-social { grid-template-columns: 1fr; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('registerForm');
    var submitBtn = document.getElementById('registerBtn');

    var toggleBtns = document.querySelectorAll('[data-toggle="password"]');
    toggleBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var input = this.parentElement.querySelector('input');
            var icon = this.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });
    });

    // Password strength meter
    var passwordInput = document.getElementById('password');
    var meter = document.getElementById('passwordMeter');
    var strengthLabel = document.getElementById('passwordStrengthLabel');

    passwordInput.addEventListener('input', function() {
        var val = this.value;
        var score = 0;
        if (val.length >= 8) score += 25;
        if (val.length >= 12) score += 10;
        if (/[a-z]/.test(val)) score += 10;
        if (/[A-Z]/.test(val)) score += 15;
        if (/[0-9]/.test(val)) score += 15;
        if (/[^a-zA-Z0-9]/.test(val)) score += 15;
        if (val.length >= 16) score += 10;
        score = Math.min(score, 100);

        var level, text;
        if (score < 30) { level = 'weak'; text = 'Weak'; }
        else if (score < 60) { level = 'fair'; text = 'Fair'; }
        else if (score < 80) { level = 'good'; text = 'Good'; }
        else { level = 'strong'; text = 'Strong'; }

        meter.className = 'password-strength-meter strength-' + level;
        strengthLabel.textContent = 'Password strength: ' + text;
        strengthLabel.className = 'password-strength-label strength-' + level;
    });

    function showError(input, message) {
        var error = input.closest('.form-group').querySelector('.field-error');
        input.classList.add('error');
        if (error) error.textContent = message;
    }

    function clearError(input) {
        var error = input.closest('.form-group').querySelector('.field-error');
        input.classList.remove('error');
        if (error) error.textContent = '';
    }

    function validateField(input) {
        var val = input.value.trim();

        if (input.required && !val) {
            showError(input, 'This field is required');
            return false;
        }

        if (input.id === 'username') {
            if (val.length < 3 || val.length > 20) {
                showError(input, 'Username must be 3-20 characters');
                return false;
            }
            if (!/^[a-zA-Z0-9_]+$/.test(val)) {
                showError(input, 'Letters, numbers, and underscores only');
                return false;
            }
        }

        if (input.id === 'email' && val && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
            showError(input, 'Invalid email address');
            return false;
        }

        if (input.id === 'password' && val.length < 8) {
            showError(input, 'Minimum 8 characters');
            return false;
        }

        if (input.id === 'confirm_password') {
            var pw = document.getElementById('password').value;
            if (val !== pw) {
                showError(input, 'Passwords do not match');
                return false;
            }
        }

        if (input.id === 'agree' && !input.checked) {
            showError(input, 'You must agree to the terms');
            return false;
        }

        clearError(input);
        return true;
    }

    var inputs = form.querySelectorAll('input, select, textarea');
    inputs.forEach(function(input) {
        input.addEventListener('blur', function() { validateField(this); });
        input.addEventListener('input', function() {
            if (this.dataset.touched) validateField(this);
        });
        input.addEventListener('focus', function() { this.dataset.touched = '1'; });
    });

    function showToast(message, type) {
        type = type || 'error';
        var container = document.querySelector('.toast-container');
        if (!container) return;
        var toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.innerHTML =
            '<span class="toast-icon">' + (type === 'success' ? '&#10003;' : '&#10005;') + '</span>' +
            '<span class="toast-message">' + message + '</span>' +
            '<button class="toast-close" aria-label="Dismiss">&times;</button>';
        container.appendChild(toast);
        setTimeout(function() { toast.classList.add('toast-visible'); }, 10);
        toast.querySelector('.toast-close').addEventListener('click', function() {
            if (toast && !toast.classList.contains('toast-dismissing')) {
                toast.classList.add('toast-dismissing');
                setTimeout(function() { toast.remove(); }, 300);
            }
        });
        setTimeout(function() {
            if (toast && !toast.classList.contains('toast-dismissing')) {
                toast.classList.add('toast-dismissing');
                setTimeout(function() { toast.remove(); }, 300);
            }
        }, 5000);
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var isValid = true;
        inputs.forEach(function(input) {
            if (!validateField(input)) isValid = false;
        });
        if (!isValid) return;

        submitBtn.classList.add('loading');
        submitBtn.disabled = true;

        var formData = {
            csrf_token: document.querySelector('input[name="csrf_token"]').value,
            username: document.getElementById('username').value.trim(),
            email: document.getElementById('email').value.trim(),
            password: document.getElementById('password').value,
            confirm_password: document.getElementById('confirm_password').value,
            referral_code: document.getElementById('referral_code').value.trim(),
            agree: document.getElementById('agree').checked ? 1 : 0,
            captcha_token: ''
        };

        fetch('/auth/register.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(formData)
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            submitBtn.classList.remove('loading');
            submitBtn.disabled = false;

            if (data.success) {
                showToast(data.message || 'Account created!', 'success');
                setTimeout(function() {
                    window.location.href = data.redirect || '/auth/verify.php';
                }, 800);
            } else {
                showToast(data.error || 'Registration failed.', 'error');
            }
        })
        .catch(function() {
            submitBtn.classList.remove('loading');
            submitBtn.disabled = false;
            showToast('Connection error. Please try again.', 'error');
        });
    });

    var socialBtns = document.querySelectorAll('.social-btn');
    socialBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var provider = this.getAttribute('data-social');
            showToast('Sign up with ' + provider.charAt(0).toUpperCase() + provider.slice(1) + ' coming soon!', 'info');
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
