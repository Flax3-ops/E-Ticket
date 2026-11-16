<?php

/**
 * Uji HTTP end-to-end lintas peran (A14, audit fitur wajib SRS §4).
 *
 * Pemakaian:  php tests/http/run.php [http://localhost:8082] [--out docs/A14-http-results.md]
 * Syarat: aplikasi berjalan, akun demo ada (DemoSeeder), DEMO_OTP=true.
 * PERINGATAN: uji ini mengubah data. Jalankan pada database demo yang sudah dicadangkan
 * (mysqldump) lalu pulihkan setelahnya.
 */

$base = rtrim($argv[1] ?? 'http://localhost:8082', '/');
$out = null;
foreach ($argv as $i => $arg) {
    if ($arg === '--out') {
        $out = $argv[$i + 1] ?? null;
    }
}
$results = [];
$tmp = sys_get_temp_dir() . '/tiket-http-' . getmypid();
@mkdir($tmp);

require_once __DIR__ . '/Client.php';

function check(string $id, string $desc, bool $ok, string $detail = ''): void
{
    global $results;
    $results[] = [$id, $desc, $ok, $detail];
    echo ($ok ? 'PASS' : 'FAIL') . "  $id  $desc" . ($ok ? '' : "  -> $detail") . "\n";
}

function login(string $base, string $email, string $name, string $tmp, string $password = 'Demo12345!'): Client
{
    $c = new Client($base, $name, $tmp);
    $c->req('GET', '/login');
    [$s, , $loc] = $c->req('POST', '/login', ['email' => $email, 'password' => $password]);
    if ($loc !== '/otp') {
        return $c;
    }
    [, $html] = $c->req('GET', '/otp');
    if (preg_match('/letter-space">(\d{6})</', $html, $m)) {
        $c->req('POST', '/otp', ['code' => $m[1]]);
    }
    return $c;
}

function ticketId(string $loc): int
{
    return preg_match('#/tickets/(\d+)#', $loc, $m) ? (int) $m[1] : 0;
}

function pdo(): PDO
{
    // Database yang diuji; ubah lewat TIKET_TEST_DB bila aplikasi yang diuji memakai database lain.
    return new PDO('mysql:host=127.0.0.1;dbname=' . (getenv('TIKET_TEST_DB') ?: 'tiket_demo') . ';charset=utf8mb4', 'root', '');
}

// ---------------------------------------------------------------- persiapan
$admin = login($base, 'admin@tiket.local', 'admin', $tmp);
$p1 = login($base, 'petugas@tiket.local', 'p1', $tmp);
$p2 = login($base, 'petugas2@tiket.local', 'p2', $tmp);
$u1 = login($base, 'pemohon@tiket.local', 'u1', $tmp);
$u2 = login($base, 'pemohon2@tiket.local', 'u2', $tmp);
$anon = new Client($base, 'anon', $tmp);

[$s] = $admin->req('GET', '/');
check('AUTH-01', 'Login password + OTP berhasil untuk admin (dashboard 200)', $s === 200, "status $s");
[$s, , $l] = $anon->req('GET', '/tickets');
check('AUTH-06a', 'Anonim ke /tickets dialihkan ke /login', in_array($s, [302, 303], true) && $l === '/login', "status $s loc $l");
[$s] = $anon->req('GET', '/api/dashboard/summary', [], [], true);
check('API-403a', 'Anonim ke /api/dashboard/summary -> 403', $s === 403, "status $s");
[$s] = $anon->req('GET', '/monitor/metrics', [], [], true);
check('API-403b', 'Anonim ke /monitor/metrics -> 403', $s === 403, "status $s");

