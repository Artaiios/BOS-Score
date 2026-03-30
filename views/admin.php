<?php
/**
 * BOS-Score v1.1.0 – Event-Admin UI
 * Änderungen v1.1: Breadcrumb, E-Mail in Teilnehmer, Ankündigungen,
 * Einstellungen mit Sub-Tabs (Allgemein/Strafenkatalog/Standort)
 */

require_once __DIR__ . '/../lib/mail.php';

$eventId = $event['id'];
$sessions = get_sessions($eventId);
$members = get_members($eventId, false);
$activeMembers = array_filter($members, fn($m) => $m['active']);
$penaltyTypes = get_penalty_types($eventId);
$allPenalties = get_penalties_for_event($eventId);
$totalPenalty = get_event_penalty_total($eventId);
$penaltyByType = get_penalty_stats_by_type($eventId);
$penaltyByMember = get_penalty_stats_by_member($eventId);
$sessionDurationGlobal = (int)($event['session_duration_hours'] ?? 3);
$nextSessionAdmin = get_next_session($sessions, $sessionDurationGlobal);
$adminRolesEnabled = (bool)($event['roles_enabled'] ?? false);
$themeColor = $event['theme_primary'] ?? '#dc2626';
$invitations = get_event_invitations($eventId);
$pendingRegs = get_pending_registrations($eventId);
$eventAdmins = get_event_admins($eventId);
$allRoles = get_roles($eventId);

$tab = $_GET['tab'] ?? 'overview';
$subtab = $_GET['subtab'] ?? '';
$isArchived = ($event['status'] === 'archived');

$successMsg = $_SESSION['event_admin_success'] ?? '';
$errorMsg = $_SESSION['event_admin_error'] ?? '';
unset($_SESSION['event_admin_success'], $_SESSION['event_admin_error']);

