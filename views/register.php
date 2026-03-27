<?php
/**
 * BOS-Score – Teilnehmer-Registrierung
 * Über Einladungslink: ?invite={token}
 */

$inviteToken = $_GET['invite'] ?? '';
$invitation = validate_event_invitation($inviteToken);

if (!$invitation) {
    http_response_code(400);
    $errorMessage = 'Dieser Einladungslink ist ungültig, abgelaufen oder die Registrierung ist geschlossen.';
    require __DIR__ . '/partials/error.php';
    exit;
}

$eventName = $invitation['event_name'];
$eventId = (int)$invitation['event_id'];
$invitationId = (int)$invitation['id'];
$registered = false;
$error = '';

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Ungültige Anfrage. Bitte versuche es erneut.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim(strtolower($_POST['email'] ?? ''));
        $privacyOk = !empty($_POST['privacy_consent']);

        if (empty($name))  $error = 'Bitte gib deinen Namen ein.';
        elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
            $error = 'Bitte gib eine gültige E-Mail-Adresse ein.';
        elseif (!$privacyOk) $error = 'Bitte bestätige die Datenschutzerklärung.';

        if (empty($error)) {
            // Rate-Limit prüfen
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            if (!check_rate_limit($ip, 'registration', RATE_LIMIT_REGISTRATIONS_PER_HOUR)) {
                $error = 'Zu viele Registrierungsversuche. Bitte warte eine Stunde.';
            }
        }

        if (empty($error)) {
            require_once __DIR__ . '/../lib/mail.php';

            // Prüfen ob E-Mail bereits registriert ist
            $existingUser = get_user_by_email($email);

            if ($existingUser) {
                // Prüfen ob bereits für dieses Event registriert
                if (has_event_role($existingUser['id'], $eventId, ['member', 'admin'])) {
                    $error = 'Du bist bereits für dieses Event registriert. Bitte melde dich an.';
                } else {
                    // Bestehenden User zu Event hinzufügen
                    add_event_role($eventId, $existingUser['id'], 'member', null);

                    // Member-Eintrag anlegen
                    $memberId = create_member($eventId, $existingUser['display_name'], $existingUser['email']);
                    link_member_account($memberId, $existingUser['id']);

                    // Registrierung protokollieren
                    $stmt = get_pdo()->prepare("INSERT INTO user_registrations (invitation_id, event_id, name, email, status) VALUES (?, ?, ?, ?, 'confirmed')");
                    $stmt->execute([$invitationId, $eventId, $existingUser['display_name'], $existingUser['email']]);

                    // Info-Mail senden
                    send_event_added_mail($existingUser['email'], $existingUser['display_name'], $eventName);

                    audit_log($eventId, $existingUser['id'], 'registration', 'Bestehender User zu Event hinzugefügt');
                    $registered = true;
                }
            } else {
                // Neuen User anlegen
                $autoConfirm = (bool)$invitation['auto_confirm_registration'];

                // Registrierung in Warteschlange
                $status = $autoConfirm ? 'confirmed' : 'pending';
                $stmt = get_pdo()->prepare("INSERT INTO user_registrations (invitation_id, event_id, name, email, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$invitationId, $eventId, $name, $email, $status]);

                if ($autoConfirm) {
                    // Sofort Account anlegen
                    $userId = create_user_account($email, $name);
                    log_consent($userId, PRIVACY_VERSION, hash_value($ip));
                    add_event_role($eventId, $userId, 'member', null);

                    // Member anlegen und verknüpfen
                    $memberId = create_member($eventId, $name, $email);
                    link_member_account($memberId, $userId);

                    // Magic Link senden
                    $token = create_magic_link($userId, 'registration', false);
                    send_magic_link_mail($email, $name, $token, 'registration');

                    audit_log($eventId, $userId, 'registration', "Neue Registrierung: $name");
                } else {
                    // Admin benachrichtigen
                    $admins = get_event_admins($eventId);
                    foreach ($admins as $admin) {
                        send_registration_notification_mail($admin['email'], $admin['display_name'], $name, $eventName);
                    }

                    audit_log($eventId, null, 'registration', "Registrierung eingegangen (pending): $name");
                }

                $registered = true;
            }
        }
    }
}

