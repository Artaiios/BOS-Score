<?php
/**
 * BOS-Score v1.1.0 – Server-Admin UI (Tab-basiert)
 * Tabs: Uebersicht / Events / Server-Logs / Einstellungen / Benutzerverwaltung
 * Nur aggregierte Statistiken — keine personenbezogenen Teilnehmer-Daten.
 */

require_once __DIR__ . '/../lib/mail.php';

$orgName = get_server_config('organization_name', APP_NAME);
$adminEmail = get_server_config('admin_email', '');
$tab = $_GET['tab'] ?? 'overview';

$successMsg = $_SESSION['admin_success'] ?? '';
$errorMsg = $_SESSION['admin_error'] ?? '';
unset($_SESSION['admin_success'], $_SESSION['admin_error']);

// ── POST-Aktionen ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['admin_error'] = 'Ungueltige Anfrage.';
        redirect('admin.php?tab=' . $tab);
    }
    $action = $_POST['action'] ?? '';
    $rUrl = 'admin.php?tab=' . $tab;

    // Event erstellen
    if ($action === 'create_event') {
        $eventName = trim($_POST['event_name'] ?? '');
        $eventOrg = trim($_POST['event_org'] ?? '');
        $d2Date = $_POST['d2_date'] ?? ''; $d2Count = (int)($_POST['d2_count'] ?? 20);
        $d1Enabled = !empty($_POST['d1_enabled']);
        $d1Date = $_POST['d1_date'] ?? ''; $d1Count = (int)($_POST['d1_count'] ?? 11);
        $adminEmails = trim($_POST['admin_emails'] ?? '');
        $themeColor = $_POST['theme_primary'] ?? '#dc2626';

        if (empty($eventName) || empty($d2Date)) {
            $_SESSION['admin_error'] = 'Eventname und Hauptfrist sind Pflichtfelder.';
            redirect($rUrl);
        }

        $result = create_event($eventName, $d2Date, $d2Count, $d1Date, $d1Count, $d1Enabled, $eventOrg);
        $eventId = $result['id'];
        update_event($eventId, ['theme_primary' => $themeColor]);
        add_event_role($eventId, $user['id'], 'admin', $user['id']);
        audit_log($eventId, $user['id'], 'event_created', "Event erstellt: $eventName");

        if (!empty($adminEmails)) {
            $emails = array_filter(array_map('trim', preg_split('/[\n,;]+/', $adminEmails)));
            foreach ($emails as $email) {
                $email = strtolower($email);
                if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $email === $user['email']) continue;
                $token = create_admin_invitation($eventId, $email, $user['id']);
                send_admin_invitation_mail($email, $eventName, $token, $user['display_name']);
                audit_log($eventId, $user['id'], 'admin_invited', "Admin eingeladen: $email");
            }
        }
        $_SESSION['admin_success'] = 'Event "' . $eventName . '" erstellt.';
        redirect($rUrl);
    }
    if ($action === 'archive_event') {
        $eId = (int)($_POST['event_id'] ?? 0); $ev = get_event_by_id($eId);
        if ($ev) { update_event($eId, ['status' => 'archived']); audit_log($eId, $user['id'], 'event_archived', "Event archiviert: {$ev['name']}"); $_SESSION['admin_success'] = 'Event "' . $ev['name'] . '" archiviert.'; }
        redirect($rUrl);
    }
    if ($action === 'reactivate_event') {
        $eId = (int)($_POST['event_id'] ?? 0); $ev = get_event_by_id($eId);
        if ($ev) { update_event($eId, ['status' => 'active']); audit_log($eId, $user['id'], 'event_reactivated', "Event reaktiviert: {$ev['name']}"); $_SESSION['admin_success'] = 'Event "' . $ev['name'] . '" reaktiviert.'; }
        redirect($rUrl);
    }
    if ($action === 'delete_event') {
        $eId = (int)($_POST['event_id'] ?? 0); $ev = get_event_by_id($eId);
        if ($ev) { audit_log(null, $user['id'], 'event_deleted', "Event geloescht: {$ev['name']}"); delete_event($eId); $_SESSION['admin_success'] = 'Event "' . $ev['name'] . '" geloescht.'; }
        redirect($rUrl);
    }
    if ($action === 'save_settings') {
        $newOrgName = trim($_POST['organization_name'] ?? '');
        $newAdminEmail = trim($_POST['admin_email'] ?? '');
        if (!empty($newOrgName)) set_server_config('organization_name', $newOrgName);
        if (!empty($newAdminEmail)) set_server_config('admin_email', $newAdminEmail);
        $_SESSION['admin_success'] = 'Einstellungen gespeichert.';
        redirect($rUrl);
    }
    // Server-Admin hinzufuegen
    if ($action === 'add_server_admin') {
        $email = trim(strtolower($_POST['sa_email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['admin_error'] = 'Ungueltige E-Mail-Adresse.'; redirect($rUrl);
        }
        $target = get_user_by_email($email);
        if (!$target) {
            $_SESSION['admin_error'] = 'Kein Account mit dieser E-Mail gefunden. Der Benutzer muss sich zuerst registrieren.';
            redirect($rUrl);
        }
        if (is_server_admin($target['id'])) {
            $_SESSION['admin_error'] = $target['display_name'] . ' ist bereits Server-Admin.';
            redirect($rUrl);
        }
        add_event_role(null, $target['id'], 'server_admin', $user['id']);
        send_server_admin_invitation_mail($email, $user['display_name']);
        audit_log(null, $user['id'], 'server_admin_added', "Server-Admin hinzugefuegt: $email");
        $_SESSION['admin_success'] = $target['display_name'] . ' wurde als Server-Admin hinzugefuegt.';
        redirect($rUrl);
    }
    // Server-Admin entfernen
    if ($action === 'remove_server_admin') {
        $targetId = (int)($_POST['user_id'] ?? 0);
        if ($targetId === $user['id']) { $_SESSION['admin_error'] = 'Du kannst dich nicht selbst entfernen.'; redirect($rUrl); }
        $serverAdmins = get_all_server_admins();
        if (count($serverAdmins) <= 1) { $_SESSION['admin_error'] = 'Der letzte Server-Admin kann nicht entfernt werden.'; redirect($rUrl); }
        $target = get_user_by_id($targetId);
        if ($target) {
            remove_server_admin_role($targetId);
            audit_log(null, $user['id'], 'server_admin_removed', "Server-Admin entfernt: {$target['email']}");
            $_SESSION['admin_success'] = $target['display_name'] . ' wurde als Server-Admin entfernt.';
        }
        redirect($rUrl);
    }
    // Audit-Log CSV Export
    if ($action === 'export_server_audit') {
        $logs = get_global_audit_log(10000);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="server-audit-log-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Zeitpunkt', 'Event', 'Benutzer', 'Typ', 'Beschreibung'], ';');
        foreach ($logs as $l) { fputcsv($out, [$l['created_at'], $l['event_name'] ?? 'Global', $l['user_name'] ?? '-', $l['action_type'], $l['action_description']], ';'); }
        fclose($out); exit;
    }
}

// Daten laden
$events = get_event_stats_overview();
$serverAdmins = get_all_server_admins();

$baseTabUrl = 'admin.php?tab=';
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

<!-- Navigation -->
<nav class="bg-gray-900 text-white shadow-lg">
    <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-1.5 text-sm">
            <a href="index.php" class="flex items-center gap-1 text-gray-400 hover:text-white">
                <span class="text-base">🏠</span><span class="hidden sm:inline"><?= e(APP_NAME) ?></span>
            </a>
            <span class="text-gray-600">/</span>
            <span class="font-bold text-white">🔧 Server-Administration</span>
        </div>
        <div class="flex items-center gap-4">
            <a href="profile.php" class="text-sm text-gray-300 hover:text-white"><?= e($user['display_name']) ?></a>
            <a href="index.php?logout" class="text-sm text-red-400 hover:text-red-300">Abmelden</a>
        </div>
    </div>
</nav>

<div class="max-w-5xl mx-auto px-4 py-8">

<?php if ($successMsg): ?><div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-6"><p class="text-green-700 text-sm">✅ <?= e($successMsg) ?></p></div><?php endif; ?>
<?php if ($errorMsg): ?><div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6"><p class="text-red-700 text-sm">❌ <?= e($errorMsg) ?></p></div><?php endif; ?>

<!-- Tabs -->
<div class="mb-6 flex flex-wrap gap-2 border-b pb-3">
<?php
$tabs = [
    'overview' => '📊 Uebersicht',
    'events' => '📋 Events (' . count($events) . ')',
    'logs' => '📝 Server-Logs',
    'settings' => '⚙️ Einstellungen',
    'users' => '👥 Benutzerverwaltung',
];
foreach ($tabs as $key => $label):
    $active = $tab === $key;
?>
<a href="<?= $baseTabUrl . $key ?>"
   class="px-3 py-2 rounded-lg text-sm font-medium transition <?= $active ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>"><?= $label ?></a>
<?php endforeach; ?>
</div>

<?php
// ══════════════════════════════════════════════════════════════
// TAB: Uebersicht
// ══════════════════════════════════════════════════════════════
if ($tab === 'overview'):
    $activeEvents = count(array_filter($events, fn($e) => $e['status'] === 'active'));
    $totalMembers = array_sum(array_column($events, 'member_count'));
    $totalPenalties = array_sum(array_map(fn($e) => (float)$e['total_penalties'], $events));
?>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border p-4 text-center"><div class="text-2xl font-bold text-gray-900"><?= count($events) ?></div><div class="text-gray-500 text-sm">Events gesamt</div></div>
    <div class="bg-white rounded-xl shadow-sm border p-4 text-center"><div class="text-2xl font-bold text-green-600"><?= $activeEvents ?></div><div class="text-gray-500 text-sm">Aktive Events</div></div>
    <div class="bg-white rounded-xl shadow-sm border p-4 text-center"><div class="text-2xl font-bold text-gray-900"><?= $totalMembers ?></div><div class="text-gray-500 text-sm">Teilnehmer gesamt</div></div>
    <div class="bg-white rounded-xl shadow-sm border p-4 text-center"><div class="text-2xl font-bold text-red-600"><?= format_currency($totalPenalties) ?></div><div class="text-gray-500 text-sm">Team-Kassen gesamt</div></div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="font-bold text-gray-800 mb-4">Server-Admins (<?= count($serverAdmins) ?>)</h3>
        <?php foreach ($serverAdmins as $sa): ?>
        <div class="flex items-center justify-between py-2 border-b last:border-0">
            <div><span class="font-medium text-gray-800"><?= e($sa['display_name']) ?></span> <span class="text-xs text-gray-400 ml-1"><?= e($sa['email']) ?></span></div>
            <span class="text-xs text-gray-400">seit <?= format_date($sa['granted_at']) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="font-bold text-gray-800 mb-4">Letzte Aktivitaeten</h3>
        <?php $recentLogs = get_global_audit_log(8); ?>
        <?php foreach ($recentLogs as $l): ?>
        <div class="py-2 border-b last:border-0">
            <div class="flex items-center justify-between">
                <span class="text-xs bg-gray-100 px-2 py-0.5 rounded-full"><?= e($l['action_type']) ?></span>
                <span class="text-xs text-gray-400"><?= format_datetime($l['created_at']) ?></span>
            </div>
            <p class="text-xs text-gray-600 mt-0.5"><?= e($l['action_description']) ?><?php if ($l['event_name']): ?> <span class="text-gray-400">· <?= e($l['event_name']) ?></span><?php endif; ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border p-5">
    <h3 class="font-bold text-gray-800 mb-3">System-Informationen</h3>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
        <div><span class="text-gray-500">Version:</span> <span class="font-medium"><?= e(APP_VERSION) ?></span></div>
        <div><span class="text-gray-500">PHP:</span> <span class="font-medium"><?= phpversion() ?></span></div>
        <div><span class="text-gray-500">Organisation:</span> <span class="font-medium"><?= e($orgName) ?></span></div>
        <div><span class="text-gray-500">Kontakt:</span> <span class="font-medium"><?= e($adminEmail ?: '-') ?></span></div>
    </div>
</div>

<?php
// ══════════════════════════════════════════════════════════════
// TAB: Events
// ══════════════════════════════════════════════════════════════
elseif ($tab === 'events'): ?>

<!-- Event erstellen -->
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <h2 class="text-lg font-bold text-gray-900 mb-4">Neues Event erstellen</h2>
    <form method="POST">
        <input type="hidden" name="action" value="create_event"><?= csrf_field() ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Eventname *</label>
                <input type="text" name="event_name" required placeholder="z.B. Leistungsabzeichen Bronze 2026" class="w-full border rounded-lg p-2 text-sm"></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Organisation (optional)</label>
                <input type="text" name="event_org" placeholder="Ueberschreibt globalen Namen" class="w-full border rounded-lg p-2 text-sm"></div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Hauptfrist (Datum) *</label>
                <input type="date" name="d2_date" required class="w-full border rounded-lg p-2 text-sm"></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Benoetigte Teilnahmen</label>
                <input type="number" name="d2_count" value="20" min="1" class="w-full border rounded-lg p-2 text-sm"></div>
        </div>
        <div class="mb-4">
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="d1_enabled" value="1" class="rounded text-red-600"
                       onchange="document.getElementById('d1_fields').classList.toggle('hidden', !this.checked)">
                Zwischenfrist aktivieren
            </label>
            <div id="d1_fields" class="hidden grid grid-cols-2 gap-4 mt-3">
                <div><label class="block text-xs font-semibold text-gray-600 mb-1">Zwischenfrist</label><input type="date" name="d1_date" class="w-full border rounded-lg p-2 text-sm"></div>
                <div><label class="block text-xs font-semibold text-gray-600 mb-1">Teilnahmen</label><input type="number" name="d1_count" value="11" min="1" class="w-full border rounded-lg p-2 text-sm"></div>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Event-Farbe</label>
                <input type="color" name="theme_primary" value="#dc2626" class="h-10 w-20 rounded border cursor-pointer"></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Event-Admins einladen</label>
                <textarea name="admin_emails" rows="2" placeholder="E-Mail-Adressen (komma- oder zeilengetrennt)" class="w-full border rounded-lg p-2 text-sm"></textarea>
                <p class="text-xs text-gray-400 mt-1">Du wirst automatisch als Admin hinzugefuegt.</p></div>
        </div>
        <button type="submit" class="bg-red-600 text-white font-semibold px-6 py-2 rounded-lg hover:bg-red-700 transition">🚀 Event erstellen</button>
    </form>
</div>

<!-- Event-Liste -->
<div class="bg-white rounded-xl shadow-sm p-6">
    <h2 class="text-lg font-bold text-gray-900 mb-4">Events (<?= count($events) ?>)</h2>
    <?php if (empty($events)): ?><p class="text-gray-500 text-sm">Noch keine Events.</p>
    <?php else: ?>
    <div class="space-y-3">
    <?php foreach ($events as $ev):
        $tc = $ev['theme_primary'] ?? '#dc2626';
        $evAdmins = get_event_admins_for_event($ev['id']);
    ?>
        <div class="border rounded-xl p-4 border-l-4 <?= $ev['status'] === 'archived' ? 'opacity-60' : '' ?>" style="border-left-color:<?= e($tc) ?>;">
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="font-bold text-gray-900">
                        <a href="index.php?event=<?= e($ev['public_token'] ?? '') ?>&admin_view=1" class="hover:underline" style="color:<?= e($tc) ?>;"><?= e($ev['name']) ?></a>
                        <?php if ($ev['status'] === 'archived'): ?><span class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full ml-1">Archiviert</span><?php endif; ?>
                    </h3>
                    <?php if ($ev['organization_name']): ?><p class="text-xs text-gray-500"><?= e($ev['organization_name']) ?></p><?php endif; ?>
                    <!-- Event-Admins -->
                    <div class="mt-1 flex flex-wrap gap-1">
                        <?php foreach ($evAdmins as $ea): ?>
                        <span class="text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full">🔑 <?= e($ea['display_name']) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="flex gap-2 shrink-0">
                    <?php if ($ev['status'] === 'active'): ?>
                    <form method="POST" class="inline" onsubmit="return confirm('Event archivieren?')"><input type="hidden" name="action" value="archive_event"><input type="hidden" name="event_id" value="<?= $ev['id'] ?>"><?= csrf_field() ?>
                    <button class="text-xs text-gray-500 hover:text-gray-700">Archivieren</button></form>
                    <?php else: ?>
                    <form method="POST" class="inline"><input type="hidden" name="action" value="reactivate_event"><input type="hidden" name="event_id" value="<?= $ev['id'] ?>"><?= csrf_field() ?>
                    <button class="text-xs text-green-600 hover:text-green-700">Reaktivieren</button></form>
                    <?php endif; ?>
                    <form method="POST" class="inline" onsubmit="return confirm('Event endgueltig loeschen?')"><input type="hidden" name="action" value="delete_event"><input type="hidden" name="event_id" value="<?= $ev['id'] ?>"><?= csrf_field() ?>
                    <button class="text-xs text-red-500 hover:text-red-700">Loeschen</button></form>
                </div>
            </div>
            <div class="grid grid-cols-4 gap-3 mt-3">
                <div class="text-center bg-gray-50 rounded-lg p-2"><div class="text-lg font-bold text-gray-900"><?= $ev['member_count'] ?></div><div class="text-xs text-gray-500">Teilnehmer</div></div>
                <div class="text-center bg-gray-50 rounded-lg p-2"><div class="text-lg font-bold text-gray-900"><?= $ev['session_count'] ?></div><div class="text-xs text-gray-500">Termine</div></div>
                <div class="text-center bg-gray-50 rounded-lg p-2"><div class="text-lg font-bold text-gray-900"><?= $ev['total_present'] ?></div><div class="text-xs text-gray-500">Teilnahmen</div></div>
                <div class="text-center bg-gray-50 rounded-lg p-2"><div class="text-lg font-bold text-gray-900"><?= format_currency((float)$ev['total_penalties']) ?></div><div class="text-xs text-gray-500">Kasse</div></div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php
// ══════════════════════════════════════════════════════════════
// TAB: Server-Logs
// ══════════════════════════════════════════════════════════════
elseif ($tab === 'logs'):
    $filterType = $_GET['filter_type'] ?? '';
    $allLogs = get_global_audit_log(500);
    $logTypes = []; foreach ($allLogs as $l) { $logTypes[$l['action_type']] = true; }
    if ($filterType) { $allLogs = array_filter($allLogs, fn($l) => $l['action_type'] === $filterType); }
?>
<div class="bg-white rounded-xl shadow-sm p-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold text-gray-900">📝 Server-Logs (<?= count($allLogs) ?>)</h2>
        <div class="flex gap-2">
            <select onchange="location.href='<?= $baseTabUrl ?>logs&filter_type='+this.value" class="border rounded-lg px-3 py-1 text-sm">
                <option value="">Alle Typen</option>
                <?php foreach (array_keys($logTypes) as $at): ?><option value="<?= e($at) ?>" <?= $filterType===$at?'selected':'' ?>><?= e($at) ?></option><?php endforeach; ?>
            </select>
            <form method="POST"><input type="hidden" name="action" value="export_server_audit"><?= csrf_field() ?><button class="bg-gray-600 text-white px-3 py-1 rounded-lg text-sm font-semibold hover:bg-gray-700">📥 CSV</button></form>
        </div>
    </div>
    <?php if (empty($allLogs)): ?><p class="text-gray-400 text-sm">Keine Eintraege.</p>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Zeitpunkt</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Event</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Benutzer</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Typ</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Beschreibung</th>
            </tr></thead>
            <tbody class="divide-y">
            <?php foreach ($allLogs as $l): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-2 text-xs text-gray-400 whitespace-nowrap"><?= format_datetime($l['created_at']) ?></td>
                    <td class="px-3 py-2 text-xs"><?= e($l['event_name'] ?? 'Global') ?></td>
                    <td class="px-3 py-2 text-xs"><?= e($l['user_name'] ?? '-') ?></td>
                    <td class="px-3 py-2"><span class="text-xs bg-gray-100 px-2 py-0.5 rounded-full"><?= e($l['action_type']) ?></span></td>
                    <td class="px-3 py-2 text-xs text-gray-600"><?= e($l['action_description']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php
// ══════════════════════════════════════════════════════════════
// TAB: Einstellungen
// ══════════════════════════════════════════════════════════════
elseif ($tab === 'settings'): ?>
<div class="bg-white rounded-xl shadow-sm p-6">
    <h2 class="text-lg font-bold text-gray-900 mb-4">⚙️ Globale Einstellungen</h2>
    <form method="POST">
        <input type="hidden" name="action" value="save_settings"><?= csrf_field() ?>
        <div class="space-y-4 max-w-lg">
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Organisationsname</label>
                <input type="text" name="organization_name" value="<?= e($orgName) ?>" class="w-full border rounded-lg p-2 text-sm">
                <p class="text-xs text-gray-400 mt-1">Wird als Standard-Organisation fuer neue Events verwendet.</p></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Administrator E-Mail</label>
                <input type="email" name="admin_email" value="<?= e($adminEmail) ?>" class="w-full border rounded-lg p-2 text-sm">
                <p class="text-xs text-gray-400 mt-1">Wird auf Fehlerseiten als Kontaktadresse angezeigt.</p></div>
        </div>
        <button type="submit" class="mt-6 bg-gray-900 text-white font-semibold px-6 py-2 rounded-lg hover:bg-gray-800 transition">Speichern</button>
    </form>
</div>

<?php
// ══════════════════════════════════════════════════════════════
// TAB: Benutzerverwaltung
// ══════════════════════════════════════════════════════════════
elseif ($tab === 'users'):
    $allUsers = get_all_user_accounts();
?>
<!-- Server-Admins -->
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <h2 class="text-lg font-bold text-gray-900 mb-4">🔧 Server-Administratoren (<?= count($serverAdmins) ?>)</h2>
    <div class="divide-y mb-4">
    <?php foreach ($serverAdmins as $sa): ?>
        <div class="flex items-center justify-between py-3">
            <div>
                <span class="font-medium text-gray-800"><?= e($sa['display_name']) ?></span>
                <span class="text-xs text-gray-400 ml-2"><?= e($sa['email']) ?></span>
                <?php if ($sa['id'] === $user['id']): ?><span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full ml-1">Du</span><?php endif; ?>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400">seit <?= format_date($sa['granted_at']) ?></span>
                <?php if ($sa['granted_by_name']): ?><span class="text-xs text-gray-400">von <?= e($sa['granted_by_name']) ?></span><?php endif; ?>
                <?php if ($sa['id'] !== $user['id'] && count($serverAdmins) > 1): ?>
                <form method="POST" onsubmit="return confirm('Server-Admin-Rechte entfernen fuer <?= e($sa['display_name']) ?>?')">
                    <input type="hidden" name="action" value="remove_server_admin"><input type="hidden" name="user_id" value="<?= $sa['id'] ?>"><?= csrf_field() ?>
                    <button class="text-xs text-red-500 hover:text-red-700">Entfernen</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
    <form method="POST" class="flex gap-3">
        <input type="hidden" name="action" value="add_server_admin"><?= csrf_field() ?>
        <input type="email" name="sa_email" required placeholder="E-Mail des neuen Server-Admins" class="flex-1 border rounded-lg px-3 py-2 text-sm">
        <button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-gray-800 transition">+ Server-Admin</button>
    </form>
    <p class="text-xs text-gray-400 mt-2">Der Benutzer muss bereits einen Account haben (sich einmal registriert haben).</p>
</div>

<!-- Alle Benutzer -->
<div class="bg-white rounded-xl shadow-sm p-6">
    <h2 class="text-lg font-bold text-gray-900 mb-4">👥 Registrierte Benutzer (<?= count($allUsers) ?>)</h2>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Name</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">E-Mail</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600 hidden md:table-cell">Rollen</th>
                <th class="px-3 py-2 text-center text-xs font-semibold text-gray-600">Events</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Registriert</th>
            </tr></thead>
            <tbody class="divide-y">
            <?php foreach ($allUsers as $u): $isSA = is_server_admin($u['id']); ?>
                <tr class="hover:bg-gray-50 <?= $isSA ? 'bg-yellow-50' : '' ?>">
                    <td class="px-3 py-2">
                        <span class="font-medium text-gray-800"><?= e($u['display_name']) ?></span>
                        <?php if ($isSA): ?><span class="text-xs bg-gray-800 text-white px-1.5 py-0.5 rounded ml-1">Admin</span><?php endif; ?>
                    </td>
                    <td class="px-3 py-2 text-xs text-gray-500"><?= e($u['email']) ?></td>
                    <td class="px-3 py-2 text-xs text-gray-400 hidden md:table-cell"><?= e($u['roles_summary'] ?: '-') ?></td>
                    <td class="px-3 py-2 text-center text-xs"><?= $u['event_count'] ?></td>
                    <td class="px-3 py-2 text-xs text-gray-400"><?= format_date($u['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

</div>

<footer class="text-center text-xs text-gray-400 py-6">
    <?= e(APP_NAME) ?> v<?= APP_VERSION ?>
</footer>

</body>
</html>
