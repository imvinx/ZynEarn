<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAdmin();
$pageTitle = 'Coupons';
$db = getDB();

if (isAjaxRequest()) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    if ($action === 'list') {
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = 20; $offset = ($page-1)*$limit;
        $total = $db->query("SELECT COUNT(*) FROM coupons")->fetchColumn();
        $rows = $db->query("SELECT c.*, (SELECT COUNT(*) FROM redeemed_coupons rc WHERE rc.coupon_id=c.id) as used FROM coupons c ORDER BY c.id DESC LIMIT $limit OFFSET $offset")->fetchAll();
        $html = '';
        foreach ($rows as $r) {
            $html .= '<tr>
                <td>'.$r['id'].'</td>
                <td><strong style="font-family:var(--font-mono);letter-spacing:1px">'.sanitize($r['code']).'</strong></td>
                <td><span class="badge badge-'.($r['type']==='fixed'?'info':'warning').'">'.$r['type'].'</span></td>
                <td style="font-family:var(--font-mono);color:var(--secondary)">'.($r['type']==='fixed'?formatCurrency($r['value']):$r['value'].'%').'</td>
                <td>'.formatCurrency($r['min_amount']).'</td>
                <td>'.$r['current_uses'].'/'.($r['max_uses']?:'∞').'</td>
                <td>'.($r['expires_at'] ? date('M d, Y', strtotime($r['expires_at'])) : '<span style="color:var(--text-muted)">Never</span>').'</td>
                <td><span class="badge badge-'.($r['status']==='active'?'success':'neutral').'">'.$r['status'].'</span></td>
                <td><button class="btn btn-sm btn-ghost" onclick="toggleCoupon('.$r['id'].')"><i class="fas fa-'.($r['status']==='active'?'pause':'play').'"></i></button><button class="btn btn-sm btn-ghost" onclick="editCoupon('.$r['id'].')"><i class="fas fa-edit"></i></button><button class="btn btn-sm btn-ghost" onclick="deleteCoupon('.$r['id'].')" style="color:#ff6b6b"><i class="fas fa-trash"></i></button></td>
            </tr>';
        }
        jsonResponse(['success'=>true,'html'=>$html,'total'=>$total,'pages'=>ceil($total/$limit)]);
    }
    
    if ($action === 'save') {
        $id = intval($_POST['id'] ?? 0);
        $data = ['code'=>strtoupper($_POST['code']),'type'=>$_POST['type'],'value'=>floatval($_POST['value']),'min_amount'=>floatval($_POST['min_amount']??0),'max_uses'=>$_POST['max_uses']?intval($_POST['max_uses']):null,'expires_at'=>$_POST['expires_at']?:null];
        if ($id) {
            $sets = []; $p = [];
            foreach ($data as $k=>$v) { $sets[] = "$k=?"; $p[] = $v; }
            $p[] = $id;
            $db->prepare("UPDATE coupons SET " . implode(',', $sets) . " WHERE id=?")->execute($p);
        } else {
            $data['status'] = 'active';
            $db->prepare("INSERT INTO coupons (code,type,value,min_amount,max_uses,expires_at,status) VALUES (?,?,?,?,?,?,?)")->execute(array_values($data));
        }
        logAdminAction($_SESSION['user_id'], ($id?'Updated':'Created').' coupon');
        jsonResponse(['success'=>true,'message'=>'Coupon saved']);
    }
    
    if ($action === 'get') {
        $stmt = $db->prepare("SELECT * FROM coupons WHERE id=?");
        $stmt->execute([intval($_GET['id'])]);
        jsonResponse(['success'=>true,'coupon'=>$stmt->fetch()]);
    }
    
    if ($action === 'toggle') {
        $db->prepare("UPDATE coupons SET status = IF(status='active','inactive','active') WHERE id=?")->execute([intval($_POST['id'])]);
        jsonResponse(['success'=>true,'message'=>'Coupon toggled']);
    }
    
    if ($action === 'delete') {
        $db->prepare("DELETE FROM coupons WHERE id=?")->execute([intval($_POST['id'])]);
        $db->prepare("DELETE FROM redeemed_coupons WHERE coupon_id=?")->execute([intval($_POST['id'])]);
        jsonResponse(['success'=>true,'message'=>'Coupon deleted']);
    }
    
    if ($action === 'redeemed') {
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = 20; $offset = ($page-1)*$limit;
        $total = $db->query("SELECT COUNT(*) FROM redeemed_coupons rc JOIN coupons c ON rc.coupon_id=c.id")->fetchColumn();
        $rows = $db->query("SELECT rc.*, u.username, c.code FROM redeemed_coupons rc JOIN users u ON rc.user_id=u.id JOIN coupons c ON rc.coupon_id=c.id ORDER BY rc.id DESC LIMIT $limit OFFSET $offset")->fetchAll();
        $html = '';
        foreach ($rows as $r) {
            $html .= '<tr><td>'.$r['id'].'</td><td><strong>'.sanitize($r['username']).'</strong></td><td style="font-family:var(--font-mono)">'.sanitize($r['code']).'</td><td style="color:var(--text-muted);font-size:.78rem">'.timeAgo($r['created_at']).'</td></tr>';
        }
        jsonResponse(['success'=>true,'html'=>$html,'total'=>$total,'pages'=>ceil($total/$limit)]);
    }
    
    errorResponse('Unknown action');
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
  <div><h2>Coupon Codes</h2></div>
  <div class="page-actions"><button class="btn btn-primary" onclick="openCouponModal()"><i class="fas fa-plus"></i> Create Coupon</button></div>
