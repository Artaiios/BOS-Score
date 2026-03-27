<?php
/**
 * BOS-Score – Admin-Einladung annehmen
 * URL: ?admin_invite={token}
 */

$inviteToken = $_GET['admin_invite'] ?? '';
$invitation = validate_admin_invitation($inviteToken);

if (!$invitation) {
    http_response_code(400);
    $errorMessage = 'Diese Einladung ist ungültig oder wurde bereits angenommen.';
    require __DIR__ . '/partials/error.php';
    exit;
}

$eventName = $invitation['event_name'];
$eventId = (int)$invitation['event_id'];
$invitedEmail = $invitation['email'];
$accepted = false;
$needsRegistration = false;
$error = '';

// Prüfe ob der User bereits eingeloggt ist
$currentUser = get_logged_in_user();

// Prüfe ob ein Account mit der eingeladenen E-Mail existiert
$existingUser = get_user_by_email($invitedEmail);

if ($currentUser && strtolower($currentUser['email']) === strtolower($invitedEmail)) {
    // Eingeloggter User ist der Eingeladene → direkt annehmen
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'accept') {
        if (!verify_csrf($_POST['csrf_token'] ?? '')) {
            $error = 'Ungültige Anfrage.';
        } else {
            add_event_role($eventId, $currentUser['id'], 'admin', $invitation['invited_by']);
            accept_admin_invitation($invitation['id']);
            audit_log($eventId, $currentUser['id'], 'admin_invite_accepted', "Admin-Einladung angenommen: {$currentUser['display_name']}");
            $accepted = true;
        }
    }
} elseif ($existingUser) {
    // Account existiert, aber nicht eingeloggt → Login anfordern
    $needsLogin = true;
} else {
    // Kein Account → Registrierungsformular
    $needsRegistration = true;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register_admin') {
        if (!verify_csrf($_POST['csrf_token'] ?? '')) {
            $error = 'Ungültige Anfrage.';
        } else {
            $name = trim($_POST['name'] ?? '');
            $privacyOk = !empty($_POST['privacy_consent']);

            if (empty($name)) $error = 'Bitte gib deinen Namen ein.';
            elseif (!$privacyOk) $error = 'Bitte bestätige die Datenschutzerklärung.';

            if (empty($error)) {
                require_once __DIR__ . '/../lib/mail.php';

                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $userId = create_user_account($invitedEmail, $name);
                log_consent($userId, PRIVACY_VERSION, hash_value($ip));
                add_event_role($eventId, $userId, 'admin', $invitation['invited_by']);
                accept_admin_invitation($invitation['id']);

                // Magic Link senden
                $token = create_magic_link($userId, 'registration', false);
                send_magic_link_mail($invitedEmail, $name, $token, 'registration');

                audit_log($eventId, $userId, 'admin_invite_accepted', "Admin-Einladung angenommen (neuer Account): $name");
                $accepted = true;
                $needsRegistration = false;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin-Einladung – <?= e(APP_NAME) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
<div class="max-w-md w-full">
    <div class="bg-white rounded-2xl shadow-xl p-8">
        <div class="text-center mb-6">
            <div class="text-4xl mb-3">🔑</div>
            <h1 class="text-xl font-bold text-gray-900">Admin-Einladung</h1>
            <p class="text-gray-600 mt-1">Event: <strong><?= e($eventName) ?></strong></p>
        </div>

        <?php if ($accepted): ?>
            <div class="bg-green-50 border border-green-200 rounded-xl p-6 text-center">
                <h2 class="font-bold text-green-800 text-lg mb-2">✅ Einladung angenommen!</h2>
                <?php if ($currentUser): ?>
                    <p class="text-green-700 text-sm">Du bist jetzt Event-Admin für „<?= e($eventName) ?>".</p>
                    <a href="index.php" class="inline-block mt-4 bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition text-sm font-semibold">
                        Zur Übersicht
                    </a>
                <?php else: ?>
                    <p class="text-green-700 text-sm">
                        Prüfe dein E-Mail-Postfach — wir haben dir einen Anmeldelink an
                        <strong><?= e($invitedEmail) ?></strong> gesendet.
                    </p>
                <?php endif; ?>
            </div>

        <?php elseif (!empty($needsLogin)): ?>
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-6">
                <p class="text-blue-700 text-sm mb-4">
                    Die Einladung richtet sich an <strong><?= e($invitedEmail) ?></strong>.
                    Bitte melde dich mit diesem Account an.
                </p>
                <a href="index.php?login&return_to=<?= urlencode('index.php?admin_invite=' . $inviteToken) ?>"
                   class="block w-full bg-blue-600 text-white text-center font-semibold py-3 rounded-xl hover:bg-blue-700 transition">
                    Anmelden
                </a>
            </div>

        <?php elseif ($needsRegistration): ?>
            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
                    <p class="text-red-700 text-sm">❌ <?= e($error) ?></p>
                </div>
            <?php endif; ?>

            <p class="text-gray-600 text-sm mb-4">
                Du wurdest als Admin für dieses Event eingeladen. Erstelle einen Account um fortzufahren.
            </p>

            <form method="POST">
                <input type="hidden" name="action" value="register_admin">
                <?= csrf_field() ?>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">E-Mail-Adresse</label>
                        <input type="email" readonly value="<?= e($invitedEmail) ?>"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-500">
                    </div>

                    <div>
                        <label for="name" class="block text-sm font-semibold text-gray-700 mb-1">Dein Name</label>
                        <input type="text" id="name" name="name" required autofocus
                               placeholder="Vor- und Nachname"
                               value="<?= e($_POST['name'] ?? '') ?>"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none">
                    </div>

                    <div class="flex items-start gap-2">
                        <input type="checkbox" id="privacy_consent" name="privacy_consent" value="1" required
                               class="mt-1 rounded border-gray-300 text-red-600 focus:ring-red-500">
                        <label for="privacy_consent" class="text-xs text-gray-600">
                            Ich habe die <a href="index.php?privacy" class="text-red-600 underline" target="_blank">Datenschutzerklärung</a>
                            gelesen und stimme der Verarbeitung meiner Daten zu.
                        </label>
                    </div>
                </div>

                <button type="submit" class="w-full mt-6 bg-red-600 text-white font-semibold py-3 rounded-xl hover:bg-red-700 transition">
                    Account erstellen & Einladung annehmen
                </button>
            </form>

        <?php elseif ($currentUser): ?>
            <!-- Eingeloggt, richtige E-Mail → Bestätigung -->
            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
                    <p class="text-red-700 text-sm">❌ <?= e($error) ?></p>
                </div>
            <?php endif; ?>

            <p class="text-gray-600 text-sm mb-4">
                Du wurdest als <strong>Event-Admin</strong> für „<?= e($eventName) ?>" eingeladen.
                Möchtest du die Einladung annehmen?
            </p>

            <form method="POST">
                <input type="hidden" name="action" value="accept">
                <?= csrf_field() ?>
                <button type="submit" class="w-full bg-red-600 text-white font-semibold py-3 rounded-xl hover:bg-red-700 transition">
                    ✅ Einladung annehmen
                </button>
            </form>

            <div class="text-center mt-4">
                <a href="index.php" class="text-sm text-gray-500 hover:text-gray-700">Abbrechen</a>
            </div>
        <?php endif; ?>
    </div>

    <p class="text-center text-gray-400 text-xs mt-4">
        <a href="index.php?privacy" class="hover:text-gray-600">Datenschutz</a>
        · <?= e(APP_NAME) ?> v<?= APP_VERSION ?>
    </p>
</div>
</body>
</html>
