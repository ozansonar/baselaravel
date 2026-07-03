/**
 * Blog AI Fill
 * Generate blog content via AI and populate all form fields.
 */
(function () {
    'use strict';

    window.aiFillBlogAll = function () {
        var btn = document.getElementById('btnAiFillBlog');
        if (!btn) return;

        var url = btn.getAttribute('data-url');
        var titleInput = document.getElementById('title');
        var categorySelect = document.getElementById('blog_category_id');

        if (!titleInput || !url) return;

        var title = (titleInput.value || '').trim();

        if (title === '') {
            if (typeof AdminModal !== 'undefined') {
                AdminModal.status({
                    title: 'Eksik Bilgi',
                    message: 'Lütfen önce blog başlığını girin.',
                    type: 'warning'
                });
            }
            titleInput.focus();
            return;
        }

        var tokenEl = document.querySelector('meta[name="csrf-token"]')
            || document.querySelector('input[name="_token"]');
        var csrfToken = tokenEl ? (tokenEl.getAttribute('content') || tokenEl.value) : '';

        var originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-arrow-repeat spin me-1"></i><span>Üretiyor...</span>';
        addSpinStyle();

        if (typeof AdminModal !== 'undefined') {
            AdminModal.loading.show({
                title: 'AI Üretiyor...',
                message: 'Blog içeriği oluşturuluyor, lütfen bekleyin.'
            });
        }

        var body = new FormData();
        body.append('_token', csrfToken);
        body.append('title', title);
        if (categorySelect && categorySelect.value) {
            body.append('blog_category_id', categorySelect.value);
        }

        fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: body,
            credentials: 'same-origin'
        })
            .then(function (response) {
                return response.json().then(function (json) {
                    return { status: response.status, json: json };
                });
            })
            .then(function (res) {
                if (!res.json || res.json.success !== true) {
                    var msg = (res.json && res.json.message) ? res.json.message : 'AI içeriği üretilemedi.';
                    if (typeof AdminModal !== 'undefined') {
                        AdminModal.status({ title: 'Hata', message: msg, type: 'danger' });
                    }
                    return;
                }

                applyAiData(res.json.data || {});

                if (typeof AdminModal !== 'undefined') {
                    AdminModal.status({
                        title: 'Başarılı',
                        message: 'Blog içeriği AI ile dolduruldu. Göndermeden önce kontrol edin.',
                        type: 'success'
                    });
                }
            })
            .catch(function () {
                if (typeof AdminModal !== 'undefined') {
                    AdminModal.status({
                        title: 'Bağlantı Hatası',
                        message: 'AI servisine ulaşılamadı. Lütfen tekrar deneyin.',
                        type: 'danger'
                    });
                }
            })
            .finally(function () {
                if (typeof AdminModal !== 'undefined') {
                    AdminModal.loading.hide();
                }
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            });
    };

    function applyAiData(data) {
        // Excerpt
        var excerpt = document.getElementById('excerpt');
        if (excerpt && data.excerpt) {
            excerpt.value = data.excerpt;
            if (typeof window.updateCharCounter === 'function') {
                window.updateCharCounter(excerpt, 300);
            }
        }

        // Body (TinyMCE)
        if (data.body) {
            if (typeof tinymce !== 'undefined' && tinymce.get('body')) {
                tinymce.get('body').setContent(data.body);
            } else {
                var bodyField = document.getElementById('body');
                if (bodyField) {
                    bodyField.value = data.body;
                }
            }
        }

        // SEO
        var metaTitle = document.getElementById('meta_title');
        if (metaTitle && data.meta_title) {
            metaTitle.value = data.meta_title;
            if (typeof window.updateCharCounter === 'function') {
                window.updateCharCounter(metaTitle, 60);
            }
        }

        var metaDesc = document.getElementById('meta_description');
        if (metaDesc && data.meta_description) {
            metaDesc.value = data.meta_description;
            if (typeof window.updateCharCounter === 'function') {
                window.updateCharCounter(metaDesc, 160);
            }
        }

        if (typeof window.updateSeoPreview === 'function') {
            window.updateSeoPreview();
        }
    }

    function addSpinStyle() {
        if (document.getElementById('ai-fill-spin-style')) return;
        var style = document.createElement('style');
        style.id = 'ai-fill-spin-style';
        style.textContent = '@keyframes aiFillSpin{from{transform:rotate(0)}to{transform:rotate(360deg)}}'
            + '.spin{display:inline-block;animation:aiFillSpin 0.9s linear infinite;}';
        document.head.appendChild(style);
    }
})();
