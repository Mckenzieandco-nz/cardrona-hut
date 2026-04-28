<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

sessionStart();

if (isLoggedIn()) {
    header('Location: ' . url('/owner/dashboard.php'));
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (login($email, $password)) {
        header('Location: ' . url('/owner/dashboard.php'));
        exit;
    }
    $error = 'Invalid email or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Login — <?= SITE_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex items-center justify-center p-4"
      style="background: linear-gradient(135deg, #1a2e1a 0%, #2d4a3e 50%, #3d5a4a 100%);">
    <div class="w-full max-w-sm">
        <div class="text-center mb-8">
            <div class="text-5xl mb-3">⛷</div>
            <h1 class="text-2xl font-bold text-white"><?= SITE_NAME ?></h1>
            <p class="text-green-300 text-sm mt-1">Owner access</p>
        </div>

        <div class="bg-white rounded-2xl p-8 shadow-2xl">
            <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 mb-5 text-sm">
                <?= e($error) ?>
            </div>
            <?php endif; ?>

            <form method="post" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-stone-700 mb-1.5">Email</label>
                    <input type="email" name="email" required autocomplete="email"
                           value="<?= e($_POST['email'] ?? '') ?>"
                           class="w-full px-3 py-2.5 border border-stone-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-600">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700 mb-1.5">Password</label>
                    <input type="password" name="password" required autocomplete="current-password"
                           class="w-full px-3 py-2.5 border border-stone-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-600">
                </div>
                <button type="submit"
                        class="w-full bg-green-800 hover:bg-green-700 text-white font-semibold py-2.5 rounded-lg transition mt-2">
                    Log in
                </button>
            </form>
        </div>

        <p class="text-center mt-5">
            <a href="<?= url('/') ?>" class="text-green-400 hover:text-green-300 text-sm">← Back to home</a>
        </p>
    </div>
</body>
</html>
