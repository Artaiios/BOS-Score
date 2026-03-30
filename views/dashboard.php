<?php
/**
 * BOS-Score v1.1.0 – Event-Dashboard
 * Neu: Breadcrumb, Ankuendigungen, Entschuldigung direkt in Terminliste
 */

$sessions = get_sessions($event['id']);
$members = get_members($event['id']);
$memberStats = get_member_stats($event['id']);
$totalPenalty = get_event_penalty_total($event['id']);
$sessionDuration = (int)($event['session_duration_hours'] ?? 3);
$d1Enabled = (bool)($event['deadline_1_enabled'] ?? true);
$dashOrgName = get_organization_name($event);
$dashRolesEnabled = (bool)($event['roles_enabled'] ?? false);
$themeColor = $event['theme_primary'] ?? '#dc2626';

$now = new DateTime();
$deadline1 = new DateTime($event['deadline_1_date']);
$deadline2 = new DateTime($event['deadline_2_date']);
$daysLeftD1 = $deadline1 > $now ? (int)$now->diff($deadline1)->days : 0;
$daysLeftD2 = $deadline2 > $now ? (int)$now->diff($deadline2)->days : 0;

$endedSessions = array_filter($sessions, fn($s) => is_session_ended($s, $sessionDuration));
$totalSessions = count($sessions);
$totalPast = count($endedSessions);
$remainingBeforeD1 = count(array_filter($sessions, fn($s) => !is_session_ended($s, $sessionDuration) && $s['session_date'] <= $event['deadline_1_date']));
$remainingBeforeD2 = count(array_filter($sessions, fn($s) => !is_session_ended($s, $sessionDuration) && $s['session_date'] <= $event['deadline_2_date']));

$avgPresent = 0;
if (!empty($memberStats)) { $avgPresent = round(array_sum(array_column($memberStats, 'present')) / count($memberStats), 1); }
$nextSession = get_next_session($sessions, $sessionDuration);
$avgProgress = $event['deadline_2_count'] > 0 ? min(100, round(($avgPresent / $event['deadline_2_count']) * 100)) : 0;

// Wetter
$weather = null;
$weatherLat = (float)($event['weather_lat'] ?? 0); $weatherLng = (float)($event['weather_lng'] ?? 0);
if ($nextSession && $weatherLat != 0 && $weatherLng != 0) {
    $weatherCacheFile = sys_get_temp_dir() . '/bosscore_weather_' . $event['id'] . '_' . $nextSession['session_date'] . '.json';
    $weatherCacheAge = file_exists($weatherCacheFile) ? (time() - filemtime($weatherCacheFile)) : PHP_INT_MAX;
    if ($weatherCacheAge < 3600 && file_exists($weatherCacheFile)) { $weather = json_decode(file_get_contents($weatherCacheFile), true); }
    else {
        $wUrl = 'https://api.open-meteo.com/v1/forecast?latitude='.$weatherLat.'&longitude='.$weatherLng.'&daily=temperature_2m_max,temperature_2m_min,precipitation_probability_max,weathercode&timezone=Europe/Berlin&start_date='.$nextSession['session_date'].'&end_date='.$nextSession['session_date'];
        $wJson = @file_get_contents($wUrl, false, stream_context_create(['http'=>['timeout'=>3,'ignore_errors'=>true]]));
        if ($wJson) { $wd = json_decode($wJson, true);
            if (isset($wd['daily'])) { $d=$wd['daily']; $wc=$d['weathercode'][0]??-1;
                $wm=[0=>['☀️','Klar'],1=>['🌤️','Ueberwiegend klar'],2=>['⛅','Teilweise bewoelkt'],3=>['☁️','Bewoelkt'],45=>['🌫️','Nebel'],51=>['🌦️','Nieselregen'],61=>['🌦️','Leichter Regen'],63=>['🌧️','Regen'],65=>['🌧️','Starker Regen'],71=>['🌨️','Leichter Schnee'],73=>['❄️','Schnee'],80=>['🌦️','Regenschauer'],81=>['🌧️','Regenschauer'],95=>['⛈️','Gewitter']];
                $wi=$wm[$wc]??['🌡️','Unbekannt'];
                $weather=['emoji'=>$wi[0],'desc'=>$wi[1],'temp_max'=>round($d['temperature_2m_max'][0]),'temp_min'=>round($d['temperature_2m_min'][0]),'rain_prob'=>$d['precipitation_probability_max'][0]??0];
                @file_put_contents($weatherCacheFile, json_encode($weather));
            }
        }
    }
}

