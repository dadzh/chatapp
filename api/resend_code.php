<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/email_code.php';
require_post();

$userId = require_pending('pending_email_user_id');

$user = DB::queryExecuter(
    'SELECT email, display_name, email_code_expires FROM users WHERE id = ?',
    [$userId],
    'fetch'
);

// At most one new code per minute.
// A fresh code expires in 10 minutes, so "more than 9 minutes left" means
// the last code was sent less than a minute ago.
if ($user['email_code_expires'] !== null) {
    $secondsLeft = strtotime($user['email_code_expires']) - time();
    if ($secondsLeft > EMAIL_CODE_MINUTES * 60 - 60) {
        json_response(['error' => 'Please wait a minute before requesting a new code'], 429);
    }
}

if (!send_verification_code($userId, $user['email'], $user['display_name'])) {
    json_response(['error' => 'Could not send the email, please try again later'], 500);
}

json_response(['ok' => true]);