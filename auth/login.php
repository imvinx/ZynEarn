<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/user/dashboard.php');
    exit;
}
$pageTitle = 'Login';

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
    if (isRateLimited('login_' . $ip, MAX_LOGIN_ATTEMPTS, LOGIN_TIMEOUT)) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Too many login attempts. Please try again later.']);
        exit;
    }

    $username = sanitize($data['username'] ?? '');
    $password = $data['password'] ?? '';
    $remember = !empty($data['remember']);

    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'error' => 'Please fill in all fields.']);
        exit;
    }

    try {
        $db = getDB();
        $field = validateEmail($username) ? 'email' : 'username';
        $stmt = $db->prepare("SELECT * FROM users WHERE $field = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !verifyPassword($password, $user['password'])) {
            Security::logAttempt($ip, $username, false);
            echo json_encode(['success' => false, 'error' => 'Invalid credentials. Please try again.']);
            exit;
        }

        if ($user['status'] === 'suspended') {
            echo json_encode(['success' => false, 'error' => 'Your account has been suspended. Contact support.']);
            exit;
        }

        if ($user['status'] === 'banned') {
            echo json_encode(['success' => false, 'error' => 'Your account has been permanently banned.']);
            exit;
        }

        if (!$user['email_verified'] && !empty($user['email'])) {
            echo json_encode(['success' => false, 'redirect' => '/auth/verify.php', 'error' => 'Please verify your email before logging in.']);
            exit;
        }

        if ($user['two_factor_enabled']) {
            $_SESSION['two_factor_user_id'] = $user['id'];
            echo json_encode(['success' => true, 'redirect' => '/auth/two-factor.php']);
            exit;
        }

        createSession($user['id']);
        Security::logAttempt($ip, $username, true);

        $stmt = $db->prepare("UPDATE users SET last_login = NOW(), last_ip = ? WHERE id = ?");
        $stmt->execute([$ip, $user['id']]);

        echo json_encode(['success' => true, 'redirect' => '/user/dashboard.php', 'message' => 'Welcome back, ' . htmlspecialchars($user['username']) . '!']);
    } catch (Exception $e) {
        logError('Login error', ['message' => $e->getMessage()]);
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
        <div class="auth-card glass-strong" id="loginCard">
            <div class="auth-header">
                <a href="<?= APP_URL ?>" class="auth-logo">
                    <div class="logo-icon">Z</div>
                    <span class="logo-text"><?= APP_NAME ?></span>
                </a>
                <h1 class="auth-title">Welcome Back</h1>
                <p class="auth-subtitle">Sign in to continue earning</p>
            </div>

            <form class="auth-form" id="loginForm" novalidate>
                <?= csrf_field() ?>

                <div class="form-group">
                    <label class="form-label" for="username">Email or Username</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" class="form-input" id="username" name="username" placeholder="Enter email or username" required autocomplete="username" autofocus>
                    </div>
                    <span class="field-error"></span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" class="form-input" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                        <button type="button" class="password-toggle" data-toggle="password" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <span class="field-error"></span>
                </div>

                <div class="form-group auth-options">
                    <label class="toggle">
                        <input type="checkbox" class="toggle-input" name="remember" id="remember">
                        <span class="toggle-track"></span>
                        <span class="toggle-label">Remember me</span>
                    </label>
                    <a href="/auth/forgot.php" class="auth-link">Forgot password?</a>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg" id="loginBtn">
                    <span class="btn-text">Sign In</span>
                    <span class="btn-loader"><i class="fas fa-circle-notch fa-spin"></i></span>
                </button>

                <div class="auth-divider">
                    <span>Or continue with</span>
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
                <p>Don't have an account? <a href="/auth/register.php" class="auth-link">Create one now</a></p>
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

.auth-shape-1 {
    width: 600px;
    height: 600px;
    background: var(--primary);
    top: -10%;
    right: -10%;
    animation-delay: 0s;
}

.auth-shape-2 {
    width: 400px;
    height: 400px;
    background: var(--secondary);
    bottom: -5%;
    left: -5%;
    animation-delay: -5s;
}

.auth-shape-3 {
    width: 300px;
    height: 300px;
    background: var(--accent);
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    animation-delay: -10s;
}

.auth-shape-4 {
    width: 200px;
    height: 200px;
    background: var(--warning);
    bottom: 20%;
    right: 15%;
    animation-delay: -15s;
}

@keyframes authFloat {
    0%, 100% { transform: translate(0, 0) scale(1); }
    25% { transform: translate(30px, -30px) scale(1.1); }
    50% { transform: translate(-20px, 20px) scale(.9); }
    75% { transform: translate(20px, 30px) scale(1.05); }
}

.auth-container {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 440px;
}

.auth-card {
    padding: 40px 36px;
    border-radius: var(--radius-xl);
    animation: scaleInBounce .6s ease;
}

.auth-header {
    text-align: center;
    margin-bottom: 32px;
}

.auth-logo {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 24px;
    text-decoration: none;
}

.auth-logo .logo-icon {
    width: 44px;
    height: 44px;
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

.auth-title {
    font-family: var(--font-display);
    font-size: 1.75rem;
    font-weight: 700;
    margin-bottom: 6px;
}

.auth-subtitle {
    color: var(--text-secondary);
    font-size: .9rem;
}

.auth-form {
    display: flex;
    flex-direction: column;
    gap: 0;
}

.input-icon-wrap {
    position: relative;
}

.input-icon-wrap .form-input {
    padding-left: 44px;
    padding-right: 44px;
    height: 50px;
}

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

.form-input:focus ~ .input-icon {
    color: var(--primary);
}

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

.password-toggle:hover {
    color: var(--text-primary);
}

.auth-options {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 8px 0 24px;
}

.auth-link {
    color: var(--primary-light);
    font-size: .85rem;
    font-weight: 500;
    text-decoration: none;
    transition: color var(--transition);
}

.auth-link:hover {
    color: var(--primary);
    text-decoration: underline;
}

.btn-block {
    width: 100%;
}

.btn-lg {
    padding: 16px 28px;
    font-size: 1rem;
}

.btn-text { transition: opacity var(--transition); }
.btn-loader { display: none; }

.btn.loading .btn-text { opacity: 0; }
.btn.loading .btn-loader { display: inline-flex; position: absolute; }

.auth-divider {
    display: flex;
    align-items: center;
    gap: 16px;
    margin: 24px 0;
    color: var(--text-tertiary);
    font-size: .8rem;
}

.auth-divider::before,
.auth-divider::after {
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
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid var(--glass-border);
}

.auth-footer p {
    font-size: .85rem;
    color: var(--text-secondary);
    margin: 0;
}

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
    .auth-options { flex-direction: column; gap: 12px; align-items: flex-start; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('loginForm');
    const submitBtn = document.getElementById('loginBtn');
    const username = document.getElementById('username');
    const password = document.getElementById('password');
    const toggleBtns = document.querySelectorAll('[data-toggle="password"]');

    toggleBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const input = this.parentElement.querySelector('input');
            const icon = this.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });
    });

    function showError(input, message) {
        const error = input.closest('.form-group').querySelector('.field-error');
        input.classList.add('error');
        if (error) error.textContent = message;
    }

    function clearError(input) {
        const error = input.closest('.form-group').querySelector('.field-error');
        input.classList.remove('error');
        if (error) error.textContent = '';
    }

    function validateField(input) {
        const val = input.value.trim();
        if (input.required && !val) {
            showError(input, 'This field is required');
            return false;
        }
        if (input.id === 'password' && val.length < 6) {
            showError(input, 'Password must be at least 6 characters');
            return false;
        }
        clearError(input);
        return true;
    }

    username.addEventListener('blur', function() { validateField(this); });
    username.addEventListener('input', function() { if (this.dataset.touched) validateField(this); });
    username.addEventListener('focus', function() { this.dataset.touched = '1'; });

    password.addEventListener('blur', function() { validateField(this); });
    password.addEventListener('input', function() { if (this.dataset.touched) validateField(this); });
    password.addEventListener('focus', function() { this.dataset.touched = '1'; });

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
        var closeBtn = toast.querySelector('.toast-close');
        closeBtn.addEventListener('click', function() { dismissToast(toast); });
        setTimeout(function() { dismissToast(toast); }, 5000);
    }

    function dismissToast(toast) {
        if (!toast || toast.classList.contains('toast-dismissing')) return;
        toast.classList.add('toast-dismissing');
        setTimeout(function() { toast.remove(); }, 300);
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        var isUsernameValid = validateField(username);
        var isPasswordValid = validateField(password);

        if (!isUsernameValid || !isPasswordValid) return;

        submitBtn.classList.add('loading');
        submitBtn.disabled = true;

        var formData = {
            csrf_token: document.querySelector('input[name="csrf_token"]').value,
            username: username.value.trim(),
            password: password.value,
            remember: document.getElementById('remember').checked ? 1 : 0
        };

        fetch('/auth/login.php', {
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
                showToast(data.message || 'Login successful!', 'success');
                setTimeout(function() {
                    window.location.href = data.redirect || '/user/dashboard.php';
                }, 800);
            } else {
                showToast(data.error || 'Login failed. Please try again.', 'error');
                if (data.redirect) {
                    setTimeout(function() {
                        window.location.href = data.redirect;
                    }, 1500);
                }
            }
        })
        .catch(function(err) {
            submitBtn.classList.remove('loading');
            submitBtn.disabled = false;
            showToast('Connection error. Please try again.', 'error');
        });
    });

    var socialBtns = document.querySelectorAll('.social-btn');
    socialBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var provider = this.getAttribute('data-social');
            showToast('Sign in with ' + provider.charAt(0).toUpperCase() + provider.slice(1) + ' coming soon!', 'info');
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
