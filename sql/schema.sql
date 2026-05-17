-- ------------------------------------------------------------
-- 1. users
-- ------------------------------------------------------------
CREATE TABLE `users` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`        VARCHAR(50)     NOT NULL,
  `email`           VARCHAR(255)    NOT NULL,
  `password`        VARCHAR(255)    NOT NULL,
  `google_id`       VARCHAR(100)    DEFAULT NULL,
  `discord_id`      VARCHAR(100)    DEFAULT NULL,
  `telegram_id`     VARCHAR(100)    DEFAULT NULL,
  `avatar`          VARCHAR(500)    DEFAULT NULL,
  `role`            ENUM('user','admin')          NOT NULL DEFAULT 'user',
  `status`          ENUM('active','banned','suspended') NOT NULL DEFAULT 'active',
  `email_verified`  TINYINT(1)     NOT NULL DEFAULT 0,
  `two_factor_enabled` TINYINT(1)  NOT NULL DEFAULT 0,
  `two_factor_secret`  VARCHAR(255) DEFAULT NULL,
  `device_fingerprint` VARCHAR(255) DEFAULT NULL,
  `last_login_ip`   VARCHAR(45)     DEFAULT NULL,
  `last_login_at`   DATETIME        DEFAULT NULL,
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `banned_at`       DATETIME        DEFAULT NULL,
  `ban_reason`      TEXT            DEFAULT NULL,
  `kyc_status`      ENUM('unverified','pending','verified','rejected') NOT NULL DEFAULT 'unverified',
  `kyc_verified_at` DATETIME        DEFAULT NULL,
  `referral_code`   VARCHAR(20)     DEFAULT NULL,
  `referred_by`     BIGINT UNSIGNED DEFAULT NULL,
  `referral_earnings` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `membership_tier` ENUM('free','silver','gold','platinum','vip') NOT NULL DEFAULT 'free',
  `xp_points`       INT UNSIGNED    NOT NULL DEFAULT 0,
  `level`           INT UNSIGNED    NOT NULL DEFAULT 1,
  `rank`            VARCHAR(50)     DEFAULT NULL,
  `streak_days`     INT UNSIGNED    NOT NULL DEFAULT 0,
  `last_daily_claim` DATE           DEFAULT NULL,
  `timezone`        VARCHAR(50)     DEFAULT 'UTC',
  `language`        VARCHAR(10)     DEFAULT 'en',
  `theme`           ENUM('dark','light') NOT NULL DEFAULT 'dark',
  `notifications_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`),
  UNIQUE KEY `uk_email` (`email`),
  UNIQUE KEY `uk_referral_code` (`referral_code`),
  KEY `idx_users_status` (`status`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_membership_tier` (`membership_tier`),
  KEY `idx_users_referred_by` (`referred_by`),
  KEY `idx_users_kyc_status` (`kyc_status`),
  KEY `idx_users_created_at` (`created_at`),
  CONSTRAINT `fk_users_referred_by` FOREIGN KEY (`referred_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. user_wallets
-- ------------------------------------------------------------
CREATE TABLE `user_wallets` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        BIGINT UNSIGNED NOT NULL,
  `wallet_type`    ENUM('upi','paytm','paypal','crypto','binance','faucetpay') NOT NULL,
  `wallet_address` VARCHAR(255)    NOT NULL,
  `is_default`     TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_wallets_user_id` (`user_id`),
  KEY `idx_user_wallets_type` (`wallet_type`),
  KEY `idx_user_wallets_default` (`user_id`, `is_default`),
  CONSTRAINT `fk_wallets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. earnings
-- ------------------------------------------------------------
CREATE TABLE `earnings` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       BIGINT UNSIGNED NOT NULL,
  `type`          ENUM('offerwall','shortlink','faucet','quiz','scratch','spin','video','survey','task','referral','bonus','daily','cashback','achievement','game','ad','article','poll','minigame','ai_task','mission','challenge') NOT NULL,
  `amount`        DECIMAL(14,8)   NOT NULL,
  `currency`      VARCHAR(10)     NOT NULL DEFAULT 'USD',
  `status`        ENUM('pending','credited','rejected') NOT NULL DEFAULT 'pending',
  `description`   VARCHAR(500)    DEFAULT NULL,
  `reference_id`  VARCHAR(100)    DEFAULT NULL,
  `metadata`      JSON            DEFAULT NULL,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_earnings_user_id` (`user_id`),
  KEY `idx_earnings_type` (`type`),
  KEY `idx_earnings_status` (`status`),
  KEY `idx_earnings_created_at` (`created_at`),
  KEY `idx_earnings_user_type` (`user_id`, `type`),
  KEY `idx_earnings_reference_id` (`reference_id`),
  CONSTRAINT `fk_earnings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. transactions
-- ------------------------------------------------------------
CREATE TABLE `transactions` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         BIGINT UNSIGNED NOT NULL,
  `type`            ENUM('deposit','withdrawal','referral_bonus','purchase','reward','transfer') NOT NULL,
  `amount`          DECIMAL(14,2)   NOT NULL,
  `fee`             DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  `net_amount`      DECIMAL(14,2)   NOT NULL,
  `currency`        VARCHAR(10)     NOT NULL DEFAULT 'USD',
  `payment_method`  VARCHAR(50)     DEFAULT NULL,
  `status`          ENUM('pending','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
  `reference_id`    VARCHAR(100)    DEFAULT NULL,
  `gateway_response` JSON          DEFAULT NULL,
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `approved_by`     BIGINT UNSIGNED DEFAULT NULL,
  `approved_at`     DATETIME        DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_transactions_user_id` (`user_id`),
  KEY `idx_transactions_type` (`type`),
  KEY `idx_transactions_status` (`status`),
  KEY `idx_transactions_created_at` (`created_at`),
  KEY `idx_transactions_reference_id` (`reference_id`),
  KEY `idx_transactions_approved_by` (`approved_by`),
  CONSTRAINT `fk_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_transactions_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. withdrawals
-- ------------------------------------------------------------
CREATE TABLE `withdrawals` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         BIGINT UNSIGNED NOT NULL,
  `amount`          DECIMAL(14,2)   NOT NULL,
  `fee`             DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  `net_amount`      DECIMAL(14,2)   NOT NULL,
  `payment_method`  ENUM('upi','paytm','paypal','crypto','binance','faucetpay') NOT NULL,
  `wallet_address`  VARCHAR(255)    NOT NULL,
  `status`          ENUM('pending','approved','processing','completed','failed','rejected') NOT NULL DEFAULT 'pending',
  `admin_note`      TEXT            DEFAULT NULL,
  `transaction_id`  BIGINT UNSIGNED DEFAULT NULL,
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `processed_at`    DATETIME        DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_withdrawals_user_id` (`user_id`),
  KEY `idx_withdrawals_status` (`status`),
  KEY `idx_withdrawals_payment_method` (`payment_method`),
  KEY `idx_withdrawals_created_at` (`created_at`),
  KEY `idx_withdrawals_transaction_id` (`transaction_id`),
  CONSTRAINT `fk_withdrawals_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_withdrawals_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 6. deposits
-- ------------------------------------------------------------
CREATE TABLE `deposits` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         BIGINT UNSIGNED NOT NULL,
  `amount`          DECIMAL(14,2)   NOT NULL,
  `bonus_amount`    DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  `total_amount`    DECIMAL(14,2)   NOT NULL,
  `payment_method`  VARCHAR(50)     NOT NULL,
  `gateway`         VARCHAR(50)     DEFAULT NULL,
  `transaction_id`  BIGINT UNSIGNED DEFAULT NULL,
  `status`          ENUM('pending','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_deposits_user_id` (`user_id`),
  KEY `idx_deposits_status` (`status`),
  KEY `idx_deposits_gateway` (`gateway`),
  KEY `idx_deposits_created_at` (`created_at`),
  KEY `idx_deposits_transaction_id` (`transaction_id`),
  CONSTRAINT `fk_deposits_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_deposits_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 7. offerwall_offers
-- ------------------------------------------------------------
CREATE TABLE `offerwall_offers` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`          VARCHAR(255)    NOT NULL,
  `description`    TEXT            DEFAULT NULL,
  `provider`       VARCHAR(100)    NOT NULL,
  `offer_id`       VARCHAR(100)    DEFAULT NULL,
  `payout_amount`  DECIMAL(14,8)   NOT NULL,
  `payout_type`    VARCHAR(50)     DEFAULT NULL,
  `category`       VARCHAR(100)    DEFAULT NULL,
  `icon`           VARCHAR(500)    DEFAULT NULL,
  `url`            VARCHAR(500)    DEFAULT NULL,
  `device_type`    VARCHAR(50)     DEFAULT NULL,
  `country_target` VARCHAR(255)    DEFAULT NULL,
  `status`         ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_offerwall_offers_status` (`status`),
  KEY `idx_offerwall_offers_provider` (`provider`),
  KEY `idx_offerwall_offers_category` (`category`),
  KEY `idx_offerwall_offers_payout` (`payout_amount`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 8. offerwall_completions
-- ------------------------------------------------------------
CREATE TABLE `offerwall_completions` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        BIGINT UNSIGNED NOT NULL,
  `offer_id`       BIGINT UNSIGNED DEFAULT NULL,
  `offerwall_id`   VARCHAR(100)    DEFAULT NULL,
  `payout`         DECIMAL(14,8)   NOT NULL,
  `status`         ENUM('pending','credited','rejected') NOT NULL DEFAULT 'pending',
  `click_id`       VARCHAR(100)    DEFAULT NULL,
  `transaction_id` BIGINT UNSIGNED DEFAULT NULL,
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_offerwall_completions_user_id` (`user_id`),
  KEY `idx_offerwall_completions_offer_id` (`offer_id`),
  KEY `idx_offerwall_completions_status` (`status`),
  KEY `idx_offerwall_completions_transaction_id` (`transaction_id`),
  CONSTRAINT `fk_offerwall_comp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_offerwall_comp_offer` FOREIGN KEY (`offer_id`) REFERENCES `offerwall_offers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_offerwall_comp_txn` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 9. shortlinks
-- ------------------------------------------------------------
CREATE TABLE `shortlinks` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`           VARCHAR(255)    NOT NULL,
  `url`             VARCHAR(500)    NOT NULL,
  `slug`            VARCHAR(100)    NOT NULL,
  `payout_per_click` DECIMAL(14,8)  NOT NULL,
  `min_visits`      INT UNSIGNED    NOT NULL DEFAULT 0,
  `max_visits`      INT UNSIGNED    DEFAULT NULL,
  `total_clicks`    INT UNSIGNED    NOT NULL DEFAULT 0,
  `status`          ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_shortlinks_slug` (`slug`),
  KEY `idx_shortlinks_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 10. shortlink_clicks
-- ------------------------------------------------------------
CREATE TABLE `shortlink_clicks` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       BIGINT UNSIGNED NOT NULL,
  `shortlink_id`  BIGINT UNSIGNED NOT NULL,
  `ip_address`    VARCHAR(45)     DEFAULT NULL,
  `user_agent`    TEXT            DEFAULT NULL,
  `rewarded`      TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_shortlink_clicks_user_id` (`user_id`),
  KEY `idx_shortlink_clicks_shortlink_id` (`shortlink_id`),
  KEY `idx_shortlink_clicks_created_at` (`created_at`),
  KEY `idx_shortlink_clicks_user_link` (`user_id`, `shortlink_id`),
  CONSTRAINT `fk_shortlink_clicks_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_shortlink_clicks_link` FOREIGN KEY (`shortlink_id`) REFERENCES `shortlinks` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 11. faucet_claims
-- ------------------------------------------------------------
CREATE TABLE `faucet_claims` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`           BIGINT UNSIGNED NOT NULL,
  `amount`            DECIMAL(14,8)   NOT NULL,
  `reward_multiplier` DECIMAL(5,2)    NOT NULL DEFAULT 1.00,
  `claim_type`        ENUM('standard','golden','high_roller') NOT NULL DEFAULT 'standard',
  `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_faucet_claims_user_id` (`user_id`),
  KEY `idx_faucet_claims_claim_type` (`claim_type`),
  KEY `idx_faucet_claims_created_at` (`created_at`),
  CONSTRAINT `fk_faucet_claims_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 12. quiz_questions
-- ------------------------------------------------------------
CREATE TABLE `quiz_questions` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category`       VARCHAR(100)    NOT NULL,
  `question`       TEXT            NOT NULL,
  `options`        JSON            NOT NULL,
  `correct_answer` VARCHAR(255)    NOT NULL,
  `difficulty`     ENUM('easy','medium','hard') NOT NULL DEFAULT 'easy',
  `reward_amount`  DECIMAL(14,8)   NOT NULL DEFAULT 0.00000000,
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_quiz_questions_category` (`category`),
  KEY `idx_quiz_questions_difficulty` (`difficulty`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 13. quiz_attempts
-- ------------------------------------------------------------
CREATE TABLE `quiz_attempts` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         BIGINT UNSIGNED NOT NULL,
  `score`           DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
  `total_questions` INT UNSIGNED    NOT NULL DEFAULT 0,
  `correct_answers` INT UNSIGNED    NOT NULL DEFAULT 0,
  `reward_earned`   DECIMAL(14,8)   NOT NULL DEFAULT 0.00000000,
  `completed_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_quiz_attempts_user_id` (`user_id`),
  KEY `idx_quiz_attempts_completed_at` (`completed_at`),
  CONSTRAINT `fk_quiz_attempts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 14. scratch_cards
-- ------------------------------------------------------------
CREATE TABLE `scratch_cards` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(255)    NOT NULL,
  `price`      DECIMAL(14,8)   NOT NULL,
  `rewards`    JSON            NOT NULL COMMENT 'Array of possible rewards with amounts and probabilities',
  `image`      VARCHAR(500)    DEFAULT NULL,
  `status`     ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_scratch_cards_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 15. scratch_plays
-- ------------------------------------------------------------
CREATE TABLE `scratch_plays` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       BIGINT UNSIGNED NOT NULL,
  `card_id`       BIGINT UNSIGNED NOT NULL,
  `reward_amount` DECIMAL(14,8)   NOT NULL DEFAULT 0.00000000,
  `won`           TINYINT(1)      NOT NULL DEFAULT 0,
  `played_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_scratch_plays_user_id` (`user_id`),
  KEY `idx_scratch_plays_card_id` (`card_id`),
  CONSTRAINT `fk_scratch_plays_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_scratch_plays_card` FOREIGN KEY (`card_id`) REFERENCES `scratch_cards` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 16. spin_wheels
-- ------------------------------------------------------------
CREATE TABLE `spin_wheels` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`            VARCHAR(255)    NOT NULL,
  `segments`        JSON            NOT NULL,
  `spin_cost`       DECIMAL(14,8)   NOT NULL DEFAULT 0.00000000,
  `cooldown_minutes` INT UNSIGNED   NOT NULL DEFAULT 0,
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 17. spin_results
-- ------------------------------------------------------------
CREATE TABLE `spin_results` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        BIGINT UNSIGNED NOT NULL,
  `wheel_id`       BIGINT UNSIGNED NOT NULL,
  `segment_index`  INT UNSIGNED    NOT NULL,
  `reward`         VARCHAR(255)    DEFAULT NULL,
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_spin_results_user_id` (`user_id`),
  KEY `idx_spin_results_wheel_id` (`wheel_id`),
  CONSTRAINT `fk_spin_results_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_spin_results_wheel` FOREIGN KEY (`wheel_id`) REFERENCES `spin_wheels` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 18. surveys
-- ------------------------------------------------------------
CREATE TABLE `surveys` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`          VARCHAR(255)    NOT NULL,
  `description`    TEXT            DEFAULT NULL,
  `questions`      JSON            NOT NULL,
  `reward_amount`  DECIMAL(14,8)   NOT NULL,
  `estimated_time` INT UNSIGNED    DEFAULT NULL COMMENT 'In minutes',
  `category`       VARCHAR(100)    DEFAULT NULL,
  `status`         ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_surveys_status` (`status`),
  KEY `idx_surveys_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 19. survey_responses
-- ------------------------------------------------------------
CREATE TABLE `survey_responses` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         BIGINT UNSIGNED NOT NULL,
  `survey_id`       BIGINT UNSIGNED NOT NULL,
  `answers`         JSON            NOT NULL,
  `completed`       TINYINT(1)      NOT NULL DEFAULT 0,
  `reward_credited` TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_survey_responses_user_id` (`user_id`),
  KEY `idx_survey_responses_survey_id` (`survey_id`),
  CONSTRAINT `fk_survey_responses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_survey_responses_survey` FOREIGN KEY (`survey_id`) REFERENCES `surveys` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 20. tasks
-- ------------------------------------------------------------
CREATE TABLE `tasks` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`           VARCHAR(255)    NOT NULL,
  `description`     TEXT            DEFAULT NULL,
  `type`            ENUM('social','youtube','telegram','discord','twitter','instagram','facebook','custom') NOT NULL,
  `reward_amount`   DECIMAL(14,8)   NOT NULL,
  `url`             VARCHAR(500)    DEFAULT NULL,
  `requirements`    TEXT            DEFAULT NULL,
  `verification_type` ENUM('manual','auto') NOT NULL DEFAULT 'auto',
  `status`          ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tasks_type` (`type`),
  KEY `idx_tasks_status` (`status`),
  KEY `idx_tasks_verification_type` (`verification_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 21. task_submissions
-- ------------------------------------------------------------
CREATE TABLE `task_submissions` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       BIGINT UNSIGNED NOT NULL,
  `task_id`       BIGINT UNSIGNED NOT NULL,
  `proof_url`     VARCHAR(500)    DEFAULT NULL,
  `status`        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_note`    TEXT            DEFAULT NULL,
  `reviewed_by`   BIGINT UNSIGNED DEFAULT NULL,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at`   DATETIME        DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_task_submissions_user_id` (`user_id`),
  KEY `idx_task_submissions_task_id` (`task_id`),
  KEY `idx_task_submissions_status` (`status`),
  KEY `idx_task_submissions_reviewed_by` (`reviewed_by`),
  CONSTRAINT `fk_task_submissions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_task_submissions_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_task_submissions_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 22. daily_rewards
-- ------------------------------------------------------------
CREATE TABLE `daily_rewards` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `day_number`    TINYINT UNSIGNED NOT NULL COMMENT '1-30',
  `reward_amount` DECIMAL(14,8)   NOT NULL,
  `reward_type`   VARCHAR(50)     NOT NULL DEFAULT 'coins',
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_daily_rewards_day` (`day_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 23. daily_logins
-- ------------------------------------------------------------
CREATE TABLE `daily_logins` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        BIGINT UNSIGNED NOT NULL,
  `day_number`     TINYINT UNSIGNED NOT NULL,
  `reward_claimed` TINYINT(1)      NOT NULL DEFAULT 0,
  `claimed_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_daily_logins_user_id` (`user_id`),
  KEY `idx_daily_logins_day` (`day_number`),
  KEY `idx_daily_logins_claimed_at` (`claimed_at`),
  CONSTRAINT `fk_daily_logins_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 24. achievements
-- ------------------------------------------------------------
CREATE TABLE `achievements` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`              VARCHAR(255)    NOT NULL,
  `description`       TEXT            DEFAULT NULL,
  `icon`              VARCHAR(500)    DEFAULT NULL,
  `requirement_type`  VARCHAR(100)    NOT NULL,
  `requirement_value` INT UNSIGNED    NOT NULL DEFAULT 0,
  `reward_amount`     DECIMAL(14,8)   NOT NULL DEFAULT 0.00000000,
  `xp_reward`         INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_achievements_requirement_type` (`requirement_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 25. user_achievements
-- ------------------------------------------------------------
CREATE TABLE `user_achievements` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        BIGINT UNSIGNED NOT NULL,
  `achievement_id` BIGINT UNSIGNED NOT NULL,
  `unlocked_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_achievement` (`user_id`, `achievement_id`),
  KEY `idx_user_achievements_user_id` (`user_id`),
  KEY `idx_user_achievements_achievement_id` (`achievement_id`),
  CONSTRAINT `fk_user_achievements_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_user_achievements_achievement` FOREIGN KEY (`achievement_id`) REFERENCES `achievements` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 26. referrals
-- ------------------------------------------------------------
CREATE TABLE `referrals` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `referrer_id`       BIGINT UNSIGNED NOT NULL,
  `referred_id`       BIGINT UNSIGNED NOT NULL,
  `status`            ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `commission_earned` DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  `commission_rate`   DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
  `level`             TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1-5',
  `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_referrals_pair` (`referrer_id`, `referred_id`),
  KEY `idx_referrals_referrer_id` (`referrer_id`),
  KEY `idx_referrals_referred_id` (`referred_id`),
  KEY `idx_referrals_level` (`level`),
  CONSTRAINT `fk_referrals_referrer` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_referrals_referred` FOREIGN KEY (`referred_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 27. referral_commissions
-- ------------------------------------------------------------
CREATE TABLE `referral_commissions` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `referral_id` BIGINT UNSIGNED NOT NULL,
  `level`       TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `amount`      DECIMAL(14,2)   NOT NULL,
  `source_type` VARCHAR(50)     DEFAULT NULL,
  `source_id`   BIGINT UNSIGNED DEFAULT NULL,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_referral_commissions_referral_id` (`referral_id`),
  KEY `idx_referral_commissions_level` (`level`),
  CONSTRAINT `fk_referral_commissions_referral` FOREIGN KEY (`referral_id`) REFERENCES `referrals` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 28. notifications
-- ------------------------------------------------------------
CREATE TABLE `notifications` (
  `id`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`   BIGINT UNSIGNED NOT NULL,
  `type`      VARCHAR(50)     NOT NULL,
  `title`     VARCHAR(255)    NOT NULL,
  `message`   TEXT            DEFAULT NULL,
  `data`      JSON            DEFAULT NULL,
  `read`      TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at` DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user_id` (`user_id`),
  KEY `idx_notifications_read` (`read`),
  KEY `idx_notifications_created_at` (`created_at`),
  KEY `idx_notifications_user_read` (`user_id`, `read`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 29. announcements
-- ------------------------------------------------------------
CREATE TABLE `announcements` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`      VARCHAR(255)    NOT NULL,
  `message`    TEXT            NOT NULL,
  `type`       ENUM('info','warning','success','danger') NOT NULL DEFAULT 'info',
  `target`     ENUM('all','users','admins') NOT NULL DEFAULT 'all',
  `status`     ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_announcements_type` (`type`),
  KEY `idx_announcements_target` (`target`),
  KEY `idx_announcements_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 30. coupons
-- ------------------------------------------------------------
CREATE TABLE `coupons` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`         VARCHAR(50)     NOT NULL,
  `type`         ENUM('fixed','percentage') NOT NULL,
  `value`        DECIMAL(14,2)   NOT NULL,
  `min_amount`   DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  `max_uses`     INT UNSIGNED    DEFAULT NULL,
  `current_uses` INT UNSIGNED    NOT NULL DEFAULT 0,
  `expires_at`   DATETIME        DEFAULT NULL,
  `status`       ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_coupons_code` (`code`),
  KEY `idx_coupons_status` (`status`),
  KEY `idx_coupons_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 31. redeemed_coupons
-- ------------------------------------------------------------
CREATE TABLE `redeemed_coupons` (
  `id`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`   BIGINT UNSIGNED NOT NULL,
  `coupon_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_coupon` (`user_id`, `coupon_id`),
  KEY `idx_redeemed_coupons_user_id` (`user_id`),
  KEY `idx_redeemed_coupons_coupon_id` (`coupon_id`),
  CONSTRAINT `fk_redeemed_coupons_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_redeemed_coupons_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 32. user_sessions
-- ------------------------------------------------------------
CREATE TABLE `user_sessions` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       BIGINT UNSIGNED NOT NULL,
  `session_token` VARCHAR(255)    NOT NULL,
  `ip_address`    VARCHAR(45)     DEFAULT NULL,
  `user_agent`    TEXT            DEFAULT NULL,
  `device_type`   VARCHAR(50)     DEFAULT NULL,
  `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
  `expires_at`    DATETIME        NOT NULL,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_sessions_user_id` (`user_id`),
  KEY `idx_user_sessions_token` (`session_token`),
  KEY `idx_user_sessions_active` (`is_active`),
  KEY `idx_user_sessions_expires_at` (`expires_at`),
  CONSTRAINT `fk_user_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 33. login_attempts
-- ------------------------------------------------------------
CREATE TABLE `login_attempts` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_address`   VARCHAR(45)     NOT NULL,
  `username`     VARCHAR(50)     DEFAULT NULL,
  `attempted_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `success`      TINYINT(1)      NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_login_attempts_ip` (`ip_address`),
  KEY `idx_login_attempts_username` (`username`),
  KEY `idx_login_attempts_attempted_at` (`attempted_at`),
  KEY `idx_login_attempts_success` (`success`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 34. chat_messages
-- ------------------------------------------------------------
CREATE TABLE `chat_messages` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `message`    TEXT            NOT NULL,
  `room`       ENUM('global','team','admin') NOT NULL DEFAULT 'global',
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chat_messages_user_id` (`user_id`),
  KEY `idx_chat_messages_room` (`room`),
  KEY `idx_chat_messages_created_at` (`created_at`),
  CONSTRAINT `fk_chat_messages_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 35. support_tickets
-- ------------------------------------------------------------
CREATE TABLE `support_tickets` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `subject`    VARCHAR(255)    NOT NULL,
  `message`    TEXT            NOT NULL,
  `category`   VARCHAR(100)    DEFAULT NULL,
  `priority`   ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `status`     ENUM('open','closed','resolved') NOT NULL DEFAULT 'open',
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_support_tickets_user_id` (`user_id`),
  KEY `idx_support_tickets_status` (`status`),
  KEY `idx_support_tickets_priority` (`priority`),
  KEY `idx_support_tickets_created_at` (`created_at`),
  CONSTRAINT `fk_support_tickets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 36. ticket_replies
-- ------------------------------------------------------------
CREATE TABLE `ticket_replies` (
  `id`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` BIGINT UNSIGNED NOT NULL,
  `user_id`   BIGINT UNSIGNED NOT NULL,
  `message`   TEXT            NOT NULL,
  `created_at` DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ticket_replies_ticket_id` (`ticket_id`),
  KEY `idx_ticket_replies_user_id` (`user_id`),
  CONSTRAINT `fk_ticket_replies_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ticket_replies_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 37. blog_posts
-- ------------------------------------------------------------
CREATE TABLE `blog_posts` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`         VARCHAR(255)    NOT NULL,
  `slug`          VARCHAR(255)    NOT NULL,
  `content`       LONGTEXT        DEFAULT NULL,
  `excerpt`       TEXT            DEFAULT NULL,
  `featured_image` VARCHAR(500)   DEFAULT NULL,
  `author_id`     BIGINT UNSIGNED DEFAULT NULL,
  `category`      VARCHAR(100)    DEFAULT NULL,
  `tags`          JSON            DEFAULT NULL,
  `status`        ENUM('draft','published') NOT NULL DEFAULT 'draft',
  `published_at`  DATETIME        DEFAULT NULL,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_blog_posts_slug` (`slug`),
  KEY `idx_blog_posts_author_id` (`author_id`),
  KEY `idx_blog_posts_status` (`status`),
  KEY `idx_blog_posts_category` (`category`),
  KEY `idx_blog_posts_published_at` (`published_at`),
  CONSTRAINT `fk_blog_posts_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 38. pages_cms
-- ------------------------------------------------------------
CREATE TABLE `pages_cms` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`           VARCHAR(255)    NOT NULL,
  `slug`            VARCHAR(255)    NOT NULL,
  `content`         LONGTEXT        DEFAULT NULL,
  `meta_title`      VARCHAR(255)    DEFAULT NULL,
  `meta_description` TEXT           DEFAULT NULL,
  `status`          ENUM('draft','published') NOT NULL DEFAULT 'draft',
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pages_cms_slug` (`slug`),
  KEY `idx_pages_cms_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 39. faq_items
-- ------------------------------------------------------------
CREATE TABLE `faq_items` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `question`   TEXT            NOT NULL,
  `answer`     LONGTEXT        NOT NULL,
  `category`   VARCHAR(100)    DEFAULT NULL,
  `sort_order` INT UNSIGNED    NOT NULL DEFAULT 0,
  `status`     ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_faq_items_category` (`category`),
  KEY `idx_faq_items_sort_order` (`sort_order`),
  KEY `idx_faq_items_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 40. admin_logs
-- ------------------------------------------------------------
CREATE TABLE `admin_logs` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id`   BIGINT UNSIGNED NOT NULL,
  `action`     VARCHAR(255)    NOT NULL,
  `details`    JSON            DEFAULT NULL,
  `ip_address` VARCHAR(45)     DEFAULT NULL,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_logs_admin_id` (`admin_id`),
  KEY `idx_admin_logs_action` (`action`),
  KEY `idx_admin_logs_created_at` (`created_at`),
  CONSTRAINT `fk_admin_logs_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 41. site_settings
-- ------------------------------------------------------------
CREATE TABLE `site_settings` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key`        VARCHAR(100)    NOT NULL,
  `value`      LONGTEXT        DEFAULT NULL,
  `group_name` VARCHAR(100)    NOT NULL DEFAULT 'general',
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_settings_key` (`key`),
  KEY `idx_site_settings_group` (`group_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 42. payment_gateways
-- ------------------------------------------------------------
CREATE TABLE `payment_gateways` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(100)    NOT NULL,
  `display_name` VARCHAR(255)    NOT NULL,
  `type`         VARCHAR(50)     NOT NULL,
  `credentials`  JSON            DEFAULT NULL,
  `status`       ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `sort_order`   INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_payment_gateways_name` (`name`),
  KEY `idx_payment_gateways_status` (`status`),
  KEY `idx_payment_gateways_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 43. mining_sessions
-- ------------------------------------------------------------
CREATE TABLE `mining_sessions` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      BIGINT UNSIGNED NOT NULL,
  `hash_rate`    DECIMAL(14,4)   NOT NULL DEFAULT 0.0000,
  `started_at`   DATETIME        NOT NULL,
  `ended_at`     DATETIME        DEFAULT NULL,
  `total_earned` DECIMAL(14,8)   NOT NULL DEFAULT 0.00000000,
  `status`       ENUM('active','completed') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `idx_mining_sessions_user_id` (`user_id`),
  KEY `idx_mining_sessions_status` (`status`),
  KEY `idx_mining_sessions_started_at` (`started_at`),
  CONSTRAINT `fk_mining_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 44. nft_items
-- ------------------------------------------------------------
CREATE TABLE `nft_items` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(255)    NOT NULL,
  `description` TEXT            DEFAULT NULL,
  `image_url`   VARCHAR(500)    DEFAULT NULL,
  `price`       DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  `owner_id`    BIGINT UNSIGNED DEFAULT NULL,
  `creator_id`  BIGINT UNSIGNED DEFAULT NULL,
  `rarity`      ENUM('common','uncommon','rare','epic','legendary') NOT NULL DEFAULT 'common',
  `status`      ENUM('available','sold','minted') NOT NULL DEFAULT 'available',
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_nft_items_owner_id` (`owner_id`),
  KEY `idx_nft_items_creator_id` (`creator_id`),
  KEY `idx_nft_items_rarity` (`rarity`),
  KEY `idx_nft_items_status` (`status`),
  CONSTRAINT `fk_nft_items_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_nft_items_creator` FOREIGN KEY (`creator_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 45. user_nfts
-- ------------------------------------------------------------
CREATE TABLE `user_nfts` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      BIGINT UNSIGNED NOT NULL,
  `nft_id`       BIGINT UNSIGNED NOT NULL,
  `purchased_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_nft` (`user_id`, `nft_id`),
  KEY `idx_user_nfts_user_id` (`user_id`),
  KEY `idx_user_nfts_nft_id` (`nft_id`),
  CONSTRAINT `fk_user_nfts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_user_nfts_nft` FOREIGN KEY (`nft_id`) REFERENCES `nft_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 46. game_scores
-- ------------------------------------------------------------
CREATE TABLE `game_scores` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       BIGINT UNSIGNED NOT NULL,
  `game_type`     VARCHAR(100)    NOT NULL,
  `score`         INT UNSIGNED    NOT NULL DEFAULT 0,
  `reward_earned` DECIMAL(14,8)   NOT NULL DEFAULT 0.00000000,
  `played_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_game_scores_user_id` (`user_id`),
  KEY `idx_game_scores_game_type` (`game_type`),
  KEY `idx_game_scores_played_at` (`played_at`),
  CONSTRAINT `fk_game_scores_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 47. user_rewards
-- ------------------------------------------------------------
CREATE TABLE `user_rewards` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      BIGINT UNSIGNED NOT NULL,
  `reward_type`  VARCHAR(50)     NOT NULL,
  `reference_id` BIGINT UNSIGNED DEFAULT NULL,
  `amount`       DECIMAL(14,8)   NOT NULL DEFAULT 0.00000000,
  `claimed`      TINYINT(1)      NOT NULL DEFAULT 0,
  `claimed_at`   DATETIME        DEFAULT NULL,
  `expires_at`   DATETIME        DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_rewards_user_id` (`user_id`),
  KEY `idx_user_rewards_reward_type` (`reward_type`),
  KEY `idx_user_rewards_claimed` (`claimed`),
  KEY `idx_user_rewards_expires_at` (`expires_at`),
  CONSTRAINT `fk_user_rewards_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 48. missions
-- ------------------------------------------------------------
CREATE TABLE `missions` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`         VARCHAR(255)    NOT NULL,
  `description`   TEXT            DEFAULT NULL,
  `requirements`  JSON            NOT NULL,
  `rewards`       JSON            NOT NULL,
  `duration_type` ENUM('daily','weekly','monthly') NOT NULL DEFAULT 'daily',
  `start_date`    DATETIME        DEFAULT NULL,
  `end_date`      DATETIME        DEFAULT NULL,
  `status`        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_missions_duration_type` (`duration_type`),
  KEY `idx_missions_status` (`status`),
  KEY `idx_missions_dates` (`start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 49. user_missions
-- ------------------------------------------------------------
CREATE TABLE `user_missions` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        BIGINT UNSIGNED NOT NULL,
  `mission_id`     BIGINT UNSIGNED NOT NULL,
  `progress`       INT UNSIGNED    NOT NULL DEFAULT 0,
  `completed`      TINYINT(1)      NOT NULL DEFAULT 0,
  `completed_at`   DATETIME        DEFAULT NULL,
  `reward_claimed` TINYINT(1)      NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_mission` (`user_id`, `mission_id`),
  KEY `idx_user_missions_user_id` (`user_id`),
  KEY `idx_user_missions_mission_id` (`mission_id`),
  KEY `idx_user_missions_completed` (`completed`),
  CONSTRAINT `fk_user_missions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_user_missions_mission` FOREIGN KEY (`mission_id`) REFERENCES `missions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 50. ai_chat_messages
-- ------------------------------------------------------------
CREATE TABLE `ai_chat_messages` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `message`    TEXT            NOT NULL,
  `response`   LONGTEXT        DEFAULT NULL,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_chat_messages_user_id` (`user_id`),
  KEY `idx_ai_chat_messages_created_at` (`created_at`),
  CONSTRAINT `fk_ai_chat_messages_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED DATA
-- ============================================================

-- ------------------------------------------------------------
-- Default Admin User
-- Password: Admin@123 (bcrypt hash)
-- ------------------------------------------------------------
INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `status`, `email_verified`, `referral_code`, `membership_tier`, `xp_points`, `level`, `timezone`, `language`, `theme`, `notifications_enabled`)
VALUES (1, 'admin', 'admin@zynearn.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', 1, 'ADMIN1', 'vip', 999999, 100, 'UTC', 'en', 'dark', 1);

-- ------------------------------------------------------------
-- Default Site Settings
-- ------------------------------------------------------------
INSERT INTO `site_settings` (`key`, `value`, `group_name`) VALUES
('site_name', 'ZynEarn', 'general'),
('site_description', 'Modern Money Earning Platform', 'general'),
('site_logo', '/uploads/logo.png', 'general'),
('site_favicon', '/uploads/favicon.ico', 'general'),
('site_url', 'https://zynearn.com', 'general'),
('site_email', 'support@zynearn.com', 'general'),
('currency', 'USD', 'general'),
('currency_symbol', '$', 'general'),
('min_withdrawal', '1.00', 'withdrawal'),
('max_withdrawal', '1000.00', 'withdrawal'),
('withdrawal_fee_percentage', '5.00', 'withdrawal'),
('min_deposit', '5.00', 'deposit'),
('referral_bonus_percentage', '10.00', 'referral'),
('referral_levels', '5', 'referral'),
('daily_reward_enabled', '1', 'rewards'),
('faucet_interval_minutes', '60', 'faucet'),
('faucet_base_reward', '0.00000100', 'faucet'),
('maintenance_mode', '0', 'general'),
('registration_enabled', '1', 'general'),
('email_verification_required', '0', 'general'),
('max_login_attempts', '5', 'security'),
('lockout_duration_minutes', '30', 'security');

-- ------------------------------------------------------------
-- Default Daily Rewards (30 days)
-- ------------------------------------------------------------
INSERT INTO `daily_rewards` (`day_number`, `reward_amount`, `reward_type`) VALUES
(1, 0.00000100, 'coins'),
(2, 0.00000200, 'coins'),
(3, 0.00000300, 'coins'),
(4, 0.00000400, 'coins'),
(5, 0.00000500, 'coins'),
(6, 0.00000600, 'coins'),
(7, 0.00001000, 'coins'),
(8, 0.00000800, 'coins'),
(9, 0.00000900, 'coins'),
(10, 0.00001000, 'coins'),
(11, 0.00001100, 'coins'),
(12, 0.00001200, 'coins'),
(13, 0.00001300, 'coins'),
(14, 0.00002000, 'coins'),
(15, 0.00001500, 'coins'),
(16, 0.00001600, 'coins'),
(17, 0.00001700, 'coins'),
(18, 0.00001800, 'coins'),
(19, 0.00001900, 'coins'),
(20, 0.00002000, 'coins'),
(21, 0.00002500, 'coins'),
(22, 0.00002200, 'coins'),
(23, 0.00002300, 'coins'),
(24, 0.00002400, 'coins'),
(25, 0.00002500, 'coins'),
(26, 0.00003000, 'coins'),
(27, 0.00002700, 'coins'),
(28, 0.00002800, 'coins'),
(29, 0.00002900, 'coins'),
(30, 0.00005000, 'coins');

-- ------------------------------------------------------------
-- Default Achievements
-- ------------------------------------------------------------
INSERT INTO `achievements` (`name`, `description`, `requirement_type`, `requirement_value`, `reward_amount`, `xp_reward`) VALUES
('First Steps', 'Complete your first task', 'tasks_completed', 1, 0.00001000, 10),
('Getting Started', 'Earn your first $0.01', 'total_earnings', 0.01, 0.00005000, 25),
('Shortlink Star', 'Click 100 shortlinks', 'shortlink_clicks', 100, 0.00010000, 50),
('Faucet Fanatic', 'Claim faucet 50 times', 'faucet_claims', 50, 0.00010000, 50),
('Quiz Master', 'Answer 50 quiz questions correctly', 'quiz_correct', 50, 0.00020000, 75),
('Scratch Addict', 'Play 25 scratch cards', 'scratch_plays', 25, 0.00015000, 50),
('Spin Champion', 'Spin the wheel 30 times', 'spins', 30, 0.00015000, 50),
('Survey Expert', 'Complete 10 surveys', 'surveys_completed', 10, 0.00025000, 100),
('Social Butterfly', 'Complete 20 social tasks', 'social_tasks', 20, 0.00020000, 75),
('Referral King', 'Refer 10 active users', 'referrals', 10, 0.00050000, 150),
('Streak Master', 'Maintain a 7-day login streak', 'streak_days', 7, 0.00030000, 100),
('Diamond Hands', 'Maintain a 30-day login streak', 'streak_days', 30, 0.00100000, 300),
('Millionaire', 'Earn a total of $1.00', 'total_earnings', 1.00, 0.00100000, 250),
('Task Crusher', 'Complete 100 tasks', 'tasks_completed', 100, 0.00050000, 150),
('NFT Collector', 'Purchase 5 NFTs', 'nfts_purchased', 5, 0.00050000, 150),
('Miner', 'Complete 10 mining sessions', 'mining_sessions', 10, 0.00025000, 100),
('High Roller', 'Earn $0.01 in a single faucet claim', 'faucet_high_roll', 1, 0.00030000, 100),
('Game Champion', 'Score 1000 points in any game', 'game_high_score', 1000, 0.00020000, 75),
('Mission Accomplished', 'Complete 5 missions', 'missions_completed', 5, 0.00030000, 100),
('Supportive', 'Submit 3 support tickets', 'support_tickets', 3, 0.00010000, 50);

-- ------------------------------------------------------------
-- Default Payment Gateways
-- ------------------------------------------------------------
INSERT INTO `payment_gateways` (`name`, `display_name`, `type`, `status`, `sort_order`) VALUES
('paypal', 'PayPal', 'paypal', 'active', 1),
('paytm', 'Paytm', 'upi', 'active', 2),
('upi', 'UPI Transfer', 'upi', 'active', 3),
('binance', 'Binance Pay', 'crypto', 'active', 4),
('crypto', 'Cryptocurrency', 'crypto', 'active', 5),
('faucetpay', 'FaucetPay', 'crypto', 'active', 6);

-- ============================================================
-- END OF SCHEMA
-- ============================================================
