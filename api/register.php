<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/email_code.php';
require_post();

// 1. Read the input
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$displayName = trim($_POST['display_name'] ?? '');
if ($displayName === '') {
    $displayName = $username;
}

// 2. Validate it
if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
    json_response(['error' => 'Username must be 3-30 letters, digits or underscores'], 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
    json_response(['error' => 'Invalid email address'], 400);
}
if (strlen($password) < 8) {
    json_response(['error' => 'Password must be at least 8 characters'], 400);
}
if (!preg_match('/[A-Z]/', $password)) {
    json_response(['error' => 'Password must contain at least one capital letter'], 400);
}
if (!preg_match('/[0-9]/', $password)) {
    json_response(['error' => 'Password must contain at least one number'], 400);
}
if (mb_strlen($displayName) > 50) {
    json_response(['error' => 'Display name is too long (max 50)'], 400);
}

// 3. Check the username and email are free
$existing = DB::queryExecuter(
    'SELECT username FROM users WHERE username = ? OR email = ?',
    [$username, $email],
    'fetch'
);
if ($existing) {
    $taken = strcasecmp($existing['username'], $username) === 0 ? 'Username' : 'Email';
    json_response(['error' => "$taken is already taken"], 409);
}

// 4. Store the user with a hashed password
$userId = (int) DB::queryExecuter(
    'INSERT INTO users (username, email, password_hash, display_name) VALUES (?, ?, ?, ?)',
    [$username, $email, password_hash($password, PASSWORD_DEFAULT), $displayName],
    'id'
);

// 5. Don't log in yet: the email must be confirmed first
set_auth_state('pending_email_user_id', $userId);

$sent = send_verification_code($userId, $email, $displayName);

json_response(['next' => 'verify_email', 'email_sent' => $sent], 201);

json_response([
    'user' => ['id' => $userId, 'username' => $username, 'display_name' => $displayName],
], 201);