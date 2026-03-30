<?php
/**
 * BOS-Score – E-Mail-Versand
 *
 * Nutzt PHPMailer für SMTP-Versand.
 * Alle E-Mails: Deutsch, Plaintext, DSGVO-konform.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';
require_once __DIR__ . '/phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Erstellt und konfiguriert eine PHPMailer-Instanz.
 */
function create_mailer(): PHPMailer {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = SMTP_ENCRYPTION;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);

    return $mail;
}

/**
 * Sendet eine E-Mail. Gibt true bei Erfolg zurück.
 */
function send_mail(string $to, string $subject, string $body): bool {
    try {
        $mail = create_mailer();
        $mail->addAddress($to);
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->send();
        return true;
    } catch (Exception $e) {
        if (DEBUG_MODE) {
            error_log('BOS-Score Mail-Fehler: ' . $e->getMessage());
        }
        return false;
    }
}

// ═══════════════════════════════════════════════════════════
// E-Mail-Templates
// ═══════════════════════════════════════════════════════════

/**
 * Sendet den Magic Link zum Anmelden.
 */
function send_magic_link_mail(string $email, string $name, string $token, string $purpose = 'login'): bool {
    $baseUrl = get_base_url();
    $magicUrl = $baseUrl . '/index.php?magic=' . $token;
    $appName = APP_NAME;
    $expiry = MAGIC_LINK_EXPIRY_MINUTES;

    if ($purpose === 'registration') {
        $subject = "$appName – Registrierung abschließen";
        $body = <<<EOT
Hallo $name,

willkommen bei $appName! Klicke auf den folgenden Link, um deine Registrierung abzuschließen und dich anzumelden:

$magicUrl

Der Link ist $expiry Minuten gültig und kann nur einmal verwendet werden.

Falls du dich nicht registriert hast, kannst du diese E-Mail ignorieren.

---
$appName
Diese E-Mail wurde automatisch gesendet. Bitte antworte nicht auf diese Nachricht.
EOT;
    } else {
        $subject = "$appName – Dein Anmeldelink";
        $body = <<<EOT
Hallo $name,

hier ist dein Anmeldelink für $appName:

$magicUrl

Der Link ist $expiry Minuten gültig und kann nur einmal verwendet werden.

Falls du keinen Anmeldelink angefordert hast, kannst du diese E-Mail ignorieren.
Dein Account bleibt in diesem Fall unverändert.

---
$appName
Diese E-Mail wurde automatisch gesendet. Bitte antworte nicht auf diese Nachricht.
EOT;
    }

    return send_mail($email, $subject, $body);
}

/**
 * Sendet eine Admin-Einladung per E-Mail.
 */
function send_admin_invitation_mail(string $email, string $eventName, string $token, string $inviterName): bool {
    $baseUrl = get_base_url();
    $inviteUrl = $baseUrl . '/index.php?admin_invite=' . $token;
    $appName = APP_NAME;

    $subject = "$appName – Einladung als Event-Admin: $eventName";
    $body = <<<EOT
Hallo,

$inviterName hat dich als Administrator für das Event "$eventName" in $appName eingeladen.

Klicke auf den folgenden Link, um die Einladung anzunehmen:

$inviteUrl

Als Event-Admin kannst du Teilnehmer einladen, Termine verwalten, Anwesenheit eintragen und den Strafenkatalog pflegen.

Falls du diese Einladung nicht erwartet hast, kannst du sie ignorieren.

---
$appName
Diese E-Mail wurde automatisch gesendet. Bitte antworte nicht auf diese Nachricht.
EOT;

    return send_mail($email, $subject, $body);
}

/**
 * Informiert einen bestehenden User, dass er zu einem neuen Event hinzugefügt wurde.
 */
