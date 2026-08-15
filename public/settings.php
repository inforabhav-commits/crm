<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/ui.php';
requireAdmin();
$user = currentUser();
$message = '';
$messageType = 'success';
$justcallConfig = justcallConfig();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    setAppSetting('justcall_call_mode', 'dialer_sdk');
    $message = 'JustCall settings saved.';
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$webhookUrl = $scheme . '://' . $host . $basePath . '/webhook.php';
?>
<?php renderLayoutStart('Settings', $user, 'settings', 'Configure JustCall calling.'); ?>
<?php flashMessage($message, $messageType); ?>

<section class="form-panel mb-18">
    <div class="section-header">
        <div>
            <h2>JustCall Dialer SDK</h2>
            <p>Human agents call from the embedded JustCall Dialer SDK inside CRM. Final call details come from JustCall v2 webhooks.</p>
        </div>
        <?php echo statusBadge('available'); ?>
    </div>
    <form method="post">
        <?php echo csrfInputFieldHtml(); ?>
        <div class="grid two-col">
            <div class="field">
                <label for="justcall_webhook_url">CRM Webhook URL</label>
                <input id="justcall_webhook_url" value="<?php echo e($webhookUrl); ?>" readonly>
                <span class="hint">Add this URL in JustCall v2 webhooks for Call completed in JustCall and Call updated in JustCall.</span>
            </div>
            <div class="import-notes">
                <strong>Calling status</strong>
                <span>Human agents call from the embedded JustCall Dialer SDK inside the CRM.</span>
                <span>Final call metadata is received from JustCall webhook events and local SDK logging.</span>
            </div>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit" data-loading-text="Saving...">Save Settings</button>
            <button class="btn btn-secondary" type="button" data-copy-text="<?php echo e($webhookUrl); ?>">Copy Webhook URL</button>
        </div>
    </form>
</section>
<?php renderLayoutEnd(); ?>
