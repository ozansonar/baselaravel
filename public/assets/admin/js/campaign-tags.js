'use strict';

/**
 * Kişiselleştirme değişkenlerini tek tıkla ekler.
 *
 * Değişkenler ({first_name}, {email} …) elle yazılıyordu: süslü parantezi
 * unutmak ya da adı yanlış yazmak kolay ve hata gönderim anına kadar
 * görünmüyor. Kopyala düğmesi de tam çözmüyor, kullanıcı yine doğru yere
 * yapıştırmak zorunda.
 *
 * Burada değişken doğrudan imlecin olduğu yere yazılıyor — panoya dokunulmuyor,
 * alan terk edilmiyor. Hedef alan düğme grubunun data-tag-target'ında yazılı;
 * konu alanı düz bir input, gövde ise TinyMCE olduğu için iki ayrı yol var.
 */
(function () {
    var gruplar = document.querySelectorAll('.cmp-tags[data-tag-target]');

    if (gruplar.length === 0) {
        return;
    }

    /**
     * Düz metin alanına imleç konumundan ekler ve imleci eklenenin sonuna alır;
     * değere eklemek imleci metnin sonuna atardı ve araya yazmak imkânsız olurdu.
     */
    function metneEkle(alan, metin) {
        var bas = alan.selectionStart !== null ? alan.selectionStart : alan.value.length;
        var son = alan.selectionEnd !== null ? alan.selectionEnd : bas;

        alan.value = alan.value.slice(0, bas) + metin + alan.value.slice(son);

        var imlec = bas + metin.length;
        alan.focus();
        alan.setSelectionRange(imlec, imlec);

        // Doğrulama motoru ve karakter sayaçları değişikliği duymalı.
        alan.dispatchEvent(new Event('input', { bubbles: true }));
    }

    /**
     * TinyMCE'ye ekler. Editör kendi belgesinde çalıştığı için textarea'ya
     * yazmak ekrana yansımaz; eklentinin kendi komutu kullanılıyor.
     */
    function editoreEkle(id, metin) {
        if (typeof tinymce === 'undefined') {
            return false;
        }

        var editor = tinymce.get(id);

        // initialized: editör henüz kurulmadıysa komut çalışmaz.
        // isHidden: kaynak kodu görünümünde editör kapalı olur, o hâlde metin
        // yine textarea'ya yazılmalı.
        if (!editor || !editor.initialized || (typeof editor.isHidden === 'function' && editor.isHidden())) {
            return false;
        }

        editor.focus();
        editor.execCommand('mceInsertContent', false, metin);

        return true;
    }

    function geribildirim(dugme) {
        dugme.classList.add('cmp-tag--eklendi');

        window.setTimeout(function () {
            dugme.classList.remove('cmp-tag--eklendi');
        }, 700);
    }

    gruplar.forEach(function (grup) {
        var hedefSecici = grup.dataset.tagTarget;

        grup.querySelectorAll('.cmp-tag').forEach(function (dugme) {
            dugme.addEventListener('click', function () {
                var metin = dugme.dataset.tag;
                var hedef = document.querySelector(hedefSecici);

                if (!hedef) {
                    return;
                }

                // Önce editör deneniyor; alan zengin metin düzenleyiciye
                // çevrilmemişse (ya da editör yüklenememişse) textarea açıkta
                // kalır ve metin doğrudan ona yazılır.
                if (!editoreEkle(hedef.id, metin)) {
                    metneEkle(hedef, metin);
                }

                geribildirim(dugme);
            });
        });
    });
})();
