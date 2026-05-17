<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAdmin();
$pageTitle = 'Tasks';
$db = getDB();

if (isAjaxRequest()) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    if ($action === 'list') {
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = 20; $offset = ($page-1)*$limit;
        $total = $db->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
        $rows = $db->query("SELECT * FROM tasks ORDER BY id DESC LIMIT $limit OFFSET $offset")->fetchAll();
        $html = '';
        foreach ($rows as $r) {
            $html .= '<tr><td>'.$r['id'].'</td><td>'.sanitize($r['title']).'</td><td><span class="badge badge-info">'.$r['type'].'</span></td><td style="font-family:var(--font-mono);color:var(--secondary)">'.formatCurrency($r['reward_amount']).'</td><td><span class="badge badge-'.($r['status']==='active'?'success':'neutral').'">'.$r['status'].'</span></td><td>'.$r['verification_type'].'</td><td><button class="btn btn-sm btn-ghost" onclick="editTask('.$r['id'].')"><i class="fas fa-edit"></i></button><button class="btn btn-sm btn-ghost" onclick="deleteTask('.$r['id'].')" style="color:#ff6b6b"><i class="fas fa-trash"></i></button></td></tr>';
        }
        jsonResponse(['success'=>true,'html'=>$html,'total'=>$total,'pages'=>ceil($total/$limit)]);
    }
    
    if ($action === 'save') {
        $id = intval($_POST['id'] ?? 0);
        $data = ['title'=>$_POST['title'],'description'=>$_POST['description'],'type'=>$_POST['type'],'reward_amount'=>floatval($_POST['reward_amount']),'url'=>$_POST['url'],'requirements'=>$_POST['requirements'],'verification_type'=>$_POST['verification_type']];
        if ($id) {
            $sets = []; $p = [];
            foreach ($data as $k => $v) { $sets[] = "$k=?"; $p[] = $v; }
            $p[] = $id;
            $db->prepare("UPDATE tasks SET " . implode(',', $sets) . " WHERE id=?")->execute($p);
        } else {
            $data['status'] = 'active';
            $db->prepare("INSERT INTO tasks (title,description,type,reward_amount,url,requirements,verification_type,status) VALUES (?,?,?,?,?,?,?,?)")->execute(array_values($data));
        }
        logAdminAction($_SESSION['user_id'], ($id?'Updated':'Created').' task');
        jsonResponse(['success'=>true,'message'=>'Task saved']);
    }
    
    if ($action === 'get') {
        $stmt = $db->prepare("SELECT * FROM tasks WHERE id=?");
        $stmt->execute([intval($_GET['id'])]);
        jsonResponse(['success'=>true,'task'=>$stmt->fetch()]);
    }
    
    if ($action === 'delete') {
        $db->prepare("DELETE FROM tasks WHERE id=?")->execute([intval($_POST['id'])]);
        jsonResponse(['success'=>true,'message'=>'Task deleted']);
    }
    
    if ($action === 'submissions') {
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = 20; $offset = ($page-1)*$limit;
        $status = $_GET['status'] ?? 'pending';
        $where = $status !== 'all' ? "WHERE ts.status='$status'" : '';
        $total = $db->query("SELECT COUNT(*) FROM task_submissions ts $where")->fetchColumn();
        $rows = $db->query("SELECT ts.*, u.username, t.title as task_title FROM task_submissions ts JOIN users u ON ts.user_id=u.id JOIN tasks t ON ts.task_id=t.id $where ORDER BY ts.id DESC LIMIT $limit OFFSET $offset")->fetchAll();
        $html = '';
        foreach ($rows as $r) {
            $html .= '<tr><td>'.$r['id'].'</td><td>'.sanitize($r['username']).'</td><td>'.sanitize($r['task_title']).'</td>
                <td>'.($r['proof_url']?'<a href="'.sanitize($r['proof_url']).'" target="_blank" class="btn btn-sm btn-outline"><i class="fas fa-external-link"></i> View</a>':'<span style="color:var(--text-muted)">N/A</span>').'</td>
                <td><span class="badge badge-'.($r['status']==='pending'?'warning':($r['status']==='approved'?'success':'danger')).'">'.$r['status'].'</span></td>
                <td style="color:var(--text-muted);font-size:.78rem">'.timeAgo($r['created_at']).'</td>
                <td>';
            if ($r['status'] === 'pending') {
                $html .= '<button class="btn btn-sm btn-success" onclick="approveSubmission('.$r['id'].')"><i class="fas fa-check"></i></button> ';
                $html .= '<button class="btn btn-sm btn-danger" onclick="rejectSubmission('.$r['id'].')"><i class="fas fa-times"></i></button>';
            }
            $html .= '</td></tr>';
        }
        jsonResponse(['success'=>true,'html'=>$html,'total'=>$total,'pages'=>ceil($total/$limit)]);
    }
    
    if ($action === 'approve_submission') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT ts.*, t.reward_amount FROM task_submissions ts JOIN tasks t ON ts.task_id=t.id WHERE ts.id=? AND ts.status='pending'");
        $stmt->execute([$id]);
        $sub = $stmt->fetch();
        if (!$sub) errorResponse('Submission not found');
        $db->beginTransaction();
        try {
            $db->prepare("UPDATE task_submissions SET status='approved', reviewed_by=?, reviewed_at=NOW() WHERE id=?")->execute([$_SESSION['user_id'], $id]);
            addEarning($sub['user_id'], 'task', $sub['reward_amount'], 'Task: ' . $sub['task_id']);
            addNotification($sub['user_id'], 'task', 'Task Approved', 'Your task submission has been approved.');
            $db->commit();
            logAdminAction($_SESSION['user_id'], 'Approved task submission #'.$id);
            jsonResponse(['success'=>true,'message'=>'Submission approved']);
        } catch (Exception $e) { $db->rollback(); errorResponse('Error approving'); }
    }
    
    if ($action === 'reject_submission') {
        $id = intval($_POST['id'] ?? 0);
        $reason = $_POST['reason'] ?? '';
        $stmt = $db->prepare("SELECT user_id FROM task_submissions WHERE id=? AND status='pending'");
        $stmt->execute([$id]);
        $sub = $stmt->fetch();
        if (!$sub) errorResponse('Submission not found');
        $db->prepare("UPDATE task_submissions SET status='rejected', admin_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?")->execute([$reason, $_SESSION['user_id'], $id]);
        addNotification($sub['user_id'], 'task', 'Task Rejected', 'Your task submission was rejected. Reason: ' . $reason);
        logAdminAction($_SESSION['user_id'], 'Rejected task submission #'.$id);
        jsonResponse(['success'=>true,'message'=>'Submission rejected']);
    }
    
    errorResponse('Unknown action');
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
  <div><h2>Task Management</h2></div>
  <div class="page-actions"><button class="btn btn-primary" onclick="openTaskModal()"><i class="fas fa-plus"></i> Add Task</button></div>