function send_event_added_mail(string $email, string $name, string $eventName): bool {
    $baseUrl = get_base_url();
    $loginUrl = $baseUrl . '/index.php?login';
    $appName = APP_NAME;

    $subject = $appName . ' - Du wurdest zu "' . $eventName . '" hinzugefuegt';
    $body = <<<EOT
Hallo $name,

du wurdest als Teilnehmer zum Event "$eventName" in $appName hinzugefügt.

Melde dich an, um das Dashboard aufzurufen:

$loginUrl

---
$appName
Diese E-Mail wurde automatisch gesendet. Bitte antworte nicht auf diese Nachricht.
EOT;

    return send_mail($email, $subject, $body);
}

/**
 * Bestätigt die Account-Löschung.
 */
function send_account_deleted_mail(string $email, string $name): bool {
    $appName = APP_NAME;
    $days = SOFT_DELETE_RETENTION_DAYS;

    $subject = "$appName – Dein Account wurde gelöscht";
    $body = <<<EOT
Hallo $name,

dein Account bei $appName wurde wie gewünscht gelöscht.

Deine Daten werden nach einer Aufbewahrungsfrist von $days Tagen endgültig entfernt.
Bis dahin kann die Löschung durch den Administrator rückgängig gemacht werden.

Falls du deinen Account nicht gelöscht hast, wende dich bitte umgehend an den Administrator.

---
$appName
Diese E-Mail wurde automatisch gesendet. Bitte antworte nicht auf diese Nachricht.
EOT;

    return send_mail($email, $subject, $body);
}

/**
 * Benachrichtigt den Admin über eine neue Registrierung (bei manueller Bestätigung).
 */
function send_registration_notification_mail(string $adminEmail, string $adminName, string $participantName, string $eventName): bool {
    $baseUrl = get_base_url();
    $loginUrl = $baseUrl . '/index.php?login';
    $appName = APP_NAME;

    $subject = $appName . ' - Neue Registrierung fuer "' . $eventName . '"';
    $body = <<<EOT
Hallo $adminName,

es liegt eine neue Registrierung für das Event "$eventName" vor:

Teilnehmer: $participantName

Bitte melde dich an und bestätige oder lehne die Registrierung ab:

$loginUrl

---
$appName
Diese E-Mail wurde automatisch gesendet. Bitte antworte nicht auf diese Nachricht.
EOT;

    return send_mail($adminEmail, $subject, $body);
}

/**
 * Sendet eine direkte Teilnehmer-Einladung per E-Mail.
 * Der Admin lädt gezielt eine Person per E-Mail-Adresse ein.
 */
function send_participant_invitation_mail(string $email, string $eventName, string $inviteToken, string $inviterName): bool {
    $baseUrl = get_base_url();
    $inviteUrl = $baseUrl . '/index.php?invite=' . $inviteToken;
    $appName = APP_NAME;

    $subject = "$appName – Einladung: $eventName";
    $body = <<<EOT
Hallo,

$inviterName hat dich zur Teilnahme am Event "$eventName" in $appName eingeladen.

Klicke auf den folgenden Link, um dich zu registrieren:

$inviteUrl

Nach der Registrierung erhältst du einen Anmeldelink per E-Mail, mit dem du dich jederzeit einloggen kannst.

Falls du diese Einladung nicht erwartet hast, kannst du sie ignorieren.

---
$appName
Diese E-Mail wurde automatisch gesendet. Bitte antworte nicht auf diese Nachricht.
EOT;

    return send_mail($email, $subject, $body);
}

/**
 * Sendet eine Server-Admin-Einladung per E-Mail.
 */
function send_server_admin_invitation_mail(string $email, string $inviterName): bool {
    $baseUrl = get_base_url();
    $loginUrl = $baseUrl . '/index.php?login';
    $appName = APP_NAME;

    $subject = $appName . ' - Einladung als Server-Administrator';
    $body = <<<EOT
Hallo,

$inviterName hat dich als Server-Administrator in $appName eingeladen. Du hast damit Zugriff auf die globale Verwaltung aller Events.

Melde dich hier an:

$loginUrl

Falls du noch keinen Account hast, wende dich bitte an $inviterName.

---
$appName
Diese E-Mail wurde automatisch gesendet. Bitte antworte nicht auf diese Nachricht.
EOT;

    return send_mail($email, $subject, $body);
}
