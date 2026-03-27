<?php
/**
 * BOS-Score – Server-Admin UI
 * Nur aggregierte Statistiken — keine personenbezogenen Daten.
 */

require_once __DIR__ . '/../lib/mail.php';

$orgName = get_server_config('organization_name', APP_NAME);
$adminEmail = get_server_config('admin_email', '');

$successMsg = $_SESSION['admin_success'] ?? '';
$errorMsg = $_SESSION['admin_error'] ?? '';
unset($_SESSION['admin_success'], $_SESSION['admin_error']);

// ── POST-Aktionen ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['admin_error'] = 'Ungültige Anfrage.';
        redirect('admin.php');
    }

    $action = $_POST['action'] ?? '';

    // ── Event erstellen ─────────────────────────────────────
    if ($action === 'create_event') {
        $eventName  = trim($_POST['event_name'] ?? '');
        $eventOrg   = trim($_POST['event_org'] ?? '');
        $d2Date     = $_POST['d2_date'] ?? '';
        $d2Count    = (int)($_POST['d2_count'] ?? 20);
        $d1Enabled  = !empty($_POST['d1_enabled']);
        $d1Date     = $_POST['d1_date'] ?? '';
        $d1Count    = (int)($_POST['d1_count'] ?? 11);
        $adminEmails = trim($_POST['admin_emails'] ?? '');
        $themeColor = $_POST['theme_primary'] ?? '#dc2626';

        if (empty($eventName) || empty($d2Date)) {
            $_SESSION['admin_error'] = 'Eventname und Hauptfrist sind Pflichtfelder.';
            redirect('admin.php');
        }

        $result = create_event($eventName, $d2Date, $d2Count, $d1Date, $d1Count, $d1Enabled, $eventOrg);
        $eventId = $result['id'];

        // Theme setzen
        update_event($eventId, ['theme_primary' => $themeColor]);

        // Server-Admin als Event-Admin hinzufügen
        add_event_role($eventId, $user['id'], 'admin', $user['id']);

        audit_log($eventId, $user['id'], 'event_created', "Event erstellt: $eventName");

        // Weitere Admins einladen
        if (!empty($adminEmails)) {
            $emails = array_filter(array_map('trim', preg_split('/[\n,;]+/', $adminEmails)));
            foreach ($emails as $email) {
                $email = strtolower($email);
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
                if ($email === $user['email']) continue; // Sich selbst nicht nochmal einladen

                $token = create_admin_invitation($eventId, $email, $user['id']);
                send_admin_invitation_mail($email, $eventName, $token, $user['display_name']);
                audit_log($eventId, $user['id'], 'admin_invited', "Admin eingeladen: $email");
            }
        }

        $_SESSION['admin_success'] = 'Event "' . $eventName . '" erstellt.';
        redirect('admin.php');
    }

    // ── Event archivieren ───────────────────────────────────
    if ($action === 'archive_event') {
        $eventId = (int)($_POST['event_id'] ?? 0);
        $event = get_event_by_id($eventId);
        if ($event) {
            update_event($eventId, ['status' => 'archived']);
            audit_log($eventId, $user['id'], 'event_archived', "Event archiviert: {$event['name']}");
        $_SESSION['admin_success'] = 'Event "' . $event['name'] . '" archiviert.';
        }
        redirect('admin.php');
    }

    // ── Event löschen ───────────────────────────────────────
    if ($action === 'delete_event') {
        $eventId = (int)($_POST['event_id'] ?? 0);
        $event = get_event_by_id($eventId);
        if ($event) {
            audit_log(null, $user['id'], 'event_deleted', "Event gelöscht: {$event['name']}");
            delete_event($eventId);
        $_SESSION['admin_success'] = 'Event "' . $event['name'] . '" geloescht.';
        }
        redirect('admin.php');
    }

    // ── Globale Einstellungen ───────────────────────────────
    if ($action === 'save_settings') {
        $newOrgName = trim($_POST['organization_name'] ?? '');
        $newAdminEmail = trim($_POST['admin_email'] ?? '');

        if (!empty($newOrgName)) set_server_config('organization_name', $newOrgName);
        if (!empty($newAdminEmail)) set_server_config('admin_email', $newAdminEmail);

        $_SESSION['admin_success'] = 'Einstellungen gespeichert.';
        redirect('admin.php');
    }
}

