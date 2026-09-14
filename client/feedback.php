<?php
require_once __DIR__ . '/../includes/header.php';
require_any_role(['client']);
$pdo = pdo_connect();
ensure_recommender_tables($pdo);
ensure_feedback_and_media_columns($pdo);
$user = current_user();

$parlorId = (int)($_GET['parlor_id'] ?? $_POST['parlor_id'] ?? 0);
$parlorStmt = $pdo->prepare("SELECT * FROM funeral_parlors WHERE id = ? AND status = 'approved'");
$parlorStmt->execute([$parlorId]);
$parlor = $parlorStmt->fetch();
if (!$parlor) {
    http_response_code(404);
    echo '<div class="mx-auto max-w-xl py-8"><div class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-700">Funeral parlor not found.</div></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$categoryLabels = [
    'location' => 'Location & accessibility',
    'budget' => 'Budget affordability',
    'religion' => 'Religious accommodation',
    'casket' => 'Casket options',
    'service_delivery' => 'Service delivery',
    'documentation' => 'Documentation & paperwork',
    'arrangement' => 'Arrangement flexibility',
    'flower' => 'Flowers & décor',
];

$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ratings = [];
    foreach (array_keys($categoryLabels) as $category) {
        $value = (int)($_POST[$category] ?? 0);
        $ratings[$category] = max(1, min(5, $value ?: 3));
    }
    $comment = trim($_POST['comment'] ?? '');

    $stmt = $pdo->prepare(
        'INSERT INTO parlor_feedback (parlor_id, client_id, location, budget, religion, casket, service_delivery, documentation, arrangement, flower, comment)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
            location=VALUES(location), budget=VALUES(budget), religion=VALUES(religion), casket=VALUES(casket),
            service_delivery=VALUES(service_delivery), documentation=VALUES(documentation),
            arrangement=VALUES(arrangement), flower=VALUES(flower), comment=VALUES(comment)'
    );
    $stmt->execute([
        $parlor['id'], $user['id'],
        $ratings['location'], $ratings['budget'], $ratings['religion'], $ratings['casket'],
        $ratings['service_delivery'], $ratings['documentation'], $ratings['arrangement'], $ratings['flower'],
        $comment ?: null,
    ]);
    $success = 'Thank you — your feedback now factors into this parlor\'s matched score for future families.';
}

$existingStmt = $pdo->prepare('SELECT * FROM parlor_feedback WHERE parlor_id = ? AND client_id = ?');
$existingStmt->execute([$parlor['id'], $user['id']]);
$existing = $existingStmt->fetch();
?>
<div class="mx-auto max-w-2xl space-y-5 py-6 sm:py-10">
  <div class="f-card p-6 text-center sm:p-8">
    <span class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-funeral-100 text-funeral-700">
      <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 1 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"/></svg>
    </span>
    <p class="f-eyebrow mb-2">Optional feedback</p>
    <h1 class="text-3xl font-bold text-funeral-800">Rate <?= htmlspecialchars($parlor['name']) ?></h1>
    <p class="mx-auto mt-2 max-w-lg text-stone-600">Entirely optional. If you'd like to share your experience, it helps other families and adjusts this parlor's matched score.</p>
  </div>

  <?php if ($success): ?>
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= htmlspecialchars($success) ?></div>
  <?php elseif ($existing): ?>
    <div class="rounded-xl border border-funeral-200 bg-funeral-50 px-4 py-3 text-sm text-funeral-800">You've already rated this parlor — submitting again will update your existing feedback.</div>
  <?php endif; ?>

  <form method="post" class="space-y-6">
    <input type="hidden" name="parlor_id" value="<?= (int)$parlor['id'] ?>" />
    <div class="f-card p-6 sm:p-8">
      <p class="f-eyebrow mb-1">Rate each aspect</p>
      <p class="mb-5 text-sm text-stone-500">1 = poor, 5 = excellent. Leave any at the default if you're unsure.</p>
      <div class="space-y-5">
        <?php foreach ($categoryLabels as $key => $label): ?>
          <?php $current = $existing ? (int)$existing[$key] : 3; ?>
          <div class="border-b border-stone-100 pb-5 last:border-0 last:pb-0">
            <div class="mb-2 font-medium text-stone-800"><?= htmlspecialchars($label) ?></div>
            <div class="grid grid-cols-5 gap-2" role="radiogroup" aria-label="<?= htmlspecialchars($label) ?> rating">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <label class="rating-option cursor-pointer rounded-xl border-2 <?= $current === $i ? 'border-funeral-500 bg-funeral-50' : 'border-stone-200 bg-white' ?> px-2 py-2.5 text-center text-sm font-semibold text-stone-700 transition">
                  <input type="radio" name="<?= $key ?>" value="<?= $i ?>" class="sr-only" <?= $current === $i ? 'checked' : '' ?> />
                  <?= $i ?>
                </label>
              <?php endfor; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="f-card p-6 sm:p-8">
      <label class="mb-1 block text-sm font-medium text-stone-700">Comments (optional)</label>
      <textarea name="comment" rows="3" class="w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3 outline-none transition focus:border-funeral-500 focus:ring-2 focus:ring-funeral-200" placeholder="Anything else you'd like to share?"><?= htmlspecialchars($existing['comment'] ?? '') ?></textarea>
    </div>

    <div class="flex flex-col items-center gap-3 sm:flex-row sm:justify-between">
      <a href="<?= BASE_PATH ?>/client/dashboard.php" class="inline-flex items-center gap-1 text-sm font-medium text-stone-600 hover:text-stone-800">
        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.5 15L7.5 10L12.5 5" /></svg>
        Back to dashboard
      </a>
      <button type="submit" class="w-full rounded-full bg-funeral-500 px-6 py-3 font-semibold text-white shadow-lg shadow-funeral-500/20 transition hover:bg-funeral-400 sm:w-auto"><?= $existing ? 'Update feedback' : 'Submit feedback' ?></button>
    </div>
  </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
document.querySelectorAll('.rating-option input').forEach(input => {
  input.addEventListener('change', () => {
    const group = input.closest('[role="radiogroup"]');
    group.querySelectorAll('.rating-option').forEach(opt => {
      const active = opt.querySelector('input').checked;
      opt.classList.toggle('border-funeral-500', active);
      opt.classList.toggle('bg-funeral-50', active);
      opt.classList.toggle('border-stone-200', !active);
      opt.classList.toggle('bg-white', !active);
    });
  });
});
</script>
