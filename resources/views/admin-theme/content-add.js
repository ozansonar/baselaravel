// ==================== CONTENT ADD PAGE - JavaScript ====================

(function () {
  'use strict';

  // ==================== SLUG GENERATOR ====================
  window.generateSlug = function (title) {
    var charMap = {
      'ç': 'c', 'ğ': 'g', 'ı': 'i', 'ö': 'o', 'ş': 's', 'ü': 'u',
      'Ç': 'c', 'Ğ': 'g', 'İ': 'i', 'Ö': 'o', 'Ş': 's', 'Ü': 'u'
    };
    var slug = title.split('').map(function (ch) { return charMap[ch] || ch; }).join('');
    slug = slug.toLowerCase().replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
    document.getElementById('contentSlug').value = slug;
    document.getElementById('seoPreviewSlug').textContent = slug || 'yeni-icerik';
    updateSeoPreview();
  };


  // ==================== CHARACTER COUNTER ====================
  window.updateCharCounter = function (el, max) {
    var counter = document.getElementById(el.id + '-counter');
    if (counter) {
      var len = el.value.length;
      counter.textContent = len;
      counter.style.color = len > max ? 'var(--neon-red)' : '';
    }
  };


  // ==================== SUBCATEGORIES ====================
  var subcategories = {
    teknoloji: ['Yapay Zeka', 'Blockchain', 'IoT', 'Siber Güvenlik', 'Bulut Bilişim'],
    yazilim: ['Web Geliştirme', 'Mobil Uygulama', 'DevOps', 'Veritabanı', 'API Geliştirme'],
    tasarim: ['UI/UX', 'Grafik Tasarım', 'Web Tasarım', 'Marka Kimliği', '3D Modelleme'],
    pazarlama: ['SEO', 'Sosyal Medya', 'İçerik Pazarlama', 'E-posta', 'Analitik'],
    egitim: ['Online Kurslar', 'Workshop', 'Sertifika', 'Mentorluk', 'Bootcamp'],
    yasam: ['Sağlık', 'Seyahat', 'Kariyer', 'Kişisel Gelişim', 'Finans'],
    bilim: ['Fizik', 'Biyoloji', 'Uzay', 'Matematik', 'Çevre']
  };

  window.loadSubcategories = function (category) {
    var select = document.getElementById('contentSubcategory');
    select.innerHTML = '<option value="">Alt kategori seçin...</option>';
    if (category && subcategories[category]) {
      subcategories[category].forEach(function (sub) {
        var opt = document.createElement('option');
        opt.value = sub.toLowerCase().replace(/[^a-z0-9]/g, '-');
        opt.textContent = sub;
        select.appendChild(opt);
      });
      select.disabled = false;
    } else {
      select.disabled = true;
    }
  };


  // ==================== TAGS ====================
  var tags = [];
  var keywords = [];

  window.handleTagInput = function (e) {
    if (e.key === 'Enter' || e.key === ',') {
      e.preventDefault();
      var val = e.target.value.replace(/,/g, '').trim();
      if (val && tags.length < 10 && !tags.includes(val)) {
        tags.push(val);
        renderTags();
      }
      e.target.value = '';
    }
  };

  function renderTags() {
    var container = document.getElementById('tagsContainer');
    container.innerHTML = tags.map(function (tag, i) {
      return '<span class="ca-tag">' + escapeHtml(tag) + ' <span class="ca-tag-remove" onclick="removeTag(' + i + ')">&times;</span></span>';
    }).join('');
  }

  window.removeTag = function (index) {
    tags.splice(index, 1);
    renderTags();
  };

  window.handleKeywordInput = function (e) {
    if (e.key === 'Enter' || e.key === ',') {
      e.preventDefault();
      var val = e.target.value.replace(/,/g, '').trim();
      if (val && keywords.length < 5 && !keywords.includes(val)) {
        keywords.push(val);
        renderKeywords();
      }
      e.target.value = '';
    }
  };

  function renderKeywords() {
    var container = document.getElementById('keywordsContainer');
    container.innerHTML = keywords.map(function (kw, i) {
      return '<span class="ca-tag">' + escapeHtml(kw) + ' <span class="ca-tag-remove" onclick="removeKeyword(' + i + ')">&times;</span></span>';
    }).join('');
  }

  window.removeKeyword = function (index) {
    keywords.splice(index, 1);
    renderKeywords();
  };


  // ==================== CONTENT TYPE ====================
  window.selectContentType = function (el, type) {
    document.querySelectorAll('.ca-type-card').forEach(function (c) { c.classList.remove('selected'); });
    el.classList.add('selected');
  };


  // ==================== RICH TEXT EDITOR ====================
  window.execFormat = function (cmd) {
    document.execCommand(cmd, false, null);
    document.getElementById('contentEditor').focus();
  };

  window.execHeading = function (tag) {
    if (tag) {
      document.execCommand('formatBlock', false, '<' + tag + '>');
    } else {
      document.execCommand('formatBlock', false, '<p>');
    }
    document.getElementById('contentEditor').focus();
  };

  window.insertBlockquote = function () {
    document.execCommand('formatBlock', false, '<blockquote>');
  };

  window.insertLink = function () {
    var url = prompt('Bağlantı URL\'si girin:', 'https://');
    if (url) {
      document.execCommand('createLink', false, url);
    }
  };

  window.insertImage = function () {
    var url = prompt('Görsel URL\'si girin:', 'https://');
    if (url) {
      document.execCommand('insertImage', false, url);
    }
  };

  window.insertCode = function () {
    document.execCommand('formatBlock', false, '<pre>');
  };

  window.insertTable = function () {
    var table = '<table style="width:100%;border-collapse:collapse;margin:16px 0"><tr><th style="border:1px solid rgba(255,255,255,0.1);padding:10px">Başlık 1</th><th style="border:1px solid rgba(255,255,255,0.1);padding:10px">Başlık 2</th><th style="border:1px solid rgba(255,255,255,0.1);padding:10px">Başlık 3</th></tr><tr><td style="border:1px solid rgba(255,255,255,0.1);padding:10px">Veri 1</td><td style="border:1px solid rgba(255,255,255,0.1);padding:10px">Veri 2</td><td style="border:1px solid rgba(255,255,255,0.1);padding:10px">Veri 3</td></tr></table>';
    document.execCommand('insertHTML', false, table);
  };

  window.toggleFullscreen = function () {
    var wrapper = document.querySelector('.ca-editor-wrapper');
    wrapper.classList.toggle('ca-editor-fullscreen');
  };

  // Word count & read time
  var editor = document.getElementById('contentEditor');
  if (editor) {
    editor.addEventListener('input', function () {
      var text = editor.innerText.trim();
      var words = text ? text.split(/\s+/).length : 0;
      document.getElementById('wordCount').textContent = words;
      document.getElementById('readTime').textContent = Math.max(1, Math.ceil(words / 200));
    });
    // Clear placeholder on first focus
    editor.addEventListener('focus', function () {
      if (editor.innerHTML.indexOf('İçeriğinizi buraya') !== -1) {
        editor.innerHTML = '';
      }
    }, { once: true });
  }


  // ==================== DROPZONE.JS (İçerik Dosyaları) ====================
  Dropzone.autoDiscover = false;

  var contentDropzone = new Dropzone('#contentDropzone', {
    url: '/api/upload',
    paramName: 'file',
    maxFilesize: 10,              // 10 MB
    maxFiles: 20,
    uploadMultiple: false,         // Her dosya ayrı istek
    parallelUploads: 2,
    acceptedFiles: 'image/jpeg,image/png,image/gif,image/webp,application/pdf,.doc,.docx',
    addRemoveLinks: true,
    dictRemoveFile: 'Kaldır',
    dictCancelUpload: 'İptal',
    dictFileTooBig: 'Dosya çok büyük ({{filesize}}MB). Maks: {{maxFilesize}}MB.',
    dictInvalidFileType: 'Bu dosya türü desteklenmiyor.',
    dictMaxFilesExceeded: 'En fazla {{maxFiles}} dosya yüklenebilir.',
    dictResponseError: 'Sunucu hatası: {{statusCode}}',

    // Thumbnail ayarları
    thumbnailWidth: 80,
    thumbnailHeight: 80,
    thumbnailMethod: 'crop',

    // Her dosya eklendiğinde data-type attribute'u ekle (CSS için)
    init: function () {
      this.on('addedfile', function (file) {
        var ext = file.name.split('.').pop().toLowerCase();
        file.previewElement.setAttribute('data-type', ext);

        // Görsel olmayan dosyalar için özel ikon
        if (!file.type.match(/image.*/)) {
          var iconHtml = '<i class="bi bi-file-earmark" style="font-size:28px;color:var(--text-muted)"></i>';
          if (ext === 'pdf') {
            iconHtml = '<i class="bi bi-file-earmark-pdf" style="font-size:28px;color:var(--neon-red)"></i>';
          } else if (ext === 'doc' || ext === 'docx') {
            iconHtml = '<i class="bi bi-file-earmark-word" style="font-size:28px;color:var(--neon-blue)"></i>';
          }
          var imgEl = file.previewElement.querySelector('.dz-image');
          if (imgEl) {
            imgEl.innerHTML = iconHtml;
          }
        }
      });

      this.on('sending', function (file, xhr, formData) {
        // CSRF token ekle (Laravel ile kullanım için)
        var csrfToken = document.querySelector('meta[name="csrf-token"]');
        if (csrfToken) {
          formData.append('_token', csrfToken.getAttribute('content'));
        }
      });

      this.on('success', function (file, response) {
        showToast(file.name + ' başarıyla yüklendi.', 'success');
      });

      this.on('error', function (file, message) {
        // Sunucu yoksa bile önizleme kalsın (demo mod)
        if (typeof message === 'string' && message.indexOf('XMLHttpRequest') !== -1) {
          file.previewElement.querySelector('.dz-error-message span').textContent = '';
          file.previewElement.classList.remove('dz-error');
          file.previewElement.classList.add('dz-success');
        } else if (typeof message === 'string') {
          showToast(message, 'error');
        }
      });

      this.on('removedfile', function (file) {
        showToast(file.name + ' kaldırıldı.', 'info');
      });
    }
  });


  // ==================== SEO PREVIEW ====================
  window.updateSeoPreview = function () {
    var title = document.getElementById('metaTitle').value || document.getElementById('contentTitle').value || 'İçerik Başlığı Buraya Gelecek';
    var desc = document.getElementById('metaDescription').value || 'İçeriğinizin meta açıklaması burada görünecek.';
    document.getElementById('seoPreviewTitle').textContent = title;
    document.getElementById('seoPreviewDesc').textContent = desc;

    // Status indicators
    var titleLen = document.getElementById('metaTitle').value.length;
    var descLen = document.getElementById('metaDescription').value.length;

    var titleStatus = document.getElementById('metaTitleStatus');
    var descStatus = document.getElementById('metaDescStatus');

    if (titleLen === 0) { titleStatus.textContent = ''; titleStatus.className = 'ca-seo-status'; }
    else if (titleLen >= 50 && titleLen <= 60) { titleStatus.textContent = 'Mükemmel'; titleStatus.className = 'ca-seo-status good'; }
    else if (titleLen >= 40 && titleLen <= 65) { titleStatus.textContent = 'İyi'; titleStatus.className = 'ca-seo-status warn'; }
    else { titleStatus.textContent = 'Düzenle'; titleStatus.className = 'ca-seo-status bad'; }

    if (descLen === 0) { descStatus.textContent = ''; descStatus.className = 'ca-seo-status'; }
    else if (descLen >= 120 && descLen <= 160) { descStatus.textContent = 'Mükemmel'; descStatus.className = 'ca-seo-status good'; }
    else if (descLen >= 100 && descLen <= 170) { descStatus.textContent = 'İyi'; descStatus.className = 'ca-seo-status warn'; }
    else { descStatus.textContent = 'Düzenle'; descStatus.className = 'ca-seo-status bad'; }
  };


  // ==================== PUBLISH SETTINGS ====================
  window.toggleScheduleDate = function (status) {
    var show = status === 'scheduled';
    document.getElementById('scheduleDateWrapper').classList.toggle('d-none', !show);
    document.getElementById('scheduleTimeWrapper').classList.toggle('d-none', !show);
  };

  window.togglePasswordField = function (visibility) {
    document.getElementById('passwordFieldWrapper').classList.toggle('d-none', visibility !== 'password');
  };


  // ==================== SECTION NAVIGATION ====================
  window.scrollToSection = function (id, el) {
    var target = document.getElementById(id);
    if (target) {
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    document.querySelectorAll('.stg-nav-item').forEach(function (item) { item.classList.remove('active'); });
    if (el) el.classList.add('active');
  };

  // Auto-highlight nav on scroll
  var sections = ['section-basic', 'section-content', 'section-media', 'section-seo', 'section-publish', 'section-advanced'];
  var navItems = document.querySelectorAll('.stg-nav-item');

  function onScroll() {
    var scrollPos = window.scrollY + 140;
    for (var i = sections.length - 1; i >= 0; i--) {
      var el = document.getElementById(sections[i]);
      if (el && el.offsetTop <= scrollPos) {
        navItems.forEach(function (n) { n.classList.remove('active'); });
        if (navItems[i]) navItems[i].classList.add('active');
        break;
      }
    }
  }
  window.addEventListener('scroll', onScroll);


  // ==================== FORM ACTIONS ====================
  window.saveDraft = function () {
    showToast('Taslak başarıyla kaydedildi!', 'success');
  };

  window.previewContent = function () {
    showToast('Önizleme yeni sekmede açılıyor...', 'info');
  };

  window.publishContent = function () {
    var title = document.getElementById('contentTitle').value.trim();
    var category = document.getElementById('contentCategory').value;

    if (!title) {
      showToast('Lütfen içerik başlığını girin!', 'error');
      document.getElementById('contentTitle').focus();
      return;
    }
    if (!category) {
      showToast('Lütfen bir kategori seçin!', 'error');
      document.getElementById('contentCategory').focus();
      return;
    }

    showToast('İçerik başarıyla yayınlandı!', 'success');
  };

  window.resetForm = function () {
    if (confirm('Tüm form alanları sıfırlanacak. Emin misiniz?')) {
      document.querySelectorAll('.stg-input, .stg-textarea, .stg-select').forEach(function (el) {
        if (el.tagName === 'SELECT') el.selectedIndex = 0;
        else el.value = '';
      });
      tags = [];
      keywords = [];
      renderTags();
      renderKeywords();
      contentDropzone.removeAllFiles(true);
      document.getElementById('coverInput').value = '';
      document.getElementById('contentEditor').innerHTML = '<p>İçeriğinizi buraya yazmaya başlayın...</p>';
      document.getElementById('wordCount').textContent = '0';
      document.getElementById('readTime').textContent = '0';
      showToast('Form sıfırlandı.', 'info');
    }
  };


  // ==================== TOAST ====================
  function showToast(message, type) {
    var existing = document.querySelector('.ca-toast');
    if (existing) existing.remove();

    var iconMap = {
      success: 'bi-check-circle-fill',
      error: 'bi-exclamation-circle-fill',
      info: 'bi-info-circle-fill'
    };

    var toast = document.createElement('div');
    toast.className = 'ca-toast ca-toast-' + type;
    toast.innerHTML = '<i class="bi ' + (iconMap[type] || iconMap.info) + '"></i> ' + message;
    document.body.appendChild(toast);

    requestAnimationFrame(function () {
      toast.classList.add('show');
    });

    setTimeout(function () {
      toast.classList.remove('show');
      setTimeout(function () { toast.remove(); }, 300);
    }, 3000);
  }


  // ==================== UTILITY ====================
  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

})();
