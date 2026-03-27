<?php
/**
 * BOS-Score – Öffentliche Startseite
 * Zeigt nur den Login-Hinweis, da Events Auth erfordern.
 */

$orgName = get_server_config('organization_name', APP_NAME);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
<div class="max-w-md w-full text-center">
    <div class="bg-white rounded-2xl shadow-xl p-8">
        <div class="text-5xl mb-4">📊</div>
        <h1 class="text-2xl font-bold text-gray-900"><?= e(APP_NAME) ?></h1>
        <p class="text-gray-500 mt-1"><?= e($orgName) ?></p>
        <p class="text-gray-600 text-sm mt-6">
            Webanwendung zur Verwaltung von Übungen und Leistungsabzeichen für BOS-Organisationen.
        </p>
        <a href="index.php?login" class="block w-full mt-6 bg-red-600 text-white font-semibold py-3 rounded-xl hover:bg-red-700 transition">
            🔐 Anmelden
        </a>
    </div>
    <p class="text-center text-gray-400 text-xs mt-4">
        <a href="index.php?privacy" class="hover:text-gray-600">Datenschutz</a>
        · <?= e(APP_NAME) ?> v<?= APP_VERSION ?>
    </p>
</div>
</body>
</html>
