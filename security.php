<?php
// Спільний захист усіх практикумів. Секрети задаються тільки через оточення.
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

function forum_json_request(): bool
{
    return preg_match('/^(api.*|auth)\.php$/D', basename($_SERVER['SCRIPT_NAME'] ?? '')) === 1;
}

function forum_deny(int $status, string $message): never
{
    http_response_code($status);
    header('Cache-Control: no-store');
    if (forum_json_request()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="uk"><meta charset="utf-8"><title>Помилка</title><p>'
            . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p></html>';
    }
    exit;
}

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(static function (Throwable $error): void {
    error_log('Forum: ' . $error);
    forum_deny(500, 'Внутрішня помилка сервера. Спробуйте пізніше.');
});

function forum_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('FORUMSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if (!session_start()) {
        throw new RuntimeException('Cannot start session');
    }
    header('Cache-Control: no-store');
}

function forum_csrf_token(): string
{
    forum_session();
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function forum_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . forum_csrf_token() . '">';
}

function forum_require_csrf(array $input = []): void
{
    $expected = forum_csrf_token();
    $given = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? null);
    if (!is_string($given) || !preg_match('/^[a-f0-9]{64}$/D', $given)
        || !hash_equals($expected, $given)) {
        forum_deny(403, 'Недійсний CSRF-токен. Оновіть сторінку.');
    }
}

function forum_is_moderator(): bool
{
    forum_session();
    $hash = getenv('PHP_LAB_MODERATOR_HASH') ?: '';
    return $hash !== '' && isset($_SESSION['moderator_key'], $_SESSION['moderator_until'])
        && is_string($_SESSION['moderator_key']) && is_int($_SESSION['moderator_until'])
        && $_SESSION['moderator_until'] > time()
        && hash_equals(hash('sha256', $hash), $_SESSION['moderator_key']);
}

function forum_require_moderator(): void
{
    if (!forum_is_moderator()) {
        forum_deny(403, 'Цю дію може виконувати лише модератор.');
    }
}

function forum_auth_state(): array
{
    return ['csrf_token' => forum_csrf_token(), 'moderator' => forum_is_moderator()];
}

function forum_auth_action(array $input): void
{
    forum_require_csrf($input);
    $action = $input['action'] ?? '';
    if ($action === 'login') {
        $hash = getenv('PHP_LAB_MODERATOR_HASH') ?: '';
        if ($hash === '') {
            forum_deny(503, 'Вхід модератора не налаштовано на сервері.');
        }
        if (($_SESSION['login_blocked_until'] ?? 0) > time()) {
            forum_deny(429, 'Забагато спроб. Спробуйте через хвилину.');
        }
        $password = $input['password'] ?? null;
        if (!is_string($password) || strlen($password) > 1024 || !password_verify($password, $hash)) {
            $_SESSION['login_failures'] = ($_SESSION['login_failures'] ?? 0) + 1;
            if ($_SESSION['login_failures'] >= 5) {
                $_SESSION['login_blocked_until'] = time() + 60;
                $_SESSION['login_failures'] = 0;
            }
            forum_deny(403, 'Неправильний пароль.');
        }
        session_regenerate_id(true);
        $_SESSION['moderator_key'] = hash('sha256', $hash);
        $_SESSION['moderator_until'] = time() + 1800;
        unset($_SESSION['login_failures'], $_SESSION['login_blocked_until']);
    } elseif ($action === 'logout') {
        session_regenerate_id(true);
        unset($_SESSION['moderator_key'], $_SESSION['moderator_until']);
    } else {
        forum_deny(400, 'Невідома дія входу.');
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function forum_pinned_cache_file(): string
{
    $dir = getenv('PHP_LAB_CACHE_DIR') ?: sys_get_temp_dir() . '/php-practicum07';
    $identity = __DIR__ . '|' . (getenv('PHP_LAB_DSN') ?: 'practicum4')
        . '|' . (getenv('PHP_LAB_DB_USER') ?: 'root');
    return $dir . '/pinned_' . hash('sha256', $identity) . '.json';
}

function forum_invalidate_pinned(): void
{
    // Усі версії працюють з тією самою БД та одним кешем.
    $path = forum_pinned_cache_file();
    if (!is_file($path) || !($file = @fopen($path, 'c+'))) return;
    try {
        if (flock($file, LOCK_EX)) {
            @ftruncate($file, 0);
            @fflush($file);
        }
    } finally {
        fclose($file);
    }
}
