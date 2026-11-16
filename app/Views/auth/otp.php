<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="auth-shell"><div class="card shadow-sm border-0 auth-card"><div class="card-body p-5">
  <div class="brand-mark">◈</div><p class="eyebrow">VERIFIKASI</p><h1 class="h2 fw-bold mb-2">Masukkan kode OTP</h1><p class="text-secondary">Kode berlaku 5 menit dan hanya dapat dipakai sekali.</p>
  <?php if ($demoOtp): ?><div class="alert alert-info">Kode untuk demo lokal: <strong class="fs-4 d-block letter-space"><?= esc($demoOtp) ?></strong></div><?php endif ?>
  <form method="post" action="<?= site_url('otp') ?>"><?= csrf_field() ?>
    <label class="form-label" for="code">Kode 6 digit</label><input class="form-control mb-4" id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code">
    <button class="btn btn-primary w-100 py-2">Verifikasi dan masuk</button>
  </form>
</div></div></div>
<?= $this->endSection() ?>
