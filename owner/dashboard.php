<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$db = db();

$openJobs   = (int) $db->query("SELECT COUNT(*) FROM jobs WHERE status != 'done'")->fetchColumn();
$highJobs   = (int) $db->query("SELECT COUNT(*) FROM jobs WHERE status != 'done' AND priority = 'high'")->fetchColumn();
$billsCount = (int) $db->query("SELECT COUNT(*) FROM bills")->fetchColumn();
$billsYear  = (float) $db->query("SELECT COALESCE(SUM(amount),0) FROM bills WHERE YEAR(COALESCE(bill_date, created_at)) = YEAR(NOW())")->fetchColumn();

$openJobsList = $db->query(
    "SELECT j.*, u.name AS assigned_name
     FROM jobs j
     LEFT JOIN users u ON j.assigned_to = u.id
     WHERE j.status != 'done'
     ORDER BY FIELD(j.priority,'high','medium','low'), j.created_at DESC
     LIMIT 8"
)->fetchAll();

$lastLeaving = $db->query(
    "SELECT lc.*, u.name FROM leaving_completions lc
     JOIN users u ON lc.completed_by = u.id
     ORDER BY lc.completed_at DESC LIMIT 1"
)->fetch();

$recentBills = $db->query(
    "SELECT b.*, u.name AS uploader FROM bills b
     JOIN users u ON b.uploaded_by = u.id
     ORDER BY b.created_at DESC LIMIT 4"
)->fetchAll();

$pageTitle   = 'Dashboard';
$section     = 'owner';
$currentPage = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-stone-800">Dashboard</h1>
    <p class="text-stone-500 text-sm mt-0.5">Welcome back, <?= e($_SESSION['user_name'] ?? '') ?></p>
</div>

<!-- Stats row -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <a href="<?= url('/owner/jobs.php') ?>"
       class="bg-white rounded-xl border border-stone-200 hover:border-green-400 transition p-5">
        <div class="text-3xl font-bold text-green-800"><?= $openJobs ?></div>
        <div class="text-sm text-stone-500 mt-1">Open jobs</div>
        <?php if ($highJobs): ?>
        <div class="text-xs text-red-500 mt-1"><?= $highJobs ?> high priority</div>
        <?php endif; ?>
    </a>
    <a href="<?= url('/owner/bills.php') ?>"
       class="bg-white rounded-xl border border-stone-200 hover:border-green-400 transition p-5">
        <div class="text-3xl font-bold text-green-800"><?= $billsCount ?></div>
        <div class="text-sm text-stone-500 mt-1">Bills stored</div>
        <?php if ($billsYear > 0): ?>
        <div class="text-xs text-stone-400 mt-1"><?= formatCurrency($billsYear) ?> this year</div>
        <?php endif; ?>
    </a>
    <a href="<?= url('/owner/leaving-list.php') ?>"
       class="bg-white rounded-xl border border-stone-200 hover:border-green-400 transition p-5">
        <div class="text-3xl font-bold text-green-800">
            <?= $lastLeaving ? date('d M', strtotime($lastLeaving['completed_at'])) : '—' ?>
        </div>
        <div class="text-sm text-stone-500 mt-1">Last checkout</div>
        <?php if ($lastLeaving): ?>
        <div class="text-xs text-stone-400 mt-1">by <?= e($lastLeaving['name']) ?></div>
        <?php endif; ?>
    </a>
    <a href="<?= url('/guest/') ?>" target="_blank"
       class="bg-white rounded-xl border border-stone-200 hover:border-green-400 transition p-5">
        <div class="text-3xl font-bold text-stone-400">↗</div>
        <div class="text-sm text-stone-500 mt-1">Guest area</div>
        <div class="text-xs text-stone-400 mt-1">Open in new tab</div>
    </a>
</div>

