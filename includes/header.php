<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/functions.php';

$csrfToken = $_SESSION['csrf_token'] ?? '';
if (empty($csrfToken)) {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrfToken;
}

$pageTitle = $pageTitle ?? 'ZynEarn - Earn Real Money';
$pageDescription = $pageDescription ?? 'Turn your time into real money. Complete tasks, surveys, offers and more.';
$theme = $_SESSION['theme'] ?? 'dark';
$bodyClass = $bodyClass ?? '';

$isLoggedIn = isset($_SESSION['user_id']);

$unreadCount = 0;
if ($isLoggedIn && isset($db)) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $unreadCount = (int)$stmt->fetchColumn();
        $_SESSION['unread_notifications'] = $unreadCount;
    } catch (Exception $e) {
        $unreadCount = $_SESSION['unread_notifications'] ?? 0;
    }
}
?><!DOCTYPE html>
<html lang="en-US" dir="ltr" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <meta name="theme-color" content="#0a0a1a">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken); ?>">
    <meta name="csrf-param" content="csrf_token">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="ZynEarn">
    <meta name="application-name" content="ZynEarn">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="robots" content="index, follow">
    <meta name="googlebot" content="index, follow">
    <link rel="manifest" href="/pwa/manifest.json">
    <link rel="apple-touch-icon" href="/assets/images/icon-192.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/images/favicon-16.png">
    <link rel="mask-icon" href="/assets/images/safari-pinned-tab.svg" color="#6C5CE7">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/style.css?v=<?php echo APP_VERSION ?? '1.0.0'; ?>" rel="stylesheet">

    <?php if (isset($pageStyles)): foreach ((array)$pageStyles as $style): ?>
    <link href="<?php echo htmlspecialchars($style); ?>" rel="stylesheet">
    <?php endforeach; endif; ?>

    <title><?php echo htmlspecialchars($pageTitle); ?></title>

    <?php if (isset($pageMeta)): foreach ((array)$pageMeta as $name => $content): ?>
    <meta name="<?php echo htmlspecialchars($name); ?>" content="<?php echo htmlspecialchars($content); ?>">
    <?php endforeach; endif; ?>

    <?php if (isset($ogTags)): foreach ((array)$ogTags as $property => $content): ?>
    <meta property="<?php echo htmlspecialchars($property); ?>" content="<?php echo htmlspecialchars($content); ?>">
    <?php endforeach; endif; ?>

    <script>
    const APP_CONFIG = {
        baseUrl: '<?php echo APP_URL ?? '/zyn-earn'; ?>',
        csrfToken: '<?php echo htmlspecialchars($csrfToken); ?>',
        version: '<?php echo APP_VERSION ?? '1.0.0'; ?>',
        currency: '<?php echo defined("APP_CURRENCY") ? APP_CURRENCY : "USD"; ?>',
        currencySymbol: '<?php echo defined("APP_CURRENCY_SYMBOL") ? APP_CURRENCY_SYMBOL : "$"; ?>',
        debug: <?php echo defined('APP_DEBUG') && APP_DEBUG ? 'true' : 'false'; ?>,
        userId: <?php echo $isLoggedIn ? ($_SESSION['user_id'] ?? 'null') : 'null'; ?>,
        unreadNotifications: <?php echo $unreadCount; ?>,
        theme: '<?php echo htmlspecialchars($theme); ?>',
        user: <?php echo $isLoggedIn ? json_encode([
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['user']['username'] ?? '',
            'balance' => $_SESSION['user']['balance'] ?? 0
        ]) : 'null'; ?>
    };
    </script>

    <?php if (defined('GA_ID') && GA_ID): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo GA_ID; ?>"></script>
    <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '<?php echo GA_ID; ?>');
    </script>
    <?php endif; ?>

    <?php if (isset($headExtra)): echo $headExtra; endif; ?>
</head>
<body class="<?php echo htmlspecialchars(trim($theme . ' ' . $bodyClass)); ?>"
      data-theme="<?php echo htmlspecialchars($theme); ?>"
      data-user-id="<?php echo $isLoggedIn ? ($_SESSION['user_id'] ?? '') : ''; ?>">
    <?php if (defined('BODY_EXTRA')): echo BODY_EXTRA; endif; ?>

    <div id="app" class="app-container">
        <?php if ($isLoggedIn): ?>
            <?php include __DIR__ . '/sidebar.php'; ?>
            <main class="main-content" id="mainContent">
                <div class="mobile-header">
                    <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Open menu">
                        <i class="fas fa-bars"></i>
                    </button>
                    <a href="/dashboard.php" class="mobile-logo">
                        <span class="logo-text">Zyn<span class="text-primary">Earn</span></span>
                    </a>
                    <div class="mobile-actions">
                        <a href="/notifications.php" class="mobile-notif-btn" id="mobileNotifBtn">
                            <i class="fas fa-bell"></i>
                            <?php if ($unreadCount > 0): ?>
                            <span class="notif-dot" id="mobileNotifDot"><?php echo $unreadCount > 9 ? '9+' : $unreadCount; ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
                <div class="content-wrapper">
        <?php endif; ?>
        <div id="pageLoader" class="page-loader" style="display:none;">
            <div class="loader-spinner"></div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('mobile-open');
                    if (sidebarOverlay) sidebarOverlay.classList.toggle('active');
                });
            }

            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            if (csrfMeta) {
                document.addEventListener('click', function(e) {
                    const form = e.target.closest('form');
                    if (form && !form.querySelector('input[name="csrf_token"]')) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'csrf_token';
                        input.value = csrfMeta.content;
                        form.appendChild(input);
                    }
                });
            }
        });
        </script>
