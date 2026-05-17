<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAdmin();
$pageTitle = 'Dashboard';
$db = getDB();
$stats = [];
$stats['total_users'] = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$stats['total_earnings'] = $db->query("SELECT COALESCE(SUM(amount),0) FROM earnings WHERE status='credited'")->fetchColumn();
$stats['total_withdrawals'] = $db->query("SELECT COALESCE(SUM(amount),0) FROM withdrawals WHERE status='completed'")->fetchColumn();
$stats['pending_withdrawals'] = $db->query("SELECT COUNT(*) FROM withdrawals WHERE status='pending'")->fetchColumn();
$stats['active_today'] = $db->query("SELECT COUNT(DISTINCT user_id) FROM user_sessions WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$stats['new_today'] = $db->query("SELECT COUNT(*) FROM users WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$recentUsers = $db->query("SELECT id,username,email,avatar,status,level,created_at FROM users ORDER BY created_at DESC LIMIT 8")->fetchAll();
$pendingWds = $db->query("SELECT w.*,u.username FROM withdrawals w JOIN users u ON w.user_id=u.id WHERE w.status='pending' ORDER BY w.created_at DESC LIMIT 6")->fetchAll();
$activities = $db->query("SELECT al.*,u.username FROM admin_logs al JOIN users u ON al.admin_id=u.id ORDER BY al.created_at DESC LIMIT 10")->fetchAll();
$systemHealth = [
    'db' => true,
    'cache' => is_writable(CACHE_DIR),
    'uploads' => is_writable(UPLOAD_DIR),
    'logs' => is_writable(__DIR__ . '/../system/logs'),
];
$earningsData = $db->query("SELECT DATE(created_at) as date, SUM(amount) as total FROM earnings WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND status='credited' GROUP BY DATE(created_at) ORDER BY date")->fetchAll();
$userGrowth = $db->query("SELECT DATE(created_at) as date, COUNT(*) as total FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY date")->fetchAll();
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="stats-grid">
  <div class="stat-card" style="--accent-c:var(--primary)">
    <div class="stat-icon purple"><i class="fas fa-users"></i></div>
    <div class="stat-info">
      <div class="stat-label">Total Users</div>
      <div class="stat-value"><?= formatNumber($stats['total_users']) ?></div>
      <div class="stat-change up"><i class="fas fa-arrow-up"></i> <?= $stats['new_today'] ?> new today</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="fas fa-coins"></i></div>
    <div class="stat-info">
      <div class="stat-label">Total Earnings</div>
      <div class="stat-value"><?= formatCurrency($stats['total_earnings']) ?></div>
      <div class="stat-change up"><i class="fas fa-chart-line"></i> All time</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fas fa-hand-holding-usd"></i></div>
    <div class="stat-info">
      <div class="stat-label">Total Withdrawals</div>
      <div class="stat-value"><?= formatCurrency($stats['total_withdrawals']) ?></div>
      <div class="stat-change up"><i class="fas fa-check-circle"></i> Completed</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
    <div class="stat-info">
      <div class="stat-label">Pending Withdrawals</div>
      <div class="stat-value"><?= $stats['pending_withdrawals'] ?></div>
      <div class="stat-change <?= $stats['pending_withdrawals'] > 0 ? 'down' : 'up' ?>"><i class="fas fa-hourglass-half"></i> Awaiting approval</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon pink"><i class="fas fa-bolt"></i></div>
    <div class="stat-info">
      <div class="stat-label">Active Users Today</div>
      <div class="stat-value"><?= formatNumber($stats['active_today']) ?></div>
      <div class="stat-change up"><i class="fas fa-users"></i> Online</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon red"><i class="fas fa-user-plus"></i></div>
    <div class="stat-info">
      <div class="stat-label">New Users Today</div>
      <div class="stat-value"><?= $stats['new_today'] ?></div>
      <div class="stat-change up"><i class="fas fa-arrow-up"></i> Registered</div>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:24px">
  <div class="card">
    <div class="card-header"><h3><i class="fas fa-chart-area" style="color:var(--primary);margin-right:8px"></i>Revenue (Last 30 Days)</h3></div>
    <div class="card-body"><div class="chart-container"><canvas id="revenueChart"></canvas></div></div>
  </div>
  <div class="card">
    <div class="card-header"><h3><i class="fas fa-chart-line" style="color:var(--secondary);margin-right:8px"></i>User Growth</h3></div>
    <div class="card-body"><div class="chart-container"><canvas id="userGrowthChart"></canvas></div></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">
  <div class="card">
    <div class="card-header"><h3><i class="fas fa-user-plus" style="color:var(--secondary);margin-right:8px"></i>Recent Registrations</h3></div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap">
        <table>
          <thead><tr><th>User</th><th>Email</th><th>Level</th><th>Status</th><th>Joined</th></tr></thead>
          <tbody>
            <?php foreach($recentUsers as $u): ?>
            <tr>
              <td><div style="display:flex;align-items:center;gap:8px"><div class="avatar avatar-sm"><?= strtoupper(substr($u['username'],0,1)) ?></div><strong><?= sanitize($u['username']) ?></strong></div></td>
              <td><?= sanitize($u['email']) ?></td>
              <td><span class="badge badge-primary">Lvl <?= $u['level'] ?></span></td>
              <td><span class="badge badge-<?= $u['status'] === 'active' ? 'success' : 'danger' ?>"><?= $u['status'] ?></span></td>
              <td style="color:var(--text-muted);font-size:.78rem"><?= timeAgo($u['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($recentUsers)): ?><tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:30px">No users yet</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="card-footer"><a href="/admin/users.php" class="btn btn-ghost btn-sm">View All Users <i class="fas fa-arrow-right"></i></a></div>
  </div>
  <div class="card">
    <div class="card-header"><h3><i class="fas fa-hourglass-half" style="color:var(--accent2);margin-right:8px"></i>Pending Withdrawals</h3></div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap">
        <table>
          <thead><tr><th>User</th><th>Amount</th><th>Method</th><th>Date</th></tr></thead>
          <tbody>
            <?php foreach($pendingWds as $w): ?>
            <tr>
              <td><strong><?= sanitize($w['username']) ?></strong></td>
              <td style="font-family:var(--font-mono);color:var(--secondary)"><?= formatCurrency($w['amount']) ?></td>
              <td><span class="badge badge-info"><?= $w['payment_method'] ?></span></td>
              <td style="color:var(--text-muted);font-size:.78rem"><?= timeAgo($w['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($pendingWds)): ?><tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:30px">No pending withdrawals</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="card-footer"><a href="/admin/withdrawals.php" class="btn btn-ghost btn-sm">Manage Withdrawals <i class="fas fa-arrow-right"></i></a></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px">
  <div class="card">
    <div class="card-header"><h3><i class="fas fa-history" style="color:var(--accent);margin-right:8px"></i>Recent Activity</h3></div>
    <div class="card-body" style="padding:0">
      <div class="activity-list">
        <?php foreach($activities as $act): ?>
        <div class="activity-item" style="padding:14px 20px">
          <div class="activity-icon purple"><i class="fas fa-user-shield"></i></div>
          <div class="activity-content">
            <div class="activity-text"><strong><?= sanitize($act['username']) ?></strong> <?= sanitize($act['action']) ?></div>
            <div class="activity-time"><?= timeAgo($act['created_at']) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if(empty($activities)): ?><div style="text-align:center;color:var(--text-muted);padding:30px">No recent activity</div><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><h3><i class="fas fa-heartbeat" style="color:var(--secondary);margin-right:8px"></i>System Health</h3></div>
    <div class="card-body">
      <?php foreach(['db'=>'Database','cache'=>'Cache','uploads'=>'Uploads','logs'=>'Logs'] as $k=>$v): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--dark-border)">
        <span style="font-size:.84rem"><?= $v ?></span>
        <span class="badge badge-<?= $systemHealth[$k] ? 'success' : 'danger' ?>"><i class="fas fa-<?= $systemHealth[$k] ? 'check-circle' : 'times-circle' ?>"></i> <?= $systemHealth[$k] ? 'Healthy' : 'Error' ?></span>
      </div>
      <?php endforeach; ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0">
        <span style="font-size:.84rem">PHP Version</span>
        <span class="badge badge-info"><i class="fas fa-code"></i> <?= PHP_VERSION ?></span>
      </div>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0">
        <span style="font-size:.84rem">Server Time</span>
        <span class="badge badge-neutral"><?= date('H:i:s') ?></span>
      </div>
    </div>
  </div>
</div>

<script>
const revLabels = [<?php foreach($earningsData as $r): ?>'<?= date('M d', strtotime($r['date'])) ?>',<?php endforeach; ?>];
const revData = [<?php foreach($earningsData as $r): ?><?= $r['total'] ?>,<?php endforeach; ?>];
const userLabels = [<?php foreach($userGrowth as $r): ?>'<?= date('M d', strtotime($r['date'])) ?>',<?php endforeach; ?>];
const userData = [<?php foreach($userGrowth as $r): ?><?= $r['total'] ?>,<?php endforeach; ?>];

new Chart(document.getElementById('revenueChart'), {
  type:'line',
  data:{
    labels:revLabels,
    datasets:[{
      label:'Revenue',
      data:revData,
      borderColor:'#6c5ce7',
      backgroundColor:'rgba(108,92,231,.1)',
      fill:true,
      tension:.4,
      pointBackgroundColor:'#6c5ce7',
      pointBorderColor:'#6c5ce7',
      pointRadius:3,
      borderWidth:2
    }]
  },
  options:{
    responsive:true,maintainAspectRatio:false,
    plugins:{legend:{display:false}},
    scales:{
      x:{grid:{display:false},ticks:{color:'rgba(255,255,255,.4)',font:{size:10}}},
      y:{grid:{color:'rgba(255,255,255,.05)'},ticks:{color:'rgba(255,255,255,.4)',font:{size:10},callback:v=>'$'+v.toFixed(2)}}
    }
  }
});

new Chart(document.getElementById('userGrowthChart'), {
  type:'bar',
  data:{
    labels:userLabels,
    datasets:[{
      label:'New Users',
      data:userData,
      backgroundColor:'rgba(0,206,201,.6)',
      borderColor:'#00cec9',
      borderWidth:1,
      borderRadius:4
    }]
  },
  options:{
    responsive:true,maintainAspectRatio:false,
    plugins:{legend:{display:false}},
    scales:{
      x:{grid:{display:false},ticks:{color:'rgba(255,255,255,.4)',font:{size:10}}},
      y:{grid:{color:'rgba(255,255,255,.05)'},ticks:{color:'rgba(255,255,255,.4)',font:{size:10},stepSize:1}}
    }
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
