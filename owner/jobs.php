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
    $id     = (int)($_POST['job_id'] ?? 0);

    if ($action === 'add') {
        $title      = trim($_POST['title'] ?? '');
        $desc       = trim($_POST['description'] ?? '');
        $priority   = in_array($_POST['priority'], ['low','medium','high']) ? $_POST['priority'] : 'medium';
        $assignedTo = $_POST['assigned_to'] !== '' ? (int)$_POST['assigned_to'] : null;
        if ($title) {
            $db->prepare(
                'INSERT INTO jobs (title, description, priority, assigned_to, created_by) VALUES (?, ?, ?, ?, ?)'
            )->execute([$title, $desc, $priority, $assignedTo, $user['id']]);
            flash('success', 'Job added.');
        }
        header('Location: ' . url('/owner/jobs.php'));
        exit;
    }

    if ($action === 'complete' && $id) {
        $db->prepare("UPDATE jobs SET status='done', completed_at=NOW() WHERE id=?")->execute([$id]);
        header('Location: ' . url('/owner/jobs.php'));
        exit;
    }

    if ($action === 'reopen' && $id) {
        $db->prepare("UPDATE jobs SET status='open', completed_at=NULL WHERE id=?")->execute([$id]);
        header('Location: ' . url('/owner/jobs.php?tab=done'));
        exit;
    }

    if ($action === 'delete' && $id) {
        $db->prepare('DELETE FROM jobs WHERE id=?')->execute([$id]);
        flash('success', 'Job deleted.');
        header('Location: ' . url('/owner/jobs.php'));
        exit;
    }
}

$tab = $_GET['tab'] ?? 'open';

$openJobs = $db->query(
    "SELECT j.*, u.name AS assigned_name, c.name AS creator_name
     FROM jobs j
     LEFT JOIN users u ON j.assigned_to = u.id
     LEFT JOIN users c ON j.created_by  = c.id
     WHERE j.status != 'done'
     ORDER BY FIELD(j.priority,'high','medium','low'), j.created_at DESC"
)->fetchAll();

$doneJobs = $db->query(
    "SELECT j.*, u.name AS assigned_name
     FROM jobs j
     LEFT JOIN users u ON j.assigned_to = u.id
     WHERE j.status = 'done'
     ORDER BY j.completed_at DESC
     LIMIT 30"
)->fetchAll();

$owners = $db->query('SELECT id, name FROM users ORDER BY name')->fetchAll();

$success = flash('success');
$showAdd = isset($_GET['add']);

$pageTitle   = 'Jobs';
$section     = 'owner';
$currentPage = 'jobs';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($success): ?>
<div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 mb-5 text-sm">✓ <?= e($success) ?></div>
<?php endif; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-stone-800">Jobs</h1>
        <p class="text-stone-500 text-sm mt-0.5">Maintenance and to-do list for the hut</p>
    </div>
    <button onclick="document.getElementById('addForm').classList.toggle('hidden')"
            class="bg-green-800 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
        + Add Job
    </button>
</div>

