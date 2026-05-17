<?php

define('CRON_SECRET', getenv('CRON_SECRET') ?: 'change-this-to-a-random-secret-key');

if (!isset($_GET['task'])) {
    http_response_code(400);
    die(json_encode(['error' => 'No task specified']));
}

$providedKey = $_GET['key'] ?? '';
if ($providedKey !== CRON_SECRET) {
    http_response_code(403);
    die(json_encode(['error' => 'Invalid cron secret']));
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$task = $_GET['task'];
$allowedTasks = [
    'process_withdrawals',
    'reset_faucet',
    'reset_daily_rewards',
    'clean_sessions',
    'clean_login_attempts',
    'send_notifications',
    'process_queue',
    'update_levels',
    'process_referrals',
    'backup_database',
    'clean_logs',
    'daily_reset'
];

if (!in_array($task, $allowedTasks)) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid task']));
}

$startTime = microtime(true);
$log = [];
$log[] = "[" . date('Y-m-d H:i:s') . "] Task: {$task} started";

try {
    $result = $task();
    $log[] = "[" . date('Y-m-d H:i:s') . "] Task: {$task} completed";
} catch (Exception $e) {
    $log[] = "[" . date('Y-m-d H:i:s') . "] Task: {$task} failed - " . $e->getMessage();
    $result = ['success' => false, 'error' => $e->getMessage()];
}

$elapsed = round((microtime(true) - $startTime) * 1000, 2);
$log[] = "[" . date('Y-m-d H:i:s') . "] Duration: {$elapsed}ms";

file_put_contents(
    __DIR__ . '/../storage/logs/cron.log',
    implode("\n", $log) . "\n",
    FILE_APPEND | LOCK_EX
);

if (isset($_GET['debug'])) {
    echo json_encode(['log' => $log, 'result' => $result], JSON_PRETTY_PRINT);
} else {
    echo json_encode($result);
}

