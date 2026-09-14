<?php
require_once __DIR__ . '/../includes/header.php';
require_any_role(['client']);
$user = current_user();
$pdo = pdo_connect();
$pdo->exec("CREATE TABLE IF NOT EXISTS funeral_parlors (id INT AUTO_INCREMENT PRIMARY KEY, director_id INT NOT NULL, name VARCHAR(200) NOT NULL, location VARCHAR(255) NOT NULL, contact_number VARCHAR(50) NOT NULL, license_path VARCHAR(255) NOT NULL, status ENUM('pending','approved','rejected') DEFAULT 'pending', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (director_id) REFERENCES users(id) ON DELETE CASCADE)");
$columnCheck = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'packages' AND COLUMN_NAME = 'parlor_id'");
$columnCheck->execute();
if (!$columnCheck->fetchColumn()) {
  $pdo->exec('ALTER TABLE packages ADD COLUMN parlor_id INT NULL');
}
require_once __DIR__ . '/../includes/RecommenderEngine.php';
ensure_recommender_tables($pdo);
ensure_feedback_and_media_columns($pdo);

// remember this as the last visited dashboard for Back buttons
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['last_dashboard'] = BASE_PATH . '/client/dashboard.php';

$iloiloLocations = require __DIR__ . '/../includes/iloilo_locations.php';
// Label shown in the dropdown => keywords checked against package title/inclusions text.
// A package's religion isn't a structured field, so matching is keyword-based rather
// than requiring the exact label text — "Other" has no reliable keyword, so it never
// excludes anything (safer than silently hiding every package tagged "other faiths").
$religionOptions = [
    'Roman Catholic' => ['catholic'],
    'Christian / Protestant' => ['christian', 'protestant'],
    'Iglesia ni Cristo' => ['iglesia', 'inc'],
    'Islam' => ['islam', 'muslim'],
    'Buddhist' => ['buddh'],
    'Other' => [],
];
// A package qualifies as a "near-budget" match up to this much over budget.
const BUDGET_TOLERANCE = 0.15;

if (isset($_GET['clear'])) {
    $pdo->prepare('DELETE FROM client_preferences WHERE client_id = ?')->execute([$user['id']]);
    header('Location: ' . BASE_PATH . '/client/dashboard.php');
    exit;
}

$prefStmt = $pdo->prepare('SELECT * FROM client_preferences WHERE client_id = ?');
$prefStmt->execute([$user['id']]);
$savedPreferences = $prefStmt->fetch();

if (isset($_GET['search'])) {
    $searchBudget = trim($_GET['budget'] ?? '') !== '' ? (float)$_GET['budget'] : null;
    $searchReligion = trim($_GET['religion'] ?? '');
    $searchMunicipality = trim($_GET['municipality'] ?? '');
    $searchBarangay = trim($_GET['barangay'] ?? '');
    $searchLocation = ($searchMunicipality && $searchBarangay && isset($iloiloLocations[$searchMunicipality]) && in_array($searchBarangay, $iloiloLocations[$searchMunicipality], true))
        ? "{$searchBarangay}, {$searchMunicipality}" : '';

    $stmt = $pdo->prepare(
        'INSERT INTO client_preferences (client_id, budget, religion, location)
         VALUES (?,?,?,?)
         ON DUPLICATE KEY UPDATE budget = VALUES(budget), religion = VALUES(religion), location = VALUES(location)'
    );
    $stmt->execute([$user['id'], $searchBudget, $searchReligion ?: null, $searchLocation ?: null]);
} else {
    $searchBudget = $savedPreferences && $savedPreferences['budget'] !== null ? (float)$savedPreferences['budget'] : null;
    $searchReligion = $savedPreferences['religion'] ?? '';
    $searchLocation = $savedPreferences['location'] ?? '';
    $searchMunicipality = location_municipality($searchLocation);
    $searchBarangay = '';
    if ($searchLocation && str_contains($searchLocation, ',')) {
        [$searchBarangay] = array_map('trim', explode(',', $searchLocation, 2));
    }
}

