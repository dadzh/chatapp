<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/totp.php';
require_post();

$userId = require_pending('pending_enroll_user_id');

$user = DB::queryExecuter('SELECT username FROM users WHERE id = ?', [$userId], 'fetch');

// Create a secret, but only remember it in the session until it is confirmed
$secret = totp_generate_secret();
$_SESSION['totp_pending_secret'] = $secret;

json_response([
    'secret' => $secret,
    'uri'    => totp_uri($secret, $user['username']),
]);