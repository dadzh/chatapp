<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

const EMAIL_CODE_MINUTES = 10;  // a code is valid for 10 minutes
const EMAIL_CODE_MAX_ATTEMPTS = 5;   // wrong guesses allowed per code

// Create a new code, store its hash, and email it to the user
function send_verification_code(int $userId, string $email, string $name): bool
{
    $code    = (string) random_int(100000, 999999);
    $expires = date('Y-m-d H:i:s', time() + EMAIL_CODE_MINUTES * 60);

    DB::queryExecuter(
        'UPDATE users SET email_code_hash = ?, email_code_expires = ?, email_code_attempts = 0
         WHERE id = ?',
        [password_hash($code, PASSWORD_DEFAULT), $expires, $userId],
        'check'
    );

    $body = "Hello $name,\n\n"
          . "Your verification code is: $code\n\n"
          . "It expires in " . EMAIL_CODE_MINUTES . " minutes.\n"
          . "If you did not create an account, you can ignore this email.";

    return send_email($email, 'Your Chat App verification code', $body);
}

// Check a code typed by the user.
// Returns 'ok', 'wrong', 'expired' or 'locked'.
function check_verification_code(int $userId, string $code): string
{
    $user = DB::queryExecuter(
        'SELECT email_code_hash, email_code_expires, email_code_attempts FROM users WHERE id = ?',
        [$userId],
        'fetch'
    );

    // No code waiting (never sent, or already used)
    if (!$user || $user['email_code_hash'] === null) {
        return 'expired';
    }
    // Too many wrong guesses for this code
    if ($user['email_code_attempts'] >= EMAIL_CODE_MAX_ATTEMPTS) {
        return 'locked';
    }
    // Older than 10 minutes
    if (strtotime($user['email_code_expires']) < time()) {
        return 'expired';
    }
    // Wrong code: count the attempt
    if (!preg_match('/^[0-9]{6}$/', $code) || !password_verify($code, $user['email_code_hash'])) {
        DB::queryExecuter(
            'UPDATE users SET email_code_attempts = email_code_attempts + 1 WHERE id = ?',
            [$userId],
            'check'
        );
        return 'wrong';
    }

    // Correct: mark the email as verified and delete the code so it can't be reused
    DB::queryExecuter(
        'UPDATE users SET email_verified = 1, email_code_hash = NULL,
                          email_code_expires = NULL, email_code_attempts = 0
         WHERE id = ?',
        [$userId],
        'check'
    );
    return 'ok';
}