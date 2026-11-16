<?php

/**
 * Uji beban terautentikasi (A14, NF-03): 50 sesi pengguna sah serentak + ApacheBench sebagai uji HTTP dasar.
 *
 * Pemakaian: php tests/http/stress.php [http://localhost:8082] --out docs/A14-stress-results.md
 * Mengubah data (membuat 50 pengguna `load01..50@tiket.local`, lalu menghapusnya). Cadangkan DB lebih dulu.
 */

require_once __DIR__ . '/Client.php';

$base = rtrim($argv[1] ?? 'http://localhost:8082', '/');
$out = null;
$label = '';
foreach ($argv as $i => $a) {
    if ($a === '--out') {
        $out = $argv[$i + 1] ?? null;
    }
    if ($a === '--label') {
        $label = $argv[$i + 1] ?? '';
    }
}
$tmp = sys_get_temp_dir() . '/tiket-stress-' . getmypid();
@mkdir($tmp);
$pdo = new PDO('mysql:host=127.0.0.1;dbname=tiket_demo;charset=utf8mb4', 'root', '');
$N = 50;

function pct(array $v, float $p): float
{
    sort($v);
    return $v ? $v[(int) max(0, ceil($p * count($v)) - 1)] : 0.0;
}

/** Jalankan banyak permintaan paralel; kembalikan [[status, ms, body, handle-index], ...]. */
function parallel(array $jobs): array
{
    $mh = curl_multi_init();
    $hs = [];
    foreach ($jobs as $i => $job) {
        $ch = curl_init($job['url']);
        $opt = [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEFILE => $job['jar'], CURLOPT_COOKIEJAR => $job['jar'], CURLOPT_TIMEOUT => 120];
        if (isset($job['post'])) {
            $opt[CURLOPT_POST] = true;
            $opt[CURLOPT_POSTFIELDS] = http_build_query($job['post']);
        }
        curl_setopt_array($ch, $opt);
        curl_multi_add_handle($mh, $ch);
        $hs[$i] = $ch;
    }
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh, 0.2);
    } while ($running > 0);
    $res = [];
    foreach ($hs as $i => $ch) {
        $raw = (string) curl_multi_getcontent($ch);
        $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $head = substr($raw, 0, $size);
        $res[$i] = ['status' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE), 'ms' => curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000, 'body' => substr($raw, $size), 'loc' => preg_match('/^Location:\s*(\S+)/mi', $head, $m) ? preg_replace('#^https?://[^/]+#', '', $m[1]) : ''];
        curl_multi_remove_handle($mh, $ch);
    }
    return $res;
}

function token(string $html): string
{
    return preg_match('/name="csrf_test_name"\s+value="([^"]+)"/', $html, $m) ? $m[1] : '';
}

// Sampler CPU/RAM di latar belakang (1 detik) memakai Get-Counter.
function startSampler(string $file, int $seconds): mixed
{
    $ps = '$e=Get-Date; Get-Counter -Counter "\Processor(_Total)\% Processor Time","\Memory\Available MBytes" -SampleInterval 1 -MaxSamples ' . $seconds
        . ' | ForEach-Object { $c=$_.CounterSamples; ($c[0].CookedValue.ToString("F1",[Globalization.CultureInfo]::InvariantCulture)) + "," + ($c[1].CookedValue.ToString("F0",[Globalization.CultureInfo]::InvariantCulture)) } | ForEach-Object { [IO.File]::AppendAllText("' . $file . '", $_ + [Environment]::NewLine) }';
    @unlink($file);
    return proc_open(['powershell', '-NoProfile', '-Command', $ps], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $p);
}

function samplerStats(string $file, int $from = 0, ?int $to = null): array
{
    $cpu = [];
    $ram = [];
    $lines = [];
    for ($try = 0; $try < 20 && is_file($file); $try++) {
        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            break;
        }
        $lines = [];
        usleep(100000);
    }
    foreach (array_slice($lines, $from, $to === null ? null : $to - $from) as $line) {
        [$c, $r] = array_map('floatval', explode(',', $line) + [0, 0]);
        $cpu[] = $c;
        $ram[] = $r;
    }
    return ['cpu_max' => $cpu ? max($cpu) : null, 'cpu_avg' => $cpu ? array_sum($cpu) / count($cpu) : null, 'ram_free_min' => $ram ? min($ram) : null, 'ram_free_start' => $ram[0] ?? null, 'samples' => count($cpu)];
}

