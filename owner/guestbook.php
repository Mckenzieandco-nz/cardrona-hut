<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if ($_POST['action'] === 'delete') {
        $db->prepare('DELETE FROM guestbook WHERE id = ?')->execute([(int)$_POST['entry_id']]);
        flash('success', 'Entry deleted.');
    }
    header('Location: ' . url('/owner/guestbook.php'));
    exit;
}

$entries = $db->query('SELECT * FROM guestbook ORDER BY created_at DESC')->fetchAll();
$success = flash('success');

$pageTitle   = 'Guest Book';
$section     = 'owner';
$currentPage = 'guestbook';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($success): ?>
<div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 mb-5 text-sm">✓ <?= e($success) ?></div>
<?php endif; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-stone-800">Guest Book</h1>
        <p class="text-stone-500 text-sm mt-0.5">
            <?= count($entries) ?> entr<?= count($entries) === 1 ? 'y' : 'ies' ?> ·
            <a href="<?= url('/guest/guestbook.php') ?>" target="_blank" class="text-green-700 hover:underline">View public page ↗</a>
        </p>
    </div>
</div>

<?php if (empty($entries)): ?>
<div class="bg-white rounded-xl border border-stone-200 p-12 text-center text-stone-400">
    <div class="text-4xl mb-3">📖</div>
    <p class="text-sm">No entries yet.</p>
</div>
<?php else: ?>
<div class="space-y-3 max-w-2xl">
    <?php foreach ($entries as $entry): ?>
    <div class="bg-white rounded-xl border border-stone-200 p-5">
        <div class="flex items-start justify-between gap-3">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-semibold text-stone-800"><?= e($entry['guest_name']) ?></span>
                    <?php if ($entry['rating']): ?>
                    <span><?= str_repeat('⭐', (int)$entry['rating']) ?></span>
                    <?php endif; ?>
                    <?php if ($entry['stay_date']): ?>
                    <span class="text-xs text-stone-400">· <?= date('F Y', strtotime($entry['stay_date'])) ?></span>
                    <?php endif; ?>
                </div>
                <p class="text-sm text-stone-600 mt-2 leading-relaxed whitespace-pre-line"><?= e($entry['message']) ?></p>
                <p class="text-xs text-stone-300 mt-2"><?= date('d M Y', strtotime($entry['created_at'])) ?></p>
            </div>
            <form method="post" class="flex-shrink-0">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="entry_id" value="<?= $entry['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <button type="submit"
                        class="text-xs text-stone-400 hover:text-red-500 transition"
                        onclick="return confirm('Delete this entry?')">Delete</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