// Pull every package from approved parlors/directors only — a pending or
// rejected account's packages must never reach client search, no exceptions.
$rows = $pdo->query(
    "SELECT fp.id AS parlor_id, fp.name AS parlor_name, fp.location, fp.contact_number, u.full_name, u.email,
            p.id AS package_id, p.title, p.inclusions, p.price, p.image_path
     FROM funeral_parlors fp
     JOIN users u ON u.id = fp.director_id
     JOIN packages p ON p.parlor_id = fp.id
     WHERE fp.status = 'approved' AND u.status = 'approved'
     ORDER BY p.price ASC"
)->fetchAll();

$exactResults = [];
$flaggedResults = [];
foreach ($rows as $row) {
    // Defensive only: excludes a parlor whose location isn't a recognized
    // municipality/barangay at all. Every parlor registered through the
    // standard dropdown always passes this.
    if (!is_in_service_area($row['location'])) {
        continue;
    }

    $price = (float)$row['price'];

    if ($searchBudget === null) {
        $budgetFlag = null;
    } elseif ($price <= $searchBudget) {
        $budgetFlag = null;
    } elseif ($price <= $searchBudget * (1 + BUDGET_TOLERANCE)) {
        $budgetFlag = 'Close to your budget';
    } else {
        continue; // too far over budget — excluded, not just flagged
    }

    if ($searchReligion !== '' && !empty($religionOptions[$searchReligion])) {
        $haystack = $row['title'] . ' ' . $row['inclusions'];
        $matchesKeyword = false;
        foreach ($religionOptions[$searchReligion] as $keyword) {
            if (stripos($haystack, $keyword) !== false) { $matchesKeyword = true; break; }
        }
        if (!$matchesKeyword) {
            continue; // religion is a hard filter, no near-match variant
        }
    }

    $locationFlag = null;
    if ($searchMunicipality !== '') {
        $parlorMunicipality = location_municipality($row['location']);
        $parlorBarangay = str_contains($row['location'], ',') ? trim(explode(',', $row['location'], 2)[0]) : '';
        // Municipality without a barangay means "show every parlor in that
        // municipality" as an exact match; a barangay narrows it further.
        $exactLocation = $searchBarangay !== ''
            ? ($parlorMunicipality === $searchMunicipality && $parlorBarangay === $searchBarangay)
            : ($parlorMunicipality === $searchMunicipality);
        if (!$exactLocation) {
            $locationFlag = 'Nearby option';
        }
    }

    $result = [
        'package_id' => (int)$row['package_id'],
        'title' => $row['title'],
        'inclusions' => $row['inclusions'],
        'price' => $price,
        'image_path' => $row['image_path'],
        'parlor_id' => (int)$row['parlor_id'],
        'parlor_name' => $row['parlor_name'],
        'location' => $row['location'],
        'contact_number' => $row['contact_number'],
        'director_name' => $row['full_name'],
        'budget_flag' => $budgetFlag,
        'location_flag' => $locationFlag,
    ];

    if ($budgetFlag === null && $locationFlag === null) {
        $exactResults[] = $result;
    } else {
        $flaggedResults[] = $result;
    }
}
?>
<div class="space-y-6 py-4">
  <div class="flex flex-col gap-3 rounded-[28px] border border-stone-200 bg-white/80 p-5 shadow-soft sm:flex-row sm:items-center sm:justify-between sm:p-6">
    <div>
      <p class="f-eyebrow mb-2">Client portal</p>
      <h2 class="text-3xl font-bold text-funeral-800">Welcome, <?= htmlspecialchars($user['full_name']) ?></h2>
      <p class="mt-1 text-sm text-stone-600">Search by budget, location, and religion — we'll show what fits, and what's close.</p>
    </div>
    <button onclick="history.back()" class="inline-flex shrink-0 items-center gap-1 self-start rounded-full border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-stone-700 transition hover:bg-stone-50 sm:self-auto">
      <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.5 15L7.5 10L12.5 5" /></svg>
      Back
    </button>
  </div>

  <div class="f-card p-6 sm:p-8">
    <div class="mb-5 flex items-center gap-3">
      <div class="f-icon-tile">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
      </div>
      <div>
        <h3 class="text-xl font-semibold text-funeral-800">Find your best match</h3>
        <p class="text-sm text-stone-500">Everything is optional, but the more you share, the sharper the match.</p>
      </div>
    </div>

    <form method="get" class="space-y-4 rounded-2xl border border-stone-200 bg-stone-50/70 p-4 sm:p-5">
      <input type="hidden" name="search" value="1" />
      <div class="grid gap-3 sm:grid-cols-2">
        <div>
          <label class="text-sm font-medium text-stone-700">Budget (PHP)</label>
          <input name="budget" type="number" step="0.01" min="0" value="<?= $searchBudget !== null ? htmlspecialchars((string)$searchBudget) : '' ?>" class="mt-1 w-full rounded-xl border border-stone-300 bg-white px-3 py-2.5 outline-none transition focus:border-funeral-500 focus:ring-2 focus:ring-funeral-200" placeholder="Any amount" />
        </div>
        <div>
          <label class="text-sm font-medium text-stone-700">Religion</label>
          <select name="religion" class="mt-1 w-full rounded-xl border border-stone-300 bg-white px-3 py-2.5 outline-none transition focus:border-funeral-500 focus:ring-2 focus:ring-funeral-200">
            <option value="">Any / no preference</option>
            <?php foreach (array_keys($religionOptions) as $option): ?>
              <option value="<?= htmlspecialchars($option) ?>" <?= $searchReligion === $option ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <?php render_location_fields($iloiloLocations, 'search', $searchMunicipality, $searchBarangay, 'municipality', 'barangay', 'Location', false); ?>

      <div class="flex items-center gap-3 pt-1">
        <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-funeral-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-funeral-500">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
          Show my matches
        </button>
        <a href="<?= BASE_PATH ?>/client/dashboard.php?clear=1" class="text-sm font-medium text-stone-500 hover:text-stone-700">Clear preferences</a>
      </div>
    </form>

    <?php if ($exactResults || $flaggedResults): ?>
      <div class="mt-6 space-y-3">
        <h4 class="text-sm font-semibold uppercase tracking-[0.16em] text-funeral-700">Available Packages</h4>
        <?php if (!$exactResults): ?>
          <p class="text-sm text-stone-500">Nothing matches every filter exactly — see the nearby/close-budget options below.</p>
        <?php else: foreach ($exactResults as $package): ?>
          <?php include __DIR__ . '/_package_result.php'; ?>
        <?php endforeach; endif; ?>
      </div>

      <?php if ($flaggedResults): ?>
        <div class="mt-6 space-y-3 border-t border-stone-200 pt-5">
          <h4 class="text-sm font-semibold uppercase tracking-[0.16em] text-funeral-700">You Might Also Consider</h4>
          <p class="-mt-1 text-xs text-stone-500">Close to your budget or a bit outside your chosen barangay — still worth a look.</p>
          <?php foreach ($flaggedResults as $package): ?>
            <?php include __DIR__ . '/_package_result.php'; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="mt-6 flex flex-col items-center gap-2 rounded-2xl border border-dashed border-stone-300 bg-stone-50/60 p-8 text-center">
        <span class="text-2xl">🔍</span>
        <p class="text-sm font-medium text-stone-700">No packages match your search yet.</p>
        <p class="text-sm text-stone-500">Try widening your budget or clearing a filter.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<?php location_dropdown_script($iloiloLocations, 'search'); ?>
