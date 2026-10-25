<?php 
require_once __DIR__ . '/db/connect.php';
require_once __DIR__ . '/functions/appointment_function.php';
require_once __DIR__ . '/functions/catalog_functions.php';

$title  = 'Services';
$active = 'services';
include __DIR__ . '/includes/header.php';

$services = getServices($connect2db);
?>

<small class="mu">Your space / Services</small>
<h1>Find your fresh.</h1>
<p class="mu">From a quick shape-up to the full refresh. Choose what feels like you.</p>

<div class="between wrapx chips">
    <div class="row wrapx">
        <?php 
        $categories = [
            'all' => 'All services',
            'haircut' => 'Haircuts',
            'beard' => 'Beard & shave',
            'package' => 'Packages'
        ];
        foreach($categories as $k => $v): 
        ?>
            <button class="chip<?= $k == 'all' ? ' on' : '' ?>" data-f="<?= $k ?>">
                <?= $v ?>
            </button>
        <?php endforeach; ?>
    </div>
    <small class="mu" id="count"><?= count($services) ?> services · Something for everyone</small>
</div>

<div class="g3 svc-grid">
    <?php foreach($services as $s): ?>
        <div class="card svc" data-c="<?= $s['category'] ?>">
            <?php if (serviceImagePath($s) !== ''): ?>
                <img class="svc-img" src="<?= htmlspecialchars(serviceImagePath($s)) ?>"
                     alt="<?= htmlspecialchars($s['name']) ?> reference photo" loading="lazy">
            <?php endif; ?>
            <div class="between">
                <?= $s['popular'] ? '<span class="pill">Popular</span>' : '' ?>
            </div>
            
            <h3><?= htmlspecialchars($s['name']) ?></h3>
            <p class="mu"><?= htmlspecialchars($s['description']) ?></p>
            
            <div class="between">
                <h2><?= money($s['price']) ?></h2>
                <small class="mu"><?= ic('clock', 's') ?> <?= $s['duration'] ?> min</small>
            </div>
            
            <a href="book.php?s=<?= $s['id'] ?>" class="btn t">Book this service <?= ic('arrow') ?></a>
        </div>
    <?php endforeach; ?>
</div>

<?php include 'includes/footer.php'; ?>