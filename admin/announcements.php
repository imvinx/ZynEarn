<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAdmin();
$pageTitle = 'Announcements';
$db = getDB();

if (isAjaxRequest()) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    if ($action === 'list') {
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = 20; $offset = ($page-1)*$limit;
        $total = $db->query("SELECT COUNT(*) FROM announcements")->fetchColumn();
        $rows = $db->query("SELECT * FROM announcements ORDER BY id DESC LIMIT $limit OFFSET $offset")->fetchAll();
        $html = '';
        foreach ($rows as $r) {
            $html .= '<tr><td>'.$r['id'].'</td><td>'.sanitize($r['title']).'</td><td><span class="badge badge-'.($r['type']==='info'?'info':($r['type']==='success'?'success':($r['type']==='danger'?'danger':'warning'))).'">'.$r['type'].'</span></td><td><span class="badge badge-info">'.$r['target'].'</span></td><td><span class="badge badge-'.($r['status']==='active'?'success':'neutral').'">'.$r['status'].'</span></td><td style="color:var(--text-muted);font-size:.78rem">'.timeAgo($r['created_at']).'</td><td><button class="btn btn-sm btn-ghost" onclick="editAnnouncement('.$r['id'].')"><i class="fas fa-edit"></i></button><button class="btn btn-sm btn-ghost" onclick="deleteAnnouncement('.$r['id'].')" style="color:#ff6b6b"><i class="fas fa-trash"></i></button></td></tr>';
        }
        jsonResponse(['success'=>true,'html'=>$html,'total'=>$total,'pages'=>ceil($total/$limit)]);
    }
    
    if ($action === 'save') {
        $id = intval($_POST['id'] ?? 0);
        $title = $_POST['title']; $message = $_POST['message']; $type = $_POST['type']; $target = $_POST['target'];
        if ($id) {
            $db->prepare("UPDATE announcements SET title=?, message=?, type=?, target=? WHERE id=?")->execute([$title,$message,$type,$target,$id]);
        } else {
            $db->prepare("INSERT INTO announcements (title,message,type,target,status) VALUES (?,?,?,?,'active')")->execute([$title,$message,$type,$target]);
            $id = $db->lastInsertId();
            // Send notification to users
            $notifStmt = $db->prepare("INSERT INTO notifications (user_id, type, title, message) SELECT id, 'announcement', ?, ? FROM users WHERE 1=1 " . ($target === 'admins' ? "AND role='admin'" : "AND role='user'"));
            $notifStmt->execute([$title, $message]);
        }
        logAdminAction($_SESSION['user_id'], ($id?'Updated':'Created').' announcement #'.$id);
        jsonResponse(['success'=>true,'message'=>'Announcement saved']);
    }
    
    if ($action === 'get') {
        $stmt = $db->prepare("SELECT * FROM announcements WHERE id=?");
        $stmt->execute([intval($_GET['id'])]);
        jsonResponse(['success'=>true,'announcement'=>$stmt->fetch()]);
    }
    
    if ($action === 'delete') {
        $db->prepare("DELETE FROM announcements WHERE id=?")->execute([intval($_POST['id'])]);
        jsonResponse(['success'=>true,'message'=>'Announcement deleted']);
    }
    
    errorResponse('Unknown action');
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
  <div><h2>Announcements</h2></div>
  <div class="page-actions"><button class="btn btn-primary" onclick="openAnnounceModal()"><i class="fas fa-plus"></i> New Announcement</button></div>
</div>

<div class="card"><div class="card-body">
  <div class="table-wrap"><table><thead><tr><th>ID</th><th>Title</th><th>Type</th><th>Target</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead><tbody id="announceBody"></tbody></table></div>
  <div class="pagination" id="announcePagination"></div>
</div></div>

<!-- Announcement Modal -->
<div class="modal-overlay" id="announceModal">
  <div class="modal"><div class="modal-header"><h3 id="announceModalTitle">New Announcement</h3><button class="modal-close" onclick="closeModal('announceModal')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body">
      <form id="announceForm">
        <input type="hidden" name="id" id="announceId">
        <div class="form-group"><label>Title</label><input type="text" class="form-control" name="title" id="announceTitle" required></div>
        <div class="form-group"><label>Message</label><textarea class="form-control" name="message" id="announceMessage" rows="4" required></textarea></div>
        <div class="form-row">
          <div class="form-group"><label>Type</label><select class="form-control" name="type" id="announceType"><option value="info">Info</option><option value="success">Success</option><option value="warning">Warning</option><option value="danger">Danger</option></select></div>
          <div class="form-group"><label>Target</label><select class="form-control" name="target" id="announceTarget"><option value="all">All Users</option><option value="users">Users Only</option><option value="admins">Admins Only</option></select></div>
        </div>
      </form>
    </div>
    <div class="modal-footer"><button class="btn btn-ghost" onclick="closeModal('announceModal')">Cancel</button><button class="btn btn-primary" onclick="saveAnnouncement()"><i class="fas fa-paper-plane"></i> Publish</button></div>
  </div>
</div>

<script>
let annPage = 1;
function loadAnnouncements(page) {
  page = page || annPage; annPage = page;
  ajaxGet('/admin/announcements.php?action=list&page=' + page, function(res) {
    if (res.success) {
      document.getElementById('announceBody').innerHTML = res.html;
      var p = document.getElementById('announcePagination'); p.innerHTML = '';
      for (var i = 1; i <= res.pages; i++) {
        var a = document.createElement('a'); a.href = '#'; a.textContent = i;
        a.className = i === page ? 'active' : '';
        a.onclick = function(pi) { return function() { loadAnnouncements(pi); return false; }; }(i); p.appendChild(a);
      }
    }
  });
}

function openAnnounceModal(id) {
  document.getElementById('announceForm').reset();
  document.getElementById('announceId').value = '';
  document.getElementById('announceModalTitle').textContent = 'New Announcement';
  if (id) {
    ajaxGet('/admin/announcements.php?action=get&id=' + id, function(res) {
      if (res.success && res.announcement) {
        var a = res.announcement;
        document.getElementById('announceId').value = a.id;
        document.getElementById('announceTitle').value = a.title;
        document.getElementById('announceMessage').value = a.message;
        document.getElementById('announceType').value = a.type;
        document.getElementById('announceTarget').value = a.target;
        document.getElementById('announceModalTitle').textContent = 'Edit Announcement';
        openModal('announceModal');
      }
    });
  } else { openModal('announceModal'); }
}

function editAnnouncement(id) { openAnnounceModal(id); }

function saveAnnouncement() {
  var data = serializeForm(document.getElementById('announceForm'));
  data.action = 'save';
  ajax('/admin/announcements.php', data, function(res) {
    if (res.success) { toast(res.message, 'success'); closeModal('announceModal'); loadAnnouncements(1); }
    else toast(res.error, 'error');
  });
}

function deleteAnnouncement(id) {
  confirmAction('Delete this announcement?', function() {
    ajax('/admin/announcements.php', {action:'delete', id:id}, function(res) {
      if (res.success) { toast(res.message, 'success'); loadAnnouncements(annPage); }
      else toast(res.error, 'error');
    });
  });
}

loadAnnouncements(1);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
