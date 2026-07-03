// ==================== PRODUCT ADD PAGE - JavaScript ====================

(function () {
  'use strict';

  // ==================== SLUG GENERATION ====================
  window.generateProductSlug = function (value) {
    var charMap = { 'ç': 'c', 'ğ': 'g', 'ı': 'i', 'ö': 'o', 'ş': 's', 'ü': 'u', 'Ç': 'c', 'Ğ': 'g', 'İ': 'i', 'Ö': 'o', 'Ş': 's', 'Ü': 'u' };
    var slug = value
      .toLowerCase()
      .replace(/[çğıöşüÇĞİÖŞÜ]/g, function (ch) { return charMap[ch] || ch; })
      .replace(/[^a-z0-9\s-]/g, '')
      .replace(/\s+/g, '-')
      .replace(/-+/g, '-')
      .replace(/^-|-$/g, '');

    document.getElementById('productSlug').value = slug;

    // Update SEO preview
    var seoSlug = document.getElementById('seoSlugPreview');
    if (seoSlug) seoSlug.textContent = slug || 'urun-adi';

    var seoTitle = document.getElementById('seoPreviewTitle');
    if (seoTitle && !document.getElementById('seoTitle').value) {
      seoTitle.textContent = (value || 'Ürün Adı') + ' | Site Adı';
    }
  };


  // ==================== CHARACTER COUNTER ====================
  window.updateCharCounter = function (input, max) {
    var counter = document.getElementById(input.id + '-counter');
    if (counter) {
      var len = input.value.length;
      counter.textContent = len;
      counter.parentElement.classList.toggle('text-danger', len > max);
    }
  };


  // ==================== PROFIT CALCULATION ====================
  window.calculateProfit = function () {
    var price = parseFloat(document.getElementById('productPrice').value) || 0;
    var cost = parseFloat(document.getElementById('productCost').value) || 0;

    var profit = price > 0 && cost > 0 ? ((price - cost) / price * 100) : 0;
    profit = Math.max(0, Math.min(100, profit));

    var profitBar = document.getElementById('profitBar');
    var profitText = document.getElementById('profitText');
    if (profitBar && profitText) {
      profitBar.style.width = profit + '%';
      profitText.textContent = '%' + profit.toFixed(1);

      profitBar.className = 'prd-profit-fill';
      if (profit >= 50) {
        profitBar.classList.add('prd-profit-high');
      } else if (profit >= 20) {
        profitBar.classList.add('prd-profit-mid');
      } else {
        profitBar.classList.add('prd-profit-low');
      }
    }
  };


  // ==================== DISCOUNT CALCULATION ====================
  window.calculateDiscount = function () {
    var price = parseFloat(document.getElementById('productPrice').value) || 0;
    var comparePrice = parseFloat(document.getElementById('productComparePrice').value) || 0;

    var discountText = document.getElementById('discountText');
    var discountDisplay = document.getElementById('discountDisplay');
    if (!discountText || !discountDisplay) return;

    if (comparePrice > price && price > 0) {
      var discount = ((comparePrice - price) / comparePrice * 100).toFixed(0);
      discountText.textContent = '%' + discount + ' indirim';
      discountDisplay.classList.add('prd-discount-active');
    } else {
      discountText.textContent = '%0 indirim';
      discountDisplay.classList.remove('prd-discount-active');
    }
  };


  // ==================== SKU GENERATION ====================
  window.generateSku = function () {
    var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    var prefix = chars.charAt(Math.floor(Math.random() * chars.length)) + chars.charAt(Math.floor(Math.random() * chars.length));
    var num = Math.floor(1000 + Math.random() * 9000);
    document.getElementById('productSku').value = 'SKU-' + prefix + '-' + num;
    showToast('SKU otomatik oluşturuldu.', 'info');
  };


  // ==================== STOCK FIELDS TOGGLE ====================
  window.toggleStockFields = function () {
    var checked = document.getElementById('trackStock').checked;
    var fields = document.getElementById('stockFields');
    if (fields) {
      fields.style.display = checked ? '' : 'none';
    }
  };


  // ==================== TAG INPUT ====================
  var tags = [];
  var maxTags = 15;

  window.handleTagInput = function (event) {
    if (event.key === 'Enter' || event.key === ',') {
      event.preventDefault();
      var input = event.target;
      var value = input.value.replace(/,/g, '').trim();
      if (value && tags.length < maxTags && tags.indexOf(value) === -1) {
        tags.push(value);
        renderTags();
      }
      input.value = '';
    }
  };

  function renderTags() {
    var container = document.getElementById('tagsContainer');
    if (!container) return;
    container.innerHTML = '';
    tags.forEach(function (tag, index) {
      var el = document.createElement('span');
      el.className = 'ca-tag';
      el.innerHTML = tag + ' <button type="button" onclick="removeTag(' + index + ')"><i class="bi bi-x"></i></button>';
      container.appendChild(el);
    });
  }

  window.removeTag = function (index) {
    tags.splice(index, 1);
    renderTags();
  };


  // ==================== SECTION NAVIGATION ====================
  window.scrollToSection = function (sectionId, navItem) {
    var section = document.getElementById(sectionId);
    if (section) {
      section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    if (navItem) {
      document.querySelectorAll('.stg-nav-item').forEach(function (item) { item.classList.remove('active'); });
      navItem.classList.add('active');
    }
  };

  // Intersection Observer for nav highlight
  function initNavObserver() {
    var sections = document.querySelectorAll('.card-dark[id^="section-"]');
    var navItems = document.querySelectorAll('.stg-nav-item');
    if (sections.length === 0 || navItems.length === 0) return;

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          var id = entry.target.id;
          navItems.forEach(function (item) {
            item.classList.toggle('active', item.getAttribute('href') === '#' + id);
          });
        }
      });
    }, { rootMargin: '-20% 0px -60% 0px' });

    sections.forEach(function (section) { observer.observe(section); });
  }


  // ==================== VARIANT MANAGEMENT ====================
  window.toggleVariantSection = function () {
    var checked = document.getElementById('hasVariants').checked;
    var section = document.getElementById('variantSection');
    if (section) {
      if (checked) {
        section.classList.remove('d-none');
      } else {
        section.classList.add('d-none');
      }
    }
  };

  window.selectVariantType = function (btn, type) {
    btn.classList.toggle('active');
  };

  window.addVariantRow = function () {
    var tbody = document.getElementById('variantTableBody');
    if (!tbody) return;

    var row = document.createElement('tr');
    row.innerHTML =
      '<td>' +
      '  <div class="d-flex align-items-center gap-2">' +
      '    <span class="prd-color-dot" data-color="#cccccc"></span>' +
      '    <input type="text" class="form-control form-control-sm" placeholder="Seçenek adı">' +
      '  </div>' +
      '</td>' +
      '<td><input type="number" class="form-control form-control-sm" value="0" placeholder="0.00"></td>' +
      '<td><input type="number" class="form-control form-control-sm" value="0" placeholder="0"></td>' +
      '<td><input type="text" class="form-control form-control-sm" placeholder="SKU"></td>' +
      '<td>' +
      '  <button class="usr-action-btn danger" onclick="removeVariantRow(this)" title="Sil"><i class="bi bi-trash"></i></button>' +
      '</td>';

    tbody.appendChild(row);
    showToast('Yeni varyant satırı eklendi.', 'info');
  };

  window.removeVariantRow = function (btn) {
    var row = btn.closest('tr');
    if (row) {
      row.remove();
      showToast('Varyant silindi.', 'info');
    }
  };


  // ==================== IMAGE UPLOADS ====================
  window.handleMainImageUpload = function (input) {
    if (input.files && input.files[0]) {
      var file = input.files[0];
      if (file.size > 2 * 1024 * 1024) {
        showToast('Dosya boyutu 2 MB\'den küçük olmalıdır.', 'error');
        return;
      }

      var reader = new FileReader();
      reader.onload = function (e) {
        var preview = document.getElementById('mainImagePreview');
        var placeholder = document.getElementById('mainImagePlaceholder');
        if (preview && placeholder) {
          preview.src = e.target.result;
          preview.classList.remove('d-none');
          placeholder.classList.add('d-none');
        }
      };
      reader.readAsDataURL(file);
      showToast('Ana görsel yüklendi.', 'success');
    }
  };

  window.handleGalleryUpload = function (input) {
    if (!input.files) return;
    var grid = document.getElementById('galleryGrid');
    var addBtn = grid.querySelector('.prd-gallery-add');
    var existingCount = grid.querySelectorAll('.prd-gallery-item').length;

    Array.from(input.files).forEach(function (file, i) {
      if (existingCount + i >= 8) {
        showToast('En fazla 8 galeri görseli yükleyebilirsiniz.', 'error');
        return;
      }
      if (file.size > 2 * 1024 * 1024) {
        showToast(file.name + ' dosyası çok büyük (Maks. 2 MB).', 'error');
        return;
      }

      var reader = new FileReader();
      reader.onload = function (e) {
        var item = document.createElement('div');
        item.className = 'prd-gallery-item';
        item.innerHTML =
          '<img src="' + e.target.result + '" alt="Galeri görseli" loading="lazy">' +
          '<button class="prd-gallery-remove" onclick="removeGalleryImage(this)" title="Kaldır">' +
          '  <i class="bi bi-x"></i>' +
          '</button>';
        grid.insertBefore(item, addBtn);
      };
      reader.readAsDataURL(file);
    });

    showToast('Galeri görselleri yüklendi.', 'success');
    input.value = '';
  };

  window.removeGalleryImage = function (btn) {
    var item = btn.closest('.prd-gallery-item');
    if (item) {
      item.remove();
      showToast('Görsel kaldırıldı.', 'info');
    }
  };


  // ==================== SEO PREVIEW ====================
  window.updateSeoPreview = function () {
    var title = document.getElementById('seoTitle').value;
    var desc = document.getElementById('seoDescription').value;

    var previewTitle = document.getElementById('seoPreviewTitle');
    var previewDesc = document.getElementById('seoPreviewDesc');

    if (previewTitle) previewTitle.textContent = title || 'Ürün Adı | Site Adı';
    if (previewDesc) previewDesc.textContent = desc || 'Ürünün meta açıklaması burada görünecektir...';
  };


  // ==================== BRAND MODAL ====================
  window.openBrandModal = function () {
    var modal = new bootstrap.Modal(document.getElementById('brandModal'));
    modal.show();
  };

  window.saveBrand = function () {
    var name = document.getElementById('newBrandName').value.trim();
    if (!name) {
      showToast('Marka adı boş olamaz.', 'error');
      return;
    }
    document.getElementById('productBrand').value = name;
    bootstrap.Modal.getInstance(document.getElementById('brandModal')).hide();
    document.getElementById('newBrandName').value = '';
    showToast('Marka "' + name + '" eklendi.', 'success');
  };


  // ==================== RICH TEXT EDITOR ====================
  window.execFormat = function (command) {
    document.execCommand(command, false, null);
    document.getElementById('productEditor').focus();
  };

  window.execHeading = function (tag) {
    if (tag) {
      document.execCommand('formatBlock', false, '<' + tag + '>');
    } else {
      document.execCommand('formatBlock', false, '<p>');
    }
    document.getElementById('productEditor').focus();
  };

  window.insertLink = function () {
    var url = prompt('Bağlantı URL\'si girin:');
    if (url) document.execCommand('createLink', false, url);
  };

  window.insertImage = function () {
    var url = prompt('Görsel URL\'si girin:');
    if (url) document.execCommand('insertImage', false, url);
  };

  window.insertTable = function () {
    var html = '<table class="table table-bordered"><tbody><tr><td>Hücre 1</td><td>Hücre 2</td></tr><tr><td>Hücre 3</td><td>Hücre 4</td></tr></tbody></table>';
    document.execCommand('insertHTML', false, html);
  };

  // Word counter
  function initWordCounter() {
    var editor = document.getElementById('productEditor');
    if (!editor) return;

    editor.addEventListener('input', function () {
      var text = editor.innerText.trim();
      var words = text ? text.split(/\s+/).length : 0;
      var counter = document.getElementById('wordCount');
      if (counter) counter.textContent = words;
    });
  }


  // ==================== FORM ACTIONS ====================
  window.saveProductDraft = function () {
    var name = document.getElementById('productName').value.trim();
    if (!name) {
      showToast('Lütfen ürün adını girin.', 'error');
      document.getElementById('productName').focus();
      return;
    }
    showToast('Ürün taslak olarak kaydedildi.', 'success');
  };

  window.previewProduct = function () {
    showToast('Ürün önizlemesi açılıyor...', 'info');
  };

  window.publishProduct = function () {
    var name = document.getElementById('productName').value.trim();
    var category = document.getElementById('productCategory').value;
    var price = document.getElementById('productPrice').value;

    if (!name) {
      showToast('Lütfen ürün adını girin.', 'error');
      document.getElementById('productName').focus();
      return;
    }
    if (!category) {
      showToast('Lütfen kategori seçin.', 'error');
      document.getElementById('productCategory').focus();
      return;
    }
    if (!price || parseFloat(price) <= 0) {
      showToast('Lütfen geçerli bir fiyat girin.', 'error');
      document.getElementById('productPrice').focus();
      return;
    }

    var modal = new bootstrap.Modal(document.getElementById('successModal'));
    modal.show();
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


  // ==================== INIT ====================
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initNavObserver();
      initWordCounter();
    });
  } else {
    initNavObserver();
    initWordCounter();
  }

})();
