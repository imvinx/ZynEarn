<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAdmin();
$pageTitle = 'Withdrawals';
$db = getDB();

if (isAjaxRequest()) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    if ($action === 'list') {
        $tab = $_GET['tab'] ?? 'pending';
        $search = $_GET['search'] ?? '';
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $where = ["w.status = ?"];
        $params = [$tab === 'pending' ? 'pending' : ($tab === 'approved' ? 'approved' : ($tab === 'completed' ? 'completed' : ($tab === 'rejected' ? 'rejected' : 'pending')))];
        if ($tab === 'completed') $where = ["w.status IN ('completed','processing')"];
        if ($search) { $where[] = "u.username LIKE ?"; $params[] = "%$search%"; }
        $whereClause = 'WHERE ' . implode(' AND ', $where);
        
        $count = $db->prepare("SELECT COUNT(*) FROM withdrawals w JOIN users u ON w.user_id=u.id $whereClause");
        $count->execute($params);
        $total = $count->fetchColumn();
        
        $stmt = $db->prepare("SELECT w.*, u.username, u.email FROM withdrawals w JOIN users u ON w.user_id=u.id $whereClause ORDER BY w.created_at DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        
        $html = '';
        foreach ($rows as $w) {
            $html .= '<tr>
                <td>'.$w['id'].'</td>
                <td><strong>'.sanitize($w['username']).'</strong></td>
                <td style="font-family:var(--font-mono);color:var(--secondary)">'.formatCurrency($w['amount']).'</td>
                <td style="font-family:var(--font-mono)">-'.formatCurrency($w['fee']).'</td>
                <td style="font-family:var(--font-mono)">'.formatCurrency($w['net_amount']).'</td>
                <td><span class="badge badge-info">'.strtoupper($w['payment_method']).'</span></td>
                <td>' . substr(sanitize($w['wallet_address']), 0, 20) . '...</td>
                <td><span class="badge badge-'.($w['status']==='pending'?'warning':($w['status']==='completed'?'success':($w['status']==='rejected'?'danger':'info'))).'">'.$w['status'].'</span></td>
                <td style="color:var(--text-muted);font-size:.78rem">'.timeAgo($w['created_at']).'</td>
                <td>';
            if ($w['status'] === 'pending') {
                $html .= '<button class="btn btn-sm btn-success" onclick="approveWd('.$w['id'].')"><i class="fas fa-check"></i></button> ';
                $html .= '<button class="btn btn-sm btn-danger" onclick="rejectWd('.$w['id'].')"><i class="fas fa-times"></i></button>';
            } else {
                $html .= '<span style="color:var(--text-muted);font-size:.7rem">'.ucfirst($w['status']).'</span>';
            }
            $html .= '</td></tr>';
        }
        jsonResponse(['success' => true, 'html' => $html, 'total' => $total, 'pages' => ceil($total / $limit)]);
    }
    
    if ($action === 'approve') {
        $id = intval($_POST['id'] ?? 0);
        $note = $_POST['note'] ?? '';
        $stmt = $db->prepare("SELECT * FROM withdrawals WHERE id=? AND status='pending'");
        $stmt->execute([$id]);
        $wd = $stmt->fetch();
        if (!$wd) errorResponse('Withdrawal not found or already processed');
        
        $db->beginTransaction();
        try {
            $balance = getUserBalance($wd['user_id']);
            if ($balance < $wd['amount']) {
                $db->rollback();
                errorResponse('Insufficient user balance');
            }
            
            $stmt = $db->prepare("INSERT INTO transactions (user_id, type, amount, fee, net_amount, payment_method, status, reference_id) VALUES (?, 'withdrawal', ?, ?, ?, ?, 'completed', ?)");
            $stmt->execute([$wd['user_id'], $wd['amount'], $wd['fee'], $wd['net_amount'], $wd['payment_method'], 'WD-' . $id]);
            $txnId = $db->lastInsertId();
            
            $stmt = $db->prepare("UPDATE withdrawals SET status='completed', admin_note=?, transaction_id=?, processed_at=NOW() WHERE id=?");
            $stmt->execute([$note, $txnId, $id]);
            
            addNotification($wd['user_id'], 'withdrawal', 'Withdrawal Completed', 'Your withdrawal of ' . formatCurrency($wd['amount']) . ' has been completed.');
            logAdminAction($_SESSION['user_id'], 'Approved withdrawal #'.$id);
            $db->commit();
            jsonResponse(['success' => true, 'message' => 'Withdrawal approved']);
        } catch (Exception $e) {
            $db->rollback();
            errorResponse('Failed to approve: ' . $e->getMessage());
        }
    }
    
    if ($action === 'reject') {
        $id = intval($_POST['id'] ?? 0);
        $reason = $_POST['reason'] ?? '';
        $stmt = $db->prepare("UPDATE withdrawals SET status='rejected', admin_note=? WHERE id=? AND status='pending'");
        $stmt->execute([$reason, $id]);
        if ($stmt->rowCount()) {
            $wd = $db->prepare("SELECT user_id, amount FROM withdrawals WHERE id=?")->execute([$id]);
            $wd = $db->prepare("SELECT * FROM withdrawals WHERE id=?")->execute([$id]);
            // Fetch properly
            $st = $db->prepare("SELECT * FROM withdrawals WHERE id=?");
            $st->execute([$id]);
            $wdd = $st->fetch();
            if ($wdd) {
                addNotification($wdd['user_id'], 'withdrawal', 'Withdrawal Rejected', 'Your withdrawal of ' . formatCurrency($wdd['amount']) . ' was rejected. Reason: ' . $reason);
            }
            logAdminAction($_SESSION['user_id'], 'Rejected withdrawal #'.$id, ['reason' => $reason]);
            jsonResponse(['success' => true, 'message' => 'Withdrawal rejected']);
        }
        errorResponse('Could not reject withdrawal');
    }
    
    if ($action === 'bulk') {
        $ids = explode(',', $_POST['ids'] ?? '');
        $bulkAction = $_POST['bulk_action'] ?? '';
        $count = 0;
        foreach ($ids as $id) {
            $id = intval($id);
            if (!$id) continue;
            if ($bulkAction === 'approve') {
                $stmt = $db->prepare("UPDATE withdrawals SET status='completed', processed_at=NOW() WHERE id=? AND status='pending'");
                $stmt->execute([$id]);
                if ($stmt->rowCount()) $count++;
            } elseif ($bulkAction === 'reject') {
                $stmt = $db->prepare("UPDATE withdrawals SET status='rejected' WHERE id=? AND status='pending'");
                $stmt->execute([$id]);
                if ($stmt->rowCount()) $count++;
            }
        }
        logAdminAction($_SESSION['user_id'], ucfirst($bulkAction) . ' bulk withdrawals', ['count' => $count]);
        jsonResponse(['success' => true, 'message' => "$count withdrawals $bulkAction" . (($count > 1 || $count === 0) ? 'ed' : 'ed')]);
    }
    
    errorResponse('Unknown action');
}

