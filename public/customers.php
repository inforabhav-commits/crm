<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/import.php';
require_once __DIR__ . '/../includes/ui.php';
requireLogin();
$user = currentUser();
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user['role'] === 'admin') {
    requireCsrfToken();
    try {
        $action = $_POST['form_action'] ?? '';
        if ($action === 'bulk_import') {
            if (empty($_FILES['import_file']['tmp_name'])) {
                $message = 'Please select a CSV or XLSX file to import.';
                $messageType = 'error';
            } else {
                $rows = readImportRows($_FILES['import_file']['tmp_name'], $_FILES['import_file']['name']);
                $result = importCustomersFromRows($rows, $pdo);
                $message = $result['imported'] > 0
                    ? 'Imported or updated ' . $result['imported'] . ' customers. Skipped ' . $result['skipped'] . ' invalid rows.'
                    : 'No valid customers were found in the uploaded file.';
                $messageType = $result['imported'] > 0 ? 'success' : 'warning';
            }
        } elseif ($action === 'single_customer') {
            $customerId = trim($_POST['customer_id'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $phoneNo = trim($_POST['phone_no'] ?? '');
            $email = trim($_POST['email'] ?? '');
            if (!$customerId || !$name || !$phoneNo) {
                throw new Exception('Customer ID, Name, and Phone No are required.');
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Enter a valid email address.');
            }
            $stmt = $pdo->prepare('INSERT INTO customers (customer_id, name, email, phone_no, date_of_entry, amount, plan, software, license_number, product_number, file_password, issue, payment_type, last4, assigned_agent_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE name = VALUES(name), email = VALUES(email), phone_no = VALUES(phone_no), date_of_entry = VALUES(date_of_entry), amount = VALUES(amount), plan = VALUES(plan), software = VALUES(software), license_number = VALUES(license_number), product_number = VALUES(product_number), file_password = VALUES(file_password), issue = VALUES(issue), payment_type = VALUES(payment_type), last4 = VALUES(last4), assigned_agent_id = VALUES(assigned_agent_id)');
            $stmt->execute([
                $customerId,
                $name,
                $email ?: null,
                $phoneNo,
                $_POST['date_of_entry'] ?: null,
                $_POST['amount'] ?: 0,
                trim($_POST['plan'] ?? ''),
                trim($_POST['software'] ?? ''),
                trim($_POST['license_number'] ?? ''),
                trim($_POST['product_number'] ?? ''),
                $_POST['file_password'] ?? '',
                trim($_POST['issue'] ?? ''),
                trim($_POST['payment_type'] ?? ''),
                trim($_POST['last4'] ?? ''),
                $_POST['assigned_agent_id'] ?? null ?: null,
            ]);
            $message = 'Customer saved.';
        } else {
            $message = 'Invalid customer action.';
            $messageType = 'error';
        }
    } catch (Exception $exception) {
        $message = $exception->getMessage();
        $messageType = 'error';
    } catch (PDOException $exception) {
        $message = 'Customer could not be saved. Check for duplicate customer IDs or invalid data.';
        $messageType = 'error';
    }
}

$agents = $pdo->query('SELECT id, name FROM users WHERE role = "sales_agent" ORDER BY name')->fetchAll();
if (isAdminUser($user)) {
    $customers = $pdo->query('SELECT c.*, u.name as agent_name FROM customers c LEFT JOIN users u ON u.id = c.assigned_agent_id ORDER BY c.id DESC')->fetchAll();
} else {
    $stmt = $pdo->prepare('SELECT id, customer_id, name, email, phone_no FROM customers WHERE assigned_agent_id = ? ORDER BY id DESC');
    $stmt->execute([$user['id']]);
    $customers = $stmt->fetchAll();
}
$editingCustomer = null;
if (isAdminUser($user) && isset($_GET['edit_id'])) {
    $editStmt = $pdo->prepare('SELECT * FROM customers WHERE id = ? LIMIT 1');
    $editStmt->execute([(int) $_GET['edit_id']]);
    $editingCustomer = $editStmt->fetch() ?: null;
}
?>
<?php renderLayoutStart('Customers', $user, 'customers', 'Add, import, assign, and call customers without leaving the workspace.'); ?>
<?php flashMessage($message, $messageType); ?>

<?php if ($user['role'] === 'admin') { ?>
<section class="form-panel mb-18">
    <div class="section-header">
        <div>
            <h2>Add Single Customer</h2>
            <p><?php echo $editingCustomer ? 'Edit the complete customer profile.' : 'Save one customer manually with the fields shared by the client.'; ?></p>
        </div>
    </div>
    <form method="post">
        <?php echo csrfInputFieldHtml(); ?>
        <input type="hidden" name="form_action" value="single_customer">
        <div class="grid two-col">
            <div class="field"><label for="customer_id">Customer ID</label><input id="customer_id" name="customer_id" required maxlength="50" value="<?php echo e($editingCustomer['customer_id'] ?? ''); ?>"></div>
            <div class="field"><label for="name">Name</label><input id="name" name="name" required maxlength="100" value="<?php echo e($editingCustomer['name'] ?? ''); ?>"></div>
            <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" maxlength="100" value="<?php echo e($editingCustomer['email'] ?? ''); ?>"></div>
            <div class="field"><label for="phone_no">Phone No</label><input id="phone_no" name="phone_no" required maxlength="50" inputmode="tel" value="<?php echo e($editingCustomer['phone_no'] ?? ''); ?>"></div>
            <div class="field"><label for="date_of_entry">Date</label><input id="date_of_entry" type="date" name="date_of_entry" value="<?php echo e($editingCustomer['date_of_entry'] ?? ''); ?>"></div>
            <div class="field"><label for="amount">Amount</label><input id="amount" type="number" step="0.01" min="0" name="amount" value="<?php echo e($editingCustomer['amount'] ?? ''); ?>"></div>
            <div class="field"><label for="plan">Plan</label><input id="plan" name="plan" maxlength="100" value="<?php echo e($editingCustomer['plan'] ?? ''); ?>"></div>
            <div class="field"><label for="software">Software</label><input id="software" name="software" maxlength="100" value="<?php echo e($editingCustomer['software'] ?? ''); ?>"></div>
            <div class="field"><label for="license_number">License Number</label><input id="license_number" name="license_number" maxlength="100" value="<?php echo e($editingCustomer['license_number'] ?? ''); ?>"></div>
            <div class="field"><label for="product_number">Product Number</label><input id="product_number" name="product_number" maxlength="100" value="<?php echo e($editingCustomer['product_number'] ?? ''); ?>"></div>
            <div class="field"><label for="file_password">File Password</label><input id="file_password" name="file_password" autocomplete="off" value="<?php echo e($editingCustomer['file_password'] ?? ''); ?>"></div>
            <div class="field"><label for="payment_type">Payment Type</label><select id="payment_type" name="payment_type"><?php foreach (['Cash', 'UPI', 'Card'] as $paymentType) { ?><option <?php echo (($editingCustomer['payment_type'] ?? 'Cash') === $paymentType) ? 'selected' : ''; ?>><?php echo e($paymentType); ?></option><?php } ?></select></div>
            <div class="field"><label for="last4">Last 4</label><input id="last4" name="last4" maxlength="4" pattern="[0-9]{0,4}" inputmode="numeric" value="<?php echo e($editingCustomer['last4'] ?? ''); ?>"></div>
            <div class="field"><label for="assigned_agent_id">Assign Agent</label><select id="assigned_agent_id" name="assigned_agent_id"><option value="">None</option><?php foreach ($agents as $agent) { echo '<option value="' . e($agent['id']) . '"' . (((string) ($editingCustomer['assigned_agent_id'] ?? '') === (string) $agent['id']) ? ' selected' : '') . '>' . e($agent['name']) . '</option>'; } ?></select></div>
            <div class="field"><label for="issue">Issue</label><textarea id="issue" name="issue"><?php echo e($editingCustomer['issue'] ?? ''); ?></textarea></div>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit" data-loading-text="Saving..."><?php echo $editingCustomer ? 'Update Customer' : 'Save Customer'; ?></button>
            <?php if ($editingCustomer) { ?><a class="button-link btn-secondary" href="customers.php">Cancel Edit</a><?php } ?>
        </div>
    </form>
</section>

<section class="form-panel mb-18">
    <div class="section-header">
        <div>
            <h2>Bulk CSV Import</h2>
            <p>Upload many customers at once using the same columns from the sample file.</p>
        </div>
        <a class="button-link btn-secondary" href="sample_customers.csv" download>Download CSV Sample</a>
    </div>
    <form method="post" enctype="multipart/form-data">
        <?php echo csrfInputFieldHtml(); ?>
        <input type="hidden" name="form_action" value="bulk_import">
        <div class="grid import-grid">
            <div class="field">
                <label for="import_file">CSV or XLSX File</label>
                <input id="import_file" type="file" name="import_file" accept=".csv,.xlsx" required>
                <span class="hint">Supported columns: Customer ID, Name, Email, Phone No, Date, Amount, Plan, Software, License Number, Product Number, File Password, Issue, Payment Type, Last 4.</span>
            </div>
            <div class="import-notes">
                <strong>Import rules</strong>
                <span>Existing Customer ID rows will be updated.</span>
                <span>Customer ID, Name, and Phone No are required.</span>
            </div>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit" data-loading-text="Importing...">Import Customers</button>
        </div>
    </form>
</section>
<?php } ?>

<section class="section">
    <div class="section-header">
        <div>
            <h2>Customers</h2>
            <p><?php echo e(count($customers)); ?> customer records available.</p>
        </div>
        <div class="toolbar">
            <input class="search-input" type="search" placeholder="<?php echo canViewFullCustomerPhone($user) ? 'Search customers, phone, plan' : 'Search customers or email'; ?>" data-table-search="customers">
            <?php if (isAdminUser($user)) { ?>
            <select class="filter-select" data-table-filter="customers" data-field="agent">
                <option value="">All agents</option>
                <?php foreach ($agents as $agent) { ?><option value="<?php echo e($agent['name']); ?>"><?php echo e($agent['name']); ?></option><?php } ?>
            </select>
            <?php } ?>
        </div>
    </div>
    <div class="table-shell">
        <div class="table-responsive">
            <table class="data-table" data-table="customers" data-page-size="10">
                <?php if (isAdminUser($user)) { ?>
                <thead><tr><th data-sort>ID</th><th data-sort>Name</th><th data-sort>Email</th><th data-sort>Phone</th><th data-sort>Agent</th><th data-sort>Plan</th><th data-sort>Software</th><th data-sort>Amount</th><th>Action</th></tr></thead>
                <?php } else { ?>
                <thead><tr><th data-sort>Name</th><th data-sort>Email</th><th data-sort>Phone</th><th>Action</th></tr></thead>
                <?php } ?>
        <tbody>
            <?php if ($customers) { foreach ($customers as $customer) { ?>
                <?php if (isAdminUser($user)) { ?>
                <tr>
                    <td><?php echo e($customer['customer_id']); ?></td>
                    <td><?php echo e($customer['name']); ?></td>
                    <td><?php echo e($customer['email'] ?? '-'); ?></td>
                    <td><?php echo e(customerPhoneDisplay($customer['phone_no'], $user)); ?></td>
                    <td data-field="agent" data-value="<?php echo e($customer['agent_name'] ?? ''); ?>"><?php echo e($customer['agent_name'] ?? '-'); ?></td>
                    <td><?php echo e($customer['plan'] ?: '-'); ?></td>
                    <td><?php echo e($customer['software'] ?: '-'); ?></td>
                    <td><?php echo e(moneyValue($customer['amount'])); ?></td>
                    <td><a class="button-link" href="call.php?customer_id=<?php echo e($customer['id']); ?>">Call</a> <a class="button-link btn-secondary" href="customers.php?edit_id=<?php echo e($customer['id']); ?>">Edit</a></td>
                </tr>
                <?php } else { ?>
                <tr>
                    <td><?php echo e($customer['name']); ?></td>
                    <td><?php echo e($customer['email'] ?? '-'); ?></td>
                    <td><?php echo e(customerPhoneDisplay($customer['phone_no'], $user)); ?></td>
                    <td><a class="button-link" href="call.php?customer_id=<?php echo e($customer['id']); ?>">Call</a></td>
                </tr>
                <?php } ?>
            <?php }} else { ?>
                <tr><td class="empty-state" colspan="<?php echo isAdminUser($user) ? 9 : 4; ?>"><strong>No customers yet</strong><span>Add a customer or import a file to get started.</span></td></tr>
            <?php } ?>
        </tbody>
            </table>
        </div>
    </div>
</section>
<?php renderLayoutEnd(); ?>
