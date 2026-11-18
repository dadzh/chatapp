<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

const ACCESS_TOKEN_SECONDS  = 15 * 60;       // access token: 15 minutes
const REFRESH_TOKEN_SECONDS = 7 * 24 * 3600; // refresh token: 7 days

// ---------- JWT basics ----------

// Base64 in the URL-safe variant JWTs use: - and _ instead of + and /, no = padding
function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string
{
    return (string) base64_decode(strtr($data, '-_', '+/'));
}

function jwt_secret(): string
{
    $secret = $_ENV['JWT_SECRET'] ?? '';
    if (strlen($secret) < 32) {
        throw new Exception('JWT_SECRET in .env must be at least 32 characters');
    }
    return $secret;
}

// header.payload.signature, signed with HMAC-SHA256
function create_jwt(array $payload): string
{
    $header    = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $body      = base64url_encode(json_encode($payload));
    $signature = base64url_encode(hash_hmac('sha256', $header . '.' . $body, jwt_secret(), true));

    return $header . '.' . $body . '.' . $signature;
}

// Returns the payload if the signature is valid and the token hasn't expired, otherwise null
function verify_jwt(string $jwt): ?array
{
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        return null;
    }
    [$header, $body, $signature] = $parts;

    // Always HS256 with our secret, whatever the header claims
    $expected = base64url_encode(hash_hmac('sha256', $header . '.' . $body, jwt_secret(), true));
    if (!hash_equals($expected, $signature)) {
        return null;
    }

    $payload = json_decode(base64url_decode($body), true);
    if (!is_array($payload) || !isset($payload['exp']) || time() >= $payload['exp']) {
        return null;
    }
    return $payload;
}

// ---------- Cookies ----------

function set_auth_cookie(string $name, string $value, int $expires): void
{
    setcookie($name, $value, [
        'expires'  => $expires,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,     // JavaScript can't read it
        'samesite' => 'Lax',    // not sent on cross-site POSTs
    ]);
}

// ---------- Issuing, checking and revoking ----------

// Log the user in: a new access token and a new refresh token, as cookies
function issue_tokens(int $userId): void
{
    $now = time();

    $access = create_jwt([
        'sub'  => $userId,      // subject: who the token is about
        'type' => 'access',
        'iat'  => $now,         // issued at
        'exp'  => $now + ACCESS_TOKEN_SECONDS,
    ]);

    // The refresh token is just a long random value; only its hash is stored
    $refresh = bin2hex(random_bytes(32));
    DB::queryExecuter(
        'INSERT INTO refresh_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)',
        [$userId, hash('sha256', $refresh), date('Y-m-d H:i:s', $now + REFRESH_TOKEN_SECONDS)],
        'check'
    );

    // Tidy up: remove refresh tokens that have expired
    DB::queryExecuter('DELETE FROM refresh_tokens WHERE expires_at < ?', [date('Y-m-d H:i:s', $now)], 'check');

    set_auth_cookie('access_token', $access, $now + ACCESS_TOKEN_SECONDS);
    set_auth_cookie('refresh_token', $refresh, $now + REFRESH_TOKEN_SECONDS);
}

// Who is logged in? Uses the access token, or the refresh token if the access token
// has expired (issuing a new pair). Returns the user id, or null if not logged in.
function current_user_id(): ?int
{
    $payload = verify_jwt($_COOKIE['access_token'] ?? '');
    if ($payload !== null && ($payload['type'] ?? '') === 'access') {
        return (int) $payload['sub'];
    }
    return refresh_login();
}

function refresh_login(): ?int
{
    $refresh = $_COOKIE['refresh_token'] ?? '';
    if ($refresh === '') {
        return null;
    }

    $row = DB::queryExecuter(
        'SELECT id, user_id, expires_at FROM refresh_tokens WHERE token_hash = ?',
        [hash('sha256', $refresh)],
        'fetch'
    );
    if (!$row) {
        return null;
    }

    // Rotation: each refresh token works exactly once
    DB::queryExecuter('DELETE FROM refresh_tokens WHERE id = ?', [$row['id']], 'check');

    if (strtotime($row['expires_at']) < time()) {
        return null;
    }

    issue_tokens((int) $row['user_id']);
    return (int) $row['user_id'];
}

// Log out: invalidate the refresh token in the database and delete both cookies
function revoke_tokens(): void
{
    $refresh = $_COOKIE['refresh_token'] ?? '';
    if ($refresh !== '') {
        DB::queryExecuter(
            'DELETE FROM refresh_tokens WHERE token_hash = ?',
            [hash('sha256', $refresh)],
            'check'
        );
    }
    set_auth_cookie('access_token', '', time() - 3600);
    set_auth_cookie('refresh_token', '', time() - 3600);
}