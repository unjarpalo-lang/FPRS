<?php
require_once __DIR__ . '/../config/db.php';

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function current_user(): ?array {
    if (!is_logged_in()) return null;
    $pdo = pdo_connect();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function require_role(string $role) {
    if (!is_logged_in()) {
        header('Location: ' . BASE_PATH . '/login.php'); exit;
    }
    $user = current_user();
    if (!$user || $user['role'] !== $role) {
        http_response_code(403); echo "Forbidden"; exit;
    }
}

function require_any_role(array $roles) {
    if (!is_logged_in()) { header('Location: ' . BASE_PATH . '/login.php'); exit; }
    $user = current_user();
    if (!$user || !in_array($user['role'],$roles,true)) { http_response_code(403); echo "Forbidden"; exit; }
}

function first_approved_director_id(): ?int {
    $pdo = pdo_connect();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE role = ? AND status = ? ORDER BY id ASC LIMIT 1');
    $stmt->execute(['director', 'approved']);
    $row = $stmt->fetch();
    return $row ? (int)$row['id'] : null;
}

/**
 * The single reusable Municipality -> Barangay location picker, used
 * identically everywhere the app captures a location (client search,
 * parlor registration, parlor address edits). Echoes the field markup;
 * pair with location_dropdown_script() for the cascading behavior.
 */
/**
 * Renders an uploaded document (image or PDF) directly inline — no external
 * link/new tab required, per the "instant review" verification requirement.
 * @param string $localPath Filesystem path, used only to sniff the mime type.
 * @param string $publicPath URL path (BASE_PATH-relative) used as the src.
 */
function render_document_viewer(string $localPath, string $publicPath, string $label): void {
    if (!is_file($localPath)) {
        echo '<div class="mt-2 rounded-xl border border-dashed border-stone-300 bg-stone-50 p-4 text-center text-sm text-stone-500">' . htmlspecialchars($label) . ' file not found.</div>';
        return;
    }
    $mime = (string)mime_content_type($localPath);
    ?>
    <div class="mt-2 overflow-hidden rounded-xl border border-stone-200 bg-stone-50">
        <?php if (str_starts_with($mime, 'image/')): ?>
            <img src="<?= htmlspecialchars($publicPath) ?>" alt="<?= htmlspecialchars($label) ?>" class="max-h-[32rem] w-full object-contain" />
        <?php elseif ($mime === 'application/pdf'): ?>
            <iframe src="<?= htmlspecialchars($publicPath) ?>" title="<?= htmlspecialchars($label) ?>" class="h-[32rem] w-full" loading="lazy"></iframe>
        <?php else: ?>
            <div class="p-4 text-center text-sm text-stone-500">Preview isn't available for this file type (<?= htmlspecialchars($mime) ?>).</div>
        <?php endif; ?>
    </div>
    <?php
}

function render_location_fields(array $locations, string $idPrefix, string $prefillMunicipality, string $prefillBarangay, string $municipalityName = 'municipality', string $barangayName = 'barangay', string $label = 'Location', bool $required = true): void {
    $req = $required ? 'required' : '';
    ?>
    <div>
      <label class="mb-1 block text-sm font-medium text-stone-700"><?= htmlspecialchars($label) ?></label>
      <div class="grid gap-3 sm:grid-cols-2">
        <select name="<?= htmlspecialchars($municipalityName) ?>" id="<?= htmlspecialchars($idPrefix) ?>-municipality" <?= $req ?> class="w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3 outline-none transition focus:border-funeral-500 focus:ring-2 focus:ring-funeral-200">
          <option value="">Select municipality</option>
          <?php foreach (array_keys($locations) as $municipality): ?>
            <option value="<?= htmlspecialchars($municipality) ?>" <?= $municipality === $prefillMunicipality ? 'selected' : '' ?>><?= htmlspecialchars($municipality) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="<?= htmlspecialchars($barangayName) ?>" id="<?= htmlspecialchars($idPrefix) ?>-barangay" <?= $req ?> class="w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3 outline-none transition focus:border-funeral-500 focus:ring-2 focus:ring-funeral-200">
          <option value="">Select barangay</option>
          <?php if ($prefillMunicipality && isset($locations[$prefillMunicipality])): foreach ($locations[$prefillMunicipality] as $barangay): ?>
            <option value="<?= htmlspecialchars($barangay) ?>" <?= $barangay === $prefillBarangay ? 'selected' : '' ?>><?= htmlspecialchars($barangay) ?></option>
          <?php endforeach; endif; ?>
        </select>
      </div>
    </div>
    <?php
}

/** Companion script for render_location_fields() — same idPrefix, same cascading behavior everywhere. */
function location_dropdown_script(array $locations, string $idPrefix): void {
    ?>
    <script>
      (function () {
        var LOCATIONS = <?= json_encode($locations) ?>;
        var m = document.getElementById('<?= htmlspecialchars($idPrefix, ENT_QUOTES) ?>-municipality');
        var b = document.getElementById('<?= htmlspecialchars($idPrefix, ENT_QUOTES) ?>-barangay');
        if (!m || !b) return;
        m.addEventListener('change', function () {
          var list = LOCATIONS[m.value] || [];
          b.innerHTML = '<option value="">Select barangay</option>' + list.map(function (name) {
            var opt = document.createElement('option');
            opt.value = name; opt.textContent = name;
            return opt.outerHTML;
          }).join('');
        });
      })();
    </script>
    <?php
}

/** Municipality parsed out of a "Barangay, Municipality" location string, or '' if unparseable. */
function location_municipality(?string $location): string {
    if (!$location || !str_contains($location, ',')) return '';
    [, $municipality] = array_map('trim', explode(',', $location, 2));
    return $municipality;
}

/** Whether a "Barangay, Municipality" location string falls inside the platform's service area. */
function is_in_service_area(?string $location): bool {
    $locations = require __DIR__ . '/iloilo_locations.php';
    $municipality = location_municipality($location);
    return $municipality !== '' && isset($locations[$municipality]);
}

    /** available above the low-stock threshold, insufficient if low but non-zero, missing at zero.
     *  Mirrors deriveStatus() in assets/js/firebase-inventory.js so both the SQL-tracked and any
     *  Firebase real-time inventory use the same three-state vocabulary. */
    function derive_inventory_status(int $quantity, int $lowThreshold = 3): string {
        if ($quantity <= 0) return 'missing';
        if ($quantity < $lowThreshold) return 'insufficient';
        return 'available';
    }

    function inventory_status_pill_class(string $status): string {
        return match ($status) {
            'available' => 'f-pill-approved',
            'insufficient' => 'f-pill-pending',
            default => 'f-pill-rejected', // missing
        };
    }

    function ensure_inventory_media_columns(PDO $pdo): void {
        $col = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_resources' AND COLUMN_NAME = 'image_path'");
        $col->execute();
        if (!$col->fetchColumn()) {
            $pdo->exec("ALTER TABLE inventory_resources ADD COLUMN image_path VARCHAR(255) NULL");
        }
        $qtyCol = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_resources' AND COLUMN_NAME = 'quantity'");
        $qtyCol->execute();
        if (!$qtyCol->fetchColumn()) {
            $pdo->exec("ALTER TABLE inventory_resources ADD COLUMN quantity INT NOT NULL DEFAULT 0");
        }
        // Widen available_status to the same 3-state vocabulary used everywhere else
        // (available/insufficient/missing) instead of the old available/unavailable.
        // Must widen the enum to a superset FIRST — updating to 'missing' while the
        // column only allows 'available'/'unavailable' silently corrupts the value
        // to '' under MySQL's non-strict mode instead of erroring.
        $pdo->exec("ALTER TABLE inventory_resources MODIFY available_status ENUM('available','insufficient','missing','unavailable') NOT NULL DEFAULT 'available'");
        $pdo->exec("UPDATE inventory_resources SET available_status = 'missing' WHERE available_status = 'unavailable' OR available_status = ''");
        $pdo->exec("ALTER TABLE inventory_resources MODIFY available_status ENUM('available','insufficient','missing') NOT NULL DEFAULT 'available'");
    }

    function ensure_director_scope_columns(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS funeral_parlors (id INT AUTO_INCREMENT PRIMARY KEY, director_id INT NOT NULL, name VARCHAR(200) NOT NULL, location VARCHAR(255) NOT NULL, contact_number VARCHAR(50) NOT NULL, license_path VARCHAR(255) NOT NULL, status ENUM('pending','approved','rejected') DEFAULT 'pending', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (director_id) REFERENCES users(id) ON DELETE CASCADE)");
        $reasonCol = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'funeral_parlors' AND COLUMN_NAME = 'status_reason'");
        $reasonCol->execute();
        if (!$reasonCol->fetchColumn()) {
            $pdo->exec("ALTER TABLE funeral_parlors ADD COLUMN status_reason VARCHAR(500) NULL");
        }
        foreach (['packages' => 'director_id', 'inventory_resources' => 'director_id'] as $table => $column) {
            $check = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
            $check->execute([$table, $column]);
            if (!$check->fetchColumn()) {
                $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} INT NULL");
            }
        }
        foreach (['packages', 'inventory_resources'] as $table) {
            $check = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'parlor_id'");
            $check->execute([$table]);
            if (!$check->fetchColumn()) {
                $pdo->exec("ALTER TABLE {$table} ADD COLUMN parlor_id INT NULL");
            }
        }
    }

    function ensure_feedback_and_media_columns(PDO $pdo): void {
        $col = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'packages' AND COLUMN_NAME = 'image_path'");
        $col->execute();
        if (!$col->fetchColumn()) {
            $pdo->exec("ALTER TABLE packages ADD COLUMN image_path VARCHAR(255) NULL");
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS parlor_feedback (
            id INT AUTO_INCREMENT PRIMARY KEY,
            parlor_id INT NOT NULL,
            client_id INT NOT NULL,
            location TINYINT NOT NULL,
            budget TINYINT NOT NULL,
            religion TINYINT NOT NULL,
            casket TINYINT NOT NULL,
            service_delivery TINYINT NOT NULL,
            documentation TINYINT NOT NULL,
            arrangement TINYINT NOT NULL,
            flower TINYINT NOT NULL,
            comment TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY parlor_client (parlor_id, client_id),
            FOREIGN KEY (parlor_id) REFERENCES funeral_parlors(id) ON DELETE CASCADE,
            FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE
        )");
    }

    /**
     * Deletes a user account and everything that only makes sense attached to
     * it. Real foreign keys handle most of this (funeral_parlors, messages,
     * client_preferences, parlor_feedback cascade automatically), but
     * packages/inventory_resources were added via ALTER TABLE without FKs,
     * so those are cleaned up explicitly to avoid orphaned rows.
     */
    function delete_user_account(PDO $pdo, int $userId): void {
        $pdo->beginTransaction();
        try {
            $parlorStmt = $pdo->prepare('SELECT id FROM funeral_parlors WHERE director_id = ?');
            $parlorStmt->execute([$userId]);
            $parlorIds = $parlorStmt->fetchAll(PDO::FETCH_COLUMN);

            $pdo->prepare('DELETE FROM packages WHERE director_id = ?')->execute([$userId]);
            $pdo->prepare('DELETE FROM inventory_resources WHERE director_id = ?')->execute([$userId]);
            if ($parlorIds) {
                $placeholders = implode(',', array_fill(0, count($parlorIds), '?'));
                $pdo->prepare("DELETE FROM packages WHERE parlor_id IN ({$placeholders})")->execute($parlorIds);
                $pdo->prepare("DELETE FROM inventory_resources WHERE parlor_id IN ({$placeholders})")->execute($parlorIds);
            }

            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    function selected_director_parlor(PDO $pdo, int $directorId): ?array {
        $parlorId = (int)($_SESSION['selected_parlor_id'] ?? 0);
        if (!$parlorId) return null;
        // A parlor is selectable/manageable regardless of status (approved,
        // pending, or declined) — only client-facing visibility is status-gated,
        // never the director's own management tools.
        $stmt = $pdo->prepare('SELECT * FROM funeral_parlors WHERE id = ? AND director_id = ?');
        $stmt->execute([$parlorId, $directorId]);
        return $stmt->fetch() ?: null;
    }

    /** User-facing label for a funeral_parlors.status value — "rejected" reads as "Declined" everywhere. */
    function status_label(string $status): string {
        return match ($status) {
            'approved' => 'Approved',
            'pending' => 'Pending',
            'rejected' => 'Declined',
            default => ucfirst($status),
        };
    }

    /** Tailwind pill class for a funeral_parlors.status value, paired with status_label(). */
    function status_pill_class(string $status): string {
        return match ($status) {
            'approved' => 'f-pill-approved',
            'rejected' => 'f-pill-rejected',
            default => 'f-pill-pending',
        };
    }

    /** Links a SQL users row to its Firebase Auth account. Additive — the existing
     *  password-based session login (login.php) keeps working unchanged. */
    function ensure_firebase_columns(PDO $pdo): void {
        $col = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'firebase_uid'");
        $col->execute();
        if (!$col->fetchColumn()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN firebase_uid VARCHAR(128) NULL UNIQUE");
        }
    }

    /** One row per included service/item within a package (casket, flowers, hearse, ...),
     *  each with its own optional thumbnail. Additive alongside packages.inclusions, which
     *  stays a flattened text summary for the recommender engine's religion-keyword matching. */
    function ensure_package_inclusion_images(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS package_inclusions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            package_id INT NOT NULL,
            service_name VARCHAR(120) NOT NULL,
            service_detail VARCHAR(255) NOT NULL,
            image_path VARCHAR(255) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY package_idx (package_id),
            FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE CASCADE
        )");
        $col = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'package_inclusions' AND COLUMN_NAME = 'description'");
        $col->execute();
        if (!$col->fetchColumn()) {
            $pdo->exec("ALTER TABLE package_inclusions ADD COLUMN description VARCHAR(500) NULL AFTER service_detail");
        }
    }

    /**
     * Responsive grid of a package's inclusions, each with its own thumbnail
     * (or a neutral placeholder icon when a provider hasn't uploaded one).
     */
    /**
     * @param bool $compact True for small inline previews (e.g. a director's package
     *   card): smaller thumbnails, name/detail only. False for the full package
     *   details page: larger images plus each item's description.
     */
    function render_package_inclusions(PDO $pdo, int $packageId, bool $compact = true): void {
        $stmt = $pdo->prepare('SELECT * FROM package_inclusions WHERE package_id = ? ORDER BY sort_order ASC, id ASC');
        $stmt->execute([$packageId]);
        $items = $stmt->fetchAll();
        if (!$items) {
            echo '<p class="text-sm text-stone-500">This provider hasn\'t itemized individual inclusions yet.</p>';
            return;
        }
        $imgSize = $compact ? 'h-14 w-14' : 'h-20 w-20';
        $iconSize = $compact ? 'h-6 w-6' : 'h-8 w-8';
        ?>
        <div class="grid grid-cols-1 gap-3 <?= $compact ? 'sm:grid-cols-2 lg:grid-cols-3' : 'sm:grid-cols-2' ?>">
            <?php foreach ($items as $item): ?>
                <div class="flex items-center gap-3 rounded-xl border border-stone-200 bg-white p-3 shadow-sm">
                    <?php if (!empty($item['image_path'])): ?>
                        <img src="<?= htmlspecialchars(BASE_PATH . '/' . ltrim($item['image_path'], '/')) ?>" alt="<?= htmlspecialchars($item['service_name']) ?>" class="<?= $imgSize ?> shrink-0 rounded-lg border border-stone-200 object-cover" />
                    <?php else: ?>
                        <div class="<?= $imgSize ?> flex shrink-0 items-center justify-center rounded-lg border border-dashed border-stone-300 bg-stone-50 text-stone-300">
                            <svg class="<?= $iconSize ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/></svg>
                        </div>
                    <?php endif; ?>
                    <div class="min-w-0">
                        <div class="truncate text-sm font-semibold text-stone-800"><?= htmlspecialchars($item['service_name']) ?></div>
                        <div class="truncate text-xs text-stone-500"><?= htmlspecialchars($item['service_detail']) ?></div>
                        <?php if (!$compact && !empty($item['description'])): ?>
                            <div class="mt-1 text-xs leading-relaxed text-stone-600"><?= htmlspecialchars($item['description']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    function ensure_recommender_tables(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS parlor_survey_responses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            parlor_id INT NOT NULL,
            respondent_no INT NOT NULL,
            criterion_key VARCHAR(40) NOT NULL,
            score TINYINT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY parlor_idx (parlor_id),
            FOREIGN KEY (parlor_id) REFERENCES funeral_parlors(id) ON DELETE CASCADE
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS client_preferences (
            client_id INT PRIMARY KEY,
            budget DECIMAL(10,2) NULL,
            religion VARCHAR(100) NULL,
            max_distance DECIMAL(6,2) NULL,
            importance_location TINYINT NOT NULL DEFAULT 3,
            importance_budget TINYINT NOT NULL DEFAULT 3,
            importance_religion TINYINT NOT NULL DEFAULT 3,
            importance_casket TINYINT NOT NULL DEFAULT 3,
            importance_service_delivery TINYINT NOT NULL DEFAULT 3,
            importance_documentation TINYINT NOT NULL DEFAULT 3,
            importance_arrangement TINYINT NOT NULL DEFAULT 3,
            importance_flower TINYINT NOT NULL DEFAULT 3,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        $col = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_preferences' AND COLUMN_NAME = 'location'");
        $col->execute();
        if (!$col->fetchColumn()) {
            $pdo->exec("ALTER TABLE client_preferences ADD COLUMN location VARCHAR(255) NULL");
        }
    }
