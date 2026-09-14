<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_any_role(['admin']);
$pdo = pdo_connect();
$action = $_GET['action'] ?? 'list';
if (!isset($_GET['id'])) { header('Location: ' . BASE_PATH . '/admin/dashboard.php'); exit; }
$id = (int)$_GET['id'];

if ($action === 'approve') {
    $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute(['approved',$id]);
    header('Location: ' . BASE_PATH . '/admin/dashboard.php'); exit;
}
if ($action === 'reject') {
    $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute(['rejected',$id]);
    header('Location: ' . BASE_PATH . '/admin/dashboard.php'); exit;
}

if ($action === 'view') {
    $u = $pdo->prepare('SELECT * FROM users WHERE id = ?'); $u->execute([$id]); $user = $u->fetch();
    if (!$user) { echo "Not found"; exit; }
    include __DIR__ . '/../includes/header.php';
    ?>
    <div class="mx-auto max-w-2xl py-4">
      <div class="f-card p-6">
        <h2 class="text-2xl font-semibold text-funeral-800">Verification documents: <?= htmlspecialchars($user['full_name']) ?></h2>
        <p class="mt-1 text-sm text-stone-500">Rendered directly below — no need to open a new tab.</p>

        <?php if ($user['valid_id_path']): ?>
          <div class="mt-5">
            <div class="text-sm font-medium text-stone-700">Valid ID</div>
            <?php render_document_viewer(__DIR__ . '/../' . ltrim($user['valid_id_path'], '/'), BASE_PATH . '/' . ltrim($user['valid_id_path'], '/'), 'Valid ID'); ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($user['license_path'])): ?>
          <div class="mt-5">
            <div class="text-sm font-medium text-stone-700">Funeral home license</div>
            <?php render_document_viewer(__DIR__ . '/../' . ltrim($user['license_path'], '/'), BASE_PATH . '/' . ltrim($user['license_path'], '/'), 'Funeral home license'); ?>
          </div>
        <?php endif; ?>

        <div class="mt-6 flex flex-wrap gap-2">
          <a href="<?= BASE_PATH ?>/admin/verify_user.php?action=approve&id=<?= $id ?>" class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300">Approve</a>
          <a href="<?= BASE_PATH ?>/admin/verify_user.php?action=reject&id=<?= $id ?>" class="inline-flex items-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-red-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-300">Decline</a>
          <a href="<?= BASE_PATH ?>/admin/dashboard.php" class="inline-flex items-center rounded-lg border border-stone-300 bg-white px-4 py-2 text-sm font-semibold text-stone-700 transition hover:bg-stone-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-stone-300">Back</a>
        </div>
      </div>
    </div>
    <?php
    include __DIR__ . '/../includes/footer.php';
    exit;
}
