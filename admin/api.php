<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAdmin();
$pageTitle = 'API';
$db = getDB();

// Check for api_keys table, create if missing
try {
    $db->query("SELECT 1 FROM api_keys LIMIT 1");
} catch (Exception $e) {
    $db->exec("CREATE TABLE IF NOT EXISTS `api_keys` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` BIGINT UNSIGNED DEFAULT NULL,
        `name` VARCHAR(255) NOT NULL,
        `api_key` VARCHAR(255) NOT NULL,
        `permissions` JSON DEFAULT NULL,
        `status` ENUM('active','revoked') NOT NULL DEFAULT 'active',
        `last_used_at` DATETIME DEFAULT NULL,
        `expires_at` DATETIME DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_api_keys_key` (`api_key`),
        KEY `idx_api_keys_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

if (isAjaxRequest()) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    if ($action === 'list') {
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = 20; $offset = ($page-1)*$limit;
        $total = $db->query("SELECT COUNT(*) FROM api_keys")->fetchColumn();
        $rows = $db->query("SELECT * FROM api_keys ORDER BY id DESC LIMIT $limit OFFSET $offset")->fetchAll();
        $html = '';
        foreach ($rows as $r) {
            $html .= '<tr>
                <td>'.$r['id'].'</td>
                <td>'.sanitize($r['name']).'</td>
                <td style="font-family:var(--font-mono);font-size:.78rem"><code>'.substr($r['api_key'],0,20).'...'.substr($r['api_key'],-8).'</code></td>
                <td><span class="badge badge-'.($r['status']==='active'?'success':'danger').'">'.$r['status'].'</span></td>
                <td style="color:var(--text-muted);font-size:.78rem">'.($r['last_used_at']?timeAgo($r['last_used_at']):'Never').'</td>
                <td style="color:var(--text-muted);font-size:.78rem">'.timeAgo($r['created_at']).'</td>
                <td>
                    <button class="btn btn-sm btn-ghost" onclick="copyKey(\''.$r['api_key'].'\')"><i class="fas fa-copy"></i></button>
                    <button class="btn btn-sm btn-'.($r['status']==='active'?'warning':'success').'" onclick="toggleKey('.$r['id'].')"><i class="fas fa-'.($r['status']==='active'?'times':'check').'"></i></button>
                    <button class="btn btn-sm btn-ghost" onclick="deleteKey('.$r['id'].')" style="color:#ff6b6b"><i class="fas fa-trash"></i></button>
                </td>
            </tr>';
        }
        jsonResponse(['success'=>true,'html'=>$html,'total'=>$total,'pages'=>ceil($total/$limit)]);
    }
    
    if ($action === 'generate') {
        $name = $_POST['name'] ?? 'API Key';
        $permissions = ['read','write'];
        $key = 'zyn_' . bin2hex(random_bytes(32));
        $stmt = $db->prepare("INSERT INTO api_keys (name, api_key, permissions, status) VALUES (?, ?, ?, 'active')");
        $stmt->execute([$name, $key, json_encode($permissions)]);
        logAdminAction($_SESSION['user_id'], 'Generated API key: ' . $name);
        jsonResponse(['success'=>true, 'message'=>'API key generated', 'key'=>$key]);
    }
    
    if ($action === 'toggle') {
        $id = intval($_POST['id'] ?? 0);
        $db->prepare("UPDATE api_keys SET status = IF(status='active','revoked','active') WHERE id=?")->execute([$id]);
        jsonResponse(['success'=>true, 'message'=>'API key toggled']);
    }
    
    if ($action === 'delete') {
        $db->prepare("DELETE FROM api_keys WHERE id=?")->execute([intval($_POST['id'])]);
        jsonResponse(['success'=>true, 'message'=>'API key deleted']);
    }
    
    if ($action === 'stats') {
        $total = $db->query("SELECT COUNT(*) FROM api_keys")->fetchColumn();
        $active = $db->query("SELECT COUNT(*) FROM api_keys WHERE status='active'")->fetchColumn();
        $revoked = $total - $active;
        jsonResponse(['success'=>true, 'total'=>$total, 'active'=>$active, 'revoked'=>$revoked]);
    }
    
    errorResponse('Unknown action');
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
  <div><h2>API Management</h2></div>
  <div class="page-actions">
    <button class="btn btn-primary" onclick="generateKey()"><i class="fas fa-key"></i> Generate Key</button>
  </div>
</div>

<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px">
  <div class="stat-card"><div class="stat-icon purple"><i class="fas fa-key"></i></div><div class="stat-info"><div class="stat-label">Total Keys</div><div class="stat-value" id="statTotal">0</div></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fas fa-check-circle"></i></div><div class="stat-info"><div class="stat-label">Active Keys</div><div class="stat-value" id="statActive">0</div></div></div>
  <div class="stat-card"><div class="stat-icon red"><i class="fas fa-times-circle"></i></div><div class="stat-info"><div class="stat-label">Revoked Keys</div><div class="stat-value" id="statRevoked">0</div></div></div>
</div>

<div class="card">
  <div class="card-body">
    <div class="table-wrap">
      <table>
        <thead><tr><th>ID</th><th>Name</th><th>Key</th><th>Status</th><th>Last Used</th><th>Created</th><th>Actions</th></tr></thead>
        <tbody id="apiKeysBody"></tbody>
      </table>
    </div>
    <div class="pagination" id="apiPagination"></div>
  </div>
</div>

<div class="card" style="margin-top:20px">
  <div class="card-header"><h3><i class="fas fa-book"></i> API Documentation</h3></div>
  <div class="card-body">
    <div style="background:var(--dark-bg);padding:16px;border-radius:var(--radius-sm);font-family:var(--font-mono);font-size:.8rem;line-height:1.8;overflow-x:auto">
      <div style="color:var(--text-muted);margin-bottom:12px">// Base URL: <?= APP_URL ?>/api/v1</div>
      <div><span style="color:var(--secondary)">GET</span> <span style="color:var(--accent2)">/users</span> <span style="color:var(--text-muted)">- List users</span></div>
      <div><span style="color:var(--secondary)">GET</span> <span style="color:var(--accent2)">/users/{id}</span> <span style="color:var(--text-muted)">- Get user details</span></div>
      <div><span style="color:var(--accent2)">POST</span> <span style="color:var(--accent2)">/earnings</span> <span style="color:var(--text-muted)">- Add earnings</span></div>
      <div><span style="color:var(--accent2)">GET</span> <span style="color:var(--accent2)">/withdrawals</span> <span style="color:var(--text-muted)">- List withdrawals</span></div>
      <div style="margin-top:12px;color:var(--text-muted)">// Headers: Authorization: Bearer {api_key}</div>
    </div>
  </div>
</div>

<!-- Generate Key Modal -->
<div class="modal-overlay" id="genKeyModal">
  <div class="modal modal-sm"><div class="modal-header"><h3>Generate API Key</h3><button class="modal-close" onclick="closeModal('genKeyModal')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body">
      <div class="form-group"><label>Key Name</label><input type="text" class="form-control" id="keyName" placeholder="My App" value="Production Key"></div>
      <div id="keyResult" style="display:none">
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> Key generated successfully!</div>
        <div class="form-group"><label>Your API Key (copy now - won't be shown again)</label>
          <div style="display:flex;gap:8px">
            <input type="text" class="form-control" id="generatedKey" readonly style="font-family:var(--font-mono);font-size:.75rem">
            <button class="btn btn-sm btn-outline" onclick="copyGeneratedKey()"><i class="fas fa-copy"></i></button>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-footer" id="genKeyFooter">
      <button class="btn btn-ghost" onclick="closeModal('genKeyModal')">Cancel</button>
      <button class="btn btn-primary" onclick="doGenerateKey()"><i class="fas fa-key"></i> Generate</button>
    </div>
  </div>
</div>

<script>
function loadKeys(page) {
  page = page || 1;
  ajaxGet('/admin/api.php?action=list&page=' + page, function(res) {
    if (res.success) {
      document.getElementById('apiKeysBody').innerHTML = res.html;
      var p = document.getElementById('apiPagination'); p.innerHTML = '';
      for (var i = 1; i <= res.pages; i++) {
        var a = document.createElement('a'); a.href = '#'; a.textContent = i;
        a.className = i === page ? 'active' : '';
        a.onclick = function(pi) { return function() { loadKeys(pi); return false; }; }(i); p.appendChild(a);
      }
    }
  });
}

function loadStats() {
  ajaxGet('/admin/api.php?action=stats', function(res) {
    if (res.success) {
      document.getElementById('statTotal').textContent = res.total;
      document.getElementById('statActive').textContent = res.active;
      document.getElementById('statRevoked').textContent = res.revoked;
    }
  });
}

function generateKey() {
  document.getElementById('keyName').value = 'Production Key';
  document.getElementById('generatedKey').value = '';
  document.getElementById('keyResult').style.display = 'none';
  document.getElementById('genKeyFooter').style.display = 'flex';
  openModal('genKeyModal');
}

function doGenerateKey() {
  var name = document.getElementById('keyName').value || 'API Key';
  ajax('/admin/api.php', {action:'generate', name:name}, function(res) {
    if (res.success) {
      document.getElementById('generatedKey').value = res.key;
      document.getElementById('keyResult').style.display = 'block';
      document.getElementById('genKeyFooter').style.display = 'none';
      toast(res.message, 'success');
      loadKeys(1);
      loadStats();
    } else toast(res.error, 'error');
  });
}

function copyGeneratedKey() {
  var inp = document.getElementById('generatedKey');
  inp.select(); document.execCommand('copy');
  toast('Key copied to clipboard', 'success');
}

function copyKey(key) {
  var ta = document.createElement('textarea');
  ta.value = key; document.body.appendChild(ta);
  ta.select(); document.execCommand('copy');
  ta.remove();
  toast('Key copied', 'success');
}

function toggleKey(id) {
  ajax('/admin/api.php', {action:'toggle', id:id}, function(res) {
    if (res.success) { toast(res.message, 'success'); loadKeys(1); loadStats(); }
    else toast(res.error, 'error');
  });
}

function deleteKey(id) {
  confirmAction('Delete this API key?', function() {
    ajax('/admin/api.php', {action:'delete', id:id}, function(res) {
      if (res.success) { toast(res.message, 'success'); loadKeys(1); loadStats(); }
      else toast(res.error, 'error');
    });
  });
}

loadKeys(1);
loadStats();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
