<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ui.php';
requireLogin();
$user = currentUser();

if (isAdminUser($user)) {
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM customers');
    $totalCustomers = $stmt->fetch()['total'];
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM users WHERE role = "sales_agent"');
    $totalAgents = $stmt->fetch()['total'];
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM call_logs');
    $totalCalls = $stmt->fetch()['total'];
    $agents = $pdo->query('SELECT id, name, status, justcall_primary_number, justcall_secondary_number, justcall_third_number FROM users WHERE role = "sales_agent" ORDER BY name')->fetchAll();
} else {
    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM customers WHERE assigned_agent_id = ?');
    $stmt->execute([$user['id']]);
    $totalCustomers = $stmt->fetch()['total'];
    $totalAgents = 1;
    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM call_logs WHERE agent_id = ?');
    $stmt->execute([$user['id']]);
    $totalCalls = $stmt->fetch()['total'];
    $agentStmt = $pdo->prepare('SELECT id, name, status, justcall_primary_number, justcall_secondary_number, justcall_third_number FROM users WHERE id = ? LIMIT 1');
    $agentStmt->execute([$user['id']]);
    $agent = $agentStmt->fetch();
    $agents = $agent ? [$agent] : [];
}

$availableAgents = array_filter($agents, function ($agent) {
    return ($agent['status'] ?? '') === 'available';
});
?>
<?php renderLayoutStart('Dashboard', $user, 'dashboard', 'A live overview of customers, agents, and call volume.'); ?>
<section class="grid stats-grid" aria-label="CRM summary">
    <article class="card stat-card">
        <span>Total customers</span>
        <strong><?php echo e($totalCustomers); ?></strong>
        <small class="muted">Customer records in CRM</small>
    </article>
    <article class="card stat-card">
        <span>Sales agents</span>
        <strong><?php echo e($totalAgents); ?></strong>
        <small class="muted"><?php echo e(count($availableAgents)); ?> currently available</small>
    </article>
    <article class="card stat-card">
        <span>Call logs</span>
        <strong><?php echo e($totalCalls); ?></strong>
        <small class="muted">Tracked call interactions</small>
    </article>
</section>

<section class="section mt-18">
    <div class="section-header">
        <div>
            <h2>Agent Status Dashboard</h2>
            <p>Monitor agent availability and assigned JustCall numbers.</p>
        </div>
        <div class="toolbar">
            <input class="search-input" type="search" placeholder="Search agents or numbers" data-table-search="agents">
            <select class="filter-select" data-table-filter="agents" data-field="status">
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
            <table class="data-table" data-table="agents" data-page-size="8">
                <thead><tr><th data-sort>Agent</th><th data-sort>Status</th><th data-sort>Numbers</th></tr></thead>
        <tbody>
            <?php if ($agents) { foreach ($agents as $agent) { ?>
                <tr>
                    <td><?php echo e($agent['name']); ?></td>
                    <td data-field="status" data-value="<?php echo e($agent['status'] ?? 'offline'); ?>"><?php echo statusBadge($agent['status'] ?? 'offline'); ?></td>
                    <td><?php echo e(compactNumbers($agent)); ?></td>
                </tr>
            <?php }} else { ?>
                <tr><td class="empty-state" colspan="3"><strong>No agents yet</strong><span>Create agents to see their live status here.</span></td></tr>
            <?php } ?>
        </tbody>
            </table>
        </div>
    </div>
</section>
<?php renderLayoutEnd(); ?>
