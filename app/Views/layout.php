<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tiket Layanan</title>
    <link rel="stylesheet" href="<?= base_url('vendor/bootstrap/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>
<body>
<?php if (session('user_id')): ?>
<nav class="navbar navbar-expand-lg navbar-dark app-nav">
  <div class="container-fluid px-4">
    <a class="navbar-brand fw-bold" href="<?= site_url('/') ?>">◈ Tiket Layanan</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="Buka navigasi"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="mainNav">
      <div class="navbar-nav me-auto">
        <a class="nav-link" href="<?= site_url('/') ?>">Dashboard</a>
        <a class="nav-link" href="<?= site_url('tickets') ?>">Tiket</a>
        <a class="nav-link" href="<?= site_url('notifications') ?>">Notifikasi <span id="unread-badge" class="badge text-bg-danger"></span></a>
        <?php if (session('role') !== 'pemohon'): ?><a class="nav-link" href="<?= site_url('alerts') ?>">Alert</a><?php endif ?>
        <?php if (session('role') === 'admin'): ?>
          <a class="nav-link" href="<?= site_url('monitor') ?>">Monitor</a>
          <a class="nav-link" href="<?= site_url('admin/users') ?>">Pengguna</a>
          <a class="nav-link" href="<?= site_url('admin/categories') ?>">Kategori</a>
          <a class="nav-link" href="<?= site_url('admin/settings') ?>">Pengaturan</a>
          <a class="nav-link" href="<?= site_url('admin/logs') ?>">Log</a>
        <?php endif ?>
      </div>
      <span class="navbar-text me-3"><?= esc(session('name')) ?> · <?= esc(session('role')) ?></span>
      <button class="btn btn-sm btn-outline-light me-2" type="button" id="theme-toggle">Tema</button>
      <form method="post" action="<?= site_url('logout') ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-light">Keluar</button></form>
    </div>
  </div>
</nav>
<?php endif ?>
<main class="container-fluid content-wrap px-4 py-4">
<?php if (session()->getFlashdata('error')): ?><div class="alert alert-danger" role="alert"><?= esc(session()->getFlashdata('error')) ?></div><?php endif ?>
<?php if (session()->getFlashdata('success')): ?><div class="alert alert-success" role="alert"><?= esc(session()->getFlashdata('success')) ?></div><?php endif ?>
<?= $this->renderSection('content') ?>
</main>
<script src="<?= base_url('vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= base_url('assets/js/app.js') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
