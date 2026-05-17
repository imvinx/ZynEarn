<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAuth();
$user = getCurrentUser();
$pageTitle = 'Quizzes';
$userId = $user['id'];
$db = getDB();

$stmt = $db->query("SELECT DISTINCT category FROM quiz_questions ORDER BY category");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

$categoriesData = [];
foreach ($categories as $cat) {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM quiz_questions WHERE category = ?");
    $stmt->execute([$cat]);
    $total = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COALESCE(AVG(score), 0) as avg_score FROM quiz_attempts WHERE user_id = ? AND total_questions > 0");
    $stmt->execute([$userId]);
    $avgScore = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COALESCE(SUM(reward_earned), 0) as total_reward FROM quiz_attempts WHERE user_id = ?");
    $stmt->execute([$userId]);
    $totalReward = $stmt->fetchColumn();

    $categoriesData[] = ['name' => $cat, 'questions' => $total, 'avg_score' => $avgScore, 'total_reward' => $totalReward];
}

// Leaderboard
$stmt = $db->query("SELECT u.username, u.avatar, COALESCE(SUM(qa.reward_earned), 0) as total_earned, COUNT(qa.id) as quizzes_taken FROM quiz_attempts qa JOIN users u ON qa.user_id = u.id GROUP BY qa.user_id ORDER BY total_earned DESC LIMIT 10");
$leaderboard = $stmt->fetchAll();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<style>
.quiz-category-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: var(--space-4);
    margin-bottom: var(--space-6);
}
.quiz-category-card {
    background: var(--card-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    padding: var(--space-5);
    text-align: center;
    transition: var(--transition);
    cursor: pointer;
}
.quiz-category-card:hover {
    border-color: var(--primary);
    transform: translateY(-4px);
    box-shadow: var(--shadow-md);
}
.quiz-category-icon {
    font-size: var(--text-3xl);
    margin-bottom: var(--space-3);
    color: var(--primary);
}
.quiz-category-name {
    font-size: var(--text-lg);
    font-weight: var(--weight-semibold);
    color: var(--text-primary);
}
.quiz-category-count {
    font-size: var(--text-sm);
    color: var(--text-secondary);
    margin-top: var(--space-1);
}
.quiz-container {
    display: none;
    max-width: 700px;
    margin: 0 auto;
}
.quiz-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: var(--space-6);
}
.quiz-timer {
    font-family: var(--font-mono);
    font-size: var(--text-2xl);
    font-weight: var(--weight-bold);
    color: var(--primary);
}
.quiz-progress-text {
    font-size: var(--text-sm);
    color: var(--text-secondary);
}
.quiz-question-card {
    background: var(--card-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    padding: var(--space-6);
    margin-bottom: var(--space-4);
}
.quiz-question-text {
    font-size: var(--text-lg);
    font-weight: var(--weight-semibold);
    color: var(--text-primary);
    margin-bottom: var(--space-5);
}
.quiz-option {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    padding: var(--space-3) var(--space-4);
    background: var(--bg-card-hover);
    border: 2px solid var(--border-primary);
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: var(--transition);
    margin-bottom: var(--space-2);
}
.quiz-option:hover {
    border-color: var(--primary);
    background: rgba(108,92,231,0.05);
}
.quiz-option.selected {
    border-color: var(--primary);
    background: rgba(108,92,231,0.1);
}
.quiz-option.correct {
    border-color: var(--success);
    background: rgba(0,184,148,0.1);
}
.quiz-option.wrong {
    border-color: var(--danger);
    background: rgba(225,112,85,0.1);
}
.quiz-option-letter {
    width: 28px;
    height: 28px;
    border-radius: var(--radius-full);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--text-xs);
    font-weight: var(--weight-bold);
    background: var(--bg-card);
    color: var(--text-primary);
    flex-shrink: 0;
}
.quiz-option.selected .quiz-option-letter { background: var(--primary); color: var(--white); }
.quiz-option.correct .quiz-option-letter { background: var(--success); color: var(--white); }
.quiz-option.wrong .quiz-option-letter { background: var(--danger); color: var(--white); }
.quiz-result {
    text-align: center;
    padding: var(--space-8);
}
.quiz-result-score {
    font-family: var(--font-display);
    font-size: var(--text-5xl);
    font-weight: var(--weight-bold);
    color: var(--primary);
}
.quiz-result-label {
    font-size: var(--text-lg);
    color: var(--text-secondary);
    margin-top: var(--space-2);
}
.quiz-result-reward {
    font-size: var(--text-xl);
    color: var(--success);
    font-weight: var(--weight-bold);
    margin-top: var(--space-4);
}
.leaderboard-item {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    padding: var(--space-3);
    background: var(--bg-card-hover);
    border-radius: var(--radius-md);
    margin-bottom: var(--space-2);
}
.leaderboard-rank {
    width: 28px;
    height: 28px;
    border-radius: var(--radius-full);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--text-xs);
    font-weight: var(--weight-bold);
    flex-shrink: 0;
}
.leaderboard-rank.gold { background: var(--warning); color: var(--text-inverse); }
.leaderboard-rank.silver { background: #C0C0C0; color: var(--text-inverse); }
.leaderboard-rank.bronze { background: #CD7F32; color: var(--white); }
.leaderboard-rank.default { background: var(--bg-card); color: var(--text-tertiary); }
.leaderboard-name { flex: 1; font-size: var(--text-sm); font-weight: var(--weight-medium); color: var(--text-primary); }
.leaderboard-earned { font-family: var(--font-mono); font-size: var(--text-sm); color: var(--success); font-weight: var(--weight-semibold); }
</style>

<div class="dashboard-wrapper">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-section">
            <div class="sidebar-section-title">Main Menu</div>
            <a href="/user/dashboard.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-home"></i></span><span class="sidebar-nav-text">Dashboard</span></a>
            <a href="/user/wallet.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-wallet"></i></span><span class="sidebar-nav-text">Wallet</span></a>
            <a href="/user/earnings.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-chart-line"></i></span><span class="sidebar-nav-text">Earnings</span></a>
            <a href="/user/offers.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-th"></i></span><span class="sidebar-nav-text">Offers</span></a>
            <a href="/user/tasks.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-check-circle"></i></span><span class="sidebar-nav-text">Tasks</span></a>
            <a href="/user/quizzes.php" class="sidebar-nav-item active"><span class="sidebar-nav-icon"><i class="fas fa-brain"></i></span><span class="sidebar-nav-text">Quizzes</span></a>
            <a href="/user/scratch.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-hand-paper"></i></span><span class="sidebar-nav-text">Scratch</span></a>
            <a href="/user/spin.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-circle-notch"></i></span><span class="sidebar-nav-text">Spin</span></a>
            <a href="/user/shortlinks.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-link"></i></span><span class="sidebar-nav-text">Shortlinks</span></a>
            <a href="/user/faucet.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-water"></i></span><span class="sidebar-nav-text">Faucet</span></a>
            <a href="/user/videos.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-video"></i></span><span class="sidebar-nav-text">Videos</span></a>
            <a href="/user/surveys.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-clipboard-list"></i></span><span class="sidebar-nav-text">Surveys</span></a>
            <a href="/user/daily.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-calendar-day"></i></span><span class="sidebar-nav-text">Daily</span></a>
            <a href="/user/achievements.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-trophy"></i></span><span class="sidebar-nav-text">Achievements</span></a>
            <a href="/user/missions.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-rocket"></i></span><span class="sidebar-nav-text">Missions</span></a>
        </div>
        <div class="sidebar-section">
            <div class="sidebar-section-title">Account</div>
            <a href="/user/referrals.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-users"></i></span><span class="sidebar-nav-text">Referrals</span></a>
            <a href="/user/withdraw.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-cash-register"></i></span><span class="sidebar-nav-text">Withdraw</span></a>
            <a href="/user/profile.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-user"></i></span><span class="sidebar-nav-text">Profile</span></a>
            <a href="/auth/logout.php" class="sidebar-nav-item logout-item"><span class="sidebar-nav-icon"><i class="fas fa-sign-out-alt"></i></span><span class="sidebar-nav-text">Logout</span></a>
        </div>
    </aside>
    <main class="main-content">
        <div class="dashboard-top-header">
            <div class="dashboard-greeting">
                <h1>Quizzes</h1>
                <p>Test your knowledge and earn rewards</p>
            </div>
        </div>

        <div id="quizCategoriesView">
            <div class="dashboard-panel-header">
                <div class="dashboard-panel-title">Categories</div>
            </div>
            <div class="quiz-category-grid">
                <?php foreach ($categoriesData as $cat): ?>
                <div class="quiz-category-card" onclick="startQuiz('<?= sanitize($cat['name']) ?>')">
                    <div class="quiz-category-icon"><i class="fas fa-book"></i></div>
                    <div class="quiz-category-name"><?= sanitize($cat['name']) ?></div>
                    <div class="quiz-category-count"><?= $cat['questions'] ?> questions</div>
                    <div style="margin-top: var(--space-3); display: flex; justify-content: center; gap: var(--space-3);">
                        <span class="badge badge-primary badge-sm"><?= number_format($cat['avg_score'], 1) ?>% avg</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="dashboard-panel" style="margin-top: var(--space-6);">
                <div class="dashboard-panel-header">
                    <div class="dashboard-panel-title">Leaderboard</div>
                </div>
                <?php if (count($leaderboard) > 0): ?>
                <?php foreach ($leaderboard as $i => $lb): ?>
                <div class="leaderboard-item">
                    <div class="leaderboard-rank <?= $i === 0 ? 'gold' : ($i === 1 ? 'silver' : ($i === 2 ? 'bronze' : 'default')) ?>"><?= $i + 1 ?></div>
                    <span class="leaderboard-name"><?= sanitize($lb['username']) ?></span>
                    <span style="font-size: var(--text-xs); color: var(--text-tertiary);"><?= $lb['quizzes_taken'] ?> quizzes</span>
                    <span class="leaderboard-earned"><?= formatCurrency($lb['total_earned']) ?></span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="quiz-container" id="quizContainer">
            <div class="quiz-header">
                <div>
                    <div class="quiz-progress-text" id="quizProgress">Question 1 of 10</div>
                </div>
                <div class="quiz-timer" id="quizTimer">05:00</div>
            </div>
            <div id="quizQuestionArea"></div>
        </div>

        <div class="bottom-nav-spacer"></div>
    </main>
</div>

<script>
let quizState = {
    active: false,
    category: '',
    questions: [],
    currentIndex: 0,
    correct: 0,
    timer: null,
    timeLeft: 300,
    answers: [],
    locked: false
};

function startQuiz(category) {
    quizState = { ...quizState, active: true, category, questions: [], currentIndex: 0, correct: 0, timeLeft: 300, answers: [], locked: false };

    fetch('/user/ajax/quiz_start.php?category=' + encodeURIComponent(category), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            quizState.questions = data.questions;
            document.getElementById('quizCategoriesView').style.display = 'none';
            document.getElementById('quizContainer').style.display = 'block';
            showQuestion();
            startTimer();
        } else {
            alert(data.error || 'Failed to load quiz');
        }
    });
}