// ------------------------------------------------------- matriks akses rute
$denied = [
    ['pemohon', $u1, 'GET', '/monitor'], ['pemohon', $u1, 'GET', '/monitor/metrics'], ['pemohon', $u1, 'GET', '/admin/users'],
    ['pemohon', $u1, 'GET', '/admin/settings'], ['pemohon', $u1, 'GET', '/admin/logs'], ['pemohon', $u1, 'GET', '/reports/tickets.xlsx'],
    ['pemohon', $u1, 'GET', '/reports/monthly.pdf'], ['pemohon', $u1, 'GET', '/alerts'], ['pemohon', $u1, 'GET', '/api/alerts/active'],
    ['pemohon', $u1, 'GET', '/slideshow'],
    ['petugas', $p1, 'GET', '/monitor'], ['petugas', $p1, 'GET', '/admin/users'], ['petugas', $p1, 'GET', '/reports/tickets.xlsx'],
    ['petugas', $p1, 'GET', '/tickets/new'], ['petugas', $p1, 'GET', '/slideshow'],
    ['admin', $admin, 'GET', '/tickets/new'],
];
foreach ($denied as $i => [$role, $client, $method, $path]) {
    [$s] = $client->req($method, $path);
    check('ACL-' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT), "$role $method $path ditolak (403)", $s === 403, "status $s");
}
$allowed = [[$admin, '/monitor'], [$admin, '/monitor/metrics'], [$admin, '/admin/users'], [$admin, '/admin/categories'], [$admin, '/admin/settings'], [$admin, '/admin/logs'], [$admin, '/slideshow'], [$admin, '/alerts'], [$p1, '/alerts'], [$p1, '/api/alerts/active'], [$u1, '/tickets/new'], [$u1, '/notifications'], [$u1, '/api/notifications/unread'], [$u1, '/api/dashboard/summary']];
foreach ($allowed as $i => [$client, $path]) {
    [$s] = $client->req('GET', $path);
    check('ACL-OK-' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT), "Akses sah GET $path -> 200", $s === 200, "status $s");
}

// ------------------------------------------------------------ alur tiket
$title = 'Uji HTTP <script>alert(1)</script> ' . date('His');
[$s, , $l] = $u1->req('POST', '/tickets', ['title' => $title, 'description' => 'Deskripsi uji lengkap untuk tiket otomatis.', 'category_id' => 1, 'priority' => 'Kritis']);
$tid = ticketId($l);
check('TKT-01', 'Pemohon membuat tiket valid -> redirect ke detail', in_array($s, [302, 303], true) && $tid > 0, "status $s loc $l");
[$s, $b] = $u1->req('GET', "/tickets/$tid");
check('TKT-02', 'Nomor tiket TKT-YYYYMMDD-XXXX tampil', $s === 200 && preg_match('/TKT-' . gmdate('Ymd') . '-\d{4}/', $b) === 1);
check('SEC-XSS', 'Judul berisi <script> di-escape pada halaman detail', ! str_contains($b, '<script>alert(1)</script>') && str_contains($b, '&lt;script&gt;'));
[$s, , $l] = $u1->req('POST', '/tickets', ['title' => 'abc', 'description' => 'pendek', 'category_id' => 1, 'priority' => 'Kritis']);
check('TKT-01b', 'Tiket invalid (judul/deskripsi pendek) ditolak', in_array($s, [302, 303], true) && ticketId($l) === 0, "loc $l");
[$s, , $l] = $u1->req('POST', '/tickets', ['title' => 'Judul valid cukup', 'description' => 'Deskripsi cukup panjang.', 'category_id' => 9999, 'priority' => 'Kritis']);
check('TKT-01c', 'Kategori tidak ada ditolak', in_array($s, [302, 303], true) && ticketId($l) === 0, "loc $l");
$row = pdo()->query("SELECT priority,status,sla_due_at,created_at,TIMESTAMPDIFF(HOUR,created_at,sla_due_at) h FROM tickets WHERE id=$tid")->fetch(PDO::FETCH_ASSOC);
check('TKT-03', 'sla_due_at = created_at + 4 jam (Kritis)', (int) $row['h'] === 4, json_encode($row));

[$s, $b] = $u2->req('GET', "/tickets/$tid");
check('ACL-T01', 'Pemohon lain tidak bisa melihat tiket (404)', $s === 404, "status $s");
check('LOG-04', 'Halaman galat ramah pengguna menampilkan kode kesalahan (E-TKT-404)', str_contains($b, 'E-TKT-404') && str_contains($b, '<html'), substr(strip_tags($b), 0, 80));
[$s, $b] = $u2->req('GET', '/admin/users');
check('LOG-04b', 'Galat 403 menampilkan kode E-ACL-403', $s === 403 && str_contains($b, 'E-ACL-403'), "status $s");
[$s] = $u2->req('GET', "/tickets/$tid/edit");
check('ACL-T02', 'Pemohon lain tidak bisa edit (403)', $s === 403, "status $s");
[$s] = $u2->req('POST', "/tickets/$tid/comments", ['body' => 'Komentar liar']);
check('ACL-T03', 'Pemohon lain tidak bisa komentar (403)', $s === 403, "status $s");
[$s] = $u2->req('POST', "/tickets/$tid/status", ['status' => 'Ditutup', 'note' => 'x']);
check('ACL-T04', 'Pemohon lain tidak bisa ubah status (tidak ada perubahan)', pdo()->query("SELECT status FROM tickets WHERE id=$tid")->fetchColumn() === 'Baru');
foreach (['take' => [], 'priority' => ['priority' => 'Rendah', 'reason' => 'x'], 'delete' => [], 'assign' => ['officer_id' => 2, 'reason' => 'x']] as $act => $data) {
    [$s] = $u1->req('POST', "/tickets/$tid/$act", $data);
    check("ACL-T05-$act", "Pemohon POST /$act ditolak (403)", $s === 403, "status $s");
    if ($act === 'take') {
        continue; // petugas memang boleh mengambil; diuji terpisah pada uji bersamaan
    }
    [$s] = $p1->req('POST', "/tickets/$tid/" . $act, $data);
    check("ACL-T06-$act", "Petugas POST /$act ditolak (403)", $s === 403, "status $s");
}
[$s] = $u1->req('GET', '/tickets?q=' . urlencode('Uji HTTP'));
check('TKT-04', 'Daftar tiket dengan pencarian judul 200', $s === 200);