// ── POST-Aktionen ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['event_admin_error'] = 'Ungueltige Anfrage.';
        redirect("index.php?event=" . urlencode($event['public_token']) . "&admin_view=1&tab=$tab");
    }
    $action = $_POST['action'] ?? '';
    $rUrl = "index.php?event=" . urlencode($event['public_token']) . "&admin_view=1&tab=$tab" . ($subtab ? "&subtab=$subtab" : '');

    // Termin erstellen
    if ($action === 'create_session') {
        $date = $_POST['session_date'] ?? ''; $time = $_POST['session_time'] ?? '19:00'; $comment = trim($_POST['comment'] ?? '');
        if (!empty($date)) { create_session_entry($eventId, $date, $time, $comment); audit_log($eventId, $user['id'], 'session_created', "Termin: $date $time"); $_SESSION['event_admin_success'] = 'Termin erstellt.'; }
        redirect($rUrl);
    }
    if ($action === 'edit_session') {
        $sid = (int)$_POST['session_id']; $date = $_POST['session_date'] ?? ''; $time = $_POST['session_time'] ?? '19:00'; $comment = trim($_POST['comment'] ?? '');
        if ($sid && $date) { update_session_entry($sid, $date, $time, $comment); $_SESSION['event_admin_success'] = 'Termin gespeichert.'; }
        redirect($rUrl);
    }
    if ($action === 'delete_session') { delete_session_entry((int)($_POST['session_id'] ?? 0)); $_SESSION['event_admin_success'] = 'Termin geloescht.'; redirect($rUrl); }
    if ($action === 'bulk_import_sessions') {
        $lines = array_filter(array_map('trim', explode("\n", $_POST['bulk_sessions'] ?? ''))); $imported = 0;
        foreach ($lines as $line) { if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})\s+(\d{2}:\d{2})\s*(.*)?$/', $line, $m)) { create_session_entry($eventId, $m[3].'-'.$m[2].'-'.$m[1], $m[4], trim($m[5] ?? '')); $imported++; } }
        $_SESSION['event_admin_success'] = "$imported Termine importiert."; redirect($rUrl);
    }
    if ($action === 'set_attendance_form') {
        $sid = (int)$_POST['session_id']; $mid = (int)$_POST['member_id']; $st = $_POST['status'];
        if (in_array($st, ['present','excused','absent'])) { set_attendance($sid, $mid, $st, 'admin'); }
        redirect($rUrl . "&session_id=$sid");
    }
    if ($action === 'edit_member') {
        $mid = (int)$_POST['member_id']; $name = trim($_POST['member_name'] ?? ''); $role = trim($_POST['member_role'] ?? ''); $active = !empty($_POST['member_active']);
        if ($mid && $name) { update_member($mid, $name, $role, $active); $_SESSION['event_admin_success'] = 'Teilnehmer gespeichert.'; }
        redirect($rUrl);
    }
    if ($action === 'invite_participant') {
        $email = trim(strtolower($_POST['participant_email'] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $existing = get_user_by_email($email);
            if ($existing && has_event_role($existing['id'], $eventId, ['member','admin'])) {
                $_SESSION['event_admin_error'] = 'Diese E-Mail ist bereits registriert.';
            } else {
                $activeInv = null; foreach ($invitations as $inv) { if ($inv['invalidated_at'] === null) { $activeInv = $inv; break; } }
                if (!$activeInv) { $invToken = create_event_invitation($eventId, 'open', null); } else { $invToken = $activeInv['token']; }
                send_participant_invitation_mail($email, $event['name'], $invToken, $user['display_name']);
                $_SESSION['event_admin_success'] = "Einladung an $email gesendet.";
            }
        } else { $_SESSION['event_admin_error'] = 'Ungueltige E-Mail-Adresse.'; }
        redirect($rUrl);
    }
    if ($action === 'join_as_participant') {
        if (!has_event_role($user['id'], $eventId, ['member'])) {
            add_event_role($eventId, $user['id'], 'member', $user['id']);
            $linkedM = get_linked_member($user['id'], $eventId);
            if (!$linkedM) { $mId = create_member($eventId, $user['display_name'], $user['email']); link_member_account($mId, $user['id']); }
            $_SESSION['event_admin_success'] = 'Du bist jetzt auch als Teilnehmer registriert.';
        }
        redirect($rUrl);
    }
    if ($action === 'assign_penalty') {
        $mid = (int)$_POST['member_id']; $tid = (int)$_POST['penalty_type_id'];
        if ($mid && $tid) { create_penalty($mid, $tid, $_POST['penalty_date'] ?? date('Y-m-d'), trim($_POST['penalty_comment'] ?? '')); $_SESSION['event_admin_success'] = 'Strafe zugewiesen.'; }
        redirect($rUrl);
    }
    if ($action === 'delete_penalty') { delete_penalty((int)$_POST['penalty_id']); $_SESSION['event_admin_success'] = 'Strafe entfernt.'; redirect($rUrl); }
    if ($action === 'create_penalty_type') {
        $desc = trim($_POST['pt_description'] ?? ''); $amount = (float)($_POST['pt_amount'] ?? 0); $sort = (int)($_POST['pt_sort'] ?? 0);
        if ($desc && $amount > 0) { create_penalty_type($eventId, $desc, $amount, null, $sort); $_SESSION['event_admin_success'] = 'Straftyp erstellt.'; }
        redirect($rUrl);
    }
    if ($action === 'edit_penalty_type') {
        $ptId = (int)$_POST['pt_id']; $desc = trim($_POST['pt_description'] ?? ''); $amount = (float)($_POST['pt_amount'] ?? 0);
        $activeFrom = $_POST['pt_active_from'] ?? null; $ptActive = !empty($_POST['pt_active']); $sort = (int)($_POST['pt_sort'] ?? 0);
        if ($ptId && $desc) { update_penalty_type($ptId, $desc, $amount, $activeFrom ?: null, $ptActive, $sort); $_SESSION['event_admin_success'] = 'Straftyp gespeichert.'; }
        redirect($rUrl);
    }
    if ($action === 'delete_penalty_type') { delete_penalty_type((int)$_POST['pt_id']); $_SESSION['event_admin_success'] = 'Straftyp geloescht.'; redirect($rUrl); }
    if ($action === 'create_role') {
        $rName = trim($_POST['role_name'] ?? ''); $rSort = (int)($_POST['role_sort'] ?? 0);
        if ($rName) { create_role($eventId, $rName, $rSort); $_SESSION['event_admin_success'] = 'Rolle "' . $rName . '" erstellt.'; }
        redirect($rUrl);
    }
    if ($action === 'delete_role') { delete_role((int)$_POST['role_id']); $_SESSION['event_admin_success'] = 'Rolle geloescht.'; redirect($rUrl); }
    if ($action === 'set_member_roles') {
        $mid = (int)$_POST['member_id']; $roleIds = $_POST['role_ids'] ?? [];
        if ($mid) { set_member_roles($mid, array_map('intval', (array)$roleIds)); $_SESSION['event_admin_success'] = 'Rollen aktualisiert.'; }
        redirect($rUrl);
    }
    // Einstellungen Allgemein
    if ($action === 'save_settings_general') {
        update_event($eventId, [
            'name' => trim($_POST['event_name'] ?? $event['name']),
            'status' => $_POST['event_status'] ?? $event['status'],
            'organization_name' => trim($_POST['org_name'] ?? ''),
            'contact_email' => trim($_POST['contact_email'] ?? ''),
            'deadline_2_date' => $_POST['d2_date'] ?? $event['deadline_2_date'],
            'deadline_2_count' => (int)($_POST['d2_count'] ?? $event['deadline_2_count']),
            'deadline_2_name' => trim($_POST['d2_name'] ?? 'Hauptfrist'),
            'deadline_1_enabled' => !empty($_POST['d1_enabled']) ? 1 : 0,
            'deadline_1_date' => $_POST['d1_date'] ?? $event['deadline_1_date'],
            'deadline_1_count' => (int)($_POST['d1_count'] ?? $event['deadline_1_count']),
            'deadline_1_name' => trim($_POST['d1_name'] ?? 'Zwischenfrist'),
            'session_duration_hours' => (int)($_POST['duration'] ?? 3),
            'auto_confirm_registration' => !empty($_POST['auto_confirm']) ? 1 : 0,
            'roles_enabled' => !empty($_POST['roles_enabled']) ? 1 : 0,
            'theme_primary' => $_POST['theme_primary'] ?? '#dc2626',
        ]);
        $_SESSION['event_admin_success'] = 'Einstellungen gespeichert.'; redirect($rUrl);
    }
    // Einstellungen Standort
    if ($action === 'save_settings_location') {
        update_event($eventId, [
            'weather_location' => trim($_POST['weather_location'] ?? ''),
            'weather_lat' => (float)($_POST['weather_lat'] ?? 0),
            'weather_lng' => (float)($_POST['weather_lng'] ?? 0),
        ]);
        $_SESSION['event_admin_success'] = 'Standort gespeichert.'; redirect($rUrl);
    }
    // Ankuendigung speichern
    if ($action === 'save_announcement') {
        $text = trim($_POST['announcement_text'] ?? '');
        $days = (int)($_POST['announcement_days'] ?? 0);
        $expiresAt = $days > 0 && !empty($text) ? (new DateTime())->modify("+$days days")->format('Y-m-d H:i:s') : null;
        update_event($eventId, ['announcement_text' => $text ?: null, 'announcement_expires_at' => $expiresAt]);
        $_SESSION['event_admin_success'] = empty($text) ? 'Ankuendigung entfernt.' : "Ankuendigung fuer $days Tage gesetzt.";
        redirect($rUrl);
    }
    // Event reaktivieren
    if ($action === 'reactivate_event') {
        update_event($eventId, ['status' => 'active']); $_SESSION['event_admin_success'] = 'Event reaktiviert.'; redirect($rUrl);
    }
    if ($action === 'create_invitation_form') {
        create_event_invitation($eventId, $_POST['reg_mode'] ?? 'open', $_POST['reg_until'] ?? null);
        $_SESSION['event_admin_success'] = 'Einladungslink erstellt.'; redirect($rUrl);
    }
    if ($action === 'invalidate_invitation') {
        invalidate_event_invitation((int)$_POST['invitation_id']); $_SESSION['event_admin_success'] = 'Link deaktiviert.'; redirect($rUrl);
    }
    if ($action === 'invite_admin_form') {
        $email = trim(strtolower($_POST['admin_email'] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $token = create_admin_invitation($eventId, $email, $user['id']);
            send_admin_invitation_mail($email, $event['name'], $token, $user['display_name']);
            $_SESSION['event_admin_success'] = "Admin-Einladung an $email gesendet.";
        }
        redirect($rUrl);
    }
    if ($action === 'confirm_reg') {
        $regId = (int)$_POST['reg_id'];
        $stmt = get_pdo()->prepare("SELECT * FROM user_registrations WHERE id = ? AND event_id = ? AND status = 'pending'");
        $stmt->execute([$regId, $eventId]); $reg = $stmt->fetch();
        if ($reg) {
            confirm_registration($regId);
            $eu = get_user_by_email($reg['email']);
            if (!$eu) { $nuid = create_user_account($reg['email'], $reg['name']); log_consent($nuid, PRIVACY_VERSION, ''); } else { $nuid = $eu['id']; }
            add_event_role($eventId, $nuid, 'member', $user['id']);
            $mId = create_member($eventId, $reg['name'], $reg['email']); link_member_account($mId, $nuid);
            $tk = create_magic_link($nuid, 'registration', false); send_magic_link_mail($reg['email'], $reg['name'], $tk, 'registration');
            $_SESSION['event_admin_success'] = $reg['name'] . ' bestaetigt.';
        }
        redirect($rUrl);
    }
    if ($action === 'reject_reg') { reject_registration((int)$_POST['reg_id']); $_SESSION['event_admin_success'] = 'Registrierung abgelehnt.'; redirect($rUrl); }
    if ($action === 'export_audit') {
        $logs = get_audit_log($eventId, null, null, 10000);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="audit-log-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w'); fputcsv($out, ['Zeitpunkt', 'Benutzer', 'Typ', 'Beschreibung'], ';');
        foreach ($logs as $l) { fputcsv($out, [$l['created_at'], $l['user_name'] ?? '-', $l['action_type'], $l['action_description']], ';'); }
        fclose($out); exit;
    }
    if ($action === 'toggle_roles') {
        update_event($eventId, ['roles_enabled' => !empty($_POST['enabled']) ? 1 : 0]);
        json_response(['success' => true]);
    }
}

$breadcrumbLevel = 'event_admin';
$pageTitle = 'Verwaltung - ' . $event['name'];
$baseTabUrl = 'index.php?event=' . e($event['public_token']) . '&admin_view=1&tab=';
require __DIR__ . '/partials/header.php';
?>

<?php if ($isArchived): ?>
<div class="bg-yellow-50 border border-yellow-300 rounded-xl p-4 mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <p class="text-yellow-800 text-sm font-semibold">📦 Dieses Event ist archiviert.</p>
    <form method="POST"><input type="hidden" name="action" value="reactivate_event"><?= csrf_field() ?>
    <button type="submit" class="bg-green-600 text-white px-5 py-2 rounded-lg text-sm font-bold hover:bg-green-700 transition">🔄 Reaktivieren</button></form>
</div>
<?php endif; ?>

<?php if ($successMsg): ?><div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-6"><p class="text-green-700 text-sm">✅ <?= e($successMsg) ?></p></div><?php endif; ?>
<?php if ($errorMsg): ?><div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6"><p class="text-red-700 text-sm">❌ <?= e($errorMsg) ?></p></div><?php endif; ?>

<!-- Tabs -->
<div class="mb-6 flex flex-wrap gap-2 border-b pb-3">
<?php
$tabs = [
    'overview' => '📊 Uebersicht',
    'invitations' => '📨 Einladungen' . (count($pendingRegs) > 0 ? ' (' . count($pendingRegs) . ')' : ''),
    'attendance' => '✅ Anwesenheit',
    'members' => '👥 Teilnehmer',
    'penalties' => '💰 Team-Kasse',
    'sessions' => '📅 Termine',
    'roles' => '🏷️ Rollen',
    'admins' => '🔑 Admins',
    'settings' => '⚙️ Einstellungen',
    'audit' => '📝 Audit-Log',
];
foreach ($tabs as $key => $label):
    $active = $tab === $key;
?>
<a href="<?= $baseTabUrl . $key ?>"
   class="px-3 py-2 rounded-lg text-sm font-medium transition <?= $active ? 'text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>"
   <?= $active ? 'style="background-color:' . e($themeColor) . ';"' : '' ?>><?= $label ?></a>
<?php endforeach; ?>
</div>

<?php
// ══════════════════════════════════════════════════════════════
// TAB: Uebersicht
// ══════════════════════════════════════════════════════════════
if ($tab === 'overview'): ?>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border p-4 text-center"><div class="text-2xl font-bold text-gray-900"><?= count($activeMembers) ?></div><div class="text-gray-500 text-sm">Teilnehmer</div></div>
    <div class="bg-white rounded-xl shadow-sm border p-4 text-center"><div class="text-2xl font-bold text-gray-900"><?= count($sessions) ?></div><div class="text-gray-500 text-sm">Termine</div></div>
    <div class="bg-white rounded-xl shadow-sm border p-4 text-center"><div class="text-2xl font-bold text-gray-900"><?= count($allPenalties) ?></div><div class="text-gray-500 text-sm">Strafen</div></div>
    <div class="bg-white rounded-xl shadow-sm border p-4 text-center"><div class="text-2xl font-bold" style="color:<?= e($themeColor) ?>;"><?= format_currency($totalPenalty) ?></div><div class="text-gray-500 text-sm">Team-Kasse</div></div>
</div>

<!-- Ankuendigung verwalten -->
<div class="bg-white rounded-xl shadow-sm border p-5 mb-6">
    <h3 class="font-bold text-gray-800 mb-3">📢 Dashboard-Ankuendigung</h3>
    <?php
    $hasAnnouncement = !empty($event['announcement_text']) && (!$event['announcement_expires_at'] || new DateTime($event['announcement_expires_at']) > new DateTime());
    if ($hasAnnouncement): ?>
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-3 text-sm text-blue-800">
        <strong>Aktiv:</strong> <?= e($event['announcement_text']) ?>
        <?php if ($event['announcement_expires_at']): ?><br><span class="text-xs text-blue-600">Laeuft ab: <?= format_datetime($event['announcement_expires_at']) ?></span><?php endif; ?>
    </div>
    <?php endif; ?>
    <form method="POST" class="space-y-3">
        <input type="hidden" name="action" value="save_announcement"><?= csrf_field() ?>
        <textarea name="announcement_text" rows="2" placeholder="Nachricht fuer alle Teilnehmer im Dashboard..." class="w-full border rounded-lg p-2 text-sm"><?= e($event['announcement_text'] ?? '') ?></textarea>
        <div class="flex items-center gap-3">
            <label class="text-sm text-gray-600">Sichtbar fuer</label>
            <select name="announcement_days" class="border rounded-lg px-3 py-1 text-sm">
                <option value="3">3 Tage</option><option value="7" selected>7 Tage</option><option value="14">14 Tage</option><option value="30">30 Tage</option><option value="90">90 Tage</option>
            </select>
            <button type="submit" class="text-white px-4 py-1.5 rounded-lg text-sm font-semibold" style="background-color:<?= e($themeColor) ?>;">Speichern</button>
            <?php if ($hasAnnouncement): ?>
            <button type="submit" name="announcement_text" value="" class="text-xs text-red-600 hover:text-red-700">Entfernen</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm border p-5">
    <h3 class="font-bold text-gray-800 mb-2">🔗 Event-URL</h3>
    <input type="text" readonly value="<?= e(get_base_url() . '/index.php?event=' . $event['public_token']) ?>" class="w-full text-xs p-2 bg-gray-50 border rounded font-mono" onclick="this.select(); navigator.clipboard?.writeText(this.value);">
</div>

<?php
// ══════════════════════════════════════════════════════════════
// TAB: Einladungen
// ══════════════════════════════════════════════════════════════
elseif ($tab === 'invitations'): ?>
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <h2 class="text-lg font-bold text-gray-900 mb-4">📨 Einladungslinks</h2>
    <form method="POST" class="flex flex-wrap gap-3 mb-4">
        <input type="hidden" name="action" value="create_invitation_form"><?= csrf_field() ?>
        <select name="reg_mode" class="border rounded-lg px-3 py-2 text-sm"><option value="open">Offen</option><option value="until_date">Bis Datum</option><option value="closed">Geschlossen</option></select>
        <input type="datetime-local" name="reg_until" class="border rounded-lg px-3 py-2 text-sm">
        <button type="submit" class="text-white px-4 py-2 rounded-lg text-sm font-semibold" style="background-color:<?= e($themeColor) ?>;">Neuen Link</button>
    </form>
    <?php foreach ($invitations as $inv): $isValid = $inv['invalidated_at'] === null; ?>
    <div class="flex items-center gap-3 py-2 border-b last:border-0 <?= $isValid ? '' : 'opacity-50' ?>">
        <input type="text" readonly value="<?= e(get_base_url() . '/index.php?invite=' . $inv['token']) ?>" class="flex-1 text-xs font-mono bg-gray-50 border rounded px-2 py-1" onclick="this.select(); navigator.clipboard?.writeText(this.value);">
        <span class="text-xs text-gray-400"><?= e($inv['reg_mode']) ?></span>
        <?php if ($isValid): ?>
        <form method="POST" onsubmit="return confirm('Link deaktivieren?')"><input type="hidden" name="action" value="invalidate_invitation"><input type="hidden" name="invitation_id" value="<?= $inv['id'] ?>"><?= csrf_field() ?>
        <button class="text-xs text-red-600 font-semibold">Deaktivieren</button></form>
        <?php else: ?><span class="text-xs text-red-500">Deaktiviert</span><?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <h2 class="text-lg font-bold text-gray-900 mb-4">📧 Direkt einladen</h2>
    <p class="text-gray-500 text-sm mb-3">Der Teilnehmer muss sich selbst registrieren und der Datenschutzerklaerung zustimmen.</p>
    <form method="POST" class="flex gap-3">
        <input type="hidden" name="action" value="invite_participant"><?= csrf_field() ?>
        <input type="email" name="participant_email" required placeholder="E-Mail-Adresse" class="flex-1 border rounded-lg px-3 py-2 text-sm">
        <button type="submit" class="text-white px-4 py-2 rounded-lg text-sm font-semibold" style="background-color:<?= e($themeColor) ?>;">Senden</button>
    </form>
</div>
<div class="bg-white rounded-xl shadow-sm p-6">
    <h2 class="text-lg font-bold text-gray-900 mb-4">📋 Offene Registrierungen (<?= count($pendingRegs) ?>)</h2>
    <?php if (empty($pendingRegs)): ?><p class="text-gray-400 text-sm">Keine offenen Registrierungen.</p>
    <?php else: foreach ($pendingRegs as $reg): ?>
    <div class="flex items-center justify-between py-3 border-b last:border-0">
        <div><span class="font-medium"><?= e($reg['name']) ?></span> <span class="text-xs text-gray-400"><?= e($reg['email']) ?></span></div>
        <div class="flex gap-2">
            <form method="POST"><input type="hidden" name="action" value="confirm_reg"><input type="hidden" name="reg_id" value="<?= $reg['id'] ?>"><?= csrf_field() ?><button class="text-xs bg-green-100 text-green-800 px-3 py-1 rounded-full font-semibold">✅ Bestaetigen</button></form>
            <form method="POST" onsubmit="return confirm('Ablehnen?')"><input type="hidden" name="action" value="reject_reg"><input type="hidden" name="reg_id" value="<?= $reg['id'] ?>"><?= csrf_field() ?><button class="text-xs bg-red-100 text-red-800 px-3 py-1 rounded-full font-semibold">❌ Ablehnen</button></form>
        </div>
    </div>
    <?php endforeach; endif; ?>
</div>

<?php
// ══════════════════════════════════════════════════════════════
// TAB: Anwesenheit
// ══════════════════════════════════════════════════════════════
elseif ($tab === 'attendance'):
    $allAttData = [];
    foreach ($sessions as $s) {
        $sAtt = get_attendance_for_session($s['id']); $lookup = [];
        foreach ($sAtt as $a) { $lookup[$a['member_id']] = $a; }
        $allAttData[$s['id']] = ['present'=>count(array_filter($sAtt,fn($a)=>$a['status']==='present')),'excused'=>count(array_filter($sAtt,fn($a)=>$a['status']==='excused')),'absent'=>count(array_filter($sAtt,fn($a)=>$a['status']==='absent')),'members'=>$lookup];
    }
    $autoExpandId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : ($nextSessionAdmin ? $nextSessionAdmin['id'] : 0);
    if (empty($sessions) || empty($activeMembers)): ?>
<p class="text-gray-400 text-sm bg-white rounded-xl shadow-sm border p-6">Erstelle zuerst Termine und warte auf Teilnehmer.</p>
<?php else: foreach (array_reverse($sessions) as $s):
    $sData = $allAttData[$s['id']]; $ended = is_session_ended($s, $sessionDurationGlobal);
    $isExpanded = ($s['id'] === $autoExpandId); $isNext = $nextSessionAdmin && $s['id'] === $nextSessionAdmin['id'];
    $rowStyle = $isNext ? 'background-color:#fed7aa;border-left:5px solid #ea580c;font-weight:600;' : ($ended ? 'background-color:#f3f4f6;color:#9ca3af;' : '');
?>
<details class="mb-2 border rounded-xl overflow-hidden" <?= $isExpanded ? 'open' : '' ?>>
    <summary class="px-5 py-3 cursor-pointer hover:bg-gray-50" style="<?= $rowStyle ?>">
        <span class="text-sm font-medium"><?= format_weekday($s['session_date']) ?>, <?= format_date($s['session_date']) ?> – <?= format_time($s['session_time']) ?></span>
        <?php if ($s['comment']): ?><span class="text-xs text-gray-400 ml-2">(<?= e($s['comment']) ?>)</span><?php endif; ?>
        <?php if ($isNext): ?><span style="font-size:10px;background:#ea580c;color:white;padding:1px 6px;border-radius:9999px;margin-left:4px;">NAECHSTER</span><?php endif; ?>
        <span class="text-xs ml-3">✅<?= $sData['present'] ?> 🟡<?= $sData['excused'] ?> ❌<?= $sData['absent'] ?></span>
    </summary>
    <div class="px-5 py-3 bg-gray-50 border-t">
        <?php foreach ($activeMembers as $m): $mAtt = $sData['members'][$m['id']] ?? null; $curStatus = $mAtt['status'] ?? ''; ?>
        <div class="flex items-center justify-between py-1.5 border-b border-gray-100 last:border-0">
            <span class="text-sm text-gray-700"><?= e($m['name']) ?></span>
            <div class="flex gap-1">
                <?php foreach (['present'=>'✅','excused'=>'📝','absent'=>'❌'] as $st => $icon): ?>
                <form method="POST" class="inline"><input type="hidden" name="action" value="set_attendance_form"><input type="hidden" name="session_id" value="<?= $s['id'] ?>"><input type="hidden" name="member_id" value="<?= $m['id'] ?>"><input type="hidden" name="status" value="<?= $st ?>"><?= csrf_field() ?>
                <button class="w-8 h-8 rounded-full text-sm <?= $curStatus === $st ? 'ring-2 ring-offset-1 ring-gray-400 bg-gray-100' : 'hover:bg-gray-100' ?>"><?= $icon ?></button></form>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</details>
<?php endforeach; endif; ?>

<?php
// ══════════════════════════════════════════════════════════════
// TAB: Teilnehmer (mit E-Mail + Beitritt-Button)
// ══════════════════════════════════════════════════════════════
elseif ($tab === 'members'):
    $isAlsoMember = has_event_role($user['id'], $eventId, ['member']);
?>
<?php if (!$isAlsoMember): ?>
<div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6 flex items-center justify-between">
    <div><strong class="text-blue-800">Selbst teilnehmen?</strong> <span class="text-blue-600 text-sm">Du bist Admin, aber noch kein Teilnehmer.</span></div>
    <form method="POST"><input type="hidden" name="action" value="join_as_participant"><?= csrf_field() ?>
    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-blue-700 transition">👤 Beitreten</button></form>
</div>
<?php endif; ?>
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="px-5 py-4 border-b"><h3 class="font-bold text-gray-800"><?= count($members) ?> Teilnehmer</h3></div>
    <div class="divide-y">
    <?php foreach ($members as $m): ?>
        <details class="group">
            <summary class="px-5 py-3 flex items-center justify-between cursor-pointer hover:bg-gray-50">
                <div class="min-w-0">
                    <span class="font-medium <?= $m['active'] ? 'text-gray-800' : 'text-gray-400 line-through' ?>"><?= e($m['name']) ?></span>
                    <?php if ($m['role']): ?><span class="text-xs text-gray-400 ml-1">(<?= e($m['role']) ?>)</span><?php endif; ?>
                    <?php if (!$m['active']): ?><span class="text-xs bg-gray-200 text-gray-500 px-2 py-0.5 rounded-full ml-1">Inaktiv</span><?php endif; ?>
                    <?php if (!empty($m['email'])): ?><br><span class="text-xs text-gray-400"><?= e($m['email']) ?></span><?php endif; ?>
                </div>
                <span class="text-xs text-gray-400 group-open:hidden shrink-0">✏️ Bearbeiten</span>
            </summary>
            <div class="px-5 pb-4 bg-gray-50 border-t">
                <form method="POST" class="flex flex-wrap gap-3 mt-3">
                    <input type="hidden" name="action" value="edit_member"><input type="hidden" name="member_id" value="<?= $m['id'] ?>"><?= csrf_field() ?>
                    <input type="text" name="member_name" value="<?= e($m['name']) ?>" required class="border rounded-lg px-3 py-2 text-sm flex-1 min-w-[150px]" placeholder="Name">
                    <input type="text" name="member_role" value="<?= e($m['role']) ?>" class="border rounded-lg px-3 py-2 text-sm w-40" placeholder="Funktion">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="member_active" value="1" <?= $m['active'] ? 'checked' : '' ?> class="rounded text-red-600"> Aktiv</label>
                    <button type="submit" class="text-white px-4 py-2 rounded-lg text-sm font-semibold" style="background-color:<?= e($themeColor) ?>;">Speichern</button>
                </form>
            </div>
        </details>
    <?php endforeach; ?>
    </div>
</div>

<?php
// ══════════════════════════════════════════════════════════════
// TAB: Team-Kasse
// ══════════════════════════════════════════════════════════════
elseif ($tab === 'penalties'):
    $hasAnyPenalties = array_sum(array_column($penaltyByType, 'count')) > 0;
?>
<div class="bg-white rounded-xl shadow-sm border p-6 mb-6">
    <h2 class="text-lg font-bold text-gray-900 mb-4">💰 Strafe zuweisen</h2>
    <form method="POST" class="flex flex-wrap gap-3">
        <input type="hidden" name="action" value="assign_penalty"><?= csrf_field() ?>
        <select name="member_id" required class="border rounded-lg px-3 py-2 text-sm"><option value="">Teilnehmer...</option><?php foreach ($activeMembers as $m): ?><option value="<?= $m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?></select>
        <select name="penalty_type_id" required class="border rounded-lg px-3 py-2 text-sm"><option value="">Straftyp...</option><?php foreach ($penaltyTypes as $pt): if ($pt['active']): ?><option value="<?= $pt['id'] ?>"><?= e($pt['description']) ?> (<?= format_currency((float)$pt['amount']) ?>)</option><?php endif; endforeach; ?></select>
        <input type="date" name="penalty_date" value="<?= date('Y-m-d') ?>" class="border rounded-lg px-3 py-2 text-sm">
        <input type="text" name="penalty_comment" placeholder="Kommentar" class="border rounded-lg px-3 py-2 text-sm">
        <button type="submit" class="text-white px-4 py-2 rounded-lg text-sm font-semibold" style="background-color:<?= e($themeColor) ?>;">Zuweisen</button>
    </form>
</div>
<?php if ($hasAnyPenalties): ?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="font-bold text-gray-800 mb-4">Nach Typ</h3>
        <div style="max-width:240px;margin:0 auto 1rem;"><canvas id="chartPenaltyType"></canvas></div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="font-bold text-gray-800 mb-4">Nach Teilnehmer</h3>
        <div style="min-height:<?= max(200, count($penaltyByMember)*28) ?>px"><canvas id="chartPenaltyMember"></canvas></div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var ptData = <?= json_encode(array_values(array_filter($penaltyByType, fn($s) => (int)$s['count'] > 0))) ?>;
    if (ptData.length > 0) new Chart(document.getElementById('chartPenaltyType'), {type:'doughnut',data:{labels:ptData.map(d=>d.description+' ('+d.count+'x)'),datasets:[{data:ptData.map(d=>parseInt(d.count)),backgroundColor:['#dc2626','#f59e0b','#22c55e','#3b82f6','#8b5cf6','#ec4899','#14b8a6'],borderWidth:0}]},options:{responsive:true,cutout:'55%',plugins:{legend:{position:'bottom',labels:{boxWidth:10,padding:8}}}}});
    var pmData = <?= json_encode(array_values(array_filter($penaltyByMember, fn($s) => (float)$s['total'] > 0))) ?>;
    if (pmData.length > 0) { pmData.sort((a,b)=>b.total-a.total); new Chart(document.getElementById('chartPenaltyMember'), {type:'bar',data:{labels:pmData.map(d=>d.name),datasets:[{label:'EUR',data:pmData.map(d=>parseFloat(d.total)),backgroundColor:'<?= e($themeColor) ?>',borderRadius:4}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{beginAtZero:true},y:{grid:{display:false}}}}}); }
});
</script>
<?php endif; ?>
<div class="bg-white rounded-xl shadow-sm border p-6">
    <h3 class="font-bold text-gray-800 mb-3">Kassenstand: <?= format_currency($totalPenalty) ?></h3>
    <div class="divide-y text-sm">
    <?php foreach ($allPenalties as $p): ?>
        <div class="py-2 flex justify-between items-center">
            <div><span class="font-medium"><?= e($p['member_name']) ?></span> - <?= e($p['type_description']) ?><?php if($p['comment']):?> <span class="text-gray-400">(<?=e($p['comment'])?>)</span><?php endif;?></div>
            <div class="flex items-center gap-3">
                <span class="text-red-600 font-semibold"><?= format_currency((float)$p['amount']) ?></span>
                <span class="text-gray-400 text-xs"><?= format_date($p['penalty_date']) ?></span>
                <form method="POST" onsubmit="return confirm('Strafe entfernen?')"><input type="hidden" name="action" value="delete_penalty"><input type="hidden" name="penalty_id" value="<?= $p['id'] ?>"><?= csrf_field() ?><button class="text-xs text-red-400 hover:text-red-600">🗑️</button></form>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
