<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
sessionStart();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cardrona Hut</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex flex-col items-center justify-center p-6"
      style="background: linear-gradient(135deg, #1a2e1a 0%, #2d4a3e 40%, #3d5a4a 100%);">

    <div class="text-center mb-10">
        <div class="text-7xl mb-5">🏔</div>
        <h1 class="text-5xl font-bold text-white tracking-tight mb-2">Cardrona Hut</h1>
        <p class="text-green-300 text-lg">Cardrona, New Zealand</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 w-full max-w-md">
        <a href="<?= url('/guest/') ?>"
           class="group bg-white/10 hover:bg-white/20 backdrop-blur border border-white/20 hover:border-white/40 transition-all duration-200 rounded-2xl p-8 text-center">
            <div class="text-5xl mb-4">📖</div>
            <div class="font-semibold text-white text-lg mb-1">Guest Info</div>
            <div class="text-green-300 text-sm">Guides &amp; how-tos</div>
        </a>
        <a href="<?= url('/owner/login.php') ?>"
           class="group bg-green-800/80 hover:bg-green-700/90 backdrop-blur border border-green-600/50 hover:border-green-500 transition-all duration-200 rounded-2xl p-8 text-center">
            <div class="text-5xl mb-4">🔑</div>
            <div class="font-semibold text-white text-lg mb-1">Owner Login</div>
            <div class="text-green-300 text-sm">Manage the hut</div>
        </a>
    </div>

</body>
</html>
