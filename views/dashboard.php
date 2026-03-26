<?php
/**
 * Öffentliches Dashboard – Startseite pro Jahrgang
 */

$sessions = get_sessions($event['id']);
$members = get_members($event['id']);
$memberStats = get_member_stats($event['id']);
$totalPenalty = get_event_penalty_total($event['id']);
$sessionDuration = (int)($event['session_duration_hours'] ?? 3);
$d1Enabled = (bool)($event['deadline_1_enabled'] ?? true);
$dashOrgName = get_organization_name($event);
$dashRolesEnabled = (bool)($event['roles_enabled'] ?? false);

$now = new DateTime();
$deadline1 = new DateTime($event['deadline_1_date']);
$deadline2 = new DateTime($event['deadline_2_date']);

// Countdown Frist 1
$daysLeftD1 = $deadline1 > $now ? (int)$now->diff($deadline1)->days : 0;
$daysLeftD2 = $deadline2 > $now ? (int)$now->diff($deadline2)->days : 0;

// Beendete Termine (Start + Dauer überschritten)
$endedSessions = array_filter($sessions, fn($s) => is_session_ended($s, $sessionDuration));
$totalSessions = count($sessions);
$totalPast = count($endedSessions);

// Für Frist-Berechnungen: noch nicht beendete Termine vor den Fristen
$remainingBeforeD1 = count(array_filter($sessions, fn($s) => !is_session_ended($s, $sessionDuration) && $s['session_date'] <= $event['deadline_1_date']));
$remainingBeforeD2 = count(array_filter($sessions, fn($s) => !is_session_ended($s, $sessionDuration) && $s['session_date'] <= $event['deadline_2_date']));

// Durchschnittliche Teilnahmen
$avgPresent = 0;
if (!empty($memberStats)) {
    $avgPresent = round(array_sum(array_column($memberStats, 'present')) / count($memberStats), 1);
}

// Nächster Termin (berücksichtigt Übungsdauer)
$nextSession = get_next_session($sessions, $sessionDuration);

// Fortschrittsbalken
$avgProgress = $event['deadline_2_count'] > 0 ? min(100, round(($avgPresent / $event['deadline_2_count']) * 100)) : 0;

// ── Wetter für nächsten Termin (Open-Meteo, kostenlos, kein API-Key) ──
$weather = null;
$weatherLat = (float)($event['weather_lat'] ?? 48.81);
$weatherLng = (float)($event['weather_lng'] ?? 8.945);
if ($nextSession && $weatherLat != 0 && $weatherLng != 0) {
    $weatherCacheFile = sys_get_temp_dir() . '/laz_weather_' . $event['id'] . '_' . $nextSession['session_date'] . '.json';
    $weatherCacheAge = file_exists($weatherCacheFile) ? (time() - filemtime($weatherCacheFile)) : PHP_INT_MAX;

    if ($weatherCacheAge < 3600 && file_exists($weatherCacheFile)) {
        $weather = json_decode(file_get_contents($weatherCacheFile), true);
    } else {
        $weatherUrl = 'https://api.open-meteo.com/v1/forecast?latitude=' . $weatherLat . '&longitude=' . $weatherLng
            . '&daily=temperature_2m_max,temperature_2m_min,precipitation_probability_max,weathercode'
            . '&timezone=Europe/Berlin'
            . '&start_date=' . $nextSession['session_date']
            . '&end_date=' . $nextSession['session_date'];

        $ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
        $weatherJson = @file_get_contents($weatherUrl, false, $ctx);

        if ($weatherJson) {
            $weatherData = json_decode($weatherJson, true);
            if (isset($weatherData['daily'])) {
                $d = $weatherData['daily'];
                $wmoCode = $d['weathercode'][0] ?? -1;

                // WMO-Wettercode → Beschreibung + Emoji
                $wmoMap = [
                    0 => ['☀️', 'Klar'], 1 => ['🌤️', 'Überwiegend klar'],
                    2 => ['⛅', 'Teilweise bewölkt'], 3 => ['☁️', 'Bewölkt'],
                    45 => ['🌫️', 'Nebel'], 48 => ['🌫️', 'Reifnebel'],
                    51 => ['🌦️', 'Leichter Nieselregen'], 53 => ['🌦️', 'Nieselregen'],
                    55 => ['🌧️', 'Starker Nieselregen'], 56 => ['🌨️', 'Gefrierender Nieselregen'],
                    61 => ['🌦️', 'Leichter Regen'], 63 => ['🌧️', 'Regen'],
                    65 => ['🌧️', 'Starker Regen'], 66 => ['🌨️', 'Gefrierender Regen'],
                    71 => ['🌨️', 'Leichter Schnee'], 73 => ['❄️', 'Schnee'],
                    75 => ['❄️', 'Starker Schnee'], 77 => ['❄️', 'Schneekörner'],
                    80 => ['🌦️', 'Leichte Regenschauer'], 81 => ['🌧️', 'Regenschauer'],
                    82 => ['⛈️', 'Starke Regenschauer'], 85 => ['🌨️', 'Schneeschauer'],
                    95 => ['⛈️', 'Gewitter'], 96 => ['⛈️', 'Gewitter mit Hagel'],
                ];
                $wInfo = $wmoMap[$wmoCode] ?? ['🌡️', 'Unbekannt'];

                $weather = [
                    'emoji' => $wInfo[0],
                    'desc' => $wInfo[1],
                    'temp_max' => round($d['temperature_2m_max'][0]),
                    'temp_min' => round($d['temperature_2m_min'][0]),
                    'rain_prob' => $d['precipitation_probability_max'][0] ?? 0,
                ];

                @file_put_contents($weatherCacheFile, json_encode($weather));
            }
        }
    }
}