</div>

<?php
// ══════════════════════════════════════════════════════════════
// TAB: Termine
// ══════════════════════════════════════════════════════════════
elseif ($tab === 'sessions'): ?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-1 space-y-6">
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="font-bold text-gray-800 mb-4">Termin erstellen</h3>
            <form method="POST" class="space-y-3">
                <input type="hidden" name="action" value="create_session"><?= csrf_field() ?>
                <input type="date" name="session_date" required class="w-full border rounded-lg p-2 text-sm">
                <input type="time" name="session_time" value="19:00" class="w-full border rounded-lg p-2 text-sm">
                <input type="text" name="comment" placeholder="Kommentar" class="w-full border rounded-lg p-2 text-sm">
                <button type="submit" class="w-full text-white py-2 rounded-lg font-semibold" style="background-color:<?= e($themeColor) ?>;">+ Termin</button>
            </form>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="font-bold text-gray-800 mb-4">Bulk-Import</h3>
            <p class="text-xs text-gray-400 mb-2">Format: DD.MM.YYYY HH:MM Kommentar</p>
            <form method="POST"><input type="hidden" name="action" value="bulk_import_sessions"><?= csrf_field() ?>
                <textarea name="bulk_sessions" rows="6" placeholder="01.01.2026 18:30 Kommentar..." class="w-full border rounded-lg p-2 text-sm mb-3 font-mono"></textarea>
                <button type="submit" class="w-full bg-gray-600 text-white py-2 rounded-lg font-semibold hover:bg-gray-700">Importieren</button>
            </form>
        </div>
    </div>
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="px-5 py-4 border-b"><h3 class="font-bold text-gray-800"><?= count($sessions) ?> Termine</h3></div>
            <div class="divide-y">
            <?php foreach ($sessions as $s): $isPast = $s['session_date'] < date('Y-m-d'); ?>
                <details class="group">
                    <summary class="px-5 py-3 flex items-center justify-between cursor-pointer hover:bg-gray-50 <?= $isPast ? 'bg-gray-50 text-gray-400' : '' ?>">
                        <div><span class="font-medium"><?= format_weekday($s['session_date']) ?>, <?= format_date($s['session_date']) ?></span> <span class="text-sm ml-2"><?= format_time($s['session_time']) ?></span><?php if($s['comment']):?> <span class="text-xs text-gray-400 ml-2"><?=e($s['comment'])?></span><?php endif;?></div>
                        <span class="text-xs text-gray-400 group-open:hidden">✏️</span>
                    </summary>
                    <div class="px-5 pb-4 bg-gray-50 border-t">
                        <form method="POST" class="flex flex-wrap gap-3 mt-3 items-end">
                            <input type="hidden" name="action" value="edit_session"><input type="hidden" name="session_id" value="<?= $s['id'] ?>"><?= csrf_field() ?>
                            <div><label class="text-xs text-gray-500">Datum</label><input type="date" name="session_date" value="<?= e($s['session_date']) ?>" required class="border rounded-lg px-3 py-2 text-sm"></div>
                            <div><label class="text-xs text-gray-500">Uhrzeit</label><input type="time" name="session_time" value="<?= e($s['session_time']) ?>" class="border rounded-lg px-3 py-2 text-sm"></div>
                            <div class="flex-1 min-w-[150px]"><label class="text-xs text-gray-500">Kommentar</label><input type="text" name="comment" value="<?= e($s['comment']) ?>" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
                            <button type="submit" class="text-white px-4 py-2 rounded-lg text-sm font-semibold" style="background-color:<?= e($themeColor) ?>;">Speichern</button>
                        </form>
                        <form method="POST" class="mt-2" onsubmit="return confirm('Termin loeschen?')"><input type="hidden" name="action" value="delete_session"><input type="hidden" name="session_id" value="<?= $s['id'] ?>"><?= csrf_field() ?><button class="text-xs text-red-500 hover:text-red-700">🗑️ Loeschen</button></form>
                    </div>
                </details>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php
