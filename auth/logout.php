<?php
require_once __DIR__ . '/../includes/auth_middleware.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAjaxRequest()) {
    destroySession();
    echo json_encode(['success' => true, 'redirect' => APP_URL . '/auth/login.php', 'message' => 'You have been logged out.']);
    exit;
}

destroySession();

$redirect = $_GET['redirect'] ?? APP_URL . '/auth/login.php';
header('Location: ' . $redirect);
exit;