// Teilnahmen ueber Zeit
$attendanceOverTime = [];
foreach ($endedSessions as $s) {
    $stmt = get_pdo()->prepare("SELECT COUNT(*) FROM attendance WHERE session_id = ? AND status = 'present'");
    $stmt->execute([$s['id']]); $attendanceOverTime[] = ['date' => format_date($s['session_date']), 'count' => (int)$stmt->fetchColumn()];
}

// Anwesenheit pro Termin
$sessionAttendance = [];
foreach ($sessions as $s) {
    $att = get_attendance_for_session($s['id']);
    $sessionAttendance[$s['id']] = ['present'=>count(array_filter($att,fn($a)=>$a['status']==='present')),'excused'=>count(array_filter($att,fn($a)=>$a['status']==='excused')),'absent'=>count(array_filter($att,fn($a)=>$a['status']==='absent'))];
}

$memberPenalties = [];
foreach ($memberStats as $m) { $memberPenalties[$m['id']] = get_member_penalty_total($m['id']); }

// Mein Status (Auth-basiert)
$linkedMember = get_linked_member($user['id'], $event['id']);
$myMemberId = $linkedMember ? $linkedMember['id'] : 0;
$myStats = null; $myDeadline1 = null; $myDeadline2 = null; $myPenalty = 0;

// Meine Anwesenheit pro Termin (fuer Entschuldigungs-Buttons)
$myAttendance = [];
if ($myMemberId > 0) {
    foreach ($memberStats as $ms) { if ($ms['id'] === $myMemberId) { $myStats = $ms; break; } }
    if ($myStats) {
        $myDeadline1 = calculate_deadline_status($myStats['present'], $event['deadline_1_count'], $event['deadline_1_date'], $totalSessions, $totalPast, $remainingBeforeD1);
        $myDeadline2 = calculate_deadline_status($myStats['present'], $event['deadline_2_count'], $event['deadline_2_date'], $totalSessions, $totalPast, $remainingBeforeD2);
        $myPenalty = $memberPenalties[$myMemberId] ?? 0;
    }
    $myAttData = get_attendance_for_member($myMemberId);
    foreach ($myAttData as $a) { $myAttendance[$a['session_id']] = $a; }
}

$d1Name = e($event['deadline_1_name'] ?? 'Frist 1');
$d2Name = e($event['deadline_2_name'] ?? 'Frist 2');
$d1Passed = $deadline1 < $now; $d2Passed = $deadline2 < $now;

// Ankuendigung aktiv?
$announcement = null;
if (!empty($event['announcement_text'])) {
    if (!$event['announcement_expires_at'] || new DateTime($event['announcement_expires_at']) > $now) {
        $announcement = $event['announcement_text'];
    }
}

$breadcrumbLevel = 'event_dashboard';
$pageTitle = $event['name'];
require __DIR__ . '/partials/header.php';
?>

<?php if ($isArchived): ?>
<div class="bg-yellow-50 border border-yellow-300 rounded-xl p-4 mb-6">
    <p class="text-yellow-800 text-sm font-semibold">📦 Dieses Event ist archiviert.</p>
</div>
<?php endif; ?>

<!-- Ankuendigung -->
<?php if ($announcement): ?>
<div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
    <div class="flex items-start gap-3">
        <span class="text-xl shrink-0">📢</span>
        <p class="text-blue-800 text-sm"><?= nl2br(e($announcement)) ?></p>
    </div>
</div>
<?php endif; ?>

<!-- Kopfbereich -->
<div class="mb-6">
    <h1 class="text-2xl md:text-3xl font-extrabold text-gray-900 mb-2"><?= e($event['name']) ?></h1>
    <p class="text-gray-500"><?= e($dashOrgName) ?> · <?= date('d.m.Y') ?> · <?= $totalSessions ?> Termine</p>
    <div class="mt-4">
        <div class="flex justify-between text-sm text-gray-600 mb-1">
            <span>Gruppenfortschritt (Ø <?= $avgPresent ?> / <?= $event['deadline_2_count'] ?>)</span>
            <span class="font-semibold"><?= $avgProgress ?>%</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
            <div class="h-full rounded-full transition-all duration-500 <?= $avgProgress >= 80 ? 'bg-green-500' : ($avgProgress >= 50 ? 'bg-yellow-500' : 'bg-red-500') ?>" style="width:<?= $avgProgress ?>%"></div>
        </div>
    </div>
