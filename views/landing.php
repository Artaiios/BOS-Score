<?php
/**
 * BOS-Score – Event-Landing-Page
 * Wird angezeigt wenn ein nicht eingeloggter User ?event={token} aufruft.
 */

$eventName = $event['name'];
$orgName = get_organization_name($event);
$themeColor = $event['theme_primary'] ?? '#dc2626';

// Prüfe ob ein aktiver Einladungslink existiert
$invitations = get_event_invitations($event['id']);
$activeInvitation = null;
foreach ($invitations as $inv) {
    if ($inv['invalidated_at'] === null) {
        $validated = validate_event_invitation($inv['token']);
        if ($validated) {
            $activeInvitation = $inv;
            break;
        }
    }
}
$returnTo = 'index.php?event=' . urlencode($eventToken);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($eventName) ?> – <?= e(APP_NAME) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>:root { --color-primary: <?= e($themeColor) ?>; }</style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
<div class="max-w-md w-full">
    <div class="bg-white rounded-2xl shadow-xl p-8 text-center">
        <div class="text-5xl mb-4">🔒</div>
        <h1 class="text-xl font-bold text-gray-900"><?= e($eventName) ?></h1>
        <p class="text-gray-500 mt-1"><?= e($orgName) ?></p>

        <p class="text-gray-600 text-sm mt-6">
            Dieses Event erfordert eine Anmeldung.
        </p>

        <div class="mt-6 space-y-3">
            <a href="index.php?login&return_to=<?= urlencode($returnTo) ?>"
               class="block w-full text-white font-semibold py-3 rounded-xl hover:opacity-90 transition"
               style="background-color: var(--color-primary);">
                🔐 Anmelden
            </a>

            <?php if ($activeInvitation): ?>
                <a href="index.php?invite=<?= e($activeInvitation['token']) ?>"
                   class="block w-full bg-gray-100 text-gray-700 font-semibold py-3 rounded-xl hover:bg-gray-200 transition">
                    📋 Registrieren
                </a>
            <?php endif; ?>
        </div>

        <?php if (!$activeInvitation): ?>
            <p class="text-xs text-gray-400 mt-4">
                Noch kein Account? Bitte wende dich an den Event-Verantwortlichen für einen Einladungslink.
            </p>
        <?php endif; ?>
    </div>

    <p class="text-center text-gray-400 text-xs mt-4">
        <a href="index.php?privacy" class="hover:text-gray-600">Datenschutz</a>
        · <?= e(APP_NAME) ?> v<?= APP_VERSION ?>
    </p>
</div>
</body>
</html>
