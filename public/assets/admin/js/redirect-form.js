'use strict';

/**
 * Yönlendirme formu: durum koduna göre hedef alanını yönetir.
 *
 * 404 ve 410 ziyaretçiyi bir yere göndermez, sonucu bildirir. O kodlar
 * seçildiğinde hedef alanı hem gizleniyor hem de kuralı kaldırılıyor: görünmez
 * bir zorunlu alan, kimsenin göremediği bir hatayla formu kilitler.
 */
(function () {
    var select = document.getElementById('status_code');
    var field = document.getElementById('newUrlField');
    var input = document.getElementById('new_url');
    var description = document.getElementById('statusDescription');

    if (!select || !field || !input) {
        return;
    }

    var KURAL = 'validate[required,custom[redirectTarget],maxSize[500]]';
    var KURAL_SECIMLIK = 'validate[custom[redirectTarget],maxSize[500]]';

    function uygula() {
        var secili = select.options[select.selectedIndex];
        var yonlendirir = !secili || secili.dataset.redirects !== '0';

        field.classList.toggle('d-none', !yonlendirir);
        input.setAttribute('data-validation-engine', yonlendirir ? KURAL : KURAL_SECIMLIK);

        if (!yonlendirir) {
            // Alan gizlendiğinde eski değer formla birlikte gitmesin.
            input.value = '';
        }

        if (description && secili) {
            description.textContent = secili.dataset.description || '';
        }
    }

    select.addEventListener('change', uygula);
    uygula();
})();
