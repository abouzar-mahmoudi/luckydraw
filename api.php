<?php
/**
 * قرعه‌کشی — JSON API
 *
 * All endpoints:  api.php?a=<action>
 *   GET  info                       server info (time, LAN ip, storage driver)
 *   POST roll    {tool,state}       stateless draw (no room) -> {result,next}
 *   POST create  {tool,state,ttl,title,code?} -> {room,token}   (code = optional custom room code)
 *   GET  room    ?code&v&vid        poll a live room (v = last seen version)
 *   POST state   {code,token,state} host: replace room state
 *   POST title   {code,token,title} host: rename
 *   POST draw    {code,token}       host: perform a draw -> {room,event}
 *   POST extend  {code,token,minutes}
 *   POST clear   {code,token}       host: clear history
 *   POST end     {code,token}       host: close the room
 *
 *   Registration forms ("signup"):
 *   POST signup_create  {tool,title,fields,auto,ttl,code?}      -> {signup,token}
 *   GET  signup         ?code[&token][&v]     public info; with a valid host token: entries too
 *   POST signup_register {code,name,code_value}                 participant registration
 *   POST signup_moderate {code,token,op:approve|reject|delete,entry|'*'}
 *   POST signup_set     {code,token,open?,auto?}                 host: open/close, auto-approve
 *   POST signup_extend  {code,token,minutes}
 *   POST signup_end     {code,token}
 *
 * Error envelope: {ok:false, error:<code>, message:<text in the UI language (?lang= / ld_lang cookie)>}
 */
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

const LD_MAX_BODY = 512 * 1024; // bytes

$action = isset($_GET['a']) && is_string($_GET['a']) ? preg_replace('/[^a-z_]/', '', $_GET['a']) : '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    json_fail(t('err.method'), 405, 'method');
}

// ---- read input --------------------------------------------------------------
$in = [];
if ($method === 'POST') {
    // CSRF guard: browser-originated cross-site POSTs are refused. Same-origin
    // requests and non-browser clients (curl, scripts without Origin) are unaffected.
    if (!same_origin_request()) {
        json_fail(t('err.cross_site'), 403, 'cross_site');
    }

    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > LD_MAX_BODY) {
        json_fail(t('err.too_large'), 413, 'too_large');
    }
    $raw = file_get_contents('php://input', false, null, 0, LD_MAX_BODY + 1) ?: '';
    if (strlen($raw) > LD_MAX_BODY) {
        json_fail(t('err.too_large'), 413, 'too_large');
    }
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== false || ($raw !== '' && ($raw[0] === '{' || $raw[0] === '['))) {
        $decoded = json_decode($raw, true, 32);
        if (!is_array($decoded)) {
            json_fail(t('err.bad_json'), 400, 'bad_json');
        }
        $in = $decoded;
    } else {
        $in = $_POST;
        if (isset($in['state']) && is_string($in['state'])) {
            $in['state'] = json_decode($in['state'], true, 32) ?: [];
        }
    }
}

/** Raw input value (body first, then query string). */
$get = static fn(string $k, $default = null) => $in[$k] ?? $_GET[$k] ?? $default;
/** Input value coerced to string ('' for arrays/objects/null). */
$str = static function (string $k, string $default = '') use ($get): string {
    $v = $get($k);
    return is_scalar($v) ? (string) $v : $default;
};
/** Input value coerced to int. */
$int = static function (string $k, int $default) use ($get): int {
    $v = $get($k);
    return is_numeric($v) ? (int) $v : $default;
};

