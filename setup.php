<?php
/**
 * LAZ Übungs-Tracker – Ersteinrichtung
 * Erstellt die Datenbankstruktur und generiert den Server-Admin-Token.
 */

require_once __DIR__ . '/config.php';

if (SETUP_COMPLETE) {
    die('<!DOCTYPE html><html><head><meta charset="utf-8"><title>Setup gesperrt</title></head><body style="font-family:sans-serif;max-width:600px;margin:50px auto;text-align:center;"><h1>⚠️ Setup bereits durchgeführt</h1><p>Setze <code>SETUP_COMPLETE</code> in <code>config.php</code> auf <code>false</code>, um das Setup erneut auszuführen.</p></body></html>');
}

$errors = [];
$success = false;
$serverAdminUrl = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orgName = trim($_POST['organization_name'] ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    if (empty($orgName)) $errors[] = 'Bitte einen Organisationsnamen eingeben.';
    if (empty($adminEmail)) $errors[] = 'Bitte eine Administrator E-Mail eingeben.';

    if (empty($errors)) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `" . DB_NAME . "`");

            // Tabellen löschen (Reihenfolge wegen Foreign Keys)
            foreach (['audit_log','penalties','penalty_types','member_roles','roles','attendance','sessions','members','events','server_config'] as $t) {
                $pdo->exec("DROP TABLE IF EXISTS $t");
            }

            // server_config
            $pdo->exec("CREATE TABLE server_config (
                id INT AUTO_INCREMENT PRIMARY KEY,
                config_key VARCHAR(100) NOT NULL UNIQUE,
                config_value TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // events
            $pdo->exec("CREATE TABLE events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                organization_name VARCHAR(255) DEFAULT NULL,
                public_token VARCHAR(64) NOT NULL UNIQUE,
                admin_token VARCHAR(64) NOT NULL UNIQUE,
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
                status ENUM('active','archived') NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // members
            $pdo->exec("CREATE TABLE members (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                role VARCHAR(100) DEFAULT '',
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
                INDEX idx_event_active (event_id, active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // sessions
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

            // attendance
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

            // penalty_types
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

            // penalties
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

            // roles
            $pdo->exec("CREATE TABLE roles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
                INDEX idx_event_sort (event_id, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // member_roles
            $pdo->exec("CREATE TABLE member_roles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                member_id INT NOT NULL,
                role_id INT NOT NULL,
                FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
                FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
                UNIQUE KEY uk_member_role (member_id, role_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // audit_log
            $pdo->exec("CREATE TABLE audit_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_id INT NOT NULL,
                member_id INT NULL,
                action_type VARCHAR(50) NOT NULL,
                action_description TEXT NOT NULL,
                ip_address VARCHAR(45),
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
                INDEX idx_event_action (event_id, action_type),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Server-Config initialisieren
            $serverToken = bin2hex(random_bytes(24));
            $stmt = $pdo->prepare("INSERT INTO server_config (config_key, config_value) VALUES (?, ?)");
            $stmt->execute(['server_admin_token', $serverToken]);
            $stmt->execute(['organization_name', $orgName]);
            $stmt->execute(['admin_email', $adminEmail]);
            $stmt->execute(['show_public_overview', '0']);

            $success = true;
            $baseUrl = get_base_url();
            $serverAdminUrl = $baseUrl . '/admin.php?token=' . $serverToken;

        } catch (PDOException $e) {
            $errors[] = 'Datenbankfehler: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔧 LAZ Tracker – Ersteinrichtung</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-lg w-full">
        <div class="bg-white rounded-2xl shadow-xl p-8">
            <div class="text-center mb-8">
                <div class="text-5xl mb-4">🔧</div>
                <h1 class="text-2xl font-bold text-gray-900">LAZ Übungs-Tracker</h1>
                <p class="text-gray-500 mt-1">Ersteinrichtung</p>
            </div>

            <?php if ($success): ?>
                <div class="bg-green-50 border border-green-200 rounded-xl p-6 mb-6">
                    <h2 class="font-bold text-green-800 text-lg mb-2">✅ Einrichtung erfolgreich!</h2>
                    <p class="text-green-700 text-sm mb-4">Die Datenbank wurde erstellt.</p>
                    <div>
                        <label class="block text-xs font-semibold text-green-800 mb-1">🔑 Server-Admin URL:</label>
                        <input type="text" readonly value="<?= e($serverAdminUrl) ?>"
                               class="w-full text-xs p-2 bg-white border border-green-300 rounded font-mono"
                               onclick="this.select()">
                    </div>
                </div>
                <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-6">
                    <h3 class="font-bold text-yellow-800 text-sm mb-2">⚠️ Nächste Schritte:</h3>
                    <ol class="text-yellow-700 text-sm space-y-1 list-decimal list-inside">
                        <li>Server-Admin URL sicher abspeichern</li>
                        <li><code class="bg-yellow-100 px-1 rounded">SETUP_COMPLETE</code> in <code class="bg-yellow-100 px-1 rounded">config.php</code> auf <code class="bg-yellow-100 px-1 rounded">true</code> setzen</li>
                        <li>Erstes Event im Server-Admin erstellen</li>
                    </ol>
                </div>
                <a href="<?= e($serverAdminUrl) ?>" class="block w-full bg-red-600 text-white text-center font-semibold py-3 rounded-xl hover:bg-red-700 transition">
                    Zum Server-Admin →
                </a>
            <?php else: ?>
                <?php if (!empty($errors)): ?>
                    <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
                        <?php foreach ($errors as $err): ?>
                            <p class="text-red-700 text-sm">❌ <?= e($err) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <form method="POST">
                    <div class="space-y-4 mb-6">
                        <div>
                            <label class="text-xs font-semibold text-gray-600">Name der Organisation:</label>
                            <input type="text" name="organization_name" required
                                   placeholder="z.B. Freiwillige Feuerwehr Rutesheim"
                                   value="<?= e($_POST['organization_name'] ?? '') ?>"
                                   class="w-full border rounded-lg p-2 text-sm mt-1 focus:ring-2 focus:ring-red-500 focus:border-red-500">
                            <p class="text-xs text-gray-400 mt-1">Wird auf allen Seiten angezeigt.</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600">Administrator E-Mail:</label>
                            <input type="email" name="admin_email" required
                                   placeholder="admin@beispiel.de"
                                   value="<?= e($_POST['admin_email'] ?? '') ?>"
                                   class="w-full border rounded-lg p-2 text-sm mt-1 focus:ring-2 focus:ring-red-500 focus:border-red-500">
                            <p class="text-xs text-gray-400 mt-1">Wird im Footer und auf Fehlerseiten als Kontaktadresse angezeigt.</p>
                        </div>
                    </div>
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
                        <h3 class="font-bold text-blue-800 text-sm mb-2">ℹ️ Was wird eingerichtet?</h3>
                        <ul class="text-blue-700 text-sm space-y-1">
                            <li>• Alle Datenbanktabellen werden erstellt</li>
                            <li>• Server-Admin-Token wird generiert</li>
                            <li>• Events werden danach im Server-Admin angelegt</li>
                        </ul>
                    </div>
                    <div class="bg-gray-50 border rounded-xl p-4 mb-6">
                        <h3 class="font-bold text-gray-700 text-sm mb-2">📋 Voraussetzungen:</h3>
                        <ul class="text-gray-600 text-sm space-y-1">
                            <li>✓ MySQL/MariaDB verfügbar</li>
                            <li>✓ Zugangsdaten in <code class="bg-gray-200 px-1 rounded">config.php</code> eingetragen</li>
                            <li>✓ PHP 8.0+ mit PDO-MySQL</li>
                        </ul>
                    </div>
                    <button type="submit" class="w-full bg-red-600 text-white font-semibold py-3 rounded-xl hover:bg-red-700 transition"
                            onclick="return confirm('Bestehende Tabellen werden gelöscht und neu erstellt!')">
                        🚀 Einrichtung starten
                    </button>
                </form>
            <?php endif; ?>
        </div>
        <p class="text-center text-gray-400 text-xs mt-4">LAZ Übungs-Tracker v<?= APP_VERSION ?></p>
    </div>
</body>
</html>
