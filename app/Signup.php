<?php
declare(strict_types=1);

/**
 * Registration forms ("ثبت‌نام جهت قرعه‌کشی").
 *
 * The host opens a form for a tool (pick / wheel / teams); participants open
 * /signup/CODE and register with their name and/or a code (e.g. employee id).
 * Entries wait in a pending list until the host approves them (or the form
 * is set to auto-approve). Approved entries are what the host imports into
 * the draw list.
 *
 * Document shape:
 *  id, tool, title, fields ('name'|'code'|'both'), auto (bool), open (bool),
 *  owner, token_hash, created_at, expires_at, version,
 *  entries: [{id, name, code, at, status: 'pending'|'approved'|'rejected', ip}]
 */
final class Signup
{
    public const TOOLS = ['pick', 'wheel', 'teams'];
    public const FIELDS = ['name', 'code', 'both'];
    public const MAX_ENTRIES = 2000;
    public const MAX_NAME = 60;
    public const MAX_CODE = 32;
    /** minutes a form may stay open */
    public const TTL_OPTIONS = [60, 180, 360, 720, 1440, 2880, 4320, 10080];
    public const DEFAULT_TTL = 1440;
    /** max registrations from one client address per form (anti-spam) */
    public const PER_IP = 20;

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
     * @return array [signup, hostToken]
     */
    public function create(string $tool, string $title, string $fields, bool $auto, int $ttlMinutes, ?string $customCode, string $owner): array
    {
        if (!in_array($tool, self::TOOLS, true)) {
            throw new LdError('err.bad_tool', 400, 'bad_tool');
        }
        if (!in_array($fields, self::FIELDS, true)) {
            $fields = 'name';
        }
        if (!in_array($ttlMinutes, self::TTL_OPTIONS, true)) {
            $ttlMinutes = self::DEFAULT_TTL;
        }
        $custom = null;
        if ($customCode !== null && trim($customCode) !== '') {
            $custom = Room::normalizeCode($customCode);
            if ($custom === null) {
                throw new LdError('err.custom_code', 400, 'invalid', [LD_CODE_MIN, LD_CODE_MAX]);
            }
        }
        $now = now_ms();
        $maxTotal = (int) ld_config('max_signups_total', 200);
        $maxPerIp = (int) ld_config('max_signups_per_ip', 20);
        if ($maxTotal > 0 && $this->store->countActiveSignups($now) >= $maxTotal) {
            throw new LdError('err.server_full', 429, 'server_full');
        }
        if ($maxPerIp > 0 && $owner !== '' && $this->store->countActiveSignups($now, $owner) >= $maxPerIp) {
            throw new LdError('err.too_many_rooms', 429, 'too_many_rooms');
        }

        $token = bin2hex(random_bytes(20));
        $doc = [
            'id' => '',
            'tool' => $tool,
            'title' => Room::title($title),
            'fields' => $fields,
            'auto' => $auto,
            'open' => true,
            'custom' => $custom !== null,
            'owner' => $owner,
            'token_hash' => hash('sha256', $token),
            'created_at' => $now,
            'expires_at' => $now + $ttlMinutes * 60000,
            'ttl_minutes' => $ttlMinutes,
            'version' => 1,
            'entries' => [],
        ];
        if ($custom !== null) {
            $doc['id'] = $custom;
            if ($this->find($custom) !== null || !$this->store->insertSignup($doc)) {
                throw new RoomCodeTakenException();
            }
            return [$doc, $token];
        }
        for ($try = 0; $try < 20; $try++) {
            $doc['id'] = self::randomCode();
            $this->find($doc['id']);
            if ($this->store->insertSignup($doc)) {
                return [$doc, $token];
            }
        }
        throw new LdError('err.no_code', 503, 'no_code');
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

    /** Live form or null (expired forms are deleted on access). */
    public function find(string $code): ?array
    {
        $doc = $this->store->getSignup($code);
        if ($doc === null) {
            return null;
        }
        if ((int) $doc['expires_at'] < now_ms()) {
            $this->store->deleteSignup($code);
            return null;
        }
        return $doc;
    }

    public function authorize(array $doc, ?string $token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }
        return hash_equals($doc['token_hash'], hash('sha256', $token));
    }

    /* ------------------------------------------------------------------ */

    /** What a participant may see (no entries of other people). */
    public function publicView(array $doc): array
    {
        $counts = self::counts($doc);
        return [
            'id' => $doc['id'],
            'tool' => $doc['tool'],
            'title' => $doc['title'],
            'fields' => $doc['fields'],
            'auto' => !empty($doc['auto']),
            'open' => !empty($doc['open']),
            'expires_at' => $doc['expires_at'],
            'created_at' => $doc['created_at'],
            'approved' => $counts['approved'],
            'total' => $counts['total'],
        ];
    }

    /** Everything the host needs (entries included, IPs excluded). */
    public function hostView(array $doc): array
    {
        $out = $this->publicView($doc);
        $out['custom'] = !empty($doc['custom']);
        $out['ttl_minutes'] = $doc['ttl_minutes'];
        $out['version'] = $doc['version'];
        $out['pending'] = self::counts($doc)['pending'];
        $out['entries'] = array_values(array_map(static function (array $e): array {
            return ['id' => $e['id'], 'name' => $e['name'], 'code' => $e['code'], 'at' => $e['at'], 'status' => $e['status']];
        }, $doc['entries']));
        return $out;
    }

