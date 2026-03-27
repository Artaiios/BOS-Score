<?php
/**
 * BOS-Score – Profil-Router
 * Event-übergreifende Profilseite: Sessions, Datenexport, Account-Löschung.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/auth.php';

$user = require_auth();

// ── POST-Aktionen ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        json_response(['success' => false, 'message' => 'Ungültige Anfrage.'], 403);
    }

    $action = $_POST['action'] ?? '';

    // ── Anzeigenamen ändern ──────────────────────────────────
    if ($action === 'update_name') {
        $newName = trim($_POST['display_name'] ?? '');
        if (empty($newName) || mb_strlen($newName) > 100) {
            $_SESSION['profile_error'] = 'Bitte gib einen gültigen Namen ein (max. 100 Zeichen).';
        } else {
            update_display_name($user['id'], $newName);
            audit_log(null, $user['id'], 'profile_update', "Name geändert: {$user['display_name']} → $newName");
            $_SESSION['profile_success'] = 'Name erfolgreich geändert.';
        }
        redirect('profile.php');
    }

    // ── Einzelne Session widerrufen ─────────────────────────
    if ($action === 'revoke_session') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        if ($sessionId > 0 && $sessionId !== $user['session_id']) {
            revoke_session($sessionId, $user['id']);
            $_SESSION['profile_success'] = 'Session widerrufen.';
        }
        redirect('profile.php#sessions');
    }

    // ── Alle anderen Sessions widerrufen ────────────────────
    if ($action === 'revoke_all_sessions') {
        $count = revoke_all_sessions($user['id'], $user['session_id']);
        $_SESSION['profile_success'] = "$count andere Session(s) widerrufen.";
        redirect('profile.php#sessions');
    }

    // ── Datenexport (JSON) ──────────────────────────────────
    if ($action === 'export_data') {
        $data = export_user_data($user['id']);
        audit_log(null, $user['id'], 'data_export', 'DSGVO-Datenexport durchgeführt');

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="bos-score-datenexport-' . date('Y-m-d') . '.json"');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // ── Account löschen ─────────────────────────────────────
    if ($action === 'delete_account') {
        $confirmEmail = trim(strtolower($_POST['confirm_email'] ?? ''));
        if ($confirmEmail !== $user['email']) {
            $_SESSION['profile_error'] = 'Die eingegebene E-Mail-Adresse stimmt nicht überein.';
            redirect('profile.php#delete');
        }

        require_once __DIR__ . '/lib/mail.php';

        // Einwilligung widerrufen protokollieren
        $stmt = get_pdo()->prepare("INSERT INTO consent_log (user_account_id, consent_version, action, ip_hash) VALUES (?, ?, 'withdrawn', ?)");
        $stmt->execute([$user['id'], PRIVACY_VERSION, hash_value($_SERVER['REMOTE_ADDR'] ?? '')]);

        audit_log(null, $user['id'], 'account_delete', "Account-Löschung angefordert: {$user['email']}");

        // Soft-Delete
        soft_delete_user($user['id']);
        send_account_deleted_mail($user['email'], $user['display_name']);

        // Logout
        clear_auth_cookie();
        session_destroy();

        // Bestätigungsseite
        ?>
        <!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Account gelöscht – <?= e(APP_NAME) ?></title>
            <script src="https://cdn.tailwindcss.com"></script>
        </head>
        <body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
        <div class="max-w-md w-full bg-white rounded-2xl shadow-xl p-8 text-center">
            <div class="text-5xl mb-4">👋</div>
            <h1 class="text-xl font-bold text-gray-900">Account gelöscht</h1>
            <p class="text-gray-600 text-sm mt-4">
                Dein Account wurde zur Löschung vorgemerkt. Deine Daten werden nach
                <?= SOFT_DELETE_RETENTION_DAYS ?> Tagen endgültig entfernt.
            </p>
            <p class="text-gray-500 text-sm mt-2">
                Eine Bestätigung wurde an deine E-Mail-Adresse gesendet.
            </p>
            <a href="index.php" class="inline-block mt-6 text-red-600 hover:text-red-700 text-sm font-semibold">
                Zur Startseite
            </a>
        </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// ── GET: Profilseite anzeigen ───────────────────────────────
require __DIR__ . '/views/profile.php';
