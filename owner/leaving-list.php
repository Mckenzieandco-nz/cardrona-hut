<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$db   = db();
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'complete') {
        $checked = $_POST['items'] ?? [];
        $notes   = trim($_POST['notes'] ?? '');
        $db->prepare('INSERT INTO leaving_completions (completed_by, items_json, notes) VALUES (?, ?, ?)')
           ->execute([$user['id'], json_encode($checked), $notes]);
        flash('success', 'Leaving checklist completed. Safe travels!');
        header('Location: ' . url('/owner/leaving-list.php'));
        exit;
    }

    if ($action === 'add_item') {
        $item = trim($_POST['item'] ?? '');
        if ($item) {
            $db->prepare(
                'INSERT INTO leaving_items (item, display_order)
                 SELECT ?, COALESCE(MAX(display_order), 0) + 1 FROM leaving_items'
            )->execute([$item]);
        }
        header('Location: ' . url('/owner/leaving-list.php'));
        exit;
    }

    if ($action === 'delete_item') {
        $db->prepare('DELETE FROM leaving_items WHERE id = ?')->execute([(int)$_POST['item_id']]);
        header('Location: ' . url('/owner/leaving-list.php'));
        exit;
    }
}

$items   = $db->query('SELECT * FROM leaving_items ORDER BY display_order, id')->fetchAll();
$history = $db->query(
    'SELECT lc.*, u.name FROM leaving_completions lc
     JOIN users u ON lc.completed_by = u.id
     ORDER BY lc.completed_at DESC LIMIT 15'
)->fetchAll();

$success = flash('success');

$pageTitle   = 'Leaving List';
$section     = 'owner';
$currentPage = 'leaving';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($success): ?>
<div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 mb-5 text-sm font-medium">
    ✓ <?= e($success) ?>
</div>
<?php endif; ?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-stone-800">Leaving Checklist</h1>
    <p class="text-stone-500 text-sm mt-0.5">Complete before leaving the hut</p>
</div>

<div class="grid md:grid-cols-3 gap-6">

    <!-- ── Checklist form ── -->
    <div class="md:col-span-2">
        <form method="post" class="bg-white rounded-xl border border-stone-200 p-6">
            <input type="hidden" name="action" value="complete">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

            <?php if (empty($items)): ?>
            <p class="text-stone-400 text-sm text-center py-6">
                No checklist items yet.
                <?php if (isAdmin()): ?>Add some below.<?php endif; ?>
            </p>
            <?php else: ?>
            <div class="space-y-3 mb-6">
                <?php foreach ($items as $item): ?>
                <label class="flex items-center gap-3 cursor-pointer group select-none">
                    <input type="checkbox" name="items[]" value="<?= e($item['item']) ?>"
                           class="w-5 h-5 rounded border-stone-300 text-green-700 focus:ring-green-500 cursor-pointer flex-shrink-0">
                    <span class="text-stone-700 group-hover:text-stone-900"><?= e($item['item']) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="mb-5">
                <label class="block text-sm font-medium text-stone-600 mb-1.5">Notes (optional)</label>
                <textarea name="notes" rows="2"
                          placeholder="Any issues, maintenance needed, low supplies..."
                          class="w-full px-3 py-2 border border-stone-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500 resize-none"></textarea>
            </div>

            <button type="submit"
                    class="w-full bg-green-800 hover:bg-green-700 text-white font-semibold py-3 rounded-lg transition text-sm">
                ✓ Mark as Complete &amp; Submit
            </button>
        </form>

        <?php if (isAdmin()): ?>
        <!-- ── Manage items (admin) ── -->
        <div class="bg-stone-50 rounded-xl border border-stone-200 p-5 mt-4">
            <h3 class="text-sm font-semibold text-stone-600 mb-3">Manage checklist items</h3>

            <?php if ($items): ?>
            <ul class="space-y-1 mb-4">
                <?php foreach ($items as $item): ?>
                <li class="flex items-center gap-2 group">
                    <span class="flex-1 text-sm text-stone-700"><?= e($item['item']) ?></span>
                    <form method="post">
                        <input type="hidden" name="action" value="delete_item">
                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <button type="submit"
                                class="text-stone-300 hover:text-red-500 text-xs transition opacity-0 group-hover:opacity-100"
                                onclick="return confirm('Remove this item?')">Remove</button>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <form method="post" class="flex gap-2">
                <input type="hidden" name="action" value="add_item">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="text" name="item" required placeholder="e.g. Lock all windows"
                       class="flex-1 px-3 py-2 border border-stone-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                <button type="submit"
                        class="bg-stone-700 hover:bg-stone-600 text-white px-4 py-2 rounded-lg text-sm transition">
                    Add
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── History ── -->
    <div>
        <h2 class="font-semibold text-stone-700 mb-3">Completion history</h2>
        <?php if (empty($history)): ?>
        <p class="text-stone-400 text-sm">No completions recorded yet.</p>
        <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($history as $h): ?>
            <div class="bg-white rounded-lg border border-stone-200 p-3.5">
                <div class="flex items-start justify-between gap-2">
                    <span class="text-sm font-medium text-stone-700"><?= e($h['name']) ?></span>
                    <span class="text-xs text-stone-400 whitespace-nowrap">
                        <?= date('d M Y', strtotime($h['completed_at'])) ?>
                    </span>
                </div>
                <?php if ($h['notes']): ?>
                <p class="text-xs text-stone-500 mt-1.5 leading-relaxed"><?= e($h['notes']) ?></p>
                <?php endif; ?>
                <?php
                $done = json_decode($h['items_json'] ?? '[]', true);
                if ($done): ?>
                <p class="text-xs text-stone-400 mt-1"><?= count($done) ?> items checked</p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
