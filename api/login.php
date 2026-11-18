<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/email_code.php';
require_once __DIR__ . '/login_limit.php';
require_post();

// A real bcrypt hash of a random string nobody knows. Checked when the
// username doesn't exist, so that case takes as long as a wrong password.
const DUMMY_HASH = '$2y$10$0Uqv5M0S8sdjzGnzTsYAbOPzsxbLDnTRX4uzMKf75SRtJINybK/g2';

$login    = trim($_POST['login'] ?? '');
$password = $_POST['password'] ?? '';
$ip       = $_SERVER['REMOTE_ADDR'];

$user = DB::queryExecuter(
    'SELECT id, email, display_name, password_hash, email_verified, totp_enabled
     FROM users WHERE username = ? OR email = ?',
    [$login, $login],
    'fetch'
);

// Count failures per account (so username and email share one counter),
// or per typed name when no such account exists
if ($user) {
    $loginKey = 'user:' . $user['id'];
} else {
    $loginKey = 'name:' . strtolower($login);
}

// 0. Too many recent failures: refuse before even checking the password
if (login_is_locked($loginKey, $ip)) {
    json_response(['error' => 'Too many failed attempts. Try again in ' . LOGIN_WINDOW_MINUTES . ' minutes.'], 429);
}

$hash = $user ? $user['password_hash'] : DUMMY_HASH;

if (!password_verify($password, $hash) || !$user) {
    login_record_failure($loginKey, $ip);
    json_response(['error' => 'Wrong username or password'], 401);
}

login_clear_failures($loginKey);

$userId = (int) $user['id'];

// 1. Email never confirmed: send a code
if (!$user['email_verified']) {
    set_auth_state('pending_email_user_id', $userId);
    $sent = send_verification_code($userId, $user['email'], $user['display_name']);
    json_response(['next' => 'verify_email', 'email_sent' => $sent]);
}

// 2. Authenticator app never set up: go to setup
if (!$user['totp_enabled']) {
    set_auth_state('pending_enroll_user_id', $userId);
    json_response(['next' => 'setup_totp']);
}

// 3. Normal case: ask for the authenticator code
set_auth_state('pending_totp_user_id', $userId);
json_response(['next' => 'verify_totp']);