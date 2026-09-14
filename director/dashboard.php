<?php
require_once __DIR__ . '/../includes/header.php';
require_any_role(['director']);
$user = current_user();
$pdo = pdo_connect();
ensure_director_scope_columns($pdo);

// remember this as the last visited dashboard for Back buttons
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['last_dashboard'] = BASE_PATH . '/director/dashboard.php';

$parlorStmt = $pdo->prepare("SELECT * FROM funeral_parlors WHERE director_id = ? ORDER BY name ASC");
$parlorStmt->execute([$user['id']]);
$parlors = $parlorStmt->fetchAll();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_parlor'])) {
  $selectedId = (int)($_POST['parlor_id'] ?? 0);
  foreach ($parlors as $parlor) {
    if ((int)$parlor['id'] === $selectedId) {
      $_SESSION['selected_parlor_id'] = $selectedId;
      header('Location: ' . BASE_PATH . '/director/dashboard.php');
      exit;
    }
  }
}
$selectedParlor = selected_director_parlor($pdo, (int)$user['id']);
?>
<div class="mx-auto max-w-3xl space-y-6 py-6 sm:py-8">
  <?php if (!$selectedParlor): ?>
    <section class="f-card p-6 text-center sm:p-10">
      <span class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-funeral-100 text-funeral-700">
        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V9l7-5 7 5v12"/><path d="M9 21v-6h6v6"/></svg>
      </span>
      <p class="f-eyebrow mb-2">Director portal</p>
      <h2 class="text-3xl font-bold text-funeral-800">Welcome, <?= htmlspecialchars($user['full_name']) ?></h2>
      <p class="mx-auto mt-3 max-w-xl text-stone-600">Start by managing your registered funeral parlors. Select a parlor before managing its packages or inventory.</p>
      <a href="<?= BASE_PATH ?>/director/funeral_parlors.php" class="mt-6 inline-flex items-center gap-2 rounded-full bg-funeral-600 px-6 py-3 font-semibold text-white shadow-lift transition hover:bg-funeral-500">Manage Parlors <span aria-hidden="true">→</span></a>
    </section>
  <?php else: ?>
    <section class="f-card p-6">
      <div class="flex items-center justify-between gap-3">
        <div>
          <p class="f-eyebrow">Selected parlor</p>
          <h3 class="mt-1 text-2xl font-semibold text-funeral-800"><?= htmlspecialchars($selectedParlor['name']) ?></h3>
        </div>
        <span class="f-pill <?= status_pill_class($selectedParlor['status']) ?>"><?= htmlspecialchars(status_label($selectedParlor['status'])) ?></span>
      </div>
      <?php if ($selectedParlor['status'] === 'pending'): ?>
        <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
          <?php if (!is_in_service_area($selectedParlor['location'])): ?>
            This parlor is <strong>pending</strong> because its location isn't recognized. Update the address to a listed municipality and barangay to become eligible for review.
          <?php else: ?>
            This parlor is <strong>pending admin approval</strong>. Your listing will become visible to clients automatically once approved — you can keep managing packages and details below in the meantime.
          <?php endif; ?>
        </div>
      <?php elseif ($selectedParlor['status'] === 'rejected'): ?>
        <div class="mt-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          This parlor was <strong>declined</strong><?= !empty($selectedParlor['status_reason']) ? ' — ' . htmlspecialchars($selectedParlor['status_reason']) : '.' ?> It stays hidden from clients until an admin re-approves it. You can update its details below at any time.
        </div>
      <?php endif; ?>
      <div class="f-divider my-5"></div>
      <div class="grid gap-4 sm:grid-cols-3">
        <a href="<?= BASE_PATH ?>/director/packages.php" class="group f-card-flat flex flex-col items-start gap-3 p-4 transition hover:shadow-soft">
          <span class="f-icon-tile"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a4 4 0 0 1 8 0v2"/></svg></span>
          <span class="font-semibold text-funeral-800">Manage Packages</span>
          <span class="text-sm text-stone-500">Create and price the service packages families see.</span>
        </a>
        <a href="<?= BASE_PATH ?>/director/inventory.php" class="group f-card-flat flex flex-col items-start gap-3 p-4 transition hover:shadow-soft">
          <span class="f-icon-tile"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7 12 3 4 7l8 4 8-4Z"/><path d="M4 7v10l8 4 8-4V7"/><path d="M12 11v10"/></svg></span>
          <span class="font-semibold text-funeral-800">Manage Inventory</span>
          <span class="text-sm text-stone-500">Track flowers, caskets, and supplies on hand.</span>
        </a>
        <a href="<?= BASE_PATH ?>/director/account.php" class="group f-card-flat flex flex-col items-start gap-3 p-4 transition hover:shadow-soft">
          <span class="f-icon-tile"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg></span>
          <span class="font-semibold text-funeral-800">Manage Account</span>
          <span class="text-sm text-stone-500">Update your contact details.</span>
        </a>
      </div>
      <div class="mt-5 text-center">
        <a href="<?= BASE_PATH ?>/director/funeral_parlors.php" class="text-sm font-medium text-funeral-700 hover:text-funeral-600">Switch parlor or register another →</a>
      </div>
    </section>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
