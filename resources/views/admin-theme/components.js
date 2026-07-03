// ==================== SHARED COMPONENTS ====================
// Sidebar ve Navbar bileşenlerini tüm sayfalara enjekte eder.
// Her sayfada tekrarlanan HTML'i ortadan kaldırır.

// Global: Sidebar dropdown toggle (onclick handler'dan çağrılır)
function toggleDropdown(btn) {
  var dropdown = btn.closest('.nav-dropdown');
  // Diğer açık dropdown'ları kapat
  document.querySelectorAll('.nav-dropdown.open').forEach(function (dd) {
    if (dd !== dropdown) dd.classList.remove('open');
  });
  dropdown.classList.toggle('open');
}

(function () {
  'use strict';

  // ---- Sidebar HTML ----
  const sidebarHTML = `
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="sidebar-logo">O</div>
      <div class="sidebar-brand">
        <h5>OZAN</h5>
        <span>Admin Panel</span>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-title">Ana Menu</div>
      <a href="index.html" class="nav-link"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
      <a href="modal.html" class="nav-link"><i class="bi bi-window-stack"></i> Modal Galerisi</a>
      <div class="nav-dropdown">
        <button class="nav-dropdown-toggle" onclick="toggleDropdown(this)">
          <i class="bi bi-ui-checks-grid"></i> Form Elemanları
          <i class="bi bi-chevron-down nav-dropdown-arrow"></i>
        </button>
        <div class="nav-dropdown-menu">
          <a href="forms.html#form-kontrol" class="nav-link"><i class="bi bi-input-cursor-text"></i> Form Kontrol</a>
          <a href="forms.html#select" class="nav-link"><i class="bi bi-menu-button-wide"></i> Select</a>
          <a href="forms.html#checks-radios" class="nav-link"><i class="bi bi-ui-checks"></i> Onay & Radio</a>
          <a href="forms.html#range" class="nav-link"><i class="bi bi-sliders"></i> Aralık (Range)</a>
          <a href="forms.html#input-group" class="nav-link"><i class="bi bi-input-cursor"></i> Girdi Grubu</a>
          <a href="forms.html#floating-labels" class="nav-link"><i class="bi bi-badge-tm"></i> Yüzen Etiketler</a>
          <a href="forms.html#layout" class="nav-link"><i class="bi bi-layout-text-window"></i> Düzen (Layout)</a>
          <a href="forms.html#validation" class="nav-link"><i class="bi bi-shield-check"></i> Doğrulama</a>
          <a href="forms.html#editors" class="nav-link"><i class="bi bi-pencil-square"></i> Editörler</a>
        </div>
      </div>
      <a href="buttons.html" class="nav-link"><i class="bi bi-hand-index-thumb"></i> Butonlar</a>
      <a href="tables.html" class="nav-link"><i class="bi bi-table"></i> Tablolar</a>
      <a href="tabs.html" class="nav-link"><i class="bi bi-segmented-nav"></i> Sekmeler</a>
      <a href="offcanvas.html" class="nav-link"><i class="bi bi-layout-sidebar-reverse"></i> Offcanvas</a>
      <a href="cards.html" class="nav-link"><i class="bi bi-card-heading"></i> Kartlar</a>
      <a href="badges.html" class="nav-link"><i class="bi bi-bookmark-star"></i> Rozetler & Etiketler</a>
      <a href="accordions.html" class="nav-link"><i class="bi bi-chevron-bar-expand"></i> Accordions</a>
      <a href="alerts.html" class="nav-link"><i class="bi bi-bell-fill"></i> Alerts & Toasts</a>
      <a href="progress-spinners.html" class="nav-link"><i class="bi bi-hourglass-split"></i> Progress & Spinners</a>
      <a href="analytics.html" class="nav-link"><i class="bi bi-bar-chart-line"></i> Analitik</a>
      <a href="orders.html" class="nav-link"><i class="bi bi-cart3"></i> Siparişler <span class="nav-badge">12</span></a>
      <div class="nav-dropdown">
        <button class="nav-dropdown-toggle" onclick="toggleDropdown(this)">
          <i class="bi bi-box-seam"></i> Ürünler
          <i class="bi bi-chevron-down nav-dropdown-arrow"></i>
        </button>
        <div class="nav-dropdown-menu">
          <a href="products.html" class="nav-link"><i class="bi bi-list-ul"></i> Ürün Listesi</a>
          <a href="product-add.html" class="nav-link"><i class="bi bi-plus-circle"></i> Ürün Ekleme</a>
        </div>
      </div>

      <div class="nav-dropdown">
        <button class="nav-dropdown-toggle" onclick="toggleDropdown(this)">
          <i class="bi bi-journal-richtext"></i> İçerik Yönetimi
          <i class="bi bi-chevron-down nav-dropdown-arrow"></i>
        </button>
        <div class="nav-dropdown-menu">
          <a href="content-list.html" class="nav-link"><i class="bi bi-list-ul"></i> İçerik Listesi</a>
          <a href="content-add.html" class="nav-link"><i class="bi bi-plus-circle"></i> İçerik Ekleme</a>
        </div>
      </div>

      <div class="nav-section-title">Yönetim</div>
      <a href="users.html" class="nav-link"><i class="bi bi-people"></i> Kullanıcılar</a>
      <a href="roles-permissions.html" class="nav-link"><i class="bi bi-shield-check"></i> Roller & İzinler</a>
      <a href="campaigns.html" class="nav-link"><i class="bi bi-megaphone"></i> Kampanyalar</a>
      <a href="messages.html" class="nav-link"><i class="bi bi-chat-dots"></i> Mesajlar <span class="nav-badge">5</span></a>

      <div class="nav-section-title">Sistem</div>
      <a href="settings.html" class="nav-link"><i class="bi bi-gear"></i> Ayarlar</a>
      <a href="reports.html" class="nav-link"><i class="bi bi-file-earmark-text"></i> Raporlar</a>
      <a href="notifications.html" class="nav-link"><i class="bi bi-bell"></i> Bildirimler</a>
      <a href="help.html" class="nav-link"><i class="bi bi-question-circle"></i> Yardım</a>
    </nav>

    <div class="sidebar-footer">
      <a href="profile.html" class="sidebar-user text-decoration-none">
        <div class="sidebar-user-avatar">OZ</div>
        <div class="sidebar-user-info">
          <h6>Ozan Sonar</h6>
          <span>Super Admin</span>
        </div>
      </a>
    </div>
  </aside>`;

  // ---- Navbar HTML ----
  const navbarHTML = `
    <header class="top-navbar" id="topNavbar">
      <div class="d-flex align-items-center gap-3">
        <button type="button" class="btn-icon d-lg-none" aria-label="Menüyü aç/kapat" onclick="document.getElementById('sidebar').classList.toggle('show')">
          <i class="bi bi-list"></i>
        </button>
        <button type="button" class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="Menüyü aç/kapat" onclick="toggleSidebar()">
          <i class="bi bi-layout-sidebar-inset" id="sidebarToggleIcon"></i>
        </button>
      </div>
      <div class="navbar-actions">
        <button type="button" class="theme-toggle-btn" id="themeToggleBtn" aria-label="Tema değiştir" onclick="toggleTheme()">
          <i class="bi bi-moon-stars-fill" id="themeToggleIcon"></i>
        </button>
        <button type="button" class="nav-action-btn" aria-label="Bildirimler"><i class="bi bi-bell"></i><span class="badge-dot"></span></button>
        <button type="button" class="nav-action-btn" aria-label="Ayarlar"><i class="bi bi-gear"></i></button>
        <button type="button" class="nav-action-btn" aria-label="Profil"><i class="bi bi-person-circle"></i></button>
      </div>
    </header>`;

  // ---- Inject Sidebar ----
  const sidebarContainer = document.getElementById('sidebar-container');
  if (sidebarContainer) {
    sidebarContainer.outerHTML = sidebarHTML;
    highlightActiveSidebar();
  }

  // ---- Inject Navbar ----
  const navbarContainer = document.getElementById('navbar-container');
  if (navbarContainer) {
    navbarContainer.outerHTML = navbarHTML;
  }

  // ---- Highlight Active Sidebar Link ----
  function highlightActiveSidebar() {
    const page = window.location.pathname.split('/').pop() || 'index.html';

    // Handle dropdown pages (forms, content, products)
    var dropdownPages = document.querySelectorAll('.nav-dropdown-menu .nav-link');
    var foundInDropdown = false;

    dropdownPages.forEach(function (link) {
      var href = link.getAttribute('href');
      // Match exact page or page with hash
      if (href === page || href.split('#')[0] === page) {
        link.classList.add('active');
        var dropdown = link.closest('.nav-dropdown');
        if (dropdown) {
          dropdown.classList.add('open');
          var toggle = dropdown.querySelector('.nav-dropdown-toggle');
          if (toggle) toggle.classList.add('active');
        }
        foundInDropdown = true;
      }
    });

    // Highlight active sub-link based on hash (for forms.html)
    if (page === 'forms.html') {
      var hash = window.location.hash;
      if (hash) {
        dropdownPages.forEach(function (link) {
          if (link.getAttribute('href').includes(hash)) {
            link.classList.add('active');
          }
        });
      }
      return;
    }

    if (foundInDropdown) return;

    // Handle other pages
    document.querySelectorAll('.sidebar-nav > .nav-link').forEach(function (link) {
      var href = link.getAttribute('href');
      if (href === page) {
        link.classList.add('active');
      }
    });
  }

  // ---- Sidebar Event Listeners ----
  function initSidebarEvents() {
    document.querySelectorAll('.sidebar-nav .nav-link').forEach(function (link) {
      link.addEventListener('click', function (e) {
        var href = this.getAttribute('href');
        if (href === '#') {
          e.preventDefault();
        }
        document.querySelectorAll('.sidebar-nav .nav-link').forEach(function (l) {
          l.classList.remove('active');
        });
        this.classList.add('active');
        if (window.innerWidth < 992) {
          document.getElementById('sidebar').classList.remove('show');
        }
      });
    });

    // Click outside sidebar to close (mobile)
    document.addEventListener('click', function (e) {
      var sidebar = document.getElementById('sidebar');
      if (
        sidebar &&
        window.innerWidth < 992 &&
        sidebar.classList.contains('show') &&
        !sidebar.contains(e.target) &&
        !e.target.closest('.btn-icon')
      ) {
        sidebar.classList.remove('show');
      }
    });
  }

  initSidebarEvents();

  // ---- Sidebar Toggle (Desktop) ----
  window.toggleSidebar = function () {
    var sidebar = document.getElementById('sidebar');
    var mainContent = document.querySelector('.main-content');
    if (!sidebar) return;
    sidebar.classList.toggle('collapsed');
    if (mainContent) mainContent.classList.toggle('sidebar-collapsed');
  };

  // ---- Theme Toggle (Dark / Light) ----
  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    var icon = document.getElementById('themeToggleIcon');
    if (icon) {
      icon.className = theme === 'light' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
    }
  }

  // Apply saved theme on load
  var savedTheme = localStorage.getItem('admin-theme') || 'dark';
  applyTheme(savedTheme);

  window.toggleTheme = function () {
    var current = document.documentElement.getAttribute('data-theme') || 'dark';
    var next = current === 'dark' ? 'light' : 'dark';
    applyTheme(next);
    localStorage.setItem('admin-theme', next);
  };
})();
