<?php
declare(strict_types=1);

/**
 * Live room management (create / read / draw / extend / end).
 *
 * A room is a short-lived shared session identified by a code: either an
 * auto-generated 6-character code or a custom one chosen by the host
 * (4–16 letters/digits/dashes, e.g. "JASHN-1404").
 * The host proves ownership with a secret token (only its SHA-256 is stored).
 */
final class Room
{
    public const HISTORY_MAX = 200;

    private Store $store;

    public function __construct(Store $store)
    {
        $this->store = $store;
    }

    public function store(): Store
    {
        return $this->store;
    }

    /* ------------------------------------------------------------------ */

    /**
     * Canonical form of a room code, or null when it cannot be one.
     * Accepts Persian/Arabic digits and lower-case, drops spaces/underscores,
     * keeps A–Z, 0–9 and single dashes between groups.
     */
    public static function normalizeCode($code): ?string
    {
        if (!is_string($code) && !is_int($code)) {
            return null;
        }
        $code = (string) $code;
        if (strlen($code) > 64) {
            return null;
        }
        $code = str_replace(['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'], ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], $code);
        $code = str_replace(['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'], ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], $code);
        $code = strtoupper(trim($code));
        $code = preg_replace('/[\s_]+/', '-', $code) ?? '';
        $code = preg_replace('/[^A-Z0-9-]/', '', $code) ?? '';
        $code = trim(preg_replace('/-+/', '-', $code) ?? '', '-');
        $len = strlen($code);
        if ($len < LD_CODE_MIN || $len > LD_CODE_MAX) {
            return null;
        }
        if (!preg_match('/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/', $code)) {
            return null;
        }
        return $code;
    }

    private static function randomCode(): string
    {
        $alphabet = LD_CODE_ALPHABET;
        $len = strlen($alphabet);
        $code = '';
        for ($i = 0; $i < LD_CODE_AUTO_LEN; $i++) {
            $code .= $alphabet[random_int(0, $len - 1)];
        }
        return $code;
    }

    private static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /* ------------------------------------------------------------------ */

    /**
     * Create a room. $customCode (optional) is the host's own code; when it is
     * null/empty a random 6-character code is generated.
     *
     * @return array [room, hostToken]
     * @throws InvalidArgumentException  bad tool / bad custom code
     * @throws RoomCodeTakenException     custom code already in use
     * @throws RuntimeException           server is full / cannot allocate a code
     */
    public function create(string $tool, array $state, int $ttlMinutes, string $title, ?string $customCode = null, string $owner = ''): array
    {
        if (!in_array($tool, Draw::TOOLS, true)) {
            throw new LdError('err.bad_tool', 400, 'bad_tool');
        }
        if (!in_array($ttlMinutes, LD_TTL_OPTIONS, true)) {
            $ttlMinutes = 10;
        }
        $custom = null;
        if ($customCode !== null && trim($customCode) !== '') {
            $custom = self::normalizeCode($customCode);
            if ($custom === null) {
                throw new LdError('err.custom_code', 400, 'invalid', [LD_CODE_MIN, LD_CODE_MAX]);
            }
        }

        $now = now_ms();
        // fair-use limits (configurable in config.php)
        $maxTotal = (int) ld_config('max_rooms_total', 300);
        $maxPerIp = (int) ld_config('max_rooms_per_ip', 30);
        if ($maxTotal > 0 && $this->store->countActive($now) >= $maxTotal) {
            throw new LdError('err.server_full', 429, 'server_full');
        }
        if ($maxPerIp > 0 && $owner !== '' && $this->store->countActive($now, $owner) >= $maxPerIp) {
            throw new LdError('err.too_many_rooms', 429, 'too_many_rooms');
        }

        $token = bin2hex(random_bytes(20));
        $room = [
            'id' => '',
            'tool' => $tool,
            'title' => self::title($title),
            'custom' => $custom !== null,
            'owner' => $owner,
            'token_hash' => self::hashToken($token),
            'created_at' => $now,
            'expires_at' => $now + $ttlMinutes * 60000,
            'ttl_minutes' => $ttlMinutes,
            'version' => 1,
            'state' => Draw::sanitizeState($tool, $state),
            'event' => null,
            'history' => [],
            'ended' => false,
        ];

        if ($custom !== null) {
            $room['id'] = $custom;
            // An expired/ended room with the same code can be replaced.
            if ($this->find($custom) !== null || !$this->store->insert($room)) {
                throw new RoomCodeTakenException();
            }
            return [$room, $token];
        }

        for ($try = 0; $try < 20; $try++) {
            $room['id'] = self::randomCode();
            $this->find($room['id']);              // drops an expired leftover with this code, if any
            if ($this->store->insert($room)) {     // atomic: fails when the code is live
                return [$room, $token];
            }
        }
        throw new LdError('err.no_code', 503, 'no_code');
    }

    public static function title($t): string
    {
        $t = trim(preg_replace('/\s+/u', ' ', (string) $t) ?? '');
        return mb_strlen($t) > 60 ? mb_substr($t, 0, 60) : $t;
    }

    /** Fetch a live room; returns null when missing or expired (expired rooms are deleted). */
    public function find(string $code): ?array
    {
        $room = $this->store->get($code);
        if ($room === null) {
            return null;
        }
        if ((int) $room['expires_at'] < now_ms() || !empty($room['ended'])) {
            $this->store->delete($code);
            return null;
        }
        return $room;
    }

    public function authorize(array $room, ?string $token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }
        return hash_equals($room['token_hash'], self::hashToken($token));
    }

    /** Public projection sent to clients (never includes the token hash). */
    public function publicView(array $room, bool $forHost = false): array
    {
        $now = now_ms();
        $out = [
            'id' => $room['id'],
            'tool' => $room['tool'],
            'title' => $room['title'],
            'custom' => !empty($room['custom']),
            'created_at' => $room['created_at'],
            'expires_at' => $room['expires_at'],
            'ttl_minutes' => $room['ttl_minutes'],
            'max_expires_at' => $room['created_at'] + LD_MAX_TTL_TOTAL * 60000,
            'version' => $room['version'],
            'state' => $room['state'],
            'event' => $room['event'],
            'history' => $room['history'],
            'viewers' => $this->store->countViewers($room['id'], $now - 8000),
        ];
        return $out;
    }

    /* ------------------------------------------------------------------ */

    public function updateState(array $room, array $state): array
    {
        $room['state'] = Draw::sanitizeState($room['tool'], $state);
        $room['version']++;
        $this->store->put($room);
        return $room;
    }

    public function updateTitle(array $room, string $title): array
    {
        $room['title'] = self::title($title);
        $room['version']++;
        $this->store->put($room);
        return $room;
    }

    public function draw(array $room): array
    {
        $tool = $room['tool'];
        $state = $room['state'];
        [$result, $next] = Draw::run($tool, $state);
        $now = now_ms();
        $event = [
            'id' => $room['id'] . '-' . ($room['version'] + 1) . '-' . bin2hex(random_bytes(3)),
            'at' => $now,
            'tool' => $tool,
            'state' => $state,
            'result' => $result,
            'next' => $next,
        ];
        $room['event'] = $event;
        $room['state'] = $next;
        $room['version']++;
        $entry = [
            'at' => $now,
            'text' => self::historyLabel($tool, $result, $state),
            'items' => self::historyItems($tool, $result, $state),
        ];
        if ($tool === 'coin') {
            $entry['labels'] = [$state['heads'], $state['tails']];
        }
        array_unshift($room['history'], $entry);
        if (count($room['history']) > self::HISTORY_MAX) {
            $room['history'] = array_slice($room['history'], 0, self::HISTORY_MAX);
        }
        $this->store->put($room);
        return [$room, $event];
    }

    public function clearHistory(array $room): array
    {
        $room['history'] = [];
        $room['event'] = null;
        $room['version']++;
        $this->store->put($room);
        return $room;
    }

    public function extend(array $room, int $minutes): array
    {
        if (!in_array($minutes, LD_TTL_OPTIONS, true)) {
            $minutes = 10;
        }
        $max = $room['created_at'] + LD_MAX_TTL_TOTAL * 60000;
        $base = max((int) $room['expires_at'], now_ms());
        $room['expires_at'] = min($max, $base + $minutes * 60000);
        $room['version']++;
        $this->store->put($room);
        return $room;
    }

    public function end(array $room): void
    {
        $this->store->delete($room['id']);
    }

    /* ------------------------------------------------------------------ */

    public static function historyItems(string $tool, array $result, array $state = []): array
    {
        switch ($tool) {
            case 'teams':
                $out = [];
                foreach ($result['groups'] as $g => $members) {
                    $out[] = Draw::groupName($state, $g) . ': ' . implode(t('js.sep'), array_map(static fn($i) => $state['items'][$i]['n'] ?? '', $members));
                }
                return $out;
            case 'coin':
                return $result['sides'];
            case 'number':
                return $result['numbers'];
            case 'pick':
                return array_map(static fn($it) => $it['n'], $result['picked']);
            case 'wheel':
                return [$result['winner']['n']];
        }
        return [];
    }

    public static function historyLabel(string $tool, array $result, array $state = []): string
    {
        switch ($tool) {
            case 'teams':
                return implode(' | ', self::historyItems($tool, $result, $state));
            case 'coin':
                return implode(', ', array_map(static fn($s) => $s === 0 ? 'H' : 'T', $result['sides']));
            case 'number':
                return implode(', ', $result['numbers']);
            case 'pick':
                return implode(', ', array_map(static fn($it) => $it['n'], $result['picked']));
            case 'wheel':
                return $result['winner']['n'];
        }
        return '';
    }
}
