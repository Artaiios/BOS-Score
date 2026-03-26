<?php
/**
 * LAZ Übungs-Tracker – Hauptrouter
 */

require_once __DIR__ . '/db.php';

// ── Routing ─────────────────────────────────────────────────

$eventToken = $_GET['event'] ?? '';
$adminToken = $_GET['admin'] ?? '';
$memberId   = isset($_GET['member']) ? (int)$_GET['member'] : 0;

// Kein Event-Token → Startseite oder Fehler
if (empty($eventToken)) {
    $showOverview = get_server_config('show_public_overview', '0') === '1';
    if ($showOverview) {
        require __DIR__ . '/views/overview.php';
    } else {
        http_response_code(404);
        $errorMessage = 'Kein Event angegeben. Bitte verwende den direkten Link zu deinem Event.';
        require __DIR__ . '/views/partials/error.php';
    }
    exit;
}

// Event laden
$event = get_event_by_public_token($eventToken);
if (!$event) {
    http_response_code(404);
    $errorMessage = 'Event nicht gefunden.';
    require __DIR__ . '/views/partials/error.php';
    exit;
}

$isArchived = ($event['status'] === 'archived');

// Admin-Modus prüfen
$isAdmin = false;
if (!empty($adminToken)) {
    $adminEvent = get_event_by_admin_token($eventToken, $adminToken);
    if ($adminEvent) {
        $isAdmin = true;
        $event = $adminEvent; // Admin kann auch archivierte Events sehen
    }
}

// ── View auswählen ──────────────────────────────────────────

if ($isAdmin) {
    require __DIR__ . '/views/admin.php';
} elseif ($memberId > 0) {
    $member = get_member($memberId);
    if (!$member || $member['event_id'] != $event['id']) {
        http_response_code(404);
        $errorMessage = 'Teilnehmer nicht gefunden.';
        require __DIR__ . '/views/partials/error.php';
        exit;
    }
    require __DIR__ . '/views/member.php';
} else {
    require __DIR__ . '/views/dashboard.php';
}
