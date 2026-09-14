<?php
require_once __DIR__ . '/../includes/header.php';
// allow directors and clients to view packages
require_any_role(['director','client']);
$pdo = pdo_connect();
$user = current_user();
ensure_director_scope_columns($pdo);
ensure_feedback_and_media_columns($pdo);
ensure_package_inclusion_images($pdo);
$selectedParlor = $user && $user['role'] === 'director' ? selected_director_parlor($pdo, (int)$user['id']) : null;
if ($user && $user['role'] === 'director' && !$selectedParlor) { header('Location: ' . BASE_PATH . '/director/funeral_parlors.php'); exit; }

// CRUD operations handled by package_action.php
if ($user && $user['role'] === 'director') {
  $stmt = $pdo->prepare('SELECT * FROM packages WHERE director_id = ? AND parlor_id = ? ORDER BY created_at DESC');
  $stmt->execute([$user['id'], $selectedParlor['id']]);
  $packages = $stmt->fetchAll();
} else {
  $packages = $pdo->query('SELECT * FROM packages ORDER BY created_at DESC')->fetchAll();
}
?>
<div class="space-y-5 py-2">
  <div class="flex flex-col gap-3 rounded-[24px] border border-stone-200 bg-white/80 p-5 shadow-soft sm:flex-row sm:items-center sm:justify-between">
    <div>
      <p class="f-eyebrow mb-1">Service catalog</p>
      <h2 class="text-2xl font-semibold text-funeral-800">Funeral Packages</h2>
    </div>
    <?php if ($user && $user['role'] === 'director'): ?>
      <a href="package_action.php?action=create" class="inline-flex items-center gap-2 rounded-full bg-funeral-600 px-4 py-2 text-sm font-semibold text-white shadow transition hover:bg-funeral-500">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
        Create Package
      </a>
    <?php endif; ?>
  </div>

  <?php if (empty($packages)): ?>
    <div class="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-stone-300 bg-stone-50/60 p-8 text-center">
      <span class="text-2xl">📦</span>
      <p class="text-sm font-medium text-stone-700">No packages found.</p>
      <p class="text-sm text-stone-500">Create a new package to get started.</p>
    </div>
  <?php else: ?>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <?php foreach($packages as $p): ?>
        <div class="f-card-flat flex flex-col justify-between p-5 shadow-sm">
          <div>
            <?php if (!empty($p['image_path'])): ?>
              <img src="<?= htmlspecialchars(BASE_PATH . '/' . ltrim($p['image_path'], '/')) ?>" alt="<?= htmlspecialchars($p['title']) ?>" class="mb-3 h-32 w-full rounded-xl border border-stone-200 object-cover" />
            <?php endif; ?>
            <div class="mb-1 text-lg font-semibold text-funeral-800"><?=htmlspecialchars($p['title'])?></div>
            <div class="mb-3 text-sm leading-relaxed text-stone-600"><?=nl2br(htmlspecialchars($p['inclusions']))?></div>
            <?php render_package_inclusions($pdo, (int)$p['id']); ?>
          </div>
          <div class="mt-4 border-t border-stone-200 pt-3">
            <div class="text-sm font-medium text-stone-700">Price</div>
            <div class="text-xl font-bold text-funeral-800">₱<?=htmlspecialchars(number_format($p['price'],2))?></div>
            <?php if ($user && $user['role'] === 'director'): ?>
              <div class="mt-3 flex gap-2">
                <a href="package_action.php?action=edit&id=<?=$p['id']?>" class="inline-flex items-center px-3 py-1.5 rounded-full bg-amber-500 text-white text-sm font-medium transition hover:bg-amber-400">Edit</a>
                <a href="package_action.php?action=delete&id=<?=$p['id']?>" class="inline-flex items-center px-3 py-1.5 rounded-full bg-red-600 text-white text-sm font-medium transition hover:bg-red-500" onclick="return confirm('Delete this package?');">Delete</a>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
