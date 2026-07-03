(function () {
  'use strict';

  /* ==================== MESSAGE DATA ==================== */
  var messages = [
    { id:1, folder:'inbox', label:'important', starred:true, unread:true, sender:'Ahmet Yılmaz', email:'ahmet.yilmaz@sirket.com', initials:'AY', color:'indigo', time:'10:24', date:'22 Şubat 2026, 10:24', to:'ben <ozan@sirket.com>', subject:'Yeni proje toplantısı hakkında', preview:'Merhaba Ozan, yarınki toplantı için gündem maddelerini hazırladım. Lütfen bir göz atıp onaylayabilir misin?', body:'<p>Merhaba Ozan,</p><p>Yarınki toplantı için gündem maddelerini hazırladım. Lütfen bir göz atıp onaylayabilir misin?</p><p>Ekip olarak bu hafta ele almamız gereken konular:</p><ol><li>Sprint planlaması ve görev dağılımı</li><li>Yeni müşteri entegrasyon projesi</li><li>Performans optimizasyonu sonuçları</li><li>Q1 hedeflerinin gözden geçirilmesi</li></ol><p>Toplantı süresi yaklaşık 1 saat olacak. Herkesin katılımını bekliyorum.</p><p>Saygılarımla,<br>Ahmet Yılmaz<br>Proje Yöneticisi</p>', attachments:[{name:'gundem_toplanti.pdf',size:'245 KB',icon:'bi-file-earmark-pdf',cls:''},{name:'sprint_plani.xlsx',size:'128 KB',icon:'bi-file-earmark-excel',cls:'xlsx'}] },
    { id:2, folder:'inbox', label:'work', starred:false, unread:true, sender:'Zeynep Kaya', email:'zeynep.kaya@sirket.com', initials:'ZK', color:'teal', time:'09:45', date:'22 Şubat 2026, 09:45', to:'ben <ozan@sirket.com>', subject:'API entegrasyonu tamamlandı', preview:'REST API entegrasyonunu bitirdim. Tüm endpoint\'ler test edildi ve sorunsuz çalışıyor. Staging ortamına deploy...', body:'<p>Selam,</p><p>REST API entegrasyonunu bitirdim. Tüm endpoint\'ler test edildi ve sorunsuz çalışıyor.</p><p>Staging ortamına deploy ettim. Aşağıdaki endpoint\'leri kontrol edebilirsin:</p><ul><li><code>GET /api/v2/users</code> — Kullanıcı listesi</li><li><code>POST /api/v2/orders</code> — Sipariş oluşturma</li><li><code>PUT /api/v2/products/:id</code> — Ürün güncelleme</li><li><code>DELETE /api/v2/sessions/:id</code> — Oturum silme</li></ul><p>Swagger dokümantasyonu da güncellendi. Herhangi bir sorun olursa haber ver.</p><p>İyi çalışmalar,<br>Zeynep</p>', attachments:[{name:'api_docs_v2.pdf',size:'1.2 MB',icon:'bi-file-earmark-pdf',cls:''}] },
    { id:3, folder:'inbox', label:'finance', starred:false, unread:true, sender:'Mehmet Demir', email:'mehmet.demir@sirket.com', initials:'MD', color:'orange', time:'Dün', date:'21 Şubat 2026, 14:30', to:'ben <ozan@sirket.com>', subject:'Ocak ayı bütçe raporu', preview:'Merhaba, Ocak ayı bütçe raporunu ekte bulabilirsiniz. Genel olarak hedeflerin %92\'sine ulaşıldı.', body:'<p>Merhaba,</p><p>Ocak ayı bütçe raporunu ekte bulabilirsiniz. Genel olarak hedeflerin %92\'sine ulaşıldı.</p><p>Detaylı analiz için öne çıkan kalemler:</p><div class="msg-detail-table"><div class="msg-detail-table-row"><span>Pazarlama</span><span>18.500 TL / 20.000 TL (%92.5)</span></div><div class="msg-detail-table-row"><span>Altyapı</span><span>32.100 TL / 35.000 TL (%91.7)</span></div><div class="msg-detail-table-row"><span>İnsan Kaynakları</span><span>45.800 TL / 48.000 TL (%95.4)</span></div></div><p>Toplam tasarruf: <strong>6.600 TL</strong></p><p>Saygılarımla,<br>Mehmet Demir<br>Finans Müdürü</p>', attachments:[{name:'ocak_butce_2026.xlsx',size:'856 KB',icon:'bi-file-earmark-excel',cls:'xlsx'},{name:'butce_grafikleri.pdf',size:'2.1 MB',icon:'bi-file-earmark-pdf',cls:''}] },
    { id:4, folder:'inbox', label:'work', starred:true, unread:false, sender:'Elif Özkan', email:'elif.ozkan@sirket.com', initials:'EÖ', color:'pink', time:'Dün', date:'21 Şubat 2026, 11:15', to:'ben <ozan@sirket.com>', subject:'Tasarım dosyaları güncellendi', preview:'Figma üzerindeki tasarım dosyalarını güncelledim. Yeni renk paleti ve tipografi değişiklikleri...', body:'<p>Merhaba Ozan,</p><p>Figma üzerindeki tasarım dosyalarını güncelledim.</p><p>Değişiklikler:</p><ul><li>Ana renk paleti güncellendi (Teal tonları eklendi)</li><li>Başlık fontları Inter\'den DM Serif Display\'e değiştirildi</li><li>Buton border-radius\'ları 12px olarak standartlaştırıldı</li><li>Dark mode kontrast oranları WCAG AA standardına uygun hale getirildi</li></ul><p>Elif Özkan<br>UI/UX Tasarımcı</p>', attachments:[{name:'design_system_v3.fig',size:'14.5 MB',icon:'bi-file-earmark-image',cls:''}] },
    { id:5, folder:'inbox', label:'important', starred:false, unread:true, sender:'Can Çelik', email:'can.celik@sirket.com', initials:'CÇ', color:'red', time:'20 Şub', date:'20 Şubat 2026, 16:45', to:'ben <ozan@sirket.com>, guvenlik@sirket.com', subject:'Güvenlik açığı bildirimi — ACİL', preview:'Acil! XSS açığı tespit edildi. Login sayfasındaki input alanlarında sanitizasyon eksik...', body:'<p><strong class="text-neon-red">ACİL!</strong></p><p>XSS açığı tespit edildi. Login sayfasındaki input alanlarında sanitizasyon eksik.</p><p>Detaylar:</p><ul><li><strong>Tip:</strong> Reflected XSS</li><li><strong>Şiddet:</strong> Yüksek (CVSS 7.5)</li><li><strong>Etkilenen Sayfa:</strong> /login, /register, /forgot-password</li><li><strong>Vektör:</strong> <code>&lt;script&gt;</code> tag injection via email input field</li></ul><p>Hemen müdahale etmemiz gerekiyor. Önerilen çözümler:</p><ol><li>Input sanitization uygulanması (DOMPurify)</li><li>CSP header\'larının güncellenmesi</li><li>HttpOnly cookie flag\'inin eklenmesi</li></ol><p>Can Çelik<br>Güvenlik Mühendisi</p>', attachments:[{name:'vulnerability_report.pdf',size:'523 KB',icon:'bi-file-earmark-pdf',cls:''}] },
    { id:6, folder:'inbox', label:'personal', starred:true, unread:true, sender:'Selin Arslan', email:'selin.arslan@sirket.com', initials:'SA', color:'green', time:'19 Şub', date:'19 Şubat 2026, 18:20', to:'ekip@sirket.com', subject:'Doğum günü partisi organizasyonu', preview:'Herkese merhaba! Cuma akşamı için restoran ayarladım. Saat 19:00\'da buluşuyoruz...', body:'<p>Herkese merhaba!</p><p>Cuma akşamı için restoran ayarladım. Saat 19:00\'da buluşuyoruz.</p><p>Detaylar:</p><ul><li><strong>Yer:</strong> Günaydın Restoran — Beyoğlu</li><li><strong>Tarih:</strong> 21 Şubat Cuma</li><li><strong>Saat:</strong> 19:00</li><li><strong>Kişi Sayısı:</strong> 15</li></ul><p>Menü seçenekleri ekte. Herkes kendi tercihini yapabilir.</p><p>Selin</p>', attachments:[{name:'menu_secenekleri.pdf',size:'1.8 MB',icon:'bi-file-earmark-pdf',cls:''}] },
    { id:7, folder:'inbox', label:'work', starred:false, unread:false, sender:'Burak Tuncer', email:'burak.tuncer@sirket.com', initials:'BT', color:'purple', time:'18 Şub', date:'18 Şubat 2026, 16:00', to:'dev-team@sirket.com', subject:'Sprint retrospektif notları', preview:'Son sprint\'in retrospektif notlarını paylaşıyorum. Genel performans gayet iyi...', body:'<p>Ekip,</p><p>Son sprint\'in retrospektif notlarını paylaşıyorum.</p><p><strong>İyi Giden:</strong></p><ul><li>Deployment süreci iyileştirildi (15dk → 5dk)</li><li>Code review süresi azaldı</li><li>Müşteri memnuniyet skoru %94\'e çıktı</li></ul><p><strong>İyileştirme Alanları:</strong></p><ul><li>Test coverage %78\'den %90\'a çıkarılmalı</li><li>Dokümantasyon güncellemeleri gecikiyor</li></ul><p>Burak Tuncer<br>Scrum Master</p>', attachments:[] },
    { id:8, folder:'inbox', label:'work', starred:false, unread:false, sender:'Deniz Yıldırım', email:'deniz.yildirim@sirket.com', initials:'DY', color:'cyan', time:'17 Şub', date:'17 Şubat 2026, 10:30', to:'ben <ozan@sirket.com>', subject:'Yeni müşteri portföyü', preview:'Bu ay 12 yeni müşteri kazandık. Detaylı portföy analizini ekte bulabilirsin...', body:'<p>Merhaba Ozan,</p><p>Bu ay 12 yeni müşteri kazandık.</p><p>Sektörel dağılım:</p><ul><li>Teknoloji: 4 müşteri</li><li>E-ticaret: 3 müşteri</li><li>Finans: 3 müşteri</li><li>Sağlık: 2 müşteri</li></ul><p>Q1 hedefimize yaklaşıyoruz.</p><p>Deniz Yıldırım<br>Satış Müdürü</p>', attachments:[{name:'musteri_portfoyu_subat.xlsx',size:'432 KB',icon:'bi-file-earmark-excel',cls:'xlsx'}] },
    { id:9, folder:'inbox', label:'finance', starred:false, unread:false, sender:'Hakan Koç', email:'hakan.koc@sirket.com', initials:'HK', color:'orange', time:'15 Şub', date:'15 Şubat 2026, 09:15', to:'ben <ozan@sirket.com>', subject:'Fatura onayı bekleniyor', preview:'3 adet fatura onayınızı bekliyor. Toplam tutar: 45.200 TL. Ödeme vadesi 1 Mart...', body:'<p>Merhaba,</p><p>3 adet fatura onayınızı bekliyor.</p><div class="msg-detail-table"><div class="msg-detail-table-row"><span>AWS Altyapı</span><span>18.750 TL — Vade: 1 Mar</span></div><div class="msg-detail-table-row"><span>Figma Lisans</span><span>4.200 TL — Vade: 1 Mar</span></div><div class="msg-detail-table-row"><span>Slack Business</span><span>22.250 TL — Vade: 5 Mar</span></div></div><p>Toplam: <strong>45.200 TL</strong></p><p>Hakan Koç<br>Muhasebe</p>', attachments:[{name:'fatura_aws.pdf',size:'156 KB',icon:'bi-file-earmark-pdf',cls:''},{name:'fatura_figma.pdf',size:'98 KB',icon:'bi-file-earmark-pdf',cls:''},{name:'fatura_slack.pdf',size:'112 KB',icon:'bi-file-earmark-pdf',cls:''}] },
    { id:10, folder:'inbox', label:'personal', starred:false, unread:false, sender:'Nisa Erdoğan', email:'nisa.erdogan@sirket.com', initials:'NE', color:'purple', time:'14 Şub', date:'14 Şubat 2026, 15:40', to:'ekip@sirket.com', subject:'Konferans biletleri alındı', preview:'DevFest 2026 biletlerini aldım! 15-16 Mart tarihlerinde İstanbul\'da. Otel ayarlayalım mı?', body:'<p>Merhaba ekip,</p><p>DevFest 2026 biletlerini aldım!</p><p>Etkinlik detayları:</p><ul><li><strong>Tarih:</strong> 15-16 Mart 2026</li><li><strong>Yer:</strong> İstanbul Kongre Merkezi</li><li><strong>Bilet:</strong> 8 kişi</li></ul><p>Otel önerileri:</p><ol><li>Hilton Bomonti (5dk mesafe)</li><li>Marriott Şişli (10dk mesafe)</li></ol><p>Nisa Erdoğan<br>İnsan Kaynakları</p>', attachments:[{name:'devfest_biletler.pdf',size:'320 KB',icon:'bi-file-earmark-pdf',cls:''}] },
    { id:11, folder:'sent', label:'', starred:false, unread:false, sender:'Ahmet Yılmaz\'a', email:'', initials:'OZ', color:'teal-self', time:'09:30', date:'22 Şubat 2026, 09:30', to:'ahmet.yilmaz@sirket.com', subject:'Re: Yeni proje toplantısı hakkında', preview:'Gündemi inceledim, gayet güzel hazırlanmış. Sadece 3. maddeye teknik detayları da ekleyelim...', body:'<p>Merhaba Ahmet,</p><p>Gündemi inceledim, gayet güzel hazırlanmış. Sadece 3. maddeye teknik detayları da ekleyelim.</p><p>Toplantıda ayrıca yeni CI/CD pipeline\'ını da konuşabiliriz.</p><p>Teşekkürler,<br>Ozan</p>', attachments:[] },
    { id:12, folder:'sent', label:'', starred:false, unread:false, sender:'Tüm Ekibe', email:'', initials:'OZ', color:'teal-self', time:'Dün', date:'21 Şubat 2026, 17:00', to:'tum-ekip@sirket.com', subject:'Haftalık durum güncellemesi', preview:'Bu haftanın özetini paylaşıyorum. 3 sprint tamamlandı, 2 kritik bug düzeltildi...', body:'<p>Ekip,</p><p>Bu haftanın özeti:</p><ul><li>3 sprint tamamlandı</li><li>2 kritik bug düzeltildi</li><li>Yeni dashboard modülü devreye alındı</li><li>Performans iyileştirmesi: sayfa yükleme süresi %40 azaldı</li></ul><p>Herkese iyi hafta sonları!</p><p>Ozan Sonar<br>Tech Lead</p>', attachments:[] },
    { id:13, folder:'sent', label:'', starred:false, unread:false, sender:'Can Çelik\'e', email:'', initials:'OZ', color:'teal-self', time:'20 Şub', date:'20 Şubat 2026, 17:10', to:'can.celik@sirket.com', subject:'Re: Güvenlik açığı bildirimi — ACİL', preview:'Hemen ilgileniyorum. WAF kurallarını güncelliyorum ve input sanitizasyonunu tüm formlara uyguluyorum...', body:'<p>Can,</p><p>Hemen ilgileniyorum. WAF kurallarını güncelliyorum ve input sanitizasyonunu tüm formlara uyguluyorum.</p><p>30 dakika içinde hotfix deploy edeceğim.</p><p>Ozan</p>', attachments:[] },
    { id:14, folder:'draft', label:'', starred:false, unread:false, sender:'Mehmet Demir\'e', email:'', initials:'', color:'draft', time:'Dün', date:'Taslak — 21 Şubat 2026', to:'mehmet.demir@sirket.com', subject:'Re: Ocak ayı bütçe raporu', preview:'Mehmet, raporu inceledim. Pazarlama bütçesindeki sapma konusunda...', body:'<p>Mehmet,</p><p>Raporu inceledim. Pazarlama bütçesindeki sapma konusunda...</p>', attachments:[] },
    { id:15, folder:'draft', label:'', starred:false, unread:false, sender:'Yönetim Kurulu\'na', email:'', initials:'', color:'draft', time:'18 Şub', date:'Taslak — 18 Şubat 2026', to:'yonetim@sirket.com', subject:'Q1 strateji sunumu', preview:'Değerli yönetim kurulu üyeleri, 2026 ilk çeyrek stratejimizi...', body:'<p>Değerli yönetim kurulu üyeleri,</p><p>2026 ilk çeyrek stratejimizi...</p>', attachments:[] },
    { id:16, folder:'trash', label:'', starred:false, unread:false, sender:'Spam Gönderici', email:'spam@unknown.com', initials:'SP', color:'gray', time:'10 Şub', date:'10 Şubat 2026', to:'ozan@sirket.com', subject:'Harika fırsat! Kaçırmayın!', preview:'Bu inanılmaz teklifi kaçırmayın! Sınırlı süre için geçerli olan kampanyamızdan yararlanın...', body:'<p>Bu inanılmaz teklifi kaçırmayın! Sınırlı süre için geçerli olan kampanyamızdan yararlanın.</p>', attachments:[] }
  ];

  /* ==================== STATE ==================== */
  var currentFolder = 'inbox';
  var activeMessageId = null;

  /* ==================== INIT ==================== */
  document.addEventListener('DOMContentLoaded', function () {
    renderMessageList();
    animateStorageBar();
  });

  /* ==================== RENDER MESSAGE LIST ==================== */
  function renderMessageList() {
    var container = document.getElementById('msgList');
    if (!container) return;
    container.innerHTML = '';

    messages.forEach(function (m) {
      var visible = m.folder === currentFolder;
      var isDraft = m.folder === 'draft';
      var starIcon = m.starred ? 'bi-star-fill' : 'bi-star';
      var starActive = m.starred ? ' active' : '';

      var html = '' +
        '<div class="msg-item' + (m.unread ? ' unread' : '') + (isDraft ? ' draft' : '') + (visible ? '' : ' d-none') + '"' +
        ' data-id="' + m.id + '" data-folder="' + m.folder + '" data-label="' + m.label + '"' +
        (m.starred ? ' data-starred="true"' : '') +
        ' onclick="openMessage(' + m.id + ')">' +
          '<div class="msg-item-check">' +
            '<input type="checkbox" class="usr-checkbox" onclick="event.stopPropagation(); toggleBulk()">' +
          '</div>' +
          '<button class="msg-star' + starActive + '" onclick="event.stopPropagation(); toggleStar(this, ' + m.id + ')" title="Yıldızla">' +
            '<i class="bi ' + starIcon + '"></i>' +
          '</button>' +
          (isDraft
            ? '<div class="msg-avatar msg-avatar--draft"><i class="bi bi-file-earmark-text"></i></div>'
            : '<div class="msg-avatar msg-avatar--' + m.color + '">' + m.initials + '</div>'
          ) +
          '<div class="msg-item-content">' +
            '<div class="msg-item-top">' +
              '<span class="msg-sender">' + (isDraft ? '<span class="msg-draft-tag">Taslak</span> ' : '') + m.sender + '</span>' +
              (m.label ? '<span class="msg-label-tag msg-label-tag--' + m.label + '">' + labelName(m.label) + '</span>' : '') +
              '<span class="msg-time">' + m.time + '</span>' +
            '</div>' +
            '<div class="msg-subject">' + m.subject + '</div>' +
            '<div class="msg-preview">' + m.preview + '</div>' +
          '</div>' +
          '<div class="msg-item-actions">' +
            (m.folder !== 'trash' ? '<button onclick="event.stopPropagation(); archiveMsg(' + m.id + ')" title="Arşivle"><i class="bi bi-archive"></i></button>' : '') +
            '<button onclick="event.stopPropagation(); deleteMsg(' + m.id + ')" title="Sil"><i class="bi bi-trash3"></i></button>' +
          '</div>' +
        '</div>';

      container.innerHTML += html;
    });
  }

  function labelName(label) {
    var map = { important: 'Önemli', work: 'İş', personal: 'Kişisel', finance: 'Finans' };
    return map[label] || label;
  }

  /* ==================== FOLDER NAVIGATION ==================== */
  window.switchFolder = function (folder, btn) {
    currentFolder = folder;
    document.querySelectorAll('.msg-folder-btn').forEach(function (b) { b.classList.remove('active'); });
    if (btn) btn.classList.add('active');
    document.querySelectorAll('.msg-label-btn').forEach(function (b) { b.classList.remove('active'); });

    var items = document.querySelectorAll('.msg-item');
    var visibleCount = 0;
    items.forEach(function (item) {
      if (folder === 'starred') {
        var show = item.getAttribute('data-starred') === 'true';
        item.classList.toggle('d-none', !show);
        if (show) visibleCount++;
      } else if (item.getAttribute('data-folder') === folder) {
        item.classList.remove('d-none');
        visibleCount++;
      } else {
        item.classList.add('d-none');
      }
    });

    document.getElementById('msgEmpty').classList.toggle('d-none', visibleCount > 0);
    closeDetail();
    document.getElementById('msgListPanel').classList.remove('detail-open');
  };

  window.filterByLabel = function (label) {
    document.querySelectorAll('.msg-folder-btn').forEach(function (b) { b.classList.remove('active'); });
    document.querySelectorAll('.msg-label-btn').forEach(function (b) { b.classList.remove('active'); });
    event.target.closest('.msg-label-btn').classList.add('active');

    var items = document.querySelectorAll('.msg-item');
    var visibleCount = 0;
    items.forEach(function (item) {
      if (item.getAttribute('data-label') === label) {
        item.classList.remove('d-none');
        visibleCount++;
      } else {
        item.classList.add('d-none');
      }
    });
    document.getElementById('msgEmpty').classList.toggle('d-none', visibleCount > 0);
  };

  window.toggleFolders = function () {
    document.getElementById('msgFolders').classList.toggle('show');
  };

  /* ==================== OPEN MESSAGE ==================== */
  window.openMessage = function (id) {
    var m = messages.find(function (x) { return x.id === id; });
    if (!m) return;
    activeMessageId = id;

    // Mark read
    m.unread = false;
    var item = document.querySelector('.msg-item[data-id="' + id + '"]');
    if (item) item.classList.remove('unread');

    // Set active
    document.querySelectorAll('.msg-item').forEach(function (i) { i.classList.remove('active'); });
    if (item) item.classList.add('active');

    // Fill detail
    document.getElementById('detailSubject').textContent = m.subject;
    document.getElementById('detailSender').textContent = m.sender;
    document.getElementById('detailEmail').textContent = m.email ? '<' + m.email + '>' : '';
    document.getElementById('detailDate').textContent = m.date;
    document.getElementById('detailTo').textContent = 'Alıcı: ' + m.to;
    document.getElementById('detailText').innerHTML = m.body;

    var avatar = document.getElementById('detailAvatar');
    avatar.textContent = m.initials || '';
    avatar.className = 'msg-detail-sender-avatar msg-avatar--' + m.color;

    // Attachments
    var attEl = document.getElementById('detailAttachments');
    if (m.attachments && m.attachments.length > 0) {
      attEl.classList.remove('d-none');
      document.getElementById('attTitle').textContent = 'Ekler (' + m.attachments.length + ')';
      document.getElementById('attList').innerHTML = m.attachments.map(function (a) {
        return '<div class="msg-attachment-item">' +
          '<div class="msg-attachment-icon ' + a.cls + '"><i class="bi ' + a.icon + '"></i></div>' +
          '<div class="msg-attachment-info"><span class="msg-attachment-name">' + a.name + '</span><span class="msg-attachment-size">' + a.size + '</span></div>' +
          '<button class="msg-attachment-download" title="İndir" onclick="showToast(\'Dosya indiriliyor: ' + a.name + '\',\'info\')"><i class="bi bi-download"></i></button>' +
          '</div>';
      }).join('');
    } else {
      attEl.classList.add('d-none');
    }

    document.getElementById('msgDetailContent').classList.remove('d-none');
    document.getElementById('msgDetailEmpty').classList.add('d-none');
    document.getElementById('msgListPanel').classList.add('detail-open');
    updateUnreadCount();
  };

  window.closeDetail = function () {
    document.getElementById('msgListPanel').classList.remove('detail-open');
    document.querySelectorAll('.msg-item').forEach(function (i) { i.classList.remove('active'); });
    document.getElementById('msgDetailContent').classList.add('d-none');
    document.getElementById('msgDetailEmpty').classList.remove('d-none');
    activeMessageId = null;
  };

  /* ==================== STAR ==================== */
  window.toggleStar = function (btn, id) {
    var m = messages.find(function (x) { return x.id === id; });
    if (!m) return;
    m.starred = !m.starred;
    btn.classList.toggle('active');
    var icon = btn.querySelector('i');
    icon.className = m.starred ? 'bi bi-star-fill' : 'bi bi-star';
    var item = btn.closest('.msg-item');
    if (m.starred) item.setAttribute('data-starred', 'true');
    else item.removeAttribute('data-starred');
  };

  /* ==================== DELETE / ARCHIVE ==================== */
  window.deleteMsg = function (id) {
    var item = document.querySelector('.msg-item[data-id="' + id + '"]');
    if (!item) return;
    item.classList.add('msg-item--removing');
    setTimeout(function () {
      item.remove();
      var idx = messages.findIndex(function (x) { return x.id === id; });
      if (idx !== -1) messages.splice(idx, 1);
      updateUnreadCount();
      checkEmpty();
      if (activeMessageId === id) closeDetail();
    }, 350);
    showToast('Mesaj silindi', 'success');
  };

  window.archiveMsg = function (id) {
    var m = messages.find(function (x) { return x.id === id; });
    if (!m) return;
    m.folder = 'archive';
    var item = document.querySelector('.msg-item[data-id="' + id + '"]');
    if (item) {
      item.setAttribute('data-folder', 'archive');
      if (currentFolder !== 'archive') {
        item.classList.add('msg-item--archiving');
        setTimeout(function () {
          item.classList.add('d-none');
          item.classList.remove('msg-item--archiving');
          checkEmpty();
          if (activeMessageId === id) closeDetail();
        }, 350);
      }
    }
    showToast('Mesaj arşivlendi', 'info');
  };

  window.markAllRead = function () {
    messages.forEach(function (m) { m.unread = false; });
    document.querySelectorAll('.msg-item.unread').forEach(function (el) { el.classList.remove('unread'); });
    updateUnreadCount();
    showToast('Tüm mesajlar okundu olarak işaretlendi', 'success');
  };

  window.refreshMessages = function () {
    var list = document.getElementById('msgList');
    list.classList.add('msg-list--refreshing');
    setTimeout(function () { list.classList.remove('msg-list--refreshing'); }, 600);
    showToast('Mesajlar yenilendi', 'info');
  };

  /* ==================== SEARCH & SORT ==================== */
  window.searchMessages = function (query) {
    var q = query.toLowerCase();
    var items = document.querySelectorAll('.msg-item');
    var visibleCount = 0;
    items.forEach(function (item) {
      var text = item.textContent.toLowerCase();
      if (q === '') {
        var folder = item.getAttribute('data-folder');
        if (currentFolder === 'starred') {
          item.classList.toggle('d-none', item.getAttribute('data-starred') !== 'true');
        } else {
          item.classList.toggle('d-none', folder !== currentFolder);
        }
      } else if (text.indexOf(q) !== -1) {
        item.classList.remove('d-none');
        visibleCount++;
      } else {
        item.classList.add('d-none');
      }
    });
    if (q) document.getElementById('msgEmpty').classList.toggle('d-none', visibleCount > 0);
    else checkEmpty();
  };

  window.sortMessages = function (type) {
    var labels = { newest: 'En Yeni', oldest: 'En Eski', unread: 'Okunmamış' };
    showToast('Sıralama: ' + (labels[type] || type), 'info');
  };

  /* ==================== BULK ==================== */
  window.toggleBulk = function () {
    var checked = document.querySelectorAll('.msg-item-check input:checked');
    var bar = document.getElementById('msgBulkBar');
    document.getElementById('bulkCount').textContent = checked.length;
    bar.classList.toggle('show', checked.length > 0);
  };

  window.bulkMarkRead = function () {
    document.querySelectorAll('.msg-item-check input:checked').forEach(function (cb) {
      var item = cb.closest('.msg-item');
      var id = parseInt(item.getAttribute('data-id'));
      var m = messages.find(function (x) { return x.id === id; });
      if (m) m.unread = false;
      item.classList.remove('unread');
      cb.checked = false;
    });
    toggleBulk();
    updateUnreadCount();
    showToast('Seçili mesajlar okundu işaretlendi', 'success');
  };

  window.bulkArchive = function () {
    document.querySelectorAll('.msg-item-check input:checked').forEach(function (cb) {
      var item = cb.closest('.msg-item');
      var id = parseInt(item.getAttribute('data-id'));
      archiveMsg(id);
      cb.checked = false;
    });
    toggleBulk();
  };

  window.bulkDelete = function () {
    var ids = [];
    document.querySelectorAll('.msg-item-check input:checked').forEach(function (cb) {
      var item = cb.closest('.msg-item');
      ids.push(parseInt(item.getAttribute('data-id')));
      cb.checked = false;
    });
    ids.forEach(function (id) { deleteMsg(id); });
    toggleBulk();
  };

  /* ==================== DETAIL ACTIONS ==================== */
  window.replyMessage = function () {
    if (!activeMessageId) return;
    var m = messages.find(function (x) { return x.id === activeMessageId; });
    if (!m) return;
    document.getElementById('composeTo').value = m.email || '';
    document.getElementById('composeCc').value = '';
    document.getElementById('composeSubject').value = 'Re: ' + m.subject;
    document.getElementById('composeBody').value = '';
    new bootstrap.Modal(document.getElementById('composeModal')).show();
  };

  window.forwardMessage = function () {
    if (!activeMessageId) return;
    var m = messages.find(function (x) { return x.id === activeMessageId; });
    if (!m) return;
    document.getElementById('composeTo').value = '';
    document.getElementById('composeCc').value = '';
    document.getElementById('composeSubject').value = 'Fwd: ' + m.subject;
    document.getElementById('composeBody').value = '';
    new bootstrap.Modal(document.getElementById('composeModal')).show();
  };

  window.printMessage = function () { showToast('Yazdırma penceresi açılıyor...', 'info'); };

  window.archiveDetail = function () {
    if (activeMessageId) archiveMsg(activeMessageId);
  };

  window.deleteDetail = function () {
    if (activeMessageId) deleteMsg(activeMessageId);
  };

  window.sendReply = function () {
    var text = document.getElementById('quickReplyText');
    if (!text.value.trim()) { showToast('Lütfen yanıtınızı yazın', 'warning'); return; }
    text.value = '';
    showToast('Yanıt gönderildi', 'success');
  };

  /* ==================== COMPOSE ==================== */
  window.openCompose = function () {
    document.getElementById('composeTo').value = '';
    document.getElementById('composeCc').value = '';
    document.getElementById('composeSubject').value = '';
    document.getElementById('composeBody').value = '';
    document.getElementById('composeAttachments').innerHTML = '';
    new bootstrap.Modal(document.getElementById('composeModal')).show();
  };

  window.sendCompose = function () {
    var to = document.getElementById('composeTo').value;
    var subject = document.getElementById('composeSubject').value;
    if (!to || !subject) { showToast('Lütfen alıcı ve konu alanlarını doldurun', 'warning'); return; }
    bootstrap.Modal.getInstance(document.getElementById('composeModal')).hide();
    showToast('Mesaj gönderildi', 'success');
  };

  window.saveDraft = function () {
    bootstrap.Modal.getInstance(document.getElementById('composeModal')).hide();
    showToast('Taslak kaydedildi', 'info');
  };

  window.addComposeAttachment = function () {
    var container = document.getElementById('composeAttachments');
    var name = 'dosya_' + (container.children.length + 1) + '.pdf';
    var el = document.createElement('div');
    el.className = 'msg-compose-att-item';
    el.innerHTML = '<i class="bi bi-file-earmark me-1"></i>' + name + '<button onclick="this.parentElement.remove()"><i class="bi bi-x"></i></button>';
    container.appendChild(el);
  };

  /* ==================== HELPERS ==================== */
  function updateUnreadCount() {
    var count = messages.filter(function (m) { return m.unread && m.folder === 'inbox'; }).length;
    var badge = document.querySelector('[data-folder="inbox"] .msg-folder-count');
    if (badge) {
      badge.textContent = count;
      badge.classList.toggle('d-none', count === 0);
    }
  }

  function checkEmpty() {
    var visible = Array.from(document.querySelectorAll('.msg-item')).some(function (i) { return !i.classList.contains('d-none'); });
    document.getElementById('msgEmpty').classList.toggle('d-none', visible);
  }

  function animateStorageBar() {
    setTimeout(function () {
      document.querySelectorAll('.msg-storage-fill[data-width]').forEach(function (el) {
        el.style.width = el.getAttribute('data-width') + '%';
      });
    }, 500);
  }

  /* ==================== TOAST ==================== */
  function showToast(message, type) {
    if (window.showToast && window.showToast !== showToast) {
      window.showToast(message, type);
      return;
    }
    type = type || 'success';
    var existing = document.querySelector('.ca-toast');
    if (existing) existing.remove();
    var icons = { success:'bi-check-circle-fill', danger:'bi-x-circle-fill', warning:'bi-exclamation-triangle-fill', info:'bi-info-circle-fill' };
    var colors = { success:'text-neon-green', danger:'text-neon-red', warning:'text-neon-orange', info:'text-neon-blue' };
    var toast = document.createElement('div');
    toast.className = 'ca-toast ca-toast-' + type;
    toast.innerHTML = '<i class="bi ' + (icons[type]||icons.info) + ' ' + (colors[type]||'') + '"></i><span>' + message + '</span><button onclick="this.parentElement.remove()"><i class="bi bi-x"></i></button>';
    document.body.appendChild(toast);
    requestAnimationFrame(function () { toast.classList.add('show'); });
    setTimeout(function () { toast.classList.remove('show'); setTimeout(function () { toast.remove(); }, 350); }, 3500);
  }

})();
