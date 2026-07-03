(function () {
  'use strict';

  var activeMessageId = null;

  /* ==================== INIT ==================== */
  document.addEventListener('DOMContentLoaded', function () {
    animateCounters();

    var searchInput = document.getElementById('msgSearchInput');
    if (searchInput) {
      searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          document.getElementById('searchForm').submit();
        }
      });
    }
  });

  /* ==================== COUNTER ANIMATION ==================== */
  function animateCounters() {
    var statValues = document.querySelectorAll('.usr-stat-value');
    statValues.forEach(function (el) {
      var target = parseInt(el.getAttribute('data-count'), 10);
      if (isNaN(target) || target === 0) return;
      var duration  = 1400;
      var startTime = null;
      function step(ts) {
        if (!startTime) startTime = ts;
        var progress = Math.min((ts - startTime) / duration, 1);
        var eased    = 1 - Math.pow(1 - progress, 3);
        el.textContent = Math.floor(eased * target).toLocaleString('tr-TR');
        if (progress < 1) requestAnimationFrame(step);
        else el.textContent = target.toLocaleString('tr-TR');
      }
      requestAnimationFrame(step);
    });
  }

  /* ==================== OPEN MESSAGE (AJAX) ==================== */
  window.openMessage = function (id) {
    activeMessageId = id;

    // Set active item
    document.querySelectorAll('.msg-item').forEach(function (i) { i.classList.remove('active'); });
    var item = document.querySelector('.msg-item[data-id="' + id + '"]');
    if (item) {
      item.classList.add('active');
      item.classList.remove('unread');
    }

    // Show detail panel on mobile
    document.getElementById('msgListPanel').classList.add('detail-open');

    var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    fetch('/admin/contact-messages/' + id, {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      }
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
      document.getElementById('detailSubject').textContent = data.subject;
      document.getElementById('detailSender').textContent = data.name;
      document.getElementById('detailEmail').textContent = '<' + data.email + '>';
      document.getElementById('detailDate').textContent = data.created_at;

      // Avatar initials
      var avatar = document.getElementById('detailAvatar');
      var nameParts = data.name.split(' ');
      var initials = (nameParts[0] || '').charAt(0).toUpperCase() + (nameParts[1] || '').charAt(0).toUpperCase();
      avatar.textContent = initials;

      // Message body (plain text → paragraphs)
      var msgText = data.message || '';
      var paragraphs = msgText.split('\n').filter(function (p) { return p.trim() !== ''; });
      var html = paragraphs.map(function (p) {
        // Escape HTML
        var div = document.createElement('div');
        div.textContent = p;
        return '<p>' + div.innerHTML + '</p>';
      }).join('');
      document.getElementById('detailText').innerHTML = html;

      // Replied badge
      var repliedBadge = document.getElementById('detailRepliedBadge');
      if (data.replied_at) {
        repliedBadge.classList.remove('d-none');
        repliedBadge.title = 'Yanıtlanma: ' + data.replied_at;
      } else {
        repliedBadge.classList.add('d-none');
      }

      // Sent reply display
      var sentReplySection = document.getElementById('sentReplySection');
      if (data.reply_text) {
        sentReplySection.classList.remove('d-none');
        document.getElementById('sentReplyDate').textContent = data.replied_at || '';
        var replyParagraphs = data.reply_text.split('\n').filter(function (p) { return p.trim() !== ''; });
        var replyHtml = replyParagraphs.map(function (p) {
          var d = document.createElement('div');
          d.textContent = p;
          return '<p>' + d.innerHTML + '</p>';
        }).join('');
        document.getElementById('sentReplyText').innerHTML = replyHtml;
      } else {
        sentReplySection.classList.add('d-none');
      }

      // Reply form info + reset
      document.getElementById('replyToInfo').textContent = data.name + ' <' + data.email + '>';
      document.getElementById('replySection').classList.add('d-none');
      document.getElementById('replyBody').value = '';
      document.getElementById('detailReplyBtn').classList.remove('active');

      // Phone section
      if (data.phone) {
        document.getElementById('detailPhoneSection').classList.remove('d-none');
        document.getElementById('detailPhone').textContent = data.phone;
        document.getElementById('detailPhoneLink').href = 'tel:' + data.phone;
        document.getElementById('detailPhoneBtn').classList.remove('d-none');
        document.getElementById('detailPhoneBtn').href = 'tel:' + data.phone;
      } else {
        document.getElementById('detailPhoneSection').classList.add('d-none');
        document.getElementById('detailPhoneBtn').classList.add('d-none');
      }

      // IP section
      if (data.ip_address) {
        document.getElementById('detailIpSection').classList.remove('d-none');
        document.getElementById('detailIp').textContent = data.ip_address;
      } else {
        document.getElementById('detailIpSection').classList.add('d-none');
      }

      document.getElementById('msgDetailContent').classList.remove('d-none');
      document.getElementById('msgDetailEmpty').classList.add('d-none');
    })
    .catch(function () {
      showToast('Mesaj yüklenemedi', 'danger');
    });
  };

  window.closeDetail = function () {
    document.getElementById('msgListPanel').classList.remove('detail-open');
    document.querySelectorAll('.msg-item').forEach(function (i) { i.classList.remove('active'); });
    document.getElementById('msgDetailContent').classList.add('d-none');
    document.getElementById('msgDetailEmpty').classList.remove('d-none');
    activeMessageId = null;
  };

  /* ==================== DELETE DETAIL ==================== */
  window.deleteDetail = function () {
    if (activeMessageId) {
      openDeleteModal(activeMessageId, document.getElementById('detailSender').textContent);
    }
  };

  /* ==================== FOLDER TOGGLE (MOBILE) ==================== */
  window.toggleFolders = function () {
    document.getElementById('msgFolders').classList.toggle('show');
  };

  /* ==================== PER PAGE ==================== */
  window.changePerPage = function (value) {
    var url = new URL(window.location.href);
    url.searchParams.set('per_page', value);
    url.searchParams.delete('page');
    window.location.href = url.toString();
  };

  /* ==================== BULK SELECT ==================== */
  window.toggleBulk = function () {
    var checked = document.querySelectorAll('.msg-checkbox:checked');
    var bar = document.getElementById('msgBulkBar');
    document.getElementById('bulkCount').textContent = checked.length;
    bar.classList.toggle('show', checked.length > 0);
  };

  window.bulkDelete = function () {
    var checked = document.querySelectorAll('.msg-checkbox:checked');
    if (checked.length === 0) {
      showToast('Lütfen mesaj seçin', 'warning');
      return;
    }

    AdminModal.confirm({
      title: 'Toplu Silme Onayı',
      message: checked.length + ' mesajı silmek istediğinize emin misiniz?',
      type: 'danger',
      confirmText: 'Evet, Sil',
      confirmIcon: 'bi bi-trash3',
      warning: 'Bu işlem geri alınamaz.'
    }).then(function (confirmed) {
      if (!confirmed) return;

      var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
      var completed = 0;
      var ids = [];
      checked.forEach(function (cb) { ids.push(cb.value); });

      ids.forEach(function (id) {
        fetch('/admin/contact-messages/' + id, {
          method: 'DELETE',
          headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
          }
        }).then(function () {
          completed++;
          if (completed === ids.length) {
            showToast(ids.length + ' mesaj silindi', 'success');
            setTimeout(function () { location.reload(); }, 800);
          }
        }).catch(function () {
          completed++;
        });
      });
    });
  };

  /* ==================== DELETE MODAL ==================== */
  window.openDeleteModal = function (messageId, messageName) {
    AdminModal.confirm({
      title: 'Silme Onayı',
      message: 'Bu mesajı silmek istediğinizden emin misiniz?',
      type: 'danger',
      confirmText: 'Evet, Sil',
      confirmIcon: 'bi bi-trash3',
      detailTitle: messageName,
      warning: 'Bu işlem geri alınamaz.'
    }).then(function (confirmed) {
      if (!confirmed) return;
      var form = document.createElement('form');
      form.method = 'POST';
      form.action = '/admin/contact-messages/' + messageId;
      form.innerHTML = '<input type="hidden" name="_token" value="' + document.querySelector('meta[name="csrf-token"]').getAttribute('content') + '"><input type="hidden" name="_method" value="DELETE">';
      document.body.appendChild(form);
      form.submit();
    });
  };

  /* ==================== REPLY ==================== */
  window.toggleReplyForm = function () {
    var section = document.getElementById('replySection');
    var btn = document.getElementById('detailReplyBtn');
    var isHidden = section.classList.contains('d-none');
    section.classList.toggle('d-none', !isHidden);
    btn.classList.toggle('active', isHidden);
    if (isHidden) {
      document.getElementById('replyBody').focus();
    }
  };

  window.sendReply = function () {
    if (!activeMessageId) return;

    var body = document.getElementById('replyBody').value.trim();
    if (!body) {
      showToast('Lütfen yanıt mesajını yazın.', 'warning');
      return;
    }
    if (body.length < 5) {
      showToast('Yanıt en az 5 karakter olmalıdır.', 'warning');
      return;
    }

    var btn = document.getElementById('sendReplyBtn');
    var origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Gönderiliyor...';

    var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    fetch('/admin/contact-messages/' + activeMessageId + '/reply', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify({ reply_body: body })
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
      if (data.success) {
        showToast(data.message, 'success');
        document.getElementById('replySection').classList.add('d-none');
        document.getElementById('replyBody').value = '';
        document.getElementById('detailReplyBtn').classList.remove('active');

        // Show replied badge
        var badge = document.getElementById('detailRepliedBadge');
        badge.classList.remove('d-none');
        badge.title = 'Yanıtlanma: ' + (data.replied_at || '');

        // Show sent reply
        var sentSection = document.getElementById('sentReplySection');
        sentSection.classList.remove('d-none');
        document.getElementById('sentReplyDate').textContent = data.replied_at || '';
        var replyPs = body.split('\n').filter(function (p) { return p.trim() !== ''; });
        var replyH = replyPs.map(function (p) {
          var d = document.createElement('div');
          d.textContent = p;
          return '<p>' + d.innerHTML + '</p>';
        }).join('');
        document.getElementById('sentReplyText').innerHTML = replyH;
      } else {
        showToast(data.message || 'Yanıt gönderilemedi.', 'danger');
      }
    })
    .catch(function () {
      showToast('Yanıt gönderilirken bir hata oluştu.', 'danger');
    })
    .finally(function () {
      btn.disabled = false;
      btn.innerHTML = origHtml;
    });
  };

  /* ==================== TOAST ==================== */
  function showToast(message, type) {
    if (typeof type === 'undefined') type = 'success';
    var titleMap = { success: 'Başarılı', error: 'Hata', danger: 'Hata', warning: 'Uyarı', info: 'Bilgi' };
    var modalType = type === 'error' ? 'danger' : type;
    if (typeof AdminModal !== 'undefined') {
      AdminModal.status({ title: titleMap[type] || 'Bilgi', message: message, type: modalType });
    }
  }

})();
