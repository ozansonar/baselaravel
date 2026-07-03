/**
 * Product AI Fill
 * Generate product content via Gemini and populate all form fields.
 */
(function () {
    'use strict';

    window.aiFillProductAll = function () {
        var btn = document.getElementById('btnAiFillAll');
        if (!btn) return;

        var url = btn.getAttribute('data-url');
        var nameInput = document.getElementById('productName');
        var categorySelect = document.getElementById('productCategory');

        if (!nameInput || !url) return;

        var name = (nameInput.value || '').trim();

        if (name === '') {
            if (typeof AdminModal !== 'undefined') {
                AdminModal.status({
                    title: 'Eksik Bilgi',
                    message: 'Lütfen önce ürün adını girin.',
                    type: 'warning'
                });
            }
            nameInput.focus();
            return;
        }

        // CSRF token
        var tokenEl = document.querySelector('meta[name="csrf-token"]')
            || document.querySelector('input[name="_token"]');
        var csrfToken = tokenEl ? (tokenEl.getAttribute('content') || tokenEl.value) : '';

        // Loading state
        var originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-arrow-repeat spin me-1"></i><span class="ai-fill-label">Üretiyor...</span>';
        addSpinStyle();

        if (typeof AdminModal !== 'undefined') {
            AdminModal.loading.show({
                title: 'AI Üretiyor...',
                message: 'Ürün içeriği oluşturuluyor, lütfen bekleyin.'
            });
        }

        var body = new FormData();
        body.append('_token', csrfToken);
        body.append('name', name);
        if (categorySelect && categorySelect.value) {
            body.append('category_id', categorySelect.value);
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
                        message: 'Ürün içeriği AI ile dolduruldu. Göndermeden önce kontrol edin.',
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

    /**
     * Populate form fields from AI-generated data.
     */
    function applyAiData(data) {
        // Short description
        var excerpt = document.getElementById('productExcerpt');
        if (excerpt && data.short_description) {
            excerpt.value = data.short_description;
            if (typeof window.updateCharCounter === 'function') {
                window.updateCharCounter(excerpt, 300);
            }
        }

        // Detailed description (contenteditable + hidden textarea)
        var editor = document.getElementById('productEditor');
        var descField = document.getElementById('descriptionField');
        if (data.description) {
            if (editor) {
                editor.innerHTML = data.description;
                editor.dispatchEvent(new Event('input', { bubbles: true }));
            }
            if (descField) {
                descField.value = data.description;
            }
        }

        // SEO
        var seoTitle = document.getElementById('seoTitle');
        if (seoTitle && data.meta_title) {
            seoTitle.value = data.meta_title;
            if (typeof window.updateCharCounter === 'function') {
                window.updateCharCounter(seoTitle, 60);
            }
        }
        var seoDesc = document.getElementById('seoDescription');
        if (seoDesc && data.meta_description) {
            seoDesc.value = data.meta_description;
            if (typeof window.updateCharCounter === 'function') {
                window.updateCharCounter(seoDesc, 160);
            }
        }
        if (typeof window.updateSeoPreview === 'function') {
            window.updateSeoPreview();
        }

        // Features
        if (Array.isArray(data.features) && data.features.length > 0) {
            fillFeatures(data.features);
        }

        // Nutritions
        if (Array.isArray(data.nutritions) && data.nutritions.length > 0) {
            fillNutritions(data.nutritions);
        }
    }

    function fillFeatures(features) {
        var toggle = document.getElementById('hasFeatures');
        var tbody = document.getElementById('featuresTableBody');
        if (!toggle || !tbody) return;

        // Enable toggle and reveal section
        if (!toggle.checked) {
            toggle.checked = true;
            if (typeof window.toggleFeaturesSection === 'function') {
                window.toggleFeaturesSection();
            }
        }

        // Clear all existing rows
        tbody.innerHTML = '';

        // Add a fresh row for each feature and populate it
        features.forEach(function (feature, idx) {
            var row = document.createElement('tr');
            row.innerHTML =
                '<td>' +
                '  <input type="text" class="form-control form-control-sm"' +
                '         name="features[' + idx + '][icon]"' +
                '         placeholder="Ör: fa-solid fa-leaf">' +
                '</td>' +
                '<td>' +
                '  <input type="text" class="form-control form-control-sm"' +
                '         name="features[' + idx + '][text]"' +
                '         placeholder="Ör: %100 Doğal & Organik">' +
                '</td>' +
                '<td>' +
                '  <button type="button" class="usr-action-btn danger" onclick="removeFeatureRow(this)" title="Sil"><i class="bi bi-trash"></i></button>' +
                '</td>';
            tbody.appendChild(row);

            var inputs = row.querySelectorAll('input');
            if (inputs[0]) inputs[0].value = feature.icon || 'fa-solid fa-check';
            if (inputs[1]) inputs[1].value = feature.text || '';
        });
    }

    function fillNutritions(nutritions) {
        var toggle = document.getElementById('hasNutrition');
        var tbody = document.getElementById('nutritionTableBody');
        if (!toggle || !tbody) return;

        if (!toggle.checked) {
            toggle.checked = true;
            if (typeof window.toggleNutritionSection === 'function') {
                window.toggleNutritionSection();
            }
        }

        tbody.innerHTML = '';

        nutritions.forEach(function (nutrition, idx) {
            var row = document.createElement('tr');
            row.innerHTML =
                '<td>' +
                '  <input type="text" class="form-control form-control-sm"' +
                '         name="nutritions[' + idx + '][name]"' +
                '         placeholder="Ör: Enerji, Protein, Yağ...">' +
                '</td>' +
                '<td>' +
                '  <input type="text" class="form-control form-control-sm"' +
                '         name="nutritions[' + idx + '][per_value]"' +
                '         placeholder="Ör: 350 kcal">' +
                '</td>' +
                '<td>' +
                '  <input type="text" class="form-control form-control-sm"' +
                '         name="nutritions[' + idx + '][daily_value]"' +
                '         placeholder="Ör: %17">' +
                '</td>' +
                '<td>' +
                '  <button type="button" class="usr-action-btn danger" onclick="removeNutritionRow(this)" title="Sil"><i class="bi bi-trash"></i></button>' +
                '</td>';
            tbody.appendChild(row);

            var inputs = row.querySelectorAll('input');
            if (inputs[0]) inputs[0].value = nutrition.name || '';
            if (inputs[1]) inputs[1].value = nutrition.per_value || '';
            if (inputs[2]) inputs[2].value = nutrition.daily_value || '';
        });
    }

    /**
     * Inject spin animation CSS once (used by the loading icon).
     */
    function addSpinStyle() {
        if (document.getElementById('ai-fill-spin-style')) return;
        var style = document.createElement('style');
        style.id = 'ai-fill-spin-style';
        style.textContent = '@keyframes aiFillSpin{from{transform:rotate(0)}to{transform:rotate(360deg)}}'
            + '#btnAiFillAll .spin{display:inline-block;animation:aiFillSpin 0.9s linear infinite;}';
        document.head.appendChild(style);
    }
})();
