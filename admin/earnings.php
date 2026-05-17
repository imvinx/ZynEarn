<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAdmin();
$pageTitle = 'Earnings';
$db = getDB();

if (isAjaxRequest()) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    if ($action === 'list') {
        $search = $_GET['search'] ?? '';
        $type = $_GET['type'] ?? '';
        $status = $_GET['status'] ?? '';
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $where = [];
        $params = [];
        if ($search) { $where[] = "u.username LIKE ?"; $params[] = "%$search%"; }
        if ($type) { $where[] = "e.type = ?"; $params[] = $type; }
        if ($status) { $where[] = "e.status = ?"; $params[] = $status; }
        if ($dateFrom) { $where[] = "e.created_at >= ?"; $params[] = $dateFrom; }
        if ($dateTo) { $where[] = "e.created_at <= ?"; $params[] = $dateTo . ' 23:59:59'; }
        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $countStmt = $db->prepare("SELECT COUNT(*) FROM earnings e JOIN users u ON e.user_id=u.id $whereClause");
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT e.*, u.username FROM earnings e JOIN users u ON e.user_id=u.id $whereClause ORDER BY e.id DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        $earnings = $stmt->fetchAll();
        
        $html = '';
        foreach ($earnings as $e) {
            $html .= '<tr>
                <td>'.$e['id'].'</td>
                <td><strong>'.sanitize($e['username']).'</strong></td>
                <td><span class="badge badge-info">'.sanitize($e['type']).'</span></td>
                <td style="font-family:var(--font-mono);color:var(--secondary)">'.formatCurrency($e['amount']).'</td>
                <td><span class="badge badge-'.($e['status']==='credited'?'success':($e['status']==='pending'?'warning':'danger')).'">'.$e['status'].'</span></td>
                <td style="color:var(--text-muted);font-size:.78rem">'.timeAgo($e['created_at']).'</td>
            </tr>';
        }
        jsonResponse(['success' => true, 'html' => $html, 'total' => $total, 'pages' => ceil($total / $limit)]);
    }
    
    if ($action === 'add') {
        $userId = intval($_POST['user_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $type = $_POST['type'] ?? 'bonus';
        $description = $_POST['description'] ?? '';
        if (!$userId || $amount <= 0) errorResponse('Invalid input');
        $stmt = $db->prepare("SELECT id FROM users WHERE id=?");
        $stmt->execute([$userId]);
        if (!$stmt->fetch()) errorResponse('User not found');
        
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO earnings (user_id, type, amount, status, description) VALUES (?, ?, ?, 'credited', ?)");
            $stmt->execute([$userId, $type, $amount, $description]);
            logAdminAction($_SESSION['user_id'], 'Added earnings to user #'.$userId, ['amount' => $amount, 'type' => $type]);
            $db->commit();
            jsonResponse(['success' => true, 'message' => 'Earnings added successfully']);
        } catch (Exception $e) {
            $db->rollback();
            errorResponse('Failed to add earnings');
        }
    }
    
    if ($action === 'report') {
        $period = $_GET['period'] ?? 'daily';
        $groupBy = $period === 'daily' ? 'DATE(created_at)' : ($period === 'weekly' ? 'YEARWEEK(created_at)' : ($period === 'monthly' ? 'DATE_FORMAT(created_at,"%Y-%m")' : 'DATE_FORMAT(created_at,"%Y")'));
        $stmt = $db->query("SELECT $groupBy as period, type, SUM(amount) as total, COUNT(*) as count FROM earnings WHERE status='credited' GROUP BY period, type ORDER BY period DESC LIMIT 100");
        $data = $stmt->fetchAll();
        jsonResponse(['success' => true, 'data' => $data]);
    }
    
    errorResponse('Unknown action');
}

$earningTypes = $db->query("SELECT DISTINCT type FROM earnings ORDER BY type")->fetchAll(PDO::FETCH_COLUMN);
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
  <div>
    <h2>Earnings</h2>
    <p>Total: <?= formatCurrency($db->query("SELECT COALESCE(SUM(amount),0) FROM earnings WHERE status='credited'")->fetchColumn()) ?></p>
  </div>
  <div class="page-actions">
    <button class="btn btn-primary" onclick="openModal('addEarningModal')"><i class="fas fa-plus"></i> Add Earnings</button>
  </div>
