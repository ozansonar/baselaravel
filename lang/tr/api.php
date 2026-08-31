<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| API metinleri
|--------------------------------------------------------------------------
| Yalnızca API'ye özgü olanlar burada. Ön yüzle ortak metinler (giriş
| başarısız, hesap devre dışı, iletişim mesajı alındı) `site` grubundan
| okunuyor — aynı cümlenin iki dosyada iki ayrı hâli olmasın diye: biri
| panelden düzeltilir, öteki geride kalırdı.
*/

return [

    'common' => [
        'ok'                 => 'İşlem başarılı.',
        'error'              => 'İstek işlenemedi.',
        'not_found'          => 'Kayıt bulunamadı.',
        'forbidden'          => 'Bu işlem için yetkiniz yok.',
        'method_not_allowed' => 'Bu adres için kullanılan yöntem desteklenmiyor.',
        'validation_failed'  => 'Gönderilen bilgiler geçersiz.',
        'too_many_requests'  => 'Çok fazla istek gönderdiniz. Lütfen biraz bekleyip tekrar deneyin.',
        'server_error'       => 'Beklenmeyen bir hata oluştu. Lütfen daha sonra tekrar deneyin.',
        'unavailable'        => 'Servis şu anda kullanılamıyor.',
    ],

    'auth' => [
        'registered'            => 'Kaydınız oluşturuldu.',
        'logged_in'             => 'Giriş yapıldı.',
        'logged_out'            => 'Çıkış yapıldı.',
        'unauthenticated'       => 'Bu işlem için giriş yapmalısınız.',
        'registration_disabled' => 'Yeni kayıtlar şu anda kapalı.',
        'verification_sent'     => 'Doğrulama bağlantısı e-posta adresinize gönderildi.',
        'already_verified'      => 'E-posta adresiniz zaten doğrulanmış.',
        'email_unverified'      => 'Bu işlem için önce e-posta adresinizi doğrulamanız gerekiyor.',
    ],

    'password' => [
        'code_sent'     => 'Adres kayıtlıysa şifre sıfırlama kodu gönderildi.',
        'code_invalid'  => 'Kod geçersiz ya da süresi dolmuş. Yeni bir kod isteyin.',
        'code_required' => 'Sıfırlama kodu zorunludur.',
        'code_digits'   => 'Sıfırlama kodu 6 haneli olmalıdır.',
        'reset'         => 'Şifreniz değiştirildi. Yeni şifrenizle giriş yapabilirsiniz.',
    ],

    'pages' => [
        'not_found' => 'Sayfa bulunamadı.',
    ],

    'menus' => [
        'not_found' => 'Bu konumda yayında bir menü yok.',
    ],

    'settings' => [
        'group_not_found' => 'Böyle bir ayar grubu yok.',
    ],

    'translations' => [
        'group_not_found' => 'Böyle bir çeviri grubu yok.',
    ],

    'blog' => [
        'category_not_found' => 'Böyle bir kategori yok.',
        'post_not_found'     => 'Yazı bulunamadı.',
    ],

    'gallery' => [
        'category_not_found' => 'Böyle bir kategori yok.',
        'invalid_type'       => 'Geçersiz tür. Kullanılabilir değerler: photo, video.',
    ],

];
