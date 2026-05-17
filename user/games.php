<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAuth();
$user = getCurrentUser();
$pageTitle = 'Mini Games';
$userId = $user['id'];
$db = getDB();

$stmt = $db->prepare("SELECT id, game_type, score, reward, played_at FROM game_scores WHERE user_id = ? ORDER BY played_at DESC LIMIT 20");
$stmt->execute([$userId]);
$gameHistory = $stmt->fetchAll();

$stmt = $db->prepare("SELECT game_type, MAX(score) as high_score FROM game_scores WHERE user_id = ? GROUP BY game_type");
$stmt->execute([$userId]);
$highScores = [];
while ($row = $stmt->fetch()) {
    $highScores[$row['game_type']] = $row['high_score'];
}

$games = [
    'memory' => ['name' => 'Memory Match', 'icon' => 'fa-brain', 'color' => 'primary', 'reward' => 0.02, 'desc' => 'Match pairs of cards as fast as you can'],
    'clickspeed' => ['name' => 'Click Speed', 'icon' => 'fa-mouse-pointer', 'color' => 'secondary', 'reward' => 0.01, 'desc' => 'Click as many times as you can in 10 seconds'],
    'guess' => ['name' => 'Number Guessing', 'icon' => 'fa-question-circle', 'color' => 'accent', 'reward' => 0.03, 'desc' => 'Guess the number between 1 and 100'],
    'reaction' => ['name' => 'Reaction Time', 'icon' => 'fa-bolt', 'color' => 'success', 'reward' => 0.015, 'desc' => 'Click when the screen changes color'],
];
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<style>
.games-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: var(--space-5); margin-bottom: var(--space-6); }
.game-card { background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-lg); padding: var(--space-6); transition: var(--transition); cursor: pointer; }
.game-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); border-color: var(--border-light); }
.game-card-header { display: flex; align-items: center; gap: var(--space-4); margin-bottom: var(--space-4); }
.game-card-icon { width: 56px; height: 56px; border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; font-size: var(--text-2xl); flex-shrink: 0; }
.game-card-title { font-family: var(--font-display); font-size: var(--text-lg); font-weight: var(--weight-semibold); color: var(--text-primary); }
.game-card-desc { font-size: var(--text-sm); color: var(--text-secondary); margin-bottom: var(--space-4); min-height: 40px; }
.game-card-stats { display: flex; align-items: center; justify-content: space-between; padding-top: var(--space-4); border-top: 1px solid var(--border-primary); }
.game-card-highscore { font-size: var(--text-sm); color: var(--text-tertiary); }
.game-card-highscore span { color: var(--warning); font-weight: var(--weight-bold); }
.game-card-reward { font-size: var(--text-sm); font-weight: var(--weight-semibold); color: var(--success); }
.game-interface { background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-lg); padding: var(--space-6); display: none; margin-bottom: var(--space-6); }
.game-interface.active { display: block; }
.game-interface-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-4); }
.game-interface-title { font-family: var(--font-display); font-size: var(--text-xl); font-weight: var(--weight-bold); color: var(--text-primary); }
.game-area { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 300px; text-align: center; padding: var(--space-6); }
.game-btn-start { font-size: var(--text-lg); padding: var(--space-4) var(--space-8); }
.game-btn-start:disabled { opacity: 0.5; cursor: not-allowed; }
.game-timer { font-family: var(--font-mono); font-size: var(--text-3xl); font-weight: var(--weight-bold); color: var(--primary); margin-bottom: var(--space-4); }
.game-score { font-family: var(--font-display); font-size: var(--text-4xl); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-4); }
.game-result { font-size: var(--text-lg); color: var(--success); margin-bottom: var(--space-4); display: none; }
.game-result .reward-amount { font-weight: var(--weight-bold); font-size: var(--text-2xl); }
.memory-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: var(--space-3); max-width: 400px; margin: 0 auto; }
.memory-card { aspect-ratio: 1; background: var(--gradient-primary); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: var(--text-2xl); color: white; cursor: pointer; transition: var(--transition); user-select: none; }
.memory-card:hover { transform: scale(1.05); }
.memory-card.flipped { background: var(--bg-card-hover); color: var(--text-primary); border: 2px solid var(--primary); }
.memory-card.matched { background: var(--success); color: white; cursor: default; opacity: 0.7; }
.click-area { width: 100%; max-width: 400px; height: 250px; background: var(--bg-card-hover); border: 2px dashed var(--border-light); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: var(--transition); user-select: none; }
.click-area:hover { border-color: var(--primary); }
.click-area.active { background: rgba(0,184,148,0.1); border-color: var(--success); }
.click-area .click-text { font-size: var(--text-2xl); font-weight: var(--weight-bold); color: var(--text-tertiary); }
.click-count { font-size: var(--text-5xl); font-weight: var(--weight-bold); color: var(--primary); }
.guess-input { max-width: 200px; margin: 0 auto; text-align: center; font-size: var(--text-2xl); padding: var(--space-3); }
.guess-hint { font-size: var(--text-lg); font-weight: var(--weight-semibold); margin-bottom: var(--space-3); }
.guess-hint.high { color: var(--danger); }
.guess-hint.low { color: var(--info); }
.guess-hint.correct { color: var(--success); }
.reaction-area { width: 100%; max-width: 500px; height: 280px; border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background 0.1s; user-select: none; }
.reaction-area.waiting { background: var(--danger); }
.reaction-area.ready { background: var(--success); }
.reaction-area.too-early { background: var(--warning); }
.reaction-text { font-size: var(--text-2xl); font-weight: var(--weight-bold); color: white; }
</style>

