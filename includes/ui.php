<?php
function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function pageName() {
    return basename($_SERVER['SCRIPT_NAME'] ?? '');
}

function navItems($user) {
    $items = [
        ['label' => 'Dashboard', 'href' => 'index.php', 'key' => 'dashboard', 'icon' => 'grid'],
        ['label' => 'Customers', 'href' => 'customers.php', 'key' => 'customers', 'icon' => 'users'],
    ];

    if (($user['role'] ?? '') === 'admin') {
        $items[] = ['label' => 'Calls', 'href' => 'calls.php', 'key' => 'calls', 'icon' => 'phone'];
        $items[] = ['label' => 'Agents', 'href' => 'agents.php', 'key' => 'agents', 'icon' => 'agent'];
        $items[] = ['label' => 'Settings', 'href' => 'settings.php', 'key' => 'settings', 'icon' => 'settings'];
    }

    return $items;
}

function iconMarkup($name) {
    $icons = [
        'grid' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/></svg>',
        'users' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 11a4 4 0 1 0-3.2-6.4A5 5 0 0 1 14 8a5 5 0 0 1-1.2 3.4A4 4 0 0 0 16 11zM8 13a5 5 0 1 0 0-10 5 5 0 0 0 0 10zM8 15c-3.3 0-6 1.8-6 4v1h12v-1c0-2.2-2.7-4-6-4zM16 13c-1.1 0-2.1.2-3 .6 1.8 1 3 2.5 3 4.4v2h6v-1c0-2.2-2.7-4-6-4z"/></svg>',
        'phone' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 10.8a15.5 15.5 0 0 0 6.6 6.6l2.2-2.2c.3-.3.7-.4 1.1-.3 1.2.4 2.5.6 3.8.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1C10.9 21 3 13.1 3 3.7c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.6.6 3.8.1.4 0 .8-.3 1.1l-2.2 2.2z"/></svg>',
        'agent' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5zM4 22a8 8 0 0 1 16 0zM19 8h2v5h-2zM3 8h2v5H3z"/></svg>',
        'settings' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19.4 13.5c.1-.5.1-1 .1-1.5s0-1-.1-1.5l2-1.5-2-3.5-2.4 1a8 8 0 0 0-2.6-1.5L14 2h-4l-.4 2.5A8 8 0 0 0 7 6L4.6 5l-2 3.5 2 1.5c-.1.5-.1 1-.1 1.5s0 1 .1 1.5l-2 1.5 2 3.5 2.4-1a8 8 0 0 0 2.6 1.5L10 22h4l.4-2.5A8 8 0 0 0 17 18l2.4 1 2-3.5zM12 15.5a3.5 3.5 0 1 1 0-7 3.5 3.5 0 0 1 0 7z"/></svg>',
        'logout' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 3h9a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1h-9v-2h8V5h-8zM9 8l-1.4 1.4 1.6 1.6H3v2h6.2l-1.6 1.6L9 16l4-4z"/></svg>',
    ];

    return $icons[$name] ?? '';
}

function renderLayoutStart($title, $user, $active, $subtitle = '') {
    $displayName = $user['name'] ?? 'User';
    $role = ucwords(str_replace('_', ' ', $user['role'] ?? ''));
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrfToken()); ?>">
    <title><?php echo e($title); ?> | Sales CRM</title>
    <link rel="stylesheet" href="assets/css/app.css">
    <script defer src="assets/js/app.js"></script>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="index.php" aria-label="Sales CRM dashboard">
            <span class="brand-mark">SC</span>
            <span><strong>Sales CRM</strong><small>Revenue workspace</small></span>
        </a>
        <nav class="side-nav" aria-label="Primary navigation">
            <?php foreach (navItems($user) as $item) { ?>
                <a class="<?php echo $active === $item['key'] ? 'active' : ''; ?>" href="<?php echo e($item['href']); ?>">
                    <?php echo iconMarkup($item['icon']); ?>
                    <span><?php echo e($item['label']); ?></span>
                </a>
            <?php } ?>
        </nav>
        <div class="sidebar-footer">
            <div class="user-chip">
                <span class="avatar"><?php echo e(strtoupper(substr($displayName, 0, 1))); ?></span>
                <span><strong><?php echo e($displayName); ?></strong><small><?php echo e($role); ?></small></span>
            </div>
            <a class="logout-link" href="logout.php"><?php echo iconMarkup('logout'); ?><span>Logout</span></a>
        </div>
    </aside>
    <div class="main-panel">
        <header class="topbar">
            <button class="icon-button menu-toggle" type="button" data-sidebar-toggle aria-label="Toggle navigation">
                <span></span><span></span><span></span>
            </button>
            <div>
                <p class="eyebrow">Sales operations</p>
                <h1><?php echo e($title); ?></h1>
                <?php if ($subtitle) { ?><p class="page-subtitle"><?php echo e($subtitle); ?></p><?php } ?>
            </div>
            <div class="topbar-user">
                <span class="avatar"><?php echo e(strtoupper(substr($displayName, 0, 1))); ?></span>
                <span><?php echo e($displayName); ?></span>
            </div>
        </header>
        <main class="content">
    <?php
}

function renderLayoutEnd() {
    ?>
        </main>
    </div>
</div>
</body>
</html>
    <?php
}

function flashMessage($message, $type = 'success') {
    if (!$message) {
        return;
    }
    echo '<div class="alert alert-' . e($type) . '" role="status">' . e($message) . '</div>';
}

function statusBadge($status) {
    $status = $status ?: 'offline';
    $class = strtolower(str_replace(' ', '-', $status));
    return '<span class="status-badge status-' . e($class) . '">' . e(ucwords(str_replace('_', ' ', $status))) . '</span>';
}

function moneyValue($value) {
    return number_format((float) $value, 2);
}

function canViewFullCustomerPhone($user) {
    return ($user['role'] ?? '') === 'admin';
}

function phoneLastFour($phoneNumber) {
    $digits = preg_replace('/\D+/', '', (string) $phoneNumber);
    if ($digits === '') {
        return '-';
    }

    return substr($digits, -4);
}

function customerPhoneDisplay($phoneNumber, $user) {
    if (canViewFullCustomerPhone($user)) {
        return (string) $phoneNumber;
    }

    $lastFour = phoneLastFour($phoneNumber);
    return $lastFour === '-' ? '-' : '******' . $lastFour;
}

function compactNumbers($row) {
    $numbers = array_filter([
        $row['justcall_primary_number'] ?? '',
        $row['justcall_secondary_number'] ?? '',
        $row['justcall_third_number'] ?? '',
    ]);

    return $numbers ? implode(', ', $numbers) : '-';
}
