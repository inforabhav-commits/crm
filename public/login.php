<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ui.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    if (isLoginBlocked()) {
        $error = getLoginBlockMessage();
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        if (loginUser($email, $password, $pdo)) {
            header('Location: index.php');
            exit;
        }
        $error = 'Invalid credentials';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CRM Login</title>
    <link rel="stylesheet" href="assets/css/app.css">
    <script defer src="assets/js/app.js"></script>
</head>
<body>
<main class="login-page">
    <section class="login-card" aria-labelledby="login-title">
        <span class="login-brand">SC</span>
        <h1 id="login-title">Sales CRM</h1>
        <p>Sign in to manage customers, agents, and call activity.</p>
        <?php flashMessage($error ?? '', 'error'); ?>
        <form method="post">
            <?php echo csrfInputFieldHtml(); ?>
            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" autocomplete="email" placeholder="admin@example.com" required>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <div class="password-wrap">
                    <input id="password" name="password" type="password" autocomplete="current-password" required>
                    <button type="button" data-password-toggle="#password">Show</button>
                </div>
            </div>
            <button class="btn" type="submit" data-loading-text="Signing in...">Login</button>
        </form>
        <p class="muted">Default admin: admin@example.com / admin123</p>
    </section>
</main>
</body>
</html>
