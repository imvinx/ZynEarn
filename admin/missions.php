<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAdmin();
$pageTitle = 'Missions';
$db = getDB();

if (isAjaxRequest()) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    if ($action === 'list') {
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = 20; $offset = ($page-1)*$limit;
        $total = $db->query("SELECT COUNT(*) FROM missions")->fetchColumn();
        $rows = $db->query("SELECT * FROM missions ORDER BY id DESC LIMIT $limit OFFSET $offset")->fetchAll();
        $html = '';
        foreach ($rows as $r) {
            $rewards = json_decode($r['rewards'], true);
            $rewardStr = $rewards ? (isset($rewards['amount']) ? formatCurrency($rewards['amount']) : (isset($rewards[0]['amount']) ? formatCurrency($rewards[0]['amount']) : 'See rewards')) : 'N/A';
            $html .= '<tr>
                <td>'.$r['id'].'</td>
                <td>'.sanitize($r['title']).'</td>
                <td><span class="badge badge-'.($r['duration_type']==='daily'?'info':($r['duration_type']==='weekly'?'warning':'primary')).'">'.$r['duration_type'].'</span></td>
                <td>'.$rewardStr.'</td>
                <td><span class="badge badge-'.($r['status']==='active'?'success':'neutral').'">'.$r['status'].'</span></td>
                <td style="color:var(--text-muted);font-size:.78rem">'.($r['start_date']?date('M d',strtotime($r['start_date'])):'-').'</td>
                <td style="color:var(--text-muted);font-size:.78rem">'.($r['end_date']?date('M d',strtotime($r['end_date'])):'-').'</td>
                <td><button class="btn btn-sm btn-ghost" onclick="editMission('.$r['id'].')"><i class="fas fa-edit"></i></button><button class="btn btn-sm btn-ghost" onclick="deleteMission('.$r['id'].')" style="color:#ff6b6b"><i class="fas fa-trash"></i></button></td>
            </tr>';
        }
        jsonResponse(['success'=>true,'html'=>$html,'total'=>$total,'pages'=>ceil($total/$limit)]);
    }
    
    if ($action === 'save') {
        $id = intval($_POST['id'] ?? 0);
        $requirements = json_encode(['type'=>$_POST['req_type']??'earnings','value'=>intval($_POST['req_value']??0)]);
        $rewards = json_encode(['amount'=>floatval($_POST['reward_amount']??0),'xp'=>intval($_POST['xp_reward']??0),'type'=>'coins']);
        $data = ['title'=>$_POST['title'],'description'=>$_POST['description'],'requirements'=>$requirements,'rewards'=>$rewards,'duration_type'=>$_POST['duration_type'],'start_date'=>$_POST['start_date']?:null,'end_date'=>$_POST['end_date']?:null];
        if ($id) {
            $sets = []; $p = [];
            foreach ($data as $k=>$v) { $sets[] = "$k=?"; $p[] = $v; }
            $p[] = $id;
            $db->prepare("UPDATE missions SET " . implode(',', $sets) . " WHERE id=?")->execute($p);
        } else {
            $data['status'] = 'active';
            $db->prepare("INSERT INTO missions (title,description,requirements,rewards,duration_type,start_date,end_date,status) VALUES (?,?,?,?,?,?,?,?)")->execute(array_values($data));
        }
        logAdminAction($_SESSION['user_id'], ($id?'Updated':'Created').' mission');
        jsonResponse(['success'=>true,'message'=>'Mission saved']);
    }
    
    if ($action === 'get') {
        $stmt = $db->prepare("SELECT * FROM missions WHERE id=?");
        $stmt->execute([intval($_GET['id'])]);
        $m = $stmt->fetch();
        $req = json_decode($m['requirements'], true);
        $rew = json_decode($m['rewards'], true);
        $m['req_type'] = $req['type'] ?? 'earnings';
        $m['req_value'] = $req['value'] ?? 0;
        $m['reward_amount'] = $rew['amount'] ?? 0;
        $m['xp_reward'] = $rew['xp'] ?? 0;
        jsonResponse(['success'=>true,'mission'=>$m]);
    }
    
    if ($action === 'delete') {
        $db->prepare("DELETE FROM missions WHERE id=?")->execute([intval($_POST['id'])]);
        $db->prepare("DELETE FROM user_missions WHERE mission_id=?")->execute([intval($_POST['id'])]);
        jsonResponse(['success'=>true,'message'=>'Mission deleted']);
    }
    
    if ($action === 'progress') {
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = 20; $offset = ($page-1)*$limit;
        $total = $db->query("SELECT COUNT(*) FROM user_missions")->fetchColumn();
        $rows = $db->query("SELECT um.*, u.username, m.title FROM user_missions um JOIN users u ON um.user_id=u.id JOIN missions m ON um.mission_id=m.id ORDER BY um.id DESC LIMIT $limit OFFSET $offset")->fetchAll();
        $html = '';
        foreach ($rows as $r) {
            $html .= '<tr><td>'.$r['id'].'</td><td>'.sanitize($r['username']).'</td><td>'.sanitize($r['title']).'</td><td>'.$r['progress'].'</td><td><span class="badge badge-'.($r['completed']?'success':'warning').'">'.($r['completed']?'Completed':'In Progress').'</span></td><td style="color:var(--text-muted);font-size:.78rem">'.($r['completed_at']?timeAgo($r['completed_at']):'-').'</td></tr>';
        }
        jsonResponse(['success'=>true,'html'=>$html,'total'=>$total,'pages'=>ceil($total/$limit)]);
    }
    
    errorResponse('Unknown action');
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
  <div><h2>Missions</h2></div>
  <div class="page-actions"><button class="btn btn-primary" onclick="openMissionModal()"><i class="fas fa-plus"></i> Add Mission</button></div>
