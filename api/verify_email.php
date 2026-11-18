<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/email_code.php';
require_post();

$userId = require_pending('pending_email_user_id');

$code   = trim($_POST['code'] ?? '');
$result = check_verification_code($userId, $code);

if ($result === 'wrong') {
    json_response(['error' => 'Wrong code, try again'], 400);
}
if ($result === 'locked') {
    json_response(['error' => 'Too many wrong attempts. Request a new code.'], 429);
}
if ($result === 'expired') {
    json_response(['error' => 'The code has expired. Request a new code.'], 400);
}

// Email confirmed. Next: the authenticator app.
$user = DB::queryExecuter('SELECT totp_enabled FROM users WHERE id = ?', [$userId], 'fetch');

if ($user['totp_enabled']) {
    set_auth_state('pending_totp_user_id', $userId);
    json_response(['next' => 'verify_totp']);
}

set_auth_state('pending_enroll_user_id', $userId);
json_response(['next' => 'setup_totp']);