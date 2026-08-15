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

http_response_code(410);
echo json_encode([
    'ok' => false,
    'message' => 'This endpoint is deprecated. Use the embedded JustCall Dialer SDK on the call page for human agent calls.',
]);
