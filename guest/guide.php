<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$slug = $_GET['slug'] ?? '';
$stmt = db()->prepare('SELECT * FROM guides WHERE slug = ?');
$stmt->execute([$slug]);
$guide = $stmt->fetch();

if (!$guide) {
    header('Location: ' . url('/guest/'));
    exit;
}

$pageTitle = $guide['title'];
$section   = 'guest';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <a href="<?= url('/guest/') ?>" class="inline-flex items-center gap-1 text-sm text-stone-500 hover:text-stone-700">
        ← Back to guides
    </a>
</div>

<div class="max-w-2xl">
    <div class="bg-white rounded-xl border border-stone-200 p-6 md:p-8">
        <div class="text-5xl mb-5"><?= e($guide['icon'] ?: '📄') ?></div>
        <h1 class="text-2xl font-bold text-stone-800 mb-6"><?= e($guide['title']) ?></h1>
        <div class="text-stone-700 leading-relaxed space-y-3">
            <?= nl2br(e($guide['content'])) ?>
        </div>
    </div>

    <!-- Other guides -->
    <?php
    $others = db()->prepare('SELECT title, slug, icon FROM guides WHERE slug != ? ORDER BY display_order, title LIMIT 6');
    $others->execute([$slug]);
    $others = $others->fetchAll();
    ?>
    <?php if ($others): ?>
    <div class="mt-8">
        <h2 class="text-sm font-semibold text-stone-500 uppercase tracking-wide mb-3">Other guides</h2>
        <div class="flex flex-wrap gap-2">
            <?php foreach ($others as $o): ?>
            <a href="<?= url('/guest/guide.php?slug=') . urlencode($o['slug']) ?>"
               class="inline-flex items-center gap-1.5 bg-white border border-stone-200 hover:border-green-400 rounded-full px-4 py-1.5 text-sm text-stone-700 hover:text-green-800 transition">
                <?= e($o['icon'] ?: '📄') ?> <?= e($o['title']) ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