</div>

<!-- Frist-Countdown -->
<div class="grid grid-cols-1 <?= $d1Enabled ? 'md:grid-cols-2' : '' ?> gap-4 mb-6">
    <?php if ($d1Enabled): ?>
    <div class="rounded-xl border overflow-hidden <?= $d1Passed ? 'bg-gray-50 border-gray-200' : 'bg-white border-yellow-200' ?>">
        <div style="height:4px;background:<?= $d1Passed ? '#9ca3af' : '#f59e0b' ?>;"></div>
        <div class="p-4">
            <div class="flex items-center justify-between mb-2"><h3 class="font-bold <?= $d1Passed ? 'text-gray-400' : 'text-gray-800' ?>"><?= $d1Name ?></h3><span class="text-xs <?= $d1Passed ? 'text-gray-400' : 'text-gray-500' ?>"><?= format_date($event['deadline_1_date']) ?></span></div>
            <?php if ($d1Passed): ?><div class="text-gray-400 text-sm">Frist abgelaufen</div>
            <?php else: ?><div class="flex items-baseline gap-3"><div><span class="text-3xl font-extrabold text-yellow-600"><?= $daysLeftD1 ?></span> <span class="text-yellow-600 text-sm">Tage</span></div><div class="text-gray-400 text-sm">·</div><div><span class="text-xl font-bold text-gray-700"><?= $remainingBeforeD1 ?></span> <span class="text-gray-500 text-sm">Termine</span></div></div>
            <div class="text-xs text-gray-400 mt-1">Mind. <?= $event['deadline_1_count'] ?> Teilnahmen</div><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <div class="rounded-xl border overflow-hidden <?= $d2Passed ? 'bg-gray-50 border-gray-200' : 'bg-white border-red-200' ?>">
        <div style="height:4px;background:<?= $d2Passed ? '#9ca3af' : e($themeColor) ?>;"></div>
        <div class="p-4">
            <div class="flex items-center justify-between mb-2"><h3 class="font-bold <?= $d2Passed ? 'text-gray-400' : 'text-gray-800' ?>"><?= $d2Name ?></h3><span class="text-xs <?= $d2Passed ? 'text-gray-400' : 'text-gray-500' ?>"><?= format_date($event['deadline_2_date']) ?></span></div>
            <?php if ($d2Passed): ?><div class="text-gray-400 text-sm">Frist abgelaufen</div>
            <?php else: ?><div class="flex items-baseline gap-3"><div><span class="text-3xl font-extrabold" style="color:<?= e($themeColor) ?>;"><?= $daysLeftD2 ?></span> <span class="text-sm" style="color:<?= e($themeColor) ?>;">Tage</span></div><div class="text-gray-400 text-sm">·</div><div><span class="text-xl font-bold text-gray-700"><?= $remainingBeforeD2 ?></span> <span class="text-gray-500 text-sm">Termine</span></div></div>
            <div class="text-xs text-gray-400 mt-1">Mind. <?= $event['deadline_2_count'] ?> Teilnahmen</div><?php endif; ?>
        </div>
    </div>
</div>

