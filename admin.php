<?php
/**
 * BOS-Score – Server-Administration
 * Zugang über Auth-Session + Rolle server_admin.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/auth.php';

$user = require_event_role(null, ['server_admin']);

require __DIR__ . '/views/server_admin.php';