$counts = [
    'pending' => $db->query("SELECT COUNT(*) FROM withdrawals WHERE status='pending'")->fetchColumn(),
    'approved' => $db->query("SELECT COUNT(*) FROM withdrawals WHERE status='approved'")->fetchColumn(),
    'completed' => $db->query("SELECT COUNT(*) FROM withdrawals WHERE status IN ('completed','processing')")->fetchColumn(),
    'rejected' => $db->query("SELECT COUNT(*) FROM withdrawals WHERE status='rejected'")->fetchColumn(),
];
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
  <div>
    <h2>Withdrawal Management</h2>
    <p><?= $counts['pending'] ?> pending withdrawals</p>
  </div>
</div>

<div class="tabs" id="wdTabs">
  <button class="tab active" data-tab="pending" onclick="switchWdTab('pending')">Pending (<?= $counts['pending'] ?>)</button>
  <button class="tab" data-tab="approved" onclick="switchWdTab('approved')">Approved (<?= $counts['approved'] ?>)</button>
  <button class="tab" data-tab="completed" onclick="switchWdTab('completed')">Completed (<?= $counts['completed'] ?>)</button>
  <button class="tab" data-tab="rejected" onclick="switchWdTab('rejected')">Rejected (<?= $counts['rejected'] ?>)</button>
</div>

<div class="card">
  <div class="card-body">
    <div class="filter-bar">
      <input type="text" class="form-control" id="wdSearch" placeholder="Search by username..." style="min-width:200px">
      <button class="btn btn-primary btn-sm" onclick="loadWithdrawals()"><i class="fas fa-search"></i> Search</button>
      <span style="flex:1"></span>
      <button class="btn btn-success btn-sm" id="bulkApproveBtn" onclick="bulkWdAction('approve')" style="display:none"><i class="fas fa-check"></i> Approve Selected</button>
      <button class="btn btn-danger btn-sm" id="bulkRejectBtn" onclick="bulkWdAction('reject')" style="display:none"><i class="fas fa-times"></i> Reject Selected</button>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th style="width:30px"><input type="checkbox" id="wdSelectAll"></th><th>ID</th><th>User</th><th>Amount</th><th>Fee</th><th>Net</th><th>Method</th><th>Wallet</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody id="wdBody"><tr><td colspan="11" style="text-align:center;padding:40px"><div class="loading-spinner"></div></td></tr></tbody>
      </table>
    </div>
    <div class="pagination" id="wdPagination"></div>
  </div>
</div>

<!-- Approve Modal -->
<div class="modal-overlay" id="approveWdModal">
  <div class="modal modal-sm"><div class="modal-header"><h3><i class="fas fa-check-circle" style="color:var(--secondary)"></i> Approve Withdrawal</h3><button class="modal-close" onclick="closeModal('approveWdModal')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body"><input type="hidden" id="approveWdId"><p style="color:var(--text-secondary);margin-bottom:16px">Confirm approval of this withdrawal?</p><div class="form-group"><label>Admin Note (optional)</label><textarea class="form-control" id="approveNote" rows="2" placeholder="Add a note..."></textarea></div></div>
    <div class="modal-footer"><button class="btn btn-ghost" onclick="closeModal('approveWdModal')">Cancel</button><button class="btn btn-success" onclick="confirmApprove()"><i class="fas fa-check"></i> Approve</button></div>
  </div>
