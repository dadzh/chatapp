<?php
require_once __DIR__ . '/helpers.php';

$userId = require_login();

$user = DB::queryExecuter(
    'SELECT id, username, display_name, email FROM users WHERE id = ?',
    [$userId],
    'fetch'
);

if (!$user) {
    revoke_tokens();
    json_response(['error' => 'Not logged in'], 401);
}

$user['id'] = (int) $user['id'];
json_response(['user' => $user]);