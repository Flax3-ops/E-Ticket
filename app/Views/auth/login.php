<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="auth-shell"><div class="card shadow-sm border-0 auth-card"><div class="card-body p-5">
  <div class="brand-mark">◈</div><p class="eyebrow">HELPDESK INTERNAL</p><h1 class="h2 fw-bold mb-2">Masuk ke Tiket Layanan</h1><p class="text-secondary mb-4">Kelola permintaan layanan dalam satu tempat.</p>
  <form method="post" action="<?= site_url('login') ?>"><?= csrf_field() ?>
    <label class="form-label" for="email">Email</label><input class="form-control mb-3" id="email" name="email" type="email" required autocomplete="username">
    <label class="form-label" for="password">Password</label><input class="form-control mb-4" id="password" name="password" type="password" required autocomplete="current-password">
    <button class="btn btn-primary w-100 py-2">Lanjut ke OTP</button>
  </form>
</div></div></div>
<?= $this->endSection() ?>