try {
    switch ($action) {

        /* ------------------------------------------------------------------ */
        case 'info': {
            $storeName = null;
            $storeError = null;
            try {
                $storeName = Store::make()->name();
            } catch (Throwable $e) {
                error_log('[luckydraw] storage unavailable: ' . $e->getMessage());
                $storeError = t('err.storage');
            }
            json_out([
                'ok' => true,
                'app' => LD_APP_NAME,
                'version' => LD_VERSION,
                'server_time' => now_ms(),
                'lan_ip' => server_lan_ip(),
                'port' => (int) ($_SERVER['SERVER_PORT'] ?? 80),
                'https' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'store' => $storeName,
                'store_error' => $storeError,
                'ttl_options' => LD_TTL_OPTIONS,
                'max_ttl_total' => LD_MAX_TTL_TOTAL,
                'code_rules' => ['min' => LD_CODE_MIN, 'max' => LD_CODE_MAX, 'auto_len' => LD_CODE_AUTO_LEN],
                'signup_ttl_options' => Signup::TTL_OPTIONS,
                'lang' => ld_lang(),
            ]);
        }

        /* ------------------------------------------------------------------ */
        case 'roll': {
            if ($method !== 'POST') {
                json_fail(t('err.post_required'), 405, 'method');
            }
            $tool = $str('tool');
            if (!in_array($tool, Draw::TOOLS, true)) {
                json_fail(t('err.bad_tool'), 400, 'bad_tool');
            }
            $state = Draw::sanitizeState($tool, $get('state', []));
            [$result, $next] = Draw::run($tool, $state);
            json_out([
                'ok' => true,
                'server_time' => now_ms(),
                'event' => [
                    'id' => 'local-' . bin2hex(random_bytes(4)),
                    'at' => now_ms(),
                    'tool' => $tool,
                    'state' => $state,
                    'result' => $result,
                    'next' => $next,
                ],
            ]);
        }

        /* ------------------------------------------------------------------ */
        case 'create': {
            if ($method !== 'POST') {
                json_fail(t('err.post_required'), 405, 'method');
            }
            $rooms = new Room(Store::make());
            if (random_int(1, 5) === 1) {
                $rooms->store()->purgeExpired(now_ms());
            }
            $tool = $str('tool');
            if (!in_array($tool, Draw::TOOLS, true)) {
                json_fail(t('err.bad_tool'), 400, 'bad_tool');
            }
            $ttl = $int('ttl', 10);
            $customCode = trim($str('code'));
            $state = $get('state', []);
            [$room, $token] = $rooms->create(
                $tool,
                is_array($state) ? $state : [],
                $ttl,
                $str('title'),
                $customCode !== '' ? $customCode : null,
                client_key()
            );
            json_out([
                'ok' => true,
                'server_time' => now_ms(),
                'token' => $token,
                'room' => $rooms->publicView($room, true),
            ]);
        }

        /* ------------------------------------------------------------------ */
        case 'room': {
            $rooms = new Room(Store::make());
            $code = Room::normalizeCode($str('code'));
            if ($code === null) {
                json_fail(t('err.bad_code'), 404, 'not_found');
            }
            $room = $rooms->find($code);
            if ($room === null) {
                json_fail(t('err.not_found'), 404, 'not_found');
            }
            $vid = $str('vid');
            if ($vid !== '' && preg_match('/^[a-z0-9]{6,32}$/i', $vid)) {
                $rooms->store()->touchViewer($code, $vid, now_ms());
            }
            $seen = $int('v', -1);
            if ($seen === (int) $room['version']) {
                json_out([
                    'ok' => true,
                    'changed' => false,
                    'server_time' => now_ms(),
                    'version' => $room['version'],
                    'expires_at' => $room['expires_at'],
                    'viewers' => $rooms->store()->countViewers($code, now_ms() - 8000),
                ]);
            }
            json_out([
                'ok' => true,
                'changed' => true,
                'server_time' => now_ms(),
                'room' => $rooms->publicView($room),
            ]);
        }

        /* ------------------------------------------------------------------ */
        case 'state':
        case 'title':
        case 'draw':
        case 'extend':
        case 'clear':
        case 'end': {
            if ($method !== 'POST') {
                json_fail(t('err.post_required'), 405, 'method');
            }
            $rooms = new Room(Store::make());
            $code = Room::normalizeCode($str('code'));
            $room = $code !== null ? $rooms->find($code) : null;
            if ($room === null) {
                json_fail(t('err.not_found'), 404, 'not_found');
            }
            $token = $get('token');
            if (!$rooms->authorize($room, is_string($token) ? $token : null)) {
                json_fail(t('err.forbidden'), 403, 'forbidden');
            }

            if ($action === 'state') {
                $state = $get('state', []);
                $room = $rooms->updateState($room, is_array($state) ? $state : []);
            } elseif ($action === 'title') {
                $room = $rooms->updateTitle($room, $str('title'));
            } elseif ($action === 'draw') {
                // Optional: host may push the latest state atomically with the draw.
                if (is_array($get('state'))) {
                    $room = $rooms->updateState($room, $get('state'));
                }
                [$room, $event] = $rooms->draw($room);
                json_out([
                    'ok' => true,
                    'server_time' => now_ms(),
                    'event' => $event,
                    'room' => $rooms->publicView($room, true),
                ]);
            } elseif ($action === 'extend') {
                $room = $rooms->extend($room, $int('minutes', 10));
            } elseif ($action === 'clear') {
                $room = $rooms->clearHistory($room);
            } elseif ($action === 'end') {
                $rooms->end($room);
                json_out(['ok' => true, 'server_time' => now_ms(), 'ended' => true]);
            }

            json_out([
                'ok' => true,
                'server_time' => now_ms(),
                'room' => $rooms->publicView($room, true),
            ]);
        }

        /* ------------------------------------------------------------------ */
        case 'signup_create': {
            if ($method !== 'POST') {
                json_fail(t('err.post_required'), 405, 'method');
            }
            $signups = new Signup(Store::make());
            if (random_int(1, 5) === 1) {
                $signups->store()->purgeExpiredSignups(now_ms());
            }
            $customCode = trim($str('code'));
            [$doc, $token] = $signups->create(
                $str('tool'),
                $str('title'),
                $str('fields', 'name'),
                filter_var($get('auto', false), FILTER_VALIDATE_BOOLEAN),
                $int('ttl', Signup::DEFAULT_TTL),
                $customCode !== '' ? $customCode : null,
                client_key()
            );
            json_out(['ok' => true, 'server_time' => now_ms(), 'token' => $token, 'signup' => $signups->hostView($doc)]);
        }

        /* ------------------------------------------------------------------ */
        case 'signup': {
            $signups = new Signup(Store::make());
            $code = Room::normalizeCode($str('code'));
            $doc = $code !== null ? $signups->find($code) : null;
            if ($doc === null) {
                json_fail(t('err.signup_not_found'), 404, 'not_found');
            }
            $token = $get('token');
            $isHost = $signups->authorize($doc, is_string($token) ? $token : null);
            if ($isHost) {
                $seen = $int('v', -1);
                if ($seen === (int) $doc['version']) {
                    json_out(['ok' => true, 'changed' => false, 'server_time' => now_ms(), 'version' => $doc['version'], 'expires_at' => $doc['expires_at']]);
                }
                json_out(['ok' => true, 'changed' => true, 'server_time' => now_ms(), 'signup' => $signups->hostView($doc)]);
            }
            json_out(['ok' => true, 'server_time' => now_ms(), 'signup' => $signups->publicView($doc)]);
        }

        /* ------------------------------------------------------------------ */
        case 'signup_register': {
            if ($method !== 'POST') {
                json_fail(t('err.post_required'), 405, 'method');
            }
            $signups = new Signup(Store::make());
            $code = Room::normalizeCode($str('code'));
            $doc = $code !== null ? $signups->find($code) : null;
            if ($doc === null) {
                json_fail(t('err.signup_not_found'), 404, 'not_found');
            }
            [$doc, $entry] = $signups->register($doc, $str('name'), $str('code_value'), client_key());
            json_out(['ok' => true, 'server_time' => now_ms(), 'entry' => $entry, 'signup' => $signups->publicView($doc)]);
        }

        /* ------------------------------------------------------------------ */
        case 'signup_moderate':
        case 'signup_set':
        case 'signup_extend':
        case 'signup_end': {
            if ($method !== 'POST') {
                json_fail(t('err.post_required'), 405, 'method');
            }
            $signups = new Signup(Store::make());
            $code = Room::normalizeCode($str('code'));
            $doc = $code !== null ? $signups->find($code) : null;
            if ($doc === null) {
                json_fail(t('err.signup_not_found'), 404, 'not_found');
            }
            $token = $get('token');
            if (!$signups->authorize($doc, is_string($token) ? $token : null)) {
                json_fail(t('err.forbidden'), 403, 'forbidden');
            }
            if ($action === 'signup_moderate') {
                $doc = $signups->moderate($doc, $str('op'), $str('entry'));
            } elseif ($action === 'signup_set') {
                if ($get('open') !== null) {
                    $doc = $signups->setOpen($doc, filter_var($get('open'), FILTER_VALIDATE_BOOLEAN));
                }
                if ($get('auto') !== null) {
                    $doc = $signups->setAuto($doc, filter_var($get('auto'), FILTER_VALIDATE_BOOLEAN));
                }
            } elseif ($action === 'signup_extend') {
                $doc = $signups->extend($doc, $int('minutes', Signup::DEFAULT_TTL));
            } else {
                $signups->end($doc);
                json_out(['ok' => true, 'server_time' => now_ms(), 'ended' => true]);
            }
            json_out(['ok' => true, 'server_time' => now_ms(), 'signup' => $signups->hostView($doc)]);
        }

        default:
            json_fail(t('err.unknown_action'), 404, 'unknown_action');
    }
} catch (LdError $e) {
    // user-facing, translated
    json_fail($e->getMessage(), $e->status, $e->errorCode);
} catch (InvalidArgumentException $e) {
    error_log('[luckydraw] ' . get_class($e) . ': ' . $e->getMessage());
    json_fail(t('err.runtime'), 400, 'invalid');
} catch (RuntimeException $e) {
    // PDO / JSON / filesystem problems: never echo internals to the client.
    error_log('[luckydraw] ' . get_class($e) . ': ' . $e->getMessage());
    json_fail(t('err.runtime'), 422, 'runtime');
} catch (Throwable $e) {
    error_log('[luckydraw] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    json_fail(t('err.server'), 500, 'server');
}
