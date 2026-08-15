<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/justcall.php';
require_once __DIR__ . '/../includes/ui.php';
requireAdmin();
$user = currentUser();
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$name || !$email || !$password) {
        $message = 'Name, email, and password are required.';
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Enter a valid email address.';
        $messageType = 'error';
    } elseif (strlen($password) < 6) {
        $message = 'Password must be at least 6 characters.';
        $messageType = 'error';
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $email, hashPassword($password), 'sales_agent']);
            $agentId = $pdo->lastInsertId();

            $justcallMatch = findJustCallUser($name, $email);
            if ($justcallMatch['user']) {
                $justcallUser = $justcallMatch['user'];
                $numbers = extractJustCallUserNumbers($justcallUser);
                $justcallUserId = $justcallUser['id'] ?? $justcallUser['user_id'] ?? null;
                $status = ($justcallUser['available'] ?? '') === 'Yes' ? 'available' : 'offline';
                $pdo->prepare('UPDATE users SET justcall_user_id = ?, justcall_primary_number = ?, justcall_secondary_number = ?, justcall_third_number = ?, status = ? WHERE id = ?')->execute([
                    $justcallUserId,
                    $numbers[0] ?? null,
                    $numbers[1] ?? null,
                    $numbers[2] ?? null,
                    $status,
                    $agentId
                ]);
                $message = 'Agent created and matched with existing JustCall user.';
            } elseif ($justcallMatch['error']) {
                $message = 'Agent created locally, but JustCall users could not be checked. ' . $justcallMatch['error'];
                $messageType = 'warning';
            } else {
                $message = 'Agent created locally. No matching JustCall user was found for this name or email.';
                $messageType = 'warning';
            }
        } catch (PDOException $exception) {
            $message = 'Agent could not be created. Check for duplicate email or invalid data.';
            $messageType = 'error';
        }
    }
}

$agents = $pdo->query('SELECT * FROM users WHERE role = "sales_agent" ORDER BY id DESC')->fetchAll();
?>
<?php renderLayoutStart('Agents', $user, 'agents', 'Create sales agents and monitor their JustCall profile details.'); ?>
<?php flashMessage($message, $messageType); ?>
<section class="form-panel mb-18">
    <div class="section-header">
        <div>
            <h2>Add Agent</h2>
            <p>Creates the local CRM login and matches it with an existing JustCall user when the email or name is same.</p>
        </div>
    </div>
    <form method="post">
        <?php echo csrfInputFieldHtml(); ?>
        <div class="grid two-col">
            <div class="field"><label for="name">Agent Name</label><input id="name" name="name" required maxlength="100"></div>
            <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" required maxlength="100"></div>
            <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" required minlength="6" autocomplete="new-password"></div>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit" data-loading-text="Creating...">Create Agent</button>
        </div>
    </form>
</section>

<section class="section">
    <div class="section-header">
        <div>
            <h2>Agents</h2>
            <p><?php echo e(count($agents)); ?> sales agents in the workspace.</p>
        </div>
        <div class="toolbar">
            <input class="search-input" type="search" placeholder="Search agents or email" data-table-search="agents-list">
            <select class="filter-select" data-table-filter="agents-list" data-field="status">
                <option value="">All statuses</option>
                <option value="available">Available</option>
                <option value="on_call">On call</option>
                <option value="busy">Busy</option>
                <option value="offline">Offline</option>
            </select>
        </div>
    </div>
    <div class="table-shell">
        <div class="table-responsive">
            <table class="data-table" data-table="agents-list" data-page-size="10">
                <thead><tr><th data-sort>Name</th><th data-sort>Email</th><th data-sort>Status</th><th data-sort>JustCall Numbers</th></tr></thead>
        <tbody>
            <?php if ($agents) { foreach ($agents as $agent) { ?>
                <tr>
                    <td><?php echo e($agent['name']); ?></td>
                    <td><?php echo e($agent['email']); ?></td>
                    <td data-field="status" data-value="<?php echo e($agent['status'] ?? 'offline'); ?>"><?php echo statusBadge($agent['status'] ?? 'offline'); ?></td>
                    <td><?php echo e(compactNumbers($agent)); ?></td>
                </tr>
            <?php }} else { ?>
                <tr><td class="empty-state" colspan="4"><strong>No agents yet</strong><span>Create a sales agent to start assigning customers.</span></td></tr>
            <?php } ?>
        </tbody>
            </table>
        </div>
    </div>
</section>
<?php renderLayoutEnd(); ?>
