<?php
/**
 * قرعه‌کشی — LuckyDraw
 * Bootstrap: constants, helpers, autoload.
 *
 * Requirements: PHP 7.4+ (json, mbstring recommended, pdo_sqlite optional).
 * Works 100% offline — no external requests are made anywhere in the app.
 */
declare(strict_types=1);

define('LD_ROOT', dirname(__DIR__));
define('LD_STORAGE', LD_ROOT . DIRECTORY_SEPARATOR . 'storage');
define('LD_VERSION', '1.2.0');
define('LD_APP_NAME', 'قرعه‌کشی');
define('LD_MAX_ITEMS', 500);        // max list entries per draw
define('LD_MAX_ITEM_LEN', 80);      // max characters per entry
define('LD_TTL_OPTIONS', [5, 10, 15, 30, 60]); // minutes a live link can be valid for
define('LD_MAX_TTL_TOTAL', 6 * 60); // max total lifetime of a live link (minutes) incl. extensions
define('LD_CODE_ALPHABET', 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'); // no 0/O/1/I ambiguity
define('LD_CODE_AUTO_LEN', 6);      // length of auto-generated room codes
define('LD_CODE_MIN', 4);           // custom room code length limits
define('LD_CODE_MAX', 16);
define('LD_MAX_VIEWERS', 500);      // presence entries tracked per room
define('LD_LANGS', [                 // UI languages (app/lang/<code>.php)
    'fa' => ['name' => 'فارسی', 'short' => 'فا', 'dir' => 'rtl', 'digits' => 'fa', 'locale' => 'fa-IR'],
    'en' => ['name' => 'English', 'short' => 'EN', 'dir' => 'ltr', 'digits' => 'en', 'locale' => 'en-US'],
]);
define('LD_DEFAULT_LANG', 'fa');
define('LD_JSON_FLAGS', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

error_reporting(E_ALL);
ini_set('display_errors', '0');
date_default_timezone_set('UTC');

if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

// --- tiny mbstring polyfills (only used when the extension is missing) ---
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $s): int { return count(preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: []); }
}
if (!function_exists('mb_substr')) {
    function mb_substr(string $s, int $start, ?int $len = null): string {
        $chars = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return implode('', $len === null ? array_slice($chars, $start) : array_slice($chars, $start, $len));
    }
}

/**
 * Optional settings from config.php (see config.example.php).
 * Defaults: max_rooms_total = 300 live rooms on the server,
 *           max_rooms_per_ip = 30 live rooms per client address.
 */
function ld_config(string $key, $default = null)
{
    static $config = null;
    if ($config === null) {
        $config = [];
        $file = LD_ROOT . '/config.php';
        if (is_file($file)) {
            $loaded = require $file;
            if (is_array($loaded)) {
                $config = $loaded;
            }
        }
    }
    return array_key_exists($key, $config) ? $config[$key] : $default;
}

/* ---------------------------------------------------------------------------
 * i18n — UI language is chosen with ?lang=xx (stored in the ld_lang cookie);
 * falls back to config 'default_lang', then Persian.
 * ------------------------------------------------------------------------- */

/** Current UI language code ('fa' | 'en'). */
function ld_lang(): string
{
    static $lang = null;
    if ($lang !== null) {
        return $lang;
    }
    $pick = static function ($v): ?string {
        $v = is_string($v) ? strtolower(trim($v)) : '';
        return isset(LD_LANGS[$v]) ? $v : null;
    };
    $lang = $pick($_GET['lang'] ?? null)
        ?? $pick($_COOKIE['ld_lang'] ?? null)
        ?? $pick(ld_config('default_lang', LD_DEFAULT_LANG))
        ?? LD_DEFAULT_LANG;
    return $lang;
}

/** Text direction of the current language ('rtl' | 'ltr'). */
function ld_dir(): string
{
    return LD_LANGS[ld_lang()]['dir'];
}

/** Whole dictionary for a language. */
function ld_strings(?string $lang = null): array
{
    static $cache = [];
    $lang = $lang ?? ld_lang();
    if (!isset($cache[$lang])) {
        $file = LD_ROOT . '/app/lang/' . $lang . '.php';
        $loaded = is_file($file) ? require $file : [];
        $cache[$lang] = is_array($loaded) ? $loaded : [];
    }
    return $cache[$lang];
}

