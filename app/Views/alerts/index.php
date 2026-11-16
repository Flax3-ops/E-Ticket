<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="page-heading"><div><p class="eyebrow">PERINGATAN SISTEM</p><h1>Alert</h1><p class="text-secondary">Diperiksa setiap menit oleh tugas terjadwal.</p></div></div>
<div class="table-card"><table class="table mb-0"><thead><tr><th>Aturan</th><th>Pesan</th><th>Kejadian</th><th>Pertama WIB</th><th>Status</th></tr></thead><tbody><?php foreach ($items as $a): ?><tr><td><?= esc($a['rule_code']) ?></td><td><?= esc($a['message']) ?></td><td><?= esc($a['occurrences']) ?></td><td><?= esc(local_time($a['first_seen_at'])) ?></td><td><span class="badge text-bg-<?= $a['resolved_at'] ? 'success' : 'danger' ?>"><?= $a['resolved_at'] ? 'Pulih' : 'Aktif' ?></span></td></tr><?php endforeach ?><?php if (! $items): ?><tr><td colspan="5" class="text-center text-secondary py-5">Belum ada alert.</td></tr><?php endif ?></tbody></table></div>
<?= $this->endSection() ?>
