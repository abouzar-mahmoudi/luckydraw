<?php
declare(strict_types=1);

/**
 * Storage abstraction for live rooms.
 *
 * Two zero-configuration back-ends are provided:
 *  - SqliteStore : used automatically when the pdo_sqlite extension exists.
 *  - FileStore   : plain JSON files (works on any PHP install).
 *
 * A room is a JSON document; presence (online viewers) is tracked separately
 * so that frequent viewer pings do not rewrite the room document.
 */
abstract class Store
{
    abstract public function name(): string;

    abstract public function get(string $id): ?array;

    abstract public function put(array $room): void;

    /** Create a room only if its id is free. Returns false when the id already exists. */
    abstract public function insert(array $room): bool;

    abstract public function delete(string $id): void;

    /** Number of live (non-expired) rooms, optionally only those created by $owner. */
    abstract public function countActive(int $nowMs, ?string $owner = null): int;

    /** Remove rooms whose expires_at < $nowMs. Returns number removed. */
    abstract public function purgeExpired(int $nowMs): int;

    abstract public function touchViewer(string $roomId, string $viewerId, int $nowMs): void;

    abstract public function countViewers(string $roomId, int $sinceMs): int;

    /* ---- registration forms ("signups"): same document model as rooms ---- */

    abstract public function getSignup(string $id): ?array;

    abstract public function putSignup(array $signup): void;

    /** Create only if the id is free (atomic). */
    abstract public function insertSignup(array $signup): bool;

    abstract public function deleteSignup(string $id): void;

    abstract public function countActiveSignups(int $nowMs, ?string $owner = null): int;

    abstract public function purgeExpiredSignups(int $nowMs): int;

    /** Factory: pick the best available back-end. */
    public static function make(): Store
    {
        $driver = strtolower((string) (ld_config('store') ?? getenv('LD_STORE') ?: 'auto'));

        if (!is_dir(LD_STORAGE)) {
            @mkdir(LD_STORAGE, 0775, true);
        }

        $sqliteAvailable = class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers(), true);
        if ($driver !== 'file' && $sqliteAvailable) {
            try {
                return new SqliteStore(LD_STORAGE . '/luckydraw.sqlite');
            } catch (Throwable $e) {
                if ($driver === 'sqlite') {
                    throw $e;
                }
                // fall through to file store
            }
        }
        return new FileStore(LD_STORAGE);
    }
}

/* -------------------------------------------------------------------------- */

final class SqliteStore extends Store
{
    private PDO $db;

    public function __construct(string $path)
    {
        $this->db = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->db->exec('PRAGMA journal_mode=WAL');
        $this->db->exec('PRAGMA busy_timeout=4000');
        $this->db->exec('PRAGMA synchronous=NORMAL');
        $this->db->exec('CREATE TABLE IF NOT EXISTS rooms (
            id TEXT PRIMARY KEY,
            data TEXT NOT NULL,
            expires_at INTEGER NOT NULL,
            updated_at INTEGER NOT NULL
        )');
        $this->db->exec('CREATE INDEX IF NOT EXISTS rooms_expires ON rooms(expires_at)');
        // v1.1: owner column (fair-use limits). Older databases are upgraded in place.
        $cols = array_column($this->db->query('PRAGMA table_info(rooms)')->fetchAll(), 'name');
        if (!in_array('owner', $cols, true)) {
            $this->db->exec("ALTER TABLE rooms ADD COLUMN owner TEXT NOT NULL DEFAULT ''");
        }
        $this->db->exec('CREATE TABLE IF NOT EXISTS presence (
            room_id TEXT NOT NULL,
            viewer_id TEXT NOT NULL,
            last_seen INTEGER NOT NULL,
            PRIMARY KEY (room_id, viewer_id)
        )');
        // v1.2: registration forms
        $this->db->exec('CREATE TABLE IF NOT EXISTS signups (
            id TEXT PRIMARY KEY,
            data TEXT NOT NULL,
            expires_at INTEGER NOT NULL,
            updated_at INTEGER NOT NULL,
            owner TEXT NOT NULL DEFAULT \'\'
        )');
        $this->db->exec('CREATE INDEX IF NOT EXISTS signups_expires ON signups(expires_at)');
    }

    public function name(): string
    {
        return 'sqlite';
    }

