<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAdmin();
$pageTitle = 'Maintenance';
$db = getDB();

$maintenanceMode = getSetting('maintenance_mode', '0');
$cacheSize = 0;
$cacheFiles = glob(CACHE_DIR . '/*');
foreach ($cacheFiles as $f) { if (is_file($f)) $cacheSize += filesize($f); }

$logFiles = glob(__DIR__ . '/../system/logs/*.log');
$logSizes = [];
foreach ($logFiles as $lf) {
    $logSizes[] = ['file' => basename($lf), 'size' => filesize($lf), 'date' => date('Y-m-d H:i:s', filemtime($lf))];
}
rsort($logSizes);

if (isAjaxRequest()) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    if ($action === 'toggle_maintenance') {
        $newVal = $_POST['value'] === '1' ? '1' : '0';
        updateSetting('maintenance_mode', $newVal);
        logAdminAction($_SESSION['user_id'], ($newVal === '1' ? 'Enabled' : 'Disabled') . ' maintenance mode');
        jsonResponse(['success'=>true, 'message'=>'Maintenance mode ' . ($newVal === '1' ? 'enabled' : 'disabled')]);
    }
    
    if ($action === 'clear_cache') {
        $count = 0;
        foreach ($cacheFiles as $f) {
            if (is_file($f)) { unlink($f); $count++; }
        }
        logAdminAction($_SESSION['user_id'], 'Cleared cache (' . $count . ' files)');
        jsonResponse(['success'=>true, 'message'=>"Cache cleared ($count files deleted)"]);
    }
    
    if ($action === 'view_log') {
        $logFile = basename($_POST['file'] ?? '');
        $path = __DIR__ . '/../system/logs/' . $logFile;
        if (!file_exists($path)) errorResponse('Log file not found');
        $content = file_get_contents($path);
        $lines = explode("\n", $content);
        $html = '';
        foreach (array_slice($lines, -200) as $line) {
            $cls = '';
            if (stripos($line, 'error') !== false) $cls = 'style="color:#ff6b6b"';
            elseif (stripos($line, 'warning') !== false) $cls = 'style="color:var(--accent2)"';
            $html .= '<div ' . $cls . '>' . sanitize($line) . '</div>';
        }
        jsonResponse(['success'=>true, 'html'=>$html, 'file'=>$logFile]);
    }
    
    if ($action === 'clear_log') {
        $logFile = basename($_POST['file'] ?? '');
        $path = __DIR__ . '/../system/logs/' . $logFile;
        if (file_exists($path)) file_put_contents($path, '');
        jsonResponse(['success'=>true, 'message'=>'Log cleared']);
    }
    
    errorResponse('Unknown action');
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header"><div><h2>Maintenance</h2></div></div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">
  <div class="card">
    <div class="card-header"><h3><i class="fas fa-power-off"></i> Maintenance Mode</h3></div>
    <div class="card-body">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
        <div>
          <div style="font-weight:600;margin-bottom:4px">Maintenance Mode</div>
          <div style="color:var(--text-secondary);font-size:.82rem">When enabled, only admins can access the site</div>
        </div>
        <label class="form-switch">
          <input type="checkbox" id="maintenanceToggle" <?= $maintenanceMode === '1' ? 'checked' : '' ?> onchange="toggleMaintenance()">
          <span class="slider"></span>
        </label>
      </div>
      <div style="background:var(--dark-bg);padding:12px 16px;border-radius:var(--radius-sm)">
        <div style="display:flex;align-items:center;gap:8px">
          <span class="status-dot <?= $maintenanceMode === '1' ? 'pending' : 'active' ?>"></span>
          <span style="font-size:.84rem">Currently: <strong><?= $maintenanceMode === '1' ? 'Under Maintenance' : 'Live' ?></strong></span>
        </div>
      </div>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><h3><i class="fas fa-trash-alt"></i> Cache Management</h3></div>
    <div class="card-body">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
        <div>
          <div style="font-weight:600;margin-bottom:4px">Cache Size</div>
          <div style="color:var(--text-secondary);font-size:.82rem"><?= count($cacheFiles) ?> files, <?= formatNumber($cacheSize) ?> bytes</div>
        </div>
        <button class="btn btn-warning" onclick="clearCache()"><i class="fas fa-broom"></i> Clear Cache</button>
      </div>
      <div class="alert alert-info"><i class="fas fa-info-circle"></i> Clearing cache may temporarily increase load times as data is regenerated.</div>
    </div>
  </div>
</div>

