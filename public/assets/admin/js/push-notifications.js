/**
 * Push duyuruları — liste ve detay ekranı.
 *
 * Form tarafı (hedef seçimi, önizleme, cihaz sayısı) push-notification-form.js
 * dosyasında.
 */

/**
 * Silme onayı: formu doğru duyuruya yönlendirir.
 */
function openDeleteModal(id, title) {
    var form = document.getElementById('deleteForm');
    var label = document.getElementById('deletePushName');
    if (!form) return;

    form.action = window.location.pathname.replace(/\/$/, '') + '/' + id;
    if (label) label.textContent = title;

    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

/**
 * İptal onayı — detay ekranı.
 *
 * Form hedefi Blade'de yazılı: iptal edilecek duyuru zaten açık olan duyuru,
 * listeden seçilen bir kayıt değil.
 */
function openPushCancel(title) {
    var modal = document.getElementById('cancelModal');
    if (!modal) return;

    var label = document.getElementById('cancelPushName');
    if (label) label.textContent = title;

    new bootstrap.Modal(modal).show();
}
