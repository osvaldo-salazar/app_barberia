import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

const css   = n => getComputedStyle(document.documentElement).getPropertyValue(n).trim();
const money = n => '₡' + String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
const PALETTE = ['#3568C9', '#2FAE79', '#E0A030', '#C8372D', '#8A6FD6', '#4FB3D9', '#D96BA0', '#7C879C'];

export function initDashboard(d) {
  const charts = [];
  const el = id => document.getElementById(id);

  function build() {
    charts.splice(0).forEach(c => c.destroy());

    Chart.defaults.color = css('--bs-secondary-color');
    Chart.defaults.borderColor = css('--bb-line');
    Chart.defaults.font.family = css('--bs-body-font-family');

    const base = { responsive: true, maintainAspectRatio: false };
    const noLegend = { legend: { display: false } };
    const make = (id, config) => { if (el(id)) charts.push(new Chart(el(id), config)); };

    make('chWeeks', {
      type: 'bar',
      data: {
        labels: d.weeks.labels,
        datasets: [
          { type: 'bar',  label: 'Producido',         data: d.weeks.produced, backgroundColor: PALETTE[0], borderRadius: 6 },
          { type: 'line', label: 'Utilidad del jefe', data: d.weeks.profit, borderColor: PALETTE[1],
            backgroundColor: PALETTE[1], tension: .3, pointRadius: 3 },
        ],
      },
      options: {
        ...base,
        plugins: {
          legend: { display: true, position: 'bottom' },
          tooltip: { callbacks: { label: c => c.dataset.label + ': ' + money(c.parsed.y) } },
        },
        scales: { y: { beginAtZero: true, ticks: { callback: v => money(v) } } },
      },
    });

    make('chBarbers', {
      type: 'bar',
      data: {
        labels: d.barbers.map(b => b.name),
        datasets: [{ data: d.barbers.map(b => b.cuts), backgroundColor: PALETTE, borderRadius: 6 }],
      },
      options: {
        ...base, indexAxis: 'y',
        plugins: { ...noLegend, tooltip: { callbacks: {
          label: c => c.parsed.x + (c.parsed.x === 1 ? ' corte' : ' cortes'),
          afterLabel: c => 'Producido: ' + money(d.barbers[c.dataIndex].total),
        } } },
        scales: { x: { beginAtZero: true, ticks: { precision: 0 } } },
      },
    });

    make('chHours', {
      type: 'bar',
      data: { labels: d.hours.map(h => h.label),
              datasets: [{ data: d.hours.map(h => h.count), backgroundColor: PALETTE[2], borderRadius: 6 }] },
      options: { ...base, plugins: noLegend, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
    });

    make('chDays', {
      type: 'bar',
      data: { labels: d.weekdays.map(x => x.label),
              datasets: [{ data: d.weekdays.map(x => x.count), backgroundColor: PALETTE[4], borderRadius: 6 }] },
      options: { ...base, plugins: noLegend, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
    });

    make('chMethods', {
      type: 'doughnut',
      data: { labels: d.methods.map(m => m.label),
              datasets: [{ data: d.methods.map(m => m.amount), backgroundColor: [PALETTE[1], PALETTE[0], PALETTE[2]],
                           borderColor: css('--bb-surface') }] },
      options: { ...base, plugins: {
        legend: { position: 'bottom' },
        tooltip: { callbacks: { label: c => c.label + ': ' + money(c.parsed) } },
      } },
    });

    make('chServices', {
      type: 'bar',
      data: { labels: d.services.map(s => s.name),
              datasets: [{ data: d.services.map(s => s.count), backgroundColor: PALETTE[5], borderRadius: 6 }] },
      options: { ...base, indexAxis: 'y', plugins: noLegend, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } },
    });
  }

  build();

  // Si cambias entre modo claro y oscuro, los gráficos se redibujan con los colores nuevos
  new MutationObserver(build).observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });
}