    public function get(string $id): ?array
    {
        $st = $this->db->prepare('SELECT data FROM rooms WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) {
            return null;
        }
        $room = json_decode($row['data'], true);
        return is_array($room) ? $room : null;
    }

    private function params(array $room): array
    {
        return [
            ':id' => $room['id'],
            ':data' => json_encode($room, LD_JSON_FLAGS),
            ':exp' => (int) $room['expires_at'],
            ':upd' => now_ms(),
            ':owner' => (string) ($room['owner'] ?? ''),
        ];
    }

    public function put(array $room): void
    {
        $st = $this->db->prepare('INSERT INTO rooms (id, data, expires_at, updated_at, owner) VALUES (:id, :data, :exp, :upd, :owner)
            ON CONFLICT(id) DO UPDATE SET data = excluded.data, expires_at = excluded.expires_at, updated_at = excluded.updated_at, owner = excluded.owner');
        $st->execute($this->params($room));
    }

    public function insert(array $room): bool
    {
        $st = $this->db->prepare('INSERT OR IGNORE INTO rooms (id, data, expires_at, updated_at, owner) VALUES (:id, :data, :exp, :upd, :owner)');
        $st->execute($this->params($room));
        return $st->rowCount() === 1;
    }

    public function delete(string $id): void
    {
        $this->db->prepare('DELETE FROM rooms WHERE id = ?')->execute([$id]);
        $this->db->prepare('DELETE FROM presence WHERE room_id = ?')->execute([$id]);
    }

    public function countActive(int $nowMs, ?string $owner = null): int
    {
        if ($owner === null) {
            $st = $this->db->prepare('SELECT COUNT(*) AS c FROM rooms WHERE expires_at >= ?');
            $st->execute([$nowMs]);
        } else {
            $st = $this->db->prepare('SELECT COUNT(*) AS c FROM rooms WHERE expires_at >= ? AND owner = ?');
            $st->execute([$nowMs, $owner]);
        }
        return (int) ($st->fetch()['c'] ?? 0);
    }

    public function purgeExpired(int $nowMs): int
    {
        $st = $this->db->prepare('DELETE FROM rooms WHERE expires_at < ?');
        $st->execute([$nowMs]);
        $n = $st->rowCount();
        $this->db->prepare('DELETE FROM presence WHERE last_seen < ?')->execute([$nowMs - 120000]);
        return $n;
    }

    public function touchViewer(string $roomId, string $viewerId, int $nowMs): void
    {
        $up = $this->db->prepare('UPDATE presence SET last_seen = ? WHERE room_id = ? AND viewer_id = ?');
        $up->execute([$nowMs, $roomId, $viewerId]);
        if ($up->rowCount() > 0) {
            return;
        }
        // New viewer: cap the presence table per room so junk ids cannot grow it without bound.
        if ($this->countViewers($roomId, 0) >= LD_MAX_VIEWERS) {
            return;
        }
        $st = $this->db->prepare('INSERT OR IGNORE INTO presence (room_id, viewer_id, last_seen) VALUES (?, ?, ?)');
        $st->execute([$roomId, $viewerId, $nowMs]);
    }

    public function countViewers(string $roomId, int $sinceMs): int
    {
        $st = $this->db->prepare('SELECT COUNT(*) AS c FROM presence WHERE room_id = ? AND last_seen >= ?');
        $st->execute([$roomId, $sinceMs]);
        return (int) ($st->fetch()['c'] ?? 0);
    }

    /* ---- signups ---- */

    public function getSignup(string $id): ?array
    {
        $st = $this->db->prepare('SELECT data FROM signups WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) {
            return null;
        }
        $doc = json_decode($row['data'], true);
        return is_array($doc) ? $doc : null;
    }

    public function putSignup(array $signup): void
    {
        $st = $this->db->prepare('INSERT INTO signups (id, data, expires_at, updated_at, owner) VALUES (:id, :data, :exp, :upd, :owner)
            ON CONFLICT(id) DO UPDATE SET data = excluded.data, expires_at = excluded.expires_at, updated_at = excluded.updated_at, owner = excluded.owner');
        $st->execute($this->params($signup));
    }

    public function insertSignup(array $signup): bool
    {
        $st = $this->db->prepare('INSERT OR IGNORE INTO signups (id, data, expires_at, updated_at, owner) VALUES (:id, :data, :exp, :upd, :owner)');
        $st->execute($this->params($signup));
        return $st->rowCount() === 1;
    }

    public function deleteSignup(string $id): void
    {
        $this->db->prepare('DELETE FROM signups WHERE id = ?')->execute([$id]);
    }

    public function countActiveSignups(int $nowMs, ?string $owner = null): int
    {
        if ($owner === null) {
            $st = $this->db->prepare('SELECT COUNT(*) AS c FROM signups WHERE expires_at >= ?');
            $st->execute([$nowMs]);
        } else {
            $st = $this->db->prepare('SELECT COUNT(*) AS c FROM signups WHERE expires_at >= ? AND owner = ?');
            $st->execute([$nowMs, $owner]);
        }
        return (int) ($st->fetch()['c'] ?? 0);
    }

    public function purgeExpiredSignups(int $nowMs): int
    {
        $st = $this->db->prepare('DELETE FROM signups WHERE expires_at < ?');
        $st->execute([$nowMs]);
        return $st->rowCount();
    }
}

/* -------------------------------------------------------------------------- */

final class FileStore extends Store
{
    private string $dir;

    public function __construct(string $storageDir)
    {
        $this->dir = rtrim($storageDir, '/\\') . '/rooms';
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0775, true) && !is_dir($this->dir)) {
            throw new RuntimeException('Storage directory is not writable: ' . $storageDir);
        }
        if (!is_writable($this->dir)) {
            throw new RuntimeException('Storage directory is not writable: ' . $this->dir);
        }
    }

