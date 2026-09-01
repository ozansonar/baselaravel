'use strict';

/**
 * Şifre alanlarına göster/gizle düğmesi.
 *
 * Her forma elle düğme yazmak yerine sayfadaki şifre alanları taranıyor: yeni
 * bir form eklendiğinde de kendiliğinden çalışır, unutulacak bir yer kalmaz.
 *
 * Girdinin kendisi yerinde kalır, yalnız yanına bir düğme eklenir; name,
 * autocomplete ve doğrulama nitelikleri aynen çalışmaya devam eder. Alan bir
 * gruba (input-group) oturuyorsa düğme o grubun içine konur — grup zaten tek
 * satır olduğu için hizalama şaşmaz. Grupsuz alanlarda girdi ince bir
 * sarmalayıcıya alınır.
 *
 * Ön yüz çok dilli: düğmenin okunur adı window.SiteText'ten (partials/js-lang)
 * alınıyor, böylece metin sayfanın diliyle geliyor.
 *
 * Form gönderilirken alan şifre kipine geri döner: açık bırakılan bir alan
 * tarayıcının form geçmişine düz metin olarak düşebilirdi.
 */
(function () {
    var READY_FLAG = 'pwToggleReady';
    var ICON_SHOW = 'fa-solid fa-eye';
    var ICON_HIDE = 'fa-solid fa-eye-slash';

    var LABEL_SHOW = window.siteText('showPassword');
    var LABEL_HIDE = window.siteText('hidePassword');

    /**
     * Düğmenin oturacağı kap: varsa alanın grubu, yoksa yeni bir sarmalayıcı.
     */
    function resolveHost(input) {
        var group = input.closest('.input-group');

        if (group) {
            group.classList.add('pw-host');

            return group;
        }

        var wrapper = document.createElement('span');
        wrapper.className = 'pw-host pw-host--bare';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);

        return wrapper;
    }

    function createButton() {
        var button = document.createElement('button');

        button.type = 'button';
        button.className = 'pw-toggle';
        button.setAttribute('aria-pressed', 'false');
        button.setAttribute('aria-label', LABEL_SHOW);
        button.title = LABEL_SHOW;
        button.innerHTML = '<i class="' + ICON_SHOW + '"></i>';

        return button;
    }

    function toggle(input, button) {
        var revealing = input.type === 'password';

        input.type = revealing ? 'text' : 'password';

        // Gönderim sırasında geri çevrilecek alanlar bu işaretten bulunuyor.
        if (revealing) {
            input.dataset.pwVisible = '1';
        } else {
            delete input.dataset.pwVisible;
        }

        button.setAttribute('aria-pressed', revealing ? 'true' : 'false');
        button.setAttribute('aria-label', revealing ? LABEL_HIDE : LABEL_SHOW);
        button.title = revealing ? LABEL_HIDE : LABEL_SHOW;
        button.firstChild.className = revealing ? ICON_HIDE : ICON_SHOW;

        // Odak alana dönsün, imleç metnin sonunda kalsın: kullanıcı yazmaya
        // kaldığı yerden devam etsin.
        input.focus();

        try {
            var end = input.value.length;
            input.setSelectionRange(end, end);
        } catch (error) {
            // Kimi tarayıcı şifre kipinde imleç konumuna izin vermiyor; sorun değil.
        }
    }

    function enhance(input) {
        if (input.dataset[READY_FLAG] || input.disabled || input.readOnly) {
            return;
        }

        if (input.hasAttribute('data-no-pw-toggle')) {
            return;
        }

        input.dataset[READY_FLAG] = '1';
        input.classList.add('pw-input');

        var host = resolveHost(input);
        var button = createButton();

        button.addEventListener('click', function () {
            toggle(input, button);
        });

        host.appendChild(button);
    }

    function enhanceAll(root) {
        var scope = root && root.querySelectorAll ? root : document;

        Array.prototype.forEach.call(
            scope.querySelectorAll('input[type="password"]'),
            enhance
        );
    }

    document.addEventListener('DOMContentLoaded', function () {
        enhanceAll(document);

        // Sonradan açılan formlardaki alanlar da kapsansın.
        if (typeof MutationObserver === 'undefined') {
            return;
        }

        new MutationObserver(function (records) {
            records.forEach(function (record) {
                Array.prototype.forEach.call(record.addedNodes, function (node) {
                    if (node.nodeType !== 1) {
                        return;
                    }

                    if (node.matches && node.matches('input[type="password"]')) {
                        enhance(node);
                    }

                    enhanceAll(node);
                });
            });
        }).observe(document.body, { childList: true, subtree: true });
    });

    // Açık bırakılan alan şifre kipine dönmeden gönderilmesin.
    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (!form || !form.querySelectorAll) {
            return;
        }

        Array.prototype.forEach.call(
            form.querySelectorAll('input[data-pw-visible="1"]'),
            function (input) {
                input.type = 'password';
                delete input.dataset.pwVisible;

                var button = input.parentNode.querySelector('.pw-toggle');

                if (button) {
                    button.setAttribute('aria-pressed', 'false');
                    button.setAttribute('aria-label', LABEL_SHOW);
                    button.title = LABEL_SHOW;
                    button.firstChild.className = ICON_SHOW;
                }
            }
        );
    }, true);
})();
