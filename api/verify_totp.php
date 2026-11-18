<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/totp_check.php';
require_post();

$userId = require_pending('pending_totp_user_id');
$code   = trim($_POST['code'] ?? '');

$result = check_totp_code($userId, $code);

if ($result === 'wrong') {
    json_response(['error' => 'Wrong code, try again'], 400);
}
if ($result === 'locked') {
    json_response(['error' => 'Too many wrong codes. Try again in ' . TOTP_LOCK_MINUTES . ' minutes.'], 429);
}

// Password and code both correct: logged in
complete_login($userId);
json_response(['next' => 'chat']);