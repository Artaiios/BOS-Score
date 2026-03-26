<?php
/**
 * LAZ Übungs-Tracker – Konfiguration
 * Freiwillige Feuerwehr Rutesheim
 */

// Datenbank-Zugangsdaten (bitte anpassen!)
define('DB_HOST', 'localhost');
define('DB_NAME', 'laz_tracker');
define('DB_USER', 'DEIN_DB_USER');
define('DB_PASS', 'DEIN_DB_PASSWORT');
define('DB_CHARSET', 'utf8mb4');

// Anwendungs-Einstellungen
define('APP_NAME', 'LAZ Übungs-Tracker');
define('APP_VERSION', '1.7.2');
define('TIMEZONE', 'Europe/Berlin');

// Setup-Sperre: auf true setzen nach der Ersteinrichtung
define('SETUP_COMPLETE', false);

// Fehleranzeige (im Produktivbetrieb auf false setzen)
define('DEBUG_MODE', false);

// Zeitzone setzen
date_default_timezone_set(TIMEZONE);

// Fehlerbehandlung
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Session starten (für CSRF-Token)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CSRF-Token generieren
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

// Hilfsfunktionen
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function format_date(string $date): string {
    $dt = new DateTime($date);
    return $dt->format('d.m.Y');
}

function format_datetime(string $datetime): string {
    $dt = new DateTime($datetime);
    return $dt->format('d.m.Y \u\m H:i \U\h\r');
}

function format_time(string $time): string {
    return substr($time, 0, 5);
}

function format_weekday(string $date): string {
    $days = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
    $dt = new DateTime($date);
    return $days[(int)$dt->format('w')];
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
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = dirname($_SERVER['SCRIPT_NAME']);
    return rtrim($protocol . '://' . $host . $path, '/');
}