<div class="dashboard-wrapper">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-section">
            <div class="sidebar-section-title">Main Menu</div>
            <a href="/user/dashboard.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-home"></i></span><span class="sidebar-nav-text">Dashboard</span></a>
            <a href="/user/wallet.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-wallet"></i></span><span class="sidebar-nav-text">Wallet</span></a>
            <a href="/user/earnings.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-chart-line"></i></span><span class="sidebar-nav-text">Earnings</span></a>
            <a href="/user/offers.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-th"></i></span><span class="sidebar-nav-text">Offers</span></a>
            <a href="/user/tasks.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-check-circle"></i></span><span class="sidebar-nav-text">Tasks</span></a>
            <a href="/user/games.php" class="sidebar-nav-item active"><span class="sidebar-nav-icon"><i class="fas fa-gamepad"></i></span><span class="sidebar-nav-text">Games</span></a>
            <a href="/user/referrals.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-users"></i></span><span class="sidebar-nav-text">Referrals</span></a>
            <a href="/user/withdraw.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-cash-register"></i></span><span class="sidebar-nav-text">Withdraw</span></a>
            <a href="/user/deposit.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-plus-circle"></i></span><span class="sidebar-nav-text">Deposit</span></a>
        </div>
        <div class="sidebar-section">
            <div class="sidebar-section-title">Account</div>
            <a href="/user/profile.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-user"></i></span><span class="sidebar-nav-text">Profile</span></a>
            <a href="/user/settings.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-cog"></i></span><span class="sidebar-nav-text">Settings</span></a>
            <a href="/user/support.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-headset"></i></span><span class="sidebar-nav-text">Support</span></a>
            <a href="/auth/logout.php" class="sidebar-nav-item logout-item"><span class="sidebar-nav-icon"><i class="fas fa-sign-out-alt"></i></span><span class="sidebar-nav-text">Logout</span></a>
        </div>
    </aside>
    <main class="main-content">
        <div class="dashboard-top-header">
            <div class="dashboard-greeting">
                <h1>Mini Games</h1>
                <p>Play games and earn rewards</p>
            </div>
            <div class="dashboard-actions">
                <div class="balance-display" onclick="toggleBalanceVisibility()">
                    <span class="balance-display-icon"><i class="fas fa-eye" id="eyeIcon"></i></span>
                    <span class="balance-display-amount" id="headerBalance"><?= formatCurrency(getUserBalance($userId)) ?></span>
                </div>
            </div>
        </div>

        <div class="games-grid">
            <?php foreach ($games as $key => $game): ?>
            <div class="game-card" onclick="openGame('<?= $key ?>')">
                <div class="game-card-header">
                    <div class="game-card-icon" style="background: rgba(var(--<?= $game['color'] ?>-rgb,108,92,231),0.15); color: var(--<?= $game['color'] ?>);">
                        <i class="fas <?= $game['icon'] ?>"></i>
                    </div>
                    <div class="game-card-title"><?= $game['name'] ?></div>
                </div>
                <div class="game-card-desc"><?= $game['desc'] ?></div>
                <div class="game-card-stats">
                    <div class="game-card-highscore">High: <span><?= isset($highScores[$key]) ? $highScores[$key] : 0 ?></span></div>
                    <div class="game-card-reward"><i class="fas fa-coins"></i> +<?= formatCurrency($game['reward']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div id="memoryGame" class="game-interface">
            <div class="game-interface-header">
                <div class="game-interface-title">Memory Match</div>
                <button class="btn btn-sm btn-ghost" onclick="closeGame('memory')"><i class="fas fa-times"></i></button>
            </div>
            <div class="game-area">
                <div class="game-timer" id="memoryTimer">00</div>
                <div class="game-score" id="memoryScore">Moves: 0</div>
                <div class="memory-grid" id="memoryGrid"></div>
                <div class="game-result" id="memoryResult"></div>
                <button class="btn btn-primary game-btn-start" id="memoryStartBtn" onclick="startMemoryGame()">Start Game</button>
            </div>
        </div>

        <div id="clickspeedGame" class="game-interface">
            <div class="game-interface-header">
                <div class="game-interface-title">Click Speed</div>
                <button class="btn btn-sm btn-ghost" onclick="closeGame('clickspeed')"><i class="fas fa-times"></i></button>
            </div>
            <div class="game-area">
                <div class="game-timer" id="clickTimer">10</div>
                <div class="click-count" id="clickCount">0</div>
                <div class="click-area" id="clickArea" onclick="handleClick()">
                    <span class="click-text" id="clickText">Click here!</span>
                </div>
                <div class="game-result" id="clickResult"></div>
                <button class="btn btn-primary game-btn-start" id="clickStartBtn" onclick="startClickGame()">Start Game</button>
            </div>
        </div>

        <div id="guessGame" class="game-interface">
            <div class="game-interface-header">
                <div class="game-interface-title">Number Guessing</div>
                <button class="btn btn-sm btn-ghost" onclick="closeGame('guess')"><i class="fas fa-times"></i></button>
            </div>
            <div class="game-area">
                <div class="guess-hint" id="guessHint">Guess a number between 1 and 100</div>
                <input type="number" class="form-input guess-input" id="guessInput" min="1" max="100" placeholder="?" disabled>
                <div style="margin-top: var(--space-4); display: flex; gap: var(--space-3);">
                    <button class="btn btn-primary" id="guessSubmitBtn" onclick="makeGuess()" disabled>Guess</button>
                    <button class="btn btn-outline-light" id="guessResetBtn" onclick="resetGuessGame()" style="display:none;">Play Again</button>
                </div>
                <div class="game-score" id="guessAttempts">Attempts: 0</div>
                <div class="game-result" id="guessResult"></div>
                <button class="btn btn-primary game-btn-start" id="guessStartBtn" onclick="startGuessGame()">Start Game</button>
            </div>
        </div>

        <div id="reactionGame" class="game-interface">
            <div class="game-interface-header">
                <div class="game-interface-title">Reaction Time</div>
                <button class="btn btn-sm btn-ghost" onclick="closeGame('reaction')"><i class="fas fa-times"></i></button>
            </div>
            <div class="game-area">
                <div class="reaction-area waiting" id="reactionArea" onclick="handleReactionClick()">
                    <span class="reaction-text" id="reactionText">Wait for green...</span>
                </div>
                <div class="game-timer" id="reactionTime" style="display:none;">0 ms</div>
                <div class="game-result" id="reactionResult"></div>
                <button class="btn btn-primary game-btn-start" id="reactionStartBtn" onclick="startReactionGame()">Start Game</button>
            </div>
        </div>

        <div class="dashboard-panel">
            <div class="dashboard-panel-header">
                <div class="dashboard-panel-title"><i class="fas fa-history"></i> Game History</div>
            </div>
            <div class="table-container">
                <table class="table table-sm">
                    <thead>
                        <tr><th>Game</th><th>Score</th><th>Reward</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                        <?php if (count($gameHistory) > 0): ?>
                        <?php foreach ($gameHistory as $g): ?>
                        <tr>
                            <td><span class="badge badge-primary badge-sm"><?= ucfirst($g['game_type']) ?></span></td>
                            <td><span class="text-mono" style="color: var(--text-primary); font-weight: var(--weight-semibold);"><?= $g['score'] ?></span></td>
                            <td><span style="color: var(--success); font-weight: var(--weight-semibold);">+<?= formatCurrency($g['reward']) ?></span></td>
                            <td><span class="text-mono" style="font-size: var(--text-xs); color: var(--text-tertiary);"><?= date('M d, H:i', strtotime($g['played_at'])) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr><td colspan="4"><div class="table-empty"><div class="table-empty-icon"><i class="fas fa-gamepad"></i></div><p>No games played yet</p></div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="bottom-nav-spacer"></div>
    </main>
</div>

<script>
let balanceVisible = true;
function toggleBalanceVisibility() {
    balanceVisible = !balanceVisible;
    const el = document.getElementById('headerBalance');
    const icon = document.getElementById('eyeIcon');
    if (balanceVisible) { el.textContent = '<?= formatCurrency(getUserBalance($userId)) ?>'; icon.className = 'fas fa-eye'; }
    else { el.textContent = '••••••'; icon.className = 'fas fa-eye-slash'; }
}

function openGame(type) {
    document.querySelectorAll('.game-interface').forEach(g => g.classList.remove('active'));
    document.getElementById(type + 'Game').classList.add('active');
    document.getElementById(type + 'Game').scrollIntoView({ behavior: 'smooth' });
}
function closeGame(type) {
    document.getElementById(type + 'Game').classList.remove('active');
}

const GAME_REWARDS = { memory: 0.02, clickspeed: 0.01, guess: 0.03, reaction: 0.015 };

function submitScore(gameType, score) {
    const formData = new FormData();
    formData.append('game_type', gameType);
    formData.append('score', score);
    formData.append('csrf_token', '<?= csrf_token() ?>');
    return fetch('/user/ajax/submit_game_score.php', {
        method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData
    }).then(r => r.json());
}

// Memory Match
let memoryCards = [], memoryFlipped = [], memoryMatched = [], memoryMoves = 0, memoryLocked = false, memoryStarted = false;
const MEMORY_ICONS = ['fa-star', 'fa-heart', 'fa-bolt', 'fa-moon', 'fa-sun', 'fa-fire', 'fa-diamond', 'fa-gem'];
function startMemoryGame() {
    if (memoryStarted) return;
    memoryStarted = true;
    memoryMoves = 0; memoryMatched = []; memoryFlipped = [];
    document.getElementById('memoryScore').textContent = 'Moves: 0';
    document.getElementById('memoryTimer').textContent = '00';
    document.getElementById('memoryResult').style.display = 'none';
    document.getElementById('memoryStartBtn').style.display = 'none';
    const grid = document.getElementById('memoryGrid');
    let icons = [...MEMORY_ICONS, ...MEMORY_ICONS];
    icons.sort(() => Math.random() - 0.5);
    memoryCards = icons;
    grid.innerHTML = icons.map((icon, i) => `<div class="memory-card" data-index="${i}" onclick="flipCard(${i})"><i class="fas ${icon}"></i></div>`).join('');
}
function flipCard(index) {
    if (memoryLocked || memoryFlipped.includes(index) || memoryMatched.includes(index)) return;
    const cards = document.querySelectorAll('.memory-card');
    cards[index].classList.add('flipped');
    memoryFlipped.push(index);
    if (memoryFlipped.length === 2) {
        memoryLocked = true;
        memoryMoves++;
        document.getElementById('memoryScore').textContent = 'Moves: ' + memoryMoves;
        const [i1, i2] = memoryFlipped;
        if (memoryCards[i1] === memoryCards[i2]) {
            memoryMatched.push(i1, i2);
            cards[i1].classList.add('matched');
            cards[i2].classList.add('matched');
            memoryFlipped = [];
            memoryLocked = false;
            if (memoryMatched.length === memoryCards.length) {
                endMemoryGame();
            }
        } else {
            setTimeout(() => {
                cards[i1].classList.remove('flipped');
                cards[i2].classList.remove('flipped');
                memoryFlipped = [];
                memoryLocked = false;
            }, 800);
        }
    }
}
function endMemoryGame() {
    const timeBonus = Math.max(0, 30 - memoryMoves);
    const score = Math.max(1, timeBonus);
    const result = document.getElementById('memoryResult');
    result.style.display = 'block';
    submitScore('memory', score).then(data => {
        if (data.success) {
            result.innerHTML = '<span class="reward-amount">+<?= APP_CURRENCY_SYMBOL ?>' + data.reward.toFixed(4) + '</span> earned!';
        } else {
            result.innerHTML = 'Score: ' + score;
        }
    });
    document.getElementById('memoryStartBtn').style.display = 'inline-flex';
    memoryStarted = false;
}

// Click Speed
let clickCount = 0, clickTimer = 10, clickInterval = null, clickGameActive = false;
function startClickGame() {
    if (clickGameActive) return;
    clickGameActive = true;
    clickCount = 0; clickTimer = 10;
    document.getElementById('clickCount').textContent = '0';
    document.getElementById('clickTimer').textContent = '10';
    document.getElementById('clickResult').style.display = 'none';
    document.getElementById('clickStartBtn').style.display = 'none';
    document.getElementById('clickText').textContent = 'Click as fast as you can!';
    document.getElementById('clickArea').classList.add('active');
    clickInterval = setInterval(() => {
        clickTimer--;
        document.getElementById('clickTimer').textContent = clickTimer;
        if (clickTimer <= 0) {
            endClickGame();
        }
    }, 1000);
}
function handleClick() {
    if (!clickGameActive) return;
    clickCount++;
    document.getElementById('clickCount').textContent = clickCount;
}
function endClickGame() {
    clickGameActive = false;
    clearInterval(clickInterval);
    document.getElementById('clickArea').classList.remove('active');
    document.getElementById('clickText').textContent = 'Time\'s up!';
    const result = document.getElementById('clickResult');
    result.style.display = 'block';
    submitScore('clickspeed', clickCount).then(data => {
        if (data.success) {
            result.innerHTML = '<span class="reward-amount">+<?= APP_CURRENCY_SYMBOL ?>' + data.reward.toFixed(4) + '</span> earned! ' + clickCount + ' clicks';
        } else {
            result.innerHTML = clickCount + ' clicks';
        }
    });
    document.getElementById('clickStartBtn').style.display = 'inline-flex';
}

// Number Guessing
let guessNumber = 0, guessAttempts = 0, guessGameActive = false, guessMaxAttempts = 7;
function startGuessGame() {
    if (guessGameActive) return;
    guessGameActive = true;
    guessNumber = Math.floor(Math.random() * 100) + 1;
    guessAttempts = 0;
    document.getElementById('guessHint').textContent = 'Guess a number between 1 and 100';
    document.getElementById('guessHint').className = 'guess-hint';
    document.getElementById('guessAttempts').textContent = 'Attempts: 0';
    document.getElementById('guessResult').style.display = 'none';
    document.getElementById('guessStartBtn').style.display = 'none';
    document.getElementById('guessInput').disabled = false;
    document.getElementById('guessSubmitBtn').disabled = false;
    document.getElementById('guessInput').value = '';
    document.getElementById('guessInput').focus();
    document.getElementById('guessResetBtn').style.display = 'none';
}
function makeGuess() {
    if (!guessGameActive) return;
    const input = document.getElementById('guessInput');
    const val = parseInt(input.value);
    if (isNaN(val) || val < 1 || val > 100) return;
    guessAttempts++;
    document.getElementById('guessAttempts').textContent = 'Attempts: ' + guessAttempts;
    const hint = document.getElementById('guessHint');
    if (val === guessNumber) {
        hint.textContent = 'Correct! The number was ' + guessNumber + '!';
        hint.className = 'guess-hint correct';
        endGuessGame(true);
    } else if (val < guessNumber) {
        hint.textContent = 'Too low! Guess higher.';
        hint.className = 'guess-hint low';
    } else {
        hint.textContent = 'Too high! Guess lower.';
        hint.className = 'guess-hint high';
    }
    input.value = '';
    input.focus();
    if (guessAttempts >= guessMaxAttempts) {
        hint.textContent = 'Out of attempts! The number was ' + guessNumber + '.';
        hint.className = 'guess-hint';
        endGuessGame(false);
    }
}
function endGuessGame(won) {
    guessGameActive = false;
    document.getElementById('guessInput').disabled = true;
    document.getElementById('guessSubmitBtn').disabled = true;
    document.getElementById('guessResetBtn').style.display = 'inline-flex';
    const result = document.getElementById('guessResult');
    result.style.display = 'block';
    if (won) {
        const score = Math.max(1, guessMaxAttempts - guessAttempts + 1);
        submitScore('guess', score).then(data => {
            if (data.success) {
                result.innerHTML = '<span class="reward-amount">+<?= APP_CURRENCY_SYMBOL ?>' + data.reward.toFixed(4) + '</span> earned!';
            } else {
                result.innerHTML = 'You won!';
            }
        });
    } else {
        result.innerHTML = 'Better luck next time!';
    }
    document.getElementById('guessStartBtn').style.display = 'inline-flex';
}
function resetGuessGame() {
    document.getElementById('guessStartBtn').click();
}

// Reaction Time
let reactionState = 'idle', reactionTimeout = null, reactionStartTime = 0, reactionGameActive = false;
function startReactionGame() {
    if (reactionGameActive) return;
    reactionGameActive = true;
    document.getElementById('reactionTime').style.display = 'none';
    document.getElementById('reactionResult').style.display = 'none';
    document.getElementById('reactionStartBtn').style.display = 'none';
    const area = document.getElementById('reactionArea');
    area.className = 'reaction-area waiting';
    document.getElementById('reactionText').textContent = 'Wait for green...';
    reactionState = 'waiting';
    const delay = 1000 + Math.random() * 3000;
    reactionTimeout = setTimeout(() => {
        if (reactionState === 'waiting') {
            reactionState = 'ready';
            area.className = 'reaction-area ready';
            document.getElementById('reactionText').textContent = 'CLICK NOW!';
            reactionStartTime = Date.now();
        }
    }, delay);
}
function handleReactionClick() {
    const area = document.getElementById('reactionArea');
    if (reactionState === 'waiting') {
        clearTimeout(reactionTimeout);
        reactionState = 'tooeary';
        area.className = 'reaction-area too-early';
        document.getElementById('reactionText').textContent = 'Too early! Click to retry.';
        setTimeout(() => {
            if (reactionState === 'tooeary') {
                reactionState = 'idle';
                document.getElementById('reactionStartBtn').style.display = 'inline-flex';
            }
        }, 1500);
    } else if (reactionState === 'ready') {
        const reactionTime = Date.now() - reactionStartTime;
        reactionState = 'done';
        document.getElementById('reactionTime').style.display = 'block';
        document.getElementById('reactionTime').textContent = reactionTime + ' ms';
        area.className = 'reaction-area ready';
        document.getElementById('reactionText').textContent = 'Great!';
        const result = document.getElementById('reactionResult');
        result.style.display = 'block';
        const score = Math.max(1, Math.round(500 / reactionTime * 100));
        submitScore('reaction', score).then(data => {
            if (data.success) {
                result.innerHTML = '<span class="reward-amount">+<?= APP_CURRENCY_SYMBOL ?>' + data.reward.toFixed(4) + '</span> earned!';
            } else {
                result.innerHTML = reactionTime + ' ms';
            }
        });
        document.getElementById('reactionStartBtn').style.display = 'inline-flex';
        reactionGameActive = false;
    } else if (reactionState === 'done' || reactionState === 'idle') {
    }
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
