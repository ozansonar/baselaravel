/**
 * City Page AI Fill
 * Generate city landing page content via Gemini and populate form fields.
 */
(function () {
    'use strict';

    window.aiFillCityAll = function () {
        var btn = document.getElementById('btnAiFillCity');
        if (!btn) return;

        var url = btn.getAttribute('data-url');
        var cityNameInput = document.getElementById('city_name');
        var regionInput = document.getElementById('region');

        if (!cityNameInput || !url) return;

        var cityName = (cityNameInput.value || '').trim();

        if (cityName === '') {
            if (typeof AdminModal !== 'undefined') {
                AdminModal.status({
                    title: 'Eksik Bilgi',
                    message: 'Lütfen önce şehir adını girin.',
                    type: 'warning'
                });
            }
            cityNameInput.focus();
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
                message: cityName + ' için içerik oluşturuluyor, lütfen bekleyin.'
            });
        }

        var body = new FormData();
        body.append('_token', csrfToken);
        body.append('city_name', cityName);
        if (regionInput && regionInput.value) {
            body.append('region', regionInput.value);
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
                        message: cityName + ' için içerik üretildi. Göndermeden önce kontrol edin.',
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

    function setIfEmpty(id, value, maxLen) {
        var el = document.getElementById(id);
        if (!el || !value) return;
        // Always overwrite — user is explicitly asking AI to refill
        el.value = value;
        if (typeof window.updateCharCounter === 'function' && maxLen) {
            window.updateCharCounter(el, maxLen);
        }
    }

    function applyAiData(data) {
        setIfEmpty('title', data.title);
        setIfEmpty('meta_title', data.meta_title, 70);
        setIfEmpty('meta_description', data.meta_description, 200);
        setIfEmpty('hero_heading', data.hero_heading);
        setIfEmpty('hero_description', data.hero_description);
        setIfEmpty('shipping_note', data.shipping_note);
        setIfEmpty('delivery_time', data.delivery_time);

        // Content (TinyMCE)
        if (data.content) {
            if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                tinymce.get('content').setContent(data.content);
            } else {
                var contentField = document.getElementById('content');
                if (contentField) {
                    contentField.value = data.content;
                }
            }
        }

        if (typeof window.updateSeoPreview === 'function') {
            window.updateSeoPreview();
        }
    }

    function addSpinStyle() {
        if (document.getElementById('city-ai-fill-spin-style')) return;
        var style = document.createElement('style');
        style.id = 'city-ai-fill-spin-style';
        style.textContent = '@keyframes cityAiFillSpin{from{transform:rotate(0)}to{transform:rotate(360deg)}}'
            + '.spin{display:inline-block;animation:cityAiFillSpin 0.9s linear infinite;}';
        document.head.appendChild(style);
    }
})();