function showQuestion() {
    const q = quizState.questions[quizState.currentIndex];
    if (!q) return finishQuiz();

    quizState.locked = false;
    const area = document.getElementById('quizQuestionArea');
    const total = quizState.questions.length;
    document.getElementById('quizProgress').textContent = `Question ${quizState.currentIndex + 1} of ${total}`;

    const letters = ['A', 'B', 'C', 'D'];
    let html = `
    <div class="quiz-question-card">
        <div class="quiz-question-text">${q.question}</div>
        ${q.options.map((opt, i) => `
        <div class="quiz-option" data-index="${i}" onclick="selectAnswer(${i})">
            <span class="quiz-option-letter">${letters[i]}</span>
            <span>${opt}</span>
        </div>
        `).join('')}
    </div>
    <button class="btn btn-primary btn-block" onclick="nextQuestion()" id="nextBtn" disabled>Next</button>
    `;
    area.innerHTML = html;
}

function selectAnswer(index) {
    if (quizState.locked) return;
    quizState.locked = true;

    const q = quizState.questions[quizState.currentIndex];
    const options = document.querySelectorAll('.quiz-option');
    options.forEach((opt, i) => {
        opt.classList.remove('selected', 'correct', 'wrong');
        if (i === index) opt.classList.add('selected');
        if (i === q.correct) opt.classList.add('correct');
        if (i === index && i !== q.correct) opt.classList.add('wrong');
    });

    quizState.answers.push({
        questionId: q.id,
        selected: index,
        correct: index === q.correct
    });
    if (index === q.correct) quizState.correct++;

    document.getElementById('nextBtn').disabled = false;
}

