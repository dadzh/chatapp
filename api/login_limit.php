<?php
require_once __DIR__ . '/db.php';

const LOGIN_MAX_PER_ACCOUNT = 5;   // failed logins allowed for one username/email...
const LOGIN_MAX_PER_IP      = 20;  // ...or from one IP address...
const LOGIN_WINDOW_MINUTES  = 15;  // ...within this many minutes

// The cut-off time: failures older than this no longer count
function login_window_start(): string
{
    return date('Y-m-d H:i:s', time() - LOGIN_WINDOW_MINUTES * 60);
}

// Too many recent failures for this login name, or from this IP?
function login_is_locked(string $login, string $ip): bool
{
    $since = login_window_start();

    $byLogin = DB::queryExecuter(
        'SELECT COUNT(*) AS n FROM login_attempts WHERE login = ? AND attempted_at > ?',
        [$login, $since],
        'fetch'
    );
    $byIp = DB::queryExecuter(
        'SELECT COUNT(*) AS n FROM login_attempts WHERE ip = ? AND attempted_at > ?',
        [$ip, $since],
        'fetch'
    );

    return $byLogin['n'] >= LOGIN_MAX_PER_ACCOUNT || $byIp['n'] >= LOGIN_MAX_PER_IP;
}

// Remember one failed attempt
function login_record_failure(string $login, string $ip): void
{
    DB::queryExecuter(
        'INSERT INTO login_attempts (login, ip, attempted_at) VALUES (?, ?, ?)',
        [$login, $ip, date('Y-m-d H:i:s')],
        'check'
    );
}

// Correct password: forget this login name's failures, and tidy up old rows
function login_clear_failures(string $login): void
{
    DB::queryExecuter('DELETE FROM login_attempts WHERE login = ?', [$login], 'check');
    DB::queryExecuter(
        'DELETE FROM login_attempts WHERE attempted_at < ?',
        [date('Y-m-d H:i:s', time() - 86400)],
        'check'
    );
}