</div>

<div class="tabs">
  <button class="tab active" onclick="switchTab(this,'tasks')">Tasks</button>
  <button class="tab" onclick="switchTab(this,'submissions')">Submissions</button>
</div>

<div class="tab-content active" id="tab-tasks">
  <div class="card"><div class="card-body">
    <div class="table-wrap"><table><thead><tr><th>ID</th><th>Title</th><th>Type</th><th>Reward</th><th>Status</th><th>Verification</th><th>Actions</th></tr></thead><tbody id="tasksBody"></tbody></table></div>
    <div class="pagination" id="tasksPagination"></div>
  </div></div>
</div>

<div class="tab-content" id="tab-submissions">
  <div class="card"><div class="card-body">
    <div class="filter-bar">
      <select class="form-control" id="subStatus" onchange="loadSubmissions()"><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option><option value="all">All</option></select>
    </div>
    <div class="table-wrap"><table><thead><tr><th>ID</th><th>User</th><th>Task</th><th>Proof</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead><tbody id="submissionsBody"></tbody></table></div>
    <div class="pagination" id="submissionsPagination"></div>
  </div></div>
</div>

<!-- Task Modal -->
<div class="modal-overlay" id="taskModal">
  <div class="modal modal-lg"><div class="modal-header"><h3 id="taskModalTitle">Add Task</h3><button class="modal-close" onclick="closeModal('taskModal')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body">
      <form id="taskForm">
        <input type="hidden" name="id" id="taskId">
        <div class="form-row">
          <div class="form-group"><label>Title</label><input type="text" class="form-control" name="title" id="taskTitle" required></div>
          <div class="form-group"><label>Type</label><select class="form-control" name="type" id="taskType"><option value="social">Social</option><option value="youtube">YouTube</option><option value="telegram">Telegram</option><option value="discord">Discord</option><option value="twitter">Twitter</option><option value="instagram">Instagram</option><option value="facebook">Facebook</option><option value="custom">Custom</option></select></div>
        </div>
        <div class="form-group"><label>Description</label><textarea class="form-control" name="description" id="taskDesc" rows="3"></textarea></div>
        <div class="form-row">
          <div class="form-group"><label>Reward Amount</label><input type="number" step="0.0001" class="form-control" name="reward_amount" id="taskReward" required></div>
          <div class="form-group"><label>Verification Type</label><select class="form-control" name="verification_type" id="taskVerification"><option value="auto">Auto</option><option value="manual">Manual</option></select></div>
        </div>
        <div class="form-group"><label>URL</label><input type="url" class="form-control" name="url" id="taskUrl"></div>
        <div class="form-group"><label>Requirements</label><textarea class="form-control" name="requirements" id="taskRequirements" rows="2" placeholder="Task requirements description"></textarea></div>
      </form>
    </div>
    <div class="modal-footer"><button class="btn btn-ghost" onclick="closeModal('taskModal')">Cancel</button><button class="btn btn-primary" onclick="saveTask()"><i class="fas fa-save"></i> Save</button></div>
  </div>
</div>

