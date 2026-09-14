<?php
require_once __DIR__ . '/../includes/header.php';
require_any_role(['director']);
$pdo = pdo_connect();
ensure_director_scope_columns($pdo);
$user = current_user();
$message = '';
$locationMessage = '';
$iloiloLocations = require __DIR__ . '/../includes/iloilo_locations.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_account'])) {
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    if ($fullName !== '') {
        $stmt = $pdo->prepare('UPDATE users SET full_name=?, phone=? WHERE id=? AND role=?');
        $stmt->execute([$fullName, $phone, $user['id'], 'director']);
        $user = current_user();
        $message = 'Account updated.';
    }
}

$selectedParlor = selected_director_parlor($pdo, (int)$user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_location']) && $selectedParlor) {
    $municipality = trim($_POST['municipality'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $parlorContact = trim($_POST['parlor_contact_number'] ?? '');
    if ($municipality && $barangay && isset($iloiloLocations[$municipality]) && in_array($barangay, $iloiloLocations[$municipality], true) && $parlorContact !== '') {
        $newLocation = "{$barangay}, {$municipality}";
        // The dropdown only ever offers in-scope municipalities, but re-check
        // defensively: an out-of-scope location always forces pending, no exceptions.
        $newStatus = is_in_service_area($newLocation) ? $selectedParlor['status'] : 'pending';
        $stmt = $pdo->prepare('UPDATE funeral_parlors SET location = ?, contact_number = ?, status = ? WHERE id = ? AND director_id = ?');
        $stmt->execute([$newLocation, $parlorContact, $newStatus, $selectedParlor['id'], $user['id']]);
        $selectedParlor['location'] = $newLocation;
        $selectedParlor['contact_number'] = $parlorContact;
        $selectedParlor['status'] = $newStatus;
        $locationMessage = 'Parlor details updated.';
    } else {
        $locationMessage = 'Please choose a municipality, a barangay, and enter a contact number.';
    }
}

$prefillMunicipality = '';
$prefillBarangay = '';
if ($selectedParlor && !empty($selectedParlor['location']) && str_contains($selectedParlor['location'], ',')) {
    [$maybeBarangay, $maybeMunicipality] = array_map('trim', explode(',', $selectedParlor['location'], 2));
    if (isset($iloiloLocations[$maybeMunicipality]) && in_array($maybeBarangay, $iloiloLocations[$maybeMunicipality], true)) {
        $prefillMunicipality = $maybeMunicipality;
        $prefillBarangay = $maybeBarangay;
    }
}
?>
<div class="mx-auto max-w-xl py-6 sm:py-10">
  <section class="f-card p-6 sm:p-8">
    <span class="mb-3 flex h-11 w-11 items-center justify-center rounded-full bg-funeral-100 text-funeral-700">
      <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg>
    </span>
    <p class="f-eyebrow mb-1">Director portal</p>
    <h2 class="text-2xl font-semibold text-funeral-800">Manage Account</h2>
    <?php if ($message): ?><div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <form method="post" class="mt-5 space-y-4">
      <input type="hidden" name="update_account" value="1" />
      <div>
        <label class="mb-1 block text-sm font-medium text-stone-700">Full name</label>
        <input name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required class="w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3 outline-none transition focus:border-funeral-500 focus:ring-2 focus:ring-funeral-200" />
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-stone-700">Your personal phone</label>
        <p class="mb-1.5 text-xs text-stone-500">For your own login/account records only — this is not shown to clients. To change the number clients see, use the parlor contact number below.</p>
        <input name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" class="w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3 outline-none transition focus:border-funeral-500 focus:ring-2 focus:ring-funeral-200" />
      </div>
      <div class="flex gap-3 pt-1">
        <button class="rounded-full bg-funeral-600 px-5 py-2.5 font-semibold text-white shadow-sm transition hover:bg-funeral-500">Save Account</button>
        <a href="<?= BASE_PATH ?>/director/dashboard.php" class="rounded-full border border-stone-300 px-5 py-2.5 text-sm font-semibold text-stone-700 transition hover:bg-stone-50">Dashboard</a>
      </div>
    </form>
  </section>

  <section class="f-card mt-5 p-6 sm:p-8">
    <span class="mb-3 flex h-11 w-11 items-center justify-center rounded-full bg-funeral-100 text-funeral-700">
      <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-4.6-9.5-9C1 8.5 2.8 5 6.2 5c1.9 0 3.4 1 4.8 2.8C12.4 6 13.9 5 15.8 5c3.4 0 5.2 3.5 3.7 7-2.5 4.4-9.5 9-9.5 9Z"/></svg>
    </span>
    <p class="f-eyebrow mb-1">Parlor details</p>
    <h2 class="text-2xl font-semibold text-funeral-800">Update address & contact number</h2>

    <?php if (!$selectedParlor): ?>
      <p class="mt-4 text-sm text-stone-600">You don't have a selected parlor yet. <a href="<?= BASE_PATH ?>/director/funeral_parlors.php" class="font-semibold text-funeral-700 hover:text-funeral-600">Register or select one</a> to set its details here.</p>
    <?php else: ?>
      <p class="mt-1 text-sm text-stone-500">Editing <span class="font-medium text-funeral-700"><?= htmlspecialchars($selectedParlor['name']) ?></span> — this contact number is what clients see when browsing recommendations.</p>
      <?php if ($locationMessage): ?><div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700"><?= htmlspecialchars($locationMessage) ?></div><?php endif; ?>
      <form method="post" class="mt-5 space-y-4">
        <input type="hidden" name="update_location" value="1" />
        <?php if (!$prefillMunicipality && !empty($selectedParlor['location'])): ?>
          <p class="text-xs text-stone-500">Current location: <?= htmlspecialchars($selectedParlor['location']) ?> — choose below to replace it.</p>
        <?php endif; ?>
        <?php render_location_fields($iloiloLocations, 'acct', $prefillMunicipality, $prefillBarangay, 'municipality', 'barangay', 'Location'); ?>
        <div>
          <label class="mb-1 block text-sm font-medium text-stone-700">Parlor contact number</label>
          <input name="parlor_contact_number" value="<?= htmlspecialchars($selectedParlor['contact_number'] ?? '') ?>" required class="w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3 outline-none transition focus:border-funeral-500 focus:ring-2 focus:ring-funeral-200" />
        </div>
        <button class="rounded-full bg-funeral-600 px-5 py-2.5 font-semibold text-white shadow-sm transition hover:bg-funeral-500">Save parlor details</button>
      </form>
    <?php endif; ?>
  </section>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<?php if ($selectedParlor): location_dropdown_script($iloiloLocations, 'acct'); endif; ?>