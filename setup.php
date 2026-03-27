<?php
/**
 * BOS-Score – Ersteinrichtung
 * Erstellt die Datenbankstruktur und den Server-Admin-Account.
 */

require_once __DIR__ . '/config.php';

if (SETUP_COMPLETE) {
    die('<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><title>Setup gesperrt</title></head><body style="font-family:sans-serif;max-width:600px;margin:50px auto;text-align:center;"><h1>⚠️ Setup bereits durchgeführt</h1><p>Setze <code>SETUP_COMPLETE</code> in <code>config.php</code> auf <code>false</code>, um das Setup erneut auszuführen.</p></body></html>');
}

$errors = [];
$success = false;
$magicLinkSent = false;

// ── Schritt 2: Server-Admin registrieren ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step']) && $_POST['step'] === 'admin') {
    $orgName      = trim($_POST['organization_name'] ?? '');
    $adminName    = trim($_POST['admin_name'] ?? '');
    $adminEmail   = trim(strtolower($_POST['admin_email'] ?? ''));
    $privacyOk    = !empty($_POST['privacy_consent']);

    if (empty($orgName))    $errors[] = 'Bitte einen Organisationsnamen eingeben.';
    if (empty($adminName))  $errors[] = 'Bitte deinen Namen eingeben.';
    if (empty($adminEmail) || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Bitte eine gültige E-Mail-Adresse eingeben.';
    }
    if (!$privacyOk) $errors[] = 'Bitte die Datenschutzerklärung bestätigen.';

    if (empty($errors)) {
        try {
            require_once __DIR__ . '/db.php';
            require_once __DIR__ . '/lib/auth.php';
            require_once __DIR__ . '/lib/mail.php';

            $pdo = get_pdo();

            // Server-Config setzen
            set_server_config('organization_name', $orgName);
            set_server_config('admin_email', $adminEmail);
            set_server_config('show_public_overview', '0');

            // User-Account anlegen
            $userId = create_user_account($adminEmail, $adminName);

            // DSGVO-Einwilligung protokollieren
            log_consent($userId, PRIVACY_VERSION, hash_value($_SERVER['REMOTE_ADDR'] ?? ''));

            // Rolle zuweisen: server_admin (event_id = NULL)
            add_event_role(null, $userId, 'server_admin', null);

            // Magic Link erstellen und senden
            $token = create_magic_link($userId, 'registration', false);
            $mailResult = send_magic_link_mail($adminEmail, $adminName, $token, 'registration');

            if ($mailResult) {
                $magicLinkSent = true;
                $success = true;
            } else {
                $errors[] = 'E-Mail konnte nicht gesendet werden. Bitte prüfe die SMTP-Konfiguration in config.php.';
            }

        } catch (PDOException $e) {
            $errors[] = 'Datenbankfehler: ' . $e->getMessage();
        } catch (Exception $e) {
            $errors[] = 'Fehler: ' . $e->getMessage();
        }
    }
}

