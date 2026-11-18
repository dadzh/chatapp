<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/crypto.php';

$userId = require_login();

$conversations = DB::queryExecuter(
    'SELECT c.id, c.is_group,
            CASE WHEN c.is_group = 1 THEN c.name ELSE other.display_name END AS title,
            lm.body AS last_message,
            lm.created_at AS last_message_at,
            (SELECT COUNT(*) FROM messages m
              WHERE m.conversation_id = c.id
                AND m.id > me.last_read_message_id
                AND m.sender_id <> me.user_id) AS unread
     FROM conversation_members me
     JOIN conversations c
       ON c.id = me.conversation_id
     LEFT JOIN conversation_members om
       ON om.conversation_id = c.id AND om.user_id <> me.user_id AND c.is_group = 0
     LEFT JOIN users other
       ON other.id = om.user_id
     LEFT JOIN messages lm
       ON lm.id = (SELECT MAX(id) FROM messages WHERE conversation_id = c.id)
     WHERE me.user_id = ?
     ORDER BY lm.id DESC, c.id DESC',
    [$userId],
    'fetchall'
);
// Decrypt each conversation's last message preview
foreach ($conversations as $i => $c) {
    if ($c['last_message'] !== null) {
        $conversations[$i]['last_message'] = decrypt_message($c['last_message'], (int) $c['id']);
    }
}

json_response(['conversations' => $conversations]);