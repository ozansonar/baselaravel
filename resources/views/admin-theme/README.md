# OZAN Admin Panel

Dark-theme, responsive admin panel built with **Bootstrap 5.3.8**, **Chart.js** and vanilla JavaScript.

---

## Table of Contents

- [Tech Stack](#tech-stack)
- [Project Structure](#project-structure)
- [Shared Components](#shared-components)
- [Pages](#pages)
  - [Dashboard (index.html)](#1-dashboard--indexhtml)
  - [Login (login.html)](#2-login--loginhtml)
  - [Register (register.html)](#3-register--registerhtml)
  - [Forgot Password (forgot-password.html)](#4-forgot-password--forgot-passwordhtml)
  - [Modal Gallery (modal.html)](#5-modal-gallery--modalhtml)
  - [Forms (forms.html)](#6-forms--formshtml)
  - [Buttons (buttons.html)](#7-buttons--buttonshtml)
  - [Tables (tables.html)](#8-tables--tableshtml)
  - [Tabs (tabs.html)](#9-tabs--tabshtml)
  - [Offcanvas (offcanvas.html)](#10-offcanvas--offcanvashtml)
  - [Cards (cards.html)](#11-cards--cardshtml)
  - [Badges (badges.html)](#12-badges--badgeshtml)
  - [Accordions (accordions.html)](#13-accordions--accordionshtml)
  - [Alerts & Toasts (alerts.html)](#14-alerts--toasts--alertshtml)
  - [Progress & Spinners (progress-spinners.html)](#15-progress--spinners--progress-spinnershtml)
  - [Users (users.html)](#16-users--usershtml)
  - [User Form (user-form.html)](#17-user-form--user-formhtml)
  - [Messages (messages.html)](#18-messages--messageshtml)
  - [Roles & Permissions (roles-permissions.html)](#19-roles--permissions--roles-permissionshtml)
  - [Settings (settings.html)](#20-settings--settingshtml)
  - [Notifications (notifications.html)](#21-notifications--notificationshtml)
  - [Profile (profile.html)](#22-profile--profilehtml)
  - [Analytics (analytics.html)](#23-analytics--analyticshtml)
  - [Orders (orders.html)](#24-orders--ordershtml)
  - [Products (products.html)](#25-products--productshtml)
  - [Product Add (product-add.html)](#26-product-add--product-addhtml)
  - [Reports (reports.html)](#27-reports--reportshtml)
  - [Campaigns (campaigns.html)](#28-campaigns--campaignshtml)
  - [Content List (content-list.html)](#29-content-list--content-listhtml)
  - [Content Add (content-add.html)](#30-content-add--content-addhtml)
  - [Help Center (help.html)](#31-help-center--helphtml)
- [JavaScript Modules](#javascript-modules)
- [CSS Architecture](#css-architecture)
- [How to Add a New Page](#how-to-add-a-new-page)

---

## Tech Stack

| Technology | Version | Purpose |
|---|---|---|
| Bootstrap | 5.3.8 | Grid, utilities, modal/tab/offcanvas components |
| Bootstrap Icons | 1.13.1 | Icon library |
| Chart.js | 4.4.0 | Dashboard charts (line, doughnut, bar, radar) |
| Quill.js | 1.3.7 | Rich-text editor (forms page) |
| AOS | 2.3.1 | Animate On Scroll (fade-up/down/left/right + delay) |
| Dropzone.js | 5.9.3 | Drag-and-drop file upload (content-add page) |
| Vanilla JS | ES6+ | App logic, wizard, toast, animations |

All dependencies are loaded via CDN -- no build step required.

---

## Project Structure

```
admin-panel-master/
│
├── index.html                 # Dashboard (main page)
│
│  Auth Pages (standalone, no sidebar)
├── login.html                 # Login page
├── register.html              # Registration page
├── forgot-password.html       # Password reset (multi-step)
│
│  Component Showcase Pages
├── modal.html                 # Advanced custom modal gallery (wizard, charts, confirm, gallery)
├── forms.html                 # Form elements showcase
├── buttons.html               # Button variants
├── tables.html                # Table layouts
├── tabs.html                  # Tab & pill navigation
├── offcanvas.html             # Offcanvas (slide-in panels)
├── cards.html                 # Card components
├── badges.html                # Badges & labels
├── accordions.html            # Accordion components
├── alerts.html                # Alerts & toasts
├── progress-spinners.html     # Progress bars & spinners
│
│  Application Pages
├── users.html                 # User management (list + CRUD modals)
├── user-form.html             # User create/edit form (full page)
├── messages.html              # Email-style messaging system
├── roles-permissions.html     # Role & permission management
├── settings.html              # Application settings
├── notifications.html         # Notification center
├── profile.html               # User profile page
├── analytics.html             # Advanced analytics & KPI dashboard
├── orders.html                # Order management (list + detail modals)
├── products.html              # Product management (list + CRUD modals)
├── product-add.html           # Product create/edit form (full page)
├── reports.html               # Report center (generate, schedule, download)
├── campaigns.html             # Campaign management (discounts, coupons)
├── content-list.html          # Content management list
├── content-add.html           # Content create/edit form (Dropzone + sections)
├── help.html                  # Help center (search, FAQ, guides, tickets)
│
│  Styles & Scripts
├── styles.css                 # All custom styles (single file, ~18K lines)
├── app.js                     # Charts, wizard, toast, counters
├── components.js              # Sidebar & navbar injection
│
│  Config
├── .claude/skills/            # AI assistant skill definitions
└── README.md                  # This file
```

---

## Shared Components

### Sidebar (`<aside class="sidebar">`) -- via `components.js`

- **Injected into:** `<div id="sidebar-container"></div>`
- **NOT used on:** Auth pages (login, register, forgot-password)
- **Sections:** Ana Menü, Yönetim, Sistem
- **Features:**
  - Dropdown menu for "Form Elemanları" (`toggleDropdown()`)
  - Active page highlighting (`highlightActiveSidebar()`)
  - Mobile: slides in/out with `.show` class, closes on outside click
  - Badge counters on Siparişler (12) and Mesajlar (5)
  - Footer shows logged-in user avatar + info

#### Sidebar Full Navigation Tree

```
Ana Menü
├── Dashboard                    → index.html
├── Modal Galerisi               → modal.html
├── Form Elemanları (dropdown)
│   ├── Form Kontrol             → forms.html#form-kontrol
│   ├── Select                   → forms.html#select
│   ├── Onay & Radio             → forms.html#checks-radios
│   ├── Aralık (Range)           → forms.html#range
│   ├── Girdi Grubu              → forms.html#input-group
│   ├── Yüzen Etiketler          → forms.html#floating-labels
│   ├── Düzen (Layout)           → forms.html#layout
│   ├── Doğrulama                → forms.html#validation
│   └── Editörler                → forms.html#editors
├── Butonlar                     → buttons.html
├── Tablolar                     → tables.html
├── Sekmeler                     → tabs.html
├── Offcanvas                    → offcanvas.html
├── Kartlar                      → cards.html
├── Rozetler & Etiketler         → badges.html
├── Accordions                   → accordions.html
├── Alerts & Toasts              → alerts.html
├── Progress & Spinners          → progress-spinners.html
├── Analitik                     → analytics.html
├── Siparişler [badge:12]        → orders.html
├── Ürünler (dropdown)
│   ├── Ürün Listesi             → products.html
│   └── Ürün Ekleme              → product-add.html
└── İçerik (dropdown)
    ├── İçerik Listesi           → content-list.html
    └── İçerik Ekleme            → content-add.html

Yönetim
├── Kullanıcılar                 → users.html
├── Roller & İzinler             → roles-permissions.html
├── Kampanyalar                  → campaigns.html
└── Mesajlar [badge:5]           → messages.html

Sistem
├── Ayarlar                      → settings.html
├── Raporlar                     → reports.html
├── Bildirimler                  → notifications.html
└── Yardım                       → help.html

Sidebar Footer (user card)       → profile.html
```

> **Not:** Auth sayfaları (login, register, forgot-password) sidebar ve navbar kullanmaz; geri kalan tüm sayfalar `components.js` ile otomatik enjekte eder.

### Navbar (`<header class="top-navbar">`) -- via `components.js`

- **Injected into:** `<div id="navbar-container"></div>`
- **NOT used on:** Auth pages (login, register, forgot-password)
- **Contains:** hamburger menu (mobile), search input, notification/settings/profile action buttons

---

## Pages

### 1. Dashboard -- `index.html`

> Main overview page with stats, charts and activity feed.

**Stat Cards (4x):**

| Card | Icon | CSS Class |
|---|---|---|
| Toplam Gelir | `bi-currency-dollar` | `.stat-card` + `.stat-icon.revenue` |
| Aktif Kullanıcı | `bi-people` | `.stat-card` + `.stat-icon.users` |
| Siparişler | `bi-cart3` | `.stat-card` + `.stat-icon.orders` |
| Dönüşüm Oranı | `bi-graph-up-arrow` | `.stat-card` + `.stat-icon.conversion` |

**Charts (Chart.js):**

| Chart | Canvas ID | Type | Description |
|---|---|---|---|
| Revenue | `#revenueChart` | Line/Area | Weekly/monthly/yearly revenue comparison |
| Traffic | `#trafficChart` | Doughnut | Traffic source breakdown (5 channels) |
| Orders | `#ordersChart` | Bar | Daily order count |
| Performance | `#performanceChart` | Radar | Speed/Security/SEO/Access/Perf/UX scores |

**Modals:**

| Modal | ID | Trigger | Content |
|---|---|---|---|
| Analytics Detail | `#analyticsModal` | "Detaylı Analiz" button | Full analytics with `#analyticsDetailChart` (line) |
| Quick Add User | `#quickAddUserModal` | "Hızlı Ekle" button | Simple name/email/role form |
| Settings | `#settingsModal` | "Ayarlar" button | Tabs: Genel, Bildirimler, Görünüm |
| Export | `#exportModal` | "Dışa Aktar" button | Format picker: PDF, Excel, CSV, JSON |

**Other sections:**
- Recent Orders table (`.recent-orders`)
- Activity Feed (`.activity-feed`)
- Quick Actions panel (4 icon buttons)
- Revenue chart period switcher: `updateRevenueChart('weekly'|'monthly'|'yearly')`

**Scripts:** `components.js`, `app.js`

---

### 2. Login -- `login.html`

> Standalone login page with animated background (no sidebar/navbar).

**Layout:** Two-panel (brand left, form right), centered on mobile.

**Left panel (branding):**
- Logo, title, tagline
- Feature cards (Güvenli Erişim, Hızlı Yönetim, Responsive Tasarım)
- Stats row (12K+ Kullanıcı, 99.9% Uptime, 50+ Bileşen)

**Right panel (form):**
- Social login buttons (Google, GitHub, Apple)
- Email + password form with "Beni Hatırla" checkbox
- "Şifremi Unuttum" link -> `forgot-password.html`
- "Kayıt Olun" link -> `register.html`

**JS Functions:**
- `togglePass(btn)` -- show/hide password
- `handleLogin(e)` -- submit with loading spinner, redirect to `index.html`

**CSS Classes:** `.auth-body`, `.auth-bg`, `.auth-orb`, `.auth-wrapper`, `.auth-brand-panel`, `.auth-form-panel`, `.auth-social-btn`, `.auth-input-wrap`, `.auth-submit`, `.auth-spinner`

**Scripts:** Inline `<script>` only (no components.js)

---

### 3. Register -- `register.html`

> Standalone registration page with purple theme variant.

**Layout:** Two-panel, same structure as login.

**Left panel:** Different branding (Hızlı Kurulum, Sınırsız Erişim, Takım Yönetimi) + testimonial card.

**Right panel (form):**
- Social login buttons
- Name (first + last), email, password, password confirm
- Password strength indicator (4 bars + text: Zayıf/Orta/İyi/Güçlü)
- Terms & privacy checkbox
- Link to `login.html`

**JS Functions:**
- `togglePass(btn)` -- show/hide password
- `checkStrength(val)` -- password strength scoring (length, uppercase, digit, special)
- `handleRegister(e)` -- validate password match, submit with spinner, redirect to `login.html`

**CSS Classes:** Same auth classes + `.auth-submit.purple`, `.password-strength`, `.strength-bars`, `.strength-bar`

**Scripts:** Inline `<script>` only

---

### 4. Forgot Password -- `forgot-password.html`

> Multi-step password reset flow (4 steps), standalone centered page.

**Steps:**

| Step | ID | Content |
|---|---|---|
| 1. Email | `#step1` | Email input, "Doğrulama Kodu Gönder" button |
| 2. OTP | `#step2` | 6-digit OTP input, countdown timer (60s), resend button |
| 3. New Password | `#step3` | New password + confirm, strength indicator, password rules checklist |
| 4. Success | `#step4` | Success message with "Giriş Yap" button |

**JS Functions:**
- `goStep(n)` -- show step n, hide others
- `sendCode(e)` -- simulate sending OTP, start countdown
- `otpMove(el)` / `otpBack(e, el)` -- OTP input auto-advance and backspace
- OTP paste support (clipboard)
- `startCountdown()` / `resendCode()` -- 60s countdown timer
- `verifyCode(e)` -- validate 6-digit code
- `checkStrength(val)` -- password strength + rule checklist
- `resetPassword(e)` -- validate match, show success step
- `btnLoading(btn, loading)` -- generic button loading state

**CSS Classes:** `.auth-centered`, `.auth-form-centered`, `.auth-reset-icon`, `.auth-step`, `.otp-container`, `.otp-input`, `.otp-timer`, `.auth-resend`, `.auth-password-rules`, `.auth-rule`, `.auth-success`, `.auth-success-icon`

**Scripts:** Inline `<script>` only

---

### 5. Modal Gallery -- `modal.html`

> Showcase of advanced custom modal patterns.

**Modals (8):**

| Modal | ID | Type | Description |
|---|---|---|---|
| User Detail | `#userDetailModal` | Info | Avatar, stats, tabs (Genel Bakış / Aktivite / Ayarlar) |
| Add User Wizard | `#addUserModal` | Wizard (3-step) | Step 1: Personal -> Step 2: Role -> Step 3: Confirm |
| Delete Confirm | `#deleteModal` | Confirm | Red warning dialog with cancel/confirm buttons |
| Analytics | `#analyticsModal` | Chart | 12-month line chart + KPI metrics |
| Settings | `#settingsModal` | Tabs | Genel / Bildirimler / Görünüm tabs |
| Export | `#exportModal` | Form | Format picker (PDF/Excel/CSV/JSON) + options |
| Image Preview | `#imagePreviewModal` | Gallery | Image with zoom/rotate buttons, thumbnails strip |
| Notification Detail | `#notificationModal` | Info | Notification details with action buttons |

**Wizard API (app.js):**
- `wizardNext()` -- advance step, populate confirmation at step 2->3, submit at step 3
- `wizardPrev()` -- go back one step
- `wizardReset()` -- reset to step 1 (auto-called on modal close)
- `updateWizardUI()` -- sync step indicators and button labels

**CSS Classes:** `.modal-custom`, `.wizard-steps`, `.wizard-step`, `.user-detail-modal`, `.delete-modal-body`, `.analytics-modal`, `.settings-modal`, `.export-modal`, `.image-preview-modal`, `.notification-detail-modal`

**Scripts:** `components.js`, `app.js`

---

### 6. Forms -- `forms.html`

> Complete form element reference with 9 sections.

**Sections (anchor-linked from sidebar dropdown):**

| Section | Anchor | Key Elements |
|---|---|---|
| Form Kontrol | `#form-kontrol` | Text, email, password, textarea, file, color, readonly, disabled |
| Select | `#select` | Default, multiple, sm/lg sizes, disabled |
| Onay & Radio | `#checks-radios` | Checkboxes, radios, switches, inline, toggle buttons |
| Aralık (Range) | `#range` | Default, min/max, steps, disabled |
| Girdi Grubu | `#input-group` | Prepend/append icons, buttons, multiple inputs |
| Yüzen Etiketler | `#floating-labels` | Floating label input, textarea, select |
| Düzen (Layout) | `#layout` | Horizontal, inline, column sizing, auto-sizing |
| Doğrulama | `#validation` | Valid/invalid feedback, tooltips |
| Editörler | `#editors` | Quill.js rich-text editor |

**External dependency:** Quill.js (CDN) for `#editors` section only.

**Scripts:** `components.js`, Quill.js (inline init)

---

### 7. Buttons -- `buttons.html`

> Button variant showcase.

**Sections:** Solid, Outline, Sizes, States, Icon Buttons, Block Buttons, Button Groups, Gradient Buttons.

**Custom CSS:** `.btn-gradient-primary`, `.btn-gradient-success`, `.btn-gradient-danger`, `.btn-gradient-warning`

**Scripts:** `components.js` only

---

### 8. Tables -- `tables.html`

> Table layout variations: basic, striped, hoverable, bordered, borderless, compact, color variants, responsive, advanced (status badges + action buttons).

**Scripts:** `components.js` only

---

### 9. Tabs -- `tabs.html`

> Tab and pill navigation: basic tabs, pills, vertical tabs, icon tabs, card tabs, justified/fill.

**Scripts:** `components.js` only

---

### 10. Offcanvas -- `offcanvas.html`

> Slide-in panel variations.

| Offcanvas | ID | Placement | Content |
|---|---|---|---|
| Left | `#offcanvasStart` | `offcanvas-start` | Navigation menu |
| Right | `#offcanvasEnd` | `offcanvas-end` | Notification panel |
| Top | `#offcanvasTop` | `offcanvas-top` | Search bar |
| Bottom | `#offcanvasBottom` | `offcanvas-bottom` | Cookie consent |
| No Backdrop | `#offcanvasNoBackdrop` | `offcanvas-start` | Filter panel |
| Scrollable | `#offcanvasScroll` | `offcanvas-start` | Settings |

**Scripts:** `components.js` only

---

### 11. Cards -- `cards.html`

> Card variations: basic, image cards, horizontal, color cards, card groups, pricing, profile cards, stat cards.

**Scripts:** `components.js` only

---

### 12. Badges -- `badges.html`

> Badge and label variations: color badges, pill, icon, sizes, button badges, status badges, notification badge, removable tags.

**Scripts:** `components.js` only

---

### 13. Accordions -- `accordions.html`

> Accordion variations: basic, always open, flush, icon accordion, FAQ, nested.

**Scripts:** `components.js` only

---

### 14. Alerts & Toasts -- `alerts.html`

> Alert patterns (color, dismissible, icon, rich content) and toast patterns (live, color, auto-dismiss, custom position).

**Toast JS helper** (app.js): `showToast(message, type)` -- programmatic floating toast.

**Scripts:** `components.js`, inline `<script>` for live toast demos

---

### 15. Progress & Spinners -- `progress-spinners.html`

> Progress bars (basic, color, striped, animated, stacked, labeled) and spinners (border, grow, sizes, colors, button spinners).

**Scripts:** `components.js` only

---

### 16. Users -- `users.html`

> User management page with list, stats and CRUD modals.

**Page elements:**
- Search + filter/add buttons
- Stat cards row (4x)
- User table (avatar, role badge, status badge, action dropdown)
- Pagination

**Modals:**

| Modal | ID | Description |
|---|---|---|
| Add User | `#addUserModal` | 3-step wizard (Personal -> Role -> Confirm) |
| View User | `#viewUserModal` | Read-only detail with tabs |
| Edit User | `#editUserModal` | Pre-filled edit form |
| Delete User | `#deleteUserModal` | Red confirm dialog |
| Filter | `#filterModal` | Role, status, department, date filters |
| Export | `#exportModal` | Export format picker |

**CSS Classes:** `.user-management`, `.user-avatar`, `.user-stats`, `.status-badge.active/.inactive/.pending`, `.role-badge`

**Scripts:** `components.js`, `app.js` (wizard + toast)

---

### 17. User Form -- `user-form.html`

> Full-page user create/edit form with vertical section navigation.

**Dual mode:** Create (`user-form.html`) or Edit (`user-form.html?edit=username`). Auto-detects via URL parameter.

**Sections (left nav):**

| Section | ID | Content |
|---|---|---|
| Profil Fotoğrafı | `#section-avatar` | Avatar upload/preview, file input (max 2MB) |
| Kişisel Bilgiler | `#section-personal` | Name, email, phone, birth date, gender, location, department, bio, website, LinkedIn |
| Hesap Bilgileri | `#section-account` | Username, display name, password + confirm (with strength meter), email verification, 2FA |
| Rol & Yetki | `#section-role` | Role select + visual role cards (Admin/Editor/Moderator/User/Viewer), status select |
| İzinler | `#section-permissions` | 3-group permission toggles: İçerik Yönetimi (5), Kullanıcı Yönetimi (5), Sistem Yönetimi (5) |
| Bildirimler | `#section-notifications` | Email notifications (4 toggles) + app notifications (4 toggles) |
| Notlar | `#section-notes` | Admin-only internal notes, tags, priority select |

**Modals:**

| Modal | ID | Description |
|---|---|---|
| Unsaved Changes | `#unsavedModal` | Warning before leaving with unsaved changes |

**JS Functions:**
- `loadUserData(username)` -- populate form from simulated user database (10 users)
- `updatePermissionsByRole(role)` -- auto-set permission toggles based on role presets
- `saveUser()` -- validate required fields, password match, redirect to `users.html`
- `previewAvatar(input)` / `removeAvatar()` -- avatar image preview
- `togglePassword(fieldId, btn)` -- show/hide password
- `scrollToSection(id, el)` -- smooth scroll to section
- `resetForm()` -- reset all fields to defaults or reload edit data
- `saveDraft()` / `showToast(message, type)` -- draft save and notifications

**CSS Classes:** `.stg-layout`, `.stg-nav`, `.stg-nav-item`, `.stg-content`, `.card-dark`, `.card-header-custom`, `.card-body-custom`, `.form-section-header`, `.form-section-icon`, `.stg-label`, `.stg-input`, `.stg-select`, `.stg-textarea`, `.stg-input-group`, `.stg-input-prefix`, `.stg-toggle-list`, `.stg-toggle-item`, `.stg-switch`, `.uf-avatar-section`, `.uf-avatar-preview`, `.uf-role-cards`, `.uf-role-card`, `.uf-perm-grid`, `.uf-perm-group`, `.uf-password-strength`, `.uf-strength-bars`, `.uf-bottom-actions`

**Scripts:** `components.js`, `app.js`, inline `<script>`

---

### 18. Messages -- `messages.html`

> Full email-style messaging system with 3-panel layout.

**Layout:** Folder sidebar | Message list | Message detail.

**Folder sidebar:**

| Folder | Data Attr | Badge |
|---|---|---|
| Gelen Kutusu | `inbox` | Unread count |
| Gönderilenler | `sent` | -- |
| Taslaklar | `draft` | Count |
| Yıldızlı | `starred` | Count |
| Arşiv | `archive` | -- |
| Çöp Kutusu | `trash` | -- |

**Labels:** Önemli (red), İş (blue), Kişisel (green), Finans (orange)

**Message list features:**
- Search input (`searchMessages()`)
- Sort select (newest/oldest/unread)
- Bulk select with actions (mark read, archive, delete)
- Star toggle per message
- Unread indicator (`.msg-item.unread`)
- Archive/delete with slide animation

**Message detail panel:**
- Sender info (avatar, name, email, date)
- Full message body (HTML content)
- Attachments with download buttons
- Quick reply textarea with send button
- Action buttons: reply, forward, print, archive, delete

**Modals:**

| Modal | ID | Description |
|---|---|---|
| Compose | `#composeModal` | New message: to, CC, subject, body, attachments |

**JS Functions (30+):**
- `switchFolder(folder, btn)` -- folder navigation
- `filterByLabel(label)` -- label filtering
- `openMessage(item)` -- load message detail, mark as read
- `closeDetail()` -- close detail panel (mobile)
- `toggleStar(btn)` -- star/unstar message
- `deleteMsg(btn)` / `archiveMsg(btn)` -- animated remove
- `markAllRead()` / `refreshMessages()` -- bulk actions
- `searchMessages(query)` -- real-time search across sender/subject/preview
- `sortMessages(type)` -- sort feedback
- `toggleBulk()` / `bulkMarkRead()` / `bulkArchive()` / `bulkDelete()` -- bulk operations
- `openCompose()` / `replyMessage()` / `forwardMessage()` / `sendMessage()` -- compose flow
- `saveDraft()` / `addAttachment()` / `sendReply()` -- utility functions

**Data:** 16 messages with full detail objects in `messageDetails` map (sender, email, avatar, date, subject, HTML body, attachments).

**CSS Classes:** `.msg-layout`, `.msg-folders`, `.msg-folder-btn`, `.msg-list-panel`, `.msg-list`, `.msg-item`, `.msg-item.unread`, `.msg-star`, `.msg-avatar`, `.msg-sender`, `.msg-subject`, `.msg-preview`, `.msg-label-tag`, `.msg-detail-panel`, `.msg-detail-content`, `.msg-quick-reply`, `.msg-attachments`, `.msg-attachment-item`, `.msg-compose-modal`, `.msg-bulk-bar`, `.msg-empty`

**Scripts:** `components.js`, inline `<script>` (~550 lines)

---

### 19. Roles & Permissions -- `roles-permissions.html`

> Role management with permission matrix.

**Page elements:**
- Role cards grid (Admin, Editor, Moderator, Viewer, Support, Developer)
- Permission matrix table (checkboxes per module per role)

**Modals:**

| Modal | ID | Description |
|---|---|---|
| Add Role | `#addRoleModal` | Name, description, color, permission checkboxes |
| Edit Role | `#editRoleModal` | Pre-filled role editor |
| Delete Role | `#deleteRoleModal` | Confirm dialog with warning |

**CSS Classes:** `.role-card`, `.role-icon`, `.role-badge`, `.permission-matrix`, `.permission-check`

**Scripts:** `components.js` only

---

### 20. Settings -- `settings.html`

> Application settings with vertical tab navigation.

**Tab layout:** Vertical pills on left, content on right.

**Tabs:**

| Tab | Content |
|---|---|
| Genel | Site title, description, URL, language, timezone |
| Bildirimler | Toggle switches for email/push/SMS channels |
| Görünüm | Theme selector (dark/light/auto), color swatches, font size, sidebar position |
| Güvenlik | 2FA, session timeout, password policy, IP whitelist |
| E-posta | SMTP settings: host, port, encryption, username, password |
| Yedekleme | Auto-backup toggle, frequency, retention, storage, manual backup |

**Scripts:** `components.js` only

---

### 21. Notifications -- `notifications.html`

> Notification center with filtering.

**Page elements:**
- Filter tabs (Tümü, Okunmamış, Sistem, Güvenlik)
- Notification cards list with icon, title, message, time, action buttons
- "Daha Fazla Yükle" load-more button

**Modals:**

| Modal | ID | Description |
|---|---|---|
| Notification Settings | `#notifSettingsModal` | Channel + category toggles |
| Filter | `#notifFilterModal` | Type, date, status filters |

**CSS Classes:** `.notification-card`, `.notification-card.unread`, `.notification-icon`, `.notification-actions`

**Scripts:** `components.js` only

---

### 22. Profile -- `profile.html`

> User profile page with cover image, avatar and tabbed content.

**Tab content:**

| Tab | Content |
|---|---|
| Genel Bakış | Bio card + skill tags + personal info |
| Aktivite | Timeline feed (login, upload, comment events) |
| Projeler | Project cards (title, tech tags, progress bar, team avatars) |
| Ayarlar | Edit profile form: avatar, name, email, phone, bio, social links |

**CSS Classes:** `.profile-header`, `.profile-cover`, `.profile-avatar`, `.profile-stats`, `.activity-timeline`, `.timeline-item`, `.project-card`

**Scripts:** `components.js` only

---

### 23. Analytics -- `analytics.html`

> Advanced analytics dashboard with KPI cards, multi-chart breakdowns and live monitoring.

**KPI Cards (4x):** Ziyaretçi, Gelir, Dönüşüm Oranı, Aktif Kullanıcı -- each with trend sparkline.

**Charts (Chart.js):**

| Chart | Canvas ID | Type | Description |
|---|---|---|---|
| Trafik Trendi | `#trafficTrendChart` | Line | Daily visitors (organic/direct/social/paid) |
| Trafik Kaynakları | `#trafficSourceChart` | Doughnut | Channel breakdown |
| Gelir Analizi | `#revenueAnalyticsChart` | Bar | Monthly revenue vs target |
| Dönüşüm Hunisi | `#conversionChart` | Bar (horizontal) | Funnel stages |
| Gerçek Zamanlı | `#realtimeChart` | Line | Live visitor feed (auto-refreshes) |
| Coğrafi Dağılım | table | -- | Top countries by sessions |

**Date range selector:** Son 7/30/90 Gün, Son 6 Ay, Son 1 Yıl, Özel Tarih (`changeDateRange(value)`).

**Modals:**

| Modal | ID | Description |
|---|---|---|
| Canlı İzleme | `#liveModal` | Real-time visitor map + activity feed |

**JS Functions:** `changeDateRange(val)`, `downloadReport(format)`, `openLiveModal()`

**CSS Classes:** `.anl-kpi-card`, `.anl-kpi-icon`, `.anl-kpi-header`, `.anl-kpi-value`, `.anl-kpi-trend`, `.anl-date-range`, `.anl-date-select`, `.anl-chart-card`, `.anl-geo-table`, `.anl-live-modal`

**Scripts:** `components.js`, Chart.js, AOS, inline `<script>`

---

### 24. Orders -- `orders.html`

> Order management page with list view, status tracking and CRUD modals.

**Stat Cards (4x):** Toplam Sipariş, Toplam Gelir, Bekleyen, İptal Edilen.

**Order table columns:** Sipariş No, Müşteri (avatar + name), Ürünler, Toplam, Durum, Tarih, İşlemler.

**Status badges:** `pending` (beklemede), `processing` (işlemde), `shipped` (kargoda), `delivered` (teslim), `cancelled` (iptal).

**Modals:**

| Modal | ID | Description |
|---|---|---|
| Sipariş Detay | `#orderDetailModal` | Full order detail: items, customer info, address, timeline |
| Yeni Sipariş | `#newOrderModal` | Create order: customer search, product picker, address |
| Durum Güncelle | `#statusModal` | Change order status with note |
| Filtre | `#filterModal` | Date range, status, min/max amount filters |
| İptal | `#cancelModal` | Cancel with reason select + note |

**JS Functions:** `openOrderDetail(id)`, `openNewOrderModal()`, `updateStatus(id)`, `cancelOrder(id)`, `exportOrders(format)`

**CSS Classes:** `.ord-status-badge`, `.ord-product-list`, `.ord-timeline`, `.ord-timeline-item`

**Scripts:** `components.js`, AOS, inline `<script>`

---

### 25. Products -- `products.html`

> Product management page with grid/list toggle, advanced filtering and CRUD modals.

**Stat Cards (4x):** Toplam Ürün, Aktif Ürün, Stok Kritik, Toplam Değer.

**View modes:** Grid (`.prod-grid`) and List (`.prod-list`) toggle.

**Product card:** Image, name, category badge, price, stock level, status badge, action buttons.

**Modals:**

| Modal | ID | Description |
|---|---|---|
| Ürün Detay | `#productDetailModal` | Images, full description, specs, stock history |
| Hızlı Düzenle | `#quickEditModal` | Edit price, stock, status inline |
| Sil | `#deleteProductModal` | Confirm delete dialog |
| Filtre | `#filterModal` | Category, price range, stock status, date filters |

**JS Functions:** `toggleView(mode)`, `openProductDetail(id)`, `quickEdit(id)`, `deleteProduct(id)`, `exportProducts(format)`

**CSS Classes:** `.prod-grid`, `.prod-list`, `.prod-card`, `.prod-card-img`, `.prod-card-body`, `.prod-price`, `.prod-stock-badge`, `.prod-actions`

**Scripts:** `components.js`, AOS, inline `<script>`

---

### 26. Product Add -- `product-add.html`

> Full-page product create/edit form with left navigation and section scroll.

**Sections (left nav + mobile select):**

| Section | ID | Content |
|---|---|---|
| Temel Bilgiler | `#section-basic` | Name, description (Quill), category, brand, tags |
| Fiyatlandırma | `#section-pricing` | Price, discounted price, cost, tax rate, currency |
| Stok & Kargo | `#section-stock` | SKU, barcode, stock qty, warehouse, weight, dimensions |
| Medya Yönetimi | `#section-media` | Main image + gallery upload, alt text, sort order |
| Varyantlar | `#section-variants` | Color/size/material variant builder with stock per variant |
| SEO Ayarları | `#section-seo` | Meta title, description, slug, canonical, og:image |
| Gelişmiş Ayarlar | `#section-advanced` | Status, visibility, featured, related products, notes |

**Header actions:** Taslak Kaydet, Önizle, Yayınla.

**JS Functions:** `saveProductDraft()`, `previewProduct()`, `publishProduct()`, `scrollToSection(id, el)`

**Scripts:** `components.js`, AOS, Quill.js, inline `<script>`

---

### 27. Reports -- `reports.html`

> Report center for generating, scheduling and downloading business reports.

**Stat Cards (4x):** Toplam Rapor (284), İndirilen (1,892), Zamanlanmış (23), Ortalama Boyut.

**Report types (cards grid):**

| Report | Icon | Color |
|---|---|---|
| Satış Raporu | `bi-graph-up` | blue |
| Müşteri Raporu | `bi-people` | green |
| Ürün Raporu | `bi-box-seam` | orange |
| Finansal Rapor | `bi-currency-dollar` | teal |
| Stok Raporu | `bi-archive` | purple |
| Pazarlama Raporu | `bi-megaphone` | pink |

**Tabs:** Tüm Raporlar / Zamanlanmış / Şablonlar / Geçmiş.

**Modals:**

| Modal | ID | Description |
|---|---|---|
| Özel Rapor | `#customReportModal` | Report builder: type, date range, columns, format, schedule |
| Rapor Önizle | `#previewModal` | Preview generated report before download |

**Date range selector:** Bu Hafta / Bu Ay / Bu Çeyrek / Bu Yıl / Özel Aralık (`changeGlobalRange(value)`).

**JS Functions:** `openCustomReportModal()`, `generateReport(type)`, `downloadReport(id, format)`, `scheduleReport(id)`, `previewReport(id)`, `changeGlobalRange(val)`

**CSS Classes:** `.rpr-date-range`, `.rpr-date-select`, `.rpr-type-card`, `.rpr-type-icon`, `.rpr-report-row`, `.rpr-status-badge`, `.rpr-schedule-badge`

**Scripts:** `components.js`, AOS, inline `<script>`

---

### 28. Campaigns -- `campaigns.html`

> Campaign management for discounts, coupon codes, flash sales and free shipping offers.

**Stat Cards (4x):** Toplam Kampanya (47), Aktif Kampanya (12), Toplam İndirim, Dönüşüm Oranı.

**Campaign types:** İndirim (percentage/fixed), Kupon Kodu, Flash Sale, Ücretsiz Kargo.

**Campaign list columns:** Kampanya adı, Tür badge, Tarih aralığı, İndirim, Kullanım / Limit, Durum, İşlemler.

**Filters:** Tümü / Aktif / Planlanmış / Sona Ermiş / Taslak (tab pills).

**Modals:**

| Modal | ID | Description |
|---|---|---|
| Yeni Kampanya | `#createCampaignModal` | Wizard: type → details (discount, code, dates, target) → preview |
| Düzenle | `#editCampaignModal` | Pre-filled campaign editor |
| Sil | `#deleteCampaignModal` | Confirm delete dialog |
| Takvim | `#calendarModal` | Monthly calendar view of campaign periods |

**JS Functions:** `openCreateCampaignModal()`, `editCampaign(id)`, `deleteCampaign(id)`, `openCalendarModal()`, `filterCampaigns(status)`

**CSS Classes:** `.cmp-type-badge`, `.cmp-status-badge`, `.cmp-usage-bar`, `.cmp-calendar`, `.cmp-calendar-day`, `.cmp-calendar-event`

**Scripts:** `components.js`, AOS, inline `<script>`

---

### 29. Content List -- `content-list.html`

> Content management list page with filtering, bulk actions and status management.

**Stat Cards (4x):** Toplam İçerik (1,247), Yayında (986), Taslak, Arşiv.

**Table columns:** Başlık (thumbnail + title + excerpt), Kategori, Yazar (avatar), Durum, Tarih, İşlemler.

**Status badges:** `published` (yayında), `draft` (taslak), `archived` (arşiv), `scheduled` (planlanmış).

**Filters:** Arama input, Kategori select, Durum select, Tarih aralığı.

**Bulk actions:** Seçilenleri yayınla, arşivle, sil.

**Modals:**

| Modal | ID | Description |
|---|---|---|
| Sil | `#deleteContentModal` | Confirm delete dialog |
| Filtre | `#filterModal` | Advanced category/author/date filters |
| Dışa Aktar | export dropdown | CSV / JSON / Excel / PDF |

**JS Functions:** `filterContent()`, `bulkAction(action)`, `deleteContent(id)`, `exportData(format)`

**CSS Classes:** `.cnt-thumbnail`, `.cnt-status-badge`, `.cnt-category-badge`, `.cnt-author-avatar`, `.cnt-bulk-bar`

**Scripts:** `components.js`, AOS, inline `<script>`

---

### 30. Content Add -- `content-add.html`

> Full-page content create/edit form with Quill editor, Dropzone media upload and section navigation.

**Sections (left nav + mobile select):**

| Section | ID | Content |
|---|---|---|
| Temel Bilgiler | `#section-basic` | Title, slug, excerpt, category, tags, author |
| İçerik Editörü | `#section-content` | Quill.js rich-text editor (full toolbar) |
| Medya Yönetimi | `#section-media` | Featured image + gallery (Dropzone drag-and-drop) |
| SEO Ayarları | `#section-seo` | Meta title, description, canonical, og:image, keywords |
| Yayın Ayarları | `#section-publish` | Status, visibility, schedule date, comment settings |
| Gelişmiş Ayarlar | `#section-advanced` | Custom CSS class, redirect URL, related content |

**Header actions:** Taslak Kaydet, Önizle, Yayınla.

**JS Functions:** `saveDraft()`, `previewContent()`, `publishContent()`, `scrollToSection(id, el)`

**External dependencies:** Quill.js (rich text editor), Dropzone.js 5.9.3 (media upload).

**Scripts:** `components.js`, Quill.js, Dropzone.js, inline `<script>`

---

### 31. Help Center -- `help.html`

> Help center with hero search, FAQ accordion, guide cards, video tutorials and support ticket creation.

**Sections:**

| Section | ID | Content |
|---|---|---|
| Hero Search | -- | Full-width search bar with popular tags |
| Stat Cards | -- | SSS (89 sorular), Kılavuz (45), Video (28), Ticket (1,234) |
| Hızlı Erişim | -- | 6 category cards (Siparişler, Ürünler, Müşteriler, Raporlar, Entegrasyonlar, Hesap) |
| SSS | `#faq` | Bootstrap accordion with 8+ Q&A items |
| Kılavuzlar | `#guides` | Guide cards with estimated read time and difficulty badge |
| Video Dersler | `#videos` | Video thumbnail cards with duration badge |
| Destek | `#support` | Support channels: live chat, email, phone + ticket form |

**Modals:**

| Modal | ID | Description |
|---|---|---|
| Destek Talebi | `#ticketModal` | Support ticket: category, priority, subject, description, attachments |

**JS Functions:** `searchHelp()`, `submitSearch()`, `quickSearch(tag)`, `openTicketModal()`, `submitTicket()`, `scrollToSection(id)`

**CSS Classes:** `.hlp-search-hero`, `.hlp-search-inner`, `.hlp-search-bar`, `.hlp-search-tags`, `.hlp-tag`, `.hlp-stat-card`, `.hlp-category-card`, `.hlp-guide-card`, `.hlp-video-card`, `.hlp-support-card`

**Scripts:** `components.js`, AOS, inline `<script>`

---

## JavaScript Modules

### `components.js` -- Shared UI Injection

| Function | Description |
|---|---|
| `toggleDropdown(btn)` | Toggle sidebar dropdown menu (global, called via `onclick`) |
| `highlightActiveSidebar()` | Adds `.active` class to current page's sidebar link |
| `initSidebarEvents()` | Click handlers: active state, mobile close, outside-click close |

**How it works:** Replaces `#sidebar-container` and `#navbar-container` placeholder divs with full sidebar/navbar HTML on page load.

### `app.js` -- Dashboard & Interactions

| Function / Feature | Description |
|---|---|
| `colors` object | Shared color palette (teal, purple, blue, pink, orange, green, red) |
| `rgba(hex, alpha)` | Helper: hex color to rgba string |
| Revenue Chart | Line/area with gradient, period switcher |
| Traffic Chart | Doughnut -- 5 traffic sources |
| Orders Chart | Bar -- daily orders |
| Performance Chart | Radar -- 6 metrics |
| Analytics Detail Chart | Line inside `#analyticsModal` (lazy-init on modal open) |
| `updateRevenueChart(period)` | Switch data: `'weekly'` / `'monthly'` / `'yearly'` |
| `wizardNext()` | Advance wizard step, populate confirmation, submit |
| `wizardPrev()` | Go back one wizard step |
| `wizardReset()` | Reset wizard to step 1 |
| `updateWizardUI()` | Sync step indicators and button labels |
| `showToast(message, type)` | Floating toast. Types: `'success'`, `'error'`, `'warning'`, `'info'` |
| `animateCounters()` | Animate `.stat-value` elements from 0 to target |

---

## CSS Architecture

All styles are in `styles.css` (~18,000 lines).

### CSS Custom Properties (`:root`)

```css
--bg-primary: #0f1225       /* Page background */
--bg-card: #1a1f35          /* Card/panel background */
--bg-input: #151933         /* Input field background */
--border-color: rgba(255,255,255,0.08)
--text-primary: #f1f5f9     /* Primary text */
--text-secondary: #94a3b8   /* Secondary/muted text */
--teal-primary: #14b8a6     /* Accent color */
--teal-glow: rgba(20,184,166,0.15)
--neon-purple, --neon-blue, --neon-pink, --neon-red, --neon-orange, --neon-green
```

### CSS Module Prefix Convention

Each application page uses a unique CSS prefix to namespace its component classes and avoid collisions. When adding a new component to a page, always use that page's prefix.

| Prefix | Page / Module | Example |
|---|---|---|
| `anl-` | analytics.html | `.anl-kpi-card`, `.anl-chart-card` |
| `ord-` | orders.html | `.ord-status-badge`, `.ord-timeline` |
| `prod-` | products.html | `.prod-card`, `.prod-stock-badge` |
| `rpr-` | reports.html | `.rpr-type-card`, `.rpr-status-badge` |
| `cmp-` | campaigns.html | `.cmp-type-badge`, `.cmp-calendar` |
| `cnt-` | content-list.html | `.cnt-thumbnail`, `.cnt-status-badge` |
| `hlp-` | help.html | `.hlp-search-hero`, `.hlp-tag` |
| `msg-` | messages.html | `.msg-layout`, `.msg-item` |
| `usr-` | users.html (stat cards) | `.usr-stat-card`, `.usr-stat-icon` |
| `stg-` | settings/form layout | `.stg-layout`, `.stg-nav`, `.stg-input` |
| `uf-` | user-form.html | `.uf-avatar-section`, `.uf-role-card` |
| `auth-` | auth pages (login/register) | `.auth-wrapper`, `.auth-submit` |
| `otp-` | forgot-password.html | `.otp-container`, `.otp-input` |

> **Rule:** When creating a new page `foo.html`, prefix all its scoped classes with `foo-`.

### AOS (Animate On Scroll) Reference

AOS is loaded via CDN on all application pages (not auth pages). Animations are triggered when elements enter the viewport.

**CDN link (in `<head>`):**
```html
<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
```

**Init script (before `</body>`):**
```html
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>AOS.init({ once: true, offset: 60 });</script>
```

**Common data attributes:**

| Attribute | Values | Purpose |
|---|---|---|
| `data-aos` | `fade-up`, `fade-down`, `fade-left`, `fade-right`, `zoom-in` | Animation type |
| `data-aos-delay` | `0`, `50`, `100`, `150`, `200` (ms) | Stagger delay for grid items |
| `data-aos-duration` | `400`, `600`, `800` (ms) | Animation duration |

**Typical pattern for staggered stat cards:**
```html
<div class="col-..." data-aos="fade-up" data-aos-delay="0"> ... </div>
<div class="col-..." data-aos="fade-up" data-aos-delay="100"> ... </div>
<div class="col-..." data-aos="fade-up" data-aos-delay="200"> ... </div>
```

**Page headers & breadcrumbs:** use `data-aos="fade-down"` with `data-aos-duration="400"`.

### Main CSS Class Groups

| Class | Purpose |
|---|---|
| **Layout** | |
| `.sidebar` | Fixed left sidebar (260px, collapsible on mobile) |
| `.top-navbar` | Top bar with search + action buttons |
| `.main-content` | Content area (margin-left: 260px on desktop) |
| `.admin-wrapper` | Flex wrapper for sidebar + main |
| `.page-content` | Inner page padding wrapper |
| **Cards & Containers** | |
| `.content-card` | Dark card with border + radius |
| `.card-dark` | Alternative dark card container |
| `.stat-card` | Dashboard stat card with hover glow |
| `.chart-container` | Chart wrapper (height: 300px) |
| **Modals** | |
| `.modal-custom` | Dark-themed modal override |
| `.wizard-steps` / `.wizard-step` | Step indicator bar |
| **Auth Pages** | |
| `.auth-body` | Auth page body (no sidebar) |
| `.auth-bg` / `.auth-orb` | Animated gradient background orbs |
| `.auth-wrapper` | Two-panel auth layout |
| `.auth-brand-panel` | Left branding panel |
| `.auth-form-panel` | Right form panel |
| `.auth-input-wrap` | Input with icon prefix |
| `.auth-submit` | Primary submit button (color variants: `.purple`, `.orange`, `.blue`, `.green`) |
| `.auth-social-btn` | Social login buttons (`.google`, `.github`, `.apple`) |
| `.otp-container` / `.otp-input` | OTP code input grid |
| `.password-strength` / `.strength-bar` | Password strength indicator |
| **Messages** | |
| `.msg-layout` | 3-panel message layout |
| `.msg-folders` | Folder sidebar |
| `.msg-list-panel` / `.msg-list` | Message list |
| `.msg-item` / `.msg-item.unread` | Message row |
| `.msg-detail-panel` | Message detail viewer |
| `.msg-compose-modal` | Compose new message modal |
| `.msg-bulk-bar` | Bulk action toolbar |
| **User Form** | |
| `.stg-layout` / `.stg-nav` / `.stg-content` | Settings-style layout with left nav |
| `.stg-input` / `.stg-select` / `.stg-textarea` | Custom dark form inputs |
| `.stg-toggle-list` / `.stg-switch` | Toggle switch lists |
| `.uf-avatar-section` | Avatar upload area |
| `.uf-role-cards` / `.uf-role-card` | Clickable role cards |
| `.uf-perm-grid` / `.uf-perm-group` | Permission toggle groups |
| **Shared Buttons** | |
| `.btn-teal` | Primary teal action button (used on all pages for main CTA) |
| `.btn-glass` | Translucent "glass" secondary button |
| `.btn-icon` | Square icon-only button |
| `.btn-gradient-primary/success/danger/warning` | Gradient button variants |
| **Shared Text & Color** | |
| `.text-teal` | Teal accent text (active breadcrumb, highlights) |
| `.text-clr-secondary` | Muted/secondary text color (dropdown items) |
| `.text-neon-blue/green/red/orange/purple` | Neon color text for icons in dropdowns |
| **Shared Navigation** | |
| `.page-header` | Page title + subtitle + action buttons row |
| `.page-title` | H1 page title |
| `.page-subtitle` | Subtitle/description paragraph below title |
| `.breadcrumb-link` | Styled breadcrumb anchor |
| `.nav-badge` | Small count badge inside sidebar link (e.g. Siparişler: 12) |
| **Shared Cards** | |
| `.usr-stat-card` | Reusable stat card (used on users, orders, products, campaigns, reports, content-list pages) |
| `.usr-stat-icon` | Stat card icon wrapper. Variants: `.usr-stat-icon-blue/green/orange/red/purple/teal` |
| `.usr-stat-label` | Small label above the stat number |
| `.usr-stat-value` | Large stat number |
| `.usr-stat-change.positive/.negative` | Trend change line with arrow icon |
| **Shared Dropdowns** | |
| `.dropdown-menu-theme` | Dark-themed Bootstrap dropdown menu override |
| `.dropdown-divider-theme` | Styled divider inside dark dropdown |
| **Common** | |
| `.badge-dot` | Small notification dot |
| `.status-badge` | Status indicator (`.active`/`.inactive`/`.pending`) |
| `.role-badge` | Role label badge |
| `.user-avatar` | Circular user avatar |
| `.notification-card` | Notification list item |
| `.permission-matrix` | Permissions checkbox grid |

### Responsive Breakpoints

| Breakpoint | Behavior |
|---|---|
| `>= 992px` (desktop) | Sidebar visible, main content has left margin |
| `< 992px` (tablet) | Sidebar hidden, hamburger toggle, navbar compact |
| `< 576px` (mobile) | Stat cards stack, charts shrink, auth pages single-column |

---

## Common Reusable Patterns

These patterns appear across multiple pages and should be copy-pasted as-is when building new pages.

### Breadcrumb

```html
<nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
  <ol class="breadcrumb mb-0">
    <li class="breadcrumb-item">
      <a href="index.html" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
    </li>
    <li class="breadcrumb-item active text-teal">Sayfa Adı</li>
  </ol>
</nav>
```

### Page Header with Actions

```html
<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3" data-aos="fade-down">
  <div>
    <h1 class="page-title">Sayfa Başlığı</h1>
    <p class="page-subtitle">Kısa açıklama buraya.</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <button class="btn-glass"><i class="bi bi-download"></i> İkincil Aksiyon</button>
    <button class="btn-teal"><i class="bi bi-plus-lg"></i> Ana Aksiyon</button>
  </div>
</div>
```

### Stat Card (`.usr-stat-card`)

Used on: orders, products, campaigns, reports, content-list, users, help.

```html
<div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
  <div class="usr-stat-card">
    <div class="usr-stat-icon usr-stat-icon-blue">
      <i class="bi bi-box-seam-fill"></i>
    </div>
    <div class="usr-stat-info">
      <span class="usr-stat-label">Toplam Ürün</span>
      <h3 class="usr-stat-value">2.847</h3>
      <span class="usr-stat-change positive"><i class="bi bi-arrow-up-short"></i> 12.5% bu ay</span>
    </div>
  </div>
</div>
```

Icon color variants: `usr-stat-icon-blue`, `usr-stat-icon-green`, `usr-stat-icon-orange`, `usr-stat-icon-red`, `usr-stat-icon-purple`, `usr-stat-icon-teal`.

### Export Dropdown

```html
<div class="dropdown">
  <button class="btn-glass dropdown-toggle" data-bs-toggle="dropdown">
    <i class="bi bi-download"></i> Dışa Aktar
  </button>
  <ul class="dropdown-menu dropdown-menu-end dropdown-menu-theme">
    <li><a class="dropdown-item text-clr-secondary" href="#" onclick="exportData('csv')">
      <i class="bi bi-filetype-csv me-2 text-neon-blue"></i>CSV olarak indir</a></li>
    <li><a class="dropdown-item text-clr-secondary" href="#" onclick="exportData('json')">
      <i class="bi bi-filetype-json me-2 text-neon-orange"></i>JSON olarak indir</a></li>
    <li><a class="dropdown-item text-clr-secondary" href="#" onclick="exportData('excel')">
      <i class="bi bi-file-earmark-excel me-2 text-neon-green"></i>Excel olarak indir</a></li>
    <li><hr class="dropdown-divider dropdown-divider-theme"></li>
    <li><a class="dropdown-item text-clr-secondary" href="#" onclick="exportData('pdf')">
      <i class="bi bi-filetype-pdf me-2 text-neon-red"></i>PDF olarak indir</a></li>
  </ul>
</div>
```

### Dark Card Container

```html
<div class="card-dark mb-4">
  <div class="card-header-custom d-flex align-items-center justify-content-between">
    <h6 class="mb-0"><i class="bi bi-icon-name me-2 text-teal"></i>Bölüm Başlığı</h6>
    <button class="btn-glass btn-sm">İşlem</button>
  </div>
  <div class="card-body-custom">
    <!-- İçerik buraya -->
  </div>
</div>
```

### Confirm Delete Modal

```html
<div class="modal fade" id="deleteXyzModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content modal-custom">
      <div class="modal-body text-center p-4">
        <div class="delete-modal-icon mb-3">
          <i class="bi bi-trash3-fill text-danger fs-2"></i>
        </div>
        <h6 class="mb-2">Silmek istediğinize emin misiniz?</h6>
        <p class="text-clr-secondary small mb-4">Bu işlem geri alınamaz.</p>
        <div class="d-flex gap-2 justify-content-center">
          <button class="btn-glass" data-bs-dismiss="modal">Vazgeç</button>
          <button class="btn btn-danger" onclick="confirmDelete()">Sil</button>
        </div>
      </div>
    </div>
  </div>
</div>
```

### Toast Notification (via `app.js`)

```js
// Types: 'success' | 'error' | 'warning' | 'info'
showToast('Kayıt başarıyla oluşturuldu.', 'success');
showToast('Bir hata oluştu.', 'error');
```

Requires `app.js` to be loaded on the page.

### Left-Nav Section Form Layout (used on user-form, product-add, content-add)

```html
<div class="row g-4 align-items-start">
  <!-- Left nav (desktop only) -->
  <div class="col-lg-3 d-none d-lg-block">
    <div class="stg-nav-inner position-sticky stg-nav-sticky">
      <a href="#section-basic" class="stg-nav-item active" onclick="scrollToSection('section-basic', this)">
        <i class="bi bi-info-circle"></i> Temel Bilgiler
      </a>
      <a href="#section-advanced" class="stg-nav-item" onclick="scrollToSection('section-advanced', this)">
        <i class="bi bi-gear"></i> Gelişmiş
      </a>
    </div>
  </div>
  <!-- Main content -->
  <div class="col-lg-9">
    <div id="section-basic" class="card-dark mb-4"> ... </div>
    <div id="section-advanced" class="card-dark mb-4"> ... </div>
  </div>
</div>
```

---

## How to Add a New Page

### Step-by-step checklist

1. Create `your-page.html` in the project root.
2. Pick a **CSS prefix** for page-scoped classes (e.g. `xyz-` for `xyz.html`). Add all scoped CSS to `styles.css` under the comment `/* === XYZ PAGE === */`.
3. Add the full HTML using the **Page Template** below.
4. Register a sidebar link in `components.js` → `sidebarHTML` string (see step 4).
5. If the page uses Chart.js, add `<script src="app.js"></script>` and optionally AOS.
6. Add `data-aos` attributes to stat cards and section headers for entrance animations.

---

### Page Template (with SEO + AOS)

```html
<!DOCTYPE html>
<html lang="tr" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sayfa Adı - OZAN Admin</title>
  <meta name="description" content="Sayfanın kısa açıklaması (160 karakter).">
  <link rel="canonical" href="https://example.com/your-page.html">
  <!-- Open Graph -->
  <meta property="og:title" content="Sayfa Adı - OZAN Admin">
  <meta property="og:description" content="Sayfanın kısa açıklaması.">
  <meta property="og:type" content="website">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
  <!-- AOS (include on all non-auth pages) -->
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
  <link href="styles.css" rel="stylesheet">
</head>
<body>
<div class="admin-wrapper">
  <div id="sidebar-container"></div>
  <main class="main-content">
    <div id="navbar-container"></div>
    <div class="page-content">

      <!-- Breadcrumb -->
      <nav aria-label="breadcrumb" class="mb-3" data-aos="fade-down" data-aos-duration="400">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item">
            <a href="index.html" class="breadcrumb-link"><i class="bi bi-house me-1"></i>Ana Sayfa</a>
          </li>
          <li class="breadcrumb-item active text-teal">Sayfa Adı</li>
        </ol>
      </nav>

      <!-- Page header -->
      <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4" data-aos="fade-down">
        <div>
          <h1 class="page-title">Sayfa Başlığı</h1>
          <p class="page-subtitle">Sayfanın kısa açıklaması.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <button class="btn-teal"><i class="bi bi-plus-lg"></i> Yeni Ekle</button>
        </div>
      </div>

      <!-- Stat cards row (optional) -->
      <div class="row g-3 mb-4">
        <div class="col-xxl-3 col-xl-6 col-sm-6" data-aos="fade-up" data-aos-delay="0">
          <div class="usr-stat-card">
            <div class="usr-stat-icon usr-stat-icon-blue"><i class="bi bi-grid-fill"></i></div>
            <div class="usr-stat-info">
              <span class="usr-stat-label">Etiket</span>
              <h3 class="usr-stat-value">0</h3>
              <span class="usr-stat-change positive"><i class="bi bi-arrow-up-short"></i> 0% bu ay</span>
            </div>
          </div>
        </div>
        <!-- Repeat with data-aos-delay="100", "200", "300" for each card -->
      </div>

      <!-- Content section -->
      <div class="card-dark mb-4" data-aos="fade-up">
        <div class="card-header-custom d-flex align-items-center justify-content-between">
          <h6 class="mb-0"><i class="bi bi-list-ul me-2 text-teal"></i>Bölüm Başlığı</h6>
        </div>
        <div class="card-body-custom">
          <!-- İçerik buraya -->
        </div>
      </div>

    </div><!-- /.page-content -->
  </main>
</div><!-- /.admin-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="components.js"></script>
<!-- <script src="app.js"></script>  ← only if you need showToast(), charts, or wizard -->
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
  AOS.init({ once: true, offset: 60 });
</script>
</body>
</html>
```

---

### Step 4: Add sidebar link in `components.js`

Open `components.js`, find the `sidebarHTML` template string, and add your link inside the correct `<li>` section:

```js
// Ana Menü bölümüne:
<a href="your-page.html" class="nav-link"><i class="bi bi-icon-name"></i> Sayfa Adı</a>

// Dropdown içine (Ürünler, İçerik gibi):
<li class="nav-dropdown-item">
  <a href="your-page.html" class="nav-link"><i class="bi bi-icon-name"></i> Alt Sayfa</a>
</li>

// Badge ile (bildirim sayısı):
<a href="your-page.html" class="nav-link">
  <i class="bi bi-icon-name"></i> Sayfa <span class="nav-badge">5</span>
</a>
```

---

### Quick rules for AI when writing page code

| Rule | Detail |
|---|---|
| CSS prefix | All page-scoped classes must use `xyz-` prefix (e.g. `xyz-card`, `xyz-badge`) |
| AOS | Add `data-aos="fade-up"` + stagger `data-aos-delay` to every stat card; `data-aos="fade-down"` to breadcrumb/header |
| Stat cards | Always use `.usr-stat-card` + `.usr-stat-icon-{color}` — never custom stat card HTML |
| Export button | Always use `.btn-glass.dropdown-toggle` + `.dropdown-menu-theme` pattern |
| Inline style | NEVER use `style="..."` — always add a class to `styles.css` |
| Toast | Call `showToast(msg, type)` from `app.js`; never build custom toast HTML |
| Dark modal | Add `.modal-custom` to `.modal-content`; use `.btn-glass` for cancel, `.btn-teal` or `.btn-danger` for confirm |
| No jQuery | Vanilla JS only; use `document.querySelector`, `fetch`, `classList` |
