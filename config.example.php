<?php
/**
 * BOS-Score – Konfiguration (Vorlage)
 *
 * 1. Diese Datei kopieren: cp config.example.php config.php
 * 2. Alle Platzhalter mit deinen Werten ersetzen
 * 3. setup.php im Browser aufrufen
 */

// ── Datenbank-Zugangsdaten ──────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'DEIN_DATENBANKNAME');
define('DB_USER', 'DEIN_DB_BENUTZER');
define('DB_PASS', 'DEIN_DB_PASSWORT');
define('DB_CHARSET', 'utf8mb4');

// ── Anwendungs-Einstellungen ────────────────────────────────
define('APP_NAME', 'BOS-Score');
define('APP_VERSION', '1.0.2');
define('TIMEZONE', 'Europe/Berlin');

// ── Setup-Sperre ────────────────────────────────────────────
define('SETUP_COMPLETE', false);

// ── Fehleranzeige ───────────────────────────────────────────
define('DEBUG_MODE', false);

// ── SMTP E-Mail-Versand ─────────────────────────────────────
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'noreply@example.com');
define('SMTP_PASS', 'DEIN_SMTP_PASSWORT');
define('SMTP_FROM', 'noreply@example.com');
define('SMTP_FROM_NAME', 'BOS-Score');
define('SMTP_ENCRYPTION', 'tls');

// ── Authentifizierung & Sessions ────────────────────────────
define('SESSION_LIFETIME_DAYS', 30);
define('MAGIC_LINK_EXPIRY_MINUTES', 30);
define('RATE_LIMIT_MAGIC_LINKS_PER_HOUR', 3);
define('RATE_LIMIT_REGISTRATIONS_PER_HOUR', 10);

// ── Datenschutz (DSGVO) ────────────────────────────────────
define('PRIVACY_VERSION', '1.1');
define('ORGANIZATION_ADDRESS', 'ADRESSE_DER_ORGA'); // eine echte Adresse, "Straße NR, PLZ ORT"
define('PRIVACY_FILE', __DIR__ . '/privacy.md');
define('SOFT_DELETE_RETENTION_DAYS', 30);
define('AUDIT_LOG_RETENTION_DAYS', 365);

// ── Sicherheit ──────────────────────────────────────────────
// HMAC-Secret für IP-/Token-Hashing (DSGVO-konforme Pseudonymisierung)
// Einmalig generieren: php -r "echo bin2hex(random_bytes(32));"
// NIEMALS in Git committen! Nur in config.php, nicht in config.example.php.
define('HASH_SECRET', 'HIER_DEIN_HASH_SECRET_EINFUEGEN');

// Basis-URL der Anwendung (verhindert Host-Header-Injection)
// Beispiel: 'https://meine-domain.de/bos-score' oder 'https://meine-domain.de'
define('APP_BASE_URL', 'https://DEINE-DOMAIN.de/bos-score');

// ── Session-Cookie-Name ─────────────────────────────────────
define('AUTH_COOKIE_NAME', 'bos_score_session');

// ═══════════════════════════════════════════════════════════
// Ab hier: Nicht verändern (Framework-Funktionen)
// ═══════════════════════════════════════════════════════════

date_default_timezone_set(TIMEZONE);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    // Warnung wenn DEBUG_MODE auf einem Nicht-Localhost-System aktiv ist
    if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
        error_log('BOS-Score SICHERHEITSWARNUNG: DEBUG_MODE ist auf einem Produktivsystem aktiv! (' . ($_SERVER['HTTP_HOST'] ?? 'unknown') . ')');
    }
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── CSRF-Schutz ─────────────────────────────────────────────

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ── Hilfsfunktionen ─────────────────────────────────────────

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function format_date(string $date): string {
    return (new DateTime($date))->format('d.m.Y');
}

function format_datetime(string $datetime): string {
    return (new DateTime($datetime))->format('d.m.Y \u\m H:i \U\h\r');
}

function format_time(string $time): string {
    return substr($time, 0, 5);
}

function format_weekday(string $date): string {
    $days = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
    return $days[(int)(new DateTime($date))->format('w')];
}

function format_currency(float $amount): string {
    return number_format($amount, 2, ',', '.') . ' €';
}

function generate_token(int $length = 32): string {
    return bin2hex(random_bytes($length));
}

function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function get_base_url(): string {
    // APP_BASE_URL nutzen falls definiert (verhindert Host-Header-Injection)
    if (defined('APP_BASE_URL') && APP_BASE_URL !== 'https://DEINE-DOMAIN.de/bos-score') {
        return rtrim(APP_BASE_URL, '/');
    }
    // Fallback für Entwicklung/Setup (nicht für Produktion empfohlen)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = dirname($_SERVER['SCRIPT_NAME']);
    return rtrim($protocol . '://' . $host . $path, '/');
}

function is_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
}

function hash_value(string $value): string {
    return hash_hmac('sha256', $value, HASH_SECRET);
}

function parse_device_label(string $userAgent): string {
    $ua = strtolower($userAgent);
    $device = 'Unbekannt';
    if (strpos($ua, 'ipad') !== false) $device = 'Tablet/iPad';
    elseif (strpos($ua, 'iphone') !== false) $device = 'Mobile/iPhone';
    elseif (strpos($ua, 'android') !== false) $device = strpos($ua, 'mobile') !== false ? 'Mobile/Android' : 'Tablet/Android';
    elseif (strpos($ua, 'windows') !== false) $device = 'Desktop/Windows';
    elseif (strpos($ua, 'macintosh') !== false || strpos($ua, 'mac os') !== false) $device = 'Desktop/Mac';
    elseif (strpos($ua, 'linux') !== false) $device = 'Desktop/Linux';

    $browser = '';
    if (strpos($ua, 'firefox') !== false) $browser = 'Firefox';
    elseif (strpos($ua, 'edg/') !== false || strpos($ua, 'edge') !== false) $browser = 'Edge';
    elseif (strpos($ua, 'chrome') !== false && strpos($ua, 'edg') === false) $browser = 'Chrome';
    elseif (strpos($ua, 'safari') !== false && strpos($ua, 'chrome') === false) $browser = 'Safari';

    return $browser ? "$device · $browser" : $device;
}
