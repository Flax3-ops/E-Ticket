// Uji browser otomatis lewat Chrome DevTools Protocol (tanpa dependensi npm; Node >= 22).
//   node tests/browser/capture.mjs <browser.exe> <baseUrl> <outDir> <label> [port]
// Mengambil screenshot desktop (1366x800) dan mobile (360x740) tiap peran, memeriksa galat konsol,
// overflow horizontal, grafik, tema, slideshow (next/prev/jeda/otomatis), dan menghitung aksi skenario A04.
// Browser Chromium (Chrome, Edge) mendukung CDP. Firefox tidak; lihat docs/A12-multimedia.md.
import { spawn } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import http from 'node:http';
import path from 'node:path';

const [, , exe, base, outDir, label, portArg] = process.argv;
const port = Number(portArg || 9333);
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
fs.mkdirSync(outDir, { recursive: true });

// ---------------------------------------------------------------- login HTTP (cookie sesi)
// node:http dengan Connection: close (fetch/undici bermasalah dengan server bawaan PHP -S yang HTTP/1.0).
function httpReq(url, { method = 'GET', headers = {}, body } = {}) {
  return new Promise((resolve, reject) => {
    const u = new URL(url);
    const req = http.request({ hostname: u.hostname, port: u.port, path: u.pathname + u.search, method, agent: false, headers: { ...headers, connection: 'close' } }, (res) => {
      let text = '';
      res.setEncoding('utf8');
      res.on('data', (c) => (text += c));
      res.on('end', () => resolve({ status: res.statusCode, setCookie: res.headers['set-cookie'] ?? [], text }));
    });
    req.on('error', reject);
    if (body) req.write(body);
    req.end();
  });
}

async function sessionFor(email) {
  const jar = new Map();
  const req = async (url, opt = {}) => {
    const headers = { ...(opt.headers || {}), cookie: [...jar].map(([k, v]) => `${k}=${v}`).join('; ') };
    const r = await httpReq(url, { ...opt, headers });
    for (const c of r.setCookie) {
      const kv = c.split(';')[0];
      const i = kv.indexOf('=');
      jar.set(kv.slice(0, i), kv.slice(i + 1));
    }
    return { text: async () => r.text };
  };
  const token = (html) => /name="csrf_test_name"\s+value="([^"]+)"/.exec(html)?.[1] ?? '';
  const form = (o) => new URLSearchParams(o).toString();
  const post = (url, body) => req(url, { method: 'POST', headers: { 'content-type': 'application/x-www-form-urlencoded' }, body: form(body) });
  let html = await (await req(`${base}/login`)).text();
  await post(`${base}/login`, { csrf_test_name: token(html), email, password: 'Demo12345!' });
  html = await (await req(`${base}/otp`)).text();
  const code = /letter-space">(\d{6})</.exec(html)?.[1];
  if (!code) throw new Error('OTP demo tidak tampil untuk ' + email);
  await post(`${base}/otp`, { csrf_test_name: token(html), code });
  const sid = jar.get('ci_session');
  if (!sid) throw new Error('Sesi tidak terbentuk untuk ' + email);
  return sid;
}

