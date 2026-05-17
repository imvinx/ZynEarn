<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAdmin();
$pageTitle = 'Achievements';
$db = getDB();

if (isAjaxRequest()) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    if ($action === 'list') {
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = 20; $offset = ($page-1)*$limit;
        $total = $db->query("SELECT COUNT(*) FROM achievements")->fetchColumn();
        $rows = $db->query("SELECT * FROM achievements ORDER BY id DESC LIMIT $limit OFFSET $offset")->fetchAll();
        $html = '';
        foreach ($rows as $r) {
            $unlocked = $db->prepare("SELECT COUNT(*) FROM user_achievements WHERE achievement_id=?");
            $unlocked->execute([$r['id']]); $unl = $unlocked->fetchColumn();
            $html .= '<tr>
                <td>'.$r['id'].'</td>
                <td>'.sanitize($r['name']).'</td>
                <td style="color:var(--text-secondary);font-size:.8rem">'.substr(sanitize($r['description']),0,60).'...</td>
                <td><span class="badge badge-info">'.$r['requirement_type'].'</span></td>
                <td style="font-family:var(--font-mono)">'.$r['requirement_value'].'</td>
                <td style="font-family:var(--font-mono);color:var(--secondary)">'.formatCurrency($r['reward_amount']).'</td>
                <td>'.formatNumber($r['xp_reward']).' XP</td>
                <td><span class="badge badge-primary">'.$unl.' users</span></td>
                <td><button class="btn btn-sm btn-ghost" onclick="editAchievement('.$r['id'].')"><i class="fas fa-edit"></i></button><button class="btn btn-sm btn-ghost" onclick="deleteAchievement('.$r['id'].')" style="color:#ff6b6b"><i class="fas fa-trash"></i></button></td>
            </tr>';
        }
        jsonResponse(['success'=>true,'html'=>$html,'total'=>$total,'pages'=>ceil($total/$limit)]);
    }
    
    if ($action === 'save') {
        $id = intval($_POST['id'] ?? 0);
        $data = ['name'=>$_POST['name'],'description'=>$_POST['description'],'requirement_type'=>$_POST['requirement_type'],'requirement_value'=>intval($_POST['requirement_value']),'reward_amount'=>floatval($_POST['reward_amount']),'xp_reward'=>intval($_POST['xp_reward'])];
        if ($id) {
            $sets = []; $p = [];
            foreach ($data as $k=>$v) { $sets[] = "$k=?"; $p[] = $v; }
            $p[] = $id;
            $db->prepare("UPDATE achievements SET " . implode(',', $sets) . " WHERE id=?")->execute($p);
        } else {
            $db->prepare("INSERT INTO achievements (name,description,requirement_type,requirement_value,reward_amount,xp_reward) VALUES (?,?,?,?,?,?)")->execute(array_values($data));
        }
        logAdminAction($_SESSION['user_id'], ($id?'Updated':'Created').' achievement');
        jsonResponse(['success'=>true,'message'=>'Achievement saved']);
    }
    
    if ($action === 'get') {
        $stmt = $db->prepare("SELECT * FROM achievements WHERE id=?");
        $stmt->execute([intval($_GET['id'])]);
        jsonResponse(['success'=>true,'achievement'=>$stmt->fetch()]);
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        $db->prepare("DELETE FROM achievements WHERE id=?")->execute([$id]);
        $db->prepare("DELETE FROM user_achievements WHERE achievement_id=?")->execute([$id]);
        jsonResponse(['success'=>true,'message'=>'Achievement deleted']);
    }
    
    errorResponse('Unknown action');
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
  <div><h2>Achievements</h2></div>
  <div class="page-actions"><button class="btn btn-primary" onclick="openAchievementModal()"><i class="fas fa-plus"></i> Add Achievement</button></div>
</div>

<div class="card"><div class="card-body">
  <div class="table-wrap"><table><thead><tr><th>ID</th><th>Name</th><th>Description</th><th>Type</th><th>Requirement</th><th>Reward</th><th>XP</th><th>Unlocked</th><th>Actions</th></tr></thead><tbody id="achieveBody"></tbody></table></div>
  <div class="pagination" id="achievePagination"></div>
