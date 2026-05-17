<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
$pageTitle = 'Verify Email';

if (!isset($_SESSION['pending_user_id']) && !isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAjaxRequest()) {
    header('Content-Type: application/json');

    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $action = $data['action'] ?? 'verify';

    if (!isset($data['csrf_token']) || !verify_csrf($data['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid security token.']);
        exit;
    }

    $userId = $_SESSION['pending_user_id'] ?? $_SESSION['user_id'] ?? null;
    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'Session expired. Please register again.', 'redirect' => '/auth/register.php']);
        exit;
    }

    try {
        $db = getDB();

        if ($action === 'verify') {
            $otp = preg_replace('/[^0-9]/', '', $data['otp'] ?? '');

            if (strlen($otp) !== OTP_LENGTH) {
                echo json_encode(['success' => false, 'error' => 'Please enter a valid 6-digit code.']);
                exit;
            }

            $stmt = $db->prepare("SELECT id, email_otp, otp_expires_at FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user) {
                echo json_encode(['success' => false, 'error' => 'User not found.']);
                exit;
            }

            if ($user['otp_expires_at'] < date('Y-m-d H:i:s')) {
                echo json_encode(['success' => false, 'error' => 'OTP has expired. Request a new one.']);
                exit;
            }

            if ($user['email_otp'] !== $otp) {
                echo json_encode(['success' => false, 'error' => 'Invalid verification code.']);
                exit;
            }

            $stmt = $db->prepare("UPDATE users SET email_verified_at = NOW(), email_otp = NULL, otp_expires_at = NULL, status = 'active' WHERE id = ?");
            $stmt->execute([$userId]);

            if (isset($_SESSION['pending_user_id'])) {
                createSession($userId);
                unset($_SESSION['pending_user_id']);
                unset($_SESSION['pending_email']);
            }

            echo json_encode(['success' => true, 'redirect' => '/user/dashboard.php', 'message' => 'Email verified successfully!']);
        } elseif ($action === 'resend') {
            $stmt = $db->prepare("SELECT email_otp, otp_expires_at FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user) {
                echo json_encode(['success' => false, 'error' => 'User not found.']);
                exit;
            }

            // Check cooldown (60 seconds)
            if ($user['otp_expires_at'] > date('Y-m-d H:i:s', time() - 240)) {
                echo json_encode(['success' => false, 'error' => 'Please wait before requesting a new code.']);
                exit;
            }

            $newOtp = generateOTP();
            $newExpiry = date('Y-m-d H:i:s', time() + OTP_EXPIRY);

            $stmt = $db->prepare("UPDATE users SET email_otp = ?, otp_expires_at = ? WHERE id = ?");
            $stmt->execute([$newOtp, $newExpiry, $userId]);

            // Send email (placeholder)
            // $email = $_SESSION['pending_email'] ?? '';
            // if ($email) mail($email, 'Your ' . APP_NAME . ' verification code', "Your OTP: $newOtp", "From: noreply@" . $_SERVER['HTTP_HOST']);

            echo json_encode(['success' => true, 'message' => 'A new verification code has been sent.']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid action.']);
        }
    } catch (Exception $e) {
        logError('Email verification error', ['message' => $e->getMessage()]);
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
        <div class="auth-card glass-strong" id="verifyCard">
            <div class="auth-header">
                <div class="auth-icon-wrap">
                    <i class="fas fa-envelope-open-text"></i>
                </div>
                <h1 class="auth-title">Verify Your Email</h1>
                <p class="auth-subtitle">We sent a 6-digit code to your email</p>
                <p class="auth-email-display" id="emailDisplay"><?= htmlspecialchars($_SESSION['pending_email'] ?? 'your email') ?></p>
            </div>

            <form class="auth-form" id="verifyForm" novalidate>
                <?= csrf_field() ?>

                <div class="form-group">
                    <label class="form-label">Verification Code</label>
                    <div class="otp-container" id="otpContainer">
                        <?php for ($i = 0; $i < 6; $i++): ?>
                        <input type="text" class="otp-input" id="otp_<?= $i ?>" name="otp_<?= $i ?>" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code" <?= $i === 0 ? 'autofocus' : '' ?>>
                        <?php endfor; ?>
                    </div>
                    <span class="field-error" id="otpError"></span>
                </div>

                <div class="otp-timer" id="otpTimer">
                    <span id="countdownText">Code expires in <strong id="countdown">5:00</strong></span>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg" id="verifyBtn">
                    <span class="btn-text">Verify Email</span>
                    <span class="btn-loader"><i class="fas fa-circle-notch fa-spin"></i></span>
                </button>

                <div class="otp-resend">
                    <span>Didn't receive the code?</span>
                    <button type="button" class="btn btn-ghost" id="resendBtn" disabled>
                        <span id="resendText">Resend Code (<span id="resendCountdown">60</span>s)</span>
                    </button>
                </div>
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

.auth-email-display {
    font-family: var(--font-mono);
    font-size: .85rem;
    color: var(--text-primary);
    background: var(--bg-input);
    padding: 8px 16px;
    border-radius: var(--radius-md);
    display: inline-block;
    margin-top: 8px;
}

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

.otp-timer {
    text-align: center;
    font-size: .82rem;
    color: var(--text-secondary);
    margin-bottom: 24px;
}

.otp-timer strong {
    font-family: var(--font-mono);
    color: var(--primary);
}

.otp-resend {
    text-align: center;
    margin-top: 20px;
    font-size: .85rem;
    color: var(--text-secondary);
}

.otp-resend .btn-ghost {
    color: var(--primary-light);
    background: none;
    border: none;
    font-size: .85rem;
    font-weight: 500;
    cursor: pointer;
    padding: 4px 8px;
    transition: color var(--transition);
}

.otp-resend .btn-ghost:hover:not(:disabled) { color: var(--primary); text-decoration: underline; }
.otp-resend .btn-ghost:disabled { opacity: .5; cursor: not-allowed; }

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
    text-align: center;
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
    .otp-input { width: 44px; height: 52px; font-size: 1.2rem; }
    .otp-container { gap: 6px; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('verifyForm');
    var submitBtn = document.getElementById('verifyBtn');
    var resendBtn = document.getElementById('resendBtn');
    var otpInputs = document.querySelectorAll('.otp-input');
    var otpError = document.getElementById('otpError');
    var countdownEl = document.getElementById('countdown');
    var resendCountdownEl = document.getElementById('resendCountdown');
    var resendText = document.getElementById('resendText');

    var timerSeconds = 300;
    var resendCooldown = 60;
    var timerInterval, resendInterval;

    function startTimer() {
        timerInterval = setInterval(function() {
            timerSeconds--;
            if (timerSeconds <= 0) {
                clearInterval(timerInterval);
                countdownEl.textContent = 'Expired';
                countdownEl.style.color = 'var(--danger)';
            } else {
                var mins = Math.floor(timerSeconds / 60);
                var secs = timerSeconds % 60;
                countdownEl.textContent = mins + ':' + (secs < 10 ? '0' : '') + secs;
            }
        }, 1000);
    }

    function startResendCooldown() {
        resendBtn.disabled = true;
        var remaining = resendCooldown;
        resendInterval = setInterval(function() {
            remaining--;
            resendCountdownEl.textContent = remaining;
            if (remaining <= 0) {
                clearInterval(resendInterval);
                resendBtn.disabled = false;
                resendText.innerHTML = 'Resend Code';
            }
        }, 1000);
    }

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

            if (index === otpInputs.length - 1 && val) {
                // Auto-submit not triggered to allow review. User clicks Verify.
            }

            otpError.textContent = '';
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

        input.addEventListener('focus', function() {
            this.select();
        });
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

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var code = getOtpValue();

        if (code.length !== 6) {
            otpError.textContent = 'Please enter the complete 6-digit code.';
            return;
        }

        submitBtn.classList.add('loading');
        submitBtn.disabled = true;

        var formData = {
            csrf_token: document.querySelector('input[name="csrf_token"]').value,
            action: 'verify',
            otp: code
        };

        fetch('/auth/verify.php', {
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
                showToast(data.message || 'Email verified!', 'success');
                clearInterval(timerInterval);
                setTimeout(function() {
                    window.location.href = data.redirect || '/user/dashboard.php';
                }, 800);
            } else {
                otpError.textContent = data.error || 'Verification failed.';
                if (data.redirect) {
                    setTimeout(function() { window.location.href = data.redirect; }, 1500);
                }
            }
        })
        .catch(function() {
            submitBtn.classList.remove('loading');
            submitBtn.disabled = false;
            showToast('Connection error. Please try again.', 'error');
        });
    });

    resendBtn.addEventListener('click', function() {
        if (this.disabled) return;

        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/auth/verify.php', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function() {
            var data = JSON.parse(xhr.responseText);
            if (data.success) {
                showToast(data.message || 'New code sent!', 'success');
                timerSeconds = 300;
                startResendCooldown();
                // Reset timer display
                countdownEl.style.color = '';
                // Clear inputs
                otpInputs.forEach(function(input) {
                    input.value = '';
                    input.classList.remove('filled');
                });
                otpInputs[0].focus();
            } else {
                showToast(data.error || 'Failed to resend code.', 'error');
            }
        };
        xhr.send(JSON.stringify({
            csrf_token: document.querySelector('input[name="csrf_token"]').value,
            action: 'resend'
        }));
    });

    // Start countdowns
    startTimer();
    startResendCooldown();
    otpInputs[0].focus();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
