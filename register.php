<?php
require_once __DIR__ . '/includes/header.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
  $role = ($_POST['role'] ?? 'client') === 'director' ? 'director' : 'client';
  $funeral_name = trim($_POST['funeral_name'] ?? '');

    if (!$full || !$email || !$password) $errors[] = 'Please complete required fields.';
    if ($password !== $confirmPassword) $errors[] = 'Passwords do not match.';

    // for clients we require a valid ID; for directors we require a license upload
    // require different uploads depending on role
    $uploadField = $role === 'director' ? 'license' : 'valid_id';
    // Extension is derived from the sniffed MIME type below, never from the
    // user-supplied filename — a filename's extension is attacker-controlled
    // and trusting it (even after a content check) can let a disguised script
    // land in uploads/ with a .php-family name.
    $allowedUploadExtensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'application/pdf' => 'pdf'];
    if (isset($_FILES[$uploadField]) && $_FILES[$uploadField]['error'] === UPLOAD_ERR_OK) {
      $finfo = new finfo(FILEINFO_MIME_TYPE);
      $mime = $finfo->file($_FILES[$uploadField]['tmp_name']);
      if (!isset($allowedUploadExtensions[$mime])) $errors[] = 'Invalid upload format. Use PNG, JPG or PDF.';
    } else {
      $errors[] = ($role === 'director') ? 'License upload is required for directors.' : 'Valid ID upload is required.';
    }

    if ($role === 'director' && !$funeral_name) {
      $errors[] = 'Funeral name is required for director registration.';
    }

    if (empty($errors)) {
        $pdo = pdo_connect();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Email already registered.';
        } else {
            $uploadPath = __DIR__ . '/uploads/';
            $fname = uniqid($uploadField . '_', true) . '.' . $allowedUploadExtensions[$mime];
            move_uploaded_file($_FILES[$uploadField]['tmp_name'], $uploadPath . $fname);
            $hash = password_hash($password, PASSWORD_DEFAULT);
            // ensure columns exist: funeral_name and license_path
            $col = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'funeral_name'");
            $col->execute();
            if (!$col->fetch()) {
              $pdo->exec("ALTER TABLE users ADD COLUMN funeral_name VARCHAR(255) NULL");
            }
            $col2 = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'license_path'");
            $col2->execute();
            if (!$col2->fetch()) {
              $pdo->exec("ALTER TABLE users ADD COLUMN license_path VARCHAR(255) NULL");
            }
            // prepare paths
            $valid_id_path = $role === 'client' ? 'uploads/'.$fname : null;
            $license_path = $role === 'director' ? 'uploads/'.$fname : null;
            // Directors can log in and manage their account immediately — approval
            // is enforced per-parlor (client visibility), not by gating login itself.
            // Clients still go through the existing pending-until-approved login gate.
            $userStatus = $role === 'director' ? 'approved' : 'pending';
            $ins = $pdo->prepare('INSERT INTO users (full_name,email,phone,password,role,valid_id_path,license_path,funeral_name,status) VALUES (?,?,?,?,?,?,?,?,?)');
            $ins->execute([$full,$email,$phone,$hash,$role,$valid_id_path,$license_path, $role === 'director' ? $funeral_name : null,$userStatus]);
            $success = $role === 'director'
                ? 'Registration complete — you can log in right away. Register your funeral parlor next; its listing stays pending until an admin approves it.'
                : 'Registration submitted. Awaiting admin approval.';
        }
    }
}
$postedRole = ($_POST['role'] ?? 'client') === 'director' ? 'director' : 'client';
?>
<div class="mx-auto max-w-2xl py-6 sm:py-10">
  <div class="f-card p-6 sm:p-8">
    <p class="f-eyebrow mb-2">Registration</p>
    <h2 class="mb-1 text-3xl font-bold text-funeral-800">Create your account</h2>
    <p class="mb-6 text-sm text-stone-600">A few details now save time later. Your submission is reviewed gently by our team before you can sign in.</p>

    <?php if (!empty($errors)): ?>
      <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars(implode('<br>', $errors)) ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
      <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="space-y-6">
      <div>
        <p class="mb-2 text-sm font-medium text-stone-700">I am registering as</p>
        <div class="grid grid-cols-2 gap-3" id="role-toggle">
          <label class="role-option cursor-pointer rounded-2xl border-2 border-funeral-500 bg-funeral-50 p-4 text-center transition">
            <input type="radio" name="role" value="client" class="sr-only" <?= $postedRole === 'client' ? 'checked' : '' ?> />
            <span class="block text-lg">🕯️</span>
            <span class="mt-1 block text-sm font-semibold text-funeral-800">Client</span>
            <span class="mt-0.5 block text-xs text-stone-500">Seeking care for a loved one</span>
          </label>
          <label class="role-option cursor-pointer rounded-2xl border-2 border-stone-200 bg-white p-4 text-center transition">
            <input type="radio" name="role" value="director" class="sr-only" <?= $postedRole === 'director' ? 'checked' : '' ?> />
            <span class="block text-lg">🏛️</span>
            <span class="mt-1 block text-sm font-semibold text-funeral-800">Director</span>
            <span class="mt-0.5 block text-xs text-stone-500">Offering funeral services</span>
          </label>
        </div>
      </div>

      <div class="f-divider"></div>

      <div>
        <p class="f-eyebrow mb-3">Personal details</p>
        <div class="space-y-4">
          <div>
            <label class="mb-1 block text-sm font-medium text-stone-700">Full name</label>
            <input name="full_name" required class="w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3 outline-none transition focus:border-funeral-500 focus:ring-2 focus:ring-funeral-200" />
          </div>
          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="mb-1 block text-sm font-medium text-stone-700">Email</label>
              <input name="email" type="email" required class="w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3 outline-none transition focus:border-funeral-500 focus:ring-2 focus:ring-funeral-200" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-stone-700">Phone</label>
              <input name="phone" class="w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3 outline-none transition focus:border-funeral-500 focus:ring-2 focus:ring-funeral-200" />
            </div>
          </div>
          <div id="funeral-name-field">
            <label class="mb-1 block text-sm font-medium text-stone-700">Funeral home name</label>
            <input name="funeral_name" class="w-full rounded-xl border border-stone-300 bg-stone-50 px-4 py-3 outline-none transition focus:border-funeral-500 focus:ring-2 focus:ring-funeral-200" />
          </div>
        </div>
      </div>

      <div class="f-divider"></div>

      <div>
        <p class="f-eyebrow mb-3">Security</p>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-1 block text-sm font-medium text-stone-700">Password</label>
            <div class="flex gap-2">
              <input id="password" name="password" type="password" required class="min-w-0 flex-1 rounded-xl border border-stone-300 bg-stone-50 px-4 py-3 outline-none transition focus:border-funeral-500 focus:ring-2 focus:ring-funeral-200" />
              <button type="button" class="password-toggle rounded-xl border border-stone-300 bg-white px-3 text-sm font-semibold text-funeral-700" data-target="password" aria-label="Show password">Show</button>
            </div>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-stone-700">Confirm password</label>
            <div class="flex gap-2">
              <input id="confirm_password" name="confirm_password" type="password" required class="min-w-0 flex-1 rounded-xl border border-stone-300 bg-stone-50 px-4 py-3 outline-none transition focus:border-funeral-500 focus:ring-2 focus:ring-funeral-200" />
              <button type="button" class="password-toggle rounded-xl border border-stone-300 bg-white px-3 text-sm font-semibold text-funeral-700" data-target="confirm_password" aria-label="Show confirm password">Show</button>
            </div>
          </div>
        </div>
      </div>

      <div class="f-divider"></div>

      <div>
        <p class="f-eyebrow mb-3">Verification</p>
        <div id="valid-id-field">
          <label class="mb-1 block text-sm font-medium text-stone-700">Upload valid ID</label>
          <p class="mb-2 text-xs text-stone-500">Used only to confirm your identity before approval.</p>
          <input name="valid_id" type="file" accept="image/*,application/pdf" class="w-full rounded-xl border border-dashed border-stone-300 bg-stone-50 px-3 py-3 text-sm" />
        </div>
        <div id="license-field">
          <label class="mb-1 block text-sm font-medium text-stone-700">Upload funeral home license</label>
          <p class="mb-2 text-xs text-stone-500">Reviewed by an administrator before your listing goes live.</p>
          <input name="license" type="file" accept="image/*,application/pdf" class="w-full rounded-xl border border-dashed border-stone-300 bg-stone-50 px-3 py-3 text-sm" />
        </div>
      </div>

      <button type="submit" class="w-full rounded-full bg-funeral-500 px-5 py-3 font-semibold text-white shadow-lg shadow-funeral-500/20 transition hover:bg-funeral-400">Submit registration</button>
    </form>

    <p class="mt-6 text-center text-sm text-stone-600">Already have an account? <a href="<?= BASE_PATH ?>/login.php" class="font-semibold text-funeral-700 hover:text-funeral-600">Log in</a></p>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
