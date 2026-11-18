<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/totp.php';
require_once __DIR__ . '/crypto.php';
require_post();

$userId = require_pending('pending_enroll_user_id');
$code   = trim($_POST['code'] ?? '');

$secret = $_SESSION['totp_pending_secret'] ?? null;
if ($secret === null) {
    json_response(['error' => 'Start the setup first'], 400);
}

$step = totp_find_step($secret, $code);
if ($step === null) {
    json_response(['error' => 'Wrong code, try again'], 400);
}

DB::queryExecuter(
    'UPDATE users SET totp_secret = ?, totp_enabled = 1, totp_last_step = ? WHERE id = ?',
    [encrypt_secret($secret, $userId), $step, $userId],
    'check'
);
unset($_SESSION['totp_pending_secret']);

// All steps done: logged in
complete_login($userId);
json_response(['next' => 'chat']);