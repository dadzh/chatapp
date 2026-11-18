<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/totp.php';
require_once __DIR__ . '/crypto.php';

const TOTP_MAX_ATTEMPTS = 5;   // wrong codes allowed...
const TOTP_LOCK_MINUTES = 15;  // ...before the TOTP step is locked for this long

// Check an authenticator code at login.
// Returns 'ok', 'wrong' or 'locked'.
function check_totp_code(int $userId, string $code): string
{
    $user = DB::queryExecuter(
        'SELECT totp_secret, totp_attempts, totp_locked_until, totp_last_step
         FROM users WHERE id = ?',
        [$userId],
        'fetch'
    );

    // Still locked from earlier wrong attempts
    if ($user['totp_locked_until'] !== null && strtotime($user['totp_locked_until']) > time()) {
        return 'locked';
    }

    $secret = decrypt_secret($user['totp_secret'], $userId);
    $step   = totp_find_step($secret, $code);

    // A code that was already used counts as wrong (replay protection)
    $alreadyUsed = $step !== null
                && $user['totp_last_step'] !== null
                && $step <= (int) $user['totp_last_step'];

    if ($step === null || $alreadyUsed) {
        $attempts    = (int) $user['totp_attempts'] + 1;
        $lockedUntil = null;

        if ($attempts >= TOTP_MAX_ATTEMPTS) {
            $lockedUntil = date('Y-m-d H:i:s', time() + TOTP_LOCK_MINUTES * 60);
            $attempts    = 0;   // a fresh count once the lock ends
        }

        DB::queryExecuter(
            'UPDATE users SET totp_attempts = ?, totp_locked_until = ? WHERE id = ?',
            [$attempts, $lockedUntil, $userId],
            'check'
        );

        if ($lockedUntil !== null) {
            return 'locked';
        }
        return 'wrong';
    }

    // Correct: clear the counter and remember this step so the code can't be reused
    DB::queryExecuter(
        'UPDATE users SET totp_attempts = 0, totp_locked_until = NULL, totp_last_step = ?
         WHERE id = ?',
        [$step, $userId],
        'check'
    );
    return 'ok';
}