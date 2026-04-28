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

    if ($action === 'add' || $action === 'update') {
        $title   = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $icon    = trim($_POST['icon'] ?? '📄');
        $excerpt = trim($_POST['excerpt'] ?? '');

        if ($title && $content) {
            $slug = slugify($title);

            // Ensure slug is unique (append number if needed)
            $editId = (int)($_POST['guide_id'] ?? 0);
            $check  = $db->prepare('SELECT id FROM guides WHERE slug = ? AND id != ?');
            $check->execute([$slug, $editId]);
            if ($check->fetch()) {
                $slug .= '-' . time();
            }

            if ($action === 'add') {
                $db->prepare(
                    'INSERT INTO guides (title, slug, content, icon, excerpt, display_order)
                     SELECT ?, ?, ?, ?, ?, COALESCE(MAX(display_order), 0) + 1 FROM guides'
                )->execute([$title, $slug, $content, $icon, $excerpt]);
                flash('success', 'Guide "' . $title . '" added.');
            } else {
                $db->prepare(
                    'UPDATE guides SET title=?, slug=?, content=?, icon=?, excerpt=? WHERE id=?'
                )->execute([$title, $slug, $content, $icon, $excerpt, $editId]);
                flash('success', 'Guide updated.');
            }
        }
        header('Location: ' . url('/owner/guides.php'));
        exit;
    }

    if ($action === 'delete') {
        $id = (int)$_POST['guide_id'];
        $db->prepare('DELETE FROM guides WHERE id = ?')->execute([$id]);
        flash('success', 'Guide deleted.');
        header('Location: ' . url('/owner/guides.php'));
        exit;
    }

    if ($action === 'move') {
        $id        = (int)$_POST['guide_id'];
        $direction = $_POST['direction'] ?? '';

        $guides = $db->query('SELECT id, display_order FROM guides ORDER BY display_order, id')->fetchAll();
        $ids    = array_column($guides, 'id');
        $pos    = array_search($id, $ids);

        if ($direction === 'up' && $pos > 0) {
            $swapId = $ids[$pos - 1];
        } elseif ($direction === 'down' && $pos < count($ids) - 1) {
            $swapId = $ids[$pos + 1];
        } else {
            $swapId = null;
        }

        if ($swapId) {
            $orders = array_column($guides, 'display_order', 'id');
            $a = $orders[$id];
            $b = $orders[$swapId];
            // Swap — if equal, offset by 1
            if ($a === $b) { $b = $a + 1; }
            $db->prepare('UPDATE guides SET display_order=? WHERE id=?')->execute([$b, $id]);
            $db->prepare('UPDATE guides SET display_order=? WHERE id=?')->execute([$a, $swapId]);
        }
        header('Location: ' . url('/owner/guides.php'));
        exit;
    }
}

// Pre-load guide for editing
$editGuide = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare('SELECT * FROM guides WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editGuide = $stmt->fetch() ?: null;
}

$guides  = $db->query('SELECT * FROM guides ORDER BY display_order, title')->fetchAll();
$success = flash('success');

$pageTitle   = 'Guides';
$section     = 'owner';
$currentPage = 'guides';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($success): ?>
<div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 mb-5 text-sm">✓ <?= e($success) ?></div>
<?php endif; ?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-stone-800">Guest Guides</h1>
    <p class="text-stone-500 text-sm mt-0.5">
        Manage the guides shown in the
        <a href="<?= url('/guest/') ?>" target="_blank" class="text-green-700 hover:underline">guest area ↗</a>
    </p>
</div>

