<?php
// CLI: php measure.php before|after http://127.0.0.1:8007/LR7/api.php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function check(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
}

function median(array $values): float
{
    sort($values, SORT_NUMERIC);
    $n = count($values);
    return ($values[intdiv($n - 1, 2)] + $values[intdiv($n, 2)]) / 2;
}

function readApi(string $url, array $expected): array
{
    $context = stream_context_create(['http' => ['timeout' => 30, 'ignore_errors' => true]]);
    $raw = file_get_contents($url, false, $context);
    check($raw !== false, 'Не вдалося прочитати API.');
    $headers = $http_response_header ?? [];
    check((bool) preg_match('~^HTTP/\S+ 200(?: |$)~', $headers[0] ?? ''), 'API має повернути 200.');
    $profile = [];
    foreach ($headers as $line) {
        if (str_contains($line, ':')) {
            [$key, $value] = explode(':', $line, 2);
            $profile[strtolower($key)] = trim($value);
        }
    }
    check(($profile['x-sql-queries'] ?? '') === '1', 'Потрібен mode=original, рівно 1 SQL.');
    check(($profile['x-cache'] ?? '') === 'NONE', 'Цей замір не використовує кеш.');
    check(is_numeric($profile['x-elapsed-ms'] ?? null)
        && is_numeric($profile['x-peak-memory-mb'] ?? null), 'Немає профілю PHP.');
    $json = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    check(($json['success'] ?? false) === true && ($json['data'] ?? null) === $expected,
        'API повертає не ті дані: перевірте DSN сервера та seed.');
    return ['ms' => (float)$profile['x-elapsed-ms'], 'mb' => (float)$profile['x-peak-memory-mb'],
        'hash' => hash('sha256', $raw), 'bytes' => strlen($raw)];
}

function bench(string $url): array
{
    $stdout = tempnam(sys_get_temp_dir(), 'lab7-ab-');
    $stderr = tempnam(sys_get_temp_dir(), 'lab7-ab-');
    check($stdout !== false && $stderr !== false, 'Немає доступу до тимчасового каталогу.');
    try {
        // Масив аргументів: без cmd.exe/sh та ручного shell-екранування.
        $command = [getenv('AB_BIN') ?: 'ab', '-n', '200', '-c', '20', $url];
        $process = proc_open($command,
            [0 => ['pipe', 'r'], 1 => ['file', $stdout, 'w'], 2 => ['file', $stderr, 'w']], $pipes);
        check(is_resource($process), 'Не вдалося запустити ab (PATH або AB_BIN).');
        fclose($pipes[0]);
        $exit = proc_close($process);
        $raw = file_get_contents($stdout);
        check($exit === 0, 'ab завершився з помилкою: ' . file_get_contents($stderr));
        foreach (['Complete requests' => 200, 'Failed requests' => 0] as $label => $wanted) {
            check(preg_match('/' . preg_quote($label, '/') . ':\s+(\d+)/', $raw, $m) === 1
                && (int)$m[1] === $wanted, 'Невдалий ab: ' . $label);
        }
        check(!preg_match('/Non-2xx responses:\s+([1-9]\d*)/', $raw), 'ab отримав HTTP-помилки.');
        check(preg_match('/Requests per second:\s+([\d.]+)/', $raw, $rps) === 1, 'Немає RPS.');
        check(preg_match_all('/Time per request:\s+([\d.]+)/', $raw, $times) === 2, 'Немає часу ab.');
        return ['rps' => (float)$rps[1], 'mean_ms' => (float)$times[1][0],
            'completed' => 200, 'failed' => 0, 'non2xx' => 0, 'raw' => $raw];
    } finally {
        if (is_string($stdout) && is_file($stdout)) unlink($stdout);
        if (is_string($stderr) && is_file($stderr)) unlink($stderr);
    }
}