// Teilnahmen über Zeit (für Liniendiagramm)
$attendanceOverTime = [];
foreach ($endedSessions as $s) {
    $stmt = get_pdo()->prepare("SELECT COUNT(*) FROM attendance WHERE session_id = ? AND status = 'present'");
    $stmt->execute([$s['id']]);
    $cnt = (int)$stmt->fetchColumn();
    $attendanceOverTime[] = ['date' => format_date($s['session_date']), 'count' => $cnt];
}

// Anwesenheit pro Termin (für Terminliste)
$sessionAttendance = [];
foreach ($sessions as $s) {
    $att = get_attendance_for_session($s['id']);
    $sessionAttendance[$s['id']] = [
        'present' => count(array_filter($att, fn($a) => $a['status'] === 'present')),
        'excused' => count(array_filter($att, fn($a) => $a['status'] === 'excused')),
        'absent' => count(array_filter($att, fn($a) => $a['status'] === 'absent')),
    ];
}

// Penalty totals per member
$memberPenalties = [];
foreach ($memberStats as $m) {
    $memberPenalties[$m['id']] = get_member_penalty_total($m['id']);
}

// ── Mein Status (Cookie-basiert) ──────────────────────────
$myMemberId = isset($_COOKIE['laz_member_' . $event['id']]) ? (int)$_COOKIE['laz_member_' . $event['id']] : 0;
$myStats = null;
$myDeadline1 = null;
$myDeadline2 = null;
$myPenalty = 0;
if ($myMemberId > 0) {
    foreach ($memberStats as $ms) {
        if ($ms['id'] === $myMemberId) {
            $myStats = $ms;
            break;
        }
    }
    if ($myStats) {
        $myDeadline1 = calculate_deadline_status($myStats['present'], $event['deadline_1_count'], $event['deadline_1_date'], $totalSessions, $totalPast, $remainingBeforeD1);
        $myDeadline2 = calculate_deadline_status($myStats['present'], $event['deadline_2_count'], $event['deadline_2_date'], $totalSessions, $totalPast, $remainingBeforeD2);
        $myPenalty = $memberPenalties[$myMemberId] ?? 0;
    }
}

require __DIR__ . '/partials/header.php';
?>

<?php if ($isArchived): ?>
<div class="bg-yellow-50 border border-yellow-300 rounded-xl p-4 mb-6">
    <p class="text-yellow-800 text-sm font-semibold">📦 Dieses Event ist archiviert und wird nur noch im Lesemodus angezeigt.</p>
</div>
<?php endif; ?>

<!-- Kopfbereich -->
<div class="mb-6">
    <h1 class="text-2xl md:text-3xl font-extrabold text-gray-900 mb-2">
        🔥 <?= e($event['name']) ?>
    </h1>
    <p class="text-gray-500"><?= e($dashOrgName) ?> · <?= date('d.m.Y') ?> · <?= $totalSessions ?> Termine insgesamt</p>

    <!-- Gesamtfortschritt -->
    <div class="mt-4">
        <div class="flex justify-between text-sm text-gray-600 mb-1">
            <span>Gruppenfortschritt (Ø <?= $avgPresent ?> / <?= $event['deadline_2_count'] ?> Teilnahmen)</span>
            <span class="font-semibold"><?= $avgProgress ?>%</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
            <div class="h-full rounded-full transition-all duration-500 <?= $avgProgress >= 80 ? 'bg-green-500' : ($avgProgress >= 50 ? 'bg-yellow-500' : 'bg-red-500') ?>"
                 style="width: <?= $avgProgress ?>%"></div>
        </div>
    </div>