<!-- Reject Submission Modal -->
<div class="modal-overlay" id="rejectSubModal">
  <div class="modal modal-sm"><div class="modal-header"><h3>Reject Submission</h3><button class="modal-close" onclick="closeModal('rejectSubModal')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body"><input type="hidden" id="rejectSubId"><div class="form-group"><label>Reason</label><textarea class="form-control" id="rejectSubReason" rows="3" required></textarea></div></div>
    <div class="modal-footer"><button class="btn btn-ghost" onclick="closeModal('rejectSubModal')">Cancel</button><button class="btn btn-danger" onclick="confirmRejectSub()"><i class="fas fa-times"></i> Reject</button></div>
  </div>
</div>

<script>
let tasksPage = 1, subPage = 1;

function loadTasks(page) {
  page = page || tasksPage; tasksPage = page;
  ajaxGet('/admin/tasks.php?action=list&page=' + page, function(res) {
    if (res.success) {
      document.getElementById('tasksBody').innerHTML = res.html;
      var p = document.getElementById('tasksPagination'); p.innerHTML = '';
      for (var i = 1; i <= res.pages; i++) {
        var a = document.createElement('a'); a.href = '#'; a.textContent = i;
        a.className = i === page ? 'active' : '';
        a.onclick = function(pi) { return function() { loadTasks(pi); return false; }; }(i); p.appendChild(a);
      }
    }
  });
}

function loadSubmissions(page) {
  page = page || subPage; subPage = page;
  var status = document.getElementById('subStatus').value;
  ajaxGet('/admin/tasks.php?action=submissions&page=' + page + '&status=' + status, function(res) {
    if (res.success) {
      document.getElementById('submissionsBody').innerHTML = res.html;
      var p = document.getElementById('submissionsPagination'); p.innerHTML = '';
      for (var i = 1; i <= res.pages; i++) {
        var a = document.createElement('a'); a.href = '#'; a.textContent = i;
        a.className = i === page ? 'active' : '';
        a.onclick = function(pi) { return function() { loadSubmissions(pi); return false; }; }(i); p.appendChild(a);
      }
    }
  });
}

function openTaskModal(id) {
  document.getElementById('taskForm').reset();
  document.getElementById('taskId').value = '';
  document.getElementById('taskModalTitle').textContent = 'Add Task';
  if (id) {
    ajaxGet('/admin/tasks.php?action=get&id=' + id, function(res) {
      if (res.success && res.task) {
        var t = res.task;
        document.getElementById('taskId').value = t.id;
        document.getElementById('taskTitle').value = t.title;
        document.getElementById('taskType').value = t.type;
        document.getElementById('taskDesc').value = t.description || '';
        document.getElementById('taskReward').value = t.reward_amount;
        document.getElementById('taskVerification').value = t.verification_type;
        document.getElementById('taskUrl').value = t.url || '';
        document.getElementById('taskRequirements').value = t.requirements || '';
        document.getElementById('taskModalTitle').textContent = 'Edit Task';
        openModal('taskModal');
      }
    });
  } else { openModal('taskModal'); }
}

function editTask(id) { openTaskModal(id); }

function saveTask() {
  var data = serializeForm(document.getElementById('taskForm'));
  data.action = 'save';
  ajax('/admin/tasks.php', data, function(res) {
    if (res.success) { toast(res.message, 'success'); closeModal('taskModal'); loadTasks(1); }
    else toast(res.error, 'error');
  });
}

function deleteTask(id) {
  confirmAction('Delete this task?', function() {
    ajax('/admin/tasks.php', {action:'delete', id:id}, function(res) {
      if (res.success) { toast(res.message, 'success'); loadTasks(tasksPage); }
      else toast(res.error, 'error');
    });
  });
}

function approveSubmission(id) {
  ajax('/admin/tasks.php', {action:'approve_submission', id:id}, function(res) {
    if (res.success) { toast(res.message, 'success'); loadSubmissions(subPage); }
    else toast(res.error, 'error');
  });
}

function rejectSubmission(id) {
  document.getElementById('rejectSubId').value = id;
  document.getElementById('rejectSubReason').value = '';
  openModal('rejectSubModal');
}

function confirmRejectSub() {
  var id = document.getElementById('rejectSubId').value;
  var reason = document.getElementById('rejectSubReason').value;
  if (!reason) { toast('Please enter a reason', 'error'); return; }
  ajax('/admin/tasks.php', {action:'reject_submission', id:id, reason:reason}, function(res) {
    if (res.success) { toast(res.message, 'success'); closeModal('rejectSubModal'); loadSubmissions(subPage); }
    else toast(res.error, 'error');
  });
}

function switchTab(el, tab) {
  document.querySelectorAll('.tabs .tab').forEach(function(t) { t.classList.remove('active'); });
  el.classList.add('active');
  document.querySelectorAll('.tab-content').forEach(function(t) { t.classList.remove('active'); });
  document.getElementById('tab-' + tab).classList.add('active');
  if (tab === 'submissions') loadSubmissions(1);
}

loadTasks(1);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
