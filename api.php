<?php
/**
 * LAZ Übungs-Tracker – API-Endpunkte (AJAX)
 */

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$eventToken = $_POST['event_token'] ?? $_GET['event_token'] ?? '';
$adminToken = $_POST['admin_token'] ?? $_GET['admin_token'] ?? '';
$serverToken = $_POST['server_token'] ?? $_GET['server_token'] ?? '';

// CSRF-Prüfung für POST-Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($csrfToken)) {
        json_response(['success' => false, 'message' => 'Ungültiges Sicherheitstoken. Bitte Seite neu laden.'], 403);
    }
}

// ── Server-Admin Aktionen ───────────────────────────────────
$isServerAdmin = verify_server_admin_token($serverToken);

if ($isServerAdmin && in_array($action, ['create_event', 'delete_event', 'save_server_settings'])) {
    try {
        switch ($action) {
            case 'create_event':
                $name = trim($_POST['name'] ?? '');
                $orgName = trim($_POST['organization_name'] ?? '');
                $d2Date = $_POST['deadline_2_date'] ?? '';
                $d2Count = max(1, (int)($_POST['deadline_2_count'] ?? 20));
                $d1Enabled = ($_POST['deadline_1_enabled'] ?? '0') === '1';
                $d1Date = $_POST['deadline_1_date'] ?? '';
                $d1Count = max(1, (int)($_POST['deadline_1_count'] ?? 11));
                $penaltySource = $_POST['penalty_source'] ?? 'default';
                $copyFrom = (int)($_POST['copy_from_event'] ?? 0);

                if (empty($name) || empty($d2Date)) {
                    json_response(['success' => false, 'message' => 'Name und Hauptfrist-Datum sind erforderlich.'], 400);
                }

                $result = create_event($name, $d2Date, $d2Count, $d1Date, $d1Count, $d1Enabled, $orgName);

                // Strafenkatalog
                if ($penaltySource === 'copy' && $copyFrom > 0) {
                    $count = copy_penalty_types($copyFrom, $result['id']);
                } elseif ($penaltySource === 'default') {
                    $defaultPenalties = [
                        ['Zu spät kommen', 5.00, null, 10],
                        ['Unentschuldigtes Fehlen', 10.00, null, 20],
                        ['Versagen von Sprüchen', 1.00, null, 30],
                        ['Rauchen während der Übungsdurchführung', 5.00, null, 40],
                        ['Handynutzung während der Übungsdurchführung', 5.00, null, 50],
                        ['PSA unvollständig', 2.00, null, 60],
                        ['Kurzfristige Absage (< 1h vor Übungsbeginn)', 2.00, null, 70],
                    ];
                    foreach ($defaultPenalties as $p) {
                        create_penalty_type($result['id'], $p[0], $p[1], $p[2], $p[3]);
                    }
                }

                // Audit-Log (verwende das neue Event)
                audit_log($result['id'], null, 'event_create', 'Event "' . $name . '" erstellt (Server-Admin)');

                $baseUrl = get_base_url();
                json_response([
                    'success' => true,
                    'message' => 'Event "' . $name . '" erstellt.',
                    'public_url' => $baseUrl . '/index.php?event=' . $result['public_token'],
                    'admin_url' => $baseUrl . '/index.php?event=' . $result['public_token'] . '&admin=' . $result['admin_token'],
                ]);
                break;

            case 'delete_event':
                $eventId = (int)($_POST['event_id'] ?? 0);
                if (!$eventId) json_response(['success' => false, 'message' => 'Ungültige Event-ID.'], 400);
                $ev = get_event_by_id($eventId);
                if (!$ev) json_response(['success' => false, 'message' => 'Event nicht gefunden.'], 404);
                delete_event($eventId);
                json_response(['success' => true, 'message' => 'Event "' . $ev['name'] . '" gelöscht.']);
                break;

            case 'save_server_settings':
                $newOrgName = trim($_POST['organization_name'] ?? '');
                $adminEmail = trim($_POST['admin_email'] ?? '');
                $showOverview = ($_POST['show_public_overview'] ?? '0') === '1' ? '1' : '0';
                if (!empty($newOrgName)) set_server_config('organization_name', $newOrgName);
                set_server_config('admin_email', $adminEmail);
                set_server_config('show_public_overview', $showOverview);
                json_response(['success' => true, 'message' => 'Einstellungen gespeichert.']);
                break;
        }
    } catch (Exception $e) {
        json_response(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()], 500);
    }
    exit;
}

