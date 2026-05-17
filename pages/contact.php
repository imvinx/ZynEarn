<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
$pageTitle = 'Contact Us - ZynEarn';
$pageDescription = 'Get in touch with the ZynEarn support team. We are here to help you.';
$bodyClass = 'contact-page';
include __DIR__ . '/../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAjaxRequest()) {
    header('Content-Type: application/json');

    if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid security token.']);
        exit;
    }

    $ip = getClientIP();
    if (isRateLimited('contact_' . $ip, 3, 300)) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Too many messages. Please try again later.']);
        exit;
    }

    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $subject = sanitize($_POST['subject'] ?? '');
    $message = sanitize($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        echo json_encode(['success' => false, 'error' => 'All fields are required.']);
        exit;
    }

    if (!validateEmail($email)) {
        echo json_encode(['success' => false, 'error' => 'Invalid email address.']);
        exit;
    }

    if (strlen($message) < 10) {
        echo json_encode(['success' => false, 'error' => 'Message must be at least 10 characters.']);
        exit;
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO contact_messages (name, email, subject, message, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$name, $email, $subject, $message, $ip, $_SERVER['HTTP_USER_AGENT'] ?? '']);

        $messageId = $db->lastInsertId();

        $adminEmail = getenv('SMTP_FROM') ?: 'support@zynearn.com';
        $headers = "From: {$email}\r\nReply-To: {$email}\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n";
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $emailBody = "New contact message from {$name} ({$email})\n\nSubject: {$subject}\n\nMessage:\n{$message}\n\n---\nIP: {$ip}\nUser Agent: {$userAgent}";
        @mail($adminEmail, "[ZynEarn Contact] {$subject}", $emailBody, $headers);

        echo json_encode(['success' => true, 'message' => 'Message sent successfully! We will get back to you soon.']);
    } catch (Exception $e) {
        logError('Contact form error', ['message' => $e->getMessage()]);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to send message. Please try again later.']);
    }
    exit;
}
?>

<main class="contact-main">
    <div class="legal-hero">
        <div class="container">
            <h1>Contact Us</h1>
            <p class="legal-subtitle">Have a question or need help? We are here for you.</p>
        </div>
    </div>

    <div class="container">
        <div class="contact-grid">
            <div class="contact-info">
                <div class="contact-card">
                    <div class="cc-icon"><i class="fas fa-envelope"></i></div>
                    <h3>Email Us</h3>
                    <p><a href="mailto:support@zynearn.com">support@zynearn.com</a></p>
                    <p class="cc-sub">We reply within 24 hours</p>
                </div>
                <div class="contact-card">
                    <div class="cc-icon"><i class="fab fa-discord"></i></div>
                    <h3>Discord</h3>
                    <p><a href="https://discord.gg/zynearn" target="_blank">Join our Discord</a></p>
                    <p class="cc-sub">Live community support</p>
                </div>
                <div class="contact-card">
                    <div class="cc-icon"><i class="fab fa-telegram-plane"></i></div>
                    <h3>Telegram</h3>
                    <p><a href="https://t.me/ZynEarnSupport" target="_blank">@ZynEarnSupport</a></p>
                    <p class="cc-sub">Quick chat support</p>
                </div>
                <div class="contact-card">
                    <div class="cc-icon"><i class="fas fa-question-circle"></i></div>
                    <h3>FAQ</h3>
                    <p><a href="/pages/faq.php">View FAQ</a></p>
                    <p class="cc-sub">Find answers instantly</p>
                </div>
            </div>

            <div class="contact-form-wrap">
                <div class="form-header">
                    <h2>Send Us a Message</h2>
                    <p>Fill out the form below and we will get back to you as soon as possible.</p>
                </div>
                <form class="contact-form" id="contactForm" method="POST">
                    <?= csrf_field() ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="name">Your Name</label>
                            <input type="text" class="form-input" id="name" name="name" placeholder="John Doe" required minlength="2">
                            <span class="field-error"></span>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="email">Your Email</label>
                            <input type="email" class="form-input" id="email" name="email" placeholder="john@example.com" required>
                            <span class="field-error"></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="subject">Subject</label>
                        <input type="text" class="form-input" id="subject" name="subject" placeholder="How can we help?" required minlength="3">
                        <span class="field-error"></span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="message">Message</label>
                        <textarea class="form-input form-textarea" id="message" name="message" rows="6" placeholder="Describe your issue or question in detail..." required minlength="10"></textarea>
                        <span class="field-error"></span>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg" id="contactSubmitBtn">
                        <span class="btn-text"><i class="fas fa-paper-plane"></i> Send Message</span>
                        <span class="btn-loader"><i class="fas fa-circle-notch fa-spin"></i></span>
                    </button>
                </form>
                <div id="contactSuccess" class="contact-success" style="display:none;">
                    <i class="fas fa-check-circle"></i>
                    <h3>Message Sent Successfully!</h3>
                    <p>Thank you for reaching out. Our team will review your message and respond within 24 hours.</p>
                </div>
            </div>
        </div>
    </div>
