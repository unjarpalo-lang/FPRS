<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_any_role(['admin']);

$pdo = pdo_connect();
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header('Location: ' . BASE_PATH . '/admin/dashboard.php');
    exit;
}

if ($action === 'approve') {
    $pdo->prepare('UPDATE funerals SET status = ? WHERE id = ?')->execute(['scheduled', $id]);
    header('Location: ' . BASE_PATH . '/admin/dashboard.php');
    exit;
}

if ($action === 'reject') {
    $pdo->prepare('UPDATE funerals SET status = ? WHERE id = ?')->execute(['cancelled', $id]);
    header('Location: ' . BASE_PATH . '/admin/dashboard.php');
    exit;
}

header('Location: ' . BASE_PATH . '/admin/dashboard.php');
exit;
