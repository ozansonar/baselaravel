{{-- Betiklerin okuduğu metin sözlüğü.

     Ön yüz JS dosyalarının hiçbirinde yazılı metin yok; hepsi buradan geliyor,
     dolayısıyla sayfanın dilinden. Betiklerden önce basılıyor: sonra basılsaydı
     form-validation.js kendi kurallarını sözlük yokken kurar ve uyarılar boş
     kalırdı.

     siteText() burada tanımlanıyor, her dosyada ayrı ayrı değil — beş dosyanın
     aynı üç satırı kopyalaması yerine tek yer.

     Kaynak: App\Support\FrontScriptText --}}
<script nonce="{{ csp_nonce() }}">
window.SiteText = {!! json_encode(\App\Support\FrontScriptText::all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
window.siteText = function (key, replacements) {
    var text = (window.SiteText && window.SiteText[key]) || '';

    if (replacements) {
        Object.keys(replacements).forEach(function (name) {
            text = text.replace(':' + name, replacements[name]);
        });
    }

    return text;
};
</script>
