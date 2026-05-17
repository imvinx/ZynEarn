<?php
$isLoggedIn = isset($_SESSION['user_id']);
?>
        <?php if ($isLoggedIn): ?>
                </div>
            </main>
        <?php endif; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/js/all.min.js" defer></script>
    <script src="/zyn-earn/assets/js/app.js?v=<?php echo defined('APP_VERSION') ? APP_VERSION : '1.0.0'; ?>"></script>

    <?php if (isset($pageScripts)): foreach ((array)$pageScripts as $script): ?>
    <script src="<?php echo htmlspecialchars($script); ?>"></script>
    <?php endforeach; endif; ?>

    <?php if (isset($inlineScripts)): foreach ((array)$inlineScripts as $key => $script): ?>
    <script id="inline-script-<?php echo $key; ?>">
        <?php echo $script; ?>
    </script>
    <?php endforeach; endif; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/zyn-earn/pwa/sw.js', { scope: '/zyn-earn/' })
                .then(function(reg) {
                    console.log('SW registered:', reg.scope);
                })
                .catch(function(err) {
                    console.log('SW registration failed:', err);
                });
        }

        const pageLoader = document.getElementById('pageLoader');
        if (pageLoader) {
            window.addEventListener('beforeunload', function() {
                pageLoader.style.display = 'flex';
            });
            window.addEventListener('load', function() {
                pageLoader.style.display = 'none';
            });
        }

        const themeMeta = document.querySelector('meta[name="theme-color"]');
        if (themeMeta) {
            const html = document.documentElement;
            const observer = new MutationObserver(function() {
                const theme = html.getAttribute('data-theme');
                themeMeta.content = theme === 'light' ? '#ffffff' : '#0a0a1a';
            });
            observer.observe(html, { attributes: true, attributeFilter: ['data-theme'] });
        }

        const notifBadge = document.getElementById('notificationBadge');
        if (notifBadge && APP_CONFIG.userId) {
            function pollNotifications() {
                fetch(APP_CONFIG.baseUrl + '/api/notifications.php?action=unread', {
                    headers: {
                        'Authorization': 'Bearer ' + (localStorage.getItem('auth_token') || ''),
                        'X-CSRF-Token': APP_CONFIG.csrfToken
                    }
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success && data.count !== undefined) {
                        const count = parseInt(data.count);
                        const mobileDot = document.getElementById('mobileNotifDot');
                        if (count > 0) {
                            notifBadge.textContent = count > 99 ? '99+' : count;
                            notifBadge.style.display = 'flex';
                            if (mobileDot) {
                                mobileDot.textContent = count > 9 ? '9+' : count;
                                mobileDot.style.display = 'flex';
                            }
                        } else {
                            notifBadge.style.display = 'none';
                            if (mobileDot) mobileDot.style.display = 'none';
                        }
                    }
                })
                .catch(function() {});
            }

            setInterval(pollNotifications, 30000);
            setTimeout(pollNotifications, 5000);
        }
    });
    </script>

    <?php if (isset($footerExtra)): echo $footerExtra; endif; ?>
</body>
</html>
