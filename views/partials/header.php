<?php
/**
 * BOS-Score – Gemeinsamer Header
 * Erwartet: $event (optional), $pageTitle (optional), $user (via get_logged_in_user())
 */

$_headerUser = $user ?? get_logged_in_user();
$_headerOrgName = isset($event) ? get_organization_name($event) : get_server_config('organization_name', APP_NAME);
$_headerTheme = $event['theme_primary'] ?? '#dc2626';
$_headerEventToken = $event['public_token'] ?? '';
$_headerIsAdmin = isset($event) && $_headerUser && (has_event_role($_headerUser['id'], $event['id'], ['admin']) || is_server_admin($_headerUser['id']));
$_headerPageTitle = $pageTitle ?? ($event['name'] ?? APP_NAME);
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
    <style>
        :root {
            --color-primary: <?= e($_headerTheme) ?>;
            --color-primary-dark: <?= e($_headerTheme) ?>dd;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

<!-- Navigation -->
<nav class="bg-white shadow-sm border-b">
    <div class="max-w-5xl mx-auto px-4 py-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <?php if (isset($event)): ?>
                    <a href="index.php?event=<?= e($_headerEventToken) ?>" class="text-lg font-bold text-gray-900" style="color: var(--color-primary);">
                        <?= e($event['name']) ?>
                    </a>
                    <span class="text-xs text-gray-400"><?= e($_headerOrgName) ?></span>
                    <?php if ($isArchived ?? false): ?>
                        <span class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full">Archiviert</span>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="index.php" class="text-lg font-bold text-gray-900"><?= e(APP_NAME) ?></a>
                <?php endif; ?>
            </div>

            <div class="flex items-center gap-3 text-sm">
                <?php if ($_headerUser): ?>
                    <?php if (isset($event) && $_headerIsAdmin): ?>
                        <a href="index.php?event=<?= e($_headerEventToken) ?>&admin_view=1"
                           class="text-gray-600 hover:text-gray-900">⚙️ Admin</a>
                    <?php endif; ?>
                    <a href="index.php" class="text-gray-600 hover:text-gray-900">🏠 Übersicht</a>
                    <a href="profile.php" class="text-gray-600 hover:text-gray-900"><?= e($_headerUser['display_name']) ?></a>
                    <a href="index.php?logout" class="text-red-600 hover:text-red-700">Abmelden</a>
                <?php else: ?>
                    <a href="index.php?login" class="text-red-600 hover:text-red-700">Anmelden</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<div class="max-w-5xl mx-auto px-4 py-6">