<!-- Naechster Termin + Wetter -->
<?php if ($nextSession): ?>
<div class="bg-white rounded-xl shadow-sm border mb-6 overflow-hidden">
    <div class="flex flex-col sm:flex-row">
        <div class="flex-1 p-5">
            <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Naechster Termin</div>
            <div class="text-xl font-bold text-gray-900"><?= format_weekday($nextSession['session_date']) ?>, <?= format_date($nextSession['session_date']) ?></div>
            <div class="text-gray-600 mt-1"><?= format_time($nextSession['session_time']) ?> Uhr<?php if ($nextSession['comment']): ?> · <span class="text-gray-400"><?= e($nextSession['comment']) ?></span><?php endif; ?></div>
            <?php $sessionDT = new DateTime($nextSession['session_date'].' '.$nextSession['session_time']); $diff = $now->diff($sessionDT);
            if ($nextSession['session_date'] === date('Y-m-d')): ?><div class="mt-2 inline-flex items-center gap-1 bg-red-100 text-red-700 text-xs font-bold px-3 py-1 rounded-full">🔴 Heute!</div>
            <?php elseif ($diff->days <= 3): ?><div class="mt-2 inline-flex items-center gap-1 bg-orange-100 text-orange-700 text-xs font-bold px-3 py-1 rounded-full">⏰ In <?= $diff->days ?> <?= $diff->days === 1 ? 'Tag' : 'Tagen' ?></div><?php endif; ?>
        </div>
        <?php if ($weather): ?>
        <div class="sm:w-48 p-5 sm:border-l border-t sm:border-t-0 bg-gradient-to-br from-blue-50 to-white flex flex-col items-center justify-center text-center">
            <div class="text-4xl mb-1"><?= $weather['emoji'] ?></div>
            <div class="text-sm font-semibold text-gray-700"><?= e($weather['desc']) ?></div>
            <div class="text-lg font-bold text-gray-900 mt-1"><?= $weather['temp_min'] ?>° / <?= $weather['temp_max'] ?>°</div>
            <?php if ($weather['rain_prob'] > 0): ?><div class="text-xs mt-1 <?= $weather['rain_prob'] > 50 ? 'text-blue-600 font-semibold' : 'text-gray-400' ?>">💧 <?= $weather['rain_prob'] ?>%</div><?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Mein Status -->
<div class="bg-white rounded-xl shadow-sm border mb-6 overflow-hidden">
    <div class="px-5 py-3 border-b flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <h2 class="font-bold text-gray-800">👤 Mein Status</h2>
        <?php if ($myStats): ?><span class="text-sm text-gray-500">Angemeldet als <strong><?= e($myStats['name']) ?></strong></span><?php endif; ?>
    </div>
    <?php if ($myStats): ?>
    <div class="p-5">
        <div class="grid grid-cols-2 sm:grid-cols-<?= $d1Enabled ? '4' : '3' ?> gap-4">
            <div class="text-center"><div class="text-2xl font-bold text-gray-900"><?= $myStats['present'] ?></div><div class="text-xs text-gray-500">Teilnahmen</div></div>
            <?php if ($d1Enabled && $myDeadline1): ?><div class="text-center"><div class="inline-flex items-center gap-1 px-3 py-1 rounded-lg text-sm font-bold <?= $myDeadline1['class'] ?>"><?= $myDeadline1['icon'] ?> <?= $myStats['present'] ?>/<?= $event['deadline_1_count'] ?></div><div class="text-xs text-gray-500 mt-1"><?= $d1Name ?></div></div><?php endif; ?>
            <div class="text-center"><div class="inline-flex items-center gap-1 px-3 py-1 rounded-lg text-sm font-bold <?= $myDeadline2['class'] ?>"><?= $myDeadline2['icon'] ?> <?= $myStats['present'] ?>/<?= $event['deadline_2_count'] ?></div><div class="text-xs text-gray-500 mt-1"><?= $d2Name ?></div></div>
            <div class="text-center"><div class="text-2xl font-bold <?= $myPenalty > 0 ? 'text-red-600' : 'text-green-600' ?>"><?= $myPenalty > 0 ? format_currency($myPenalty) : '0 €' ?></div><div class="text-xs text-gray-500">Team-Kasse</div></div>
        </div>
        <div class="mt-4 text-center"><a href="index.php?event=<?= e($event['public_token']) ?>&member=<?= $myMemberId ?>" class="inline-flex items-center gap-1 text-sm font-semibold hover:underline" style="color:<?= e($themeColor) ?>;">Meine vollstaendige Uebersicht →</a></div>
    </div>
    <?php else: ?>
    <div class="px-5 py-6 text-center text-gray-400 text-sm">Dein Account ist noch nicht mit einem Teilnehmer verknuepft.</div>
    <?php endif; ?>
</div>

<!-- Statistik-Karten -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-white rounded-xl shadow-sm border p-4"><div class="text-3xl mb-1">👥</div><div class="text-2xl font-bold text-gray-900"><?= count($members) ?></div><div class="text-gray-500 text-sm">Teilnehmer</div></div>
    <div class="bg-white rounded-xl shadow-sm border p-4"><div class="text-3xl mb-1">📊</div><div class="text-2xl font-bold text-gray-900"><?= $avgPresent ?></div><div class="text-gray-500 text-sm">Ø Teilnahmen</div></div>
    <div class="bg-white rounded-xl shadow-sm border p-4"><div class="text-3xl mb-1">✅</div><div class="text-2xl font-bold text-gray-900"><?= $totalPast ?> / <?= $totalSessions ?></div><div class="text-gray-500 text-sm">Absolviert</div></div>
    <div class="bg-white rounded-xl shadow-sm border p-4"><div class="text-3xl mb-1">💰</div><div class="text-2xl font-bold text-gray-900"><?= format_currency($totalPenalty) ?></div><div class="text-gray-500 text-sm">Team-Kasse</div></div>
