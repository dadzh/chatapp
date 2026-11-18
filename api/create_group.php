<?php
require_once __DIR__ . '/helpers.php';
require_post();

$userId = require_login();
$otherIds = $_POST['user_ids'] ?? [];
if (!is_array($otherIds) || count($otherIds) === 0) {
    json_response(['error' => 'Choose at least one member'], 400);
}
$otherIds = array_unique(array_map('intval', $otherIds)); 

$name = trim($_POST['name'] ?? '');

$names = [];
foreach ($otherIds as $otherId) {
    if ($otherId === $userId){
        json_response(['error' => 'You cannot start a chat with yourself'], 400);
    }
    $other = DB::queryExecuter('SELECT id, display_name FROM users WHERE id = ?', [$otherId], 'fetch');
    if (!$other){
        json_response(['error' => 'User not found'], 404);
    }
    $names[] = $other['display_name'];
}
if ($name===''){
    $name = implode(', ', $names);
    }
if (mb_strlen($name) > 80) {
    json_response(['error' => 'Group name is too long (max 80)'], 400);
}

$conversationId = (int) DB::queryExecuter(
    'INSERT INTO conversations (is_group, name, created_by) VALUES (1, ?, ?)',
    [$name, $userId], 'id'
);

DB::queryExecuter(
    'INSERT INTO conversation_members (conversation_id, user_id) VALUES (?,?)',
    [$conversationId, $userId], 'check'
);
foreach($otherIds as $otherId){
    DB::queryExecuter(
    'INSERT INTO conversation_members (conversation_id, user_id) VALUES (?,?)',
    [$conversationId, $otherId], 'check');
}
json_response(['conversation_id' => $conversationId, 'name' => $name], 201);