function process_withdrawals(): array
{
    global $db;

    $autoCompleteHours = (int)($_ENV['WITHDRAWAL_AUTO_COMPLETE_HOURS'] ?? 48);

    $cutoff = date('Y-m-d H:i:s', time() - ($autoCompleteHours * 3600));

    $stmt = $db->prepare("
        UPDATE withdrawals
        SET status = 'completed',
            completed_at = NOW(),
            updated_at = NOW()
        WHERE status = 'pending'
          AND payment_method != 'crypto'
          AND created_at <= ?
    ");
    $stmt->execute([$cutoff]);
    $autoCompleted = $stmt->rowCount();

    $stmt = $db->prepare("
        UPDATE withdrawals
        SET status = 'failed',
            failure_reason = 'Auto-cancelled: processing time exceeded',
            updated_at = NOW()
        WHERE status = 'pending'
          AND created_at <= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute();
    $autoFailed = $stmt->rowCount();

    return [
        'success' => true,
        'auto_completed' => $autoCompleted,
        'auto_failed' => $autoFailed
    ];
}

function reset_faucet(): array
{
    global $db;

    $stmt = $db->prepare("UPDATE user_faucet_claims SET today_claims = 0, last_reset = NOW() WHERE DATE(last_reset) < CURDATE()");
    $stmt->execute();
    $reset = $stmt->rowCount();

    $stmt = $db->prepare("DELETE FROM faucet_claims WHERE DATE(claimed_at) < CURDATE()");
    $stmt->execute();
    $cleaned = $stmt->rowCount();

    return [
        'success' => true,
        'claims_reset' => $reset,
        'claims_cleaned' => $cleaned
    ];
}

function reset_daily_rewards(): array
{
    global $db;

    $stmt = $db->prepare("UPDATE users SET daily_reward_claimed = 0, daily_reward_day = daily_reward_day WHERE 1=1");
    $stmt->execute();

    $stmt = $db->prepare("UPDATE daily_rewards SET claimed = 0 WHERE DATE(claimed_at) < CURDATE()");
    $stmt->execute();
    $resetCount = $stmt->rowCount();

    $stmt = $db->prepare("DELETE FROM daily_logs WHERE DATE(created_at) < CURDATE() AND type = 'reward'");
    $stmt->execute();
    $cleaned = $stmt->rowCount();

    return [
        'success' => true,
        'rewards_reset' => $resetCount,
        'logs_cleaned' => $cleaned
    ];
}

function clean_sessions(): array
{
    global $db;

    $stmt = $db->prepare("DELETE FROM sessions WHERE expires_at < NOW()");
    $stmt->execute();
    $deleted = $stmt->rowCount();

    if (function_exists('session_gc')) {
        session_gc();
    }

    $sessionPath = session_save_path() ?: sys_get_temp_dir();
    $fileCount = 0;
    foreach (glob($sessionPath . '/sess_*') as $file) {
        if (filemtime($file) < time() - 86400) {
            @unlink($file);
            $fileCount++;
        }
    }

    return [
        'success' => true,
        'db_sessions_cleaned' => $deleted,
        'file_sessions_cleaned' => $fileCount
    ];
}

function clean_login_attempts(): array
{
    global $db;

    $stmt = $db->prepare("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt->execute();
    $deleted = $stmt->rowCount();

    $stmt = $db->prepare("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    $archived = $stmt->rowCount();

    return [
        'success' => true,
        'recent_cleaned' => $deleted,
        'archived' => $archived
    ];
}

function send_notifications(): array
{
    global $db;

    $stmt = $db->prepare("
        SELECT n.*, u.email, u.push_subscription
        FROM notifications n
        JOIN users u ON n.user_id = u.id
        WHERE n.scheduled_at <= NOW()
          AND n.status = 'pending'
        LIMIT 50
    ");
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sent = 0;
    $failed = 0;

    foreach ($notifications as $notification) {
        try {
            if ($notification['channel'] === 'email' && !empty($notification['email'])) {
                sendEmail($notification['email'], $notification['subject'], $notification['body']);
                $sent++;
            } elseif ($notification['channel'] === 'push' && !empty($notification['push_subscription'])) {
                sendPushNotification($notification['push_subscription'], $notification['title'], $notification['body']);
                $sent++;
            } elseif ($notification['channel'] === 'in_app') {
                $sent++;
            }

            $stmt = $db->prepare("UPDATE notifications SET status = 'sent', sent_at = NOW() WHERE id = ?");
            $stmt->execute([$notification['id']]);
        } catch (Exception $e) {
            $failed++;
            $stmt = $db->prepare("UPDATE notifications SET status = 'failed', error = ? WHERE id = ?");
            $stmt->execute([$e->getMessage(), $notification['id']]);
        }
    }

    return [
        'success' => true,
        'sent' => $sent,
        'failed' => $failed,
        'total_processed' => count($notifications)
    ];
}

function process_queue(): array
{
    global $db;

    $stmt = $db->prepare("
        SELECT * FROM queue
        WHERE (status = 'pending' OR (status = 'processing' AND started_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)))
          AND attempts < max_attempts
        ORDER BY priority DESC, created_at ASC
        LIMIT 20
    ");
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $processed = 0;
    $failed = 0;

    foreach ($items as $item) {
        try {
            $stmt = $db->prepare("UPDATE queue SET status = 'processing', started_at = NOW(), attempts = attempts + 1 WHERE id = ?");
            $stmt->execute([$item['id']]);

            $handler = $item['handler'];
            $payload = json_decode($item['payload'], true);

            if (function_exists($handler)) {
                $result = $handler($payload);
            } elseif (class_exists($handler)) {
                $instance = new $handler();
                $method = $item['method'] ?? 'handle';
                $result = $instance->$method($payload);
            } else {
                throw new Exception("Handler '{$handler}' not found");
            }

            $stmt = $db->prepare("
                UPDATE queue
                SET status = 'completed', completed_at = NOW(), result = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([json_encode($result), $item['id']]);
            $processed++;
        } catch (Exception $e) {
            $stmt = $db->prepare("
                UPDATE queue
                SET status = 'failed', error = ?, updated_at = NOW()
                WHERE id = ? AND attempts >= max_attempts
            ");
            $stmt->execute([$e->getMessage(), $item['id']]);

            if ($item['attempts'] < $item['max_attempts']) {
                $stmt = $db->prepare("
                    UPDATE queue
                    SET status = 'pending', error = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$e->getMessage(), $item['id']]);
            }
            $failed++;
        }
    }

    $stmt = $db->prepare("DELETE FROM queue WHERE status = 'completed' AND completed_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute();
    $cleaned = $stmt->rowCount();

    return [
        'success' => true,
        'processed' => $processed,
        'failed' => $failed,
        'cleaned_old' => $cleaned
    ];
}

function update_levels(): array
{
    global $db;

    $stmt = $db->prepare("
        SELECT l.*, l.min_earnings, l.max_earnings
        FROM levels l
        ORDER BY l.min_earnings ASC
    ");
    $stmt->execute();
    $levels = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("
        SELECT u.id, u.total_earned, u.level_id
        FROM users u
        WHERE u.status = 'active'
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $updated = 0;

    foreach ($users as $user) {
        $newLevelId = null;
        foreach ($levels as $level) {
            if ($user['total_earned'] >= $level['min_earnings']) {
                $newLevelId = $level['id'];
            } else {
                break;
            }
        }

        if ($newLevelId && $newLevelId != $user['level_id']) {
            $stmt = $db->prepare("UPDATE users SET level_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newLevelId, $user['id']]);
            $updated++;
        }
    }

    return [
        'success' => true,
        'users_checked' => count($users),
        'levels_updated' => $updated
    ];
}

function process_referrals(): array
{
    global $db;

    $stmt = $db->prepare("
        SELECT r.id, r.referrer_id, r.referred_id, r.bonus, r.status, u.total_earned
        FROM referrals r
        JOIN users u ON r.referred_id = u.id
        WHERE r.status = 'pending'
          AND r.bonus IS NULL
    ");
    $stmt->execute();
    $referrals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $commissionRate = (float)($_ENV['REFERRAL_COMMISSION_RATE'] ?? 0.10);
    $minEarningsForBonus = (float)($_ENV['REFERRAL_MIN_EARNINGS'] ?? 5.0);
    $bonusAmount = (float)($_ENV['REFERRAL_BONUS_AMOUNT'] ?? 1.0);

    $processed = 0;

    foreach ($referrals as $referral) {
        try {
            $db->beginTransaction();

            if ($referral['total_earned'] >= $minEarningsForBonus) {
                $stmt = $db->prepare("UPDATE referrals SET status = 'qualified', bonus = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$bonusAmount, $referral['id']]);

                $stmt = $db->prepare("UPDATE users SET balance = balance + ?, total_earned = total_earned + ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$bonusAmount, $bonusAmount, $referral['referrer_id']]);

                $stmt = $db->prepare("
                    INSERT INTO earnings (user_id, amount, type, description, status, created_at)
                    VALUES (?, ?, 'referral_bonus', 'Referral commission bonus', 'completed', NOW())
                ");
                $stmt->execute([$referral['referrer_id'], $bonusAmount]);

                $processed++;
            }

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            logError("Referral processing error: " . $e->getMessage());
        }
    }

    $stmt = $db->prepare("
        SELECT r.id, r.referrer_id, r.referred_id, r.bonus, u.total_earned
        FROM referrals r
        JOIN users u ON r.referred_id = u.id
        WHERE r.status = 'qualified'
          AND r.bonus > 0
    ");
    $stmt->execute();
    $qualified = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $commissionsPaid = 0;

    foreach ($qualified as $referral) {
        try {
            $db->beginTransaction();

            $earnings = $referral['total_earned'];
            $commission = round($earnings * $commissionRate, 2);

            if ($commission > 0) {
                $stmt = $db->prepare("UPDATE referrals SET commission = ?, commission_paid_at = NOW(), updated_at = NOW() WHERE id = ?");
                $stmt->execute([$commission, $referral['id']]);

                $stmt = $db->prepare("UPDATE users SET balance = balance + ?, total_earned = total_earned + ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$commission, $commission, $referral['referrer_id']]);

                $stmt = $db->prepare("
                    INSERT INTO earnings (user_id, amount, type, description, status, created_at)
                    VALUES (?, ?, 'referral_commission', 'Referral earnings commission', 'completed', NOW())
                ");
                $stmt->execute([$referral['referrer_id'], $commission]);

                $commissionsPaid++;
            }

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            logError("Referral commission error: " . $e->getMessage());
        }
    }

    return [
        'success' => true,
        'new_referrals_processed' => $processed,
        'commissions_paid' => $commissionsPaid
    ];
}

function backup_database(): array
{
    $dbName = $_ENV['DB_NAME'] ?? 'zynearn';
    $dbUser = $_ENV['DB_USER'] ?? 'root';
    $dbPass = $_ENV['DB_PASS'] ?? '';
    $dbHost = $_ENV['DB_HOST'] ?? 'localhost';

    $backupDir = __DIR__ . '/../storage/backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }

    $date = date('Y-m-d-H-i-s');
    $filename = "backup-{$date}.sql.gz";
    $filepath = $backupDir . '/' . $filename;

    $retentionDays = (int)($_ENV['BACKUP_RETENTION_DAYS'] ?? 7);

    if (PHP_OS_FAMILY === 'Windows') {
        $command = sprintf(
            '"%s" -h%s -u%s -p%s %s | "%s" -c > %s',
            'mysqldump',
            $dbHost,
            $dbUser,
            $dbPass,
            $dbName,
            'gzip',
            $filepath
        );
    } else {
        $command = sprintf(
            'mysqldump -h%s -u%s -p%s %s | gzip > %s',
            escapeshellarg($dbHost),
            escapeshellarg($dbUser),
            escapeshellarg($dbPass),
            escapeshellarg($dbName),
            escapeshellarg($filepath)
        );
    }

    $output = [];
    $returnCode = 0;
    exec($command, $output, $returnCode);

    if ($returnCode !== 0) {
        $db = getDbConnection();
        $stmt = $db->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $sql = "-- ZynEarn Database Backup\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";

        foreach ($tables as $table) {
            $stmt = $db->query("SHOW CREATE TABLE `{$table}`");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $sql .= $row['Create Table'] . ";\n\n";

            $stmt = $db->query("SELECT * FROM `{$table}`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $values = array_map(function($v) use ($db) {
                    return $v === null ? 'NULL' : $db->quote($v);
                }, array_values($row));
                $sql .= "INSERT INTO `{$table}` VALUES (" . implode(',', $values) . ");\n";
            }
            $sql .= "\n";
        }

        file_put_contents($filepath, gzencode($sql));
    }

    $backupSize = filesize($filepath);

    $files = glob($backupDir . '/backup-*.sql.gz');
    usort($files, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });

    $deleted = 0;
    foreach ($files as $file) {
        if (count($files) - $deleted <= 3) break;
        if (filemtime($file) < time() - ($retentionDays * 86400)) {
            @unlink($file);
            $deleted++;
        }
    }

    return [
        'success' => true,
        'filename' => $filename,
        'size' => $backupSize,
        'old_backups_deleted' => $deleted
    ];
}

function clean_logs(): array
{
    $logRetentionDays = (int)($_ENV['LOG_RETENTION_DAYS'] ?? 30);
    $cutoff = date('Y-m-d H:i:s', time() - ($logRetentionDays * 86400));

    $cleaned = [];

    $logTables = [
        'activity_logs' => 'created_at',
        'error_logs' => 'created_at',
        'api_logs' => 'created_at',
        'email_logs' => 'sent_at',
        'notification_logs' => 'created_at',
        'withdrawal_logs' => 'created_at',
        'earnings_logs' => 'created_at'
    ];

    try {
        $db = getDbConnection();

        foreach ($logTables as $table => $dateColumn) {
            try {
                $stmt = $db->prepare("DELETE FROM {$table} WHERE {$dateColumn} < ?");
                $stmt->execute([$cutoff]);
                $cleaned[$table] = $stmt->rowCount();
            } catch (PDOException $e) {
                $cleaned[$table] = 0;
            }
        }
    } catch (Exception $e) {
        $cleaned['error'] = $e->getMessage();
    }

    $logDir = __DIR__ . '/../storage/logs';
    if (is_dir($logDir)) {
        $files = glob($logDir . '/*.log');
        $deletedFiles = 0;
        foreach ($files as $file) {
            if (filemtime($file) < time() - ($logRetentionDays * 86400)) {
                @unlink($file);
                $deletedFiles++;
            }
        }
        $cleaned['log_files'] = $deletedFiles;
    }

    return [
        'success' => true,
        'tables_cleaned' => $cleaned
    ];
}

function daily_reset(): array
{
    $results = [];

    $results['faucet'] = reset_faucet();
    $results['daily_rewards'] = reset_daily_rewards();
    $results['sessions'] = clean_sessions();
    $results['login_attempts'] = clean_login_attempts();

    return [
        'success' => true,
        'daily_reset_completed' => date('Y-m-d H:i:s'),
        'details' => $results
    ];
}
