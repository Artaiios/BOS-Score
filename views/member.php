<?php
/**
 * Teilnehmer-Detailseite
 */

$sessions = get_sessions($event['id']);
$attendance = get_attendance_for_member($member['id']);
$penalties = get_penalties_for_member($member['id']);
$penaltyTotal = get_member_penalty_total($member['id']);
$sessionDuration = (int)($event['session_duration_hours'] ?? 3);
$d1Enabled = (bool)($event['deadline_1_enabled'] ?? true);

$totalSessions = count($sessions);
$pastSessions = array_filter($sessions, fn($s) => $s['session_date'] <= date('Y-m-d'));
$totalPast = count($pastSessions);

// Attendance als Lookup
$attLookup = [];
foreach ($attendance as $a) {
    $attLookup[$a['session_id']] = $a;
}

$present = count(array_filter($attendance, fn($a) => $a['status'] === 'present'));
$excused = count(array_filter($attendance, fn($a) => $a['status'] === 'excused'));
$absent = count(array_filter($attendance, fn($a) => $a['status'] === 'absent'));
$pending = $totalSessions - count($attendance);

// Verbleibende Termine
$remainingD1 = count(array_filter($sessions, fn($s) => $s['session_date'] > date('Y-m-d') && $s['session_date'] <= $event['deadline_1_date']));
$remainingD2 = count(array_filter($sessions, fn($s) => $s['session_date'] > date('Y-m-d') && $s['session_date'] <= $event['deadline_2_date']));

if ($d1Enabled) {
    $d1 = calculate_deadline_status($present, $event['deadline_1_count'], $event['deadline_1_date'], $totalSessions, $totalPast, $remainingD1);
    $progressD1 = $event['deadline_1_count'] > 0 ? min(100, round(($present / $event['deadline_1_count']) * 100)) : 0;
}
$d2 = calculate_deadline_status($present, $event['deadline_2_count'], $event['deadline_2_date'], $totalSessions, $totalPast, $remainingD2);
$progressD2 = $event['deadline_2_count'] > 0 ? min(100, round(($present / $event['deadline_2_count']) * 100)) : 0;

$pageTitle = $member['name'] . ' – ' . $event['name'];
require __DIR__ . '/partials/header.php';
?>

<?php if ($isArchived): ?>
<div class="bg-yellow-50 border border-yellow-300 rounded-xl p-4 mb-6">
    <p class="text-yellow-800 text-sm font-semibold">📦 Dieses Event ist archiviert und wird nur noch im Lesemodus angezeigt.</p>
</div>
<?php endif; ?>

<!-- Breadcrumb -->
<nav class="mb-6 text-sm">
    <a href="index.php?event=<?= e($event['public_token']) ?>" class="text-red-600 hover:underline">← Zurück zur Übersicht</a>
</nav>

<!-- Kopfbereich -->
<div class="mb-8">
    <h1 class="text-2xl md:text-3xl font-extrabold text-gray-900"><?= e($member['name']) ?></h1>
    <?php if ($member['role']): ?>
        <p class="text-gray-500 mt-1"><?= e($member['role']) ?></p>
    <?php endif; ?>
    <?php
    $memberRolesEnabled = (bool)($event['roles_enabled'] ?? false);
    if ($memberRolesEnabled):
        $myRoles = get_member_roles($member['id']);
        if (!empty($myRoles)):
    ?>
    <div class="flex flex-wrap gap-1 mt-2">
        <?php foreach ($myRoles as $mr): ?>
        <span class="text-xs bg-gray-200 text-gray-700 px-2 py-1 rounded font-medium"><?= e($mr['name']) ?></span>
        <?php endforeach; ?>
    </div>
    <?php endif; endif; ?>
</div>

