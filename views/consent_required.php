<?php
/**
 * BOS-Score – Erneute Datenschutz-Zustimmung
 * Wird angezeigt wenn PRIVACY_VERSION sich geändert hat.
 */

$user = get_logged_in_user();
if (!$user) {
    redirect(get_base_url() . '/index.php?login');
}

$returnTo = $_GET['return_to'] ?? 'index.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'grant_consent') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Ungültige Anfrage.';
    } elseif (empty($_POST['privacy_consent'])) {
        $error = 'Bitte bestätige die Datenschutzerklärung.';
    } else {
        log_consent($user['id'], PRIVACY_VERSION, hash_value($_SERVER['REMOTE_ADDR'] ?? ''));
        audit_log(null, $user['id'], 'consent_renewed', "Datenschutz v" . PRIVACY_VERSION . " zugestimmt");
        redirect($returnTo);
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datenschutz-Aktualisierung – <?= e(APP_NAME) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
<div class="max-w-md w-full">
    <div class="bg-white rounded-2xl shadow-xl p-8">
        <div class="text-center mb-6">
            <div class="text-4xl mb-3">🔒</div>
            <h1 class="text-xl font-bold text-gray-900">Datenschutzerklärung aktualisiert</h1>
        </div>

        <p class="text-gray-600 text-sm mb-4">
            Unsere Datenschutzerklärung wurde auf <strong>Version <?= e(PRIVACY_VERSION) ?></strong> aktualisiert.
            Bitte lies die aktuelle Fassung und bestätige deine Zustimmung, um fortzufahren.
        </p>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4">
                <p class="text-red-700 text-sm">❌ <?= e($error) ?></p>
            </div>
        <?php endif; ?>

        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
            <a href="index.php?privacy" target="_blank" class="text-blue-700 text-sm font-semibold hover:text-blue-800">
                📄 Datenschutzerklärung lesen (öffnet in neuem Tab) →
            </a>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="grant_consent">
            <?= csrf_field() ?>

            <div class="flex items-start gap-2 mb-6">
                <input type="checkbox" id="privacy_consent" name="privacy_consent" value="1" required
                       class="mt-1 rounded border-gray-300 text-red-600 focus:ring-red-500">
                <label for="privacy_consent" class="text-xs text-gray-600">
                    Ich habe die aktualisierte Datenschutzerklärung (Version <?= e(PRIVACY_VERSION) ?>)
                    gelesen und stimme der Verarbeitung meiner Daten zu.
                </label>
            </div>

            <button type="submit" class="w-full bg-red-600 text-white font-semibold py-3 rounded-xl hover:bg-red-700 transition">
                Zustimmen und fortfahren
            </button>
        </form>

        <div class="text-center mt-4">
            <a href="index.php?logout" class="text-sm text-gray-500 hover:text-gray-700">Lieber abmelden</a>
        </div>
    </div>
</div>
</body>
</html>
