<?php
declare(strict_types=1);

define('CY_APP', dirname(__DIR__));                 // .../app
define('CY_ROOT', dirname(CY_APP));                  // 账号根目录（app 的上一级）
define('CY_PUBLIC', CY_ROOT . '/public');            // 虚拟主机 web 根

/**
 * 读取配置（支持点路径），配置只存在 app/config.php。
 */
function cfg(string $path = '', mixed $default = null): mixed
{
    static $config = null;
    if ($config === null) {
        $file = CY_APP . '/config.php';
        if (!is_file($file)) {
            http_response_code(500);
            exit('缺少 app/config.php，请复制 config.example.php 并填写。');
        }
        $config = require $file;
        $tz = $config['site']['timezone'] ?? 'Asia/Shanghai';
        date_default_timezone_set($tz);
    }
    if ($path === '') {
        return $config;
    }
    $value = $config;
    foreach (explode('.', $path) as $key) {
        if (!is_array($value) || !array_key_exists($key, $value)) {
            return $default;
        }
        $value = $value[$key];
    }
    return $value;
}

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $d = cfg('db');
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $d['host'], (int) $d['port'], $d['name']);
    $pdo = new PDO($dsn, $d['user'], $d['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

function post_str(string $key, int $max = 0, string $default = ''): string
{
    $value = isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : $default;
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? '';
    if ($max > 0 && mb_strlen($value, 'UTF-8') > $max) {
        $value = mb_substr($value, 0, $max, 'UTF-8');
    }
    return $value;
}

function get_str(string $key, int $max = 0, string $default = ''): string
{
    $value = isset($_GET[$key]) && is_string($_GET[$key]) ? trim($_GET[$key]) : $default;
    if ($max > 0 && mb_strlen($value, 'UTF-8') > $max) {
        $value = mb_substr($value, 0, $max, 'UTF-8');
    }
    return $value;
}

function valid_slug(string $slug): bool
{
    return (bool) preg_match('/^[a-z0-9][a-z0-9-]{0,119}$/', $slug);
}

function valid_date(string $date): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }
    [$y, $m, $d] = array_map('intval', explode('-', $date));
    return checkdate($m, $d, $y);
}

function today(): string
{
    return date('Y-m-d');
}

/**
 * 中文按 400 字/分钟，英文按词折算；返回 [字数, 分钟]。
 */
function reading_stats(string $html): array
{
    $text = trim(strip_tags($html));
    $text = preg_replace('/\s+/u', ' ', $text) ?? '';
    $chars = mb_strlen($text, 'UTF-8');
    $words = str_word_count($text);
    $count = max($chars, (int) ($words * 2));
    $minutes = max(1, (int) ceil($chars / 400));
    return [$count, $minutes];
}

function fmt_date(?string $date, string $format = 'Y.m.d'): string
{
    if (!$date) {
        return '';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '';
}

function site_url(string $path = ''): string
{
    return rtrim((string) cfg('site.base_url'), '/') . '/' . ltrim($path, '/');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name((string) cfg('admin.session', 'cy_admin'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => is_https(),
    ]);
    session_start();
}

function is_logged_in(): bool
{
    start_session();
    if (empty($_SESSION['admin'])) {
        return false;
    }
    $expires = (int) ($_SESSION['admin_expires'] ?? 0);
    if ($expires > 0 && time() > $expires) {
        $_SESSION = [];
        return false;
    }
    return true;
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: admin.php');
        exit;
    }
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function check_csrf(): void
{
    start_session();
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('请求已过期，请刷新页面重试。');
    }
}

function admin_password_ok(string $input, ?string $username = null): bool
{
    $account = admin_account();
    if ($username !== null && $username !== '' && !hash_equals($account['username'], $username)) {
        return false;
    }
    if ($account['password_hash'] !== '') {
        return password_verify($input, $account['password_hash']);
    }
    $plain = (string) cfg('admin.password', '');
    return $plain !== '' && hash_equals($plain, $input);
}

/**
 * 后台账户：优先读 settings 表里的 k='admin'，没有则回落到 config.php。
 * 返回 ['username', 'password_hash', 'password'(仅 config 兜底), 'source']。
 */
function admin_account(): array
{
    if (isset($GLOBALS['cy_admin_account_cache'])) {
        return $GLOBALS['cy_admin_account_cache'];
    }
    $account = [
        'username'      => (string) (cfg('admin.username', 'admin') ?: 'admin'),
        'password_hash' => (string) cfg('admin.password_hash', ''),
        'password'      => (string) cfg('admin.password', ''),
        'source'        => 'config',
    ];
    try {
        $stmt = db()->prepare("SELECT v FROM settings WHERE k = 'admin'");
        $stmt->execute();
        $json = (string) $stmt->fetchColumn();
        if ($json !== '') {
            $data = json_decode($json, true);
            if (is_array($data)) {
                if (!empty($data['username'])) {
                    $account['username'] = (string) $data['username'];
                }
                if (!empty($data['password_hash'])) {
                    $account['password_hash'] = (string) $data['password_hash'];
                    $account['password'] = '';
                    $account['source'] = 'settings';
                }
            }
        }
    } catch (Throwable $e) {
        // 数据库未就绪或 settings 表还没建，继续用 config.php 的兜底账户
    }
    $GLOBALS['cy_admin_account_cache'] = $account;
    return $account;
}

function admin_account_save(string $username, string $passwordHash): void
{
    $payload = json_encode([
        'username'      => $username,
        'password_hash' => $passwordHash,
        'updated_at'    => date('c'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $stmt = db()->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
    $stmt->execute(['admin', $payload]);
    $GLOBALS['cy_admin_account_cache'] = [
        'username'      => $username,
        'password_hash' => $passwordHash,
        'password'      => '',
        'source'        => 'settings',
    ];
}

function is_default_password(): bool
{
    return (string) cfg('admin.password_hash', '') === '' && (string) cfg('admin.password', '') === 'change-me';
}

function flash(string $type, string $message): void
{
    start_session();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    start_session();
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function json_out(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * 只允许站内相对路径，防止后台跳转被利用。
 */
function safe_back(string $fallback = 'admin.php'): string
{
    $to = $_POST['back'] ?? $_GET['back'] ?? '';
    if (is_string($to) && $to !== '' && !preg_match('#^(?:[a-z]+:)?//#i', $to) && !str_contains($to, "\n")) {
        return $to;
    }
    return $fallback;
}

function json_decode_field(?string $json, array $fallback = []): array
{
    if (!$json) {
        return $fallback;
    }
    $data = json_decode($json, true);
    return is_array($data) ? $data : $fallback;
}