    public function name(): string
    {
        return 'file';
    }

    /** Room ids are validated upstream (Room::normalizeCode); this is a second safety net. */
    private static function safeId(string $id): string
    {
        return preg_replace('/[^A-Z0-9-]/', '', $id) ?? '';
    }

    private function path(string $id): string
    {
        return $this->dir . '/' . self::safeId($id) . '.json';
    }

    private function signupPath(string $id): string
    {
        return $this->dir . '/signup-' . self::safeId($id) . '.json';
    }

    private function presenceDir(string $id): string
    {
        return $this->dir . '/' . self::safeId($id) . '.viewers';
    }

    public function get(string $id): ?array
    {
        $file = $this->path($id);
        if (!is_file($file)) {
            return null;
        }
        $fh = @fopen($file, 'rb');
        if (!$fh) {
            return null;
        }
        flock($fh, LOCK_SH);
        $raw = stream_get_contents($fh);
        flock($fh, LOCK_UN);
        fclose($fh);
        $room = json_decode((string) $raw, true);
        return is_array($room) ? $room : null;
    }

    public function put(array $room): void
    {
        $file = $this->path($room['id']);
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $json = json_encode($room, LD_JSON_FLAGS);
        if (file_put_contents($tmp, $json, LOCK_EX) === false) {
            throw new RuntimeException('Cannot write room file');
        }
        if (!@rename($tmp, $file)) {
            // Windows may refuse to overwrite an open file; fall back to copy.
            @copy($tmp, $file);
            @unlink($tmp);
        }
    }

    public function insert(array $room): bool
    {
        $file = $this->path($room['id']);
        $fh = @fopen($file, 'x'); // exclusive create: fails atomically when the file exists
        if ($fh === false) {
            return false;
        }
        $ok = fwrite($fh, json_encode($room, LD_JSON_FLAGS)) !== false;
        fclose($fh);
        if (!$ok) {
            @unlink($file);
            throw new RuntimeException('Cannot write room file');
        }
        return true;
    }

    /** Room files only (signup forms live in signup-*.json). */
    private function roomFiles(): array
    {
        return array_values(array_filter(glob($this->dir . '/*.json') ?: [], static fn($f) => strpos(basename($f), 'signup-') !== 0));
    }

    public function countActive(int $nowMs, ?string $owner = null): int
    {
        $n = 0;
        foreach ($this->roomFiles() as $file) {
            $room = json_decode((string) @file_get_contents($file), true);
            if (!is_array($room) || (int) ($room['expires_at'] ?? 0) < $nowMs) {
                continue;
            }
            if ($owner !== null && (string) ($room['owner'] ?? '') !== $owner) {
                continue;
            }
            $n++;
        }
        return $n;
    }

