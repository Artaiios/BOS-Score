<?php
/**
 * BOS-Score – Hauptrouter
 *
 * Routing-Logik:
 *   ?magic={token}         → Magic Link konsumieren, Session erstellen
 *   ?invite={token}        → Teilnehmer-Registrierung
 *   ?admin_invite={token}  → Admin-Einladung annehmen
 *   ?login                 → Login-Seite (Magic Link anfordern)
 *   ?logout                → Logout
 *   ?privacy               → Datenschutzerklärung
 *   ?consent_required      → Erneute Datenschutz-Zustimmung
 *   ?event={token}         → Event-Dashboard (Auth erforderlich)
 *   ?event={token}&member={id} → Teilnehmer-Detail
 *   (kein Parameter)       → Home-Dashboard oder Login
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/auth.php';

// ── Magic Link konsumieren ──────────────────────────────────
if (isset($_GET['magic'])) {
    $token = $_GET['magic'];
    $result = consume_magic_link($token);

    if (!$result) {
        http_response_code(400);
        $errorMessage = 'Dieser Anmeldelink ist ungültig oder abgelaufen. Bitte fordere einen neuen Link an.';
        $errorAction = '<a href="' . e(get_base_url()) . '/index.php?login" class="inline-block mt-4 bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700 transition">Neuen Link anfordern</a>';
        require __DIR__ . '/views/partials/error.php';
        exit;
    }

    // Session erstellen
    create_session($result['user_id'], $result['remember_me']);

    // Audit-Log
    audit_log(null, $result['user_id'], 'login', 'Anmeldung per Magic Link');

    // Redirect: zurück zur gewünschten Seite oder Home
    $returnTo = $_GET['return_to'] ?? 'index.php';
    redirect($returnTo);
}

// ── Logout ──────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    $user = get_logged_in_user();
    if ($user) {
        audit_log(null, $user['id'], 'logout', 'Abmeldung');
    }
    logout();
    redirect(get_base_url() . '/index.php?login&logged_out=1');
}

// ── Datenschutzerklärung ────────────────────────────────────
if (isset($_GET['privacy'])) {
    require __DIR__ . '/views/privacy.php';
    exit;
}

// ── Erneute Datenschutz-Zustimmung ──────────────────────────
if (isset($_GET['consent_required'])) {
    require __DIR__ . '/views/consent_required.php';
    exit;
}

// ── Login-Seite ─────────────────────────────────────────────
if (isset($_GET['login'])) {
    // Bereits eingeloggt? → Home
    $user = get_logged_in_user();
    if ($user) {
        redirect(get_base_url() . '/index.php');
    }
    require __DIR__ . '/views/login.php';
    exit;
}

// ── Teilnehmer-Registrierung ────────────────────────────────
if (isset($_GET['invite'])) {
    require __DIR__ . '/views/register.php';
    exit;
}

// ── Admin-Einladung annehmen ────────────────────────────────
if (isset($_GET['admin_invite'])) {
    require __DIR__ . '/views/accept_admin_invite.php';
    exit;
}

// ── Event-Routing ───────────────────────────────────────────
$eventToken = $_GET['event'] ?? '';

if (!empty($eventToken)) {
    $event = get_event_by_public_token($eventToken);
    if (!$event) {
        http_response_code(404);
        $errorMessage = 'Event nicht gefunden.';
        require __DIR__ . '/views/partials/error.php';
        exit;
    }

    // Auth prüfen
    $user = get_logged_in_user();
    if (!$user) {
        // Nicht eingeloggt → Landing-Page
        require __DIR__ . '/views/landing.php';
        exit;
    }

    // Berechtigung prüfen: User muss Teilnehmer oder Admin des Events sein
    $isEventAdmin = has_event_role($user['id'], $event['id'], ['admin']);
    $isEventMember = has_event_role($user['id'], $event['id'], ['member']);
    $isServerAdmin = is_server_admin($user['id']);

    if (!$isEventAdmin && !$isEventMember && !$isServerAdmin) {
        http_response_code(403);
        $errorMessage = 'Du bist nicht für dieses Event registriert.';
        require __DIR__ . '/views/partials/error.php';
        exit;
    }

    $isArchived = ($event['status'] === 'archived');
    $isAdmin = $isEventAdmin || $isServerAdmin;
    $memberId = isset($_GET['member']) ? (int)$_GET['member'] : 0;

    // ── Admin-Ansicht ───────────────────────────────────────
    if (isset($_GET['admin_view']) && $isAdmin) {
        require __DIR__ . '/views/admin.php';
        exit;
    }

    // ── Teilnehmer-Detail ───────────────────────────────────
    if ($memberId > 0) {
        $member = get_member($memberId);
        if (!$member || $member['event_id'] != $event['id']) {
            http_response_code(404);
            $errorMessage = 'Teilnehmer nicht gefunden.';
            require __DIR__ . '/views/partials/error.php';
            exit;
        }

        // Nur eigene Seite oder Admin
        if (!$isAdmin) {
            $linkedMember = get_linked_member($user['id'], $event['id']);
            if (!$linkedMember || $linkedMember['id'] !== $member['id']) {
                http_response_code(403);
                $errorMessage = 'Du kannst nur deine eigene Detailseite aufrufen.';
                require __DIR__ . '/views/partials/error.php';
                exit;
            }
        }

        require __DIR__ . '/views/member.php';
        exit;
    }

    // ── Dashboard ───────────────────────────────────────────
    require __DIR__ . '/views/dashboard.php';
    exit;
}

// ── Kein Parameter: Home oder Login ─────────────────────────
$user = get_logged_in_user();
if ($user) {
    require __DIR__ . '/views/home.php';
} else {
    // Login-Seite zeigen
    require __DIR__ . '/views/login.php';
}
