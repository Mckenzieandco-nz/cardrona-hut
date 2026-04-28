<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$db      = db();
$user    = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // ── Change own password ──────────────────────────────────────────────────
    if ($action === 'change_password') {
        $current  = $_POST['current_password'] ?? '';
        $new      = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($current, $row['password_hash'])) {
            flash('error', 'Current password is incorrect.');
        } elseif (strlen($new) < 8) {
            flash('error', 'New password must be at least 8 characters.');
        } elseif ($new !== $confirm) {
            flash('error', 'New passwords do not match.');
        } else {
            $db->prepare('UPDATE users SET password_hash=? WHERE id=?')
               ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            flash('success', 'Password updated.');
        }
        header('Location: ' . url('/owner/settings.php'));
        exit;
    }

    // ── Add owner (admin only) ───────────────────────────────────────────────
    if ($action === 'add_user' && isAdmin()) {
        $name     = trim($_POST['name'] ?? '');
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] === 'admin' ? 'admin' : 'owner';

        if (!$name || !$email || !$password) {
            flash('error', 'All fields are required.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Invalid email address.');
        } elseif (strlen($password) < 8) {
            flash('error', 'Password must be at least 8 characters.');
        } else {
            $check = $db->prepare('SELECT id FROM users WHERE email = ?');
            $check->execute([$email]);
            if ($check->fetch()) {
                flash('error', 'An account with that email already exists.');
            } else {
                $db->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)')
                   ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
                flash('success', $name . ' added as an owner.');
            }
        }
        header('Location: ' . url('/owner/settings.php'));
        exit;
    }

    // ── Delete owner (admin only, can't delete self) ─────────────────────────
    if ($action === 'delete_user' && isAdmin()) {
        $id = (int)$_POST['user_id'];
        if ($id === $user['id']) {
            flash('error', 'You cannot delete your own account.');
        } else {
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            flash('success', 'Owner removed.');
        }
        header('Location: ' . url('/owner/settings.php'));
        exit;
    }

    // ── Reset another user's password (admin only) ───────────────────────────
    if ($action === 'reset_password' && isAdmin()) {
        $id          = (int)$_POST['user_id'];
        $newPassword = $_POST['new_password'] ?? '';
        if (strlen($newPassword) < 8) {
            flash('error', 'Password must be at least 8 characters.');
        } else {
            $db->prepare('UPDATE users SET password_hash=? WHERE id=?')
               ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $id]);
            flash('success', 'Password reset.');
        }
        header('Location: ' . url('/owner/settings.php'));
        exit;
    }
}

$users   = $db->query('SELECT id, name, email, role, created_at FROM users ORDER BY role DESC, name')->fetchAll();
$success = flash('success');
$error   = flash('error');

$pageTitle   = 'Settings';
$section     = 'owner';
$currentPage = 'settings';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($success): ?>
<div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 mb-5 text-sm">✓ <?= e($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 mb-5 text-sm"><?= e($error) ?></div>
<?php endif; ?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-stone-800">Settings</h1>
</div>

<div class="grid lg:grid-cols-2 gap-6">

    <!-- ── Change own password ── -->
    <div class="bg-white rounded-xl border border-stone-200 p-6">
        <h2 class="font-semibold text-stone-700 mb-4">Change your password</h2>
        <form method="post" class="space-y-3">
            <input type="hidden" name="action" value="change_password">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <div>
                <label class="block text-sm font-medium text-stone-600 mb-1">Current password</label>
                <input type="password" name="current_password" required autocomplete="current-password"
                       class="w-full px-3 py-2 border border-stone-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-stone-600 mb-1">New password</label>
                <input type="password" name="new_password" required autocomplete="new-password" minlength="8"
                       class="w-full px-3 py-2 border border-stone-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-stone-600 mb-1">Confirm new password</label>
                <input type="password" name="confirm_password" required autocomplete="new-password" minlength="8"
                       class="w-full px-3 py-2 border border-stone-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
            <button type="submit"
                    class="w-full bg-green-800 hover:bg-green-700 text-white font-semibold py-2.5 rounded-lg transition text-sm">
                Update Password
            </button>
        </form>
    </div>

    <!-- ── Owner list ── -->
    <div>
        <div class="bg-white rounded-xl border border-stone-200 mb-4">
            <div class="px-5 py-4 border-b border-stone-100">
                <h2 class="font-semibold text-stone-700">Owners</h2>
            </div>
            <ul class="divide-y divide-stone-100">
                <?php foreach ($users as $u): ?>
                <li class="px-5 py-3 flex items-center gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-stone-700"><?= e($u['name']) ?></span>
                            <?php if ($u['role'] === 'admin'): ?>
                            <span class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded">Admin</span>
                            <?php endif; ?>
                            <?php if ($u['id'] == $user['id']): ?>
                            <span class="text-xs text-stone-400">(you)</span>
                            <?php endif; ?>
                        </div>
                        <div class="text-xs text-stone-400 mt-0.5"><?= e($u['email']) ?></div>
                    </div>
                    <?php if (isAdmin() && $u['id'] != $user['id']): ?>
                    <div class="flex items-center gap-2">
                        <!-- Reset password inline -->
                        <button onclick="toggleReset(<?= $u['id'] ?>)"
                                class="text-xs text-stone-400 hover:text-stone-600">Reset pw</button>
                        <form method="post" class="inline">
                            <input type="hidden" name="action" value="delete_user">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <button type="submit"
                                    class="text-xs text-stone-400 hover:text-red-500 transition"
                                    onclick="return confirm('Remove <?= addslashes(e($u['name'])) ?>?')">
                                Remove
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </li>
                <!-- Reset password form (hidden by default) -->
                <?php if (isAdmin() && $u['id'] != $user['id']): ?>
                <li id="reset-<?= $u['id'] ?>" class="hidden px-5 py-3 bg-stone-50">
                    <form method="post" class="flex gap-2">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="password" name="new_password" required minlength="8"
                               placeholder="New password (min 8 chars)"
                               class="flex-1 px-3 py-1.5 border border-stone-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                        <button type="submit"
                                class="bg-green-800 text-white text-xs px-3 py-1.5 rounded-lg hover:bg-green-700 transition">
                            Set
                        </button>
                    </form>
                </li>
                <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>

        <?php if (isAdmin()): ?>
        <!-- ── Add new owner ── -->
        <div class="bg-white rounded-xl border border-stone-200 p-5">
            <h2 class="font-semibold text-stone-700 mb-3">Add an owner</h2>
            <form method="post" class="space-y-3">
                <input type="hidden" name="action" value="add_user">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-stone-600 mb-1">Name</label>
                        <input type="text" name="name" required
                               class="w-full px-3 py-2 border border-stone-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-stone-600 mb-1">Role</label>
                        <select name="role"
                                class="w-full px-3 py-2 border border-stone-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                            <option value="owner">Owner</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-600 mb-1">Email</label>
                    <input type="email" name="email" required
                           class="w-full px-3 py-2 border border-stone-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-600 mb-1">Password <span class="font-normal text-stone-400">(min 8 chars)</span></label>
                    <input type="password" name="password" required minlength="8"
                           class="w-full px-3 py-2 border border-stone-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <button type="submit"
                        class="w-full bg-stone-700 hover:bg-stone-600 text-white font-semibold py-2.5 rounded-lg transition text-sm">
                    Add Owner
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>

</div>

<script>
function toggleReset(id) {
    const el = document.getElementById('reset-' + id);
    el.classList.toggle('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
