<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireCsrfToken();
$user = currentUser();

header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid payload.']);
    exit;
}

$customerId = (int) ($data['customer_id'] ?? 0);
if (!$customerId) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Customer is required.']);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ? LIMIT 1');
$stmt->execute([$customerId]);
$customer = $stmt->fetch();
if (!$customer) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Customer not found.']);
    exit;
}

if (!canAccessCustomer($customer, $user)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'You are not allowed to call this customer.']);
    exit;
}

$currentUserRow = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$currentUserRow->execute([$user['id']]);
$currentUserRow = $currentUserRow->fetch() ?: $user;

$pdo->prepare('INSERT INTO call_logs (customer_id, agent_id, direction, customer_number, justcall_agent_id, justcall_agent_name, status, start_time, payload) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)')->execute([
    $customer['id'],
    $user['id'],
    'outbound',
    $customer['phone_no'],
    $currentUserRow['justcall_user_id'] ?? null,
    $currentUserRow['name'] ?? null,
    'initiated',
    json_encode(['source' => 'embedded_dialer']),
]);

echo json_encode([
    'ok' => true,
    'phone_number' => $customer['phone_no'],
]);
