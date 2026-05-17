<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAuth();
$user = getCurrentUser();
$pageTitle = 'Surveys';
$userId = $user['id'];
$db = getDB();

$stmt = $db->prepare("SELECT * FROM surveys WHERE status = 'active' ORDER BY reward_amount DESC");
$stmt->execute();
$surveys = $stmt->fetchAll();

$stmt = $db->prepare("SELECT sr.*, s.title as survey_title, s.reward_amount FROM survey_responses sr JOIN surveys s ON sr.survey_id = s.id WHERE sr.user_id = ? ORDER BY sr.created_at DESC LIMIT 10");
$stmt->execute([$userId]);
$surveyHistory = $stmt->fetchAll();

$totalBalance = getUserBalance($userId);
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<style>
.surveys-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: var(--space-4);
}
.survey-card {
    background: var(--card-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    padding: var(--space-5);
    transition: var(--transition);
    display: flex;
    flex-direction: column;
}
.survey-card:hover { border-color: var(--border-light); transform: translateY(-2px); box-shadow: var(--shadow-md); }
.survey-card-header {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    margin-bottom: var(--space-3);
}
.survey-card-icon {
    width: 44px;
    height: 44px;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--text-xl);
    background: rgba(108,92,231,0.15);
    color: var(--primary);
    flex-shrink: 0;
}
.survey-card-title {
    flex: 1;
    font-size: var(--text-sm);
    font-weight: var(--weight-semibold);
    color: var(--text-primary);
}
.survey-card-desc {
    font-size: var(--text-xs);
    color: var(--text-secondary);
    margin-bottom: var(--space-3);
    flex: 1;
}
.survey-card-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: var(--space-3);
    border-top: 1px solid var(--border-primary);
}
.survey-card-reward {
    font-family: var(--font-display);
    font-size: var(--text-lg);
    font-weight: var(--weight-bold);
    color: var(--success);
}
.survey-card-time { font-size: var(--text-xs); color: var(--text-tertiary); }
.survey-container {
    display: none;
    max-width: 600px;
    margin: 0 auto;
}
.survey-question {
    margin-bottom: var(--space-5);
}
.survey-question-text {
    font-size: var(--text-base);
    font-weight: var(--weight-semibold);
    color: var(--text-primary);
    margin-bottom: var(--space-3);
}
.survey-option {
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
.survey-option:hover { border-color: var(--primary); background: rgba(108,92,231,0.05); }
.survey-option.selected { border-color: var(--primary); background: rgba(108,92,231,0.1); }
.survey-nav {
    display: flex;
    justify-content: space-between;
    margin-top: var(--space-5);
}
.history-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--space-3);
    background: var(--bg-card-hover);
    border-radius: var(--radius-md);
    margin-bottom: var(--space-2);
    font-size: var(--text-sm);
}
.history-title { flex: 1; color: var(--text-primary); font-weight: var(--weight-medium); }
.history-status { font-size: var(--text-xs); }
.history-date { font-size: var(--text-xs); color: var(--text-tertiary); }
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
            <a href="/user/quizzes.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-brain"></i></span><span class="sidebar-nav-text">Quizzes</span></a>
            <a href="/user/scratch.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-hand-paper"></i></span><span class="sidebar-nav-text">Scratch</span></a>
            <a href="/user/spin.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-circle-notch"></i></span><span class="sidebar-nav-text">Spin</span></a>
            <a href="/user/shortlinks.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-link"></i></span><span class="sidebar-nav-text">Shortlinks</span></a>
            <a href="/user/faucet.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-water"></i></span><span class="sidebar-nav-text">Faucet</span></a>
            <a href="/user/videos.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-video"></i></span><span class="sidebar-nav-text">Videos</span></a>
            <a href="/user/surveys.php" class="sidebar-nav-item active"><span class="sidebar-nav-icon"><i class="fas fa-clipboard-list"></i></span><span class="sidebar-nav-text">Surveys</span></a>
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
                <h1>Surveys</h1>
                <p>Share your opinion and earn rewards</p>
            </div>
            <div class="balance-display">
                <span class="balance-display-amount" id="balanceDisplay"><?= formatCurrency($totalBalance) ?></span>
            </div>
        </div>

        <div id="surveysListView">
            <div style="display: grid; grid-template-columns: 1fr 300px; gap: var(--space-6);">
                <div>
                    <div class="dashboard-panel-header">
                        <div class="dashboard-panel-title">Available Surveys</div>
                    </div>
                    <div class="surveys-grid">
                        <?php if (count($surveys) > 0): ?>
                        <?php foreach ($surveys as $s): ?>
                        <div class="survey-card">
                            <div class="survey-card-header">
                                <div class="survey-card-icon"><i class="fas fa-poll"></i></div>
                                <div class="survey-card-title"><?= sanitize($s['title']) ?></div>
                            </div>
                            <div class="survey-card-desc"><?= sanitize(mb_substr($s['description'] ?? 'Complete this survey to earn rewards', 0, 120)) ?></div>
                            <div class="survey-card-meta">
                                <span class="survey-card-reward">+<?= formatCurrency($s['reward_amount']) ?></span>
                                <span class="survey-card-time"><i class="far fa-clock"></i> ~<?= $s['estimated_time'] ?> min</span>
                            </div>
                            <button class="btn btn-sm btn-primary btn-block mt-3" onclick="startSurvey(<?= $s['id'] ?>)">Start Survey</button>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <div style="grid-column: 1/-1; text-align: center; padding: var(--space-12); color: var(--text-tertiary);">
                            <i class="fas fa-poll" style="font-size: var(--text-5xl); opacity: 0.3; margin-bottom: var(--space-4); display: block;"></i>
                            <p>No surveys available right now.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <div class="dashboard-panel">
                        <div class="dashboard-panel-header">
                            <div class="dashboard-panel-title">Survey History</div>
                        </div>
                        <?php if (count($surveyHistory) > 0): ?>
                        <?php foreach ($surveyHistory as $sh): ?>
                        <div class="history-item">
                            <span class="history-title"><?= sanitize($sh['survey_title']) ?></span>
                            <span class="badge badge-<?= $sh['completed'] ? 'success' : 'warning' ?> badge-sm"><?= $sh['completed'] ? 'Done' : 'Incomplete' ?></span>
                            <span class="history-date"><?= timeAgo($sh['created_at']) ?></span>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <div style="padding: var(--space-6); text-align: center; color: var(--text-tertiary);">
                            <i class="fas fa-inbox" style="font-size: var(--text-3xl); opacity: 0.3; margin-bottom: var(--space-2); display: block;"></i>
                            <p style="font-size: var(--text-sm);">No surveys taken yet</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="survey-container" id="surveyContainer">
            <div class="dashboard-panel-header">
                <div class="dashboard-panel-title" id="surveyTitle">Survey</div>
            </div>
            <div id="surveyQuestions"></div>
            <div class="survey-nav">
                <button class="btn btn-outline-light" id="surveyPrevBtn" onclick="surveyPrev()" style="display: none;"><i class="fas fa-arrow-left"></i> Previous</button>
                <div style="flex:1;"></div>
                <button class="btn btn-primary" id="surveyNextBtn" onclick="surveyNext()">Next <i class="fas fa-arrow-right"></i></button>
            </div>
            <button class="btn btn-success btn-block mt-4" id="surveySubmitBtn" style="display: none;" onclick="submitSurvey()">Complete Survey & Earn Reward</button>
        </div>
        <div class="bottom-nav-spacer"></div>
    </main>
