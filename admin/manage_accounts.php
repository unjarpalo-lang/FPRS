<?php
require_once __DIR__ . '/../includes/header.php';
require_any_role(['admin']);
$pdo = pdo_connect();
ensure_director_scope_columns($pdo);

$statusFilter = $_GET['status'] ?? 'all';
$sql = "SELECT fp.*, u.full_name AS director_name, u.email AS director_email
        FROM funeral_parlors fp
        JOIN users u ON u.id = fp.director_id";
$params = [];
if (in_array($statusFilter, ['approved', 'pending', 'rejected'], true)) {
    $sql .= ' WHERE fp.status = ?';
    $params[] = $statusFilter;
}
$sql .= ' ORDER BY fp.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$parlors = $stmt->fetchAll();

$counts = $pdo->query("SELECT status, COUNT(*) c FROM funeral_parlors GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$tabs = [
    'all' => 'All (' . array_sum($counts) . ')',
    'approved' => 'Approved (' . ($counts['approved'] ?? 0) . ')',
    'pending' => 'Pending (' . ($counts['pending'] ?? 0) . ')',
    'rejected' => 'Declined (' . ($counts['rejected'] ?? 0) . ')',
];
?>
<div class="space-y-6 py-4">
  <div class="flex flex-col gap-3 rounded-[28px] border border-stone-200 bg-white/80 p-5 shadow-soft sm:flex-row sm:items-center sm:justify-between sm:p-6">
    <div>
      <p class="f-eyebrow mb-2">Admin access</p>
      <h2 class="text-3xl font-bold text-funeral-800">Manage Accounts</h2>
      <p class="mt-1 text-sm text-stone-600">Every funeral parlor account and its current status. Change status directly — no confirmation needed.</p>
    </div>
    <a href="<?= BASE_PATH ?>/admin/dashboard.php" class="inline-flex shrink-0 items-center gap-1 rounded-full border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-stone-700 transition hover:bg-stone-50">
      <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.5 15L7.5 10L12.5 5" /></svg>
      Back to dashboard
    </a>
  </div>

  <div class="f-card p-5 sm:p-6">
    <div class="mb-4 flex flex-wrap gap-2">
      <?php foreach ($tabs as $value => $label): ?>
        <a href="?status=<?= $value ?>" class="rounded-full px-4 py-2 text-sm font-medium transition <?= $statusFilter === $value ? 'bg-funeral-600 text-white' : 'border border-stone-300 bg-white text-stone-700 hover:bg-stone-50' ?>"><?= htmlspecialchars($label) ?></a>
      <?php endforeach; ?>
    </div>

    <?php if (!$parlors): ?>
      <div class="rounded-2xl border border-dashed border-stone-300 bg-stone-50/60 p-8 text-center text-sm text-stone-500">No accounts in this category.</div>
    <?php else: ?>
      <div class="space-y-3">
        <?php foreach ($parlors as $parlor): ?>
          <div class="f-card-flat p-4 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <span class="font-semibold text-stone-800"><?= htmlspecialchars($parlor['name']) ?></span>
                  <span class="f-pill <?= status_pill_class($parlor['status']) ?>"><?= htmlspecialchars(status_label($parlor['status'])) ?></span>
                </div>
                <div class="mt-1 text-sm text-stone-500"><?= htmlspecialchars($parlor['location']) ?> · <?= htmlspecialchars($parlor['contact_number']) ?></div>
                <div class="text-sm text-stone-500">Director: <?= htmlspecialchars($parlor['director_name']) ?> (<?= htmlspecialchars($parlor['director_email']) ?>)</div>
                <?php if ($parlor['status'] === 'rejected' && !empty($parlor['status_reason'])): ?>
                  <div class="mt-1 text-sm text-red-700">Reason: <?= htmlspecialchars($parlor['status_reason']) ?></div>
                <?php endif; ?>
              </div>

              <div class="flex shrink-0 flex-wrap items-center gap-2">
                <a href="<?= BASE_PATH ?>/admin/parlor_action.php?action=view&id=<?= (int)$parlor['id'] ?>" class="rounded-full border border-stone-300 px-3 py-1.5 text-xs font-medium text-stone-700 transition hover:bg-stone-50">View license</a>

                <?php if ($parlor['status'] !== 'approved'): ?>
                  <a href="<?= BASE_PATH ?>/admin/parlor_action.php?action=set_status&status=approved&id=<?= (int)$parlor['id'] ?>&redirect=<?= urlencode(BASE_PATH . '/admin/manage_accounts.php?status=' . $statusFilter) ?>" class="rounded-full bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-emerald-500">Approve</a>
                <?php endif; ?>
                <?php if ($parlor['status'] !== 'pending'): ?>
                  <a href="<?= BASE_PATH ?>/admin/parlor_action.php?action=set_status&status=pending&id=<?= (int)$parlor['id'] ?>&redirect=<?= urlencode(BASE_PATH . '/admin/manage_accounts.php?status=' . $statusFilter) ?>" class="rounded-full bg-amber-500 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-amber-400">Set Pending</a>
                <?php endif; ?>
                <?php if ($parlor['status'] !== 'rejected'): ?>
                  <form method="post" action="<?= BASE_PATH ?>/admin/parlor_action.php" class="flex items-center gap-1">
                    <input type="hidden" name="action" value="set_status" />
                    <input type="hidden" name="status" value="rejected" />
                    <input type="hidden" name="id" value="<?= (int)$parlor['id'] ?>" />
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars(BASE_PATH . '/admin/manage_accounts.php?status=' . $statusFilter) ?>" />
                    <input type="text" name="reason" placeholder="Reason (optional)" class="w-32 rounded-full border border-stone-300 px-2.5 py-1.5 text-xs outline-none focus:border-red-400 focus:ring-1 focus:ring-red-200" />
                    <button type="submit" class="rounded-full bg-red-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-red-500">Decline</button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
