// Slideshow statistik (Unit 12): storyboard 5 frame, timeline berbasis waktu nyata, kontrol putar/jeda/maju/mundur.
// Data berasal dari server (window.slidesData); tidak ada panggilan jaringan saat slideshow berjalan.
(() => {
  const data = window.slidesData;
  const top = data.top_officer || { name: 'Belum ada', total: 0 };
  // Storyboard: tiap frame punya label, nilai, deskripsi, durasi (ms); 'chart' menandai frame bergrafik.
  const frames = [
    { label: 'TOTAL TIKET', value: data.cards.total, description: 'Permintaan tercatat', duration: 4500 },
    { label: 'TIKET TERBUKA', value: data.cards.open, description: 'Menunggu atau sedang diproses', duration: 4500 },
    { label: 'LEWAT SLA', value: data.cards.overdue, description: 'Perlu perhatian segera', duration: 5000 },
    { label: 'GRAFIK STATUS', value: '', description: 'Distribusi status tiket', duration: 6000, chart: true },
    { label: 'PETUGAS TERATAS', value: top.name, description: `${top.total} tiket selesai`, duration: 5000 }
  ];
  let index = 0;          // indeks frame aktif
  let playing = true;     // status putar/jeda
  let start = performance.now(); // waktu mulai frame aktif (dasar progres)
  let chart;
  const label = document.getElementById('slide-label');
  const value = document.getElementById('slide-value');
  const description = document.getElementById('slide-description');
  const progress = document.getElementById('slide-progress');
  const chartCanvas = document.getElementById('slide-chart');

  // Menggambar satu frame: beberapa pernyataan sekaligus (teks, transisi fade/slide, deskripsi, progres, grafik).
  function renderFrame(i) {
    const frame = frames[i];
    label.textContent = frame.label;
    value.style.display = frame.chart ? 'none' : 'block';
    chartCanvas.style.display = frame.chart ? 'block' : 'none';
    // Transisi nilai: sembunyikan + geser 18 px, lalu tampilkan setelah 120 ms (fade + slide)
    value.style.opacity = 0;
    value.style.transform = 'translateY(18px)';
    setTimeout(() => {
      value.textContent = frame.value;
      value.style.transition = 'all .5s';
      value.style.opacity = 1;
      value.style.transform = 'translateY(0)';
    }, 120);
    description.textContent = frame.description;
    progress.style.width = '0%';
    start = performance.now();
    // Grafik dibuat malas, hanya saat frame bergrafik pertama kali tampil
    if (frame.chart && !chart) {
      chart = new Chart(chartCanvas, {
        type: 'bar',
        data: {
          labels: data.by_status.map(x => x.status),
          datasets: [{ label: 'Tiket', data: data.by_status.map(x => Number(x.total)), backgroundColor: '#2962ff' }]
        },
        options: { responsive: true, maintainAspectRatio: false }
      });
    }
  }
  // Pindah frame secara melingkar (modulo); direction 1 = maju, -1 = mundur
  function next(direction = 1) {
    index = (index + direction + frames.length) % frames.length;
    renderFrame(index);
  }
  // Loop animasi: memperbarui progres dari waktu nyata dan berganti frame saat durasi habis
  function tick(now) {
    if (playing) {
      const elapsed = now - start;
      progress.style.width = Math.min(100, elapsed / frames[index].duration * 100) + '%';
      if (elapsed >= frames[index].duration) next();
    }
    requestAnimationFrame(tick);
  }
  // Kontrol pengguna
  document.getElementById('next').onclick = () => next();
  document.getElementById('prev').onclick = () => next(-1);
  document.getElementById('pause').onclick = event => {
    playing = !playing;
    event.target.textContent = playing ? 'Jeda' : 'Putar';
    start = performance.now();
  };
  document.getElementById('fullscreen').onclick = () => document.documentElement.requestFullscreen?.();
  renderFrame(index);
  requestAnimationFrame(tick);
})();
