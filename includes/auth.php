<?php
function configureSession() {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') === '443';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'] ?? '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function sendSecurityHeaders() {
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: microphone=(), camera=(), geolocation=()');
    header('X-XSS-Protection: 1; mode=block');
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') === '443') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
}

configureSession();
session_start();
sendSecurityHeaders();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/justcall.php';

function currentUser() {
    return $_SESSION['user'] ?? null;
}

function requestHeader($name) {
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return $_SERVER[$key] ?? null;
}

function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfInputFieldHtml() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function validateCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || !is_string($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function requireCsrfToken($token = null) {
    if ($token === null) {
        if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
        } elseif (!empty($_POST['csrf_token'])) {
            $token = $_POST['csrf_token'];
        }
    }

    if (!validateCsrfToken((string) $token)) {
        $isJsonRequest = false;
        $contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
        if (strpos($contentType, 'application/json') !== false) {
            $isJsonRequest = true;
        }
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            $isJsonRequest = true;
        }

        http_response_code(400);
        if ($isJsonRequest) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }

        exit('Invalid CSRF token');
    }
}

function isLoginBlocked() {
    if (!empty($_SESSION['login_blocked_until']) && time() < $_SESSION['login_blocked_until']) {
        return true;
    }
    return false;
}

function recordLoginAttempt($success) {
    if ($success) {
        unset($_SESSION['login_attempts'], $_SESSION['login_blocked_until']);
        return;
    }

    $attempts = (int) ($_SESSION['login_attempts'] ?? 0);
    $attempts++;
    $_SESSION['login_attempts'] = $attempts;

    if ($attempts >= 6) {
        $_SESSION['login_blocked_until'] = time() + 300;
    }
}

function getLoginBlockMessage() {
    if (!empty($_SESSION['login_blocked_until']) && time() < $_SESSION['login_blocked_until']) {
        $remaining = $_SESSION['login_blocked_until'] - time();
        return 'Too many failed login attempts. Try again in ' . ceil($remaining / 60) . ' minute(s).';
    }
    return '';
}

function requireLogin() {
    if (!currentUser()) {
        header('Location: login.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if ((currentUser()['role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('Forbidden');
    }
}

function isAdminUser($user = null) {
    $user = $user ?: currentUser();
    return ($user['role'] ?? '') === 'admin';
}

function isSalesAgentUser($user = null) {
    $user = $user ?: currentUser();
    return ($user['role'] ?? '') === 'sales_agent';
}

function canAccessCustomer($customer, $user = null) {
    $user = $user ?: currentUser();
    if (!$user || !$customer) {
        return false;
    }

    if (isAdminUser($user)) {
        return true;
    }

    if (isSalesAgentUser($user)) {
        return (int) ($customer['assigned_agent_id'] ?? 0) === (int) ($user['id'] ?? 0);
    }

    return false;
}

function requireCustomerAccess($customer, $user = null) {
    if (!canAccessCustomer($customer, $user)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function syncAgentJustCallProfile($user, $pdo) {
    if (($user['role'] ?? '') !== 'sales_agent') {
        return;
    }

    $usersResponse = justcallListUsers();
    $phonesResponse = justcallListPhoneNumbers();

    $entries = extractJustCallEntries($usersResponse['body'] ?? null);
    $matched = null;
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $email = $entry['email'] ?? $entry['user_email'] ?? $entry['username'] ?? '';
        $name = $entry['name'] ?? $entry['full_name'] ?? '';
        if ($email === $user['email'] || $name === $user['name']) {
            $matched = $entry;
            break;
        }
    }

    if ($matched) {
        $justcallUserId = $matched['id'] ?? $matched['user_id'] ?? null;
        if ($justcallUserId) {
            $stmt = $pdo->prepare('UPDATE users SET justcall_user_id = ? WHERE id = ?');
            $stmt->execute([$justcallUserId, $user['id']]);
        }
    }

    $numbers = extractJustCallPhoneNumbers($phonesResponse['body'] ?? null);
    if (!empty($numbers)) {
        $stmt = $pdo->prepare('UPDATE users SET justcall_primary_number = ?, justcall_secondary_number = ?, justcall_third_number = ? WHERE id = ?');
        $stmt->execute([$numbers[0] ?? null, $numbers[1] ?? null, $numbers[2] ?? null, $user['id']]);
    }
}

function loginUser($email, $password, $pdo) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !verifyPassword($password, $user['password_hash'])) {
        recordLoginAttempt(false);
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
    ];

    recordLoginAttempt(true);
    syncAgentJustCallProfile($user, $pdo);

    return true;
}

function logoutUser() {
    session_unset();
    session_destroy();
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
}
