<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAdmin();
$pageTitle = 'Offers';
$db = getDB();

if (isAjaxRequest()) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    if ($action === 'list') {
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        $where = []; $params = [];
        if ($search) { $where[] = "(title LIKE ? OR provider LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
        if ($status) { $where[] = "status = ?"; $params[] = $status; }
        $wc = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $total = $db->prepare("SELECT COUNT(*) FROM offerwall_offers $wc")->execute($params) ? $db->query("SELECT FOUND_ROWS()")->fetchColumn() : 0;
        // Re-do properly
        $c = $db->prepare("SELECT COUNT(*) FROM offerwall_offers $wc");
        $c->execute($params); $total = $c->fetchColumn();
        $stmt = $db->prepare("SELECT * FROM offerwall_offers $wc ORDER BY id DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params); $rows = $stmt->fetchAll();
        $html = '';
        foreach ($rows as $r) {
            $html .= '<tr>
                <td>'.$r['id'].'</td>
                <td>'.sanitize($r['title']).'</td>
                <td><span class="badge badge-info">'.sanitize($r['provider']).'</span></td>
                <td style="font-family:var(--font-mono);color:var(--secondary)">'.formatCurrency($r['payout_amount']).'</td>
                <td><span class="badge badge-'.($r['status']==='active'?'success':'neutral').'">'.$r['status'].'</span></td>
                <td style="color:var(--text-muted);font-size:.78rem">'.timeAgo($r['created_at']).'</td>
                <td>
                    <button class="btn btn-sm btn-ghost" onclick="toggleOffer('.$r['id'].')"><i class="fas fa-'.($r['status']==='active'?'pause':'play').'"></i></button>
                    <button class="btn btn-sm btn-ghost" onclick="editOffer('.$r['id'].')"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-ghost" onclick="deleteOffer('.$r['id'].')" style="color:#ff6b6b"><i class="fas fa-trash"></i></button>
                </td>
            </tr>';
        }
        jsonResponse(['success' => true, 'html' => $html, 'total' => $total, 'pages' => ceil($total / $limit)]);
    }
    
    if ($action === 'save') {
        $id = intval($_POST['id'] ?? 0);
        $data = ['title' => $_POST['title'], 'description' => $_POST['description'], 'provider' => $_POST['provider'], 'offer_id' => $_POST['offer_id'], 'payout_amount' => floatval($_POST['payout_amount']), 'payout_type' => $_POST['payout_type'], 'category' => $_POST['category'], 'url' => $_POST['url'], 'device_type' => $_POST['device_type'], 'country_target' => $_POST['country_target']];
        if ($id) {
            $sets = []; $params = [];
            foreach ($data as $k => $v) { $sets[] = "$k=?"; $params[] = $v; }
            $params[] = $id;
            $db->prepare("UPDATE offerwall_offers SET " . implode(',', $sets) . " WHERE id=?")->execute($params);
        } else {
            $data['status'] = 'active';
            $stmt = $db->prepare("INSERT INTO offerwall_offers (title,description,provider,offer_id,payout_amount,payout_type,category,url,device_type,country_target,status) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute(array_values($data));
        }
        logAdminAction($_SESSION['user_id'], ($id?'Updated':'Created').' offer #'.($id?:$db->lastInsertId()));
        jsonResponse(['success' => true, 'message' => 'Offer saved']);
    }
    
    if ($action === 'get') {
        $id = intval($_GET['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM offerwall_offers WHERE id=?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true, 'offer' => $stmt->fetch()]);
    }
    
    if ($action === 'toggle') {
        $id = intval($_POST['id'] ?? 0);
        $db->prepare("UPDATE offerwall_offers SET status = IF(status='active','inactive','active') WHERE id=?")->execute([$id]);
        logAdminAction($_SESSION['user_id'], 'Toggled offer #'.$id);
        jsonResponse(['success' => true, 'message' => 'Offer toggled']);
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM offerwall_offers WHERE id=?")->execute([$id]);
        logAdminAction($_SESSION['user_id'], 'Deleted offer #'.$id);
        jsonResponse(['success' => true, 'message' => 'Offer deleted']);
    }
    
    if ($action === 'completions') {
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        $total = $db->query("SELECT COUNT(*) FROM offerwall_completions")->fetchColumn();
        $rows = $db->query("SELECT oc.*, u.username, oo.title FROM offerwall_completions oc LEFT JOIN users u ON oc.user_id=u.id LEFT JOIN offerwall_offers oo ON oc.offer_id=oo.id ORDER BY oc.id DESC LIMIT $limit OFFSET $offset")->fetchAll();
        $html = '';
        foreach ($rows as $r) {
            $html .= '<tr><td>'.$r['id'].'</td><td>'.sanitize($r['username']??'N/A').'</td><td>'.sanitize($r['title']??'N/A').'</td><td style="font-family:var(--font-mono);color:var(--secondary)">'.formatCurrency($r['payout']).'</td><td><span class="badge badge-'.($r['status']==='credited'?'success':($r['status']==='pending'?'warning':'danger')).'">'.$r['status'].'</span></td><td style="color:var(--text-muted);font-size:.78rem">'.timeAgo($r['created_at']).'</td></tr>';
        }
        jsonResponse(['success' => true, 'html' => $html, 'total' => $total, 'pages' => ceil($total / $limit)]);
    }
    
    errorResponse('Unknown action');
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
  <div><h2>Offer Management</h2></div>
  <div class="page-actions"><button class="btn btn-primary" onclick="openOfferModal()"><i class="fas fa-plus"></i> Add Offer</button></div>
</div>

<div class="tabs">
  <button class="tab active" onclick="switchTab(this,'offers')">Offers</button>
  <button class="tab" onclick="switchTab(this,'completions')">Completions</button>
</div>

<div class="tab-content active" id="tab-offers">
  <div class="card">
    <div class="card-body">
      <div class="filter-bar">
        <input type="text" class="form-control" id="offerSearch" placeholder="Search title or provider..." style="min-width:200px">
        <select class="form-control" id="offerStatus"><option value="">All</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
        <button class="btn btn-primary btn-sm" onclick="loadOffers()"><i class="fas fa-search"></i> Search</button>
      </div>
      <div class="table-wrap"><table><thead><tr><th>ID</th><th>Title</th><th>Provider</th><th>Payout</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead><tbody id="offersBody"></tbody></table></div>
      <div class="pagination" id="offersPagination"></div>
    </div>
  </div>
</div>

<div class="tab-content" id="tab-completions">
  <div class="card">
    <div class="card-body">
      <div class="table-wrap"><table><thead><tr><th>ID</th><th>User</th><th>Offer</th><th>Payout</th><th>Status</th><th>Date</th></tr></thead><tbody id="completionsBody"></tbody></table></div>
      <div class="pagination" id="completionsPagination"></div>
    </div>
  </div>
</div>

<!-- Offer Modal -->
<div class="modal-overlay" id="offerModal">
  <div class="modal modal-lg"><div class="modal-header"><h3 id="offerModalTitle">Add Offer</h3><button class="modal-close" onclick="closeModal('offerModal')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body">
      <form id="offerForm">
        <input type="hidden" name="id" id="offerId">
        <div class="form-row">
          <div class="form-group"><label>Title</label><input type="text" class="form-control" name="title" id="offerTitle" required></div>
          <div class="form-group"><label>Provider</label><input type="text" class="form-control" name="provider" id="offerProvider" required></div>
        </div>
        <div class="form-group"><label>Description</label><textarea class="form-control" name="description" id="offerDesc" rows="3"></textarea></div>
        <div class="form-row">
          <div class="form-group"><label>Offer ID</label><input type="text" class="form-control" name="offer_id" id="offerOid"></div>
          <div class="form-group"><label>Payout Amount</label><input type="number" step="0.0001" class="form-control" name="payout_amount" id="offerPayout" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Payout Type</label><input type="text" class="form-control" name="payout_type" id="offerPayoutType"></div>
          <div class="form-group"><label>Category</label><input type="text" class="form-control" name="category" id="offerCategory"></div>
        </div>
        <div class="form-group"><label>URL</label><input type="url" class="form-control" name="url" id="offerUrl"></div>
        <div class="form-row">
          <div class="form-group"><label>Device Type</label><select class="form-control" name="device_type" id="offerDevice"><option value="">Any</option><option value="desktop">Desktop</option><option value="mobile">Mobile</option><option value="tablet">Tablet</option></select></div>
          <div class="form-group"><label>Country Target</label><input type="text" class="form-control" name="country_target" id="offerCountry" placeholder="Comma separated country codes"></div>
        </div>
      </form>
    </div>
    <div class="modal-footer"><button class="btn btn-ghost" onclick="closeModal('offerModal')">Cancel</button><button class="btn btn-primary" onclick="saveOffer()"><i class="fas fa-save"></i> Save</button></div>
  </div>
</div>

<script>
let offerPage = 1, compPage = 1;

function loadOffers(page) {
  page = page || offerPage; offerPage = page;
  var params = 'action=list&page=' + page;
  var s = document.getElementById('offerSearch').value; if (s) params += '&search=' + encodeURIComponent(s);
  var st = document.getElementById('offerStatus').value; if (st) params += '&status=' + st;
  document.getElementById('offersBody').innerHTML = '<tr><td colspan="7" style="text-align:center;padding:40px"><div class="loading-spinner"></div></td></tr>';
  ajaxGet('/admin/offers.php?' + params, function(res) {
    if (res.success) {
      document.getElementById('offersBody').innerHTML = res.html;
      var p = document.getElementById('offersPagination'); p.innerHTML = '';
      for (var i = 1; i <= res.pages; i++) {
        var a = document.createElement('a'); a.href = '#'; a.textContent = i;
        a.className = i === page ? 'active' : '';
        a.onclick = function(pi) { return function() { loadOffers(pi); return false; }; }(i);
        p.appendChild(a);
      }
    }
  });
}

function loadCompletions(page) {
  page = page || compPage; compPage = page;
  ajaxGet('/admin/offers.php?action=completions&page=' + page, function(res) {
    if (res.success) {
      document.getElementById('completionsBody').innerHTML = res.html;
      var p = document.getElementById('completionsPagination'); p.innerHTML = '';
      for (var i = 1; i <= res.pages; i++) {
        var a = document.createElement('a'); a.href = '#'; a.textContent = i;
        a.className = i === page ? 'active' : '';
        a.onclick = function(pi) { return function() { loadCompletions(pi); return false; }; }(i);
        p.appendChild(a);
      }
    }
  });
}

function openOfferModal(id) {
  document.getElementById('offerForm').reset();
  document.getElementById('offerId').value = '';
  document.getElementById('offerModalTitle').textContent = 'Add Offer';
  if (id) {
    ajaxGet('/admin/offers.php?action=get&id=' + id, function(res) {
      if (res.success && res.offer) {
        var o = res.offer;
        document.getElementById('offerId').value = o.id;
        document.getElementById('offerTitle').value = o.title;
        document.getElementById('offerDesc').value = o.description || '';
        document.getElementById('offerProvider').value = o.provider;
        document.getElementById('offerOid').value = o.offer_id || '';
        document.getElementById('offerPayout').value = o.payout_amount;
        document.getElementById('offerPayoutType').value = o.payout_type || '';
        document.getElementById('offerCategory').value = o.category || '';
        document.getElementById('offerUrl').value = o.url || '';
        document.getElementById('offerDevice').value = o.device_type || '';
        document.getElementById('offerCountry').value = o.country_target || '';
        document.getElementById('offerModalTitle').textContent = 'Edit Offer';
        openModal('offerModal');
      }
    });
  } else { openModal('offerModal'); }
}

function editOffer(id) { openOfferModal(id); }

function saveOffer() {
  var form = document.getElementById('offerForm');
  var data = serializeForm(form);
  data.action = 'save';
  ajax('/admin/offers.php', data, function(res) {
    if (res.success) { toast(res.message, 'success'); closeModal('offerModal'); loadOffers(1); }
    else toast(res.error, 'error');
  });
}

function toggleOffer(id) {
  ajax('/admin/offers.php', {action:'toggle', id:id}, function(res) {
    if (res.success) { toast(res.message, 'success'); loadOffers(offerPage); }
    else toast(res.error, 'error');
  });
}

function deleteOffer(id) {
  confirmAction('Delete this offer?', function() {
    ajax('/admin/offers.php', {action:'delete', id:id}, function(res) {
      if (res.success) { toast(res.message, 'success'); loadOffers(offerPage); }
      else toast(res.error, 'error');
    });
  });
}

function switchTab(el, tab) {
  document.querySelectorAll('.tabs .tab').forEach(function(t) { t.classList.remove('active'); });
  el.classList.add('active');
  document.querySelectorAll('.tab-content').forEach(function(t) { t.classList.remove('active'); });
  document.getElementById('tab-' + tab).classList.add('active');
  if (tab === 'completions') loadCompletions(1);
}

loadOffers(1);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
