<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($message) ?> — Tiket Layanan</title>
    <style>body{font:16px system-ui;background:#f4f7fb;color:#162033;margin:0;display:grid;place-items:center;min-height:100vh}.card{background:#fff;border-radius:16px;padding:2.5rem;max-width:520px;box-shadow:0 10px 40px #14244216;margin:1rem}a{color:#2962ff}code{background:#eef2f9;padding:.15rem .4rem;border-radius:6px}@media (prefers-color-scheme:dark){body{background:#0f1726;color:#e6ecf7}.card{background:#172136}a{color:#8ab4ff}code{background:#22304b}}</style>
</head>
<body>
<main class="card">
    <p>◈ Tiket Layanan</p>
    <h1><?= esc($message) ?></h1>
    <p>Permintaan tidak dapat diproses (HTTP <?= esc($status) ?>). Kode kesalahan: <code><?= esc($code) ?></code></p>
    <p>Sebutkan kode ini bila menghubungi admin.</p>
    <a href="<?= esc(site_url('/')) ?>">Kembali ke beranda</a>
</main>
</body>
</html>
