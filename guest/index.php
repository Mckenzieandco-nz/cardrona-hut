<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$guides = db()->query('SELECT id, title, slug, icon, excerpt FROM guides ORDER BY display_order, title')->fetchAll();

$pageTitle = 'Guest Guides';
$section   = 'guest';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-2xl font-bold text-stone-800">Welcome to 9 Little Meg</h1>
    <p class="text-stone-500 mt-1">Everything you need for a great stay.</p>
</div>

<?php if (empty($guides)): ?>
<div class="bg-white rounded-xl border border-stone-200 p-12 text-center text-stone-400">
    <div class="text-4xl mb-3">🏔</div>
    <p>Guides coming soon.</p>
</div>
<?php else: ?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($guides as $g): ?>
    <a href="<?= url('/guest/guide.php?slug=') . urlencode($g['slug']) ?>"
       class="bg-white rounded-xl border border-stone-200 hover:border-green-400 hover:shadow-md transition-all duration-150 p-6 group">
        <div class="text-3xl mb-3"><?= e($g['icon'] ?: '📄') ?></div>
        <h2 class="font-semibold text-stone-800 group-hover:text-green-800 mb-1"><?= e($g['title']) ?></h2>
        <?php if ($g['excerpt']): ?>
        <p class="text-sm text-stone-500 line-clamp-2"><?= e($g['excerpt']) ?></p>
        <?php endif; ?>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
