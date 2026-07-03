(function () {
  'use strict';

  /* ==================== STATE ==================== */
  var currentStatus = 'all';
  var sortColumn    = '';
  var sortDir       = 'asc';
  var currentOrder  = null; // id of the order being acted on

  /* ==================== ORDER DATA ==================== */
  var orderData = {
    1: {
      id: '#ORD-2847', date: '22 Şubat 2026 - 14:32',
      status: 'delivered', statusLabel: 'Teslim Edildi',
      customer: { name: 'Ahmet Yılmaz', email: 'ahmet@email.com', phone: '+90 532 123 4567' },
      address: 'Atatürk Mah. Cumhuriyet Cad. No:42/A Daire:5, Kadıköy, İstanbul, 34710',
      shipping: 'Yurtiçi Kargo', trackingNo: 'YK-789456123',
      products: [{ name: 'iPhone 16 Pro Max', variant: '256GB, Siyah Titanyum', price: '54.999 TL', qty: 1, img: 'https://picsum.photos/seed/prod1/36/36' }],
      subtotal: '54.999 TL', shippingCost: 'Ücretsiz', tax: '9.166 TL', total: '54.999 TL',
      payment: 'Kredi Kartı', paymentStatus: 'Ödendi', transactionId: 'TXN-8847291034',
      timeline: [
        { title: 'Sipariş Alındı',     time: '22 Şub, 14:32', done: true },
        { title: 'Ödeme Onaylandı',    time: '22 Şub, 14:35', done: true },
        { title: 'Hazırlanıyor',       time: '22 Şub, 15:10', done: true },
        { title: 'Kargoya Verildi',    time: '23 Şub, 09:20', done: true },
        { title: 'Teslim Edildi',      time: '24 Şub, 11:45', done: true }
      ]
    },
    2: {
      id: '#ORD-2846', date: '21 Şubat 2026 - 10:15',
      status: 'pending', statusLabel: 'Bekleyen',
      customer: { name: 'Elif Kaya', email: 'elif@email.com', phone: '+90 542 234 5678' },
      address: 'Bağcılar Mah. İnönü Cad. No:7, Bağcılar, İstanbul, 34200',
      shipping: 'MNG Kargo', trackingNo: '-',
      products: [
        { name: 'Samsung Galaxy S25 Ultra', variant: '512GB, Titanyum Siyah', price: '44.999 TL', qty: 1, img: 'https://picsum.photos/seed/prod2/36/36' },
        { name: 'Sony WH-1000XM6',          variant: 'Siyah',                 price: '24.999 TL', qty: 1, img: 'https://picsum.photos/seed/prod4/36/36' }
      ],
      subtotal: '69.998 TL', shippingCost: '29 TL', tax: '11.666 TL', total: '70.027 TL',
      payment: 'Havale/EFT', paymentStatus: 'Bekleniyor', transactionId: '-',
      timeline: [
        { title: 'Sipariş Alındı',  time: '21 Şub, 10:15', done: true },
        { title: 'Ödeme Onaylandı', time: 'Bekleniyor...',  done: false },
        { title: 'Hazırlanıyor',    time: '',                done: false },
        { title: 'Kargoya Verildi', time: '',                done: false },
        { title: 'Teslim Edildi',   time: '',                done: false }
      ]
    },
    3: {
      id: '#ORD-2845', date: '20 Şubat 2026 - 08:55',
      status: 'shipped', statusLabel: 'Kargoda',
      customer: { name: 'Mehmet Demir', email: 'mehmet@email.com', phone: '+90 555 345 6789' },
      address: 'Çankaya Mah. Atatürk Blv. No:22, Çankaya, Ankara, 06100',
      shipping: 'Yurtiçi Kargo', trackingNo: 'YK-789456456',
      products: [{ name: 'Nike Air Max 2026', variant: '42, Beyaz/Siyah', price: '4.299 TL', qty: 1, img: 'https://picsum.photos/seed/prod3/36/36' }],
      subtotal: '4.299 TL', shippingCost: '29 TL', tax: '716 TL', total: '4.328 TL',
      payment: 'Kredi Kartı', paymentStatus: 'Ödendi', transactionId: 'TXN-8847291055',
      timeline: [
        { title: 'Sipariş Alındı',  time: '20 Şub, 08:55', done: true },
        { title: 'Ödeme Onaylandı', time: '20 Şub, 09:02', done: true },
        { title: 'Hazırlanıyor',    time: '20 Şub, 11:30', done: true },
        { title: 'Kargoya Verildi', time: '21 Şub, 10:00', done: true },
        { title: 'Teslim Edildi',   time: 'Bekleniyor...',  done: false }
      ]
    },
    4: {
      id: '#ORD-2844', date: '19 Şubat 2026 - 16:40',
      status: 'processing', statusLabel: 'Hazırlanıyor',
      customer: { name: 'Zeynep Arslan', email: 'zeynep@email.com', phone: '+90 533 456 7890' },
      address: 'Nilüfer Mah. Doğukent Blv. No:55 Daire:3, Nilüfer, Bursa, 16140',
      shipping: 'Aras Kargo', trackingNo: '-',
      products: [{ name: 'MacBook Pro M4 Max', variant: '16", 48GB RAM, 1TB', price: '89.999 TL', qty: 1, img: 'https://picsum.photos/seed/prod6/36/36' }],
      subtotal: '89.999 TL', shippingCost: 'Ücretsiz', tax: '15.000 TL', total: '89.999 TL',
      payment: 'Dijital Cüzdan', paymentStatus: 'Ödendi', transactionId: 'TXN-8847291072',
      timeline: [
        { title: 'Sipariş Alındı',  time: '19 Şub, 16:40', done: true },
        { title: 'Ödeme Onaylandı', time: '19 Şub, 16:41', done: true },
        { title: 'Hazırlanıyor',    time: '20 Şub, 09:00', done: true },
        { title: 'Kargoya Verildi', time: 'Bekleniyor...',  done: false },
        { title: 'Teslim Edildi',   time: '',                done: false }
      ]
    },
    5: {
      id: '#ORD-2843', date: '18 Şubat 2026 - 12:20',
      status: 'cancelled', statusLabel: 'İptal',
      customer: { name: 'Burak Can', email: 'burak@email.com', phone: '+90 544 567 8901' },
      address: 'Konak Mah. Anafartalar Cad. No:33, Konak, İzmir, 35250',
      shipping: 'İptal edildi', trackingNo: '-',
      products: [{ name: 'Dyson V15 Detect', variant: 'Sarı', price: '18.999 TL', qty: 1, img: 'https://picsum.photos/seed/prod8/36/36' }],
      subtotal: '18.999 TL', shippingCost: 'İptal', tax: '-', total: '18.999 TL',
      payment: 'Kredi Kartı', paymentStatus: 'İade Edildi', transactionId: 'TXN-8847291089',
      timeline: [
        { title: 'Sipariş Alındı',  time: '18 Şub, 12:20', done: true },
        { title: 'Ödeme Onaylandı', time: '18 Şub, 12:22', done: true },
        { title: 'İptal Edildi',    time: '18 Şub, 13:05', done: true, cancelled: true },
        { title: 'Kargoya Verildi', time: '',                done: false },
        { title: 'Teslim Edildi',   time: '',                done: false }
      ]
    },
    6: {
      id: '#ORD-2842', date: '17 Şubat 2026 - 09:10',
      status: 'shipped', statusLabel: 'Kargoda',
      customer: { name: 'Selin Öztürk', email: 'selin@email.com', phone: '+90 505 678 9012' },
      address: 'Yenişehir Mah. İsmet Paşa Cad. No:18, Seyhan, Adana, 01150',
      shipping: 'PTT Kargo', trackingNo: 'PTT-456789012',
      products: [{ name: 'Adidas Ultraboost 24', variant: '38, Siyah/Beyaz', price: '3.599 TL', qty: 1, img: 'https://picsum.photos/seed/prod5/36/36' }],
      subtotal: '3.599 TL', shippingCost: '29 TL', tax: '599 TL', total: '3.628 TL',
      payment: 'Kapıda Ödeme', paymentStatus: 'Bekleniyor', transactionId: '-',
      timeline: [
        { title: 'Sipariş Alındı',  time: '17 Şub, 09:10', done: true },
        { title: 'Ödeme Onaylandı', time: '17 Şub, 09:12', done: true },
        { title: 'Hazırlanıyor',    time: '17 Şub, 14:00', done: true },
        { title: 'Kargoya Verildi', time: '18 Şub, 09:30', done: true },
        { title: 'Teslim Edildi',   time: 'Bekleniyor...',  done: false }
      ]
    },
    7: {
      id: '#ORD-2841', date: '16 Şubat 2026 - 18:05',
      status: 'delivered', statusLabel: 'Teslim Edildi',
      customer: { name: 'Deniz Yıldız', email: 'deniz@email.com', phone: '+90 552 789 0123' },
      address: 'Bornova Mah. Mevlana Cad. No:9, Bornova, İzmir, 35040',
      shipping: 'Sürat Kargo', trackingNo: 'SK-123456789',
      products: [
        { name: "L'Oréal Revitalift Serum",     variant: '30ml', price: '899 TL',  qty: 1, img: 'https://picsum.photos/seed/prod7/36/36' },
        { name: 'Revitalift Gece Kremi',         variant: '50ml', price: '799 TL',  qty: 1, img: 'https://picsum.photos/seed/prod7b/36/36' },
        { name: 'Revitalift Tonik',              variant: '200ml', price: '999 TL', qty: 1, img: 'https://picsum.photos/seed/prod7c/36/36' }
      ],
      subtotal: '2.697 TL', shippingCost: 'Ücretsiz', tax: '449 TL', total: '2.697 TL',
      payment: 'Kredi Kartı', paymentStatus: 'Ödendi', transactionId: 'TXN-8847291101',
      timeline: [
        { title: 'Sipariş Alındı',  time: '16 Şub, 18:05', done: true },
        { title: 'Ödeme Onaylandı', time: '16 Şub, 18:07', done: true },
        { title: 'Hazırlanıyor',    time: '17 Şub, 08:30', done: true },
        { title: 'Kargoya Verildi', time: '17 Şub, 16:00', done: true },
        { title: 'Teslim Edildi',   time: '19 Şub, 12:20', done: true }
      ]
    },
    8: {
      id: '#ORD-2840', date: '15 Şubat 2026 - 11:30',
      status: 'pending', statusLabel: 'Bekleyen',
      customer: { name: 'Can Akın', email: 'can@email.com', phone: '+90 536 890 1234' },
      address: 'Kızılay Mah. Gazi Mustafa Kemal Blv. No:120, Çankaya, Ankara, 06570',
      shipping: 'Yurtiçi Kargo', trackingNo: '-',
      products: [{ name: 'Sony WH-1000XM6', variant: 'Gümüş', price: '24.999 TL', qty: 1, img: 'https://picsum.photos/seed/prod4/36/36' }],
      subtotal: '24.999 TL', shippingCost: 'Ücretsiz', tax: '4.166 TL', total: '24.999 TL',
      payment: 'Havale/EFT', paymentStatus: 'Bekleniyor', transactionId: '-',
      timeline: [
        { title: 'Sipariş Alındı',  time: '15 Şub, 11:30', done: true },
        { title: 'Ödeme Onaylandı', time: 'Bekleniyor...',  done: false },
        { title: 'Hazırlanıyor',    time: '',                done: false },
        { title: 'Kargoya Verildi', time: '',                done: false },
        { title: 'Teslim Edildi',   time: '',                done: false }
      ]
    }
  };

  /* ==================== STATUS CONFIG ==================== */
  var statusConfig = {
    pending:    { label: 'Bekleyen',       icon: 'bi-clock-fill',       cls: 'ord-status-pending' },
    processing: { label: 'Hazırlanıyor',   icon: 'bi-gear-fill',        cls: 'ord-status-processing' },
    shipped:    { label: 'Kargoda',        icon: 'bi-truck',             cls: 'ord-status-shipped' },
    delivered:  { label: 'Teslim Edildi',  icon: 'bi-check-circle-fill', cls: 'ord-status-delivered' },
    cancelled:  { label: 'İptal',          icon: 'bi-x-circle-fill',     cls: 'ord-status-cancelled' }
  };

  /* ==================== INIT ==================== */
  document.addEventListener('DOMContentLoaded', function () {
    animateCounters();
  });

  /* ==================== COUNTER ANIMATION ==================== */
  function animateCounters() {
    var statValues = document.querySelectorAll('.usr-stat-value');
    statValues.forEach(function (el) {
      var raw = el.textContent.trim();
      if (raw.indexOf('%') !== -1 || raw.indexOf('M') !== -1 || raw.indexOf('.') !== -1) return;
      var target = parseInt(raw.replace(/\D/g, ''), 10);
      if (isNaN(target)) return;
      var start     = 0;
      var duration  = 1400;
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

  /* ==================== FILTER BY STATUS TAB ==================== */
  window.filterByOrderStatus = function (status, btn) {
    currentStatus = status;
    document.querySelectorAll('.cl-status-tab').forEach(function (t) { t.classList.remove('active'); });
    btn.classList.add('active');
    applyFilters();
  };

  /* ==================== FILTER ORDERS ==================== */
  window.filterOrders = function () { applyFilters(); };

  function applyFilters() {
    var search  = (document.getElementById('orderSearch').value || '').toLowerCase().trim();
    var payment = document.getElementById('filterPayment').value;
    var date    = document.getElementById('filterDate').value;
    var amount  = document.getElementById('filterAmount').value;
    var rows    = document.querySelectorAll('#ordersTableBody tr');

    rows.forEach(function (row) {
      var rowStatus  = row.getAttribute('data-status')  || '';
      var rowPayment = row.getAttribute('data-payment') || '';
      var rowAmount  = parseInt(row.getAttribute('data-amount') || '0', 10);
      var rowText    = row.textContent.toLowerCase();

      var statusMatch  = currentStatus === 'all' || rowStatus === currentStatus;
      var paymentMatch = !payment || rowPayment === payment;
      var searchMatch  = !search  || rowText.indexOf(search) !== -1;
      var amountMatch  = !amount  || checkAmount(rowAmount, amount);
      var dateMatch    = !date    || true; // demo: always true

      row.style.display = (statusMatch && paymentMatch && searchMatch && amountMatch && dateMatch) ? '' : 'none';
    });
  }

  function checkAmount(val, range) {
    if (range === '0-500')       return val <= 500;
    if (range === '500-2000')    return val > 500   && val <= 2000;
    if (range === '2000-10000')  return val > 2000  && val <= 10000;
    if (range === '10000+')      return val > 10000;
    return true;
  }

  /* ==================== RESET FILTERS ==================== */
  window.resetOrderFilters = function () {
    document.getElementById('orderSearch').value  = '';
    document.getElementById('filterPayment').value = '';
    document.getElementById('filterDate').value    = '';
    document.getElementById('filterAmount').value  = '';
    document.querySelectorAll('#ordersTableBody tr').forEach(function (r) { r.style.display = ''; });
    showToast('Filtreler sıfırlandı', 'info');
  };

  /* ==================== SORT ==================== */
  window.sortOrders = function (col) {
    if (sortColumn === col) {
      sortDir = sortDir === 'asc' ? 'desc' : 'asc';
    } else {
      sortColumn = col;
      sortDir    = 'asc';
    }
    var tbody = document.getElementById('ordersTableBody');
    var rows  = Array.prototype.slice.call(tbody.querySelectorAll('tr'));

    rows.sort(function (a, b) {
      var aVal = getCellValue(a, col);
      var bVal = getCellValue(b, col);
      if (!isNaN(aVal) && !isNaN(bVal)) {
        return sortDir === 'asc' ? aVal - bVal : bVal - aVal;
      }
      return sortDir === 'asc'
        ? String(aVal).localeCompare(String(bVal), 'tr')
        : String(bVal).localeCompare(String(aVal), 'tr');
    });

    rows.forEach(function (r) { tbody.appendChild(r); });

    document.querySelectorAll('.cl-th-sortable i').forEach(function (i) { i.className = 'bi bi-arrow-down-up'; });
    var idx    = { id: 1, customer: 2, products: 3, total: 4, status: 5, payment: 6, date: 7 };
    var thList = document.querySelectorAll('.cl-th-sortable');
    if (idx[col] !== undefined && thList[idx[col] - 1]) {
      thList[idx[col] - 1].querySelector('i').className = sortDir === 'asc' ? 'bi bi-arrow-up' : 'bi bi-arrow-down';
    }
  };

  function getCellValue(row, col) {
    var cells = row.querySelectorAll('td');
    if (col === 'id')       return cells[1] ? cells[1].textContent.trim() : '';
    if (col === 'customer') return cells[2] ? cells[2].textContent.trim() : '';
    if (col === 'total')    return parseInt(row.getAttribute('data-amount') || '0', 10);
    if (col === 'status')   return row.getAttribute('data-status') || '';
    if (col === 'payment')  return row.getAttribute('data-payment') || '';
    if (col === 'date')     return cells[7] ? cells[7].textContent.trim() : '';
    return '';
  }

  /* ==================== SELECT ALL / BULK ==================== */
  window.toggleSelectAll = function (master) {
    document.querySelectorAll('#ordersTableBody .usr-checkbox').forEach(function (cb) {
      cb.checked = master.checked;
    });
    updateBulk();
  };

  window.updateBulk = function () {
    var checked = document.querySelectorAll('#ordersTableBody .usr-checkbox:checked').length;
    var bulk    = document.getElementById('bulkActions');
    var counter = document.getElementById('selectedCount');
    if (checked > 0) {
      bulk.classList.remove('d-none');
      counter.textContent = checked;
    } else {
      bulk.classList.add('d-none');
    }
    var all = document.getElementById('selectAll');
    var total = document.querySelectorAll('#ordersTableBody .usr-checkbox').length;
    all.indeterminate = checked > 0 && checked < total;
    all.checked       = checked === total && total > 0;
  };

  /* ==================== BULK ACTIONS ==================== */
  window.bulkOrderAction = function (action) {
    var checked = document.querySelectorAll('#ordersTableBody .usr-checkbox:checked');
    if (checked.length === 0) { showToast('Lütfen sipariş seçin', 'warning'); return; }

    if (action === 'cancel') {
      document.getElementById('bulkCancelCount').textContent = checked.length;
      var modal = new bootstrap.Modal(document.getElementById('bulkCancelModal'));
      modal.show();
      return;
    }

    var cfg = statusConfig[action];
    if (!cfg) return;

    checked.forEach(function (cb) {
      var row    = cb.closest('tr');
      var badge  = row.querySelector('.ord-status-badge');
      if (badge) {
        badge.className   = 'ord-status-badge ' + cfg.cls;
        badge.innerHTML   = '<i class="bi ' + cfg.icon + '"></i> ' + cfg.label;
        row.setAttribute('data-status', action);
      }
    });

    document.getElementById('selectAll').checked = false;
    document.querySelectorAll('#ordersTableBody .usr-checkbox').forEach(function (cb) { cb.checked = false; });
    updateBulk();
    showToast(checked.length + ' sipariş "' + cfg.label + '" olarak güncellendi', 'success');
  };

  window.confirmBulkCancel = function () {
    var checked = document.querySelectorAll('#ordersTableBody .usr-checkbox:checked');
    var cfg     = statusConfig['cancelled'];
    checked.forEach(function (cb) {
      var row   = cb.closest('tr');
      var badge = row.querySelector('.ord-status-badge');
      if (badge) {
        badge.className = 'ord-status-badge ' + cfg.cls;
        badge.innerHTML = '<i class="bi ' + cfg.icon + '"></i> ' + cfg.label;
        row.setAttribute('data-status', 'cancelled');
      }
    });
    document.getElementById('selectAll').checked = false;
    document.querySelectorAll('#ordersTableBody .usr-checkbox').forEach(function (cb) { cb.checked = false; });
    updateBulk();
    showToast(checked.length + ' sipariş iptal edildi', 'danger');
  };

  /* ==================== ORDER DETAIL MODAL ==================== */
  window.openOrderDetail = function (id) {
    var o = orderData[id];
    if (!o) return;
    currentOrder = id;

    document.getElementById('detailOrderId').textContent    = o.id;
    document.getElementById('detailOrderDate').textContent  = o.date;
    document.getElementById('detailCustomerName').textContent  = o.customer.name;
    document.getElementById('detailCustomerEmail').textContent = o.customer.email;
    document.getElementById('detailCustomerPhone').textContent = o.customer.phone;
    document.getElementById('detailAddress').textContent    = o.address;
    document.getElementById('detailShipping').textContent   = o.shipping;
    document.getElementById('detailTrackingNo').textContent = o.trackingNo;
    document.getElementById('detailSubtotal').textContent   = o.subtotal;
    document.getElementById('detailShippingCost').textContent = o.shippingCost;
    document.getElementById('detailTax').textContent        = o.tax;
    document.getElementById('detailTotal').textContent      = o.total;
    document.getElementById('detailPaymentMethod').textContent = o.payment;
    document.getElementById('detailPaymentStatus').textContent = o.paymentStatus;
    document.getElementById('detailTransactionId').textContent = o.transactionId;

    // Status badge
    var cfg   = statusConfig[o.status] || {};
    var badge = document.getElementById('detailOrderStatus');
    badge.className = 'ord-status-badge ' + (cfg.cls || '');
    badge.innerHTML = '<i class="bi ' + (cfg.icon || '') + '"></i> ' + o.statusLabel;

    // Timeline
    buildDetailTimeline(o.timeline);

    // Products table
    buildDetailProducts(o.products);

    // Update modal action buttons
    var invoiceBtn = document.querySelector('#orderDetailModal .btn-teal');
    var statusBtn  = document.querySelector('#orderDetailModal .btn-glass');
    if (invoiceBtn) invoiceBtn.setAttribute('onclick', 'openInvoiceModal(' + id + ')');
    if (statusBtn)  statusBtn.setAttribute('onclick', 'openStatusModal(' + id + ')');

    var modal = new bootstrap.Modal(document.getElementById('orderDetailModal'));
    modal.show();
  };

  function buildDetailTimeline(steps) {
    var container = document.querySelector('.ord-timeline');
    if (!container) return;
    var html = '';
    steps.forEach(function (step, idx) {
      var cancelledClass = step.cancelled ? ' cancelled' : '';
      var doneClass      = step.done ? ' completed' : '';
      var activeClass    = (idx === steps.length - 1 && step.done && !step.cancelled) ? ' active' : '';
      html += '<div class="ord-timeline-step' + doneClass + activeClass + cancelledClass + '">';
      html += '<div class="ord-timeline-dot">';
      if (step.done) html += step.cancelled ? '<i class="bi bi-x"></i>' : '<i class="bi bi-check"></i>';
      html += '</div>';
      html += '<div class="ord-timeline-info">';
      html += '<span class="ord-timeline-title">' + step.title + '</span>';
      if (step.time) html += '<span class="ord-timeline-time">' + step.time + '</span>';
      html += '</div></div>';
      if (idx < steps.length - 1) {
        html += '<div class="ord-timeline-line' + (step.done && !step.cancelled ? ' completed' : '') + '"></div>';
      }
    });
    container.innerHTML = html;
  }

  function buildDetailProducts(products) {
    var tbody = document.querySelector('#detailProductsTable tbody');
    if (!tbody) return;
    var html = '';
    products.forEach(function (p) {
      html += '<tr>';
      html += '<td><div class="d-flex align-items-center gap-2">';
      html += '<img src="' + p.img + '" alt="' + p.name + '" class="ord-product-thumb-lg">';
      html += '<div><span class="ord-product-name">' + p.name + '</span>';
      html += '<span class="ord-product-variant">' + p.variant + '</span></div>';
      html += '</div></td>';
      html += '<td>' + p.price + '</td><td>' + p.qty + '</td>';
      html += '<td class="text-end"><strong>' + p.price + '</strong></td>';
      html += '</tr>';
    });
    tbody.innerHTML = html;
  }

  /* ==================== STATUS MODAL ==================== */
  window.openStatusModal = function (id) {
    var o = orderData[id];
    if (!o) return;
    currentOrder = id;

    document.getElementById('statusOrderId').textContent = o.id;
    document.querySelectorAll('input[name="orderStatus"]').forEach(function (radio) {
      radio.checked = radio.value === o.status;
    });
    document.getElementById('statusNote').value = '';

    bootstrap.Modal.getInstance(document.getElementById('orderDetailModal')) &&
      bootstrap.Modal.getInstance(document.getElementById('orderDetailModal')).hide();

    var modal = new bootstrap.Modal(document.getElementById('statusModal'));
    modal.show();
  };

  window.updateOrderStatus = function () {
    var selected = document.querySelector('input[name="orderStatus"]:checked');
    if (!selected) { showToast('Lütfen bir durum seçin', 'warning'); return; }
    var newStatus = selected.value;
    var cfg       = statusConfig[newStatus];
    var o         = orderData[currentOrder];
    if (!o || !cfg) return;

    o.status      = newStatus;
    o.statusLabel = cfg.label;

    // Update row in table
    var rows = document.querySelectorAll('#ordersTableBody tr');
    rows.forEach(function (row, idx) {
      if (idx + 1 === currentOrder) {
        var badge = row.querySelector('.ord-status-badge');
        if (badge) {
          badge.className = 'ord-status-badge ' + cfg.cls;
          badge.innerHTML = '<i class="bi ' + cfg.icon + '"></i> ' + cfg.label;
          row.setAttribute('data-status', newStatus);
        }
      }
    });

    bootstrap.Modal.getInstance(document.getElementById('statusModal')).hide();
    showToast('Sipariş durumu "' + cfg.label + '" olarak güncellendi', 'success');
  };

  /* ==================== INVOICE MODAL ==================== */
  window.openInvoiceModal = function (id) {
    var o = orderData[id];
    if (!o) return;
    currentOrder = id;

    var ordNum = o.id.replace('#ORD-', '');
    document.getElementById('invoiceNo').textContent      = 'FTR-2026-' + ordNum;
    document.getElementById('invoiceDate').textContent    = o.date.split(' - ')[0];
    document.getElementById('invoiceOrderId').textContent = o.id;

    document.getElementById('invoiceBillTo').innerHTML =
      '<strong>' + o.customer.name + '</strong><br>' + o.address + '<br>' + o.customer.email;
    document.getElementById('invoiceShipTo').innerHTML =
      '<strong>' + o.customer.name + '</strong><br>' + o.address;

    var tbody = document.getElementById('invoiceTableBody');
    var html  = '';
    o.products.forEach(function (p, idx) {
      html += '<tr><td>' + (idx + 1) + '</td><td>' + p.name + ' - ' + p.variant + '</td>';
      html += '<td>' + p.price + '</td><td>' + p.qty + '</td>';
      html += '<td class="text-end">' + p.price + '</td></tr>';
    });
    tbody.innerHTML = html;

    var summary = document.querySelector('.ord-invoice-summary');
    if (summary) {
      summary.innerHTML =
        '<div class="ord-summary-row"><span>Ara Toplam</span><span>' + o.subtotal + '</span></div>' +
        '<div class="ord-summary-row"><span>Kargo</span><span>' + o.shippingCost + '</span></div>' +
        '<div class="ord-summary-row"><span>KDV</span><span>' + o.tax + '</span></div>' +
        '<div class="ord-summary-row ord-summary-total"><span>GENEL TOPLAM</span><span>' + o.total + '</span></div>';
    }

    var footer = document.querySelector('.ord-invoice-footer p');
    if (footer) footer.textContent = 'Ödeme Yöntemi: ' + o.payment + ' | İşlem ID: ' + o.transactionId;

    bootstrap.Modal.getInstance(document.getElementById('orderDetailModal')) &&
      bootstrap.Modal.getInstance(document.getElementById('orderDetailModal')).hide();

    var modal = new bootstrap.Modal(document.getElementById('invoiceModal'));
    modal.show();
  };

  window.printInvoice = function () {
    window.print();
  };

  window.downloadInvoice = function () {
    showToast('PDF indiriliyor...', 'info');
  };

  window.printOrder = function () {
    window.print();
  };

  /* ==================== TRACKING MODAL ==================== */
  window.trackShipment = function (id) {
    var o = orderData[id];
    if (!o) return;

    document.getElementById('trackingNo').textContent = o.trackingNo;
    var modal = new bootstrap.Modal(document.getElementById('trackingModal'));
    modal.show();
  };

  /* ==================== NEW ORDER MODAL (placeholder) ==================== */
  window.openNewOrderModal = function () {
    showToast('Yeni sipariş formu yakında aktif olacak', 'info');
  };

  /* ==================== EXPORT ==================== */
  window.exportOrders = function (type) {
    var labels = { csv: 'CSV', excel: 'Excel', pdf: 'PDF' };
    showToast('Siparişler ' + (labels[type] || type) + ' olarak dışa aktarılıyor...', 'info');
  };

  /* ==================== PAGINATION ==================== */
  window.goToPage = function (page) {
    page = parseInt(page, 10);
    if (isNaN(page) || page < 1) return;
    document.querySelectorAll('.cl-page-btn').forEach(function (btn) { btn.classList.remove('active'); });
    showToast('Sayfa ' + page + ' yükleniyor...', 'info');
  };

  window.changePerPage = function () {
    var val = document.getElementById('perPage').value;
    showToast('Sayfa başına ' + val + ' sipariş gösteriliyor', 'info');
  };

  /* ==================== TOAST ==================== */
  function showToast(message, type) {
    type = type || 'success';
    var existing = document.querySelector('.ca-toast');
    if (existing) existing.remove();

    var icons = { success: 'bi-check-circle-fill', danger: 'bi-x-circle-fill', warning: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
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

  window.showOrdersToast = showToast;

})();