    public static function counts(array $doc): array
    {
        $c = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
        foreach ($doc['entries'] as $e) {
            $c['total']++;
            $c[$e['status']] = ($c[$e['status']] ?? 0) + 1;
        }
        return $c;
    }

    /* ------------------------------------------------------------------ */

    private static function cleanName($v): string
    {
        $v = trim(preg_replace('/\s+/u', ' ', (string) $v) ?? '');
        $v = preg_replace('/[\x00-\x1F\x7F]/u', '', $v) ?? '';
        return mb_strlen($v) > self::MAX_NAME ? mb_substr($v, 0, self::MAX_NAME) : $v;
    }

    private static function cleanCode($v): string
    {
        $v = trim((string) $v);
        $v = str_replace(['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'], ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], $v);
        $v = str_replace(['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'], ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], $v);
        $v = preg_replace('/[^\p{L}\p{N}\-_.\/]/u', '', $v) ?? '';
        return mb_strlen($v) > self::MAX_CODE ? mb_substr($v, 0, self::MAX_CODE) : $v;
    }

    /**
     * Participant registration. Duplicate name/code (case-insensitive) is refused.
     * @return array [doc, entry]
     */
    public function register(array $doc, $name, $code, string $clientKey): array
    {
        if (empty($doc['open'])) {
            throw new LdError('err.signup_closed', 409, 'signup_closed');
        }
        $name = self::cleanName($name);
        $code = self::cleanCode($code);
        $fields = $doc['fields'];
        if (($fields === 'name' || $fields === 'both') && $name === '') {
            throw new LdError('err.signup_name_required', 400, 'invalid');
        }
        if (($fields === 'code' || $fields === 'both') && $code === '') {
            throw new LdError('err.signup_code_required', 400, 'invalid');
        }
        if ($fields === 'name') {
            $code = '';
        } elseif ($fields === 'code') {
            $name = '';
        }
        if (count($doc['entries']) >= self::MAX_ENTRIES) {
            throw new LdError('err.signup_full', 409, 'signup_full');
        }
        $fromIp = 0;
        foreach ($doc['entries'] as $e) {
            if (($e['ip'] ?? '') === $clientKey) {
                $fromIp++;
            }
            if ($code !== '' && mb_strtolower($e['code']) === mb_strtolower($code)) {
                throw new LdError('err.signup_duplicate_code', 409, 'duplicate');
            }
            if ($code === '' && $name !== '' && mb_strtolower($e['name']) === mb_strtolower($name)) {
                throw new LdError('err.signup_duplicate_name', 409, 'duplicate');
            }
        }
        if ($fromIp >= self::PER_IP) {
            throw new LdError('err.signup_rate', 429, 'rate_limited');
        }
        $entry = [
            'id' => bin2hex(random_bytes(6)),
            'name' => $name,
            'code' => $code,
            'at' => now_ms(),
            'status' => !empty($doc['auto']) ? 'approved' : 'pending',
            'ip' => $clientKey,
        ];
        $doc['entries'][] = $entry;
        $doc['version']++;
        $this->store->putSignup($doc);
        unset($entry['ip']);
        return [$doc, $entry];
    }

    /** Host moderation: approve / reject / delete one entry, or all pending. */
    public function moderate(array $doc, string $action, $entryId): array
    {
        if (!in_array($action, ['approve', 'reject', 'delete'], true)) {
            throw new LdError('err.unknown_action', 400, 'unknown_action');
        }
        $all = $entryId === '*' || $entryId === null || $entryId === '';
        $changed = false;
        foreach ($doc['entries'] as $k => $e) {
            if (!$all && $e['id'] !== $entryId) {
                continue;
            }
            if ($all && $action !== 'delete' && $e['status'] !== 'pending') {
                continue;
            }
            if ($action === 'approve') {
                $doc['entries'][$k]['status'] = 'approved';
            } elseif ($action === 'reject') {
                $doc['entries'][$k]['status'] = 'rejected';
            } else {
                unset($doc['entries'][$k]);
            }
            $changed = true;
        }
        if ($changed) {
            $doc['entries'] = array_values($doc['entries']);
            $doc['version']++;
            $this->store->putSignup($doc);
        }
        return $doc;
    }

    public function setOpen(array $doc, bool $open): array
    {
        $doc['open'] = $open;
        $doc['version']++;
        $this->store->putSignup($doc);
        return $doc;
    }

    public function setAuto(array $doc, bool $auto): array
    {
        $doc['auto'] = $auto;
        $doc['version']++;
        $this->store->putSignup($doc);
        return $doc;
    }

    public function extend(array $doc, int $minutes): array
    {
        if (!in_array($minutes, self::TTL_OPTIONS, true)) {
            $minutes = self::DEFAULT_TTL;
        }
        $max = $doc['created_at'] + 30 * 24 * 60 * 60000; // at most 30 days in total
        $base = max((int) $doc['expires_at'], now_ms());
        $doc['expires_at'] = min($max, $base + $minutes * 60000);
        $doc['version']++;
        $this->store->putSignup($doc);
        return $doc;
    }

    public function end(array $doc): void
    {
        $this->store->deleteSignup($doc['id']);
    }

    /** Display label of an entry inside the draw list ("Name (CODE)" / "CODE"). */
    public static function label(array $e): string
    {
        if ($e['name'] !== '' && $e['code'] !== '') {
            return $e['name'] . ' (' . $e['code'] . ')';
        }
        return $e['name'] !== '' ? $e['name'] : $e['code'];
    }
}
