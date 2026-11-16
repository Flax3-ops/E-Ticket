<?php

declare(strict_types=1);

// Hanya untuk database tiket_loadtest; jangan jalankan pada database demo.
$host = getenv('LOADTEST_HOST') ?: '127.0.0.1';
$user = getenv('LOADTEST_USER') ?: 'root';
$password = getenv('LOADTEST_PASSWORD') ?: '';
$database = getenv('LOADTEST_DATABASE') ?: 'tiket_loadtest';
if ($database !== 'tiket_loadtest') {
    throw new RuntimeException('Benchmark hanya boleh menggunakan tiket_loadtest.');
}
$db = new mysqli($host, $user, $password, $database);
$queries = [
    'status_prioritas' => "SELECT SQL_NO_CACHE id FROM tickets WHERE status='Baru' AND priority='Rendah' LIMIT 20",
    'sla' => "SELECT SQL_NO_CACHE id FROM tickets WHERE sla_due_at < UTC_TIMESTAMP() AND status='Baru' LIMIT 20",
    'judul' => "SELECT SQL_NO_CACHE id FROM tickets WHERE MATCH(title) AGAINST ('99999' IN NATURAL LANGUAGE MODE) LIMIT 20",
];
$indexes = ['idx_status_priority' => 'CREATE INDEX idx_status_priority ON tickets(status,priority)', 'idx_sla_due' => 'CREATE INDEX idx_sla_due ON tickets(sla_due_at)', 'ft_title' => 'CREATE FULLTEXT INDEX ft_title ON tickets(title)'];
$measure = static function (mysqli $db, array $queries, bool $indexed): array {
    $result = [];
    foreach ($queries as $name => $query) {
        if ($name === 'judul' && ! $indexed) {
            $query = "SELECT SQL_NO_CACHE id FROM tickets WHERE title LIKE '%99999%' LIMIT 20";
        }
        $explain = $db->query('EXPLAIN ' . preg_replace('/SQL_NO_CACHE /', '', $query))->fetch_assoc();
        $times = [];
        for ($i = 0; $i < 5; $i++) {
            $start = hrtime(true);
            $db->query($query)->fetch_all(MYSQLI_ASSOC);
            $times[] = (hrtime(true) - $start) / 1e6;
        }
        sort($times);
        $result[$name] = ['median_ms' => round($times[2], 3), 'explain_type' => $explain['type'], 'explain_key' => $explain['key'], 'explain_rows' => $explain['rows']];
    }
    return $result;
};
$dropped = [];
try {
    foreach ($indexes as $name => $_) {
        $db->query('DROP INDEX ' . $name . ' ON tickets');
        $dropped[] = $name;
    }
    $before = $measure($db, $queries, false);
} finally {
    foreach ($dropped as $name) {
        $db->query($indexes[$name]);
    }
}
$after = $measure($db, $queries, true);
$report = ['database' => $database, 'rows' => (int) $db->query('SELECT COUNT(*) AS n FROM tickets')->fetch_assoc()['n'], 'before' => $before, 'after' => $after, 'measured_at_utc' => gmdate('c')];
$path = dirname(__DIR__) . '/docs/A08-index-benchmark.json';
if (! is_dir(dirname($path))) {
    mkdir(dirname($path), 0775, true);
}
file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