</div>

<!-- Reject Modal -->
<div class="modal-overlay" id="rejectWdModal">
  <div class="modal modal-sm"><div class="modal-header"><h3><i class="fas fa-times-circle" style="color:#ff6b6b"></i> Reject Withdrawal</h3><button class="modal-close" onclick="closeModal('rejectWdModal')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body"><input type="hidden" id="rejectWdId"><div class="form-group"><label>Reason <span style="color:#ff6b6b">*</span></label><textarea class="form-control" id="rejectReason" rows="3" placeholder="Enter rejection reason..." required></textarea></div></div>
    <div class="modal-footer"><button class="btn btn-ghost" onclick="closeModal('rejectWdModal')">Cancel</button><button class="btn btn-danger" onclick="confirmReject()"><i class="fas fa-times"></i> Reject</button></div>
  </div>
</div>

<script>
let wdPage = 1, wdTab = 'pending';

function loadWithdrawals(page) {
  page = page || wdPage;
  wdPage = page;
  var params = 'action=list&tab=' + wdTab + '&page=' + page;
  var s = document.getElementById('wdSearch').value;
  if (s) params += '&search=' + encodeURIComponent(s);
  
  document.getElementById('wdBody').innerHTML = '<tr><td colspan="11" style="text-align:center;padding:40px"><div class="loading-spinner"></div></td></tr>';
  
  ajaxGet('/admin/withdrawals.php?' + params, function(res) {
    if (res.success) {
      document.getElementById('wdBody').innerHTML = res.html;
      var p = document.getElementById('wdPagination');
      p.innerHTML = '';
      for (var i = 1; i <= res.pages; i++) {
        var a = document.createElement('a'); a.href = '#'; a.textContent = i;
        a.className = i === page ? 'active' : '';
        a.onclick = function(pi) { return function() { loadWithdrawals(pi); return false; }; }(i);
        p.appendChild(a);
      }
      document.getElementById('wdSelectAll').checked = false;
      updateBulkWdButtons();
      document.getElementById('wdSelectAll').onchange = function() {
        document.querySelectorAll('.wd-checkbox').forEach(c => c.checked = this.checked);
        updateBulkWdButtons();
      };
    }
  });
}

function switchWdTab(tab) {
  wdTab = tab;
  wdPage = 1;
  document.querySelectorAll('#wdTabs .tab').forEach(function(t) { t.classList.remove('active'); });
  document.querySelector('#wdTabs .tab[data-tab="' + tab + '"]').classList.add('active');
  loadWithdrawals(1);
}

function approveWd(id) {
  document.getElementById('approveWdId').value = id;
  document.getElementById('approveNote').value = '';
  openModal('approveWdModal');
}

function confirmApprove() {
  var id = document.getElementById('approveWdId').value;
  var note = document.getElementById('approveNote').value;
  ajax('/admin/withdrawals.php', {action:'approve', id:id, note:note}, function(res) {
    if (res.success) { toast(res.message, 'success'); closeModal('approveWdModal'); loadWithdrawals(wdPage); }
    else toast(res.error, 'error');
  });
}

function rejectWd(id) {
  document.getElementById('rejectWdId').value = id;
  document.getElementById('rejectReason').value = '';
  openModal('rejectWdModal');
}

function confirmReject() {
  var id = document.getElementById('rejectWdId').value;
  var reason = document.getElementById('rejectReason').value;
  if (!reason) { toast('Please enter a rejection reason', 'error'); return; }
  ajax('/admin/withdrawals.php', {action:'reject', id:id, reason:reason}, function(res) {
    if (res.success) { toast(res.message, 'success'); closeModal('rejectWdModal'); loadWithdrawals(wdPage); }
    else toast(res.error, 'error');
  });
}

function updateBulkWdButtons() {
  var checked = document.querySelectorAll('.wd-checkbox:checked').length;
  document.getElementById('bulkApproveBtn').style.display = checked > 0 && wdTab === 'pending' ? 'inline-flex' : 'none';
  document.getElementById('bulkRejectBtn').style.display = checked > 0 && wdTab === 'pending' ? 'inline-flex' : 'none';
}

function bulkWdAction(action) {
  var ids = [];
  document.querySelectorAll('.wd-checkbox:checked').forEach(function(c) { ids.push(c.value); });
  if (!ids.length) return;
  confirmAction(action === 'approve' ? 'Approve selected withdrawals?' : 'Reject selected withdrawals?', function() {
    ajax('/admin/withdrawals.php', {action:'bulk', ids:ids.join(','), bulk_action:action}, function(res) {
      if (res.success) { toast(res.message, 'success'); loadWithdrawals(wdPage); }
      else toast(res.error, 'error');
    });
  });
}

loadWithdrawals(1);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
