// ==================== ANALYTICS PAGE - JavaScript ====================

(function () {
  'use strict';

  // ---- Color Palette (reuse from app.js) ----
  var clr = {
    teal: '#14b8a6', tealLight: '#5eead4',
    purple: '#a855f7', blue: '#3b82f6',
    pink: '#ec4899', orange: '#f97316',
    green: '#22c55e', red: '#ef4444'
  };

  function rgba(hex, alpha) {
    var r = parseInt(hex.slice(1, 3), 16);
    var g = parseInt(hex.slice(3, 5), 16);
    var b = parseInt(hex.slice(5, 7), 16);
    return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
  }

  // ---- Chart.js Global Config ----
  if (typeof Chart !== 'undefined') {
    Chart.defaults.color = '#94a3b8';
    Chart.defaults.borderColor = 'rgba(255,255,255,0.06)';
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.font.size = 12;
  }

  var tooltipStyle = {
    backgroundColor: '#1a1f35',
    borderColor: 'rgba(255,255,255,0.1)',
    borderWidth: 1,
    padding: 12,
    cornerRadius: 8,
    titleFont: { weight: '600' }
  };


  // ==================== SPARKLINE CHARTS ====================
  function createSparkline(canvasId, data, color) {
    var el = document.getElementById(canvasId);
    if (!el) return;
    var ctx = el.getContext('2d');
    var gradient = ctx.createLinearGradient(0, 0, 0, 40);
    gradient.addColorStop(0, rgba(color, 0.3));
    gradient.addColorStop(1, rgba(color, 0));

    new Chart(ctx, {
      type: 'line',
      data: {
        labels: data.map(function (_, i) { return i; }),
        datasets: [{
          data: data,
          borderColor: color,
          backgroundColor: gradient,
          fill: true,
          tension: 0.4,
          pointRadius: 0,
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { enabled: false } },
        scales: {
          x: { display: false },
          y: { display: false }
        }
      }
    });
  }

  createSparkline('sparkVisitors', [85, 92, 78, 105, 112, 98, 125, 118, 134, 128, 142, 128], clr.teal);
  createSparkline('sparkPageviews', [210, 245, 198, 278, 310, 265, 340, 295, 358, 325, 370, 342], clr.blue);
  createSparkline('sparkConversion', [3.2, 3.8, 3.5, 4.1, 4.5, 4.2, 4.9, 4.6, 5.1, 4.8, 5.3, 4.8], clr.green);
  createSparkline('sparkSession', [4.2, 3.8, 4.5, 3.6, 3.9, 3.4, 3.7, 3.5, 3.2, 3.6, 3.3, 3.4], clr.orange);


  // ==================== MAIN TREND CHART ====================
  var trendEl = document.getElementById('trendChart');
  var trendChart = null;

  if (trendEl) {
    var trendCtx = trendEl.getContext('2d');
    var trendGrad1 = trendCtx.createLinearGradient(0, 0, 0, 300);
    trendGrad1.addColorStop(0, rgba(clr.teal, 0.25));
    trendGrad1.addColorStop(1, rgba(clr.teal, 0));

    var trendGrad2 = trendCtx.createLinearGradient(0, 0, 0, 300);
    trendGrad2.addColorStop(0, rgba(clr.purple, 0.15));
    trendGrad2.addColorStop(1, rgba(clr.purple, 0));

    var trendData = {
      '7d': {
        labels: ['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'],
        revenue: [12800, 15200, 13500, 18900, 16400, 21200, 19800],
        visitors: [4200, 5100, 4600, 6200, 5800, 7400, 6900]
      },
      '30d': {
        labels: ['1. Hft', '2. Hft', '3. Hft', '4. Hft'],
        revenue: [68500, 74200, 82100, 91400],
        visitors: [28400, 31200, 35600, 38700]
      },
      '90d': {
        labels: ['Oca', 'Şub', 'Mar'],
        revenue: [245000, 312000, 286000],
        visitors: [95000, 112000, 108000]
      },
      '1y': {
        labels: ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'],
        revenue: [82000, 91000, 78000, 105000, 112000, 98000, 125000, 118000, 134000, 128000, 142000, 148000],
        visitors: [32000, 38000, 30000, 42000, 46000, 40000, 52000, 48000, 55000, 51000, 58000, 56000]
      }
    };

    trendChart = new Chart(trendCtx, {
      type: 'line',
      data: {
        labels: trendData['30d'].labels,
        datasets: [
          {
            label: 'Gelir ($)',
            data: trendData['30d'].revenue,
            borderColor: clr.teal,
            backgroundColor: trendGrad1,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: clr.teal,
            pointBorderColor: '#1a1f35',
            pointBorderWidth: 2,
            pointRadius: 5,
            pointHoverRadius: 7,
            borderWidth: 2.5,
            yAxisID: 'y'
          },
          {
            label: 'Ziyaretçi',
            data: trendData['30d'].visitors,
            borderColor: clr.purple,
            backgroundColor: trendGrad2,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: clr.purple,
            pointBorderColor: '#1a1f35',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
            borderWidth: 2,
            borderDash: [5, 5],
            yAxisID: 'y1'
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            position: 'top', align: 'end',
            labels: { usePointStyle: true, pointStyle: 'circle', padding: 20, font: { size: 12, weight: '500' } }
          },
          tooltip: Object.assign({}, tooltipStyle, {
            callbacks: {
              label: function (ctx) {
                if (ctx.datasetIndex === 0) return ' Gelir: $' + ctx.parsed.y.toLocaleString();
                return ' Ziyaretçi: ' + ctx.parsed.y.toLocaleString();
              }
            }
          })
        },
        scales: {
          x: { grid: { display: false }, ticks: { font: { weight: '500' } } },
          y: {
            type: 'linear', position: 'left',
            grid: { color: 'rgba(255,255,255,0.04)' },
            ticks: { callback: function (v) { return '$' + (v / 1000).toFixed(0) + 'K'; }, font: { weight: '500' } }
          },
          y1: {
            type: 'linear', position: 'right',
            grid: { drawOnChartArea: false },
            ticks: { callback: function (v) { return (v / 1000).toFixed(0) + 'K'; }, font: { weight: '500' } }
          }
        }
      }
    });
  }

  window.updateTrendChart = function (period, btn) {
    if (!trendChart || !trendData[period]) return;
    var data = trendData[period];
    trendChart.data.labels = data.labels;
    trendChart.data.datasets[0].data = data.revenue;
    trendChart.data.datasets[1].data = data.visitors;
    trendChart.update('active');

    // Toggle active button
    var parent = btn.parentElement;
    if (parent) {
      parent.querySelectorAll('.btn-glass').forEach(function (b) { b.classList.remove('active'); });
    }
    btn.classList.add('active');
  };


  // ==================== TRAFFIC DOUGHNUT ====================
  var trafficEl = document.getElementById('anlTrafficChart');
  if (trafficEl) {
    new Chart(trafficEl.getContext('2d'), {
      type: 'doughnut',
      data: {
        labels: ['Organik', 'Direkt', 'Sosyal', 'Referans', 'E-posta'],
        datasets: [{
          data: [38, 24, 19, 12, 7],
          backgroundColor: [clr.teal, clr.blue, clr.purple, clr.orange, clr.pink],
          borderColor: '#1a1f35',
          borderWidth: 3,
          hoverOffset: 10
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '75%',
        plugins: {
          legend: { display: false },
          tooltip: Object.assign({}, tooltipStyle, {
            callbacks: { label: function (ctx) { return ' ' + ctx.label + ': ' + ctx.parsed + '%'; } }
          })
        }
      }
    });
  }


  // ==================== DEVICE DOUGHNUT ====================
  var deviceEl = document.getElementById('deviceChart');
  if (deviceEl) {
    new Chart(deviceEl.getContext('2d'), {
      type: 'doughnut',
      data: {
        labels: ['Mobil', 'Masaüstü', 'Tablet'],
        datasets: [{
          data: [58, 32, 10],
          backgroundColor: [clr.blue, clr.purple, clr.orange],
          borderColor: '#1a1f35',
          borderWidth: 3,
          hoverOffset: 8
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%',
        plugins: {
          legend: { display: false },
          tooltip: Object.assign({}, tooltipStyle, {
            callbacks: { label: function (ctx) { return ' ' + ctx.label + ': ' + ctx.parsed + '%'; } }
          })
        }
      }
    });
  }


  // ==================== HOURLY BAR CHART ====================
  var hourlyEl = document.getElementById('hourlyChart');
  if (hourlyEl) {
    var hourLabels = [];
    var hourData = [];
    var hourColors = [];
    for (var h = 0; h < 24; h++) {
      hourLabels.push(h.toString().padStart(2, '0') + ':00');
      var val = Math.floor(Math.random() * 600) + 100;
      // Peak hours: 10-14 and 19-22
      if ((h >= 10 && h <= 14) || (h >= 19 && h <= 22)) val += 400;
      if (h >= 2 && h <= 6) val = Math.floor(val * 0.3);
      hourData.push(val);
      var intensity = val / 1000;
      hourColors.push(rgba(clr.teal, Math.max(0.3, Math.min(0.9, intensity))));
    }

    new Chart(hourlyEl.getContext('2d'), {
      type: 'bar',
      data: {
        labels: hourLabels,
        datasets: [{
          label: 'Ziyaretçi',
          data: hourData,
          backgroundColor: hourColors,
          borderRadius: 4,
          borderSkipped: false,
          barPercentage: 0.7
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: Object.assign({}, tooltipStyle, {
            callbacks: { label: function (ctx) { return ' ' + ctx.parsed.y + ' ziyaretçi'; } }
          })
        },
        scales: {
          x: {
            grid: { display: false },
            ticks: {
              maxTicksLimit: 12,
              font: { size: 10 }
            }
          },
          y: {
            grid: { color: 'rgba(255,255,255,0.04)' },
            ticks: { font: { size: 10 } }
          }
        }
      }
    });
  }


  // ==================== CATEGORY REVENUE (Horizontal Bar) ====================
  var catRevEl = document.getElementById('categoryRevenueChart');
  if (catRevEl) {
    new Chart(catRevEl.getContext('2d'), {
      type: 'bar',
      data: {
        labels: ['Elektronik', 'Giyim', 'Ev & Yaşam', 'Kozmetik', 'Spor', 'Aksesuar'],
        datasets: [{
          label: 'Gelir ($)',
          data: [89200, 34500, 28100, 18900, 15400, 12300],
          backgroundColor: [
            rgba(clr.teal, 0.8),
            rgba(clr.purple, 0.8),
            rgba(clr.blue, 0.8),
            rgba(clr.pink, 0.8),
            rgba(clr.green, 0.8),
            rgba(clr.orange, 0.8)
          ],
          borderRadius: 6,
          borderSkipped: false,
          barThickness: 24
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: Object.assign({}, tooltipStyle, {
            callbacks: { label: function (ctx) { return ' $' + ctx.parsed.x.toLocaleString(); } }
          })
        },
        scales: {
          x: {
            grid: { color: 'rgba(255,255,255,0.04)' },
            ticks: { callback: function (v) { return '$' + (v / 1000).toFixed(0) + 'K'; } }
          },
          y: {
            grid: { display: false },
            ticks: { font: { weight: '500' } }
          }
        }
      }
    });
  }


  // ==================== HEATMAP ====================
  function generateHeatmap() {
    var grid = document.getElementById('heatmapGrid');
    if (!grid) return;
    grid.innerHTML = '';

    var days = ['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'];

    // Header row
    var headerRow = document.createElement('div');
    headerRow.className = 'anl-heatmap-row anl-heatmap-header';
    headerRow.innerHTML = '<span class="anl-heatmap-label"></span>';
    for (var hh = 0; hh < 24; hh += 2) {
      var headCell = document.createElement('span');
      headCell.className = 'anl-heatmap-cell-label';
      headCell.textContent = hh.toString().padStart(2, '0');
      headerRow.appendChild(headCell);
    }
    grid.appendChild(headerRow);

    // Data rows
    days.forEach(function (day) {
      var row = document.createElement('div');
      row.className = 'anl-heatmap-row';
      row.innerHTML = '<span class="anl-heatmap-label">' + day + '</span>';

      for (var hour = 0; hour < 24; hour += 2) {
        var val = Math.random();
        // Peak hours boost
        if ((hour >= 10 && hour <= 14) || (hour >= 18 && hour <= 22)) val = Math.min(1, val + 0.4);
        if (hour >= 2 && hour <= 6) val *= 0.3;
        // Weekend boost for evenings
        if ((day === 'Cmt' || day === 'Paz') && hour >= 14) val = Math.min(1, val + 0.2);

        var cell = document.createElement('span');
        cell.className = 'anl-heatmap-cell';
        cell.title = day + ' ' + hour.toString().padStart(2, '0') + ':00 - ' + Math.floor(val * 100) + '% yoğunluk';
        cell.style.opacity = Math.max(0.15, val);
        row.appendChild(cell);
      }
      grid.appendChild(row);
    });
  }

  generateHeatmap();

  window.switchHourlyView = function (view, btn) {
    var barView = document.getElementById('hourlyBarView');
    var heatView = document.getElementById('hourlyHeatmapView');
    if (!barView || !heatView) return;

    if (view === 'bar') {
      barView.classList.remove('d-none');
      heatView.classList.add('d-none');
    } else {
      barView.classList.add('d-none');
      heatView.classList.remove('d-none');
    }

    var parent = btn.parentElement;
    if (parent) {
      parent.querySelectorAll('.btn-glass').forEach(function (b) { b.classList.remove('active'); });
    }
    btn.classList.add('active');
  };


  // ==================== DEVICE BAR ANIMATION ====================
  function animateDeviceBars() {
    document.querySelectorAll('.anl-device-fill').forEach(function (bar) {
      var width = bar.getAttribute('data-width');
      setTimeout(function () {
        bar.style.width = width + '%';
      }, 300);
    });
  }

  // Geo bar animation
  function animateGeoBars() {
    document.querySelectorAll('.anl-geo-fill').forEach(function (bar) {
      var width = bar.getAttribute('data-width');
      setTimeout(function () {
        bar.style.width = width + '%';
      }, 300);
    });
  }


  // ==================== GEO VIEW SWITCH ====================
  var cityData = [
    { flag: '🇹🇷', name: 'İstanbul', visitors: '42.180', pct: 32.8, trend: '+12.3%', positive: true },
    { flag: '🇹🇷', name: 'Ankara', visitors: '18.920', pct: 14.7, trend: '+5.8%', positive: true },
    { flag: '🇹🇷', name: 'İzmir', visitors: '12.440', pct: 9.7, trend: '+8.1%', positive: true },
    { flag: '🇩🇪', name: 'Berlin', visitors: '8.230', pct: 6.4, trend: '+18.2%', positive: true },
    { flag: '🇺🇸', name: 'New York', visitors: '6.180', pct: 4.8, trend: '+25.6%', positive: true },
    { flag: '🇹🇷', name: 'Bursa', visitors: '5.920', pct: 4.6, trend: '-2.1%', positive: false }
  ];

  window.switchGeoView = function (view, btn) {
    var tbody = document.getElementById('geoTableBody');
    if (!tbody) return;

    var parent = btn.parentElement;
    if (parent) {
      parent.querySelectorAll('.btn-glass').forEach(function (b) { b.classList.remove('active'); });
    }
    btn.classList.add('active');

    if (view === 'city') {
      tbody.innerHTML = '';
      cityData.forEach(function (item) {
        var row = document.createElement('tr');
        row.innerHTML =
          '<td><div class="anl-geo-cell"><span class="anl-flag">' + item.flag + '</span><span>' + item.name + '</span></div></td>' +
          '<td><strong>' + item.visitors + '</strong></td>' +
          '<td class="d-none d-sm-table-cell"><div class="anl-geo-bar-wrap"><div class="anl-geo-bar"><div class="anl-geo-fill" data-width="' + item.pct + '"></div></div><span>' + item.pct + '%</span></div></td>' +
          '<td><span class="anl-kpi-trend ' + (item.positive ? 'positive' : 'negative') + '"><i class="bi bi-arrow-' + (item.positive ? 'up' : 'down') + '-short"></i>' + item.trend.replace(/[+-]/, '') + '</span></td>';
        tbody.appendChild(row);
      });
      animateGeoBars();
    } else {
      // Reload page-level content (country view is default HTML)
      location.reload();
    }
  };


  // ==================== MODAL CHARTS ====================
  // Live Modal
  window.openLiveModal = function () {
    var modal = new bootstrap.Modal(document.getElementById('liveModal'));
    modal.show();
  };

  document.getElementById('liveModal')?.addEventListener('shown.bs.modal', function () {
    var canvas = document.getElementById('liveChart');
    if (!canvas || canvas._chartInit) return;
    canvas._chartInit = true;

    var liveLabels = [];
    var liveData = [];
    for (var m = 59; m >= 0; m--) {
      liveLabels.push(m + 'dk');
      liveData.push(Math.floor(Math.random() * 80) + 150);
    }

    var ctx = canvas.getContext('2d');
    var grad = ctx.createLinearGradient(0, 0, 0, 200);
    grad.addColorStop(0, rgba(clr.green, 0.3));
    grad.addColorStop(1, rgba(clr.green, 0));

    new Chart(ctx, {
      type: 'line',
      data: {
        labels: liveLabels,
        datasets: [{
          label: 'Aktif Kullanıcı',
          data: liveData,
          borderColor: clr.green,
          backgroundColor: grad,
          fill: true,
          tension: 0.3,
          pointRadius: 0,
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: tooltipStyle },
        scales: {
          x: { grid: { display: false }, ticks: { maxTicksLimit: 12, font: { size: 10 } } },
          y: { grid: { color: 'rgba(255,255,255,0.04)' } }
        }
      }
    });
  });

  // Traffic Detail Modal
  window.openTrafficDetailModal = function () {
    var modal = new bootstrap.Modal(document.getElementById('trafficDetailModal'));
    modal.show();
  };

  document.getElementById('trafficDetailModal')?.addEventListener('shown.bs.modal', function () {
    var canvas = document.getElementById('trafficDetailChart');
    if (!canvas || canvas._chartInit) return;
    canvas._chartInit = true;

    var ctx = canvas.getContext('2d');
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'],
        datasets: [
          { label: 'Organik', data: [32, 35, 33, 38, 40, 37, 42, 39, 44, 41, 45, 38], borderColor: clr.teal, tension: 0.4, borderWidth: 2 },
          { label: 'Direkt', data: [22, 24, 21, 25, 23, 26, 24, 27, 25, 28, 26, 24], borderColor: clr.blue, tension: 0.4, borderWidth: 2 },
          { label: 'Sosyal', data: [15, 18, 16, 20, 22, 19, 24, 21, 25, 23, 27, 19], borderColor: clr.purple, tension: 0.4, borderWidth: 2 },
          { label: 'Referans', data: [10, 11, 13, 12, 14, 11, 13, 12, 15, 14, 12, 12], borderColor: clr.orange, tension: 0.4, borderWidth: 2 },
          { label: 'E-posta', data: [5, 6, 7, 5, 8, 7, 9, 8, 10, 9, 8, 7], borderColor: clr.pink, tension: 0.4, borderWidth: 2 }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'top', labels: { usePointStyle: true, pointStyle: 'circle', padding: 16 } },
          tooltip: tooltipStyle
        },
        scales: {
          x: { grid: { display: false } },
          y: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { callback: function (v) { return v + 'K'; } } }
        }
      }
    });
  });

  // Top Products Modal
  window.openTopProductsModal = function () {
    var modal = new bootstrap.Modal(document.getElementById('topProductsModal'));
    modal.show();
  };

  document.getElementById('topProductsModal')?.addEventListener('shown.bs.modal', function () {
    var canvas = document.getElementById('topProductsChart');
    if (!canvas || canvas._chartInit) return;
    canvas._chartInit = true;

    new Chart(canvas.getContext('2d'), {
      type: 'bar',
      data: {
        labels: ['iPhone 16 Pro Max', 'Nike Air Max 2026', 'Galaxy S25 Ultra', 'MacBook Pro M4', 'Sony WH-1000XM6', 'Dyson V15', 'Adidas Ultraboost', 'Revitalift Serum'],
        datasets: [
          {
            label: 'Satış Adedi',
            data: [1247, 2340, 892, 3891, 534, 287, 678, 1456],
            backgroundColor: rgba(clr.teal, 0.7),
            borderRadius: 6,
            barThickness: 20,
            yAxisID: 'y'
          },
          {
            label: 'Gelir ($K)',
            data: [68.5, 10.1, 40.1, 350, 13.3, 5.4, 2.4, 1.3],
            backgroundColor: rgba(clr.purple, 0.7),
            borderRadius: 6,
            barThickness: 20,
            yAxisID: 'y1'
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'top', labels: { usePointStyle: true, pointStyle: 'circle', padding: 16 } },
          tooltip: tooltipStyle
        },
        scales: {
          x: { grid: { display: false }, ticks: { font: { size: 10 } } },
          y: { type: 'linear', position: 'left', grid: { color: 'rgba(255,255,255,0.04)' }, title: { display: true, text: 'Satış Adedi' } },
          y1: { type: 'linear', position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Gelir ($K)' } }
        }
      }
    });
  });


  // ==================== ACTIONS ====================
  window.changeDateRange = function (range) {
    if (range === 'custom') {
      showToast('Özel tarih aralığı seçici açılıyor...', 'info');
      return;
    }
    var rangeNames = { '7d': '7 gün', '30d': '30 gün', '90d': '90 gün', '6m': '6 ay', '1y': '1 yıl' };
    showToast('Son ' + (rangeNames[range] || range) + ' verileri yükleniyor...', 'info');
  };

  window.downloadReport = function (format) {
    var names = { pdf: 'PDF', excel: 'Excel', csv: 'CSV' };
    showToast((names[format] || format) + ' raporu indiriliyor...', 'success');
  };

  window.refreshActivities = function () {
    showToast('Aktiviteler yenileniyor...', 'info');
  };


  // ==================== TOAST ====================
  function showToast(message, type) {
    var existing = document.querySelector('.ca-toast');
    if (existing) existing.remove();

    var iconMap = { success: 'bi-check-circle-fill', error: 'bi-exclamation-circle-fill', info: 'bi-info-circle-fill' };
    var toast = document.createElement('div');
    toast.className = 'ca-toast ca-toast-' + type;
    toast.innerHTML = '<i class="bi ' + (iconMap[type] || iconMap.info) + '"></i> ' + message;
    document.body.appendChild(toast);

    requestAnimationFrame(function () { toast.classList.add('show'); });
    setTimeout(function () {
      toast.classList.remove('show');
      setTimeout(function () { toast.remove(); }, 300);
    }, 3000);
  }


  // ==================== COUNTER ANIMATION ====================
  function animateCounters() {
    var duration = 1500;
    document.querySelectorAll('.anl-kpi-value').forEach(function (el) {
      var originalText = el.textContent.trim();
      var target = parseInt(originalText.replace(/\./g, '').replace(/[^0-9]/g, ''), 10);
      if (isNaN(target) || target === 0) return;

      function formatNum(n) {
        return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
      }

      var prefix = originalText.match(/^[^0-9]*/)[0] || '';
      var suffix = originalText.match(/[^0-9]*$/)[0] || '';

      el.textContent = prefix + '0' + suffix;
      var startTime = null;

      function step(timestamp) {
        if (!startTime) startTime = timestamp;
        var progress = Math.min((timestamp - startTime) / duration, 1);
        var eased = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
        var current = Math.floor(eased * target);
        el.textContent = prefix + formatNum(current) + suffix;
        if (progress < 1) {
          requestAnimationFrame(step);
        } else {
          el.textContent = originalText;
        }
      }

      requestAnimationFrame(step);
    });

    // Animate mini values too
    document.querySelectorAll('.anl-mini-value').forEach(function (el) {
      var text = el.textContent.trim();
      var num = parseFloat(text.replace(/[^0-9.]/g, ''));
      if (isNaN(num)) return;

      var prefix = text.match(/^[^0-9]*/)[0] || '';
      var suffix = text.match(/[^0-9.]*$/)[0] || '';

      el.textContent = prefix + '0' + suffix;
      var startTime = null;

      function step(ts) {
        if (!startTime) startTime = ts;
        var p = Math.min((ts - startTime) / 1200, 1);
        var e = p === 1 ? 1 : 1 - Math.pow(2, -10 * p);
        var cur = e * num;
        var formatted = num >= 100 ? Math.floor(cur).toLocaleString('tr-TR') : cur.toFixed(1);
        el.textContent = prefix + formatted + suffix;
        if (p < 1) requestAnimationFrame(step);
        else el.textContent = text;
      }
      requestAnimationFrame(step);
    });
  }


  // ==================== INIT ====================
  function init() {
    animateCounters();
    animateDeviceBars();
    animateGeoBars();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