</div>

<div class="tabs">
  <button class="tab active" onclick="switchTab(this,'missions')">Missions</button>
  <button class="tab" onclick="switchTab(this,'progress')">User Progress</button>
</div>

<div class="tab-content active" id="tab-missions">
  <div class="card"><div class="card-body">
    <div class="table-wrap"><table><thead><tr><th>ID</th><th>Title</th><th>Duration</th><th>Reward</th><th>Status</th><th>Start</th><th>End</th><th>Actions</th></tr></thead><tbody id="missionsBody"></tbody></table></div>
    <div class="pagination" id="missionsPagination"></div>
  </div></div>
</div>

<div class="tab-content" id="tab-progress">
  <div class="card"><div class="card-body">
    <div class="table-wrap"><table><thead><tr><th>ID</th><th>User</th><th>Mission</th><th>Progress</th><th>Status</th><th>Completed</th></tr></thead><tbody id="progressBody"></tbody></table></div>
    <div class="pagination" id="progressPagination"></div>
  </div></div>
</div>

<!-- Mission Modal -->
<div class="modal-overlay" id="missionModal">
  <div class="modal"><div class="modal-header"><h3 id="missionModalTitle">Add Mission</h3><button class="modal-close" onclick="closeModal('missionModal')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body">
      <form id="missionForm">
        <input type="hidden" name="id" id="missionId">
        <div class="form-group"><label>Title</label><input type="text" class="form-control" name="title" id="missionTitle" required></div>
        <div class="form-group"><label>Description</label><textarea class="form-control" name="description" id="missionDesc" rows="2"></textarea></div>
        <div class="form-row">
          <div class="form-group"><label>Duration Type</label><select class="form-control" name="duration_type" id="missionDuration"><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option></select></div>
          <div class="form-group"><label>Status</label><select class="form-control" name="status" id="missionStatus"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Requirement Type</label><select class="form-control" name="req_type" id="missionReqType"><option value="earnings">Earnings</option><option value="tasks">Tasks</option><option value="referrals">Referrals</option><option value="clicks">Clicks</option></select></div>
          <div class="form-group"><label>Requirement Value</label><input type="number" class="form-control" name="req_value" id="missionReqValue" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Reward Amount</label><input type="number" step="0.0001" class="form-control" name="reward_amount" id="missionReward" value="0"></div>
          <div class="form-group"><label>XP Reward</label><input type="number" class="form-control" name="xp_reward" id="missionXp" value="0"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Start Date</label><input type="date" class="form-control" name="start_date" id="missionStart"></div>
          <div class="form-group"><label>End Date</label><input type="date" class="form-control" name="end_date" id="missionEnd"></div>
        </div>
      </form>
    </div>
    <div class="modal-footer"><button class="btn btn-ghost" onclick="closeModal('missionModal')">Cancel</button><button class="btn btn-primary" onclick="saveMission()"><i class="fas fa-save"></i> Save</button></div>
  </div>
