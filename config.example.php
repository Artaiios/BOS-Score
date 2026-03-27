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
define('APP_VERSION', '0.9.1');
define('TIMEZONE', 'Europe/Berlin');

// ── Setup-Sperre ────────────────────────────────────────────
// Nach der Ersteinrichtung auf true setzen!
define('SETUP_COMPLETE', false);

// ── Fehleranzeige ───────────────────────────────────────────
// Im Produktivbetrieb auf false setzen
define('DEBUG_MODE', false);

// ── SMTP E-Mail-Versand ─────────────────────────────────────
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);              // 587 = TLS, 465 = SSL
define('SMTP_USER', 'noreply@example.com');
define('SMTP_PASS', 'DEIN_SMTP_PASSWORT');
define('SMTP_FROM', 'noreply@example.com');
define('SMTP_FROM_NAME', 'BOS-Score');
define('SMTP_ENCRYPTION', 'tls');      // 'tls' oder 'ssl'

// ── Authentifizierung & Sessions ────────────────────────────
define('SESSION_LIFETIME_DAYS', 30);
define('MAGIC_LINK_EXPIRY_MINUTES', 30);
define('RATE_LIMIT_MAGIC_LINKS_PER_HOUR', 3);
define('RATE_LIMIT_REGISTRATIONS_PER_HOUR', 10);

// ── Datenschutz (DSGVO) ────────────────────────────────────
define('PRIVACY_VERSION', '1.0');
define('PRIVACY_FILE', __DIR__ . '/privacy.md');
define('SOFT_DELETE_RETENTION_DAYS', 30);

// ── Session-Cookie-Name ─────────────────────────────────────
define('AUTH_COOKIE_NAME', 'bos_score_session');

// ═══════════════════════════════════════════════════════════
// Ab hier: Nicht verändern (Framework-Funktionen)
// ═══════════════════════════════════════════════════════════

date_default_timezone_set(TIMEZONE);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
