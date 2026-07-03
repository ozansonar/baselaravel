// ==================== TOPIC CLUSTER — Admin ====================
//
// Bir ürün için 7 yeni konu üret + her konuyu tek tıkla blog yazısına
// çevir. Tüm işler AJAX, sayfa yenilemez. Cron rotasyonunu BOZMAZ.

(function () {
    'use strict';

    var csrfToken = '';
    document.addEventListener('DOMContentLoaded', function () {
        var meta = document.querySelector('meta[name="csrf-token"]');
        csrfToken = meta ? meta.getAttribute('content') : '';

        bindGenerateButtons();
        bindConvertButtons();
        bindDeleteButtons();
    });

    /* ─────────────── Generate (AI ile 7 konu üret) ─────────────── */

    function bindGenerateButtons() {
        document.querySelectorAll('.topic-generate-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var productId = btn.dataset.productId;
                var productName = btn.dataset.productName;
                generateTopics(btn, productId, productName);
            });
        });
    }

    function generateTopics(btn, productId, productName) {
        var originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-arrow-repeat topic-spin me-1"></i>AI üretiyor...';
        addSpinStyle();

        if (typeof AdminModal !== 'undefined') {
            AdminModal.loading.show({
                title: 'AI Üretiyor...',
                message: productName + ' için 7 konu öneriliyor (~30-60 sn).'
            });
        }

        var body = new FormData();
        body.append('_token', csrfToken);
        body.append('product_id', productId);

        fetch('/admin/product-topics/generate', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: body,
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); })
            .then(function (res) {
                if (!res.ok || !res.json.success) {
                    showError(res.json.message || 'AI konuları üretemedi.');
                    return;
                }

                appendTopicsToTable(productId, res.json.topics || []);
                showSuccess(res.json.message || '7 konu eklendi.');

                // Status mesajının okunabilmesi için 2sn beklet, sonra
                // sayfayı reload ederek tam state'i (durum badge'leri,
                // converted_at vb.) yeniden çek.
                setTimeout(function () { window.location.reload(); }, 2000);
            })
            .catch(function () {
                showError('Bağlantı hatası. Lütfen tekrar deneyin.');
            })
            .finally(function () {
                if (typeof AdminModal !== 'undefined') AdminModal.loading.hide();
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            });
    }

    /**
     * AJAX ile gelen topic'leri tabloya hemen eklemek için (UX) — sayfa
     * reload'undan önce kısa süre görünmesi yeterli.
     */
    function appendTopicsToTable(productId, topics) {
        if (!topics.length) return;
        var tbody = document.querySelector('.topics-tbody[data-product-id="' + productId + '"]');
        if (!tbody) return;

        topics.forEach(function (t) {
            var tr = document.createElement('tr');
            tr.id = 'topic-' + t.id;
            tr.innerHTML = ''
                + '<td><div class="cl-content-info"><span class="cl-content-title">' + escapeHtml(t.title) + '</span></div></td>'
                + '<td class="d-none d-md-table-cell"><span class="cl-category-badge">' + escapeHtml(t.search_intent || '-') + '</span></td>'
                + '<td><span class="usr-status-badge pending"><i class="bi bi-hourglass"></i> Bekliyor</span></td>'
                + '<td><div class="usr-actions"><span class="text-muted small">Yenileniyor...</span></div></td>';
            tbody.appendChild(tr);
        });
    }

    /* ─────────────── Convert (topic → blog yazısı) ─────────────── */

    function bindConvertButtons() {
        document.querySelectorAll('.topic-convert-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var topicId = btn.dataset.topicId;
                var topicTitle = btn.dataset.topicTitle;
                convertTopic(btn, topicId, topicTitle);
            });
        });
    }

    function convertTopic(btn, topicId, topicTitle) {
        var originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-arrow-repeat topic-spin"></i>';

        if (typeof AdminModal !== 'undefined') {
            AdminModal.loading.show({
                title: 'Blog yazısı üretiliyor...',
                message: '"' + topicTitle + '" için içerik oluşturuluyor (~30-60 sn).'
            });
        }

        fetch('/admin/product-topics/' + topicId + '/convert', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); })
            .then(function (res) {
                if (!res.ok || !res.json.success) {
                    showError(res.json.message || 'Blog oluşturulamadı.');
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                    return;
                }

                replaceTopicRow(topicId, res.json.topic);
                showSuccess('Blog yazısı oluşturuldu: ' + topicTitle);
            })
            .catch(function () {
                showError('Bağlantı hatası. Lütfen tekrar deneyin.');
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            })
            .finally(function () {
                if (typeof AdminModal !== 'undefined') AdminModal.loading.hide();
            });
    }

    function replaceTopicRow(topicId, payload) {
        var tr = document.getElementById('topic-' + topicId);
        if (!tr || !payload || !payload.blog_post) return;

        var statusCell = tr.querySelector('.topic-status-cell');
        if (statusCell) {
            statusCell.innerHTML = '<span class="usr-status-badge active"><i class="bi bi-check-circle"></i> Blog\'a çevrildi</span>';
        }

        var actionsCell = tr.querySelector('.topic-actions');
        if (actionsCell) {
            actionsCell.innerHTML = ''
                + '<a class="usr-action-btn" title="Blog Yazısını Düzenle" href="' + payload.blog_post.edit_url + '">'
                + '<i class="bi bi-pencil"></i></a>';
        }
    }

    /* ─────────────── Delete ─────────────── */

    function bindDeleteButtons() {
        document.querySelectorAll('.topic-delete-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var topicTitle = btn.dataset.topicTitle;
                var form = btn.closest('.topic-delete-form');
                if (!form) return;

                if (typeof AdminModal === 'undefined') return;

                AdminModal.confirm({
                    title: 'Konuyu Sil',
                    message: 'Bu konuyu silmek istediğinize emin misiniz?',
                    type: 'danger',
                    confirmText: 'Evet, Sil',
                    confirmIcon: 'bi bi-trash3',
                    detailTitle: topicTitle,
                    warning: 'Konu silinir; çevrilmiş blog yazısı silinmez.'
                }).then(function (confirmed) {
                    if (confirmed) form.submit();
                });
            });
        });
    }

    /* ─────────────── Helpers ─────────────── */

    function showSuccess(msg) {
        if (typeof AdminModal !== 'undefined') {
            AdminModal.status({ title: 'Başarılı', message: msg, type: 'success' });
        }
    }

    function showError(msg) {
        if (typeof AdminModal !== 'undefined') {
            AdminModal.status({ title: 'Hata', message: msg, type: 'danger' });
        }
    }

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function addSpinStyle() {
        if (document.getElementById('topic-spin-style')) return;
        var style = document.createElement('style');
        style.id = 'topic-spin-style';
        style.textContent = '@keyframes topicSpin{from{transform:rotate(0)}to{transform:rotate(360deg)}}'
            + '.topic-spin{display:inline-block;animation:topicSpin 0.9s linear infinite;}';
        document.head.appendChild(style);
    }
})();
