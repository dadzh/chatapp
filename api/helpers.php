<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt.php';

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

set_exception_handler(function (Throwable $e) {
    error_log($e->getMessage());
    json_response(['error' => 'Server error'], 500);
});

// ---------- Requests ----------

function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['error' => 'POST required'], 405);
    }

    // Every POST must carry this session's CSRF token in a header
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sent)) {
        json_response(['error' => 'Invalid security token, please reload the page'], 403);
    }
}

// This session's CSRF token, created on first use
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// ---------- Logged in (JWT) ----------

// The logged-in user's id from the JWT cookies, or stop with 401
function require_login(): int
{
    $userId = current_user_id();
    if ($userId === null) {
        json_response(['error' => 'Not logged in'], 401);
    }
    return $userId;
}

function require_member(int $conversationId, int $userId): void
{
    $member = DB::queryExecuter(
        'SELECT 1 FROM conversation_members WHERE conversation_id = ? AND user_id = ?',
        [$conversationId, $userId],
        'fetch'
    );
    if (!$member) {
        json_response(['error' => 'Conversation not found'], 404);
    }
}

// ---------- Login in progress (session) ----------
// While logging in, a user moves through these session keys, one at a time:
//   pending_email_user_id   password ok / registered, email not confirmed yet
//   pending_enroll_user_id  email confirmed, authenticator app not set up yet
//   pending_totp_user_id    password ok, waiting for the authenticator code
// When every step is done, complete_login() issues the JWTs.

const AUTH_STATES = ['pending_email_user_id', 'pending_enroll_user_id', 'pending_totp_user_id'];

function clear_auth_states(): void
{
    foreach (AUTH_STATES as $key) {
        unset($_SESSION[$key]);
    }
}

// Move the session to a new pending state for this user
function set_auth_state(string $state, int $userId): void
{
    clear_auth_states();
    session_regenerate_id(true);
    $_SESSION[$state] = $userId;
}

// Return the user id if the session is in the given pending state, or stop with 401
function require_pending(string $state): int
{
    if (empty($_SESSION[$state])) {
        json_response(['error' => 'Your session expired, please log in again'], 401);
    }
    return (int) $_SESSION[$state];
}

// All steps passed: end the pending state and log in with JWTs
function complete_login(int $userId): void
{
    clear_auth_states();
    session_regenerate_id(true);
    issue_tokens($userId);
}