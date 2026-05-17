<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAdmin();
$pageTitle = 'Users';
$db = getDB();

// AJAX handlers
if (isAjaxRequest()) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    if ($action === 'list') {
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';
        $level = $_GET['level'] ?? '';
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $where = [];
        $params = [];
        if ($search) { $where[] = "(u.username LIKE ? OR u.email LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
        if ($status) { $where[] = "u.status = ?"; $params[] = $status; }
        if ($level) { $where[] = "u.level = ?"; $params[] = $level; }
        if ($dateFrom) { $where[] = "u.created_at >= ?"; $params[] = $dateFrom; }
        if ($dateTo) { $where[] = "u.created_at <= ?"; $params[] = $dateTo . ' 23:59:59'; }
        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $countStmt = $db->prepare("SELECT COUNT(*) FROM users u $whereClause");
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT u.*, COALESCE((SELECT SUM(amount) FROM earnings WHERE user_id=u.id AND status='credited'),0) as total_earned FROM users u $whereClause ORDER BY u.id DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        $users = $stmt->fetchAll();
        
        $html = '';
        foreach ($users as $u) {
            $balance = getUserBalance($u['id']);
            $html .= '<tr>
                <td>'.$u['id'].'</td>
                <td><div style="display:flex;align-items:center;gap:8px"><div class="avatar avatar-sm">'.strtoupper(substr($u['username'],0,1)).'</div><strong>'.sanitize($u['username']).'</strong></div></td>
                <td>'.sanitize($u['email']).'</td>
                <td style="font-family:var(--font-mono);color:var(--secondary)">'.formatCurrency($balance).'</td>
                <td><span class="badge badge-primary">Lvl '.$u['level'].'</span></td>
                <td><span class="badge badge-'.($u['status']==='active'?'success':($u['status']==='banned'?'danger':'warning')).'">'.$u['status'].'</span></td>
                <td style="color:var(--text-muted);font-size:.78rem">'.date('M d, Y', strtotime($u['created_at'])).'</td>
                <td>
                    <div class="dropdown">
                        <button class="btn btn-ghost btn-sm dropdown-toggle"><i class="fas fa-ellipsis-v"></i></button>
                        <div class="dropdown-menu">
                            <a href="#" onclick="viewUser('.$u['id'].');return false"><i class="fas fa-eye"></i> View</a>
                            <a href="#" onclick="editUser('.$u['id'].');return false"><i class="fas fa-edit"></i> Edit</a>
                            '.($u['status']==='banned'?'<a href="#" onclick="unbanUser('.$u['id'].');return false"><i class="fas fa-check-circle"></i> Unban</a>':'<a href="#" onclick="banUser('.$u['id'].');return false"><i class="fas fa-ban"></i> Ban</a>').'
                            <div class="dropdown-divider"></div>
                            <a href="#" onclick="deleteUser('.$u['id'].');return false" style="color:#ff6b6b"><i class="fas fa-trash"></i> Delete</a>
                        </div>
                    </div>
                </td>
            </tr>';
        }
        jsonResponse(['success' => true, 'html' => $html, 'total' => $total, 'pages' => ceil($total / $limit)]);
    }
    
    if ($action === 'view') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT u.*, COALESCE((SELECT SUM(amount) FROM earnings WHERE user_id=u.id AND status='credited'),0) as total_earned, COALESCE((SELECT SUM(amount) FROM withdrawals WHERE user_id=u.id AND status='completed'),0) as total_withdrawn FROM users u WHERE u.id=?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) errorResponse('User not found');
        $html = '<div style="text-align:center;margin-bottom:24px">
            <div class="avatar avatar-lg" style="margin:0 auto 12px">'.strtoupper(substr($user['username'],0,2)).'</div>
            <h3 style="font-size:1.2rem">'.sanitize($user['username']).'</h3>
            <span class="badge badge-'.($user['status']==='active'?'success':'danger').'">'.$user['status'].'</span>
        </div>
        <div class="form-row">
            <div><div class="form-label">Email</div><div style="font-size:.9rem">'.sanitize($user['email']).'</div></div>
            <div><div class="form-label">Balance</div><div style="font-size:.9rem;font-family:var(--font-mono);color:var(--secondary)">'.formatCurrency(getUserBalance($user['id'])).'</div></div>
            <div><div class="form-label">Level</div><div><span class="badge badge-primary">Lvl '.$user['level'].'</span></div></div>
            <div><div class="form-label">XP Points</div><div>'.formatNumber($user['xp_points']).'</div></div>
            <div><div class="form-label">Total Earned</div><div style="font-family:var(--font-mono);color:var(--secondary)">'.formatCurrency($user['total_earned']).'</div></div>
            <div><div class="form-label">Total Withdrawn</div><div style="font-family:var(--font-mono)">'.formatCurrency($user['total_withdrawn']).'</div></div>
            <div><div class="form-label">Role</div><div><span class="badge badge-'.($user['role']==='admin'?'warning':'info').'">'.$user['role'].'</span></div></div>
            <div><div class="form-label">Tier</div><div><span class="badge badge-purple">'.ucfirst($user['membership_tier']).'</span></div></div>
            <div><div class="form-label">Joined</div><div style="color:var(--text-muted)">'.date('M d, Y H:i', strtotime($user['created_at'])).'</div></div>
            <div><div class="form-label">Last Login</div><div style="color:var(--text-muted)">'.($user['last_login_at'] ? timeAgo($user['last_login_at']) : 'Never').'</div></div>
        </div>';
        jsonResponse(['success' => true, 'html' => $html, 'user' => $user]);
    }
    
    if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM users WHERE id=?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) errorResponse('User not found');
        $html = '<input type="hidden" name="id" value="'.$id.'">
            <div class="form-row">
                <div class="form-group"><label>Username</label><input type="text" class="form-control" name="username" value="'.sanitize($user['username']).'"></div>
                <div class="form-group"><label>Email</label><input type="email" class="form-control" name="email" value="'.sanitize($user['email']).'"></div>
                <div class="form-group"><label>Balance</label><input type="number" step="0.0001" class="form-control" name="balance" value="'.getUserBalance($user['id']).'"></div>
                <div class="form-group"><label>Level</label><input type="number" class="form-control" name="level" value="'.$user['level'].'"></div>
                <div class="form-group"><label>XP Points</label><input type="number" class="form-control" name="xp_points" value="'.$user['xp_points'].'"></div>
                <div class="form-group"><label>Status</label><select class="form-control" name="status"><option value="active" '.($user['status']==='active'?'selected':'').'>Active</option><option value="banned" '.($user['status']==='banned'?'selected':'').'>Banned</option><option value="suspended" '.($user['status']==='suspended'?'selected':'').'>Suspended</option></select></div>
                <div class="form-group"><label>Role</label><select class="form-control" name="role"><option value="user" '.($user['role']==='user'?'selected':'').'>User</option><option value="admin" '.($user['role']==='admin'?'selected':'').'>Admin</option></select></div>
                <div class="form-group"><label>Membership Tier</label><select class="form-control" name="membership_tier"><option value="free" '.($user['membership_tier']==='free'?'selected':'').'>Free</option><option value="silver" '.($user['membership_tier']==='silver'?'selected':'').'>Silver</option><option value="gold" '.($user['membership_tier']==='gold'?'selected':'').'>Gold</option><option value="platinum" '.($user['membership_tier']==='platinum'?'selected':'').'>Platinum</option><option value="vip" '.($user['membership_tier']==='vip'?'selected':'').'>VIP</option></select></div>
            </div>';
        jsonResponse(['success' => true, 'html' => $html, 'user' => $user]);
    }
    
    if ($action === 'save') {
        $id = intval($_POST['id'] ?? 0);
        $data = $_POST;
        unset($data['action'], $data['id']);
        $balance = floatval($data['balance'] ?? 0);
        unset($data['balance']);
        $sets = []; $params = [];
        foreach (['username','email','level','xp_points','status','role','membership_tier'] as $f) {
            if (isset($data[$f])) { $sets[] = "$f = ?"; $params[] = $data[$f]; }
        }
        $params[] = $id;
        if (!empty($sets)) {
            $stmt = $db->prepare("UPDATE users SET " . implode(', ', $sets) . " WHERE id = ?");
            $stmt->execute($params);
        }
        // Balance adjustment logic
        $currentBalance = getUserBalance($id);
        $diff = $balance - $currentBalance;
        if (abs($diff) > 0.0001) {
            $type = $diff > 0 ? 'bonus' : 'adjustment';
            $stmt = $db->prepare("INSERT INTO earnings (user_id, type, amount, status, description) VALUES (?, ?, ?, 'credited', ?)");
            $stmt->execute([$id, $type, $diff, 'Admin balance adjustment']);
        }
        logAdminAction($_SESSION['user_id'], 'Updated user #'.$id, $data);
        jsonResponse(['success' => true, 'message' => 'User updated successfully']);
    }
    
    if ($action === 'ban') {
        $id = intval($_POST['id'] ?? 0);
        $reason = $_POST['reason'] ?? '';
        $stmt = $db->prepare("UPDATE users SET status='banned', banned_at=NOW(), ban_reason=? WHERE id=? AND role!='admin'");
        $stmt->execute([$reason, $id]);
        logAdminAction($_SESSION['user_id'], 'Banned user #'.$id, ['reason' => $reason]);
        jsonResponse(['success' => true, 'message' => 'User banned']);
    }
    
    if ($action === 'unban') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $db->prepare("UPDATE users SET status='active', banned_at=NULL, ban_reason=NULL WHERE id=?");
        $stmt->execute([$id]);
        logAdminAction($_SESSION['user_id'], 'Unbanned user #'.$id);
        jsonResponse(['success' => true, 'message' => 'User unbanned']);
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT role FROM users WHERE id=?");
        $stmt->execute([$id]);
        $u = $stmt->fetch();
        if (!$u || $u['role'] === 'admin') errorResponse('Cannot delete admin users');
        $db->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
        logAdminAction($_SESSION['user_id'], 'Deleted user #'.$id);
        jsonResponse(['success' => true, 'message' => 'User deleted']);
    }
    
    errorResponse('Unknown action');
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
  <div>
    <h2>User Management</h2>
    <p><?= formatNumber($db->query("SELECT COUNT(*) FROM users")->fetchColumn()) ?> total users</p>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="filter-bar">
      <input type="text" class="form-control" id="searchInput" placeholder="Search username or email..." style="min-width:220px">
      <select class="form-control" id="statusFilter"><option value="">All Status</option><option value="active">Active</option><option value="banned">Banned</option><option value="suspended">Suspended</option></select>
      <select class="form-control" id="levelFilter"><option value="">All Levels</option><?php for($i=1;$i<=15;$i++): ?><option value="<?=$i?>">Level <?=$i?></option><?php endfor; ?></select>
      <input type="date" class="form-control" id="dateFrom">
      <input type="date" class="form-control" id="dateTo">
      <button class="btn btn-primary btn-sm" onclick="loadUsers()"><i class="fas fa-search"></i> Search</button>
      <button class="btn btn-ghost btn-sm" onclick="resetFilters()"><i class="fas fa-undo"></i> Reset</button>
    </div>
    <div class="bulk-bar" id="bulkBar">
      <span id="bulkCount">0 selected</span>
      <button class="btn btn-sm btn-danger" onclick="bulkAction('ban')"><i class="fas fa-ban"></i> Ban</button>
      <button class="btn btn-sm btn-success" onclick="bulkAction('unban')"><i class="fas fa-check-circle"></i> Unban</button>
      <button class="btn btn-sm btn-ghost" onclick="clearSelection()"><i class="fas fa-times"></i> Clear</button>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th style="width:30px"><input type="checkbox" id="selectAll"></th><th>ID</th><th>User</th><th>Email</th><th>Balance</th><th>Level</th><th>Status</th><th>Joined</th><th style="width:60px">Actions</th></tr></thead>
        <tbody id="usersTableBody"><tr><td colspan="9" style="text-align:center;padding:40px"><div class="loading-spinner"></div></td></tr></tbody>
      </table>
    </div>
    <div class="pagination" id="usersPagination"></div>
  </div>
