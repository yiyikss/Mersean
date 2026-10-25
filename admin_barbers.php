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
    } elseif ($action === 'add_barber') {
        $error = addBarber($connect2db, $_POST, $_FILES['photo'] ?? null);
        if ($error === '') {
            header('Location: admin_barbers.php?done=added');
            exit;
        }
    } elseif ($action === 'edit_barber') {
        $error = updateBarber($connect2db, (int) ($_POST['barber_id'] ?? 0), $_POST, $_FILES['photo'] ?? null);
        if ($error === '') {
            header('Location: admin_barbers.php?done=updated');
            exit;
        }
    } elseif ($action === 'delete_barber') {
        $error = deleteBarber($connect2db, (int) ($_POST['barber_id'] ?? 0));
        if ($error === '') {
            header('Location: admin_barbers.php?done=deleted');
            exit;
        }
    }
}

$messages = ['added' => 'Barber added. Customers can book them now.', 'updated' => 'Barber updated.', 'deleted' => 'Barber removed.'];
$done     = $messages[$_GET['done'] ?? ''] ?? '';

$barbers = getBarbers($connect2db);

// Are we editing a barber? (Edit button => ?edit=ID, or the edit form came back with an error)
$editId  = $action === 'edit_barber' ? (int) ($_POST['barber_id'] ?? 0) : (int) ($_GET['edit'] ?? 0);
$editing = null;
foreach ($barbers as $b) {
    if ((int) $b['id'] === $editId) {
        $editing = $b;
    }
}

// Values shown in the form: blank for "add", saved values for "edit", or what was just typed on error
$form = ['name' => $editing['name'] ?? '', 'specialty' => $editing['specialty'] ?? ''];
if ($error !== '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['name']      = $_POST['name'] ?? '';
    $form['specialty'] = $_POST['specialty'] ?? '';
}

$title  = 'Barbers';
$active = 'barbers';
include __DIR__ . '/includes/admin_header.php';
?>

<small class="mu">Shop management / Barbers</small>

<div class="between head">
    <div>
        <h1>Our barbers.</h1>
        <p class="mu">Add the people customers can choose when they book, or remove a barber who has left.</p>
    </div>
</div>

<?php if ($done !== ''): ?>
    <p class="msg success"><?= htmlspecialchars($done) ?></p>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <p class="msg error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<div class="card mgr">
    <h3>Current barbers (<?= count($barbers) ?>)</h3>

    <div class="scroll">
        <table>
            <tr>
                <th>BARBER</th>
                <th>SPECIALTY</th>
                <th>ACTION</th>
            </tr>
            <?php foreach ($barbers as $b): ?>
                <tr>
                    <td>
                        <div class="row">
                            <?= av($b['photo'], 'sm') ?>
                            <b><?= htmlspecialchars($b['name']) ?></b>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($b['specialty'] ?? '') ?></td>
                    <td>
                        <div class="acts">
                            <a href="admin_barbers.php?edit=<?= (int) $b['id'] ?>#form" class="btn">Edit</a>
                        <form method="post" action="admin_barbers.php"
                              onsubmit="return confirm('Remove this barber? Customers will no longer be able to book them.');">
                            <input type="hidden" name="action" value="delete_barber">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
                            <input type="hidden" name="barber_id" value="<?= (int) $b['id'] ?>">
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
    <h3><?= $editing ? 'Edit barber' : 'Add a new barber' ?></h3>

    <form method="post" action="admin_barbers.php#form" enctype="multipart/form-data">
        <input type="hidden" name="action" value="<?= $editing ? 'edit_barber' : 'add_barber' ?>">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
        <?php if ($editing): ?>
            <input type="hidden" name="barber_id" value="<?= (int) $editing['id'] ?>">
        <?php endif; ?>

        <div class="fgrid">
            <label>
                Name
                <input name="name" maxlength="100" placeholder="e.g. Juan Dela Cruz" value="<?= htmlspecialchars($form['name']) ?>" required>
            </label>

            <label>
                Specialty
                <input name="specialty" maxlength="150" placeholder="e.g. Fades &amp; beard styling" value="<?= htmlspecialchars($form['specialty']) ?>">
            </label>
            
        </div>

        <div class="row">
            <button class="btn p"><?= $editing ? 'Save changes' : 'Add barber ' . ic('plus') ?></button>
            <?php if ($editing): ?>
                <a href="admin_barbers.php" class="btn">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>