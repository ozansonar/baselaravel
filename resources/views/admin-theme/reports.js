(function () {
  'use strict';

  /* ==================== COLOR PALETTE ==================== */
  var clr = {
    teal:   '#14b8a6', tealLight: '#5eead4',
    blue:   '#3b82f6', purple: '#a855f7',
    pink:   '#ec4899', orange: '#f97316',
    green:  '#22c55e', red:    '#ef4444'
  };

  function rgba(hex, alpha) {
    var r = parseInt(hex.slice(1, 3), 16);
    var g = parseInt(hex.slice(3, 5), 16);
    var b = parseInt(hex.slice(5, 7), 16);
    return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
  }

  /* ==================== CHART.JS GLOBAL CONFIG ==================== */
  Chart.defaults.color          = '#94a3b8';
  Chart.defaults.borderColor    = 'rgba(255,255,255,0.06)';
  Chart.defaults.font.family    = "'Inter', sans-serif";
  Chart.defaults.font.size      = 12;
  Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(15,20,40,0.92)';
  Chart.defaults.plugins.tooltip.borderColor      = 'rgba(20,184,166,0.3)';
  Chart.defaults.plugins.tooltip.borderWidth      = 1;
  Chart.defaults.plugins.tooltip.padding          = 12;
  Chart.defaults.plugins.tooltip.titleColor       = '#f1f5f9';
  Chart.defaults.plugins.tooltip.bodyColor        = '#94a3b8';
  Chart.defaults.plugins.tooltip.cornerRadius     = 10;

  /* ==================== STATE ==================== */
  var trendChart     = null;
  var typeChart      = null;
  var previewChart   = null;
  var currentReport  = null; // type being acted on
  var trendView      = 'generated';

  /* ==================== REPORT META ==================== */
  var reportMeta = {
    sales:     { label: 'Satış Raporu',       color: clr.teal,   icon: 'bi-graph-up-arrow'  },
    customers: { label: 'Müşteri Analizi',    color: clr.blue,   icon: 'bi-people'           },
    products:  { label: 'Ürün Performansı',   color: clr.purple, icon: 'bi-box-seam'         },
    stock:     { label: 'Stok Durumu',        color: clr.orange, icon: 'bi-archive'           },
    financial: { label: 'Finansal Özet',      color: clr.green,  icon: 'bi-cash-stack'       },
    campaigns: { label: 'Kampanya Raporu',    color: clr.pink,   icon: 'bi-megaphone'        }
  };

  /* ==================== PREVIEW DATA ==================== */
  var previewData = {
    sales: {
      title: 'Satış Raporu Önizleme',
      totalRecords: '8.432', totalAmount: '₺2.84M', dateRange: 'Şub 2026', type: 'Satış',
      chartLabels: ['1 Şub','5 Şub','10 Şub','15 Şub','20 Şub','22 Şub'],
      chartData:   [84200, 112400, 98700, 143600, 128900, 155200],
      tableHeaders: ['Sipariş No', 'Müşteri', 'Ürün', 'Tutar', 'Durum'],
      tableRows: [
        ['#ORD-2847', 'Ahmet Yılmaz', 'iPhone 16 Pro Max', '54.999 TL', 'Teslim Edildi'],
        ['#ORD-2846', 'Elif Kaya', 'Galaxy S25 Ultra', '69.998 TL', 'Bekleyen'],
        ['#ORD-2845', 'Mehmet Demir', 'Nike Air Max 2026', '4.299 TL', 'Kargoda'],
        ['#ORD-2844', 'Zeynep Arslan', 'MacBook Pro M4 Max', '89.999 TL', 'Hazırlanıyor'],
        ['#ORD-2843', 'Burak Can', 'Dyson V15', '18.999 TL', 'İptal'],
        ['#ORD-2842', 'Selin Öztürk', 'Adidas Ultraboost', '3.599 TL', 'Kargoda'],
        ['#ORD-2841', 'Deniz Yıldız', 'Revitalift Seti', '2.697 TL', 'Teslim Edildi'],
        ['#ORD-2840', 'Can Akın', 'Sony WH-1000XM6', '24.999 TL', 'Bekleyen'],
        ['#ORD-2839', 'Merve Kılıç', 'iPad Pro M4', '42.999 TL', 'Teslim Edildi'],
        ['#ORD-2838', 'Ali Çelik', 'Samsung 4K TV', '31.999 TL', 'Hazırlanıyor']
      ]
    },
    customers: {
      title: 'Müşteri Analizi Önizleme',
      totalRecords: '12.847', totalAmount: '₺892 LTV', dateRange: 'Q1 2026', type: 'Müşteri',
      chartLabels: ['1 Şub','5 Şub','10 Şub','15 Şub','20 Şub','22 Şub'],
      chartData:   [320, 415, 380, 510, 460, 540],
      tableHeaders: ['Müşteri', 'E-posta', 'Kayıt Tarihi', 'LTV', 'Segment'],
      tableRows: [
        ['Ahmet Yılmaz', 'ahmet@email.com', '12.01.2024', '₺125.400', 'VIP'],
        ['Elif Kaya', 'elif@email.com', '03.03.2023', '₺88.200', 'Premium'],
        ['Mehmet Demir', 'mehmet@email.com', '25.06.2023', '₺42.100', 'Regular'],
        ['Zeynep Arslan', 'zeynep@email.com', '14.09.2024', '₺212.800', 'VIP'],
        ['Burak Can', 'burak@email.com', '07.11.2022', '₺15.300', 'Regular'],
        ['Selin Öztürk', 'selin@email.com', '22.04.2024', '₺31.900', 'Regular'],
        ['Deniz Yıldız', 'deniz@email.com', '01.08.2023', '₺67.500', 'Premium'],
        ['Can Akın', 'can@email.com', '30.10.2024', '₺24.800', 'Regular'],
        ['Merve Kılıç', 'merve@email.com', '15.02.2023', '₺98.600', 'Premium'],
        ['Ali Çelik', 'ali@email.com', '20.07.2024', '₺44.200', 'Regular']
      ]
    },
    products: {
      title: 'Ürün Performansı Önizleme',
      totalRecords: '1.248', totalAmount: '%38.4 Kar', dateRange: 'Şub 2026', type: 'Ürün',
      chartLabels: ['iPhone', 'MacBook', 'Samsung', 'Dyson', 'Nike', 'Adidas'],
      chartData:   [412, 287, 356, 198, 522, 443],
      tableHeaders: ['Ürün', 'Kategori', 'Satılan Adet', 'Gelir', 'Kar Marjı'],
      tableRows: [
        ['iPhone 16 Pro Max', 'Elektronik', '412', '₺22.7M', '%28.3'],
        ['MacBook Pro M4', 'Elektronik', '287', '₺25.8M', '%22.1'],
        ['Samsung Galaxy S25', 'Elektronik', '356', '₺14.2M', '%31.7'],
        ['Dyson V15 Detect', 'Ev Aletleri', '198', '₺3.76M', '%44.2'],
        ['Nike Air Max 2026', 'Spor', '522', '₺2.24M', '%52.8'],
        ['Adidas Ultraboost 24', 'Spor', '443', '₺1.59M', '%55.1'],
        ['Sony WH-1000XM6', 'Elektronik', '319', '₺7.97M', '%33.6'],
        ['L\'Oréal Revitalift', 'Kozmetik', '287', '₺258K', '%68.4'],
        ['iPad Pro M4', 'Elektronik', '156', '₺6.71M', '%24.9'],
        ['Fitbit Charge 6', 'Spor', '234', '₺936K', '%61.2']
      ]
    },
    stock: {
      title: 'Stok Durumu Önizleme',
      totalRecords: '3.847', totalAmount: '47 Kritik', dateRange: '22 Şub 2026', type: 'Stok',
      chartLabels: ['Elektronik', 'Giyim', 'Spor', 'Kozmetik', 'Ev', 'Diğer'],
      chartData:   [8240, 12800, 5630, 3920, 4180, 2100],
      tableHeaders: ['Ürün', 'SKU', 'Mevcut Stok', 'Kritik Seviye', 'Durum'],
      tableRows: [
        ['iPhone 16 Pro Max 256GB', 'APL-IPH16-256', '42', '50', 'Kritik'],
        ['Samsung Galaxy S25 512GB', 'SAM-S25-512', '128', '30', 'Normal'],
        ['MacBook Pro M4 1TB', 'APL-MBP-M4-1T', '15', '20', 'Düşük'],
        ['Nike Air Max 2026 42', 'NIK-AM26-42', '204', '50', 'Normal'],
        ['Dyson V15 Detect', 'DYS-V15-DET', '0', '10', 'Tükendi'],
        ['Sony WH-1000XM6 Siyah', 'SNY-WH6-BLK', '89', '25', 'Normal'],
        ['Adidas Ultraboost 38', 'ADI-UB24-38', '7', '15', 'Kritik'],
        ['L\'Oréal Revitalift Serum', 'LOR-RVL-SRM', '312', '50', 'Normal'],
        ['Fitbit Charge 6', 'FIT-CH6-BLK', '3', '20', 'Kritik'],
        ['iPad Pro M4 256GB', 'APL-IPD-M4-256', '31', '25', 'Normal']
      ]
    },
    financial: {
      title: 'Finansal Özet Önizleme',
      totalRecords: '12 ay', totalAmount: '₺2.84M Net', dateRange: '2025 Yıllık', type: 'Finansal',
      chartLabels: ['Oca','Şub','Mar','Nis','May','Haz','Tem','Ağu','Eyl','Eki','Kas','Ara'],
      chartData:   [148000, 162000, 198000, 187000, 224000, 241000, 219000, 256000, 234000, 271000, 298000, 342000],
      tableHeaders: ['Ay', 'Gelir', 'Gider', 'Brüt Kar', 'KDV'],
      tableRows: [
        ['Ocak 2025', '₺148.400', '₺89.200', '₺59.200', '₺26.712'],
        ['Şubat 2025', '₺162.800', '₺97.100', '₺65.700', '₺29.304'],
        ['Mart 2025', '₺198.200', '₺118.900', '₺79.300', '₺35.676'],
        ['Nisan 2025', '₺187.500', '₺112.500', '₺75.000', '₺33.750'],
        ['Mayıs 2025', '₺224.100', '₺134.500', '₺89.600', '₺40.338'],
        ['Haziran 2025', '₺241.300', '₺144.800', '₺96.500', '₺43.434'],
        ['Temmuz 2025', '₺219.600', '₺131.800', '₺87.800', '₺39.528'],
        ['Ağustos 2025', '₺256.400', '₺153.800', '₺102.600', '₺46.152'],
        ['Eylül 2025', '₺234.200', '₺140.500', '₺93.700', '₺42.156'],
        ['Ekim 2025', '₺271.800', '₺163.100', '₺108.700', '₺48.924']
      ]
    },
    campaigns: {
      title: 'Kampanya Raporu Önizleme',
      totalRecords: '24 kampanya', totalAmount: '%312 ROI', dateRange: 'Q4 2025', type: 'Kampanya',
      chartLabels: ['Kış İnd.', 'YılBaşı', 'Sevgililer', 'Kara Cuma', 'Bahar', 'Atatürk'],
      chartData:   [42, 78, 55, 94, 38, 61],
      tableHeaders: ['Kampanya', 'Başlangıç', 'Bitiş', 'Bütçe', 'ROI'],
      tableRows: [
        ['Kış İndirimi 2025', '01.12.2025', '31.12.2025', '₺50.000', '%284'],
        ['Yılbaşı Fırsatları', '27.12.2025', '02.01.2026', '₺75.000', '%412'],
        ['Sevgililer Günü', '08.02.2026', '14.02.2026', '₺35.000', '%198'],
        ['Kara Cuma 2025', '29.11.2025', '30.11.2025', '₺120.000', '%621'],
        ['Bahar Koleksiyonu', '01.03.2026', '31.03.2026', '₺45.000', '%167'],
        ['10 Kasım Kampanyası', '09.11.2025', '10.11.2025', '₺25.000', '%189'],
        ['Flash Sale Salı', '18.02.2026', '18.02.2026', '₺15.000', '%341'],
        ['Back to School', '01.09.2025', '30.09.2025', '₺60.000', '%223'],
        ['Anneler Günü', '10.05.2026', '12.05.2026', '₺40.000', '-'],
        ['Ramazan Özel', '01.03.2026', '30.03.2026', '₺55.000', '-']
      ]
    }
  };

  /* ==================== INIT ==================== */
  document.addEventListener('DOMContentLoaded', function () {
    animateCounters();
    initTrendChart();
    initTypeChart();
  });

  /* ==================== COUNTER ANIMATION ==================== */
  function animateCounters() {
    document.querySelectorAll('.usr-stat-value[data-target]').forEach(function (el) {
      var target   = parseInt(el.getAttribute('data-target'), 10);
      var duration = 1400;
      var startTime = null;
      function step(ts) {
        if (!startTime) startTime = ts;
        var progress = Math.min((ts - startTime) / duration, 1);
        var eased    = 1 - Math.pow(1 - progress, 3);
        el.textContent = Math.floor(eased * target).toLocaleString('tr-TR');
        if (progress < 1) requestAnimationFrame(step);
        else el.textContent = target.toLocaleString('tr-TR');
      }
      requestAnimationFrame(step);
    });
  }

  /* ==================== MONTHLY TREND CHART ==================== */
  function initTrendChart() {
    var ctx = document.getElementById('reportTrendChart');
    if (!ctx) return;

    var labels     = ['Mar','Nis','May','Haz','Tem','Ağu','Eyl','Eki','Kas','Ara','Oca','Şub'];
    var generated  = [18, 22, 19, 27, 24, 31, 28, 35, 42, 48, 38, 52];
    var downloaded = [124, 178, 156, 213, 197, 242, 228, 289, 341, 398, 312, 417];

    trendChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [
          {
            label: 'Oluşturulan',
            data: generated,
            backgroundColor: rgba(clr.teal, 0.75),
            borderColor: clr.teal,
            borderWidth: 1,
            borderRadius: 6,
            borderSkipped: false
          },
          {
            label: 'İndirilen',
            data: downloaded,
            backgroundColor: rgba(clr.blue, 0.6),
            borderColor: clr.blue,
            borderWidth: 1,
            borderRadius: 6,
            borderSkipped: false,
            hidden: true
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            display: true,
            position: 'top',
            align: 'end',
            labels: { boxWidth: 12, boxHeight: 12, borderRadius: 4, useBorderRadius: true }
          }
        },
        scales: {
          x: { grid: { display: false } },
          y: {
            beginAtZero: true,
            grid: { color: 'rgba(255,255,255,0.04)' },
            ticks: { maxTicksLimit: 6 }
          }
        }
      }
    });
  }

  window.switchTrendView = function (view, btn) {
    trendView = view;
    document.querySelectorAll('.rpr-chart-tab').forEach(function (t) { t.classList.remove('active'); });
    btn.classList.add('active');

    if (!trendChart) return;
    if (view === 'generated') {
      trendChart.data.datasets[0].hidden = false;
      trendChart.data.datasets[1].hidden = true;
    } else {
      trendChart.data.datasets[0].hidden = true;
      trendChart.data.datasets[1].hidden = false;
    }
    trendChart.update();
  };

  /* ==================== DOUGHNUT CHART ==================== */
  function initTypeChart() {
    var ctx = document.getElementById('reportTypeChart');
    if (!ctx) return;

    var labels = ['Satış', 'Müşteri', 'Ürün', 'Stok', 'Finansal', 'Kampanya'];
    var values = [38, 22, 18, 12, 6, 4];
    var colors = [clr.teal, clr.blue, clr.purple, clr.orange, clr.green, clr.pink];

    typeChart = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: labels,
        datasets: [{
          data: values,
          backgroundColor: colors.map(function (c) { return rgba(c, 0.8); }),
          borderColor: colors,
          borderWidth: 2,
          hoverOffset: 8
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        cutout: '65%',
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function (ctx) { return ' ' + ctx.label + ': %' + ctx.parsed; }
            }
          }
        }
      }
    });

    // Build custom legend
    var legend = document.getElementById('reportTypeLegend');
    if (legend) {
      var html = '';
      labels.forEach(function (label, idx) {
        html += '<div class="rpr-legend-item">';
        html += '<span class="rpr-legend-dot" style="background:' + colors[idx] + '"></span>';
        html += '<span class="rpr-legend-label">' + label + '</span>';
        html += '<span class="rpr-legend-pct">%' + values[idx] + '</span>';
        html += '</div>';
      });
      legend.innerHTML = html;
    }
  }

  /* ==================== GENERATE REPORT ==================== */
  window.generateReport = function (type) {
    var meta = reportMeta[type];
    if (!meta) return;

    var btn = event.target.closest('.rpr-generate-btn');
    if (btn) {
      var orig = btn.innerHTML;
      btn.innerHTML = '<span class="rpr-spin"><i class="bi bi-arrow-repeat"></i></span> Oluşturuluyor...';
      btn.disabled  = true;
      setTimeout(function () {
        btn.innerHTML = orig;
        btn.disabled  = false;
        showToast(meta.label + ' başarıyla oluşturuldu ve indirildi!', 'success');
      }, 2000);
    }
  };

  /* ==================== PREVIEW MODAL ==================== */
  window.openPreviewModal = function (type) {
    var data = previewData[type];
    var meta = reportMeta[type];
    if (!data || !meta) return;
    currentReport = type;

    document.getElementById('previewModalTitle').textContent   = data.title;
    document.getElementById('previewTotalRecords').textContent = data.totalRecords;
    document.getElementById('previewTotalAmount').textContent  = data.totalAmount;
    document.getElementById('previewDateRange').textContent    = data.dateRange;
    document.getElementById('previewType').textContent         = data.type;

    buildPreviewTable(data);

    var modal = new bootstrap.Modal(document.getElementById('previewModal'));
    modal.show();

    document.getElementById('previewModal').addEventListener('shown.bs.modal', function onShown() {
      buildPreviewChart(data, meta);
      document.getElementById('previewModal').removeEventListener('shown.bs.modal', onShown);
    });
  };

  function buildPreviewChart(data, meta) {
    var ctx = document.getElementById('previewChart');
    if (!ctx) return;
    if (previewChart) { previewChart.destroy(); previewChart = null; }

    var isBar    = (currentReport === 'products' || currentReport === 'campaigns');
    var type     = isBar ? 'bar' : 'line';
    var gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 200);
    gradient.addColorStop(0, rgba(meta.color, 0.4));
    gradient.addColorStop(1, rgba(meta.color, 0.0));

    previewChart = new Chart(ctx, {
      type: type,
      data: {
        labels: data.chartLabels,
        datasets: [{
          label: meta.label,
          data:  data.chartData,
          borderColor:     meta.color,
          backgroundColor: isBar ? rgba(meta.color, 0.65) : gradient,
          borderWidth:     2,
          tension:         0.4,
          fill:            !isBar,
          pointRadius:     4,
          pointHoverRadius: 6,
          pointBackgroundColor: meta.color,
          borderRadius:     isBar ? 6 : undefined,
          borderSkipped:    false
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { display: false } },
          y: {
            beginAtZero: true,
            grid: { color: 'rgba(255,255,255,0.04)' },
            ticks: {
              maxTicksLimit: 5,
              callback: function (val) {
                if (val >= 1000000) return (val / 1000000).toFixed(1) + 'M';
                if (val >= 1000)    return (val / 1000).toFixed(0) + 'K';
                return val;
              }
            }
          }
        }
      }
    });
  }

  function buildPreviewTable(data) {
    var head = document.getElementById('previewTableHead');
    var body = document.getElementById('previewTableBody');
    if (!head || !body) return;

    var headHtml = '<tr>';
    data.tableHeaders.forEach(function (h) { headHtml += '<th>' + h + '</th>'; });
    headHtml += '</tr>';
    head.innerHTML = headHtml;

    var bodyHtml = '';
    data.tableRows.forEach(function (row) {
      bodyHtml += '<tr>';
      row.forEach(function (cell) { bodyHtml += '<td>' + cell + '</td>'; });
      bodyHtml += '</tr>';
    });
    body.innerHTML = bodyHtml;
  }

  window.downloadPreviewReport = function (format) {
    format = format || 'pdf';
    var meta = reportMeta[currentReport];
    var ext  = format === 'excel' ? 'xlsx' : format;
    showToast((meta ? meta.label : 'Rapor') + ' ' + ext.toUpperCase() + ' olarak indiriliyor...', 'info');
  };

  /* ==================== CUSTOM REPORT MODAL ==================== */
  window.openCustomReportModal = function () {
    var modal = new bootstrap.Modal(document.getElementById('customReportModal'));
    modal.show();
  };

  window.previewCustomReport = function () {
    var type = document.getElementById('customReportType').value;
    if (!type) { showToast('Lütfen rapor türü seçin', 'warning'); return; }
    bootstrap.Modal.getInstance(document.getElementById('customReportModal')).hide();
    openPreviewModal(type);
  };

  window.buildCustomReport = function () {
    var type = document.getElementById('customReportType').value;
    if (!type) { showToast('Lütfen rapor türü seçin', 'warning'); return; }
    var format  = document.querySelector('input[name="customFormat"]:checked');
    var fmt     = format ? format.value.toUpperCase() : 'PDF';
    var meta    = reportMeta[type];
    bootstrap.Modal.getInstance(document.getElementById('customReportModal')).hide();
    showToast((meta ? meta.label : 'Rapor') + ' ' + fmt + ' formatında hazırlanıyor...', 'success');
  };

  /* ==================== SCHEDULE MODAL ==================== */
  window.openScheduleModal = function (type) {
    if (type) {
      var meta = reportMeta[type];
      var sel  = document.getElementById('schedReportType');
      if (sel && meta) sel.value = type;
    }
    var modal = new bootstrap.Modal(document.getElementById('scheduleModal'));
    modal.show();
  };

  window.updateScheduleDay = function () {
    var freq = document.getElementById('schedFrequency').value;
    var wrap = document.getElementById('schedDayWrap');
    if (wrap) {
      wrap.style.display = freq === 'daily' ? 'none' : '';
    }
  };

  window.saveSchedule = function () {
    var type  = document.getElementById('schedReportType').value;
    var email = document.getElementById('schedEmail').value.trim();
    if (!type)  { showToast('Lütfen rapor türü seçin', 'warning'); return; }
    if (!email) { showToast('Lütfen e-posta adresini girin', 'warning'); return; }

    var meta = reportMeta[type];
    bootstrap.Modal.getInstance(document.getElementById('scheduleModal')).hide();
    showToast((meta ? meta.label : 'Rapor') + ' planlaması kaydedildi!', 'success');
  };

  window.editSchedule = function (id) {
    var modal = new bootstrap.Modal(document.getElementById('scheduleModal'));
    modal.show();
  };

  window.runScheduleNow = function (id) {
    showToast('Rapor şimdi çalıştırılıyor, hazır olunca e-posta ile gönderilecek.', 'info');
  };

  window.toggleSchedule = function (id, active) {
    var rows  = document.querySelectorAll('#scheduledTableBody tr');
    var row   = rows[id - 1];
    if (!row) return;
    var badge = row.querySelector('.rpr-status-badge');
    if (!badge) return;
    if (active) {
      badge.className   = 'rpr-status-badge active';
      badge.textContent = 'Aktif';
      showToast('Rapor planlaması aktifleştirildi', 'success');
    } else {
      badge.className   = 'rpr-status-badge inactive';
      badge.textContent = 'Pasif';
      showToast('Rapor planlaması devre dışı bırakıldı', 'warning');
    }
  };

  var pendingDeleteId = null;

  window.deleteSchedule = function (id) {
    pendingDeleteId = id;
    var rows = document.querySelectorAll('#scheduledTableBody tr');
    var row  = rows[id - 1];
    var title = row ? (row.querySelector('.rpr-sched-title') || {}).textContent || '' : '';
    var nameEl = document.getElementById('deleteScheduleName');
    if (nameEl) nameEl.textContent = title;
    var modal = new bootstrap.Modal(document.getElementById('deleteScheduleModal'));
    modal.show();
  };

  window.confirmDeleteSchedule = function () {
    if (pendingDeleteId === null) return;
    var rows = document.querySelectorAll('#scheduledTableBody tr');
    var row  = rows[pendingDeleteId - 1];
    if (row) {
      row.style.opacity = '0';
      row.style.transition = 'opacity 0.35s';
      setTimeout(function () { row.remove(); }, 350);
      showToast('Rapor planlaması silindi', 'danger');
    }
    pendingDeleteId = null;
  };

  /* ==================== DOWNLOAD SEARCH ==================== */
  window.filterDownloads = function () {
    var q     = (document.getElementById('downloadSearch').value || '').toLowerCase().trim();
    var items = document.querySelectorAll('.rpr-download-item');
    items.forEach(function (item) {
      var name = (item.getAttribute('data-name') || '').toLowerCase();
      var text = item.textContent.toLowerCase();
      item.style.display = (!q || name.indexOf(q) !== -1 || text.indexOf(q) !== -1) ? '' : 'none';
    });
  };

  window.downloadFile = function (filename) {
    showToast(filename + ' indiriliyor...', 'info');
  };

  /* ==================== GLOBAL DATE RANGE ==================== */
  window.changeGlobalRange = function (val) {
    if (val === 'custom') {
      showToast('Özel tarih aralığı seçici yakında aktif olacak', 'info');
      return;
    }
    var labels = { week: 'bu haftaya', month: 'bu aya', quarter: 'bu çeyreğe', year: 'bu yıla' };
    showToast('Raporlar ' + (labels[val] || val) + ' göre güncellendi', 'info');
  };

  /* ==================== TOAST ==================== */
  function showToast(message, type) {
    type = type || 'success';
    var existing = document.querySelector('.ca-toast');
    if (existing) existing.remove();

    var icons  = { success: 'bi-check-circle-fill', danger: 'bi-x-circle-fill', warning: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
    var colors = { success: 'text-neon-green', danger: 'text-neon-red', warning: 'text-neon-orange', info: 'text-neon-blue' };

    var toast = document.createElement('div');
    toast.className = 'ca-toast ca-toast-' + type;
    toast.innerHTML =
      '<i class="bi ' + (icons[type] || icons.info) + ' ' + (colors[type] || '') + '"></i>' +
      '<span>' + message + '</span>' +
      '<button onclick="this.parentElement.remove()"><i class="bi bi-x"></i></button>';

    document.body.appendChild(toast);
    requestAnimationFrame(function () { toast.classList.add('show'); });
    setTimeout(function () {
      toast.classList.remove('show');
      setTimeout(function () { toast.remove(); }, 350);
    }, 3500);
  }

  window.showReportsToast = showToast;

})();