</div>

<!-- View User Modal -->
<div class="modal-overlay" id="viewUserModal">
  <div class="modal modal-lg"><div class="modal-header"><h3><i class="fas fa-user"></i> User Details</h3><button class="modal-close" onclick="closeModal('viewUserModal')"><i class="fas fa-times"></i></button></div><div class="modal-body" id="viewUserContent"></div><div class="modal-footer"><button class="btn btn-ghost" onclick="closeModal('viewUserModal')">Close</button></div></div>
</div>

<!-- Edit User Modal -->
<div class="modal-overlay" id="editUserModal">
  <div class="modal modal-lg"><div class="modal-header"><h3><i class="fas fa-edit"></i> Edit User</h3><button class="modal-close" onclick="closeModal('editUserModal')"><i class="fas fa-times"></i></button></div><div class="modal-body"><form id="editUserForm"></form></div><div class="modal-footer"><button class="btn btn-ghost" onclick="closeModal('editUserModal')">Cancel</button><button class="btn btn-primary" onclick="saveUser()"><i class="fas fa-save"></i> Save Changes</button></div></div>
</div>

<!-- Ban User Modal -->
<div class="modal-overlay" id="banUserModal">
  <div class="modal modal-sm"><div class="modal-header"><h3><i class="fas fa-ban" style="color:#ff6b6b"></i> Ban User</h3><button class="modal-close" onclick="closeModal('banUserModal')"><i class="fas fa-times"></i></button></div><div class="modal-body"><p style="color:var(--text-secondary);margin-bottom:16px">Are you sure you want to ban this user?</p><input type="hidden" id="banUserId"><div class="form-group"><label>Reason (optional)</label><textarea class="form-control" id="banReason" rows="2" placeholder="Enter ban reason..."></textarea></div></div><div class="modal-footer"><button class="btn btn-ghost" onclick="closeModal('banUserModal')">Cancel</button><button class="btn btn-danger" onclick="confirmBan()"><i class="fas fa-ban"></i> Ban User</button></div></div>
