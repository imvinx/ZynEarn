<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
requireAdmin();
$pageTitle = 'Settings';
$db = getDB();

if (isAjaxRequest()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $group = $_POST['group'] ?? 'general';
        $settings = $_POST['settings'] ?? [];
        foreach ($settings as $key => $value) {
            updateSetting($key, $value);
        }
        logAdminAction($_SESSION['user_id'], 'Updated settings group: ' . $group);
        jsonResponse(['success' => true, 'message' => 'Settings saved']);
    }
    if ($action === 'get') {
        $group = $_GET['group'] ?? 'general';
        $stmt = $db->prepare("SELECT `key`, `value` FROM site_settings WHERE group_name = ?");
        $stmt->execute([$group]);
        $settings = [];
        foreach ($stmt->fetchAll() as $row) { $settings[$row['key']] = $row['value']; }
        jsonResponse(['success' => true, 'settings' => $settings]);
    }
    errorResponse('Unknown action');
}

$groups = ['general' => 'General', 'withdrawal' => 'Withdrawal', 'deposit' => 'Deposit', 'referral' => 'Referral', 'rewards' => 'Rewards', 'faucet' => 'Faucet', 'security' => 'Security', 'email' => 'Email', 'theme' => 'Theme'];
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header"><div><h2>Settings</h2><p>Configure your platform</p></div></div>

<div class="card">
  <div class="card-body">
    <div class="tabs" id="settingsTabs">
      <?php foreach ($groups as $key => $label): ?>
      <button class="tab <?= $key === 'general' ? 'active' : '' ?>" data-group="<?= $key ?>" onclick="loadSettings('<?= $key ?>', this)"><?= $label ?></button>
      <?php endforeach; ?>
    </div>
    <div id="settingsContent">
      <div style="text-align:center;padding:40px"><div class="loading-spinner"></div></div>
    </div>
    <div class="card-footer" style="margin-top:20px">
      <button class="btn btn-primary" onclick="saveSettings()"><i class="fas fa-save"></i> Save Settings</button>
    </div>
  </div>
</div>

<script>
let currentGroup = 'general';

function loadSettings(group, btn) {
  currentGroup = group;
  if (btn) {
    document.querySelectorAll('#settingsTabs .tab').forEach(function(t) { t.classList.remove('active'); });
    btn.classList.add('active');
  }
  document.getElementById('settingsContent').innerHTML = '<div style="text-align:center;padding:40px"><div class="loading-spinner"></div></div>';
  
  ajaxGet('/admin/settings.php?action=get&group=' + group, function(res) {
    if (!res.success) return;
    var s = res.settings;
    var html = '<div style="padding:20px 0">';
    
    var fields = {
      general: [
        {key:'site_name', label:'Site Name', type:'text'},
        {key:'site_description', label:'Site Description', type:'textarea'},
        {key:'site_url', label:'Site URL', type:'text'},
        {key:'site_email', label:'Site Email', type:'email'},
        {key:'currency', label:'Currency', type:'text'},
        {key:'currency_symbol', label:'Currency Symbol', type:'text'},
        {key:'registration_enabled', label:'Registration Enabled', type:'checkbox'},
        {key:'email_verification_required', label:'Email Verification Required', type:'checkbox'},
        {key:'maintenance_mode', label:'Maintenance Mode', type:'checkbox'},
      ],
      withdrawal: [
        {key:'min_withdrawal', label:'Minimum Withdrawal Amount', type:'number'},
        {key:'max_withdrawal', label:'Maximum Withdrawal Amount', type:'number'},
        {key:'withdrawal_fee_percentage', label:'Withdrawal Fee (%)', type:'number'},
      ],
      deposit: [
        {key:'min_deposit', label:'Minimum Deposit', type:'number'},
      ],
      referral: [
        {key:'referral_bonus_percentage', label:'Referral Bonus (%)', type:'number'},
        {key:'referral_levels', label:'Referral Levels', type:'number'},
      ],
      rewards: [
        {key:'daily_reward_enabled', label:'Daily Reward Enabled', type:'checkbox'},
      ],
      faucet: [
        {key:'faucet_interval_minutes', label:'Faucet Interval (minutes)', type:'number'},
        {key:'faucet_base_reward', label:'Faucet Base Reward', type:'number'},
      ],
      security: [
        {key:'max_login_attempts', label:'Max Login Attempts', type:'number'},
        {key:'lockout_duration_minutes', label:'Lockout Duration (minutes)', type:'number'},
      ],
      email: [
        {key:'mail_host', label:'SMTP Host', type:'text'},
        {key:'mail_port', label:'SMTP Port', type:'number'},
        {key:'mail_username', label:'SMTP Username', type:'text'},
        {key:'mail_password', label:'SMTP Password', type:'password'},
        {key:'mail_encryption', label:'SMTP Encryption', type:'text', placeholder:'tls or ssl'},
        {key:'mail_from_address', label:'From Address', type:'email'},
        {key:'mail_from_name', label:'From Name', type:'text'},
      ],
      theme: [
        {key:'primary_color', label:'Primary Color', type:'text'},
        {key:'secondary_color', label:'Secondary Color', type:'text'},
        {key:'accent_color', label:'Accent Color', type:'text'},
        {key:'site_logo', label:'Logo URL', type:'text'},
        {key:'site_favicon', label:'Favicon URL', type:'text'},
      ]
    };
    
    var groupFields = fields[group] || [];
    groupFields.forEach(function(f) {
      var val = s[f.key] !== undefined ? s[f.key] : '';
      if (f.type === 'checkbox') {
        html += '<div class="form-check" style="margin-bottom:14px">';
        html += '<input type="checkbox" id="set_' + f.key + '" data-key="' + f.key + '" ' + (val == '1' ? 'checked' : '') + '>';
        html += '<label for="set_' + f.key + '">' + f.label + '</label></div>';
      } else if (f.type === 'textarea') {
        html += '<div class="form-group"><label>' + f.label + '</label><textarea class="form-control" id="set_' + f.key + '" data-key="' + f.key + '" rows="3">' + val.replace(/</g, '&lt;') + '</textarea></div>';
      } else {
        html += '<div class="form-group"><label>' + f.label + '</label><input type="' + f.type + '" class="form-control" id="set_' + f.key + '" data-key="' + f.key + '" value="' + val.replace(/</g, '&lt;') + '"' + (f.placeholder ? ' placeholder="' + f.placeholder + '"' : '') + '></div>';
      }
    });
    
    html += '</div>';
    document.getElementById('settingsContent').innerHTML = html;
  });
}

function saveSettings() {
  var settings = {};
  document.querySelectorAll('#settingsContent [data-key]').forEach(function(el) {
    var key = el.dataset.key;
    if (el.type === 'checkbox') settings[key] = el.checked ? '1' : '0';
    else settings[key] = el.value;
  });
  
  ajax('/admin/settings.php', {action:'save', group:currentGroup, settings:settings}, function(res) {
    if (res.success) toast(res.message, 'success');
    else toast(res.error, 'error');
  });
}

loadSettings('general', document.querySelector('#settingsTabs .tab'));
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
