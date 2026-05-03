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

    if ($action === 'upload') {
        $description = trim($_POST['description'] ?? '');
        $amount      = $_POST['amount'] !== '' ? (float)$_POST['amount'] : null;
        $billDate    = $_POST['bill_date'] ?: null;

        if ($description === '') {
            flash('error', 'Please enter a description.');
        } elseif (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'File upload failed. Check that the file is under ' . MAX_UPLOAD_MB . 'MB.');
        } else {
            $file    = $_FILES['file'];
            $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'heic', 'webp'];

            if (!in_array($ext, $allowed)) {
                flash('error', 'Only PDF, JPG, PNG, HEIC, and WEBP files are allowed.');
            } elseif ($file['size'] > MAX_UPLOAD_SIZE) {
                flash('error', 'File too large (max ' . MAX_UPLOAD_MB . 'MB).');
            } else {
                $newName = uniqid('bill_', true) . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $newName)) {
                    $db->prepare(
                        'INSERT INTO bills (description, amount, filename, original_filename, uploaded_by, bill_date)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    )->execute([$description, $amount, $newName, $file['name'], $user['id'], $billDate]);
                    flash('success', 'Bill uploaded.');
                } else {
                    flash('error', 'Could not save file. Check the uploads folder is writable.');
                }
            }
        }
        header('Location: ' . url('/owner/bills.php?year=' . (int)($_POST['year'] ?? date('Y'))));
        exit;
    }

    if ($action === 'delete') {
        $id   = (int)$_POST['bill_id'];
        $stmt = $db->prepare('SELECT * FROM bills WHERE id = ?');
        $stmt->execute([$id]);
        $bill = $stmt->fetch();
        if ($bill) {
            @unlink(UPLOAD_DIR . $bill['filename']);
            $db->prepare('DELETE FROM bills WHERE id = ?')->execute([$id]);
            flash('success', 'Bill deleted.');
        }
        header('Location: ' . url('/owner/bills.php?year=' . (int)($_POST['year'] ?? date('Y'))));
        exit;
    }

    if ($action === 'set_nights') {
        $nights = max(0, (int)$_POST['nights']);
        $yr     = (int)$_POST['year'];
        setSetting("nights_{$yr}", (string)$nights);
        header('Location: ' . url('/owner/bills.php?year=' . $yr));
        exit;
    }
}

// Year filter
$year      = (int)($_GET['year'] ?? date('Y'));
$yearsStmt = $db->query('SELECT DISTINCT YEAR(COALESCE(bill_date, created_at)) AS yr FROM bills ORDER BY yr DESC');
$years     = $yearsStmt->fetchAll(PDO::FETCH_COLUMN) ?: [date('Y')];
if (!in_array($year, $years)) $years[] = $year;
rsort($years);

$billsStmt = $db->prepare(
    'SELECT b.*, u.name AS uploader FROM bills b
     JOIN users u ON b.uploaded_by = u.id
     WHERE YEAR(COALESCE(b.bill_date, b.created_at)) = ?
     ORDER BY COALESCE(b.bill_date, b.created_at) DESC'
);
$billsStmt->execute([$year]);
$bills = $billsStmt->fetchAll();

$totalAmount = array_sum(array_column(
    array_filter($bills, fn($b) => $b['amount'] !== null),
    'amount'
));

$nights      = (int)getSetting("nights_{$year}", '0');
$costPerNight = ($nights > 0 && $totalAmount > 0) ? $totalAmount / $nights : null;

$success = flash('success');
$error   = flash('error');

$pageTitle   = 'Bills';
$section     = 'owner';
$currentPage = 'bills';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($success): ?>
<div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 mb-5 text-sm">✓ <?= e($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 mb-5 text-sm"><?= e($error) ?></div>
<?php endif; ?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-stone-800">Bills &amp; Invoices</h1>
    <p class="text-stone-500 text-sm mt-0.5">Upload and track hut expenses</p>
</div>

