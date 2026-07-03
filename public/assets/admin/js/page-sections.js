(function () {
  'use strict';

  /* ==================== REPEATER TEMPLATES ==================== */

  var templates = {
    values: function (index) {
      return '<div class="repeater-item border rounded p-3 mb-3" data-index="' + index + '">' +
        '<div class="d-flex justify-content-between align-items-center mb-3">' +
          '<span class="badge bg-teal">Değer #' + (index + 1) + '</span>' +
          '<button type="button" class="btn-glass btn-sm text-danger" onclick="removeRepeaterItem(this)">' +
            '<i class="bi bi-trash"></i>' +
          '</button>' +
        '</div>' +
        '<div class="row g-3">' +
          '<div class="col-12 col-md-4">' +
            '<label class="form-label">İkon</label>' +
            '<input type="text" class="form-control" name="sections[values][' + index + '][icon]" placeholder="fa-solid fa-seedling">' +
            '<div class="form-text">FontAwesome ikon class\'ı</div>' +
          '</div>' +
          '<div class="col-12 col-md-8">' +
            '<label class="form-label">Başlık</label>' +
            '<input type="text" class="form-control" name="sections[values][' + index + '][title]" placeholder="Ör: %100 Doğal">' +
          '</div>' +
          '<div class="col-12">' +
            '<label class="form-label">Açıklama</label>' +
            '<textarea class="form-control" name="sections[values][' + index + '][description]" rows="2" placeholder="Değer açıklaması..."></textarea>' +
          '</div>' +
        '</div>' +
      '</div>';
    },

    timeline: function (index) {
      return '<div class="repeater-item border rounded p-3 mb-3" data-index="' + index + '">' +
        '<div class="d-flex justify-content-between align-items-center mb-3">' +
          '<span class="badge bg-teal">Dönem #' + (index + 1) + '</span>' +
          '<button type="button" class="btn-glass btn-sm text-danger" onclick="removeRepeaterItem(this)">' +
            '<i class="bi bi-trash"></i>' +
          '</button>' +
        '</div>' +
        '<div class="row g-3">' +
          '<div class="col-12 col-md-3">' +
            '<label class="form-label">Yıl</label>' +
            '<input type="text" class="form-control" name="sections[timeline][' + index + '][year]" placeholder="Ör: 1975">' +
          '</div>' +
          '<div class="col-12 col-md-9">' +
            '<label class="form-label">Başlık</label>' +
            '<input type="text" class="form-control" name="sections[timeline][' + index + '][title]" placeholder="Ör: Kuruluş">' +
          '</div>' +
          '<div class="col-12">' +
            '<label class="form-label">Açıklama</label>' +
            '<textarea class="form-control" name="sections[timeline][' + index + '][description]" rows="2" placeholder="Bu dönemin açıklaması..."></textarea>' +
          '</div>' +
        '</div>' +
      '</div>';
    },

    stats: function (index) {
      return '<div class="repeater-item border rounded p-3 mb-3" data-index="' + index + '">' +
        '<div class="d-flex justify-content-between align-items-center mb-3">' +
          '<span class="badge bg-teal">İstatistik #' + (index + 1) + '</span>' +
          '<button type="button" class="btn-glass btn-sm text-danger" onclick="removeRepeaterItem(this)">' +
            '<i class="bi bi-trash"></i>' +
          '</button>' +
        '</div>' +
        '<div class="row g-3">' +
          '<div class="col-12 col-md-3">' +
            '<label class="form-label">Sayı</label>' +
            '<input type="number" class="form-control" name="sections[stats][' + index + '][number]" placeholder="Ör: 50" min="0">' +
          '</div>' +
          '<div class="col-12 col-md-3">' +
            '<label class="form-label">Son Ek</label>' +
            '<input type="text" class="form-control" name="sections[stats][' + index + '][suffix]" placeholder="Ör: +, K+">' +
          '</div>' +
          '<div class="col-12 col-md-3">' +
            '<label class="form-label">Etiket</label>' +
            '<input type="text" class="form-control" name="sections[stats][' + index + '][label]" placeholder="Ör: Yıllık Deneyim">' +
          '</div>' +
          '<div class="col-12 col-md-3">' +
            '<label class="form-label">Format</label>' +
            '<select class="form-select" name="sections[stats][' + index + '][format]">' +
              '<option value="">Normal</option>' +
              '<option value="K">K (binler)</option>' +
            '</select>' +
            '<div class="form-text">15000 → 15K</div>' +
          '</div>' +
        '</div>' +
      '</div>';
    },

    team: function (index) {
      return '<div class="repeater-item border rounded p-3 mb-3" data-index="' + index + '">' +
        '<div class="d-flex justify-content-between align-items-center mb-3">' +
          '<span class="badge bg-teal">Üye #' + (index + 1) + '</span>' +
          '<button type="button" class="btn-glass btn-sm text-danger" onclick="removeRepeaterItem(this)">' +
            '<i class="bi bi-trash"></i>' +
          '</button>' +
        '</div>' +
        '<div class="row g-3">' +
          '<div class="col-12 col-md-6">' +
            '<label class="form-label">Ad Soyad</label>' +
            '<input type="text" class="form-control" name="sections[team][' + index + '][name]" placeholder="Ör: Orhan Yılmaz">' +
          '</div>' +
          '<div class="col-12 col-md-6">' +
            '<label class="form-label">Rol / Unvan</label>' +
            '<input type="text" class="form-control" name="sections[team][' + index + '][role]" placeholder="Ör: Kurucu Baba">' +
          '</div>' +
          '<div class="col-12">' +
            '<label class="form-label">Açıklama</label>' +
            '<textarea class="form-control" name="sections[team][' + index + '][description]" rows="2" placeholder="Kısa tanıtım metni..."></textarea>' +
          '</div>' +
          '<div class="col-12">' +
            '<label class="form-label">Fotoğraf</label>' +
            '<input type="file" class="form-control" name="sections[team][' + index + '][photo_file]" accept="image/png,image/jpeg,image/webp">' +
            '<div class="form-text">PNG, JPG, WebP | Maks. 2 MB</div>' +
          '</div>' +
        '</div>' +
      '</div>';
    }
  };

  /* ==================== ADD REPEATER ITEM ==================== */

  window.addRepeaterItem = function (type) {
    var container = document.getElementById('repeater-' + type);
    if (!container) return;

    // Remove empty message
    var emptyMsg = document.getElementById(type + '-empty');
    if (emptyMsg) emptyMsg.remove();

    // Find next index
    var items = container.querySelectorAll('.repeater-item');
    var nextIndex = 0;
    items.forEach(function (item) {
      var idx = parseInt(item.getAttribute('data-index'), 10);
      if (idx >= nextIndex) nextIndex = idx + 1;
    });

    // Create and append new item
    var templateFn = templates[type];
    if (!templateFn) return;

    var temp = document.createElement('div');
    temp.innerHTML = templateFn(nextIndex);
    var newItem = temp.firstElementChild;
    container.appendChild(newItem);

    // Scroll to new item
    newItem.scrollIntoView({ behavior: 'smooth', block: 'center' });

    // Focus first input
    var firstInput = newItem.querySelector('input, textarea');
    if (firstInput) {
      setTimeout(function () { firstInput.focus(); }, 300);
    }
  };

  /* ==================== REMOVE REPEATER ITEM ==================== */

  window.removeRepeaterItem = function (btn) {
    var item = btn.closest('.repeater-item');
    if (!item) return;

    var container = item.parentElement;
    item.remove();

    // Re-index remaining items
    reindexRepeater(container);
  };

  /* ==================== RE-INDEX REPEATER ==================== */

  function reindexRepeater(container) {
    var items = container.querySelectorAll('.repeater-item');
    var type = container.id.replace('repeater-', '');

    items.forEach(function (item, newIndex) {
      item.setAttribute('data-index', newIndex);

      // Update badge text
      var badge = item.querySelector('.badge');
      if (badge) {
        var labelMap = { values: 'Değer', timeline: 'Dönem', stats: 'İstatistik', team: 'Üye' };
        badge.textContent = (labelMap[type] || 'Öğe') + ' #' + (newIndex + 1);
      }

      // Update name attributes
      var inputs = item.querySelectorAll('input, textarea, select');
      inputs.forEach(function (input) {
        var name = input.getAttribute('name');
        if (name) {
          input.setAttribute('name', name.replace(
            /sections\[\w+\]\[\d+\]/,
            'sections[' + type + '][' + newIndex + ']'
          ));
        }
      });
    });
  }

})();
