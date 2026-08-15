<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ui.php';
requireAdmin();
$user = currentUser();

$calls = $pdo->query('SELECT cl.*, c.name as customer_name, u.name as crm_agent_name FROM call_logs cl LEFT JOIN customers c ON c.id = cl.customer_id LEFT JOIN users u ON u.id = cl.agent_id ORDER BY cl.id DESC')->fetchAll();
$statuses = array_unique(array_filter(array_map(function ($call) {
    return $call['status'] ?? '';
}, $calls)));
sort($statuses);
?>
<?php renderLayoutStart('Call Logs', $user, 'calls', 'Review call status, duration, recordings, and dispositions.'); ?>
<section class="section">
    <div class="section-header">
        <div>
            <h2>Call Logs</h2>
            <p><?php echo e(count($calls)); ?> calls have been recorded.</p>
        </div>
        <div class="toolbar">
            <input class="search-input" type="search" placeholder="Search calls, customers, agents" data-table-search="calls">
            <select class="filter-select" data-table-filter="calls" data-field="status">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $status) { ?><option value="<?php echo e($status); ?>"><?php echo e(ucwords(str_replace('_', ' ', $status))); ?></option><?php } ?>
            </select>
        </div>
    </div>
    <div class="table-shell">
        <div class="table-responsive">
            <table class="data-table" data-table="calls" data-page-size="12">
                <thead><tr><th data-sort>ID</th><th data-sort>Customer</th><th data-sort>Customer Number</th><th data-sort>Agent</th><th data-sort>JustCall Number</th><th data-sort>Direction</th><th data-sort>Status</th><th data-sort>Duration</th><th>Recording</th><th data-sort>Started</th><th data-sort>Ended</th></tr></thead>
        <tbody>
            <?php if ($calls) { foreach ($calls as $call) { ?>
                <tr>
                    <td><?php echo e($call['id']); ?></td>
                    <td><?php echo e($call['customer_name'] ?? '-'); ?></td>
                    <td><?php echo e(customerPhoneDisplay($call['customer_number'] ?? '', $user)); ?></td>
                    <td><?php echo e($call['justcall_agent_name'] ?: ($call['crm_agent_name'] ?? '-')); ?></td>
                    <td><?php echo e($call['justcall_number'] ?? '-'); ?></td>
                    <td><?php echo e($call['direction'] ?? '-'); ?></td>
                    <td data-field="status" data-value="<?php echo e($call['status'] ?? ''); ?>"><?php echo statusBadge($call['status'] ?? '-'); ?></td>
                    <td><?php echo e($call['duration'] !== null ? $call['duration'] . ' sec' : '-'); ?></td>
                    <td><?php echo $call['recording_url'] ? '<audio controls preload="none" src="' . e($call['recording_url']) . '"></audio>' : '-'; ?></td>
                    <td><?php echo e($call['start_time'] ?? $call['created_at'] ?? '-'); ?></td>
                    <td><?php echo e($call['end_time'] ?? '-'); ?></td>
                </tr>
            <?php }} else { ?>
                <tr><td class="empty-state" colspan="11"><strong>No call logs yet</strong><span>Calls and webhooks will appear here once activity starts.</span></td></tr>
            <?php } ?>
        </tbody>
            </table>
        </div>
    </div>
</section>
<?php renderLayoutEnd(); ?>
