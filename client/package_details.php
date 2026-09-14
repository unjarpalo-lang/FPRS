<?php
require_once __DIR__ . '/../includes/header.php';
require_any_role(['client']);
$pdo = pdo_connect();
ensure_package_inclusion_images($pdo);

$packageId = (int)($_GET['package_id'] ?? 0);
$stmt = $pdo->prepare(
    "SELECT p.*, fp.id AS parlor_id, fp.name AS parlor_name, fp.location, fp.contact_number
     FROM packages p
     JOIN funeral_parlors fp ON fp.id = p.parlor_id
     JOIN users u ON u.id = fp.director_id
     WHERE p.id = ? AND fp.status = 'approved' AND u.status = 'approved'"
);
$stmt->execute([$packageId]);
$package = $stmt->fetch();

if (!$package) {
    http_response_code(404);
    echo '<div class="mx-auto max-w-lg py-10"><div class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-700">Package not found.</div></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}
?>
<div class="mx-auto max-w-3xl py-6 sm:py-10">
  <a href="<?= BASE_PATH ?>/client/contact.php?package_id=<?= (int)$package['id'] ?>&director_id=<?= (int)$package['parlor_id'] ?>" class="mb-4 inline-flex items-center gap-1 rounded-lg border border-stone-300 bg-white px-4 py-2 text-sm font-semibold text-stone-700 transition hover:bg-stone-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-stone-300">
    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.5 15L7.5 10L12.5 5" /></svg>
    Back to contact details
  </a>

  <div class="f-card overflow-hidden p-0">
    <?php if (!empty($package['image_path'])): ?>
      <img src="<?= htmlspecialchars(BASE_PATH . '/' . ltrim($package['image_path'], '/')) ?>" alt="<?= htmlspecialchars($package['title']) ?>" class="h-56 w-full object-cover sm:h-72" />
    <?php else: ?>
      <div class="flex h-40 w-full items-center justify-center bg-stone-100 text-stone-300">
        <svg class="h-12 w-12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/></svg>
      </div>
    <?php endif; ?>

    <div class="p-6 sm:p-8">
      <p class="f-eyebrow mb-1"><?= htmlspecialchars($package['parlor_name']) ?></p>
      <h1 class="text-2xl font-bold text-funeral-800 sm:text-3xl"><?= htmlspecialchars($package['title']) ?></h1>
      <div class="mt-1 text-2xl font-bold text-funeral-700">₱<?= htmlspecialchars(number_format((float)$package['price'], 2)) ?></div>
      <p class="mt-1 text-sm text-stone-500"><?= htmlspecialchars($package['location']) ?> · <?= htmlspecialchars($package['contact_number']) ?></p>

      <div class="f-divider my-6"></div>

      <h2 class="mb-4 text-lg font-semibold text-funeral-800">What's included</h2>
      <?php render_package_inclusions($pdo, (int)$package['id'], false); ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