// ══════════════════════════════════════════════════════════════
// TAB: Rollen
// ══════════════════════════════════════════════════════════════
elseif ($tab === 'roles'): ?>
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold text-gray-900">🏷️ Rollen</h2>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" <?= $adminRolesEnabled ? 'checked' : '' ?> onchange="fetch('api.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=toggle_roles&event_id=<?=$eventId?>&enabled='+(this.checked?'1':'0')+'&csrf_token=<?=csrf_token()?>'}).then(()=>location.reload())" class="rounded text-red-600"> Aktiviert</label>
    </div>
    <form method="POST" class="flex flex-wrap gap-3 mb-4">
        <input type="hidden" name="action" value="create_role"><?= csrf_field() ?>
        <input type="text" name="role_name" required placeholder="z.B. Gruppenfuehrer" class="border rounded-lg px-3 py-2 text-sm flex-1 min-w-[200px]">
        <input type="number" name="role_sort" value="0" min="0" class="border rounded-lg px-3 py-2 text-sm w-20" placeholder="Sort.">
        <button type="submit" class="text-white px-4 py-2 rounded-lg text-sm font-semibold" style="background-color:<?= e($themeColor) ?>;">+ Rolle</button>
    </form>
    <?php if (!empty($allRoles)): ?>
    <div class="divide-y mb-6">
    <?php foreach ($allRoles as $r): ?>
        <div class="py-2 flex items-center justify-between">
            <span class="font-medium text-gray-800"><?= e($r['name']) ?> <span class="text-xs text-gray-400">(Sort: <?= $r['sort_order'] ?>)</span></span>
            <form method="POST" onsubmit="return confirm('Rolle loeschen?')"><input type="hidden" name="action" value="delete_role"><input type="hidden" name="role_id" value="<?= $r['id'] ?>"><?= csrf_field() ?><button class="text-xs text-red-500">🗑️</button></form>
        </div>
    <?php endforeach; ?>
    </div>
    <h3 class="font-bold text-gray-800 mb-3">Zuweisung</h3>
    <?php foreach ($activeMembers as $m): $currentRoleIds = get_member_role_ids($m['id']); ?>
    <form method="POST" class="flex items-center gap-3 py-2 border-b last:border-0">
        <input type="hidden" name="action" value="set_member_roles"><input type="hidden" name="member_id" value="<?= $m['id'] ?>"><?= csrf_field() ?>
        <span class="text-sm text-gray-700 w-40 shrink-0"><?= e($m['name']) ?></span>
        <div class="flex flex-wrap gap-1 flex-1">
            <?php foreach ($allRoles as $r): $isAssigned = in_array($r['id'], $currentRoleIds); ?>
            <label class="text-xs px-2 py-1 rounded border cursor-pointer <?= $isAssigned ? 'bg-gray-700 text-white border-gray-700' : 'bg-white text-gray-500 border-gray-200 hover:bg-gray-50' ?>">
                <input type="checkbox" name="role_ids[]" value="<?= $r['id'] ?>" <?= $isAssigned ? 'checked' : '' ?> class="hidden"> <?= e($r['name']) ?>
            </label>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="text-xs text-gray-500 hover:text-gray-700 shrink-0">💾</button>
    </form>
    <?php endforeach; endif; ?>
