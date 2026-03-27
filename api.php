<?php
/**
 * BOS-Score – API-Endpunkte
 * Alle Endpunkte sind CSRF-geschützt und erfordern Auth wo nötig.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/auth.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// CSRF-Prüfung für alle POST-Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        json_response(['success' => false, 'message' => 'Ungültige Anfrage (CSRF).'], 403);
    }
}

switch ($action) {

    // ═══════════════════════════════════════════════════════
    // Authentifizierung
    // ═══════════════════════════════════════════════════════

    case 'request_magic_link':
        $email = trim(strtolower($_POST['email'] ?? ''));
        $rememberMe = !empty($_POST['remember_me']);

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(['success' => false, 'message' => 'Ungültige E-Mail.'], 400);
        }

        if (!check_rate_limit($email, 'magic_link', RATE_LIMIT_MAGIC_LINKS_PER_HOUR)) {
            json_response(['success' => false, 'message' => 'Zu viele Anfragen.'], 429);
        }

        require_once __DIR__ . '/lib/mail.php';

        $user = get_user_by_email($email);
        if ($user) {
            $token = create_magic_link($user['id'], 'login', $rememberMe);
            send_magic_link_mail($user['email'], $user['display_name'], $token, 'login');
        }

        // Immer Erfolg (E-Mail-Enumeration verhindern)
        json_response(['success' => true, 'message' => 'Falls ein Account existiert, wurde ein Link gesendet.']);
        break;

    // ═══════════════════════════════════════════════════════
    // Event-Admin: Einladungslink-Verwaltung
    // ═══════════════════════════════════════════════════════

    case 'create_invitation':
        $user = require_auth();
        $eventId = (int)($_POST['event_id'] ?? 0);
        if (!has_event_role($user['id'], $eventId, ['admin']) && !is_server_admin($user['id'])) {
            json_response(['success' => false, 'message' => 'Keine Berechtigung.'], 403);
        }

        $regMode = $_POST['reg_mode'] ?? 'open';
        $regUntil = $_POST['reg_until'] ?? null;
        if ($regMode === 'until_date' && empty($regUntil)) $regMode = 'open';

        $token = create_event_invitation($eventId, $regMode, $regUntil);
        $inviteUrl = get_base_url() . '/index.php?invite=' . $token;

        audit_log($eventId, $user['id'], 'invitation_created', "Einladungslink erstellt (Modus: $regMode)");

        json_response(['success' => true, 'token' => $token, 'url' => $inviteUrl]);
        break;

    case 'invalidate_invitation':
        $user = require_auth();
        $invitationId = (int)($_POST['invitation_id'] ?? 0);
        $eventId = (int)($_POST['event_id'] ?? 0);

        if (!has_event_role($user['id'], $eventId, ['admin']) && !is_server_admin($user['id'])) {
            json_response(['success' => false, 'message' => 'Keine Berechtigung.'], 403);
        }

        invalidate_event_invitation($invitationId);
        audit_log($eventId, $user['id'], 'invitation_invalidated', "Einladungslink deaktiviert (#$invitationId)");

        json_response(['success' => true, 'message' => 'Einladungslink deaktiviert.']);
        break;

    // ═══════════════════════════════════════════════════════
    // Event-Admin: Admin-Einladung
    // ═══════════════════════════════════════════════════════

    case 'invite_admin':
        $user = require_auth();
        $eventId = (int)($_POST['event_id'] ?? 0);
        $email = trim(strtolower($_POST['email'] ?? ''));

        if (!has_event_role($user['id'], $eventId, ['admin']) && !is_server_admin($user['id'])) {
            json_response(['success' => false, 'message' => 'Keine Berechtigung.'], 403);
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(['success' => false, 'message' => 'Ungültige E-Mail.'], 400);
        }

        require_once __DIR__ . '/lib/mail.php';

        $event = get_event_by_id($eventId);
        $token = create_admin_invitation($eventId, $email, $user['id']);
        send_admin_invitation_mail($email, $event['name'], $token, $user['display_name']);

        audit_log($eventId, $user['id'], 'admin_invited', "Admin eingeladen: $email");

        json_response(['success' => true, 'message' => "Einladung an $email gesendet."]);
        break;

    // ═══════════════════════════════════════════════════════
    // Event-Admin: Registrierungen verwalten
    // ═══════════════════════════════════════════════════════

    case 'confirm_registration':
        $user = require_auth();
        $regId = (int)($_POST['registration_id'] ?? 0);
        $eventId = (int)($_POST['event_id'] ?? 0);

        if (!has_event_role($user['id'], $eventId, ['admin']) && !is_server_admin($user['id'])) {
            json_response(['success' => false, 'message' => 'Keine Berechtigung.'], 403);
        }

        require_once __DIR__ . '/lib/mail.php';

        $stmt = get_pdo()->prepare("SELECT * FROM user_registrations WHERE id = ? AND event_id = ? AND status = 'pending'");
        $stmt->execute([$regId, $eventId]);
        $reg = $stmt->fetch();

        if (!$reg) {
            json_response(['success' => false, 'message' => 'Registrierung nicht gefunden.'], 404);
        }

        confirm_registration($regId);

        // Account anlegen falls nötig
        $existingUser = get_user_by_email($reg['email']);
        if (!$existingUser) {
            $newUserId = create_user_account($reg['email'], $reg['name']);
            log_consent($newUserId, PRIVACY_VERSION, '');
        } else {
            $newUserId = $existingUser['id'];
        }

        add_event_role($eventId, $newUserId, 'member', $user['id']);

        // Member anlegen und verknüpfen
        $memberId = create_member($eventId, $reg['name'], $reg['email']);
        link_member_account($memberId, $newUserId);
        auto_link_member_by_email($newUserId, $eventId);

        // Magic Link senden
        $token = create_magic_link($newUserId, 'registration', false);
        send_magic_link_mail($reg['email'], $reg['name'], $token, 'registration');

        audit_log($eventId, $user['id'], 'registration_confirmed', "Registrierung bestätigt: {$reg['name']}");

        json_response(['success' => true, 'message' => "Registrierung von {$reg['name']} bestätigt."]);
        break;

    case 'reject_registration':
        $user = require_auth();
        $regId = (int)($_POST['registration_id'] ?? 0);
        $eventId = (int)($_POST['event_id'] ?? 0);

        if (!has_event_role($user['id'], $eventId, ['admin']) && !is_server_admin($user['id'])) {
            json_response(['success' => false, 'message' => 'Keine Berechtigung.'], 403);
        }

        $stmt = get_pdo()->prepare("SELECT * FROM user_registrations WHERE id = ? AND event_id = ?");
        $stmt->execute([$regId, $eventId]);
        $reg = $stmt->fetch();

        if ($reg) {
            reject_registration($regId);
            audit_log($eventId, $user['id'], 'registration_rejected', "Registrierung abgelehnt: {$reg['name']}");
        }

        json_response(['success' => true, 'message' => 'Registrierung abgelehnt.']);
        break;

    // ═══════════════════════════════════════════════════════
    // Event-Admin: Manueller Member-Link
    // ═══════════════════════════════════════════════════════

    case 'link_member':
        $user = require_auth();
        $memberId = (int)($_POST['member_id'] ?? 0);
        $userAccountId = (int)($_POST['user_account_id'] ?? 0);
        $eventId = (int)($_POST['event_id'] ?? 0);

        if (!has_event_role($user['id'], $eventId, ['admin']) && !is_server_admin($user['id'])) {
            json_response(['success' => false, 'message' => 'Keine Berechtigung.'], 403);
        }

        link_member_account($memberId, $userAccountId);
        audit_log($eventId, $user['id'], 'member_linked', "Member #$memberId mit Account #$userAccountId verknüpft");

        json_response(['success' => true, 'message' => 'Verknüpfung erstellt.']);
        break;

    // ═══════════════════════════════════════════════════════
    // Bestehende Endpunkte (mit Auth-Gate)
    // ═══════════════════════════════════════════════════════

    case 'set_attendance':
        $user = require_auth();
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $memberId = (int)($_POST['member_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $eventId = (int)($_POST['event_id'] ?? 0);

        if (!has_event_role($user['id'], $eventId, ['admin'])) {
            json_response(['success' => false, 'message' => 'Keine Berechtigung.'], 403);
        }

        if (!in_array($status, ['present', 'excused', 'absent'])) {
            json_response(['success' => false, 'message' => 'Ungültiger Status.'], 400);
        }

        set_attendance($sessionId, $memberId, $status, 'admin');
        audit_log($eventId, $user['id'], 'attendance', "Anwesenheit gesetzt: Member #$memberId = $status");

        json_response(['success' => true]);
        break;

    case 'member_excuse':
        $user = require_auth();
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $memberId = (int)($_POST['member_id'] ?? 0);
        $eventId = (int)($_POST['event_id'] ?? 0);

        // Nur eigener Member
        $linked = get_linked_member($user['id'], $eventId);
        if (!$linked || $linked['id'] !== $memberId) {
            json_response(['success' => false, 'message' => 'Keine Berechtigung.'], 403);
        }

        $result = member_excuse($sessionId, $memberId);
        if ($result['success']) {
            audit_log($eventId, $user['id'], 'self_excuse', "Selbst-Entschuldigung: Termin #$sessionId");
        }
        json_response($result);
        break;

    case 'member_withdraw_excuse':
        $user = require_auth();
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $memberId = (int)($_POST['member_id'] ?? 0);
        $eventId = (int)($_POST['event_id'] ?? 0);

        $linked = get_linked_member($user['id'], $eventId);
        if (!$linked || $linked['id'] !== $memberId) {
            json_response(['success' => false, 'message' => 'Keine Berechtigung.'], 403);
        }

        $result = member_withdraw_excuse($sessionId, $memberId);
        if ($result['success']) {
            audit_log($eventId, $user['id'], 'withdraw_excuse', "Entschuldigung zurückgezogen: Termin #$sessionId");
        }
        json_response($result);
        break;

    default:
        json_response(['success' => false, 'message' => 'Unbekannte Aktion.'], 400);
}
