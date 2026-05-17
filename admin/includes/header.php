<?php
require_once __DIR__ . '/../../includes/functions.php';
$currentUser = getCurrentUser();
$unreadTickets = 0;
$pendingWithdrawals = 0;
try {
    $db = getDB();
    $unreadTickets = $db->query("SELECT COUNT(*) FROM support_tickets WHERE status='open'")->fetchColumn();
    $pendingWithdrawals = $db->query("SELECT COUNT(*) FROM withdrawals WHERE status='pending'")->fetchColumn();
} catch(Exception $e) {}
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$sidebarLinks = [
    'dashboard' => ['label' => 'Dashboard', 'icon' => 'fa-chart-pie', 'pages' => ['dashboard.php']],
    'users' => ['label' => 'Users', 'icon' => 'fa-users', 'pages' => ['users.php']],
    'earnings' => ['label' => 'Earnings', 'icon' => 'fa-coins', 'pages' => ['earnings.php']],
    'withdrawals' => ['label' => 'Withdrawals', 'icon' => 'fa-hand-holding-usd', 'pages' => ['withdrawals.php'], 'badge' => $pendingWithdrawals],
    'offers' => ['label' => 'Offers', 'icon' => 'fa-table-cells-large', 'pages' => ['offers.php']],
    'tasks' => ['label' => 'Tasks', 'icon' => 'fa-check-double', 'pages' => ['tasks.php']],
    'missions' => ['label' => 'Missions', 'icon' => 'fa-bullseye', 'pages' => ['missions.php']],
    'achievements' => ['label' => 'Achievements', 'icon' => 'fa-trophy', 'pages' => ['achievements.php']],
    'announcements' => ['label' => 'Announcements', 'icon' => 'fa-bullhorn', 'pages' => ['announcements.php']],
    'coupons' => ['label' => 'Coupons', 'icon' => 'fa-ticket', 'pages' => ['coupons.php']],
    'content' => ['label' => 'Content', 'icon' => 'fa-file-alt', 'pages' => ['content.php']],
    'support' => ['label' => 'Support', 'icon' => 'fa-headset', 'pages' => ['support.php'], 'badge' => $unreadTickets],
    'reports' => ['label' => 'Reports', 'icon' => 'fa-chart-bar', 'pages' => ['reports.php']],
    'settings' => ['label' => 'Settings', 'icon' => 'fa-cog', 'pages' => ['settings.php']],
    'security' => ['label' => 'Security', 'icon' => 'fa-shield-alt', 'pages' => ['security.php']],
    'backup' => ['label' => 'Backup', 'icon' => 'fa-database', 'pages' => ['backup.php']],
    'api' => ['label' => 'API', 'icon' => 'fa-key', 'pages' => ['api.php']],
    'maintenance' => ['label' => 'Maintenance', 'icon' => 'fa-wrench', 'pages' => ['maintenance.php']],
];
function isSidebarActive($linkPages) {
    return in_array(basename($_SERVER['SCRIPT_NAME']), $linkPages) ? 'active' : '';
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title><?= $pageTitle ?? 'Admin' ?> - <?= APP_NAME ?> Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.min.css" crossorigin="anonymous">
<link rel="stylesheet" href="/admin/assets/admin.css">
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <i class="fas fa-rocket"></i>
    <span>ZynEarn</span>
    <span style="font-size:.6rem;background:var(--primary);padding:2px 8px;border-radius:50px;font-family:var(--font-family);-webkit-text-fill-color:#fff;margin-left:4px">Admin</span>
  </div>
  <nav class="sidebar-nav">
    <?php foreach(['Management' => ['dashboard','users','earnings','withdrawals'], 'Content' => ['offers','tasks','missions','achievements','announcements','coupons','content'], 'Support' => ['support'], 'Analytics' => ['reports'], 'System' => ['settings','security','backup','api','maintenance']] as $sectionName => $sectionLinks): ?>
    <div class="sidebar-section">
      <div class="sidebar-label"><?= $sectionName ?></div>
      <?php foreach($sectionLinks as $key): $link = $sidebarLinks[$key]; ?>
      <a href="/admin/<?= $link['pages'][0] ?>" class="sidebar-link <?= isSidebarActive($link['pages']) ?>">
        <i class="fas <?= $link['icon'] ?>"></i>
        <span><?= $link['label'] ?></span>
        <?php if(!empty($link['badge']) && $link['badge'] > 0): ?>
        <span class="badge"><?= $link['badge'] > 99 ? '99+' : $link['badge'] ?></span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-footer">
    <div class="admin-info">
      <div class="admin-avatar"><?= strtoupper(substr($currentUser['username'] ?? 'A', 0, 1)) ?></div>
      <div>
        <div class="admin-name"><?= sanitize($currentUser['username'] ?? 'Admin') ?></div>
        <div class="admin-role"><?= sanitize($currentUser['role'] ?? 'admin') ?></div>
      </div>
    </div>
  </div>
</aside>
<div class="main-wrapper">
  <header class="main-header">
    <div class="header-left">
      <button class="header-btn" id="sidebarToggle" style="border-radius:var(--radius-sm);width:auto;padding:0 12px;gap:8px;font-size:.82rem;display:none">
        <i class="fas fa-bars"></i>
      </button>
      <div>
        <h1><?= $pageTitle ?? 'Dashboard' ?></h1>
        <div class="breadcrumb"><?= APP_NAME ?> <span>/</span> <?= $pageTitle ?? 'Dashboard' ?></div>
      </div>
    </div>
    <div class="header-actions">
      <div class="header-search">
        <i class="fas fa-search"></i>
        <input type="text" placeholder="Search..." id="globalSearch">
      </div>
      <button class="header-btn" onclick="window.open('/admin/support.php','_self')" data-tooltip="Support Tickets">
        <i class="fas fa-headset"></i>
        <?php if($unreadTickets > 0): ?><span class="dot"></span><?php endif; ?>
      </button>
      <button class="header-btn" onclick="window.open('/','_blank')" data-tooltip="View Site">
        <i class="fas fa-external-link-alt"></i>
      </button>
      <div class="dropdown">
        <button class="header-btn" id="adminDropdownToggle">
          <i class="fas fa-user-circle"></i>
        </button>
        <div class="dropdown-menu" id="adminDropdown">
          <a href="/admin/settings.php"><i class="fas fa-cog"></i> Settings</a>
          <a href="/admin/security.php"><i class="fas fa-shield-alt"></i> Security</a>
          <div class="dropdown-divider"></div>
          <a href="/auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
      </div>
    </div>
  </header>
  <main class="main-content">