</div>

<?php
// ══════════════════════════════════════════════════════════════
// TAB: Admins
// ══════════════════════════════════════════════════════════════
elseif ($tab === 'admins'): ?>
<div class="bg-white rounded-xl shadow-sm p-6">
    <h2 class="text-lg font-bold text-gray-900 mb-4">🔑 Event-Admins</h2>
    <?php foreach ($eventAdmins as $admin): ?>
    <div class="flex items-center justify-between py-2 border-b last:border-0">
        <div><span class="font-medium"><?= e($admin['display_name']) ?></span> <span class="text-xs text-gray-400 ml-2"><?= e($admin['email']) ?></span></div>
        <span class="text-xs text-gray-400">seit <?= format_date($admin['granted_at']) ?></span>
    </div>
    <?php endforeach; ?>
    <form method="POST" class="flex gap-3 mt-4">
        <input type="hidden" name="action" value="invite_admin_form"><?= csrf_field() ?>
        <input type="email" name="admin_email" required placeholder="E-Mail des neuen Admins" class="flex-1 border rounded-lg px-3 py-2 text-sm">
        <button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-gray-800">Admin einladen</button>
    </form>
</div>

<?php
// ══════════════════════════════════════════════════════════════
// TAB: Einstellungen (Sub-Tabs: Allgemein / Strafenkatalog / Standort)
// ══════════════════════════════════════════════════════════════
elseif ($tab === 'settings'):
    if (!$subtab) $subtab = 'general';
    $subTabUrl = $baseTabUrl . 'settings&subtab=';
