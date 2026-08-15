<?php
require_once __DIR__ . '/../config/database.php';

function pickValue($items, $default = null) {
    foreach ($items as $item) {
        if ($item !== null && $item !== '') {
            return $item;
        }
    }
    return $default;
}

function verifyJustCallWebhook($config, $payload) {
    $secret = trim((string) ($config['webhook_secret'] ?? ''));
    if ($secret === '') {
        return true;
    }

    $signature = null;
    if (!empty($_SERVER['HTTP_X_JUSTCALL_SIGNATURE'])) {
        $signature = $_SERVER['HTTP_X_JUSTCALL_SIGNATURE'];
    } elseif (!empty($_SERVER['HTTP_X_HUB_SIGNATURE'])) {
        $signature = $_SERVER['HTTP_X_HUB_SIGNATURE'];
    } elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $parts = preg_split('/\s+/', trim($_SERVER['HTTP_AUTHORIZATION']));
        if (count($parts) === 2 && strtolower($parts[0]) === 'bearer') {
            $signature = $parts[1];
        }
    }

    if ($signature === null) {
        return false;
    }

    $computed = hash_hmac('sha256', $payload, $secret);
    return hash_equals($computed, $signature);
}

function normalizeJustCallEventStatus($eventType, $callInfo) {
    $type = strtolower((string) ($callInfo['type'] ?? ''));
    if ($type) {
        return $type;
    }

    $eventMap = [
        'call.incoming' => 'incoming',
        'call.answered' => 'answered',
        'call.missed' => 'missed',
        'call.voicemail' => 'voicemail',
        'call.initiated' => 'initiated',
        'call.completed' => 'completed',
        'call.updated' => 'updated',
        'sales_dialer.call.completed' => 'completed',
        'sales_dialer.call.updated' => 'updated',
    ];

    return $eventMap[$eventType] ?? $eventType;
}

function combineJustCallDateTime($date, $time) {
    $date = trim((string) $date);
    $time = trim((string) $time);
    if ($date === '' || $time === '') {
        return null;
    }

    $timestamp = strtotime($date . ' ' . $time);
    return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
}

