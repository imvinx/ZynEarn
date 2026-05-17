<?php
// Application Configuration
define('APP_NAME', 'ZynEarn');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost:8080');
define('APP_ENV', getenv('APP_ENV') ?: 'production');
define('APP_DEBUG', getenv('APP_DEBUG') ?: false);
define('APP_VERSION', '1.0.0');
define('APP_TIMEZONE', 'UTC');
define('APP_CURRENCY', 'USD');
define('APP_CURRENCY_SYMBOL', '$');

// Session Configuration
define('SESSION_LIFETIME', 86400 * 7);
define('SESSION_NAME', 'ZYNEARN_SESSION');
define('SESSION_SECURE', APP_ENV === 'production');
define('SESSION_HTTP_ONLY', true);
define('SESSION_SAME_SITE', 'Strict');

// Cookie Configuration
define('COOKIE_DOMAIN', '');
define('COOKIE_PATH', '/');
define('COOKIE_SECURE', APP_ENV === 'production');
define('COOKIE_HTTP_ONLY', true);
define('COOKIE_SAME_SITE', 'Strict');

// Cache Configuration
define('CACHE_ENABLED', true);
define('CACHE_DIR', __DIR__ . '/../system/cache');
define('CACHE_LIFETIME', 3600);

// Upload Configuration
define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('MAX_UPLOAD_SIZE', 5242880);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf']);

// Pagination
define('PAGINATION_LIMIT', 20);
