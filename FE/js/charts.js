/**
 * charts.js - Inisialisasi dan konfigurasi visual grafik penjualan dengan Chart.js
 */

let salesChartInstance = null;

const chartDataSets = {
  weekly: {
    labels: ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'],
    data: [145000, 210000, 180000, 320000, 280000, 490000, 395000]
  },
  monthly: {
    labels: ['Mgg 1', 'Mgg 2', 'Mgg 3', 'Mgg 4'],
    data: [1250000, 1890000, 2100000, 2450000]
  }
};

function initSalesChart() {
  const canvas = document.getElementById('salesChart');
  if (!canvas) return;

  const ctx = canvas.getContext('2d');
  
  // Gradient fill di bawah garis
  const gradient = ctx.createLinearGradient(0, 0, 0, 300);
  gradient.addColorStop(0, 'rgba(220, 238, 177, 0.6)'); // block-lime pastel
  gradient.addColorStop(1, 'rgba(255, 255, 255, 0.0)');

  salesChartInstance = new Chart(ctx, {
    type: 'line',
    data: {
      labels: chartDataSets.weekly.labels,
      datasets: [{
        label: 'Pendapatan (Rp)',
        data: chartDataSets.weekly.data,
        borderColor: '#000000',
        borderWidth: 2.5,
        backgroundColor: gradient,
        fill: true,
        tension: 0.35,
        pointBackgroundColor: '#000000',
        pointBorderColor: '#ffffff',
        pointBorderWidth: 2,
        pointRadius: 4,
        pointHoverRadius: 6
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false
        },
        tooltip: {
          backgroundColor: '#000000',
          titleColor: '#ffffff',
          bodyColor: '#ffffff',
          titleFont: {
            family: 'JetBrains Mono',
            size: 11
          },
          bodyFont: {
            family: 'Inter',
            size: 13
          },
          padding: 10,
          cornerRadius: 6,
          displayColors: false,
          callbacks: {
            label: function(context) {
              return 'Rp ' + Number(context.parsed.y).toLocaleString('id-ID');
            }
          }
        }
      },
      scales: {
        x: {
          grid: {
            color: '#f1f1f1',
            drawBorder: false
          },
          ticks: {
            font: {
              family: 'JetBrains Mono',
              size: 11
            },
            color: '#71717a'
          }
        },
        y: {
          grid: {
            color: '#f1f1f1',
            drawBorder: false
          },
          ticks: {
            font: {
              family: 'JetBrains Mono',
              size: 11
            },
            color: '#71717a',
            callback: function(val) {
              return (val / 1000) + 'k';
            }
          }
        }
      }
    }
  });

  // Listener untuk toggle periode grafik
  const periodButtons = document.querySelectorAll('[data-chart-period]');
  periodButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      periodButtons.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      const period = btn.getAttribute('data-chart-period');
      updateSalesChartPeriod(period);
    });
  });
}

function updateSalesChartPeriod(period) {
  if (!salesChartInstance || !chartDataSets[period]) return;

  salesChartInstance.data.labels = chartDataSets[period].labels;
  salesChartInstance.data.datasets[0].data = chartDataSets[period].data;
  salesChartInstance.update();
}
