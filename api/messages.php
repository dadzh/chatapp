<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/crypto.php';

$userId  = require_login();
$conversationId = (int) ($_GET['conversation_id'] ?? 0);
$afterId = (int) ($_GET['after_id'] ?? 0);

require_member($conversationId, $userId);

$messages = DB::queryExecuter(
    'SELECT m.id, m.sender_id, u.display_name AS sender_name, m.body, m.created_at
     FROM messages m
     JOIN users u ON u.id = m.sender_id
     WHERE m.conversation_id = ? AND m.id > ?
     ORDER BY m.id',
    [$conversationId, $afterId],
    'fetchall'
);

// Decrypt each message's text
foreach ($messages as $i => $m) {
    $messages[$i]['body'] = decrypt_message($m['body'], $conversationId);
}
// Remember how far this user has read
if ($messages) {
    $lastId = end($messages)['id'];
    DB::queryExecuter(
        'UPDATE conversation_members SET last_read_message_id = ?
         WHERE conversation_id = ? AND user_id = ?',
        [$lastId, $conversationId, $userId],
        'check'
    );
}


json_response(['messages' => $messages]);