<!-- Fortschrittsbalken -->
<div class="grid grid-cols-1 <?= $d1Enabled ? 'md:grid-cols-2' : '' ?> gap-4 mb-8">
    <?php if ($d1Enabled): ?>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <div class="flex justify-between items-center mb-2">
            <h3 class="font-semibold text-gray-700"><?= e($event['deadline_1_name'] ?? 'Frist 1') ?>: <?= format_date($event['deadline_1_date']) ?></h3>
            <span class="<?= $d1['class'] ?> px-2 py-1 rounded-lg text-xs font-semibold"><?= $d1['icon'] ?> <?= $present ?>/<?= $event['deadline_1_count'] ?></span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
            <div class="h-full rounded-full transition-all <?= $progressD1 >= 100 ? 'bg-green-500' : ($progressD1 >= 70 ? 'bg-yellow-500' : 'bg-red-500') ?>"
                 style="width: <?= $progressD1 ?>%"></div>
        </div>
        <p class="text-xs text-gray-400 mt-1">Noch <?= max(0, $event['deadline_1_count'] - $present) ?> Teilnahmen benötigt · <?= $remainingD1 ?> Termine übrig</p>
    </div>
    <?php endif; ?>
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <div class="flex justify-between items-center mb-2">
            <h3 class="font-semibold text-gray-700"><?= e($event['deadline_2_name'] ?? 'Frist 2') ?>: <?= format_date($event['deadline_2_date']) ?></h3>
            <span class="<?= $d2['class'] ?> px-2 py-1 rounded-lg text-xs font-semibold"><?= $d2['icon'] ?> <?= $present ?>/<?= $event['deadline_2_count'] ?></span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
            <div class="h-full rounded-full transition-all <?= $progressD2 >= 100 ? 'bg-green-500' : ($progressD2 >= 70 ? 'bg-yellow-500' : 'bg-red-500') ?>"
                 style="width: <?= $progressD2 ?>%"></div>
        </div>
        <p class="text-xs text-gray-400 mt-1">Noch <?= max(0, $event['deadline_2_count'] - $present) ?> Teilnahmen benötigt · <?= $remainingD2 ?> Termine übrig</p>
    </div>
</div>

<!-- Donut-Diagramm & Statistik -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-sm border p-5 md:col-span-1">
        <h3 class="font-bold text-gray-800 mb-3">Übersicht</h3>
        <div style="max-width: 220px; margin: 0 auto;">
            <canvas id="chartDonut"></canvas>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-5 md:col-span-2">
        <h3 class="font-bold text-gray-800 mb-4">Statistik</h3>
        <div class="grid grid-cols-2 gap-4">
            <div class="bg-green-50 rounded-lg p-3 text-center">
                <div class="text-2xl font-bold text-green-600"><?= $present ?></div>
                <div class="text-xs text-green-500">Anwesend</div>
            </div>
            <div class="bg-yellow-50 rounded-lg p-3 text-center">
                <div class="text-2xl font-bold text-yellow-600"><?= $excused ?></div>
                <div class="text-xs text-yellow-500">Entschuldigt</div>
            </div>
            <div class="bg-red-50 rounded-lg p-3 text-center">
                <div class="text-2xl font-bold text-red-600"><?= $absent ?></div>
                <div class="text-xs text-red-500">Unentschuldigt</div>
            </div>
            <div class="bg-gray-50 rounded-lg p-3 text-center">
                <div class="text-2xl font-bold text-gray-600"><?= $pending ?></div>
                <div class="text-xs text-gray-400">Ausstehend</div>
            </div>
        </div>
        <?php if ($penaltyTotal > 0): ?>
        <div class="mt-4 bg-red-50 rounded-lg p-3 text-center">
            <div class="text-2xl font-bold text-red-600"><?= format_currency($penaltyTotal) ?></div>
            <div class="text-xs text-red-500">Strafkasse</div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Terminliste -->