function nextQuestion() {
    quizState.currentIndex++;
    if (quizState.currentIndex >= quizState.questions.length) {
        finishQuiz();
    } else {
        showQuestion();
    }
}

function startTimer() {
    if (quizState.timer) clearInterval(quizState.timer);
    const display = document.getElementById('quizTimer');
    quizState.timeLeft = 300;

    quizState.timer = setInterval(() => {
        quizState.timeLeft--;
        const m = Math.floor(quizState.timeLeft / 60);
        const s = quizState.timeLeft % 60;
        display.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        display.style.color = quizState.timeLeft < 30 ? 'var(--danger)' : 'var(--primary)';
        if (quizState.timeLeft <= 0) {
            clearInterval(quizState.timer);
            finishQuiz();
        }
    }, 1000);
}

function finishQuiz() {
    clearInterval(quizState.timer);
    const total = quizState.questions.length;
    const score = total > 0 ? Math.round((quizState.correct / total) * 100) : 0;

    const formData = new FormData();
    formData.append('category', quizState.category);
    formData.append('score', score);
    formData.append('correct', quizState.correct);
    formData.append('total', total);
    formData.append('answers', JSON.stringify(quizState.answers));
    formData.append('csrf_token', '<?= csrf_token() ?>');

    fetch('/user/ajax/quiz_submit.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        const area = document.getElementById('quizQuestionArea');
        const grade = score >= 80 ? 'Excellent!' : (score >= 60 ? 'Good Job!' : (score >= 40 ? 'Not Bad' : 'Keep Trying'));
        area.innerHTML = `
        <div class="quiz-result">
            <div class="quiz-result-score">${score}%</div>
            <div class="quiz-result-label">${grade}</div>
            <div style="margin-top: var(--space-3); color: var(--text-secondary);">${quizState.correct}/${total} correct answers</div>
            ${data.success ? `<div class="quiz-result-reward">+${data.formatted_reward || '<?= APP_CURRENCY_SYMBOL ?>0.00'} earned!</div>` : ''}
            <button class="btn btn-primary mt-6" onclick="backToCategories()">Back to Categories</button>
        </div>
        `;
        document.getElementById('quizTimer').textContent = 'Done!';
    });
}

function backToCategories() {
    quizState.active = false;
    document.getElementById('quizContainer').style.display = 'none';
    document.getElementById('quizCategoriesView').style.display = 'block';
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