// ---------------------------------------------------------------- CDP
class Page {
  constructor(ws) {
    this.ws = ws;
    this.id = 0;
    this.pending = new Map();
    this.errors = [];
    this.urls = new Map();
    ws.addEventListener('message', (m) => {
      const msg = JSON.parse(m.data);
      if (msg.id && this.pending.has(msg.id)) {
        const { resolve, reject } = this.pending.get(msg.id);
        this.pending.delete(msg.id);
        msg.error ? reject(new Error(msg.error.message)) : resolve(msg.result);
      } else if (msg.method === 'Runtime.exceptionThrown') {
        this.errors.push('exception: ' + (msg.params.exceptionDetails.exception?.description || msg.params.exceptionDetails.text));
      } else if (msg.method === 'Runtime.consoleAPICalled' && msg.params.type === 'error') {
        this.errors.push('console.error: ' + msg.params.args.map((a) => a.value ?? a.description).join(' '));
      } else if (msg.method === 'Network.requestWillBeSent') {
        this.urls.set(msg.params.requestId, msg.params.request.url);
      } else if (msg.method === 'Network.loadingFailed' && !msg.params.canceled) {
        this.errors.push('network: ' + msg.params.errorText + ' ' + (this.urls.get(msg.params.requestId) ?? '?'));
      } else if (msg.method === 'Network.responseReceived' && msg.params.response.status >= 400 && !/favicon/.test(msg.params.response.url)) {
        this.errors.push(`http ${msg.params.response.status}: ${msg.params.response.url}`);
      }
    });
  }
  send(method, params = {}) {
    const id = ++this.id;
    this.ws.send(JSON.stringify({ id, method, params }));
    return new Promise((resolve, reject) => this.pending.set(id, { resolve, reject }));
  }
  async eval(expr) {
    const r = await this.send('Runtime.evaluate', { expression: expr, returnByValue: true, awaitPromise: true });
    if (r.exceptionDetails) throw new Error(r.exceptionDetails.exception?.description || 'eval gagal');
    return r.result.value;
  }
  async goto(url) {
    await this.send('Page.navigate', { url });
    await this.ready();
  }
  async ready() {
    for (let i = 0; i < 100; i++) {
      await sleep(100);
      try {
        if ((await this.eval('document.readyState')) === 'complete') break;
      } catch { /* sedang navigasi */ }
    }
    await sleep(500);
  }
  async shot(file) {
    const r = await this.send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true });
    fs.writeFileSync(path.join(outDir, file), Buffer.from(r.data, 'base64'));
  }
  async viewport(width, height) {
    await this.send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: width < 500 });
  }
  /** Klik sungguhan pada elemen (pusat kotak) lewat Input.dispatchMouseEvent; mengembalikan true bila ditemukan. */
  async click(selectorOrText) {
    const rect = await this.eval(`(() => {
      const q = ${JSON.stringify(selectorOrText)};
      let el = null;
      try { el = document.querySelector(q); } catch (e) {}
      if (!el) el = [...document.querySelectorAll('a,button,input[type=submit]')].find(e => e.textContent.trim() === q || e.value === q);
      if (!el) return null;
      el.scrollIntoView({block: 'center'});
      const r = el.getBoundingClientRect();
      return {x: r.x + r.width / 2, y: r.y + r.height / 2};
    })()`);
    if (!rect) return false;
    for (const type of ['mousePressed', 'mouseReleased']) {
      await this.send('Input.dispatchMouseEvent', { type, x: rect.x, y: rect.y, button: 'left', clickCount: 1 });
    }
    await sleep(300);
    await this.ready();
    return true;
  }
  async type(selector, text) {
    await this.eval(`document.querySelector(${JSON.stringify(selector)}).focus()`);
    await this.send('Input.insertText', { text });
  }
}

async function launch() {
  const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'tiket-browser-'));
  const proc = spawn(exe, [`--remote-debugging-port=${port}`, `--user-data-dir=${profile}`, '--headless=new', '--no-first-run', '--no-default-browser-check', '--disable-gpu', '--window-size=1366,800', 'about:blank'], { stdio: 'ignore' });
  let version;
  for (let i = 0; i < 60; i++) {
    try {
      version = await (await fetch(`http://127.0.0.1:${port}/json/version`)).json();
      break;
    } catch { await sleep(300); }
  }
  if (!version) throw new Error('Browser tidak merespons CDP');
  const targets = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
  const target = targets.find((t) => t.type === 'page');
  const ws = new WebSocket(target.webSocketDebuggerUrl);
  await new Promise((r) => ws.addEventListener('open', r));
  const page = new Page(ws);
  for (const d of ['Page', 'Runtime', 'Network']) await page.send(d + '.enable');
  return { proc, page, version: version.Browser, profile };
}

async function setSession(page, sid) {
  await page.send('Network.clearBrowserCookies');
  await page.send('Network.setCookie', { name: 'ci_session', value: sid, url: base, httpOnly: true });
}

// ---------------------------------------------------------------- uji
const report = { label, diuji: new Date().toISOString(), halaman: [], interaksi: {}, skenario_a04: {} };
const { proc, page, version } = await launch();
report.browser = version;
const sessions = {
  admin: await sessionFor('admin@tiket.local'),
  petugas: await sessionFor('petugas@tiket.local'),
  pemohon: await sessionFor('pemohon@tiket.local'),
};