<!-- ── Add job form ── -->
<div id="addForm" class="<?= $showAdd ? '' : 'hidden' ?> bg-white rounded-xl border border-stone-200 p-6 mb-6">
    <h2 class="font-semibold text-stone-700 mb-4">New Job</h2>
    <form method="post" class="grid sm:grid-cols-2 gap-4">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-stone-600 mb-1">Title *</label>
            <input type="text" name="title" required placeholder="e.g. Fix deck board"
                   class="w-full px-3 py-2 border border-stone-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
        </div>
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-stone-600 mb-1">Description</label>
            <textarea name="description" rows="2" placeholder="More detail..."
                      class="w-full px-3 py-2 border border-stone-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500 resize-none"></textarea>
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-600 mb-1">Priority</label>
            <select name="priority"
                    class="w-full px-3 py-2 border border-stone-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                <option value="low">Low</option>
                <option value="medium" selected>Medium</option>
                <option value="high">High</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-600 mb-1">Assign to</label>
            <select name="assigned_to"
                    class="w-full px-3 py-2 border border-stone-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                <option value="">Unassigned</option>
                <?php foreach ($owners as $o): ?>
                <option value="<?= $o['id'] ?>"><?= e($o['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="sm:col-span-2 flex gap-2">
            <button type="submit"
                    class="bg-green-800 hover:bg-green-700 text-white font-semibold px-6 py-2.5 rounded-lg transition text-sm">
                Add Job
            </button>
            <button type="button" onclick="document.getElementById('addForm').classList.add('hidden')"
                    class="text-stone-500 hover:text-stone-700 px-4 py-2 rounded-lg text-sm">
                Cancel
            </button>
        </div>
    </form>
</div>

<!-- ── Tabs ── -->
<div class="flex gap-1 border-b border-stone-200 mb-5">
    <a href="<?= url('/owner/jobs.php') ?>"
       class="px-4 py-2 text-sm font-medium border-b-2 transition
              <?= $tab !== 'done' ? 'border-green-700 text-green-800' : 'border-transparent text-stone-500 hover:text-stone-700' ?>">
        Open <span class="ml-1 bg-stone-200 text-stone-600 rounded-full px-1.5 text-xs"><?= count($openJobs) ?></span>
    </a>
    <a href="<?= url('/owner/jobs.php?tab=done') ?>"
       class="px-4 py-2 text-sm font-medium border-b-2 transition
              <?= $tab === 'done' ? 'border-green-700 text-green-800' : 'border-transparent text-stone-500 hover:text-stone-700' ?>">
        Completed
    </a>
</div>

<?php if ($tab !== 'done'): ?>
<!-- ── Open jobs ── -->
<?php if (empty($openJobs)): ?>
<div class="bg-white rounded-xl border border-stone-200 p-10 text-center text-stone-400">
    <div class="text-3xl mb-2">✅</div>
    <p class="text-sm">All done — no open jobs!</p>
</div>
<?php else: ?>
<div class="space-y-3">
    <?php foreach ($openJobs as $job): ?>
    <div class="bg-white rounded-xl border border-stone-200 p-4">
        <div class="flex items-start gap-3">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-medium text-stone-800"><?= e($job['title']) ?></span>
                    <?php echo priorityBadge($job['priority']); ?>
                    <?php if ($job['assigned_name']): ?>
                    <span class="text-xs text-stone-400">→ <?= e($job['assigned_name']) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($job['description']): ?>
                <p class="text-sm text-stone-500 mt-1"><?= e($job['description']) ?></p>
                <?php endif; ?>
                <p class="text-xs text-stone-400 mt-1">Added <?= formatDate($job['created_at']) ?> by <?= e($job['creator_name']) ?></p>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <form method="post" class="inline">
                    <input type="hidden" name="action" value="complete">
                    <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <button type="submit"
                            class="bg-green-100 hover:bg-green-200 text-green-800 text-xs font-medium px-3 py-1.5 rounded-lg transition">
                        ✓ Done
                    </button>
                </form>
                <form method="post" class="inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <button type="submit" class="text-stone-400 hover:text-red-500 text-xs transition"
                            onclick="return confirm('Delete this job?')">Delete</button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ── Completed jobs ── -->
<?php if (empty($doneJobs)): ?>
<div class="bg-white rounded-xl border border-stone-200 p-10 text-center text-stone-400">
    <p class="text-sm">No completed jobs yet.</p>
</div>
<?php else: ?>
<div class="space-y-2">
    <?php foreach ($doneJobs as $job): ?>
    <div class="bg-white rounded-xl border border-stone-200 p-4 flex items-center gap-3 opacity-75">
        <span class="text-green-600 flex-shrink-0">✓</span>
        <div class="flex-1 min-w-0">
            <span class="text-sm text-stone-700 line-through"><?= e($job['title']) ?></span>
            <?php if ($job['completed_at']): ?>
            <span class="text-xs text-stone-400 ml-2"><?= formatDate($job['completed_at']) ?></span>
            <?php endif; ?>
        </div>
        <form method="post" class="inline">
            <input type="hidden" name="action" value="reopen">
            <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <button type="submit" class="text-xs text-stone-400 hover:text-stone-600 transition">Reopen</button>
        </form>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
