<?php
require_once __DIR__ . '/../includes/header.php';
require_any_role(['admin']);
$pdo = pdo_connect();
$currentUser = current_user();

$roleFilter = $_GET['role'] ?? 'all';
$sql = 'SELECT id, full_name, email, phone, role, status, funeral_name, created_at FROM users';
$params = [];
if (in_array($roleFilter, ['client', 'director', 'admin'], true)) {
    $sql .= ' WHERE role = ?';
    $params[] = $roleFilter;
}
$sql .= ' ORDER BY role ASC, full_name ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();

$tabs = ['all' => 'All', 'client' => 'Clients', 'director' => 'Directors', 'admin' => 'Admins'];
?>
<div class="space-y-6 py-4">
  <div class="flex flex-col gap-3 rounded-[28px] border border-stone-200 bg-white/80 p-5 shadow-soft sm:flex-row sm:items-center sm:justify-between sm:p-6">
    <div>
      <p class="f-eyebrow mb-2">Admin access</p>
      <h2 class="text-3xl font-bold text-funeral-800">Manage Users</h2>
      <p class="mt-1 text-sm text-stone-600">View every account and remove one if it no longer belongs on the platform.</p>
    </div>
    <a href="<?= BASE_PATH ?>/admin/dashboard.php" class="inline-flex shrink-0 items-center gap-1 rounded-full border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-stone-700 transition hover:bg-stone-50">
      <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.5 15L7.5 10L12.5 5" /></svg>
      Back to dashboard
    </a>
  </div>

  <div class="f-card p-5 sm:p-6">
    <div class="mb-4 flex flex-wrap gap-2">
      <?php foreach ($tabs as $value => $label): ?>
        <a href="?role=<?= $value ?>" class="rounded-full px-4 py-2 text-sm font-medium transition <?= $roleFilter === $value ? 'bg-funeral-600 text-white' : 'border border-stone-300 bg-white text-stone-700 hover:bg-stone-50' ?>"><?= $label ?></a>
      <?php endforeach; ?>
    </div>

    <?php if (!$users): ?>
      <div class="rounded-2xl border border-dashed border-stone-300 bg-stone-50/60 p-8 text-center text-sm text-stone-500">No accounts in this category.</div>
    <?php else: ?>
      <div class="space-y-2">
        <?php foreach ($users as $u): ?>
          <?php
            $isSelf = (int)$u['id'] === (int)$currentUser['id'];
            $isLastAdmin = $u['role'] === 'admin' && $adminCount <= 1;
            $pillClass = $u['status'] === 'approved' ? 'f-pill-approved' : ($u['status'] === 'rejected' ? 'f-pill-rejected' : 'f-pill-pending');
          ?>
          <div class="f-card-flat flex flex-col gap-3 p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <div>
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-medium text-stone-800"><?= htmlspecialchars($u['full_name']) ?></span>
                <span class="f-pill bg-funeral-100 text-funeral-800"><?= htmlspecialchars(ucfirst($u['role'])) ?></span>
                <span class="f-pill <?= $pillClass ?>"><?= htmlspecialchars(ucfirst($u['status'] ?? 'pending')) ?></span>
              </div>
              <div class="mt-1 text-sm text-stone-500"><?= htmlspecialchars($u['email']) ?><?= $u['phone'] ? ' · ' . htmlspecialchars($u['phone']) : '' ?></div>
              <?php if ($u['funeral_name']): ?><div class="text-sm text-stone-500">Business: <?= htmlspecialchars($u['funeral_name']) ?></div><?php endif; ?>
            </div>
            <div class="flex shrink-0 items-center gap-2">
              <?php if ($isSelf): ?>
                <span class="text-xs text-stone-400">This is your account</span>
              <?php elseif ($isLastAdmin): ?>
                <span class="text-xs text-stone-400">Last remaining admin</span>
              <?php else: ?>
                <a href="<?= BASE_PATH ?>/admin/user_action.php?action=delete&id=<?= (int)$u['id'] ?>" onclick="return confirm('Delete <?= htmlspecialchars(addslashes($u['full_name'])) ?>? This also removes their parlors, packages, and feedback. This cannot be undone.');" class="rounded-full bg-red-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-red-500">Delete</a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
