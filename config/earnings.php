<?php
// Earning Configuration
if (!defined('FAUCET_BASE_REWARD')) define('FAUCET_BASE_REWARD', 0.0001);
if (!defined('FAUCET_MAX_REWARD')) define('FAUCET_MAX_REWARD', 0.001);
if (!defined('FAUCET_BONUS_MULTIPLIER')) define('FAUCET_BONUS_MULTIPLIER', 1.5);

if (!defined('SHORTLINK_BASE_PAYOUT')) define('SHORTLINK_BASE_PAYOUT', 0.001);
if (!defined('SHORTLINK_MAX_PAYOUT')) define('SHORTLINK_MAX_PAYOUT', 0.01);

if (!defined('QUIZ_EASY_REWARD')) define('QUIZ_EASY_REWARD', 0.01);
if (!defined('QUIZ_MEDIUM_REWARD')) define('QUIZ_MEDIUM_REWARD', 0.025);
if (!defined('QUIZ_HARD_REWARD')) define('QUIZ_HARD_REWARD', 0.05);

if (!defined('SCRATCH_CARD_COST')) define('SCRATCH_CARD_COST', 0.05);
if (!defined('SCRATCH_MIN_WIN')) define('SCRATCH_MIN_WIN', 0.01);
if (!defined('SCRATCH_MAX_WIN')) define('SCRATCH_MAX_WIN', 1.00);

if (!defined('SPIN_COST')) define('SPIN_COST', 0.02);
if (!defined('SPIN_COOLDOWN')) define('SPIN_COOLDOWN', 300);

if (!defined('VIDEO_REWARD')) define('VIDEO_REWARD', 0.005);
if (!defined('AD_REWARD')) define('AD_REWARD', 0.001);

if (!defined('SURVEY_BASE_REWARD')) define('SURVEY_BASE_REWARD', 0.10);
if (!defined('SURVEY_MAX_REWARD')) define('SURVEY_MAX_REWARD', 2.00);

if (!defined('DAILY_BONUS_BASE')) define('DAILY_BONUS_BASE', 0.01);
if (!defined('DAILY_BONUS_INCREMENT')) define('DAILY_BONUS_INCREMENT', 0.005);
if (!defined('MAX_STREAK_BONUS')) define('MAX_STREAK_BONUS', 1.00);

if (!defined('XP_PER_EARN')) define('XP_PER_EARN', 0.1);
if (!defined('XP_PER_REFERRAL')) define('XP_PER_REFERRAL', 50);
if (!defined('XP_PER_LOGIN')) define('XP_PER_LOGIN', 10);

define('LEVEL_THRESHOLDS', [
    1 => 0,
    2 => 100,
    3 => 250,
    4 => 500,
    5 => 1000,
    6 => 2000,
    7 => 3500,
    8 => 5000,
    9 => 7500,
    10 => 10000,
    11 => 15000,
    12 => 25000,
    13 => 50000,
    14 => 100000,
    15 => 250000
]);

define('VIP_MEMBERSHIP_COSTS', [
    'silver' => 5.00,
    'gold' => 15.00,
    'platinum' => 50.00,
    'vip' => 100.00
]);

define('VIP_MULTIPLIERS', [
    'free' => 1.0,
    'silver' => 1.2,
    'gold' => 1.5,
    'platinum' => 2.0,
    'vip' => 3.0
]);
