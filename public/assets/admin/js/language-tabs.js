(function () {
    'use strict';

    // Remember which language tab was open so a failed save, or simply coming
    // back to the form, does not drop the translator on the first tab again.
    function initLanguageTabs() {
        document.querySelectorAll('[id$="ActiveLocale"]').forEach(function (input) {
            var listId = input.id.replace('ActiveLocale', '');
            var list = document.getElementById(listId);

            if (!list) {
                return;
            }

            list.querySelectorAll('.lang-tabs__btn').forEach(function (button) {
                button.addEventListener('shown.bs.tab', function () {
                    input.value = this.dataset.locale || '';
                });
            });

            // A tab holding a validation error takes precedence over the
            // remembered one; that is where the user needs to look.
            var invalid = list.querySelector('.lang-tabs__btn--invalid');

            if (invalid && typeof bootstrap !== 'undefined') {
                bootstrap.Tab.getOrCreateInstance(invalid).show();
                input.value = invalid.dataset.locale || '';
            }
        });
    }

    document.addEventListener('DOMContentLoaded', initLanguageTabs);
})();