const views = [
  ['anon', '/login', 'login'],
  ['admin', '/', 'dashboard-admin'],
  ['admin', '/tickets', 'tiket-daftar'],
  ['admin', '/alerts', 'alert'],
  ['admin', '/monitor', 'monitor'],
  ['admin', '/slideshow', 'slideshow'],
  ['admin', '/admin/users', 'admin-pengguna'],
  ['admin', '/admin/settings', 'admin-pengaturan'],
  ['petugas', '/', 'dashboard-petugas'],
  ['petugas', '/notifications', 'notifikasi'],
  ['pemohon', '/', 'dashboard-pemohon'],
  ['pemohon', '/tickets/new', 'tiket-form'],
];
for (const [w, h, tag] of [[1366, 800, 'desktop'], [360, 740, 'mobile360']]) {
  await page.viewport(w, h);
  for (const [role, url, name] of views) {
    if (role === 'anon') await page.send('Network.clearBrowserCookies');
    else await setSession(page, sessions[role]);
    page.errors.length = 0;
    await page.goto(base + url);
    if (name === 'monitor') await sleep(6500); // beri waktu satu siklus polling 5 detik
    const info = await page.eval(`({title: document.title, scrollW: document.documentElement.scrollWidth, innerW: window.innerWidth, h1: document.querySelector('h1')?.textContent?.trim() ?? '', canvases: document.querySelectorAll('canvas').length})`);
    await page.shot(`${label}-${tag}-${name}.png`);
    report.halaman.push({ viewport: tag, halaman: name, url, judul: info.title, h1: info.h1, overflow_horizontal: info.scrollW > info.innerW + 1, canvas: info.canvases, galat: [...page.errors] });
  }
}

// ---- grafik, tema, slideshow, notifikasi (desktop)
await page.viewport(1366, 800);
await setSession(page, sessions.admin);
await page.goto(base + '/');
report.interaksi.grafik_dashboard = await page.eval(`[...document.querySelectorAll('canvas')].map(c => ({id: c.id, rendered: !!(window.Chart && Chart.getChart(c)), width: c.width, height: c.height}))`);
const before = await page.eval(`document.body.classList.contains('dark')`);
await page.click('#theme-toggle');
const after = await page.eval(`document.body.classList.contains('dark')`);
report.interaksi.tema = { sebelum_gelap: before, sesudah_klik_gelap: after, berubah: before !== after };
await page.shot(`${label}-desktop-dashboard-mode-gelap.png`);
await page.click('#theme-toggle');

await page.goto(base + '/slideshow');
const labels = [];
const snap = async () => labels.push(await page.eval(`document.getElementById('slide-label').textContent`));
await snap();
for (let i = 0; i < 4; i++) { await page.click('#next'); await sleep(700); await snap(); }
await page.shot(`${label}-desktop-slideshow-frame-petugas.png`);
await page.click('#prev'); await sleep(700);
const prevLabel = await page.eval(`document.getElementById('slide-label').textContent`);
await page.click('#pause');
const pausedText = await page.eval(`document.getElementById('pause').textContent`);
const idleA = await page.eval(`document.getElementById('slide-label').textContent`);
await sleep(5500);
const idleB = await page.eval(`document.getElementById('slide-label').textContent`);
await page.click('#pause');
const playText = await page.eval(`document.getElementById('pause').textContent`);
const autoA = await page.eval(`document.getElementById('slide-label').textContent`);
await sleep(5600);
const autoB = await page.eval(`document.getElementById('slide-label').textContent`);
// Layar penuh: headless menolak requestFullscreen; yang diverifikasi adalah klik tombol benar-benar memanggil API tersebut.
await page.eval(`window.__fsCalls = 0; Element.prototype.requestFullscreen = function () { window.__fsCalls++; return Promise.resolve(); };`);
await page.click('#fullscreen');
const fs1 = (await page.eval('window.__fsCalls')) === 1 ? 'tombol memanggil requestFullscreen (headless tidak menampilkan layar penuh)' : 'tombol TIDAK memanggil requestFullscreen';
report.interaksi.slideshow = { frame_berurutan: labels, jumlah_frame_unik: new Set(labels).size, prev_kembali_ke: prevLabel, teks_tombol_saat_jeda: pausedText, tidak_maju_saat_jeda: idleA === idleB, teks_tombol_saat_putar: playText, maju_otomatis: autoA !== autoB, layar_penuh: fs1 };

