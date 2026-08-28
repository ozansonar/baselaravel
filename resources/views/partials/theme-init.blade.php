{{--
    Koyu/açık kipi ilk boyamadan önce yazar.

    Betik <head> içinde, stil dosyalarından önce çalışıyor: kip gövde
    boyanmadan belirlendiği için sayfa önce açık açılıp sonra koyuya atlamıyor
    (FOUC). Bu yüzden ayrı bir dosyaya alınamaz — dış dosya beklenirken ilk
    kare çizilirdi.

    Seçim yoksa işletim sisteminin tercihi geçerli; ziyaretçi düğmeye
    bastığında localStorage'a yazılıyor ve o tercih kalıcı oluyor.
    Ayrıntı: public/js/theme.js
--}}
<script>
    (function () {
        try {
            var stored = window.localStorage.getItem('theme');
            var theme = (stored === 'light' || stored === 'dark')
                ? stored
                : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-bs-theme', theme);
        } catch (e) {
            document.documentElement.setAttribute('data-bs-theme', 'light');
        }
    })();
</script>