// pemohon edit saat status Baru
[$s, , $l] = $u1->req('POST', "/tickets/$tid/edit", ['title' => 'Uji HTTP diubah pemohon', 'description' => 'Deskripsi diubah oleh pemohon pemilik.', 'category_id' => 2, 'priority' => 'Kritis']);
check('TKT-09', 'Pemohon pemilik mengubah tiket Baru', in_array($s, [302, 303], true) && pdo()->query("SELECT title FROM tickets WHERE id=$tid")->fetchColumn() === 'Uji HTTP diubah pemohon');
check('TKT-09b', 'Prioritas tidak berubah lewat edit pemohon', pdo()->query("SELECT priority FROM tickets WHERE id=$tid")->fetchColumn() === 'Kritis');

// pengambilan bersamaan oleh dua petugas
$mh = curl_multi_init();
$handles = [];
foreach ([$p1, $p2] as $k => $cl) {
    $ref = new ReflectionClass($cl);
    $tokenMethod = $ref->getMethod('token');
    $tokenMethod->setAccessible(true);
    $token = $tokenMethod->invoke($cl, '/');
    $ch = curl_init($base . "/tickets/$tid/take");
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(['csrf_test_name' => $token]), CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $cl->jar, CURLOPT_COOKIEJAR => $cl->jar . '.x']);
    curl_multi_add_handle($mh, $ch);
    $handles[] = $ch;
}
do {
    curl_multi_exec($mh, $running);
    curl_multi_select($mh);
} while ($running > 0);
foreach ($handles as $ch) {
    curl_multi_remove_handle($mh, $ch);
}
$assigned = pdo()->query("SELECT assigned_to FROM tickets WHERE id=$tid")->fetchColumn();
$logs = (int) pdo()->query("SELECT COUNT(*) FROM ticket_logs WHERE ticket_id=$tid AND new_status='Diproses'")->fetchColumn();
check('TKT-05', 'Dua petugas mengambil bersamaan: tepat satu berhasil (1 log Diproses)', $assigned !== null && $logs === 1, "assigned=$assigned logs=$logs");
$owner = (int) $assigned;
$ownerClient = $owner === (int) pdo()->query("SELECT id FROM users WHERE email='petugas@tiket.local'")->fetchColumn() ? $p1 : $p2;
$otherClient = $ownerClient === $p1 ? $p2 : $p1;
[$s, $b] = $otherClient->follow('POST', "/tickets/$tid/take");
check('TKT-05b', 'Petugas kedua mendapat pesan tiket sudah diambil', str_contains($b, 'sudah diambil'), 'tidak ada pesan');

