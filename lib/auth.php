<?php
/**
 * BOS-Score – Authentifizierung & Session-Verwaltung
 *
 * Enthält alle Funktionen für das Magic-Link- und Session-basierte Auth-System.
 * DSGVO-konform: IPs und User-Agents werden ausschließlich als Hash gespeichert.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

// ═══════════════════════════════════════════════════════════
// Session-Management
// ═══════════════════════════════════════════════════════════

/**
 * Prüft den Auth-Cookie und gibt den eingeloggten User zurück.
 * Aktualisiert last_seen_at bei jedem Aufruf.
 * Gibt null zurück wenn nicht eingeloggt oder Session abgelaufen/widerrufen.
 */
function get_logged_in_user(): ?array {
    static $cachedUser = null;
    static $checked = false;

    if ($checked) return $cachedUser;
    $checked = true;

    $sessionToken = $_COOKIE[AUTH_COOKIE_NAME] ?? '';
    if (empty($sessionToken)) return null;

    $tokenHash = hash_value($sessionToken);

    $stmt = get_pdo()->prepare("
        SELECT us.*, ua.id as user_id, ua.email, ua.display_name, ua.deleted_at, ua.consent_version
        FROM user_sessions us
        JOIN user_accounts ua ON us.user_account_id = ua.id
        WHERE us.session_token_hash = ?
          AND us.revoked_at IS NULL
          AND us.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$tokenHash]);
    $session = $stmt->fetch();

    if (!$session) {
        // Ungültiger/abgelaufener Cookie → löschen
        clear_auth_cookie();
        return null;
    }

    // Soft-deleted User?
    if ($session['deleted_at'] !== null) {
        clear_auth_cookie();
        return null;
    }

    // last_seen_at aktualisieren (max. alle 5 Minuten um DB-Last zu reduzieren)
    $lastSeen = $session['last_seen_at'] ? new DateTime($session['last_seen_at']) : null;
    $now = new DateTime();
    if (!$lastSeen || $now->getTimestamp() - $lastSeen->getTimestamp() > 300) {
        $updateStmt = get_pdo()->prepare("UPDATE user_sessions SET last_seen_at = NOW() WHERE id = ?");
        $updateStmt->execute([$session['id']]);
    }

    $cachedUser = [
        'id'               => (int)$session['user_id'],
        'email'            => $session['email'],
        'display_name'     => $session['display_name'],
        'consent_version'  => $session['consent_version'],
        'session_id'       => (int)$session['id'],
    ];

    return $cachedUser;
}

/**
 * Auth-Gate: Leitet auf Login-Seite weiter wenn nicht eingeloggt.
 * Optional: Redirect-URL nach erfolgreichem Login.
 */
function require_auth(?string $redirectAfterLogin = null): array {
    $user = get_logged_in_user();
    if (!$user) {
        $returnTo = $redirectAfterLogin ?? ($_SERVER['REQUEST_URI'] ?? '');
        $loginUrl = get_base_url() . '/index.php?login&return_to=' . urlencode($returnTo);
        redirect($loginUrl);
    }

    // Datenschutzversion prüfen
    if ($user['consent_version'] !== PRIVACY_VERSION) {
        $consentUrl = get_base_url() . '/index.php?consent_required&return_to=' . urlencode($_SERVER['REQUEST_URI'] ?? '');
        redirect($consentUrl);
    }

    return $user;
}

/**
 * Prüft ob der eingeloggte User eine bestimmte Rolle für ein Event hat.
 * $eventId = null prüft globale Rollen (z.B. server_admin).
 * $roles = Array erlaubter Rollen.
 */
function require_event_role(?int $eventId, array $roles): array {
    $user = require_auth();

    if (!has_event_role($user['id'], $eventId, $roles)) {
        http_response_code(403);
        $errorMessage = 'Du hast keine Berechtigung für diese Seite.';
        require __DIR__ . '/../views/partials/error.php';
        exit;
    }

    return $user;
}

/**
 * Prüft ob ein User eine bestimmte Rolle hat (ohne Redirect).
 */