/** Translate a key; {0}, {1}… are replaced by $args. Falls back to the default language, then the key itself. */
function t(string $key, ...$args): string
{
    $s = ld_strings()[$key] ?? ld_strings(LD_DEFAULT_LANG)[$key] ?? $key;
    foreach ($args as $i => $a) {
        $s = str_replace('{' . $i . '}', (string) $a, $s);
    }
    return $s;
}

/** Translate + HTML-escape (for templates). */
function tx(string $key, ...$args): string
{
    return e(t($key, ...$args));
}

/** Strings exported to JavaScript (keys prefixed with "js."). */
function ld_js_strings(): array
{
    $out = [];
    foreach (ld_strings(LD_DEFAULT_LANG) + ld_strings() as $k => $v) {
        if (strpos($k, 'js.') === 0) {
            $out[substr($k, 3)] = ld_strings()[$k] ?? $v;
        }
    }
    return $out;
}

/** Remember the chosen language for a year. */
function set_lang_cookie(string $lang): void
{
    if (!isset(LD_LANGS[$lang]) || headers_sent()) {
        return;
    }
    setcookie('ld_lang', $lang, [
        'expires' => time() + 365 * 86400,
        'path' => base_url() . '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Current request path + query (minus "lang"), as a safe same-site relative URL.
 * Used for the language switch links and the post-switch redirect.
 */
function current_url_without_lang(): string
{
    $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $path = str_replace('\\', '/', $path);
    $path = '/' . ltrim($path, '/');          // never protocol-relative ("//host")
    $q = $_GET;
    unset($q['lang']);
    return $path . ($q ? '?' . http_build_query($q) : '');
}

/** Link that switches the UI language. */
function lang_switch_url(string $lang): string
{
    $u = current_url_without_lang();
    return $u . (strpos($u, '?') === false ? '?' : '&') . 'lang=' . rawurlencode($lang);
}

/** Per-request nonce for the few inline <script> blocks (Content-Security-Policy). */
function csp_nonce(): string
{
    static $nonce = null;
    if ($nonce === null) {
        $nonce = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    }
    return $nonce;
}

/**
 * Defensive HTTP headers. Everything the app needs is same-origin (scripts,
 * styles, fonts, XHR); the only inline scripts carry the per-request nonce.
 * Frame embedding is intentionally left open so the live view can be placed
 * inside a kiosk/dashboard page on the LAN.
 */
function security_headers(bool $html = true): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('X-Permitted-Cross-Domain-Policies: none');
    if ($html) {
        $csp = "default-src 'self'; script-src 'self' 'nonce-" . csp_nonce() . "'; style-src 'self'; img-src 'self' data:; "
            . "font-src 'self'; connect-src 'self'; worker-src 'self' blob:; child-src 'self' blob:; object-src 'none'; base-uri 'self'; form-action 'self'";
        // Embedding (kiosk / dashboard iframes) is allowed by default; set
        // 'frame_ancestors' => "'self'" (or "'none'") in config.php to restrict it.
        $ancestors = trim((string) ld_config('frame_ancestors', ''));
        if ($ancestors !== '' && preg_match("~^[A-Za-z0-9 .:*'/_-]+$~", $ancestors)) {
            $csp .= '; frame-ancestors ' . $ancestors;
        }
        header('Content-Security-Policy: ' . $csp);
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    }
}

/**
 * CSRF check for state-changing requests.
 *  1. Sec-Fetch-Site (all current browsers): "cross-site" is refused,
 *     "same-origin"/"none" is accepted regardless of proxies.
 *  2. Otherwise the Origin header must match Host / X-Forwarded-Host or one of
 *     config.php 'allowed_origins'. Requests without Origin (scripts) pass.
 */
function same_origin_request(): bool
{
    $site = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
    if ($site === 'cross-site') {
        return false;
    }
    if ($site === 'same-origin' || $site === 'none') {
        return true;
    }
    $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin === '' || strcasecmp($origin, 'null') === 0) {
        return $origin === ''; // "null" origin (sandboxed frame / file://) is refused
    }
    $oHost = strtolower((string) parse_url($origin, PHP_URL_HOST));
    if ($oHost === '') {
        return false;
    }
    $oPort = parse_url($origin, PHP_URL_PORT);
    $oHostPort = $oHost . ($oPort ? ':' . $oPort : '');
    $candidates = [];
    foreach (['HTTP_HOST', 'HTTP_X_FORWARDED_HOST', 'SERVER_NAME'] as $k) {
        foreach (explode(',', (string) ($_SERVER[$k] ?? '')) as $h) {
            $h = strtolower(trim($h));
            if ($h !== '') {
                $candidates[] = $h;
                $candidates[] = preg_replace('/:\d+$/', '', $h);
            }
        }
    }
    foreach ((array) ld_config('allowed_origins', []) as $a) {
        $a = strtolower(trim((string) $a));
        $candidates[] = (string) (parse_url($a, PHP_URL_HOST) ?: $a);
        $candidates[] = preg_replace('~^https?://~', '', rtrim($a, '/'));
    }
    return in_array($oHostPort, $candidates, true) || in_array($oHost, $candidates, true);
}

/** Client address used only for fair-use limits (never trusts proxy headers). */
function client_key(): string
{
    return substr(hash('sha256', 'ld-owner|' . ($_SERVER['REMOTE_ADDR'] ?? 'cli')), 0, 16);
}

spl_autoload_register(static function (string $class): void {
    $file = __DIR__ . DIRECTORY_SEPARATOR . $class . '.php';
    if (is_file($file)) {
        require $file;
    }
});

/** HTML-escape */
function e($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Current unix time in milliseconds */
function now_ms(): int
{
    return (int) floor(microtime(true) * 1000);
}

/** URL prefix where the app is mounted (e.g. "/luckydraw" or "") */
function base_url(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $dir = str_replace('\\', '/', dirname($script));
    return rtrim($dir, '/');
}

/** Versioned asset URL (cache busting) */
function asset(string $path): string
{
    return base_url() . '/assets/' . ltrim($path, '/') . '?v=' . LD_VERSION;
}

/** Send JSON and stop */
function json_out($data, int $status = 200): void
{
    http_response_code($status);
    security_headers(false);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    echo json_encode($data, LD_JSON_FLAGS);
    exit;
}

/** Send a JSON error and stop */
function json_fail(string $message, int $status = 400, string $code = 'error'): void
{
    json_out(['ok' => false, 'error' => $code, 'message' => $message, 'server_time' => now_ms()], $status);
}

/**
 * Best-effort LAN IPv4 of this server. Used to build share links when the
 * host opened the site through "localhost" (which other devices can't reach).
 * Never performs DNS lookups or sends packets (safe for offline networks).
 */
function server_lan_ip(): ?string
{
    $isLan = static function ($ip): bool {
        return is_string($ip)
            && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            && strpos($ip, '127.') !== 0
            && strpos($ip, '169.254.') !== 0;
    };

    if (!empty($_SERVER['SERVER_ADDR']) && $isLan($_SERVER['SERVER_ADDR'])) {
        return $_SERVER['SERVER_ADDR'];
    }

    // UDP "connect" does not transmit anything; it only asks the OS which
    // interface would be used to reach the target.
    foreach (['udp://10.255.255.255:9', 'udp://192.168.255.255:9', 'udp://172.31.255.255:9'] as $target) {
        $sock = @stream_socket_client($target, $errno, $errstr, 0.2);
        if ($sock) {
            $name = @stream_socket_get_name($sock, false);
            fclose($sock);
            if ($name) {
                $ip = explode(':', $name)[0];
                if ($isLan($ip)) {
                    return $ip;
                }
            }
        }
    }
    return null;
}

/** Render a view file with variables */
function render(string $view, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    require LD_ROOT . '/app/views/' . $view . '.php';
}

/** Render a full page (layout + content view) */
function page(array $page, string $view, array $vars = []): void
{
    security_headers(true);
    header('Vary: Cookie');                     // language lives in a cookie
    header('Content-Language: ' . ld_lang());
    if (!empty($page['no_store'])) {
        header('Cache-Control: private, no-store, max-age=0');
    }
    ob_start();
    render($view, $vars + ['page' => $page]);
    $content = ob_get_clean();
    render('layout', ['page' => $page, 'content' => $content]);
}