?>
<!-- Sub-Tabs -->
<div class="flex gap-2 mb-4">
    <?php foreach (['general'=>'Allgemein','penalty_catalog'=>'Strafenkatalog','location'=>'Standort'] as $sk => $sl):
        $sActive = $subtab === $sk;
    ?>
    <a href="<?= $subTabUrl . $sk ?>" class="px-3 py-1.5 rounded-lg text-sm font-medium <?= $sActive ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>"><?= $sl ?></a>
    <?php endforeach; ?>
</div>

<?php if ($subtab === 'general'): ?>
<!-- ═══ Allgemein ═══ -->
<div class="bg-white rounded-xl shadow-sm p-6">
    <form method="POST">
        <input type="hidden" name="action" value="save_settings_general"><?= csrf_field() ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Eventname</label><input type="text" name="event_name" value="<?= e($event['name']) ?>" class="w-full border rounded-lg p-2 text-sm"></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Status</label>
                <select name="event_status" class="w-full border rounded-lg p-2 text-sm"><option value="active" <?= $event['status']==='active'?'selected':'' ?>>Aktiv</option><option value="archived" <?= $event['status']==='archived'?'selected':'' ?>>Archiviert</option></select></div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Organisation</label><input type="text" name="org_name" value="<?= e($event['organization_name'] ?? '') ?>" class="w-full border rounded-lg p-2 text-sm"></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Kontakt-E-Mail</label><input type="email" name="contact_email" value="<?= e($event['contact_email'] ?? '') ?>" placeholder="Wird auf Fehlerseiten angezeigt" class="w-full border rounded-lg p-2 text-sm"></div>
        </div>
        <h3 class="font-bold text-gray-800 mt-6 mb-3">Fristen</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Hauptfrist</label><input type="date" name="d2_date" value="<?= e($event['deadline_2_date']) ?>" class="w-full border rounded-lg p-2 text-sm"></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Teilnahmen</label><input type="number" name="d2_count" value="<?= $event['deadline_2_count'] ?>" min="1" class="w-full border rounded-lg p-2 text-sm"></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Name</label><input type="text" name="d2_name" value="<?= e($event['deadline_2_name'] ?? '') ?>" class="w-full border rounded-lg p-2 text-sm"></div>
        </div>
        <div class="mb-4"><label class="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="d1_enabled" value="1" <?= $event['deadline_1_enabled'] ? 'checked' : '' ?> class="rounded text-red-600"> Zwischenfrist aktivieren</label></div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Zwischenfrist</label><input type="date" name="d1_date" value="<?= e($event['deadline_1_date']) ?>" class="w-full border rounded-lg p-2 text-sm"></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Teilnahmen</label><input type="number" name="d1_count" value="<?= $event['deadline_1_count'] ?>" min="1" class="w-full border rounded-lg p-2 text-sm"></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Name</label><input type="text" name="d1_name" value="<?= e($event['deadline_1_name'] ?? '') ?>" class="w-full border rounded-lg p-2 text-sm"></div>
        </div>
        <h3 class="font-bold text-gray-800 mt-6 mb-3">Weitere Optionen</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Uebungsdauer (Std.)</label><input type="number" name="duration" value="<?= $sessionDurationGlobal ?>" min="1" max="12" class="w-full border rounded-lg p-2 text-sm"></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Event-Farbe</label><input type="color" name="theme_primary" value="<?= e($themeColor) ?>" class="h-10 w-20 rounded border cursor-pointer"></div>
            <div class="flex flex-col gap-2 mt-5">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="auto_confirm" value="1" <?= $event['auto_confirm_registration'] ? 'checked' : '' ?> class="rounded text-red-600"> Auto-Bestaetigung</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="roles_enabled" value="1" <?= $adminRolesEnabled ? 'checked' : '' ?> class="rounded text-red-600"> Rollen aktiviert</label>
            </div>
        </div>
        <button type="submit" class="text-white px-6 py-2 rounded-lg text-sm font-semibold" style="background-color:<?= e($themeColor) ?>;">Speichern</button>
    </form>
