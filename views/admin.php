<?php
/**
 * Admin-Bereich
 */

$sessions = get_sessions($event['id']);
$members = get_members($event['id'], false); // Alle inkl. inaktive
$activeMembers = array_filter($members, fn($m) => $m['active']);
$penaltyTypes = get_penalty_types($event['id']);
$allPenalties = get_penalties_for_event($event['id']);
$totalPenalty = get_event_penalty_total($event['id']);
$penaltyByType = get_penalty_stats_by_type($event['id']);
$penaltyByMember = get_penalty_stats_by_member($event['id']);

$adminToken = $event['admin_token'];
$tab = $_GET['tab'] ?? 'overview';
$isArchived = ($event['status'] === 'archived');
$sessionDurationGlobal = (int)($event['session_duration_hours'] ?? 3);
$nextSessionAdmin = get_next_session($sessions, $sessionDurationGlobal);
$adminRolesEnabled = (bool)($event['roles_enabled'] ?? false);

$pageTitle = 'Admin – ' . $event['name'];
require __DIR__ . '/partials/header.php';
?>

<?php if ($isArchived): ?>
<div class="bg-yellow-50 border border-yellow-300 rounded-xl p-4 mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <p class="text-yellow-800 text-sm font-semibold">📦 Dieses Event ist archiviert. Alle Funktionen sind deaktiviert.</p>
    <button type="button" onclick="reactivateEvent()"
            class="bg-green-600 text-white px-5 py-2 rounded-lg text-sm font-bold hover:bg-green-700 transition shrink-0">
        🔄 Event reaktivieren
    </button>
</div>
<?php endif; ?>

<!-- Admin-Tabs -->
<div class="mb-6 flex flex-wrap gap-2 border-b pb-3">
    <?php
    $tabs = [
        'overview' => '📊 Übersicht',
        'attendance' => '✅ Anwesenheit',
        'penalties' => '💰 Strafen',
        'sessions' => '📅 Termine',
        'members' => '👥 Teilnehmer',
        'roles' => '🏷️ Rollen',
        'settings' => '⚙️ Einstellungen',
        'audit' => '📝 Audit-Log',
    ];
    foreach ($tabs as $key => $label):
        $active = $tab === $key;
        $url = 'index.php?event=' . e($event['public_token']) . '&admin=' . e($adminToken) . '&tab=' . $key;
    ?>
    <a href="<?= $url ?>"
       class="px-3 py-2 rounded-lg text-sm font-medium transition <?= $active ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
        <?= $label ?>
    </a>
    <?php endforeach; ?>
</div>

<?php
// ══════════════════════════════════════════════════════════════
// Tab: Übersicht
// ══════════════════════════════════════════════════════════════
if ($tab === 'overview'):
    $memberStats = get_member_stats($event['id']);

    // Wetter für nächsten Termin
    $ovWeather = null;
    if ($nextSessionAdmin) {
        $wLat = (float)($event['weather_lat'] ?? 0);
        $wLng = (float)($event['weather_lng'] ?? 0);
        if ($wLat != 0 && $wLng != 0) {
            $weatherCacheFile = sys_get_temp_dir() . '/laz_weather_' . $event['id'] . '_' . $nextSessionAdmin['session_date'] . '.json';
            $weatherCacheAge = file_exists($weatherCacheFile) ? (time() - filemtime($weatherCacheFile)) : PHP_INT_MAX;
            if ($weatherCacheAge < 3600 && file_exists($weatherCacheFile)) {
                $ovWeather = json_decode(file_get_contents($weatherCacheFile), true);
            } else {
                $wUrl = 'https://api.open-meteo.com/v1/forecast?latitude=' . $wLat . '&longitude=' . $wLng
                    . '&daily=temperature_2m_max,temperature_2m_min,precipitation_probability_max,weathercode'
                    . '&timezone=Europe/Berlin&start_date=' . $nextSessionAdmin['session_date'] . '&end_date=' . $nextSessionAdmin['session_date'];
                $ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
                $wJson = @file_get_contents($wUrl, false, $ctx);
                if ($wJson) {
                    $wData = json_decode($wJson, true);
                    if (isset($wData['daily'])) {
                        $wd = $wData['daily'];
                        $wmoCode = $wd['weathercode'][0] ?? -1;
                        $wmoMap = [0=>['☀️','Klar'],1=>['🌤️','Überwiegend klar'],2=>['⛅','Teilweise bewölkt'],3=>['☁️','Bewölkt'],45=>['🌫️','Nebel'],48=>['🌫️','Reifnebel'],51=>['🌦️','Leichter Nieselregen'],53=>['🌦️','Nieselregen'],55=>['🌧️','Starker Nieselregen'],61=>['🌦️','Leichter Regen'],63=>['🌧️','Regen'],65=>['🌧️','Starker Regen'],71=>['🌨️','Leichter Schnee'],73=>['❄️','Schnee'],75=>['❄️','Starker Schnee'],80=>['🌦️','Leichte Regenschauer'],81=>['🌧️','Regenschauer'],82=>['⛈️','Starke Regenschauer'],95=>['⛈️','Gewitter'],96=>['⛈️','Gewitter mit Hagel']];
                        $wInfo = $wmoMap[$wmoCode] ?? ['🌡️','Unbekannt'];
                        $ovWeather = ['emoji'=>$wInfo[0],'desc'=>$wInfo[1],'temp_max'=>round($wd['temperature_2m_max'][0]),'temp_min'=>round($wd['temperature_2m_min'][0]),'rain_prob'=>$wd['precipitation_probability_max'][0]??0];
                        @file_put_contents($weatherCacheFile, json_encode($ovWeather));
                    }
                }
            }
        }
    }
?>

<!-- Statistik-Karten -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border p-4 text-center">
        <div class="text-2xl font-bold text-gray-900"><?= count($activeMembers) ?></div>
        <div class="text-gray-500 text-sm">Aktive Teilnehmer</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-4 text-center">
        <div class="text-2xl font-bold text-gray-900"><?= count($sessions) ?></div>
        <div class="text-gray-500 text-sm">Termine</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-4 text-center">
        <div class="text-2xl font-bold text-gray-900"><?= count($allPenalties) ?></div>
        <div class="text-gray-500 text-sm">Strafen vergeben</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-4 text-center">
        <div class="text-2xl font-bold text-red-600"><?= format_currency($totalPenalty) ?></div>
        <div class="text-gray-500 text-sm">Strafkasse</div>
    </div>
</div>

<!-- Nächster Termin + Wetter -->
<?php if ($nextSessionAdmin): ?>
<div class="bg-white rounded-xl shadow-sm border p-5 mb-6">
    <h3 class="font-bold text-gray-800 mb-3">📅 Nächster Termin</h3>
    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
        <div class="flex-1">
            <div class="text-lg font-bold text-gray-900">
                <?= format_weekday($nextSessionAdmin['session_date']) ?>, <?= format_date($nextSessionAdmin['session_date']) ?> – <?= format_time($nextSessionAdmin['session_time']) ?> Uhr
            </div>
            <?php if ($nextSessionAdmin['comment']): ?>
                <div class="text-sm text-gray-500 mt-1"><?= e($nextSessionAdmin['comment']) ?></div>
            <?php endif; ?>
            <?php if ($adminRolesEnabled):
                $ovRoleAvail = get_session_role_availability($nextSessionAdmin['id'], $event['id']);
                if (!empty($ovRoleAvail)):
            ?>
            <div class="flex flex-wrap gap-1 mt-2">
                <?php foreach ($ovRoleAvail as $ra): ?>
                <span class="text-xs px-1.5 py-0.5 rounded <?= $ra['ok'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700 font-bold' ?>">
                    <?= e($ra['name']) ?> <?= $ra['available'] ?>/<?= $ra['total'] ?> <?= $ra['ok'] ? '✅' : '❌' ?>
                </span>
                <?php endforeach; ?>
            </div>
            <?php endif; endif; ?>
        </div>
        <?php if ($ovWeather): ?>
        <div class="bg-gray-50 rounded-lg p-4 text-center shrink-0" style="min-width: 140px;">
            <div class="text-3xl"><?= $ovWeather['emoji'] ?></div>
            <div class="font-semibold text-gray-800 text-sm"><?= $ovWeather['desc'] ?></div>
            <div class="text-gray-600 text-sm"><?= $ovWeather['temp_min'] ?>° / <?= $ovWeather['temp_max'] ?>°C</div>
            <div class="text-xs text-gray-400">🌧️ <?= $ovWeather['rain_prob'] ?>%</div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Strafkasse -->
