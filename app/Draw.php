<?php
declare(strict_types=1);

/**
 * Pure random "draw" logic + state sanitising.
 *
 * All randomness comes from random_int() (CSPRNG). The JavaScript side has an
 * equivalent implementation (crypto.getRandomValues) for offline/local mode;
 * the *result structure* produced here is what the front-end animates.
 */
final class Draw
{
    public const TOOLS = ['coin', 'number', 'pick', 'wheel', 'teams'];

    /* ------------------------------------------------------------------ */
    /* state sanitising                                                    */
    /* ------------------------------------------------------------------ */

    public static function sanitizeState(string $tool, $state): array
    {
        $s = is_array($state) ? $state : [];
        switch ($tool) {
            case 'coin':
                return [
                    'heads' => self::label($s['heads'] ?? '', t('draw.heads'), 24),
                    'tails' => self::label($s['tails'] ?? '', t('draw.tails'), 24),
                    'count' => self::intBetween($s['count'] ?? 1, 1, 10, 1),
                ];

            case 'number':
                $min = self::intBetween($s['min'] ?? 1, -1000000000, 1000000000, 1);
                $max = self::intBetween($s['max'] ?? 100, -1000000000, 1000000000, 100);
                if ($min > $max) {
                    [$min, $max] = [$max, $min];
                }
                $unique = !empty($s['unique']);
                $count = self::intBetween($s['count'] ?? 1, 1, 100, 1);
                if ($unique) {
                    $span = $max - $min + 1;
                    if ($count > $span) {
                        $count = max(1, (int) $span);
                    }
                }
                return [
                    'min' => $min,
                    'max' => $max,
                    'count' => $count,
                    'unique' => $unique,
                    'sort' => !empty($s['sort']),
                ];

            case 'pick':
                $items = self::items($s['items'] ?? []);
                return [
                    'items' => $items,
                    'count' => self::intBetween($s['count'] ?? 1, 1, 100, 1),
                    'remove' => !empty($s['remove']),
                ];

            case 'wheel':
                return [
                    'items' => self::items($s['items'] ?? []),
                    'remove' => !empty($s['remove']),
                    'duration' => self::intBetween($s['duration'] ?? 7, 3, 15, 7),
                ];

            case 'teams':
                $by = ($s['by'] ?? 'groups') === 'size' ? 'size' : 'groups';
                return [
                    'items' => self::items($s['items'] ?? []),
                    'by' => $by,
                    'n' => self::intBetween($s['n'] ?? 2, 1, 100, 2),
                    'names' => self::names($s['names'] ?? []),
                ];
        }
        throw new InvalidArgumentException('Unknown tool');
    }