</div>

<!-- ══ Frist-Countdown-Karten ══════════════════════════════════ -->
<div class="grid grid-cols-1 <?= $d1Enabled ? 'md:grid-cols-2' : '' ?> gap-4 mb-6">
    <?php
    $d1Name = e($event['deadline_1_name'] ?? 'Frist 1');
    $d2Name = e($event['deadline_2_name'] ?? 'Frist 2');
    $d1Passed = $deadline1 < $now;
    $d2Passed = $deadline2 < $now;
    ?>
    <?php if ($d1Enabled): ?>
    <!-- Frist 1 (Zwischenziel) -->
    <div class="rounded-xl border overflow-hidden <?= $d1Passed ? 'bg-gray-50 border-gray-200' : 'bg-white border-yellow-200' ?>">
        <div style="height: 4px; background: <?= $d1Passed ? '#9ca3af' : '#f59e0b' ?>;"></div>
        <div class="p-4">
            <div class="flex items-center justify-between mb-2">
                <h3 class="font-bold <?= $d1Passed ? 'text-gray-400' : 'text-gray-800' ?>"><?= $d1Name ?></h3>
                <span class="text-xs <?= $d1Passed ? 'text-gray-400' : 'text-gray-500' ?>"><?= format_date($event['deadline_1_date']) ?></span>
            </div>
            <?php if ($d1Passed): ?>
                <div class="text-gray-400 text-sm">Frist abgelaufen</div>
            <?php else: ?>
                <div class="flex items-baseline gap-3">
                    <div>
                        <span class="text-3xl font-extrabold text-yellow-600"><?= $daysLeftD1 ?></span>
                        <span class="text-yellow-600 text-sm ml-1">Tage</span>
                    </div>
                    <div class="text-gray-400 text-sm">·</div>
                    <div>
                        <span class="text-xl font-bold text-gray-700"><?= $remainingBeforeD1 ?></span>
                        <span class="text-gray-500 text-sm ml-1">Termine übrig</span>
                    </div>
                </div>
                <div class="text-xs text-gray-400 mt-1">Mindestens <?= $event['deadline_1_count'] ?> Teilnahmen erforderlich</div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Frist 2 (Hauptfrist) -->
    <div class="rounded-xl border overflow-hidden <?= $d2Passed ? 'bg-gray-50 border-gray-200' : 'bg-white border-red-200' ?>">
        <div style="height: 4px; background: <?= $d2Passed ? '#9ca3af' : '#dc2626' ?>;"></div>
        <div class="p-4">
            <div class="flex items-center justify-between mb-2">
                <h3 class="font-bold <?= $d2Passed ? 'text-gray-400' : 'text-gray-800' ?>"><?= $d2Name ?></h3>
                <span class="text-xs <?= $d2Passed ? 'text-gray-400' : 'text-gray-500' ?>"><?= format_date($event['deadline_2_date']) ?></span>
            </div>
            <?php if ($d2Passed): ?>
                <div class="text-gray-400 text-sm">Frist abgelaufen</div>
            <?php else: ?>
                <div class="flex items-baseline gap-3">
                    <div>
                        <span class="text-3xl font-extrabold text-red-600"><?= $daysLeftD2 ?></span>
                        <span class="text-red-600 text-sm ml-1">Tage</span>
                    </div>
                    <div class="text-gray-400 text-sm">·</div>
                    <div>
                        <span class="text-xl font-bold text-gray-700"><?= $remainingBeforeD2 ?></span>
                        <span class="text-gray-500 text-sm ml-1">Termine übrig</span>
                    </div>
                </div>
                <div class="text-xs text-gray-400 mt-1">Mindestens <?= $event['deadline_2_count'] ?> Teilnahmen erforderlich</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ══ Nächster Termin + Wetter ════════════════════════════════ -->
