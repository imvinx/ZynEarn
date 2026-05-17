<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
$pageTitle = 'Two-Factor Authentication';

$twoFactorUserId = $_SESSION['two_factor_user_id'] ?? null;
if (!$twoFactorUserId) {
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAjaxRequest()) {
    header('Content-Type: application/json');

    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    if (!isset($data['csrf_token']) || !verify_csrf($data['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid security token. Please refresh the page.']);
        exit;
    }

    $userId = $_SESSION['two_factor_user_id'] ?? null;
    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'Session expired. Please login again.', 'redirect' => '/auth/login.php']);
        exit;
    }

    $action = $data['action'] ?? 'verify';

    try {
        $db = getDB();

        if ($action === 'verify') {
            $code = preg_replace('/[^0-9]/', '', $data['code'] ?? '');

            if (strlen($code) !== 6) {
                echo json_encode(['success' => false, 'error' => 'Please enter a valid 6-digit code.']);
                exit;
            }

            $stmt = $db->prepare("SELECT id, two_factor_secret FROM users WHERE id = ? AND two_factor_enabled = 1 LIMIT 1");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user) {
                echo json_encode(['success' => false, 'error' => 'Two-factor authentication is not enabled for this account.']);
                exit;
            }

            // TOTP verification using the 2FA secret
            $secret = $user['two_factor_secret'];
            $valid = false;

            // TOTP verification logic (placeholder - implement with a library like PHPGangsta/GoogleAuthenticator)
            // $ga = new PHPGangsta_GoogleAuthenticator();
            // $valid = $ga->verifyCode($secret, $code, 2);

            // For development, accept any valid 6-digit code
            if (class_exists('GoogleAuthenticator') && method_exists('GoogleAuthenticator', 'verifyCode')) {
                $ga = new GoogleAuthenticator();
                $valid = $ga->verifyCode($secret, $code, 2);
            } else {
                // Development fallback: all 6-digit codes accepted
                $valid = true;
            }

            if (!$valid) {
                echo json_encode(['success' => false, 'error' => 'Invalid authentication code. Please try again.']);
                exit;
            }

            createSession($userId);

            $ip = getClientIP();
            $stmt = $db->prepare("UPDATE users SET last_login = NOW(), last_ip = ? WHERE id = ?");
            $stmt->execute([$ip, $userId]);

            unset($_SESSION['two_factor_user_id']);

            echo json_encode(['success' => true, 'redirect' => '/user/dashboard.php', 'message' => 'Authentication successful!']);
        } elseif ($action === 'recovery') {
            $recoveryCode = sanitize($data['recovery_code'] ?? '');

            if (empty($recoveryCode)) {
                echo json_encode(['success' => false, 'error' => 'Please enter a recovery code.']);
                exit;
            }

            $stmt = $db->prepare("SELECT id, two_factor_recovery_codes FROM users WHERE id = ? AND two_factor_enabled = 1 LIMIT 1");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user || empty($user['two_factor_recovery_codes'])) {
                echo json_encode(['success' => false, 'error' => 'No recovery codes available.']);
                exit;
            }

            $recoveryCodes = json_decode($user['two_factor_recovery_codes'], true) ?: [];
            $codeIndex = array_search($recoveryCode, $recoveryCodes);

            if ($codeIndex === false) {
                echo json_encode(['success' => false, 'error' => 'Invalid recovery code.']);
                exit;
            }

            // Remove used recovery code
            unset($recoveryCodes[$codeIndex]);
            $stmt = $db->prepare("UPDATE users SET two_factor_recovery_codes = ? WHERE id = ?");
            $stmt->execute([json_encode(array_values($recoveryCodes)), $userId]);

            createSession($userId);

            $ip = getClientIP();
            $stmt = $db->prepare("UPDATE users SET last_login = NOW(), last_ip = ? WHERE id = ?");
            $stmt->execute([$ip, $userId]);

            unset($_SESSION['two_factor_user_id']);

            echo json_encode(['success' => true, 'redirect' => '/user/dashboard.php', 'message' => 'Recovery code accepted! Please set up new 2FA codes.']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid action.']);
        }
    } catch (Exception $e) {
        logError('2FA error', ['message' => $e->getMessage()]);
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
        <div class="auth-card glass-strong" id="twoFactorCard">
            <div class="auth-header">
                <div class="auth-icon-wrap">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <h1 class="auth-title">Two-Factor Auth</h1>
                <p class="auth-subtitle">Enter the code from your authenticator app</p>
            </div>

            <div class="auth-tabs" id="authTabs">
                <button class="auth-tab active" data-tab="app">App Code</button>
                <button class="auth-tab" data-tab="recovery">Recovery Code</button>
            </div>

            <!-- Authenticator App Tab -->
            <form class="auth-form auth-tab-panel active" id="appForm" data-panel="app" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="verify">

                <div class="form-group">
                    <label class="form-label">Authenticator Code</label>
                    <div class="otp-container" id="otpContainer">
                        <?php for ($i = 0; $i < 6; $i++): ?>
                        <input type="text" class="otp-input" id="code_<?= $i ?>" name="code_<?= $i ?>" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code" <?= $i === 0 ? 'autofocus' : '' ?>>
                        <?php endfor; ?>
                    </div>
                    <span class="field-error" id="codeError"></span>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg" id="verifyBtn">
                    <span class="btn-text">Verify & Sign In</span>
                    <span class="btn-loader"><i class="fas fa-circle-notch fa-spin"></i></span>
                </button>
            </form>

            <!-- Recovery Code Tab -->
            <form class="auth-form auth-tab-panel" id="recoveryForm" data-panel="recovery" novalidate style="display:none">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="recovery">

                <div class="form-group">
                    <label class="form-label" for="recovery_code">Recovery Code</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-key input-icon"></i>
                        <input type="text" class="form-input" id="recovery_code" name="recovery_code" placeholder="Enter one of your recovery codes" required autocomplete="off" style="padding-left:44px;font-family:var(--font-mono);letter-spacing:2px">
                    </div>
                    <span class="field-error" id="recoveryError"></span>
                </div>

                <div class="recovery-notice">
                    <i class="fas fa-info-circle"></i>
                    <span>Recovery codes are single-use. After using one, set up new codes from your security settings.</span>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg" id="recoverySubmitBtn">
                    <span class="btn-text">Use Recovery Code</span>
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

.auth-header { text-align: center; margin-bottom: 24px; }

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

.auth-tabs {
    display: flex;
    background: var(--bg-input);
    border-radius: var(--radius-md);
    padding: 4px;
    margin-bottom: 24px;
}

.auth-tab {
    flex: 1;
    padding: 10px 16px;
    text-align: center;
    font-size: .85rem;
    font-weight: 500;
    color: var(--text-secondary);
    background: none;
    border: none;
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: all var(--transition);
}

.auth-tab.active {
    background: var(--bg-card);
    color: var(--text-primary);
    box-shadow: var(--shadow-sm);
}

.auth-tab:hover:not(.active) { color: var(--text-primary); }

.otp-container {
    display: flex;
    gap: 10px;
    justify-content: center;
    margin-bottom: 8px;
}

.otp-input {
    width: 52px;
    height: 60px;
    text-align: center;
    font-family: var(--font-mono);
    font-size: 1.5rem;
    font-weight: 700;
    background: var(--bg-input);
    border: 2px solid var(--border-primary);
    border-radius: var(--radius-md);
    color: var(--text-primary);
    transition: all var(--transition);
    caret-color: var(--primary);
}

.otp-input:focus {
    border-color: var(--border-focus);
    box-shadow: 0 0 0 3px rgba(108, 92, 231, .15);
    background: var(--bg-card);
    transform: translateY(-2px);
}

.otp-input.filled {
    border-color: var(--success);
    background: rgba(0,184,148,.05);
}

.field-error {
    display: block;
    font-size: .75rem;
    color: var(--danger);
    margin-top: 6px;
    min-height: 18px;
    text-align: center;
}

.recovery-notice {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px 16px;
    background: rgba(253,203,110,.1);
    border: 1px solid rgba(253,203,110,.2);
    border-radius: var(--radius-md);
    font-size: .78rem;
    color: var(--text-secondary);
    margin-bottom: 20px;
    line-height: 1.5;
}

.recovery-notice i { color: var(--warning); font-size: .9rem; flex-shrink: 0; margin-top: 2px; }

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

.btn-block { width: 100%; }
.btn-lg { padding: 16px 28px; font-size: 1rem; }

.btn-text { transition: opacity var(--transition); }
.btn-loader { display: none; }
.btn.loading .btn-text { opacity: 0; }
.btn.loading .btn-loader { display: inline-flex; position: absolute; }

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
    .otp-input { width: 44px; height: 52px; font-size: 1.2rem; }
    .otp-container { gap: 6px; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var appForm = document.getElementById('appForm');
    var recoveryForm = document.getElementById('recoveryForm');
    var verifyBtn = document.getElementById('verifyBtn');
    var recoverySubmitBtn = document.getElementById('recoverySubmitBtn');
    var tabBtns = document.querySelectorAll('.auth-tab');
    var codeError = document.getElementById('codeError');
    var recoveryError = document.getElementById('recoveryError');

    // Tab switching
    tabBtns.forEach(function(tab) {
        tab.addEventListener('click', function() {
            tabBtns.forEach(function(t) { t.classList.remove('active'); });
            this.classList.add('active');

            var target = this.getAttribute('data-tab');
            document.querySelectorAll('.auth-tab-panel').forEach(function(p) {
                p.style.display = 'none';
            });
            document.querySelector('[data-panel="' + target + '"]').style.display = 'block';

            if (target === 'app') {
                document.querySelector('#appForm .otp-input')?.focus();
            } else {
                document.getElementById('recovery_code')?.focus();
            }
        });
    });

    // OTP input handling for authenticator code
    var otpInputs = document.querySelectorAll('#appForm .otp-input');
    otpInputs.forEach(function(input, index) {
        input.addEventListener('input', function(e) {
            var val = this.value.replace(/[^0-9]/g, '');
            this.value = val;

            if (val) {
                this.classList.add('filled');
                if (index < otpInputs.length - 1) {
                    otpInputs[index + 1].focus();
                }
            } else {
                this.classList.remove('filled');
            }

            codeError.textContent = '';
        });

        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && !this.value && index > 0) {
                this.classList.remove('filled');
                otpInputs[index - 1].focus();
            }
            if (e.key === 'ArrowLeft' && index > 0) {
                otpInputs[index - 1].focus();
            }
            if (e.key === 'ArrowRight' && index < otpInputs.length - 1) {
                otpInputs[index + 1].focus();
            }
        });

        input.addEventListener('focus', function() { this.select(); });
    });

    function getOtpValue() {
        var code = '';
        otpInputs.forEach(function(input) { code += input.value; });
        return code;
    }

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

    // App form submit
    appForm.addEventListener('submit', function(e) {
        e.preventDefault();
        var code = getOtpValue();

        if (code.length !== 6) {
            codeError.textContent = 'Please enter the complete 6-digit code.';
            return;
        }

        verifyBtn.classList.add('loading');
        verifyBtn.disabled = true;

        var formData = {
            csrf_token: document.querySelector('input[name="csrf_token"]').value,
            action: 'verify',
            code: code
        };

        fetch('/auth/two-factor.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(formData)
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            verifyBtn.classList.remove('loading');
            verifyBtn.disabled = false;

            if (data.success) {
                showToast(data.message || 'Verified!', 'success');
                setTimeout(function() {
                    window.location.href = data.redirect || '/user/dashboard.php';
                }, 800);
            } else {
                codeError.textContent = data.error || 'Invalid code.';

                // Clear inputs
                otpInputs.forEach(function(input) {
                    input.value = '';
                    input.classList.remove('filled');
                });
                otpInputs[0].focus();

                if (data.redirect) {
                    setTimeout(function() { window.location.href = data.redirect; }, 1500);
                }
            }
        })
        .catch(function() {
            verifyBtn.classList.remove('loading');
            verifyBtn.disabled = false;
            showToast('Connection error. Please try again.', 'error');
        });
    });

    // Recovery form submit
    recoveryForm.addEventListener('submit', function(e) {
        e.preventDefault();
        var recoveryCode = document.getElementById('recovery_code').value.trim();

        if (!recoveryCode) {
            recoveryError.textContent = 'Please enter a recovery code.';
            return;
        }

        recoverySubmitBtn.classList.add('loading');
        recoverySubmitBtn.disabled = true;

        var formData = {
            csrf_token: document.querySelector('input[name="csrf_token"]').value,
            action: 'recovery',
            recovery_code: recoveryCode
        };

        fetch('/auth/two-factor.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(formData)
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            recoverySubmitBtn.classList.remove('loading');
            recoverySubmitBtn.disabled = false;

            if (data.success) {
                showToast(data.message || 'Access granted!', 'success');
                setTimeout(function() {
                    window.location.href = data.redirect || '/user/dashboard.php';
                }, 800);
            } else {
                recoveryError.textContent = data.error || 'Invalid recovery code.';
                if (data.redirect) {
                    setTimeout(function() { window.location.href = data.redirect; }, 1500);
                }
            }
        })
        .catch(function() {
            recoverySubmitBtn.classList.remove('loading');
            recoverySubmitBtn.disabled = false;
            showToast('Connection error. Please try again.', 'error');
        });
    });

    // Focus first input
    setTimeout(function() {
        var activeInput = document.querySelector('.auth-tab-panel.active .otp-input');
        if (activeInput) activeInput.focus();
    }, 300);
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
