(function () {
  'use strict';

  /* ==================== GUIDE CONTENT ==================== */
  var guideContent = {
    orders: {
      title: '<i class="bi bi-cart-check me-2"></i>Sipariş Yönetimi',
      body: `
        <div class="hlp-guide-steps">
          <div class="hlp-step">
            <div class="hlp-step-num">1</div>
            <div class="hlp-step-body">
              <h6>Siparişler Sayfasını Açın</h6>
              <p>Sol menüden <strong>Siparişler</strong> bağlantısına tıklayın. Tüm sipariş listesi görüntülenir.</p>
            </div>
          </div>
          <div class="hlp-step">
            <div class="hlp-step-num">2</div>
            <div class="hlp-step-body">
              <h6>Sipariş Bulma</h6>
              <p>Arama kutusuna sipariş numarası, müşteri adı veya e-posta girin. Ödeme yöntemi, tarih ve tutar filtrelerini kullanabilirsiniz.</p>
            </div>
          </div>
          <div class="hlp-step">
            <div class="hlp-step-num">3</div>
            <div class="hlp-step-body">
              <h6>Sipariş Durumunu Güncelleme</h6>
              <p>Satır sonundaki <i class="bi bi-arrow-repeat"></i> ikonuna tıklayın. Bekleyen → Hazırlanıyor → Kargoda → Teslim Edildi adımlarını takip edin.</p>
            </div>
          </div>
          <div class="hlp-step">
            <div class="hlp-step-num">4</div>
            <div class="hlp-step-body">
              <h6>Fatura Oluşturma</h6>
              <p><i class="bi bi-receipt"></i> ikonuna tıklayın. Fatura modalında <strong>Yazdır</strong> veya <strong>PDF İndir</strong> seçeneğini kullanın.</p>
            </div>
          </div>
          <div class="hlp-step">
            <div class="hlp-step-num">5</div>
            <div class="hlp-step-body">
              <h6>Toplu İşlem</h6>
              <p>Birden fazla siparişi seçip üstteki bulk action butonlarıyla toplu durum güncellemesi veya iptal yapabilirsiniz.</p>
            </div>
          </div>
        </div>
        <div class="hlp-guide-tip">
          <i class="bi bi-lightbulb-fill text-neon-orange me-2"></i>
          <span><strong>İpucu:</strong> Durum sekmelerini (Bekleyen, Kargoda vb.) kullanarak siparişleri hızlıca filtreleyin.</span>
        </div>
        <div class="mt-4">
          <a href="orders.html" class="btn-teal"><i class="bi bi-arrow-right me-1"></i>Siparişler Sayfasına Git</a>
        </div>`
    },
    products: {
      title: '<i class="bi bi-box-seam me-2"></i>Ürün Ekleme ve Yönetimi',
      body: `
        <div class="hlp-guide-steps">
          <div class="hlp-step">
            <div class="hlp-step-num">1</div>
            <div class="hlp-step-body">
              <h6>Yeni Ürün Oluşturma</h6>
              <p>Ürünler menüsünden <strong>Ürün Ekle</strong>'ye tıklayın. Sol navigasyondan 7 bölüm arasında gezinebilirsiniz.</p>
            </div>
          </div>
          <div class="hlp-step">
            <div class="hlp-step-num">2</div>
            <div class="hlp-step-body">
              <h6>Temel Bilgiler</h6>
              <p>Ürün adı, kategori, marka, tür ve açıklamasını girin. Slug otomatik oluşturulur, özelleştirebilirsiniz.</p>
            </div>
          </div>
          <div class="hlp-step">
            <div class="hlp-step-num">3</div>
            <div class="hlp-step-body">
              <h6>Fiyatlandırma</h6>
              <p>Satış fiyatı, karşılaştırma fiyatı ve maliyet girin. Kar marjı ve indirim oranı otomatik hesaplanır.</p>
            </div>
          </div>
          <div class="hlp-step">
            <div class="hlp-step-num">4</div>
            <div class="hlp-step-body">
              <h6>Görsel Yükleme</h6>
              <p>Ana görsel ve galeri görselleri ekleyin. Önerilen format WebP, boyut 800×800 piksel.</p>
            </div>
          </div>
          <div class="hlp-step">
            <div class="hlp-step-num">5</div>
            <div class="hlp-step-body">
              <h6>Varyant Tanımlama</h6>
              <p>Varyantlar bölümünden renk, beden vb. seçenekler ekleyin. Her kombinasyon için ayrı stok ve fiyat girebilirsiniz.</p>
            </div>
          </div>
        </div>
        <div class="hlp-guide-tip">
          <i class="bi bi-lightbulb-fill text-neon-orange me-2"></i>
          <span><strong>İpucu:</strong> SEO bölümünü doldurmak ürününüzün arama motorlarında daha iyi görünmesini sağlar.</span>
        </div>
        <div class="mt-4">
          <a href="product-add.html" class="btn-teal"><i class="bi bi-arrow-right me-1"></i>Ürün Ekleme Sayfasına Git</a>
        </div>`
    },
    reports: {
      title: '<i class="bi bi-file-earmark-bar-graph me-2"></i>Rapor Oluşturma',
      body: `
        <div class="hlp-guide-steps">
          <div class="hlp-step">
            <div class="hlp-step-num">1</div>
            <div class="hlp-step-body">
              <h6>Hızlı Rapor</h6>
              <p>Raporlar sayfasında rapor türü kartını bulun ve <strong>Oluştur</strong> butonuna tıklayın. Seçilen format otomatik indirilir.</p>
            </div>
          </div>
          <div class="hlp-step">
            <div class="hlp-step-num">2</div>
            <div class="hlp-step-body">
              <h6>Özel Rapor</h6>
              <p>Sağ üstteki <strong>Özel Rapor</strong> butonuyla tarih aralığı, sütunlar ve filtreler seçebilirsiniz.</p>
            </div>
          </div>
          <div class="hlp-step">
            <div class="hlp-step-num">3</div>
            <div class="hlp-step-body">
              <h6>Rapor Planlama</h6>
              <p>Herhangi bir rapor kartının <i class="bi bi-alarm"></i> ikonuyla otomatik raporu planlayın. Sıklık, gün, saat ve e-posta belirleyin.</p>
            </div>
          </div>
          <div class="hlp-step">
            <div class="hlp-step-num">4</div>
            <div class="hlp-step-body">
              <h6>Dışa Aktarma Formatları</h6>
              <p>PDF, Excel ve CSV formatlarında dışa aktarım desteklenir. Finansal raporlar için PDF, veri analizi için Excel önerilir.</p>
            </div>
          </div>
        </div>
        <div class="hlp-guide-tip">
          <i class="bi bi-lightbulb-fill text-neon-orange me-2"></i>
          <span><strong>İpucu:</strong> Haftalık satış raporunu her Pazartesi otomatik planlamak, haftalık toplantılara hazırlık için idealdir.</span>
        </div>
        <div class="mt-4">
          <a href="reports.html" class="btn-teal"><i class="bi bi-arrow-right me-1"></i>Raporlar Sayfasına Git</a>
        </div>`
    },
    users: {
      title: '<i class="bi bi-people me-2"></i>Kullanıcı ve Yetki Yönetimi',
      body: `
        <div class="hlp-guide-steps">
          <div class="hlp-step">
            <div class="hlp-step-num">1</div>
            <div class="hlp-step-body">
              <h6>Kullanıcı Davet Etme</h6>
              <p>Kullanıcılar sayfasında <strong>Kullanıcı Davet Et</strong> butonuna tıklayın. E-posta ve rol belirleyip gönderin.</p>
            </div>
          </div>
          <div class="hlp-step">
            <div class="hlp-step-num">2</div>
            <div class="hlp-step-body">
              <h6>Rol Atama</h6>
              <p>Hazır roller: <strong>Süper Admin, Yönetici, Editör, Müşteri Hizmetleri, Analist</strong>. Her rolün farklı sayfa erişimi vardır.</p>
            </div>
          </div>
          <div class="hlp-step">
            <div class="hlp-step-num">3</div>
            <div class="hlp-step-body">
              <h6>Kullanıcı Durumu</h6>
              <p>Kullanıcıyı geçici olarak pasif yapabilirsiniz. Pasif kullanıcılar sisteme giriş yapamaz.</p>
            </div>
          </div>
        </div>
        <div class="hlp-guide-tip">
          <i class="bi bi-lightbulb-fill text-neon-orange me-2"></i>
          <span><strong>İpucu:</strong> Minimum yetki prensibini uygulayın — her kullanıcıya yalnızca işi için gereken erişimi verin.</span>
        </div>`
    },
    analytics: {
      title: '<i class="bi bi-bar-chart-line me-2"></i>Analitik Dashboard',
      body: `
        <div class="hlp-guide-steps">
          <div class="hlp-step">
            <div class="hlp-step-num">1</div>
            <div class="hlp-step-body">
              <h6>KPI Kartlarını Okuma</h6>
              <p>Üst 4 kart temel metrikleri gösterir. Yeşil ok artış, kırmızı ok azalışı ifade eder. Önceki döneme göre yüzdelik fark gösterilir.</p>
            </div>
          </div>
          <div class="hlp-step">
            <div class="hlp-step-num">2</div>
            <div class="hlp-step-body">
              <h6>Tarih Aralığı Değiştirme</h6>
              <p>Sağ üstteki tarih seçiciden 7 gün, 30 gün, 90 gün veya 1 yıl aralığını seçin. Tüm grafikler güncellenir.</p>
            </div>
          </div>
          <div class="hlp-step">
            <div class="hlp-step-num">3</div>
            <div class="hlp-step-body">
              <h6>Grafik Etkileşimi</h6>
              <p>Grafik üzerine gelince tooltip açılır. Sağa tıklayarak verileri görebilir, alttaki legend item'larına tıklayarak seriler gizlenebilir.</p>
            </div>
          </div>
          <div class="hlp-step">
            <div class="hlp-step-num">4</div>
            <div class="hlp-step-body">
              <h6>Canlı İzleme</h6>
              <p>"Canlı İzleme" butonuyla anlık ziyaretçi ve dönüşüm verilerini gerçek zamanlı takip edebilirsiniz.</p>
            </div>
          </div>
        </div>
        <div class="hlp-guide-tip">
          <i class="bi bi-lightbulb-fill text-neon-orange me-2"></i>
          <span><strong>İpucu:</strong> Isı haritasını kullanarak hangi saatlerde en fazla sipariş alındığını öğrenin ve stok/kargo planlamasını buna göre yapın.</span>
        </div>
        <div class="mt-4">
          <a href="analytics.html" class="btn-teal"><i class="bi bi-arrow-right me-1"></i>Analitik Sayfasına Git</a>
        </div>`
    },
    settings: {
      title: '<i class="bi bi-gear-wide-connected me-2"></i>Panel Ayarları',
      body: `
        <div class="hlp-guide-steps">
          <div class="hlp-step">
            <div class="hlp-step-num">1</div>
            <div class="hlp-step-body">
              <h6>Profil Güncelleme</h6>
              <p>Ad, e-posta ve profil fotoğrafınızı Ayarlar &gt; Profil bölümünden güncelleyebilirsiniz.</p>
            </div>
          </div>
          <div class="hlp-step">
            <div class="hlp-step-num">2</div>
            <div class="hlp-step-body">
              <h6>Şifre Değiştirme</h6>
              <p>Güvenlik bölümünden mevcut şifrenizi girerek yeni şifre belirleyin. Güçlü şifre için harf + rakam + sembol kombinasyonu kullanın.</p>
            </div>
          </div>
          <div class="hlp-step">
            <div class="hlp-step-num">3</div>
            <div class="hlp-step-body">
              <h6>Bildirim Tercihleri</h6>
              <p>E-posta ve push bildirimlerini özelleştirin. Yeni sipariş, stok uyarısı ve güvenlik bildirimleri ayrı ayrı yönetilebilir.</p>
            </div>
          </div>
        </div>
        <div class="hlp-guide-tip">
          <i class="bi bi-lightbulb-fill text-neon-orange me-2"></i>
          <span><strong>İpucu:</strong> İki faktörlü doğrulamayı aktifleştirerek hesap güvenliğinizi artırın.</span>
        </div>
        <div class="mt-4">
          <a href="settings.html" class="btn-teal"><i class="bi bi-arrow-right me-1"></i>Ayarlar Sayfasına Git</a>
        </div>`
    }
  };

  /* ==================== VIDEO META ==================== */
  var videoMeta = {
    intro:     { title: 'Panel\'e Giriş',  dur: '12:34' },
    orders:    { title: 'Sipariş Akışı',   dur: '18:22' },
    products:  { title: 'Ürün Kataloğu',   dur: '24:17' },
    analytics: { title: 'Veri Analizi',    dur: '31:08' }
  };

  /* ==================== INIT ==================== */
  document.addEventListener('DOMContentLoaded', function () {
    animateStatNums();
  });

  /* ==================== STAT NUMBER ANIMATION ==================== */
  function animateStatNums() {
    document.querySelectorAll('.hlp-stat-num').forEach(function (el) {
      var raw = el.textContent.trim();
      if (raw.indexOf('/') !== -1 || raw.indexOf('%') !== -1) return;
      var target = parseInt(raw, 10);
      if (isNaN(target)) return;
      var duration = 1200;
      var startTime = null;
      function step(ts) {
        if (!startTime) startTime = ts;
        var progress = Math.min((ts - startTime) / duration, 1);
        var eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = Math.floor(eased * target);
        if (progress < 1) requestAnimationFrame(step);
        else el.textContent = target;
      }
      requestAnimationFrame(step);
    });
  }

  /* ==================== SEARCH ==================== */
  window.searchHelp = function () {
    var q = (document.getElementById('helpSearch').value || '').toLowerCase().trim();
    if (!q) return;
    highlightFaqMatches(q);
  };

  window.submitSearch = function () {
    var q = (document.getElementById('helpSearch').value || '').trim();
    if (!q) { showToast('Lütfen bir arama terimi girin', 'warning'); return; }
    highlightFaqMatches(q.toLowerCase());
    scrollToSection('faq');
    showToast('"' + q + '" için sonuçlar gösteriliyor', 'info');
  };

  window.quickSearch = function (term) {
    document.getElementById('helpSearch').value = term;
    submitSearch();
  };

  function highlightFaqMatches(q) {
    document.querySelectorAll('.hlp-accordion-item').forEach(function (item) {
      var text = item.textContent.toLowerCase();
      item.style.display = text.indexOf(q) !== -1 ? '' : 'none';
    });
  }

  /* ==================== SCROLL ==================== */
  window.scrollToSection = function (id) {
    var el = document.getElementById(id);
    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  };

  /* ==================== FAQ CATEGORY FILTER ==================== */
  window.switchFaqCategory = function (cat, btn) {
    document.querySelectorAll('.hlp-faq-tab').forEach(function (t) { t.classList.remove('active'); });
    btn.classList.add('active');

    document.querySelectorAll('.hlp-accordion-item').forEach(function (item) {
      item.style.display = '';
    });

    if (cat === 'all') return;

    document.querySelectorAll('.hlp-accordion-item').forEach(function (item) {
      if (item.getAttribute('data-category') !== cat) {
        item.style.display = 'none';
      }
    });
  };

  /* ==================== GUIDE MODAL ==================== */
  window.openGuideModal = function (key) {
    var data = guideContent[key];
    if (!data) return;
    document.getElementById('guideModalTitle').innerHTML = data.title;
    document.getElementById('guideModalBody').innerHTML  = data.body;
    var modal = new bootstrap.Modal(document.getElementById('guideModal'));
    modal.show();
  };

  window.openGuidesModal = function () {
    showToast('Tüm kılavuzlar yakında mevcut olacak', 'info');
  };

  /* ==================== VIDEO MODAL ==================== */
  window.openVideoModal = function (key) {
    var meta = videoMeta[key];
    if (!meta) return;
    document.getElementById('videoModalTitle').innerHTML      = '<i class="bi bi-play-circle me-2"></i>' + meta.title;
    document.getElementById('videoPlaceholderTitle').textContent = meta.title;
    document.getElementById('videoPlaceholderDur').textContent  = meta.dur;
    var modal = new bootstrap.Modal(document.getElementById('videoModal'));
    modal.show();
  };

  /* ==================== TICKET MODAL ==================== */
  window.openTicketModal = function () {
    var modal = new bootstrap.Modal(document.getElementById('ticketModal'));
    modal.show();
  };

  window.previewTicketFile = function (input) {
    var preview = document.getElementById('ticketFilePreview');
    if (!preview || !input.files || !input.files[0]) return;
    var file = input.files[0];
    preview.innerHTML =
      '<div class="hlp-file-preview"><i class="bi bi-paperclip me-1"></i><span>' +
      file.name + '</span><span class="text-muted ms-2">(' + formatFileSize(file.size) + ')</span></div>';
    preview.classList.remove('d-none');
  };

  function formatFileSize(bytes) {
    if (bytes < 1024)       return bytes + ' B';
    if (bytes < 1048576)    return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
  }

  window.submitTicket = function () {
    var cat   = document.getElementById('ticketCategory').value;
    var subj  = (document.getElementById('ticketSubject').value || '').trim();
    var desc  = (document.getElementById('ticketDescription').value || '').trim();
    var email = (document.getElementById('ticketEmail').value || '').trim();

    if (!cat)   { showToast('Lütfen kategori seçin', 'warning'); return; }
    if (!subj)  { showToast('Lütfen konu başlığı girin', 'warning'); return; }
    if (!desc)  { showToast('Lütfen açıklama girin', 'warning'); return; }
    if (!email) { showToast('Lütfen e-posta adresini girin', 'warning'); return; }

    var btn = document.querySelector('#ticketModal .btn-teal');
    if (btn) {
      var orig = btn.innerHTML;
      btn.innerHTML = '<i class="bi bi-arrow-repeat hlp-spin me-1"></i> Gönderiliyor...';
      btn.disabled  = true;
      setTimeout(function () {
        btn.innerHTML = orig;
        btn.disabled  = false;
        bootstrap.Modal.getInstance(document.getElementById('ticketModal')).hide();
        showToast('Destek talebiniz alındı! Yakında e-posta ile yanıt verilecek.', 'success');
        document.getElementById('ticketSubject').value     = '';
        document.getElementById('ticketDescription').value = '';
      }, 1500);
    }
  };

  /* ==================== LIVE CHAT MODAL ==================== */
  window.openLiveChatModal = function () {
    var modal = new bootstrap.Modal(document.getElementById('liveChatModal'));
    modal.show();
  };

  window.startLiveChat = function () {
    bootstrap.Modal.getInstance(document.getElementById('liveChatModal')).hide();
    showToast('Canlı destek bağlantısı kuruluyor...', 'info');
  };

  /* ==================== EMAIL MODAL ==================== */
  window.openEmailModal = function () {
    showToast('E-posta: destek@ozan.dev', 'info');
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

  window.showHelpToast = showToast;

})();