<?php if ($nextSession): ?>
<div class="bg-white rounded-xl shadow-sm border mb-6 overflow-hidden">
    <div class="flex flex-col sm:flex-row">
        <!-- Termin-Info -->
        <div class="flex-1 p-5">
            <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Nächster Termin</div>
            <div class="text-xl font-bold text-gray-900">
                <?= format_weekday($nextSession['session_date']) ?>, <?= format_date($nextSession['session_date']) ?>
            </div>
            <div class="text-gray-600 mt-1">
                <?= format_time($nextSession['session_time']) ?> Uhr
                <?php if ($nextSession['comment']): ?>
                    · <span class="text-gray-400"><?= e($nextSession['comment']) ?></span>
                <?php endif; ?>
            </div>
            <?php
            // Countdown zum Termin
            $sessionDT = new DateTime($nextSession['session_date'] . ' ' . $nextSession['session_time']);
            $diffToSession = $now->diff($sessionDT);
            if ($nextSession['session_date'] === date('Y-m-d')):
            ?>
                <div class="mt-2 inline-flex items-center gap-1 bg-red-100 text-red-700 text-xs font-bold px-3 py-1 rounded-full">
                    🔴 Heute!
                </div>
            <?php elseif ($diffToSession->days <= 3): ?>
                <div class="mt-2 inline-flex items-center gap-1 bg-orange-100 text-orange-700 text-xs font-bold px-3 py-1 rounded-full">
                    ⏰ In <?= $diffToSession->days ?> <?= $diffToSession->days === 1 ? 'Tag' : 'Tagen' ?>
                </div>
            <?php endif; ?>
        </div>
        <!-- Wetter -->
        <?php if ($weather): ?>
        <div class="sm:w-48 p-5 sm:border-l border-t sm:border-t-0 bg-gradient-to-br from-blue-50 to-white flex flex-col items-center justify-center text-center">
            <div class="text-4xl mb-1"><?= $weather['emoji'] ?></div>
            <div class="text-sm font-semibold text-gray-700"><?= e($weather['desc']) ?></div>
            <div class="text-lg font-bold text-gray-900 mt-1">
                <?= $weather['temp_min'] ?>° / <?= $weather['temp_max'] ?>°
            </div>
            <?php if ($weather['rain_prob'] > 0): ?>
                <div class="text-xs mt-1 <?= $weather['rain_prob'] > 50 ? 'text-blue-600 font-semibold' : 'text-gray-400' ?>">
                    💧 <?= $weather['rain_prob'] ?>% Regen
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- ══ Mein Status (Cookie-basiert) ════════════════════════════ -->
<div class="bg-white rounded-xl shadow-sm border mb-6 overflow-hidden">
    <div class="px-5 py-3 border-b flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <h2 class="font-bold text-gray-800">👤 Mein Status</h2>
        <select id="myMemberSelect" onchange="selectMyMember(this.value)"
                class="border rounded-lg px-3 py-1.5 text-sm bg-white focus:ring-2 focus:ring-red-500 focus:border-red-500 max-w-xs">
            <option value="0">– Wähle deinen Namen –</option>
            <?php foreach ($members as $m): ?>
                <option value="<?= $m['id'] ?>" <?= $myMemberId === $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if ($myStats): ?>
    <div class="p-5">
        <div class="grid grid-cols-2 sm:grid-cols-<?= $d1Enabled ? '4' : '3' ?> gap-4">
            <!-- Teilnahmen -->
            <div class="text-center">
                <div class="text-2xl font-bold text-gray-900"><?= $myStats['present'] ?></div>
                <div class="text-xs text-gray-500">Teilnahmen</div>
            </div>
            <?php if ($d1Enabled && $myDeadline1): ?>
            <!-- Frist 1 -->
            <div class="text-center">
                <div class="inline-flex items-center gap-1 px-3 py-1 rounded-lg text-sm font-bold <?= $myDeadline1['class'] ?>">
                    <?= $myDeadline1['icon'] ?> <?= $myStats['present'] ?>/<?= $event['deadline_1_count'] ?>
                </div>
                <div class="text-xs text-gray-500 mt-1"><?= $d1Name ?></div>
            </div>
            <?php endif; ?>
            <!-- Frist 2 -->
            <div class="text-center">
                <div class="inline-flex items-center gap-1 px-3 py-1 rounded-lg text-sm font-bold <?= $myDeadline2['class'] ?>">
                    <?= $myDeadline2['icon'] ?> <?= $myStats['present'] ?>/<?= $event['deadline_2_count'] ?>
                </div>
                <div class="text-xs text-gray-500 mt-1"><?= $d2Name ?></div>
            </div>
            <!-- Strafkasse -->
            <div class="text-center">
                <div class="text-2xl font-bold <?= $myPenalty > 0 ? 'text-red-600' : 'text-green-600' ?>">
                    <?= $myPenalty > 0 ? format_currency($myPenalty) : '0 €' ?>
                </div>
                <div class="text-xs text-gray-500">Strafkasse</div>
            </div>
        </div>
        <!-- Link zur Detailseite -->
        <div class="mt-4 text-center">
            <a href="index.php?event=<?= e($event['public_token']) ?>&member=<?= $myMemberId ?>"
               class="inline-flex items-center gap-1 text-red-600 hover:text-red-800 text-sm font-semibold hover:underline">
                Meine vollständige Übersicht →
            </a>
        </div>
    </div>
    <?php else: ?>
    <div class="px-5 py-6 text-center text-gray-400 text-sm">
        Wähle oben deinen Namen, um deinen persönlichen Status zu sehen.
    </div>
    <?php endif; ?>