// ── Event-Statistiken laden ─────────────────────────────────
$events = get_event_stats_overview();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server-Administration – <?= e(APP_NAME) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">

<nav class="bg-gray-900 text-white shadow-lg">
    <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="text-lg font-bold">🔧 <?= e(APP_NAME) ?></span>
            <span class="text-xs text-gray-400">Server-Administration</span>
        </div>
        <div class="flex items-center gap-4">
            <a href="index.php" class="text-sm text-gray-300 hover:text-white">← Übersicht</a>
            <a href="profile.php" class="text-sm text-gray-300 hover:text-white"><?= e($user['display_name']) ?></a>
            <a href="index.php?logout" class="text-sm text-red-400 hover:text-red-300">Abmelden</a>
        </div>
    </div>
</nav>

<div class="max-w-5xl mx-auto px-4 py-8">

    <?php if ($successMsg): ?>
        <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-6">
            <p class="text-green-700 text-sm">✅ <?= e($successMsg) ?></p>
        </div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
            <p class="text-red-700 text-sm">❌ <?= e($errorMsg) ?></p>
        </div>
    <?php endif; ?>

    <!-- ── Event erstellen ───────────────────────────────────── -->
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
        <h2 class="text-lg font-bold text-gray-900 mb-4">Neues Event erstellen</h2>

        <form method="POST">
            <input type="hidden" name="action" value="create_event">
            <?= csrf_field() ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Eventname *</label>
                    <input type="text" name="event_name" required placeholder="z.B. Leistungsabzeichen Bronze 2026"
                           class="w-full border rounded-lg p-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Organisation (optional)</label>
                    <input type="text" name="event_org" placeholder="Überschreibt globalen Namen"
                           class="w-full border rounded-lg p-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Hauptfrist (Datum) *</label>
                    <input type="date" name="d2_date" required
                           class="w-full border rounded-lg p-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Benötigte Teilnahmen</label>
                    <input type="number" name="d2_count" value="20" min="1"
                           class="w-full border rounded-lg p-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500">
                </div>
            </div>

            <div class="mb-4">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="d1_enabled" value="1" class="rounded text-red-600 focus:ring-red-500"
                           onchange="document.getElementById('d1_fields').classList.toggle('hidden', !this.checked)">
                    Optionale Zwischenfrist (Frist 1) aktivieren
                </label>
                <div id="d1_fields" class="hidden grid grid-cols-2 gap-4 mt-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Zwischenfrist (Datum)</label>
                        <input type="date" name="d1_date"
                               class="w-full border rounded-lg p-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Benötigte Teilnahmen</label>
                        <input type="number" name="d1_count" value="11" min="1"
                               class="w-full border rounded-lg p-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500">
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Event-Farbe</label>
                <input type="color" name="theme_primary" value="#dc2626" class="h-10 w-20 rounded border cursor-pointer">
            </div>

            <div class="mb-6">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Event-Admins einladen (E-Mail-Adressen)</label>
                <textarea name="admin_emails" rows="2" placeholder="Eine Adresse pro Zeile oder kommagetrennt"
                          class="w-full border rounded-lg p-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500"></textarea>
                <p class="text-xs text-gray-400 mt-1">Du wirst automatisch als Admin hinzugefügt.</p>
            </div>

            <button type="submit" class="bg-red-600 text-white font-semibold px-6 py-2 rounded-lg hover:bg-red-700 transition">
                🚀 Event erstellen
            </button>
        </form>
    </div>

    <!-- ── Events Übersicht ──────────────────────────────────── -->
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
        <h2 class="text-lg font-bold text-gray-900 mb-4">Events (<?= count($events) ?>)</h2>

        <?php if (empty($events)): ?>
            <p class="text-gray-500 text-sm">Noch keine Events erstellt.</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($events as $ev): ?>
                    <?php $themeColor = $ev['theme_primary'] ?? '#dc2626'; ?>
                    <div class="border rounded-xl p-4 border-l-4 <?= $ev['status'] === 'archived' ? 'opacity-60' : '' ?>"
                         style="border-left-color: <?= e($themeColor) ?>;">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="font-bold text-gray-900">
                                    <?= e($ev['name']) ?>
                                    <?php if ($ev['status'] === 'archived'): ?>
                                        <span class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full ml-1">Archiviert</span>
                                    <?php endif; ?>
                                </h3>
                                <?php if ($ev['organization_name']): ?>
                                    <p class="text-xs text-gray-500"><?= e($ev['organization_name']) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="flex gap-2">
                                <?php if ($ev['status'] === 'active'): ?>
                                    <form method="POST" class="inline" onsubmit="return confirm('Event archivieren?')">
                                        <input type="hidden" name="action" value="archive_event">
                                        <input type="hidden" name="event_id" value="<?= $ev['id'] ?>">
                                        <?= csrf_field() ?>
                                        <button class="text-xs text-gray-500 hover:text-gray-700">Archivieren</button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" class="inline" onsubmit="return confirm('Event endgültig löschen? Alle Daten gehen verloren!')">
                                    <input type="hidden" name="action" value="delete_event">
                                    <input type="hidden" name="event_id" value="<?= $ev['id'] ?>">
                                    <?= csrf_field() ?>
                                    <button class="text-xs text-red-500 hover:text-red-700">Löschen</button>
                                </form>
                            </div>
                        </div>

                        <!-- Aggregierte Statistiken (keine Namen/E-Mails) -->
                        <div class="grid grid-cols-4 gap-3 mt-3">
                            <div class="text-center bg-gray-50 rounded-lg p-2">
                                <div class="text-lg font-bold text-gray-900"><?= $ev['member_count'] ?></div>
                                <div class="text-xs text-gray-500">Teilnehmer</div>
                            </div>
                            <div class="text-center bg-gray-50 rounded-lg p-2">
                                <div class="text-lg font-bold text-gray-900"><?= $ev['session_count'] ?></div>
                                <div class="text-xs text-gray-500">Termine</div>
                            </div>
                            <div class="text-center bg-gray-50 rounded-lg p-2">
                                <div class="text-lg font-bold text-gray-900"><?= $ev['total_present'] ?></div>
                                <div class="text-xs text-gray-500">Teilnahmen</div>
                            </div>
                            <div class="text-center bg-gray-50 rounded-lg p-2">
                                <div class="text-lg font-bold text-gray-900"><?= format_currency((float)$ev['total_penalties']) ?></div>
                                <div class="text-xs text-gray-500">Team-Kasse</div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── Globale Einstellungen ──────────────────────────────── -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h2 class="text-lg font-bold text-gray-900 mb-4">Globale Einstellungen</h2>

        <form method="POST">
            <input type="hidden" name="action" value="save_settings">
            <?= csrf_field() ?>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Organisationsname</label>
                    <input type="text" name="organization_name" value="<?= e($orgName) ?>"
                           class="w-full border rounded-lg p-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Administrator E-Mail</label>
                    <input type="email" name="admin_email" value="<?= e($adminEmail) ?>"
                           class="w-full border rounded-lg p-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500">
                    <p class="text-xs text-gray-400 mt-1">Wird auf Fehlerseiten als Kontaktadresse angezeigt.</p>
                </div>
            </div>

            <button type="submit" class="mt-4 bg-gray-900 text-white font-semibold px-6 py-2 rounded-lg hover:bg-gray-800 transition">
                Speichern
            </button>
        </form>
    </div>
</div>

<footer class="text-center text-xs text-gray-400 py-6">
    <?= e(APP_NAME) ?> v<?= APP_VERSION ?>
</footer>

</body>
</html>