</div>

<div class="tabs">
  <button class="tab active" onclick="switchTab(this,'list')">Earnings List</button>
  <button class="tab" onclick="switchTab(this,'report')">Reports</button>
</div>

<div class="tab-content active" id="tab-list">
  <div class="card">
    <div class="card-body">
      <div class="filter-bar">
        <input type="text" class="form-control" id="searchInput" placeholder="Search by username..." style="min-width:200px">
        <select class="form-control" id="typeFilter"><option value="">All Types</option><?php foreach($earningTypes as $t): ?><option value="<?=$t?>"><?=ucfirst($t)?></option><?php endforeach; ?></select>
        <select class="form-control" id="statusFilter"><option value="">All Status</option><option value="credited">Credited</option><option value="pending">Pending</option><option value="rejected">Rejected</option></select>
        <input type="date" class="form-control" id="dateFrom"><input type="date" class="form-control" id="dateTo">
        <button class="btn btn-primary btn-sm" onclick="loadEarnings()"><i class="fas fa-search"></i> Search</button>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>ID</th><th>User</th><th>Type</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
          <tbody id="earningsBody"><tr><td colspan="6" style="text-align:center;padding:40px"><div class="loading-spinner"></div></td></tr></tbody>
        </table>
      </div>
      <div class="pagination" id="earningsPagination"></div>
    </div>
  </div>
</div>

<div class="tab-content" id="tab-report">
  <div class="card">
    <div class="card-body">
      <div style="display:flex;gap:12px;margin-bottom:20px">
        <button class="btn btn-sm <?php /* default daily */ ?> btn-primary" onclick="loadReport('daily')" id="reportDaily">Daily</button>
        <button class="btn btn-sm btn-outline" onclick="loadReport('weekly')" id="reportWeekly">Weekly</button>
        <button class="btn btn-sm btn-outline" onclick="loadReport('monthly')" id="reportMonthly">Monthly</button>
        <button class="btn btn-sm btn-outline" onclick="loadReport('yearly')" id="reportYearly">Yearly</button>
      </div>
      <div class="chart-container" style="height:300px"><canvas id="reportChart"></canvas></div>
      <div class="table-wrap" style="margin-top:20px"><table id="reportTable"><thead><tr><th>Period</th><th>Type</th><th>Total</th><th>Count</th></tr></thead><tbody id="reportBody"></tbody></table></div>
    </div>
  </div>
</div>

<!-- Add Earning Modal -->
<div class="modal-overlay" id="addEarningModal">
  <div class="modal"><div class="modal-header"><h3><i class="fas fa-plus-circle"></i> Add Earnings to User</h3><button class="modal-close" onclick="closeModal('addEarningModal')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body">
      <div class="form-group"><label>User ID</label><input type="number" class="form-control" id="addUserId" placeholder="Enter user ID" required></div>
      <div class="form-group"><label>Amount</label><input type="number" step="0.0001" class="form-control" id="addAmount" placeholder="0.00" required></div>
      <div class="form-group"><label>Type</label><select class="form-control" id="addType"><option value="bonus">Bonus</option><option value="adjustment">Adjustment</option><option value="offerwall">Offerwall</option><option value="task">Task</option><option value="referral">Referral</option><option value="daily">Daily Reward</option></select></div>
      <div class="form-group"><label>Description</label><textarea class="form-control" id="addDescription" rows="2" placeholder="Optional description"></textarea></div>
    </div>
    <div class="modal-footer"><button class="btn btn-ghost" onclick="closeModal('addEarningModal')">Cancel</button><button class="btn btn-primary" onclick="addEarning()"><i class="fas fa-plus"></i> Add Earnings</button></div>
  </div>
</div>

<script>
let reportChart = null;
let earningsPage = 1;

