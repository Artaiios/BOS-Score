<?php
/**
 * BOS-Score – Profilseite
 * Event-übergreifend: Name, Sessions, DSGVO-Rechte.
 */

$activeSessions = get_active_sessions($user['id']);
$consentHistory = get_consent_log($user['id']);
$userEvents = get_user_events($user['id']);
$userRoles = get_user_roles($user['id']);
$orgName = get_server_config('organization_name', APP_NAME);

$successMsg = $_SESSION['profile_success'] ?? '';
$errorMsg = $_SESSION['profile_error'] ?? '';
unset($_SESSION['profile_success'], $_SESSION['profile_error']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil – <?= e(APP_NAME) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">

<!-- Navigation -->
<nav class="bg-white shadow-sm border-b">
    <div class="max-w-3xl mx-auto px-4 py-3 flex items-center justify-between">
        <a href="index.php" class="text-xl font-bold text-gray-900"><?= e(APP_NAME) ?></a>
        <div class="flex items-center gap-4">
            <a href="index.php" class="text-sm text-gray-600 hover:text-gray-900">← Übersicht</a>
            <a href="index.php?logout" class="text-sm text-red-600 hover:text-red-700">Abmelden</a>
        </div>
    </div>
</nav>

<div class="max-w-3xl mx-auto px-4 py-8">

    <h1 class="text-2xl font-bold text-gray-900 mb-6">Profil & Einstellungen</h1>

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

    <!-- ── Persönliche Daten ─────────────────────────────────── -->
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
        <h2 class="text-lg font-bold text-gray-900 mb-4">Persönliche Daten</h2>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="update_name">
            <?= csrf_field() ?>

            <div>
                <label for="display_name" class="block text-sm font-semibold text-gray-700 mb-1">Anzeigename</label>
                <div class="flex gap-2">
                    <input type="text" id="display_name" name="display_name" required
                           value="<?= e($user['display_name']) ?>" maxlength="100"
                           class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none">
                    <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-red-700 transition">
                        Speichern
                    </button>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">E-Mail-Adresse</label>
                <input type="email" readonly value="<?= e($user['email']) ?>"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-500">
                <p class="text-xs text-gray-400 mt-1">Die E-Mail-Adresse kann nicht geändert werden (Anmeldung per Magic Link).</p>
            </div>
        </form>
    </div>

    <!-- ── Meine Rollen ──────────────────────────────────────── -->
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
        <h2 class="text-lg font-bold text-gray-900 mb-4">Meine Rollen</h2>

        <?php if (empty($userRoles)): ?>
            <p class="text-gray-500 text-sm">Keine Rollen zugewiesen.</p>
        <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($userRoles as $r): ?>
                    <?php
                    $roleLabel = match($r['role']) {
                        'server_admin' => '🔧 Server-Admin',
                        'admin'        => '🔑 Event-Admin',
                        'member'       => '👤 Teilnehmer',
                        default        => $r['role'],
                    };
                    $context = $r['event_name'] ? e($r['event_name']) : 'Global';
                    ?>
                    <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                        <div>
                            <span class="text-sm font-medium text-gray-900"><?= $roleLabel ?></span>
                            <span class="text-xs text-gray-400 ml-2"><?= $context ?></span>
                        </div>
                        <span class="text-xs text-gray-400">seit <?= format_date($r['granted_at']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── Aktive Sessions ───────────────────────────────────── -->
    <div id="sessions" class="bg-white rounded-xl shadow-sm p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-gray-900">Aktive Sessions</h2>
            <?php if (count($activeSessions) > 1): ?>
                <form method="POST" class="inline" onsubmit="return confirm('Alle anderen Sessions abmelden?')">
                    <input type="hidden" name="action" value="revoke_all_sessions">
                    <?= csrf_field() ?>
                    <button type="submit" class="text-xs text-red-600 hover:text-red-700 font-semibold">
                        Alle anderen abmelden
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (empty($activeSessions)): ?>
            <p class="text-gray-500 text-sm">Keine aktiven Sessions.</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($activeSessions as $sess): ?>
                    <?php $isCurrent = ($sess['id'] === $user['session_id']); ?>
                    <div class="flex items-center justify-between py-3 border-b border-gray-100 last:border-0 <?= $isCurrent ? 'bg-green-50 -mx-3 px-3 rounded-lg' : '' ?>">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-gray-900"><?= e($sess['device_label'] ?? 'Unbekannt') ?></span>
                                <?php if ($isCurrent): ?>
                                    <span class="text-xs bg-green-200 text-green-800 px-2 py-0.5 rounded-full font-semibold">Aktuell</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-gray-400 mt-0.5">
                                Angemeldet: <?= format_datetime($sess['created_at']) ?>
                                <?php if ($sess['last_seen_at']): ?>
                                    · Zuletzt aktiv: <?= format_datetime($sess['last_seen_at']) ?>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-gray-400">
                                Läuft ab: <?= format_datetime($sess['expires_at']) ?>
                            </div>
                        </div>
                        <?php if (!$isCurrent): ?>
                            <form method="POST" onsubmit="return confirm('Diese Session abmelden?')">
                                <input type="hidden" name="action" value="revoke_session">
                                <input type="hidden" name="session_id" value="<?= $sess['id'] ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="text-xs text-red-600 hover:text-red-700 font-semibold">
                                    Widerrufen
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── Einwilligungshistorie ──────────────────────────────── -->
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
        <h2 class="text-lg font-bold text-gray-900 mb-4">Datenschutz-Einwilligungen</h2>

        <p class="text-sm text-gray-600 mb-3">
            Aktuelle Datenschutzversion: <strong>v<?= e(PRIVACY_VERSION) ?></strong>
            · <a href="index.php?privacy" class="text-red-600 hover:text-red-700" target="_blank">Datenschutzerklärung lesen</a>
        </p>

        <?php if (!empty($consentHistory)): ?>
            <div class="space-y-2">
                <?php foreach ($consentHistory as $c): ?>
                    <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0 text-sm">
                        <div>
                            <span class="font-medium <?= $c['action'] === 'granted' ? 'text-green-700' : 'text-red-700' ?>">
                                <?= $c['action'] === 'granted' ? '✅ Zugestimmt' : '❌ Widerrufen' ?>
                            </span>
                            <span class="text-gray-500 ml-2">Version <?= e($c['consent_version']) ?></span>
                        </div>
                        <span class="text-xs text-gray-400"><?= format_datetime($c['created_at']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── DSGVO-Rechte ──────────────────────────────────────── -->
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
        <h2 class="text-lg font-bold text-gray-900 mb-4">Deine Rechte (DSGVO)</h2>

        <div class="space-y-4">
            <!-- Datenexport -->
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">📥 Datenexport</h3>
                    <p class="text-xs text-gray-500">Alle deine Daten als JSON herunterladen (Art. 15/20 DSGVO).</p>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="export_data">
                    <?= csrf_field() ?>
                    <button type="submit" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg text-sm font-semibold hover:bg-gray-200 transition">
                        Exportieren
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Account löschen ───────────────────────────────────── -->
    <div id="delete" class="bg-white rounded-xl shadow-sm p-6 border border-red-200">
        <h2 class="text-lg font-bold text-red-800 mb-2">⚠️ Account löschen</h2>
        <p class="text-sm text-gray-600 mb-4">
            Dein Account wird zur Löschung vorgemerkt und nach <?= SOFT_DELETE_RETENTION_DAYS ?> Tagen
            endgültig entfernt. Alle Sessions werden sofort beendet. Dieser Vorgang kann nur durch
            den Server-Administrator rückgängig gemacht werden.
        </p>

        <form method="POST" onsubmit="return confirm('Bist du sicher? Dein Account und alle zugehörigen Daten werden gelöscht.')">
            <input type="hidden" name="action" value="delete_account">
            <?= csrf_field() ?>

            <div class="mb-4">
                <label for="confirm_email" class="block text-sm font-semibold text-gray-700 mb-1">
                    Zur Bestätigung: gib deine E-Mail-Adresse ein
                </label>
                <input type="email" id="confirm_email" name="confirm_email" required
                       placeholder="<?= e($user['email']) ?>"
                       class="w-full border border-red-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none">
            </div>

            <button type="submit" class="bg-red-600 text-white px-6 py-2 rounded-lg text-sm font-semibold hover:bg-red-700 transition">
                Account endgültig löschen
            </button>
        </form>
    </div>

</div>

<footer class="text-center text-xs text-gray-400 py-6">
    <?= e(APP_NAME) ?> v<?= APP_VERSION ?>
</footer>

</body>
</html>