[$s] = $otherClient->req('GET', "/tickets/$tid");
check('ACL-T07', 'Petugas lain tetap boleh melihat tiket aktif (200)', $s === 200);
[$s] = $otherClient->req('POST', "/tickets/$tid/comments", ['body' => 'Komentar petugas lain']);
check('ACL-T08', 'Petugas bukan penerima tidak bisa komentar (403)', $s === 403, "status $s");
[$s] = $otherClient->req('POST', "/tickets/$tid/status", ['status' => 'Selesai', 'note' => 'curang']);
check('ACL-T09', 'Petugas bukan penerima tidak bisa menyelesaikan', pdo()->query("SELECT status FROM tickets WHERE id=$tid")->fetchColumn() === 'Diproses');
[$s] = $ownerClient->req('POST', "/tickets/$tid/comments", ['body' => 'Sedang kami cek.']);
check('KOM-01', 'Petugas penerima menambah komentar', in_array($s, [302, 303], true) && (int) pdo()->query("SELECT COUNT(*) FROM comments WHERE ticket_id=$tid AND body='Sedang kami cek.'")->fetchColumn() === 1);
[$s] = $ownerClient->req('POST', "/tickets/$tid/status", ['status' => 'Selesai', 'note' => '']);
check('TKT-06a', 'Selesai tanpa catatan ditolak', pdo()->query("SELECT status FROM tickets WHERE id=$tid")->fetchColumn() === 'Diproses');
[$s] = $ownerClient->req('POST', "/tickets/$tid/status", ['status' => 'Ditutup', 'note' => 'lompat']);
check('TKT-07', 'Transisi tidak sah Diproses->Ditutup ditolak', pdo()->query("SELECT status FROM tickets WHERE id=$tid")->fetchColumn() === 'Diproses');
[$s] = $u1->req('POST', "/tickets/$tid/edit", ['title' => 'Coba ubah saat diproses', 'description' => 'Deskripsi untuk uji edit.', 'category_id' => 1, 'priority' => 'Kritis']);
check('TKT-09c', 'Pemohon tidak boleh edit setelah status bukan Baru (403)', $s === 403, "status $s");

