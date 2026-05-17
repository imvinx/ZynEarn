<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAdmin();
$pageTitle = 'Reports';
$db = getDB();

if (isAjaxRequest()) {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'revenue') {
        $period = $_GET['period'] ?? 'daily';
        $groupBy = $period === 'daily' ? 'DATE(created_at)' : ($period === 'weekly' ? 'YEARWEEK(created_at)' : ($period === 'monthly' ? 'DATE_FORMAT(created_at,"%Y-%m")' : 'DATE_FORMAT(created_at,"%Y")'));
        $rows = $db->query("SELECT $groupBy as period, SUM(CASE WHEN type='withdrawal' THEN -amount ELSE amount END) as revenue, SUM(CASE WHEN type='withdrawal' THEN 0 ELSE amount END) as earnings, SUM(CASE WHEN type='withdrawal' THEN amount ELSE 0 END) as withdrawals FROM (SELECT amount, 'earning' as type, created_at FROM earnings WHERE status='credited' UNION ALL SELECT amount, 'withdrawal' as type, created_at FROM withdrawals WHERE status='completed') t GROUP BY period ORDER BY period DESC LIMIT 90")->fetchAll();
        jsonResponse(['success'=>true,'data'=>$rows]);
    }
    
    if ($action === 'users') {
        $period = $_GET['period'] ?? 'daily';
        $groupBy = $period === 'daily' ? 'DATE(created_at)' : ($period === 'weekly' ? 'YEARWEEK(created_at)' : ($period === 'monthly' ? 'DATE_FORMAT(created_at,"%Y-%m")' : 'DATE_FORMAT(created_at,"%Y")'));
        $rows = $db->query("SELECT $groupBy as period, COUNT(*) as count FROM users GROUP BY period ORDER BY period DESC LIMIT 90")->fetchAll();
        jsonResponse(['success'=>true,'data'=>$rows]);
    }
    
    if ($action === 'export') {
        $type = $_GET['type'] ?? 'revenue';
        $period = $_GET['period'] ?? 'monthly';
        $groupBy = $period === 'daily' ? 'DATE(created_at)' : ($period === 'monthly' ? 'DATE_FORMAT(created_at,"%Y-%m")' : 'DATE_FORMAT(created_at,"%Y")');
        $rows = [];
        if ($type === 'revenue') {
            $rows = $db->query("SELECT $groupBy as period, SUM(CASE WHEN type='withdrawal' THEN -amount ELSE amount END) as revenue, SUM(CASE WHEN type='withdrawal' THEN 0 ELSE amount END) as earnings, SUM(CASE WHEN type='withdrawal' THEN amount ELSE 0 END) as withdrawals FROM (SELECT amount, 'earning' as type, created_at FROM earnings WHERE status='credited' UNION ALL SELECT amount, 'withdrawal' as type, created_at FROM withdrawals WHERE status='completed') t GROUP BY period ORDER BY period DESC")->fetchAll();
        } elseif ($type === 'users') {
            $rows = $db->query("SELECT $groupBy as period, COUNT(*) as count, SUM(IF(status='active',1,0)) as active FROM users GROUP BY period ORDER BY period DESC")->fetchAll();
        } elseif ($type === 'earnings') {
            $rows = $db->query("SELECT $groupBy as period, type, SUM(amount) as total, COUNT(*) as count FROM earnings WHERE status='credited' GROUP BY period, type ORDER BY period DESC")->fetchAll();
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $type . '_report_' . date('Y-m-d') . '.csv');
        $out = fopen('php://output', 'w');
        if (!empty($rows)) {
            fputcsv($out, array_keys($rows[0]));
            foreach ($rows as $r) { fputcsv($out, $r); }
        }
        fclose($out);
        exit;
    }
    
    jsonResponse(['success'=>true,'data'=>[]]);
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
  <div><h2>Reports & Analytics</h2></div>
  <div class="page-actions">
    <div class="dropdown">
      <button class="btn btn-primary dropdown-toggle"><i class="fas fa-download"></i> Export</button>
      <div class="dropdown-menu">
        <a href="/admin/reports.php?action=export&type=revenue&period=monthly"><i class="fas fa-chart-line"></i> Revenue Report</a>
        <a href="/admin/reports.php?action=export&type=users&period=monthly"><i class="fas fa-users"></i> Users Report</a>
        <a href="/admin/reports.php?action=export&type=earnings&period=monthly"><i class="fas fa-coins"></i> Earnings Report</a>
      </div>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">
  <div class="card">
    <div class="card-header">
      <h3>Revenue</h3>
      <div style="display:flex;gap:6px">
        <button class="btn btn-sm btn-outline" onclick="loadRevenue('daily')">Daily</button>
        <button class="btn btn-sm btn-primary" id="revMonthly" onclick="loadRevenue('monthly')">Monthly</button>
        <button class="btn btn-sm btn-outline" onclick="loadRevenue('yearly')">Yearly</button>
      </div>
    </div>
    <div class="card-body"><div class="chart-container"><canvas id="revenueReportChart"></canvas></div></div>
  </div>
  <div class="card">
    <div class="card-header">
      <h3>User Registrations</h3>
      <div style="display:flex;gap:6px">
        <button class="btn btn-sm btn-outline" onclick="loadUsers('daily')">Daily</button>
        <button class="btn btn-sm btn-primary" id="userMonthly" onclick="loadUsers('monthly')">Monthly</button>
        <button class="btn btn-sm btn-outline" onclick="loadUsers('yearly')">Yearly</button>
      </div>
    </div>
    <div class="card-body"><div class="chart-container"><canvas id="userReportChart"></canvas></div></div>
  </div>