<div style="background: white; border-radius: 12px; border: 1px solid #e5e7eb; margin-bottom: 2rem; overflow: hidden;">
    <div style="padding: 16px 20px; background: linear-gradient(to right, #dc2626, #b91c1c); border-bottom: 1px solid #e5e7eb;">
        <h2 style="font-weight: bold; color: white; margin: 0;">📅 Meine Termine</h2>
    </div>
    <?php
    $nextSessionFound = false;
    foreach ($sessions as $s):
        $sessionStart = new DateTime($s['session_date'] . ' ' . $s['session_time']);
        $sessionEnded = is_session_ended($s, $sessionDuration);
        $sessionFuture = is_session_in_future($s);
        $isPast = $sessionEnded;
        $isToday = $s['session_date'] === date('Y-m-d');
        $att = $attLookup[$s['id']] ?? null;
        $status = $att['status'] ?? null;

        // Nächster Termin = erster noch nicht beendeter Termin
        $isNext = false;
        if (!$nextSessionFound && !$sessionEnded) {
            $isNext = true;
            $nextSessionFound = true;
        }

        if ($sessionEnded && !$status) $status = 'absent';

        $statusConfig = [
            'present' => ['icon' => '✅', 'text' => 'Anwesend', 'class' => 'text-green-600 bg-green-50'],
            'excused' => ['icon' => '🟡', 'text' => 'Entschuldigt', 'class' => 'text-yellow-600 bg-yellow-50'],
            'absent'  => ['icon' => '❌', 'text' => 'Fehlend', 'class' => 'text-red-600 bg-red-50'],
        ];
        $statusInfo = $status ? ($statusConfig[$status] ?? null) : null;

        // Kann der Teilnehmer seinen Entschuldigungsstatus ändern?
        $canChange = !$isArchived && can_member_change_excuse($s, $att);
        $isSelfExcused = ($status === 'excused' && $att && $att['excused_by'] === 'member');

        // Zeilen-Styling
        if ($isNext) {
            $rowBg = '#fed7aa';
            $rowBorder = 'border-left: 5px solid #ea580c;';
            $textColor = '#111827';
            $textWeight = 'font-weight: 700;';
        } elseif ($isToday && !$sessionEnded) {
            $rowBg = '#fee2e2';
            $rowBorder = '';
            $textColor = '#111827';
            $textWeight = 'font-weight: 600;';
        } elseif ($isPast) {
            $rowBg = '#f3f4f6';
            $rowBorder = '';
            $textColor = '#9ca3af';
            $textWeight = '';
        } else {
            $rowBg = '#ffffff';
            $rowBorder = '';
            $textColor = '#1f2937';
            $textWeight = '';
        }
    ?>
    <div style="padding: 16px 20px; background-color: <?= $rowBg ?>; <?= $rowBorder ?> <?= $textWeight ?> border-bottom: 1px solid #e5e7eb;" id="session-<?= $s['id'] ?>">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <div style="color: <?= $textColor ?>; <?= $textWeight ?>">
                    <?= format_weekday($s['session_date']) ?>, <?= format_date($s['session_date']) ?> – <?= format_time($s['session_time']) ?> Uhr
                    <?php if ($isToday && !$sessionEnded): ?>
                        <span style="font-size: 11px; background-color: #dc2626; color: white; padding: 2px 8px; border-radius: 9999px; margin-left: 4px; font-weight: 600;">HEUTE</span>
                    <?php endif; ?>
                    <?php if ($isNext && !$isToday): ?>
                        <span style="font-size: 11px; background-color: #ea580c; color: white; padding: 2px 8px; border-radius: 9999px; margin-left: 4px; font-weight: 600;">NÄCHSTER</span>
                    <?php endif; ?>
                </div>
                <?php if ($s['comment']): ?>
                    <div style="font-size: 14px; color: <?= $isPast ? '#d1d5db' : '#6b7280' ?>;"><?= e($s['comment']) ?></div>
                <?php endif; ?>
                <?php if ($att && $att['excused_at'] && $status === 'excused'): ?>
                    <div style="font-size: 12px; color: <?= $isPast ? '#d1d5db' : '#9ca3af' ?>; margin-top: 4px;">
                        Entschuldigt <?= format_datetime($att['excused_at']) ?>
                        <?php if ($att['excused_by'] === 'member'): ?>(selbst)<?php else: ?>(Admin)<?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <?php if ($statusInfo): ?>
                    <span class="<?= $statusInfo['class'] ?> px-3 py-1 rounded-lg text-xs font-semibold">
                        <?= $statusInfo['icon'] ?> <?= $statusInfo['text'] ?>
                    </span>
                <?php elseif (!$isPast): ?>
                    <span class="text-gray-400 bg-gray-100 px-3 py-1 rounded-lg text-xs font-semibold">⏳ Ausstehend</span>
                <?php endif; ?>

                <?php if ($canChange && $status !== 'excused'): ?>
                    <!-- Entschuldigen-Button: nur wenn Termin noch nicht gestartet -->
                    <button onclick="excuseMe(<?= $s['id'] ?>, <?= $member['id'] ?>)"
                            class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1.5 rounded-lg text-xs font-semibold transition whitespace-nowrap">
                        Entschuldigen
                    </button>
                <?php elseif ($canChange && $isSelfExcused): ?>
                    <!-- Zurückziehen-Button: nur wenn selbst entschuldigt UND Termin noch nicht gestartet -->
                    <button onclick="withdrawExcuse(<?= $s['id'] ?>, <?= $member['id'] ?>)"
                            class="bg-gray-400 hover:bg-gray-500 text-white px-3 py-1.5 rounded-lg text-xs font-semibold transition whitespace-nowrap">
                        Zurückziehen
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Strafenliste -->
<?php if (!empty($penalties)): ?>
<div class="bg-white rounded-xl shadow-sm border overflow-hidden mb-8">
    <div class="px-5 py-4 border-b flex justify-between items-center bg-gradient-to-r from-red-600 to-red-700">
        <h2 class="font-bold text-white">💰 Meine Strafen</h2>
        <span class="text-white font-bold"><?= format_currency($penaltyTotal) ?></span>
    </div>
    <div class="divide-y">
        <?php foreach ($penalties as $p): ?>
        <div class="px-5 py-3 flex justify-between items-center">
            <div>
                <div class="text-sm font-medium text-gray-800"><?= e($p['type_description']) ?></div>
                <div class="text-xs text-gray-400"><?= format_date($p['penalty_date']) ?><?php if ($p['comment']): ?> · <?= e($p['comment']) ?><?php endif; ?></div>
            </div>
            <div class="text-red-600 font-semibold"><?= format_currency($p['amount']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<script>
// Donut-Diagramm
document.addEventListener('DOMContentLoaded', function() {
    new Chart(document.getElementById('chartDonut'), {
        type: 'doughnut',
        data: {
            labels: ['Anwesend', 'Entschuldigt', 'Unentschuldigt', 'Ausstehend'],
            datasets: [{
                data: [<?= $present ?>, <?= $excused ?>, <?= $absent ?>, <?= $pending ?>],
                backgroundColor: ['#22c55e', '#f59e0b', '#ef4444', '#d1d5db'],
                borderWidth: 0,
            }]
        },
        options: {
            responsive: true,
            cutout: '60%',
            plugins: {
                legend: { position: 'bottom', labels: { padding: 12, boxWidth: 12 } }
            }
        }
    });
});

// Entschuldigung
async function excuseMe(sessionId, memberId) {
    if (!confirm('Möchtest du dich für diesen Termin entschuldigen?')) return;

    const result = await apiCall('excuse', {
        session_id: sessionId,
        member_id: memberId
    });

    if (result.success) {
        if (result.short_notice) {
            showToast(result.message, 'warning');
        }
        setTimeout(() => location.reload(), 1500);
    }
}

// Entschuldigung zurückziehen
async function withdrawExcuse(sessionId, memberId) {
    if (!confirm('Möchtest du deine Entschuldigung zurückziehen? Du giltst dann wieder als teilnehmend.')) return;

    const result = await apiCall('withdraw_excuse', {
        session_id: sessionId,
        member_id: memberId
    });

    if (result.success) {
        setTimeout(() => location.reload(), 1500);
    }
}
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