// lampiran
$png = "$tmp/a.png";
file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
$exe = "$tmp/evil.png";
file_put_contents($exe, "MZ\x90\x00 bukan gambar sungguhan");
$big = "$tmp/big.pdf";
file_put_contents($big, "%PDF-1.4\n" . str_repeat('A', 2 * 1024 * 1024 + 10));
$pdf = "$tmp/ok.pdf";
file_put_contents($pdf, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
$attach = static fn (Client $c, string $file, string $mime, string $name) => $c->req('POST', "/tickets/$tid/attachments", [], ['attachment' => [$file, $mime, $name]]);
$count = static fn () => (int) pdo()->query("SELECT COUNT(*) FROM attachments WHERE ticket_id=$tid")->fetchColumn();

$attach($u1, $exe, 'image/png', 'evil.png');
check('KOM-03a', 'Berkas palsu (MZ berekstensi png) ditolak lewat validasi MIME', $count() === 0, 'count=' . $count());
$attach($u1, $big, 'application/pdf', 'big.pdf');
check('KOM-02a', 'Berkas > 2 MB ditolak', $count() === 0, 'count=' . $count());
$attach($u1, $png, 'image/png', '../../evil name.png');
check('KOM-02b', 'PNG valid diunggah pemilik', $count() === 1, 'count=' . $count());
$stored = pdo()->query("SELECT stored_name FROM attachments WHERE ticket_id=$tid")->fetchColumn();
check('KOM-03b', 'Nama berkas diacak (hex 32 + ekstensi), bukan nama asli', (bool) preg_match('/^[0-9a-f]{32}\.png$/', (string) $stored), (string) $stored);
$attach($u1, $pdf, 'application/pdf', 'ok.pdf');
$attach($ownerClient, $png, 'image/png', 'petugas.png');
$attach($u1, $png, 'image/png', 'ke4.png');
check('KOM-02c', 'Lampiran ke-4 ditolak (maks 3)', $count() === 3, 'count=' . $count());
$attach($u2, $png, 'image/png', 'liar.png');
check('KOM-02d', 'Pemohon lain tidak bisa mengunggah', $count() === 3);
$aid = (int) pdo()->query("SELECT id FROM attachments WHERE ticket_id=$tid ORDER BY id LIMIT 1")->fetchColumn();
foreach ([['pemohon pemilik', $u1, 200], ['petugas penerima', $ownerClient, 200], ['admin', $admin, 200], ['pemohon lain', $u2, 404], ['petugas bukan penerima', $otherClient, 404], ['anonim', $anon, 302]] as $k => [$who, $cl, $exp]) {
    [$s] = $cl->req('GET', "/attachments/$aid");
    check('KOM-04-' . ($k + 1), "Unduh lampiran oleh $who -> $exp", $s === $exp, "status $s");
}

// penyelesaian, buka ulang, tutup
[$s] = $ownerClient->req('POST', "/tickets/$tid/status", ['status' => 'Selesai', 'note' => 'Sudah diperbaiki.']);
check('TKT-06b', 'Petugas penerima menyelesaikan dengan catatan', pdo()->query("SELECT status FROM tickets WHERE id=$tid")->fetchColumn() === 'Selesai');
[$s] = $u2->req('POST', "/tickets/$tid/status", ['status' => 'Diproses', 'note' => 'buka']);
check('TKT-06c', 'Pemohon lain tidak bisa membuka ulang', pdo()->query("SELECT status FROM tickets WHERE id=$tid")->fetchColumn() === 'Selesai');
[$s] = $u1->req('POST', "/tickets/$tid/status", ['status' => 'Diproses', 'note' => 'Masih bermasalah.']);
check('TKT-06d', 'Pemohon pemilik membuka ulang dalam 7 hari', pdo()->query("SELECT status FROM tickets WHERE id=$tid")->fetchColumn() === 'Diproses');
$ownerClient->req('POST', "/tickets/$tid/status", ['status' => 'Selesai', 'note' => 'Diperbaiki lagi.']);
// batas 7 hari: mundurkan resolved_at secara langsung di DB uji
pdo()->exec("UPDATE tickets SET resolved_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 8 DAY) WHERE id=$tid");
$u1->req('POST', "/tickets/$tid/status", ['status' => 'Diproses', 'note' => 'Terlambat buka ulang.']);
check('TKT-06e', 'Buka ulang > 7x24 jam ditolak', pdo()->query("SELECT status FROM tickets WHERE id=$tid")->fetchColumn() === 'Selesai');
$u1->req('POST', "/tickets/$tid/status", ['status' => 'Ditutup', 'note' => 'Terima kasih.']);
check('TKT-06f', 'Pemohon menutup tiket Selesai', pdo()->query("SELECT status FROM tickets WHERE id=$tid")->fetchColumn() === 'Ditutup');
$u1->req('POST', "/tickets/$tid/status", ['status' => 'Diproses', 'note' => 'Buka lagi.']);
$admin->req('POST', "/tickets/$tid/status", ['status' => 'Diproses', 'note' => 'Koreksi admin.']);
check('TKT-06g', 'Tiket Ditutup final: pemohon dan admin tidak bisa membuka ulang', pdo()->query("SELECT status FROM tickets WHERE id=$tid")->fetchColumn() === 'Ditutup');
$nlog = (int) pdo()->query("SELECT COUNT(*) FROM ticket_logs WHERE ticket_id=$tid AND changed_by IS NOT NULL")->fetchColumn();
$nall = (int) pdo()->query("SELECT COUNT(*) FROM ticket_logs WHERE ticket_id=$tid")->fetchColumn();
check('TKT-08', 'Setiap perubahan status tercatat trigger dengan pelaku', $nall >= 5 && $nlog === $nall, "log=$nall berpelaku=$nlog");

// F-TKT-11/12: prioritas + penugasan admin
[$s, , $l] = $u1->req('POST', '/tickets', ['title' => 'Tiket untuk uji admin', 'description' => 'Menguji prioritas dan penugasan.', 'category_id' => 1, 'priority' => 'Rendah']);
$t2 = ticketId($l);
$before = pdo()->query("SELECT sla_due_at FROM tickets WHERE id=$t2")->fetchColumn();
$admin->req('POST', "/tickets/$t2/priority", ['priority' => 'Kritis', 'reason' => 'Dampak luas']);
$r = pdo()->query("SELECT priority,TIMESTAMPDIFF(HOUR,created_at,sla_due_at) h FROM tickets WHERE id=$t2")->fetch(PDO::FETCH_ASSOC);
check('TKT-11', 'Admin ubah prioritas -> SLA dihitung ulang dari created_at (72 -> 4 jam)', $r['priority'] === 'Kritis' && (int) $r['h'] === 4, json_encode($r));
$admin->req('POST', "/tickets/$t2/priority", ['priority' => 'Rendah', 'reason' => '']);
check('TKT-11b', 'Ubah prioritas tanpa alasan ditolak', pdo()->query("SELECT priority FROM tickets WHERE id=$t2")->fetchColumn() === 'Kritis');
$pid2 = (int) pdo()->query("SELECT id FROM users WHERE email='petugas2@tiket.local'")->fetchColumn();
$admin->req('POST', "/tickets/$t2/assign", ['officer_id' => $pid2, 'reason' => 'Pemerataan beban']);
$r = pdo()->query("SELECT status, assigned_to FROM tickets WHERE id=$t2")->fetch(PDO::FETCH_ASSOC);
check('TKT-12', 'Admin menugaskan petugas dengan alasan (assigned_to terisi, status Diproses)', (int) $r['assigned_to'] === $pid2 && $r['status'] === 'Diproses', json_encode($r));
$admin->req('POST', "/tickets/$t2/assign", ['officer_id' => $pid2, 'reason' => '']);
check('TKT-12b', 'Penugasan tanpa alasan ditolak', (int) pdo()->query("SELECT COUNT(*) FROM activity_logs WHERE action='ticket_reassigned' AND entity_id=$t2")->fetchColumn() === 1);

// soft delete
$admin->req('POST', "/tickets/$t2/delete");
check('TKT-10', 'Admin soft delete tiket', pdo()->query("SELECT deleted_at FROM tickets WHERE id=$t2")->fetchColumn() !== null);
foreach ([['pemohon pemilik', $u1, 404], ['petugas', $p2, 404], ['admin', $admin, 200]] as $k => [$who, $cl, $exp]) {
    [$s] = $cl->req('GET', "/tickets/$t2");
    check('TKT-10-' . ($k + 1), "Tiket soft delete dilihat $who -> $exp", $s === $exp, "status $s");
}
[$s, $b] = $admin->req('GET', '/tickets?q=' . urlencode('Tiket untuk uji admin'));
check('TKT-10d', 'Tiket soft delete tidak muncul di daftar', ! str_contains($b, 'TKT-' . gmdate('Ymd') . '-' . str_pad((string) pdo()->query("SELECT SUBSTRING(ticket_no,-4) FROM tickets WHERE id=$t2")->fetchColumn(), 4, '0', STR_PAD_LEFT)));

// dashboard JSON
[$s, $b] = $admin->req('GET', '/api/dashboard/summary', [], [], true);
$j = json_decode($b, true);
check('DSB-03', 'Endpoint summary memuat cards, by_status, by_category, by_day', is_array($j) && isset($j['cards'], $j['by_status'], $j['by_category'], $j['by_day']));
$tot = array_sum(array_column($j['by_status'] ?? [], 'total'));
$real = (int) pdo()->query('SELECT COUNT(*) FROM tickets WHERE deleted_at IS NULL')->fetchColumn();
check('DSB-03b', 'Jumlah by_status = tiket aktif (tanpa duplikasi, tanpa soft delete)', $tot === $real && (int) $j['cards']['total'] === $real, "by_status=$tot real=$real cards={$j['cards']['total']}");
[$s, $b] = $u1->req('GET', '/api/dashboard/summary', [], [], true);
$jj = json_decode($b, true);
$own = (int) pdo()->query("SELECT COUNT(*) FROM tickets WHERE deleted_at IS NULL AND user_id=(SELECT id FROM users WHERE email='pemohon@tiket.local')")->fetchColumn();
check('DSB-03c', 'Dashboard pemohon hanya menghitung tiket miliknya', (int) $jj['cards']['total'] === $own, "cards={$jj['cards']['total']} own=$own");

// ekspor
$countRows = static function (string $xlsxBody) use ($tmp): int {
    $f = "$tmp/x.xlsx";
    file_put_contents($f, $xlsxBody);
    $z = new ZipArchive();
    if ($z->open($f) !== true) {
        return -1; // bukan berkas xlsx (mis. halaman galat)
    }
    $xml = (string) $z->getFromName('xl/worksheets/sheet1.xml');
    return preg_match_all('/<row /', $xml) - 1;
};
[$s, $b] = $admin->req('GET', '/reports/tickets.xlsx');
$all = $countRows($b);
check('LAP-01', 'Ekspor Excel semua tiket aktif', $s === 200 && $all === $real, "baris=$all real=$real");
[$s, $b] = $admin->req('GET', '/reports/tickets.xlsx?status=Selesai&priority=Kritis');
$flt = $countRows($b);
$exp = (int) pdo()->query("SELECT COUNT(*) FROM tickets WHERE deleted_at IS NULL AND status='Selesai' AND priority='Kritis'")->fetchColumn();
check('LAP-01b', 'Ekspor Excel terfilter sesuai jumlah filter', $flt === $exp, "baris=$flt harapan=$exp");
[$s, $b] = $admin->req('GET', '/reports/monthly.pdf');
check('LAP-02', 'Ekspor PDF bulanan (PDF valid)', $s === 200 && str_starts_with($b, '%PDF'));
[$s] = $admin->req('GET', '/reports/monthly.pdf?month=2026-13');
check('LAP-02b', 'Bulan tidak valid -> 400', $s === 400, "status $s");

// F-NTF-10 acknowledge
// Alert uji khusus (idempoten): hapus sisa uji sebelumnya, buat baru memakai SATU koneksi agar lastInsertId valid.
$db = pdo();
$db->exec("DELETE FROM alerts WHERE rule_code = 'UJI'");
$db->exec("INSERT INTO alerts (rule_code,object_key,active_key,level,message,occurrences,first_seen_at,last_seen_at,created_at,updated_at) VALUES ('UJI','http','UJI:http','critical','Alert uji HTTP',1,UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP())");
$aid = (int) $db->lastInsertId();
[$s] = $p1->req('POST', "/alerts/$aid/ack");
check('NTF-10a', 'Petugas tidak boleh acknowledge (403)', $s === 403, "status $s");
$admin->req('POST', "/alerts/$aid/ack");
$r = pdo()->query("SELECT acknowledged_by, resolved_at FROM alerts WHERE id=$aid")->fetch(PDO::FETCH_ASSOC) ?: [];
check('NTF-10b', 'Admin acknowledge: acknowledged_by terisi, alert belum pulih', isset($r['acknowledged_by']) && $r['acknowledged_by'] !== null && $r['resolved_at'] === null, json_encode($r));
if (pdo()->query("SHOW COLUMNS FROM alerts LIKE 'acknowledged_at'")->fetch()) {
    $at = pdo()->query("SELECT acknowledged_at FROM alerts WHERE id=$aid")->fetchColumn();
    check('NTF-10c', 'Waktu acknowledge tersimpan (acknowledged_at, patch v1.1)', $at !== null && strtotime($at . ' UTC') > time() - 120, (string) $at);
}

// F-MST-01/02: CRUD kategori dan pengguna oleh admin
$cname = 'Kategori Uji ' . date('His');
$admin->req('POST', '/admin/categories', ['name' => $cname, 'description' => 'uji']);
$cid = (int) pdo()->query("SELECT id FROM categories WHERE name=" . pdo()->quote($cname))->fetchColumn();
check('MST-01a', 'Admin menambah kategori', $cid > 0);
$admin->req('POST', '/admin/categories', ['id' => $cid, 'name' => $cname . ' ubah', 'description' => 'diubah']);
check('MST-01b', 'Admin mengubah kategori', pdo()->query("SELECT description FROM categories WHERE id=$cid")->fetchColumn() === 'diubah');
$admin->req('POST', '/admin/categories', ['name' => 'x', 'description' => '']);
check('MST-01c', 'Nama kategori terlalu pendek ditolak', (int) pdo()->query("SELECT COUNT(*) FROM categories WHERE name='x'")->fetchColumn() === 0);
$admin->req('POST', "/admin/categories/1/delete");
check('MST-01d', 'Kategori yang dipakai tiket tidak bisa dihapus', (int) pdo()->query('SELECT COUNT(*) FROM categories WHERE id=1')->fetchColumn() === 1);
$admin->req('POST', "/admin/categories/$cid/delete");
check('MST-01e', 'Admin menghapus kategori yang tidak dipakai', (int) pdo()->query("SELECT COUNT(*) FROM categories WHERE id=$cid")->fetchColumn() === 0);
[$s] = $u1->req('POST', '/admin/categories', ['name' => 'Kategori liar', 'description' => '']);
check('MST-01f', 'Pemohon tidak bisa menambah kategori (403)', $s === 403 && (int) pdo()->query("SELECT COUNT(*) FROM categories WHERE name='Kategori liar'")->fetchColumn() === 0, "status $s");
$email = 'uji' . date('His') . '@tiket.local';
$admin->req('POST', '/admin/users', ['name' => 'Pengguna Uji', 'email' => $email, 'role' => 'petugas', 'password' => 'Rahasia123!', 'is_active' => 1]);
$urow = pdo()->query('SELECT id, role, password_hash FROM users WHERE email=' . pdo()->quote($email))->fetch(PDO::FETCH_ASSOC);
check('MST-02a', 'Admin menambah pengguna; password disimpan sebagai hash bcrypt', $urow && $urow['role'] === 'petugas' && str_starts_with((string) $urow['password_hash'], '$2y$') && $urow['password_hash'] !== 'Rahasia123!');
$admin->req('POST', '/admin/users', ['name' => 'Pengguna Uji', 'email' => 'bukan-email', 'role' => 'petugas', 'password' => 'Rahasia123!']);
check('MST-02b', 'Email tidak valid ditolak', (int) pdo()->query("SELECT COUNT(*) FROM users WHERE email='bukan-email'")->fetchColumn() === 0);
$admin->req('POST', '/admin/users', ['id' => $urow['id'], 'name' => 'Pengguna Uji', 'email' => $email, 'role' => 'admin', 'is_active' => 1]);
check('MST-02c', 'Admin mengubah peran pengguna', pdo()->query('SELECT role FROM users WHERE id=' . (int) $urow['id'])->fetchColumn() === 'admin');
$pw = login($base, $email, 'baru', $tmp, 'Rahasia123!');
[$s] = $pw->req('GET', '/admin/users');
check('MST-02d', 'Pengguna baru dapat login (OTP) dan peran baru berlaku langsung', $s === 200, "status $s");

// pengguna nonaktif: sesi berlaku langsung
$u3 = login($base, 'pemohon3@tiket.local', 'u3', $tmp);
[$s] = $u3->req('GET', '/tickets');
$uid3 = (int) pdo()->query("SELECT id FROM users WHERE email='pemohon3@tiket.local'")->fetchColumn();
$admin->req('POST', "/admin/users/$uid3/delete");
[$s2, , $l2] = $u3->req('GET', '/tickets');
check('AUTH-ACT', 'Sesi pengguna yang dinonaktifkan admin langsung ditolak', $s === 200 && $s2 === 302 && $l2 === '/login', "sebelum=$s sesudah=$s2 $l2");
[$s3, , $l3] = $anon->req('POST', '/login', ['email' => 'pemohon3@tiket.local', 'password' => 'Demo12345!']);
check('AUTH-ACT2', 'Login pengguna nonaktif ditolak', $l3 !== '/otp', "loc $l3");
$admin->req('POST', '/admin/users', ['id' => $uid3, 'name' => 'Pemohon 3', 'email' => 'pemohon3@tiket.local', 'role' => 'pemohon', 'is_active' => 1]);

// OTP salah 3 kali -> terkunci
$bad = new Client($base, 'bad', $tmp);
$bad->req('GET', '/login');
[, , $l] = $bad->req('POST', '/login', ['email' => 'pemohon6@tiket.local', 'password' => 'Demo12345!']);
for ($i = 0; $i < 3; $i++) {
    $bad->req('POST', '/otp', ['code' => '000000']);
}
[$s, , $l] = $bad->req('POST', '/login', ['email' => 'pemohon6@tiket.local', 'password' => 'Demo12345!']);
check('AUTH-04', 'OTP salah 3x mengunci akun: login benar berikutnya ditolak', $l !== '/otp', "loc $l");
check('AUTH-07', 'Log umum tidak memuat nilai OTP mentah', (int) pdo()->query("SELECT COUNT(*) FROM activity_logs WHERE action LIKE '%otp%' AND action REGEXP '[0-9]{6}'")->fetchColumn() === 0);
$locked = pdo()->query("SELECT locked_until FROM users WHERE email='pemohon6@tiket.local'")->fetchColumn();
check('AUTH-04b', 'locked_until diisi ~10 menit ke depan', $locked && strtotime($locked . ' UTC') > time() + 480 && strtotime($locked . ' UTC') <= time() + 600, (string) $locked);
pdo()->exec("UPDATE users SET locked_until=NULL WHERE email='pemohon6@tiket.local'");

[$s, , $l] = $admin->req('POST', '/logout');
[$s2, , $l2] = $admin->req('GET', '/tickets');
check('AUTH-05', 'Logout menghapus sesi', in_array($s, [302, 303], true) && $l2 === '/login', "loc $l2");

// ------------------------------------------------------------- ringkasan
$pass = count(array_filter($results, static fn ($r) => $r[2]));
$total = count($results);
echo "\n$pass/$total lulus\n";
if ($out) {
    $md = "# Hasil uji HTTP lintas peran\n\n";
    $md .= 'Dijalankan: ' . gmdate('Y-m-d H:i') . " UTC — `php tests/http/run.php $base`\n\n";
    $md .= "Ringkasan: **$pass dari $total** pemeriksaan lulus.\n\n| ID | Pemeriksaan | Hasil |\n|---|---|---|\n";
    foreach ($results as [$id, $desc, $ok, $detail]) {
        $md .= "| $id | " . str_replace('|', '\\|', $desc) . ' | ' . ($ok ? 'Lulus' : 'GAGAL: ' . str_replace('|', '\\|', $detail)) . " |\n";
    }
    file_put_contents($out, $md);
}
exit($pass === $total ? 0 : 1);