</div>

<!-- Charts -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-sm border p-5"><h2 class="font-bold text-gray-800 mb-4">Teilnahmen pro Teilnehmer</h2><div style="min-height:<?= max(200, count($memberStats)*28) ?>px"><canvas id="chartParticipation"></canvas></div></div>
    <div class="bg-white rounded-xl shadow-sm border p-5"><h2 class="font-bold text-gray-800 mb-4">Teilnahmen-Entwicklung</h2><div style="min-height:200px"><canvas id="chartTimeline"></canvas></div></div>
</div>

<!-- ══ Terminliste mit Entschuldigungs-Buttons ════════════════ -->
<?php
$pastSessionsDash = []; $nextSessionItem = null; $futureSessionsDash = []; $nextFoundDash = false;
foreach ($sessions as $s) {
    $s['_ended'] = is_session_ended($s, $sessionDuration);
    if (!$nextFoundDash && !$s['_ended']) { $nextSessionItem = $s; $nextFoundDash = true; }
    elseif ($s['_ended']) { $pastSessionsDash[] = $s; }
    else { $futureSessionsDash[] = $s; }
}

// Helper: Entschuldigungs-Button rendern
function render_excuse_button_row($s, $myMemberId, $myAttendance, $eventId, $isArchived, $sessionDuration) {
    if ($myMemberId <= 0 || $isArchived || is_session_ended($s, $sessionDuration)) return;
    $att = $myAttendance[$s['id']] ?? null;
    $status = $att['status'] ?? null;
    $canChange = can_member_change_excuse($s, $att);
    $isSelfExcused = ($status === 'excused' && $att && $att['excused_by'] === 'member');

    if (!$canChange) return;
    echo '<tr style="border-bottom: 2px solid #d1d5db;"><td colspan="7" class="px-4 py-1.5">';
    echo '<div class="flex items-center gap-2 justify-end">';
    if ($status === 'excused' && $isSelfExcused) {
        echo '<span class="text-xs text-yellow-600">🟡 Du bist entschuldigt</span>';
        echo '<button onclick="withdrawExcuse('.$s['id'].','.$myMemberId.','.$eventId.')" class="bg-gray-400 hover:bg-gray-500 text-white px-3 py-1 rounded-lg text-xs font-semibold transition">Zurueckziehen</button>';
    } elseif ($status !== 'excused') {
        echo '<button onclick="excuseMe('.$s['id'].','.$myMemberId.','.$eventId.')" class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded-lg text-xs font-semibold transition">Entschuldigen</button>';
    }
    echo '</div></td></tr>';
}
?>
<div class="bg-white rounded-xl shadow-sm border mb-8 overflow-hidden">
    <div class="px-5 py-4 border-b"><h2 class="font-bold text-gray-800">📅 Uebungstermine</h2></div>

    <?php if (!empty($pastSessionsDash)): ?>
    <div style="border-bottom: 2px solid #d1d5db;">
        <div onclick="document.getElementById('pastSessionsBody').classList.toggle('hidden');var i=document.getElementById('pastIcon');i.textContent=i.textContent==='▶'?'▼':'▶';"
             class="px-5 py-3 flex items-center cursor-pointer hover:bg-gray-50" style="background-color:#f3f4f6;">
            <span id="pastIcon" class="text-xs text-gray-400 mr-2">▶</span>
            <span class="font-semibold text-gray-500">Vergangene Termine</span>
            <span class="text-xs bg-gray-200 text-gray-500 px-2 py-0.5 rounded-full ml-2"><?= count($pastSessionsDash) ?></span>
        </div>
        <div id="pastSessionsBody" class="hidden"><div class="overflow-x-auto"><table class="w-full text-sm">
            <?php foreach ($pastSessionsDash as $s): $att = $sessionAttendance[$s['id']] ?? ['present'=>0,'excused'=>0,'absent'=>0]; ?>
            <tr style="background-color:#f3f4f6;color:#9ca3af;border-bottom:2px solid #d1d5db;">
                <td class="px-4 py-2.5 font-medium"><?= format_date($s['session_date']) ?></td>
                <td class="px-4 py-2.5 hidden sm:table-cell"><?= format_weekday($s['session_date']) ?></td>
                <td class="px-4 py-2.5"><?= format_time($s['session_time']) ?> Uhr</td>
                <td class="px-4 py-2.5 hidden md:table-cell"><?= e($s['comment']) ?></td>
                <td class="px-4 py-2.5 text-center"><?= $att['present'] ?: '-' ?></td>
                <td class="px-4 py-2.5 text-center"><?= $att['excused'] ?: '-' ?></td>
                <td class="px-4 py-2.5 text-center"><?= $att['absent'] ?: '-' ?></td>
            </tr>
            <?php endforeach; ?>
        </table></div></div>
    </div>
    <?php endif; ?>

    <?php if ($nextSessionItem):
        $s = $nextSessionItem; $isToday = $s['session_date'] === date('Y-m-d');
        $att = $sessionAttendance[$s['id']] ?? ['present'=>0,'excused'=>0,'absent'=>0];
    ?>
    <div style="border-bottom:2px solid #d1d5db;"><div class="overflow-x-auto"><table class="w-full text-sm">
        <thead style="background-color:#e5e7eb;"><tr>
            <th class="px-4 py-3 text-left font-semibold text-gray-700">Datum</th>
            <th class="px-4 py-3 text-left font-semibold text-gray-700 hidden sm:table-cell">Tag</th>
            <th class="px-4 py-3 text-left font-semibold text-gray-700">Uhrzeit</th>
            <th class="px-4 py-3 text-left font-semibold text-gray-700 hidden md:table-cell">Kommentar</th>
            <th class="px-4 py-3 text-center font-semibold text-gray-700">✅</th>
            <th class="px-4 py-3 text-center font-semibold text-gray-700">🟡</th>
            <th class="px-4 py-3 text-center font-semibold text-gray-700">❌</th>
        </tr></thead>
        <tbody>
            <tr style="background-color:#fed7aa;border-left:5px solid #ea580c;font-weight:600;">
                <td class="px-4 py-3 font-medium"><?= format_date($s['session_date']) ?><?php if ($isToday): ?> <span class="text-xs bg-red-600 text-white px-2 py-0.5 rounded-full">HEUTE</span><?php endif; ?> <span style="font-size:11px;background:#ea580c;color:white;padding:2px 8px;border-radius:9999px;">NAECHSTER</span></td>
                <td class="px-4 py-3 hidden sm:table-cell"><?= format_weekday($s['session_date']) ?></td>
                <td class="px-4 py-3"><?= format_time($s['session_time']) ?> Uhr</td>
                <td class="px-4 py-3 hidden md:table-cell" style="color:#6b7280;"><?= e($s['comment']) ?></td>
                <td class="px-4 py-3 text-center"><?= $att['present'] ?: '-' ?></td>
                <td class="px-4 py-3 text-center"><?= $att['excused'] ?: '-' ?></td>
                <td class="px-4 py-3 text-center"><?= $att['absent'] ?: '-' ?></td>
            </tr>
            <?php render_excuse_button_row($s, $myMemberId, $myAttendance, $event['id'], $isArchived, $sessionDuration); ?>
        </tbody>
    </table></div></div>
    <?php elseif (empty($pastSessionsDash) && empty($futureSessionsDash)): ?>
    <div class="px-5 py-8 text-center text-gray-400">Noch keine Termine vorhanden.</div>
    <?php endif; ?>

    <?php if (!empty($futureSessionsDash)): ?>
    <div>
        <div onclick="document.getElementById('futureSessionsBody').classList.toggle('hidden');var i=document.getElementById('futureIcon');i.textContent=i.textContent==='▶'?'▼':'▶';"
             class="px-5 py-3 flex items-center cursor-pointer hover:bg-gray-50">
            <span id="futureIcon" class="text-xs text-gray-400 mr-2">▶</span>
            <span class="font-semibold text-gray-600">Kommende Termine</span>
            <span class="text-xs bg-gray-200 text-gray-500 px-2 py-0.5 rounded-full ml-2"><?= count($futureSessionsDash) ?></span>
        </div>
        <div id="futureSessionsBody" class="hidden"><div class="overflow-x-auto"><table class="w-full text-sm">
            <?php foreach ($futureSessionsDash as $s):
                $isToday = $s['session_date'] === date('Y-m-d');
                $att = $sessionAttendance[$s['id']] ?? ['present'=>0,'excused'=>0,'absent'=>0];
            ?>
            <tr style="border-bottom:1px solid #e5e7eb;">
                <td class="px-4 py-2.5 font-medium"><?= format_date($s['session_date']) ?><?php if ($isToday): ?> <span class="text-xs bg-red-600 text-white px-2 py-0.5 rounded-full">HEUTE</span><?php endif; ?></td>
                <td class="px-4 py-2.5 hidden sm:table-cell"><?= format_weekday($s['session_date']) ?></td>
                <td class="px-4 py-2.5"><?= format_time($s['session_time']) ?> Uhr</td>
                <td class="px-4 py-2.5 hidden md:table-cell" style="color:#6b7280;"><?= e($s['comment']) ?></td>
                <td class="px-4 py-2.5 text-center"><?= $att['present'] ?: '-' ?></td>
                <td class="px-4 py-2.5 text-center"><?= $att['excused'] ?: '-' ?></td>
                <td class="px-4 py-2.5 text-center"><?= $att['absent'] ?: '-' ?></td>
            </tr>
            <?php render_excuse_button_row($s, $myMemberId, $myAttendance, $event['id'], $isArchived, $sessionDuration); ?>
            <?php endforeach; ?>
        </table></div></div>
    </div>
    <?php endif; ?>