try {
    $phase = $argv[1] ?? '';
    check(in_array($phase, ['before', 'after'], true), 'Вкажіть before або after.');
    $endpoint = $argv[2] ?? 'http://127.0.0.1:8007/LR7/api.php';
    $parts = parse_url($endpoint);
    check(is_array($parts) && ($parts['scheme'] ?? '') === 'http'
        && in_array($parts['host'] ?? '', ['127.0.0.1', 'localhost', '[::1]'], true)
        && !isset($parts['query']) && !isset($parts['fragment'])
        && !isset($parts['user']) && !isset($parts['pass'])
        && str_ends_with($parts['path'] ?? '', '/api.php'), 'Вкажіть локальну URL api.php без параметрів.');
    $pdo = require __DIR__ . '/db.php';
    check($pdo->query('SELECT DATABASE()')->fetchColumn() === 'practicum7_bench',
        'Вимірювання дозволено лише для practicum7_bench; задайте PHP_LAB_DSN.');
    $stats = $pdo->query('SELECT COUNT(*) n, COUNT(DISTINCT author) authors, SUM(is_pinned) pinned
        FROM topics')->fetch();
    check((int)$stats['n'] === 1000 && (int)$stats['authors'] === 100 && (int)$stats['pinned'] === 50,
        'Спочатку виконайте seed.sql (1000 тем / 100 авторів / 50 pinned).');
    $indexes = [];
    foreach ($pdo->query('SHOW INDEX FROM topics')->fetchAll() as $index) {
        $indexes[$index['Key_name']][(int)$index['Seq_in_index']] = [
            $index['Column_name'], (int)$index['Non_unique'], $index['Sub_part'],
            strtoupper($index['Index_type']), $index['Collation'],
            ($index['Visible'] ?? 'YES') === 'YES' && ($index['Ignored'] ?? 'NO') === 'NO',
        ];
    }
    foreach ($indexes as &$columns) ksort($columns);
    unset($columns);
    ksort($indexes);
    $expectedIndexes = ['PRIMARY' => [1 => ['id', 0, null, 'BTREE', 'A', true]]];
    if ($phase === 'after') {
        $expectedIndexes['idx_topics_author'] = [1 => ['author', 1, null, 'BTREE', 'A', true]];
    }
    check($indexes === $expectedIndexes,
        'Неправильні індекси: повторіть seed.sql; для after виконайте create_index.sql.');
    $explain = file_get_contents(__DIR__ . '/explain.sql');
    $plan = $pdo->query($explain)->fetchAll();
    $all = $pdo->query('SELECT id,title,author,created_at,is_pinned FROM topics ORDER BY id')->fetchAll();
    foreach ($all as &$row) {
        $row['id'] = (int)$row['id'];
        $row['is_pinned'] = (int)$row['is_pinned'];
    }
    unset($row);
    foreach ($all as $i => $row) {
        $n = $i + 1;
        check($row === ['id' => $n, 'title' => sprintf('Тема форуму %04d', $n),
            'author' => sprintf('author_%03d', $n % 100), 'created_at' => '2026-11-25',
            'is_pinned' => (int)($n % 20 === 0)], 'Дані змінено: повторіть seed.sql.');
    }
    $expected = array_reverse(array_values(array_filter($all,
        static fn(array $row): bool => $row['author'] === 'author_042')));
    check(count($expected) === 10, 'Некоректний seed автора author_042.');
    $url = $endpoint . '?resource=topics&mode=original&author=author_042';
    readApi($url, $expected); // прогрів без включення в медіану
    $profiles = [];
    for ($i = 0; $i < 10; ++$i) $profiles[] = readApi($url, $expected);
    $runs = [];
    for ($i = 0; $i < 3; ++$i) $runs[] = bench($url);
    $result = ['phase' => $phase, 'url' => $url, 'php' => PHP_VERSION,
        'database' => $pdo->query('SELECT VERSION()')->fetchColumn(), 'os' => PHP_OS_FAMILY,
        'rows' => 1000, 'authors' => 100, 'n' => 200, 'c' => 20,
        'dataset_hash' => hash('sha256', json_encode($all, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
        'response_hash' => $profiles[0]['hash'], 'response_bytes' => $profiles[0]['bytes'],
        'explain_sql' => $explain, 'explain' => $plan, 'sql' => 1, 'cache' => 'NONE',
        'profile_samples_ms' => array_column($profiles, 'ms'),
        'php_median_ms' => median(array_column($profiles, 'ms')),
        'memory_mb' => max(array_column($profiles, 'mb')), 'ab_runs' => $runs,
        'rps_median' => median(array_column($runs, 'rps'))];
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), "\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
