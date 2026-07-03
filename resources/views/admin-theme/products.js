// ==================== PRODUCTS PAGE - JavaScript ====================

(function () {
  'use strict';

  var deleteTargetId = null;
  var currentView = 'grid';

  // ==================== VIEW SWITCHING ====================
  window.switchView = function (view) {
    currentView = view;
    var gridView = document.getElementById('productGridView');
    var tableView = document.getElementById('productTableView');
    var gridBtn = document.getElementById('gridViewBtn');
    var tableBtn = document.getElementById('tableViewBtn');

    if (view === 'grid') {
      gridView.classList.remove('d-none');
      tableView.classList.add('d-none');
      gridBtn.classList.add('active');
      tableBtn.classList.remove('active');
    } else {
      gridView.classList.add('d-none');
      tableView.classList.remove('d-none');
      gridBtn.classList.remove('active');
      tableBtn.classList.add('active');
    }
  };


  // ==================== STATUS TAB FILTERING ====================
  window.filterByProductStatus = function (status, btn) {
    document.querySelectorAll('.cl-status-tab').forEach(function (t) { t.classList.remove('active'); });
    btn.classList.add('active');

    // Filter grid cards
    var cards = document.querySelectorAll('#productGridView [data-status]');
    cards.forEach(function (card) {
      var cardStatus = card.getAttribute('data-status');
      card.style.display = (status === 'all' || cardStatus === status) ? '' : 'none';
    });

    // Filter table rows
    var rows = document.querySelectorAll('#productTableBody tr[data-status]');
    rows.forEach(function (row) {
      var rowStatus = row.getAttribute('data-status');
      row.style.display = (status === 'all' || rowStatus === status) ? '' : 'none';
    });
  };


  // ==================== PRODUCT FILTERING ====================
  window.filterProducts = function () {
    var search = document.getElementById('productSearch').value.toLowerCase();
    var category = document.getElementById('filterCategory').value;
    var brand = document.getElementById('filterBrand').value;
    var stock = document.getElementById('filterStock').value;

    // Filter grid cards
    var cards = document.querySelectorAll('#productGridView [data-status]');
    cards.forEach(function (card) {
      var title = (card.querySelector('.prd-card-title') || {}).textContent || '';
      var brandText = (card.querySelector('.prd-card-brand') || {}).textContent || '';
      var meta = (card.querySelector('.prd-card-meta') || {}).textContent || '';
      var cardCategory = card.getAttribute('data-category');
      var cardBrand = card.getAttribute('data-brand');
      var cardStock = card.getAttribute('data-stock');

      var matchSearch = !search || title.toLowerCase().indexOf(search) !== -1 || brandText.toLowerCase().indexOf(search) !== -1 || meta.toLowerCase().indexOf(search) !== -1;
      var matchCategory = !category || cardCategory === category;
      var matchBrand = !brand || cardBrand === brand;
      var matchStock = !stock || cardStock === stock;

      card.style.display = (matchSearch && matchCategory && matchBrand && matchStock) ? '' : 'none';
    });

    // Filter table rows
    var rows = document.querySelectorAll('#productTableBody tr[data-status]');
    rows.forEach(function (row) {
      var title = (row.querySelector('.cl-content-title') || {}).textContent || '';
      var meta = (row.querySelector('.cl-content-meta') || {}).textContent || '';
      var rowCategory = row.getAttribute('data-category');
      var rowBrand = row.getAttribute('data-brand');

      var matchSearch = !search || title.toLowerCase().indexOf(search) !== -1 || meta.toLowerCase().indexOf(search) !== -1;
      var matchCategory = !category || rowCategory === category;
      var matchBrand = !brand || rowBrand === brand;

      row.style.display = (matchSearch && matchCategory && matchBrand) ? '' : 'none';
    });
  };


  // ==================== RESET FILTERS ====================
  window.resetProductFilters = function () {
    document.getElementById('productSearch').value = '';
    document.getElementById('filterCategory').selectedIndex = 0;
    document.getElementById('filterBrand').selectedIndex = 0;
    document.getElementById('filterPrice').selectedIndex = 0;
    document.getElementById('filterStock').selectedIndex = 0;

    document.querySelectorAll('.cl-status-tab').forEach(function (t, i) {
      t.classList.toggle('active', i === 0);
    });

    document.querySelectorAll('#productGridView [data-status]').forEach(function (card) {
      card.style.display = '';
    });

    document.querySelectorAll('#productTableBody tr[data-status]').forEach(function (row) {
      row.style.display = '';
    });

    showToast('Filtreler sıfırlandı.', 'info');
  };


  // ==================== SELECT ALL / BULK (TABLE VIEW) ====================
  window.toggleSelectAllProducts = function (checkbox) {
    var rows = document.querySelectorAll('#productTableBody tr:not([style*="display: none"]) .usr-checkbox');
    rows.forEach(function (cb) { cb.checked = checkbox.checked; });
    updateProductBulk();
  };

  window.updateProductBulk = function () {
    var checked = document.querySelectorAll('#productTableBody .usr-checkbox:checked').length;
    var bulk = document.getElementById('bulkActions');
    var count = document.getElementById('selectedCount');
    if (checked > 0) {
      bulk.classList.remove('d-none');
      count.textContent = checked;
    } else {
      bulk.classList.add('d-none');
    }
  };

  window.bulkProductAction = function (action) {
    var count = document.querySelectorAll('#productTableBody .usr-checkbox:checked').length;
    if (count === 0) return;

    if (action === 'delete') {
      document.getElementById('bulkDeleteCount').textContent = count;
      var modal = new bootstrap.Modal(document.getElementById('bulkDeleteModal'));
      modal.show();
      return;
    }

    var actionText = { activate: 'aktif etmek', draft: 'taslağa almak' };
    if (confirm(count + ' ürünü ' + (actionText[action] || action) + ' istediğinize emin misiniz?')) {
      showToast(count + ' ürün başarıyla işlendi.', 'success');
      document.querySelectorAll('#productTableBody .usr-checkbox:checked').forEach(function (cb) { cb.checked = false; });
      var selectAll = document.getElementById('selectAllProducts');
      if (selectAll) selectAll.checked = false;
      updateProductBulk();
    }
  };

  window.confirmBulkDelete = function () {
    var count = document.querySelectorAll('#productTableBody .usr-checkbox:checked').length;
    showToast(count + ' ürün başarıyla silindi.', 'success');
    document.querySelectorAll('#productTableBody .usr-checkbox:checked').forEach(function (cb) { cb.checked = false; });
    var selectAll = document.getElementById('selectAllProducts');
    if (selectAll) selectAll.checked = false;
    updateProductBulk();
  };


  // ==================== SORT PRODUCTS ====================
  var sortState = {};
  window.sortProducts = function (column) {
    sortState[column] = !sortState[column];
    var tbody = document.getElementById('productTableBody');
    if (!tbody) return;
    var rows = Array.from(tbody.querySelectorAll('tr'));

    var colMap = {
      name: '.cl-content-title',
      category: '.cl-category-badge',
      price: '.prd-price-current',
      stock: '.prd-table-stock',
      status: '.usr-status-badge',
      sales: '.cl-views'
    };
    var selector = colMap[column];

    rows.sort(function (a, b) {
      var aVal = (a.querySelector(selector) || {}).textContent || '';
      var bVal = (b.querySelector(selector) || {}).textContent || '';

      if (column === 'price' || column === 'stock' || column === 'sales') {
        aVal = parseInt(aVal.replace(/[^\d]/g, '')) || 0;
        bVal = parseInt(bVal.replace(/[^\d]/g, '')) || 0;
        return sortState[column] ? aVal - bVal : bVal - aVal;
      }

      return sortState[column]
        ? aVal.localeCompare(bVal, 'tr')
        : bVal.localeCompare(aVal, 'tr');
    });

    rows.forEach(function (row) { tbody.appendChild(row); });
  };


  // ==================== QUICK VIEW MODAL ====================
  var productData = {
    1: {
      name: 'iPhone 16 Pro Max',
      category: 'Elektronik',
      brand: 'Apple',
      price: '54.999 TL',
      oldPrice: '59.999 TL',
      discount: '-8%',
      sku: 'SKU-1001',
      stock: '245 adet',
      sales: '1.247 adet',
      desc: 'Apple iPhone 16 Pro Max, A18 Pro çip ile güçlendirilmiş, 6.9 inç Super Retina XDR OLED ekrana sahip premium akıllı telefon. 48MP ana kamera, 5x optik zoom ve USB-C bağlantı özelliği ile donatılmıştır.',
      img: 'prod1'
    },
    2: {
      name: 'Samsung Galaxy S25 Ultra',
      category: 'Elektronik',
      brand: 'Samsung',
      price: '44.999 TL',
      oldPrice: '52.999 TL',
      discount: '-15%',
      sku: 'SKU-1002',
      stock: '182 adet',
      sales: '892 adet',
      desc: 'Samsung Galaxy S25 Ultra, Snapdragon 8 Elite işlemci, 6.8 inç Dynamic AMOLED 2X ekran, 200MP kamera ve S Pen desteği ile üst düzey performans sunar.',
      img: 'prod2'
    },
    3: {
      name: 'Nike Air Max 2026',
      category: 'Giyim',
      brand: 'Nike',
      price: '4.299 TL',
      oldPrice: '',
      discount: '',
      sku: 'SKU-2001',
      stock: '8 adet',
      sales: '2.340 adet',
      desc: 'Nike Air Max 2026, yenilikçi Air yastıklama teknolojisi, nefes alabilir örgü üst yüzey ve dayanıklı kauçuk taban ile maksimum konfor sunar.',
      img: 'prod3'
    },
    4: {
      name: 'Sony WH-1000XM6',
      category: 'Elektronik',
      brand: 'Sony',
      price: '24.999 TL',
      oldPrice: '31.249 TL',
      discount: '-20%',
      sku: 'SKU-1003',
      stock: '67 adet',
      sales: '534 adet',
      desc: 'Sony WH-1000XM6, sektör lideri gürültü engelleme, 40 saat pil ömrü, LDAC ve DSEE Extreme ile kristal netliğinde ses kalitesi sunan kablosuz kulaklık.',
      img: 'prod4'
    },
    5: {
      name: 'Adidas Ultraboost 24',
      category: 'Spor',
      brand: 'Adidas',
      price: '3.599 TL',
      oldPrice: '',
      discount: '',
      sku: 'SKU-2002',
      stock: '120 adet',
      sales: '678 adet',
      desc: 'Adidas Ultraboost 24, BOOST teknolojisi ile enerji geri dönüşümü, Primeknit+ üst yüzey ve Continental kauçuk taban ile üstün performans koşu ayakkabısı.',
      img: 'prod5'
    },
    6: {
      name: 'MacBook Pro M4 Max',
      category: 'Elektronik',
      brand: 'Apple',
      price: '89.999 TL',
      oldPrice: '',
      discount: '',
      sku: 'SKU-1004',
      stock: '0 adet',
      sales: '3.891 adet',
      desc: 'Apple MacBook Pro M4 Max, 16 inç Liquid Retina XDR ekran, 48GB birleşik bellek, 1TB SSD ve 22 saate kadar pil ömrü ile profesyonel kullanıcılar için tasarlanmıştır.',
      img: 'prod6'
    },
    7: {
      name: "L'Oréal Revitalift Serum",
      category: 'Kozmetik',
      brand: "L'Oréal Paris",
      price: '899 TL',
      oldPrice: '1.199 TL',
      discount: '-25%',
      sku: 'SKU-3001',
      stock: '340 adet',
      sales: '1.456 adet',
      desc: "L'Oréal Paris Revitalift %1.5 Saf Hyaluronik Asit Serum, yoğun nemlendirme ve kırışıklık karşıtı etkisiyle cildi dolgunlaştırır ve gençleştirir.",
      img: 'prod7'
    },
    8: {
      name: 'Dyson V15 Detect',
      category: 'Ev & Yaşam',
      brand: 'Dyson',
      price: '18.999 TL',
      oldPrice: '21.110 TL',
      discount: '-10%',
      sku: 'SKU-4001',
      stock: '53 adet',
      sales: '287 adet',
      desc: 'Dyson V15 Detect, lazer toz algılama teknolojisi, piezo sensör ile gerçek zamanlı parçacık sayımı ve 60 dakikaya kadar çalışma süresi sunan kablosuz süpürge.',
      img: 'prod8'
    }
  };

  window.openQuickView = function (id) {
    var data = productData[id];
    if (!data) return;

    document.getElementById('quickViewName').textContent = data.name;
    document.getElementById('quickViewCategory').textContent = data.category;
    document.getElementById('quickViewPrice').textContent = data.price;
    document.getElementById('quickViewDesc').textContent = data.desc;
    document.getElementById('quickViewSku').textContent = data.sku;
    document.getElementById('quickViewBrand').textContent = data.brand;
    document.getElementById('quickViewStock').textContent = data.stock;
    document.getElementById('quickViewCategorySpec').textContent = data.category;
    document.getElementById('quickViewSales').textContent = data.sales;
    document.getElementById('quickViewMainImg').src = 'https://picsum.photos/seed/' + data.img + '/600/450';

    var oldPrice = document.getElementById('quickViewOldPrice');
    var discountTag = document.querySelector('.prd-modal-discount-tag');
    if (data.oldPrice) {
      oldPrice.textContent = data.oldPrice;
      oldPrice.classList.remove('d-none');
      if (discountTag) {
        discountTag.textContent = data.discount;
        discountTag.classList.remove('d-none');
      }
    } else {
      oldPrice.classList.add('d-none');
      if (discountTag) discountTag.classList.add('d-none');
    }

    document.getElementById('quickViewEditBtn').href = 'product-add.html?edit=' + id;

    // Update thumbnails
    var thumbContainer = document.getElementById('quickViewThumbs');
    if (thumbContainer) {
      thumbContainer.innerHTML = '';
      var suffixes = ['', 'a', 'b', 'c'];
      suffixes.forEach(function (suffix, index) {
        var btn = document.createElement('button');
        btn.className = 'prd-modal-thumb' + (index === 0 ? ' active' : '');
        btn.onclick = function () { changeQuickViewImg(btn, data.img + suffix); };
        btn.innerHTML = '<img src="https://picsum.photos/seed/' + data.img + suffix + '/100/75" alt="Görsel ' + (index + 1) + '" loading="lazy">';
        thumbContainer.appendChild(btn);
      });
    }

    var modal = new bootstrap.Modal(document.getElementById('quickViewModal'));
    modal.show();
  };

  window.changeQuickViewImg = function (btn, seed) {
    document.querySelectorAll('.prd-modal-thumb').forEach(function (t) { t.classList.remove('active'); });
    btn.classList.add('active');
    document.getElementById('quickViewMainImg').src = 'https://picsum.photos/seed/' + seed + '/600/450';
  };


  // ==================== DELETE MODAL ====================
  window.openDeleteModal = function (title, id) {
    deleteTargetId = id;
    document.getElementById('deleteProductTitle').textContent = '"' + title + '"';
    var modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    modal.show();
  };

  window.confirmDelete = function () {
    if (deleteTargetId) {
      showToast('Ürün başarıyla silindi.', 'success');
      deleteTargetId = null;
    }
  };


  // ==================== PAGINATION ====================
  window.goToProductPage = function (page) {
    page = parseInt(page);
    if (page < 1 || page > 24) return;
    document.querySelectorAll('.cl-page-btn').forEach(function (btn) { btn.classList.remove('active'); });
    showToast('Sayfa ' + page + ' yükleniyor...', 'info');
  };

  window.changePerPage = function () {
    var perPage = document.getElementById('perPage').value;
    showToast(perPage + ' ürün gösteriliyor.', 'info');
  };


  // ==================== ACTIONS ====================
  window.duplicateProduct = function (id) {
    showToast('Ürün kopyalandı. Taslak olarak kaydedildi.', 'success');
  };

  window.exportProducts = function (format) {
    var formatNames = { csv: 'CSV', json: 'JSON', excel: 'Excel', pdf: 'PDF' };
    showToast((formatNames[format] || format) + ' dosyası indiriliyor...', 'success');
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
    var elements = document.querySelectorAll('.usr-stat-value, .cl-tab-count');

    elements.forEach(function (el) {
      var originalText = el.textContent.trim();
      var suffix = '';
      var numericText = originalText;

      var suffixMatch = originalText.match(/([KMBkmb]+)$/);
      if (suffixMatch) {
        suffix = suffixMatch[1];
        numericText = originalText.replace(/[KMBkmb]+$/, '').trim();
      }

      var target = parseInt(numericText.replace(/\./g, ''), 10);
      if (isNaN(target) || target === 0) return;

      function formatNumber(n) {
        return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
      }

      el.textContent = '0' + suffix;
      var startTime = null;

      function step(timestamp) {
        if (!startTime) startTime = timestamp;
        var progress = Math.min((timestamp - startTime) / duration, 1);
        var eased = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
        var current = Math.floor(eased * target);
        el.textContent = formatNumber(current) + suffix;
        if (progress < 1) {
          requestAnimationFrame(step);
        } else {
          el.textContent = originalText;
        }
      }

      requestAnimationFrame(step);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', animateCounters);
  } else {
    animateCounters();
  }

})();