<div class="card" style="margin-bottom:24px">
  <div class="card-header"><h3><i class="fas fa-file-alt"></i> System Logs</h3></div>
  <div class="card-body">
    <?php if (!empty($logSizes)): ?>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px">
      <?php foreach ($logSizes as $log): ?>
      <button class="btn btn-sm btn-outline" onclick="viewLog('<?= sanitize($log['file']) ?>')"><i class="fas fa-file"></i> <?= sanitize($log['file']) ?> (<?= formatNumber($log['size']) ?>B)</button>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p style="color:var(--text-muted)">No log files found.</p>
    <?php endif; ?>
    <div id="logViewer" style="background:var(--dark-bg);padding:16px;border-radius:var(--radius-sm);font-family:var(--font-mono);font-size:.75rem;line-height:1.6;max-height:400px;overflow-y:auto;white-space:pre-wrap;display:none">
      <div style="display:flex;justify-content:space-between;margin-bottom:10px;position:sticky;top:0;background:var(--dark-bg);padding-bottom:8px">
        <strong id="logFileName" style="font-size:.8rem"></strong>
        <div>
          <button class="btn btn-sm btn-ghost" onclick="document.getElementById('logViewer').style.display='none'"><i class="fas fa-times"></i> Close</button>
        </div>
      </div>
      <div id="logContent"></div>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
  <div class="card">
    <div class="card-header"><h3><i class="fab fa-php"></i> PHP Info</h3></div>
    <div class="card-body">
      <table>
        <tbody>
          <tr><td style="color:var(--text-secondary)">PHP Version</td><td><span class="badge badge-info"><?= PHP_VERSION ?></span></td></tr>
          <tr><td style="color:var(--text-secondary)">Server API</td><td><?= PHP_SAPI ?></td></tr>
          <tr><td style="color:var(--text-secondary)">Memory Limit</td><td><?= ini_get('memory_limit') ?></td></tr>
          <tr><td style="color:var(--text-secondary)">Max Upload Size</td><td><?= ini_get('upload_max_filesize') ?></td></tr>
          <tr><td style="color:var(--text-secondary)">Max Execution Time</td><td><?= ini_get('max_execution_time') ?>s</td></tr>
          <tr><td style="color:var(--text-secondary)">PDO Driver</td><td>MySQL (mysql)</td></tr>
          <tr><td style="color:var(--text-secondary)">Database</td><td><?= DB_NAME ?></td></tr>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><h3><i class="fas fa-server"></i> Server Status</h3></div>
    <div class="card-body">
      <table>
        <tbody>
          <tr><td style="color:var(--text-secondary)">Server Time</td><td style="font-family:var(--font-mono)"><?= date('Y-m-d H:i:s') ?></td></tr>
          <tr><td style="color:var(--text-secondary)">Server Timezone</td><td><?= date_default_timezone_get() ?></td></tr>
          <tr><td style="color:var(--text-secondary)">Disk Free Space</td><td><?php $disk = disk_free_space(__DIR__); echo formatNumber($disk) . ' bytes'; ?></td></tr>
          <tr><td style="color:var(--text-secondary)">Disk Total Space</td><td><?php $total = disk_total_space(__DIR__); echo formatNumber($total) . ' bytes'; ?></td></tr>
          <tr><td style="color:var(--text-secondary)">Disk Usage</td><td><?= $total > 0 ? round(($total - $disk) / $total * 100, 1) : 0 ?>%</td></tr>
          <tr><td style="color:var(--text-secondary)">Cache Writable</td><td><span class="badge badge-<?= is_writable(CACHE_DIR) ? 'success' : 'danger' ?>"><?= is_writable(CACHE_DIR) ? 'Yes' : 'No' ?></span></td></tr>
          <tr><td style="color:var(--text-secondary)">Uploads Writable</td><td><span class="badge badge-<?= is_writable(UPLOAD_DIR) ? 'success' : 'danger' ?>"><?= is_writable(UPLOAD_DIR) ? 'Yes' : 'No' ?></span></td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function toggleMaintenance() {
  var val = document.getElementById('maintenanceToggle').checked ? '1' : '0';
  ajax('/admin/maintenance.php', {action:'toggle_maintenance', value:val}, function(res) {
    if (res.success) toast(res.message, 'success');
    else toast(res.error, 'error');
  });
}

function clearCache() {
  confirmAction('Clear all cached files?', function() {
    ajax('/admin/maintenance.php', {action:'clear_cache'}, function(res) {
      if (res.success) { toast(res.message, 'success'); location.reload(); }
      else toast(res.error, 'error');
    });
  });
}

function viewLog(file) {
  ajax('/admin/maintenance.php', {action:'view_log', file:file}, function(res) {
    if (res.success) {
      document.getElementById('logFileName').textContent = res.file;
      document.getElementById('logContent').innerHTML = res.html;
      document.getElementById('logViewer').style.display = 'block';
    } else toast(res.error, 'error');
  });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
