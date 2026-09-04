<?php
/**
 * LuckyDraw / قرعه‌کشی  (front controller)
 *
 * Routes (pretty URLs need mod_rewrite/.htaccess, nginx rule, or the
 * built-in server router; the ?p= / ?r= query forms always work):
 *   /                                   home
 *   /coin /number /pick /wheel /teams   tool pages (host)
 *   /live/CODE   or  /?r=CODE           live viewer page  (/l/CODE = legacy alias)
 *   /signup/CODE or  /?s=CODE           public registration page (ثبت‌نام جهت قرعه‌کشی)
 */
declare(strict_types=1);

// ---- PHP built-in server router mode:  php -S 0.0.0.0:8080 index.php ----
if (PHP_SAPI === 'cli-server') {
    $reqPath = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    if (preg_match('~^/(storage|app)(/|$)~', $reqPath) || strpos($reqPath, '..') !== false || strpos($reqPath, "\0") !== false) {
        http_response_code(404);
        exit;
    }
    $file = realpath(__DIR__ . $reqPath);
    if ($reqPath !== '/' && $file !== false && is_file($file) && strpos($file, __DIR__ . DIRECTORY_SEPARATOR) === 0
        && $file !== __FILE__) {
        if (preg_match('~\.(php|phtml|sqlite|json|md|lock|htaccess|bat|sh|conf)$~i', $file) && $file !== __DIR__ . DIRECTORY_SEPARATOR . 'api.php') {
            http_response_code(404); // only api.php may be executed / served directly
            exit;
        }
        return false; // serve static file / execute api.php directly
    }
    $_SERVER['LD_REWRITE'] = '1';
}

require __DIR__ . '/app/bootstrap.php';

// ---- language switch: /any/page?lang=en  → remember + redirect to the clean URL ----
if (isset($_GET['lang'])) {
    $lang = is_string($_GET['lang']) ? strtolower($_GET['lang']) : '';
    if (isset(LD_LANGS[$lang])) {
        set_lang_cookie($lang);
    }
    security_headers(false);
    header('Cache-Control: no-store');
    header('Location: ' . current_url_without_lang(), true, 303);
    exit;
}

/** True when clean URLs are known to work (.htaccess / nginx / cli-server). */
function pretty_urls(): bool
{
    return !empty($_SERVER['LD_REWRITE']) || !empty($_SERVER['REDIRECT_LD_REWRITE'])
        || (!empty($_SERVER['HTTP_X_LD_REWRITE']));
}

/** Build an in-app URL for a route */
function route_url(string $route, array $q = []): string
{
    $base = base_url();
    if (pretty_urls()) {
        $u = $base . '/' . ltrim($route, '/');
        if ($route === '' || $route === '/') {
            $u = $base . '/';
        }
        return $u . ($q ? '?' . http_build_query($q) : '');
    }
    if ($route === '' || $route === '/') {
        return $base . '/' . ($q ? '?' . http_build_query($q) : '');
    }
    if (preg_match('~^live/([A-Z0-9-]{1,64})$~', $route, $m)) {
        return $base . '/?' . http_build_query(['r' => $m[1]] + $q);
    }
    if (preg_match('~^signup/([A-Z0-9-]{1,64})$~', $route, $m)) {
        return $base . '/?' . http_build_query(['s' => $m[1]] + $q);
    }
    return $base . '/index.php?' . http_build_query(['p' => $route] + $q);
}

/** Live-viewer URL for a room code (pretty:  /live/CODE   fallback:  /?r=CODE). */
function live_url(string $code): string
{
    return route_url('live/' . $code);
}

/** Public registration URL for a signup code (pretty:  /signup/CODE   fallback:  /?s=CODE). */
function signup_url(string $code): string
{
    return route_url('signup/' . $code);
}

// ---- resolve route ---------------------------------------------------------
$route = '';
$liveCode = null;   // raw code when the request targets the live viewer
$signupCode = null; // raw code when the request targets the registration page
if (isset($_GET['r'])) {
    $liveCode = is_string($_GET['r']) ? $_GET['r'] : '';
} elseif (isset($_GET['s'])) {
    $signupCode = is_string($_GET['s']) ? $_GET['s'] : '';
} elseif (isset($_GET['p'])) {
    $route = is_string($_GET['p']) ? trim($_GET['p'], '/') : '';
} else {
    $uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $base = base_url();
    if ($base !== '' && strpos($uri, $base) === 0) {
        $uri = substr($uri, strlen($base));
    }
    $route = trim(preg_replace('~^/index\.php~', '', $uri) ?? '', '/');
}
if ($liveCode === null && preg_match('~^(?:live|l)/(.{1,64})$~su', $route, $m)) {
    $liveCode = $m[1];
}
if ($signupCode === null && preg_match('~^(?:signup|reg|s)/(.{1,64})$~su', $route, $m)) {
    $signupCode = $m[1];
}

$tools = [];
foreach (['coin' => 'fa-coins', 'number' => 'fa-dice', 'pick' => 'fa-list-check', 'wheel' => 'fa-dharmachakra', 'teams' => 'fa-people-group'] as $key => $icon) {
    $tools[$key] = ['title' => t('tool.' . $key), 'icon' => $icon, 'desc' => t('tool.' . $key . '.desc')];
}

if ($liveCode !== null) {
    $room = null;
    $code = Room::normalizeCode($liveCode);
    try {
        $rooms = new Room(Store::make());
        if ($code !== null) {
            $room = $rooms->find($code);
        }
    } catch (Throwable $e) {
        error_log('[luckydraw] live page: ' . $e->getMessage());
        $room = null;
    }
    if ($room === null) {
        http_response_code(404);
    }
    page(
        [
            'title' => $room ? ($room['title'] !== '' ? $room['title'] : $tools[$room['tool']]['title']) . ' — ' . t('page.live_suffix') : t('page.invalid_link'),
            'body_class' => 'page-live',
            'tool' => $room['tool'] ?? null,
            'code' => $room['id'] ?? $code,
            'no_store' => true,
        ],
        'live',
        ['room' => $room, 'code' => $room['id'] ?? $code, 'tools' => $tools]
    );
    exit;
}

if ($signupCode !== null) {
    $signup = null;
    $code = Room::normalizeCode($signupCode);
    try {
        $signups = new Signup(Store::make());
        if ($code !== null) {
            $signup = $signups->find($code);
        }
    } catch (Throwable $e) {
        error_log('[luckydraw] signup page: ' . $e->getMessage());
        $signup = null;
    }
    if ($signup === null) {
        http_response_code(404);
    }
    page(
        [
            'title' => t('signup.page_title') . ($signup && $signup['title'] !== '' ? ' — ' . $signup['title'] : ''),
            'body_class' => 'page-signup',
            'tool' => $signup['tool'] ?? null,
            'code' => $signup['id'] ?? $code,
            'no_store' => true,
        ],
        'signup',
        ['signup' => $signup ? $signups->publicView($signup) : null, 'code' => $signup['id'] ?? $code, 'tools' => $tools]
    );
    exit;
}

if ($route === '') {
    page(['title' => t('app.tagline'), 'body_class' => 'page-home'], 'home', ['tools' => $tools]);
    exit;
}

if (isset($tools[$route])) {
    page(
        ['title' => $tools[$route]['title'], 'body_class' => 'page-tool page-' . $route, 'tool' => $route],
        'tool',
        ['tool' => $route, 'tools' => $tools]
    );
    exit;
}

http_response_code(404);
page(['title' => t('page.notfound'), 'body_class' => 'page-404'], '404', ['tools' => $tools]);
