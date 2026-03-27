<?php
/**
 * BOS-Score – Datenschutzerklärung
 * Rendert privacy.md als HTML-Seite.
 *
 * BUGFIXES v0.9.1:
 * - match() → switch() für PHP 7.4 Kompatibilität
 * - SOFT_DELETE_RETENTION_DAYS mit defined()-Guard abgesichert
 * - PRIVACY_VERSION mit defined()-Guard abgesichert
 * - Alle Konstanten defensive abgefragt
 */

// config.php und db.php sind bereits über index.php geladen (require_once → kein Doppel-Include)
// Defensive: Falls direkt aufgerufen
if (!defined('APP_NAME')) {
    require_once __DIR__ . '/../config.php';
}

$privacyFile = defined('PRIVACY_FILE') ? PRIVACY_FILE : __DIR__ . '/../privacy.md';
$orgName     = 'der Betreiber';
$adminEmail  = '';

// Server-Config laden (DB muss nicht verfügbar sein)
try {
    if (!function_exists('get_pdo')) {
        require_once __DIR__ . '/../db.php';
    }
    if (function_exists('get_server_config')) {
        $orgName    = get_server_config('organization_name', 'der Betreiber');
        $adminEmail = get_server_config('admin_email', '');
    }
} catch (Exception $e) {
    // Fallback – DB nicht erreichbar (z.B. während Setup)
    $adminEmail = '';
}

$content = '';
if (file_exists($privacyFile)) {
    $raw = file_get_contents($privacyFile);

    // Platzhalter ersetzen
    $retentionDays = defined('SOFT_DELETE_RETENTION_DAYS') ? (string)SOFT_DELETE_RETENTION_DAYS : '30';
    $privacyVer    = defined('PRIVACY_VERSION')            ? PRIVACY_VERSION                    : '1.0';
    $appName       = defined('APP_NAME')                   ? APP_NAME                           : 'BOS-Score';

    $raw = str_replace('{{ORGANIZATION}}',  $orgName,       $raw);
    $raw = str_replace('{{ADMIN_EMAIL}}',   $adminEmail,    $raw);
    $raw = str_replace('{{APP_NAME}}',      $appName,       $raw);
    $raw = str_replace('{{PRIVACY_VERSION}}', $privacyVer,  $raw);
    $raw = str_replace('{{RETENTION_DAYS}}',  $retentionDays, $raw);

    $content = $raw;
} else {
    $content = "## Datenschutzerklärung\n\nDie Datenschutzerklärung ist derzeit nicht verfügbar. Bitte wende dich an den Administrator.";
}

// ── Einfacher Markdown-zu-HTML Renderer ────────────────────
// BUGFIX: match() → switch() für PHP 7.4 Kompatibilität
function simple_markdown_to_html(string $md): string
{
    $lines  = explode("\n", $md);
    $html   = '';
    $inList = false;

    foreach ($lines as $line) {
        $trimmed = trim($line);

        // Leere Zeile
        if (empty($trimmed)) {
            if ($inList) {
                $html  .= "</ul>\n";
                $inList = false;
            }
            $html .= "<br>\n";
            continue;
        }

        // Überschriften (##)
        if (preg_match('/^(#{1,4})\s+(.+)$/', $trimmed, $m)) {
            if ($inList) {
                $html  .= "</ul>\n";
                $inList = false;
            }
            $level = strlen($m[1]);
            // BUGFIX: switch statt match (PHP 7.4 kompatibel)
            switch ($level) {
                case 1:
                    $classes = 'text-2xl font-bold text-gray-900 mt-8 mb-4';
                    break;
                case 2:
                    $classes = 'text-xl font-bold text-gray-900 mt-6 mb-3';
                    break;
                case 3:
                    $classes = 'text-lg font-semibold text-gray-800 mt-4 mb-2';
                    break;
                default:
                    $classes = 'text-base font-semibold text-gray-800 mt-3 mb-2';
                    break;
            }
            $html .= "<h{$level} class=\"{$classes}\">"
                   . htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8')
                   . "</h{$level}>\n";
            continue;
        }

        // Aufzählung (- oder *)
        if (preg_match('/^[-*]\s+(.+)$/', $trimmed, $m)) {
            if (!$inList) {
                $html  .= '<ul class="list-disc pl-6 text-gray-700 text-sm space-y-1 mb-3">' . "\n";
                $inList = true;
            }
            $html .= '<li>' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . "</li>\n";
            continue;
        }

        // Normaler Absatz
        if ($inList) {
            $html  .= "</ul>\n";
            $inList = false;
        }
        $html .= '<p class="text-gray-700 text-sm mb-3">'
               . htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8')
               . "</p>\n";
    }

    if ($inList) {
        $html .= "</ul>\n";
    }

    return $html;
}

$appNameSafe = defined('APP_NAME') ? htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') : 'BOS-Score';
$verSafe     = defined('APP_VERSION') ? htmlspecialchars(APP_VERSION, ENT_QUOTES, 'UTF-8') : '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datenschutz – <?= $appNameSafe ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen p-4">
<div class="max-w-3xl mx-auto">

    <div class="bg-white rounded-2xl shadow-xl p-8 mb-6">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900">🔒 Datenschutzerklärung</h1>
            <a href="index.php" class="text-sm text-gray-500 hover:text-gray-700">← Zurück</a>
        </div>

        <div class="prose prose-sm max-w-none">
            <?= simple_markdown_to_html($content) ?>
        </div>
    </div>

    <p class="text-center text-gray-400 text-xs mt-4">
        <?= $appNameSafe ?><?= $verSafe ? ' v' . $verSafe : '' ?>
    </p>
</div>
</body>
</html>