</div>

<div class="tabs">
  <button class="tab active" onclick="switchTab(this,'coupons')">Coupons</button>
  <button class="tab" onclick="switchTab(this,'redeemed')">Redeemed</button>
</div>

<div class="tab-content active" id="tab-coupons">
  <div class="card"><div class="card-body">
    <div class="table-wrap"><table><thead><tr><th>ID</th><th>Code</th><th>Type</th><th>Value</th><th>Min Amount</th><th>Uses</th><th>Expires</th><th>Status</th><th>Actions</th></tr></thead><tbody id="couponsBody"></tbody></table></div>
    <div class="pagination" id="couponsPagination"></div>
  </div></div>
</div>

<div class="tab-content" id="tab-redeemed">
  <div class="card"><div class="card-body">
    <div class="table-wrap"><table><thead><tr><th>ID</th><th>User</th><th>Coupon</th><th>Date</th></tr></thead><tbody id="redeemedBody"></tbody></table></div>
    <div class="pagination" id="redeemedPagination"></div>
  </div></div>
</div>

<!-- Coupon Modal -->
<div class="modal-overlay" id="couponModal">
  <div class="modal"><div class="modal-header"><h3 id="couponModalTitle">Create Coupon</h3><button class="modal-close" onclick="closeModal('couponModal')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body">
      <form id="couponForm">
        <input type="hidden" name="id" id="couponId">
        <div class="form-row">
          <div class="form-group"><label>Code</label><input type="text" class="form-control" name="code" id="couponCode" required style="text-transform:uppercase;font-family:var(--font-mono)"></div>
          <div class="form-group"><label>Type</label><select class="form-control" name="type" id="couponType"><option value="fixed">Fixed</option><option value="percentage">Percentage</option></select></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Value</label><input type="number" step="0.01" class="form-control" name="value" id="couponValue" required></div>
          <div class="form-group"><label>Min Amount</label><input type="number" step="0.01" class="form-control" name="min_amount" id="couponMin" value="0"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Max Uses</label><input type="number" class="form-control" name="max_uses" id="couponMaxUses" placeholder="Leave empty for unlimited"></div>
          <div class="form-group"><label>Expires At</label><input type="date" class="form-control" name="expires_at" id="couponExpires"></div>
        </div>
      </form>
    </div>
    <div class="modal-footer"><button class="btn btn-ghost" onclick="closeModal('couponModal')">Cancel</button><button class="btn btn-primary" onclick="saveCoupon()"><i class="fas fa-save"></i> Create</button></div>
  </div>
