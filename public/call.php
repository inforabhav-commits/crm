<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/justcall.php';
require_once __DIR__ . '/../includes/ui.php';
requireLogin();
$user = currentUser();
$currentUserRow = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$currentUserRow->execute([$user['id']]);
$currentUserRow = $currentUserRow->fetch() ?: $user;
$message = '';
$messageType = 'success';
$justcallConfig = justcallConfig();

$customerId = (int)($_GET['customer_id'] ?? 0);
$customer = $pdo->prepare('SELECT * FROM customers WHERE id = ? LIMIT 1');
$customer->execute([$customerId]);
$customer = $customer->fetch();
if ($customer) {
    requireCustomerAccess($customer, $user);
}

$history = [];
if ($customer) {
    if (isAdminUser($user)) {
        $historyStmt = $pdo->prepare('SELECT cl.*, u.name as crm_agent_name FROM call_logs cl LEFT JOIN users u ON u.id = cl.agent_id WHERE cl.customer_id = ? ORDER BY cl.id DESC LIMIT 25');
        $historyStmt->execute([$customer['id']]);
    } else {
        $historyStmt = $pdo->prepare('SELECT cl.*, u.name as crm_agent_name FROM call_logs cl LEFT JOIN users u ON u.id = cl.agent_id WHERE cl.customer_id = ? AND cl.agent_id = ? ORDER BY cl.id DESC LIMIT 25');
        $historyStmt->execute([$customer['id'], $user['id']]);
    }
    $history = $historyStmt->fetchAll();
}
?>
<?php renderLayoutStart('Call Customer', $user, 'customers', 'Call customers through the embedded JustCall Dialer SDK.'); ?>
<?php flashMessage($message, $messageType); ?>
<section class="section">
    <div class="section-header">
        <div>
            <h2>Call Customer</h2>
            <p>Sales users send only the customer ID to the server. Customer numbers stay masked in the CRM.</p>
        </div>
        <a class="button-link btn-secondary" href="customers.php">Back to Customers</a>
    </div>
    <?php if ($customer) { ?>
        <div class="customer-summary">
            <div class="summary-item"><span>Name</span><strong><?php echo e($customer['name']); ?></strong></div>
            <div class="summary-item"><span>Email</span><strong><?php echo e($customer['email'] ?? '-'); ?></strong></div>
            <div class="summary-item"><span>Phone</span><strong><?php echo e(customerPhoneDisplay($customer['phone_no'], $user)); ?></strong></div>
            <?php if (isAdminUser($user)) { ?>
            <div class="summary-item"><span>Customer ID</span><strong><?php echo e($customer['customer_id']); ?></strong></div>
            <div class="summary-item"><span>Plan</span><strong><?php echo e($customer['plan'] ?: '-'); ?></strong></div>
            <div class="summary-item"><span>Software</span><strong><?php echo e($customer['software'] ?: '-'); ?></strong></div>
            <div class="summary-item"><span>Amount</span><strong><?php echo e(moneyValue($customer['amount'])); ?></strong></div>
            <div class="summary-item"><span>License Number</span><strong><?php echo e($customer['license_number'] ?: '-'); ?></strong></div>
            <div class="summary-item"><span>Product Number</span><strong><?php echo e($customer['product_number'] ?: '-'); ?></strong></div>
            <div class="summary-item"><span>File Password</span><strong><?php echo e($customer['file_password'] ?: '-'); ?></strong></div>
            <div class="summary-item"><span>Payment Type</span><strong><?php echo e($customer['payment_type'] ?: '-'); ?> <?php echo $customer['last4'] ? '(' . e($customer['last4']) . ')' : ''; ?></strong></div>
            <div class="summary-item wide"><span>Issue</span><strong><?php echo e($customer['issue'] ?: '-'); ?></strong></div>
            <?php } ?>
        </div>
        <section class="dialer-workspace" data-justcall-dialer data-customer-id="<?php echo e($customer['id']); ?>">
            <div class="dialer-controls">
                <div>
                    <h3>JustCall Dialer</h3>
                    <p data-dialer-status>Loading embedded JustCall dialer...</p>
                </div>
                <div class="form-actions">
                    <button class="btn" type="button" data-sdk-call disabled>Call Customer</button>
                    <?php if (canViewFullCustomerPhone($user)) { ?>
                    <button class="btn btn-secondary" type="button" data-copy-text="<?php echo e($customer['phone_no']); ?>">Copy Number</button>
                    <?php } ?>
                </div>
            </div>
            <div id="justcall-dialer" class="justcall-dialer-frame"></div>
        </section>

        <?php if (isAdminUser($user)) { ?>
        <section class="section mt-18">
            <div class="section-header">
                <div>
                    <h2>Call History</h2>
                    <p><?php echo e(count($history)); ?> recent calls for this customer.</p>
                </div>
            </div>
            <div class="table-shell">
                <div class="table-responsive">
                    <table class="data-table customer-history-table">
                        <thead><tr><th>Status</th><th>Direction</th><th>Agent</th><th>Customer Number</th><th>JustCall Number</th><th>Duration</th><th>Recording</th><th>Started</th><th>Ended</th></tr></thead>
                        <tbody>
                        <?php if ($history) { foreach ($history as $call) { ?>
                            <tr>
                                <td><?php echo statusBadge($call['status'] ?? '-'); ?></td>
                                <td><?php echo e($call['direction'] ?? '-'); ?></td>
                                <td><?php echo e($call['justcall_agent_name'] ?: ($call['crm_agent_name'] ?? '-')); ?></td>
                                <td><?php echo e(customerPhoneDisplay($call['customer_number'] ?? '', $user)); ?></td>
                                <td><?php echo e($call['justcall_number'] ?? '-'); ?></td>
                                <td><?php echo e($call['duration'] !== null ? $call['duration'] . ' sec' : '-'); ?></td>
                                <td><?php echo $call['recording_url'] ? '<audio controls preload="none" src="' . e($call['recording_url']) . '"></audio>' : '-'; ?></td>
                                <td><?php echo e($call['start_time'] ?? $call['created_at'] ?? '-'); ?></td>
                                <td><?php echo e($call['end_time'] ?? '-'); ?></td>
                            </tr>
                        <?php }} else { ?>
                            <tr><td class="empty-state" colspan="9"><strong>No call history yet</strong><span>SDK events and JustCall webhooks will appear here after calling.</span></td></tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        <?php } ?>
    <?php } else { ?>
        <div class="empty-state"><strong>Customer not found</strong><span>The selected customer record does not exist or is no longer available.</span></div>
    <?php } ?>
</section>
<?php if ($customer) { ?>
<script type="module" src="assets/js/justcall-dialer.js"></script>
<?php } ?>
<?php renderLayoutEnd(); ?>
