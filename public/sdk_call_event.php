<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireCsrfToken();
$user = currentUser();

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data) {
    http_response_code(400);
    exit('Invalid payload');
}

$customerId = (int) ($data['customer_id'] ?? 0);
$callSid = trim((string) ($data['call_sid'] ?? ''));
$status = trim((string) ($data['status'] ?? ''));
$direction = trim((string) ($data['direction'] ?? 'outbound'));
$duration = isset($data['duration']) ? (int) $data['duration'] : null;

if (!$customerId || !$status) {
    http_response_code(422);
    exit('Missing required fields');
}

$customerStmt = $pdo->prepare('SELECT * FROM customers WHERE id = ? LIMIT 1');
$customerStmt->execute([$customerId]);
$customer = $customerStmt->fetch();
if (!$customer || !canAccessCustomer($customer, $user)) {
    http_response_code(403);
    exit('Forbidden');
}
$customerNumber = $customer['phone_no'];

$currentUserRow = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$currentUserRow->execute([$user['id']]);
$currentUserRow = $currentUserRow->fetch() ?: $user;

$existing = null;
if ($callSid !== '') {
    $stmt = $pdo->prepare('SELECT id FROM call_logs WHERE call_sid = ? LIMIT 1');
    $stmt->execute([$callSid]);
    $existing = $stmt->fetch();
}

if (!$existing) {
    $stmt = $pdo->prepare('SELECT id FROM call_logs WHERE customer_id = ? AND agent_id = ? AND call_sid IS NULL ORDER BY id DESC LIMIT 1');
    $stmt->execute([$customerId, $user['id']]);
    $existing = $stmt->fetch();
}

if ($existing) {
    $pdo->prepare('UPDATE call_logs SET call_sid = COALESCE(NULLIF(?, ""), call_sid), direction = COALESCE(NULLIF(?, ""), direction), customer_number = COALESCE(NULLIF(?, ""), customer_number), justcall_agent_id = COALESCE(NULLIF(?, ""), justcall_agent_id), justcall_agent_name = COALESCE(NULLIF(?, ""), justcall_agent_name), status = ?, duration = COALESCE(?, duration), end_time = CASE WHEN ? = "ended" THEN NOW() ELSE end_time END, payload = ? WHERE id = ?')->execute([
        $callSid,
        $direction,
        $customerNumber,
        $currentUserRow['justcall_user_id'] ?? '',
        $currentUserRow['name'] ?? '',
        $status,
        $duration,
        $status,
        $input,
        $existing['id'],
    ]);
} else {
    $pdo->prepare('INSERT INTO call_logs (customer_id, agent_id, call_sid, direction, customer_number, justcall_agent_id, justcall_agent_name, status, duration, start_time, end_time, payload) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)')->execute([
        $customerId,
        $user['id'],
        $callSid ?: null,
        $direction ?: 'outbound',
        $customerNumber ?: null,
        $currentUserRow['justcall_user_id'] ?? null,
        $currentUserRow['name'] ?? null,
        $status,
        $duration,
        $status === 'ended' ? date('Y-m-d H:i:s') : null,
        $input,
    ]);
}

echo 'ok';
