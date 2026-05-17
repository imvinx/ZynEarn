<?php
require_once __DIR__ . '/../../includes/auth_middleware.php';
$pageTitle = 'AI Assistant - ZynEarn';
$pageDescription = 'Chat with ZynEarn AI assistant for help, tips, and guidance.';
$bodyClass = 'ai-chat-page';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAjaxRequest()) {
    header('Content-Type: application/json');

    if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid security token.']);
        exit;
    }

    $userId = $_SESSION['user_id'];
    $message = sanitize($_POST['message'] ?? '');
    $action = $_POST['action'] ?? 'chat';

    if (empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Message is required.']);
        exit;
    }

    $responses = [
        'hello' => ['Hello! How can I help you with ZynEarn today?', 'Hi there! Ready to boost your earnings?', 'Hey! What can I assist you with?'],
        'hi' => ['Hello! How can I help you with ZynEarn today?', 'Hi there! Ready to boost your earnings?', 'Hey! What can I assist you with?'],
        'how' => ['Great question! Here are the main ways to earn: complete offers from our offerwall, take surveys, complete tasks, use the faucet, spin the wheel, scratch cards, watch videos, answer quizzes, and refer friends. Each method has different payout rates.'],
        'earn' => ['You can earn through multiple methods: Offerwall (up to $50/offer), Surveys ($1-$15 each), Shortlinks ($0.50-$2/click), Faucet (up to $0.10/claim), Spin Wheel (up to $100), Scratch Cards (up to $25), Tasks ($0.50-$10), Videos, Quizzes, and Referrals (20% lifetime commission).'],
        'withdraw' => ['To withdraw your earnings, go to your dashboard and click "Withdraw". You can withdraw via PayPal, Bitcoin, Ethereum, USDT, gift cards, or bank transfer. Minimum withdrawal is $1. Processing takes 24-48 hours for standard members, instantly for Gold+ members.'],
        'referral' => ['Our referral program lets you earn 20% commission on your direct referrals for life, plus 5% on their referrals too. Share your unique referral link from your dashboard. Top referrers also win monthly bonus prizes up to $1,000!'],
        'vip' => ['We have 5 membership tiers: Free (1x), Silver - $5/mo (1.5x), Gold - $15/mo (2.5x), Platinum - $50/mo (4x), and VIP - $100/mo (6x). Higher tiers unlock better multipliers, exclusive offers, instant withdrawals, and priority support.'],
        'help' => ['I can help you with: earning methods, withdrawals, account issues, referrals, membership tiers, daily bonuses, platform features, and general questions. Just ask me anything about ZynEarn!'],
        'balance' => ['You can check your balance anytime from your dashboard. Your balance = total earnings minus withdrawals. Keep earning to grow your balance and withdraw when you reach the $1 minimum.'],
        'daily' => ['Don\'t forget to claim your daily bonus! The bonus increases with each consecutive day you claim. Login daily to build your streak and earn up to $1.00 per day in streak bonuses.'],
        'thanks' => ['You\'re welcome! If you have any other questions, feel free to ask. Happy earning!', 'Glad I could help! Let me know if you need anything else.', 'My pleasure! Enjoy your earnings journey with ZynEarn!'],
        'bye' => ['Goodbye! Come back anytime you need help. Happy earning!', 'See you later! Keep up the great work on ZynEarn!', 'Take care! Remember to check your daily bonus!'],
        'faucet' => ['The faucet gives you free rewards every 5 minutes. Just click the faucet button on your dashboard and watch the timer. Base reward starts at $0.0001 and can multiply based on your membership tier.'],
        'quiz' => ['Quizzes are a fun way to earn! Answer questions correctly and earn rewards. Easy quizzes pay $0.01, medium $0.025, and hard $0.05 per correct answer. Test your knowledge and earn!'],
        'spin' => ['The Spin Wheel gives you a chance to win up to $100 every day! Each spin costs $0.02 but the potential rewards make it exciting. Try your luck daily for big wins.'],
        'scratch' => ['Scratch cards are instant-win games. Each card costs $0.05 and you can win up to $1.00 per card. Reveal matching symbols to win prizes instantly!'],
        'level' => ['Your level increases as you earn XP points. Complete tasks, earn money, and refer friends to gain XP. Higher levels unlock achievements and rewards. There are 15 levels to progress through!'],
        'achievement' => ['Achievements are special milestones you can unlock by earning, referring friends, reaching levels, and maintaining daily streaks. Each achievement gives you XP and cash rewards. Check your achievements page!'],
        'security' => ['Your security is our priority. We use Argon2id password hashing, CSRF protection, rate limiting, SSL encryption, and two-factor authentication. Never share your password and enable 2FA for extra protection.'],
        'default' => [
            'That\'s a great question! I recommend checking our <a href="/pages/faq.php">FAQ page</a> for detailed information. If you need further assistance, feel free to <a href="/pages/contact.php">contact our support team</a>.',
            'I\'d love to help with that! For the most accurate information, please visit our <a href="/pages/faq.php">FAQ</a> or reach out to <a href="/pages/contact.php">support</a>.',
            'Good question! While I try to cover common topics, you might find more details in our <a href="/pages/faq.php">FAQ section</a> or by <a href="/pages/contact.php">contacting support</a>.'
        ]
    ];

    $response = '';
    $lowerMsg = strtolower($message);

    $matched = false;
    foreach ($responses as $keyword => $replies) {
        if ($keyword === 'default') continue;
        if (strpos($lowerMsg, $keyword) !== false) {
            $response = $replies[array_rand(is_array($replies) && isset($replies[0]) ? $replies : [$replies])];
            $matched = true;
            break;
        }
    }

    if (!$matched) {
        $defaults = $responses['default'];
        $response = $defaults[array_rand($defaults)];
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO ai_chat_log (user_id, user_message, bot_response, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$userId, $message, $response]);

        $stmt = $db->prepare("SELECT COUNT(*) FROM ai_chat_log WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $stmt->execute([$userId]);
        $recentCount = $stmt->fetchColumn();

        echo json_encode([
            'success' => true,
            'response' => $response,
            'conversation_id' => $db->lastInsertId(),
            'recent_count' => $recentCount,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        logError('AI chat error', ['message' => $e->getMessage()]);
        echo json_encode([
            'success' => true,
            'response' => $response,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    exit;
}

include __DIR__ . '/../../includes/header.php';
?>

<main class="ai-chat-main">
    <div class="chat-container">
        <div class="chat-header">
            <div class="chat-avatar">
                <i class="fas fa-robot"></i>
            </div>
            <div class="chat-header-info">
                <h2>ZynEarn AI Assistant</h2>
                <p class="chat-status"><span class="status-dot"></span> Online - Ready to help</p>
            </div>
            <button class="chat-clear-btn" id="chatClearBtn" title="Clear conversation"><i class="fas fa-trash-alt"></i></button>
        </div>

        <div class="chat-messages" id="chatMessages">
            <div class="message bot-message">
                <div class="msg-avatar"><i class="fas fa-robot"></i></div>
                <div class="msg-content">
                    <p>Hi! I'm Zyn, your AI earning assistant. I can help you with:</p>
                    <ul>
                        <li>Earning methods and strategies</li>
                        <li>Withdrawal information</li>
                        <li>Referral program details</li>
                        <li>Membership tiers and benefits</li>
                        <li>Platform features and tips</li>
                    </ul>
                    <p>What would you like to know? 😊</p>
                    <span class="msg-time">Just now</span>
                </div>
            </div>
        </div>

        <div class="chat-suggestions" id="chatSuggestions">
            <button class="suggestion-chip" data-msg="How can I earn money?">How to earn?</button>
            <button class="suggestion-chip" data-msg="Tell me about withdrawals">Withdrawals</button>
            <button class="suggestion-chip" data-msg="How does the referral program work?">Referrals</button>
            <button class="suggestion-chip" data-msg="Tell me about VIP membership">VIP Tiers</button>
            <button class="suggestion-chip" data-msg="Help me get started">Get Started</button>
        </div>

        <form class="chat-input-form" id="chatForm" autocomplete="off">
            <?= csrf_field() ?>
            <div class="chat-input-wrap">
                <input type="text" class="chat-input" id="chatInput" name="message" placeholder="Type your message..." required autocomplete="off">
                <button type="button" class="chat-voice-btn" id="chatVoiceBtn" title="Voice input" aria-label="Voice input">
                    <i class="fas fa-microphone"></i>
                </button>
                <button type="submit" class="chat-send-btn" id="chatSendBtn" aria-label="Send message">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </form>

        <div class="chat-footer">
            <p>AI responses are automated. For urgent issues, <a href="/pages/contact.php">contact support</a>.</p>
        </div>
    </div>
</main>

<style>
.ai-chat-main {
    display: flex;
    justify-content: center;
    padding: 30px 20px 100px;
    min-height: calc(100vh - 80px);
}
.chat-container {
    width: 100%;
    max-width: 700px;
    background: var(--dark-card);
    border: 1px solid var(--dark-border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(0,0,0,.2);
}
.chat-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 20px 24px;
    border-bottom: 1px solid var(--dark-border);
    background: rgba(108,92,231,.05);
}
.chat-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: var(--gradient-main);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: #fff;
    flex-shrink: 0;
}
.chat-header-info h2 {
    font-family: var(--font-display);
    font-size: 1.1rem;
    font-weight: 600;
}
.chat-status {
    font-size: .75rem;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 6px;
}
.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--secondary);
    display: inline-block;
    animation: pulse-dot 2s ease-in-out infinite;
}
@keyframes pulse-dot {
    0%, 100% { opacity: 1; }
    50% { opacity: .4; }
}
.chat-clear-btn {
    margin-left: auto;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(255,107,107,.1);
    border: none;
    color: var(--accent);
    font-size: .85rem;
    cursor: pointer;
    transition: all var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
}
.chat-clear-btn:hover {
    background: rgba(255,107,107,.2);
}
.chat-messages {
    flex: 1;
    padding: 24px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 16px;
    max-height: 480px;
    min-height: 300px;
}
.message {
    display: flex;
    gap: 12px;
    max-width: 85%;
    animation: msgIn .3s ease;
}
@keyframes msgIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.bot-message { align-self: flex-start; }
.user-message { align-self: flex-end; flex-direction: row-reverse; }
.msg-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .85rem;
    flex-shrink: 0;
}
.bot-message .msg-avatar {
    background: rgba(108,92,231,.15);
    color: var(--primary);
}
.user-message .msg-avatar {
    background: rgba(0,206,201,.15);
    color: var(--secondary);
}
.msg-content {
    background: var(--dark-surface);
    border: 1px solid var(--dark-border);
    border-radius: 16px;
    padding: 14px 18px;
    font-size: .88rem;
    line-height: 1.7;
}
.bot-message .msg-content {
    border-bottom-left-radius: 4px;
}
.user-message .msg-content {
    border-bottom-right-radius: 4px;
    background: rgba(108,92,231,.1);
    border-color: rgba(108,92,231,.2);
}
.msg-content p {
    margin: 0 0 8px;
}
.msg-content p:last-child {
    margin-bottom: 0;
}
.msg-content ul {
    margin: 8px 0;
    padding-left: 20px;
    list-style: disc;
}
.msg-content ul li {
    margin-bottom: 4px;
}
.msg-content a {
    color: var(--primary);
    text-decoration: underline;
}
.msg-time {
    display: block;
    font-size: .68rem;
    color: var(--text-secondary);
    margin-top: 6px;
}
.chat-suggestions {
    display: flex;
    gap: 8px;
    padding: 0 24px 12px;
    flex-wrap: wrap;
}
.suggestion-chip {
    padding: 8px 14px;
    border-radius: 50px;
    background: rgba(108,92,231,.08);
    border: 1px solid rgba(108,92,231,.15);
    color: var(--text-secondary);
    font-size: .78rem;
    cursor: pointer;
    transition: all var(--transition);
    white-space: nowrap;
}
.suggestion-chip:hover {
    background: rgba(108,92,231,.15);
    color: var(--primary);
    border-color: rgba(108,92,231,.3);
}
.chat-input-form {
    padding: 16px 24px;
    border-top: 1px solid var(--dark-border);
}
.chat-input-wrap {
    display: flex;
    gap: 8px;
    align-items: center;
    background: var(--dark-surface);
    border: 1px solid var(--dark-border);
    border-radius: 50px;
    padding: 4px 4px 4px 20px;
    transition: all var(--transition);
}
.chat-input-wrap:focus-within {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(108,92,231,.1);
}
.chat-input {
    flex: 1;
    background: none;
    border: none;
    color: var(--text-primary);
    font-size: .9rem;
    padding: 10px 0;
    outline: none;
}
.chat-input::placeholder {
    color: var(--text-secondary);
}
.chat-voice-btn, .chat-send-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    cursor: pointer;
    transition: all var(--transition);
    font-size: .9rem;
}
.chat-voice-btn {
    background: none;
    color: var(--text-secondary);
}
.chat-voice-btn:hover {
    color: var(--primary);
}
.chat-send-btn {
    background: var(--gradient-main);
    color: #fff;
}
.chat-send-btn:hover {
    box-shadow: 0 4px 15px rgba(108,92,231,.3);
}
.chat-send-btn:disabled {
    opacity: .5;
    cursor: not-allowed;
}
.chat-footer {
    padding: 12px 24px;
    border-top: 1px solid var(--glass-border);
    text-align: center;
}
.chat-footer p {
    font-size: .72rem;
    color: var(--text-secondary);
}
.chat-footer a {
    color: var(--primary);
}
.typing-indicator {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 14px 18px;
    background: var(--dark-surface);
    border: 1px solid var(--dark-border);
    border-radius: 16px;
    border-bottom-left-radius: 4px;
    align-self: flex-start;
}
.typing-indicator span {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--text-secondary);
    animation: typingDot 1.4s ease-in-out infinite;
}
.typing-indicator span:nth-child(2) { animation-delay: .2s; }
.typing-indicator span:nth-child(3) { animation-delay: .4s; }
@keyframes typingDot {
    0%, 60%, 100% { transform: translateY(0); opacity: .4; }
    30% { transform: translateY(-6px); opacity: 1; }
}
@media (max-width: 768px) {
    .ai-chat-main { padding: 20px 12px 90px; }
    .chat-messages { max-height: 400px; padding: 16px; }
    .message { max-width: 95%; }
    .chat-suggestions { padding: 0 16px 8px; }
    .chat-input-form { padding: 12px 16px; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('chatForm');
    const input = document.getElementById('chatInput');
    const sendBtn = document.getElementById('chatSendBtn');
    const messages = document.getElementById('chatMessages');
    const clearBtn = document.getElementById('chatClearBtn');
    const suggestions = document.querySelectorAll('.suggestion-chip');
    const voiceBtn = document.getElementById('chatVoiceBtn');

    function appendMessage(text, isUser, time) {
        const div = document.createElement('div');
        div.className = 'message ' + (isUser ? 'user-message' : 'bot-message');
        const avatar = isUser ? '<div class="msg-avatar"><i class="fas fa-user"></i></div>' : '<div class="msg-avatar"><i class="fas fa-robot"></i></div>';
        const t = time || new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        div.innerHTML = avatar + '<div class="msg-content">' + text + '<span class="msg-time">' + t + '</span></div>';
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
    }

    function showTyping() {
        const div = document.createElement('div');
        div.className = 'typing-indicator';
        div.id = 'typingIndicator';
        div.innerHTML = '<span></span><span></span><span></span>';
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
    }

    function hideTyping() {
        const el = document.getElementById('typingIndicator');
        if (el) el.remove();
    }

    function sendMessage(msg) {
        if (!msg || !msg.trim()) return;
        msg = msg.trim();

        appendMessage('<p>' + msg.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</p>', true);
        input.value = '';
        sendBtn.disabled = true;
        showTyping();

        var formData = new FormData();
        formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
        formData.append('message', msg);
        formData.append('action', 'chat');

        fetch('/modules/ai/chat.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            hideTyping();
            sendBtn.disabled = false;
            if (data.success && data.response) {
                appendMessage('<p>' + data.response + '</p>', false, data.timestamp ? new Date(data.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : null);
            } else {
                appendMessage('<p>Sorry, I encountered an error. Please try again.</p>', false);
            }
        })
        .catch(function() {
            hideTyping();
            sendBtn.disabled = false;
            appendMessage('<p>Connection error. Please check your internet and try again.</p>', false);
        });
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        sendMessage(input.value);
    });

    suggestions.forEach(function(btn) {
        btn.addEventListener('click', function() {
            sendMessage(this.getAttribute('data-msg'));
        });
    });

    clearBtn.addEventListener('click', function() {
        if (confirm('Clear conversation history?')) {
            var botMessages = messages.querySelectorAll('.message');
            botMessages.forEach(function(m) { m.remove(); });
            appendMessage('<p>Conversation cleared. How can I help you?</p>', false);
        }
    });

    if (voiceBtn && 'webkitSpeechRecognition' in window) {
        var recognition = new webkitSpeechRecognition();
        recognition.continuous = false;
        recognition.interimResults = false;
        recognition.lang = 'en-US';
        recognition.onresult = function(e) {
            var transcript = e.results[0][0].transcript;
            input.value = transcript;
            sendMessage(transcript);
        };
        recognition.onerror = function() {
            voiceBtn.style.color = '';
        };
        voiceBtn.addEventListener('click', function() {
            this.style.color = 'var(--accent)';
            recognition.start();
        });
    } else if (voiceBtn) {
        voiceBtn.style.display = 'none';
    }

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage(this.value);
        }
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