</main>

<div class="toast-container" data-toast-container></div>

<style>
.legal-hero {
    padding: 120px 0 60px;
    text-align: center;
    background: linear-gradient(135deg, rgba(108,92,231,.1), rgba(0,206,201,.05));
}
.legal-hero h1 {
    font-family: var(--font-display);
    font-size: clamp(2rem, 4vw, 3rem);
    font-weight: 700;
    margin-bottom: 8px;
}
.legal-subtitle {
    color: var(--text-secondary);
    font-size: .9rem;
}
.contact-grid {
    display: grid;
    grid-template-columns: 1fr 1.5fr;
    gap: 60px;
    padding: 60px 20px 100px;
    max-width: 1100px;
    margin: 0 auto;
}
.contact-info {
    display: flex;
    flex-direction: column;
    gap: 20px;
}
.contact-card {
    background: var(--dark-card);
    border: 1px solid var(--dark-border);
    border-radius: var(--radius);
    padding: 24px;
    transition: all var(--transition);
}
.contact-card:hover {
    transform: translateY(-3px);
    border-color: rgba(108,92,231,.3);
    box-shadow: var(--shadow-glow);
}
.cc-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: rgba(108,92,231,.1);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    margin-bottom: 14px;
}
.contact-card h3 {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 6px;
}
.contact-card p {
    color: var(--text-secondary);
    font-size: .85rem;
}
.contact-card a {
    color: var(--primary);
    transition: color var(--transition);
}
.contact-card a:hover {
    color: var(--secondary);
}
.cc-sub {
    font-size: .75rem;
    margin-top: 4px;
}
.contact-form-wrap {
    background: var(--dark-card);
    border: 1px solid var(--dark-border);
    border-radius: var(--radius-lg);
    padding: 40px;
}
.form-header {
    margin-bottom: 28px;
}
.form-header h2 {
    font-family: var(--font-display);
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 8px;
}
.form-header p {
    color: var(--text-secondary);
    font-size: .9rem;
}
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.form-group {
    margin-bottom: 20px;
}
.form-label {
    display: block;
    font-size: .85rem;
    font-weight: 500;
    margin-bottom: 6px;
    color: var(--text-primary);
}
.form-input {
    width: 100%;
    padding: 12px 16px;
    border-radius: var(--radius-sm);
    background: var(--dark-surface);
    border: 1px solid var(--dark-border);
    color: var(--text-primary);
    font-size: .9rem;
    transition: all var(--transition);
}
.form-input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(108,92,231,.1);
}
.form-textarea {
    resize: vertical;
    min-height: 140px;
}
.field-error {
    display: block;
    font-size: .75rem;
    color: var(--accent);
    margin-top: 4px;
    min-height: 18px;
}
.contact-success {
    text-align: center;
    padding: 40px;
}
.contact-success i {
    font-size: 3rem;
    color: var(--secondary);
    margin-bottom: 16px;
}
.contact-success h3 {
    font-family: var(--font-display);
    font-size: 1.3rem;
    font-weight: 700;
    margin-bottom: 8px;
}
.contact-success p {
    color: var(--text-secondary);
}
@media (max-width: 768px) {
    .contact-grid {
        grid-template-columns: 1fr;
        gap: 40px;
        padding: 40px 16px 80px;
    }
    .form-row {
        grid-template-columns: 1fr;
    }
    .contact-form-wrap {
        padding: 24px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('contactForm');
    const submitBtn = document.getElementById('contactSubmitBtn');
    const successDiv = document.getElementById('contactSuccess');

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

        var formData = new FormData(form);
        formData.append('X-Requested-With', 'XMLHttpRequest');

        submitBtn.classList.add('loading');
        submitBtn.disabled = true;

        fetch('/pages/contact.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            submitBtn.classList.remove('loading');
            submitBtn.disabled = false;

            if (data.success) {
                form.style.display = 'none';
                successDiv.style.display = 'block';
                showToast(data.message || 'Message sent!', 'success');
            } else {
                showToast(data.error || 'Failed to send message.', 'error');
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
