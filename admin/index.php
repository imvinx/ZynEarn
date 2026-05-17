<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
session_name(SESSION_NAME);
session_set_cookie_params(['lifetime' => SESSION_LIFETIME, 'path' => COOKIE_PATH, 'domain' => COOKIE_DOMAIN, 'secure' => COOKIE_SECURE, 'httponly' => COOKIE_HTTP_ONLY, 'samesite' => COOKIE_SAME_SITE]);
session_start();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username && $password) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND role = 'admin' LIMIT 1");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        if ($user && verifyPassword($password, $user['password'])) {
            if ($user['status'] !== 'active') {
                $error = 'Your account is not active.';
            } else {
                createSession($user['id']);
                logAdminAction($user['id'], 'Admin Login', ['ip' => getClientIP()]);
                header('Location: /admin/dashboard.php');
                exit;
            }
        } else {
            Security::logAttempt(getClientIP(), $username, false);
            $error = 'Invalid credentials.';
        }
    } else {
        $error = 'Please enter username and password.';
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login - <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
<link rel="stylesheet" href="/admin/assets/admin.css">
<style>
.login-page{min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--dark-bg);position:relative;overflow:hidden}
.login-page::before{content:'';position:absolute;width:700px;height:700px;border-radius:50%;background:radial-gradient(circle,rgba(108,92,231,.15),transparent 70%);top:-250px;right:-200px;pointer-events:none}
.login-page::after{content:'';position:absolute;width:500px;height:500px;border-radius:50%;background:radial-gradient(circle,rgba(0,206,201,.1),transparent 70%);bottom:-200px;left:-150px;pointer-events:none}
.login-box{position:relative;z-index:1;width:100%;max-width:420px;padding:48px 40px;background:var(--dark-surface);border:1px solid var(--dark-border);border-radius:var(--radius-lg);box-shadow:0 25px 80px rgba(0,0,0,.5)}
.login-box .login-logo{text-align:center;margin-bottom:36px}
.login-box .login-logo i{font-size:3rem;background:var(--gradient-main);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;margin-bottom:12px}
.login-box .login-logo h2{font-family:var(--font-display);font-size:1.8rem;font-weight:700}
.login-box .login-logo p{color:var(--text-secondary);font-size:.82rem;margin-top:6px}
.login-box .login-shield{text-align:center;margin-bottom:24px;color:var(--text-muted);font-size:.78rem}
.login-box .login-shield i{color:var(--primary);margin-right:6px}
.form-group{margin-bottom:20px}
.form-group label{display:block;font-size:.8rem;font-weight:500;margin-bottom:6px;color:var(--text-secondary)}
.form-group .input-wrap{display:flex;align-items:center;background:var(--dark-bg);border:1px solid var(--dark-border);border-radius:var(--radius-sm);transition:all var(--transition);padding:0 14px}
.form-group .input-wrap:focus-within{border-color:var(--primary);box-shadow:0 0 0 3px rgba(108,92,231,.12)}
.form-group .input-wrap i{color:var(--text-muted);font-size:.9rem}
.form-group .input-wrap input{width:100%;padding:12px 12px;background:none;border:none;color:var(--text-primary);font-size:.85rem}
.form-group .input-wrap input::placeholder{color:var(--text-muted)}
.btn{width:100%;padding:14px;border-radius:var(--radius-sm);font-size:.9rem;font-weight:600;background:var(--gradient-main);color:#fff;border:none;cursor:pointer;transition:all var(--transition);display:flex;align-items:center;justify-content:center;gap:8px}
.btn:hover{transform:translateY(-2px);box-shadow:0 8px 30px rgba(108,92,231,.4)}
.btn:disabled{opacity:.6;cursor:not-allowed;transform:none}
.error-msg{background:rgba(255,107,107,.12);border:1px solid rgba(255,107,107,.2);color:#ff6b6b;padding:12px 16px;border-radius:var(--radius-sm);font-size:.82rem;margin-bottom:20px;display:flex;align-items:center;gap:8px}
.login-footer{text-align:center;margin-top:24px;font-size:.8rem;color:var(--text-muted)}
.login-footer a{color:var(--primary);text-decoration:none}
.login-footer a:hover{text-decoration:underline}
.password-toggle{background:none;border:none;color:var(--text-muted);cursor:pointer;padding:4px;font-size:.85rem}
.password-toggle:hover{color:var(--text-primary)}
</style>
</head>
<body>
<div class="login-page">
  <div class="login-box">
    <div class="login-logo">
      <i class="fas fa-shield-halved"></i>
      <h2><?= APP_NAME ?> Admin</h2>
      <p>Sign in to manage your platform</p>
    </div>
    <div class="login-shield">
      <i class="fas fa-lock"></i> Authorized personnel only
    </div>
    <?php if ($error): ?>
    <div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?= sanitize($error) ?></div>
    <?php endif; ?>
    <form method="POST" action="" id="loginForm">
      <div class="form-group">
        <label>Username or Email</label>
        <div class="input-wrap">
          <i class="fas fa-user"></i>
          <input type="text" name="username" placeholder="Enter your username" required autocomplete="username" autofocus>
        </div>
      </div>
      <div class="form-group">
        <label>Password</label>
        <div class="input-wrap">
          <i class="fas fa-lock"></i>
          <input type="password" name="password" id="adminPassword" placeholder="Enter your password" required autocomplete="current-password">
          <button type="button" class="password-toggle" onclick="var p=document.getElementById('adminPassword');p.type=p.type==='password'?'text':'password';this.querySelector('i').className='fas fa-'+(p.type==='password'?'eye':'eye-slash')"><i class="fas fa-eye"></i></button>
        </div>
      </div>
      <button type="submit" class="btn" id="loginBtn"><i class="fas fa-arrow-right"></i> Sign In</button>
    </form>
    <div class="login-footer">
      <a href="/">← Back to <?= APP_NAME ?></a>
    </div>
  </div>
</div>
<script>
document.getElementById('loginForm').addEventListener('submit', function(e) {
  var btn = document.getElementById('loginBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in...';
});
</script>
</body>
</html>