</div>

<script>
let surveyQuestions = [];
let surveyCurrentIndex = 0;
let surveyAnswers = {};
let surveyId = null;

function startSurvey(id) {
    fetch('/user/ajax/survey_start.php?id=' + id, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            surveyId = id;
            surveyQuestions = data.questions;
            surveyCurrentIndex = 0;
            surveyAnswers = {};
            document.getElementById('surveysListView').style.display = 'none';
            document.getElementById('surveyContainer').style.display = 'block';
            document.getElementById('surveyTitle').textContent = data.title || 'Survey';
            renderSurveyQuestion();
        } else {
            alert(data.error || 'Failed to load survey');
        }
    });
}

function renderSurveyQuestion() {
    const q = surveyQuestions[surveyCurrentIndex];
    if (!q) return;

    const total = surveyQuestions.length;
    document.getElementById('surveyPrevBtn').style.display = surveyCurrentIndex === 0 ? 'none' : '';
    document.getElementById('surveyNextBtn').style.display = surveyCurrentIndex < total - 1 ? '' : 'none';
    document.getElementById('surveySubmitBtn').style.display = surveyCurrentIndex === total - 1 ? '' : 'none';

    let html = `<div class="survey-question">
        <div style="font-size: var(--text-xs); color: var(--text-tertiary); margin-bottom: var(--space-2);">Question ${surveyCurrentIndex + 1} of ${total}</div>
        <div class="survey-question-text">${q.question || q.text}</div>`;

    if (q.options && Array.isArray(q.options)) {
        q.options.forEach((opt, i) => {
            const selected = surveyAnswers[q.id || q._id || surveyCurrentIndex] === opt;
            html += `<div class="survey-option ${selected ? 'selected' : ''}" onclick="selectSurveyOption(${i}, '${opt.replace(/'/g, "\\'")}')">
                <input type="${q.type === 'multiple' ? 'checkbox' : 'radio'}" name="survey_q_${surveyCurrentIndex}" value="${opt}" ${selected ? 'checked' : ''} style="accent-color: var(--primary);">
                <span>${opt}</span>
            </div>`;
        });
    }

    html += '</div>';
    document.getElementById('surveyQuestions').innerHTML = html;
}

