<?php
/**
 * BOS-Score – Fehlerseite
 * Erwartet: $errorMessage (string), optional $errorAction (HTML-Button)
 */

$appName = defined('APP_NAME') ? APP_NAME : 'BOS-Score';
$version = defined('APP_VERSION') ? APP_VERSION : '';
$adminEmail = '';
try {
    if (function_exists('get_server_config')) {
        $adminEmail = get_server_config('admin_email', '');
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fehler – <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
<div class="max-w-md w-full">
    <div class="bg-white rounded-2xl shadow-xl p-8 text-center">
        <div class="text-5xl mb-4">😕</div>
        <h1 class="text-xl font-bold text-gray-900 mb-3">Etwas ist schiefgelaufen</h1>
        <p class="text-gray-600 text-sm mb-6">
            <?= htmlspecialchars($errorMessage ?? 'Ein unbekannter Fehler ist aufgetreten.', ENT_QUOTES, 'UTF-8') ?>
        </p>

        <?php if (!empty($errorAction)): ?>
            <div class="mb-4"><?= $errorAction ?></div>
        <?php endif; ?>

        <a href="index.php" class="inline-block text-sm text-gray-500 hover:text-gray-700">← Zur Startseite</a>

        <?php if ($adminEmail): ?>
            <p class="text-xs text-gray-400 mt-6">
                Problem besteht weiterhin?
                <a href="mailto:<?= htmlspecialchars($adminEmail, ENT_QUOTES, 'UTF-8') ?>" class="text-red-600 hover:text-red-700">
                    Administrator kontaktieren
                </a>
            </p>
        <?php endif; ?>
    </div>

    <p class="text-center text-gray-400 text-xs mt-4">
        <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?>
        <?php if ($version): ?> v<?= $version ?><?php endif; ?>
    </p>
</div>
</body>
</html>
