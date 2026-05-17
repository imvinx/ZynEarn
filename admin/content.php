<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAdmin();
$pageTitle = 'Content';
$db = getDB();

if (isAjaxRequest()) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    $contentType = $_POST['content_type'] ?? $_GET['content_type'] ?? 'faq';
    
    $tables = ['faq'=>'faq_items', 'blog'=>'blog_posts', 'pages'=>'pages_cms'];
    $table = $tables[$contentType] ?? 'faq_items';
    
    if ($action === 'list') {
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = 20; $offset = ($page-1)*$limit;
        $total = $db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        if ($contentType === 'faq') {
            $rows = $db->query("SELECT * FROM $table ORDER BY sort_order ASC, id DESC LIMIT $limit OFFSET $offset")->fetchAll();
            $html = '';
            foreach ($rows as $r) {
                $html .= '<tr><td>'.$r['id'].'</td><td>'.substr(sanitize($r['question']),0,60).'...</td><td>'.sanitize($r['category']?:'General').'</td><td><span class="badge badge-'.($r['status']==='active'?'success':'neutral').'">'.$r['status'].'</span></td><td><button class="btn btn-sm btn-ghost" onclick="editItem('.$r['id'].')"><i class="fas fa-edit"></i></button><button class="btn btn-sm btn-ghost" onclick="deleteItem('.$r['id'].')" style="color:#ff6b6b"><i class="fas fa-trash"></i></button></td></tr>';
            }
        } elseif ($contentType === 'blog') {
            $rows = $db->query("SELECT b.*, u.username FROM $table b LEFT JOIN users u ON b.author_id=u.id ORDER BY b.id DESC LIMIT $limit OFFSET $offset")->fetchAll();
            $html = '';
            foreach ($rows as $r) {
                $html .= '<tr><td>'.$r['id'].'</td><td>'.substr(sanitize($r['title']),0,50).'...</td><td>'.sanitize($r['category']?:'Uncategorized').'</td><td><span class="badge badge-'.($r['status']==='published'?'success':'warning').'">'.$r['status'].'</span></td><td>'.sanitize($r['username']??'N/A').'</td><td><button class="btn btn-sm btn-ghost" onclick="editItem('.$r['id'].')"><i class="fas fa-edit"></i></button><button class="btn btn-sm btn-ghost" onclick="deleteItem('.$r['id'].')" style="color:#ff6b6b"><i class="fas fa-trash"></i></button></td></tr>';
            }
        } else {
            $rows = $db->query("SELECT * FROM $table ORDER BY id DESC LIMIT $limit OFFSET $offset")->fetchAll();
            $html = '';
            foreach ($rows as $r) {
                $html .= '<tr><td>'.$r['id'].'</td><td>'.sanitize($r['title']).'</td><td>/'.sanitize($r['slug']).'</td><td><span class="badge badge-'.($r['status']==='published'?'success':'warning').'">'.$r['status'].'</span></td><td><button class="btn btn-sm btn-ghost" onclick="editItem('.$r['id'].')"><i class="fas fa-edit"></i></button><button class="btn btn-sm btn-ghost" onclick="deleteItem('.$r['id'].')" style="color:#ff6b6b"><i class="fas fa-trash"></i></button></td></tr>';
            }
        }
        jsonResponse(['success'=>true,'html'=>$html,'total'=>$total,'pages'=>ceil($total/$limit)]);
    }
    
    if ($action === 'get') {
        $id = intval($_GET['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM $table WHERE id=?");
        $stmt->execute([$id]);
        jsonResponse(['success'=>true,'item'=>$stmt->fetch()]);
    }
    
    if ($action === 'save') {
        $id = intval($_POST['id'] ?? 0);
        if ($contentType === 'faq') {
            $question = $_POST['question']; $answer = $_POST['answer']; $category = $_POST['category']; $sort = intval($_POST['sort_order']??0);
            if ($id) $db->prepare("UPDATE $table SET question=?, answer=?, category=?, sort_order=? WHERE id=?")->execute([$question,$answer,$category,$sort,$id]);
            else $db->prepare("INSERT INTO $table (question,answer,category,sort_order,status) VALUES (?,?,?,?,'active')")->execute([$question,$answer,$category,$sort]);
        } elseif ($contentType === 'blog') {
            $title = $_POST['title']; $slug = $_POST['slug']; $content = $_POST['content']; $excerpt = $_POST['excerpt']; $category = $_POST['category']; $status = $_POST['status'];
            if ($id) $db->prepare("UPDATE $table SET title=?, slug=?, content=?, excerpt=?, category=?, status=? WHERE id=?")->execute([$title,$slug,$content,$excerpt,$category,$status,$id]);
            else $db->prepare("INSERT INTO $table (title,slug,content,excerpt,category,status,author_id) VALUES (?,?,?,?,?,?,?)")->execute([$title,$slug,$content,$excerpt,$category,$status,$_SESSION['user_id']]);
        } else {
            $title = $_POST['title']; $slug = $_POST['slug']; $content = $_POST['content']; $status = $_POST['status'];
            if ($id) $db->prepare("UPDATE $table SET title=?, slug=?, content=?, status=? WHERE id=?")->execute([$title,$slug,$content,$status,$id]);
            else $db->prepare("INSERT INTO $table (title,slug,content,status) VALUES (?,?,?,?)")->execute([$title,$slug,$content,$status]);
        }
        logAdminAction($_SESSION['user_id'], ($id?'Updated':'Created').' '.$contentType.' content');
        jsonResponse(['success'=>true,'message'=>'Content saved']);
    }
    
    if ($action === 'delete') {
        $db->prepare("DELETE FROM $table WHERE id=?")->execute([intval($_POST['id'])]);
        jsonResponse(['success'=>true,'message'=>'Content deleted']);
    }
    
    errorResponse('Unknown action');
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
  <div><h2>Content Management</h2></div>
  <div class="page-actions"><button class="btn btn-primary" onclick="openContentModal()"><i class="fas fa-plus"></i> Add New</button></div>
</div>

<div class="tabs" id="contentTabs">
  <button class="tab active" data-type="faq" onclick="switchContent('faq',this)">FAQ</button>
  <button class="tab" data-type="blog" onclick="switchContent('blog',this)">Blog Posts</button>
  <button class="tab" data-type="pages" onclick="switchContent('pages',this)">Pages</button>
</div>

<div class="card"><div class="card-body">
  <div class="table-wrap"><table><thead id="contentThead"><tr><th>ID</th><th>Question</th><th>Category</th><th>Status</th><th>Actions</th></tr></thead><tbody id="contentBody"></tbody></table></div>
  <div class="pagination" id="contentPagination"></div>
</div></div>

<!-- Content Modal -->
<div class="modal-overlay" id="contentModal">
  <div class="modal modal-lg"><div class="modal-header"><h3 id="contentModalTitle">Add Content</h3><button class="modal-close" onclick="closeModal('contentModal')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body">
      <form id="contentForm">
        <input type="hidden" name="id" id="contentId">
        <input type="hidden" name="content_type" id="contentType">
        <div id="contentFormFields"></div>
      </form>
    </div>
    <div class="modal-footer"><button class="btn btn-ghost" onclick="closeModal('contentModal')">Cancel</button><button class="btn btn-primary" onclick="saveContent()"><i class="fas fa-save"></i> Save</button></div>
  </div>
</div>

<script>
let contentType = 'faq', contentPage = 1;

function switchContent(type, btn) {
  contentType = type;
  document.querySelectorAll('#contentTabs .tab').forEach(function(t) { t.classList.remove('active'); });
  btn.classList.add('active');
  loadContent(1);
}

function loadContent(page) {
  page = page || contentPage; contentPage = page;
  ajaxGet('/admin/content.php?action=list&content_type=' + contentType + '&page=' + page, function(res) {
    if (res.success) {
      document.getElementById('contentBody').innerHTML = res.html;
      var p = document.getElementById('contentPagination'); p.innerHTML = '';
      for (var i = 1; i <= res.pages; i++) {
        var a = document.createElement('a'); a.href = '#'; a.textContent = i;
        a.className = i === page ? 'active' : '';
        a.onclick = function(pi) { return function() { loadContent(pi); return false; }; }(i); p.appendChild(a);
      }
    }
  });
}

function openContentModal(id) {
  document.getElementById('contentForm').reset();
  document.getElementById('contentId').value = '';
  document.getElementById('contentType').value = contentType;
  document.getElementById('contentModalTitle').textContent = 'Add ' + contentType;
  
  var fieldsHtml = '';
  if (contentType === 'faq') {
    fieldsHtml = '<div class="form-group"><label>Question</label><input type="text" class="form-control" name="question" required></div><div class="form-group"><label>Answer</label><textarea class="form-control" name="answer" rows="4" required></textarea></div><div class="form-row"><div class="form-group"><label>Category</label><input type="text" class="form-control" name="category" placeholder="General"></div><div class="form-group"><label>Sort Order</label><input type="number" class="form-control" name="sort_order" value="0"></div></div>';
  } else if (contentType === 'blog') {
    fieldsHtml = '<div class="form-row"><div class="form-group"><label>Title</label><input type="text" class="form-control" name="title" required></div><div class="form-group"><label>Slug</label><input type="text" class="form-control" name="slug" placeholder="my-post-slug"></div></div><div class="form-group"><label>Content</label><textarea class="form-control" name="content" rows="6" required></textarea></div><div class="form-group"><label>Excerpt</label><textarea class="form-control" name="excerpt" rows="2"></textarea></div><div class="form-row"><div class="form-group"><label>Category</label><input type="text" class="form-control" name="category"></div><div class="form-group"><label>Status</label><select class="form-control" name="status"><option value="draft">Draft</option><option value="published">Published</option></select></div></div>';
  } else {
    fieldsHtml = '<div class="form-row"><div class="form-group"><label>Title</label><input type="text" class="form-control" name="title" required></div><div class="form-group"><label>Slug</label><input type="text" class="form-control" name="slug" placeholder="page-slug" required></div></div><div class="form-group"><label>Content</label><textarea class="form-control" name="content" rows="6" required></textarea></div><div class="form-group"><label>Status</label><select class="form-control" name="status"><option value="draft">Draft</option><option value="published">Published</option></select></div>';
  }
  document.getElementById('contentFormFields').innerHTML = fieldsHtml;
  
  if (id) {
    ajaxGet('/admin/content.php?action=get&id=' + id + '&content_type=' + contentType, function(res) {
      if (res.success && res.item) {
        document.getElementById('contentId').value = id;
        document.getElementById('contentModalTitle').textContent = 'Edit ' + contentType;
        var item = res.item;
        Object.keys(item).forEach(function(k) {
          var el = document.querySelector('[name="' + k + '"]');
          if (el) el.value = item[k] || '';
        });
      }
    });
  }
  openModal('contentModal');
}

function editItem(id) { openContentModal(id); }

function saveContent() {
  var data = serializeForm(document.getElementById('contentForm'));
  data.action = 'save';
  ajax('/admin/content.php', data, function(res) {
    if (res.success) { toast(res.message, 'success'); closeModal('contentModal'); loadContent(1); }
    else toast(res.error, 'error');
  });
}

function deleteItem(id) {
  confirmAction('Delete this item?', function() {
    ajax('/admin/content.php', {action:'delete', id:id, content_type:contentType}, function(res) {
      if (res.success) { toast(res.message, 'success'); loadContent(contentPage); }
      else toast(res.error, 'error');
    });
  });
}

loadContent(1);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
