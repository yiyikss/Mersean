<?php
require_once __DIR__ . '/db/connect.php';
require_once __DIR__ . '/functions/appointment_function.php';
require_once __DIR__ . '/functions/catalog_functions.php';
require_once __DIR__ . '/functions/admin_only_guard.php';   // admins only

// Simple CSRF token so other sites can't add or delete things for you
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

// Add / edit / delete buttons (handled before any HTML is printed)
$error  = '';
$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $error = 'Your session expired. Please try again.';
    } elseif ($action === 'add_service') {
        $error = addService($connect2db, $_POST, $_FILES['image'] ?? null);
        if ($error === '') {
            header('Location: admin_services.php?done=added');
            exit;
        }
    } elseif ($action === 'edit_service') {
        $error = updateService($connect2db, (int) ($_POST['service_id'] ?? 0), $_POST, $_FILES['image'] ?? null);
        if ($error === '') {
            header('Location: admin_services.php?done=updated');
            exit;
        }
    } elseif ($action === 'delete_service') {
        $error = deleteService($connect2db, (int) ($_POST['service_id'] ?? 0));
        if ($error === '') {
            header('Location: admin_services.php?done=deleted');
            exit;
        }
    }
}

$messages = ['added' => 'Service added. Customers can book it now.', 'updated' => 'Service updated.', 'deleted' => 'Service removed.'];
$done     = $messages[$_GET['done'] ?? ''] ?? '';

$services   = getServices($connect2db);
$categories = serviceCategories();

// Are we editing a service? (Edit button => ?edit=ID, or the edit form came back with an error)
$editId  = $action === 'edit_service' ? (int) ($_POST['service_id'] ?? 0) : (int) ($_GET['edit'] ?? 0);
$editing = null;
foreach ($services as $s) {
    if ((int) $s['id'] === $editId) {
        $editing = $s;
    }
}

// Values shown in the form: blank for "add", the saved values for "edit",
// or what the person just typed if there was an error.
$form = ['name' => '', 'category' => '', 'icon' => 'scissors', 'price' => '', 'duration' => '', 'description' => '', 'popular' => 0];
if ($editing) {
    foreach ($form as $key => $unused) {
        $form[$key] = $editing[$key] ?? $form[$key];
    }
}
if ($error !== '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['name', 'category', 'icon', 'price', 'duration', 'description'] as $key) {
        $form[$key] = $_POST[$key] ?? '';
    }
    $form['popular'] = isset($_POST['popular']) ? 1 : 0;
}

$title  = 'Services';
$active = 'services';
include __DIR__ . '/includes/admin_header.php';
?>

<small class="mu">Shop management / Services</small>

<div class="between head">
    <div>
        <h1>Our services.</h1>
        <p class="mu">Add the haircuts and treatments customers can book, or remove the ones you no longer offer.</p>
    </div>
</div>

<?php if ($done !== ''): ?>
    <p class="msg success"><?= htmlspecialchars($done) ?></p>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <p class="msg error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<div class="card mgr">
    <h3>Current services (<?= count($services) ?>)</h3>

    <div class="scroll">
        <table>
            <tr>
                <th>SERVICE</th>
                <th>CATEGORY</th>
                <th>PRICE</th>
                <th>TIME</th>
                <th>ACTION</th>
            </tr>
            <?php foreach ($services as $s): ?>
                <tr>
                    <td>
                        <div class="row">
                            <?php if (serviceImagePath($s) !== ''): ?>
                                <img class="thumb" src="<?= htmlspecialchars(serviceImagePath($s)) ?>" alt="">
                            <?php endif; ?>
                            <div>
                                <b><?= htmlspecialchars($s['name']) ?></b>
                                <?= $s['popular'] ? '<span class="pill">Popular</span>' : '' ?><br>
                                <small class="mu"><?= htmlspecialchars($s['description'] ?? '') ?></small>
                            </div>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($categories[$s['category']] ?? $s['category']) ?></td>
                    <td><?= money($s['price']) ?></td>
                    <td><?= (int) $s['duration'] ?> min</td>
                    <td>
                        <div class="acts">
                            <a href="admin_services.php?edit=<?= (int) $s['id'] ?>#form" class="btn">Edit</a>
                        <form method="post" action="admin_services.php"
                              onsubmit="return confirm('Remove this service? Customers will no longer be able to book it.');">
                            <input type="hidden" name="action" value="delete_service">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
                            <input type="hidden" name="service_id" value="<?= (int) $s['id'] ?>">
                            <button class="btn danger">Delete</button>
                        </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>

<div class="card mgr" id="form">
    <h3><?= $editing ? 'Edit service' : 'Add a new service' ?></h3>

    <form method="post" action="admin_services.php#form" enctype="multipart/form-data">
        <input type="hidden" name="action" value="<?= $editing ? 'edit_service' : 'add_service' ?>">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
        <?php if ($editing): ?>
            <input type="hidden" name="service_id" value="<?= (int) $editing['id'] ?>">
        <?php endif; ?>

        <div class="fgrid">
            <label class="wide">
                Service name
                <input name="name" maxlength="100" placeholder="e.g. Haircut" value="<?= htmlspecialchars($form['name']) ?>" required>
            </label>

            <label>
                Category
                <select name="category" required>
                    <?php foreach ($categories as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $form['category'] === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Price (₱)
                <input type="number" name="price" min="1" max="99999" step="0.01" placeholder="250" value="<?= htmlspecialchars((string) $form['price']) ?>" required>
            </label>

            <label>
                Duration (minutes)
                <input type="number" name="duration" min="5" max="480" step="5" placeholder="30" value="<?= htmlspecialchars((string) $form['duration']) ?>" required>
            </label>

            <label class="wide">
                Description
                <textarea name="description" rows="3" maxlength="500" placeholder="A short description customers will read"><?= htmlspecialchars((string) $form['description']) ?></textarea>
            </label>

            <label class="wide">
                Reference photo <small class="mu">(optional · JPG, PNG or WebP, up to 3 MB)</small>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
            </label>

            <?php if ($editing && serviceImagePath($editing) !== ''): ?>
                <div class="wide cur-img">
                    <img src="<?= htmlspecialchars(serviceImagePath($editing)) ?>" alt="Current photo">
                    <label class="chk">
                        <input type="checkbox" name="remove_image"> Remove current photo
                    </label>
                </div>
            <?php endif; ?>

            <label class="chk wide">
                <input type="checkbox" name="popular" <?= $form['popular'] ? 'checked' : '' ?>> Mark as popular
            </label>
        </div>

        <div class="row">
            <button class="btn p"><?= $editing ? 'Save changes' : 'Add service ' . ic('plus') ?></button>
            <?php if ($editing): ?>
                <a href="admin_services.php" class="btn">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>