function selectSurveyOption(index, value) {
    const q = surveyQuestions[surveyCurrentIndex];
    const key = q.id || q._id || surveyCurrentIndex;

    if (q.type === 'multiple') {
        if (!surveyAnswers[key]) surveyAnswers[key] = [];
        const idx = surveyAnswers[key].indexOf(value);
        if (idx >= 0) surveyAnswers[key].splice(idx, 1);
        else surveyAnswers[key].push(value);
    } else {
        surveyAnswers[key] = value;
        document.querySelectorAll('.survey-option').forEach(el => el.classList.remove('selected'));
        document.querySelectorAll('.survey-option')[index]?.classList.add('selected');
    }
}

function surveyNext() {
    const q = surveyQuestions[surveyCurrentIndex];
    const key = q.id || q._id || surveyCurrentIndex;
    if (!surveyAnswers[key]) { alert('Please select an answer'); return; }
    surveyCurrentIndex++;
    renderSurveyQuestion();
}

function surveyPrev() {
    if (surveyCurrentIndex > 0) { surveyCurrentIndex--; renderSurveyQuestion(); }
}

function submitSurvey() {
    const q = surveyQuestions[surveyCurrentIndex];
    const key = q.id || q._id || surveyCurrentIndex;
    if (!surveyAnswers[key]) { alert('Please select an answer'); return; }

    const formData = new FormData();
    formData.append('survey_id', surveyId);
    formData.append('answers', JSON.stringify(surveyAnswers));
    formData.append('csrf_token', '<?= csrf_token() ?>');

    document.getElementById('surveySubmitBtn').disabled = true;
    document.getElementById('surveySubmitBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

    fetch('/user/ajax/survey_submit.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('surveyQuestions').innerHTML = `
                <div style="text-align: center; padding: var(--space-8);">
                    <div style="font-size: var(--text-5xl); margin-bottom: var(--space-4);">🎉</div>
                    <div style="font-family: var(--font-display); font-size: var(--text-2xl); font-weight: var(--weight-bold); color: var(--success);">+${data.formatted_amount || '0.00'} earned!</div>
                    <div style="color: var(--text-secondary); margin-top: var(--space-2);">Thank you for completing the survey!</div>
                    <button class="btn btn-primary mt-6" onclick="backToSurveys()">Back to Surveys</button>
                </div>
            `;
            document.getElementById('surveySubmitBtn').style.display = 'none';
            document.getElementById('surveyPrevBtn').style.display = 'none';
            if (data.new_balance_formatted) {
                document.getElementById('balanceDisplay').textContent = data.new_balance_formatted;
            }
        } else {
            alert(data.error || 'Submission failed');
            document.getElementById('surveySubmitBtn').disabled = false;
            document.getElementById('surveySubmitBtn').innerHTML = 'Complete Survey & Earn Reward';
        }
    });
}

function backToSurveys() {
    document.getElementById('surveyContainer').style.display = 'none';
    document.getElementById('surveysListView').style.display = 'block';
    surveyId = null;
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
