<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAdmin();
$pageTitle = 'Security';
$db = getDB();

$loginAttempts = $db->query("SELECT * FROM login_attempts ORDER BY attempted_at DESC LIMIT 50")->fetchAll();
$blockedIps = $db->query("SELECT ip_address, COUNT(*) as attempts, MAX(attempted_at) as last_attempt FROM login_attempts WHERE success=0 AND attempted_at > DATE_SUB(NOW(), INTERVAL 1 DAY) GROUP BY ip_address HAVING attempts >= 5 ORDER BY attempts DESC")->fetchAll();
$adminLogs = $db->query("SELECT al.*, u.username FROM admin_logs al JOIN users u ON al.admin_id=u.id ORDER BY al.created_at DESC LIMIT 50")->fetchAll();
$suspicious = $db->query("SELECT ip_address, COUNT(*) as attempts FROM login_attempts WHERE attempted_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) AND success=0 GROUP BY ip_address HAVING attempts > 10")->fetchAll();

if (isAjaxRequest()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'block_ip') {
        $ip = $_POST['ip'] ?? '';
        if ($ip) {
            updateSetting('blocked_ip_' . md5($ip), $ip);
            logAdminAction($_SESSION['user_id'], 'Blocked IP: ' . $ip);
            jsonResponse(['success'=>true,'message'=>'IP blocked']);
        }
        errorResponse('Invalid IP');
    }
    if ($action === 'unblock_ip') {
        $ip = $_POST['ip'] ?? '';
        if ($ip) {
            updateSetting('blocked_ip_' . md5($ip), '');
            jsonResponse(['success'=>true,'message'=>'IP unblocked']);
        }
        errorResponse('Invalid IP');
    }
    errorResponse('Unknown action');
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header"><div><h2>Security Dashboard</h2></div></div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">
  <div class="card">
    <div class="card-header"><h3><i class="fas fa-exclamation-triangle" style="color:var(--accent2)"></i> Suspicious Activity (Last Hour)</h3></div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap"><table><thead><tr><th>IP Address</th><th>Failed Attempts</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach($suspicious as $s): ?>
          <tr><td style="font-family:var(--font-mono)"><?= sanitize($s['ip_address']) ?></td><td><span class="badge badge-danger"><?= $s['attempts'] ?></span></td><td><button class="btn btn-sm btn-danger" onclick="blockIP('<?= sanitize($s['ip_address']) ?>')"><i class="fas fa-ban"></i> Block</button></td></tr>
          <?php endforeach; ?>
          <?php if(empty($suspicious)): ?><tr><td colspan="3" style="text-align:center;color:var(--text-muted);padding:30px">No suspicious activity detected</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><h3><i class="fas fa-ban" style="color:#ff6b6b"></i> Blocked IPs</h3></div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap"><table><thead><tr><th>IP Address</th><th>Attempts</th><th>Last Attempt</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach($blockedIps as $b): ?>
          <tr><td style="font-family:var(--font-mono)"><?= sanitize($b['ip_address']) ?></td><td><span class="badge badge-danger"><?= $b['attempts'] ?></span></td><td style="color:var(--text-muted);font-size:.78rem"><?= timeAgo($b['last_attempt']) ?></td><td><button class="btn btn-sm btn-success" onclick="unblockIP('<?= sanitize($b['ip_address']) ?>')"><i class="fas fa-check"></i> Unblock</button></td></tr>
          <?php endforeach; ?>
          <?php if(empty($blockedIps)): ?><tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:30px">No blocked IPs</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card" style="margin-bottom:24px">
  <div class="card-header"><h3><i class="fas fa-history"></i> Login Attempts</h3></div>
  <div class="card-body" style="padding:0">
    <div class="table-wrap"><table><thead><tr><th>IP</th><th>Username</th><th>Time</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach($loginAttempts as $l): ?>
        <tr><td style="font-family:var(--font-mono)"><?= sanitize($l['ip_address']) ?></td><td><?= sanitize($l['username']??'N/A') ?></td><td style="color:var(--text-muted);font-size:.78rem"><?= timeAgo($l['attempted_at']) ?></td><td><span class="badge badge-<?= $l['success']?'success':'danger' ?>"><?= $l['success']?'Success':'Failed' ?></span></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-header"><h3><i class="fas fa-clipboard-list"></i> Admin Action Logs</h3></div>
  <div class="card-body" style="padding:0">
    <div class="table-wrap"><table><thead><tr><th>Admin</th><th>Action</th><th>IP</th><th>Time</th></tr></thead>
      <tbody>
        <?php foreach($adminLogs as $log): ?>
        <tr><td><strong><?= sanitize($log['username']) ?></strong></td><td><?= sanitize($log['action']) ?></td><td style="font-family:var(--font-mono);font-size:.78rem"><?= sanitize($log['ip_address']??'N/A') ?></td><td style="color:var(--text-muted);font-size:.78rem"><?= timeAgo($log['created_at']) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function blockIP(ip) {
  confirmAction('Block IP: ' + ip + '?', function() {
    ajax('/admin/security.php', {action:'block_ip', ip:ip}, function(res) {
      if (res.success) { toast(res.message, 'success'); location.reload(); }
      else toast(res.error, 'error');
    });
  });
}

function unblockIP(ip) {
  confirmAction('Unblock IP: ' + ip + '?', function() {
    ajax('/admin/security.php', {action:'unblock_ip', ip:ip}, function(res) {
      if (res.success) { toast(res.message, 'success'); location.reload(); }
      else toast(res.error, 'error');
    });
  });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