</div>

<!-- Statistik-Karten -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-white rounded-xl shadow-sm border p-4">
        <div class="text-3xl mb-1">👥</div>
        <div class="text-2xl font-bold text-gray-900"><?= count($members) ?></div>
        <div class="text-gray-500 text-sm">Teilnehmer</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-4">
        <div class="text-3xl mb-1">📊</div>
        <div class="text-2xl font-bold text-gray-900"><?= $avgPresent ?></div>
        <div class="text-gray-500 text-sm">Ø Teilnahmen</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-4">
        <div class="text-3xl mb-1">✅</div>
        <div class="text-2xl font-bold text-gray-900"><?= $totalPast ?> / <?= $totalSessions ?></div>
        <div class="text-gray-500 text-sm">Termine absolviert</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-4">
        <div class="text-3xl mb-1">💰</div>
        <div class="text-2xl font-bold text-gray-900"><?= format_currency($totalPenalty) ?></div>
        <div class="text-gray-500 text-sm">Strafkasse</div>
    </div>
</div>

<!-- Diagramme -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <!-- Balkendiagramm: Teilnahmen pro Teilnehmer -->
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h2 class="font-bold text-gray-800 mb-4">Teilnahmen pro Teilnehmer</h2>
        <div style="min-height: <?= max(200, count($memberStats) * 28) ?>px">
            <canvas id="chartParticipation"></canvas>
        </div>
    </div>

    <!-- Liniendiagramm: Teilnahmen über Zeit -->
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h2 class="font-bold text-gray-800 mb-4">Teilnahmen-Entwicklung</h2>
        <div style="min-height: 200px">
            <canvas id="chartTimeline"></canvas>
        </div>
    </div>
</div>

