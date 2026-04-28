<?php
/**
 * Cardrona Hut — First-run setup
 *
 * 1. Fill in config.php with your Hostinger DB credentials first
 * 2. Visit this page once in your browser to create all tables and seed data
 * 3. DELETE this file immediately afterwards
 */

// Very basic protection
if (file_exists(__DIR__ . '/.setup_done')) {
    die('<p style="font-family:sans-serif;color:red;">Setup has already been run. Delete setup.php from your server.</p>');
}

require_once __DIR__ . '/config.php';

$errors   = [];
$messages = [];

// ── Connect ──────────────────────────────────────────────────────────────────
try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $db  = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('<p style="font-family:sans-serif;color:red;">Database connection failed: ' . htmlspecialchars($e->getMessage()) . '<br>Check DB_HOST, DB_NAME, DB_USER, DB_PASS in config.php.</p>');
}

// ── Handle form submission ────────────────────────────────────────────────────
$done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminName     = trim($_POST['admin_name'] ?? '');
    $adminEmail    = strtolower(trim($_POST['admin_email'] ?? ''));
    $adminPassword = $_POST['admin_password'] ?? '';
    $confirm       = $_POST['confirm_password'] ?? '';

    if (!$adminName)                                  $errors[] = 'Name is required.';
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
    if (strlen($adminPassword) < 8)                  $errors[] = 'Password must be at least 8 characters.';
    if ($adminPassword !== $confirm)                  $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        // ── Create tables ─────────────────────────────────────────────────────
        $tables = [
            "CREATE TABLE IF NOT EXISTS users (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                name          VARCHAR(100)  NOT NULL,
                email         VARCHAR(150)  NOT NULL UNIQUE,
                password_hash VARCHAR(255)  NOT NULL,
                role          ENUM('owner','admin') NOT NULL DEFAULT 'owner',
                created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS guides (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                title         VARCHAR(200) NOT NULL,
                slug          VARCHAR(200) NOT NULL UNIQUE,
                content       TEXT         NOT NULL,
                excerpt       VARCHAR(300) DEFAULT '',
                icon          VARCHAR(10)  DEFAULT '📄',
                display_order INT          DEFAULT 0,
                created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS jobs (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                title         VARCHAR(200) NOT NULL,
                description   TEXT,
                priority      ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
                status        ENUM('open','done')         NOT NULL DEFAULT 'open',
                assigned_to   INT  DEFAULT NULL,
                created_by    INT  NOT NULL,
                created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                completed_at  TIMESTAMP NULL DEFAULT NULL,
                FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (created_by)  REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS bills (
                id                INT AUTO_INCREMENT PRIMARY KEY,
                description       VARCHAR(200)   NOT NULL,
                amount            DECIMAL(10,2)  DEFAULT NULL,
                filename          VARCHAR(255)   NOT NULL,
                original_filename VARCHAR(255)   NOT NULL,
                uploaded_by       INT            NOT NULL,
                bill_date         DATE           DEFAULT NULL,
                created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (uploaded_by) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS leaving_items (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                item          VARCHAR(200) NOT NULL,
                display_order INT          DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS leaving_completions (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                completed_by  INT  NOT NULL,
                completed_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                items_json    TEXT,
                notes         TEXT,
                FOREIGN KEY (completed_by) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];

        foreach ($tables as $sql) {
            $db->exec($sql);
        }
        $messages[] = '✓ Database tables created.';

        // ── Admin user ────────────────────────────────────────────────────────
        $existing = $db->prepare('SELECT id FROM users WHERE email = ?');
        $existing->execute([$adminEmail]);
        if (!$existing->fetch()) {
            $db->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)')
               ->execute([$adminName, $adminEmail, password_hash($adminPassword, PASSWORD_DEFAULT), 'admin']);
            $messages[] = '✓ Admin account created for ' . htmlspecialchars($adminEmail) . '.';
        } else {
            $messages[] = '⚠ Admin email already exists — skipped user creation.';
        }

        // ── Leaving list items ────────────────────────────────────────────────
        $count = $db->query('SELECT COUNT(*) FROM leaving_items')->fetchColumn();
        if ($count == 0) {
            $defaultItems = [
                'Lock all windows and doors',
                'Turn off all lights',
                'Turn off spa / hot tub',
                'Turn off heating and aircon',
                'Empty all rubbish bins',
                'Strip beds and leave linen for washing',
                'Clean kitchen benches and dishes',
                'Check BBQ is off and covered',
                'Secure outdoor furniture',
                'Turn off water heater',
                'Check all appliances are off',
                'Secure the garage / storage',
            ];
            $stmt = $db->prepare('INSERT INTO leaving_items (item, display_order) VALUES (?, ?)');
            foreach ($defaultItems as $i => $item) {
                $stmt->execute([$item, $i + 1]);
            }
            $messages[] = '✓ Default leaving checklist created (' . count($defaultItems) . ' items).';
        } else {
            $messages[] = '⚠ Leaving items already exist — skipped.';
        }

        // ── Sample guides ─────────────────────────────────────────────────────
        $guideCount = $db->query('SELECT COUNT(*) FROM guides')->fetchColumn();
        if ($guideCount == 0) {
            $sampleGuides = [
                ['🏠', 'House Rules', 'house-rules', 'The basics for a great stay.',
                 "Welcome to Cardrona Hut!\n\nPlease treat the hut as your own home.\n\n" .
                 "CHECK-IN\nKey is in the lockbox by the front door. Code will be sent separately.\n\n" .
                 "CHECKOUT\nPlease complete the leaving checklist before you go. Check-out by 10am.\n\n" .
                 "GENERAL\n- No smoking inside\n- Keep noise down after 10pm\n- No parties or large gatherings\n- Dogs by prior arrangement only\n\n" .
                 "In an emergency call 111."],
                ['❄️', 'Heating & Aircon', 'heating-aircon', 'How to use the heat pump and heating.',
                 "HEAT PUMP\nThe heat pump remote is on the windowsill in the lounge.\n\n" .
                 "Heating: Press MODE until 'HEAT' shows, then use the arrows to set temperature.\n" .
                 "Recommended setting: 20–22°C\n\n" .
                 "Cooling: Press MODE until 'COOL' shows.\n\n" .
                 "TIPS\n- Keep doors and windows closed when running\n- Turn off when leaving the hut\n- If it's not responding, check the wall switch is on"],
                ['♨️', 'Hot Tub / Spa', 'hot-tub', 'Getting the most out of the spa.',
                 "USING THE SPA\nThe spa is located on the back deck.\n\n" .
                 "TURNING ON\n1. Lift the cover and set it aside on the holder\n2. Press the Jets button to start\n3. Use the Temperature button to adjust heat\n\nIdeal temperature: 38–40°C\n\n" .
                 "RULES\n- Shower before using\n- Maximum 4 people\n- No glass near the spa\n- Children must be supervised\n\n" .
                 "TURNING OFF\nPress Jets to stop, then replace the cover securely to retain heat.\n\n" .
                 "Note: Leave the spa powered on (not jets, just heating) — do not switch off at the wall."],
                ['📶', 'WiFi', 'wifi', 'Internet access details.',
                 "WIFI DETAILS\nNetwork: CARDRONAhut\nPassword: [contact owner for password]\n\n" .
                 "Works throughout the hut and on the deck.\n\nIf you have connection issues, the router is in the cupboard by the front door — try turning it off and on again."],
                ['🍳', 'Kitchen', 'kitchen', 'Appliances, coffee, and cooking.',
                 "COFFEE\nWe have a Nespresso machine on the bench. Pods are in the drawer below.\nPress the button for espresso, hold for lungo.\n\n" .
                 "OVEN\nFan bake recommended. Preheat for 10 mins.\n\n" .
                 "DISHWASHER\nTablets are under the sink. Run a cycle before you leave.\n\n" .
                 "BBQ\nGas BBQ on the deck. Turn the gas valve on the bottle before igniting.\n" .
                 "Always turn gas off at the bottle after use.\n\n" .
                 "PLEASE\n- Leave the kitchen clean\n- Don't leave food out\n- Put rubbish in the outdoor bin"],
                ['🗑️', 'Rubbish & Recycling', 'rubbish', 'What goes where.',
                 "RUBBISH\nGeneral rubbish goes in the black bin in the garage.\n" .
                 "Council collection is Tuesday morning — put the bin out Monday night if needed.\n\n" .
                 "RECYCLING\nYellow lid bin — paper, cardboard, tins, plastic bottles (rinsed).\n\n" .
                 "GLASS\nGlass goes in the blue crate by the garage.\n\n" .
                 "COMPOST\nFood scraps in the green bin under the kitchen sink.\n\n" .
                 "Please don't leave rubbish inside overnight — it attracts mice."],
                ['🗺️', 'Local Area', 'local-area', 'Things to do around Cardrona.',
                 "SKIING & SNOWBOARDING\nCardrona Alpine Resort — 5 min drive. World-class terrain for all abilities.\n\n" .
                 "WANAKA\n20 min drive. Great cafes, restaurants, and Lake Wanaka walks.\n\nMust-do: Roy's Peak track (allow 5–6 hours).\n\n" .
                 "QUEENSTOWN\n45 min drive. Restaurants, bars, bungy, and more.\n\n" .
                 "LOCAL TIPS\n- Cardrona Hotel (5 min drive) — historic pub, great meals\n- Snow Farm — cross-country skiing and snowshoeing\n- Treble Cone — alternative ski field, spectacular views"],
            ];

            $stmt = $db->prepare(
                'INSERT INTO guides (title, slug, content, icon, excerpt, display_order) VALUES (?, ?, ?, ?, ?, ?)'
            );
            foreach ($sampleGuides as $i => [$icon, $title, $slug, $excerpt, $content]) {
                $stmt->execute([$title, $slug, $content, $icon, $excerpt, ($i + 1) * 10]);
            }
            $messages[] = '✓ Sample guides created (' . count($sampleGuides) . ' guides).';
        } else {
            $messages[] = '⚠ Guides already exist — skipped.';
        }

        // ── Mark done ─────────────────────────────────────────────────────────
        file_put_contents(__DIR__ . '/.setup_done', date('Y-m-d H:i:s'));
        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup — Cardrona Hut</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-stone-100 flex items-center justify-center p-6">
<div class="w-full max-w-lg bg-white rounded-2xl shadow-xl p-8">

    <div class="text-center mb-8">
        <div class="text-5xl mb-3">🏔</div>
        <h1 class="text-2xl font-bold text-stone-800">Cardrona Hut — Setup</h1>
        <p class="text-stone-500 text-sm mt-1">Run once to create the database and your admin account.</p>
    </div>

    <?php if ($done): ?>
    <!-- ── Success ── -->
    <div class="bg-green-50 border border-green-200 rounded-xl p-5 mb-6">
        <?php foreach ($messages as $m): ?>
        <p class="text-sm text-green-800 mb-1"><?= $m ?></p>
        <?php endforeach; ?>
    </div>
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6">
        <p class="text-sm font-semibold text-amber-800 mb-1">⚠ Important — do this now:</p>
        <p class="text-sm text-amber-700">Delete <code class="bg-amber-100 px-1 rounded">setup.php</code> from your server via Hostinger File Manager. It should not be accessible once setup is complete.</p>
    </div>
    <div class="flex gap-3">
        <a href="<?= BASE_URL ?>/" class="flex-1 text-center bg-stone-700 hover:bg-stone-600 text-white font-semibold py-2.5 rounded-lg transition">
            Go to Home
        </a>
        <a href="<?= BASE_URL ?>/owner/login.php" class="flex-1 text-center bg-green-800 hover:bg-green-700 text-white font-semibold py-2.5 rounded-lg transition">
            Owner Login →
        </a>
    </div>

    <?php else: ?>
    <!-- ── Setup form ── -->
    <?php if ($errors): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-5">
        <?php foreach ($errors as $e): ?>
        <p class="text-sm text-red-700"><?= htmlspecialchars($e) ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="post" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1.5">Your name</label>
            <input type="text" name="admin_name" required
                   value="<?= htmlspecialchars($_POST['admin_name'] ?? '') ?>"
                   placeholder="e.g. James"
                   class="w-full px-3 py-2.5 border border-stone-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-600">
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1.5">Email address</label>
            <input type="email" name="admin_email" required
                   value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>"
                   class="w-full px-3 py-2.5 border border-stone-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-600">
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1.5">Password <span class="font-normal text-stone-400">(min 8 chars)</span></label>
            <input type="password" name="admin_password" required minlength="8"
                   class="w-full px-3 py-2.5 border border-stone-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-600">
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1.5">Confirm password</label>
            <input type="password" name="confirm_password" required minlength="8"
                   class="w-full px-3 py-2.5 border border-stone-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-600">
        </div>

        <div class="bg-stone-50 border border-stone-200 rounded-lg p-3 text-xs text-stone-500">
            This will create all database tables and seed the following default data:
            <ul class="mt-1 list-disc list-inside space-y-0.5">
                <li>Your admin account</li>
                <li>12-item leaving checklist</li>
                <li>7 sample guest guides (edit them in the owner area)</li>
            </ul>
        </div>

        <button type="submit"
                class="w-full bg-green-800 hover:bg-green-700 text-white font-bold py-3 rounded-lg transition">
            Run Setup
        </button>
    </form>
    <?php endif; ?>

</div>
</body>
</html>
