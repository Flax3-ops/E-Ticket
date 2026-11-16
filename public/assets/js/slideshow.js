(() => {
  const data = window.slidesData;
  const top = data.top_officer || { name: 'Belum ada', total: 0 };
  const frames = [
    { label: 'TOTAL TIKET', value: data.cards.total, description: 'Permintaan tercatat', duration: 4500 },
    { label: 'TIKET TERBUKA', value: data.cards.open, description: 'Menunggu atau sedang diproses', duration: 4500 },
    { label: 'LEWAT SLA', value: data.cards.overdue, description: 'Perlu perhatian segera', duration: 5000 },
    { label: 'GRAFIK STATUS', value: '', description: 'Distribusi status tiket', duration: 6000, chart: true },
    { label: 'PETUGAS TERATAS', value: top.name, description: `${top.total} tiket selesai`, duration: 5000 }
  ];
  let index = 0;
  let playing = true;
  let start = performance.now();
  let chart;
  const label = document.getElementById('slide-label');
  const value = document.getElementById('slide-value');
  const description = document.getElementById('slide-description');
  const progress = document.getElementById('slide-progress');
  const chartCanvas = document.getElementById('slide-chart');

  function renderFrame(i) {
    const frame = frames[i];
    label.textContent = frame.label;
    value.style.display = frame.chart ? 'none' : 'block';
    chartCanvas.style.display = frame.chart ? 'block' : 'none';
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
  function next(direction = 1) {
    index = (index + direction + frames.length) % frames.length;
    renderFrame(index);
  }
  function tick(now) {
    if (playing) {
      const elapsed = now - start;
      progress.style.width = Math.min(100, elapsed / frames[index].duration * 100) + '%';
      if (elapsed >= frames[index].duration) next();
    }
    requestAnimationFrame(tick);
  }
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