<!-- Terminliste (Accordion) -->
<?php
// Termine in 3 Gruppen aufteilen
$pastSessions = [];
$nextSessionItem = null;
$futureSessions = [];
$nextFoundDash = false;
foreach ($sessions as $s) {
    $s['_ended'] = is_session_ended($s, $sessionDuration);
    if (!$nextFoundDash && !$s['_ended']) {
        $nextSessionItem = $s;
        $nextFoundDash = true;
    } elseif ($s['_ended']) {
        $pastSessions[] = $s;
    } else {
        $futureSessions[] = $s;
    }
}
?>
<div class="bg-white rounded-xl shadow-sm border mb-8 overflow-hidden">
    <div class="px-5 py-4 border-b">
        <h2 class="font-bold text-gray-800">📅 Übungstermine</h2>
    </div>

    <!-- ═══ Vergangene Termine (Accordion) ═══ -->
    <?php if (!empty($pastSessions)): ?>
    <div style="border-bottom: 2px solid #d1d5db;">
        <div onclick="document.getElementById('pastSessionsBody').classList.toggle('hidden'); var icon = document.getElementById('pastIcon'); icon.textContent = icon.textContent === '▶' ? '▼' : '▶';"
             class="px-5 py-3 flex items-center justify-between cursor-pointer hover:bg-gray-50 transition" style="background-color: #f3f4f6;">
            <div class="flex items-center gap-2">
                <span id="pastIcon" class="text-xs text-gray-400">▶</span>
                <span class="font-semibold text-gray-500">Vergangene Termine</span>
                <span class="text-xs bg-gray-200 text-gray-500 px-2 py-0.5 rounded-full"><?= count($pastSessions) ?></span>
            </div>
        </div>
        <div id="pastSessionsBody" class="hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <?php foreach ($pastSessions as $s):
                        $att = $sessionAttendance[$s['id']] ?? ['present' => 0, 'excused' => 0, 'absent' => 0];
                        $hasRoleRow = false;
                        if ($dashRolesEnabled) {
                            $roleAvail = get_session_role_availability($s['id'], $event['id']);
                            $hasRoleRow = !empty($roleAvail);
                        }
                    ?>
                    <tr style="background-color: #f3f4f6; color: #9ca3af; <?= $hasRoleRow ? '' : 'border-bottom: 2px solid #d1d5db;' ?>">
                        <td class="px-4 py-2.5 font-medium"><?= format_date($s['session_date']) ?></td>
                        <td class="px-4 py-2.5 hidden sm:table-cell"><?= format_weekday($s['session_date']) ?></td>
                        <td class="px-4 py-2.5"><?= format_time($s['session_time']) ?> Uhr</td>
                        <td class="px-4 py-2.5 hidden md:table-cell"><?= e($s['comment']) ?></td>
                        <td class="px-4 py-2.5 text-center"><?= $att['present'] ?: '-' ?></td>
                        <td class="px-4 py-2.5 text-center"><?= $att['excused'] ?: '-' ?></td>
                        <td class="px-4 py-2.5 text-center"><?= $att['absent'] ?: '-' ?></td>
                    </tr>
                    <?php if ($hasRoleRow): ?>
                    <tr style="background-color: #f3f4f6; color: #9ca3af; border-bottom: 2px solid #d1d5db;">
                        <td colspan="7" class="px-4 pt-0 pb-2">
                            <div class="flex flex-wrap gap-1">
                                <?php foreach ($roleAvail as $ra): ?>
                                <span class="text-xs px-1.5 py-0.5 rounded <?= $ra['ok'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700 font-bold' ?>">
                                    <?= e($ra['name']) ?> <?= $ra['available'] ?>/<?= $ra['total'] ?> <?= $ra['ok'] ? '✅' : '❌' ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ═══ Nächster Termin (immer sichtbar) ═══ -->
    <?php if ($nextSessionItem):
        $s = $nextSessionItem;
        $isToday = $s['session_date'] === date('Y-m-d');
        $att = $sessionAttendance[$s['id']] ?? ['present' => 0, 'excused' => 0, 'absent' => 0];
        $hasRoleRow = false;
        if ($dashRolesEnabled) {
            $roleAvail = get_session_role_availability($s['id'], $event['id']);
            $hasRoleRow = !empty($roleAvail);
        }
    ?>
    <div style="border-bottom: 2px solid #d1d5db;">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead style="background-color: #e5e7eb;">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Datum</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 hidden sm:table-cell">Tag</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Uhrzeit</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 hidden md:table-cell">Kommentar</th>
                        <th class="px-4 py-3 text-center font-semibold text-gray-700">✅</th>
                        <th class="px-4 py-3 text-center font-semibold text-gray-700">🟡</th>
                        <th class="px-4 py-3 text-center font-semibold text-gray-700">❌</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="background-color: #fed7aa; border-left: 5px solid #ea580c; font-weight: 600; <?= $hasRoleRow ? '' : 'border-bottom: 2px solid #d1d5db;' ?>">
                        <td class="px-4 py-3 font-medium">
                            <?= format_date($s['session_date']) ?>
                            <?php if ($isToday): ?><span class="text-xs bg-red-600 text-white px-2 py-0.5 rounded-full ml-1">HEUTE</span><?php endif; ?>
                            <span style="font-size: 11px; background-color: #ea580c; color: white; padding: 2px 8px; border-radius: 9999px; margin-left: 4px; font-weight: 600;">NÄCHSTER</span>
                        </td>
                        <td class="px-4 py-3 hidden sm:table-cell"><?= format_weekday($s['session_date']) ?></td>
                        <td class="px-4 py-3"><?= format_time($s['session_time']) ?> Uhr</td>
                        <td class="px-4 py-3 hidden md:table-cell" style="color: #6b7280;"><?= e($s['comment']) ?></td>
                        <td class="px-4 py-3 text-center"><?= $att['present'] ?: '-' ?></td>
                        <td class="px-4 py-3 text-center"><?= $att['excused'] ?: '-' ?></td>
                        <td class="px-4 py-3 text-center"><?= $att['absent'] ?: '-' ?></td>
                    </tr>
                    <?php if ($hasRoleRow): ?>
                    <tr style="background-color: #fed7aa; border-bottom: 2px solid #d1d5db;">
                        <td colspan="7" class="px-4 pt-0 pb-2">
                            <div class="flex flex-wrap gap-1">
                                <?php foreach ($roleAvail as $ra): ?>
                                <span class="text-xs px-1.5 py-0.5 rounded <?= $ra['ok'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700 font-bold' ?>">
                                    <?= e($ra['name']) ?> <?= $ra['available'] ?>/<?= $ra['total'] ?> <?= $ra['ok'] ? '✅' : '❌' ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php elseif (empty($pastSessions) && empty($futureSessions)): ?>
    <div class="px-5 py-8 text-center text-gray-400">Noch keine Termine vorhanden.</div>
    <?php endif; ?>

    <!-- ═══ Kommende Termine (Accordion) ═══ -->
    <?php if (!empty($futureSessions)): ?>
    <div>
        <div onclick="document.getElementById('futureSessionsBody').classList.toggle('hidden'); var icon = document.getElementById('futureIcon'); icon.textContent = icon.textContent === '▶' ? '▼' : '▶';"
             class="px-5 py-3 flex items-center justify-between cursor-pointer hover:bg-gray-50 transition">
            <div class="flex items-center gap-2">
                <span id="futureIcon" class="text-xs text-gray-400">▶</span>
                <span class="font-semibold text-gray-600">Kommende Termine</span>
                <span class="text-xs bg-gray-200 text-gray-500 px-2 py-0.5 rounded-full"><?= count($futureSessions) ?></span>
            </div>
        </div>
        <div id="futureSessionsBody" class="hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <?php foreach ($futureSessions as $s):
                        $isToday = $s['session_date'] === date('Y-m-d');
                        $att = $sessionAttendance[$s['id']] ?? ['present' => 0, 'excused' => 0, 'absent' => 0];
                        $hasRoleRow = false;
                        if ($dashRolesEnabled) {
                            $roleAvail = get_session_role_availability($s['id'], $event['id']);
                            $hasRoleRow = !empty($roleAvail);
                        }
                    ?>
                    <tr style="<?= $hasRoleRow ? '' : 'border-bottom: 2px solid #d1d5db;' ?>">
                        <td class="px-4 py-2.5 font-medium">
                            <?= format_date($s['session_date']) ?>
                            <?php if ($isToday): ?><span class="text-xs bg-red-600 text-white px-2 py-0.5 rounded-full ml-1">HEUTE</span><?php endif; ?>
                        </td>
                        <td class="px-4 py-2.5 hidden sm:table-cell"><?= format_weekday($s['session_date']) ?></td>
                        <td class="px-4 py-2.5"><?= format_time($s['session_time']) ?> Uhr</td>
                        <td class="px-4 py-2.5 hidden md:table-cell" style="color: #6b7280;"><?= e($s['comment']) ?></td>
                        <td class="px-4 py-2.5 text-center"><?= $att['present'] ?: '-' ?></td>
                        <td class="px-4 py-2.5 text-center"><?= $att['excused'] ?: '-' ?></td>
                        <td class="px-4 py-2.5 text-center"><?= $att['absent'] ?: '-' ?></td>
                    </tr>
                    <?php if ($hasRoleRow): ?>
                    <tr style="border-bottom: 2px solid #d1d5db;">
                        <td colspan="7" class="px-4 pt-0 pb-2">
                            <div class="flex flex-wrap gap-1">
                                <?php foreach ($roleAvail as $ra): ?>
                                <span class="text-xs px-1.5 py-0.5 rounded <?= $ra['ok'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700 font-bold' ?>">
                                    <?= e($ra['name']) ?> <?= $ra['available'] ?>/<?= $ra['total'] ?> <?= $ra['ok'] ? '✅' : '❌' ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Teilnehmer-Tabelle -->
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="px-5 py-4 border-b">
        <h2 class="font-bold text-gray-800">👥 Teilnehmer</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm" id="memberTable">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600 cursor-pointer hover:text-red-600" onclick="sortTable(0)">Name ↕</th>
                    <th class="px-4 py-3 text-center font-semibold text-gray-600 cursor-pointer hover:text-red-600" onclick="sortTable(1)">Teilnahmen ↕</th>
                    <th class="px-4 py-3 text-center font-semibold text-gray-600 cursor-pointer hover:text-red-600 hidden sm:table-cell" onclick="sortTable(2)">Quote ↕</th>
                    <?php if ($d1Enabled): ?>
                    <th class="px-4 py-3 text-center font-semibold text-gray-600" title="<?= e($event['deadline_1_name'] ?? 'Frist 1') ?>: <?= format_date($event['deadline_1_date']) ?> (mind. <?= $event['deadline_1_count'] ?>)"><?= e($event['deadline_1_name'] ?? 'Frist 1') ?></th>
                    <?php endif; ?>
                    <th class="px-4 py-3 text-center font-semibold text-gray-600" title="<?= e($event['deadline_2_name'] ?? 'Frist 2') ?>: <?= format_date($event['deadline_2_date']) ?> (mind. <?= $event['deadline_2_count'] ?>)"><?= e($event['deadline_2_name'] ?? 'Frist 2') ?></th>
                    <th class="px-4 py-3 text-center font-semibold text-gray-600 cursor-pointer hover:text-red-600 hidden md:table-cell" onclick="sortTable(<?= $d1Enabled ? 5 : 4 ?>)">Strafkasse ↕</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach ($memberStats as $m):
                    if ($d1Enabled) {
                        $d1 = calculate_deadline_status($m['present'], $event['deadline_1_count'], $event['deadline_1_date'], $totalSessions, $totalPast, $remainingBeforeD1);
                    }
                    $d2 = calculate_deadline_status($m['present'], $event['deadline_2_count'], $event['deadline_2_date'], $totalSessions, $totalPast, $remainingBeforeD2);
                    $penalty = $memberPenalties[$m['id']] ?? 0;
                ?>
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-4 py-3">
                        <a href="index.php?event=<?= e($event['public_token']) ?>&member=<?= $m['id'] ?>"
                           class="text-red-600 hover:text-red-800 font-medium hover:underline">
                            <?= e($m['name']) ?>
                        </a>
                        <?php if ($dashRolesEnabled):
                            $mRoles = get_member_roles($m['id']);
                            if (!empty($mRoles)):
                        ?>
                        <span class="ml-1 text-xs text-gray-400"><?= implode(', ', array_map(fn($r) => e($r['name']), $mRoles)) ?></span>
                        <?php endif; endif; ?>
                    </td>
                    <td class="px-4 py-3 text-center font-semibold" data-sort="<?= $m['present'] ?>"><?= $m['present'] ?></td>
                    <td class="px-4 py-3 text-center hidden sm:table-cell" data-sort="<?= $m['quote'] ?>"><?= $m['quote'] ?>%</td>
                    <?php if ($d1Enabled): ?>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-block px-2 py-1 rounded-lg text-xs font-semibold <?= $d1['class'] ?>">
                            <?= $d1['icon'] ?> <?= $m['present'] ?>/<?= $event['deadline_1_count'] ?>
                        </span>
                    </td>
                    <?php endif; ?>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-block px-2 py-1 rounded-lg text-xs font-semibold <?= $d2['class'] ?>">
                            <?= $d2['icon'] ?> <?= $m['present'] ?>/<?= $event['deadline_2_count'] ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center hidden md:table-cell" data-sort="<?= $penalty ?>">
                        <?php if ($penalty > 0): ?>
                            <span class="text-red-600 font-semibold"><?= format_currency($penalty) ?></span>
                        <?php else: ?>
                            <span class="text-gray-300">–</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// ── Mein Status: Cookie setzen ──────────────────────────────
