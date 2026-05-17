<?php
// Security Configuration
define('PEPPER', getenv('PEPPER') ?: 'zY7nEaRnS3cUr3P3pPeR!@#$');
define('HASH_ALGO', PASSWORD_ARGON2ID);
define('HASH_OPTIONS', ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 3]);
define('BCRYPT_COST', 12);
define('TOKEN_LENGTH', 64);
define('API_TOKEN_LENGTH', 128);
define('CSRF_TOKEN_LENGTH', 32);
define('OTP_LENGTH', 6);
define('OTP_EXPIRY', 300);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 900);
define('MAX_REGISTER_ATTEMPTS', 3);
define('REGISTER_TIMEOUT', 3600);

// Rate Limiting
define('RATE_LIMIT_ENABLED', true);
define('RATE_LIMIT_REQUESTS', 100);
define('RATE_LIMIT_WINDOW', 60);
define('API_RATE_LIMIT', 60);
define('FAUCET_COOLDOWN', 300);
define('SPIN_COOLDOWN', 300);

// CORS
define('CORS_ORIGINS', ['http://localhost:3000', 'https://zynearn.com']);

// Allowed IPs for admin (empty = all allowed)
define('ADMIN_ALLOWED_IPS', []);
