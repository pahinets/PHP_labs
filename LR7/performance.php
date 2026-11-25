<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/security.php';

final class Profile
{
    public static float $start;
    public static int $queries = 0;
    public static string $cache = 'NONE';
}
Profile::$start = microtime(true);

// Рахуємо виконання SQL, а не prepare(): підготовка ще не читає дані.
function sql(PDO $pdo, string $query, ?array $params = null): PDOStatement
{
    if ($params === null) {
        ++Profile::$queries;
        return $pdo->query($query);
    }
    $stmt = $pdo->prepare($query);
    ++Profile::$queries;
    $stmt->execute($params);
    return $stmt;
}

function profileHeaders(): void
{
    $elapsed = (microtime(true) - Profile::$start) * 1000;
    $memory = memory_get_peak_usage(true) / 1024 / 1024;
    header('X-Elapsed-Ms: ' . number_format($elapsed, 3, '.', ''));
    header('X-Peak-Memory-Mb: ' . number_format($memory, 3, '.', ''));
    header('X-SQL-Queries: ' . Profile::$queries);
    header('X-Cache: ' . Profile::$cache);
    error_log(sprintf('Practicum07: %.3f ms; %.3f MB; SQL=%d; cache=%s',
        $elapsed, $memory, Profile::$queries, Profile::$cache));
}

function pinnedCacheFile(): string
{
    return forum_pinned_cache_file();
}

function openPinnedCache()
{
    $file = pinnedCacheFile();
    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        return false;
    }
    // c+ не обрізає файл до отримання LOCK_EX (важливо й для Windows).
    return @fopen($file, 'c+');
}

function validPinnedCache(mixed $entry): bool
{
    if (!$entry instanceof stdClass || !is_int($entry->expires ?? null)
        || $entry->expires <= time() || $entry->expires > time() + 60
        || !isset($entry->data) || !is_array($entry->data)) {
        return false;
    }
    foreach ($entry->data as $row) {
        // Приймаємо лише п'ять полів контракту; зайве значення може містити INF.
        if (!$row instanceof stdClass || count(get_object_vars($row)) !== 5
            || !is_int($row->id ?? null) || $row->id < 1
            || !is_string($row->title ?? null) || !is_string($row->author ?? null)
            || !is_string($row->created_at ?? null) || ($row->is_pinned ?? null) !== 1) {
            return false;
        }
    }
    return true;
}

function cachedPinned(callable $load): array
{
    $file = openPinnedCache();
    if (!$file) {
        Profile::$cache = 'BYPASS';
        return $load();
    }
    try {
        if (!flock($file, LOCK_EX)) {
            Profile::$cache = 'BYPASS';
            return $load();
        }
        // Без associative=true: порожній об'єкт {} не є списком [].
        $entry = json_decode(stream_get_contents($file));
        if (validPinnedCache($entry)) {
            Profile::$cache = 'HIT';
            return array_map(static fn(stdClass $row): array => (array)$row, $entry->data);
        }
        Profile::$cache = 'MISS';
        // Завантаження під тим самим lock: запобігає stampede та гонці з інвалідацією.
        $rows = $load();
        $json = json_encode(['expires' => time() + 60, 'data' => $rows],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (!@ftruncate($file, 0) || !rewind($file)
            || @fwrite($file, $json) !== strlen($json) || !@fflush($file)) {
            Profile::$cache = 'BYPASS';
        }
        return $rows;
    } finally {
        fclose($file); // також звільняє lock при винятку
    }
}

function invalidatePinned(): void
{
    $file = openPinnedCache();
    if (!$file) {
        return; // TTL обмежує старіння, навіть якщо інвалідація недоступна.
    }
    try {
        if (flock($file, LOCK_EX)) {
            @ftruncate($file, 0);
            @fflush($file);
        }
    } finally {
        fclose($file);
    }
}