<?php
    $hasAnyPenalties = array_sum(array_column($penaltyByType, 'count')) > 0;
?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="font-bold text-gray-800 mb-4">💰 Strafkasse nach Typ</h3>
        <?php if ($hasAnyPenalties): ?>
        <div style="max-width: 240px; margin: 0 auto 1rem;">
            <canvas id="chartPenaltyType"></canvas>
        </div>
        <?php endif; ?>
        <div class="divide-y text-sm">
            <?php
            $totalCount = 0; $totalSum = 0;
            foreach ($penaltyByType as $s):
                $count = (int)$s['count']; $total = (float)$s['total'];
                $totalCount += $count; $totalSum += $total;
            ?>
            <div class="py-2 flex justify-between items-center <?= $count === 0 ? 'text-gray-300' : '' ?>">
                <span><?= e($s['description']) ?>
                    <?php if ($count > 0): ?><span class="inline-flex items-center justify-center bg-red-100 text-red-700 text-xs font-bold rounded-full px-2 py-0.5 ml-1"><?= $count ?>×</span><?php endif; ?>
                </span>
                <span class="font-semibold <?= $count > 0 ? 'text-red-600' : 'text-gray-300' ?>"><?= format_currency($total) ?></span>
            </div>
            <?php endforeach; ?>
            <?php if ($totalCount > 0): ?>
            <div class="py-2 flex justify-between items-center font-bold">
                <span>Gesamt (<?= $totalCount ?> Strafen)</span>
                <span class="text-red-700"><?= format_currency($totalSum) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="font-bold text-gray-800 mb-4">💰 Strafkasse nach Teilnehmer</h3>
        <?php if ($hasAnyPenalties): ?>
        <div style="min-height: <?= max(200, count($penaltyByMember) * 28) ?>px">
            <canvas id="chartPenaltyMember"></canvas>
        </div>
        <?php else: ?>
        <div class="text-center text-gray-400 py-6"><div class="text-3xl mb-2">👥</div><p>Noch keine Strafen vergeben.</p></div>
        <?php endif; ?>
    </div>
</div>
<?php if ($hasAnyPenalties): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var ptData = <?= json_encode(array_values(array_filter($penaltyByType, fn($s) => (int)$s['count'] > 0))) ?>;
    if (ptData.length > 0) {
        new Chart(document.getElementById('chartPenaltyType'), {
            type: 'doughnut', data: {
                labels: ptData.map(function(d) { return d.description + ' (' + d.count + '×)'; }),
                datasets: [{ data: ptData.map(function(d) { return parseInt(d.count); }), backgroundColor: ['#dc2626','#f59e0b','#22c55e','#3b82f6','#8b5cf6','#ec4899','#14b8a6'], borderWidth: 0 }]
            }, options: { responsive: true, cutout: '55%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 8 } } } }
        });
    }
    var pmData = <?= json_encode(array_values(array_filter($penaltyByMember, fn($s) => (float)$s['total'] > 0))) ?>;
    if (pmData.length > 0) {
        pmData.sort(function(a, b) { return b.total - a.total; });
        new Chart(document.getElementById('chartPenaltyMember'), {
            type: 'bar', data: {
                labels: pmData.map(function(d) { return d.name; }),
                datasets: [{ label: 'Strafen (€)', data: pmData.map(function(d) { return parseFloat(d.total); }), backgroundColor: '#dc2626', borderRadius: 4 }]
            }, options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, grid: { color: '#f3f4f6' } }, y: { grid: { display: false } } } }
        });
    }
});
</script>
<?php endif; ?>

<!-- Links -->
<div class="bg-white rounded-xl shadow-sm border p-5">
    <h3 class="font-bold text-gray-800 mb-2">🔗 Links</h3>
    <div class="space-y-3">
        <div>
            <label class="text-xs font-semibold text-gray-500">Öffentliche URL (für Teilnehmer):</label>
            <input type="text" readonly value="<?= e(get_base_url() . '/index.php?event=' . $event['public_token']) ?>"
                   class="w-full text-xs p-2 bg-gray-50 border rounded font-mono mt-1" onclick="this.select()">
        </div>
        <div>
            <label class="text-xs font-semibold text-gray-500">Admin-URL:</label>
            <input type="text" readonly value="<?= e(get_base_url() . '/index.php?event=' . $event['public_token'] . '&admin=' . $adminToken) ?>"
                   class="w-full text-xs p-2 bg-gray-50 border rounded font-mono mt-1" onclick="this.select()">
        </div>
    </div>
</div>

<?php
// ══════════════════════════════════════════════════════════════
// Tab: Teilnehmer
// ══════════════════════════════════════════════════════════════
elseif ($tab === 'members'):
?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Teilnehmer hinzufügen -->
    <div class="lg:col-span-1 space-y-6">
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="font-bold text-gray-800 mb-4">Teilnehmer hinzufügen</h3>
            <div>
                <div class="space-y-3">
                    <input type="text" id="memberName" placeholder="Name" required
                           class="w-full border rounded-lg p-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500">
                    <input type="text" id="memberRole" placeholder="Funktion (optional)"
                           class="w-full border rounded-lg p-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500">
                    <button type="button" onclick="addMember()" class="w-full bg-red-600 text-white py-2 rounded-lg font-semibold hover:bg-red-700 transition">
                        Hinzufügen
                    </button>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="font-bold text-gray-800 mb-4">Bulk-Import</h3>
            <div>
                <textarea id="bulkNames" rows="6" placeholder="Ein Name pro Zeile..."
                          class="w-full border rounded-lg p-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500 mb-3"></textarea>
                <button type="button" onclick="bulkImportMembers()" class="w-full bg-gray-600 text-white py-2 rounded-lg font-semibold hover:bg-gray-700 transition">
                    Importieren
                </button>
            </div>
        </div>
    </div>

    <!-- Teilnehmerliste -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="px-5 py-4 border-b">
                <h3 class="font-bold text-gray-800"><?= count($members) ?> Teilnehmer</h3>
            </div>
            <div class="divide-y">
                <?php foreach ($members as $m): ?>
                <div class="px-5 py-3 flex items-center justify-between" id="member-row-<?= $m['id'] ?>">
                    <div>
                        <span class="font-medium <?= $m['active'] ? 'text-gray-800' : 'text-gray-400 line-through' ?>">
                            <?= e($m['name']) ?>
                        </span>
                        <?php if ($m['role']): ?>
                            <span class="text-xs text-gray-400 ml-1">(<?= e($m['role']) ?>)</span>
                        <?php endif; ?>
                        <?php if (!$m['active']): ?>
                            <span class="text-xs bg-gray-200 text-gray-500 px-2 py-0.5 rounded-full ml-1">Inaktiv</span>
                        <?php endif; ?>
                    </div>
                    <button onclick="editMember(<?= $m['id'] ?>, '<?= e(addslashes($m['name'])) ?>', '<?= e(addslashes($m['role'])) ?>', <?= $m['active'] ? 'true' : 'false' ?>)"
                            class="text-gray-400 hover:text-red-600 text-sm transition">
                        ✏️ Bearbeiten
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Bearbeiten-Modal -->
<div id="editMemberModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6">
        <h3 class="font-bold text-lg mb-4">Teilnehmer bearbeiten</h3>
        <div>
            <input type="hidden" id="editMemberId">
            <div class="space-y-3 mb-4">
                <input type="text" id="editMemberName" placeholder="Name" required
                       class="w-full border rounded-lg p-2 text-sm">
                <input type="text" id="editMemberRole" placeholder="Funktion"
                       class="w-full border rounded-lg p-2 text-sm">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" id="editMemberActive" class="rounded">
                    <span>Aktiv</span>
                </label>
            </div>
            <div class="flex gap-2">
                <button type="button" onclick="saveMember()" class="flex-1 bg-red-600 text-white py-2 rounded-lg font-semibold hover:bg-red-700">Speichern</button>
                <button type="button" onclick="document.getElementById('editMemberModal').classList.add('hidden')"
                        class="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg font-semibold hover:bg-gray-300">Abbrechen</button>
            </div>
        </div>
    </div>
</div>

