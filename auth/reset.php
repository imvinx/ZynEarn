<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/user/dashboard.php');
    exit;
}
$pageTitle = 'Reset Password';

$tokenValid = false;
$token = $_GET['token'] ?? '';

if (!empty($token)) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expires > NOW() LIMIT 1");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        if ($user) {
            $tokenValid = true;
        }
    } catch (Exception $e) {
        logError('Reset token validation error', ['message' => $e->getMessage()]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAjaxRequest()) {
    header('Content-Type: application/json');

    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    if (!isset($data['csrf_token']) || !verify_csrf($data['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid security token. Please refresh the page.']);
        exit;
    }

    $token = sanitize($data['token'] ?? '');
    $password = $data['password'] ?? '';
    $confirmPassword = $data['confirm_password'] ?? '';

    if (empty($token)) {
        echo json_encode(['success' => false, 'error' => 'Invalid reset token.']);
        exit;
    }

    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters.']);
        exit;
    }

    if ($password !== $confirmPassword) {
        echo json_encode(['success' => false, 'error' => 'Passwords do not match.']);
        exit;
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expires > NOW() LIMIT 1");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            echo json_encode(['success' => false, 'error' => 'Invalid or expired reset token.']);
            exit;
        }

        $hashedPassword = hashPassword($password);
        $stmt = $db->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
        $stmt->execute([$hashedPassword, $user['id']]);

        // Invalidate all sessions
        $stmt = $db->prepare("UPDATE user_sessions SET is_active = 0 WHERE user_id = ?");
        $stmt->execute([$user['id']]);

        echo json_encode(['success' => true, 'redirect' => '/auth/login.php', 'message' => 'Password reset successfully! You can now login.']);
    } catch (Exception $e) {
        logError('Reset password error', ['message' => $e->getMessage()]);
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
        <div class="auth-card glass-strong" id="resetCard">
            <div class="auth-header">
                <div class="auth-icon-wrap">
                    <i class="fas fa-lock-open"></i>
                </div>
                <h1 class="auth-title">Reset Password</h1>
                <p class="auth-subtitle">Choose a new password for your account</p>
            </div>

            <?php if ($tokenValid): ?>
            <form class="auth-form" id="resetForm" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <div class="form-group">
                    <label class="form-label" for="password">New Password</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" class="form-input" id="password" name="password" placeholder="Enter new password" required minlength="8" autocomplete="new-password" autofocus>
                        <button type="button" class="password-toggle" data-toggle="password" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <span class="field-error"></span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm New Password</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" class="form-input" id="confirm_password" name="confirm_password" placeholder="Repeat new password" required autocomplete="new-password">
                        <button type="button" class="password-toggle" data-toggle="password" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <span class="field-error"></span>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg" id="resetBtn">
                    <span class="btn-text">Reset Password</span>
                    <span class="btn-loader"><i class="fas fa-circle-notch fa-spin"></i></span>
                </button>
            </form>
            <?php else: ?>
            <div class="auth-error-state">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Invalid or Expired Link</h3>
                <p>This password reset link is invalid or has expired. Please request a new one.</p>
                <a href="/auth/forgot.php" class="btn btn-primary">Request New Link</a>
            </div>
            <?php endif; ?>

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

.auth-error-state {
    text-align: center;
    padding: 20px 0;
}

.auth-error-state i {
    font-size: 3rem;
    color: var(--warning);
    margin-bottom: 16px;
    display: block;
}

.auth-error-state h3 {
    font-family: var(--font-display);
    font-size: 1.2rem;
    font-weight: 600;
    margin-bottom: 8px;
}

.auth-error-state p {
    color: var(--text-secondary);
    font-size: .85rem;
    margin-bottom: 24px;
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
    <?php if ($tokenValid): ?>
    var form = document.getElementById('resetForm');
    var submitBtn = document.getElementById('resetBtn');

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

        clearError(input);
        return true;
    }

    var inputs = form.querySelectorAll('input');
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
            token: document.querySelector('input[name="token"]').value,
            password: document.getElementById('password').value,
            confirm_password: document.getElementById('confirm_password').value
        };

        fetch('/auth/reset.php', {
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
                showToast(data.message || 'Password reset successful!', 'success');
                setTimeout(function() {
                    window.location.href = data.redirect || '/auth/login.php';
                }, 1200);
            } else {
                showToast(data.error || 'Failed to reset password.', 'error');
            }
        })
        .catch(function() {
            submitBtn.classList.remove('loading');
            submitBtn.disabled = false;
            showToast('Connection error. Please try again.', 'error');
        });
    });
    <?php endif; ?>
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