</div>

<!-- Teilnehmer-Tabelle -->
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="px-5 py-4 border-b"><h2 class="font-bold text-gray-800">👥 Teilnehmer</h2></div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm" id="memberTable">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left font-semibold text-gray-600 cursor-pointer hover:text-red-600" onclick="sortTable(0)">Name ↕</th>
                <th class="px-4 py-3 text-center font-semibold text-gray-600 cursor-pointer hover:text-red-600" onclick="sortTable(1)">Teilnahmen ↕</th>
                <th class="px-4 py-3 text-center font-semibold text-gray-600 cursor-pointer hover:text-red-600 hidden sm:table-cell" onclick="sortTable(2)">Quote ↕</th>
                <?php if ($d1Enabled): ?><th class="px-4 py-3 text-center font-semibold text-gray-600"><?= $d1Name ?></th><?php endif; ?>
                <th class="px-4 py-3 text-center font-semibold text-gray-600"><?= $d2Name ?></th>
                <th class="px-4 py-3 text-center font-semibold text-gray-600 cursor-pointer hover:text-red-600 hidden md:table-cell" onclick="sortTable(<?= $d1Enabled ? 5 : 4 ?>)">Team-Kasse ↕</th>
            </tr></thead>
            <tbody class="divide-y">
            <?php foreach ($memberStats as $m):
                if ($d1Enabled) { $d1 = calculate_deadline_status($m['present'], $event['deadline_1_count'], $event['deadline_1_date'], $totalSessions, $totalPast, $remainingBeforeD1); }
                $d2 = calculate_deadline_status($m['present'], $event['deadline_2_count'], $event['deadline_2_date'], $totalSessions, $totalPast, $remainingBeforeD2);
                $penalty = $memberPenalties[$m['id']] ?? 0; $isMe = ($m['id'] === $myMemberId); $canLink = $isMe || $isAdmin;
            ?>
            <tr class="hover:bg-gray-50 <?= $isMe ? 'bg-yellow-50' : '' ?>">
                <td class="px-4 py-3"><?php if ($canLink): ?><a href="index.php?event=<?= e($event['public_token']) ?>&member=<?= $m['id'] ?>" class="font-medium hover:underline" style="color:<?= e($themeColor) ?>;"><?= e($m['name']) ?><?php if ($isMe): ?> <span class="text-xs text-gray-400">(Du)</span><?php endif; ?></a><?php else: ?><span class="font-medium text-gray-800"><?= e($m['name']) ?></span><?php endif; ?></td>
                <td class="px-4 py-3 text-center font-semibold" data-sort="<?= $m['present'] ?>"><?= $m['present'] ?></td>
                <td class="px-4 py-3 text-center hidden sm:table-cell" data-sort="<?= $m['quote'] ?>"><?= $m['quote'] ?>%</td>
                <?php if ($d1Enabled): ?><td class="px-4 py-3 text-center"><span class="inline-block px-2 py-1 rounded-lg text-xs font-semibold <?= $d1['class'] ?>"><?= $d1['icon'] ?> <?= $m['present'] ?>/<?= $event['deadline_1_count'] ?></span></td><?php endif; ?>
                <td class="px-4 py-3 text-center"><span class="inline-block px-2 py-1 rounded-lg text-xs font-semibold <?= $d2['class'] ?>"><?= $d2['icon'] ?> <?= $m['present'] ?>/<?= $event['deadline_2_count'] ?></span></td>
                <td class="px-4 py-3 text-center hidden md:table-cell" data-sort="<?= $penalty ?>"><?php if ($penalty > 0): ?><span class="text-red-600 font-semibold"><?= format_currency($penalty) ?></span><?php else: ?><span class="text-gray-300">-</span><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