// ── Event-bezogene Aktionen ─────────────────────────────────
$event = null;
if ($eventToken) {
    $event = get_event_by_public_token($eventToken);
}
if (!$event) {
    json_response(['success' => false, 'message' => 'Ungültiges Event.'], 400);
}

$isAdmin = false;
if ($adminToken) {
    $adminEvent = get_event_by_admin_token($eventToken, $adminToken);
    if ($adminEvent) {
        $isAdmin = true;
        $event = $adminEvent;
    }
}

// Archivierte Events: nur Reaktivierung und Export erlauben
$isArchived = ($event['status'] === 'archived');
if ($isArchived && !in_array($action, ['update_event', 'export_audit_csv'])) {
    json_response(['success' => false, 'message' => 'Dieses Event ist archiviert. Änderungen sind nicht möglich.'], 403);
}

try {
    switch ($action) {

        // ── Teilnehmer entschuldigt sich ────────────────────
        case 'excuse':
            $sessionId = (int)($_POST['session_id'] ?? 0);
            $memberId = (int)($_POST['member_id'] ?? 0);

            if (!$sessionId || !$memberId) {
                json_response(['success' => false, 'message' => 'Ungültige Parameter.'], 400);
            }

            // Prüfe ob Member zum Event gehört
            $member = get_member($memberId);
            if (!$member || $member['event_id'] != $event['id']) {
                json_response(['success' => false, 'message' => 'Teilnehmer nicht gefunden.'], 404);
            }

            $result = member_excuse($sessionId, $memberId);

            if ($result['success']) {
                audit_log($event['id'], $memberId, 'excuse',
                    $member['name'] . ' hat sich für Termin #' . $sessionId . ' entschuldigt' .
                    ($result['short_notice'] ? ' (kurzfristig)' : ''));
            }

            json_response($result);
            break;

        // ── Teilnehmer zieht Entschuldigung zurück ──────────
        case 'withdraw_excuse':
            $sessionId = (int)($_POST['session_id'] ?? 0);
            $memberId = (int)($_POST['member_id'] ?? 0);

            if (!$sessionId || !$memberId) {
                json_response(['success' => false, 'message' => 'Ungültige Parameter.'], 400);
            }

            $member = get_member($memberId);
            if (!$member || $member['event_id'] != $event['id']) {
                json_response(['success' => false, 'message' => 'Teilnehmer nicht gefunden.'], 404);
            }

            $result = member_withdraw_excuse($sessionId, $memberId);

            if ($result['success']) {
                audit_log($event['id'], $memberId, 'withdraw_excuse',
                    $member['name'] . ' hat Entschuldigung für Termin #' . $sessionId . ' zurückgezogen');
            }

            json_response($result);
            break;

        // ── Admin: Anwesenheit speichern ────────────────────
        case 'save_attendance':
            if (!$isAdmin) json_response(['success' => false, 'message' => 'Kein Zugriff.'], 403);

            $sessionId = (int)($_POST['session_id'] ?? 0);
            $attendance = $_POST['attendance'] ?? [];

            if (!$sessionId || !is_array($attendance)) {
                json_response(['success' => false, 'message' => 'Ungültige Parameter.'], 400);
            }

            foreach ($attendance as $memberId => $status) {
                $memberId = (int)$memberId;
                if (empty($status)) {
                    // Leerer Status = Eintrag löschen (zurückgesetzt)
                    get_pdo()->prepare("DELETE FROM attendance WHERE session_id = ? AND member_id = ?")->execute([$sessionId, $memberId]);
                } else {
                    $status = in_array($status, ['present', 'excused', 'absent']) ? $status : 'absent';
                    set_attendance($sessionId, $memberId, $status, 'admin');
                }
            }

            audit_log($event['id'], null, 'attendance', 'Anwesenheit für Termin #' . $sessionId . ' aktualisiert');
            json_response(['success' => true, 'message' => 'Anwesenheit gespeichert.']);
            break;

        // ── Admin: Teilnehmer hinzufügen ────────────────────
        case 'add_member':
            if (!$isAdmin) json_response(['success' => false, 'message' => 'Kein Zugriff.'], 403);

            $name = trim($_POST['name'] ?? '');
            $role = trim($_POST['role'] ?? '');

            if (empty($name)) {
                json_response(['success' => false, 'message' => 'Name darf nicht leer sein.'], 400);
            }

            $id = create_member($event['id'], $name, $role);
            audit_log($event['id'], $id, 'member_add', 'Teilnehmer "' . $name . '" hinzugefügt');
            json_response(['success' => true, 'message' => 'Teilnehmer hinzugefügt.', 'id' => $id]);
            break;

        // ── Admin: Bulk-Import Teilnehmer ───────────────────
        case 'bulk_import_members':
            if (!$isAdmin) json_response(['success' => false, 'message' => 'Kein Zugriff.'], 403);

            $names = trim($_POST['names'] ?? '');
            if (empty($names)) {
                json_response(['success' => false, 'message' => 'Keine Namen angegeben.'], 400);
            }

            $lines = array_filter(array_map('trim', explode("\n", $names)));
            $count = 0;
            foreach ($lines as $name) {
                if (!empty($name)) {
                    create_member($event['id'], $name);
                    $count++;
                }
            }

            audit_log($event['id'], null, 'member_bulk', $count . ' Teilnehmer importiert');
            json_response(['success' => true, 'message' => $count . ' Teilnehmer importiert.', 'count' => $count]);
            break;

        // ── Admin: Teilnehmer bearbeiten ────────────────────
        case 'update_member':
            if (!$isAdmin) json_response(['success' => false, 'message' => 'Kein Zugriff.'], 403);

            $id = (int)($_POST['member_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $role = trim($_POST['role'] ?? '');
            $active = (bool)($_POST['active'] ?? true);

            if (!$id || empty($name)) {
                json_response(['success' => false, 'message' => 'Ungültige Parameter.'], 400);
            }

            update_member($id, $name, $role, $active);
            audit_log($event['id'], $id, 'member_update', 'Teilnehmer "' . $name . '" aktualisiert (aktiv: ' . ($active ? 'ja' : 'nein') . ')');
            json_response(['success' => true, 'message' => 'Teilnehmer aktualisiert.']);
            break;

        // ── Admin: Termin hinzufügen ────────────────────────
        case 'add_session':
            if (!$isAdmin) json_response(['success' => false, 'message' => 'Kein Zugriff.'], 403);

            $date = $_POST['date'] ?? '';
            $time = $_POST['time'] ?? '';
            $comment = trim($_POST['comment'] ?? '');

            if (empty($date) || empty($time)) {
                json_response(['success' => false, 'message' => 'Datum und Uhrzeit sind erforderlich.'], 400);
            }

            $id = create_session($event['id'], $date, $time, $comment);
            audit_log($event['id'], null, 'session_add', 'Termin am ' . $date . ' um ' . $time . ' hinzugefügt');
            json_response(['success' => true, 'message' => 'Termin hinzugefügt.', 'id' => $id]);
            break;

        // ── Admin: Termin bearbeiten ────────────────────────
        case 'update_session':
            if (!$isAdmin) json_response(['success' => false, 'message' => 'Kein Zugriff.'], 403);

            $id = (int)($_POST['session_id'] ?? 0);
            $date = $_POST['date'] ?? '';
            $time = $_POST['time'] ?? '';
            $comment = trim($_POST['comment'] ?? '');

            if (!$id || empty($date) || empty($time)) {
                json_response(['success' => false, 'message' => 'Ungültige Parameter.'], 400);
            }

            update_session($id, $date, $time, $comment);
            audit_log($event['id'], null, 'session_update', 'Termin #' . $id . ' aktualisiert');
            json_response(['success' => true, 'message' => 'Termin aktualisiert.']);
            break;

        // ── Admin: Termin löschen ───────────────────────────
        case 'delete_session':
            if (!$isAdmin) json_response(['success' => false, 'message' => 'Kein Zugriff.'], 403);

            $id = (int)($_POST['session_id'] ?? 0);
            if (!$id) json_response(['success' => false, 'message' => 'Ungültige Parameter.'], 400);

            delete_session($id);
            audit_log($event['id'], null, 'session_delete', 'Termin #' . $id . ' gelöscht');
            json_response(['success' => true, 'message' => 'Termin gelöscht.']);
            break;

        // ── Admin: Bulk-Import Termine ──────────────────────
        case 'bulk_import_sessions':
            if (!$isAdmin) json_response(['success' => false, 'message' => 'Kein Zugriff.'], 403);

            $data = trim($_POST['sessions_data'] ?? '');
            if (empty($data)) {
                json_response(['success' => false, 'message' => 'Keine Daten angegeben.'], 400);
            }

            $lines = array_filter(array_map('trim', explode("\n", $data)));
            $count = 0;
            $errors = [];

            foreach ($lines as $i => $line) {
                // Format: DD.MM. HHMM Kommentar oder DD.MM.YYYY HHMM Kommentar
                if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})?\s+(\d{2}):?(\d{2})\s*(.*)?$/', $line, $m)) {
                    $day = $m[1];
                    $month = $m[2];
                    $year = $m[3] ?: date('Y');
                    $hour = $m[4];
                    $minute = $m[5];
                    $comment = trim($m[6] ?? '');

                    $date = "$year-$month-$day";
                    $time = "$hour:$minute:00";

                    create_session($event['id'], $date, $time, $comment);
                    $count++;
                } else {
                    $errors[] = 'Zeile ' . ($i + 1) . ': Ungültiges Format';
                }
            }

            audit_log($event['id'], null, 'session_bulk', $count . ' Termine importiert');
            $msg = $count . ' Termine importiert.';
            if (!empty($errors)) $msg .= ' Fehler: ' . implode(', ', $errors);
            json_response(['success' => true, 'message' => $msg, 'count' => $count, 'errors' => $errors]);
            break;

        // ── Admin: Straftyp verwalten ───────────────────────
        case 'add_penalty_type':
            if (!$isAdmin) json_response(['success' => false, 'message' => 'Kein Zugriff.'], 403);

            $desc = trim($_POST['description'] ?? '');
            $amount = (float)($_POST['amount'] ?? 0);
            $activeFrom = $_POST['active_from'] ?? null;
            $sortOrder = (int)($_POST['sort_order'] ?? 0);

            if (empty($desc) || $amount <= 0) {
                json_response(['success' => false, 'message' => 'Beschreibung und Betrag sind erforderlich.'], 400);
            }

            if (empty($activeFrom)) $activeFrom = null;

            $id = create_penalty_type($event['id'], $desc, $amount, $activeFrom, $sortOrder);
            audit_log($event['id'], null, 'penalty_type_add', 'Straftyp "' . $desc . '" (' . $amount . '€) hinzugefügt');
            json_response(['success' => true, 'message' => 'Straftyp hinzugefügt.', 'id' => $id]);
            break;

        case 'update_penalty_type':
            if (!$isAdmin) json_response(['success' => false, 'message' => 'Kein Zugriff.'], 403);

            $id = (int)($_POST['penalty_type_id'] ?? 0);
            $desc = trim($_POST['description'] ?? '');
            $amount = (float)($_POST['amount'] ?? 0);
            $activeFrom = $_POST['active_from'] ?? null;
            $active = (bool)($_POST['active'] ?? true);
            $sortOrder = (int)($_POST['sort_order'] ?? 0);

            if (empty($activeFrom)) $activeFrom = null;

            update_penalty_type($id, $desc, $amount, $activeFrom, $active, $sortOrder);
            audit_log($event['id'], null, 'penalty_type_update', 'Straftyp #' . $id . ' aktualisiert');
            json_response(['success' => true, 'message' => 'Straftyp aktualisiert.']);
            break;

        case 'delete_penalty_type':
            if (!$isAdmin) json_response(['success' => false, 'message' => 'Kein Zugriff.'], 403);

            $id = (int)($_POST['penalty_type_id'] ?? 0);
            delete_penalty_type($id);
            audit_log($event['id'], null, 'penalty_type_delete', 'Straftyp #' . $id . ' gelöscht');
            json_response(['success' => true, 'message' => 'Straftyp gelöscht.']);
            break;

        // ── Admin: Strafe zuweisen ──────────────────────────
        case 'add_penalty':
            if (!$isAdmin) json_response(['success' => false, 'message' => 'Kein Zugriff.'], 403);

            $memberId = (int)($_POST['member_id'] ?? 0);
            $typeId = (int)($_POST['penalty_type_id'] ?? 0);
            $date = $_POST['penalty_date'] ?? date('Y-m-d');
            $comment = trim($_POST['comment'] ?? '');

            if (!$memberId || !$typeId) {
                json_response(['success' => false, 'message' => 'Teilnehmer und Straftyp sind erforderlich.'], 400);
            }

            $id = create_penalty($memberId, $typeId, $date, $comment);
            $member = get_member($memberId);
            audit_log($event['id'], $memberId, 'penalty_add', 'Strafe für "' . ($member['name'] ?? '?') . '" zugewiesen (Typ #' . $typeId . ')');
            json_response(['success' => true, 'message' => 'Strafe zugewiesen.', 'id' => $id]);
            break;

        case 'delete_penalty':
            if (!$isAdmin) json_response(['success' => false, 'message' => 'Kein Zugriff.'], 403);

            $id = (int)($_POST['penalty_id'] ?? 0);
            delete_penalty($id);
            audit_log($event['id'], null, 'penalty_delete', 'Strafe #' . $id . ' gelöscht (Soft-Delete)');
            json_response(['success' => true, 'message' => 'Strafe gelöscht.']);
            break;

        // ── Admin: Event aktualisieren ──────────────────────
        case 'update_event':
            if (!$isAdmin) json_response(['success' => false, 'message' => 'Kein Zugriff.'], 403);

            $data = [];
            $data['name'] = trim($_POST['name'] ?? '');
            $data['status'] = in_array($_POST['status'] ?? '', ['active', 'archived']) ? $_POST['status'] : 'active';
            $data['organization_name'] = trim($_POST['organization_name'] ?? '') ?: null;
            $data['deadline_1_date'] = $_POST['deadline_1_date'] ?? '';
            $data['deadline_1_count'] = (int)($_POST['deadline_1_count'] ?? 11);
            $data['deadline_1_name'] = trim($_POST['deadline_1_name'] ?? 'Frist 1');
            $data['deadline_1_enabled'] = ($_POST['deadline_1_enabled'] ?? '1') === '1' ? 1 : 0;
            $data['deadline_2_date'] = $_POST['deadline_2_date'] ?? '';
            $data['deadline_2_count'] = (int)($_POST['deadline_2_count'] ?? 20);
            $data['deadline_2_name'] = trim($_POST['deadline_2_name'] ?? 'Frist 2');
            $data['session_duration_hours'] = max(1, (int)($_POST['session_duration_hours'] ?? 3));

            $wLoc = trim($_POST['weather_location'] ?? '');
            $wLat = (float)($_POST['weather_lat'] ?? 0);
            $wLng = (float)($_POST['weather_lng'] ?? 0);
            if ($wLoc !== '' && $wLat != 0 && $wLng != 0) {
                $data['weather_location'] = $wLoc;
                $data['weather_lat'] = $wLat;
                $data['weather_lng'] = $wLng;
            }

            if (empty($data['name'])) {
                json_response(['success' => false, 'message' => 'Name darf nicht leer sein.'], 400);
            }

            update_event($event['id'], $data);
            audit_log($event['id'], null, 'event_update', 'Event-Einstellungen aktualisiert');
            json_response(['success' => true, 'message' => 'Einstellungen gespeichert.']);
            break;

        // ── Geocoding (Ortsname → Koordinaten) ──────────────
        case 'geocode':
            if (!$isAdmin) json_response(['success' => false, 'message' => 'Kein Zugriff.'], 403);

            $query = trim($_POST['query'] ?? '');
            if (empty($query)) {
                json_response(['success' => false, 'message' => 'Bitte einen Ortsnamen oder PLZ eingeben.'], 400);
            }

            $geoUrl = 'https://geocoding-api.open-meteo.com/v1/search?name=' . urlencode($query) . '&count=5&language=de&format=json';
            $ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
            $geoJson = @file_get_contents($geoUrl, false, $ctx);

            if (!$geoJson) {
                json_response(['success' => false, 'message' => 'Geocoding-Anfrage fehlgeschlagen. Bitte später erneut versuchen.'], 500);
            }

            $geoData = json_decode($geoJson, true);
            $results = [];
            foreach (($geoData['results'] ?? []) as $r) {
                $results[] = [
                    'name' => $r['name'] ?? '',
                    'admin1' => $r['admin1'] ?? '',
                    'country' => $r['country'] ?? '',
                    'lat' => $r['latitude'] ?? 0,
                    'lng' => $r['longitude'] ?? 0,
                ];
            }

            if (empty($results)) {
                json_response(['success' => false, 'message' => 'Kein Ort gefunden für "' . $query . '".']);
            }

            json_response(['success' => true, 'results' => $results]);
            break;

        // ── Admin: Rolle hinzufügen ─────────────────────────
        case 'add_role':
            if (!$isAdmin) json_response(['success' => false, 'message' => 'Kein Zugriff.'], 403);
            $name = trim($_POST['name'] ?? '');
            $sortOrder = (int)($_POST['sort_order'] ?? 0);
            if (empty($name)) json_response(['success' => false, 'message' => 'Rollenname darf nicht leer sein.'], 400);
            create_role($event['id'], $name, $sortOrder);
            audit_log($event['id'], null, 'role_add', 'Rolle "' . $name . '" hinzugefügt');
            json_response(['success' => true, 'message' => 'Rolle "' . $name . '" hinzugefügt.']);
            break;

        // ── Admin: Rolle löschen ────────────────────────────
        case 'delete_role':
            if (!$isAdmin) json_response(['success' => false, 'message' => 'Kein Zugriff.'], 403);
            $roleId = (int)($_POST['role_id'] ?? 0);
            if (!$roleId) json_response(['success' => false, 'message' => 'Ungültige Rolle.'], 400);
            delete_role($roleId);
            audit_log($event['id'], null, 'role_delete', 'Rolle gelöscht');
            json_response(['success' => true, 'message' => 'Rolle gelöscht.']);
            break;

        // ── Admin: Rollen eines Teilnehmers setzen ──────────
        case 'set_member_roles':
            if (!$isAdmin) json_response(['success' => false, 'message' => 'Kein Zugriff.'], 403);
            $memberId = (int)($_POST['member_id'] ?? 0);
            $roleIds = isset($_POST['role_ids']) ? array_map('intval', (array)$_POST['role_ids']) : [];
            if (!$memberId) json_response(['success' => false, 'message' => 'Ungültiger Teilnehmer.'], 400);
            set_member_roles($memberId, $roleIds);
            $member = get_member($memberId);
            audit_log($event['id'], $memberId, 'role_assign', 'Rollen aktualisiert für ' . ($member['name'] ?? ''));
            json_response(['success' => true, 'message' => 'Rollen aktualisiert.']);
            break;

        // ── Admin: Rollen aktivieren/deaktivieren ───────────
        case 'toggle_roles':
            if (!$isAdmin) json_response(['success' => false, 'message' => 'Kein Zugriff.'], 403);
            $enabled = ($_POST['enabled'] ?? '0') === '1' ? 1 : 0;
            update_event($event['id'], ['roles_enabled' => $enabled]);
            audit_log($event['id'], null, 'event_update', 'Rollen ' . ($enabled ? 'aktiviert' : 'deaktiviert'));
            json_response(['success' => true, 'message' => 'Rollen ' . ($enabled ? 'aktiviert' : 'deaktiviert') . '.']);
            break;

        // ── Admin: Audit-Log CSV Export ─────────────────────
        case 'export_audit_csv':
            if (!$isAdmin) json_response(['success' => false, 'message' => 'Kein Zugriff.'], 403);

            $logs = get_audit_log($event['id'], null, null, 10000);

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="audit_log_' . date('Y-m-d') . '.csv"');

            $out = fopen('php://output', 'w');
            fputcsv($out, ['Zeitpunkt', 'Teilnehmer', 'Aktion', 'Beschreibung', 'IP-Adresse'], ';');
            foreach ($logs as $log) {
                fputcsv($out, [
                    $log['created_at'],
                    $log['member_name'] ?? '-',
                    $log['action_type'],
                    $log['action_description'],
                    $log['ip_address'],
                ], ';');
            }
            fclose($out);
            exit;

        default:
            json_response(['success' => false, 'message' => 'Unbekannte Aktion: ' . $action], 400);
    }
} catch (PDOException $e) {
    json_response(['success' => false, 'message' => 'Datenbankfehler: ' . (DEBUG_MODE ? $e->getMessage() : 'Bitte später erneut versuchen.')], 500);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()], 500);
}