// ----------------------------------------------------------- persiapan
foreach (['activity_logs', 'otp_codes', 'notifications'] as $tbl) {
    $pdo->exec("DELETE FROM $tbl WHERE user_id IN (SELECT id FROM users WHERE email LIKE 'load%@tiket.local')");
}
$pdo->exec("DELETE FROM users WHERE email LIKE 'load%@tiket.local'");
$hash = password_hash('Demo12345!', PASSWORD_BCRYPT);
$ins = $pdo->prepare("INSERT INTO users (name,email,password_hash,role,is_active,created_at,updated_at) VALUES (?,?,?,'pemohon',1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
$jars = [];
for ($i = 1; $i <= $N; $i++) {
    $email = sprintf('load%02d@tiket.local', $i);
    $ins->execute(["Load $i", $email, $hash]);
    $jars[$i] = "$tmp/u$i.jar";
}

$report = [];
$sampleFile = "$tmp/samples.csv";

// ------------------------------------------- fase A: 50 login serentak
$sampler = startSampler($sampleFile, 400);
$lineCount = static function () use ($sampleFile): int {
    for ($try = 0; $try < 20; $try++) { // berkas dikunci sesaat saat sampler menulis
        $lines = @file($sampleFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            return count($lines);
        }
        usleep(100000);
    }
    return 0;
};
sleep(3);
$mark0 = $lineCount();
$t0 = microtime(true);
$r1 = parallel(array_map(static fn ($i) => ['url' => "$base/login", 'jar' => $jars[$i]], range(1, $N)));
$jobs = [];
foreach (range(1, $N) as $i) {
    $jobs[$i] = ['url' => "$base/login", 'jar' => $jars[$i], 'post' => ['csrf_test_name' => token($r1[$i - 1]['body'] ?? $r1[$i]['body']), 'email' => sprintf('load%02d@tiket.local', $i), 'password' => 'Demo12345!']];
}
$r2 = parallel($jobs);
$r3 = parallel(array_map(static fn ($i) => ['url' => "$base/otp", 'jar' => $jars[$i]], range(1, $N)));
$jobs = [];
foreach (range(1, $N) as $k => $i) {
    $code = preg_match('/letter-space">(\d{6})</', $r3[$k]['body'] ?? $r3[$i]['body'], $m) ? $m[1] : '000000';
    $jobs[$i] = ['url' => "$base/otp", 'jar' => $jars[$i], 'post' => ['csrf_test_name' => token($r3[$k]['body'] ?? $r3[$i]['body']), 'code' => $code]];
}
$r4 = parallel($jobs);
$phaseA = array_merge($r1, $r2, $r3, $r4);
$loginOk = 0;
foreach ($r4 as $r) {
    $loginOk += ($r['status'] === 302 || $r['status'] === 303) && $r['loc'] === '/' ? 1 : 0;
}
$timesA = array_column($phaseA, 'ms');
$errA = count(array_filter($phaseA, static fn ($r) => $r['status'] >= 400 || $r['status'] === 0));
$durA = microtime(true) - $t0;
$report['login'] = ['sesi' => $N, 'berhasil' => $loginOk, 'permintaan' => count($phaseA), 'error' => $errA, 'p50' => pct($timesA, 0.5), 'p95' => pct($timesA, 0.95), 'max' => max($timesA), 'durasi' => $durA];
echo "Login serentak: $loginOk/$N berhasil, p95 " . round(pct($timesA, 0.95)) . " ms\n";

$markA = $lineCount();
// ------------------------- fase B: 50 sesi bekerja serentak (alur terautentikasi)
$paths = ['/', '/tickets', '/tickets?q=Permintaan', '/api/dashboard/summary', '/notifications', '/api/notifications/unread'];
$all = [];
$errB = 0;
$t1 = microtime(true);
for ($round = 0; $round < 5; $round++) {
    foreach ($paths as $path) {
        $res = parallel(array_map(static fn ($i) => ['url' => $base . $path, 'jar' => $jars[$i]], range(1, $N)));
        foreach ($res as $r) {
            $all[] = $r['ms'];
            $errB += $r['status'] === 200 ? 0 : 1;
        }
    }
}
$durB = microtime(true) - $t1;
$report['kerja'] = ['sesi' => $N, 'permintaan' => count($all), 'error' => $errB, 'p50' => pct($all, 0.5), 'p95' => pct($all, 0.95), 'max' => max($all), 'rps' => count($all) / $durB, 'durasi' => $durB];
echo 'Sesi bekerja: ' . count($all) . " permintaan, error $errB, p95 " . round(pct($all, 0.95)) . " ms\n";

$markB = $lineCount();
// ------------------------------------------------------------- ApacheBench
$admin = new Client($base, 'admin', $tmp);
$admin->req('GET', '/login');
$admin->req('POST', '/login', ['email' => 'admin@tiket.local', 'password' => 'Demo12345!']);
[, $html] = $admin->req('GET', '/otp');
if (preg_match('/letter-space">(\d{6})</', $html, $m)) {
    $admin->req('POST', '/otp', ['code' => $m[1]]);
}
$cookie = static function (string $jar): string {
    $pairs = [];
    foreach (file($jar, FILE_IGNORE_NEW_LINES) as $line) {
        $line = ltrim($line, '#HttpOnly_');
        $f = explode("\t", $line);
        if (count($f) >= 7 && $f[5] !== 'csrf_cookie_name') {
            $pairs[] = $f[5] . '=' . $f[6];
        }
    }
    return implode('; ', $pairs);
};
$ab = 'D:/xampp/apache/bin/ab.exe';
$abRuns = [['/login', null], ['/tickets', $cookie($jars[1])], ['/monitor/metrics', $cookie($admin->jar)]];
$report['ab'] = [];
foreach ($abRuns as [$path, $ck]) {
    $m1 = $lineCount();
    $cmd = [$ab, '-n', '1000', '-c', '50', '-l', '-s', '60'];
    if ($ck) {
        array_push($cmd, '-C', $ck);
    }
    $cmd[] = $base . $path;
    $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $txt = stream_get_contents($pipes[1]);
    proc_close($proc);
    $g = static fn (string $re) => preg_match($re, $txt, $mm) ? $mm[1] : '?';
    $report['ab'][$path] = [
        'rps' => $g('/Requests per second:\s+([\d.]+)/'), 'mean' => $g('/Time per request:\s+([\d.]+) \[ms\] \(mean\)/'),
        'failed' => $g('/Failed requests:\s+(\d+)/'), 'non2xx' => $g('/Non-2xx responses:\s+(\d+)/') === '?' ? '0' : $g('/Non-2xx responses:\s+(\d+)/'),
        'p50' => $g('/\s50%\s+(\d+)/'), 'p95' => $g('/\s95%\s+(\d+)/'), 'p99' => $g('/\s99%\s+(\d+)/'), 'max' => $g('/100%\s+(\d+)/'), 'complete' => $g('/Complete requests:\s+(\d+)/'),
    ];
    $report['ab'][$path]['res'] = samplerStats($sampleFile, $m1, $lineCount());
    echo "ab $path: " . json_encode($report['ab'][$path]) . "\n";
    sleep(2);
}
$statsA = samplerStats($sampleFile, $mark0, $markA);
$statsB = samplerStats($sampleFile, $markA, $markB);
$statsBase = samplerStats($sampleFile, 0, $mark0);
proc_terminate($sampler);

// ------------------------------------------------------------- pembersihan
$pdo->exec("DELETE FROM activity_logs WHERE user_id IN (SELECT id FROM users WHERE email LIKE 'load%@tiket.local')");
$pdo->exec("DELETE FROM otp_codes WHERE user_id IN (SELECT id FROM users WHERE email LIKE 'load%@tiket.local')");
$pdo->exec("DELETE FROM notifications WHERE user_id IN (SELECT id FROM users WHERE email LIKE 'load%@tiket.local')");
$pdo->exec("DELETE FROM users WHERE email LIKE 'load%@tiket.local'");

$md = '# Hasil uji beban terautentikasi dan ApacheBench' . ($label !== '' ? " — $label" : '') . "\n\n";
$md .= 'Dijalankan: ' . gmdate('Y-m-d H:i') . " UTC pada instance Apache XAMPP (`scripts/apache-tiket.conf`), PHP " . PHP_VERSION . ", database `tiket_demo` (100 tiket).\n\n";
$md .= "## Lingkungan\n\n" . php_uname() . "\n\n";
$md .= "## 1. 50 sesi sah serentak (NF-03)\n\n";
$L = $report['login'];
$K = $report['kerja'];
$md .= "| Fase | Sesi | Permintaan | Berhasil | Error | p50 ms | p95 ms | maks ms | Catatan |\n|---|---:|---:|---:|---:|---:|---:|---:|---|\n";
$md .= sprintf("| Login password+OTP (4 langkah, serentak) | %d | %d | %d login | %d | %.0f | %.0f | %.0f | durasi %.1f s |\n", $L['sesi'], $L['permintaan'], $L['berhasil'], $L['error'], $L['p50'], $L['p95'], $L['max'], $L['durasi']);
$md .= sprintf("| Alur kerja (6 halaman x 5 putaran, 50 sesi serentak) | %d | %d | %d status 200 | %d | %.0f | %.0f | %.0f | %.1f req/s, durasi %.1f s |\n\n", $K['sesi'], $K['permintaan'], $K['permintaan'] - $K['error'], $K['error'], $K['p50'], $K['p95'], $K['max'], $K['rps'], $K['durasi']);
$md .= "Setiap sesi memakai cookie sesi sendiri hasil login nyata (bukan satu cookie yang diulang). Halaman: `/`, `/tickets`, `/tickets?q=Permintaan`, `/api/dashboard/summary`, `/notifications`, `/api/notifications/unread`.\n\n";
$md .= "## 2. ApacheBench `-n 1000 -c 50` (uji HTTP dasar, bukan bukti 50 pengguna)\n\n| Rute | Selesai | Gagal | Non-2xx | req/s | rata-rata ms | p50 ms | p95 ms | p99 ms | maks ms |\n|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|\n";
foreach ($report['ab'] as $path => $a) {
    $md .= "| `$path` | {$a['complete']} | {$a['failed']} | {$a['non2xx']} | {$a['rps']} | {$a['mean']} | {$a['p50']} | {$a['p95']} | {$a['p99']} | {$a['max']} |\n";
}
$fmt = static fn (array $st) => $st['samples'] ? sprintf('CPU rata-rata %.1f%% (maks %.1f%%), RAM bebas minimum %.0f MB (%d sampel)', $st['cpu_avg'], $st['cpu_max'], $st['ram_free_min'], $st['samples']) : 'tidak tersedia';
$md .= "
## 3. Sumber daya (Get-Counter, sampel per detik)

| Kondisi | CPU / RAM |
|---|---|
";
$md .= '| Sebelum uji (kondisi awal) | ' . $fmt($statsBase) . " |
";
$md .= '| Login 50 sesi serentak | ' . $fmt($statsA) . " |
";
$md .= '| Alur kerja 50 sesi | ' . $fmt($statsB) . " |
";
foreach ($report['ab'] as $path => $a) {
    $md .= "| ab `$path` | " . $fmt($a['res']) . " |
";
}
$md .= "\nCatatan: pengukuran berasal dari satu laptop yang juga menjalankan klien uji, XAMPP, dan proses lain; hasil bukan jaminan untuk perangkat atau konfigurasi lain.\n";
if ($out) {
    file_put_contents($out, $md);
}
echo "\nSelesai.\n";
