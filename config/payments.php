<?php
// Payment Gateway Configuration
define('PAYPAL_CLIENT_ID', getenv('PAYPAL_CLIENT_ID') ?: '');
define('PAYPAL_CLIENT_SECRET', getenv('PAYPAL_CLIENT_SECRET') ?: '');
define('PAYPAL_MODE', 'sandbox');

define('STRIPE_PUBLIC_KEY', getenv('STRIPE_PUBLIC_KEY') ?: '');
define('STRIPE_SECRET_KEY', getenv('STRIPE_SECRET_KEY') ?: '');
define('STRIPE_WEBHOOK_SECRET', getenv('STRIPE_WEBHOOK_SECRET') ?: '');

define('RAZORPAY_KEY_ID', getenv('RAZORPAY_KEY_ID') ?: '');
define('RAZORPAY_KEY_SECRET', getenv('RAZORPAY_KEY_SECRET') ?: '');

define('BINANCE_API_KEY', getenv('BINANCE_API_KEY') ?: '');
define('BINANCE_SECRET_KEY', getenv('BINANCE_SECRET_KEY') ?: '');

define('FAUCETPAY_API_KEY', getenv('FAUCETPAY_API_KEY') ?: '');
define('FAUCETPAY_SECRET', getenv('FAUCETPAY_SECRET') ?: '');

// Minimum/Maximum Withdrawals
define('MIN_WITHDRAWAL', 1.00);
define('MAX_WITHDRAWAL', 1000.00);
define('WITHDRAWAL_FEE_PERCENTAGE', 2);
define('WITHDRAWAL_FEE_FIXED', 0.50);

// Deposit Bonus
define('DEPOSIT_BONUS_PERCENTAGE', 10);
define('MAX_DEPOSIT_BONUS', 100.00);

// Referral Commission Levels
define('REFERRAL_COMMISSION_LEVELS', [
    1 => 10,  // Level 1: 10%
    2 => 5,   // Level 2: 5%
    3 => 2.5, // Level 3: 2.5%
    4 => 1,   // Level 4: 1%
    5 => 0.5  // Level 5: 0.5%
]);
