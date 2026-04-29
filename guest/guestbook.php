<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirect away if disabled
if (getSetting('guestbook_enabled', '1') !== '1') {
    header('Location: ' . url('/guest/'));
    exit;
}

$success = null;
$error   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['guest_name'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $rating  = isset($_POST['rating']) && (int)$_POST['rating'] >= 1 && (int)$_POST['rating'] <= 5
               ? (int)$_POST['rating'] : null;
    $date    = $_POST['stay_date'] ?? null;

    if (!$name) {
        $error = 'Please enter your name.';
    } elseif (!$message) {
        $error = 'Please write a message.';
    } else {
        db()->prepare(
            'INSERT INTO guestbook (guest_name, stay_date, message, rating) VALUES (?, ?, ?, ?)'
        )->execute([$name, $date ?: null, $message, $rating]);
        $success = true;
    }
}

$entries = db()->query(
    'SELECT * FROM guestbook ORDER BY created_at DESC'
)->fetchAll();

$pageTitle = 'Guest Book';
$section   = 'guest';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-2xl mx-auto">

    <div class="mb-8 text-center">
        <div class="text-4xl mb-2">📖</div>
        <h1 class="text-2xl font-bold text-stone-800">Guest Book</h1>
        <p class="text-stone-500 text-sm mt-1">Share your memories of 9 Little Meg</p>
    </div>

    <?php if ($success): ?>
    <!-- Success state -->
    <div class="bg-green-50 border border-green-200 rounded-2xl p-8 text-center mb-8">
        <div class="text-4xl mb-3">🥂</div>
        <h2 class="font-semibold text-green-800 text-lg mb-1">Thanks for signing the guest book!</h2>
        <p class="text-green-700 text-sm mb-4">Your entry has been added.</p>
        <a href="<?= url('/guest/guestbook.php') ?>"
           class="inline-block bg-green-800 hover:bg-green-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition">
            View all entries
        </a>
    </div>
    <?php else: ?>
    <!-- Add entry form -->
    <div class="bg-white rounded-2xl border border-stone-200 p-6 mb-8 shadow-sm">
        <h2 class="font-semibold text-stone-700 mb-4">Leave a message</h2>

        <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 mb-4 text-sm">
            <?= e($error) ?>
        </div>
        <?php endif; ?>

        <form method="post" class="space-y-4">
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-stone-600 mb-1.5">Your name *</label>
                    <input type="text" name="guest_name" required
                           value="<?= e($_POST['guest_name'] ?? '') ?>"
                           placeholder="e.g. The Smith Family"
                           class="w-full px-3 py-2.5 border border-stone-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-600 mb-1.5">Date of stay</label>
                    <input type="date" name="stay_date"
                           value="<?= e($_POST['stay_date'] ?? date('Y-m-d')) ?>"
                           class="w-full px-3 py-2.5 border border-stone-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
            </div>

            <!-- Star rating -->
            <div>
                <label class="block text-sm font-medium text-stone-600 mb-2">Your rating</label>
                <div class="flex gap-1" id="stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <label class="cursor-pointer">
                        <input type="radio" name="rating" value="<?= $i ?>" class="sr-only"
                               <?= (($_POST['rating'] ?? 5) == $i) ? 'checked' : '' ?>>
                        <span class="text-3xl star-btn transition-transform hover:scale-110 select-none"
                              data-val="<?= $i ?>">☆</span>
                    </label>
                    <?php endfor; ?>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-600 mb-1.5">Message *</label>
                <textarea name="message" required rows="4"
                          placeholder="Tell us about your stay..."
                          class="w-full px-3 py-2.5 border border-stone-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500 resize-none"><?= e($_POST['message'] ?? '') ?></textarea>
            </div>

            <button type="submit"
                    class="w-full bg-green-800 hover:bg-green-700 text-white font-semibold py-3 rounded-lg transition">
                Sign the Guest Book ✍️
            </button>
        </form>
    </div>
    <?php endif; ?>

    <!-- Entries -->
    <?php if (empty($entries)): ?>
    <div class="text-center text-stone-400 py-10">
        <p class="text-sm">No entries yet — be the first!</p>
    </div>
    <?php else: ?>
    <div class="space-y-4">
        <h2 class="text-sm font-semibold text-stone-500 uppercase tracking-wide">
            <?= count($entries) ?> entr<?= count($entries) === 1 ? 'y' : 'ies' ?>
        </h2>
        <?php foreach ($entries as $entry): ?>
        <div class="bg-white rounded-2xl border border-stone-200 p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3 mb-3">
                <div>
                    <div class="font-semibold text-stone-800"><?= e($entry['guest_name']) ?></div>
                    <?php if ($entry['stay_date']): ?>
                    <div class="text-xs text-stone-400 mt-0.5">
                        Stayed <?= date('F Y', strtotime($entry['stay_date'])) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if ($entry['rating']): ?>
                <div class="text-lg flex-shrink-0">
                    <?= str_repeat('⭐', (int)$entry['rating']) ?>
                </div>
                <?php endif; ?>
            </div>
            <p class="text-stone-700 text-sm leading-relaxed whitespace-pre-line"><?= e($entry['message']) ?></p>
            <p class="text-xs text-stone-300 mt-3"><?= date('d M Y', strtotime($entry['created_at'])) ?></p>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<script>
// Star rating interaction
const stars = document.querySelectorAll('.star-btn');
const inputs = document.querySelectorAll('input[name="rating"]');

function updateStars(val) {
    stars.forEach(s => {
        s.textContent = parseInt(s.dataset.val) <= val ? '⭐' : '☆';
    });
}

// Set initial state
const checked = document.querySelector('input[name="rating"]:checked');
if (checked) updateStars(parseInt(checked.value));

stars.forEach(star => {
    star.addEventListener('mouseover', () => updateStars(parseInt(star.dataset.val)));
    star.addEventListener('click', () => {
        const val = parseInt(star.dataset.val);
        inputs[val - 1].checked = true;
        updateStars(val);
    });
});

document.getElementById('stars')?.addEventListener('mouseleave', () => {
    const checked = document.querySelector('input[name="rating"]:checked');
    updateStars(checked ? parseInt(checked.value) : 0);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