</div>

<script>
let currentPage = 1;
document.getElementById('selectAll').addEventListener('change', function() {
  document.querySelectorAll('.user-checkbox').forEach(c => c.checked = this.checked);
  updateBulkBar();
});

function loadUsers(page) {
  page = page || currentPage;
  currentPage = page;
  var params = 'action=list&page=' + page;
  var s = document.getElementById('searchInput').value; if (s) params += '&search=' + encodeURIComponent(s);
  var st = document.getElementById('statusFilter').value; if (st) params += '&status=' + st;
  var lv = document.getElementById('levelFilter').value; if (lv) params += '&level=' + lv;
  var df = document.getElementById('dateFrom').value; if (df) params += '&date_from=' + df;
  var dt = document.getElementById('dateTo').value; if (dt) params += '&date_to=' + dt;
  
  document.getElementById('usersTableBody').innerHTML = '<tr><td colspan="9" style="text-align:center;padding:40px"><div class="loading-spinner"></div></td></tr>';
  
  ajaxGet('/admin/users.php?' + params, function(res) {
    if (res.success) {
      document.getElementById('usersTableBody').innerHTML = res.html;
      var p = document.getElementById('usersPagination');
      p.innerHTML = '';
      for (var i = 1; i <= res.pages; i++) {
        var a = document.createElement('a');
        a.href = '#';
        a.textContent = i;
        a.className = i === page ? 'active' : '';
        a.onclick = function(pi) { return function() { loadUsers(pi); return false; }; }(i);
        p.appendChild(a);
      }
      document.getElementById('selectAll').checked = false;
      updateBulkBar();
      // Re-bind dropdowns
      document.querySelectorAll('.dropdown-toggle').forEach(function(btn) {
        btn.addEventListener('click', function(e) { e.stopPropagation(); var m = this.nextElementSibling; m.classList.toggle('active'); });
      });
    } else {
      document.getElementById('usersTableBody').innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:30px">Error loading users</td></tr>';
    }
  });
}

