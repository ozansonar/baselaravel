(function () {
  'use strict';

  /* ==================== CAMPAIGN DATA ==================== */
  var campaigns = [
    {
      id: 1,
      name: 'Kış Büyük İndirim',
      type: 'percentage',
      typeLabel: 'Yüzde İndirim',
      discount: 25,
      discountDisplay: '%25',
      couponCode: null,
      status: 'active',
      startDate: '2026-01-15',
      endDate: '2026-03-15',
      minOrder: 200,
      maxDiscount: 500,
      totalLimit: 5000,
      perUserLimit: 2,
      used: 2847,
      revenue: 142350,
      categories: ['Tüm Kategoriler'],
      targetGroup: 'Tüm Kullanıcılar',
      stackable: false,
      autoApply: true,
      description: 'Kış sezonu boyunca geçerli tüm ürünlerde %25 indirim kampanyası.',
      color: 'teal'
    },
    {
      id: 2,
      name: 'Yeni Üye Hoş Geldin',
      type: 'coupon',
      typeLabel: 'Kupon Kodu',
      discount: 50,
      discountDisplay: '₺50',
      couponCode: 'HOSGELDIN50',
      status: 'active',
      startDate: '2026-01-01',
      endDate: '2026-12-31',
      minOrder: 150,
      maxDiscount: 50,
      totalLimit: 0,
      perUserLimit: 1,
      used: 1243,
      revenue: 62150,
      categories: ['Tüm Kategoriler'],
      targetGroup: 'Yeni Üyeler',
      stackable: false,
      autoApply: false,
      description: 'İlk alışverişte geçerli ₺50 indirim kuponu.',
      color: 'blue'
    },
    {
      id: 3,
      name: 'Flash Sale — Elektronik',
      type: 'flash',
      typeLabel: 'Flash Sale',
      discount: 40,
      discountDisplay: '%40',
      couponCode: null,
      status: 'active',
      startDate: '2026-02-20',
      endDate: '2026-02-23',
      minOrder: 0,
      maxDiscount: 2000,
      totalLimit: 500,
      perUserLimit: 1,
      used: 387,
      revenue: 96750,
      categories: ['Elektronik'],
      targetGroup: 'Tüm Kullanıcılar',
      stackable: false,
      autoApply: true,
      description: '72 saat sürecek elektronik ürünlerde mega indirim!',
      color: 'red'
    },
    {
      id: 4,
      name: 'Ücretsiz Kargo Haftası',
      type: 'freeShipping',
      typeLabel: 'Ücretsiz Kargo',
      discount: 0,
      discountDisplay: 'Ücretsiz',
      couponCode: null,
      status: 'active',
      startDate: '2026-02-17',
      endDate: '2026-02-24',
      minOrder: 100,
      maxDiscount: null,
      totalLimit: 0,
      perUserLimit: 0,
      used: 1534,
      revenue: 0,
      categories: ['Tüm Kategoriler'],
      targetGroup: 'Tüm Kullanıcılar',
      stackable: true,
      autoApply: true,
      description: '₺100 ve üzeri tüm siparişlerde ücretsiz kargo.',
      color: 'green'
    },
    {
      id: 5,
      name: '3 Al 2 Öde — Moda',
      type: 'bogo',
      typeLabel: 'Al-Öde',
      discount: 33,
      discountDisplay: '3 Al 2 Öde',
      couponCode: null,
      status: 'active',
      startDate: '2026-02-01',
      endDate: '2026-02-28',
      minOrder: 0,
      maxDiscount: null,
      totalLimit: 2000,
      perUserLimit: 3,
      used: 876,
      revenue: 43800,
      categories: ['Moda'],
      targetGroup: 'Tüm Kullanıcılar',
      stackable: false,
      autoApply: true,
      description: 'Moda kategorisinde 3 ürün al, en ucuzunu ödemeden götür.',
      color: 'purple'
    },
    {
      id: 6,
      name: 'VIP Özel — %30 İndirim',
      type: 'percentage',
      typeLabel: 'Yüzde İndirim',
      discount: 30,
      discountDisplay: '%30',
      couponCode: 'VIP30',
      status: 'active',
      startDate: '2026-02-10',
      endDate: '2026-03-10',
      minOrder: 500,
      maxDiscount: 1500,
      totalLimit: 1000,
      perUserLimit: 5,
      used: 412,
      revenue: 61800,
      categories: ['Tüm Kategoriler'],
      targetGroup: 'VIP Müşteriler',
      stackable: false,
      autoApply: false,
      description: 'VIP müşterilere özel %30 indirim fırsatı. VIP30 kodunu kullanın.',
      color: 'orange'
    },
    {
      id: 7,
      name: 'Valentines Day Koleksiyonu',
      type: 'percentage',
      typeLabel: 'Yüzde İndirim',
      discount: 20,
      discountDisplay: '%20',
      couponCode: 'SEVGI20',
      status: 'ended',
      startDate: '2026-02-07',
      endDate: '2026-02-15',
      minOrder: 100,
      maxDiscount: 300,
      totalLimit: 3000,
      perUserLimit: 2,
      used: 2156,
      revenue: 64680,
      categories: ['Kozmetik', 'Moda'],
      targetGroup: 'Tüm Kullanıcılar',
      stackable: true,
      autoApply: false,
      description: 'Sevgililer Günü\'ne özel kozmetik ve moda kategorisinde %20 indirim.',
      color: 'pink'
    },
    {
      id: 8,
      name: 'Bahar Koleksiyonu Lansmanı',
      type: 'fixed',
      typeLabel: 'Sabit İndirim',
      discount: 75,
      discountDisplay: '₺75',
      couponCode: 'BAHAR75',
      status: 'scheduled',
      startDate: '2026-03-01',
      endDate: '2026-03-31',
      minOrder: 250,
      maxDiscount: 75,
      totalLimit: 2000,
      perUserLimit: 1,
      used: 0,
      revenue: 0,
      categories: ['Moda'],
      targetGroup: 'Tüm Kullanıcılar',
      stackable: false,
      autoApply: false,
      description: 'Bahar koleksiyonu lansmanında ₺250 üzeri alışverişlerde ₺75 indirim.',
      color: 'teal'
    },
    {
      id: 9,
      name: 'Mart Spor Kampanyası',
      type: 'percentage',
      typeLabel: 'Yüzde İndirim',
      discount: 15,
      discountDisplay: '%15',
      couponCode: null,
      status: 'scheduled',
      startDate: '2026-03-05',
      endDate: '2026-03-20',
      minOrder: 200,
      maxDiscount: 400,
      totalLimit: 1500,
      perUserLimit: 2,
      used: 0,
      revenue: 0,
      categories: ['Spor'],
      targetGroup: 'Tüm Kullanıcılar',
      stackable: true,
      autoApply: true,
      description: 'Spor kategorisinde %15 indirim. Otomatik uygulanır.',
      color: 'blue'
    },
    {
      id: 10,
      name: 'Teknoloji Haftası Flash',
      type: 'flash',
      typeLabel: 'Flash Sale',
      discount: 35,
      discountDisplay: '%35',
      couponCode: null,
      status: 'scheduled',
      startDate: '2026-03-10',
      endDate: '2026-03-12',
      minOrder: 0,
      maxDiscount: 1500,
      totalLimit: 300,
      perUserLimit: 1,
      used: 0,
      revenue: 0,
      categories: ['Elektronik'],
      targetGroup: 'Tüm Kullanıcılar',
      stackable: false,
      autoApply: true,
      description: '48 saat sürecek elektronik mega kampanyası!',
      color: 'red'
    },
    {
      id: 11,
      name: 'Müşteri Geri Kazanım',
      type: 'coupon',
      typeLabel: 'Kupon Kodu',
      discount: 100,
      discountDisplay: '₺100',
      couponCode: 'GELGEL100',
      status: 'paused',
      startDate: '2026-02-01',
      endDate: '2026-04-01',
      minOrder: 300,
      maxDiscount: 100,
      totalLimit: 500,
      perUserLimit: 1,
      used: 89,
      revenue: 8900,
      categories: ['Tüm Kategoriler'],
      targetGroup: 'İnaktif Kullanıcılar',
      stackable: false,
      autoApply: false,
      description: '60+ gün alışveriş yapmayan kullanıcılara özel ₺100 indirim.',
      color: 'orange'
    },
    {
      id: 12,
      name: 'Yazlık Ürünler Ön Satış',
      type: 'percentage',
      typeLabel: 'Yüzde İndirim',
      discount: 10,
      discountDisplay: '%10',
      couponCode: null,
      status: 'draft',
      startDate: '',
      endDate: '',
      minOrder: 0,
      maxDiscount: 200,
      totalLimit: 0,
      perUserLimit: 0,
      used: 0,
      revenue: 0,
      categories: ['Moda', 'Spor'],
      targetGroup: 'Tüm Kullanıcılar',
      stackable: true,
      autoApply: true,
      description: 'Yazlık koleksiyon ön satış indirimi (taslak).',
      color: 'purple'
    },
    {
      id: 13,
      name: 'Yılbaşı Mega İndirim',
      type: 'percentage',
      typeLabel: 'Yüzde İndirim',
      discount: 50,
      discountDisplay: '%50',
      couponCode: 'YILBASI50',
      status: 'ended',
      startDate: '2025-12-25',
      endDate: '2026-01-05',
      minOrder: 300,
      maxDiscount: 1000,
      totalLimit: 10000,
      perUserLimit: 3,
      used: 8742,
      revenue: 437100,
      categories: ['Tüm Kategoriler'],
      targetGroup: 'Tüm Kullanıcılar',
      stackable: false,
      autoApply: false,
      description: 'Yılbaşı dönemine özel tüm ürünlerde geçerli mega indirim kampanyası.',
      color: 'teal'
    },
    {
      id: 14,
      name: 'Black Friday Klasik',
      type: 'percentage',
      typeLabel: 'Yüzde İndirim',
      discount: 60,
      discountDisplay: '%60',
      couponCode: null,
      status: 'ended',
      startDate: '2025-11-24',
      endDate: '2025-11-30',
      minOrder: 0,
      maxDiscount: 3000,
      totalLimit: 0,
      perUserLimit: 0,
      used: 15234,
      revenue: 914040,
      categories: ['Tüm Kategoriler'],
      targetGroup: 'Tüm Kullanıcılar',
      stackable: false,
      autoApply: true,
      description: 'Black Friday haftası — tüm ürünlerde %60\'a varan indirimler.',
      color: 'purple'
    },
    {
      id: 15,
      name: 'Kozmetik Özel Kampanya',
      type: 'bogo',
      typeLabel: 'Al-Öde',
      discount: 50,
      discountDisplay: '2 Al 1 Öde',
      couponCode: null,
      status: 'paused',
      startDate: '2026-02-10',
      endDate: '2026-03-10',
      minOrder: 0,
      maxDiscount: null,
      totalLimit: 1000,
      perUserLimit: 2,
      used: 234,
      revenue: 11700,
      categories: ['Kozmetik'],
      targetGroup: 'Tüm Kullanıcılar',
      stackable: false,
      autoApply: true,
      description: 'Kozmetik ürünlerde 2 al 1 öde kampanyası.',
      color: 'pink'
    },
    {
      id: 16,
      name: 'Ev & Yaşam İndirimi',
      type: 'fixed',
      typeLabel: 'Sabit İndirim',
      discount: 150,
      discountDisplay: '₺150',
      couponCode: 'EVIM150',
      status: 'active',
      startDate: '2026-02-15',
      endDate: '2026-03-15',
      minOrder: 500,
      maxDiscount: 150,
      totalLimit: 800,
      perUserLimit: 1,
      used: 312,
      revenue: 46800,
      categories: ['Ev & Yaşam'],
      targetGroup: 'Tüm Kullanıcılar',
      stackable: true,
      autoApply: false,
      description: 'Ev & Yaşam kategorisinde ₺500 üzeri alışverişlerde ₺150 indirim.',
      color: 'green'
    },
    {
      id: 17,
      name: 'Hafta Sonu Süprizi',
      type: 'percentage',
      typeLabel: 'Yüzde İndirim',
      discount: 10,
      discountDisplay: '%10',
      couponCode: 'HAFTASONU',
      status: 'active',
      startDate: '2026-02-21',
      endDate: '2026-02-23',
      minOrder: 75,
      maxDiscount: 100,
      totalLimit: 2000,
      perUserLimit: 1,
      used: 187,
      revenue: 9350,
      categories: ['Tüm Kategoriler'],
      targetGroup: 'Tüm Kullanıcılar',
      stackable: true,
      autoApply: false,
      description: 'Bu hafta sonu tüm alışverişlerde %10 ekstra indirim.',
      color: 'blue'
    },
    {
      id: 18,
      name: 'Spor Malzemeleri Kampanyası',
      type: 'percentage',
      typeLabel: 'Yüzde İndirim',
      discount: 20,
      discountDisplay: '%20',
      couponCode: null,
      status: 'ended',
      startDate: '2026-01-10',
      endDate: '2026-02-10',
      minOrder: 150,
      maxDiscount: 500,
      totalLimit: 3000,
      perUserLimit: 2,
      used: 1876,
      revenue: 56280,
      categories: ['Spor'],
      targetGroup: 'Tüm Kullanıcılar',
      stackable: false,
      autoApply: true,
      description: 'Spor kategorisinde %20 indirim kampanyası.',
      color: 'orange'
    },
    {
      id: 19,
      name: 'İlk Sipariş Kargo Bedava',
      type: 'freeShipping',
      typeLabel: 'Ücretsiz Kargo',
      discount: 0,
      discountDisplay: 'Ücretsiz',
      couponCode: null,
      status: 'active',
      startDate: '2026-01-01',
      endDate: '2026-06-30',
      minOrder: 0,
      maxDiscount: null,
      totalLimit: 0,
      perUserLimit: 1,
      used: 2456,
      revenue: 0,
      categories: ['Tüm Kategoriler'],
      targetGroup: 'Yeni Üyeler',
      stackable: true,
      autoApply: true,
      description: 'Yeni üyelerin ilk siparişinde kargo bedava.',
      color: 'teal'
    },
    {
      id: 20,
      name: 'Ramazan Özel',
      type: 'percentage',
      typeLabel: 'Yüzde İndirim',
      discount: 20,
      discountDisplay: '%20',
      couponCode: 'RAMAZAN20',
      status: 'scheduled',
      startDate: '2026-02-28',
      endDate: '2026-03-30',
      minOrder: 100,
      maxDiscount: 400,
      totalLimit: 5000,
      perUserLimit: 3,
      used: 0,
      revenue: 0,
      categories: ['Ev & Yaşam', 'Moda'],
      targetGroup: 'Tüm Kullanıcılar',
      stackable: false,
      autoApply: false,
      description: 'Ramazan ayına özel Ev & Yaşam ve Moda kategorilerinde %20 indirim.',
      color: 'purple'
    },
    {
      id: 21,
      name: 'Geri Dönüşüm Teşviki',
      type: 'fixed',
      typeLabel: 'Sabit İndirim',
      discount: 30,
      discountDisplay: '₺30',
      couponCode: 'RECYCLE30',
      status: 'paused',
      startDate: '2026-02-01',
      endDate: '2026-04-30',
      minOrder: 100,
      maxDiscount: 30,
      totalLimit: 1000,
      perUserLimit: 1,
      used: 156,
      revenue: 4680,
      categories: ['Tüm Kategoriler'],
      targetGroup: 'Tüm Kullanıcılar',
      stackable: true,
      autoApply: false,
      description: 'Eski ürün iade edenlere yeni alışverişte ₺30 indirim.',
      color: 'green'
    },
    {
      id: 22,
      name: 'Doğum Günü İndirimi',
      type: 'percentage',
      typeLabel: 'Yüzde İndirim',
      discount: 15,
      discountDisplay: '%15',
      couponCode: null,
      status: 'active',
      startDate: '2026-01-01',
      endDate: '2026-12-31',
      minOrder: 0,
      maxDiscount: 200,
      totalLimit: 0,
      perUserLimit: 1,
      used: 534,
      revenue: 16020,
      categories: ['Tüm Kategoriler'],
      targetGroup: 'Tüm Kullanıcılar',
      stackable: true,
      autoApply: true,
      description: 'Doğum günü ayında otomatik %15 indirim.',
      color: 'pink'
    },
    {
      id: 23,
      name: 'Nisan Kampanyası Taslağı',
      type: 'percentage',
      typeLabel: 'Yüzde İndirim',
      discount: 25,
      discountDisplay: '%25',
      couponCode: null,
      status: 'draft',
      startDate: '',
      endDate: '',
      minOrder: 200,
      maxDiscount: 600,
      totalLimit: 3000,
      perUserLimit: 2,
      used: 0,
      revenue: 0,
      categories: ['Tüm Kategoriler'],
      targetGroup: 'Tüm Kullanıcılar',
      stackable: false,
      autoApply: true,
      description: 'Nisan ayı genel indirim kampanyası taslağı.',
      color: 'blue'
    },
    {
      id: 24,
      name: 'Anneler Günü Erken Kuş',
      type: 'coupon',
      typeLabel: 'Kupon Kodu',
      discount: 75,
      discountDisplay: '₺75',
      couponCode: 'ANNE75',
      status: 'scheduled',
      startDate: '2026-05-01',
      endDate: '2026-05-11',
      minOrder: 200,
      maxDiscount: 75,
      totalLimit: 5000,
      perUserLimit: 1,
      used: 0,
      revenue: 0,
      categories: ['Kozmetik', 'Moda', 'Ev & Yaşam'],
      targetGroup: 'Tüm Kullanıcılar',
      stackable: false,
      autoApply: false,
      description: 'Anneler Günü\'ne özel ₺75 indirim kuponu.',
      color: 'pink'
    }
  ];

  /* ==================== TYPE & STATUS CONFIG ==================== */
  var typeIcons = {
    percentage:   'bi-percent',
    fixed:        'bi-cash-coin',
    coupon:       'bi-ticket-perforated',
    freeShipping: 'bi-truck',
    bogo:         'bi-gift',
    flash:        'bi-lightning-charge-fill'
  };

  var typeColors = {
    percentage:   'teal',
    fixed:        'blue',
    coupon:       'purple',
    freeShipping: 'green',
    bogo:         'orange',
    flash:        'red'
  };

  var statusLabels = {
    active:    'Aktif',
    scheduled: 'Planlanmış',
    paused:    'Duraklatılmış',
    ended:     'Tamamlanmış',
    draft:     'Taslak'
  };

  var statusIcons = {
    active:    'bi-lightning-charge-fill',
    scheduled: 'bi-calendar-event',
    paused:    'bi-pause-circle-fill',
    ended:     'bi-check-circle-fill',
    draft:     'bi-pencil-fill'
  };

  /* ==================== INIT ==================== */
  var currentView = 'grid';
  var deleteTargetId = null;
  var editTargetId = null;

  document.addEventListener('DOMContentLoaded', function () {
    renderGridView();
  });

  /* ==================== GRID RENDERING ==================== */
  function renderGridView() {
    var container = document.getElementById('campaignGridView');
    if (!container) return;
    container.innerHTML = '';

    campaigns.forEach(function (c, idx) {
      var usagePercent = c.totalLimit > 0 ? Math.round((c.used / c.totalLimit) * 100) : (c.used > 0 ? 100 : 0);
      var progressColor = usagePercent >= 90 ? 'var(--neon-red)' : usagePercent >= 60 ? 'var(--neon-orange)' : 'var(--teal-primary)';
      var dateStr = c.startDate ? formatDateShort(c.startDate) + ' – ' + formatDateShort(c.endDate) : 'Tarih belirlenmedi';

      var html = '' +
        '<div class="col-xxl-4 col-xl-6 col-md-6" data-status="' + c.status + '" data-type="' + c.type + '" data-name="' + c.name.toLowerCase() + '" data-coupon="' + (c.couponCode || '').toLowerCase() + '" data-aos="fade-up" data-aos-delay="' + (idx % 6) * 50 + '">' +
          '<div class="cmp-card">' +
            '<div class="cmp-card-header cmp-card-header--' + c.color + '">' +
              '<div class="cmp-card-type-icon"><i class="bi ' + typeIcons[c.type] + '"></i></div>' +
              '<div class="cmp-card-header-info">' +
                '<span class="cmp-card-type-label">' + c.typeLabel + '</span>' +
                '<span class="cmp-card-discount-big">' + c.discountDisplay + '</span>' +
              '</div>' +
              '<div class="cmp-card-status cmp-status--' + c.status + '">' +
                '<i class="bi ' + statusIcons[c.status] + '"></i> ' + statusLabels[c.status] +
              '</div>' +
              '<input type="checkbox" class="usr-checkbox cmp-card-check" data-id="' + c.id + '" onchange="updateBulkSelection()">' +
            '</div>' +
            '<div class="cmp-card-body">' +
              '<h6 class="cmp-card-name">' + c.name + '</h6>' +
              '<p class="cmp-card-desc">' + c.description + '</p>' +
              (c.couponCode ? '<div class="cmp-card-coupon" onclick="showCouponModal(\'' + c.couponCode + '\')"><i class="bi bi-ticket-perforated me-1"></i>' + c.couponCode + ' <i class="bi bi-clipboard ms-1"></i></div>' : '') +
              '<div class="cmp-card-meta">' +
                '<div class="cmp-card-meta-item"><i class="bi bi-calendar3"></i><span>' + dateStr + '</span></div>' +
                (c.minOrder > 0 ? '<div class="cmp-card-meta-item"><i class="bi bi-cart"></i><span>Min. ₺' + c.minOrder.toLocaleString('tr-TR') + '</span></div>' : '') +
                '<div class="cmp-card-meta-item"><i class="bi bi-people"></i><span>' + c.targetGroup + '</span></div>' +
                '<div class="cmp-card-meta-item"><i class="bi bi-tag"></i><span>' + c.categories.join(', ') + '</span></div>' +
              '</div>' +
              '<div class="cmp-card-progress">' +
                '<div class="cmp-card-progress-header">' +
                  '<span>Kullanım: ' + c.used.toLocaleString('tr-TR') + (c.totalLimit > 0 ? ' / ' + c.totalLimit.toLocaleString('tr-TR') : '') + '</span>' +
                  (c.totalLimit > 0 ? '<span>' + usagePercent + '%</span>' : '') +
                '</div>' +
                '<div class="cmp-card-progress-bar">' +
                  '<div class="cmp-card-progress-fill" data-width="' + Math.min(usagePercent, 100) + '" data-color="' + progressColor + '"></div>' +
                '</div>' +
              '</div>' +
              (c.revenue > 0 ? '<div class="cmp-card-revenue"><i class="bi bi-graph-up-arrow me-1"></i>Gelir etkisi: <strong>₺' + c.revenue.toLocaleString('tr-TR') + '</strong></div>' : '') +
            '</div>' +
            '<div class="cmp-card-footer">' +
              '<button class="usr-action-btn" title="Detay" onclick="openCampaignDetail(' + c.id + ')"><i class="bi bi-eye"></i></button>' +
              '<button class="usr-action-btn" title="Düzenle" onclick="openEditCampaign(' + c.id + ')"><i class="bi bi-pencil"></i></button>' +
              (c.couponCode ? '<button class="usr-action-btn" title="Kupon Kopyala" onclick="showCouponModal(\'' + c.couponCode + '\')"><i class="bi bi-clipboard"></i></button>' : '') +
              (c.status === 'active' ? '<button class="usr-action-btn" title="Duraklat" onclick="togglePause(' + c.id + ')"><i class="bi bi-pause-circle"></i></button>' : '') +
              (c.status === 'paused' ? '<button class="usr-action-btn success" title="Aktif Et" onclick="togglePause(' + c.id + ')"><i class="bi bi-lightning-charge"></i></button>' : '') +
              '<button class="usr-action-btn danger" title="Sil" onclick="openDeleteCampaign(' + c.id + ')"><i class="bi bi-trash"></i></button>' +
            '</div>' +
          '</div>' +
        '</div>';

      container.innerHTML += html;
    });

    animateProgressBars();
  }

  /* ==================== TABLE RENDERING ==================== */
  function renderTableView() {
    var tbody = document.getElementById('campaignTableBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    campaigns.forEach(function (c) {
      var usagePercent = c.totalLimit > 0 ? Math.round((c.used / c.totalLimit) * 100) : (c.used > 0 ? 100 : 0);
      var dateStr = c.startDate ? formatDateShort(c.startDate) + ' – ' + formatDateShort(c.endDate) : '—';

      var tr = document.createElement('tr');
      tr.setAttribute('data-status', c.status);
      tr.setAttribute('data-type', c.type);
      tr.setAttribute('data-name', c.name.toLowerCase());
      tr.setAttribute('data-coupon', (c.couponCode || '').toLowerCase());

      tr.innerHTML = '' +
        '<td data-label="Seç"><input type="checkbox" class="usr-checkbox cmp-table-check" data-id="' + c.id + '" onchange="updateBulkSelection()"></td>' +
        '<td data-label="Kampanya">' +
          '<div class="cmp-table-name-cell">' +
            '<div class="cmp-table-icon cmp-table-icon--' + typeColors[c.type] + '"><i class="bi ' + typeIcons[c.type] + '"></i></div>' +
            '<div>' +
              '<strong class="cmp-table-name">' + c.name + '</strong>' +
              (c.couponCode ? '<span class="cmp-table-coupon">' + c.couponCode + '</span>' : '') +
            '</div>' +
          '</div>' +
        '</td>' +
        '<td data-label="Tür"><span class="cmp-type-pill cmp-type-pill--' + typeColors[c.type] + '">' + c.typeLabel + '</span></td>' +
        '<td data-label="İndirim"><span class="cmp-table-discount">' + c.discountDisplay + '</span></td>' +
        '<td data-label="Tarih" class="d-none d-lg-table-cell"><span class="cmp-table-date">' + dateStr + '</span></td>' +
        '<td data-label="Kullanım">' +
          '<div class="cmp-table-usage">' +
            '<span>' + c.used.toLocaleString('tr-TR') + (c.totalLimit > 0 ? '/' + c.totalLimit.toLocaleString('tr-TR') : '') + '</span>' +
            '<div class="cmp-table-progress"><div class="cmp-table-progress-fill" data-width="' + Math.min(usagePercent, 100) + '"></div></div>' +
          '</div>' +
        '</td>' +
        '<td data-label="Durum"><span class="cmp-status-badge cmp-status--' + c.status + '"><i class="bi ' + statusIcons[c.status] + '"></i> ' + statusLabels[c.status] + '</span></td>' +
        '<td data-label="Gelir" class="d-none d-xl-table-cell"><span class="cmp-table-revenue">' + (c.revenue > 0 ? '₺' + c.revenue.toLocaleString('tr-TR') : '—') + '</span></td>' +
        '<td data-label="İşlem">' +
          '<div class="usr-actions">' +
            '<button class="usr-action-btn" title="Detay" onclick="openCampaignDetail(' + c.id + ')"><i class="bi bi-eye"></i></button>' +
            '<button class="usr-action-btn" title="Düzenle" onclick="openEditCampaign(' + c.id + ')"><i class="bi bi-pencil"></i></button>' +
            '<button class="usr-action-btn danger" title="Sil" onclick="openDeleteCampaign(' + c.id + ')"><i class="bi bi-trash"></i></button>' +
          '</div>' +
        '</td>';

      tbody.appendChild(tr);
    });

    animateTableProgress();
  }

  /* ==================== VIEW TOGGLE ==================== */
  window.switchView = function (view) {
    currentView = view;
    var gridEl  = document.getElementById('campaignGridView');
    var tableEl = document.getElementById('campaignTableView');
    var gridBtn = document.getElementById('viewGrid');
    var tableBtn = document.getElementById('viewTable');

    if (view === 'grid') {
      gridEl.classList.remove('d-none');
      tableEl.classList.add('d-none');
      gridBtn.classList.add('active');
      tableBtn.classList.remove('active');
      renderGridView();
    } else {
      gridEl.classList.add('d-none');
      tableEl.classList.remove('d-none');
      gridBtn.classList.remove('active');
      tableBtn.classList.add('active');
      renderTableView();
    }
  };

  /* ==================== FILTER ==================== */
  window.filterByStatus = function (status, btn) {
    document.querySelectorAll('.cl-status-tab').forEach(function (t) { t.classList.remove('active'); });
    btn.classList.add('active');

    var selector = currentView === 'grid' ? '#campaignGridView [data-status]' : '#campaignTableBody tr[data-status]';
    document.querySelectorAll(selector).forEach(function (el) {
      el.style.display = (status === 'all' || el.getAttribute('data-status') === status) ? '' : 'none';
    });
  };

  window.filterCampaigns = function () {
    var q    = (document.getElementById('campaignSearch').value || '').toLowerCase().trim();
    var type = document.getElementById('filterType').value;

    var selector = currentView === 'grid' ? '#campaignGridView [data-status]' : '#campaignTableBody tr[data-status]';
    document.querySelectorAll(selector).forEach(function (el) {
      var matchName = !q || (el.getAttribute('data-name') || '').indexOf(q) !== -1 || (el.getAttribute('data-coupon') || '').indexOf(q) !== -1;
      var matchType = !type || el.getAttribute('data-type') === type;
      el.style.display = (matchName && matchType) ? '' : 'none';
    });
  };

  window.sortCampaigns = function () {
    // placeholder for sort — show toast
    var val = document.getElementById('filterSort').value;
    showToast('Sıralama: ' + val, 'info');
  };

  window.sortCampaignsBy = function (col) {
    showToast(col + ' sütununa göre sıralandı', 'info');
  };

  window.resetFilters = function () {
    document.getElementById('campaignSearch').value = '';
    document.getElementById('filterType').value = '';
    document.getElementById('filterSort').value = 'newest';
    document.querySelectorAll('.cl-status-tab').forEach(function (t, i) {
      t.classList.toggle('active', i === 0);
    });

    var selector = currentView === 'grid' ? '#campaignGridView [data-status]' : '#campaignTableBody tr[data-status]';
    document.querySelectorAll(selector).forEach(function (el) { el.style.display = ''; });
    showToast('Filtreler sıfırlandı', 'info');
  };

  /* ==================== BULK SELECTION ==================== */
  window.toggleSelectAllTable = function (master) {
    document.querySelectorAll('.cmp-table-check').forEach(function (cb) { cb.checked = master.checked; });
    updateBulkSelection();
  };

  window.updateBulkSelection = function () {
    var checks = document.querySelectorAll('.cmp-card-check:checked, .cmp-table-check:checked');
    var bulk = document.getElementById('bulkActions');
    var countEl = document.getElementById('selectedCount');
    if (checks.length > 0) {
      bulk.classList.remove('d-none');
      countEl.textContent = checks.length;
    } else {
      bulk.classList.add('d-none');
    }
  };

  window.bulkAction = function (action) {
    var count = document.querySelectorAll('.cmp-card-check:checked, .cmp-table-check:checked').length;
    if (!count) return;
    var labels = { activate: 'aktif yapıldı', pause: 'duraklatıldı', 'delete': 'silindi' };
    showToast(count + ' kampanya ' + (labels[action] || action), 'success');
    document.querySelectorAll('.cmp-card-check:checked, .cmp-table-check:checked').forEach(function (cb) { cb.checked = false; });
    updateBulkSelection();
  };

  /* ==================== CAMPAIGN DETAIL MODAL ==================== */
  window.openCampaignDetail = function (id) {
    var c = campaigns.find(function (x) { return x.id === id; });
    if (!c) return;

    document.getElementById('detailCmpName').textContent = c.name;
    var dateStr = c.startDate ? formatDateLong(c.startDate) + ' — ' + formatDateLong(c.endDate) : 'Tarih belirlenmedi';
    var usagePercent = c.totalLimit > 0 ? Math.round((c.used / c.totalLimit) * 100) : (c.used > 0 ? 100 : 0);
    var progressColor = usagePercent >= 90 ? 'var(--neon-red)' : usagePercent >= 60 ? 'var(--neon-orange)' : 'var(--teal-primary)';

    var body = document.getElementById('detailModalBody');
    body.innerHTML = '' +
      '<div class="cmp-detail-hero cmp-card-header--' + c.color + '">' +
        '<div class="cmp-detail-hero-icon"><i class="bi ' + typeIcons[c.type] + '"></i></div>' +
        '<div class="cmp-detail-hero-value">' + c.discountDisplay + '</div>' +
        '<span class="cmp-card-status cmp-status--' + c.status + '"><i class="bi ' + statusIcons[c.status] + '"></i> ' + statusLabels[c.status] + '</span>' +
      '</div>' +

      '<p class="cmp-detail-desc mt-3">' + c.description + '</p>' +

      (c.couponCode ? '<div class="cmp-detail-coupon"><i class="bi bi-ticket-perforated me-2"></i><strong>' + c.couponCode + '</strong><button class="btn-glass btn-sm ms-2" onclick="copyCouponToClipboard(\'' + c.couponCode + '\')"><i class="bi bi-clipboard"></i> Kopyala</button></div>' : '') +

      '<div class="ord-detail-section mt-3">' +
        '<h6 class="ord-section-title"><i class="bi bi-info-circle me-2"></i>Kampanya Bilgileri</h6>' +
        '<div class="ord-info-row"><span>Tür</span><strong>' + c.typeLabel + '</strong></div>' +
        '<div class="ord-info-row"><span>İndirim</span><strong>' + c.discountDisplay + '</strong></div>' +
        '<div class="ord-info-row"><span>Tarih Aralığı</span><strong>' + dateStr + '</strong></div>' +
        '<div class="ord-info-row"><span>Kategoriler</span><strong>' + c.categories.join(', ') + '</strong></div>' +
        '<div class="ord-info-row"><span>Hedef Grup</span><strong>' + c.targetGroup + '</strong></div>' +
      '</div>' +

      '<div class="ord-detail-section mt-3">' +
        '<h6 class="ord-section-title"><i class="bi bi-sliders me-2"></i>Koşullar</h6>' +
        '<div class="ord-info-row"><span>Min. Sepet Tutarı</span><strong>' + (c.minOrder > 0 ? '₺' + c.minOrder.toLocaleString('tr-TR') : 'Yok') + '</strong></div>' +
        '<div class="ord-info-row"><span>Maks. İndirim</span><strong>' + (c.maxDiscount ? '₺' + c.maxDiscount.toLocaleString('tr-TR') : 'Sınırsız') + '</strong></div>' +
        '<div class="ord-info-row"><span>Toplam Limit</span><strong>' + (c.totalLimit > 0 ? c.totalLimit.toLocaleString('tr-TR') : 'Sınırsız') + '</strong></div>' +
        '<div class="ord-info-row"><span>Kişi Başı</span><strong>' + (c.perUserLimit > 0 ? c.perUserLimit : 'Sınırsız') + '</strong></div>' +
        '<div class="ord-info-row"><span>Birleştirilebilir</span><strong>' + (c.stackable ? 'Evet' : 'Hayır') + '</strong></div>' +
        '<div class="ord-info-row"><span>Otomatik Uygulama</span><strong>' + (c.autoApply ? 'Evet' : 'Hayır') + '</strong></div>' +
      '</div>' +

      '<div class="ord-detail-section mt-3">' +
        '<h6 class="ord-section-title"><i class="bi bi-bar-chart me-2"></i>Performans</h6>' +
        '<div class="cmp-detail-perf">' +
          '<div class="cmp-detail-perf-item">' +
            '<span class="cmp-detail-perf-num">' + c.used.toLocaleString('tr-TR') + '</span>' +
            '<span class="cmp-detail-perf-label">Kullanım</span>' +
          '</div>' +
          '<div class="cmp-detail-perf-item">' +
            '<span class="cmp-detail-perf-num">' + usagePercent + '%</span>' +
            '<span class="cmp-detail-perf-label">Doluluk</span>' +
          '</div>' +
          '<div class="cmp-detail-perf-item">' +
            '<span class="cmp-detail-perf-num">₺' + c.revenue.toLocaleString('tr-TR') + '</span>' +
            '<span class="cmp-detail-perf-label">Gelir Etkisi</span>' +
          '</div>' +
        '</div>' +
        '<div class="cmp-card-progress mt-3">' +
          '<div class="cmp-card-progress-bar">' +
            '<div class="cmp-card-progress-fill" style="width:' + Math.min(usagePercent, 100) + '%;background:' + progressColor + '"></div>' +
          '</div>' +
        '</div>' +
      '</div>' +

      '<div class="d-flex gap-2 mt-4">' +
        '<button class="btn-teal" onclick="openEditCampaign(' + c.id + ');bootstrap.Modal.getInstance(document.getElementById(\'campaignDetailModal\')).hide();"><i class="bi bi-pencil me-1"></i>Düzenle</button>' +
        (c.status === 'active' ? '<button class="btn-glass" onclick="togglePause(' + c.id + ');bootstrap.Modal.getInstance(document.getElementById(\'campaignDetailModal\')).hide();"><i class="bi bi-pause-circle me-1"></i>Duraklat</button>' : '') +
        (c.status === 'paused' ? '<button class="btn-glass" onclick="togglePause(' + c.id + ');bootstrap.Modal.getInstance(document.getElementById(\'campaignDetailModal\')).hide();"><i class="bi bi-lightning-charge me-1"></i>Aktif Et</button>' : '') +
      '</div>';

    var modal = new bootstrap.Modal(document.getElementById('campaignDetailModal'));
    modal.show();
  };

  /* ==================== EDIT CAMPAIGN ==================== */
  window.openEditCampaign = function (id) {
    var c = campaigns.find(function (x) { return x.id === id; });
    if (!c) return;
    editTargetId = id;

    document.getElementById('editCmpName').value     = c.name;
    document.getElementById('editCmpStatus').value   = c.status;
    document.getElementById('editCmpDiscount').value  = c.discount;
    document.getElementById('editCmpStart').value     = c.startDate;
    document.getElementById('editCmpEnd').value       = c.endDate;
    document.getElementById('editCmpMinOrder').value  = c.minOrder || '';
    document.getElementById('editCmpLimit').value     = c.totalLimit || '';
    document.getElementById('editCmpDesc').value      = c.description;

    var modal = new bootstrap.Modal(document.getElementById('editCampaignModal'));
    modal.show();
  };

  window.saveEditCampaign = function () {
    var c = campaigns.find(function (x) { return x.id === editTargetId; });
    if (!c) return;
    c.name        = document.getElementById('editCmpName').value || c.name;
    c.status      = document.getElementById('editCmpStatus').value;
    c.discount    = parseInt(document.getElementById('editCmpDiscount').value) || c.discount;
    c.startDate   = document.getElementById('editCmpStart').value || c.startDate;
    c.endDate     = document.getElementById('editCmpEnd').value || c.endDate;
    c.minOrder    = parseInt(document.getElementById('editCmpMinOrder').value) || 0;
    c.totalLimit  = parseInt(document.getElementById('editCmpLimit').value) || 0;
    c.description = document.getElementById('editCmpDesc').value || c.description;

    bootstrap.Modal.getInstance(document.getElementById('editCampaignModal')).hide();
    showToast('"' + c.name + '" kampanyası güncellendi', 'success');
    if (currentView === 'grid') renderGridView(); else renderTableView();
  };

  /* ==================== DELETE CAMPAIGN ==================== */
  window.openDeleteCampaign = function (id) {
    var c = campaigns.find(function (x) { return x.id === id; });
    if (!c) return;
    deleteTargetId = id;
    document.getElementById('deleteCmpName').textContent = c.name;
    var modal = new bootstrap.Modal(document.getElementById('deleteCampaignModal'));
    modal.show();
  };

  window.confirmDeleteCampaign = function () {
    var idx = campaigns.findIndex(function (x) { return x.id === deleteTargetId; });
    if (idx === -1) return;
    var name = campaigns[idx].name;
    campaigns.splice(idx, 1);
    bootstrap.Modal.getInstance(document.getElementById('deleteCampaignModal')).hide();
    showToast('"' + name + '" kampanyası silindi', 'success');
    if (currentView === 'grid') renderGridView(); else renderTableView();
  };

  /* ==================== TOGGLE PAUSE ==================== */
  window.togglePause = function (id) {
    var c = campaigns.find(function (x) { return x.id === id; });
    if (!c) return;
    if (c.status === 'active') {
      c.status = 'paused';
      showToast('"' + c.name + '" duraklatıldı', 'warning');
    } else if (c.status === 'paused') {
      c.status = 'active';
      showToast('"' + c.name + '" aktif edildi', 'success');
    }
    if (currentView === 'grid') renderGridView(); else renderTableView();
  };

  /* ==================== CREATE CAMPAIGN — WIZARD ==================== */
  var wizardStep = 1;

  window.openCreateCampaignModal = function () {
    wizardStep = 1;
    updateWizardUI();
    var modal = new bootstrap.Modal(document.getElementById('createCampaignModal'));
    modal.show();
  };

  window.wizardNext = function () {
    if (wizardStep === 1) {
      var name = (document.getElementById('cmpName').value || '').trim();
      if (!name) { showToast('Kampanya adı gerekli', 'warning'); return; }
    }
    if (wizardStep === 2) {
      populatePreview();
    }
    if (wizardStep === 3) {
      createCampaign();
      return;
    }
    wizardStep++;
    updateWizardUI();
  };

  window.wizardPrev = function () {
    if (wizardStep <= 1) return;
    wizardStep--;
    updateWizardUI();
  };

  function updateWizardUI() {
    for (var i = 1; i <= 3; i++) {
      var panel = document.getElementById('wizardPanel' + i);
      var step  = document.getElementById('wizStep' + i);
      if (panel) panel.classList.toggle('d-none', i !== wizardStep);
      if (step) {
        step.classList.remove('active', 'completed');
        if (i < wizardStep) step.classList.add('completed');
        else if (i === wizardStep) step.classList.add('active');
      }
    }
    var prevBtn = document.getElementById('wizPrevBtn');
    var nextBtn = document.getElementById('wizNextBtn');
    var draftBtn = document.getElementById('wizDraftBtn');
    prevBtn.classList.toggle('d-none', wizardStep === 1);
    draftBtn.classList.toggle('d-none', wizardStep === 3);
    nextBtn.innerHTML = wizardStep === 3 ? '<i class="bi bi-check-lg me-1"></i> Oluştur' : 'İleri <i class="bi bi-arrow-right ms-1"></i>';
  }

  function populatePreview() {
    var typeEl = document.getElementById('cmpType');
    var typeLabel = typeEl.options[typeEl.selectedIndex].text;
    document.getElementById('prevTypeBadge').textContent = typeLabel;
    document.getElementById('prevName').textContent = document.getElementById('cmpName').value || 'İsimsiz';
    document.getElementById('prevDesc').textContent = document.getElementById('cmpDescription').value || 'Açıklama yok';

    var disc = document.getElementById('cmpDiscount').value;
    var type = typeEl.value;
    if (type === 'percentage') {
      document.getElementById('prevDiscount').textContent = '%' + disc;
    } else if (type === 'fixed') {
      document.getElementById('prevDiscount').textContent = '₺' + disc;
    } else if (type === 'freeShipping') {
      document.getElementById('prevDiscount').textContent = 'Ücretsiz Kargo';
    } else {
      document.getElementById('prevDiscount').textContent = disc || '—';
    }

    var sd = document.getElementById('cmpStartDate').value;
    var ed = document.getElementById('cmpEndDate').value;
    document.getElementById('prevDates').textContent = (sd && ed) ? formatDateLong(sd) + ' — ' + formatDateLong(ed) : 'Belirlenmedi';
    document.getElementById('prevMinOrder').textContent = document.getElementById('cmpMinOrder').value ? '₺' + parseInt(document.getElementById('cmpMinOrder').value).toLocaleString('tr-TR') : 'Yok';

    var tg = document.getElementById('cmpTargetGroup');
    document.getElementById('prevTarget').textContent = tg.options[tg.selectedIndex].text;
    document.getElementById('prevLimit').textContent = document.getElementById('cmpTotalLimit').value || 'Sınırsız';
  }

  function createCampaign() {
    bootstrap.Modal.getInstance(document.getElementById('createCampaignModal')).hide();
    showToast('Kampanya başarıyla oluşturuldu!', 'success');
    // Reset form
    document.getElementById('cmpName').value = '';
    document.getElementById('cmpDiscount').value = '';
    document.getElementById('cmpDescription').value = '';
    document.getElementById('cmpStartDate').value = '';
    document.getElementById('cmpEndDate').value = '';
    wizardStep = 1;
    updateWizardUI();
  }

  window.saveDraft = function () {
    bootstrap.Modal.getInstance(document.getElementById('createCampaignModal')).hide();
    showToast('Kampanya taslak olarak kaydedildi', 'info');
    wizardStep = 1;
    updateWizardUI();
  };

  /* ==================== DISCOUNT FIELD TOGGLE ==================== */
  window.toggleDiscountFields = function () {
    var type = document.getElementById('cmpType').value;
    var discountWrap = document.getElementById('discountValueWrap');
    var couponWrap   = document.getElementById('couponCodeWrap');
    var bogoWrap     = document.getElementById('bogoWrap');
    var unit         = document.getElementById('discountUnit');

    discountWrap.classList.remove('d-none');
    couponWrap.classList.add('d-none');
    bogoWrap.classList.add('d-none');

    if (type === 'percentage' || type === 'flash') {
      unit.textContent = '%';
    } else if (type === 'fixed') {
      unit.textContent = '₺';
    } else if (type === 'coupon') {
      unit.textContent = '₺';
      couponWrap.classList.remove('d-none');
    } else if (type === 'freeShipping') {
      discountWrap.classList.add('d-none');
    } else if (type === 'bogo') {
      discountWrap.classList.add('d-none');
      bogoWrap.classList.remove('d-none');
    }
  };

  window.generateCouponCode = function () {
    var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    var code = '';
    for (var i = 0; i < 8; i++) code += chars.charAt(Math.floor(Math.random() * chars.length));
    document.getElementById('cmpCouponCode').value = code;
  };

  /* ==================== COUPON MODAL ==================== */
  window.showCouponModal = function (code) {
    document.getElementById('couponModalCode').textContent = code;
    var modal = new bootstrap.Modal(document.getElementById('couponModal'));
    modal.show();
  };

  window.copyCouponCode = function () {
    var code = document.getElementById('couponModalCode').textContent;
    copyToClipboard(code);
  };

  window.copyCouponToClipboard = function (code) {
    copyToClipboard(code);
  };

  function copyToClipboard(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(function () {
        showToast('Kupon kodu kopyalandı: ' + text, 'success');
      });
    } else {
      var ta = document.createElement('textarea');
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
      showToast('Kupon kodu kopyalandı: ' + text, 'success');
    }
  }

  /* ==================== CALENDAR MODAL ==================== */
  window.openCalendarModal = function () {
    renderCalendar();
    var modal = new bootstrap.Modal(document.getElementById('calendarModal'));
    modal.show();
  };

  function renderCalendar() {
    var container = document.getElementById('campaignCalendar');
    if (!container) return;

    var days = ['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'];
    var html = '<div class="cmp-cal-header">';
    days.forEach(function (d) { html += '<div class="cmp-cal-day-name">' + d + '</div>'; });
    html += '</div><div class="cmp-cal-grid">';

    // February 2026 starts on Sunday (index 6 in Pzt-based), has 28 days
    for (var i = 0; i < 6; i++) html += '<div class="cmp-cal-cell cmp-cal-empty"></div>';

    for (var d = 1; d <= 28; d++) {
      var dateStr = '2026-02-' + (d < 10 ? '0' : '') + d;
      var dayEvents = campaigns.filter(function (c) {
        return c.startDate && c.endDate && dateStr >= c.startDate && dateStr <= c.endDate && (c.status === 'active' || c.status === 'scheduled');
      });

      var isToday = d === 22;
      html += '<div class="cmp-cal-cell' + (isToday ? ' cmp-cal-today' : '') + '">';
      html += '<span class="cmp-cal-num">' + d + '</span>';
      if (dayEvents.length > 0) {
        dayEvents.slice(0, 3).forEach(function (ev) {
          html += '<div class="cmp-cal-event cmp-cal-event--' + ev.color + '" title="' + ev.name + '">' + ev.name.substring(0, 18) + '</div>';
        });
        if (dayEvents.length > 3) {
          html += '<div class="cmp-cal-more">+' + (dayEvents.length - 3) + ' daha</div>';
        }
      }
      html += '</div>';
    }

    html += '</div>';
    container.innerHTML = html;
  }

  /* ==================== PROGRESS BAR ANIMATION ==================== */
  function animateProgressBars() {
    setTimeout(function () {
      document.querySelectorAll('.cmp-card-progress-fill[data-width]').forEach(function (el) {
        el.style.width = el.getAttribute('data-width') + '%';
        if (el.getAttribute('data-color')) el.style.background = el.getAttribute('data-color');
      });
    }, 300);
  }

  function animateTableProgress() {
    setTimeout(function () {
      document.querySelectorAll('.cmp-table-progress-fill[data-width]').forEach(function (el) {
        el.style.width = el.getAttribute('data-width') + '%';
      });
    }, 200);
  }

  /* ==================== DATE HELPERS ==================== */
  var months = ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];
  var monthsFull = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];

  function formatDateShort(str) {
    if (!str) return '—';
    var parts = str.split('-');
    return parseInt(parts[2]) + ' ' + months[parseInt(parts[1]) - 1];
  }

  function formatDateLong(str) {
    if (!str) return '—';
    var parts = str.split('-');
    return parseInt(parts[2]) + ' ' + monthsFull[parseInt(parts[1]) - 1] + ' ' + parts[0];
  }

  /* ==================== TOAST (fallback) ==================== */
  function showToast(message, type) {
    if (window.showToast && window.showToast !== showToast) {
      window.showToast(message, type);
      return;
    }
    var existing = document.querySelector('.ca-toast');
    if (existing) existing.remove();

    type = type || 'success';
    var icons = { success: 'bi-check-circle-fill', danger: 'bi-x-circle-fill', warning: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
    var colors = { success: 'text-neon-green', danger: 'text-neon-red', warning: 'text-neon-orange', info: 'text-neon-blue' };

    var toast = document.createElement('div');
    toast.className = 'ca-toast ca-toast-' + type;
    toast.innerHTML = '<i class="bi ' + (icons[type] || icons.info) + ' ' + (colors[type] || '') + '"></i><span>' + message + '</span><button onclick="this.parentElement.remove()"><i class="bi bi-x"></i></button>';
    document.body.appendChild(toast);
    requestAnimationFrame(function () { toast.classList.add('show'); });
    setTimeout(function () { toast.classList.remove('show'); setTimeout(function () { toast.remove(); }, 350); }, 3500);
  }

})();
