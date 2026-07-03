// ==================== PRODUCT ADD/EDIT PAGE - JavaScript ====================

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

    var slugField = document.getElementById('productSlug');
    if (slugField) slugField.value = slug;

    // Update SEO preview
    var seoSlug = document.getElementById('seoSlugPreview');
    if (seoSlug) seoSlug.textContent = slug || 'urun-adi';

    var seoTitle = document.getElementById('seoPreviewTitle');
    var seoTitleInput = document.getElementById('seoTitle');
    if (seoTitle && seoTitleInput && !seoTitleInput.value) {
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
    var priceEl = document.getElementById('productPrice');
    var costEl = document.getElementById('productCost');
    if (!priceEl || !costEl) return;

    var price = parseFloat(priceEl.value) || 0;
    var cost = parseFloat(costEl.value) || 0;

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
    var priceEl = document.getElementById('productPrice');
    var comparePriceEl = document.getElementById('productComparePrice');
    if (!priceEl || !comparePriceEl) return;

    var price = parseFloat(priceEl.value) || 0;
    var comparePrice = parseFloat(comparePriceEl.value) || 0;

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
    var skuField = document.getElementById('productSku');
    if (skuField) skuField.value = 'SKU-' + prefix + '-' + num;
    showToast('SKU otomatik oluşturuldu.', 'info');
  };


  // ==================== STOCK FIELDS TOGGLE ====================
  window.toggleStockFields = function () {
    var checkbox = document.getElementById('trackStock');
    var fields = document.getElementById('stockFields');
    if (checkbox && fields) {
      fields.style.display = checkbox.checked ? '' : 'none';
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


  // ==================== FEATURES TABLE ====================
  var featureRowIndex = 0;

  function initFeatureIndex() {
    var tbody = document.getElementById('featuresTableBody');
    if (tbody) {
      featureRowIndex = tbody.querySelectorAll('tr').length;
    }
  }

  window.toggleFeaturesSection = function () {
    var checked = document.getElementById('hasFeatures').checked;
    var section = document.getElementById('featuresSection');
    if (section) {
      if (checked) {
        section.classList.remove('d-none');
      } else {
        section.classList.add('d-none');
      }
    }
  };

  window.addFeatureRow = function () {
    var tbody = document.getElementById('featuresTableBody');
    if (!tbody) return;

    var row = document.createElement('tr');
    row.innerHTML =
      '<td>' +
      '  <input type="text" class="form-control form-control-sm"' +
      '         name="features[' + featureRowIndex + '][icon]"' +
      '         value="fa-solid fa-check"' +
      '         placeholder="Ör: fa-solid fa-leaf">' +
      '</td>' +
      '<td>' +
      '  <input type="text" class="form-control form-control-sm"' +
      '         name="features[' + featureRowIndex + '][text]"' +
      '         placeholder="Ör: %100 Doğal & Organik">' +
      '</td>' +
      '<td>' +
      '  <button type="button" class="usr-action-btn danger" onclick="removeFeatureRow(this)" title="Sil"><i class="bi bi-trash"></i></button>' +
      '</td>';

    tbody.appendChild(row);
    featureRowIndex++;
    showToast('Yeni özellik satırı eklendi.', 'info');
  };

  window.removeFeatureRow = function (btn) {
    var tbody = document.getElementById('featuresTableBody');
    if (!tbody) return;

    if (tbody.querySelectorAll('tr').length <= 1) {
      showToast('En az bir satır bulunmalıdır.', 'error');
      return;
    }

    var row = btn.closest('tr');
    if (row) {
      row.remove();
      showToast('Satır silindi.', 'info');
    }
  };

  function cleanFeaturesIfDisabled() {
    var toggle = document.getElementById('hasFeatures');
    if (toggle && !toggle.checked) {
      var tbody = document.getElementById('featuresTableBody');
      if (tbody) {
        var inputs = tbody.querySelectorAll('input[name]');
        inputs.forEach(function (input) { input.removeAttribute('name'); });
      }
    }
  }


  // ==================== NUTRITION TABLE ====================
  var nutritionRowIndex = 0;

  function initNutritionIndex() {
    var tbody = document.getElementById('nutritionTableBody');
    if (tbody) {
      nutritionRowIndex = tbody.querySelectorAll('tr').length;
    }
  }

  window.toggleNutritionSection = function () {
    var checked = document.getElementById('hasNutrition').checked;
    var section = document.getElementById('nutritionSection');
    if (section) {
      if (checked) {
        section.classList.remove('d-none');
      } else {
        section.classList.add('d-none');
      }
    }
  };

  window.addNutritionRow = function () {
    var tbody = document.getElementById('nutritionTableBody');
    if (!tbody) return;

    var row = document.createElement('tr');
    row.innerHTML =
      '<td>' +
      '  <input type="text" class="form-control form-control-sm"' +
      '         name="nutritions[' + nutritionRowIndex + '][name]"' +
      '         placeholder="Ör: Enerji, Protein, Yağ...">' +
      '</td>' +
      '<td>' +
      '  <input type="text" class="form-control form-control-sm"' +
      '         name="nutritions[' + nutritionRowIndex + '][per_value]"' +
      '         placeholder="Ör: 350 kcal">' +
      '</td>' +
      '<td>' +
      '  <input type="text" class="form-control form-control-sm"' +
      '         name="nutritions[' + nutritionRowIndex + '][daily_value]"' +
      '         placeholder="Ör: %17">' +
      '</td>' +
      '<td>' +
      '  <button type="button" class="usr-action-btn danger" onclick="removeNutritionRow(this)" title="Sil"><i class="bi bi-trash"></i></button>' +
      '</td>';

    tbody.appendChild(row);
    nutritionRowIndex++;
    showToast('Yeni besin değeri satırı eklendi.', 'info');
  };

  window.removeNutritionRow = function (btn) {
    var tbody = document.getElementById('nutritionTableBody');
    if (!tbody) return;

    // Minimum 1 row
    if (tbody.querySelectorAll('tr').length <= 1) {
      showToast('En az bir satır bulunmalıdır.', 'error');
      return;
    }

    var row = btn.closest('tr');
    if (row) {
      row.remove();
      showToast('Satır silindi.', 'info');
    }
  };


  // ==================== SHIPPING & RETURN TABLE ====================
  var shippingRowIndex = 2;

  function initShippingIndex() {
    var shippingBody = document.getElementById('shippingTableBody');
    var returnBody = document.getElementById('returnTableBody');
    var count = 0;
    if (shippingBody) count += shippingBody.querySelectorAll('tr').length;
    if (returnBody) count += returnBody.querySelectorAll('tr').length;
    shippingRowIndex = count;
  }

  window.toggleShippingSection = function () {
    var checked = document.getElementById('hasShipping').checked;
    var section = document.getElementById('shippingSection');
    if (section) {
      if (checked) {
        section.classList.remove('d-none');
      } else {
        section.classList.add('d-none');
      }
    }
  };

  window.addShippingRow = function (type) {
    var tbodyId = type === 'shipping' ? 'shippingTableBody' : 'returnTableBody';
    var tbody = document.getElementById(tbodyId);
    if (!tbody) return;

    var row = document.createElement('tr');
    row.innerHTML =
      '<td>' +
      '  <input type="hidden" name="shipping_items[' + shippingRowIndex + '][type]" value="' + type + '">' +
      '  <input type="text" class="form-control form-control-sm"' +
      '         name="shipping_items[' + shippingRowIndex + '][content]"' +
      '         placeholder="' + (type === 'shipping' ? 'Ör: 200₺ üzeri siparişlerde ücretsiz kargo' : 'Ör: 7 gün içinde koşulsuz iade') + '">' +
      '</td>' +
      '<td>' +
      '  <button type="button" class="usr-action-btn danger" onclick="removeShippingRow(this, \'' + tbodyId + '\')" title="Sil"><i class="bi bi-trash"></i></button>' +
      '</td>';

    tbody.appendChild(row);
    shippingRowIndex++;
    showToast(type === 'shipping' ? 'Yeni kargo bilgisi satırı eklendi.' : 'Yeni iade bilgisi satırı eklendi.', 'info');
  };

  window.removeShippingRow = function (btn, tbodyId) {
    var tbody = document.getElementById(tbodyId);
    if (!tbody) return;

    if (tbody.querySelectorAll('tr').length <= 1) {
      showToast('En az bir satır bulunmalıdır.', 'error');
      return;
    }

    var row = btn.closest('tr');
    if (row) {
      row.remove();
      showToast('Satır silindi.', 'info');
    }
  };

  function cleanShippingIfDisabled() {
    var toggle = document.getElementById('hasShipping');
    if (toggle && !toggle.checked) {
      var shippingBody = document.getElementById('shippingTableBody');
      var returnBody = document.getElementById('returnTableBody');
      [shippingBody, returnBody].forEach(function (tbody) {
        if (tbody) {
          var inputs = tbody.querySelectorAll('input[name]');
          inputs.forEach(function (input) { input.removeAttribute('name'); });
        }
      });
    }
  }


  // ==================== DROPZONE.JS (Galeri Görselleri) ====================
  var galleryDropzone = null;
  var isEditMode = false;

  function initGalleryDropzone() {
    var dzElement = document.getElementById('galleryDropzone');
    if (!dzElement || typeof Dropzone === 'undefined') return;

    var uploadUrl = dzElement.getAttribute('data-upload-url');
    isEditMode = !!uploadUrl;

    var csrfToken = document.querySelector('meta[name="csrf-token"]');

    var config = {
      paramName: 'file',
      maxFilesize: 2,
      maxFiles: 8,
      parallelUploads: 8,
      acceptedFiles: 'image/jpeg,image/png,image/webp',
      addRemoveLinks: true,
      dictRemoveFile: 'Kaldır',
      dictCancelUpload: 'İptal',
      dictFileTooBig: 'Dosya çok büyük ({{filesize}}MB). Maks: {{maxFilesize}}MB.',
      dictInvalidFileType: 'Bu dosya türü desteklenmiyor.',
      dictMaxFilesExceeded: 'En fazla {{maxFiles}} dosya yüklenebilir.',
      dictResponseError: 'Sunucu hatası: {{statusCode}}',
      thumbnailWidth: 80,
      thumbnailHeight: 80,
      thumbnailMethod: 'crop',
      headers: csrfToken ? { 'X-CSRF-TOKEN': csrfToken.content } : {},

      init: function () {
        var myDropzone = this;

        // CSRF token for every request
        this.on('sending', function (file, xhr, formData) {
          if (csrfToken) {
            formData.append('_token', csrfToken.content);
          }
        });

        this.on('success', function (file, response) {
          // Store server-side image ID for later deletion
          if (response && response.id) {
            file.imageId = response.id;
          }
          showToast(file.name + ' başarıyla yüklendi.', 'success');
        });

        this.on('error', function (file, message) {
          var msg = typeof message === 'string' ? message : (message.message || 'Yükleme hatası');
          showToast(msg, 'error');
        });

        this.on('removedfile', function (file) {
          var imageId = file.imageId;
          if (imageId) {
            deleteImageFromServer(imageId);
          }
        });

        // Load existing images in edit mode
        loadExistingImages(myDropzone);
      }
    };

    if (isEditMode) {
      // Edit mode: auto upload immediately
      config.url = uploadUrl;
      config.autoProcessQueue = true;
    } else {
      // Create mode: queue files, submit with form
      config.url = '#';
      config.autoProcessQueue = false;
    }

    galleryDropzone = new Dropzone('#galleryDropzone', config);

    // Init SortableJS on the Dropzone preview container
    initDropzoneSortable();
  }

  function initDropzoneSortable() {
    if (typeof Sortable === 'undefined' || !galleryDropzone) return;

    var dzElement = document.getElementById('galleryDropzone');
    if (!dzElement) return;

    // Sortable needs the preview container — Dropzone uses the main element itself
    var previewContainer = dzElement;

    Sortable.create(previewContainer, {
      draggable: '.dz-preview',
      ghostClass: 'dz-sortable-ghost',
      chosenClass: 'dz-sortable-chosen',
      animation: 150,
      filter: '.dz-message',
      onEnd: function () {
        // Reorder the internal files array to match DOM order
        var previews = previewContainer.querySelectorAll('.dz-preview');
        var newOrder = [];
        previews.forEach(function (preview) {
          // Find matching file
          for (var i = 0; i < galleryDropzone.files.length; i++) {
            if (galleryDropzone.files[i].previewElement === preview) {
              newOrder.push(galleryDropzone.files[i]);
              break;
            }
          }
        });
        galleryDropzone.files = newOrder;

        // In edit mode, persist order to server
        if (isEditMode) {
          saveImageOrder();
        }
      }
    });
  }

  function saveImageOrder() {
    var dzElement = document.getElementById('galleryDropzone');
    if (!dzElement) return;

    var reorderUrl = dzElement.getAttribute('data-reorder-url');
    if (!reorderUrl) return;

    var csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (!csrfToken) return;

    // Build ordered array of image IDs
    var imageIds = [];
    galleryDropzone.files.forEach(function (file) {
      if (file.imageId) {
        imageIds.push(file.imageId);
      }
    });

    if (imageIds.length === 0) return;

    fetch(reorderUrl, {
      method: 'PATCH',
      headers: {
        'X-CSRF-TOKEN': csrfToken.content,
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({ image_ids: imageIds })
    })
    .then(function (response) { return response.json(); })
    .then(function (data) {
      if (data.success) {
        showToast('Görsel sıralaması güncellendi.', 'success');
      }
    })
    .catch(function () {
      showToast('Sıralama kaydedilirken hata oluştu.', 'error');
    });
  }

  function loadExistingImages(dz) {
    var dzElement = document.getElementById('galleryDropzone');
    if (!dzElement) return;

    var existingData = dzElement.getAttribute('data-existing-images');
    if (!existingData) return;

    var images = JSON.parse(existingData);
    images.forEach(function (img) {
      var mockFile = {
        name: img.name || 'Görsel',
        size: 12345,
        imageId: img.id,
        isExisting: true,
        accepted: true
      };
      dz.files.push(mockFile);
      dz.emit('addedfile', mockFile);
      dz.emit('thumbnail', mockFile, img.url);
      dz.emit('complete', mockFile);
      mockFile.previewElement.classList.add('dz-success', 'dz-existing');
    });
  }

  function deleteImageFromServer(imageId) {
    var csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (!csrfToken) return;

    var baseUrl = document.querySelector('meta[name="base-url"]');
    var url = (baseUrl ? baseUrl.content : '') + '/admin/products/images/' + imageId;

    fetch(url, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': csrfToken.content,
        'Accept': 'application/json'
      }
    })
    .then(function (response) { return response.json(); })
    .then(function (data) {
      if (data.success) {
        showToast('Görsel silindi.', 'success');
      }
    })
    .catch(function () {
      showToast('Görsel silinirken hata oluştu.', 'error');
    });
  }

  // Sync dropzone files to hidden input before form submit (create mode only)
  function syncDropzoneToInput() {
    if (!galleryDropzone || isEditMode) return;

    var dt = new DataTransfer();
    galleryDropzone.getAcceptedFiles().forEach(function (file) {
      if (!file.isExisting) {
        dt.items.add(file);
      }
    });

    var hiddenInput = document.getElementById('galleryInput');
    if (hiddenInput) {
      hiddenInput.files = dt.files;
    }
  }


  // ==================== SEO PREVIEW ====================
  window.updateSeoPreview = function () {
    var titleEl = document.getElementById('seoTitle');
    var descEl = document.getElementById('seoDescription');

    var previewTitle = document.getElementById('seoPreviewTitle');
    var previewDesc = document.getElementById('seoPreviewDesc');

    if (previewTitle && titleEl) previewTitle.textContent = titleEl.value || 'Ürün Adı | Site Adı';
    if (previewDesc && descEl) previewDesc.textContent = descEl.value || 'Ürünün meta açıklaması burada görünecektir...';
  };


  // ==================== RICH TEXT EDITOR ====================
  window.execFormat = function (command) {
    document.execCommand(command, false, null);
    var editor = document.getElementById('productEditor');
    if (editor) editor.focus();
  };

  window.execHeading = function (tag) {
    if (tag) {
      document.execCommand('formatBlock', false, '<' + tag + '>');
    } else {
      document.execCommand('formatBlock', false, '<p>');
    }
    var editor = document.getElementById('productEditor');
    if (editor) editor.focus();
  };

  window.insertLink = function () {
    var url = prompt('Bağlantı URL\'si girin:');
    if (url) document.execCommand('createLink', false, url);
  };

  window.insertEditorImage = function () {
    var url = prompt('Görsel URL\'si girin:');
    if (url) document.execCommand('insertImage', false, url);
  };

  window.insertTable = function () {
    var html = '<table class="table table-bordered"><tbody><tr><td>Hücre 1</td><td>Hücre 2</td></tr><tr><td>Hücre 3</td><td>Hücre 4</td></tr></tbody></table>';
    document.execCommand('insertHTML', false, html);
  };

  // Sync editor content to hidden textarea before form submit
  function syncEditorToTextarea() {
    var editor = document.getElementById('productEditor');
    var textarea = document.getElementById('descriptionField');
    if (editor && textarea) {
      textarea.value = editor.innerHTML;
    }
  }

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


  // ==================== TOAST ====================
  function showToast(message, type) {
    if (typeof type === 'undefined') type = 'success';
    var titleMap = { success: 'Başarılı', error: 'Hata', danger: 'Hata', warning: 'Uyarı', info: 'Bilgi' };
    var modalType = type === 'error' ? 'danger' : type;
    if (typeof AdminModal !== 'undefined') {
      AdminModal.status({ title: titleMap[type] || 'Bilgi', message: message, type: modalType });
    }
  }
  window.showToast = showToast;


  // Clear nutrition inputs when toggle is off (prevent sending empty data)
  function cleanNutritionIfDisabled() {
    var toggle = document.getElementById('hasNutrition');
    if (toggle && !toggle.checked) {
      var tbody = document.getElementById('nutritionTableBody');
      if (tbody) {
        var inputs = tbody.querySelectorAll('input[name]');
        inputs.forEach(function (input) { input.removeAttribute('name'); });
      }
    }
  }


  // ==================== FORM SUBMIT ====================
  function initFormSubmit() {
    var form = document.getElementById('productForm');
    if (!form) return;

    form.addEventListener('submit', function () {
      syncEditorToTextarea();
      syncDropzoneToInput();
      cleanFeaturesIfDisabled();
      cleanNutritionIfDisabled();
      cleanShippingIfDisabled();
    });
  }


  // ==================== INIT ====================
  function init() {
    initNavObserver();
    initWordCounter();
    initFormSubmit();
    initGalleryDropzone();
    initFeatureIndex();
    initNutritionIndex();
    initShippingIndex();

    // Init char counters for existing values (edit mode)
    var fieldsWithCounters = [
      { id: 'productName', max: 150 },
      { id: 'productExcerpt', max: 300 },
      { id: 'seoTitle', max: 60 },
      { id: 'seoDescription', max: 160 }
    ];
    fieldsWithCounters.forEach(function (f) {
      var el = document.getElementById(f.id);
      if (el && el.value) updateCharCounter(el, f.max);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
