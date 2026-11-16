<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="page-heading"><div><p class="eyebrow">KONFIGURASI</p><h1>SLA dan ambang alert</h1></div></div>
<form method="post" action="<?= site_url('admin/settings') ?>" class="form-card" style="max-width:700px"><?= csrf_field() ?><div class="row g-3"><?php foreach ($items as $item): ?><div class="col-md-6"><label class="form-label" for="setting-<?= esc($item['key']) ?>"><?= esc($item['key']) ?> <?= str_starts_with($item['key'],'sla_') ? '(jam)' : '(%)' ?></label><input id="setting-<?= esc($item['key']) ?>" class="form-control" type="number" min="1" max="1000" name="settings[<?= esc($item['key']) ?>]" value="<?= esc($item['value']) ?>"></div><?php endforeach ?></div><button class="btn btn-primary mt-4">Simpan pengaturan</button></form>
<?= $this->endSection() ?>
