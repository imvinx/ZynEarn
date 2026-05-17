<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/user/dashboard.php');
    exit;
}
$pageTitle = 'Forgot Password';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAjaxRequest()) {
    header('Content-Type: application/json');

    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    if (!isset($data['csrf_token']) || !verify_csrf($data['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid security token. Please refresh the page.']);
        exit;
    }

    $ip = getClientIP();
    if (isRateLimited('forgot_' . $ip, 3, 300)) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Too many requests. Please try again later.']);
        exit;
    }

    $email = sanitize($data['email'] ?? '');

    if (empty($email) || !validateEmail($email)) {
        echo json_encode(['success' => false, 'error' => 'Please enter a valid email address.']);
        exit;
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, username FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = generateToken(64);
            $expires = date('Y-m-d H:i:s', time() + 3600);

            $stmt = $db->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
            $stmt->execute([$token, $expires, $user['id']]);

            // Send email (placeholder)
            // $resetLink = APP_URL . '/auth/reset.php?token=' . $token;
            // mail($email, 'Password Reset - ' . APP_NAME, "Click here to reset your password: $resetLink", "From: noreply@" . $_SERVER['HTTP_HOST']);
        }

        // Always return success to prevent email enumeration
        echo json_encode([
            'success' => true,
            'message' => 'If an account with that email exists, a reset link has been sent.'
        ]);
    } catch (Exception $e) {
        logError('Forgot password error', ['message' => $e->getMessage()]);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'An unexpected error occurred.']);
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
        <div class="auth-card glass-strong" id="forgotCard">
            <div class="auth-header">
                <div class="auth-icon-wrap">
                    <i class="fas fa-key"></i>
                </div>
                <h1 class="auth-title">Forgot Password?</h1>
                <p class="auth-subtitle">Enter your email and we'll send you a reset link</p>
            </div>

            <form class="auth-form" id="forgotForm" novalidate>
                <?= csrf_field() ?>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" class="form-input" id="email" name="email" placeholder="Enter your registered email" required autocomplete="email" autofocus>
                    </div>
                    <span class="field-error"></span>
                </div>

                <div class="form-success-message" id="successMessage" style="display:none">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>Check your inbox</strong>
                        <p>If an account exists, we've sent a reset link.</p>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg" id="forgotBtn">
                    <span class="btn-text">Send Reset Link</span>
                    <span class="btn-loader"><i class="fas fa-circle-notch fa-spin"></i></span>
                </button>
            </form>

            <div class="auth-footer">
                <p><a href="/auth/login.php" class="auth-link"><i class="fas fa-arrow-left"></i> Back to login</a></p>
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

.auth-bg { position: fixed; inset: 0; z-index: 0; pointer-events: none; }

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

.auth-container { position: relative; z-index: 1; width: 100%; max-width: 440px; }

.auth-card {
    padding: 40px 36px;
    border-radius: var(--radius-xl);
    animation: scaleInBounce .6s ease;
}

.auth-header { text-align: center; margin-bottom: 32px; }

.auth-icon-wrap {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: rgba(108,92,231,.15);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 1.5rem;
    color: var(--primary);
}

.auth-title { font-family: var(--font-display); font-size: 1.75rem; font-weight: 700; margin-bottom: 6px; }
.auth-subtitle { color: var(--text-secondary); font-size: .9rem; }

.input-icon-wrap { position: relative; }
.input-icon-wrap .form-input { padding-left: 44px; height: 50px; }

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

.form-success-message {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 16px;
    background: rgba(0,184,148,.1);
    border: 1px solid rgba(0,184,148,.2);
    border-radius: var(--radius-md);
    margin-bottom: 20px;
}

.form-success-message i { color: var(--success); font-size: 1.2rem; flex-shrink: 0; margin-top: 2px; }
.form-success-message strong { display: block; color: var(--text-primary); font-size: .9rem; margin-bottom: 2px; }
.form-success-message p { color: var(--text-secondary); font-size: .82rem; margin: 0; }

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

.auth-footer {
    text-align: center;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid var(--glass-border);
}

.auth-footer p { font-size: .85rem; color: var(--text-secondary); margin: 0; }
.auth-footer .auth-link { display: inline-flex; align-items: center; gap: 6px; }

@media (max-width: 480px) {
    .auth-card { padding: 28px 20px; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('forgotForm');
    var submitBtn = document.getElementById('forgotBtn');
    var email = document.getElementById('email');
    var successMessage = document.getElementById('successMessage');

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
            showError(input, 'Please enter your email');
            return false;
        }
        if (val && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
            showError(input, 'Invalid email address');
            return false;
        }
        clearError(input);
        return true;
    }

    email.addEventListener('blur', function() { validateField(this); });
    email.addEventListener('input', function() { if (this.dataset.touched) validateField(this); });
    email.addEventListener('focus', function() { this.dataset.touched = '1'; });

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
        if (!validateField(email)) return;

        submitBtn.classList.add('loading');
        submitBtn.disabled = true;
        successMessage.style.display = 'none';

        var formData = {
            csrf_token: document.querySelector('input[name="csrf_token"]').value,
            email: email.value.trim()
        };

        fetch('/auth/forgot.php', {
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
                successMessage.style.display = 'flex';
                email.value = '';
                showToast(data.message || 'Reset link sent!', 'success');
            } else {
                showToast(data.error || 'Failed to send reset link.', 'error');
            }
        })
        .catch(function() {
            submitBtn.classList.remove('loading');
            submitBtn.disabled = false;
            showToast('Connection error. Please try again.', 'error');
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
