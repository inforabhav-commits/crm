<?php
function justcallConfig() {
    $config = require __DIR__ . '/../config/justcall.php';
    $localPath = __DIR__ . '/../config/justcall.local.php';
    if (is_file($localPath)) {
        $config = array_merge($config, require $localPath);
    }
    global $pdo;
    if (isset($pdo) && $pdo instanceof PDO) {
        try {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM app_settings WHERE setting_key = 'justcall_call_mode'");
            foreach ($stmt->fetchAll() as $setting) {
                if ($setting['setting_key'] === 'justcall_call_mode' && trim((string) $setting['setting_value']) !== '') {
                    $config['call_mode'] = trim((string) $setting['setting_value']);
                }
            }
        } catch (PDOException $exception) {
        }
    }
    return $config;
}

function justcallRequest($method, $path, $payload = [], $timeout = 20) {
    $config = justcallConfig();
    $apiKey = trim($config['api_key'] ?? '');
    $secret = trim($config['api_secret'] ?? '');

    if ($apiKey === '' || $secret === '') {
        return [
            'http_code' => 0,
            'body' => null,
            'raw_body' => null,
            'error' => 'JustCall API credentials are not configured.',
        ];
    }

    $url = rtrim($config['base_url'] ?? 'https://api.justcall.io', '/') . $path;
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    if (($config['auth_mode'] ?? 'basic') === 'raw') {
        $headers[] = 'Authorization: ' . $apiKey . ':' . $secret;
    } else {
        $headers[] = 'Authorization: Basic ' . base64_encode($apiKey . ':' . $secret);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if ($method !== 'GET' && !empty($payload)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = $response ? json_decode($response, true) : null;

    return [
        'http_code' => $httpCode,
        'body' => $decoded,
        'raw_body' => $decoded === null ? $response : null,
        'error' => $error ?: null,
    ];
}

function justcallErrorMessage($result) {
    if (!empty($result['error'])) {
        return $result['error'];
    }

    $httpCode = (int) ($result['http_code'] ?? 0);
    if ($httpCode === 0) {
        return 'No response was received from JustCall.';
    }

    $body = $result['body'] ?? null;
    if (is_array($body)) {
        $message = $body['message'] ?? $body['error'] ?? $body['errors'][0]['message'] ?? null;
        if ($message) {
            return 'JustCall returned HTTP ' . $httpCode . ': ' . $message;
        }
    }

    $rawBody = trim(strip_tags((string) ($result['raw_body'] ?? '')));
    if ($rawBody !== '') {
        $rawBody = preg_replace('/\s+/', ' ', $rawBody);
        return 'JustCall returned HTTP ' . $httpCode . ': ' . substr($rawBody, 0, 180);
    }

    return 'JustCall returned HTTP ' . $httpCode . '.';
}

function justcallDialerUrl($phoneNumber, $metadata = []) {
    $phoneNumber = trim((string) $phoneNumber);
    $params = ['numbers' => $phoneNumber];
    if (!empty($metadata)) {
        $params['medium'] = 'custom';
        $params['metadata'] = json_encode($metadata);
        $params['metadata_type'] = 'json';
    }
    return 'https://app.justcall.io/dialer?' . http_build_query($params);
}

function justcallAddContactToSalesDialer($campaignId, $customer) {
    if (!$campaignId) {
        return [
            'http_code' => 0,
            'body' => null,
            'raw_body' => null,
            'error' => 'JustCall Sales Dialer Campaign ID is required.',
        ];
    }

    return justcallRequest('POST', '/v2.1/sales_dialer/campaigns/contact', [
        'campaign_id' => (int) $campaignId,
        'phone_number' => $customer['phone_no'] ?? '',
        'name' => $customer['name'] ?? '',
        'custom_fields' => [
            ['name' => 'customer_id', 'value' => (string) ($customer['customer_id'] ?? '')],
            ['name' => 'plan', 'value' => (string) ($customer['plan'] ?? '')],
            ['name' => 'software', 'value' => (string) ($customer['software'] ?? '')],
            ['name' => 'issue', 'value' => (string) ($customer['issue'] ?? '')],
        ],
    ]);
}

function justcallCreateCall($phoneNumber, $customer = []) {
    return [
        'http_code' => 0,
        'body' => null,
        'raw_body' => null,
        'error' => 'Calls must be initiated from the browser using the embedded JustCall Dialer SDK. The backend no longer starts calls through JustCall voice agents.',
    ];
}

function justcallInitiateAiVoiceCall($phoneNumber, $customer = []) {
    return [
        'http_code' => 410,
        'body' => null,
        'raw_body' => null,
        'error' => 'The JustCall AI Voice Agent flow has been disabled. Use the embedded JustCall Dialer SDK for human agents.',
    ];
}

function justcallCreateUser($name, $email) {
    return justcallRequest('POST', '/v1/users', [
        'name' => $name,
        'email' => $email,
    ]);
}

function justcallListUsers() {
    return justcallRequest('GET', '/v2.1/users');
}

function justcallListPhoneNumbers() {
    return justcallRequest('GET', '/v2.1/phone-numbers');
}

function findJustCallUser($name, $email) {
    $usersResponse = justcallListUsers();
    if ($usersResponse['http_code'] < 200 || $usersResponse['http_code'] >= 300) {
        return [
            'user' => null,
            'error' => justcallErrorMessage($usersResponse),
        ];
    }

    $entries = extractJustCallEntries($usersResponse['body'] ?? null);
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $entryEmail = strtolower(trim($entry['email'] ?? $entry['user_email'] ?? $entry['username'] ?? ''));
        $entryName = strtolower(trim($entry['name'] ?? $entry['full_name'] ?? ''));
        if ($entryEmail === strtolower(trim($email)) || $entryName === strtolower(trim($name))) {
            return ['user' => $entry, 'error' => null];
        }
    }

    return ['user' => null, 'error' => null];
}

function extractJustCallUserNumbers($entry) {
    if (!is_array($entry)) {
        return [];
    }

    $numbers = [];
    foreach (['owned_numbers', 'shared_numbers'] as $key) {
        if (isset($entry[$key]) && is_array($entry[$key])) {
            foreach ($entry[$key] as $number) {
                $numbers[] = (string) $number;
            }
        }
    }

    return array_slice(array_unique(array_filter($numbers)), 0, 3);
}

function extractJustCallEntries($body) {
    if (!is_array($body)) {
        return [];
    }

    if (isset($body['data']) && is_array($body['data'])) {
        return $body['data'];
    }

    if (isset($body['users']) && is_array($body['users'])) {
        return $body['users'];
    }

    if (isset($body['result']) && is_array($body['result'])) {
        return $body['result'];
    }

    if (isset($body['items']) && is_array($body['items'])) {
        return $body['items'];
    }

    return is_array($body) ? array_values(array_filter($body, 'is_array')) : [];
}

function extractJustCallPhoneNumbers($body) {
    $items = [];
    if (!is_array($body)) {
        return [];
    }

    if (isset($body['data']) && is_array($body['data'])) {
        $items = $body['data'];
    } elseif (isset($body['phone_numbers']) && is_array($body['phone_numbers'])) {
        $items = $body['phone_numbers'];
    } elseif (isset($body['phones']) && is_array($body['phones'])) {
        $items = $body['phones'];
    } elseif (isset($body['items']) && is_array($body['items'])) {
        $items = $body['items'];
    } else {
        $items = array_values(array_filter($body, 'is_array'));
    }

    $numbers = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $candidate = $item['phone_number'] ?? $item['justcall_number'] ?? $item['number'] ?? $item['value'] ?? $item['phone'] ?? null;
        if ($candidate) {
            $numbers[] = (string) $candidate;
        }
    }

    return array_slice(array_unique($numbers), 0, 3);
}