</div>

<div class="card">
  <div class="card-header"><h3>Earnings by Type</h3></div>
  <div class="card-body">
    <div class="table-wrap">
      <table>
        <thead><tr><th>Type</th><th>Total Amount</th><th>Count</th><th>% of Total</th></tr></thead>
        <tbody>
          <?php
          $totalEarnings = $db->query("SELECT COALESCE(SUM(amount),0) FROM earnings WHERE status='credited'")->fetchColumn();
          $byType = $db->query("SELECT type, SUM(amount) as total, COUNT(*) as count FROM earnings WHERE status='credited' GROUP BY type ORDER BY total DESC")->fetchAll();
          foreach ($byType as $r):
          ?>
          <tr>
            <td><span class="badge badge-info"><?= sanitize($r['type']) ?></span></td>
            <td style="font-family:var(--font-mono);color:var(--secondary)"><?= formatCurrency($r['total']) ?></td>
            <td><?= formatNumber($r['count']) ?></td>
            <td><?= $totalEarnings > 0 ? round($r['total'] / $totalEarnings * 100, 1) : 0 ?>%</td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
let revChart = null, userChart = null;

function loadRevenue(period) {
  document.querySelectorAll('.card-header .btn').forEach(function(b) { if (b.closest('.card-header').querySelector('h3').textContent === 'Revenue') b.className = 'btn btn-sm btn-outline'; });
  var btn = document.getElementById('rev' + period.charAt(0).toUpperCase() + period.slice(1));
  if (btn) btn.className = 'btn btn-sm btn-primary';
  
  ajaxGet('/admin/reports.php?action=revenue&period=' + period, function(res) {
    if (!res.success || !res.data) return;
    var labels = [], earnings = [], withdrawals = [], revenue = [];
    res.data.reverse().forEach(function(r) {
      labels.push(r.period);
      earnings.push(parseFloat(r.earnings));
      withdrawals.push(parseFloat(r.withdrawals));
      revenue.push(parseFloat(r.revenue));
    });
    if (revChart) revChart.destroy();
    revChart = new Chart(document.getElementById('revenueReportChart'), {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [
          { label: 'Earnings', data: earnings, backgroundColor: 'rgba(0,206,201,.6)', borderColor: '#00cec9', borderWidth: 1 },
          { label: 'Withdrawals', data: withdrawals, backgroundColor: 'rgba(255,107,107,.6)', borderColor: '#ff6b6b', borderWidth: 1 }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { labels: { color: 'rgba(255,255,255,.6)' } } },
        scales: {
          x: { grid: { display: false }, ticks: { color: 'rgba(255,255,255,.4)' } },
          y: { grid: { color: 'rgba(255,255,255,.05)' }, ticks: { color: 'rgba(255,255,255,.4)' } }
        }
      }
    });
  });
}

function loadUsers(period) {
  document.querySelectorAll('.card-header .btn').forEach(function(b) { if (b.closest('.card-header').querySelector('h3').textContent === 'User Registrations') b.className = 'btn btn-sm btn-outline'; });
  var btn = document.getElementById('user' + period.charAt(0).toUpperCase() + period.slice(1));
  if (btn) btn.className = 'btn btn-sm btn-primary';
  
  ajaxGet('/admin/reports.php?action=users&period=' + period, function(res) {
    if (!res.success || !res.data) return;
    var labels = [], counts = [];
    res.data.reverse().forEach(function(r) {
      labels.push(r.period);
      counts.push(parseInt(r.count));
    });
    if (userChart) userChart.destroy();
    userChart = new Chart(document.getElementById('userReportChart'), {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{ label: 'New Users', data: counts, borderColor: '#6c5ce7', backgroundColor: 'rgba(108,92,231,.1)', fill: true, tension: .4 }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { labels: { color: 'rgba(255,255,255,.6)' } } },
        scales: {
          x: { grid: { display: false }, ticks: { color: 'rgba(255,255,255,.4)' } },
          y: { grid: { color: 'rgba(255,255,255,.05)' }, ticks: { color: 'rgba(255,255,255,.4)', stepSize: 1 } }
        }
      }
    });
  });
}

loadRevenue('monthly');
loadUsers('monthly');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
