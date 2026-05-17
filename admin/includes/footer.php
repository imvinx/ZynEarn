  </main>
</div>
<div class="toast-container" id="toastContainer"></div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
<script>
(function() {
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  const toggle = document.getElementById('sidebarToggle');
  const dtToggle = document.getElementById('adminDropdownToggle');
  const ddm = document.getElementById('adminDropdown');

  if (toggle) {
    toggle.style.display = window.innerWidth <= 1024 ? 'flex' : 'none';
    toggle.addEventListener('click', function(e) {
      e.stopPropagation();
      sidebar.classList.toggle('open');
      overlay.classList.toggle('active');
    });
    overlay.addEventListener('click', function() {
      sidebar.classList.remove('open');
      overlay.classList.remove('active');
    });
    window.addEventListener('resize', function() {
      toggle.style.display = window.innerWidth <= 1024 ? 'flex' : 'none';
      if (window.innerWidth > 1024) {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
      }
    });
  }

  if (dtToggle && ddm) {
    dtToggle.addEventListener('click', function(e) {
      e.stopPropagation();
      ddm.classList.toggle('active');
    });
    document.addEventListener('click', function(e) {
      if (!ddm.contains(e.target) && !dtToggle.contains(e.target)) {
        ddm.classList.remove('active');
      }
    });
  }

  document.querySelectorAll('.dropdown').forEach(function(d) {
    const btn = d.querySelector('.dropdown-toggle');
    const menu = d.querySelector('.dropdown-menu');
    if (btn && menu) {
      btn.addEventListener('click', function(e) {
        e.stopPropagation();
        menu.classList.toggle('active');
      });
    }
  });
  document.addEventListener('click', function() {
    document.querySelectorAll('.dropdown-menu').forEach(function(m) { m.classList.remove('active'); });
  });
})();

function toast(message, type) {
  type = type || 'success';
  var c = document.getElementById('toastContainer');
  if (!c) { c = document.createElement('div'); c.id = 'toastContainer'; c.className = 'toast-container'; document.body.appendChild(c); }
  var icons = {success:'fa-check-circle',error:'fa-exclamation-circle',warning:'fa-exclamation-triangle',info:'fa-info-circle'};
  var t = document.createElement('div');
  t.className = 'toast toast-' + type;
  t.innerHTML = '<i class="fas ' + (icons[type] || icons.info) + '"></i><span class="toast-msg">' + message + '</span><button class="toast-close" onclick="this.parentElement.classList.add(\'toast-out\');setTimeout(function(){this.parentElement.remove()}.bind(this),300)"><i class="fas fa-times"></i></button>';
  c.appendChild(t);
  setTimeout(function() {
    t.classList.add('toast-out');
    setTimeout(function() { if (t.parentElement) t.remove(); }, 300);
  }, 4000);
}

function confirmAction(message, callback) {
  if (confirm(message || 'Are you sure?')) { callback(); }
}

function ajax(url, data, callback, method) {
  method = method || 'POST';
  var xhr = new XMLHttpRequest();
  xhr.open(method, url, true);
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
  xhr.onload = function() {
    var res;
    try { res = JSON.parse(xhr.responseText); } catch(e) { res = {success:false, error:'Invalid response'}; }
    if (callback) callback(res);
  };
  xhr.onerror = function() {
    if (callback) callback({success:false, error:'Network error'});
  };
  var params = '';
  if (data) {
    var parts = [];
    for (var k in data) { if (data.hasOwnProperty(k)) parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(data[k])); }
    params = parts.join('&');
  }
  xhr.send(params);
}

function ajaxGet(url, callback) {
  ajax(url, null, callback, 'GET');
}

function serializeForm(form) {
  var data = {};
  var els = form.querySelectorAll('input, select, textarea');
  for (var i = 0; i < els.length; i++) {
    var el = els[i];
    if (el.type === 'checkbox') data[el.name] = el.checked ? 1 : 0;
    else if (el.type === 'radio') { if (el.checked) data[el.name] = el.value; }
    else if (el.name) data[el.name] = el.value;
  }
  return data;
}

function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
document.addEventListener('click', function(e) {
  if (e.target.classList.contains('modal-overlay')) { e.target.classList.remove('active'); }
});
</script>
</body>
</html>
