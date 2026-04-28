<?php
// Expected vars from calling page:
//   $pageTitle   (string)
//   $section     ('guest' | 'owner')
//   $currentPage (string, owner nav only)
$isOwner = ($section ?? '') === 'owner';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? SITE_NAME) ?> — <?= SITE_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        [x-cloak] { display: none !important; }
        .prose p { margin-bottom: 0.75rem; }
    </style>
</head>
<body class="bg-stone-50 min-h-screen font-sans text-stone-900 antialiased">

<?php if ($isOwner): ?>
<!-- ── Owner nav ── -->
<nav class="bg-green-900 text-white shadow-lg">
    <div class="max-w-6xl mx-auto px-4">
        <div class="flex items-center justify-between h-14">
            <div class="flex items-center gap-1">
                <a href="<?= url('/owner/dashboard.php') ?>" class="font-bold text-base mr-4 tracking-tight whitespace-nowrap">⛷ Cardrona Hut</a>
                <div class="hidden md:flex items-center gap-0.5">
                    <?php
                    $navLinks = [
                        'dashboard' => ['Dashboard',    '/owner/dashboard.php'],
                        'leaving'   => ['Leaving List', '/owner/leaving-list.php'],
                        'bills'     => ['Bills',        '/owner/bills.php'],
                        'jobs'      => ['Jobs',         '/owner/jobs.php'],
                        'guides'    => ['Guides',       '/owner/guides.php'],
                    ];
                    foreach ($navLinks as $key => [$label, $path]):
                        $active = ($currentPage ?? '') === $key;
                    ?>
                    <a href="<?= url($path) ?>" class="px-3 py-1.5 rounded text-sm <?= $active ? 'bg-green-700 font-medium' : 'hover:bg-green-800' ?>"><?= $label ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-sm text-green-300 hidden sm:block"><?= e($_SESSION['user_name'] ?? '') ?></span>
                <a href="<?= url('/owner/settings.php') ?>" class="text-green-300 hover:text-white text-sm hidden sm:block">Settings</a>
                <a href="<?= url('/owner/logout.php') ?>" class="text-green-300 hover:text-white text-sm">Logout</a>
            </div>
        </div>
        <!-- Mobile scroll nav -->
        <div class="md:hidden flex gap-1 pb-2 overflow-x-auto">
            <?php foreach ($navLinks as $key => [$label, $path]):
                $active = ($currentPage ?? '') === $key;
            ?>
            <a href="<?= url($path) ?>" class="whitespace-nowrap px-3 py-1 rounded text-sm <?= $active ? 'bg-green-700' : 'hover:bg-green-800' ?>"><?= $label ?></a>
            <?php endforeach; ?>
            <a href="<?= url('/owner/settings.php') ?>" class="whitespace-nowrap px-3 py-1 rounded text-sm <?= ($currentPage??'') === 'settings' ? 'bg-green-700' : 'hover:bg-green-800' ?>">Settings</a>
        </div>
    </div>
</nav>
<?php else: ?>
<!-- ── Guest nav ── -->
<nav class="bg-stone-800 text-white shadow">
    <div class="max-w-4xl mx-auto px-4 h-12 flex items-center gap-3">
        <a href="<?= url('/guest/') ?>" class="font-semibold tracking-wide">🏔 Cardrona Hut</a>
    </div>
</nav>
<?php endif; ?>

<main class="max-w-6xl mx-auto px-4 py-6">
