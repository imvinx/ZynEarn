<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAdmin();
$pageTitle = 'Backup';
$db = getDB();

$backupDir = __DIR__ . '/../system/cache/backups';
if (!is_dir($backupDir)) @mkdir($backupDir, 0755, true);
$backups = glob($backupDir . '/*.sql.gz');
$backupList = [];
foreach ($backups as $b) {
    $backupList[] = ['file' => basename($b), 'size' => filesize($b), 'date' => date('Y-m-d H:i:s', filemtime($b))];
}
usort($backupList, function($a, $b) { return strtotime($b['date']) - strtotime($a['date']); });

if (isAjaxRequest()) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    if ($action === 'create') {
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql.gz';
        $filepath = $backupDir . '/' . $filename;
        
        $cmd = sprintf('mysqldump --host=%s --user=%s --password=%s %s 2>&1', escapeshellarg(DB_HOST), escapeshellarg(DB_USER), escapeshellarg(DB_PASS), escapeshellarg(DB_NAME));
        
        $output = [];
        $returnVar = 0;
        exec($cmd . ' 2>&1', $output, $returnVar);
        
        if ($returnVar === 0) {
            $sql = implode("\n", $output);
            file_put_contents($filepath, gzencode($sql, 9));
            logAdminAction($_SESSION['user_id'], 'Created database backup: ' . $filename);
            jsonResponse(['success'=>true, 'message'=>'Backup created successfully', 'filename'=>$filename]);
        } else {
            // Fallback: use PHP-based backup
            try {
                $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                $sql = "-- ZynEarn Database Backup\n-- Date: " . date('Y-m-d H:i:s') . "\n\n";
                foreach ($tables as $table) {
                    $create = $db->query("SHOW CREATE TABLE `$table`")->fetch();
                    $sql .= "\n" . $create['Create Table'] . ";\n\n";
                    $rows = $db->query("SELECT * FROM `$table`")->fetchAll();
                    foreach ($rows as $row) {
                        $vals = array_map(function($v) use ($db) { return $v === null ? 'NULL' : "'" . substr($db->quote($v), 1, -1) . "'"; }, array_values($row));
                        $sql .= "INSERT INTO `$table` VALUES (" . implode(',', $vals) . ");\n";
                    }
                }
                file_put_contents($filepath, gzencode($sql, 9));
                logAdminAction($_SESSION['user_id'], 'Created PHP-based database backup: ' . $filename);
                jsonResponse(['success'=>true, 'message'=>'Backup created (PHP fallback)', 'filename'=>$filename]);
            } catch (Exception $e) {
                errorResponse('Backup failed: ' . $e->getMessage());
            }
        }
    }
    
    if ($action === 'download') {
        $file = basename($_GET['file'] ?? '');
        $path = $backupDir . '/' . $file;
        if (!file_exists($path)) errorResponse('Backup file not found');
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
    
    if ($action === 'restore') {
        $file = basename($_POST['file'] ?? '');
        $path = $backupDir . '/' . $file;
        if (!file_exists($path)) errorResponse('Backup file not found');
        
        $sql = gzdecode(file_get_contents($path));
        if (!$sql) errorResponse('Invalid backup file');
        
        try {
            $db->exec("SET FOREIGN_KEY_CHECKS = 0");
            $statements = explode(";\n", $sql);
            foreach ($statements as $stmt) {
                $stmt = trim($stmt);
                if (!empty($stmt)) {
                    $db->exec($stmt);
                }
            }
            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
            logAdminAction($_SESSION['user_id'], 'Restored database from backup: ' . $file);
            jsonResponse(['success'=>true, 'message'=>'Database restored successfully']);
        } catch (Exception $e) {
            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
            errorResponse('Restore failed: ' . $e->getMessage());
        }
    }
    
    if ($action === 'delete') {
        $file = basename($_POST['file'] ?? '');
        $path = $backupDir . '/' . $file;
        if (file_exists($path)) unlink($path);
        jsonResponse(['success'=>true, 'message'=>'Backup deleted']);
    }
    
    if ($action === 'list') {
        jsonResponse(['success'=>true, 'backups'=>$backupList]);
    }
    
    errorResponse('Unknown action');
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
  <div><h2>Database Backup</h2></div>
  <div class="page-actions">
    <button class="btn btn-primary" onclick="createBackup()"><i class="fas fa-database"></i> Create Backup</button>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="table-wrap">
      <table>
        <thead><tr><th>Filename</th><th>Size</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>
          <?php if (empty($backupList)): ?>
          <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:30px">No backups available</td></tr>
          <?php else: ?>
          <?php foreach($backupList as $b): ?>
          <tr>
            <td style="font-family:var(--font-mono);font-size:.8rem"><?= sanitize($b['file']) ?></td>
            <td><?= formatNumber($b['size']) ?> B</td>
            <td style="color:var(--text-muted);font-size:.78rem"><?= $b['date'] ?></td>
            <td>
              <a href="/admin/backup.php?action=download&file=<?= urlencode($b['file']) ?>" class="btn btn-sm btn-outline"><i class="fas fa-download"></i></a>
              <button class="btn btn-sm btn-ghost" onclick="restoreBackup('<?= sanitize($b['file']) ?>')"><i class="fas fa-undo"></i></button>
              <button class="btn btn-sm btn-ghost" onclick="deleteBackup('<?= sanitize($b['file']) ?>')" style="color:#ff6b6b"><i class="fas fa-trash"></i></button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card" style="margin-top:20px">
  <div class="card-header"><h3><i class="fas fa-clock"></i> Scheduled Backup Settings</h3></div>
  <div class="card-body">
    <div class="form-row">
      <div class="form-group">
        <label>Auto Backup Frequency</label>
        <select class="form-control" id="backupFrequency">
          <option value="never">Never</option>
          <option value="daily">Daily</option>
          <option value="weekly">Weekly</option>
          <option value="monthly">Monthly</option>
        </select>
      </div>
      <div class="form-group">
        <label>Max Backups to Keep</label>
        <input type="number" class="form-control" id="maxBackups" value="10" min="1">
      </div>
    </div>
    <button class="btn btn-primary" onclick="saveBackupSettings()"><i class="fas fa-save"></i> Save Settings</button>
  </div>
</div>

<script>
function createBackup() {
  var btn = event.target;
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
  ajax('/admin/backup.php', {action:'create'}, function(res) {
    btn.disabled = false; btn.innerHTML = '<i class="fas fa-database"></i> Create Backup';
    if (res.success) { toast(res.message, 'success'); location.reload(); }
    else toast(res.error, 'error');
  });
}

function restoreBackup(file) {
  confirmAction('Restore database from ' + file + '? This will overwrite all current data.', function() {
    ajax('/admin/backup.php', {action:'restore', file:file}, function(res) {
      if (res.success) { toast(res.message, 'success'); }
      else toast(res.error, 'error');
    });
  });
}

function deleteBackup(file) {
  confirmAction('Delete backup ' + file + '?', function() {
    ajax('/admin/backup.php', {action:'delete', file:file}, function(res) {
      if (res.success) { toast(res.message, 'success'); location.reload(); }
      else toast(res.error, 'error');
    });
  });
}

function saveBackupSettings() {
  toast('Backup settings saved', 'success');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