    public function delete(string $id): void
    {
        @unlink($this->path($id));
        $pd = $this->presenceDir($id);
        if (is_dir($pd)) {
            foreach (glob($pd . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($pd);
        }
    }

    public function purgeExpired(int $nowMs): int
    {
        $n = 0;
        foreach ($this->roomFiles() as $file) {
            $room = json_decode((string) @file_get_contents($file), true);
            if (!is_array($room) || (int) ($room['expires_at'] ?? 0) < $nowMs) {
                $this->delete(basename($file, '.json'));
                $n++;
            }
        }
        // stale temp files
        foreach (glob($this->dir . '/*.tmp') ?: [] as $tmp) {
            if (filemtime($tmp) < time() - 60) {
                @unlink($tmp);
            }
        }
        return $n;
    }

    public function touchViewer(string $roomId, string $viewerId, int $nowMs): void
    {
        $pd = $this->presenceDir($roomId);
        if (!is_dir($pd)) {
            @mkdir($pd, 0775, true);
        }
        $safe = preg_replace('/[^a-z0-9]/', '', strtolower($viewerId));
        if ($safe === '') {
            return;
        }
        $f = $pd . '/' . $safe;
        if (!is_file($f) && count(glob($pd . '/*') ?: []) >= LD_MAX_VIEWERS) {
            return; // presence cap per room
        }
        @touch($f, (int) floor($nowMs / 1000));
    }

    public function countViewers(string $roomId, int $sinceMs): int
    {
        $pd = $this->presenceDir($roomId);
        if (!is_dir($pd)) {
            return 0;
        }
        $since = (int) floor($sinceMs / 1000);
        $n = 0;
        foreach (glob($pd . '/*') ?: [] as $f) {
            $m = @filemtime($f);
            if ($m !== false && $m >= $since) {
                $n++;
            } elseif ($m !== false && $m < $since - 300) {
                @unlink($f);
            }
        }
        return $n;
    }

    /* ---- signups ---- */

    private function readJson(string $file): ?array
    {
        if (!is_file($file)) {
            return null;
        }
        $fh = @fopen($file, 'rb');
        if (!$fh) {
            return null;
        }
        flock($fh, LOCK_SH);
        $raw = stream_get_contents($fh);
        flock($fh, LOCK_UN);
        fclose($fh);
        $doc = json_decode((string) $raw, true);
        return is_array($doc) ? $doc : null;
    }

    private function writeJson(string $file, array $doc): void
    {
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, json_encode($doc, LD_JSON_FLAGS), LOCK_EX) === false) {
            throw new RuntimeException('Cannot write file');
        }
        if (!@rename($tmp, $file)) {
            @copy($tmp, $file);
            @unlink($tmp);
        }
    }

    public function getSignup(string $id): ?array
    {
        return $this->readJson($this->signupPath($id));
    }

    public function putSignup(array $signup): void
    {
        $this->writeJson($this->signupPath($signup['id']), $signup);
    }

    public function insertSignup(array $signup): bool
    {
        $file = $this->signupPath($signup['id']);
        $fh = @fopen($file, 'x');
        if ($fh === false) {
            return false;
        }
        $ok = fwrite($fh, json_encode($signup, LD_JSON_FLAGS)) !== false;
        fclose($fh);
        if (!$ok) {
            @unlink($file);
            throw new RuntimeException('Cannot write file');
        }
        return true;
    }

    public function deleteSignup(string $id): void
    {
        @unlink($this->signupPath($id));
    }

    public function countActiveSignups(int $nowMs, ?string $owner = null): int
    {
        $n = 0;
        foreach (glob($this->dir . '/signup-*.json') ?: [] as $file) {
            $doc = json_decode((string) @file_get_contents($file), true);
            if (!is_array($doc) || (int) ($doc['expires_at'] ?? 0) < $nowMs) {
                continue;
            }
            if ($owner !== null && (string) ($doc['owner'] ?? '') !== $owner) {
                continue;
            }
            $n++;
        }
        return $n;
    }

    public function purgeExpiredSignups(int $nowMs): int
    {
        $n = 0;
        foreach (glob($this->dir . '/signup-*.json') ?: [] as $file) {
            $doc = json_decode((string) @file_get_contents($file), true);
            if (!is_array($doc) || (int) ($doc['expires_at'] ?? 0) < $nowMs) {
                @unlink($file);
                $n++;
            }
        }
        return $n;
    }
}