<div class="grid lg:grid-cols-5 gap-6">

    <!-- ── Add / Edit form ── -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl border border-stone-200 p-6 sticky top-4">
            <h2 class="font-semibold text-stone-700 mb-4">
                <?= $editGuide ? 'Edit guide' : 'Add a guide' ?>
            </h2>
            <form method="post" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="<?= $editGuide ? 'update' : 'add' ?>">
                <?php if ($editGuide): ?>
                <input type="hidden" name="guide_id" value="<?= $editGuide['id'] ?>">
                <?php endif; ?>

                <div class="flex gap-2">
                    <div class="w-20">
                        <label class="block text-sm font-medium text-stone-600 mb-1">Icon</label>
                        <input type="text" name="icon" maxlength="4"
                               value="<?= e($editGuide['icon'] ?? '📄') ?>"
                               class="w-full px-2 py-2 border border-stone-300 rounded-lg text-center text-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-stone-600 mb-1">Title *</label>
                        <input type="text" name="title" required
                               value="<?= e($editGuide['title'] ?? '') ?>"
                               placeholder="e.g. Hot Tub"
                               class="w-full px-3 py-2 border border-stone-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-stone-600 mb-1">Short description
                        <span class="font-normal text-stone-400">(shown on card)</span>
                    </label>
                    <input type="text" name="excerpt"
                           value="<?= e($editGuide['excerpt'] ?? '') ?>"
                           placeholder="One-line summary..."
                           class="w-full px-3 py-2 border border-stone-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-stone-600 mb-1">Content *</label>
                    <textarea name="content" required rows="10"
                              placeholder="Write the guide content here. Use blank lines to separate paragraphs."
                              class="w-full px-3 py-2 border border-stone-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500 resize-y font-mono"><?= e($editGuide['content'] ?? '') ?></textarea>
                    <p class="text-xs text-stone-400 mt-1">Plain text. Use blank lines for paragraphs.</p>
                </div>

                <div class="flex gap-2 pt-1">
                    <button type="submit"
                            class="flex-1 bg-green-800 hover:bg-green-700 text-white font-semibold py-2.5 rounded-lg transition text-sm">
                        <?= $editGuide ? 'Save Changes' : 'Add Guide' ?>
                    </button>
                    <?php if ($editGuide): ?>
                    <a href="<?= url('/owner/guides.php') ?>"
                       class="px-4 py-2.5 border border-stone-300 rounded-lg text-sm text-stone-600 hover:bg-stone-50 transition">
                        Cancel
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Guide list ── -->
    <div class="lg:col-span-3">
        <?php if (empty($guides)): ?>
        <div class="bg-white rounded-xl border border-stone-200 p-10 text-center text-stone-400">
            <div class="text-4xl mb-3">📖</div>
            <p class="text-sm">No guides yet — add your first one.</p>
        </div>
        <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($guides as $i => $g): ?>
            <div class="bg-white rounded-xl border <?= $editGuide && $editGuide['id'] == $g['id'] ? 'border-green-400 ring-1 ring-green-300' : 'border-stone-200' ?> p-4">
                <div class="flex items-start gap-3">
                    <div class="text-2xl flex-shrink-0 mt-0.5"><?= e($g['icon'] ?: '📄') ?></div>
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-stone-800"><?= e($g['title']) ?></div>
                        <?php if ($g['excerpt']): ?>
                        <p class="text-xs text-stone-400 mt-0.5 truncate"><?= e($g['excerpt']) ?></p>
                        <?php endif; ?>
                        <p class="text-xs text-stone-300 mt-1">
                            <?= mb_strlen($g['content']) ?> chars ·
                            <a href="<?= url('/guest/guide.php?slug=') . urlencode($g['slug']) ?>"
                               target="_blank" class="hover:text-green-600">Preview ↗</a>
                        </p>
                    </div>
                    <div class="flex items-center gap-1 flex-shrink-0">
                        <!-- Move up/down -->
                        <form method="post" class="inline">
                            <input type="hidden" name="action" value="move">
                            <input type="hidden" name="guide_id" value="<?= $g['id'] ?>">
                            <input type="hidden" name="direction" value="up">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <button type="submit"
                                    class="text-stone-300 hover:text-stone-600 px-1 py-1 text-xs disabled:opacity-20"
                                    <?= $i === 0 ? 'disabled' : '' ?> title="Move up">▲</button>
                        </form>
                        <form method="post" class="inline">
                            <input type="hidden" name="action" value="move">
                            <input type="hidden" name="guide_id" value="<?= $g['id'] ?>">
                            <input type="hidden" name="direction" value="down">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <button type="submit"
                                    class="text-stone-300 hover:text-stone-600 px-1 py-1 text-xs"
                                    <?= $i === count($guides) - 1 ? 'disabled' : '' ?> title="Move down">▼</button>
                        </form>
                        <a href="?edit=<?= $g['id'] ?>"
                           class="text-sm text-green-700 hover:text-green-900 px-2 py-1 font-medium">Edit</a>
                        <form method="post" class="inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="guide_id" value="<?= $g['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <button type="submit"
                                    class="text-sm text-stone-400 hover:text-red-500 px-2 py-1 transition"
                                    onclick="return confirm('Delete \'<?= addslashes($g['title']) ?>\'?')">
                                Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