function resetFilters() {
  document.getElementById('searchInput').value = '';
  document.getElementById('statusFilter').value = '';
  document.getElementById('levelFilter').value = '';
  document.getElementById('dateFrom').value = '';
  document.getElementById('dateTo').value = '';
  loadUsers(1);
}

function viewUser(id) {
  ajax('/admin/users.php', {action:'view',id:id}, function(res) {
    if (res.success) { document.getElementById('viewUserContent').innerHTML = res.html; openModal('viewUserModal'); }
    else toast(res.error, 'error');
  });
}

function editUser(id) {
  ajax('/admin/users.php', {action:'edit',id:id}, function(res) {
    if (res.success) { document.getElementById('editUserForm').innerHTML = res.html; openModal('editUserModal'); }
    else toast(res.error, 'error');
  });
}

function saveUser() {
  var form = document.getElementById('editUserForm');
  var data = serializeForm(form);
  data.action = 'save';
  ajax('/admin/users.php', data, function(res) {
    if (res.success) { toast(res.message, 'success'); closeModal('editUserModal'); loadUsers(currentPage); }
    else toast(res.error, 'error');
  });
}

function banUser(id) {
  document.getElementById('banUserId').value = id;
  document.getElementById('banReason').value = '';
  openModal('banUserModal');
}

