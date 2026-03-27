<?php
/**
 * BOS-Score – Login-Seite
 * E-Mail eingeben → Magic Link wird gesendet.
 */

$returnTo = $_GET['return_to'] ?? '';
$loggedOut = isset($_GET['logged_out']);
$linkSent = false;
$error = '';

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_magic_link') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Ungültige Anfrage. Bitte versuche es erneut.';
    } else {
        $email = trim(strtolower($_POST['email'] ?? ''));
        $rememberMe = !empty($_POST['remember_me']);

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Bitte gib eine gültige E-Mail-Adresse ein.';
        } else {
            // Rate-Limit prüfen
            if (!check_rate_limit($email, 'magic_link', RATE_LIMIT_MAGIC_LINKS_PER_HOUR)) {
                $error = 'Zu viele Anfragen. Bitte warte eine Stunde und versuche es erneut.';
            } else {
                require_once __DIR__ . '/../lib/mail.php';

                $user = get_user_by_email($email);
                if ($user) {
                    $token = create_magic_link($user['id'], 'login', $rememberMe);
                    send_magic_link_mail($user['email'], $user['display_name'], $token, 'login');
                }

                // Immer Erfolg zeigen (verhindert E-Mail-Enumeration)
                $linkSent = true;
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
    <title>Anmelden – <?= e(APP_NAME) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
<div class="max-w-md w-full">
    <div class="bg-white rounded-2xl shadow-xl p-8">
        <div class="text-center mb-8">
            <div class="text-4xl mb-3">🔐</div>
            <h1 class="text-2xl font-bold text-gray-900"><?= e(APP_NAME) ?></h1>
            <p class="text-gray-500 mt-1">Anmelden</p>
        </div>

        <?php if ($loggedOut): ?>
            <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-6">
                <p class="text-green-700 text-sm">✅ Du wurdest erfolgreich abgemeldet.</p>
            </div>
        <?php endif; ?>

        <?php if ($linkSent): ?>
            <!-- Erfolg: Link gesendet -->
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-6">
                <h2 class="font-bold text-blue-800 text-lg mb-2">📧 Prüfe dein Postfach</h2>
                <p class="text-blue-700 text-sm mb-3">
                    Falls ein Account mit dieser E-Mail-Adresse existiert, haben wir dir
                    einen Anmeldelink gesendet.
                </p>
                <p class="text-blue-600 text-xs">
                    Der Link ist <?= MAGIC_LINK_EXPIRY_MINUTES ?> Minuten gültig und kann nur einmal verwendet werden.
                    Prüfe ggf. auch deinen Spam-Ordner.
                </p>
            </div>
            <div class="text-center mt-6">
                <a href="index.php?login" class="text-sm text-gray-500 hover:text-gray-700">Erneut versuchen</a>
            </div>

        <?php else: ?>
            <!-- Login-Formular -->
            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
                    <p class="text-red-700 text-sm">❌ <?= e($error) ?></p>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="request_magic_link">
                <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
                <?= csrf_field() ?>

                <div class="space-y-4">
                    <div>
                        <label for="email" class="block text-sm font-semibold text-gray-700 mb-1">E-Mail-Adresse</label>
                        <input type="email" id="email" name="email" required autofocus
                               placeholder="deine@email.de"
                               value="<?= e($_POST['email'] ?? '') ?>"
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none">
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="remember_me" name="remember_me" value="1"
                               class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                        <label for="remember_me" class="text-sm text-gray-600">
                            Auf diesem Gerät angemeldet bleiben
                        </label>
                    </div>
                </div>

                <button type="submit" class="w-full mt-6 bg-red-600 text-white font-semibold py-3 rounded-xl hover:bg-red-700 transition">
                    Anmeldelink senden
                </button>
            </form>

            <p class="text-center text-xs text-gray-400 mt-6">
                Wir senden dir einen sicheren Link per E-Mail — kein Passwort nötig.
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
