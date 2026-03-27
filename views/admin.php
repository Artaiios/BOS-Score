<?php
/**
 * BOS-Score – Event-Admin UI
 * Auth-geschützt. Originalgetreue Adaption mit Einladungsverwaltung.
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

$tab = $_GET['tab'] ?? 'overview';
$isArchived = ($event['status'] === 'archived');

$successMsg = $_SESSION['event_admin_success'] ?? '';
$errorMsg = $_SESSION['event_admin_error'] ?? '';
unset($_SESSION['event_admin_success'], $_SESSION['event_admin_error']);

// POST-Aktionen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['event_admin_error'] = 'Ungültige Anfrage.';
        redirect("index.php?event=" . urlencode($event['public_token']) . "&admin_view=1&tab=$tab");
    }
    $action = $_POST['action'] ?? '';
    $redirectUrl = "index.php?event=" . urlencode($event['public_token']) . "&admin_view=1&tab=$tab";

    if ($action === 'create_session') {
        $date = $_POST['session_date'] ?? ''; $time = $_POST['session_time'] ?? '19:00'; $comment = trim($_POST['comment'] ?? '');
        if (!empty($date)) { create_session_entry($eventId, $date, $time, $comment); audit_log($eventId, $user['id'], 'session_created', "Termin: $date $time"); $_SESSION['event_admin_success'] = 'Termin erstellt.'; }
        redirect($redirectUrl);
    }
    if ($action === 'delete_session') {
        delete_session_entry((int)($_POST['session_id'] ?? 0)); $_SESSION['event_admin_success'] = 'Termin gelöscht.'; redirect($redirectUrl);
    }
    if ($action === 'set_attendance_form') {
        $sid = (int)$_POST['session_id']; $mid = (int)$_POST['member_id']; $st = $_POST['status'];
        if (in_array($st, ['present','excused','absent'])) { set_attendance($sid, $mid, $st, 'admin'); }
        redirect($redirectUrl . "&session_id=$sid");
    }
    if ($action === 'assign_penalty') {
        $mid = (int)$_POST['member_id']; $tid = (int)$_POST['penalty_type_id'];
        if ($mid && $tid) { create_penalty($mid, $tid, $_POST['penalty_date'] ?? date('Y-m-d'), trim($_POST['penalty_comment'] ?? '')); $_SESSION['event_admin_success'] = 'Strafe zugewiesen.'; }
        redirect($redirectUrl);
    }
    if ($action === 'save_settings') {
        update_event($eventId, [
            'name' => trim($_POST['event_name'] ?? $event['name']),
            'organization_name' => trim($_POST['org_name'] ?? ''),
            'deadline_2_date' => $_POST['d2_date'] ?? $event['deadline_2_date'],
            'deadline_2_count' => (int)($_POST['d2_count'] ?? $event['deadline_2_count']),
            'deadline_2_name' => trim($_POST['d2_name'] ?? 'Hauptfrist'),
            'deadline_1_enabled' => !empty($_POST['d1_enabled']) ? 1 : 0,
            'deadline_1_date' => $_POST['d1_date'] ?? $event['deadline_1_date'],
            'deadline_1_count' => (int)($_POST['d1_count'] ?? $event['deadline_1_count']),
            'deadline_1_name' => trim($_POST['d1_name'] ?? 'Zwischenfrist'),
            'session_duration_hours' => (int)($_POST['duration'] ?? 3),
            'auto_confirm_registration' => !empty($_POST['auto_confirm']) ? 1 : 0,
            'weather_lat' => (float)($_POST['weather_lat'] ?? 0),
            'weather_lng' => (float)($_POST['weather_lng'] ?? 0),
            'theme_primary' => $_POST['theme_primary'] ?? '#dc2626',
        ]);
        $_SESSION['event_admin_success'] = 'Einstellungen gespeichert.'; redirect($redirectUrl);
    }
    if ($action === 'create_invitation_form') {
        $token = create_event_invitation($eventId, $_POST['reg_mode'] ?? 'open', $_POST['reg_until'] ?? null);
        $_SESSION['event_admin_success'] = 'Einladungslink erstellt.'; redirect($redirectUrl);
    }
    if ($action === 'invite_admin_form') {
        $email = trim(strtolower($_POST['admin_email'] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $token = create_admin_invitation($eventId, $email, $user['id']);
            send_admin_invitation_mail($email, $event['name'], $token, $user['display_name']);
            $_SESSION['event_admin_success'] = "Admin-Einladung an $email gesendet.";
        }
        redirect($redirectUrl);
    }
}

$pageTitle = 'Admin – ' . $event['name'];
$baseTabUrl = 'index.php?event=' . e($event['public_token']) . '&admin_view=1&tab=';
require __DIR__ . '/partials/header.php';
?>

<?php if ($successMsg): ?>
<div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-6"><p class="text-green-700 text-sm">✅ <?= e($successMsg) ?></p></div>
<?php endif; ?>
<?php if ($errorMsg): ?>
<div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6"><p class="text-red-700 text-sm">❌ <?= e($errorMsg) ?></p></div>
<?php endif; ?>

<!-- Tabs -->
<div class="mb-6 flex flex-wrap gap-2 border-b pb-3">
    <?php
    $tabs = [
        'overview' => '📊 Übersicht',
        'invitations' => '📨 Einladungen' . (count($pendingRegs) > 0 ? ' (' . count($pendingRegs) . ')' : ''),
        'attendance' => '✅ Anwesenheit',
        'penalties' => '💰 Team-Kasse',
        'sessions' => '📅 Termine',
        'admins' => '🔑 Admins',
        'settings' => '⚙️ Einstellungen',
    ];
    foreach ($tabs as $key => $label):
        $active = $tab === $key;
    ?>
    <a href="<?= $baseTabUrl . $key ?>"
       class="px-3 py-2 rounded-lg text-sm font-medium transition <?= $active ? 'text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>"
       <?= $active ? 'style="background-color: ' . e($themeColor) . ';"' : '' ?>>
        <?= $label ?>
    </a>
    <?php endforeach; ?>
</div>

<?php if ($tab === 'overview'): ?>
<!-- ═══ Übersicht ═══ -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border p-4 text-center"><div class="text-2xl font-bold text-gray-900"><?= count($activeMembers) ?></div><div class="text-gray-500 text-sm">Teilnehmer</div></div>
    <div class="bg-white rounded-xl shadow-sm border p-4 text-center"><div class="text-2xl font-bold text-gray-900"><?= count($sessions) ?></div><div class="text-gray-500 text-sm">Termine</div></div>
    <div class="bg-white rounded-xl shadow-sm border p-4 text-center"><div class="text-2xl font-bold text-gray-900"><?= count($allPenalties) ?></div><div class="text-gray-500 text-sm">Strafen</div></div>
    <div class="bg-white rounded-xl shadow-sm border p-4 text-center"><div class="text-2xl font-bold" style="color: <?= e($themeColor) ?>;"><?= format_currency($totalPenalty) ?></div><div class="text-gray-500 text-sm">Team-Kasse</div></div>
</div>

<div class="bg-white rounded-xl shadow-sm border p-5 mb-6">
    <h3 class="font-bold text-gray-800 mb-2">🔗 Event-URL (für Teilnehmer)</h3>
    <input type="text" readonly value="<?= e(get_base_url() . '/index.php?event=' . $event['public_token']) ?>" class="w-full text-xs p-2 bg-gray-50 border rounded font-mono" onclick="this.select(); navigator.clipboard.writeText(this.value);">
</div>

<?php elseif ($tab === 'invitations'): ?>
<!-- ═══ Einladungen ═══ -->
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <h2 class="text-lg font-bold text-gray-900 mb-4">📨 Teilnehmer-Einladungslinks</h2>
    <form method="POST" class="flex flex-wrap gap-3 mb-4">
        <input type="hidden" name="action" value="create_invitation_form">
        <?= csrf_field() ?>
        <select name="reg_mode" class="border rounded-lg px-3 py-2 text-sm"><option value="open">Offen</option><option value="until_date">Bis Datum</option><option value="closed">Geschlossen</option></select>
        <input type="datetime-local" name="reg_until" class="border rounded-lg px-3 py-2 text-sm">
        <button type="submit" class="text-white px-4 py-2 rounded-lg text-sm font-semibold hover:opacity-90 transition" style="background-color: <?= e($themeColor) ?>;">Neuen Link erstellen</button>
    </form>
    <?php foreach ($invitations as $inv): $isValid = $inv['invalidated_at'] === null; ?>
    <div class="flex items-center gap-3 py-2 border-b last:border-0 <?= $isValid ? '' : 'opacity-50' ?>">
        <input type="text" readonly value="<?= e(get_base_url() . '/index.php?invite=' . $inv['token']) ?>" class="flex-1 text-xs font-mono bg-gray-50 border rounded px-2 py-1" onclick="this.select(); navigator.clipboard.writeText(this.value);">
        <span class="text-xs text-gray-400"><?= e($inv['reg_mode']) ?> · <?= format_datetime($inv['created_at']) ?></span>
        <?= !$isValid ? '<span class="text-xs text-red-500">Deaktiviert</span>' : '' ?>
    </div>
    <?php endforeach; ?>
</div>

<!-- Offene Registrierungen -->
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <h2 class="text-lg font-bold text-gray-900 mb-4">📋 Offene Registrierungen (<?= count($pendingRegs) ?>)</h2>
    <?php if (empty($pendingRegs)): ?>
        <p class="text-gray-400 text-sm">Keine offenen Registrierungen.</p>
    <?php else: ?>
        <?php foreach ($pendingRegs as $reg): ?>
        <div class="flex items-center justify-between py-3 border-b last:border-0">
            <div><span class="font-medium"><?= e($reg['name']) ?></span> <span class="text-xs text-gray-400"><?= e($reg['email']) ?></span></div>
            <div class="flex gap-2">
                <form method="POST" action="api.php"><input type="hidden" name="action" value="confirm_registration"><input type="hidden" name="registration_id" value="<?= $reg['id'] ?>"><input type="hidden" name="event_id" value="<?= $eventId ?>"><?= csrf_field() ?><button class="text-xs bg-green-100 text-green-800 px-3 py-1 rounded-full hover:bg-green-200 font-semibold">✅ Bestätigen</button></form>
                <form method="POST" action="api.php" onsubmit="return confirm('Ablehnen?')"><input type="hidden" name="action" value="reject_registration"><input type="hidden" name="registration_id" value="<?= $reg['id'] ?>"><input type="hidden" name="event_id" value="<?= $eventId ?>"><?= csrf_field() ?><button class="text-xs bg-red-100 text-red-800 px-3 py-1 rounded-full hover:bg-red-200 font-semibold">❌ Ablehnen</button></form>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'attendance'): ?>
<!-- ═══ Anwesenheit ═══ -->
<?php
$allAttData = [];
foreach ($sessions as $s) {
    $sAtt = get_attendance_for_session($s['id']);
    $lookup = []; foreach ($sAtt as $a) { $lookup[$a['member_id']] = $a; }
    $allAttData[$s['id']] = ['present'=>count(array_filter($sAtt,fn($a)=>$a['status']==='present')),'excused'=>count(array_filter($sAtt,fn($a)=>$a['status']==='excused')),'absent'=>count(array_filter($sAtt,fn($a)=>$a['status']==='absent')),'members'=>$lookup];
}
$autoExpandId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : ($nextSessionAdmin ? $nextSessionAdmin['id'] : 0);
?>
<?php foreach (array_reverse($sessions) as $s):
    $sData = $allAttData[$s['id']];
    $ended = is_session_ended($s, $sessionDurationGlobal);
    $isExpanded = ($s['id'] === $autoExpandId);
    $isNext = $nextSessionAdmin && $s['id'] === $nextSessionAdmin['id'];
    $rowStyle = $isNext ? 'background-color:#fed7aa;border-left:5px solid #ea580c;font-weight:600;' : ($ended ? 'background-color:#f3f4f6;color:#9ca3af;' : '');
?>
<details class="mb-2 border rounded-xl overflow-hidden" <?= $isExpanded ? 'open' : '' ?>>
    <summary class="px-5 py-3 cursor-pointer hover:bg-gray-50" style="<?= $rowStyle ?>">
        <span class="text-sm font-medium"><?= format_weekday($s['session_date']) ?>, <?= format_date($s['session_date']) ?> – <?= format_time($s['session_time']) ?></span>
        <?php if ($s['comment']): ?><span class="text-xs text-gray-400 ml-2">(<?= e($s['comment']) ?>)</span><?php endif; ?>
        <?php if ($isNext): ?><span style="font-size:10px;background:#ea580c;color:white;padding:1px 6px;border-radius:9999px;margin-left:4px;">NÄCHSTER</span><?php endif; ?>
        <span class="text-xs ml-3">✅<?= $sData['present'] ?> 🟡<?= $sData['excused'] ?> ❌<?= $sData['absent'] ?></span>
    </summary>
    <div class="px-5 py-3 bg-gray-50 border-t">
        <?php foreach ($activeMembers as $m):
            $mAtt = $sData['members'][$m['id']] ?? null;
            $curStatus = $mAtt['status'] ?? '';
        ?>
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
<?php endforeach; ?>

<?php elseif ($tab === 'penalties'): ?>
<!-- ═══ Team-Kasse ═══ -->
<div class="bg-white rounded-xl shadow-sm border p-6 mb-6">
    <h2 class="text-lg font-bold text-gray-900 mb-4">💰 Strafe zuweisen</h2>
    <form method="POST" class="flex flex-wrap gap-3">
        <input type="hidden" name="action" value="assign_penalty"><?= csrf_field() ?>
        <select name="member_id" required class="border rounded-lg px-3 py-2 text-sm"><option value="">Teilnehmer…</option><?php foreach ($activeMembers as $m): ?><option value="<?= $m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?></select>
        <select name="penalty_type_id" required class="border rounded-lg px-3 py-2 text-sm"><option value="">Straftyp…</option><?php foreach ($penaltyTypes as $pt): if ($pt['active']): ?><option value="<?= $pt['id'] ?>"><?= e($pt['description']) ?> (<?= format_currency((float)$pt['amount']) ?>)</option><?php endif; endforeach; ?></select>
        <input type="date" name="penalty_date" value="<?= date('Y-m-d') ?>" class="border rounded-lg px-3 py-2 text-sm">
        <input type="text" name="penalty_comment" placeholder="Kommentar" class="border rounded-lg px-3 py-2 text-sm">
        <button type="submit" class="text-white px-4 py-2 rounded-lg text-sm font-semibold" style="background-color: <?= e($themeColor) ?>;">Zuweisen</button>
    </form>
</div>
<div class="bg-white rounded-xl shadow-sm border p-6">
    <h3 class="font-bold text-gray-800 mb-3">Kassenstand: <?= format_currency($totalPenalty) ?></h3>
    <div class="divide-y text-sm">
        <?php foreach ($allPenalties as $p): ?>
        <div class="py-2 flex justify-between"><div><span class="font-medium"><?= e($p['member_name']) ?></span> – <?= e($p['type_description']) ?><?php if($p['comment']): ?> <span class="text-gray-400">(<?= e($p['comment']) ?>)</span><?php endif; ?></div><div class="text-red-600 font-semibold"><?= format_currency((float)$p['amount']) ?> <span class="text-gray-400 text-xs"><?= format_date($p['penalty_date']) ?></span></div></div>
        <?php endforeach; ?>
    </div>
</div>

<?php elseif ($tab === 'sessions'): ?>
<!-- ═══ Termine ═══ -->
<div class="bg-white rounded-xl shadow-sm border p-6 mb-6">
    <h2 class="text-lg font-bold text-gray-900 mb-4">📅 Termin erstellen</h2>
    <form method="POST" class="flex flex-wrap gap-3">
        <input type="hidden" name="action" value="create_session"><?= csrf_field() ?>
        <input type="date" name="session_date" required class="border rounded-lg px-3 py-2 text-sm">
        <input type="time" name="session_time" value="19:00" class="border rounded-lg px-3 py-2 text-sm">
        <input type="text" name="comment" placeholder="Kommentar" class="border rounded-lg px-3 py-2 text-sm flex-1 min-w-[150px]">
        <button type="submit" class="text-white px-4 py-2 rounded-lg text-sm font-semibold" style="background-color: <?= e($themeColor) ?>;">+ Termin</button>
    </form>
</div>
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="divide-y">
        <?php foreach ($sessions as $s): $isPast = $s['session_date'] < date('Y-m-d'); ?>
        <div class="px-5 py-3 flex items-center justify-between <?= $isPast ? 'bg-gray-50 text-gray-400' : '' ?>">
            <div><span class="font-medium"><?= format_weekday($s['session_date']) ?>, <?= format_date($s['session_date']) ?></span> <span class="text-sm ml-2"><?= format_time($s['session_time']) ?></span><?php if($s['comment']): ?> <span class="text-xs text-gray-400 ml-2"><?= e($s['comment']) ?></span><?php endif; ?></div>
            <form method="POST" onsubmit="return confirm('Termin löschen?')"><input type="hidden" name="action" value="delete_session"><input type="hidden" name="session_id" value="<?= $s['id'] ?>"><?= csrf_field() ?><button class="text-xs text-red-500 hover:text-red-700">🗑️ Löschen</button></form>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php elseif ($tab === 'admins'): ?>
<!-- ═══ Admins ═══ -->
<div class="bg-white rounded-xl shadow-sm p-6 mb-6">
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
        <button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-gray-800 transition">Admin einladen</button>
    </form>
</div>

<?php elseif ($tab === 'settings'): ?>
<!-- ═══ Einstellungen ═══ -->
<div class="bg-white rounded-xl shadow-sm p-6">
    <h2 class="text-lg font-bold text-gray-900 mb-4">⚙️ Event-Einstellungen</h2>
    <form method="POST">
        <input type="hidden" name="action" value="save_settings"><?= csrf_field() ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Eventname</label><input type="text" name="event_name" value="<?= e($event['name']) ?>" class="w-full border rounded-lg p-2 text-sm"></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Organisation</label><input type="text" name="org_name" value="<?= e($event['organization_name'] ?? '') ?>" class="w-full border rounded-lg p-2 text-sm"></div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Hauptfrist</label><input type="date" name="d2_date" value="<?= e($event['deadline_2_date']) ?>" class="w-full border rounded-lg p-2 text-sm"></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Teilnahmen</label><input type="number" name="d2_count" value="<?= $event['deadline_2_count'] ?>" min="1" class="w-full border rounded-lg p-2 text-sm"></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Name</label><input type="text" name="d2_name" value="<?= e($event['deadline_2_name'] ?? '') ?>" class="w-full border rounded-lg p-2 text-sm"></div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Übungsdauer (Std.)</label><input type="number" name="duration" value="<?= $sessionDurationGlobal ?>" min="1" max="12" class="w-full border rounded-lg p-2 text-sm"></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Wetter Lat</label><input type="text" name="weather_lat" value="<?= $event['weather_lat'] ?>" class="w-full border rounded-lg p-2 text-sm"></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Wetter Lng</label><input type="text" name="weather_lng" value="<?= $event['weather_lng'] ?>" class="w-full border rounded-lg p-2 text-sm"></div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Event-Farbe</label><input type="color" name="theme_primary" value="<?= e($themeColor) ?>" class="h-10 w-20 rounded border cursor-pointer"></div>
            <div><label class="flex items-center gap-2 text-sm text-gray-700 mt-4"><input type="checkbox" name="auto_confirm" value="1" <?= $event['auto_confirm_registration'] ? 'checked' : '' ?> class="rounded text-red-600">Registrierungen automatisch bestätigen</label></div>
        </div>
        <button type="submit" class="text-white px-6 py-2 rounded-lg text-sm font-semibold" style="background-color: <?= e($themeColor) ?>;">Speichern</button>
    </form>
</div>
<?php endif; ?>

<div class="text-center text-sm text-gray-400 mt-6">
    <a href="index.php?event=<?= e($event['public_token']) ?>" class="hover:text-gray-600">← Zum Dashboard</a>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
