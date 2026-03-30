<?php
/**
 * BOS-Score – Header mit Breadcrumb-Navigation
 *
 * Erwartet (optional): $event, $pageTitle, $breadcrumbLevel, $member
 * $breadcrumbLevel: 'event_dashboard', 'event_admin', 'event_member' (default: auto-detect)
 */

$_headerUser = $user ?? get_logged_in_user();
$_headerOrgName = isset($event) ? get_organization_name($event) : get_server_config('organization_name', APP_NAME);
$_headerTheme = $event['theme_primary'] ?? '#dc2626';
$_headerEventToken = $event['public_token'] ?? '';
$_headerIsAdmin = isset($event) && $_headerUser && (has_event_role($_headerUser['id'], $event['id'], ['admin']) || is_server_admin($_headerUser['id']));
$_headerIsServerAdmin = $_headerUser && is_server_admin($_headerUser['id']);
$_headerPageTitle = $pageTitle ?? ($event['name'] ?? APP_NAME);

// Breadcrumb-Level automatisch erkennen falls nicht gesetzt
if (!isset($breadcrumbLevel)) {
    if (isset($member)) $breadcrumbLevel = 'event_member';
    elseif (isset($_GET['admin_view'])) $breadcrumbLevel = 'event_admin';
    elseif (isset($event)) $breadcrumbLevel = 'event_dashboard';
    else $breadcrumbLevel = 'home';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($_headerPageTitle) ?> – <?= e(APP_NAME) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <link rel="stylesheet" href="assets/style.css">
    <style>:root{--color-primary:<?= e($_headerTheme) ?>;--color-primary-dark:<?= e($_headerTheme) ?>dd;}</style>
</head>
<body class="bg-gray-50 min-h-screen">

<!-- Navigation mit Breadcrumb -->
<nav class="bg-white shadow-sm border-b">
    <div class="max-w-5xl mx-auto px-4 py-3">
        <div class="flex items-center justify-between">
            <!-- Breadcrumb -->
            <div class="flex items-center gap-1.5 text-sm min-w-0 overflow-hidden">
                <!-- Ebene 1: Home -->
                <a href="index.php" class="flex items-center gap-1 text-gray-500 hover:text-gray-900 shrink-0">
                    <span class="text-base">🏠</span>
                    <span class="hidden sm:inline font-medium"><?= e(APP_NAME) ?></span>
                </a>

                <?php if (isset($event)): ?>
                <!-- Ebene 2: Event -->
                <span class="text-gray-300 shrink-0">/</span>
                <?php if ($breadcrumbLevel === 'event_dashboard'): ?>
                    <span class="font-bold text-gray-900 truncate" style="color: var(--color-primary);"><?= e($event['name']) ?></span>
                    <?php if ($isArchived ?? false): ?><span class="text-xs bg-gray-200 text-gray-500 px-1.5 py-0.5 rounded-full ml-1 shrink-0">Archiv</span><?php endif; ?>
                <?php else: ?>
                    <a href="index.php?event=<?= e($_headerEventToken) ?>" class="text-gray-500 hover:text-gray-900 truncate" style="color: var(--color-primary);"><?= e($event['name']) ?></a>
                <?php endif; ?>

                <?php if ($breadcrumbLevel === 'event_admin'): ?>
                <!-- Ebene 3: Verwaltung -->
                <span class="text-gray-300 shrink-0">/</span>
                <span class="font-bold text-gray-900 shrink-0">⚙️ Verwaltung</span>
                <?php elseif ($breadcrumbLevel === 'event_member' && isset($member)): ?>
                <!-- Ebene 3: Teilnehmer-Detail -->
                <span class="text-gray-300 shrink-0">/</span>
                <span class="font-bold text-gray-900 truncate">👤 <?= e($member['name']) ?></span>
                <?php endif; ?>

                <?php endif; ?>
            </div>

            <!-- Rechte Seite: Aktionen -->
            <div class="flex items-center gap-3 text-sm shrink-0 ml-3">
                <?php if ($_headerUser): ?>
                    <?php if (isset($event) && $_headerIsAdmin && $breadcrumbLevel !== 'event_admin'): ?>
                        <a href="index.php?event=<?= e($_headerEventToken) ?>&admin_view=1"
                           class="text-gray-500 hover:text-gray-900 hidden sm:inline">⚙️ Verwaltung</a>
                    <?php endif; ?>
                    <?php if ($_headerIsServerAdmin && $breadcrumbLevel !== 'server_admin'): ?>
                        <a href="admin.php" class="text-gray-500 hover:text-gray-900 hidden sm:inline">🔧 Server</a>
                    <?php endif; ?>
                    <a href="profile.php" class="text-gray-500 hover:text-gray-900 hidden sm:inline"><?= e($_headerUser['display_name']) ?></a>
                    <a href="index.php?logout" class="text-red-600 hover:text-red-700">Abmelden</a>
                <?php else: ?>
                    <a href="index.php?login" class="text-red-600 hover:text-red-700">Anmelden</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<div class="max-w-5xl mx-auto px-4 py-6">