function has_event_role(int $userId, ?int $eventId, array $roles): bool {
    if ($eventId === null) {
        // Globale Rolle (server_admin)
        $stmt = get_pdo()->prepare("
            SELECT COUNT(*) FROM event_roles
            WHERE user_account_id = ? AND event_id IS NULL AND role IN (" . implode(',', array_fill(0, count($roles), '?')) . ")
        ");
        $stmt->execute(array_merge([$userId], $roles));
    } else {
        $stmt = get_pdo()->prepare("
            SELECT COUNT(*) FROM event_roles
            WHERE user_account_id = ? AND event_id = ? AND role IN (" . implode(',', array_fill(0, count($roles), '?')) . ")
        ");
        $stmt->execute(array_merge([$userId, $eventId], $roles));
    }
    return (int)$stmt->fetchColumn() > 0;
}

/**
 * Prüft ob ein User Server-Admin ist.
 */
function is_server_admin(int $userId): bool {
    return has_event_role($userId, null, ['server_admin']);
}

/**
 * Erstellt eine neue Session nach Magic-Link-Login.
 * Setzt den Auth-Cookie.
 */
function create_session(int $userId, bool $rememberMe): string {
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash_value($token);

    if ($rememberMe) {
        $expiresAt = (new DateTime())->modify('+' . SESSION_LIFETIME_DAYS . ' days')->format('Y-m-d H:i:s');
        $cookieLifetime = time() + (SESSION_LIFETIME_DAYS * 86400);
    } else {
        // Browser-Session: läuft serverseitig nach 24h ab als Sicherheitsnetz
        $expiresAt = (new DateTime())->modify('+24 hours')->format('Y-m-d H:i:s');
        $cookieLifetime = 0; // Browser-Session
    }

    $ipHash = hash_value($_SERVER['REMOTE_ADDR'] ?? '');
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $uaHash = hash_value($ua);
    $deviceLabel = parse_device_label($ua);

    $stmt = get_pdo()->prepare("
        INSERT INTO user_sessions (user_account_id, session_token_hash, expires_at, last_seen_at, ip_hash, user_agent_hash, device_label)
        VALUES (?, ?, ?, NOW(), ?, ?, ?)
    ");
    $stmt->execute([$userId, $tokenHash, $expiresAt, $ipHash, $uaHash, $deviceLabel]);

    // Cookie setzen
    $secure = is_https();
    setcookie(AUTH_COOKIE_NAME, $token, [
        'expires'  => $cookieLifetime,
        'path'     => '/',
        'secure'   => $secure,
        'httponly'  => true,
        'samesite' => 'Strict',
    ]);

    return $token;
}

/**
 * Widerruft eine einzelne Session.
 */
function revoke_session(int $sessionId, int $userId): bool {
    $stmt = get_pdo()->prepare("UPDATE user_sessions SET revoked_at = NOW() WHERE id = ? AND user_account_id = ?");
    $stmt->execute([$sessionId, $userId]);
    return $stmt->rowCount() > 0;
}

/**
 * Widerruft alle Sessions eines Users, optional außer der aktuellen.
 */
function revoke_all_sessions(int $userId, ?int $exceptSessionId = null): int {
    if ($exceptSessionId) {
        $stmt = get_pdo()->prepare("UPDATE user_sessions SET revoked_at = NOW() WHERE user_account_id = ? AND id != ? AND revoked_at IS NULL");
        $stmt->execute([$userId, $exceptSessionId]);
    } else {
        $stmt = get_pdo()->prepare("UPDATE user_sessions SET revoked_at = NOW() WHERE user_account_id = ? AND revoked_at IS NULL");
        $stmt->execute([$userId]);
    }
    return $stmt->rowCount();
}

/**
 * Gibt alle aktiven Sessions eines Users zurück.
 */
function get_active_sessions(int $userId): array {
    $stmt = get_pdo()->prepare("
        SELECT id, created_at, expires_at, last_seen_at, device_label
        FROM user_sessions
        WHERE user_account_id = ? AND revoked_at IS NULL AND expires_at > NOW()
        ORDER BY last_seen_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Löscht den Auth-Cookie.
 */
function clear_auth_cookie(): void {
    setcookie(AUTH_COOKIE_NAME, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly'  => true,
        'samesite' => 'Strict',
    ]);
}

/**
 * Logout: Session widerrufen und Cookie löschen.
 */
function logout(): void {
    $user = get_logged_in_user();
    if ($user) {
        revoke_session($user['session_id'], $user['id']);
    }
    clear_auth_cookie();
}


// ═══════════════════════════════════════════════════════════
// Magic Links
// ═══════════════════════════════════════════════════════════

/**
 * Erstellt einen Magic Link und gibt den Klartext-Token zurück.
 * In der DB wird nur der SHA-256-Hash gespeichert.
 */
function create_magic_link(int $userId, string $purpose = 'login', bool $rememberMe = false): string {
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash_value($token);
    $expiresAt = (new DateTime())->modify('+' . MAGIC_LINK_EXPIRY_MINUTES . ' minutes')->format('Y-m-d H:i:s');
    $ipHash = hash_value($_SERVER['REMOTE_ADDR'] ?? '');

    $stmt = get_pdo()->prepare("
        INSERT INTO magic_links (user_account_id, token_hash, purpose, remember_me, expires_at, ip_hash)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $tokenHash, $purpose, $rememberMe ? 1 : 0, $expiresAt, $ipHash]);

    return $token;
}

/**
 * Validiert und konsumiert einen Magic Link.
 * Gibt [user_id, remember_me] zurück oder null bei Fehler.
 */
function consume_magic_link(string $token): ?array {
    $tokenHash = hash_value($token);

    $stmt = get_pdo()->prepare("
        SELECT * FROM magic_links
        WHERE token_hash = ?
          AND used_at IS NULL
          AND expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$tokenHash]);
    $link = $stmt->fetch();

    if (!$link) return null;

    // Als verwendet markieren (Single-Use)
    $updateStmt = get_pdo()->prepare("UPDATE magic_links SET used_at = NOW() WHERE id = ?");
    $updateStmt->execute([$link['id']]);

    return [
        'user_id'     => (int)$link['user_account_id'],
        'remember_me' => (bool)$link['remember_me'],
        'purpose'     => $link['purpose'],
    ];
}


// ═══════════════════════════════════════════════════════════
// Rate-Limiting
// ═══════════════════════════════════════════════════════════

/**
 * Prüft ob das Rate-Limit erreicht ist.
 * Loggt den Versuch automatisch.
 * Gibt true zurück wenn der Request erlaubt ist, false wenn blockiert.
 */
function check_rate_limit(string $identifier, string $action, int $maxAttempts, int $windowMinutes = 60): bool {
    $identifierHash = hash_value($identifier);

    // Alte Einträge aufräumen (älter als 24h)
    get_pdo()->prepare("DELETE FROM rate_limits WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)")->execute();

    // Aktuelle Versuche zählen
    $stmt = get_pdo()->prepare("
        SELECT COUNT(*) FROM rate_limits
        WHERE identifier_hash = ? AND action = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
    ");
    $stmt->execute([$identifierHash, $action, $windowMinutes]);
    $count = (int)$stmt->fetchColumn();

    if ($count >= $maxAttempts) {
        return false; // Blockiert
    }

    // Versuch loggen
    $logStmt = get_pdo()->prepare("INSERT INTO rate_limits (identifier_hash, action) VALUES (?, ?)");
    $logStmt->execute([$identifierHash, $action]);

    return true; // Erlaubt
}


// ═══════════════════════════════════════════════════════════
// Einladungen
// ═══════════════════════════════════════════════════════════

/**
 * Erstellt einen Teilnehmer-Einladungslink für ein Event.
 */
function create_event_invitation(int $eventId, string $regMode = 'open', ?string $regUntil = null): string {
    $token = bin2hex(random_bytes(16));

    $stmt = get_pdo()->prepare("
        INSERT INTO event_invitations (event_id, token, reg_mode, reg_until)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$eventId, $token, $regMode, $regUntil]);

    return $token;
}

/**
 * Validiert einen Einladungslink. Prüft Gültigkeit und Registrierungsfenster.
 * Gibt Einladungs-Array zurück oder null.
 */
function validate_event_invitation(string $token): ?array {
    $stmt = get_pdo()->prepare("
        SELECT ei.*, e.name as event_name, e.auto_confirm_registration, e.theme_primary
        FROM event_invitations ei
        JOIN events e ON ei.event_id = e.id
        WHERE ei.token = ?
          AND ei.invalidated_at IS NULL
          AND e.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $invitation = $stmt->fetch();

    if (!$invitation) return null;

    // Registrierungsmodus prüfen
    if ($invitation['reg_mode'] === 'closed') return null;
    if ($invitation['reg_mode'] === 'until_date' && $invitation['reg_until']) {
        if (new DateTime() > new DateTime($invitation['reg_until'])) return null;
    }

    return $invitation;
}

/**
 * Invalidiert einen Einladungslink.
 */
function invalidate_event_invitation(int $invitationId): void {
    $stmt = get_pdo()->prepare("UPDATE event_invitations SET invalidated_at = NOW() WHERE id = ?");
    $stmt->execute([$invitationId]);
}

/**
 * Gibt alle Einladungslinks für ein Event zurück.
 */
function get_event_invitations(int $eventId): array {
    $stmt = get_pdo()->prepare("SELECT * FROM event_invitations WHERE event_id = ? ORDER BY created_at DESC");
    $stmt->execute([$eventId]);
    return $stmt->fetchAll();
}

/**
 * Erstellt eine Admin-Einladung für ein Event.
 */
function create_admin_invitation(int $eventId, string $email, ?int $invitedBy = null): string {
    $token = bin2hex(random_bytes(16));

    $stmt = get_pdo()->prepare("
        INSERT INTO admin_invitations (event_id, email, token, invited_by)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$eventId, strtolower($email), $token, $invitedBy]);

    return $token;
}

/**
 * Validiert eine Admin-Einladung.
 */
function validate_admin_invitation(string $token): ?array {
    $stmt = get_pdo()->prepare("
        SELECT ai.*, e.name as event_name
        FROM admin_invitations ai
        JOIN events e ON ai.event_id = e.id
        WHERE ai.token = ?
          AND ai.accepted_at IS NULL
          AND ai.invalidated_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}

/**
 * Markiert eine Admin-Einladung als angenommen.
 */
function accept_admin_invitation(int $invitationId): void {
    $stmt = get_pdo()->prepare("UPDATE admin_invitations SET accepted_at = NOW() WHERE id = ?");
    $stmt->execute([$invitationId]);
}


// ═══════════════════════════════════════════════════════════
// User-Account-Verwaltung
// ═══════════════════════════════════════════════════════════

/**
 * Legt einen neuen User-Account an.
 */
function create_user_account(string $email, string $displayName): int {
    $stmt = get_pdo()->prepare("
        INSERT INTO user_accounts (email, display_name, consent_given_at, consent_version)
        VALUES (?, ?, NOW(), ?)
    ");
    $stmt->execute([strtolower($email), $displayName, PRIVACY_VERSION]);
    return (int)get_pdo()->lastInsertId();
}

/**
 * Gibt einen User nach E-Mail zurück (nur aktive, nicht gelöschte).
 */
function get_user_by_email(string $email): ?array {
    $stmt = get_pdo()->prepare("SELECT * FROM user_accounts WHERE email = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([strtolower($email)]);
    return $stmt->fetch() ?: null;
}

/**
 * Gibt einen User nach ID zurück.
 */
function get_user_by_id(int $id): ?array {
    $stmt = get_pdo()->prepare("SELECT * FROM user_accounts WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/**
 * Aktualisiert den Anzeigenamen eines Users.
 */
function update_display_name(int $userId, string $newName): void {
    $stmt = get_pdo()->prepare("UPDATE user_accounts SET display_name = ? WHERE id = ?");
    $stmt->execute([trim($newName), $userId]);
}

/**
 * Soft-Delete: Markiert den Account als gelöscht.
 */
function soft_delete_user(int $userId): void {
    $stmt = get_pdo()->prepare("UPDATE user_accounts SET deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$userId]);

    // Alle Sessions widerrufen
    revoke_all_sessions($userId);
}

/**
 * Gibt alle Events zurück, an denen ein User teilnimmt (mit Rolle).
 */
function get_user_events(int $userId): array {
    $stmt = get_pdo()->prepare("
        SELECT e.*, er.role
        FROM event_roles er
        JOIN events e ON er.event_id = e.id
        WHERE er.user_account_id = ?
          AND er.event_id IS NOT NULL
        ORDER BY e.status ASC, e.created_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Gibt alle Rollen eines Users zurück (inkl. globale).
 */
function get_user_roles(int $userId): array {
    $stmt = get_pdo()->prepare("
        SELECT er.*, e.name as event_name
        FROM event_roles er
        LEFT JOIN events e ON er.event_id = e.id
        WHERE er.user_account_id = ?
        ORDER BY er.granted_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}


// ═══════════════════════════════════════════════════════════
// Event-Rollen
// ═══════════════════════════════════════════════════════════

/**
 * Weist einem User eine Rolle für ein Event zu.
 */
function add_event_role(?int $eventId, int $userId, string $role, ?int $grantedBy = null): void {
    $stmt = get_pdo()->prepare("
        INSERT IGNORE INTO event_roles (event_id, user_account_id, role, granted_by)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$eventId, $userId, $role, $grantedBy]);
}

/**
 * Gibt alle Admins eines Events zurück.
 */
function get_event_admins(int $eventId): array {
    $stmt = get_pdo()->prepare("
        SELECT ua.id, ua.email, ua.display_name, er.granted_at
        FROM event_roles er
        JOIN user_accounts ua ON er.user_account_id = ua.id
        WHERE er.event_id = ? AND er.role = 'admin'
          AND ua.deleted_at IS NULL
        ORDER BY er.granted_at ASC
    ");
    $stmt->execute([$eventId]);
    return $stmt->fetchAll();
}

/**
 * Gibt alle registrierten Teilnehmer eines Events zurück.
 */
function get_event_registered_members(int $eventId): array {
    $stmt = get_pdo()->prepare("
        SELECT ua.id, ua.email, ua.display_name, er.granted_at
        FROM event_roles er
        JOIN user_accounts ua ON er.user_account_id = ua.id
        WHERE er.event_id = ? AND er.role = 'member'
          AND ua.deleted_at IS NULL
        ORDER BY ua.display_name ASC
    ");
    $stmt->execute([$eventId]);
    return $stmt->fetchAll();
}


// ═══════════════════════════════════════════════════════════
// Member ↔ Account Verknüpfung
// ═══════════════════════════════════════════════════════════

/**
 * Verknüpft einen Member mit einem User-Account.
 */
function link_member_account(int $memberId, int $userId): void {
    $stmt = get_pdo()->prepare("
        INSERT IGNORE INTO member_account_links (member_id, user_account_id)
        VALUES (?, ?)
    ");
    $stmt->execute([$memberId, $userId]);
}

/**
 * Findet den Member-Eintrag eines Users für ein bestimmtes Event.
 */
function get_linked_member(int $userId, int $eventId): ?array {
    $stmt = get_pdo()->prepare("
        SELECT m.* FROM members m
        JOIN member_account_links mal ON m.id = mal.member_id
        WHERE mal.user_account_id = ? AND m.event_id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId, $eventId]);
    return $stmt->fetch() ?: null;
}

/**
 * Versucht automatischen Match: E-Mail des Users mit E-Mail-Feld der Members.
 */
function auto_link_member_by_email(int $userId, int $eventId): ?int {
    $user = get_user_by_id($userId);
    if (!$user) return null;

    $stmt = get_pdo()->prepare("
        SELECT id FROM members
        WHERE event_id = ? AND email = ? AND active = 1
        LIMIT 1
    ");
    $stmt->execute([$eventId, $user['email']]);
    $memberId = $stmt->fetchColumn();

    if ($memberId) {
        link_member_account((int)$memberId, $userId);
        return (int)$memberId;
    }

    return null;
}

/**
 * Gibt unverknüpfte Registrierungen für ein Event zurück (für Admin-Match).
 */
function get_unlinked_registrations(int $eventId): array {
    $stmt = get_pdo()->prepare("
        SELECT ur.*, ua.display_name as account_name
        FROM user_registrations ur
        LEFT JOIN user_accounts ua ON ur.email = ua.email
        WHERE ur.event_id = ? AND ur.status = 'confirmed'
          AND NOT EXISTS (
              SELECT 1 FROM member_account_links mal
              JOIN members m ON mal.member_id = m.id
              WHERE mal.user_account_id = ua.id AND m.event_id = ?
          )
        ORDER BY ur.created_at DESC
    ");
    $stmt->execute([$eventId, $eventId]);
    return $stmt->fetchAll();
}


// ═══════════════════════════════════════════════════════════
// DSGVO-Funktionen
// ═══════════════════════════════════════════════════════════

/**
 * Protokolliert eine Datenschutz-Einwilligung.
 */
function log_consent(int $userId, string $version, string $ipHash): void {
    $stmt = get_pdo()->prepare("
        INSERT INTO consent_log (user_account_id, consent_version, action, ip_hash)
        VALUES (?, ?, 'granted', ?)
    ");
    $stmt->execute([$userId, $version, $ipHash]);

    // User-Account aktualisieren
    $updateStmt = get_pdo()->prepare("UPDATE user_accounts SET consent_given_at = NOW(), consent_version = ? WHERE id = ?");
    $updateStmt->execute([$version, $userId]);
}

/**
 * Gibt die Einwilligungshistorie eines Users zurück.
 */
function get_consent_log(int $userId): array {
    $stmt = get_pdo()->prepare("SELECT * FROM consent_log WHERE user_account_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Exportiert alle Daten eines Users als Array (für JSON-Download, Art. 15/20 DSGVO).
 */
function export_user_data(int $userId): array {
    $user = get_user_by_id($userId);
    if (!$user) return [];

    // Sensible Felder entfernen
    unset($user['id']);

    $roles = get_user_roles($userId);
    $consent = get_consent_log($userId);
    $sessions = get_active_sessions($userId);

    // Verknüpfte Members und deren Anwesenheit
    $stmt = get_pdo()->prepare("
        SELECT m.name, m.role, m.event_id, e.name as event_name
        FROM member_account_links mal
        JOIN members m ON mal.member_id = m.id
        JOIN events e ON m.event_id = e.id
        WHERE mal.user_account_id = ?
    ");
    $stmt->execute([$userId]);
    $memberships = $stmt->fetchAll();

    // Anwesenheitsdaten
    $attendanceData = [];
    foreach ($memberships as $m) {
        $stmt = get_pdo()->prepare("
            SELECT a.status, a.excused_at, s.session_date, s.session_time
            FROM attendance a
            JOIN sessions s ON a.session_id = s.id
            JOIN members mem ON a.member_id = mem.id
            JOIN member_account_links mal ON mem.id = mal.member_id
            WHERE mal.user_account_id = ? AND mem.event_id = ?
            ORDER BY s.session_date
        ");
        $stmt->execute([$userId, $m['event_id']]);
        $attendanceData[$m['event_name']] = $stmt->fetchAll();
    }

    return [
        'export_datum' => date('Y-m-d H:i:s'),
        'account'      => $user,
        'rollen'       => $roles,
        'mitgliedschaften' => $memberships,
        'anwesenheit'  => $attendanceData,
        'einwilligungen' => $consent,
        'aktive_sessions' => $sessions,
    ];
}

/**
 * Gibt die Pending-Registrierungen eines Events zurück.
 */
function get_pending_registrations(int $eventId): array {
    $stmt = get_pdo()->prepare("
        SELECT * FROM user_registrations
        WHERE event_id = ? AND status = 'pending'
        ORDER BY created_at ASC
    ");
    $stmt->execute([$eventId]);
    return $stmt->fetchAll();
}

/**
 * Bestätigt eine Registrierung.
 */
function confirm_registration(int $registrationId): void {
    $stmt = get_pdo()->prepare("UPDATE user_registrations SET status = 'confirmed' WHERE id = ?");
    $stmt->execute([$registrationId]);
}

/**
 * Lehnt eine Registrierung ab.
 */
function reject_registration(int $registrationId): void {
    $stmt = get_pdo()->prepare("UPDATE user_registrations SET status = 'rejected' WHERE id = ?");
    $stmt->execute([$registrationId]);
}
