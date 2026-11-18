<?php
// TOTP: time-based one-time passwords (RFC 6238).
// The same algorithm used by Google Authenticator, Microsoft Authenticator, Authy...

const TOTP_PERIOD = 30;   // a new code every 30 seconds
const TOTP_DIGITS = 6;    // codes are 6 digits long

// ---------- Base32: the text format authenticator apps use for secrets ----------

function base32_encode(string $bytes): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($bytes) as $char) {
        $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
    }
    $result = '';
    foreach (str_split($bits, 5) as $chunk) {
        $result .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
    }
    return $result;
}

function base32_decode(string $text): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split(strtoupper($text)) as $char) {
        $pos = strpos($alphabet, $char);
        if ($pos !== false) {
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
    }
    $result = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $result .= chr(bindec($byte));
        }
    }
    return $result;
}

// ---------- TOTP ----------

// A new random secret: 20 random bytes = 32 Base32 characters
function totp_generate_secret(): string
{
    return base32_encode(random_bytes(20));
}

// The code for one 30-second time step
function totp_code(string $secret, int $timeStep): string
{
    $key     = base32_decode($secret);
    $counter = pack('J', $timeStep);                  // time step as 8 bytes
    $hash    = hash_hmac('sha1', $counter, $key, true); // 20-byte HMAC-SHA1

    // "Dynamic truncation": pick 4 bytes of the hash, turn them into a number
    $offset = ord($hash[19]) & 0x0F;
    $number = ((ord($hash[$offset])     & 0x7F) << 24)
            | ( ord($hash[$offset + 1])         << 16)
            | ( ord($hash[$offset + 2])         << 8)
            |   ord($hash[$offset + 3]);

    // Keep the last 6 digits, padded with leading zeros
    return str_pad((string) ($number % (10 ** TOTP_DIGITS)), TOTP_DIGITS, '0', STR_PAD_LEFT);
}

// Is $code valid right now? Accepts the previous, current and next step,
// so a phone clock that is a few seconds off still works.
function totp_find_step(string $secret, string $code): ?int
{
    if (!preg_match('/^[0-9]{6}$/', $code)) {
        return null;
    }
    $now = intdiv(time(), TOTP_PERIOD);
    for ($i = -1; $i <= 1; $i++) {
        if (hash_equals(totp_code($secret, $now + $i), $code)) {
            return $now + $i;
        }
    }
    return null;
}

function totp_verify(string $secret, string $code): bool
{
    return totp_find_step($secret, $code) !== null;
}


// The link encoded in the QR code that the authenticator app scans
function totp_uri(string $secret, string $username): string
{
    return 'otpauth://totp/ChatApp:' . rawurlencode($username)
         . '?secret=' . $secret . '&issuer=ChatApp';
}