var sortDir = {};
function sortTable(c) {
    var t = document.getElementById('memberTable'), b = t.tBodies[0], r = Array.from(b.rows);
    var d = sortDir[c] = !(sortDir[c] ?? false);
    r.sort(function(a, b2) {
        var av = a.cells[c].getAttribute('data-sort') || a.cells[c].textContent.trim();
        var bv = b2.cells[c].getAttribute('data-sort') || b2.cells[c].textContent.trim();
        var an = parseFloat(av), bn = parseFloat(bv);
        if (!isNaN(an) && !isNaN(bn)) return d ? an - bn : bn - an;
        return d ? av.localeCompare(bv, 'de') : bv.localeCompare(av, 'de');
    });
    r.forEach(function(row) { b.appendChild(row); });
}

// Entschuldigung direkt vom Dashboard
function excuseMe(sessionId, memberId, eventId) {
    if (!confirm('Fuer diesen Termin entschuldigen?')) return;
    var form = new FormData();
    form.append('action', 'member_excuse'); form.append('session_id', sessionId);
    form.append('member_id', memberId); form.append('event_id', eventId);
    form.append('csrf_token', '<?= csrf_token() ?>');
    fetch('api.php', { method: 'POST', body: form }).then(function(r){return r.json();}).then(function(res) {
        if (res.message) alert(res.message);
        if (res.success) setTimeout(function(){location.reload();}, 800);
    });
}
function withdrawExcuse(sessionId, memberId, eventId) {
    if (!confirm('Entschuldigung zurueckziehen?')) return;
    var form = new FormData();
    form.append('action', 'member_withdraw_excuse'); form.append('session_id', sessionId);
    form.append('member_id', memberId); form.append('event_id', eventId);
    form.append('csrf_token', '<?= csrf_token() ?>');
    fetch('api.php', { method: 'POST', body: form }).then(function(r){return r.json();}).then(function(res) {
        if (res.message) alert(res.message);
        if (res.success) setTimeout(function(){location.reload();}, 800);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    var tc = '<?= e($themeColor) ?>';
    var md = <?= json_encode(array_map(fn($m) => ['name'=>$m['name'],'present'=>$m['present']], $memberStats)) ?>;
    md.sort(function(a,b){return b.present-a.present;});
    new Chart(document.getElementById('chartParticipation'), {type:'bar',data:{labels:md.map(function(m){return m.name;}),datasets:[{label:'Teilnahmen',data:md.map(function(m){return m.present;}),backgroundColor:md.map(function(m){return m.present>=<?=$event['deadline_2_count']?>?'#22c55e':(m.present>=<?=$d1Enabled?$event['deadline_1_count']:$event['deadline_2_count']?>?'#f59e0b':'#ef4444');}),borderRadius:4}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{beginAtZero:true},y:{grid:{display:false}}}}});
    var td = <?= json_encode($attendanceOverTime) ?>;
    if (td.length > 0) new Chart(document.getElementById('chartTimeline'), {type:'line',data:{labels:td.map(function(t){return t.date;}),datasets:[{label:'Anwesend',data:td.map(function(t){return t.count;}),borderColor:tc,backgroundColor:tc+'1a',fill:true,tension:.3,pointRadius:4,pointBackgroundColor:tc}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{ticks:{maxRotation:45}},y:{beginAtZero:true}}}});
});
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