</div>

<script>
let missionPage = 1, progressPage = 1;

function loadMissions(page) {
  page = page || missionPage; missionPage = page;
  ajaxGet('/admin/missions.php?action=list&page=' + page, function(res) {
    if (res.success) {
      document.getElementById('missionsBody').innerHTML = res.html;
      var p = document.getElementById('missionsPagination'); p.innerHTML = '';
      for (var i = 1; i <= res.pages; i++) {
        var a = document.createElement('a'); a.href = '#'; a.textContent = i;
        a.className = i === page ? 'active' : '';
        a.onclick = function(pi) { return function() { loadMissions(pi); return false; }; }(i); p.appendChild(a);
      }
    }
  });
}

function loadProgress(page) {
  page = page || progressPage; progressPage = page;
  ajaxGet('/admin/missions.php?action=progress&page=' + page, function(res) {
    if (res.success) {
      document.getElementById('progressBody').innerHTML = res.html;
      var p = document.getElementById('progressPagination'); p.innerHTML = '';
      for (var i = 1; i <= res.pages; i++) {
        var a = document.createElement('a'); a.href = '#'; a.textContent = i;
        a.className = i === page ? 'active' : '';
        a.onclick = function(pi) { return function() { loadProgress(pi); return false; }; }(i); p.appendChild(a);
      }
    }
  });
}

function openMissionModal(id) {
  document.getElementById('missionForm').reset();
  document.getElementById('missionId').value = '';
  document.getElementById('missionModalTitle').textContent = 'Add Mission';
  if (id) {
    ajaxGet('/admin/missions.php?action=get&id=' + id, function(res) {
      if (res.success && res.mission) {
        var m = res.mission;
        document.getElementById('missionId').value = m.id;
        document.getElementById('missionTitle').value = m.title;
        document.getElementById('missionDesc').value = m.description || '';
        document.getElementById('missionDuration').value = m.duration_type;
        document.getElementById('missionStatus').value = m.status;
        document.getElementById('missionReqType').value = m.req_type || 'earnings';
        document.getElementById('missionReqValue').value = m.req_value || 0;
        document.getElementById('missionReward').value = m.reward_amount || 0;
        document.getElementById('missionXp').value = m.xp_reward || 0;
        document.getElementById('missionStart').value = m.start_date ? m.start_date.substring(0,10) : '';
        document.getElementById('missionEnd').value = m.end_date ? m.end_date.substring(0,10) : '';
        document.getElementById('missionModalTitle').textContent = 'Edit Mission';
        openModal('missionModal');
      }
    });
  } else { openModal('missionModal'); }
}

function editMission(id) { openMissionModal(id); }

function saveMission() {
  var data = serializeForm(document.getElementById('missionForm'));
  data.action = 'save';
  ajax('/admin/missions.php', data, function(res) {
    if (res.success) { toast(res.message, 'success'); closeModal('missionModal'); loadMissions(1); }
    else toast(res.error, 'error');
  });
}

function deleteMission(id) {
  confirmAction('Delete this mission?', function() {
    ajax('/admin/missions.php', {action:'delete', id:id}, function(res) {
      if (res.success) { toast(res.message, 'success'); loadMissions(missionPage); }
      else toast(res.error, 'error');
    });
  });
}

function switchTab(el, tab) {
  document.querySelectorAll('.tabs .tab').forEach(function(t) { t.classList.remove('active'); });
  el.classList.add('active');
  document.querySelectorAll('.tab-content').forEach(function(t) { t.classList.remove('active'); });
  document.getElementById('tab-' + tab).classList.add('active');
  if (tab === 'progress') loadProgress(1);
}

loadMissions(1);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
