<?php
require_once __DIR__ . '/config.php';

const SECRET_CIPHER = 'aes-256-gcm';

// ---------- General encryption ----------

// A 32-byte key from private/.env, by name (e.g. 'TOTP_KEY', 'MESSAGE_KEY')
function encryption_key(string $name): string
{
    $key = base64_decode($_ENV[$name] ?? '', true);
    if ($key === false || strlen($key) !== 32) {
        throw new Exception("$name in .env must be 32 random bytes, base64-encoded");
    }
    return $key;
}

// Encrypt $plain with the named key. $context is bound into the result:
// decryption only works with the same context (e.g. the same user or conversation).
function encrypt_value(string $plain, string $keyName, string $context): string
{
    $iv     = random_bytes(12);          // a new random value every time
    $tag    = '';                        // filled in by openssl_encrypt
    $cipher = openssl_encrypt($plain, SECRET_CIPHER, encryption_key($keyName),
                              OPENSSL_RAW_DATA, $iv, $tag, $context);

    // Store everything needed to decrypt, as one text value: iv + tag + ciphertext
    return base64_encode($iv . $tag . $cipher);
}

// Reverse of encrypt_value. Throws if the data was changed, or the key or context is wrong.
function decrypt_value(string $stored, string $keyName, string $context): string
{
    $data = base64_decode($stored, true);
    if ($data === false || strlen($data) < 28) {
        throw new Exception('Encrypted value is malformed');
    }

    $iv     = substr($data, 0, 12);
    $tag    = substr($data, 12, 16);
    $cipher = substr($data, 28);

    $plain = openssl_decrypt($cipher, SECRET_CIPHER, encryption_key($keyName),
                             OPENSSL_RAW_DATA, $iv, $tag, $context);
    if ($plain === false) {
        throw new Exception('Could not decrypt');
    }
    return $plain;
}

// ---------- TOTP secrets ----------

function encrypt_secret(string $secret, int $userId): string
{
    return encrypt_value($secret, 'TOTP_KEY', 'user:' . $userId);
}

function decrypt_secret(string $stored, int $userId): string
{
    return decrypt_value($stored, 'TOTP_KEY', 'user:' . $userId);
}

// ---------- Messages ----------

function encrypt_message(string $body, int $conversationId): string
{
    return encrypt_value($body, 'MESSAGE_KEY', 'conversation:' . $conversationId);
}

// A message that can't be decrypted is shown as a notice instead of
// breaking the whole conversation
function decrypt_message(string $stored, int $conversationId): string
{
    try {
        return decrypt_value($stored, 'MESSAGE_KEY', 'conversation:' . $conversationId);
    } catch (Exception $e) {
        error_log('Message decryption failed in conversation ' . $conversationId);
        return '[This message could not be decrypted]';
    }
}