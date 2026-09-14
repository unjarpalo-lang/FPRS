<?php
require_once __DIR__ . '/../includes/header.php';
require_any_role(['admin']);
$pdo = pdo_connect();
$pdo->exec("CREATE TABLE IF NOT EXISTS funeral_parlors (id INT AUTO_INCREMENT PRIMARY KEY, director_id INT NOT NULL, name VARCHAR(200) NOT NULL, location VARCHAR(255) NOT NULL, contact_number VARCHAR(50) NOT NULL, license_path VARCHAR(255) NOT NULL, status ENUM('pending','approved','rejected') DEFAULT 'pending', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (director_id) REFERENCES users(id) ON DELETE CASCADE)");

// remember this as the last visited dashboard for Back buttons
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['last_dashboard'] = BASE_PATH . '/admin/dashboard.php';

$col = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'funeral_name'");
$col->execute();
$hasFuneralName = (bool) $col->fetchColumn();
if ($hasFuneralName) {
  $pending = $pdo->query("SELECT id,full_name,email,valid_id_path,role,funeral_name,phone,created_at FROM users WHERE status='pending' ORDER BY created_at DESC")->fetchAll();
} else {
  $pending = $pdo->query("SELECT id,full_name,email,valid_id_path,role,phone,created_at FROM users WHERE status='pending' ORDER BY created_at DESC")->fetchAll();
}
$pendingFunerals = $pdo->query("SELECT f.id, f.client_id, f.package_id, f.funeral_date, f.status, u.full_name AS client_name, p.title AS package_title FROM funerals f JOIN users u ON u.id = f.client_id LEFT JOIN packages p ON p.id = f.package_id WHERE f.status='pending' ORDER BY f.created_at DESC")->fetchAll();
$activeFunerals = $pdo->query("SELECT COUNT(*) as cnt FROM funerals WHERE status IN ('pending','scheduled')")->fetchColumn();
$activeDirectors = $pdo->query("SELECT COUNT(*) FROM users WHERE role='director' AND status='approved'")->fetchColumn();
$pendingParlors = $pdo->query("SELECT fp.*, u.full_name AS director_name FROM funeral_parlors fp JOIN users u ON u.id = fp.director_id WHERE fp.status='pending' ORDER BY fp.created_at DESC")->fetchAll();
?>
<div class="space-y-6 py-4">
  <div class="flex flex-col gap-3 rounded-[28px] border border-stone-200 bg-white/80 p-5 shadow-soft sm:flex-row sm:items-center sm:justify-between sm:p-6">
    <div>
      <p class="f-eyebrow mb-2">Admin access</p>
      <h2 class="text-3xl font-bold text-funeral-800">Admin Dashboard</h2>
      <p class="mt-1 text-sm text-stone-600">A calm overview of what needs your attention today.</p>
    </div>
    <div class="flex shrink-0 items-center gap-2">
      <button onclick="history.back()" class="inline-flex items-center gap-1 rounded-full border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-stone-700 transition hover:bg-stone-50">
        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.5 15L7.5 10L12.5 5" /></svg>
        Back
      </button>
      <a href="<?= BASE_PATH ?>/admin/manage_accounts.php" class="inline-flex items-center gap-2 rounded-full border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-stone-700 transition hover:bg-stone-50">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V9l7-5 7 5v12"/><path d="M9 21v-6h6v6"/></svg>
        Manage Accounts
      </a>
      <a href="<?= BASE_PATH ?>/admin/users.php" class="inline-flex items-center gap-2 rounded-full border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-stone-700 transition hover:bg-stone-50">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6.5 6.5-6.5S15.5 16.4 15.5 20"/><circle cx="17" cy="9" r="2.5"/><path d="M21.5 20c0-2.8-1.9-5.1-4.5-5.8"/></svg>
        Manage Users
      </a>
      <span class="inline-flex items-center rounded-full bg-funeral-100 px-4 py-2 text-sm font-semibold text-funeral-800">Approval center</span>
    </div>
  </div>

  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <div class="f-card-flat flex items-center gap-3 p-4 shadow-sm">
      <span class="f-icon-tile"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V9l7-5 7 5v12"/><path d="M9 21v-6h6v6"/></svg></span>
      <div><div class="text-2xl font-bold text-funeral-800"><?= htmlspecialchars($activeDirectors) ?></div><div class="text-xs text-stone-500">Active directors</div></div>
    </div>
    <div class="f-card-flat flex items-center gap-3 p-4 shadow-sm">
      <span class="f-icon-tile"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2c1.2 2.1 2 3.6 2 5a2 2 0 1 1-4 0c0-1.4.8-2.9 2-5Z" /><path d="M12 9v13" /><path d="M6 22c0-3.3 2.7-6 6-6s6 2.7 6 6" /></svg></span>
      <div><div class="text-2xl font-bold text-funeral-800"><?= htmlspecialchars($activeFunerals) ?></div><div class="text-xs text-stone-500">Active funerals</div></div>
    </div>
    <div class="f-card-flat flex items-center gap-3 p-4 shadow-sm">
      <span class="f-icon-tile"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg></span>
      <div><div class="text-2xl font-bold text-funeral-800"><?= count($pending) ?></div><div class="text-xs text-stone-500">Pending registrations</div></div>
    </div>
    <div class="f-card-flat flex items-center gap-3 p-4 shadow-sm">
      <span class="f-icon-tile"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a4 4 0 0 1 8 0v2"/></svg></span>
      <div><div class="text-2xl font-bold text-funeral-800"><?= count($pendingParlors) ?></div><div class="text-xs text-stone-500">Pending parlors</div></div>
    </div>
  </div>

  <div class="grid gap-5 lg:grid-cols-2">
    <div class="f-card p-5 sm:p-6">
      <h3 class="mb-4 flex items-center gap-2 text-xl font-semibold text-funeral-800">
        <svg class="h-5 w-5 text-funeral-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg>
        Pending Registrations
      </h3>
      <?php if (!$pending): ?>
        <div class="rounded-2xl border border-dashed border-stone-300 bg-stone-50/60 p-6 text-center text-sm text-stone-500">Nothing waiting for review.</div>
      <?php endif; ?>
      <div class="space-y-3">
        <?php foreach($pending as $p): ?>
          <div class="f-card-flat p-3.5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <div class="font-medium text-stone-800"><?= htmlspecialchars($p['full_name']) ?> <span class="f-pill f-pill-pending ml-1"><?= htmlspecialchars($p['role']) ?></span></div>
                <div class="text-sm text-stone-500"><?= htmlspecialchars($p['email']) ?></div>
                <?php if (!empty($p['role']) && $p['role'] === 'director'): ?>
                  <div class="text-sm text-stone-600">Funeral name: <?= htmlspecialchars($p['funeral_name'] ?? '') ?></div>
                  <div class="text-sm text-stone-600">Contact: <?= htmlspecialchars($p['phone'] ?? '') ?></div>
                <?php endif; ?>
              </div>
              <div class="flex flex-wrap gap-2">
                <a href="<?php echo BASE_PATH; ?>/admin/verify_user.php?action=view&id=<?= (int)$p['id'] ?>" class="rounded-full border border-stone-300 px-3 py-1.5 text-xs font-medium text-stone-700 transition hover:bg-stone-50">View ID Photo</a>
                <a href="<?php echo BASE_PATH; ?>/admin/verify_user.php?action=approve&id=<?= (int)$p['id'] ?>" class="rounded-full bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-emerald-500">Approve</a>
                <a href="<?php echo BASE_PATH; ?>/admin/verify_user.php?action=reject&id=<?= (int)$p['id'] ?>" class="rounded-full bg-red-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-red-500">Reject</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="space-y-5">
      <div class="f-card p-5 sm:p-6">
        <h4 class="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-[0.18em] text-funeral-700">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a4 4 0 0 1 8 0v2"/></svg>
          Pending Funeral Parlors
        </h4>
        <?php if (!$pendingParlors): ?><div class="rounded-xl border border-dashed border-stone-300 bg-stone-50/60 p-4 text-center text-sm text-stone-500">No pending parlor registrations.</div><?php endif; ?>
        <div class="space-y-2">
          <?php foreach ($pendingParlors as $parlor): ?>
            <div class="f-card-flat p-3">
              <div class="font-medium text-stone-800"><?= htmlspecialchars($parlor['name']) ?></div>
              <div class="text-sm text-stone-500"><?= htmlspecialchars($parlor['location']) ?> · <?= htmlspecialchars($parlor['contact_number']) ?> · Director: <?= htmlspecialchars($parlor['director_name']) ?></div>
              <div class="mt-2 flex gap-2"><a href="<?= BASE_PATH ?>/admin/parlor_action.php?action=view&id=<?= (int)$parlor['id'] ?>" class="rounded-full border border-stone-300 px-3 py-1.5 text-xs font-medium text-stone-700 transition hover:bg-stone-50">View license</a><a href="<?= BASE_PATH ?>/admin/parlor_action.php?action=approve&id=<?= (int)$parlor['id'] ?>" class="rounded-full bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-emerald-500">Approve</a><a href="<?= BASE_PATH ?>/admin/parlor_action.php?action=reject&id=<?= (int)$parlor['id'] ?>" class="rounded-full bg-red-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-red-500">Decline</a></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="f-card p-5 sm:p-6">
        <h4 class="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-[0.18em] text-funeral-700">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2c1.2 2.1 2 3.6 2 5a2 2 0 1 1-4 0c0-1.4.8-2.9 2-5Z" /><path d="M12 9v13" /><path d="M6 22c0-3.3 2.7-6 6-6s6 2.7 6 6" /></svg>
          Pending Funeral Requests
        </h4>
        <?php if (empty($pendingFunerals)): ?>
          <div class="rounded-xl border border-dashed border-stone-300 bg-stone-50/60 p-4 text-center text-sm text-stone-500">No pending funeral requests.</div>
        <?php else: ?>
          <div class="space-y-2">
            <?php foreach ($pendingFunerals as $f): ?>
              <div class="f-card-flat p-3">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                  <div>
                    <div class="font-medium text-stone-800"><?= htmlspecialchars($f['client_name']) ?></div>
                    <div class="text-xs text-stone-500"><?= htmlspecialchars($f['package_title'] ?: 'No package') ?> • <?= htmlspecialchars($f['funeral_date']) ?></div>
                  </div>
                  <div class="flex flex-wrap gap-2">
                    <a href="<?php echo BASE_PATH; ?>/admin/funeral_action.php?action=approve&id=<?= (int)$f['id'] ?>" class="rounded-full bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-emerald-500">Approve</a>
                    <a href="<?php echo BASE_PATH; ?>/admin/funeral_action.php?action=reject&id=<?= (int)$f['id'] ?>" class="rounded-full bg-red-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-red-500">Reject</a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