function upsertCallLogFromJustCallPayload($pdo, $data, $input) {
    $eventType = $data['event'] ?? $data['type'] ?? '';
    $call = $data['data'] ?? $data['call'] ?? $data;
    $callInfo = $call['call_info'] ?? [];
    $duration = $call['call_duration'] ?? [];

    $callSid = pickValue([
        $call['call_sid'] ?? null,
        $call['id'] ?? null,
        $data['call_sid'] ?? null,
    ]);
    $customerNumber = pickValue([
        $call['contact_number'] ?? null,
        $data['contact_number'] ?? null,
        $data['phone_number'] ?? null,
    ]);
    $agentEmail = pickValue([
        $call['agent_email'] ?? null,
        $data['agent_email'] ?? null,
        $data['user']['email'] ?? null,
    ]);

    $customerId = null;
    if ($customerNumber) {
        $stmt = $pdo->prepare('SELECT id FROM customers WHERE REPLACE(REPLACE(phone_no, "+", ""), " ", "") = REPLACE(REPLACE(?, "+", ""), " ", "") LIMIT 1');
        $stmt->execute([$customerNumber]);
        $customer = $stmt->fetch();
        $customerId = $customer['id'] ?? null;
    }

    $agentId = null;
    if ($agentEmail) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$agentEmail]);
        $agent = $stmt->fetch();
        $agentId = $agent['id'] ?? null;
    }

    $fields = [
        'customer_id' => $customerId,
        'agent_id' => $agentId,
        'call_sid' => $callSid,
        'direction' => $callInfo['direction'] ?? $call['direction'] ?? null,
        'customer_number' => $customerNumber,
        'justcall_number' => $call['justcall_number'] ?? null,
        'justcall_agent_id' => isset($call['agent_id']) ? (string) $call['agent_id'] : null,
        'justcall_agent_name' => $call['agent_name'] ?? null,
        'status' => normalizeJustCallEventStatus($eventType, $callInfo),
        'duration' => $duration['total_duration'] ?? $duration['conversation_time'] ?? $data['duration'] ?? null,
        'start_time' => combineJustCallDateTime($call['call_date'] ?? null, $call['call_time'] ?? null),
        'recording_url' => $callInfo['recording'] ?? $callInfo['recording_child'] ?? $data['recording_url'] ?? null,
        'disposition' => $callInfo['disposition'] ?? $data['disposition'] ?? null,
        'payload' => $input,
    ];
    if ($fields['start_time'] && $fields['duration'] !== null) {
        $fields['end_time'] = date('Y-m-d H:i:s', strtotime($fields['start_time']) + (int) $fields['duration']);
    } else {
        $fields['end_time'] = null;
    }

    if ($callSid) {
        $stmt = $pdo->prepare('SELECT id FROM call_logs WHERE call_sid = ? LIMIT 1');
        $stmt->execute([$callSid]);
        $existing = $stmt->fetch();
        if ($existing) {
            $pdo->prepare('UPDATE call_logs SET customer_id = COALESCE(?, customer_id), agent_id = COALESCE(?, agent_id), direction = COALESCE(?, direction), customer_number = COALESCE(?, customer_number), justcall_number = COALESCE(?, justcall_number), justcall_agent_id = COALESCE(?, justcall_agent_id), justcall_agent_name = COALESCE(?, justcall_agent_name), status = COALESCE(?, status), duration = COALESCE(?, duration), start_time = COALESCE(?, start_time), end_time = COALESCE(?, end_time), recording_url = COALESCE(?, recording_url), disposition = COALESCE(?, disposition), payload = ? WHERE id = ?')->execute([
                $fields['customer_id'],
                $fields['agent_id'],
                $fields['direction'],
                $fields['customer_number'],
                $fields['justcall_number'],
                $fields['justcall_agent_id'],
                $fields['justcall_agent_name'],
                $fields['status'],
                $fields['duration'],
                $fields['start_time'],
                $fields['end_time'],
                $fields['recording_url'],
                $fields['disposition'],
                $fields['payload'],
                $existing['id'],
            ]);
            return;
        }
    }

    $pdo->prepare('INSERT INTO call_logs (customer_id, agent_id, call_sid, direction, customer_number, justcall_number, justcall_agent_id, justcall_agent_name, status, duration, start_time, end_time, recording_url, disposition, payload) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([
        $fields['customer_id'],
        $fields['agent_id'],
        $fields['call_sid'],
        $fields['direction'],
        $fields['customer_number'],
        $fields['justcall_number'],
        $fields['justcall_agent_id'],
        $fields['justcall_agent_name'],
        $fields['status'],
        $fields['duration'],
        $fields['start_time'],
        $fields['end_time'],
        $fields['recording_url'],
        $fields['disposition'],
        $fields['payload'],
    ]);
}

$input = file_get_contents('php://input');
$config = require __DIR__ . '/../config/justcall.php';

if (!verifyJustCallWebhook($config, $input)) {
    http_response_code(401);
    exit('Invalid webhook signature');
}

$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    exit('Invalid payload');
}

$eventType = $data['event'] ?? $data['type'] ?? '';

if (strpos($eventType, 'call.') !== false || strpos($eventType, 'sales_dialer') !== false) {
    upsertCallLogFromJustCallPayload($pdo, $data, $input);
}

if ($eventType === 'user.status.updated') {
    $agentEmail = $data['user']['email'] ?? null;
    if ($agentEmail) {
        $pdo->prepare('UPDATE users SET status = ? WHERE email = ?')->execute([
            $data['status'] ?? 'offline',
            $agentEmail
        ]);
    }
}

echo 'ok';