// ---- skenario A04: hitung aksi (klik/submit) dari dashboard, memakai klik sungguhan
const a04 = report.skenario_a04;
async function scenario(name, role, limit, steps) {
  await setSession(page, sessions[role]);
  await page.goto(base + '/');
  let clicks = 0;
  const log = [];
  for (const step of steps) {
    if (step.type) {
      await page.type(step.sel, step.text);
      log.push(`isi ${step.sel}`);
      continue;
    }
    if (step.select) {
      await page.eval(`(() => { const s = document.querySelector(${JSON.stringify(step.select)}); s.value = ${JSON.stringify(step.value)}; s.dispatchEvent(new Event('change', {bubbles: true})); })()`);
      log.push(`pilih ${step.select}=${step.value}`);
      continue;
    }
    const ok = await page.click(step.click);
    if (!ok) { log.push(`GAGAL klik ${step.click}`); break; }
    clicks++;
    log.push(`klik ${step.click}`);
  }
  a04[name] = { peran: role, aksi_klik: clicks, batas: limit, lulus: clicks <= limit, langkah: log, url_akhir: await page.eval('location.pathname') };
}
const suffix = Date.now().toString().slice(-6);
await scenario('buat_tiket', 'pemohon', 4, [
  { click: '+ Tiket baru' },
  { type: true, sel: '#title', text: 'Uji skenario A04 ' + suffix },
  { type: true, sel: '#description', text: 'Deskripsi uji skenario penghitungan aksi pengguna.' },
  { select: '#category', value: '1' },
  { click: 'Buat tiket' },
]);
const newPath = a04.buat_tiket.url_akhir;
a04.buat_tiket.berhasil = /^\/tickets\/\d+$/.test(newPath);
const newTicketId = newPath.split('/').pop();
await scenario('ambil_tiket', 'petugas', 3, [
  { click: 'Tiket' },
  { click: `a[href$="/tickets/${newTicketId}"]` },
  { click: 'Ambil tiket' },
]);
a04.ambil_tiket.berhasil = await page.eval(`document.body.innerText.includes('Diproses')`);
await scenario('komentar', 'petugas', 3, [
  { click: 'Tiket' },
  { click: `a[href$="/tickets/${newTicketId}"]` },
  { type: true, sel: 'textarea[name=body]', text: 'Sedang diperiksa oleh petugas.' },
  { click: 'Kirim komentar' },
]);
a04.komentar.berhasil = await page.eval(`document.body.innerText.includes('Sedang diperiksa oleh petugas.')`);
await scenario('selesaikan_tiket', 'petugas', 3, [
  { click: 'Tiket' },
  { click: `a[href$="/tickets/${newTicketId}"]` },
  { type: true, sel: 'form[action$="/status"] textarea[name=note]', text: 'Masalah telah diselesaikan.' },
  { click: 'Simpan status' },
]);
a04.selesaikan_tiket.berhasil = await page.eval(`document.body.innerText.includes('Selesai')`);
await scenario('tutup_tiket', 'pemohon', 3, [
  { click: 'Tiket' },
  { click: `a[href$="/tickets/${newTicketId}"]` },
  { select: 'form[action$="/status"] select[name=status]', value: 'Ditutup' },
  { type: true, sel: 'form[action$="/status"] textarea[name=note]', text: 'Terima kasih, sudah teratasi.' },
  { click: 'Simpan status' },
]);
a04.tutup_tiket.berhasil = await page.eval(`document.body.innerText.includes('Ditutup')`);
await scenario('ekspor_laporan', 'admin', 4, [{ click: 'Tiket' }, { click: 'Ekspor Excel' }]);
a04.ekspor_laporan.catatan = 'Unduhan berkas diperiksa terpisah (tests/http/run.php LAP-01); klik terakhir memicu unduhan.';

fs.writeFileSync(path.join(outDir, `hasil-${label}.json`), JSON.stringify(report, null, 2));
proc.kill();
const bad = report.halaman.filter((h) => h.galat.length || h.overflow_horizontal);
console.log(`${label}: ${report.halaman.length} halaman, ${bad.length} bermasalah`);
for (const b of bad) console.log(' -', b.viewport, b.halaman, b.overflow_horizontal ? 'OVERFLOW' : '', b.galat.join(' | '));
console.log('slideshow:', JSON.stringify(report.interaksi.slideshow));
console.log('A04:', Object.entries(a04).map(([k, v]) => `${k}=${v.aksi_klik}/${v.batas}${v.lulus && (v.berhasil ?? true) ? ' ok' : ' GAGAL'}`).join(', '));
process.exit(0);
