<?php
require_once __DIR__ . '/helpers.php';
require_post();

revoke_tokens();

$_SESSION = [];
$p = session_get_cookie_params();
setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
session_destroy();

json_response(['ok' => true]);