function selectMyMember(memberId) {
    const id = parseInt(memberId);
    if (id > 0) {
        document.cookie = 'laz_member_<?= $event['id'] ?>=' + id + ';path=/;max-age=31536000;SameSite=Lax';
    } else {
        document.cookie = 'laz_member_<?= $event['id'] ?>=;path=/;max-age=0';
    }
    location.reload();
}

// ── Tabellensortierung ──────────────────────────────────────
let sortDir = {};
function sortTable(colIdx) {
    const table = document.getElementById('memberTable');
    const tbody = table.tBodies[0];
    const rows = Array.from(tbody.rows);
    const dir = sortDir[colIdx] = !(sortDir[colIdx] ?? false);

    rows.sort((a, b) => {
        let av = a.cells[colIdx].getAttribute('data-sort') || a.cells[colIdx].textContent.trim();
        let bv = b.cells[colIdx].getAttribute('data-sort') || b.cells[colIdx].textContent.trim();
        const an = parseFloat(av), bn = parseFloat(bv);
        if (!isNaN(an) && !isNaN(bn)) return dir ? an - bn : bn - an;
        return dir ? av.localeCompare(bv, 'de') : bv.localeCompare(av, 'de');
    });

    rows.forEach(r => tbody.appendChild(r));
}

// ── Charts ──────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    // Balkendiagramm
    const memberData = <?= json_encode(array_map(fn($m) => ['name' => $m['name'], 'present' => $m['present']], $memberStats)) ?>;
    memberData.sort((a, b) => b.present - a.present);

    new Chart(document.getElementById('chartParticipation'), {
        type: 'bar',
        data: {
            labels: memberData.map(m => m.name),
            datasets: [{
                label: 'Teilnahmen',
                data: memberData.map(m => m.present),
                backgroundColor: memberData.map(m => m.present >= <?= $event['deadline_2_count'] ?> ? '#22c55e' : (m.present >= <?= $event['deadline_1_count'] ?> ? '#f59e0b' : '#ef4444')),
                borderRadius: 4,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, grid: { color: '#f3f4f6' } },
                y: { grid: { display: false } }
            }
        }
    });

    // Liniendiagramm
    const timeData = <?= json_encode($attendanceOverTime) ?>;
    if (timeData.length > 0) {
        new Chart(document.getElementById('chartTimeline'), {
            type: 'line',
            data: {
                labels: timeData.map(t => t.date),
                datasets: [{
                    label: 'Anwesend',
                    data: timeData.map(t => t.count),
                    borderColor: '#dc2626',
                    backgroundColor: 'rgba(220, 38, 38, 0.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4,
                    pointBackgroundColor: '#dc2626',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: '#f3f4f6' }, ticks: { maxRotation: 45 } },
                    y: { beginAtZero: true, grid: { color: '#f3f4f6' } }
                }
            }
        });
    }
});
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