<div class="grid lg:grid-cols-3 gap-6">
    <!-- Open jobs -->
    <div class="lg:col-span-2 bg-white rounded-xl border border-stone-200">
        <div class="flex items-center justify-between px-5 py-4 border-b border-stone-100">
            <h2 class="font-semibold text-stone-800">Open Jobs</h2>
            <a href="<?= url('/owner/jobs.php') ?>" class="text-sm text-green-700 hover:text-green-900">All jobs →</a>
        </div>
        <?php if (empty($openJobsList)): ?>
        <p class="px-5 py-8 text-sm text-stone-400 text-center">No open jobs — all clear ✓</p>
        <?php else: ?>
        <ul class="divide-y divide-stone-100">
            <?php foreach ($openJobsList as $job): ?>
            <li class="px-5 py-3 flex items-center gap-3">
                <?php
                $dot = ['high' => 'bg-red-400', 'medium' => 'bg-amber-400', 'low' => 'bg-stone-300'];
                ?>
                <span class="w-2 h-2 rounded-full flex-shrink-0 <?= $dot[$job['priority']] ?? 'bg-stone-300' ?>"></span>
                <span class="flex-1 text-sm text-stone-700"><?= e($job['title']) ?></span>
                <?php if ($job['assigned_name']): ?>
                <span class="text-xs text-stone-400 hidden sm:block"><?= e($job['assigned_name']) ?></span>
                <?php endif; ?>
                <?php echo priorityBadge($job['priority']); ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>

    <!-- Recent bills + quick links -->
    <div class="space-y-4">
        <div class="bg-white rounded-xl border border-stone-200">
            <div class="flex items-center justify-between px-5 py-4 border-b border-stone-100">
                <h2 class="font-semibold text-stone-800">Recent Bills</h2>
                <a href="<?= url('/owner/bills.php') ?>" class="text-sm text-green-700 hover:text-green-900">All →</a>
            </div>
            <?php if (empty($recentBills)): ?>
            <p class="px-5 py-6 text-xs text-stone-400 text-center">No bills yet</p>
            <?php else: ?>
            <ul class="divide-y divide-stone-100">
                <?php foreach ($recentBills as $b): ?>
                <li class="px-5 py-3">
                    <div class="text-sm font-medium text-stone-700 truncate"><?= e($b['description']) ?></div>
                    <div class="flex items-center justify-between mt-0.5">
                        <span class="text-xs text-stone-400"><?= formatDate($b['bill_date'] ?? $b['created_at']) ?></span>
                        <?php if ($b['amount']): ?>
                        <span class="text-xs font-medium text-stone-600"><?= formatCurrency((float)$b['amount']) ?></span>
                        <?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <!-- Quick actions -->
        <div class="grid grid-cols-2 gap-3">
            <a href="<?= url('/owner/leaving-list.php') ?>"
               class="bg-green-50 hover:bg-green-100 border border-green-200 rounded-xl p-4 text-center transition">
                <div class="text-2xl mb-1">✅</div>
                <div class="text-xs font-medium text-green-800">Leaving List</div>
            </a>
            <a href="<?= url('/owner/jobs.php?add=1') ?>"
               class="bg-green-50 hover:bg-green-100 border border-green-200 rounded-xl p-4 text-center transition">
                <div class="text-2xl mb-1">➕</div>
                <div class="text-xs font-medium text-green-800">Add Job</div>
            </a>
            <a href="<?= url('/owner/bills.php') ?>"
               class="bg-green-50 hover:bg-green-100 border border-green-200 rounded-xl p-4 text-center transition">
                <div class="text-2xl mb-1">🧾</div>
                <div class="text-xs font-medium text-green-800">Upload Bill</div>
            </a>
            <a href="<?= url('/owner/guides.php') ?>"
               class="bg-green-50 hover:bg-green-100 border border-green-200 rounded-xl p-4 text-center transition">
                <div class="text-2xl mb-1">📖</div>
                <div class="text-xs font-medium text-green-800">Edit Guides</div>
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
