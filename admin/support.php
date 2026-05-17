<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAdmin();
$pageTitle = 'Support';
$db = getDB();

if (isAjaxRequest()) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    if ($action === 'list') {
        $status = $_GET['status'] ?? 'open';
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = 20; $offset = ($page-1)*$limit;
        $where = $status !== 'all' ? "WHERE t.status='$status'" : '';
        $total = $db->query("SELECT COUNT(*) FROM support_tickets t $where")->fetchColumn();
        $rows = $db->query("SELECT t.*, u.username FROM support_tickets t JOIN users u ON t.user_id=u.id $where ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset")->fetchAll();
        $html = '';
        foreach ($rows as $r) {
            $replyCount = $db->prepare("SELECT COUNT(*) FROM ticket_replies WHERE ticket_id=?")->execute([$r['id']]) ? $db->query("SELECT COUNT(*) FROM ticket_replies WHERE ticket_id={$r['id']}")->fetchColumn() : 0;
            $html .= '<tr>
                <td>'.$r['id'].'</td>
                <td><strong>'.sanitize($r['username']).'</strong></td>
                <td>'.sanitize($r['subject']).'</td>
                <td><span class="badge badge-'.($r['priority']==='urgent'?'danger':($r['priority']==='high'?'warning':($r['priority']==='medium'?'info':'neutral'))).'">'.$r['priority'].'</span></td>
                <td><span class="badge badge-'.($r['status']==='open'?'success':($r['status']==='closed'?'neutral':'info')).'">'.$r['status'].'</span></td>
                <td>'.$replyCount.'</td>
                <td style="color:var(--text-muted);font-size:.78rem">'.timeAgo($r['created_at']).'</td>
                <td><button class="btn btn-sm btn-primary" onclick="viewTicket('.$r['id'].')"><i class="fas fa-eye"></i> View</button></td>
            </tr>';
        }
        jsonResponse(['success'=>true,'html'=>$html,'total'=>$total,'pages'=>ceil($total/$limit)]);
    }
    
    if ($action === 'view') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT t.*, u.username FROM support_tickets t JOIN users u ON t.user_id=u.id WHERE t.id=?");
        $stmt->execute([$id]);
        $ticket = $stmt->fetch();
        if (!$ticket) errorResponse('Ticket not found');
        $replies = $db->prepare("SELECT r.*, u.username, u.role FROM ticket_replies r JOIN users u ON r.user_id=u.id WHERE r.ticket_id=? ORDER BY r.created_at ASC");
        $replies->execute([$id]);
        $replies = $replies->fetchAll();
        $html = '<input type="hidden" id="ticketId" value="'.$id.'">
            <div style="margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--dark-border)">
                <h4 style="margin-bottom:6px">'.sanitize($ticket['subject']).'</h4>
                <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:10px">
                    <span><strong>By:</strong> '.sanitize($ticket['username']).'</span>
                    <span class="badge badge-'.($ticket['priority']==='urgent'?'danger':($ticket['priority']==='high'?'warning':($ticket['priority']==='medium'?'info':'neutral'))).'">'.$ticket['priority'].'</span>
                    <span class="badge badge-'.($ticket['status']==='open'?'success':($ticket['status']==='closed'?'neutral':'info')).'">'.$ticket['status'].'</span>
                    <span style="color:var(--text-muted);font-size:.78rem">'.date('M d, Y H:i', strtotime($ticket['created_at'])).'</span>
                </div>
                <div style="background:var(--dark-bg);padding:16px;border-radius:var(--radius-sm);font-size:.85rem;line-height:1.6">'.nl2br(sanitize($ticket['message'])).'</div>
            </div>
            <h5 style="margin-bottom:12px;font-size:.85rem;font-weight:600">Replies ('.count($replies).')</h5>';
        foreach ($replies as $rep) {
            $isAdmin = $rep['role'] === 'admin';
            $html .= '<div style="display:flex;gap:12px;margin-bottom:12px;'.($isAdmin?'flex-direction:row-reverse':'').'">
                <div class="avatar avatar-sm" style="background:'.($isAdmin?'var(--gradient-main)':'rgba(255,255,255,.1)').'">'.strtoupper(substr($rep['username'],0,1)).'</div>
                <div style="flex:1;background:'.($isAdmin?'rgba(108,92,231,.08)':'var(--dark-bg)').';padding:12px 16px;border-radius:var(--radius-sm);'.($isAdmin?'margin-left:40px':'margin-right:40px').'">
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px">
                        <strong style="font-size:.8rem">'.sanitize($rep['username']).'</strong>
                        <span style="color:var(--text-muted);font-size:.7rem">'.timeAgo($rep['created_at']).'</span>
                    </div>
                    <div style="font-size:.84rem;line-height:1.5">'.nl2br(sanitize($rep['message'])).'</div>
                </div>
            </div>';
        }
        $html .= '<div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--dark-border)">
            <div class="form-group"><label>Reply</label><textarea class="form-control" id="replyMessage" rows="3" placeholder="Type your reply..."></textarea></div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <button class="btn btn-primary" onclick="sendReply()"><i class="fas fa-reply"></i> Send Reply</button>
                <button class="btn btn-success" onclick="changeTicketStatus(\'resolved\')"><i class="fas fa-check"></i> Mark Resolved</button>
                <button class="btn btn-ghost" onclick="changeTicketStatus(\'closed\')"><i class="fas fa-times"></i> Close</button>
            </div>
        </div>';
        jsonResponse(['success'=>true,'html'=>$html,'ticket'=>$ticket]);
    }
    
    if ($action === 'reply') {
        $ticketId = intval($_POST['ticket_id'] ?? 0);
        $message = $_POST['message'] ?? '';
        if (!$message) errorResponse('Message is required');
        $stmt = $db->prepare("INSERT INTO ticket_replies (ticket_id, user_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$ticketId, $_SESSION['user_id'], $message]);
        $db->prepare("UPDATE support_tickets SET status='open' WHERE id=?")->execute([$ticketId]);
        logAdminAction($_SESSION['user_id'], 'Replied to ticket #'.$ticketId);
        jsonResponse(['success'=>true,'message'=>'Reply sent']);
    }
    
    if ($action === 'status') {
        $ticketId = intval($_POST['ticket_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $db->prepare("UPDATE support_tickets SET status=? WHERE id=?")->execute([$status, $ticketId]);
        logAdminAction($_SESSION['user_id'], 'Changed ticket #'.$ticketId.' status to '.$status);
        jsonResponse(['success'=>true,'message'=>'Status updated']);
    }
    
    errorResponse('Unknown action');
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header"><div><h2>Support Tickets</h2></div></div>

<div class="card">
  <div class="card-body">
    <div class="filter-bar">
      <select class="form-control" id="ticketStatus" onchange="loadTickets()">
        <option value="open">Open</option><option value="resolved">Resolved</option><option value="closed">Closed</option><option value="all">All</option>
      </select>
    </div>
    <div class="table-wrap"><table><thead><tr><th>ID</th><th>User</th><th>Subject</th><th>Priority</th><th>Status</th><th>Replies</th><th>Date</th><th>Actions</th></tr></thead><tbody id="ticketsBody"></tbody></table></div>
    <div class="pagination" id="ticketsPagination"></div>
  </div>
</div>

<!-- Ticket View Modal -->
<div class="modal-overlay" id="ticketModal">
  <div class="modal modal-lg" style="max-width:800px">
    <div class="modal-header"><h3>Ticket Details</h3><button class="modal-close" onclick="closeModal('ticketModal')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body" id="ticketContent" style="max-height:70vh;overflow-y:auto"></div>
  </div>
</div>

<script>
let ticketPage = 1;
function loadTickets(page) {
  page = page || ticketPage; ticketPage = page;
  var status = document.getElementById('ticketStatus').value;
  ajaxGet('/admin/support.php?action=list&page=' + page + '&status=' + status, function(res) {
    if (res.success) {
      document.getElementById('ticketsBody').innerHTML = res.html;
      var p = document.getElementById('ticketsPagination'); p.innerHTML = '';
      for (var i = 1; i <= res.pages; i++) {
        var a = document.createElement('a'); a.href = '#'; a.textContent = i;
        a.className = i === page ? 'active' : '';
        a.onclick = function(pi) { return function() { loadTickets(pi); return false; }; }(i); p.appendChild(a);
      }
    }
  });
}

function viewTicket(id) {
  ajax('/admin/support.php', {action:'view', id:id}, function(res) {
    if (res.success) { document.getElementById('ticketContent').innerHTML = res.html; openModal('ticketModal'); }
    else toast(res.error, 'error');
  });
}

function sendReply() {
  var ticketId = document.getElementById('ticketId').value;
  var message = document.getElementById('replyMessage').value;
  if (!message) { toast('Please enter a reply', 'error'); return; }
  ajax('/admin/support.php', {action:'reply', ticket_id:ticketId, message:message}, function(res) {
    if (res.success) { toast(res.message, 'success'); viewTicket(ticketId); }
    else toast(res.error, 'error');
  });
}

function changeTicketStatus(status) {
  var ticketId = document.getElementById('ticketId').value;
  ajax('/admin/support.php', {action:'status', ticket_id:ticketId, status:status}, function(res) {
    if (res.success) { toast(res.message, 'success'); closeModal('ticketModal'); loadTickets(ticketPage); }
    else toast(res.error, 'error');
  });
}

loadTickets(1);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