$themeColor = $invitation['theme_primary'] ?? '#dc2626';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrierung – <?= e($eventName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>:root { --color-primary: <?= e($themeColor) ?>; }</style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
<div class="max-w-md w-full">
    <div class="bg-white rounded-2xl shadow-xl p-8">
        <div class="text-center mb-6">
            <div class="text-4xl mb-3">📋</div>
            <h1 class="text-xl font-bold text-gray-900">Registrierung</h1>
            <p class="text-gray-600 mt-1 font-medium"><?= e($eventName) ?></p>
        </div>

        <?php if ($registered): ?>
            <div class="bg-green-50 border border-green-200 rounded-xl p-6">
                <?php if ($existingUser ?? false): ?>
                    <h2 class="font-bold text-green-800 text-lg mb-2">✅ Hinzugefügt!</h2>
                    <p class="text-green-700 text-sm">
                        Du wurdest zum Event „<?= e($eventName) ?>" hinzugefügt.
                        Du kannst dich jetzt mit deinem bestehenden Account anmelden.
                    </p>
                    <a href="index.php?login" class="inline-block mt-4 bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition text-sm font-semibold">
                        Anmelden
                    </a>
                <?php elseif ($autoConfirm ?? false): ?>
                    <h2 class="font-bold text-green-800 text-lg mb-2">✅ Registrierung erfolgreich!</h2>
                    <p class="text-green-700 text-sm">
                        Prüfe dein E-Mail-Postfach — wir haben dir einen Anmeldelink an
                        <strong><?= e($email) ?></strong> gesendet.
                    </p>
                <?php else: ?>
                    <h2 class="font-bold text-green-800 text-lg mb-2">✅ Registrierung eingegangen!</h2>
                    <p class="text-green-700 text-sm">
                        Deine Registrierung wird vom Event-Admin geprüft.
                        Du erhältst eine E-Mail, sobald sie bestätigt wurde.
                    </p>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
                    <p class="text-red-700 text-sm">❌ <?= e($error) ?></p>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="register">
                <?= csrf_field() ?>

                <div class="space-y-4">
                    <div>
                        <label for="name" class="block text-sm font-semibold text-gray-700 mb-1">Dein Name</label>
                        <input type="text" id="name" name="name" required autofocus
                               placeholder="Vor- und Nachname"
                               value="<?= e($_POST['name'] ?? '') ?>"
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none">
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-semibold text-gray-700 mb-1">E-Mail-Adresse</label>
                        <input type="email" id="email" name="email" required
                               placeholder="deine@email.de"
                               value="<?= e($_POST['email'] ?? '') ?>"
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none">
                        <p class="text-xs text-gray-400 mt-1">Wird ausschließlich für den Anmeldelink verwendet.</p>
                    </div>

                    <div class="flex items-start gap-2">
                        <input type="checkbox" id="privacy_consent" name="privacy_consent" value="1" required
                               class="mt-1 rounded border-gray-300 text-red-600 focus:ring-red-500">
                        <label for="privacy_consent" class="text-xs text-gray-600">
                            Ich habe die <a href="index.php?privacy" class="text-red-600 underline" target="_blank">Datenschutzerklärung</a>
                            gelesen und stimme der Verarbeitung meiner Daten zum Zweck der Übungsverwaltung zu.
                        </label>
                    </div>
                </div>

                <button type="submit" class="w-full mt-6 text-white font-semibold py-3 rounded-xl hover:opacity-90 transition"
                        style="background-color: var(--color-primary);">
                    Registrieren
                </button>
            </form>

            <div class="text-center mt-6">
                <p class="text-xs text-gray-400">
                    Bereits registriert?
                    <a href="index.php?login" class="text-red-600 hover:text-red-700">Anmelden</a>
                </p>
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
