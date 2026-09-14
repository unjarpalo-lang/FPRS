<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_any_role(['admin']);
$pdo = pdo_connect();
$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $location = $_POST['location'] ?? '';
    $contact = $_POST['contact_info'] ?? '';
    $desc = $_POST['description'] ?? '';
    if ($_POST['id'] ?? false) {
        $pdo->prepare('UPDATE shops SET name=?,location=?,contact_info=?,description=? WHERE id=?')->execute([$name,$location,$contact,$desc,$_POST['id']]);
    } else {
        $pdo->prepare('INSERT INTO shops (admin_id,name,location,contact_info,description) VALUES (?,?,?,?,?)')->execute([$_SESSION['user_id'],$name,$location,$contact,$desc]);
    }
  header('Location: ' . BASE_PATH . '/admin/shops.php'); exit;
}

if ($action === 'delete' && isset($_GET['id'])) {
  $pdo->prepare('DELETE FROM shops WHERE id = ?')->execute([$_GET['id']]); header('Location: ' . BASE_PATH . '/admin/shops.php'); exit;
}

$shop = null;
if ($action === 'edit' && isset($_GET['id'])) { $stmt = $pdo->prepare('SELECT * FROM shops WHERE id = ?'); $stmt->execute([$_GET['id']]); $shop = $stmt->fetch(); }

// include header for rendering
include __DIR__ . '/../includes/header.php';

?>
<div class="max-w-xl">
  <h2 class="text-2xl font-semibold"><?=($shop? 'Edit' : 'Add')?> Shop</h2>
  <form method="post" class="space-y-3 mt-3">
    <?php if($shop): ?><input type="hidden" name="id" value="<?=$shop['id']?>" /><?php endif; ?>
    <div><label>Name</label><input name="name" class="w-full border p-2 rounded" value="<?=htmlspecialchars($shop['name'] ?? '')?>" required /></div>
    <div><label>Location</label><input name="location" class="w-full border p-2 rounded" value="<?=htmlspecialchars($shop['location'] ?? '')?>" /></div>
    <div><label>Contact</label><input name="contact_info" class="w-full border p-2 rounded" value="<?=htmlspecialchars($shop['contact_info'] ?? '')?>" /></div>
    <div><label>Description</label><textarea name="description" class="w-full border p-2 rounded"><?=htmlspecialchars($shop['description'] ?? '')?></textarea></div>
    <div><button class="px-3 py-2 bg-indigo-600 text-white rounded">Save</button></div>
  </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
