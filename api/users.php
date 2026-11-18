<?php
require_once __DIR__ . '/helpers.php';

$userId = require_login();

$users = DB::queryExecuter(
    'SELECT id, username, display_name FROM users WHERE id <> ? ORDER BY display_name',
    [$userId],
    'fetchall'
);

json_response(['users' => $users]);