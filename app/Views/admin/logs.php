<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="page-heading"><div><p class="eyebrow">AUDIT</p><h1>Log aktivitas</h1></div></div><form method="get" class="d-flex gap-2 mb-3"><input class="form-control" style="max-width:300px" name="action" placeholder="Cari aksi" value="<?= esc((string) request()->getGet('action')) ?>"><button class="btn btn-primary">Cari</button></form>
<div class="table-card"><table class="table mb-0"><thead><tr><th>Waktu WIB</th><th>Pengguna</th><th>Aksi</th><th>Entitas</th><th>IP</th></tr></thead><tbody><?php foreach ($items as $x): ?><tr><td><?= esc(local_time($x['created_at'])) ?></td><td><?= esc($x['name'] ?? 'Sistem') ?></td><td><?= esc($x['action']) ?></td><td><?= esc($x['entity']) ?> #<?= esc($x['entity_id']) ?></td><td><?= esc($x['ip']) ?></td></tr><?php endforeach ?></tbody></table></div>
<?= $this->endSection() ?>