</div>

<script>
let couponPage = 1, redeemedPage = 1;

function loadCoupons(page) {
  page = page || couponPage; couponPage = page;
  ajaxGet('/admin/coupons.php?action=list&page=' + page, function(res) {
    if (res.success) {
      document.getElementById('couponsBody').innerHTML = res.html;
      var p = document.getElementById('couponsPagination'); p.innerHTML = '';
      for (var i = 1; i <= res.pages; i++) {
        var a = document.createElement('a'); a.href = '#'; a.textContent = i;
        a.className = i === page ? 'active' : '';
        a.onclick = function(pi) { return function() { loadCoupons(pi); return false; }; }(i); p.appendChild(a);
      }
    }
  });
}

function loadRedeemed(page) {
  page = page || redeemedPage; redeemedPage = page;
  ajaxGet('/admin/coupons.php?action=redeemed&page=' + page, function(res) {
    if (res.success) {
      document.getElementById('redeemedBody').innerHTML = res.html;
      var p = document.getElementById('redeemedPagination'); p.innerHTML = '';
      for (var i = 1; i <= res.pages; i++) {
        var a = document.createElement('a'); a.href = '#'; a.textContent = i;
        a.className = i === page ? 'active' : '';
        a.onclick = function(pi) { return function() { loadRedeemed(pi); return false; }; }(i); p.appendChild(a);
      }
    }
  });
}

function openCouponModal(id) {
  document.getElementById('couponForm').reset();
  document.getElementById('couponId').value = '';
  document.getElementById('couponModalTitle').textContent = 'Create Coupon';
  if (id) {
    ajaxGet('/admin/coupons.php?action=get&id=' + id, function(res) {
      if (res.success && res.coupon) {
        var c = res.coupon;
        document.getElementById('couponId').value = c.id;
        document.getElementById('couponCode').value = c.code;
        document.getElementById('couponType').value = c.type;
        document.getElementById('couponValue').value = c.value;
        document.getElementById('couponMin').value = c.min_amount;
        document.getElementById('couponMaxUses').value = c.max_uses || '';
        document.getElementById('couponExpires').value = c.expires_at ? c.expires_at.substring(0,10) : '';
        document.getElementById('couponModalTitle').textContent = 'Edit Coupon';
        openModal('couponModal');
      }
    });
  } else { openModal('couponModal'); }
}

function editCoupon(id) { openCouponModal(id); }

function saveCoupon() {
  var data = serializeForm(document.getElementById('couponForm'));
  data.action = 'save';
  ajax('/admin/coupons.php', data, function(res) {
    if (res.success) { toast(res.message, 'success'); closeModal('couponModal'); loadCoupons(1); }
    else toast(res.error, 'error');
  });
}

function toggleCoupon(id) {
  ajax('/admin/coupons.php', {action:'toggle', id:id}, function(res) {
    if (res.success) { toast(res.message, 'success'); loadCoupons(couponPage); }
    else toast(res.error, 'error');
  });
}

function deleteCoupon(id) {
  confirmAction('Delete this coupon?', function() {
    ajax('/admin/coupons.php', {action:'delete', id:id}, function(res) {
      if (res.success) { toast(res.message, 'success'); loadCoupons(couponPage); }
      else toast(res.error, 'error');
    });
  });
}

function switchTab(el, tab) {
  document.querySelectorAll('.tabs .tab').forEach(function(t) { t.classList.remove('active'); });
  el.classList.add('active');
  document.querySelectorAll('.tab-content').forEach(function(t) { t.classList.remove('active'); });
  document.getElementById('tab-' + tab).classList.add('active');
  if (tab === 'redeemed') loadRedeemed(1);
}

loadCoupons(1);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
