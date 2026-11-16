<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="page-heading"><div><p class="eyebrow">RINGKASAN LAYANAN</p><h1>Dashboard</h1><p class="text-secondary">Pantau tiket dan tindak lanjut terbaru.</p></div><div class="d-flex gap-2"><?php if (session('role') === 'pemohon'): ?><a href="<?= site_url('tickets/new') ?>" class="btn btn-primary">+ Tiket baru</a><?php endif ?><?php if (session('role') === 'admin'): ?><a href="<?= site_url('slideshow') ?>" class="btn btn-outline-primary">Layar statistik</a><?php endif ?></div></div>
<?php foreach ($alerts as $alert): ?><div class="alert alert-danger d-flex justify-content-between"><span><strong>Alert:</strong> <?= esc($alert['message']) ?></span><a href="<?= site_url('alerts') ?>">Lihat</a></div><?php endforeach ?>
<div class="row g-3 mb-4">
<?php foreach (['total' => ['Total tiket','primary'], 'open' => ['Tiket terbuka','info'], 'overdue' => ['Lewat SLA','danger'], 'average_minutes' => ['Rata-rata selesai (menit)','success']] as $key => $meta): ?>
<div class="col-6 col-xl-3"><div class="metric-card"><div class="metric-icon text-<?= $meta[1] ?>">●</div><p><?= esc($meta[0]) ?></p><strong><?= esc($data['cards'][$key]) ?></strong></div></div>
<?php endforeach ?>
</div>
<div class="row g-3"><div class="col-lg-4"><div class="panel"><h2>Status tiket</h2><canvas id="status-chart" aria-label="Grafik status tiket"></canvas></div></div><div class="col-lg-4"><div class="panel"><h2>Tiket per kategori</h2><canvas id="category-chart" aria-label="Grafik kategori tiket"></canvas></div></div><div class="col-lg-4"><div class="panel"><h2>30 hari terakhir</h2><canvas id="day-chart" aria-label="Grafik tiket harian"></canvas></div></div></div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script src="<?= base_url('vendor/chartjs/chart.umd.min.js') ?>"></script>
<script>window.dashboardData = <?= json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
<?= $this->endSection() ?>
