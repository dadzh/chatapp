<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/crypto.php';
require_post();

$userId         = require_login();
$conversationId = (int) ($_POST['conversation_id'] ?? 0);
$body           = trim($_POST['body'] ?? '');

if ($body === '') {
    json_response(['error' => 'Message is empty'], 400);
}
if (mb_strlen($body) > 2000) {
    json_response(['error' => 'Message is too long (max 2000 characters)'], 400);
}

require_member($conversationId, $userId);

$messageId = (int) DB::queryExecuter(
    'INSERT INTO messages (conversation_id, sender_id, body) VALUES (?, ?, ?)',
    [$conversationId, $userId, encrypt_message($body, $conversationId)],
    'id'
);

json_response(['message_id' => $messageId], 201);