</div>

<?php elseif ($subtab === 'penalty_catalog'): ?>
<!-- ═══ Strafenkatalog ═══ -->
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <h2 class="text-lg font-bold text-gray-900 mb-4">Straftyp anlegen</h2>
    <form method="POST" class="flex flex-wrap gap-3">
        <input type="hidden" name="action" value="create_penalty_type"><?= csrf_field() ?>
        <input type="text" name="pt_description" required placeholder="Beschreibung" class="border rounded-lg px-3 py-2 text-sm flex-1 min-w-[200px]">
        <input type="number" name="pt_amount" step="0.01" min="0.01" required placeholder="Betrag" class="border rounded-lg px-3 py-2 text-sm w-28">
        <input type="number" name="pt_sort" value="0" min="0" class="border rounded-lg px-3 py-2 text-sm w-20" title="Sortierung">
        <button type="submit" class="text-white px-4 py-2 rounded-lg text-sm font-semibold" style="background-color:<?= e($themeColor) ?>;">+ Straftyp</button>
    </form>
</div>
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="px-5 py-4 border-b"><h3 class="font-bold text-gray-800"><?= count($penaltyTypes) ?> Straftypen</h3></div>
    <div class="divide-y">
    <?php foreach ($penaltyTypes as $pt): ?>
        <details class="group">
            <summary class="px-5 py-3 flex items-center justify-between cursor-pointer hover:bg-gray-50">
                <div>
                    <span class="font-medium <?= $pt['active'] ? 'text-gray-800' : 'text-gray-400 line-through' ?>"><?= e($pt['description']) ?></span>
                    <span class="text-sm text-red-600 font-semibold ml-2"><?= format_currency((float)$pt['amount']) ?></span>
                    <?php if (!$pt['active']): ?><span class="text-xs bg-gray-200 text-gray-500 px-2 py-0.5 rounded-full ml-1">Inaktiv</span><?php endif; ?>
                    <span class="text-xs text-gray-400 ml-2">Sort: <?= $pt['sort_order'] ?></span>
                </div>
                <span class="text-xs text-gray-400 group-open:hidden">✏️</span>
            </summary>
            <div class="px-5 pb-4 bg-gray-50 border-t">
                <form method="POST" class="flex flex-wrap gap-3 mt-3 items-end">
                    <input type="hidden" name="action" value="edit_penalty_type"><input type="hidden" name="pt_id" value="<?= $pt['id'] ?>"><?= csrf_field() ?>
                    <div class="flex-1 min-w-[200px]"><label class="text-xs text-gray-500">Beschreibung</label><input type="text" name="pt_description" value="<?= e($pt['description']) ?>" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
                    <div class="w-28"><label class="text-xs text-gray-500">Betrag</label><input type="number" name="pt_amount" step="0.01" value="<?= $pt['amount'] ?>" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
                    <div class="w-32"><label class="text-xs text-gray-500">Aktiv ab</label><input type="date" name="pt_active_from" value="<?= e($pt['active_from'] ?? '') ?>" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
                    <div class="w-20"><label class="text-xs text-gray-500">Sort.</label><input type="number" name="pt_sort" value="<?= $pt['sort_order'] ?>" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
                    <label class="flex items-center gap-1 text-sm"><input type="checkbox" name="pt_active" value="1" <?= $pt['active'] ? 'checked' : '' ?> class="rounded text-red-600"> Aktiv</label>
                    <button type="submit" class="text-white px-4 py-2 rounded-lg text-sm font-semibold" style="background-color:<?= e($themeColor) ?>;">Speichern</button>
                </form>
                <form method="POST" class="mt-2" onsubmit="return confirm('Straftyp loeschen?')">
                    <input type="hidden" name="action" value="delete_penalty_type"><input type="hidden" name="pt_id" value="<?= $pt['id'] ?>"><?= csrf_field() ?>
                    <button class="text-xs text-red-500 hover:text-red-700">🗑️ Loeschen</button>
                </form>
            </div>
        </details>
    <?php endforeach; ?>
    </div>
