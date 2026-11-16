<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="page-heading"><div><p class="eyebrow">KESEHATAN SERVER</p><h1>Monitor resource</h1><p class="text-secondary">Diperbarui setiap 5 detik. Garis putus-putus menunjukkan batas atas.</p></div><span id="metric-status" class="badge text-bg-secondary">Memuat...</span></div>
<div class="row g-3 mb-3"><?php foreach (['cpu'=>'CPU','ram'=>'RAM','disk'=>'Disk','db'=>'Koneksi DB'] as $key=>$label): ?><div class="col-6 col-lg-3"><div class="metric-card"><p><?= $label ?></p><strong id="metric-<?= $key ?>">—</strong></div></div><?php endforeach ?></div>
<div class="panel"><h2>Riwayat 60 sampel</h2><canvas id="resource-chart" style="height:360px" aria-label="Grafik resource"></canvas><small class="text-secondary">CPU &gt;85%, RAM &gt;85%, Disk &gt;90%, DB &gt;80% memicu alert.</small></div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?><script src="<?= base_url('vendor/chartjs/chart.umd.min.js') ?>"></script><script>window.monitorHistory=<?= json_encode(array_reverse($history), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script><script src="<?= base_url('assets/js/monitor.js') ?>"></script><?= $this->endSection() ?>
