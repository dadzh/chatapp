<?php
require_once __DIR__ . '/helpers.php';
require_post();

$userId  = require_login();
$otherId = (int) ($_POST['user_id'] ?? 0);

// 1. The other user must exist and must not be you
if ($otherId === $userId) {
    json_response(['error' => 'You cannot start a chat with yourself'], 400);
}
$other = DB::queryExecuter('SELECT id FROM users WHERE id = ?', [$otherId], 'fetch');
if (!$other) {
    json_response(['error' => 'User not found'], 404);
}

// 2. Build the key: smaller id first, e.g. "1:2"
$directKey = min($userId, $otherId) . ':' . max($userId, $otherId);

// 3. If the conversation already exists, return it
$existing = DB::queryExecuter(
    'SELECT id FROM conversations WHERE direct_key = ?',
    [$directKey],
    'fetch'
);
if ($existing) {
    json_response(['conversation_id' => (int) $existing['id']]);
}

// 4. Otherwise create it and add both users as members
$conversationId = (int) DB::queryExecuter(
    'INSERT INTO conversations (is_group, direct_key, created_by) VALUES (0, ?, ?)',
    [$directKey, $userId],
    'id'
);
DB::queryExecuter(
    'INSERT INTO conversation_members (conversation_id, user_id) VALUES (?, ?), (?, ?)',
    [$conversationId, $userId, $conversationId, $otherId],
    'check'
);

json_response(['conversation_id' => $conversationId], 201);