</div>

<?php elseif ($subtab === 'location'): ?>
<!-- ═══ Standort ═══ -->
<div class="bg-white rounded-xl shadow-sm p-6">
    <h2 class="text-lg font-bold text-gray-900 mb-4">🌤️ Wetter-Standort</h2>
    <div class="mb-4">
        <div class="flex gap-3 mb-2">
            <input type="text" id="weatherQuery" placeholder="Ortsname oder PLZ..." class="flex-1 border rounded-lg px-3 py-2 text-sm">
            <button type="button" onclick="geocodeLocation()" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-blue-700">📍 Suchen</button>
        </div>
        <div id="geocodeResults" class="hidden mb-2"></div>
    </div>
    <form method="POST">
        <input type="hidden" name="action" value="save_settings_location"><?= csrf_field() ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Standort</label><input type="text" name="weather_location" id="weatherLocation" value="<?= e($event['weather_location'] ?? '') ?>" class="w-full border rounded-lg p-2 text-sm"></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Breitengrad</label><input type="text" name="weather_lat" id="weatherLat" value="<?= $event['weather_lat'] ?>" class="w-full border rounded-lg p-2 text-sm"></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Laengengrad</label><input type="text" name="weather_lng" id="weatherLng" value="<?= $event['weather_lng'] ?>" class="w-full border rounded-lg p-2 text-sm"></div>
        </div>
        <button type="submit" class="text-white px-6 py-2 rounded-lg text-sm font-semibold" style="background-color:<?= e($themeColor) ?>;">Speichern</button>
    </form>
</div>
<script>
async function geocodeLocation() {
    var q = document.getElementById('weatherQuery').value.trim();
    if (!q) { alert('Bitte einen Ortsnamen eingeben.'); return; }
    var r = await fetch('https://geocoding-api.open-meteo.com/v1/search?name='+encodeURIComponent(q)+'&count=5&language=de');
    var data = await r.json(); var el = document.getElementById('geocodeResults');
    if (!data.results || data.results.length === 0) { el.innerHTML='<p class="text-red-500 text-xs">Kein Ort gefunden.</p>'; el.classList.remove('hidden'); return; }
    var html='<div class="space-y-1">';
    data.results.forEach(function(r) {
        var label = r.name + (r.admin1 ? ', ' + r.admin1 : '') + (r.country ? ' (' + r.country + ')' : '');
        html += '<button type="button" onclick="selectGeo(\''+r.name.replace(/\'/g,"\\'")+'\','+r.latitude+','+r.longitude+')" class="w-full text-left px-3 py-2 rounded-lg text-sm border hover:bg-blue-50 transition">📍 '+label+'</button>';
    });
    el.innerHTML=html+'</div>'; el.classList.remove('hidden');
}
function selectGeo(name,lat,lng) {
    document.getElementById('weatherLocation').value=name;
    document.getElementById('weatherLat').value=lat;
    document.getElementById('weatherLng').value=lng;
    document.getElementById('geocodeResults').classList.add('hidden');
}
</script>
<?php endif; // subtab ?>

<?php
// ══════════════════════════════════════════════════════════════
// TAB: Audit-Log
// ══════════════════════════════════════════════════════════════
elseif ($tab === 'audit'):
    $filterType = $_GET['filter_type'] ?? '';
    $auditLogs = get_audit_log($eventId, $filterType ?: null, null, 200);
    $actionTypes = []; foreach ($auditLogs as $l) { $actionTypes[$l['action_type']] = true; }
?>
<div class="bg-white rounded-xl shadow-sm p-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold text-gray-900">📝 Audit-Log</h2>
        <div class="flex gap-2">
            <select onchange="location.href='<?= $baseTabUrl ?>audit&filter_type='+this.value" class="border rounded-lg px-3 py-1 text-sm">
                <option value="">Alle</option>
                <?php foreach (array_keys($actionTypes) as $at): ?><option value="<?= e($at) ?>" <?= $filterType===$at?'selected':'' ?>><?= e($at) ?></option><?php endforeach; ?>
            </select>
            <form method="POST"><input type="hidden" name="action" value="export_audit"><?= csrf_field() ?><button class="bg-gray-600 text-white px-3 py-1 rounded-lg text-sm font-semibold hover:bg-gray-700">📥 CSV</button></form>
        </div>
    </div>
    <?php if (empty($auditLogs)): ?><p class="text-gray-400 text-sm">Keine Eintraege.</p>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Zeitpunkt</th><th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Benutzer</th><th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Typ</th><th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Beschreibung</th></tr></thead>
            <tbody class="divide-y">
            <?php foreach ($auditLogs as $l): ?>
                <tr class="hover:bg-gray-50"><td class="px-3 py-2 text-xs text-gray-400 whitespace-nowrap"><?= format_datetime($l['created_at']) ?></td><td class="px-3 py-2 text-xs"><?= e($l['user_name'] ?? '-') ?></td><td class="px-3 py-2"><span class="text-xs bg-gray-100 px-2 py-0.5 rounded-full"><?= e($l['action_type']) ?></span></td><td class="px-3 py-2 text-xs text-gray-600"><?= e($l['action_description']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="text-center text-sm text-gray-400 mt-6">
    <a href="index.php?event=<?= e($event['public_token']) ?>" class="hover:text-gray-600">← Zum Dashboard</a>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