<div class="grid md:grid-cols-3 gap-6">

    <!-- ── Upload form ── -->
    <div class="space-y-4">
        <div class="bg-white rounded-xl border border-stone-200 p-6">
            <h2 class="font-semibold text-stone-700 mb-4">Upload a bill</h2>
            <form method="post" enctype="multipart/form-data" class="space-y-3">
                <input type="hidden" name="action" value="upload">
                <input type="hidden" name="year" value="<?= $year ?>">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <div>
                    <label class="block text-sm font-medium text-stone-600 mb-1">Description *</label>
                    <input type="text" name="description" required placeholder="e.g. Power bill March"
                           class="w-full px-3 py-2 border border-stone-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-600 mb-1">Amount (NZD)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-stone-400 text-sm">$</span>
                        <input type="number" name="amount" step="0.01" min="0" placeholder="0.00"
                               class="w-full pl-7 pr-3 py-2 border border-stone-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-600 mb-1">Bill date</label>
                    <input type="date" name="bill_date" value="<?= date('Y-m-d') ?>"
                           class="w-full px-3 py-2 border border-stone-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-600 mb-1">File * <span class="font-normal text-stone-400">(PDF, JPG, PNG)</span></label>
                    <input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.heic,.webp"
                           class="w-full text-sm text-stone-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-stone-100 file:text-stone-700 hover:file:bg-stone-200 cursor-pointer">
                </div>
                <button type="submit"
                        class="w-full bg-green-800 hover:bg-green-700 text-white font-semibold py-2.5 rounded-lg transition text-sm mt-1">
                    Upload Bill
                </button>
            </form>
        </div>

        <!-- ── Cost per night calculator ── -->
        <div class="bg-white rounded-xl border border-stone-200 p-6">
            <h2 class="font-semibold text-stone-700 mb-1">Cost per night — <?= $year ?></h2>
            <p class="text-xs text-stone-400 mb-4">Enter nights stayed to calculate running cost</p>

            <form method="post" class="flex gap-2 mb-5">
                <input type="hidden" name="action" value="set_nights">
                <input type="hidden" name="year" value="<?= $year ?>">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <div class="flex-1">
                    <label class="block text-xs text-stone-500 mb-1">Nights stayed in <?= $year ?></label>
                    <input type="number" name="nights" min="0" value="<?= $nights ?>"
                           placeholder="0"
                           class="w-full px-3 py-2 border border-stone-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div class="flex items-end">
                    <button type="submit"
                            class="bg-stone-700 hover:bg-stone-600 text-white px-4 py-2 rounded-lg text-sm transition">
                        Save
                    </button>
                </div>
            </form>

            <!-- Stats -->
            <div class="space-y-3">
                <div class="flex justify-between items-center py-2 border-b border-stone-100">
                    <span class="text-sm text-stone-600">Total costs</span>
                    <span class="font-semibold text-stone-800"><?= formatCurrency($totalAmount) ?></span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-stone-100">
                    <span class="text-sm text-stone-600">Nights stayed</span>
                    <span class="font-semibold text-stone-800"><?= $nights > 0 ? $nights . ' nights' : '—' ?></span>
                </div>
                <div class="flex justify-between items-center py-2 rounded-lg bg-green-50 px-3 -mx-1">
                    <span class="text-sm font-semibold text-green-800">Cost per night</span>
                    <span class="text-xl font-bold text-green-800">
                        <?= $costPerNight ? formatCurrency($costPerNight) : '—' ?>
                    </span>
                </div>
                <?php if ($costPerNight): ?>
                <p class="text-xs text-stone-400 text-center">
                    <?= formatCurrency($totalAmount) ?> ÷ <?= $nights ?> nights = <?= formatCurrency($costPerNight) ?>/night
                </p>
                <?php elseif ($totalAmount > 0 && $nights === 0): ?>
                <p class="text-xs text-amber-600 text-center">Enter nights stayed above to calculate</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Bills list ── -->
    <div class="md:col-span-2">
        <!-- Year tabs -->
        <div class="flex items-center gap-2 mb-4 flex-wrap">
            <?php foreach ($years as $yr): ?>
            <a href="?year=<?= (int)$yr ?>"
               class="px-3 py-1 rounded-full text-sm font-medium transition
                      <?= $yr == $year ? 'bg-green-800 text-white' : 'bg-white border border-stone-200 text-stone-600 hover:border-green-400' ?>">
                <?= (int)$yr ?>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($bills) && $totalAmount > 0): ?>
        <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-3 mb-4 text-sm text-green-800 flex items-center justify-between">
            <span>
                <strong><?= formatCurrency($totalAmount) ?></strong> total for <?= $year ?>
                (<?= count($bills) ?> bill<?= count($bills) !== 1 ? 's' : '' ?>)
            </span>
            <?php if ($costPerNight): ?>
            <span class="font-bold text-green-900"><?= formatCurrency($costPerNight) ?>/night</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (empty($bills)): ?>
        <div class="bg-white rounded-xl border border-stone-200 p-10 text-center text-stone-400">
            <div class="text-3xl mb-2">🧾</div>
            <p class="text-sm">No bills for <?= $year ?></p>
        </div>
        <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($bills as $bill): ?>
            <?php $isPdf = strtolower(pathinfo($bill['filename'], PATHINFO_EXTENSION)) === 'pdf'; ?>
            <div class="bg-white rounded-xl border border-stone-200 p-4 flex items-center gap-4">
                <div class="text-2xl flex-shrink-0"><?= $isPdf ? '📄' : '🖼' ?></div>
                <div class="flex-1 min-w-0">
                    <div class="font-medium text-stone-800 truncate"><?= e($bill['description']) ?></div>
                    <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                        <span class="text-xs text-stone-400">
                            <?= formatDate($bill['bill_date'] ?? $bill['created_at']) ?>
                        </span>
                        <span class="text-xs text-stone-300">·</span>
                        <span class="text-xs text-stone-400"><?= e($bill['uploader']) ?></span>
                        <?php if ($bill['amount']): ?>
                        <span class="text-xs text-stone-300">·</span>
                        <span class="text-xs font-semibold text-stone-600"><?= formatCurrency((float)$bill['amount']) ?></span>
                        <?php if ($costPerNight): ?>
                        <span class="text-xs text-stone-300">·</span>
                        <span class="text-xs text-stone-400"><?= number_format((float)$bill['amount'] / $totalAmount * 100, 1) ?>% of total</span>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex items-center gap-3 flex-shrink-0">
                    <a href="<?= url('/uploads/') . e($bill['filename']) ?>" target="_blank"
                       class="text-sm text-green-700 hover:text-green-900 font-medium">View</a>
                    <form method="post" class="inline">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="bill_id" value="<?= $bill['id'] ?>">
                        <input type="hidden" name="year" value="<?= $year ?>">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <button type="submit" class="text-sm text-stone-400 hover:text-red-500 transition"
                                onclick="return confirm('Delete this bill? This cannot be undone.')">Delete</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
