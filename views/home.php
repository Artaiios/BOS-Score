<?php
/**
 * BOS-Score – Persönliches Dashboard
 * Zeigt alle Events des eingeloggten Users mit Direktlinks.
 */

$user = require_auth();
$userId = $user['id'];

$events = get_user_events($userId);
$isAdmin = is_server_admin($userId);
$orgName = get_server_config('organization_name', APP_NAME);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> – Meine Übersicht</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">

<!-- Navigation -->
<nav class="bg-white shadow-sm border-b">
    <div class="max-w-4xl mx-auto px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="text-xl font-bold text-gray-900"><?= e(APP_NAME) ?></span>
            <span class="text-xs text-gray-400"><?= e($orgName) ?></span>
        </div>
        <div class="flex items-center gap-4">
            <a href="profile.php" class="text-sm text-gray-600 hover:text-gray-900"><?= e($user['display_name']) ?></a>
            <a href="index.php?logout" class="text-sm text-red-600 hover:text-red-700">Abmelden</a>
        </div>
    </div>
</nav>

<div class="max-w-4xl mx-auto px-4 py-8">

    <!-- Begrüßung -->
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Hallo, <?= e($user['display_name']) ?>!</h1>
        <p class="text-gray-500 mt-1">Deine Übersicht aller Events und Rollen.</p>
    </div>

    <!-- Server-Admin -->
    <?php if ($isAdmin): ?>
    <a href="admin.php" class="block bg-gray-900 text-white rounded-xl p-5 mb-6 hover:bg-gray-800 transition">
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-lg">🔧</span>
                    <span class="font-bold">Server-Administration</span>
                </div>
                <p class="text-gray-300 text-sm mt-1">Events verwalten, Admins einladen, globale Einstellungen</p>
            </div>
            <span class="text-gray-400 text-xl">→</span>
        </div>
    </a>
    <?php endif; ?>

    <!-- Events -->
    <?php if (empty($events)): ?>
        <div class="bg-white rounded-xl shadow-sm p-8 text-center">
            <div class="text-4xl mb-4">📭</div>
            <h2 class="text-lg font-bold text-gray-900">Keine Events</h2>
            <p class="text-gray-500 mt-2 text-sm">
                Du bist noch keinem Event zugeordnet.
                <?php if ($isAdmin): ?>
                    Erstelle ein neues Event über die Server-Administration.
                <?php else: ?>
                    Bitte deinen Gruppenführer um einen Einladungslink.
                <?php endif; ?>
            </p>
        </div>
    <?php else: ?>
        <h2 class="text-lg font-bold text-gray-900 mb-4">Meine Events</h2>
        <div class="space-y-4">
            <?php foreach ($events as $ev): ?>
                <?php
                $roleLabel = match($ev['role']) {
                    'admin' => '🔑 Event-Admin',
                    'member' => '👤 Teilnehmer',
                    default => $ev['role'],
                };
                $roleClass = $ev['role'] === 'admin' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800';
                $statusBadge = $ev['status'] === 'archived' ? '<span class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full ml-2">Archiviert</span>' : '';
                $themeColor = $ev['theme_primary'] ?? '#dc2626';
                ?>
                <div class="bg-white rounded-xl shadow-sm overflow-hidden border-l-4" style="border-left-color: <?= e($themeColor) ?>;">
                    <div class="p-5">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="font-bold text-gray-900 text-lg">
                                    <?= e($ev['name']) ?><?= $statusBadge ?>
                                </h3>
                                <?php if ($ev['organization_name']): ?>
                                    <p class="text-gray-500 text-sm"><?= e($ev['organization_name']) ?></p>
                                <?php endif; ?>
                                <span class="inline-block mt-2 text-xs font-semibold px-2 py-0.5 rounded-full <?= $roleClass ?>">
                                    <?= $roleLabel ?>
                                </span>
                            </div>
                        </div>

                        <div class="flex gap-3 mt-4">
                            <a href="index.php?event=<?= e($ev['public_token']) ?>"
                               class="inline-flex items-center gap-1 text-sm font-semibold text-white px-4 py-2 rounded-lg hover:opacity-90 transition"
                               style="background-color: <?= e($themeColor) ?>;">
                                📊 Dashboard
                            </a>
                            <?php if ($ev['role'] === 'admin'): ?>
                                <a href="index.php?event=<?= e($ev['public_token']) ?>&admin_view=1"
                                   class="inline-flex items-center gap-1 text-sm font-semibold text-gray-700 bg-gray-100 px-4 py-2 rounded-lg hover:bg-gray-200 transition">
                                    ⚙️ Admin-Bereich
                                </a>
                            <?php else: ?>
                                <?php
                                $linkedMember = get_linked_member($userId, $ev['id']);
                                if ($linkedMember): ?>
                                    <a href="index.php?event=<?= e($ev['public_token']) ?>&member=<?= $linkedMember['id'] ?>"
                                       class="inline-flex items-center gap-1 text-sm font-semibold text-gray-700 bg-gray-100 px-4 py-2 rounded-lg hover:bg-gray-200 transition">
                                        👤 Mein Status
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Schnelllinks -->
    <div class="mt-8 flex items-center justify-center gap-6 text-sm text-gray-400">
        <a href="profile.php" class="hover:text-gray-600">⚙️ Profil & Einstellungen</a>
        <a href="index.php?privacy" class="hover:text-gray-600">🔒 Datenschutz</a>
        <a href="index.php?logout" class="hover:text-red-600">🚪 Abmelden</a>
    </div>
</div>

<footer class="text-center text-xs text-gray-400 py-6">
    <?= e(APP_NAME) ?> v<?= APP_VERSION ?>
</footer>

</body>
</html>