<?php
// ══════════════════════════════════════════════════════════════
// Tab: Termine
// ══════════════════════════════════════════════════════════════
elseif ($tab === 'sessions'):
?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-1 space-y-6">
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="font-bold text-gray-800 mb-4">Termin hinzufügen</h3>
            <div>
                <div class="space-y-3">
                    <input type="date" id="sessionDate" required class="w-full border rounded-lg p-2 text-sm">
                    <input type="time" id="sessionTime" required class="w-full border rounded-lg p-2 text-sm">
                    <input type="text" id="sessionComment" placeholder="Kommentar (optional)" class="w-full border rounded-lg p-2 text-sm">
                    <button type="button" onclick="addSession()" class="w-full bg-red-600 text-white py-2 rounded-lg font-semibold hover:bg-red-700 transition">
                        Hinzufügen
                    </button>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="font-bold text-gray-800 mb-4">Bulk-Import</h3>
            <p class="text-xs text-gray-400 mb-2">Format: DD.MM.YYYY HH:MM Kommentar</p>
            <div>
                <textarea id="bulkSessions" rows="6" placeholder="01.01.2026 18:30 Kommentar..."
                          class="w-full border rounded-lg p-2 text-sm mb-3 font-mono"></textarea>
                <button type="button" onclick="bulkImportSessions()" class="w-full bg-gray-600 text-white py-2 rounded-lg font-semibold hover:bg-gray-700 transition">
                    Importieren
                </button>
            </div>
        </div>
    </div>

    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="px-5 py-4 border-b">
                <h3 class="font-bold text-gray-800"><?= count($sessions) ?> Termine</h3>
            </div>
            <div class="divide-y max-h-screen overflow-y-auto">
                <?php foreach ($sessions as $s):
                    $isPast = $s['session_date'] < date('Y-m-d');
                ?>
                <div class="px-5 py-3 flex items-center justify-between <?= $isPast ? 'bg-gray-50 text-gray-400' : '' ?>">
                    <div>
                        <span class="font-medium"><?= format_weekday($s['session_date']) ?>, <?= format_date($s['session_date']) ?></span>
                        <span class="text-sm ml-2"><?= format_time($s['session_time']) ?> Uhr</span>
                        <?php if ($s['comment']): ?>
                            <span class="text-xs text-gray-400 ml-2"><?= e($s['comment']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="flex gap-2">
                        <a href="index.php?event=<?= e($event['public_token']) ?>&admin=<?= e($adminToken) ?>&tab=attendance&session_id=<?= $s['id'] ?>"
                           class="text-blue-500 hover:text-blue-700 text-xs font-medium">✅ Anwesenheit</a>
                        <button onclick="deleteSession(<?= $s['id'] ?>)" class="text-red-400 hover:text-red-600 text-xs">🗑️</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php
// ══════════════════════════════════════════════════════════════
// Tab: Anwesenheit
// ══════════════════════════════════════════════════════════════
elseif ($tab === 'attendance'):
    $sessionDuration = (int)($event['session_duration_hours'] ?? 3);
    $attRolesEnabled = (bool)($event['roles_enabled'] ?? false);

    // Alle Anwesenheitsdaten vorladen
    $allAttData = [];
    foreach ($sessions as $s) {
        $sAtt = get_attendance_for_session($s['id']);
        $lookup = [];
        foreach ($sAtt as $a) { $lookup[$a['member_id']] = $a; }
        $allAttData[$s['id']] = [
            'present' => count(array_filter($sAtt, fn($a) => $a['status'] === 'present')),
            'excused' => count(array_filter($sAtt, fn($a) => $a['status'] === 'excused')),
            'absent' => count(array_filter($sAtt, fn($a) => $a['status'] === 'absent')),
            'members' => $lookup,
        ];
    }

    // Termine in 3 Gruppen aufteilen
    $pastAtt = []; $nextAtt = null; $futureAtt = [];
    $nf = false;
    foreach ($sessions as $s) {
        $s['_ended'] = is_session_ended($s, $sessionDuration);
        if (!$nf && !$s['_ended']) { $nextAtt = $s; $nf = true; }
        elseif ($s['_ended']) { $pastAtt[] = $s; }
        else { $futureAtt[] = $s; }
    }

    // Auto-expand per URL-Parameter
    $autoExpandId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : ($nextAtt ? $nextAtt['id'] : 0);
?>

<?php
// ── Helper: Rendert eine Session-Zeile + aufklappbares Panel ──
function render_att_session($s, $sData, $activeMembers, $attRolesEnabled, $eventId, $isExpanded, $isNext) {
    $ended = $s['_ended'] ?? false;
    $isToday = $s['session_date'] === date('Y-m-d');
    $totalMarked = $sData['present'] + $sData['excused'] + $sData['absent'];

    if ($isNext) { $rowStyle = 'background-color: #fed7aa; border-left: 5px solid #ea580c; font-weight: 600;'; }
    elseif ($isToday && !$ended) { $rowStyle = 'background-color: #fee2e2; font-weight: 600;'; }
    elseif ($ended) { $rowStyle = 'background-color: #f3f4f6; color: #9ca3af;'; }
    else { $rowStyle = ''; }
?>
    <div style="<?= $rowStyle ?>border-bottom: 1px solid #e5e7eb; cursor: pointer;"
         onclick="toggleAttendance(<?= $s['id'] ?>)">
        <div class="px-5 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-sm" id="expand-icon-<?= $s['id'] ?>"><?= $isExpanded ? '▼' : '▶' ?></span>
                <span class="font-medium text-sm">
                    <?= format_weekday($s['session_date']) ?>, <?= format_date($s['session_date']) ?> – <?= format_time($s['session_time']) ?>
                </span>
                <?php if ($s['comment']): ?>
                    <span class="text-xs" style="color: <?= $ended ? '#9ca3af' : '#6b7280' ?>;">(<?= e($s['comment']) ?>)</span>
                <?php endif; ?>
                <?php if ($isToday && !$ended): ?>
                    <span style="font-size: 10px; background-color: #dc2626; color: white; padding: 1px 6px; border-radius: 9999px;">HEUTE</span>
                <?php endif; ?>
                <?php if ($isNext): ?>
                    <span style="font-size: 10px; background-color: #ea580c; color: white; padding: 1px 6px; border-radius: 9999px;">NÄCHSTER</span>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-3 text-xs">
                <?php if ($totalMarked > 0): ?>
                    <span class="text-green-600 font-semibold">✅ <?= $sData['present'] ?></span>
                    <span class="text-yellow-600 font-semibold">🟡 <?= $sData['excused'] ?></span>
                    <span class="text-red-600 font-semibold">❌ <?= $sData['absent'] ?></span>
                <?php else: ?>
                    <span class="text-gray-400">Noch nicht erfasst</span>
                <?php endif; ?>
            </div>
            <?php if ($attRolesEnabled):
                $roleAvail = get_session_role_availability($s['id'], $eventId);
                if (!empty($roleAvail)):
            ?>
            <div class="flex flex-wrap gap-1 mt-1">
                <?php foreach ($roleAvail as $ra): ?>
                <span class="text-xs px-1.5 py-0.5 rounded <?= $ra['ok'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700 font-bold' ?>">
                    <?= e($ra['name']) ?> <?= $ra['available'] ?>/<?= $ra['total'] ?> <?= $ra['ok'] ? '✅' : '❌' ?>
                </span>
                <?php endforeach; ?>
            </div>
            <?php endif; endif; ?>
        </div>
    </div>
    <div id="att-panel-<?= $s['id'] ?>" class="<?= $isExpanded ? '' : 'hidden' ?>" style="border-bottom: 2px solid #dc2626; background-color: #fafafa;">
        <div class="px-5 py-3 flex flex-wrap items-center justify-between gap-2 border-b bg-gray-50">
            <span class="text-sm font-semibold text-gray-600">
                <?= format_weekday($s['session_date']) ?>, <?= format_date($s['session_date']) ?>
            </span>
            <div class="flex gap-2">
                <button type="button" onclick="event.stopPropagation(); setAllAttendanceFor(<?= $s['id'] ?>, 'present')"
                        class="bg-green-500 text-white px-3 py-1 rounded-lg text-xs font-semibold hover:bg-green-600 transition">Alle anwesend</button>
                <button type="button" onclick="event.stopPropagation(); setAllAttendanceFor(<?= $s['id'] ?>, 'absent')"
                        class="bg-red-500 text-white px-3 py-1 rounded-lg text-xs font-semibold hover:bg-red-600 transition">Alle fehlend</button>
            </div>
        </div>
        <div class="divide-y">
            <?php foreach ($activeMembers as $m):
                $mAtt = $sData['members'][$m['id']] ?? null;
                $mStatus = $mAtt['status'] ?? '';
                $excusedBy = $mAtt['excused_by'] ?? '';
            ?>
            <div class="px-5 py-2.5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-medium text-gray-800 text-sm"><?= e($m['name']) ?></span>
                    <?php if ($mAtt && $mStatus === 'excused' && $mAtt['excused_at']): ?>
                        <?php if ($excusedBy === 'member'): ?>
                            <span class="text-xs text-yellow-600">🟡 selbst entsch.</span>
                        <?php else: ?>
                            <span class="text-xs text-blue-500">🔵 durch Admin</span>
                        <?php endif; ?>
                    <?php endif; ?>
                    <span class="att-saved-indicator hidden text-xs text-green-500" id="att-saved-<?= $s['id'] ?>-<?= $m['id'] ?>">✓ gespeichert</span>
                </div>
                <div class="flex gap-1 att-group" data-session="<?= $s['id'] ?>" data-member="<?= $m['id'] ?>">
                    <input type="hidden" id="att-val-<?= $s['id'] ?>-<?= $m['id'] ?>" value="<?= e($mStatus) ?>">
                    <?php foreach (['present' => ['✅', 'Anwesend', 'bg-green-600', 'border-green-600'], 'excused' => ['🟡', 'Entsch.', 'bg-yellow-500', 'border-yellow-500'], 'absent' => ['❌', 'Fehlend', 'bg-red-600', 'border-red-600']] as $val => [$icon, $label, $bgActive, $borderActive]):
                        $isActive = $mStatus === $val;
                    ?>
                    <button type="button" data-status="<?= $val ?>"
                            onclick="event.stopPropagation(); setAttFor(<?= $s['id'] ?>, <?= $m['id'] ?>, '<?= $val ?>')"
                            class="att-btn px-2 py-1 rounded text-xs font-medium border transition
                            <?= $isActive ? "$bgActive text-white $borderActive" : 'bg-white text-gray-500 border-gray-200 hover:bg-gray-50' ?>">
                        <?= $icon ?> <span class="hidden sm:inline"><?= $label ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php } // end render_att_session ?>

<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="px-5 py-4 border-b" style="background-color: #e5e7eb;">
        <div class="flex items-center justify-between">
            <h3 class="font-bold text-gray-700">✅ Anwesenheit verwalten</h3>
            <span class="text-xs text-gray-500"><?= count($activeMembers) ?> Teilnehmer · <?= count($sessions) ?> Termine</span>
        </div>
    </div>

    <!-- Vergangene Termine -->
    <?php if (!empty($pastAtt)): ?>
    <div style="border-bottom: 2px solid #d1d5db;">
        <div onclick="document.getElementById('attPastBody').classList.toggle('hidden'); var i=document.getElementById('attPastIcon'); i.textContent=i.textContent==='▶'?'▼':'▶';"
             class="px-5 py-3 flex items-center justify-between cursor-pointer hover:bg-gray-50 transition" style="background-color: #f3f4f6;">
            <div class="flex items-center gap-2">
                <span id="attPastIcon" class="text-xs text-gray-400">▶</span>
                <span class="font-semibold text-gray-500">Vergangene Termine</span>
                <span class="text-xs bg-gray-200 text-gray-500 px-2 py-0.5 rounded-full"><?= count($pastAtt) ?></span>
            </div>
        </div>
        <div id="attPastBody" class="hidden">
            <?php foreach ($pastAtt as $s):
                $s['_ended'] = true;
                render_att_session($s, $allAttData[$s['id']], $activeMembers, $attRolesEnabled, $event['id'], ($s['id'] === $autoExpandId), false);
            endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Nächster Termin -->
    <?php if ($nextAtt):
        $nextAtt['_ended'] = false;
        render_att_session($nextAtt, $allAttData[$nextAtt['id']], $activeMembers, $attRolesEnabled, $event['id'], true, true);
    endif; ?>

    <!-- Kommende Termine -->
    <?php if (!empty($futureAtt)): ?>
    <div>
        <div onclick="document.getElementById('attFutureBody').classList.toggle('hidden'); var i=document.getElementById('attFutureIcon'); i.textContent=i.textContent==='▶'?'▼':'▶';"
             class="px-5 py-3 flex items-center justify-between cursor-pointer hover:bg-gray-50 transition">
            <div class="flex items-center gap-2">
                <span id="attFutureIcon" class="text-xs text-gray-400">▶</span>
                <span class="font-semibold text-gray-600">Kommende Termine</span>
                <span class="text-xs bg-gray-200 text-gray-500 px-2 py-0.5 rounded-full"><?= count($futureAtt) ?></span>
            </div>
        </div>
        <div id="attFutureBody" class="hidden">
            <?php foreach ($futureAtt as $s):
                $s['_ended'] = false;
                render_att_session($s, $allAttData[$s['id']], $activeMembers, $attRolesEnabled, $event['id'], ($s['id'] === $autoExpandId), false);
            endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
// ══════════════════════════════════════════════════════════════
// Tab: Strafen zuweisen
// ══════════════════════════════════════════════════════════════
elseif ($tab === 'penalties'):
?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="font-bold text-gray-800 mb-4">Strafe zuweisen</h3>
            <div>
                <div class="space-y-3">
                    <select id="penMember" required class="w-full border rounded-lg p-2 text-sm">
                        <option value="">– Teilnehmer –</option>
                        <?php foreach ($activeMembers as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= e($m['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="penType" required class="w-full border rounded-lg p-2 text-sm">
                        <option value="">– Straftyp –</option>
                        <?php foreach ($penaltyTypes as $pt): ?>
                        <?php if ($pt['active']): ?>
                        <option value="<?= $pt['id'] ?>"><?= e($pt['description']) ?> (<?= format_currency($pt['amount']) ?>)</option>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    <input type="date" id="penDate" value="<?= date('Y-m-d') ?>" class="w-full border rounded-lg p-2 text-sm">
                    <input type="text" id="penComment" placeholder="Kommentar (optional)" class="w-full border rounded-lg p-2 text-sm">
                    <button type="button" onclick="addPenalty()" class="w-full bg-red-600 text-white py-2 rounded-lg font-semibold hover:bg-red-700 transition">Strafe zuweisen</button>
                </div>
            </div>
        </div>
    </div>

    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="px-5 py-4 border-b flex justify-between items-center">
                <h3 class="font-bold text-gray-800">Zugewiesene Strafen</h3>
                <span class="text-red-600 font-bold"><?= format_currency($totalPenalty) ?></span>
            </div>
            <div class="divide-y max-h-96 overflow-y-auto">
                <?php if (empty($allPenalties)): ?>
                    <div class="px-5 py-8 text-center text-gray-400">Noch keine Strafen vergeben.</div>
                <?php endif; ?>
                <?php foreach ($allPenalties as $p): ?>
                <div class="px-5 py-3 flex items-center justify-between">
                    <div>
                        <span class="font-medium text-gray-800"><?= e($p['member_name']) ?></span>
                        <span class="text-sm text-gray-500 ml-2"><?= e($p['type_description']) ?></span>
                        <span class="text-red-600 font-semibold ml-2"><?= format_currency($p['amount']) ?></span>
                        <div class="text-xs text-gray-400"><?= format_date($p['penalty_date']) ?><?= $p['comment'] ? ' · ' . e($p['comment']) : '' ?></div>
                    </div>
                    <button onclick="deletePenalty(<?= $p['id'] ?>)" class="text-red-400 hover:text-red-600 text-xs">🗑️</button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php
// ══════════════════════════════════════════════════════════════
// Tab: Audit-Log
// ══════════════════════════════════════════════════════════════
elseif ($tab === 'audit'):
    $filterAction = $_GET['filter_action'] ?? '';
    $filterMember = isset($_GET['filter_member']) ? (int)$_GET['filter_member'] : 0;
    $logs = get_audit_log($event['id'], $filterAction ?: null, $filterMember ?: null, 200);
?>
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="px-5 py-4 border-b flex flex-wrap items-center justify-between gap-3">
        <h3 class="font-bold text-gray-800">Audit-Log</h3>
        <div class="flex gap-2 flex-wrap">
            <select onchange="filterAudit('filter_action', this.value)" class="border rounded-lg p-1.5 text-xs">
                <option value="">Alle Aktionen</option>
                <?php foreach (['excuse','withdraw_excuse','attendance','member_add','member_update','member_bulk','session_add','session_delete','penalty_add','penalty_delete','penalty_type_add','role_add','role_delete','role_assign','event_update','event_create','setup'] as $at): ?>
                <option value="<?= $at ?>" <?= $filterAction === $at ? 'selected' : '' ?>><?= $at ?></option>
                <?php endforeach; ?>
            </select>
            <select onchange="filterAudit('filter_member', this.value)" class="border rounded-lg p-1.5 text-xs">
                <option value="">Alle Teilnehmer</option>
                <?php foreach ($members as $m): ?>
                <option value="<?= $m['id'] ?>" <?= $filterMember == $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <a href="api.php?action=export_audit_csv&event_token=<?= e($event['public_token']) ?>&admin_token=<?= e($adminToken) ?>"
               class="bg-gray-100 hover:bg-gray-200 text-gray-600 px-3 py-1.5 rounded-lg text-xs font-medium transition">📥 CSV-Export</a>
        </div>
    </div>
    <div class="divide-y max-h-screen overflow-y-auto text-sm">
        <?php if (empty($logs)): ?>
            <div class="px-5 py-8 text-center text-gray-400">Keine Log-Einträge gefunden.</div>
        <?php endif; ?>
        <?php foreach ($logs as $log): ?>
        <div class="px-5 py-2.5">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <span class="inline-block bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded font-mono"><?= e($log['action_type']) ?></span>
                    <?php if ($log['member_name']): ?>
                        <span class="text-gray-500 text-xs ml-1"><?= e($log['member_name']) ?></span>
                    <?php endif; ?>
                    <div class="text-gray-700 mt-0.5"><?= e($log['action_description']) ?></div>
                </div>
                <div class="text-xs text-gray-400 whitespace-nowrap">
                    <?= format_datetime($log['created_at']) ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php
// ══════════════════════════════════════════════════════════════
// Tab: Einstellungen
// ══════════════════════════════════════════════════════════════
elseif ($tab === 'settings'):
    $serverAdminEmail = get_server_config('admin_email', '');
?>
<!-- Hinweis -->
<div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
    <p class="text-blue-700 text-sm">ℹ️ Neue Events/Jahrgänge können nur über den
    <?php if ($serverAdminEmail): ?>
        <a href="mailto:<?= e($serverAdminEmail) ?>" class="font-bold underline hover:text-blue-900">Server-Admin</a> (<?= e($serverAdminEmail) ?>)
    <?php else: ?>
        <strong>Server-Admin</strong>
    <?php endif; ?>
    erstellt werden.</p>
</div>

<!-- Einstellungen: 2-Spalten-Grid -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Karte: Grunddaten -->
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h4 class="font-bold text-gray-800 mb-4">📋 Event-Grunddaten</h4>
        <div class="space-y-3">
            <div>
                <label class="text-xs font-semibold text-gray-500">Name:</label>
                <input type="text" id="eventName" value="<?= e($event['name']) ?>" class="w-full border rounded-lg p-2 text-sm mt-1">
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-500">Status:</label>
                <select id="eventStatus" class="w-full border rounded-lg p-2 text-sm mt-1">
                    <option value="active" <?= $event['status'] === 'active' ? 'selected' : '' ?>>Aktiv</option>
                    <option value="archived" <?= $event['status'] === 'archived' ? 'selected' : '' ?>>Archiviert</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-500">Organisationsname (optional):</label>
                <input type="text" id="eventOrgName" value="<?= e($event['organization_name'] ?? '') ?>"
                       placeholder="Leer = globaler Standard (<?= e(get_server_config('organization_name', '')) ?>)"
                       class="w-full border rounded-lg p-2 text-sm mt-1">
                <p class="text-xs text-gray-400 mt-0.5">Überschreibt den globalen Organisationsnamen nur für dieses Event.</p>
            </div>
        </div>
    </div>

    <!-- Karte: Fristen -->
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h4 class="font-bold text-gray-800 mb-4">📅 Fristen</h4>
        <div class="space-y-3">
            <h5 class="font-semibold text-gray-600 text-sm">Hauptfrist (Abnahme)</h5>
            <div>
                <label class="text-xs text-gray-500">Anzeigename:</label>
                <input type="text" id="d2Name" value="<?= e($event['deadline_2_name'] ?? 'Frist 2') ?>" placeholder="z.B. Abnahme, Finale..." class="w-full border rounded-lg p-2 text-sm mt-1">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs text-gray-500">Datum:</label>
                    <input type="date" id="d2Date" value="<?= $event['deadline_2_date'] ?>" class="w-full border rounded-lg p-2 text-sm mt-1">
                </div>
                <div>
                    <label class="text-xs text-gray-500">Mindest-Teilnahmen:</label>
                    <input type="number" id="d2Count" value="<?= $event['deadline_2_count'] ?>" min="1" class="w-full border rounded-lg p-2 text-sm mt-1">
                </div>
            </div>
            <hr>
            <div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" id="d1Enabled" <?= ($event['deadline_1_enabled'] ?? 1) ? 'checked' : '' ?> class="rounded"
                           onchange="document.getElementById('d1Fields').classList.toggle('hidden', !this.checked)">
                    <span class="font-semibold text-gray-600 text-sm">Zwischenziel (Frist 1) aktivieren</span>
                </label>
            </div>
            <div id="d1Fields" class="<?= ($event['deadline_1_enabled'] ?? 1) ? '' : 'hidden' ?> space-y-3">
                <div>
                    <label class="text-xs text-gray-500">Anzeigename:</label>
                    <input type="text" id="d1Name" value="<?= e($event['deadline_1_name'] ?? 'Frist 1') ?>" placeholder="z.B. Zwischenziel, Halbzeit..." class="w-full border rounded-lg p-2 text-sm mt-1">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs text-gray-500">Datum:</label>
                        <input type="date" id="d1Date" value="<?= $event['deadline_1_date'] ?>" class="w-full border rounded-lg p-2 text-sm mt-1">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Mindest-Teilnahmen:</label>
                        <input type="number" id="d1Count" value="<?= $event['deadline_1_count'] ?>" min="1" class="w-full border rounded-lg p-2 text-sm mt-1">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Karte: Übungsdauer -->
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h4 class="font-bold text-gray-800 mb-4">⏱️ Übungsdauer</h4>
        <div>
            <label class="text-xs text-gray-500">Standard-Übungsdauer (Stunden):</label>
            <input type="number" id="sessionDuration" value="<?= (int)($event['session_duration_hours'] ?? 3) ?>" min="1" max="12" class="w-full border rounded-lg p-2 text-sm mt-1">
            <p class="text-xs text-gray-400 mt-1">Bestimmt, ab wann eine Übung als beendet gilt und der "Nächste Termin" wechselt.</p>
        </div>
    </div>

    <!-- Karte: Wetter-Standort -->
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h4 class="font-bold text-gray-800 mb-4">🌤️ Wetter-Standort</h4>
        <div>
            <label class="text-xs text-gray-500">Ort (für Wettervorhersage im Dashboard):</label>
            <div class="flex gap-2 mt-1">
                <input type="text" id="weatherQuery" placeholder="Ortsname oder PLZ eingeben..."
                       value="<?= e($event['weather_location'] ?? '') ?>"
                       class="flex-1 border rounded-lg p-2 text-sm">
                <button type="button" onclick="geocodeLocation()"
                        class="bg-blue-500 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-blue-600 transition shrink-0">
                    🔍
                </button>
            </div>
            <div id="geocodeResults" class="mt-2 hidden"></div>
            <input type="hidden" id="weatherLocation" value="<?= e($event['weather_location'] ?? '') ?>">
            <input type="hidden" id="weatherLat" value="<?= (float)($event['weather_lat'] ?? 0) ?>">
            <input type="hidden" id="weatherLng" value="<?= (float)($event['weather_lng'] ?? 0) ?>">
            <p class="text-xs text-gray-400 mt-1" id="weatherCurrentInfo">
                <?php if ($event['weather_location'] ?? ''): ?>
                    Aktuell: <?= e($event['weather_location']) ?>
                    (<?= number_format((float)($event['weather_lat'] ?? 0), 4) ?>°N,
                     <?= number_format((float)($event['weather_lng'] ?? 0), 4) ?>°E)
                <?php else: ?>
                    Noch kein Standort konfiguriert.
                <?php endif; ?>
            </p>
        </div>
    </div>
</div>

<!-- Speichern-Button (volle Breite) -->
<div class="mb-6">
    <button type="button" onclick="updateEvent()" class="w-full bg-red-600 text-white py-3 rounded-xl font-bold hover:bg-red-700 transition text-sm">
        💾 Event-Einstellungen speichern
    </button>
</div>

<!-- Strafenkatalog (volle Breite) -->
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="px-5 py-4 border-b" style="background-color: #e5e7eb;">
        <h3 class="font-bold text-gray-700">📋 Strafenkatalog</h3>
    </div>
    <div class="divide-y">
        <?php if (empty($penaltyTypes)): ?>
            <div class="px-5 py-8 text-center text-gray-400">Keine Straftypen angelegt.</div>
        <?php endif; ?>
        <?php foreach ($penaltyTypes as $pt): ?>
        <div class="px-5 py-3" id="pt-row-<?= $pt['id'] ?>">
            <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="flex-1 min-w-0" id="pt-display-<?= $pt['id'] ?>">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="inline-flex items-center justify-center bg-gray-100 text-gray-500 text-xs font-mono rounded w-8 h-6" title="Sortierung"><?= (int)$pt['sort_order'] ?></span>
                        <span class="font-medium <?= $pt['active'] ? 'text-gray-800' : 'text-gray-400' ?>"><?= e($pt['description']) ?></span>
                        <span class="text-red-600 font-semibold"><?= format_currency($pt['amount']) ?></span>
                        <?php if ($pt['active_from']): ?>
                            <span class="text-xs text-gray-400">(ab <?= format_date($pt['active_from']) ?>)</span>
                        <?php endif; ?>
                        <?php if (!$pt['active']): ?>
                            <span class="text-xs bg-gray-200 text-gray-500 px-2 py-0.5 rounded-full">Inaktiv</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex gap-2 shrink-0" id="pt-buttons-<?= $pt['id'] ?>">
                    <button onclick="editPenaltyType(<?= $pt['id'] ?>)" class="text-gray-400 hover:text-blue-600 text-xs transition">✏️ Bearbeiten</button>
                    <button onclick="deletePenaltyType(<?= $pt['id'] ?>)" class="text-gray-400 hover:text-red-600 text-xs transition">🗑️</button>
                </div>
            </div>
            <div class="hidden mt-3 bg-gray-50 rounded-lg p-3" id="pt-edit-<?= $pt['id'] ?>">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-2">
                    <div>
                        <label class="text-xs text-gray-500">Sortierung:</label>
                        <input type="number" id="pt-sort-<?= $pt['id'] ?>" value="<?= (int)$pt['sort_order'] ?>" class="w-full border rounded-lg p-1.5 text-sm mt-0.5">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Betrag (€):</label>
                        <input type="number" id="pt-amount-<?= $pt['id'] ?>" value="<?= $pt['amount'] ?>" step="0.50" min="0.50" class="w-full border rounded-lg p-1.5 text-sm mt-0.5">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Aktiv ab:</label>
                        <input type="date" id="pt-from-<?= $pt['id'] ?>" value="<?= $pt['active_from'] ?? '' ?>" class="w-full border rounded-lg p-1.5 text-sm mt-0.5">
                    </div>
                    <div class="flex items-end">
                        <label class="flex items-center gap-1.5 text-sm cursor-pointer">
                            <input type="checkbox" id="pt-active-<?= $pt['id'] ?>" <?= $pt['active'] ? 'checked' : '' ?> class="rounded"> Aktiv
                        </label>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="text-xs text-gray-500">Beschreibung:</label>
                    <input type="text" id="pt-desc-<?= $pt['id'] ?>" value="<?= e($pt['description']) ?>" class="w-full border rounded-lg p-1.5 text-sm mt-0.5">
                </div>
                <div class="flex gap-2">
                    <button onclick="savePenaltyType(<?= $pt['id'] ?>)" class="bg-red-600 text-white px-4 py-1.5 rounded-lg text-xs font-semibold hover:bg-red-700 transition">💾 Speichern</button>
                    <button onclick="cancelEditPenaltyType(<?= $pt['id'] ?>)" class="bg-gray-200 text-gray-600 px-4 py-1.5 rounded-lg text-xs font-semibold hover:bg-gray-300 transition">Abbrechen</button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <!-- Straftyp hinzufügen -->
    <div class="px-5 py-4 border-t bg-gray-50">
        <div class="grid grid-cols-1 sm:grid-cols-5 gap-2 items-end">
            <div class="sm:col-span-2">
                <label class="text-xs text-gray-500">Beschreibung:</label>
                <input type="text" id="ptDescription" placeholder="Beschreibung" class="w-full border rounded-lg p-2 text-sm mt-0.5">
            </div>
            <div>
                <label class="text-xs text-gray-500">Betrag (€):</label>
                <input type="number" id="ptAmount" placeholder="5.00" step="0.50" min="0.50" class="w-full border rounded-lg p-2 text-sm mt-0.5">
            </div>
            <div>
                <label class="text-xs text-gray-500">Sort:</label>
                <input type="number" id="ptSortOrder" value="0" class="w-full border rounded-lg p-2 text-sm mt-0.5">
            </div>
            <div>
                <button type="button" onclick="addPenaltyType()" class="w-full bg-red-600 text-white py-2 rounded-lg text-sm font-semibold hover:bg-red-700 transition">+ Hinzufügen</button>
            </div>
        </div>
    </div>
</div>

<?php
// ══════════════════════════════════════════════════════════════
// Tab: Rollen
// ══════════════════════════════════════════════════════════════
elseif ($tab === 'roles'):
    $rolesEnabled = (bool)($event['roles_enabled'] ?? false);
    $roles = get_roles($event['id']);
?>

<!-- Rollen aktivieren -->
<div class="bg-white rounded-xl shadow-sm border p-5 mb-6">
    <label class="flex items-center gap-3 cursor-pointer">
        <input type="checkbox" id="rolesToggle" <?= $rolesEnabled ? 'checked' : '' ?> class="rounded w-5 h-5"
               onchange="try{toggleRoles(this.checked);}catch(e){alert(e.message);}">
        <div>
            <span class="font-bold text-gray-800">Rollen aktivieren</span>
            <p class="text-xs text-gray-400">Wenn aktiviert, können Teilnehmern Rollen zugewiesen werden. Die Rollenverfügbarkeit wird im Dashboard und in der Anwesenheitsliste angezeigt.</p>
        </div>
    </label>
</div>

<div id="rolesContent" class="<?= $rolesEnabled ? '' : 'hidden' ?>">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Rollenkatalog -->
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="px-5 py-4 border-b" style="background-color: #e5e7eb;">
                <h3 class="font-bold text-gray-700">🏷️ Rollenkatalog</h3>
            </div>

            <?php if (empty($roles)): ?>
                <div class="p-5 text-center text-gray-400 text-sm">Noch keine Rollen definiert.</div>
            <?php else: ?>
                <div class="divide-y">
                    <?php foreach ($roles as $role):
                        $memberCount = count(get_pdo()->query("SELECT mr.member_id FROM member_roles mr JOIN members m ON mr.member_id = m.id WHERE mr.role_id = {$role['id']} AND m.active = 1")->fetchAll());
                    ?>
                    <div class="px-5 py-3 flex items-center justify-between">
                        <div>
                            <span class="font-medium text-gray-800"><?= e($role['name']) ?></span>
                            <span class="text-xs text-gray-400 ml-2"><?= $memberCount ?> Teilnehmer</span>
                        </div>
                        <button type="button" onclick="try{deleteRole(<?= $role['id'] ?>, '<?= e(addslashes($role['name'])) ?>');}catch(e){alert(e.message);}"
                                class="text-red-400 hover:text-red-600 text-xs px-2 py-1 rounded hover:bg-red-50 transition">🗑️</button>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="px-5 py-4 border-t bg-gray-50">
                <div class="flex gap-2">
                    <input type="text" id="newRoleName" placeholder="Rollenname (z.B. Gruppenführer)"
                           class="flex-1 border rounded-lg p-2 text-sm">
                    <input type="number" id="newRoleSort" placeholder="Sort" value="<?= (count($roles) + 1) * 10 ?>"
                           class="w-16 border rounded-lg p-2 text-sm text-center">
                    <button type="button" onclick="try{addRole();}catch(e){alert(e.message);}"
                            class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-red-700 transition">+</button>
                </div>
            </div>
        </div>

        <!-- Rollen zuweisen -->
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="px-5 py-4 border-b" style="background-color: #e5e7eb;">
                <h3 class="font-bold text-gray-700">👥 Rollen zuweisen</h3>
            </div>

            <?php if (empty($roles)): ?>
                <div class="p-5 text-center text-gray-400 text-sm">Erstelle zuerst Rollen im Katalog.</div>
            <?php else: ?>
                <div class="divide-y max-h-96 overflow-y-auto">
                    <?php foreach ($activeMembers as $m):
                        $mRoleIds = get_member_role_ids($m['id']);
                    ?>
                    <div class="px-5 py-3">
                        <div class="font-medium text-gray-800 text-sm mb-2"><?= e($m['name']) ?></div>
                        <div class="flex flex-wrap gap-1">
                            <?php foreach ($roles as $role):
                                $hasRole = in_array($role['id'], $mRoleIds);
                            ?>
                            <button type="button"
                                    onclick="try{toggleMemberRole(<?= $m['id'] ?>, <?= $role['id'] ?>, this);}catch(e){alert(e.message);}"
                                    class="px-2 py-1 rounded text-xs font-medium border transition
                                    <?= $hasRole ? 'bg-gray-700 text-white border-gray-700' : 'bg-white text-gray-500 border-gray-200 hover:bg-gray-50' ?>"
                                    data-active="<?= $hasRole ? '1' : '0' ?>">
                                <?= e($role['name']) ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php endif; ?>
<script>
const ADMIN_TOKEN = '<?= e($adminToken) ?>';

async function adminApi(action, data = {}) {
    data.admin_token = ADMIN_TOKEN;
    return apiCall(action, data, ADMIN_TOKEN);
}

// ── Teilnehmer ──────────────────────────────────────────────
async function addMember() {
    const r = await adminApi('add_member', {
        name: document.getElementById('memberName').value,
        role: document.getElementById('memberRole').value
    });
    if (r.success) setTimeout(() => location.reload(), 800);
}

async function bulkImportMembers() {
    const r = await adminApi('bulk_import_members', {
        names: document.getElementById('bulkNames').value
    });
    if (r.success) setTimeout(() => location.reload(), 800);
}

function editMember(id, name, role, active) {
    document.getElementById('editMemberId').value = id;
    document.getElementById('editMemberName').value = name;
    document.getElementById('editMemberRole').value = role;
    document.getElementById('editMemberActive').checked = active;
    document.getElementById('editMemberModal').classList.remove('hidden');
}

async function saveMember() {
    const r = await adminApi('update_member', {
        member_id: document.getElementById('editMemberId').value,
        name: document.getElementById('editMemberName').value,
        role: document.getElementById('editMemberRole').value,
        active: document.getElementById('editMemberActive').checked ? 1 : 0,
    });
    if (r.success) setTimeout(() => location.reload(), 800);
}

// ── Termine ─────────────────────────────────────────────────
async function addSession() {
    const r = await adminApi('add_session', {
        date: document.getElementById('sessionDate').value,
        time: document.getElementById('sessionTime').value,
        comment: document.getElementById('sessionComment').value,
    });
    if (r.success) setTimeout(() => location.reload(), 800);
}

async function bulkImportSessions() {
    const r = await adminApi('bulk_import_sessions', {
        sessions_data: document.getElementById('bulkSessions').value
    });
    if (r.success) setTimeout(() => location.reload(), 800);
}

async function deleteSession(id) {
    if (!confirm('Termin wirklich löschen? Alle Anwesenheitsdaten gehen verloren!')) return;
    const r = await adminApi('delete_session', { session_id: id });
    if (r.success) setTimeout(() => location.reload(), 800);
}

// ── Anwesenheit ─────────────────────────────────────────────
// ── Anwesenheit (aufklappbare Terminliste) ──────────────────
function toggleAttendance(sessionId) {
    const panel = document.getElementById('att-panel-' + sessionId);
    const icon = document.getElementById('expand-icon-' + sessionId);
    if (!panel) return;
    const isHidden = panel.classList.contains('hidden');
    panel.classList.toggle('hidden');
    if (icon) icon.textContent = isHidden ? '▼' : '▶';
}

const attColors = {
    present: { bg: 'bg-green-600', text: 'text-white', border: 'border-green-600' },
    excused: { bg: 'bg-yellow-500', text: 'text-white', border: 'border-yellow-500' },
    absent:  { bg: 'bg-red-600',    text: 'text-white', border: 'border-red-600' },
};
const attDefault = { bg: 'bg-white', text: 'text-gray-500', border: 'border-gray-200' };

function updateAttButtons(sessionId, memberId, newStatus) {
    const group = document.querySelector('.att-group[data-session="' + sessionId + '"][data-member="' + memberId + '"]');
    if (!group) return;
    group.querySelectorAll('.att-btn').forEach(btn => {
        const btnStatus = btn.getAttribute('data-status');
        const isActive = (newStatus !== '' && btnStatus === newStatus);
        const colors = isActive ? attColors[btnStatus] : attDefault;
        Object.values(attColors).forEach(c => btn.classList.remove(c.bg, c.text, c.border));
        btn.classList.remove(attDefault.bg, attDefault.text, attDefault.border);
        btn.classList.add(colors.bg, colors.text, colors.border);
    });
}

function showAttSaved(sessionId, memberId) {
    const indicator = document.getElementById('att-saved-' + sessionId + '-' + memberId);
    if (!indicator) return;
    indicator.classList.remove('hidden');
    setTimeout(() => indicator.classList.add('hidden'), 2000);
}

async function setAttFor(sessionId, memberId, status) {
    const input = document.getElementById('att-val-' + sessionId + '-' + memberId);
    if (!input) return;

    // Toggle: erneuter Klick auf aktiven Status → zurücksetzen
    const newStatus = (input.value === status) ? '' : status;
    input.value = newStatus;

    // Sofort visuell aktualisieren
    updateAttButtons(sessionId, memberId, newStatus);

    // Sofort einzeln speichern
    const data = { session_id: sessionId };
    data['attendance[' + memberId + ']'] = newStatus;
    const result = await adminApi('save_attendance', data);
    if (result.success) {
        showAttSaved(sessionId, memberId);
    }
}

async function setAllAttendanceFor(sessionId, status) {
    const groups = document.querySelectorAll('.att-group[data-session="' + sessionId + '"]');
    // Zuerst alle visuell aktualisieren
    groups.forEach(group => {
        const memberId = group.getAttribute('data-member');
        const input = document.getElementById('att-val-' + sessionId + '-' + memberId);
        if (input) input.value = status;
        updateAttButtons(sessionId, parseInt(memberId), status);
    });

    // Dann alle auf einmal speichern (ein API-Call für Effizienz)
    const data = { session_id: sessionId };
    groups.forEach(group => {
        const memberId = group.getAttribute('data-member');
        data['attendance[' + memberId + ']'] = status;
    });
    const result = await adminApi('save_attendance', data);
    if (result.success) {
        groups.forEach(group => {
            const memberId = group.getAttribute('data-member');
            showAttSaved(sessionId, parseInt(memberId));
        });
    }
}

// ── Straftypen ──────────────────────────────────────────────
async function addPenaltyType() {
    const r = await adminApi('add_penalty_type', {
        description: document.getElementById('ptDescription').value,
        amount: document.getElementById('ptAmount').value,
        active_from: document.getElementById('ptActiveFrom').value,
        sort_order: document.getElementById('ptSortOrder').value,
    });
    if (r.success) setTimeout(() => location.reload(), 800);
}

async function deletePenaltyType(id) {
    if (!confirm('Straftyp wirklich löschen?')) return;
    const r = await adminApi('delete_penalty_type', { penalty_type_id: id });
    if (r.success) setTimeout(() => location.reload(), 800);
}

function editPenaltyType(id) {
    // Close any other open editors
    document.querySelectorAll('[id^="pt-edit-"]').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('[id^="pt-buttons-"]').forEach(el => el.classList.remove('hidden'));
    // Open this one
    document.getElementById('pt-edit-' + id).classList.remove('hidden');
    document.getElementById('pt-buttons-' + id).classList.add('hidden');
}

function cancelEditPenaltyType(id) {
    document.getElementById('pt-edit-' + id).classList.add('hidden');
    document.getElementById('pt-buttons-' + id).classList.remove('hidden');
}

async function savePenaltyType(id) {
    const r = await adminApi('update_penalty_type', {
        penalty_type_id: id,
        description: document.getElementById('pt-desc-' + id).value,
        amount: document.getElementById('pt-amount-' + id).value,
        active_from: document.getElementById('pt-from-' + id).value,
        active: document.getElementById('pt-active-' + id).checked ? 1 : 0,
        sort_order: document.getElementById('pt-sort-' + id).value,
    });
    if (r.success) setTimeout(() => location.reload(), 800);
}

// ── Strafen ─────────────────────────────────────────────────
async function addPenalty() {
    const r = await adminApi('add_penalty', {
        member_id: document.getElementById('penMember').value,
        penalty_type_id: document.getElementById('penType').value,
        penalty_date: document.getElementById('penDate').value,
        comment: document.getElementById('penComment').value,
    });
    if (r.success) setTimeout(() => location.reload(), 800);
}

async function deletePenalty(id) {
    if (!confirm('Strafe wirklich rückgängig machen?')) return;
    const r = await adminApi('delete_penalty', { penalty_id: id });
    if (r.success) setTimeout(() => location.reload(), 800);
}

// ── Event ───────────────────────────────────────────────────
async function updateEvent() {
    await adminApi('update_event', {
        name: document.getElementById('eventName').value,
        status: document.getElementById('eventStatus').value,
        organization_name: document.getElementById('eventOrgName').value,
        deadline_1_date: document.getElementById('d1Date').value,
        deadline_1_count: document.getElementById('d1Count').value,
        deadline_1_name: document.getElementById('d1Name').value,
        deadline_1_enabled: document.getElementById('d1Enabled').checked ? '1' : '0',
        deadline_2_date: document.getElementById('d2Date').value,
        deadline_2_count: document.getElementById('d2Count').value,
        deadline_2_name: document.getElementById('d2Name').value,
        session_duration_hours: document.getElementById('sessionDuration').value,
        weather_location: document.getElementById('weatherLocation').value,
        weather_lat: document.getElementById('weatherLat').value,
        weather_lng: document.getElementById('weatherLng').value,
    });
}

// ── Geocoding (Ortssuche) ───────────────────────────────────
async function geocodeLocation() {
    const query = document.getElementById('weatherQuery').value.trim();
    if (!query) { showToast('Bitte einen Ortsnamen oder PLZ eingeben.', 'warning'); return; }

    const result = await adminApi('geocode', { query: query });
    const container = document.getElementById('geocodeResults');

    if (!result.success || !result.results) {
        container.innerHTML = '<p class="text-red-500 text-xs">Kein Ort gefunden.</p>';
        container.classList.remove('hidden');
        return;
    }

    let html = '<div class="space-y-1">';
    result.results.forEach((r, i) => {
        const label = r.name + (r.admin1 ? ', ' + r.admin1 : '') + (r.country ? ' (' + r.country + ')' : '');
        html += `<button type="button" onclick="selectWeatherLocation('${r.name.replace(/'/g, "\\'")}', ${r.lat}, ${r.lng})"
                    class="w-full text-left px-3 py-2 rounded-lg text-sm border hover:bg-blue-50 hover:border-blue-300 transition">
                    📍 ${label} <span class="text-gray-400 text-xs">(${r.lat.toFixed(3)}°, ${r.lng.toFixed(3)}°)</span>
                 </button>`;
    });
    html += '</div>';
    container.innerHTML = html;
    container.classList.remove('hidden');
}

function selectWeatherLocation(name, lat, lng) {
    document.getElementById('weatherLocation').value = name;
    document.getElementById('weatherLat').value = lat;
    document.getElementById('weatherLng').value = lng;
    document.getElementById('weatherQuery').value = name;
    document.getElementById('geocodeResults').classList.add('hidden');
    document.getElementById('weatherCurrentInfo').innerHTML =
        'Ausgewählt: <strong>' + name + '</strong> (' + lat.toFixed(4) + '°N, ' + lng.toFixed(4) + '°E) — zum Übernehmen "Speichern" klicken';
    document.getElementById('weatherCurrentInfo').classList.add('text-blue-600');
    showToast('Standort "' + name + '" ausgewählt. Bitte "Speichern" klicken.', 'info');
}

// ── Rollen ──────────────────────────────────────────────────
async function toggleRoles(enabled) {
    var r = await adminApi('toggle_roles', { enabled: enabled ? '1' : '0' });
    if (r.success) {
        var content = document.getElementById('rolesContent');
        if (content) content.classList.toggle('hidden', !enabled);
    }
}

async function addRole() {
    var name = document.getElementById('newRoleName').value.trim();
    var sort = document.getElementById('newRoleSort').value;
    if (!name) { showToast('Bitte Rollenname eingeben.', 'warning'); return; }
    var r = await adminApi('add_role', { name: name, sort_order: sort });
    if (r.success) setTimeout(function() { location.reload(); }, 800);
}

async function deleteRole(id, name) {
    if (!confirm('Rolle "' + name + '" löschen? Alle Zuweisungen werden entfernt.')) return;
    var r = await adminApi('delete_role', { role_id: id });
    if (r.success) setTimeout(function() { location.reload(); }, 800);
}

async function toggleMemberRole(memberId, roleId, btn) {
    var isActive = btn.getAttribute('data-active') === '1';
    // Alle aktuellen Rollen des Mitglieds sammeln
    var container = btn.parentElement;
    var buttons = container.querySelectorAll('button[data-active]');
    var roleIds = [];
    for (var i = 0; i < buttons.length; i++) {
        var b = buttons[i];
        var bRoleId = parseInt(b.getAttribute('onclick').match(/toggleMemberRole\(\d+,\s*(\d+)/)[1]);
        var bActive = b.getAttribute('data-active') === '1';
        if (bRoleId === roleId) {
            // Toggle this one
            if (!isActive) roleIds.push(bRoleId);
        } else if (bActive) {
            roleIds.push(bRoleId);
        }
    }

    var formData = { member_id: memberId };
    for (var j = 0; j < roleIds.length; j++) {
        formData['role_ids[' + j + ']'] = roleIds[j];
    }
    if (roleIds.length === 0) {
        formData['role_ids'] = '';
    }

    var r = await adminApi('set_member_roles', formData);
    if (r.success) {
        // UI toggle
        if (isActive) {
            btn.setAttribute('data-active', '0');
            btn.className = 'px-2 py-1 rounded text-xs font-medium border transition bg-white text-gray-500 border-gray-200 hover:bg-gray-50';
        } else {
            btn.setAttribute('data-active', '1');
            btn.className = 'px-2 py-1 rounded text-xs font-medium border transition bg-gray-700 text-white border-gray-700';
        }
    }
}

// ── Audit-Filter ────────────────────────────────────────────
function filterAudit(param, value) {
    const url = new URL(location.href);
    if (value) url.searchParams.set(param, value);
    else url.searchParams.delete(param);
    location.href = url.toString();
}

// ── Archiviertes Event reaktivieren ─────────────────────────
async function reactivateEvent() {
    if (!confirm('Event wieder aktivieren? Alle Funktionen werden wieder freigeschaltet.')) return;
    await adminApi('update_event', { name: '<?= e(addslashes($event['name'])) ?>', status: 'active' });
    setTimeout(function() { location.reload(); }, 800);
}

<?php if ($isArchived): ?>
// ── Read-Only-Modus für archivierte Events ──────────────────
(function() {
    // Alle onclick-Buttons deaktivieren (außer Tabs, Reaktivierung, Audit-Filter)
    var buttons = document.querySelectorAll('button[onclick], button[type="button"]');
    for (var i = 0; i < buttons.length; i++) {
        var btn = buttons[i];
        var oc = btn.getAttribute('onclick') || '';
        // Reaktivierung und Audit-Filter erlauben
        if (oc.indexOf('reactivateEvent') !== -1) continue;
        if (oc.indexOf('filterAudit') !== -1) continue;
        btn.disabled = true;
        btn.style.opacity = '0.4';
        btn.style.cursor = 'not-allowed';
        btn.onclick = null;
        btn.setAttribute('onclick', '');
    }
    // Alle Eingabefelder deaktivieren
    var inputs = document.querySelectorAll('main input, main select, main textarea');
    for (var j = 0; j < inputs.length; j++) {
        inputs[j].disabled = true;
        inputs[j].style.opacity = '0.6';
    }
})();
<?php endif; ?>
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