function confirmBan() {
  var id = document.getElementById('banUserId').value;
  var reason = document.getElementById('banReason').value;
  ajax('/admin/users.php', {action:'ban',id:id,reason:reason}, function(res) {
    if (res.success) { toast(res.message, 'success'); closeModal('banUserModal'); loadUsers(currentPage); }
    else toast(res.error, 'error');
  });
}

function unbanUser(id) {
  confirmAction('Unban this user?', function() {
    ajax('/admin/users.php', {action:'unban',id:id}, function(res) {
      if (res.success) { toast(res.message, 'success'); loadUsers(currentPage); }
      else toast(res.error, 'error');
    });
  });
}

function deleteUser(id) {
  confirmAction('Delete this user? This cannot be undone.', function() {
    ajax('/admin/users.php', {action:'delete',id:id}, function(res) {
      if (res.success) { toast(res.message, 'success'); loadUsers(currentPage); }
      else toast(res.error, 'error');
    });
  });
}

function updateBulkBar() {
  var checked = document.querySelectorAll('.user-checkbox:checked').length;
  var bar = document.getElementById('bulkBar');
  if (checked > 0) { bar.classList.add('active'); document.getElementById('bulkCount').textContent = checked + ' selected'; }
  else bar.classList.remove('active');
}

function bulkAction(action) {
  var ids = [];
  document.querySelectorAll('.user-checkbox:checked').forEach(function(c) { ids.push(c.value); });
  if (!ids.length) return;
  confirmAction(action === 'ban' ? 'Ban selected users?' : 'Unban selected users?', function() {
    ajax('/admin/users.php', {action:'bulk_' + action, ids:ids.join(',')}, function(res) {
      if (res.success) { toast(res.message, 'success'); loadUsers(currentPage); } else toast(res.error, 'error');
    });
  });
}

function clearSelection() {
  document.querySelectorAll('.user-checkbox').forEach(c => c.checked = false);
  document.getElementById('selectAll').checked = false;
  updateBulkBar();
}

loadUsers(1);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
