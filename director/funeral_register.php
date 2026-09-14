<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_any_role(['director']);
$pdo = pdo_connect();
$user = current_user();
$error = '';
$success = '';
$parlorId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$existing = null;
if ($parlorId) {
  $stmt = $pdo->prepare('SELECT * FROM funeral_parlors WHERE id = ? AND director_id = ?');
  $stmt->execute([$parlorId, $user['id']]);
  $existing = $stmt->fetch();
  if (!$existing) { http_response_code(404); exit('Funeral parlor not found.'); }
}

$iloiloLocations = require __DIR__ . '/../includes/iloilo_locations.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $municipality = trim($_POST['municipality'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $location = ($barangay && $municipality) ? "{$barangay}, {$municipality}" : '';
    $contact = trim($_POST['contact_number'] ?? '');
    $license = $_FILES['license'] ?? null;
    $hasNewLicense = $license && $license['error'] === UPLOAD_ERR_OK;
    if (!$name || !$location || !$contact || (!$existing && !$hasNewLicense)) {
      $error = 'Name, location, contact number, and a license picture are required.';
    } else {
      // Extension is derived from the sniffed MIME type, never the user-supplied
      // filename — trusting an attacker-chosen filename's extension (even after
      // a content check) can let a disguised script land in uploads/ as .php.
      $allowedLicenseExtensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'application/pdf' => 'pdf'];
      $mime = $hasNewLicense ? (new finfo(FILEINFO_MIME_TYPE))->file($license['tmp_name']) : null;
      if ($hasNewLicense && !isset($allowedLicenseExtensions[$mime])) {
            $error = 'License must be a PNG, JPG, or PDF file.';
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS funeral_parlors (id INT AUTO_INCREMENT PRIMARY KEY, director_id INT NOT NULL, name VARCHAR(200) NOT NULL, location VARCHAR(255) NOT NULL, contact_number VARCHAR(50) NOT NULL, license_path VARCHAR(255) NOT NULL, status ENUM('pending','approved','rejected') DEFAULT 'pending', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (director_id) REFERENCES users(id) ON DELETE CASCADE)");
            $licensePath = $existing['license_path'] ?? null;
            if ($hasNewLicense) {
              $filename = uniqid('parlor_license_', true) . '.' . $allowedLicenseExtensions[$mime];
              move_uploaded_file($license['tmp_name'], __DIR__ . '/../uploads/' . $filename);
              $licensePath = 'uploads/' . $filename;
            }
            // Registration itself is never blocked by location. A brand-new
            // parlor always starts pending; an existing one keeps its current
            // status — status changes are admin-driven (Approve/Pending/Decline
            // from Manage Accounts), never a side effect of editing. The one
            // defensive exception: a location outside every recognized
            // municipality/barangay forces pending, since it can't be reviewed
            // against known data.
            $inScope = is_in_service_area($location);
            if ($existing) {
              if (!$inScope) {
                $newStatus = 'pending';
                $success = "Funeral parlor updated. That location isn't recognized, so it stays pending until corrected.";
              } else {
                $newStatus = $existing['status'];
                $success = 'Funeral parlor updated.';
              }
              $stmt = $pdo->prepare('UPDATE funeral_parlors SET name=?,location=?,contact_number=?,license_path=?,status=? WHERE id=? AND director_id=?');
              $stmt->execute([$name, $location, $contact, $licensePath, $newStatus, $parlorId, $user['id']]);
              $existing['status'] = $newStatus;
            } else {
              $stmt = $pdo->prepare('INSERT INTO funeral_parlors (director_id,name,location,contact_number,license_path,status) VALUES (?,?,?,?,?,?)');
              $stmt->execute([$user['id'], $name, $location, $contact, $licensePath, 'pending']);
              $success = 'Funeral parlor submitted. An administrator will review it and set it to Approved, Pending, or Declined.';
            }
        }
    }
}

$prefillMunicipality = '';
$prefillBarangay = '';
if (!empty($existing['location']) && str_contains($existing['location'], ',')) {
    [$maybeBarangay, $maybeMunicipality] = array_map('trim', explode(',', $existing['location'], 2));
    if (isset($iloiloLocations[$maybeMunicipality]) && in_array($maybeBarangay, $iloiloLocations[$maybeMunicipality], true)) {
        $prefillMunicipality = $maybeMunicipality;
        $prefillBarangay = $maybeBarangay;
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="mx-auto max-w-2xl py-6 sm:py-10">
  <div class="f-card p-6 sm:p-8">
    <p class="f-eyebrow mb-2">Director service</p>
    <h2 class="mb-2 text-3xl font-bold text-funeral-800"><?= $existing ? 'Manage funeral parlor' : 'Register a funeral parlor' ?></h2>
    <p class="mb-6 text-sm text-stone-600">Submit each parlor separately. Even when you operate only one, select and manage it as its own approved location.</p>
    <?php if ($error): ?><div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="space-y-4">
      <?php if ($existing): ?><input type="hidden" name="id" value="<?= (int)$existing['id'] ?>" /><?php endif; ?>
      <div><label class="mb-1 block text-sm font-medium text-stone-700">Funeral parlor name</label><input name="name" value="<?= htmlspecialchars($existing['name'] ?? '') ?>" required class="w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3 outline-none transition focus:border-funeral-500 focus:ring-2 focus:ring-funeral-200" /></div>
      <?php if (!empty($existing['location']) && !$prefillMunicipality): ?>
        <p class="text-xs text-stone-500">Current: <?= htmlspecialchars($existing['location']) ?> — choose below to replace it.</p>
      <?php endif; ?>
      <?php render_location_fields($iloiloLocations, 'reg', $prefillMunicipality, $prefillBarangay, 'municipality', 'barangay', 'Location'); ?>
      <div><label class="mb-1 block text-sm font-medium text-stone-700">Contact number</label><input name="contact_number" value="<?= htmlspecialchars($existing['contact_number'] ?? '') ?>" required class="w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3 outline-none transition focus:border-funeral-500 focus:ring-2 focus:ring-funeral-200" /></div>
      <div><label class="mb-1 block text-sm font-medium text-stone-700">License picture <?= $existing ? '(optional replacement)' : '' ?></label><input name="license" type="file" accept="image/*,application/pdf" <?= $existing ? '' : 'required' ?> class="w-full rounded-xl border border-dashed border-stone-300 bg-stone-50 px-3 py-3 text-sm" /></div>
      <div class="flex items-center gap-4 pt-1"><button class="rounded-full bg-funeral-600 px-5 py-2.5 font-semibold text-white shadow-sm transition hover:bg-funeral-500">Submit for approval</button><a href="<?= BASE_PATH ?>/director/dashboard.php" class="text-sm font-medium text-funeral-700 hover:text-funeral-600">Cancel</a></div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<?php location_dropdown_script($iloiloLocations, 'reg'); ?>