// ── Schritt 1: Datenbank erstellen ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step']) && $_POST['step'] === 'database') {
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `" . DB_NAME . "`");

        // Alle Tabellen in korrekter Reihenfolge löschen (Foreign Keys)
        $dropOrder = [
            'rate_limits', 'consent_log', 'user_sessions', 'magic_links',
            'member_account_links', 'user_registrations', 'admin_invitations',
            'event_invitations', 'event_roles', 'user_accounts',
            'audit_log', 'penalties', 'penalty_types', 'member_roles', 'roles',
            'attendance', 'sessions', 'members', 'events', 'server_config',
        ];
        foreach ($dropOrder as $t) {
            $pdo->exec("DROP TABLE IF EXISTS `$t`");
        }

        // ════════════════════════════════════════════════════
        // Bestehende Kerntabellen (BOS-Score / ehem. LAZ)
        // ════════════════════════════════════════════════════

        $pdo->exec("CREATE TABLE server_config (
            id INT AUTO_INCREMENT PRIMARY KEY,
            config_key VARCHAR(100) NOT NULL UNIQUE,
            config_value TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            organization_name VARCHAR(255) DEFAULT NULL,
            public_token VARCHAR(64) NOT NULL UNIQUE,
            deadline_1_date DATE NOT NULL,
            deadline_1_count INT NOT NULL DEFAULT 11,
            deadline_1_name VARCHAR(100) DEFAULT 'Frist 1',
            deadline_1_enabled TINYINT(1) NOT NULL DEFAULT 1,
            deadline_2_date DATE NOT NULL,
            deadline_2_count INT NOT NULL DEFAULT 20,
            deadline_2_name VARCHAR(100) DEFAULT 'Frist 2',
            session_duration_hours INT NOT NULL DEFAULT 3,
            weather_location VARCHAR(255) DEFAULT '',
            weather_lat DECIMAL(8,5) DEFAULT 0,
            weather_lng DECIMAL(8,5) DEFAULT 0,
            roles_enabled TINYINT(1) NOT NULL DEFAULT 0,
            auto_confirm_registration TINYINT(1) NOT NULL DEFAULT 1,
            theme_primary VARCHAR(7) DEFAULT '#dc2626',
            theme_logo_path VARCHAR(255) DEFAULT NULL,
            status ENUM('active','archived') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE members (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) DEFAULT NULL,
            role VARCHAR(100) DEFAULT '',
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
            INDEX idx_event_active (event_id, active),
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            session_date DATE NOT NULL,
            session_time TIME NOT NULL,
            comment VARCHAR(255) DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
            INDEX idx_event_date (event_id, session_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            member_id INT NOT NULL,
            status ENUM('present','excused','absent') NOT NULL DEFAULT 'absent',
            excused_at DATETIME NULL,
            excused_by ENUM('member','admin') NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
            FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
            UNIQUE KEY uk_session_member (session_id, member_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE penalty_types (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            description VARCHAR(255) NOT NULL,
            amount DECIMAL(5,2) NOT NULL,
            active_from DATE NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE penalties (
            id INT AUTO_INCREMENT PRIMARY KEY,
            member_id INT NOT NULL,
            penalty_type_id INT NOT NULL,
            penalty_date DATE NOT NULL,
            comment VARCHAR(500) DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
            FOREIGN KEY (penalty_type_id) REFERENCES penalty_types(id) ON DELETE CASCADE,
            INDEX idx_member_active (member_id, deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
            INDEX idx_event_sort (event_id, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE member_roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            member_id INT NOT NULL,
            role_id INT NOT NULL,
            FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
            UNIQUE KEY uk_member_role (member_id, role_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NULL,
            user_account_id INT NULL,
            action_type VARCHAR(50) NOT NULL,
            action_description TEXT NOT NULL,
            ip_hash VARCHAR(64),
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_event_action (event_id, action_type),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // ════════════════════════════════════════════════════
        // Neue Auth-Tabellen
        // ════════════════════════════════════════════════════

        $pdo->exec("CREATE TABLE user_accounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            display_name VARCHAR(100) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            consent_given_at DATETIME NULL,
            consent_version VARCHAR(10) NULL,
            INDEX idx_email (email),
            INDEX idx_deleted (deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE event_roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NULL,
            user_account_id INT NOT NULL,
            role ENUM('server_admin','admin','member') NOT NULL,
            granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            granted_by INT NULL,
            FOREIGN KEY (user_account_id) REFERENCES user_accounts(id) ON DELETE CASCADE,
            UNIQUE KEY uk_event_user_role (event_id, user_account_id, role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE event_invitations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            reg_mode ENUM('open','until_date','closed') NOT NULL DEFAULT 'open',
            reg_until DATETIME NULL,
            invalidated_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE admin_invitations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            email VARCHAR(255) NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            invited_by INT NULL,
            accepted_at DATETIME NULL,
            invalidated_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
            FOREIGN KEY (invited_by) REFERENCES user_accounts(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE user_registrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invitation_id INT NULL,
            event_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(255) NOT NULL,
            status ENUM('pending','confirmed','rejected') NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
            INDEX idx_event_status (event_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE member_account_links (
            id INT AUTO_INCREMENT PRIMARY KEY,
            member_id INT NOT NULL,
            user_account_id INT NOT NULL,
            linked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
            FOREIGN KEY (user_account_id) REFERENCES user_accounts(id) ON DELETE CASCADE,
            UNIQUE KEY uk_member_user (member_id, user_account_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE magic_links (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_account_id INT NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            purpose ENUM('login','registration') NOT NULL DEFAULT 'login',
            remember_me TINYINT(1) NOT NULL DEFAULT 0,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ip_hash VARCHAR(64) NULL,
            FOREIGN KEY (user_account_id) REFERENCES user_accounts(id) ON DELETE CASCADE,
            INDEX idx_token_hash (token_hash),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE user_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_account_id INT NOT NULL,
            session_token_hash VARCHAR(64) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            last_seen_at DATETIME NULL,
            ip_hash VARCHAR(64) NULL,
            user_agent_hash VARCHAR(64) NULL,
            device_label VARCHAR(100) NULL,
            revoked_at DATETIME NULL,
            FOREIGN KEY (user_account_id) REFERENCES user_accounts(id) ON DELETE CASCADE,
            INDEX idx_token_hash (session_token_hash),
            INDEX idx_user_active (user_account_id, revoked_at, expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE consent_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_account_id INT NOT NULL,
            consent_version VARCHAR(10) NOT NULL,
            action ENUM('granted','withdrawn') NOT NULL,
            ip_hash VARCHAR(64) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_account_id) REFERENCES user_accounts(id) ON DELETE CASCADE,
            INDEX idx_user (user_account_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            identifier_hash VARCHAR(64) NOT NULL,
            action VARCHAR(50) NOT NULL,
            attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_identifier_action (identifier_hash, action, attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $_SESSION['setup_db_done'] = true;

    } catch (PDOException $e) {
        $errors[] = 'Datenbankfehler: ' . $e->getMessage();
    }
}

$dbDone = !empty($_SESSION['setup_db_done']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔧 BOS-Score – Ersteinrichtung</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
<div class="max-w-lg w-full">
    <div class="bg-white rounded-2xl shadow-xl p-8">
        <div class="text-center mb-8">
            <div class="text-5xl mb-4">🔧</div>
            <h1 class="text-2xl font-bold text-gray-900"><?= e(APP_NAME) ?></h1>
            <p class="text-gray-500 mt-1">Ersteinrichtung</p>
        </div>

        <?php if ($success && $magicLinkSent): ?>
            <!-- ── Erfolg: Magic Link gesendet ── -->
            <div class="bg-green-50 border border-green-200 rounded-xl p-6 mb-6">
                <h2 class="font-bold text-green-800 text-lg mb-2">✅ Einrichtung erfolgreich!</h2>
                <p class="text-green-700 text-sm">
                    Die Datenbank wurde erstellt und dein Server-Admin-Account angelegt.
                </p>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-xl p-6 mb-6">
                <h3 class="font-bold text-blue-800 text-sm mb-2">📧 Prüfe dein E-Mail-Postfach</h3>
                <p class="text-blue-700 text-sm">
                    Ein Anmeldelink wurde an <strong><?= e($adminEmail) ?></strong> gesendet.
                    Klicke den Link in der E-Mail, um dich als Server-Admin anzumelden.
                </p>
                <p class="text-blue-600 text-xs mt-2">
                    Der Link ist <?= MAGIC_LINK_EXPIRY_MINUTES ?> Minuten gültig.
                </p>
            </div>

            <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4">
                <h3 class="font-bold text-yellow-800 text-sm mb-2">⚠️ Wichtig:</h3>
                <p class="text-yellow-700 text-sm">
                    Setze <code class="bg-yellow-100 px-1 rounded">SETUP_COMPLETE</code> in
                    <code class="bg-yellow-100 px-1 rounded">config.php</code> auf
                    <code class="bg-yellow-100 px-1 rounded">true</code>, um das Setup zu sperren.
                </p>
            </div>

        <?php elseif ($dbDone): ?>
            <!-- ── Schritt 2: Server-Admin anlegen ── -->
            <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-6">
                <p class="text-green-700 text-sm font-semibold">✅ Datenbank erfolgreich erstellt</p>
            </div>

            <h2 class="text-lg font-bold text-gray-800 mb-4">Schritt 2: Server-Admin anlegen</h2>

            <?php if (!empty($errors)): ?>
                <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
                    <?php foreach ($errors as $err): ?>
                        <p class="text-red-700 text-sm">❌ <?= e($err) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="step" value="admin">
                <div class="space-y-4 mb-6">
                    <div>
                        <label class="text-xs font-semibold text-gray-600">Name der Organisation</label>
                        <input type="text" name="organization_name" required
                               placeholder="z.B. THW OV Leonberg, FF Rutesheim, DRK KV Böblingen"
                               value="<?= e($_POST['organization_name'] ?? '') ?>"
                               class="w-full border rounded-lg p-2 text-sm mt-1 focus:ring-2 focus:ring-red-500 focus:border-red-500">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600">Dein Name</label>
                        <input type="text" name="admin_name" required
                               placeholder="Vor- und Nachname"
                               value="<?= e($_POST['admin_name'] ?? '') ?>"
                               class="w-full border rounded-lg p-2 text-sm mt-1 focus:ring-2 focus:ring-red-500 focus:border-red-500">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600">Deine E-Mail-Adresse</label>
                        <input type="email" name="admin_email" required
                               placeholder="admin@beispiel.de"
                               value="<?= e($_POST['admin_email'] ?? '') ?>"
                               class="w-full border rounded-lg p-2 text-sm mt-1 focus:ring-2 focus:ring-red-500 focus:border-red-500">
                        <p class="text-xs text-gray-400 mt-1">Hierhin wird der Anmeldelink gesendet.</p>
                    </div>
                    <div class="flex items-start gap-2 mt-2">
                        <input type="checkbox" name="privacy_consent" id="privacy_consent" value="1" required
                               class="mt-1 rounded border-gray-300 text-red-600 focus:ring-red-500">
                        <label for="privacy_consent" class="text-xs text-gray-600">
                            Ich habe die <a href="index.php?privacy" class="text-red-600 underline" target="_blank">Datenschutzerklärung</a>
                            gelesen und stimme der Verarbeitung meiner Daten zu.
                        </label>
                    </div>
                </div>

                <button type="submit" class="w-full bg-red-600 text-white font-semibold py-3 rounded-xl hover:bg-red-700 transition">
                    🚀 Account erstellen & Anmeldelink senden
                </button>
            </form>

        <?php else: ?>
            <!-- ── Schritt 1: Datenbank erstellen ── -->
            <?php if (!empty($errors)): ?>
                <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
                    <?php foreach ($errors as $err): ?>
                        <p class="text-red-700 text-sm">❌ <?= e($err) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <h2 class="text-lg font-bold text-gray-800 mb-4">Schritt 1: Datenbank einrichten</h2>

            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
                <h3 class="font-bold text-blue-800 text-sm mb-2">ℹ️ Was wird eingerichtet?</h3>
                <p class="text-blue-700 text-sm">
                    Alle Datenbanktabellen für BOS-Score werden erstellt: Events, Teilnehmer,
                    Termine, Anwesenheit, Strafenkatalog, Team-Kasse, sowie das komplette
                    Authentifizierungssystem mit Magic Links und DSGVO-konformer Datenverwaltung.
                </p>
            </div>

            <div class="bg-gray-50 border rounded-xl p-4 mb-6">
                <h3 class="font-bold text-gray-700 text-sm mb-2">📋 Voraussetzungen</h3>
                <p class="text-gray-600 text-sm space-y-1">
                    Stelle sicher, dass MySQL/MariaDB verfügbar ist, die Zugangsdaten in
                    <code class="bg-gray-200 px-1 rounded">config.php</code> eingetragen sind,
                    PHP 8.0+ mit PDO-MySQL installiert ist und die SMTP-Zugangsdaten für den
                    E-Mail-Versand konfiguriert sind.
                </p>
            </div>

            <form method="POST">
                <input type="hidden" name="step" value="database">
                <button type="submit" class="w-full bg-red-600 text-white font-semibold py-3 rounded-xl hover:bg-red-700 transition"
                        onclick="return confirm('Bestehende Tabellen werden gelöscht und neu erstellt. Fortfahren?')">
                    🗄️ Datenbank erstellen
                </button>
            </form>
        <?php endif; ?>
    </div>

    <p class="text-center text-gray-400 text-xs mt-4"><?= e(APP_NAME) ?> v<?= APP_VERSION ?></p>
</div>
</body>
</html>