</div></div>

<!-- Achievement Modal -->
<div class="modal-overlay" id="achieveModal">
  <div class="modal"><div class="modal-header"><h3 id="achieveModalTitle">Add Achievement</h3><button class="modal-close" onclick="closeModal('achieveModal')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body">
      <form id="achieveForm">
        <input type="hidden" name="id" id="achieveId">
        <div class="form-group"><label>Name</label><input type="text" class="form-control" name="name" id="achieveName" required></div>
        <div class="form-group"><label>Description</label><textarea class="form-control" name="description" id="achieveDesc" rows="2"></textarea></div>
        <div class="form-row">
          <div class="form-group"><label>Requirement Type</label><select class="form-control" name="requirement_type" id="achieveType"><option value="earnings">Total Earnings</option><option value="referrals">Referrals</option><option value="level">Level</option><option value="streak">Login Streak</option><option value="tasks_completed">Tasks Completed</option><option value="shortlink_clicks">Shortlink Clicks</option><option value="faucet_claims">Faucet Claims</option><option value="spins">Spins</option></select></div>
          <div class="form-group"><label>Requirement Value</label><input type="number" class="form-control" name="requirement_value" id="achieveValue" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Reward Amount</label><input type="number" step="0.0001" class="form-control" name="reward_amount" id="achieveReward" value="0"></div>
          <div class="form-group"><label>XP Reward</label><input type="number" class="form-control" name="xp_reward" id="achieveXp" value="0"></div>
        </div>
      </form>
    </div>
    <div class="modal-footer"><button class="btn btn-ghost" onclick="closeModal('achieveModal')">Cancel</button><button class="btn btn-primary" onclick="saveAchievement()"><i class="fas fa-save"></i> Save</button></div>
  </div>
</div>

<script>
let achievePage = 1;
function loadAchievements(page) {
  page = page || achievePage; achievePage = page;
  ajaxGet('/admin/achievements.php?action=list&page=' + page, function(res) {
    if (res.success) {
      document.getElementById('achieveBody').innerHTML = res.html;
      var p = document.getElementById('achievePagination'); p.innerHTML = '';
      for (var i = 1; i <= res.pages; i++) {
        var a = document.createElement('a'); a.href = '#'; a.textContent = i;
        a.className = i === page ? 'active' : '';
        a.onclick = function(pi) { return function() { loadAchievements(pi); return false; }; }(i); p.appendChild(a);
      }
    }
  });
}

function openAchievementModal(id) {
  document.getElementById('achieveForm').reset();
  document.getElementById('achieveId').value = '';
  document.getElementById('achieveModalTitle').textContent = 'Add Achievement';
  if (id) {
    ajaxGet('/admin/achievements.php?action=get&id=' + id, function(res) {
      if (res.success && res.achievement) {
        var a = res.achievement;
        document.getElementById('achieveId').value = a.id;
        document.getElementById('achieveName').value = a.name;
        document.getElementById('achieveDesc').value = a.description || '';
        document.getElementById('achieveType').value = a.requirement_type;
        document.getElementById('achieveValue').value = a.requirement_value;
        document.getElementById('achieveReward').value = a.reward_amount;
        document.getElementById('achieveXp').value = a.xp_reward;
        document.getElementById('achieveModalTitle').textContent = 'Edit Achievement';
        openModal('achieveModal');
      }
    });
  } else { openModal('achieveModal'); }
}

function editAchievement(id) { openAchievementModal(id); }

function saveAchievement() {
  var data = serializeForm(document.getElementById('achieveForm'));
  data.action = 'save';
  ajax('/admin/achievements.php', data, function(res) {
    if (res.success) { toast(res.message, 'success'); closeModal('achieveModal'); loadAchievements(1); }
    else toast(res.error, 'error');
  });
}

function deleteAchievement(id) {
  confirmAction('Delete this achievement?', function() {
    ajax('/admin/achievements.php', {action:'delete', id:id}, function(res) {
      if (res.success) { toast(res.message, 'success'); loadAchievements(achievePage); }
      else toast(res.error, 'error');
    });
  });
}

loadAchievements(1);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