function loadEarnings(page) {
  page = page || earningsPage;
  earningsPage = page;
  var params = 'action=list&page=' + page;
  var s = document.getElementById('searchInput').value; if (s) params += '&search=' + encodeURIComponent(s);
  var t = document.getElementById('typeFilter').value; if (t) params += '&type=' + t;
  var st = document.getElementById('statusFilter').value; if (st) params += '&status=' + st;
  var df = document.getElementById('dateFrom').value; if (df) params += '&date_from=' + df;
  var dt = document.getElementById('dateTo').value; if (dt) params += '&date_to=' + dt;
  
  document.getElementById('earningsBody').innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px"><div class="loading-spinner"></div></td></tr>';
  
  ajaxGet('/admin/earnings.php?' + params, function(res) {
    if (res.success) {
      document.getElementById('earningsBody').innerHTML = res.html;
      var p = document.getElementById('earningsPagination');
      p.innerHTML = '';
      for (var i = 1; i <= res.pages; i++) {
        var a = document.createElement('a'); a.href = '#'; a.textContent = i;
        a.className = i === page ? 'active' : '';
        a.onclick = function(pi) { return function() { loadEarnings(pi); return false; }; }(i);
        p.appendChild(a);
      }
    }
  });
}

function addEarning() {
  var userId = document.getElementById('addUserId').value;
  var amount = document.getElementById('addAmount').value;
  var type = document.getElementById('addType').value;
  var desc = document.getElementById('addDescription').value;
  if (!userId || !amount || parseFloat(amount) <= 0) { toast('Please fill all required fields', 'error'); return; }
  ajax('/admin/earnings.php', {action:'add', user_id:userId, amount:amount, type:type, description:desc}, function(res) {
    if (res.success) { toast(res.message, 'success'); closeModal('addEarningModal'); loadEarnings(1); }
    else toast(res.error, 'error');
  });
}

function loadReport(period) {
  ['daily','weekly','monthly','yearly'].forEach(function(p) {
    document.getElementById('report' + p.charAt(0).toUpperCase() + p.slice(1)).className = 'btn btn-sm ' + (p === period ? 'btn-primary' : 'btn-outline');
  });
  ajaxGet('/admin/earnings.php?action=report&period=' + period, function(res) {
    if (!res.success) return;
    var data = res.data;
    var labels = [], types = new Set(), values = {};
    data.forEach(function(r) {
      if (labels.indexOf(r.period) === -1) labels.push(r.period);
      types.add(r.type);
      var key = r.period + '_' + r.type;
      values[key] = parseFloat(r.total);
    });
    labels.reverse();
    var datasets = [];
    types.forEach(function(t) {
      var color = '#' + Math.floor(Math.random()*16777215).toString(16);
      datasets.push({
        label: t,
        data: labels.map(function(l) { return values[l + '_' + t] || 0; }),
        backgroundColor: color + '66',
        borderColor: color,
        borderWidth: 1
      });
    });
    
    if (reportChart) reportChart.destroy();
    reportChart = new Chart(document.getElementById('reportChart'), {
      type: 'bar',
      data: { labels: labels, datasets: datasets },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { labels: { color: 'rgba(255,255,255,.6)' } } },
        scales: {
          x: { grid: { display: false }, ticks: { color: 'rgba(255,255,255,.4)' } },
          y: { grid: { color: 'rgba(255,255,255,.05)' }, ticks: { color: 'rgba(255,255,255,.4)' } }
        }
      }
    });
    
    var tbody = document.getElementById('reportBody');
    tbody.innerHTML = '';
    data.forEach(function(r) {
      tbody.innerHTML += '<tr><td>' + r.period + '</td><td><span class="badge badge-info">' + r.type + '</span></td><td style="font-family:var(--font-mono);color:var(--secondary)">' + formatCurrency(r.total) + '</td><td>' + r.count + '</td></tr>';
    });
  });
}

function switchTab(el, tabId) {
  document.querySelectorAll('.tab').forEach(function(t) { t.classList.remove('active'); });
  el.classList.add('active');
  document.querySelectorAll('.tab-content').forEach(function(t) { t.classList.remove('active'); });
  document.getElementById('tab-' + tabId).classList.add('active');
  if (tabId === 'report') loadReport('daily');
}

loadEarnings(1);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