document.querySelectorAll('.password-toggle').forEach(button => {
  button.addEventListener('click', () => {
    const input = document.getElementById(button.dataset.target);
    const visible = input.type === 'text';
    input.type = visible ? 'password' : 'text';
    button.textContent = visible ? 'Show' : 'Hide';
    button.setAttribute('aria-label', `${visible ? 'Show' : 'Hide'} password`);
  });
});

(function () {
  const options = document.querySelectorAll('#role-toggle .role-option');
  const funeralNameField = document.getElementById('funeral-name-field');
  const validIdField = document.getElementById('valid-id-field');
  const licenseField = document.getElementById('license-field');

  function applyRole(role) {
    options.forEach(opt => {
      const isActive = opt.querySelector('input').value === role;
      opt.classList.toggle('border-funeral-500', isActive);
      opt.classList.toggle('bg-funeral-50', isActive);
      opt.classList.toggle('border-stone-200', !isActive);
      opt.classList.toggle('bg-white', !isActive);
    });
    const isDirector = role === 'director';
    funeralNameField.classList.toggle('hidden', !isDirector);
    validIdField.classList.toggle('hidden', isDirector);
    licenseField.classList.toggle('hidden', !isDirector);
  }

  options.forEach(opt => {
    opt.addEventListener('click', () => {
      const input = opt.querySelector('input');
      input.checked = true;
      applyRole(input.value);
    });
  });

  const checked = document.querySelector('#role-toggle input:checked');
  applyRole(checked ? checked.value : 'client');
})();
</script>