    /** Optional custom group names (max 50, 30 chars each). */
    public static function names($raw): array
    {
        if (is_string($raw)) {
            $raw = preg_split('/[\n,،;؛]+/u', $raw) ?: [];
        }
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $n) {
            $n = trim(preg_replace('/\s+/u', ' ', (string) $n) ?? '');
            if ($n === '') {
                continue;
            }
            $out[] = mb_strlen($n) > 30 ? mb_substr($n, 0, 30) : $n;
            if (count($out) >= 50) {
                break;
            }
        }
        return $out;
    }

    /** Resolve the display name of group $i (0-based). */
    public static function groupName(array $state, int $i): string
    {
        $names = $state['names'] ?? [];
        return isset($names[$i]) && $names[$i] !== '' ? $names[$i] : t('draw.group', $i + 1);
    }

    /** Number of groups implied by the state (by count or by size). */
    public static function groupCount(array $state, int $members): int
    {
        $n = (int) ($state['n'] ?? 2);
        if (($state['by'] ?? 'groups') === 'size') {
            $n = (int) ceil($members / max(1, $n));
        }
        return max(1, min($n, max(1, $members)));
    }

    /** Normalise a list of {n: name, w: weight} entries. */
    public static function items($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $it) {
            if (is_string($it)) {
                $it = ['n' => $it, 'w' => 1];
            }
            if (!is_array($it)) {
                continue;
            }
            $name = trim(preg_replace('/\s+/u', ' ', (string) ($it['n'] ?? '')) ?? '');
            if ($name === '') {
                continue;
            }
            if (mb_strlen($name) > LD_MAX_ITEM_LEN) {
                $name = mb_substr($name, 0, LD_MAX_ITEM_LEN);
            }
            $out[] = ['n' => $name, 'w' => self::weight($it['w'] ?? 1)];
            if (count($out) >= LD_MAX_ITEMS) {
                break;
            }
        }
        return $out;
    }

    /**
     * Normalise an entry weight. Weights may be decimal (0.25, 0.5, 2.5 …):
     * they are clamped to 0.01–100 and rounded to two decimals. Integers are
     * kept as int so existing lists serialise exactly as before ("name*3").
     * @return int|float
     */
    public static function weight($v)
    {
        if (is_string($v)) {
            $v = str_replace(['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٫', ','], ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '.', '.'], trim($v));
        }
        if (!is_numeric($v)) {
            return 1;
        }
        $w = round(max(0.01, min(100.0, (float) $v)), 2);
        return floor($w) == $w ? (int) $w : $w;
    }

    private static function label($v, string $default, int $max): string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return $default;
        }
        return mb_strlen($v) > $max ? mb_substr($v, 0, $max) : $v;
    }

    public static function intBetween($v, int $min, int $max, int $default): int
    {
        if (!is_numeric($v)) {
            return $default;
        }
        $v = (int) $v;
        return max($min, min($max, $v));
    }

    /* ------------------------------------------------------------------ */
    /* result generators                                                   */
    /* ------------------------------------------------------------------ */

    /**
     * Produce an event result for $tool from the current $state.
     * Returns [result, newState] — newState may differ (e.g. winner removed).
     */
    public static function run(string $tool, array $state, array $payload = []): array
    {
        switch ($tool) {
            case 'coin':
                return self::coin($state);
            case 'number':
                return self::number($state);
            case 'pick':
                return self::pick($state);
            case 'wheel':
                return self::wheel($state);
            case 'teams':
                return self::teams($state);
        }
        throw new InvalidArgumentException('Unknown tool');
    }

    private static function coin(array $state): array
    {
        $count = $state['count'];
        $sides = [];
        for ($i = 0; $i < $count; $i++) {
            $sides[] = random_int(0, 1); // 0 = heads, 1 = tails
        }
        $result = [
            'sides' => $sides,
            'flips' => random_int(7, 11),        // half-turns of the 3D coin
            'duration' => 2600,
        ];
        return [$result, $state];
    }

    private static function number(array $state): array
    {
        $min = $state['min'];
        $max = $state['max'];
        $count = $state['count'];
        $numbers = [];
        if ($state['unique']) {
            $span = $max - $min + 1;
            if ($span <= 5000) {
                // small range: shuffle a pool (Fisher–Yates with random_int)
                $pool = range($min, $max);
                for ($i = count($pool) - 1; $i > 0; $i--) {
                    $j = random_int(0, $i);
                    [$pool[$i], $pool[$j]] = [$pool[$j], $pool[$i]];
                }
                $numbers = array_slice($pool, 0, $count);
            } else {
                $seen = [];
                while (count($numbers) < $count) {
                    $n = random_int($min, $max);
                    if (!isset($seen[$n])) {
                        $seen[$n] = true;
                        $numbers[] = $n;
                    }
                }
            }
        } else {
            for ($i = 0; $i < $count; $i++) {
                $numbers[] = random_int($min, $max);
            }
        }
        if ($state['sort']) {
            sort($numbers);
        }
        $result = [
            'numbers' => array_values($numbers),
            'duration' => 2200 + min(6, $count) * 350,
        ];
        return [$result, $state];
    }

    private static function pick(array $state): array
    {
        $items = $state['items'];
        if (count($items) === 0) {
            throw new LdError('err.empty_list');
        }
        $count = min($state['count'], count($items));
        $indexes = self::weightedSample($items, $count);
        $picked = array_map(static fn(int $i) => $items[$i], $indexes);

        $newState = $state;
        if ($state['remove']) {
            $remove = array_flip($indexes);
            $newState['items'] = array_values(array_filter($items, static fn($it, $i) => !isset($remove[$i]), ARRAY_FILTER_USE_BOTH));
        }
        $result = [
            'indexes' => $indexes,
            'picked' => $picked,
            'duration' => 2600 + min(10, $count) * 700,
        ];
        return [$result, $newState];
    }

    private static function wheel(array $state): array
    {
        $items = $state['items'];
        if (count($items) < 2) {
            throw new LdError('err.min_two_options');
        }
        $index = self::weightedSample($items, 1)[0];
        $newState = $state;
        if ($state['remove']) {
            $newState['items'] = array_values(array_filter($items, static fn($it, $i) => $i !== $index, ARRAY_FILTER_USE_BOTH));
        }
        $result = [
            'index' => $index,
            'winner' => $items[$index],
            'turns' => random_int(5, 8),
            'offset' => random_int(120, 880) / 1000, // position inside the winning slice
            'duration' => $state['duration'] * 1000,
        ];
        return [$result, $newState];
    }

    private static function teams(array $state): array
    {
        $items = $state['items'];
        $count = count($items);
        if ($count < 2) {
            throw new LdError('err.min_two_people');
        }
        $n = self::groupCount($state, $count);
        $idx = range(0, $count - 1);
        for ($i = $count - 1; $i > 0; $i--) {          // Fisher–Yates with CSPRNG
            $j = random_int(0, $i);
            [$idx[$i], $idx[$j]] = [$idx[$j], $idx[$i]];
        }
        $groups = array_fill(0, $n, []);
        foreach ($idx as $k => $i) {                    // deal round-robin → balanced sizes
            $groups[$k % $n][] = $i;
        }
        $result = [
            'groups' => $groups,
            'duration' => 1200 + min($count, 40) * 110,
        ];
        return [$result, $state];
    }

    /**
     * Weighted sampling without replacement. Returns item indexes.
     * Decimal weights are handled by scaling every weight to an integer number
     * of "tickets" (1/100 of a unit) so random_int() (CSPRNG) can still be used.
     */
    private static function weightedSample(array $items, int $count): array
    {
        $indexes = array_keys($items);
        $tickets = array_map(static fn($it) => max(1, (int) round(((float) $it['w']) * 100)), $items);
        $chosen = [];
        while (count($chosen) < $count && count($indexes) > 0) {
            $total = 0;
            foreach ($indexes as $i) {
                $total += $tickets[$i];
            }
            $r = random_int(1, max(1, $total));
            $acc = 0;
            $pickPos = 0;
            foreach ($indexes as $pos => $i) {
                $acc += $tickets[$i];
                if ($r <= $acc) {
                    $pickPos = $pos;
                    break;
                }
            }
            $chosen[] = $indexes[$pickPos];
            unset($indexes[$pickPos]);
            $indexes = array_values($indexes);
        }
        return $chosen;
    }
}
